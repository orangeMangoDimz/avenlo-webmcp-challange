<?php
/**
 * Fiat Currency Model
 * 对应表: fiat_currencies
 * 用于管理从 Alchemy Pay Fiat Query API 同步的法币配置
 */

require_once __DIR__ . '/BaseModel.php';

class FiatCurrency extends BaseModel {
    protected $table = 'fiat_currencies';
    protected $primaryKey = 'id';
    protected $fillable = [
        'fiat_code',
        'country',
        'pay_way_code',
        'pay_way_name',
        'fixed_fee',
        'fee_rate',
        'pay_min',
        'pay_max',
        'usd_pay_min',
        'usd_pay_max',
        'side',
        'is_enabled',
        'api_supported',
        'last_synced_at'
    ];

    /**
     * 根据唯一键查找（用于同步时去重）
     * @param string $fiatCode
     * @param string|null $country
     * @param string|null $payWayCode
     * @param string $side
     * @return array|null
     */
    public function findByUniqueKey($fiatCode, $country, $payWayCode, $side) {
        $sql = "SELECT * FROM {$this->table}
                WHERE fiat_code = ? AND side = ?";
        $params = [$fiatCode, $side];

        if ($country !== null && $country !== '') {
            $sql .= " AND country = ?";
            $params[] = $country;
        } else {
            $sql .= " AND (country IS NULL OR country = '')";
        }

        if ($payWayCode !== null && $payWayCode !== '') {
            $sql .= " AND pay_way_code = ?";
            $params[] = $payWayCode;
        } else {
            $sql .= " AND (pay_way_code IS NULL OR pay_way_code = '')";
        }

        $sql .= " LIMIT 1";
        $result = $this->db->fetchOne($sql, $params);
        return $result ? $this->normalizeDataTypes($result) : null;
    }

    /**
     * 获取所有启用的 Fiat（用于前端选择）
     * @param string|null $side BUY or SELL
     * @return array
     */
    public function getEnabledFiats($side = null) {
        $sql = "SELECT * FROM {$this->table} WHERE is_enabled = 1 AND api_supported = 1";
        $params = [];

        if ($side) {
            $sql .= " AND side = ?";
            $params[] = $side;
        }

        $sql .= " ORDER BY fiat_code, country, pay_way_code";
        return $this->query($sql, $params);
    }

    /**
     * 批量更新或插入（用于同步）
     * @param array $dataList
     * @return array ['inserted' => count, 'updated' => count]
     */
    public function syncFromApi($dataList) {
        $inserted = 0;
        $updated = 0;
        $now = date('Y-m-d H:i:s');

        foreach ($dataList as $data) {
            $existing = $this->findByUniqueKey(
                $data['fiat_code'],
                $data['country'] ?? null,
                $data['pay_way_code'] ?? null,
                $data['side']
            );

            $data['api_supported'] = 1;
            $data['last_synced_at'] = $now;

            if ($existing) {
                // 更新现有记录（保留 is_enabled 状态）
                $updateData = $data;
                unset($updateData['is_enabled']); // 不同步覆盖管理员设置的启用状态
                $this->update($existing['id'], $updateData);
                $updated++;
            } else {
                // 插入新记录（默认不启用，需要管理员手动启用）
                $data['is_enabled'] = 0;
                $this->create($data);
                $inserted++;
            }
        }

        return ['inserted' => $inserted, 'updated' => $updated];
    }
}
