-- ============================================================
-- Custom Reports: Sales Managers row-detail child sources
-- Views + report_data_sources + detail panel mapping
-- MySQL 5.7: no subqueries in a view FROM clause; helpers are real views.
-- ============================================================
-- Created: 2026-08-13
-- Environment: dev
-- Closest migration: 022_20260813143800_alter_vReportSalesManagers_one_row_per_sales.sql
-- ============================================================
-- BACKUP FIRST: run scripts/backup.sh before applying.
-- Idempotent: CREATE OR REPLACE VIEW + INSERT ... ON DUPLICATE KEY UPDATE.
-- ALTER is_detail_only is not re-runnable if the column already exists.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

ALTER TABLE `report_data_sources`
  ADD COLUMN `is_detail_only` TINYINT(1) NOT NULL DEFAULT 0
  COMMENT '1 = hidden from widget picker; used only as a row-detail child source'
  AFTER `query_handler`;

CREATE TABLE IF NOT EXISTS `report_data_source_detail_panels` (
  `id` CHAR(36) NOT NULL,
  `parent_data_source_id` CHAR(36) NOT NULL,
  `child_data_source_id` CHAR(36) NOT NULL,
  `parent_field` VARCHAR(255) NOT NULL,
  `child_field` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_report_detail_panels_parent` (`parent_data_source_id`),
  CONSTRAINT `fk_report_detail_panels_parent`
    FOREIGN KEY (`parent_data_source_id`) REFERENCES `report_data_sources` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_report_detail_panels_child`
    FOREIGN KEY (`child_data_source_id`) REFERENCES `report_data_sources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Child data sources shown when expanding a custom-report parent row';

DROP VIEW IF EXISTS `vReportSalesClients`;
DROP VIEW IF EXISTS `vReportSalesIbs`;
DROP VIEW IF EXISTS `vReportClientTradeCounts`;
DROP VIEW IF EXISTS `vReportIbCommissionTotals`;

CREATE VIEW `vReportIbCommissionTotals` AS
SELECT
    `ibPartnerId` AS `ibId`,
    SUM(`commission`) AS `totalCommission`
FROM `ib_commission_order`
WHERE `status` = 'completed'
GROUP BY `ibPartnerId`;

CREATE VIEW `vReportClientTradeCounts` AS
SELECT
    ta.`userId` AS `userId`,
    COUNT(*) AS `tradeCount`
FROM `orders` o
INNER JOIN `tradingAccountExternalAccounts` tea ON tea.`providerAccountId` = o.`trading_login`
INNER JOIN `tradingAccounts` ta ON ta.`id` = tea.`tradingAccountId`
GROUP BY ta.`userId`;

CREATE VIEW `vReportSalesIbs` AS
SELECT
    sb.`salesId` AS `salesId`,
    ib.`id` AS `ibId`,
    ib.`userId` AS `userId`,
    ib.`ibCode` AS `ibCode`,
    COALESCE(
        NULLIF(TRIM(CONCAT(IFNULL(cu.`firstName`, ''), ' ', IFNULL(cu.`lastName`, ''))), ''),
        ib.`companyName`
    ) AS `ibName`,
    ib.`adminAlias` AS `adminAlias`,
    COALESCE(cu.`email`, ib.`contactEmail`) AS `email`,
    TRIM(CONCAT(IFNULL(cu.`phoneCountryCode`, ''), ' ', IFNULL(cu.`phone`, ib.`contactPhone`))) AS `phone`,
    COALESCE(cl.`name`, cu.`country`) AS `country`,
    ib.`totalClients` AS `clientsCount`,
    COALESCE(comm.`totalCommission`, 0) AS `totalCommission`,
    ib.`status` AS `status`
FROM `sales_bind` sb
INNER JOIN `ibPartners` ib ON ib.`userId` = sb.`clientId` AND ib.`status` = 'approved'
LEFT JOIN `clientUsers` cu ON cu.`id` = ib.`userId`
LEFT JOIN `countryList` cl ON cl.`code` = cu.`country`
LEFT JOIN `vReportIbCommissionTotals` comm ON comm.`ibId` = ib.`id`;

CREATE VIEW `vReportSalesClients` AS
SELECT
    sb.`salesId` AS `salesId`,
    cu.`id` AS `clientId`,
    cu.`id` AS `userId`,
    cu.`firstName` AS `firstName`,
    cu.`lastName` AS `lastName`,
    cu.`email` AS `email`,
    TRIM(CONCAT(IFNULL(cu.`phoneCountryCode`, ''), ' ', IFNULL(cu.`phone`, ''))) AS `phone`,
    COALESCE(cl.`name`, cu.`country`) AS `country`,
    cu.`kycStatus` AS `kycStatus`,
    COALESCE(ac.`accountCount`, 0) AS `accountCount`,
    COALESCE(ac.`totalBalance`, 0) AS `balance`,
    COALESCE(tr.`tradeCount`, 0) AS `trades`
FROM `sales_bind` sb
INNER JOIN `clientUsers` cu ON cu.`id` = sb.`clientId`
LEFT JOIN `ibPartners` ib ON ib.`userId` = sb.`clientId` AND ib.`status` = 'approved'
LEFT JOIN `countryList` cl ON cl.`code` = cu.`country`
LEFT JOIN `vReportClientAccountCounts` ac ON ac.`userId` = cu.`id`
LEFT JOIN `vReportClientTradeCounts` tr ON tr.`userId` = cu.`id`
WHERE ib.`userId` IS NULL;

START TRANSACTION;

INSERT INTO `report_data_sources` (
  `id`, `display_name`, `source_type`, `schema_name`, `object_name`, `query_handler`, `is_detail_only`, `created_at`
) VALUES
  ('b4000000-0000-4000-8000-000000000001', 'Sales IBs', 'view', NULL, 'vReportSalesIbs', NULL, 1, CURRENT_TIMESTAMP),
  ('b5000000-0000-4000-8000-000000000001', 'Sales Clients', 'view', NULL, 'vReportSalesClients', NULL, 1, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `source_type`  = VALUES(`source_type`),
  `schema_name`  = VALUES(`schema_name`),
  `object_name`  = VALUES(`object_name`),
  `is_detail_only` = VALUES(`is_detail_only`);

DELETE FROM `report_data_source_fields`
WHERE `data_source_id` IN (
  'b4000000-0000-4000-8000-000000000001',
  'b5000000-0000-4000-8000-000000000001'
);

INSERT INTO `report_data_source_fields` (`id`, `data_source_id`, `display_name`, `column_name`, `data_type`, `field_role`) VALUES
  ('b4000000-0000-4000-8000-000000000101', 'b4000000-0000-4000-8000-000000000001', 'IB Code', 'ibCode', 'string', 'dimension'),
  ('b4000000-0000-4000-8000-000000000102', 'b4000000-0000-4000-8000-000000000001', 'IB Name', 'ibName', 'string', 'dimension'),
  ('b4000000-0000-4000-8000-000000000103', 'b4000000-0000-4000-8000-000000000001', 'Admin Alias', 'adminAlias', 'string', 'dimension'),
  ('b4000000-0000-4000-8000-000000000104', 'b4000000-0000-4000-8000-000000000001', 'Email', 'email', 'string', 'dimension'),
  ('b4000000-0000-4000-8000-000000000105', 'b4000000-0000-4000-8000-000000000001', 'Phone', 'phone', 'string', 'dimension'),
  ('b4000000-0000-4000-8000-000000000106', 'b4000000-0000-4000-8000-000000000001', 'Country', 'country', 'string', 'dimension'),
  ('b4000000-0000-4000-8000-000000000107', 'b4000000-0000-4000-8000-000000000001', 'Clients Count', 'clientsCount', 'integer', 'measure'),
  ('b4000000-0000-4000-8000-000000000108', 'b4000000-0000-4000-8000-000000000001', 'Total Commission', 'totalCommission', 'decimal', 'measure'),
  ('b4000000-0000-4000-8000-000000000109', 'b4000000-0000-4000-8000-000000000001', 'Status', 'status', 'string', 'dimension'),
  ('b4000000-0000-4000-8000-000000000110', 'b4000000-0000-4000-8000-000000000001', 'IB ID', 'ibId', 'integer', 'dimension'),
  ('b4000000-0000-4000-8000-000000000111', 'b4000000-0000-4000-8000-000000000001', 'Sales ID', 'salesId', 'integer', 'dimension'),
  ('b4000000-0000-4000-8000-000000000112', 'b4000000-0000-4000-8000-000000000001', 'User ID', 'userId', 'integer', 'dimension'),

  ('b5000000-0000-4000-8000-000000000101', 'b5000000-0000-4000-8000-000000000001', 'Client ID', 'clientId', 'integer', 'dimension'),
  ('b5000000-0000-4000-8000-000000000102', 'b5000000-0000-4000-8000-000000000001', 'First Name', 'firstName', 'string', 'dimension'),
  ('b5000000-0000-4000-8000-000000000103', 'b5000000-0000-4000-8000-000000000001', 'Last Name', 'lastName', 'string', 'dimension'),
  ('b5000000-0000-4000-8000-000000000104', 'b5000000-0000-4000-8000-000000000001', 'Email', 'email', 'string', 'dimension'),
  ('b5000000-0000-4000-8000-000000000105', 'b5000000-0000-4000-8000-000000000001', 'Phone', 'phone', 'string', 'dimension'),
  ('b5000000-0000-4000-8000-000000000106', 'b5000000-0000-4000-8000-000000000001', 'Country', 'country', 'string', 'dimension'),
  ('b5000000-0000-4000-8000-000000000107', 'b5000000-0000-4000-8000-000000000001', 'KYC Status', 'kycStatus', 'string', 'dimension'),
  ('b5000000-0000-4000-8000-000000000108', 'b5000000-0000-4000-8000-000000000001', 'Balance', 'balance', 'decimal', 'measure'),
  ('b5000000-0000-4000-8000-000000000109', 'b5000000-0000-4000-8000-000000000001', 'Trades', 'trades', 'integer', 'measure'),
  ('b5000000-0000-4000-8000-000000000110', 'b5000000-0000-4000-8000-000000000001', 'Account Count', 'accountCount', 'integer', 'measure'),
  ('b5000000-0000-4000-8000-000000000111', 'b5000000-0000-4000-8000-000000000001', 'Sales ID', 'salesId', 'integer', 'dimension'),
  ('b5000000-0000-4000-8000-000000000112', 'b5000000-0000-4000-8000-000000000001', 'User ID', 'userId', 'integer', 'dimension')
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `data_type`    = VALUES(`data_type`),
  `field_role`   = VALUES(`field_role`);

INSERT INTO `report_data_source_detail_panels` (
  `id`, `parent_data_source_id`, `child_data_source_id`, `parent_field`, `child_field`, `title`, `sort_order`
) VALUES
  ('b2000000-0000-4000-8000-000000000201', 'b2000000-0000-4000-8000-000000000001', 'b4000000-0000-4000-8000-000000000001', 'salesId', 'salesId', 'IBs Under This Sales', 1),
  ('b2000000-0000-4000-8000-000000000202', 'b2000000-0000-4000-8000-000000000001', 'b5000000-0000-4000-8000-000000000001', 'salesId', 'salesId', 'Clients Under This Sales', 2)
ON DUPLICATE KEY UPDATE
  `parent_data_source_id` = VALUES(`parent_data_source_id`),
  `child_data_source_id`  = VALUES(`child_data_source_id`),
  `parent_field`          = VALUES(`parent_field`),
  `child_field`           = VALUES(`child_field`),
  `title`                 = VALUES(`title`),
  `sort_order`            = VALUES(`sort_order`);

COMMIT;

-- ============================================================
-- Rollback
-- ============================================================
-- START TRANSACTION;
-- DELETE FROM `report_data_source_detail_panels` WHERE `id` IN (
--   'b2000000-0000-4000-8000-000000000201',
--   'b2000000-0000-4000-8000-000000000202'
-- );
-- DELETE FROM `report_data_source_fields` WHERE `data_source_id` IN (
--   'b4000000-0000-4000-8000-000000000001',
--   'b5000000-0000-4000-8000-000000000001'
-- );
-- DELETE FROM `report_data_sources` WHERE `id` IN (
--   'b4000000-0000-4000-8000-000000000001',
--   'b5000000-0000-4000-8000-000000000001'
-- );
-- COMMIT;
-- DROP TABLE IF EXISTS `report_data_source_detail_panels`;
-- ALTER TABLE `report_data_sources` DROP COLUMN `is_detail_only`;
-- DROP VIEW IF EXISTS `vReportSalesClients`;
-- DROP VIEW IF EXISTS `vReportSalesIbs`;
-- DROP VIEW IF EXISTS `vReportClientTradeCounts`;
-- DROP VIEW IF EXISTS `vReportIbCommissionTotals`;
