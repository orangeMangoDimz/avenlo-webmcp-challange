-- ============================================
-- Utrada CRM - Admin Backend System Database
-- ============================================
-- 后台管理系统数据库（驼峰命名法）
-- 创建日期: 2025-10-07
-- 最后更新: 2025-10-07
-- 包含: 管理员登录、账户管理、权限控制、日志记录
--
-- 主要功能:
-- - 管理员账户管理 (admin-accounts.html)
-- - 管理员登录认证 (admin-login.html)
-- - 角色权限控制系统
-- - 用户资料管理 (My Profile 模态框)
-- - 密码修改功能 (Change Password 模态框)
-- - 登录日志记录
-- ============================================

-- 设置字符集
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. 管理员用户表 (Admin Users)
-- ============================================
-- 对应 admin-accounts.html 中的账户列表
DROP TABLE IF EXISTS `adminUsers`;
CREATE TABLE `adminUsers` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `username` varchar(50) NOT NULL COMMENT '用户名（用于登录）',
  `email` varchar(100) NOT NULL COMMENT '邮箱地址',
  `passwordHash` varchar(255) NOT NULL COMMENT '密码哈希值',
  `fullName` varchar(100) NOT NULL COMMENT '全名',
  `avatarInitials` varchar(5) DEFAULT NULL COMMENT '头像缩写（如 JD, SM）',
  `avatarColor` varchar(50) DEFAULT NULL COMMENT '头像背景色（CSS gradient）',
  `roleId` int(11) NOT NULL COMMENT '角色ID（外键）',
  `status` enum('active','inactive') DEFAULT 'active' COMMENT '账户状态',
  `isLocked` tinyint(1) DEFAULT 0 COMMENT '账户是否锁定',
  `failedLoginAttempts` int(11) DEFAULT 0 COMMENT '失败登录次数',
  `lastLoginAt` datetime DEFAULT NULL COMMENT '最后登录时间',
  `lastLoginIp` varchar(45) DEFAULT NULL COMMENT '最后登录IP',
  `rememberToken` varchar(100) DEFAULT NULL COMMENT '记住我令牌',
  `passwordChangedAt` datetime DEFAULT NULL COMMENT '密码最后修改时间',
  `mustChangePassword` tinyint(1) DEFAULT 0 COMMENT '是否必须修改密码',
  `twoFactorEnabled` tinyint(1) DEFAULT 0 COMMENT '是否启用双因素认证',
  `twoFactorSecret` varchar(255) DEFAULT NULL COMMENT '双因素认证密钥',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `createdBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT '创建人ID',
  `updatedAt` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT '更新人ID',
  `deletedAt` datetime DEFAULT NULL COMMENT '软删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukUsername` (`username`),
  UNIQUE KEY `ukEmail` (`email`),
  KEY `idxRoleId` (`roleId`),
  KEY `idxStatus` (`status`),
  KEY `idxCreatedAt` (`createdAt`),
  KEY `idxLastLogin` (`lastLoginAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员用户表';

-- 插入示例数据（对应页面中的示例账户）
INSERT INTO `adminUsers` (`username`, `email`, `passwordHash`, `fullName`, `avatarInitials`, `avatarColor`, `roleId`, `status`, `lastLoginAt`, `createdAt`) VALUES
('john.doe', 'john.doe@utrada.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', 'JD', 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)', 1, 'active', '2025-10-07 14:30:00', '2024-01-15 00:00:00'),
('sarah.miller', 'sarah.miller@utrada.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Miller', 'SM', 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)', 2, 'active', '2025-10-07 10:15:00', '2024-02-20 00:00:00'),
('michael.j', 'michael.j@utrada.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Michael Johnson', 'MJ', 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)', 3, 'active', '2025-10-06 16:45:00', '2024-03-10 00:00:00'),
('emily.brown', 'emily.brown@utrada.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Emily Brown', 'EB', 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)', 4, 'inactive', '2025-09-28 09:20:00', '2024-04-05 00:00:00'),
('david.w', 'david.w@utrada.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'David Wilson', 'DW', 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)', 3, 'active', '2025-10-07 13:10:00', '2024-05-12 00:00:00');

-- ============================================
-- 2. 管理员角色表 (Admin Roles)
-- ============================================
-- 对应 admin-accounts.html 中的角色选择和角色徽章
DROP TABLE IF EXISTS `adminRoles`;
CREATE TABLE `adminRoles` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '角色ID',
  `roleKey` varchar(50) NOT NULL COMMENT '角色标识（admin, manager, operator, viewer）',
  `roleName` varchar(50) NOT NULL COMMENT '角色名称',
  `roleDisplayName` varchar(100) NOT NULL COMMENT '角色显示名称',
  `description` text DEFAULT NULL COMMENT '角色描述',
  `badgeColor` varchar(50) DEFAULT NULL COMMENT '徽章颜色CSS类',
  `level` int(11) DEFAULT 0 COMMENT '角色级别（数字越大权限越高）',
  `isSystem` tinyint(1) DEFAULT 0 COMMENT '是否系统角色（不可删除）',
  `isActive` tinyint(1) DEFAULT 1 COMMENT '是否启用',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updatedAt` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukRoleKey` (`roleKey`),
  KEY `idxLevel` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员角色表';

-- 插入角色数据（对应页面中的四种角色）
INSERT INTO `adminRoles` (`roleKey`, `roleName`, `roleDisplayName`, `description`, `badgeColor`, `level`, `isSystem`, `isActive`) VALUES
('admin', 'Administrator', 'Administrator', 'Full system access with all permissions', 'admin', 100, 1, 1),
('manager', 'Manager', 'Manager', 'Management access with limited system settings', 'manager', 75, 1, 1),
('operator', 'Operator', 'Operator', 'Operational access for daily tasks', 'operator', 50, 1, 1),
('viewer', 'Viewer', 'Viewer', 'Read-only access to system data', 'viewer', 25, 1, 1);

-- ============================================
-- 3. 管理员权限表 (Admin Permissions)
-- ============================================
-- 对应 admin-accounts.html 中的权限复选框
DROP TABLE IF EXISTS `adminPermissions`;
CREATE TABLE `adminPermissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '权限ID',
  `permissionKey` varchar(100) NOT NULL COMMENT '权限标识（如 perm-clients）',
  `permissionName` varchar(100) NOT NULL COMMENT '权限名称',
  `permissionDisplayName` varchar(100) NOT NULL COMMENT '权限显示名称',
  `description` text DEFAULT NULL COMMENT '权限描述',
  `module` varchar(50) DEFAULT NULL COMMENT '所属模块',
  `parentId` int(11) DEFAULT NULL COMMENT '父权限ID',
  `sortOrder` int(11) DEFAULT 0 COMMENT '排序',
  `isActive` tinyint(1) DEFAULT 1 COMMENT '是否启用',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updatedAt` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukPermissionKey` (`permissionKey`),
  KEY `idxModule` (`module`),
  KEY `idxParentId` (`parentId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员权限表';

-- 插入权限数据（对应页面中的权限复选框）
INSERT INTO `adminPermissions` (`permissionKey`, `permissionName`, `permissionDisplayName`, `description`, `module`, `sortOrder`) VALUES
('perm-clients', 'manage_clients', 'Manage Clients', 'Full access to client management including create, edit, delete', 'clients', 1),
('perm-transactions', 'view_transactions', 'View Transactions', 'View transaction history and details', 'transactions', 2),
('perm-kyc', 'manage_kyc', 'Manage KYC', 'Manage KYC documents and approval process', 'kyc', 3),
('perm-reports', 'view_reports', 'View Reports', 'Access to all system reports and analytics', 'reports', 4),
('perm-settings', 'system_settings', 'System Settings', 'Access to system configuration and settings', 'system', 5),
('perm-accounts', 'manage_accounts', 'Manage Accounts', 'Manage administrator accounts and permissions', 'system', 6);

-- ============================================
-- 4. 角色权限关联表 (Role Permissions)
-- ============================================
DROP TABLE IF EXISTS `adminRolePermissions`;
CREATE TABLE `adminRolePermissions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '关联ID',
  `roleId` int(11) NOT NULL COMMENT '角色ID',
  `permissionId` int(11) NOT NULL COMMENT '权限ID',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukRolePermission` (`roleId`, `permissionId`),
  KEY `idxRoleId` (`roleId`),
  KEY `idxPermissionId` (`permissionId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色权限关联表';

-- 为 Administrator 角色分配所有权限
INSERT INTO `adminRolePermissions` (`roleId`, `permissionId`) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6);

-- 为 Manager 角色分配部分权限
INSERT INTO `adminRolePermissions` (`roleId`, `permissionId`) VALUES
(2, 1), (2, 2), (2, 3), (2, 4);

-- 为 Operator 角色分配操作权限
INSERT INTO `adminRolePermissions` (`roleId`, `permissionId`) VALUES
(3, 1), (3, 2), (3, 4);

-- 为 Viewer 角色分配只读权限
INSERT INTO `adminRolePermissions` (`roleId`, `permissionId`) VALUES
(4, 2), (4, 4);

-- ============================================
-- 5. 用户自定义权限表 (User Custom Permissions)
-- ============================================
-- 用于覆盖角色默认权限，实现更细粒度的权限控制
DROP TABLE IF EXISTS `adminUserPermissions`;
CREATE TABLE `adminUserPermissions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '关联ID',
  `userId` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `permissionId` int(11) NOT NULL COMMENT '权限ID',
  `isGranted` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否授予（1=授予，0=撤销）',
  `grantedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT '授予人ID',
  `grantedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '授予时间',
  `expiresAt` datetime DEFAULT NULL COMMENT '过期时间',
  `reason` text DEFAULT NULL COMMENT '授予/撤销原因',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukUserPermission` (`userId`, `permissionId`),
  KEY `idxUserId` (`userId`),
  KEY `idxPermissionId` (`permissionId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户自定义权限表';

-- ============================================
-- 6. 管理员登录日志表 (Admin Login Logs)
-- ============================================
-- 对应 admin-login.html 的登录功能和 admin-accounts.html 的最后登录时间
DROP TABLE IF EXISTS `adminLoginLogs`;
CREATE TABLE `adminLoginLogs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `userId` bigint(20) UNSIGNED DEFAULT NULL COMMENT '用户ID（登录成功时记录）',
  `username` varchar(100) DEFAULT NULL COMMENT '登录用户名',
  `email` varchar(100) DEFAULT NULL COMMENT '登录邮箱',
  `loginStatus` enum('success','failed','blocked') NOT NULL COMMENT '登录状态',
  `failureReason` varchar(255) DEFAULT NULL COMMENT '失败原因',
  `ipAddress` varchar(45) NOT NULL COMMENT 'IP地址',
  `userAgent` text DEFAULT NULL COMMENT '浏览器User Agent',
  `deviceType` varchar(50) DEFAULT NULL COMMENT '设备类型（desktop, mobile, tablet）',
  `browser` varchar(50) DEFAULT NULL COMMENT '浏览器名称',
  `platform` varchar(50) DEFAULT NULL COMMENT '操作系统',
  `locationCountry` varchar(100) DEFAULT NULL COMMENT '国家',
  `locationCity` varchar(100) DEFAULT NULL COMMENT '城市',
  `rememberMe` tinyint(1) DEFAULT 0 COMMENT '是否勾选记住我',
  `sessionId` varchar(255) DEFAULT NULL COMMENT '会话ID',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '登录时间',
  PRIMARY KEY (`id`),
  KEY `idxUserId` (`userId`),
  KEY `idxLoginStatus` (`loginStatus`),
  KEY `idxCreatedAt` (`createdAt`),
  KEY `idxIpAddress` (`ipAddress`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员登录日志表';

-- ============================================
-- 7. 密码重置令牌表 (Password Reset Tokens)
-- ============================================
-- 对应 admin-login.html 的忘记密码功能
DROP TABLE IF EXISTS `adminPasswordResets`;
CREATE TABLE `adminPasswordResets` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '令牌ID',
  `userId` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `email` varchar(100) NOT NULL COMMENT '邮箱地址',
  `token` varchar(255) NOT NULL COMMENT '重置令牌（哈希）',
  `tokenPlain` varchar(100) DEFAULT NULL COMMENT '明文令牌（用于邮件发送，不应长期存储）',
  `expiresAt` datetime NOT NULL COMMENT '过期时间',
  `usedAt` datetime DEFAULT NULL COMMENT '使用时间',
  `ipAddress` varchar(45) DEFAULT NULL COMMENT '请求IP',
  `userAgent` text DEFAULT NULL COMMENT '用户代理',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idxEmail` (`email`),
  KEY `idxToken` (`token`),
  KEY `idxUserId` (`userId`),
  KEY `idxExpiresAt` (`expiresAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='密码重置令牌表';

-- ============================================
-- 8. 管理员会话表 (Admin Sessions)
-- ============================================
-- 管理活动会话，支持"记住我"功能
DROP TABLE IF EXISTS `adminSessions`;
CREATE TABLE `adminSessions` (
  `id` varchar(255) NOT NULL COMMENT '会话ID',
  `userId` bigint(20) UNSIGNED DEFAULT NULL COMMENT '用户ID',
  `ipAddress` varchar(45) DEFAULT NULL COMMENT 'IP地址',
  `userAgent` text DEFAULT NULL COMMENT '用户代理',
  `payload` longtext NOT NULL COMMENT '会话数据',
  `lastActivity` int(11) NOT NULL COMMENT '最后活动时间（时间戳）',
  `rememberToken` varchar(100) DEFAULT NULL COMMENT '记住我令牌',
  `expiresAt` datetime DEFAULT NULL COMMENT '过期时间',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idxUserId` (`userId`),
  KEY `idxLastActivity` (`lastActivity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员会话表';


-- ============================================
-- 9. 系统设置表 (System Settings)
-- ============================================
-- 对应侧边栏中的 System Setting > Login Page Settings
DROP TABLE IF EXISTS `adminSystemSettings`;
CREATE TABLE `adminSystemSettings` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '设置ID',
  `settingKey` varchar(100) NOT NULL COMMENT '设置键',
  `settingValue` text DEFAULT NULL COMMENT '设置值',
  `settingType` varchar(50) DEFAULT 'string' COMMENT '值类型（string, number, boolean, json）',
  `category` varchar(50) DEFAULT NULL COMMENT '设置分类（login, security, email, general等）',
  `displayName` varchar(100) NOT NULL COMMENT '显示名称',
  `description` text DEFAULT NULL COMMENT '设置描述',
  `isPublic` tinyint(1) DEFAULT 0 COMMENT '是否公开（客户端可见）',
  `isEditable` tinyint(1) DEFAULT 1 COMMENT '是否可编辑',
  `sortOrder` int(11) DEFAULT 0 COMMENT '排序',
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT '更新人ID',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updatedAt` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukSettingKey` (`settingKey`),
  KEY `idxCategory` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统设置表';

-- 插入登录页面和安全相关设置
INSERT INTO `adminSystemSettings` (`settingKey`, `settingValue`, `settingType`, `category`, `displayName`, `description`) VALUES
('loginPageTitle', 'BDX Admin - Backend Login', 'string', 'login', 'Login Page Title', 'The title shown on the browser tab'),
('loginLogoText', 'BDX', 'string', 'login', 'Login Logo Text', 'The logo text displayed on login page'),
('loginSubtitle', 'Secure backend management system', 'string', 'login', 'Login Subtitle', 'The subtitle shown below the logo'),
('loginAllowRememberMe', '1', 'boolean', 'login', 'Allow Remember Me', 'Enable or disable remember me checkbox'),
('loginMaxAttempts', '5', 'number', 'security', 'Max Login Attempts', 'Maximum failed login attempts before account lock'),
('loginLockoutDuration', '30', 'number', 'security', 'Lockout Duration (minutes)', 'Duration of account lockout after max attempts'),
('loginSessionTimeout', '120', 'number', 'security', 'Session Timeout (minutes)', 'Auto logout after inactivity'),
('passwordMinLength', '8', 'number', 'security', 'Password Minimum Length', 'Minimum password length requirement'),
('passwordRequireUppercase', '1', 'boolean', 'security', 'Require Uppercase', 'Password must contain uppercase letters'),
('passwordRequireLowercase', '1', 'boolean', 'security', 'Require Lowercase', 'Password must contain lowercase letters'),
('passwordRequireNumber', '1', 'boolean', 'security', 'Require Number', 'Password must contain numbers'),
('passwordRequireSpecial', '1', 'boolean', 'security', 'Require Special Character', 'Password must contain special characters'),
('passwordResetTokenExpiry', '60', 'number', 'security', 'Password Reset Token Expiry (minutes)', 'How long password reset links are valid'),
('systemVersion', '2.1.0', 'string', 'general', 'System Version', 'Current system version number');

-- ============================================
-- 10. 管理员个人资料扩展表 (Admin User Profiles)
-- ============================================
-- 对应用户下拉菜单中的 My Profile 模态框
DROP TABLE IF EXISTS `adminUserProfiles`;
CREATE TABLE `adminUserProfiles` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '资料ID',
  `userId` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `phone` varchar(20) DEFAULT NULL COMMENT '电话号码',
  `department` varchar(100) DEFAULT NULL COMMENT '部门',
  `timezone` varchar(50) DEFAULT 'UTC' COMMENT '时区',
  `language` varchar(10) DEFAULT 'en' COMMENT '语言偏好',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updatedAt` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukUserId` (`userId`),
  KEY `idxDepartment` (`department`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员个人资料扩展表';

-- ============================================
-- 11. 管理员密码历史表 (Admin Password History)
-- ============================================
-- 防止用户重复使用旧密码
DROP TABLE IF EXISTS `adminPasswordHistory`;
CREATE TABLE `adminPasswordHistory` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '历史ID',
  `userId` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
  `passwordHash` varchar(255) NOT NULL COMMENT '密码哈希',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idxUserId` (`userId`),
  KEY `idxCreatedAt` (`createdAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员密码历史表';


-- ============================================
-- 创建视图 (Views)
-- ============================================

-- 管理员完整信息视图
DROP VIEW IF EXISTS `vAdminUsersFull`;
CREATE VIEW `vAdminUsersFull` AS
SELECT
    u.id,
    u.username,
    u.email,
    u.fullName,
    u.avatarInitials,
    u.avatarColor,
    u.status,
    u.isLocked,
    u.lastLoginAt,
    u.lastLoginIp,
    u.createdAt,
    r.roleKey,
    r.roleName,
    r.roleDisplayName,
    r.badgeColor,
    r.level as roleLevel,
    p.phone,
    p.department,
    p.timezone,
    p.language
FROM adminUsers u
LEFT JOIN adminRoles r ON u.roleId = r.id
LEFT JOIN adminUserProfiles p ON u.id = p.userId
WHERE u.deletedAt IS NULL;

-- 管理员权限视图（包含角色权限和自定义权限）
DROP VIEW IF EXISTS `vAdminUserPermissions`;
CREATE VIEW `vAdminUserPermissions` AS
SELECT
    u.id as userId,
    u.username,
    u.email,
    p.permissionKey,
    p.permissionName,
    p.permissionDisplayName,
    p.module,
    'role' as permissionSource,
    1 as isGranted
FROM adminUsers u
INNER JOIN adminRoles r ON u.roleId = r.id
INNER JOIN adminRolePermissions rp ON r.id = rp.roleId
INNER JOIN adminPermissions p ON rp.permissionId = p.id
WHERE u.deletedAt IS NULL AND p.isActive = 1
UNION
SELECT
    up.userId,
    u.username,
    u.email,
    p.permissionKey,
    p.permissionName,
    p.permissionDisplayName,
    p.module,
    'custom' as permissionSource,
    up.isGranted
FROM adminUserPermissions up
INNER JOIN adminUsers u ON up.userId = u.id
INNER JOIN adminPermissions p ON up.permissionId = p.id
WHERE u.deletedAt IS NULL AND p.isActive = 1
AND (up.expiresAt IS NULL OR up.expiresAt > NOW());

-- ============================================
-- 存储过程 (Stored Procedures)
-- ============================================

-- 记录登录日志的存储过程
DROP PROCEDURE IF EXISTS `spLogAdminLogin`;
DELIMITER $$
CREATE PROCEDURE `spLogAdminLogin`(
    IN pUserId BIGINT,
    IN pUsername VARCHAR(100),
    IN pEmail VARCHAR(100),
    IN pStatus ENUM('success','failed','blocked'),
    IN pFailureReason VARCHAR(255),
    IN pIpAddress VARCHAR(45),
    IN pUserAgent TEXT,
    IN pRememberMe TINYINT
)
BEGIN
    DECLARE vSessionId VARCHAR(255);

    -- 生成会话ID
    SET vSessionId = UUID();

    -- 插入登录日志
    INSERT INTO adminLoginLogs (
        userId, username, email, loginStatus, failureReason,
        ipAddress, userAgent, rememberMe, sessionId
    ) VALUES (
        pUserId, pUsername, pEmail, pStatus, pFailureReason,
        pIpAddress, pUserAgent, pRememberMe, vSessionId
    );

    -- 如果登录成功，更新用户最后登录信息
    IF pStatus = 'success' AND pUserId IS NOT NULL THEN
        UPDATE adminUsers
        SET lastLoginAt = NOW(),
            lastLoginIp = pIpAddress,
            failedLoginAttempts = 0
        WHERE id = pUserId;
    END IF;

    -- 如果登录失败，增加失败次数
    IF pStatus = 'failed' AND pUserId IS NOT NULL THEN
        UPDATE adminUsers
        SET failedLoginAttempts = failedLoginAttempts + 1
        WHERE id = pUserId;

        -- 检查是否需要锁定账户
        UPDATE adminUsers
        SET isLocked = 1
        WHERE id = pUserId
        AND failedLoginAttempts >= (SELECT settingValue FROM adminSystemSettings WHERE settingKey = 'loginMaxAttempts');
    END IF;

    SELECT vSessionId as sessionId;
END$$
DELIMITER ;

-- 检查用户权限的存储过程
DROP PROCEDURE IF EXISTS `spCheckAdminPermission`;
DELIMITER $$
CREATE PROCEDURE `spCheckAdminPermission`(
    IN pUserId BIGINT,
    IN pPermissionKey VARCHAR(100)
)
BEGIN
    DECLARE vHasPermission TINYINT DEFAULT 0;

    -- 检查用户是否有该权限（通过角色或自定义授予）
    SELECT COUNT(*) INTO vHasPermission
    FROM vAdminUserPermissions
    WHERE userId = pUserId
    AND permissionKey = pPermissionKey
    AND isGranted = 1;

    SELECT vHasPermission as hasPermission;
END$$
DELIMITER ;

-- 创建管理员账户的存储过程
DROP PROCEDURE IF EXISTS `spCreateAdminUser`;
DELIMITER $$
CREATE PROCEDURE `spCreateAdminUser`(
    IN pUsername VARCHAR(50),
    IN pEmail VARCHAR(100),
    IN pPasswordHash VARCHAR(255),
    IN pFullName VARCHAR(100),
    IN pRoleId INT,
    IN pStatus ENUM('active','inactive'),
    IN pCreatedBy BIGINT,
    OUT pNewUserId BIGINT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET pNewUserId = NULL;
    END;

    START TRANSACTION;

    -- 生成头像缩写
    SET @initials = UPPER(CONCAT(
        SUBSTRING(SUBSTRING_INDEX(pFullName, ' ', 1), 1, 1),
        SUBSTRING(SUBSTRING_INDEX(pFullName, ' ', -1), 1, 1)
    ));

    -- 插入用户
    INSERT INTO adminUsers (
        username, email, passwordHash, fullName,
        avatarInitials, roleId, status, createdBy
    ) VALUES (
        pUsername, pEmail, pPasswordHash, pFullName,
        @initials, pRoleId, pStatus, pCreatedBy
    );

    SET pNewUserId = LAST_INSERT_ID();

    -- 创建用户扩展资料
    INSERT INTO adminUserProfiles (userId) VALUES (pNewUserId);

    COMMIT;
END$$
DELIMITER ;

-- ============================================
-- 触发器 (Triggers)
-- ============================================

-- 用户创建后自动创建资料
DROP TRIGGER IF EXISTS `trgAdminUsersAfterInsert`;
DELIMITER $$
CREATE TRIGGER `trgAdminUsersAfterInsert`
AFTER INSERT ON `adminUsers`
FOR EACH ROW
BEGIN
    -- 如果资料不存在则创建
    INSERT IGNORE INTO adminUserProfiles (userId) VALUES (NEW.id);
END$$
DELIMITER ;

-- 密码更改后记录历史
DROP TRIGGER IF EXISTS `trgAdminUsersPasswordUpdate`;
DELIMITER $$
CREATE TRIGGER `trgAdminUsersPasswordUpdate`
AFTER UPDATE ON `adminUsers`
FOR EACH ROW
BEGIN
    IF NEW.passwordHash != OLD.passwordHash THEN
        INSERT INTO adminPasswordHistory (userId, passwordHash)
        VALUES (NEW.id, OLD.passwordHash);
    END IF;
END$$
DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- 索引优化建议
-- ============================================
-- 以下是额外的索引建议，根据实际查询情况添加

-- ALTER TABLE adminUsers ADD INDEX idxEmailStatus (email, status);
-- ALTER TABLE adminLoginLogs ADD INDEX idxUserDate (userId, createdAt);

-- ============================================
-- 数据库创建完成
-- ============================================
--
-- 使用说明：
-- 1. 执行此SQL文件创建所有表和对象
-- 2. 默认管理员账户密码为加密后的值，需要通过应用程序重置
-- 3. 所有时间字段使用服务器时区，建议统一使用UTC
-- 4. 定期清理过期的会话、令牌和登录日志数据
-- 5. 建议定期备份 adminUsers 和 adminLoginLogs 表
--
-- 更新日志 (2025-10-07):
-- v2.0 - 大幅简化数据库结构
-- - 简化 adminUserProfiles 表，移除不常用字段
-- - 用户下拉菜单更新为：My Profile（模态框）、Change Password（模态框）、Logout
-- - 移除 Account Settings 和 Help & Support 相关配置
-- - 移除通知系统 (adminNotifications 表)
-- - 移除活动日志系统 (adminActivityLogs 表)
-- - 移除安全监控 (adminAuditTrail 和 adminSecurityEvents 表)
-- - 保留核心功能：用户管理、角色权限、登录认证、密码安全
-- - 数据库表数量从 15 个精简至 11 个
--
-- ============================================
