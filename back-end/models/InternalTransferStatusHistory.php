<?php
/**
 * Internal Transfer Status History Model
 * 对应表: internalTransferStatusHistory
 */

require_once __DIR__ . '/BaseModel.php';

class InternalTransferStatusHistory extends BaseModel {
    protected $table = 'internalTransferStatusHistory';
    protected $primaryKey = 'id';
    protected $fillable = [
        'internalTransferId',
        'previousStatus',
        'newStatus',
        'description',
        'changedBy',
        'metadata'
    ];

    /**
     * 获取内部转账的状态历史
     */
    public function getInternalTransferHistory($internalTransferId) {
        $sql = "SELECT itsh.*, au.fullName as changedByName
                FROM {$this->table} itsh
                LEFT JOIN adminUsers au ON itsh.changedBy = au.id
                WHERE itsh.internalTransferId = :internalTransferId
                ORDER BY itsh.createdAt ASC";

        return $this->query($sql, ['internalTransferId' => $internalTransferId]);
    }

    /**
     * 记录状态变更
     */
    public function logStatusChange($internalTransferId, $previousStatus, $newStatus, $description = null, $changedBy = null, $metadata = null) {
        return $this->create([
            'internalTransferId' => $internalTransferId,
            'previousStatus' => $previousStatus,
            'newStatus' => $newStatus,
            'description' => $description,
            'changedBy' => $changedBy,
            'metadata' => $metadata ? json_encode($metadata) : null
        ]);
    }
}
