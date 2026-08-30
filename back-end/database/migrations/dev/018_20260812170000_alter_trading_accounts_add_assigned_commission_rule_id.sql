-- ============================================================
-- Trading account rebate rule assignment
-- Table: tradingAccounts
-- ============================================================
-- Created: 2026-08-12
-- Environment: dev
-- Closest migration: 017_20260812100000_seed_report_data_source_all_transactions.sql
-- ============================================================
-- Assign a trading account to a specific IB commission rule (rebate rate).
-- Null = keep auto rule matching (existing behavior).
-- ============================================================
-- BACKUP FIRST: run scripts/backup.sh before applying.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

ALTER TABLE `tradingAccounts`
  ADD COLUMN `assignedCommissionRuleId` INT(11) UNSIGNED NULL
    COMMENT 'ibCommissionRules.id for this account rebate rate'
    AFTER `accountType`,
  ADD KEY `idx_assignedCommissionRuleId` (`assignedCommissionRuleId`);

COMMIT;

-- ============================================================
-- Rollback
-- ============================================================
-- START TRANSACTION;
-- ALTER TABLE `tradingAccounts`
--   DROP KEY `idx_assignedCommissionRuleId`,
--   DROP COLUMN `assignedCommissionRuleId`;
-- COMMIT;
