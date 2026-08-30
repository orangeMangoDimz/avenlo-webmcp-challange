<?php
/**
 * Crypto Deposit Address Model
 * 对应表: cryptoDepositAddresses
 */

require_once __DIR__ . '/BaseModel.php';

class CryptoDepositAddress extends BaseModel {
    protected $table = 'cryptoDepositAddresses';
    protected $primaryKey = 'id';
    protected $fillable = [
        'paymentMethodId',
        'walletAddress',
        'networkType',
        'qrCodePath',
        'minimumDeposit',
        'minimumDepositUsd',
        'confirmationBlocks',
        'isActive',
        'updatedBy'
    ];

    /**
     * 获取所有活跃的加密货币地址（包含支付方式信息）
     */
    public function getActiveAddresses() {
        $sql = "SELECT cda.*, pm.methodKey, pm.methodName, pm.shortCode, pm.iconClass
                FROM {$this->table} cda
                INNER JOIN paymentMethods pm ON cda.paymentMethodId = pm.id
                WHERE cda.isActive = 1
                ORDER BY pm.displayOrder ASC";

        return $this->query($sql);
    }

    /**
     * 根据支付方式获取地址
     */
    public function getByPaymentMethod($paymentMethodId) {
        return $this->findOne([
            'paymentMethodId' => $paymentMethodId,
            'isActive' => 1
        ]);
    }

    /**
     * 更新或创建地址
     */
    public function upsert($paymentMethodId, $data) {
        $existing = $this->findOne(['paymentMethodId' => $paymentMethodId]);

        if ($existing) {
            return $this->update($existing['id'], $data);
        } else {
            $data['paymentMethodId'] = $paymentMethodId;
            return $this->create($data);
        }
    }
}
