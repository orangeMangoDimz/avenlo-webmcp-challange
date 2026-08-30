<?php
/**
 * 国家列表 API（公开）
 */

require_once __DIR__ . '/../controllers/CountryController.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new CountryController();
$method = $_SERVER['REQUEST_METHOD'];
$path = trim($_GET['path'] ?? '', '/');
$pathParts = array_values(array_filter(explode('/', $path), 'strlen'));

try {
    // 处理 /api/countries/all 和 /api/countries/without-all
    if ($path === 'all') {
        if ($method === 'GET') {
            $controller->list(true);
        } else {
            Response::error('Method not allowed', 405);
        }
        return;
    } else if ($path === 'without-all') {
        if ($method === 'GET') {
            $controller->list(false);
        } else {
            Response::error('Method not allowed', 405);
        }
        return;
    }

    // 处理 /api/countries/transaction-settings/countries 相关路由
    if (count($pathParts) >= 2 && $pathParts[0] === 'transaction-settings' && $pathParts[1] === 'countries') {
        $subPath = $pathParts[2] ?? null;
        $code = $pathParts[3] ?? null;

        if (!$subPath) {
            // GET /api/countries/transaction-settings/countries
            if ($method === 'GET') {
                $controller->index();
            } else {
                Response::error('Method not allowed', 405);
            }
        } else if ($subPath === 'regions') {
            // GET /api/countries/transaction-settings/countries/regions
            if ($method === 'GET') {
                $controller->regions();
            } else {
                Response::error('Method not allowed', 405);
            }
        } else if ($subPath === 'validate') {
            // POST /api/countries/transaction-settings/countries/validate
            if ($method === 'POST') {
                $controller->validate();
            } else {
                Response::error('Method not allowed', 405);
            }
        } else {
            // GET /api/countries/transaction-settings/countries/{code}
            if ($method === 'GET') {
                $controller->show($subPath);
            } else {
                Response::error('Method not allowed', 405);
            }
        }
        return;
    }

    Response::error('Route not found', 404);
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
