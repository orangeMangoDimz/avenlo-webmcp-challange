<?php
/**
 * IB Rule Additional Rule 模型
 * 对应表：ibRuleAdditionalRules
 */

require_once __DIR__ . '/BaseModel.php';

class IbRuleAdditionalRule extends BaseModel {
    protected $table = 'ibRuleAdditionalRules';
    protected $primaryKey = 'id';

    protected $fillable = [
        'ruleId', 'productType', 'productName', 'ruleType',
        'ruleValue', 'ruleCondition', 'isActive'
    ];

    /**
     * 批量创建额外规则
     */
    public function bulkCreateRules($ruleId, $additionalRules) {
        $this->db->beginTransaction();

        try {
            foreach ($additionalRules as $rule) {
                $rule['ruleId'] = $ruleId;
                $this->create($rule);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * 批量更新额外规则
     */
    public function bulkUpdateRules($ruleId, $additionalRules) {
        $this->db->beginTransaction();

        try {
            // 删除现有额外规则（包括其关联的层级）
            $this->db->query(
                "DELETE FROM {$this->table} WHERE ruleId = :ruleId",
                ['ruleId' => $ruleId]
            );

            // 重新创建
            foreach ($additionalRules as $rule) {
                $rule['ruleId'] = $ruleId;

                // 清理数据：移除 null, undefined, 空字符串，以及不在 fillable 中的字段
                $cleanedRule = [];
                foreach ($rule as $key => $value) {
                    // 跳过 tiers 字段（它会单独处理）
                    if ($key === 'tiers') {
                        continue;
                    }

                    // 只保留 fillable 字段
                    if (in_array($key, $this->fillable)) {
                        // 跳过 null 和空字符串（数据库有默认值）
                        if ($value !== null && $value !== '') {
                            $cleanedRule[$key] = $value;
                        }
                    }
                }

                // 确保 ruleId 存在
                $cleanedRule['ruleId'] = $ruleId;

                // 创建额外规则
                $additionalRuleId = $this->create($cleanedRule);

                // 如果有 tiers 数据，创建层级
                if (isset($rule['tiers']) && !empty($rule['tiers'])) {
                    require_once __DIR__ . '/IbRuleCommissionTier.php';
                    $tierModel = new IbRuleCommissionTier();
                    $tierModel->bulkCreateTiers($additionalRuleId, $rule['tiers']);
                }
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
