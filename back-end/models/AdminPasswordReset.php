<?php
/**
 * 密码重置令牌模型
 */

require_once __DIR__ . '/BaseModel.php';

class AdminPasswordReset extends BaseModel {
    protected $table = 'adminPasswordResets';
    protected $primaryKey = 'id';

    protected $fillable = [
        'userId', 'email', 'token', 'tokenPlain',
        'expiresAt', 'usedAt', 'ipAddress', 'userAgent'
    ];

    /**
     * 创建重置令牌
     */
    public function createToken($userId, $email) {
        $config = require __DIR__ . '/../config/app.php';
        $expiry = $config['security']['password_reset_expiry'];

        $plainToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainToken);

        $data = [
            'userId' => $userId,
            'email' => $email,
            'token' => $hashedToken,
            'tokenPlain' => $plainToken,  // 仅用于邮件发送
            'expiresAt' => date('Y-m-d H:i:s', time() + ($expiry * 60)),
            'ipAddress' => $this->getClientIp(),
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];

        $this->create($data);
        return $plainToken;
    }

    /**
     * 验证令牌
     */
    public function verifyToken($plainToken) {
        $hashedToken = hash('sha256', $plainToken);

        $sql = "SELECT * FROM {$this->table}
                WHERE token = :token
                AND usedAt IS NULL
                AND expiresAt > NOW()
                LIMIT 1";

        return $this->db->fetchOne($sql, ['token' => $hashedToken]);
    }

    /**
     * 标记令牌为已使用
     */
    public function markAsUsed($tokenId) {
        return $this->update($tokenId, [
            'usedAt' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 删除用户所有重置令牌
     */
    public function deleteUserTokens($userId) {
        return $this->db->delete($this->table, 'userId = :userId', ['userId' => $userId]);
    }

    /**
     * 清理过期令牌
     */
    public function deleteExpiredTokens() {
        $sql = "DELETE FROM {$this->table} WHERE expiresAt < NOW()";
        return $this->db->query($sql);
    }

    /**
     * 获取客户端IP
     */
    private function getClientIp() {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
