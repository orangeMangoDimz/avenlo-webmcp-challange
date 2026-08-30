<?php
/**
 * 邮件链接 API（仅通过 index.php?t=加密令牌 进入，不暴露接口路径）
 * 供邮件内点击链接使用，无需登录。后续可扩展验证邮件、重置密码等类型。
 */

require_once __DIR__ . '/../controllers/IbInvitationController.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';
require_once __DIR__ . '/../utils/MailLinkToken.php';

$method = $_SERVER['REQUEST_METHOD'];
$token = isset($_GET['t']) ? trim((string) $_GET['t']) : '';

try {
    if ($token === '') {
        Response::error('Invalid link', 400);
    }

    $payload = MailLinkToken::decode($token);
    if ($payload === null) {
        Response::error('Invalid or expired link', 400);
    }

    $type = $payload['type'] ?? '';

    // IB 邀请文档查看（邮件内「查看文档」链接）
    if ($type === MailLinkToken::TYPE_IB_DOCUMENT) {
        if ($method !== 'GET') {
            Response::error('Method not allowed', 405);
        }
        $documentId = isset($payload['documentId']) ? (int) $payload['documentId'] : 0;
        $code = isset($payload['code']) ? (string) $payload['code'] : '';
        if ($documentId <= 0 || $code === '') {
            Response::error('Invalid link', 400);
        }
        $controller = new IbInvitationController();
        $controller->viewDocument($documentId, $code);
        exit;
    }

    // 后续可在此添加更多 type，例如：verify_email、reset_password、unsubscribe
    Response::error('Unknown link type', 400);
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
