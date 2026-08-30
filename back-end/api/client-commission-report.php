<?php
/**
 * 客户端佣金报表 API 路由
 */

require_once __DIR__ . '/../controllers/ClientCommissionReportController.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new ClientCommissionReportController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

try {
    // 路由映射
    $routes = [
        'GET /statistics' => 'statistics',
        'GET /list' => 'list',
        'GET /detail' => 'detail',
        'GET /export-active' => 'exportActive',
        'POST /export' => 'export',
        'GET /export-status' => 'exportStatus',
        'POST /export-cancel' => 'exportCancel',
        'GET /export-download' => 'exportDownload',
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
