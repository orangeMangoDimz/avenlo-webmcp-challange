<?php
/**
 * 客户端支付账户管理 API 路由
 */

require_once __DIR__ . '/../controllers/ClientPaymentAccountController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new ClientPaymentAccountController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 预览模式（X-Preview-Token）可跳过 JWT 认证；否则需要认证
if (!ClientAuthContext::allowPreviewRequest()) {
    AuthMiddleware::authenticate();
}

try {
    // 解析路径参数
    $pathParts = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
    $action = $pathParts[0] ?? null;
    $id = $pathParts[1] ?? null;

    if (empty($action)) {
        // GET /api/client/payment-accounts - 获取账户列表
        if ($method === 'GET') {
            $controller->index();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($action === 'create') {
        // POST /api/client/payment-accounts/create - 创建账户
        if ($method === 'POST') {
            $controller->create();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($action === 'modify' && is_numeric($id)) {
        // POST /api/client/payment-accounts/modify/{id} - 更新账户
        if ($method === 'POST') {
            $controller->update($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($action === 'remove' && is_numeric($id)) {
        // DELETE /api/client/payment-accounts/remove/{id} - 删除账户
        if ($method === 'DELETE') {
            $controller->delete($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif (is_numeric($action) && ($pathParts[1] ?? null) === 'set-default') {
        // POST /api/client/payment-accounts/{id}/set-default - 设置默认账户
        $accountId = $action;
        if ($method === 'POST') {
            $controller->setDefault($accountId);
        } else {
            Response::error('Method not allowed', 405);
        }
    } else {
        Response::error('Invalid route', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
