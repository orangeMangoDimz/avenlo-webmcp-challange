<?php
/**
 * 客户邮件模板 API
 */

require_once __DIR__ . '/../controllers/ClientEmailTemplateController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new ClientEmailTemplateController();
$method = $_SERVER['REQUEST_METHOD'];

AuthMiddleware::authenticate();

try {
    if ($method === 'GET') {
        $controller->index();
    } else {
        Response::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
