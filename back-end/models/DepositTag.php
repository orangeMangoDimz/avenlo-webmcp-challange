<?php
/**
 * Deposit Tag Model
 * 对应表: depositTags
 */

require_once __DIR__ . '/BaseModel.php';

class DepositTag extends BaseModel {
    protected $table = 'depositTags';
    protected $primaryKey = 'id';
    protected $fillable = [
        'tagName',
        'tagColor',
        'textColor',
        'description',
        'isSystemTag',
        'createdBy'
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
     * 创建或获取标签
     */
    public function findOrCreate($tagName, $createdBy = null) {
        $existing = $this->findByName($tagName);

        if ($existing) {
            return $existing;
        }

        $tagId = $this->create([
            'tagName' => $tagName,
            'createdBy' => $createdBy
        ]);

        return $this->findById($tagId);
    }
}
