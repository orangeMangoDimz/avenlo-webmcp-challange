<?php
/**
 * 日志管理API路由
 */

require_once __DIR__ . '/../controllers/LogController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new LogController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 所有路由都需要认证
AuthMiddleware::authenticate();

try {
    // 路由映射
    if ($path === 'login' || $path === 'login-logs') {
        // /api/logs/login
        if ($method === 'GET') {
            $controller->loginLogs();
        }
    } elseif ($path === 'stats' || $path === 'login-stats') {
        // /api/logs/stats
        if ($method === 'GET') {
            $controller->loginStats();
        }
    } elseif ($path === 'failed-logins') {
        // /api/logs/failed-logins
        if ($method === 'GET') {
            $controller->failedLogins();
        }
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
