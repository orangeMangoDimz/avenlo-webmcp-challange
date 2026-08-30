<?php

require_once __DIR__ . '/../controllers/ClientIbClientPositionController.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new ClientIbClientPositionController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

try {
    if ($path === '' || $path === 'positions') {
        if ($method === 'GET') {
            $controller->positions();
        } else {
            Response::error('Method not allowed', 405);
        }
    } else {
        Response::error('Route not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
