<?php
/**
 * IB 推荐链接访问记录（公开，不鉴权）
 * POST /api/ib-referral-visit  Body: { "ref": "suffix" }
 * 客户端访问 /#/ib-referral/:suffix 时调用，按 IP + 后缀 24 小时内只计 1 次点击。
 */

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/ClientIp.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../models/IbReferralSettings.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$ref = isset($data['ref']) ? trim((string)$data['ref']) : '';
if ($ref === '') {
    Response::success(['recorded' => false, 'message' => 'Missing ref']);
    exit;
}

$settingsModel = new IbReferralSettings();
$ibPartnerId = $settingsModel->getIbPartnerIdBySuffix($ref);
if ($ibPartnerId <= 0) {
    Response::success(['recorded' => false, 'message' => 'Invalid ref']);
    exit;
}

$settingsModel->ensureSettingsRow($ibPartnerId, $ref);
$ip = ClientIp::getClientIp();
$db = Database::getInstance();
$since = date('Y-m-d H:i:s', strtotime('-24 hours'));
$existing = $db->fetchOne(
    'SELECT id FROM ib_referral_visit_log WHERE ip = :ip AND referralSuffix = :suffix AND createdAt > :since LIMIT 1',
    ['ip' => $ip, 'suffix' => $ref, 'since' => $since]
);
if ($existing !== false && $existing !== null) {
    Response::success(['recorded' => false, 'message' => 'Already counted recently']);
    exit;
}

$db->query(
    'INSERT INTO ib_referral_visit_log (ip, referralSuffix, createdAt) VALUES (:ip, :suffix, NOW())',
    ['ip' => $ip, 'suffix' => $ref]
);
$settingsModel->incrementUrlClicks($ibPartnerId);
Response::success(['recorded' => true]);
