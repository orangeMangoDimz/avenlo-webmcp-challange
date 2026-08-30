<?php
/**
 * Withdrawal Questions API
 */

require_once __DIR__ . '/../controllers/WithdrawalVerificationQuestionController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$questionController = new WithdrawalVerificationQuestionController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

AuthMiddleware::authenticate();
$guardWithdrawalQuestion = static function ($permissionKeys) {
    AuthMiddleware::checkAnyPermission((array)$permissionKeys);
};

try {
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    if (empty($path)) {
        if ($method === 'GET') {
            $guardWithdrawalQuestion(['page_withdrawkyctemplates_readonly', 'page_withdrawkyctemplates_editquestion']);
            if (isset($_GET['template_id'])) {
                $questionController->getTemplateQuestions($_GET['template_id']);
            } elseif (isset($_GET['category_id'])) {
                $questionController->getCategoryQuestions($_GET['category_id']);
            } else {
                Response::error('Either template_id or category_id is required', 400);
            }
        }
    } elseif ($path === 'create') {
        if ($method === 'POST') {
            $guardWithdrawalQuestion('page_withdrawkyctemplates_addquestion');
            $questionController->create();
        }
    } elseif ($path === 'create-with-scope') {
        if ($method === 'POST') {
            $guardWithdrawalQuestion('page_withdrawkyctemplates_addquestion');
            $questionController->create(true);
        }
    } elseif ($path === 'create-with-scope-and-locked') {
        if ($method === 'POST') {
            $guardWithdrawalQuestion('page_withdrawkyctemplates_addquestion');
            $questionController->create(true, true);
        }
    } elseif ($path === 'reorder') {
        if ($method === 'PUT') {
            $guardWithdrawalQuestion('page_withdrawkyctemplates_editquestion');
            $questionController->reorder();
        }
    } elseif ($pathParts[0] === 'modify' && isset($pathParts[1])) {
        if ($method === 'PUT') {
            $guardWithdrawalQuestion('page_withdrawkyctemplates_editquestion');
            $questionController->update($pathParts[1]);
        }
    } elseif ($pathParts[0] === 'modify-with-scope' && isset($pathParts[1])) {
        if ($method === 'PUT') {
            $guardWithdrawalQuestion('page_withdrawkyctemplates_editquestion');
            $questionController->update($pathParts[1], true);
        }
    } elseif ($pathParts[0] === 'modify-with-scope-and-locked' && isset($pathParts[1])) {
        if ($method === 'PUT') {
            $guardWithdrawalQuestion('page_withdrawkyctemplates_editquestion');
            $questionController->update($pathParts[1], true, true);
        }
    } elseif ($pathParts[0] === 'delete' && isset($pathParts[1])) {
        if ($method === 'DELETE') {
            $guardWithdrawalQuestion('page_withdrawkyctemplates_deletequestion');
            $questionController->delete($pathParts[1]);
        }
    } elseif ($id && !$action) {
        if ($method === 'GET') {
            $guardWithdrawalQuestion(['page_withdrawkyctemplates_readonly', 'page_withdrawkyctemplates_editquestion']);
            $questionController->show($id);
        } elseif ($method === 'PUT') {
            $guardWithdrawalQuestion('page_withdrawkyctemplates_editquestion');
            $questionController->update($id);
        } elseif ($method === 'DELETE') {
            $guardWithdrawalQuestion('page_withdrawkyctemplates_deletequestion');
            $questionController->delete($id);
        }
    } elseif ($id && $action === 'duplicate') {
        if ($method === 'POST') {
            $guardWithdrawalQuestion('page_withdrawkyctemplates_duplicatequestion');
            $questionController->duplicate($id);
        }
    } else {
        Response::error('Route not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
