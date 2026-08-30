<?php
/**
 * Crypto Currency Model
 * 对应表: crypto_currencies
 * 用于管理从 Alchemy Pay Crypto Query API 同步的加密货币配置
 */

require_once __DIR__ . '/BaseModel.php';

class CryptoCurrency extends BaseModel {
    protected $table = 'crypto_currencies';
    protected $primaryKey = 'id';
    protected $fillable = [
        'fiat_code',
        'crypto_code',
        'network',
        'network_display',
        'buy_enable',
        'sell_enable',
        'min_crypto_amount',
        'max_crypto_amount',
        'is_enabled',
        'api_supported',
        'last_synced_at'
    ];

    /**
     * 根据唯一键查找（用于同步时去重）
     * @param string $fiatCode
     * @param string $cryptoCode
     * @param string $network
     * @return array|null
     */
    public function findByUniqueKey($fiatCode, $cryptoCode, $network) {
        $sql = "SELECT * FROM {$this->table}
                WHERE fiat_code = ? AND crypto_code = ? AND network = ?
                LIMIT 1";
        $result = $this->db->fetchOne($sql, [$fiatCode, $cryptoCode, $network]);
        return $result ? $this->normalizeDataTypes($result) : null;
    }

    /**
     * 获取所有启用的 Crypto（用于前端选择）
     * @param string|null $fiatCode 法币代码
     * @param string|null $side BUY or SELL
     * @return array
     */
    public function getEnabledCryptos($fiatCode = null, $side = null) {
        $sql = "SELECT * FROM {$this->table} WHERE is_enabled = 1 AND api_supported = 1";
        $params = [];

        if ($fiatCode) {
            $sql .= " AND fiat_code = ?";
            $params[] = $fiatCode;
        }

        if ($side === 'BUY') {
            $sql .= " AND buy_enable = 1";
        } elseif ($side === 'SELL') {
            $sql .= " AND sell_enable = 1";
        }

        $sql .= " ORDER BY fiat_code, crypto_code, network";
        return $this->query($sql, $params);
    }

    /**
     * 批量更新或插入（用于同步）
     * @param string $fiatCode 法币代码
     * @param array $dataList
     * @return array ['inserted' => count, 'updated' => count]
     */
    public function syncFromApi($fiatCode, $dataList) {
        $inserted = 0;
        $updated = 0;
        $now = date('Y-m-d H:i:s');

        foreach ($dataList as $data) {
            $existing = $this->findByUniqueKey($fiatCode, $data['crypto_code'], $data['network']);

            $data['fiat_code'] = $fiatCode;
            $data['api_supported'] = 1;
            $data['last_synced_at'] = $now;

            if ($existing) {
                // 更新现有记录（保留 is_enabled 状态）
                $updateData = $data;
                unset($updateData['is_enabled']); // 不同步覆盖管理员设置的启用状态
                $this->update($existing['id'], $updateData);
                $updated++;
            } else {
                // 插入新记录（默认不启用）
                $data['is_enabled'] = 0;
                $this->create($data);
                $inserted++;
            }
        }

        return ['inserted' => $inserted, 'updated' => $updated];
    }
}
