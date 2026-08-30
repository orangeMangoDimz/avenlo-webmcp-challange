<?php
/**
 * 管理员登录日志模型
 */

require_once __DIR__ . '/BaseModel.php';

class AdminLoginLog extends BaseModel {
    protected $table = 'adminLoginLogs';
    protected $primaryKey = 'id';

    protected $fillable = [
        'userId', 'username', 'email', 'loginStatus', 'failureReason',
        'ipAddress', 'userAgent', 'deviceType', 'browser', 'platform',
        'locationCountry', 'locationCity', 'rememberMe', 'sessionId'
    ];

    /**
     * 记录登录日志
     */
    public function logLogin($data) {
        // 解析User Agent
        $userAgent = $data['userAgent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
        $parsed = $this->parseUserAgent($userAgent);

        $logData = array_merge($data, [
            'ipAddress' => $data['ipAddress'] ?? $this->getClientIp(),
            'userAgent' => $userAgent,
            'deviceType' => $parsed['deviceType'],
            'browser' => $parsed['browser'],
            'platform' => $parsed['platform'],
            'createdAt' => date('Y-m-d H:i:s')
        ]);

        return $this->create($logData);
    }

    /**
     * 获取用户登录历史
     */
    public function getUserLoginHistory($userId, $limit = 10) {
        $sql = "SELECT * FROM {$this->table}
                WHERE userId = :userId
                ORDER BY createdAt DESC
                LIMIT {$limit}";

        return $this->db->fetchAll($sql, ['userId' => $userId]);
    }

    /**
     * 获取最近登录记录
     */
    public function getRecentLogins($limit = 50) {
        return $this->findAll([], 'createdAt DESC', $limit);
    }

    /**
     * 获取指定用户当日失败登录次数（用于每日限制判断）
     * @param int $userId 用户ID
     * @return int 当日失败次数
     */
    public function getTodayFailedCount($userId) {
        $sql = "SELECT COUNT(*) as cnt FROM {$this->table}
                WHERE userId = :userId
                AND loginStatus = 'failed'
                AND DATE(createdAt) = CURDATE()";
        $row = $this->db->fetchOne($sql, ['userId' => $userId]);
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * 获取失败登录记录
     */
    public function getFailedLogins($startDate = null, $endDate = null) {
        $sql = "SELECT * FROM {$this->table}
                WHERE loginStatus = 'failed'";
        $params = [];

        if ($startDate) {
            $sql .= " AND createdAt >= :startDate";
            $params['startDate'] = $startDate;
        }

        if ($endDate) {
            $sql .= " AND createdAt <= :endDate";
            $params['endDate'] = $endDate;
        }

        $sql .= " ORDER BY createdAt DESC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * 获取登录统计
     */
    public function getLoginStats($days = 7) {
        $sql = "SELECT
                    DATE(createdAt) as date,
                    COUNT(*) as total,
                    SUM(CASE WHEN loginStatus = 'success' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN loginStatus = 'failed' THEN 1 ELSE 0 END) as failed
                FROM {$this->table}
                WHERE createdAt >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY DATE(createdAt)
                ORDER BY date DESC";

        return $this->db->fetchAll($sql, ['days' => $days]);
    }

    /**
     * 获取客户端IP
     */
    private function getClientIp() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED',
                   'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                $ip = $_SERVER[$key];
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * 解析User Agent
     */
    private function parseUserAgent($userAgent) {
        $result = [
            'deviceType' => 'desktop',
            'browser' => 'Unknown',
            'platform' => 'Unknown'
        ];

        // 检测设备类型
        if (preg_match('/mobile|android|iphone|ipad|tablet/i', $userAgent)) {
            $result['deviceType'] = preg_match('/tablet|ipad/i', $userAgent) ? 'tablet' : 'mobile';
        }

        // 检测浏览器
        if (preg_match('/Edge/i', $userAgent)) {
            $result['browser'] = 'Edge';
        } elseif (preg_match('/Chrome/i', $userAgent)) {
            $result['browser'] = 'Chrome';
        } elseif (preg_match('/Safari/i', $userAgent)) {
            $result['browser'] = 'Safari';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $result['browser'] = 'Firefox';
        } elseif (preg_match('/MSIE|Trident/i', $userAgent)) {
            $result['browser'] = 'IE';
        }

        // 检测操作系统
        if (preg_match('/Windows/i', $userAgent)) {
            $result['platform'] = 'Windows';
        } elseif (preg_match('/Mac/i', $userAgent)) {
            $result['platform'] = 'MacOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $result['platform'] = 'Linux';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $result['platform'] = 'Android';
        } elseif (preg_match('/iOS|iPhone|iPad/i', $userAgent)) {
            $result['platform'] = 'iOS';
        }

        return $result;
    }
}
