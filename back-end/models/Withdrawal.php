<?php
/**
 * Withdrawal Model
 * 对应表: withdrawals
 */

require_once __DIR__ . '/BaseModel.php';

class Withdrawal extends BaseModel {
    protected $table = 'withdrawals';
    protected $primaryKey = 'id';
    protected $fillable = [
        'transactionId',
        'userId',
        'tradingAccountId',
        'gatewaySettingId',
        'amount',
        'amountScale',
        'platformAmount',
        'displayUnit',
        'currencyCode',
        'networkFee',
        'platformFee',
        'quotedAmount',
        'exchangeRate',
        'withdrawalSubmissionId',
        'status',
        'withdrawalReason',
        'transactionHash',
        'gatewayTransactionId',
        'requestedAt',
        'approvedAt',
        'approvedBy',
        'rejectedAt',
        'rejectedBy',
        'rejectionReasonId',
        'rejectionNotes',
        'completedAt',
        'adminNotes',
        'supportContent',
        'previousWithdrawalsCount30Days',
        'previousWithdrawalsAmount30Days',
        'ipAddress',
        'userAgent'
    ];

    /**
     * 获取提款列表（带分页和筛选）
     */
    public function getWithdrawals($page = 1, $perPage = 10, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $conditions = [];
        $params = [];

        $sql = "SELECT w.*,
                       u.firstName, u.lastName, u.email,
                       pgs.gatewayName AS gatewayName,
                       pgs.type AS gatewayType,
                       pgs.iconClass AS gatewayIconClass,
                       pgs.gatewayKey,
                       w.gatewaySettingId AS gatewaySettingId,
                       CASE
                           WHEN tea.providerAccountId IS NOT NULL AND tea.providerAccountId <> '' THEN tea.providerAccountId
                           ELSE ta.accountNumber
                       END AS accountNumber,
                       ta.accountNickname,
                       CASE
                           WHEN tea.providerAccountId IS NOT NULL AND tea.providerAccountId <> '' THEN tea.providerAccountId
                           ELSE COALESCE(ta.accountNumber, tea.providerAccountId, tea.name)
                       END AS accountName,
                       tp.platformKey AS groupPlatformKey,
                       tp.shortCode AS sourcePlatformShortCode,
                       tg.label AS groupLabel,
                       tg.scale AS groupScale,
                       tg.unit AS groupUnit,
                       wrr.reasonTitle as rejectionReasonTitle,
                       GROUP_CONCAT(DISTINCT wt.tagName SEPARATOR ', ') as tags
                FROM {$this->table} w
                INNER JOIN clientUsers u ON w.userId = u.id
                LEFT JOIN paymentGatewaySettings pgs ON pgs.id = w.gatewaySettingId
                LEFT JOIN tradingAccounts ta ON w.tradingAccountId = ta.id
                LEFT JOIN tradingPlatforms tp ON ta.platformId = tp.id
                LEFT JOIN tradingAccountExternalAccounts tea ON tea.tradingAccountId = ta.id
                LEFT JOIN trading_group tg
                    ON tg.trading_platforms_key = tp.platformKey
                   AND (
                        (tp.platformKey IN ('mt5', 'mt4') AND tg.id = tea.groupId)
                        OR
                        (tp.platformKey NOT IN ('mt5', 'mt4') AND tg.trading_id = tea.groupId)
                   )
                LEFT JOIN rejectionReasons wrr ON w.rejectionReasonId = wrr.id
                LEFT JOIN withdrawalTagAssignments wta ON w.id = wta.withdrawalId
                LEFT JOIN withdrawalTags wt ON wta.tagId = wt.id";

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
                $conditions[] = "w.status IN (" . implode(', ', $placeholders) . ")";
            }
        }

        if (!empty($filters['startDate'])) {
            $conditions[] = "DATE(w.requestedAt) >= :startDate";
            $params['startDate'] = $filters['startDate'];
        }

        if (!empty($filters['endDate'])) {
            $conditions[] = "DATE(w.requestedAt) <= :endDate";
            $params['endDate'] = $filters['endDate'];
        }

        if (!empty($filters['restrict_to_sales_id']) && (int)$filters['restrict_to_sales_id'] > 0) {
            $conditions[] = "w.userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)";
            $params['restrict_to_sales_id'] = (int)$filters['restrict_to_sales_id'];
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " GROUP BY w.id";
        $sql .= " ORDER BY w.requestedAt DESC";

        // 获取总数
        $countSql = "SELECT COUNT(DISTINCT w.id) as count
                     FROM {$this->table} w";
        if (!empty($conditions)) {
            $countSql .= " WHERE " . implode(' AND ', array_map(function($cond) {
                return str_replace('w.', '', $cond);
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
     * 搜索提款（支持 client name, ID, amount, payment method, tags）
     */
    public function searchWithdrawals($searchTerm, $page = 1, $perPage = 10, $restrictToSalesId = null) {
        $offset = ($page - 1) * $perPage;
        $searchPattern = "%{$searchTerm}%";

        // 检查是否是数字（可能是 client ID）
        $isNumeric = is_numeric($searchTerm);
        $numericValue = $isNumeric ? (int)$searchTerm : null;

        $sql = "SELECT DISTINCT w.*,
                       u.firstName, u.lastName, u.email,
                       pgs.gatewayName AS gatewayName,
                       pgs.type AS gatewayType,
                       pgs.iconClass AS gatewayIconClass,
                       pgs.gatewayKey,
                       w.gatewaySettingId AS gatewaySettingId,
                       CASE
                           WHEN tea.providerAccountId IS NOT NULL AND tea.providerAccountId <> '' THEN tea.providerAccountId
                           ELSE ta.accountNumber
                       END AS accountNumber,
                       ta.accountNickname,
                       CASE
                           WHEN tea.providerAccountId IS NOT NULL AND tea.providerAccountId <> '' THEN tea.providerAccountId
                           ELSE COALESCE(ta.accountNumber, tea.providerAccountId, tea.name)
                       END AS accountName,
                       tg.label AS groupLabel,
                       tg.scale AS groupScale,
                       tg.unit AS groupUnit
                FROM {$this->table} w
                INNER JOIN clientUsers u ON w.userId = u.id
                LEFT JOIN paymentGatewaySettings pgs ON pgs.id = w.gatewaySettingId
                LEFT JOIN tradingAccounts ta ON w.tradingAccountId = ta.id
                LEFT JOIN tradingPlatforms tp ON ta.platformId = tp.id
                LEFT JOIN tradingAccountExternalAccounts tea ON tea.tradingAccountId = ta.id
                LEFT JOIN trading_group tg
                    ON tg.trading_platforms_key = tp.platformKey
                   AND (
                        (tp.platformKey IN ('mt5', 'mt4') AND tg.id = tea.groupId)
                        OR
                        (tp.platformKey NOT IN ('mt5', 'mt4') AND tg.trading_id = tea.groupId)
                   )
                LEFT JOIN withdrawalTagAssignments wta ON w.id = wta.withdrawalId
                LEFT JOIN withdrawalTags wt ON wta.tagId = wt.id
                WHERE (u.firstName LIKE :search1
                   OR u.lastName LIKE :search2
                   OR u.email LIKE :search3
                   OR wt.tagName LIKE :search4";

        // 如果是数字，也搜索 client ID
        if ($isNumeric) {
            $sql .= " OR u.id = :clientId";
        }
        $sql .= ")";
        if ($restrictToSalesId !== null && (int)$restrictToSalesId > 0) {
            $sql .= " AND w.userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)";
        }

        $perPage = max(1, (int)$perPage);
        $offset = max(0, (int)$offset);
        $sql .= " ORDER BY w.requestedAt DESC
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
        $countSql = "SELECT COUNT(DISTINCT w.id) as count
                     FROM {$this->table} w
                     INNER JOIN clientUsers u ON w.userId = u.id
                     LEFT JOIN paymentGatewaySettings pgs ON pgs.id = w.gatewaySettingId
                     LEFT JOIN withdrawalTagAssignments wta ON w.id = wta.withdrawalId
                     LEFT JOIN withdrawalTags wt ON wta.tagId = wt.id
                     WHERE (u.firstName LIKE :search1
                        OR u.lastName LIKE :search2
                        OR u.email LIKE :search3
                        OR wt.tagName LIKE :search4";

        if ($isNumeric) {
            $countSql .= " OR u.id = :clientId";
        }
        $countSql .= ")";
        if ($restrictToSalesId !== null && (int)$restrictToSalesId > 0) {
            $countSql .= " AND w.userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)";
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
     * 获取提款详情（包含完整信息）
     */
    public function getWithdrawalDetails($withdrawalId) {
        $sql = "SELECT w.*,
                       u.firstName, u.lastName, u.email, u.phone, u.country,
                       pgs.gatewayName AS gatewayName,
                       pgs.type AS gatewayType,
                       pgs.iconClass AS gatewayIconClass,
                       pgs.gatewayKey,
                       w.gatewaySettingId AS gatewaySettingId,
                       CASE
                           WHEN tea.providerAccountId IS NOT NULL AND tea.providerAccountId <> '' THEN tea.providerAccountId
                           ELSE ta.accountNumber
                       END AS accountNumber,
                       ta.accountNickname,
                       CASE
                           WHEN tea.providerAccountId IS NOT NULL AND tea.providerAccountId <> '' THEN tea.providerAccountId
                           ELSE COALESCE(ta.accountNumber, tea.providerAccountId, tea.name)
                       END AS accountName,
                       tp.platformKey AS groupPlatformKey,
                       tp.shortCode AS sourcePlatformShortCode,
                       tg.label AS groupLabel,
                       tg.scale AS groupScale,
                       tg.unit AS groupUnit,
                       wrr.reasonTitle as rejectionReasonTitle, wrr.reasonDescription as rejectionReasonDescription,
                       aa.fullName as approvedByName,
                       ar.fullName as rejectedByName
                FROM {$this->table} w
                INNER JOIN clientUsers u ON w.userId = u.id
                LEFT JOIN paymentGatewaySettings pgs ON pgs.id = w.gatewaySettingId
                LEFT JOIN tradingAccounts ta ON w.tradingAccountId = ta.id
                LEFT JOIN tradingPlatforms tp ON ta.platformId = tp.id
                LEFT JOIN tradingAccountExternalAccounts tea ON tea.tradingAccountId = ta.id
                LEFT JOIN trading_group tg
                    ON tg.trading_platforms_key = tp.platformKey
                   AND (
                        (tp.platformKey IN ('mt5', 'mt4') AND tg.id = tea.groupId)
                        OR
                        (tp.platformKey NOT IN ('mt5', 'mt4') AND tg.trading_id = tea.groupId)
                   )
                LEFT JOIN rejectionReasons wrr ON w.rejectionReasonId = wrr.id
                LEFT JOIN adminUsers aa ON w.approvedBy = aa.id
                LEFT JOIN adminUsers ar ON w.rejectedBy = ar.id
                WHERE w.id = :withdrawalId
                LIMIT 1";

        return $this->db->fetchOne($sql, ['withdrawalId' => $withdrawalId]);
    }

    /**
     * 根据transactionId查找
     */
    public function findByTransactionId($transactionId) {
        return $this->findOne(['transactionId' => $transactionId]);
    }

    /**
     * 生成 withdrawal transactionId（格式与原 spCreateWithdrawal 内部一致）。
     * 用于在调用 SP 之前先拿到 transactionId（platform debit 需要把它带给 FinancePro 作为 originOrderId）。
     */
    public function generateTransactionId() {
        $date = date('Ymd');
        $random = str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
        return "TXN-{$date}-W{$random}";
    }

    /**
     * 获取用户的提款列表
     */
    public function getUserWithdrawals($userId, $limit = 10) {
        $limit = max(1, (int)$limit); // 确保是正整数
        $sql = "SELECT w.*, pgs.gatewayName AS gatewayName,
                       pgs.type AS gatewayType,
                       pgs.iconClass AS gatewayIconClass,
                       pgs.gatewayKey,
                       w.gatewaySettingId AS gatewaySettingId
                FROM {$this->table} w
                LEFT JOIN paymentGatewaySettings pgs ON pgs.id = w.gatewaySettingId
                WHERE w.userId = :userId
                ORDER BY w.requestedAt DESC
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
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejectedCount,
                    SUM(CASE WHEN status = 'completed' THEN quotedAmount ELSE 0 END) as totalCompletedAmount,
                    SUM(CASE WHEN status = 'completed' AND DATE(completedAt) = CURDATE() THEN quotedAmount ELSE 0 END) as todayAmount
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
