<?php
/**
 * Internal Transfer Model
 * 对应表: internalTransfers
 */

require_once __DIR__ . '/BaseModel.php';

class InternalTransfer extends BaseModel {
    protected $table = 'internalTransfers';
    protected $primaryKey = 'id';
    protected $fillable = [
        'transactionId',
        'userId',
        'fromType',
        'fromTradingAccountId',
        'toTradingAccountId',
        'amount',
        'fromAmountScale',
        'fromPlatformAmount',
        'fromDisplayUnit',
        'toAmountScale',
        'toPlatformAmount',
        'toDisplayUnit',
        'status',
        'requestedAt',
        'approvedAt',
        'approvedBy',
        'rejectedAt',
        'rejectedBy',
        'rejectionReasonId',
        'rejectionNotes',
        'completedAt',
        'failureReason',
        'adminNotes',
        'clientNotes',
        'ipAddress',
        'userAgent'
    ];

    /**
     * 获取内部转账列表（带分页和筛选）
     */
    public function getInternalTransfers($page = 1, $perPage = 10, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $conditions = [];
        $params = [];

        // 构建SQL
        $sql = "SELECT it.*,
                       u.firstName, u.lastName, u.email,
                       CASE
                           WHEN tea_from.providerAccountId IS NOT NULL AND tea_from.providerAccountId <> '' THEN tea_from.providerAccountId
                           ELSE ta_from.accountNumber
                       END AS fromAccountNumber,
                       ta_from.accountNickname as fromAccountNickname,
                       tp_from.platformKey AS fromGroupPlatformKey,
                       tp_from.shortCode AS fromPlatformShortCode,
                       tg_from.label AS fromGroupLabel,
                       CASE
                           WHEN tea_to.providerAccountId IS NOT NULL AND tea_to.providerAccountId <> '' THEN tea_to.providerAccountId
                           ELSE ta_to.accountNumber
                       END AS toAccountNumber,
                       ta_to.accountNickname as toAccountNickname,
                       tp_to.platformKey AS toGroupPlatformKey,
                       tp_to.shortCode AS toPlatformShortCode,
                       tg_to.label AS toGroupLabel,
                       GROUP_CONCAT(DISTINCT dt.tagName SEPARATOR ', ') as tags
                FROM {$this->table} it
                INNER JOIN clientUsers u ON it.userId = u.id
                LEFT JOIN tradingAccounts ta_from ON it.fromTradingAccountId = ta_from.id
                LEFT JOIN tradingPlatforms tp_from ON ta_from.platformId = tp_from.id
                LEFT JOIN tradingAccountExternalAccounts tea_from ON tea_from.tradingAccountId = ta_from.id
                LEFT JOIN trading_group tg_from
                    ON tg_from.trading_platforms_key = tp_from.platformKey
                   AND (
                        (tp_from.platformKey IN ('mt5', 'mt4') AND tg_from.id = tea_from.groupId)
                        OR
                        (tp_from.platformKey NOT IN ('mt5', 'mt4') AND tg_from.trading_id = tea_from.groupId)
                   )
                INNER JOIN tradingAccounts ta_to ON it.toTradingAccountId = ta_to.id
                LEFT JOIN tradingPlatforms tp_to ON ta_to.platformId = tp_to.id
                LEFT JOIN tradingAccountExternalAccounts tea_to ON tea_to.tradingAccountId = ta_to.id
                LEFT JOIN trading_group tg_to
                    ON tg_to.trading_platforms_key = tp_to.platformKey
                   AND (
                        (tp_to.platformKey IN ('mt5', 'mt4') AND tg_to.id = tea_to.groupId)
                        OR
                        (tp_to.platformKey NOT IN ('mt5', 'mt4') AND tg_to.trading_id = tea_to.groupId)
                   )
                LEFT JOIN internalTransferTagAssignments itta ON it.id = itta.internalTransferId
                LEFT JOIN depositTags dt ON itta.tagId = dt.id";

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
                $conditions[] = "it.status IN (" . implode(', ', $placeholders) . ")";
            }
        }

        if (!empty($filters['fromType'])) {
            // 兼容处理：如果过滤的是 'wallet'，同时匹配 'available_balance'（旧数据）
            if ($filters['fromType'] == 'wallet' || $filters['fromType'] == 'available_balance') {
                $conditions[] = "(it.fromType = 'wallet' OR it.fromType = 'available_balance')";
            } else {
                $conditions[] = "it.fromType = :fromType";
                $params['fromType'] = $filters['fromType'];
            }
        }

        if (!empty($filters['startDate'])) {
            $conditions[] = "DATE(it.requestedAt) >= :startDate";
            $params['startDate'] = $filters['startDate'];
        }

        if (!empty($filters['endDate'])) {
            $conditions[] = "DATE(it.requestedAt) <= :endDate";
            $params['endDate'] = $filters['endDate'];
        }

        if (!empty($filters['restrict_to_sales_id']) && (int)$filters['restrict_to_sales_id'] > 0) {
            $conditions[] = "it.userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)";
            $params['restrict_to_sales_id'] = (int)$filters['restrict_to_sales_id'];
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " GROUP BY it.id";
        $sql .= " ORDER BY it.requestedAt DESC";

        // 获取总数
        $countSql = "SELECT COUNT(DISTINCT it.id) as count
                     FROM {$this->table} it";
        if (!empty($conditions)) {
            $countSql .= " WHERE " . implode(' AND ', array_map(function($cond) {
                return str_replace('it.', '', $cond);
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
     * 获取内部转账详情（包含完整信息）
     */
    public function getInternalTransferDetails($transferId) {
        $sql = "SELECT it.*,
                       u.firstName, u.lastName, u.email, u.phone, u.country,
                       CASE
                           WHEN tea_from.providerAccountId IS NOT NULL AND tea_from.providerAccountId <> '' THEN tea_from.providerAccountId
                           ELSE ta_from.accountNumber
                       END AS fromAccountNumber,
                       ta_from.accountNickname as fromAccountNickname,
                       tp_from.platformKey AS fromGroupPlatformKey,
                       tp_from.shortCode AS fromPlatformShortCode,
                       CASE
                           WHEN tea_to.providerAccountId IS NOT NULL AND tea_to.providerAccountId <> '' THEN tea_to.providerAccountId
                           ELSE ta_to.accountNumber
                       END AS toAccountNumber,
                       ta_to.accountNickname as toAccountNickname,
                       tp_to.platformKey AS toGroupPlatformKey,
                       tp_to.shortCode AS toPlatformShortCode,
                       tg_from.label AS fromGroupLabel,
                       tg_from.scale AS fromGroupScale,
                       tg_from.unit AS fromGroupUnit,
                       tg_to.label AS toGroupLabel,
                       tg_to.scale AS toGroupScale,
                       tg_to.unit AS toGroupUnit,
                       aa.fullName as approvedByName,
                       ar.fullName as rejectedByName,
                       rr.reasonTitle as rejectionReasonTitle
                FROM {$this->table} it
                INNER JOIN clientUsers u ON it.userId = u.id
                LEFT JOIN tradingAccounts ta_from ON it.fromTradingAccountId = ta_from.id
                LEFT JOIN tradingPlatforms tp_from ON ta_from.platformId = tp_from.id
                LEFT JOIN tradingAccountExternalAccounts tea_from ON tea_from.tradingAccountId = ta_from.id
                LEFT JOIN trading_group tg_from
                    ON tg_from.trading_platforms_key = tp_from.platformKey
                   AND (
                        (tp_from.platformKey IN ('mt5', 'mt4') AND tg_from.id = tea_from.groupId)
                        OR
                        (tp_from.platformKey NOT IN ('mt5', 'mt4') AND tg_from.trading_id = tea_from.groupId)
                   )
                INNER JOIN tradingAccounts ta_to ON it.toTradingAccountId = ta_to.id
                LEFT JOIN tradingPlatforms tp_to ON ta_to.platformId = tp_to.id
                LEFT JOIN tradingAccountExternalAccounts tea_to ON tea_to.tradingAccountId = ta_to.id
                LEFT JOIN trading_group tg_to
                    ON tg_to.trading_platforms_key = tp_to.platformKey
                   AND (
                        (tp_to.platformKey IN ('mt5', 'mt4') AND tg_to.id = tea_to.groupId)
                        OR
                        (tp_to.platformKey NOT IN ('mt5', 'mt4') AND tg_to.trading_id = tea_to.groupId)
                   )
                LEFT JOIN adminUsers aa ON it.approvedBy = aa.id
                LEFT JOIN adminUsers ar ON it.rejectedBy = ar.id
                LEFT JOIN rejectionReasons rr ON it.rejectionReasonId = rr.id
                WHERE it.id = :transferId
                LIMIT 1";

        return $this->db->fetchOne($sql, ['transferId' => $transferId]);
    }

    /**
     * 搜索内部转账
     */
    public function searchInternalTransfers($searchTerm, $page = 1, $perPage = 10, $restrictToSalesId = null) {
        $offset = ($page - 1) * $perPage;
        $searchPattern = "%{$searchTerm}%";

        // 检查是否是数字（可能是 client ID）
        $isNumeric = is_numeric($searchTerm);
        $numericValue = $isNumeric ? (int)$searchTerm : null;

        $sql = "SELECT DISTINCT it.*,
                       u.firstName, u.lastName, u.email,
                       ta_from.accountNumber as fromAccountNumber, ta_from.accountNickname as fromAccountNickname,
                       ta_to.accountNumber as toAccountNumber, ta_to.accountNickname as toAccountNickname
                FROM {$this->table} it
                INNER JOIN clientUsers u ON it.userId = u.id
                LEFT JOIN tradingAccounts ta_from ON it.fromTradingAccountId = ta_from.id
                INNER JOIN tradingAccounts ta_to ON it.toTradingAccountId = ta_to.id
                LEFT JOIN internalTransferTagAssignments itta ON it.id = itta.internalTransferId
                LEFT JOIN depositTags dt ON itta.tagId = dt.id
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
            $sql .= " AND it.userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)";
        }

        $perPage = max(1, (int)$perPage);
        $offset = max(0, (int)$offset);
        $sql .= " ORDER BY it.requestedAt DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $params = [
            'search1' => $searchPattern,
            'search2' => $searchPattern,
            'search3' => $searchPattern,
            'search4' => $searchPattern
        ];

        if ($isNumeric) {
            $params['clientId'] = (int)$numericValue;
        }
        if ($restrictToSalesId !== null && (int)$restrictToSalesId > 0) {
            $params['restrict_to_sales_id'] = (int)$restrictToSalesId;
        }

        $items = $this->db->fetchAll($sql, $params);

        // 获取总数
        $countSql = "SELECT COUNT(DISTINCT it.id) as count
                     FROM {$this->table} it
                     INNER JOIN clientUsers u ON it.userId = u.id
                     LEFT JOIN tradingAccounts ta_from ON it.fromTradingAccountId = ta_from.id
                     INNER JOIN tradingAccounts ta_to ON it.toTradingAccountId = ta_to.id
                     LEFT JOIN internalTransferTagAssignments itta ON it.id = itta.internalTransferId
                     LEFT JOIN depositTags dt ON itta.tagId = dt.id
                     WHERE (u.firstName LIKE :search1
                        OR u.lastName LIKE :search2
                        OR u.email LIKE :search3
                        OR dt.tagName LIKE :search4";

        if ($isNumeric) {
            $countSql .= " OR u.id = :clientId";
        }
        $countSql .= ")";
        if ($restrictToSalesId !== null && (int)$restrictToSalesId > 0) {
            $countSql .= " AND it.userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)";
        }

        $countParams = [
            'search1' => $searchPattern,
            'search2' => $searchPattern,
            'search3' => $searchPattern,
            'search4' => $searchPattern
        ];
        if ($isNumeric) {
            $countParams['clientId'] = (int)$numericValue;
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
     * 生成唯一的交易ID
     */
    public function generateTransactionId() {
        $prefix = 'ITF';
        $date = date('Ymd');
        $random = str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);
        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * 获取用户的内部转账列表（客户端）
     */
    public function getUserInternalTransfers($userId, $limit = 10) {
        $limit = max(1, (int)$limit); // 确保是正整数
        $sql = "SELECT it.*,
                       ta_from.accountNumber as fromAccountNumber, ta_from.accountNickname as fromAccountNickname,
                       ta_to.accountNumber as toAccountNumber, ta_to.accountNickname as toAccountNickname
                FROM {$this->table} it
                LEFT JOIN tradingAccounts ta_from ON it.fromTradingAccountId = ta_from.id
                INNER JOIN tradingAccounts ta_to ON it.toTradingAccountId = ta_to.id
                WHERE it.userId = :userId
                ORDER BY it.requestedAt DESC
                LIMIT {$limit}";

        return $this->db->fetchAll($sql, [
            'userId' => $userId
        ]);
    }

    /**
     * 获取内部转账统计信息
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
