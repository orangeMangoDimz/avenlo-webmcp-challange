<?php
/**
 * Payment Methods API 路由
 */

require_once __DIR__ . '/../controllers/PaymentMethodController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new PaymentMethodController();
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
        // /api/payment-methods
        if ($method === 'GET') {
            $controller->index();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'crypto') {
        // /api/payment-methods/crypto
        if ($method === 'GET') {
            $controller->getCryptoMethods();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'fiat') {
        // /api/payment-methods/fiat
        if ($method === 'GET') {
            $controller->getFiatMethods();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && !$action) {
        // /api/payment-methods/{id}
        if ($method === 'GET') {
            $controller->show($id);
        } elseif ($method === 'PUT') {
            $controller->update($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
