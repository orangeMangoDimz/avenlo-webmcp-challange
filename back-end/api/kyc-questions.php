<?php
/**
 * KYC Questions API 路由
 * 问题的CRUD操作
 */

require_once __DIR__ . '/../controllers/KycQuestionController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$questionController = new KycQuestionController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 所有路由都需要管理员认证
AuthMiddleware::authenticate();
$guardKycQuestion = static function ($permissionKeys) {
    AuthMiddleware::checkAnyPermission((array)$permissionKeys);
};

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    if (empty($path)) {
        // /api/kyc-questions
        if ($method === 'GET') {
            $guardKycQuestion(['page_kyctemplates_readonly', 'page_kyctemplates_editquestion']);
            // 支持通过query参数过滤
            if (isset($_GET['template_id'])) {
                $questionController->getTemplateQuestions($_GET['template_id']);
            } elseif (isset($_GET['category_id'])) {
                $questionController->getCategoryQuestions($_GET['category_id']);
            } else {
                Response::error('Either template_id or category_id is required', 400);
            }
        }
    } elseif ($path === 'create') {
        // /api/kyc-questions/create
        if ($method === 'POST') {
            $guardKycQuestion('page_kyctemplates_addquestion');
            $questionController->create();
        }
    } elseif ($path === 'reorder') {
        // /api/kyc-questions/reorder
        if ($method === 'PUT') {
            $guardKycQuestion('page_kyctemplates_editquestion');
            $questionController->reorder();
        }
    } elseif ($pathParts[0] === 'modify' && isset($pathParts[1])) {
        // /api/kyc-questions/modify/{id}
        if ($method === 'PUT') {
            $guardKycQuestion('page_kyctemplates_editquestion');
            $questionController->update($pathParts[1]);
        }
    } elseif ($pathParts[0] === 'delete' && isset($pathParts[1])) {
        // /api/kyc-questions/delete/{id}
        if ($method === 'DELETE') {
            $guardKycQuestion('page_kyctemplates_deletequestion');
            $questionController->delete($pathParts[1]);
        }
    } elseif ($id && !$action) {
        // /api/kyc-questions/{id}
        if ($method === 'GET') {
            $guardKycQuestion(['page_kyctemplates_readonly', 'page_kyctemplates_editquestion']);
            $questionController->show($id);
        } elseif ($method === 'PUT') {
            $guardKycQuestion('page_kyctemplates_editquestion');
            $questionController->update($id);
        } elseif ($method === 'DELETE') {
            $guardKycQuestion('page_kyctemplates_deletequestion');
            $questionController->delete($id);
        }
    } elseif ($id && $action === 'duplicate') {
        // /api/kyc-questions/{id}/duplicate
        if ($method === 'POST') {
            $guardKycQuestion('page_kyctemplates_duplicatequestion');
            $questionController->duplicate($id);
        }
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
