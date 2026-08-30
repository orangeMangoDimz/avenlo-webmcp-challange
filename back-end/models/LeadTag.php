<?php
/**
 * Lead 标签模型
 */

require_once __DIR__ . '/BaseModel.php';

class LeadTag extends BaseModel {
    protected $table = 'leadTags';
    protected $primaryKey = 'id';

    protected $fillable = [
        'tagName', 'tagColor', 'description', 'isSystemTag', 'createdBy'
    ];

    /**
     * 获取所有标签
     */
    public function getAllTags() {
        return $this->findAll([], 'tagName ASC');
    }

    /**
     * 根据标签名查找
     */
    public function findByName($tagName) {
        return $this->findOne(['tagName' => $tagName]);
    }

    /**
     * 获取系统标签
     */
    public function getSystemTags() {
        return $this->findAll(['isSystemTag' => 1], 'tagName ASC');
    }

    /**
     * 获取自定义标签
     */
    public function getCustomTags() {
        return $this->findAll(['isSystemTag' => 0], 'createdAt DESC');
    }

    /**
     * 获取标签的使用统计
     */
    public function getTagUsageStats($tagId) {
        $sql = "SELECT COUNT(*) as usageCount
                FROM leadTagAssignments
                WHERE tagId = :tagId";

        return $this->db->fetchOne($sql, ['tagId' => $tagId]);
    }

    /**
     * 获取所有标签及其使用次数
     */
    public function getTagsWithUsage() {
        $sql = "SELECT lt.*,
                       COUNT(lta.id) as usageCount
                FROM {$this->table} lt
                LEFT JOIN leadTagAssignments lta ON lt.id = lta.tagId
                GROUP BY lt.id
                ORDER BY lt.tagName ASC";

        return $this->db->fetchAll($sql);
    }

    /**
     * 检查是否可以删除标签（系统标签不能删除）
     */
    public function canDelete($tagId) {
        $tag = $this->findById($tagId);
        return $tag && $tag['isSystemTag'] == 0;
    }
}
