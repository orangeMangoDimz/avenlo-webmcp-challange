<?php
/**
 * 邮件模板管理 API 路由
 */

require_once __DIR__ . '/../controllers/EmailTemplateController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new EmailTemplateController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 所有接口都需要管理员认证
AuthMiddleware::authenticate();

try {
    // 解析路径参数
    $pathParts = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
    $firstPart = $pathParts[0] ?? null;
    $secondPart = $pathParts[1] ?? null;
    $thirdPart = $pathParts[2] ?? null;

    if (empty($firstPart)) {
        // GET /api/email-templates (获取模板列表)
        if ($method === 'GET') {
            $controller->index();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($firstPart === 'categories') {
        // GET /api/email-templates/categories (获取所有分类)
        if ($method === 'GET') {
            $controller->getCategories();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($firstPart === 'create') {
        // POST /api/email-templates/create (创建模板)
        if ($method === 'POST') {
            $controller->create();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($firstPart === 'modify' && is_numeric($secondPart)) {
        // PUT /api/email-templates/modify/{id} (更新模板)
        $id = (int)$secondPart;
        if ($method === 'PUT' || $method === 'PATCH') {
            $controller->update($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($firstPart === 'remove' && is_numeric($secondPart)) {
        // DELETE /api/email-templates/remove/{id} (删除模板)
        $id = (int)$secondPart;
        if ($method === 'DELETE') {
            $controller->delete($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($firstPart === 'key' && !empty($secondPart)) {
        // GET /api/email-templates/key/{key} (根据key获取模板)
        if ($method === 'GET') {
            $controller->getByKey($secondPart);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif (is_numeric($firstPart)) {
        $id = (int)$firstPart;

        if ($secondPart === 'toggle-active') {
            // GET /api/email-templates/{id}/toggle-active (切换启用状态)
            if ($method === 'GET') {
                $controller->toggleActive($id);
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif (empty($secondPart)) {
            // GET /api/email-templates/{id} (获取单个模板)
            if ($method === 'GET') {
                $controller->show($id);
            } else {
                Response::error('Method not allowed', 405);
            }
        } else {
            Response::error('Endpoint not found', 404);
        }
    } else {
        Response::error('Endpoint not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
