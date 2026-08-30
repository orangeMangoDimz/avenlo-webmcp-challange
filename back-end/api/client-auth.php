<?php
/**
 * 客户端认证 API 路由
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 0);  // 不直接显示错误，而是记录

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
//header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Preview-Token');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * 从请求中读取 X-Preview-Token（兼容多种服务器/代理的 $_SERVER 键名）
 * @return string
 */
function getPreviewTokenFromRequest() {
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'x-preview-token') {
                $v = trim((string) $value);
                if ($v !== '') return $v;
                break;
            }
        }
    }
    $candidates = ['HTTP_X_PREVIEW_TOKEN', 'REDIRECT_HTTP_X_PREVIEW_TOKEN'];
    foreach ($candidates as $key) {
        if (!empty($_SERVER[$key])) {
            $v = trim((string) $_SERVER[$key]);
            if ($v !== '') return $v;
        }
    }
    foreach ($_SERVER as $key => $value) {
        if ((stripos($key, 'HTTP_') === 0 || stripos($key, 'REDIRECT_HTTP_') === 0)
            && stripos($key, 'PREVIEW') !== false && stripos($key, 'TOKEN') !== false) {
            $v = trim((string) $value);
            if ($v !== '') return $v;
        }
    }
    return '';
}

try {
    // 加载依赖
    require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';
    require_once __DIR__ . '/../utils/JWT.php';
    require_once __DIR__ . '/../middleware/AuthMiddleware.php';
    require_once __DIR__ . '/../controllers/ClientAuthController.php';

    $controller = new ClientAuthController();
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_GET['path'] ?? '';

    // 公开路由（不需要认证）
    $publicRoutes = [
        'POST /register' => 'register',
        'POST /login' => 'login',
        'POST /verify-email' => 'verifyEmail',
        'POST /resend-verification' => 'resendVerification',
        'POST /forgot-password' => 'forgotPassword',
        'POST /reset-password' => 'resetPassword',
        // App→WebView handoff：用 App 端签发的一次性 code 换 client JWT
        'POST /exchange-handoff' => 'exchangeHandoff'
    ];

    // 需要认证的路由
    $protectedRoutes = [
        'POST /logout' => 'logout',
        'GET /me' => 'me',
        'POST /change-password' => 'changePassword',
        'GET /my-documents' => 'myDocuments',
        'PUT /update-profile' => 'updateProfile',
        'GET /ib-status' => 'getIbStatus'
    ];

    $routeKey = "{$method} /{$path}";

    // 检查公开路由
    if (isset($publicRoutes[$routeKey])) {
        $method = $publicRoutes[$routeKey];
        $controller->$method();
        exit;
    }

    // 检查受保护路由
    if (isset($protectedRoutes[$routeKey])) {
        $methodName = $protectedRoutes[$routeKey];

        // 预览模式：优先校验 X-Preview-Token，有效则放行（不要求 Authorization）
        $previewToken = getPreviewTokenFromRequest();
        if ($previewToken !== '') {
            require_once __DIR__ . '/../models/AdminPreviewToken.php';
            $tokenModel = new AdminPreviewToken();
            $session = $tokenModel->validateToken($previewToken);
            if ($session) {
                $controller->$methodName();
                exit;
            }
        }

        // 正常认证：要求 Authorization
        $token = JWT::getTokenFromHeader();

        if (!$token) {
            Response::error('Unauthorized', 401);
        }

        AuthMiddleware::authenticate();

        $controller->$methodName();
        exit;
    }

    // 如果是GET请求但路径匹配POST路由，提供友好提示
    if ($method === 'GET') {
        $postKey = "POST /{$path}";
        if (isset($publicRoutes[$postKey]) || isset($protectedRoutes[$postKey])) {
            http_response_code(200);
            echo json_encode([
                'success' => false,
                'message' => 'This endpoint requires POST request',
                'endpoint' => "/{$path}",
                'required_method' => 'POST',
                'your_method' => 'GET',
                'hint' => 'Use test page or cURL to test POST endpoints',
                'test_page' => 'http://localhost/Utrada%20CRM/back-end/test-client-portal-api.html'
            ], JSON_PRETTY_PRINT);
            exit;
        }
    }

    // 路由未找到 - 提供可用路由列表
    $availableRoutes = array_merge(
        array_keys($publicRoutes),
        array_keys($protectedRoutes)
    );

    Response::error('Route not found', 404, [
        'requested' => $routeKey,
        'available_routes' => $availableRoutes,
        'hint' => 'Check the method (GET/POST) and path'
    ]);

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
