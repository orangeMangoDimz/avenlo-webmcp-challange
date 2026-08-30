<?php
/**
 * Menu Settings API 路由
 * 通用菜单设置接口，用于控制菜单显示/隐藏
 * 支持缓存机制，提高性能
 */

require_once __DIR__ . '/../controllers/MenuSettingsController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new MenuSettingsController();
$method = $_SERVER['REQUEST_METHOD'];
$path = trim($_GET['path'] ?? '', '/');

// 所有路由都需要认证
AuthMiddleware::authenticate();

try {
    if (empty($path)) {
        // GET /api/menu-settings - 获取所有菜单相关设置
        if ($method === 'GET') {
            $controller->index();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'refresh') {
        // POST /api/menu-settings/refresh - 强制刷新缓存
        if ($method === 'POST') {
            $controller->refreshCache();
        } else {
            Response::error('Method not allowed', 405);
        }
    } else {
        Response::error('Route not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
