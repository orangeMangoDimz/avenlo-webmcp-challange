<?php
/**
 * 用户管理API路由
 */

require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new UserController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 所有路由都需要认证
AuthMiddleware::authenticate();
$guardAccountManagement = static function ($permissionKeys) {
    AuthMiddleware::checkAnyPermission((array)$permissionKeys);
};

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    // 路由映射
    if (empty($path)) {
        // /api/users
        if ($method === 'GET') {
            $guardAccountManagement(['page_accountmanagement_readonly', 'page_accountmanagement_edit']);
            $controller->index();
        } elseif ($method === 'POST') {
            // 保持向后兼容：POST /api/users 仍然可用
            $guardAccountManagement('page_accountmanagement_create');
            $controller->create();
        }
    } elseif ($id === 'create' && !$action) {
        // /api/users/create (新路由)
        if ($method === 'POST') {
            $guardAccountManagement('page_accountmanagement_create');
            $controller->create();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id === 'getdepartments' && !$action) {
        // /api/users/getdepartments
        if ($method === 'GET') {
            $guardAccountManagement(['page_accountmanagement_readonly', 'page_accountmanagement_edit']);
            $controller->getDepartments();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id === 'getpositions' && !$action) {
        // /api/users/getpositions
        if ($method === 'GET') {
            $guardAccountManagement(['page_accountmanagement_readonly', 'page_accountmanagement_edit']);
            $controller->getPositions();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && !$action && is_numeric($id)) {
        // /api/users/{id} - 只匹配数字ID
        if ($method === 'GET') {
            $guardAccountManagement(['page_accountmanagement_readonly', 'page_accountmanagement_edit']);
            $controller->show($id);
        } elseif ($method === 'PUT') {
            $guardAccountManagement('page_accountmanagement_edit');
            $controller->update($id);
        } elseif ($method === 'DELETE') {
            $guardAccountManagement('page_accountmanagement_delete');
            $controller->delete($id);
        }
    } elseif ($id && $action) {
        // /api/users/{id}/{action}
        switch ($action) {
            case 'toggle-lock':
                if ($method === 'POST') {
                    $guardAccountManagement('page_accountmanagement_edit');
                    $controller->toggleLock($id);
                }
                break;
            case 'reset-password':
                if ($method === 'POST') {
                    $guardAccountManagement('page_accountmanagement_edit');
                    $controller->resetPassword($id);
                }
                break;
            case 'profile':
                if ($method === 'POST') {
                    $controller->updateProfile($id);
                }
                break;
            default:
                Response::error('Route not found', 404);
        }
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
