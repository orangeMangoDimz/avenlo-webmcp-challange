<?php

require_once __DIR__ . '/../controllers/DeveloperSettingsController.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new DeveloperSettingsController();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $controller->index();
        return;
    }
    if ($method === 'PUT') {
        $controller->update();
        return;
    }
    Response::error('Method not allowed', 405);
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
