-- Own-account Net Deposit for one Direct Client (no downline).
-- Matches ClientIbDashboardController::getMemberOwnNetDeposit($memberId).
-- Formula: SUM(completed deposits) − SUM(completed withdrawals) for this userId only.
-- Change @client_id only (clientUsers.id = deposits/withdrawals.userId).

SET @client_id = 635;

SELECT
  @client_id AS user_id,
  COALESCE((
    SELECT SUM(amount) FROM deposits
    WHERE userId = @client_id AND status = 'completed'
  ), 0) AS totalDeposit,
  COALESCE((
    SELECT SUM(amount) FROM withdrawals
    WHERE userId = @client_id AND status = 'completed'
  ), 0) AS totalWithdraw,
  COALESCE((
    SELECT SUM(amount) FROM deposits
    WHERE userId = @client_id AND status = 'completed'
  ), 0)
  - COALESCE((
    SELECT SUM(amount) FROM withdrawals
    WHERE userId = @client_id AND status = 'completed'
  ), 0) AS netDeposit;

-- Deposit breakdown (manual sum check)
SELECT id, amount, status, createdAt
FROM deposits
WHERE userId = @client_id AND status = 'completed'
ORDER BY createdAt;

-- Withdrawal breakdown (manual sum check)
SELECT id, amount, status, createdAt
FROM withdrawals
WHERE userId = @client_id AND status = 'completed'
ORDER BY createdAt;
