<?php
/**
 * Daily Reports API routes
 */

require_once __DIR__ . '/../controllers/DailyReportController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new DailyReportController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// Every route requires an authenticated admin
AuthMiddleware::authenticate();

try {
    if ($path === 'summary') {
        // GET /api/daily-reports/summary
        if ($method === 'GET') {
            $controller->summary();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'kpi') {
        // PUT /api/daily-reports/kpi
        if ($method === 'PUT' || $method === 'POST') {
            $controller->saveKpi();
        } else {
            Response::error('Method not allowed', 405);
        }
    } else {
        Response::error('Route not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
