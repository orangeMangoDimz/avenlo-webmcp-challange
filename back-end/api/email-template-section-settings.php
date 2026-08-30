<?php
/**
 * 邮件模板板块设置 API 路由
 */

require_once __DIR__ . '/../controllers/EmailTemplateSectionSettingsController.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new EmailTemplateSectionSettingsController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

try {
    // 解析路径参数
    $pathParts = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
    $firstPart = $pathParts[0] ?? null;
    $secondPart = $pathParts[1] ?? null;
    $thirdPart = $pathParts[2] ?? null;

    if (empty($firstPart)) {
        // GET /api/email-template-section-settings (获取所有板块设置)
        if ($method === 'GET') {
            $controller->index();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($firstPart === 'batch') {
        // PUT /api/email-template-section-settings/batch (批量更新)
        if ($method === 'PUT' || $method === 'PATCH') {
            $controller->updateBatch();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif (!empty($firstPart) && $secondPart === 'templates') {
        // GET /api/email-template-section-settings/{sectionKey}/templates (获取板块的模板列表)
        $sectionKey = $firstPart;
        if ($method === 'GET') {
            $controller->getTemplates($sectionKey);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif (!empty($firstPart)) {
        // GET /api/email-template-section-settings/{sectionKey} (获取单个板块设置)
        // PUT /api/email-template-section-settings/{sectionKey} (更新单个板块设置)
        $sectionKey = $firstPart;

        if ($method === 'GET') {
            $controller->show($sectionKey);
        } elseif ($method === 'PUT' || $method === 'PATCH') {
            $controller->update($sectionKey);
        } else {
            Response::error('Method not allowed', 405);
        }
    } else {
        Response::error('Endpoint not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
