-- ============================================================
-- Daily Report (Sales) schema and permissions
-- Table: salesMonthlyKpis
-- ============================================================
-- Created: 2026-08-06
-- Environment: dev
-- Closest migration: 004_seed_application_logs_view_permission.sql
-- Schema style: camelCase, matching adminPermissions / adminRolePermissions
-- ============================================================
-- Daily Report lists one row per sales person for a selected day (deposit,
-- withdrawal, net deposit, new leads, new clients) and holds one net-deposit
-- KPI target per sales person per MONTH, compared against that person's
-- month-to-date net deposit.
--
-- Day boundaries are computed with the viewer's timezone offset (sent by the
-- frontend), falling back to UTC+10.
--
-- Idempotent: safe to re-run. Also migrates installs that received the first
-- cut of this feature (a company-wide daily KPI table under the Report group).
-- ============================================================
-- BACKUP FIRST: run scripts/backup.sh before applying.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ---------------------------------------------------------------------
-- Schema changes first. MySQL commits DDL implicitly, so these cannot sit
-- inside the transaction below - keeping them separate makes that explicit,
-- rather than wrapping them in a transaction that would not roll them back.
-- ---------------------------------------------------------------------

-- Drop the first cut of this feature (company-wide daily KPI table).
-- Harmless on installs that never had it.
DROP TABLE IF EXISTS `dailyReportKpis`;

-- Monthly KPI target per sales person
CREATE TABLE IF NOT EXISTS `salesMonthlyKpis` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `salesId` int(11) UNSIGNED NOT NULL,
  `kpiMonth` char(7) NOT NULL COMMENT 'YYYY-MM',
  `kpiTarget` decimal(18,2) NOT NULL DEFAULT 0.00,
  `updatedByAdminId` int(11) UNSIGNED DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `salesMonth` (`salesId`, `kpiMonth`),
  KEY `kpiMonth` (`kpiMonth`),
  KEY `updatedByAdminId` (`updatedByAdminId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Monthly net deposit KPI target per sales user';

-- ---------------------------------------------------------------------
-- Permission rows and grants.
-- ---------------------------------------------------------------------

START TRANSACTION;

-- 1) Drop the permission the first cut added (page-level sales scoping is now
--    decided by the Sales Manager role, not by a _viewallclients permission).
--    adminPermissions has no foreign keys pointing at it, so the per-user and
--    per-role grants have to be cleared by hand or they turn into orphans.
DELETE up FROM `adminUserPermissions` up
    INNER JOIN `adminPermissions` p ON p.`id` = up.`permissionId`
WHERE p.`permissionKey` = 'page_dailyreport_viewallclients';

DELETE rp FROM `adminRolePermissions` rp
    INNER JOIN `adminPermissions` p ON p.`id` = rp.`permissionId`
WHERE p.`permissionKey` = 'page_dailyreport_viewallclients';

DELETE FROM `adminPermissions` WHERE `permissionKey` = 'page_dailyreport_viewallclients';

-- 2) Page permission. Move it under group_sales if the first cut put it under
--    group_report, otherwise insert it fresh.
UPDATE `adminPermissions` p
    INNER JOIN `adminPermissions` g ON g.`permissionKey` = 'group_sales'
SET p.`parentId` = g.`id`, p.`sortOrder` = 3, p.`updatedAt` = NOW()
WHERE p.`permissionKey` = 'page_dailyreport';

INSERT INTO `adminPermissions`
(`permissionKey`, `permissionName`, `permissionDisplayName`, `permissionDisplayNameZh`, `description`,
 `module`, `route`, `is_menu`, `parentId`, `sortOrder`, `isActive`, `createdAt`, `updatedAt`)
SELECT
    'page_dailyreport', 'Daily Report', 'Daily Report', '每日报表',
    'Daily sales report page',
    NULL, '/daily-report', 1, p.`id`, 3, 1, NOW(), NOW()
FROM `adminPermissions` p
WHERE p.`permissionKey` = 'group_sales'
  AND NOT EXISTS (SELECT 1 FROM `adminPermissions` WHERE `permissionKey` = 'page_dailyreport');

-- 3) Page action permissions
INSERT INTO `adminPermissions`
(`permissionKey`, `permissionName`, `permissionDisplayName`, `permissionDisplayNameZh`, `description`,
 `module`, `route`, `is_menu`, `parentId`, `sortOrder`, `isActive`, `createdAt`, `updatedAt`)
SELECT
    'page_dailyreport_readonly', 'Read only', 'Read only', '只读',
    'View page data and show menu for Daily Report. A sales user sees only their own row.',
    NULL, NULL, 0, p.`id`, 50, 1, NOW(), NOW()
FROM `adminPermissions` p
WHERE p.`permissionKey` = 'page_dailyreport'
  AND NOT EXISTS (SELECT 1 FROM `adminPermissions` WHERE `permissionKey` = 'page_dailyreport_readonly');

INSERT INTO `adminPermissions`
(`permissionKey`, `permissionName`, `permissionDisplayName`, `permissionDisplayNameZh`, `description`,
 `module`, `route`, `is_menu`, `parentId`, `sortOrder`, `isActive`, `createdAt`, `updatedAt`)
SELECT
    'page_dailyreport_edit_kpi', 'Edit KPI', 'Edit KPI', '编辑 KPI',
    'Edit the monthly KPI target of any sales user on Daily Report page',
    NULL, NULL, 0, p.`id`, 49, 1, NOW(), NOW()
FROM `adminPermissions` p
WHERE p.`permissionKey` = 'page_dailyreport'
  AND NOT EXISTS (SELECT 1 FROM `adminPermissions` WHERE `permissionKey` = 'page_dailyreport_edit_kpi');

-- 4) Grants: admin and Sales Manager get everything including Edit KPI;
--    a plain Sales user gets read access only (own row, KPI read-only).
INSERT INTO `adminRolePermissions` (`roleId`, `permissionId`)
SELECT r.`id`, p.`id`
FROM `adminRoles` r
         INNER JOIN `adminPermissions` p
                    ON p.`permissionKey` IN (
                                             'page_dailyreport',
                                             'page_dailyreport_readonly',
                                             'page_dailyreport_edit_kpi'
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
                                             'page_dailyreport',
                                             'page_dailyreport_readonly'
                        )
WHERE r.`roleKey` = 'sales'
  AND NOT EXISTS (
    SELECT 1 FROM `adminRolePermissions` rp
    WHERE rp.`roleId` = r.`id` AND rp.`permissionId` = p.`id`
);

COMMIT;

-- ---------------------------------------------------------------------
-- Result check. Expect 3 permission rows and 8 grants; 0 permission rows means
-- group_sales is missing from this install, so nothing was created.
-- ---------------------------------------------------------------------
SELECT
    (SELECT COUNT(*) FROM `adminPermissions` WHERE `permissionKey` LIKE 'page_dailyreport%') AS permissionRows,
    (SELECT COUNT(*) FROM `adminRolePermissions` rp
        INNER JOIN `adminPermissions` p ON p.`id` = rp.`permissionId`
     WHERE p.`permissionKey` LIKE 'page_dailyreport%') AS grantRows,
    (SELECT COUNT(*) FROM `information_schema`.`TABLES`
     WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'salesMonthlyKpis') AS kpiTableExists;

-- ============================================================
-- Rollback
-- ============================================================
-- Removing the permissions hides the page everywhere; the deployed code is
-- harmless without them. salesMonthlyKpis is kept on purpose - dropping it
-- throws away every target anyone entered.
-- ============================================================
-- START TRANSACTION;
-- DELETE rp FROM `adminRolePermissions` rp
--     INNER JOIN `adminPermissions` p ON p.`id` = rp.`permissionId`
-- WHERE p.`permissionKey` LIKE 'page_dailyreport%';
-- DELETE up FROM `adminUserPermissions` up
--     INNER JOIN `adminPermissions` p ON p.`id` = up.`permissionId`
-- WHERE p.`permissionKey` LIKE 'page_dailyreport%';
-- DELETE FROM `adminPermissions` WHERE `permissionKey` LIKE 'page_dailyreport%';
-- COMMIT;
-- DROP TABLE IF EXISTS `salesMonthlyKpis`;
