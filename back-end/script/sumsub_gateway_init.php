<?php
/**
 * Sumsub gateway bootstrap initializer
 *
 * 往 externalKycGateways 表插入一条 Sumsub 占位记录，
 * 让后台"第三方 KYC 设置"页面能直接进入 Detail 填 appToken / secretKey 等凭证。
 *
 * 行为：
 *   - 已存在则跳过，不会覆盖任何已配置的字段（避免抹掉手填的 secret）
 *   - 不存在则插入：默认 isEnabled=0、environment=sandbox、baseUrl 指向 Sumsub 官方域名
 *
 * 用法：
 *   php script/sumsub_gateway_init.php
 *
 * 前置依赖：先执行 database/all_crm_update_260511.sql 建表
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../utils/Database.php';

const SUMSUB_PROVIDER_KEY = 'sumsub';
const SUMSUB_DISPLAY_NAME = 'Sumsub';
const SUMSUB_DEFAULT_BASE_URL = 'https://api.sumsub.com';

function out($message) {
    echo $message . PHP_EOL;
}

$db = Database::getInstance();

try {
    $db->beginTransaction();

    out('=== Sumsub Gateway Init ===');

    $existing = $db->fetchOne(
        "SELECT * FROM externalKycGateways WHERE provider = :provider AND deletedAt IS NULL LIMIT 1",
        ['provider' => SUMSUB_PROVIDER_KEY]
    );

    if ($existing) {
        out("[SKIP] externalKycGateways already exists: id={$existing['id']}, isEnabled={$existing['isEnabled']}");
        out('       Existing config is preserved. Manage it in the admin UI.');
        $db->commit();
        exit(0);
    }

    $gatewayId = $db->insert('externalKycGateways', [
        'provider' => SUMSUB_PROVIDER_KEY,
        'displayName' => SUMSUB_DISPLAY_NAME,
        'isEnabled' => 0,
        'environment' => 'sandbox',
        'baseUrl' => SUMSUB_DEFAULT_BASE_URL,
        'appToken' => null,
        'secretKey' => null,
        'webhookSecret' => null,
        'iframeBaseUrl' => null,
        'returnUrl' => null,
        'configData' => null,
        'updatedBy' => null,
    ]);

    out("[OK] externalKycGateways inserted: id={$gatewayId}");

    $db->commit();

    out('');
    out('[DONE] Sumsub bootstrap finished successfully.');
    out('Gateway ID: ' . $gatewayId);
    out('Next: open admin UI → External KYC Settings → Detail → fill App Token / Secret Key → enable.');
    exit(0);
} catch (Throwable $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->rollback();
    }

    out('[ERROR] ' . $e->getMessage());
    exit(1);
}
