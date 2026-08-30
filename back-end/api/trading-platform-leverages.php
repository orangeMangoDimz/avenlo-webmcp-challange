<?php
/**
 * Trading Platform Leverages API 路由
 * 用户侧按平台获取可用杠杆
 */

require_once __DIR__ . '/../controllers/TradingPlatformLeverageController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new TradingPlatformLeverageController();
$method = $_SERVER['REQUEST_METHOD'];
$path = trim((string)($_GET['path'] ?? ''), '/');

if (!ClientAuthContext::allowPreviewRequest()) {
    AuthMiddleware::authenticate();
}

try {
    if ($method !== 'GET') {
        Response::error('Method not allowed', 405);
    }

    if ($path === '') {
        Response::validationError([
            'platformKey' => ['platformKey is required.']
        ], 'platformKey is required.');
    }

    $controller->getUserLeverages($path);
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
