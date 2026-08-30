<?php
/**
 * KYC Template Documents API 路由
 * 模板文档的CRUD操作
 */

require_once __DIR__ . '/../controllers/KycTemplateDocumentController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$documentController = new KycTemplateDocumentController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 所有路由都需要管理员认证
AuthMiddleware::authenticate();
$guardKycTemplateDocument = static function ($permissionKeys) {
    AuthMiddleware::checkAnyPermission((array)$permissionKeys);
};

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    if ($path === 'create') {
        // /api/kyc-template-documents/create
        if ($method === 'POST') {
            $guardKycTemplateDocument('page_kyctemplates_addlegaldocument');
            $documentController->create();
        }
    } elseif ($pathParts[0] === 'modify' && isset($pathParts[1])) {
        // /api/kyc-template-documents/modify/{id}
        if ($method === 'PUT') {
            $guardKycTemplateDocument('page_kyctemplates_editlegaldocument');
            $documentController->update($pathParts[1]);
        }
    } elseif ($pathParts[0] === 'remove' && isset($pathParts[1])) {
        // /api/kyc-template-documents/remove/{id}
        if ($method === 'DELETE') {
            $guardKycTemplateDocument('page_kyctemplates_deletelegaldocument');
            $documentController->delete($pathParts[1]);
        }
    } elseif ($id && !$action) {
        // /api/kyc-template-documents/{id}
        if ($method === 'GET') {
            $guardKycTemplateDocument(['page_kyctemplates_readonly', 'page_kyctemplates_editlegaldocument']);
            $documentController->show($id);
        }
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
