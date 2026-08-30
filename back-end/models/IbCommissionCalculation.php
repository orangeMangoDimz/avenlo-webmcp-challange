<?php
/**
 * IB佣金计算记录模型
 * 对应表：ibCommissionCalculations
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/IbProgramSetting.php';

class IbCommissionCalculation extends BaseModel {
    protected $table = 'ibCommissionCalculations';
    protected $primaryKey = 'id';

    protected $fillable = [
        'orderId', 'ibPartnerId', 'tierLevel', 'clientId', 'tradingLogin', 'symbolId', 'symbol',
        'volume', 'orderOpenTime', 'orderCloseTime', 'holdDuration', 'spread',
        'originalCommission', 'extractedCommission', 'finalCommission',
        'commissionType', 'ruleId', 'productRuleId', 'additionalRuleId',
        'calculationDetails', 'calculationStatus', 'paymentCycle',
        'periodStart', 'periodEnd', 'calculatedAt', 'withdrawnAt', 'withdrawalId'
    ];

    /**
     * 获取IB代理的佣金统计
     */
    public function getIbCommissionStatistics($ibPartnerId, $startDate = null, $endDate = null) {
        $conditions = ['ibPartnerId' => $ibPartnerId];
        $params = ['ibPartnerId' => $ibPartnerId];

        if ($startDate) {
            $conditions['periodStart'] = $startDate;
            $params['periodStart'] = $startDate;
        }

        if ($endDate) {
            $conditions['periodEnd'] = $endDate;
            $params['periodEnd'] = $endDate;
        }

        $whereClause = "WHERE ibPartnerId = :ibPartnerId";
        if ($startDate) {
            $whereClause .= " AND (periodStart >= :periodStart OR calculatedAt >= :periodStart)";
        }
        if ($endDate) {
            $whereClause .= " AND (periodEnd <= :periodEnd OR calculatedAt <= :periodEnd)";
        }

        $sql = "SELECT
                    SUM(finalCommission) as totalCommission,
                    SUM(CASE WHEN calculationStatus = 'withdrawn' THEN finalCommission ELSE 0 END) as paidCommission,
                    SUM(CASE WHEN calculationStatus = 'calculated' THEN finalCommission ELSE 0 END) as pendingCommission,
                    COUNT(*) as totalOrders,
                    SUM(volume) as totalVolume
                FROM {$this->table}
                {$whereClause}";

        return $this->db->fetchOne($sql, $params);
    }

    /**
     * 获取IB代理的佣金列表（带分页）
     */
    public function getIbCommissionList($ibPartnerId, $page = 1, $perPage = 10, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $conditions = ['ibPartnerId' => $ibPartnerId];
        $params = ['ibPartnerId' => $ibPartnerId];

        if (isset($filters['status'])) {
            $conditions['calculationStatus'] = $filters['status'];
            $params['calculationStatus'] = $filters['status'];
        }

        if (isset($filters['startDate'])) {
            $params['startDate'] = $filters['startDate'];
        }

        if (isset($filters['endDate'])) {
            $params['endDate'] = $filters['endDate'];
        }

        $whereClause = "WHERE ibPartnerId = :ibPartnerId";
        if (isset($filters['status'])) {
            $whereClause .= " AND calculationStatus = :calculationStatus";
        }
        if (isset($filters['startDate'])) {
            $whereClause .= " AND calculatedAt >= :startDate";
        }
        if (isset($filters['endDate'])) {
            $whereClause .= " AND calculatedAt <= :endDate";
        }

        $sql = "SELECT
                    icc.*,
                    o.symbol as orderSymbol,
                    o.volume as orderVolume,
                    cu.firstName,
                    cu.lastName,
                    cu.email as clientEmail,
                    ics.symbolName,
                    ics.symbolDescription,
                    sec.securityName,
                    sec.securityDescription
                FROM {$this->table} icc
                LEFT JOIN orders o ON icc.orderId = o.id
                LEFT JOIN clientUsers cu ON icc.clientId = cu.id
                LEFT JOIN ibCustomSymbols ics ON icc.symbolId = ics.id
                LEFT JOIN ibCustomSecurities sec ON ics.securityId = sec.id
                {$whereClause}
                ORDER BY icc.calculatedAt DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as count
                     FROM {$this->table}
                     {$whereClause}";

        $items = $this->db->fetchAll($sql, $params);
        $total = $this->db->fetchOne($countSql, $params)['count'];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * 获取所有IB代理的佣金报表（汇总）
     */
    public function getAllIbCommissionReport($page = 1, $perPage = 10, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $params = [];

        $whereClause = "WHERE 1=1";
        if (isset($filters['startDate'])) {
            $whereClause .= " AND (icc.periodStart >= :startDate OR icc.calculatedAt >= :startDate)";
            $params['startDate'] = $filters['startDate'];
        }
        if (isset($filters['endDate'])) {
            $whereClause .= " AND (icc.periodEnd <= :endDate OR icc.calculatedAt <= :endDate)";
            $params['endDate'] = $filters['endDate'];
        }
        if (isset($filters['status']) && !empty($filters['status'])) {
            // 将前端状态值映射到后端的calculationStatus
            // 前端状态：paid, pending, processing
            // 后端状态：withdrawn, calculated, cancelled
            $statusMap = [
                'paid' => 'withdrawn',
                'pending' => 'calculated',
                'processing' => 'calculated' // processing状态需要特殊处理
            ];

            $calculationStatus = $statusMap[$filters['status']] ?? $filters['status'];

            if ($filters['status'] === 'processing') {
                // processing状态：既有paidCommission又有pendingCommission
                // 需要查询既有withdrawn又有calculated状态的IB
                $whereClause .= " AND icc.ibPartnerId IN (
                    SELECT DISTINCT ibPartnerId
                    FROM {$this->table}
                    WHERE calculationStatus = 'withdrawn'
                ) AND icc.ibPartnerId IN (
                    SELECT DISTINCT ibPartnerId
                    FROM {$this->table}
                    WHERE calculationStatus = 'calculated'
                )";
            } else {
                $whereClause .= " AND icc.calculationStatus = :status";
                $params['status'] = $calculationStatus;
            }
        }

        $this->appendIbReportSearchFilter($whereClause, $params, $filters['search'] ?? '');

        // 排序
        $orderBy = "ORDER BY totalCommission DESC";
        if (isset($filters['sort'])) {
            switch ($filters['sort']) {
                case 'commission_asc':
                    $orderBy = "ORDER BY totalCommission ASC";
                    break;
                case 'commission_desc':
                    $orderBy = "ORDER BY totalCommission DESC";
                    break;
                case 'name_asc':
                    $orderBy = "ORDER BY displayName ASC";
                    break;
                case 'name_desc':
                    $orderBy = "ORDER BY displayName DESC";
                    break;
                default:
                    $orderBy = "ORDER BY totalCommission DESC";
            }
        }

        // 使用PHP递归方法统计所有IB的客户数量（包括多层级）
        $clientCountsMap = $this->getAllIbClientsCountRecursive();

        $sql = "SELECT
                    icc.ibPartnerId,
                    ib.ibCode,
                    ib.companyName,
                    ib.contactEmail,
                    ib.country,
                    MAX(COALESCE(TRIM(CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,''))), cu.email, ib.companyName)) AS displayName,
                    COUNT(icc.id) as totalOrders,
                    SUM(icc.volume) as tradingVolume,
                    SUM(icc.finalCommission) as totalCommission,
                    SUM(CASE WHEN icc.calculationStatus = 'withdrawn' THEN icc.finalCommission ELSE 0 END) as paidCommission,
                    SUM(CASE WHEN icc.calculationStatus = 'calculated' THEN icc.finalCommission ELSE 0 END) as pendingCommission,
                    MAX(icc.calculatedAt) as lastCalculationDate,
                    MAX(CASE WHEN icc.calculationStatus = 'withdrawn' THEN icc.withdrawnAt ELSE NULL END) as lastPayoutDate
                FROM {$this->table} icc
                INNER JOIN ibPartners ib ON icc.ibPartnerId = ib.id
                LEFT JOIN clientUsers cu ON cu.id = ib.userId
                {$whereClause}
                GROUP BY icc.ibPartnerId, ib.ibCode, ib.companyName, ib.contactEmail, ib.country
                {$orderBy}
                LIMIT {$perPage} OFFSET {$offset}";

        $items = $this->db->fetchAll($sql, $params);

        // 为每个IB添加客户数量
        foreach ($items as &$item) {
            $ibPartnerId = isset($item['ibPartnerId']) ? $item['ibPartnerId'] : null;
            if ($ibPartnerId !== null) {
                $item['clientsReferred'] = isset($clientCountsMap[$ibPartnerId]) ? (int)$clientCountsMap[$ibPartnerId] : 0;
            } else {
                $item['clientsReferred'] = 0;
            }
        }
        unset($item);

        $countSql = "SELECT COUNT(DISTINCT icc.ibPartnerId) as count
                     FROM {$this->table} icc
                     INNER JOIN ibPartners ib ON icc.ibPartnerId = ib.id
                     LEFT JOIN clientUsers cu ON cu.id = ib.userId
                     {$whereClause}";

        $total = $this->db->fetchOne($countSql, $params)['count'];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * 获取总体统计信息
     */
    public function getOverallStatistics($filters = []) {
        $params = [];
        $whereClause = "WHERE 1=1";

        if (isset($filters['startDate'])) {
            $whereClause .= " AND (icc.periodStart >= :startDate OR icc.calculatedAt >= :startDate)";
            $params['startDate'] = $filters['startDate'];
        }
        if (isset($filters['endDate'])) {
            $whereClause .= " AND (icc.periodEnd <= :endDate OR icc.calculatedAt <= :endDate)";
            $params['endDate'] = $filters['endDate'];
        }
        if (isset($filters['status']) && !empty($filters['status'])) {
            // 将前端状态值映射到后端的calculationStatus
            $statusMap = [
                'paid' => 'withdrawn',
                'pending' => 'calculated',
                'processing' => 'calculated'
            ];

            $calculationStatus = $statusMap[$filters['status']] ?? $filters['status'];

            if ($filters['status'] === 'processing') {
                // processing状态：既有paidCommission又有pendingCommission
                $whereClause .= " AND icc.ibPartnerId IN (
                    SELECT DISTINCT ibPartnerId
                    FROM {$this->table}
                    WHERE calculationStatus = 'withdrawn'
                ) AND icc.ibPartnerId IN (
                    SELECT DISTINCT ibPartnerId
                    FROM {$this->table}
                    WHERE calculationStatus = 'calculated'
                )";
            } else {
                $whereClause .= " AND icc.calculationStatus = :status";
                $params['status'] = $calculationStatus;
            }
        }

        $this->appendIbReportSearchFilter($whereClause, $params, $filters['search'] ?? '');

        $sql = "SELECT
                    COUNT(DISTINCT icc.ibPartnerId) as activeIbs,
                    SUM(icc.finalCommission) as totalCommission,
                    SUM(CASE WHEN icc.calculationStatus = 'withdrawn' THEN icc.finalCommission ELSE 0 END) as paidCommission,
                    SUM(CASE WHEN icc.calculationStatus = 'calculated' THEN icc.finalCommission ELSE 0 END) as pendingCommission,
                    COUNT(*) as totalCalculations
                FROM {$this->table} icc
                INNER JOIN ibPartners ib ON icc.ibPartnerId = ib.id
                LEFT JOIN clientUsers cu ON cu.id = ib.userId
                {$whereClause}";

        return $this->db->fetchOne($sql, $params);
    }

    private function appendIbReportSearchFilter(string &$whereClause, array &$params, $searchRaw): void
    {
        $search = trim((string)$searchRaw);
        if ($search === '') {
            return;
        }

        $whereClause .= " AND (
            LOWER(cu.firstName) LIKE :search
            OR LOWER(cu.lastName) LIKE :search
            OR LOWER(TRIM(CONCAT(IFNULL(cu.firstName, ''), ' ', IFNULL(cu.lastName, '')))) LIKE :search
            OR LOWER(ib.ibCode) LIKE :search
            OR LOWER(ib.contactEmail) LIKE :search
        )";
        $params['search'] = '%' . mb_strtolower($search, 'UTF-8') . '%';
    }

    /**
     * 获取IB代理的总佣金
     */
    public function getTotalCommissionByIbPartner($ibPartnerId, $startDate = null, $endDate = null) {
        $params = ['ibPartnerId' => $ibPartnerId];
        $whereClause = "WHERE ibPartnerId = :ibPartnerId AND calculationStatus != 'cancelled'";

        if ($startDate) {
            $whereClause .= " AND (periodStart >= :startDate OR calculatedAt >= :startDate)";
            $params['startDate'] = $startDate;
        }

        if ($endDate) {
            $whereClause .= " AND (periodEnd <= :endDate OR calculatedAt <= :endDate)";
            $params['endDate'] = $endDate;
        }

        $sql = "SELECT SUM(finalCommission) as total
                FROM {$this->table}
                {$whereClause}";

        $result = $this->db->fetchOne($sql, $params);
        return (float)($result['total'] ?? 0);
    }

    /**
     * 获取客户的总佣金（针对特定IB）
     */
    public function getTotalCommissionByClient($clientId, $ibPartnerId) {
        $sql = "SELECT SUM(finalCommission) as total
                FROM {$this->table}
                WHERE clientId = :clientId
                AND ibPartnerId = :ibPartnerId
                AND calculationStatus != 'cancelled'";

        $result = $this->db->fetchOne($sql, [
            'clientId' => $clientId,
            'ibPartnerId' => $ibPartnerId
        ]);

        return (float)($result['total'] ?? 0);
    }

    /**
     * 获取活跃客户数（最近有交易的客户）
     */
    public function getActiveClientsCount($ibPartnerId, $sinceDate) {
        $sql = "SELECT COUNT(DISTINCT clientId) as count
                FROM {$this->table}
                WHERE ibPartnerId = :ibPartnerId
                AND calculatedAt >= :sinceDate
                AND calculationStatus != 'cancelled'";

        $result = $this->db->fetchOne($sql, [
            'ibPartnerId' => $ibPartnerId,
            'sinceDate' => $sinceDate
        ]);

        return (int)($result['count'] ?? 0);
    }

    /**
     * 使用PHP递归方法统计所有IB的客户数量（包括多层级）
     * @return array [ibPartnerId => clientsCount]
     */
    private function getAllIbClientsCountRecursive() {
        $clientCountsMap = [];

        try {
            // 1. 查询所有IB的层级关系（parent-child）
            $hierarchySql = "
                SELECT
                    ibPartnerId,
                    parentIbPartnerId
                FROM ibNetworkHierarchy
                WHERE parentIbPartnerId IS NOT NULL
            ";
            $hierarchyData = $this->db->fetchAll($hierarchySql, []);

            // 构建父子关系映射：parentId => [childIds]
            $childrenMap = [];
            $allIbIds = [];
            foreach ($hierarchyData as $row) {
                $parentId = (int)$row['parentIbPartnerId'];
                $childId = (int)$row['ibPartnerId'];
                if (!isset($childrenMap[$parentId])) {
                    $childrenMap[$parentId] = [];
                }
                $childrenMap[$parentId][] = $childId;
                $allIbIds[$parentId] = true;
                $allIbIds[$childId] = true;
            }

            // 2. 查询每个IB的直接客户数量
            $directClientsSql = "
                SELECT
                    parentId AS ibPartnerId,
                    COUNT(DISTINCT childClientId) as clientsCount
                FROM ib_partner_bind
                WHERE isClient = 1
                GROUP BY parentId
            ";
            $directClientsData = $this->db->fetchAll($directClientsSql, []);

            // 构建直接客户数量映射，同时收集所有有客户的IB
            $directClientsMap = [];
            foreach ($directClientsData as $row) {
                $ibId = (int)$row['ibPartnerId'];
                $directClientsMap[$ibId] = (int)$row['clientsCount'];
                $allIbIds[$ibId] = true; // 确保有客户的IB也被包含
            }

            // 3. 为每个IB递归统计所有下级IB的客户数量
            $maxDepth = IbProgramSetting::resolveNetworkMaxDepth();

            foreach (array_keys($allIbIds) as $ibPartnerId) {
                if (!isset($clientCountsMap[$ibPartnerId])) {
                    $path = []; // 递归路径（用于检测循环引用）
                    $clientCountsMap[$ibPartnerId] = $this->countClientsRecursive(
                        $ibPartnerId,
                        $childrenMap,
                        $directClientsMap,
                        $path,
                        $maxDepth,
                        0
                    );
                }
            }

        } catch (Exception $e) {
            Logger::error("Error in getAllIbClientsCountRecursive: " . $e->getMessage());
            // 如果出错，回退到只统计直接客户
            $fallbackSql = "
                SELECT
                    parentId AS ibPartnerId,
                    COUNT(DISTINCT childClientId) as clientsReferred
                FROM ib_partner_bind
                WHERE isClient = 1
                GROUP BY parentId
            ";
            $fallbackData = $this->db->fetchAll($fallbackSql, []);
            foreach ($fallbackData as $row) {
                $clientCountsMap[(int)$row['ibPartnerId']] = (int)$row['clientsReferred'];
            }
        }

        return $clientCountsMap;
    }

    /**
     * 递归统计指定IB及其所有下级IB的客户数量
     * @param int $ibPartnerId 当前IB ID
     * @param array $childrenMap 父子关系映射 [parentId => [childIds]]
     * @param array $directClientsMap 直接客户数量映射 [ibPartnerId => count]
     * @param array $path 当前递归路径（用于检测循环引用）
     * @param int $maxDepth 最大递归深度
     * @param int $currentDepth 当前递归深度
     * @return int 客户总数
     */
    private function countClientsRecursive($ibPartnerId, &$childrenMap, &$directClientsMap, &$path, $maxDepth, $currentDepth) {
        // 防止超过最大深度
        if ($currentDepth >= $maxDepth) {
            Logger::error("Maximum depth ({$maxDepth}) reached for IB Partner ID: {$ibPartnerId}");
            return 0;
        }

        // 检测循环引用：如果当前节点已在路径中，说明存在循环
        if (in_array($ibPartnerId, $path)) {
            Logger::error("Circular reference detected for IB Partner ID: {$ibPartnerId}. Path: " . implode(' -> ', $path) . " -> {$ibPartnerId}");
            return 0;
        }

        // 将当前节点添加到路径中
        $path[] = $ibPartnerId;

        // 当前IB的直接客户数量
        $count = isset($directClientsMap[$ibPartnerId]) ? $directClientsMap[$ibPartnerId] : 0;

        // 递归统计所有下级IB的客户数量
        if (isset($childrenMap[$ibPartnerId]) && is_array($childrenMap[$ibPartnerId])) {
            foreach ($childrenMap[$ibPartnerId] as $childId) {
                $count += $this->countClientsRecursive(
                    $childId,
                    $childrenMap,
                    $directClientsMap,
                    $path,
                    $maxDepth,
                    $currentDepth + 1
                );
            }
        }

        // 从路径中移除当前节点（回溯）
        array_pop($path);

        return $count;
    }
}
