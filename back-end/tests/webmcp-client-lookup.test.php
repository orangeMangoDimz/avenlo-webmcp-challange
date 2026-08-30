<?php

require_once __DIR__ . '/../controllers/WebMcpClientController.php';

function assertTrue(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertThrows(callable $callback, string $message): void {
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return;
    }

    throw new RuntimeException($message);
}

assertThrows(
    static fn() => WebMcpClientController::normalizeLookupInput([]),
    'Expected an empty lookup to be rejected.'
);
assertThrows(
    static fn() => WebMcpClientController::normalizeLookupInput(['email' => 'a@example.com', 'id' => 42]),
    'Expected multiple identifiers to be rejected.'
);
assertThrows(
    static fn() => WebMcpClientController::normalizeLookupInput(['id' => 0]),
    'Expected an invalid ID to be rejected.'
);
assertThrows(
    static fn() => WebMcpClientController::normalizeLookupInput(['email' => str_repeat('a', 250) . '@example.com']),
    'Expected an overlong email to be rejected.'
);

assertTrue(
    WebMcpClientController::normalizeLookupInput(['email' => ' a@example.com ']) === ['email' => 'a@example.com'],
    'Expected email whitespace to be trimmed.'
);
assertTrue(
    WebMcpClientController::normalizeLookupInput(['id' => '42']) === ['id' => 42],
    'Expected string IDs to be normalized to integers.'
);
assertTrue(
    WebMcpClientController::normalizeLookupInput(['code' => ' IB-2026-042 ']) === ['code' => 'IB-2026-042'],
    'Expected IB code whitespace to be trimmed.'
);

assertThrows(
    static fn() => WebMcpClientController::normalizeSearchInput([]),
    'Expected an empty client search to be rejected.'
);
assertThrows(
    static fn() => WebMcpClientController::normalizeSearchInput(['neverLoggedIn' => 'yes']),
    'Expected a non-boolean neverLoggedIn filter to be rejected.'
);
assertThrows(
    static fn() => WebMcpClientController::normalizeSearchInput(['tag' => 'VIP', 'limit' => 51]),
    'Expected a search limit above 50 to be rejected.'
);
assertTrue(
    WebMcpClientController::normalizeSearchInput([
        'country' => ' Indonesia ',
        'tag' => ' VIP ',
        'neverLoggedIn' => true,
        'limit' => '25'
    ]) === [
        'country' => 'Indonesia',
        'tag' => 'VIP',
        'neverLoggedIn' => true,
        'page' => 1,
        'limit' => 25
    ],
    'Expected client search filters and pagination to be normalized.'
);
assertTrue(
    WebMcpClientController::normalizeSearchInput(['neverLoggedIn' => 'true'])['neverLoggedIn'] === true,
    'Expected HTTP query-string booleans to be normalized.'
);

assertThrows(
    static fn() => WebMcpClientController::normalizeTransactionInput(['id' => 42, 'type' => 'unknown']),
    'Expected an invalid transaction type to be rejected.'
);
assertThrows(
    static fn() => WebMcpClientController::normalizeTransactionInput(['id' => 42, 'limit' => 51]),
    'Expected a transaction limit above 50 to be rejected.'
);
assertTrue(
    WebMcpClientController::normalizeTransactionInput([
        'id' => '42',
        'type' => 'withdrawals',
        'limit' => '10'
    ]) === [
        'id' => 42,
        'type' => 'withdrawal',
        'page' => 1,
        'limit' => 10
    ],
    'Expected transaction lookup and pagination to be normalized.'
);

assertThrows(
    static fn() => WebMcpClientController::normalizeExportClientInput(['clientIds' => []]),
    'Expected an empty client selection to be rejected.'
);
assertThrows(
    static fn() => WebMcpClientController::normalizeExportClientInput(['clientIds' => [42], 'registeredFrom' => 'bad-date']),
    'Expected an invalid client export date to be rejected.'
);
assertTrue(
    WebMcpClientController::normalizeExportClientInput([
        'clientIds' => ['42', 43],
        'country' => ' Indonesia ',
        'registeredFrom' => '2026-01-01',
        'registeredTo' => '2026-01-31'
    ]) === [
        'clientIds' => [42, 43],
        'country' => 'Indonesia',
        'registeredFrom' => '2026-01-01',
        'registeredTo' => '2026-01-31'
    ],
    'Expected client export filters to be normalized.'
);
assertThrows(
    static fn() => WebMcpClientController::normalizeExportTransactionInput([
        'clientIds' => [42],
        'dateFrom' => '2026-02-01',
        'dateTo' => '2026-01-01'
    ]),
    'Expected a reversed transaction date range to be rejected.'
);
assertTrue(
    WebMcpClientController::normalizeExportTransactionInput([
        'clientIds' => ['42'],
        'dateFrom' => '2026-08-01',
        'dateTo' => '2026-08-30',
        'type' => 'withdrawals',
        'status' => ' completed '
    ]) === [
        'clientIds' => [42],
        'dateFrom' => '2026-08-01',
        'dateTo' => '2026-08-30',
        'type' => 'withdrawal',
        'status' => 'completed'
    ],
    'Expected transaction export filters to be normalized.'
);

echo "webmcp client lookup validation tests passed\n";
