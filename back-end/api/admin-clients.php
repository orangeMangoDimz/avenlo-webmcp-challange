<?php
/**
 * 管理员客户管理 API 路由
 * 专门用于后台管理已验证的客户
 */

require_once __DIR__ . '/../controllers/ClientManagementController.php';
require_once __DIR__ . '/../controllers/ClientCommissionReportController.php';
require_once __DIR__ . '/../controllers/TradingAccountController.php';
require_once __DIR__ . '/../controllers/LoginPageSettingsController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new ClientManagementController();
$commissionReportController = new ClientCommissionReportController();
$tradingAccountController = new TradingAccountController();
$loginSettingsController = new LoginPageSettingsController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 检查认证（仅管理员）
AuthMiddleware::authenticate();
$guardClientsList = static function ($permissionKeys) {
    AuthMiddleware::checkAnyPermission((array)$permissionKeys);
};
$guardClientDetail = static function ($permissionKeys) {
    AuthMiddleware::checkAnyPermission((array)$permissionKeys);
};
$guardClientSales = static function () {
    AuthMiddleware::checkAnyPermission(['page_clientsdetail_sales', 'page_clientslist_assign']);
};
// GET assignable-sales：只读即可拉取 Sales 参考数据；写操作仍走 $guardClientSales
$guardAssignableSalesRead = static function () {
    AuthMiddleware::checkAnyPermission([
        'page_leads_readonly',
        'page_clientslist_readonly',
        'page_clientslist_assign',
        'page_leads_assign',
        'page_clientsdetail_sales',
    ]);
};
$guardClientTransactions = static function () {
    $type = strtolower(trim((string)($_GET['type'] ?? 'all')));
    $typeAliases = [
        'deposits' => 'deposit',
        'withdrawals' => 'withdrawal',
        'internal-transfer' => 'internal_transfer',
        'internal-transfers' => 'internal_transfer',
        'internaltransfer' => 'internal_transfer',
        'internaltransfers' => 'internal_transfer'
    ];
    $type = $typeAliases[$type] ?? $type;

    $permissionMap = [
        'deposit' => 'page_clientsdetail_funding',
        'withdrawal' => 'page_clientsdetail_funding',
        'internal_transfer' => 'page_clientsdetail_funding'
    ];

    if ($type === 'all') {
        AuthMiddleware::checkPermission('page_clientsdetail_funding');
        return;
    }

    if (isset($permissionMap[$type])) {
        AuthMiddleware::checkPermission($permissionMap[$type]);
        return;
    }

    AuthMiddleware::checkAnyPermission(array_values($permissionMap));
};
$guardWithdrawTemplatePayments = static function () {
    AuthMiddleware::checkAnyPermission(['page_clientsdetail_payment']);
};
$guardIbReport = static function () {
    AuthMiddleware::checkAnyPermission(['page_ibreport_readonly', 'page_ibreport']);
};
$guardTradingAccountCreate = static function () {
    AuthMiddleware::checkPermission('client_tradding_create');
};

try {
    // 解析路径参数（此时 $path 为去除 admin-clients 前缀后的子路径）
    $pathParts = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    if (empty($id)) {
        // /api/admin-clients
        if ($method === 'GET') {
            $controller->index();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($id === 'create') {
        if ($method === 'POST') {
            $controller->create();
        }
    } elseif ($id === 'options') {
        if ($method === 'GET') {
            $controller->getCreateOptions();
        }
    } elseif ($id === 'assignable-sales') {
        if ($method === 'GET') {
            $guardAssignableSalesRead();
            $controller->getAssignableSales();
        }
    } elseif ($id === 'statistics') {
        // /api/admin-clients/statistics
        if ($method === 'GET') {
            $controller->statistics();
        }
    } elseif ($id === 'for-ib-selection') {
        // /api/admin-clients/for-ib-selection
        if ($method === 'GET') {
            $controller->getClientsForIbSelection();
        }
    } elseif ($id === 'trading-platforms') {
        // /api/admin-clients/trading-platforms
        if ($method === 'GET') {
            $guardTradingAccountCreate();
            $tradingAccountController->getAdminPlatforms();
        }
    } elseif ($id === 'ib') {
        // /api/admin-clients/ib?id={ibPartnerId}
        if ($method === 'GET') {
            $guardClientDetail('page_clientsdetail_ib');
            $controller->getIbAllListDetail();
        }
    } elseif ($id === 'ib-network-clients') {
        $guardClientDetail('page_clientsdetail_ib');
        if ($action === null) {
            if ($method !== 'GET') {
                Response::error('Method not allowed', 405);
            }
            $controller->getIbNetworkClients();
        } elseif ($action === 'export') {
            if ($method !== 'POST') {
                Response::error('Method not allowed', 405);
            }
            $controller->exportIbNetworkClients();
        } elseif ($action === 'export-active') {
            if ($method !== 'GET') {
                Response::error('Method not allowed', 405);
            }
            $controller->exportIbNetworkClientsActive();
        } elseif ($action === 'export-status') {
            if ($method !== 'GET') {
                Response::error('Method not allowed', 405);
            }
            $controller->exportIbNetworkClientsStatus();
        } elseif ($action === 'export-cancel') {
            if ($method !== 'POST') {
                Response::error('Method not allowed', 405);
            }
            $controller->exportIbNetworkClientsCancel();
        } elseif ($action === 'export-download') {
            if ($method !== 'GET') {
                Response::error('Method not allowed', 405);
            }
            $controller->exportIbNetworkClientsDownload();
        } else {
            Response::error('Route not found', 404);
        }
    } elseif ($id === 'commission-report') {
        $guardIbReport();
        $ibPartnerId = isset($_GET['ibPartnerId']) ? (int)$_GET['ibPartnerId'] : 0;

        if ($action === 'statistics' || $action === 'list' || $action === 'detail') {
            if ($method !== 'GET') {
                Response::error('Method not allowed', 405);
            }
            if ($action === 'statistics') {
                $commissionReportController->adminStatistics($ibPartnerId);
            } elseif ($action === 'list') {
                $commissionReportController->adminList($ibPartnerId);
            } else {
                $commissionReportController->adminDetail($ibPartnerId);
            }
        } elseif ($action === 'export-detail') {
            if ($method !== 'POST') {
                Response::error('Method not allowed', 405);
            }
            $commissionReportController->adminExportDetail($ibPartnerId);
        } elseif ($action === 'export-detail-active') {
            if ($method !== 'GET') {
                Response::error('Method not allowed', 405);
            }
            $commissionReportController->adminExportDetailActive();
        } elseif ($action === 'export-detail-status') {
            if ($method !== 'GET') {
                Response::error('Method not allowed', 405);
            }
            $commissionReportController->adminExportDetailStatus();
        } elseif ($action === 'export-detail-cancel') {
            if ($method !== 'POST') {
                Response::error('Method not allowed', 405);
            }
            $commissionReportController->adminExportDetailCancel();
        } elseif ($action === 'export-detail-download') {
            if ($method !== 'GET') {
                Response::error('Method not allowed', 405);
            }
            $commissionReportController->adminExportDetailDownload();
        } else {
            Response::error('Route not found', 404);
        }
    } elseif ($id === 'withdraw-template') {
        // /api/admin-clients/withdraw-template?clientId={id}
        if ($method === 'GET') {
            $guardWithdrawTemplatePayments();
            $controller->getWithdrawTemplatePayments();
        }
    } elseif ($id === 'detail') {
        // /api/admin-clients/detail&id={id}
        if ($method === 'GET') {
            $guardClientDetail('page_clientsdetail_profile');
            $controller->detail();
        }
    } elseif ($id === 'bulk-assign') {
        // /api/admin-clients/bulk-assign
        if ($method === 'POST') {
            $guardClientSales();
            $controller->bulkAssign();
        }
    } elseif ($id === 'bulk-tags') {
        // /api/admin-clients/bulk-tags
        if ($method === 'POST') {
            $controller->bulkAddTags();
        }
    } elseif ($id === 'bulk-delete') {
        // /api/admin-clients/bulk-delete
        if ($method === 'POST') {
            $controller->bulkDelete();
        }
    } elseif (preg_match('/^export\/(.+)$/', $id, $matches)) {
        // /api/admin-clients/export/{format}
        if ($method === 'GET') {
            $format = $matches[1];
            $controller->export($format);
        }
    } elseif (is_numeric($id) && !$action) {
        // /api/admin-clients/{id}
        if ($method === 'GET') {
            $controller->show($id);
        } elseif ($method === 'PUT') {
            $controller->update($id);
        } elseif ($method === 'DELETE') {
            $controller->delete($id);
        }
    } elseif (is_numeric($id) && $action) {
        // /api/admin-clients/{id}/{action}

        switch ($action) {
            case 'activities':
                // /api/admin-clients/{id}/activities
                if ($method === 'GET') {
                    $controller->getActivities($id);
                }
                break;

            case 'kyc-history':
                // /api/admin-clients/{id}/kyc-history
                if ($method === 'GET') {
                    $controller->getKycHistory($id);
                }
                break;

            case 'documents':
                // /api/admin-clients/{id}/documents
                if ($method === 'GET') {
                    $guardClientDetail('page_clientsdetail_document');
                    $controller->getDocuments($id);
                }
                break;

            case 'ib-upline':
                // /api/admin-clients/{id}/ib-upline
                if ($method === 'GET') {
                    $guardClientDetail('page_clientsdetail_ib_referal');
                    $controller->getClientIbUpline($id);
                }
                break;

            case 'sales':
                // /api/admin-clients/{id}/sales
                if ($method === 'GET') {
                    $guardClientDetail('page_clientsdetail_sales');
                    $controller->getClientSalesAssignment($id);
                }
                break;

            case 'status':
                // /api/admin-clients/{id}/status
                if ($method === 'PUT') {
                    $controller->updateStatus($id);
                }
                break;

            case 'approve-kyc':
                // /api/admin-clients/{id}/approve-kyc
                if ($method === 'POST') {
                    $guardClientsList('page_leads_direct_approve_kyc');
                    $controller->approveKyc($id);
                }
                break;

            case 'reset-password':
                // /api/admin-clients/{id}/reset-password
                if ($method === 'POST') {
                    $guardClientsList('page_clientslist_direct_password_reset');
                    $controller->resetPassword($id);
                }
                break;

            case 'send-password-reset':
                // /api/admin-clients/{id}/send-password-reset
                if ($method === 'POST') {
                    $guardClientsList('page_clientslist_password_reset');
                    $controller->sendPasswordReset($id);
                }
                break;

            case 'send-email':
                // /api/admin-clients/{id}/send-email
                if ($method === 'POST') {
                    $controller->sendEmail($id);
                }
                break;

            case 'trading-info':
                // /api/admin-clients/{id}/trading-info
                if ($method === 'GET') {
                    $guardClientDetail('page_clientsdetail_trading');
                    $controller->getTradingInfo($id);
                }
                break;

            case 'trading-history':
                // /api/admin-clients/{id}/trading-history?status=closed|open|pending
                if ($method === 'GET') {
                    $guardClientDetail('page_clientsdetail_trading');
                    $controller->getTradingHistory($id);
                }
                break;

            case 'trading-platforms':
                // Deprecated: use /api/admin-clients/trading-platforms
                if ($method === 'GET') {
                    $guardTradingAccountCreate();
                    $tradingAccountController->getAdminPlatforms();
                }
                break;

            case 'trading-groups':
                // /api/admin-clients/{id}/trading-groups/default?platform={platformKey}
                if ($method === 'GET' && (($pathParts[2] ?? null) === 'default')) {
                    $guardTradingAccountCreate();
                    $loginSettingsController->getDefaultTradingGroupForClient((int)$id);
                }
                break;

            case 'trading-accounts':
                // /api/admin-clients/{id}/trading-accounts/create
                if ($method === 'POST' && (($pathParts[2] ?? null) === 'create')) {
                    $guardTradingAccountCreate();
                    $tradingAccountController->createAdminAccount((int)$id);
                }
                // /api/admin-clients/{id}/trading-accounts/assignable-commission-rules
                elseif ($method === 'GET' && (($pathParts[2] ?? null) === 'assignable-commission-rules')) {
                    $guardTradingAccountCreate();
                    $tradingAccountController->getAssignableCommissionRules((int)$id);
                }
                // /api/admin-clients/{id}/trading-accounts/{tradingAccountId}/assigned-commission-rule
                elseif (
                    $method === 'PATCH'
                    && isset($pathParts[2])
                    && ctype_digit((string)$pathParts[2])
                    && (($pathParts[3] ?? null) === 'assigned-commission-rule')
                ) {
                    $guardTradingAccountCreate();
                    $tradingAccountController->updateAssignedCommissionRule((int)$id, (int)$pathParts[2]);
                }
                break;

            case 'transactions':
                // /api/admin-clients/{id}/transactions
                if ($method === 'GET') {
                    $guardClientTransactions();
                    $controller->getTransactions($id);
                }
                break;

            case 'withdraw-template':
                // /api/admin-clients/{id}/withdraw-template
                if ($method === 'GET') {
                    $guardWithdrawTemplatePayments();
                    $controller->getWithdrawTemplatePayments($id);
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
