<?php
/**
 * IB Applications API 路由
 * IB申请管理接口
 */

require_once __DIR__ . '/../controllers/IbApplicationController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$ibApplicationController = new IbApplicationController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 预览模式（X-Preview-Token）可跳过 JWT 认证；否则需要认证
if (!ClientAuthContext::allowPreviewRequest()) {
    AuthMiddleware::authenticate();
}

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;
    $subAction = $pathParts[2] ?? null;

    // 路由映射
    if (empty($path)) {
        // /api/ib-applications
        if ($method === 'GET') {
            $ibApplicationController->index();
        } elseif ($method === 'POST') {
            $ibApplicationController->create();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'agree') {
        // /api/ib-applications/agree (客户端同意成为IB)
        if ($method === 'POST') {
            $ibApplicationController->agree();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'agree-multiple') {
        // /api/ib-applications/agree-multiple (允许同一账号创建多个IB)
        if ($method === 'POST') {
            $ibApplicationController->agreeMultiple();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'statistics') {
        // /api/ib-applications/statistics
        if ($method === 'GET') {
            $ibApplicationController->statistics();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'bulk-assign-reviewer') {
        // /api/ib-applications/bulk-assign-reviewer
        if ($method === 'POST') {
            $ibApplicationController->bulkAssignReviewer();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'bulk-approve') {
        // /api/ib-applications/bulk-approve
        if ($method === 'POST') {
            $ibApplicationController->bulkApprove();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'export') {
        // /api/ib-applications/export
        if ($method === 'POST') {
            $ibApplicationController->export();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && !$action) {
        // /api/ib-applications/{id}
        if ($method === 'GET') {
            $ibApplicationController->show($id);
        } elseif ($method === 'PUT') {
            $ibApplicationController->update($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && $action) {
        // /api/ib-applications/{id}/{action}
        switch ($action) {
            case 'approve':
                // /api/ib-applications/{id}/approve
                if ($method === 'POST') {
                    $ibApplicationController->approve($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'reject':
                // /api/ib-applications/{id}/reject
                if ($method === 'POST') {
                    $ibApplicationController->reject($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'request-info':
                // /api/ib-applications/{id}/request-info
                if ($method === 'POST') {
                    $ibApplicationController->requestMoreInfo($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'assign-reviewer':
                // /api/ib-applications/{id}/assign-reviewer
                if ($method === 'POST') {
                    $ibApplicationController->assignReviewer($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'assign-tier':
                // /api/ib-applications/{id}/assign-tier
                if ($method === 'POST') {
                    $ibApplicationController->assignTier($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'assign-rule':
                // /api/ib-applications/{id}/assign-rule
                if ($method === 'POST') {
                    $ibApplicationController->assignRule($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'rules':
                // /api/ib-applications/{id}/rules/{ruleId}
                if ($method === 'DELETE' && $subAction) {
                    $ibApplicationController->removeRule($id, $subAction);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'activities':
                // /api/ib-applications/{id}/activities
                if ($method === 'GET') {
                    $ibApplicationController->getActivities($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'kyc-data':
                // /api/ib-applications/{id}/kyc-data
                if ($method === 'GET') {
                    $ibApplicationController->getKycData($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'custom-rules':
                // /api/ib-applications/{id}/custom-rules
                if ($method === 'GET') {
                    $ibApplicationController->getCustomRules($id);
                } elseif ($method === 'POST') {
                    $ibApplicationController->saveCustomRules($id);
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
