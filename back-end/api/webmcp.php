<?php

require_once __DIR__ . '/../controllers/WebMcpClientController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new WebMcpClientController();
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
        'admin/export-clients' => ['handler' => 'exportClients', 'method' => 'POST'],
        'admin/export-client-transactions' => ['handler' => 'exportClientTransactions', 'method' => 'POST'],
        'admin/export-status' => ['handler' => 'exportStatus', 'method' => 'GET'],
        'admin/export-download' => ['handler' => 'exportDownload', 'method' => 'GET']
    ];

    if (isset($routes[$path])) {
        $route = $routes[$path];
        if ($method !== $route['method']) {
            Response::error('Method not allowed', 405);
        }
        $controller->{$route['handler']}();
    }

    Response::error('Route not found', 404);
} catch (Throwable $exception) {
    ApplicationErrorHandler::handleException($exception);
}
