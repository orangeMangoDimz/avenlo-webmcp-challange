<?php
/**
 * Withdrawal Rejection Reason Model
 * 对应表: withdrawalRejectionReasons
 */

require_once __DIR__ . '/BaseModel.php';

class WithdrawalRejectionReason extends BaseModel {
    protected $table = 'withdrawalRejectionReasons';
    protected $primaryKey = 'id';
    protected $fillable = [
        'reasonKey',
        'reasonTitle',
        'reasonDescription',
        'isActive',
        'displayOrder'
    ];

    /**
     * 获取所有活跃的拒绝原因
     */
    public function getActiveReasons() {
        return $this->findAll(['isActive' => 1], 'displayOrder ASC');
    }

    /**
     * 根据reasonKey查找
     */
    public function findByKey($reasonKey) {
        return $this->findOne(['reasonKey' => $reasonKey]);
    }
}
