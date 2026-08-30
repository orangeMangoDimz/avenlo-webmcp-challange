<?php
/**
 * 管理员通知渠道表模型
 */

require_once __DIR__ . '/BaseModel.php';

class AdminNotificationDelivery extends BaseModel {
    protected $table = 'adminNotificationDeliveries';
    protected $primaryKey = 'id';

    protected $fillable = [
        'notificationId',
        'channel',
        'status',
        'errorMessage',
        'sentAt',
        'createdAt'
    ];
}
