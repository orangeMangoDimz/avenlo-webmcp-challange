-- Commission-linked P/L for one Direct Client under one IB (deduped by orderId).
-- IB Dashboard detail uses OLDEST bind: ORDER BY createdAt ASC LIMIT 1 on ib_partner_bind.
-- Commission Report uses the ibPartnerId from the API URL instead.
--
-- Change @client_id and @ib_id.

SET @client_id = 635;
SET @ib_id = 71;

-- Optional: resolve oldest bind for IB Dashboard parity
-- SELECT parentId INTO @ib_id
-- FROM ib_partner_bind
-- WHERE childClientId = @client_id AND isClient = 1
-- ORDER BY createdAt ASC
-- LIMIT 1;

SELECT COALESCE(SUM(o.profit), 0) AS profit_loss
FROM (
  SELECT ico.orderId
  FROM ib_commission_order ico
  INNER JOIN orders o2 ON ico.orderId = o2.id
  INNER JOIN tradingAccountExternalAccounts taea ON taea.providerAccountId = o2.trading_login
  INNER JOIN tradingAccounts ta ON ta.id = taea.tradingAccountId
  WHERE ta.userId = @client_id
    AND ico.ibPartnerId = @ib_id
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
  INNER JOIN orders o2 ON ico.orderId = o2.id
  INNER JOIN tradingAccountExternalAccounts taea ON taea.providerAccountId = o2.trading_login
  INNER JOIN tradingAccounts ta ON ta.id = taea.tradingAccountId
  WHERE ta.userId = @client_id
    AND ico.ibPartnerId = @ib_id
    AND ico.status != 'cancelled'
    AND ico.orderId IS NOT NULL
  GROUP BY ico.orderId
) x
INNER JOIN orders o ON o.id = x.orderId
ORDER BY o.closeTime;
