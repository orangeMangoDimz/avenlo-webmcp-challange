<?php
/**
 * 客户端预览 Token 回收 API（View as client）
 * POST /api/client/preview-revoke
 * Body: { "token": "xxx" }
 * 关闭预览页时调用，回收 token
 * CORS 与 Content-Type 由 index.php 统一处理
 */

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../models/AdminPreviewToken.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$token = isset($input['token']) ? trim($input['token']) : '';

if ($token === '') {
    Response::error('Token is required', 400);
}

$tokenModel = new AdminPreviewToken();
$revoked = $tokenModel->revokeToken($token);

Response::success([
    'revoked' => $revoked,
], $revoked ? 'Token revoked' : 'Token not found or already revoked');
