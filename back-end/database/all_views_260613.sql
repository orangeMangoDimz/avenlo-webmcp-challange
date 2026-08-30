-- ============================================================
-- utrada_crm 全量视图（23 个，最新版本，camelCase 表名）
-- 整理日期: 2026-06-13
-- 每个 view 取自其最新的源 SQL 文件（非数据库直接导出，
-- 避免 macOS lower_case_table_names=2 导致表名引用被转小写）
-- 已去除 DEFINER / ALGORITHM / SQL SECURITY
-- ============================================================

-- ============================================================
-- vAdminUserPermissions
-- 来源: database/修复数据表被小写的问题/update_views_camelcase.sql
-- ============================================================
DROP VIEW IF EXISTS `vAdminUserPermissions`;
CREATE VIEW `vAdminUserPermissions` AS
SELECT
    u.id AS userId,
    u.username AS username,
    u.email AS email,
    p.permissionKey AS permissionKey,
    p.permissionName AS permissionName,
    p.permissionDisplayName AS permissionDisplayName,
    p.module AS module,
    'role' AS permissionSource,
    1 AS isGranted
FROM adminUsers u
INNER JOIN adminRoles r ON u.roleId = r.id
INNER JOIN adminRolePermissions rp ON r.id = rp.roleId
INNER JOIN adminPermissions p ON rp.permissionId = p.id
WHERE u.deletedAt IS NULL AND p.isActive = 1
UNION
SELECT
    up.userId AS userId,
    u.username AS username,
    u.email AS email,
    p.permissionKey AS permissionKey,
    p.permissionName AS permissionName,
    p.permissionDisplayName AS permissionDisplayName,
    p.module AS module,
    'custom' AS permissionSource,
    up.isGranted AS isGranted
FROM adminUserPermissions up
INNER JOIN adminUsers u ON up.userId = u.id
INNER JOIN adminPermissions p ON up.permissionId = p.id
WHERE u.deletedAt IS NULL AND p.isActive = 1
AND (up.expiresAt IS NULL OR up.expiresAt > NOW());

-- ============================================================
-- vAdminUsersFull
-- 来源: database/all_crm_update_260106.sql
-- ============================================================
DROP VIEW IF EXISTS `vAdminUsersFull`;
CREATE VIEW `vAdminUsersFull` AS
SELECT
    u.id,
    u.username,
    u.email,
    u.fullName,
    u.avatarInitials,
    u.avatarColor,
    u.status,
    u.isLocked,
    u.lastLoginAt,
    u.lastLoginIp,
    u.createdAt,
    u.roleId,
    u.positionId,
    u.departmentId,
    r.roleKey,
    r.roleName,
    r.roleDisplayName,
    r.badgeColor,
    r.level AS roleLevel,
    p.phone,
    p.phoneCountryCode,
    p.department,
    p.timezone,
    p.language
FROM adminUsers u
         LEFT JOIN adminRoles r ON u.roleId = r.id
         LEFT JOIN adminUserProfiles p ON u.id = p.userId
WHERE u.deletedAt IS NULL;

-- ============================================================
-- vAllTransactions
-- 来源: database/all_crm_update_260324.sql
-- ============================================================
DROP VIEW IF EXISTS `vAllTransactions`;
CREATE VIEW `vAllTransactions` AS
SELECT
    d.id AS id,
    d.transactionId AS transactionId,
    d.userId AS userId,
    u.firstName AS firstName,
    u.lastName AS lastName,
    u.email AS email,
    'deposit' AS transactionType,
    d.amount AS amount,
    d.quotedAmount AS quotedAmount,
    d.status AS status,
    COALESCE(pgs.gatewayName, 'Deposit') AS paymentMethod,
    COALESCE(pgs.type, 'fiat') AS paymentType,
    d.requestedAt AS requestedAt,
    d.approvedAt AS approvedAt,
    d.completedAt AS completedAt,
    d.approvedBy AS approvedBy,
    d.rejectedAt AS rejectedAt,
    d.rejectedBy AS rejectedBy,
    NULL AS fromTradingAccountId,
    d.tradingAccountId AS targetTradingAccountId,
    taea.providerAccountId AS targetPlatformAccountId,
    CASE WHEN d.tradingAccountId IS NULL THEN 'wallet' ELSE 'platform' END AS fromType,
    NULL AS fromAccountNumber,
    NULL AS fromAccountNickname,
    ta.accountNumber AS targetAccountNumber,
    ta.accountNickname AS targetAccountNickname,
    tp.platformKey AS targetPlatformKey,
    tp.displayName AS targetPlatformName,
    d.createdAt AS createdAt,
    d.updatedAt AS updatedAt
FROM deposits d
         INNER JOIN clientUsers u ON d.userId = u.id
         LEFT JOIN paymentGatewaySettings pgs ON pgs.id = d.gatewaySettingId
         LEFT JOIN tradingAccounts ta ON d.tradingAccountId = ta.id
         LEFT JOIN tradingAccountExternalAccounts taea ON ta.id = taea.tradingAccountId
         LEFT JOIN tradingPlatforms tp ON ta.platformId = tp.id
UNION ALL
SELECT
    w.id AS id,
    w.transactionId AS transactionId,
    w.userId AS userId,
    u.firstName AS firstName,
    u.lastName AS lastName,
    u.email AS email,
    'withdrawal' AS transactionType,
    w.amount AS amount,
    w.quotedAmount AS quotedAmount,
    w.status AS status,
    COALESCE(pgs.gatewayName, 'Withdrawal') AS paymentMethod,
    COALESCE(pgs.type, 'fiat') AS paymentType,
    w.requestedAt AS requestedAt,
    w.approvedAt AS approvedAt,
    w.completedAt AS completedAt,
    w.approvedBy AS approvedBy,
    w.rejectedAt AS rejectedAt,
    w.rejectedBy AS rejectedBy,
    w.tradingAccountId AS fromTradingAccountId,
    w.tradingAccountId AS targetTradingAccountId,
    NULL AS targetPlatformAccountId,
    CASE WHEN w.tradingAccountId IS NULL THEN 'wallet' ELSE 'platform' END AS fromType,
    ta.accountNumber AS fromAccountNumber,
    ta.accountNickname AS fromAccountNickname,
    ta.accountNumber AS targetAccountNumber,
    ta.accountNickname AS targetAccountNickname,
    tp.platformKey AS targetPlatformKey,
    tp.displayName AS targetPlatformName,
    w.createdAt AS createdAt,
    w.updatedAt AS updatedAt
FROM withdrawals w
         INNER JOIN clientUsers u ON w.userId = u.id
         LEFT JOIN paymentGatewaySettings pgs ON pgs.id = w.gatewaySettingId
         LEFT JOIN tradingAccounts ta ON w.tradingAccountId = ta.id
         LEFT JOIN tradingPlatforms tp ON ta.platformId = tp.id
UNION ALL
SELECT
    it.id AS id,
    it.transactionId AS transactionId,
    it.userId AS userId,
    u.firstName AS firstName,
    u.lastName AS lastName,
    u.email AS email,
    'internal_transfer' AS transactionType,
    it.amount AS amount,
    it.amount AS quotedAmount,
    it.status AS status,
    CONCAT(
            CASE
                WHEN it.fromType = 'wallet' OR it.fromType = 'available_balance' THEN 'Wallet'
                ELSE COALESCE(fromta.accountNickname, 'Trading Account')
                END,
            ' -> ',
            COALESCE(tota.accountNickname, 'Trading Account')
    ) AS paymentMethod,
    'internal' AS paymentType,
    it.requestedAt AS requestedAt,
    it.approvedAt AS approvedAt,
    it.completedAt AS completedAt,
    it.approvedBy AS approvedBy,
    NULL AS rejectedAt,
    NULL AS rejectedBy,
    it.fromTradingAccountId AS fromTradingAccountId,
    it.toTradingAccountId AS targetTradingAccountId,
    NULL AS targetPlatformAccountId,
    it.fromType AS fromType,
    fromta.accountNumber AS fromAccountNumber,
    fromta.accountNickname AS fromAccountNickname,
    tota.accountNumber AS targetAccountNumber,
    tota.accountNickname AS targetAccountNickname,
    tp.platformKey AS targetPlatformKey,
    tp.displayName AS targetPlatformName,
    it.createdAt AS createdAt,
    it.updatedAt AS updatedAt
FROM internalTransfers it
         INNER JOIN clientUsers u ON it.userId = u.id
         LEFT JOIN tradingAccounts fromta ON it.fromTradingAccountId = fromta.id
         LEFT JOIN tradingAccounts tota ON it.toTradingAccountId = tota.id
         LEFT JOIN tradingPlatforms tp ON tota.platformId = tp.id;

-- ============================================================
-- vDepositsSummary
-- 来源: database/all_crm_update_260324.sql
-- ============================================================
DROP VIEW IF EXISTS `vDepositsSummary`;
CREATE VIEW `vDepositsSummary` AS
SELECT
    d.id AS id,
    d.transactionId AS transactionId,
    d.userId AS userId,
    CONCAT(u.firstName, ' ', u.lastName) AS clientName,
    u.email AS clientEmail,
    d.amount AS amount,
    d.quotedAmount AS quotedAmount,
    d.status AS status,
    COALESCE(pgs.gatewayName, 'Deposit') AS paymentMethod,
    COALESCE(pgs.type, 'fiat') AS paymentType,
    d.requestedAt AS requestedAt,
    d.approvedAt AS approvedAt,
    d.completedAt AS completedAt,
    d.rejectedAt AS rejectedAt,
    NULL AS fromTradingAccountId,
    d.tradingAccountId AS targetTradingAccountId,
    taea.providerAccountId AS targetPlatformAccountId,
    CASE WHEN d.tradingAccountId IS NULL THEN 'wallet' ELSE 'platform' END AS fromType,
    NULL AS fromAccountNumber,
    NULL AS fromAccountNickname,
    ta.accountNumber AS targetAccountNumber,
    ta.accountNickname AS targetAccountNickname,
    tp.platformKey AS targetPlatformKey,
    tp.displayName AS targetPlatformName,
    rr.reasonTitle AS rejectionReason,
    GROUP_CONCAT(dt.tagName SEPARATOR ', ') AS tags,
    d.createdAt AS createdAt
FROM deposits d
         INNER JOIN clientUsers u ON d.userId = u.id
         LEFT JOIN paymentGatewaySettings pgs ON pgs.id = d.gatewaySettingId
         LEFT JOIN tradingAccounts ta ON d.tradingAccountId = ta.id
         LEFT JOIN tradingAccountExternalAccounts taea ON ta.id = taea.tradingAccountId
         LEFT JOIN tradingPlatforms tp ON ta.platformId = tp.id
         LEFT JOIN rejectionReasons rr ON d.rejectionReasonId = rr.id
         LEFT JOIN depositTagAssignments dta ON d.id = dta.depositId
         LEFT JOIN depositTags dt ON dta.tagId = dt.id
GROUP BY d.id;

-- ============================================================
-- vWithdrawalsSummary
-- 来源: database/all_crm_update_260324.sql
-- ============================================================
DROP VIEW IF EXISTS `vWithdrawalsSummary`;
CREATE VIEW `vWithdrawalsSummary` AS
SELECT
    w.id AS id,
    w.transactionId AS transactionId,
    w.userId AS userId,
    CONCAT(u.firstName, ' ', u.lastName) AS clientName,
    u.email AS clientEmail,
    w.amount AS amount,
    w.quotedAmount AS quotedAmount,
    w.status AS status,
    COALESCE(pgs.gatewayName, 'Withdrawal') AS paymentMethod,
    COALESCE(pgs.type, 'fiat') AS paymentType,
    w.requestedAt AS requestedAt,
    w.approvedAt AS approvedAt,
    w.completedAt AS completedAt,
    w.rejectedAt AS rejectedAt,
    wrr.reasonTitle AS rejectionReason,
    GROUP_CONCAT(wt.tagName SEPARATOR ', ') AS tags,
    w.createdAt AS createdAt
FROM withdrawals w
INNER JOIN clientUsers u ON w.userId = u.id
LEFT JOIN paymentGatewaySettings pgs ON pgs.id = w.gatewaySettingId
LEFT JOIN rejectionReasons wrr ON w.rejectionReasonId = wrr.id
LEFT JOIN withdrawalTagAssignments wta ON w.id = wta.withdrawalId
LEFT JOIN withdrawalTags wt ON wta.tagId = wt.id
GROUP BY w.id;

-- ============================================================
-- vw_active_assignments
-- 来源: database/修复数据表被小写的问题/update_views_camelcase.sql
-- ============================================================
DROP VIEW IF EXISTS `vw_active_assignments`;
CREATE VIEW `vw_active_assignments` AS
SELECT
    la.id AS assignmentId,
    la.leadId AS leadId,
    cu.firstName AS leadFirstName,
    cu.lastName AS leadLastName,
    cu.email AS leadEmail,
    cu.phone AS leadPhone,
    cu.country AS leadCountry,
    la.salesRepId AS salesRepId,
    sr.firstName AS repFirstName,
    sr.lastName AS repLastName,
    sr.email AS repEmail,
    CONCAT(sr.firstName, ' ', sr.lastName) AS repFullName,
    la.assignmentStatus AS assignmentStatus,
    la.priority AS priority,
    la.assignedAt AS assignedAt,
    la.lastContactedAt AS lastContactedAt,
    la.expectedCloseDate AS expectedCloseDate,
    (SELECT COUNT(*) FROM leadAssignmentNotes WHERE assignmentId = la.id) AS notesCount,
    (SELECT COUNT(*) FROM assignmentReminders WHERE assignmentId = la.id AND isCompleted = 0) AS pendingReminders
FROM leadAssignments la
INNER JOIN clientUsers cu ON la.leadId = cu.id
INNER JOIN salesRepresentatives sr ON la.salesRepId = sr.id
WHERE la.isActive = 1
ORDER BY la.assignedAt DESC;

-- ============================================================
-- vw_client_kyc_progress
-- 来源: database/all_crm_update_260511.sql
-- ============================================================
DROP VIEW IF EXISTS `vw_client_kyc_progress`;
CREATE VIEW `vw_client_kyc_progress` AS
SELECT
  `s`.`id` AS `submissionId`,
  `s`.`clientId` AS `clientId`,
  `cu`.`email` AS `clientEmail`,
  `cu`.`firstName` AS `firstName`,
  `cu`.`lastName` AS `lastName`,
  `s`.`templateId` AS `templateId`,
  `t`.`templateName` AS `templateName`,
  `s`.`isThirdParty` AS `isThirdParty`,
  `t`.`isThirdPartyEnabled` AS `isThirdPartyEnabled`,
  -- provider 优先用 kycTemplates 字段，回落到通过 externalKycTemplates 关联的 gateway，
  -- 防止有些历史模板 isThirdPartyEnabled=1 但 thirdPartyProvider 字段是 NULL
  COALESCE(`t`.`thirdPartyProvider`, `g`.`provider`) AS `thirdPartyProvider`,
  `s`.`externalId` AS `externalId`,
  -- detailUrl：gateway.detailUrl + submission.externalId 都齐才拼，缺一个就 NULL，不做任何兜底
  CASE
    WHEN `g`.`detailUrl` IS NOT NULL AND `g`.`detailUrl` <> ''
         AND `s`.`externalId` IS NOT NULL AND `s`.`externalId` <> ''
    THEN CONCAT(`g`.`detailUrl`, `s`.`externalId`)
    ELSE NULL
  END AS `detailUrl`,
  `s`.`submissionStatus` AS `submissionStatus`,
  `s`.`submittedAt` AS `submittedAt`,
  `s`.`reviewedAt` AS `reviewedAt`,
  `s`.`reviewedBy` AS `reviewerId`,
  COALESCE(`au`.`fullName`, `au`.`username`, '') AS `reviewerName`,
  `au`.`username` AS `reviewerUsername`,
  (SELECT COUNT(0) FROM `clientKycAnswers` WHERE `clientKycAnswers`.`submissionId` = `s`.`id`) AS `answeredQuestions`,
  `t`.`totalQuestions` AS `totalQuestions`,
  ROUND((((SELECT COUNT(0) FROM `clientKycAnswers` WHERE `clientKycAnswers`.`submissionId` = `s`.`id`) / `t`.`totalQuestions`) * 100), 2) AS `progressPercentage`,
  (SELECT COUNT(0) FROM `clientKycDocumentSignatures` WHERE `clientKycDocumentSignatures`.`submissionId` = `s`.`id`) AS `signedDocuments`,
  (SELECT COUNT(0) FROM `kycTemplateDocuments` WHERE `kycTemplateDocuments`.`templateId` = `s`.`templateId` AND `kycTemplateDocuments`.`isActive` = 1) AS `requiredDocuments`
FROM `clientKycSubmissions` `s`
  JOIN `clientUsers` `cu` ON `s`.`clientId` = `cu`.`id`
  JOIN `kycTemplates` `t` ON `s`.`templateId` = `t`.`id`
  LEFT JOIN `adminUsers` `au` ON `s`.`reviewedBy` = `au`.`id`
  LEFT JOIN `externalKycTemplates` `et` ON `et`.`id` = `t`.`externalTemplateId`
  LEFT JOIN `externalKycGateways` `g` ON `g`.`id` = `et`.`gatewayId`
ORDER BY `s`.`submittedAt` DESC;

-- ============================================================
-- vw_client_kyc_status
-- 来源: database/修复数据表被小写的问题/update_views_camelcase.sql
-- ============================================================
DROP VIEW IF EXISTS `vw_client_kyc_status`;
CREATE VIEW `vw_client_kyc_status` AS
SELECT
    cu.id AS clientId,
    cu.email AS email,
    cu.firstName AS firstName,
    cu.lastName AS lastName,
    cu.country AS country,
    s.id AS submissionId,
    s.templateId AS templateId,
    t.templateName AS templateName,
    COALESCE(s.submissionStatus, 'draft') AS submissionStatus,
    s.submittedAt AS submittedAt,
    s.reviewedAt AS reviewedAt,
    s.rejectionReason AS rejectionReason,
    s.createdAt AS createdAt,
    s.updatedAt AS updatedAt,
    (SELECT COUNT(*) FROM clientKycAnswers WHERE submissionId = s.id) AS answeredQuestions,
    COALESCE(t.totalQuestions, 0) AS totalQuestions,
    CASE
        WHEN t.totalQuestions > 0
        THEN ROUND(((SELECT COUNT(*) FROM clientKycAnswers WHERE submissionId = s.id) / t.totalQuestions) * 100, 2)
        ELSE 0
    END AS progressPercentage,
    (SELECT COUNT(*) FROM clientKycDocumentSignatures WHERE submissionId = s.id) AS signedDocuments,
    (SELECT COUNT(*) FROM kycTemplateDocuments WHERE templateId = s.templateId AND isActive = 1) AS requiredDocuments
FROM clientUsers cu
LEFT JOIN (
    SELECT
        s1.id,
        s1.clientId,
        s1.templateId,
        s1.submissionStatus,
        s1.submittedAt,
        s1.reviewedAt,
        s1.reviewedBy,
        s1.approvalNotes,
        s1.rejectionReason,
        s1.ipAddress,
        s1.userAgent,
        s1.createdAt,
        s1.updatedAt
    FROM clientKycSubmissions s1
    INNER JOIN (
        SELECT clientId, MAX(createdAt) AS maxCreated
        FROM clientKycSubmissions
        GROUP BY clientId
    ) s2 ON s1.clientId = s2.clientId AND s1.createdAt = s2.maxCreated
) s ON cu.id = s.clientId
LEFT JOIN kycTemplates t ON s.templateId = t.id
ORDER BY cu.id;

-- ============================================================
-- vw_client_management
-- 来源: database/修复数据表被小写的问题/update_views_camelcase.sql
-- ============================================================
DROP VIEW IF EXISTS `vw_client_management`;
CREATE VIEW `vw_client_management` AS
SELECT
    c.id AS id,
    c.email AS email,
    c.firstName AS firstName,
    c.lastName AS lastName,
    CONCAT(c.firstName, ' ', c.lastName) AS fullName,
    c.phone AS phone,
    c.country AS country,
    c.status AS accountStatus,
    c.kycStatus AS kycStatus,
    c.verifiedAt AS verifiedAt,
    c.tags AS tags,
    c.assignedTo AS assignedTo,
    c.notes AS notes,
    c.createdAt AS createdAt,
    c.updatedAt AS updatedAt,
    c.lastLoginAt AS lastLoginAt,
    s.id AS submissionId,
    s.submittedAt AS submittedAt,
    s.reviewedAt AS reviewedAt,
    s.reviewedBy AS reviewedBy,
    s.approvalNotes AS approvalNotes,
    s.rejectionReason AS rejectionReason,
    a.fullName AS reviewerName,
    a.username AS reviewerUsername,
    a.email AS reviewerEmail,
    aa.fullName AS assignedAdminName,
    aa.username AS assignedAdminUsername,
    aa.email AS assignedAdminEmail,
    (SELECT COUNT(*) FROM clientKycSubmissions WHERE clientId = c.id) AS totalSubmissions,
    (SELECT COUNT(*) FROM clientActivityLog WHERE clientId = c.id) AS totalActivities
FROM clientUsers c
LEFT JOIN clientKycSubmissions s ON c.kycSubmissionId = s.id
LEFT JOIN adminUsers a ON s.reviewedBy = a.id
LEFT JOIN adminUsers aa ON c.assignedTo = aa.id
WHERE c.kycStatus = 'approved'
ORDER BY c.verifiedAt DESC;

-- ============================================================
-- vw_client_withdrawal_verification_progress
-- 来源: database/all_crm_update_260310.sql
-- ============================================================
DROP VIEW IF EXISTS `vw_client_withdrawal_verification_progress`;
CREATE VIEW `vw_client_withdrawal_verification_progress` AS
SELECT
  s.id AS submissionId,
  s.clientId,
  CONCAT(cu.firstName, ' ', cu.lastName) AS clientName,
  cu.email AS clientEmail,
  s.templateId,
  t.templateName,
  s.gatewaySettingId,
  pgs.gatewayKey,
  pgs.gatewayName,
  s.paymentMethodId,
  pm.methodName,
  s.submissionStatus,
  s.submittedAt,
  s.reviewedAt,
  s.reviewedBy,
  s.approvalNotes,
  s.rejectionReason,
  s.createdAt,
  s.updatedAt,
  (SELECT COUNT(*) FROM withdrawalVerificationQuestions q WHERE q.templateId = s.templateId AND q.isActive = 1) AS totalQuestions,
  (SELECT COUNT(*) FROM clientWithdrawalVerificationAnswers a WHERE a.submissionId = s.id) AS answeredQuestions,
  (SELECT COUNT(*) FROM withdrawalVerificationTemplateDocuments d WHERE d.templateId = s.templateId AND d.isActive = 1) AS totalDocuments,
  (SELECT COUNT(*) FROM clientWithdrawalVerificationDocumentSignatures ds WHERE ds.submissionId = s.id) AS signedDocuments
FROM clientWithdrawalVerificationSubmissions s
INNER JOIN clientUsers cu ON cu.id = s.clientId
INNER JOIN withdrawalVerificationTemplates t ON t.id = s.templateId
INNER JOIN paymentGatewaySettings pgs ON pgs.id = s.gatewaySettingId
LEFT JOIN paymentMethods pm ON pm.id = s.paymentMethodId;

-- ============================================================
-- vw_ibApplicationsDetails
-- 来源: database/all_crm_update_251129.sql
-- ============================================================
DROP VIEW IF EXISTS `vw_ibApplicationsDetails`;
CREATE VIEW `vw_ibApplicationsDetails` AS
SELECT
    app.id,
    -- applicantName 和 applicantEmail 优先从 clientUsers 获取，如果 clientId 为 NULL 则使用 ibApplications 中的值
    COALESCE(CONCAT(cu.firstName, ' ', cu.lastName), app.applicantName) AS applicantName,
    COALESCE(cu.email, app.applicantEmail) AS applicantEmail,
    app.ibType,
    app.expectedClients,
    app.yearsOfExperience,
    app.applicationStatus,
    app.applicationDate,
    app.reviewerId,
    app.auditorId,
    app.reviewStartDate,
    app.rulesLastSavedAt,
    app.isEdit,
    -- 操作员（Operator）信息 - reviewerId 对应操作员
    COALESCE(operator.fullName, operator.username, '') AS reviewerName,
    operator.email AS reviewerEmail,
    -- 审批员（Reviewer/Auditor）信息 - auditorId 对应审批员
    COALESCE(auditor.fullName, auditor.username, '') AS auditorName,
    auditor.email AS auditorEmail,
    tl.tierLevel,
    tl.tierName as assignedTierName,
    pf.primaryProducts,
    pf.targetRegions,
    pf.marketingChannels,
    -- Client User 信息（通过 clientId 关联）
    cu.id AS clientId,
    cu.firstName AS clientFirstName,
    cu.lastName AS clientLastName,
    cu.phone AS clientPhone,
    cu.country AS clientCountry,
    cu.status AS clientStatus,
    -- 已分配的规则数量（从 ibApplicationCustomRules 读取，只统计有 ruleId 的）
    COUNT(DISTINCT acr.ruleId) as preAssignedRulesCount,
    -- 已分配的规则列表（JSON格式，从 ibApplicationCustomRules 读取，只包含有 ruleId 的）
    (SELECT JSON_ARRAYAGG(
                    JSON_OBJECT(
                            'id', acr.ruleId,
                            'ruleName', acr.ruleName,
                            'ruleType', acr.ruleType,
                            'customRuleId', acr.id
                        )
                ) FROM ibApplicationCustomRules acr
     WHERE acr.applicationId = app.id
       AND acr.ruleId IS NOT NULL
       AND acr.status = 'active') AS preAssignedRules
FROM ibApplications app
         LEFT JOIN ibTierLevels tl ON app.assignedTierLevelId = tl.id
         LEFT JOIN ibApplicationProductFocus pf ON app.id = pf.applicationId
         LEFT JOIN ibApplicationCustomRules acr ON app.id = acr.applicationId
    AND acr.ruleId IS NOT NULL
    AND acr.status = 'active'
-- JOIN 操作员（Operator）表 - reviewerId 对应操作员
         LEFT JOIN adminUsers operator ON app.reviewerId = operator.id
-- JOIN 审批员（Auditor）表 - auditorId 对应审批员
         LEFT JOIN adminUsers auditor ON app.auditorId = auditor.id
-- JOIN Client Users 表 - 通过 clientId 关联
         LEFT JOIN clientUsers cu ON app.clientId = cu.id
GROUP BY app.id;

-- ============================================================
-- vw_ibCommissionRulesSummary
-- 来源: database/all_crm_update_260324.sql
-- ============================================================
DROP VIEW IF EXISTS `vw_ibCommissionRulesSummary`;
CREATE VIEW `vw_ibCommissionRulesSummary` AS
SELECT
    cr.id,
    cr.ruleName,
    cr.ruleDescription,
    cr.ruleType,
    cr.targetRegion,
    cr.paymentCycle,
    cr.paymentDay,
    cr.minimumPayout,
    cr.payoutCurrency,
    cr.status,
    cr.createdAt,
    cr.tierId,
    cr.product_type,
    cr.product,
    cr.trading_platforms_key,
    cr.minimum_trade,
    cr.maximum_trade,
    cr.rate,
    cr.fixed_amount,
    tl.tierLevel AS tierLevel,
    tl.tierName AS tierName,
    CASE
        WHEN cr.product_type = 'symbols' THEN sym.symbolName
        WHEN cr.product_type = 'securities' THEN sec.securityName
        ELSE NULL
        END AS productName,
    (
        SELECT COUNT(*)
        FROM ibRuleProducts rp
        WHERE rp.ruleId = cr.id
    ) AS productCount,
    (
        SELECT COALESCE(
                       CONCAT(
                               '[',
                               GROUP_CONCAT(
                                       JSON_OBJECT(
                                               'id', rp.id,
                                               'ruleId', rp.ruleId,
                                               'productType', rp.productType,
                                               'productName', rp.productName,
                                               'commissionType', rp.commissionType,
                                               'commissionRate', rp.commissionRate,
                                               'additionalRate', rp.additionalRate,
                                               'minimumVolume', rp.minimumVolume,
                                               'createdAt', rp.createdAt,
                                               'updatedAt', rp.updatedAt
                                       )
                                           ORDER BY rp.id ASC SEPARATOR ','
                               ),
                               ']'
                       ),
                       JSON_ARRAY()
               )
        FROM ibRuleProducts rp
        WHERE rp.ruleId = cr.id
    ) AS products,
    (
        SELECT COUNT(*)
        FROM ibRuleAdditionalRules ar
        WHERE ar.ruleId = cr.id
    ) AS additionalRulesCount,
    (
        SELECT COUNT(DISTINCT pa.ibPartnerId)
        FROM ibPartnerRuleAssignments pa
        WHERE pa.ruleId = cr.id
    ) AS assignedIbCount
FROM ibCommissionRules cr
         LEFT JOIN ibTierLevels tl ON cr.tierId = tl.id
         LEFT JOIN ibCustomSymbols sym ON cr.product_type = 'symbols' AND cr.product = sym.id
         LEFT JOIN ibCustomSecurities sec ON cr.product_type = 'securities' AND cr.product = sec.id;

-- ============================================================
-- vw_kyc_active_templates
-- 来源: database/修复数据表被小写的问题/update_views_camelcase.sql
-- ============================================================
DROP VIEW IF EXISTS `vw_kyc_active_templates`;
CREATE VIEW `vw_kyc_active_templates` AS
SELECT
    t.id AS templateId,
    t.templateName AS templateName,
    t.description AS description,
    t.totalQuestions AS totalQuestions,
    t.totalRules AS totalRules,
    GROUP_CONCAT(tc.countryName ORDER BY tc.countryName ASC SEPARATOR ', ') AS countries,
    GROUP_CONCAT(tc.countryCode ORDER BY tc.countryCode ASC SEPARATOR ',') AS countryCodes
FROM kycTemplates t
LEFT JOIN kycTemplateCountries tc ON t.id = tc.templateId
WHERE t.status = 'active'
GROUP BY t.id
ORDER BY t.displayOrder;

-- ============================================================
-- vw_kyc_questions_full
-- 来源: database/修复数据表被小写的问题/update_views_camelcase.sql
-- ============================================================
DROP VIEW IF EXISTS `vw_kyc_questions_full`;
CREATE VIEW `vw_kyc_questions_full` AS
SELECT
    q.id AS questionId,
    q.templateId AS templateId,
    q.categoryId AS categoryId,
    q.questionNumber AS questionNumber,
    q.questionText AS questionText,
    q.helpText AS helpText,
    q.questionType AS questionType,
    q.validationRules AS validationRules,
    q.isRequired AS isRequired,
    q.isActive AS isActive,
    q.displayOrder AS displayOrder,
    q.createdAt AS createdAt,
    q.updatedAt AS updatedAt,
    c.categoryName AS categoryName,
    (SELECT COUNT(*) FROM kycQuestionOptions WHERE questionId = q.id AND isActive = 1) AS optionCount,
    (SELECT COUNT(*) FROM kycQuestionDocumentTypes WHERE questionId = q.id) AS documentTypeCount
FROM kycQuestions q
LEFT JOIN kycQuestionCategories c ON q.categoryId = c.id
ORDER BY q.templateId, q.displayOrder, q.questionNumber;

-- ============================================================
-- vw_kyc_section_approval_progress
-- 来源: database/修复数据表被小写的问题/update_views_camelcase.sql
-- ============================================================
DROP VIEW IF EXISTS `vw_kyc_section_approval_progress`;
CREATE VIEW `vw_kyc_section_approval_progress` AS
SELECT
    s.id AS submissionId,
    s.clientId AS clientId,
    s.templateId AS templateId,
    s.submissionStatus AS submissionStatus,
    cu.email AS clientEmail,
    cu.firstName AS firstName,
    cu.lastName AS lastName,
    t.templateName AS templateName,
    (SELECT COUNT(*) FROM kycQuestionCategories WHERE templateId = s.templateId AND isActive = 1) AS totalCategories,
    (SELECT COUNT(DISTINCT categoryId) FROM kycSectionApprovals WHERE submissionId = s.id AND approvalStatus = 'approved') AS approvedCategories,
    ROUND(((SELECT COUNT(DISTINCT categoryId) FROM kycSectionApprovals WHERE submissionId = s.id AND approvalStatus = 'approved') /
           (SELECT COUNT(*) FROM kycQuestionCategories WHERE templateId = s.templateId AND isActive = 1)) * 100, 2) AS approvalProgress,
    CASE
        WHEN (SELECT COUNT(DISTINCT categoryId) FROM kycSectionApprovals WHERE submissionId = s.id AND approvalStatus = 'approved') =
             (SELECT COUNT(*) FROM kycQuestionCategories WHERE templateId = s.templateId AND isActive = 1)
        THEN 1
        ELSE 0
    END AS allSectionsApproved
FROM clientKycSubmissions s
INNER JOIN clientUsers cu ON s.clientId = cu.id
INNER JOIN kycTemplates t ON s.templateId = t.id
WHERE s.submissionStatus = 'under_review'
ORDER BY s.submittedAt DESC;

-- ============================================================
-- vw_kyc_template_summary
-- 来源: database/all_crm_update_260511.sql
-- ============================================================
DROP VIEW IF EXISTS `vw_kyc_template_summary`;
CREATE VIEW `vw_kyc_template_summary` AS
SELECT
  `t`.`id` AS `templateId`,
  `t`.`templateName` AS `templateName`,
  `t`.`description` AS `description`,
  `t`.`status` AS `status`,
  `t`.`isThirdPartyEnabled` AS `isThirdPartyEnabled`,
  `t`.`thirdPartyProvider` AS `thirdPartyProvider`,
  `t`.`externalTemplateId` AS `externalTemplateId`,
  `t`.`isAutoApproveEnabled` AS `isAutoApproveEnabled`,
  `t`.`requireDocumentSignature` AS `requireDocumentSignature`,
  `t`.`totalQuestions` AS `totalQuestions`,
  `t`.`totalRules` AS `totalRules`,
  `t`.`displayOrder` AS `displayOrder`,
  `t`.`createdAt` AS `createdAt`,
  `t`.`updatedAt` AS `updatedAt`,
  (SELECT COUNT(0) FROM `kycTemplateCountries` WHERE `kycTemplateCountries`.`templateId` = `t`.`id`) AS `countryCount`,
  (SELECT COUNT(0) FROM `kycQuestionCategories` WHERE `kycQuestionCategories`.`templateId` = `t`.`id` AND `kycQuestionCategories`.`isActive` = 1) AS `activeCategoryCount`,
  (SELECT COUNT(0) FROM `clientKycSubmissions` WHERE `clientKycSubmissions`.`templateId` = `t`.`id`) AS `totalSubmissions`,
  (SELECT COUNT(0) FROM `clientKycSubmissions` WHERE `clientKycSubmissions`.`templateId` = `t`.`id` AND `clientKycSubmissions`.`submissionStatus` = 'approved') AS `approvedSubmissions`
FROM `kycTemplates` `t`
ORDER BY `t`.`displayOrder`, `t`.`id`;

-- ============================================================
-- vw_lead_assignment_timeline
-- 来源: database/修复数据表被小写的问题/update_views_camelcase.sql
-- ============================================================
DROP VIEW IF EXISTS `vw_lead_assignment_timeline`;
CREATE VIEW `vw_lead_assignment_timeline` AS
SELECT
    lah.id AS id,
    lah.leadId AS leadId,
    cu.firstName AS leadFirstName,
    cu.lastName AS leadLastName,
    cu.email AS leadEmail,
    lah.actionType AS actionType,
    lah.previousSalesRepId AS previousSalesRepId,
    CONCAT(sr1.firstName, ' ', sr1.lastName) AS previousSalesRep,
    lah.newSalesRepId AS newSalesRepId,
    CONCAT(sr2.firstName, ' ', sr2.lastName) AS newSalesRep,
    lah.reason AS reason,
    lah.createdAt AS actionDate
FROM leadAssignmentHistory lah
INNER JOIN clientUsers cu ON lah.leadId = cu.id
LEFT JOIN salesRepresentatives sr1 ON lah.previousSalesRepId = sr1.id
LEFT JOIN salesRepresentatives sr2 ON lah.newSalesRepId = sr2.id
ORDER BY lah.createdAt DESC;

-- ============================================================
-- vw_lead_summary
-- 来源: database/修复数据表被小写的问题/update_views_camelcase.sql
-- ============================================================
DROP VIEW IF EXISTS `vw_lead_summary`;
CREATE VIEW `vw_lead_summary` AS
SELECT
    cu.id AS leadId,
    cu.email AS email,
    cu.firstName AS firstName,
    cu.lastName AS lastName,
    cu.phone AS phone,
    cu.country AS country,
    cu.status AS status,
    cu.emailVerified AS emailVerified,
    cu.registrationIp AS registrationIp,
    cu.createdAt AS registrationDate,
    cu.lastLoginAt AS lastLoginAt,
    (SELECT COUNT(*) FROM leadTagAssignments WHERE leadId = cu.id) AS tagCount,
    (SELECT COUNT(*) FROM legalDocumentSignatures WHERE leadId = cu.id) AS signedDocumentsCount,
    (SELECT kycStatus FROM leadKycStatus WHERE leadId = cu.id) AS kycStatus
FROM clientUsers cu
WHERE cu.kycStatus IS NULL OR cu.kycStatus <> 'approved'
ORDER BY cu.createdAt DESC;

-- ============================================================
-- vw_lead_summary_with_assignment
-- 来源: database/修复数据表被小写的问题/update_views_camelcase.sql
-- ============================================================
DROP VIEW IF EXISTS `vw_lead_summary_with_assignment`;
CREATE VIEW `vw_lead_summary_with_assignment` AS
SELECT
    cu.id AS leadId,
    cu.email AS email,
    cu.firstName AS firstName,
    cu.lastName AS lastName,
    cu.phone AS phone,
    cu.country AS country,
    cu.status AS status,
    cu.emailVerified AS emailVerified,
    cu.registrationIp AS registrationIp,
    cu.createdAt AS registrationDate,
    cu.lastLoginAt AS lastLoginAt,
    (SELECT COUNT(*) FROM leadTagAssignments WHERE leadId = cu.id) AS tagCount,
    (SELECT COUNT(*) FROM legalDocumentSignatures WHERE leadId = cu.id) AS signedDocumentsCount,
    (SELECT kycStatus FROM leadKycStatus WHERE leadId = cu.id) AS kycStatus,
    la.id AS assignmentId,
    la.salesRepId AS salesRepId,
    CONCAT(sr.firstName, ' ', sr.lastName) AS assignedToSalesRep,
    la.assignmentStatus AS assignmentStatus,
    la.assignedAt AS assignedAt,
    la.lastContactedAt AS lastContactedAt,
    (SELECT COUNT(*) FROM leadAssignmentNotes WHERE assignmentId = la.id) AS notesCount
FROM clientUsers cu
LEFT JOIN leadAssignments la ON cu.id = la.leadId AND la.isActive = 1
LEFT JOIN salesRepresentatives sr ON la.salesRepId = sr.id
ORDER BY cu.createdAt DESC;

-- ============================================================
-- vw_salesrep_workload
-- 来源: database/修复数据表被小写的问题/update_views_camelcase.sql
-- ============================================================
DROP VIEW IF EXISTS `vw_salesrep_workload`;
CREATE VIEW `vw_salesrep_workload` AS
SELECT
    sr.id AS salesRepId,
    sr.repCode AS repCode,
    CONCAT(sr.firstName, ' ', sr.lastName) AS fullName,
    sr.email AS email,
    sr.department AS department,
    sr.isActive AS isActive,
    sr.maxLeadCapacity AS maxLeadCapacity,
    COUNT(la.id) AS activeLeadsCount,
    (sr.maxLeadCapacity - COUNT(la.id)) AS availableCapacity,
    (SELECT COUNT(*) FROM leadAssignmentNotes lan
     INNER JOIN leadAssignments la2 ON lan.assignmentId = la2.id
     WHERE la2.salesRepId = sr.id AND la2.isActive = 1
     AND CAST(lan.createdAt AS DATE) = CURDATE()) AS todayNotesCount,
    (SELECT COUNT(*) FROM assignmentReminders ar
     INNER JOIN leadAssignments la3 ON ar.assignmentId = la3.id
     WHERE la3.salesRepId = sr.id AND ar.isCompleted = 0
     AND CAST(ar.reminderDate AS DATE) <= CURDATE()) AS overdueReminders
FROM salesRepresentatives sr
LEFT JOIN leadAssignments la ON sr.id = la.salesRepId AND la.isActive = 1
WHERE sr.isActive = 1
GROUP BY sr.id, sr.repCode, sr.firstName, sr.lastName, sr.email, sr.department, sr.isActive, sr.maxLeadCapacity
ORDER BY activeLeadsCount DESC;

-- ============================================================
-- vw_withdrawal_verification_active_templates
-- 来源: database/all_crm_update_260310.sql
-- ============================================================
DROP VIEW IF EXISTS `vw_withdrawal_verification_active_templates`;
CREATE VIEW `vw_withdrawal_verification_active_templates` AS
SELECT
  t.id AS templateId,
  t.gatewaySettingId,
  pgs.gatewayKey,
  pgs.gatewayName,
  pgs.iconClass,
  pgs.isDepositEnabled,
  pgs.isWithdrawalEnabled,
  t.templateName,
  t.description,
  t.requireDocumentSignature,
  t.isAutoApproveEnabled,
  t.totalQuestions,
  t.totalRules,
  t.displayOrder
FROM withdrawalVerificationTemplates t
INNER JOIN paymentGatewaySettings pgs ON pgs.id = t.gatewaySettingId
WHERE t.status = 'active'
ORDER BY pgs.gatewayName, t.displayOrder, t.id;

-- ============================================================
-- vw_withdrawal_verification_questions_full
-- 来源: database/all_crm_update_260408.sql
-- ============================================================
DROP VIEW IF EXISTS `vw_withdrawal_verification_questions_full`;
CREATE VIEW `vw_withdrawal_verification_questions_full` AS
SELECT
  q.id,
  q.templateId,
  q.categoryId,
  c.categoryName,
  q.questionNumber,
  q.questionText,
  q.helpText,
  q.questionType,
  q.scope,
  q.validationRules,
  q.isRequired,
  q.isActive,
  q.isLocked,
  q.displayOrder,
  q.metadata,
  (
    SELECT JSON_ARRAYAGG(
      JSON_OBJECT(
        'id', o.id,
        'label', COALESCE(o.optionLabel, o.optionValue),
        'value', o.optionValue,
        'optionLabel', COALESCE(o.optionLabel, o.optionValue),
        'optionValue', o.optionValue,
        'displayOrder', o.displayOrder
      )
    )
    FROM withdrawalVerificationQuestionOptions o
    WHERE o.questionId = q.id AND o.isActive = 1
  ) AS options,
  (
    SELECT JSON_ARRAYAGG(d.documentType)
    FROM withdrawalVerificationQuestionDocumentTypes d
    WHERE d.questionId = q.id
  ) AS fileDocumentTypes
FROM withdrawalVerificationQuestions q
INNER JOIN withdrawalVerificationQuestionCategories c ON c.id = q.categoryId;

-- ============================================================
-- vw_withdrawal_verification_template_summary
-- 来源: database/all_crm_update_260310.sql
-- ============================================================
DROP VIEW IF EXISTS `vw_withdrawal_verification_template_summary`;
CREATE VIEW `vw_withdrawal_verification_template_summary` AS
SELECT
  t.id AS templateId,
  t.gatewaySettingId,
  pgs.gatewayKey,
  pgs.gatewayName,
  pgs.iconClass,
  pgs.isDepositEnabled,
  pgs.isWithdrawalEnabled,
  t.templateName,
  t.description,
  t.status,
  t.isAutoApproveEnabled,
  t.requireDocumentSignature,
  t.totalQuestions,
  t.totalRules,
  t.displayOrder,
  t.createdAt,
  t.updatedAt,
  (SELECT COUNT(*) FROM withdrawalVerificationQuestionCategories c WHERE c.templateId = t.id AND c.isActive = 1) AS activeCategoryCount,
  (SELECT COUNT(*) FROM clientWithdrawalVerificationSubmissions s WHERE s.templateId = t.id) AS totalSubmissions,
  (SELECT COUNT(*) FROM clientWithdrawalVerificationSubmissions s WHERE s.templateId = t.id AND s.submissionStatus = 'approved') AS approvedSubmissions
FROM withdrawalVerificationTemplates t
INNER JOIN paymentGatewaySettings pgs ON pgs.id = t.gatewaySettingId
ORDER BY t.displayOrder, t.id;
