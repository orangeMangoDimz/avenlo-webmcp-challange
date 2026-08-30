<?php
/**
 * Payment Gateway Setting Model
 * 对应表: paymentGatewaySettings
 */

require_once __DIR__ . '/BaseModel.php';

class PaymentGatewaySetting extends BaseModel {
    protected $table = 'paymentGatewaySettings';
    protected $primaryKey = 'id';
    protected $fillable = [
        'gatewayKey',
        'gatewayName',
        'type',
        'iconClass',
        'integration',
        'isMultiCurrency',
        'isEnabled',
        'isDepositEnabled',
        'isWithdrawalEnabled',
        'displayOrder',
        'processingTime',
        'withdrawalProcessingTime',
        'depositContent',
        'withdrawalContent',
        'environment',
        'appId',
        'apiKey',
        'secretKey',
        'merchantName',
        'webhookUrl',
        'returnUrl',
        'supportedFiatCurrencies',
        'supportedCryptoCurrencies',
        'amountDecimalPlaces',
        'configData',
        'updatedBy',
        'deletedAt'
    ];

    protected $hidden = ['apiKey', 'secretKey'];

    const NOT_DELETED_CONDITION = 'deletedAt IS NULL';

    // 'system' 网关只用于 admin 手工打钱 / 扣钱，对外列表（admin 配置页 + 客户可选支付方式）一律隐藏。
    // 仅内部按 key 显式 lookup（findByKey('system')）能拿到。
    const GATEWAY_KEY_SYSTEM = 'system';

    protected function notDeletedCondition() {
        return self::NOT_DELETED_CONDITION;
    }

    public function findById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id AND {$this->notDeletedCondition()} LIMIT 1";
        $result = $this->db->fetchOne($sql, ['id' => $id]);
        return $result ? $this->hideFields($result) : null;
    }

    public function findOne($conditions) {
        if (!array_key_exists('deletedAt', $conditions)) {
            $conditions['deletedAt'] = null;
        }
        return parent::findOne($conditions);
    }

    public function findAll($conditions = [], $orderBy = null, $limit = null, $offset = null) {
        if (!array_key_exists('deletedAt', $conditions)) {
            $conditions['deletedAt'] = null;
        }
        return parent::findAll($conditions, $orderBy, $limit, $offset);
    }

    public function update($id, $data) {
        $filteredData = $this->filterFillable($data);
        if (empty($filteredData)) {
            return false;
        }

        return $this->db->update(
            $this->table,
            $filteredData,
            "{$this->primaryKey} = :id AND {$this->notDeletedCondition()}",
            ['id' => $id]
        );
    }

    /**
     * 根据gatewayKey查找
     */
    public function findByKey($gatewayKey) {
        return $this->findOne(['gatewayKey' => $gatewayKey]);
    }

    /**
     * 获取所有启用的网关（自动隐藏 system 网关）
     */
    public function getEnabledGateways() {
        $sql = "SELECT * FROM {$this->table}
                WHERE isEnabled = 1
                  AND gatewayKey != :systemKey
                  AND {$this->notDeletedCondition()}
                ORDER BY displayOrder ASC, id ASC";
        $results = $this->db->fetchAll($sql, ['systemKey' => self::GATEWAY_KEY_SYSTEM]);
        return $this->hideFields($results);
    }

    /**
     * 获取所有网关（自动隐藏 system 网关）
     */
    public function getAllGateways() {
        $sql = "SELECT * FROM {$this->table}
                WHERE gatewayKey != :systemKey
                  AND {$this->notDeletedCondition()}
                ORDER BY displayOrder ASC, id ASC";
        $results = $this->db->fetchAll($sql, ['systemKey' => self::GATEWAY_KEY_SYSTEM]);
        return $this->hideFields($results);
    }

    /**
     * 获取网关配置（包含解密后的密钥）
     * 仅供内部使用，不应直接返回给前端
     */
    public function getGatewayConfigForProcessing($gatewayKey) {
        $sql = "SELECT * FROM {$this->table} WHERE gatewayKey = :gatewayKey AND isEnabled = 1 AND {$this->notDeletedCondition()} LIMIT 1";
        return $this->db->fetchOne($sql, ['gatewayKey' => $gatewayKey]);
    }

    /**
     * 根据gatewayKey查找（包含敏感字段，仅供内部使用）
     * 绕过 $hidden 字段限制，返回完整的配置信息
     *
     * @param string $gatewayKey
     * @return array|null
     */
    public function findByKeyWithSecrets($gatewayKey) {
        $sql = "SELECT * FROM {$this->table} WHERE gatewayKey = :gatewayKey AND {$this->notDeletedCondition()} LIMIT 1";
        $result = $this->db->fetchOne($sql, ['gatewayKey' => $gatewayKey]);
        return $result ? $this->normalizeDataTypes($result) : null;
    }

    /**
     * 根据id查找（包含敏感字段，仅供内部使用）
     * 绕过 $hidden 字段限制，返回完整的配置信息
     *
     * @param string $gatewayKey
     * @return array|null
     */
    public function findByIdWithSecrets($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id AND {$this->notDeletedCondition()} LIMIT 1";
        $result = $this->db->fetchOne($sql, ['id' => $id]);
        return $result ? $this->normalizeDataTypes($result) : null;
    }

    protected function getNumericFields() {
        return [
            'id',
            'integration',
            'updatedBy',
            'amountDecimalPlaces',
            'displayOrder'
        ];
    }

    protected function getBooleanFields() {
        return [
            'integration',
            'isMultiCurrency'
        ];
    }
}
