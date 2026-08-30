
DROP VIEW IF EXISTS `vadminuserpermissions`;
DROP VIEW IF EXISTS `vadminusersfull`;
DROP VIEW IF EXISTS `valltransactions`;
DROP VIEW IF EXISTS `vdepositssummary`;
DROP VIEW IF EXISTS `vwithdrawalssummary`;
DROP VIEW IF EXISTS `vw_ibapplicationsdetails`;
DROP VIEW IF EXISTS `vw_ibcommissionrulessummary`;
DROP VIEW IF EXISTS `vw_ibpartnerssummary`;

-- ============================================================
-- 1. vAdminUserPermissions 视图
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
-- 2. vAdminUsersFull 视图
-- ============================================================
DROP VIEW IF EXISTS `vAdminUsersFull`;
CREATE VIEW `vAdminUsersFull` AS
SELECT
    u.id AS id,
    u.username AS username,
    u.email AS email,
    u.fullName AS fullName,
    u.avatarInitials AS avatarInitials,
    u.avatarColor AS avatarColor,
    u.status AS status,
    u.isLocked AS isLocked,
    u.lastLoginAt AS lastLoginAt,
    u.lastLoginIp AS lastLoginIp,
    u.createdAt AS createdAt,
    r.roleKey AS roleKey,
    r.roleName AS roleName,
    r.roleDisplayName AS roleDisplayName,
    r.badgeColor AS badgeColor,
    r.level AS roleLevel,
    p.phone AS phone,
    p.department AS department,
    p.timezone AS timezone,
    p.language AS language
FROM adminUsers u
LEFT JOIN adminRoles r ON u.roleId = r.id
LEFT JOIN adminUserProfiles p ON u.id = p.userId
WHERE u.deletedAt IS NULL;

-- ============================================================
-- 3. vAllTransactions 视图
-- 执行日期: 2026-01-27
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
    d.netAmount AS netAmount,
    d.status AS status,
    pm.methodName AS paymentMethod,
    pm.methodType AS paymentType,
    d.requestedAt AS requestedAt,
    d.approvedAt AS approvedAt,
    d.completedAt AS completedAt,
    d.approvedBy AS approvedBy,
    NULL AS rejectedAt,
    NULL AS rejectedBy,
    d.createdAt AS createdAt,
    d.updatedAt AS updatedAt
FROM deposits d
INNER JOIN clientUsers u ON d.userId = u.id
INNER JOIN paymentMethods pm ON d.paymentMethodId = pm.id
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
    w.netAmount AS netAmount,
    w.status AS status,
    pm.methodName AS paymentMethod,
    pm.methodType AS paymentType,
    w.requestedAt AS requestedAt,
    w.approvedAt AS approvedAt,
    w.completedAt AS completedAt,
    w.approvedBy AS approvedBy,
    w.rejectedAt AS rejectedAt,
    w.rejectedBy AS rejectedBy,
    w.createdAt AS createdAt,
    w.updatedAt AS updatedAt
FROM withdrawals w
INNER JOIN clientUsers u ON w.userId = u.id
INNER JOIN paymentMethods pm ON w.paymentMethodId = pm.id
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
    it.amount AS netAmount,
    it.status AS status,
    CONCAT(
        CASE WHEN it.fromType = 'wallet' OR it.fromType = 'available_balance' THEN 'Wallet'
             ELSE COALESCE(fromta.accountNickname, 'Trading Account') END,
        ' → ',
        COALESCE(tota.accountNickname, 'Trading Account')
    ) AS paymentMethod,
    'internal' AS paymentType,
    it.requestedAt AS requestedAt,
    it.approvedAt AS approvedAt,
    it.completedAt AS completedAt,
    it.approvedBy AS approvedBy,
    NULL AS rejectedAt,
    NULL AS rejectedBy,
    it.createdAt AS createdAt,
    it.updatedAt AS updatedAt
FROM internalTransfers it
INNER JOIN clientUsers u ON it.userId = u.id
LEFT JOIN tradingAccounts fromta ON it.fromTradingAccountId = fromta.id
LEFT JOIN tradingAccounts tota ON it.toTradingAccountId = tota.id;

-- ============================================================
-- 4. vDepositsSummary 视图
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
    d.netAmount AS netAmount,
    d.status AS status,
    pm.methodName AS paymentMethod,
    pm.methodType AS paymentType,
    d.requestedAt AS requestedAt,
    d.approvedAt AS approvedAt,
    d.completedAt AS completedAt,
    GROUP_CONCAT(dt.tagName SEPARATOR ', ') AS tags,
    d.createdAt AS createdAt
FROM deposits d
INNER JOIN clientUsers u ON d.userId = u.id
INNER JOIN paymentMethods pm ON d.paymentMethodId = pm.id
LEFT JOIN depositTagAssignments dta ON d.id = dta.depositId
LEFT JOIN depositTags dt ON dta.tagId = dt.id
GROUP BY d.id;

-- ============================================================
-- 5. vw_active_assignments 视图
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
-- 6. vw_client_kyc_progress 视图
-- ============================================================
DROP VIEW IF EXISTS `vw_client_kyc_progress`;
CREATE VIEW `vw_client_kyc_progress` AS
SELECT
    s.id AS submissionId,
    s.clientId AS clientId,
    cu.email AS clientEmail,
    cu.firstName AS firstName,
    cu.lastName AS lastName,
    s.templateId AS templateId,
    t.templateName AS templateName,
    s.submissionStatus AS submissionStatus,
    s.submittedAt AS submittedAt,
    s.reviewedAt AS reviewedAt,
    s.reviewedBy AS reviewerId,
    COALESCE(au.fullName, au.username, '') AS reviewerName,
    au.username AS reviewerUsername,
    (SELECT COUNT(*) FROM clientKycAnswers WHERE submissionId = s.id) AS answeredQuestions,
    t.totalQuestions AS totalQuestions,
    ROUND(((SELECT COUNT(*) FROM clientKycAnswers WHERE submissionId = s.id) / t.totalQuestions) * 100, 2) AS progressPercentage,
    (SELECT COUNT(*) FROM clientKycDocumentSignatures WHERE submissionId = s.id) AS signedDocuments,
    (SELECT COUNT(*) FROM kycTemplateDocuments WHERE templateId = s.templateId AND isActive = 1) AS requiredDocuments
FROM clientKycSubmissions s
INNER JOIN clientUsers cu ON s.clientId = cu.id
INNER JOIN kycTemplates t ON s.templateId = t.id
LEFT JOIN adminUsers au ON s.reviewedBy = au.id
ORDER BY s.submittedAt DESC;

-- ============================================================
-- 7. vw_client_kyc_status 视图
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
-- 8. vw_client_management 视图
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
-- 9. vw_ibApplicationsDetails 视图
-- ============================================================
DROP VIEW IF EXISTS `vw_ibApplicationsDetails`;
CREATE VIEW `vw_ibApplicationsDetails` AS
SELECT
    app.id AS id,
    app.applicantName AS applicantName,
    app.applicantEmail AS applicantEmail,
    app.ibType AS ibType,
    app.expectedClients AS expectedClients,
    app.yearsOfExperience AS yearsOfExperience,
    app.applicationStatus AS applicationStatus,
    app.applicationDate AS applicationDate,
    app.reviewerId AS reviewerId,
    app.auditorId AS auditorId,
    -- 操作员（Operator）信息 - reviewerId 对应操作员
    COALESCE(operator.fullName, operator.username, '') AS reviewerName,
    operator.email AS reviewerEmail,
    -- 审批员（Reviewer/Auditor）信息 - auditorId 对应审批员
    COALESCE(auditor.fullName, auditor.username, '') AS auditorName,
    auditor.email AS auditorEmail,
    tl.tierLevel AS tierLevel,
    tl.tierName AS assignedTierName,
    pf.primaryProducts AS primaryProducts,
    pf.targetRegions AS targetRegions,
    pf.marketingChannels AS marketingChannels,
    COUNT(DISTINCT ra.ruleId) AS preAssignedRulesCount
FROM ibApplications app
LEFT JOIN ibTierLevels tl ON app.assignedTierLevelId = tl.id
LEFT JOIN ibApplicationProductFocus pf ON app.id = pf.applicationId
LEFT JOIN ibApplicationRuleAssignments ra ON app.id = ra.applicationId
-- JOIN 操作员（Operator）表 - reviewerId 对应操作员
LEFT JOIN adminUsers operator ON app.reviewerId = operator.id
-- JOIN 审批员（Auditor）表 - auditorId 对应审批员
LEFT JOIN adminUsers auditor ON app.auditorId = auditor.id
GROUP BY app.id;

-- ============================================================
-- 10. vw_ibCommissionRulesSummary 视图
-- ============================================================
DROP VIEW IF EXISTS `vw_ibCommissionRulesSummary`;
CREATE VIEW `vw_ibCommissionRulesSummary` AS
SELECT
    cr.id,
    cr.ruleName,
    cr.ruleType,
    cr.ruleDescription,
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
    cr.minimum_trade,
    cr.maximum_trade,
    cr.rate,
    cr.fixed_amount,
    MAX(tl.tierLevel) AS tierLevel,
    MAX(tl.tierName) AS tierName,
    MAX(CASE WHEN cr.product_type = 'symbols' THEN sym.symbolName WHEN cr.product_type = 'securities' THEN sec.securityName END) AS productName,
    COUNT(DISTINCT rp.id) AS productCount,
    COUNT(DISTINCT ar.id) AS additionalRulesCount,
    COUNT(DISTINCT pa.ibPartnerId) AS assignedIbCount
FROM ibCommissionRules cr
         LEFT JOIN ibTierLevels tl ON cr.tierId = tl.id
         LEFT JOIN ibCustomSymbols sym ON cr.product_type = 'symbols' AND cr.product = sym.id
         LEFT JOIN ibCustomSecurities sec ON cr.product_type = 'securities' AND cr.product = sec.id
         LEFT JOIN ibRuleProducts rp ON cr.id = rp.ruleId
         LEFT JOIN ibRuleAdditionalRules ar ON cr.id = ar.ruleId
         LEFT JOIN ibPartnerRuleAssignments pa ON cr.id = pa.ruleId
GROUP BY cr.id, cr.ruleName, cr.ruleType, cr.ruleDescription, cr.targetRegion, cr.paymentCycle, cr.paymentDay,
         cr.minimumPayout, cr.payoutCurrency, cr.status, cr.createdAt, cr.tierId, cr.product_type, cr.product,
         cr.minimum_trade, cr.maximum_trade, cr.rate, cr.fixed_amount;

-- ============================================================
-- 11. vw_ibPartnersSummary 视图
-- ============================================================
DROP VIEW IF EXISTS `vw_ibPartnersSummary`;
CREATE VIEW `vw_ibPartnersSummary` AS
SELECT
    ib.id AS id,
    ib.ibCode AS ibCode,
    ib.companyName AS companyName,
    ib.ibType AS ibType,
    ib.contactEmail AS contactEmail,
    ib.country AS country,
    ib.status AS status,
    ib.registrationDate AS registrationDate,
    ib.totalClients AS totalClients,
    ib.activeClients AS activeClients,
    ib.totalCommissionEarned AS totalCommissionEarned,
    ib.totalTradingVolume AS totalTradingVolume,
    tl.tierLevel AS tierLevel,
    tl.tierName AS tierName,
    COUNT(DISTINCT ra.ruleId) AS assignedRulesCount,
    GROUP_CONCAT(DISTINCT cr.ruleName ORDER BY cr.ruleName ASC SEPARATOR ', ') AS assignedRuleNames
FROM ibPartners ib
LEFT JOIN ibTierLevels tl ON ib.tierLevelId = tl.id
LEFT JOIN ibPartnerRuleAssignments ra ON ib.id = ra.ibPartnerId
LEFT JOIN ibCommissionRules cr ON ra.ruleId = cr.id
WHERE ib.status IN ('active', 'pending')
GROUP BY ib.id;

-- ============================================================
-- 12. vw_kyc_active_templates 视图
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
-- 13. vw_kyc_questions_full 视图
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
-- 14. vw_kyc_section_approval_progress 视图
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
-- 15. vw_kyc_template_summary 视图
-- ============================================================
DROP VIEW IF EXISTS `vw_kyc_template_summary`;
CREATE VIEW `vw_kyc_template_summary` AS
SELECT
    t.id AS templateId,
    t.templateName AS templateName,
    t.description AS description,
    t.status AS status,
    t.isThirdPartyEnabled AS isThirdPartyEnabled,
    t.isAutoApproveEnabled AS isAutoApproveEnabled,
    t.requireDocumentSignature AS requireDocumentSignature,
    t.totalQuestions AS totalQuestions,
    t.totalRules AS totalRules,
    t.displayOrder AS displayOrder,
    t.createdAt AS createdAt,
    t.updatedAt AS updatedAt,
    (SELECT COUNT(*) FROM kycTemplateCountries WHERE templateId = t.id) AS countryCount,
    (SELECT COUNT(*) FROM kycQuestionCategories WHERE templateId = t.id AND isActive = 1) AS activeCategoryCount,
    (SELECT COUNT(*) FROM clientKycSubmissions WHERE templateId = t.id) AS totalSubmissions,
    (SELECT COUNT(*) FROM clientKycSubmissions WHERE templateId = t.id AND submissionStatus = 'approved') AS approvedSubmissions
FROM kycTemplates t
ORDER BY t.displayOrder, t.id;

-- ============================================================
-- 16. vw_lead_assignment_timeline 视图
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
-- 17. vw_lead_summary 视图
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
-- 18. vw_lead_summary_with_assignment 视图
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
-- 19. vw_salesrep_workload 视图
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
-- 20. vWithdrawalsSummary 视图
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
    w.netAmount AS netAmount,
    w.status AS status,
    pm.methodName AS paymentMethod,
    pm.methodType AS paymentType,
    w.requestedAt AS requestedAt,
    w.approvedAt AS approvedAt,
    w.completedAt AS completedAt,
    w.rejectedAt AS rejectedAt,
    wrr.reasonTitle AS rejectionReason,
    GROUP_CONCAT(wt.tagName SEPARATOR ', ') AS tags,
    w.createdAt AS createdAt
FROM withdrawals w
INNER JOIN clientUsers u ON w.userId = u.id
INNER JOIN paymentMethods pm ON w.paymentMethodId = pm.id
LEFT JOIN withdrawalRejectionReasons wrr ON w.rejectionReasonId = wrr.id
LEFT JOIN withdrawalTagAssignments wta ON w.id = wta.withdrawalId
LEFT JOIN withdrawalTags wt ON wta.tagId = wt.id
GROUP BY w.id;

-- ============================================================
-- 视图更新完成
-- ============================================================
-- 注意：
-- 1. 所有视图名称已修正为正确的驼峰命名或下划线命名
-- 2. 所有视图中的表名已从小写改为驼峰命名
-- 3. 执行此脚本前，请确保已执行表名重命名脚本
-- ============================================================
