<?php
/**
 * Transaction Search Tag Model
 * 对应表: transactionSearchTags
 */

require_once __DIR__ . '/BaseModel.php';

class TransactionSearchTag extends BaseModel {
    protected $table = 'transactionSearchTags';
    protected $primaryKey = 'id';
    protected $fillable = [
        'tagName',
        'searchKeywords',
        'transactionType',
        'displayOrder',
        'isActive',
        'createdBy'
    ];

    /**
     * 获取所有活跃的搜索标签
     */
    public function getActiveTags($transactionType = 'both') {
        $sql = "SELECT * FROM {$this->table}
                WHERE isActive = 1
                AND (transactionType = :transactionType OR transactionType = 'both')
                ORDER BY displayOrder ASC";

        return $this->query($sql, ['transactionType' => $transactionType]);
    }

    /**
     * 根据标签名查找
     */
    public function findByName($tagName) {
        return $this->findOne(['tagName' => $tagName]);
    }
}
