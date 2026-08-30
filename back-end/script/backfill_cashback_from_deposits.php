<?php
/**
 * Backfill cashback (ib_commission_order) for deposits that were auto-approved
 * via payment gateway callback before the callback started triggering
 * CommissionOrderService::createFromDeposit.
 *
 * 扫描所有 status='completed' 的 deposits，对尚未生成 cash_back_rebate
 * 佣金单的 deposit 补生成。幂等：已有 cashback 记录的 deposit 会跳过。
 *
 * 用法：
 *   php script/backfill_cashback_from_deposits.php              # dry-run（默认）
 *   php script/backfill_cashback_from_deposits.php --apply      # 实际写入
 *   php script/backfill_cashback_from_deposits.php --apply --since=2025-01-01
 *   php script/backfill_cashback_from_deposits.php --apply --deposit-id=123
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../services/CommissionOrderService.php';

// ---------- 解析命令行参数 ----------
$opts = getopt('', ['apply', 'since::', 'deposit-id::']);
$dryRun = !isset($opts['apply']);
$since = isset($opts['since']) ? (string)$opts['since'] : null;
$onlyDepositId = isset($opts['deposit-id']) ? (int)$opts['deposit-id'] : 0;

function out($msg) {
    echo $msg . PHP_EOL;
}

out('=== Backfill Cashback from Deposits ===');
out('Mode: ' . ($dryRun ? 'DRY-RUN (use --apply to write)' : 'APPLY'));
if ($since !== null) {
    out('Since (deposit createdAt >=): ' . $since);
}
if ($onlyDepositId > 0) {
    out('Only deposit id: ' . $onlyDepositId);
}
out('');

$db = Database::getInstance();

// ---------- 查找候选 deposits ----------
// 条件：status='completed' 且 不存在 ib_commission_order 带 ruleType='cash_back_rebate' 关联这个 depositId
$sql = "SELECT d.id, d.userId, d.amount, d.createdAt, d.approvedAt, d.status
        FROM deposits d
        WHERE d.status = 'completed'
          AND NOT EXISTS (
              SELECT 1 FROM ib_commission_order co
              WHERE co.depositId = d.id
                AND co.ruleType = 'cash_back_rebate'
          )";
$params = [];

if ($since !== null) {
    $sql .= " AND d.createdAt >= :since";
    $params['since'] = $since;
}
if ($onlyDepositId > 0) {
    $sql .= " AND d.id = :depositId";
    $params['depositId'] = $onlyDepositId;
}
$sql .= " ORDER BY d.id ASC";

$candidates = $db->fetchAll($sql, $params);
$total = count($candidates);
out("Found {$total} candidate deposit(s) missing cashback commission.");
if ($total === 0) {
    out('Nothing to do.');
    exit(0);
}

// ---------- 逐笔处理 ----------
$service = new CommissionOrderService();
$createdCount = 0;
$skippedChain = 0;
$skippedNoRule = 0;
$skippedAmount = 0;
$errored = 0;
$depositWithRecords = 0;

foreach ($candidates as $deposit) {
    $depositId = (int)$deposit['id'];
    $userId = (int)$deposit['userId'];
    $amount = (float)$deposit['amount'];

    $line = sprintf(
        '[deposit #%d] userId=%d amount=%.2f createdAt=%s',
        $depositId,
        $userId,
        $amount,
        $deposit['createdAt'] ?? ''
    );

    if ($dryRun) {
        // dry-run: 跑一遍 chain + 规则预估，不写库
        $chain = $service->getBindingChainForClient($userId);
        if (empty($chain)) {
            out($line . ' -> DRY: no IB chain, skip');
            $skippedChain++;
            continue;
        }
        out($line . ' -> DRY: chain=[' . implode(',', $chain) . '] will attempt createFromDeposit');
        continue;
    }

    try {
        $result = $service->createFromDeposit($depositId);
        $created = (int)($result['created'] ?? 0);
        $message = (string)($result['message'] ?? '');

        if ($created > 0) {
            $createdCount += $created;
            $depositWithRecords++;
            out($line . " -> OK: created {$created} commission record(s)");
        } else {
            // 按 message 归类
            if (stripos($message, 'No IB binding chain') !== false) {
                $skippedChain++;
            } elseif (stripos($message, 'Deposit amount invalid') !== false) {
                $skippedAmount++;
            } else {
                $skippedNoRule++;
            }
            out($line . " -> SKIP: {$message}");
        }
    } catch (Exception $e) {
        $errored++;
        out($line . ' -> ERROR: ' . $e->getMessage());
    }
}

// ---------- 汇总 ----------
out('');
out('=== Summary ===');
out('Total candidates            : ' . $total);
if ($dryRun) {
    out('(dry-run, nothing written)');
} else {
    out('Deposits with new records   : ' . $depositWithRecords);
    out('Commission records created  : ' . $createdCount);
    out('Skipped (no IB chain)       : ' . $skippedChain);
    out('Skipped (no matching rule)  : ' . $skippedNoRule);
    out('Skipped (bad amount)        : ' . $skippedAmount);
    out('Errored                     : ' . $errored);
}
