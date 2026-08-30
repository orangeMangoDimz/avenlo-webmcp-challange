<?php
/**
 * Track individual Swoole background jobs.
 * Never throws — tracking failures must not break business operations.
 */

require_once __DIR__ . '/../models/BackgroundJob.php';
require_once __DIR__ . '/../utils/RequestLogContext.php';

class BackgroundJobService {
    public const TIMEOUT_SHORT_SECONDS = 300;
    public const TIMEOUT_ORDER_SYNC_SECONDS = 900;
    public const TIMEOUT_EXPORT_SECONDS = 3600;

    /** @var BackgroundJob|null */
    private $model;

    public function __construct() {
        // Lazy model construction.
    }

    /**
     * @param array<string,mixed> $fields
     * @return string|null jobId
     */
    public function createQueued(array $fields) {
        try {
            $type = trim((string) ($fields['type'] ?? ''));
            if ($type === '') {
                return null;
            }

            $jobId = trim((string) ($fields['jobId'] ?? ''));
            if ($jobId === '') {
                $jobId = RequestLogContext::generateId();
            }

            $existing = $this->jobs()->findByJobId($jobId);
            if ($existing) {
                return $jobId;
            }

            $now = gmdate('Y-m-d H:i:s');
            $userType = isset($fields['userType']) ? trim((string) $fields['userType']) : 'system';
            if ($userType === '' || !in_array($userType, ['admin', 'client', 'system'], true)) {
                $userType = 'system';
            }

            $userId = $fields['userId'] ?? null;
            if ($userId !== null) {
                $userId = (int) $userId;
                if ($userId <= 0) {
                    $userId = null;
                }
            }

            $row = [
                'jobId' => $jobId,
                'schedulerRunId' => isset($fields['schedulerRunId']) && $fields['schedulerRunId'] !== null
                    ? (int) $fields['schedulerRunId']
                    : null,
                'type' => mb_substr($type, 0, 64),
                'status' => 'queued',
                'taskId' => isset($fields['taskId']) ? (int) $fields['taskId'] : null,
                'serverInstanceId' => isset($fields['serverInstanceId'])
                    ? (string) $fields['serverInstanceId']
                    : null,
                'requestId' => $fields['requestId'] ?? null,
                'correlationId' => $fields['correlationId'] ?? null,
                'userId' => $userId,
                'userType' => $userType,
                'queuedAt' => $now,
                'createdAt' => $now,
                'updatedAt' => $now,
            ];

            $this->jobs()->create($row);
            return $jobId;
        } catch (Throwable $e) {
            $this->safeLog('createQueued failed: ' . $e->getMessage());
            return null;
        }
    }

    public function createSystemJob($type, $schedulerRunDbId = null, $serverInstanceId = null) {
        return $this->createQueued([
            'type' => $type,
            'schedulerRunId' => $schedulerRunDbId,
            'serverInstanceId' => $serverInstanceId,
            'userType' => 'system',
            'userId' => null,
            'requestId' => null,
            'correlationId' => null,
        ]);
    }

    public function createFromUserContext($type, $schedulerRunDbId = null, array $extra = []) {
        $ctx = RequestLogContext::get();
        $userType = trim((string) ($extra['userType'] ?? $ctx['userType'] ?? 'system'));
        if (!in_array($userType, ['admin', 'client', 'system'], true)) {
            $userType = 'system';
        }

        return $this->createQueued(array_merge([
            'type' => $type,
            'schedulerRunId' => $schedulerRunDbId,
            'requestId' => $extra['requestId'] ?? ($ctx['requestId'] ?? null),
            'correlationId' => $extra['correlationId'] ?? ($ctx['correlationId'] ?? null),
            'userId' => $extra['userId'] ?? ($ctx['userId'] ?? null),
            'userType' => $userType,
            'jobId' => $extra['jobId'] ?? ($ctx['jobId'] ?? null),
            'serverInstanceId' => $extra['serverInstanceId'] ?? null,
        ], $extra));
    }

    public function attachTaskId($jobId, $taskId, $serverInstanceId = null) {
        try {
            $row = $this->jobs()->findByJobId($jobId);
            if (!$row) {
                return false;
            }
            $data = [
                'taskId' => $taskId === false || $taskId === null ? null : (int) $taskId,
                'updatedAt' => gmdate('Y-m-d H:i:s'),
            ];
            if ($serverInstanceId !== null && $serverInstanceId !== '') {
                $data['serverInstanceId'] = (string) $serverInstanceId;
            }
            $this->jobs()->update((int) $row['id'], $data);
            return true;
        } catch (Throwable $e) {
            $this->safeLog('attachTaskId failed: ' . $e->getMessage());
            return false;
        }
    }

    public function markFailedQueue($jobId, $error) {
        try {
            $row = $this->jobs()->findByJobId($jobId);
            if (!$row) {
                return false;
            }
            $now = gmdate('Y-m-d H:i:s');
            $this->jobs()->update((int) $row['id'], [
                'status' => 'failed',
                'errorMessage' => mb_substr((string) $error, 0, 65000),
                'finishedAt' => $now,
                'updatedAt' => $now,
            ]);
            return true;
        } catch (Throwable $e) {
            $this->safeLog('markFailedQueue failed: ' . $e->getMessage());
            return false;
        }
    }

    public function markRunning($jobId, $workerId, $processId) {
        try {
            $row = $this->jobs()->findByJobId($jobId);
            if (!$row) {
                return false;
            }
            $now = gmdate('Y-m-d H:i:s');
            $this->jobs()->update((int) $row['id'], [
                'status' => 'running',
                'workerId' => (int) $workerId,
                'processId' => (int) $processId,
                'startedAt' => $now,
                'lastHeartbeatAt' => $now,
                'updatedAt' => $now,
            ]);
            return true;
        } catch (Throwable $e) {
            $this->safeLog('markRunning failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param array<string,mixed> $progress progressPercent, processed, total, currentStep
     */
    public function heartbeat($jobId, array $progress = []) {
        try {
            $row = $this->jobs()->findByJobId($jobId);
            if (!$row) {
                return false;
            }

            $data = [];
            foreach (['progressPercent', 'processed', 'total', 'currentStep'] as $key) {
                if (array_key_exists($key, $progress)) {
                    $data[$key] = $progress[$key];
                }
            }
            if (isset($data['progressPercent'])) {
                $data['progressPercent'] = max(0, min(100, (int) $data['progressPercent']));
            }
            if (isset($data['processed'])) {
                $data['processed'] = max(0, (int) $data['processed']);
            }
            if (isset($data['total'])) {
                $data['total'] = max(0, (int) $data['total']);
            }

            if (!$this->progressFieldsChanged($row, $data)) {
                return true;
            }

            $data['lastHeartbeatAt'] = gmdate('Y-m-d H:i:s');
            $data['updatedAt'] = gmdate('Y-m-d H:i:s');
            $this->jobs()->update((int) $row['id'], $data);
            return true;
        } catch (Throwable $e) {
            $this->safeLog('job heartbeat failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync export progress-file fields onto backgroundJobs.
     * Writes only when status or progress counters actually change.
     *
     * @param array<string,mixed> $progress percent, processed, total, status, message
     */
    public function syncExportProgress($jobId, array $progress) {
        try {
            $row = $this->jobs()->findByJobId($jobId);
            if (!$row) {
                return false;
            }

            $data = [];
            $mappedStatus = $this->mapExportStatusToJobStatus((string) ($progress['status'] ?? ''));
            if ($mappedStatus !== null) {
                $data['status'] = $mappedStatus;
            }

            if (array_key_exists('percent', $progress)) {
                $data['progressPercent'] = max(0, min(100, (int) $progress['percent']));
            }
            if (array_key_exists('processed', $progress)) {
                $data['processed'] = max(0, (int) $progress['processed']);
            }
            if (array_key_exists('total', $progress)) {
                $data['total'] = max(0, (int) $progress['total']);
            }
            if (array_key_exists('message', $progress) && $progress['message'] !== null && $progress['message'] !== '') {
                $data['currentStep'] = mb_substr((string) $progress['message'], 0, 255);
            }

            if (!$this->progressFieldsChanged($row, $data)) {
                return true;
            }

            $now = gmdate('Y-m-d H:i:s');
            $data['lastHeartbeatAt'] = $now;
            $data['updatedAt'] = $now;

            if (isset($data['status']) && in_array($data['status'], ['completed', 'failed', 'cancelled'], true)) {
                if (empty($row['finishedAt'])) {
                    $data['finishedAt'] = $now;
                }
            }

            $this->jobs()->update((int) $row['id'], $data);
            return true;
        } catch (Throwable $e) {
            $this->safeLog('syncExportProgress failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $incoming
     */
    private function progressFieldsChanged(array $row, array $incoming) {
        if (empty($incoming)) {
            return false;
        }
        $checks = [
            'progressPercent' => 'int',
            'processed' => 'int',
            'total' => 'int',
            'currentStep' => 'string',
            'status' => 'string',
        ];
        foreach ($checks as $key => $type) {
            if (!array_key_exists($key, $incoming)) {
                continue;
            }
            $old = $row[$key] ?? null;
            $new = $incoming[$key];
            if ($type === 'int') {
                if ((int) $old !== (int) $new) {
                    return true;
                }
                continue;
            }
            if ((string) ($old ?? '') !== (string) ($new ?? '')) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return string|null null = do not change job status
     */
    private function mapExportStatusToJobStatus($exportStatus) {
        switch (strtolower(trim((string) $exportStatus))) {
            case 'queued':
                return 'queued';
            case 'running':
            case 'cancelling':
                return 'running';
            case 'cancelled':
                return 'cancelled';
            case 'done':
                return 'completed';
            case 'error':
                return 'failed';
            default:
                return null;
        }
    }

    public function markCompleted($jobId, $result = null) {
        try {
            $row = $this->jobs()->findByJobId($jobId);
            if (!$row) {
                return false;
            }
            $now = gmdate('Y-m-d H:i:s');
            $data = [
                'status' => 'completed',
                'progressPercent' => 100,
                'finishedAt' => $now,
                'lastHeartbeatAt' => $now,
                'updatedAt' => $now,
                'errorMessage' => null,
            ];
            if (is_array($result)) {
                if (array_key_exists('processed', $result)) {
                    $data['processed'] = max(0, (int) $result['processed']);
                }
                if (array_key_exists('total', $result)) {
                    $data['total'] = max(0, (int) $result['total']);
                    if (!array_key_exists('processed', $result)) {
                        $data['processed'] = $data['total'];
                    }
                }
                if (array_key_exists('percent', $result) && $result['percent'] !== null) {
                    $data['progressPercent'] = max(0, min(100, (int) $result['percent']));
                }
                $data['result'] = $this->encodeResult($result);
            } elseif ($result !== null) {
                $data['result'] = $this->encodeResult($result);
            }
            $this->jobs()->update((int) $row['id'], $data);
            return true;
        } catch (Throwable $e) {
            $this->safeLog('markCompleted failed: ' . $e->getMessage());
            return false;
        }
    }

    public function markFailed($jobId, $error) {
        try {
            $row = $this->jobs()->findByJobId($jobId);
            if (!$row) {
                return false;
            }
            $now = gmdate('Y-m-d H:i:s');
            $this->jobs()->update((int) $row['id'], [
                'status' => 'failed',
                'errorMessage' => mb_substr((string) $error, 0, 65000),
                'finishedAt' => $now,
                'lastHeartbeatAt' => $now,
                'updatedAt' => $now,
            ]);
            return true;
        } catch (Throwable $e) {
            $this->safeLog('markFailed failed: ' . $e->getMessage());
            return false;
        }
    }

    public function markCancelled($jobId, $message = 'Cancelled', array $extra = []) {
        try {
            $row = $this->jobs()->findByJobId($jobId);
            if (!$row) {
                return false;
            }
            $now = gmdate('Y-m-d H:i:s');
            $data = [
                'status' => 'cancelled',
                'errorMessage' => mb_substr((string) $message, 0, 65000),
                'finishedAt' => $now,
                'lastHeartbeatAt' => $now,
                'updatedAt' => $now,
            ];
            if (array_key_exists('progressPercent', $extra)) {
                $data['progressPercent'] = max(0, min(100, (int) $extra['progressPercent']));
            }
            if (array_key_exists('processed', $extra)) {
                $data['processed'] = max(0, (int) $extra['processed']);
            }
            if (array_key_exists('total', $extra)) {
                $data['total'] = max(0, (int) $extra['total']);
            }
            $this->jobs()->update((int) $row['id'], $data);
            return true;
        } catch (Throwable $e) {
            $this->safeLog('markCancelled failed: ' . $e->getMessage());
            return false;
        }
    }

    public function reapStaleJobs() {
        try {
            return $this->jobs()->markStaleByHeartbeat([
                'sync_orders_balances' => self::TIMEOUT_ORDER_SYNC_SECONDS,
                'reconcile_psp_deposits' => self::TIMEOUT_SHORT_SECONDS,
                'reconcile_fivepay_deposits' => self::TIMEOUT_SHORT_SECONDS,
                'export_commission_report' => self::TIMEOUT_EXPORT_SECONDS,
                'export_deposit_withdraw_report' => self::TIMEOUT_EXPORT_SECONDS,
                'export_admin_ib_commission_detail' => self::TIMEOUT_EXPORT_SECONDS,
                'export_admin_ib_network_clients' => self::TIMEOUT_EXPORT_SECONDS,
                'export_custom_report_widget' => self::TIMEOUT_EXPORT_SECONDS,
                'export_admin_funding_report' => self::TIMEOUT_EXPORT_SECONDS,
                'export_admin_ib_statement_report' => self::TIMEOUT_EXPORT_SECONDS,
                'export_client_ib_statement_report' => self::TIMEOUT_EXPORT_SECONDS,
                'export_admin_operation_log_report' => self::TIMEOUT_EXPORT_SECONDS,
            ], self::TIMEOUT_SHORT_SECONDS);
        } catch (Throwable $e) {
            $this->safeLog('reapStaleJobs failed: ' . $e->getMessage());
            return 0;
        }
    }

    private function jobs() {
        if ($this->model === null) {
            $this->model = new BackgroundJob();
        }
        return $this->model;
    }

    private function encodeResult($result) {
        if ($result === null) {
            return null;
        }
        if (is_string($result)) {
            return $result;
        }
        $sanitized = $this->sanitize($result);
        $json = json_encode(
            $sanitized,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
        return $json === false ? null : $json;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function sanitize($value) {
        $sensitive = ['password', 'token', 'authorization', 'secret', 'apikey', 'privatekey'];
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $key));
                $isSensitive = false;
                foreach ($sensitive as $fragment) {
                    if ($normalized === $fragment || strpos($normalized, $fragment) !== false) {
                        $isSensitive = true;
                        break;
                    }
                }
                $out[$key] = $isSensitive ? '[REDACTED]' : $this->sanitize($item);
            }
            return $out;
        }
        if (is_string($value) && strlen($value) > 8000) {
            return mb_substr($value, 0, 8000) . '…[truncated]';
        }
        return $value;
    }

    private function safeLog($message) {
        @error_log('[BackgroundJobService] ' . $message);
    }
}
