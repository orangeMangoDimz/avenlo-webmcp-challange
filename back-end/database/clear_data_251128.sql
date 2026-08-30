-- ============================================================
-- 日期 2025-11-28之前的批量删除不需要的数据SQL文件
-- 用途：初始化数据库，删除日志、交易记录等表数据
-- 注意：此文件只删除数据，不删除表结构
-- 执行前请务必备份数据库！
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. 删除日志表数据
-- ============================================================

-- 管理员登录日志
DELETE FROM `adminLoginLogs`;

-- 客户端活动日志
DELETE FROM `clientActivityLog`;

-- IB活动日志
DELETE FROM `ibActivityLog`;

-- Lead活动日志
DELETE FROM `leadActivityLog`;

-- 账户验证日志
DELETE FROM `accountVerificationLogs`;
DELETE FROM `account_verification_logs`;

-- KYC提交活动日志
DELETE FROM `kycSubmissionActivityLog`;

-- 自动批准日志
DELETE FROM `autoApprovalLog`;

-- ============================================================
-- 2. 删除历史记录表数据
-- ============================================================

-- 存款状态历史
DELETE FROM `depositStatusHistory`;

-- 提款状态历史
DELETE FROM `withdrawalStatusHistory`;

-- 内部转账状态历史
DELETE FROM `internalTransferStatusHistory`;

-- Lead状态历史
DELETE FROM `leadStatusHistory`;

-- Lead编辑历史
DELETE FROM `leadEditHistory`;

-- Lead分配历史
DELETE FROM `leadAssignmentHistory`;

-- IB申请状态历史
DELETE FROM `ibApplicationStatusHistory`;

-- IB佣金历史
DELETE FROM `ibCommissionHistory`;

-- KYC模板编辑历史
DELETE FROM `kycTemplateEditHistory`;

-- KYC设置变更日志
DELETE FROM `kycSettingsChangeLog`;

-- 登录设置变更日志
DELETE FROM `loginSettingsChangeLog`;

-- 管理员密码历史
DELETE FROM `adminPasswordHistory`;

-- ============================================================
-- 3. 删除交易记录表数据
-- ============================================================

-- 存款记录
DELETE FROM `deposits`;
DELETE FROM `depositNotes`;
DELETE FROM `depositTagAssignments`;

-- 提款记录
DELETE FROM `withdrawals`;
DELETE FROM `withdrawalDocumentRequestItems`;
DELETE FROM `withdrawalDocumentRequests`;
DELETE FROM `withdrawalOtpVerifications`;
DELETE FROM `withdrawalTagAssignments`;

-- 内部转账记录
DELETE FROM `internalTransfers`;
DELETE FROM `internalTransferNotes`;
DELETE FROM `internalTransferTagAssignments`;

-- ============================================================
-- 4. 删除通知表数据
-- ============================================================

-- 管理员通知
DELETE FROM `adminNotifications`;
DELETE FROM `adminNotificationDeliveries`;
DELETE FROM `adminSystemNotifications`;

-- 客户端通知
DELETE FROM `clientNotifications`;
DELETE FROM `clientNotificationDeliveries`;
DELETE FROM `clientSystemNotifications`;

-- ============================================================
-- 5. 删除会话表数据
-- ============================================================

-- 管理员会话
DELETE FROM `adminSessions`;

-- 客户端会话
DELETE FROM `clientSessions`;

-- ============================================================
-- 6. 删除验证和重置令牌表数据
-- ============================================================

-- 管理员密码重置令牌
DELETE FROM `adminPasswordResets`;

-- 客户端密码重置令牌
DELETE FROM `clientPasswordResets`;

-- 客户端邮箱验证
DELETE FROM `clientEmailVerifications`;

-- 账户验证
DELETE FROM `accountVerifications`;
DELETE FROM `account_verifications`;
DELETE FROM `accountVerificationFiles`;
DELETE FROM `account_verification_files`;

-- 提款OTP验证
DELETE FROM `withdrawalOtpVerifications`;

-- ============================================================
-- 7. 删除KYC相关数据
-- ============================================================

-- KYC提交记录
DELETE FROM `clientKycSubmissions`;
DELETE FROM `clientKycAnswers`;
DELETE FROM `clientKycDocumentSignatures`;

-- KYC重新提交
DELETE FROM `kycResubmitRequests`;
DELETE FROM `kycResubmitAnswers`;

-- KYC时间线
DELETE FROM `kycTimeline`;

-- KYC部分批准
DELETE FROM `kycSectionApprovals`;

-- ============================================================
-- 8. 删除Lead相关数据
-- ============================================================

-- Lead分配
DELETE FROM `leadAssignments`;
DELETE FROM `leadAssignmentNotes`;
DELETE FROM `leadTagAssignments`;
DELETE FROM `leadBulkOperations`;
DELETE FROM `leadKycStatus`;

-- ============================================================
-- 9. 删除IB相关数据
-- ============================================================

-- IB申请
DELETE FROM `ibApplications`;
DELETE FROM `ibApplicationDocumentAcknowledgements`;
DELETE FROM `ibApplicationProductFocus`;
DELETE FROM `ibApplicationRuleAssignments`;

-- IB关系
DELETE FROM `ibClientRelationships`;

-- IB邀请
DELETE FROM `ibInvitations`;

-- IB性能指标
DELETE FROM `ibPerformanceMetrics`;

-- IB统计摘要
DELETE FROM `ibStatisticsSummary`;

-- ============================================================
-- 10. 删除其他临时数据
-- ============================================================

-- 批量分配操作
DELETE FROM `bulkAssignmentOperations`;

-- 分配提醒
DELETE FROM `assignmentReminders`;

-- 客户端保存的钱包
DELETE FROM `clientSavedWallets`;

-- 客户端工单
DELETE FROM `clientTickets`;

-- 加密货币存款地址
DELETE FROM `cryptoDepositAddresses`;

-- 交易账户外部账户
DELETE FROM `tradingAccountExternalAccounts`;

-- ============================================================
-- 11. 重置自增ID（可选，根据需要取消注释）
-- ============================================================

-- ALTER TABLE `adminLoginLogs` AUTO_INCREMENT = 1;
-- ALTER TABLE `clientActivityLog` AUTO_INCREMENT = 1;
-- ALTER TABLE `deposits` AUTO_INCREMENT = 1;
-- ALTER TABLE `withdrawals` AUTO_INCREMENT = 1;
-- ALTER TABLE `internalTransfers` AUTO_INCREMENT = 1;
-- ALTER TABLE `clientKycSubmissions` AUTO_INCREMENT = 1;
-- ALTER TABLE `adminNotifications` AUTO_INCREMENT = 1;
-- ALTER TABLE `clientNotifications` AUTO_INCREMENT = 1;
-- ALTER TABLE `adminSessions` AUTO_INCREMENT = 1;
-- ALTER TABLE `clientSessions` AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 执行完成
-- ============================================================
-- 注意：如果需要重置自增ID，请取消上面第11部分的注释
-- ============================================================
