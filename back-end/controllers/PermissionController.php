<?php
/**
 * 权限管理控制器
 */

require_once __DIR__ . '/../models/AdminPermission.php';
require_once __DIR__ . '/../utils/Response.php';

class PermissionController {
    private $permissionModel;

    public function __construct() {
        $this->permissionModel = new AdminPermission();
    }

    /**
     * 获取所有权限
     * GET /api/permissions
     */
    public function index() {
        $grouped = $_GET['grouped'] ?? false;

        if ($grouped) {
            $permissions = $this->permissionModel->getPermissionsByModule();
        } else {
            $permissions = $this->permissionModel->getActivePermissions();
        }

        Response::success($permissions);
    }

    /**
     * 获取单个权限
     * GET /api/permissions/{id}
     */
    public function show($id) {
        $permission = $this->permissionModel->findById($id);

        if (!$permission) {
            Response::notFound('Permission not found');
        }

        Response::success($permission);
    }

    /**
     * 获取用户权限
     * GET /api/permissions/user/{userId}
     */
    public function getUserPermissions($userId) {
        $permissions = $this->permissionModel->getUserPermissions($userId);
        Response::success($permissions);
    }

    /**
     * 检查用户权限
     * POST /api/permissions/check
     */
    public function checkPermission() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['userId']) || !isset($data['permissionKey'])) {
            Response::error('userId and permissionKey are required', 400);
        }

        $hasPermission = $this->permissionModel->userHasPermission(
            $data['userId'],
            $data['permissionKey']
        );

        Response::success([
            'hasPermission' => $hasPermission
        ]);
    }
}
