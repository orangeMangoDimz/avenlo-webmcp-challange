<?php
/**
 * Withdrawal Status History Model
 * 对应表: withdrawalStatusHistory
 */

require_once __DIR__ . '/BaseModel.php';

class WithdrawalStatusHistory extends BaseModel {
    protected $table = 'withdrawalStatusHistory';
    protected $primaryKey = 'id';
    protected $fillable = [
        'withdrawalId',
        'previousStatus',
        'newStatus',
        'description',
        'changedBy',
        'metadata'
    ];

    /**
     * 获取提款的状态历史
     */
    public function getWithdrawalHistory($withdrawalId) {
        $sql = "SELECT wsh.*, au.fullName as changedByName
                FROM {$this->table} wsh
                LEFT JOIN adminUsers au ON wsh.changedBy = au.id
                WHERE wsh.withdrawalId = :withdrawalId
                ORDER BY wsh.createdAt ASC";

        return $this->query($sql, ['withdrawalId' => $withdrawalId]);
    }

    /**
     * 记录状态变更
     */
    public function logStatusChange($withdrawalId, $previousStatus, $newStatus, $description = null, $changedBy = null, $metadata = null) {
        return $this->create([
            'withdrawalId' => $withdrawalId,
            'previousStatus' => $previousStatus,
            'newStatus' => $newStatus,
            'description' => $description,
            'changedBy' => $changedBy,
            'metadata' => $metadata ? json_encode($metadata) : null
        ]);
    }
}
