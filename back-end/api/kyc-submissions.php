<?php
/**
 * KYC Submissions API 路由
 * 客户KYC提交和审核
 */

require_once __DIR__ . '/../controllers/KycSubmissionController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$submissionController = new KycSubmissionController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 检查认证（客户端或管理端）
AuthMiddleware::authenticate();
$isAdminUser = (AuthMiddleware::getCurrentUser()['type'] ?? '') === 'admin';
$guardAdminKycSubmission = static function ($permissionKeys) use ($isAdminUser) {
    if ($isAdminUser) {
        AuthMiddleware::checkAnyPermission((array)$permissionKeys);
    }
};

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    if (empty($path)) {
        // /api/kyc-submissions
        if ($method === 'GET') {
            // 管理员查看所有提交
            $guardAdminKycSubmission(['page_kyclist_readonly', 'page_kyclist_approve']);
            $submissionController->index();
        } elseif ($method === 'POST') {
            // 客户创建新提交
            $submissionController->create();
        }
    } elseif ($path === 'pending') {
        // /api/kyc-submissions/pending
        if ($method === 'GET') {
            $guardAdminKycSubmission(['page_kyclist_readonly', 'page_kyclist_assignreviewer']);
            $submissionController->getPending();
        }
    } elseif ($path === 'statistics') {
        // /api/kyc-submissions/statistics
        if ($method === 'GET') {
            $guardAdminKycSubmission(['page_kyclist_readonly', 'page_kyclist_approve']);
            $submissionController->statistics();
        }
    } elseif ($path === 'bulk-assign') {
        // /api/kyc-submissions/bulk-assign
        if ($method === 'POST') {
            $guardAdminKycSubmission('page_kyclist_assignreviewer');
            $submissionController->bulkAssign();
        }
    } elseif ($path === 'bulk-approve') {
        // /api/kyc-submissions/bulk-approve
        if ($method === 'POST') {
            $guardAdminKycSubmission('page_kyclist_approve');
            $submissionController->bulkApprove();
        }
    } elseif ($path === 'bulk-reject') {
        // /api/kyc-submissions/bulk-reject
        if ($method === 'POST') {
            $guardAdminKycSubmission('page_kyclist_reject');
            $submissionController->bulkReject();
        }
    } elseif ($id && !$action) {
        // /api/kyc-submissions/{id}
        if ($method === 'GET') {
            $guardAdminKycSubmission(['page_clientsdetail_profile', 'page_kyclist_readonly', 'page_kyclist_approve']);
            $submissionController->show($id);
        }
    } elseif ($id && $action) {
        // /api/kyc-submissions/{id}/{action}

        switch ($action) {
            case 'answers':
                // /api/kyc-submissions/{id}/answers
                if ($method === 'POST') {
                    $submissionController->saveAnswers($id);
                }
                break;

            case 'sign-documents':
                // /api/kyc-submissions/{id}/sign-documents
                if ($method === 'POST') {
                    $submissionController->signDocuments($id);
                }
                break;

            case 'submit':
                // /api/kyc-submissions/{id}/submit
                if ($method === 'POST') {
                    $submissionController->submit($id);
                }
                break;

            case 'approve':
                // /api/kyc-submissions/{id}/approve
                if ($method === 'POST') {
                    $guardAdminKycSubmission('page_kyclist_approve');
                    $submissionController->approve($id);
                }
                break;

            case 'reject':
                // /api/kyc-submissions/{id}/reject
                if ($method === 'POST') {
                    $guardAdminKycSubmission('page_kyclist_reject');
                    $submissionController->reject($id);
                }
                break;

            case 'need-docs':
                // /api/kyc-submissions/{id}/need-docs
                if ($method === 'POST') {
                    $guardAdminKycSubmission('page_kyclist_needmoredocuments');
                    $submissionController->needDocs($id);
                }
                break;

            case 'assign':
                // /api/kyc-submissions/{id}/assign
                if ($method === 'POST') {
                    $guardAdminKycSubmission('page_kyclist_assignreviewer');
                    $submissionController->assign($id);
                }
                break;

            case 'activities':
                // /api/kyc-submissions/{id}/activities
                if ($method === 'GET') {
                    $guardAdminKycSubmission(['page_kyclist_readonly', 'page_kyclist_assignreviewer']);
                    $submissionController->getActivities($id);
                }
                break;

            case 'evaluate-rules':
                // /api/kyc-submissions/{id}/evaluate-rules
                if ($method === 'POST') {
                    $submissionController->evaluateRules($id);
                }
                break;

            case 'resubmit-request':
                // /api/kyc-submissions/{id}/resubmit-request
                if ($method === 'GET') {
                    $guardAdminKycSubmission(['page_kyclist_readonly', 'page_kyclist_needmoredocuments']);
                    $submissionController->getResubmitRequest($id);
                }
                break;

            case 'resubmit-answers':
                // /api/kyc-submissions/{id}/resubmit-answers
                if ($method === 'GET') {
                    $guardAdminKycSubmission(['page_clientsdetail_profile', 'page_kyclist_readonly', 'page_kyclist_needmoredocuments']);
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
