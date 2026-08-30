<?php
/**
 * Withdrawals API 路由
 */

require_once __DIR__ . '/../controllers/WithdrawalController.php';
require_once __DIR__ . '/../controllers/WithdrawalTagController.php';
require_once __DIR__ . '/../controllers/OTPController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$withdrawalController = new WithdrawalController();
$tagController = new WithdrawalTagController();
$otpController = new OTPController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 预览模式（X-Preview-Token）可跳过 JWT 认证；否则需要认证
if (!ClientAuthContext::allowPreviewRequest()) {
    AuthMiddleware::authenticate();
}

$guardWithdrawal = static function ($permissionKeys) {
    AuthMiddleware::checkAnyPermission((array)$permissionKeys);
};
$isAdminUser = (AuthMiddleware::getCurrentUser()['type'] ?? '') === 'admin';

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;
    $subAction = $pathParts[2] ?? null;

    // 路由映射
    if (empty($path)) {
        // /api/withdrawals
        if ($method === 'GET') {
            $guardWithdrawal(['page_withdraw_readonly', 'page_withdraw_approve']);
            $withdrawalController->index();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'create') {
        // /api/withdrawals/create (客户端)
        if ($method === 'POST') {
            $withdrawalController->create();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'create/support-data') {
        // /api/withdrawals/create/support-data (客户端)
        if ($method === 'POST') {
            $withdrawalController->createSupportData();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'create/legacy') {
        // /api/withdrawals/create/legacy (客户端)
        if ($method === 'POST') {
            $withdrawalController->createLegacy();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'create/ibeepay') {
        // /api/withdrawals/create/ibeepay (客户端)
        if ($method === 'POST') {
            $withdrawalController->createIbeepay();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'statistics') {
        // /api/withdrawals/statistics
        if ($method === 'GET') {
            $guardWithdrawal(['page_withdraw_readonly', 'page_withdraw_approve']);
            $withdrawalController->statistics();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'my-withdrawals') {
        // /api/withdrawals/my-withdrawals (客户端)
        if ($method === 'GET') {
            $withdrawalController->myWithdrawals();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'rejection-reasons') {
        // /api/withdrawals/rejection-reasons
        if ($method === 'GET') {
            $guardWithdrawal(['page_withdraw_readonly', 'page_withdraw_reject']);
            $withdrawalController->getRejectionReasons();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'request-otp') {
        // /api/withdrawals/request-otp (客户端)
        if ($method === 'POST') {
            $otpController->requestWithdrawalOTP();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'verify-otp') {
        // /api/withdrawals/verify-otp (客户端)
        if ($method === 'POST') {
            $otpController->verifyWithdrawalOTP();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'otp-status') {
        // /api/withdrawals/otp-status (客户端)
        if ($method === 'GET') {
            $otpController->checkOTPStatus();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'bulk-approve') {
        // /api/withdrawals/bulk-approve
        if ($method === 'POST') {
            $guardWithdrawal('page_withdraw_approve');
            $withdrawalController->bulkApprove();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'bulk-add-tags') {
        // /api/withdrawals/bulk-add-tags
        if ($method === 'POST') {
            $guardWithdrawal('page_withdraw_tags');
            $withdrawalController->bulkAddTags();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'export') {
        // /api/withdrawals/export
        if ($method === 'POST') {
            $guardWithdrawal('page_withdraw_export');
            $withdrawalController->export();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && !$action) {
        // /api/withdrawals/{id}
        if ($method === 'GET') {
            $guardWithdrawal(['page_clientsdetail_funding', 'page_withdraw_readonly', 'page_withdraw_approve']);
            $withdrawalController->show($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id && $action) {
        // /api/withdrawals/{id}/{action}
        switch ($action) {
            case 'approve':
                // /api/withdrawals/{id}/approve
                if ($method === 'POST') {
                    $guardWithdrawal('page_withdraw_approve');
                    $withdrawalController->approve($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'reject':
                // /api/withdrawals/{id}/reject
                if ($method === 'POST') {
                    $guardWithdrawal('page_withdraw_reject');
                    $withdrawalController->reject($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'cancel':
                // /api/withdrawals/{id}/cancel
                if ($method === 'POST') {
                    $withdrawalController->cancel($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'complete':
                // /api/withdrawals/{id}/complete
                if ($method === 'POST') {
                    $guardWithdrawal('page_withdraw_approve');
                    $withdrawalController->complete($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'request-documents':
                // /api/withdrawals/{id}/request-documents
                if ($method === 'POST') {
                    $guardWithdrawal('page_withdraw_needmoredocuments');
                    $withdrawalController->requestDocuments($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'document-request':
                // /api/withdrawals/{id}/document-request (客户端和后台)
                if ($method === 'GET') {
                    if ($isAdminUser) {
                        $guardWithdrawal(['page_clientsdetail_funding', 'page_withdraw_readonly', 'page_withdraw_needmoredocuments']);
                    }
                    $withdrawalController->getDocumentRequest($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'upload-document':
                // /api/withdrawals/{id}/upload-document (客户端)
                if ($method === 'POST') {
                    $withdrawalController->uploadDocument($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'submit-documents':
                // /api/withdrawals/{id}/submit-documents (客户端)
                if ($method === 'POST') {
                    $withdrawalController->submitDocuments($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'send-email':
                // /api/withdrawals/{id}/send-email
                if ($method === 'POST') {
                    $guardWithdrawal('page_withdraw_contactclient');
                    $withdrawalController->sendEmail($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'history':
                // /api/withdrawals/{id}/history
                if ($method === 'GET') {
                    $guardWithdrawal(['page_clientsdetail_funding', 'page_withdraw_readonly', 'page_withdraw_approve']);
                    $withdrawalController->getHistory($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;

            case 'tags':
                // /api/withdrawals/{id}/tags
                if (!$subAction) {
                    if ($method === 'POST') {
                        $guardWithdrawal('page_withdraw_tags');
                        $withdrawalController->addTag($id);
                    } else {
                        Response::error('Method not allowed', 405);
                    }
                } else {
                    // /api/withdrawals/{id}/tags/{tagId}
                    if ($method === 'DELETE') {
                        $guardWithdrawal('page_withdraw_tags');
                        $withdrawalController->removeTag($id, $subAction);
                    } else {
                        Response::error('Method not allowed', 405);
                    }
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
