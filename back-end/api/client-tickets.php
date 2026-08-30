<?php
/**
 * 客户端工单 API 路由
 */

require_once __DIR__ . '/../controllers/ClientTicketController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new ClientTicketController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

try {
    // 解析路径参数
    $pathParts = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
    $firstPart = $pathParts[0] ?? null;
    $secondPart = $pathParts[1] ?? null;

    if (empty($firstPart)) {
        // GET /api/client-tickets (后台查看工单列表)
        if ($method === 'GET') {
            // 后台查看，需要管理员认证
            AuthMiddleware::authenticate();
            $controller->index();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($firstPart === 'create') {
        // POST /api/client-tickets/create (客户端提交工单)
        if ($method === 'POST') {
            // 客户端提交，需要客户端认证
            $controller->create();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($firstPart === 'detail' && is_numeric($secondPart)) {
        // GET /api/client-tickets/detail/{id} (后台查看工单详情)
        AuthMiddleware::authenticate();
        if ($method === 'GET') {
            $controller->show($secondPart);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif (is_numeric($firstPart) && $secondPart === 'status') {
        // POST /api/client-tickets/{id}/status (后台标记工单已解决/未解决)
        AuthMiddleware::authenticate();
        if ($method === 'POST') {
            $controller->updateStatus($firstPart);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif (is_numeric($firstPart)) {
        // GET /api/client-tickets/{id} (保留向后兼容，但推荐使用 /detail/{id})
        AuthMiddleware::authenticate();
        if ($method === 'GET') {
            $controller->show($firstPart);
        } else {
            Response::error('Method not allowed', 405);
        }
    } else {
        Response::error('Endpoint not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
