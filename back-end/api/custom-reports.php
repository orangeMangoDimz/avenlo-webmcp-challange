<?php
/**
 * Custom Reports API routes
 */

require_once __DIR__ . '/../controllers/CustomReportController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new CustomReportController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

AuthMiddleware::authenticate();

try {
    $pathParts = explode('/', trim($path, '/'));
    $first = $pathParts[0] ?? '';
    $second = $pathParts[1] ?? null;
    $third = $pathParts[2] ?? null;

    if ($path === '' || $path === false) {
        if ($method === 'GET') {
            $controller->index();
        } elseif ($method === 'POST') {
            $controller->create();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first === 'transactions' && $second === null) {
        if ($method === 'GET') {
            $controller->getTransactions();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first === 'data-sources' && $second === null) {
        if ($method === 'GET') {
            $controller->listDataSources();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first === 'data-sources' && $second !== null && ($pathParts[2] ?? null) === 'rows') {
        if ($method === 'GET') {
            $controller->getDataSourceRows($second);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first === 'data-sources' && $second !== null && ($pathParts[2] ?? null) === 'export') {
        if ($method === 'POST') {
            $controller->exportDataSourceRows($second);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first === 'data-sources' && $second !== null && ($pathParts[2] ?? null) === 'export-active') {
        if ($method === 'GET') {
            $controller->exportWidgetActive();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first === 'data-sources' && $second !== null && ($pathParts[2] ?? null) === 'export-status') {
        if ($method === 'GET') {
            $controller->exportWidgetStatus();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first === 'data-sources' && $second !== null && ($pathParts[2] ?? null) === 'export-cancel') {
        if ($method === 'POST') {
            $controller->exportWidgetCancel();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first === 'data-sources' && $second !== null && ($pathParts[2] ?? null) === 'export-download') {
        if ($method === 'GET') {
            $controller->exportWidgetDownload();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first === 'data-sources' && $second !== null && ($pathParts[2] ?? null) === 'column-values') {
        if ($method === 'GET') {
            $controller->getDataSourceColumnValues($second);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first === 'data-sources' && $second !== null && ($pathParts[2] ?? null) === null) {
        if ($method === 'GET') {
            $controller->getDataSource($second);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first !== '' && $second === 'widgets' && $third === null) {
        if ($method === 'POST') {
            $controller->createWidget($first);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first !== '' && $second === 'widgets' && $third !== null && ($pathParts[3] ?? null) === 'duplicate') {
        if ($method === 'POST') {
            $controller->duplicateWidget($first, $third);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first !== '' && $second === 'widgets' && $third !== null && ($pathParts[3] ?? null) === 'rows') {
        if ($method === 'GET') {
            $controller->getWidgetRows($first, $third);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first !== '' && $second === 'widgets' && $third !== null && ($pathParts[3] ?? null) === 'column-values') {
        if ($method === 'GET') {
            $controller->getWidgetColumnValues($first, $third);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first !== '' && $second === 'widgets' && $third !== null && ($pathParts[3] ?? null) === 'detail-rows') {
        if ($method === 'GET') {
            $controller->getWidgetDetailRows($first, $third);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first !== '' && $second === 'widgets' && $third !== null && ($pathParts[3] ?? null) === 'chart') {
        if ($method === 'GET') {
            $controller->getWidgetChart($first, $third);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first !== '' && $second === 'widgets' && $third !== null && ($pathParts[3] ?? null) === 'export') {
        if ($method === 'POST') {
            $controller->exportWidgetRows($first, $third);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first !== '' && $second === 'widgets' && $third !== null && ($pathParts[3] ?? null) === 'export-active') {
        if ($method === 'GET') {
            $controller->exportWidgetActive();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first !== '' && $second === 'widgets' && $third !== null && ($pathParts[3] ?? null) === 'export-status') {
        if ($method === 'GET') {
            $controller->exportWidgetStatus();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first !== '' && $second === 'widgets' && $third !== null && ($pathParts[3] ?? null) === 'export-cancel') {
        if ($method === 'POST') {
            $controller->exportWidgetCancel();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first !== '' && $second === 'widgets' && $third !== null && ($pathParts[3] ?? null) === 'export-download') {
        if ($method === 'GET') {
            $controller->exportWidgetDownload();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first !== '' && $second === 'widgets' && $third !== null) {
        if ($method === 'PUT' || $method === 'PATCH') {
            $controller->updateWidget($first, $third);
        } elseif ($method === 'DELETE') {
            $controller->deleteWidget($first, $third);
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($first !== '' && $second === null) {
        if ($method === 'GET') {
            $controller->show($first);
        } elseif ($method === 'PUT' || $method === 'PATCH') {
            $controller->update($first);
        } elseif ($method === 'DELETE') {
            $controller->delete($first);
        } else {
            Response::error('Method not allowed', 405);
        }
    } else {
        Response::error('Route not found', 404);
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
