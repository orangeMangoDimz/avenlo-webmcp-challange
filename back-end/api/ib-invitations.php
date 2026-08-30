<?php
/**
 * IB Invitations API 路由
 * IB邀请管理接口
 */

require_once __DIR__ . '/../controllers/IbInvitationController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$ibInvitationController = new IbInvitationController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $segment1 = $pathParts[0] ?? null;
    $segment2 = $pathParts[1] ?? null;
    $segment3 = $pathParts[2] ?? null;

    // 路由映射
    if (empty($path)) {
        // /api/ib-invitations
        AuthMiddleware::authenticate(); // 需要认证
        if ($method === 'GET') {
            $ibInvitationController->index();
        } elseif ($method === 'POST') {
            $ibInvitationController->create();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($segment1 === 'verify' && $segment2) {
        // /api/ib-invitations/verify/{code} (GET - 管理员使用未加密的邀请码)
        AuthMiddleware::authenticate(); // 需要认证
        if ($method === 'GET') {
            $ibInvitationController->verifyInvitation($segment2);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($segment1 === 'verify' && !$segment2) {
        // /api/ib-invitations/verify (POST - 客户端验证加密的邀请码)
        // 这个方法内部会自己处理认证
        if ($method === 'POST') {
            $ibInvitationController->verifyEncryptedInvitation();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($segment1 === 'my-status') {
        // /api/ib-invitations/my-status (GET - 获取当前用户的IB状态)
        // 这个方法内部会自己处理认证（使用JWT::getTokenFromHeader）
        if ($method === 'GET') {
            $ibInvitationController->getMyStatus();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($segment1 && $segment2 === 'accept') {
        // /api/ib-invitations/{code}/accept
        if ($method === 'POST') {
            $ibInvitationController->acceptInvitation($segment1);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($segment1 && $segment2 === 'decline') {
        // /api/ib-invitations/{code}/decline
        if ($method === 'POST') {
            $ibInvitationController->declineInvitation($segment1);
        } else {
            Response::error('Method not allowed', 405);
        }
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
