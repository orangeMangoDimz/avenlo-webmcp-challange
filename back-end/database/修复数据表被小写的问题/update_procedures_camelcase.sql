
-- ============================================================
-- 存储过程更新脚本：修正存储过程名称和表名为驼峰命名
-- 数据库：utrada_crm
-- 说明：将存储过程名称和存储过程中的表名从小写改为驼峰命名法
-- ============================================================

-- ============================================================
-- 先删除所有旧存储过程
-- ============================================================

DROP PROCEDURE IF EXISTS `spApproveDeposit`;
DROP PROCEDURE IF EXISTS `spCreateDeposit`;
DROP PROCEDURE IF EXISTS `spApproveWithdrawal`;
DROP PROCEDURE IF EXISTS `spCompleteWithdrawal`;
DROP PROCEDURE IF EXISTS `spCreateWithdrawal`;
DROP PROCEDURE IF EXISTS `spRejectWithdrawal`;
DROP PROCEDURE IF EXISTS `spGetTransactionStatistics`;
DROP PROCEDURE IF EXISTS `spCheckAdminPermission`;
DROP PROCEDURE IF EXISTS `spCreateAdminUser`;
DROP PROCEDURE IF EXISTS `spLogAdminLogin`;
DROP PROCEDURE IF EXISTS `sp_approveIbApplication`;
DROP PROCEDURE IF EXISTS `sp_rejectIbApplication`;
DROP PROCEDURE IF EXISTS `sp_calculateIbCommission`;

-- ============================================================
-- 创建所有存储过程（使用 DELIMITER $$）
-- ============================================================

DELIMITER $$

-- ============================================================
-- 1. 存款相关存储过程
-- ============================================================

-- 批准存款
CREATE PROCEDURE `spApproveDeposit`(
    IN pDepositId BIGINT,
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

    SELECT status INTO vCurrentStatus FROM deposits WHERE id = pDepositId;

    UPDATE deposits
    SET status = 'completed',
        approvedAt = NOW(),
        approvedBy = pApprovedBy,
        completedAt = NOW(),
        adminNotes = pAdminNotes
    WHERE id = pDepositId;

    INSERT INTO depositStatusHistory (depositId, previousStatus, newStatus, description, changedBy)
    VALUES (pDepositId, vCurrentStatus, 'completed', 'Deposit approved and completed by admin', pApprovedBy);

    COMMIT;
END$$

-- 创建存款
CREATE PROCEDURE `spCreateDeposit`(
    IN pUserId BIGINT,
    IN pTradingAccountId BIGINT,
    IN pPaymentMethodId INT,
    IN pAmount DECIMAL(15,2),
    IN pAmountCrypto DECIMAL(18,8),
    IN pFromAddress VARCHAR(255),
    IN pIpAddress VARCHAR(45),
    OUT pTransactionId VARCHAR(50),
    OUT pDepositId BIGINT
)
BEGIN
    DECLARE vTransactionId VARCHAR(50);
    DECLARE vNetAmount DECIMAL(15,2);
    DECLARE vPlatformFee DECIMAL(15,2);
    DECLARE vFeePercentage DECIMAL(5,2);
    DECLARE vFeeFixed DECIMAL(15,2);
    DECLARE vChargeToClient TINYINT(1);
    DECLARE vPaymentType VARCHAR(10);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET pTransactionId = NULL;
        SET pDepositId = NULL;
    END;

    START TRANSACTION;

    SET vTransactionId = CONCAT('TXN-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(FLOOR(RAND() * 999999), 6, '0'));

    SELECT methodType INTO vPaymentType FROM paymentMethods WHERE id = pPaymentMethodId;

    SELECT feePercentage, feeFixed, chargeToClient
    INTO vFeePercentage, vFeeFixed, vChargeToClient
    FROM transactionFees
    WHERE transactionType = 'deposit'
    AND paymentType = vPaymentType
    AND isActive = 1
    LIMIT 1;

    SET vPlatformFee = (pAmount * vFeePercentage / 100) + vFeeFixed;
    IF vChargeToClient = 1 THEN
        SET vNetAmount = pAmount - vPlatformFee;
    ELSE
        SET vNetAmount = pAmount;
    END IF;

    INSERT INTO deposits (
        transactionId, userId, tradingAccountId, paymentMethodId,
        amount, amountCrypto, fromAddress, platformFee, netAmount,
        status, ipAddress, requestedAt
    ) VALUES (
        vTransactionId, pUserId, pTradingAccountId, pPaymentMethodId,
        pAmount, pAmountCrypto, pFromAddress, vPlatformFee, vNetAmount,
        'pending', pIpAddress, NOW()
    );

    SET pDepositId = LAST_INSERT_ID();
    SET pTransactionId = vTransactionId;

    INSERT INTO depositStatusHistory (depositId, previousStatus, newStatus, description)
    VALUES (pDepositId, NULL, 'pending', 'Deposit initiated by client');

    COMMIT;
END$$

-- ============================================================
-- 2. 提款相关存储过程
-- ============================================================

-- 批准提款
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

    SELECT status INTO vCurrentStatus FROM withdrawals WHERE id = pWithdrawalId;

    UPDATE withdrawals
    SET status = 'processing',
        approvedAt = NOW(),
        approvedBy = pApprovedBy,
        adminNotes = pAdminNotes
    WHERE id = pWithdrawalId;

    INSERT INTO withdrawalStatusHistory (withdrawalId, previousStatus, newStatus, description, changedBy)
    VALUES (pWithdrawalId, vCurrentStatus, 'processing', 'Withdrawal approved and processing', pApprovedBy);

    COMMIT;
END$$

-- 完成提款
CREATE PROCEDURE `spCompleteWithdrawal`(
    IN pWithdrawalId BIGINT,
    IN pCompletedBy BIGINT,
    IN pTransactionHash VARCHAR(255)
)
BEGIN
    DECLARE vCurrentStatus VARCHAR(20);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
    END;

    START TRANSACTION;

    SELECT status INTO vCurrentStatus FROM withdrawals WHERE id = pWithdrawalId;

    UPDATE withdrawals
    SET status = 'completed',
        completedAt = NOW(),
        transactionHash = pTransactionHash
    WHERE id = pWithdrawalId;

    INSERT INTO withdrawalStatusHistory (withdrawalId, previousStatus, newStatus, description, changedBy)
    VALUES (pWithdrawalId, vCurrentStatus, 'completed', 'Withdrawal completed - funds sent to client', pCompletedBy);

    UPDATE clientSavedWallets
    SET lastUsedAt = NOW(), usageCount = usageCount + 1
    WHERE userId = (SELECT userId FROM withdrawals WHERE id = pWithdrawalId)
    AND walletAddress = (SELECT destinationAddress FROM withdrawals WHERE id = pWithdrawalId);

    COMMIT;
END$$

-- 创建提款
-- 增加 pClientPaymentAccountId：关联 client_payment_accounts.id，有值时以关联表为准，destinationAddress/destinationLabel 可传 NULL
CREATE PROCEDURE `spCreateWithdrawal`(
    IN pUserId INT,
    IN pTradingAccountId INT,
    IN pAmount DECIMAL(15,2),
    IN pDestinationAddress VARCHAR(255),
    IN pDestinationLabel VARCHAR(200),
    IN pWithdrawalReason VARCHAR(500),
    IN pIpAddress VARCHAR(45),
    IN pClientPaymentAccountId INT UNSIGNED,
    OUT pTransactionId VARCHAR(50),
    OUT pWithdrawalId BIGINT
)
BEGIN
    DECLARE vTransactionId VARCHAR(50);
    DECLARE vNetAmount DECIMAL(15,2);
    DECLARE vPlatformFee DECIMAL(15,2);
    DECLARE vFeePercentage DECIMAL(5,2);
    DECLARE vFeeFixed DECIMAL(15,2);
    DECLARE vChargeToClient TINYINT(1);
    DECLARE vPrevCount INT;
    DECLARE vPrevAmount DECIMAL(15,2);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
ROLLBACK;
SET pTransactionId = NULL;
        SET pWithdrawalId = NULL;
END;

START TRANSACTION;

-- Generate unique transaction ID
SET vTransactionId = CONCAT('TXN-', DATE_FORMAT(NOW(), '%Y%m%d'), '-W', LPAD(FLOOR(RAND() * 999999), 6, '0'));

    -- Get fee configuration (出金统一使用 fiat 类型)
SELECT feePercentage, feeFixed, chargeToClient
INTO vFeePercentage, vFeeFixed, vChargeToClient
FROM transactionFees
WHERE transactionType = 'withdrawal'
  AND paymentType = 'fiat'
  AND isActive = 1
    LIMIT 1;

-- 如果找不到手续费配置，使用默认值（0手续费）
IF vFeePercentage IS NULL THEN
        SET vFeePercentage = 0.00;
        SET vFeeFixed = 0.00;
        SET vChargeToClient = 0;
END IF;

    -- Calculate fees and net amount
    SET vPlatformFee = (pAmount * vFeePercentage / 100) + vFeeFixed;

    IF vChargeToClient = 1 THEN
        SET vNetAmount = pAmount - vPlatformFee;
ELSE
        SET vNetAmount = pAmount;
END IF;

    -- Get previous withdrawals count and amount (30 days)
SELECT
    COUNT(*), COALESCE(SUM(amount), 0)
INTO vPrevCount, vPrevAmount
FROM withdrawals
WHERE userId = pUserId
  AND status = 'completed'
  AND completedAt >= DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Insert withdrawal record (paymentMethodId 设为 NULL；clientPaymentAccountId 关联 client_payment_accounts，有值时不再依赖 destinationAddress 存收款信息)
INSERT INTO withdrawals (
    transactionId, userId, tradingAccountId, paymentMethodId,
    amount, destinationAddress, destinationLabel, clientPaymentAccountId, withdrawalReason,
    platformFee, netAmount, status,
    previousWithdrawalsCount30Days, previousWithdrawalsAmount30Days,
    ipAddress, requestedAt
) VALUES (
             vTransactionId, pUserId, pTradingAccountId, NULL,
             pAmount, pDestinationAddress, pDestinationLabel, pClientPaymentAccountId, pWithdrawalReason,
             vPlatformFee, vNetAmount, 'pending',
             vPrevCount, vPrevAmount,
             pIpAddress, NOW()
         );

SET pWithdrawalId = LAST_INSERT_ID();
    SET pTransactionId = vTransactionId;

    -- Insert initial status history
INSERT INTO withdrawalStatusHistory (withdrawalId, previousStatus, newStatus, description)
VALUES (pWithdrawalId, NULL, 'pending', 'Withdrawal request submitted by client');

COMMIT;
END$$

-- 拒绝提款
CREATE PROCEDURE `spRejectWithdrawal`(
    IN pWithdrawalId BIGINT,
    IN pRejectedBy BIGINT,
    IN pRejectionReasonId INT,
    IN pRejectionNotes TEXT,
    IN pCustomReason TEXT
)
BEGIN
    DECLARE vCurrentStatus VARCHAR(20);
    DECLARE vFinalNotes TEXT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
    END;

    START TRANSACTION;

    SELECT status INTO vCurrentStatus FROM withdrawals WHERE id = pWithdrawalId;

    IF pCustomReason IS NOT NULL AND pCustomReason != '' THEN
        SET vFinalNotes = pCustomReason;
    ELSE
        SET vFinalNotes = pRejectionNotes;
    END IF;

    UPDATE withdrawals
    SET status = 'rejected',
        rejectedAt = NOW(),
        rejectedBy = pRejectedBy,
        rejectionReasonId = pRejectionReasonId,
        rejectionNotes = vFinalNotes
    WHERE id = pWithdrawalId;

    INSERT INTO withdrawalStatusHistory (withdrawalId, previousStatus, newStatus, description, changedBy)
    VALUES (pWithdrawalId, vCurrentStatus, 'rejected', 'Withdrawal rejected by admin', pRejectedBy);

    COMMIT;
END$$

-- ============================================================
-- 3. 交易统计存储过程
-- ============================================================

-- 获取交易统计
CREATE PROCEDURE `spGetTransactionStatistics`(
    IN pStartDate DATE,
    IN pEndDate DATE,
    OUT pTotalDeposits DECIMAL(15,2),
    OUT pDepositCount INT,
    OUT pTotalWithdrawals DECIMAL(15,2),
    OUT pWithdrawalCount INT,
    OUT pNetFlow DECIMAL(15,2)
)
BEGIN
    SELECT COALESCE(SUM(netAmount), 0), COUNT(*)
    INTO pTotalDeposits, pDepositCount
    FROM deposits
    WHERE status = 'completed'
    AND DATE(completedAt) BETWEEN pStartDate AND pEndDate;

    SELECT COALESCE(SUM(netAmount), 0), COUNT(*)
    INTO pTotalWithdrawals, pWithdrawalCount
    FROM withdrawals
    WHERE status = 'completed'
    AND DATE(completedAt) BETWEEN pStartDate AND pEndDate;

    SET pNetFlow = pTotalDeposits - pTotalWithdrawals;
END$$

-- ============================================================
-- 4. 管理员相关存储过程
-- ============================================================

-- 检查管理员权限
CREATE PROCEDURE `spCheckAdminPermission`(
    IN pUserId BIGINT,
    IN pPermissionKey VARCHAR(100)
)
BEGIN
    DECLARE vHasPermission TINYINT DEFAULT 0;

    SELECT COUNT(*) INTO vHasPermission
    FROM vAdminUserPermissions
    WHERE userId = pUserId
    AND permissionKey = pPermissionKey
    AND isGranted = 1;

    SELECT vHasPermission as hasPermission;
END$$

-- 创建管理员用户
CREATE PROCEDURE `spCreateAdminUser`(
    IN pUsername VARCHAR(50),
    IN pEmail VARCHAR(100),
    IN pPasswordHash VARCHAR(255),
    IN pFullName VARCHAR(100),
    IN pRoleId INT,
    IN pStatus ENUM('active','inactive'),
    IN pCreatedBy BIGINT,
    OUT pNewUserId BIGINT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET pNewUserId = NULL;
    END;

    START TRANSACTION;

    SET @initials = UPPER(CONCAT(
        SUBSTRING(SUBSTRING_INDEX(pFullName, ' ', 1), 1, 1),
        SUBSTRING(SUBSTRING_INDEX(pFullName, ' ', -1), 1, 1)
    ));

    INSERT INTO adminUsers (
        username, email, passwordHash, fullName,
        avatarInitials, roleId, status, createdBy
    ) VALUES (
        pUsername, pEmail, pPasswordHash, pFullName,
        @initials, pRoleId, pStatus, pCreatedBy
    );

    SET pNewUserId = LAST_INSERT_ID();

    INSERT INTO adminUserProfiles (userId) VALUES (pNewUserId);

    COMMIT;
END$$

-- 记录管理员登录
CREATE PROCEDURE `spLogAdminLogin`(
    IN pUserId BIGINT,
    IN pUsername VARCHAR(100),
    IN pEmail VARCHAR(100),
    IN pStatus ENUM('success','failed','blocked'),
    IN pFailureReason VARCHAR(255),
    IN pIpAddress VARCHAR(45),
    IN pUserAgent TEXT,
    IN pRememberMe TINYINT
)
BEGIN
    DECLARE vSessionId VARCHAR(255);

    SET vSessionId = UUID();

    INSERT INTO adminLoginLogs (
        userId, username, email, loginStatus, failureReason,
        ipAddress, userAgent, rememberMe, sessionId
    ) VALUES (
        pUserId, pUsername, pEmail, pStatus, pFailureReason,
        pIpAddress, pUserAgent, pRememberMe, vSessionId
    );

    IF pStatus = 'success' AND pUserId IS NOT NULL THEN
        UPDATE adminUsers
        SET lastLoginAt = NOW(),
            lastLoginIp = pIpAddress,
            failedLoginAttempts = 0
        WHERE id = pUserId;
    END IF;

    IF pStatus = 'failed' AND pUserId IS NOT NULL THEN
        UPDATE adminUsers
        SET failedLoginAttempts = failedLoginAttempts + 1
        WHERE id = pUserId;

        UPDATE adminUsers
        SET isLocked = 1
        WHERE id = pUserId
        AND failedLoginAttempts >= (SELECT settingValue FROM adminSystemSettings WHERE settingKey = 'loginMaxAttempts');
    END IF;

    SELECT vSessionId as sessionId;
END$$

-- ============================================================
-- 5. IB 申请相关存储过程
-- ============================================================

-- 批准 IB 申请
CREATE PROCEDURE `sp_approveIbApplication`(
    IN p_applicationId INT,
    IN p_tierLevelId INT,
    IN p_approvedBy BIGINT,
    OUT p_ibPartnerId INT,
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(500)
)
BEGIN
    DECLARE v_applicantEmail VARCHAR(200);
    DECLARE v_companyName VARCHAR(200);
    DECLARE v_ibCode VARCHAR(50);
    DECLARE v_existingIbId INT;
    DECLARE v_ibCount INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Error occurred during IB application approval';
    END;

    START TRANSACTION;

    -- Check if application exists and is in correct status
    SELECT applicantEmail, companyName
    INTO v_applicantEmail, v_companyName
    FROM ibApplications
    WHERE id = p_applicationId
    AND applicationStatus IN ('pending', 'in_review', 'more_info_requested');

    IF v_applicantEmail IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'Application not found or not in approvable status';
        ROLLBACK;
    ELSE
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

        SET v_ibCode = CONCAT('IB', v_ibCount);

        -- Create IB partner record
        INSERT INTO ibPartners (
            ibCode,
            companyName,
            ibType,
            tierLevelId,
            contactPerson,
            contactEmail,
            contactPhone,
            country,
            registrationDate,
            approvalDate,
            approvedBy,
            status
        )
        SELECT
            v_ibCode,
            COALESCE(companyName, applicantName),
            ibType,
            p_tierLevelId,
            applicantName,
            applicantEmail,
            applicantPhone,
            country,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP,
            p_approvedBy,
            'active'
        FROM ibApplications
        WHERE id = p_applicationId;

        SET p_ibPartnerId = LAST_INSERT_ID();

        -- Update application status
        UPDATE ibApplications
        SET applicationStatus = 'approved',
            reviewCompletedDate = CURRENT_TIMESTAMP,
            approvedBy = p_approvedBy,
            createdIbPartnerId = p_ibPartnerId
        WHERE id = p_applicationId;

        -- Copy rule assignments from application to IB partner
        INSERT INTO ibPartnerRuleAssignments (ibPartnerId, ruleId, assignedBy)
        SELECT p_ibPartnerId, ruleId, p_approvedBy
        FROM ibApplicationRuleAssignments
        WHERE applicationId = p_applicationId;

        -- Log activity
        INSERT INTO ibActivityLog (ibPartnerId, applicationId, activityType, activityDescription, performedBy, performedByType)
        VALUES (p_ibPartnerId, p_applicationId, 'application_approved', CONCAT('IB application approved. IB Code: ', v_ibCode), p_approvedBy, 'admin');

        COMMIT;
        SET p_success = TRUE;
        SET p_message = CONCAT('IB application approved successfully. IB Code: ', v_ibCode);
    END IF;
END$$

-- 拒绝 IB 申请
CREATE PROCEDURE `sp_rejectIbApplication`(
    IN p_applicationId INT,
    IN p_rejectedBy BIGINT,
    IN p_rejectionReason TEXT,
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(500)
)
BEGIN
    DECLARE v_applicantEmail VARCHAR(200);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Error occurred during IB application rejection';
    END;

    START TRANSACTION;

    SELECT applicantEmail
    INTO v_applicantEmail
    FROM ibApplications
    WHERE id = p_applicationId
    AND applicationStatus IN ('pending', 'in_review', 'more_info_requested');

    IF v_applicantEmail IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'Application not found or not in rejectable status';
        ROLLBACK;
    ELSE
        UPDATE ibApplications
        SET applicationStatus = 'rejected',
            reviewCompletedDate = CURRENT_TIMESTAMP,
            rejectedBy = p_rejectedBy,
            rejectionReason = p_rejectionReason
        WHERE id = p_applicationId;

        INSERT INTO ibActivityLog (applicationId, activityType, activityDescription, performedBy, performedByType)
        VALUES (p_applicationId, 'application_rejected', CONCAT('IB application rejected. Reason: ', p_rejectionReason), p_rejectedBy, 'admin');

        COMMIT;
        SET p_success = TRUE;
        SET p_message = 'IB application rejected successfully';
    END IF;
END$$

-- 计算 IB 佣金
CREATE PROCEDURE `sp_calculateIbCommission`(
    IN p_ibPartnerId INT,
    IN p_periodStart DATE,
    IN p_periodEnd DATE,
    OUT p_totalCommission DECIMAL(15,2),
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(500)
)
BEGIN
    DECLARE v_ibExists INT;

    SELECT COUNT(*) INTO v_ibExists FROM ibPartners WHERE id = p_ibPartnerId;

    IF v_ibExists = 0 THEN
        SET p_success = FALSE;
        SET p_message = 'IB partner not found';
        SET p_totalCommission = 0.00;
    ELSE
        -- 佣金计算逻辑（这里需要根据实际业务逻辑实现）
        SET p_totalCommission = 0.00;

        INSERT INTO ibActivityLog (ibPartnerId, activityType, activityDescription, performedByType, metadata)
        VALUES (
            p_ibPartnerId,
            'commission_calculated',
            CONCAT('Commission calculated for period ', p_periodStart, ' to ', p_periodEnd),
            'system',
            JSON_OBJECT('periodStart', p_periodStart, 'periodEnd', p_periodEnd, 'amount', p_totalCommission)
        );

        SET p_success = TRUE;
        SET p_message = 'Commission calculated successfully';
    END IF;
END$$

DELIMITER ;

-- ============================================================
-- 存储过程更新完成
-- ============================================================
-- 注意：
-- 1. 大部分存储过程名称已统一为驼峰命名法
-- 2. 以下三个存储过程保持下划线命名（按用户要求）：
--    - sp_approveIbApplication
--    - sp_calculateIbCommission
--    - sp_rejectIbApplication
-- 2. 所有存储过程中的表名已从小写改为驼峰命名
-- 3. 执行此脚本前，请确保已执行表名重命名脚本和视图更新脚本
-- ============================================================
