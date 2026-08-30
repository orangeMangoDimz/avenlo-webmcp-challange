<?php
/**
 * 登录设置变更日志模型
 */

require_once __DIR__ . '/BaseModel.php';

class LoginSettingsChangeLog extends BaseModel {
    protected $table = 'loginSettingsChangeLog';
    protected $primaryKey = 'id';

    protected $fillable = [
        'settingType', 'settingName', 'oldValue',
        'newValue', 'changedBy', 'ipAddress'
    ];

    /**
     * 记录设置变更
     */
    public function logChange($data) {
        $logData = array_merge([
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ], $data);

        // 如果值是数组或对象，转换为JSON
        if (isset($logData['oldValue']) && !is_string($logData['oldValue'])) {
            $logData['oldValue'] = json_encode($logData['oldValue']);
        }
        if (isset($logData['newValue']) && !is_string($logData['newValue'])) {
            $logData['newValue'] = json_encode($logData['newValue']);
        }

        return $this->create($logData);
    }

    /**
     * 获取设置变更历史
     */
    public function getChangeHistory($settingType = null, $page = 1, $perPage = 50) {
        $conditions = [];
        if ($settingType) {
            $conditions['settingType'] = $settingType;
        }

        return $this->paginate($page, $perPage, $conditions, 'createdAt DESC');
    }

    /**
     * 获取管理员的变更记录
     */
    public function getAdminChanges($adminUserId, $page = 1, $perPage = 50) {
        return $this->paginate($page, $perPage, ['changedBy' => $adminUserId], 'createdAt DESC');
    }

    /**
     * 按设置类型统计变更
     */
    public function getStatsByType() {
        $sql = "SELECT settingType, COUNT(*) as count
                FROM {$this->table}
                GROUP BY settingType
                ORDER BY count DESC";

        return $this->db->fetchAll($sql);
    }

    /**
     * 获取最近变更
     */
    public function getRecentChanges($limit = 50) {
        return $this->findAll([], 'createdAt DESC', $limit);
    }
}
