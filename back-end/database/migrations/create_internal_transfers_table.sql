-- ============================================================
-- Internal Transfers Table
-- For client internal transfer requests between trading accounts
-- ============================================================
-- Created: 2025-11-21
-- Description: Database table for internal transfers
-- Naming Convention: camelCase
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Internal Transfers
CREATE TABLE `internalTransfers` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `transactionId` varchar(50) NOT NULL COMMENT 'Unique transaction ID (ITF-YYYYMMDD-XXXXX)',
  `userId` int(11) UNSIGNED NOT NULL COMMENT 'Client user ID',
  `fromType` enum('available_balance','trading_account') NOT NULL COMMENT 'Source type: available balance or trading account',
  `fromTradingAccountId` int(11) UNSIGNED DEFAULT NULL COMMENT 'Source trading account ID (if fromType is trading_account)',
  `toTradingAccountId` int(11) UNSIGNED NOT NULL COMMENT 'Target trading account ID',
  `amount` decimal(15,2) NOT NULL COMMENT 'Transfer amount in USD',
  `status` enum('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `requestedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When client initiated transfer',
  `approvedAt` datetime DEFAULT NULL COMMENT 'When admin approved',
  `approvedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `completedAt` datetime DEFAULT NULL COMMENT 'When transfer was completed',
  `failureReason` varchar(500) DEFAULT NULL,
  `adminNotes` text DEFAULT NULL COMMENT 'Internal admin notes',
  `clientNotes` text DEFAULT NULL COMMENT 'Notes from client',
  `ipAddress` varchar(45) DEFAULT NULL COMMENT 'Client IP when requesting',
  `userAgent` text DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukTransactionId` (`transactionId`),
  KEY `idxUserId` (`userId`),
  KEY `idxFromTradingAccountId` (`fromTradingAccountId`),
  KEY `idxToTradingAccountId` (`toTradingAccountId`),
  KEY `idxStatus` (`status`),
  KEY `idxRequestedAt` (`requestedAt`),
  KEY `idxApprovedAt` (`approvedAt`),
  KEY `idxCompletedAt` (`completedAt`),
  CONSTRAINT `fkInternalTransfersUser` FOREIGN KEY (`userId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fkInternalTransfersFromAccount` FOREIGN KEY (`fromTradingAccountId`) REFERENCES `tradingAccounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fkInternalTransfersToAccount` FOREIGN KEY (`toTradingAccountId`) REFERENCES `tradingAccounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Client internal transfer transactions';

-- Internal Transfer Status History
-- Tracks status changes for internal transfers (timeline feature)
CREATE TABLE `internalTransferStatusHistory` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `internalTransferId` bigint(20) UNSIGNED NOT NULL,
  `previousStatus` enum('pending','processing','completed','failed','cancelled') DEFAULT NULL,
  `newStatus` enum('pending','processing','completed','failed','cancelled') NOT NULL,
  `description` varchar(500) DEFAULT NULL COMMENT 'Status change description',
  `changedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID (NULL for system changes)',
  `metadata` text DEFAULT NULL COMMENT 'Additional JSON data',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxInternalTransferId` (`internalTransferId`),
  KEY `idxNewStatus` (`newStatus`),
  KEY `idxCreatedAt` (`createdAt`),
  CONSTRAINT `fkInternalTransferStatusHistoryTransfer` FOREIGN KEY (`internalTransferId`) REFERENCES `internalTransfers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Internal transfer status change history';

-- Internal Transfer Tags
-- Master table for internal transfer tags (reuse deposit tags structure)
-- Note: We can reuse depositTags table or create a separate one
-- For now, we'll reuse depositTags table

-- Internal Transfer Tag Assignments
CREATE TABLE `internalTransferTagAssignments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `internalTransferId` bigint(20) UNSIGNED NOT NULL,
  `tagId` int(11) UNSIGNED NOT NULL,
  `createdBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukTransferTag` (`internalTransferId`, `tagId`),
  KEY `idxInternalTransferId` (`internalTransferId`),
  KEY `idxTagId` (`tagId`),
  CONSTRAINT `fkInternalTransferTagAssignmentsTransfer` FOREIGN KEY (`internalTransferId`) REFERENCES `internalTransfers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fkInternalTransferTagAssignmentsTag` FOREIGN KEY (`tagId`) REFERENCES `depositTags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Internal transfer tag assignments';

-- Internal Transfer Notes
CREATE TABLE `internalTransferNotes` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `internalTransferId` bigint(20) UNSIGNED NOT NULL,
  `noteContent` text NOT NULL,
  `createdBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID who created the note',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `internalTransferId` (`internalTransferId`),
  KEY `createdBy` (`createdBy`),
  CONSTRAINT `fk_internal_transfer_notes_transfer` FOREIGN KEY (`internalTransferId`) REFERENCES `internalTransfers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_internal_transfer_notes_admin` FOREIGN KEY (`createdBy`) REFERENCES `adminUsers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admin notes for internal transfer transactions';

COMMIT;
