<?php
/**
 * Admin Trading Account Adjustments API 路由
 *   POST /api/admin/trading-account-adjustments/credit  - 后台给交易账户打/扣 credits（直接打平台）
 *   POST /api/admin/trading-account-adjustments/balance - 后台给交易账户打钱/扣钱（走 deposit/withdrawal 审批流）
 */

require_once __DIR__ . '/../controllers/AdminTradingAccountAdjustmentController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

$controller = new AdminTradingAccountAdjustmentController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

AuthMiddleware::authenticate();

try {
    if ($path === 'credit') {
        if ($method === 'POST') {
            $controller->credit();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'balance') {
        if ($method === 'POST') {
            $controller->balance();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'reset-password') {
        if ($method === 'POST') {
            $controller->resetPassword();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'group') {
        if ($method === 'POST') {
            $controller->changeGroup();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'leverage') {
        if ($method === 'POST') {
            $controller->changeLeverage();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'options') {
        if ($method === 'GET') {
            $controller->options();
        } else {
            Response::error('Method not allowed', 405);
        }
    } else {
        Response::error('Route not found', 404);
    }
} catch (Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
