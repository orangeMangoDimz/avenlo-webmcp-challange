<?php
/**
 * App 端会话模型
 * 与 clientSessions 解耦：App 端使用 access + refresh 双 token，按设备粒度可吊销。
 */

require_once __DIR__ . '/BaseModel.php';

class AppSession extends BaseModel {
    protected $table = 'appSessions';
    protected $primaryKey = 'id';

    protected $fillable = [
        'userId', 'refreshToken', 'deviceId', 'deviceModel', 'os',
        'appVersion', 'ipAddress', 'userAgent', 'lastActivity',
        'accessExpiresAt', 'refreshExpiresAt', 'revokedAt'
    ];

    /**
     * 创建一条 App 会话
     *
     * 时间字段全部用 MySQL NOW() / DATE_ADD 计算，避免 PHP 与 MySQL 时区不一致
     * 导致刚发的 token 立刻被判过期。
     *
     * @return array ['id' => int, 'refreshToken' => string]
     */
    public function createSession($userId, array $context, $accessTtlSeconds, $refreshTtlSeconds) {
        $refreshToken = bin2hex(random_bytes(48));
        $accessTtl = (int)$accessTtlSeconds;
        $refreshTtl = (int)$refreshTtlSeconds;

        $sql = "INSERT INTO {$this->table}
                (userId, refreshToken, deviceId, deviceModel, os, appVersion,
                 ipAddress, userAgent,
                 lastActivity, createdAt, accessExpiresAt, refreshExpiresAt)
                VALUES (:userId, :refreshToken, :deviceId, :deviceModel, :os, :appVersion,
                        :ipAddress, :userAgent,
                        NOW(), NOW(),
                        DATE_ADD(NOW(), INTERVAL :accessTtl SECOND),
                        DATE_ADD(NOW(), INTERVAL :refreshTtl SECOND))";
        $this->db->query($sql, [
            'userId' => (int)$userId,
            'refreshToken' => $refreshToken,
            'deviceId' => $context['deviceId'] ?? null,
            'deviceModel' => $context['deviceModel'] ?? null,
            'os' => $context['os'] ?? null,
            'appVersion' => $context['appVersion'] ?? null,
            'ipAddress' => $context['ipAddress'] ?? null,
            'userAgent' => $context['userAgent'] ?? null,
            'accessTtl' => $accessTtl,
            'refreshTtl' => $refreshTtl,
        ]);
        $id = (int)$this->db->getConnection()->lastInsertId();

        return ['id' => $id, 'refreshToken' => $refreshToken];
    }

    /**
     * 校验会话是否有效（未撤销且未过期）
     * 给 AppAuthMiddleware 用。
     */
    public function findActive($sessionId) {
        $sql = "SELECT * FROM {$this->table}
                WHERE id = :id
                  AND revokedAt IS NULL
                  AND refreshExpiresAt > NOW()
                LIMIT 1";
        return $this->db->fetchOne($sql, ['id' => (int)$sessionId]);
    }

    public function findByRefreshToken($refreshToken) {
        $sql = "SELECT * FROM {$this->table}
                WHERE refreshToken = :rt
                  AND revokedAt IS NULL
                  AND refreshExpiresAt > NOW()
                LIMIT 1";
        return $this->db->fetchOne($sql, ['rt' => $refreshToken]);
    }

    public function touch($sessionId) {
        $sql = "UPDATE {$this->table} SET lastActivity = NOW() WHERE id = :id";
        return $this->db->query($sql, ['id' => (int)$sessionId]);
    }

    public function revoke($sessionId) {
        $sql = "UPDATE {$this->table} SET revokedAt = NOW() WHERE id = :id AND revokedAt IS NULL";
        return $this->db->query($sql, ['id' => (int)$sessionId]);
    }

    public function revokeAllForUser($userId) {
        $sql = "UPDATE {$this->table}
                SET revokedAt = NOW()
                WHERE userId = :uid AND revokedAt IS NULL";
        return $this->db->query($sql, ['uid' => (int)$userId]);
    }
}
