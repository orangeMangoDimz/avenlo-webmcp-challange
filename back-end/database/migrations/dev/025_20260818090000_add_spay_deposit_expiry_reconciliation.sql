-- 5Pay deposit expiry reconciliation.
-- Apply after the funding/deposit status migrations.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

ALTER TABLE `deposits`
    MODIFY COLUMN `status` ENUM(
        'unpaid', 'payment_failed', 'pending', 'processing',
        'completed', 'failed', 'rejected', 'cancelled', 'expired'
    ) NOT NULL DEFAULT 'pending',
    ADD COLUMN `spayLastStatusCheckAt` DATETIME NULL DEFAULT NULL
        COMMENT 'Last 5Pay order-status inquiry time (UTC)' AFTER `expiredAt`,
    ADD COLUMN `spayStatusCheckAttempts` INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Number of 5Pay order-status inquiries' AFTER `spayLastStatusCheckAt`,
    ADD COLUMN `spayStatusCheckLeaseUntil` DATETIME NULL DEFAULT NULL
        COMMENT 'Short lease preventing duplicate 5Pay inquiries' AFTER `spayStatusCheckAttempts`,
    ADD KEY `idx_deposits_spay_reconciliation` (
        `gatewaySettingId`, `status`, `expiredAt`, `spayLastStatusCheckAt`, `spayStatusCheckLeaseUntil`
    );

ALTER TABLE `depositStatusHistory`
    MODIFY COLUMN `previousStatus` ENUM(
        'unpaid', 'payment_failed', 'pending', 'processing',
        'completed', 'failed', 'rejected', 'cancelled', 'expired'
    ) DEFAULT NULL,
    MODIFY COLUMN `newStatus` ENUM(
        'unpaid', 'payment_failed', 'pending', 'processing',
        'completed', 'failed', 'rejected', 'cancelled', 'expired'
    ) NOT NULL;

DROP PROCEDURE IF EXISTS `spMarkDepositProviderTerminal`;
DELIMITER $$
CREATE PROCEDURE `spMarkDepositProviderTerminal`(
    IN pDepositId BIGINT,
    IN pNewStatus VARCHAR(20),
    IN pReason VARCHAR(500),
    IN pProviderStatus VARCHAR(20)
)
BEGIN
    DECLARE vCurrentStatus VARCHAR(20);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    IF pNewStatus NOT IN ('expired', 'cancelled') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid provider terminal deposit status';
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
        JSON_OBJECT('source', '5pay', 'providerStatus', pProviderStatus)
    );

    COMMIT;
END$$
DELIMITER ;

COMMIT;
