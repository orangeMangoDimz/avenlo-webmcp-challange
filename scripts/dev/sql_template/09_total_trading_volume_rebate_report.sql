-- Rebate Report — Total Trading Volume (lots).
-- Matches ClientCommissionReportController::getStatisticsPayloadForIb → totalTradingVolume
-- Formula: unique orderId from completed ib_commission_order → SUM(orders.volume) / 100
-- Deposit-only rebate rows (orderId IS NULL) are excluded.
-- Change @ib_partner_id (ibPartners.id).
-- @start_date / @end_date: set both NULL for all-time; set both for a range (ico.orderDate).

SET @ib_partner_id = 218;
SET @start_date = NULL;
SET @end_date = NULL;

SELECT COALESCE(SUM(o.volume), 0) / 100 AS totalTradingVolumeLots
FROM (
  SELECT DISTINCT orderId
  FROM ib_commission_order
  WHERE ibPartnerId = @ib_partner_id
    AND status = 'completed'
    AND orderId IS NOT NULL
    AND (@start_date IS NULL OR orderDate >= @start_date)
    AND (@end_date IS NULL OR orderDate <= @end_date)
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
  AND (@start_date IS NULL OR ico.orderDate >= @start_date)
  AND (@end_date IS NULL OR ico.orderDate <= @end_date)
ORDER BY ico.orderDate DESC;
