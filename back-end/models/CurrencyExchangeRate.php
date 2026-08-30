<?php
/**
 * Currency Exchange Rate Model
 * 对应表: currencyExchangeRates
 */

require_once __DIR__ . '/BaseModel.php';

class CurrencyExchangeRate extends BaseModel {
    protected $table = 'currencyExchangeRates';
    protected $primaryKey = 'id';
    protected $fillable = [
        'type',
        'currencyCode',
        'currencyName',
        'currencySymbol',
        'exchangeRate',
        'syncMode',
        'lastSyncedAt',
        'depositType',
        'withdrawType',
        'depositBias',
        'withdrawBias',
        'sortOrder',
        'isActive',
        'updatedBy'
    ];

    protected function getBooleanFields() {
        return ['isActive'];
    }

    protected function getNumericFields() {
        return [
            'id',
            'exchangeRate',
            'depositBias',
            'withdrawBias',
            'sortOrder',
            'updatedBy'
        ];
    }

    /**
     * 获取所有启用的汇率
     */
    public function getActiveRates() {
        return $this->findAll(['isActive' => 1], 'sortOrder DESC, currencyCode ASC');
    }

    /**
     * 获取所有汇率（包括禁用的）
     */
    public function getAllRates() {
        return $this->findAll([], 'sortOrder DESC, currencyCode ASC');
    }

    /**
     * 根据货币代码获取汇率
     */
    public function getRateByCurrencyCode($currencyCode, $type = null) {
        $conditions = [
            'currencyCode' => strtoupper($currencyCode),
            'isActive' => 1
        ];
        if ($type !== null && $type !== '') {
            $conditions['type'] = strtolower((string)$type);
        }

        return $this->findOne($conditions);
    }

    public function getAdjustedRateByCurrencyCode($currencyCode, $assetType = null, $transactionType = null) {
        $rate = $this->getRateByCurrencyCode($currencyCode, $assetType);
        return $this->resolveAdjustedRate($rate, $transactionType);
    }

    public function resolveAdjustedRate($rate, $transactionType = null) {
        if (!$rate || !isset($rate['exchangeRate'])) {
            return null;
        }

        $baseRate = (float)$rate['exchangeRate'];
        if ($baseRate <= 0) {
            return null;
        }

        $normalizedTransactionType = $this->normalizeTransactionType($transactionType);
        if ($normalizedTransactionType === 'deposit') {
            return $this->calculateAdjustedRate(
                $baseRate,
                $rate['depositType'] ?? 'fixed',
                $rate['depositBias'] ?? 0
            );
        }

        if ($normalizedTransactionType === 'withdraw') {
            return $this->calculateAdjustedRate(
                $baseRate,
                $rate['withdrawType'] ?? 'fixed',
                $rate['withdrawBias'] ?? 0
            );
        }

        return $baseRate;
    }

    /**
     * 根据货币代码查找（包括禁用的）
     */
    public function findByCurrencyCode($currencyCode, $type = null) {
        $conditions = [
            'currencyCode' => strtoupper($currencyCode)
        ];
        if ($type !== null && $type !== '') {
            $conditions['type'] = strtolower((string)$type);
        }

        return $this->findOne($conditions);
    }

    /**
     * 验证货币代码是否已存在
     */
    public function currencyCodeExists($currencyCode, $excludeId = null, $type = null) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE currencyCode = :currencyCode";
        $params = ['currencyCode' => strtoupper($currencyCode)];

        if ($type !== null && $type !== '') {
            $sql .= " AND type = :type";
            $params['type'] = strtolower((string)$type);
        }

        if ($excludeId) {
            $sql .= " AND id != :excludeId";
            $params['excludeId'] = $excludeId;
        }

        $result = $this->db->fetchOne($sql, $params);
        return $result['count'] > 0;
    }

    /**
     * 创建或更新汇率
     */
    public function createOrUpdate($currencyCode, $data) {
        $existing = $this->findByCurrencyCode($currencyCode, $data['type'] ?? null);

        if ($existing) {
            return $this->update($existing['id'], $data);
        } else {
            $data['currencyCode'] = strtoupper($currencyCode);
            return $this->create($data);
        }
    }

    /**
     * 切换启用状态
     */
    public function toggleActive($id) {
        $rate = $this->findById($id);
        if (!$rate) {
            return false;
        }

        return $this->update($id, [
            'isActive' => $rate['isActive'] ? 0 : 1
        ]);
    }

    /**
     * 切换 auto / manual。manual 的行不被 5 分钟汇率同步覆盖。
     */
    public function toggleSyncMode($id) {
        $rate = $this->findById($id);
        if (!$rate) {
            return false;
        }

        $next = (($rate['syncMode'] ?? 'auto') === 'auto') ? 'manual' : 'auto';
        return $this->update($id, ['syncMode' => $next]);
    }

    private function calculateAdjustedRate($baseRate, $mode, $bias) {
        $normalizedMode = strtolower(trim((string)($mode ?? 'fixed')));
        $normalizedBias = is_numeric($bias) ? (float)$bias : 0.0;

        if ($normalizedMode === 'dynamic') {
            return $baseRate * (1 + $normalizedBias);
        }

        return $baseRate + $normalizedBias;
    }

    private function normalizeTransactionType($transactionType) {
        $normalized = strtolower(trim((string)($transactionType ?? '')));
        if ($normalized === 'withdrawal') {
            return 'withdraw';
        }

        if (in_array($normalized, ['deposit', 'withdraw'], true)) {
            return $normalized;
        }

        return '';
    }
}
