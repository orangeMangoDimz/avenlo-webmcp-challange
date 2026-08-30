<?php
/**
 * Lead 状态历史模型
 */

require_once __DIR__ . '/BaseModel.php';

class LeadStatusHistory extends BaseModel {
    protected $table = 'leadStatusHistory';
    protected $primaryKey = 'id';

    protected $fillable = [
        'leadId', 'previousStatus', 'newStatus', 'changedBy', 'notes'
    ];

    /**
     * 获取Lead的状态历史
     */
    public function getLeadHistory($leadId) {
        return $this->findAll(
            ['leadId' => $leadId],
            'createdAt DESC'
        );
    }

    /**
     * 记录状态变更
     */
    public function logStatusChange($leadId, $previousStatus, $newStatus, $changedBy, $notes = null) {
        return $this->create([
            'leadId' => $leadId,
            'previousStatus' => $previousStatus,
            'newStatus' => $newStatus,
            'changedBy' => $changedBy,
            'notes' => $notes
        ]);
    }

    /**
     * 获取最近的状态变更
     */
    public function getRecentChanges($limit = 10) {
        $sql = "SELECT lsh.*, cu.firstName, cu.lastName, cu.email
                FROM {$this->table} lsh
                INNER JOIN clientUsers cu ON lsh.leadId = cu.id
                ORDER BY lsh.createdAt DESC
                LIMIT {$limit}";

        return $this->db->fetchAll($sql);
    }
}
