<?php
/**
 * 搜索标签模型（快速搜索标签配置）
 */

require_once __DIR__ . '/BaseModel.php';

class SearchTag extends BaseModel {
    protected $table = 'searchTags';
    protected $primaryKey = 'id';

    protected $fillable = [
        'tagName', 'searchKeywords', 'displayOrder', 'isActive', 'createdBy'
    ];

    /**
     * 检查标签名称是否可用
     */
    public function isTagNameAvailable($tagName): bool
    {
        $tag = $this->findOne([ 'tagName' => $tagName ]);
        return empty($tag);
    }

    /**
     * 获取所有活跃的搜索标签
     */
    public function getActiveTags() {
        return $this->findAll(['isActive' => 1], 'displayOrder ASC');
    }

    /**
     * 获取所有搜索标签（包括非活跃的）
     */
    public function getAllTags() {
        return $this->findAll([], 'displayOrder ASC');
    }

    /**
     * 更新显示顺序
     */
    public function updateDisplayOrder($tagId, $newOrder) {
        return $this->update($tagId, ['displayOrder' => $newOrder]);
    }

    /**
     * 批量更新显示顺序
     */
    public function bulkUpdateOrder($orderData) {
        foreach ($orderData as $item) {
            $this->update($item['id'], ['displayOrder' => $item['order']]);
        }
        return true;
    }

    /**
     * 切换标签状态（启用/禁用）
     */
    public function toggleActive($tagId) {
        $tag = $this->findById($tagId);
        if (!$tag) {
            return false;
        }

        $newStatus = $tag['isActive'] == 1 ? 0 : 1;
        return $this->update($tagId, ['isActive' => $newStatus]);
    }
}
