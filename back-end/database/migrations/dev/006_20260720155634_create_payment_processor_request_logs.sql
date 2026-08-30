-- ============================================================
-- Payment processor outbound request logs
-- Table: paymentProcessorRequestLogs
-- ============================================================
-- Created: 2026-07-20
-- Environment: dev
-- Closest migration: 003_20260720104614_create_application_logs_table.sql
-- Schema style: paymentProcessorCallbackLogs (all_crm_update_260408.sql)
-- DDL source: docs/PAYMENT_PROCESSOR_REQUEST_LOGS_TDD.md §5.4
-- ============================================================
-- BACKUP FIRST: run scripts/backup.sh before applying.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `paymentProcessorRequestLogs` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` VARCHAR(50) NOT NULL,
  `environment` ENUM('sandbox','production') NOT NULL DEFAULT 'production',
  `transactionType` ENUM('deposit','withdrawal') NOT NULL,
  `operation` VARCHAR(50) NOT NULL,
  `deliveryMode` ENUM('client_redirect','server_http') NOT NULL,
  `depositId` BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'Soft ref only; no FK',
  `withdrawalId` BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'Soft ref only; no FK',
  `localOrderId` VARCHAR(100) NOT NULL,
  `providerOrderId` VARCHAR(255) DEFAULT NULL,
  `providerRequestId` VARCHAR(255) DEFAULT NULL,
  `attemptNo` INT UNSIGNED NOT NULL DEFAULT 1,
  `idempotencyKey` VARCHAR(255) DEFAULT NULL,
  `amount` DECIMAL(20,8) DEFAULT NULL,
  `currencyCode` VARCHAR(20) DEFAULT NULL,
  `requestMethod` VARCHAR(10) DEFAULT NULL,
  `endpointPath` VARCHAR(255) DEFAULT NULL,
  `requestPayload` JSON DEFAULT NULL,
  `responseHttpStatus` SMALLINT UNSIGNED DEFAULT NULL,
  `providerStatus` VARCHAR(64) DEFAULT NULL,
  `responsePayload` JSON DEFAULT NULL,
  `requestStatus` ENUM('prepared','redirect_issued','sent','accepted','failed','timeout','unknown') NOT NULL DEFAULT 'prepared',
  `errorCode` VARCHAR(100) DEFAULT NULL,
  `errorMessage` VARCHAR(500) DEFAULT NULL,
  `requestId` VARCHAR(128) DEFAULT NULL,
  `correlationId` VARCHAR(128) DEFAULT NULL,
  `startedAt` DATETIME DEFAULT NULL,
  `completedAt` DATETIME DEFAULT NULL,
  `durationMs` INT UNSIGNED DEFAULT NULL,
  `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC wall clock',
  `updatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'UTC wall clock',
  PRIMARY KEY (`id`),
  KEY `idxPprlDepositCreated` (`depositId`, `createdAt`),
  KEY `idxPprlWithdrawalCreated` (`withdrawalId`, `createdAt`),
  KEY `idxPprlLocalOrder` (`localOrderId`),
  KEY `idxPprlProviderOrder` (`provider`, `providerOrderId`),
  KEY `idxPprlStatusCreated` (`requestStatus`, `createdAt`),
  KEY `idxPprlCorrelationId` (`correlationId`),
  CONSTRAINT `chk_paymentProcessorRequestLogs_depositOrWithdrawal`
    CHECK (
      (
        `transactionType` = 'deposit'
        AND `depositId` IS NOT NULL
        AND `withdrawalId` IS NULL
      )
      OR (
        `transactionType` = 'withdrawal'
        AND `withdrawalId` IS NOT NULL
        AND `depositId` IS NULL
      )
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Outbound payment processor request attempts';

COMMIT;

-- ============================================================
-- Rollback
-- ============================================================
-- START TRANSACTION;
-- DROP TABLE IF EXISTS `paymentProcessorRequestLogs`;
-- COMMIT;
