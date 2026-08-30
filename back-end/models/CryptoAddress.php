<?php
/**
 * Crypto Address Model
 * 对应表: crypto_addresses
 * 用于管理加密货币入金/出金地址
 */

require_once __DIR__ . '/BaseModel.php';

class CryptoAddress extends BaseModel {
    protected $table = 'crypto_addresses';
    protected $primaryKey = 'id';
    protected $fillable = [
        'side',
        'name',
        'fiat_currency',
        'crypto_currency',
        'network',
        'wallet_address',
        'min_amount',
        'is_active',
        'updated_by'
    ];

    /**
     * 获取所有地址（包含筛选条件）
     * @param array $conditions 筛选条件
     * @param string $orderBy 排序
     * @return array
     */
    public function getAll($conditions = [], $orderBy = 'id DESC') {
        $where = [];
        $params = [];

        if (!empty($conditions['side'])) {
            $where[] = "side = ?";
            $params[] = $conditions['side'];
        }

        if (!empty($conditions['fiat_currency'])) {
            $where[] = "fiat_currency = ?";
            $params[] = $conditions['fiat_currency'];
        }

        if (!empty($conditions['crypto_currency'])) {
            $where[] = "crypto_currency = ?";
            $params[] = $conditions['crypto_currency'];
        }

        if (!empty($conditions['network'])) {
            $where[] = "network = ?";
            $params[] = $conditions['network'];
        }

        if (isset($conditions['is_active'])) {
            $where[] = "is_active = ?";
            $params[] = $conditions['is_active'] ? 1 : 0;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM {$this->table} {$whereClause} ORDER BY {$orderBy}";

        if (!empty($params)) {
            return $this->query($sql, $params);
        } else {
            return $this->query($sql);
        }
    }

    /**
     * 获取活跃的地址
     * @param string $side BUY or SELL
     * @return array
     */
    public function getActiveAddresses($side = null) {
        $conditions = ['is_active' => 1];
        if ($side) {
            $conditions['side'] = $side;
        }
        return $this->getAll($conditions, 'id DESC');
    }

    /**
     * 检查地址是否已存在（相同side, crypto, network, fiat的组合）
     * @param string $side
     * @param string $cryptoCurrency
     * @param string $network
     * @param string $fiatCurrency
     * @param int|null $excludeId 排除的ID（用于更新时）
     * @return bool
     */
    public function exists($side, $cryptoCurrency, $network, $fiatCurrency, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}
                WHERE side = ? AND crypto_currency = ? AND network = ? AND fiat_currency = ?";
        $params = [$side, $cryptoCurrency, $network, $fiatCurrency];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $result = $this->query($sql, $params);
        return !empty($result) && $result[0]['count'] > 0;
    }
}
