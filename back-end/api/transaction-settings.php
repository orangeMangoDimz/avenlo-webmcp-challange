<?php
/**
 * Transaction Settings API 路由
 */

require_once __DIR__ . '/../controllers/TransactionSettingsController.php';
require_once __DIR__ . '/../controllers/ClientTextController.php';
require_once __DIR__ . '/../controllers/AutoApprovalController.php';
require_once __DIR__ . '/../controllers/CountryController.php';
require_once __DIR__ . '/../controllers/SecuritySettingsController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

// 客户端公开接口或预览模式（X-Preview-Token）不需要 JWT 认证
$path = trim($_GET['path'] ?? '', '/');
$pathParts = array_values(array_filter(explode('/', $path), 'strlen'));
$section = $pathParts[0] ?? null;
$isClientPublicRoute = ($section === 'client');

if (!ClientAuthContext::allowPreviewRequest() && !$isClientPublicRoute) {
    AuthMiddleware::authenticate();
}

$guardTransactionSettings = static function ($requestMethod) {
    if (!AuthMiddleware::getCurrentUser()) {
        return;
    }

    if ($requestMethod === 'GET') {
        AuthMiddleware::checkAnyPermission(['page_transactionsettings_readonly', 'page_transactionsettings_edit']);
        return;
    }

    AuthMiddleware::checkPermission('page_transactionsettings_edit');
};

$controller = new TransactionSettingsController();
$clientTextController = new ClientTextController();
$autoApprovalController = new AutoApprovalController();
$countryController = new CountryController();
$securityController = new SecuritySettingsController();

$method = $_SERVER['REQUEST_METHOD'];
$path = trim($_GET['path'] ?? '', '/');
$pathParts = array_values(array_filter(explode('/', $path), 'strlen'));
$section = $pathParts[0] ?? null;
$id = $pathParts[1] ?? null;
$subSection = $pathParts[2] ?? null;
$action = $pathParts[3] ?? null;

$currentUser = AuthMiddleware::getCurrentUser();
$userType = $currentUser['type'] ?? null;

try {
    // 路由映射
    if (empty($path)) {
        // /api/transaction-settings
        if ($method === 'GET') {
            $guardTransactionSettings($method);
            $controller->index();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($section === 'crypto-addresses') {
        if (!$id) {
            // /api/transaction-settings/crypto-addresses
            if ($method === 'GET') {
                $guardTransactionSettings($method);
                $controller->getCryptoAddresses();
            } else {
                Response::error('Method not allowed', 405);
            }
        } else {
            // /api/transaction-settings/crypto-addresses/{paymentMethodId}
            if ($method === 'PUT') {
                $guardTransactionSettings($method);
                $controller->updateCryptoAddress($id);
            } else {
                Response::error('Method not allowed', 405);
            }
        }
    } elseif ($section === 'gateways') {
        // /api/transaction-settings/gateways or /api/transaction-settings/gateways/{gatewayKey}
        if ($method === 'GET' && !$id) {
            $guardTransactionSettings($method);
            $controller->getGateways();
        } elseif ($method === 'DELETE' && $id) {
            $guardTransactionSettings($method);
            $controller->deleteGateway($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($section === 'gateways-modify') {
        // /api/transaction-settings/gateways-modify/{gatewayKey}
        if ($method === 'PUT' && $id) {
            $guardTransactionSettings($method);
            $controller->updateGateway($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($section === 'gateway-fees') {
        // /api/transaction-settings/gateway-fees
        if ($method === 'GET') {
            $guardTransactionSettings($method);
            $controller->getGatewayFees();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($section === 'gateway-fees-modify') {
        // /api/transaction-settings/gateway-fees-modify/{gatewaySettingId}/limit
        // /api/transaction-settings/gateway-fees-modify/{gatewaySettingId}/deposit-fee
        // /api/transaction-settings/gateway-fees-modify/{gatewaySettingId}/withdraw-fee
        // /api/transaction-settings/gateway-fees-modify/{gatewaySettingId}/quick-amounts
        // /api/transaction-settings/gateway-fees-modify/{gatewaySettingId}/quick-amounts/{quickAmountId}
        if ($id && $subSection === 'quick-amounts') {
            $guardTransactionSettings($method);
            $quickAmountId = $action ? (int)$action : 0;
            if ($method === 'POST' && !$quickAmountId) {
                $controller->addGatewayQuickAmount($id);
            } elseif ($method === 'DELETE' && $quickAmountId > 0) {
                $controller->deleteGatewayQuickAmount($id, $quickAmountId);
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($method === 'PUT' && $id && $subSection) {
            $guardTransactionSettings($method);
            if ($subSection === 'limit') {
                $controller->updateGatewayFeeLimits($id);
            } elseif ($subSection === 'deposit-fee') {
                $controller->updateGatewayDepositFee($id);
            } elseif ($subSection === 'withdraw-fee') {
                $controller->updateGatewayWithdrawalFee($id);
            } else {
                Response::error('Method not allowed', 405);
            }
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($section === 'gateway-display-content') {
        // /api/transaction-settings/gateway-display-content/{gatewaySettingId}
        if ($method === 'GET' && $id) {
            $guardTransactionSettings($method);
            $controller->getGatewayDisplayContent($id);
        } elseif ($method === 'PUT' && $id) {
            $guardTransactionSettings($method);
            $controller->updateGatewayDisplayContent($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($section === 'payment-support-questions') {
        // /api/transaction-settings/payment-support-questions
        // /api/transaction-settings/payment-support-questions/{id}/options/toggle
        if ($method === 'GET' && !$id) {
            $guardTransactionSettings($method);
            $controller->getPaymentSupportQuestions();
        } elseif ($method === 'POST' && !$id) {
            $guardTransactionSettings($method);
            $controller->createPaymentSupportQuestion();
        } elseif ($method === 'POST' && $id && $subSection === 'options' && $action === 'toggle') {
            $guardTransactionSettings($method);
            $controller->togglePaymentSupportQuestionOption($id);
        } elseif ($method === 'PUT' && $id) {
            $guardTransactionSettings($method);
            $controller->updatePaymentSupportQuestion($id);
        } elseif ($method === 'DELETE' && $id) {
            $guardTransactionSettings($method);
            $controller->deletePaymentSupportQuestion($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($section === 'display-contents') {
        // /api/transaction-settings/display-contents
        // /api/transaction-settings/display-contents/{scope}
        if ($method === 'GET') {
            $guardTransactionSettings($method);
            $controller->getDisplayContents($id);
        } elseif ($method === 'PUT' && $id) {
            $guardTransactionSettings($method);
            $controller->updateDisplayContent($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($section === 'limits') {
        // /api/transaction-settings/limits
        if ($method === 'GET') {
            $guardTransactionSettings($method);
            $controller->getLimits();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($section === 'limits-modify') {
        // /api/transaction-settings/limits-modify/{id}
        if ($method === 'PUT' && $id) {
            $guardTransactionSettings($method);
            $controller->updateLimit($id);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($section === 'notifications') {
        // /api/transaction-settings/notifications
        if ($method === 'GET') {
            $guardTransactionSettings($method);
            $controller->getNotifications();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($section === 'notifications-modify') {
        // /api/transaction-settings/notifications-modify
        if ($method === 'PUT') {
            $guardTransactionSettings($method);
            $controller->updateNotification();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($section === 'client') {
        if ($id === 'exchange-rates') {
            // /api/transaction-settings/client/exchange-rates
            if ($method === 'GET') {
                $controller->getClientExchangeRates();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($id === 'gateways') {
            if (!$subSection) {
                // /api/transaction-settings/client/gateways
                if ($method === 'GET') {
                    $controller->getClientGateways();
                } else {
                    Response::error('Method not allowed', 405);
                }
            } elseif ($action === 'cryptos') {
                // /api/transaction-settings/client/gateways/{gatewayKey}/cryptos
                if ($method === 'GET') {
                    $controller->getGatewayCryptos($subSection);
                } else {
                    Response::error('Method not allowed', 405);
                }
            } else {
                Response::error('Route not found', 404);
            }
        } elseif ($id === 'deposit' && $subSection === 'payments') {
            if ($method === 'GET') {
                $gatewaySettingId = $action ?? ($_GET['gatewaySettingId'] ?? null);
                if (!$gatewaySettingId) {
                    Response::validationError(['gatewaySettingId' => 'gatewaySettingId is required']);
                }
                $controller->getClientDepositPayments($gatewaySettingId);
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($id === 'texts') {
            // /api/transaction-settings/client/texts
            if ($method === 'GET') {
                $clientTextController->index();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($id === 'security-settings') {
            // /api/transaction-settings/client/security-settings
            if ($method === 'GET') {
                $securityController->index();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($id === 'display-contents') {
            if ($method === 'GET') {
                $controller->getClientDisplayContents();
            } else {
                Response::error('Method not allowed', 405);
            }
        } else {
            Response::error('Route not found', 404);
        }
        exit;
    } elseif ($section === 'client-texts') {
        if (!$id) {
            // /api/transaction-settings/client-texts
            if ($method === 'GET') {
                $guardTransactionSettings($method);
                $clientTextController->index();
            } elseif ($method === 'PUT') {
                $guardTransactionSettings($method);
                $clientTextController->update();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($id === 'restore-all') {
            // /api/transaction-settings/client-texts/restore-all
            if ($method === 'POST') {
                $guardTransactionSettings($method);
                $clientTextController->restoreAll();
            } else {
                Response::error('Method not allowed', 405);
            }
        } else {
            // /api/transaction-settings/client-texts/{textKey}
            if ($subSection === 'restore') {
                // /api/transaction-settings/client-texts/{textKey}/restore
                if ($method === 'POST') {
                    $guardTransactionSettings($method);
                    $clientTextController->restore($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
            } else {
                // /api/transaction-settings/client-texts/{textKey}
                if ($method === 'GET') {
                    $guardTransactionSettings($method);
                    $clientTextController->show($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
            }
        }
    } elseif ($section === 'countries') {
        if (!$id) {
            // /api/transaction-settings/countries
            if ($method === 'GET') {
                $guardTransactionSettings($method);
                $countryController->index();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($id === 'list-from-country-list') {
            // /api/transaction-settings/countries/list-from-country-list（从 countryList 表，供入金自动审核国家多选用）
            if ($method === 'GET') {
                $guardTransactionSettings($method);
                $countryController->listFromCountryList();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($id === 'regions') {
            // /api/transaction-settings/countries/regions
            if ($method === 'GET') {
                $guardTransactionSettings($method);
                $countryController->regions();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($id === 'validate') {
            // /api/transaction-settings/countries/validate
            if ($method === 'POST') {
                $guardTransactionSettings($method);
                $countryController->validate();
            } else {
                Response::error('Method not allowed', 405);
            }
        } else {
            // /api/transaction-settings/countries/{code}
            if ($method === 'GET') {
                $guardTransactionSettings($method);
                $countryController->show($id);
            } else {
                Response::error('Method not allowed', 405);
            }
        }
    } elseif ($section === 'auto-approval-rules') {
        if (!$id) {
            // /api/transaction-settings/auto-approval-rules
            if ($method === 'GET') {
                $guardTransactionSettings($method);
                $autoApprovalController->index();
            } elseif ($method === 'POST') {
                $guardTransactionSettings($method);
                $autoApprovalController->create();
            } elseif ($method === 'PUT') {
                $guardTransactionSettings($method);
                $autoApprovalController->batchUpdate();
            } else {
                Response::error('Method not allowed', 405);
            }
        } else {
            // /api/transaction-settings/auto-approval-rules/{id}
            if ($subSection === 'toggle') {
                // /api/transaction-settings/auto-approval-rules/{id}/toggle
                if ($method === 'POST') {
                    $guardTransactionSettings($method);
                    $autoApprovalController->toggle($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
            } else {
                // /api/transaction-settings/auto-approval-rules/{id}
                if ($method === 'GET') {
                    $guardTransactionSettings($method);
                    $autoApprovalController->show($id);
                } elseif ($method === 'PUT') {
                    $guardTransactionSettings($method);
                    $autoApprovalController->update($id);
                } elseif ($method === 'DELETE') {
                    $guardTransactionSettings($method);
                    $autoApprovalController->delete($id);
                } else {
                    Response::error('Method not allowed', 405);
                }
            }
        }
    } elseif ($section === 'auto-approval-stats') {
        // /api/transaction-settings/auto-approval-stats
        if ($method === 'GET') {
            $guardTransactionSettings($method);
            $autoApprovalController->stats();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($section === 'check-auto-approval') {
        // /api/transaction-settings/check-auto-approval
        if ($method === 'POST') {
            $guardTransactionSettings($method);
            $autoApprovalController->checkTransaction();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($section === 'security-settings') {
        if (!$id) {
            // /api/transaction-settings/security-settings
            if ($method === 'GET') {
                $guardTransactionSettings($method);
                $securityController->index();
            } elseif ($method === 'PUT') {
                $guardTransactionSettings($method);
                $securityController->update();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($id === 'restore-defaults') {
            // /api/transaction-settings/security-settings/restore-defaults
            if ($method === 'POST') {
                $guardTransactionSettings($method);
                $securityController->restoreDefaults();
            } else {
                Response::error('Method not allowed', 405);
            }
        } else {
            // /api/transaction-settings/security-settings/{key}
            if ($method === 'GET') {
                $guardTransactionSettings($method);
                $securityController->show($id);
            } else {
                Response::error('Method not allowed', 405);
            }
        }
    } elseif ($section === 'security-stats') {
        // /api/transaction-settings/security-stats
        if ($method === 'GET') {
            $guardTransactionSettings($method);
            $securityController->stats();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($section === 'exchange-rates') {
        if (!$id) {
            // /api/transaction-settings/exchange-rates
            if ($method === 'GET') {
                $guardTransactionSettings($method);
                $controller->getExchangeRates();
            } elseif ($method === 'POST') {
                $guardTransactionSettings($method);
                $controller->createExchangeRate();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($subSection === 'toggle') {
            // /api/transaction-settings/exchange-rates/{id}/toggle
            if ($method === 'POST') {
                $guardTransactionSettings($method);
                $controller->toggleExchangeRate($id);
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($subSection === 'sync-mode') {
            // /api/transaction-settings/exchange-rates/{id}/sync-mode
            if ($method === 'POST') {
                $guardTransactionSettings($method);
                $controller->toggleExchangeRateSyncMode($id);
            } else {
                Response::error('Method not allowed', 405);
            }
        } else {
            // /api/transaction-settings/exchange-rates/{id}
            if ($method === 'PUT') {
                $guardTransactionSettings($method);
                $controller->updateExchangeRate($id);
            } elseif ($method === 'DELETE') {
                $guardTransactionSettings($method);
                $controller->deleteExchangeRate($id);
            } else {
                Response::error('Method not allowed', 405);
            }
        }
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
