-- ============================================================
-- Custom Reports: widget display name
-- Table: report_widgets (ALTER)
-- ============================================================
-- Created: 2026-07-21
-- Environment: dev
-- Closest migration: 001_20260720104614_create_custom_reports_tables.sql
-- ============================================================
-- BACKUP FIRST: run scripts/backup.sh before applying.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

SET @db := DATABASE();

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `report_widgets` ADD COLUMN `name` VARCHAR(255) DEFAULT NULL AFTER `widget_type`',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'report_widgets' AND COLUMN_NAME = 'name'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `report_widgets` rw
INNER JOIN `report_data_sources` ds ON ds.id = rw.data_source_id
SET rw.name = ds.display_name
WHERE rw.name IS NULL;

COMMIT;

-- ============================================================
-- Rollback
-- ============================================================
-- START TRANSACTION;
-- SET @db := DATABASE();
-- SET @sql := (
--   SELECT IF(
--     COUNT(*) > 0,
--     'ALTER TABLE `report_widgets` DROP COLUMN `name`',
--     'SELECT 1'
--   )
--   FROM information_schema.COLUMNS
--   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'report_widgets' AND COLUMN_NAME = 'name'
-- );
-- PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
-- COMMIT;
