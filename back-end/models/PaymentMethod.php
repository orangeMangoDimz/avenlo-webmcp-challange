<?php
/**
 * Payment Method Model
 * 对应表: paymentMethods
 */

require_once __DIR__ . '/BaseModel.php';

class PaymentMethod extends BaseModel {
    protected $table = 'paymentMethods';
    protected $primaryKey = 'id';
    protected $fillable = [
        'methodKey',
        'methodName',
        'methodType',
        'iconClass',
        'shortCode',
        'networkName',
        'isDepositEnabled',
        'isWithdrawalEnabled',
        'processingTime',
        'displayOrder',
        'country',
        'minPurchaseAmount',
        'maxPurchaseAmount'
    ];

    /**
     * 获取所有启用的支付方式
     */
    public function getEnabledMethods($type = null) {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        if ($type === 'deposit') {
            $sql .= " AND isDepositEnabled = 1";
        } elseif ($type === 'withdrawal') {
            $sql .= " AND isWithdrawalEnabled = 1";
        }

        $sql .= " ORDER BY displayOrder ASC";

        return $this->query($sql, $params);
    }

    /**
     * 根据methodKey查找
     */
    public function findByKey($methodKey) {
        return $this->findOne(['methodKey' => $methodKey]);
    }

    /**
     * 获取加密货币支付方式
     */
    public function getCryptoMethods() {
        return $this->findAll(['methodType' => 'crypto'], 'displayOrder ASC');
    }

    /**
     * 获取法币支付方式
     */
    public function getFiatMethods() {
        return $this->findAll(['methodType' => 'fiat'], 'displayOrder ASC');
    }
}
