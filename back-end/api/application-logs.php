<?php
/**
 * Application logs API
 * GET /api/application-logs
 */

require_once __DIR__ . '/../controllers/ApplicationLogController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new ApplicationLogController();
$method = $_SERVER['REQUEST_METHOD'];
$path = trim($_GET['path'] ?? '', '/');

try {
    if ($path === '' || $path === 'list') {
        if ($method === 'GET') {
            $controller->index();
            return;
        }
        Response::error('Method not allowed', 405);
    }

    Response::error('Route not found', 404);
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
