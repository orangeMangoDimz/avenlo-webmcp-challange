<?php
/**
 * 客户端IB Dashboard API 路由
 */

require_once __DIR__ . '/../controllers/ClientIbDashboardController.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new ClientIbDashboardController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 解析路径
$segments = array_filter(explode('/', $path));
$segments = array_values($segments);

try {
    // 路由映射
    $routes = [
        'GET /partners' => 'partners',
        'PUT /alias' => 'updateClientAlias',
        'GET /statistics' => 'statistics',
        'GET /referral-url' => 'referralUrl',
        'GET /commission-rates' => 'commissionRates',
        'GET /journey' => 'journey',
        'GET /network' => 'network',
        'GET /network-members' => 'networkMembers'
    ];

    $routeKey = "{$method} /{$path}";

    if (isset($routes[$routeKey])) {
        $methodName = $routes[$routeKey];
        $controller->$methodName();
        exit;
    }

    Response::error('Route not found', 404, [
        'requested' => $routeKey,
        'available_routes' => array_keys($routes)
    ]);

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
