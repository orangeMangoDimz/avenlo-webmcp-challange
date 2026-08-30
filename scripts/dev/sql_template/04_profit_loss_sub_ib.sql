-- Commission-linked P/L for one Sub-IB (ibPartnerId on ib_commission_order only).
-- Excludes parent IB rows. Deduped by orderId.
-- Change @sub_ib_id only (e.g. 124).

SET @sub_ib_id = 124;

SELECT COALESCE(SUM(o.profit), 0) AS profit_loss
FROM (
  SELECT ico.orderId
  FROM ib_commission_order ico
  WHERE ico.ibPartnerId = @sub_ib_id
    AND ico.status != 'cancelled'
    AND ico.orderId IS NOT NULL
  GROUP BY ico.orderId
) x
INNER JOIN orders o ON o.id = x.orderId;

-- Per-trade breakdown (manual sum check)
SELECT
  o.trading_id,
  o.profit,
  o.closeTime
FROM (
  SELECT ico.orderId
  FROM ib_commission_order ico
  WHERE ico.ibPartnerId = @sub_ib_id
    AND ico.status != 'cancelled'
    AND ico.orderId IS NOT NULL
  GROUP BY ico.orderId
) x
INNER JOIN orders o ON o.id = x.orderId
ORDER BY o.closeTime;
