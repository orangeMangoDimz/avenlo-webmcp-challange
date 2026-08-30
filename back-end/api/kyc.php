<?php
/**
 * Client KYC API 路由
 * 客户端 KYC 验证和提交
 */

require_once __DIR__ . '/../controllers/LeadKycController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$kycController = new LeadKycController();

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));

    $action = $pathParts[0] ?? null;
    $id = $pathParts[1] ?? null;
    $subAction = $pathParts[2] ?? null;

    // ============================================================
    // 客户端路由（需要客户端认证）
    // ============================================================

    // 所有路由都需要客户端认证
    // TODO: 实现客户端认证中间件
    // AuthMiddleware::authenticateClient();

    if ($action === 'client-template' && $method === 'GET') {
        // GET /api/kyc/client-template
        $kycController->getClientTemplate();
    } elseif ($action === 'client-status' && $method === 'GET') {
        // GET /api/kyc/client-status
        $kycController->getClientStatus();
    } elseif ($action === 'client-start' && $method === 'POST') {
        // POST /api/kyc/client-start
        $kycController->startKycProcess();
    } elseif ($action === 'client-restart' && $method === 'POST') {
        // POST /api/kyc/client-restart
        $kycController->restartKycProcess();
    }
    elseif ($action === 'templates' && $id === 'details' && $method === 'GET') {
        // This won't work, need to fix routing
        Response::error('Invalid route', 400);
    }
    elseif ($action === 'templates' && $id && $subAction === 'details' && $method === 'GET') {
        // GET /api/kyc/templates/{id}/details
        $kycController->getTemplateDetails($id);
    }
    elseif ($action === 'submissions' && !$id && $method === 'POST') {
        // POST /api/kyc/submissions
        $kycController->createSubmission();
    }
    elseif ($action === 'submissions' && $id && $subAction === 'answers' && $method === 'PUT') {
        // PUT /api/kyc/submissions/{id}/answers
        $kycController->saveAnswers($id);
    }
    elseif ($action === 'submissions' && $id && $subAction === 'answers' && $method === 'DELETE') {
        // DELETE /api/kyc/submissions/{id}/answers
        $kycController->deleteAnswers($id);
    }
    elseif ($action === 'submissions' && $id && $subAction === 'upload' && $method === 'POST') {
        // POST /api/kyc/submissions/{id}/upload
        $kycController->uploadFile($id);
    }
    elseif ($action === 'submissions' && $id && $subAction === 'sign-documents' && $method === 'POST') {
        // POST /api/kyc/submissions/{id}/sign-documents
        $kycController->signDocuments($id);
    }
    elseif ($action === 'submissions' && $id && $subAction === 'submit' && $method === 'POST') {
        // POST /api/kyc/submissions/{id}/submit
        $kycController->submitApplication($id);
    }
    elseif ($action === 'submissions' && $id && $subAction === 'resubmit-answers' && $method === 'POST') {
        // POST /api/kyc/submissions/{id}/resubmit-answers
        $kycController->submitResubmitAnswers($id);
    }
    elseif ($action === 'submissions' && $id && $subAction === 'resubmit-request' && $method === 'GET') {
        // GET /api/kyc/submissions/{id}/resubmit-request
        $kycController->getResubmitRequest($id);
    }
    elseif ($action === 'submissions' && $id && $subAction === 'resubmit-upload' && $method === 'POST') {
        // POST /api/kyc/submissions/{id}/resubmit-upload
        $kycController->uploadResubmitFile($id);
    }
    elseif ($action === 'submissions' && $id && $subAction === 'next-question' && $method === 'GET') {
        // GET /api/kyc/submissions/{id}/next-question
        error_log("KYC API - Entering getNextQuestion route for submission ID: $id");
        $kycController->getNextQuestion($id);
    }
    elseif ($action === 'submissions' && $id && $subAction === 'progress' && $method === 'GET') {
        // GET /api/kyc/submissions/{id}/progress
        $kycController->getSubmissionProgress($id);
    }
    elseif ($action === 'submissions' && $id && $subAction === 'evaluate-rules' && $method === 'POST') {
        // POST /api/kyc/submissions/{id}/evaluate-rules
        $kycController->evaluateRules($id);
    }
    elseif ($action === 'submissions' && $id && !$subAction && $method === 'GET') {
        // GET /api/kyc/submissions/{id} - 必须放在最后，因为它的条件最宽泛
        error_log("KYC API - Entering getSubmissionDetails route for submission ID: $id");
        $kycController->getSubmissionDetails($id);
    }
    elseif ($action === 'my-submission' && $method === 'GET') {
        // GET /api/kyc/my-submission
        $kycController->getMySubmission();
    }
    elseif ($action === 'external-launch' && $method === 'GET') {
        // GET /api/kyc/external-launch  第三方 KYC 启动信息（accessToken 等）
        $kycController->getExternalLaunch();
    }
    elseif ($action === 'my-signed-documents' && $method === 'GET') {
        // GET /api/kyc/my-signed-documents
        $kycController->getMySignedDocuments();
    }
    else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
