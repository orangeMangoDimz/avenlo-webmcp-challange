<?php
/**
 * 客户通知渠道投递模型
 */

require_once __DIR__ . '/BaseModel.php';

class ClientNotificationDelivery extends BaseModel {
    protected $table = 'clientNotificationDeliveries';
    protected $primaryKey = 'id';

    protected $fillable = [
        'notificationId',
        'channel',
        'status',
        'errorMessage',
        'sentAt',
        'createdAt'
    ];

    /**
     * 获取通知的投递记录
     */
    public function getDeliveriesByNotification($notificationId) {
        $sql = "SELECT * FROM {$this->table}
                WHERE notificationId = :notificationId";

        return $this->db->fetchAll($sql, ['notificationId' => $notificationId]);
    }
}
