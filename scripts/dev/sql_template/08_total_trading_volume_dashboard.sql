-- IB Dashboard — Total Trading Volume (lots, lifetime).
-- Matches ClientIbDashboardController::statistics → totalTradingVolume
-- Formula: unique orderId from completed ib_commission_order → SUM(orders.volume) / 100
-- Deposit-only rebate rows (orderId IS NULL) are excluded.
-- Change @ib_partner_id (ibPartners.id).

SET @ib_partner_id = 218;

SELECT COALESCE(SUM(o.volume), 0) / 100 AS totalTradingVolumeLots
FROM (
  SELECT DISTINCT orderId
  FROM ib_commission_order
  WHERE ibPartnerId = @ib_partner_id
    AND status = 'completed'
    AND orderId IS NOT NULL
) x
INNER JOIN orders o ON o.id = x.orderId;

-- Breakdown (manual sum check)
SELECT
  ico.id AS commissionOrderId,
  ico.orderId,
  ico.depositId,
  CASE
    WHEN ico.orderId IS NOT NULL THEN 'trade'
    WHEN ico.depositId IS NOT NULL THEN 'deposit'
    ELSE 'unknown'
  END AS sourceType,
  o.volume / 100 AS lots,
  ico.commission,
  ico.status,
  ico.orderDate
FROM ib_commission_order ico
LEFT JOIN orders o ON o.id = ico.orderId
WHERE ico.ibPartnerId = @ib_partner_id
  AND ico.status = 'completed'
ORDER BY ico.orderDate DESC;
