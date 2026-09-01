<?php

$controllerPath = __DIR__ . '/../controllers/WebMcpReportController.php';
$servicePath = __DIR__ . '/../services/WebMcpReportService.php';
$viewsPath = __DIR__ . '/../database/all_views_260613.sql';

function assertReportTrue(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertReportThrows(callable $callback, string $message): void {
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return;
    }
    throw new RuntimeException($message);
}

assertReportTrue(file_exists($controllerPath), 'Expected the Report WebMCP controller to exist.');
assertReportTrue(file_exists($servicePath), 'Expected the Report WebMCP service to exist.');
assertReportTrue(file_exists($viewsPath), 'Expected the canonical view definition to exist.');

$viewsSql = file_get_contents($viewsPath);
assertReportTrue(
    substr_count($viewsSql, 'AS currency') >= 3,
    'Expected vAllTransactions to expose currency for every transaction type.'
);

require_once $controllerPath;
require_once $servicePath;

assertReportTrue(
    WebMcpReportController::routeHandlers() === [
        'admin/get-funding-summary' => ['handler' => 'getFundingSummary', 'method' => 'GET'],
        'admin/search-funding-transactions' => ['handler' => 'searchFundingTransactions', 'method' => 'GET'],
        'admin/get-daily-sales-performance' => ['handler' => 'getDailySalesPerformance', 'method' => 'GET'],
        'admin/search-ib-partners' => ['handler' => 'searchIbPartners', 'method' => 'GET'],
        'admin/get-ib-statement' => ['handler' => 'getIbStatement', 'method' => 'GET'],
        'admin/list-custom-reports' => ['handler' => 'listCustomReports', 'method' => 'GET'],
        'admin/get-custom-report-results' => ['handler' => 'getCustomReportResults', 'method' => 'GET'],
        'admin/export-funding-report' => ['handler' => 'exportFundingReport', 'method' => 'POST'],
        'admin/export-ib-statement' => ['handler' => 'exportIbStatement', 'method' => 'POST'],
        'admin/report-export-status' => ['handler' => 'reportExportStatus', 'method' => 'GET'],
        'admin/report-export-download' => ['handler' => 'reportExportDownload', 'method' => 'GET'],
    ],
    'Expected the approved Report WebMCP route contract.'
);

assertReportTrue(
    WebMcpReportController::normalizeFundingPeriod([
        'startDate' => '2026-08-01',
        'endDate' => '2026-08-31',
    ]) === ['startDate' => '2026-08-01', 'endDate' => '2026-08-31'],
    'Expected an explicit funding period to be preserved.'
);
assertReportThrows(
    static fn() => WebMcpReportController::normalizeFundingPeriod(['startDate' => '2026-08-31']),
    'Expected incomplete funding periods to be rejected.'
);
assertReportThrows(
    static fn() => WebMcpReportController::normalizeFundingPeriod([
        'startDate' => '2026-09-01',
        'endDate' => '2026-08-31',
    ]),
    'Expected reversed funding periods to be rejected.'
);

$fundingSearch = WebMcpReportController::normalizeFundingSearch([
    'startDate' => '2026-08-24',
    'endDate' => '2026-08-31',
    'type' => 'withdrawal',
    'query' => ' WD-123 ',
    'minAmount' => '100',
    'page' => '2',
]);
assertReportTrue(
    $fundingSearch === [
        'startDate' => '2026-08-24',
        'endDate' => '2026-08-31',
        'type' => 'withdrawal',
        'query' => 'WD-123',
        'minAmount' => 100.0,
        'page' => 2,
        'limit' => 25,
    ],
    'Expected funding filters and pagination to be normalized.'
);
assertReportThrows(
    static fn() => WebMcpReportController::normalizeFundingSearch(['type' => 'credit']),
    'Expected non-report transaction types to be rejected.'
);
assertReportTrue(
    WebMcpReportController::normalizeFundingExport([
        'startDate' => '2026-08-01',
        'endDate' => '2026-08-31',
        'type' => 'deposit',
    ]) === [
        'startDate' => '2026-08-01',
        'endDate' => '2026-08-31',
        'type' => 'deposit',
    ],
    'Expected funding exports to be normalized server-side.'
);
assertReportThrows(
    static fn() => WebMcpReportController::normalizeFundingExport(['query' => 'client']),
    'Expected unsupported funding export filters to be rejected server-side.'
);

assertReportTrue(
    WebMcpReportController::normalizeDailyPerformance([
        'date' => '2026-08-31',
        'tzOffset' => '420',
        'rankBy' => 'deposits',
        'limit' => '10',
    ]) === [
        'date' => '2026-08-31',
        'tzOffset' => 420,
        'rankBy' => 'deposits',
        'limit' => 10,
    ],
    'Expected daily sales inputs to be normalized.'
);

assertReportTrue(
    WebMcpReportController::normalizeIbStatement([
        'ibCode' => ' IB-001 ',
        'startDate' => '2026-08-01',
        'endDate' => '2026-08-31',
    ]) === [
        'ibCode' => 'IB-001',
        'startDate' => '2026-08-01',
        'endDate' => '2026-08-31',
        'page' => 1,
        'limit' => 25,
    ],
    'Expected an IB code statement lookup to be normalized.'
);
assertReportThrows(
    static fn() => WebMcpReportController::normalizeIbStatement([
        'ibPartnerId' => 7,
        'ibCode' => 'IB-007',
        'startDate' => '2026-08-01',
        'endDate' => '2026-08-31',
    ]),
    'Expected exactly one IB selector.'
);
assertReportTrue(
    WebMcpReportController::normalizeIbExport([
        'ibCode' => ' IB-001 ',
        'startDate' => '2026-08-01',
        'endDate' => '2026-08-31',
        'format' => 'excel',
    ]) === [
        'ibCode' => 'IB-001',
        'startDate' => '2026-08-01',
        'endDate' => '2026-08-31',
        'format' => 'excel',
    ],
    'Expected IB exports to accept an exact code with server-side validation.'
);

$ranked = WebMcpReportService::rankDailyRows([
    ['salesId' => 3, 'salesName' => 'Charlie', 'deposits' => 10],
    ['salesId' => 1, 'salesName' => 'Alice', 'deposits' => 50],
    ['salesId' => 2, 'salesName' => 'Bob', 'deposits' => 50],
], 'deposits', 10);
assertReportTrue(
    array_column($ranked, 'rank') === [1, 1, 3]
        && array_column($ranked, 'salesId') === [1, 2, 3],
    'Expected deterministic competition ranking.'
);

echo "webmcp report validation tests passed\n";
