-- ============================================================
-- Utrada CRM - KYC Management System Database
-- ============================================================
-- Created: 2024-12-20
-- Description: Database tables for KYC template management,
--              question configuration, conditional logic rules,
--              and document requirements
-- Related Pages: KYCTemplateList.html, KYCList.html, KYCSettings.html
-- Naming Convention: camelCase
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================
-- KYC TEMPLATE TABLES
-- ============================================================

-- KYC Templates
-- Main table for KYC verification templates
CREATE TABLE `kycTemplates` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `templateName` varchar(200) NOT NULL COMMENT 'Template display name',
  `description` text DEFAULT NULL COMMENT 'Template description',
  `status` enum('draft','active','inactive','archived') NOT NULL DEFAULT 'draft',
  `isThirdPartyEnabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Use third-party KYC system',
  `thirdPartyProvider` varchar(100) DEFAULT NULL COMMENT 'Third-party provider name (e.g., Jumio, Onfido)',
  `isAutoApproveEnabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Auto-approve KYC without manual review',
  `requireDocumentSignature` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Require legal document signatures',
  `totalQuestions` int(11) NOT NULL DEFAULT 0 COMMENT 'Total number of questions',
  `totalRules` int(11) NOT NULL DEFAULT 0 COMMENT 'Total number of conditional rules',
  `displayOrder` int(11) NOT NULL DEFAULT 0,
  `createdBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `isThirdPartyEnabled` (`isThirdPartyEnabled`),
  KEY `displayOrder` (`displayOrder`),
  KEY `createdAt` (`createdAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='KYC Templates';

-- Insert sample templates
INSERT INTO `kycTemplates` (`id`, `templateName`, `description`, `status`, `requireDocumentSignature`, `totalQuestions`, `totalRules`, `displayOrder`, `createdAt`) VALUES
(1, 'Standard Individual KYC', 'Standard verification for individual accounts', 'active', 1, 12, 3, 1, '2024-11-19 14:30:00'),
(2, 'Corporate Account KYC', 'Verification for corporate and business accounts', 'active', 1, 18, 8, 2, '2024-11-18 10:00:00'),
(3, 'Professional Trader KYC', 'Enhanced verification for professional traders', 'active', 1, 15, 6, 3, '2024-11-15 09:00:00'),
(4, 'Simplified KYC - Asia Pacific', 'Simplified process for APAC region', 'draft', 1, 8, 3, 4, '2024-11-10 16:00:00');

-- ============================================================
-- COUNTRY CONFIGURATION
-- ============================================================

-- KYC Template Countries
-- Defines which countries each template applies to
CREATE TABLE `kycTemplateCountries` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `templateId` int(11) UNSIGNED NOT NULL,
  `countryCode` varchar(10) NOT NULL COMMENT 'ISO country code (US, UK, CA, AU, etc.) or "ALL"',
  `countryName` varchar(100) NOT NULL COMMENT 'Country display name',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniqueTemplateCountry` (`templateId`, `countryCode`),
  KEY `templateId` (`templateId`),
  KEY `countryCode` (`countryCode`),
  CONSTRAINT `kycTemplateCountriesFk` FOREIGN KEY (`templateId`) REFERENCES `kycTemplates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Template country assignments';

-- Insert sample country assignments
INSERT INTO `kycTemplateCountries` (`templateId`, `countryCode`, `countryName`) VALUES
-- Template 1: Standard Individual KYC
(1, 'US', 'United States'),
(1, 'CA', 'Canada'),
(1, 'UK', 'United Kingdom'),
(1, 'AU', 'Australia'),
(1, 'DE', 'Germany'),
-- Template 2: Corporate Account KYC
(2, 'ALL', 'All Countries'),
-- Template 3: Professional Trader KYC
(3, 'UK', 'United Kingdom'),
(3, 'SG', 'Singapore'),
-- Template 4: Simplified KYC - Asia Pacific
(4, 'JP', 'Japan'),
(4, 'HK', 'Hong Kong'),
(4, 'TH', 'Thailand'),
(4, 'VN', 'Vietnam');

-- ============================================================
-- QUESTION SYSTEM
-- ============================================================

-- KYC Question Categories
-- Organizes questions into logical groups
CREATE TABLE `kycQuestionCategories` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `templateId` int(11) UNSIGNED NOT NULL,
  `categoryName` varchar(200) NOT NULL COMMENT 'Category display name',
  `description` text DEFAULT NULL,
  `displayOrder` int(11) NOT NULL DEFAULT 0,
  `isExpanded` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Default expanded state in UI',
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `templateId` (`templateId`),
  KEY `displayOrder` (`displayOrder`),
  KEY `isActive` (`isActive`),
  CONSTRAINT `kycQuestionCategoriesFk` FOREIGN KEY (`templateId`) REFERENCES `kycTemplates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='KYC Question Categories';

-- Insert sample categories for Template 1
INSERT INTO `kycQuestionCategories` (`templateId`, `categoryName`, `description`, `displayOrder`, `isExpanded`) VALUES
(1, 'Personal Information', 'Basic personal details and identification', 1, 1),
(1, 'Financial Information', 'Income, employment and financial status', 2, 1),
(1, 'Investment Experience', 'Trading experience and knowledge', 3, 0),
(1, 'Risk Assessment', 'Risk tolerance and investment objectives', 4, 0),
(1, 'Compliance', 'Regulatory compliance and document verification', 5, 0);

-- KYC Questions
-- Individual questions within templates
CREATE TABLE `kycQuestions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `templateId` int(11) UNSIGNED NOT NULL,
  `categoryId` int(11) UNSIGNED NOT NULL,
  `questionNumber` int(11) NOT NULL COMMENT 'Display number (1, 2, 3, etc.)',
  `questionText` text NOT NULL COMMENT 'The actual question text',
  `helpText` text DEFAULT NULL COMMENT 'Helper text shown below question',
  `questionType` enum('text','number','email','tel','date','single_choice','multiple_choice','yes_no','file_upload','textarea') NOT NULL,
  `validationRules` varchar(500) DEFAULT NULL COMMENT 'Validation rules (e.g., required|min:2|max:100)',
  `isRequired` tinyint(1) NOT NULL DEFAULT 1,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `displayOrder` int(11) NOT NULL DEFAULT 0,
  `metadata` text DEFAULT NULL COMMENT 'JSON metadata for additional configuration',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `createdBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `templateId` (`templateId`),
  KEY `categoryId` (`categoryId`),
  KEY `questionType` (`questionType`),
  KEY `isActive` (`isActive`),
  KEY `displayOrder` (`displayOrder`),
  CONSTRAINT `kycQuestionsTemplateFk` FOREIGN KEY (`templateId`) REFERENCES `kycTemplates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kycQuestionsCategoryFk` FOREIGN KEY (`categoryId`) REFERENCES `kycQuestionCategories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='KYC Questions';

-- Insert sample questions for Template 1
INSERT INTO `kycQuestions` (`templateId`, `categoryId`, `questionNumber`, `questionText`, `helpText`, `questionType`, `validationRules`, `isRequired`, `displayOrder`) VALUES
-- Personal Information (Category 1)
(1, 1, 1, 'What is your full legal name?', 'Enter your name as it appears on official documents', 'text', 'required|min:2|max:100', 1, 1),
(1, 1, 2, 'What is your date of birth?', 'Select your birth date from the calendar', 'date', 'required|date|before:today', 1, 2),
(1, 1, 3, 'What is your nationality?', 'Select your country of citizenship', 'single_choice', 'required|in:United States,United Kingdom,Canada,Australia,Germany,Other', 1, 3),
-- Financial Information (Category 2)
(1, 2, 4, 'What is your annual income range?', 'Select the range that best describes your annual income', 'single_choice', 'required', 1, 4),
(1, 2, 5, 'What is your employment status?', 'Select your current employment situation', 'single_choice', 'required', 1, 5),
-- Investment Experience (Category 3)
(1, 3, 6, 'How many years of trading experience do you have?', 'Include all types of financial trading experience', 'single_choice', 'required', 1, 6),
(1, 3, 7, 'Do you have experience with high-risk investments?', 'This includes derivatives, forex, CFDs, and other leveraged products', 'yes_no', 'required|boolean', 1, 7),
(1, 3, 8, 'What types of financial instruments have you traded?', 'Select all that apply', 'multiple_choice', 'required|array|min:1', 1, 8),
-- Risk Assessment (Category 4)
(1, 4, 9, 'What is your risk tolerance level?', 'How comfortable are you with investment risk?', 'single_choice', 'required', 1, 9),
(1, 4, 10, 'What is your maximum acceptable loss per trade?', 'Enter the maximum amount you are willing to lose on a single trade (in USD)', 'number', 'required|numeric|min:1|max:100000', 1, 10),
-- Compliance (Category 5)
(1, 5, 11, 'Are you a politically exposed person (PEP)?', 'PEP includes senior political figures and their close associates', 'yes_no', 'required|boolean', 1, 11),
(1, 5, 12, 'Please upload your identity verification documents', 'Upload clear photos or scans of your official identity documents', 'file_upload', 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', 1, 12);

-- KYC Question Options
-- Options for single_choice and multiple_choice questions
CREATE TABLE `kycQuestionOptions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `questionId` int(11) UNSIGNED NOT NULL,
  `optionValue` varchar(500) NOT NULL COMMENT 'The actual option value/text',
  `displayOrder` int(11) NOT NULL DEFAULT 0,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `questionId` (`questionId`),
  KEY `displayOrder` (`displayOrder`),
  CONSTRAINT `kycQuestionOptionsFk` FOREIGN KEY (`questionId`) REFERENCES `kycQuestions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Question answer options';

-- Insert options for choice questions
-- Question 3: Nationality
INSERT INTO `kycQuestionOptions` (`questionId`, `optionValue`, `displayOrder`) VALUES
(3, 'United States', 1),
(3, 'United Kingdom', 2),
(3, 'Canada', 3),
(3, 'Australia', 4),
(3, 'Germany', 5),
(3, 'Other', 6);

-- Question 4: Annual income range
INSERT INTO `kycQuestionOptions` (`questionId`, `optionValue`, `displayOrder`) VALUES
(4, 'Under $25,000', 1),
(4, '$25,000-$50,000', 2),
(4, '$50,000-$100,000', 3),
(4, '$100,000-$250,000', 4),
(4, 'Over $250,000', 5);

-- Question 5: Employment status
INSERT INTO `kycQuestionOptions` (`questionId`, `optionValue`, `displayOrder`) VALUES
(5, 'Employed Full-time', 1),
(5, 'Employed Part-time', 2),
(5, 'Self-employed', 3),
(5, 'Retired', 4),
(5, 'Student', 5),
(5, 'Unemployed', 6);

-- Question 6: Trading experience years
INSERT INTO `kycQuestionOptions` (`questionId`, `optionValue`, `displayOrder`) VALUES
(6, 'No experience', 1),
(6, 'Less than 1 year', 2),
(6, '1-3 years', 3),
(6, '3-5 years', 4),
(6, 'More than 5 years', 5);

-- Question 8: Financial instruments
INSERT INTO `kycQuestionOptions` (`questionId`, `optionValue`, `displayOrder`) VALUES
(8, 'Stocks', 1),
(8, 'Bonds', 2),
(8, 'Forex', 3),
(8, 'CFDs', 4),
(8, 'Commodities', 5),
(8, 'Cryptocurrencies', 6),
(8, 'Options', 7),
(8, 'Futures', 8);

-- Question 9: Risk tolerance level
INSERT INTO `kycQuestionOptions` (`questionId`, `optionValue`, `displayOrder`) VALUES
(9, 'Very Low', 1),
(9, 'Low', 2),
(9, 'Moderate', 3),
(9, 'High', 4),
(9, 'Very High', 5);

-- KYC Question Document Types
-- Document types accepted for file_upload questions
CREATE TABLE `kycQuestionDocumentTypes` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `questionId` int(11) UNSIGNED NOT NULL,
  `documentType` varchar(100) NOT NULL COMMENT 'e.g., ID_CARD, PASSPORT, PROOF_ADDRESS',
  `documentDisplayName` varchar(200) NOT NULL COMMENT 'Display name for document type',
  `isRequired` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Is this document type mandatory',
  `displayOrder` int(11) NOT NULL DEFAULT 0,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `questionId` (`questionId`),
  KEY `documentType` (`documentType`),
  CONSTRAINT `kycQuestionDocumentTypesFk` FOREIGN KEY (`questionId`) REFERENCES `kycQuestions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Document types for file upload questions';

-- Insert document types for Question 12 (Identity verification)
INSERT INTO `kycQuestionDocumentTypes` (`questionId`, `documentType`, `documentDisplayName`, `isRequired`, `displayOrder`) VALUES
(12, 'ID_CARD', 'Identity Card', 0, 1),
(12, 'PASSPORT', 'Passport', 0, 2),
(12, 'DRIVERS_LICENSE', 'Driver\'s License', 0, 3),
(12, 'PROOF_ADDRESS', 'Proof of Address', 0, 4);

-- ============================================================
-- CONDITIONAL LOGIC RULES
-- ============================================================

-- KYC Conditional Rules
-- Simplified rules: Jump to question or Reject application
CREATE TABLE `kycConditionalRules` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `templateId` int(11) UNSIGNED NOT NULL,
  `ruleName` varchar(200) NOT NULL COMMENT 'Rule display name',
  `ruleType` enum('jump_to','reject') NOT NULL COMMENT 'Jump to question or Reject application',
  `triggerQuestionId` int(11) UNSIGNED NOT NULL COMMENT 'Question that triggers this rule',
  `triggerAnswer` varchar(500) NOT NULL COMMENT 'Answer value that triggers the rule',
  `targetQuestionId` int(11) UNSIGNED DEFAULT NULL COMMENT 'Question to jump to (for jump_to type)',
  `rejectMessage` text DEFAULT NULL COMMENT 'Rejection message (for reject type)',
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `displayOrder` int(11) NOT NULL DEFAULT 0,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `createdBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  PRIMARY KEY (`id`),
  KEY `templateId` (`templateId`),
  KEY `ruleType` (`ruleType`),
  KEY `triggerQuestionId` (`triggerQuestionId`),
  KEY `targetQuestionId` (`targetQuestionId`),
  KEY `isActive` (`isActive`),
  CONSTRAINT `kycConditionalRulesTemplateFk` FOREIGN KEY (`templateId`) REFERENCES `kycTemplates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kycConditionalRulesTriggerFk` FOREIGN KEY (`triggerQuestionId`) REFERENCES `kycQuestions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kycConditionalRulesTargetFk` FOREIGN KEY (`targetQuestionId`) REFERENCES `kycQuestions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Conditional logic rules';

-- Insert sample rules for Template 1
INSERT INTO `kycConditionalRules` (`templateId`, `ruleName`, `ruleType`, `triggerQuestionId`, `triggerAnswer`, `targetQuestionId`, `rejectMessage`, `isActive`, `displayOrder`) VALUES
-- Rule 1: Jump if Forex experience
(1, 'Trading Experience Jump Logic', 'jump_to', 8, 'Forex', 10, NULL, 1, 1),
-- Rule 2: Reject if no experience
(1, 'No Experience Rejection', 'reject', 6, 'No experience', NULL, 'Sorry, we require trading experience', 1, 2),
-- Rule 3: Jump if high income
(1, 'High Income Skip Logic', 'jump_to', 4, 'Over $250,000', 9, NULL, 1, 3);

-- ============================================================
-- LEGAL DOCUMENT REQUIREMENTS
-- ============================================================

-- KYC Template Documents
-- Links templates with required legal documents
-- Note: Uses existing legalDocuments table from client_portal_database.sql
CREATE TABLE `kycTemplateDocuments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `templateId` int(11) UNSIGNED NOT NULL,
  `documentId` int(11) UNSIGNED NOT NULL COMMENT 'Reference to legalDocuments.id',
  `documentTitle` varchar(500) NOT NULL COMMENT 'Document title for display',
  `documentContent` longtext NOT NULL COMMENT 'HTML content (can override legalDocuments content)',
  `displayOrder` int(11) NOT NULL DEFAULT 0,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `templateId` (`templateId`),
  KEY `documentId` (`documentId`),
  KEY `displayOrder` (`displayOrder`),
  CONSTRAINT `kycTemplateDocumentsTemplateFk` FOREIGN KEY (`templateId`) REFERENCES `kycTemplates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Template document requirements';

-- Insert sample documents for Template 1
INSERT INTO `kycTemplateDocuments` (`templateId`, `documentId`, `documentTitle`, `documentContent`, `displayOrder`) VALUES
(1, 1, 'Terms of Service Agreement', '<p>By signing up, you agree to our terms and conditions of service.</p>', 1),
(1, 2, 'Privacy Policy', '<p>We respect your privacy and are committed to protecting your personal data.</p>', 2),
(1, 3, 'Risk Disclosure Statement', '<p><strong>Warning:</strong> Trading CFDs and leveraged products carries a high level of risk and may result in the loss of all your invested capital.</p>', 3);

-- ============================================================
-- CLIENT KYC SUBMISSIONS
-- ============================================================

-- Client KYC Submissions
-- Tracks KYC form submissions from clients
CREATE TABLE `clientKycSubmissions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `clientId` int(11) UNSIGNED NOT NULL COMMENT 'Reference to clientUsers.id',
  `templateId` int(11) UNSIGNED NOT NULL,
  `submissionStatus` enum('draft','submitted','under_review','approved','rejected','incomplete') NOT NULL DEFAULT 'draft',
  `submittedAt` datetime DEFAULT NULL,
  `reviewedAt` datetime DEFAULT NULL,
  `reviewedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `approvalNotes` text DEFAULT NULL,
  `rejectionReason` text DEFAULT NULL,
  `ipAddress` varchar(45) DEFAULT NULL,
  `userAgent` text DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `clientId` (`clientId`),
  KEY `templateId` (`templateId`),
  KEY `submissionStatus` (`submissionStatus`),
  KEY `submittedAt` (`submittedAt`),
  CONSTRAINT `clientKycSubmissionsClientFk` FOREIGN KEY (`clientId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `clientKycSubmissionsTemplateFk` FOREIGN KEY (`templateId`) REFERENCES `kycTemplates` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Client KYC submissions';

-- Client KYC Answers
-- Stores answers to KYC questions
CREATE TABLE `clientKycAnswers` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `submissionId` int(11) UNSIGNED NOT NULL,
  `questionId` int(11) UNSIGNED NOT NULL,
  `answerText` text DEFAULT NULL COMMENT 'Text answer',
  `answerValues` text DEFAULT NULL COMMENT 'JSON array for multiple choice answers',
  `answerDate` date DEFAULT NULL COMMENT 'Date answer',
  `answerNumber` decimal(15,2) DEFAULT NULL COMMENT 'Numeric answer',
  `uploadedFiles` text DEFAULT NULL COMMENT 'JSON array of uploaded file paths',
  `answeredAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniqueSubmissionQuestion` (`submissionId`, `questionId`),
  KEY `submissionId` (`submissionId`),
  KEY `questionId` (`questionId`),
  CONSTRAINT `clientKycAnswersSubmissionFk` FOREIGN KEY (`submissionId`) REFERENCES `clientKycSubmissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `clientKycAnswersQuestionFk` FOREIGN KEY (`questionId`) REFERENCES `kycQuestions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Client KYC answers';

-- Client KYC Document Signatures
-- Tracks which documents clients signed
CREATE TABLE `clientKycDocumentSignatures` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `submissionId` int(11) UNSIGNED NOT NULL,
  `templateDocumentId` int(11) UNSIGNED NOT NULL,
  `signedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ipAddress` varchar(45) DEFAULT NULL,
  `userAgent` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniqueSubmissionDocument` (`submissionId`, `templateDocumentId`),
  KEY `submissionId` (`submissionId`),
  KEY `templateDocumentId` (`templateDocumentId`),
  CONSTRAINT `clientKycDocSignaturesSubmissionFk` FOREIGN KEY (`submissionId`) REFERENCES `clientKycSubmissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `clientKycDocSignaturesDocFk` FOREIGN KEY (`templateDocumentId`) REFERENCES `kycTemplateDocuments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Client document signatures';

-- ============================================================
-- AUDIT AND HISTORY TABLES
-- ============================================================

-- KYC Template Edit History
-- Tracks changes made to templates
CREATE TABLE `kycTemplateEditHistory` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `templateId` int(11) UNSIGNED NOT NULL,
  `changeType` varchar(100) NOT NULL COMMENT 'e.g., template_info, question_added, rule_modified',
  `fieldName` varchar(200) DEFAULT NULL,
  `oldValue` text DEFAULT NULL,
  `newValue` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `editedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `ipAddress` varchar(45) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `templateId` (`templateId`),
  KEY `changeType` (`changeType`),
  KEY `editedBy` (`editedBy`),
  KEY `createdAt` (`createdAt`),
  CONSTRAINT `kycTemplateEditHistoryFk` FOREIGN KEY (`templateId`) REFERENCES `kycTemplates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Template edit history';

-- KYC Submission Activity Log
-- Tracks admin actions on submissions
CREATE TABLE `kycSubmissionActivityLog` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `submissionId` int(11) UNSIGNED NOT NULL,
  `activityType` varchar(100) NOT NULL COMMENT 'e.g., status_changed, reviewed, approved, rejected',
  `description` text NOT NULL,
  `performedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `metadata` text DEFAULT NULL COMMENT 'JSON data with additional info',
  `ipAddress` varchar(45) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `submissionId` (`submissionId`),
  KEY `activityType` (`activityType`),
  KEY `performedBy` (`performedBy`),
  KEY `createdAt` (`createdAt`),
  CONSTRAINT `kycSubmissionActivityLogFk` FOREIGN KEY (`submissionId`) REFERENCES `clientKycSubmissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='KYC submission activity log';

-- ============================================================
-- VIEWS FOR CONVENIENCE
-- ============================================================

-- Template Summary View
CREATE OR REPLACE VIEW `vw_kyc_template_summary` AS
SELECT
  t.id AS templateId,
  t.templateName,
  t.description,
  t.status,
  t.isThirdPartyEnabled,
  t.isAutoApproveEnabled,
  t.requireDocumentSignature,
  t.totalQuestions,
  t.totalRules,
  t.displayOrder,
  t.createdAt,
  t.updatedAt,
  (SELECT COUNT(*) FROM kycTemplateCountries WHERE templateId = t.id) AS countryCount,
  (SELECT COUNT(*) FROM kycQuestionCategories WHERE templateId = t.id AND isActive = 1) AS activeCategoryCount,
  (SELECT COUNT(*) FROM clientKycSubmissions WHERE templateId = t.id) AS totalSubmissions,
  (SELECT COUNT(*) FROM clientKycSubmissions WHERE templateId = t.id AND submissionStatus = 'approved') AS approvedSubmissions
FROM kycTemplates t
ORDER BY t.displayOrder, t.id;

-- Question with Options View
CREATE OR REPLACE VIEW `vw_kyc_questions_full` AS
SELECT
  q.id AS questionId,
  q.templateId,
  q.categoryId,
  q.questionNumber,
  q.questionText,
  q.helpText,
  q.questionType,
  q.validationRules,
  q.isRequired,
  q.isActive,
  q.displayOrder,
  q.createdAt,
  q.updatedAt,
  c.categoryName,
  (SELECT COUNT(*) FROM kycQuestionOptions WHERE questionId = q.id AND isActive = 1) AS optionCount,
  (SELECT COUNT(*) FROM kycQuestionDocumentTypes WHERE questionId = q.id) AS documentTypeCount
FROM kycQuestions q
LEFT JOIN kycQuestionCategories c ON q.categoryId = c.id
ORDER BY q.templateId, q.displayOrder, q.questionNumber;

-- Active Templates with Countries View
CREATE OR REPLACE VIEW `vw_kyc_active_templates` AS
SELECT
  t.id AS templateId,
  t.templateName,
  t.description,
  t.totalQuestions,
  t.totalRules,
  GROUP_CONCAT(tc.countryName ORDER BY tc.countryName SEPARATOR ', ') AS countries,
  GROUP_CONCAT(tc.countryCode ORDER BY tc.countryCode SEPARATOR ',') AS countryCodes
FROM kycTemplates t
LEFT JOIN kycTemplateCountries tc ON t.id = tc.templateId
WHERE t.status = 'active'
GROUP BY t.id
ORDER BY t.displayOrder;

-- Client KYC Progress View
CREATE OR REPLACE VIEW `vw_client_kyc_progress` AS
SELECT
  s.id AS submissionId,
  s.clientId,
  cu.email AS clientEmail,
  cu.firstName,
  cu.lastName,
  s.templateId,
  t.templateName,
  s.submissionStatus,
  s.submittedAt,
  s.reviewedAt,
  (SELECT COUNT(*) FROM clientKycAnswers WHERE submissionId = s.id) AS answeredQuestions,
  t.totalQuestions,
  ROUND((SELECT COUNT(*) FROM clientKycAnswers WHERE submissionId = s.id) / t.totalQuestions * 100, 2) AS progressPercentage,
  (SELECT COUNT(*) FROM clientKycDocumentSignatures WHERE submissionId = s.id) AS signedDocuments,
  (SELECT COUNT(*) FROM kycTemplateDocuments WHERE templateId = s.templateId AND isActive = 1) AS requiredDocuments
FROM clientKycSubmissions s
INNER JOIN clientUsers cu ON s.clientId = cu.id
INNER JOIN kycTemplates t ON s.templateId = t.id
ORDER BY s.submittedAt DESC;

-- ============================================================
-- TRIGGERS
-- ============================================================

-- Auto-update totalQuestions when questions are added/removed
DELIMITER $$
CREATE TRIGGER `trg_update_total_questions_after_insert`
AFTER INSERT ON `kycQuestions`
FOR EACH ROW
BEGIN
    UPDATE kycTemplates
    SET totalQuestions = (SELECT COUNT(*) FROM kycQuestions WHERE templateId = NEW.templateId AND isActive = 1)
    WHERE id = NEW.templateId;
END$$

CREATE TRIGGER `trg_update_total_questions_after_update`
AFTER UPDATE ON `kycQuestions`
FOR EACH ROW
BEGIN
    IF OLD.isActive != NEW.isActive OR OLD.templateId != NEW.templateId THEN
        UPDATE kycTemplates
        SET totalQuestions = (SELECT COUNT(*) FROM kycQuestions WHERE templateId = NEW.templateId AND isActive = 1)
        WHERE id = NEW.templateId;

        IF OLD.templateId != NEW.templateId THEN
            UPDATE kycTemplates
            SET totalQuestions = (SELECT COUNT(*) FROM kycQuestions WHERE templateId = OLD.templateId AND isActive = 1)
            WHERE id = OLD.templateId;
        END IF;
    END IF;
END$$

CREATE TRIGGER `trg_update_total_questions_after_delete`
AFTER DELETE ON `kycQuestions`
FOR EACH ROW
BEGIN
    UPDATE kycTemplates
    SET totalQuestions = (SELECT COUNT(*) FROM kycQuestions WHERE templateId = OLD.templateId AND isActive = 1)
    WHERE id = OLD.templateId;
END$$

-- Auto-update totalRules when rules are added/removed
CREATE TRIGGER `trg_update_total_rules_after_insert`
AFTER INSERT ON `kycConditionalRules`
FOR EACH ROW
BEGIN
    UPDATE kycTemplates
    SET totalRules = (SELECT COUNT(*) FROM kycConditionalRules WHERE templateId = NEW.templateId AND isActive = 1)
    WHERE id = NEW.templateId;
END$$

CREATE TRIGGER `trg_update_total_rules_after_update`
AFTER UPDATE ON `kycConditionalRules`
FOR EACH ROW
BEGIN
    IF OLD.isActive != NEW.isActive OR OLD.templateId != NEW.templateId THEN
        UPDATE kycTemplates
        SET totalRules = (SELECT COUNT(*) FROM kycConditionalRules WHERE templateId = NEW.templateId AND isActive = 1)
        WHERE id = NEW.templateId;

        IF OLD.templateId != NEW.templateId THEN
            UPDATE kycTemplates
            SET totalRules = (SELECT COUNT(*) FROM kycConditionalRules WHERE templateId = OLD.templateId AND isActive = 1)
            WHERE id = OLD.templateId;
        END IF;
    END IF;
END$$

CREATE TRIGGER `trg_update_total_rules_after_delete`
AFTER DELETE ON `kycConditionalRules`
FOR EACH ROW
BEGIN
    UPDATE kycTemplates
    SET totalRules = (SELECT COUNT(*) FROM kycConditionalRules WHERE templateId = OLD.templateId AND isActive = 1)
    WHERE id = OLD.templateId;
END$$

DELIMITER ;

-- ============================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================

ALTER TABLE `kycQuestions` ADD INDEX `idx_template_category` (`templateId`, `categoryId`);
ALTER TABLE `kycQuestions` ADD INDEX `idx_type_active` (`questionType`, `isActive`);
ALTER TABLE `clientKycAnswers` ADD INDEX `idx_submission_question` (`submissionId`, `questionId`);
ALTER TABLE `clientKycSubmissions` ADD INDEX `idx_client_status` (`clientId`, `submissionStatus`);

COMMIT;

-- ============================================================
-- END OF KYC MANAGEMENT DATABASE SCHEMA
-- ============================================================
--
-- Installation Order:
-- 1. client_portal_database.sql (must be installed first)
-- 2. admin_system_database.sql (must be installed first)
-- 3. kyc_management_database.sql (this file)
--
-- Dependencies:
-- - clientUsers table (from client_portal_database.sql)
-- - legalDocuments table (from client_portal_database.sql)
-- - adminUsers table (from admin_system_database.sql)
--
-- Features Included:
-- ✓ Template management with country assignments
-- ✓ Question system with categories
-- ✓ Multiple question types (text, number, date, choices, file upload)
-- ✓ Question options for choice questions
-- ✓ Document type specifications for file uploads
-- ✓ Simplified conditional logic rules (jump to question or reject)
-- ✓ Document signature requirements
-- ✓ Client submission tracking
-- ✓ Answer storage for all question types
-- ✓ Document signature tracking
-- ✓ Edit history and activity logs
-- ✓ Comprehensive views for common queries
-- ✓ Auto-updating triggers for counts
-- ✓ Performance indexes
--
-- Notes:
-- - Third-party KYC integration ready (set isThirdPartyEnabled = 1)
-- - Auto-approval feature available (set isAutoApproveEnabled = 1)
-- - Supports multiple languages through template content
-- - File uploads stored as JSON paths in uploadedFiles field
-- - All timestamps use server timezone (recommend UTC)
--
-- ============================================================
