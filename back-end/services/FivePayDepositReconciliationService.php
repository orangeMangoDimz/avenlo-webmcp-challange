<?php

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../models/Deposit.php';
require_once __DIR__ . '/PaymentSettlementService.php';
require_once __DIR__ . '/FivePayService.php';

/**
 * Reconcile 5Pay F2F deposits after their 30-minute payment window.
 *
 * The scheduler only claims deposits that are past their local expiry time.
 * Provider status remains authoritative: pending/error responses leave the
 * deposit unchanged and become eligible for another inquiry after five minutes.
 */
class FivePayDepositReconciliationService
{
    private const EXPIRY_SECONDS = 1800;
    private const RETRY_SECONDS = 300;
    private const LEASE_SECONDS = 240;
    private const DEFAULT_BATCH_LIMIT = 50;

    private $db;
    private $depositModel;
    private $settlementService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->depositModel = new Deposit();
    }

    /**
     * @return array{success:bool, discovered:int, processed:int, changed:int, errors:int}
     */
    public function run(int $limit = self::DEFAULT_BATCH_LIMIT): array
    {
        $limit = max(1, min($limit, 200));
        $candidates = $this->findDueDeposits($limit);
        $result = [
            'success' => true,
            'discovered' => count($candidates),
            'processed' => 0,
            'changed' => 0,
            'errors' => 0,
        ];

        foreach ($candidates as $candidate) {
            $depositId = (int)($candidate['id'] ?? 0);
            if ($depositId <= 0 || !$this->claim($depositId)) {
                continue;
            }

            $result['processed']++;
            try {
                $outcome = $this->reconcile($candidate);
                if (!empty($outcome['changed'])) {
                    $result['changed']++;
                }
                if (empty($outcome['success'])) {
                    $result['errors']++;
                }
            } catch (Throwable $e) {
                $result['errors']++;
                $this->finishAttempt($depositId);
                Logger::error('5Pay deposit reconciliation failed', [
                    'domain' => 'payment',
                    'depositId' => $depositId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $result['success'] = $result['errors'] === 0;
        return $result;
    }

    private function findDueDeposits(int $limit): array
    {
        $sql = "SELECT d.*, pgs.gatewayKey, pgs.apiKey, pgs.secretKey, pgs.configData
                FROM deposits d
                INNER JOIN paymentGatewaySettings pgs ON pgs.id = d.gatewaySettingId
                WHERE pgs.isEnabled = 1
                  AND pgs.deletedAt IS NULL
                  AND (
                      LOWER(pgs.gatewayKey) LIKE '5pay%'
                      OR LOWER(pgs.gatewayKey) LIKE 'spay%'
                      OR (
                          JSON_VALID(pgs.configData)
                          AND LOWER(JSON_UNQUOTE(JSON_EXTRACT(pgs.configData, '$.providerKey'))) IN ('5pay', 'spay')
                      )
                  )
                  AND d.status IN ('unpaid', 'pending', 'processing')
                  AND COALESCE(
                      d.expiredAt,
                      DATE_ADD(COALESCE(d.requestedAt, d.createdAt), INTERVAL 30 MINUTE)
                  ) <= UTC_TIMESTAMP()
                  AND (
                      d.spayStatusCheckLeaseUntil IS NULL
                      OR d.spayStatusCheckLeaseUntil <= UTC_TIMESTAMP()
                  )
                  AND (
                      d.spayLastStatusCheckAt IS NULL
                      OR d.spayLastStatusCheckAt <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)
                  )
                ORDER BY COALESCE(
                    d.expiredAt,
                    DATE_ADD(COALESCE(d.requestedAt, d.createdAt), INTERVAL 30 MINUTE)
                ) ASC, d.id ASC
                LIMIT {$limit}";

        return $this->db->fetchAll($sql);
    }

    private function claim(int $depositId): bool
    {
        $stmt = $this->db->query(
            "UPDATE deposits
             SET spayStatusCheckLeaseUntil = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 4 MINUTE),
                 spayStatusCheckAttempts = COALESCE(spayStatusCheckAttempts, 0) + 1
             WHERE id = :depositId
               AND status IN ('unpaid', 'pending', 'processing')
               AND (
                   spayStatusCheckLeaseUntil IS NULL
                   OR spayStatusCheckLeaseUntil <= UTC_TIMESTAMP()
               )
               AND (
                   spayLastStatusCheckAt IS NULL
                   OR spayLastStatusCheckAt <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)
               )",
            ['depositId' => $depositId]
        );

        return $stmt && $stmt->rowCount() === 1;
    }

    private function reconcile(array $candidate): array
    {
        $depositId = (int)($candidate['id'] ?? 0);
        try {
            $service = new FivePayService($candidate);
            if (!$service->isConfigured()) {
                throw new RuntimeException('5Pay gateway is not configured');
            }

            $transactionId = trim((string)($candidate['transactionId'] ?? ''));
            $merchantOrderNo = FivePayService::buildMerchantOrderNo($transactionId);
            if ($merchantOrderNo === '') {
                throw new RuntimeException('Deposit transactionId is missing');
            }

            $response = $service->getOrderStatus([
                'MerchantId' => $service->getMerchantId(),
                'ChannelName' => FivePayService::CHANNEL_F2F,
                'MerchantOrderNo' => $merchantOrderNo,
                'TimeStamp' => FivePayService::utcTimestamp(),
            ]);
            if (FivePayService::isOrderNotFoundResponse($response)) {
                $this->finishAttempt($depositId);
                Logger::info('5Pay deposit reconciliation completed', [
                    'domain' => 'payment',
                    'depositId' => $depositId,
                    'merchantOrderNo' => $merchantOrderNo,
                    'providerStatus' => 'not_found',
                    'mappedStatus' => 'cancelled',
                ]);
                return $this->applyProviderStatus($candidate, 'cancelled', 'not_found');
            }
            $statusData = $this->validateStatusResponse($service, $response, $candidate, $merchantOrderNo);
            $mappedStatus = FivePayService::mapDepositStatus($statusData['status']);

            $this->finishAttempt($depositId);
            Logger::info('5Pay deposit reconciliation completed', [
                'domain' => 'payment',
                'depositId' => $depositId,
                'merchantOrderNo' => $merchantOrderNo,
                'providerStatus' => $statusData['status'],
                'mappedStatus' => $mappedStatus,
            ]);

            if ($mappedStatus === 'processing') {
                return ['success' => true, 'changed' => false];
            }
            if (!in_array($mappedStatus, ['success', 'expired', 'cancelled'], true)) {
                Logger::warning('5Pay deposit reconciliation returned an unknown status', [
                    'domain' => 'payment',
                    'depositId' => $depositId,
                    'providerStatus' => $statusData['status'],
                ]);
                return ['success' => true, 'changed' => false];
            }

            return $this->applyProviderStatus($candidate, $mappedStatus, $statusData['status']);
        } catch (Throwable $e) {
            $this->finishAttempt($depositId);
            Logger::error('5Pay deposit status inquiry failed', [
                'domain' => 'payment',
                'depositId' => $depositId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'changed' => false];
        }
    }

    private function validateStatusResponse(
        FivePayService $service,
        array $response,
        array $deposit,
        string $merchantOrderNo
    ): array {
        if (!FivePayService::isDepositAccepted($response)) {
            throw new RuntimeException(FivePayService::depositErrorMessage($response));
        }

        $data = $response['Data'] ?? [];
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        if (!is_array($data)) {
            throw new RuntimeException('5Pay status response Data is invalid');
        }

        $details = $data['Details'] ?? $data;
        if (is_string($details)) {
            $details = json_decode($details, true);
        }
        if (!is_array($details)) {
            throw new RuntimeException('5Pay status response Details is invalid');
        }

        $signPayload = $details;
        $sign = trim((string)($signPayload['Sign'] ?? $signPayload['sign'] ?? ''));
        if ($sign === '' && isset($data['Sign'])) {
            $signPayload = $data;
            $sign = trim((string)$data['Sign']);
        }
        if ($sign === '' || !$service->verifySign($signPayload)) {
            throw new RuntimeException('Invalid 5Pay status response signature');
        }

        $responseTimestamp = trim((string)($details['TimeStamp'] ?? $data['TimeStamp'] ?? ''));
        if ($responseTimestamp !== '' && !FivePayService::isTimestampFresh($responseTimestamp)) {
            throw new RuntimeException('Stale 5Pay status response timestamp');
        }

        $expectedMerchantId = $service->getMerchantId();
        $responseMerchantId = trim((string)($details['MerchantId'] ?? $data['MerchantId'] ?? ''));
        if ($responseMerchantId !== '' && $responseMerchantId !== $expectedMerchantId) {
            throw new RuntimeException('5Pay status response MerchantId mismatch');
        }

        $channelName = trim((string)($data['ChannelName'] ?? $details['ChannelName'] ?? ''));
        if ($channelName !== FivePayService::CHANNEL_F2F) {
            throw new RuntimeException('5Pay status response ChannelName mismatch');
        }

        $responseMerchantOrderNo = trim((string)(
            $details['MerchantOrderNo'] ?? $data['MerchantOrderNo'] ?? ''
        ));
        if ($responseMerchantOrderNo !== $merchantOrderNo) {
            throw new RuntimeException('5Pay status response MerchantOrderNo mismatch');
        }

        $responseOrderAmount = trim((string)($details['OrderAmount'] ?? $data['OrderAmount'] ?? ''));
        $expectedAmount = FivePayService::formatAmount($deposit['quotedAmount'] ?? $deposit['amount'] ?? 0);
        if ($responseOrderAmount !== '' && FivePayService::formatAmount($responseOrderAmount) !== $expectedAmount) {
            throw new RuntimeException('5Pay status response amount mismatch');
        }

        $status = trim((string)($details['Status'] ?? $data['Status'] ?? ''));
        if ($status === '') {
            throw new RuntimeException('5Pay status response Status is missing');
        }

        return [
            'status' => $status,
            'details' => $details,
        ];
    }

    private function applyProviderStatus(array $candidate, string $mappedStatus, string $providerStatus): array
    {
        $depositId = (int)($candidate['id'] ?? 0);
        $latest = $this->depositModel->getDepositDetails($depositId);
        if (!$latest) {
            throw new RuntimeException('Deposit not found while applying 5Pay status');
        }

        $currentStatus = (string)($latest['status'] ?? '');
        if (in_array($currentStatus, ['completed', 'expired', 'cancelled', 'failed', 'rejected'], true)) {
            Logger::warning('5Pay status conflicts with an existing terminal CRM status', [
                'domain' => 'payment',
                'depositId' => $depositId,
                'currentStatus' => $currentStatus,
                'providerStatus' => $providerStatus,
                'mappedStatus' => $mappedStatus,
            ]);
            return ['success' => true, 'changed' => false];
        }

        if ($mappedStatus === 'success') {
            $this->getSettlementService()->markDepositSuccess(
                $latest,
                0,
                'Auto-approved by 5Pay status reconciliation',
                'auto-approve'
            );
            return ['success' => true, 'changed' => true];
        }

        if ($mappedStatus === 'expired') {
            $reason = 'Deposit expired by 5Pay status reconciliation';
        } elseif ($providerStatus === 'not_found') {
            $reason = 'Deposit cancelled because 5Pay has no matching order';
        } else {
            $reason = 'Deposit cancelled by 5Pay status reconciliation';
        }
        $this->getSettlementService()->markDepositProviderTerminal(
            $latest,
            $mappedStatus,
            $reason,
            $providerStatus,
            '5pay'
        );

        return ['success' => true, 'changed' => true];
    }

    private function getSettlementService(): PaymentSettlementService
    {
        if ($this->settlementService === null) {
            $this->settlementService = new PaymentSettlementService();
        }
        return $this->settlementService;
    }

    private function finishAttempt(int $depositId): void
    {
        if ($depositId <= 0) {
            return;
        }

        try {
            $this->db->query(
                'UPDATE deposits
                 SET spayLastStatusCheckAt = UTC_TIMESTAMP(),
                     spayStatusCheckLeaseUntil = NULL
                 WHERE id = :depositId',
                ['depositId' => $depositId]
            );
        } catch (Throwable $e) {
            Logger::error('Failed to persist 5Pay reconciliation bookkeeping', [
                'domain' => 'payment',
                'depositId' => $depositId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
