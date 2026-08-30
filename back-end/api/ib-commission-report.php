<?php
/**
 * IB佣金报表API路由
 * IB佣金报表查询和统计接口
 */

require_once __DIR__ . '/../controllers/IbCommissionReportController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$ibCommissionReportController = new IbCommissionReportController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

AuthMiddleware::authenticate();

try {
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    if (empty($path)) {
        if ($method === 'GET') {
            $ibCommissionReportController->index();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'statistics') {
        if ($method === 'GET') {
            $ibCommissionReportController->statistics();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'export-detail') {
        if ($method === 'POST') {
            $ibCommissionReportController->exportDetail();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'export-detail-active') {
        if ($method === 'GET') {
            $ibCommissionReportController->exportDetailActive();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'export-detail-status') {
        if ($method === 'GET') {
            $ibCommissionReportController->exportDetailStatus();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'export-detail-cancel') {
        if ($method === 'POST') {
            $ibCommissionReportController->exportDetailCancel();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'export-detail-download') {
        if ($method === 'GET') {
            $ibCommissionReportController->exportDetailDownload();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && is_numeric($id) && !$action) {
        if ($method === 'GET') {
            $ibCommissionReportController->show($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } else {
        Response::error('Route not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
