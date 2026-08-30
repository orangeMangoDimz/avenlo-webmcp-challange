<?php
/**
 * 管理员权限模型
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/AdminUser.php';
require_once __DIR__ . '/AdminRole.php';

class AdminPermission extends BaseModel {
    protected $table = 'adminPermissions';
    protected $primaryKey = 'id';

    protected $fillable = [
        'permissionKey', 'permissionName', 'permissionDisplayName', 'permissionDisplayNameZh',
        'description', 'module', 'parentId', 'sortOrder', 'isActive',
        'route', 'is_menu'
    ];

    /**
     * 获取所有活跃权限
     */
    public function getActivePermissions() {
        return $this->findAll(['isActive' => 1], 'sortOrder ASC');
    }

    /**
     * 根据权限键查找
     */
    public function findByKey($permissionKey) {
        return $this->findOne(['permissionKey' => $permissionKey]);
    }

    /**
     * 按模块分组获取权限
     */
    public function getPermissionsByModule() {
        $permissions = $this->getActivePermissions();
        $grouped = [];

        foreach ($permissions as $permission) {
            $module = $permission['module'] ?? 'other';
            if (!isset($grouped[$module])) {
                $grouped[$module] = [];
            }
            $grouped[$module][] = $permission;
        }

        return $grouped;
    }

    /**
     * 获取用户所有权限（角色权限 + 自定义权限）
     */
    public function getUserPermissions($userId) {
        $sql = "SELECT DISTINCT
                    p.id, p.permissionKey, p.permissionName,
                    p.permissionDisplayName, p.permissionDisplayNameZh, p.module, p.sortOrder
                FROM vAdminUserPermissions v
                INNER JOIN adminPermissions p ON v.permissionKey = p.permissionKey
                WHERE v.userId = :userId AND v.isGranted = 1 AND p.isActive = 1
                ORDER BY p.sortOrder";

        return $this->db->fetchAll($sql, ['userId' => $userId]);
    }

    /**
     * 检查用户是否有指定权限
     */
    public function userHasPermission($userId, $permissionKey) {
        // 获取用户角色
        $userModel = new AdminUser();
        $user = $userModel->findById($userId);

        if ($user && isset($user['roleId']) && $user['roleId'] == 1) {
            return true;
        }

        // 其他角色按正常权限检查
        $sql = "SELECT COUNT(*) as count
                FROM vAdminUserPermissions
                WHERE userId = :userId
                AND permissionKey = :permissionKey
                AND isGranted = 1";

        $result = $this->db->fetchOne($sql, [
            'userId' => $userId,
            'permissionKey' => $permissionKey
        ]);

        return $result && $result['count'] > 0;
    }
}
