<?php
/**
 * 客户会话模型
 */

require_once __DIR__ . '/BaseModel.php';

class ClientSession extends BaseModel {
    protected $table = 'clientSessions';
    protected $primaryKey = 'id';

    protected $fillable = [
        'userId', 'token', 'ipAddress', 'userAgent',
        'lastActivity', 'expiresAt'
    ];

    /**
     * 创建会话
     */
    public function createSession($userId, $options = []) {
        $config = require __DIR__ . '/../config/app.php';
        $rememberMe = $options['rememberMe'] ?? false;

        // 记住我：30天，否则：24小时
        $expiryHours = $rememberMe ? 720 : 24;

        $sessionData = [
            'userId' => $userId,
            'token' => bin2hex(random_bytes(32)),
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'expiresAt' => date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours"))
        ];

        return $this->create($sessionData);
    }

    /**
     * 验证会话
     */
    public function verifySession($sessionId) {
        $sql = "SELECT * FROM {$this->table}
                WHERE id = :id
                AND expiresAt > NOW()
                LIMIT 1";

        return $this->db->fetchOne($sql, ['id' => $sessionId]);
    }

    /**
     * 更新最后活动时间
     */
    public function updateActivity($sessionId) {
        return $this->update($sessionId, [
            'lastActivity' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 删除用户所有会话
     */
    public function deleteUserSessions($userId) {
        $sql = "DELETE FROM {$this->table} WHERE userId = :userId";
        return $this->db->query($sql, ['userId' => $userId]);
    }

    /**
     * 清理过期会话
     */
    public function cleanExpired() {
        $sql = "DELETE FROM {$this->table} WHERE expiresAt < NOW()";
        return $this->db->query($sql);
    }

    /**
     * 获取用户活跃会话
     */
    public function getUserActiveSessions($userId) {
        $sql = "SELECT * FROM {$this->table}
                WHERE userId = :userId
                AND expiresAt > NOW()
                ORDER BY lastActivity DESC";

        return $this->db->fetchAll($sql, ['userId' => $userId]);
    }
}
