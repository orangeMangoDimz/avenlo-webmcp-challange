-- ============================================================
-- 添加 IB Management 权限
-- Add IB Management Permission
-- ============================================================
-- Created: 2024-11-23
-- Description: 为 IB 管理功能添加权限，包括文档、层级、规则等
-- ============================================================

-- 1. 添加 IB Management 权限
INSERT INTO `adminPermissions` (`permissionKey`, `permissionName`, `permissionDisplayName`, `description`, `module`, `sortOrder`)
VALUES
('perm-ib-management', 'manage_ib_program', 'Manage IB Program', 'Full access to IB (Introducing Broker) program management including settings, documents, tier levels, commission rules, applications, and partners', 'ib_management', 7)
ON DUPLICATE KEY UPDATE
  `permissionName` = 'manage_ib_program',
  `permissionDisplayName` = 'Manage IB Program',
  `description` = 'Full access to IB (Introducing Broker) program management including settings, documents, tier levels, commission rules, applications, and partners',
  `module` = 'ib_management',
  `sortOrder` = 7;

-- 2. 获取新添加的权限ID（如果需要手动分配，请在下面手动更新权限ID）
-- 为了让脚本更通用，我们使用子查询来获取权限ID

-- 3. 为 Administrator 角色 (roleId=1) 分配 IB Management 权限
INSERT INTO `adminRolePermissions` (`roleId`, `permissionId`)
SELECT 1, id FROM `adminPermissions` WHERE `permissionKey` = 'perm-ib-management'
ON DUPLICATE KEY UPDATE `roleId` = `roleId`;

-- 4. 为 Manager 角色 (roleId=2) 分配 IB Management 权限（可选）
INSERT INTO `adminRolePermissions` (`roleId`, `permissionId`)
SELECT 2, id FROM `adminPermissions` WHERE `permissionKey` = 'perm-ib-management'
ON DUPLICATE KEY UPDATE `roleId` = `roleId`;

-- 5. 验证权限添加成功
-- 查看新添加的权限
SELECT * FROM `adminPermissions` WHERE `permissionKey` = 'perm-ib-management';

-- 查看角色权限分配情况
SELECT
    r.roleName,
    p.permissionKey,
    p.permissionDisplayName,
    p.description
FROM `adminRolePermissions` rp
JOIN `adminRoles` r ON rp.roleId = r.id
JOIN `adminPermissions` p ON rp.permissionId = p.id
WHERE p.permissionKey = 'perm-ib-management'
ORDER BY r.id;

-- ============================================================
-- 说明 (Instructions)
-- ============================================================
-- 1. 这个脚本会添加 perm-ib-management 权限到 adminPermissions 表
-- 2. 自动为 Administrator 和 Manager 角色分配这个权限
-- 3. 如果权限已存在，会更新其信息而不会重复添加
-- 4. 用户需要重新登录以获取新的权限
-- ============================================================
