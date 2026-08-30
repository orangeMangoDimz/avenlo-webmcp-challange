<?php
/**
 * KYC Webhook API 路由
 * 接收第三方 KYC 平台（Sumsub 等）的回调。
 *
 * 注意：这里**不走 AuthMiddleware**，靠 HMAC 签名校验来源可信性。
 */

require_once __DIR__ . '/../controllers/KycWebhookController.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new KycWebhookController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

try {
    $pathParts = explode('/', trim($path, '/'));
    $action = $pathParts[0] ?? null;

    if ($action === 'sumsub' && $method === 'POST') {
        // POST /api/kyc-webhook/sumsub
        $controller->handleSumsub();
    } else {
        Response::error('Route not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
