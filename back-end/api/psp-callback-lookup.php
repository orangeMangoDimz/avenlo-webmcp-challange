<?php
/**
 * PSP Callback Lookup API
 *
 * 用 deposit / withdrawal 的 transactionId 或业务记录 ID 查这一笔订单的全部 callback 记录
 * （同一笔订单 PSP 通常会发多次：付款中、付款成功 …）。
 * Vexora stores dash-stripped tradeNo as orderId; match CRM id with/without '-'.
 * 命中按时间倒序返回，未命中返回空数组。
 *
 * GET /api/psp-callback-lookup?orderId=xxx&transactionType=deposit&recordId=1697
 * 权限：page_deposit_readonly | page_deposit_approve | page_withdraw_readonly | page_withdraw_approve 任一
 */

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

AuthMiddleware::authenticate();
// 与 GET /deposits/{id}、GET /withdrawals/{id} 详情接口的权限保持一致：
// client detail funding tab 用 page_clientsdetail_funding，列表页用 deposit/withdraw 的读/审批权限
AuthMiddleware::checkAnyPermission([
    'page_clientsdetail_funding',
    'page_deposit_readonly',
    'page_deposit_approve',
    'page_withdraw_readonly',
    'page_withdraw_approve'
]);

try {
    $orderId = isset($_GET['orderId']) ? trim((string)$_GET['orderId']) : '';
    $transactionType = strtolower(trim((string)($_GET['transactionType'] ?? '')));
    $recordIdRaw = trim((string)($_GET['recordId'] ?? ''));
    $recordId = $recordIdRaw !== '' && ctype_digit($recordIdRaw) ? (int)$recordIdRaw : 0;

    if ($transactionType !== '' && !in_array($transactionType, ['deposit', 'withdrawal'], true)) {
        Response::validationError(['transactionType' => 'transactionType must be deposit or withdrawal']);
    }
    if ($recordIdRaw !== '' && $recordId <= 0) {
        Response::validationError(['recordId' => 'recordId must be a positive integer']);
    }
    if ($recordId > 0 && $transactionType === '') {
        Response::validationError(['transactionType' => 'transactionType is required when recordId is provided']);
    }
    if ($orderId === '' && $recordId <= 0) {
        Response::validationError(['orderId' => 'orderId or recordId is required']);
    }

    $db = Database::getInstance();
    $matchConditions = [];
    $params = [];

    if ($orderId !== '') {
        $orderIdNoDash = str_replace('-', '', $orderId);
        $matchConditions[] = 'orderId = :orderId';
        $matchConditions[] = 'orderId = :orderIdNoDash';
        $matchConditions[] = "REPLACE(orderId, '-', '') = :orderIdNoDash2";
        $params['orderId'] = $orderId;
        $params['orderIdNoDash'] = $orderIdNoDash;
        $params['orderIdNoDash2'] = $orderIdNoDash;
    }

    if ($recordId > 0) {
        $recordColumn = $transactionType === 'deposit' ? 'depositId' : 'withdrawalId';
        $matchConditions[] = "`{$recordColumn}` = :recordId";
        $params['recordId'] = $recordId;
    }

    $where = '(' . implode(' OR ', $matchConditions) . ')';
    if ($transactionType !== '') {
        $where = 'transactionType = :transactionType AND ' . $where;
        $params['transactionType'] = $transactionType;
    }

    $rows = $db->fetchAll(
        "SELECT id, transactionType, orderId, callbackStatus, amount,
                rawPayload, processResult, errorMessage
         FROM paymentProcessorCallbackLogs
         WHERE {$where}
         ORDER BY id DESC",
        $params
    );

    Response::success([
        'orderId' => $orderId,
        'callbacks' => $rows
    ]);
} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
