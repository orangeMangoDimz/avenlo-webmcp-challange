<?php
/**
 * Client Saved Wallet Model
 * 对应表: clientSavedWallets
 */

require_once __DIR__ . '/BaseModel.php';

class ClientSavedWallet extends BaseModel {
    protected $table = 'clientSavedWallets';
    protected $primaryKey = 'id';
    protected $fillable = [
        'userId',
        'walletName',
        'paymentMethodId',
        'walletAddress',
        'networkType',
        'isVerified',
        'verifiedAt',
        'verificationMethod',
        'isDefault',
        'lastUsedAt',
        'usageCount'
    ];

    /**
     * 获取用户的所有钱包
     */
    public function getUserWallets($userId) {
        $sql = "SELECT csw.*, pm.methodName, pm.methodType, pm.shortCode, pm.iconClass
                FROM {$this->table} csw
                INNER JOIN paymentMethods pm ON csw.paymentMethodId = pm.id
                WHERE csw.userId = :userId
                ORDER BY csw.isDefault DESC, csw.lastUsedAt DESC, csw.createdAt DESC";

        return $this->query($sql, ['userId' => $userId]);
    }

    /**
     * 获取用户指定支付方式的钱包
     */
    public function getUserWalletsByMethod($userId, $paymentMethodId) {
        $sql = "SELECT csw.*, pm.methodName, pm.shortCode
                FROM {$this->table} csw
                INNER JOIN paymentMethods pm ON csw.paymentMethodId = pm.id
                WHERE csw.userId = :userId AND csw.paymentMethodId = :paymentMethodId
                ORDER BY csw.isDefault DESC, csw.lastUsedAt DESC";

        return $this->query($sql, [
            'userId' => $userId,
            'paymentMethodId' => $paymentMethodId
        ]);
    }

    /**
     * 设置为默认钱包
     */
    public function setAsDefault($walletId, $userId) {
        // 获取钱包信息
        $wallet = $this->findById($walletId);

        if (!$wallet || $wallet['userId'] != $userId) {
            return false;
        }

        // 先取消该用户该支付方式的所有默认钱包
        $sql = "UPDATE {$this->table}
                SET isDefault = 0
                WHERE userId = :userId AND paymentMethodId = :paymentMethodId";

        $this->db->execute($sql, [
            'userId' => $userId,
            'paymentMethodId' => $wallet['paymentMethodId']
        ]);

        // 设置当前钱包为默认
        return $this->update($walletId, ['isDefault' => 1]);
    }

    /**
     * 更新使用统计
     */
    public function updateUsageStats($walletId) {
        $sql = "UPDATE {$this->table}
                SET lastUsedAt = NOW(), usageCount = usageCount + 1
                WHERE id = :walletId";

        return $this->db->execute($sql, ['walletId' => $walletId]);
    }
}
