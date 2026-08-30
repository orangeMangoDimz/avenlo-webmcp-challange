<?php
/**
 * 系统设置模型
 */

require_once __DIR__ . '/BaseModel.php';

class AdminSystemSetting extends BaseModel {
    protected $table = 'adminSystemSettings';
    protected $primaryKey = 'id';

    protected $fillable = [
        'settingKey', 'settingValue', 'settingType', 'category',
        'displayName', 'description', 'isPublic', 'isEditable',
        'sortOrder', 'updatedBy'
    ];

    /**
     * 根据键获取设置值
     */
    public function getValue($key, $default = null) {
        $setting = $this->findOne(['settingKey' => $key]);

        if (!$setting) {
            return $default;
        }

        return $this->castValue($setting['settingValue'], $setting['settingType']);
    }

    /**
     * 设置值
     */
    public function setValue($key, $value, $updatedBy = null) {
        $setting = $this->findOne(['settingKey' => $key]);

        if (!$setting) {
            return false;
        }

        $data = [
            'settingValue' => $value,
            'updatedAt' => date('Y-m-d H:i:s')
        ];

        if ($updatedBy) {
            $data['updatedBy'] = $updatedBy;
        }

        return $this->db->update(
            $this->table,
            $data,
            'settingKey = :key',
            ['key' => $key]
        );
    }

    /**
     * 按分类获取设置
     */
    public function getByCategory($category) {
        $settings = $this->findAll(['category' => $category], 'sortOrder ASC');

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['settingKey']] = $this->castValue(
                $setting['settingValue'],
                $setting['settingType']
            );
        }

        return $result;
    }

    /**
     * 获取所有公开设置
     */
    public function getPublicSettings() {
        $settings = $this->findAll(['isPublic' => 1], 'sortOrder ASC');

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['settingKey']] = [
                'value' => $this->castValue($setting['settingValue'], $setting['settingType']),
                'type' => $setting['settingType'],
                'display_name' => $setting['displayName']
            ];
        }

        return $result;
    }

    /**
     * 批量更新设置
     */
    public function bulkUpdate($settings, $updatedBy = null) {
        try {
            $this->db->beginTransaction();

            foreach ($settings as $key => $value) {
                $this->setValue($key, $value, $updatedBy);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }

    /**
     * 类型转换
     */
    private function castValue($value, $type) {
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'number':
                return is_numeric($value) ? (int)$value : 0;
            case 'json':
                return json_decode($value, true);
            case 'string':
            default:
                return $value;
        }
    }
}
