-- ============================================================
-- Migration: Insert Sample Data for Funding Management System
-- ============================================================
-- Created: 2024-11-13
-- Description: Inserts comprehensive sample data for deposits, withdrawals,
--              tags, wallets, and related tables for testing purposes
-- Related Tables: deposits, withdrawals, depositTags, withdrawalTags,
--                 clientSavedWallets, depositStatusHistory, withdrawalStatusHistory
-- ============================================================
-- IMPORTANT PREREQUISITES:
-- 1. This script will create test clientUsers if they don't exist (IDs 1-10)
-- 2. tradingAccountId is set to NULL to avoid foreign key constraints
-- 3. If you need to link to specific trading accounts, update manually after insert
-- 4. Make sure paymentMethods table is populated (from funding_management_database.sql)
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- ============================================================
-- CREATE TEST CLIENT USERS (IF NOT EXISTS)
-- ============================================================

-- Check and create test users for sample data
-- These are dummy users for testing purposes only
-- Using minimal required fields to ensure compatibility

INSERT IGNORE INTO `clientUsers` (`id`, `firstName`, `lastName`, `email`) VALUES
(1, 'John', 'Doe', 'john.doe.test@example.com'),
(2, 'Jane', 'Smith', 'jane.smith.test@example.com'),
(3, 'Michael', 'Johnson', 'michael.j.test@example.com'),
(4, 'Emily', 'Brown', 'emily.brown.test@example.com'),
(5, 'David', 'Wilson', 'david.wilson.test@example.com'),
(6, 'Sarah', 'Martinez', 'sarah.m.test@example.com'),
(7, 'Robert', 'Taylor', 'robert.taylor.test@example.com'),
(8, 'Lisa', 'Anderson', 'lisa.anderson.test@example.com'),
(9, 'James', 'Thomas', 'james.thomas.test@example.com'),
(10, 'Patricia', 'Garcia', 'patricia.garcia.test@example.com');

-- Verification: Check if test users were created
SELECT CONCAT('Created/Verified ', COUNT(*), ' test client users (IDs 1-10)') AS status FROM clientUsers WHERE id BETWEEN 1 AND 10;

-- ============================================================
-- SAMPLE DEPOSITS
-- ============================================================

-- Insert sample deposit transactions with various statuses and payment methods
INSERT INTO `deposits` (`transactionId`, `userId`, `tradingAccountId`, `paymentMethodId`, `amount`, `amountCrypto`, `cryptoNetwork`, `fromAddress`, `toAddress`, `networkFee`, `platformFee`, `netAmount`, `exchangeRate`, `status`, `confirmations`, `requiredConfirmations`, `transactionHash`, `requestedAt`, `approvedAt`, `approvedBy`, `completedAt`, `clientNotes`, `adminNotes`, `ipAddress`) VALUES

-- Completed deposits
('TXN-20241113-001234', 1, NULL, 1, 5000.00, 0.13245678, 'mainnet', '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa', 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh', 0.00, 0.00, 5000.00, 37749.85, 'completed', 6, 3, '5f3e8c9a7b2d1e4f6a8c9b5d3e7f2a4b6c8d9e1f3a5b7c9d2e4f6a8b1c3d5e7f9', '2024-11-10 10:30:00', '2024-11-10 11:00:00', 1, '2024-11-10 11:30:00', 'Initial deposit for trading', 'Verified and approved', '192.168.1.100'),

('TXN-20241113-001235', 2, NULL, 2, 12500.00, 3.89234567, 'erc20', '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0', '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0', 0.00, 0.00, 12500.00, 3214.56, 'completed', 15, 12, '0x8f3e9c1a4b7d2e5f8a9c6b3d1e4f7a2b5c8d9e2f4a6b8c1d3e5f7a9b2c4d6e8f', '2024-11-09 14:20:00', '2024-11-09 15:00:00', 1, '2024-11-09 15:45:00', 'Large deposit', 'VIP client - expedited processing', '192.168.1.101'),

('TXN-20241113-001236', 3, NULL, 3, 8000.00, 8000.00000000, 'erc20', '0x8f4e7c2a5b9d3e6f1a8c5b2d4e7f9a3b6c8d1e4f2a5b7c9d3e6f8a1b4c6d8e', '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0', 0.00, 0.00, 8000.00, 1.00, 'completed', 20, 12, '0x3a6f8b1c4d7e9f2a5b8c1d4e7f9a2b5c8d1e4f7a9b2c5d8e1f4a7b9c2d5e8f1a', '2024-11-08 09:15:00', '2024-11-08 10:00:00', 1, '2024-11-08 10:30:00', 'USDT deposit via ERC20', 'Fast settlement', '192.168.1.102'),

('TXN-20241113-001237', 4, NULL, 6, 15000.00, NULL, NULL, NULL, NULL, 0.00, 375.00, 14625.00, NULL, 'completed', NULL, NULL, 'ACH-2024110812345678', '2024-11-07 16:45:00', '2024-11-07 17:00:00', 1, '2024-11-07 17:00:00', 'AlchemyPay deposit', 'Instant payment gateway', '192.168.1.103'),

('TXN-20241113-001238', 5, NULL, 5, 25000.00, NULL, NULL, 'GB82WEST12345698765432', NULL, 0.00, 625.00, 24375.00, NULL, 'completed', NULL, NULL, 'WIRE-2024110600123456', '2024-11-06 08:00:00', '2024-11-06 12:00:00', 2, '2024-11-06 16:00:00', 'Bank wire transfer', 'Large amount verified', '192.168.1.104'),

-- Processing deposits
('TXN-20241113-001239', 6, NULL, 1, 3500.00, 0.09123456, 'mainnet', '1BvBMSEYstWetqTFn5Au4m4GFg7xJaNVN2', 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh', 0.00, 0.00, 3500.00, 38356.23, 'processing', 4, 3, '2b4d6e8f1a3c5d7e9f2a4b6c8d1e3f5a7b9c2d4e6f8a1b3c5d7e9f2a4b6c8d1e', '2024-11-13 08:30:00', '2024-11-13 09:00:00', 1, NULL, 'Regular deposit', 'Confirmations in progress', '192.168.1.105'),

('TXN-20241113-001240', 7, NULL, 2, 7800.00, 2.45678901, 'erc20', '0x1a3c5d7e9f2b4c6d8e1f3a5b7c9d2e4f6a8b1c3d5e7f9a2b4c6d8e1f3a5b7c', '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0', 0.00, 0.00, 7800.00, 3174.89, 'processing', 8, 12, '0x5c7e9f2a4b6d8e1f3a5c7d9e2f4a6b8c1d3e5f7a9b2c4d6e8f1a3b5c7d9e2f4a', '2024-11-13 10:15:00', '2024-11-13 10:45:00', 2, NULL, 'ETH deposit', 'Waiting for confirmations', '192.168.1.106'),

-- Pending deposits
('TXN-20241113-001241', 8, NULL, 1, 2000.00, 0.05234567, 'mainnet', '3J98t1WpEZ73CNmYviecrnyiWrnqRhWNLy', 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh', 0.00, 0.00, 2000.00, 38213.45, 'pending', 1, 3, '8e1f3a5b7c9d2e4f6a8b1c3d5e7f9a2b4c6d8e1f3a5b7c9d2e4f6a8b1c3d5e7f', '2024-11-13 11:00:00', NULL, NULL, NULL, 'Small test deposit', NULL, '192.168.1.107'),

('TXN-20241113-001242', 9, NULL, 6, 5500.00, NULL, NULL, NULL, NULL, 0.00, 137.50, 5362.50, NULL, 'pending', NULL, NULL, NULL, '2024-11-13 11:30:00', NULL, NULL, NULL, 'AlchemyPay pending', NULL, '192.168.1.108'),

('TXN-20241113-001243', 10, NULL, 3, 10000.00, 10000.00000000, 'trc20', 'TXYZoPYvSDDeJrwS73fVqV4kLZPVJxQ8Qr', '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0', 0.00, 0.00, 10000.00, 1.00, 'pending', 2, 12, NULL, '2024-11-13 12:00:00', NULL, NULL, NULL, 'USDT via TRC20', NULL, '192.168.1.109'),

-- Failed deposit
('TXN-20241113-001244', 1, NULL, 1, 1500.00, 0.03912345, 'mainnet', 'Invalid-Address-Test', 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh', 0.00, 0.00, 1500.00, 38356.23, 'failed', 0, 3, NULL, '2024-11-12 15:00:00', NULL, NULL, NULL, 'Test deposit', 'Invalid source address', '192.168.1.100'),

-- Cancelled deposit
('TXN-20241113-001245', 2, NULL, 5, 20000.00, NULL, NULL, NULL, NULL, 0.00, 500.00, 19500.00, NULL, 'cancelled', NULL, NULL, NULL, '2024-11-11 10:00:00', NULL, NULL, NULL, 'Bank transfer', 'Cancelled by client', '192.168.1.101');

-- ============================================================
-- SAMPLE DEPOSIT STATUS HISTORY
-- ============================================================
-- Note: Status history is automatically created by triggers when deposit status changes
-- This section adds additional historical records for demonstration purposes

INSERT INTO `depositStatusHistory` (`depositId`, `previousStatus`, `newStatus`, `description`, `changedBy`, `createdAt`)
SELECT id, NULL, 'pending', 'Deposit initiated by client', NULL, requestedAt
FROM deposits WHERE transactionId = 'TXN-20241113-001234'
UNION ALL
SELECT id, 'pending', 'processing', '3 confirmations received', NULL, DATE_ADD(requestedAt, INTERVAL 30 MINUTE)
FROM deposits WHERE transactionId = 'TXN-20241113-001234'
UNION ALL
SELECT id, 'processing', 'completed', 'Deposit approved and completed by admin', 1, completedAt
FROM deposits WHERE transactionId = 'TXN-20241113-001234' AND completedAt IS NOT NULL;

-- ============================================================
-- SAMPLE DEPOSIT TAG ASSIGNMENTS
-- ============================================================
-- Using subqueries to get correct depositId based on transactionId

INSERT INTO `depositTagAssignments` (`depositId`, `tagId`, `assignedBy`, `assignedAt`)
-- TXN-20241113-001234 - Crypto tag
SELECT id, 3, 1, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM deposits WHERE transactionId = 'TXN-20241113-001234'
UNION ALL
-- TXN-20241113-001235 - Large Amount, VIP, Crypto tags
SELECT id, 1, 1, DATE_ADD(requestedAt, INTERVAL 10 MINUTE) FROM deposits WHERE transactionId = 'TXN-20241113-001235'
UNION ALL
SELECT id, 2, 1, DATE_ADD(requestedAt, INTERVAL 10 MINUTE) FROM deposits WHERE transactionId = 'TXN-20241113-001235'
UNION ALL
SELECT id, 3, 1, DATE_ADD(requestedAt, INTERVAL 10 MINUTE) FROM deposits WHERE transactionId = 'TXN-20241113-001235'
UNION ALL
-- TXN-20241113-001236 - Crypto, Stablecoin tags
SELECT id, 3, 1, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM deposits WHERE transactionId = 'TXN-20241113-001236'
UNION ALL
SELECT id, 7, 1, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM deposits WHERE transactionId = 'TXN-20241113-001236'
UNION ALL
-- TXN-20241113-001237 - Fiat, Priority tags
SELECT id, 4, 1, DATE_ADD(requestedAt, INTERVAL 5 MINUTE) FROM deposits WHERE transactionId = 'TXN-20241113-001237'
UNION ALL
SELECT id, 6, 1, DATE_ADD(requestedAt, INTERVAL 5 MINUTE) FROM deposits WHERE transactionId = 'TXN-20241113-001237'
UNION ALL
-- TXN-20241113-001238 - Large Amount, Fiat, Verified tags
SELECT id, 1, 2, DATE_ADD(requestedAt, INTERVAL 30 MINUTE) FROM deposits WHERE transactionId = 'TXN-20241113-001238'
UNION ALL
SELECT id, 4, 2, DATE_ADD(requestedAt, INTERVAL 30 MINUTE) FROM deposits WHERE transactionId = 'TXN-20241113-001238'
UNION ALL
SELECT id, 5, 2, DATE_ADD(requestedAt, INTERVAL 30 MINUTE) FROM deposits WHERE transactionId = 'TXN-20241113-001238'
UNION ALL
-- TXN-20241113-001239 - Crypto tag
SELECT id, 3, 1, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM deposits WHERE transactionId = 'TXN-20241113-001239'
UNION ALL
-- TXN-20241113-001240 - Verified tag
SELECT id, 5, 2, DATE_ADD(requestedAt, INTERVAL 5 MINUTE) FROM deposits WHERE transactionId = 'TXN-20241113-001240';

-- ============================================================
-- SAMPLE WITHDRAWALS
-- ============================================================

-- Insert sample withdrawal transactions with various statuses
INSERT INTO `withdrawals` (`transactionId`, `userId`, `tradingAccountId`, `paymentMethodId`, `amount`, `amountCrypto`, `cryptoNetwork`, `destinationAddress`, `destinationLabel`, `bankName`, `accountHolderName`, `accountNumber`, `swiftBic`, `networkFee`, `platformFee`, `netAmount`, `exchangeRate`, `status`, `withdrawalReason`, `transactionHash`, `requestedAt`, `approvedAt`, `approvedBy`, `rejectedAt`, `rejectedBy`, `rejectionReasonId`, `rejectionNotes`, `completedAt`, `adminNotes`, `previousWithdrawalsCount30Days`, `previousWithdrawalsAmount30Days`, `accountBalance`, `ipAddress`) VALUES

-- Completed withdrawals
('TXN-20241113-W001234', 1, NULL, 1, 4500.00, 0.11734567, 'mainnet', '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa', 'My BTC Wallet', NULL, NULL, NULL, NULL, 5.00, 0.00, 4495.00, 38345.21, 'completed', 'Profit withdrawal', '9a2b4c6d8e1f3a5b7c9d2e4f6a8b1c3d5e7f9a2b4c6d8e1f3a5b7c9d2e4f6a8b', '2024-11-05 14:00:00', '2024-11-05 15:00:00', 1, NULL, NULL, NULL, NULL, '2024-11-05 16:00:00', 'Verified and processed', 2, 9000.00, 15000.00, '192.168.1.100'),

('TXN-20241113-W001235', 2, NULL, 2, 8000.00, 2.51234567, 'erc20', '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0', 'ETH Main Wallet', NULL, NULL, NULL, NULL, 8.50, 0.00, 7991.50, 3183.45, 'completed', 'Partial withdrawal', '0x7c9d2e4f6a8b1c3d5e7f9a2b4c6d8e1f3a5b7c9d2e4f6a8b1c3d5e7f9a2b4c6d', '2024-11-04 10:30:00', '2024-11-04 11:30:00', 1, NULL, NULL, NULL, NULL, '2024-11-04 13:00:00', 'VIP client processed', 3, 15000.00, 25000.00, '192.168.1.101'),

('TXN-20241113-W001236', 3, NULL, 3, 6000.00, 6000.00000000, 'erc20', '0x8f4e7c2a5b9d3e6f1a8c5b2d4e7f9a3b6c8d1e4f2a5b7c9d3e6f8a1b4c6d8e', 'USDT Wallet', NULL, NULL, NULL, NULL, 0.00, 0.00, 6000.00, 1.00, 'completed', 'Account rebalancing', '0x2e4f6a8b1c3d5e7f9a2b4c6d8e1f3a5b7c9d2e4f6a8b1c3d5e7f9a2b4c6d8e1f', '2024-11-03 09:00:00', '2024-11-03 10:00:00', 2, NULL, NULL, NULL, NULL, '2024-11-03 11:00:00', 'Stablecoin withdrawal', 1, 6000.00, 12000.00, '192.168.1.102'),

('TXN-20241113-W001237', 4, NULL, 5, 12000.00, NULL, NULL, NULL, NULL, 'HSBC Bank', 'John Smith', 'GB82WEST12345698765432', 'HSBCGB2L', 0.00, 0.00, 12000.00, NULL, 'completed', 'Monthly withdrawal', 'WIRE-2024110200987654', '2024-11-02 08:00:00', '2024-11-02 10:00:00', 2, NULL, NULL, NULL, NULL, '2024-11-02 16:00:00', 'Bank transfer completed', 2, 20000.00, 30000.00, '192.168.1.103'),

-- Processing withdrawals
('TXN-20241113-W001238', 5, NULL, 1, 5500.00, 0.14356789, 'mainnet', '3J98t1WpEZ73CNmYviecrnyiWrnqRhWNLy', 'Cold Wallet', NULL, NULL, NULL, NULL, 5.00, 0.00, 5495.00, 38312.45, 'processing', 'Profit taking', NULL, '2024-11-12 15:00:00', '2024-11-12 16:00:00', 1, NULL, NULL, NULL, NULL, NULL, 'Approved - sending funds', 0, 0.00, 20000.00, '192.168.1.104'),

('TXN-20241113-W001239', 6, NULL, 2, 9500.00, 2.98765432, 'erc20', '0x1a3c5d7e9f2b4c6d8e1f3a5b7c9d2e4f6a8b1c3d5e7f9a2b4c6d8e1f3a5b7c', 'Trading Wallet', NULL, NULL, NULL, NULL, 9.00, 0.00, 9491.00, 3179.23, 'processing', 'Fund transfer', NULL, '2024-11-13 09:00:00', '2024-11-13 10:00:00', 2, NULL, NULL, NULL, NULL, NULL, 'Transaction in progress', 1, 8000.00, 18000.00, '192.168.1.105'),

-- Pending withdrawals
('TXN-20241113-W001240', 7, NULL, 1, 3200.00, 0.08356789, 'mainnet', '1BvBMSEYstWetqTFn5Au4m4GFg7xJaNVN2', 'Savings Wallet', NULL, NULL, NULL, NULL, 5.00, 0.00, 3195.00, 38289.12, 'pending', 'Regular withdrawal', NULL, '2024-11-13 11:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pending admin review', 1, 5000.00, 12000.00, '192.168.1.106'),

('TXN-20241113-W001241', 8, NULL, 3, 7000.00, 7000.00000000, 'trc20', 'TXYZoPYvSDDeJrwS73fVqV4kLZPVJxQ8Qr', 'USDT TRC20', NULL, NULL, NULL, NULL, 1.00, 0.00, 6999.00, 1.00, 'pending', 'Withdrawal request', NULL, '2024-11-13 12:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'New withdrawal request', 0, 0.00, 15000.00, '192.168.1.107'),

('TXN-20241113-W001242', 9, NULL, 5, 15000.00, NULL, NULL, NULL, NULL, 'Barclays Bank', 'Jane Doe', 'GB29NWBK60161331926819', 'BARCGB22', 0.00, 0.00, 15000.00, NULL, 'pending', 'Large withdrawal', NULL, '2024-11-13 13:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Awaiting compliance review', 2, 25000.00, 40000.00, '192.168.1.108'),

-- Rejected withdrawals
('TXN-20241113-W001243', 10, NULL, 1, 25000.00, 0.65234567, 'mainnet', 'UnverifiedAddress123456789', 'Unknown Wallet', NULL, NULL, NULL, NULL, 5.00, 0.00, 24995.00, 38345.21, 'rejected', 'Large withdrawal', NULL, '2024-11-10 14:00:00', NULL, NULL, '2024-11-10 16:00:00', 1, 5, 'Wallet address not verified. Please submit verification documents.', NULL, 'Insufficient verification', 0, 0.00, 30000.00, '192.168.1.109'),

('TXN-20241113-W001244', 1, NULL, 2, 18000.00, 5.65432100, 'erc20', '0x9f2e4a6b8c1d3e5f7a9b2c4d6e8f1a3b5c7d9e2f4a6b8c1d3e5f7a9b2c4d6e', 'Test Wallet', NULL, NULL, NULL, NULL, 10.00, 0.00, 17990.00, 3183.45, 'rejected', 'Emergency withdrawal', NULL, '2024-11-09 10:00:00', NULL, NULL, '2024-11-09 12:00:00', 2, 6, 'Cannot process withdrawal due to active trading positions. Please close all positions first.', NULL, 'Active positions detected', 4, 15000.00, 25000.00, '192.168.1.100'),

-- Cancelled withdrawal
('TXN-20241113-W001245', 2, NULL, 5, 10000.00, NULL, NULL, NULL, NULL, 'Chase Bank', 'Michael Brown', 'US64SVBKUS6S3300958879', 'CHASUS33', 0.00, 0.00, 10000.00, NULL, 'cancelled', 'Test withdrawal', NULL, '2024-11-08 09:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cancelled by client before processing', 5, 30000.00, 35000.00, '192.168.1.101');

-- ============================================================
-- SAMPLE WITHDRAWAL STATUS HISTORY
-- ============================================================
-- Note: Status history is automatically created by triggers when withdrawal status changes
-- This section adds additional historical records for demonstration purposes

INSERT INTO `withdrawalStatusHistory` (`withdrawalId`, `previousStatus`, `newStatus`, `description`, `changedBy`, `createdAt`)
-- For TXN-20241113-W001234 (Completed)
SELECT id, NULL, 'pending', 'Withdrawal request submitted by client', NULL, requestedAt FROM withdrawals WHERE transactionId = 'TXN-20241113-W001234'
UNION ALL
SELECT id, 'pending', 'processing', 'Withdrawal approved and processing', 1, approvedAt FROM withdrawals WHERE transactionId = 'TXN-20241113-W001234' AND approvedAt IS NOT NULL
UNION ALL
SELECT id, 'processing', 'completed', 'Withdrawal completed - funds sent to client', 1, completedAt FROM withdrawals WHERE transactionId = 'TXN-20241113-W001234' AND completedAt IS NOT NULL;

-- ============================================================
-- SAMPLE WITHDRAWAL TAG ASSIGNMENTS
-- ============================================================
-- Using subqueries to get correct withdrawalId based on transactionId

INSERT INTO `withdrawalTagAssignments` (`withdrawalId`, `tagId`, `assignedBy`, `assignedAt`)
-- TXN-20241113-W001234 - Large Amount, Crypto, BTC, Verified tags
SELECT id, 1, 1, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001234'
UNION ALL
SELECT id, 3, 1, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001234'
UNION ALL
SELECT id, 9, 1, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001234'
UNION ALL
SELECT id, 5, 1, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001234'
UNION ALL
-- TXN-20241113-W001235 - Large Amount, VIP, Crypto, Priority, Verified tags
SELECT id, 1, 1, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001235'
UNION ALL
SELECT id, 2, 1, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001235'
UNION ALL
SELECT id, 3, 1, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001235'
UNION ALL
SELECT id, 6, 1, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001235'
UNION ALL
SELECT id, 5, 1, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001235'
UNION ALL
-- TXN-20241113-W001236 - Crypto, Stablecoin tags
SELECT id, 3, 1, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001236'
UNION ALL
SELECT id, 10, 1, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001236'
UNION ALL
-- TXN-20241113-W001237 - Large Amount, Bank Transfer, Regular Client tags
SELECT id, 1, 2, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001237'
UNION ALL
SELECT id, 4, 2, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001237'
UNION ALL
SELECT id, 8, 2, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001237'
UNION ALL
-- TXN-20241113-W001238 - Crypto, Urgent tags
SELECT id, 3, 1, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001238'
UNION ALL
SELECT id, 7, 1, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001238'
UNION ALL
-- TXN-20241113-W001239 - Crypto tag
SELECT id, 3, 2, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001239'
UNION ALL
-- TXN-20241113-W001242 - Large Amount, Bank Transfer, Priority tags
SELECT id, 1, NULL, DATE_ADD(requestedAt, INTERVAL 5 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001242'
UNION ALL
SELECT id, 4, NULL, DATE_ADD(requestedAt, INTERVAL 5 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001242'
UNION ALL
SELECT id, 6, NULL, DATE_ADD(requestedAt, INTERVAL 5 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001242'
UNION ALL
-- TXN-20241113-W001243 - Large Amount tag
SELECT id, 1, 1, DATE_ADD(requestedAt, INTERVAL 15 MINUTE) FROM withdrawals WHERE transactionId = 'TXN-20241113-W001243';

-- ============================================================
-- SAMPLE CLIENT SAVED WALLETS
-- ============================================================

INSERT INTO `clientSavedWallets` (`userId`, `walletName`, `paymentMethodId`, `walletAddress`, `networkType`, `isVerified`, `verifiedAt`, `verificationMethod`, `isDefault`, `lastUsedAt`, `usageCount`) VALUES
-- User 1 wallets
(1, 'My Main BTC Wallet', 1, '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa', 'mainnet', 1, '2024-10-15 10:00:00', 'email', 1, '2024-11-05 16:00:00', 3),
(1, 'Cold Storage BTC', 1, '3J98t1WpEZ73CNmYviecrnyiWrnqRhWNLy', 'mainnet', 0, NULL, NULL, 0, NULL, 0),
(1, 'ETH Trading Wallet', 2, '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0', 'erc20', 1, '2024-10-20 14:00:00', 'small_transaction', 1, '2024-10-28 11:00:00', 1),

-- User 2 wallets
(2, 'Primary ETH Wallet', 2, '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb0', 'erc20', 1, '2024-10-10 09:00:00', 'email', 1, '2024-11-04 13:00:00', 5),
(2, 'USDT Main', 3, '0x8f4e7c2a5b9d3e6f1a8c5b2d4e7f9a3b6c8d1e4f2a5b7c9d3e6f8a1b4c6d8e', 'erc20', 1, '2024-10-12 11:00:00', 'sms', 1, '2024-10-30 10:00:00', 2),

-- User 3 wallets
(3, 'USDT ERC20 Wallet', 3, '0x8f4e7c2a5b9d3e6f1a8c5b2d4e7f9a3b6c8d1e4f2a5b7c9d3e6f8a1b4c6d8e', 'erc20', 1, '2024-09-25 13:00:00', 'email', 1, '2024-11-03 11:00:00', 4),
(3, 'USDT TRC20 Wallet', 3, 'TXYZoPYvSDDeJrwS73fVqV4kLZPVJxQ8Qr', 'trc20', 1, '2024-10-05 15:00:00', 'small_transaction', 0, '2024-10-25 09:00:00', 2),

-- User 4 wallets
(4, 'BTC Savings', 1, '1BvBMSEYstWetqTFn5Au4m4GFg7xJaNVN2', 'mainnet', 1, '2024-09-15 10:00:00', 'email', 1, '2024-10-20 14:00:00', 2),

-- User 5 wallets
(5, 'Main Trading Wallet', 1, '3J98t1WpEZ73CNmYviecrnyiWrnqRhWNLy', 'mainnet', 1, '2024-08-20 12:00:00', 'email', 1, '2024-11-12 16:00:00', 1),
(5, 'Backup ETH', 2, '0x1a3c5d7e9f2b4c6d8e1f3a5b7c9d2e4f6a8b1c3d5e7f9a2b4c6d8e1f3a5b7c', 'erc20', 0, NULL, NULL, 0, NULL, 0);

-- ============================================================
-- SAMPLE WITHDRAWAL DOCUMENT REQUESTS
-- ============================================================
-- Note: Document requests are skipped due to complexity of nested IDs
-- In production, these would be created through the admin interface
-- Uncomment and modify the following if needed for testing

/*
-- Document request for high-value withdrawal (TXN-20241113-W001243)
INSERT INTO `withdrawalDocumentRequests` (`withdrawalId`, `requestStatus`, `requestedBy`, `requestedAt`, `adminInstructions`, `adminNotes`)
SELECT id, 'pending', 1, DATE_ADD(requestedAt, INTERVAL 2 HOUR),
       'Please provide additional verification documents for this large withdrawal request.',
       'High-value withdrawal requires enhanced due diligence'
FROM withdrawals WHERE transactionId = 'TXN-20241113-W001243';

-- Document request for bank withdrawal (TXN-20241113-W001242)
INSERT INTO `withdrawalDocumentRequests` (`withdrawalId`, `requestStatus`, `requestedBy`, `requestedAt`, `adminInstructions`, `adminNotes`)
SELECT id, 'pending', 2, DATE_ADD(requestedAt, INTERVAL 15 MINUTE),
       'Please confirm your bank account details and provide a recent bank statement.',
       'Standard compliance check for bank withdrawal'
FROM withdrawals WHERE transactionId = 'TXN-20241113-W001242';
*/

-- ============================================================
-- VERIFICATION QUERIES
-- ============================================================

-- Count deposits by status
SELECT
    status,
    COUNT(*) as count,
    SUM(amount) as total_amount,
    SUM(netAmount) as total_net_amount
FROM deposits
GROUP BY status
ORDER BY status;

-- Count withdrawals by status
SELECT
    status,
    COUNT(*) as count,
    SUM(amount) as total_amount,
    SUM(netAmount) as total_net_amount
FROM withdrawals
GROUP BY status
ORDER BY status;

-- Count deposit tags
SELECT
    dt.tagName,
    COUNT(dta.depositId) as usage_count
FROM depositTags dt
LEFT JOIN depositTagAssignments dta ON dt.id = dta.tagId
GROUP BY dt.id, dt.tagName
ORDER BY usage_count DESC;

-- Count withdrawal tags
SELECT
    wt.tagName,
    COUNT(wta.withdrawalId) as usage_count
FROM withdrawalTags wt
LEFT JOIN withdrawalTagAssignments wta ON wt.id = wta.tagId
GROUP BY wt.id, wt.tagName
ORDER BY usage_count DESC;

-- Count saved wallets
SELECT
    COUNT(*) as total_wallets,
    SUM(CASE WHEN isVerified = 1 THEN 1 ELSE 0 END) as verified_wallets,
    SUM(CASE WHEN isDefault = 1 THEN 1 ELSE 0 END) as default_wallets
FROM clientSavedWallets;

-- Count document requests
SELECT
    requestStatus,
    COUNT(*) as count
FROM withdrawalDocumentRequests
GROUP BY requestStatus;

COMMIT;

-- ============================================================
-- SUCCESS MESSAGE
-- ============================================================

SELECT 'Sample data inserted successfully for Funding Management System' AS status,
       (SELECT COUNT(*) FROM deposits) AS total_deposits,
       (SELECT COUNT(*) FROM withdrawals) AS total_withdrawals,
       (SELECT COUNT(*) FROM depositStatusHistory) AS deposit_history_records,
       (SELECT COUNT(*) FROM withdrawalStatusHistory) AS withdrawal_history_records,
       (SELECT COUNT(*) FROM depositTagAssignments) AS deposit_tags_assigned,
       (SELECT COUNT(*) FROM withdrawalTagAssignments) AS withdrawal_tags_assigned,
       (SELECT COUNT(*) FROM clientSavedWallets) AS saved_wallets,
       (SELECT COUNT(*) FROM withdrawalDocumentRequests) AS document_requests;

-- ============================================================
-- END OF SAMPLE DATA MIGRATION
-- ============================================================
