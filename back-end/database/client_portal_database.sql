-- ============================================================
-- Client Portal Database Schema
-- For Client Login/Registration and Admin Settings Pages
-- ============================================================
-- Created: 2024-01-15
-- Description: Database tables for client portal authentication,
--              login page customization, and registration management
-- Naming Convention: camelCase
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================
-- CLIENT AUTHENTICATION TABLES
-- ============================================================

-- Client Users Table
-- Stores all client user accounts for the trading platform
CREATE TABLE `clientUsers` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `passwordHash` varchar(255) NOT NULL,
  `firstName` varchar(100) DEFAULT NULL,
  `lastName` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `emailVerified` tinyint(1) NOT NULL DEFAULT 0,
  `emailVerifiedAt` datetime DEFAULT NULL,
  `status` enum('active','inactive','suspended','pending_verification') NOT NULL DEFAULT 'pending_verification',
  `registrationIp` varchar(45) DEFAULT NULL,
  `lastLoginAt` datetime DEFAULT NULL,
  `lastLoginIp` varchar(45) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `status` (`status`),
  KEY `emailVerified` (`emailVerified`),
  KEY `createdAt` (`createdAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Client user accounts';

-- Client Password Reset Table
-- Manages password reset tokens for client accounts
CREATE TABLE `clientPasswordResets` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expiresAt` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `usedAt` datetime DEFAULT NULL,
  `ipAddress` varchar(45) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `token` (`token`),
  KEY `expiresAt` (`expiresAt`),
  KEY `used` (`used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Client password reset tokens';

-- Client Sessions Table
-- Tracks active client sessions
CREATE TABLE `clientSessions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `userId` int(11) UNSIGNED NOT NULL,
  `token` varchar(500) NOT NULL,
  `ipAddress` varchar(45) DEFAULT NULL,
  `userAgent` text DEFAULT NULL,
  `lastActivity` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expiresAt` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `userId` (`userId`),
  KEY `token` (`token`(255)),
  KEY `lastActivity` (`lastActivity`),
  KEY `expiresAt` (`expiresAt`),
  CONSTRAINT `clientSessionsUserFk` FOREIGN KEY (`userId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Client active sessions';

-- Client Email Verification Table
-- Stores email verification tokens
CREATE TABLE `clientEmailVerifications` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `userId` int(11) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expiresAt` datetime NOT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `verifiedAt` datetime DEFAULT NULL,
  `ipAddress` varchar(45) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `userId` (`userId`),
  KEY `email` (`email`),
  KEY `token` (`token`),
  KEY `expiresAt` (`expiresAt`),
  KEY `verified` (`verified`),
  CONSTRAINT `clientEmailVerificationsUserFk` FOREIGN KEY (`userId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Client email verification tokens';

-- ============================================================
-- LOGIN PAGE CUSTOMIZATION TABLES
-- ============================================================

-- Login Page Branding Settings
-- Stores customization for the client login page left panel
CREATE TABLE `loginPageBranding` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `logoType` enum('text','image') NOT NULL DEFAULT 'text',
  `logoText` varchar(100) DEFAULT 'BDX',
  `logoImagePath` varchar(500) DEFAULT NULL,
  `taglineEn` varchar(500) DEFAULT 'Advanced CFD Trading Platform',
  `taglineZh` varchar(500) DEFAULT '先进的差价合约交易平台',
  `featuresContent` text DEFAULT NULL COMMENT 'HTML content for features section',
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updatedBy` int(11) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID who made the update',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Login page branding customization';

-- Insert default branding settings
INSERT INTO `loginPageBranding` (`id`, `logoType`, `logoText`, `taglineEn`, `taglineZh`, `featuresContent`) VALUES
(1, 'text', 'BDX', 'Advanced CFD Trading Platform', '先进的差价合约交易平台',
'<ul><li>Trade CFDs on Forex, Stocks, and Commodities</li><li>Competitive spreads and fast execution</li><li>Advanced charting and analysis tools</li><li>24/7 customer support</li><li>Secure and regulated platform</li></ul>');

-- Registration Form Fields Configuration
-- Manages which fields appear in the registration form
CREATE TABLE `registrationFormFields` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `fieldId` varchar(100) NOT NULL COMMENT 'Unique identifier for the field',
  `fieldName` varchar(200) NOT NULL COMMENT 'Display name of the field',
  `fieldDescription` varchar(500) DEFAULT NULL,
  `fieldType` enum('text','email','password','tel','select','checkbox','radio','textarea') NOT NULL DEFAULT 'text',
  `isEnabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether field appears in form',
  `isRequired` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether field is required',
  `isMandatory` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Cannot be disabled/removed',
  `displayOrder` int(11) NOT NULL DEFAULT 0,
  `validationRules` text DEFAULT NULL COMMENT 'JSON array of validation rules',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fieldId` (`fieldId`),
  KEY `isEnabled` (`isEnabled`),
  KEY `displayOrder` (`displayOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registration form fields configuration';

-- Insert default registration fields
INSERT INTO `registrationFormFields` (`fieldId`, `fieldName`, `fieldDescription`, `fieldType`, `isEnabled`, `isRequired`, `isMandatory`, `displayOrder`) VALUES
('email', 'Email Address', 'User\'s email address (required for login)', 'email', 1, 1, 1, 1),
('password', 'Password', 'User\'s account password', 'password', 1, 1, 1, 2),
('confirmPassword', 'Confirm Password', 'Password confirmation', 'password', 1, 1, 1, 3),
('firstName', 'First Name', 'User\'s first name', 'text', 1, 1, 0, 4),
('lastName', 'Last Name', 'User\'s last name', 'text', 1, 1, 0, 5),
('phone', 'Phone Number', 'User\'s contact phone number', 'tel', 1, 1, 0, 6),
('country', 'Country of Residence', 'User\'s country of residence', 'select', 1, 1, 0, 7);

-- Password Strength Settings
-- Configuration for password complexity requirements
CREATE TABLE `passwordStrengthSettings` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `strengthLevel` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `minLength` int(11) NOT NULL DEFAULT 8,
  `requireLetters` tinyint(1) NOT NULL DEFAULT 1,
  `requireNumbers` tinyint(1) NOT NULL DEFAULT 1,
  `requireUppercase` tinyint(1) NOT NULL DEFAULT 0,
  `requireLowercase` tinyint(1) NOT NULL DEFAULT 0,
  `requireSpecialChars` tinyint(1) NOT NULL DEFAULT 0,
  `description` varchar(500) DEFAULT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Only one level can be active at a time',
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `isActive` (`isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Password strength requirements';

-- Insert default password strength settings for all three levels
INSERT INTO `passwordStrengthSettings` (`id`, `strengthLevel`, `minLength`, `requireLetters`, `requireNumbers`, `requireUppercase`, `requireLowercase`, `requireSpecialChars`, `description`, `isActive`) VALUES
(1, 'low', 6, 0, 0, 0, 0, 0, 'Minimum 6 characters - No special requirements', 0),
(2, 'medium', 8, 1, 1, 0, 0, 0, 'Minimum 8 characters with letters and numbers', 1),
(3, 'high', 12, 1, 1, 1, 1, 1, 'Minimum 12 characters with uppercase, lowercase, numbers and special characters', 0);

-- Legal Documents
-- Stores Terms of Service, Privacy Policy, Risk Disclosure, etc.
CREATE TABLE `legalDocuments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `documentType` varchar(100) NOT NULL COMMENT 'e.g., terms_of_service, privacy_policy, risk_disclosure',
  `title` varchar(500) NOT NULL,
  `content` longtext NOT NULL COMMENT 'HTML content of the document',
  `languageCode` varchar(10) NOT NULL DEFAULT 'en' COMMENT 'Language code (en, zh, etc.)',
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `version` varchar(50) DEFAULT '1.0',
  `effectiveDate` date DEFAULT NULL,
  `displayOrder` int(11) NOT NULL DEFAULT 0,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updatedBy` int(11) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  PRIMARY KEY (`id`),
  KEY `documentType` (`documentType`),
  KEY `languageCode` (`languageCode`),
  KEY `isActive` (`isActive`),
  KEY `displayOrder` (`displayOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Legal documents for registration';

-- Insert default legal documents (empty content for admin to fill)
INSERT INTO `legalDocuments` (`documentType`, `title`, `content`, `languageCode`, `displayOrder`) VALUES
('terms_of_service', 'Terms of Service', '<p>Please add your Terms of Service content here.</p>', 'en', 1),
('privacy_policy', 'Privacy Policy', '<p>Please add your Privacy Policy content here.</p>', 'en', 2),
('risk_disclosure', 'Risk Disclosure', '<p>Please add your Risk Disclosure content here.</p>', 'en', 3);

-- Language Packs
-- Manages available languages and their translations
CREATE TABLE `languagePacks` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `languageCode` varchar(10) NOT NULL COMMENT 'ISO language code (en, zh, th, vi, ja, etc.)',
  `languageName` varchar(200) NOT NULL COMMENT 'Display name of language',
  `flagEmoji` varchar(10) DEFAULT NULL COMMENT 'Flag emoji or icon',
  `translations` longtext NOT NULL COMMENT 'JSON object containing all translations',
  `isEnabled` tinyint(1) NOT NULL DEFAULT 1,
  `isDefault` tinyint(1) NOT NULL DEFAULT 0,
  `isBuiltin` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Built-in language cannot be deleted',
  `lastUpdated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `languageCode` (`languageCode`),
  KEY `isEnabled` (`isEnabled`),
  KEY `isDefault` (`isDefault`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Language packs for client portal';

-- Insert default language packs (English and Simplified Chinese)
INSERT INTO `languagePacks` (`languageCode`, `languageName`, `flagEmoji`, `translations`, `isEnabled`, `isDefault`, `isBuiltin`) VALUES
('en', 'English', '🇺🇸', '{"tagline":"Advanced CFD Trading Platform","feature1":"Trade CFDs on Forex, Stocks, and Commodities","feature2":"Competitive spreads and fast execution","feature3":"Advanced charting and analysis tools","feature4":"24/7 customer support","feature5":"Secure and regulated platform","welcome":"Welcome Back","loginSubtitle":"Log in to access your trading account","emailAddress":"Email Address","emailPlaceholder":"Enter your email","password":"Password","passwordPlaceholder":"Enter your password","rememberMe":"Remember me","forgotPassword":"Forgot Password?","loginButton":"Log In","or":"OR","noAccount":"Don\'t have an account?","signUp":"Sign Up","securityNotice":"🔒 Your connection is secure and encrypted. BDX is committed to protecting your data.","firstName":"First Name","lastName":"Last Name","phoneNumber":"Phone Number","country":"Country of Residence","confirmPassword":"Confirm Password"}', 1, 1, 1),
('zh', '简体中文 (Simplified Chinese)', '🇨🇳', '{"tagline":"先进的差价合约交易平台","feature1":"交易外汇、股票和大宗商品的差价合约","feature2":"极具竞争力的点差和快速执行","feature3":"先进的图表和分析工具","feature4":"7×24小时客户支持","feature5":"安全且受监管的平台","welcome":"欢迎回来","loginSubtitle":"登录您的交易账户","emailAddress":"邮箱地址","emailPlaceholder":"请输入您的邮箱","password":"密码","passwordPlaceholder":"请输入您的密码","rememberMe":"记住我","forgotPassword":"忘记密码？","loginButton":"登录","or":"或","noAccount":"还没有账户？","signUp":"注册","securityNotice":"🔒 您的连接是安全加密的。BDX致力于保护您的数据。","firstName":"名字","lastName":"姓氏","phoneNumber":"电话号码","country":"居住国家","confirmPassword":"确认密码"}', 1, 0, 1);

-- IP-Based Language Detection Settings
-- Configuration for automatic language detection based on IP
CREATE TABLE `ipLanguageDetectionSettings` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `isEnabled` tinyint(1) NOT NULL DEFAULT 1,
  `defaultLanguageCode` varchar(10) NOT NULL DEFAULT 'en',
  `fallbackLanguageCode` varchar(10) NOT NULL DEFAULT 'en',
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IP-based language detection settings';

-- Insert default IP language detection settings
INSERT INTO `ipLanguageDetectionSettings` (`id`, `isEnabled`, `defaultLanguageCode`, `fallbackLanguageCode`) VALUES
(1, 1, 'en', 'en');

-- Email Verification Settings
-- Configuration for email verification process
CREATE TABLE `emailVerificationSettings` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `isRequired` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Require email verification for new accounts',
  `emailSubject` varchar(500) NOT NULL DEFAULT 'Verify Your BDX Account',
  `emailTemplate` text NOT NULL COMMENT 'Email template with placeholders like {user_name}, {verification_link}',
  `verificationLinkExpiryHours` int(11) NOT NULL DEFAULT 24,
  `allowResend` tinyint(1) NOT NULL DEFAULT 1,
  `resendCooldownMinutes` int(11) NOT NULL DEFAULT 5 COMMENT 'Minutes before user can request another email',
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Email verification configuration';

-- Insert default email verification settings
INSERT INTO `emailVerificationSettings` (`id`, `isRequired`, `emailSubject`, `emailTemplate`, `verificationLinkExpiryHours`, `allowResend`, `resendCooldownMinutes`) VALUES
(1, 1, 'Verify Your BDX Account',
'Dear {user_name},\n\nThank you for registering with BDX! To complete your registration and start trading, please verify your email address by clicking the link below:\n\n{verification_link}\n\nThis link will expire in 24 hours.\n\nIf you did not create this account, please ignore this email.\n\nBest regards,\nThe BDX Team',
24, 1, 5);

-- ============================================================
-- UTILITY TABLES
-- ============================================================

-- Client Activity Log
-- Tracks important client actions for security and auditing
CREATE TABLE `clientActivityLog` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `userId` int(11) UNSIGNED DEFAULT NULL,
  `activityType` varchar(100) NOT NULL COMMENT 'login, logout, register, password_reset, etc.',
  `description` text DEFAULT NULL,
  `ipAddress` varchar(45) DEFAULT NULL,
  `userAgent` text DEFAULT NULL,
  `metadata` text DEFAULT NULL COMMENT 'JSON data with additional info',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `userId` (`userId`),
  KEY `activityType` (`activityType`),
  KEY `createdAt` (`createdAt`),
  CONSTRAINT `clientActivityLogUserFk` FOREIGN KEY (`userId`) REFERENCES `clientUsers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Client activity audit log';

-- Settings Change Log
-- Tracks changes made to login page settings
CREATE TABLE `loginSettingsChangeLog` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `settingType` varchar(100) NOT NULL COMMENT 'branding, registration, legal, language, email',
  `settingName` varchar(200) NOT NULL,
  `oldValue` text DEFAULT NULL,
  `newValue` text DEFAULT NULL,
  `changedBy` int(11) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `ipAddress` varchar(45) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `settingType` (`settingType`),
  KEY `changedBy` (`changedBy`),
  KEY `createdAt` (`createdAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for login settings changes';

-- ============================================================
-- INDEXES AND CONSTRAINTS
-- ============================================================

-- Additional indexes for performance optimization
ALTER TABLE `clientUsers`
  ADD INDEX `idxEmailStatus` (`email`, `status`),
  ADD INDEX `idxLastLogin` (`lastLoginAt`);

ALTER TABLE `clientSessions`
  ADD INDEX `idxUserExpires` (`userId`, `expiresAt`);

ALTER TABLE `legalDocuments`
  ADD INDEX `idxTypeLangActive` (`documentType`, `languageCode`, `isActive`);

COMMIT;

-- ============================================================
-- END OF CLIENT PORTAL DATABASE SCHEMA
-- ============================================================
