<?php
/**
 * 客户密码重置模型
 */

require_once __DIR__ . '/BaseModel.php';

class ClientPasswordReset extends BaseModel {
    protected $table = 'clientPasswordResets';
    protected $primaryKey = 'id';

    protected $fillable = [
        'email', 'token', 'expiresAt', 'used', 'usedAt', 'ipAddress'
    ];

    /**
     * 创建重置令牌
     */
    public function createToken($email) {
        $token = bin2hex(random_bytes(32));
        $config = require __DIR__ . '/../config/app.php';
        $expiryHours = $config['auth']['password_reset_expiry'] ?? 24;

        $this->create([
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
                AND used = 0
                AND expiresAt > NOW()
                LIMIT 1";

        return $this->db->fetchOne($sql, ['token' => $token]);
    }

    /**
     * 标记为已使用
     */
    public function markAsUsed($id) {
        return $this->update($id, [
            'used' => 1,
            'usedAt' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 清理过期令牌
     */
    public function cleanExpired() {
        $sql = "DELETE FROM {$this->table} WHERE expiresAt < NOW()";
        return $this->db->query($sql);
    }
}
