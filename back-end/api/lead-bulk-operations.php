<?php
/**
 * Lead批量操作日志API路由
 */

require_once __DIR__ . '/../controllers/LeadBulkOperationController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new LeadBulkOperationController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 所有路由都需要认证
AuthMiddleware::authenticate();

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    // 路由映射
    if (empty($path)) {
        // /api/lead-bulk-operations
        if ($method === 'GET') {
            $controller->index();
        }
    } elseif ($path === 'stats') {
        // /api/lead-bulk-operations/stats
        if ($method === 'GET') {
            $controller->getStats();
        }
    } elseif ($path === 'recent') {
        // /api/lead-bulk-operations/recent
        if ($method === 'GET') {
            $controller->getRecent();
        }
    } elseif ($path === 'trends') {
        // /api/lead-bulk-operations/trends
        if ($method === 'GET') {
            $controller->getTrends();
        }
    } elseif ($path === 'admin-rankings') {
        // /api/lead-bulk-operations/admin-rankings
        if ($method === 'GET') {
            $controller->getAdminRankings();
        }
    } elseif ($path === 'export') {
        // /api/lead-bulk-operations/export
        if ($method === 'POST') {
            $controller->export();
        }
    } elseif ($id === 'admin' && $action) {
        // /api/lead-bulk-operations/admin/{adminId}
        if ($method === 'GET') {
            $controller->getOperationsByAdmin($action);
        }
    } elseif ($id === 'lead' && $action) {
        // /api/lead-bulk-operations/lead/{leadId}
        if ($method === 'GET') {
            $controller->getOperationsByLead($action);
        }
    } elseif ($id && !$action) {
        // /api/lead-bulk-operations/{id}
        if ($method === 'GET') {
            $controller->show($id);
        } elseif ($method === 'DELETE') {
            $controller->delete($id);
        }
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
