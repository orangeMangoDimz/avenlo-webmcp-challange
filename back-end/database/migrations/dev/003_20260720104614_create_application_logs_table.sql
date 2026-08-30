-- ============================================================
-- Application logs schema
-- Table: applicationLogs
-- ============================================================
-- Created: 2026-07-20
-- Environment: dev
-- Closest migration: 001_create_custom_reports_tables.sql
-- Schema style: adminOperationLogs / paymentProcessorCallbackLogs (camelCase)
-- ============================================================
-- BACKUP FIRST: run scripts/backup.sh before applying.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `applicationLogs` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `level` VARCHAR(16) NOT NULL COMMENT 'ERROR, WARNING, INFO',
  `service` VARCHAR(64) NOT NULL COMMENT 'api, swoole, etc.',
  `environment` VARCHAR(32) NOT NULL,
  `message` TEXT NOT NULL,
  `context` JSON DEFAULT NULL,
  `exceptionClass` VARCHAR(255) DEFAULT NULL,
  `stackTrace` TEXT DEFAULT NULL,
  `requestId` VARCHAR(128) DEFAULT NULL,
  `correlationId` VARCHAR(128) DEFAULT NULL,
  `userId` BIGINT(20) DEFAULT NULL COMMENT 'Soft ref only; no FK',
  `userType` VARCHAR(32) DEFAULT NULL COMMENT 'admin, client, system, anonymous',
  `jobId` VARCHAR(128) DEFAULT NULL,
  `source` VARCHAR(128) DEFAULT NULL,
  `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC wall clock',
  PRIMARY KEY (`id`),
  KEY `idx_applicationLogs_createdAt` (`createdAt`),
  KEY `idx_applicationLogs_level_createdAt` (`level`, `createdAt`),
  KEY `idx_applicationLogs_service_createdAt` (`service`, `createdAt`),
  KEY `idx_applicationLogs_requestId` (`requestId`),
  KEY `idx_applicationLogs_correlationId` (`correlationId`),
  CONSTRAINT `chk_applicationLogs_level`
    CHECK (`level` IN ('ERROR', 'WARNING', 'INFO')),
  CONSTRAINT `chk_applicationLogs_userType`
    CHECK (`userType` IS NULL OR `userType` IN ('admin', 'client', 'system', 'anonymous'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Application / service error and info logs';

COMMIT;

-- ============================================================
-- Rollback
-- ============================================================
-- START TRANSACTION;
-- DROP TABLE IF EXISTS `applicationLogs`;
-- COMMIT;
