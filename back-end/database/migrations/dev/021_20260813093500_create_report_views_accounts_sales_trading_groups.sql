-- ============================================================
-- Custom Reports: Accounts / Sales Managers / Trading Groups
-- Views + report_data_sources + report_data_source_fields
-- MySQL 5.7: no subqueries in a view FROM clause; helpers are real views.
-- ============================================================
-- Created: 2026-08-13
-- Environment: dev
-- Closest migration: 017_20260812100000_seed_report_data_source_all_transactions.sql
-- ============================================================
-- BACKUP FIRST: run scripts/backup.sh before applying.
-- Idempotent: CREATE OR REPLACE VIEW + INSERT ... ON DUPLICATE KEY UPDATE.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP VIEW IF EXISTS `vReportTradingGroups`;
DROP VIEW IF EXISTS `vReportSalesManagers`;
DROP VIEW IF EXISTS `vReportAccounts`;
DROP VIEW IF EXISTS `vReportClientAccountCounts`;
DROP VIEW IF EXISTS `vReportClientFunding`;
DROP VIEW IF EXISTS `vReportAccountBalanceTotals`;

CREATE VIEW `vReportAccountBalanceTotals` AS
SELECT
    `trading_account_id` AS `tradingAccountId`,
    SUM(`balance`) AS `balance`,
    SUM(`equity`) AS `equity`,
    SUM(`margin`) AS `margin`,
    SUM(`free_margin`) AS `freeMargin`,
    SUM(`credit`) AS `credit`
FROM `trading_account_balances`
GROUP BY `trading_account_id`;

CREATE VIEW `vReportClientFunding` AS
SELECT
    `userId` AS `userId`,
    SUM(CASE WHEN `transactionType` = 'deposit' AND `status` = 'completed' THEN `amount` ELSE 0 END) AS `depositAmount`,
    SUM(CASE WHEN `transactionType` = 'withdrawal' AND `status` = 'completed' THEN `amount` ELSE 0 END) AS `withdrawalAmount`,
    SUM(CASE WHEN `transactionType` = 'deposit' AND `status` = 'completed' THEN 1 ELSE 0 END) AS `depositCount`,
    SUM(CASE WHEN `transactionType` = 'withdrawal' AND `status` = 'completed' THEN 1 ELSE 0 END) AS `withdrawalCount`
FROM `vAllTransactions`
WHERE `transactionType` IN ('deposit', 'withdrawal')
GROUP BY `userId`;

CREATE VIEW `vReportClientAccountCounts` AS
SELECT
    ta.`userId` AS `userId`,
    COUNT(*) AS `accountCount`,
    COALESCE(SUM(bal.`balance`), 0) AS `totalBalance`,
    COALESCE(SUM(bal.`equity`), 0) AS `totalEquity`
FROM `tradingAccounts` ta
LEFT JOIN `vReportAccountBalanceTotals` bal ON bal.`tradingAccountId` = ta.`id`
GROUP BY ta.`userId`;

CREATE VIEW `vReportAccounts` AS
SELECT
    ta.`id` AS `tradingAccountId`,
    ta.`accountNumber` AS `accountNumber`,
    ta.`accountNickname` AS `accountNickname`,
    ta.`accountCurrency` AS `accountCurrency`,
    ta.`accountType` AS `accountType`,
    ta.`leverageValue` AS `leverageValue`,
    ta.`status` AS `status`,
    tp.`platformKey` AS `platformKey`,
    tp.`displayName` AS `platformName`,
    ta.`userId` AS `userId`,
    cu.`firstName` AS `firstName`,
    cu.`lastName` AS `lastName`,
    cu.`email` AS `email`,
    cu.`kycStatus` AS `kycStatus`,
    su.`id` AS `salesId`,
    COALESCE(NULLIF(TRIM(su.`fullName`), ''), su.`username`) AS `salesName`,
    su.`email` AS `salesEmail`,
    sr.`roleDisplayName` AS `salesRole`,
    tg.`id` AS `groupId`,
    tg.`name` AS `groupName`,
    COALESCE(NULLIF(TRIM(tg.`label`), ''), tg.`name`) AS `groupLabel`,
    tg.`trading_id` AS `groupTradingId`,
    ta.`initialDeposit` AS `initialDeposit`,
    COALESCE(bal.`balance`, tea.`platformBalance`, 0) AS `balance`,
    COALESCE(bal.`equity`, 0) AS `equity`,
    COALESCE(bal.`margin`, 0) AS `margin`,
    COALESCE(bal.`freeMargin`, 0) AS `freeMargin`,
    COALESCE(bal.`credit`, tea.`platformCredit`, 0) AS `credit`,
    ta.`createdAt` AS `createdAt`,
    ta.`updatedAt` AS `updatedAt`
FROM `tradingAccounts` ta
INNER JOIN `tradingPlatforms` tp ON tp.`id` = ta.`platformId`
INNER JOIN `clientUsers` cu ON cu.`id` = ta.`userId`
LEFT JOIN `adminUsers` su ON su.`id` = cu.`accountManagerId`
LEFT JOIN `adminRoles` sr ON sr.`id` = su.`roleId`
LEFT JOIN `tradingAccountExternalAccounts` tea ON tea.`tradingAccountId` = ta.`id`
LEFT JOIN `trading_group` tg
    ON tg.`trading_platforms_key` = tp.`platformKey`
   AND (
        (tp.`platformKey` IN ('mt5', 'mt4') AND tg.`id` = tea.`groupId`)
        OR
        (tp.`platformKey` NOT IN ('mt5', 'mt4') AND tg.`trading_id` = tea.`groupId`)
   )
LEFT JOIN `vReportAccountBalanceTotals` bal ON bal.`tradingAccountId` = ta.`id`;

CREATE VIEW `vReportSalesManagers` AS
SELECT
    sb.`id` AS `salesBindId`,
    sb.`salesId` AS `salesId`,
    COALESCE(NULLIF(TRIM(su.`fullName`), ''), su.`username`) AS `salesName`,
    su.`email` AS `salesEmail`,
    su.`username` AS `salesUsername`,
    sr.`roleDisplayName` AS `salesRole`,
    sr.`roleKey` AS `salesRoleKey`,
    sb.`clientId` AS `clientId`,
    cu.`firstName` AS `firstName`,
    cu.`lastName` AS `lastName`,
    cu.`email` AS `email`,
    cu.`kycStatus` AS `kycStatus`,
    COALESCE(ac.`accountCount`, 0) AS `accountCount`,
    COALESCE(ac.`totalBalance`, 0) AS `totalBalance`,
    COALESCE(f.`depositAmount`, 0) AS `depositAmount`,
    COALESCE(f.`withdrawalAmount`, 0) AS `withdrawalAmount`,
    (COALESCE(f.`depositAmount`, 0) - COALESCE(f.`withdrawalAmount`, 0)) AS `netDeposit`,
    COALESCE(f.`depositCount`, 0) AS `depositCount`,
    COALESCE(f.`withdrawalCount`, 0) AS `withdrawalCount`,
    sb.`createdAt` AS `boundAt`,
    cu.`createdAt` AS `clientCreatedAt`
FROM `sales_bind` sb
INNER JOIN `clientUsers` cu ON cu.`id` = sb.`clientId`
LEFT JOIN `adminUsers` su ON su.`id` = sb.`salesId`
LEFT JOIN `adminRoles` sr ON sr.`id` = su.`roleId`
LEFT JOIN `vReportClientAccountCounts` ac ON ac.`userId` = sb.`clientId`
LEFT JOIN `vReportClientFunding` f ON f.`userId` = sb.`clientId`;

CREATE VIEW `vReportTradingGroups` AS
SELECT
    `groupId`,
    `groupTradingId`,
    `groupName`,
    `groupLabel`,
    `platformKey`,
    `platformName`,
    `tradingAccountId`,
    `accountNumber`,
    `accountNickname`,
    `accountCurrency`,
    `status`,
    `userId`,
    `firstName`,
    `lastName`,
    `email`,
    `salesId`,
    `salesName`,
    `balance`,
    `equity`,
    `credit`,
    `createdAt`
FROM `vReportAccounts`
WHERE `groupId` IS NOT NULL;

START TRANSACTION;

INSERT INTO `report_data_sources` (
  `id`, `display_name`, `source_type`, `schema_name`, `object_name`, `query_handler`, `created_at`
) VALUES
  ('b1000000-0000-4000-8000-000000000001', 'Accounts', 'view', NULL, 'vReportAccounts', NULL, CURRENT_TIMESTAMP),
  ('b2000000-0000-4000-8000-000000000001', 'Sales Managers', 'view', NULL, 'vReportSalesManagers', NULL, CURRENT_TIMESTAMP),
  ('b3000000-0000-4000-8000-000000000001', 'Trading Groups', 'view', NULL, 'vReportTradingGroups', NULL, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `source_type`  = VALUES(`source_type`),
  `schema_name`  = VALUES(`schema_name`),
  `object_name`  = VALUES(`object_name`);

INSERT INTO `report_data_source_fields` (`id`, `data_source_id`, `display_name`, `column_name`, `data_type`, `field_role`) VALUES
  ('b1000000-0000-4000-8000-000000000101', 'b1000000-0000-4000-8000-000000000001', 'Trading Account ID', 'tradingAccountId', 'integer', 'dimension'),
  ('b1000000-0000-4000-8000-000000000102', 'b1000000-0000-4000-8000-000000000001', 'Account Number', 'accountNumber', 'string', 'dimension'),
  ('b1000000-0000-4000-8000-000000000103', 'b1000000-0000-4000-8000-000000000001', 'Account Nickname', 'accountNickname', 'string', 'dimension'),
  ('b1000000-0000-4000-8000-000000000104', 'b1000000-0000-4000-8000-000000000001', 'Account Currency', 'accountCurrency', 'string', 'dimension'),
  ('b1000000-0000-4000-8000-000000000105', 'b1000000-0000-4000-8000-000000000001', 'Account Type', 'accountType', 'string', 'dimension'),
  ('b1000000-0000-4000-8000-000000000106', 'b1000000-0000-4000-8000-000000000001', 'Leverage', 'leverageValue', 'string', 'dimension'),
  ('b1000000-0000-4000-8000-000000000107', 'b1000000-0000-4000-8000-000000000001', 'Status', 'status', 'string', 'dimension'),
  ('b1000000-0000-4000-8000-000000000108', 'b1000000-0000-4000-8000-000000000001', 'Platform Key', 'platformKey', 'string', 'dimension'),
  ('b1000000-0000-4000-8000-000000000109', 'b1000000-0000-4000-8000-000000000001', 'Platform Name', 'platformName', 'string', 'dimension'),
  ('b1000000-0000-4000-8000-000000000110', 'b1000000-0000-4000-8000-000000000001', 'User ID', 'userId', 'integer', 'dimension'),
  ('b1000000-0000-4000-8000-000000000111', 'b1000000-0000-4000-8000-000000000001', 'First Name', 'firstName', 'string', 'dimension'),
  ('b1000000-0000-4000-8000-000000000112', 'b1000000-0000-4000-8000-000000000001', 'Last Name', 'lastName', 'string', 'dimension'),
  ('b1000000-0000-4000-8000-000000000113', 'b1000000-0000-4000-8000-000000000001', 'Email', 'email', 'string', 'dimension'),
  ('b1000000-0000-4000-8000-000000000114', 'b1000000-0000-4000-8000-000000000001', 'KYC Status', 'kycStatus', 'string', 'dimension'),
  ('b1000000-0000-4000-8000-000000000115', 'b1000000-0000-4000-8000-000000000001', 'Sales ID', 'salesId', 'integer', 'dimension'),
  ('b1000000-0000-4000-8000-000000000116', 'b1000000-0000-4000-8000-000000000001', 'Sales Name', 'salesName', 'string', 'dimension'),
  ('b1000000-0000-4000-8000-000000000117', 'b1000000-0000-4000-8000-000000000001', 'Sales Email', 'salesEmail', 'string', 'dimension'),
  ('b1000000-0000-4000-8000-000000000118', 'b1000000-0000-4000-8000-000000000001', 'Sales Role', 'salesRole', 'string', 'dimension'),
  ('b1000000-0000-4000-8000-000000000119', 'b1000000-0000-4000-8000-000000000001', 'Group ID', 'groupId', 'integer', 'dimension'),
  ('b1000000-0000-4000-8000-000000000120', 'b1000000-0000-4000-8000-000000000001', 'Group Name', 'groupName', 'string', 'dimension'),
  ('b1000000-0000-4000-8000-000000000121', 'b1000000-0000-4000-8000-000000000001', 'Group Label', 'groupLabel', 'string', 'dimension'),
  ('b1000000-0000-4000-8000-000000000122', 'b1000000-0000-4000-8000-000000000001', 'Group Trading ID', 'groupTradingId', 'integer', 'dimension'),
  ('b1000000-0000-4000-8000-000000000123', 'b1000000-0000-4000-8000-000000000001', 'Initial Deposit', 'initialDeposit', 'decimal', 'measure'),
  ('b1000000-0000-4000-8000-000000000124', 'b1000000-0000-4000-8000-000000000001', 'Balance', 'balance', 'decimal', 'measure'),
  ('b1000000-0000-4000-8000-000000000125', 'b1000000-0000-4000-8000-000000000001', 'Equity', 'equity', 'decimal', 'measure'),
  ('b1000000-0000-4000-8000-000000000126', 'b1000000-0000-4000-8000-000000000001', 'Margin', 'margin', 'decimal', 'measure'),
  ('b1000000-0000-4000-8000-000000000127', 'b1000000-0000-4000-8000-000000000001', 'Free Margin', 'freeMargin', 'decimal', 'measure'),
  ('b1000000-0000-4000-8000-000000000128', 'b1000000-0000-4000-8000-000000000001', 'Credit', 'credit', 'decimal', 'measure'),
  ('b1000000-0000-4000-8000-000000000129', 'b1000000-0000-4000-8000-000000000001', 'Created At', 'createdAt', 'datetime', 'datetime'),
  ('b1000000-0000-4000-8000-000000000130', 'b1000000-0000-4000-8000-000000000001', 'Updated At', 'updatedAt', 'datetime', 'datetime'),

  ('b2000000-0000-4000-8000-000000000101', 'b2000000-0000-4000-8000-000000000001', 'Sales Bind ID', 'salesBindId', 'integer', 'dimension'),
  ('b2000000-0000-4000-8000-000000000102', 'b2000000-0000-4000-8000-000000000001', 'Sales ID', 'salesId', 'integer', 'dimension'),
  ('b2000000-0000-4000-8000-000000000103', 'b2000000-0000-4000-8000-000000000001', 'Sales Name', 'salesName', 'string', 'dimension'),
  ('b2000000-0000-4000-8000-000000000104', 'b2000000-0000-4000-8000-000000000001', 'Sales Email', 'salesEmail', 'string', 'dimension'),
  ('b2000000-0000-4000-8000-000000000105', 'b2000000-0000-4000-8000-000000000001', 'Sales Username', 'salesUsername', 'string', 'dimension'),
  ('b2000000-0000-4000-8000-000000000106', 'b2000000-0000-4000-8000-000000000001', 'Sales Role', 'salesRole', 'string', 'dimension'),
  ('b2000000-0000-4000-8000-000000000107', 'b2000000-0000-4000-8000-000000000001', 'Sales Role Key', 'salesRoleKey', 'string', 'dimension'),
  ('b2000000-0000-4000-8000-000000000108', 'b2000000-0000-4000-8000-000000000001', 'Client ID', 'clientId', 'integer', 'dimension'),
  ('b2000000-0000-4000-8000-000000000109', 'b2000000-0000-4000-8000-000000000001', 'First Name', 'firstName', 'string', 'dimension'),
  ('b2000000-0000-4000-8000-000000000110', 'b2000000-0000-4000-8000-000000000001', 'Last Name', 'lastName', 'string', 'dimension'),
  ('b2000000-0000-4000-8000-000000000111', 'b2000000-0000-4000-8000-000000000001', 'Email', 'email', 'string', 'dimension'),
  ('b2000000-0000-4000-8000-000000000112', 'b2000000-0000-4000-8000-000000000001', 'KYC Status', 'kycStatus', 'string', 'dimension'),
  ('b2000000-0000-4000-8000-000000000113', 'b2000000-0000-4000-8000-000000000001', 'Account Count', 'accountCount', 'integer', 'measure'),
  ('b2000000-0000-4000-8000-000000000114', 'b2000000-0000-4000-8000-000000000001', 'Total Balance', 'totalBalance', 'decimal', 'measure'),
  ('b2000000-0000-4000-8000-000000000115', 'b2000000-0000-4000-8000-000000000001', 'Deposit Amount', 'depositAmount', 'decimal', 'measure'),
  ('b2000000-0000-4000-8000-000000000116', 'b2000000-0000-4000-8000-000000000001', 'Withdrawal Amount', 'withdrawalAmount', 'decimal', 'measure'),
  ('b2000000-0000-4000-8000-000000000117', 'b2000000-0000-4000-8000-000000000001', 'Net Deposit', 'netDeposit', 'decimal', 'measure'),
  ('b2000000-0000-4000-8000-000000000118', 'b2000000-0000-4000-8000-000000000001', 'Deposit Count', 'depositCount', 'integer', 'measure'),
  ('b2000000-0000-4000-8000-000000000119', 'b2000000-0000-4000-8000-000000000001', 'Withdrawal Count', 'withdrawalCount', 'integer', 'measure'),
  ('b2000000-0000-4000-8000-000000000120', 'b2000000-0000-4000-8000-000000000001', 'Bound At', 'boundAt', 'datetime', 'datetime'),
  ('b2000000-0000-4000-8000-000000000121', 'b2000000-0000-4000-8000-000000000001', 'Client Created At', 'clientCreatedAt', 'datetime', 'datetime'),

  ('b3000000-0000-4000-8000-000000000101', 'b3000000-0000-4000-8000-000000000001', 'Group ID', 'groupId', 'integer', 'dimension'),
  ('b3000000-0000-4000-8000-000000000102', 'b3000000-0000-4000-8000-000000000001', 'Group Trading ID', 'groupTradingId', 'integer', 'dimension'),
  ('b3000000-0000-4000-8000-000000000103', 'b3000000-0000-4000-8000-000000000001', 'Group Name', 'groupName', 'string', 'dimension'),
  ('b3000000-0000-4000-8000-000000000104', 'b3000000-0000-4000-8000-000000000001', 'Group Label', 'groupLabel', 'string', 'dimension'),
  ('b3000000-0000-4000-8000-000000000105', 'b3000000-0000-4000-8000-000000000001', 'Platform Key', 'platformKey', 'string', 'dimension'),
  ('b3000000-0000-4000-8000-000000000106', 'b3000000-0000-4000-8000-000000000001', 'Platform Name', 'platformName', 'string', 'dimension'),
  ('b3000000-0000-4000-8000-000000000107', 'b3000000-0000-4000-8000-000000000001', 'Trading Account ID', 'tradingAccountId', 'integer', 'dimension'),
  ('b3000000-0000-4000-8000-000000000108', 'b3000000-0000-4000-8000-000000000001', 'Account Number', 'accountNumber', 'string', 'dimension'),
  ('b3000000-0000-4000-8000-000000000109', 'b3000000-0000-4000-8000-000000000001', 'Account Nickname', 'accountNickname', 'string', 'dimension'),
  ('b3000000-0000-4000-8000-000000000110', 'b3000000-0000-4000-8000-000000000001', 'Account Currency', 'accountCurrency', 'string', 'dimension'),
  ('b3000000-0000-4000-8000-000000000111', 'b3000000-0000-4000-8000-000000000001', 'Status', 'status', 'string', 'dimension'),
  ('b3000000-0000-4000-8000-000000000112', 'b3000000-0000-4000-8000-000000000001', 'User ID', 'userId', 'integer', 'dimension'),
  ('b3000000-0000-4000-8000-000000000113', 'b3000000-0000-4000-8000-000000000001', 'First Name', 'firstName', 'string', 'dimension'),
  ('b3000000-0000-4000-8000-000000000114', 'b3000000-0000-4000-8000-000000000001', 'Last Name', 'lastName', 'string', 'dimension'),
  ('b3000000-0000-4000-8000-000000000115', 'b3000000-0000-4000-8000-000000000001', 'Email', 'email', 'string', 'dimension'),
  ('b3000000-0000-4000-8000-000000000116', 'b3000000-0000-4000-8000-000000000001', 'Sales ID', 'salesId', 'integer', 'dimension'),
  ('b3000000-0000-4000-8000-000000000117', 'b3000000-0000-4000-8000-000000000001', 'Sales Name', 'salesName', 'string', 'dimension'),
  ('b3000000-0000-4000-8000-000000000118', 'b3000000-0000-4000-8000-000000000001', 'Balance', 'balance', 'decimal', 'measure'),
  ('b3000000-0000-4000-8000-000000000119', 'b3000000-0000-4000-8000-000000000001', 'Equity', 'equity', 'decimal', 'measure'),
  ('b3000000-0000-4000-8000-000000000120', 'b3000000-0000-4000-8000-000000000001', 'Credit', 'credit', 'decimal', 'measure'),
  ('b3000000-0000-4000-8000-000000000121', 'b3000000-0000-4000-8000-000000000001', 'Created At', 'createdAt', 'datetime', 'datetime')
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `data_type`    = VALUES(`data_type`),
  `field_role`   = VALUES(`field_role`);

COMMIT;

-- ============================================================
-- Rollback
-- ============================================================
-- START TRANSACTION;
-- DELETE FROM `report_data_source_fields` WHERE `data_source_id` IN (
--   'b1000000-0000-4000-8000-000000000001',
--   'b2000000-0000-4000-8000-000000000001',
--   'b3000000-0000-4000-8000-000000000001'
-- );
-- DELETE FROM `report_data_sources` WHERE `id` IN (
--   'b1000000-0000-4000-8000-000000000001',
--   'b2000000-0000-4000-8000-000000000001',
--   'b3000000-0000-4000-8000-000000000001'
-- );
-- COMMIT;
-- DROP VIEW IF EXISTS `vReportTradingGroups`;
-- DROP VIEW IF EXISTS `vReportSalesManagers`;
-- DROP VIEW IF EXISTS `vReportAccounts`;
-- DROP VIEW IF EXISTS `vReportClientAccountCounts`;
-- DROP VIEW IF EXISTS `vReportClientFunding`;
-- DROP VIEW IF EXISTS `vReportAccountBalanceTotals`;
