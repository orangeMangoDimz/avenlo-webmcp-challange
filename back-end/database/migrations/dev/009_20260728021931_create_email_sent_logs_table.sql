-- ============================================================
-- Outbound email sent logs
-- Table: emailSentLogs
-- ============================================================
-- Created: 2026-07-28
-- Environment: dev
-- Closest migration: 006_20260720155634_create_payment_processor_request_logs.sql
-- Schema style: applicationLogs / paymentProcessorRequestLogs (camelCase)
-- ============================================================
-- BACKUP FIRST: run scripts/backup.sh before applying.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `emailSentLogs` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender` VARCHAR(255) NOT NULL,
  `recipient` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(500) NOT NULL,
  `content` VARCHAR(2000) DEFAULT NULL COMMENT 'Truncated body preview; app truncates before insert',
  `provider` ENUM('api','swoole') NOT NULL COMMENT 'Call origin, not mail driver',
  `status` ENUM('success','failed') NOT NULL,
  `errorMessage` VARCHAR(500) DEFAULT NULL,
  `stackTrace` TEXT DEFAULT NULL,
  `relatedType` VARCHAR(64) DEFAULT NULL COMMENT 'e.g. deposit, withdrawal, client_notification',
  `relatedId` BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'Soft ref only; no FK',
  `meta` JSON DEFAULT NULL,
  `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC wall clock',
  PRIMARY KEY (`id`),
  KEY `idx_emailSentLogs_createdAt` (`createdAt`),
  KEY `idx_emailSentLogs_status_createdAt` (`status`, `createdAt`),
  KEY `idx_emailSentLogs_provider_createdAt` (`provider`, `createdAt`),
  KEY `idx_emailSentLogs_recipient` (`recipient`),
  KEY `idx_emailSentLogs_related` (`relatedType`, `relatedId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Outbound email send attempts (one row per attempt)';

COMMIT;

-- ============================================================
-- Rollback
-- ============================================================
-- START TRANSACTION;
-- DROP TABLE IF EXISTS `emailSentLogs`;
-- COMMIT;
