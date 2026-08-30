<?php
/**
 * 法律文档管理API路由
 */

require_once __DIR__ . '/../controllers/LegalDocumentController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new LegalDocumentController();
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
        // /api/legal-documents
        if ($method === 'GET') {
            $controller->index();
        } elseif ($method === 'POST') {
            $controller->create();
        }
    } elseif ($path === 'reorder') {
        // /api/legal-documents/reorder
        if ($method === 'POST') {
            $controller->reorder();
        }
    } elseif ($id === 'type' && $action) {
        // /api/legal-documents/type/{documentType}
        if (!$subAction) {
            // /api/legal-documents/type/{documentType}
            if ($method === 'GET') {
                $controller->getByType($action);
            }
        } elseif ($subAction === 'languages') {
            // /api/legal-documents/type/{documentType}/languages
            if ($method === 'GET') {
                $controller->getAvailableLanguages($action);
            }
        } else {
            Response::error('Route not found', 404);
        }
    } elseif ($id && !$action) {
        // /api/legal-documents/{id}
        if ($method === 'GET') {
            $controller->show($id);
        } elseif ($method === 'PUT') {
            $controller->update($id);
        } elseif ($method === 'DELETE') {
            $controller->delete($id);
        }
    } elseif ($id && $action) {
        // /api/legal-documents/{id}/{action}
        switch ($action) {
            case 'toggle':
                // /api/legal-documents/{id}/toggle
                if ($method === 'POST') {
                    $controller->toggleActive($id);
                }
                break;
            case 'publish':
                // /api/legal-documents/{id}/publish
                if ($method === 'POST') {
                    $controller->publishNewVersion($id);
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
