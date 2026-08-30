<?php
/**
 * Persist outbound payment-processor request attempts.
 * Fail-open: never throws; logging failure must not block payments.
 */

require_once __DIR__ . '/../models/PaymentProcessorRequestLog.php';
require_once __DIR__ . '/../utils/RequestLogContext.php';
require_once __DIR__ . '/../utils/Logger.php';

class PaymentProcessorRequestLogService {
    public const STATUS_PREPARED = 'prepared';
    public const STATUS_REDIRECT_ISSUED = 'redirect_issued';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_FAILED = 'failed';
    public const STATUS_TIMEOUT = 'timeout';
    public const STATUS_UNKNOWN = 'unknown';

    private const TERMINAL_STATUSES = [
        self::STATUS_REDIRECT_ISSUED,
        self::STATUS_ACCEPTED,
        self::STATUS_FAILED,
        self::STATUS_TIMEOUT,
        self::STATUS_UNKNOWN,
    ];

    private static $sensitiveKeyFragments = [
        'password',
        'token',
        'authorization',
        'secret',
        'apikey',
        'privatekey',
        'accesstoken',
        'refreshtoken',
        'clientsecret',
        'sign',
        'signature',
        'authkey',
        'merchanttoken',
    ];

    /** Exact keys that must always be redacted (e.g. IBeePay withdrawal `key`). */
    private static $exactSensitiveKeys = [
        'key',
        'sign',
        'signature',
        'secret',
        'secretkey',
        'apikey',
        'authorization',
    ];

    /** @var PaymentProcessorRequestLog|null */
    private $model;

    /**
     * Create a prepared attempt row. Returns log id or null.
     *
     * @param array<string,mixed> $attrs
     * @return int|null
     */
    public function beginAttempt(array $attrs) {
        try {
            $transactionType = trim((string) ($attrs['transactionType'] ?? ''));
            $provider = trim((string) ($attrs['provider'] ?? ''));
            $operation = trim((string) ($attrs['operation'] ?? ''));
            $deliveryMode = trim((string) ($attrs['deliveryMode'] ?? ''));
            $localOrderId = trim((string) ($attrs['localOrderId'] ?? ''));

            if ($provider === '' || $operation === '' || $localOrderId === '') {
                return null;
            }
            if ($transactionType !== 'deposit' && $transactionType !== 'withdrawal') {
                return null;
            }
            if ($deliveryMode !== 'client_redirect' && $deliveryMode !== 'server_http') {
                return null;
            }

            $depositId = isset($attrs['depositId']) ? (int) $attrs['depositId'] : null;
            $withdrawalId = isset($attrs['withdrawalId']) ? (int) $attrs['withdrawalId'] : null;
            if ($depositId !== null && $depositId <= 0) {
                $depositId = null;
            }
            if ($withdrawalId !== null && $withdrawalId <= 0) {
                $withdrawalId = null;
            }

            if ($transactionType === 'deposit') {
                if ($depositId === null || $withdrawalId !== null) {
                    return null;
                }
            } else {
                if ($withdrawalId === null || $depositId !== null) {
                    return null;
                }
            }

            $requestCtx = RequestLogContext::get();
            $now = gmdate('Y-m-d H:i:s');
            $startedAt = isset($attrs['startedAt']) ? (string) $attrs['startedAt'] : $now;
            $attemptNo = $this->logs()->nextAttemptNo($provider, $operation, $localOrderId);

            $environment = $this->normalizeEnvironment($attrs['environment'] ?? null);

            $row = [
                'provider' => mb_substr($provider, 0, 50),
                'environment' => $environment,
                'transactionType' => $transactionType,
                'operation' => mb_substr($operation, 0, 50),
                'deliveryMode' => $deliveryMode,
                'depositId' => $depositId,
                'withdrawalId' => $withdrawalId,
                'localOrderId' => mb_substr($localOrderId, 0, 100),
                'providerOrderId' => $this->nullableString($attrs['providerOrderId'] ?? null, 255),
                'providerRequestId' => $this->nullableString($attrs['providerRequestId'] ?? null, 255),
                'attemptNo' => $attemptNo,
                'idempotencyKey' => $this->nullableString($attrs['idempotencyKey'] ?? null, 255),
                'amount' => $attrs['amount'] ?? null,
                'currencyCode' => $this->nullableString($attrs['currencyCode'] ?? null, 20),
                'requestMethod' => $this->nullableString($attrs['requestMethod'] ?? null, 10),
                'endpointPath' => $this->sanitizeEndpointPath($attrs['endpointPath'] ?? null),
                'requestPayload' => $this->encodeJson($this->sanitizePayload($attrs['requestPayload'] ?? null)),
                'requestStatus' => self::STATUS_PREPARED,
                'requestId' => $attrs['requestId'] ?? ($requestCtx['requestId'] ?? null),
                'correlationId' => $attrs['correlationId'] ?? ($requestCtx['correlationId'] ?? null),
                'startedAt' => $startedAt,
                'createdAt' => $now,
                'updatedAt' => $now,
            ];

            $id = $this->logs()->create($row);
            return $id ? (int) $id : null;
        } catch (Throwable $e) {
            $this->safeLog('beginAttempt failed: ' . $e->getMessage(), $attrs);
            return null;
        }
    }

    /**
     * @param array<string,mixed> $patch
     */
    public function markSent($logId, array $patch = []) {
        $this->patchAttempt((int) $logId, array_merge($patch, [
            'requestStatus' => self::STATUS_SENT,
        ]), false);
    }

    /**
     * @param array<string,mixed> $patch
     */
    public function markRedirectIssued($logId, array $patch = []) {
        $this->completeAttempt((int) $logId, self::STATUS_REDIRECT_ISSUED, $patch);
    }

    /**
     * @param array<string,mixed> $patch
     */
    public function completeAttempt($logId, $status, array $patch = []) {
        $status = trim((string) $status);
        if (!in_array($status, self::TERMINAL_STATUSES, true) && $status !== self::STATUS_SENT) {
            $status = self::STATUS_UNKNOWN;
        }

        $isTerminal = in_array($status, self::TERMINAL_STATUSES, true);
        $this->patchAttempt((int) $logId, array_merge($patch, [
            'requestStatus' => $status,
        ]), $isTerminal);
    }

    /**
     * Map curl errno to timeout vs failed.
     */
    public function statusFromCurlErrno($errno) {
        $errno = (int) $errno;
        // CURLE_OPERATION_TIMEDOUT = 28
        if ($errno === 28) {
            return self::STATUS_TIMEOUT;
        }
        return self::STATUS_FAILED;
    }

    /**
     * Resolve sandbox|production from gateway config / explicit value.
     *
     * @param mixed $value
     * @param array|null $gateway
     */
    public function resolveEnvironment($value = null, $gateway = null) {
        if ($value !== null && $value !== '') {
            return $this->normalizeEnvironment($value);
        }

        if (is_array($gateway)) {
            $config = $gateway['configData'] ?? null;
            if (is_string($config) && trim($config) !== '') {
                $decoded = json_decode($config, true);
                if (is_array($decoded) && isset($decoded['environment'])) {
                    return $this->normalizeEnvironment($decoded['environment']);
                }
            }
            if (isset($gateway['environment'])) {
                return $this->normalizeEnvironment($gateway['environment']);
            }
        }

        return 'production';
    }

    /**
     * @param mixed $payload
     * @return mixed
     */
    public function sanitizePayload($payload) {
        if ($payload === null) {
            return null;
        }
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                return $this->sanitizePayload($decoded);
            }
            if (strlen($payload) > 8000) {
                return mb_substr($payload, 0, 8000) . '…[truncated]';
            }
            return $payload;
        }
        if (is_object($payload)) {
            return $this->sanitizePayload((array) $payload);
        }
        if (!is_array($payload)) {
            return $payload;
        }

        $out = [];
        foreach ($payload as $key => $item) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $out[$key] = '[REDACTED]';
                continue;
            }
            $out[$key] = $this->sanitizePayload($item);
        }
        return $out;
    }

    /**
     * Path-only URL; strip credentials and sensitive query params.
     *
     * @param mixed $urlOrPath
     * @return string|null
     */
    public function sanitizeEndpointPath($urlOrPath) {
        if ($urlOrPath === null) {
            return null;
        }
        $raw = trim((string) $urlOrPath);
        if ($raw === '') {
            return null;
        }

        $parts = parse_url($raw);
        if ($parts === false) {
            return mb_substr($raw, 0, 255);
        }

        $path = isset($parts['path']) ? (string) $parts['path'] : $raw;
        // Strip embedded merchant tokens that look like long hex/base64 segments in path.
        $path = preg_replace('#/[A-Za-z0-9_\-]{32,}(?=/|$)#', '/[REDACTED]', $path);

        if (!empty($parts['host'])) {
            $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
            $host = $parts['host'];
            $port = isset($parts['port']) ? ':' . $parts['port'] : '';
            $path = $scheme . $host . $port . $path;
        }

        return mb_substr($path, 0, 255);
    }

    /**
     * @param array<string,mixed> $patch
     * @param bool $markCompleted
     */
    private function patchAttempt($logId, array $patch, $markCompleted) {
        if ($logId <= 0) {
            return;
        }

        try {
            $existing = $this->logs()->findById($logId);
            if (!$existing) {
                return;
            }

            $now = gmdate('Y-m-d H:i:s');
            $update = ['updatedAt' => $now];

            if (isset($patch['requestStatus'])) {
                $update['requestStatus'] = (string) $patch['requestStatus'];
            }
            if (array_key_exists('requestMethod', $patch)) {
                $update['requestMethod'] = $this->nullableString($patch['requestMethod'], 10);
            }
            if (array_key_exists('endpointPath', $patch)) {
                $update['endpointPath'] = $this->sanitizeEndpointPath($patch['endpointPath']);
            }
            if (array_key_exists('requestPayload', $patch)) {
                $update['requestPayload'] = $this->encodeJson($this->sanitizePayload($patch['requestPayload']));
            }
            if (array_key_exists('responseHttpStatus', $patch)) {
                $status = $patch['responseHttpStatus'];
                $update['responseHttpStatus'] = $status === null ? null : (int) $status;
            }
            if (array_key_exists('providerStatus', $patch)) {
                $update['providerStatus'] = $this->nullableString($patch['providerStatus'], 64);
            }
            if (array_key_exists('responsePayload', $patch)) {
                $update['responsePayload'] = $this->encodeJson($this->sanitizePayload($patch['responsePayload']));
            }
            if (array_key_exists('providerOrderId', $patch)) {
                $update['providerOrderId'] = $this->nullableString($patch['providerOrderId'], 255);
            }
            if (array_key_exists('providerRequestId', $patch)) {
                $update['providerRequestId'] = $this->nullableString($patch['providerRequestId'], 255);
            }
            if (array_key_exists('errorCode', $patch)) {
                $update['errorCode'] = $this->nullableString($patch['errorCode'], 100);
            }
            if (array_key_exists('errorMessage', $patch)) {
                $msg = $patch['errorMessage'];
                $update['errorMessage'] = $msg === null || $msg === ''
                    ? null
                    : mb_substr((string) $msg, 0, 500);
            }
            if (array_key_exists('idempotencyKey', $patch)) {
                $update['idempotencyKey'] = $this->nullableString($patch['idempotencyKey'], 255);
            }

            if ($markCompleted) {
                $completedAt = isset($patch['completedAt'])
                    ? (string) $patch['completedAt']
                    : $now;
                $update['completedAt'] = $completedAt;

                if (array_key_exists('durationMs', $patch) && $patch['durationMs'] !== null) {
                    $update['durationMs'] = max(0, (int) $patch['durationMs']);
                } elseif (!empty($existing['startedAt'])) {
                    $start = strtotime((string) $existing['startedAt'] . ' UTC');
                    $end = strtotime($completedAt . ' UTC');
                    if ($start !== false && $end !== false && $end >= $start) {
                        $update['durationMs'] = (int) round(($end - $start) * 1000);
                    }
                }
            } elseif (array_key_exists('durationMs', $patch) && $patch['durationMs'] !== null) {
                $update['durationMs'] = max(0, (int) $patch['durationMs']);
            }

            $this->logs()->update($logId, $update);
        } catch (Throwable $e) {
            $this->safeLog('patchAttempt failed: ' . $e->getMessage(), [
                'logId' => $logId,
                'patch' => $patch,
            ]);
        }
    }

    /**
     * Map a callback to the best matching outbound request attempt.
     * Priority: provider_order_id → local_order_id → single deposit/withdrawal attempt.
     * Never guesses when multiple attempts remain.
     *
     * @return array{requestLogId:?int,correlationMethod:string}
     */
    public function findAttemptForCallback(
        $provider,
        $transactionType,
        $depositId = null,
        $withdrawalId = null,
        $callbackOrderId = null,
        $providerOrderId = null
    ) {
        $unmatched = [
            'requestLogId' => null,
            'correlationMethod' => 'unmatched',
        ];

        try {
            $provider = strtolower(trim((string) $provider));
            $transactionType = trim((string) $transactionType);
            if ($provider === '' || ($transactionType !== 'deposit' && $transactionType !== 'withdrawal')) {
                return $unmatched;
            }

            $depositId = $depositId !== null ? (int) $depositId : null;
            $withdrawalId = $withdrawalId !== null ? (int) $withdrawalId : null;
            if ($depositId !== null && $depositId <= 0) {
                $depositId = null;
            }
            if ($withdrawalId !== null && $withdrawalId <= 0) {
                $withdrawalId = null;
            }

            $callbackOrderId = trim((string) ($callbackOrderId ?? ''));
            $providerOrderId = trim((string) ($providerOrderId ?? ''));

            // 1) Provider order ID
            if ($providerOrderId !== '') {
                $row = $this->logs()->queryOne(
                    "SELECT id FROM {$this->logs()->getTableName()}
                     WHERE provider = :provider
                       AND transactionType = :transactionType
                       AND providerOrderId = :providerOrderId
                     ORDER BY id DESC
                     LIMIT 1",
                    [
                        'provider' => $provider,
                        'transactionType' => $transactionType,
                        'providerOrderId' => $providerOrderId,
                    ]
                );
                if ($row && !empty($row['id'])) {
                    return [
                        'requestLogId' => (int) $row['id'],
                        'correlationMethod' => 'provider_order_id',
                    ];
                }
            }

            // 2) Local order ID (exact + dash-stripped variants for Vexora/Coinsbuy)
            $localCandidates = $this->localOrderCandidates($callbackOrderId);
            foreach ($localCandidates as $localOrderId) {
                $row = $this->logs()->queryOne(
                    "SELECT id FROM {$this->logs()->getTableName()}
                     WHERE provider = :provider
                       AND transactionType = :transactionType
                       AND localOrderId = :localOrderId
                     ORDER BY id DESC
                     LIMIT 1",
                    [
                        'provider' => $provider,
                        'transactionType' => $transactionType,
                        'localOrderId' => $localOrderId,
                    ]
                );
                if ($row && !empty($row['id'])) {
                    return [
                        'requestLogId' => (int) $row['id'],
                        'correlationMethod' => 'local_order_id',
                    ];
                }

                // Also match when request stored dash-stripped local id but callback has dashed form (or reverse)
                $row = $this->logs()->queryOne(
                    "SELECT id FROM {$this->logs()->getTableName()}
                     WHERE provider = :provider
                       AND transactionType = :transactionType
                       AND REPLACE(localOrderId, '-', '') = :normalized
                     ORDER BY id DESC
                     LIMIT 1",
                    [
                        'provider' => $provider,
                        'transactionType' => $transactionType,
                        'normalized' => str_replace('-', '', $localOrderId),
                    ]
                );
                if ($row && !empty($row['id'])) {
                    return [
                        'requestLogId' => (int) $row['id'],
                        'correlationMethod' => 'local_order_id',
                    ];
                }
            }

            // 3) Unique attempt for deposit/withdrawal id
            if ($transactionType === 'deposit' && $depositId !== null) {
                $rows = $this->logs()->query(
                    "SELECT id FROM {$this->logs()->getTableName()}
                     WHERE provider = :provider
                       AND transactionType = 'deposit'
                       AND depositId = :depositId
                     ORDER BY id DESC
                     LIMIT 2",
                    [
                        'provider' => $provider,
                        'depositId' => $depositId,
                    ]
                );
                if (is_array($rows) && count($rows) === 1 && !empty($rows[0]['id'])) {
                    return [
                        'requestLogId' => (int) $rows[0]['id'],
                        'correlationMethod' => 'deposit_id',
                    ];
                }
            }

            if ($transactionType === 'withdrawal' && $withdrawalId !== null) {
                $rows = $this->logs()->query(
                    "SELECT id FROM {$this->logs()->getTableName()}
                     WHERE provider = :provider
                       AND transactionType = 'withdrawal'
                       AND withdrawalId = :withdrawalId
                     ORDER BY id DESC
                     LIMIT 2",
                    [
                        'provider' => $provider,
                        'withdrawalId' => $withdrawalId,
                    ]
                );
                if (is_array($rows) && count($rows) === 1 && !empty($rows[0]['id'])) {
                    return [
                        'requestLogId' => (int) $rows[0]['id'],
                        'correlationMethod' => 'withdrawal_id',
                    ];
                }
            }

            return $unmatched;
        } catch (Throwable $e) {
            $this->safeLog('findAttemptForCallback failed: ' . $e->getMessage(), [
                'depositId' => $depositId ?? null,
                'withdrawalId' => $withdrawalId ?? null,
                'localOrderId' => $callbackOrderId ?? null,
            ]);
            return $unmatched;
        }
    }

    /**
     * @return string[]
     */
    private function localOrderCandidates($callbackOrderId) {
        $callbackOrderId = trim((string) $callbackOrderId);
        if ($callbackOrderId === '') {
            return [];
        }

        $out = [$callbackOrderId];
        $stripped = str_replace('-', '', $callbackOrderId);
        if ($stripped !== '' && $stripped !== $callbackOrderId) {
            $out[] = $stripped;
        }
        // Vexora tradeNo is max 32 chars of dash-stripped id
        if (strlen($stripped) > 32) {
            $out[] = substr($stripped, 0, 32);
        }

        return array_values(array_unique($out));
    }

    private function logs() {
        if ($this->model === null) {
            $this->model = new PaymentProcessorRequestLog();
        }
        return $this->model;
    }

    private function normalizeEnvironment($value) {
        $env = strtolower(trim((string) $value));
        if ($env === 'sandbox' || $env === 'test' || $env === 'testing' || $env === 'dev' || $env === 'development') {
            return 'sandbox';
        }
        return 'production';
    }

    private function isSensitiveKey($key) {
        $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $key));
        if (in_array($normalized, self::$exactSensitiveKeys, true)) {
            return true;
        }
        foreach (self::$sensitiveKeyFragments as $fragment) {
            if ($normalized === $fragment || strpos($normalized, $fragment) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private function encodeJson($value) {
        if ($value === null) {
            return null;
        }
        $json = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
        return $json === false ? null : $json;
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private function nullableString($value, $maxLen) {
        if ($value === null) {
            return null;
        }
        $str = trim((string) $value);
        if ($str === '') {
            return null;
        }
        return mb_substr($str, 0, (int) $maxLen);
    }

    /**
     * @param array<string,mixed>|null $context
     */
    private function safeLog($message, $context = null) {
        try {
            Logger::error('PaymentProcessorRequestLogService: ' . $message, is_array($context) ? [
                'domain' => 'payment',
                'depositId' => $context['depositId'] ?? null,
                'withdrawalId' => $context['withdrawalId'] ?? null,
                'localOrderId' => $context['localOrderId'] ?? null,
                'logId' => $context['logId'] ?? null,
            ] : ['domain' => 'payment']);
        } catch (Throwable $e) {
            @error_log('PaymentProcessorRequestLogService: ' . $message);
        }
    }
}
