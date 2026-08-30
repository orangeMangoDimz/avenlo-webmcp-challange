-- ============================================================
-- Trading Accounts Database Schema
-- Supports client open account feature
-- ============================================================
-- Created: 2025-11-07
-- Description: Tables for trading platforms, leverage options,
--              and client trading accounts.
-- Naming Convention: camelCase
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================
-- TRADING PLATFORM MASTER DATA
-- ============================================================

CREATE TABLE `tradingPlatforms` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `platformKey` varchar(50) NOT NULL COMMENT 'Unique key, e.g. mt5, mt4, financepro',
  `displayName` varchar(100) NOT NULL,
  `shortCode` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `features` text DEFAULT NULL COMMENT 'JSON array of feature bullet strings',
  `isEnabled` tinyint(1) NOT NULL DEFAULT 1,
  `isRecommended` tinyint(1) NOT NULL DEFAULT 0,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `platformKey` (`platformKey`),
  KEY `isEnabled` (`isEnabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Available trading platforms for client accounts';

INSERT INTO `tradingPlatforms`
  (`platformKey`, `displayName`, `shortCode`, `description`, `features`, `isEnabled`, `isRecommended`)
VALUES
  (
    'mt4',
    'MetaTrader 4',
    'MT4',
    'Industry-standard platform with a user-friendly interface and reliable forex & CFD trading capabilities.',
    '["User-friendly interface","Reliable forex & CFD trading","Expert Advisors (EAs)","Mobile & desktop support"]',
    1,
    0
  ),
  (
    'mt5',
    'MetaTrader 5',
    'MT5',
    'Advanced trading platform with superior charting tools, multi-asset trading, and automated strategies.',
    '["Multi-asset trading","Advanced charting tools","Integrated economic calendar","Algorithmic trading (EAs)"]',
    1,
    1
  ),
  (
    'financepro',
    'FinancePro',
    'FinancePro',
    'Our proprietary trading platform with ultra-fast execution, advanced risk management, and social trading features.',
    '["Ultra-fast execution","Advanced risk management","Real-time market analytics","Social trading features"]',
    1,
    0
  );

-- ============================================================
-- LEVERAGE OPTIONS PER PLATFORM
-- ============================================================

CREATE TABLE `tradingPlatformLeverages` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `platformId` int(11) UNSIGNED NOT NULL,
  `leverageValue` varchar(20) NOT NULL COMMENT 'e.g. 1:50',
  `displayLabel` varchar(150) NOT NULL COMMENT 'Label shown to clients',
  `riskNote` varchar(150) DEFAULT NULL COMMENT 'Optional risk descriptor',
  `isEnabled` tinyint(1) NOT NULL DEFAULT 1,
  `displayOrder` int(11) NOT NULL DEFAULT 0,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `platformLeverageUnique` (`platformId`, `leverageValue`),
  KEY `platformId` (`platformId`),
  CONSTRAINT `tradingPlatformLeveragesPlatformFk` FOREIGN KEY (`platformId`) REFERENCES `tradingPlatforms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Available leverage ratios per trading platform';

-- Default leverage options (applies to all platforms)
INSERT INTO `tradingPlatformLeverages`
  (`platformId`, `leverageValue`, `displayLabel`, `riskNote`, `isEnabled`, `displayOrder`)
SELECT p.id, v.leverageValue, v.displayLabel, v.riskNote, 1, v.displayOrder
FROM `tradingPlatforms` AS p
JOIN (
  SELECT '1:50' AS leverageValue, '1:50 - Conservative' AS displayLabel, 'Lower risk exposure' AS riskNote, 1 AS displayOrder UNION ALL
  SELECT '1:100', '1:100 - Moderate', 'Balanced risk profile', 2 UNION ALL
  SELECT '1:200', '1:200 - Standard', 'Standard leverage level', 3 UNION ALL
  SELECT '1:300', '1:300 - Aggressive', 'Higher potential risk/reward', 4 UNION ALL
  SELECT '1:400', '1:400 - High Risk', 'Suitable for experienced traders', 5 UNION ALL
  SELECT '1:500', '1:500 - Maximum', 'Maximum leverage offered', 6
) AS v;

-- ============================================================
-- CLIENT TRADING ACCOUNTS
-- ============================================================

CREATE TABLE `tradingAccounts` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `userId` int(11) UNSIGNED NOT NULL,
  `platformId` int(11) UNSIGNED NOT NULL,
  `accountNumber` varchar(30) NOT NULL,
  `accountNickname` varchar(150) NOT NULL,
  `accountCurrency` varchar(10) NOT NULL DEFAULT 'USD',
  `accountType` varchar(50) NOT NULL DEFAULT 'Standard',
  `leverageValue` varchar(20) NOT NULL,
  `initialDeposit` decimal(15,2) NOT NULL,
  `status` enum('active','pending','closed') NOT NULL DEFAULT 'active',
  `emailSent` tinyint(1) NOT NULL DEFAULT 0,
  `emailSentAt` datetime DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accountNumber` (`accountNumber`),
  KEY `userId` (`userId`),
  KEY `platformId` (`platformId`),
  KEY `status` (`status`),
  CONSTRAINT `tradingAccountsUserFk` FOREIGN KEY (`userId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tradingAccountsPlatformFk` FOREIGN KEY (`platformId`) REFERENCES `tradingPlatforms` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Client trading accounts opened via portal';

COMMIT;

-- ============================================================
-- END OF TRADING ACCOUNTS DATABASE SCHEMA
-- ============================================================
