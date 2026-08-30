<?php
/**
 * IB Commission Rules API 路由
 * IB佣金规则管理接口
 */

require_once __DIR__ . '/../controllers/IbCommissionRuleController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$ibRuleController = new IbCommissionRuleController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 所有路由都需要认证
AuthMiddleware::authenticate();
$guardIbRule = static function ($permissionKeys) {
    AuthMiddleware::checkAnyPermission((array)$permissionKeys);
};

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;
    $subAction = $pathParts[2] ?? null;

    // 路由映射
    if (empty($path)) {
        // /api/ib-rules
        if ($method === 'GET') {
            $ibRuleController->index();
        } elseif ($method === 'POST') {
            $guardIbRule('page_ib_settings_create_rule');
            $ibRuleController->create();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'active') {
        // /api/ib-rules/active
        if ($method === 'GET') {
            $ibRuleController->getActiveRules();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'active-by-tier') {
        // /api/ib-rules/active-by-tier?tier_level_id=
        if ($method === 'GET') {
            $ibRuleController->getActiveRulesByTierLevel();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'additional-rules' && $action && $subAction === 'tiers') {
        // /api/ib-rules/additional-rules/{additionalRuleId}/tiers
        if ($method === 'PUT') {
            $guardIbRule('page_ib_settings_edit_rule');
            $ibRuleController->updateTiers($action); // $action is additionalRuleId
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && !$action) {
        // /api/ib-rules/{id}
        if ($method === 'GET') {
            $ibRuleController->show($id);
        } elseif ($method === 'PUT') {
            $guardIbRule('page_ib_settings_edit_rule');
            $ibRuleController->update($id);
        } elseif ($method === 'DELETE') {
            $ibRuleController->delete($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && $action) {
        // /api/ib-rules/{id}/{action}
        switch ($action) {
            case 'duplicate':
                // /api/ib-rules/{id}/duplicate
                if ($method === 'POST') {
                    $guardIbRule('page_ib_settings_create_rule');
                    $ibRuleController->duplicate($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'products':
                // /api/ib-rules/{id}/products
                if ($method === 'PUT') {
                    $guardIbRule('page_ib_settings_edit_rule');
                    $ibRuleController->updateProducts($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'additional-rules':
                // /api/ib-rules/{id}/additional-rules
                if ($method === 'PUT') {
                    $guardIbRule('page_ib_settings_edit_rule');
                    $ibRuleController->updateAdditionalRules($id);
                } else {
                    Response::error('Method not allowed', 405);
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
