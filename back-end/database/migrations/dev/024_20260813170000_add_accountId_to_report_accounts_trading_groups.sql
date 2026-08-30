-- ============================================================
-- Custom Reports: add platform Account ID (providerAccountId)
-- Same value as Client Detail "Account ID"
-- ============================================================
-- Created: 2026-08-13
-- Environment: dev
-- Closest migration: 023_20260813153000_sales_managers_detail_child_sources.sql
-- ============================================================
-- BACKUP FIRST: run scripts/backup.sh before applying.
-- Idempotent: DROP/CREATE VIEW + INSERT ... ON DUPLICATE KEY UPDATE.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP VIEW IF EXISTS `vReportTradingGroups`;
DROP VIEW IF EXISTS `vReportAccounts`;

CREATE VIEW `vReportAccounts` AS
SELECT
    ta.`id` AS `tradingAccountId`,
    tea.`providerAccountId` AS `accountId`,
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

CREATE VIEW `vReportTradingGroups` AS
SELECT
    `groupId`,
    `groupTradingId`,
    `groupName`,
    `groupLabel`,
    `platformKey`,
    `platformName`,
    `tradingAccountId`,
    `accountId`,
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

INSERT INTO `report_data_source_fields` (`id`, `data_source_id`, `display_name`, `column_name`, `data_type`, `field_role`) VALUES
  ('b1000000-0000-4000-8000-000000000131', 'b1000000-0000-4000-8000-000000000001', 'Account ID', 'accountId', 'string', 'dimension'),
  ('b3000000-0000-4000-8000-000000000122', 'b3000000-0000-4000-8000-000000000001', 'Account ID', 'accountId', 'string', 'dimension')
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `data_type`    = VALUES(`data_type`),
  `field_role`   = VALUES(`field_role`);
