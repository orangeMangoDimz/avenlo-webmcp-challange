<?php
/**
 * KYC Categories API 路由
 * 问题分类的CRUD操作
 */

require_once __DIR__ . '/../controllers/KycCategoryController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$categoryController = new KycCategoryController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 所有路由都需要管理员认证
AuthMiddleware::authenticate();
$guardKycCategory = static function ($permissionKeys) {
    AuthMiddleware::checkAnyPermission((array)$permissionKeys);
};

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    if (empty($path)) {
        // /api/kyc-categories
        if ($method === 'GET') {
            $guardKycCategory(['page_kyctemplates_readonly', 'page_kyctemplates_editcategory']);
            // 必须提供template_id
            if (isset($_GET['template_id'])) {
                $categoryController->getTemplateCategories($_GET['template_id']);
            } else {
                Response::error('template_id is required', 400);
            }
        }
    } elseif ($path === 'create') {
        // /api/kyc-categories/create
        if ($method === 'POST') {
            $guardKycCategory('page_kyctemplates_addcategory');
            $categoryController->create();
        }
    } elseif ($path === 'reorder') {
        // /api/kyc-categories/reorder
        if ($method === 'PUT') {
            $guardKycCategory('page_kyctemplates_editcategory');
            $categoryController->reorder();
        }
    } elseif ($pathParts[0] === 'remove' && isset($pathParts[1])) {
        // /api/kyc-categories/remove/{id}
        if ($method === 'DELETE') {
            $guardKycCategory('page_kyctemplates_deletecategory');
            $categoryController->delete($pathParts[1]);
        }
    } elseif ($id && !$action) {
        // /api/kyc-categories/{id}
        if ($method === 'GET') {
            $guardKycCategory(['page_kyctemplates_readonly', 'page_kyctemplates_editcategory']);
            $categoryController->show($id);
        } elseif ($method === 'PUT') {
            $guardKycCategory('page_kyctemplates_editcategory');
            $categoryController->update($id);
        } elseif ($method === 'DELETE') {
            $guardKycCategory('page_kyctemplates_deletecategory');
            $categoryController->delete($id);
        }
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
