<?php

$controllerPath = __DIR__ . '/../controllers/WebMcpSalesController.php';
$servicePath = __DIR__ . '/../services/WebMcpSalesService.php';

function assertSalesTrue(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertSalesThrows(callable $callback, string $message): void {
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return;
    }
    throw new RuntimeException($message);
}

assertSalesTrue(file_exists($controllerPath), 'Expected the sales WebMCP controller to exist.');
assertSalesTrue(file_exists($servicePath), 'Expected the sales WebMCP service to exist.');

require_once $controllerPath;
require_once $servicePath;
require_once __DIR__ . '/../controllers/SalesController.php';

assertSalesTrue(
    method_exists(SalesController::class, 'show'),
    'Expected the Sales Dashboard API to load an authorized target sales user.'
);

assertSalesTrue(
    WebMcpSalesController::routeHandlers() === [
        'admin/search-sales' => 'searchSales',
        'admin/get-sales-clients' => 'getSalesClients',
        'admin/get-sales-partners' => 'getSalesPartners',
        'admin/get-sales-performance' => 'getSalesPerformance',
        'admin/get-sales-daily-summary' => 'getSalesDailySummary',
        'admin/get-sales-leaderboard' => 'getSalesLeaderboard',
    ],
    'Expected the six approved sales routes.'
);

assertSalesTrue(
    WebMcpSalesController::normalizeSearchInput(['query' => ' Sarah ', 'limit' => '10']) === [
        'query' => 'Sarah',
        'page' => 1,
        'limit' => 10,
    ],
    'Expected sales search normalization.'
);
assertSalesThrows(
    static fn() => WebMcpSalesController::normalizeSearchInput(['query' => '']),
    'Expected an empty sales query to be rejected.'
);

assertSalesTrue(
    WebMcpSalesController::normalizeRelationshipInput([
        'salesId' => '42',
        'search' => ' client ',
        'page' => '2',
    ]) === [
        'salesId' => 42,
        'search' => 'client',
        'page' => 2,
        'limit' => 25,
    ],
    'Expected relationship pagination normalization.'
);
assertSalesThrows(
    static fn() => WebMcpSalesController::normalizeRelationshipInput(['salesId' => 0]),
    'Expected an invalid sales ID to be rejected.'
);

assertSalesTrue(
    WebMcpSalesController::normalizePerformanceInput([
        'salesId' => '42',
        'month' => '2026-08',
        'tzOffset' => '420',
    ]) === [
        'salesId' => 42,
        'month' => '2026-08',
        'tzOffset' => 420,
    ],
    'Expected monthly performance normalization.'
);
assertSalesThrows(
    static fn() => WebMcpSalesController::normalizePerformanceInput(['salesId' => 42, 'month' => '2026-13']),
    'Expected an invalid performance month to be rejected.'
);

assertSalesTrue(
    WebMcpSalesController::normalizeDailyInput([
        'salesId' => 42,
        'date' => '2026-08-31',
        'tzOffset' => '-300',
    ]) === [
        'salesId' => 42,
        'date' => '2026-08-31',
        'tzOffset' => -300,
    ],
    'Expected daily summary normalization.'
);

assertSalesTrue(
    WebMcpSalesController::normalizeLeaderboardInput([
        'metric' => 'newClients',
        'period' => 'month',
        'month' => '2026-08',
        'tzOffset' => '420',
    ]) === [
        'metric' => 'newClients',
        'period' => 'month',
        'month' => '2026-08',
        'tzOffset' => 420,
        'limit' => 10,
    ],
    'Expected leaderboard normalization.'
);
assertSalesThrows(
    static fn() => WebMcpSalesController::normalizeLeaderboardInput(['metric' => 'commission']),
    'Expected an unsupported leaderboard metric to be rejected.'
);
assertSalesThrows(
    static fn() => WebMcpSalesController::normalizeLeaderboardInput(['period' => 'day', 'month' => '2026-08']),
    'Expected month to be rejected for a daily leaderboard.'
);

$managementAccess = [
    'admin_user_id' => 7,
    'can_view_sales' => true,
    'can_view_all_sales' => true,
    'can_view_daily' => true,
];
$salesOnlyAccess = [
    'admin_user_id' => 42,
    'can_view_sales' => true,
    'can_view_all_sales' => false,
    'can_view_daily' => true,
];
assertSalesTrue(
    WebMcpSalesController::targetInScope($managementAccess, 42),
    'Expected sales management to view any sales target.'
);
assertSalesTrue(
    WebMcpSalesController::targetInScope($salesOnlyAccess, 42)
        && !WebMcpSalesController::targetInScope($salesOnlyAccess, 43),
    'Expected a sales-only caller to view only themselves.'
);
assertSalesTrue(
    WebMcpSalesController::leaderboardInScope($managementAccess)
        && !WebMcpSalesController::leaderboardInScope($salesOnlyAccess),
    'Expected only daily-report management users to view the team leaderboard.'
);

$ranked = WebMcpSalesService::rankRows([
    ['salesId' => 3, 'salesName' => 'Charlie', 'newClients' => 2],
    ['salesId' => 1, 'salesName' => 'Alice', 'newClients' => 5],
    ['salesId' => 2, 'salesName' => 'Bob', 'newClients' => 5],
], 'newClients', 10);
assertSalesTrue(
    array_column($ranked, 'rank') === [1, 1, 3],
    'Expected equal leaderboard values to share competition rank.'
);
assertSalesTrue(
    array_column($ranked, 'salesId') === [1, 2, 3],
    'Expected deterministic leaderboard ordering.'
);

$performance = WebMcpSalesService::buildPerformancePayload(
    ['id' => 42, 'name' => 'Sarah Tan'],
    '2026-08',
    '2026-08-01',
    '2026-08-31',
    420,
    ['deposits' => 900, 'depositCount' => 3, 'withdrawals' => 400, 'withdrawalCount' => 1, 'netDeposit' => 500, 'newLeads' => 4, 'newClients' => 2],
    1000.0
);
assertSalesTrue(
    $performance['target']['achievementRate'] === 50.0,
    'Expected performance to compare net deposit with the monthly target.'
);
assertSalesTrue(
    WebMcpSalesService::achievementRate(500.0, null) === null
        && WebMcpSalesService::achievementRate(500.0, 0.0) === null,
    'Expected missing or zero targets to have no achievement rate.'
);

$daily = WebMcpSalesService::buildDailyPayload(
    ['id' => 42, 'name' => 'Sarah Tan'],
    '2026-08-31',
    420,
    ['deposits' => 300, 'depositCount' => 2, 'withdrawals' => 100, 'withdrawalCount' => 1, 'netDeposit' => 200, 'newLeads' => 3, 'newClients' => 1],
    500.0,
    1000.0
);
assertSalesTrue(
    $daily['monthToDateNetDeposit'] === 500.0
        && $daily['target']['achievementRate'] === 50.0,
    'Expected daily summaries to include month-to-date KPI context.'
);

echo "webmcp sales validation tests passed\n";
