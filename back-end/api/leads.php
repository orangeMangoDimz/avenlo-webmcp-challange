<?php
/**
 * Leads管理API路由
 */

require_once __DIR__ . '/../controllers/LeadController.php';
require_once __DIR__ . '/../controllers/LeadTagController.php';
require_once __DIR__ . '/../controllers/LeadKycController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$leadController = new LeadController();
$tagController = new LeadTagController();
$kycController = new LeadKycController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 所有路由都需要认证
AuthMiddleware::authenticate();

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    // 路由映射
    if (empty($path)) {
        // /api/leads
        if ($method === 'GET') {
            $leadController->index();
        } elseif ($method === 'POST') {
            // 导出功能不需要特殊权限检查
            if (isset($_GET['action']) && $_GET['action'] === 'export') {
                $leadController->export();
            } else {
                Response::error('Method not allowed', 405);
            }
        }
    } elseif ($path === 'statistics') {
        // /api/leads/statistics
        if ($method === 'GET') {
            $leadController->statistics();
        }
    } elseif ($path === 'export') {
        // /api/leads/export
        if ($method === 'POST') {
            $leadController->export();
        }
    } elseif ($id && !$action) {
        // /api/leads/{id}
        if ($method === 'GET') {
            $leadController->show($id);
        } elseif ($method === 'PUT') {
            $leadController->update($id);
        }
    } elseif ($id && $action) {
        // /api/leads/{id}/{action}
        $subAction = $pathParts[2] ?? null;

        switch ($action) {
            case 'status':
                if ($method === 'POST' || $method === 'PUT') {
                    AuthMiddleware::checkAnyPermission(['page_leads_edit']);
                    $leadController->updateStatus($id);
                }
                break;
            case 'reset-password':
                if ($method === 'POST') {
                    AuthMiddleware::checkAnyPermission(['page_leads_password_reset']);
                    $leadController->resetPassword($id);
                }
                break;
            case 'send-password-reset':
                if ($method === 'POST') {
                    AuthMiddleware::checkAnyPermission(['page_leads_password_reset']);
                    $leadController->sendPasswordReset($id);
                }
                break;
            case 'activities':
                if ($method === 'GET') {
                    $leadController->getActivities($id);
                }
                break;
            case 'edit-history':
                if ($method === 'GET') {
                    $leadController->getEditHistory($id);
                }
                break;
            case 'status-history':
                if ($method === 'GET') {
                    $leadController->getStatusHistory($id);
                }
                break;
            // 标签相关路由
            case 'tags':
                if (!$subAction) {
                    // /api/leads/{id}/tags
                    if ($method === 'GET') {
                        $tagController->getLeadTags($id);
                    } elseif ($method === 'POST') {
                        $tagController->assignToLead($id);
                    }
                } else {
                    // /api/leads/{id}/tags/{tagId}
                    if ($method === 'DELETE') {
                        $tagController->removeFromLead($id, $subAction);
                    }
                }
                break;
            // KYC相关路由
            case 'kyc':
                if (!$subAction) {
                    // /api/leads/{id}/kyc
                    if ($method === 'GET') {
                        $kycController->show($id);
                    } elseif ($method === 'PUT') {
                        $kycController->update($id);
                    }
                } else {
                    // /api/leads/{id}/kyc/{action}
                    switch ($subAction) {
                        case 'approve':
                            if ($method === 'POST') {
                                $kycController->approve($id);
                            }
                            break;
                        case 'reject':
                            if ($method === 'POST') {
                                $kycController->reject($id);
                            }
                            break;
                        case 'document-submitted':
                            if ($method === 'POST') {
                                $kycController->markDocumentSubmitted($id);
                            }
                            break;
                    }
                }
                break;
            case 'preview-token':
                if ($method === 'POST') {
                    $leadController->createPreviewToken($id);
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
