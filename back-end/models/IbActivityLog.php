<?php
/**
 * IB Activity Log 模型
 * 对应表：ibActivityLog
 */

require_once __DIR__ . '/BaseModel.php';

class IbActivityLog extends BaseModel {
    protected $table = 'ibActivityLog';
    protected $primaryKey = 'id';

    protected $fillable = [
        'ibPartnerId', 'applicationId', 'activityType', 'activityDescription',
        'performedBy', 'performedByType', 'ipAddress', 'metadata'
    ];

    /**
     * 记录活动日志
     */
    public function logActivity($ibPartnerId, $activityType, $description, $performedBy = null, $performedByType = 'admin', $metadata = null, $ipAddress = null) {
        $data = [
            'ibPartnerId' => $ibPartnerId,
            'activityType' => $activityType,
            'activityDescription' => $description,
            'performedBy' => $performedBy,
            'performedByType' => $performedByType,
            'ipAddress' => $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? null,
            'metadata' => $metadata ? json_encode($metadata) : null
        ];

        return $this->create($data);
    }

    /**
     * 记录申请相关活动
     */
    public function logApplicationActivity($applicationId, $activityType, $description, $performedBy = null, $performedByType = 'admin', $metadata = null) {
        $data = [
            'applicationId' => $applicationId,
            'activityType' => $activityType,
            'activityDescription' => $description,
            'performedBy' => $performedBy,
            'performedByType' => $performedByType,
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null,
            'metadata' => $metadata ? json_encode($metadata) : null
        ];

        return $this->create($data);
    }

    /**
     * 获取IB活动日志
     */
    public function getIbActivities($ibPartnerId, $limit = 50) {
        $sql = "SELECT
                    log.*,
                    au.fullName as performedByName,
                    au.username as performedByUsername
                FROM {$this->table} log
                LEFT JOIN adminUsers au ON log.performedBy = au.id AND log.performedByType = 'admin'
                WHERE log.ibPartnerId = :ibPartnerId
                ORDER BY log.createdAt DESC
                LIMIT {$limit}";

        $activities = $this->db->fetchAll($sql, ['ibPartnerId' => $ibPartnerId]);

        // 解析metadata
        foreach ($activities as &$activity) {
            if ($activity['metadata']) {
                $activity['metadata'] = json_decode($activity['metadata'], true);
            }
        }

        return $activities;
    }

    /**
     * 获取申请活动日志
     */
    public function getApplicationActivities($applicationId, $limit = 50) {
        $sql = "SELECT
                    log.*,
                    au.fullName as performedByName,
                    au.username as performedByUsername
                FROM {$this->table} log
                LEFT JOIN adminUsers au ON log.performedBy = au.id AND log.performedByType = 'admin'
                WHERE log.applicationId = :applicationId
                ORDER BY log.createdAt DESC
                LIMIT {$limit}";

        $activities = $this->db->fetchAll($sql, ['applicationId' => $applicationId]);

        // 解析metadata
        foreach ($activities as &$activity) {
            if ($activity['metadata']) {
                $activity['metadata'] = json_decode($activity['metadata'], true);
            }
        }

        return $activities;
    }
}
