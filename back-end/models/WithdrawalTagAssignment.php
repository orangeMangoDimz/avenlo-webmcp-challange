<?php
/**
 * Withdrawal Tag Assignment Model
 * 对应表: withdrawalTagAssignments
 */

require_once __DIR__ . '/BaseModel.php';

class WithdrawalTagAssignment extends BaseModel {
    protected $table = 'withdrawalTagAssignments';
    protected $primaryKey = 'id';
    protected $fillable = [
        'withdrawalId',
        'tagId',
        'assignedBy',
        'assignedAt'
    ];

    /**
     * 获取提款的所有标签
     */
    public function getWithdrawalTags($withdrawalId) {
        $sql = "SELECT wt.*
                FROM withdrawalTags wt
                INNER JOIN {$this->table} wta ON wt.id = wta.tagId
                WHERE wta.withdrawalId = :withdrawalId
                ORDER BY wt.tagName ASC";

        return $this->query($sql, ['withdrawalId' => $withdrawalId]);
    }

    /**
     * 分配标签到提款
     */
    public function assignTag($withdrawalId, $tagId, $assignedBy = null) {
        // 检查是否已存在
        $existing = $this->findOne([
            'withdrawalId' => $withdrawalId,
            'tagId' => $tagId
        ]);

        if ($existing) {
            return $existing['id'];
        }

        return $this->create([
            'withdrawalId' => $withdrawalId,
            'tagId' => $tagId,
            'assignedBy' => $assignedBy
        ]);
    }

    /**
     * 移除提款的标签
     */
    public function removeTag($withdrawalId, $tagId) {
        $sql = "DELETE FROM {$this->table}
                WHERE withdrawalId = :withdrawalId AND tagId = :tagId";

        return $this->db->query($sql, [
            'withdrawalId' => $withdrawalId,
            'tagId' => $tagId
        ]);
    }

    /**
     * 批量分配标签
     */
    public function bulkAssignTag($withdrawalIds, $tagId, $assignedBy = null) {
        $results = [];

        foreach ($withdrawalIds as $withdrawalId) {
            $results[$withdrawalId] = $this->assignTag($withdrawalId, $tagId, $assignedBy);
        }

        return $results;
    }
}
