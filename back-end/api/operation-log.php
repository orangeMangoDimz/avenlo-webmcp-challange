<?php
/**
 * 后台操作日志统一 API（日志设置 + 日志报表）
 * 与 /api/logs/*（登录日志）区分
 *
 * 日志设置 module-settings：
 *   GET  /api/operation-log/module-settings
 *   GET  /api/operation-log/module-settings/check?modelKey=
 *   POST /api/operation-log/module-settings/start
 *   POST /api/operation-log/module-settings/stop
 *   POST /api/operation-log/module-settings/bulk-start
 *   POST /api/operation-log/module-settings/bulk-stop
 *
 * 日志报表 reports：
 *   GET  /api/operation-log/reports/init
 *   GET  /api/operation-log/reports
 *   POST /api/operation-log/reports/export
 *   GET  /api/operation-log/reports/export-active
 *   GET  /api/operation-log/reports/export-status
 *   POST /api/operation-log/reports/export-cancel
 *   GET  /api/operation-log/reports/export-download
 *
 * 仅写日志 record：
 *   POST /api/operation-log/record
 */

require_once __DIR__ . '/../controllers/AdminOperationLogModuleSettingsController.php';
require_once __DIR__ . '/../controllers/AdminOperationLogReportController.php';
require_once __DIR__ . '/../controllers/AdminOperationLogRecordController.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$settingsController = new AdminOperationLogModuleSettingsController();
$reportController = new AdminOperationLogReportController();
$recordController = new AdminOperationLogRecordController();
$method = $_SERVER['REQUEST_METHOD'];
$path = trim($_GET['path'] ?? '', '/');
$segments = $path === '' ? [] : explode('/', $path);
$group = $segments[0] ?? '';
$action = $segments[1] ?? '';

try {
    if ($group === 'module-settings') {
        routeModuleSettings($settingsController, $method, $action);
    } elseif ($group === 'reports') {
        routeReports($reportController, $method, $action);
    } elseif ($group === 'record') {
        if ($method === 'POST' && ($action === '' || $action === 'create')) {
            $recordController->record();
            return;
        }
        Response::error('Route not found', 404);
    } else {
        Response::error('Route not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}

function routeModuleSettings(AdminOperationLogModuleSettingsController $controller, $method, $action) {
    if ($action === '' || $action === 'list') {
        if ($method === 'GET') {
            $controller->index();
            return;
        }
    } elseif ($action === 'check') {
        if ($method === 'GET') {
            $controller->check();
            return;
        }
    } elseif ($action === 'start') {
        if ($method === 'POST') {
            $controller->startOne();
            return;
        }
    } elseif ($action === 'stop') {
        if ($method === 'POST') {
            $controller->stopOne();
            return;
        }
    } elseif ($action === 'bulk-start') {
        if ($method === 'POST') {
            $controller->bulkStart();
            return;
        }
    } elseif ($action === 'bulk-stop') {
        if ($method === 'POST') {
            $controller->bulkStop();
            return;
        }
    }
    Response::error('Route not found', 404);
}

function routeReports(AdminOperationLogReportController $controller, $method, $action) {
    if ($action === 'init') {
        if ($method === 'GET') {
            $controller->init();
            return;
        }
    } elseif ($action === 'export') {
        if ($method === 'POST') {
            $controller->export();
            return;
        }
    } elseif ($action === 'export-active') {
        if ($method === 'GET') {
            $controller->exportActive();
            return;
        }
    } elseif ($action === 'export-status') {
        if ($method === 'GET') {
            $controller->exportStatus();
            return;
        }
    } elseif ($action === 'export-cancel') {
        if ($method === 'POST') {
            $controller->exportCancel();
            return;
        }
    } elseif ($action === 'export-download') {
        if ($method === 'GET') {
            $controller->exportDownload();
            return;
        }
    } elseif ($action === '' || $action === 'list') {
        if ($method === 'GET') {
            $controller->index();
            return;
        }
    }
    Response::error('Route not found', 404);
}
