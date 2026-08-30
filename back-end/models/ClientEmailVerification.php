<?php
/**
 * 客户邮箱验证模型
 */

require_once __DIR__ . '/BaseModel.php';

class ClientEmailVerification extends BaseModel {
    protected $table = 'clientEmailVerifications';
    protected $primaryKey = 'id';

    protected $fillable = [
        'userId', 'email', 'token', 'expiresAt',
        'verified', 'verifiedAt', 'ipAddress'
    ];

    /**
     * 创建验证令牌
     */
    public function createToken($userId, $email) {
        $token = bin2hex(random_bytes(32));

        // 获取设置中的过期时间
        require_once __DIR__ . '/EmailVerificationSettings.php';
        $settingsModel = new EmailVerificationSettings();
        $settings = $settingsModel->getSettings();
        $expiryHours = $settings['verificationLinkExpiryHours'] ?? 24;

        $this->create([
            'userId' => $userId,
            'email' => $email,
            'token' => $token,
            'expiresAt' => date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours")),
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ]);

        return $token;
    }

    /**
     * 验证令牌
     */
    public function verifyToken($token) {
        $sql = "SELECT * FROM {$this->table}
                WHERE token = :token
                AND verified = 0
                AND expiresAt > NOW()
                LIMIT 1";

        return $this->db->fetchOne($sql, ['token' => $token]);
    }

    /**
     * 标记为已验证
     */
    public function markAsVerified($id) {
        return $this->update($id, [
            'verified' => 1,
            'verifiedAt' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 检查是否可以重发
     */
    public function canResend($userId) {
        require_once __DIR__ . '/EmailVerificationSettings.php';
        $settingsModel = new EmailVerificationSettings();
        $settings = $settingsModel->getSettings();
        $cooldownMinutes = $settings['resendCooldownMinutes'] ?? 5;

        $sql = "SELECT * FROM {$this->table}
                WHERE userId = :userId
                AND createdAt > DATE_SUB(NOW(), INTERVAL {$cooldownMinutes} MINUTE)
                ORDER BY createdAt DESC
                LIMIT 1";

        $recent = $this->db->fetchOne($sql, ['userId' => $userId]);
        return !$recent;
    }

    /**
     * 清理过期令牌
     */
    public function cleanExpired() {
        $sql = "DELETE FROM {$this->table} WHERE expiresAt < NOW()";
        return $this->db->query($sql);
    }
}
