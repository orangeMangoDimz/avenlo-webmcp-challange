-- ============================================================
-- 数据清理 SQL - 截至 2026-05-08
-- 用途：清空所有用户业务数据，保留管理员账号与配置数据
-- 注意：
--   1. 使用 TRUNCATE 清空数据并自动重置自增 ID
--   2. 保留 adminUsers / adminRoles / adminPermissions 等管理员体系
--   3. 保留 KYC 模板、邮件模板、支付配置、商城商品、国家、币种等配置
--      （tag 配置：deposit / withdrawal / lead / search 的 tag 表本次也清空）
--   4. 执行前请务必备份数据库！
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. 管理员相关 - 仅清理日志/会话/通知/重置令牌，保留账号本体
-- ============================================================

-- 管理员登录日志
TRUNCATE TABLE `adminLoginLogs`;

-- 管理员密码历史 / 重置令牌
TRUNCATE TABLE `adminPasswordHistory`;
TRUNCATE TABLE `adminPasswordResets`;

-- 管理员会话与预览 token
TRUNCATE TABLE `adminSessions`;
TRUNCATE TABLE `admin_preview_tokens`;

-- 管理员通知
TRUNCATE TABLE `adminNotificationDeliveries`;
TRUNCATE TABLE `adminNotifications`;
TRUNCATE TABLE `adminSystemNotifications`;

-- 登录设置变更日志
TRUNCATE TABLE `loginSettingsChangeLog`;

-- ============================================================
-- 2. 客户端用户及其衍生数据
-- ============================================================

-- 客户端会话 / 验证 / 重置
TRUNCATE TABLE `clientSessions`;
TRUNCATE TABLE `appSessions`;
TRUNCATE TABLE `appWebHandoffs`;
TRUNCATE TABLE `clientPasswordResets`;
TRUNCATE TABLE `clientEmailVerifications`;
TRUNCATE TABLE `email_otp_verifications`;

-- 客户端通知
TRUNCATE TABLE `clientNotificationDeliveries`;
TRUNCATE TABLE `clientNotifications`;
TRUNCATE TABLE `clientSystemNotifications`;

-- 客户端活动 / 工单 / 钱包 / 支付账户
TRUNCATE TABLE `clientActivityLog`;
TRUNCATE TABLE `clientTickets`;
TRUNCATE TABLE `clientSavedWallets`;
TRUNCATE TABLE `client_payment_accounts`;

-- 客户端与 IB 关系绑定（关系数据，不是配置）
TRUNCATE TABLE `clientIbPartnerAssignments`;

-- 客户端用户主表
TRUNCATE TABLE `clientUsers`;

-- ============================================================
-- 3. 账户验证（KYC 之外的账户级验证）
-- ============================================================

TRUNCATE TABLE `accountVerificationLogs`;
TRUNCATE TABLE `account_verification_logs`;
TRUNCATE TABLE `accountVerificationFiles`;
TRUNCATE TABLE `account_verification_files`;
TRUNCATE TABLE `accountVerifications`;
TRUNCATE TABLE `account_verifications`;

-- ============================================================
-- 4. KYC 提交数据（保留模板/问题/规则等配置）
-- ============================================================

TRUNCATE TABLE `clientKycDocumentSignatures`;
TRUNCATE TABLE `clientKycAnswers`;
TRUNCATE TABLE `kycResubmitAnswers`;
TRUNCATE TABLE `kycResubmitRequests`;
TRUNCATE TABLE `kycSectionApprovals`;
TRUNCATE TABLE `kycSubmissionActivityLog`;
TRUNCATE TABLE `kycTimeline`;
TRUNCATE TABLE `clientKycSubmissions`;

-- KYC 模板 / 设置变更历史
TRUNCATE TABLE `kycTemplateEditHistory`;
TRUNCATE TABLE `kycSettingsChangeLog`;

-- ============================================================
-- 5. 提款验证提交数据（保留模板/问题/规则等配置）
-- ============================================================

TRUNCATE TABLE `clientWithdrawalVerificationDocumentSignatures`;
TRUNCATE TABLE `clientWithdrawalVerificationAnswers`;
TRUNCATE TABLE `withdrawalVerificationResubmitAnswers`;
TRUNCATE TABLE `withdrawalVerificationResubmitRequests`;
TRUNCATE TABLE `withdrawalVerificationSectionApprovals`;
TRUNCATE TABLE `withdrawalVerificationActivityLog`;
TRUNCATE TABLE `withdrawalVerificationTimeline`;
TRUNCATE TABLE `clientWithdrawalVerificationSubmissions`;

-- 提款验证模板编辑历史
TRUNCATE TABLE `withdrawalVerificationTemplateEditHistory`;

-- ============================================================
-- 6. 资金类 - 入金 / 出金 / 内转
-- ============================================================

-- 存款
TRUNCATE TABLE `depositTagAssignments`;
TRUNCATE TABLE `depositTags`;
TRUNCATE TABLE `depositNotes`;
TRUNCATE TABLE `depositStatusHistory`;
TRUNCATE TABLE `deposits`;

-- 提款
TRUNCATE TABLE `withdrawalTagAssignments`;
TRUNCATE TABLE `withdrawalTags`;
TRUNCATE TABLE `withdrawalDocumentRequestItems`;
TRUNCATE TABLE `withdrawalDocumentRequests`;
TRUNCATE TABLE `withdrawalOtpVerifications`;
TRUNCATE TABLE `withdrawalStatusHistory`;
TRUNCATE TABLE `withdrawals`;

-- 内部转账
TRUNCATE TABLE `internalTransferTagAssignments`;
TRUNCATE TABLE `internalTransferNotes`;
TRUNCATE TABLE `internalTransferStatusHistory`;
TRUNCATE TABLE `internalTransfers`;

-- 支付网关回调日志
TRUNCATE TABLE `paymentProcessorCallbackLogs`;

-- 客户端加密货币存款地址
TRUNCATE TABLE `cryptoDepositAddresses`;

-- ============================================================
-- 7. 交易账户 / 持仓 / 订单
-- ============================================================

TRUNCATE TABLE `tradingAccountExternalAccounts`;
TRUNCATE TABLE `trading_account_balances`;
TRUNCATE TABLE `tradingAccounts`;

TRUNCATE TABLE `positions`;
TRUNCATE TABLE `orders`;

-- ============================================================
-- 8. Lead 相关数据
-- ============================================================

TRUNCATE TABLE `leadTagAssignments`;
TRUNCATE TABLE `leadTags`;
TRUNCATE TABLE `leadAssignmentNotes`;
TRUNCATE TABLE `leadAssignmentHistory`;
TRUNCATE TABLE `leadAssignments`;
TRUNCATE TABLE `leadStatusHistory`;
TRUNCATE TABLE `leadEditHistory`;
TRUNCATE TABLE `leadKycStatus`;
TRUNCATE TABLE `leadBulkOperations`;
TRUNCATE TABLE `leadActivityLog`;

-- 分配相关
TRUNCATE TABLE `assignmentReminders`;
TRUNCATE TABLE `bulkAssignmentOperations`;
TRUNCATE TABLE `autoApprovalLog`;

-- 销售业绩 / 绑定 / 推荐访问日志（保留 salesRepresentatives 与 sales_referral_settings 配置）
TRUNCATE TABLE `salesRepPerformance`;
TRUNCATE TABLE `sales_bind`;
TRUNCATE TABLE `sales_referral_visit_log`;

-- ============================================================
-- 9. IB 相关数据（保留 ibCommissionRules / ibTierLevels / ibProgramSettings
--    / ibCustomSecurities / ibCustomSymbols / ibDocumentTemplates 等配置）
-- ============================================================

-- IB 申请及子表
TRUNCATE TABLE `ibApplicationCustomRuleAdditionalRules`;
TRUNCATE TABLE `ibApplicationCustomRuleCommissionTiers`;
TRUNCATE TABLE `ibApplicationCustomRuleProducts`;
TRUNCATE TABLE `ibApplicationCustomRules`;
TRUNCATE TABLE `ibApplicationDocumentAcknowledgements`;
TRUNCATE TABLE `ibApplicationProductFocus`;
TRUNCATE TABLE `ibApplicationRuleAssignments`;
TRUNCATE TABLE `ibApplicationStatusHistory`;
TRUNCATE TABLE `ibApplications`;

-- IB 客户关系 / 邀请 / 推荐码
TRUNCATE TABLE `ibClientRelationships`;
TRUNCATE TABLE `ibInvitations`;
TRUNCATE TABLE `ibReferralCodes`;
TRUNCATE TABLE `ib_referral_settings`;
TRUNCATE TABLE `ib_referral_visit_log`;

-- IB 网络 / 绑定 / 状态历史 / 交易组绑定
TRUNCATE TABLE `ibNetworkHierarchy`;
TRUNCATE TABLE `ib_partner_bind`;
TRUNCATE TABLE `ib_partner_status_history`;
TRUNCATE TABLE `ib_partner_trading_groups`;

-- IB 文档（partner 上传的实际文档，不是模板）
TRUNCATE TABLE `ibPartnerDocumentAcknowledgements`;
TRUNCATE TABLE `ibPartnerDocuments`;

-- IB 佣金计算 / 历史 / 提取 / 队列 / 订单
TRUNCATE TABLE `commissionCalculationQueue`;
TRUNCATE TABLE `ibCommissionCalculations`;
TRUNCATE TABLE `ibCommissionHistory`;
TRUNCATE TABLE `ibCommissionWithdrawals`;
TRUNCATE TABLE `ib_commission_order`;

-- IB 业绩 / 统计 / 支付设置 / 规则关联
TRUNCATE TABLE `ibPerformanceMetrics`;
TRUNCATE TABLE `ibStatisticsSummary`;
TRUNCATE TABLE `ibPartnerPaymentSettings`;
TRUNCATE TABLE `ibPartnerRuleAssignments`;
TRUNCATE TABLE `ib_partner_rules`;

-- IB 活动日志
TRUNCATE TABLE `ibActivityLog`;

-- IB 主表
TRUNCATE TABLE `ibPartners`;

-- ============================================================
-- 10. 法律协议签署记录（保留 legalDocuments 模板本体）
-- ============================================================

TRUNCATE TABLE `legalDocumentSignatures`;

-- ============================================================
-- 11. 积分商城 - 用户兑换 / 流水（保留商品 / 分类 / 设置等配置）
-- ============================================================

TRUNCATE TABLE `points_mall_redemption_status_logs`;
TRUNCATE TABLE `points_mall_redemption_shipping`;
TRUNCATE TABLE `points_mall_redemptions`;
TRUNCATE TABLE `points_mall_ledger`;
TRUNCATE TABLE `points_mall_wallet_ledger`;

-- ============================================================
-- 12. 搜索标签（搜索历史/常用筛选保存）
-- ============================================================

TRUNCATE TABLE `searchTags`;
TRUNCATE TABLE `transactionSearchTags`;

INSERT INTO positions (name, description, displayOrder, isActive) VALUES
  ('Department Head', 'Department head, responsible for overall department management and strategic decisions', 4, 1),
  ('Manager',         'Manager, responsible for team management and operations supervision',                     3, 1),
  ('Team Lead',       'Team lead, responsible for leading and coordinating team work',                           2, 1),
  ('Staff',           'Staff member, responsible for executing specific tasks and projects',                     1, 1);

INSERT IGNORE INTO `leadTags` (`tagName`, `tagColor`, `description`, `isSystemTag`) VALUES
('New Lead',   '#fbbf24', 'Newly registered lead',     1),
('Hot Lead',   '#f97316', 'High potential lead',       1),
('VIP',        '#8b5cf6', 'VIP customer',              1),
('Premium',    '#3b82f6', 'Premium tier customer',     1),
('High Value', '#6366f1', 'High value potential',      1),
('Urgent',     '#ef4444', 'Requires urgent attention', 1),
('Follow-up',  '#10b981', 'Needs follow-up',           1),
('IB',         '#2563eb', 'IB partner',                1);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 执行完成（TRUNCATE 已自动重置所有表的 AUTO_INCREMENT）
-- ============================================================
