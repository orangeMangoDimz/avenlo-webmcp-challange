-- ============================================================
-- Gateway quick amount chips (deposit / withdrawal)
-- Table: paymentGatewayQuickAmounts
-- ============================================================
-- Created: 2026-08-06
-- Environment: dev
-- Closest migration: 009_20260728021931_create_email_sent_logs_table.sql
-- Spec: docs/superpowers/specs/2026-08-06-gateway-quick-amounts-design.md
-- ============================================================
-- BACKUP FIRST: run scripts/backup.sh before applying.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `paymentGatewayQuickAmounts` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `gatewaySettingId` INT(11) UNSIGNED NOT NULL COMMENT 'paymentGatewaySettings.id',
  `transactionType` ENUM('deposit','withdrawal') NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL COMMENT 'USD quick-select amount',
  `sortOrder` INT(11) NOT NULL DEFAULT 0,
  `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pgqa_gateway_type_amount` (`gatewaySettingId`, `transactionType`, `amount`),
  KEY `idx_pgqa_gateway_type_sort` (`gatewaySettingId`, `transactionType`, `sortOrder`),
  CONSTRAINT `fkPaymentGatewayQuickAmountsGateway`
    FOREIGN KEY (`gatewaySettingId`) REFERENCES `paymentGatewaySettings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Per-gateway USD quick amount chips for deposit/withdrawal';

INSERT INTO `paymentGatewayQuickAmounts` (`gatewaySettingId`, `transactionType`, `amount`, `sortOrder`)
SELECT pgs.`id`, v.`transactionType`, v.`amount`, v.`sortOrder`
FROM `paymentGatewaySettings` pgs
CROSS JOIN (
  SELECT 'deposit' AS `transactionType`, 100.00 AS `amount`, 1 AS `sortOrder`
  UNION ALL SELECT 'deposit', 500.00, 2
  UNION ALL SELECT 'deposit', 1000.00, 3
  UNION ALL SELECT 'deposit', 5000.00, 4
  UNION ALL SELECT 'withdrawal', 100.00, 1
  UNION ALL SELECT 'withdrawal', 500.00, 2
  UNION ALL SELECT 'withdrawal', 1000.00, 3
  UNION ALL SELECT 'withdrawal', 5000.00, 4
) AS v
WHERE pgs.`deletedAt` IS NULL
ON DUPLICATE KEY UPDATE `updatedAt` = CURRENT_TIMESTAMP;

COMMIT;

-- ============================================================
-- Rollback
-- ============================================================
-- START TRANSACTION;
-- DROP TABLE IF EXISTS `paymentGatewayQuickAmounts`;
-- COMMIT;
