-- ============================================================
-- 触发器更新脚本：修正触发器名称和表名为驼峰命名
-- 数据库：utrada_crm
-- 说明：将触发器名称和触发器中的表名从小写改为驼峰命名法
-- ============================================================

-- ============================================================
-- 先删除所有旧触发器
-- ============================================================

DROP TRIGGER IF EXISTS `trgAccountVerificationsAfterUpdate`;
DROP TRIGGER IF EXISTS `trgAdminUsersAfterInsert`;
DROP TRIGGER IF EXISTS `trgAdminUsersPasswordUpdate`;
DROP TRIGGER IF EXISTS `trgDepositsAfterUpdate`;
DROP TRIGGER IF EXISTS `trgIbApplicationsAfterUpdate`;
DROP TRIGGER IF EXISTS `trgIbClientRelationshipAfterInsert`;
DROP TRIGGER IF EXISTS `trgIbClientRelationshipAfterUpdate`;
DROP TRIGGER IF EXISTS `trgIbClientRelationshipAfterDelete`;
DROP TRIGGER IF EXISTS `trgIbPartnersBeforeInsert`;
DROP TRIGGER IF EXISTS `trgUpdateTotalRulesAfterInsert`;
DROP TRIGGER IF EXISTS `trgUpdateTotalRulesAfterUpdate`;
DROP TRIGGER IF EXISTS `trgUpdateTotalRulesAfterDelete`;
DROP TRIGGER IF EXISTS `trgUpdateTotalQuestionsAfterInsert`;
DROP TRIGGER IF EXISTS `trgUpdateTotalQuestionsAfterUpdate`;
DROP TRIGGER IF EXISTS `trgUpdateTotalQuestionsAfterDelete`;
DROP TRIGGER IF EXISTS `trgAfterLeadAssignmentInsert`;
DROP TRIGGER IF EXISTS `trgAfterLeadAssignmentUpdate`;
DROP TRIGGER IF EXISTS `trgAfterLeadAssignmentDelete`;
DROP TRIGGER IF EXISTS `trgWithdrawalsAfterUpdate`;

-- ============================================================
-- 创建所有触发器（使用 DELIMITER $$）
-- ============================================================

DELIMITER $$

-- ============================================================
-- 1. 账户验证相关触发器
-- ============================================================

-- 账户验证状态更新触发器
CREATE TRIGGER `trgAccountVerificationsAfterUpdate`
AFTER UPDATE ON `accountVerifications`
FOR EACH ROW
BEGIN
    IF NEW.verificationStatus != OLD.verificationStatus THEN
        INSERT INTO accountVerificationLogs (
            verificationId,
            actionType,
            actionBy,
            actionDetails
        ) VALUES (
            NEW.id,
            CASE NEW.verificationStatus
                WHEN 'approved' THEN 'approved'
                WHEN 'rejected' THEN 'rejected'
                ELSE 'updated'
            END,
            NEW.reviewedBy,
            JSON_OBJECT(
                'oldStatus', OLD.verificationStatus,
                'newStatus', NEW.verificationStatus,
                'reviewNotes', NEW.reviewNotes,
                'rejectionReason', NEW.rejectionReason
            )
        );
    END IF;
END$$

-- ============================================================
-- 2. 管理员相关触发器
-- ============================================================

-- 管理员用户创建后自动创建资料
CREATE TRIGGER `trgAdminUsersAfterInsert`
AFTER INSERT ON `adminUsers`
FOR EACH ROW
BEGIN
    INSERT IGNORE INTO adminUserProfiles (userId) VALUES (NEW.id);
END$$

-- 管理员密码更新触发器
CREATE TRIGGER `trgAdminUsersPasswordUpdate`
AFTER UPDATE ON `adminUsers`
FOR EACH ROW
BEGIN
    IF NEW.passwordHash != OLD.passwordHash THEN
        INSERT INTO adminPasswordHistory (userId, passwordHash)
        VALUES (NEW.id, OLD.passwordHash);
    END IF;
END$$

-- ============================================================
-- 3. 存款相关触发器
-- ============================================================

-- 存款状态更新触发器
CREATE TRIGGER `trgDepositsAfterUpdate`
AFTER UPDATE ON `deposits`
FOR EACH ROW
BEGIN
    IF NEW.status != OLD.status THEN
        INSERT INTO depositStatusHistory (depositId, previousStatus, newStatus, description)
        VALUES (NEW.id, OLD.status, NEW.status, CONCAT('Status changed from ', OLD.status, ' to ', NEW.status));
    END IF;

    IF NEW.confirmations >= NEW.requiredConfirmations
       AND OLD.confirmations < OLD.requiredConfirmations
       AND NEW.status = 'pending' THEN
        UPDATE deposits
        SET status = 'processing'
        WHERE id = NEW.id;
    END IF;
END$$

-- ============================================================
-- 4. IB 申请相关触发器
-- ============================================================

-- IB 申请状态更新触发器
CREATE TRIGGER `trgIbApplicationsAfterUpdate`
AFTER UPDATE ON `ibApplications`
FOR EACH ROW
BEGIN
    IF OLD.applicationStatus != NEW.applicationStatus THEN
        INSERT INTO ibApplicationStatusHistory (applicationId, previousStatus, newStatus, changedBy, notes)
        VALUES (NEW.id, OLD.applicationStatus, NEW.applicationStatus, NEW.updatedBy, CONCAT('Status changed from ', OLD.applicationStatus, ' to ', NEW.applicationStatus));
    END IF;
END$$

-- ============================================================
-- 5. IB 客户关系相关触发器
-- ============================================================

-- IB 客户关系插入触发器
CREATE TRIGGER `trgIbClientRelationshipAfterInsert`
AFTER INSERT ON `ibClientRelationships`
FOR EACH ROW
BEGIN
    UPDATE ibPartners
    SET totalClients = totalClients + 1,
        activeClients = activeClients + IF(NEW.isActive = 1, 1, 0)
    WHERE id = NEW.ibPartnerId;
END$$

-- IB 客户关系更新触发器
CREATE TRIGGER `trgIbClientRelationshipAfterUpdate`
AFTER UPDATE ON `ibClientRelationships`
FOR EACH ROW
BEGIN
    IF OLD.isActive != NEW.isActive THEN
        UPDATE ibPartners
        SET activeClients = activeClients + IF(NEW.isActive = 1, 1, -1)
        WHERE id = NEW.ibPartnerId;
    END IF;
END$$

-- IB 客户关系删除触发器
CREATE TRIGGER `trgIbClientRelationshipAfterDelete`
AFTER DELETE ON `ibClientRelationships`
FOR EACH ROW
BEGIN
    UPDATE ibPartners
    SET totalClients = totalClients - 1,
        activeClients = activeClients - IF(OLD.isActive = 1, 1, 0)
    WHERE id = OLD.ibPartnerId;
END$$

-- ============================================================
-- 6. IB 合作伙伴相关触发器
-- ============================================================

-- IB 合作伙伴插入前触发器（生成IB代码）
CREATE TRIGGER `trgIbPartnersBeforeInsert`
BEFORE INSERT ON `ibPartners`
FOR EACH ROW
BEGIN
    DECLARE v_ibCount INT;

    IF NEW.ibCode IS NULL OR NEW.ibCode = '' THEN
        -- Generate IB code in IB+数字格式 (IB101, IB102, etc.)
        SELECT COALESCE(MAX(CAST(SUBSTRING(ibCode, 3) AS UNSIGNED)), 100) INTO v_ibCount
        FROM ibPartners
        WHERE ibCode REGEXP '^IB[0-9]+$';

        -- 如果找不到IB开头的代码，从101开始
        IF v_ibCount < 100 THEN
            SET v_ibCount = 101;
        ELSE
            SET v_ibCount = v_ibCount + 1;
        END IF;

        SET NEW.ibCode = CONCAT('IB', v_ibCount);
    END IF;
END$$

-- ============================================================
-- 7. KYC 条件规则相关触发器
-- ============================================================

-- KYC 条件规则插入后更新模板规则数
CREATE TRIGGER `trgUpdateTotalRulesAfterInsert`
AFTER INSERT ON `kycConditionalRules`
FOR EACH ROW
BEGIN
    UPDATE kycTemplates
    SET totalRules = (SELECT COUNT(*) FROM kycConditionalRules WHERE templateId = NEW.templateId AND isActive = 1)
    WHERE id = NEW.templateId;
END$$

-- KYC 条件规则更新后更新模板规则数
CREATE TRIGGER `trgUpdateTotalRulesAfterUpdate`
AFTER UPDATE ON `kycConditionalRules`
FOR EACH ROW
BEGIN
    IF OLD.isActive != NEW.isActive OR OLD.templateId != NEW.templateId THEN
        UPDATE kycTemplates
        SET totalRules = (SELECT COUNT(*) FROM kycConditionalRules WHERE templateId = NEW.templateId AND isActive = 1)
        WHERE id = NEW.templateId;

        IF OLD.templateId != NEW.templateId THEN
            UPDATE kycTemplates
            SET totalRules = (SELECT COUNT(*) FROM kycConditionalRules WHERE templateId = OLD.templateId AND isActive = 1)
            WHERE id = OLD.templateId;
        END IF;
    END IF;
END$$

-- KYC 条件规则删除后更新模板规则数
CREATE TRIGGER `trgUpdateTotalRulesAfterDelete`
AFTER DELETE ON `kycConditionalRules`
FOR EACH ROW
BEGIN
    UPDATE kycTemplates
    SET totalRules = (SELECT COUNT(*) FROM kycConditionalRules WHERE templateId = OLD.templateId AND isActive = 1)
    WHERE id = OLD.templateId;
END$$

-- ============================================================
-- 8. KYC 问题相关触发器
-- ============================================================

-- KYC 问题插入后更新模板问题数
CREATE TRIGGER `trgUpdateTotalQuestionsAfterInsert`
AFTER INSERT ON `kycQuestions`
FOR EACH ROW
BEGIN
    UPDATE kycTemplates
    SET totalQuestions = (SELECT COUNT(*) FROM kycQuestions WHERE templateId = NEW.templateId AND isActive = 1)
    WHERE id = NEW.templateId;
END$$

-- KYC 问题更新后更新模板问题数
CREATE TRIGGER `trgUpdateTotalQuestionsAfterUpdate`
AFTER UPDATE ON `kycQuestions`
FOR EACH ROW
BEGIN
    IF OLD.isActive != NEW.isActive OR OLD.templateId != NEW.templateId THEN
        UPDATE kycTemplates
        SET totalQuestions = (SELECT COUNT(*) FROM kycQuestions WHERE templateId = NEW.templateId AND isActive = 1)
        WHERE id = NEW.templateId;

        IF OLD.templateId != NEW.templateId THEN
            UPDATE kycTemplates
            SET totalQuestions = (SELECT COUNT(*) FROM kycQuestions WHERE templateId = OLD.templateId AND isActive = 1)
            WHERE id = OLD.templateId;
        END IF;
    END IF;
END$$

-- KYC 问题删除后更新模板问题数
CREATE TRIGGER `trgUpdateTotalQuestionsAfterDelete`
AFTER DELETE ON `kycQuestions`
FOR EACH ROW
BEGIN
    UPDATE kycTemplates
    SET totalQuestions = (SELECT COUNT(*) FROM kycQuestions WHERE templateId = OLD.templateId AND isActive = 1)
    WHERE id = OLD.templateId;
END$$

-- ============================================================
-- 9. Leads 分配相关触发器
-- ============================================================

-- Leads 分配插入后更新销售代表统计
CREATE TRIGGER `trgAfterLeadAssignmentInsert`
AFTER INSERT ON `leadAssignments`
FOR EACH ROW
BEGIN
    IF NEW.isActive = 1 THEN
        UPDATE salesRepresentatives
        SET currentLeadCount = currentLeadCount + 1
        WHERE id = NEW.salesRepId;
    END IF;
END$$

-- Leads 分配更新后更新销售代表统计
CREATE TRIGGER `trgAfterLeadAssignmentUpdate`
AFTER UPDATE ON `leadAssignments`
FOR EACH ROW
BEGIN
    -- 如果分配被停用
    IF OLD.isActive = 1 AND NEW.isActive = 0 THEN
        UPDATE salesRepresentatives
        SET currentLeadCount = GREATEST(currentLeadCount - 1, 0)
        WHERE id = OLD.salesRepId;
    END IF;

    -- 如果分配被激活
    IF OLD.isActive = 0 AND NEW.isActive = 1 THEN
        UPDATE salesRepresentatives
        SET currentLeadCount = currentLeadCount + 1
        WHERE id = NEW.salesRepId;
    END IF;

    -- 如果销售代表被更改
    IF OLD.salesRepId != NEW.salesRepId AND NEW.isActive = 1 THEN
        UPDATE salesRepresentatives
        SET currentLeadCount = GREATEST(currentLeadCount - 1, 0)
        WHERE id = OLD.salesRepId;

        UPDATE salesRepresentatives
        SET currentLeadCount = currentLeadCount + 1
        WHERE id = NEW.salesRepId;
    END IF;
END$$

-- Leads 分配删除后更新销售代表统计
CREATE TRIGGER `trgAfterLeadAssignmentDelete`
AFTER DELETE ON `leadAssignments`
FOR EACH ROW
BEGIN
    IF OLD.isActive = 1 THEN
        UPDATE salesRepresentatives
        SET currentLeadCount = GREATEST(currentLeadCount - 1, 0)
        WHERE id = OLD.salesRepId;
    END IF;
END$$

-- ============================================================
-- 10. 提款相关触发器
-- ============================================================

-- 提款状态更新触发器
CREATE TRIGGER `trgWithdrawalsAfterUpdate`
AFTER UPDATE ON `withdrawals`
FOR EACH ROW
BEGIN
    IF NEW.status != OLD.status THEN
        INSERT INTO withdrawalStatusHistory (withdrawalId, previousStatus, newStatus, description)
        VALUES (NEW.id, OLD.status, NEW.status, CONCAT('Status changed from ', OLD.status, ' to ', NEW.status));
    END IF;
END$$

DELIMITER ;

-- ============================================================
-- 触发器更新完成
-- ============================================================
-- 注意：
-- 1. 所有触发器名称已统一为驼峰命名法
-- 2. 所有触发器中的表名已从小写改为驼峰命名
-- 3. 删除了重复的触发器（如 after_verification_status_update 和 trg_ibPartners_before_insert）
-- 4. 执行此脚本前，请确保已执行表名重命名脚本
-- ============================================================
