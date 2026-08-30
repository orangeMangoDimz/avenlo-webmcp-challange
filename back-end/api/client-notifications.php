<?php
/**
 * 客户端 / 后台通知 API 入口
 * - 客户端：读取、标记通知（支持 X-Preview-Token 预览）
 * - 后台：创建、调度通知（需 JWT 管理员）
 */

require_once __DIR__ . '/../controllers/ClientNotificationController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = trim($_GET['path'] ?? '', '/');
$pathParts = array_values(array_filter(explode('/', $path), 'strlen'));
$resource = $pathParts[0] ?? null;

$controller = new ClientNotificationController();

try {
    if ($resource === 'bulk') {
        AuthMiddleware::authenticate();
        if ($method === 'POST') {
            $controller->createBulk();
        } else {
            Response::error('Method not allowed', 405);
        }
        return;
    }

    if (empty($resource)) {
        AuthMiddleware::authenticate();
        if ($method === 'POST') {
            $controller->create();
        } else {
            Response::error('Method not allowed', 405);
        }
        return;
    }

    // 客户端接口：支持 X-Preview-Token，优先放行预览
    $isClientRoute = ($resource === 'list' && $method === 'GET')
        || ($resource === 'mark-read' && $method === 'POST')
        || ($resource === 'mark-all-read' && in_array($method, ['GET', 'POST'], true));

    if ($isClientRoute) {
        $clientUserId = ClientAuthContext::getCurrentClientUserId();
        if ($clientUserId !== null) {
            $GLOBALS['current_user'] = ['userId' => $clientUserId, 'type' => 'client'];
            if ($resource === 'list') {
                $controller->listForClient();
            } else if ($resource === 'mark-read') {
                $controller->markReadForClient();
            } else if ($resource === 'mark-all-read') {
                $controller->markAllReadForClient();
            }
            return;
        }
    }

    AuthMiddleware::authenticate();
    $currentUser = AuthMiddleware::getCurrentUser();
    $userType = $currentUser['type'] ?? null;

    if ($resource === 'process-due' && $method === 'POST') {
        $controller->processDue();
    } else if ($resource === 'list' && $method === 'GET') {
        $controller->listForClient();
    } else if ($resource === 'mark-read' && $method === 'POST') {
        $controller->markReadForClient();
    } else if ($resource === 'mark-all-read' && in_array($method, ['GET', 'POST'], true)) {
        $controller->markAllReadForClient();
    } else {
        Response::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
