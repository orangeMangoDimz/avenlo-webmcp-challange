<?php
/**
 * External KYC Gateway Model
 * 对应表: externalKycGateways
 */

require_once __DIR__ . '/BaseModel.php';

class ExternalKycGateway extends BaseModel {
    protected $table = 'externalKycGateways';
    protected $primaryKey = 'id';
    protected $fillable = [
        'provider',
        'displayName',
        'isEnabled',
        'environment',
        'baseUrl',
        'appToken',
        'secretKey',
        'webhookSecret',
        'iframeBaseUrl',
        'returnUrl',
        'detailUrl',
        'configData',
        'updatedBy',
        'deletedAt',
    ];

    // 默认对外不返回的敏感字段
    protected $hidden = ['appToken', 'secretKey', 'webhookSecret'];

    const NOT_DELETED_CONDITION = 'deletedAt IS NULL';

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
     * 隐藏 secret 之前先注入 hasXxx 标识，前端可以用来展示"已配置/未配置"，
     * 但永远拿不到具体值。
     */
    protected function hideFields($data) {
        if (empty($data)) {
            return $data;
        }

        if (isset($data[$this->primaryKey])) {
            $data['hasAppToken'] = !empty($data['appToken']);
            $data['hasSecretKey'] = !empty($data['secretKey']);
            $data['hasWebhookSecret'] = !empty($data['webhookSecret']);
        } elseif (is_array($data)) {
            foreach ($data as &$record) {
                if (is_array($record)) {
                    $record['hasAppToken'] = !empty($record['appToken']);
                    $record['hasSecretKey'] = !empty($record['secretKey']);
                    $record['hasWebhookSecret'] = !empty($record['webhookSecret']);
                }
            }
            unset($record);
        }

        return parent::hideFields($data);
    }

    /**
     * 根据 id 查找（包含敏感字段，仅供 service 层使用）
     */
    public function findByIdWithSecrets($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id AND {$this->notDeletedCondition()} LIMIT 1";
        $result = $this->db->fetchOne($sql, ['id' => $id]);
        return $result ? $this->normalizeDataTypes($result) : null;
    }

    /**
     * 根据 provider 查找（包含敏感字段，仅供 service 层使用）
     */
    public function findByProviderWithSecrets($provider) {
        $sql = "SELECT * FROM {$this->table} WHERE provider = :provider AND {$this->notDeletedCondition()} LIMIT 1";
        $result = $this->db->fetchOne($sql, ['provider' => $provider]);
        return $result ? $this->normalizeDataTypes($result) : null;
    }
}
