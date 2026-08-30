<?php

require_once __DIR__ . '/../controllers/ClientIbStatementReportController.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new ClientIbStatementReportController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

try {
    if ($path === '' || $path === 'statement') {
        if ($method === 'GET') {
            $controller->statement();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'export') {
        if ($method === 'POST') {
            $controller->exportReport();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'export-active') {
        if ($method === 'GET') {
            $controller->exportActive();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'export-status') {
        if ($method === 'GET') {
            $controller->exportStatus();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'export-cancel') {
        if ($method === 'POST') {
            $controller->exportCancel();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'export-download') {
        if ($method === 'GET') {
            $controller->exportDownload();
        } else {
            Response::error('Method not allowed', 405);
        }
    } else {
        Response::error('Route not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
