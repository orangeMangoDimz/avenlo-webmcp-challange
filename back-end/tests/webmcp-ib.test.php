<?php

require_once __DIR__ . '/../controllers/WebMcpIbController.php';

function assertIbTrue(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertIbSame($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true) .
            "\nActual: " . var_export($actual, true)
        );
    }
}

function assertIbThrows(callable $callback, string $message): void {
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return;
    }

    throw new RuntimeException($message);
}

assertIbThrows(
    static fn() => WebMcpIbController::normalizeIbLookupInput([]),
    'Expected an empty IB lookup to be rejected.'
);
assertIbThrows(
    static fn() => WebMcpIbController::normalizeIbLookupInput(['id' => 12, 'code' => 'IB-12']),
    'Expected multiple IB identifiers to be rejected.'
);
assertIbThrows(
    static fn() => WebMcpIbController::normalizeIbLookupInput(['email' => 'not-an-email']),
    'Expected an invalid IB email to be rejected.'
);
assertIbSame(
    ['code' => 'IB-2026-042'],
    WebMcpIbController::normalizeIbLookupInput(['code' => ' IB-2026-042 ']),
    'Expected an IB code to be trimmed.'
);
assertIbSame(
    ['id' => 42],
    WebMcpIbController::normalizeIbLookupInput(['id' => '42']),
    'Expected an IB ID to be normalized.'
);

assertIbSame(
    ['id' => 42, 'maxDepth' => 1],
    WebMcpIbController::normalizeNetworkInput(['id' => '42', 'maxDepth' => '1'], 5),
    'Expected direct-child network input to normalize maxDepth=1.'
);
assertIbSame(
    ['code' => 'IB-42', 'maxDepth' => 5],
    WebMcpIbController::normalizeNetworkInput(['code' => 'IB-42'], 5),
    'Expected omitted maxDepth to use the configured limit.'
);
assertIbSame(
    ['code' => 'IB-42', 'maxDepth' => 5],
    WebMcpIbController::normalizeNetworkInput(['code' => 'IB-42', 'maxDepth' => 9], 5),
    'Expected requested maxDepth to be capped by the configured limit.'
);
assertIbThrows(
    static fn() => WebMcpIbController::normalizeNetworkInput(['id' => 42, 'maxDepth' => 0], 5),
    'Expected zero maxDepth to be rejected.'
);

assertIbSame(
    ['id' => 42, 'relationship' => 'all', 'page' => 2, 'limit' => 50],
    WebMcpIbController::normalizeClientsInput([
        'id' => 42,
        'relationship' => 'ALL',
        'page' => '2',
        'limit' => '50'
    ]),
    'Expected IB client relationship and pagination to normalize.'
);
assertIbThrows(
    static fn() => WebMcpIbController::normalizeClientsInput(['id' => 42]),
    'Expected relationship to be required.'
);
assertIbThrows(
    static fn() => WebMcpIbController::normalizeClientsInput(['id' => 42, 'relationship' => 'children']),
    'Expected an unsupported relationship to be rejected.'
);
assertIbThrows(
    static fn() => WebMcpIbController::normalizeClientsInput(['id' => 42, 'relationship' => 'direct', 'limit' => 51]),
    'Expected client pagination above 50 to be rejected.'
);

assertIbSame(
    ['email' => 'client@example.com'],
    WebMcpIbController::normalizeClientLookupInput(['email' => ' client@example.com ']),
    'Expected client email lookup to normalize.'
);
assertIbThrows(
    static fn() => WebMcpIbController::normalizeClientLookupInput(['code' => 'IB-42']),
    'Expected IB code to be unsupported for client upline lookup.'
);

$projectedIb = WebMcpIbController::projectIb([
    'id' => '42',
    'userId' => '7',
    'ibCode' => 'IB-2026-042',
    'ibName' => 'Jane Partner',
    'companyName' => 'Partner Ltd',
    'email' => 'partner@example.com',
    'country' => 'ID',
    'status' => 'approved',
    'ibType' => 'Master IB',
    'tierLevelId' => '2',
    'tierLevel' => '2',
    'tierLevelName' => 'Gold',
    'registrationDate' => '2026-01-15 10:30:00',
    'paymentSettings' => ['secret' => true],
    'assignedRules' => [['id' => 9]],
    'contactPhone' => '+620000'
]);
assertIbSame(42, $projectedIb['id'], 'Expected a numeric IB ID.');
assertIbSame('Jane Partner', $projectedIb['name'], 'Expected the resolved IB name.');
assertIbSame('IB-2026-042', $projectedIb['code'], 'Expected the public IB code.');
assertIbTrue(!isset($projectedIb['paymentSettings']), 'Expected payment settings to be excluded.');
assertIbTrue(!isset($projectedIb['assignedRules']), 'Expected assigned rules to be excluded.');
assertIbTrue(!isset($projectedIb['contactPhone']), 'Expected phone data to be excluded.');

$tree = WebMcpIbController::buildHierarchyFromRows(42, [
    ['parentId' => 42, 'id' => 43, 'ibCode' => 'IB-43', 'ibName' => 'Child One'],
    ['parentId' => 43, 'id' => 44, 'ibCode' => 'IB-44', 'ibName' => 'Grandchild'],
    ['parentId' => 44, 'id' => 42, 'ibCode' => 'IB-42', 'ibName' => 'Cycle']
], 2);
assertIbSame(1, count($tree), 'Expected one direct child in the hierarchy.');
assertIbSame(43, $tree[0]['id'], 'Expected the direct child first.');
assertIbSame(1, $tree[0]['depth'], 'Expected direct children at depth one.');
assertIbSame(44, $tree[0]['children'][0]['id'], 'Expected the grandchild at depth two.');
assertIbSame([], $tree[0]['children'][0]['children'], 'Expected cycle/depth traversal to stop safely.');

assertIbSame(
    [
        'directIbs' => 2,
        'totalDescendantIbs' => 3,
        'directClients' => 5,
        'totalNetworkClients' => 12,
        'totalNetworkMembers' => 15
    ],
    WebMcpIbController::calculateNetworkTotals(
        [42 => 0, 43 => 1, 44 => 1, 45 => 2],
        5,
        12
    ),
    'Expected network totals to exclude the root IB and include all visible clients.'
);

assertIbSame(
    [
        'admin/get-ib-partner' => 'getPartner',
        'admin/get-ib-network' => 'getNetwork',
        'admin/get-ib-network-stats' => 'getNetworkStats',
        'admin/get-ib-clients' => 'getClients',
        'admin/get-client-ib-upline' => 'getClientUpline'
    ],
    WebMcpIbController::routeHandlers(),
    'Expected exactly the five IB read API routes.'
);

echo "webmcp IB validation tests passed\n";
