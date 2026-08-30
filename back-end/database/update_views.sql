-- ============================================================
-- 此文件为修复视图，将所有表名改为小写以适配生产环境
-- ============================================================
-- 修复视图 vw_client_kyc_progress
-- 将所有表名改为小写以适配生产环境
-- 执行日期: 2025-11-25
-- ============================================================

-- 删除旧视图
DROP VIEW IF EXISTS `vw_client_kyc_progress`;

-- 重建视图（所有表名使用小写）
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
    (SELECT COUNT(*) FROM clientkycanswers WHERE submissionId = s.id) AS answeredQuestions,
    t.totalQuestions,
    ROUND((SELECT COUNT(*) FROM clientkycanswers WHERE submissionId = s.id) / t.totalQuestions * 100, 2) AS progressPercentage,
    (SELECT COUNT(*) FROM clientkycdocumentsignatures WHERE submissionId = s.id) AS signedDocuments,
    (SELECT COUNT(*) FROM kyctemplatedocuments WHERE templateId = s.templateId AND isActive = 1) AS requiredDocuments
FROM clientkycsubmissions s
INNER JOIN clientusers cu ON s.clientId = cu.id
INNER JOIN kyctemplates t ON s.templateId = t.id
LEFT JOIN adminusers au ON s.reviewedBy = au.id
ORDER BY s.submittedAt DESC;


-- 执行日期: 2026-01-27
-- 更新 vAllTransactions 视图，添加 Internal Transfer 并显示交易账号名字
DROP VIEW IF EXISTS `vAllTransactions`;
CREATE VIEW `vAllTransactions` AS
SELECT
    d.id,
    d.transactionId,
    d.userId,
    u.firstName,
    u.lastName,
    u.email,
    'deposit' as transactionType,
    d.amount,
    d.netAmount,
    d.status,
    pm.methodName as paymentMethod,
    pm.methodType as paymentType,
    d.requestedAt,
    d.approvedAt,
    d.completedAt,
    d.approvedBy,
    NULL as rejectedAt,
    NULL as rejectedBy,
    d.createdAt,
    d.updatedAt
FROM deposits d
         INNER JOIN clientUsers u ON d.userId = u.id
         INNER JOIN paymentMethods pm ON d.paymentMethodId = pm.id
UNION ALL
SELECT
    w.id,
    w.transactionId,
    w.userId,
    u.firstName,
    u.lastName,
    u.email,
    'withdrawal' as transactionType,
    w.amount,
    w.netAmount,
    w.status,
    pm.methodName as paymentMethod,
    pm.methodType as paymentType,
    w.requestedAt,
    w.approvedAt,
    w.completedAt,
    w.approvedBy,
    w.rejectedAt,
    w.rejectedBy,
    w.createdAt,
    w.updatedAt
FROM withdrawals w
         INNER JOIN clientUsers u ON w.userId = u.id
         INNER JOIN paymentMethods pm ON w.paymentMethodId = pm.id
UNION ALL
SELECT
    it.id,
    it.transactionId,
    it.userId,
    u.firstName,
    u.lastName,
    u.email,
    'internal_transfer' as transactionType,
    it.amount,
    it.amount as netAmount,
    it.status,
    CONCAT(
            CASE
                WHEN it.fromType = 'wallet' OR it.fromType = 'available_balance' THEN 'Wallet'
                ELSE COALESCE(fromTA.accountNickname, 'Trading Account')
                END,
            ' → ',
            COALESCE(toTA.accountNickname, 'Trading Account')
        ) as paymentMethod,
    'internal' as paymentType,
    it.requestedAt,
    it.approvedAt,
    it.completedAt,
    it.approvedBy,
    NULL as rejectedAt,
    NULL as rejectedBy,
    it.createdAt,
    it.updatedAt
FROM internalTransfers it
         INNER JOIN clientUsers u ON it.userId = u.id
         LEFT JOIN tradingAccounts fromTA ON it.fromTradingAccountId = fromTA.id
         LEFT JOIN tradingAccounts toTA ON it.toTradingAccountId = toTA.id;
