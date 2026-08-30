<?php
/**
 * 注册表单字段配置模型
 */

require_once __DIR__ . '/BaseModel.php';

class RegistrationFormField extends BaseModel {
    protected $table = 'registrationFormFields';
    protected $primaryKey = 'id';

    protected $fillable = [
        'fieldId', 'fieldName', 'fieldDescription', 'fieldType',
        'isEnabled', 'isRequired', 'isMandatory',
        'displayOrder', 'validationRules'
    ];

    /**
     * 获取所有启用的字段（按显示顺序）
     */
    public function getEnabledFields() {
        $fields = $this->findAll(['isEnabled' => 1], 'displayOrder ASC');
        return $this->convertBooleanFields($fields);
    }

    /**
     * 获取启用且非 mandatory 的字段（按显示顺序）—— App 注册页拿到的"可选字段"
     */
    public function getEnabledOptionalFields() {
        $fields = $this->findAll([
            'isEnabled'   => 1,
            'isMandatory' => 0,
        ], 'displayOrder ASC');
        return $this->convertBooleanFields($fields);
    }

    /**
     * 获取启用且 mandatory 的字段 fieldId 列表（业务硬约束必填项）
     */
    public function getMandatoryFieldIds() {
        $rows = $this->findAll([
            'isEnabled'   => 1,
            'isMandatory' => 1,
        ], 'displayOrder ASC');
        return array_column($rows ?: [], 'fieldId');
    }

    /**
     * 获取所有字段（按显示顺序）
     */
    public function getAllFields() {
        $fields = $this->findAll([], 'displayOrder ASC');
        return $this->convertBooleanFields($fields);
    }

    /**
     * 转换布尔字段为真正的布尔值
     */
    private function convertBooleanFields($fields) {
        if (empty($fields)) {
            return $fields;
        }

        foreach ($fields as &$field) {
            $field['isEnabled'] = (bool)(int)$field['isEnabled'];
            $field['isRequired'] = (bool)(int)$field['isRequired'];
            $field['isMandatory'] = (bool)(int)$field['isMandatory'];
        }

        return $fields;
    }

    /**
     * 根据fieldId查找
     */
    public function findByFieldId($fieldId) {
        return $this->findOne(['fieldId' => $fieldId]);
    }

    /**
     * 更新字段启用状态
     */
    public function toggleEnabled($id, $isEnabled) {
        // 检查是否是必填字段
        $field = $this->findById($id);
        if ($field && $field['isMandatory'] && !$isEnabled) {
            throw new Exception('Cannot disable mandatory fields.');
        }

        return $this->update($id, ['isEnabled' => $isEnabled]);
    }

    /**
     * 更新字段顺序
     */
    public function updateOrder($id, $newOrder) {
        return $this->update($id, ['displayOrder' => $newOrder]);
    }

    /**
     * 批量更新字段顺序
     * @param array $orderData 格式: [['id' => 1, 'order' => 1], ['id' => 2, 'order' => 2], ...]
     */
    public function batchUpdateOrder($orderData) {
        if (empty($orderData) || !is_array($orderData)) {
            return false;
        }

        foreach ($orderData as $item) {
            if (isset($item['id']) && isset($item['order'])) {
                $this->update($item['id'], ['displayOrder' => $item['order']]);
            }
        }
        return true;
    }

    /**
     * 删除字段（检查是否是必填字段）
     */
    public function deleteField($id) {
        $field = $this->findById($id);
        if ($field && $field['isMandatory']) {
            throw new Exception('Cannot delete mandatory fields.');
        }

        return $this->delete($id);
    }

    /**
     * 获取必填字段
     */
    public function getRequiredFields() {
        return $this->findAll([
            'isEnabled' => 1,
            'isRequired' => 1
        ], 'displayOrder ASC');
    }
}
