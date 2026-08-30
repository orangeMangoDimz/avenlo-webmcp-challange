<?php
/**
 * IB Rule Commission Tier 模型
 * 对应表：ibRuleCommissionTiers
 */

require_once __DIR__ . '/BaseModel.php';

class IbRuleCommissionTier extends BaseModel {
    protected $table = 'ibRuleCommissionTiers';
    protected $primaryKey = 'id';

    protected $fillable = [
        'additionalRuleId', 'tierLevel', 'tierName',
        'commissionRate', 'minimumVolume', 'maximumVolume'
    ];

    /**
     * 批量创建层级
     */
    public function bulkCreateTiers($additionalRuleId, $tiers) {
        $this->db->beginTransaction();

        try {
            // 先删除现有层级
            $this->db->query(
                "DELETE FROM {$this->table} WHERE additionalRuleId = :additionalRuleId",
                ['additionalRuleId' => $additionalRuleId]
            );

            // 创建新层级
            foreach ($tiers as $tier) {
                $tier['additionalRuleId'] = $additionalRuleId;
                $this->create($tier);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
