<?php
/**
 * Transaction Fee Model
 * 对应表: transactionFees
 */

require_once __DIR__ . '/BaseModel.php';

class TransactionFee extends BaseModel {
    protected $table = 'transactionFees';
    protected $primaryKey = 'id';
    protected $fillable = [
        'transactionType',
        'paymentType',
        'feeType',
        'feePercentage',
        'feeFixed',
        'chargeToClient',
        'isActive',
        'updatedBy'
    ];

    /**
     * 获取活跃的费用配置
     */
    public function getActiveFees($transactionType = null, $paymentType = null) {
        $conditions = ['isActive' => 1];

        if ($transactionType) {
            $conditions['transactionType'] = $transactionType;
        }

        if ($paymentType) {
            $conditions['paymentType'] = $paymentType;
        }

        return $this->findAll($conditions);
    }

    /**
     * 获取指定类型的费用配置
     */
    public function getFeeForTransaction($transactionType, $paymentType) {
        return $this->findOne([
            'transactionType' => $transactionType,
            'paymentType' => $paymentType,
            'isActive' => 1
        ]);
    }

    /**
     * 计算交易费用
     */
    public function calculateFee($transactionType, $paymentType, $amount) {
        $feeConfig = $this->getFeeForTransaction($transactionType, $paymentType);

        if (!$feeConfig) {
            return [
                'platformFee' => 0,
                'netAmount' => $amount,
                'feeConfig' => null
            ];
        }

        $platformFee = 0;

        // 计算百分比费用
        if ($feeConfig['feeType'] === 'percentage' || $feeConfig['feeType'] === 'both') {
            $platformFee += ($amount * $feeConfig['feePercentage'] / 100);
        }

        // 添加固定费用
        if ($feeConfig['feeType'] === 'fixed' || $feeConfig['feeType'] === 'both') {
            $platformFee += $feeConfig['feeFixed'];
        }

        // 计算净额
        $netAmount = $amount;
        if ($feeConfig['chargeToClient']) {
            $netAmount = $amount - $platformFee;
        }

        return [
            'platformFee' => round($platformFee, 2),
            'netAmount' => round($netAmount, 2),
            'chargeToClient' => (bool)$feeConfig['chargeToClient'],
            'feeConfig' => $feeConfig
        ];
    }
}
