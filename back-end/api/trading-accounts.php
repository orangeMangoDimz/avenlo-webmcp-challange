<?php
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

/**
 * Trading Accounts API 路由
 */

require_once __DIR__ . '/../controllers/TradingAccountController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';

$controller = new TradingAccountController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 预览模式（X-Preview-Token）可跳过 JWT 认证；否则需要认证
if (!ClientAuthContext::allowPreviewRequest()) {
    AuthMiddleware::authenticate();
}

try {
    if (empty($path)) {
        if ($method === 'GET') {
            $controller->getAccounts();
        } else {
            Response::error('Route not found', 404);
        }
    } elseif ($path === 'create') {
        if ($method === 'POST') {
            $controller->createAccount();
        } else {
            Response::error('Route not found', 404);
        }
    } elseif ($path === 'summary') {
        if ($method === 'GET') {
            $controller->getAccountSummary();
        } else {
            Response::error('Route not found', 404);
        }
    } elseif ($path === 'history') {
        if ($method === 'GET') {
            $controller->getOrderHistory();
        } else {
            Response::error('Route not found', 404);
        }
    } elseif ($path === 'positions') {
        if ($method === 'GET') {
            $controller->getOpenPositions();
        } else {
            Response::error('Route not found', 404);
        }
    } elseif ($path === 'pending') {
        if ($method === 'GET') {
            $controller->getPendingOrders();
        } else {
            Response::error('Route not found', 404);
        }
    } elseif ($path === 'transaction-history') {
        if ($method === 'GET') {
            $controller->transactionHistory();
        } else {
            Response::error('Route not found', 404);
        }
    } elseif ($path === 'leverage') {
        if ($method === 'POST') {
            $controller->changeLeverage();
        } else {
            Response::error('Route not found', 404);
        }
    } elseif ($path === 'password') {
        if ($method === 'POST') {
            $controller->changeTradingPassword();
        } else {
            Response::error('Route not found', 404);
        }
    } elseif ($path === 'name') {
        if ($method === 'POST') {
            $controller->changeAccountName();
        } else {
            Response::error('Route not found', 404);
        }
    } else {
        Response::error('Route not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
