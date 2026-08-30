<?php
/**
 * Backfill trade commission (ib_commission_order) for closed orders that never
 * got a commission row (e.g. dual-IB self-trades that picked the wrong IB).
 *
 * Calls CommissionOrderService::createFromOrder (idempotent if a row already exists).
 *
 * Run order (after account→rule feature):
 *   1. Assign trading account (e.g. login 11491) to the Ultra commission rule in Admin
 *   2. Deploy CommissionOrderService assignment logic
 *   3. Dry-run (default) for the target login
 *   4. Finance / ops approval (realtime rules credit wallet)
 *   5. --apply
 *   6. Verify SQL / Admin Rebate Order / client Rebate Report Self row
 *
 * 用法：
 *   php script/backfill_commission_from_orders.php --trading-login=11491
 *   php script/backfill_commission_from_orders.php --trading-login=11491 --apply
 *   php script/backfill_commission_from_orders.php --trading-login=11491 --since=2026-07-01
 *   php script/backfill_commission_from_orders.php --trading-login=11491 --order-id=123 --apply
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../services/CommissionOrderService.php';

$opts = getopt('', ['apply', 'trading-login::', 'since::', 'order-id::']);
$dryRun = !isset($opts['apply']);
$tradingLogin = isset($opts['trading-login']) ? trim((string)$opts['trading-login']) : '';
$since = isset($opts['since']) ? (string)$opts['since'] : null;
$onlyOrderId = isset($opts['order-id']) ? (int)$opts['order-id'] : 0;

function out($msg) {
    echo $msg . PHP_EOL;
}

out('=== Backfill Commission from Orders ===');
out('Mode: ' . ($dryRun ? 'DRY-RUN (use --apply to write)' : 'APPLY'));
if ($tradingLogin !== '') {
    out('Trading login: ' . $tradingLogin);
}
if ($since !== null) {
    out('Since (order closetime FROM_UNIXTIME >=): ' . $since);
}
if ($onlyOrderId > 0) {
    out('Only order id: ' . $onlyOrderId);
}
out('');

if ($tradingLogin === '' && $onlyOrderId <= 0) {
    out('ERROR: require --trading-login=... and/or --order-id=...');
    exit(1);
}

$db = Database::getInstance();

$sql = "SELECT o.id, o.trading_login, o.symbol, o.volume, o.cmd, o.trading_status, o.closetime
        FROM orders o
        WHERE o.trading_status = 2
          AND o.closetime > 0
          AND o.cmd IN (0, 1)
          AND NOT EXISTS (
              SELECT 1 FROM ib_commission_order co WHERE co.orderId = o.id
          )";
$params = [];

if ($tradingLogin !== '') {
    $sql .= " AND o.trading_login = :login";
    $params['login'] = $tradingLogin;
}
if ($since !== null) {
    $sql .= " AND FROM_UNIXTIME(o.closetime) >= :since";
    $params['since'] = $since;
}
if ($onlyOrderId > 0) {
    $sql .= " AND o.id = :orderId";
    $params['orderId'] = $onlyOrderId;
}
$sql .= " ORDER BY o.id ASC";

$candidates = $db->fetchAll($sql, $params);
$total = count($candidates);
out("Found {$total} candidate order(s) missing commission.");
if ($total === 0) {
    out('Nothing to do.');
    exit(0);
}

$service = new CommissionOrderService();
$createdCount = 0;
$orderWithRecords = 0;
$skippedChain = 0;
$skippedOther = 0;
$errored = 0;
$wouldCreate = 0;

foreach ($candidates as $order) {
    $orderId = (int)$order['id'];
    $login = (string)($order['trading_login'] ?? '');
    $symbol = (string)($order['symbol'] ?? '');
    $volume = (float)($order['volume'] ?? 0);
    $lots = $volume / 100.0;
    $closeAt = !empty($order['closetime']) ? date('Y-m-d H:i:s', (int)$order['closetime']) : '';

    $line = sprintf(
        '[order #%d] login=%s symbol=%s volume=%.2f lots=%.4f closetime=%s',
        $orderId,
        $login,
        $symbol,
        $volume,
        $lots,
        $closeAt
    );

    if ($dryRun) {
        $chain = $service->getBindingChainForOrder($orderId);
        if (empty($chain)) {
            out($line . ' -> DRY: no IB chain, skip');
            $skippedChain++;
            continue;
        }
        $wouldCreate++;
        out($line . ' -> DRY: chain=[' . implode(',', $chain) . '] will attempt createFromOrder');
        continue;
    }

    try {
        $result = $service->createFromOrder($orderId);
        $created = (int)($result['created'] ?? 0);
        $message = (string)($result['message'] ?? '');

        if ($created > 0) {
            $createdCount += $created;
            $orderWithRecords++;
            out($line . " -> OK: created {$created} commission record(s)");
        } else {
            if (stripos($message, 'No IB binding chain') !== false) {
                $skippedChain++;
            } else {
                $skippedOther++;
            }
            out($line . " -> SKIP: {$message}");
        }
    } catch (Exception $e) {
        $errored++;
        out($line . ' -> ERROR: ' . $e->getMessage());
    }
}

out('');
out('=== Summary ===');
out('Total candidates            : ' . $total);
if ($dryRun) {
    out('Would attempt create        : ' . $wouldCreate);
    out('Skipped (no IB chain)       : ' . $skippedChain);
    out('(dry-run, nothing written)');
} else {
    out('Orders with new records     : ' . $orderWithRecords);
    out('Commission records created  : ' . $createdCount);
    out('Skipped (no IB chain)       : ' . $skippedChain);
    out('Skipped (other)             : ' . $skippedOther);
    out('Errored                     : ' . $errored);
}
