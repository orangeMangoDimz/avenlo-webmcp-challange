<?php
/**
 * 权限管理API路由
 */

require_once __DIR__ . '/../controllers/PermissionController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new PermissionController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 所有路由都需要认证
AuthMiddleware::authenticate();
$guardRolePermission = static function ($permissionKeys) {
    AuthMiddleware::checkAnyPermission((array)$permissionKeys);
};

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));

    if (empty($path)) {
        // /api/permissions
        if ($method === 'GET') {
            $guardRolePermission(['page_rolemanagement_readonly', 'page_rolemanagement_edit']);
            $controller->index();
        }
    } elseif ($pathParts[0] === 'check') {
        // /api/permissions/check
        if ($method === 'POST') {
            $guardRolePermission(['page_rolemanagement_readonly', 'page_rolemanagement_edit']);
            $controller->checkPermission();
        }
    } elseif ($pathParts[0] === 'user' && isset($pathParts[1])) {
        // /api/permissions/user/{userId}
        if ($method === 'GET') {
            $guardRolePermission(['page_rolemanagement_readonly', 'page_rolemanagement_edit']);
            $controller->getUserPermissions($pathParts[1]);
        }
    } elseif (is_numeric($pathParts[0])) {
        // /api/permissions/{id}
        if ($method === 'GET') {
            $guardRolePermission(['page_rolemanagement_readonly', 'page_rolemanagement_edit']);
            $controller->show($pathParts[0]);
        }
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
