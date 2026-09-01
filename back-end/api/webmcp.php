<?php

require_once __DIR__ . '/../controllers/WebMcpClientController.php';
require_once __DIR__ . '/../controllers/WebMcpKycController.php';
require_once __DIR__ . '/../controllers/WebMcpIbController.php';
require_once __DIR__ . '/../controllers/WebMcpAdminLogController.php';
require_once __DIR__ . '/../controllers/WebMcpSalesController.php';
require_once __DIR__ . '/../controllers/WebMcpReportController.php';
require_once __DIR__ . '/../controllers/WebMcpOperationsController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new WebMcpClientController();
$kycController = new WebMcpKycController();
$ibController = new WebMcpIbController();
$adminLogController = new WebMcpAdminLogController();
$salesController = new WebMcpSalesController();
$reportController = new WebMcpReportController();
$operationsController = new WebMcpOperationsController();
$method = $_SERVER['REQUEST_METHOD'];
$path = trim((string)($_GET['path'] ?? ''), '/');

AuthMiddleware::authenticate();

try {
    $routes = [
        'admin/get-client' => ['handler' => 'getClient', 'method' => 'GET'],
        'admin/search-clients' => ['handler' => 'searchClients', 'method' => 'GET'],
        'admin/get-client-documents' => ['handler' => 'getClientDocuments', 'method' => 'GET'],
        'admin/get-client-trading-accounts' => ['handler' => 'getClientTradingAccounts', 'method' => 'GET'],
        'admin/get-client-recent-transactions' => ['handler' => 'getClientRecentTransactions', 'method' => 'GET'],
        'admin/search-transactions' => ['handler' => 'searchTransactions', 'method' => 'GET'],
        'admin/get-transaction' => ['handler' => 'getTransaction', 'method' => 'GET'],
        'admin/export-clients' => ['handler' => 'exportClients', 'method' => 'POST'],
        'admin/export-client-transactions' => ['handler' => 'exportClientTransactions', 'method' => 'POST'],
        'admin/export-transactions' => ['handler' => 'exportTransactions', 'method' => 'POST'],
        'admin/export-status' => ['handler' => 'exportStatus', 'method' => 'GET'],
        'admin/export-download' => ['handler' => 'exportDownload', 'method' => 'GET']
    ];

    foreach (WebMcpKycController::routeHandlers() as $kycPath => $handler) {
        $routes[$kycPath] = [
            'handler' => $handler,
            'method' => 'GET',
            'controller' => $kycController
        ];
    }

    foreach (WebMcpIbController::routeHandlers() as $ibPath => $handler) {
        $routes[$ibPath] = [
            'handler' => $handler,
            'method' => 'GET',
            'controller' => $ibController
        ];
    }

    foreach (WebMcpAdminLogController::routeHandlers() as $adminLogPath => $handler) {
        $routes[$adminLogPath] = [
            'handler' => $handler,
            'method' => $adminLogPath === 'admin/export-operation-logs' ? 'POST' : 'GET',
            'controller' => $adminLogController
        ];
    }

    foreach (WebMcpSalesController::routeHandlers() as $salesPath => $handler) {
        $routes[$salesPath] = [
            'handler' => $handler,
            'method' => 'GET',
            'controller' => $salesController
        ];
    }

    foreach (WebMcpReportController::routeHandlers() as $reportPath => $route) {
        $routes[$reportPath] = [
            'handler' => $route['handler'],
            'method' => $route['method'],
            'controller' => $reportController
        ];
    }

    foreach (WebMcpOperationsController::routeHandlers() as $operationsPath => $route) {
        $routes[$operationsPath] = [
            'handler' => $route['handler'],
            'method' => $route['method'],
            'controller' => $operationsController
        ];
    }

    if (isset($routes[$path])) {
        $route = $routes[$path];
        if ($method !== $route['method']) {
            Response::error('Method not allowed', 405);
        }
        $routeController = $route['controller'] ?? $controller;
        $routeController->{$route['handler']}();
    }

    Response::error('Route not found', 404);
} catch (Throwable $exception) {
    ApplicationErrorHandler::handleException($exception);
}
