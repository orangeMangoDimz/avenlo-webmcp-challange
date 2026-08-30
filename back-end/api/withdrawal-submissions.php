<?php
/**
 * Withdrawal Submissions API
 */

require_once __DIR__ . '/../controllers/WithdrawalVerificationSubmissionController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$submissionController = new WithdrawalVerificationSubmissionController();

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

AuthMiddleware::authenticate();
$isAdminUser = (AuthMiddleware::getCurrentUser()['type'] ?? '') === 'admin';
$guardAddressVerification = static function ($permissionKeys) use ($isAdminUser) {
    if ($isAdminUser) {
        AuthMiddleware::checkAnyPermission((array)$permissionKeys);
    }
};

try {
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    if ($path === '' || $path === null) {
        if ($method === 'GET') {
            $guardAddressVerification(['page_addressverification_readonly', 'page_addressverification_approve']);
            $submissionController->index();
        }
        if ($method === 'POST') {
            $submissionController->create();
        }
    } elseif ($path === 'pending') {
        if ($method === 'GET') {
            $guardAddressVerification(['page_addressverification_readonly', 'page_addressverification_assignreviewer']);
            $submissionController->getPending();
        }
    } elseif ($path === 'statistics') {
        if ($method === 'GET') {
            $guardAddressVerification(['page_addressverification_readonly', 'page_addressverification_approve']);
            $submissionController->statistics();
        }
    } elseif ($id && !$action) {
        if ($method === 'GET') {
            $guardAddressVerification(['page_clientsdetail_payment', 'page_addressverification_readonly', 'page_addressverification_approve']);
            $submissionController->show($id);
        }
    } elseif ($id && $action) {
        switch ($action) {
            case 'answers':
                if ($method === 'POST') {
                    $submissionController->saveAnswers($id);
                }
                break;
            case 'upload':
                if ($method === 'POST') {
                    $submissionController->uploadFile($id);
                }
                break;
            case 'resubmit-upload':
                if ($method === 'POST') {
                    $submissionController->uploadResubmitFile($id);
                }
                break;
            case 'sign-documents':
                if ($method === 'POST') {
                    $submissionController->signDocuments($id);
                }
                break;
            case 'submit':
                if ($method === 'POST') {
                    $submissionController->submit($id);
                }
                break;
            case 'hide':
                if ($method === 'POST') {
                    $submissionController->hide($id);
                }
                break;
            case 'approve':
                if ($method === 'POST') {
                    $guardAddressVerification('page_addressverification_approve');
                    $submissionController->approve($id);
                }
                break;
            case 'reject':
                if ($method === 'POST') {
                    $guardAddressVerification('page_addressverification_reject');
                    $submissionController->reject($id);
                }
                break;
            case 'need-docs':
                if ($method === 'POST') {
                    $guardAddressVerification('page_addressverification_needmoredocuments');
                    $submissionController->needDocs($id);
                }
                break;
            case 'assign':
                if ($method === 'POST') {
                    $guardAddressVerification('page_addressverification_assignreviewer');
                    $submissionController->assign($id);
                }
                break;
            case 'activities':
                if ($method === 'GET') {
                    $guardAddressVerification(['page_addressverification_readonly', 'page_addressverification_assignreviewer']);
                    $submissionController->getActivities($id);
                }
                break;
            case 'evaluate-rules':
                if ($method === 'POST') {
                    $submissionController->evaluateRules($id);
                }
                break;
            case 'resubmit-request':
                if ($method === 'GET') {
                    $guardAddressVerification(['page_addressverification_readonly', 'page_addressverification_needmoredocuments']);
                    $submissionController->getResubmitRequest($id);
                }
                break;
            case 'resubmit-answers':
                if ($method === 'GET') {
                    $guardAddressVerification(['page_addressverification_readonly', 'page_addressverification_needmoredocuments']);
                    $submissionController->getResubmitAnswers($id);
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
