<?php
/**
 * Transaction Notification Setting Model
 * 对应表: transactionNotificationSettings
 */

require_once __DIR__ . '/BaseModel.php';

class TransactionNotificationSetting extends BaseModel {
    protected $table = 'transactionNotificationSettings';
    protected $primaryKey = 'id';
    protected $fillable = [
        'settingKey',
        'settingValue',
        'settingType',
        'category',
        'displayName',
        'description',
        'updatedBy'
    ];

    /**
     * 根据settingKey获取设置值
     */
    public function getValue($settingKey, $default = null) {
        $setting = $this->findOne(['settingKey' => $settingKey]);

        if (!$setting) {
            return $default;
        }

        // 根据类型转换值
        switch ($setting['settingType']) {
            case 'boolean':
                return (bool)$setting['settingValue'];
            case 'number':
                return is_numeric($setting['settingValue']) ? (float)$setting['settingValue'] : $default;
            case 'json':
                return json_decode($setting['settingValue'], true);
            default:
                return $setting['settingValue'];
        }
    }

    /**
     * 设置值
     */
    public function setValue($settingKey, $value, $updatedBy = null) {
        $setting = $this->findOne(['settingKey' => $settingKey]);

        // 转换值为字符串
        if (is_bool($value)) {
            $valueStr = $value ? '1' : '0';
        } elseif (is_array($value)) {
            $valueStr = json_encode($value);
        } else {
            $valueStr = (string)$value;
        }

        if ($setting) {
            return $this->update($setting['id'], [
                'settingValue' => $valueStr,
                'updatedBy' => $updatedBy
            ]);
        }

        return false;
    }

    /**
     * 获取分类下的所有设置
     */
    public function getByCategory($category) {
        return $this->findAll(['category' => $category]);
    }

    /**
     * 获取所有设置（按分类分组）
     */
    public function getAllGrouped() {
        $settings = $this->findAll();
        $grouped = [
            'client' => [],
            'admin' => [],
            'alerts' => []
        ];

        foreach ($settings as $setting) {
            $category = $setting['category'];
            if (isset($grouped[$category])) {
                $grouped[$category][] = $setting;
            }
        }

        return $grouped;
    }
}
