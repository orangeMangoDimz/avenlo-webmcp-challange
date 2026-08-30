-- ============================================================
-- Funding Management Database Schema
-- For Deposit & Withdrawal Management System
-- ============================================================
-- Created: 2025-11-13
-- Description: Database tables for deposit/withdrawal management,
--              payment methods, transaction settings, and reports
-- Related Pages: DepositManagement.html, WithdrawManagement.html,
--                TransactionSettings.html, client-transactions.html,
--                FundingReport.html
-- Naming Convention: camelCase
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================
-- PAYMENT METHOD CONFIGURATION
-- ============================================================

-- Payment Method Master Table
-- Defines available payment methods for deposits and withdrawals
CREATE TABLE `paymentMethods` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `methodKey` varchar(50) NOT NULL COMMENT 'Unique key: bitcoin, ethereum, usdt, usdc, bank_transfer, alchemy_pay',
  `methodName` varchar(100) NOT NULL COMMENT 'Display name: Bitcoin, Ethereum, USDT, etc.',
  `methodType` enum('crypto','fiat') NOT NULL DEFAULT 'crypto',
  `iconClass` varchar(100) DEFAULT NULL COMMENT 'FontAwesome icon class',
  `shortCode` varchar(20) DEFAULT NULL COMMENT 'BTC, ETH, USDT, etc.',
  `networkName` varchar(100) DEFAULT NULL COMMENT 'Bitcoin Mainnet, Ethereum (ERC-20), etc.',
  `isDepositEnabled` tinyint(1) NOT NULL DEFAULT 1,
  `isWithdrawalEnabled` tinyint(1) NOT NULL DEFAULT 1,
  `processingTime` varchar(100) DEFAULT NULL COMMENT '15-30 min, 1-3 days, etc.',
  `displayOrder` int(11) NOT NULL DEFAULT 0,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukMethodKey` (`methodKey`),
  KEY `idxMethodType` (`methodType`),
  KEY `idxDisplayOrder` (`displayOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment methods master table';

-- Insert default payment methods
INSERT INTO `paymentMethods` (`methodKey`, `methodName`, `methodType`, `iconClass`, `shortCode`, `networkName`, `processingTime`, `displayOrder`) VALUES
('bitcoin', 'Bitcoin', 'crypto', 'fab fa-bitcoin', 'BTC', 'Bitcoin Mainnet', '15-30 minutes', 1),
('ethereum', 'Ethereum', 'crypto', 'fab fa-ethereum', 'ETH', 'Ethereum Mainnet (ERC-20)', '15-30 minutes', 2),
('usdt', 'Tether', 'crypto', 'fas fa-coins', 'USDT', 'Ethereum (ERC-20) / Tron (TRC-20)', '15-30 minutes', 3),
('usdc', 'USD Coin', 'crypto', 'fas fa-coins', 'USDC', 'Ethereum (ERC-20)', '15-30 minutes', 4),
('bank_transfer', 'Bank Transfer', 'fiat', 'fas fa-university', 'BANK', 'Wire Transfer', '1-3 business days', 5),
('alchemy_pay', 'AlchemyPay', 'fiat', 'fas fa-credit-card', 'ACH', 'Credit/Debit Card', 'Instant', 6);

-- ============================================================
-- CRYPTOCURRENCY DEPOSIT ADDRESS CONFIGURATION
-- ============================================================

-- Crypto Deposit Addresses
-- Stores organization's wallet addresses for receiving deposits
CREATE TABLE `cryptoDepositAddresses` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `paymentMethodId` int(11) UNSIGNED NOT NULL,
  `walletAddress` varchar(255) NOT NULL COMMENT 'Blockchain wallet address',
  `networkType` varchar(50) DEFAULT NULL COMMENT 'mainnet, erc20, trc20, bep20',
  `qrCodePath` varchar(500) DEFAULT NULL COMMENT 'Path to QR code image',
  `minimumDeposit` decimal(15,8) NOT NULL DEFAULT 0.00000000 COMMENT 'Minimum deposit amount in crypto',
  `minimumDepositUsd` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Minimum deposit in USD equivalent',
  `confirmationBlocks` int(11) NOT NULL DEFAULT 3 COMMENT 'Required confirmations',
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  PRIMARY KEY (`id`),
  KEY `idxPaymentMethodId` (`paymentMethodId`),
  KEY `idxIsActive` (`isActive`),
  CONSTRAINT `fkCryptoAddressPaymentMethod` FOREIGN KEY (`paymentMethodId`) REFERENCES `paymentMethods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cryptocurrency deposit addresses configuration';

-- Insert default crypto addresses (examples)
INSERT INTO `cryptoDepositAddresses` (`paymentMethodId`, `walletAddress`, `networkType`, `minimumDeposit`, `minimumDepositUsd`, `confirmationBlocks`) VALUES
(1, 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh', 'mainnet', 0.001, 10.00, 3),
(2, '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0', 'erc20', 0.01, 10.00, 12),
(3, '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0', 'erc20', 10.00, 10.00, 12),
(4, '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0', 'erc20', 10.00, 10.00, 12);

-- ============================================================
-- PAYMENT GATEWAY CONFIGURATION (AlchemyPay)
-- ============================================================

-- Payment Gateway Settings
-- Stores configuration for third-party payment gateways
CREATE TABLE `paymentGatewaySettings` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `gatewayKey` varchar(50) NOT NULL COMMENT 'alchemy_pay, stripe, etc.',
  `gatewayName` varchar(100) NOT NULL,
  `isEnabled` tinyint(1) NOT NULL DEFAULT 1,
  `environment` enum('sandbox','production') NOT NULL DEFAULT 'production',
  `appId` varchar(255) DEFAULT NULL,
  `apiKey` varchar(500) DEFAULT NULL COMMENT 'Encrypted API key',
  `secretKey` varchar(500) DEFAULT NULL COMMENT 'Encrypted secret key',
  `merchantName` varchar(200) DEFAULT NULL,
  `webhookUrl` varchar(500) DEFAULT NULL,
  `returnUrl` varchar(500) DEFAULT NULL,
  `supportedFiatCurrencies` text DEFAULT NULL COMMENT 'JSON array: ["USD","EUR","GBP"]',
  `supportedCryptoCurrencies` text DEFAULT NULL COMMENT 'JSON array: ["BTC","ETH","USDT"]',
  `configData` text DEFAULT NULL COMMENT 'Additional JSON configuration',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukGatewayKey` (`gatewayKey`),
  KEY `idxIsEnabled` (`isEnabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment gateway configuration';

-- Insert default AlchemyPay gateway settings
INSERT INTO `paymentGatewaySettings` (`gatewayKey`, `gatewayName`, `isEnabled`, `environment`, `merchantName`, `supportedFiatCurrencies`, `supportedCryptoCurrencies`) VALUES
('alchemy_pay', 'AlchemyPay', 1, 'production', 'BDX Trading', '["USD","EUR","GBP"]', '["BTC","ETH","USDT","USDC"]');

-- ============================================================
-- TRANSACTION LIMITS AND FEES CONFIGURATION
-- ============================================================

-- Transaction Limits Configuration
-- Defines deposit and withdrawal limits
CREATE TABLE `transactionLimits` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `transactionType` enum('deposit','withdrawal') NOT NULL,
  `paymentType` enum('crypto','fiat','all') NOT NULL DEFAULT 'all',
  `minimumAmount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Minimum transaction amount in USD',
  `maximumAmount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Maximum per transaction in USD',
  `dailyLimit` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Maximum per day in USD',
  `monthlyLimit` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Maximum per month in USD',
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  PRIMARY KEY (`id`),
  KEY `idxTransactionType` (`transactionType`),
  KEY `idxPaymentType` (`paymentType`),
  KEY `idxIsActive` (`isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Transaction limits configuration';

-- Insert default transaction limits
INSERT INTO `transactionLimits` (`transactionType`, `paymentType`, `minimumAmount`, `maximumAmount`, `dailyLimit`, `monthlyLimit`) VALUES
('deposit', 'all', 10.00, 50000.00, 100000.00, 500000.00),
('withdrawal', 'all', 50.00, 100000.00, 100000.00, 500000.00);

-- Transaction Fees Configuration
-- Defines fees for different transaction types
CREATE TABLE `transactionFees` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `transactionType` enum('deposit','withdrawal') NOT NULL,
  `paymentType` enum('crypto','fiat') NOT NULL,
  `feeType` enum('percentage','fixed','both') NOT NULL DEFAULT 'percentage',
  `feePercentage` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Fee percentage (e.g., 2.50 for 2.5%)',
  `feeFixed` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Fixed fee amount in USD',
  `chargeToClient` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=client pays, 0=platform absorbs',
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  PRIMARY KEY (`id`),
  KEY `idxTransactionType` (`transactionType`),
  KEY `idxPaymentType` (`paymentType`),
  KEY `idxIsActive` (`isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Transaction fees configuration';

-- Insert default transaction fees
INSERT INTO `transactionFees` (`transactionType`, `paymentType`, `feeType`, `feePercentage`, `feeFixed`, `chargeToClient`) VALUES
('deposit', 'crypto', 'percentage', 0.00, 0.00, 1),
('deposit', 'fiat', 'percentage', 2.50, 0.00, 1),
('withdrawal', 'crypto', 'fixed', 0.00, 0.00, 1),
('withdrawal', 'fiat', 'percentage', 0.00, 0.00, 0);

-- ============================================================
-- DEPOSIT MANAGEMENT TABLES
-- ============================================================

-- Deposits Table
-- Main table for all deposit transactions
CREATE TABLE `deposits` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `transactionId` varchar(50) NOT NULL COMMENT 'Unique transaction ID (TXN-YYYYMMDD-XXXXX)',
  `userId` int(11) UNSIGNED NOT NULL COMMENT 'Client user ID',
  `tradingAccountId` int(11) UNSIGNED DEFAULT NULL COMMENT 'Target trading account',
  `paymentMethodId` int(11) UNSIGNED NOT NULL,
  `amount` decimal(15,2) NOT NULL COMMENT 'Deposit amount in USD',
  `amountCrypto` decimal(20,8) DEFAULT NULL COMMENT 'Amount in cryptocurrency if applicable',
  `cryptoNetwork` varchar(50) DEFAULT NULL COMMENT 'erc20, trc20, bep20, mainnet',
  `fromAddress` varchar(255) DEFAULT NULL COMMENT 'Sender wallet address or bank account',
  `toAddress` varchar(255) DEFAULT NULL COMMENT 'Our receiving wallet address',
  `networkFee` decimal(15,2) DEFAULT 0.00 COMMENT 'Network/transaction fee',
  `platformFee` decimal(15,2) DEFAULT 0.00 COMMENT 'Platform service fee',
  `netAmount` decimal(15,2) NOT NULL COMMENT 'Net amount credited to client',
  `exchangeRate` decimal(20,8) DEFAULT NULL COMMENT 'Crypto to USD exchange rate at time of deposit',
  `status` enum('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `confirmations` int(11) DEFAULT 0 COMMENT 'Current blockchain confirmations',
  `requiredConfirmations` int(11) DEFAULT 3 COMMENT 'Required confirmations',
  `transactionHash` varchar(255) DEFAULT NULL COMMENT 'Blockchain transaction hash',
  `gatewayTransactionId` varchar(255) DEFAULT NULL COMMENT 'External gateway transaction ID',
  `gatewayResponse` text DEFAULT NULL COMMENT 'JSON response from payment gateway',
  `requestedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When client initiated deposit',
  `approvedAt` datetime DEFAULT NULL COMMENT 'When admin approved',
  `approvedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `completedAt` datetime DEFAULT NULL COMMENT 'When deposit was completed',
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
  KEY `idxTradingAccountId` (`tradingAccountId`),
  KEY `idxPaymentMethodId` (`paymentMethodId`),
  KEY `idxStatus` (`status`),
  KEY `idxRequestedAt` (`requestedAt`),
  KEY `idxApprovedAt` (`approvedAt`),
  KEY `idxCompletedAt` (`completedAt`),
  KEY `idxTransactionHash` (`transactionHash`),
  CONSTRAINT `fkDepositsUser` FOREIGN KEY (`userId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fkDepositsPaymentMethod` FOREIGN KEY (`paymentMethodId`) REFERENCES `paymentMethods` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fkDepositsTradingAccount` FOREIGN KEY (`tradingAccountId`) REFERENCES `tradingAccounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Client deposit transactions';

-- Deposit Status History
-- Tracks status changes for deposits (timeline feature)
CREATE TABLE `depositStatusHistory` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `depositId` bigint(20) UNSIGNED NOT NULL,
  `previousStatus` enum('pending','processing','completed','failed','cancelled') DEFAULT NULL,
  `newStatus` enum('pending','processing','completed','failed','cancelled') NOT NULL,
  `description` varchar(500) DEFAULT NULL COMMENT 'Status change description',
  `changedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID (NULL for system changes)',
  `metadata` text DEFAULT NULL COMMENT 'Additional JSON data',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxDepositId` (`depositId`),
  KEY `idxNewStatus` (`newStatus`),
  KEY `idxCreatedAt` (`createdAt`),
  CONSTRAINT `fkDepositStatusHistoryDeposit` FOREIGN KEY (`depositId`) REFERENCES `deposits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Deposit status change history';

-- Deposit Tags
-- Master table for deposit tags
CREATE TABLE `depositTags` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tagName` varchar(100) NOT NULL,
  `tagColor` varchar(20) DEFAULT '#fef3c7' COMMENT 'Background color',
  `textColor` varchar(20) DEFAULT '#92400e' COMMENT 'Text color',
  `description` varchar(500) DEFAULT NULL,
  `isSystemTag` tinyint(1) NOT NULL DEFAULT 0,
  `createdBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukTagName` (`tagName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Deposit tags master table';

-- Insert default deposit tags
INSERT INTO `depositTags` (`tagName`, `tagColor`, `textColor`, `isSystemTag`) VALUES
('Large Amount', '#fef3c7', '#92400e', 1),
('VIP', '#ede9fe', '#5b21b6', 1),
('Crypto', '#dbeafe', '#1e3a8a', 1),
('Fiat', '#ccfbf1', '#134e4a', 1),
('Verified', '#d1fae5', '#065f46', 1),
('Priority', '#fee2e2', '#991b1b', 1),
('Stablecoin', '#fef9c3', '#713f12', 1);

-- Deposit Tag Assignments
-- Associates tags with specific deposits
CREATE TABLE `depositTagAssignments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `depositId` bigint(20) UNSIGNED NOT NULL,
  `tagId` int(11) UNSIGNED NOT NULL,
  `assignedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `assignedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukDepositTag` (`depositId`, `tagId`),
  KEY `idxDepositId` (`depositId`),
  KEY `idxTagId` (`tagId`),
  CONSTRAINT `fkDepositTagAssignmentsDeposit` FOREIGN KEY (`depositId`) REFERENCES `deposits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fkDepositTagAssignmentsTag` FOREIGN KEY (`tagId`) REFERENCES `depositTags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Deposit and tag associations';

-- ============================================================
-- WITHDRAWAL MANAGEMENT TABLES
-- ============================================================

-- Withdrawals Table
-- Main table for all withdrawal transactions
CREATE TABLE `withdrawals` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `transactionId` varchar(50) NOT NULL COMMENT 'Unique transaction ID (TXN-YYYYMMDD-WXXXXX)',
  `userId` int(11) UNSIGNED NOT NULL COMMENT 'Client user ID',
  `tradingAccountId` int(11) UNSIGNED DEFAULT NULL COMMENT 'Source trading account',
  `paymentMethodId` int(11) UNSIGNED NOT NULL,
  `amount` decimal(15,2) NOT NULL COMMENT 'Withdrawal amount in USD',
  `amountCrypto` decimal(20,8) DEFAULT NULL COMMENT 'Amount in cryptocurrency if applicable',
  `cryptoNetwork` varchar(50) DEFAULT NULL COMMENT 'erc20, trc20, bep20, mainnet',
  `destinationAddress` varchar(255) DEFAULT NULL COMMENT 'Client wallet address or bank account',
  `destinationLabel` varchar(200) DEFAULT NULL COMMENT 'Wallet label/name',
  `bankName` varchar(200) DEFAULT NULL COMMENT 'Bank name for bank transfers',
  `accountHolderName` varchar(200) DEFAULT NULL COMMENT 'Bank account holder name',
  `accountNumber` varchar(100) DEFAULT NULL COMMENT 'Bank account number',
  `swiftBic` varchar(50) DEFAULT NULL COMMENT 'SWIFT/BIC code',
  `networkFee` decimal(15,2) DEFAULT 0.00 COMMENT 'Network/transaction fee',
  `platformFee` decimal(15,2) DEFAULT 0.00 COMMENT 'Platform service fee',
  `netAmount` decimal(15,2) NOT NULL COMMENT 'Net amount sent to client',
  `exchangeRate` decimal(20,8) DEFAULT NULL COMMENT 'Crypto to USD exchange rate',
  `status` enum('pending','processing','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `withdrawalReason` varchar(500) DEFAULT NULL COMMENT 'Client provided reason',
  `transactionHash` varchar(255) DEFAULT NULL COMMENT 'Blockchain transaction hash',
  `gatewayTransactionId` varchar(255) DEFAULT NULL COMMENT 'External gateway transaction ID',
  `requestedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When client requested withdrawal',
  `approvedAt` datetime DEFAULT NULL COMMENT 'When admin approved',
  `approvedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `rejectedAt` datetime DEFAULT NULL COMMENT 'When admin rejected',
  `rejectedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `rejectionReasonId` int(11) UNSIGNED DEFAULT NULL COMMENT 'Rejection reason ID',
  `rejectionNotes` text DEFAULT NULL COMMENT 'Admin rejection notes',
  `completedAt` datetime DEFAULT NULL COMMENT 'When withdrawal was completed',
  `adminNotes` text DEFAULT NULL COMMENT 'Internal admin notes',
  `previousWithdrawalsCount30Days` int(11) DEFAULT 0 COMMENT 'Number of withdrawals in past 30 days',
  `previousWithdrawalsAmount30Days` decimal(15,2) DEFAULT 0.00 COMMENT 'Total withdrawn in past 30 days',
  `accountBalance` decimal(15,2) DEFAULT NULL COMMENT 'Account balance at time of request',
  `ipAddress` varchar(45) DEFAULT NULL COMMENT 'Client IP when requesting',
  `userAgent` text DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukTransactionId` (`transactionId`),
  KEY `idxUserId` (`userId`),
  KEY `idxTradingAccountId` (`tradingAccountId`),
  KEY `idxPaymentMethodId` (`paymentMethodId`),
  KEY `idxStatus` (`status`),
  KEY `idxRequestedAt` (`requestedAt`),
  KEY `idxApprovedAt` (`approvedAt`),
  KEY `idxCompletedAt` (`completedAt`),
  KEY `idxRejectionReasonId` (`rejectionReasonId`),
  CONSTRAINT `fkWithdrawalsUser` FOREIGN KEY (`userId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fkWithdrawalsPaymentMethod` FOREIGN KEY (`paymentMethodId`) REFERENCES `paymentMethods` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fkWithdrawalsTradingAccount` FOREIGN KEY (`tradingAccountId`) REFERENCES `tradingAccounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Client withdrawal transactions';

-- Withdrawal Status History
-- Tracks status changes for withdrawals (timeline feature)
CREATE TABLE `withdrawalStatusHistory` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `withdrawalId` bigint(20) UNSIGNED NOT NULL,
  `previousStatus` enum('pending','processing','completed','rejected','cancelled') DEFAULT NULL,
  `newStatus` enum('pending','processing','completed','rejected','cancelled') NOT NULL,
  `description` varchar(500) DEFAULT NULL COMMENT 'Status change description',
  `changedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID (NULL for system changes)',
  `metadata` text DEFAULT NULL COMMENT 'Additional JSON data',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxWithdrawalId` (`withdrawalId`),
  KEY `idxNewStatus` (`newStatus`),
  KEY `idxCreatedAt` (`createdAt`),
  CONSTRAINT `fkWithdrawalStatusHistoryWithdrawal` FOREIGN KEY (`withdrawalId`) REFERENCES `withdrawals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Withdrawal status change history';

-- Withdrawal Rejection Reasons
-- Predefined rejection reasons for withdrawals
CREATE TABLE `withdrawalRejectionReasons` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `reasonKey` varchar(50) NOT NULL COMMENT 'insufficient_docs, suspicious_activity, etc.',
  `reasonTitle` varchar(200) NOT NULL,
  `reasonDescription` varchar(500) DEFAULT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `displayOrder` int(11) NOT NULL DEFAULT 0,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukReasonKey` (`reasonKey`),
  KEY `idxIsActive` (`isActive`),
  KEY `idxDisplayOrder` (`displayOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Withdrawal rejection reasons';

-- Insert default rejection reasons
INSERT INTO `withdrawalRejectionReasons` (`reasonKey`, `reasonTitle`, `reasonDescription`, `displayOrder`) VALUES
('insufficient_docs', 'Insufficient Documentation', 'Required documents or verification information is missing or incomplete', 1),
('suspicious_activity', 'Suspicious Activity Detected', 'Unusual patterns or potential fraudulent behavior identified', 2),
('exceeds_limit', 'Exceeds Withdrawal Limit', 'Requested amount exceeds daily/monthly withdrawal limits', 3),
('insufficient_balance', 'Insufficient Available Balance', 'Account balance is insufficient after considering trading positions and fees', 4),
('invalid_destination', 'Invalid Destination Address/Account', 'Provided wallet address or bank account details are invalid or unverified', 5),
('pending_positions', 'Active Trading Positions', 'Cannot withdraw while there are open trading positions or pending orders', 6),
('compliance_issue', 'Compliance/Regulatory Issue', 'Account requires additional verification or compliance review', 7),
('terms_violation', 'Terms of Service Violation', 'Account activity violates platform terms and conditions', 8),
('custom', 'Other (Custom Reason)', 'Custom reason provided by admin', 999);

-- Withdrawal Tags
-- Master table for withdrawal tags
CREATE TABLE `withdrawalTags` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tagName` varchar(100) NOT NULL,
  `tagColor` varchar(20) DEFAULT '#fef3c7' COMMENT 'Background color',
  `textColor` varchar(20) DEFAULT '#92400e' COMMENT 'Text color',
  `description` varchar(500) DEFAULT NULL,
  `isSystemTag` tinyint(1) NOT NULL DEFAULT 0,
  `createdBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukTagName` (`tagName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Withdrawal tags master table';

-- Insert default withdrawal tags
INSERT INTO `withdrawalTags` (`tagName`, `tagColor`, `textColor`, `isSystemTag`) VALUES
('Large Amount', '#fef3c7', '#92400e', 1),
('VIP', '#ede9fe', '#5b21b6', 1),
('Crypto', '#dbeafe', '#1e3a8a', 1),
('Bank Transfer', '#ccfbf1', '#134e4a', 1),
('Verified', '#d1fae5', '#065f46', 1),
('Priority', '#fee2e2', '#991b1b', 1),
('Urgent', '#fed7aa', '#7c2d12', 1),
('Regular Client', '#e0e7ff', '#3730a3', 1),
('BTC', '#fef3c7', '#78350f', 1),
('Stablecoin', '#fef9c3', '#713f12', 1);

-- Withdrawal Tag Assignments
-- Associates tags with specific withdrawals
CREATE TABLE `withdrawalTagAssignments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `withdrawalId` bigint(20) UNSIGNED NOT NULL,
  `tagId` int(11) UNSIGNED NOT NULL,
  `assignedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `assignedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukWithdrawalTag` (`withdrawalId`, `tagId`),
  KEY `idxWithdrawalId` (`withdrawalId`),
  KEY `idxTagId` (`tagId`),
  CONSTRAINT `fkWithdrawalTagAssignmentsWithdrawal` FOREIGN KEY (`withdrawalId`) REFERENCES `withdrawals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fkWithdrawalTagAssignmentsTag` FOREIGN KEY (`tagId`) REFERENCES `withdrawalTags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Withdrawal and tag associations';

-- ============================================================
-- CLIENT SAVED WALLETS
-- ============================================================

-- Client Saved Wallet Addresses
-- Allows clients to save their wallet addresses for quick withdrawals
CREATE TABLE `clientSavedWallets` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `userId` int(11) UNSIGNED NOT NULL,
  `walletName` varchar(200) NOT NULL COMMENT 'User-friendly name (e.g., My BTC Wallet)',
  `paymentMethodId` int(11) UNSIGNED NOT NULL,
  `walletAddress` varchar(255) NOT NULL,
  `networkType` varchar(50) DEFAULT NULL COMMENT 'erc20, trc20, bep20, mainnet',
  `isVerified` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether wallet address is verified',
  `verifiedAt` datetime DEFAULT NULL,
  `verificationMethod` varchar(100) DEFAULT NULL COMMENT 'email, sms, small_transaction',
  `isDefault` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Default wallet for this payment method',
  `lastUsedAt` datetime DEFAULT NULL,
  `usageCount` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of times used',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxUserId` (`userId`),
  KEY `idxPaymentMethodId` (`paymentMethodId`),
  KEY `idxIsVerified` (`isVerified`),
  KEY `idxIsDefault` (`isDefault`),
  CONSTRAINT `fkClientSavedWalletsUser` FOREIGN KEY (`userId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fkClientSavedWalletsPaymentMethod` FOREIGN KEY (`paymentMethodId`) REFERENCES `paymentMethods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Client saved wallet addresses';

-- ============================================================
-- TRANSACTION NOTIFICATION SETTINGS
-- ============================================================

-- Transaction Notification Settings
-- Configuration for email notifications on deposits/withdrawals
CREATE TABLE `transactionNotificationSettings` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `settingKey` varchar(100) NOT NULL,
  `settingValue` text DEFAULT NULL,
  `settingType` enum('boolean','string','number','json') NOT NULL DEFAULT 'boolean',
  `category` enum('client','admin','alerts') NOT NULL,
  `displayName` varchar(200) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukSettingKey` (`settingKey`),
  KEY `idxCategory` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Transaction notification configuration';

-- Insert default notification settings
INSERT INTO `transactionNotificationSettings` (`settingKey`, `settingValue`, `settingType`, `category`, `displayName`, `description`) VALUES
('clientEmailNotifications', '1', 'boolean', 'client', 'Client Email Notifications', 'Send email to clients when deposits/withdrawals are processed'),
('adminEmailNotifications', '1', 'boolean', 'admin', 'Admin Email Notifications', 'Send email to admins for all deposit/withdrawal activities'),
('adminNotificationEmails', 'admin@bdx.com, finance@bdx.com', 'string', 'admin', 'Admin Notification Emails', 'Comma-separated list of admin emails'),
('largeDepositAlerts', '1', 'boolean', 'alerts', 'Large Deposit Alerts', 'Send special alerts for deposits above threshold'),
('largeDepositThreshold', '10000', 'number', 'alerts', 'Large Deposit Threshold (USD)', 'Threshold amount for large deposit alerts'),
('largeWithdrawalAlerts', '1', 'boolean', 'alerts', 'Large Withdrawal Alerts', 'Send special alerts for withdrawals above threshold'),
('largeWithdrawalThreshold', '10000', 'number', 'alerts', 'Large Withdrawal Threshold (USD)', 'Threshold amount for large withdrawal alerts');

-- ============================================================
-- SEARCH TAGS FOR FILTERING
-- ============================================================

-- Transaction Search Tags
-- Quick search tags for filtering deposits/withdrawals
CREATE TABLE `transactionSearchTags` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tagName` varchar(100) NOT NULL COMMENT 'Display name',
  `searchKeywords` varchar(500) NOT NULL COMMENT 'Keywords to search',
  `transactionType` enum('deposit','withdrawal','both') NOT NULL DEFAULT 'both',
  `displayOrder` int(11) NOT NULL DEFAULT 0,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `createdBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxTransactionType` (`transactionType`),
  KEY `idxIsActive` (`isActive`),
  KEY `idxDisplayOrder` (`displayOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Search tags for filtering transactions';

-- Insert default search tags
INSERT INTO `transactionSearchTags` (`tagName`, `searchKeywords`, `transactionType`, `displayOrder`) VALUES
('Bitcoin', 'bitcoin,btc', 'both', 1),
('Ethereum', 'ethereum,eth', 'both', 2),
('Pending', 'pending', 'both', 3),
('Large Amount', 'large,vip,high value', 'both', 4),
('Bank Transfer', 'bank,transfer,wire', 'both', 5),
('Crypto Only', 'bitcoin,ethereum,usdt,usdc,crypto', 'both', 6);

-- ============================================================
-- DOCUMENT REQUEST SYSTEM (Need More Documents)
-- ============================================================

-- Withdrawal Document Requests
-- When admin requests additional documents/questions for withdrawal
CREATE TABLE `withdrawalDocumentRequests` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `withdrawalId` bigint(20) UNSIGNED NOT NULL,
  `requestStatus` enum('pending','submitted','approved','rejected') NOT NULL DEFAULT 'pending',
  `requestedBy` bigint(20) UNSIGNED NOT NULL COMMENT 'Admin user ID',
  `requestedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submittedAt` datetime DEFAULT NULL COMMENT 'When client submitted documents',
  `reviewedAt` datetime DEFAULT NULL,
  `reviewedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `adminInstructions` text DEFAULT NULL COMMENT 'Additional instructions from admin',
  `adminNotes` text DEFAULT NULL COMMENT 'Internal admin notes',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxWithdrawalId` (`withdrawalId`),
  KEY `idxRequestStatus` (`requestStatus`),
  KEY `idxRequestedAt` (`requestedAt`),
  CONSTRAINT `fkWithdrawalDocRequestsWithdrawal` FOREIGN KEY (`withdrawalId`) REFERENCES `withdrawals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Document requests for withdrawals';

-- Withdrawal Document Request Items
-- Individual questions or documents requested
CREATE TABLE `withdrawalDocumentRequestItems` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `requestId` bigint(20) UNSIGNED NOT NULL,
  `itemType` enum('question','document') NOT NULL,
  `questionText` text DEFAULT NULL COMMENT 'Question text if itemType=question',
  `questionType` varchar(50) DEFAULT NULL COMMENT 'Text Input, Single Choice, File Upload, etc.',
  `questionOptions` text DEFAULT NULL COMMENT 'JSON array of options for choice questions',
  `questionValidation` varchar(255) DEFAULT NULL COMMENT 'Validation rules',
  `questionHelpText` varchar(500) DEFAULT NULL,
  `documentName` varchar(200) DEFAULT NULL COMMENT 'Document name if itemType=document',
  `documentType` varchar(50) DEFAULT NULL COMMENT 'passport, id-card, bank-statement, etc.',
  `documentDescription` varchar(500) DEFAULT NULL,
  `acceptedFileTypes` varchar(255) DEFAULT NULL COMMENT 'JSON array of accepted document types',
  `isRequired` tinyint(1) NOT NULL DEFAULT 1,
  `displayOrder` int(11) NOT NULL DEFAULT 0,
  `clientResponse` text DEFAULT NULL COMMENT 'Client answer or uploaded file path',
  `respondedAt` datetime DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxRequestId` (`requestId`),
  KEY `idxItemType` (`itemType`),
  CONSTRAINT `fkWithdrawalDocRequestItemsRequest` FOREIGN KEY (`requestId`) REFERENCES `withdrawalDocumentRequests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Document request items';

-- ============================================================
-- TRANSACTION VIEWS FOR REPORTING
-- ============================================================

-- All Transactions View (combines deposits and withdrawals)
DROP VIEW IF EXISTS `vAllTransactions`;
CREATE VIEW `vAllTransactions` AS
SELECT
    d.id,
    d.transactionId,
    d.userId,
    u.firstName,
    u.lastName,
    u.email,
    'deposit' as transactionType,
    d.amount,
    d.netAmount,
    d.status,
    pm.methodName as paymentMethod,
    pm.methodType as paymentType,
    d.requestedAt,
    d.approvedAt,
    d.completedAt,
    d.approvedBy,
    NULL as rejectedAt,
    NULL as rejectedBy,
    d.createdAt,
    d.updatedAt
FROM deposits d
INNER JOIN clientUsers u ON d.userId = u.id
INNER JOIN paymentMethods pm ON d.paymentMethodId = pm.id
UNION ALL
SELECT
    w.id,
    w.transactionId,
    w.userId,
    u.firstName,
    u.lastName,
    u.email,
    'withdrawal' as transactionType,
    w.amount,
    w.netAmount,
    w.status,
    pm.methodName as paymentMethod,
    pm.methodType as paymentType,
    w.requestedAt,
    w.approvedAt,
    w.completedAt,
    w.approvedBy,
    w.rejectedAt,
    w.rejectedBy,
    w.createdAt,
    w.updatedAt
FROM withdrawals w
INNER JOIN clientUsers u ON w.userId = u.id
INNER JOIN paymentMethods pm ON w.paymentMethodId = pm.id;

-- Deposit Summary View (with tags)
DROP VIEW IF EXISTS `vDepositsSummary`;
CREATE VIEW `vDepositsSummary` AS
SELECT
    d.id,
    d.transactionId,
    d.userId,
    CONCAT(u.firstName, ' ', u.lastName) as clientName,
    u.email as clientEmail,
    d.amount,
    d.netAmount,
    d.status,
    pm.methodName as paymentMethod,
    pm.methodType as paymentType,
    d.requestedAt,
    d.approvedAt,
    d.completedAt,
    GROUP_CONCAT(dt.tagName SEPARATOR ', ') as tags,
    d.createdAt
FROM deposits d
INNER JOIN clientUsers u ON d.userId = u.id
INNER JOIN paymentMethods pm ON d.paymentMethodId = pm.id
LEFT JOIN depositTagAssignments dta ON d.id = dta.depositId
LEFT JOIN depositTags dt ON dta.tagId = dt.id
GROUP BY d.id;

-- Withdrawal Summary View (with tags)
DROP VIEW IF EXISTS `vWithdrawalsSummary`;
CREATE VIEW `vWithdrawalsSummary` AS
SELECT
    w.id,
    w.transactionId,
    w.userId,
    CONCAT(u.firstName, ' ', u.lastName) as clientName,
    u.email as clientEmail,
    w.amount,
    w.netAmount,
    w.status,
    pm.methodName as paymentMethod,
    pm.methodType as paymentType,
    w.requestedAt,
    w.approvedAt,
    w.completedAt,
    w.rejectedAt,
    wrr.reasonTitle as rejectionReason,
    GROUP_CONCAT(wt.tagName SEPARATOR ', ') as tags,
    w.createdAt
FROM withdrawals w
INNER JOIN clientUsers u ON w.userId = u.id
INNER JOIN paymentMethods pm ON w.paymentMethodId = pm.id
LEFT JOIN withdrawalRejectionReasons wrr ON w.rejectionReasonId = wrr.id
LEFT JOIN withdrawalTagAssignments wta ON w.id = wta.withdrawalId
LEFT JOIN withdrawalTags wt ON wta.tagId = wt.id
GROUP BY w.id;

-- ============================================================
-- STORED PROCEDURES
-- ============================================================

-- Create Deposit Transaction
DROP PROCEDURE IF EXISTS `spCreateDeposit`;
DELIMITER $$
CREATE PROCEDURE `spCreateDeposit`(
    IN pUserId INT,
    IN pTradingAccountId INT,
    IN pPaymentMethodId INT,
    IN pAmount DECIMAL(15,2),
    IN pAmountCrypto DECIMAL(20,8),
    IN pFromAddress VARCHAR(255),
    IN pIpAddress VARCHAR(45),
    OUT pTransactionId VARCHAR(50),
    OUT pDepositId BIGINT
)
BEGIN
    DECLARE vTransactionId VARCHAR(50);
    DECLARE vNetAmount DECIMAL(15,2);
    DECLARE vPlatformFee DECIMAL(15,2);
    DECLARE vFeePercentage DECIMAL(5,2);
    DECLARE vFeeFixed DECIMAL(15,2);
    DECLARE vChargeToClient TINYINT(1);
    DECLARE vPaymentType VARCHAR(10);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET pTransactionId = NULL;
        SET pDepositId = NULL;
    END;

    START TRANSACTION;

    -- Generate unique transaction ID
    SET vTransactionId = CONCAT('TXN-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(FLOOR(RAND() * 999999), 6, '0'));

    -- Get payment method type
    SELECT methodType INTO vPaymentType FROM paymentMethods WHERE id = pPaymentMethodId;

    -- Get fee configuration
    SELECT feePercentage, feeFixed, chargeToClient
    INTO vFeePercentage, vFeeFixed, vChargeToClient
    FROM transactionFees
    WHERE transactionType = 'deposit'
    AND paymentType = vPaymentType
    AND isActive = 1
    LIMIT 1;

    -- Calculate fees and net amount
    SET vPlatformFee = (pAmount * vFeePercentage / 100) + vFeeFixed;

    IF vChargeToClient = 1 THEN
        SET vNetAmount = pAmount - vPlatformFee;
    ELSE
        SET vNetAmount = pAmount;
    END IF;

    -- Insert deposit record
    INSERT INTO deposits (
        transactionId, userId, tradingAccountId, paymentMethodId,
        amount, amountCrypto, fromAddress, platformFee, netAmount,
        status, ipAddress, requestedAt
    ) VALUES (
        vTransactionId, pUserId, pTradingAccountId, pPaymentMethodId,
        pAmount, pAmountCrypto, pFromAddress, vPlatformFee, vNetAmount,
        'pending', pIpAddress, NOW()
    );

    SET pDepositId = LAST_INSERT_ID();
    SET pTransactionId = vTransactionId;

    -- Insert initial status history
    INSERT INTO depositStatusHistory (depositId, previousStatus, newStatus, description)
    VALUES (pDepositId, NULL, 'pending', 'Deposit initiated by client');

    COMMIT;
END$$
DELIMITER ;

-- Create Withdrawal Transaction
DROP PROCEDURE IF EXISTS `spCreateWithdrawal`;
DELIMITER $$
CREATE PROCEDURE `spCreateWithdrawal`(
    IN pUserId INT,
    IN pTradingAccountId INT,
    IN pPaymentMethodId INT,
    IN pAmount DECIMAL(15,2),
    IN pDestinationAddress VARCHAR(255),
    IN pDestinationLabel VARCHAR(200),
    IN pWithdrawalReason VARCHAR(500),
    IN pIpAddress VARCHAR(45),
    OUT pTransactionId VARCHAR(50),
    OUT pWithdrawalId BIGINT
)
BEGIN
    DECLARE vTransactionId VARCHAR(50);
    DECLARE vNetAmount DECIMAL(15,2);
    DECLARE vPlatformFee DECIMAL(15,2);
    DECLARE vFeePercentage DECIMAL(5,2);
    DECLARE vFeeFixed DECIMAL(15,2);
    DECLARE vChargeToClient TINYINT(1);
    DECLARE vPaymentType VARCHAR(10);
    DECLARE vPrevCount INT;
    DECLARE vPrevAmount DECIMAL(15,2);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET pTransactionId = NULL;
        SET pWithdrawalId = NULL;
    END;

    START TRANSACTION;

    -- Generate unique transaction ID
    SET vTransactionId = CONCAT('TXN-', DATE_FORMAT(NOW(), '%Y%m%d'), '-W', LPAD(FLOOR(RAND() * 999999), 6, '0'));

    -- Get payment method type
    SELECT methodType INTO vPaymentType FROM paymentMethods WHERE id = pPaymentMethodId;

    -- Get fee configuration
    SELECT feePercentage, feeFixed, chargeToClient
    INTO vFeePercentage, vFeeFixed, vChargeToClient
    FROM transactionFees
    WHERE transactionType = 'withdrawal'
    AND paymentType = vPaymentType
    AND isActive = 1
    LIMIT 1;

    -- Calculate fees and net amount
    SET vPlatformFee = (pAmount * vFeePercentage / 100) + vFeeFixed;

    IF vChargeToClient = 1 THEN
        SET vNetAmount = pAmount - vPlatformFee;
    ELSE
        SET vNetAmount = pAmount;
    END IF;

    -- Get previous withdrawals count and amount (30 days)
    SELECT
        COUNT(*), COALESCE(SUM(amount), 0)
    INTO vPrevCount, vPrevAmount
    FROM withdrawals
    WHERE userId = pUserId
    AND status = 'completed'
    AND completedAt >= DATE_SUB(NOW(), INTERVAL 30 DAY);

    -- Insert withdrawal record
    INSERT INTO withdrawals (
        transactionId, userId, tradingAccountId, paymentMethodId,
        amount, destinationAddress, destinationLabel, withdrawalReason,
        platformFee, netAmount, status,
        previousWithdrawalsCount30Days, previousWithdrawalsAmount30Days,
        ipAddress, requestedAt
    ) VALUES (
        vTransactionId, pUserId, pTradingAccountId, pPaymentMethodId,
        pAmount, pDestinationAddress, pDestinationLabel, pWithdrawalReason,
        vPlatformFee, vNetAmount, 'pending',
        vPrevCount, vPrevAmount,
        pIpAddress, NOW()
    );

    SET pWithdrawalId = LAST_INSERT_ID();
    SET pTransactionId = vTransactionId;

    -- Insert initial status history
    INSERT INTO withdrawalStatusHistory (withdrawalId, previousStatus, newStatus, description)
    VALUES (pWithdrawalId, NULL, 'pending', 'Withdrawal request submitted by client');

    COMMIT;
END$$
DELIMITER ;

-- Approve Deposit
DROP PROCEDURE IF EXISTS `spApproveDeposit`;
DELIMITER $$
CREATE PROCEDURE `spApproveDeposit`(
    IN pDepositId BIGINT,
    IN pApprovedBy BIGINT,
    IN pAdminNotes TEXT
)
BEGIN
    DECLARE vCurrentStatus VARCHAR(20);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
    END;

    START TRANSACTION;

    -- Get current status
    SELECT status INTO vCurrentStatus FROM deposits WHERE id = pDepositId;

    -- Update deposit
    UPDATE deposits
    SET status = 'completed',
        approvedAt = NOW(),
        approvedBy = pApprovedBy,
        completedAt = NOW(),
        adminNotes = pAdminNotes
    WHERE id = pDepositId;

    -- Insert status history
    INSERT INTO depositStatusHistory (depositId, previousStatus, newStatus, description, changedBy)
    VALUES (pDepositId, vCurrentStatus, 'completed', 'Deposit approved and completed by admin', pApprovedBy);

    COMMIT;
END$$
DELIMITER ;

-- Approve Withdrawal
DROP PROCEDURE IF EXISTS `spApproveWithdrawal`;
DELIMITER $$
CREATE PROCEDURE `spApproveWithdrawal`(
    IN pWithdrawalId BIGINT,
    IN pApprovedBy BIGINT,
    IN pAdminNotes TEXT
)
BEGIN
    DECLARE vCurrentStatus VARCHAR(20);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
    END;

    START TRANSACTION;

    -- Get current status
    SELECT status INTO vCurrentStatus FROM withdrawals WHERE id = pWithdrawalId;

    -- Update withdrawal to processing
    UPDATE withdrawals
    SET status = 'processing',
        approvedAt = NOW(),
        approvedBy = pApprovedBy,
        adminNotes = pAdminNotes
    WHERE id = pWithdrawalId;

    -- Insert status history
    INSERT INTO withdrawalStatusHistory (withdrawalId, previousStatus, newStatus, description, changedBy)
    VALUES (pWithdrawalId, vCurrentStatus, 'processing', 'Withdrawal approved and processing', pApprovedBy);

    COMMIT;
END$$
DELIMITER ;

-- Reject Withdrawal
DROP PROCEDURE IF EXISTS `spRejectWithdrawal`;
DELIMITER $$
CREATE PROCEDURE `spRejectWithdrawal`(
    IN pWithdrawalId BIGINT,
    IN pRejectedBy BIGINT,
    IN pRejectionReasonId INT,
    IN pRejectionNotes TEXT,
    IN pCustomReason VARCHAR(500)
)
BEGIN
    DECLARE vCurrentStatus VARCHAR(20);
    DECLARE vFinalNotes TEXT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
    END;

    START TRANSACTION;

    -- Get current status
    SELECT status INTO vCurrentStatus FROM withdrawals WHERE id = pWithdrawalId;

    -- If custom reason, use it; otherwise use notes
    IF pCustomReason IS NOT NULL AND pCustomReason != '' THEN
        SET vFinalNotes = pCustomReason;
    ELSE
        SET vFinalNotes = pRejectionNotes;
    END IF;

    -- Update withdrawal
    UPDATE withdrawals
    SET status = 'rejected',
        rejectedAt = NOW(),
        rejectedBy = pRejectedBy,
        rejectionReasonId = pRejectionReasonId,
        rejectionNotes = vFinalNotes
    WHERE id = pWithdrawalId;

    -- Insert status history
    INSERT INTO withdrawalStatusHistory (withdrawalId, previousStatus, newStatus, description, changedBy)
    VALUES (pWithdrawalId, vCurrentStatus, 'rejected', 'Withdrawal rejected by admin', pRejectedBy);

    COMMIT;
END$$
DELIMITER ;

-- Complete Withdrawal
DROP PROCEDURE IF EXISTS `spCompleteWithdrawal`;
DELIMITER $$
CREATE PROCEDURE `spCompleteWithdrawal`(
    IN pWithdrawalId BIGINT,
    IN pTransactionHash VARCHAR(255),
    IN pCompletedBy BIGINT
)
BEGIN
    DECLARE vCurrentStatus VARCHAR(20);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
    END;

    START TRANSACTION;

    -- Get current status
    SELECT status INTO vCurrentStatus FROM withdrawals WHERE id = pWithdrawalId;

    -- Update withdrawal
    UPDATE withdrawals
    SET status = 'completed',
        completedAt = NOW(),
        transactionHash = pTransactionHash
    WHERE id = pWithdrawalId;

    -- Insert status history
    INSERT INTO withdrawalStatusHistory (withdrawalId, previousStatus, newStatus, description, changedBy)
    VALUES (pWithdrawalId, vCurrentStatus, 'completed', 'Withdrawal completed - funds sent to client', pCompletedBy);

    -- Update saved wallet last used time if applicable
    UPDATE clientSavedWallets
    SET lastUsedAt = NOW(), usageCount = usageCount + 1
    WHERE userId = (SELECT userId FROM withdrawals WHERE id = pWithdrawalId)
    AND walletAddress = (SELECT destinationAddress FROM withdrawals WHERE id = pWithdrawalId);

    COMMIT;
END$$
DELIMITER ;

-- ============================================================
-- FUNDING REPORT FUNCTIONS
-- ============================================================

-- Get Transaction Statistics
DROP PROCEDURE IF EXISTS `spGetTransactionStatistics`;
DELIMITER $$
CREATE PROCEDURE `spGetTransactionStatistics`(
    IN pStartDate DATE,
    IN pEndDate DATE,
    OUT pTotalDeposits DECIMAL(15,2),
    OUT pTotalWithdrawals DECIMAL(15,2),
    OUT pNetFlow DECIMAL(15,2),
    OUT pDepositCount INT,
    OUT pWithdrawalCount INT
)
BEGIN
    -- Calculate total deposits
    SELECT COALESCE(SUM(netAmount), 0), COUNT(*)
    INTO pTotalDeposits, pDepositCount
    FROM deposits
    WHERE status = 'completed'
    AND DATE(completedAt) BETWEEN pStartDate AND pEndDate;

    -- Calculate total withdrawals
    SELECT COALESCE(SUM(netAmount), 0), COUNT(*)
    INTO pTotalWithdrawals, pWithdrawalCount
    FROM withdrawals
    WHERE status = 'completed'
    AND DATE(completedAt) BETWEEN pStartDate AND pEndDate;

    -- Calculate net flow
    SET pNetFlow = pTotalDeposits - pTotalWithdrawals;
END$$
DELIMITER ;

-- ============================================================
-- TRIGGERS
-- ============================================================

-- Auto-update deposit confirmations
DROP TRIGGER IF EXISTS `trgDepositsAfterUpdate`;
DELIMITER $$
CREATE TRIGGER `trgDepositsAfterUpdate`
AFTER UPDATE ON `deposits`
FOR EACH ROW
BEGIN
    -- If status changed, record it
    IF NEW.status != OLD.status THEN
        INSERT INTO depositStatusHistory (depositId, previousStatus, newStatus, description)
        VALUES (NEW.id, OLD.status, NEW.status, CONCAT('Status changed from ', OLD.status, ' to ', NEW.status));
    END IF;

    -- If confirmations reached required, update status to processing
    IF NEW.confirmations >= NEW.requiredConfirmations
       AND OLD.confirmations < OLD.requiredConfirmations
       AND NEW.status = 'pending' THEN
        UPDATE deposits
        SET status = 'processing'
        WHERE id = NEW.id;
    END IF;
END$$
DELIMITER ;

-- Auto-update withdrawal status history
DROP TRIGGER IF EXISTS `trgWithdrawalsAfterUpdate`;
DELIMITER $$
CREATE TRIGGER `trgWithdrawalsAfterUpdate`
AFTER UPDATE ON `withdrawals`
FOR EACH ROW
BEGIN
    -- If status changed, record it
    IF NEW.status != OLD.status THEN
        INSERT INTO withdrawalStatusHistory (withdrawalId, previousStatus, newStatus, description)
        VALUES (NEW.id, OLD.status, NEW.status, CONCAT('Status changed from ', OLD.status, ' to ', NEW.status));
    END IF;
END$$
DELIMITER ;

-- ============================================================
-- INDEXES FOR PERFORMANCE OPTIMIZATION
-- ============================================================

-- Additional composite indexes for common queries
ALTER TABLE deposits
  ADD INDEX `idxUserStatus` (`userId`, `status`),
  ADD INDEX `idxStatusRequested` (`status`, `requestedAt`),
  ADD INDEX `idxPaymentMethodStatus` (`paymentMethodId`, `status`);

ALTER TABLE withdrawals
  ADD INDEX `idxUserStatus` (`userId`, `status`),
  ADD INDEX `idxStatusRequested` (`status`, `requestedAt`),
  ADD INDEX `idxPaymentMethodStatus` (`paymentMethodId`, `status`);

-- ============================================================
-- CLIENT INTERFACE TEXT CONFIGURATION
-- ============================================================

-- Client Interface Text Settings
-- Stores customizable text displayed on client transaction pages
CREATE TABLE `clientInterfaceTexts` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `textKey` varchar(100) NOT NULL COMMENT 'Unique key: deposit.pageTitle, withdrawal.successMessage, etc.',
  `textCategory` enum('deposit','withdrawal','general') NOT NULL DEFAULT 'general',
  `textValue` text NOT NULL COMMENT 'The actual text displayed to clients',
  `defaultValue` text DEFAULT NULL COMMENT 'Default fallback value',
  `description` varchar(500) DEFAULT NULL COMMENT 'Description of this text field',
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID who last updated',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukTextKey` (`textKey`),
  KEY `idxTextCategory` (`textCategory`),
  KEY `idxIsActive` (`isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Client interface text customization';

-- Insert default client interface texts for deposits
INSERT INTO `clientInterfaceTexts` (`textKey`, `textCategory`, `textValue`, `defaultValue`, `description`) VALUES
('deposit.pageTitle', 'deposit', 'Deposit Funds', 'Deposit Funds', 'Main title shown on deposit page'),
('deposit.pageDescription', 'deposit', 'Add funds to your trading account quickly and securely', 'Add funds to your trading account quickly and securely', 'Description text under deposit page title'),
('deposit.minimumNotice', 'deposit', 'Minimum deposit: $${amount}', 'Minimum deposit: $${amount}', 'Notice about minimum deposit amount (use ${amount} for dynamic value)'),
('deposit.processingNotice', 'deposit', 'Processing Time: Cryptocurrency deposits are typically confirmed within 15-30 minutes.', 'Processing Time: Cryptocurrency deposits are typically confirmed within 15-30 minutes.', 'Information about deposit processing time'),
('deposit.successMessage', 'deposit', '✓ Deposit request created successfully! Your deposit will be credited after confirmation.', '✓ Deposit request created successfully! Your deposit will be credited after confirmation.', 'Success message shown after creating deposit'),
('deposit.tips', 'deposit', '[{"id":1,"icon":"fa-bolt","title":"Instant Deposits","description":"Use cryptocurrency for instant deposits"},{"id":2,"icon":"fa-shield-alt","title":"Secure Transactions","description":"All transactions are encrypted"}]', '[{"id":1,"icon":"fa-bolt","title":"Instant Deposits","description":"Use cryptocurrency for instant deposits"},{"id":2,"icon":"fa-shield-alt","title":"Secure Transactions","description":"All transactions are encrypted"}]', 'Dynamic list of deposit tips (JSON array)'),
('withdrawal.pageTitle', 'withdrawal', 'Withdraw Funds', 'Withdraw Funds', 'Main title shown on withdrawal page'),
('withdrawal.pageDescription', 'withdrawal', 'Withdraw your profits securely to your preferred payment method', 'Withdraw your profits securely to your preferred payment method', 'Description text under withdrawal page title'),
('withdrawal.minimumNotice', 'withdrawal', 'Minimum withdrawal: $${amount}', 'Minimum withdrawal: $${amount}', 'Notice about minimum withdrawal amount (use ${amount} for dynamic value)'),
('withdrawal.reviewWarning', 'withdrawal', 'Important: All withdrawal requests are reviewed for security. Processing time: 1-3 business days.', 'Important: All withdrawal requests are reviewed for security. Processing time: 1-3 business days.', 'Warning about withdrawal review process'),
('withdrawal.successMessage', 'withdrawal', '✓ Withdrawal request submitted successfully! You will receive an email notification once processed.', '✓ Withdrawal request submitted successfully! You will receive an email notification once processed.', 'Success message shown after submitting withdrawal'),
('withdrawal.informations', 'withdrawal', '[{"id":1,"icon":"fa-clock","title":"Processing Time","description":"Crypto: 1-2 hours | Bank: 2-3 days"},{"id":2,"icon":"fa-percentage","title":"Fees","description":"Crypto: Network fee | Bank: Free"}]', '[{"id":1,"icon":"fa-clock","title":"Processing Time","description":"Crypto: 1-2 hours | Bank: 2-3 days"},{"id":2,"icon":"fa-percentage","title":"Fees","description":"Crypto: Network fee | Bank: Free"}]', 'Dynamic list of withdrawal information items (JSON array)');

-- ============================================================
-- COUNTRY REFERENCE DATA
-- ============================================================

-- Countries List
-- Reference data for country selection in auto-approval rules
CREATE TABLE `countries` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(3) NOT NULL COMMENT 'ISO 3166-1 alpha-3 country code',
  `code2` varchar(2) NOT NULL COMMENT 'ISO 3166-1 alpha-2 country code',
  `name` varchar(100) NOT NULL COMMENT 'Country name in English',
  `region` varchar(50) DEFAULT NULL COMMENT 'Geographic region',
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukCountryCode` (`code`),
  UNIQUE KEY `ukCountryCode2` (`code2`),
  KEY `idxRegion` (`region`),
  KEY `idxIsActive` (`isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Countries reference data';

-- Insert common countries (sample - extend as needed)
INSERT INTO `countries` (`code`, `code2`, `name`, `region`) VALUES
('USA', 'US', 'United States', 'North America'),
('GBR', 'GB', 'United Kingdom', 'Europe'),
('CAN', 'CA', 'Canada', 'North America'),
('AUS', 'AU', 'Australia', 'Oceania'),
('DEU', 'DE', 'Germany', 'Europe'),
('FRA', 'FR', 'France', 'Europe'),
('JPN', 'JP', 'Japan', 'Asia'),
('CHN', 'CN', 'China', 'Asia'),
('IND', 'IN', 'India', 'Asia'),
('SGP', 'SG', 'Singapore', 'Asia'),
('HKG', 'HK', 'Hong Kong', 'Asia'),
('ARE', 'AE', 'United Arab Emirates', 'Middle East'),
('CHE', 'CH', 'Switzerland', 'Europe'),
('NLD', 'NL', 'Netherlands', 'Europe'),
('SWE', 'SE', 'Sweden', 'Europe'),
('NOR', 'NO', 'Norway', 'Europe'),
('DNK', 'DK', 'Denmark', 'Europe'),
('ESP', 'ES', 'Spain', 'Europe'),
('ITA', 'IT', 'Italy', 'Europe'),
('BRA', 'BR', 'Brazil', 'South America'),
('MEX', 'MX', 'Mexico', 'North America'),
('KOR', 'KR', 'South Korea', 'Asia'),
('THA', 'TH', 'Thailand', 'Asia'),
('MYS', 'MY', 'Malaysia', 'Asia'),
('IDN', 'ID', 'Indonesia', 'Asia'),
('PHL', 'PH', 'Philippines', 'Asia'),
('VNM', 'VN', 'Vietnam', 'Asia'),
('NZL', 'NZ', 'New Zealand', 'Oceania'),
('ZAF', 'ZA', 'South Africa', 'Africa'),
('RUS', 'RU', 'Russia', 'Europe');

-- ============================================================
-- AUTO-APPROVAL RULES CONFIGURATION
-- ============================================================

-- Auto Approval Rules
-- Defines rules for automatic approval of deposits and withdrawals
CREATE TABLE `autoApprovalRules` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ruleType` enum('deposit','withdrawal') NOT NULL COMMENT 'Type of transaction this rule applies to',
  `ruleName` varchar(100) NOT NULL COMMENT 'Descriptive name for the rule',
  `isEnabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether this rule is active',
  `priority` int(11) NOT NULL DEFAULT 0 COMMENT 'Rule priority (higher = checked first)',

  -- Amount Criteria
  `minAmount` decimal(15,2) DEFAULT NULL COMMENT 'Minimum transaction amount (USD)',
  `maxAmount` decimal(15,2) DEFAULT NULL COMMENT 'Maximum transaction amount (USD)',

  -- Geographic Criteria
  `allowedCountries` text DEFAULT NULL COMMENT 'JSON array of allowed country codes, or "ALL" for all countries',
  `excludedCountries` text DEFAULT NULL COMMENT 'JSON array of excluded country codes',

  -- Client Criteria
  `requiredClientTags` text DEFAULT NULL COMMENT 'Comma-separated tags - client must have ALL these tags',
  `excludedClientTags` text DEFAULT NULL COMMENT 'Comma-separated tags - client with ANY of these tags will be excluded',
  `requireKycVerified` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Require client to have verified KYC status',
  `minAccountAge` int(11) DEFAULT NULL COMMENT 'Minimum account age in days',
  `minPreviousTransactions` int(11) DEFAULT NULL COMMENT 'Minimum number of successful previous transactions',

  -- Withdrawal-Specific Criteria
  `checkSavedWallet` tinyint(1) DEFAULT 0 COMMENT 'For withdrawals: only approve if using saved/verified wallet',
  `requireMatchingDepositMethod` tinyint(1) DEFAULT 0 COMMENT 'For withdrawals: require previous deposit using same method',

  -- Additional Criteria
  `allowedPaymentMethods` text DEFAULT NULL COMMENT 'JSON array of allowed payment method IDs, or NULL for all',
  `timeRestrictions` text DEFAULT NULL COMMENT 'JSON object for time-based restrictions (business hours, etc.)',

  -- Metadata
  `description` text DEFAULT NULL COMMENT 'Detailed description of rule purpose',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID who last updated',
  `lastAppliedAt` datetime DEFAULT NULL COMMENT 'Last time this rule auto-approved a transaction',
  `totalApprovals` int(11) NOT NULL DEFAULT 0 COMMENT 'Total number of transactions auto-approved by this rule',

  PRIMARY KEY (`id`),
  KEY `idxRuleType` (`ruleType`),
  KEY `idxIsEnabled` (`isEnabled`),
  KEY `idxPriority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auto-approval rules for deposits and withdrawals';

-- Insert default auto-approval rules (disabled by default for security)
INSERT INTO `autoApprovalRules` (`ruleType`, `ruleName`, `isEnabled`, `priority`, `minAmount`, `maxAmount`, `allowedCountries`, `requiredClientTags`, `excludedClientTags`, `requireKycVerified`, `description`) VALUES
('deposit', 'Default Deposit Auto-Approval', 0, 100, 0.00, 10000.00, '["ALL"]', '', 'Suspicious,Blocked,Under Review', 1, 'Default rule for auto-approving deposits up to $10,000 for verified clients without suspicious tags'),
('withdrawal', 'Default Withdrawal Auto-Approval', 0, 100, 0.00, 5000.00, '["ALL"]', '', 'Suspicious,Blocked,Under Review', 1, 'Default rule for auto-approving withdrawals up to $5,000 for verified clients without suspicious tags');

-- ============================================================
-- AUTO-APPROVAL AUDIT LOG
-- ============================================================

-- Auto Approval Log
-- Tracks all auto-approval decisions for audit purposes
CREATE TABLE `autoApprovalLog` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `transactionType` enum('deposit','withdrawal') NOT NULL,
  `transactionId` bigint(20) UNSIGNED NOT NULL COMMENT 'ID from deposits or withdrawals table',
  `transactionRefId` varchar(50) DEFAULT NULL COMMENT 'Transaction reference ID (TXN-YYYYMMDD-XXXXX)',
  `userId` bigint(20) UNSIGNED NOT NULL COMMENT 'Client user ID',
  `ruleId` int(11) UNSIGNED DEFAULT NULL COMMENT 'Auto-approval rule that was applied (NULL if rejected)',
  `wasAutoApproved` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether transaction was auto-approved',
  `checkResults` text DEFAULT NULL COMMENT 'JSON object with detailed check results',
  `rejectionReason` text DEFAULT NULL COMMENT 'Reason why auto-approval was not granted',
  `amount` decimal(15,2) NOT NULL COMMENT 'Transaction amount',
  `clientCountry` varchar(3) DEFAULT NULL COMMENT 'Client country code at time of transaction',
  `clientTags` text DEFAULT NULL COMMENT 'Client tags at time of transaction',
  `kycStatus` varchar(50) DEFAULT NULL COMMENT 'Client KYC status at time of transaction',
  `checkedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ipAddress` varchar(45) DEFAULT NULL COMMENT 'IP address of transaction request',
  PRIMARY KEY (`id`),
  KEY `idxTransactionType` (`transactionType`, `transactionId`),
  KEY `idxUserId` (`userId`),
  KEY `idxRuleId` (`ruleId`),
  KEY `idxWasAutoApproved` (`wasAutoApproved`),
  KEY `idxCheckedAt` (`checkedAt`),
  CONSTRAINT `fkAutoApprovalLogRule` FOREIGN KEY (`ruleId`) REFERENCES `autoApprovalRules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auto-approval decision audit log';

-- ============================================================
-- TRANSACTION SECURITY SETTINGS
-- ============================================================

-- Transaction Security Settings
-- Stores security configurations for transactions
CREATE TABLE `transactionSecuritySettings` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `settingKey` varchar(100) NOT NULL COMMENT 'Unique setting key',
  `settingValue` text DEFAULT NULL COMMENT 'Setting value',
  `settingType` enum('boolean','string','number','json') NOT NULL DEFAULT 'boolean',
  `description` varchar(500) DEFAULT NULL COMMENT 'Setting description',
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID who last updated',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukSettingKey` (`settingKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Transaction security settings';

-- Insert default security settings
INSERT INTO `transactionSecuritySettings` (`settingKey`, `settingValue`, `settingType`, `description`) VALUES
('salesManagerNotifications', '0', 'boolean', 'Enable Sales Managers to receive transaction notifications for their clients'),
('withdrawalOtpRequired', '0', 'boolean', 'Require OTP verification for withdrawal requests'),
('otpValidityMinutes', '10', 'number', 'OTP code validity period in minutes (1-60)'),
('requireVerifiedWalletOnly', '1', 'boolean', 'Only allow withdrawals to verified/saved wallet addresses'),
('requireWithdrawalVerification', '0', 'boolean', 'Require clients to verify their withdrawal accounts before first withdrawal'),
('verificationMaxFileSize', '5', 'number', 'Maximum file size for verification documents in MB (1-20)'),
('autoRejectUnverified', '0', 'boolean', 'Automatically reject withdrawal requests from unverified accounts');

-- ============================================================
-- WITHDRAWAL OTP VERIFICATIONS
-- ============================================================

-- Withdrawal OTP Verifications
-- Stores OTP verification records for withdrawal requests
CREATE TABLE `withdrawalOtpVerifications` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `userId` int(11) UNSIGNED NOT NULL COMMENT 'Client user ID',
  `otpCode` varchar(10) NOT NULL COMMENT 'The OTP code',
  `otpHash` varchar(255) NOT NULL COMMENT 'Hashed OTP code for security',
  `isVerified` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether OTP has been verified',
  `verifiedAt` datetime DEFAULT NULL COMMENT 'When OTP was verified',
  `expiresAt` datetime NOT NULL COMMENT 'When OTP expires',
  `ipAddress` varchar(45) DEFAULT NULL COMMENT 'IP address when OTP was requested',
  `userAgent` text DEFAULT NULL COMMENT 'User agent when OTP was requested',
  `attempts` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of verification attempts',
  `maxAttempts` int(11) NOT NULL DEFAULT 5 COMMENT 'Maximum allowed attempts',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxUserId` (`userId`),
  KEY `idxOtpHash` (`otpHash`),
  KEY `idxIsVerified` (`isVerified`),
  KEY `idxExpiresAt` (`expiresAt`),
  CONSTRAINT `fkWithdrawalOtpUser` FOREIGN KEY (`userId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Withdrawal OTP verification records';

-- ============================================================
-- WITHDRAWAL ACCOUNT VERIFICATION SYSTEM
-- ============================================================

-- Account Verifications
-- Stores verification requests for bank accounts and crypto wallets
CREATE TABLE `accountVerifications` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `userId` int(11) UNSIGNED NOT NULL COMMENT 'Client user ID',
  `paymentMethodId` int(11) UNSIGNED NOT NULL COMMENT 'Payment method ID',
  `accountType` enum('bank','crypto') NOT NULL COMMENT 'Account type: bank or cryptocurrency',
  `accountName` varchar(255) NOT NULL COMMENT 'User-friendly account name',

  -- Bank specific fields
  `bankName` varchar(255) DEFAULT NULL COMMENT 'Bank name',
  `accountNumber` varchar(255) DEFAULT NULL COMMENT 'Bank account number (encrypted)',
  `accountHolderName` varchar(255) DEFAULT NULL COMMENT 'Account holder name',
  `swiftCode` varchar(50) DEFAULT NULL COMMENT 'SWIFT/BIC code',

  -- Crypto specific fields
  `walletAddress` varchar(500) DEFAULT NULL COMMENT 'Cryptocurrency wallet address',
  `walletNetwork` varchar(50) DEFAULT NULL COMMENT 'Network type (ERC20, TRC20, etc.)',

  -- Verification status
  `verificationStatus` enum('pending','approved','rejected') DEFAULT 'pending' COMMENT 'Verification status',

  -- Notes and review
  `clientNotes` text DEFAULT NULL COMMENT 'Notes from client',
  `reviewNotes` text DEFAULT NULL COMMENT 'Admin review notes',
  `rejectionReason` text DEFAULT NULL COMMENT 'Reason for rejection',

  -- Review information
  `reviewedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID who reviewed',
  `reviewedAt` datetime DEFAULT NULL COMMENT 'When reviewed',

  -- Timestamps
  `submittedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When submitted',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When created',
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'When updated',

  PRIMARY KEY (`id`),
  KEY `idxUserId` (`userId`),
  KEY `idxPaymentMethodId` (`paymentMethodId`),
  KEY `idxVerificationStatus` (`verificationStatus`),
  KEY `idxUserIdStatus` (`userId`, `verificationStatus`),
  KEY `idxStatusSubmitted` (`verificationStatus`, `submittedAt` DESC),
  CONSTRAINT `fkAccountVerificationsUser` FOREIGN KEY (`userId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fkAccountVerificationsPaymentMethod` FOREIGN KEY (`paymentMethodId`) REFERENCES `paymentMethods` (`id`) ON DELETE CASCADE,
  CHECK (
    (accountType = 'bank' AND bankName IS NOT NULL AND accountNumber IS NOT NULL AND accountHolderName IS NOT NULL) OR
    (accountType = 'crypto' AND walletAddress IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Account verification requests for withdrawals';

-- Account Verification Files
-- Stores uploaded verification documents
CREATE TABLE `accountVerificationFiles` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `verificationId` bigint(20) UNSIGNED NOT NULL COMMENT 'Verification request ID',
  `fileName` varchar(255) NOT NULL COMMENT 'Original file name',
  `filePath` varchar(500) NOT NULL COMMENT 'File storage path',
  `fileType` varchar(100) NOT NULL COMMENT 'MIME type',
  `fileSize` int(11) NOT NULL COMMENT 'File size in bytes',
  `fileCategory` enum('bank_statement','wallet_screenshot','other') DEFAULT 'other' COMMENT 'File category',
  `uploadedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Upload timestamp',

  PRIMARY KEY (`id`),
  KEY `idxVerificationId` (`verificationId`),
  CONSTRAINT `fkAccountVerificationFilesVerification` FOREIGN KEY (`verificationId`) REFERENCES `accountVerifications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Verification document files';

-- Account Verification Activity Log
-- Tracks all verification-related activities
CREATE TABLE `accountVerificationLogs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `verificationId` bigint(20) UNSIGNED NOT NULL COMMENT 'Verification request ID',
  `actionType` enum('created','submitted','approved','rejected','updated','file_uploaded') NOT NULL COMMENT 'Action type',
  `actionBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'User ID who performed action (client or admin)',
  `actionDetails` text DEFAULT NULL COMMENT 'Action details in JSON format',
  `ipAddress` varchar(45) DEFAULT NULL COMMENT 'IP address',
  `userAgent` text DEFAULT NULL COMMENT 'User agent string',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When action occurred',

  PRIMARY KEY (`id`),
  KEY `idxVerificationId` (`verificationId`),
  KEY `idxActionType` (`actionType`),
  KEY `idxCreatedAt` (`createdAt` DESC),
  CONSTRAINT `fkAccountVerificationLogsVerification` FOREIGN KEY (`verificationId`) REFERENCES `accountVerifications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Account verification activity audit log';

-- Add verified account reference to withdrawals table
ALTER TABLE `withdrawals`
ADD COLUMN `verifiedAccountId` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Reference to verified account used' AFTER `destinationLabel`,
ADD KEY `idxVerifiedAccountId` (`verifiedAccountId`),
ADD CONSTRAINT `fkWithdrawalsVerifiedAccount` FOREIGN KEY (`verifiedAccountId`) REFERENCES `accountVerifications` (`id`) ON DELETE SET NULL;

-- ============================================================
-- TRIGGERS FOR ACCOUNT VERIFICATION
-- ============================================================

-- Trigger to log verification status changes
DROP TRIGGER IF EXISTS `trgAccountVerificationsAfterUpdate`;
DELIMITER $$
CREATE TRIGGER `trgAccountVerificationsAfterUpdate`
AFTER UPDATE ON `accountVerifications`
FOR EACH ROW
BEGIN
  -- Log status changes
  IF NEW.verificationStatus != OLD.verificationStatus THEN
    INSERT INTO accountVerificationLogs (
      verificationId,
      actionType,
      actionBy,
      actionDetails
    ) VALUES (
      NEW.id,
      CASE NEW.verificationStatus
        WHEN 'approved' THEN 'approved'
        WHEN 'rejected' THEN 'rejected'
        ELSE 'updated'
      END,
      NEW.reviewedBy,
      JSON_OBJECT(
        'oldStatus', OLD.verificationStatus,
        'newStatus', NEW.verificationStatus,
        'reviewNotes', NEW.reviewNotes,
        'rejectionReason', NEW.rejectionReason
      )
    );
  END IF;
END$$
DELIMITER ;

COMMIT;

-- ============================================================
-- SAMPLE DATA FOR TESTING
-- ============================================================

-- Note: Sample data should match the examples in HTML pages
-- Uncomment below to insert sample deposits and withdrawals

/*
-- Sample Deposits (matching DepositManagement.html examples)
INSERT INTO `deposits` (`transactionId`, `userId`, `paymentMethodId`, `amount`, `amountCrypto`, `netAmount`, `status`, `requestedAt`) VALUES
('TXN-20241112-001', 1, 1, 5000.00, 0.13245000, 4997.50, 'pending', '2024-11-12 10:45:00'),
('TXN-20241112-002', 2, 6, 10500.00, NULL, 10500.00, 'processing', '2024-11-12 09:30:00'),
('TXN-20241112-003', 3, 2, 25000.00, 7.89234567, 25000.00, 'completed', '2024-11-12 08:15:00');

-- Sample Withdrawals (matching WithdrawManagement.html examples)
INSERT INTO `withdrawals` (`transactionId`, `userId`, `paymentMethodId`, `amount`, `netAmount`, `status`, `requestedAt`, `withdrawalReason`) VALUES
('TXN-20241112-W001', 1, 1, 8500.00, 8496.50, 'pending', '2024-11-12 10:45:00', 'Profit withdrawal'),
('TXN-20241112-W002', 2, 5, 12800.00, 12800.00, 'processing', '2024-11-12 09:30:00', 'Funds transfer'),
('TXN-20241112-W003', 3, 2, 20000.00, 20000.00, 'completed', '2024-11-12 08:15:00', 'Account closure');
*/

-- ============================================================
-- END OF FUNDING MANAGEMENT DATABASE SCHEMA
-- ============================================================
--
-- Usage Notes:
-- 1. This schema works with existing clientUsers and tradingAccounts tables
-- 2. All amounts are stored in USD with crypto amounts as supplementary data
-- 3. Transaction IDs follow format: TXN-YYYYMMDD-XXXXX (deposits) or TXN-YYYYMMDD-WXXXXX (withdrawals)
-- 4. Status tracking uses dedicated history tables for complete audit trails
-- 5. Fees are calculated based on payment type (crypto vs fiat)
-- 6. Tags system allows flexible categorization of transactions
-- 7. Supports both crypto addresses and bank account details
-- 8. AlchemyPay gateway configuration is encrypted and secure
-- 9. Transaction limits and fees are configurable per type
-- 10. Saved wallets allow clients to reuse verified addresses
-- 11. Account verification system requires clients to verify withdrawal destinations before first withdrawal
-- 12. Supports bank account verification (with bank statement) and crypto wallet verification (with screenshot)
-- 13. All verification files are stored securely with audit logging
--
-- Related Configuration:
-- - Ensure clientUsers table exists (from client_portal_database.sql)
-- - Ensure tradingAccounts table exists (from trading_accounts_database.sql)
-- - Ensure adminUsers table exists (from admin_system_database.sql)
--
-- Security Considerations:
-- - API keys and secrets should be encrypted before storage
-- - Wallet addresses should be validated before saving
-- - Bank account numbers should be encrypted before storage
-- - IP addresses are logged for security auditing
-- - Admin actions are tracked with user IDs
-- - Verification files should be stored in secure directory with restricted access
-- - File uploads should be validated for type and size
-- - Sensitive account information should be masked/encrypted in client responses
--
-- Account Verification Features:
-- - Clients must verify bank accounts or crypto wallets before first withdrawal
-- - Supports uploading bank statements (PDF/images) for bank verification
-- - Supports uploading wallet screenshots (showing address + name) for crypto verification
-- - Admin can approve or reject verification requests with notes
-- - All verification activities are logged for audit trail
-- - Withdrawals can be linked to verified accounts for compliance tracking
-- - Configurable settings: require verification, auto-reject unverified, file size limits
--
-- ============================================================
