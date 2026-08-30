<?php
/**
 * Track Swoole scheduler status and run history.
 * Never throws — tracking failures must not break business operations.
 */

require_once __DIR__ . '/../models/SwooleSchedulerStatus.php';
require_once __DIR__ . '/../models/SwooleSchedulerRun.php';
require_once __DIR__ . '/../utils/RequestLogContext.php';

class SwooleSchedulerService {
    public const SCHEDULER_BUSINESS = 'business_scheduler';
    public const SCHEDULER_WORKER_MONITOR = 'worker_monitor';
    public const STALE_INTERVAL_MULTIPLIER = 3;

    /** @var string|null */
    private static $serverInstanceId = null;

    /** @var SwooleSchedulerStatus|null */
    private $statusModel;

    /** @var SwooleSchedulerRun|null */
    private $runModel;

    public function __construct() {
        // Lazy model construction inside methods.
    }

    public static function ensureServerInstanceId($existing = null) {
        if (is_string($existing) && $existing !== '') {
            self::$serverInstanceId = $existing;
            return self::$serverInstanceId;
        }
        if (self::$serverInstanceId === null || self::$serverInstanceId === '') {
            self::$serverInstanceId = strtoupper(bin2hex(random_bytes(16)));
        }
        return self::$serverInstanceId;
    }

    public static function getServerInstanceId() {
        return self::ensureServerInstanceId();
    }

    /**
     * @param array<string,mixed> $opts
     * @return int|null scheduler status row id
     */
    public function registerScheduler($schedulerKey, array $opts = []) {
        try {
            $schedulerKey = trim((string) $schedulerKey);
            if ($schedulerKey === '') {
                return null;
            }

            $serverInstanceId = (string) ($opts['serverInstanceId']
                ?? self::ensureServerInstanceId());
            $now = gmdate('Y-m-d H:i:s');
            $intervalMs = (int) ($opts['intervalMs'] ?? $this->defaultIntervalMs($schedulerKey));

            $defaults = $this->defaultsForKey($schedulerKey);
            $data = [
                'name' => (string) ($opts['name'] ?? $defaults['name']),
                'schedulerType' => (string) ($opts['schedulerType'] ?? $defaults['schedulerType']),
                'intervalMs' => $intervalMs > 0 ? $intervalMs : $defaults['intervalMs'],
                'status' => 'active',
                'workerId' => isset($opts['workerId']) ? (int) $opts['workerId'] : null,
                'processId' => isset($opts['processId']) ? (int) $opts['processId'] : getmypid(),
                'serverInstanceId' => $serverInstanceId,
                'timerId' => isset($opts['timerId']) ? (int) $opts['timerId'] : null,
                'heartbeatAt' => $now,
                'lastTickAt' => $opts['lastTickAt'] ?? null,
                'nextRunAt' => $opts['nextRunAt'] ?? $this->computeNextRunAt($intervalMs),
            ];

            if (isset($opts['metadata'])) {
                $data['metadata'] = $this->encodeJson($opts['metadata']);
            }

            $model = $this->status();
            $model->markOtherInstancesStale($serverInstanceId);
            return $model->upsertByKey($schedulerKey, $data);
        } catch (Throwable $e) {
            $this->safeLog('registerScheduler failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @param array<string,mixed> $extra
     */
    public function heartbeat($schedulerKey, array $extra = []) {
        try {
            $schedulerKey = trim((string) $schedulerKey);
            if ($schedulerKey === '') {
                return false;
            }

            $now = gmdate('Y-m-d H:i:s');
            $data = array_merge([
                'status' => 'active',
                'heartbeatAt' => $now,
                'lastTickAt' => $now,
                'processId' => getmypid(),
                'updatedAt' => $now,
            ], $extra);

            if (isset($data['metadata']) && !is_string($data['metadata'])) {
                $data['metadata'] = $this->encodeJson($data['metadata']);
            }

            $intervalMs = isset($extra['intervalMs'])
                ? (int) $extra['intervalMs']
                : $this->defaultIntervalMs($schedulerKey);
            if (!isset($data['nextRunAt'])) {
                $data['nextRunAt'] = $this->computeNextRunAt($intervalMs);
            }

            $id = $this->status()->upsertByKey($schedulerKey, $data);
            return $id !== null;
        } catch (Throwable $e) {
            $this->safeLog('scheduler heartbeat failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @return array{runId:string,schedulerId:int,dbId:int}|null
     */
    public function beginRun($schedulerKey, $serverInstanceId) {
        try {
            $schedulerKey = trim((string) $schedulerKey);
            $serverInstanceId = trim((string) $serverInstanceId);
            if ($schedulerKey === '') {
                return null;
            }

            $status = $this->status()->findOne(['schedulerKey' => $schedulerKey]);
            if (!$status) {
                $schedulerId = $this->registerScheduler($schedulerKey, [
                    'serverInstanceId' => $serverInstanceId,
                ]);
                if ($schedulerId === null) {
                    return null;
                }
            } else {
                $schedulerId = (int) $status['id'];
            }

            $now = gmdate('Y-m-d H:i:s');
            $runId = RequestLogContext::generateId();

            $dbId = $this->runs()->create([
                'schedulerId' => $schedulerId,
                'runId' => $runId,
                'serverInstanceId' => $serverInstanceId !== ''
                    ? $serverInstanceId
                    : self::ensureServerInstanceId(),
                'status' => 'running',
                'startedAt' => $now,
                'discoveredCount' => 0,
                'dispatchedCount' => 0,
                'failedCount' => 0,
                'createdAt' => $now,
            ]);

            if ($dbId === false || $dbId === null) {
                return null;
            }

            $this->status()->update($schedulerId, [
                'lastTickAt' => $now,
                'lastStartedAt' => $now,
                'heartbeatAt' => $now,
                'status' => 'active',
                'updatedAt' => $now,
            ]);

            return [
                'runId' => $runId,
                'schedulerId' => $schedulerId,
                'dbId' => (int) $dbId,
            ];
        } catch (Throwable $e) {
            $this->safeLog('beginRun failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @param array<string,mixed> $counts discoveredCount, dispatchedCount, failedCount, status, errorMessage
     */
    public function finishRun($runId, array $counts = []) {
        try {
            $run = $this->runs()->findByRunId($runId);
            if (!$run) {
                return false;
            }

            $now = gmdate('Y-m-d H:i:s');
            $startedTs = strtotime((string) $run['startedAt'] . ' UTC');
            $durationMs = $startedTs ? (int) max(0, (time() - $startedTs) * 1000) : null;

            $status = (string) ($counts['status'] ?? 'completed');
            if (!in_array($status, ['completed', 'failed', 'skipped', 'overlapping'], true)) {
                $status = 'completed';
            }

            $this->runs()->update((int) $run['id'], [
                'status' => $status,
                'finishedAt' => $now,
                'durationMs' => $durationMs,
                'discoveredCount' => (int) ($counts['discoveredCount'] ?? $run['discoveredCount'] ?? 0),
                'dispatchedCount' => (int) ($counts['dispatchedCount'] ?? $run['dispatchedCount'] ?? 0),
                'failedCount' => (int) ($counts['failedCount'] ?? $run['failedCount'] ?? 0),
                'errorMessage' => isset($counts['errorMessage'])
                    ? mb_substr((string) $counts['errorMessage'], 0, 65000)
                    : null,
            ]);

            $schedulerUpdate = [
                'heartbeatAt' => $now,
                'lastCompletedAt' => $status === 'completed' ? $now : null,
                'lastDispatchedCount' => (int) ($counts['dispatchedCount'] ?? 0),
                'updatedAt' => $now,
            ];
            if ($status === 'failed' && !empty($counts['errorMessage'])) {
                $schedulerUpdate['lastErrorMessage'] = mb_substr((string) $counts['errorMessage'], 0, 65000);
                $schedulerUpdate['status'] = 'error';
            } else {
                $schedulerUpdate['status'] = 'active';
                $schedulerUpdate['lastErrorMessage'] = null;
            }
            if ($status === 'completed') {
                $schedulerUpdate['lastCompletedAt'] = $now;
            }

            $this->status()->update((int) $run['schedulerId'], $schedulerUpdate);
            return true;
        } catch (Throwable $e) {
            $this->safeLog('finishRun failed: ' . $e->getMessage());
            return false;
        }
    }

    public function skipRun($runId, $reason = '') {
        return $this->finishRun($runId, [
            'status' => 'skipped',
            'errorMessage' => $reason,
            'discoveredCount' => 0,
            'dispatchedCount' => 0,
            'failedCount' => 0,
        ]);
    }

    public function markSchedulerError($schedulerKey, $message) {
        try {
            $row = $this->status()->findOne(['schedulerKey' => trim((string) $schedulerKey)]);
            if (!$row) {
                return false;
            }
            $this->status()->update((int) $row['id'], [
                'status' => 'error',
                'lastErrorMessage' => mb_substr((string) $message, 0, 65000),
                'updatedAt' => gmdate('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (Throwable $e) {
            $this->safeLog('markSchedulerError failed: ' . $e->getMessage());
            return false;
        }
    }

    public function reapStaleSchedulers() {
        try {
            return $this->status()->markStaleByHeartbeat(self::STALE_INTERVAL_MULTIPLIER);
        } catch (Throwable $e) {
            $this->safeLog('reapStaleSchedulers failed: ' . $e->getMessage());
            return 0;
        }
    }

    private function status() {
        if ($this->statusModel === null) {
            $this->statusModel = new SwooleSchedulerStatus();
        }
        return $this->statusModel;
    }

    private function runs() {
        if ($this->runModel === null) {
            $this->runModel = new SwooleSchedulerRun();
        }
        return $this->runModel;
    }

    private function defaultsForKey($schedulerKey) {
        if ($schedulerKey === self::SCHEDULER_WORKER_MONITOR) {
            return [
                'name' => 'Worker Monitor',
                'schedulerType' => 'watchdog',
                'intervalMs' => 10000,
            ];
        }
        if ($schedulerKey === self::SCHEDULER_BUSINESS) {
            return [
                'name' => 'Business Scheduler',
                'schedulerType' => 'business',
                'intervalMs' => 60000,
            ];
        }
        return [
            'name' => $schedulerKey,
            'schedulerType' => 'maintenance',
            'intervalMs' => 60000,
        ];
    }

    private function defaultIntervalMs($schedulerKey) {
        return (int) $this->defaultsForKey($schedulerKey)['intervalMs'];
    }

    private function computeNextRunAt($intervalMs) {
        $intervalMs = max(1000, (int) $intervalMs);
        return gmdate('Y-m-d H:i:s', time() + (int) ceil($intervalMs / 1000));
    }

    private function encodeJson($value) {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        $json = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
        return $json === false ? null : $json;
    }

    private function safeLog($message) {
        @error_log('[SwooleSchedulerService] ' . $message);
    }
}
