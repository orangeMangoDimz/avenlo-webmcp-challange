<?php
/**
 * Lead标签管理API路由
 */

require_once __DIR__ . '/../controllers/LeadTagController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new LeadTagController();
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
        // /api/lead-tags
        if ($method === 'GET') {
            $controller->index();
        } elseif ($method === 'POST') {
            $controller->create();
        }
    } elseif ($path === 'bulk-assign') {
        // /api/lead-tags/bulk-assign
        if ($method === 'POST') {
            $controller->bulkAssign();
        }
    } elseif ($path === 'bulk-remove') {
        // /api/lead-tags/bulk-remove
        if ($method === 'POST') {
            $controller->bulkRemove();
        }
    } elseif ($id && !$action) {
        // /api/lead-tags/{id}
        if ($method === 'GET') {
            $controller->show($id);
        } elseif ($method === 'PUT') {
            $controller->update($id);
        } elseif ($method === 'DELETE') {
            $controller->delete($id);
        }
    } elseif ($id && $action === 'leads') {
        // /api/lead-tags/{id}/leads
        if ($method === 'GET') {
            $controller->getLeadsByTag($id);
        }
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
