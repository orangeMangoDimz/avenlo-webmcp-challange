-- ============================================================
-- IB tier badge color (editable from admin IB Tier Levels page)
-- Table: ibTierLevels
-- ============================================================
-- Created: 2026-08-07
-- Environment: dev
-- Closest migration: 010_20260806091733_create_payment_gateway_quick_amounts.sql
-- ============================================================
-- BACKUP FIRST: run scripts/backup.sh before applying.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

ALTER TABLE `ibTierLevels`
  ADD COLUMN `badgeColor` VARCHAR(7) NOT NULL DEFAULT '#475569' AFTER `tierDescription`;

UPDATE `ibTierLevels` SET `badgeColor` = '#7c3aed' WHERE `tierLevel` = 1;
UPDATE `ibTierLevels` SET `badgeColor` = '#1d4ed8' WHERE `tierLevel` = 2;
UPDATE `ibTierLevels` SET `badgeColor` = '#0284c7' WHERE `tierLevel` = 3;

COMMIT;

-- ============================================================
-- Rollback
-- ============================================================
-- START TRANSACTION;
-- ALTER TABLE `ibTierLevels` DROP COLUMN `badgeColor`;
-- COMMIT;
