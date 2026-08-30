<?php
/**
 * External KYC Gateways API 路由
 * 第三方 KYC 网关（Sumsub 等）配置管理
 */

require_once __DIR__ . '/../controllers/ExternalKycGatewayController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new ExternalKycGatewayController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 所有接口都需要管理员认证
AuthMiddleware::authenticate();

// 只对管理员账号做权限校验（客户端账号到不了这里，留着兼容）
$isAdminUser = (AuthMiddleware::getCurrentUser()['type'] ?? '') === 'admin';
$guard = static function ($permissionKeys) use ($isAdminUser) {
    if ($isAdminUser) {
        AuthMiddleware::checkAnyPermission((array)$permissionKeys);
    }
};

// 通用读权限：任何能"看"的操作都至少要 readonly，edit 也算能看
$readPerms = ['page_externalkycsettings_readonly', 'page_externalkycsettings_edit'];

try {
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    if (empty($path)) {
        // GET /api/external-kyc-gateways  网关列表
        if ($method === 'GET') {
            $guard($readPerms);
            $controller->index();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id === 'template' && $action) {
        // GET /api/external-kyc-gateways/template/{externalTemplateId}  绑定信息（kyc template 详情页要用）
        // 这里放宽一点：允许 kyc 模板相关权限的人也能读（不然他们看 kyc template 详情会少这块信息）
        if ($method === 'GET') {
            $guard(array_merge($readPerms, ['page_kyctemplates_readonly', 'page_kyctemplates_edittemplate']));
            $controller->showTemplateWithGateway($action);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && !$action) {
        // PUT /api/external-kyc-gateways/{id}    修改配置
        // DELETE /api/external-kyc-gateways/{id} 软删除
        if ($method === 'PUT') {
            $guard('page_externalkycsettings_edit');
            $controller->update($id);
        } elseif ($method === 'DELETE') {
            $guard('page_externalkycsettings_delete');
            $controller->softDelete($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && $action === 'enabled') {
        // PUT /api/external-kyc-gateways/{id}/enabled  启用/停用
        if ($method === 'PUT') {
            $guard('page_externalkycsettings_enabledisable');
            $controller->setEnabled($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && $action === 'templates') {
        // GET /api/external-kyc-gateways/{id}/templates  已同步的 level 列表
        if ($method === 'GET') {
            $guard($readPerms);
            $controller->listTemplates($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && $action === 'sync') {
        // POST /api/external-kyc-gateways/{id}/sync  从第三方拉 level
        if ($method === 'POST') {
            $guard('page_externalkycsettings_sync');
            $controller->sync($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } else {
        Response::error('Route not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
