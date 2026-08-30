-- Approve & Process keeps withdrawals in processing until PSP success callback
-- (or manual complete for non-PSP gateways). Do not set completed on approve.

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
