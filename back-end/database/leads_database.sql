-- ============================================================
-- Leads Management Database Schema
-- For Admin Leads Management Page
-- ============================================================
-- Created: 2024-01-15
-- Description: Database tables for managing leads (registered clients)
--              including tags, status tracking, document signatures, and search
-- Note: Uses existing clientUsers table from client_portal_database.sql
-- Naming Convention: camelCase
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================
-- LEAD STATUS AND TRACKING TABLES
-- ============================================================

-- Lead Status History
-- Tracks status changes for each lead (new, contacted, converted)
CREATE TABLE `leadStatusHistory` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `leadId` int(11) UNSIGNED NOT NULL COMMENT 'Reference to clientUsers.id',
  `previousStatus` enum('new','contacted','converted') DEFAULT NULL,
  `newStatus` enum('new','contacted','converted') NOT NULL,
  `changedBy` int(11) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID who changed the status',
  `notes` text DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `leadId` (`leadId`),
  KEY `newStatus` (`newStatus`),
  KEY `createdAt` (`createdAt`),
  CONSTRAINT `leadStatusHistoryLeadFk` FOREIGN KEY (`leadId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lead status change history';

-- ============================================================
-- TAGGING SYSTEM
-- ============================================================

-- Lead Tags
-- Master table for all available tags
CREATE TABLE `leadTags` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tagName` varchar(100) NOT NULL,
  `tagColor` varchar(20) DEFAULT '#f59e0b' COMMENT 'Hex color code for tag display',
  `description` varchar(500) DEFAULT NULL,
  `isSystemTag` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'System tags cannot be deleted',
  `createdBy` int(11) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tagName` (`tagName`),
  KEY `isSystemTag` (`isSystemTag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master lead tags table';

-- Insert some default system tags
INSERT INTO `leadTags` (`tagName`, `tagColor`, `description`, `isSystemTag`) VALUES
('New Lead', '#fbbf24', 'Newly registered lead', 1),
('VIP', '#8b5cf6', 'VIP customer', 1),
('Urgent', '#ef4444', 'Requires urgent attention', 1),
('Premium', '#3b82f6', 'Premium tier customer', 1),
('Hot Lead', '#f97316', 'High potential lead', 1),
('Follow-up', '#10b981', 'Needs follow-up', 1),
('High Value', '#6366f1', 'High value potential', 1);

-- Lead Tag Assignments
-- Associates tags with specific leads
CREATE TABLE `leadTagAssignments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `leadId` int(11) UNSIGNED NOT NULL COMMENT 'Reference to clientUsers.id',
  `tagId` int(11) UNSIGNED NOT NULL,
  `assignedBy` int(11) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `assignedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniqueLeadTag` (`leadId`, `tagId`),
  KEY `leadId` (`leadId`),
  KEY `tagId` (`tagId`),
  CONSTRAINT `leadTagAssignmentsLeadFk` FOREIGN KEY (`leadId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leadTagAssignmentsTagFk` FOREIGN KEY (`tagId`) REFERENCES `leadTags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lead and tag associations';

-- Search Tags Configuration
-- Quick search tags displayed on the leads page for filtering
CREATE TABLE `searchTags` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tagName` varchar(100) NOT NULL COMMENT 'Display name of the search tag',
  `searchKeywords` varchar(500) NOT NULL COMMENT 'Keywords to search for when tag is clicked',
  `displayOrder` int(11) NOT NULL DEFAULT 0,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `createdBy` int(11) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `isActive` (`isActive`),
  KEY `displayOrder` (`displayOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quick search tags configuration';

-- Insert default search tags
INSERT INTO `searchTags` (`tagName`, `searchKeywords`, `displayOrder`) VALUES
('US Clients', 'United', 1),
('New Leads', 'new', 2),
('This Year', '2024', 3);

-- ============================================================
-- LEGAL DOCUMENTS SIGNATURES
-- ============================================================

-- Legal Documents Master Table
-- Stores all legal documents (Terms, Privacy Policy, Risk Disclosure, etc.)
CREATE TABLE IF NOT EXISTS `legalDocuments` (
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

-- Insert default legal documents
INSERT IGNORE INTO `legalDocuments` (`documentType`, `title`, `content`, `languageCode`, `displayOrder`) VALUES
('terms_of_service', 'Terms of Service', '<h2>Terms of Service</h2><p>By registering on our platform, you agree to comply with all applicable laws and regulations.</p><p>This is a placeholder document. Please update with your actual Terms of Service.</p>', 'en', 1),
('privacy_policy', 'Privacy Policy', '<h2>Privacy Policy</h2><p>We respect your privacy and are committed to protecting your personal data.</p><p>This is a placeholder document. Please update with your actual Privacy Policy.</p>', 'en', 2),
('risk_disclosure', 'Risk Disclosure', '<h2>Risk Disclosure</h2><p>Trading financial instruments carries a high level of risk and may not be suitable for all investors.</p><p>This is a placeholder document. Please update with your actual Risk Disclosure.</p>', 'en', 3);

-- Legal Document Signatures
-- Tracks which legal documents each lead has signed during registration
CREATE TABLE `legalDocumentSignatures` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `leadId` int(11) UNSIGNED NOT NULL COMMENT 'Reference to clientUsers.id',
  `documentId` int(11) UNSIGNED NOT NULL COMMENT 'Reference to legalDocuments.id',
  `documentType` varchar(100) NOT NULL COMMENT 'e.g., terms_of_service, privacy_policy, risk_disclosure',
  `documentVersion` varchar(50) DEFAULT NULL,
  `signedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ipAddress` varchar(45) DEFAULT NULL,
  `userAgent` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `leadId` (`leadId`),
  KEY `documentId` (`documentId`),
  KEY `documentType` (`documentType`),
  KEY `signedAt` (`signedAt`),
  CONSTRAINT `legalDocumentSignaturesLeadFk` FOREIGN KEY (`leadId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `legalDocumentSignaturesDocFk` FOREIGN KEY (`documentId`) REFERENCES `legalDocuments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Legal documents signed by leads';

-- ============================================================
-- LEAD ACTIVITY AND AUDIT LOG
-- ============================================================

-- Lead Activity Log (Admin Perspective)
-- Tracks admin actions performed on leads
CREATE TABLE `leadActivityLog` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `leadId` int(11) UNSIGNED NOT NULL COMMENT 'Reference to clientUsers.id',
  `activityType` varchar(100) NOT NULL COMMENT 'status_change, tag_added, tag_removed, info_updated, assigned, exported, etc.',
  `description` text NOT NULL,
  `performedBy` int(11) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `metadata` text DEFAULT NULL COMMENT 'JSON data with additional info',
  `ipAddress` varchar(45) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `leadId` (`leadId`),
  KEY `activityType` (`activityType`),
  KEY `performedBy` (`performedBy`),
  KEY `createdAt` (`createdAt`),
  CONSTRAINT `leadActivityLogLeadFk` FOREIGN KEY (`leadId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admin actions on leads audit log';

-- ============================================================
-- LEAD EDITABLE INFORMATION
-- ============================================================

-- Lead Edit History
-- Tracks changes made to lead information by admins
CREATE TABLE `leadEditHistory` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `leadId` int(11) UNSIGNED NOT NULL COMMENT 'Reference to clientUsers.id',
  `fieldName` varchar(100) NOT NULL COMMENT 'Field that was edited',
  `oldValue` text DEFAULT NULL,
  `newValue` text DEFAULT NULL,
  `editedBy` int(11) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `ipAddress` varchar(45) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `leadId` (`leadId`),
  KEY `fieldName` (`fieldName`),
  KEY `editedBy` (`editedBy`),
  KEY `createdAt` (`createdAt`),
  CONSTRAINT `leadEditHistoryLeadFk` FOREIGN KEY (`leadId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lead information edit history';

-- ============================================================
-- KYC STATUS TRACKING
-- ============================================================

-- KYC Status
-- Extended KYC information for leads (beyond basic status in clientUsers)
CREATE TABLE `leadKycStatus` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `leadId` int(11) UNSIGNED NOT NULL COMMENT 'Reference to clientUsers.id',
  `kycStatus` enum('not_started','in_progress','pending_review','approved','rejected') NOT NULL DEFAULT 'not_started',
  `documentSubmittedAt` datetime DEFAULT NULL,
  `reviewedAt` datetime DEFAULT NULL,
  `reviewedBy` int(11) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `rejectionReason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leadId` (`leadId`),
  KEY `kycStatus` (`kycStatus`),
  CONSTRAINT `leadKycStatusLeadFk` FOREIGN KEY (`leadId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Extended KYC status tracking';

-- ============================================================
-- BULK OPERATIONS TRACKING
-- ============================================================

-- Bulk Operations Log
-- Tracks bulk operations performed on leads
CREATE TABLE `leadBulkOperations` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `operationType` enum('bulk_assign','bulk_tag','bulk_export','bulk_status_change') NOT NULL,
  `leadIds` text NOT NULL COMMENT 'JSON array of lead IDs',
  `totalLeads` int(11) NOT NULL,
  `operationData` text DEFAULT NULL COMMENT 'JSON data specific to operation (e.g., assigned rep, tag name)',
  `performedBy` int(11) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `ipAddress` varchar(45) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `operationType` (`operationType`),
  KEY `performedBy` (`performedBy`),
  KEY `createdAt` (`createdAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bulk operations audit log';

-- ============================================================
-- ADDITIONAL INDEXES FOR PERFORMANCE
-- ============================================================

-- Add indexes to clientUsers for lead management queries
-- Note: These indexes may already exist. If you get "Duplicate key name" error,
-- it means the indexes are already created and you can safely skip this section.

-- Check if indexes exist before creating them:
-- Run this query first to see existing indexes:
-- SHOW INDEX FROM clientUsers;

-- If indexes don't exist, uncomment and run these lines:
-- ALTER TABLE `clientUsers` ADD INDEX `idxStatusCreated` (`status`, `createdAt`);
-- ALTER TABLE `clientUsers` ADD INDEX `idxCountry` (`country`);
-- ALTER TABLE `clientUsers` ADD INDEX `idxEmailVerified` (`emailVerified`);

-- ============================================================
-- VIEWS FOR CONVENIENCE
-- ============================================================

-- Lead Summary View
-- Combines lead information with tag counts (without assignment info)
-- Note: For assignment info, use vw_lead_summary_with_assignment in sales_assignment.sql
-- Note: KYC状态为approved的用户不会在此视图中显示（已转为正式客户）
CREATE OR REPLACE VIEW `vw_lead_summary` AS
SELECT
  cu.id AS leadId,
  cu.email,
  cu.firstName,
  cu.lastName,
  cu.phone,
  cu.country,
  cu.status,
  cu.emailVerified,
  cu.registrationIp,
  cu.createdAt AS registrationDate,
  cu.lastLoginAt,
  (SELECT COUNT(*) FROM leadTagAssignments WHERE leadId = cu.id) AS tagCount,
  (SELECT COUNT(*) FROM legalDocumentSignatures WHERE leadId = cu.id) AS signedDocumentsCount,
  (SELECT kycStatus FROM leadKycStatus WHERE leadId = cu.id) AS kycStatus
FROM clientUsers cu
WHERE (cu.kycStatus IS NULL OR cu.kycStatus != 'approved')
ORDER BY cu.createdAt DESC;

COMMIT;

-- ============================================================
-- END OF LEADS MANAGEMENT DATABASE SCHEMA
-- ============================================================
