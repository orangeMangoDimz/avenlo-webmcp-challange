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

echo "webmcp client lookup validation tests passed\n";
