<?php
/**
 * Deposits API 路由
 */

require_once __DIR__ . '/../controllers/DepositController.php';
require_once __DIR__ . '/../controllers/DepositTagController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$depositController = new DepositController();
$tagController = new DepositTagController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 预览模式（X-Preview-Token）可跳过 JWT 认证；否则需要认证
if (!ClientAuthContext::allowPreviewRequest()) {
    AuthMiddleware::authenticate();
}

$guardDeposit = static function ($permissionKeys) {
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
        // /api/deposits
        if ($method === 'GET') {
            $guardDeposit(['page_deposit_readonly', 'page_deposit_approve']);
            $depositController->index();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'create') {
        if ($method === 'POST') {
            $depositController->create();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'statistics') {
        // /api/deposits/statistics
        if ($method === 'GET') {
            $guardDeposit(['page_deposit_readonly', 'page_deposit_approve']);
            $depositController->statistics();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'my-deposits') {
        // /api/deposits/my-deposits (客户端)
        if ($method === 'GET') {
            $depositController->myDeposits();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'available-balance') {
        // /api/deposits/available-balance (客户端)
        if ($method === 'GET') {
            $depositController->getAvailableBalance();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'rejection-reasons') {
        // /api/deposits/rejection-reasons
        if ($method === 'GET') {
            $guardDeposit(['page_deposit_readonly', 'page_deposit_reject']);
            $depositController->getRejectionReasons();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'bulk-approve') {
        // /api/deposits/bulk-approve
        if ($method === 'POST') {
            $guardDeposit('page_deposit_approve');
            $depositController->bulkApprove();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'bulk-add-tags') {
        // /api/deposits/bulk-add-tags
        if ($method === 'POST') {
            $guardDeposit('page_deposit_tags');
            $depositController->bulkAddTags();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'export') {
        // /api/deposits/export
        if ($method === 'POST') {
            $guardDeposit('page_deposit_export');
            $depositController->export();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && !$action) {
        // /api/deposits/{id}
        if ($method === 'GET') {
            $guardDeposit(['page_clientsdetail_funding', 'page_deposit_readonly', 'page_deposit_approve']);
            $depositController->show($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && $action) {
        // /api/deposits/{id}/{action}
        switch ($action) {
            case 'approve':
                // /api/deposits/{id}/approve
                if ($method === 'POST') {
                    $guardDeposit('page_deposit_approve');
                    $depositController->approve($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'reject':
                // /api/deposits/{id}/reject
                if ($method === 'POST') {
                    $guardDeposit('page_deposit_reject');
                    $depositController->reject($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'cancel':
                // /api/deposits/{id}/cancel
                if ($method === 'POST') {
                    $depositController->cancel($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'history':
                // /api/deposits/{id}/history
                if ($method === 'GET') {
                    $guardDeposit(['page_clientsdetail_funding', 'page_deposit_readonly', 'page_deposit_approve']);
                    $depositController->getHistory($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'notes':
                // /api/deposits/{id}/notes
                if ($method === 'GET') {
                    $guardDeposit(['page_clientsdetail_funding', 'page_deposit_readonly', 'page_deposit_addnote']);
                    $depositController->getNotes($id);
                } elseif ($method === 'POST') {
                    $guardDeposit('page_deposit_addnote');
                    $depositController->addNote($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'tags':
                // /api/deposits/{id}/tags
                if (!$subAction) {
                    if ($method === 'POST') {
                        $guardDeposit('page_deposit_tags');
                        $depositController->addTag($id);
                    } else {
                        Response::error('Method not allowed', 405);
                    }
                } else {
                    // /api/deposits/{id}/tags/{tagId}
                    if ($method === 'DELETE') {
                        $guardDeposit('page_deposit_tags');
                        $depositController->removeTag($id, $subAction);
                    } else {
                        Response::error('Method not allowed', 405);
                    }
                }
                break;

            case 'send-email':
                // /api/deposits/{id}/send-email
                if ($method === 'POST') {
                    $guardDeposit('page_deposit_contactclient');
                    $depositController->sendEmail($id);
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
