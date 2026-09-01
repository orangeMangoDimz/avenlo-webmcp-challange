-- Funding trend coverage for the local development dataset.
-- Adds completed withdrawals inside the dashboard's default date range.

INSERT INTO withdrawals (
    transactionId,
    userId,
    gatewaySettingId,
    amount,
    currencyCode,
    status,
    requestedAt,
    approvedAt,
    approvedBy,
    completedAt,
    countsToBalance,
    withdrawalReason,
    adminNotes,
    createdAt,
    updatedAt
)
SELECT
    'SCN-FLOW-WDL-20260828-AVA',
    2140,
    NULL,
    18000.00,
    'USD',
    'completed',
    '2026-08-28 14:00:00',
    '2026-08-28 14:12:00',
    1,
    '2026-08-28 14:20:00',
    1,
    'Scheduled withdrawal',
    'Completed funding trend seed.',
    '2026-08-28 14:00:00',
    '2026-08-28 14:20:00'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM withdrawals
    WHERE transactionId = 'SCN-FLOW-WDL-20260828-AVA'
);

INSERT INTO withdrawals (
    transactionId,
    userId,
    gatewaySettingId,
    amount,
    currencyCode,
    status,
    requestedAt,
    approvedAt,
    approvedBy,
    completedAt,
    countsToBalance,
    withdrawalReason,
    adminNotes,
    createdAt,
    updatedAt
)
SELECT
    'SCN-FLOW-WDL-20260829-NOAH',
    2141,
    NULL,
    12500.00,
    'USD',
    'completed',
    '2026-08-29 14:00:00',
    '2026-08-29 14:10:00',
    1,
    '2026-08-29 14:18:00',
    1,
    'Scheduled withdrawal',
    'Completed funding trend seed.',
    '2026-08-29 14:00:00',
    '2026-08-29 14:18:00'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM withdrawals
    WHERE transactionId = 'SCN-FLOW-WDL-20260829-NOAH'
);

INSERT INTO withdrawals (
    transactionId,
    userId,
    gatewaySettingId,
    amount,
    currencyCode,
    status,
    requestedAt,
    approvedAt,
    approvedBy,
    completedAt,
    countsToBalance,
    withdrawalReason,
    adminNotes,
    createdAt,
    updatedAt
)
SELECT
    'SCN-FLOW-WDL-20260830-SOFIA',
    2142,
    NULL,
    7500.00,
    'USD',
    'completed',
    '2026-08-30 14:00:00',
    '2026-08-30 14:08:00',
    1,
    '2026-08-30 14:16:00',
    1,
    'Scheduled withdrawal',
    'Completed funding trend seed.',
    '2026-08-30 14:00:00',
    '2026-08-30 14:16:00'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM withdrawals
    WHERE transactionId = 'SCN-FLOW-WDL-20260830-SOFIA'
);

-- Keep the profile visible when this seed is re-run after the rows already exist.
UPDATE withdrawals
SET amount = 75000.00,
    updatedAt = '2026-08-28 14:20:00'
WHERE transactionId = 'SCN-FLOW-WDL-20260828-AVA';

UPDATE withdrawals
SET amount = 50000.00,
    updatedAt = '2026-08-29 14:18:00'
WHERE transactionId = 'SCN-FLOW-WDL-20260829-NOAH';

UPDATE withdrawals
SET amount = 25000.00,
    updatedAt = '2026-08-30 14:16:00'
WHERE transactionId = 'SCN-FLOW-WDL-20260830-SOFIA';
