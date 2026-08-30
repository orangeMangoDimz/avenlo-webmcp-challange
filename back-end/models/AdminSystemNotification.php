<?php
/**
 * 管理员系统通知模型
 */

require_once __DIR__ . '/BaseModel.php';

class AdminSystemNotification extends BaseModel {
    protected $table = 'adminSystemNotifications';
    protected $primaryKey = 'id';

    protected $fillable = [
        'notificationId',
        'type',
        'metadata',
        'adminId',
        'subject',
        'message',
        'isRead',
        'readAt',
        'createdAt'
    ];

    /**
     * 获取管理员通知分页结果
     * adminId=0 表示所有管理员都可以查看的通知
     */
    public function getPaginatedByAdmin($adminId, $limit = 10, $offset = 0) {
        $limit = max(1, (int)$limit);
        $offset = max(0, (int)$offset);

        // adminId=0 表示所有管理员都可以查看，需要查询 adminId = 0 或 adminId = 当前管理员ID
        if ($adminId == 0) {
            $sql = "SELECT SQL_CALC_FOUND_ROWS id, notificationId, type, metadata, subject, message, isRead, readAt, createdAt
                    FROM {$this->table}
                    WHERE adminId = 0
                    ORDER BY createdAt DESC
                    LIMIT {$limit} OFFSET {$offset}";
            $params = [];
        } else {
            $sql = "SELECT SQL_CALC_FOUND_ROWS id, notificationId, type, metadata, subject, message, isRead, readAt, createdAt
                    FROM {$this->table}
                    WHERE adminId = :adminId OR adminId = 0
                    ORDER BY createdAt DESC
                    LIMIT {$limit} OFFSET {$offset}";
            $params = ['adminId' => $adminId];
        }

        $items = $this->db->fetchAll($sql, $params);

        $totalResult = $this->db->fetchOne("SELECT FOUND_ROWS() as total");
        $total = (int)($totalResult['total'] ?? 0);

        return [$items, $total];
    }

    /**
     * 获取未读数量
     * adminId=0 表示所有管理员都可以查看的通知
     */
    public function getUnreadCount($adminId) {
        // adminId=0 表示所有管理员都可以查看，需要查询 adminId = 0 或 adminId = 当前管理员ID
        if ($adminId == 0) {
            $sql = "SELECT COUNT(*) as unreadCount
                    FROM {$this->table}
                    WHERE adminId = 0 AND isRead = 0";
            $params = [];
        } else {
            $sql = "SELECT COUNT(*) as unreadCount
                    FROM {$this->table}
                    WHERE (adminId = :adminId OR adminId = 0) AND isRead = 0";
            $params = ['adminId' => $adminId];
        }

        $result = $this->db->fetchOne($sql, $params);
        return (int)($result['unreadCount'] ?? 0);
    }

    /**
     * 标记通知为已读
     * adminId=0 表示所有管理员都可以查看的通知
     * @param int $id adminSystemNotifications 表的主键 id
     * @param int $adminId 管理员ID
     */
    public function markAsRead($id, $adminId) {
        // adminId=0 表示所有管理员都可以查看，需要匹配 adminId = 0 或 adminId = 当前管理员ID
        if ($adminId == 0) {
            $sql = "UPDATE {$this->table}
                    SET isRead = 1,
                        readAt = NOW()
                    WHERE id = :id
                      AND adminId = 0";
            $params = ['id' => $id];
        } else {
            $sql = "UPDATE {$this->table}
                    SET isRead = 1,
                        readAt = NOW()
                    WHERE id = :id
                      AND (adminId = :adminId OR adminId = 0)";
            $params = [
                'id' => $id,
                'adminId' => $adminId
            ];
        }

        $stmt = $this->db->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * 根据通知记录ID数组标记为已读
     * adminId=0 表示所有管理员都可以查看的通知
     */
    public function markAsReadByIds(array $ids, $adminId) {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));
        if (empty($ids)) {
            return 0;
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $key = "id{$index}";
            $placeholders[] = ":{$key}";
            $params[$key] = $id;
        }

        // adminId=0 表示所有管理员都可以查看，需要匹配 adminId = 0 或 adminId = 当前管理员ID
        if ($adminId == 0) {
            $sql = "UPDATE {$this->table}
                    SET isRead = 1,
                        readAt = CASE WHEN readAt IS NULL THEN NOW() ELSE readAt END
                    WHERE adminId = 0
                      AND id IN (" . implode(',', $placeholders) . ")";
        } else {
            $params['adminId'] = $adminId;
            $sql = "UPDATE {$this->table}
                    SET isRead = 1,
                        readAt = CASE WHEN readAt IS NULL THEN NOW() ELSE readAt END
                    WHERE (adminId = :adminId OR adminId = 0)
                      AND id IN (" . implode(',', $placeholders) . ")";
        }

        $stmt = $this->db->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * 将指定管理员所有通知标记为已读
     * adminId=0 表示所有管理员都可以查看的通知
     */
    public function markAllAsRead($adminId) {
        // adminId=0 表示所有管理员都可以查看，需要匹配 adminId = 0 或 adminId = 当前管理员ID
        if ($adminId == 0) {
            $sql = "UPDATE {$this->table}
                    SET isRead = 1,
                        readAt = CASE WHEN readAt IS NULL THEN NOW() ELSE readAt END
                    WHERE adminId = 0 AND isRead = 0";
            $params = [];
        } else {
            $sql = "UPDATE {$this->table}
                    SET isRead = 1,
                        readAt = CASE WHEN readAt IS NULL THEN NOW() ELSE readAt END
                    WHERE (adminId = :adminId OR adminId = 0) AND isRead = 0";
            $params = ['adminId' => $adminId];
        }

        $stmt = $this->db->query($sql, $params);
        return $stmt->rowCount();
    }
}
