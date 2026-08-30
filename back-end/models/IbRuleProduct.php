<?php
/**
 * IB Rule Product 模型
 * 对应表：ibRuleProducts
 */

require_once __DIR__ . '/BaseModel.php';

class IbRuleProduct extends BaseModel {
    protected $table = 'ibRuleProducts';
    protected $primaryKey = 'id';

    protected $fillable = [
        'ruleId', 'productType', 'productName', 'productId', 'commissionType',
        'commissionRate', 'additionalRate', 'minimumVolume'
    ];

    /**
     * 批量创建产品配置
     */
    public function bulkCreateProducts($ruleId, $products) {
        $this->db->beginTransaction();

        try {
            foreach ($products as $product) {
                $product['ruleId'] = $ruleId;
                $this->create($product);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * 批量更新产品配置
     */
    public function bulkUpdateProducts($ruleId, $products) {
        $this->db->beginTransaction();

        try {
            // 删除现有产品
            $this->db->query(
                "DELETE FROM {$this->table} WHERE ruleId = :ruleId",
                ['ruleId' => $ruleId]
            );

            // 重新创建
            foreach ($products as $product) {
                $product['ruleId'] = $ruleId;

                // 清理产品数据：移除 null, undefined, 空字符串，以及不在 fillable 中的字段
                $cleanedProduct = [];
                foreach ($product as $key => $value) {
                    // 只保留 fillable 字段
                    if (in_array($key, $this->fillable)) {
                        if ($key === 'productId') {
                            if ($value !== null && $value !== '') {
                                $cleanedProduct['productId'] = (int) $value;
                            }
                            continue;
                        }
                        // 跳过 null 和空字符串（数据库有默认值）
                        if ($value !== null && $value !== '') {
                            $cleanedProduct[$key] = $value;
                        }
                    }
                }

                // 确保 ruleId 存在
                $cleanedProduct['ruleId'] = $ruleId;

                $this->create($cleanedProduct);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
