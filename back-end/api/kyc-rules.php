<?php
/**
 * KYC Conditional Rules API 路由
 * 条件规则的CRUD操作
 */

require_once __DIR__ . '/../controllers/KycRuleController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$ruleController = new KycRuleController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 所有路由都需要管理员认证
AuthMiddleware::authenticate();
$guardKycRule = static function ($permissionKeys) {
    AuthMiddleware::checkAnyPermission((array)$permissionKeys);
};

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    if (empty($path)) {
        // /api/kyc-rules
        if ($method === 'GET') {
            $guardKycRule(['page_kyctemplates_readonly', 'page_kyctemplates_editrule']);
            // 必须提供template_id
            if (isset($_GET['template_id'])) {
                $ruleController->getTemplateRules($_GET['template_id']);
            } else {
                Response::error('template_id is required', 400);
            }
        }
    } elseif ($path === 'create') {
        // /api/kyc-rules/create
        if ($method === 'POST') {
            $guardKycRule('page_kyctemplates_addrule');
            $ruleController->create();
        }
    } elseif ($pathParts[0] === 'modify' && isset($pathParts[1])) {
        // /api/kyc-rules/modify/{id}
        if ($method === 'PUT') {
            $guardKycRule('page_kyctemplates_editrule');
            $ruleController->update($pathParts[1]);
        }
    } elseif ($pathParts[0] === 'remove' && isset($pathParts[1])) {
        // /api/kyc-rules/remove/{id}
        if ($method === 'DELETE') {
            $guardKycRule('page_kyctemplates_deleterule');
            $ruleController->delete($pathParts[1]);
        }
    } elseif ($id && !$action) {
        // /api/kyc-rules/{id}
        if ($method === 'GET') {
            $guardKycRule(['page_kyctemplates_readonly', 'page_kyctemplates_editrule']);
            $ruleController->show($id);
        } elseif ($method === 'PUT') {
            $guardKycRule('page_kyctemplates_editrule');
            $ruleController->update($id);
        } elseif ($method === 'DELETE') {
            $guardKycRule('page_kyctemplates_deleterule');
            $ruleController->delete($id);
        }
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
