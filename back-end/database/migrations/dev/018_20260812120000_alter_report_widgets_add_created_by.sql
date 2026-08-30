-- ============================================================
-- Custom Reports: track widget creator
-- Table: report_widgets (ALTER)
-- ============================================================
-- Created: 2026-08-12
-- Environment: dev
-- Closest migration: 013_20260721115900_alter_report_widgets_add_name.sql
-- ============================================================
-- BACKUP FIRST: run scripts/backup.sh before applying.
-- Idempotent: skips ADD COLUMN if created_by already exists.
-- Nullable so existing rows remain valid without backfill.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

SET @db := DATABASE();

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `report_widgets` ADD COLUMN `created_by` CHAR(36) DEFAULT NULL AFTER `name`',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'report_widgets' AND COLUMN_NAME = 'created_by'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;
