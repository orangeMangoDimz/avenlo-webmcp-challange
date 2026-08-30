<?php
/**
 * 管理员通知主表模型
 */

require_once __DIR__ . '/BaseModel.php';

class AdminNotification extends BaseModel {
    protected $table = 'adminNotifications';
    protected $primaryKey = 'id';

    protected $fillable = [
        'adminId',
        'subject',
        'message',
        'priority',
        'scheduleType',
        'scheduledAt',
        'status',
        'emailTemplate',
        'createdBy',
        'createdAt',
        'updatedAt'
    ];

    /**
     * 根据状态和时间获取到期的定时通知
     */
    public function getDueScheduledNotifications($limit = 50) {
        $sql = "SELECT *
                FROM {$this->table}
                WHERE scheduleType = 'scheduled'
                  AND status = 'pending'
                  AND scheduledAt IS NOT NULL
                  AND scheduledAt <= NOW()
                ORDER BY scheduledAt ASC
                LIMIT {$limit}";

        return $this->db->fetchAll($sql);
    }
}
