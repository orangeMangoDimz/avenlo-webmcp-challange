<?php
/**
 * 登录页设置 API 路由
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
//header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // 加载依赖
    require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';
    require_once __DIR__ . '/../controllers/LoginPageSettingsController.php';
    require_once __DIR__ . '/../middleware/AuthMiddleware.php';

    $controller = new LoginPageSettingsController();
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_GET['path'] ?? '';

    // 移除查询参数（如果有的话）
    $path = strtok($path, '?');
    $path = trim($path, '/');

    // 解析路径段
    $segments = array_filter(explode('/', $path));
    $segments = array_values($segments);

    // 公开路由（不需要认证）
    $publicRoutes = [
        'GET /branding' => 'getBranding',
        'GET /detect-language' => 'getIpDetectionLanguage',
        'GET /form-fields' => 'getFormFields',
        'GET /form-fields/enabled' => 'getEnabledFormFields',
        'GET /password-strength' => 'getPasswordStrength',
        'GET /legal-documents' => 'getLegalDocuments',
        'GET /legal-documents/active' => 'getActiveLegalDocuments',
        'GET /language-packs' => 'getLanguagePacks',
        'GET /language-packs/enabled' => 'getEnabledLanguagePacks',
        'GET /ip-language-detection' => 'getIpLanguageDetection',
        'GET /email-verification' => 'getEmailVerification',
        'GET /trading-groups' => 'getTradingGroups',
        'GET /trading-groups/default' => 'getDefaultTradingGroup',
        'GET /trading-groups/platforms' => 'getTradingGroupPlatforms'
    ];

    // 需要管理员认证的路由
    $protectedRoutes = [
        'PUT /branding' => 'updateBranding',
        'POST /branding/upload-logo' => 'uploadLogo',
        'POST /form-fields' => 'createFormField',
        'PUT /form-fields/order' => 'updateFieldsOrder',
        'PUT /password-strength' => 'updatePasswordStrength',
        'POST /password-strength/apply-level' => 'applyPasswordLevel',
        'POST /legal-documents' => 'createLegalDocument',
        'POST /language-packs' => 'uploadLanguagePack',
        'POST /language-packs/set-default' => 'setDefaultLanguage',
        'PUT /ip-language-detection' => 'updateIpLanguageDetection',
        'PUT /email-verification' => 'updateEmailVerification',
        'POST /trading-groups/set-default' => 'setDefaultTradingGroup',
        'POST /trading-groups/remove-default' => 'removeDefaultTradingGroup',
        'POST /trading-groups/sync' => 'syncTradingGroups',
        'GET /change-log' => 'getChangeLog',
        'PUT /countries/status' => 'updateCountriesStatus',
        'GET /countries' => 'getCountriesList',
    ];

    $loginReadonlyRoutes = [
        'GET /change-log',
        'GET /countries',
    ];

    $loginEditRoutes = [
        'PUT /branding',
        'POST /branding/upload-logo',
        'POST /form-fields',
        'PUT /form-fields/order',
        'PUT /password-strength',
        'POST /password-strength/apply-level',
        'POST /legal-documents',
        'POST /language-packs',
        'POST /language-packs/set-default',
        'PUT /ip-language-detection',
        'PUT /email-verification',
        'PUT /countries/status',
    ];

    $platformEditRoutes = [
        'POST /trading-groups/set-default',
        'POST /trading-groups/remove-default',
        'POST /trading-groups/sync',
    ];

    $routeKey = "{$method} /{$path}";

    // 检查公开路由
    if (isset($publicRoutes[$routeKey])) {
        $methodName = $publicRoutes[$routeKey];
        $controller->$methodName();
        exit;
    }

    // 检查受保护路由
    if (isset($protectedRoutes[$routeKey])) {
        AuthMiddleware::authenticate();
        if (in_array($routeKey, $loginReadonlyRoutes, true)) {
            AuthMiddleware::checkAnyPermission(['page_loginpagesettings_readonly', 'page_loginpagesettings_edit']);
        } elseif (in_array($routeKey, $loginEditRoutes, true)) {
            AuthMiddleware::checkPermission('page_loginpagesettings_edit');
        } elseif (in_array($routeKey, $platformEditRoutes, true)) {
            AuthMiddleware::checkPermission('page_platformsettings_edit');
        }
        $methodName = $protectedRoutes[$routeKey];
        $controller->$methodName();
        exit;
    }

    // 处理带参数的路由
    if (count($segments) >= 2) {
        $resource = $segments[0];
        $id = $segments[1];
        $action = $segments[2] ?? null;

        // form-fields/:id
        if ($resource === 'form-fields' && is_numeric($id)) {
            AuthMiddleware::authenticate();
            if ($method === 'PUT' || $method === 'DELETE') {
                AuthMiddleware::checkPermission('page_loginpagesettings_edit');
            }

            if ($method === 'PUT') {
                $controller->updateFormField($id);
            } elseif ($method === 'DELETE') {
                $controller->deleteFormField($id);
            } else {
                Response::error('Method not allowed', 405);
            }
            exit;
        }

        // legal-documents/:id
        if ($resource === 'legal-documents' && is_numeric($id)) {
            AuthMiddleware::authenticate();
            if ($method === 'PUT' || $method === 'DELETE') {
                AuthMiddleware::checkPermission('page_loginpagesettings_edit');
            }

            if ($method === 'PUT') {
                $controller->updateLegalDocument($id);
            } elseif ($method === 'DELETE') {
                $controller->deleteLegalDocument($id);
            } else {
                Response::error('Method not allowed', 405);
            }
            exit;
        }

        // language-packs/:code/translations
        if ($resource === 'language-packs' && $action === 'translations') {
            if ($method === 'GET') {
                $controller->getTranslations($id);
            } else {
                Response::error('Method not allowed', 405);
            }
            exit;
        }

        // language-packs/:code
        if ($resource === 'language-packs' && !$action) {
            AuthMiddleware::authenticate();
            if ($method === 'PUT') {
                AuthMiddleware::checkPermission('page_loginpagesettings_edit');
            }

            if ($method === 'PUT') {
                $controller->updateLanguagePack($id);
            } else {
                Response::error('Method not allowed', 405);
            }
            exit;
        }

        // trading-groups/:id/label
        if ($resource === 'trading-groups' && is_numeric($id) && $action === 'label') {
            AuthMiddleware::authenticate();
            if ($method === 'PUT') {
                AuthMiddleware::checkPermission('page_platformsettings_edit');
                $controller->updateTradingGroupLabel($id);
            } else {
                Response::error('Method not allowed', 405);
            }
            exit;
        }

        // trading-groups/:id/config
        if ($resource === 'trading-groups' && is_numeric($id) && $action === 'config') {
            AuthMiddleware::authenticate();
            if ($method === 'PUT') {
                AuthMiddleware::checkPermission('page_platformsettings_edit');
                $controller->updateTradingGroupConfig($id);
            } else {
                Response::error('Method not allowed', 405);
            }
            exit;
        }

        // trading-groups/platforms/:platformKey/account-settings
        if ($resource === 'trading-groups' && $id === 'platforms'
            && isset($segments[2]) && ($segments[3] ?? null) === 'account-settings') {
            AuthMiddleware::authenticate();
            if ($method === 'PUT') {
                AuthMiddleware::checkPermission('page_platformsettings_edit');
                $controller->updatePlatformAccountSettings($segments[2]);
            } else {
                Response::error('Method not allowed', 405);
            }
            exit;
        }
    }

    // 路由未找到
    Response::error('Route not found: ' . $routeKey, 404);

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
