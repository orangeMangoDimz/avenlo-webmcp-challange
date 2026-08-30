<?php
/**
 * Internal Transfers API 路由
 */

require_once __DIR__ . '/../controllers/InternalTransferController.php';
require_once __DIR__ . '/../controllers/TransactionSearchTagController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new InternalTransferController();
$searchTagController = new TransactionSearchTagController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 预览模式（X-Preview-Token）可跳过 JWT 认证；否则需要认证
if (!ClientAuthContext::allowPreviewRequest()) {
    AuthMiddleware::authenticate();
}

$guardInternalTransfer = static function ($permissionKeys) {
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
        // /api/internal-transfers
        if ($method === 'GET') {
            $guardInternalTransfer(['page_internaltransfer_readonly', 'page_internaltransfer_approve']);
            $controller->index();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'create') {
        // /api/internal-transfers/create
        if ($method === 'POST') {
            $controller->create();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'my-transfers') {
        // /api/internal-transfers/my-transfers (客户端)
        if ($method === 'GET') {
            $controller->myTransfers();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'statistics') {
        // /api/internal-transfers/statistics
        if ($method === 'GET') {
            $guardInternalTransfer(['page_internaltransfer_readonly', 'page_internaltransfer_approve']);
            $controller->statistics();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'rejection-reasons') {
        // /api/internal-transfers/rejection-reasons
        if ($method === 'GET') {
            $guardInternalTransfer(['page_internaltransfer_readonly', 'page_internaltransfer_reject']);
            $controller->getRejectionReasons();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'bulk-approve') {
        // /api/internal-transfers/bulk-approve
        if ($method === 'POST') {
            $guardInternalTransfer('page_internaltransfer_approve');
            $controller->bulkApprove();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'bulk-add-tags') {
        // /api/internal-transfers/bulk-add-tags
        if ($method === 'POST') {
            $guardInternalTransfer('page_internaltransfer_tags');
            $controller->bulkAddTags();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'export') {
        // /api/internal-transfers/export
        if ($method === 'POST') {
            $guardInternalTransfer('page_internaltransfer_export');
            $controller->export();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'search-tags') {
        // /api/internal-transfers/search-tags
        if ($method === 'GET') {
            $guardInternalTransfer(['page_internaltransfer_readonly', 'page_internaltransfer_tags']);
            $searchTagController->index();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'create-search-tags') {
        // /api/internal-transfers/create-search-tags
        if ($method === 'POST') {
            $guardInternalTransfer('page_internaltransfer_tags');
            $searchTagController->create();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif (preg_match('/^delete-search-tags\/(\d+)$/', $path, $matches)) {
        // /api/internal-transfers/delete-search-tags/{id}
        $tagId = $matches[1];
        if ($method === 'DELETE') {
            $guardInternalTransfer('page_internaltransfer_tags');
            $searchTagController->delete($tagId);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && !$action) {
        // /api/internal-transfers/{id}
        if ($method === 'GET') {
            $guardInternalTransfer(['page_clientsdetail_funding', 'page_internaltransfer_readonly', 'page_internaltransfer_approve']);
            $controller->show($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && $action) {
        // /api/internal-transfers/{id}/{action}
        switch ($action) {
            case 'approve':
                // /api/internal-transfers/{id}/approve
                if ($method === 'POST') {
                    $guardInternalTransfer('page_internaltransfer_approve');
                    $controller->approve($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'reject':
                // /api/internal-transfers/{id}/reject
                if ($method === 'POST') {
                    $guardInternalTransfer('page_internaltransfer_reject');
                    $controller->reject($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'cancel':
                // /api/internal-transfers/{id}/cancel
                if ($method === 'POST') {
                    $controller->cancel($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'history':
                // /api/internal-transfers/{id}/history
                if ($method === 'GET') {
                    $guardInternalTransfer(['page_clientsdetail_funding', 'page_internaltransfer_readonly', 'page_internaltransfer_approve']);
                    $controller->getHistory($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'notes':
                // /api/internal-transfers/{id}/notes
                if ($method === 'GET') {
                    $guardInternalTransfer(['page_clientsdetail_funding', 'page_internaltransfer_readonly', 'page_internaltransfer_addnote']);
                    $controller->getNotes($id);
                } elseif ($method === 'POST') {
                    $guardInternalTransfer('page_internaltransfer_addnote');
                    $controller->addNote($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'tags':
                // /api/internal-transfers/{id}/tags
                if (!$subAction) {
                    if ($method === 'POST') {
                        $guardInternalTransfer('page_internaltransfer_tags');
                        $controller->addTag($id);
                    } else {
                        Response::error('Method not allowed', 405);
                    }
                } else {
                    // /api/internal-transfers/{id}/tags/{tagId}
                    if ($method === 'DELETE') {
                        $guardInternalTransfer('page_internaltransfer_tags');
                        $controller->removeTag($id, $subAction);
                    } else {
                        Response::error('Method not allowed', 405);
                    }
                }
                break;

            case 'send-email':
                // /api/internal-transfers/{id}/send-email
                if ($method === 'POST') {
                    $guardInternalTransfer('page_internaltransfer_contactclient');
                    $controller->sendEmail($id);
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
