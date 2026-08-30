<?php
/**
 * Lead 活动日志模型
 */

require_once __DIR__ . '/BaseModel.php';

class LeadActivityLog extends BaseModel {
    protected $table = 'leadActivityLog';
    protected $primaryKey = 'id';

    protected $fillable = [
        'leadId', 'activityType', 'description', 'performedBy',
        'metadata', 'ipAddress'
    ];

    /**
     * 记录活动日志
     */
    public function logActivity($leadId, $activityType, $description, $performedBy = null, $metadata = null, $ipAddress = null) {
        return $this->create([
            'leadId' => $leadId,
            'activityType' => $activityType,
            'description' => $description,
            'performedBy' => $performedBy,
            'metadata' => is_array($metadata) ? json_encode($metadata) : $metadata,
            'ipAddress' => $ipAddress
        ]);
    }

    /**
     * 获取Lead的活动日志
     */
    public function getLeadActivities($leadId, $limit = 50) {
        $sql = "SELECT lal.*, au.fullName as performedByName
                FROM {$this->table} lal
                LEFT JOIN adminUsers au ON lal.performedBy = au.id
                WHERE lal.leadId = :leadId
                ORDER BY lal.createdAt DESC
                LIMIT {$limit}";

        return $this->db->fetchAll($sql, ['leadId' => $leadId]);
    }

    /**
     * 根据活动类型获取日志
     */
    public function getByType($activityType, $page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT lal.*, cu.firstName, cu.lastName, cu.email
                FROM {$this->table} lal
                INNER JOIN clientUsers cu ON lal.leadId = cu.id
                WHERE lal.activityType = :activityType
                ORDER BY lal.createdAt DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as count
                     FROM {$this->table}
                     WHERE activityType = :activityType";

        $params = ['activityType' => $activityType];

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
     * 获取最近的活动
     */
    public function getRecentActivities($limit = 20) {
        $sql = "SELECT lal.*,
                       cu.firstName, cu.lastName, cu.email,
                       au.fullName as performedByName
                FROM {$this->table} lal
                INNER JOIN clientUsers cu ON lal.leadId = cu.id
                LEFT JOIN adminUsers au ON lal.performedBy = au.id
                ORDER BY lal.createdAt DESC
                LIMIT {$limit}";

        return $this->db->fetchAll($sql);
    }

    /**
     * 获取活动类型统计
     */
    public function getActivityStats($startDate = null, $endDate = null) {
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

        $sql = "SELECT activityType, COUNT(*) as count
                FROM {$this->table}
                {$whereClause}
                GROUP BY activityType
                ORDER BY count DESC";

        return $this->db->fetchAll($sql, $params);
    }
}
