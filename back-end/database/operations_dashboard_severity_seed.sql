-- Operations dashboard filter coverage for the local development dataset.
-- These rows are idempotent and stay inside the dashboard's default
-- 2026-08-26 through 2026-09-01 date range.

-- One sensitive role change produces a Critical administrator-activity item.
INSERT INTO adminOperationLogs (
    operatorId,
    modelKey,
    moduleNameZh,
    moduleNameEn,
    subModuleKey,
    subModuleNameZh,
    subModuleNameEn,
    operationTypeKey,
    targetId,
    detailZh,
    detailEn,
    ipAddress,
    operatedAt,
    createdAt
)
SELECT
    1,
    'log_system',
    '系统管理',
    'System administration',
    'role_management',
    '角色管理',
    'Role management',
    'edit',
    1,
    'Updated a role permission for dashboard severity coverage.',
    'Updated a role permission for dashboard severity coverage.',
    '127.0.0.1',
    '2026-08-30 09:15:00',
    '2026-08-30 09:15:00'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM adminOperationLogs
    WHERE modelKey = 'log_system'
      AND subModuleKey = 'role_management'
      AND operationTypeKey = 'edit'
      AND detailEn = 'Updated a role permission for dashboard severity coverage.'
      AND operatedAt = '2026-08-30 09:15:00'
);

-- Pending KYC submissions between 24 and 48 hours old produce Medium items.
INSERT INTO clientKycSubmissions (
    clientId,
    templateId,
    isThirdParty,
    submissionStatus,
    submittedAt,
    createdAt,
    updatedAt
)
SELECT 2143, 1, 0, 'pending', '2026-08-31 00:00:00', '2026-08-31 00:00:00', '2026-08-31 00:00:00'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM clientKycSubmissions
    WHERE clientId = 2143 AND submittedAt = '2026-08-31 00:00:00'
);

INSERT INTO clientKycSubmissions (
    clientId,
    templateId,
    isThirdParty,
    submissionStatus,
    submittedAt,
    createdAt,
    updatedAt
)
SELECT 2144, 1, 0, 'pending', '2026-08-31 08:00:00', '2026-08-31 08:00:00', '2026-08-31 08:00:00'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM clientKycSubmissions
    WHERE clientId = 2144 AND submittedAt = '2026-08-31 08:00:00'
);

INSERT INTO clientKycSubmissions (
    clientId,
    templateId,
    isThirdParty,
    submissionStatus,
    submittedAt,
    createdAt,
    updatedAt
)
SELECT 2139, 1, 0, 'pending', '2026-08-30 23:00:00', '2026-08-30 23:00:00', '2026-08-30 23:00:00'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM clientKycSubmissions
    WHERE clientId = 2139 AND submittedAt = '2026-08-30 23:00:00'
);
