<?php
/**
 * 系统设置API路由
 */

require_once __DIR__ . '/../controllers/SettingController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new SettingController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 所有路由都需要认证
AuthMiddleware::authenticate();

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $key = $pathParts[0] ?? null;

    if (empty($path)) {
        // /api/settings
        if ($method === 'GET') {
            $controller->index();
        }
    } elseif ($key === 'bulk') {
        // /api/settings/bulk
        if ($method === 'PUT') {
            $controller->bulkUpdate();
        }
    } elseif ($key) {
        // /api/settings/{key}
        if ($method === 'GET') {
            $controller->show($key);
        } elseif ($method === 'PUT') {
            $controller->update($key);
        }
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
