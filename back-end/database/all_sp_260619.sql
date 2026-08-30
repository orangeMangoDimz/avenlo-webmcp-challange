-- all_sp_260619.sql : 全部存储过程/函数 (来源: testcrm 5.7)
-- 7 个资金过程已补 RESIGNAL; 函数 fnBackfillRandomAlphaNum8 已补 NOT DETERMINISTIC + NO SQL (binlog 库也能建, 不再 1418)
-- 已去 DEFINER 及 mysqldump 样板注释
-- 开头 SET sql_mode 强制过程以严格模式创建(过程会固化创建时 sql_mode); 不依赖执行时连接的 sql_mode

SET NAMES utf8mb4;
SET @__saved_sql_mode = @@sql_mode;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';

DROP FUNCTION IF EXISTS `fnBackfillRandomAlphaNum8`;
DELIMITER $$
CREATE FUNCTION `fnBackfillRandomAlphaNum8`() RETURNS varchar(8) CHARSET utf8mb4
    NOT DETERMINISTIC
    NO SQL
BEGIN
    DECLARE v_chars VARCHAR(62) DEFAULT 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    DECLARE v_result VARCHAR(8) DEFAULT '';

    WHILE CHAR_LENGTH(v_result) < 8 DO
        SET v_result = CONCAT(
            v_result,
            SUBSTRING(v_chars, FLOOR(1 + RAND() * CHAR_LENGTH(v_chars)), 1)
        );
END WHILE;

RETURN v_result;
END
$$
DELIMITER ;

DROP PROCEDURE IF EXISTS `spApproveDeposit`;
DELIMITER $$
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
        RESIGNAL;
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
END
$$
DELIMITER ;

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
        RESIGNAL;
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
END
$$
DELIMITER ;

DROP PROCEDURE IF EXISTS `spCancelDeposit`;
DELIMITER $$
CREATE PROCEDURE `spCancelDeposit`(
    IN pDepositId BIGINT,
    IN pUserId BIGINT,
    IN pCancelReason VARCHAR(500)
)
BEGIN
    DECLARE vCurrentStatus VARCHAR(20);
    DECLARE vOwnerId BIGINT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT status, userId INTO vCurrentStatus, vOwnerId
    FROM deposits
    WHERE id = pDepositId
    FOR UPDATE;

    IF vCurrentStatus IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deposit not found';
    END IF;

    IF vOwnerId <> pUserId THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'You can only cancel your own deposit';
    END IF;

    IF vCurrentStatus <> 'pending' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only pending deposits can be cancelled';
    END IF;

    UPDATE deposits
    SET status = 'cancelled',
        failureReason = pCancelReason
    WHERE id = pDepositId;

    INSERT INTO depositStatusHistory (depositId, previousStatus, newStatus, description, changedBy, metadata)
    VALUES (
        pDepositId,
        vCurrentStatus,
        'cancelled',
        'Deposit cancelled by client',
        NULL,
        JSON_OBJECT('userId', pUserId, 'reason', pCancelReason)
    );

    COMMIT;
END
$$
DELIMITER ;

DROP PROCEDURE IF EXISTS `spCancelInternalTransfer`;
DELIMITER $$
CREATE PROCEDURE `spCancelInternalTransfer`(
    IN pTransferId BIGINT,
    IN pUserId BIGINT,
    IN pCancelReason VARCHAR(500)
)
BEGIN
    DECLARE vCurrentStatus VARCHAR(20);
    DECLARE vOwnerId BIGINT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
ROLLBACK;
RESIGNAL;
END;

START TRANSACTION;

SELECT status, userId INTO vCurrentStatus, vOwnerId
FROM internalTransfers
WHERE id = pTransferId
    FOR UPDATE;

IF vCurrentStatus IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Internal transfer not found';
END IF;

    IF vOwnerId <> pUserId THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'You can only cancel your own internal transfer';
END IF;

    IF vCurrentStatus <> 'pending' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only pending internal transfers can be cancelled';
END IF;

UPDATE internalTransfers
SET status = 'cancelled',
    failureReason = pCancelReason,
    clientNotes = pCancelReason
WHERE id = pTransferId;

INSERT INTO internalTransferStatusHistory (internalTransferId, previousStatus, newStatus, description, changedBy, metadata)
VALUES (
           pTransferId,
           vCurrentStatus,
           'cancelled',
           'Internal transfer cancelled by client',
           NULL,
           JSON_OBJECT('userId', pUserId, 'reason', pCancelReason)
       );

COMMIT;
END
$$
DELIMITER ;

DROP PROCEDURE IF EXISTS `spCancelWithdrawal`;
DELIMITER $$
CREATE PROCEDURE `spCancelWithdrawal`(
    IN pWithdrawalId BIGINT,
    IN pUserId BIGINT,
    IN pCancelReason VARCHAR(500)
)
BEGIN
    DECLARE vCurrentStatus VARCHAR(20);
    DECLARE vOwnerId BIGINT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT status, userId INTO vCurrentStatus, vOwnerId
    FROM withdrawals
    WHERE id = pWithdrawalId
    FOR UPDATE;

    IF vCurrentStatus IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Withdrawal not found';
    END IF;

    IF vOwnerId <> pUserId THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'You can only cancel your own withdrawal';
    END IF;

    IF vCurrentStatus <> 'pending' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only pending withdrawals can be cancelled';
    END IF;

    UPDATE withdrawals
    SET status = 'cancelled',
        adminNotes = pCancelReason
    WHERE id = pWithdrawalId;

    INSERT INTO withdrawalStatusHistory (withdrawalId, previousStatus, newStatus, description, changedBy, metadata)
    VALUES (
        pWithdrawalId,
        vCurrentStatus,
        'cancelled',
        'Withdrawal cancelled by client',
        NULL,
        JSON_OBJECT('userId', pUserId, 'reason', pCancelReason)
    );

    COMMIT;
END
$$
DELIMITER ;

DROP PROCEDURE IF EXISTS `spCheckAdminPermission`;
DELIMITER $$
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
END
$$
DELIMITER ;

DROP PROCEDURE IF EXISTS `spCompleteWithdrawal`;
DELIMITER $$
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
        RESIGNAL;
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

    COMMIT;
END
$$
DELIMITER ;

DROP PROCEDURE IF EXISTS `spCreateAdminUser`;
DELIMITER $$
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
END
$$
DELIMITER ;

DROP PROCEDURE IF EXISTS `spCreateDeposit`;
DELIMITER $$
CREATE PROCEDURE `spCreateDeposit`(
    IN pUserId BIGINT,
    IN pTradingAccountId BIGINT,
    IN pAmount DECIMAL(15,2),
    IN pAmountScale DECIMAL(18,8),
    IN pPlatformAmount DECIMAL(18,8),
    IN pDisplayUnit VARCHAR(50),
    IN pCurrencyCode VARCHAR(20),
    IN pExchangeRate DECIMAL(18,8),
    IN pPlatformFee DECIMAL(15,2),
    IN pQuotedAmount DECIMAL(15,2),
    IN pIpAddress VARCHAR(45),
    IN pGatewaySettingId INT UNSIGNED,
    IN pSupportContent TEXT,
    IN pCryptoAddressId INT UNSIGNED,
    IN pFiatCode VARCHAR(10),
    IN pCryptoCode VARCHAR(10),
    IN pNetwork VARCHAR(50),
    OUT pTransactionId VARCHAR(50),
    OUT pDepositId BIGINT
)
BEGIN
    DECLARE vTransactionId VARCHAR(50);
    DECLARE vMerchantOrderNo VARCHAR(100);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
ROLLBACK;
SET pTransactionId = NULL;
        SET pDepositId = NULL;
        RESIGNAL;
    END;

START TRANSACTION;

SET vTransactionId = CONCAT('TXN-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(FLOOR(RAND() * 999999), 6, '0'));

INSERT INTO deposits (
    transactionId,
    userId,
    tradingAccountId,
    gatewaySettingId,
    amount,
    amountScale,
    platformAmount,
    displayUnit,
    currencyCode,
    exchangeRate,
    platformFee,
    quotedAmount,
    supportContent,
    cryptoAddressId,
    fiatCode,
    cryptoCode,
    network,
    status,
    ipAddress,
    requestedAt
) VALUES (
             vTransactionId,
             pUserId,
             pTradingAccountId,
             pGatewaySettingId,
             pAmount,
             pAmountScale,
             pPlatformAmount,
             pDisplayUnit,
             pCurrencyCode,
             pExchangeRate,
             pPlatformFee,
             pQuotedAmount,
             pSupportContent,
             pCryptoAddressId,
             pFiatCode,
             pCryptoCode,
             pNetwork,
             'pending',
             pIpAddress,
             NOW()
         );

SET pDepositId = LAST_INSERT_ID();
    SET pTransactionId = vTransactionId;

    IF pCryptoAddressId IS NOT NULL THEN
        SET vMerchantOrderNo = CONCAT(pDepositId, '-', vTransactionId);
UPDATE deposits
SET merchantOrderNo = vMerchantOrderNo
WHERE id = pDepositId;
END IF;

INSERT INTO depositStatusHistory (
    depositId,
    previousStatus,
    newStatus,
    description
) VALUES (
             pDepositId,
             NULL,
             'pending',
             'Deposit request created'
         );

COMMIT;
END
$$
DELIMITER ;

DROP PROCEDURE IF EXISTS `spCreateInternalTransfer`;
DELIMITER $$
CREATE PROCEDURE `spCreateInternalTransfer`(
    IN pTransactionId VARCHAR(50),
    IN pUserId BIGINT,
    IN pFromType VARCHAR(30),
    IN pFromTradingAccountId BIGINT,
    IN pToTradingAccountId BIGINT,
    IN pAmount DECIMAL(15,2),
    IN pClientNotes TEXT,
    IN pIpAddress VARCHAR(45),
    IN pUserAgent TEXT,
    IN pFromAmountScale DECIMAL(18,8),
    IN pFromPlatformAmount DECIMAL(18,8),
    IN pFromDisplayUnit VARCHAR(50),
    IN pToAmountScale DECIMAL(18,8),
    IN pToPlatformAmount DECIMAL(18,8),
    IN pToDisplayUnit VARCHAR(50),
    OUT pTransferId BIGINT
)
BEGIN
    DECLARE vTransactionId VARCHAR(50);
    DECLARE vFromType VARCHAR(30);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
ROLLBACK;
SET pTransferId = NULL;
        RESIGNAL;
    END;

START TRANSACTION;

SET vTransactionId = NULLIF(TRIM(COALESCE(pTransactionId, '')), '');
    IF vTransactionId IS NULL THEN
        SET vTransactionId = CONCAT('ITF-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(FLOOR(RAND() * 99999), 5, '0'));
END IF;

    SET vFromType = NULLIF(TRIM(COALESCE(pFromType, '')), '');
    IF vFromType IS NULL THEN
        IF pFromTradingAccountId IS NOT NULL AND pFromTradingAccountId > 0 THEN
            SET vFromType = 'trading_account';
        ELSE
            SET vFromType = 'available_balance';
        END IF;
    END IF;

INSERT INTO internalTransfers (
    transactionId,
    userId,
    fromType,
    fromTradingAccountId,
    toTradingAccountId,
    amount,
    fromAmountScale,
    fromPlatformAmount,
    fromDisplayUnit,
    toAmountScale,
    toPlatformAmount,
    toDisplayUnit,
    status,
    requestedAt,
    clientNotes,
    ipAddress,
    userAgent
) VALUES (
             vTransactionId,
             pUserId,
             vFromType,
             pFromTradingAccountId,
             pToTradingAccountId,
             pAmount,
             pFromAmountScale,
             pFromPlatformAmount,
             pFromDisplayUnit,
             pToAmountScale,
             pToPlatformAmount,
             pToDisplayUnit,
             'pending',
             NOW(),
             pClientNotes,
             pIpAddress,
             pUserAgent
         );

SET pTransferId = LAST_INSERT_ID();

INSERT INTO internalTransferStatusHistory (
    internalTransferId,
    previousStatus,
    newStatus,
    description
) VALUES (
             pTransferId,
             NULL,
             'pending',
             'Internal transfer request created'
         );

COMMIT;
END
$$
DELIMITER ;

DROP PROCEDURE IF EXISTS `spCreateWithdrawal`;
DELIMITER $$
CREATE PROCEDURE `spCreateWithdrawal`(
    IN pTransactionId VARCHAR(50),
    IN pUserId INT,
    IN pTradingAccountId INT,
    IN pAmount DECIMAL(15,2),
    IN pCurrencyCode VARCHAR(20),
    IN pExchangeRate DECIMAL(20,8),
    IN pPlatformFee DECIMAL(15,2),
    IN pQuotedAmount DECIMAL(15,2),
    IN pNetworkFee DECIMAL(15,2),
    IN pWithdrawalReason VARCHAR(500),
    IN pIpAddress VARCHAR(45),
    IN pGatewaySettingId INT UNSIGNED,
    IN pSupportContent TEXT,
    IN pAmountScale DECIMAL(18,8),
    IN pPlatformAmount DECIMAL(18,8),
    IN pDisplayUnit VARCHAR(50),
    OUT pWithdrawalId BIGINT
)
BEGIN
    DECLARE vPrevCount INT;
    DECLARE vPrevAmount DECIMAL(15,2);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET pWithdrawalId = NULL;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT COUNT(*), COALESCE(SUM(amount), 0)
    INTO vPrevCount, vPrevAmount
    FROM withdrawals
    WHERE userId = pUserId
      AND status = 'completed'
      AND completedAt >= DATE_SUB(NOW(), INTERVAL 30 DAY);

    INSERT INTO withdrawals (
        transactionId, userId, tradingAccountId,
        gatewaySettingId, amount, amountScale, platformAmount, displayUnit,
        currencyCode, withdrawalReason, withdrawalSubmissionId,
        networkFee, platformFee, quotedAmount, exchangeRate, status,
        supportContent, previousWithdrawalsCount30Days, previousWithdrawalsAmount30Days,
        ipAddress, requestedAt
    ) VALUES (
        pTransactionId, pUserId, pTradingAccountId,
        pGatewaySettingId, pAmount, pAmountScale, pPlatformAmount, pDisplayUnit,
        pCurrencyCode, pWithdrawalReason, NULL,
        COALESCE(pNetworkFee, 0.00), COALESCE(pPlatformFee, 0.00), COALESCE(pQuotedAmount, pAmount), pExchangeRate, 'pending',
        pSupportContent, vPrevCount, vPrevAmount,
        pIpAddress, NOW()
    );

    SET pWithdrawalId = LAST_INSERT_ID();

    INSERT INTO withdrawalStatusHistory (withdrawalId, previousStatus, newStatus, description)
    VALUES (pWithdrawalId, NULL, 'pending', 'Withdrawal request submitted by client');

    COMMIT;
END
$$
DELIMITER ;

DROP PROCEDURE IF EXISTS `spGetTransactionStatistics`;
DELIMITER $$
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
    SELECT COALESCE(SUM(amount), 0), COUNT(*)
    INTO pTotalDeposits, pDepositCount
    FROM deposits
    WHERE status = 'completed'
      AND DATE(completedAt) BETWEEN pStartDate AND pEndDate;

    SELECT COALESCE(SUM(quotedAmount), 0), COUNT(*)
    INTO pTotalWithdrawals, pWithdrawalCount
    FROM withdrawals
    WHERE status = 'completed'
      AND DATE(completedAt) BETWEEN pStartDate AND pEndDate;

    SET pNetFlow = pTotalDeposits - pTotalWithdrawals;
END
$$
DELIMITER ;

DROP PROCEDURE IF EXISTS `spLogAdminLogin`;
DELIMITER $$
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
END
$$
DELIMITER ;

DROP PROCEDURE IF EXISTS `spRejectDeposit`;
DELIMITER $$
CREATE PROCEDURE `spRejectDeposit`(
    IN pDepositId BIGINT,
    IN pRejectedBy BIGINT,
    IN pRejectionReasonId INT,
    IN pRejectionNotes TEXT,
    IN pCustomReason VARCHAR(500),
    IN pAdminNotes TEXT
)
BEGIN
    DECLARE vCurrentStatus VARCHAR(20);
    DECLARE vFinalNotes TEXT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT status INTO vCurrentStatus
    FROM deposits
    WHERE id = pDepositId
    FOR UPDATE;

    IF vCurrentStatus IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deposit not found';
    END IF;

    IF vCurrentStatus <> 'pending' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only pending deposits can be rejected';
    END IF;

    IF pCustomReason IS NOT NULL AND pCustomReason <> '' THEN
        SET vFinalNotes = pCustomReason;
    ELSE
        SET vFinalNotes = pRejectionNotes;
    END IF;

    UPDATE deposits
    SET status = 'rejected',
        rejectedAt = NOW(),
        rejectedBy = pRejectedBy,
        rejectionReasonId = pRejectionReasonId,
        rejectionNotes = vFinalNotes,
        adminNotes = pAdminNotes
    WHERE id = pDepositId;

    INSERT INTO depositStatusHistory (depositId, previousStatus, newStatus, description, changedBy, metadata)
    VALUES (
        pDepositId,
        vCurrentStatus,
        'rejected',
        'Deposit rejected by admin',
        pRejectedBy,
        JSON_OBJECT('rejectionReasonId', pRejectionReasonId, 'rejectionNotes', vFinalNotes)
    );

    COMMIT;
END
$$
DELIMITER ;

DROP PROCEDURE IF EXISTS `spRejectInternalTransfer`;
DELIMITER $$
CREATE PROCEDURE `spRejectInternalTransfer`(
    IN pTransferId BIGINT,
    IN pRejectedBy BIGINT,
    IN pRejectionReasonId INT,
    IN pRejectionNotes TEXT,
    IN pCustomReason VARCHAR(500),
    IN pAdminNotes TEXT
)
BEGIN
    DECLARE vCurrentStatus VARCHAR(20);
    DECLARE vFinalNotes TEXT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
ROLLBACK;
RESIGNAL;
END;

START TRANSACTION;

SELECT status INTO vCurrentStatus
FROM internalTransfers
WHERE id = pTransferId
    FOR UPDATE;

IF vCurrentStatus IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Internal transfer not found';
END IF;

    IF vCurrentStatus <> 'pending' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only pending internal transfers can be rejected';
END IF;

    IF pCustomReason IS NOT NULL AND pCustomReason <> '' THEN
        SET vFinalNotes = pCustomReason;
ELSE
        SET vFinalNotes = pRejectionNotes;
END IF;

UPDATE internalTransfers
SET status = 'rejected',
    failureReason = vFinalNotes,
    rejectedAt = NOW(),
    rejectedBy = pRejectedBy,
    rejectionReasonId = pRejectionReasonId,
    rejectionNotes = vFinalNotes,
    adminNotes = pAdminNotes
WHERE id = pTransferId;

INSERT INTO internalTransferStatusHistory (internalTransferId, previousStatus, newStatus, description, changedBy, metadata)
VALUES (
           pTransferId,
           vCurrentStatus,
           'rejected',
           'Internal transfer rejected by admin',
           pRejectedBy,
           JSON_OBJECT('rejectionReasonId', pRejectionReasonId, 'rejectionNotes', vFinalNotes)
       );

COMMIT;
END
$$
DELIMITER ;

DROP PROCEDURE IF EXISTS `spRejectWithdrawal`;
DELIMITER $$
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
        RESIGNAL;
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
END
$$
DELIMITER ;

SET sql_mode = @__saved_sql_mode;
