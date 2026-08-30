<?php
/**
 * PSP Callback API Dispatcher
 * 无鉴权回调统一入口（由第三方支付处理器调用）
 */

require_once __DIR__ . '/../controllers/PaymentsProcessorCallbackController.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

function callbackFailureResponse() {
    Response::error('failure', 400);
}

function parsePspCallbackPath(string $path): array {
    $segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));

    return [
        'psp' => strtolower($segments[0] ?? ''),
        'action' => strtolower($segments[1] ?? '')
    ];
}

$controller = new PaymentsProcessorCallbackController();
$method = $_SERVER['REQUEST_METHOD'];
$path = trim($_GET['path'] ?? '', '/');
$parsedPath = parsePspCallbackPath($path);
$psp = $parsedPath['psp'];
$action = $parsedPath['action'];

try {
    if ($method !== 'POST') {
        callbackFailureResponse();
    }

    if ($psp === 'ibeepay' && $action === 'deposit') {
        $controller->ibeepayDepositCallback();
    } elseif ($psp === 'ibeepay' && $action === 'withdrawal') {
        $controller->ibeepayWithdrawalCallback();
    } elseif ($psp === 'payment-asia' && $action === 'deposit') {
        $controller->paymentAsiaDepositCallback();
    } elseif ($psp === 'payment-asia' && $action === 'withdrawal') {
        $controller->paymentAsiaWithdrawalCallback();
    } elseif ($psp === 'coinsbuy' && $action === 'deposit') {
        $controller->coinsbuyDepositCallback();
    } elseif ($psp === 'coinsbuy' && $action === 'withdrawal') {
        $controller->coinsbuyWithdrawalCallback();
    } elseif ($psp === 'vexora' && $action === 'deposit') {
        $controller->vexoraDepositCallback();
    } elseif ($psp === 'vexora' && $action === 'withdrawal') {
        $controller->vexoraWithdrawalCallback();
    } elseif ($psp === 'flashpay' && $action === 'deposit') {
        $controller->flashpayDepositCallback();
    } elseif ($psp === 'flashpay' && $action === 'withdrawal') {
        $controller->flashpayWithdrawalCallback();
    } elseif ($psp === 'cvpay' && $action === 'deposit') {
        $controller->cvpayDepositCallback();
    } elseif ($psp === 'cvpay' && $action === 'withdrawal') {
        $controller->cvpayWithdrawalCallback();
    } elseif (FivePayService::isCallbackPsp($psp) && $action === 'deposit') {
        $controller->fivepayDepositCallback();
    } elseif (FivePayService::isCallbackPsp($psp) && $action === 'withdrawal') {
        $controller->fivepayWithdrawalCallback();
    } elseif ($psp === 'xlink' && $action === 'status') {
        $controller->xlinkStatusCallback();
    } else {
        callbackFailureResponse();
    }
} catch (Throwable $e) {
    ApplicationErrorHandler::recordException($e);
    callbackFailureResponse();
}
