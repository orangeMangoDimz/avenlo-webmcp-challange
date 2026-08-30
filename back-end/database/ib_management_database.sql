-- ============================================================
-- IB (Introducing Broker) Management Database Schema
-- For IB Application, Management, Commission Rules & Settings
-- ============================================================
-- Created: 2024-11-13
-- Description: Database tables for IB application processing, IB management,
--              commission rules configuration, tier templates, network hierarchy,
--              documents, and settings
-- Related Pages: IBApplicationList.html, IBList.html, IBRule.html,
--                IBSettings.html, IBTierTemplate.html
-- Naming Convention: camelCase
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================
-- IB TIER TEMPLATES AND HIERARCHY
-- ============================================================

-- IB Tier Levels
-- Defines individual tier levels with permissions (from IBTierTemplate.html)
CREATE TABLE `ibTierLevels` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tierLevel` int(11) NOT NULL COMMENT 'Tier level number (1=highest, 2, 3, 4, etc.)',
  `tierName` varchar(100) NOT NULL COMMENT 'Master Agent, Senior Agent, Standard Agent, Junior Agent',
  `tierDescription` varchar(500) DEFAULT NULL COMMENT 'Tier description',
  `canRecruitSubAgents` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Permission: Recruit sub-agents',
  `canViewReports` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Permission: View reports',
  `canManageClients` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Permission: Manage clients',
  `status` enum('active','inactive','draft') NOT NULL DEFAULT 'active',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `createdBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukTierLevel` (`tierLevel`),
  KEY `idxStatus` (`status`),
  KEY `idxTierLevel` (`tierLevel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB tier levels configuration';

-- Insert default tier levels
INSERT INTO `ibTierLevels` (`tierLevel`, `tierName`, `tierDescription`, `canRecruitSubAgents`, `canViewReports`, `canManageClients`, `status`) VALUES
(1, 'Master Agent', 'Highest level agent tier with full permissions', 1, 1, 1, 'active'),
(2, 'Senior Agent', 'Senior level agent tier with recruiting rights', 1, 1, 0, 'active'),
(3, 'Standard Agent', 'Standard level agent tier with basic access', 0, 1, 0, 'active'),
(4, 'Junior Agent', 'Entry level agent tier with basic access only', 0, 0, 0, 'active');

-- ============================================================
-- IB COMMISSION RULES CONFIGURATION
-- ============================================================

-- IB Commission Rules Master Table
-- Defines different commission rule templates (from IBRule.html)
CREATE TABLE `ibCommissionRules` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ruleName` varchar(200) NOT NULL COMMENT 'Standard Rule, Premium Rule, Ultra Rule, etc.',
  `ruleType` enum('standard','premium','ultra','custom') NOT NULL DEFAULT 'standard',
  `ruleDescription` text DEFAULT NULL COMMENT 'Rule description',
  `targetRegion` varchar(100) DEFAULT 'global' COMMENT 'global, asia, europe, americas, mena',
  `paymentCycle` enum('realtime','daily','weekly','biweekly','monthly','quarterly') NOT NULL DEFAULT 'monthly',
  `paymentDay` varchar(50) DEFAULT NULL COMMENT 'Payment day configuration: 5, 10, 1-15, everyday, etc.',
  `minimumPayout` decimal(15,2) NOT NULL DEFAULT 100.00 COMMENT 'Minimum payout amount',
  `payoutCurrency` varchar(10) NOT NULL DEFAULT 'USD',
  `autoPaymentEnabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Auto payment enabled',
  `status` enum('active','inactive','draft') NOT NULL DEFAULT 'active',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `createdBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  PRIMARY KEY (`id`),
  KEY `idxStatus` (`status`),
  KEY `idxRuleType` (`ruleType`),
  KEY `idxTargetRegion` (`targetRegion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB commission rules master table';

-- Insert default commission rules
INSERT INTO `ibCommissionRules` (`ruleName`, `ruleType`, `ruleDescription`, `targetRegion`, `paymentCycle`, `paymentDay`, `minimumPayout`, `payoutCurrency`) VALUES
('Standard Rule', 'standard', 'Basic commission structure with competitive rates', 'global', 'monthly', '5', 100.00, 'USD'),
('Premium Rule', 'premium', 'Enhanced rule with cash back benefits', 'global', 'biweekly', '1-15', 50.00, 'USD'),
('Ultra Rule', 'ultra', 'Premium rule with lowest trading fees', 'global', 'daily', 'everyday', 25.00, 'USD'),
('Crypto Commission Rule', 'custom', 'Special rates for cryptocurrency trading', 'global', 'daily', 'everyday', 50.00, 'USD'),
('Regional Partner - APAC', 'custom', 'Custom rates for Asia-Pacific region', 'asia', 'monthly', '10', 75.00, 'USD');

-- IB Commission Rule Products Configuration
-- Defines commission rates for specific products/securities
CREATE TABLE `ibRuleProducts` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ruleId` int(11) UNSIGNED NOT NULL COMMENT 'Reference to ibCommissionRules.id',
  `productType` enum('security','symbol') NOT NULL DEFAULT 'security' COMMENT 'Security or specific symbol',
  `productName` varchar(100) NOT NULL COMMENT 'Forex, Crypto, Metals, Indices, EURUSD, BTCUSD, etc.',
  `commissionType` enum('per_lot','percentage','per_trade','cashback','hybrid') NOT NULL DEFAULT 'per_lot',
  `commissionRate` decimal(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'Base commission rate',
  `additionalRate` decimal(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'Additional/bonus rate or percentage cap',
  `minimumVolume` varchar(50) DEFAULT '0.01 lots' COMMENT 'Minimum trading volume required',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxRuleId` (`ruleId`),
  KEY `idxProductType` (`productType`),
  KEY `idxCommissionType` (`commissionType`),
  CONSTRAINT `fkIbRuleProducts` FOREIGN KEY (`ruleId`) REFERENCES `ibCommissionRules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB rule products commission configuration';

-- Insert default rule products
INSERT INTO `ibRuleProducts` (`ruleId`, `productType`, `productName`, `commissionType`, `commissionRate`, `additionalRate`, `minimumVolume`) VALUES
(1, 'security', 'Forex', 'per_lot', 10.00, 0.00, '0.01 lots'),
(1, 'security', 'Indices', 'per_lot', 11.00, 0.00, '0.01 lots'),
(2, 'security', 'Forex', 'cashback', 3.00, 0.00, '0.01 lots'),
(2, 'security', 'Energies', 'cashback', 2.50, 0.00, '0.01 lots'),
(2, 'security', 'Indices', 'per_lot', 13.00, 0.00, '0.01 lots'),
(3, 'security', 'Forex', 'per_lot', 6.00, 1.50, '0.01 lots'),
(3, 'security', 'Crypto', 'percentage', 15.00, 5.00, '0.01 lots'),
(3, 'security', 'Stocks', 'per_trade', 3.00, 0.00, '1 share'),
(4, 'security', 'Crypto', 'percentage', 20.00, 0.00, '0.01 lots'),
(5, 'security', 'Forex', 'per_lot', 8.50, 0.00, '0.01 lots'),
(5, 'security', 'Metals', 'per_lot', 9.00, 0.00, '0.01 lots');

-- IB Rule Additional Rules Configuration
-- Advanced commission rules like volume tiers, bonuses, multipliers
CREATE TABLE `ibRuleAdditionalRules` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ruleId` int(11) UNSIGNED NOT NULL COMMENT 'Reference to ibCommissionRules.id',
  `productType` enum('security','symbol','all') NOT NULL DEFAULT 'security',
  `productName` varchar(100) DEFAULT NULL COMMENT 'Product name or NULL for all',
  `ruleType` enum('bonus_commission','volume_tiers','volume_multiplier','performance_bonus','cash_rebate') NOT NULL,
  `ruleValue` varchar(100) DEFAULT NULL COMMENT 'Value or tier count',
  `ruleCondition` varchar(500) DEFAULT NULL COMMENT 'Condition or threshold (e.g., "Volume > 1000 lots/month")',
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxRuleId` (`ruleId`),
  KEY `idxRuleType` (`ruleType`),
  KEY `idxIsActive` (`isActive`),
  CONSTRAINT `fkIbRuleAdditional` FOREIGN KEY (`ruleId`) REFERENCES `ibCommissionRules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB rule additional rules configuration';

-- Insert default additional rules
INSERT INTO `ibRuleAdditionalRules` (`ruleId`, `productType`, `productName`, `ruleType`, `ruleValue`, `ruleCondition`) VALUES
(2, 'security', 'Forex', 'volume_tiers', '2 Tiers', 'Auto (based on volume)'),
(2, 'security', 'Crypto', 'cash_rebate', '1.50', 'Volume > 500 lots/month'),
(3, 'security', 'Forex', 'volume_tiers', '3 Tiers', 'Auto (based on volume)'),
(3, 'security', 'Crypto', 'volume_multiplier', '1.25', 'Volume > 2000 lots/month');

-- IB Rule Commission Tiers
-- Volume-based commission tier definitions
CREATE TABLE `ibRuleCommissionTiers` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `additionalRuleId` int(11) UNSIGNED NOT NULL COMMENT 'Reference to ibRuleAdditionalRules.id',
  `tierLevel` int(11) NOT NULL COMMENT 'Tier sequence number (1, 2, 3...)',
  `tierName` varchar(100) DEFAULT NULL COMMENT 'Starter Level, Intermediate Level, etc.',
  `commissionRate` decimal(15,4) NOT NULL COMMENT 'Commission rate for this tier',
  `minimumVolume` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Minimum volume in lots',
  `maximumVolume` varchar(50) DEFAULT NULL COMMENT 'Maximum volume or "Unlimited"',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxAdditionalRuleId` (`additionalRuleId`),
  KEY `idxTierLevel` (`tierLevel`),
  CONSTRAINT `fkIbRuleCommissionTiers` FOREIGN KEY (`additionalRuleId`) REFERENCES `ibRuleAdditionalRules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB rule volume-based commission tiers';

-- Insert default commission tiers for Premium Rule (ruleId=2, additionalRuleId=1)
INSERT INTO `ibRuleCommissionTiers` (`additionalRuleId`, `tierLevel`, `tierName`, `commissionRate`, `minimumVolume`, `maximumVolume`) VALUES
(1, 1, 'Basic Level', 5.00, 0, '200'),
(1, 2, 'Advanced Level', 7.00, 201, 'Unlimited'),
(3, 1, 'Entry Level', 4.00, 0, '500'),
(3, 2, 'Mid Level', 5.50, 501, '1000'),
(3, 3, 'Elite Level', 7.00, 1001, 'Unlimited');

-- ============================================================
-- IB PARTNERS (APPROVED IBs)
-- ============================================================

-- IB Partners Master Table
-- Stores all approved IB partners (from IBList.html)
CREATE TABLE `ibPartners` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ibCode` varchar(50) NOT NULL COMMENT 'IB-2024-001, IB-2024-002, etc.',
  `userId` int(11) UNSIGNED DEFAULT NULL COMMENT 'Reference to clientUsers.id (if IB was converted from client)',
  `companyName` varchar(200) NOT NULL COMMENT 'Company or individual name',
  `ibType` enum('individual','company') NOT NULL DEFAULT 'individual',
  `tierLevelId` int(11) UNSIGNED DEFAULT NULL COMMENT 'Assigned tier level',
  `contactPerson` varchar(100) DEFAULT NULL COMMENT 'Contact person name',
  `contactEmail` varchar(200) NOT NULL,
  `contactPhone` varchar(50) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  `totalClients` int(11) NOT NULL DEFAULT 0 COMMENT 'Total referred clients count',
  `activeClients` int(11) NOT NULL DEFAULT 0 COMMENT 'Active clients count',
  `totalCommissionEarned` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Lifetime commission earned',
  `totalTradingVolume` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'Total trading volume in USD',
  `registrationDate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approvalDate` datetime DEFAULT NULL COMMENT 'When application was approved',
  `approvedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `status` enum('active','inactive','pending','suspended') NOT NULL DEFAULT 'active',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukIbCode` (`ibCode`),
  KEY `idxUserId` (`userId`),
  KEY `idxStatus` (`status`),
  KEY `idxTierLevelId` (`tierLevelId`),
  KEY `idxRegistrationDate` (`registrationDate`),
  CONSTRAINT `fkIbPartnerUser` FOREIGN KEY (`userId`) REFERENCES `clientUsers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fkIbPartnerTierLevel` FOREIGN KEY (`tierLevelId`) REFERENCES `ibTierLevels` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB partners master table';

-- Insert sample IB partners
INSERT INTO `ibPartners` (`ibCode`, `companyName`, `ibType`, `tierLevelId`, `contactPerson`, `contactEmail`, `contactPhone`, `country`, `address`, `website`, `totalClients`, `activeClients`, `totalCommissionEarned`, `totalTradingVolume`, `registrationDate`, `approvalDate`, `status`) VALUES
('IB-2024-001', 'Global Trading Partners Ltd.', 'company', 1, 'John Smith', 'john.smith@globaltp.com', '+44 20 7123 4567', 'United Kingdom', '123 City Road, London', 'www.globaltp.com', 156, 142, 45230.50, 2450000.00, '2024-01-15 00:00:00', '2024-01-15 10:30:00', 'active'),
('IB-2024-002', 'Asia Pacific Forex Solutions', 'company', 1, 'Li Wei', 'li.wei@apfs.com', '+65 6123 4567', 'Singapore', '1 Raffles Place, Singapore', 'www.apfs.com', 98, 88, 28450.75, 1850000.00, '2024-02-22 00:00:00', '2024-02-22 14:20:00', 'active'),
('IB-2024-003', 'European Markets Group', 'company', 2, 'Marco Rossi', 'marco@emgroup.eu', '+39 02 1234 5678', 'Italy', 'Via Milano 45, Milan', 'www.emgroup.eu', 75, 68, 32890.00, 1950000.00, '2024-03-10 00:00:00', '2024-03-10 11:15:00', 'active');

-- IB Partner Commission Rules Assignment
-- Associates IB partners with commission rules (supports multiple rules)
CREATE TABLE `ibPartnerRuleAssignments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ibPartnerId` int(11) UNSIGNED NOT NULL,
  `ruleId` int(11) UNSIGNED NOT NULL,
  `assignedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `assignedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukIbPartnerRule` (`ibPartnerId`, `ruleId`),
  KEY `idxIbPartnerId` (`ibPartnerId`),
  KEY `idxRuleId` (`ruleId`),
  CONSTRAINT `fkIbPartnerRuleAssignIb` FOREIGN KEY (`ibPartnerId`) REFERENCES `ibPartners` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fkIbPartnerRuleAssignRule` FOREIGN KEY (`ruleId`) REFERENCES `ibCommissionRules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB partner commission rules assignments';

-- Insert default rule assignments
INSERT INTO `ibPartnerRuleAssignments` (`ibPartnerId`, `ruleId`) VALUES
(1, 1),
(1, 3),
(2, 1),
(3, 1);

-- IB Partner Payment Settings
-- Stores payment method configuration for each IB
CREATE TABLE `ibPartnerPaymentSettings` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ibPartnerId` int(11) UNSIGNED NOT NULL,
  `paymentMethod` enum('bank_transfer','crypto','paypal','wise') NOT NULL DEFAULT 'bank_transfer',
  `bankName` varchar(200) DEFAULT NULL,
  `accountHolderName` varchar(200) DEFAULT NULL,
  `accountNumber` varchar(100) DEFAULT NULL COMMENT 'Masked or encrypted',
  `swiftCode` varchar(50) DEFAULT NULL,
  `iban` varchar(100) DEFAULT NULL,
  `cryptoWalletAddress` varchar(255) DEFAULT NULL,
  `cryptoCurrency` varchar(20) DEFAULT NULL COMMENT 'BTC, ETH, USDT',
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `additionalInfo` text DEFAULT NULL COMMENT 'Additional payment information',
  `isDefault` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxIbPartnerId` (`ibPartnerId`),
  KEY `idxIsDefault` (`isDefault`),
  CONSTRAINT `fkIbPaymentSettingsPartner` FOREIGN KEY (`ibPartnerId`) REFERENCES `ibPartners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB partner payment settings';

-- Insert sample payment settings
INSERT INTO `ibPartnerPaymentSettings` (`ibPartnerId`, `paymentMethod`, `bankName`, `accountHolderName`, `accountNumber`, `currency`, `isDefault`) VALUES
(1, 'bank_transfer', 'HSBC UK', 'Global Trading Partners Ltd.', '****1234', 'USD', 1),
(2, 'bank_transfer', 'DBS Bank', 'Asia Pacific Forex Solutions', '****5678', 'USD', 1);

-- IB Partner Documents
-- Stores uploaded documents for IB partners
CREATE TABLE `ibPartnerDocuments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ibPartnerId` int(11) UNSIGNED NOT NULL,
  `documentName` varchar(200) NOT NULL COMMENT 'IB Partnership Agreement, Business Registration, etc.',
  `documentType` varchar(100) DEFAULT NULL COMMENT 'agreement, registration, tax, other',
  `fileName` varchar(500) NOT NULL COMMENT 'Original file name',
  `filePath` varchar(1000) NOT NULL COMMENT 'Server file path',
  `fileSize` bigint(20) NOT NULL COMMENT 'File size in bytes',
  `fileMimeType` varchar(100) DEFAULT NULL COMMENT 'application/pdf, etc.',
  `uploadedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `uploadedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  PRIMARY KEY (`id`),
  KEY `idxIbPartnerId` (`ibPartnerId`),
  KEY `idxDocumentType` (`documentType`),
  CONSTRAINT `fkIbDocumentsPartner` FOREIGN KEY (`ibPartnerId`) REFERENCES `ibPartners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB partner documents';

-- Insert sample documents
INSERT INTO `ibPartnerDocuments` (`ibPartnerId`, `documentName`, `documentType`, `fileName`, `filePath`, `fileSize`, `fileMimeType`, `uploadedAt`) VALUES
(1, 'IB Partnership Agreement', 'agreement', 'partnership_agreement_ib001.pdf', '/uploads/ib/documents/partnership_agreement_ib001.pdf', 2516582, 'application/pdf', '2024-01-15 00:00:00'),
(1, 'Business Registration Certificate', 'registration', 'business_reg_ib001.pdf', '/uploads/ib/documents/business_reg_ib001.pdf', 1887436, 'application/pdf', '2024-01-15 00:00:00'),
(1, 'Tax Identification Document', 'tax', 'tax_id_ib001.pdf', '/uploads/ib/documents/tax_id_ib001.pdf', 942080, 'application/pdf', '2024-01-15 00:00:00');

-- IB Network Hierarchy
-- Defines the IB network relationship (parent-child IB structure)
CREATE TABLE `ibNetworkHierarchy` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ibPartnerId` int(11) UNSIGNED NOT NULL COMMENT 'Child IB',
  `parentIbPartnerId` int(11) UNSIGNED DEFAULT NULL COMMENT 'Parent IB (NULL for top-level IBs)',
  `hierarchyLevel` int(11) NOT NULL DEFAULT 1 COMMENT 'Level in hierarchy (1=top, 2=sub-IB, 3=sub-sub-IB)',
  `establishedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When relationship was established',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukIbPartnerId` (`ibPartnerId`),
  KEY `idxParentIbPartnerId` (`parentIbPartnerId`),
  KEY `idxHierarchyLevel` (`hierarchyLevel`),
  CONSTRAINT `fkIbNetworkChild` FOREIGN KEY (`ibPartnerId`) REFERENCES `ibPartners` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fkIbNetworkParent` FOREIGN KEY (`parentIbPartnerId`) REFERENCES `ibPartners` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB network hierarchy relationships';

-- Insert sample hierarchy
INSERT INTO `ibNetworkHierarchy` (`ibPartnerId`, `parentIbPartnerId`, `hierarchyLevel`) VALUES
(1, NULL, 1),
(2, 1, 2),
(3, 1, 2);

-- IB Client Relationships
-- Tracks which clients were referred by which IB
CREATE TABLE `ibClientRelationships` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ibPartnerId` int(11) UNSIGNED NOT NULL,
  `clientId` int(11) UNSIGNED NOT NULL COMMENT 'Reference to clientUsers.id',
  `referralCode` varchar(50) DEFAULT NULL COMMENT 'Referral code used',
  `referralDate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `isActive` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Client still active under this IB',
  `clientLifetimeVolume` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'Client total trading volume',
  `commissionGenerated` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Total commission from this client',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukIbClient` (`ibPartnerId`, `clientId`),
  KEY `idxIbPartnerId` (`ibPartnerId`),
  KEY `idxClientId` (`clientId`),
  KEY `idxIsActive` (`isActive`),
  CONSTRAINT `fkIbClientRelationIb` FOREIGN KEY (`ibPartnerId`) REFERENCES `ibPartners` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fkIbClientRelationClient` FOREIGN KEY (`clientId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB and client relationships';

-- IB Commission History
-- Tracks all commission payments to IBs
CREATE TABLE `ibCommissionHistory` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ibPartnerId` int(11) UNSIGNED NOT NULL,
  `commissionPeriodStart` date NOT NULL COMMENT 'Start of commission period',
  `commissionPeriodEnd` date NOT NULL COMMENT 'End of commission period',
  `commissionAmount` decimal(15,2) NOT NULL COMMENT 'Total commission amount',
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `tradingVolume` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'Trading volume for this period',
  `numberOfTrades` int(11) NOT NULL DEFAULT 0,
  `paymentStatus` enum('pending','processing','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
  `paymentDate` datetime DEFAULT NULL,
  `paymentReference` varchar(200) DEFAULT NULL COMMENT 'Transaction reference',
  `paymentMethod` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `calculatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paidBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxIbPartnerId` (`ibPartnerId`),
  KEY `idxPaymentStatus` (`paymentStatus`),
  KEY `idxCommissionPeriod` (`commissionPeriodStart`, `commissionPeriodEnd`),
  KEY `idxPaymentDate` (`paymentDate`),
  CONSTRAINT `fkIbCommissionHistoryPartner` FOREIGN KEY (`ibPartnerId`) REFERENCES `ibPartners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB commission payment history';

-- ============================================================
-- IB APPLICATIONS
-- ============================================================

-- IB Applications Master Table
-- Stores all IB applications (from IBApplicationList.html)
CREATE TABLE `ibApplications` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `applicantName` varchar(200) NOT NULL,
  `applicantEmail` varchar(200) NOT NULL,
  `applicantPhone` varchar(50) DEFAULT NULL,
  `ibType` enum('individual','company') NOT NULL DEFAULT 'individual',
  `companyName` varchar(200) DEFAULT NULL COMMENT 'For company type applications',
  `country` varchar(100) DEFAULT NULL,
  `expectedClients` varchar(100) DEFAULT NULL COMMENT '1-10, 11-25, 26-50, 51-100, 100+',
  `yearsOfExperience` int(11) DEFAULT NULL,
  `hasPreviousIbExperience` tinyint(1) DEFAULT 0,
  `applicationStatus` enum('pending','in_review','approved','rejected','more_info_requested') NOT NULL DEFAULT 'pending',
  `reviewerId` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID assigned to review',
  `assignedTierLevelId` int(11) UNSIGNED DEFAULT NULL COMMENT 'Tier level assigned during approval',
  `applicationDate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewStartDate` datetime DEFAULT NULL,
  `reviewCompletedDate` datetime DEFAULT NULL,
  `approvedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `rejectedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `rejectionReason` text DEFAULT NULL,
  `additionalInfoRequest` text DEFAULT NULL COMMENT 'Info requested from applicant',
  `createdIbPartnerId` int(11) UNSIGNED DEFAULT NULL COMMENT 'Created IB partner ID after approval',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID who last updated',
  PRIMARY KEY (`id`),
  KEY `idxApplicationStatus` (`applicationStatus`),
  KEY `idxReviewerId` (`reviewerId`),
  KEY `idxApplicationDate` (`applicationDate`),
  KEY `idxApplicantEmail` (`applicantEmail`),
  KEY `idxAssignedTierLevelId` (`assignedTierLevelId`),
  KEY `idxCreatedIbPartnerId` (`createdIbPartnerId`),
  CONSTRAINT `fkIbApplicationTierLevel` FOREIGN KEY (`assignedTierLevelId`) REFERENCES `ibTierLevels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fkIbApplicationCreatedPartner` FOREIGN KEY (`createdIbPartnerId`) REFERENCES `ibPartners` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB applications';

-- Insert sample applications
INSERT INTO `ibApplications` (`applicantName`, `applicantEmail`, `applicantPhone`, `ibType`, `country`, `expectedClients`, `yearsOfExperience`, `hasPreviousIbExperience`, `applicationStatus`, `assignedTierLevelId`, `applicationDate`) VALUES
('John Doe', 'john.doe@example.com', '+1 234 567 8900', 'individual', 'United States', '26-50', 5, 1, 'pending', 1, '2024-01-21 15:45:00'),
('Sarah Miller', 'sarah.miller@example.com', '+44 20 1234 5678', 'company', 'United Kingdom', '100+', 10, 1, 'in_review', NULL, '2024-01-21 10:20:00'),
('Robert Johnson', 'robert.j@example.com', '+1 345 678 9012', 'individual', 'Canada', '11-25', 7, 1, 'approved', 3, '2024-01-20 14:15:00'),
('Emma Chen', 'emma.chen@example.com', '+86 21 1234 5678', 'company', 'China', '51-100', 8, 1, 'pending', NULL, '2024-01-20 09:30:00'),
('David Wilson', 'david.wilson@example.com', '+1 456 789 0123', 'individual', 'United States', '1-10', 2, 0, 'rejected', NULL, '2024-01-19 16:50:00'),
('Lisa Anderson', 'lisa.anderson@example.com', '+61 2 1234 5678', 'individual', 'Australia', '26-50', 3, 0, 'pending', NULL, '2024-01-19 11:10:00');

-- IB Application Product Focus
-- Stores primary products and target regions for applications
CREATE TABLE `ibApplicationProductFocus` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `applicationId` int(11) UNSIGNED NOT NULL,
  `primaryProducts` text DEFAULT NULL COMMENT 'JSON array: ["Forex","Crypto","Metals"]',
  `targetRegions` text DEFAULT NULL COMMENT 'JSON array: ["North America","Europe","Asia Pacific"]',
  `marketingChannels` text DEFAULT NULL COMMENT 'JSON array: ["Website/Blog","Social Media","Email Marketing"]',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukApplicationId` (`applicationId`),
  CONSTRAINT `fkIbAppProductFocus` FOREIGN KEY (`applicationId`) REFERENCES `ibApplications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB application product and market focus';

-- Insert sample product focus data
INSERT INTO `ibApplicationProductFocus` (`applicationId`, `primaryProducts`, `targetRegions`, `marketingChannels`) VALUES
(1, '["Forex","Gold & Precious Metals","Cryptocurrencies"]', '["North America","Europe","Asia Pacific"]', '["Website/Blog","Social Media","Email Marketing"]'),
(2, '["Forex","Oil & Energy","Stock Indices","CFDs"]', '["Asia Pacific","Middle East"]', '["Website/Blog","Webinars/Events","Trading Communities","YouTube Channel"]'),
(4, '["Forex","Oil & Energy","Stock Indices","CFDs"]', '["Asia Pacific","Middle East"]', '["Website/Blog","Webinars/Events","Trading Communities","YouTube Channel"]'),
(6, '["Forex","Cryptocurrencies"]', '["Europe","North America"]', '["Social Media","Personal Network","Trading Communities"]');

-- IB Application Rule Assignments
-- Pre-assigns rules to applications before approval
CREATE TABLE `ibApplicationRuleAssignments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `applicationId` int(11) UNSIGNED NOT NULL,
  `ruleId` int(11) UNSIGNED NOT NULL,
  `assignedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `assignedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukApplicationRule` (`applicationId`, `ruleId`),
  KEY `idxApplicationId` (`applicationId`),
  KEY `idxRuleId` (`ruleId`),
  CONSTRAINT `fkIbAppRuleAssignApp` FOREIGN KEY (`applicationId`) REFERENCES `ibApplications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fkIbAppRuleAssignRule` FOREIGN KEY (`ruleId`) REFERENCES `ibCommissionRules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB application commission rule assignments';

-- IB Application Status History
-- Tracks status changes throughout the application lifecycle
CREATE TABLE `ibApplicationStatusHistory` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `applicationId` int(11) UNSIGNED NOT NULL,
  `previousStatus` varchar(50) DEFAULT NULL,
  `newStatus` varchar(50) NOT NULL,
  `changedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `notes` text DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxApplicationId` (`applicationId`),
  KEY `idxNewStatus` (`newStatus`),
  CONSTRAINT `fkIbAppStatusHistory` FOREIGN KEY (`applicationId`) REFERENCES `ibApplications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB application status change history';

-- ============================================================
-- IB INVITATIONS
-- ============================================================

-- IB Invitations
-- Tracks invitations sent to existing clients to become IBs
CREATE TABLE `ibInvitations` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invitationCode` varchar(100) NOT NULL COMMENT 'Unique invitation code',
  `clientId` int(11) UNSIGNED NOT NULL COMMENT 'Reference to clientUsers.id',
  `invitedBy` bigint(20) UNSIGNED NOT NULL COMMENT 'Admin user ID',
  `invitationMessage` text DEFAULT NULL COMMENT 'Custom message included in invitation',
  `selectedDocuments` text DEFAULT NULL COMMENT 'JSON array of document IDs to review',
  `invitationStatus` enum('sent','viewed','accepted','declined','expired') NOT NULL DEFAULT 'sent',
  `sentAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `viewedAt` datetime DEFAULT NULL,
  `respondedAt` datetime DEFAULT NULL,
  `expiresAt` datetime NOT NULL COMMENT 'Invitation expiry date',
  `createdIbPartnerId` int(11) UNSIGNED DEFAULT NULL COMMENT 'Created IB partner ID if accepted',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukInvitationCode` (`invitationCode`),
  KEY `idxClientId` (`clientId`),
  KEY `idxInvitationStatus` (`invitationStatus`),
  KEY `idxExpiresAt` (`expiresAt`),
  KEY `idxCreatedIbPartnerId` (`createdIbPartnerId`),
  CONSTRAINT `fkIbInvitationClient` FOREIGN KEY (`clientId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fkIbInvitationCreatedPartner` FOREIGN KEY (`createdIbPartnerId`) REFERENCES `ibPartners` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB invitation tracking';

-- ============================================================
-- IB DOCUMENT TEMPLATES
-- ============================================================

-- IB Document Templates
-- Stores document templates that IBs must review/sign (from IBSettings.html)
CREATE TABLE `ibDocumentTemplates` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `documentTitle` varchar(200) NOT NULL COMMENT 'IB Partnership Agreement, Compliance Declaration, etc.',
  `documentContent` longtext NOT NULL COMMENT 'Rich text HTML content',
  `iconClass` varchar(100) DEFAULT 'fas fa-file-contract' COMMENT 'FontAwesome icon class',
  `iconGradient` varchar(200) DEFAULT NULL COMMENT 'CSS gradient for icon background',
  `isRequired` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Required for IB applications',
  `displayOrder` int(11) NOT NULL DEFAULT 0,
  `wordCount` int(11) NOT NULL DEFAULT 0 COMMENT 'Approximate word count',
  `characterCount` int(11) NOT NULL DEFAULT 0 COMMENT 'Character count',
  `estimatedReadTime` int(11) NOT NULL DEFAULT 0 COMMENT 'Estimated read time in minutes',
  `version` varchar(20) DEFAULT '1.0' COMMENT 'Document version',
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `createdBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  PRIMARY KEY (`id`),
  KEY `idxIsRequired` (`isRequired`),
  KEY `idxDisplayOrder` (`displayOrder`),
  KEY `idxIsActive` (`isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB document templates';

-- Insert default document templates
INSERT INTO `ibDocumentTemplates` (`documentTitle`, `documentContent`, `iconClass`, `iconGradient`, `isRequired`, `displayOrder`, `wordCount`, `characterCount`, `estimatedReadTime`) VALUES
('IB Partnership Agreement', '<h3>IB Partnership Agreement</h3><p>This agreement is entered between BDX Trading Platform ("Company") and the Introducing Broker ("IB").</p><h4>1. Appointment</h4><p>The Company appoints the IB as an independent introducing broker to refer potential clients to the Company\'s trading platform.</p><h4>2. Commission Structure</h4><ul><li>Commissions are calculated based on referred client trading volume</li><li>Payment frequency: Monthly, within 5 business days</li><li>Minimum payout threshold: $100 USD</li></ul><h4>3. IB Responsibilities</h4><ul><li>Maintain professional conduct and ethical business practices</li><li>Comply with all applicable laws and regulations</li><li>Provide accurate information to referred clients</li></ul>', 'fas fa-handshake', 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)', 1, 1, 250, 850, 2),
('Compliance & AML Declaration', '<h3>Compliance & Anti-Money Laundering Declaration</h3><p>As an Introducing Broker, I hereby declare and acknowledge:</p><ol><li>I will comply with all applicable Anti-Money Laundering (AML) and Counter-Terrorist Financing (CTF) regulations</li><li>I will not knowingly refer clients involved in illegal activities or from sanctioned countries</li><li>I will conduct appropriate due diligence on all referred clients</li><li>I will immediately report any suspicious activities to the Company</li><li>I understand that violation of compliance regulations may result in immediate termination of this agreement</li></ol>', 'fas fa-shield-alt', 'linear-gradient(135deg, #48bb78 0%, #38a169 100%)', 1, 2, 180, 720, 2),
('Commission Payment Terms', '<h3>Commission Payment Terms & Conditions</h3><h4>Payment Schedule</h4><ul><li><strong>Payment Frequency:</strong> Monthly, on the 5th business day of each month</li><li><strong>Minimum Payout:</strong> $100 USD (commissions below threshold roll over to next month)</li><li><strong>Payment Method:</strong> Bank transfer or other agreed method</li></ul><h4>Commission Calculation</h4><p>Commissions are calculated based on the selected commission plan and referred client trading activity during the commission period.</p><h4>Payment Currency</h4><p>All payments will be made in USD unless otherwise agreed in writing. Currency conversion fees may apply for non-USD payments.</p><h4>Tax Responsibilities</h4><p>The IB is responsible for all applicable taxes on commission payments. The Company may be required to withhold taxes as per local regulations.</p>', 'fas fa-dollar-sign', 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)', 1, 3, 220, 980, 3),
('Data Protection & Confidentiality Agreement', '<h3>Data Protection & Confidentiality</h3><h4>Confidential Information</h4><p>The IB acknowledges that during the course of this partnership, they may have access to confidential information including but not limited to:</p><ul><li>Client data and personal information</li><li>Commission structures and business strategies</li><li>Proprietary trading platform features</li><li>Marketing materials and business processes</li></ul><h4>Obligations</h4><ol><li>The IB must keep all confidential information strictly confidential</li><li>Confidential information may only be used for the purpose of this partnership</li><li>The IB must comply with GDPR and applicable data protection laws</li><li>Upon termination, all confidential information must be returned or destroyed</li></ol><h4>Data Processing</h4><p>The IB acts as a data processor and must ensure all client data is handled securely and in compliance with applicable regulations.</p>', 'fas fa-lock', 'linear-gradient(135deg, #3b82f6 0%, #1e40af 100%)', 1, 4, 280, 1100, 4);

-- IB Application Document Acknowledgements
-- Tracks which documents were reviewed and signed by applicants
CREATE TABLE `ibApplicationDocumentAcknowledgements` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `applicationId` int(11) UNSIGNED NOT NULL,
  `documentTemplateId` int(11) UNSIGNED NOT NULL,
  `acknowledged` tinyint(1) NOT NULL DEFAULT 0,
  `acknowledgedAt` datetime DEFAULT NULL,
  `ipAddress` varchar(45) DEFAULT NULL,
  `digitalSignature` varchar(500) DEFAULT NULL COMMENT 'Digital signature data',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukAppDocument` (`applicationId`, `documentTemplateId`),
  KEY `idxApplicationId` (`applicationId`),
  KEY `idxDocumentTemplateId` (`documentTemplateId`),
  CONSTRAINT `fkIbAppDocAckApp` FOREIGN KEY (`applicationId`) REFERENCES `ibApplications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fkIbAppDocAckTemplate` FOREIGN KEY (`documentTemplateId`) REFERENCES `ibDocumentTemplates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB application document acknowledgements';

-- IB Partner Document Acknowledgements
-- Tracks which documents were reviewed and signed by IB partners
CREATE TABLE `ibPartnerDocumentAcknowledgements` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ibPartnerId` int(11) UNSIGNED NOT NULL,
  `documentTemplateId` int(11) UNSIGNED NOT NULL,
  `acknowledged` tinyint(1) NOT NULL DEFAULT 0,
  `acknowledgedAt` datetime DEFAULT NULL,
  `ipAddress` varchar(45) DEFAULT NULL,
  `digitalSignature` varchar(500) DEFAULT NULL COMMENT 'Digital signature data',
  `expiresAt` datetime DEFAULT NULL COMMENT 'Document expiry date (for renewals)',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukPartnerDocument` (`ibPartnerId`, `documentTemplateId`),
  KEY `idxIbPartnerId` (`ibPartnerId`),
  KEY `idxDocumentTemplateId` (`documentTemplateId`),
  KEY `idxExpiresAt` (`expiresAt`),
  CONSTRAINT `fkIbPartnerDocAckPartner` FOREIGN KEY (`ibPartnerId`) REFERENCES `ibPartners` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fkIbPartnerDocAckTemplate` FOREIGN KEY (`documentTemplateId`) REFERENCES `ibDocumentTemplates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB partner document acknowledgements';

-- ============================================================
-- IB PROGRAM SETTINGS
-- ============================================================

-- IB Program Global Settings
-- General settings for IB program (from IBSettings.html)
CREATE TABLE `ibProgramSettings` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `settingKey` varchar(100) NOT NULL COMMENT 'enable_ib_program, auto_approve_applications, etc.',
  `settingValue` text DEFAULT NULL COMMENT 'Setting value (can be JSON for complex settings)',
  `settingType` enum('boolean','string','number','json') NOT NULL DEFAULT 'string',
  `settingGroup` varchar(100) DEFAULT 'general' COMMENT 'general, display, commission, documents',
  `description` varchar(500) DEFAULT NULL,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukSettingKey` (`settingKey`),
  KEY `idxSettingGroup` (`settingGroup`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB program global settings';

-- Insert default IB program settings
INSERT INTO `ibProgramSettings` (`settingKey`, `settingValue`, `settingType`, `settingGroup`, `description`) VALUES
('enable_ib_program', '1', 'boolean', 'general', 'Enable IB program and allow applications'),
('auto_approve_applications', '0', 'boolean', 'general', 'Auto-approve IB applications without manual review'),
('max_commission_rate', '18', 'number', 'display', 'Maximum commission percentage shown in benefits'),
('min_trading_experience_months', '6', 'number', 'general', 'Minimum months of trading experience required'),
('application_processing_time', '15-20 minutes', 'string', 'display', 'Estimated time to complete application'),
('default_tier_level', '3', 'number', 'general', 'Default tier level for new IB approvals'),
('require_document_signature', '1', 'boolean', 'documents', 'Require digital signature on documents');

-- ============================================================
-- CUSTOM SECURITIES AND SYMBOLS
-- ============================================================

-- Custom Securities
-- Admin-defined custom security types beyond defaults
CREATE TABLE `ibCustomSecurities` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `securityName` varchar(100) NOT NULL COMMENT 'Commodities, Bonds, etc.',
  `securityDescription` varchar(500) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `createdBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukSecurityName` (`securityName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Custom securities for commission configuration';

-- Custom Symbols
-- Admin-defined custom trading symbols
CREATE TABLE `ibCustomSymbols` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `symbolName` varchar(50) NOT NULL COMMENT 'EURUSD, GBPUSD, BTCUSD, etc.',
  `symbolDescription` varchar(500) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `createdBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukSymbolName` (`symbolName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Custom symbols for commission configuration';

-- Insert some default symbols
INSERT INTO `ibCustomSymbols` (`symbolName`, `symbolDescription`) VALUES
('EURUSD', 'Euro vs US Dollar'),
('GBPUSD', 'British Pound vs US Dollar'),
('AUDUSD', 'Australian Dollar vs US Dollar'),
('USDJPY', 'US Dollar vs Japanese Yen'),
('XAUUSD', 'Gold vs US Dollar'),
('XAGUSD', 'Silver vs US Dollar'),
('BTCUSD', 'Bitcoin vs US Dollar'),
('ETHUSD', 'Ethereum vs US Dollar');

-- ============================================================
-- IB PERFORMANCE TRACKING
-- ============================================================

-- IB Performance Metrics
-- Tracks monthly performance metrics for each IB
CREATE TABLE `ibPerformanceMetrics` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ibPartnerId` int(11) UNSIGNED NOT NULL,
  `metricPeriod` date NOT NULL COMMENT 'First day of the month for monthly metrics',
  `totalClients` int(11) NOT NULL DEFAULT 0,
  `activeClients` int(11) NOT NULL DEFAULT 0,
  `newClients` int(11) NOT NULL DEFAULT 0 COMMENT 'New clients acquired this period',
  `totalTradingVolume` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT 'Trading volume in USD',
  `numberOfTrades` int(11) NOT NULL DEFAULT 0,
  `commissionEarned` decimal(15,2) NOT NULL DEFAULT 0.00,
  `averageClientValue` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Avg trading volume per client',
  `clientRetentionRate` decimal(5,2) DEFAULT NULL COMMENT 'Client retention percentage',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukIbPartnerPeriod` (`ibPartnerId`, `metricPeriod`),
  KEY `idxIbPartnerId` (`ibPartnerId`),
  KEY `idxMetricPeriod` (`metricPeriod`),
  CONSTRAINT `fkIbPerformancePartner` FOREIGN KEY (`ibPartnerId`) REFERENCES `ibPartners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB monthly performance metrics';

-- Insert sample performance data
INSERT INTO `ibPerformanceMetrics` (`ibPartnerId`, `metricPeriod`, `totalClients`, `activeClients`, `newClients`, `totalTradingVolume`, `numberOfTrades`, `commissionEarned`, `averageClientValue`) VALUES
(1, '2024-01-01', 156, 142, 12, 2450000.00, 8543, 15705.50, 15705.13),
(2, '2024-02-01', 98, 88, 8, 1850000.00, 5234, 12890.25, 18877.55),
(3, '2024-03-01', 75, 68, 6, 1950000.00, 6123, 14320.00, 26000.00);

-- IB Referral Codes
-- Unique referral codes for IB partner tracking
CREATE TABLE `ibReferralCodes` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ibPartnerId` int(11) UNSIGNED NOT NULL,
  `referralCode` varchar(50) NOT NULL COMMENT 'Unique referral code',
  `codeName` varchar(100) DEFAULT NULL COMMENT 'Optional name for this code',
  `codeType` enum('default','campaign','custom') NOT NULL DEFAULT 'default',
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `usageCount` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of times used',
  `maxUsageLimit` int(11) DEFAULT NULL COMMENT 'Maximum usage limit (NULL = unlimited)',
  `expiresAt` datetime DEFAULT NULL COMMENT 'Code expiry date',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukReferralCode` (`referralCode`),
  KEY `idxIbPartnerId` (`ibPartnerId`),
  KEY `idxIsActive` (`isActive`),
  CONSTRAINT `fkIbReferralCodePartner` FOREIGN KEY (`ibPartnerId`) REFERENCES `ibPartners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB referral codes';

-- Insert sample referral codes
INSERT INTO `ibReferralCodes` (`ibPartnerId`, `referralCode`, `codeName`, `codeType`, `isActive`, `usageCount`) VALUES
(1, 'GTP2024', 'Global Trading Partners Main Code', 'default', 1, 156),
(2, 'APFS2024', 'APAC Solutions Main Code', 'default', 1, 98),
(3, 'EMG2024', 'European Markets Main Code', 'default', 1, 75);

-- ============================================================
-- IB ACTIVITY LOGGING
-- ============================================================

-- IB Activity Log
-- Logs all significant IB-related activities
CREATE TABLE `ibActivityLog` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ibPartnerId` int(11) UNSIGNED DEFAULT NULL COMMENT 'Related IB partner',
  `applicationId` int(11) UNSIGNED DEFAULT NULL COMMENT 'Related application',
  `activityType` varchar(100) NOT NULL COMMENT 'application_submitted, application_approved, rule_assigned, commission_paid, etc.',
  `activityDescription` text DEFAULT NULL,
  `performedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin or client user ID',
  `performedByType` enum('admin','client','system') NOT NULL DEFAULT 'admin',
  `ipAddress` varchar(45) DEFAULT NULL,
  `metadata` text DEFAULT NULL COMMENT 'Additional JSON metadata',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxIbPartnerId` (`ibPartnerId`),
  KEY `idxApplicationId` (`applicationId`),
  KEY `idxActivityType` (`activityType`),
  KEY `idxCreatedAt` (`createdAt`),
  CONSTRAINT `fkIbActivityLogPartner` FOREIGN KEY (`ibPartnerId`) REFERENCES `ibPartners` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fkIbActivityLogApp` FOREIGN KEY (`applicationId`) REFERENCES `ibApplications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB activity log';

-- ============================================================
-- IB STATISTICS AND AGGREGATES
-- ============================================================

-- IB Statistics Summary
-- Pre-calculated statistics for quick dashboard loading
CREATE TABLE `ibStatisticsSummary` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `summaryDate` date NOT NULL COMMENT 'Date of statistics',
  `totalIbPartners` int(11) NOT NULL DEFAULT 0,
  `activeIbPartners` int(11) NOT NULL DEFAULT 0,
  `pendingApplications` int(11) NOT NULL DEFAULT 0,
  `approvedApplicationsToday` int(11) NOT NULL DEFAULT 0,
  `totalClientsReferred` int(11) NOT NULL DEFAULT 0,
  `totalCommissionPaid` decimal(20,2) NOT NULL DEFAULT 0.00,
  `totalTradingVolume` decimal(25,2) NOT NULL DEFAULT 0.00,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukSummaryDate` (`summaryDate`),
  KEY `idxSummaryDate` (`summaryDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IB statistics summary (daily aggregates)';

-- Insert sample statistics
INSERT INTO `ibStatisticsSummary` (`summaryDate`, `totalIbPartners`, `activeIbPartners`, `pendingApplications`, `approvedApplicationsToday`, `totalClientsReferred`, `totalCommissionPaid`, `totalTradingVolume`) VALUES
('2024-01-21', 8, 6, 4, 2, 643, 165541.25, 12450000.00);

-- ============================================================
-- VIEWS FOR EASIER DATA ACCESS
-- ============================================================

-- View: IB Partners with Performance Data
CREATE OR REPLACE VIEW `vw_ibPartnersSummary` AS
SELECT
    ib.id,
    ib.ibCode,
    ib.companyName,
    ib.ibType,
    ib.contactEmail,
    ib.country,
    ib.status,
    ib.registrationDate,
    ib.totalClients,
    ib.activeClients,
    ib.totalCommissionEarned,
    ib.totalTradingVolume,
    tl.tierLevel,
    tl.tierName,
    COUNT(DISTINCT ra.ruleId) as assignedRulesCount,
    GROUP_CONCAT(DISTINCT cr.ruleName ORDER BY cr.ruleName SEPARATOR ', ') as assignedRuleNames
FROM ibPartners ib
LEFT JOIN ibTierLevels tl ON ib.tierLevelId = tl.id
LEFT JOIN ibPartnerRuleAssignments ra ON ib.id = ra.ibPartnerId
LEFT JOIN ibCommissionRules cr ON ra.ruleId = cr.id
WHERE ib.status IN ('active', 'pending')
GROUP BY ib.id;

-- View: IB Applications with Details
CREATE OR REPLACE VIEW `vw_ibApplicationsDetails` AS
SELECT
    -- 基本信息
    app.id,
    app.applicantName,
    app.applicantEmail,
    app.applicantPhone,
    app.ibType,
    app.companyName,
    app.country,
    app.expectedClients,
    app.yearsOfExperience,
    app.hasPreviousIbExperience,

    -- 申请状态和分配信息
    app.applicationStatus,
    app.applicationDate,
    app.reviewerId,
    app.auditorId,
    app.assignedTierLevelId,

    -- 审核相关时间字段
    app.reviewStartDate,
    app.reviewCompletedDate,
    app.rulesLastSavedAt,
    app.isEdit,

    -- 审批和拒绝信息
    app.approvedBy,
    app.rejectedBy,
    app.rejectionReason,
    app.additionalInfoRequest,

    -- 关联信息
    app.createdIbPartnerId,
    app.createdAt,
    app.updatedAt,
    app.updatedBy,

    -- Tier Level 信息
    tl.tierLevel,
    tl.tierName AS assignedTierName,
    tl.tierDescription AS assignedTierDescription,

    -- 操作员（Operator）信息 - reviewerId 对应操作员
    COALESCE(operator.fullName, operator.username, '') AS reviewerName,
    operator.email AS reviewerEmail,

    -- 审批员（Reviewer/Auditor）信息 - auditorId 对应审批员
    COALESCE(auditor.fullName, auditor.username, '') AS auditorName,
    auditor.email AS auditorEmail,

    -- Product Focus 信息
    pf.primaryProducts,
    pf.targetRegions,
    pf.marketingChannels,

    -- 已分配的规则数量
    COUNT(DISTINCT ra.ruleId) AS preAssignedRulesCount,

    -- 已分配的规则列表（JSON格式）
    (SELECT JSON_ARRAYAGG(
        JSON_OBJECT(
            'id', r.id,
            'ruleName', r.ruleName,
            'ruleType', r.ruleType
        )
    ) FROM ibApplicationRuleAssignments ar
    INNER JOIN ibCommissionRules r ON ar.ruleId = r.id
    WHERE ar.applicationId = app.id) AS preAssignedRules

FROM ibApplications app
LEFT JOIN ibTierLevels tl ON app.assignedTierLevelId = tl.id
LEFT JOIN ibApplicationProductFocus pf ON app.id = pf.applicationId
LEFT JOIN ibApplicationRuleAssignments ra ON app.id = ra.applicationId
-- JOIN 操作员（Operator）表 - reviewerId 对应操作员
LEFT JOIN adminUsers operator ON app.reviewerId = operator.id
-- JOIN 审批员（Auditor）表 - auditorId 对应审批员
LEFT JOIN adminUsers auditor ON app.auditorId = auditor.id
GROUP BY
    app.id,
    app.applicantName,
    app.applicantEmail,
    app.applicantPhone,
    app.ibType,
    app.companyName,
    app.country,
    app.expectedClients,
    app.yearsOfExperience,
    app.hasPreviousIbExperience,
    app.applicationStatus,
    app.applicationDate,
    app.reviewerId,
    app.auditorId,
    app.assignedTierLevelId,
    app.reviewStartDate,
    app.reviewCompletedDate,
    app.rulesLastSavedAt,
    app.isEdit,
    app.approvedBy,
    app.rejectedBy,
    app.rejectionReason,
    app.additionalInfoRequest,
    app.createdIbPartnerId,
    app.createdAt,
    app.updatedAt,
    app.updatedBy,
    tl.tierLevel,
    tl.tierName,
    tl.tierDescription,
    operator.fullName,
    operator.username,
    operator.email,
    auditor.fullName,
    auditor.username,
    auditor.email,
    pf.primaryProducts,
    pf.targetRegions,
    pf.marketingChannels;

-- View: IB Commission Rules with Product Count
CREATE OR REPLACE VIEW `vw_ibCommissionRulesSummary` AS
SELECT
    cr.id,
    cr.ruleName,
    cr.ruleType,
    cr.targetRegion,
    cr.paymentCycle,
    cr.paymentDay,
    cr.minimumPayout,
    cr.payoutCurrency,
    cr.status,
    cr.createdAt,
    COUNT(DISTINCT rp.id) as productCount,
    COUNT(DISTINCT ar.id) as additionalRulesCount,
    COUNT(DISTINCT pa.ibPartnerId) as assignedIbCount
FROM ibCommissionRules cr
LEFT JOIN ibRuleProducts rp ON cr.id = rp.ruleId
LEFT JOIN ibRuleAdditionalRules ar ON cr.id = ar.ruleId
LEFT JOIN ibPartnerRuleAssignments pa ON cr.id = pa.ruleId
GROUP BY cr.id;

-- ============================================================
-- INDEXES FOR PERFORMANCE OPTIMIZATION
-- ============================================================

-- Additional composite indexes for common queries

-- IB Partners with tier and status
CREATE INDEX `idxIbPartnersTierStatus` ON `ibPartners` (`tierLevelId`, `status`);

-- IB Applications by status and date
CREATE INDEX `idxIbAppsStatusDate` ON `ibApplications` (`applicationStatus`, `applicationDate` DESC);

-- Commission history by partner and period
CREATE INDEX `idxCommissionHistoryPartnerPeriod` ON `ibCommissionHistory` (`ibPartnerId`, `commissionPeriodStart`, `commissionPeriodEnd`);

-- Rule products by rule and product type
CREATE INDEX `idxRuleProductsRuleType` ON `ibRuleProducts` (`ruleId`, `productType`);

-- ============================================================
-- STORED PROCEDURES FOR COMMON OPERATIONS
-- ============================================================

-- Procedure: Approve IB Application
DELIMITER $$

CREATE PROCEDURE `sp_approveIbApplication`(
    IN p_applicationId INT,
    IN p_approvedBy BIGINT,
    IN p_tierLevelId INT,
    OUT p_ibPartnerId INT,
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(500)
)
BEGIN
    DECLARE v_applicantEmail VARCHAR(200);
    DECLARE v_companyName VARCHAR(200);
    DECLARE v_ibCode VARCHAR(50);
    DECLARE v_existingIbId INT;
    DECLARE v_ibCount INT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Error occurred during IB application approval';
    END;

    START TRANSACTION;

    -- Check if application exists and is in correct status
    SELECT applicantEmail, companyName
    INTO v_applicantEmail, v_companyName
    FROM ibApplications
    WHERE id = p_applicationId
      AND applicationStatus IN ('pending', 'in_review', 'more_info_requested');

    IF v_applicantEmail IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'Application not found or not in approvable status';
        ROLLBACK;
    ELSE
        -- Generate IB code
        SELECT COUNT(*) + 1 INTO v_ibCount FROM ibPartners WHERE YEAR(registrationDate) = YEAR(CURDATE());
        SET v_ibCode = CONCAT('IB-', YEAR(CURDATE()), '-', LPAD(v_ibCount, 3, '0'));

        -- Create IB partner record
        INSERT INTO ibPartners (
            ibCode,
            companyName,
            ibType,
            tierLevelId,
            contactPerson,
            contactEmail,
            contactPhone,
            country,
            registrationDate,
            approvalDate,
            approvedBy,
            status
        )
        SELECT
            v_ibCode,
            COALESCE(companyName, applicantName),
            ibType,
            p_tierLevelId,
            applicantName,
            applicantEmail,
            applicantPhone,
            country,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP,
            p_approvedBy,
            'active'
        FROM ibApplications
        WHERE id = p_applicationId;

        SET p_ibPartnerId = LAST_INSERT_ID();

        -- Update application status
        UPDATE ibApplications
        SET applicationStatus = 'approved',
            reviewCompletedDate = CURRENT_TIMESTAMP,
            approvedBy = p_approvedBy,
            createdIbPartnerId = p_ibPartnerId
        WHERE id = p_applicationId;

        -- Copy rule assignments from application to IB partner
        INSERT INTO ibPartnerRuleAssignments (ibPartnerId, ruleId, assignedBy)
        SELECT p_ibPartnerId, ruleId, p_approvedBy
        FROM ibApplicationRuleAssignments
        WHERE applicationId = p_applicationId;

        -- Log activity
        INSERT INTO ibActivityLog (ibPartnerId, applicationId, activityType, activityDescription, performedBy, performedByType)
        VALUES (p_ibPartnerId, p_applicationId, 'application_approved', CONCAT('IB application approved. IB Code: ', v_ibCode), p_approvedBy, 'admin');

        COMMIT;

        SET p_success = TRUE;
        SET p_message = CONCAT('IB application approved successfully. IB Code: ', v_ibCode);
    END IF;
END$$

DELIMITER ;

-- Procedure: Reject IB Application
DELIMITER $$

CREATE PROCEDURE `sp_rejectIbApplication`(
    IN p_applicationId INT,
    IN p_rejectedBy BIGINT,
    IN p_rejectionReason TEXT,
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(500)
)
BEGIN
    DECLARE v_applicantEmail VARCHAR(200);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Error occurred during IB application rejection';
    END;

    START TRANSACTION;

    -- Check if application exists
    SELECT applicantEmail
    INTO v_applicantEmail
    FROM ibApplications
    WHERE id = p_applicationId
      AND applicationStatus IN ('pending', 'in_review', 'more_info_requested');

    IF v_applicantEmail IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'Application not found or not in rejectable status';
        ROLLBACK;
    ELSE
        -- Update application status
        UPDATE ibApplications
        SET applicationStatus = 'rejected',
            reviewCompletedDate = CURRENT_TIMESTAMP,
            rejectedBy = p_rejectedBy,
            rejectionReason = p_rejectionReason
        WHERE id = p_applicationId;

        -- Log activity
        INSERT INTO ibActivityLog (applicationId, activityType, activityDescription, performedBy, performedByType)
        VALUES (p_applicationId, 'application_rejected', CONCAT('IB application rejected. Reason: ', p_rejectionReason), p_rejectedBy, 'admin');

        COMMIT;

        SET p_success = TRUE;
        SET p_message = 'IB application rejected successfully';
    END IF;
END$$

DELIMITER ;

-- Procedure: Calculate IB Commission for Period
DELIMITER $$

CREATE PROCEDURE `sp_calculateIbCommission`(
    IN p_ibPartnerId INT,
    IN p_periodStart DATE,
    IN p_periodEnd DATE,
    OUT p_totalCommission DECIMAL(15,2),
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(500)
)
BEGIN
    DECLARE v_ibExists INT;

    -- Check if IB exists
    SELECT COUNT(*) INTO v_ibExists FROM ibPartners WHERE id = p_ibPartnerId;

    IF v_ibExists = 0 THEN
        SET p_success = FALSE;
        SET p_message = 'IB partner not found';
        SET p_totalCommission = 0.00;
    ELSE
        -- This is a simplified calculation
        -- In production, this would integrate with trading system to calculate actual commissions
        -- based on client trades, volumes, and assigned commission rules

        SET p_totalCommission = 0.00;

        -- Log commission calculation
        INSERT INTO ibActivityLog (ibPartnerId, activityType, activityDescription, performedByType, metadata)
        VALUES (
            p_ibPartnerId,
            'commission_calculated',
            CONCAT('Commission calculated for period ', p_periodStart, ' to ', p_periodEnd),
            'system',
            JSON_OBJECT('periodStart', p_periodStart, 'periodEnd', p_periodEnd, 'amount', p_totalCommission)
        );

        SET p_success = TRUE;
        SET p_message = 'Commission calculated successfully';
    END IF;
END$$

DELIMITER ;

-- Procedure: Get IB Network Tree
DELIMITER $$

CREATE PROCEDURE `sp_getIbNetworkTree`(
    IN p_rootIbPartnerId INT,
    IN p_maxDepth INT
)
BEGIN
    -- Recursive CTE to get IB network hierarchy
    WITH RECURSIVE ibTree AS (
        -- Root level
        SELECT
            ib.id,
            ib.ibCode,
            ib.companyName,
            ib.tierLevelId,
            tl.tierName,
            nh.hierarchyLevel,
            nh.parentIbPartnerId,
            1 as depth,
            CAST(ib.id AS CHAR(500)) as path
        FROM ibPartners ib
        LEFT JOIN ibNetworkHierarchy nh ON ib.id = nh.ibPartnerId
        LEFT JOIN ibTierLevels tl ON ib.tierLevelId = tl.id
        WHERE ib.id = p_rootIbPartnerId

        UNION ALL

        -- Child levels
        SELECT
            ib.id,
            ib.ibCode,
            ib.companyName,
            ib.tierLevelId,
            tl.tierName,
            nh.hierarchyLevel,
            nh.parentIbPartnerId,
            tree.depth + 1,
            CONCAT(tree.path, ',', ib.id)
        FROM ibPartners ib
        INNER JOIN ibNetworkHierarchy nh ON ib.id = nh.ibPartnerId
        INNER JOIN ibTree tree ON nh.parentIbPartnerId = tree.id
        LEFT JOIN ibTierLevels tl ON ib.tierLevelId = tl.id
        WHERE tree.depth < p_maxDepth
    )
    SELECT
        t.*,
        ib.totalClients,
        ib.activeClients,
        ib.status,
        (SELECT COUNT(*) FROM ibNetworkHierarchy WHERE parentIbPartnerId = t.id) as directSubIbsCount,
        (SELECT COUNT(*) FROM ibClientRelationships WHERE ibPartnerId = t.id) as directClientsCount
    FROM ibTree t
    LEFT JOIN ibPartners ib ON t.id = ib.id
    ORDER BY depth, hierarchyLevel;
END$$

DELIMITER ;

-- ============================================================
-- TRIGGERS FOR AUTOMATIC DATA MANAGEMENT
-- ============================================================

-- Trigger: Update IB partner client counts when relationship is added
DELIMITER $$

CREATE TRIGGER `trg_ibClientRelationship_afterInsert`
AFTER INSERT ON `ibClientRelationships`
FOR EACH ROW
BEGIN
    UPDATE ibPartners
    SET totalClients = totalClients + 1,
        activeClients = activeClients + IF(NEW.isActive = 1, 1, 0)
    WHERE id = NEW.ibPartnerId;
END$$

DELIMITER ;

-- Trigger: Update IB partner client counts when relationship is updated
DELIMITER $$

CREATE TRIGGER `trg_ibClientRelationship_afterUpdate`
AFTER UPDATE ON `ibClientRelationships`
FOR EACH ROW
BEGIN
    IF OLD.isActive != NEW.isActive THEN
        UPDATE ibPartners
        SET activeClients = activeClients + IF(NEW.isActive = 1, 1, -1)
        WHERE id = NEW.ibPartnerId;
    END IF;
END$$

DELIMITER ;

-- Trigger: Update IB partner client counts when relationship is deleted
DELIMITER $$

CREATE TRIGGER `trg_ibClientRelationship_afterDelete`
AFTER DELETE ON `ibClientRelationships`
FOR EACH ROW
BEGIN
    UPDATE ibPartners
    SET totalClients = totalClients - 1,
        activeClients = activeClients - IF(OLD.isActive = 1, 1, 0)
    WHERE id = OLD.ibPartnerId;
END$$

DELIMITER ;

-- Trigger: Auto-generate IB code if not provided
DELIMITER $$

CREATE TRIGGER `trg_ibPartners_beforeInsert`
BEFORE INSERT ON `ibPartners`
FOR EACH ROW
BEGIN
    DECLARE v_ibCount INT;

    IF NEW.ibCode IS NULL OR NEW.ibCode = '' THEN
        SELECT COUNT(*) + 1 INTO v_ibCount FROM ibPartners WHERE YEAR(registrationDate) = YEAR(CURDATE());
        SET NEW.ibCode = CONCAT('IB-', YEAR(CURDATE()), '-', LPAD(v_ibCount, 3, '0'));
    END IF;
END$$

DELIMITER ;

-- Trigger: Log application status changes
DELIMITER $$

CREATE TRIGGER `trg_ibApplications_afterUpdate`
AFTER UPDATE ON `ibApplications`
FOR EACH ROW
BEGIN
    IF OLD.applicationStatus != NEW.applicationStatus THEN
        INSERT INTO ibApplicationStatusHistory (applicationId, previousStatus, newStatus, changedBy, notes)
        VALUES (NEW.id, OLD.applicationStatus, NEW.applicationStatus, NEW.updatedBy, CONCAT('Status changed from ', OLD.applicationStatus, ' to ', NEW.applicationStatus));
    END IF;
END$$

DELIMITER ;

-- Trigger: Update document stats when template is updated
DELIMITER $$

CREATE TRIGGER `trg_ibDocumentTemplates_beforeUpdate`
BEFORE UPDATE ON `ibDocumentTemplates`
FOR EACH ROW
BEGIN
    DECLARE v_wordCount INT;
    DECLARE v_charCount INT;
    DECLARE v_readTime INT;

    -- Calculate word count (approximate from HTML content)
    SET v_charCount = CHAR_LENGTH(REGEXP_REPLACE(NEW.documentContent, '<[^>]*>', ''));
    SET v_wordCount = (CHAR_LENGTH(NEW.documentContent) - CHAR_LENGTH(REPLACE(NEW.documentContent, ' ', ''))) + 1;

    -- Calculate read time (200 words per minute)
    SET v_readTime = CEILING(v_wordCount / 200);
    IF v_readTime < 1 THEN
        SET v_readTime = 1;
    END IF;

    SET NEW.wordCount = v_wordCount;
    SET NEW.characterCount = v_charCount;
    SET NEW.estimatedReadTime = v_readTime;
END$$

DELIMITER ;

-- ============================================================
-- SAMPLE QUERIES FOR COMMON USE CASES
-- ============================================================

/*
-- 1. Get all pending IB applications with assigned tier information
SELECT
    app.id,
    app.applicantName,
    app.applicantEmail,
    app.ibType,
    app.expectedClients,
    app.applicationDate,
    tl.tierName as assignedTier,
    COUNT(ara.ruleId) as assignedRulesCount
FROM ibApplications app
LEFT JOIN ibTierLevels tl ON app.assignedTierLevelId = tl.id
LEFT JOIN ibApplicationRuleAssignments ara ON app.id = ara.applicationId
WHERE app.applicationStatus = 'pending'
GROUP BY app.id
ORDER BY app.applicationDate DESC;

-- 2. Get IB partner with all assigned rules and their details
SELECT
    ib.id,
    ib.ibCode,
    ib.companyName,
    ib.totalClients,
    ib.totalCommissionEarned,
    cr.ruleName,
    cr.paymentCycle,
    COUNT(rp.id) as ruleProductCount
FROM ibPartners ib
INNER JOIN ibPartnerRuleAssignments ra ON ib.id = ra.ibPartnerId
INNER JOIN ibCommissionRules cr ON ra.ruleId = cr.id
LEFT JOIN ibRuleProducts rp ON cr.id = rp.ruleId
WHERE ib.id = 1
GROUP BY ib.id, cr.id;

-- 3. Get IB network hierarchy for specific IB
CALL sp_getIbNetworkTree(1, 5);

-- 4. Get all active commission rules with their product configurations
SELECT
    cr.id,
    cr.ruleName,
    cr.ruleType,
    cr.paymentCycle,
    cr.minimumPayout,
    rp.productName,
    rp.commissionType,
    rp.commissionRate,
    rp.additionalRate
FROM ibCommissionRules cr
INNER JOIN ibRuleProducts rp ON cr.id = rp.ruleId
WHERE cr.status = 'active'
ORDER BY cr.id, rp.id;

-- 5. Get top performing IB partners by commission earned
SELECT
    ib.ibCode,
    ib.companyName,
    ib.totalClients,
    ib.totalCommissionEarned,
    ib.totalTradingVolume,
    tl.tierName,
    COUNT(DISTINCT cr.clientId) as directClients
FROM ibPartners ib
LEFT JOIN ibTierLevels tl ON ib.tierLevelId = tl.id
LEFT JOIN ibClientRelationships cr ON ib.id = cr.ibPartnerId
WHERE ib.status = 'active'
GROUP BY ib.id
ORDER BY ib.totalCommissionEarned DESC
LIMIT 10;

-- 6. Get IB application statistics
SELECT
    COUNT(*) as totalApplications,
    SUM(CASE WHEN applicationStatus = 'pending' THEN 1 ELSE 0 END) as pendingCount,
    SUM(CASE WHEN applicationStatus = 'in_review' THEN 1 ELSE 0 END) as inReviewCount,
    SUM(CASE WHEN applicationStatus = 'approved' THEN 1 ELSE 0 END) as approvedCount,
    SUM(CASE WHEN applicationStatus = 'rejected' THEN 1 ELSE 0 END) as rejectedCount,
    SUM(CASE WHEN DATE(applicationDate) = CURDATE() THEN 1 ELSE 0 END) as todayApplications
FROM ibApplications;

-- 7. Get commission rules summary with usage statistics
SELECT * FROM vw_ibCommissionRulesSummary
WHERE status = 'active'
ORDER BY assignedIbCount DESC;

-- 8. Get IB documents that need renewal (if expiry is set)
SELECT
    ib.ibCode,
    ib.companyName,
    dt.documentTitle,
    pda.acknowledgedAt,
    pda.expiresAt,
    DATEDIFF(pda.expiresAt, CURDATE()) as daysUntilExpiry
FROM ibPartnerDocumentAcknowledgements pda
INNER JOIN ibPartners ib ON pda.ibPartnerId = ib.id
INNER JOIN ibDocumentTemplates dt ON pda.documentTemplateId = dt.id
WHERE pda.expiresAt IS NOT NULL
  AND pda.expiresAt <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
  AND ib.status = 'active'
ORDER BY pda.expiresAt ASC;
*/

-- ============================================================
-- FINAL SETUP
-- ============================================================

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

-- ============================================================
-- END OF IB MANAGEMENT DATABASE SCHEMA
-- ============================================================
