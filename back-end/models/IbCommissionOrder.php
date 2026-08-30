<?php
/**
 * Commission Order 模型
 * 对应表：ib_commission_order
 */

require_once __DIR__ . '/BaseModel.php';

class IbCommissionOrder extends BaseModel {
    protected $table = 'ib_commission_order';
    protected $primaryKey = 'id';

    protected $fillable = [
        'ibPartnerId', 'orderId', 'depositId', 'ruleId', 'ruleType', 'commission', 'currency',
        'status', 'orderDate', 'statusDate', 'payoutDate', 'cancelDate', 'autoSettled'
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_COMPLETED = 'completed';

    /**
     * 列表（分页），带 IB Name、Email、Tier Level、Rule Name
     * @param int $page
     * @param int $perPage 0 表示不限制
     * @param array $filters ['search' => ?, 'status' => ?]
     * @return array ['items' => [], 'total' => n, 'page' => 1, 'per_page' => 10]
     */
    public function getList($page = 1, $perPage = 10, $filters = []) {
        $params = [];
        $where = ['1=1'];

        if (!empty($filters['status'])) {
            $where[] = 'co.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty(trim((string)($filters['search'] ?? '')))) {
            $search = '%' . trim($filters['search']) . '%';
            $where[] = '(
                COALESCE(TRIM(CONCAT(IFNULL(cu.firstName,\'\'), \' \', IFNULL(cu.lastName,\'\'))), ib.companyName) LIKE :search
                OR COALESCE(cu.email, ib.contactEmail) LIKE :search
                OR r.ruleName LIKE :search2
            )';
            $params['search'] = $search;
            $params['search2'] = $search;
        }

        if (!empty($filters['restrict_to_sales_id']) && (int)$filters['restrict_to_sales_id'] > 0) {
            $where[] = 'ib.userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)';
            $params['restrict_to_sales_id'] = (int)$filters['restrict_to_sales_id'];
        }

        $whereClause = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) AS cnt
            FROM {$this->table} co
            INNER JOIN ibPartners ib ON co.ibPartnerId = ib.id
            LEFT JOIN clientUsers cu ON ib.userId = cu.id
            LEFT JOIN ibTierLevels tl ON ib.tierLevelId = tl.id
            LEFT JOIN ibCommissionRules r ON co.ruleId = r.id
            WHERE {$whereClause}";
        $total = (int) $this->db->fetchOne($countSql, $params)['cnt'];

        $limit = $perPage > 0 ? (int) $perPage : 99999;
        $offset = ($page - 1) * ($perPage > 0 ? $perPage : $limit);

        $sql = "SELECT
                co.id,
                co.ibPartnerId,
                co.orderId,
                co.depositId,
                co.ruleId,
                co.ruleType,
                co.commission,
                co.currency,
                co.status,
                co.orderDate,
                co.statusDate,
                co.payoutDate,
                co.cancelDate,
                COALESCE(co.autoSettled, 0) AS autoSettled,
                COALESCE(TRIM(CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,''))), ib.companyName) AS ibName,
                COALESCE(cu.email, ib.contactEmail) AS email,
                tl.tierLevel AS tierLevel,
                r.ruleName AS ruleName
            FROM {$this->table} co
            INNER JOIN ibPartners ib ON co.ibPartnerId = ib.id
            LEFT JOIN clientUsers cu ON ib.userId = cu.id
            LEFT JOIN ibTierLevels tl ON ib.tierLevelId = tl.id
            LEFT JOIN ibCommissionRules r ON co.ruleId = r.id
            WHERE {$whereClause}
            ORDER BY co.orderDate DESC, co.id DESC
            LIMIT {$limit} OFFSET {$offset}";

        $items = $this->db->fetchAll($sql, $params);
        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage > 0 ? $perPage : $total
        ];
    }

    /**
     * 按状态统计数量（不计筛选条件，全表）
     * @return array ['total' => n, 'pending' => n, 'approved' => n, 'completed' => n, 'cancelled' => n]
     */
    public function getStats($filters = []) {
        $params = [
            'pending' => self::STATUS_PENDING,
            'approved' => self::STATUS_APPROVED,
            'completed' => self::STATUS_COMPLETED,
            'cancelled' => self::STATUS_CANCELLED
        ];
        $joinSql = '';
        $whereSql = '';
        if (!empty($filters['restrict_to_sales_id']) && (int)$filters['restrict_to_sales_id'] > 0) {
            $joinSql = " INNER JOIN ibPartners ib ON co.ibPartnerId = ib.id";
            $whereSql = " WHERE ib.userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)";
            $params['restrict_to_sales_id'] = (int)$filters['restrict_to_sales_id'];
        }
        // 别名避免与 Database::normalizeDataTypes 中布尔字段名 completed 冲突（否则会被转成 true/1）
        $sql = "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN co.status = :pending THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN co.status = :approved THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN co.status = :completed THEN 1 ELSE 0 END) AS completed_cnt,
                SUM(CASE WHEN co.status = :cancelled THEN 1 ELSE 0 END) AS cancelled
            FROM {$this->table} co{$joinSql}{$whereSql}";
        $row = $this->db->fetchOne($sql, $params);
        return [
            'total' => (int)($row['total'] ?? 0),
            'pending' => (int)($row['pending'] ?? 0),
            'approved' => (int)($row['approved'] ?? 0),
            'completed' => (int)($row['completed_cnt'] ?? 0),
            'cancelled' => (int)($row['cancelled'] ?? 0)
        ];
    }

    /**
     * 更新状态为 approved，并设置 statusDate（调用前需确认当前 status 为 pending）
     */
    public function setApproved($id) {
        $now = date('Y-m-d H:i:s');
        $this->update($id, ['status' => self::STATUS_APPROVED, 'statusDate' => $now]);
        return $this->findById($id);
    }

    /**
     * 更新状态为 completed，并设置 payoutDate（调用前需确认当前 status 为 approved）
     */
    public function setCompleted($id) {
        $now = date('Y-m-d H:i:s');
        $this->update($id, ['status' => self::STATUS_COMPLETED, 'payoutDate' => $now]);
        return $this->findById($id);
    }

    /**
     * 更新状态为 cancelled，并设置 cancelDate（调用前需确认当前 status 为 pending 或 approved）
     */
    public function setCancelled($id) {
        $now = date('Y-m-d H:i:s');
        $this->update($id, ['status' => self::STATUS_CANCELLED, 'cancelDate' => $now]);
        return $this->findById($id);
    }

    // ---------- 报表用（与 IbCommissionCalculation 返回结构兼容）----------

    /**
     * 按 IB 汇总：总佣金、已付、待付（status completed=已付，pending/approved=待付）
     */
    public function getIbCommissionStatistics($ibPartnerId, $startDate = null, $endDate = null) {
        $params = ['ibPartnerId' => $ibPartnerId];
        $where = "ico.ibPartnerId = :ibPartnerId AND ico.status != :cancelled";
        $params['cancelled'] = self::STATUS_CANCELLED;
        if ($startDate) {
            $where .= " AND ico.orderDate >= :startDate";
            $params['startDate'] = $startDate;
        }
        if ($endDate) {
            $where .= " AND ico.orderDate <= :endDate";
            $params['endDate'] = $endDate;
        }
        $sql = "SELECT
                SUM(ico.commission) AS totalCommission,
                SUM(CASE WHEN ico.status = :completed THEN ico.commission ELSE 0 END) AS paidCommission,
                SUM(CASE WHEN ico.status IN (:pending, :approved) THEN ico.commission ELSE 0 END) AS pendingCommission,
                COUNT(*) AS totalOrders,
                0 AS totalVolume
            FROM {$this->table} ico
            WHERE {$where}";
        $params['completed'] = self::STATUS_COMPLETED;
        $params['pending'] = self::STATUS_PENDING;
        $params['approved'] = self::STATUS_APPROVED;
        return $this->db->fetchOne($sql, $params);
    }

    /**
     * 某 IB 下、指定客户(按底层账户归属人 d.userId / ta.userId)产生的佣金汇总。
     * IB Report 的「自己」行用它只统计落在 IB 自己账户上的 self-referral 佣金，而不是该 IB 名下全部佣金。
     * 口径与 getCommissionListByClient 的明细列表一致（同样按 COALESCE(d.userId, ta.userId) 过滤）。
     */
    public function getClientCommissionStatistics($ibPartnerId, $clientUserId, $startDate = null, $endDate = null) {
        $params = [
            'ibPartnerId' => (int)$ibPartnerId,
            'clientUserId' => (int)$clientUserId,
            'cancelled' => self::STATUS_CANCELLED,
            'completed' => self::STATUS_COMPLETED,
            'completed2' => self::STATUS_COMPLETED,
            'pending' => self::STATUS_PENDING,
            'approved' => self::STATUS_APPROVED,
        ];
        $where = "ico.ibPartnerId = :ibPartnerId AND ico.status != :cancelled
            AND COALESCE(d.userId, ta.userId) = :clientUserId";
        if (!empty($startDate)) {
            $where .= " AND ico.orderDate >= :startDate";
            $params['startDate'] = $startDate;
        }
        if (!empty($endDate)) {
            $where .= " AND ico.orderDate <= :endDate";
            $params['endDate'] = $endDate;
        }
        $sql = "SELECT
                SUM(ico.commission) AS totalCommission,
                SUM(CASE WHEN ico.status = :completed THEN ico.commission ELSE 0 END) AS paidCommission,
                SUM(CASE WHEN ico.status IN (:pending, :approved) THEN ico.commission ELSE 0 END) AS pendingCommission,
                MAX(CASE WHEN ico.status = :completed2 THEN ico.payoutDate END) AS lastPayoutDate
            FROM {$this->table} ico
            LEFT JOIN deposits d ON d.id = ico.depositId
            LEFT JOIN orders o ON o.id = ico.orderId
            LEFT JOIN tradingAccountExternalAccounts taea ON taea.providerAccountId = o.trading_login
            LEFT JOIN tradingAccounts ta ON ta.id = taea.tradingAccountId
            WHERE {$where}";
        $row = $this->db->fetchOne($sql, $params);
        return [
            'totalCommission' => (float)($row['totalCommission'] ?? 0),
            'paidCommission' => (float)($row['paidCommission'] ?? 0),
            'pendingCommission' => (float)($row['pendingCommission'] ?? 0),
            'lastPayoutDate' => $row['lastPayoutDate'] ?? null,
        ];
    }

    /**
     * 某 IB 的佣金统计（Dashboard 用）：总佣金、入金佣金、订单佣金、按规则汇总
     */
    public function getCommissionStatsForDashboard($ibPartnerId) {
        $params = ['ibPartnerId' => $ibPartnerId, 'cancelled' => self::STATUS_CANCELLED];
        $where = "ico.ibPartnerId = :ibPartnerId AND ico.status != :cancelled";
        $totalRow = $this->db->fetchOne(
            "SELECT SUM(ico.commission) AS total FROM {$this->table} ico WHERE {$where}",
            $params
        );
        $depositRow = $this->db->fetchOne(
            "SELECT SUM(ico.commission) AS total FROM {$this->table} ico WHERE {$where} AND ico.depositId IS NOT NULL",
            $params
        );
        $orderRow = $this->db->fetchOne(
            "SELECT SUM(ico.commission) AS total FROM {$this->table} ico WHERE {$where} AND ico.orderId IS NOT NULL",
            $params
        );
        $byRuleRows = $this->db->fetchAll(
            "SELECT r.ruleName, SUM(ico.commission) AS amount
             FROM {$this->table} ico
             LEFT JOIN ibCommissionRules r ON r.id = ico.ruleId
             WHERE {$where}
             GROUP BY ico.ruleId, r.ruleName
             ORDER BY amount DESC",
            $params
        );
        $byRule = [];
        foreach ($byRuleRows as $r) {
            $byRule[] = [
                'ruleName' => $r['ruleName'] ?? '—',
                'amount' => (float)($r['amount'] ?? 0)
            ];
        }
        return [
            'total' => (float)($totalRow['total'] ?? 0),
            'depositTotal' => (float)($depositRow['total'] ?? 0),
            'orderTotal' => (float)($orderRow['total'] ?? 0),
            'byRule' => $byRule
        ];
    }

    /**
     * 某 IB 终身/区间总佣金（排除 cancelled）
     */
    public function getTotalCommissionByIbPartner($ibPartnerId, $startDate = null, $endDate = null) {
        $params = ['ibPartnerId' => $ibPartnerId];
        $where = "ibPartnerId = :ibPartnerId AND status != :cancelled";
        $params['cancelled'] = self::STATUS_CANCELLED;
        if ($startDate) {
            $where .= " AND orderDate >= :startDate";
            $params['startDate'] = $startDate;
        }
        if ($endDate) {
            $where .= " AND orderDate <= :endDate";
            $params['endDate'] = $endDate;
        }
        $row = $this->db->fetchOne("SELECT SUM(commission) AS total FROM {$this->table} WHERE {$where}", $params);
        return (float)($row['total'] ?? 0);
    }

    /**
     * Total trading volume in lots for one or more IBs.
     * Unique orderId from completed commission rows, then SUM(orders.volume) / 100.
     *
     * @param int|int[] $ibPartnerIds
     */
    public function getTotalTradingVolumeLots($ibPartnerIds, $startDate = null, $endDate = null) {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array)$ibPartnerIds),
            function ($id) { return $id > 0; }
        )));
        if (empty($ids)) {
            return 0.0;
        }

        $placeholders = implode(',', array_map(function ($index) {
            return ':ib' . $index;
        }, array_keys($ids)));
        $params = ['status' => self::STATUS_COMPLETED];
        foreach ($ids as $index => $id) {
            $params['ib' . $index] = $id;
        }

        $where = "ico.ibPartnerId IN ({$placeholders})
            AND ico.status = :status
            AND ico.orderId IS NOT NULL";
        if ($startDate) {
            $where .= " AND ico.orderDate >= :startDate";
            $params['startDate'] = $startDate;
        }
        if ($endDate) {
            $where .= " AND ico.orderDate <= :endDate";
            $params['endDate'] = $endDate;
        }

        $sql = "SELECT COALESCE(SUM(o.volume), 0) / 100 AS totalTradingVolume
            FROM (
                SELECT DISTINCT ico.orderId
                FROM {$this->table} ico
                WHERE {$where}
            ) x
            INNER JOIN orders o ON o.id = x.orderId";
        $row = $this->db->fetchOne($sql, $params);
        return (float)($row['totalTradingVolume'] ?? 0);
    }

    /**
     * 某客户对某 IB 产生的佣金（client 来自 deposit.userId 或 order->trading_login->tradingAccounts.userId）
     */
    public function getTotalCommissionByClient($clientId, $ibPartnerId) {
        $sql = "SELECT SUM(ico.commission) AS total
            FROM {$this->table} ico
            WHERE ico.ibPartnerId = :ibPartnerId AND ico.status != :cancelled
            AND (
                (ico.depositId IS NOT NULL AND ico.depositId IN (SELECT id FROM deposits WHERE userId = :clientId))
                OR
                (ico.orderId IS NOT NULL AND ico.orderId IN (
                    SELECT o.id FROM orders o
                    INNER JOIN tradingAccountExternalAccounts taea ON taea.providerAccountId = o.trading_login
                    INNER JOIN tradingAccounts ta ON ta.id = taea.tradingAccountId
                    WHERE ta.userId = :clientId2
                ))
            )";
        $row = $this->db->fetchOne($sql, [
            'ibPartnerId' => $ibPartnerId,
            'cancelled' => self::STATUS_CANCELLED,
            'clientId' => $clientId,
            'clientId2' => $clientId
        ]);
        return (float)($row['total'] ?? 0);
    }

    /**
     * 总体统计（admin IB Report 用）
     */
    public function getOverallStatistics($filters = []) {
        $params = [];
        $where = "ico.status != :cancelled";
        $params['cancelled'] = self::STATUS_CANCELLED;
        if (!empty($filters['startDate'])) {
            $where .= " AND ico.orderDate >= :startDate";
            $params['startDate'] = $filters['startDate'];
        }
        if (!empty($filters['endDate'])) {
            $where .= " AND ico.orderDate <= :endDate";
            $params['endDate'] = $filters['endDate'];
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $statusMap = ['paid' => self::STATUS_COMPLETED, 'pending' => self::STATUS_PENDING, 'processing' => self::STATUS_APPROVED];
            $st = $statusMap[$filters['status']] ?? $filters['status'];
            if ($filters['status'] === 'processing') {
                $where .= " AND ico.ibPartnerId IN (SELECT ibPartnerId FROM {$this->table} WHERE status = :completed) AND ico.ibPartnerId IN (SELECT ibPartnerId FROM {$this->table} WHERE status IN (:p,:a))";
                $params['completed'] = self::STATUS_COMPLETED;
                $params['p'] = self::STATUS_PENDING;
                $params['a'] = self::STATUS_APPROVED;
            } else {
                $where .= " AND ico.status = :status";
                $params['status'] = $st;
            }
        }
        $this->appendIbReportSearchFilter($where, $params, $filters['search'] ?? '');
        if (!empty($filters['restrict_to_sales_id']) && (int)$filters['restrict_to_sales_id'] > 0) {
            $where .= " AND ib.userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)";
            $params['restrict_to_sales_id'] = (int)$filters['restrict_to_sales_id'];
        }
        $sql = "SELECT
                COUNT(DISTINCT ico.ibPartnerId) AS activeIbs,
                SUM(ico.commission) AS totalCommission,
                SUM(CASE WHEN ico.status = :completed THEN ico.commission ELSE 0 END) AS paidCommission,
                SUM(CASE WHEN ico.status IN (:pending, :approved) THEN ico.commission ELSE 0 END) AS pendingCommission,
                COUNT(*) AS totalCalculations,
                COALESCE(SUM(o.volume), 0) / 100 AS totalLots,
                COUNT(DISTINCT ico.orderId) AS totalTrade
            FROM {$this->table} ico
            INNER JOIN ibPartners ib ON ib.id = ico.ibPartnerId
            LEFT JOIN clientUsers cu ON cu.id = ib.userId
            LEFT JOIN orders o ON o.id = ico.orderId
            WHERE {$where}";
        $params['completed'] = self::STATUS_COMPLETED;
        $params['pending'] = self::STATUS_PENDING;
        $params['approved'] = self::STATUS_APPROVED;
        return $this->db->fetchOne($sql, $params);
    }

    /**
     * 所有 IB 佣金报表列表（分页），与 IbCommissionCalculation::getAllIbCommissionReport 返回字段对齐
     * 需传入 clientCountsMap: [ibPartnerId => clientsReferred]，由调用方从 ib_partner_bind 递归得到
     */
    public function getAllIbCommissionReport($page = 1, $perPage = 10, $filters = [], array $clientCountsMap = []) {
        $offset = ($page - 1) * $perPage;
        $params = ['cancelled' => self::STATUS_CANCELLED];
        $where = "ico.status != :cancelled";
        if (!empty($filters['startDate'])) {
            $where .= " AND ico.orderDate >= :startDate";
            $params['startDate'] = $filters['startDate'];
        }
        if (!empty($filters['endDate'])) {
            $where .= " AND ico.orderDate <= :endDate";
            $params['endDate'] = $filters['endDate'];
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $statusMap = ['paid' => self::STATUS_COMPLETED, 'pending' => self::STATUS_PENDING];
            $st = $statusMap[$filters['status']] ?? $filters['status'];
            if (($filters['status'] ?? '') === 'processing') {
                $where .= " AND ico.ibPartnerId IN (SELECT ibPartnerId FROM {$this->table} WHERE status = :completed) AND ico.ibPartnerId IN (SELECT ibPartnerId FROM {$this->table} WHERE status IN (:p,:a))";
                $params['completed'] = self::STATUS_COMPLETED;
                $params['p'] = self::STATUS_PENDING;
                $params['a'] = self::STATUS_APPROVED;
            } else {
                $where .= " AND ico.status = :status";
                $params['status'] = $st;
            }
        }
        $this->appendIbReportSearchFilter($where, $params, $filters['search'] ?? '');
        if (!empty($filters['restrict_to_sales_id']) && (int)$filters['restrict_to_sales_id'] > 0) {
            $where .= " AND ib.userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)";
            $params['restrict_to_sales_id'] = (int)$filters['restrict_to_sales_id'];
        }
        $orderBy = "ORDER BY totalCommission DESC";
        if (!empty($filters['sort'])) {
            if ($filters['sort'] === 'commission_asc') $orderBy = "ORDER BY totalCommission ASC";
            elseif ($filters['sort'] === 'name_asc') $orderBy = "ORDER BY displayName ASC";
            elseif ($filters['sort'] === 'name_desc') $orderBy = "ORDER BY displayName DESC";
        }
        $params['completed'] = self::STATUS_COMPLETED;
        $params['pending'] = self::STATUS_PENDING;
        $params['approved'] = self::STATUS_APPROVED;
        // tradingVolume 用 LEFT JOIN 派生表计算，避免 SELECT 中关联子查询在 GROUP BY 下导致的错误
        $sql = "SELECT
                ico.ibPartnerId,
                ib.ibCode,
                ib.companyName,
                ib.contactEmail,
                ib.country,
                MAX(COALESCE(TRIM(CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,''))), cu.email, ib.companyName)) AS displayName,
                COUNT(ico.id) AS totalOrders,
                COALESCE(vol.tradingVolume, 0) AS tradingVolume,
                MAX(ib.totalClients) AS totalClients,
                SUM(ico.commission) AS totalCommission,
                SUM(CASE WHEN ico.status = :completed THEN ico.commission ELSE 0 END) AS paidCommission,
                SUM(CASE WHEN ico.status IN (:pending, :approved) THEN ico.commission ELSE 0 END) AS pendingCommission,
                MAX(ico.orderDate) AS lastCalculationDate,
                MAX(CASE WHEN ico.status = :completed THEN ico.payoutDate ELSE NULL END) AS lastPayoutDate
            FROM {$this->table} ico
            INNER JOIN ibPartners ib ON ib.id = ico.ibPartnerId
            LEFT JOIN clientUsers cu ON cu.id = ib.userId
            LEFT JOIN (
                SELECT ico2.ibPartnerId, SUM(o.volume) / 100 AS tradingVolume
                FROM (SELECT ibPartnerId, orderId FROM {$this->table} WHERE orderId IS NOT NULL GROUP BY ibPartnerId, orderId) ico2
                INNER JOIN orders o ON o.id = ico2.orderId
                GROUP BY ico2.ibPartnerId
            ) vol ON vol.ibPartnerId = ico.ibPartnerId
            WHERE {$where}
            GROUP BY ico.ibPartnerId, ib.ibCode, ib.companyName, ib.contactEmail, ib.country
            {$orderBy}
            LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        $items = $this->db->fetchAll($sql, $params);
        foreach ($items as &$item) {
            // 与 Final Review List 一致：优先使用 ibPartners.totalClients（调用方需先 syncAllTotalClientsFromBindTable）
            $item['clientsReferred'] = isset($item['totalClients']) ? (int)$item['totalClients'] : (int)($clientCountsMap[$item['ibPartnerId']] ?? 0);
        }
        unset($item);
        $countSql = "SELECT COUNT(DISTINCT ico.ibPartnerId) AS cnt
            FROM {$this->table} ico
            INNER JOIN ibPartners ib ON ib.id = ico.ibPartnerId
            LEFT JOIN clientUsers cu ON cu.id = ib.userId
            WHERE {$where}";
        // 只传 count 的 WHERE 里出现的参数，避免 PDO 报 “parameter was not defined”（主查询的 completed/pending/approved 不在 count 的 where 里）
        $countParams = ['cancelled' => $params['cancelled']];
        if (array_key_exists('startDate', $params)) $countParams['startDate'] = $params['startDate'];
        if (array_key_exists('endDate', $params)) $countParams['endDate'] = $params['endDate'];
        if (array_key_exists('search', $params)) $countParams['search'] = $params['search'];
        if (array_key_exists('status', $params)) $countParams['status'] = $params['status'];
        if (array_key_exists('restrict_to_sales_id', $params)) $countParams['restrict_to_sales_id'] = $params['restrict_to_sales_id'];
        if (array_key_exists('p', $params)) {
            $countParams['p'] = $params['p'];
            $countParams['a'] = $params['a'];
            $countParams['completed'] = $params['completed'];
        }
        $countRow = $this->db->fetchOne($countSql, $countParams);
        $total = is_array($countRow) ? (int)($countRow['cnt'] ?? 0) : 0;

        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    private function appendIbReportSearchFilter(string &$where, array &$params, $searchRaw): void
    {
        $search = trim((string)$searchRaw);
        if ($search === '') {
            return;
        }

        $where .= " AND (
            LOWER(cu.firstName) LIKE :search
            OR LOWER(cu.lastName) LIKE :search
            OR LOWER(TRIM(CONCAT(IFNULL(cu.firstName, ''), ' ', IFNULL(cu.lastName, '')))) LIKE :search
            OR LOWER(ib.ibCode) LIKE :search
            OR LOWER(ib.contactEmail) LIKE :search
        )";
        $params['search'] = '%' . mb_strtolower($search, 'UTF-8') . '%';
    }

    /**
     * 某 IB 的佣金明细列表（供 IB Report Detail）：含 orderId/depositId/ruleId/ruleType、规则与订单/入金信息，用于 Product Type、Rule Name、数量与费率列、Status
     */
    public function getIbCommissionList($ibPartnerId, $page = 1, $perPage = 10, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $params = ['ibPartnerId' => $ibPartnerId];
        $where = "ico.ibPartnerId = :ibPartnerId";
        if (!empty($filters['startDate'])) {
            $where .= " AND ico.orderDate >= :startDate";
            $params['startDate'] = $filters['startDate'];
        }
        if (!empty($filters['endDate'])) {
            $where .= " AND ico.orderDate <= :endDate";
            $params['endDate'] = $filters['endDate'];
        }
        // 详情列表的扩展字段：客户/账户/平台/balance（与 getCommissionListByClient 同构）
        // - order 行通过 o.trading_login → taea/ta/tp 拿；deposit 行通过 d.tradingAccountId → ta_dep/taea_dep/tp_dep 拿
        // - lastDepositTime 用相关子查询取该记录归属 client 最近一笔已完成入金时间
        // - 实时字段（equity / marginLevel / credit）DB 里没有，由 controller 写死 0
        $sql = "SELECT ico.id, ico.orderId, ico.depositId, ico.ruleId, ico.ruleType, ico.commission AS finalCommission,
                ico.orderDate AS calculatedAt, ico.status AS calculationStatus, ico.payoutDate AS withdrawnAt,
                r.ruleName, r.ruleType AS ruleRuleType, r.rate AS ruleRate, r.fixed_amount AS ruleFixedAmount,
                d.amount AS depositAmount, d.createdAt AS depositCreatedAt,
                o.volume AS orderVolume, o.symbol AS orderSymbol, o.profit AS orderProfit,
                o.trading_id AS orderTradingId,
                o.openTime AS orderOpenTime, o.closeTime AS orderCloseTime,
                ics.symbolName, sec.securityName,
                ta.accountNumber AS orderAccountNumber, ta.accountType AS orderAccountType, ta.accountCurrency AS orderAccountCurrency,
                taea.providerAccountId AS orderProviderAccountId,
                tp.displayName AS orderPlatformName, tp.shortCode AS orderPlatformCode,
                taea.platformBalance AS orderPlatformBalance,
                taea.platformCredit AS orderPlatformCredit,
                tg.label AS orderAccountTypeLabel,
                ta_dep.accountNumber AS depositAccountNumber, ta_dep.accountType AS depositAccountType, ta_dep.accountCurrency AS depositAccountCurrency,
                taea_dep.providerAccountId AS depositProviderAccountId,
                tp_dep.displayName AS depositPlatformName, tp_dep.shortCode AS depositPlatformCode,
                taea_dep.platformBalance AS depositPlatformBalance,
                taea_dep.platformCredit AS depositPlatformCredit,
                tg_dep.label AS depositAccountTypeLabel,
                cu_owner.firstName AS ownerFirstName, cu_owner.lastName AS ownerLastName,
                cu_owner.email AS ownerEmail, cu_owner.kycStatus AS ownerKycStatus,
                (SELECT MAX(createdAt) FROM deposits WHERE userId = COALESCE(d.userId, ta.userId, ta_dep.userId) AND status = 'completed') AS lastDepositTime
            FROM {$this->table} ico
            LEFT JOIN ibCommissionRules r ON r.id = ico.ruleId
            LEFT JOIN deposits d ON d.id = ico.depositId
            LEFT JOIN orders o ON o.id = ico.orderId
            LEFT JOIN tradingAccountExternalAccounts taea ON taea.providerAccountId = o.trading_login
            LEFT JOIN tradingAccounts ta ON ta.id = taea.tradingAccountId
            LEFT JOIN tradingPlatforms tp ON tp.id = ta.platformId
            LEFT JOIN trading_group tg ON tg.trading_platforms_key = tp.platformKey
                AND ((tp.platformKey IN ('mt5', 'mt4') AND tg.id = taea.groupId)
                  OR (tp.platformKey NOT IN ('mt5', 'mt4') AND tg.trading_id = taea.groupId))
            LEFT JOIN tradingAccounts ta_dep ON ta_dep.id = d.tradingAccountId
            LEFT JOIN tradingAccountExternalAccounts taea_dep ON taea_dep.tradingAccountId = ta_dep.id
            LEFT JOIN tradingPlatforms tp_dep ON tp_dep.id = ta_dep.platformId
            LEFT JOIN trading_group tg_dep ON tg_dep.trading_platforms_key = tp_dep.platformKey
                AND ((tp_dep.platformKey IN ('mt5', 'mt4') AND tg_dep.id = taea_dep.groupId)
                  OR (tp_dep.platformKey NOT IN ('mt5', 'mt4') AND tg_dep.trading_id = taea_dep.groupId))
            LEFT JOIN clientUsers cu_owner ON cu_owner.id = COALESCE(d.userId, ta.userId, ta_dep.userId)
            LEFT JOIN ibCustomSymbols ics ON (o.symbol_id > 0 AND ics.trading_id = o.symbol_id)
            LEFT JOIN ibCustomSecurities sec ON sec.id = ics.securityId
            WHERE {$where}
            ORDER BY ico.orderDate DESC, ico.id DESC
            LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        $items = $this->db->fetchAll($sql, $params);
        $countSql = "SELECT COUNT(*) AS cnt FROM {$this->table} ico WHERE {$where}";
        // 只传 count 的 WHERE 里出现的参数，避免 PDO 报 “parameter was not defined”
        $countParams = ['ibPartnerId' => $params['ibPartnerId']];
        if (array_key_exists('startDate', $params)) $countParams['startDate'] = $params['startDate'];
        if (array_key_exists('endDate', $params)) $countParams['endDate'] = $params['endDate'];
        $countRow = $this->db->fetchOne($countSql, $countParams);
        $total = is_array($countRow) ? (int)($countRow['cnt'] ?? 0) : 0;
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * 某 IB 下、指定客户（Direct Client）产生的佣金明细列表，结构与 getIbCommissionList 一致，供 formatBreakdown 使用
     */
    public function getCommissionListByClient($ibPartnerId, $clientUserId, $page = 1, $perPage = 100, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $params = ['ibPartnerId' => $ibPartnerId, 'clientUserId' => $clientUserId];
        $where = "ico.ibPartnerId = :ibPartnerId AND (d.userId = :clientUserId OR ta.userId = :clientUserId)";
        if (!empty($filters['startDate'])) {
            $where .= " AND ico.orderDate >= :startDate";
            $params['startDate'] = $filters['startDate'];
        }
        if (!empty($filters['endDate'])) {
            $where .= " AND ico.orderDate <= :endDate";
            $params['endDate'] = $filters['endDate'];
        }
        // 详情列表的扩展字段：客户/账户/平台/balance/credit（来源 tradingAccountExternalAccounts.platformBalance / platformCredit）
        // - order 行通过 o.trading_login → taea/ta/tp 拿；deposit 行通过 d.tradingAccountId → ta_dep/taea_dep/tp_dep 拿
        // - lastDepositTime 用子查询取该 client 最近一笔已完成入金时间
        // - 实时字段（equity / marginLevel）DB 里没有，由 controller 写死 0
        $sql = "SELECT ico.id, ico.orderId, ico.depositId, ico.ruleId, ico.ruleType, ico.commission AS finalCommission,
                ico.orderDate AS calculatedAt, ico.status AS calculationStatus, ico.payoutDate AS withdrawnAt,
                r.ruleName, r.ruleType AS ruleRuleType, r.rate AS ruleRate, r.fixed_amount AS ruleFixedAmount,
                d.amount AS depositAmount, d.createdAt AS depositCreatedAt,
                o.volume AS orderVolume, o.symbol AS orderSymbol, o.profit AS orderProfit,
                o.trading_id AS orderTradingId,
                o.openTime AS orderOpenTime, o.closeTime AS orderCloseTime,
                ics.symbolName, sec.securityName,
                ta.accountNumber AS orderAccountNumber, ta.accountType AS orderAccountType, ta.accountCurrency AS orderAccountCurrency,
                taea.providerAccountId AS orderProviderAccountId,
                tp.displayName AS orderPlatformName, tp.shortCode AS orderPlatformCode,
                taea.platformBalance AS orderPlatformBalance,
                taea.platformCredit AS orderPlatformCredit,
                tg.label AS orderAccountTypeLabel,
                ta_dep.accountNumber AS depositAccountNumber, ta_dep.accountType AS depositAccountType, ta_dep.accountCurrency AS depositAccountCurrency,
                taea_dep.providerAccountId AS depositProviderAccountId,
                tp_dep.displayName AS depositPlatformName, tp_dep.shortCode AS depositPlatformCode,
                taea_dep.platformBalance AS depositPlatformBalance,
                taea_dep.platformCredit AS depositPlatformCredit,
                tg_dep.label AS depositAccountTypeLabel,
                cu_owner.firstName AS ownerFirstName, cu_owner.lastName AS ownerLastName,
                cu_owner.email AS ownerEmail, cu_owner.kycStatus AS ownerKycStatus,
                (SELECT MAX(createdAt) FROM deposits WHERE userId = :clientUserIdLast AND status = 'completed') AS lastDepositTime
            FROM {$this->table} ico
            LEFT JOIN ibCommissionRules r ON r.id = ico.ruleId
            LEFT JOIN deposits d ON d.id = ico.depositId
            LEFT JOIN orders o ON o.id = ico.orderId
            LEFT JOIN tradingAccountExternalAccounts taea ON taea.providerAccountId = o.trading_login
            LEFT JOIN tradingAccounts ta ON ta.id = taea.tradingAccountId
            LEFT JOIN tradingPlatforms tp ON tp.id = ta.platformId
            LEFT JOIN trading_group tg ON tg.trading_platforms_key = tp.platformKey
                AND ((tp.platformKey IN ('mt5', 'mt4') AND tg.id = taea.groupId)
                  OR (tp.platformKey NOT IN ('mt5', 'mt4') AND tg.trading_id = taea.groupId))
            LEFT JOIN tradingAccounts ta_dep ON ta_dep.id = d.tradingAccountId
            LEFT JOIN tradingAccountExternalAccounts taea_dep ON taea_dep.tradingAccountId = ta_dep.id
            LEFT JOIN tradingPlatforms tp_dep ON tp_dep.id = ta_dep.platformId
            LEFT JOIN trading_group tg_dep ON tg_dep.trading_platforms_key = tp_dep.platformKey
                AND ((tp_dep.platformKey IN ('mt5', 'mt4') AND tg_dep.id = taea_dep.groupId)
                  OR (tp_dep.platformKey NOT IN ('mt5', 'mt4') AND tg_dep.trading_id = taea_dep.groupId))
            LEFT JOIN clientUsers cu_owner ON cu_owner.id = COALESCE(d.userId, ta.userId, ta_dep.userId)
            LEFT JOIN ibCustomSymbols ics ON (o.symbol_id > 0 AND ics.trading_id = o.symbol_id)
            LEFT JOIN ibCustomSecurities sec ON sec.id = ics.securityId
            WHERE {$where}
            ORDER BY ico.orderDate DESC, ico.id DESC
            LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        $params['clientUserIdLast'] = $clientUserId;
        $items = $this->db->fetchAll($sql, $params);
        $countSql = "SELECT COUNT(*) AS cnt FROM {$this->table} ico
            LEFT JOIN deposits d ON d.id = ico.depositId
            LEFT JOIN orders o ON o.id = ico.orderId
            LEFT JOIN tradingAccountExternalAccounts taea ON taea.providerAccountId = o.trading_login
            LEFT JOIN tradingAccounts ta ON ta.id = taea.tradingAccountId
            WHERE {$where}";
        $countParams = ['ibPartnerId' => $params['ibPartnerId'], 'clientUserId' => $params['clientUserId']];
        if (array_key_exists('startDate', $params)) $countParams['startDate'] = $params['startDate'];
        if (array_key_exists('endDate', $params)) $countParams['endDate'] = $params['endDate'];
        $countRow = $this->db->fetchOne($countSql, $countParams);
        $total = is_array($countRow) ? (int)($countRow['cnt'] ?? 0) : 0;
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * 活跃客户数：该 IB 在 sinceDate 之后有佣金记录的客户数（通过 deposit 或 order 关联到 clientUsers）
     */
    public function getActiveClientsCount($ibPartnerId, $sinceDate) {
        $sql = "SELECT COUNT(DISTINCT clientId) AS count FROM (
            SELECT d.userId AS clientId FROM {$this->table} ico INNER JOIN deposits d ON d.id = ico.depositId
            WHERE ico.ibPartnerId = :ibPartnerId AND ico.orderDate >= :sinceDate AND ico.status != :cancelled
            UNION
            SELECT ta.userId AS clientId FROM {$this->table} ico
            INNER JOIN orders o ON o.id = ico.orderId
            INNER JOIN tradingAccountExternalAccounts taea ON taea.providerAccountId = o.trading_login
            INNER JOIN tradingAccounts ta ON ta.id = taea.tradingAccountId
            WHERE ico.ibPartnerId = :ibPartnerId2 AND ico.orderDate >= :sinceDate2 AND ico.status != :cancelled2
        ) AS u";
        $row = $this->db->fetchOne($sql, [
            'ibPartnerId' => $ibPartnerId,
            'sinceDate' => $sinceDate,
            'ibPartnerId2' => $ibPartnerId,
            'sinceDate2' => $sinceDate,
            'cancelled' => self::STATUS_CANCELLED,
            'cancelled2' => self::STATUS_CANCELLED
        ]);
        return (int)($row['count'] ?? 0);
    }
}
