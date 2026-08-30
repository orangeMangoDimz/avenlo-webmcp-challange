<?php
/**
 * Admin Balance Adjustment Model
 * 对应表: admin_balance_adjustments
 *
 * 一行 = 一次 admin 手工对用户 wallet 的打钱(credit)或扣钱(debit) 操作。
 * 同步关联到一条新建的 deposit (credit) 或 withdrawal (debit) 订单，
 * 真正的余额变化由 deposit / withdrawal 走自己的审批流后由 WalletBalanceService 自然反映。
 */

require_once __DIR__ . '/BaseModel.php';

class AdminBalanceAdjustment extends BaseModel {
    protected $table = 'admin_balance_adjustments';
    protected $primaryKey = 'id';
    protected $fillable = [
        'userId',
        'direction',
        'amount',
        'currencyCode',
        'depositId',
        'withdrawalId',
        'reason',
        'operatedBy',
    ];

    const DIRECTION_CREDIT = 'credit';
    const DIRECTION_DEBIT  = 'debit';
}
