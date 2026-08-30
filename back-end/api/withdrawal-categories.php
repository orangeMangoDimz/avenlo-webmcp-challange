<?php
/**
 * Withdrawal Categories API
 */

require_once __DIR__ . '/../controllers/WithdrawalVerificationCategoryController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$categoryController = new WithdrawalVerificationCategoryController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

AuthMiddleware::authenticate();
$guardWithdrawalCategory = static function ($permissionKeys) {
    AuthMiddleware::checkAnyPermission((array)$permissionKeys);
};

try {
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    if (empty($path)) {
        if ($method === 'GET') {
            $guardWithdrawalCategory(['page_withdrawkyctemplates_readonly', 'page_withdrawkyctemplates_editcategory']);
            if (isset($_GET['template_id'])) {
                $categoryController->getTemplateCategories($_GET['template_id']);
            } else {
                Response::error('template_id is required', 400);
            }
        }
    } elseif ($path === 'create') {
        if ($method === 'POST') {
            $guardWithdrawalCategory('page_withdrawkyctemplates_addcategory');
            $categoryController->create();
        }
    } elseif ($path === 'reorder') {
        if ($method === 'PUT') {
            $guardWithdrawalCategory('page_withdrawkyctemplates_editcategory');
            $categoryController->reorder();
        }
    } elseif ($pathParts[0] === 'modify-with-locked' && isset($pathParts[1])) {
        if ($method === 'PUT') {
            $guardWithdrawalCategory('page_withdrawkyctemplates_editcategory');
            $categoryController->update($pathParts[1], false, true);
        }
    } elseif ($pathParts[0] === 'remove' && isset($pathParts[1])) {
        if ($method === 'DELETE') {
            $guardWithdrawalCategory('page_withdrawkyctemplates_deletecategory');
            $categoryController->delete($pathParts[1]);
        }
    } elseif ($id && !$action) {
        if ($method === 'GET') {
            $guardWithdrawalCategory(['page_withdrawkyctemplates_readonly', 'page_withdrawkyctemplates_editcategory']);
            $categoryController->show($id);
        } elseif ($method === 'PUT') {
            $guardWithdrawalCategory('page_withdrawkyctemplates_editcategory');
            $categoryController->update($id);
        } elseif ($method === 'DELETE') {
            $guardWithdrawalCategory('page_withdrawkyctemplates_deletecategory');
            $categoryController->delete($id);
        }
    } else {
        Response::error('Route not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
