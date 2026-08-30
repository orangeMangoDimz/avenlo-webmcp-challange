<?php
/**
 * IB佣金提现记录模型
 * 对应表：ibCommissionWithdrawals
 */

require_once __DIR__ . '/BaseModel.php';

class IbCommissionWithdrawal extends BaseModel {
    protected $table = 'ibCommissionWithdrawals';
    protected $primaryKey = 'id';

    protected $fillable = [
        'ibPartnerId', 'transactionId', 'totalAmount', 'paymentCycle',
        'periodStart', 'periodEnd', 'calculationCount', 'depositId',
        'status', 'requestedAt', 'processedAt', 'processedBy',
        'failureReason', 'adminNotes'
    ];

    /**
     * 生成唯一的交易ID
     */
    public function generateTransactionId() {
        $date = date('Ymd');
        $random = str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);
        return "COMM-{$date}-{$random}";
    }
}
