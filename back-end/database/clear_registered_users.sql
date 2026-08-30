-- ============================================================
-- 清空后台和客户端注册用户数据
-- 用途：只清空 adminUsers（后台管理员）和 clientUsers（客户端用户）及其直接关联数据
-- 注意：不改表结构，只清空用户数据。执行前请务必备份数据库！
-- 依赖：综合 all_crm_251128 ~ all_crm_update_260114 的完整库结构
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 一、清空客户端用户（clientUsers）相关数据
-- 说明：clientUsers 被众多表引用，需按依赖顺序先清子表
-- 若直接 DELETE clientUsers 会因 CASCADE 自动清理大部分关联表
-- 此处显式清空，确保无遗漏
-- ============================================================

-- 1. 客户端会话与验证
DELETE FROM `clientSessions`;
DELETE FROM `clientEmailVerifications`;
DELETE FROM `email_otp_verifications`;

-- 2. 客户端活动与通知
DELETE FROM `clientActivityLog`;
DELETE FROM `clientSystemNotifications`;
DELETE FROM `clientNotificationDeliveries`;
DELETE FROM `clientNotifications`;
DELETE FROM `clientTickets`;

-- 3. 客户端密码重置
DELETE FROM `clientPasswordResets`;

-- 4. 客户端保存的钱包与支付账户
DELETE FROM `clientSavedWallets`;
DELETE FROM `client_payment_accounts`;

-- 5. KYC 相关（clientId / submission 关联 clientUsers）
DELETE FROM `clientKycDocumentSignatures`;
DELETE FROM `clientKycAnswers`;
DELETE FROM `clientKycSubmissions`;
DELETE FROM `kycResubmitAnswers`;
DELETE FROM `kycResubmitRequests`;
DELETE FROM `kycSectionApprovals`;
DELETE FROM `kycTimeline`;

-- 6. 账户验证
DELETE FROM `account_verification_files`;
DELETE FROM `account_verification_logs`;
DELETE FROM `account_verifications`;
DELETE FROM `accountVerificationFiles`;
DELETE FROM `accountVerificationLogs`;
DELETE FROM `accountVerifications`;

-- 7. 存款/提款/内部转账及备注
DELETE FROM `depositTagAssignments`;
DELETE FROM `depositNotes`;
DELETE FROM `depositStatusHistory`;
DELETE FROM `deposits`;
DELETE FROM `withdrawalDocumentRequestItems`;
DELETE FROM `withdrawalDocumentRequests`;
DELETE FROM `withdrawalOtpVerifications`;
DELETE FROM `withdrawalTagAssignments`;
DELETE FROM `withdrawalStatusHistory`;
DELETE FROM `withdrawals`;
DELETE FROM `internalTransferTagAssignments`;
DELETE FROM `internalTransferNotes`;
DELETE FROM `internalTransferStatusHistory`;
DELETE FROM `internalTransfers`;

-- 8. 交易账户与订单
DELETE FROM `commissionCalculationQueue`;
DELETE FROM `ibCommissionCalculations`;
DELETE FROM `tradingAccountExternalAccounts`;
DELETE FROM `trading_account_balances`;
DELETE FROM `orders`;
DELETE FROM `tradingAccounts`;

-- 9. Lead 相关（leadId = clientUsers.id）
DELETE FROM `assignmentReminders`;
DELETE FROM `leadAssignments`;
DELETE FROM `leadAssignmentNotes`;
DELETE FROM `leadTagAssignments`;
DELETE FROM `leadBulkOperations`;
DELETE FROM `leadKycStatus`;
DELETE FROM `leadEditHistory`;
DELETE FROM `leadAssignmentHistory`;
DELETE FROM `leadActivityLog`;
DELETE FROM `leadStatusHistory`;
DELETE FROM `legalDocumentSignatures`;
DELETE FROM `bulkAssignmentOperations`;

-- 10. IB 相关（clientId / userId 关联 clientUsers）
DELETE FROM `clientIbAssignments`;
DELETE FROM `ibCommissionWithdrawals`;
DELETE FROM `ibClientRelationships`;
DELETE FROM `ibInvitations`;
DELETE FROM `ibApplicationCustomRuleCommissionTiers`;
DELETE FROM `ibApplicationCustomRuleAdditionalRules`;
DELETE FROM `ibApplicationCustomRuleProducts`;
DELETE FROM `ibApplicationCustomRules`;
DELETE FROM `ibApplicationDocumentAcknowledgements`;
DELETE FROM `ibApplicationProductFocus`;
DELETE FROM `ibApplicationRuleAssignments`;
DELETE FROM `ibApplicationStatusHistory`;
DELETE FROM `ibApplications`;

-- 11. 加密货币存款地址等
DELETE FROM `cryptoDepositAddresses`;

-- ============================================================
-- 二、清空后台用户（adminUsers）相关数据
-- ============================================================

-- 1. 管理员会话与登录日志
DELETE FROM `adminSessions`;
DELETE FROM `adminLoginLogs`;

-- 2. 管理员密码与令牌
DELETE FROM `adminPasswordHistory`;
DELETE FROM `adminPasswordResets`;

-- 3. 管理员通知
DELETE FROM `adminSystemNotifications`;
DELETE FROM `adminNotificationDeliveries`;
DELETE FROM `adminNotifications`;

-- 4. 管理员扩展信息与权限
DELETE FROM `adminUserPermissions`;
DELETE FROM `adminUserProfiles`;

-- 5. 核心用户表（最后清空）
DELETE FROM `adminUsers`;
DELETE FROM `clientUsers`;

-- ============================================================
-- 三、IB 合伙人（ibPartners）可选清理
-- 说明：ibPartners.userId 关联 clientUsers，若需彻底清理可取消下面注释
-- ============================================================
-- UPDATE `ibPartners` SET `userId` = NULL WHERE `userId` IS NOT NULL;
-- 如需清空 ibPartners 表：DELETE FROM `ibPartners`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 执行完成
-- ============================================================
