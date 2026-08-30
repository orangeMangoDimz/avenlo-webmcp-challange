<?php
/**
 * MT5 Connection Test Script
 *
 * 用法：
 *   php script/test_mt5_connection.php
 *
 * 说明：
 * - 读取 config/app.php 中 integrations.mt5 配置
 * - 使用 Manager WebAPI 账号测试 connect + ping
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../utils/Mt5ApiClient.php';

function maskValue($value, $visiblePrefix = 2, $visibleSuffix = 2)
{
    $value = (string)$value;
    $len = strlen($value);
    if ($len <= ($visiblePrefix + $visibleSuffix)) {
        return str_repeat('*', max(0, $len));
    }
    return substr($value, 0, $visiblePrefix) . str_repeat('*', $len - $visiblePrefix - $visibleSuffix) . substr($value, -$visibleSuffix);
}

echo "=== MT5 Connection Test ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $appConfig = require __DIR__ . '/../config/app.php';
    $mt5Config = $appConfig['integrations']['mt5'] ?? [];

    if (empty($mt5Config)) {
        throw new Exception('Missing integrations.mt5 config in config/app.php');
    }

    echo "Config Summary:\n";
    echo "- host: " . (($mt5Config['host'] ?? '') !== '' ? $mt5Config['host'] : '(empty)') . "\n";
    echo "- port: " . (($mt5Config['port'] ?? '') !== '' ? $mt5Config['port'] : '(empty)') . "\n";
    echo "- timeout: " . (($mt5Config['timeout'] ?? '') !== '' ? $mt5Config['timeout'] : '(empty)') . "\n";
    echo "- manager_login: " . (($mt5Config['manager_login'] ?? '') !== '' ? maskValue($mt5Config['manager_login']) : '(empty)') . "\n";
    echo "- manager_password: " . (($mt5Config['manager_password'] ?? '') !== '' ? maskValue($mt5Config['manager_password']) : '(empty)') . "\n";
    echo "- is_crypt: " . (isset($mt5Config['is_crypt']) ? ((bool)$mt5Config['is_crypt'] ? 'true' : 'false') : '(default)') . "\n\n";

    $client = new Mt5ApiClient($mt5Config);

    echo "Testing connect + ping...\n";
    $result = $client->testConnection();

    if (!empty($result['success'])) {
        echo "[OK] " . ($result['message'] ?? 'MT5 connection OK') . "\n";
        exit(0);
    }

    echo "[FAIL] " . ($result['message'] ?? 'Unknown error') . "\n";
    exit(1);
} catch (Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
