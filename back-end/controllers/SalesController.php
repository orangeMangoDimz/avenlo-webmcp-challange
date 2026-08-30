<?php
/**
 * Sales 列表与统计接口（Sales 角色用户）
 */

require_once __DIR__ . '/../models/AdminUser.php';
require_once __DIR__ . '/../models/AdminPermission.php';
require_once __DIR__ . '/../models/Department.php';
require_once __DIR__ . '/../models/Position.php';
require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/../models/SalesReferralSettings.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/ClientIp.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../services/SalesPerformanceMetrics.php';

class SalesController {
    private $userModel;
    private $departmentModel;
    private $positionModel;
    private $salesRoleId;
    private $salesDashboardViewPermissionId;
    /** @var SalesReferralSettings */
    private $referralSettingsModel;

    public function __construct() {
        $this->userModel = new AdminUser();
        $this->departmentModel = new Department();
        $this->positionModel = new Position();
        $this->referralSettingsModel = new SalesReferralSettings();
        $config = require __DIR__ . '/../config/app.php';
        $this->salesRoleId = (int)($config['special_roles']['sales_role_id'] ?? 6);
        $permRow = (new AdminPermission())->findByKey('page_salesdashboard_view');
        $this->salesDashboardViewPermissionId = $permRow ? (int)($permRow['id'] ?? 0) : 0;
    }

    /**
     * Sales 列表 + 顶部统计（「销售」= roleId=Sales 或 角色拥有 Sales Dashboard View）
     * GET /api/sales?page=1&per_page=10&search=xxx
     * 返回: items, pagination, stats(totalSales, activeSales, inactiveSales)
     */
    public function index() {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? $_GET['per_page'] : 10;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        if ($perPage === 'all' || $perPage === '') {
            $perPage = 9999;
        } else {
            $perPage = (int)$perPage;
            if ($perPage < 1) $perPage = 10;
        }

        $result = $this->userModel->getSalesListPaginate($page, $perPage, $search, $this->salesRoleId, $this->salesDashboardViewPermissionId);

        $items = $result['items'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

        $config = require __DIR__ . '/../config/app.php';
        $baseUrl = $config['client_frontend_url'] ?? 'http://localhost:9502';

        $departmentMap = $this->getDepartmentNameMap();
        $positionMap = $this->getPositionNameMap();

        $salesIds = array_map(function ($u) { return (int)($u['id'] ?? 0); }, $items);
        $bindCounts = $this->getSalesBindCounts($salesIds);
        $referralMap = $this->referralSettingsModel->getOrCreateByUserIds($salesIds);

        $list = [];
        foreach ($items as $u) {
            $sid = (int)($u['id'] ?? 0);
            $item = $this->mapUserToSalesItem($u, $baseUrl, $departmentMap, $positionMap, $referralMap[$sid] ?? null);
            if (isset($bindCounts[$sid])) {
                $item['totalIbs'] = $bindCounts[$sid]['ibs'];
                $item['totalClients'] = $bindCounts[$sid]['clients'];
            }
            $list[] = $item;
        }

        $stats = $this->userModel->getSalesListStats($this->salesRoleId, $this->salesDashboardViewPermissionId);

        Response::success([
            'items' => $list,
            'pagination' => [
                'total' => (int)$total,
                'per_page' => $perPage,
                'page' => $page,
                'total_pages' => $totalPages,
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * 仅获取顶部统计（「销售」口径）
     * GET /api/sales/stats
     */
    public function stats() {
        Response::success($this->userModel->getSalesListStats($this->salesRoleId, $this->salesDashboardViewPermissionId));
    }

    /**
     * 记录 Sales 推荐链接访问（客户端中间页调用，按 IP + 后缀 24 小时内只计 1 次）
     * POST /api/sales/sales-referral-visit  Body: { "ref": "suffix" }
     * 公开接口，不鉴权，供 CORS 由网站配置统一处理。
     */
    public function salesReferralVisit() {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $ref = isset($data['ref']) ? trim((string)$data['ref']) : '';
        if ($ref === '') {
            Response::success(['recorded' => false, 'message' => 'Missing ref']);
            return;
        }
        $salesId = $this->referralSettingsModel->getUserIdBySuffix($ref);
        if ($salesId <= 0) {
            Response::success(['recorded' => false, 'message' => 'Invalid ref']);
            return;
        }
        $this->referralSettingsModel->ensureSettingsRow($salesId, $ref);
        $ip = \ClientIp::getClientIp();
        $db = \Database::getInstance();
        $since = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $existing = $db->fetchOne(
            'SELECT id FROM sales_referral_visit_log WHERE ip = :ip AND referralSuffix = :suffix AND createdAt > :since LIMIT 1',
            ['ip' => $ip, 'suffix' => $ref, 'since' => $since]
        );
        if ($existing !== false && $existing !== null) {
            Response::success(['recorded' => false, 'message' => 'Already counted recently']);
            return;
        }
        $db->query(
            'INSERT INTO sales_referral_visit_log (ip, referralSuffix, createdAt) VALUES (:ip, :suffix, NOW())',
            ['ip' => $ip, 'suffix' => $ref]
        );
        $this->referralSettingsModel->incrementUrlClicks($salesId);
        Response::success(['recorded' => true]);
    }

    /**
     * 当前登录用户作为 Sales 的资料（用于 Sales Dashboard）
     * GET /api/sales/me
     * 「销售」= roleId=Sales 或 拥有 Sales Dashboard View 权限；非销售时返回 success + null，不报 403
     */
    public function me() {
        $current = AuthMiddleware::getCurrentUser();
        $userId = isset($current['userId']) ? (int)$current['userId'] : 0;
        if ($userId <= 0) {
            Response::error('Unauthorized', 401);
        }
        $user = $this->userModel->getUserFullInfo($userId);
        if (!$user) {
            Response::success(null);
            return;
        }
        $roleId = (int)($user['roleId'] ?? 0);
        // 超级管理员（roleId == 1）默认拥有所有权限，正常显示 Your Sales Referral URL
        $isSales = ($roleId === 1) || ($roleId === $this->salesRoleId) || $this->userModel->hasRolePermission($userId, $this->salesDashboardViewPermissionId);
        if (!$isSales) {
            Response::success(null);
            return;
        }
        $config = require __DIR__ . '/../config/app.php';
        $baseUrl = $config['client_frontend_url'];
        $departmentMap = $this->getDepartmentNameMap();
        $positionMap = $this->getPositionNameMap();
        $referralRow = $this->referralSettingsModel->getOrCreateByUserId($userId);
        $item = $this->mapUserToSalesItem($user, $baseUrl, $departmentMap, $positionMap, $referralRow);
        $bindCounts = $this->getSalesBindCounts([$userId]);
        if (isset($bindCounts[$userId])) {
            $item['totalIbs'] = $bindCounts[$userId]['ibs'];
            $item['totalClients'] = $bindCounts[$userId]['clients'];
        }
        Response::success($item);
    }

    /**
     * One sales user's performance over a whole month, for the Sales Dashboard cards.
     * Same aggregates as the Daily Report page (SalesPerformanceMetrics), month-wide
     * instead of a single day, so the two pages can never disagree.
     * GET /api/sales/{salesId}/monthly-performance?month=YYYY-MM&tzOffset=
     */
    public function monthlyPerformance($salesId) {
        $salesId = (int) $salesId;
        if ($salesId <= 0) {
            Response::error('Invalid sales id', 400);
            return;
        }

        $current = AuthMiddleware::getCurrentUser();
        $userId = isset($current['userId']) ? (int) $current['userId'] : 0;
        $roleId = isset($current['roleId']) ? (int) $current['roleId'] : 0;
        if ($userId <= 0) {
            Response::unauthorized();
            return;
        }

        // A sales user may only read their own numbers; sales management and the super
        // admin may read anyone's.
        $config = require __DIR__ . '/../config/app.php';
        $salesManagerRoleId = (int) ($config['special_roles']['sales_manager_role_id'] ?? 0);
        $isSalesManagement = ($roleId === $salesManagerRoleId)
            || (new AdminPermission())->userHasPermission($userId, 'page_saleslist_view');
        if ($salesId !== $userId && $roleId !== 1 && !$isSalesManagement) {
            Response::forbidden('You may only view your own performance');
            return;
        }

        $tzOffsetMinutes = SalesPerformanceMetrics::resolveTzOffsetMinutes($_GET['tzOffset'] ?? null);
        $month = SalesPerformanceMetrics::parseMonth($_GET['month'] ?? null);
        if ($month === null) {
            $month = substr(SalesPerformanceMetrics::todayInOffset($tzOffsetMinutes), 0, 7);
        }
        list($startDate, $endDate) = SalesPerformanceMetrics::monthBounds($month);

        Response::success([
            'month' => $month,
            'range' => ['startDate' => $startDate, 'endDate' => $endDate],
            'timezone' => [
                'offsetMinutes' => $tzOffsetMinutes,
                'label' => SalesPerformanceMetrics::offsetLabel($tzOffsetMinutes),
            ],
            'metrics' => SalesPerformanceMetrics::metricsForSales($salesId, $startDate, $endDate, $tzOffsetMinutes),
        ]);
    }

    /**
     * 某 Sales 绑定的 Client 列表（分页 + 关键字搜索 id/name）
     * 仅包含「非 IB」的客户：排除已在 ibPartners 中且 status=approved 的用户（IB 列表已展示）
     * GET /api/sales/{salesId}/bound-clients?page=1&per_page=10&search=xxx
     */
    public function boundClients($salesId) {
        $salesId = (int)$salesId;
        if ($salesId <= 0) {
            Response::error('Invalid sales id', 400);
            return;
        }
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        $db = Database::getInstance();
        $offset = ($page - 1) * $perPage;

        $where = 'ibApproved.userId IS NULL';
        $params = ['salesId' => $salesId];
        if ($search !== '') {
            $cond = [];
            if (is_numeric($search)) {
                $cond[] = 'cu.id = :idExact';
                $params['idExact'] = (int)$search;
            }
            $cond[] = 'cu.firstName LIKE :search';
            $cond[] = 'cu.lastName LIKE :search';
            $cond[] = 'CONCAT(TRIM(IFNULL(cu.firstName,\'\')), \' \', TRIM(IFNULL(cu.lastName,\'\'))) LIKE :searchConcat';
            $params['search'] = '%' . $search . '%';
            $params['searchConcat'] = '%' . $search . '%';
            $where .= ' AND (' . implode(' OR ', $cond) . ')';
        }

        // 数据源：该 Sales 绑定的客户（且非 approved IB）
        $clientIdsSub = "(SELECT sb.clientId AS id FROM sales_bind sb
                INNER JOIN clientUsers cu2 ON cu2.id = sb.clientId
                LEFT JOIN ibPartners ibApproved2 ON ibApproved2.userId = sb.clientId AND ibApproved2.status = 'approved'
                WHERE sb.salesId = :salesId AND ibApproved2.userId IS NULL)";

        $countSql = "SELECT COUNT(*) AS cnt FROM {$clientIdsSub} AS ids
                INNER JOIN clientUsers cu ON cu.id = ids.id
                LEFT JOIN ibPartners ibApproved ON ibApproved.userId = cu.id AND ibApproved.status = 'approved'
                WHERE {$where}";
        $total = (int)$db->fetchOne($countSql, $params)['cnt'];

        // Balance = 客户端 transactions 页顶部 Trading Account Balance（各交易账户 platformBalance 或 internalTransfers 计算余额之和）；Trades = orders 表条数
        $balanceSub = "COALESCE(SUM(COALESCE(tea.platformBalance, GREATEST(0,
            (SELECT COALESCE(SUM(COALESCE(it.toPlatformAmount, it.amount)),0) FROM internalTransfers it WHERE it.toTradingAccountId = ta.id AND it.userId = ta.userId AND it.status = 'completed')
            - (SELECT COALESCE(SUM(COALESCE(it2.fromPlatformAmount, it2.amount)),0) FROM internalTransfers it2 WHERE it2.fromTradingAccountId = ta.id AND it2.userId = ta.userId AND it2.fromType = 'trading_account' AND it2.status = 'completed')
        ))), 0)";
        $tradesSub = "(SELECT COUNT(*) FROM orders o INNER JOIN tradingAccountExternalAccounts tea_o ON tea_o.providerAccountId = o.trading_login INNER JOIN tradingAccounts ta_o ON ta_o.id = tea_o.tradingAccountId WHERE ta_o.userId = cu.id)";

        $sql = "SELECT cu.id, cu.firstName, cu.lastName, cu.email, cu.phoneCountryCode, cu.phone, cu.country, cu.kycStatus,
                cl.name AS countryName,
                (SELECT {$balanceSub} FROM tradingAccounts ta LEFT JOIN tradingAccountExternalAccounts tea ON tea.tradingAccountId = ta.id WHERE ta.userId = cu.id) AS clientBalance,
                {$tradesSub} AS clientTrades
                FROM {$clientIdsSub} AS ids
                INNER JOIN clientUsers cu ON cu.id = ids.id
                LEFT JOIN ibPartners ibApproved ON ibApproved.userId = cu.id AND ibApproved.status = 'approved'
                LEFT JOIN countryList cl ON cl.code = cu.country
                WHERE {$where}
                ORDER BY cu.id ASC
                LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        $rows = $db->fetchAll($sql, $params);

        $list = [];
        foreach ($rows as $r) {
            $k = $r['kycStatus'] ?? 'not_started';
            $kDisplay = $k === 'approved' ? 'Approved' : ($k === 'rejected' ? 'Rejected' : ($k === 'under_review' || $k === 'submitted' ? 'Pending' : 'Not Started'));
            $phonePart = trim(trim($r['phoneCountryCode'] ?? '') . ' ' . trim($r['phone'] ?? ''));
            $list[] = [
                'id' => (int)$r['id'],
                'clientId' => (string)$r['id'],
                'firstName' => $r['firstName'] ?? '',
                'lastName' => $r['lastName'] ?? '',
                'email' => $r['email'] ?? '',
                'phone' => $phonePart !== '' ? $phonePart : ($r['phone'] ?? ''),
                'country' => $r['countryName'] ?? $r['country'] ?? '',
                'balance' => (float)($r['clientBalance'] ?? 0),
                'trades' => (int)($r['clientTrades'] ?? 0),
                'kycStatus' => $k,
                'kycStatusDisplay' => $kDisplay,
            ];
        }

        Response::success([
            'items' => $list,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
            ],
        ]);
    }

    /**
     * 某 Sales 绑定的 IB 列表（分页 + 关键字搜索 id/name）
     * 绑定关系：sales_bind 的 clientId 关联 ibPartners.userId，且 ibPartners.status = 'approved'
     * GET /api/sales/{salesId}/bound-ibs?page=1&per_page=10&search=xxx
     */
    public function boundIbs($salesId) {
        $salesId = (int)$salesId;
        if ($salesId <= 0) {
            Response::error('Invalid sales id', 400);
            return;
        }
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        $db = Database::getInstance();
        $offset = ($page - 1) * $perPage;

        $where = 'sb.salesId = :salesId AND ib.status = \'approved\'';
        $params = ['salesId' => $salesId];
        if ($search !== '') {
            $cond = [];
            if (is_numeric($search)) {
                $cond[] = 'ib.id = :idExact';
                $params['idExact'] = (int)$search;
            }
            $cond[] = 'ib.ibCode LIKE :search';
            $cond[] = 'ib.companyName LIKE :search';
            $cond[] = 'ib.adminAlias LIKE :search';
            $cond[] = 'COALESCE(TRIM(CONCAT(IFNULL(cu.firstName,\'\'), \' \', IFNULL(cu.lastName,\'\'))), ib.companyName) LIKE :searchName';
            $params['search'] = '%' . $search . '%';
            $params['searchName'] = '%' . $search . '%';
            $where .= ' AND (' . implode(' OR ', $cond) . ')';
        }

        $countSql = "SELECT COUNT(DISTINCT ib.id) AS cnt FROM sales_bind sb INNER JOIN ibPartners ib ON ib.userId = sb.clientId AND ib.status = 'approved' LEFT JOIN clientUsers cu ON cu.id = ib.userId WHERE {$where}";
        $total = (int)$db->fetchOne($countSql, $params)['cnt'];

        $sql = "SELECT ib.id, ib.userId, ib.ibCode, ib.adminAlias, ib.companyName, ib.contactEmail, ib.contactPhone, ib.totalClients, ib.status,
                (SELECT COALESCE(SUM(ico.commission), 0) FROM ib_commission_order ico WHERE ico.ibPartnerId = ib.id AND ico.status = 'completed') AS totalCommission,
                COALESCE(TRIM(CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,''))), ib.companyName) AS ibName,
                COALESCE(cu.email, ib.contactEmail) AS email,
                cu.phoneCountryCode AS cuPhoneCountryCode, cu.phone AS cuPhone,
                cl.name AS countryName
                FROM sales_bind sb
                INNER JOIN ibPartners ib ON ib.userId = sb.clientId AND ib.status = 'approved'
                LEFT JOIN clientUsers cu ON cu.id = ib.userId
                LEFT JOIN countryList cl ON cl.code = cu.country
                WHERE {$where}
                ORDER BY ib.id ASC
                LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        $rows = $db->fetchAll($sql, $params);

        // IB 推广链接：与 IB 自己 dashboard 同一套（client_frontend_url + /#/registration/i/{referralSuffix}），不依赖 ibCode
        require_once __DIR__ . '/../models/IbReferralSettings.php';
        $refMap = (new \IbReferralSettings())->getOrCreateByIbPartnerIds(array_map(static fn($r) => (int)$r['id'], $rows));
        $appConfig = require __DIR__ . '/../config/app.php';
        $clientBaseUrl = rtrim((string)($appConfig['client_frontend_url'] ?? ''), '/');

        $list = [];
        foreach ($rows as $r) {
            $status = $r['status'] ?? '';
            $statusDisplay = $status === 'approved' ? 'Active' : \IbPartner::statusToDisplay($status);
            $phonePart = trim(trim($r['cuPhoneCountryCode'] ?? '') . ' ' . trim($r['cuPhone'] ?? ''));
            $refSuffix = trim((string)($refMap[(int)$r['id']]['referralSuffix'] ?? ''));
            $list[] = [
                'id' => (int)$r['id'],
                'userId' => (int)($r['userId'] ?? 0),
                'ibCode' => $r['ibCode'] ?? '',
                'adminAlias' => $r['adminAlias'] ?? '',
                'referralUrl' => $refSuffix !== '' ? $clientBaseUrl . '/#/registration/i/' . rawurlencode($refSuffix) : '',
                'ibName' => $r['ibName'] ?? $r['companyName'] ?? '',
                'email' => $r['email'] ?? '',
                'phone' => $phonePart !== '' ? $phonePart : ($r['contactPhone'] ?? ''),
                'country' => $r['countryName'] ?? $r['country'] ?? '',
                'clientsCount' => (int)($r['totalClients'] ?? 0),
                'totalCommission' => (float)($r['totalCommission'] ?? 0),
                'status' => $status,
                'statusDisplay' => $statusDisplay,
            ];
        }

        Response::success([
            'items' => $list,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
            ],
        ]);
    }

    /**
     * 批量获取各 Sales 的绑定数量（totalIbs, totalClients）
     * ibs = sales_bind 中 clientId 对应 ibPartners.userId 且 status=approved 的去重数量
     * clients = sales_bind 中排除「已是 approved IB」后的客户数（与 bound-clients 列表口径一致）
     * @param int[] $salesIds
     * @return array [ salesId => ['ibs' => int, 'clients' => int], ... ]
     */
    private function getSalesBindCounts(array $salesIds) {
        if (empty($salesIds)) {
            return [];
        }
        $salesIds = array_unique(array_map('intval', $salesIds));
        $salesIds = array_filter($salesIds, function ($id) { return $id > 0; });
        if (empty($salesIds)) {
            return [];
        }
        $db = Database::getInstance();
        $placeholders = implode(',', $salesIds);

        $clientSql = "SELECT sb.salesId, COUNT(*) AS clients
                      FROM sales_bind sb
                      LEFT JOIN ibPartners ibApproved ON ibApproved.userId = sb.clientId AND ibApproved.status = 'approved'
                      WHERE sb.salesId IN ({$placeholders}) AND ibApproved.userId IS NULL
                      GROUP BY sb.salesId";
        $clientRows = $db->fetchAll($clientSql, []);
        $map = [];
        foreach ($salesIds as $id) {
            $map[$id] = ['ibs' => 0, 'clients' => 0];
        }
        foreach ($clientRows as $r) {
            $sid = (int)$r['salesId'];
            $map[$sid]['clients'] = (int)($r['clients'] ?? 0);
        }

        $ibSql = "SELECT sb.salesId, COUNT(DISTINCT ib.id) AS ibs
                  FROM sales_bind sb
                  INNER JOIN ibPartners ib ON ib.userId = sb.clientId AND ib.status = 'approved'
                  WHERE sb.salesId IN ({$placeholders})
                  GROUP BY sb.salesId";
        $ibRows = $db->fetchAll($ibSql, []);
        foreach ($ibRows as $r) {
            $sid = (int)$r['salesId'];
            $map[$sid]['ibs'] = (int)($r['ibs'] ?? 0);
        }
        return $map;
    }

    private function getStats() {
        return $this->userModel->getSalesListStats($this->salesRoleId, $this->salesDashboardViewPermissionId);
    }

    private function getDepartmentNameMap() {
        $list = $this->departmentModel->getAllDepartments();
        $map = [];
        foreach ($list as $row) {
            $map[(int)$row['id']] = $row['name'] ?? '';
        }
        return $map;
    }

    private function getPositionNameMap() {
        $list = $this->positionModel->getAllPositions();
        $map = [];
        foreach ($list as $row) {
            $map[(int)$row['id']] = $row['name'] ?? '';
        }
        return $map;
    }

    /**
     * @param array|null $referralRow 来自 sales_referral_settings 的行
     */
    private function mapUserToSalesItem($u, $baseUrl, $departmentMap = [], $positionMap = [], $referralRow = null) {
        $id = (int)($u['id'] ?? 0);
        $status = $u['status'] ?? 'active';
        $statusDisplay = $status === 'active' ? 'Active' : 'Inactive';
        $joinDate = $u['createdAt'] ?? null;
        $baseUrl = rtrim($baseUrl, '/');
        if (!$referralRow || empty(trim($referralRow['referralSuffix'] ?? ''))) {
            $referralRow = $this->referralSettingsModel->getOrCreateByUserId($id);
        }
        $suffix = trim((string)($referralRow['referralSuffix'] ?? ''));
        $personalReferralUrl = $baseUrl . '/#/registration/s/' . rawurlencode($suffix);
        $referralUrlClicks = (int)($referralRow['urlClicks'] ?? 0);
        $referralRegistrationsCount = (int)($referralRow['registrationsCount'] ?? 0);

        $departmentId = isset($u['departmentId']) ? (int)$u['departmentId'] : null;
        $positionId = isset($u['positionId']) ? (int)$u['positionId'] : null;
        $departmentName = ($departmentId !== null && $departmentId > 0 && isset($departmentMap[$departmentId]))
            ? $departmentMap[$departmentId] : null;
        $positionName = ($positionId !== null && $positionId > 0 && isset($positionMap[$positionId]))
            ? $positionMap[$positionId] : null;

        return [
            'id' => $id,
            'salesName' => $u['fullName'] ?? $u['username'] ?? '',
            'salesCode' => (string)$id,
            'fullName' => $u['fullName'] ?? $u['username'] ?? '',
            'email' => $u['email'] ?? '',
            'joinDate' => $joinDate,
            'departmentName' => $departmentName,
            'positionName' => $positionName,
            'personalReferralUrl' => $personalReferralUrl,
            'referralUrlClicks' => $referralUrlClicks,
            'referralRegistrationsCount' => $referralRegistrationsCount,
            'totalIbs' => (int)($u['totalIbs'] ?? 0),
            'totalClients' => (int)($u['totalClients'] ?? 0),
            'status' => $status,
            'statusDisplay' => $statusDisplay,
        ];
    }

    /**
     * 更新 Sales 个人推荐链接后缀（仅后缀可编辑）
     * POST /api/sales/{salesId}/referral-suffix  Body: { "suffix": "xxx" }
     * 唯一性校验；失败返回明确错误信息
     */
    public function updateReferralSuffix($salesId) {
        $salesId = (int) $salesId;
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $subModule = OperationLogPages::resolveLogSales($body);
        $opLog = new AdminOperationLogWriter();

        if ($salesId <= 0) {
            $opLog->logSalesReferralSuffixUpdate($subModule, 0, '', '—', '', false, 'Invalid sales id');
            Response::error('Invalid sales id', 400);
        }

        $user = $this->userModel->getUserFullInfo($salesId);
        if (!$user) {
            $opLog->logSalesReferralSuffixUpdate($subModule, 0, '', '—', '', false, 'User not found');
            Response::error('User not found', 404);
        }

        $roleId = (int) ($user['roleId'] ?? 0);
        $isTargetSales = ($roleId === $this->salesRoleId)
            || $this->userModel->hasRolePermission($salesId, $this->salesDashboardViewPermissionId);
        if (!$isTargetSales) {
            $opLog->logSalesReferralSuffixUpdate($subModule, 0, '', '—', '', false, 'User is not a Sales or not found');
            Response::error('User is not a Sales or not found', 404);
        }

        $displayName = AdminOperationLogWriter::formatSalesDisplayName($user);
        $suffix = isset($body['suffix']) ? trim((string) $body['suffix']) : '';
        if ($suffix === '') {
            $opLog->logSalesReferralSuffixUpdate($subModule, $salesId, $displayName, '—', '', false, 'Suffix is required and cannot be empty');
            Response::error('Suffix is required and cannot be empty', 400);
        }
        if (strlen($suffix) > 100) {
            $opLog->logSalesReferralSuffixUpdate($subModule, $salesId, $displayName, '—', '', false, 'Suffix must be at most 100 characters');
            Response::error('Suffix must be at most 100 characters', 400);
        }
        if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $suffix)) {
            $opLog->logSalesReferralSuffixUpdate(
                $subModule,
                $salesId,
                $displayName,
                '—',
                '',
                false,
                'Suffix may only contain letters, numbers, hyphens and underscores'
            );
            Response::error('Suffix may only contain letters, numbers, hyphens and underscores', 400);
        }
        if ($this->referralSettingsModel->isSuffixTakenByOther($suffix, $salesId)) {
            $opLog->logSalesReferralSuffixUpdate(
                $subModule,
                $salesId,
                $displayName,
                '—',
                '',
                false,
                'This referral code is already in use by another Sales'
            );
            Response::error('This referral code is already in use by another Sales', 400);
        }

        $oldSuffix = '';
        $existing = $this->referralSettingsModel->getByUserId($salesId);
        if ($existing && !empty($existing['referralSuffix'])) {
            $oldSuffix = (string) $existing['referralSuffix'];
        }

        $this->referralSettingsModel->setSuffix($salesId, $suffix);
        $config = require __DIR__ . '/../config/app.php';
        $baseUrl = rtrim($config['client_frontend_url'] ?? 'http://localhost:9502', '/');
        $personalReferralUrl = $baseUrl . '/#/registration/s/' . rawurlencode($suffix);

        $opLog->logSalesReferralSuffixUpdate(
            $subModule,
            $salesId,
            $displayName,
            $oldSuffix !== '' ? $oldSuffix : '—',
            $suffix
        );

        Response::success([
            'personalReferralUrl' => $personalReferralUrl,
            'suffix' => $suffix,
        ]);
    }
}
