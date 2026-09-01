<?php

$controllerPath = __DIR__ . '/../controllers/WebMcpOperationsController.php';
$servicePath = __DIR__ . '/../services/WebMcpOperationsService.php';

function assertOperationsTrue(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertOperationsSame($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

function assertOperationsThrows(callable $callback, string $message): void {
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return;
    }
    throw new RuntimeException($message);
}

assertOperationsTrue(file_exists($controllerPath), 'Expected the operations dashboard controller to exist.');
assertOperationsTrue(file_exists($servicePath), 'Expected the operations dashboard service to exist.');

require_once $controllerPath;
require_once $servicePath;

assertOperationsSame(
    ['admin/get-operations-overview' => ['handler' => 'getOverview', 'method' => 'GET']],
    WebMcpOperationsController::routeHandlers(),
    'Expected the operations overview route to be registered.'
);

assertOperationsSame(
    ['startDate' => '2026-08-26', 'endDate' => '2026-09-01', 'tzOffset' => 420],
    WebMcpOperationsController::normalizeOverviewInput([
        'startDate' => '2026-08-26',
        'endDate' => '2026-09-01',
        'tzOffset' => '420',
    ]),
    'Expected overview filters to be normalized.'
);
assertOperationsSame(
    [
        'startDate' => '2026-08-26',
        'endDate' => '2026-09-01',
        'tzOffset' => 420,
        'severity' => 'medium',
        'exceptionType' => 'kyc',
    ],
    WebMcpOperationsController::normalizeOverviewInput([
        'startDate' => '2026-08-26',
        'endDate' => '2026-09-01',
        'tzOffset' => 420,
        'severity' => 'MEDIUM',
        'exceptionType' => 'KYC',
    ]),
    'Expected queue filters to be normalized.'
);
assertOperationsThrows(
    static fn() => WebMcpOperationsController::normalizeOverviewInput([
        'startDate' => '2026-08-26',
        'endDate' => '2026-09-01',
        'tzOffset' => 420,
        'severity' => 'urgent',
    ]),
    'Expected unsupported queue severities to be rejected.'
);

assertOperationsThrows(
    static fn() => WebMcpOperationsController::normalizeOverviewInput([
        'startDate' => '2026-08-26',
        'endDate' => '2026-09-01',
        'tzOffset' => 420,
        'unexpected' => true,
    ]),
    'Expected unknown overview filters to be rejected.'
);
assertOperationsThrows(
    static fn() => WebMcpOperationsController::normalizeOverviewInput([
        'startDate' => '2026-09-02',
        'endDate' => '2026-09-01',
        'tzOffset' => 420,
    ]),
    'Expected reversed date ranges to be rejected.'
);
assertOperationsThrows(
    static fn() => WebMcpOperationsController::normalizeOverviewInput([
        'startDate' => '2026-05-01',
        'endDate' => '2026-09-01',
        'tzOffset' => 420,
    ]),
    'Expected ranges over 90 days to be rejected.'
);
assertOperationsThrows(
    static fn() => WebMcpOperationsController::normalizeOverviewInput([
        'startDate' => '2026-08-26',
        'endDate' => '2026-09-01',
        'tzOffset' => 841,
    ]),
    'Expected timezone offsets above UTC+14 to be rejected.'
);

$policy = WebMcpOperationsService::policy([
    'high_value_amount' => 10000,
    'kyc_overdue_hours' => 24,
    'audit_mutation_burst_count' => 5,
    'audit_mutation_window_minutes' => 15,
    'queue_limit' => 3,
    'stale_after_seconds' => 300,
]);
assertOperationsSame(10000.0, $policy['highValueAmount'], 'Expected a numeric high-value policy.');
assertOperationsSame(24, $policy['kycOverdueHours'], 'Expected a KYC SLA policy.');
assertOperationsSame(3, $policy['queueLimit'], 'Expected the configured queue cap.');
assertOperationsSame(1, $policy['queueReservePerType'], 'Expected one reserved queue slot per exception type.');

$ranked = WebMcpOperationsService::rankAndLimitAttentionItems([
    ['id' => 'medium-new', 'severity' => 'medium', 'ageHours' => 2],
    ['id' => 'critical', 'severity' => 'critical', 'ageHours' => 1],
    ['id' => 'high-new', 'severity' => 'high', 'ageHours' => 2],
    ['id' => 'high-old', 'severity' => 'high', 'ageHours' => 12],
], 3);
assertOperationsSame(
    ['critical', 'high-old', 'high-new'],
    array_column($ranked, 'id'),
    'Expected severity then age ordering and queue limiting.'
);

$covered = WebMcpOperationsService::rankAndLimitAttentionItems([
    ['id' => 'client-high', 'kind' => 'client', 'severity' => 'high', 'ageHours' => 12],
    ['id' => 'audit-critical', 'kind' => 'audit', 'severity' => 'critical', 'ageHours' => 1],
    ['id' => 'kyc-medium', 'kind' => 'kyc', 'severity' => 'medium', 'ageHours' => 3],
    ['id' => 'transaction-high', 'kind' => 'transaction', 'severity' => 'high', 'ageHours' => 8],
], 4, 1);
assertOperationsSame(
    ['audit', 'client', 'transaction', 'kyc'],
    array_values(array_unique(array_column($covered, 'kind'))),
    'Expected the queue window to reserve one slot per exception type.'
);

assertOperationsSame(
    ['medium-kyc'],
    array_column(
        WebMcpOperationsService::filterAttentionItems([
            ['id' => 'critical-audit', 'severity' => 'critical', 'kind' => 'audit'],
            ['id' => 'medium-kyc', 'severity' => 'medium', 'kind' => 'kyc'],
            ['id' => 'high-client', 'severity' => 'high', 'kind' => 'client'],
        ],
        'medium',
        'kyc'
    ),
        'id'
    ),
    'Expected attention filters to match both severity and exception type.'
);

assertOperationsSame(
    [
        ['currency' => 'EUR', 'deposits' => 500.0, 'withdrawals' => 125.0, 'netFlow' => 375.0],
        ['currency' => 'USD', 'deposits' => 1000.0, 'withdrawals' => 300.0, 'netFlow' => 700.0],
    ],
    WebMcpOperationsService::projectCurrencyTotals([
        ['currency' => 'USD', 'transactionType' => 'deposit', 'totalAmount' => '1000'],
        ['currency' => 'EUR', 'transactionType' => 'deposit', 'totalAmount' => '500'],
        ['currency' => 'USD', 'transactionType' => 'withdrawal', 'totalAmount' => '300'],
        ['currency' => 'EUR', 'transactionType' => 'withdrawal', 'totalAmount' => '125'],
    ]),
    'Expected monetary totals to remain separated by currency.'
);

assertOperationsSame(
    'Northstar Partners 1',
    WebMcpOperationsService::ibDisplayName([
        'id' => 1,
        'ibCode' => 'DEMO-IB-1',
        'companyName' => ' Northstar Demo Partners 1 ',
        'adminAlias' => 'Partner 1',
        'clientAlias' => 'Demo Partner 1',
    ]),
    'Expected IB leaders to prefer the registered company name.'
);
assertOperationsSame(
    'IB #1',
    WebMcpOperationsService::ibDisplayCode(['id' => 1, 'ibCode' => 'DEMO-IB-1']),
    'Expected seeded demo IB codes to stay out of dashboard labels.'
);
assertOperationsSame(
    'Fallback partner',
    WebMcpOperationsService::ibDisplayName([
        'id' => 2,
        'companyName' => '',
        'adminAlias' => 'Fallback partner',
    ]),
    'Expected IB leader names to fall back safely when company name is missing.'
);

echo "webmcp operations validation tests passed\n";
