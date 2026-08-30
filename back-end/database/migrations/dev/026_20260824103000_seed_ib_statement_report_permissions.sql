SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

START TRANSACTION;

INSERT INTO `adminPermissions`
(`permissionKey`, `permissionName`, `permissionDisplayName`, `permissionDisplayNameZh`, `description`,
 `module`, `route`, `is_menu`, `parentId`, `sortOrder`, `isActive`, `createdAt`, `updatedAt`)
SELECT
    'page_ibstatement', 'IB P&L Report', 'IB P&L Report', 'IB 盈亏报告',
    'IB client P&L statement report page',
    NULL, '/ib-statement', 1, p.`id`, 5, 1, NOW(), NOW()
FROM `adminPermissions` p
WHERE p.`permissionKey` = 'group_report'
  AND NOT EXISTS (SELECT 1 FROM `adminPermissions` WHERE `permissionKey` = 'page_ibstatement');

INSERT INTO `adminPermissions`
(`permissionKey`, `permissionName`, `permissionDisplayName`, `permissionDisplayNameZh`, `description`,
 `module`, `route`, `is_menu`, `parentId`, `sortOrder`, `isActive`, `createdAt`, `updatedAt`)
SELECT
    'page_ibstatement_readonly', 'Read only', 'Read only', '只读',
    'View page data and show menu for IB P&L Report',
    NULL, NULL, 0, p.`id`, 50, 1, NOW(), NOW()
FROM `adminPermissions` p
WHERE p.`permissionKey` = 'page_ibstatement'
  AND NOT EXISTS (SELECT 1 FROM `adminPermissions` WHERE `permissionKey` = 'page_ibstatement_readonly');

INSERT INTO `adminPermissions`
(`permissionKey`, `permissionName`, `permissionDisplayName`, `permissionDisplayNameZh`, `description`,
 `module`, `route`, `is_menu`, `parentId`, `sortOrder`, `isActive`, `createdAt`, `updatedAt`)
SELECT
    'page_ibstatement_export', 'Export', 'Export', '导出',
    'Export IB P&L Report',
    NULL, NULL, 0, p.`id`, 49, 1, NOW(), NOW()
FROM `adminPermissions` p
WHERE p.`permissionKey` = 'page_ibstatement'
  AND NOT EXISTS (SELECT 1 FROM `adminPermissions` WHERE `permissionKey` = 'page_ibstatement_export');

INSERT INTO `adminPermissions`
(`permissionKey`, `permissionName`, `permissionDisplayName`, `permissionDisplayNameZh`, `description`,
 `module`, `route`, `is_menu`, `parentId`, `sortOrder`, `isActive`, `createdAt`, `updatedAt`)
SELECT
    'page_ibstatement_viewallclients', 'View All Clients', 'View All Clients', '查看所有客户',
    'View all IBs on IB P&L Report; without it, sales see only bound IBs',
    NULL, NULL, 0, p.`id`,
    (SELECT IFNULL(MIN(c.`sortOrder`), 1) - 1 FROM `adminPermissions` c WHERE c.`parentId` = p.`id`),
    1, NOW(), NOW()
FROM `adminPermissions` p
WHERE p.`permissionKey` = 'page_ibstatement'
  AND NOT EXISTS (SELECT 1 FROM `adminPermissions` WHERE `permissionKey` = 'page_ibstatement_viewallclients');

INSERT INTO `adminRolePermissions` (`roleId`, `permissionId`)
SELECT r.`id`, p.`id`
FROM `adminRoles` r
         INNER JOIN `adminPermissions` p
                    ON p.`permissionKey` IN (
                                             'page_ibstatement',
                                             'page_ibstatement_readonly',
                                             'page_ibstatement_export',
                                             'page_ibstatement_viewallclients'
                        )
WHERE r.`roleKey` IN ('admin', 'sales_manager')
  AND NOT EXISTS (
    SELECT 1 FROM `adminRolePermissions` rp
    WHERE rp.`roleId` = r.`id` AND rp.`permissionId` = p.`id`
);

INSERT INTO `adminRolePermissions` (`roleId`, `permissionId`)
SELECT r.`id`, p.`id`
FROM `adminRoles` r
         INNER JOIN `adminPermissions` p
                    ON p.`permissionKey` IN (
                                             'page_ibstatement',
                                             'page_ibstatement_readonly'
                        )
WHERE r.`roleKey` = 'sales'
  AND NOT EXISTS (
    SELECT 1 FROM `adminRolePermissions` rp
    WHERE rp.`roleId` = r.`id` AND rp.`permissionId` = p.`id`
);

COMMIT;
