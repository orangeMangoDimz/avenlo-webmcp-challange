<?php
/**
 * Trading Platform Leverage Model
 * 对应表: tradingPlatformLeverages
 */

require_once __DIR__ . '/BaseModel.php';

class TradingPlatformLeverage extends BaseModel {
    protected $table = 'tradingPlatformLeverages';
    protected $primaryKey = 'id';
    protected $fillable = [
        'platformId',
        'leverageValue',
        'financeProLeverageId',
        'displayLabel',
        'riskNote',
        'isEnabled',
        'displayOrder'
    ];

    /**
     * 获取指定平台启用的杠杆选项
     */
    public function getEnabledByPlatform($platformId) {
        $sql = "SELECT * FROM {$this->table} WHERE platformId = :platformId AND isEnabled = 1 ORDER BY displayOrder ASC";
        return $this->query($sql, ['platformId' => $platformId]);
    }

    /**
     * 获取指定平台的全部杠杆选项
     */
    public function getByPlatform($platformId, $enabledOnly = false) {
        $sql = "SELECT * FROM {$this->table} WHERE platformId = :platformId";
        $params = ['platformId' => $platformId];

        if ($enabledOnly) {
            $sql .= " AND isEnabled = 1";
        }

        $sql .= " ORDER BY displayOrder ASC, id ASC";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * 获取全部杠杆选项，并带上平台信息
     */
    public function getAllWithPlatforms(array $platformIds = [], $enabledOnly = false) {
        $sql = "SELECT tpl.*,
                       tp.platformKey,
                       tp.displayName AS platformName,
                       tp.shortCode AS platformCode,
                       tp.isEnabled AS platformIsEnabled
                FROM {$this->table} tpl
                INNER JOIN tradingPlatforms tp ON tp.id = tpl.platformId";
        $conditions = [];
        $params = [];

        if (!empty($platformIds)) {
            $placeholders = [];
            foreach (array_values($platformIds) as $index => $platformId) {
                $key = 'platformId' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = (int)$platformId;
            }
            $conditions[] = 'tpl.platformId IN (' . implode(', ', $placeholders) . ')';
        }

        if ($enabledOnly) {
            $conditions[] = 'tpl.isEnabled = 1';
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY tp.displayName ASC, tpl.displayOrder ASC, tpl.id ASC';
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * 判断杠杆是否可用
     */
    public function isLeverageEnabled($platformId, $leverageValue) {
        $sql = "SELECT COUNT(*) AS total FROM {$this->table} WHERE platformId = :platformId AND leverageValue = :leverageValue AND isEnabled = 1";
        $result = $this->queryOne($sql, [
            'platformId' => $platformId,
            'leverageValue' => $leverageValue
        ]);
        return ($result['total'] ?? 0) > 0;
    }

    /**
     * 根据平台和杠杆值查找
     */
    public function findByPlatformAndValue($platformId, $leverageValue, $excludeId = null) {
        $sql = "SELECT * FROM {$this->table} WHERE platformId = :platformId AND leverageValue = :leverageValue";
        $params = [
            'platformId' => $platformId,
            'leverageValue' => $leverageValue
        ];

        if ($excludeId !== null) {
            $sql .= " AND id != :excludeId";
            $params['excludeId'] = (int)$excludeId;
        }

        $sql .= " LIMIT 1";
        return $this->db->fetchOne($sql, $params);
    }
}
