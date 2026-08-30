-- ============================================================
-- Add notification type field to clientSystemNotifications
-- ============================================================
-- Created: 2025-11-26
-- Description: Add type field to support different notification types
--              such as withdrawal_document_request
-- ============================================================

ALTER TABLE `clientSystemNotifications`
ADD COLUMN `type` VARCHAR(50) NULL DEFAULT 'common' COMMENT 'Notification type: withdrawal_document_request, kyc_resubmit, etc.' AFTER `notificationId`,
ADD COLUMN `metadata` TEXT NULL DEFAULT NULL COMMENT 'JSON metadata for notification actions' AFTER `type`,
ADD INDEX `idx_type` (`type`);

-- ============================================================
-- Create Client Tickets Table
-- ============================================================
-- Created: 2025-11-26
-- Description: Create table for client support tickets
-- ============================================================

CREATE TABLE IF NOT EXISTS `clientTickets` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `clientId` INT(11) UNSIGNED NOT NULL COMMENT 'Client user ID who submitted the ticket',
    `title` VARCHAR(255) NOT NULL COMMENT 'Ticket title/subject',
    `content` TEXT NOT NULL COMMENT 'Ticket content/message',
    `status` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Ticket status (reserved for future use)',
    `priority` ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_client_tickets_client`
    FOREIGN KEY (`clientId`) REFERENCES `clientUsers`(`id`) ON DELETE CASCADE,
    INDEX `idx_client_created` (`clientId`, `createdAt`),
    INDEX `idx_created_at` (`createdAt`),
    INDEX `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Create Admin Notification System Tables
-- ============================================================
-- Created: 2025-11-26
-- Description: Create admin notification system tables similar to client notification system
--              Includes: adminNotifications, adminNotificationDeliveries, adminSystemNotifications
-- ============================================================

-- 主通知表（管理员通知）
CREATE TABLE IF NOT EXISTS `adminNotifications` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `adminId` BIGINT(20) UNSIGNED NOT NULL COMMENT 'Admin user ID who receives the notification',
    `subject` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `priority` ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    `scheduleType` ENUM('immediate','scheduled') NOT NULL DEFAULT 'immediate',
    `scheduledAt` DATETIME NULL DEFAULT NULL,
    `status` ENUM('pending','queued','sending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    `emailTemplate` VARCHAR(100) NULL DEFAULT NULL,
    `createdBy` BIGINT(20) UNSIGNED NULL DEFAULT NULL COMMENT 'System or admin user ID who created this notification',
    `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt` DATETIME NULL DEFAULT NULL,
    CONSTRAINT `fk_admin_notifications_admin`
    FOREIGN KEY (`adminId`) REFERENCES `adminUsers`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_admin_notifications_creator`
    FOREIGN KEY (`createdBy`) REFERENCES `adminUsers`(`id`) ON DELETE SET NULL,
    INDEX `idx_admin_schedule_status` (`adminId`, `status`, `scheduledAt`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 通知-渠道表（区分系统提示与邮件发送）
CREATE TABLE IF NOT EXISTS `adminNotificationDeliveries` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `notificationId` INT(11) UNSIGNED NOT NULL,
    `channel` ENUM('system','email') NOT NULL,
    `status` ENUM('pending','sending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    `errorMessage` TEXT NULL DEFAULT NULL,
    `sentAt` DATETIME NULL DEFAULT NULL,
    `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_admin_notification_deliveries_notification`
    FOREIGN KEY (`notificationId`) REFERENCES `adminNotifications`(`id`) ON DELETE CASCADE,
    INDEX `idx_channel_status` (`channel`, `status`),
    INDEX `idx_notification_channel` (`notificationId`, `channel`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 系统通知实体表（后台实际读取的提醒）
CREATE TABLE IF NOT EXISTS `adminSystemNotifications` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `notificationId` INT(11) UNSIGNED NOT NULL,
    `adminId` BIGINT(20) UNSIGNED NOT NULL,
    `type` VARCHAR(50) NULL DEFAULT 'common' COMMENT 'Notification type: client_ticket, etc.',
    `metadata` TEXT NULL DEFAULT NULL COMMENT 'JSON metadata for notification actions',
    `subject` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `isRead` TINYINT(1) NOT NULL DEFAULT 0,
    `readAt` DATETIME NULL DEFAULT NULL,
    `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_admin_system_notifications_notification`
    FOREIGN KEY (`notificationId`) REFERENCES `adminNotifications`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_admin_system_notifications_admin`
    FOREIGN KEY (`adminId`) REFERENCES `adminUsers`(`id`) ON DELETE CASCADE,
    INDEX `idx_admin_read` (`adminId`, `isRead`, `createdAt`),
    INDEX `idx_type` (`type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Withdrawal 存储过程
-- 执行日期: 2025-11-26
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

-- Update withdrawal to processing; completed only after PSP callback / manual complete
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

-- 执行日期: 2025-11-27
-- 邮件模板管理表
CREATE TABLE IF NOT EXISTS `emailTemplates` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `templateKey` VARCHAR(100) NOT NULL COMMENT '唯一标识符，用于代码中引用',
    `templateName` VARCHAR(255) NOT NULL COMMENT '模板名称',
    `category` VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT '模板分类：registration, kyc, transaction, account, notification, etc.',
    `emailSubject` TEXT NOT NULL COMMENT '邮件主题（支持变量替换）',
    `emailBody` TEXT NOT NULL COMMENT '邮件内容（HTML格式，支持变量替换）',
    `recipientType` ENUM('client', 'admin', 'both') NOT NULL DEFAULT 'client' COMMENT '收件人类型：client=客户端用户, admin=后台管理员, both=两者',
    `description` TEXT COMMENT '模板描述',
    `variables` TEXT COMMENT '可用变量说明（JSON格式）',
    `isActive` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
    `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_template_key` (`templateKey`),
    KEY `idx_category` (`category`),
    KEY `idx_is_active` (`isActive`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邮件模板管理表';

-- 插入默认邮件模板（英文名称版本，用于线上部署）
-- Insert default email templates (English names version, for production deployment)
INSERT INTO `emailTemplates` (`templateKey`, `templateName`, `category`, `emailSubject`, `emailBody`, `recipientType`, `description`, `variables`, `isActive`) VALUES
-- 注册相关 / Registration Related
('registration_welcome', 'Registration Welcome Email', 'registration', 'Welcome to {{platformName}}!', '<p>Dear {{clientName}},</p><p>Welcome to {{platformName}}! Your account has been successfully created.</p><p>Your login email: {{email}}</p><p>Please verify your email address to activate your account.</p><p><a href="{{verificationUrl}}">Verify Email</a></p><p>Best regards,<br>{{platformName}} Team</p>', 'client', '用户注册成功后发送的欢迎邮件', '{"clientName": "客户姓名", "email": "客户邮箱", "platformName": "平台名称", "verificationUrl": "邮箱验证链接"}', 1),
('registration_email_verification', 'Email Verification', 'registration', 'Verify Your Email Address', '<p>Dear {{clientName}},</p><p>Please verify your email address by clicking the link below:</p><p><a href="{{verificationUrl}}">{{verificationUrl}}</a></p><p>This link will expire in {{expiryHours}} hours.</p><p>If you did not create an account, please ignore this email.</p><p>Best regards,<br>{{platformName}} Team</p>', 'client', '注册时发送的邮箱验证邮件', '{"clientName": "客户姓名", "verificationUrl": "验证链接", "expiryHours": "过期小时数", "platformName": "平台名称"}', 1),
('registration_password_reset', 'Password Reset', 'registration', 'Reset Your Password', '<p>Dear {{clientName}},</p><p>You requested to reset your password. Click the link below to reset it:</p><p><a href="{{resetUrl}}">{{resetUrl}}</a></p><p>This link will expire in {{expiryHours}} hours.</p><p>If you did not request a password reset, please ignore this email.</p><p>Best regards,<br>{{platformName}} Team</p>', 'client', '密码重置邮件', '{"clientName": "客户姓名", "resetUrl": "重置链接", "expiryHours": "过期小时数", "platformName": "平台名称"}', 1),
-- KYC相关 / KYC Related
('kyc_submission_confirmation', 'KYC Submission Confirmation', 'kyc', 'Your KYC Verification Has Been Submitted', '<p>Dear {{clientName}},</p><p>Thank you for submitting your KYC verification documents. We have received your application and our compliance team will review it shortly.</p><p><strong>What happens next?</strong></p><ul><li>Our team will verify your documents within 1-2 business days</li><li>You will receive an email notification once the review is complete</li><li>You can track your verification status in your dashboard</li></ul><p>If you have any questions, please don\'t hesitate to contact our support team.</p><p>Best regards,<br>{{platformName}} Team</p>', 'client', 'KYC提交后发送的确认邮件', '{"clientName": "客户姓名", "platformName": "平台名称"}', 1),
('kyc_approval_notification', 'KYC Approval Notification', 'kyc', 'Your KYC Verification Has Been Approved!', '<p>Dear {{clientName}},</p><p>Great news! Your KYC verification has been approved.</p><p><strong>What this means for you:</strong></p><ul><li>Full access to all trading features</li><li>Higher transaction limits</li><li>Priority customer support</li><li>Access to advanced trading tools</li></ul><p>You can now start trading with complete confidence.</p><p><a href="{{dashboardUrl}}">Go to Dashboard</a></p><p>Best regards,<br>{{platformName}} Team</p>', 'client', 'KYC审核通过后发送的通知邮件', '{"clientName": "客户姓名", "dashboardUrl": "仪表板链接", "platformName": "平台名称"}', 1),
('kyc_rejection_notification', 'KYC Rejection Notification', 'kyc', 'Action Required: KYC Verification Issue', '<p>Dear {{clientName}},</p><p>We regret to inform you that we were unable to approve your KYC verification at this time.</p><p><strong>Reason for rejection:</strong></p><p>{{rejectionReason}}</p><p><strong>Next steps:</strong></p><ul><li>Review the feedback provided</li><li>Prepare the required documents</li><li>Resubmit your KYC application</li></ul><p><a href="{{resubmitUrl}}">Resubmit Documents</a></p><p>If you need assistance, our support team is here to help.</p><p>Best regards,<br>{{platformName}} Team</p>', 'client', 'KYC审核拒绝后发送的通知邮件', '{"clientName": "客户姓名", "rejectionReason": "拒绝原因", "resubmitUrl": "重新提交链接", "platformName": "平台名称"}', 1),
-- 交易相关 / Transaction Related
('deposit_approved', 'Deposit Approved', 'transaction', 'Deposit Approved - {{transactionId}}', '<p>Dear {{clientName}},</p><p>Your deposit request has been approved.</p><p><strong>Transaction Details:</strong></p><ul><li>Transaction ID: {{transactionId}}</li><li>Amount: {{amount}} {{currency}}</li><li>Payment Method: {{paymentMethod}}</li><li>Status: Approved</li></ul><p>The funds have been credited to your account.</p><p><a href="{{dashboardUrl}}">View Transaction</a></p><p>Best regards,<br>{{platformName}} Team</p>', 'client', '存款审核通过后发送的通知邮件', '{"clientName": "客户姓名", "transactionId": "交易ID", "amount": "金额", "currency": "货币", "paymentMethod": "支付方式", "dashboardUrl": "仪表板链接", "platformName": "平台名称"}', 1),
('deposit_rejected', 'Deposit Rejected', 'transaction', 'Deposit Rejected - {{transactionId}}', '<p>Dear {{clientName}},</p><p>Your deposit request has been rejected.</p><p><strong>Transaction Details:</strong></p><ul><li>Transaction ID: {{transactionId}}</li><li>Amount: {{amount}} {{currency}}</li><li>Payment Method: {{paymentMethod}}</li><li>Status: Rejected</li></ul><p><strong>Reason:</strong> {{rejectionReason}}</p><p>If you have any questions, please contact our support team.</p><p>Best regards,<br>{{platformName}} Team</p>', 'client', '存款审核拒绝后发送的通知邮件', '{"clientName": "客户姓名", "transactionId": "交易ID", "amount": "金额", "currency": "货币", "paymentMethod": "支付方式", "rejectionReason": "拒绝原因", "platformName": "平台名称"}', 1),
('withdrawal_approved', 'Withdrawal Approved', 'transaction', 'Withdrawal Approved - {{transactionId}}', '<p>Dear {{clientName}},</p><p>Your withdrawal request has been approved.</p><p><strong>Transaction Details:</strong></p><ul><li>Transaction ID: {{transactionId}}</li><li>Amount: {{amount}} {{currency}}</li><li>Payment Method: {{paymentMethod}}</li><li>Status: Approved</li></ul><p>The funds will be processed and transferred to your account within 1-3 business days.</p><p><a href="{{dashboardUrl}}">View Transaction</a></p><p>Best regards,<br>{{platformName}} Team</p>', 'client', '提款审核通过后发送的通知邮件', '{"clientName": "客户姓名", "transactionId": "交易ID", "amount": "金额", "currency": "货币", "paymentMethod": "支付方式", "dashboardUrl": "仪表板链接", "platformName": "平台名称"}', 1),
('withdrawal_rejected', 'Withdrawal Rejected', 'transaction', 'Withdrawal Rejected - {{transactionId}}', '<p>Dear {{clientName}},</p><p>Your withdrawal request has been rejected.</p><p><strong>Transaction Details:</strong></p><ul><li>Transaction ID: {{transactionId}}</li><li>Amount: {{amount}} {{currency}}</li><li>Payment Method: {{paymentMethod}}</li><li>Status: Rejected</li></ul><p><strong>Reason:</strong> {{rejectionReason}}</p><p>If you have any questions, please contact our support team.</p><p>Best regards,<br>{{platformName}} Team</p>', 'client', '提款审核拒绝后发送的通知邮件', '{"clientName": "客户姓名", "transactionId": "交易ID", "amount": "金额", "currency": "货币", "paymentMethod": "支付方式", "rejectionReason": "拒绝原因", "platformName": "平台名称"}', 1),
('internal_transfer_completed', 'Internal Transfer Completed', 'transaction', 'Internal Transfer Completed - {{transactionId}}', '<p>Dear {{clientName}},</p><p>Your internal transfer has been completed.</p><p><strong>Transaction Details:</strong></p><ul><li>Transaction ID: {{transactionId}}</li><li>Amount: {{amount}} {{currency}}</li><li>From: {{fromAccount}}</li><li>To: {{toAccount}}</li><li>Status: Completed</li></ul><p><a href="{{dashboardUrl}}">View Transaction</a></p><p>Best regards,<br>{{platformName}} Team</p>', 'client', '内部转账完成后发送的通知邮件', '{"clientName": "客户姓名", "transactionId": "交易ID", "amount": "金额", "currency": "货币", "fromAccount": "转出账户", "toAccount": "转入账户", "dashboardUrl": "仪表板链接", "platformName": "平台名称"}', 1),
-- 账户相关 / Account Related
('trading_account_created', 'Trading Account Created', 'account', 'Trading Account Created', '<p>Dear {{clientName}},</p><p>Your new trading account has been successfully created.</p><p><strong>Account Details:</strong></p><ul><li>Account Number: {{accountNumber}}</li><li>Account Type: {{accountType}}</li><li>Platform: {{platformName}}</li></ul><p>You can now start trading with this account.</p><p><a href="{{dashboardUrl}}">Go to Dashboard</a></p><p>Best regards,<br>{{platformName}} Team</p>', 'client', '交易账户创建后发送的通知邮件', '{"clientName": "客户姓名", "accountNumber": "账户号码", "accountType": "账户类型", "platformName": "平台名称", "dashboardUrl": "仪表板链接"}', 1),
-- 通知相关 / Notification Related
('otp_code', 'OTP Verification Code', 'notification', 'Your Verification Code', '<p>Dear {{clientName}},</p><p>Your verification code is: <strong>{{otpCode}}</strong></p><p>This code will expire in {{expiryMinutes}} minutes.</p><p>If you did not request this code, please ignore this email.</p><p>Best regards,<br>{{platformName}} Team</p>', 'client', 'OTP验证码邮件', '{"clientName": "客户姓名", "otpCode": "验证码", "expiryMinutes": "过期分钟数", "platformName": "平台名称"}', 1);


-- 创建邮件模板板块设置表
-- 用于存储不同板块（Leads、ClientList等）的邮件模板下拉列表配置
-- 执行日期: 2025-11-27

CREATE TABLE IF NOT EXISTS `emailTemplateSectionSettings` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `sectionKey` VARCHAR(50) NOT NULL COMMENT '板块标识（如：leads, client_list）',
    `sectionName` VARCHAR(100) NOT NULL COMMENT '板块名称（如：Leads, Client List）',
    `templateIds` TEXT COMMENT '选中的邮件模板ID列表，JSON格式数组',
    `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `createdBy` INT(11) UNSIGNED DEFAULT NULL COMMENT '创建者ID',
    `updatedBy` INT(11) UNSIGNED DEFAULT NULL COMMENT '更新者ID',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_section` (`sectionKey`),
    KEY `idx_section_key` (`sectionKey`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邮件模板板块设置表';

-- 插入初始数据（Leads 和 ClientList 板块）
INSERT INTO `emailTemplateSectionSettings` (`sectionKey`, `sectionName`, `templateIds`, `createdAt`, `updatedAt`)
VALUES
    ('leads', 'Leads', '[]', NOW(), NOW()),
    ('client_list', 'Client List', '[]', NOW(), NOW())
    ON DUPLICATE KEY UPDATE `sectionName` = VALUES(`sectionName`);
