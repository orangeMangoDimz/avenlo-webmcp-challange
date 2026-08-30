<?php
/**
 * 法律文档签名记录API路由
 */

require_once __DIR__ . '/../controllers/LegalDocumentSignatureController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new LegalDocumentSignatureController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 所有路由都需要认证
AuthMiddleware::authenticate();

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;
    $subAction = $pathParts[2] ?? null;

    // 路由映射
    if (empty($path)) {
        // /api/legal-document-signatures
        if ($method === 'GET') {
            $controller->index();
        } elseif ($method === 'POST') {
            $controller->create();
        }
    } elseif ($path === 'trends') {
        // /api/legal-document-signatures/trends
        if ($method === 'GET') {
            $controller->getTrends();
        }
    } elseif ($id === 'lead' && $action) {
        // /api/legal-document-signatures/lead/{leadId}
        if ($method === 'GET') {
            $controller->getLeadSignatures($action);
        }
    } elseif ($id === 'document' && $action) {
        // /api/legal-document-signatures/document/{documentId}
        if ($method === 'GET') {
            $controller->getDocumentSignatures($action);
        }
    } elseif ($id === 'stats' && $action) {
        // /api/legal-document-signatures/stats/{documentType}
        if ($method === 'GET') {
            $controller->getDocumentStats($action);
        }
    } elseif ($id === 'check' && $action) {
        if (!$subAction) {
            // /api/legal-document-signatures/check/{leadId}
            Response::error('Document type is required', 400);
        } else {
            // /api/legal-document-signatures/check/{leadId}/{documentType}
            if ($method === 'GET') {
                $controller->checkSignature($action, $subAction);
            }
        }
    } elseif ($id === 'check-all' && $action) {
        // /api/legal-document-signatures/check-all/{leadId}
        if ($method === 'GET') {
            $controller->checkAllSignatures($action);
        }
    } elseif ($id && !$action) {
        // /api/legal-document-signatures/{id}
        if ($method === 'GET') {
            $controller->show($id);
        } elseif ($method === 'DELETE') {
            $controller->delete($id);
        }
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
