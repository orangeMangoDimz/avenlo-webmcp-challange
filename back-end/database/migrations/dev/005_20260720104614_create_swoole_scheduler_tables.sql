-- ============================================================
-- Swoole scheduler + background job tracking
-- Tables: swooleSchedulerStatus, swooleSchedulerRuns, backgroundJobs
-- ============================================================
-- Created: 2026-07-20
-- Environment: dev
-- Closest migration: 003_create_application_logs_table.sql
-- FK style: 001_create_custom_reports_tables.sql
-- ============================================================
-- BACKUP FIRST: run scripts/backup.sh before applying.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `swooleSchedulerStatus` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `schedulerKey` VARCHAR(64) NOT NULL COMMENT 'Unique logical key, e.g. business_scheduler',
  `name` VARCHAR(128) NOT NULL COMMENT 'Human-readable name',
  `schedulerType` VARCHAR(32) NOT NULL COMMENT 'watchdog, business, maintenance',
  `intervalMs` INT(11) UNSIGNED NOT NULL COMMENT 'Expected tick interval in milliseconds',
  `status` VARCHAR(16) NOT NULL DEFAULT 'inactive' COMMENT 'active, inactive, error, stale',
  `workerId` INT(11) DEFAULT NULL COMMENT 'Swoole worker ID',
  `processId` INT(11) DEFAULT NULL COMMENT 'OS process ID',
  `serverInstanceId` VARCHAR(128) DEFAULT NULL COMMENT 'ID generated on Swoole start',
  `timerId` INT(11) DEFAULT NULL COMMENT 'Native Swoole timer ID',
  `lastTickAt` DATETIME DEFAULT NULL COMMENT 'Last timer fire (UTC)',
  `lastStartedAt` DATETIME DEFAULT NULL COMMENT 'Last execution start (UTC)',
  `lastCompletedAt` DATETIME DEFAULT NULL COMMENT 'Last successful completion (UTC)',
  `nextRunAt` DATETIME DEFAULT NULL COMMENT 'Expected next execution (UTC)',
  `lastDispatchedCount` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `lastErrorMessage` TEXT DEFAULT NULL,
  `heartbeatAt` DATETIME DEFAULT NULL COMMENT 'Latest scheduler heartbeat (UTC)',
  `metadata` JSON DEFAULT NULL,
  `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC wall clock',
  `updatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'UTC wall clock',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_swooleSchedulerStatus_schedulerKey` (`schedulerKey`),
  KEY `idx_swooleSchedulerStatus_status_heartbeatAt` (`status`, `heartbeatAt`),
  KEY `idx_swooleSchedulerStatus_nextRunAt` (`nextRunAt`),
  CONSTRAINT `chk_swooleSchedulerStatus_schedulerType`
    CHECK (`schedulerType` IN ('watchdog', 'business', 'maintenance')),
  CONSTRAINT `chk_swooleSchedulerStatus_status`
    CHECK (`status` IN ('active', 'inactive', 'error', 'stale'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Current state of each logical Swoole scheduler';

CREATE TABLE IF NOT EXISTS `swooleSchedulerRuns` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `schedulerId` BIGINT(20) UNSIGNED NOT NULL COMMENT 'FK swooleSchedulerStatus.id',
  `runId` VARCHAR(128) NOT NULL COMMENT 'Application-level unique execution ID',
  `serverInstanceId` VARCHAR(128) DEFAULT NULL COMMENT 'Swoole process generation',
  `status` VARCHAR(16) NOT NULL COMMENT 'running, completed, failed, skipped, overlapping',
  `startedAt` DATETIME NOT NULL COMMENT 'Execution start (UTC)',
  `finishedAt` DATETIME DEFAULT NULL COMMENT 'Execution end (UTC)',
  `durationMs` INT(11) UNSIGNED DEFAULT NULL,
  `discoveredCount` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `dispatchedCount` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `failedCount` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `errorMessage` TEXT DEFAULT NULL,
  `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC wall clock',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_swooleSchedulerRuns_runId` (`runId`),
  KEY `idx_swooleSchedulerRuns_schedulerId_startedAt` (`schedulerId`, `startedAt`),
  KEY `idx_swooleSchedulerRuns_status_startedAt` (`status`, `startedAt`),
  CONSTRAINT `chk_swooleSchedulerRuns_status`
    CHECK (`status` IN ('running', 'completed', 'failed', 'skipped', 'overlapping')),
  CONSTRAINT `fk_swooleSchedulerRuns_schedulerId`
    FOREIGN KEY (`schedulerId`) REFERENCES `swooleSchedulerStatus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Execution history for Swoole schedulers';

CREATE TABLE IF NOT EXISTS `backgroundJobs` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `jobId` VARCHAR(128) NOT NULL COMMENT 'Authoritative application-level job ID',
  `schedulerRunId` BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'FK swooleSchedulerRuns.id; nullable for non-scheduler jobs',
  `type` VARCHAR(64) NOT NULL COMMENT 'e.g. send_notification, sync_orders_balances',
  `status` VARCHAR(16) NOT NULL DEFAULT 'queued' COMMENT 'queued, running, completed, failed, cancelled, stale',
  `taskId` INT(11) DEFAULT NULL COMMENT 'Native Swoole task ID; NOT unique alone',
  `serverInstanceId` VARCHAR(128) DEFAULT NULL COMMENT 'Prevents taskId collisions after restart',
  `requestId` VARCHAR(128) DEFAULT NULL,
  `correlationId` VARCHAR(128) DEFAULT NULL,
  `userId` BIGINT(20) DEFAULT NULL COMMENT 'Soft ref only; no FK',
  `userType` VARCHAR(32) DEFAULT NULL COMMENT 'admin, client, system',
  `workerId` INT(11) DEFAULT NULL,
  `processId` INT(11) DEFAULT NULL,
  `progressPercent` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Progress as 0-100 percent (processed/total*100)',
  `processed` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Count of items completed so far',
  `total` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total items to process for this job',
  `currentStep` VARCHAR(255) DEFAULT NULL,
  `queuedAt` DATETIME DEFAULT NULL COMMENT 'UTC',
  `startedAt` DATETIME DEFAULT NULL COMMENT 'UTC',
  `lastHeartbeatAt` DATETIME DEFAULT NULL COMMENT 'UTC',
  `finishedAt` DATETIME DEFAULT NULL COMMENT 'UTC',
  `errorMessage` TEXT DEFAULT NULL,
  `result` JSON DEFAULT NULL COMMENT 'Sanitized JSON result',
  `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC wall clock',
  `updatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'UTC wall clock',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_backgroundJobs_jobId` (`jobId`),
  KEY `idx_backgroundJobs_status_queuedAt` (`status`, `queuedAt`),
  KEY `idx_backgroundJobs_type_status` (`type`, `status`),
  KEY `idx_backgroundJobs_schedulerRunId` (`schedulerRunId`),
  KEY `idx_backgroundJobs_requestId` (`requestId`),
  KEY `idx_backgroundJobs_lastHeartbeatAt` (`lastHeartbeatAt`),
  CONSTRAINT `chk_backgroundJobs_status`
    CHECK (`status` IN ('queued', 'running', 'completed', 'failed', 'cancelled', 'stale')),
  CONSTRAINT `chk_backgroundJobs_userType`
    CHECK (`userType` IS NULL OR `userType` IN ('admin', 'client', 'system')),
  CONSTRAINT `fk_backgroundJobs_schedulerRunId`
    FOREIGN KEY (`schedulerRunId`) REFERENCES `swooleSchedulerRuns` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Individual Swoole task / background job tracking';

INSERT INTO `swooleSchedulerStatus` (
  `schedulerKey`, `name`, `schedulerType`, `intervalMs`, `status`, `createdAt`, `updatedAt`
)
SELECT 'worker_monitor', 'Worker Monitor', 'watchdog', 10000, 'inactive', NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `swooleSchedulerStatus` WHERE `schedulerKey` = 'worker_monitor'
);

INSERT INTO `swooleSchedulerStatus` (
  `schedulerKey`, `name`, `schedulerType`, `intervalMs`, `status`, `createdAt`, `updatedAt`
)
SELECT 'business_scheduler', 'Business Scheduler', 'business', 60000, 'inactive', NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `swooleSchedulerStatus` WHERE `schedulerKey` = 'business_scheduler'
);

COMMIT;

-- ============================================================
-- Rollback
-- ============================================================
-- START TRANSACTION;
-- DROP TABLE IF EXISTS `backgroundJobs`;
-- DROP TABLE IF EXISTS `swooleSchedulerRuns`;
-- DROP TABLE IF EXISTS `swooleSchedulerStatus`;
-- COMMIT;
