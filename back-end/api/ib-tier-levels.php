<?php
/**
 * IB Tier Levels API 路由
 * IB层级模板管理接口
 */

require_once __DIR__ . '/../controllers/IbTierLevelController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$ibTierController = new IbTierLevelController();
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
        // /api/ib-tier-levels
        if ($method === 'GET') {
            $ibTierController->index();
        } elseif ($method === 'POST') {
            $ibTierController->create();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'active') {
        // /api/ib-tier-levels/active
        if ($method === 'GET') {
            $ibTierController->getActiveTiers();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && !$action) {
        // /api/ib-tier-levels/{id}
        if ($method === 'GET') {
            $ibTierController->show($id);
        } elseif ($method === 'PUT') {
            $ibTierController->update($id);
        } elseif ($method === 'DELETE') {
            $ibTierController->delete($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
