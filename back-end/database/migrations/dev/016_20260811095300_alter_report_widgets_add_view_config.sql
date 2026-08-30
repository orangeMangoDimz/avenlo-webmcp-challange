-- ============================================================
-- Custom Reports: widget view_config JSON + All Transactions fields
-- Table: report_widgets (ALTER), report_data_source_fields (seed)
-- ============================================================
-- Created: 2026-08-11
-- Environment: dev
-- Closest migration: 013_20260721115900_alter_report_widgets_add_name.sql
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
    'ALTER TABLE `report_widgets` ADD COLUMN `view_config` JSON DEFAULT NULL AFTER `name`',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'report_widgets' AND COLUMN_NAME = 'view_config'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `report_widgets`
SET `view_config` = JSON_OBJECT(
  'activeView', 'table',
  'views', JSON_OBJECT(
    'table', JSON_OBJECT(),
    'chart', JSON_OBJECT(
      'chartType', '',
      'xField', '',
      'yField', '',
      'sortBy', 'label_asc',
      'omitZero', false,
      'groupBy', 'none',
      'cumulative', false,
      'range', 'auto'
    )
  )
)
WHERE `view_config` IS NULL;

INSERT IGNORE INTO `report_data_source_fields` (`id`, `data_source_id`, `display_name`, `column_name`, `data_type`, `field_role`)
SELECT seed.`id`, seed.`data_source_id`, seed.`display_name`, seed.`column_name`, seed.`data_type`, seed.`field_role`
FROM (
  SELECT 'a1000000-0000-4000-8000-000000000101' AS `id`, 'a1000000-0000-4000-8000-000000000001' AS `data_source_id`, 'Date / Time' AS `display_name`, 'requestedAt' AS `column_name`, 'datetime' AS `data_type`, 'datetime' AS `field_role`
  UNION ALL SELECT 'a1000000-0000-4000-8000-000000000102', 'a1000000-0000-4000-8000-000000000001', 'First Name', 'firstName', 'string', 'dimension'
  UNION ALL SELECT 'a1000000-0000-4000-8000-000000000103', 'a1000000-0000-4000-8000-000000000001', 'Last Name', 'lastName', 'string', 'dimension'
  UNION ALL SELECT 'a1000000-0000-4000-8000-000000000104', 'a1000000-0000-4000-8000-000000000001', 'Email', 'email', 'string', 'dimension'
  UNION ALL SELECT 'a1000000-0000-4000-8000-000000000105', 'a1000000-0000-4000-8000-000000000001', 'Type', 'transactionType', 'string', 'dimension'
  UNION ALL SELECT 'a1000000-0000-4000-8000-000000000106', 'a1000000-0000-4000-8000-000000000001', 'Amount', 'amount', 'decimal', 'measure'
  UNION ALL SELECT 'a1000000-0000-4000-8000-000000000107', 'a1000000-0000-4000-8000-000000000001', 'Payment Method', 'paymentMethod', 'string', 'dimension'
  UNION ALL SELECT 'a1000000-0000-4000-8000-000000000108', 'a1000000-0000-4000-8000-000000000001', 'Status', 'status', 'string', 'dimension'
  UNION ALL SELECT 'a1000000-0000-4000-8000-000000000109', 'a1000000-0000-4000-8000-000000000001', 'User ID', 'userId', 'integer', 'dimension'
  UNION ALL SELECT 'a1000000-0000-4000-8000-000000000110', 'a1000000-0000-4000-8000-000000000001', 'Transaction ID', 'transactionId', 'string', 'dimension'
) AS seed
INNER JOIN `report_data_sources` ds ON ds.id = seed.data_source_id;

COMMIT;

-- ============================================================
-- Rollback
-- ============================================================
-- START TRANSACTION;
-- DELETE FROM `report_data_source_fields` WHERE `data_source_id` = 'a1000000-0000-4000-8000-000000000001'
--   AND `id` LIKE 'a1000000-0000-4000-8000-0000000001%';
-- ALTER TABLE `report_widgets` DROP COLUMN `view_config`;
-- COMMIT;
