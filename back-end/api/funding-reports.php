<?php
/**
 * Funding Reports API 路由
 */

require_once __DIR__ . '/../controllers/FundingReportController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new FundingReportController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 所有路由都需要管理员认证
AuthMiddleware::authenticate();

try {
    // 路由映射
    if (empty($path)) {
        // /api/funding-reports
        Response::error('Please specify a report endpoint', 404);
    } elseif ($path === 'statistics') {
        // /api/funding-reports/statistics
        if ($method === 'GET') {
            $controller->statistics();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'transactions') {
        // /api/funding-reports/transactions
        if ($method === 'GET') {
            $controller->getAllTransactions();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'export') {
        // /api/funding-reports/export
        if ($method === 'POST') {
            $controller->exportReport();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'export-active') {
        if ($method === 'GET') {
            $controller->exportActive();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'export-status') {
        if ($method === 'GET') {
            $controller->exportStatus();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'export-cancel') {
        if ($method === 'POST') {
            $controller->exportCancel();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'export-download') {
        if ($method === 'GET') {
            $controller->exportDownload();
        } else {
            Response::error('Method not allowed', 405);
        }
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
