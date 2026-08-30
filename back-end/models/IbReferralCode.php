<?php
/**
 * IB Referral Code 模型
 * 对应表：ibReferralCodes
 */

require_once __DIR__ . '/BaseModel.php';

class IbReferralCode extends BaseModel {
    protected $table = 'ibReferralCodes';
    protected $primaryKey = 'id';

    protected $fillable = [
        'ibPartnerId', 'referralCode', 'codeName', 'codeType',
        'isActive', 'usageCount', 'maxUsageLimit', 'expiresAt'
    ];

    /**
     * 根据推荐码获取信息
     */
    public function getByReferralCode($referralCode) {
        $sql = "SELECT
                    rc.*,
                    ib.ibCode,
                    ib.companyName,
                    ib.status as ibStatus
                FROM {$this->table} rc
                INNER JOIN ibPartners ib ON rc.ibPartnerId = ib.id
                WHERE rc.referralCode = :referralCode
                LIMIT 1";

        return $this->db->fetchOne($sql, ['referralCode' => $referralCode]);
    }

    /**
     * 验证推荐码是否有效
     */
    public function validateReferralCode($referralCode) {
        $code = $this->getByReferralCode($referralCode);

        if (!$code) {
            return ['valid' => false, 'reason' => 'Referral code not found'];
        }

        if (!$code['isActive']) {
            return ['valid' => false, 'reason' => 'Referral code is inactive'];
        }

        if ($code['expiresAt'] && strtotime($code['expiresAt']) < time()) {
            return ['valid' => false, 'reason' => 'Referral code expired'];
        }

        if ($code['maxUsageLimit'] && $code['usageCount'] >= $code['maxUsageLimit']) {
            return ['valid' => false, 'reason' => 'Referral code usage limit reached'];
        }

        if ($code['ibStatus'] !== 'active') {
            return ['valid' => false, 'reason' => 'IB partner is not active'];
        }

        return ['valid' => true, 'code' => $code];
    }

    /**
     * 增加使用次数
     */
    public function incrementUsage($referralCodeId) {
        $sql = "UPDATE {$this->table}
                SET usageCount = usageCount + 1
                WHERE id = :id";

        return $this->db->execute($sql, ['id' => $referralCodeId]);
    }
}
