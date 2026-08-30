-- Payment request + callback trace for one client user.
-- Path: clientUsers.id → deposits/withdrawals.userId → paymentProcessor*Logs
-- Change @user_id. Optional filters: leave NULL to skip.

SET @user_id = 635;
SET @deposit_id = NULL;
SET @withdrawal_id = NULL;
SET @from_at = NULL;
SET @to_at = NULL;

SELECT
  d.id AS depositId,
  d.amount,
  d.currencyCode,
  d.status,
  d.transactionId,
  d.createdAt
FROM deposits d
WHERE d.userId = @user_id
  AND (@deposit_id IS NULL OR d.id = @deposit_id)
  AND (@from_at IS NULL OR d.createdAt >= @from_at)
  AND (@to_at IS NULL OR d.createdAt <= @to_at)
ORDER BY d.createdAt DESC;

SELECT
  w.id AS withdrawalId,
  w.amount,
  w.currencyCode,
  w.status,
  w.transactionId,
  w.createdAt
FROM withdrawals w
WHERE w.userId = @user_id
  AND (@withdrawal_id IS NULL OR w.id = @withdrawal_id)
  AND (@from_at IS NULL OR w.createdAt >= @from_at)
  AND (@to_at IS NULL OR w.createdAt <= @to_at)
ORDER BY w.createdAt DESC;

SELECT
  r.id AS requestLogId,
  r.provider,
  r.environment,
  r.transactionType,
  r.operation,
  r.deliveryMode,
  r.depositId,
  r.withdrawalId,
  r.localOrderId,
  r.providerOrderId,
  r.amount,
  r.currencyCode,
  r.requestStatus,
  r.responseHttpStatus,
  r.providerStatus,
  r.errorCode,
  r.errorMessage,
  r.startedAt,
  r.completedAt,
  r.durationMs,
  r.createdAt
FROM paymentProcessorRequestLogs r
LEFT JOIN deposits d ON d.id = r.depositId
LEFT JOIN withdrawals w ON w.id = r.withdrawalId
WHERE (d.userId = @user_id OR w.userId = @user_id)
  AND (@deposit_id IS NULL OR r.depositId = @deposit_id)
  AND (@withdrawal_id IS NULL OR r.withdrawalId = @withdrawal_id)
  AND (@from_at IS NULL OR r.createdAt >= @from_at)
  AND (@to_at IS NULL OR r.createdAt <= @to_at)
ORDER BY r.createdAt DESC;

SELECT
  c.id AS callbackLogId,
  c.requestLogId,
  c.correlationMethod,
  c.provider,
  c.environment,
  c.transactionType,
  c.orderId,
  c.callbackStatus,
  c.amount,
  c.ip,
  c.isValid,
  c.isProcessed,
  c.processResult,
  c.errorMessage,
  c.depositId,
  c.withdrawalId,
  c.createdAt,
  c.processedAt
FROM paymentProcessorCallbackLogs c
LEFT JOIN deposits d ON d.id = c.depositId
LEFT JOIN withdrawals w ON w.id = c.withdrawalId
LEFT JOIN paymentProcessorRequestLogs r ON r.id = c.requestLogId
LEFT JOIN deposits rd ON rd.id = r.depositId
LEFT JOIN withdrawals rw ON rw.id = r.withdrawalId
WHERE (
    d.userId = @user_id
    OR w.userId = @user_id
    OR rd.userId = @user_id
    OR rw.userId = @user_id
  )
  AND (@deposit_id IS NULL OR c.depositId = @deposit_id OR r.depositId = @deposit_id)
  AND (@withdrawal_id IS NULL OR c.withdrawalId = @withdrawal_id OR r.withdrawalId = @withdrawal_id)
  AND (@from_at IS NULL OR c.createdAt >= @from_at)
  AND (@to_at IS NULL OR c.createdAt <= @to_at)
ORDER BY c.createdAt DESC;

SELECT
  'request' AS eventKind,
  r.id AS logId,
  r.createdAt AS eventAt,
  r.transactionType,
  r.depositId,
  r.withdrawalId,
  r.provider,
  r.localOrderId AS orderRef,
  r.requestStatus AS status,
  r.errorMessage
FROM paymentProcessorRequestLogs r
LEFT JOIN deposits d ON d.id = r.depositId
LEFT JOIN withdrawals w ON w.id = r.withdrawalId
WHERE (d.userId = @user_id OR w.userId = @user_id)
  AND (@deposit_id IS NULL OR r.depositId = @deposit_id)
  AND (@withdrawal_id IS NULL OR r.withdrawalId = @withdrawal_id)
  AND (@from_at IS NULL OR r.createdAt >= @from_at)
  AND (@to_at IS NULL OR r.createdAt <= @to_at)

UNION ALL

SELECT
  'callback' AS eventKind,
  c.id AS logId,
  c.createdAt AS eventAt,
  c.transactionType,
  c.depositId,
  c.withdrawalId,
  c.provider,
  c.orderId AS orderRef,
  CONCAT_WS('/', c.callbackStatus, c.processResult) AS status,
  c.errorMessage
FROM paymentProcessorCallbackLogs c
LEFT JOIN deposits d ON d.id = c.depositId
LEFT JOIN withdrawals w ON w.id = c.withdrawalId
LEFT JOIN paymentProcessorRequestLogs r ON r.id = c.requestLogId
LEFT JOIN deposits rd ON rd.id = r.depositId
LEFT JOIN withdrawals rw ON rw.id = r.withdrawalId
WHERE (
    d.userId = @user_id
    OR w.userId = @user_id
    OR rd.userId = @user_id
    OR rw.userId = @user_id
  )
  AND (@deposit_id IS NULL OR c.depositId = @deposit_id OR r.depositId = @deposit_id)
  AND (@withdrawal_id IS NULL OR c.withdrawalId = @withdrawal_id OR r.withdrawalId = @withdrawal_id)
  AND (@from_at IS NULL OR c.createdAt >= @from_at)
  AND (@to_at IS NULL OR c.createdAt <= @to_at)

ORDER BY eventAt DESC;
