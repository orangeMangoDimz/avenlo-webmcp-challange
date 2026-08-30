<?php
/**
 * PSP Callback Controller
 * 处理 IBeePay 与 Payment Asia 的回调请求。
 */

require_once __DIR__ . '/../models/Deposit.php';
require_once __DIR__ . '/../models/Withdrawal.php';
require_once __DIR__ . '/../models/PaymentProcessorCallbackLog.php';
require_once __DIR__ . '/../models/PaymentGatewaySetting.php';
require_once __DIR__ . '/../models/ClientNotification.php';
require_once __DIR__ . '/../models/ClientSystemNotification.php';
require_once __DIR__ . '/../services/PaymentSettlementService.php';
require_once __DIR__ . '/../services/PaymentProcessorRequestLogService.php';
require_once __DIR__ . '/../services/VexoraService.php';
require_once __DIR__ . '/../services/FlashPayService.php';
require_once __DIR__ . '/../services/CvPayService.php';
require_once __DIR__ . '/../services/FivePayService.php';
require_once __DIR__ . '/../services/XLinkService.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/ClientIp.php';

class PaymentsProcessorCallbackController {
    private $depositModel;
    private $withdrawalModel;
    private $callbackLogModel;
    private $gatewayModel;
    private $clientNotificationModel;
    private $clientSystemNotificationModel;
    private $appConfig;
    private $paymentService;
    private $requestLogService;

    public function __construct() {
        $this->depositModel = new Deposit();
        $this->withdrawalModel = new Withdrawal();
        $this->callbackLogModel = new PaymentProcessorCallbackLog();
        $this->gatewayModel = new PaymentGatewaySetting();
        $this->clientNotificationModel = new ClientNotification();
        $this->clientSystemNotificationModel = new ClientSystemNotification();
        $this->appConfig = require __DIR__ . '/../config/app.php';
        $this->paymentService = new PaymentSettlementService();
        $this->requestLogService = new PaymentProcessorRequestLogService();
    }

    /**
     * Handle the unified X-Link PAYIN/PAYOUT status callback.
     * The exact body is retained for HMAC verification before JSON decoding.
     */
    public function xlinkStatusCallback() {
        $rawBody = file_get_contents('php://input');
        $rawBody = is_string($rawBody) ? $rawBody : '';
        $decoded = $rawBody !== '' ? json_decode($rawBody, true) : null;
        $tentativeData = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
        $operationType = strtoupper(trim((string)($tentativeData['operation_type'] ?? '')));
        $transactionType = $operationType === 'PAYOUT' ? 'withdrawal' : 'deposit';
        $operationNumber = trim((string)($tentativeData['operation_number'] ?? ''));
        $operationId = trim((string)($tentativeData['operation_id'] ?? ''));
        $record = is_array($decoded)
            ? $this->findXLinkTransaction($operationType, $operationNumber, $operationId)
            : null;
        $logId = $this->createXLinkCallbackLog($transactionType, $tentativeData, $decoded);
        $ids = $this->xlinkCallbackIds($transactionType, $record, $operationId);

        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            $this->finishCallbackLog($logId, false, 'failed', 'Invalid JSON payload', $ids);
            $this->respondXLinkCallback(400, 'Invalid JSON payload');
        }

        if (!in_array($operationType, ['PAYIN', 'PAYOUT'], true)) {
            $this->finishCallbackLog($logId, false, 'failed', 'X-Link operation type is required', $ids);
            $this->respondXLinkCallback(400, 'Invalid operation type');
        }

        if (!$record) {
            $this->finishCallbackLog($logId, false, 'failed', 'X-Link transaction not found', $ids);
            $this->respondXLinkCallback(400, 'Transaction not found');
        }

        $gateway = $this->findXLinkGatewayForTransaction($record);
        if (!$gateway) {
            $this->finishCallbackLog($logId, false, 'failed', 'Transaction does not belong to X-Link', $ids);
            $this->respondXLinkCallback(400, 'Invalid gateway');
        }

        $service = new XLinkService($gateway);
        $signature = trim((string)($_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? ''));
        if (!$service->verifyWebhookSignature($rawBody, $signature)) {
            $this->finishCallbackLog($logId, false, 'failed', 'Invalid X-Link webhook signature', $ids);
            $this->respondXLinkCallback(400, 'Invalid signature');
        }

        $normalizationPayload = $decoded;
        if ($operationNumber === '' && $operationId !== '') {
            $normalizationPayload['data']['operation_number'] = trim((string)($record['transactionId'] ?? ''));
        }

        try {
            $normalized = $service->normalizeCallback($normalizationPayload);
        } catch (Throwable $e) {
            $this->finishCallbackLog($logId, false, 'failed', $e->getMessage(), $ids);
            $this->respondXLinkCallback(400, 'Invalid callback payload');
        }

        $expectedTransactionType = $normalized['operationType'] === 'PAYOUT' ? 'withdrawal' : 'deposit';
        if ($expectedTransactionType !== $transactionType) {
            $this->finishCallbackLog($logId, false, 'failed', 'X-Link operation type mismatch', $ids);
            $this->respondXLinkCallback(400, 'Operation type mismatch');
        }

        $expectedOperationNumber = trim((string)($record['transactionId'] ?? ''));
        if ($expectedOperationNumber === '' || $normalized['operationNumber'] !== $expectedOperationNumber) {
            $this->finishCallbackLog($logId, false, 'failed', 'X-Link operation number mismatch', $ids);
            $this->respondXLinkCallback(400, 'Operation number mismatch');
        }

        if ($normalized['currency'] !== XLinkService::DEFAULT_CURRENCY) {
            $this->finishCallbackLog($logId, false, 'failed', 'X-Link callback currency mismatch', $ids);
            $this->respondXLinkCallback(400, 'Currency mismatch');
        }

        try {
            $expectedAmount = XLinkService::normalizeKrwAmount(
                $record['quotedAmount'] ?? $record['amount'] ?? null
            );
            foreach (['initiatedAmount', 'amount'] as $amountField) {
                if ($normalized[$amountField] === null) {
                    continue;
                }
                if (XLinkService::normalizeKrwAmount($normalized[$amountField]) !== $expectedAmount) {
                    throw new InvalidArgumentException('X-Link callback amount mismatch');
                }
            }
        } catch (Throwable $e) {
            $this->finishCallbackLog($logId, false, 'failed', $e->getMessage(), $ids);
            $this->respondXLinkCallback(400, 'Amount mismatch');
        }

        $mappedStatus = $normalized['mappedStatus'];
        if ($mappedStatus === 'unknown') {
            $this->finishCallbackLog($logId, false, 'failed', 'Unsupported X-Link callback status', $ids);
            $this->respondXLinkCallback(400, 'Unsupported status');
        }

        try {
            $this->markXLinkCallbackLogValid($logId, $expectedOperationNumber);
        } catch (Throwable $e) {
            try {
                $this->finishCallbackLog($logId, false, 'failed', $e->getMessage(), $ids);
            } catch (Throwable $logError) {
                Logger::error('Failed to finalize X-Link callback audit failure: ' . $logError->getMessage());
            }
            $this->respondXLinkCallback(500, 'Callback audit persistence failed');
        }

        try {
            $this->persistXLinkCallbackReference($transactionType, $record, $normalized['operationId']);
        } catch (Throwable $e) {
            $this->finishCallbackLog($logId, false, 'failed', $e->getMessage(), $ids);
            $this->respondXLinkCallback(500, 'Callback persistence failed');
        }
        $currentStatus = strtolower(trim((string)($record['status'] ?? '')));

        if (in_array($mappedStatus, ['pending', 'processing'], true)) {
            $result = in_array($currentStatus, ['completed', 'rejected', 'cancelled'], true) ? 'ignored' : 'success';
            $message = $result === 'ignored' ? 'Nonterminal callback ignored for terminal transaction' : null;
            $this->finishCallbackLog($logId, true, $result, $message, $ids);
            $this->respondXLinkCallback(200, 'success');
        }

        $callbackLock = $this->xlinkCallbackLockName($transactionType, (int)($record['id'] ?? 0));
        if (!$this->acquireXLinkCallbackLock($callbackLock)) {
            $this->finishCallbackLog($logId, false, 'failed', 'Could not acquire X-Link settlement lock', $ids);
            $this->respondXLinkCallback(500, 'Settlement is busy');
        }

        try {
            $freshRecord = $transactionType === 'withdrawal'
                ? $this->withdrawalModel->findById((int)$record['id'])
                : $this->depositModel->findById((int)$record['id']);
        } catch (Throwable $e) {
            $this->releaseXLinkCallbackLock($callbackLock);
            $this->finishCallbackLog($logId, false, 'failed', $e->getMessage(), $ids);
            $this->respondXLinkCallback(500, 'Settlement lookup failed');
        }
        if ($freshRecord) {
            $record = $freshRecord;
            $currentStatus = strtolower(trim((string)($record['status'] ?? '')));
        }

        if ($mappedStatus === 'refunded' && $currentStatus === 'completed') {
            Logger::warning('X-Link refund callback requires manual review', [
                'transactionType' => $transactionType,
                'transactionId' => $expectedOperationNumber,
            ]);
            $this->releaseXLinkCallbackLock($callbackLock);
            $this->finishCallbackLog($logId, true, 'ignored', 'Manual review required for completed X-Link refund', $ids);
            $this->respondXLinkCallback(200, 'success');
        }

        if ($this->isXLinkDuplicateTerminalCallback($currentStatus, $mappedStatus)) {
            $this->releaseXLinkCallbackLock($callbackLock);
            $this->finishCallbackLog($logId, true, 'ignored', 'Duplicate terminal callback ignored', $ids);
            $this->respondXLinkCallback(200, 'success');
        }

        try {
            if ($mappedStatus === 'success') {
                $this->settleXLinkSuccess($transactionType, $record, $normalized['operationId']);
            } elseif (
                $transactionType === 'deposit'
                && in_array($mappedStatus, ['expired', 'cancelled'], true)
            ) {
                $reason = $mappedStatus === 'expired'
                    ? 'Deposit expired by X-Link callback'
                    : 'Deposit cancelled by X-Link callback';
                $this->paymentService->markDepositProviderTerminal(
                    $record,
                    $mappedStatus,
                    $reason,
                    $normalized['status'],
                    XLinkService::PROVIDER_KEY
                );
            } else {
                $reason = $mappedStatus === 'refunded'
                    ? ucfirst($transactionType) . ' refunded by X-Link before completion'
                    : ucfirst($transactionType) . ' ' . $mappedStatus . ' by X-Link callback';
                $this->rejectXLinkTransaction($transactionType, $record, $reason);
            }
        } catch (Throwable $e) {
            $this->releaseXLinkCallbackLock($callbackLock);
            $this->finishCallbackLog($logId, false, 'failed', $e->getMessage(), $ids);
            $this->respondXLinkCallback(500, 'Settlement failed');
        }

        $this->releaseXLinkCallbackLock($callbackLock);
        $this->finishCallbackLog($logId, true, 'success', null, $ids);
        $this->respondXLinkCallback(200, 'success');
    }

    /**
     * 处理 IBeePay deposit callback。
     */
    public function ibeepayDepositCallback() {
        $payload = $this->readPayload();
        $normalized = $this->normalizeIbeepayPayload($payload);
        if (!$this->hasMeaningfulIbeepayCallbackStructure($normalized)) {
            $this->respondCallbackFailure();
        }

        if (!$this->isAllowedCallbackIpByConfigKey('ibeepay_whitelist_ip_array')) {
            $this->respondCallbackSuccess();
        }

        $validation = $this->validateIbeepayCallbackAuth($normalized);
        $logId = $this->createCallbackLog('deposit', $normalized, $validation['valid']);

        if (!$validation['valid']) {
            $this->finishCallbackLog($logId, false, 'failed', $validation['error'], []);
            $this->respondCallbackFailure();
        }

        if ($normalized['orderId'] === '' || $normalized['status'] === '') {
            $this->finishCallbackLog($logId, false, 'failed', 'orderId and status are required', []);
            $this->respondCallbackFailure();
        }

        $deposit = $this->findDepositByOrderId($normalized['orderId']);
        if (!$deposit) {
            $this->finishCallbackLog($logId, false, 'failed', 'Deposit not found', []);
            $this->respondCallbackFailure();
        }

        $status = $normalized['status'];
        $depositId = (int)$deposit['id'];

        if ($normalized['amount'] !== '' && is_numeric($normalized['amount'])) {
            $callbackAmount = (float)$normalized['amount'];
            $expectedAmount = isset($deposit['quotedAmount']) && $deposit['quotedAmount'] !== null
                ? (float)$deposit['quotedAmount']
                : (float)($deposit['amount'] ?? 0);
            $expectedProcessorAmount = (float)((int)round($expectedAmount));
            if (abs($callbackAmount - $expectedProcessorAmount) > 0.0001) {
                $this->finishCallbackLog($logId, false, 'failed', 'Amount mismatch', ['depositId' => $depositId]);
                $this->respondCallbackFailure();
            }
        }

        if ($this->isIbeepayDepositCallbackAlreadyApplied($deposit, $status)) {
            $this->finishCallbackLog($logId, true, 'ignored', 'Duplicate callback ignored', ['depositId' => $depositId]);
            $this->respondCallbackSuccess();
        }

        if ($status === 'approved') {
            // 已是 completed 直接跳过资金动作，避免重复入账
            if (($deposit['status'] ?? '') !== 'completed') {
                try {
                    $this->paymentService->markDepositSuccess(
                        $deposit,
                        0,
                        'Auto-approved by IBeePay callback',
                        'callback'
                    );
                } catch (Exception $e) {
                    $this->finishCallbackLog(
                        $logId,
                        false,
                        'failed',
                        'Failed to approve deposit callback: ' . $e->getMessage(),
                        ['depositId' => $depositId]
                    );
                    $this->respondCallbackFailure();
                }
            }
            $this->finishCallbackLog($logId, true, 'success', null, ['depositId' => $depositId]);
            $this->respondCallbackSuccess();
        }

        if ($status === 'rejected') {
            $reasonText = 'Deposit rejected by IBeePay callback';
            //    spRejectDeposit 强制 status='pending'，PSP 在已完成的 deposit 上回 rejected 是异常情况，吞掉异常仅记 log
            try {
                $this->paymentService->markDepositRejected($deposit, 'reject', 0, [
                    'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('deposit'),
                    'rejectionNotes' => $reasonText,
                    'customReason' => $reasonText,
                ]);
            } catch (Exception $e) {
                Logger::error('Deposit reject via PSP callback failed for deposit ' . $depositId . ': ' . $e->getMessage());
            }
            // 2) 单独写一条 PSP callback 审计 log（和 reject 解耦）
            $this->paymentService->logProcessorCallbackFailure('deposit', $deposit, 'ibeepay', $reasonText, $normalized['raw'] ?? null);

            $this->finishCallbackLog($logId, true, 'success', null, ['depositId' => $depositId]);
            $this->respondCallbackSuccess();
        }

        if ($status === 'canceled' || $status === 'cancelled') {
            $this->markDepositStatusIfAllowed($deposit, 'cancelled', 'Deposit cancelled by IBeePay callback');
            $updatedDeposit = $this->depositModel->getDepositDetails($depositId) ?: $deposit;
            $this->sendDepositStatusNotice($updatedDeposit, 'cancelled', [
                'operatorId' => 0,
                'reason' => 'Deposit cancelled by IBeePay callback'
            ]);
            $this->finishCallbackLog($logId, true, 'success', null, ['depositId' => $depositId]);
            $this->respondCallbackSuccess();
        }

        $this->finishCallbackLog($logId, false, 'ignored', 'Unsupported status', ['depositId' => $depositId]);
        $this->respondCallbackFailure();
    }

    /**
     * 处理 IBeePay withdrawal callback。
     */
    public function ibeepayWithdrawalCallback() {
        $payload = $this->readPayload();
        $normalized = $this->normalizeIbeepayPayload($payload);
        if (!$this->hasMeaningfulIbeepayCallbackStructure($normalized)) {
            $this->respondCallbackFailure();
        }

        if (!$this->isAllowedCallbackIpByConfigKey('ibeepay_whitelist_ip_array')) {
            $this->respondCallbackSuccess();
        }

        $validation = $this->validateIbeepayCallbackAuth($normalized);
        $logId = $this->createCallbackLog('withdrawal', $normalized, $validation['valid']);

        if (!$validation['valid']) {
            $this->finishCallbackLog($logId, false, 'failed', $validation['error'], []);
            $this->respondCallbackFailure();
        }

        $withdrawalId = null;
        $withdrawal = null;
        if ($normalized['orderId'] !== '') {
            $withdrawal = $this->findWithdrawalByOrderId($normalized['orderId']);
            if ($withdrawal) {
                $withdrawalId = (int)$withdrawal['id'];
            }
        }

        if ($this->isIbeepayWithdrawalCallbackAlreadyRecorded($withdrawal, $normalized['status'])) {
            $processResult = (
                $withdrawal
                && $normalized['status'] === 'approved'
                && (string)($withdrawal['status'] ?? '') === 'completed'
            ) ? 'success' : 'ignored';
            $errorMessage = $processResult === 'success' ? null : 'Duplicate callback ignored';
            $this->finishCallbackLog($logId, true, $processResult, $errorMessage, ['withdrawalId' => $withdrawalId]);
            $this->respondCallbackSuccess();
        }

        if ($normalized['status'] === 'rejected' && $withdrawal) {
            $reasonText = 'Withdrawal rejected by IBeePay callback';
            $errorMessage = null;
            // 1) 走 admin reject 同一条路径：rollback 平台扣款 + spRejectWithdrawal + 通知 + balance sync
            //    spRejectWithdrawal 不强制状态，approved/pending 都能 reject
            try {
                $this->paymentService->markWithdrawalRejected($withdrawal, 'reject', 0, [
                    'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('withdrawal'),
                    'rejectionNotes' => $reasonText,
                    'customReason' => $reasonText,
                ]);
            } catch (RuntimeException $e) {
                $errorMessage = $e->getMessage();
            }
            // 2) 单独写一条 PSP callback 审计 log（和 reject 解耦）
            $this->paymentService->logProcessorCallbackFailure('withdrawal', $withdrawal, 'ibeepay', $reasonText, $normalized['raw'] ?? null);

            $this->finishCallbackLog(
                $logId,
                true,
                'success',
                $errorMessage,
                ['withdrawalId' => $withdrawalId]
            );
            $this->respondCallbackSuccess();
        }

        if ($normalized['status'] === 'approved' && $withdrawal) {
            if (($withdrawal['status'] ?? '') !== 'completed') {
                $db = Database::getInstance();
                $db->query(
                    'CALL spCompleteWithdrawal(:withdrawalId, :completedBy, :transactionHash)',
                    [
                        'withdrawalId' => $withdrawalId,
                        'completedBy' => 0,
                        'transactionHash' => $normalized['orderId'] !== '' ? $normalized['orderId'] : null
                    ]
                );
            }

            $updatedWithdrawal = $this->withdrawalModel->getWithdrawalDetails($withdrawalId) ?: $withdrawal;
            $this->sendWithdrawalStatusNotice($updatedWithdrawal, 'completed', [
                'operatorId' => 0
            ]);
            $this->finishCallbackLog($logId, true, 'success', null, ['withdrawalId' => $withdrawalId]);
            $this->respondCallbackSuccess();
        }

        $this->finishCallbackLog(
            $logId,
            false,
            'failed',
            $withdrawal ? 'Unsupported status' : 'Withdrawal not found',
            ['withdrawalId' => $withdrawalId]
        );
        $this->respondCallbackFailure();
    }

    /**
     * 处理 Payment Asia deposit callback。
     */
    public function paymentAsiaDepositCallback() {
        $payload = $this->readPayload();
        $normalized = $this->normalizePaymentAsiaPayload($payload);
        if (!$this->hasMeaningfulPaymentAsiaCallbackStructure($normalized)) {
            $this->respondCallbackFailure();
        }

        if (!$this->isAllowedCallbackIpByConfigKey('paymentasia_whitelist_ip_array')) {
            $this->respondCallbackSuccess();
        }

        if ($normalized['merchantReference'] === '' || $normalized['status'] === '' || $normalized['sign'] === '') {
            $logId = $this->createCallbackLog('deposit', $normalized, false, 'payment_asia');
            $this->finishCallbackLog($logId, false, 'failed', 'merchant_reference, status and sign are required', []);
            $this->respondCallbackFailure();
        }

        $deposit = $this->findDepositByOrderId($normalized['merchantReference']);
        $logId = $this->createCallbackLog('deposit', $normalized, false, 'payment_asia');
        if (!$deposit) {
            $this->finishCallbackLog($logId, false, 'failed', 'Deposit not found', []);
            $this->respondCallbackFailure();
        }

        $gateway = $this->findPaymentAsiaGatewayForDeposit($deposit);
        if (!$gateway) {
            $this->finishCallbackLog($logId, false, 'failed', 'Payment Asia gateway not found', ['depositId' => (int)$deposit['id']]);
            $this->respondCallbackFailure();
        }

        $validation = $this->validatePaymentAsiaCallbackAuth($normalized, $gateway);
        if (!$validation['valid']) {
            $this->finishCallbackLog($logId, false, 'failed', $validation['error'], ['depositId' => (int)$deposit['id']]);
            $this->respondCallbackFailure();
        }

        $depositId = (int)$deposit['id'];
        $expectedAmount = isset($deposit['quotedAmount']) && $deposit['quotedAmount'] !== null
            ? number_format((float)$deposit['quotedAmount'], 2, '.', '')
            : number_format((float)($deposit['amount'] ?? 0), 2, '.', '');
        $callbackAmount = $this->normalizePaymentAsiaAmount($normalized['amount']);
        if ($callbackAmount === '' || bccomp($expectedAmount, $callbackAmount, 2) !== 0) {
            $this->finishCallbackLog($logId, false, 'failed', 'Amount mismatch', ['depositId' => $depositId]);
            $this->respondCallbackFailure();
        }

        $expectedCurrency = strtoupper(trim((string)($deposit['currencyCode'] ?? '')));
        if ($expectedCurrency === '' || $expectedCurrency !== $normalized['currency']) {
            $this->finishCallbackLog($logId, false, 'failed', 'Currency mismatch', ['depositId' => $depositId]);
            $this->respondCallbackFailure();
        }

        $this->persistPaymentAsiaDepositCallbackData($depositId, $normalized);

        if ($this->isPaymentAsiaDepositCallbackAlreadyApplied($deposit, $normalized['status'])) {
            $this->finishCallbackLog($logId, true, 'ignored', 'Duplicate callback ignored', ['depositId' => $depositId]);
            $this->respondCallbackSuccess();
        }

        if ($normalized['status'] === '1') {
            if (($deposit['status'] ?? '') !== 'completed') {
                try {
                    $this->paymentService->markDepositSuccess(
                        $deposit,
                        0,
                        'Auto-approved by Payment Asia callback',
                        'callback'
                    );
                } catch (Exception $e) {
                    $this->finishCallbackLog(
                        $logId,
                        false,
                        'failed',
                        'Failed to approve deposit callback: ' . $e->getMessage(),
                        ['depositId' => $depositId]
                    );
                    $this->respondCallbackFailure();
                }
            }

            $this->finishCallbackLog($logId, true, 'success', null, ['depositId' => $depositId]);
            $this->respondCallbackSuccess();
        }

        if ($normalized['status'] === '2') {
            $reasonText = 'Deposit rejected by Payment Asia callback';
            try {
                $this->paymentService->markDepositRejected($deposit, 'reject', 0, [
                    'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('deposit'),
                    'rejectionNotes' => $reasonText,
                    'customReason' => $reasonText,
                ]);
            } catch (Exception $e) {
                Logger::error('Deposit reject via PSP callback failed for deposit ' . $depositId . ': ' . $e->getMessage());
            }
            $this->paymentService->logProcessorCallbackFailure('deposit', $deposit, 'payment_asia', $reasonText, $normalized['raw'] ?? null);

            $this->finishCallbackLog($logId, true, 'success', null, ['depositId' => $depositId]);
            $this->respondCallbackSuccess();
        }

        if (in_array($normalized['status'], ['0', '3', '4'], true)) {
            $this->finishCallbackLog(
                $logId,
                true,
                'ignored',
                'Callback recorded without order status update',
                ['depositId' => $depositId]
            );
            $this->respondCallbackSuccess();
        }

        $this->finishCallbackLog($logId, false, 'ignored', 'Unsupported status', ['depositId' => $depositId]);
        $this->respondCallbackFailure();
    }

    /**
     * 处理 Payment Asia withdrawal callback。
     */
    public function paymentAsiaWithdrawalCallback() {
        $payload = $this->readPayload();
        $normalized = $this->normalizePaymentAsiaPayload($payload);
        if (!$this->hasMeaningfulPaymentAsiaCallbackStructure($normalized)) {
            $this->respondCallbackFailure();
        }

        if (!$this->isAllowedCallbackIpByConfigKey('paymentasia_whitelist_ip_array')) {
            $this->respondCallbackSuccess();
        }

        if ($normalized['requestReference'] === '' || $normalized['status'] === '' || $normalized['sign'] === '') {
            $logId = $this->createCallbackLog('withdrawal', $normalized, false, 'payment_asia');
            $this->finishCallbackLog($logId, false, 'failed', 'request_reference, status and sign are required', []);
            $this->respondCallbackFailure();
        }

        $withdrawal = $this->findPaymentAsiaWithdrawalByRequestReference($normalized['requestReference']);
        $logId = $this->createCallbackLog('withdrawal', $normalized, false, 'payment_asia');
        if (!$withdrawal) {
            $this->finishCallbackLog($logId, false, 'failed', 'Withdrawal not found', []);
            $this->respondCallbackFailure();
        }

        $gateway = $this->findPaymentAsiaGatewayForWithdrawal($withdrawal);
        if (!$gateway) {
            $this->finishCallbackLog($logId, false, 'failed', 'Payment Asia gateway not found', ['withdrawalId' => (int)$withdrawal['id']]);
            $this->respondCallbackFailure();
        }

        $validation = $this->validatePaymentAsiaWithdrawalCallbackAuth($normalized, $gateway);
        if (!$validation['valid']) {
            $this->finishCallbackLog($logId, false, 'failed', $validation['error'], ['withdrawalId' => (int)$withdrawal['id']]);
            $this->respondCallbackFailure();
        }

        $withdrawalId = (int)$withdrawal['id'];

        if ($this->isPaymentAsiaWithdrawalCallbackAlreadyRecorded($withdrawal, $normalized['status'])) {
            $processResult = (
                $normalized['status'] === '1'
                && (string)($withdrawal['status'] ?? '') === 'completed'
            ) ? 'success' : 'ignored';
            $errorMessage = $processResult === 'success' ? null : 'Duplicate callback ignored';
            $this->finishCallbackLog($logId, true, $processResult, $errorMessage, ['withdrawalId' => $withdrawalId]);
            $this->respondCallbackSuccess();
        }

        if ($normalized['status'] === '1') {
            if (($withdrawal['status'] ?? '') !== 'completed') {
                $db = Database::getInstance();
                $db->query(
                    'CALL spCompleteWithdrawal(:withdrawalId, :transactionHash, :completedBy)',
                    [
                        'withdrawalId' => $withdrawalId,
                        'transactionHash' => $normalized['requestReference'],
                        'completedBy' => 0
                    ]
                );
            }

            $updatedWithdrawal = $this->withdrawalModel->getWithdrawalDetails($withdrawalId) ?: $withdrawal;
            $this->sendWithdrawalStatusNotice($updatedWithdrawal, 'completed', [
                'operatorId' => 0
            ]);
            $this->finishCallbackLog($logId, true, 'success', null, ['withdrawalId' => $withdrawalId]);
            $this->respondCallbackSuccess();
        }

        if ($normalized['status'] === '2' || $normalized['status'] === '8') {
            $reasonText = $normalized['status'] === '8'
                ? 'Withdrawal cancelled by Payment Asia callback'
                : ($normalized['failReason'] !== ''
                    ? 'Withdrawal rejected by Payment Asia callback: ' . $normalized['failReason']
                    : 'Withdrawal rejected by Payment Asia callback');

            $errorMessage = null;
            try {
                $this->paymentService->markWithdrawalRejected($withdrawal, 'reject', 0, [
                    'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('withdrawal'),
                    'rejectionNotes' => $reasonText,
                    'customReason' => $reasonText,
                ]);
            } catch (RuntimeException $e) {
                $errorMessage = $e->getMessage();
            }
            $this->paymentService->logProcessorCallbackFailure('withdrawal', $withdrawal, 'payment_asia', $reasonText, $normalized['raw'] ?? null);

            $this->finishCallbackLog(
                $logId,
                true,
                'success',
                $errorMessage,
                ['withdrawalId' => $withdrawalId]
            );
            $this->respondCallbackSuccess();
        }

        if (in_array($normalized['status'], ['0', '3', '4'], true)) {
            $this->finishCallbackLog(
                $logId,
                true,
                'ignored',
                'Callback recorded without order status update',
                ['withdrawalId' => $withdrawalId]
            );
            $this->respondCallbackSuccess();
        }

        $this->finishCallbackLog($logId, false, 'ignored', 'Unsupported status', ['withdrawalId' => $withdrawalId]);
        $this->respondCallbackFailure();
    }

    /**
     * Vexora deposit callback (Korea + Cambodia)
     * - JSON POST：platFormTradeNo / tradeNo / amount / status / message / successTime / timestamp / sign
     * - 验签：除 sign 外非空字段 a-z 排序拼 key=value + secret 的小写 MD5（VexoraService::verifySign）
     * - 状态：0000 成功；0001 部分支付（只记录，人工处理）；0015 处理中；00029 及其它 = 失败
     * - 必须回纯文本 "OK"（10 秒内），否则 Vexora 按 2s→5s→10s→30s→1h→3h→3h→3h 重试 8 次
     */
    public function vexoraDepositCallback() {
        $payload = $this->readPayload();
        $normalized = $this->normalizeVexoraPayload($payload);
        if ($normalized['tradeNo'] === '' && $normalized['platFormTradeNo'] === '' && $normalized['sign'] === '') {
            $this->respondVexoraFailure();
        }

        if (!$this->isAllowedCallbackIpByConfigKey('vexora_whitelist_ip_array')) {
            // 白名单外来源：直接 ACK，不给攻击者重试信号，也不进业务流程
            $this->respondVexoraAck();
        }

        if ($normalized['tradeNo'] === '' || $normalized['status'] === '' || $normalized['sign'] === '') {
            $logId = $this->createCallbackLog('deposit', $normalized, false, 'vexora');
            $this->finishCallbackLog($logId, false, 'failed', 'tradeNo, status and sign are required', []);
            $this->respondVexoraFailure();
        }

        $deposit = $this->findVexoraDepositByTradeNo($normalized['tradeNo'], $normalized['platFormTradeNo']);
        $logId = $this->createCallbackLog('deposit', $normalized, false, 'vexora');
        if (!$deposit) {
            $this->finishCallbackLog($logId, false, 'failed', 'Deposit not found', []);
            $this->respondVexoraFailure();
        }

        $gateway = $this->findVexoraGatewayBySettingId((int)($deposit['gatewaySettingId'] ?? 0));
        if (!$gateway) {
            $this->finishCallbackLog($logId, false, 'failed', 'Vexora gateway not found', ['depositId' => (int)$deposit['id']]);
            $this->respondVexoraFailure();
        }

        $service = new VexoraService($gateway);
        if (!$service->verifySign($normalized['raw'])) {
            $this->finishCallbackLog($logId, false, 'failed', 'Invalid callback sign', ['depositId' => (int)$deposit['id']]);
            $this->respondVexoraFailure();
        }

        $depositId = (int)$deposit['id'];
        $mappedStatus = VexoraService::mapStatus($normalized['status']);

        if ($mappedStatus === 'success' && (string)($deposit['status'] ?? '') === 'completed') {
            $this->finishCallbackLog($logId, true, 'ignored', 'Duplicate callback ignored', ['depositId' => $depositId]);
            $this->respondVexoraAck();
        }

        if ($mappedStatus === 'success') {
            $decimals = (new VexoraService($gateway))->getAmountDecimalPlaces();
            $expectedAmount = number_format(
                round((float)($deposit['quotedAmount'] ?? $deposit['amount'] ?? 0), $decimals),
                $decimals,
                '.',
                ''
            );
            $callbackAmount = $normalized['amount'] === ''
                ? ''
                : number_format(round((float)$normalized['amount'], $decimals), $decimals, '.', '');
            if ($callbackAmount === '' || $callbackAmount !== $expectedAmount) {
                $reasonText = 'Vexora callback amount mismatch: expected ' . $expectedAmount . ', got ' . $normalized['amount'];
                $this->paymentService->logProcessorCallbackFailure('deposit', $deposit, 'vexora', $reasonText, $normalized['raw'] ?? null);
                $this->finishCallbackLog($logId, false, 'failed', $reasonText, ['depositId' => $depositId]);
                $this->respondVexoraAck();
            }

            try {
                $this->paymentService->markDepositSuccess(
                    $deposit,
                    0,
                    'Auto-approved by Vexora callback',
                    'callback'
                );
            } catch (Exception $e) {
                $this->finishCallbackLog($logId, false, 'failed', 'Failed to approve deposit callback: ' . $e->getMessage(), ['depositId' => $depositId]);
                $this->respondVexoraFailure();
            }

            $this->finishCallbackLog($logId, true, 'success', null, ['depositId' => $depositId]);
            $this->respondVexoraAck();
        }

        if ($mappedStatus === 'partial') {
            $reasonText = 'Vexora partial payment received: ' . $normalized['amount'] . ' (' . $normalized['message'] . ')';
            $this->paymentService->logProcessorCallbackFailure('deposit', $deposit, 'vexora', $reasonText, $normalized['raw'] ?? null);
            $this->finishCallbackLog($logId, true, 'ignored', $reasonText, ['depositId' => $depositId]);
            $this->respondVexoraAck();
        }

        if ($mappedStatus === 'processing') {
            $this->finishCallbackLog($logId, true, 'ignored', 'Callback recorded without order status update', ['depositId' => $depositId]);
            $this->respondVexoraAck();
        }

        if ($mappedStatus === 'failed') {
            $reasonText = $normalized['message'] !== ''
                ? 'Deposit failed by Vexora callback: ' . $normalized['message']
                : 'Deposit failed by Vexora callback';
            try {
                $this->paymentService->markDepositRejected($deposit, 'reject', 0, [
                    'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('deposit'),
                    'rejectionNotes' => $reasonText,
                    'customReason' => $reasonText,
                ]);
            } catch (Exception $e) {
                Logger::error('Deposit reject via Vexora callback failed for deposit ' . $depositId . ': ' . $e->getMessage());
            }
            $this->paymentService->logProcessorCallbackFailure('deposit', $deposit, 'vexora', $reasonText, $normalized['raw'] ?? null);

            $this->finishCallbackLog($logId, true, 'success', null, ['depositId' => $depositId]);
            $this->respondVexoraAck();
        }

        $this->finishCallbackLog($logId, false, 'ignored', 'Unsupported status', ['depositId' => $depositId]);
        $this->respondVexoraAck();
    }

    /**
     * Vexora withdrawal callback (Korea + Cambodia).
     * 出金在 approve 时已 markWithdrawalSuccess；这里 0000 只记录确认，
     * 00029/其它失败码走 markWithdrawalRejected 回滚。
     */
    public function vexoraWithdrawalCallback() {
        $payload = $this->readPayload();
        $normalized = $this->normalizeVexoraPayload($payload);
        if ($normalized['tradeNo'] === '' && $normalized['platFormTradeNo'] === '' && $normalized['sign'] === '') {
            $this->respondVexoraFailure();
        }

        if (!$this->isAllowedCallbackIpByConfigKey('vexora_whitelist_ip_array')) {
            $this->respondVexoraAck();
        }

        if ($normalized['tradeNo'] === '' || $normalized['status'] === '' || $normalized['sign'] === '') {
            $logId = $this->createCallbackLog('withdrawal', $normalized, false, 'vexora');
            $this->finishCallbackLog($logId, false, 'failed', 'tradeNo, status and sign are required', []);
            $this->respondVexoraFailure();
        }

        $withdrawal = $this->findVexoraWithdrawalByTradeNo(
            $normalized['tradeNo'],
            $normalized['platFormTradeNo']
        );
        $logId = $this->createCallbackLog('withdrawal', $normalized, false, 'vexora');
        if (!$withdrawal) {
            $this->finishCallbackLog($logId, false, 'failed', 'Withdrawal not found', []);
            $this->respondVexoraFailure();
        }

        $gateway = $this->findVexoraGatewayBySettingId((int)($withdrawal['gatewaySettingId'] ?? 0));
        if (!$gateway) {
            $this->finishCallbackLog($logId, false, 'failed', 'Vexora gateway not found', ['withdrawalId' => (int)$withdrawal['id']]);
            $this->respondVexoraFailure();
        }

        $service = new VexoraService($gateway);
        if (!$service->verifySign($normalized['raw'])) {
            $this->finishCallbackLog($logId, false, 'failed', 'Invalid callback sign', ['withdrawalId' => (int)$withdrawal['id']]);
            $this->respondVexoraFailure();
        }

        $withdrawalId = (int)$withdrawal['id'];
        $mappedStatus = VexoraService::mapStatus($normalized['status']);

        if ($mappedStatus === 'success') {
            if (($withdrawal['status'] ?? '') !== 'completed') {
                $db = Database::getInstance();
                $db->query(
                    'CALL spCompleteWithdrawal(:withdrawalId, :transactionHash, :completedBy)',
                    [
                        'withdrawalId' => $withdrawalId,
                        'transactionHash' => $normalized['platFormTradeNo'] !== '' ? $normalized['platFormTradeNo'] : $normalized['tradeNo'],
                        'completedBy' => 0
                    ]
                );
                $updatedWithdrawal = $this->withdrawalModel->getWithdrawalDetails($withdrawalId) ?: $withdrawal;
                $this->sendWithdrawalStatusNotice($updatedWithdrawal, 'completed', ['operatorId' => 0]);
            }
            $this->finishCallbackLog($logId, true, 'success', null, ['withdrawalId' => $withdrawalId]);
            $this->respondVexoraAck();
        }

        if ($mappedStatus === 'processing') {
            $this->finishCallbackLog($logId, true, 'ignored', 'Callback recorded without order status update', ['withdrawalId' => $withdrawalId]);
            $this->respondVexoraAck();
        }

        if ($mappedStatus === 'failed') {
            $reasonText = $normalized['message'] !== ''
                ? 'Withdrawal rejected by Vexora callback: ' . $normalized['message']
                : 'Withdrawal rejected by Vexora callback';

            $errorMessage = null;
            try {
                $this->paymentService->markWithdrawalRejected($withdrawal, 'reject', 0, [
                    'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('withdrawal'),
                    'rejectionNotes' => $reasonText,
                    'customReason' => $reasonText,
                ]);
            } catch (RuntimeException $e) {
                $errorMessage = $e->getMessage();
            }
            $this->paymentService->logProcessorCallbackFailure('withdrawal', $withdrawal, 'vexora', $reasonText, $normalized['raw'] ?? null);

            $this->finishCallbackLog($logId, true, 'success', $errorMessage, ['withdrawalId' => $withdrawalId]);
            $this->respondVexoraAck();
        }

        $this->finishCallbackLog($logId, false, 'ignored', 'Unsupported status', ['withdrawalId' => $withdrawalId]);
        $this->respondVexoraAck();
    }

    public function flashpayDepositCallback() {
        $payload = $this->readPayload();
        Logger::info('FlashPay deposit callback received', [
            'domain' => 'payment',
            'requestMethod' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
            'requestUri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
            'queryString' => (string)($_SERVER['QUERY_STRING'] ?? ''),
            'getPath' => (string)($_GET['path'] ?? ''),
            'payloadKeys' => array_keys($payload),
            'mchOrderNo' => (string)($payload['mchOrderNo'] ?? ''),
            'payOrderId' => (string)($payload['payOrderId'] ?? ''),
            'state' => (string)($payload['state'] ?? ''),
            'amount' => (string)($payload['amount'] ?? ''),
            'userAgent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'remoteAddr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        ], true);
        $normalized = $this->normalizeFlashPayPayload($payload);
        if ($normalized['mchOrderNo'] === '' && $normalized['payOrderId'] === '' && $normalized['sign'] === '') {
            $this->respondFlashPayFailure();
        }

        if (!$this->isAllowedCallbackIpByConfigKey('flashpay_whitelist_ip_array')) {
            $this->respondFlashPayAck();
        }

        if ($normalized['mchOrderNo'] === '' || $normalized['state'] === '' || $normalized['sign'] === '') {
            $logId = $this->createCallbackLog('deposit', $normalized, false, 'flashpay');
            $this->finishCallbackLog($logId, false, 'failed', 'mchOrderNo, state and sign are required', []);
            $this->respondFlashPayFailure();
        }

        $deposit = $this->findFlashPayDepositByOrderNo($normalized['mchOrderNo'], $normalized['payOrderId']);
        $logId = $this->createCallbackLog('deposit', $normalized, false, 'flashpay');
        if (!$deposit) {
            $this->finishCallbackLog($logId, false, 'failed', 'Deposit not found', []);
            $this->respondFlashPayFailure();
        }

        $gateway = $this->findFlashPayGatewayBySettingId((int)($deposit['gatewaySettingId'] ?? 0));
        if (!$gateway) {
            $this->finishCallbackLog($logId, false, 'failed', 'FlashPay gateway not found', ['depositId' => (int)$deposit['id']]);
            $this->respondFlashPayFailure();
        }

        $service = new FlashPayService($gateway);
        if (!$service->verifySign($normalized['raw'])) {
            $this->finishCallbackLog($logId, false, 'failed', 'Invalid callback sign', ['depositId' => (int)$deposit['id']]);
            $this->respondFlashPayFailure();
        }

        $depositId = (int)$deposit['id'];
        $mappedStatus = FlashPayService::mapPayInState($normalized['state']);

        if ($mappedStatus === 'success' && (string)($deposit['status'] ?? '') === 'completed') {
            $this->finishCallbackLog($logId, true, 'ignored', 'Duplicate callback ignored', ['depositId' => $depositId]);
            $this->respondFlashPayAck();
        }

        if ($mappedStatus === 'success') {
            $expectedAmount = FlashPayService::toFlashPayAmountCents($deposit['quotedAmount'] ?? $deposit['amount'] ?? 0);
            $callbackAmount = FlashPayService::parseFlashPayAmountCents($normalized['amount']);
            if ($normalized['amount'] === '' || $callbackAmount !== $expectedAmount) {
                $reasonText = 'FlashPay callback amount mismatch: expected ' . $expectedAmount . ', got ' . $normalized['amount'];
                $this->paymentService->logProcessorCallbackFailure('deposit', $deposit, 'flashpay', $reasonText, $normalized['raw'] ?? null);
                $this->finishCallbackLog($logId, false, 'failed', $reasonText, ['depositId' => $depositId]);
                $this->respondFlashPayAck();
            }

            try {
                $this->paymentService->markDepositSuccess(
                    $deposit,
                    0,
                    'Auto-approved by FlashPay callback',
                    'callback'
                );
            } catch (Exception $e) {
                $this->finishCallbackLog($logId, false, 'failed', 'Failed to approve deposit callback: ' . $e->getMessage(), ['depositId' => $depositId]);
                $this->respondFlashPayFailure();
            }

            $this->finishCallbackLog($logId, true, 'success', null, ['depositId' => $depositId]);
            $this->respondFlashPayAck();
        }

        if ($mappedStatus === 'processing') {
            $this->finishCallbackLog($logId, true, 'ignored', 'Callback recorded without order status update', ['depositId' => $depositId]);
            $this->respondFlashPayAck();
        }

        if ($mappedStatus === 'failed') {
            $reasonText = $normalized['errMsg'] !== ''
                ? 'Deposit rejected by FlashPay callback: ' . $normalized['errMsg']
                : 'Deposit rejected by FlashPay callback';
            try {
                $this->paymentService->markDepositRejected($deposit, 'reject', 0, [
                    'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('deposit'),
                    'rejectionNotes' => $reasonText,
                    'customReason' => $reasonText,
                ]);
            } catch (Throwable $e) {
                $this->finishCallbackLog($logId, false, 'failed', $e->getMessage(), ['depositId' => $depositId]);
                $this->respondFlashPayFailure();
            }
            $this->paymentService->logProcessorCallbackFailure('deposit', $deposit, 'flashpay', $reasonText, $normalized['raw'] ?? null);
            $this->finishCallbackLog($logId, true, 'success', null, ['depositId' => $depositId]);
            $this->respondFlashPayAck();
        }

        $this->finishCallbackLog($logId, false, 'ignored', 'Unsupported status', ['depositId' => $depositId]);
        $this->respondFlashPayAck();
    }

    public function flashpayWithdrawalCallback() {
        $payload = $this->readPayload();
        $normalized = $this->normalizeFlashPayPayload($payload);
        if ($normalized['mchOrderNo'] === '' && $normalized['transferId'] === '' && $normalized['sign'] === '') {
            $this->respondFlashPayFailure();
        }

        if (!$this->isAllowedCallbackIpByConfigKey('flashpay_whitelist_ip_array')) {
            $this->respondFlashPayAck();
        }

        if ($normalized['mchOrderNo'] === '' || $normalized['state'] === '' || $normalized['sign'] === '') {
            $logId = $this->createCallbackLog('withdrawal', $normalized, false, 'flashpay');
            $this->finishCallbackLog($logId, false, 'failed', 'mchOrderNo, state and sign are required', []);
            $this->respondFlashPayFailure();
        }

        $withdrawal = $this->findFlashPayWithdrawalByOrderNo(
            $normalized['mchOrderNo'],
            $normalized['transferId']
        );
        $logId = $this->createCallbackLog('withdrawal', $normalized, false, 'flashpay');
        if (!$withdrawal) {
            $this->finishCallbackLog($logId, false, 'failed', 'Withdrawal not found', []);
            $this->respondFlashPayFailure();
        }

        $gateway = $this->findFlashPayGatewayBySettingId((int)($withdrawal['gatewaySettingId'] ?? 0));
        if (!$gateway) {
            $this->finishCallbackLog($logId, false, 'failed', 'FlashPay gateway not found', ['withdrawalId' => (int)$withdrawal['id']]);
            $this->respondFlashPayFailure();
        }

        $service = new FlashPayService($gateway);
        if (!$service->verifySign($normalized['raw'])) {
            $this->finishCallbackLog($logId, false, 'failed', 'Invalid callback sign', ['withdrawalId' => (int)$withdrawal['id']]);
            $this->respondFlashPayFailure();
        }

        $withdrawalId = (int)$withdrawal['id'];
        $mappedStatus = FlashPayService::mapPayOutState($normalized['state']);

        if ($mappedStatus === 'success') {
            if (($withdrawal['status'] ?? '') !== 'completed') {
                $db = Database::getInstance();
                $db->query(
                    'CALL spCompleteWithdrawal(:withdrawalId, :transactionHash, :completedBy)',
                    [
                        'withdrawalId' => $withdrawalId,
                        'transactionHash' => $normalized['transferId'] !== '' ? $normalized['transferId'] : $normalized['mchOrderNo'],
                        'completedBy' => 0
                    ]
                );
                $updatedWithdrawal = $this->withdrawalModel->getWithdrawalDetails($withdrawalId) ?: $withdrawal;
                $this->sendWithdrawalStatusNotice($updatedWithdrawal, 'completed', ['operatorId' => 0]);
            }
            $this->finishCallbackLog($logId, true, 'success', null, ['withdrawalId' => $withdrawalId]);
            $this->respondFlashPayAck();
        }

        if ($mappedStatus === 'processing') {
            $this->finishCallbackLog($logId, true, 'ignored', 'Callback recorded without order status update', ['withdrawalId' => $withdrawalId]);
            $this->respondFlashPayAck();
        }

        if ($mappedStatus === 'failed') {
            $reasonText = $normalized['errMsg'] !== ''
                ? 'Withdrawal rejected by FlashPay callback: ' . $normalized['errMsg']
                : 'Withdrawal rejected by FlashPay callback';

            $errorMessage = null;
            try {
                $this->paymentService->markWithdrawalRejected($withdrawal, 'reject', 0, [
                    'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('withdrawal'),
                    'rejectionNotes' => $reasonText,
                    'customReason' => $reasonText,
                ]);
            } catch (RuntimeException $e) {
                $errorMessage = $e->getMessage();
            }
            $this->paymentService->logProcessorCallbackFailure('withdrawal', $withdrawal, 'flashpay', $reasonText, $normalized['raw'] ?? null);

            $this->finishCallbackLog($logId, true, 'success', $errorMessage, ['withdrawalId' => $withdrawalId]);
            $this->respondFlashPayAck();
        }

        $this->finishCallbackLog($logId, false, 'ignored', 'Unsupported status', ['withdrawalId' => $withdrawalId]);
        $this->respondFlashPayAck();
    }

    private function normalizeFlashPayPayload($payload) {
        if (!is_array($payload)) {
            $payload = [];
        }

        return [
            'mchOrderNo' => trim((string)($payload['mchOrderNo'] ?? '')),
            'payOrderId' => trim((string)($payload['payOrderId'] ?? '')),
            'transferId' => trim((string)($payload['transferId'] ?? '')),
            'orderId' => trim((string)($payload['mchOrderNo'] ?? '')),
            'amount' => trim((string)($payload['amount'] ?? '')),
            'currency' => trim((string)($payload['currency'] ?? '')),
            'state' => trim((string)($payload['state'] ?? $payload['orderState'] ?? '')),
            'status' => trim((string)($payload['state'] ?? $payload['orderState'] ?? '')),
            'errMsg' => trim((string)($payload['errMsg'] ?? $payload['msg'] ?? '')),
            'sign' => trim((string)($payload['sign'] ?? '')),
            'raw' => $payload
        ];
    }

    private function findFlashPayDepositByOrderNo(string $mchOrderNo, string $payOrderId = '') {
        $db = Database::getInstance();

        if ($mchOrderNo !== '') {
            $record = $db->fetchOne(
                "SELECT * FROM deposits WHERE transactionId = :mchOrderNo OR REPLACE(transactionId, '-', '') = :mchOrderNo ORDER BY id DESC LIMIT 1",
                ['mchOrderNo' => $mchOrderNo]
            );
            if ($record) {
                return $record;
            }
        }

        if ($payOrderId !== '') {
            return $db->fetchOne(
                'SELECT * FROM deposits WHERE gatewayTransactionId = :payOrderId ORDER BY id DESC LIMIT 1',
                ['payOrderId' => $payOrderId]
            );
        }

        return null;
    }

    private function findFlashPayWithdrawalByOrderNo(string $mchOrderNo, string $transferId = '') {
        $db = Database::getInstance();
        $mchOrderNo = trim($mchOrderNo);
        $transferId = trim($transferId);

        if ($mchOrderNo !== '') {
            $withdrawal = $db->fetchOne(
                "SELECT * FROM withdrawals WHERE transactionId = :mchOrderNo OR REPLACE(transactionId, '-', '') = :mchOrderNo ORDER BY id DESC LIMIT 1",
                ['mchOrderNo' => $mchOrderNo]
            );
            if ($withdrawal) {
                return $withdrawal;
            }
        }

        if ($transferId !== '') {
            return $db->fetchOne(
                'SELECT * FROM withdrawals WHERE gatewayTransactionId = :transferId ORDER BY id DESC LIMIT 1',
                ['transferId' => $transferId]
            );
        }

        return null;
    }

    private function findFlashPayGatewayBySettingId(int $gatewaySettingId) {
        if ($gatewaySettingId <= 0) {
            return null;
        }

        $gateway = $this->gatewayModel->findByIdWithSecrets($gatewaySettingId);
        if (!$gateway || empty($gateway['isEnabled'])) {
            return null;
        }

        $gatewayKey = strtolower(trim((string)($gateway['gatewayKey'] ?? '')));
        if (strpos($gatewayKey, 'flashpay') === 0) {
            return $gateway;
        }

        $configData = json_decode((string)($gateway['configData'] ?? ''), true);
        $providerKey = is_array($configData) ? strtolower(trim((string)($configData['providerKey'] ?? ''))) : '';
        return $providerKey === 'flashpay' ? $gateway : null;
    }

    private function respondFlashPayAck() {
        http_response_code(200);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'success';
        exit;
    }

    private function respondFlashPayFailure() {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'FAIL';
        exit;
    }

    public function cvpayDepositCallback() {
        $payload = $this->readPayload();
        Logger::info('CVPay deposit callback received', [
            'domain' => 'payment',
            'requestMethod' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
            'requestUri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
            'queryString' => (string)($_SERVER['QUERY_STRING'] ?? ''),
            'getPath' => (string)($_GET['path'] ?? ''),
            'payloadKeys' => array_keys($payload),
            'mchOrderNo' => (string)($payload['mchOrderNo'] ?? ''),
            'payOrderId' => (string)($payload['payOrderId'] ?? ''),
            'state' => (string)($payload['state'] ?? ''),
            'amount' => (string)($payload['amount'] ?? ''),
            'userAgent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'remoteAddr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        ], true);
        $normalized = $this->normalizeCvPayPayload($payload);
        if ($normalized['mchOrderNo'] === '' && $normalized['payOrderId'] === '' && $normalized['sign'] === '') {
            $this->respondCvPayFailure();
        }

        if (!$this->isAllowedCallbackIpByConfigKey('cvpay_whitelist_ip_array')) {
            $this->respondCvPayAck();
        }

        if ($normalized['mchOrderNo'] === '' || $normalized['state'] === '' || $normalized['sign'] === '') {
            $logId = $this->createCallbackLog('deposit', $normalized, false, 'cvpay');
            $this->finishCallbackLog($logId, false, 'failed', 'mchOrderNo, state and sign are required', []);
            $this->respondCvPayFailure();
        }

        $deposit = $this->findCvPayDepositByOrderNo($normalized['mchOrderNo'], $normalized['payOrderId']);
        $logId = $this->createCallbackLog('deposit', $normalized, false, 'cvpay');
        if (!$deposit) {
            $this->finishCallbackLog($logId, false, 'failed', 'Deposit not found', []);
            $this->respondCvPayFailure();
        }

        $gateway = $this->findCvPayGatewayBySettingId((int)($deposit['gatewaySettingId'] ?? 0));
        if (!$gateway) {
            $this->finishCallbackLog($logId, false, 'failed', 'CVPay gateway not found', ['depositId' => (int)$deposit['id']]);
            $this->respondCvPayFailure();
        }

        $service = new CvPayService($gateway);
        if (!$service->verifySign($normalized['raw'])) {
            $this->finishCallbackLog($logId, false, 'failed', 'Invalid callback sign', ['depositId' => (int)$deposit['id']]);
            $this->respondCvPayFailure();
        }

        $depositId = (int)$deposit['id'];
        $mappedStatus = CvPayService::mapPayInState($normalized['state']);

        if ($mappedStatus === 'success' && (string)($deposit['status'] ?? '') === 'completed') {
            $this->finishCallbackLog($logId, true, 'ignored', 'Duplicate callback ignored', ['depositId' => $depositId]);
            $this->respondCvPayAck();
        }

        if ($mappedStatus === 'success') {
            $expectedAmount = CvPayService::toCvPayAmount($deposit['quotedAmount'] ?? $deposit['amount'] ?? 0);
            $callbackAmount = CvPayService::parseCvPayAmount($normalized['amount']);
            if ($normalized['amount'] === '' || $callbackAmount !== $expectedAmount) {
                $reasonText = 'CVPay callback amount mismatch: expected ' . $expectedAmount . ', got ' . $normalized['amount'];
                $this->paymentService->logProcessorCallbackFailure('deposit', $deposit, 'cvpay', $reasonText, $normalized['raw'] ?? null);
                $this->finishCallbackLog($logId, false, 'failed', $reasonText, ['depositId' => $depositId]);
                $this->respondCvPayAck();
            }

            try {
                $this->paymentService->markDepositSuccess(
                    $deposit,
                    0,
                    'Auto-approved by CVPay callback',
                    'callback'
                );
            } catch (Exception $e) {
                $this->finishCallbackLog($logId, false, 'failed', 'Failed to approve deposit callback: ' . $e->getMessage(), ['depositId' => $depositId]);
                $this->respondCvPayFailure();
            }

            $this->finishCallbackLog($logId, true, 'success', null, ['depositId' => $depositId]);
            $this->respondCvPayAck();
        }

        if ($mappedStatus === 'processing') {
            $this->finishCallbackLog($logId, true, 'ignored', 'Callback recorded without order status update', ['depositId' => $depositId]);
            $this->respondCvPayAck();
        }

        if ($mappedStatus === 'failed') {
            $reasonText = $normalized['errMsg'] !== ''
                ? 'Deposit rejected by CVPay callback: ' . $normalized['errMsg']
                : 'Deposit rejected by CVPay callback';
            try {
                $this->paymentService->markDepositRejected($deposit, 'reject', 0, [
                    'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('deposit'),
                    'rejectionNotes' => $reasonText,
                    'customReason' => $reasonText,
                ]);
            } catch (Throwable $e) {
                $this->finishCallbackLog($logId, false, 'failed', $e->getMessage(), ['depositId' => $depositId]);
                $this->respondCvPayFailure();
            }
            $this->paymentService->logProcessorCallbackFailure('deposit', $deposit, 'cvpay', $reasonText, $normalized['raw'] ?? null);
            $this->finishCallbackLog($logId, true, 'success', null, ['depositId' => $depositId]);
            $this->respondCvPayAck();
        }

        $this->finishCallbackLog($logId, false, 'ignored', 'Unsupported status', ['depositId' => $depositId]);
        $this->respondCvPayAck();
    }

    public function cvpayWithdrawalCallback() {
        $payload = $this->readPayload();
        $normalized = $this->normalizeCvPayPayload($payload);
        if ($normalized['mchOrderNo'] === '' && $normalized['transferId'] === '' && $normalized['sign'] === '') {
            $this->respondCvPayFailure();
        }

        if (!$this->isAllowedCallbackIpByConfigKey('cvpay_whitelist_ip_array')) {
            $this->respondCvPayAck();
        }

        if ($normalized['mchOrderNo'] === '' || $normalized['state'] === '' || $normalized['sign'] === '') {
            $logId = $this->createCallbackLog('withdrawal', $normalized, false, 'cvpay');
            $this->finishCallbackLog($logId, false, 'failed', 'mchOrderNo, state and sign are required', []);
            $this->respondCvPayFailure();
        }

        $withdrawal = $this->findCvPayWithdrawalByOrderNo(
            $normalized['mchOrderNo'],
            $normalized['transferId']
        );
        $logId = $this->createCallbackLog('withdrawal', $normalized, false, 'cvpay');
        if (!$withdrawal) {
            $this->finishCallbackLog($logId, false, 'failed', 'Withdrawal not found', []);
            $this->respondCvPayFailure();
        }

        $gateway = $this->findCvPayGatewayBySettingId((int)($withdrawal['gatewaySettingId'] ?? 0));
        if (!$gateway) {
            $this->finishCallbackLog($logId, false, 'failed', 'CVPay gateway not found', ['withdrawalId' => (int)$withdrawal['id']]);
            $this->respondCvPayFailure();
        }

        $service = new CvPayService($gateway);
        if (!$service->verifySign($normalized['raw'])) {
            $this->finishCallbackLog($logId, false, 'failed', 'Invalid callback sign', ['withdrawalId' => (int)$withdrawal['id']]);
            $this->respondCvPayFailure();
        }

        $withdrawalId = (int)$withdrawal['id'];
        $mappedStatus = CvPayService::mapPayOutState($normalized['state']);

        if ($mappedStatus === 'success') {
            if (($withdrawal['status'] ?? '') !== 'completed') {
                $db = Database::getInstance();
                $db->query(
                    'CALL spCompleteWithdrawal(:withdrawalId, :transactionHash, :completedBy)',
                    [
                        'withdrawalId' => $withdrawalId,
                        'transactionHash' => $normalized['transferId'] !== '' ? $normalized['transferId'] : $normalized['mchOrderNo'],
                        'completedBy' => 0
                    ]
                );
                $updatedWithdrawal = $this->withdrawalModel->getWithdrawalDetails($withdrawalId) ?: $withdrawal;
                $this->sendWithdrawalStatusNotice($updatedWithdrawal, 'completed', ['operatorId' => 0]);
            }
            $this->finishCallbackLog($logId, true, 'success', null, ['withdrawalId' => $withdrawalId]);
            $this->respondCvPayAck();
        }

        if ($mappedStatus === 'processing') {
            $this->finishCallbackLog($logId, true, 'ignored', 'Callback recorded without order status update', ['withdrawalId' => $withdrawalId]);
            $this->respondCvPayAck();
        }

        if ($mappedStatus === 'failed') {
            $currentStatus = (string)($withdrawal['status'] ?? '');
            if (in_array($currentStatus, ['rejected', 'cancelled'], true)) {
                $this->finishCallbackLog($logId, true, 'ignored', 'Duplicate failed callback (already rejected)', ['withdrawalId' => $withdrawalId]);
                $this->respondCvPayAck();
            }

            $reasonText = $normalized['errMsg'] !== ''
                ? 'Withdrawal rejected by CVPay callback: ' . $normalized['errMsg']
                : 'Withdrawal rejected by CVPay callback';

            $errorMessage = null;
            try {
                $this->paymentService->markWithdrawalRejected($withdrawal, 'reject', 0, [
                    'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('withdrawal'),
                    'rejectionNotes' => $reasonText,
                    'customReason' => $reasonText,
                ]);
            } catch (RuntimeException $e) {
                $errorMessage = $e->getMessage();
            }
            $this->paymentService->logProcessorCallbackFailure('withdrawal', $withdrawal, 'cvpay', $reasonText, $normalized['raw'] ?? null);

            $this->finishCallbackLog($logId, true, 'success', $errorMessage, ['withdrawalId' => $withdrawalId]);
            $this->respondCvPayAck();
        }

        $this->finishCallbackLog($logId, false, 'ignored', 'Unsupported status', ['withdrawalId' => $withdrawalId]);
        $this->respondCvPayAck();
    }

    private function normalizeCvPayPayload($payload) {
        if (!is_array($payload)) {
            $payload = [];
        }

        return [
            'mchOrderNo' => trim((string)($payload['mchOrderNo'] ?? '')),
            'payOrderId' => trim((string)($payload['payOrderId'] ?? '')),
            'transferId' => trim((string)($payload['transferId'] ?? '')),
            'orderId' => trim((string)($payload['mchOrderNo'] ?? '')),
            'amount' => trim((string)($payload['amount'] ?? '')),
            'currency' => trim((string)($payload['currency'] ?? '')),
            'state' => trim((string)($payload['state'] ?? $payload['orderState'] ?? '')),
            'status' => trim((string)($payload['state'] ?? $payload['orderState'] ?? '')),
            'errMsg' => trim((string)($payload['errMsg'] ?? $payload['msg'] ?? '')),
            'sign' => trim((string)($payload['sign'] ?? '')),
            'raw' => $payload
        ];
    }

    private function findCvPayDepositByOrderNo(string $mchOrderNo, string $payOrderId = '') {
        $db = Database::getInstance();

        if ($mchOrderNo !== '') {
            $record = $db->fetchOne(
                "SELECT * FROM deposits WHERE transactionId = :mchOrderNo OR REPLACE(transactionId, '-', '') = :mchOrderNo ORDER BY id DESC LIMIT 1",
                ['mchOrderNo' => $mchOrderNo]
            );
            if ($record) {
                return $record;
            }
        }

        if ($payOrderId !== '') {
            return $db->fetchOne(
                'SELECT * FROM deposits WHERE gatewayTransactionId = :payOrderId ORDER BY id DESC LIMIT 1',
                ['payOrderId' => $payOrderId]
            );
        }

        return null;
    }

    private function findCvPayWithdrawalByOrderNo(string $mchOrderNo, string $transferId = '') {
        $db = Database::getInstance();
        $mchOrderNo = trim($mchOrderNo);
        $transferId = trim($transferId);

        if ($mchOrderNo !== '') {
            $withdrawal = $db->fetchOne(
                "SELECT * FROM withdrawals WHERE transactionId = :mchOrderNo OR REPLACE(transactionId, '-', '') = :mchOrderNo ORDER BY id DESC LIMIT 1",
                ['mchOrderNo' => $mchOrderNo]
            );
            if ($withdrawal) {
                return $withdrawal;
            }
        }

        if ($transferId !== '') {
            return $db->fetchOne(
                'SELECT * FROM withdrawals WHERE gatewayTransactionId = :transferId ORDER BY id DESC LIMIT 1',
                ['transferId' => $transferId]
            );
        }

        return null;
    }

    private function findCvPayGatewayBySettingId(int $gatewaySettingId) {
        if ($gatewaySettingId <= 0) {
            return null;
        }

        $gateway = $this->gatewayModel->findByIdWithSecrets($gatewaySettingId);
        if (!$gateway || empty($gateway['isEnabled'])) {
            return null;
        }

        $gatewayKey = strtolower(trim((string)($gateway['gatewayKey'] ?? '')));
        if (strpos($gatewayKey, 'cvpay') === 0) {
            return $gateway;
        }

        $configData = json_decode((string)($gateway['configData'] ?? ''), true);
        $providerKey = is_array($configData) ? strtolower(trim((string)($configData['providerKey'] ?? ''))) : '';
        return $providerKey === 'cvpay' ? $gateway : null;
    }

    private function respondCvPayAck() {
        http_response_code(200);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'success';
        exit;
    }

    private function respondCvPayFailure() {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'FAIL';
        exit;
    }

    public function fivepayDepositCallback() {
        ini_set('display_errors', '0');
        $payload = $this->readPayload();
        Logger::info('5Pay deposit callback received', [
            'domain' => 'payment',
            'requestMethod' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
            'requestUri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
            'payloadKeys' => array_keys($payload),
            'merchantOrderNo' => (string)($payload['MerchantOrderNo'] ?? ''),
            'status' => (string)($payload['Status'] ?? ''),
            'remoteAddr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        ], true);
        $normalized = $this->normalizeFivePayDepositPayload($payload);
        if ($normalized['merchantOrderNo'] === '' && $normalized['orderNo'] === '' && $normalized['sign'] === '') {
            $this->respondFivePayFailure();
        }

        if (!$this->isAllowedCallbackIpByConfigKey('fivepay_whitelist_ip_array')) {
            $this->respondFivePayAck();
        }

        if ($normalized['merchantOrderNo'] === '' || $normalized['status'] === '' || $normalized['sign'] === '') {
            $logId = $this->createCallbackLog('deposit', $normalized, false, '5pay');
            $this->finishCallbackLog($logId, false, 'failed', 'MerchantOrderNo, Status and Sign are required', []);
            $this->respondFivePayFailure();
        }

        $deposit = $this->findFivePayDepositByOrderNo($normalized['merchantOrderNo'], $normalized['orderNo']);
        $logId = $this->createCallbackLog('deposit', $normalized, false, '5pay');
        if (!$deposit) {
            $this->finishCallbackLog($logId, false, 'failed', 'Deposit not found', []);
            $this->respondFivePayFailure();
        }

        $gateway = $this->findFivePayGatewayBySettingId((int)($deposit['gatewaySettingId'] ?? 0));
        if (!$gateway) {
            $this->finishCallbackLog($logId, false, 'failed', '5Pay gateway not found', ['depositId' => (int)$deposit['id']]);
            $this->respondFivePayFailure();
        }

        $service = new FivePayService($gateway);
        if (!$service->verifySign($normalized['raw'])) {
            $this->finishCallbackLog($logId, false, 'failed', 'Invalid callback sign', ['depositId' => (int)$deposit['id']]);
            $this->respondFivePayFailure();
        }

        if (!FivePayService::isTimestampFresh($normalized['timeStamp'])) {
            $this->finishCallbackLog($logId, false, 'failed', 'Stale callback timestamp', ['depositId' => (int)$deposit['id']]);
            $this->respondFivePayFailure();
        }

        $expectedMerchantId = trim((string)($gateway['apiKey'] ?? ''));
        if ($expectedMerchantId !== '' && $normalized['merchantId'] !== '' && $normalized['merchantId'] !== $expectedMerchantId) {
            $this->finishCallbackLog($logId, false, 'failed', 'MerchantId mismatch', ['depositId' => (int)$deposit['id']]);
            $this->respondFivePayFailure();
        }

        $this->markCallbackLogValid($logId);

        $depositId = (int)$deposit['id'];
        $mappedStatus = FivePayService::mapDepositStatus($normalized['status']);

        if ($mappedStatus === 'success' && (string)($deposit['status'] ?? '') === 'completed') {
            $this->finishCallbackLog($logId, true, 'ignored', 'Duplicate callback ignored', ['depositId' => $depositId]);
            $this->respondFivePayAck();
        }

        if ($mappedStatus === 'success') {
            $expectedAmount = FivePayService::formatAmount($deposit['quotedAmount'] ?? $deposit['amount'] ?? 0);
            $callbackAmount = $normalized['orderAmount'] !== ''
                ? FivePayService::formatAmount($normalized['orderAmount'])
                : '';
            if ($callbackAmount !== '' && $callbackAmount !== $expectedAmount) {
                Logger::warning('5Pay deposit callback amount differs from initiated amount', [
                    'depositId' => $depositId,
                    'expectedAmount' => $expectedAmount,
                    'callbackAmount' => $callbackAmount,
                ]);
            }

            try {
                $this->paymentService->markDepositSuccess(
                    $deposit,
                    0,
                    'Auto-approved by 5Pay callback',
                    'callback'
                );
            } catch (Exception $e) {
                $this->finishCallbackLog($logId, false, 'failed', 'Failed to approve deposit callback: ' . $e->getMessage(), ['depositId' => $depositId]);
                $this->respondFivePayFailure();
            }

            $this->finishCallbackLog($logId, true, 'success', null, ['depositId' => $depositId]);
            $this->respondFivePayAck();
        }

        if ($mappedStatus === 'processing') {
            $this->finishCallbackLog($logId, true, 'ignored', 'Callback recorded without order status update', ['depositId' => $depositId]);
            $this->respondFivePayAck();
        }

        if (in_array($mappedStatus, ['expired', 'cancelled'], true)) {
            $reasonText = $mappedStatus === 'expired'
                ? 'Deposit expired by 5Pay callback'
                : 'Deposit cancelled by 5Pay callback';
            try {
                $this->paymentService->markDepositProviderTerminal(
                    $deposit,
                    $mappedStatus,
                    $reasonText,
                    $normalized['status'],
                    '5pay'
                );
            } catch (Throwable $e) {
                $latestDeposit = $this->depositModel->getDepositDetails($depositId) ?: $deposit;
                if (in_array((string)($latestDeposit['status'] ?? ''), [
                    'completed', 'expired', 'cancelled', 'failed', 'rejected'
                ], true)) {
                    Logger::warning('5Pay terminal callback arrived after a terminal CRM status', [
                        'depositId' => $depositId,
                        'currentStatus' => (string)($latestDeposit['status'] ?? ''),
                        'providerStatus' => $normalized['status'],
                    ]);
                    $this->finishCallbackLog($logId, true, 'ignored', $e->getMessage(), ['depositId' => $depositId]);
                    $this->respondFivePayAck();
                }
                $this->finishCallbackLog($logId, false, 'failed', $e->getMessage(), ['depositId' => $depositId]);
                $this->respondFivePayFailure();
            }
            $this->finishCallbackLog($logId, true, 'success', null, ['depositId' => $depositId]);
            $this->respondFivePayAck();
        }

        $this->finishCallbackLog($logId, false, 'ignored', 'Unsupported status', ['depositId' => $depositId]);
        $this->respondFivePayAck();
    }

    public function fivepayWithdrawalCallback() {
        ini_set('display_errors', '0');
        $payload = $this->readPayload();
        $normalized = $this->normalizeFivePayWithdrawalPayload($payload);
        if ($normalized['merchantOrderNo'] === '' && $normalized['withdrawalId'] === '' && $normalized['sign'] === '') {
            $this->respondFivePayFailure();
        }

        if (!$this->isAllowedCallbackIpByConfigKey('fivepay_whitelist_ip_array')) {
            $this->respondFivePayAck();
        }

        if ($normalized['merchantOrderNo'] === '' || $normalized['status'] === '' || $normalized['sign'] === '') {
            $logId = $this->createCallbackLog('withdrawal', $normalized, false, '5pay');
            $this->finishCallbackLog($logId, false, 'failed', 'MerchantOrderNo, Status and Sign are required', []);
            $this->respondFivePayFailure();
        }

        $withdrawal = $this->findFivePayWithdrawalByOrderNo(
            $normalized['merchantOrderNo'],
            $normalized['withdrawalId']
        );
        $logId = $this->createCallbackLog('withdrawal', $normalized, false, '5pay');
        if (!$withdrawal) {
            $this->finishCallbackLog($logId, false, 'failed', 'Withdrawal not found', []);
            $this->respondFivePayFailure();
        }

        $gateway = $this->findFivePayGatewayBySettingId((int)($withdrawal['gatewaySettingId'] ?? 0));
        if (!$gateway) {
            $this->finishCallbackLog($logId, false, 'failed', '5Pay gateway not found', ['withdrawalId' => (int)$withdrawal['id']]);
            $this->respondFivePayFailure();
        }

        $service = new FivePayService($gateway);
        if (!$service->verifySign($normalized['raw'])) {
            $this->finishCallbackLog($logId, false, 'failed', 'Invalid callback sign', ['withdrawalId' => (int)$withdrawal['id']]);
            $this->respondFivePayFailure();
        }

        if (!FivePayService::isTimestampFresh($normalized['timeStamp'])) {
            $this->finishCallbackLog($logId, false, 'failed', 'Stale callback timestamp', ['withdrawalId' => (int)$withdrawal['id']]);
            $this->respondFivePayFailure();
        }

        $expectedMerchantId = trim((string)($gateway['apiKey'] ?? ''));
        if ($expectedMerchantId !== '' && $normalized['merchantId'] !== '' && $normalized['merchantId'] !== $expectedMerchantId) {
            $this->finishCallbackLog($logId, false, 'failed', 'MerchantId mismatch', ['withdrawalId' => (int)$withdrawal['id']]);
            $this->respondFivePayFailure();
        }

        $this->markCallbackLogValid($logId);

        $withdrawalId = (int)$withdrawal['id'];
        $mappedStatus = FivePayService::mapPayoutStatus($normalized['status']);

        if ($mappedStatus === 'success') {
            if (($withdrawal['status'] ?? '') !== 'completed') {
                $db = Database::getInstance();
                $db->query(
                    'CALL spCompleteWithdrawal(:withdrawalId, :transactionHash, :completedBy)',
                    [
                        'withdrawalId' => $withdrawalId,
                        'transactionHash' => $normalized['withdrawalId'] !== '' ? $normalized['withdrawalId'] : $normalized['merchantOrderNo'],
                        'completedBy' => 0
                    ]
                );
                $updatedWithdrawal = $this->withdrawalModel->getWithdrawalDetails($withdrawalId) ?: $withdrawal;
                $this->sendWithdrawalStatusNotice($updatedWithdrawal, 'completed', ['operatorId' => 0]);
            }
            $this->finishCallbackLog($logId, true, 'success', null, ['withdrawalId' => $withdrawalId]);
            $this->respondFivePayAck();
        }

        if ($mappedStatus === 'processing') {
            $this->finishCallbackLog($logId, true, 'ignored', 'Callback recorded without order status update', ['withdrawalId' => $withdrawalId]);
            $this->respondFivePayAck();
        }

        if ($mappedStatus === 'failed') {
            $reasonText = $normalized['rejectedReason'] !== ''
                ? 'Withdrawal rejected by 5Pay callback: ' . $normalized['rejectedReason']
                : 'Withdrawal rejected by 5Pay callback';

            $errorMessage = null;
            try {
                $this->paymentService->markWithdrawalRejected($withdrawal, 'reject', 0, [
                    'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('withdrawal'),
                    'rejectionNotes' => $reasonText,
                    'customReason' => $reasonText,
                ]);
            } catch (RuntimeException $e) {
                $errorMessage = $e->getMessage();
            }
            $this->paymentService->logProcessorCallbackFailure('withdrawal', $withdrawal, '5pay', $reasonText, $normalized['raw'] ?? null);

            $this->finishCallbackLog($logId, true, 'success', $errorMessage, ['withdrawalId' => $withdrawalId]);
            $this->respondFivePayAck();
        }

        $this->finishCallbackLog($logId, false, 'ignored', 'Unsupported status', ['withdrawalId' => $withdrawalId]);
        $this->respondFivePayAck();
    }

    private function normalizeFivePayDepositPayload($payload) {
        if (!is_array($payload)) {
            $payload = [];
        }

        return [
            'merchantId' => trim((string)($payload['MerchantId'] ?? '')),
            'merchantOrderNo' => trim((string)($payload['MerchantOrderNo'] ?? '')),
            'orderId' => trim((string)($payload['MerchantOrderNo'] ?? '')),
            'orderNo' => trim((string)($payload['OrderNo'] ?? '')),
            'orderAmount' => trim((string)($payload['OrderAmount'] ?? '')),
            'amount' => trim((string)($payload['OrderAmount'] ?? '')),
            'status' => trim((string)($payload['Status'] ?? '')),
            'timeStamp' => trim((string)($payload['TimeStamp'] ?? '')),
            'sign' => trim((string)($payload['Sign'] ?? $payload['sign'] ?? '')),
            'raw' => $payload
        ];
    }

    private function normalizeFivePayWithdrawalPayload($payload) {
        if (!is_array($payload)) {
            $payload = [];
        }

        return [
            'merchantId' => trim((string)($payload['MerchantId'] ?? '')),
            'merchantOrderNo' => trim((string)($payload['MerchantOrderNo'] ?? '')),
            'orderId' => trim((string)($payload['MerchantOrderNo'] ?? '')),
            'withdrawalId' => trim((string)($payload['WithdrawalId'] ?? $payload['Id'] ?? '')),
            'withdrawalAmount' => trim((string)($payload['WithdrawalAmount'] ?? '')),
            'amount' => trim((string)($payload['WithdrawalAmount'] ?? '')),
            'status' => trim((string)($payload['Status'] ?? '')),
            'rejectedReason' => trim((string)($payload['RejectedReason'] ?? '')),
            'timeStamp' => trim((string)($payload['TimeStamp'] ?? '')),
            'sign' => trim((string)($payload['Sign'] ?? $payload['sign'] ?? '')),
            'raw' => $payload
        ];
    }

    private function findFivePayDepositByOrderNo(string $merchantOrderNo, string $orderNo = '') {
        $db = Database::getInstance();

        if ($merchantOrderNo !== '') {
            $record = $db->fetchOne(
                "SELECT * FROM deposits WHERE transactionId = :merchantOrderNo OR REPLACE(transactionId, '-', '') = :merchantOrderNo ORDER BY id DESC LIMIT 1",
                ['merchantOrderNo' => $merchantOrderNo]
            );
            if ($record) {
                return $record;
            }
        }

        if ($orderNo !== '') {
            return $db->fetchOne(
                'SELECT * FROM deposits WHERE gatewayTransactionId = :orderNo ORDER BY id DESC LIMIT 1',
                ['orderNo' => $orderNo]
            );
        }

        return null;
    }

    private function findFivePayWithdrawalByOrderNo(string $merchantOrderNo, string $withdrawalId = '') {
        $db = Database::getInstance();
        $merchantOrderNo = trim($merchantOrderNo);
        $withdrawalId = trim($withdrawalId);

        if ($merchantOrderNo !== '') {
            $withdrawal = $db->fetchOne(
                "SELECT * FROM withdrawals WHERE transactionId = :merchantOrderNo OR REPLACE(transactionId, '-', '') = :merchantOrderNo ORDER BY id DESC LIMIT 1",
                ['merchantOrderNo' => $merchantOrderNo]
            );
            if ($withdrawal) {
                return $withdrawal;
            }
        }

        if ($withdrawalId !== '') {
            return $db->fetchOne(
                'SELECT * FROM withdrawals WHERE gatewayTransactionId = :withdrawalId ORDER BY id DESC LIMIT 1',
                ['withdrawalId' => $withdrawalId]
            );
        }

        return null;
    }

    private function findFivePayGatewayBySettingId(int $gatewaySettingId) {
        if ($gatewaySettingId <= 0) {
            return null;
        }

        $gateway = $this->gatewayModel->findByIdWithSecrets($gatewaySettingId);
        if (!$gateway) {
            return null;
        }

        if (FivePayService::isGatewayKey($gateway['gatewayKey'] ?? '')) {
            return $gateway;
        }

        $configData = json_decode((string)($gateway['configData'] ?? ''), true);
        $providerKey = is_array($configData) ? ($configData['providerKey'] ?? '') : '';
        return FivePayService::isProviderKey($providerKey) ? $gateway : null;
    }

    private function respondFivePayAck() {
        http_response_code(200);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'SUCCESS';
        exit;
    }

    private function respondFivePayFailure() {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'FAIL';
        exit;
    }

    /**
     * 标准化 Vexora callback payload。
     */
    private function normalizeVexoraPayload($payload) {
        if (!is_array($payload)) {
            $payload = [];
        }

        return [
            'tradeNo' => trim((string)($payload['tradeNo'] ?? '')),
            'platFormTradeNo' => trim((string)($payload['platFormTradeNo'] ?? '')),
            'orderId' => trim((string)($payload['tradeNo'] ?? '')),
            'amount' => trim((string)($payload['amount'] ?? '')),
            'status' => trim((string)($payload['status'] ?? '')),
            'message' => trim((string)($payload['message'] ?? '')),
            'successTime' => trim((string)($payload['successTime'] ?? '')),
            'sign' => trim((string)($payload['sign'] ?? '')),
            'raw' => $payload
        ];
    }

    /**
     * Vexora tradeNo = transactionId 去掉 '-' 截断 32 位；
     * 先按原值查，再按 REPLACE(transactionId,'-','') 匹配，最后兜底 gatewayTransactionId。
     */
    private function findVexoraDepositByTradeNo(string $tradeNo, string $platFormTradeNo = '') {
        $db = Database::getInstance();

        $record = $db->fetchOne(
            "SELECT * FROM deposits WHERE transactionId = :tradeNo OR REPLACE(transactionId, '-', '') = :tradeNo ORDER BY id DESC LIMIT 1",
            ['tradeNo' => $tradeNo]
        );
        if ($record) {
            return $record;
        }

        if ($platFormTradeNo !== '') {
            return $db->fetchOne(
                'SELECT * FROM deposits WHERE gatewayTransactionId = :platFormTradeNo ORDER BY id DESC LIMIT 1',
                ['platFormTradeNo' => $platFormTradeNo]
            );
        }

        return null;
    }

    private function findVexoraWithdrawalByTradeNo(string $tradeNo, string $platFormTradeNo = '') {
        $db = Database::getInstance();
        $tradeNo = trim($tradeNo);
        $platFormTradeNo = trim($platFormTradeNo);

        if ($tradeNo !== '') {
            $withdrawal = $db->fetchOne(
                "SELECT * FROM withdrawals WHERE transactionId = :tradeNo OR REPLACE(transactionId, '-', '') = :tradeNo ORDER BY id DESC LIMIT 1",
                ['tradeNo' => $tradeNo]
            );
            if ($withdrawal) {
                return $withdrawal;
            }
        }

        // Fallback: platFormTradeNo persisted on disbursement accept as gatewayTransactionId.
        if ($platFormTradeNo !== '') {
            return $db->fetchOne(
                'SELECT * FROM withdrawals WHERE gatewayTransactionId = :platFormTradeNo ORDER BY id DESC LIMIT 1',
                ['platFormTradeNo' => $platFormTradeNo]
            );
        }

        return null;
    }

    private function findVexoraGatewayBySettingId(int $gatewaySettingId) {
        if ($gatewaySettingId <= 0) {
            return null;
        }

        $gateway = $this->gatewayModel->findByIdWithSecrets($gatewaySettingId);
        if (!$gateway || empty($gateway['isEnabled'])) {
            return null;
        }

        $gatewayKey = strtolower(trim((string)($gateway['gatewayKey'] ?? '')));
        if (strpos($gatewayKey, 'vexora') === 0) {
            return $gateway;
        }

        $configData = json_decode((string)($gateway['configData'] ?? ''), true);
        $providerKey = is_array($configData) ? strtolower(trim((string)($configData['providerKey'] ?? ''))) : '';
        return $providerKey === 'vexora' ? $gateway : null;
    }

    /**
     * Vexora 要求纯文本 "OK"（不能带 JSON 包装）。
     */
    private function respondVexoraAck() {
        http_response_code(200);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'OK';
        exit;
    }

    private function respondVexoraFailure() {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'FAIL';
        exit;
    }

    /**
     * Browser return after Vexora checkout (success/redirect or decline/redirect).
     * 302 to client frontend success/fail hash pages.
     */
    public function vexoraDepositBrowserRedirect(string $outcome) {
        $outcomeKey = $outcome === 'success' ? 'success' : 'fail';
        $templates = $this->appConfig['transaction_callbacks']['deposit'] ?? [];
        $template = trim((string)($templates[$outcomeKey] ?? ''));
        $clientBase = rtrim((string)($this->appConfig['client_frontend_url'] ?? ''), '/');

        $tradeNo = trim((string)($_GET['tradeNo'] ?? $_GET['id'] ?? ''));
        $platFormTradeNo = trim((string)($_GET['platFormTradeNo'] ?? ''));
        $deposit = null;
        if ($tradeNo !== '' || $platFormTradeNo !== '') {
            $deposit = $this->findVexoraDepositByTradeNo($tradeNo, $platFormTradeNo);
            if (!$deposit && strpos($tradeNo, '-') !== false) {
                $deposit = $this->findVexoraDepositByTradeNo(str_replace('-', '', $tradeNo), $platFormTradeNo);
            }
        }

        $path = $template !== ''
            ? strtr($template, [
                '{type}' => rawurlencode('deposit'),
                '{id}' => rawurlencode(trim((string)($deposit['transactionId'] ?? $tradeNo))),
                '{amount}' => rawurlencode($this->formatVexoraRedirectNumber($deposit['amount'] ?? null)),
                '{fee}' => rawurlencode($this->formatVexoraRedirectNumber($deposit['platformFee'] ?? null)),
                '{total}' => rawurlencode($this->formatVexoraRedirectNumber($deposit['quotedAmount'] ?? null)),
                '{currency}' => rawurlencode(strtoupper(trim((string)($deposit['currencyCode'] ?? 'KRW')))),
                '{exchangeRate}' => rawurlencode($this->formatVexoraRedirectNumber($deposit['exchangeRate'] ?? null)),
                '{method}' => rawurlencode(trim((string)($deposit['gatewayName'] ?? $deposit['gatewayKey'] ?? 'Vexora KRW'))),
            ])
            : '/#/client/transactions/' . ($outcomeKey === 'success' ? 'success' : 'fail');

        if (preg_match('/^https?:\/\//i', $path)) {
            $location = $path;
        } elseif ($clientBase !== '') {
            $location = $clientBase . '/' . ltrim($path, '/');
        } else {
            $location = $path;
        }

        header_remove('Content-Type');
        header('Location: ' . $location, true, 302);
        exit;
    }

    private function formatVexoraRedirectNumber($value) {
        if ($value === null || $value === '') {
            return '';
        }
        if (!is_numeric($value)) {
            return trim((string)$value);
        }
        $formatted = number_format((float)$value, 8, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * Coinsbuy deposit callback
     * - 用 data.attributes.tracking_id（= 本地 transactionId）找单
     * - 用 configData.callback_secret 做 HMAC-SHA256 校验
     * - 真实状态以 included 里第一个 type=transfer 的 attributes.status 为准（链上 transfer 状态）：
     *     2 Confirmed → success；-3 Cancelled / -1 Failed → rejected；
     *     -2 Blocked → 人工审核；0 Created / 1 Unconfirmed → 仅记录
     *   data.attributes.status 只代表订单壳子，不再用于业务判断；没 transfer 时整条回调 ignore
     * - 每次回调都会写一条 paymentProcessorCallbackLogs（参照 IBeePay/PaymentAsia 现有模式），方便后台审计/对账
     */
    public function coinsbuyDepositCallback() {
        // IP 白名单校验：与 IBeePay/PaymentAsia 一致，IP 不在 coinsbuy_whitelist_ip_array 时直接 ack 200
        // 防止对方反复重试（白名单为 ['*'] 时放行所有）
        if (!$this->isAllowedCallbackIpByConfigKey('coinsbuy_whitelist_ip_array')) {
            $this->respondCallbackSuccess();
        }

        $payload = $this->readPayload();

        $depositData = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $depositAttrs = is_array($depositData['attributes'] ?? null) ? $depositData['attributes'] : [];
        $trackingId = trim((string)($depositAttrs['tracking_id'] ?? ''));
        // 真实状态以 included 里第一个 type=transfer 的 attributes.status 为准：
        // deposit.attributes.status 只代表订单壳子，链上 cancelled/blocked/failed/confirmed 等真实状态都在 transfer 上；
        // 没 transfer 时本次回调直接忽略
        $transferAttrs = null;
        foreach (($payload['included'] ?? []) as $item) {
            if (is_array($item) && ($item['type'] ?? '') === 'transfer' && is_array($item['attributes'] ?? null)) {
                $transferAttrs = $item['attributes'];
                break;
            }
        }
        $depositStatus = is_array($transferAttrs) && isset($transferAttrs['status'])
            ? (int)$transferAttrs['status']
            : null;
        $targetPaid = isset($depositAttrs['target_paid']) ? (string)$depositAttrs['target_paid'] : '';

        // 在做任何业务判断前先建 callback log（即使最终签名失败/找不到单也要留痕），与 IBeePay 行为一致
        // status 列只接受字符串 → 用 mapCoinsbuyDepositStatusForLog 转成 'paid(3)' 这种可读形式方便后台筛
        $normalizedForLog = [
            'orderId' => $trackingId,
            'status' => $depositStatus !== null ? $this->mapCoinsbuyDepositStatusForLog($depositStatus) : '',
            'amount' => $targetPaid,
            'raw' => $payload,
        ];

        // 结构无效：先记一条 log 再 fail；不能完全无视，否则后台对账会丢
        if (($depositData['type'] ?? '') !== 'deposit') {
            $logId = $this->createCallbackLog('deposit', $normalizedForLog, false, 'coinsbuy');
            $this->finishCallbackLog($logId, false, 'failed', 'Invalid payload structure (not a deposit)', []);
            $this->respondCallbackFailure();
        }

        $deposit = $trackingId !== '' ? $this->findDepositByOrderId($trackingId) : null;
        $gateway = $this->gatewayModel->findByKeyWithSecrets('coinsbuy');
        $signatureValid = $gateway ? $this->verifyCoinsbuyCallbackSignature($payload, $gateway) : false;
        // isValid = 签名通过 + 单 + gateway 都齐 → 才算"我们认可的合法回调"
        $isValid = $signatureValid && $deposit !== null && $depositStatus !== null;

        $logId = $this->createCallbackLog('deposit', $normalizedForLog, $isValid, 'coinsbuy');

        if ($depositStatus === null) {
            // 没有 transfer 子节点：Coinsbuy 只是建单/中间通知，业务上没意义；ack 200 + log 记下来即可
            Logger::info('Coinsbuy deposit callback without transfer in included, ignored', ['payload' => $payload]);
            $this->finishCallbackLog($logId, false, 'ignored', 'No transfer in included', []);
            $this->respondCallbackSuccess();
        }

        if ($trackingId === '') {
            // tracking_id 缺失说明无法定位本地订单：ack 200 防 Coinsbuy 反复重试
            Logger::error('Coinsbuy deposit callback missing tracking_id', ['payload' => $payload]);
            $this->finishCallbackLog($logId, false, 'failed', 'tracking_id missing', []);
            $this->respondCallbackSuccess();
        }

        if (!$deposit) {
            Logger::error('Coinsbuy deposit not found by tracking_id', ['trackingId' => $trackingId]);
            $this->finishCallbackLog($logId, false, 'failed', 'Deposit not found', []);
            $this->respondCallbackFailure();
        }

        $depositId = (int)$deposit['id'];

        if (!$gateway) {
            Logger::error('Coinsbuy gateway settings missing while handling callback');
            $this->finishCallbackLog($logId, false, 'failed', 'Coinsbuy gateway settings missing', ['depositId' => $depositId]);
            $this->respondCallbackFailure();
        }

        if (!$signatureValid) {
            Logger::error('Coinsbuy callback signature verification failed', [
                'depositId' => $depositId,
                'trackingId' => $trackingId,
            ]);
            $this->finishCallbackLog($logId, false, 'failed', 'Signature verification failed', ['depositId' => $depositId]);
            $this->respondCallbackFailure();
        }

        $this->persistCoinsbuyDepositCallback($depositId, $payload, $depositData);

        // 已经处理过同样状态的 callback，直接 ack（Coinsbuy 多次回调正常）
        // transfer.status=2 (Confirmed) 对应已完成入金；-3 (Cancelled) / -1 (Failed) 对应已拒/已撤
        if ($depositStatus === 2 && ($deposit['status'] ?? '') === 'completed') {
            $this->finishCallbackLog($logId, true, 'ignored', 'Duplicate confirmed callback', ['depositId' => $depositId]);
            $this->respondCallbackSuccess();
        }
        if (in_array($depositStatus, [-3, -1], true) && in_array($deposit['status'] ?? '', ['rejected', 'cancelled'], true)) {
            $this->finishCallbackLog($logId, true, 'ignored', 'Duplicate cancelled/failed callback', ['depositId' => $depositId]);
            $this->respondCallbackSuccess();
        }

        if ($depositStatus === 2) {
            // transfer.status=2 (Confirmed)：链上确认达标，资金已到 Coinsbuy 钱包。
            // 客户实际到账多少（transfer.amount_cleared）就按多少入账，与应付额一致则直接入账，
            // 不一致才 reconcile：把 quotedAmount 覆盖成实付值、amount 按 实付/汇率 - fee 反算，让入账/IB 返佣按真实到账值算。
            $settledAmount = is_array($transferAttrs) && isset($transferAttrs['amount_cleared']) ? (string)$transferAttrs['amount_cleared'] : '';

            // 拿不到有效到账金额（reconcile 会因金额 ≤ 0 抛错）→ 不入账，留 pending 等人工核对
            if ($settledAmount === '' || !is_numeric($settledAmount) || bccomp($settledAmount, '0', 2) <= 0) {
                Logger::error('Coinsbuy deposit missing settled amount, kept pending for manual review', [
                    'depositId' => $depositId,
                    'settledAmount' => $settledAmount,
                ]);
                $this->finishCallbackLog($logId, true, 'ignored', 'Missing settled amount', ['depositId' => $depositId]);
                $this->respondCallbackSuccess();
            }

            // 实付（amount_cleared）和下单应付额 quotedAmount 一致就直接入账，省掉反推；
            // 不一致才按实付反推真实 amount/quotedAmount，覆盖原下单值
            $quotedAmount = (string)($deposit['quotedAmount'] ?? '0');
            if (bccomp($settledAmount, $quotedAmount, 2) !== 0) {
                try {
                    $deposit = $this->paymentService->reconcileDepositAmountFromCallback($deposit, (float)$settledAmount);
                    Logger::info('Coinsbuy adjusted deposit amount to actual settled value', [
                        'depositId' => $depositId,
                        'reason' => 'overrode order amount to Coinsbuy actual paid (transfer.amount_cleared)',
                        'settledAmount' => $settledAmount,
                        'quotedAmountBefore' => $quotedAmount,
                        'quotedAmountAfter' => (string)($deposit['quotedAmount'] ?? ''),
                        'amountAfter' => (string)($deposit['amount'] ?? ''),
                    ]);
                } catch (Throwable $e) {
                    Logger::error('Coinsbuy reconcileDepositAmountFromCallback failed: ' . $e->getMessage(), ['depositId' => $depositId]);
                    $this->finishCallbackLog($logId, false, 'failed', 'Reconcile amount failed: ' . $e->getMessage(), ['depositId' => $depositId]);
                    $this->respondCallbackFailure();
                }
            }

            try {
                $this->paymentService->markDepositSuccess(
                    $deposit,
                    0,
                    'Auto-approved by Coinsbuy callback',
                    'callback'
                );
            } catch (Exception $e) {
                Logger::error('Coinsbuy markDepositSuccess failed: ' . $e->getMessage(), ['depositId' => $depositId]);
                $this->finishCallbackLog($logId, false, 'failed', 'markDepositSuccess: ' . $e->getMessage(), ['depositId' => $depositId]);
                $this->respondCallbackFailure();
            }
            $this->finishCallbackLog($logId, true, 'success', null, ['depositId' => $depositId]);
            $this->respondCallbackSuccess();
        }

        if ($depositStatus === -3 || $depositStatus === -1) {
            // -3 Cancelled：Coinsbuy 因安全/金额过小取消；-1 Failed：链上转账失败。两种都按拒单处理
            $reasonText = $depositStatus === -1
                ? 'Deposit failed on blockchain (Coinsbuy callback)'
                : 'Deposit cancelled by Coinsbuy callback';
            try {
                $this->paymentService->markDepositRejected($deposit, 'reject', 0, [
                    'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('deposit'),
                    'rejectionNotes' => $reasonText,
                    'customReason' => $reasonText,
                ]);
            } catch (Exception $e) {
                Logger::error('Coinsbuy markDepositRejected failed: ' . $e->getMessage(), ['depositId' => $depositId]);
            }
            $this->paymentService->logProcessorCallbackFailure('deposit', $deposit, 'coinsbuy', $reasonText, $payload);
            $this->finishCallbackLog($logId, true, 'success', $reasonText, ['depositId' => $depositId]);
            $this->respondCallbackSuccess();
        }

        if ($depositStatus === -2) {
            // Blocked：AML 检查认为可疑，临时阻止；需要人工介入复核
            Logger::error('Coinsbuy deposit BLOCKED by AML, manual review required', [
                'depositId' => $depositId,
                'trackingId' => $trackingId,
                'payload' => $payload,
            ]);
            $this->finishCallbackLog($logId, true, 'ignored', 'Blocked by AML (manual review required)', ['depositId' => $depositId]);
            $this->respondCallbackSuccess();
        }

        if ($depositStatus === 0 || $depositStatus === 1) {
            // 0 Created / 1 Unconfirmed：链上还没确认到位，仅落 callback log，不动单状态
            $reasonText = $depositStatus === 1 ? 'Unconfirmed, awaiting block confirmations' : 'Created, awaiting on-chain transfer';
            $this->finishCallbackLog($logId, true, 'ignored', $reasonText, ['depositId' => $depositId]);
            $this->respondCallbackSuccess();
        }

        // 其它未识别的状态：不动单，ack 失败让 Coinsbuy 知道我们没处理；log 里留 errorMessage 方便事后查 Coinsbuy 是否新增了状态码
        $this->finishCallbackLog($logId, false, 'failed', 'Unhandled Coinsbuy transfer status: ' . $depositStatus, ['depositId' => $depositId]);
        $this->respondCallbackFailure();
    }

    /**
     * 把 Coinsbuy transfer 的数字 status 映射成可读字符串（写到 paymentProcessorCallbackLogs.callbackStatus 用）
     * 与 IBeePay / Payment Asia 现有 map 保持类似风格："状态名(数字)"
     */
    private function mapCoinsbuyDepositStatusForLog(int $status): string {
        $map = [
            -3 => 'cancelled(-3)',
            -2 => 'blocked(-2)',
            -1 => 'failed(-1)',
            0 => 'created(0)',
            1 => 'unconfirmed(1)',
            2 => 'confirmed(2)',
        ];
        return $map[$status] ?? ('unknown(' . $status . ')');
    }

    /**
     * Coinsbuy payout (withdrawal) callback
     * - 用 data.attributes.tracking_id（= 本地 transactionId）找 withdrawal
     * - HMAC message 固定: transfer.status + transfer.amount + payout.tracking_id + meta.time
     * - 两个 status 独立：
     *     payout.status (data.attributes.status)：1 Waiting / 2 Approved / 3 Canceled
     *     transfer.status (included.transfer.attributes.status)：2 = 链上 confirmations 达标
     * - 业务映射：
     *     payout.status=3 → 在 Coinsbuy 后台被取消 → markWithdrawalRejected
     *     transfer.status=2 → 链上确认 → spCompleteWithdrawal 落 txid + 通知
     *     payout.status=1/2（无 transfer 或 transfer 未达标）→ 仅落库不动单
     */
    public function coinsbuyWithdrawalCallback() {
        // IP 白名单校验：与 IBeePay/PaymentAsia 一致，IP 不在 coinsbuy_whitelist_ip_array 时直接 ack 200
        if (!$this->isAllowedCallbackIpByConfigKey('coinsbuy_whitelist_ip_array')) {
            $this->respondCallbackSuccess();
        }

        $payload = $this->readPayload();

        $payoutData = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $payoutAttrs = is_array($payoutData['attributes'] ?? null) ? $payoutData['attributes'] : [];
        $trackingId = trim((string)($payoutAttrs['tracking_id'] ?? ''));
        $payoutStatus = isset($payoutAttrs['status']) ? (int)$payoutAttrs['status'] : null;

        $transferAttrs = $this->extractCoinsbuyTransferAttrs($payload);
        $transferStatus = is_array($transferAttrs) && isset($transferAttrs['status']) ? (int)$transferAttrs['status'] : null;
        $transferAmount = is_array($transferAttrs) ? (string)($transferAttrs['amount'] ?? '') : '';

        // payout / transfer 两个 status 拼成可读字符串供后台筛选；transfer.status=2 是终态优先显示
        $normalizedForLog = [
            'orderId' => $trackingId,
            'status' => $this->mapCoinsbuyWithdrawalStatusForLog($payoutStatus, $transferStatus),
            'amount' => $transferAmount,
            'raw' => $payload,
        ];

        if (($payoutData['type'] ?? '') !== 'payout') {
            $logId = $this->createCallbackLog('withdrawal', $normalizedForLog, false, 'coinsbuy');
            $this->finishCallbackLog($logId, false, 'failed', 'Invalid payload structure (not a payout)', []);
            $this->respondCallbackFailure();
        }

        $withdrawal = $trackingId !== '' ? $this->findWithdrawalByOrderId($trackingId) : null;
        $gateway = $this->gatewayModel->findByKeyWithSecrets('coinsbuy');
        $signatureValid = $gateway
            ? $this->verifyCoinsbuyPayoutCallbackSignature($payload, $transferAttrs, $trackingId, $gateway)
            : false;
        $isValid = $signatureValid && $withdrawal !== null;

        $logId = $this->createCallbackLog('withdrawal', $normalizedForLog, $isValid, 'coinsbuy');

        if ($trackingId === '') {
            Logger::error('Coinsbuy payout callback missing tracking_id', ['payload' => $payload]);
            $this->finishCallbackLog($logId, false, 'failed', 'tracking_id missing', []);
            $this->respondCallbackSuccess();
        }

        if (!$withdrawal) {
            Logger::error('Coinsbuy withdrawal not found by tracking_id', ['trackingId' => $trackingId]);
            $this->finishCallbackLog($logId, false, 'failed', 'Withdrawal not found', []);
            $this->respondCallbackFailure();
        }

        $withdrawalId = (int)$withdrawal['id'];

        if (!$gateway) {
            Logger::error('Coinsbuy gateway settings missing while handling payout callback');
            $this->finishCallbackLog($logId, false, 'failed', 'Coinsbuy gateway settings missing', ['withdrawalId' => $withdrawalId]);
            $this->respondCallbackFailure();
        }

        if (!$signatureValid) {
            Logger::error('Coinsbuy payout callback signature verification failed', [
                'withdrawalId' => $withdrawalId,
                'trackingId' => $trackingId,
            ]);
            $this->finishCallbackLog($logId, false, 'failed', 'Signature verification failed', ['withdrawalId' => $withdrawalId]);
            $this->respondCallbackFailure();
        }

        $txid = is_array($transferAttrs) ? trim((string)($transferAttrs['txid'] ?? '')) : '';
        $this->persistCoinsbuyWithdrawalCallback($withdrawalId, $payload, $payoutData, $txid);

        $currentStatus = (string)($withdrawal['status'] ?? '');

        // 1. payout 在 Coinsbuy 那边被 cancel
        if ($payoutStatus === 3) {
            if (in_array($currentStatus, ['rejected', 'cancelled'], true)) {
                $this->finishCallbackLog($logId, true, 'ignored', 'Duplicate canceled callback (already rejected)', ['withdrawalId' => $withdrawalId]);
                $this->respondCallbackSuccess();
            }
            $reasonText = 'Withdrawal canceled by Coinsbuy callback (payout.status=3)';
            try {
                $this->paymentService->markWithdrawalRejected($withdrawal, 'reject', 0, [
                    'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('withdrawal'),
                    'rejectionNotes' => $reasonText,
                    'customReason' => $reasonText,
                ]);
            } catch (Throwable $e) {
                Logger::error('Coinsbuy markWithdrawalRejected failed: ' . $e->getMessage(), ['withdrawalId' => $withdrawalId]);
                $this->finishCallbackLog($logId, false, 'failed', 'markWithdrawalRejected: ' . $e->getMessage(), ['withdrawalId' => $withdrawalId]);
                $this->respondCallbackFailure();
            }
            $this->paymentService->logProcessorCallbackFailure('withdrawal', $withdrawal, 'coinsbuy', $reasonText, $payload);
            $this->finishCallbackLog($logId, true, 'success', $reasonText, ['withdrawalId' => $withdrawalId]);
            $this->respondCallbackSuccess();
        }

        // 2. 链上确认（transfer.status=2）→ 完成
        if ($transferStatus === 2) {
            if ($currentStatus === 'completed') {
                $this->finishCallbackLog($logId, true, 'ignored', 'Duplicate completed callback (already completed)', ['withdrawalId' => $withdrawalId]);
                $this->respondCallbackSuccess();
            }

            try {
                $db = Database::getInstance();
                $db->query(
                    'CALL spCompleteWithdrawal(:withdrawalId, :completedBy, :transactionHash)',
                    [
                        'withdrawalId' => $withdrawalId,
                        'completedBy' => 0,
                        'transactionHash' => $txid !== '' ? $txid : null,
                    ]
                );
            } catch (Throwable $e) {
                Logger::error('Coinsbuy spCompleteWithdrawal failed: ' . $e->getMessage(), ['withdrawalId' => $withdrawalId]);
                $this->finishCallbackLog($logId, false, 'failed', 'spCompleteWithdrawal: ' . $e->getMessage(), ['withdrawalId' => $withdrawalId]);
                $this->respondCallbackFailure();
            }

            $updatedWithdrawal = $this->withdrawalModel->getWithdrawalDetails($withdrawalId) ?: $withdrawal;
            $this->sendWithdrawalStatusNotice($updatedWithdrawal, 'completed', ['operatorId' => 0]);
            $this->finishCallbackLog($logId, true, 'success', null, ['withdrawalId' => $withdrawalId]);
            $this->respondCallbackSuccess();
        }

        // 3. payout.status=1 (Waiting) / 2 (Approved) 但 transfer 还没达标 / 没有 transfer → 仅落库不动单
        $this->finishCallbackLog(
            $logId,
            true,
            'ignored',
            sprintf('Intermediate state: payout.status=%s, transfer.status=%s',
                $payoutStatus !== null ? $payoutStatus : 'null',
                $transferStatus !== null ? $transferStatus : 'null'
            ),
            ['withdrawalId' => $withdrawalId]
        );
        $this->respondCallbackSuccess();
    }

    /**
     * 把 Coinsbuy payout/transfer 两个 status 拼成可读字符串（写到 paymentProcessorCallbackLogs.callbackStatus）
     * - transfer.status=2  → 链上确认达标，终态
     * - payout.status=3    → 在 Coinsbuy 后台被取消
     * - payout.status=1/2  → Waiting / Approved 中间态
     */
    private function mapCoinsbuyWithdrawalStatusForLog(?int $payoutStatus, ?int $transferStatus): string {
        if ($transferStatus === 2) {
            return 'completed(transfer.2)';
        }
        if ($payoutStatus === 3) {
            return 'canceled(payout.3)';
        }
        $payoutMap = [
            1 => 'waiting(payout.1)',
            2 => 'approved(payout.2)',
        ];
        if ($payoutStatus !== null) {
            return $payoutMap[$payoutStatus] ?? ('unknown(payout.' . $payoutStatus . ')');
        }
        return '';
    }

    /**
     * 把 Coinsbuy payout 回调的 raw payload + Coinsbuy payout id + 链上 txid 落到 withdrawals 表
     */
    private function persistCoinsbuyWithdrawalCallback(int $withdrawalId, array $payload, array $payoutData, string $txid): void {
        $update = [
            'gatewayResponse' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        $coinsbuyPayoutId = trim((string)($payoutData['id'] ?? ''));
        if ($coinsbuyPayoutId !== '') {
            $update['gatewayTransactionId'] = $coinsbuyPayoutId;
        }
        if ($txid !== '') {
            $update['transactionHash'] = $txid;
        }
        try {
            $this->withdrawalModel->update($withdrawalId, $update);
        } catch (Throwable $e) {
            Logger::error('Failed to persist Coinsbuy payout callback: ' . $e->getMessage(), [
                'withdrawalId' => $withdrawalId,
            ]);
        }
    }

    private function extractCoinsbuyTransferAttrs(array $payload) {
        return $this->pickCoinsbuyTransferByOpType($payload, 2);
    }

    /**
     * 从 Coinsbuy callback 的 included[] 里按 op_type 选 transfer
     * - deposit callback：op_type=1（链上入金）
     * - payout callback：op_type=2（payout 主体；同时可能存在 op_type=9 transportation，顺序不固定）
     * 找不到指定 op_type 时退回到 included 里的任意一条 transfer（兼容 Coinsbuy 文档示例的简单情况）
     * 这一条是 HMAC 算的依据：错选会直接 signature verification failed
     */
    private function pickCoinsbuyTransferByOpType(array $payload, int $expectedOpType) {
        $matched = null;
        $fallback = null;
        foreach (($payload['included'] ?? []) as $item) {
            if (!is_array($item) || ($item['type'] ?? '') !== 'transfer') {
                continue;
            }
            $attrs = $item['attributes'] ?? null;
            if (!is_array($attrs)) {
                continue;
            }
            $fallback = $attrs;
            if ((int)($attrs['op_type'] ?? 0) === $expectedOpType) {
                $matched = $attrs;
                break;
            }
        }
        return $matched !== null ? $matched : $fallback;
    }

    /**
     * Coinsbuy payout 回调签名校验
     * 文档：https://docs.coinsbuy.com/api-guide/payout-methods#callback-verification
     *   message = transfer.status + transfer.amount + payout.tracking_id + meta.time
     * 没 transfer（payout 早期 cancel 可能出现）时，前两段当空字符串拼，与文档"concatenation"一致
     */
    private function verifyCoinsbuyPayoutCallbackSignature(array $payload, $transferAttrs, string $trackingId, array $gateway): bool {
        $config = $this->getCoinsbuyGatewayConfig($gateway);
        $callbackSecret = (string)($config['callback_secret'] ?? '');
        if ($callbackSecret === '') {
            Logger::error('Coinsbuy callback_secret is not configured in configData');
            return false;
        }

        $sign = (string)($payload['meta']['sign'] ?? '');
        $time = (string)($payload['meta']['time'] ?? '');
        if ($sign === '' || $time === '') {
            return false;
        }

        $transferStatus = is_array($transferAttrs) ? (string)($transferAttrs['status'] ?? '') : '';
        $transferAmount = is_array($transferAttrs) ? (string)($transferAttrs['amount'] ?? '') : '';
        $message = $transferStatus . $transferAmount . $trackingId . $time;

        $expected = hash_hmac('sha256', $message, $callbackSecret);
        return hash_equals($expected, $sign);
    }

    /**
     * 把 Coinsbuy 回调的 raw payload + Coinsbuy 那边的 deposit id 落到 deposits 表
     */
    private function persistCoinsbuyDepositCallback(int $depositId, array $payload, array $depositData): void {
        $update = [
            'gatewayResponse' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        $coinsbuyDepositId = trim((string)($depositData['id'] ?? ''));
        if ($coinsbuyDepositId !== '') {
            $update['gatewayTransactionId'] = $coinsbuyDepositId;
        }
        try {
            $this->depositModel->update($depositId, $update);
        } catch (Throwable $e) {
            Logger::error('Failed to persist Coinsbuy deposit callback: ' . $e->getMessage(), [
                'depositId' => $depositId,
            ]);
        }
    }

    /**
     * Coinsbuy 回调签名校验
     * 文档：https://docs.coinsbuy.com/api-guide/deposit-methods#callback-verification
     *   - 含 transfer：message = transfer.status + transfer.amount + deposit.tracking_id + meta.time
     *   - 不含 transfer：message = deposit.status + deposit.tracking_id (非空) + meta.time
     *   - HMAC-SHA256(message, callback_secret) hex 与 meta.sign 对比
     */
    private function verifyCoinsbuyCallbackSignature(array $payload, array $gateway): bool {
        $config = $this->getCoinsbuyGatewayConfig($gateway);
        $callbackSecret = (string)($config['callback_secret'] ?? '');
        if ($callbackSecret === '') {
            // 安全默认：未配置 callback_secret 直接拒，避免裸跑
            Logger::error('Coinsbuy callback_secret is not configured in configData');
            return false;
        }

        $sign = (string)($payload['meta']['sign'] ?? '');
        $time = (string)($payload['meta']['time'] ?? '');
        if ($sign === '' || $time === '') {
            return false;
        }

        $depositAttrs = $payload['data']['attributes'] ?? [];
        $trackingId = (string)($depositAttrs['tracking_id'] ?? '');

        // 统一走 pickCoinsbuyTransferByOpType：deposit 主体用 op_type=1，避免在 Coinsbuy 顺序不稳定时取错 transfer
        $transferAttrs = $this->pickCoinsbuyTransferByOpType($payload, 1);

        if (is_array($transferAttrs)) {
            $message = (string)($transferAttrs['status'] ?? '')
                . (string)($transferAttrs['amount'] ?? '')
                . $trackingId
                . $time;
        } else {
            // 不含 transfer：tracking_id 仅在非空时拼进去
            $message = (string)($depositAttrs['status'] ?? '')
                . ($trackingId !== '' ? $trackingId : '')
                . $time;
        }

        $expected = hash_hmac('sha256', $message, $callbackSecret);
        return hash_equals($expected, $sign);
    }

    private function getCoinsbuyGatewayConfig(array $gateway): array {
        $configData = $gateway['configData'] ?? null;
        if (is_string($configData) && trim($configData) !== '') {
            $decoded = json_decode($configData, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    private function findXLinkTransaction(string $operationType, string $operationNumber, string $operationId) {
        if ($operationType === 'PAYIN') {
            return $this->findXLinkDeposit($operationNumber, $operationId);
        }
        if ($operationType === 'PAYOUT') {
            return $this->findXLinkWithdrawal($operationNumber, $operationId);
        }

        return $this->findXLinkDeposit($operationNumber, $operationId)
            ?: $this->findXLinkWithdrawal($operationNumber, $operationId);
    }

    private function findXLinkDeposit(string $operationNumber, string $operationId) {
        if ($operationNumber !== '') {
            $deposit = $this->findDepositByOrderId($operationNumber);
            if ($deposit) {
                return $deposit;
            }
        }
        if ($operationId === '') {
            return null;
        }

        return Database::getInstance()->fetchOne(
            'SELECT * FROM deposits WHERE gatewayTransactionId = :operationId ORDER BY id DESC LIMIT 1',
            ['operationId' => $operationId]
        );
    }

    private function findXLinkWithdrawal(string $operationNumber, string $operationId) {
        if ($operationNumber !== '') {
            $withdrawal = $this->findWithdrawalByOrderId($operationNumber);
            if ($withdrawal) {
                return $withdrawal;
            }
        }
        if ($operationId === '') {
            return null;
        }

        return Database::getInstance()->fetchOne(
            'SELECT * FROM withdrawals WHERE gatewayTransactionId = :operationId ORDER BY id DESC LIMIT 1',
            ['operationId' => $operationId]
        );
    }

    private function findXLinkGatewayForTransaction(array $record) {
        $gatewaySettingId = (int)($record['gatewaySettingId'] ?? 0);
        if ($gatewaySettingId <= 0) {
            return null;
        }

        $gateway = $this->gatewayModel->findByIdWithSecrets($gatewaySettingId);
        if (!$gateway) {
            return null;
        }
        if (strtolower(trim((string)($gateway['gatewayKey'] ?? ''))) === XLinkService::GATEWAY_KEY) {
            return $gateway;
        }

        $config = $gateway['configData'] ?? null;
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        return is_array($config)
            && strtolower(trim((string)($config['providerKey'] ?? ''))) === XLinkService::PROVIDER_KEY
                ? $gateway
                : null;
    }

    private function createXLinkCallbackLog(string $transactionType, array $data, $decoded) {
        $amount = trim((string)($data['amount'] ?? $data['initiated_amount'] ?? ''));
        $safePayload = is_array($decoded) ? XLinkService::sanitizeForLog($decoded) : ['invalid_json' => true];

        return $this->callbackLogModel->create([
            'provider' => XLinkService::PROVIDER_KEY,
            'transactionType' => $transactionType,
            'orderId' => trim((string)($data['operation_number'] ?? '')) ?: null,
            'callbackStatus' => trim((string)($data['status'] ?? '')) ?: null,
            'amount' => $amount !== '' ? $amount : null,
            'ip' => ClientIp::getClientIp(),
            'rawPayload' => json_encode($safePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'isValid' => 0,
            'isProcessed' => 0,
            'processResult' => 'pending',
        ]);
    }

    private function markXLinkCallbackLogValid($logId, string $operationNumber): void {
        if (!$logId) {
            throw new RuntimeException('X-Link callback audit log was not created');
        }

        $updated = $this->callbackLogModel->update((int)$logId, [
            'isValid' => 1,
            'orderId' => $operationNumber !== '' ? $operationNumber : null,
        ]);
        if (!$updated) {
            throw new RuntimeException('Failed to persist X-Link callback audit validity');
        }
    }

    private function xlinkCallbackIds(string $transactionType, $record, string $operationId): array {
        $ids = [];
        if (is_array($record) && !empty($record['id'])) {
            $ids[$transactionType === 'withdrawal' ? 'withdrawalId' : 'depositId'] = (int)$record['id'];
        }
        if ($operationId !== '') {
            $ids['providerOrderId'] = $operationId;
        }
        return $ids;
    }

    private function persistXLinkCallbackReference(string $transactionType, array $record, string $operationId): void {
        $recordId = (int)($record['id'] ?? 0);
        if ($recordId <= 0) {
            return;
        }

        $update = [];
        if ($operationId !== '') {
            $update['gatewayTransactionId'] = $operationId;
        }
        if ($transactionType === 'deposit') {
            $update['webhookReceived'] = 1;
        }
        if (empty($update)) {
            return;
        }

        if ($transactionType === 'withdrawal') {
            $updated = $this->withdrawalModel->update($recordId, $update);
            if (!$updated && $operationId !== '') {
                $fresh = $this->withdrawalModel->findById($recordId);
                if (!$fresh || trim((string)($fresh['gatewayTransactionId'] ?? '')) !== $operationId) {
                    throw new RuntimeException('Failed to persist X-Link withdrawal callback reference');
                }
            }
        } else {
            $updated = $this->depositModel->update($recordId, $update);
            if (!$updated) {
                $fresh = $this->depositModel->findById($recordId);
                $referenceMatches = $operationId === ''
                    || trim((string)($fresh['gatewayTransactionId'] ?? '')) === $operationId;
                if (!$fresh || !$referenceMatches || empty($fresh['webhookReceived'])) {
                    throw new RuntimeException('Failed to persist X-Link deposit callback reference');
                }
            }
        }
    }

    private function isXLinkDuplicateTerminalCallback(string $currentStatus, string $mappedStatus): bool {
        if (in_array($currentStatus, ['completed', 'rejected', 'cancelled', 'failed', 'expired'], true)) {
            return true;
        }

        return ($mappedStatus === 'success' && $currentStatus === 'completed')
            || ($mappedStatus === 'cancelled' && $currentStatus === 'cancelled')
            || ($mappedStatus === 'expired' && $currentStatus === 'expired')
            || (in_array($mappedStatus, ['failed', 'refunded'], true)
                && in_array($currentStatus, ['rejected', 'failed'], true));
    }

    private function xlinkCallbackLockName(string $transactionType, int $recordId): string {
        return 'xlink_callback_' . ($transactionType === 'withdrawal' ? 'w_' : 'd_') . $recordId;
    }

    private function acquireXLinkCallbackLock(string $lockName): bool {
        try {
            $result = Database::getInstance()->fetchOne(
                'SELECT GET_LOCK(:lockName, 15) AS acquired',
                ['lockName' => $lockName]
            );
            return (int)($result['acquired'] ?? 0) === 1;
        } catch (Throwable $e) {
            Logger::error('Failed to acquire X-Link callback lock: ' . $e->getMessage());
            return false;
        }
    }

    private function releaseXLinkCallbackLock(string $lockName): void {
        try {
            Database::getInstance()->fetchOne(
                'SELECT RELEASE_LOCK(:lockName) AS released',
                ['lockName' => $lockName]
            );
        } catch (Throwable $e) {
            Logger::warning('Failed to release X-Link callback lock: ' . $e->getMessage());
        }
    }

    private function settleXLinkSuccess(string $transactionType, array $record, string $operationId): void {
        if ($transactionType === 'deposit') {
            $this->paymentService->markDepositSuccess(
                $record,
                0,
                'Auto-approved by X-Link callback',
                'callback'
            );
            return;
        }

        $withdrawalId = (int)($record['id'] ?? 0);
        $stmt = Database::getInstance()->query(
            'CALL spCompleteWithdrawal(:withdrawalId, :completedBy, :transactionHash)',
            [
                'withdrawalId' => $withdrawalId,
                'completedBy' => 0,
                'transactionHash' => $operationId !== '' ? $operationId : null,
            ]
        );
        if ($stmt) {
            $stmt->closeCursor();
        }
        $updatedWithdrawal = $this->withdrawalModel->getWithdrawalDetails($withdrawalId) ?: $record;
        $this->sendWithdrawalStatusNotice($updatedWithdrawal, 'completed', ['operatorId' => 0]);
    }

    private function rejectXLinkTransaction(string $transactionType, array $record, string $reason): void {
        $payload = [
            'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId($transactionType),
            'rejectionNotes' => $reason,
            'customReason' => $reason,
        ];

        if ($transactionType === 'withdrawal') {
            $this->paymentService->markWithdrawalRejected($record, 'reject', 0, $payload);
            return;
        }

        $this->paymentService->markDepositRejected($record, 'reject', 0, $payload);
    }

    private function respondXLinkCallback(int $statusCode, string $message): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $statusCode >= 200 && $statusCode < 300,
            'message' => $message,
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function readPayload() {
        $payload = $_POST ?? [];
        if (!is_array($payload)) {
            $payload = [];
        }

        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $payload = array_merge($payload, $json);
            } else {
                parse_str($raw, $formPayload);
                if (is_array($formPayload) && !empty($formPayload)) {
                    $payload = array_merge($payload, $formPayload);
                }
            }
        }

        return $payload;
    }

    private function respondCallbackSuccess() {
        Response::success(null, 'success');
    }

    private function respondCallbackFailure() {
        Response::error('failure', 400);
    }

    /**
     * 标准化 IBeePay callback payload。
     */
    private function normalizeIbeepayPayload($payload) {
        $status = strtolower(trim((string)($payload['status'] ?? '')));
        $orderId = trim((string)($payload['orderId'] ?? ($payload['order_id'] ?? '')));
        $amount = trim((string)($payload['amount'] ?? ''));
        $key = trim((string)($payload['key'] ?? ''));
        $hash = trim((string)($payload['hash'] ?? ''));

        return [
            'status' => $status,
            'orderId' => $orderId,
            'amount' => $amount,
            'key' => $key,
            'hash' => $hash,
            'raw' => $payload
        ];
    }

    /**
     * 标准化 Payment Asia callback payload。
     */
    private function normalizePaymentAsiaPayload($payload) {
        $payloadData = [];
        if (is_array($payload['payload'] ?? null)) {
            $payloadData = $payload['payload'];
        }

        return [
            'merchantReference' => trim((string)($payload['merchant_reference'] ?? $payloadData['merchant_reference'] ?? '')),
            'requestReference' => trim((string)($payload['request_reference'] ?? $payloadData['request_reference'] ?? '')),
            'batchReference' => trim((string)($payload['batch_reference'] ?? $payloadData['batch_reference'] ?? '')),
            'currency' => strtoupper(trim((string)($payload['currency'] ?? $payload['order_currency'] ?? $payloadData['currency'] ?? $payloadData['order_currency'] ?? ''))),
            'amount' => trim((string)($payload['amount'] ?? $payload['order_amount'] ?? $payloadData['amount'] ?? $payloadData['order_amount'] ?? '')),
            'status' => trim((string)($payload['status'] ?? $payloadData['status'] ?? '')),
            'sign' => trim((string)($payload['sign'] ?? $payloadData['sign'] ?? '')),
            'failReason' => trim((string)($payload['fail_reason'] ?? $payloadData['fail_reason'] ?? '')),
            'raw' => is_array($payload) ? $payload : []
        ];
    }

    /**
     * 校验 IBeePay callback hash。
     */
    private function validateIbeepayCallbackAuth($normalized) {
        $gateway = $this->gatewayModel->findByKeyWithSecrets('ibeepay');
        $authKey = ($gateway && !empty($gateway['isEnabled']))
            ? trim((string)($gateway['secretKey'] ?? ''))
            : '';
        if ($authKey === '') {
            return ['valid' => false, 'error' => 'Missing gateway secret'];
        }

        if ($normalized['hash'] === '') {
            return ['valid' => false, 'error' => 'Missing IBeePay callback hash'];
        }

        $expectedHash = hash('sha256', $normalized['orderId'] . $normalized['amount'] . $authKey);
        if (!hash_equals($expectedHash, $normalized['hash'])) {
            return ['valid' => false, 'error' => 'Invalid IBeePay callback hash'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * 校验 Payment Asia deposit callback sign。
     */
    private function validatePaymentAsiaCallbackAuth(array $normalized, array $gateway) {
        $secret = trim((string)($gateway['secretKey'] ?? ''));
        if ($secret === '') {
            return ['valid' => false, 'error' => 'Missing gateway secret'];
        }

        $rawPayload = is_array($normalized['raw'] ?? null) ? $normalized['raw'] : [];
        $fields = [
            'amount' => (string)($rawPayload['amount'] ?? $normalized['amount']),
            'currency' => (string)($rawPayload['currency'] ?? $normalized['currency']),
            'request_reference' => (string)($rawPayload['request_reference'] ?? $normalized['requestReference']),
            'merchant_reference' => (string)($rawPayload['merchant_reference'] ?? $normalized['merchantReference']),
            'status' => (string)($rawPayload['status'] ?? $normalized['status'])
        ];
        ksort($fields, SORT_STRING);
        $expectedSign = hash('sha512', http_build_query($fields, '', '&', PHP_QUERY_RFC1738) . $secret);

        if (!hash_equals($expectedSign, $normalized['sign'])) {
            return ['valid' => false, 'error' => 'Invalid callback sign'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * 校验 Payment Asia withdrawal callback sign。
     */
    private function validatePaymentAsiaWithdrawalCallbackAuth(array $normalized, array $gateway) {
        $secret = trim((string)($gateway['secretKey'] ?? ''));
        if ($secret === '') {
            return ['valid' => false, 'error' => 'Missing gateway secret'];
        }

        $rawPayload = is_array($normalized['raw'] ?? null) ? $normalized['raw'] : [];
        $fields = $this->extractPaymentAsiaSignedFields($rawPayload);
        if (empty($fields)) {
            return ['valid' => false, 'error' => 'Missing callback fields'];
        }

        ksort($fields, SORT_STRING);
        $expectedSign = hash('sha512', http_build_query($fields, '', '&', PHP_QUERY_RFC1738) . $secret);

        if (!hash_equals($expectedSign, $normalized['sign'])) {
            return ['valid' => false, 'error' => 'Invalid callback sign'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * 提取 Payment Asia withdrawal callback 的签名字段。
     */
    private function extractPaymentAsiaSignedFields(array $rawPayload): array {
        $source = $rawPayload;
        $payloadData = is_array($rawPayload['payload'] ?? null) ? $rawPayload['payload'] : [];

        if (
            !empty($payloadData)
            && !isset($rawPayload['request_reference'])
            && !isset($rawPayload['merchant_reference'])
            && !isset($rawPayload['status'])
            && !isset($rawPayload['amount'])
            && !isset($rawPayload['order_amount'])
        ) {
            $source = $payloadData;
        }

        unset($source['sign']);

        foreach ($source as $key => $value) {
            if (is_array($value) || is_object($value)) {
                unset($source[$key]);
                continue;
            }

            $source[$key] = (string)$value;
        }

        return $source;
    }

    private function findPaymentAsiaGatewayForDeposit(array $deposit) {
        $gatewaySettingId = (int)($deposit['gatewaySettingId'] ?? 0);
        if ($gatewaySettingId <= 0) {
            return null;
        }

        $gateway = $this->gatewayModel->findByIdWithSecrets($gatewaySettingId);
        if (!$gateway || empty($gateway['isEnabled'])) {
            return null;
        }

        $gatewayKey = strtolower(trim((string)($gateway['gatewayKey'] ?? '')));
        return strpos($gatewayKey, 'pa-') === 0 ? $gateway : null;
    }

    private function findPaymentAsiaGatewayForWithdrawal(array $withdrawal) {
        $gatewaySettingId = (int)($withdrawal['gatewaySettingId'] ?? 0);
        if ($gatewaySettingId <= 0) {
            return null;
        }

        $gateway = $this->gatewayModel->findByIdWithSecrets($gatewaySettingId);
        if (!$gateway || empty($gateway['isEnabled'])) {
            return null;
        }

        $gatewayKey = strtolower(trim((string)($gateway['gatewayKey'] ?? '')));
        return strpos($gatewayKey, 'pa-') === 0 ? $gateway : null;
    }

    private function isAllowedCallbackIpByConfigKey(string $configKey) {
        $whitelist = $this->appConfig['external_api'][$configKey] ?? [];

        if ($whitelist === '*' || $whitelist === ['*']) {
            return true;
        }

        if (!is_array($whitelist)) {
            $whitelist = [$whitelist];
        }

        $normalizedWhitelist = [];
        foreach ($whitelist as $allowedIp) {
            $allowedIp = trim((string)$allowedIp);
            if ($allowedIp === '') {
                continue;
            }
            if ($allowedIp === '*') {
                return true;
            }
            $normalizedWhitelist[] = $allowedIp;
        }

        if (empty($normalizedWhitelist)) {
            return true;
        }

        $clientIp = ClientIp::getClientIp();
        return in_array($clientIp, $normalizedWhitelist, true);
    }

    private function createCallbackLog($transactionType, $normalized, $isValid, $provider = 'ibeepay') {
        $amount = trim((string)($normalized['amount'] ?? ''));

        return $this->callbackLogModel->create([
            'provider' => $provider,
            'transactionType' => $transactionType,
            'orderId' => ($normalized['orderId'] ?? $normalized['merchantReference'] ?? $normalized['requestReference'] ?? '') !== ''
                ? ($normalized['orderId'] ?? $normalized['merchantReference'] ?? $normalized['requestReference'])
                : null,
            'callbackStatus' => $normalized['status'] !== ''
                ? (
                    $provider === 'payment_asia'
                        ? $this->mapPaymentAsiaCallbackStatusForLog((string)$normalized['status'])
                        : (string)$normalized['status']
                )
                : null,
            'amount' => $amount !== '' ? $amount : null,
            'ip' => ClientIp::getClientIp(),
            'rawPayload' => json_encode($normalized['raw'], JSON_UNESCAPED_UNICODE),
            'isValid' => $isValid ? 1 : 0,
            'isProcessed' => 0,
            'processResult' => 'pending'
        ]);
    }

    private function markCallbackLogValid($logId) {
        if (!$logId) {
            return;
        }

        try {
            $this->callbackLogModel->update((int)$logId, ['isValid' => 1]);
        } catch (Throwable $e) {
            Logger::warning('Failed to mark payment callback log as valid: ' . $e->getMessage());
        }
    }

    /**
     * 将 Payment Asia 原始状态码映射为更易读的日志状态。
     */
    private function mapPaymentAsiaCallbackStatusForLog(string $status): string {
        $value = trim($status);
        if ($value === '') {
            return '';
        }

        $map = [
            '0' => 'pending(0)',
            '1' => 'success(1)',
            '2' => 'fail(2)',
            '3' => 'authorized(3)',
            '4' => 'processing(4)',
            '8' => 'cancelled(8)',
        ];

        return $map[$value] ?? $value;
    }

    /**
     * 判断 IBeePay callback 是否包含有效结构。
     */
    private function hasMeaningfulIbeepayCallbackStructure($normalized) {
        return $normalized['orderId'] !== ''
            || $normalized['status'] !== ''
            || $normalized['amount'] !== ''
            || !empty($normalized['raw']);
    }

    /**
     * 判断 Payment Asia callback 是否包含有效结构。
     */
    private function hasMeaningfulPaymentAsiaCallbackStructure($normalized) {
        return $normalized['merchantReference'] !== ''
            || $normalized['requestReference'] !== ''
            || $normalized['status'] !== ''
            || $normalized['amount'] !== ''
            || !empty($normalized['raw']);
    }

    /**
     * 判断 IBeePay deposit callback 是否已处理。
     */
    private function isIbeepayDepositCallbackAlreadyApplied($deposit, $status) {
        $currentStatus = (string)($deposit['status'] ?? '');

        if ($status === 'approved' && $currentStatus === 'completed') {
            return true;
        }

        if ($status === 'rejected' && $currentStatus === 'failed') {
            return true;
        }

        if (($status === 'canceled' || $status === 'cancelled') && $currentStatus === 'cancelled') {
            return true;
        }

        return false;
    }

    /**
     * 判断 Payment Asia deposit callback 是否已处理。
     */
    private function isPaymentAsiaDepositCallbackAlreadyApplied(array $deposit, string $status): bool {
        $currentStatus = (string)($deposit['status'] ?? '');

        if ($status === '1' && $currentStatus === 'completed') {
            return true;
        }

        if ($status === '2' && $currentStatus === 'failed') {
            return true;
        }

        if ($status === '0' && $currentStatus === 'pending') {
            return true;
        }

        return false;
    }

    /**
     * 判断 Payment Asia withdrawal callback 是否已记录。
     */
    private function isPaymentAsiaWithdrawalCallbackAlreadyRecorded(array $withdrawal, string $status): bool {
        $currentStatus = (string)($withdrawal['status'] ?? '');

        if ($status === '1' && $currentStatus === 'completed') {
            return true;
        }

        if (in_array($status, ['2', '8'], true) && $currentStatus === 'rejected') {
            return true;
        }

        if (in_array($status, ['0', '3', '4'], true) && in_array($currentStatus, ['pending', 'processing'], true)) {
            return true;
        }

        return false;
    }

    /**
     * 判断 IBeePay withdrawal callback 是否已记录。
     */
    private function isIbeepayWithdrawalCallbackAlreadyRecorded($withdrawal, $status) {
        if (!$withdrawal) {
            return false;
        }

        $currentStatus = (string)($withdrawal['status'] ?? '');
        if ($status === 'approved' && $currentStatus === 'completed') {
            return true;
        }

        return $status !== '' && $currentStatus === $status;
    }

    private function finishCallbackLog($logId, $processed, $result, $errorMessage, $ids) {
        $update = [
            'isProcessed' => $processed ? 1 : 0,
            'processResult' => $result,
            'errorMessage' => $errorMessage,
            'processedAt' => date('Y-m-d H:i:s')
        ];
        if (!empty($ids['depositId'])) {
            $update['depositId'] = (int)$ids['depositId'];
        }
        if (!empty($ids['withdrawalId'])) {
            $update['withdrawalId'] = (int)$ids['withdrawalId'];
        }

        $correlation = $this->resolveCallbackRequestCorrelation((int) $logId, is_array($ids) ? $ids : []);
        if (!empty($correlation['requestLogId'])) {
            $update['requestLogId'] = (int) $correlation['requestLogId'];
        }
        if (!empty($correlation['correlationMethod'])) {
            $update['correlationMethod'] = (string) $correlation['correlationMethod'];
        }

        $this->callbackLogModel->update((int)$logId, $update);
    }

    /**
     * Soft-link callback row to outbound request attempt. Fail-open.
     *
     * @param array<string,mixed> $ids
     * @return array{requestLogId:?int,correlationMethod:string}
     */
    private function resolveCallbackRequestCorrelation($logId, array $ids) {
        $fallback = [
            'requestLogId' => null,
            'correlationMethod' => 'unmatched',
        ];

        try {
            if (!empty($ids['requestLogId'])) {
                return [
                    'requestLogId' => (int) $ids['requestLogId'],
                    'correlationMethod' => !empty($ids['correlationMethod'])
                        ? (string) $ids['correlationMethod']
                        : 'local_order_id',
                ];
            }

            $existing = $this->callbackLogModel->findById($logId);
            if (!$existing) {
                return $fallback;
            }

            $provider = strtolower(trim((string) ($existing['provider'] ?? '')));
            $transactionType = trim((string) ($existing['transactionType'] ?? ''));
            $callbackOrderId = trim((string) ($existing['orderId'] ?? ''));
            $depositId = !empty($ids['depositId'])
                ? (int) $ids['depositId']
                : (!empty($existing['depositId']) ? (int) $existing['depositId'] : null);
            $withdrawalId = !empty($ids['withdrawalId'])
                ? (int) $ids['withdrawalId']
                : (!empty($existing['withdrawalId']) ? (int) $existing['withdrawalId'] : null);

            $providerOrderId = trim((string) ($ids['providerOrderId'] ?? ''));
            if ($providerOrderId === '') {
                $providerOrderId = $this->resolveProviderOrderIdFromBusinessRow(
                    $transactionType,
                    $depositId,
                    $withdrawalId
                );
            }

            return $this->requestLogService->findAttemptForCallback(
                $provider,
                $transactionType,
                $depositId,
                $withdrawalId,
                $callbackOrderId !== '' ? $callbackOrderId : null,
                $providerOrderId !== '' ? $providerOrderId : null
            );
        } catch (Throwable $e) {
            Logger::error('resolveCallbackRequestCorrelation failed: ' . $e->getMessage(), [
                'domain' => 'payment',
                'logId' => $logId,
            ]);
            return $fallback;
        }
    }

    /**
     * Prefer gatewayTransactionId from deposit/withdrawal when callback omits provider id.
     */
    private function resolveProviderOrderIdFromBusinessRow($transactionType, $depositId, $withdrawalId) {
        try {
            if ($transactionType === 'deposit' && $depositId) {
                $row = $this->depositModel->findById((int) $depositId);
                $gatewayTxn = trim((string) ($row['gatewayTransactionId'] ?? ''));
                return $gatewayTxn !== '' ? $gatewayTxn : '';
            }
            if ($transactionType === 'withdrawal' && $withdrawalId) {
                $row = $this->withdrawalModel->findById((int) $withdrawalId);
                $gatewayTxn = trim((string) ($row['gatewayTransactionId'] ?? ''));
                return $gatewayTxn !== '' ? $gatewayTxn : '';
            }
        } catch (Throwable $e) {
            // ignore
        }
        return '';
    }

    private function findDepositByOrderId($orderId) {
        $depositId = $this->extractPrefixedId($orderId, 'utrada-deposit-');
        if ($depositId !== null) {
            $record = $this->depositModel->findById($depositId);
            if ($record) {
                return $record;
            }
        }

        $db = Database::getInstance();
        return $db->fetchOne(
            'SELECT * FROM deposits WHERE transactionId = :orderId OR merchantOrderNo = :orderId ORDER BY id DESC LIMIT 1',
            ['orderId' => $orderId]
        );
    }

    private function findWithdrawalByOrderId($orderId) {
        $withdrawalId = $this->extractPrefixedId($orderId, 'utrada-withdraw-');
        if ($withdrawalId !== null) {
            $record = $this->withdrawalModel->findById($withdrawalId);
            if ($record) {
                return $record;
            }
        }

        $db = Database::getInstance();
        return $db->fetchOne(
            'SELECT * FROM withdrawals WHERE transactionId = :orderId ORDER BY id DESC LIMIT 1',
            ['orderId' => $orderId]
        );
    }

    private function findPaymentAsiaWithdrawalByRequestReference(string $requestReference) {
        $value = trim($requestReference);
        if ($value === '') {
            return null;
        }

        $db = Database::getInstance();
        return $db->fetchOne(
            'SELECT * FROM withdrawals WHERE transactionId = :requestReference OR gatewayTransactionId = :requestReference ORDER BY id DESC LIMIT 1',
            ['requestReference' => $value]
        );
    }

    private function extractPrefixedId($orderId, $prefix) {
        $value = trim((string)$orderId);
        if (strpos($value, $prefix) !== 0) {
            return null;
        }

        $idPart = substr($value, strlen($prefix));
        if ($idPart === '' || !ctype_digit($idPart)) {
            return null;
        }

        $id = (int)$idPart;
        return $id > 0 ? $id : null;
    }

    private function markDepositStatusIfAllowed($deposit, $newStatus, $description) {
        $currentStatus = (string)($deposit['status'] ?? '');
        if ($currentStatus === 'completed') {
            return;
        }

        $db = Database::getInstance();
        $db->query(
            'UPDATE deposits SET status = :status, failureReason = :reason WHERE id = :id',
            [
                'status' => $newStatus,
                'reason' => $description,
                'id' => (int)$deposit['id']
            ]
        );
        $db->query(
            'INSERT INTO depositStatusHistory (depositId, previousStatus, newStatus, description, changedBy) VALUES (:depositId, :previousStatus, :newStatus, :description, :changedBy)',
            [
                'depositId' => (int)$deposit['id'],
                'previousStatus' => $currentStatus !== '' ? $currentStatus : null,
                'newStatus' => $newStatus,
                'description' => $description,
                'changedBy' => 0
            ]
        );
    }

    private function persistPaymentAsiaDepositCallbackData(int $depositId, array $normalized): void {
        $update = [
            'gatewayTransactionId' => $normalized['requestReference'] !== '' ? $normalized['requestReference'] : null,
            'gatewayResponse' => json_encode($normalized['raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ];

        try {
            $this->depositModel->update($depositId, $update);
        } catch (Throwable $e) {
            Logger::error('Failed to persist Payment Asia callback data: ' . $e->getMessage());
        }
    }

    private function normalizePaymentAsiaAmount(string $amount): string {
        $value = trim($amount);
        if ($value === '' || !is_numeric($value)) {
            return '';
        }

        return number_format((float)$value, 2, '.', '');
    }

    private function sendDepositStatusNotice(array $deposit, string $status, array $context = []): void
    {
        $clientId = (int)($deposit['userId'] ?? 0);
        $depositId = (int)($deposit['id'] ?? 0);
        if ($clientId <= 0 || $depositId <= 0) {
            return;
        }

        [$subject, $message, $type] = $this->buildDepositStatusNoticePayload($status, $context);
        if ($subject === '' || $message === '' || $type === '') {
            return;
        }

        $metadata = [
            'depositId' => $depositId,
            'clientId' => $clientId,
            'status' => $status,
            'amount' => (float)($deposit['amount'] ?? 0),
            'quotedAmount' => isset($deposit['quotedAmount']) ? (float)$deposit['quotedAmount'] : null,
            'adminNotes' => $context['adminNotes'] ?? null,
            'reason' => $context['reason'] ?? null
        ];

        $priority = in_array($status, ['failed', 'cancelled'], true) ? 'high' : 'medium';
        $this->createClientSystemNotice($clientId, $subject, $message, $type, $metadata, $priority, (int)($context['operatorId'] ?? 0));
    }

    private function sendWithdrawalStatusNotice(array $withdrawal, string $status, array $context = []): void
    {
        $clientId = (int)($withdrawal['userId'] ?? 0);
        $withdrawalId = (int)($withdrawal['id'] ?? 0);
        if ($clientId <= 0 || $withdrawalId <= 0) {
            return;
        }

        [$subject, $message, $type] = $this->buildWithdrawalStatusNoticePayload($status, $context);
        if ($subject === '' || $message === '' || $type === '') {
            return;
        }

        $metadata = [
            'withdrawalId' => $withdrawalId,
            'clientId' => $clientId,
            'status' => $status,
            'amount' => (float)($withdrawal['amount'] ?? 0),
            'platformFee' => (float)($withdrawal['platformFee'] ?? 0),
            'customReason' => $context['customReason'] ?? null
        ];

        $priority = $status === 'rejected' ? 'high' : 'medium';
        $this->createClientSystemNotice($clientId, $subject, $message, $type, $metadata, $priority, (int)($context['operatorId'] ?? 0));
    }

    /**
     * 入金 PSP 取消的客户端通知文案
     * （成功通知由 paymentService->markDepositSuccess 内部发送；
     *   PSP 失败通知由 paymentService->markDepositRejected('reject', ...) 内部发送）
     */
    private function buildDepositStatusNoticePayload(string $status, array $context = []): array
    {
        if ($status !== 'cancelled') {
            return ['', '', ''];
        }

        $subject = 'Your deposit has been cancelled';
        $message = 'Your deposit was cancelled.';
        $reason = trim((string)($context['reason'] ?? ''));
        if ($reason !== '') {
            $message .= ' Reason: ' . $reason;
        }

        return [$subject, $message, 'deposit_cancelled'];
    }

    /**
     * 出金 PSP 完成的客户端通知文案
     * （rejected/cancelled 由 paymentService->markWithdrawalRejected('reject', ...) 内部发送）
     */
    private function buildWithdrawalStatusNoticePayload(string $status, array $context = []): array
    {
        if ($status !== 'completed') {
            return ['', '', ''];
        }

        return [
            'Your withdrawal has been completed',
            'Your withdrawal has been completed successfully.',
            'withdrawal_completed'
        ];
    }

    private function createClientSystemNotice(
        int $clientId,
        string $subject,
        string $message,
        string $type,
        array $metadata,
        string $priority,
        int $operatorId = 0
    ): void {
        $now = date('Y-m-d H:i:s');

        try {
            $notificationId = $this->clientNotificationModel->create([
                'clientId' => $clientId,
                'subject' => $subject,
                'message' => $message,
                'priority' => $priority,
                'scheduleType' => 'immediate',
                'status' => 'sent',
                'emailTemplate' => null,
                'createdBy' => $operatorId > 0 ? $operatorId : null,
                'createdAt' => $now,
                'updatedAt' => $now
            ]);

            if (!$notificationId) {
                return;
            }

            $this->clientSystemNotificationModel->create([
                'notificationId' => $notificationId,
                'type' => $type,
                'metadata' => json_encode($metadata),
                'clientId' => $clientId,
                'subject' => $subject,
                'message' => $message,
                'isRead' => 0,
                'readAt' => null,
                'createdAt' => $now
            ]);
        } catch (Exception $e) {
            // Callback notice failures should not block status processing.
        }
    }
}
