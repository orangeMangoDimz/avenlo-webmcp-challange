-- ============================================================
-- Utrada CRM - KYC Settings Database
-- ============================================================
-- Created: 2024-12-20
-- Description: Database tables for KYC notification display settings
--              and client-facing KYC communication configuration
-- Related Pages: KYCSettings.html
-- Naming Convention: camelCase
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================
-- KYC NOTICE DISPLAY SETTINGS
-- ============================================================

-- KYC Notice Settings
-- Stores configuration for KYC notice card displayed on client dashboard
CREATE TABLE `kycNoticeSettings` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `settingKey` varchar(100) NOT NULL UNIQUE COMMENT 'Unique identifier for setting type',
  `isEnabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Enable/disable this notice display',

  -- Notice Card Content
  `noticeTitle` varchar(200) NOT NULL DEFAULT 'Complete Your KYC Verification' COMMENT 'Main heading on KYC notice card',
  `noticeSubtitle` varchar(255) DEFAULT 'Verify your identity to unlock full trading capabilities' COMMENT 'Secondary text below title',
  `noticeDescription` text DEFAULT NULL COMMENT 'Main content text explaining KYC requirement',

  -- Requirements Section
  `requirementsTitle` varchar(200) NOT NULL DEFAULT 'Required Documents' COMMENT 'Heading for required documents section',
  `verificationTimeNotice` text DEFAULT NULL COMMENT 'Information about expected verification processing time',

  -- Action Buttons
  `primaryButtonText` varchar(100) NOT NULL DEFAULT 'Start Verification Now' COMMENT 'Text for main action button',
  `primaryButtonAction` varchar(200) DEFAULT '/client/kyc-verification' COMMENT 'URL or action for primary button',
  `secondaryButtonText` varchar(100) NOT NULL DEFAULT 'Learn More' COMMENT 'Text for secondary action button',
  `secondaryButtonAction` varchar(200) DEFAULT '/client/kyc-info' COMMENT 'URL or action for secondary button',

  -- Display Configuration
  `displayPosition` enum('top','center','bottom') NOT NULL DEFAULT 'top' COMMENT 'Position on dashboard',
  `displayPriority` int(11) NOT NULL DEFAULT 1 COMMENT 'Display priority (1=highest)',
  `isDismissible` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Can user dismiss the notice',
  `showIcon` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Show icon on notice card',
  `iconClass` varchar(100) DEFAULT 'fas fa-id-card' COMMENT 'Font Awesome icon class',
  `backgroundColor` varchar(50) DEFAULT '#e3efec' COMMENT 'Card background color',
  `borderColor` varchar(50) DEFAULT '#174f46' COMMENT 'Card border color',

  -- Metadata
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID who last updated',

  PRIMARY KEY (`id`),
  KEY `isEnabled` (`isEnabled`),
  KEY `displayPriority` (`displayPriority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='KYC Notice Display Settings';

-- Insert default KYC notice settings
INSERT INTO `kycNoticeSettings` (
  `settingKey`,
  `isEnabled`,
  `noticeTitle`,
  `noticeSubtitle`,
  `noticeDescription`,
  `requirementsTitle`,
  `verificationTimeNotice`,
  `primaryButtonText`,
  `primaryButtonAction`,
  `secondaryButtonText`,
  `secondaryButtonAction`,
  `displayPosition`,
  `displayPriority`,
  `isDismissible`,
  `showIcon`,
  `iconClass`,
  `backgroundColor`,
  `borderColor`
) VALUES (
  'default_kyc_notice',
  1,
  'Complete Your KYC Verification',
  'Verify your identity to unlock full trading capabilities',
  'Your account is currently pending verification. To start trading and access all platform features, you need to complete the KYC (Know Your Customer) verification process. This is a one-time requirement to comply with regulatory standards and ensure the security of your account.',
  'Required Documents',
  'The KYC verification process typically takes 1-2 business days. You will receive an email notification once your documents have been reviewed. In the meantime, you can explore the platform and familiarize yourself with our trading tools.',
  'Start Verification Now',
  '/client/kyc-verification',
  'Learn More',
  '/client/kyc-info',
  'top',
  1,
  0,
  1,
  'fas fa-id-card',
  '#e3efec',
  '#174f46'
);

-- ============================================================
-- KYC STATUS MESSAGE TEMPLATES
-- ============================================================

-- KYC Status Message Templates
-- Customizable messages for different KYC verification statuses
CREATE TABLE `kycStatusMessageTemplates` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `statusType` enum('pending','under_review','approved','rejected','incomplete','expired','resubmit_required') NOT NULL COMMENT 'KYC verification status',
  `messageTitle` varchar(200) NOT NULL COMMENT 'Status message title',
  `messageContent` text NOT NULL COMMENT 'Detailed status message',
  `messageType` enum('info','success','warning','error') NOT NULL DEFAULT 'info' COMMENT 'Message display type',
  `showActionButton` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Show action button',
  `actionButtonText` varchar(100) DEFAULT NULL COMMENT 'Action button text',
  `actionButtonUrl` varchar(200) DEFAULT NULL COMMENT 'Action button URL',
  `iconClass` varchar(100) DEFAULT 'fas fa-info-circle' COMMENT 'Font Awesome icon class',
  `isActive` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Is this template active',
  `displayOrder` int(11) NOT NULL DEFAULT 0 COMMENT 'Display order',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `statusType` (`statusType`),
  KEY `isActive` (`isActive`),
  KEY `displayOrder` (`displayOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='KYC Status Message Templates';

-- Insert default status message templates
INSERT INTO `kycStatusMessageTemplates` (
  `statusType`,
  `messageTitle`,
  `messageContent`,
  `messageType`,
  `showActionButton`,
  `actionButtonText`,
  `actionButtonUrl`,
  `iconClass`,
  `displayOrder`
) VALUES
('pending',
  'KYC Verification Pending',
  'Your KYC verification is pending. Please complete the required steps to activate your account.',
  'warning',
  1,
  'Complete Verification',
  '/client/kyc-verification',
  'fas fa-clock',
  1
),
('under_review',
  'KYC Under Review',
  'Your documents are currently being reviewed by our compliance team. This typically takes 1-2 business days. We will notify you once the review is complete.',
  'info',
  0,
  NULL,
  NULL,
  'fas fa-hourglass-half',
  2
),
('approved',
  'KYC Verification Approved',
  'Congratulations! Your KYC verification has been approved. You now have full access to all trading features.',
  'success',
  1,
  'Start Trading',
  '/client/trading',
  'fas fa-check-circle',
  3
),
('rejected',
  'KYC Verification Rejected',
  'Unfortunately, your KYC verification has been rejected. Please review the reasons and resubmit your documents.',
  'error',
  1,
  'View Details',
  '/client/kyc-status',
  'fas fa-times-circle',
  4
),
('incomplete',
  'KYC Verification Incomplete',
  'Your KYC submission is incomplete. Please provide all required documents and information to proceed.',
  'warning',
  1,
  'Complete Submission',
  '/client/kyc-verification',
  'fas fa-exclamation-triangle',
  5
),
('expired',
  'KYC Verification Expired',
  'Your KYC verification has expired. Please submit updated documents to maintain your account status.',
  'error',
  1,
  'Update Documents',
  '/client/kyc-verification',
  'fas fa-calendar-times',
  6
),
('resubmit_required',
  'Resubmission Required',
  'Additional documents or information are required. Please review the feedback and resubmit your KYC application.',
  'warning',
  1,
  'Resubmit Documents',
  '/client/kyc-verification',
  'fas fa-redo',
  7
);

-- ============================================================
-- KYC REQUIREMENT ITEMS
-- ============================================================

-- KYC Requirement Items
-- Defines the list of required documents/items shown in the notice
CREATE TABLE `kycRequirementItems` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `noticeSettingId` int(11) UNSIGNED NOT NULL COMMENT 'Reference to kycNoticeSettings',
  `itemTitle` varchar(200) NOT NULL COMMENT 'Requirement item title',
  `itemDescription` text DEFAULT NULL COMMENT 'Detailed description of requirement',
  `itemType` enum('document','information','action') NOT NULL DEFAULT 'document' COMMENT 'Type of requirement',
  `iconClass` varchar(100) DEFAULT 'fas fa-file-alt' COMMENT 'Font Awesome icon class',
  `isRequired` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Is this requirement mandatory',
  `displayOrder` int(11) NOT NULL DEFAULT 0,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `noticeSettingId` (`noticeSettingId`),
  KEY `isActive` (`isActive`),
  KEY `displayOrder` (`displayOrder`),
  CONSTRAINT `kycRequirementItemsFk` FOREIGN KEY (`noticeSettingId`) REFERENCES `kycNoticeSettings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='KYC Requirement Items';

-- Insert default requirement items
INSERT INTO `kycRequirementItems` (
  `noticeSettingId`,
  `itemTitle`,
  `itemDescription`,
  `itemType`,
  `iconClass`,
  `isRequired`,
  `displayOrder`
) VALUES
(1, 'Government-issued ID', 'Valid passport, driver\'s license, or national ID card', 'document', 'fas fa-id-card', 1, 1),
(1, 'Proof of Address', 'Recent utility bill, bank statement, or government correspondence (less than 3 months old)', 'document', 'fas fa-home', 1, 2),
(1, 'Selfie Photo', 'Clear photo of yourself holding your ID document', 'document', 'fas fa-camera', 1, 3),
(1, 'Personal Information', 'Complete personal details including full name, date of birth, and contact information', 'information', 'fas fa-user-circle', 1, 4),
(1, 'Financial Information', 'Employment status, income details, and source of funds', 'information', 'fas fa-dollar-sign', 1, 5);

-- ============================================================
-- KYC EMAIL NOTIFICATION TEMPLATES
-- ============================================================

-- KYC Email Notification Templates
-- Email templates for various KYC-related notifications
CREATE TABLE `kycEmailNotificationTemplates` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `templateKey` varchar(100) NOT NULL UNIQUE COMMENT 'Unique identifier for template',
  `templateName` varchar(200) NOT NULL COMMENT 'Human-readable template name',
  `emailSubject` varchar(255) NOT NULL COMMENT 'Email subject line',
  `emailBody` text NOT NULL COMMENT 'Email body content (supports HTML)',
  `emailType` enum('submission','approval','rejection','under_review','reminder','expiry','resubmit') NOT NULL COMMENT 'Type of email notification',
  `triggerEvent` varchar(100) NOT NULL COMMENT 'Event that triggers this email',
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `sendDelay` int(11) DEFAULT 0 COMMENT 'Delay in minutes before sending (0 = immediate)',
  `ccEmails` text DEFAULT NULL COMMENT 'Additional CC email addresses (JSON array)',
  `attachmentRequired` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Should include attachments',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updatedBy` bigint(20) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `emailType` (`emailType`),
  KEY `isActive` (`isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='KYC Email Notification Templates';

-- Insert default email templates
INSERT INTO `kycEmailNotificationTemplates` (
  `templateKey`,
  `templateName`,
  `emailSubject`,
  `emailBody`,
  `emailType`,
  `triggerEvent`,
  `isActive`,
  `sendDelay`
) VALUES
('kyc_submission_confirmation',
  'KYC Submission Confirmation',
  'Your KYC Verification Has Been Submitted',
  '<p>Dear {{clientName}},</p><p>Thank you for submitting your KYC verification documents. We have received your application and our compliance team will review it shortly.</p><p><strong>What happens next?</strong></p><ul><li>Our team will verify your documents within 1-2 business days</li><li>You will receive an email notification once the review is complete</li><li>You can track your verification status in your dashboard</li></ul><p>If you have any questions, please don\'t hesitate to contact our support team.</p><p>Best regards,<br>Utrada CRM Team</p>',
  'submission',
  'kyc_submitted',
  1,
  0
),
('kyc_approval_notification',
  'KYC Approval Notification',
  'Your KYC Verification Has Been Approved!',
  '<p>Dear {{clientName}},</p><p>Great news! Your KYC verification has been approved.</p><p><strong>What this means for you:</strong></p><ul><li>Full access to all trading features</li><li>Higher transaction limits</li><li>Priority customer support</li><li>Access to advanced trading tools</li></ul><p>You can now start trading with complete confidence.</p><p><a href="{{dashboardUrl}}">Go to Dashboard</a></p><p>Best regards,<br>Utrada CRM Team</p>',
  'approval',
  'kyc_approved',
  1,
  0
),
('kyc_rejection_notification',
  'KYC Rejection Notification',
  'Action Required: KYC Verification Issue',
  '<p>Dear {{clientName}},</p><p>We regret to inform you that we were unable to approve your KYC verification at this time.</p><p><strong>Reason for rejection:</strong></p><p>{{rejectionReason}}</p><p><strong>Next steps:</strong></p><ul><li>Review the feedback provided</li><li>Prepare the required documents</li><li>Resubmit your KYC application</li></ul><p><a href="{{resubmitUrl}}">Resubmit Documents</a></p><p>If you need assistance, our support team is here to help.</p><p>Best regards,<br>Utrada CRM Team</p>',
  'rejection',
  'kyc_rejected',
  1,
  0
),
('kyc_under_review_notification',
  'KYC Under Review',
  'Your KYC Verification Is Under Review',
  '<p>Dear {{clientName}},</p><p>Your KYC documents are currently being reviewed by our compliance team.</p><p><strong>Review timeline:</strong></p><ul><li>Standard review: 1-2 business days</li><li>Complex cases: up to 5 business days</li><li>You will be notified as soon as the review is complete</li></ul><p>You can track your verification status in your dashboard.</p><p><a href="{{statusUrl}}">Check Status</a></p><p>Thank you for your patience.</p><p>Best regards,<br>Utrada CRM Team</p>',
  'under_review',
  'kyc_under_review',
  1,
  60
),
('kyc_expiry_reminder',
  'KYC Expiry Reminder',
  'Your KYC Verification Will Expire Soon',
  '<p>Dear {{clientName}},</p><p>This is a friendly reminder that your KYC verification will expire on <strong>{{expiryDate}}</strong>.</p><p><strong>To maintain uninterrupted access:</strong></p><ul><li>Update your documents before the expiry date</li><li>Ensure all information is current and accurate</li><li>Submit updated proof of address if required</li></ul><p><a href="{{updateUrl}}">Update Documents</a></p><p>Best regards,<br>Utrada CRM Team</p>',
  'expiry',
  'kyc_expiring_soon',
  1,
  0
),
('kyc_resubmit_request',
  'KYC Resubmission Required',
  'Additional Information Required for KYC Verification',
  '<p>Dear {{clientName}},</p><p>We need additional information to complete your KYC verification.</p><p><strong>Required items:</strong></p><p>{{requiredItems}}</p><p><strong>Please note:</strong></p><ul><li>Submit the requested documents within 7 days</li><li>Ensure all documents are clear and legible</li><li>Documents must be recent and valid</li></ul><p><a href="{{resubmitUrl}}">Submit Documents</a></p><p>If you have questions, please contact our support team.</p><p>Best regards,<br>Utrada CRM Team</p>',
  'resubmit',
  'kyc_resubmit_required',
  1,
  0
);

-- ============================================================
-- KYC SETTINGS CHANGE LOG
-- ============================================================

-- KYC Settings Change Log
-- Audit trail for changes to KYC settings
CREATE TABLE `kycSettingsChangeLog` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `settingType` enum('notice_settings','status_messages','requirements','email_templates') NOT NULL COMMENT 'Type of setting changed',
  `settingId` int(11) UNSIGNED NOT NULL COMMENT 'ID of the specific setting changed',
  `fieldName` varchar(100) NOT NULL COMMENT 'Name of field that was changed',
  `oldValue` text DEFAULT NULL COMMENT 'Previous value',
  `newValue` text DEFAULT NULL COMMENT 'New value',
  `changeReason` text DEFAULT NULL COMMENT 'Reason for change (optional)',
  `changedBy` bigint(20) UNSIGNED NOT NULL COMMENT 'Admin user ID',
  `changedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ipAddress` varchar(45) DEFAULT NULL COMMENT 'IP address of admin who made change',
  `userAgent` text DEFAULT NULL COMMENT 'Browser user agent',
  PRIMARY KEY (`id`),
  KEY `settingType` (`settingType`),
  KEY `settingId` (`settingId`),
  KEY `changedBy` (`changedBy`),
  KEY `changedAt` (`changedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='KYC Settings Change Audit Log';

-- ============================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================

-- Additional indexes for common queries
CREATE INDEX `idx_notice_active` ON `kycNoticeSettings` (`isEnabled`, `displayPriority`);
CREATE INDEX `idx_status_template_active` ON `kycStatusMessageTemplates` (`isActive`, `statusType`);
CREATE INDEX `idx_email_template_active` ON `kycEmailNotificationTemplates` (`isActive`, `emailType`);
CREATE INDEX `idx_requirement_active` ON `kycRequirementItems` (`isActive`, `displayOrder`);

COMMIT;

-- ============================================================
-- END OF KYC SETTINGS DATABASE
-- ============================================================
