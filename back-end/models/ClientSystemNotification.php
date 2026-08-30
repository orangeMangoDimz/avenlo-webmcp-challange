<?php
/**
 * 客户系统通知模型
 */

require_once __DIR__ . '/BaseModel.php';

class ClientSystemNotification extends BaseModel {
    protected $table = 'clientSystemNotifications';
    protected $primaryKey = 'id';

    protected $fillable = [
        'notificationId',
        'type',
        'metadata',
        'clientId',
        'subject',
        'message',
        'isRead',
        'readAt',
        'createdAt'
    ];

    /**
     * 获取客户端通知分页结果
     */
    public function getPaginatedByClient($clientId, $limit = 10, $offset = 0) {
        $limit = max(1, (int)$limit);
        $offset = max(0, (int)$offset);

        $sql = "SELECT SQL_CALC_FOUND_ROWS id, notificationId, type, metadata, subject, message, isRead, readAt, createdAt
                FROM {$this->table}
                WHERE clientId = :clientId
                ORDER BY createdAt DESC
                LIMIT {$limit} OFFSET {$offset}";

        $items = $this->db->fetchAll($sql, [
            'clientId' => $clientId
        ]);

        $totalResult = $this->db->fetchOne("SELECT FOUND_ROWS() as total");
        $total = (int)($totalResult['total'] ?? 0);

        return [$items, $total];
    }

    /**
     * 获取未读数量
     */
    public function getUnreadCount($clientId) {
        $sql = "SELECT COUNT(*) as unreadCount
                FROM {$this->table}
                WHERE clientId = :clientId AND isRead = 0";

        $result = $this->db->fetchOne($sql, ['clientId' => $clientId]);
        return (int)($result['unreadCount'] ?? 0);
    }

    /**
     * 标记通知为已读
     */
    public function markAsRead($notificationId, $clientId) {
        $sql = "UPDATE {$this->table}
                SET isRead = 1,
                    readAt = NOW()
                WHERE notificationId = :notificationId
                  AND clientId = :clientId";

        return $this->db->execute($sql, [
            'notificationId' => $notificationId,
            'clientId' => $clientId
        ]);
    }

    /**
     * 根据通知记录ID数组标记为已读
     */
    public function markAsReadByIds(array $ids, $clientId) {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));
        if (empty($ids)) {
            return 0;
        }

        $placeholders = [];
        $params = ['clientId' => $clientId];
        foreach ($ids as $index => $id) {
            $key = "id{$index}";
            $placeholders[] = ":{$key}";
            $params[$key] = $id;
        }

        $sql = "UPDATE {$this->table}
                SET isRead = 1,
                    readAt = CASE WHEN readAt IS NULL THEN NOW() ELSE readAt END
                WHERE clientId = :clientId
                  AND id IN (" . implode(',', $placeholders) . ")";

        $stmt = $this->db->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * 将指定客户端所有通知标记为已读
     */
    public function markAllAsRead($clientId) {
        $sql = "UPDATE {$this->table}
                SET isRead = 1,
                    readAt = CASE WHEN readAt IS NULL THEN NOW() ELSE readAt END
                WHERE clientId = :clientId AND isRead = 0";

        $stmt = $this->db->query($sql, ['clientId' => $clientId]);
        return $stmt->rowCount();
    }
}
