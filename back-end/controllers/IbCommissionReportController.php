<?php
/**
 * IB佣金报表控制器
 * 管理IB佣金报表的查询和统计
 */

require_once __DIR__ . '/../models/IbCommissionOrder.php';
require_once __DIR__ . '/../models/IbCommissionWithdrawal.php';
require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/../models/IbPartnerBind.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/ClientCommissionReportController.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';

class IbCommissionReportController {
    private $commissionOrderModel;
    private $commissionWithdrawalModel;
    private $ibPartnerModel;
    private $ibPartnerBindModel;

    public function __construct() {
        $this->commissionOrderModel = new IbCommissionOrder();
        $this->commissionWithdrawalModel = new IbCommissionWithdrawal();
        $this->ibPartnerModel = new IbPartner();
        $this->ibPartnerBindModel = new IbPartnerBind();
    }

    /**
     * 将前端发送的日期转换为服务器时区（Asia/Shanghai）用于查询，以前端页面的时区为准。
     * - 若前端传 ISO 8601（带 T/Z/+）：按该时刻转为服务器时区再查询，即“用户选择的日期”按用户所在时区的 0 点/24 点。
     * - 若只传 YYYY-MM-DD：视为服务器时区当日 00:00:00 / 23:59:59（兼容旧调用）。
     */
    private function convertDateToServerTimezone($dateString) {
        if (empty($dateString)) {
            return null;
        }
        try {
            if (strpos($dateString, 'T') !== false || strpos($dateString, 'Z') !== false || strpos($dateString, '+') !== false) {
                $date = new DateTime($dateString);
                $date->setTimezone(new DateTimeZone('Asia/Shanghai'));
                return $date->format('Y-m-d H:i:s');
            } else {
                // 如果只是日期字符串（YYYY-MM-DD），假设是服务器时区的日期
                // 转换为当天的开始时间（00:00:00）
                return $dateString . ' 00:00:00';
            }
        } catch (Exception $e) {
            return $dateString;
        }
    }

    /**
     * 结束日期转为服务器时区当日 23:59:59（同上，以前端传入的带时区时间为准）。
     */
    private function convertEndDateToServerTimezone($dateString) {
        if (empty($dateString)) {
            return null;
        }
        try {
            // 如果已经是ISO 8601格式（带时区），解析它
            if (strpos($dateString, 'T') !== false || strpos($dateString, 'Z') !== false || strpos($dateString, '+') !== false) {
                // 解析ISO 8601格式的日期
                $date = new DateTime($dateString);
                // 转换为服务器时区（UTC+8）
                $date->setTimezone(new DateTimeZone('Asia/Shanghai'));
                // 设置为当天的结束时间（23:59:59）
                $date->setTime(23, 59, 59);
                // 返回格式化的日期字符串（用于SQL查询）
                return $date->format('Y-m-d H:i:s');
            } else {
                // 如果只是日期字符串（YYYY-MM-DD），假设是服务器时区的日期
                // 转换为当天的结束时间（23:59:59）
                return $dateString . ' 23:59:59';
            }
        } catch (Exception $e) {
            // 如果解析失败，返回原值加上结束时间
            if (strpos($dateString, ' ') === false) {
                return $dateString . ' 23:59:59';
            }
            return $dateString;
        }
    }

    /**
     * 获取IB佣金报表列表
     * GET /api/ib-commission-report
     */
    public function index() {
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 10;
        // 构建筛选条件
        $filters = [];

        if (isset($_GET['start_date'])) {
            $filters['startDate'] = $this->convertDateToServerTimezone($_GET['start_date']);
        }

        if (isset($_GET['end_date'])) {
            $filters['endDate'] = $this->convertEndDateToServerTimezone($_GET['end_date']);
        }

        if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
            $filters['search'] = trim($_GET['search']);
        }

        if (isset($_GET['sort'])) {
            $filters['sort'] = $_GET['sort'];
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_ibreport');
        if ($scope['scope'] === 'none') {
            Response::paginated([], 0, $page, $perPage);
            return;
        }
        if ($scope['scope'] === 'own') {
            $filters['restrict_to_sales_id'] = $scope['restrict_to_sales_id'];
        }

        // 与 Final Review List 一致：先同步 ibPartners.totalClients，列表的 Clients Referred 使用该值
        $this->ibPartnerModel->syncAllTotalClientsFromBindTable();
        $clientCountsMap = $this->ibPartnerBindModel->getClientCountsMapRecursive();
        $result = $this->commissionOrderModel->getAllIbCommissionReport($page, $perPage, $filters, $clientCountsMap);

        // 格式化数据：列表用用户名(displayName)，头像简写也用用户名；Status/Last Payout 仅用于导出，列表不展示
        $formattedItems = array_map(function($item) {
            $displayName = $item['displayName'] ?? $item['companyName'] ?? '';
            return [
                'id' => $item['ibPartnerId'],
                'ibCode' => $item['ibCode'],
                'name' => $displayName,
                'email' => $item['contactEmail'],
                'country' => $item['country'],
                'totalCommission' => (float)$item['totalCommission'],
                'paidCommission' => (float)$item['paidCommission'],
                'pendingCommission' => (float)$item['pendingCommission'],
                'clientsReferred' => isset($item['clientsReferred']) ? (int)$item['clientsReferred'] : 0,
                'tradingVolume' => (float)$item['tradingVolume'],
                'totalOrders' => (int)$item['totalOrders'],
                'status' => $this->getStatusFromCommission($item['paidCommission'], $item['pendingCommission']),
                'lastPayout' => !empty($item['lastPayoutDate']) ? date('M d, Y', strtotime($item['lastPayoutDate'])) : '--',
                'initials' => $this->getInitials($displayName)
            ];
        }, $result['items']);

        Response::paginated(
            $formattedItems,
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * 获取总体统计信息
     * GET /api/ib-commission-report/statistics
     * 返回 8 个数据：本月（或当前筛选周期）的 4 项统计 + 与上一周期对比的 4 项变化（佣金为百分比，Active IBs 为绝对数值）
     */
    public function statistics() {
        $filters = [];

        if (isset($_GET['start_date'])) {
            $filters['startDate'] = $this->convertDateToServerTimezone($_GET['start_date']);
        }

        if (isset($_GET['end_date'])) {
            $filters['endDate'] = $this->convertEndDateToServerTimezone($_GET['end_date']);
        }

        if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
            $filters['search'] = trim($_GET['search']);
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_ibreport');
        if ($scope['scope'] === 'none') {
            Response::success([
                'totalCommission' => 0,
                'totalCommissionChange' => 0,
                'paidCommission' => 0,
                'paidCommissionChange' => 0,
                'pendingPayout' => 0,
                'pendingPayoutChange' => 0,
                'activeIbs' => 0,
                'activeIbsChange' => 0,
                'totalLots' => 0,
                'totalLotsChange' => 0,
                'totalTrade' => 0,
                'totalTradeChange' => 0
            ]);
            return;
        }
        if ($scope['scope'] === 'own') {
            $filters['restrict_to_sales_id'] = $scope['restrict_to_sales_id'];
        }
        $restrictToSalesId = $scope['scope'] === 'own' ? (int)$scope['restrict_to_sales_id'] : null;
        $ibSalesRestrictSql = $restrictToSalesId > 0
            ? ' AND userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)'
            : '';
        $ibSalesParams = $restrictToSalesId > 0 ? ['restrict_to_sales_id' => $restrictToSalesId] : [];

        $tz = new DateTimeZone('Asia/Shanghai');
        $now = new DateTime('now', $tz);

        // 确定“本周期”与“上一周期”的起止日期
        if (isset($_GET['start_date']) && isset($_GET['end_date']) && $_GET['start_date'] !== '' && $_GET['end_date'] !== '') {
            $thisStart = $this->convertDateToServerTimezone($_GET['start_date']);
            $thisEnd = $this->convertEndDateToServerTimezone($_GET['end_date']);
            if (!$thisStart || !$thisEnd) {
                $thisStart = $now->format('Y-m-01 00:00:00');
                $thisEnd = $now->format('Y-m-d 23:59:59');
            }
            $startDt = new DateTime($thisStart, $tz);
            $endDt = new DateTime($thisEnd, $tz);
            $days = (int) $endDt->diff($startDt)->days + 1;
            $prevEndDt = (clone $startDt)->modify('-1 day');
            $prevStartDt = (clone $prevEndDt)->modify('-' . ($days - 1) . ' days');
            $prevStart = $prevStartDt->format('Y-m-d 00:00:00');
            $prevEnd = $prevEndDt->format('Y-m-d 23:59:59');
        } else {
            // 默认：本月 vs 上月
            $thisStart = $now->format('Y-m-01 00:00:00');
            $thisEnd = $now->format('Y-m-d 23:59:59');
            $prevStart = (clone $now)->modify('first day of last month')->format('Y-m-d 00:00:00');
            $prevEnd = (clone $now)->modify('last day of last month')->format('Y-m-d 23:59:59');
        }

        $filtersThis = array_merge($filters, ['startDate' => $thisStart, 'endDate' => $thisEnd]);
        $filtersPrev = array_merge($filters, ['startDate' => $prevStart, 'endDate' => $prevEnd]);

        $statsThis = $this->commissionOrderModel->getOverallStatistics($filtersThis);
        $statsPrev = $this->commissionOrderModel->getOverallStatistics($filtersPrev);

        $totalThis = (float)($statsThis['totalCommission'] ?? 0);
        $totalPrev = (float)($statsPrev['totalCommission'] ?? 0);
        $paidThis = (float)($statsThis['paidCommission'] ?? 0);
        $paidPrev = (float)($statsPrev['paidCommission'] ?? 0);
        $pendingThis = (float)($statsThis['pendingCommission'] ?? 0);
        $pendingPrev = (float)($statsPrev['pendingCommission'] ?? 0);
        $totalLotsThis = (float)($statsThis['totalLots'] ?? 0);
        $totalLotsPrev = (float)($statsPrev['totalLots'] ?? 0);
        $totalTradeThis = (int)($statsThis['totalTrade'] ?? 0);
        $totalTradePrev = (int)($statsPrev['totalTrade'] ?? 0);

        $totalCommissionChange = $this->percentChange($totalThis, $totalPrev);
        $paidCommissionChange = $this->percentChange($paidThis, $paidPrev);
        $pendingPayoutChange = $this->percentChange($pendingThis, $pendingPrev);
        $totalLotsChange = $this->percentChange($totalLotsThis, $totalLotsPrev);
        $totalTradeChange = $this->percentChange($totalTradeThis, $totalTradePrev);

        // Active IBs：展示用为 status=approved 的当前总数；变化量 = 本周期内 updatedAt 的 approved 数 - 上一周期内 updatedAt 的 approved 数
        $db = Database::getInstance();
        $activeIbsRow = $db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM ibPartners WHERE status = :approved{$ibSalesRestrictSql}",
            array_merge(['approved' => 'approved'], $ibSalesParams)
        );
        $activeIbs = (int)($activeIbsRow['cnt'] ?? 0);

        $activeIbsThisRow = $db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM ibPartners WHERE status = :approved AND updatedAt >= :start AND updatedAt <= :end{$ibSalesRestrictSql}",
            array_merge(['approved' => 'approved', 'start' => $thisStart, 'end' => $thisEnd], $ibSalesParams)
        );
        $activeIbsPrevRow = $db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM ibPartners WHERE status = :approved AND updatedAt >= :start AND updatedAt <= :end{$ibSalesRestrictSql}",
            array_merge(['approved' => 'approved', 'start' => $prevStart, 'end' => $prevEnd], $ibSalesParams)
        );
        $activeIbsThis = (int)($activeIbsThisRow['cnt'] ?? 0);
        $activeIbsPrev = (int)($activeIbsPrevRow['cnt'] ?? 0);
        $activeIbsChange = $activeIbsThis - $activeIbsPrev;

        $response = [
            'totalCommission' => $totalThis,
            'totalCommissionChange' => round($totalCommissionChange, 1),
            'paidCommission' => $paidThis,
            'paidCommissionChange' => round($paidCommissionChange, 1),
            'pendingPayout' => $pendingThis,
            'pendingPayoutChange' => round($pendingPayoutChange, 1),
            'activeIbs' => $activeIbs,
            'activeIbsChange' => $activeIbsChange,
            'totalLots' => $totalLotsThis,
            'totalLotsChange' => round($totalLotsChange, 1),
            'totalTrade' => $totalTradeThis,
            'totalTradeChange' => round($totalTradeChange, 1)
        ];

        Response::success($response);
    }

    /**
     * 计算同比变化百分比（本期 - 上期）/ 上期 * 100；上期为 0 时返回 0
     */
    private function percentChange($current, $previous) {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }
        return (($current - $previous) / $previous) * 100;
    }

    /**
     * 获取单个IB代理的佣金详情
     * GET /api/ib-commission-report/{ibPartnerId}
     */
    public function show($ibPartnerId) {
        $startDate = isset($_GET['start_date']) ? $this->convertDateToServerTimezone($_GET['start_date']) : null;
        $endDate = isset($_GET['end_date']) ? $this->convertEndDateToServerTimezone($_GET['end_date']) : null;

        // 获取IB基本信息
        $ibPartner = $this->ibPartnerModel->findById($ibPartnerId);
        if (!$ibPartner) {
            Response::notFound('IB partner not found');
        }

        // 获取佣金统计（来自 ib_commission_order）
        $stats = $this->commissionOrderModel->getIbCommissionStatistics($ibPartnerId, $startDate, $endDate);

        // 获取佣金明细列表
        $filters = [];
        if ($startDate) {
            $filters['startDate'] = $startDate;
        }
        if ($endDate) {
            $filters['endDate'] = $endDate;
        }

        $commissionList = $this->commissionOrderModel->getIbCommissionList($ibPartnerId, 1, 100, $filters);

        $response = [
            'ibPartner' => [
                'id' => $ibPartner['id'],
                'ibCode' => $ibPartner['ibCode'],
                'companyName' => $ibPartner['companyName'],
                'contactEmail' => $ibPartner['contactEmail'],
                'country' => $ibPartner['country']
            ],
            'statistics' => [
                'totalCommission' => (float)($stats['totalCommission'] ?? 0),
                'paidCommission' => (float)($stats['paidCommission'] ?? 0),
                'pendingCommission' => (float)($stats['pendingCommission'] ?? 0),
                'totalOrders' => (int)($stats['totalOrders'] ?? 0),
                'totalVolume' => (float)($stats['totalVolume'] ?? 0)
            ],
            'breakdown' => $this->formatBreakdown($commissionList['items'])
        ];

        Response::success($response);
    }

    /**
     * 导出明细：当前筛选下所有 IB 的逐条佣金明细（与 detail 展开一致，服务端生成 CSV）
     * POST /api/ib-commission-report/export-detail
     */
    public function exportDetail() {
        try {
            require_once __DIR__ . '/../services/AdminIbCommissionDetailExportService.php';

            $currentUser = AuthMiddleware::getCurrentUser();
            $adminUserId = (int)($currentUser['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }

            $active = AdminIbCommissionDetailExportService::getActiveForAdmin($adminUserId);
            $activeStatus = (string)($active['status'] ?? '');
            if (in_array($activeStatus, ['queued', 'running', 'cancelling'], true)) {
                Response::error('Export already in progress', 409, $this->exportProgressPayload($active));
            }
            if ($activeStatus === 'done') {
                AdminIbCommissionDetailExportService::clearActive($adminUserId);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?? [];

            $filters = [];
            if (isset($input['start_date']) || isset($_GET['start_date'])) {
                $filters['startDate'] = $this->convertDateToServerTimezone($input['start_date'] ?? $_GET['start_date']);
            }
            if (isset($input['end_date']) || isset($_GET['end_date'])) {
                $filters['endDate'] = $this->convertEndDateToServerTimezone($input['end_date'] ?? $_GET['end_date']);
            }
            if (isset($input['status']) || isset($_GET['status'])) {
                $filters['status'] = $input['status'] ?? $_GET['status'];
            }
            if (isset($input['search']) || (isset($_GET['search']) && !empty(trim($_GET['search'])))) {
                $filters['search'] = $input['search'] ?? trim($_GET['search']);
            }
            if (isset($input['sort']) || isset($_GET['sort'])) {
                $filters['sort'] = $input['sort'] ?? $_GET['sort'];
            }

            $scope = AdminSalesPermission::getClientDataScopeForPage('page_ibreport');
            if ($scope['scope'] === 'none') {
                $filters['scope_none'] = true;
            } elseif ($scope['scope'] === 'own') {
                $filters['restrict_to_sales_id'] = $scope['restrict_to_sales_id'];
            }

            $jobId = str_replace('.', '', uniqid('aibcd_', true));
            AdminIbCommissionDetailExportService::ensureExportDir();
            AdminIbCommissionDetailExportService::writeProgress($jobId, [
                'adminUserId' => $adminUserId,
                'status' => 'queued',
                'cancelRequested' => false,
                'percent' => 0,
                'processed' => 0,
                'total' => 0,
                'message' => 'Queued',
                'file' => $jobId . '.csv',
            ]);
            AdminIbCommissionDetailExportService::writeActive($adminUserId, $jobId);

            $payload = [
                'type' => 'export_admin_ib_commission_detail',
                'jobId' => $jobId,
                'adminUserId' => $adminUserId,
                'userId' => $adminUserId,
                'userType' => 'admin',
                'scope' => 'all_ibs',
                'filters' => $filters,
                'requestedAt' => time(),
            ];

            try {
                $this->dispatchSwooleTask($payload);
            } catch (Exception $e) {
                AdminIbCommissionDetailExportService::writeProgress($jobId, [
                    'adminUserId' => $adminUserId,
                    'status' => 'error',
                    'cancelRequested' => false,
                    'percent' => 0,
                    'message' => $e->getMessage(),
                    'file' => null,
                ]);
                AdminIbCommissionDetailExportService::clearActive($adminUserId);
                Response::error('Failed to queue export task: ' . $e->getMessage(), 500);
            }

            Response::success([
                'jobId' => $jobId,
                'queued' => true,
            ], 'Export task accepted');
        } catch (Exception $e) {
            Response::error('Failed to export: ' . $e->getMessage(), 500);
        }
    }

    public function exportDetailActive() {
        try {
            require_once __DIR__ . '/../services/AdminIbCommissionDetailExportService.php';
            $currentUser = AuthMiddleware::getCurrentUser();
            $adminUserId = (int)($currentUser['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $progress = AdminIbCommissionDetailExportService::getActiveForAdmin($adminUserId);
            Response::success($this->exportProgressPayload($progress));
        } catch (Exception $e) {
            Response::error('Failed to fetch export status: ' . $e->getMessage(), 500);
        }
    }

    public function exportDetailStatus() {
        try {
            require_once __DIR__ . '/../services/AdminIbCommissionDetailExportService.php';
            $currentUser = AuthMiddleware::getCurrentUser();
            $adminUserId = (int)($currentUser['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $jobId = isset($_GET['jobId']) ? trim((string)$_GET['jobId']) : '';
            if ($jobId === '') {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = AdminIbCommissionDetailExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['adminUserId'] ?? 0) !== $adminUserId) {
                Response::notFound('Export job not found');
            }
            require_once __DIR__ . '/../services/ExportJobTimeoutReaper.php';
            $progress = ExportJobTimeoutReaper::reapIfStale(
                $jobId,
                $progress,
                [AdminIbCommissionDetailExportService::class, 'writeProgress'],
                [AdminIbCommissionDetailExportService::class, 'clearActive']
            );
            Response::success($this->exportProgressPayload($progress));
        } catch (Exception $e) {
            Response::error('Failed to fetch export status: ' . $e->getMessage(), 500);
        }
    }

    public function exportDetailCancel() {
        try {
            require_once __DIR__ . '/../services/AdminIbCommissionDetailExportService.php';
            $currentUser = AuthMiddleware::getCurrentUser();
            $adminUserId = (int)($currentUser['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $rawBody = json_decode(file_get_contents('php://input'), true);
            $jobId = '';
            if (is_array($rawBody) && !empty($rawBody['jobId'])) {
                $jobId = trim((string)$rawBody['jobId']);
            } elseif (!empty($_GET['jobId'])) {
                $jobId = trim((string)$_GET['jobId']);
            }
            if ($jobId === '') {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = AdminIbCommissionDetailExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['adminUserId'] ?? 0) !== $adminUserId) {
                Response::notFound('Export job not found');
            }
            $status = (string)($progress['status'] ?? '');
            if ($status === 'cancelled') {
                Response::success($this->exportProgressPayload($progress), 'Already cancelled');
            }
            if ($status === 'error') {
                AdminIbCommissionDetailExportService::clearActive($adminUserId);
                Response::success($this->exportProgressPayload($progress), 'Export already failed');
            }
            AdminIbCommissionDetailExportService::requestCancel($jobId);
            $updated = AdminIbCommissionDetailExportService::readProgress($jobId);
            Response::success($this->exportProgressPayload($updated), 'Cancel requested');
        } catch (Exception $e) {
            Response::error('Failed to cancel export: ' . $e->getMessage(), 500);
        }
    }

    public function exportDetailDownload() {
        try {
            require_once __DIR__ . '/../services/AdminIbCommissionDetailExportService.php';
            $currentUser = AuthMiddleware::getCurrentUser();
            $adminUserId = (int)($currentUser['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $jobId = isset($_GET['jobId']) ? trim((string)$_GET['jobId']) : '';
            if ($jobId === '') {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = AdminIbCommissionDetailExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['adminUserId'] ?? 0) !== $adminUserId) {
                Response::notFound('Export job not found');
            }
            if (($progress['status'] ?? '') !== 'done') {
                Response::error('Export is not ready', 400);
            }
            $csvFile = AdminIbCommissionDetailExportService::csvPath($jobId);
            if (!file_exists($csvFile) || !is_readable($csvFile)) {
                Response::error('Export file missing', 404);
            }

            AdminIbCommissionDetailExportService::clearActive($adminUserId);

            $filename = 'ib_commission_detail_all_' . date('Y-m-d') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($csvFile));
            header('Cache-Control: no-store');
            readfile($csvFile);
            exit;
        } catch (Exception $e) {
            Response::error('Failed to download export: ' . $e->getMessage(), 500);
        }
    }

    private function dispatchSwooleTask(array $payload): void
    {
        $address = config_swoole_address();
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($address, $errno, $errstr, 1.0);
        if (!$socket) {
            throw new Exception('Failed to connect myswoole: ' . $errstr . ' (' . $errno . ')');
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            fclose($socket);
            throw new Exception('Failed to encode task payload');
        }

        $written = @fwrite($socket, $json . '$$$###');
        fclose($socket);
        if ($written === false || $written <= 0) {
            throw new Exception('Failed to send task to myswoole');
        }
    }

    private function exportProgressPayload(?array $progress): array
    {
        if ($progress === null) {
            return ['active' => false];
        }
        return [
            'active' => true,
            'jobId' => (string)($progress['jobId'] ?? ''),
            'status' => (string)($progress['status'] ?? ''),
            'percent' => (int)($progress['percent'] ?? 0),
            'message' => (string)($progress['message'] ?? ''),
            'processed' => (int)($progress['processed'] ?? 0),
            'total' => (int)($progress['total'] ?? 0),
            'downloadReady' => !empty($progress['downloadReady']) || (($progress['status'] ?? '') === 'done'),
        ];
    }

    private function getStatusFromCommission($paidCommission, $pendingCommission) {
        if ($paidCommission > 0 && $pendingCommission == 0) {
            return 'paid';
        } elseif ($pendingCommission > 0 && $paidCommission == 0) {
            return 'pending';
        } elseif ($paidCommission > 0 && $pendingCommission > 0) {
            return 'processing';
        }
        return 'pending';
    }

    /**
     * 获取公司名称的首字母
     */
    private function getInitials($companyName) {
        if ($companyName === null || $companyName === '') {
            return '';
        }
        $words = explode(' ', (string)$companyName);
        $initials = '';
        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper(substr($word, 0, 1));
            }
        }
        return substr($initials, 0, 2);
    }

    /**
     * Admin Detail 的 breakdown 与 Client 端 commission-report/detail 同结构（19 列：账号/平台/balance/profit 等）。
     * 直接复用 ClientCommissionReportController::formatClientDetailOrders，避免两端字段或业务规则发散。
     */
    private function formatBreakdown($items) {
        if (empty($items)) {
            return [];
        }
        return ClientCommissionReportController::formatClientDetailOrders($items);
    }

}
