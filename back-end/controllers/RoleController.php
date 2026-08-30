<?php
/**
 * 角色管理控制器
 */

require_once __DIR__ . '/../models/AdminRole.php';
require_once __DIR__ . '/../models/AdminPermission.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../services/OperationLog/RolesOperationLog.php';
require_once __DIR__ . '/../services/OperationLog/AdminRoleLogSnapshot.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class RoleController {
    private $roleModel;
    private $permissionModel;

    public function __construct() {
        $this->roleModel = new AdminRole();
        $this->permissionModel = new AdminPermission();
    }

    /**
     * 获取角色列表
     * GET /api/roles
     */
    public function index() {
        // 返回所有角色（不区分isActive状态），按level降序排序
        $roles = $this->roleModel->findAll([], 'level DESC');

        // 获取每个角色的用户数量和权限
        foreach ($roles as &$role) {
            $role['userCount'] = $this->roleModel->getUserCount($role['id']);
            $role['permissions'] = $this->roleModel->getRolePermissions($role['id']);
        }

        Response::success($roles);
    }

    /**
     * 获取激活的角色列表（用于Account Management页面）
     * GET /api/roles/active
     */
    public function getActiveRoles() {
        // 只返回激活的角色，按level降序排序
        $roles = $this->roleModel->findAll(['isActive' => 1], 'level DESC');

        // 只返回角色基本信息，不加载权限和用户数量
        Response::success($roles);
    }

    /**
     * 获取单个角色
     * GET /api/roles/{id}
     */
    public function show($id) {
        $role = $this->roleModel->findById($id);

        if (!$role) {
            Response::notFound('Role not found');
        }

        $role['userCount'] = $this->roleModel->getUserCount($id);
        $role['permissions'] = $this->roleModel->getRolePermissions($id);

        Response::success($role);
    }

    /**
     * 创建角色
     * POST /api/roles
     */
    public function create() {
        $data = json_decode(file_get_contents('php://input'), true);
        $input = RolesOperationLog::inputFromRequest(is_array($data) ? $data : null);
        $operatorId = $this->resolveOperatorId();

        if (!is_array($data)) {
            RolesOperationLog::logFailure(
                $input,
                'add',
                'adminRoleCreateFailure',
                'Invalid JSON body',
                null,
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
            return;
        }

        $errors = Validator::validateData($data, [
            'roleName' => 'required'
        ]);
        if (!empty($errors)) {
            RolesOperationLog::logFailure(
                $input,
                'add',
                'adminRoleCreateFailure',
                OperationLogTextHelpers::validationErrorsToMessage($errors),
                null,
                $operatorId
            );
            Response::validationError($errors);
            return;
        }

        $roleId = $this->roleModel->create([
            'roleKey' => '',
            'roleName' => $data['roleName'],
            'roleDisplayName' => $data['roleName'],
            'description' => $data['description'] ?? null,
            'badgeColor' => $data['badgeColor'] ?? 'default',
            'level' => $data['level'] ?? 0,
            'isSystem' => 0,
            'isActive' => $data['isActive'] ?? 1
        ]);

        // 如果提供了权限列表，分配权限
        if (isset($data['permissionIds']) && is_array($data['permissionIds'])) {
            $this->roleModel->syncPermissions($roleId, $data['permissionIds']);
        }

        $newRole = $this->roleModel->findById($roleId);
        $newRole['permissions'] = $this->roleModel->getRolePermissions($roleId);

        $afterState = $this->buildRoleLogState($roleId);
        if ($afterState !== null) {
            RolesOperationLog::logCreateSuccess($input, $afterState, $roleId, $operatorId);
        }

        Response::success($newRole, 'Role created successfully', 201);
    }

    /**
     * 更新角色
     * PUT /api/roles/{id}
     */
    public function update($id) {
        $role = $this->roleModel->findById($id);

        if (!$role) {
            $input = RolesOperationLog::inputFromRequest();
            RolesOperationLog::logFailure(
                $input,
                'edit',
                'adminRoleUpdateFailure',
                'Role not found',
                $id,
                $this->resolveOperatorId()
            );
            Response::notFound('Role not found');
        }

        // 检查是否是超级管理员（id = 1）
        if ($id == 1) {
            $input = RolesOperationLog::inputFromRequest();
            RolesOperationLog::logFailure(
                $input,
                'edit',
                'adminRoleUpdateFailure',
                'Cannot modify Super Admin role',
                $id,
                $this->resolveOperatorId()
            );
            Response::error('Cannot modify Super Admin role', 403);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $input = RolesOperationLog::inputFromRequest(is_array($data) ? $data : null);
        $operatorId = $this->resolveOperatorId();

        if (!is_array($data)) {
            RolesOperationLog::logFailure(
                $input,
                'edit',
                'adminRoleUpdateFailure',
                'Invalid JSON body',
                $id,
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
            return;
        }

        $beforeState = $this->buildRoleLogState($id);

        $appConfig = require __DIR__ . '/../config/app.php';
        $specialRoleIds = [
            (int)($appConfig['special_roles']['sales_manager_role_id'] ?? 0),
            (int)($appConfig['special_roles']['sales_role_id'] ?? 0),
        ];
        $isSpecialRole = in_array((int)$id, $specialRoleIds, true);

        // 特殊角色（Sales Manager / Sales）：仅允许更新权限和 Description，不允许修改名称、状态
        if ($isSpecialRole && $role['isSystem']) {
            $updateData = [];
            if (array_key_exists('description', $data)) {
                $updateData['description'] = $data['description'];
            }
            if (!empty($updateData)) {
                $this->roleModel->update($id, $updateData);
            }
            if (isset($data['permissionIds']) && is_array($data['permissionIds'])) {
                $this->roleModel->syncPermissions($id, $data['permissionIds']);
            }
            $updatedRole = $this->roleModel->findById($id);
            $updatedRole['permissions'] = $this->roleModel->getRolePermissions($id);

            if ($beforeState !== null) {
                $afterState = $this->buildRoleLogState($id);
                if ($afterState !== null) {
                    RolesOperationLog::logUpdateSuccess($input, $beforeState, $afterState, $id, $operatorId);
                }
            }

            Response::success($updatedRole, 'Permissions and description updated successfully');
            return;
        }

        // 非特殊角色的系统角色不可修改
        if ($role['isSystem']) {
            RolesOperationLog::logFailure(
                $input,
                'edit',
                'adminRoleUpdateFailure',
                'Cannot modify system role',
                $id,
                $operatorId
            );
            Response::error('Cannot modify system role', 403);
        }

        $updateData = [];

        if (isset($data['roleName'])) {
            $updateData['roleName'] = $data['roleName'];
            $updateData['roleDisplayName'] = $data['roleName'];
        }

        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }

        if (isset($data['badgeColor'])) {
            $updateData['badgeColor'] = $data['badgeColor'];
        }

        if (isset($data['level'])) {
            $updateData['level'] = $data['level'];
        }

        if (isset($data['isActive'])) {
            $updateData['isActive'] = $data['isActive'];
        }

        $this->roleModel->update($id, $updateData);

        // 更新权限
        if (isset($data['permissionIds']) && is_array($data['permissionIds'])) {
            $this->roleModel->syncPermissions($id, $data['permissionIds']);
        }

        $updatedRole = $this->roleModel->findById($id);
        $updatedRole['permissions'] = $this->roleModel->getRolePermissions($id);

        if ($beforeState !== null) {
            $afterState = $this->buildRoleLogState($id);
            if ($afterState !== null) {
                RolesOperationLog::logUpdateSuccess($input, $beforeState, $afterState, $id, $operatorId);
            }
        }

        Response::success($updatedRole, 'Role updated successfully');
    }

    /**
     * 删除角色
     * DELETE /api/roles/{id}
     */
    public function delete($id) {
        $role = $this->roleModel->findById($id);

        if (!$role) {
            Response::notFound('Role not found');
        }

        // 检查是否是系统角色
        if ($role['isSystem']) {
            Response::error('Cannot delete system role', 403);
        }

        // 检查是否有用户使用此角色
        $userCount = $this->roleModel->getUserCount($id);
        if ($userCount > 0) {
            Response::error("Cannot delete role with {$userCount} active users", 400);
        }

        $this->roleModel->delete($id);

        Response::success(null, 'Role deleted successfully');
    }

    /**
     * 获取角色权限
     * GET /api/roles/{id}/permissions
     */
    public function getPermissions($id) {
        $role = $this->roleModel->findById($id);

        if (!$role) {
            Response::notFound('Role not found');
        }

        $permissions = $this->roleModel->getRolePermissions($id);

        Response::success($permissions);
    }

    /**
     * 更新角色权限
     * PUT /api/roles/{id}/permissions
     */
    public function updatePermissions($id) {
        $role = $this->roleModel->findById($id);

        if (!$role) {
            Response::notFound('Role not found');
        }

        // 检查是否是超级管理员（id = 1）
        if ($id == 1) {
            Response::error('Cannot modify Super Admin permissions', 403);
        }

        $data = json_decode(file_get_contents('php://input'), true);

        Validator::make($data, [
            'permissionIds' => 'required'
        ]);

        $permissionIds = $data['permissionIds'];

        if (!is_array($permissionIds)) {
            Response::error('Permission IDs must be an array', 400);
        }

        $success = $this->roleModel->syncPermissions($id, $permissionIds);

        if ($success) {
            $permissions = $this->roleModel->getRolePermissions($id);
            Response::success($permissions, 'Permissions updated successfully');
        } else {
            Response::error('Failed to update permissions', 500);
        }
    }

    private function resolveOperatorId() {
        $token = JWT::getTokenFromHeader();
        if (!$token) {
            return 0;
        }
        $payload = JWT::decode($token);
        return (int) ($payload['userId'] ?? 0);
    }

    /**
     * 操作日志：角色 + 权限 ID 快照
     *
     * @return array<string,mixed>|null
     */
    private function buildRoleLogState($roleId) {
        $role = $this->roleModel->findById($roleId);
        if (!$role) {
            return null;
        }
        $permissions = $this->roleModel->getRolePermissions($roleId);
        $permissionIds = [];
        foreach ($permissions as $perm) {
            $pid = (int) ($perm['id'] ?? 0);
            if ($pid > 0) {
                $permissionIds[] = $pid;
            }
        }
        return AdminRoleLogSnapshot::fromDbRow($role, $permissionIds);
    }
}
