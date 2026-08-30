<?php
/**
 * Lead 编辑历史模型
 */

require_once __DIR__ . '/BaseModel.php';

class LeadEditHistory extends BaseModel {
    protected $table = 'leadEditHistory';
    protected $primaryKey = 'id';

    protected $fillable = [
        'leadId', 'fieldName', 'oldValue', 'newValue',
        'editedBy', 'ipAddress'
    ];

    /**
     * 记录编辑历史
     */
    public function logEdit($leadId, $fieldName, $oldValue, $newValue, $editedBy, $ipAddress = null) {
        return $this->create([
            'leadId' => $leadId,
            'fieldName' => $fieldName,
            'oldValue' => $oldValue,
            'newValue' => $newValue,
            'editedBy' => $editedBy,
            'ipAddress' => $ipAddress
        ]);
    }

    /**
     * 批量记录编辑历史
     */
    public function logBulkEdit($leadId, $changes, $editedBy, $ipAddress = null) {
        foreach ($changes as $fieldName => $values) {
            $this->logEdit(
                $leadId,
                $fieldName,
                $values['old'] ?? null,
                $values['new'] ?? null,
                $editedBy,
                $ipAddress
            );
        }
        return true;
    }

    /**
     * 获取Lead的编辑历史
     */
    public function getLeadEditHistory($leadId, $limit = 50) {
        $sql = "SELECT leh.*, au.fullName as editedByName
                FROM {$this->table} leh
                LEFT JOIN adminUsers au ON leh.editedBy = au.id
                WHERE leh.leadId = :leadId
                ORDER BY leh.createdAt DESC
                LIMIT {$limit}";

        return $this->db->fetchAll($sql, ['leadId' => $leadId]);
    }

    /**
     * 获取特定字段的编辑历史
     */
    public function getFieldHistory($leadId, $fieldName) {
        $sql = "SELECT leh.*, au.fullName as editedByName
                FROM {$this->table} leh
                LEFT JOIN adminUsers au ON leh.editedBy = au.id
                WHERE leh.leadId = :leadId AND leh.fieldName = :fieldName
                ORDER BY leh.createdAt DESC";

        return $this->db->fetchAll($sql, [
            'leadId' => $leadId,
            'fieldName' => $fieldName
        ]);
    }

    /**
     * 获取最近的编辑
     */
    public function getRecentEdits($limit = 20) {
        $sql = "SELECT leh.*,
                       cu.firstName, cu.lastName, cu.email,
                       au.fullName as editedByName
                FROM {$this->table} leh
                INNER JOIN clientUsers cu ON leh.leadId = cu.id
                LEFT JOIN adminUsers au ON leh.editedBy = au.id
                ORDER BY leh.createdAt DESC
                LIMIT {$limit}";

        return $this->db->fetchAll($sql);
    }

    /**
     * 获取特定管理员的编辑记录
     */
    public function getEditsByAdmin($adminId, $page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT leh.*, cu.firstName, cu.lastName, cu.email
                FROM {$this->table} leh
                INNER JOIN clientUsers cu ON leh.leadId = cu.id
                WHERE leh.editedBy = :adminId
                ORDER BY leh.createdAt DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as count
                     FROM {$this->table}
                     WHERE editedBy = :adminId";

        $params = ['adminId' => $adminId];

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
     * 获取编辑统计
     */
    public function getEditStats($startDate = null, $endDate = null) {
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
                    fieldName,
                    COUNT(*) as editCount,
                    COUNT(DISTINCT leadId) as affectedLeads,
                    COUNT(DISTINCT editedBy) as editorsCount
                FROM {$this->table}
                {$whereClause}
                GROUP BY fieldName
                ORDER BY editCount DESC";

        return $this->db->fetchAll($sql, $params);
    }
}
