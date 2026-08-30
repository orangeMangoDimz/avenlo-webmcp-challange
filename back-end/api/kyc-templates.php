<?php
/**
 * KYC Templates API 路由
 * 模板管理、问题配置、规则管理
 */

require_once __DIR__ . '/../controllers/KycTemplateController.php';
require_once __DIR__ . '/../controllers/KycQuestionController.php';
require_once __DIR__ . '/../controllers/KycCategoryController.php';
require_once __DIR__ . '/../controllers/KycRuleController.php';
require_once __DIR__ . '/../controllers/KycTemplateDocumentController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$templateController = new KycTemplateController();
$questionController = new KycQuestionController();
$categoryController = new KycCategoryController();
$ruleController = new KycRuleController();
$documentController = new KycTemplateDocumentController();

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

AuthMiddleware::authenticate();
$isAdminUser = (AuthMiddleware::getCurrentUser()['type'] ?? '') === 'admin';
$guardKycTemplate = static function ($permissionKeys) use ($isAdminUser) {
    if ($isAdminUser) {
        AuthMiddleware::checkAnyPermission((array)$permissionKeys);
    }
};

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;
    $subAction = $pathParts[2] ?? null;

    // ============================================================
    // 模板路由
    // ============================================================

    if (empty($path)) {
        // /api/kyc-templates
        if ($method === 'GET') {
            $guardKycTemplate(['page_kyctemplates_readonly', 'page_kyctemplates_edittemplate']);
            $templateController->index();
        }
    } elseif ($path === 'taken-country-codes') {
        // /api/kyc-templates/taken-country-codes?exclude_template_id=123
        if ($method === 'GET') {
            $guardKycTemplate(['page_kyctemplates_readonly', 'page_kyctemplates_edittemplate']);
            $templateController->getTakenCountryCodes();
        } else {
            Response::error('Route not found', 404);
        }
    } elseif ($path === 'create') {
        // /api/kyc-templates/create
        if ($method === 'POST') {
            $guardKycTemplate('page_kyctemplates_addtemplate');
            $templateController->create();
        }
    } elseif ($path === 'statistics') {
        // /api/kyc-templates/statistics
        if ($method === 'GET') {
            $guardKycTemplate(['page_kyctemplates_readonly', 'page_kyctemplates_edittemplate']);
            $templateController->statistics();
        }
    } elseif ($pathParts[0] === 'modify' && isset($pathParts[1])) {
        // /api/kyc-templates/modify/{id}
        $modifyId = $pathParts[1];
        if ($method === 'PUT') {
            $guardKycTemplate('page_kyctemplates_edittemplate');
            $templateController->update($modifyId);
        }
    } elseif ($id && !$action) {
        // /api/kyc-templates/{id}
        if ($method === 'GET') {
            $guardKycTemplate(['page_kyctemplates_readonly', 'page_kyctemplates_edittemplate']);
            $templateController->show($id);
        } elseif ($method === 'PUT') {
            $guardKycTemplate('page_kyctemplates_edittemplate');
            $templateController->update($id);
        } elseif ($method === 'DELETE') {
            $guardKycTemplate('page_kyctemplates_deletetemplate');
            $templateController->delete($id);
        }
    } elseif ($id && $action) {
        // /api/kyc-templates/{id}/{action}

        switch ($action) {
            case 'clone':
                // /api/kyc-templates/{id}/clone
                if ($method === 'POST') {
                    $guardKycTemplate('page_kyctemplates_addtemplate');
                    $templateController->clone($id);
                }
                break;

            case 'countries':
                // /api/kyc-templates/{id}/countries
                if ($method === 'PUT') {
                    $guardKycTemplate('page_kyctemplates_edittemplate');
                    $templateController->updateCountries($id);
                }
                break;

            case 'history':
                // /api/kyc-templates/{id}/history
                if ($method === 'GET') {
                    $guardKycTemplate(['page_kyctemplates_readonly', 'page_kyctemplates_edittemplate']);
                    $templateController->getHistory($id);
                }
                break;

            case 'third-party':
                // /api/kyc-templates/{id}/third-party  原子更新第三方绑定
                if ($method === 'PUT') {
                    $guardKycTemplate('page_kyctemplates_edittemplate');
                    $templateController->updateThirdPartyBinding($id);
                }
                break;

            case 'questions':
                // /api/kyc-templates/{id}/questions (客户端接口，只返回active的问题)
                if ($method === 'GET') {
                    $guardKycTemplate(['page_kyctemplates_readonly', 'page_kyctemplates_editquestion']);
                    $questionController->getTemplateQuestions($id);
                }
                break;

            case 'admin-questions':
                // /api/kyc-templates/{id}/admin-questions (后台管理接口，支持获取所有问题)
                if ($method === 'POST') {
                    $guardKycTemplate(['page_kyctemplates_readonly', 'page_kyctemplates_editquestion']);
                    $questionController->getAdminTemplateQuestions($id);
                }
                break;

            case 'categories':
                // /api/kyc-templates/{id}/categories
                if ($method === 'GET') {
                    $guardKycTemplate(['page_kyctemplates_readonly', 'page_kyctemplates_editcategory']);
                    $categoryController->getTemplateCategories($id);
                }
                break;

            case 'rules':
                // /api/kyc-templates/{id}/rules
                if ($method === 'GET') {
                    $guardKycTemplate(['page_kyctemplates_readonly', 'page_kyctemplates_editrule']);
                    $ruleController->getTemplateRules($id);
                }
                break;

            case 'choice-questions':
                // /api/kyc-templates/{id}/choice-questions
                if ($method === 'GET') {
                    $guardKycTemplate(['page_kyctemplates_readonly', 'page_kyctemplates_editrule']);
                    $ruleController->getChoiceQuestions($id);
                }
                break;

            case 'documents':
                // /api/kyc-templates/{id}/documents
                if ($method === 'GET') {
                    $guardKycTemplate(['page_kyctemplates_readonly', 'page_kyctemplates_editlegaldocument']);
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
