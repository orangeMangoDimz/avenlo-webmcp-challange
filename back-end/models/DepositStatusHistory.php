<?php
/**
 * Deposit Status History Model
 * 对应表: depositStatusHistory
 */

require_once __DIR__ . '/BaseModel.php';

class DepositStatusHistory extends BaseModel {
    protected $table = 'depositStatusHistory';
    protected $primaryKey = 'id';
    protected $fillable = [
        'depositId',
        'previousStatus',
        'newStatus',
        'description',
        'changedBy',
        'metadata'
    ];

    /**
     * 获取存款的状态历史
     */
    public function getDepositHistory($depositId) {
        $sql = "SELECT dsh.*, au.fullName as changedByName
                FROM {$this->table} dsh
                LEFT JOIN adminUsers au ON dsh.changedBy = au.id
                WHERE dsh.depositId = :depositId
                ORDER BY dsh.createdAt ASC";

        return $this->query($sql, ['depositId' => $depositId]);
    }

    /**
     * 记录状态变更
     */
    public function logStatusChange($depositId, $previousStatus, $newStatus, $description = null, $changedBy = null, $metadata = null) {
        return $this->create([
            'depositId' => $depositId,
            'previousStatus' => $previousStatus,
            'newStatus' => $newStatus,
            'description' => $description,
            'changedBy' => $changedBy,
            'metadata' => $metadata ? json_encode($metadata) : null
        ]);
    }
}
