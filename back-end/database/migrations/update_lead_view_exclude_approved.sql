-- 数据库迁移脚本：更新Lead视图以排除KYC已批准的用户
-- 创建日期: 2024-01-20
-- 用途: 当KYC状态为approved时，用户从Leads列表移除，仅作为正式客户存在

-- 删除旧视图
DROP VIEW IF EXISTS `vw_lead_summary`;

-- 创建新视图（排除KYC状态为approved的用户）
CREATE VIEW `vw_lead_summary` AS
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
  (SELECT kycStatus FROM leadKycStatus WHERE leadId = cu.id) AS kycStatus
FROM clientUsers cu
WHERE (cu.kycStatus IS NULL OR cu.kycStatus != 'approved')
ORDER BY cu.createdAt DESC;

-- 说明：
-- 此迁移脚本执行以下操作：
-- 1. 更新 vw_lead_summary 视图，添加WHERE条件排除kycStatus='approved'的用户
-- 2. 这样在Leads管理页面中，KYC已批准的用户将不再显示
-- 3. 这些用户仍然存在于clientUsers表中，只是不在Leads视图中
--
-- 如何使用：
-- 在MySQL/phpMyAdmin中直接执行此脚本
--
-- 回滚方法：
-- 如果需要恢复旧视图（包含所有用户），可以删除WHERE条件
