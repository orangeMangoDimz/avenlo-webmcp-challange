<?php
/**
 * IB Settings API 路由
 * IB程序设置和文档模板管理接口
 */

require_once __DIR__ . '/../controllers/IbSettingsController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$ibSettingsController = new IbSettingsController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 所有路由都需要认证
AuthMiddleware::authenticate();

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $segment1 = $pathParts[0] ?? null;
    $segment2 = $pathParts[1] ?? null;
    $segment3 = $pathParts[2] ?? null;

    // 路由映射
    if (empty($path)) {
        // /api/ib-settings
        if ($method === 'GET') {
            $ibSettingsController->index();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'bulk-update') {
        // /api/ib-settings/bulk-update
        if ($method === 'POST') {
            $ibSettingsController->bulkUpdate();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'sync-products') {
        // /api/ib-settings/sync-products
        if ($method === 'POST') {
            $ibSettingsController->syncProducts();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'sync-progress') {
        // /api/ib-settings/sync-progress?platformKey=mt5
        if ($method === 'GET') {
            $ibSettingsController->syncProgress();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($segment1 === 'documents') {
        // 文档模板相关路由
        if (!$segment2) {
            // /api/ib-settings/documents
            if ($method === 'GET') {
                $ibSettingsController->getDocuments();
            } elseif ($method === 'POST') {
                $ibSettingsController->createDocument();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($segment2 && !$segment3) {
            // /api/ib-settings/documents/{id}
            if ($method === 'GET') {
                $ibSettingsController->getDocument($segment2);
            } elseif ($method === 'PUT') {
                $ibSettingsController->updateDocument($segment2);
            } elseif ($method === 'DELETE') {
                $ibSettingsController->deleteDocument($segment2);
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($segment2 && $segment3 === 'duplicate') {
            // /api/ib-settings/documents/{id}/duplicate
            if ($method === 'POST') {
                $ibSettingsController->duplicateDocument($segment2);
            } else {
                Response::error('Method not allowed', 405);
            }
        } else {
            Response::error('Route not found', 404);
        }
    } elseif ($segment1 === 'custom-securitiesbyplatform') {
        // 按平台获取自定义证券 /api/ib-settings/custom-securitiesbyplatform
        if ($method === 'GET') {
            $ibSettingsController->getCustomSecuritiesByPlatform();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($segment1 === 'custom-securities') {
        // 自定义证券相关路由
        // /api/ib-settings/custom-securities
        if ($method === 'GET') {
            $ibSettingsController->getCustomSecurities();
        } elseif ($method === 'POST') {
            $ibSettingsController->createCustomSecurity();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($segment1 === 'custom-symbolsbyplatform') {
        // 按平台获取自定义交易对 /api/ib-settings/custom-symbolsbyplatform
        if ($method === 'GET') {
            $ibSettingsController->getCustomSymbolsByPlatform();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($segment1 === 'custom-symbols') {
        // 自定义交易对相关路由
        // /api/ib-settings/custom-symbols
        if ($method === 'GET') {
            $ibSettingsController->getCustomSymbols();
        } elseif ($method === 'POST') {
            $ibSettingsController->createCustomSymbol();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($segment1 === 'symbol-exchange-rates') {
        if ($segment2 === 'global-mode') {
            // /api/ib-settings/symbol-exchange-rates/global-mode
            if ($method === 'POST') {
                $ibSettingsController->setSymbolExchangeGlobalMode();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif (!$segment2) {
            // /api/ib-settings/symbol-exchange-rates
            if ($method === 'GET') {
                $ibSettingsController->getSymbolExchangeRates();
            } elseif ($method === 'POST') {
                $ibSettingsController->createSymbolExchangeRate();
            } else {
                Response::error('Method not allowed', 405);
            }
        } elseif ($segment2 && !$segment3) {
            // /api/ib-settings/symbol-exchange-rates/{id}
            if ($method === 'POST') {
                $ibSettingsController->updateSymbolExchangeRate($segment2);
            } elseif ($method === 'DELETE') {
                $ibSettingsController->deleteSymbolExchangeRate($segment2);
            } else {
                Response::error('Method not allowed', 405);
            }
        } else {
            Response::error('Route not found', 404);
        }
    } elseif ($segment1 && !$segment2) {
        // /api/ib-settings/{key}
        if ($method === 'GET') {
            $ibSettingsController->show($segment1);
        } elseif ($method === 'PUT') {
            $ibSettingsController->update($segment1);
        } else {
            Response::error('Method not allowed', 405);
        }
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
