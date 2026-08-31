<?php

$controllerPath = __DIR__ . '/../controllers/WebMcpAdminLogController.php';
$servicePath = __DIR__ . '/../services/WebMcpAdminLogService.php';

function assertAdminLogTrue(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertAdminLogSame($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

function assertAdminLogThrows(callable $callback, string $message): void {
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return;
    }
    throw new RuntimeException($message);
}

assertAdminLogTrue(file_exists($controllerPath), 'Expected the Admin Log WebMCP controller to exist.');
assertAdminLogTrue(file_exists($servicePath), 'Expected the Admin Log WebMCP service to exist.');

require_once $controllerPath;
require_once $servicePath;
require_once __DIR__ . '/../services/OperationLogReportExportService.php';

assertAdminLogSame(
    [
        'admin/search-admin-users' => 'searchAdminUsers',
        'admin/get-admin-user' => 'getAdminUser',
        'admin/get-role-permissions' => 'getRolePermissions',
        'admin/find-roles-by-permission' => 'findRolesByPermission',
        'admin/check-admin-user-permission' => 'checkAdminUserPermission',
        'admin/search-operation-logs' => 'searchOperationLogs',
        'admin/get-operation-log' => 'getOperationLog',
        'admin/export-operation-logs' => 'exportOperationLogs',
    ],
    WebMcpAdminLogController::routeHandlers(),
    'Expected exactly the eight Admin Log API routes.'
);

assertAdminLogSame(
    [
        'query' => 'Sarah',
        'status' => 'active',
        'roleId' => 4,
        'page' => 2,
        'limit' => 50,
    ],
    WebMcpAdminLogController::normalizeAdminSearchInput([
        'query' => ' Sarah ',
        'status' => 'ACTIVE',
        'roleId' => '4',
        'page' => '2',
        'limit' => '50',
    ]),
    'Expected administrator search filters to normalize.'
);
assertAdminLogThrows(
    static fn() => WebMcpAdminLogController::normalizeAdminSearchInput([]),
    'Expected an administrator search without filters to be rejected.'
);
assertAdminLogThrows(
    static fn() => WebMcpAdminLogController::normalizeAdminSearchInput(['status' => 'deleted']),
    'Expected an unsupported administrator status to be rejected.'
);

assertAdminLogSame(
    ['adminUserId' => 42],
    WebMcpAdminLogController::normalizeAdminUserInput(['adminUserId' => '42']),
    'Expected the administrator ID to normalize.'
);
assertAdminLogSame(
    ['roleName' => 'Operations'],
    WebMcpAdminLogController::normalizeRoleInput(['roleName' => ' Operations ']),
    'Expected a role-name lookup to normalize.'
);
assertAdminLogThrows(
    static fn() => WebMcpAdminLogController::normalizeRoleInput(['roleId' => 4, 'roleName' => 'Operations']),
    'Expected multiple role selectors to be rejected.'
);

assertAdminLogSame(
    ['permissionKey' => 'page_withdraw_approve', 'includeInactive' => false],
    WebMcpAdminLogController::normalizePermissionInput([
        'permissionKey' => ' page_withdraw_approve ',
    ], true),
    'Expected permission lookups to use an exact normalized key.'
);
foreach (['application_logs.view', 'perm-ib-management', 'ib_application_edit_rules', 'group_report'] as $permissionKey) {
    assertAdminLogSame(
        ['permissionKey' => $permissionKey, 'includeInactive' => false],
        WebMcpAdminLogController::normalizePermissionInput(['permissionKey' => $permissionKey], true),
        'Expected every safely formatted exact permission key to normalize.'
    );
}
assertAdminLogSame(
    ['adminUserId' => 42, 'permissionKey' => 'page_withdraw_approve'],
    WebMcpAdminLogController::normalizeAdminPermissionInput([
        'adminUserId' => '42',
        'permissionKey' => ' page_withdraw_approve ',
    ]),
    'Expected effective-permission input to normalize.'
);

$operationSearch = WebMcpAdminLogController::normalizeOperationLogSearchInput([
    'operatorId' => '42',
    'module' => ' role_management ',
    'operationType' => 'edit',
    'targetType' => 'admin_role',
    'targetId' => '7',
    'startDate' => '2026-08-01',
    'endDate' => '2026-08-31',
    'query' => ' permission ',
    'page' => '2',
    'limit' => '50',
]);
assertAdminLogSame(
    [
        'operatorId' => 42,
        'module' => 'role_management',
        'operationType' => 'edit',
        'targetType' => 'admin_role',
        'targetId' => 7,
        'startDate' => '2026-08-01',
        'endDate' => '2026-08-31',
        'query' => 'permission',
        'page' => 2,
        'limit' => 50,
    ],
    $operationSearch,
    'Expected operation-log filters to normalize without changing their meaning.'
);
assertAdminLogThrows(
    static fn() => WebMcpAdminLogController::normalizeOperationLogSearchInput([]),
    'Expected an unfiltered operation-log search to be rejected.'
);
assertAdminLogThrows(
    static fn() => WebMcpAdminLogController::normalizeOperationLogSearchInput(['targetId' => 7]),
    'Expected targetId without targetType to be rejected.'
);
assertAdminLogThrows(
    static fn() => WebMcpAdminLogController::normalizeOperationLogSearchInput([
        'module' => 'role_management',
        'startDate' => '2026-09-01',
        'endDate' => '2026-08-01',
    ]),
    'Expected a reversed audit date range to be rejected.'
);

assertAdminLogSame(
    ['modelKey' => 'log_system', 'subModuleKey' => 'role_management', 'targetType' => 'admin_role'],
    WebMcpAdminLogService::resolveModule('role_management'),
    'Expected role_management to resolve through the operation-log page registry.'
);
assertAdminLogSame(
    'client',
    WebMcpAdminLogService::publicTargetType('log_transaction', 'withdrawals'),
    'Expected client_user targets to be exposed as client targets.'
);
assertAdminLogThrows(
    static fn() => WebMcpAdminLogController::normalizeOperationLogSearchInput([
        'targetType' => 'ib_partner',
    ]),
    'Expected target types without registered log scopes to be rejected.'
);
assertAdminLogSame(
    [
        'modelKey' => 'all',
        'subModule' => 'all',
        'operationType' => 'all',
        'startDate' => '',
        'endDate' => '',
        'operatorId' => 42,
        'targetId' => null,
        'query' => '',
        'modelKeys' => ['log_client', 'log_system'],
    ],
    WebMcpAdminLogService::scopeFiltersToVisibleModules(
        WebMcpAdminLogService::operationLogFilters(['operatorId' => 42]),
        ['log_client', 'log_system']
    ),
    'Expected cross-module filters to be constrained to visible model keys.'
);
assertAdminLogThrows(
    static fn() => WebMcpAdminLogService::scopeFiltersToVisibleModules(
        WebMcpAdminLogService::operationLogFilters(['module' => 'pm_products']),
        ['log_client', 'log_system']
    ),
    'Expected an explicitly hidden module to be rejected.'
);

$projected = WebMcpAdminLogService::projectAdminUser([
    'id' => '42',
    'username' => 'sarah',
    'email' => 'sarah@example.com',
    'fullName' => 'Sarah Tan',
    'status' => 'active',
    'isLocked' => '0',
    'roleId' => '4',
    'roleKey' => 'ops',
    'roleName' => 'Operations Role',
    'roleDisplayName' => 'Operations',
    'departmentName' => 'Operations',
    'positionName' => 'Manager',
    'createdAt' => '2026-01-01 00:00:00',
    'lastLoginAt' => '2026-08-31 10:00:00',
    'passwordHash' => 'secret',
    'twoFactorSecret' => 'secret',
    'rememberToken' => 'secret',
]);
assertAdminLogSame(42, $projected['id'], 'Expected a numeric administrator ID.');
assertAdminLogSame('Operations', $projected['role']['name'], 'Expected the administrator role.');
assertAdminLogSame('ops', $projected['role']['key'], 'Expected the real administrator role key.');
assertAdminLogTrue(!isset($projected['passwordHash']), 'Expected password hashes to be excluded.');
assertAdminLogTrue(!isset($projected['twoFactorSecret']), 'Expected 2FA secrets to be excluded.');
assertAdminLogTrue(!isset($projected['rememberToken']), 'Expected remember tokens to be excluded.');

$exportQuery = [
    'filters' => WebMcpAdminLogService::operationLogFilters([
        'operatorId' => 42,
        'module' => 'role_management',
    ]),
    'language' => 'en',
];
$fingerprint = OperationLogReportExportService::inputFingerprint(7, $exportQuery);
assertAdminLogSame(
    $fingerprint,
    OperationLogReportExportService::inputFingerprint(7, $exportQuery),
    'Expected the same administrator and normalized filters to have a stable export fingerprint.'
);
assertAdminLogTrue(
    $fingerprint !== OperationLogReportExportService::inputFingerprint(8, $exportQuery),
    'Expected export fingerprints to be bound to the requesting administrator.'
);
assertAdminLogTrue(
    OperationLogReportExportService::canReuseCompletedExport([
        'status' => 'done',
        'inputFingerprint' => $fingerprint,
        'completedAt' => date('Y-m-d H:i:s'),
    ], $fingerprint),
    'Expected a recent matching completed export to be reusable.'
);
assertAdminLogTrue(
    !OperationLogReportExportService::canReuseCompletedExport([
        'status' => 'running',
        'inputFingerprint' => $fingerprint,
        'completedAt' => date('Y-m-d H:i:s'),
    ], $fingerprint),
    'Expected a running export not to be treated as a completed reusable file.'
);
assertAdminLogSame(
    "'=SUM(A1:A2)",
    OperationLogReportExportService::escapeCsvCell('=SUM(A1:A2)'),
    'Expected formula-like export cells to be neutralized.'
);
assertAdminLogSame(
    '2026-08-31',
    OperationLogReportExportService::escapeCsvCell('2026-08-31'),
    'Expected ordinary export cells to remain unchanged.'
);

echo "webmcp Admin Log validation tests passed\n";
