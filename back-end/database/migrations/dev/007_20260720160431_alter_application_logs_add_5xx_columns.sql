-- ============================================================
-- Application logs: structured 5xx request metadata columns
-- Table: applicationLogs (ALTER)
-- ============================================================
-- Created: 2026-07-20
-- Environment: dev
-- Closest migration: 003_20260720104614_create_application_logs_table.sql
-- DDL source: docs/APPLICATION_ERROR_LOGGING_TDD.md §7.2
-- ============================================================
-- BACKUP FIRST: run scripts/backup.sh before applying.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Idempotent column adds (safe if re-run on partially applied env)
SET @db := DATABASE();

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `applicationLogs` ADD COLUMN `requestMethod` VARCHAR(10) DEFAULT NULL AFTER `source`',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'applicationLogs' AND COLUMN_NAME = 'requestMethod'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `applicationLogs` ADD COLUMN `requestPath` VARCHAR(512) DEFAULT NULL AFTER `requestMethod`',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'applicationLogs' AND COLUMN_NAME = 'requestPath'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `applicationLogs` ADD COLUMN `route` VARCHAR(255) DEFAULT NULL AFTER `requestPath`',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'applicationLogs' AND COLUMN_NAME = 'route'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `applicationLogs` ADD COLUMN `actualHttpStatus` SMALLINT UNSIGNED DEFAULT NULL AFTER `route`',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'applicationLogs' AND COLUMN_NAME = 'actualHttpStatus'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `applicationLogs` ADD COLUMN `applicationStatusCode` SMALLINT UNSIGNED DEFAULT NULL AFTER `actualHttpStatus`',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'applicationLogs' AND COLUMN_NAME = 'applicationStatusCode'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `applicationLogs` ADD COLUMN `errorCode` VARCHAR(128) DEFAULT NULL AFTER `applicationStatusCode`',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'applicationLogs' AND COLUMN_NAME = 'errorCode'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `applicationLogs` ADD COLUMN `failureType` VARCHAR(32) DEFAULT NULL AFTER `errorCode`',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'applicationLogs' AND COLUMN_NAME = 'failureType'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `applicationLogs` ADD COLUMN `durationMs` INT UNSIGNED DEFAULT NULL AFTER `failureType`',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'applicationLogs' AND COLUMN_NAME = 'durationMs'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `applicationLogs` ADD KEY `idx_applicationLogs_applicationStatusCreated` (`applicationStatusCode`, `createdAt`)',
    'SELECT 1'
  )
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'applicationLogs' AND INDEX_NAME = 'idx_applicationLogs_applicationStatusCreated'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `applicationLogs` ADD KEY `idx_applicationLogs_requestPathCreated` (`requestPath`, `createdAt`)',
    'SELECT 1'
  )
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'applicationLogs' AND INDEX_NAME = 'idx_applicationLogs_requestPathCreated'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `applicationLogs` ADD KEY `idx_applicationLogs_failureTypeCreated` (`failureType`, `createdAt`)',
    'SELECT 1'
  )
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'applicationLogs' AND INDEX_NAME = 'idx_applicationLogs_failureTypeCreated'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;

-- ============================================================
-- Rollback
-- ============================================================
-- START TRANSACTION;
-- ALTER TABLE `applicationLogs`
--   DROP KEY `idx_applicationLogs_applicationStatusCreated`,
--   DROP KEY `idx_applicationLogs_requestPathCreated`,
--   DROP KEY `idx_applicationLogs_failureTypeCreated`,
--   DROP COLUMN `requestMethod`,
--   DROP COLUMN `requestPath`,
--   DROP COLUMN `route`,
--   DROP COLUMN `actualHttpStatus`,
--   DROP COLUMN `applicationStatusCode`,
--   DROP COLUMN `errorCode`,
--   DROP COLUMN `failureType`,
--   DROP COLUMN `durationMs`;
-- COMMIT;
