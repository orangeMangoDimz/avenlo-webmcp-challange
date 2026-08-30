-- ============================================================
-- Create Deposit Notes Table
-- 用于存储Deposit的多条备注记录
-- ============================================================
-- Created: 2025-11-20
-- Description: 允许后台人员为每个deposit添加多条备注，每条备注记录创建人和创建时间
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Deposit Notes Table
-- 存储deposit的备注记录
CREATE TABLE IF NOT EXISTS `depositNotes` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `depositId` bigint(20) UNSIGNED NOT NULL COMMENT 'Reference to deposits.id',
  `noteContent` text NOT NULL COMMENT 'Note content',
  `createdBy` bigint(20) UNSIGNED NOT NULL COMMENT 'Admin user ID who created the note',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxDepositId` (`depositId`),
  KEY `idxCreatedBy` (`createdBy`),
  KEY `idxCreatedAt` (`createdAt`),
  CONSTRAINT `fkDepositNotesDeposit` FOREIGN KEY (`depositId`) REFERENCES `deposits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fkDepositNotesAdmin` FOREIGN KEY (`createdBy`) REFERENCES `adminUsers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Deposit notes for admin staff';

COMMIT;
