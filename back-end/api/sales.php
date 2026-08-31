<?php
/**
 * Sales 列表与统计 API
 * GET /api/sales              - 列表（含分页与顶部统计）
 * GET /api/sales/stats        - 仅顶部统计
 * GET /api/sales/{id}/monthly-performance - 某 Sales 单月业绩（Sales Dashboard 顶部卡片）
 * GET /api/sales/special-roles - 特殊角色 ID（Sales Manager / Sales，供 Role Management 用）
 * POST /api/sales/sales-referral-visit - 客户端推荐链接访问记录（公开，不鉴权，走网站配置 CORS）
 */

require_once __DIR__ . '/../controllers/SalesController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

// 先解析 path，公开接口必须在鉴权之前处理
$path = $_GET['subpath'] ?? $_GET['path'] ?? '';
$pathParts = array_values(array_filter(explode('/', trim($path, '/'))));
$firstPart = $pathParts[0] ?? '';
$secondPart = $pathParts[1] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// 公开接口：客户端中间页访问推荐链接时调用，不鉴权（必须先于 AuthMiddleware，否则会 401）
if ($method === 'POST' && $firstPart === 'sales-referral-visit') {
    $controller = new SalesController();
    $controller->salesReferralVisit();
    exit;
}

AuthMiddleware::authenticate();

try {
    if ($method === 'POST' && is_numeric($firstPart) && ($secondPart === 'referral-suffix')) {
        $controller = new SalesController();
        $controller->updateReferralSuffix((int)$firstPart);
        exit;
    }
    if ($method !== 'GET') {
        Response::error('Method not allowed', 405);
    }
    if ($firstPart === 'special-roles') {
        $appConfig = require __DIR__ . '/../config/app.php';
        $special = $appConfig['special_roles'] ?? [];
        Response::success([
            'salesManagerRoleId' => (int)($special['sales_manager_role_id'] ?? 0),
            'salesRoleId' => (int)($special['sales_role_id'] ?? 0),
        ]);
        exit;
    }

    $controller = new SalesController();
    if ($firstPart === 'me') {
        $controller->me();
    } elseif ($firstPart === 'stats') {
        $controller->stats();
    } elseif (is_numeric($firstPart) && $secondPart === 'bound-clients') {
        $controller->boundClients($firstPart);
    } elseif (is_numeric($firstPart) && $secondPart === 'bound-ibs') {
        $controller->boundIbs($firstPart);
    } elseif (is_numeric($firstPart) && $secondPart === 'monthly-performance') {
        $controller->monthlyPerformance($firstPart);
    } elseif (is_numeric($firstPart) && $secondPart === '') {
        $controller->show($firstPart);
    } elseif ($firstPart === '' || $firstPart === null) {
        $controller->index();
    } else {
        Response::error('Not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
