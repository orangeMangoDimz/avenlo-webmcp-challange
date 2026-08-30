-- ============================================================
-- Sales Assignment Database Schema
-- For Lead Sales Assignment and Management
-- ============================================================
-- Created: 2024-01-15
-- Description: Database tables for assigning leads to sales representatives,
--              tracking assignment history, performance, and follow-up notes
-- Note: This is a separate file for future expansion of sales features
-- Naming Convention: camelCase
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================
-- SALES REPRESENTATIVES MANAGEMENT
-- ============================================================

-- Sales Representatives
-- Master table for all sales representatives
CREATE TABLE `salesRepresentatives` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `adminUserId` int(11) UNSIGNED DEFAULT NULL COMMENT 'Link to admin user account if applicable',
  `repCode` varchar(50) NOT NULL COMMENT 'Unique identifier (e.g., john-williams)',
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL COMMENT 'Sales department or team',
  `position` varchar(100) DEFAULT NULL COMMENT 'Job title',
  `languagesSpoken` varchar(500) DEFAULT NULL COMMENT 'Comma-separated list of languages',
  `specialization` varchar(500) DEFAULT NULL COMMENT 'Areas of specialization (e.g., forex, commodities)',
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `maxLeadCapacity` int(11) DEFAULT NULL COMMENT 'Maximum number of active leads',
  `currentLeadCount` int(11) NOT NULL DEFAULT 0 COMMENT 'Current number of assigned leads',
  `hireDate` date DEFAULT NULL,
  `profilePicture` varchar(500) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `repCode` (`repCode`),
  UNIQUE KEY `email` (`email`),
  KEY `isActive` (`isActive`),
  KEY `department` (`department`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sales representatives master table';

-- Insert sample sales representatives (matching the HTML demo data)
INSERT INTO `salesRepresentatives` (`repCode`, `firstName`, `lastName`, `email`, `phone`, `isActive`, `maxLeadCapacity`) VALUES
('john-williams', 'John', 'Williams', 'john.williams@bdx.com', '+1-555-0101', 1, 50),
('sarah-davis', 'Sarah', 'Davis', 'sarah.davis@bdx.com', '+1-555-0102', 1, 50),
('michael-brown', 'Michael', 'Brown', 'michael.brown@bdx.com', '+1-555-0103', 1, 50),
('emily-taylor', 'Emily', 'Taylor', 'emily.taylor@bdx.com', '+1-555-0104', 1, 50),
('david-wilson', 'David', 'Wilson', 'david.wilson@bdx.com', '+1-555-0105', 1, 50);

-- ============================================================
-- LEAD ASSIGNMENTS (CURRENT STATE)
-- ============================================================

-- Lead Assignments
-- Current assignment of leads to sales representatives
-- Only one active assignment per lead at a time
CREATE TABLE `leadAssignments` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `leadId` int(11) UNSIGNED NOT NULL COMMENT 'Reference to clientUsers.id',
  `salesRepId` int(11) UNSIGNED NOT NULL,
  `assignmentStatus` enum('pending','active','completed','cancelled') NOT NULL DEFAULT 'active',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `assignedBy` int(11) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID who made the assignment',
  `assignedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `firstContactedAt` datetime DEFAULT NULL,
  `lastContactedAt` datetime DEFAULT NULL,
  `completedAt` datetime DEFAULT NULL,
  `completionNotes` text DEFAULT NULL,
  `expectedCloseDate` date DEFAULT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Only one active assignment per lead',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `leadId` (`leadId`),
  KEY `salesRepId` (`salesRepId`),
  KEY `assignmentStatus` (`assignmentStatus`),
  KEY `isActive` (`isActive`),
  KEY `assignedAt` (`assignedAt`),
  CONSTRAINT `leadAssignmentsLeadFk` FOREIGN KEY (`leadId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leadAssignmentsSalesRepFk` FOREIGN KEY (`salesRepId`) REFERENCES `salesRepresentatives` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Current lead assignments to sales reps';

-- Add unique constraint for active assignments (only one active assignment per lead)
ALTER TABLE `leadAssignments`
  ADD UNIQUE KEY `uniqueActiveLeadAssignment` (`leadId`, `isActive`);

-- ============================================================
-- ASSIGNMENT HISTORY AND TRACKING
-- ============================================================

-- Lead Assignment History
-- Complete history of all assignment changes for audit trail
CREATE TABLE `leadAssignmentHistory` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `leadId` int(11) UNSIGNED NOT NULL COMMENT 'Reference to clientUsers.id',
  `assignmentId` int(11) UNSIGNED DEFAULT NULL COMMENT 'Reference to leadAssignments.id',
  `actionType` enum('assigned','reassigned','unassigned','status_changed','completed','cancelled') NOT NULL,
  `previousSalesRepId` int(11) UNSIGNED DEFAULT NULL,
  `newSalesRepId` int(11) UNSIGNED DEFAULT NULL,
  `previousStatus` varchar(50) DEFAULT NULL,
  `newStatus` varchar(50) DEFAULT NULL,
  `reason` text DEFAULT NULL COMMENT 'Reason for the change',
  `performedBy` int(11) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `metadata` text DEFAULT NULL COMMENT 'JSON data with additional info',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `leadId` (`leadId`),
  KEY `assignmentId` (`assignmentId`),
  KEY `actionType` (`actionType`),
  KEY `newSalesRepId` (`newSalesRepId`),
  KEY `createdAt` (`createdAt`),
  CONSTRAINT `leadAssignmentHistoryLeadFk` FOREIGN KEY (`leadId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leadAssignmentHistoryAssignmentFk` FOREIGN KEY (`assignmentId`) REFERENCES `leadAssignments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Complete assignment change history';

-- ============================================================
-- FOLLOW-UP NOTES AND COMMUNICATIONS
-- ============================================================

-- Lead Assignment Notes
-- Follow-up notes and communication records
CREATE TABLE `leadAssignmentNotes` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignmentId` int(11) UNSIGNED NOT NULL,
  `leadId` int(11) UNSIGNED NOT NULL COMMENT 'Reference to clientUsers.id',
  `salesRepId` int(11) UNSIGNED NOT NULL,
  `noteType` enum('general','call','email','meeting','follow_up','reminder','escalation') NOT NULL DEFAULT 'general',
  `subject` varchar(500) DEFAULT NULL,
  `noteContent` text NOT NULL,
  `contactMethod` enum('phone','email','whatsapp','telegram','wechat','in_person','other') DEFAULT NULL,
  `contactDuration` int(11) DEFAULT NULL COMMENT 'Duration in minutes for calls/meetings',
  `nextFollowUpDate` date DEFAULT NULL,
  `isImportant` tinyint(1) NOT NULL DEFAULT 0,
  `attachments` text DEFAULT NULL COMMENT 'JSON array of file paths',
  `createdBy` int(11) UNSIGNED DEFAULT NULL COMMENT 'User ID who created the note',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `assignmentId` (`assignmentId`),
  KEY `leadId` (`leadId`),
  KEY `salesRepId` (`salesRepId`),
  KEY `noteType` (`noteType`),
  KEY `nextFollowUpDate` (`nextFollowUpDate`),
  KEY `createdAt` (`createdAt`),
  CONSTRAINT `leadAssignmentNotesAssignmentFk` FOREIGN KEY (`assignmentId`) REFERENCES `leadAssignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leadAssignmentNotesLeadFk` FOREIGN KEY (`leadId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leadAssignmentNotesSalesRepFk` FOREIGN KEY (`salesRepId`) REFERENCES `salesRepresentatives` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Follow-up notes and communications';

-- ============================================================
-- BULK ASSIGNMENT OPERATIONS
-- ============================================================

-- Bulk Assignment Operations
-- Tracks bulk assignment operations for audit
CREATE TABLE `bulkAssignmentOperations` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `salesRepId` int(11) UNSIGNED NOT NULL,
  `leadIds` text NOT NULL COMMENT 'JSON array of lead IDs',
  `totalLeads` int(11) NOT NULL,
  `successfulAssignments` int(11) NOT NULL DEFAULT 0,
  `failedAssignments` int(11) NOT NULL DEFAULT 0,
  `assignmentNotes` text DEFAULT NULL COMMENT 'Bulk assignment notes',
  `performedBy` int(11) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID',
  `ipAddress` varchar(45) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `salesRepId` (`salesRepId`),
  KEY `performedBy` (`performedBy`),
  KEY `createdAt` (`createdAt`),
  CONSTRAINT `bulkAssignmentOperationsSalesRepFk` FOREIGN KEY (`salesRepId`) REFERENCES `salesRepresentatives` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bulk assignment operations audit';

-- ============================================================
-- SALES PERFORMANCE TRACKING (FOR FUTURE EXPANSION)
-- ============================================================

-- Sales Rep Performance Statistics
-- Aggregated performance metrics per sales representative
CREATE TABLE `salesRepPerformance` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `salesRepId` int(11) UNSIGNED NOT NULL,
  `periodType` enum('daily','weekly','monthly','quarterly','yearly') NOT NULL,
  `periodStart` date NOT NULL,
  `periodEnd` date NOT NULL,
  `totalLeadsAssigned` int(11) NOT NULL DEFAULT 0,
  `leadsContacted` int(11) NOT NULL DEFAULT 0,
  `leadsConverted` int(11) NOT NULL DEFAULT 0,
  `conversionRate` decimal(5,2) DEFAULT NULL COMMENT 'Percentage',
  `averageResponseTime` int(11) DEFAULT NULL COMMENT 'Minutes',
  `totalCalls` int(11) NOT NULL DEFAULT 0,
  `totalEmails` int(11) NOT NULL DEFAULT 0,
  `totalMeetings` int(11) NOT NULL DEFAULT 0,
  `activeLeadsCount` int(11) NOT NULL DEFAULT 0,
  `completedLeadsCount` int(11) NOT NULL DEFAULT 0,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniquePeriod` (`salesRepId`, `periodType`, `periodStart`),
  KEY `salesRepId` (`salesRepId`),
  KEY `periodType` (`periodType`),
  KEY `periodStart` (`periodStart`),
  CONSTRAINT `salesRepPerformanceSalesRepFk` FOREIGN KEY (`salesRepId`) REFERENCES `salesRepresentatives` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sales rep performance statistics';

-- ============================================================
-- ASSIGNMENT REMINDERS AND TASKS
-- ============================================================

-- Assignment Reminders
-- Scheduled reminders for follow-ups and tasks
CREATE TABLE `assignmentReminders` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignmentId` int(11) UNSIGNED NOT NULL,
  `leadId` int(11) UNSIGNED NOT NULL,
  `salesRepId` int(11) UNSIGNED NOT NULL,
  `reminderType` enum('follow_up','callback','email','meeting','deadline','custom') NOT NULL,
  `reminderTitle` varchar(500) NOT NULL,
  `reminderDescription` text DEFAULT NULL,
  `reminderDate` datetime NOT NULL,
  `isCompleted` tinyint(1) NOT NULL DEFAULT 0,
  `completedAt` datetime DEFAULT NULL,
  `completedBy` int(11) UNSIGNED DEFAULT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `notificationSent` tinyint(1) NOT NULL DEFAULT 0,
  `notificationSentAt` datetime DEFAULT NULL,
  `createdBy` int(11) UNSIGNED DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `assignmentId` (`assignmentId`),
  KEY `leadId` (`leadId`),
  KEY `salesRepId` (`salesRepId`),
  KEY `reminderDate` (`reminderDate`),
  KEY `isCompleted` (`isCompleted`),
  CONSTRAINT `assignmentRemindersAssignmentFk` FOREIGN KEY (`assignmentId`) REFERENCES `leadAssignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assignmentRemindersLeadFk` FOREIGN KEY (`leadId`) REFERENCES `clientUsers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assignmentRemindersSalesRepFk` FOREIGN KEY (`salesRepId`) REFERENCES `salesRepresentatives` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Assignment reminders and tasks';

-- ============================================================
-- VIEWS FOR CONVENIENCE
-- ============================================================

-- Active Assignments Summary View
CREATE OR REPLACE VIEW `vw_active_assignments` AS
SELECT
  la.id AS assignmentId,
  la.leadId,
  cu.firstName AS leadFirstName,
  cu.lastName AS leadLastName,
  cu.email AS leadEmail,
  cu.phone AS leadPhone,
  cu.country AS leadCountry,
  la.salesRepId,
  sr.firstName AS repFirstName,
  sr.lastName AS repLastName,
  sr.email AS repEmail,
  CONCAT(sr.firstName, ' ', sr.lastName) AS repFullName,
  la.assignmentStatus,
  la.priority,
  la.assignedAt,
  la.lastContactedAt,
  la.expectedCloseDate,
  (SELECT COUNT(*) FROM leadAssignmentNotes WHERE assignmentId = la.id) AS notesCount,
  (SELECT COUNT(*) FROM assignmentReminders WHERE assignmentId = la.id AND isCompleted = 0) AS pendingReminders
FROM leadAssignments la
INNER JOIN clientUsers cu ON la.leadId = cu.id
INNER JOIN salesRepresentatives sr ON la.salesRepId = sr.id
WHERE la.isActive = 1
ORDER BY la.assignedAt DESC;

-- Sales Rep Workload View
CREATE OR REPLACE VIEW `vw_salesrep_workload` AS
SELECT
  sr.id AS salesRepId,
  sr.repCode,
  CONCAT(sr.firstName, ' ', sr.lastName) AS fullName,
  sr.email,
  sr.department,
  sr.isActive,
  sr.maxLeadCapacity,
  COUNT(la.id) AS activeLeadsCount,
  sr.maxLeadCapacity - COUNT(la.id) AS availableCapacity,
  (SELECT COUNT(*) FROM leadAssignmentNotes lan
   INNER JOIN leadAssignments la2 ON lan.assignmentId = la2.id
   WHERE la2.salesRepId = sr.id AND la2.isActive = 1
   AND DATE(lan.createdAt) = CURDATE()) AS todayNotesCount,
  (SELECT COUNT(*) FROM assignmentReminders ar
   INNER JOIN leadAssignments la3 ON ar.assignmentId = la3.id
   WHERE la3.salesRepId = sr.id AND ar.isCompleted = 0
   AND DATE(ar.reminderDate) <= CURDATE()) AS overdueReminders
FROM salesRepresentatives sr
LEFT JOIN leadAssignments la ON sr.id = la.salesRepId AND la.isActive = 1
WHERE sr.isActive = 1
GROUP BY sr.id, sr.repCode, sr.firstName, sr.lastName, sr.email, sr.department, sr.isActive, sr.maxLeadCapacity
ORDER BY activeLeadsCount DESC;

-- Lead Assignment Timeline View
CREATE OR REPLACE VIEW `vw_lead_assignment_timeline` AS
SELECT
  lah.id,
  lah.leadId,
  cu.firstName AS leadFirstName,
  cu.lastName AS leadLastName,
  cu.email AS leadEmail,
  lah.actionType,
  lah.previousSalesRepId,
  CONCAT(sr1.firstName, ' ', sr1.lastName) AS previousSalesRep,
  lah.newSalesRepId,
  CONCAT(sr2.firstName, ' ', sr2.lastName) AS newSalesRep,
  lah.reason,
  lah.createdAt AS actionDate
FROM leadAssignmentHistory lah
INNER JOIN clientUsers cu ON lah.leadId = cu.id
LEFT JOIN salesRepresentatives sr1 ON lah.previousSalesRepId = sr1.id
LEFT JOIN salesRepresentatives sr2 ON lah.newSalesRepId = sr2.id
ORDER BY lah.createdAt DESC;

-- Complete Lead Summary View (with Assignment Info)
-- This view extends vw_lead_summary from leads_database.sql with assignment information
CREATE OR REPLACE VIEW `vw_lead_summary_with_assignment` AS
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
  (SELECT kycStatus FROM leadKycStatus WHERE leadId = cu.id) AS kycStatus,
  la.id AS assignmentId,
  la.salesRepId,
  CONCAT(sr.firstName, ' ', sr.lastName) AS assignedToSalesRep,
  la.assignmentStatus,
  la.assignedAt,
  la.lastContactedAt,
  (SELECT COUNT(*) FROM leadAssignmentNotes WHERE assignmentId = la.id) AS notesCount
FROM clientUsers cu
LEFT JOIN leadAssignments la ON cu.id = la.leadId AND la.isActive = 1
LEFT JOIN salesRepresentatives sr ON la.salesRepId = sr.id
ORDER BY cu.createdAt DESC;

-- ============================================================
-- TRIGGERS FOR AUTOMATIC UPDATES
-- ============================================================

-- Trigger: Update sales rep lead count after assignment
DELIMITER $$
CREATE TRIGGER `trg_after_lead_assignment_insert`
AFTER INSERT ON `leadAssignments`
FOR EACH ROW
BEGIN
  IF NEW.isActive = 1 THEN
    UPDATE salesRepresentatives
    SET currentLeadCount = currentLeadCount + 1
    WHERE id = NEW.salesRepId;
  END IF;
END$$

-- Trigger: Update sales rep lead count after assignment update
CREATE TRIGGER `trg_after_lead_assignment_update`
AFTER UPDATE ON `leadAssignments`
FOR EACH ROW
BEGIN
  -- If assignment was deactivated
  IF OLD.isActive = 1 AND NEW.isActive = 0 THEN
    UPDATE salesRepresentatives
    SET currentLeadCount = GREATEST(currentLeadCount - 1, 0)
    WHERE id = OLD.salesRepId;
  END IF;

  -- If assignment was activated
  IF OLD.isActive = 0 AND NEW.isActive = 1 THEN
    UPDATE salesRepresentatives
    SET currentLeadCount = currentLeadCount + 1
    WHERE id = NEW.salesRepId;
  END IF;

  -- If sales rep was changed
  IF OLD.salesRepId != NEW.salesRepId AND NEW.isActive = 1 THEN
    UPDATE salesRepresentatives
    SET currentLeadCount = GREATEST(currentLeadCount - 1, 0)
    WHERE id = OLD.salesRepId;

    UPDATE salesRepresentatives
    SET currentLeadCount = currentLeadCount + 1
    WHERE id = NEW.salesRepId;
  END IF;
END$$

-- Trigger: Update sales rep lead count after assignment deletion
CREATE TRIGGER `trg_after_lead_assignment_delete`
AFTER DELETE ON `leadAssignments`
FOR EACH ROW
BEGIN
  IF OLD.isActive = 1 THEN
    UPDATE salesRepresentatives
    SET currentLeadCount = GREATEST(currentLeadCount - 1, 0)
    WHERE id = OLD.salesRepId;
  END IF;
END$$

DELIMITER ;

COMMIT;

-- ============================================================
-- END OF SALES ASSIGNMENT DATABASE SCHEMA
-- ============================================================
