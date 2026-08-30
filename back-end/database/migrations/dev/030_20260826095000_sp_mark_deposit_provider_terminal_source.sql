-- Pass real PSP source into depositStatusHistory metadata.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

DROP PROCEDURE IF EXISTS `spMarkDepositProviderTerminal`;
DELIMITER $$
CREATE PROCEDURE `spMarkDepositProviderTerminal`(
    IN pDepositId BIGINT,
    IN pNewStatus VARCHAR(20),
    IN pReason VARCHAR(500),
    IN pProviderStatus VARCHAR(20),
    IN pProviderSource VARCHAR(32)
)
BEGIN
    DECLARE vCurrentStatus VARCHAR(20);
    DECLARE vProviderSource VARCHAR(32);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    IF pNewStatus NOT IN ('expired', 'cancelled') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid provider terminal deposit status';
    END IF;

    SET vProviderSource = LOWER(TRIM(IFNULL(pProviderSource, '')));
    IF vProviderSource = '' THEN
        SET vProviderSource = '5pay';
    END IF;

    START TRANSACTION;

    SELECT status INTO vCurrentStatus
    FROM deposits
    WHERE id = pDepositId
    FOR UPDATE;

    IF vCurrentStatus IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deposit not found';
    END IF;

    IF vCurrentStatus NOT IN ('unpaid', 'pending', 'processing') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deposit is already terminal';
    END IF;

    UPDATE deposits
    SET status = pNewStatus,
        failureReason = pReason,
        expiredAt = CASE
            WHEN pNewStatus = 'expired' THEN COALESCE(expiredAt, UTC_TIMESTAMP())
            ELSE expiredAt
        END,
        spayStatusCheckLeaseUntil = NULL
    WHERE id = pDepositId;

    INSERT INTO depositStatusHistory (
        depositId, previousStatus, newStatus, description, changedBy, metadata
    ) VALUES (
        pDepositId,
        vCurrentStatus,
        pNewStatus,
        pReason,
        NULL,
        JSON_OBJECT('source', vProviderSource, 'providerStatus', pProviderStatus)
    );

    COMMIT;
END$$
DELIMITER ;

COMMIT;
