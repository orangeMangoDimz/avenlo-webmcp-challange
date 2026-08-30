-- Own-account Net Deposit for one Sub-IB (no downline rollup).
-- Matches ClientIbDashboardController::getMemberOwnNetDeposit(ibPartners.userId).
-- Formula: SUM(completed deposits) − SUM(completed withdrawals) for that userId only.
-- Change @sub_ib_id only (ibPartners.id). Resolves userId via ibPartners.userId.

SET @sub_ib_id = 124;

SELECT ip.userId INTO @user_id
FROM ibPartners ip
WHERE ip.id = @sub_ib_id;

SELECT
  @sub_ib_id AS sub_ib_id,
  @user_id AS user_id,
  COALESCE((
    SELECT SUM(amount) FROM deposits
    WHERE userId = @user_id AND status = 'completed'
  ), 0) AS totalDeposit,
  COALESCE((
    SELECT SUM(amount) FROM withdrawals
    WHERE userId = @user_id AND status = 'completed'
  ), 0) AS totalWithdraw,
  COALESCE((
    SELECT SUM(amount) FROM deposits
    WHERE userId = @user_id AND status = 'completed'
  ), 0)
  - COALESCE((
    SELECT SUM(amount) FROM withdrawals
    WHERE userId = @user_id AND status = 'completed'
  ), 0) AS netDeposit;

-- Deposit breakdown (manual sum check)
SELECT id, amount, status, createdAt
FROM deposits
WHERE userId = @user_id AND status = 'completed'
ORDER BY createdAt;

-- Withdrawal breakdown (manual sum check)
SELECT id, amount, status, createdAt
FROM withdrawals
WHERE userId = @user_id AND status = 'completed'
ORDER BY createdAt;
