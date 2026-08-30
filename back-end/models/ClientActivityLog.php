<?php
/**
 * 客户活动日志模型
 */

require_once __DIR__ . '/BaseModel.php';

class ClientActivityLog extends BaseModel {
    protected $table = 'clientActivityLog';
    protected $primaryKey = 'id';

    protected $fillable = [
        'userId', 'activityType', 'description',
        'ipAddress', 'userAgent', 'metadata'
    ];

    /**
     * 记录活动
     */
    public function logActivity($data) {
        $logData = array_merge([
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ], $data);

        // 如果metadata是数组，转换为JSON
        if (isset($logData['metadata']) && is_array($logData['metadata'])) {
            $logData['metadata'] = json_encode($logData['metadata']);
        }

        return $this->create($logData);
    }

    /**
     * 记录登录
     */
    public function logLogin($userId, $success = true, $description = null) {
        return $this->logActivity([
            'userId' => $userId,
            'activityType' => 'login',
            'description' => $description ?? ($success ? 'User logged in successfully' : 'Login failed')
        ]);
    }

    /**
     * 记录登出
     */
    public function logLogout($userId) {
        return $this->logActivity([
            'userId' => $userId,
            'activityType' => 'logout',
            'description' => 'User logged out'
        ]);
    }

    /**
     * 记录注册
     */
    public function logRegistration($userId, $description = null) {
        return $this->logActivity([
            'userId' => $userId,
            'activityType' => 'register',
            'description' => $description ?? 'New user registered'
        ]);
    }

    /**
     * 记录密码重置
     */
    public function logPasswordReset($userId, $description = null) {
        return $this->logActivity([
            'userId' => $userId,
            'activityType' => 'password_reset',
            'description' => $description ?? 'Password was reset'
        ]);
    }

    /**
     * 获取用户活动
     */
    public function getUserActivity($userId, $page = 1, $perPage = 50) {
        return $this->paginate($page, $perPage, ['userId' => $userId], 'createdAt DESC');
    }

    /**
     * 获取最近活动
     */
    public function getRecentActivity($limit = 100, $activityType = null) {
        $conditions = [];
        if ($activityType) {
            $conditions['activityType'] = $activityType;
        }

        return $this->findAll($conditions, 'createdAt DESC', $limit);
    }

    /**
     * 按活动类型统计
     */
    public function getStatsByType($startDate = null, $endDate = null) {
        $sql = "SELECT activityType, COUNT(*) as count
                FROM {$this->table}";

        $params = [];
        $where = [];

        if ($startDate) {
            $where[] = "createdAt >= :startDate";
            $params['startDate'] = $startDate;
        }

        if ($endDate) {
            $where[] = "createdAt <= :endDate";
            $params['endDate'] = $endDate;
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " GROUP BY activityType ORDER BY count DESC";

        return $this->db->fetchAll($sql, $params);
    }
}
