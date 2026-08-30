<?php
/**
 * Country Model
 * 对应表: countrylist
 * 国家参考数据
 */

require_once __DIR__ . '/BaseModel.php';

class Country extends BaseModel {
    protected $table = 'countries';
    protected $primaryKey = 'id';
    protected $fillable = [
        'code',
        'code2',
        'name',
        'region',
        'isActive'
    ];

    /**
     * 获取所有激活的国家
     * @param string $region - 区域筛选
     * @return array
     */
    public function getActiveCountries($region = null) {
        $sql = "SELECT * FROM {$this->table} WHERE isActive = 1";
        $params = [];

        if ($region) {
            $sql .= " AND region = ?";
            $params[] = $region;
        }

        $sql .= " ORDER BY name ASC";

        return $this->query($sql, $params);
    }

    /**
     * 根据国家代码查找
     * @param string $code - ISO 3166-1 alpha-3 或 alpha-2
     * @return array|null
     */
    public function findByCode($code) {
        $sql = "SELECT * FROM {$this->table} WHERE code = ? OR code2 = ? LIMIT 1";
        $result = $this->query($sql, [$code, $code]);
        return $result ? $result[0] : null;
    }

    /**
     * 批量查找多个国家
     * @param array $codes - 国家代码数组
     * @return array
     */
    public function findByCodes($codes) {
        if (empty($codes)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $sql = "SELECT * FROM {$this->table} WHERE (code IN ({$placeholders}) OR code2 IN ({$placeholders})) AND isActive = 1";
        $params = array_merge($codes, $codes);

        return $this->query($sql, $params);
    }

    /**
     * 获取所有区域
     * @return array
     */
    public function getRegions() {
        $sql = "SELECT DISTINCT region FROM {$this->table} WHERE isActive = 1 AND region IS NOT NULL ORDER BY region";
        return $this->query($sql);
    }

    /**
     * 按区域分组获取国家
     * @return array
     */
    public function getCountriesByRegion() {
        $countries = $this->getActiveCountries();
        $grouped = [];

        foreach ($countries as $country) {
            $region = $country['region'] ?: 'Other';
            if (!isset($grouped[$region])) {
                $grouped[$region] = [];
            }
            $grouped[$region][] = $country;
        }

        return $grouped;
    }

    /**
     * 验证国家代码是否有效
     * @param string $code
     * @return bool
     */
    public function isValidCountryCode($code) {
        if ($code === 'ALL') {
            return true; // Special case for "all countries"
        }

        $country = $this->findByCode($code);
        return $country !== null;
    }

    /**
     * 验证多个国家代码
     * @param array $codes
     * @return array - ['valid' => [...], 'invalid' => [...]]
     */
    public function validateCountryCodes($codes) {
        $valid = [];
        $invalid = [];

        foreach ($codes as $code) {
            if ($this->isValidCountryCode($code)) {
                $valid[] = $code;
            } else {
                $invalid[] = $code;
            }
        }

        return [
            'valid' => $valid,
            'invalid' => $invalid
        ];
    }
}
