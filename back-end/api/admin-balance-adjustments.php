<?php
/**
 * Admin Balance Adjustments API 路由
 *   POST /api/admin/balance-adjustments   - admin 打钱 / 扣钱
 *   GET  /api/admin/balance-adjustments   - 查询 admin 手工调整历史
 */

require_once __DIR__ . '/../controllers/AdminBalanceAdjustmentController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new AdminBalanceAdjustmentController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

AuthMiddleware::authenticate();

try {
    $pathParts = explode('/', trim($path, '/'));
    $first  = $pathParts[0] ?? null;
    $second = $pathParts[1] ?? null;

    if (empty($path)) {
        if ($method === 'GET') {
            $controller->index();
        } elseif ($method === 'POST') {
            $controller->create();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first === 'wallet-balance' && $second !== null) {
        // GET /api/admin/balance-adjustments/wallet-balance/{userId}
        if ($method === 'GET') {
            $controller->walletBalance($second);
        } else {
            Response::error('Method not allowed', 405);
        }
    } else {
        Response::error('Route not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
