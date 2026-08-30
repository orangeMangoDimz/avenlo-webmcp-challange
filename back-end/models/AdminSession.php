<?php
/**
 * 管理员会话模型
 */

require_once __DIR__ . '/BaseModel.php';

class AdminSession extends BaseModel {
    protected $table = 'adminSessions';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id', 'userId', 'ipAddress', 'userAgent', 'payload',
        'lastActivity', 'rememberToken', 'expiresAt'
    ];

    /**
     * 创建会话
     */
    public function createSession($userId, $payload = []) {
        $sessionId = $this->generateSessionId();

        $data = [
            'id' => $sessionId,
            'userId' => $userId,
            'ipAddress' => $this->getClientIp(),
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'payload' => json_encode($payload),
            'lastActivity' => time(),
            'createdAt' => date('Y-m-d H:i:s')
        ];

        $this->db->insert($this->table, $data);
        return $sessionId;
    }

    /**
     * 更新会话活动时间
     */
    public function updateActivity($sessionId) {
        return $this->db->update(
            $this->table,
            ['lastActivity' => time()],
            'id = :id',
            ['id' => $sessionId]
        );
    }

    /**
     * 获取用户所有会话
     */
    public function getUserSessions($userId) {
        return $this->findAll(['userId' => $userId], 'lastActivity DESC');
    }

    /**
     * 删除过期会话
     */
    public function deleteExpiredSessions() {
        $config = require __DIR__ . '/../config/app.php';
        $timeout = $config['security']['session_timeout'] * 60; // 转换为秒
        $expiredTime = time() - $timeout;

        $sql = "DELETE FROM {$this->table} WHERE lastActivity < :expiredTime";
        return $this->db->query($sql, ['expiredTime' => $expiredTime]);
    }

    /**
     * 删除用户所有会话（用于登出）
     */
    public function deleteUserSessions($userId) {
        return $this->db->delete($this->table, 'userId = :userId', ['userId' => $userId]);
    }

    /**
     * 生成会话ID
     */
    private function generateSessionId() {
        return bin2hex(random_bytes(32));
    }

    /**
     * 获取客户端IP
     */
    private function getClientIp() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return $_SERVER[$key];
            }
        }
        return '0.0.0.0';
    }
}
