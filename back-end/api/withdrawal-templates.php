<?php
/**
 * Withdrawal Templates API
 */

require_once __DIR__ . '/../controllers/WithdrawalVerificationTemplateController.php';
require_once __DIR__ . '/../controllers/WithdrawalVerificationQuestionController.php';
require_once __DIR__ . '/../controllers/WithdrawalVerificationCategoryController.php';
require_once __DIR__ . '/../controllers/WithdrawalVerificationRuleController.php';
require_once __DIR__ . '/../controllers/WithdrawalVerificationTemplateDocumentController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$templateController = new WithdrawalVerificationTemplateController();
$questionController = new WithdrawalVerificationQuestionController();
$categoryController = new WithdrawalVerificationCategoryController();
$ruleController = new WithdrawalVerificationRuleController();
$documentController = new WithdrawalVerificationTemplateDocumentController();

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

AuthMiddleware::authenticate();
$isAdminUser = (AuthMiddleware::getCurrentUser()['type'] ?? '') === 'admin';
$guardWithdrawalTemplate = static function ($permissionKeys) use ($isAdminUser) {
    if ($isAdminUser) {
        AuthMiddleware::checkAnyPermission((array)$permissionKeys);
    }
};

try {
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    if (empty($path)) {
        if ($method === 'GET') {
            $guardWithdrawalTemplate(['page_withdrawkyctemplates_readonly', 'page_withdrawkyctemplates_edittemplate']);
            $templateController->index();
        }
    } elseif ($path === 'statistics') {
        if ($method === 'GET') {
            $guardWithdrawalTemplate(['page_withdrawkyctemplates_readonly', 'page_withdrawkyctemplates_edittemplate']);
            $templateController->statistics();
        }
    } elseif ($pathParts[0] === 'deposit-payments') {
        if ($method === 'GET') {
            $gatewaySettingId = $pathParts[1] ?? ($_GET['gatewaySettingId'] ?? null);
            if (!$gatewaySettingId) {
                Response::validationError(['gatewaySettingId' => 'gatewaySettingId is required']);
            }
            $templateController->getGatewayDepositPrefill($gatewaySettingId);
        }
    } elseif ($pathParts[0] === 'payments') {
        if ($method === 'GET') {
            $gatewaySettingId = $pathParts[1] ?? ($_GET['gatewaySettingId'] ?? null);
            if (!$gatewaySettingId) {
                Response::validationError(['gatewaySettingId' => 'gatewaySettingId is required']);
            }
            $templateController->getGatewayTemplatePrefill($gatewaySettingId);
        }
    } elseif ($pathParts[0] === 'modify' && isset($pathParts[1])) {
        if ($method === 'PUT') {
            $guardWithdrawalTemplate('page_withdrawkyctemplates_edittemplate');
            $templateController->update($pathParts[1]);
        }
    } elseif ($id && !$action) {
        if ($method === 'GET') {
            $guardWithdrawalTemplate(['page_withdrawkyctemplates_readonly', 'page_withdrawkyctemplates_edittemplate']);
            $templateController->show($id);
        } elseif ($method === 'PUT') {
            $guardWithdrawalTemplate('page_withdrawkyctemplates_edittemplate');
            $templateController->update($id);
        }
    } elseif ($id && $action) {
        switch ($action) {
            case 'clone':
                if ($method === 'POST') {
                    $guardWithdrawalTemplate('page_withdrawkyctemplates_addtemplate');
                    $templateController->clone($id);
                }
                break;
            case 'history':
                if ($method === 'GET') {
                    $guardWithdrawalTemplate(['page_withdrawkyctemplates_readonly', 'page_withdrawkyctemplates_edittemplate']);
                    $templateController->getHistory($id);
                }
                break;
            case 'questions':
                if ($method === 'GET') {
                    $guardWithdrawalTemplate(['page_withdrawkyctemplates_readonly', 'page_withdrawkyctemplates_editquestion']);
                    $questionController->getTemplateQuestions($id);
                }
                break;
            case 'admin-questions':
                if ($method === 'POST') {
                    $guardWithdrawalTemplate(['page_withdrawkyctemplates_readonly', 'page_withdrawkyctemplates_editquestion']);
                    $questionController->getAdminTemplateQuestions($id);
                }
                break;
            case 'categories':
                if ($method === 'GET') {
                    $guardWithdrawalTemplate(['page_withdrawkyctemplates_readonly', 'page_withdrawkyctemplates_editcategory']);
                    $categoryController->getTemplateCategories($id);
                }
                break;
            case 'rules':
                if ($method === 'GET') {
                    $guardWithdrawalTemplate(['page_withdrawkyctemplates_readonly', 'page_withdrawkyctemplates_editrule']);
                    $ruleController->getTemplateRules($id);
                }
                break;
            case 'choice-questions':
                if ($method === 'GET') {
                    $guardWithdrawalTemplate(['page_withdrawkyctemplates_readonly', 'page_withdrawkyctemplates_editrule']);
                    $ruleController->getChoiceQuestions($id);
                }
                break;
            case 'documents':
                if ($method === 'GET') {
                    $guardWithdrawalTemplate(['page_withdrawkyctemplates_readonly', 'page_withdrawkyctemplates_editlegaldocument']);
                    $documentController->getTemplateDocuments($id);
                }
                break;
            default:
                Response::error('Route not found', 404);
        }
    } else {
        Response::error('Route not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
