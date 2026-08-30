<?php
/**
 * Deposit Tag Assignment Model
 * 对应表: depositTagAssignments
 */

require_once __DIR__ . '/BaseModel.php';

class DepositTagAssignment extends BaseModel {
    protected $table = 'depositTagAssignments';
    protected $primaryKey = 'id';
    protected $fillable = [
        'depositId',
        'tagId',
        'assignedBy',
        'assignedAt'
    ];

    /**
     * 获取存款的所有标签
     */
    public function getDepositTags($depositId) {
        $sql = "SELECT dt.*
                FROM depositTags dt
                INNER JOIN {$this->table} dta ON dt.id = dta.tagId
                WHERE dta.depositId = :depositId
                ORDER BY dt.tagName ASC";

        return $this->query($sql, ['depositId' => $depositId]);
    }

    /**
     * 分配标签到存款
     */
    public function assignTag($depositId, $tagId, $assignedBy = null) {
        // 检查是否已存在
        $existing = $this->findOne([
            'depositId' => $depositId,
            'tagId' => $tagId
        ]);

        if ($existing) {
            return $existing['id'];
        }

        return $this->create([
            'depositId' => $depositId,
            'tagId' => $tagId,
            'assignedBy' => $assignedBy
        ]);
    }

    /**
     * 移除存款的标签
     */
    public function removeTag($depositId, $tagId) {
        $sql = "DELETE FROM {$this->table}
                WHERE depositId = :depositId AND tagId = :tagId";

        return $this->db->query($sql, [
            'depositId' => $depositId,
            'tagId' => $tagId
        ]);
    }

    /**
     * 批量分配标签
     */
    public function bulkAssignTag($depositIds, $tagId, $assignedBy = null) {
        $results = [];

        foreach ($depositIds as $depositId) {
            $results[$depositId] = $this->assignTag($depositId, $tagId, $assignedBy);
        }

        return $results;
    }
}
