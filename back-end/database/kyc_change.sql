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

ALTER TABLE `kyctemplatedocuments`
    CHANGE COLUMN `documentId` `documentId` INT(11) UNSIGNED NULL COMMENT 'Reference to legalDocuments.id' AFTER `templateId`;

-- 更新 KYC 状态枚举值
-- 执行日期: 2025-10-29

-- 1. 更新 clientKycSubmissions 表的 submissionStatus 枚举
ALTER TABLE `clientKycSubmissions`
    MODIFY COLUMN `submissionStatus` enum(
    'draft',
    'pending',
    'under_review',
    'approved',
    'rejected',
    'incomplete',
    'expired',
    'resubmit_required',
    'pending_documents'
    ) NOT NULL DEFAULT 'draft';

-- 2. 更新 kycStatusMessageTemplates 表的 statusType 枚举 (如果需要保持一致)
ALTER TABLE `kycStatusMessageTemplates`
    MODIFY COLUMN `statusType` enum(
    'draft',
    'pending',
    'under_review',
    'approved',
    'rejected',
    'incomplete',
    'expired',
    'resubmit_required',
    'pending_documents'
    ) NOT NULL;

-- 3. 插入新状态对应的消息模板
INSERT INTO `kycStatusMessageTemplates` (`statusType`, `messageTitle`, `messageContent`, `messageType`, `showActionButton`, `actionButtonText`, `actionButtonUrl`, `iconClass`, `isActive`, `displayOrder`) VALUES
    ('pending_documents', 'Additional Documents Required', 'We need additional documents to complete your KYC verification.', 'warning', 1, 'Upload Documents', '/client/kyc-verification', 'fas fa-upload', 1, 8);

-- 4. 创建获取客户KYC状态的视图 (如果不存在)
CREATE OR REPLACE VIEW `vw_client_kyc_status` AS
SELECT
    cu.id AS clientId,
    cu.email,
    cu.firstName,
    cu.lastName,
    cu.country,
    s.id AS submissionId,
    s.templateId,
    t.templateName,
    COALESCE(s.submissionStatus, 'draft') AS submissionStatus,
    s.submittedAt,
    s.reviewedAt,
    s.rejectionReason,
    s.createdAt,
    s.updatedAt,
    -- 计算进度
    (SELECT COUNT(*) FROM clientKycAnswers WHERE submissionId = s.id) AS answeredQuestions,
    COALESCE(t.totalQuestions, 0) AS totalQuestions,
    CASE
        WHEN t.totalQuestions > 0 THEN
            ROUND((SELECT COUNT(*) FROM clientKycAnswers WHERE submissionId = s.id) / t.totalQuestions * 100, 2)
        ELSE 0
        END AS progressPercentage,
    -- 文档签署进度
    (SELECT COUNT(*) FROM clientKycDocumentSignatures WHERE submissionId = s.id) AS signedDocuments,
    (SELECT COUNT(*) FROM kycTemplateDocuments WHERE templateId = s.templateId AND isActive = 1) AS requiredDocuments
FROM clientUsers cu
         LEFT JOIN (
    -- 获取每个客户最新的submission
    SELECT s1.*
    FROM clientKycSubmissions s1
             INNER JOIN (
        SELECT clientId, MAX(createdAt) as maxCreated
        FROM clientKycSubmissions
        GROUP BY clientId
    ) s2 ON s1.clientId = s2.clientId AND s1.createdAt = s2.maxCreated
) s ON cu.id = s.clientId
         LEFT JOIN kycTemplates t ON s.templateId = t.id
ORDER BY cu.id;

COMMIT;

-- 为 kycStatusMessageTemplates 表添加缺少的字段
-- 1. 添加新字段
ALTER TABLE `kycStatusMessageTemplates`
    ADD COLUMN `titleIcon` varchar(100) DEFAULT 'fas fa-info-circle' COMMENT 'Title icon class' AFTER `iconClass`,
ADD COLUMN `badgeText` varchar(50) DEFAULT NULL COMMENT 'Status badge text' AFTER `titleIcon`,
ADD COLUMN `badgeClass` varchar(50) DEFAULT 'info' COMMENT 'Status badge CSS class' AFTER `badgeText`;

-- 2. 更新现有记录的新字段值
-- Draft状态
UPDATE `kycStatusMessageTemplates` SET
       `titleIcon` = 'fas fa-id-card',
       `badgeText` = 'Not Started',
       `badgeClass` = 'warning'
WHERE `statusType` = 'draft';

-- Incomplete状态
UPDATE `kycStatusMessageTemplates` SET
       `titleIcon` = 'fas fa-hourglass-half',
       `badgeText` = 'In Progress',
       `badgeClass` = 'incomplete',
       `actionButtonText`='Complete Application'
WHERE `statusType` = 'incomplete';

-- Pending状态
UPDATE `kycStatusMessageTemplates` SET
       `titleIcon` = 'fas fa-clock',
       `badgeText` = 'Pending Review',
       `badgeClass` = 'info'
WHERE `statusType` = 'pending';

-- Under Review状态
UPDATE `kycStatusMessageTemplates` SET
       `titleIcon` = 'fas fa-search',
       `badgeText` = 'Under Review',
       `badgeClass` = 'info'
WHERE `statusType` = 'under_review';

-- Approved状态
UPDATE `kycStatusMessageTemplates` SET
       `titleIcon` = 'fas fa-check-circle',
       `badgeText` = 'Approved',
       `badgeClass` = 'success'
WHERE `statusType` = 'approved';

-- Rejected状态
UPDATE `kycStatusMessageTemplates` SET
       `titleIcon` = 'fas fa-times-circle',
       `badgeText` = 'Rejected',
       `badgeClass` = 'error'
WHERE `statusType` = 'rejected';

-- Expired状态
UPDATE `kycStatusMessageTemplates` SET
       `titleIcon` = 'fas fa-hourglass-half',
       `badgeText` = 'Expired',
       `badgeClass` = 'warning'
WHERE `statusType` = 'expired';

-- Resubmit Required状态
UPDATE `kycStatusMessageTemplates` SET
       `titleIcon` = 'fas fa-hourglass-half',
       `badgeText` = 'Resubmit Required',
       `badgeClass` = 'warning'
WHERE `statusType` = 'resubmit_required';

-- Pending Documents状态
UPDATE `kycStatusMessageTemplates` SET
       `titleIcon` = 'fas fa-file-upload',
       `badgeText` = 'Documents Required',
       `badgeClass` = 'warning'
WHERE `statusType` = 'pending_documents';
-- 创建KYC操作时间线表（仅存储实际发生的事件，不使用任何模板）
CREATE TABLE `kycTimeline` (
   `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
   `submissionId` bigint(20) UNSIGNED NOT NULL COMMENT 'KYC提交ID',
   `clientId` bigint(20) UNSIGNED NOT NULL COMMENT '客户ID',
   `eventType` enum(
       'application_started',
       'personal_info_submitted',
       'financial_info_submitted',
       'documents_uploaded',
       'application_submitted',
       'under_review',
       'additional_documents_requested',
       'review_completed',
       'approved',
       'rejected',
       'expired',
       'resubmit_required',
       'progress_updated'
       ) NOT NULL COMMENT '事件类型',
   `eventTitle` varchar(200) NOT NULL COMMENT '事件标题',
   `eventDescription` text NOT NULL COMMENT '事件描述',
   `eventStatus` enum('completed', 'current', 'pending') NOT NULL DEFAULT 'completed' COMMENT '事件状态',
   `eventData` json DEFAULT NULL COMMENT '事件相关数据(JSON格式)',
   `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '事件发生时间',
   `createdBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT '操作人ID(管理员审核时)',
   PRIMARY KEY (`id`),
   KEY `idx_submission_id` (`submissionId`),
   KEY `idx_client_id` (`clientId`),
   KEY `idx_event_type` (`eventType`),
   KEY `idx_created_at` (`createdAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='KYC操作时间线记录表';

-- 为 clientUsers 表添加 KYC 相关字段
-- 执行时间: 2025-10-31

ALTER TABLE `clientUsers`
    ADD COLUMN `kycStatus` enum('not_started','in_progress','submitted','under_review','approved','rejected','incomplete') DEFAULT 'not_started' COMMENT 'KYC verification status',
ADD COLUMN `kycSubmissionId` int(11) UNSIGNED DEFAULT NULL COMMENT 'Latest KYC submission ID',
ADD COLUMN `verifiedAt` datetime DEFAULT NULL COMMENT 'KYC verification completion date',
ADD COLUMN `tags` text DEFAULT NULL COMMENT 'Client tags (JSON format)',
ADD COLUMN `assignedTo` int(11) UNSIGNED DEFAULT NULL COMMENT 'Assigned admin user ID',
ADD COLUMN `notes` text DEFAULT NULL COMMENT 'Admin notes about the client';

-- 添加索引
ALTER TABLE `clientUsers`
    ADD KEY `kycStatus` (`kycStatus`),
ADD KEY `kycSubmissionId` (`kycSubmissionId`),
ADD KEY `verifiedAt` (`verifiedAt`),
ADD KEY `assignedTo` (`assignedTo`);

-- 添加外键约束
ALTER TABLE `clientUsers`
    ADD CONSTRAINT `fk_client_kyc_submission`
        FOREIGN KEY (`kycSubmissionId`) REFERENCES `clientKycSubmissions` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE;

-- 如果 adminUsers 表存在，添加外键约束
-- ALTER TABLE `clientUsers`
-- ADD CONSTRAINT `fk_client_assigned_admin`
-- FOREIGN KEY (`assignedTo`) REFERENCES `adminUsers` (`id`)
-- ON DELETE SET NULL ON UPDATE CASCADE;

-- 客户管理视图
-- 用于后台Client List页面的数据查询优化

CREATE OR REPLACE VIEW vw_client_management AS
SELECT
    c.id,
    c.email,
    c.firstName,
    c.lastName,
    CONCAT(c.firstName, ' ', c.lastName) as fullName,
    c.phone,
    c.country,
    c.status as accountStatus,
    c.kycStatus,
    c.verifiedAt,
    c.tags,
    c.assignedTo,
    c.notes,
    c.createdAt,
    c.updatedAt,
    c.lastLoginAt,

    -- KYC 提交信息
    s.id as submissionId,
    s.submittedAt,
    s.reviewedAt,
    s.reviewedBy,
    s.approvalNotes,
    s.rejectionReason,

    -- 审核员信息
    a.fullName as reviewerName,
    a.username as reviewerUsername,
    a.email as reviewerEmail,

    -- 分配的管理员信息
    aa.fullName as assignedAdminName,
    aa.username as assignedAdminUsername,
    aa.email as assignedAdminEmail,

    -- 统计信息
    (SELECT COUNT(*) FROM clientKycSubmissions WHERE clientId = c.id) as totalSubmissions,
    (SELECT COUNT(*) FROM clientActivityLog WHERE clientId = c.id) as totalActivities

FROM clientUsers c
         LEFT JOIN clientKycSubmissions s ON c.kycSubmissionId = s.id
         LEFT JOIN adminusers a ON s.reviewedBy = a.id
         LEFT JOIN adminusers aa ON c.assignedTo = aa.id
WHERE c.kycStatus = 'approved'
ORDER BY c.verifiedAt DESC;


-- Created: 2025-11-03
ALTER TABLE `kyctimeline`
    CHANGE COLUMN `eventType` `eventType` ENUM('application_started','personal_info_submitted','financial_info_submitted','documents_uploaded','application_submitted','under_review','additional_documents_requested','review_completed','approved','rejected','expired','resubmit_required','progress_updated','pending') NOT NULL COMMENT '事件类型' COLLATE 'utf8mb4_unicode_ci' AFTER `clientId`;

-- Created: 2025-11-04
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
    s.reviewedBy AS reviewerId,
    COALESCE(au.fullName, au.username, '') AS reviewerName,
    au.username AS reviewerUsername,
    (SELECT COUNT(*) FROM clientKycAnswers WHERE submissionId = s.id) AS answeredQuestions,
    t.totalQuestions,
    ROUND((SELECT COUNT(*) FROM clientKycAnswers WHERE submissionId = s.id) / t.totalQuestions * 100, 2) AS progressPercentage,
    (SELECT COUNT(*) FROM clientKycDocumentSignatures WHERE submissionId = s.id) AS signedDocuments,
    (SELECT COUNT(*) FROM kycTemplateDocuments WHERE templateId = s.templateId AND isActive = 1) AS requiredDocuments
FROM clientKycSubmissions s
         INNER JOIN clientUsers cu ON s.clientId = cu.id
         INNER JOIN kycTemplates t ON s.templateId = t.id
         LEFT JOIN adminUsers au ON s.reviewedBy = au.id
ORDER BY s.submittedAt DESC;

-- ============================================================
-- KYC Resubmit Requests Table
-- 存储后台审批员请求用户补充的问题和文件信息
-- ============================================================
-- Created: 2025-11-05
-- Description: 存储每次请求补充信息的内容
-- =====================================
CREATE TABLE IF NOT EXISTS `kycResubmitRequests` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `submissionId` INT(11) UNSIGNED NOT NULL COMMENT 'KYC提交ID',
    `clientId` INT(11) UNSIGNED NOT NULL COMMENT '客户ID',
    `requestedBy` INT(11) UNSIGNED NOT NULL COMMENT '请求补充信息的审核员ID',
    `requestedItems` JSON NOT NULL COMMENT '请求的问题和文件列表(JSON格式)',
    `additionalNotes` TEXT DEFAULT NULL COMMENT '附加说明',
    `requestedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '请求时间',
    `status` ENUM('pending','completed','expired') NOT NULL DEFAULT 'pending' COMMENT '请求状态',
    `completedAt` DATETIME DEFAULT NULL COMMENT '完成时间',
    `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_submission_id` (`submissionId`),
    KEY `idx_client_id` (`clientId`),
    KEY `idx_status` (`status`),
    KEY `idx_requested_at` (`requestedAt`),
    CONSTRAINT `fk_resubmit_request_submission` FOREIGN KEY (`submissionId`)
    REFERENCES `clientkycsubmissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_resubmit_request_client` FOREIGN KEY (`clientId`)
    REFERENCES `clientusers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci
    COMMENT='KYC重新提交请求表';

-- ============================================================
-- KYC Resubmit Answers Table
-- 存储用户补充提交的答案信息
-- ============================================================

CREATE TABLE IF NOT EXISTS `kycResubmitAnswers` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `requestId` INT(11) UNSIGNED NOT NULL COMMENT '关联的请求ID',
    `submissionId` INT(11) UNSIGNED NOT NULL COMMENT 'KYC提交ID',
    `itemType` enum('question','document') NOT NULL COMMENT '项目类型',
    `itemId` varchar(100) DEFAULT NULL COMMENT '项目ID或标识',
    `questionText` text DEFAULT NULL COMMENT '问题文本（如果是问题）',
    `questionType` varchar(50) DEFAULT NULL COMMENT '问题类型（如果是问题）',
    `documentName` varchar(255) DEFAULT NULL COMMENT '文档名称（如果是文档）',
    `answerText` text DEFAULT NULL COMMENT '答案文本',
    `answerValues` text DEFAULT NULL COMMENT '答案值（JSON格式，用于多选）',
    `uploadedFiles` text DEFAULT NULL COMMENT '上传的文件（JSON格式）',
    `submittedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '提交时间',
    `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_request_id` (`requestId`),
    KEY `idx_submission_id` (`submissionId`),
    KEY `idx_item_type` (`itemType`),
    CONSTRAINT `fk_resubmit_answer_request` FOREIGN KEY (`requestId`) REFERENCES `kycResubmitRequests` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_resubmit_answer_submission` FOREIGN KEY (`submissionId`) REFERENCES `clientKycSubmissions` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='KYC重新提交答案表';

-- 创建时间2025-11-10
-- clientUsers加被分配的manager
ALTER TABLE `clientUsers`
    ADD COLUMN `accountManagerId` BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER `assignedTo`,
  ADD INDEX `idx_accountManagerId` (`accountManagerId`);
ALTER TABLE `clientUsers`
    ADD COLUMN `accountManagerNote` TEXT NULL AFTER `accountManagerId`;
ALTER TABLE `clientUsers`
    ADD COLUMN `accountManagerAssignedAt` DATETIME NULL DEFAULT NULL AFTER `accountManagerId`;

ALTER TABLE `clientUsers`
    ADD CONSTRAINT `fk_client_account_manager`
        FOREIGN KEY (`accountManagerId`) REFERENCES `adminUsers`(`id`)
            ON DELETE SET NULL ON UPDATE CASCADE;

-- Created: 2025-11-12
-- 通知主表（记录一次通知行为）
CREATE TABLE `clientNotifications` (
   `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
   `clientId` INT(11) UNSIGNED NOT NULL,
   `subject` VARCHAR(255) NOT NULL,
   `message` TEXT NOT NULL,
   `priority` ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
   `scheduleType` ENUM('immediate','scheduled') NOT NULL DEFAULT 'immediate',
   `scheduledAt` DATETIME NULL DEFAULT NULL,
   `status` ENUM('pending','queued','sending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
   `emailTemplate` VARCHAR(100) NULL DEFAULT NULL,
   `createdBy` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
   `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   `updatedAt` DATETIME NULL DEFAULT NULL,
   CONSTRAINT `fk_client_notifications_client`
       FOREIGN KEY (`clientId`) REFERENCES `clientUsers`(`id`) ON DELETE CASCADE,
   CONSTRAINT `fk_client_notifications_admin`
       FOREIGN KEY (`createdBy`) REFERENCES `adminUsers`(`id`) ON DELETE SET NULL,
   INDEX `idx_client_schedule_status` (`clientId`, `status`, `scheduledAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 通知-渠道表（区分系统提示与邮件发送）
CREATE TABLE `clientNotificationDeliveries` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `notificationId` INT(11) UNSIGNED NOT NULL,
    `channel` ENUM('system','email') NOT NULL,
    `status` ENUM('pending','sending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    `errorMessage` TEXT NULL DEFAULT NULL,
    `sentAt` DATETIME NULL DEFAULT NULL,
    `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notification_deliveries_notification`
        FOREIGN KEY (`notificationId`) REFERENCES `clientNotifications`(`id`) ON DELETE CASCADE,
    INDEX `idx_channel_status` (`channel`, `status`),
    INDEX `idx_notification_channel` (`notificationId`, `channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 系统通知实体表（客户端实际读取的提醒）
CREATE TABLE `clientSystemNotifications` (
     `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `notificationId` INT(11) UNSIGNED NOT NULL,
     `clientId` INT(11) UNSIGNED NOT NULL,
     `subject` VARCHAR(255) NOT NULL,
     `message` TEXT NOT NULL,
     `isRead` TINYINT(1) NOT NULL DEFAULT 0,
     `readAt` DATETIME NULL DEFAULT NULL,
     `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
     CONSTRAINT `fk_system_notifications_notification`
         FOREIGN KEY (`notificationId`) REFERENCES `clientNotifications`(`id`) ON DELETE CASCADE,
     CONSTRAINT `fk_system_notifications_client`
         FOREIGN KEY (`clientId`) REFERENCES `clientUsers`(`id`) ON DELETE CASCADE,
    INDEX `idx_client_read` (`clientId`, `isRead`, `createdAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 邮件模板表
CREATE TABLE `clientEmailTemplates` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `templateKey` VARCHAR(100) NOT NULL UNIQUE,   -- 例如 welcome / deposit 等，供前端下拉绑定
    `name` VARCHAR(150) NOT NULL,                 -- 模板名称（展示用）
    `subject` VARCHAR(255) NOT NULL,              -- 默认邮件标题
    `body` MEDIUMTEXT NOT NULL,                   -- 邮件 HTML 内容，可含占位符
    `isActive` TINYINT(1) NOT NULL DEFAULT 1,     -- 是否启用
    `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt` DATETIME NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `clientEmailTemplates` (`templateKey`, `name`, `subject`, `body`)
VALUES
    ('welcome', 'Welcome Email', 'Welcome to Utrada CRM',
     '<p>Dear {{firstName}} {{lastName}},</p>
      <p>Welcome to Utrada CRM! We are excited to help you manage your trading journey. If you have any questions, feel free to reach out to our support team.</p>
      <p>Best regards,<br>Utrada CRM Team</p>'),

    ('deposit_confirmation', 'Deposit Confirmation', 'Your Deposit Has Been Received',
     '<p>Hello {{firstName}},</p>
      <p>We have successfully received your deposit of <strong>{{amount}}</strong> on {{date}}. The funds are now available in your account.</p>
      <p>If you have any questions, please contact your account manager.</p>
      <p>Regards,<br>Utrada CRM Team</p>'),

    ('withdrawal_update', 'Withdrawal Update', 'Withdrawal Request Update',
     '<p>Hi {{firstName}},</p>
      <p>Your withdrawal request submitted on {{date}} is currently <strong>{{status}}</strong>. We will notify you once the transfer is completed.</p>
      <p>Thank you for choosing Utrada CRM.</p>
      <p>Sincerely,<br>Utrada CRM Team</p>'),

    ('kyc_reminder', 'KYC Reminder', 'Reminder: Complete Your KYC Verification',
     '<p>Dear {{firstName}},</p>
      <p>This is a friendly reminder to complete your KYC verification. Please log in to your portal and upload the required documents as soon as possible.</p>
      <p>If you need assistance, contact our compliance team.</p>
      <p>Best regards,<br>Utrada CRM Team</p>'),

    ('promotion', 'Promotional Offer', 'Exclusive Trading Promotion Just for You',
     '<p>Hello {{firstName}},</p>
      <p>We are excited to share an exclusive promotion tailored for you. Enjoy <strong>{{promotionDetails}}</strong> when you trade between {{startDate}} and {{endDate}}.</p>
      <p>Don’t miss out on this opportunity!</p>
      <p>Warm regards,<br>Utrada CRM Team</p>');

-- ============================================================
-- 交易平台账号
-- Created: 2025-11-13
-- ============================================================

CREATE TABLE `tradingAccountExternalAccounts` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tradingAccountId` int(11) UNSIGNED NOT NULL,
  `providerKey` varchar(50) NOT NULL COMMENT 'Third-party provider identifier',
  `providerAccountId` varchar(150) DEFAULT NULL COMMENT 'Account ID returned by third-party',
  `groupId` int(11) DEFAULT NULL,
  `predefinedLogin` varchar(150) DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  `name` varchar(200) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `city` varchar(150) DEFAULT NULL,
  `state` varchar(150) DEFAULT NULL,
  `country` varchar(150) DEFAULT NULL,
  `zipCode` varchar(50) DEFAULT NULL,
  `phone` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `idNumber` varchar(100) DEFAULT NULL,
  `leverage` varchar(50) DEFAULT NULL,
  `taxRate` varchar(50) DEFAULT NULL,
  `agentAccount` varchar(150) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `allowChangePin` tinyint(1) DEFAULT NULL,
  `isReadOnly` tinyint(1) DEFAULT NULL,
  `sendReport` tinyint(1) DEFAULT NULL,
  `masterPin` varchar(150) DEFAULT NULL,
  `investorPin` varchar(150) DEFAULT NULL,
  `phonePin` varchar(150) DEFAULT NULL,
  `isEnable` tinyint(1) DEFAULT NULL,
  `enabledMark` tinyint(1) DEFAULT NULL,
  `deleteMark` tinyint(1) DEFAULT NULL,
  `creatorTime` datetime DEFAULT NULL,
  `deleteTime` datetime DEFAULT NULL,
  `deleteUserId` varchar(150) DEFAULT NULL,
  `rawResponse` json DEFAULT NULL COMMENT 'Full JSON response from third-party',
  `requestPayload` json DEFAULT NULL COMMENT 'Payload sent to third-party',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tradingAccountUnique` (`tradingAccountId`),
  KEY `providerKey` (`providerKey`),
  CONSTRAINT `tradingAccountExternalAccountsAccountFk` FOREIGN KEY (`tradingAccountId`) REFERENCES `tradingAccounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Third-party trading account details linked to internal accounts';

-- 新加两个交易平台登录用户名和投资者密码（加密）
ALTER TABLE `tradingAccounts`
    ADD COLUMN `predefinedLogin` varchar(150) DEFAULT NULL COMMENT 'Third-party platform login username' AFTER `initialDeposit`,
ADD COLUMN `investorPinEncrypted` text DEFAULT NULL COMMENT 'Encrypted investor PIN (reversible)' AFTER `predefinedLogin`;
