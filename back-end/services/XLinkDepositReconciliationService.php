<?php

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../models/Deposit.php';
require_once __DIR__ . '/PaymentSettlementService.php';
require_once __DIR__ . '/XLinkService.php';

/**
 * Reconcile unpaid X-Link deposits after their payment-link expiry window.
 *
 * Provider status remains authoritative. Transport or pending responses leave
 * the deposit unchanged for a later retry.
 */
class XLinkDepositReconciliationService
{
    private const DEFAULT_EXPIRY_SECONDS = 900;
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
            if ($depositId <= 0) {
                continue;
            }

            $lockName = $this->lockName($depositId);
            if (!$this->acquireLock($lockName)) {
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
                Logger::error('X-Link deposit reconciliation failed', [
                    'domain' => 'payment',
                    'provider' => XLinkService::PROVIDER_KEY,
                    'depositId' => $depositId,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                $this->releaseLock($lockName);
            }
        }

        $result['success'] = $result['errors'] === 0;
        return $result;
    }

    private function findDueDeposits(int $limit): array
    {
        $fallbackMinutes = (int)(self::DEFAULT_EXPIRY_SECONDS / 60);
        $sql = "SELECT d.*, pgs.gatewayKey, pgs.apiKey, pgs.secretKey, pgs.configData
                FROM deposits d
                INNER JOIN paymentGatewaySettings pgs ON pgs.id = d.gatewaySettingId
                WHERE pgs.isEnabled = 1
                  AND pgs.deletedAt IS NULL
                  AND (
                      LOWER(pgs.gatewayKey) = 'xlink-krw'
                      OR (
                          JSON_VALID(pgs.configData)
                          AND LOWER(JSON_UNQUOTE(JSON_EXTRACT(pgs.configData, '$.providerKey'))) = 'xlink'
                      )
                  )
                  AND d.status IN ('unpaid', 'pending', 'processing')
                  AND (
                      (
                          d.expiredAt IS NOT NULL
                          AND d.expiredAt <= UTC_TIMESTAMP()
                      )
                      OR (
                          d.expiredAt IS NULL
                          AND COALESCE(d.requestedAt, d.createdAt) <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL {$fallbackMinutes} MINUTE)
                      )
                  )
                ORDER BY COALESCE(
                    d.expiredAt,
                    DATE_ADD(COALESCE(d.requestedAt, d.createdAt), INTERVAL {$fallbackMinutes} MINUTE)
                ) ASC, d.id ASC
                LIMIT {$limit}";

        return $this->db->fetchAll($sql);
    }

    private function reconcile(array $candidate): array
    {
        $depositId = (int)($candidate['id'] ?? 0);
        $transactionId = trim((string)($candidate['transactionId'] ?? ''));
        if ($transactionId === '') {
            throw new RuntimeException('Deposit transactionId is missing');
        }

        $service = new XLinkService($candidate);
        if (!$service->isConfigured()) {
            throw new RuntimeException('X-Link gateway is not configured');
        }

        try {
            $response = $service->getOperation($transactionId);
        } catch (XLinkApiException $exception) {
            if ($exception->getHttpStatus() === 404) {
                Logger::info('X-Link deposit reconciliation completed', [
                    'domain' => 'payment',
                    'provider' => XLinkService::PROVIDER_KEY,
                    'depositId' => $depositId,
                    'transactionId' => $transactionId,
                    'providerStatus' => 'not_found',
                    'mappedStatus' => 'cancelled',
                ]);
                return $this->applyProviderStatus($candidate, 'cancelled', 'not_found');
            }
            if ($exception->isAmbiguous()) {
                Logger::warning('X-Link deposit reconciliation inquiry is ambiguous', [
                    'domain' => 'payment',
                    'provider' => XLinkService::PROVIDER_KEY,
                    'depositId' => $depositId,
                    'transactionId' => $transactionId,
                    'httpStatus' => $exception->getHttpStatus(),
                    'error' => $exception->getMessage(),
                ]);
                return ['success' => true, 'changed' => false];
            }
            Logger::error('X-Link deposit status inquiry failed', [
                'domain' => 'payment',
                'provider' => XLinkService::PROVIDER_KEY,
                'depositId' => $depositId,
                'transactionId' => $transactionId,
                'httpStatus' => $exception->getHttpStatus(),
                'error' => $exception->getMessage(),
            ]);
            return ['success' => false, 'changed' => false];
        }

        $providerStatus = strtoupper(trim((string)($response['status'] ?? '')));
        $mappedStatus = XLinkService::mapOperationStatus($providerStatus);

        Logger::info('X-Link deposit reconciliation completed', [
            'domain' => 'payment',
            'provider' => XLinkService::PROVIDER_KEY,
            'depositId' => $depositId,
            'transactionId' => $transactionId,
            'providerStatus' => $providerStatus,
            'mappedStatus' => $mappedStatus,
        ]);

        if (in_array($mappedStatus, ['pending', 'processing'], true)) {
            return ['success' => true, 'changed' => false];
        }
        if ($mappedStatus === 'success') {
            return $this->applyProviderStatus($candidate, 'success', $providerStatus !== '' ? $providerStatus : 'SUCCESS');
        }
        if ($mappedStatus === 'expired') {
            return $this->applyProviderStatus($candidate, 'expired', $providerStatus !== '' ? $providerStatus : 'EXPIRED');
        }
        if (in_array($mappedStatus, ['cancelled', 'failed'], true)) {
            return $this->applyProviderStatus(
                $candidate,
                'cancelled',
                $providerStatus !== '' ? $providerStatus : strtoupper($mappedStatus)
            );
        }

        Logger::warning('X-Link deposit reconciliation returned an unknown status', [
            'domain' => 'payment',
            'provider' => XLinkService::PROVIDER_KEY,
            'depositId' => $depositId,
            'providerStatus' => $providerStatus,
        ]);
        return ['success' => true, 'changed' => false];
    }

    private function applyProviderStatus(array $candidate, string $mappedStatus, string $providerStatus): array
    {
        $depositId = (int)($candidate['id'] ?? 0);
        $latest = $this->depositModel->getDepositDetails($depositId);
        if (!$latest) {
            throw new RuntimeException('Deposit not found while applying X-Link status');
        }

        $currentStatus = (string)($latest['status'] ?? '');
        if (in_array($currentStatus, ['completed', 'expired', 'cancelled', 'failed', 'rejected'], true)) {
            Logger::warning('X-Link status conflicts with an existing terminal CRM status', [
                'domain' => 'payment',
                'provider' => XLinkService::PROVIDER_KEY,
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
                'Auto-approved by X-Link status reconciliation',
                'auto-approve'
            );
            return ['success' => true, 'changed' => true];
        }

        if ($mappedStatus === 'expired') {
            $reason = 'Deposit expired by X-Link status reconciliation';
        } elseif ($providerStatus === 'not_found') {
            $reason = 'Deposit cancelled because X-Link has no matching order';
        } else {
            $reason = 'Deposit cancelled by X-Link status reconciliation';
        }

        $this->getSettlementService()->markDepositProviderTerminal(
            $latest,
            $mappedStatus === 'expired' ? 'expired' : 'cancelled',
            $reason,
            $providerStatus,
            XLinkService::PROVIDER_KEY
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

    private function lockName(int $depositId): string
    {
        return 'xlink_reconcile_d_' . $depositId;
    }

    private function acquireLock(string $lockName): bool
    {
        try {
            $result = $this->db->fetchOne(
                'SELECT GET_LOCK(:lockName, 0) AS acquired',
                ['lockName' => $lockName]
            );
            return (int)($result['acquired'] ?? 0) === 1;
        } catch (Throwable $e) {
            Logger::warning('Failed to acquire X-Link reconciliation lock: ' . $e->getMessage(), [
                'domain' => 'payment',
                'provider' => XLinkService::PROVIDER_KEY,
                'lockName' => $lockName,
            ]);
            return false;
        }
    }

    private function releaseLock(string $lockName): void
    {
        try {
            $this->db->fetchOne(
                'SELECT RELEASE_LOCK(:lockName) AS released',
                ['lockName' => $lockName]
            );
        } catch (Throwable $e) {
            Logger::warning('Failed to release X-Link reconciliation lock: ' . $e->getMessage(), [
                'domain' => 'payment',
                'provider' => XLinkService::PROVIDER_KEY,
                'lockName' => $lockName,
            ]);
        }
    }
}
