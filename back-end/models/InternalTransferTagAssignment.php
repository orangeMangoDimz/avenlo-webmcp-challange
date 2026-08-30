<?php
/**
 * Internal Transfer Tag Assignment Model
 * 对应表: internalTransferTagAssignments
 */

require_once __DIR__ . '/BaseModel.php';

class InternalTransferTagAssignment extends BaseModel {
    protected $table = 'internalTransferTagAssignments';
    protected $primaryKey = 'id';
    protected $fillable = [
        'internalTransferId',
        'tagId',
        'createdBy'
    ];

    /**
     * 获取内部转账的所有标签
     */
    public function getInternalTransferTags($internalTransferId) {
        $sql = "SELECT dt.*
                FROM depositTags dt
                INNER JOIN {$this->table} itta ON dt.id = itta.tagId
                WHERE itta.internalTransferId = :internalTransferId
                ORDER BY dt.tagName ASC";

        return $this->query($sql, ['internalTransferId' => $internalTransferId]);
    }

    /**
     * 分配标签到内部转账
     */
    public function assignTag($internalTransferId, $tagId, $createdBy = null) {
        // 检查是否已存在
        $existing = $this->findOne([
            'internalTransferId' => $internalTransferId,
            'tagId' => $tagId
        ]);

        if ($existing) {
            return $existing['id'];
        }

        return $this->create([
            'internalTransferId' => $internalTransferId,
            'tagId' => $tagId,
            'createdBy' => $createdBy
        ]);
    }

    /**
     * 移除内部转账的标签
     */
    public function removeTag($internalTransferId, $tagId) {
        $sql = "DELETE FROM {$this->table}
                WHERE internalTransferId = :internalTransferId AND tagId = :tagId";

        return $this->db->query($sql, [
            'internalTransferId' => $internalTransferId,
            'tagId' => $tagId
        ]);
    }

    /**
     * 批量分配标签
     */
    public function bulkAssignTag($internalTransferIds, $tagId, $createdBy = null) {
        $results = [];

        foreach ($internalTransferIds as $internalTransferId) {
            $results[$internalTransferId] = $this->assignTag($internalTransferId, $tagId, $createdBy);
        }

        return $results;
    }
}
