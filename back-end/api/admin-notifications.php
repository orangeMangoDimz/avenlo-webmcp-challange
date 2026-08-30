<?php
/**
 * 后台管理员通知 API 路由
 */

require_once __DIR__ . '/../controllers/AdminNotificationController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new AdminNotificationController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 检查认证（仅管理员）
AuthMiddleware::authenticate();

try {
    // 解析路径参数
    $pathParts = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    if (empty($id)) {
        // GET /api/admin/notifications
        if ($method === 'GET') {
            $controller->index();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id === 'unread-count') {
        // GET /api/admin/notifications/unread-count
        if ($method === 'GET') {
            $controller->unreadCount();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id === 'mark-read') {
        // POST /api/admin/notifications/mark-read
        if ($method === 'POST') {
            $controller->markAsReadBatch();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id === 'mark-all-read') {
        // POST /api/admin/notifications/mark-all-read
        if ($method === 'POST') {
            $controller->markAllAsRead();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif (is_numeric($id)) {
        // POST /api/admin/notifications/{id}/read
        if ($action === 'read' && $method === 'POST') {
            $controller->markAsRead($id);
        } else {
            Response::error('Endpoint not found', 404);
        }
    } else {
        Response::error('Endpoint not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
