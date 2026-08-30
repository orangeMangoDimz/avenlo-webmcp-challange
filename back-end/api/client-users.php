<?php
/**
 * 客户用户管理 API 路由
 * 仅供管理员使用
 */

require_once __DIR__ . '/../controllers/ClientUserController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

// 验证管理员权限
AuthMiddleware::authenticate();

$controller = new ClientUserController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 解析路径段
$segments = array_filter(explode('/', $path));
$segments = array_values($segments);

// 所有路由都需要管理员认证
$routes = [
    'GET /' => [$controller, 'index'],
    'GET /search' => [$controller, 'search'],
    'GET /stats' => [$controller, 'stats']
];

try {
    $routeKey = "{$method} /{$path}";

    // 检查精确路由
    if (isset($routes[$routeKey])) {
        call_user_func($routes[$routeKey]);
        exit;
    }

    // 处理带ID的路由
    if (count($segments) >= 1 && is_numeric($segments[0])) {
        $id = $segments[0];
        $action = $segments[1] ?? '';

        if (empty($action)) {
            // /api/client/users/:id
            switch ($method) {
                case 'GET':
                    $controller->show($id);
                    break;
                case 'PUT':
                    $controller->update($id);
                    break;
                case 'DELETE':
                    $controller->delete($id);
                    break;
                default:
                    Response::error('Method not allowed', 405);
            }
        } elseif ($action === 'status') {
            // /api/client/users/:id/status
            if ($method === 'PUT') {
                $controller->updateStatus($id);
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($action === 'activity-log') {
            // /api/client/users/:id/activity-log
            if ($method === 'GET') {
                $controller->getActivityLog($id);
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($action === 'reset-password') {
            // /api/client/users/:id/reset-password
            if ($method === 'POST') {
                $controller->adminResetPassword($id);
            } else {
                Response::error('Method not allowed', 405);
            }
        } else {
            Response::error('Route not found', 404);
        }
        exit;
    }

    // 路由未找到
    Response::error('Route not found', 404, ['requested' => $routeKey]);

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
