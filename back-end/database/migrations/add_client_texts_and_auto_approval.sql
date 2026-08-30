-- ============================================================
-- Migration: Add Client Interface Texts and Auto-Approval Rules
-- Version: 1.0
-- Date: 2024-11-17
-- Description: Adds tables for customizable client interface texts
--              and auto-approval rules for deposits/withdrawals
-- ============================================================

-- Check if tables already exist before creating
SET @exist_client_texts := (SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'clientInterfaceTexts');
SET @exist_countries := (SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'countries');
SET @exist_auto_rules := (SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'autoApprovalRules');
SET @exist_auto_log := (SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'autoApprovalLog');

-- ============================================================
-- CLIENT INTERFACE TEXT CONFIGURATION
-- ============================================================

-- Create clientInterfaceTexts table if it doesn't exist
SET @sql_client_texts = IF(@exist_client_texts = 0,
'CREATE TABLE `clientInterfaceTexts` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `textKey` varchar(100) NOT NULL COMMENT ''Unique key: deposit.pageTitle, withdrawal.successMessage, etc.'',
  `textCategory` enum(''deposit'',''withdrawal'',''general'') NOT NULL DEFAULT ''general'',
  `textValue` text NOT NULL COMMENT ''The actual text displayed to clients'',
  `defaultValue` text DEFAULT NULL COMMENT ''Default fallback value'',
  `description` varchar(500) DEFAULT NULL COMMENT ''Description of this text field'',
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT ''Admin user ID who last updated'',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukTextKey` (`textKey`),
  KEY `idxTextCategory` (`textCategory`),
  KEY `idxIsActive` (`isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Client interface text customization''',
'SELECT ''Table clientInterfaceTexts already exists'' AS message');

PREPARE stmt FROM @sql_client_texts;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Insert default client interface texts (only if table was just created)
INSERT IGNORE INTO `clientInterfaceTexts` (`textKey`, `textCategory`, `textValue`, `defaultValue`, `description`) VALUES
('deposit.pageTitle', 'deposit', 'Deposit Funds', 'Deposit Funds', 'Main title shown on deposit page'),
('deposit.pageDescription', 'deposit', 'Add funds to your trading account quickly and securely', 'Add funds to your trading account quickly and securely', 'Description text under deposit page title'),
('deposit.minimumNotice', 'deposit', 'Minimum deposit: $${amount}', 'Minimum deposit: $${amount}', 'Notice about minimum deposit amount (use ${amount} for dynamic value)'),
('deposit.processingNotice', 'deposit', 'Processing Time: Cryptocurrency deposits are typically confirmed within 15-30 minutes.', 'Processing Time: Cryptocurrency deposits are typically confirmed within 15-30 minutes.', 'Information about deposit processing time'),
('deposit.successMessage', 'deposit', '✓ Deposit request created successfully! Your deposit will be credited after confirmation.', '✓ Deposit request created successfully! Your deposit will be credited after confirmation.', 'Success message shown after creating deposit'),
('withdrawal.pageTitle', 'withdrawal', 'Withdraw Funds', 'Withdraw Funds', 'Main title shown on withdrawal page'),
('withdrawal.pageDescription', 'withdrawal', 'Withdraw your profits securely to your preferred payment method', 'Withdraw your profits securely to your preferred payment method', 'Description text under withdrawal page title'),
('withdrawal.minimumNotice', 'withdrawal', 'Minimum withdrawal: $${amount}', 'Minimum withdrawal: $${amount}', 'Notice about minimum withdrawal amount (use ${amount} for dynamic value)'),
('withdrawal.reviewWarning', 'withdrawal', 'Important: All withdrawal requests are reviewed for security. Processing time: 1-3 business days.', 'Important: All withdrawal requests are reviewed for security. Processing time: 1-3 business days.', 'Warning about withdrawal review process'),
('withdrawal.successMessage', 'withdrawal', '✓ Withdrawal request submitted successfully! You will receive an email notification once processed.', '✓ Withdrawal request submitted successfully! You will receive an email notification once processed.', 'Success message shown after submitting withdrawal'),
('deposit.tips', 'deposit', '[{"id":1,"icon":"fa-bolt","title":"Instant Deposits","description":"Use cryptocurrency for instant deposits"},{"id":2,"icon":"fa-shield-alt","title":"Secure Transactions","description":"All transactions are encrypted"}]', '[{"id":1,"icon":"fa-bolt","title":"Instant Deposits","description":"Use cryptocurrency for instant deposits"},{"id":2,"icon":"fa-shield-alt","title":"Secure Transactions","description":"All transactions are encrypted"}]', 'Dynamic list of deposit tips (JSON array)'),
('withdrawal.informations', 'withdrawal', '[{"id":1,"icon":"fa-clock","title":"Processing Time","description":"Crypto: 1-2 hours | Bank: 2-3 days"},{"id":2,"icon":"fa-percentage","title":"Fees","description":"Crypto: Network fee | Bank: Free"}]', '[{"id":1,"icon":"fa-clock","title":"Processing Time","description":"Crypto: 1-2 hours | Bank: 2-3 days"},{"id":2,"icon":"fa-percentage","title":"Fees","description":"Crypto: Network fee | Bank: Free"}]', 'Dynamic list of withdrawal information items (JSON array)');

-- ============================================================
-- COUNTRY REFERENCE DATA
-- ============================================================

-- Create countries table if it doesn't exist
SET @sql_countries = IF(@exist_countries = 0,
'CREATE TABLE `countries` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(3) NOT NULL COMMENT ''ISO 3166-1 alpha-3 country code'',
  `code2` varchar(2) NOT NULL COMMENT ''ISO 3166-1 alpha-2 country code'',
  `name` varchar(100) NOT NULL COMMENT ''Country name in English'',
  `region` varchar(50) DEFAULT NULL COMMENT ''Geographic region'',
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukCountryCode` (`code`),
  UNIQUE KEY `ukCountryCode2` (`code2`),
  KEY `idxRegion` (`region`),
  KEY `idxIsActive` (`isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Countries reference data''',
'SELECT ''Table countries already exists'' AS message');

PREPARE stmt FROM @sql_countries;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Insert common countries (only if table was just created)
INSERT IGNORE INTO `countries` (`code`, `code2`, `name`, `region`) VALUES
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

-- Create autoApprovalRules table if it doesn't exist
SET @sql_auto_rules = IF(@exist_auto_rules = 0,
'CREATE TABLE `autoApprovalRules` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ruleType` enum(''deposit'',''withdrawal'') NOT NULL COMMENT ''Type of transaction this rule applies to'',
  `ruleName` varchar(100) NOT NULL COMMENT ''Descriptive name for the rule'',
  `isEnabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''Whether this rule is active'',
  `priority` int(11) NOT NULL DEFAULT 0 COMMENT ''Rule priority (higher = checked first)'',

  `minAmount` decimal(15,2) DEFAULT NULL COMMENT ''Minimum transaction amount (USD)'',
  `maxAmount` decimal(15,2) DEFAULT NULL COMMENT ''Maximum transaction amount (USD)'',

  `allowedCountries` text DEFAULT NULL COMMENT ''JSON array of allowed country codes, or ALL for all countries'',
  `excludedCountries` text DEFAULT NULL COMMENT ''JSON array of excluded country codes'',

  `requiredClientTags` text DEFAULT NULL COMMENT ''Comma-separated tags - client must have ALL these tags'',
  `excludedClientTags` text DEFAULT NULL COMMENT ''Comma-separated tags - client with ANY of these tags will be excluded'',
  `requireKycVerified` tinyint(1) NOT NULL DEFAULT 1 COMMENT ''Require client to have verified KYC status'',
  `minAccountAge` int(11) DEFAULT NULL COMMENT ''Minimum account age in days'',
  `minPreviousTransactions` int(11) DEFAULT NULL COMMENT ''Minimum number of successful previous transactions'',

  `checkSavedWallet` tinyint(1) DEFAULT 0 COMMENT ''For withdrawals: only approve if using saved/verified wallet'',
  `requireMatchingDepositMethod` tinyint(1) DEFAULT 0 COMMENT ''For withdrawals: require previous deposit using same method'',

  `allowedPaymentMethods` text DEFAULT NULL COMMENT ''JSON array of allowed payment method IDs, or NULL for all'',
  `timeRestrictions` text DEFAULT NULL COMMENT ''JSON object for time-based restrictions (business hours, etc.)'',

  `description` text DEFAULT NULL COMMENT ''Detailed description of rule purpose'',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT ''Admin user ID who last updated'',
  `lastAppliedAt` datetime DEFAULT NULL COMMENT ''Last time this rule auto-approved a transaction'',
  `totalApprovals` int(11) NOT NULL DEFAULT 0 COMMENT ''Total number of transactions auto-approved by this rule'',

  PRIMARY KEY (`id`),
  KEY `idxRuleType` (`ruleType`),
  KEY `idxIsEnabled` (`isEnabled`),
  KEY `idxPriority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Auto-approval rules for deposits and withdrawals''',
'SELECT ''Table autoApprovalRules already exists'' AS message');

PREPARE stmt FROM @sql_auto_rules;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Insert default auto-approval rules (disabled by default for security)
INSERT IGNORE INTO `autoApprovalRules` (`id`, `ruleType`, `ruleName`, `isEnabled`, `priority`, `minAmount`, `maxAmount`, `allowedCountries`, `requiredClientTags`, `excludedClientTags`, `requireKycVerified`, `description`) VALUES
(1, 'deposit', 'Default Deposit Auto-Approval', 0, 100, 0.00, 10000.00, '["ALL"]', '', 'Suspicious,Blocked,Under Review', 1, 'Default rule for auto-approving deposits up to $10,000 for verified clients without suspicious tags'),
(2, 'withdrawal', 'Default Withdrawal Auto-Approval', 0, 100, 0.00, 5000.00, '["ALL"]', '', 'Suspicious,Blocked,Under Review', 1, 'Default rule for auto-approving withdrawals up to $5,000 for verified clients without suspicious tags');

-- ============================================================
-- AUTO-APPROVAL AUDIT LOG
-- ============================================================

-- Create autoApprovalLog table if it doesn't exist
SET @sql_auto_log = IF(@exist_auto_log = 0,
'CREATE TABLE `autoApprovalLog` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `transactionType` enum(''deposit'',''withdrawal'') NOT NULL,
  `transactionId` bigint(20) UNSIGNED NOT NULL COMMENT ''ID from deposits or withdrawals table'',
  `transactionRefId` varchar(50) DEFAULT NULL COMMENT ''Transaction reference ID (TXN-YYYYMMDD-XXXXX)'',
  `userId` bigint(20) UNSIGNED NOT NULL COMMENT ''Client user ID'',
  `ruleId` int(11) UNSIGNED DEFAULT NULL COMMENT ''Auto-approval rule that was applied (NULL if rejected)'',
  `wasAutoApproved` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''Whether transaction was auto-approved'',
  `checkResults` text DEFAULT NULL COMMENT ''JSON object with detailed check results'',
  `rejectionReason` text DEFAULT NULL COMMENT ''Reason why auto-approval was not granted'',
  `amount` decimal(15,2) NOT NULL COMMENT ''Transaction amount'',
  `clientCountry` varchar(3) DEFAULT NULL COMMENT ''Client country code at time of transaction'',
  `clientTags` text DEFAULT NULL COMMENT ''Client tags at time of transaction'',
  `kycStatus` varchar(50) DEFAULT NULL COMMENT ''Client KYC status at time of transaction'',
  `checkedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ipAddress` varchar(45) DEFAULT NULL COMMENT ''IP address of transaction request'',
  PRIMARY KEY (`id`),
  KEY `idxTransactionType` (`transactionType`, `transactionId`),
  KEY `idxUserId` (`userId`),
  KEY `idxRuleId` (`ruleId`),
  KEY `idxWasAutoApproved` (`wasAutoApproved`),
  KEY `idxCheckedAt` (`checkedAt`),
  CONSTRAINT `fkAutoApprovalLogRule` FOREIGN KEY (`ruleId`) REFERENCES `autoApprovalRules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Auto-approval decision audit log''',
'SELECT ''Table autoApprovalLog already exists'' AS message');

PREPARE stmt FROM @sql_auto_log;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- TRANSACTION SECURITY SETTINGS
-- ============================================================

-- Check if transactionSecuritySettings table exists
SET @exist_security_settings := (SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'transactionSecuritySettings');
SET @exist_otp_verifications := (SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'withdrawalOtpVerifications');

-- Create transactionSecuritySettings table if it doesn't exist
SET @sql_security_settings = IF(@exist_security_settings = 0,
'CREATE TABLE `transactionSecuritySettings` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `settingKey` varchar(100) NOT NULL COMMENT ''Unique setting key'',
  `settingValue` text DEFAULT NULL COMMENT ''Setting value'',
  `settingType` enum(''boolean'',''string'',''number'',''json'') NOT NULL DEFAULT ''boolean'',
  `description` varchar(500) DEFAULT NULL COMMENT ''Setting description'',
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT ''Admin user ID who last updated'',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ukSettingKey` (`settingKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Transaction security settings''',
'SELECT ''Table transactionSecuritySettings already exists'' AS message');

PREPARE stmt FROM @sql_security_settings;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Insert default security settings (only if table was just created)
INSERT IGNORE INTO `transactionSecuritySettings` (`settingKey`, `settingValue`, `settingType`, `description`) VALUES
('salesManagerNotifications', '0', 'boolean', 'Enable Sales Managers to receive transaction notifications for their clients'),
('withdrawalOtpRequired', '0', 'boolean', 'Require OTP verification for withdrawal requests'),
('otpValidityMinutes', '10', 'number', 'OTP code validity period in minutes (1-60)'),
('requireVerifiedWalletOnly', '1', 'boolean', 'Only allow withdrawals to verified/saved wallet addresses');

-- Create withdrawalOtpVerifications table if it doesn't exist
SET @sql_otp_verifications = IF(@exist_otp_verifications = 0,
'CREATE TABLE `withdrawalOtpVerifications` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `userId` int(11) UNSIGNED NOT NULL COMMENT ''Client user ID'',
  `otpCode` varchar(10) NOT NULL COMMENT ''The OTP code'',
  `otpHash` varchar(255) NOT NULL COMMENT ''Hashed OTP code for security'',
  `isVerified` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''Whether OTP has been verified'',
  `verifiedAt` datetime DEFAULT NULL COMMENT ''When OTP was verified'',
  `expiresAt` datetime NOT NULL COMMENT ''When OTP expires'',
  `ipAddress` varchar(45) DEFAULT NULL COMMENT ''IP address when OTP was requested'',
  `userAgent` text DEFAULT NULL COMMENT ''User agent when OTP was requested'',
  `attempts` int(11) NOT NULL DEFAULT 0 COMMENT ''Number of verification attempts'',
  `maxAttempts` int(11) NOT NULL DEFAULT 5 COMMENT ''Maximum allowed attempts'',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idxUserId` (`userId`),
  KEY `idxOtpHash` (`otpHash`),
  KEY `idxIsVerified` (`isVerified`),
  KEY `idxExpiresAt` (`expiresAt`),
  CONSTRAINT `fkWithdrawalOtpUser` FOREIGN KEY (`userId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''Withdrawal OTP verification records''',
'SELECT ''Table withdrawalOtpVerifications already exists'' AS message');

PREPARE stmt FROM @sql_otp_verifications;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- MIGRATION COMPLETE
-- ============================================================

SELECT 'Migration completed successfully!' AS status,
       'Added client interface texts, auto-approval rules, and security settings tables' AS description;
