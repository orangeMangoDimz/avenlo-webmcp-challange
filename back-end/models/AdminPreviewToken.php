<?php
/**
 * 预览 Token 模型（View as client）
 * 表：admin_preview_tokens
 */

require_once __DIR__ . '/BaseModel.php';

class AdminPreviewToken extends BaseModel {
    protected $table = 'admin_preview_tokens';
    protected $primaryKey = 'id';
    protected $fillable = ['token', 'clientUserId', 'adminUserId', 'expiresAt', 'revokedAt'];

    /** 有效期：1 天（秒） */
    const TTL_SECONDS = 86400;

    /**
     * 生成随机 token 字符串
     */
    public static function generateToken() {
        return bin2hex(random_bytes(32));
    }

    /**
     * 创建预览 token
     * @param int $clientUserId 被模拟的客户端用户 id（leadId）
     * @param int|null $adminUserId 生成者后台用户 id
     * @return array ['token' => string, 'expiresAt' => string, 'expiresIn' => int]
     */
    public function createToken($clientUserId, $adminUserId = null) {
        $expiresAt = date('Y-m-d H:i:s', time() + self::TTL_SECONDS);
        $token = self::generateToken();

        $this->db->insert($this->table, [
            'token' => $token,
            'clientUserId' => (int) $clientUserId,
            'adminUserId' => $adminUserId ? (int) $adminUserId : null,
            'expiresAt' => $expiresAt,
            'revokedAt' => null,
        ]);

        return [
            'token' => $token,
            'expiresAt' => $expiresAt,
            'expiresIn' => self::TTL_SECONDS,
        ];
    }

    /**
     * 校验 token 并返回 clientUserId（未过期且未回收）
     * @param string $token
     * @return array|null ['clientUserId' => int, 'expiresAt' => string] 或 null
     */
    public function validateToken($token) {
        if (empty($token)) {
            return null;
        }
        $sql = "SELECT clientUserId, expiresAt FROM {$this->table}
                WHERE token = :token AND revokedAt IS NULL AND expiresAt > NOW() LIMIT 1";
        $row = $this->db->fetchOne($sql, ['token' => $token]);
        return $row ? [
            'clientUserId' => (int) $row['clientUserId'],
            'expiresAt' => $row['expiresAt'],
        ] : null;
    }

    /**
     * 回收 token（关闭预览页时调用）
     * @param string $token
     * @return bool 是否找到并更新
     */
    public function revokeToken($token) {
        if (empty($token)) {
            return false;
        }
        $sql = "UPDATE {$this->table} SET revokedAt = NOW() WHERE token = :token AND revokedAt IS NULL";
        $stmt = $this->db->query($sql, ['token' => $token]);
        return $stmt && $stmt->rowCount() > 0;
    }
}
