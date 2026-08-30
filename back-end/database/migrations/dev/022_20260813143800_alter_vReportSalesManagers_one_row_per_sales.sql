-- ============================================================
-- Custom Reports: vReportSalesManagers is 1 row per sales person
-- Same people as Sales List; same IB/client split as GET /api/sales
-- ============================================================
-- Created: 2026-08-13
-- Environment: dev
-- Closest migration: 021_20260813093500_create_report_views_accounts_sales_trading_groups.sql
-- ============================================================
-- BACKUP FIRST: run scripts/backup.sh before applying.
-- Idempotent: DROP/CREATE VIEW + DELETE/INSERT fields.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP VIEW IF EXISTS `vReportSalesManagers`;
DROP VIEW IF EXISTS `vReportSalesBindCounts`;
DROP VIEW IF EXISTS `vReportSalesFundingTotals`;
DROP VIEW IF EXISTS `vReportSalesAccountTotals`;
DROP VIEW IF EXISTS `vReportSalesIbCounts`;
DROP VIEW IF EXISTS `vReportSalesClientCounts`;

CREATE VIEW `vReportSalesClientCounts` AS
SELECT
    sb.`salesId` AS `salesId`,
    COUNT(*) AS `totalClients`
FROM `sales_bind` sb
LEFT JOIN `ibPartners` ib ON ib.`userId` = sb.`clientId` AND ib.`status` = 'approved'
WHERE ib.`userId` IS NULL
GROUP BY sb.`salesId`;

CREATE VIEW `vReportSalesIbCounts` AS
SELECT
    sb.`salesId` AS `salesId`,
    COUNT(DISTINCT ib.`id`) AS `totalIbs`
FROM `sales_bind` sb
INNER JOIN `ibPartners` ib ON ib.`userId` = sb.`clientId` AND ib.`status` = 'approved'
GROUP BY sb.`salesId`;

CREATE VIEW `vReportSalesAccountTotals` AS
SELECT
    sb.`salesId` AS `salesId`,
    COALESCE(SUM(ac.`accountCount`), 0) AS `accountCount`,
    COALESCE(SUM(ac.`totalBalance`), 0) AS `totalBalance`
FROM `sales_bind` sb
LEFT JOIN `vReportClientAccountCounts` ac ON ac.`userId` = sb.`clientId`
GROUP BY sb.`salesId`;

CREATE VIEW `vReportSalesFundingTotals` AS
SELECT
    sb.`salesId` AS `salesId`,
    COALESCE(SUM(f.`depositAmount`), 0) AS `depositAmount`,
    COALESCE(SUM(f.`withdrawalAmount`), 0) AS `withdrawalAmount`,
    COALESCE(SUM(f.`depositCount`), 0) AS `depositCount`,
    COALESCE(SUM(f.`withdrawalCount`), 0) AS `withdrawalCount`
FROM `sales_bind` sb
LEFT JOIN `vReportClientFunding` f ON f.`userId` = sb.`clientId`
GROUP BY sb.`salesId`;

CREATE VIEW `vReportSalesBindCounts` AS
SELECT
    `salesId` AS `salesId`,
    COUNT(*) AS `bindCount`
FROM `sales_bind`
GROUP BY `salesId`;

CREATE VIEW `vReportSalesManagers` AS
SELECT
    su.`id` AS `salesId`,
    COALESCE(NULLIF(TRIM(su.`fullName`), ''), su.`username`) AS `salesName`,
    su.`email` AS `salesEmail`,
    su.`username` AS `salesUsername`,
    sr.`roleDisplayName` AS `salesRole`,
    sr.`roleKey` AS `salesRoleKey`,
    su.`status` AS `status`,
    COALESCE(ibc.`totalIbs`, 0) AS `totalIbs`,
    COALESCE(clc.`totalClients`, 0) AS `totalClients`,
    COALESCE(bc.`bindCount`, 0) AS `bindCount`,
    COALESCE(ac.`accountCount`, 0) AS `accountCount`,
    COALESCE(ac.`totalBalance`, 0) AS `totalBalance`,
    COALESCE(f.`depositAmount`, 0) AS `depositAmount`,
    COALESCE(f.`withdrawalAmount`, 0) AS `withdrawalAmount`,
    (COALESCE(f.`depositAmount`, 0) - COALESCE(f.`withdrawalAmount`, 0)) AS `netDeposit`,
    COALESCE(f.`depositCount`, 0) AS `depositCount`,
    COALESCE(f.`withdrawalCount`, 0) AS `withdrawalCount`,
    su.`createdAt` AS `joinDate`
FROM `adminUsers` su
LEFT JOIN `adminRoles` sr ON sr.`id` = su.`roleId`
LEFT JOIN `vReportSalesIbCounts` ibc ON ibc.`salesId` = su.`id`
LEFT JOIN `vReportSalesClientCounts` clc ON clc.`salesId` = su.`id`
LEFT JOIN `vReportSalesBindCounts` bc ON bc.`salesId` = su.`id`
LEFT JOIN `vReportSalesAccountTotals` ac ON ac.`salesId` = su.`id`
LEFT JOIN `vReportSalesFundingTotals` f ON f.`salesId` = su.`id`
WHERE su.`deletedAt` IS NULL
  AND (
        su.`roleId` = 6
        OR su.`roleId` = 1
        OR su.`roleId` IN (
            SELECT arp.`roleId`
            FROM `adminRolePermissions` arp
            INNER JOIN `adminPermissions` p ON p.`id` = arp.`permissionId`
            WHERE p.`permissionKey` = 'page_salesdashboard_view'
        )
  );

START TRANSACTION;

DELETE FROM `report_data_source_fields`
WHERE `data_source_id` = 'b2000000-0000-4000-8000-000000000001';

INSERT INTO `report_data_source_fields` (`id`, `data_source_id`, `display_name`, `column_name`, `data_type`, `field_role`) VALUES
  ('b2000000-0000-4000-8000-000000000101', 'b2000000-0000-4000-8000-000000000001', 'Sales ID', 'salesId', 'integer', 'dimension'),
  ('b2000000-0000-4000-8000-000000000102', 'b2000000-0000-4000-8000-000000000001', 'Sales Name', 'salesName', 'string', 'dimension'),
  ('b2000000-0000-4000-8000-000000000103', 'b2000000-0000-4000-8000-000000000001', 'Sales Email', 'salesEmail', 'string', 'dimension'),
  ('b2000000-0000-4000-8000-000000000104', 'b2000000-0000-4000-8000-000000000001', 'Sales Username', 'salesUsername', 'string', 'dimension'),
  ('b2000000-0000-4000-8000-000000000105', 'b2000000-0000-4000-8000-000000000001', 'Sales Role', 'salesRole', 'string', 'dimension'),
  ('b2000000-0000-4000-8000-000000000106', 'b2000000-0000-4000-8000-000000000001', 'Sales Role Key', 'salesRoleKey', 'string', 'dimension'),
  ('b2000000-0000-4000-8000-000000000107', 'b2000000-0000-4000-8000-000000000001', 'Status', 'status', 'string', 'dimension'),
  ('b2000000-0000-4000-8000-000000000108', 'b2000000-0000-4000-8000-000000000001', 'Total IBs', 'totalIbs', 'integer', 'measure'),
  ('b2000000-0000-4000-8000-000000000109', 'b2000000-0000-4000-8000-000000000001', 'Total Clients', 'totalClients', 'integer', 'measure'),
  ('b2000000-0000-4000-8000-000000000110', 'b2000000-0000-4000-8000-000000000001', 'Bind Count', 'bindCount', 'integer', 'measure'),
  ('b2000000-0000-4000-8000-000000000111', 'b2000000-0000-4000-8000-000000000001', 'Account Count', 'accountCount', 'integer', 'measure'),
  ('b2000000-0000-4000-8000-000000000112', 'b2000000-0000-4000-8000-000000000001', 'Total Balance', 'totalBalance', 'decimal', 'measure'),
  ('b2000000-0000-4000-8000-000000000113', 'b2000000-0000-4000-8000-000000000001', 'Deposit Amount', 'depositAmount', 'decimal', 'measure'),
  ('b2000000-0000-4000-8000-000000000114', 'b2000000-0000-4000-8000-000000000001', 'Withdrawal Amount', 'withdrawalAmount', 'decimal', 'measure'),
  ('b2000000-0000-4000-8000-000000000115', 'b2000000-0000-4000-8000-000000000001', 'Net Deposit', 'netDeposit', 'decimal', 'measure'),
  ('b2000000-0000-4000-8000-000000000116', 'b2000000-0000-4000-8000-000000000001', 'Deposit Count', 'depositCount', 'integer', 'measure'),
  ('b2000000-0000-4000-8000-000000000117', 'b2000000-0000-4000-8000-000000000001', 'Withdrawal Count', 'withdrawalCount', 'integer', 'measure'),
  ('b2000000-0000-4000-8000-000000000118', 'b2000000-0000-4000-8000-000000000001', 'Join Date', 'joinDate', 'datetime', 'datetime')
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `column_name`  = VALUES(`column_name`),
  `data_type`    = VALUES(`data_type`),
  `field_role`   = VALUES(`field_role`);

COMMIT;

-- ============================================================
-- Rollback: re-run 021 to restore bind-level vReportSalesManagers
-- ============================================================
