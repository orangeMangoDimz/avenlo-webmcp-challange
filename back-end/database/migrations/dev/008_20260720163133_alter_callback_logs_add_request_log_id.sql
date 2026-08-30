-- ============================================================
-- Link paymentProcessorCallbackLogs → paymentProcessorRequestLogs
-- Soft ref: requestLogId + correlationMethod
-- ============================================================
-- Created: 2026-07-20
-- Environment: dev
-- Closest migration: 006_20260720155634_create_payment_processor_request_logs.sql
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
    'ALTER TABLE `paymentProcessorCallbackLogs` ADD COLUMN `requestLogId` BIGINT(20) UNSIGNED DEFAULT NULL COMMENT ''Soft ref to paymentProcessorRequestLogs.id; no FK'' AFTER `withdrawalId`',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'paymentProcessorCallbackLogs' AND COLUMN_NAME = 'requestLogId'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `paymentProcessorCallbackLogs` ADD COLUMN `correlationMethod` VARCHAR(32) DEFAULT NULL COMMENT ''provider_order_id|local_order_id|deposit_id|withdrawal_id|unmatched'' AFTER `requestLogId`',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'paymentProcessorCallbackLogs' AND COLUMN_NAME = 'correlationMethod'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `paymentProcessorCallbackLogs` ADD KEY `idxRequestLogId` (`requestLogId`)',
    'SELECT 1'
  )
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'paymentProcessorCallbackLogs' AND INDEX_NAME = 'idxRequestLogId'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;

-- ============================================================
-- Rollback
-- ============================================================
-- START TRANSACTION;
-- ALTER TABLE `paymentProcessorCallbackLogs`
--   DROP KEY `idxRequestLogId`,
--   DROP COLUMN `correlationMethod`,
--   DROP COLUMN `requestLogId`;
-- COMMIT;
