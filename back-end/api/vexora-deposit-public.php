<?php
/**
 * Vexora public deposit notify/redirect entrypoints.
 *
 * Paths (no auth):
 *   success/callback, decline/callback  → server webhook (POST) — deposit only
 *   success/redirect, decline/redirect  → browser 302 to client success/fail
 *
 * Canonical notify paths used in checkout/disbursement payloads:
 *   api/callback/vexora/deposit | api/callback/vexora/withdrawal
 *   (see payments-processor-interface.php)
 */

require_once __DIR__ . '/../controllers/PaymentsProcessorCallbackController.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

function vexoraPublicFailureResponse() {
    Response::error('failure', 400);
}

$fullPath = trim((string)($_SERVER['CRM_FULL_REQUEST_PATH'] ?? ($_GET['path'] ?? '')), '/');
$segments = array_values(array_filter(explode('/', $fullPath), 'strlen'));
$outcome = strtolower($segments[0] ?? '');
$action = strtolower($segments[1] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($outcome, ['success', 'decline'], true) || !in_array($action, ['callback', 'redirect'], true)) {
    vexoraPublicFailureResponse();
}

$controller = new PaymentsProcessorCallbackController();

try {
    if ($action === 'callback') {
        if ($method !== 'POST') {
            vexoraPublicFailureResponse();
        }
        $controller->vexoraDepositCallback();
        exit;
    }

    if ($method !== 'GET' && $method !== 'POST') {
        vexoraPublicFailureResponse();
    }
    $controller->vexoraDepositBrowserRedirect($outcome === 'success' ? 'success' : 'fail');
} catch (Throwable $e) {
    ApplicationErrorHandler::recordException($e);
    if ($action === 'callback') {
        vexoraPublicFailureResponse();
    }
    $controller->vexoraDepositBrowserRedirect('fail');
}
