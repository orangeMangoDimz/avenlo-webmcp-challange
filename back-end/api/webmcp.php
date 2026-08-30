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
    if ($path === 'admin/get-client') {
        if ($method !== 'GET') {
            Response::error('Method not allowed', 405);
        }
        $controller->getClient();
    }

    Response::error('Route not found', 404);
} catch (Throwable $exception) {
    ApplicationErrorHandler::handleException($exception);
}
