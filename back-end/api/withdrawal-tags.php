<?php
/**
 * Withdrawal Tags API 路由
 */

require_once __DIR__ . '/../controllers/WithdrawalTagController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new WithdrawalTagController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 所有路由都需要认证
AuthMiddleware::authenticate();

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;

    // 路由映射
    if (empty($path)) {
        // /api/withdrawal-tags
        if ($method === 'GET') {
            $controller->index();
        } elseif ($method === 'POST') {
            $controller->create();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && !$action) {
        // /api/withdrawal-tags/{id}
        if ($method === 'DELETE') {
            $controller->delete($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
