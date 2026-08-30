<?php
/**
 * 认证相关API路由
 */

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new AuthController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 公开路由（不需要认证）
$publicRoutes = [
    'POST /login' => [$controller, 'login'],
    'POST /forgot-password' => [$controller, 'forgotPassword'],
    'POST /reset-password' => [$controller, 'resetPassword']
];

// 需要认证的路由
$protectedRoutes = [
    'POST /logout' => [$controller, 'logout'],
    'GET /me' => [$controller, 'me'],
    'POST /change-password' => [$controller, 'changePassword'],
    'POST /refresh' => [$controller, 'refresh']
];

$routeKey = "{$method} /{$path}";

try {
    // 检查公开路由
    if (isset($publicRoutes[$routeKey])) {
        call_user_func($publicRoutes[$routeKey]);
        exit;
    }

    // 检查受保护路由
    if (isset($protectedRoutes[$routeKey])) {
        AuthMiddleware::authenticate();
        call_user_func($protectedRoutes[$routeKey]);
        exit;
    }

    // 路由未找到
    Response::error('Route not found', 404);

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
