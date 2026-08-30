<?php
/**
 * Client Interface Text Model
 * 对应表: clientInterfaceTexts
 * 用于管理客户端界面显示的文字配置
 */

require_once __DIR__ . '/BaseModel.php';

class ClientInterfaceText extends BaseModel {
    protected $table = 'clientInterfaceTexts';
    protected $primaryKey = 'id';
    protected $fillable = [
        'textKey',
        'textCategory',
        'textValue',
        'defaultValue',
        'description',
        'isActive',
        'updatedBy'
    ];

    /**
     * 获取所有激活的文字配置
     * @param string $category - 分类筛选 (deposit/withdrawal/general)
     * @return array
     */
    public function getActiveTexts($category = null) {
        $sql = "SELECT * FROM {$this->table} WHERE isActive = 1";
        $params = [];

        if ($category) {
            $sql .= " AND textCategory = ?";
            $params[] = $category;
        }

        $sql .= " ORDER BY textCategory, textKey";

        return $this->query($sql, $params);
    }

    /**
     * 根据textKey获取单个文字配置
     * @param string $textKey
     * @return array|null
     */
    public function getByKey($textKey) {
        return $this->findOne(['textKey' => $textKey]);
    }

    /**
     * 批量获取多个文字配置
     * @param array $keys - textKey数组
     * @return array
     */
    public function getByKeys($keys) {
        if (empty($keys)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $sql = "SELECT * FROM {$this->table} WHERE textKey IN ({$placeholders}) AND isActive = 1";

        return $this->query($sql, $keys);
    }

    /**
     * 更新文字配置
     * @param string $textKey
     * @param string $textValue
     * @param int $updatedBy - 管理员ID
     * @return bool
     */
    public function updateText($textKey, $textValue, $updatedBy = null) {
        $data = [
            'textValue' => $textValue,
            'updatedBy' => $updatedBy,
            'updatedAt' => date('Y-m-d H:i:s')
        ];

        $text = $this->getByKey($textKey);
        if (!$text) {
            return false;
        }

        return $this->update($text['id'], $data);
    }

    /**
     * 批量更新文字配置
     * @param array $texts - [textKey => textValue] 映射
     * @param int $updatedBy - 管理员ID
     * @return array - 返回更新结果统计
     */
    public function batchUpdateTexts($texts, $updatedBy = null) {
        $success = 0;
        $failed = 0;

        foreach ($texts as $textKey => $textValue) {
            $result = $this->updateText($textKey, $textValue, $updatedBy);
            if ($result) {
                $success++;
            } else {
                $failed++;
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'total' => count($texts)
        ];
    }

    /**
     * 获取格式化的文字配置（按类别分组）
     * @return array
     */
    public function getFormattedTexts() {
        $texts = $this->getActiveTexts();
        $formatted = [
            'deposit' => [],
            'withdrawal' => [],
            'general' => []
        ];

        foreach ($texts as $text) {
            $category = $text['textCategory'];
            $key = str_replace($category . '.', '', $text['textKey']);
            $formatted[$category][$key] = $text['textValue'];
        }

        return $formatted;
    }

    /**
     * 恢复默认值
     * @param string $textKey
     * @return bool
     */
    public function restoreDefault($textKey) {
        $text = $this->getByKey($textKey);
        if (!$text || !$text['defaultValue']) {
            return false;
        }

        return $this->updateText($textKey, $text['defaultValue']);
    }
}
