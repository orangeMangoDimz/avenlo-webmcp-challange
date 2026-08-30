<?php
/**
 * Admin Notification Controller
 * 处理后台管理员通知相关接口
 */

require_once __DIR__ . '/../models/AdminSystemNotification.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';

class AdminNotificationController {
    private $systemNotificationModel;

    public function __construct() {
        $this->systemNotificationModel = new AdminSystemNotification();
    }

    /**
     * 获取管理员通知列表
     * GET /api/admin/notifications
     */
    public function index() {
        $admin = $this->requireAdmin();
        $adminId = $admin['userId'];

        $limit = (int)($_GET['limit'] ?? 10);
        $offset = (int)($_GET['offset'] ?? 0);

        list($notifications, $total) = $this->systemNotificationModel->getPaginatedByAdmin($adminId, $limit, $offset);

        // 处理 metadata JSON
        foreach ($notifications as &$notification) {
            if ($notification['metadata']) {
                $decoded = json_decode($notification['metadata'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $notification['metadata'] = $decoded;
                }
            }
        }

        Response::success([
            'notifications' => $notifications,
            'total' => $total,
            'unreadCount' => $this->systemNotificationModel->getUnreadCount($adminId)
        ]);
    }

    /**
     * 获取未读通知数量
     * GET /api/admin/notifications/unread-count
     */
    public function unreadCount() {
        $admin = $this->requireAdmin();
        $adminId = $admin['userId'];

        $count = $this->systemNotificationModel->getUnreadCount($adminId);

        Response::success(['unreadCount' => $count]);
    }

    /**
     * 标记通知为已读
     * POST /api/admin/notifications/{id}/read
     */
    public function markAsRead($id) {
        $admin = $this->requireAdmin();
        $adminId = $admin['userId'];

        $this->systemNotificationModel->markAsRead($id, $adminId);

        Response::success(null, 'Notification marked as read');
    }

    /**
     * 批量标记通知为已读
     * POST /api/admin/notifications/mark-read
     */
    public function markAsReadBatch() {
        $admin = $this->requireAdmin();
        $adminId = $admin['userId'];

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            Response::validationError(['ids' => ['Notification IDs array is required']]);
        }

        $count = $this->systemNotificationModel->markAsReadByIds($ids, $adminId);

        Response::success(['markedCount' => $count], 'Notifications marked as read');
    }

    /**
     * 标记所有通知为已读
     * POST /api/admin/notifications/mark-all-read
     */
    public function markAllAsRead() {
        $admin = $this->requireAdmin();
        $adminId = $admin['userId'];

        $count = $this->systemNotificationModel->markAllAsRead($adminId);

        Response::success(['markedCount' => $count], 'All notifications marked as read');
    }

    /**
     * 要求管理员认证
     */
    private function requireAdmin() {
        $payload = JWT::getPayload();

        if (!$payload || ($payload['type'] ?? '') !== 'admin') {
            Response::forbidden('Admin authentication required');
        }

        $userId = $payload['userId'] ?? null;
        if (!$userId) {
            Response::unauthorized('Invalid token payload');
        }

        return ['userId' => $userId];
    }
}
