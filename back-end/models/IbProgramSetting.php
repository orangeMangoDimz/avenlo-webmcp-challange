<?php
/**
 * IB Program Setting 模型
 * 对应表：ibProgramSettings
 */

require_once __DIR__ . '/BaseModel.php';

class IbProgramSetting extends BaseModel {
    public const MAX_TIER_LEVEL_COUNT_KEY = 'max_tier_level_count';
    public const SYMBOL_EXCHANGE_GLOBAL_SYNC_MODE_KEY = 'ib_symbol_exchange_global_sync_mode';
    public const DEFAULT_SYMBOL_EXCHANGE_GLOBAL_SYNC_MODE = 'auto';
    public const DEFAULT_MAX_TIER_LEVEL_COUNT = 5;
    /** 防误输入上限，非业务等级上限 */
    public const MAX_TIER_LEVEL_COUNT_SANITY = 9999;

    /** @var int|null 请求内缓存 */
    private static $maxTierLevelCountCache = null;

    protected $table = 'ibProgramSettings';
    protected $primaryKey = 'id';

    protected $fillable = [
        'settingKey', 'settingValue', 'settingType', 'settingGroup',
        'description', 'updatedBy'
    ];

    /**
     * 根据key获取设置值
     */
    public function getSettingByKey($key) {
        $sql = "SELECT * FROM {$this->table} WHERE settingKey = :key LIMIT 1";
        return $this->db->fetchOne($sql, ['key' => $key]);
    }

    /**
     * 根据组获取设置
     */
    public function getSettingsByGroup($group) {
        $sql = "SELECT * FROM {$this->table} WHERE settingGroup = :group ORDER BY id ASC";
        return $this->db->fetchAll($sql, ['group' => $group]);
    }

    /**
     * 获取所有设置（按组分组）
     */
    public function getAllSettingsGrouped() {
        $sql = "SELECT * FROM {$this->table} ORDER BY settingGroup ASC, id ASC";
        $settings = $this->db->fetchAll($sql);

        $grouped = [];
        foreach ($settings as $setting) {
            $group = $setting['settingGroup'];
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][] = $setting;
        }

        return $grouped;
    }

    /**
     * 批量更新设置
     */
    public function bulkUpdateSettings($settings, $updatedBy) {
        $this->db->beginTransaction();

        try {
            foreach ($settings as $key => $value) {
                $sql = "UPDATE {$this->table}
                        SET settingValue = :value, updatedBy = :updatedBy
                        WHERE settingKey = :key";

                $this->db->query($sql, [
                    'key' => $key,
                    'value' => $value,
                    'updatedBy' => $updatedBy
                ]);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * 获取设置值（带类型转换）
     */
    public function getSettingValue($key, $default = null) {
        $setting = $this->getSettingByKey($key);

        if (!$setting) {
            return $default;
        }

        $value = $setting['settingValue'];
        $type = $setting['settingType'];

        // 根据类型转换
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'number':
                return is_numeric($value) ? (strpos($value, '.') !== false ? (float)$value : (int)$value) : $default;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    /**
     * IB 可配置的最大等级数（创建 Tier 时可选 1…N；网络递归深度与之联动）。
     */
    public function getMaxTierLevelCount(): int {
        if (self::$maxTierLevelCountCache !== null) {
            return self::$maxTierLevelCountCache;
        }

        $value = $this->getSettingValue(self::MAX_TIER_LEVEL_COUNT_KEY, self::DEFAULT_MAX_TIER_LEVEL_COUNT);
        $n = (int) $value;
        if ($n < 1) {
            $n = self::DEFAULT_MAX_TIER_LEVEL_COUNT;
        }
        if ($n > self::MAX_TIER_LEVEL_COUNT_SANITY) {
            $n = self::MAX_TIER_LEVEL_COUNT_SANITY;
        }

        self::$maxTierLevelCountCache = $n;
        return $n;
    }

    public function getNetworkMaxDepth(): int {
        return $this->getMaxTierLevelCount();
    }

    public static function resolveNetworkMaxDepth(): int {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance->getNetworkMaxDepth();
    }

    public static function clearMaxTierLevelCountCache(): void {
        self::$maxTierLevelCountCache = null;
    }
}
