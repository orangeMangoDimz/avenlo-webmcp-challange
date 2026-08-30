<?php
/**
 * 角色管理API路由
 */

require_once __DIR__ . '/../controllers/RoleController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new RoleController();
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
    $firstPart = $pathParts[0] ?? null;
    $secondPart = $pathParts[1] ?? null;
    $thirdPart = $pathParts[2] ?? null;

    // 路由映射
    if (empty($path)) {
        // /api/roles
        if ($method === 'GET') {
            $guardRolePermission(['page_rolemanagement_readonly', 'page_rolemanagement_edit']);
            $controller->index();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($firstPart === 'active' && $method === 'GET') {
        // GET /api/roles/active
        $guardRolePermission(['page_rolemanagement_readonly', 'page_rolemanagement_edit']);
        $controller->getActiveRoles();
    } elseif ($firstPart === 'create' && $method === 'POST') {
        // POST /api/roles/create
        $guardRolePermission('page_rolemanagement_create');
        $controller->create();
    } elseif ($firstPart === 'update' && $secondPart && $method === 'POST') {
        // POST /api/roles/update/{id}
        $guardRolePermission('page_rolemanagement_edit');
        $controller->update($secondPart);
    } elseif ($firstPart && $secondPart === 'update-permissions' && $method === 'POST') {
        // POST /api/roles/{id}/update-permissions
        $guardRolePermission('page_rolemanagement_edit');
        $controller->updatePermissions($firstPart);
    } elseif ($firstPart && !$secondPart) {
        // /api/roles/{id}
        if ($method === 'GET') {
            $guardRolePermission(['page_rolemanagement_readonly', 'page_rolemanagement_edit']);
            $controller->show($firstPart);
        } elseif ($method === 'DELETE') {
            $guardRolePermission('page_rolemanagement_disable');
            $controller->delete($firstPart);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($firstPart && $secondPart === 'permissions' && $method === 'GET') {
        // GET /api/roles/{id}/permissions
        $guardRolePermission(['page_rolemanagement_readonly', 'page_rolemanagement_edit']);
        $controller->getPermissions($firstPart);
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
