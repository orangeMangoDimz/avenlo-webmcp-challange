<?php
/**
 * Deposit Model
 * 对应表: deposits
 */

require_once __DIR__ . '/BaseModel.php';

class Deposit extends BaseModel {
    protected $table = 'deposits';
    protected $primaryKey = 'id';
    protected $fillable = [
        'transactionId',
        'userId',
        'tradingAccountId',
        'gatewaySettingId',
        'sourceType',
        'amount',
        'amountScale',
        'platformAmount',
        'displayUnit',
        'currencyCode',
        'platformFee',
        'quotedAmount',
        'exchangeRate',
        'status',
        'confirmations',
        'requiredConfirmations',
        'transactionHash',
        'gatewayTransactionId',
        'paymentUrl',
        'gatewayResponse',
        'requestedAt',
        'approvedAt',
        'approvedBy',
        'rejectedAt',
        'rejectedBy',
        'rejectionReasonId',
        'rejectionNotes',
        'completedAt',
        'failureReason',
        'expiredAt',
        'webhookReceived',
        'spayLastStatusCheckAt',
        'spayStatusCheckAttempts',
        'spayStatusCheckLeaseUntil',
        'adminNotes',
        'clientNotes',
        'supportContent',
        'ipAddress',
        'userAgent'
    ];

    /**
     * 获取存款列表（带分页和筛选）
     */
    public function getDeposits($page = 1, $perPage = 10, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $conditions = [];
        $params = [];

        // 构建SQL
        $sql = "SELECT d.*,
                       u.firstName, u.lastName, u.email,
                       pgs.gatewayName,
                       pgs.type AS gatewayType,
                       pgs.iconClass AS gatewayIconClass,
                       pgs.gatewayKey,
                       d.gatewaySettingId,
                       CASE
                           WHEN tea.providerAccountId IS NOT NULL AND tea.providerAccountId <> '' THEN tea.providerAccountId
                           ELSE ta.accountNumber
                       END AS accountNumber,
                       ta.accountNickname,
                       tea.name AS externalAccountName,
                       CASE
                           WHEN tea.providerAccountId IS NOT NULL AND tea.providerAccountId <> '' THEN tea.providerAccountId
                           ELSE COALESCE(ta.accountNumber, tea.providerAccountId, tea.name)
                       END AS accountName,
                       tp.displayName AS targetPlatformName,
                       tp.shortCode AS targetPlatformShortCode,
                       tp.platformKey AS targetPlatformKey,
                       tp.platformKey AS groupPlatformKey,
                       tg.label AS groupLabel,
                       tg.scale AS groupScale,
                       tg.unit AS groupUnit,
                       rr.reasonTitle as rejectionReasonTitle,
                       GROUP_CONCAT(DISTINCT dt.tagName SEPARATOR ', ') as tags
                FROM {$this->table} d
                INNER JOIN clientUsers u ON d.userId = u.id
                LEFT JOIN paymentGatewaySettings pgs ON pgs.id = d.gatewaySettingId
                LEFT JOIN tradingAccounts ta ON d.tradingAccountId = ta.id
                LEFT JOIN tradingPlatforms tp ON ta.platformId = tp.id
                LEFT JOIN tradingAccountExternalAccounts tea ON tea.tradingAccountId = ta.id
                LEFT JOIN trading_group tg
                    ON tg.trading_platforms_key = tp.platformKey
                   AND (
                        (tp.platformKey IN ('mt5', 'mt4') AND tg.id = tea.groupId)
                        OR
                        (tp.platformKey NOT IN ('mt5', 'mt4') AND tg.trading_id = tea.groupId)
                   )
                LEFT JOIN rejectionReasons rr ON d.rejectionReasonId = rr.id
                LEFT JOIN depositTagAssignments dta ON d.id = dta.depositId
                LEFT JOIN depositTags dt ON dta.tagId = dt.id";

        // 添加筛选条件，支持多选
        if (!empty($filters['status'])) {
            $statusList = is_array($filters['status']) ? array_values($filters['status']) : [$filters['status']];
            $statusList = array_values(array_filter($statusList, function ($s) { return $s !== '' && $s !== null; }));
            if (!empty($statusList)) {
                $placeholders = [];
                foreach ($statusList as $i => $st) {
                    $key = "status_{$i}";
                    $placeholders[] = ":{$key}";
                    $params[$key] = $st;
                }
                $conditions[] = "d.status IN (" . implode(', ', $placeholders) . ")";
            }
        }

        if (!empty($filters['startDate'])) {
            $conditions[] = "DATE(d.requestedAt) >= :startDate";
            $params['startDate'] = $filters['startDate'];
        }

        if (!empty($filters['endDate'])) {
            $conditions[] = "DATE(d.requestedAt) <= :endDate";
            $params['endDate'] = $filters['endDate'];
        }

        if (!empty($filters['restrict_to_sales_id']) && (int)$filters['restrict_to_sales_id'] > 0) {
            $conditions[] = "d.userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)";
            $params['restrict_to_sales_id'] = (int)$filters['restrict_to_sales_id'];
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " GROUP BY d.id";
        $sql .= " ORDER BY d.requestedAt DESC";

        // 获取总数
        $countSql = "SELECT COUNT(DISTINCT d.id) as count
                     FROM {$this->table} d";
        if (!empty($conditions)) {
            $countSql .= " WHERE " . implode(' AND ', array_map(function($cond) {
                return str_replace('d.', '', $cond);
            }, $conditions));
        }
        $countResult = $this->db->fetchOne($countSql, $params);
        $total = $countResult['count'] ?? 0;

        // 分页
        $perPage = max(1, (int)$perPage);
        $offset = max(0, (int)$offset);
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";

        $items = $this->db->fetchAll($sql, $params);

        return [
            'items' => $items,
            'total' => (int)$total,
            'page' => (int)$page,
            'per_page' => (int)$perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * 获取存款详情（包含完整信息）
     */
    public function getDepositDetails($depositId) {
        $sql = "SELECT d.*,
                       u.firstName, u.lastName, u.email, u.phone, u.country,
                       pgs.gatewayName,
                       pgs.type AS gatewayType,
                       pgs.iconClass AS gatewayIconClass,
                       pgs.gatewayKey,
                       d.gatewaySettingId,
                       CASE
                           WHEN tea.providerAccountId IS NOT NULL AND tea.providerAccountId <> '' THEN tea.providerAccountId
                           ELSE ta.accountNumber
                       END AS accountNumber,
                       ta.accountNickname,
                       tea.name AS externalAccountName,
                       CASE
                           WHEN tea.providerAccountId IS NOT NULL AND tea.providerAccountId <> '' THEN tea.providerAccountId
                           ELSE COALESCE(ta.accountNumber, tea.providerAccountId, tea.name)
                       END AS accountName,
                       tp.displayName AS targetPlatformName,
                       tp.shortCode AS targetPlatformShortCode,
                       tp.platformKey AS targetPlatformKey,
                       tp.platformKey AS groupPlatformKey,
                       tg.label AS groupLabel,
                       tg.scale AS groupScale,
                       tg.unit AS groupUnit,
                       aa.fullName as approvedByName,
                       ar.fullName as rejectedByName,
                       rr.reasonTitle as rejectionReasonTitle,
                       rr.reasonDescription as rejectionReasonDescription
                FROM {$this->table} d
                INNER JOIN clientUsers u ON d.userId = u.id
                LEFT JOIN paymentGatewaySettings pgs ON pgs.id = d.gatewaySettingId
                LEFT JOIN tradingAccounts ta ON d.tradingAccountId = ta.id
                LEFT JOIN tradingPlatforms tp ON ta.platformId = tp.id
                LEFT JOIN tradingAccountExternalAccounts tea ON tea.tradingAccountId = ta.id
                LEFT JOIN trading_group tg
                    ON tg.trading_platforms_key = tp.platformKey
                   AND (
                        (tp.platformKey IN ('mt5', 'mt4') AND tg.id = tea.groupId)
                        OR
                        (tp.platformKey NOT IN ('mt5', 'mt4') AND tg.trading_id = tea.groupId)
                   )
                LEFT JOIN adminUsers aa ON d.approvedBy = aa.id
                LEFT JOIN adminUsers ar ON d.rejectedBy = ar.id
                LEFT JOIN rejectionReasons rr ON d.rejectionReasonId = rr.id
                WHERE d.id = :depositId
                LIMIT 1";

        return $this->db->fetchOne($sql, ['depositId' => $depositId]);
    }

    /**
     * 搜索存款
     */
    public function searchDeposits($searchTerm, $page = 1, $perPage = 10, $restrictToSalesId = null) {
        $offset = ($page - 1) * $perPage;
        $searchPattern = "%{$searchTerm}%";

        // 检查是否是数字（可能是 client ID）
        $isNumeric = is_numeric($searchTerm);
        $numericValue = $isNumeric ? (int)$searchTerm : null;

        $sql = "SELECT DISTINCT d.*,
                       u.firstName, u.lastName, u.email,
                       pgs.gatewayName,
                       pgs.type AS gatewayType,
                       pgs.iconClass AS gatewayIconClass,
                       pgs.gatewayKey,
                       d.gatewaySettingId,
                       CASE
                           WHEN tea.providerAccountId IS NOT NULL AND tea.providerAccountId <> '' THEN tea.providerAccountId
                           ELSE ta.accountNumber
                       END AS accountNumber,
                       ta.accountNickname,
                       tea.name AS externalAccountName,
                       CASE
                           WHEN tea.providerAccountId IS NOT NULL AND tea.providerAccountId <> '' THEN tea.providerAccountId
                           ELSE COALESCE(ta.accountNumber, tea.providerAccountId, tea.name)
                       END AS accountName,
                       tp.displayName AS targetPlatformName,
                       tp.shortCode AS targetPlatformShortCode,
                       tp.platformKey AS targetPlatformKey,
                       tg.label AS groupLabel,
                       tg.scale AS groupScale,
                       tg.unit AS groupUnit,
                       rr.reasonTitle as rejectionReasonTitle
                FROM {$this->table} d
                INNER JOIN clientUsers u ON d.userId = u.id
                LEFT JOIN paymentGatewaySettings pgs ON pgs.id = d.gatewaySettingId
                LEFT JOIN tradingAccounts ta ON d.tradingAccountId = ta.id
                LEFT JOIN tradingPlatforms tp ON ta.platformId = tp.id
                LEFT JOIN tradingAccountExternalAccounts tea ON tea.tradingAccountId = ta.id
                LEFT JOIN trading_group tg
                    ON tg.trading_platforms_key = tp.platformKey
                   AND (
                        (tp.platformKey IN ('mt5', 'mt4') AND tg.id = tea.groupId)
                        OR
                        (tp.platformKey NOT IN ('mt5', 'mt4') AND tg.trading_id = tea.groupId)
                   )
                LEFT JOIN rejectionReasons rr ON d.rejectionReasonId = rr.id
                LEFT JOIN depositTagAssignments dta ON d.id = dta.depositId
                LEFT JOIN depositTags dt ON dta.tagId = dt.id
                WHERE (u.firstName LIKE :search1
                   OR u.lastName LIKE :search2
                   OR u.email LIKE :search3
                   OR dt.tagName LIKE :search4";

        // 如果是数字，也搜索 client ID
        if ($isNumeric) {
            $sql .= " OR u.id = :clientId";
        }
        $sql .= ")";
        if ($restrictToSalesId !== null && (int)$restrictToSalesId > 0) {
            $sql .= " AND d.userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)";
        }

        $perPage = max(1, (int)$perPage);
        $offset = max(0, (int)$offset);
        $sql .= " ORDER BY d.requestedAt DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $params = [
            'search1' => $searchPattern,
            'search2' => $searchPattern,
            'search3' => $searchPattern,
            'search4' => $searchPattern
        ];

        if ($isNumeric) {
            $params['clientId'] = $numericValue;
        }
        if ($restrictToSalesId !== null && (int)$restrictToSalesId > 0) {
            $params['restrict_to_sales_id'] = (int)$restrictToSalesId;
        }

        $items = $this->db->fetchAll($sql, $params);

        // 计算总数
        $countSql = "SELECT COUNT(DISTINCT d.id) as count
                     FROM {$this->table} d
                     INNER JOIN clientUsers u ON d.userId = u.id
                     LEFT JOIN paymentGatewaySettings pgs ON pgs.id = d.gatewaySettingId
                     LEFT JOIN depositTagAssignments dta ON d.id = dta.depositId
                     LEFT JOIN depositTags dt ON dta.tagId = dt.id
                     WHERE (u.firstName LIKE :search1
                        OR u.lastName LIKE :search2
                        OR u.email LIKE :search3
                        OR dt.tagName LIKE :search4";

        if ($isNumeric) {
            $countSql .= " OR u.id = :clientId";
        }
        $countSql .= ")";
        if ($restrictToSalesId !== null && (int)$restrictToSalesId > 0) {
            $countSql .= " AND d.userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)";
        }

        $countParams = [
            'search1' => $searchPattern,
            'search2' => $searchPattern,
            'search3' => $searchPattern,
            'search4' => $searchPattern
        ];
        if ($isNumeric) {
            $countParams['clientId'] = $numericValue;
        }
        if ($restrictToSalesId !== null && (int)$restrictToSalesId > 0) {
            $countParams['restrict_to_sales_id'] = (int)$restrictToSalesId;
        }

        $countResult = $this->db->fetchOne($countSql, $countParams);
        $total = $countResult['count'] ?? 0;

        return [
            'items' => $items,
            'total' => (int)$total,
            'page' => (int)$page,
            'per_page' => (int)$perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * 根据transactionId查找
     */
    public function findByTransactionId($transactionId) {
        return $this->findOne(['transactionId' => $transactionId]);
    }

    /**
     * 获取用户的存款列表
     */
    public function getUserDeposits($userId, $limit = 10) {
        $limit = max(1, (int)$limit); // 确保是正整数
        $sql = "SELECT d.*, pgs.gatewayName,
                       pgs.type AS gatewayType,
                       pgs.iconClass AS gatewayIconClass,
                       pgs.gatewayKey,
                       d.gatewaySettingId
                FROM {$this->table} d
                LEFT JOIN paymentGatewaySettings pgs ON pgs.id = d.gatewaySettingId
                WHERE d.userId = :userId
                ORDER BY d.requestedAt DESC
                LIMIT {$limit}";

        return $this->query($sql, ['userId' => $userId]);
    }

    /**
     * 获取统计数据
     */
    public function getStatistics($startDate = null, $endDate = null) {
        $sql = "SELECT
                    COUNT(*) as totalCount,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pendingCount,
                    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processingCount,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completedCount,
                    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as totalCompletedAmount,
                    SUM(CASE WHEN status = 'completed' AND DATE(completedAt) = CURDATE() THEN amount ELSE 0 END) as todayAmount
                FROM {$this->table}";

        $params = [];
        $conditions = [];

        if ($startDate) {
            $conditions[] = "DATE(requestedAt) >= :startDate";
            $params['startDate'] = $startDate;
        }

        if ($endDate) {
            $conditions[] = "DATE(requestedAt) <= :endDate";
            $params['endDate'] = $endDate;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        return $this->db->fetchOne($sql, $params);
    }
}
