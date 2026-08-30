<?php
/**
 * Commission Orders API 路由
 */

require_once __DIR__ . '/../controllers/CommissionOrderController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new CommissionOrderController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

AuthMiddleware::authenticate();

try {
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    if (empty($path)) {
        if ($method === 'GET') {
            $controller->index();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif (is_numeric($id) && $action === 'approve') {
        if ($method === 'POST') {
            $controller->approve($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif (is_numeric($id) && $action === 'complete') {
        if ($method === 'POST') {
            $controller->complete($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif (is_numeric($id) && $action === 'cancel') {
        if ($method === 'POST') {
            $controller->cancel($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } else {
        Response::error('Not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
