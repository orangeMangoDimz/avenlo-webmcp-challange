<?php
/**
 * 管理员角色模型
 */

require_once __DIR__ . '/BaseModel.php';

class AdminRole extends BaseModel {
    protected $table = 'adminRoles';
    protected $primaryKey = 'id';

    protected $fillable = [
        'roleKey', 'roleName', 'roleDisplayName', 'description',
        'badgeColor', 'level', 'isSystem', 'isActive'
    ];

    /**
     * 获取所有活跃角色
     */
    public function getActiveRoles() {
        return $this->findAll(['isActive' => 1], 'level DESC');
    }

    /**
     * 获取角色权限
     */
    public function getRolePermissions($roleId) {
        $sql = "SELECT p.*
                FROM adminPermissions p
                INNER JOIN adminRolePermissions rp ON p.id = rp.permissionId
                WHERE rp.roleId = :roleId AND p.isActive = 1
                ORDER BY p.sortOrder";

        return $this->db->fetchAll($sql, ['roleId' => $roleId]);
    }

    /**
     * 分配权限给角色
     */
    public function assignPermission($roleId, $permissionId) {
        return $this->db->insert('adminRolePermissions', [
            'roleId' => $roleId,
            'permissionId' => $permissionId
        ]);
    }

    /**
     * 移除角色权限
     */
    public function removePermission($roleId, $permissionId) {
        return $this->db->delete(
            'adminRolePermissions',
            'roleId = :roleId AND permissionId = :permissionId',
            ['roleId' => $roleId, 'permissionId' => $permissionId]
        );
    }

    /**
     * 批量更新角色权限
     */
    public function syncPermissions($roleId, $permissionIds) {
        try {
            $this->db->beginTransaction();

            // 删除现有权限
            $this->db->delete('adminRolePermissions', 'roleId = :roleId', ['roleId' => $roleId]);

            // 添加新权限
            foreach ($permissionIds as $permissionId) {
                $this->assignPermission($roleId, $permissionId);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }

    /**
     * 获取角色用户数量
     */
    public function getUserCount($roleId) {
        $sql = "SELECT COUNT(*) as count FROM adminUsers
                WHERE roleId = :roleId AND deletedAt IS NULL";
        $result = $this->db->fetchOne($sql, ['roleId' => $roleId]);
        return $result ? (int)$result['count'] : 0;
    }
}
