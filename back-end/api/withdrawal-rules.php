<?php
/**
 * Withdrawal Rules API
 */

require_once __DIR__ . '/../controllers/WithdrawalVerificationRuleController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$ruleController = new WithdrawalVerificationRuleController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

AuthMiddleware::authenticate();
$guardWithdrawalRule = static function ($permissionKeys) {
    AuthMiddleware::checkAnyPermission((array)$permissionKeys);
};

try {
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    if (empty($path)) {
        if ($method === 'GET') {
            $guardWithdrawalRule(['page_withdrawkyctemplates_readonly', 'page_withdrawkyctemplates_editrule']);
            if (isset($_GET['template_id'])) {
                $ruleController->getTemplateRules($_GET['template_id']);
            } else {
                Response::error('template_id is required', 400);
            }
        }
    } elseif ($path === 'create') {
        if ($method === 'POST') {
            $guardWithdrawalRule('page_withdrawkyctemplates_addrule');
            $ruleController->create();
        }
    } elseif ($pathParts[0] === 'modify' && isset($pathParts[1])) {
        if ($method === 'PUT') {
            $guardWithdrawalRule('page_withdrawkyctemplates_editrule');
            $ruleController->update($pathParts[1]);
        }
    } elseif ($pathParts[0] === 'remove' && isset($pathParts[1])) {
        if ($method === 'DELETE') {
            $guardWithdrawalRule('page_withdrawkyctemplates_deleterule');
            $ruleController->delete($pathParts[1]);
        }
    } elseif ($id && !$action) {
        if ($method === 'GET') {
            $guardWithdrawalRule(['page_withdrawkyctemplates_readonly', 'page_withdrawkyctemplates_editrule']);
            $ruleController->show($id);
        } elseif ($method === 'PUT') {
            $guardWithdrawalRule('page_withdrawkyctemplates_editrule');
            $ruleController->update($id);
        } elseif ($method === 'DELETE') {
            $guardWithdrawalRule('page_withdrawkyctemplates_deleterule');
            $ruleController->delete($id);
        }
    } else {
        Response::error('Route not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
