<?php
/**
 * Lead 批量操作模型
 */

require_once __DIR__ . '/BaseModel.php';

class LeadBulkOperation extends BaseModel {
    protected $table = 'leadBulkOperations';
    protected $primaryKey = 'id';

    protected $fillable = [
        'operationType', 'leadIds', 'totalLeads', 'operationData',
        'performedBy', 'ipAddress'
    ];

    /**
     * 记录批量操作
     */
    public function logBulkOperation($operationType, $leadIds, $operationData = null, $performedBy = null, $ipAddress = null) {
        return $this->create([
            'operationType' => $operationType,
            'leadIds' => is_array($leadIds) ? json_encode($leadIds) : $leadIds,
            'totalLeads' => is_array($leadIds) ? count($leadIds) : 0,
            'operationData' => is_array($operationData) ? json_encode($operationData) : $operationData,
            'performedBy' => $performedBy,
            'ipAddress' => $ipAddress
        ]);
    }

    /**
     * 获取批量操作历史
     */
    public function getOperationHistory($page = 1, $perPage = 20, $operationType = null) {
        $offset = ($page - 1) * $perPage;
        $conditions = [];
        $params = [];

        if ($operationType) {
            $conditions[] = "lbo.operationType = :operationType";
            $params['operationType'] = $operationType;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT lbo.*, au.fullName as performedByName
                FROM {$this->table} lbo
                LEFT JOIN adminUsers au ON lbo.performedBy = au.id
                {$whereClause}
                ORDER BY lbo.createdAt DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as count
                     FROM {$this->table} lbo
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
     * 获取特定管理员的批量操作
     */
    public function getOperationsByAdmin($adminId, $limit = 20) {
        $sql = "SELECT * FROM {$this->table}
                WHERE performedBy = :adminId
                ORDER BY createdAt DESC
                LIMIT {$limit}";

        return $this->db->fetchAll($sql, ['adminId' => $adminId]);
    }

    /**
     * 获取操作类型统计
     */
    public function getOperationTypeStats($startDate = null, $endDate = null) {
        $conditions = [];
        $params = [];

        if ($startDate) {
            $conditions[] = "createdAt >= :startDate";
            $params['startDate'] = $startDate;
        }

        if ($endDate) {
            $conditions[] = "createdAt <= :endDate";
            $params['endDate'] = $endDate;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT
                    operationType,
                    COUNT(*) as operationCount,
                    SUM(totalLeads) as totalLeadsAffected,
                    COUNT(DISTINCT performedBy) as uniquePerformers
                FROM {$this->table}
                {$whereClause}
                GROUP BY operationType
                ORDER BY operationCount DESC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * 获取最近的批量操作
     */
    public function getRecentOperations($limit = 10) {
        $sql = "SELECT lbo.*, au.fullName as performedByName
                FROM {$this->table} lbo
                LEFT JOIN adminUsers au ON lbo.performedBy = au.id
                ORDER BY lbo.createdAt DESC
                LIMIT {$limit}";

        return $this->db->fetchAll($sql);
    }

    /**
     * 获取操作详情（解析JSON数据）
     */
    public function getOperationDetails($operationId) {
        $operation = $this->findById($operationId);

        if ($operation) {
            // 解析JSON数据
            $operation['leadIds'] = json_decode($operation['leadIds'], true);
            $operation['operationData'] = json_decode($operation['operationData'], true);
        }

        return $operation;
    }
}
