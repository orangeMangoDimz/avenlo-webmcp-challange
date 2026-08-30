<?php
/**
 * Account Verification API 路由
 * 账户验证API - 处理客户端和管理端的账户验证请求
 */

require_once __DIR__ . '/../controllers/AccountVerificationController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new AccountVerificationController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 预览模式（X-Preview-Token）可跳过 JWT 认证；否则需要认证
if (!ClientAuthContext::allowPreviewRequest()) {
    AuthMiddleware::authenticate();
}

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    // 路由映射
    if (empty($path)) {
        // /api/account-verification
        if ($method === 'GET') {
            // 管理员：获取验证列表
            $controller->getVerificationList();
        } else {
            Response::error('Method not allowed', 405);
        }
    }
    elseif ($path === 'verified-accounts') {
        // /api/account-verification/verified-accounts (客户端)
        if ($method === 'GET') {
            $controller->getVerifiedAccounts();
        } else {
            Response::error('Method not allowed', 405);
        }
    }
    elseif ($path === 'submit') {
        // /api/account-verification/submit (客户端)
        if ($method === 'POST') {
            $controller->submitVerification();
        } else {
            Response::error('Method not allowed', 405);
        }
    }
    elseif ($path === 'statistics') {
        // /api/account-verification/statistics (管理员)
        if ($method === 'GET') {
            $controller->getStatistics();
        } else {
            Response::error('Method not allowed', 405);
        }
    }
    elseif ($id && !$action) {
        // /api/account-verification/{id}
        if ($method === 'GET') {
            $controller->getVerificationDetails($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    }
    elseif ($id && $action === 'review') {
        // /api/account-verification/{id}/review (管理员)
        if ($method === 'POST') {
            $controller->reviewVerification($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    }
    elseif ($pathParts[0] === 'files' && !empty($pathParts[1])) {
        // /api/account-verification/files/{fileId} (管理员)
        if ($method === 'GET') {
            $controller->downloadFile($pathParts[1]);
        } else {
            Response::error('Method not allowed', 405);
        }
    }
    else {
        Response::error('Endpoint not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
