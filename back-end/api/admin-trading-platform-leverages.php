<?php
/**
 * Admin Trading Platform Leverages API 路由
 */

require_once __DIR__ . '/../controllers/TradingPlatformLeverageController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new TradingPlatformLeverageController();
$method = $_SERVER['REQUEST_METHOD'];
$path = trim((string)($_GET['path'] ?? ''), '/');
$pathParts = array_values(array_filter(explode('/', $path), 'strlen'));
$id = $pathParts[0] ?? null;
$action = $pathParts[1] ?? null;

AuthMiddleware::authenticate();
AuthMiddleware::checkAnyPermission(
    $method === 'GET'
        ? ['page_platformsettings_readonly', 'page_platformsettings_edit']
        : ['page_platformsettings_edit']
);

try {
    if ($id === null) {
        if ($method === 'GET') {
            $controller->index();
        } elseif ($method === 'POST') {
            $controller->create();
        } else {
            Response::error('Route not found', 404);
        }
    } elseif (ctype_digit((string)$id) && $action === null) {
        if ($method === 'GET') {
            $controller->show((int)$id);
        } elseif ($method === 'PUT') {
            $controller->update((int)$id);
        } elseif ($method === 'DELETE') {
            $controller->delete((int)$id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif (ctype_digit((string)$id) && $action === 'enable' && $method === 'PUT') {
        $controller->enable((int)$id);
    } elseif (ctype_digit((string)$id) && $action === 'disable' && $method === 'PUT') {
        $controller->disable((int)$id);
    } else {
        Response::error('Route not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
