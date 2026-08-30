<?php
/**
 * 客户端预览会话 API（View as client）
 * GET /api/client/preview-session?token=xxx
 * 无需登录，仅凭 token 校验后返回 clientUserId 与过期时间
 * CORS 与 Content-Type 由 index.php 统一处理
 */

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../models/AdminPreviewToken.php';
require_once __DIR__ . '/../models/ClientUser.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    Response::error('Method not allowed', 405);
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if ($token === '') {
    Response::error('Token is required', 400);
}

$tokenModel = new AdminPreviewToken();
$session = $tokenModel->validateToken($token);

if (!$session) {
    Response::error('Invalid or expired preview token', 401);
}

$clientUserModel = new ClientUser();
$user = $clientUserModel->findById($session['clientUserId']);
if (!$user) {
    Response::error('User not found', 404);
}

// 只返回前端布局所需字段，不含敏感信息
$safeUser = [
    'id' => (int) $user['id'],
    'email' => $user['email'] ?? '',
    'firstName' => $user['firstName'] ?? '',
    'lastName' => $user['lastName'] ?? '',
    'phone' => $user['phone'] ?? '',
    'country' => $user['country'] ?? '',
    'status' => $user['status'] ?? '',
    'kycStatus' => $user['kycStatus'] ?? null,
];

Response::success([
    'clientUserId' => $session['clientUserId'],
    'expiresAt' => $session['expiresAt'],
    'readOnly' => true,
    'user' => $safeUser,
]);
