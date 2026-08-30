<?php
/**
 * Withdrawal Template Documents API
 */

require_once __DIR__ . '/../controllers/WithdrawalVerificationTemplateDocumentController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$documentController = new WithdrawalVerificationTemplateDocumentController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

AuthMiddleware::authenticate();
$guardWithdrawalTemplateDocument = static function ($permissionKeys) {
    AuthMiddleware::checkAnyPermission((array)$permissionKeys);
};

try {
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    if ($path === 'create') {
        if ($method === 'POST') {
            $guardWithdrawalTemplateDocument('page_withdrawkyctemplates_addlegaldocument');
            $documentController->create();
        }
    } elseif ($pathParts[0] === 'modify' && isset($pathParts[1])) {
        if ($method === 'PUT') {
            $guardWithdrawalTemplateDocument('page_withdrawkyctemplates_editlegaldocument');
            $documentController->update($pathParts[1]);
        }
    } elseif ($pathParts[0] === 'remove' && isset($pathParts[1])) {
        if ($method === 'DELETE') {
            $guardWithdrawalTemplateDocument('page_withdrawkyctemplates_deletelegaldocument');
            $documentController->delete($pathParts[1]);
        }
    } elseif ($id && !$action) {
        if ($method === 'GET') {
            $guardWithdrawalTemplateDocument(['page_withdrawkyctemplates_readonly', 'page_withdrawkyctemplates_editlegaldocument']);
            $documentController->show($id);
        }
    } else {
        Response::error('Route not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
