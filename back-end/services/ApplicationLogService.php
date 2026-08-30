<?php
/**
 * Persist application logs to applicationLogs.
 * Never throws. Database writes are fail-open.
 */

require_once __DIR__ . '/../models/ApplicationLog.php';
require_once __DIR__ . '/../utils/RequestLogContext.php';

class ApplicationLogService {
    private static $inWrite = false;

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
    ];

    /** @var ApplicationLog|null */
    private $model;

    /**
     * @param string               $level   ERROR|WARNING|INFO
     * @param string               $message
     * @param array|null           $context
     * @param array<string,mixed>  $meta    Optional overrides: exceptionClass, stackTrace, source, service, ...
     * @param bool                 $persistToDatabase
     * @return int|null Inserted log id, or null on failure / skip
     */
    public function write($level, $message, $context = null, array $meta = [], $persistToDatabase = true) {
        $level = strtoupper(trim((string) $level));
        if (!in_array($level, ['ERROR', 'WARNING', 'INFO'], true)) {
            $level = 'ERROR';
        }

        $message = (string) $message;
        if ($message === '') {
            $message = '(empty message)';
        }

        if (!$persistToDatabase) {
            return null;
        }

        if (self::$inWrite) {
            @error_log('ApplicationLogService recursive write blocked: ' . $message);
            return null;
        }

        self::$inWrite = true;
        try {
            $ctx = is_array($context) ? $context : ($context === null ? null : ['value' => $context]);
            $sanitized = $ctx === null ? null : $this->sanitizeContext($ctx);
            $encodedContext = $this->encodeContext($sanitized);

            $requestCtx = RequestLogContext::get();
            $exceptionClass = isset($meta['exceptionClass']) ? (string) $meta['exceptionClass'] : null;
            $stackTrace = isset($meta['stackTrace']) ? (string) $meta['stackTrace'] : null;
            $source = isset($meta['source']) ? (string) $meta['source'] : $this->resolveSource($sanitized);

            if ($exceptionClass === '' || $exceptionClass === null) {
                $exceptionClass = $this->extractExceptionClass($sanitized);
            }
            if (($stackTrace === '' || $stackTrace === null) && is_array($sanitized)) {
                $stackTrace = isset($sanitized['stackTrace']) ? (string) $sanitized['stackTrace'] : null;
            }

            $userId = $meta['userId'] ?? ($requestCtx['userId'] ?? null);
            if ($userId !== null) {
                $userId = (int) $userId;
                if ($userId <= 0) {
                    $userId = null;
                }
            }

            $userType = $meta['userType'] ?? ($requestCtx['userType'] ?? 'anonymous');
            $userType = trim((string) $userType);
            if ($userType === '') {
                $userType = 'anonymous';
            }

            $requestMethod = $meta['requestMethod'] ?? ($requestCtx['requestMethod'] ?? ($_SERVER['REQUEST_METHOD'] ?? null));
            $requestPath = $meta['requestPath'] ?? ($requestCtx['requestPath'] ?? null);
            $route = $meta['route'] ?? ($requestCtx['route'] ?? null);
            $errorCode = $meta['errorCode'] ?? null;
            $failureType = $meta['failureType'] ?? null;
            $actualHttpStatus = array_key_exists('actualHttpStatus', $meta) && $meta['actualHttpStatus'] !== null
                ? (int) $meta['actualHttpStatus']
                : null;
            $applicationStatusCode = array_key_exists('applicationStatusCode', $meta) && $meta['applicationStatusCode'] !== null
                ? (int) $meta['applicationStatusCode']
                : null;
            $durationMs = array_key_exists('durationMs', $meta) && $meta['durationMs'] !== null
                ? max(0, (int) $meta['durationMs'])
                : null;

            foreach ([
                'requestMethod' => 10,
                'requestPath' => 512,
                'route' => 255,
                'errorCode' => 128,
                'failureType' => 32,
            ] as $field => $maxLen) {
                $value = $$field;
                $$field = $value !== null && $value !== ''
                    ? mb_substr((string) $value, 0, $maxLen)
                    : null;
            }

            $row = [
                'level' => $level,
                'service' => (string) ($meta['service'] ?? $requestCtx['service'] ?? 'api'),
                'environment' => (string) ($meta['environment'] ?? $requestCtx['environment'] ?? 'development'),
                'message' => mb_substr($message, 0, 65000),
                'context' => $encodedContext,
                'exceptionClass' => $exceptionClass !== null && $exceptionClass !== '' ? mb_substr($exceptionClass, 0, 255) : null,
                'stackTrace' => $stackTrace !== null && $stackTrace !== '' ? mb_substr($stackTrace, 0, 65000) : null,
                'requestId' => $meta['requestId'] ?? ($requestCtx['requestId'] ?? null),
                'correlationId' => $meta['correlationId'] ?? ($requestCtx['correlationId'] ?? null),
                'userId' => $userId,
                'userType' => $userType,
                'jobId' => $meta['jobId'] ?? ($requestCtx['jobId'] ?? null),
                'source' => $source !== null && $source !== '' ? mb_substr($source, 0, 128) : null,
                'requestMethod' => $requestMethod,
                'requestPath' => $requestPath,
                'route' => $route,
                'actualHttpStatus' => $actualHttpStatus,
                'applicationStatusCode' => $applicationStatusCode,
                'errorCode' => $errorCode,
                'failureType' => $failureType,
                'durationMs' => $durationMs,
                'createdAt' => gmdate('Y-m-d H:i:s'),
            ];

            if ($this->model === null) {
                $this->model = new ApplicationLog();
            }
            $id = $this->model->create($row);
            return $id ? (int) $id : null;
        } catch (Throwable $e) {
            @error_log('ApplicationLogService::write failed: ' . $e->getMessage());
            return null;
        } finally {
            self::$inWrite = false;
        }
    }

    /**
     * Recursively redact sensitive keys.
     *
     * @param mixed $value
     * @return mixed
     */
    public function sanitizeContext($value) {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && $this->isSensitiveKey($key)) {
                    $out[$key] = '[REDACTED]';
                    continue;
                }
                $out[$key] = $this->sanitizeContext($item);
            }
            return $out;
        }

        if (is_object($value)) {
            if ($value instanceof Throwable) {
                return [
                    'exceptionClass' => get_class($value),
                    'message' => $value->getMessage(),
                    'code' => $value->getCode(),
                ];
            }
            return $this->sanitizeContext((array) $value);
        }

        if (is_string($value) && strlen($value) > 8000) {
            return mb_substr($value, 0, 8000) . '…[truncated]';
        }

        return $value;
    }

    /**
     * @param array|null $context
     * @return string|null
     */
    public function encodeContext($context) {
        if ($context === null) {
            return null;
        }
        $json = json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
        return $json === false ? null : $json;
    }

    private function isSensitiveKey($key) {
        $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $key));
        foreach (self::$sensitiveKeyFragments as $fragment) {
            if ($normalized === $fragment || strpos($normalized, $fragment) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array|null $context
     */
    private function resolveSource($context) {
        if (is_array($context)) {
            foreach (['source', 'controller', 'service', 'file'] as $key) {
                if (!empty($context[$key]) && is_scalar($context[$key])) {
                    return (string) $context[$key];
                }
            }
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12);
        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '';
            if ($file === '' || strpos($file, DIRECTORY_SEPARATOR . 'ApplicationLogService.php') !== false) {
                continue;
            }
            if (strpos($file, DIRECTORY_SEPARATOR . 'Logger.php') !== false) {
                continue;
            }
            $base = basename($file);
            $line = isset($frame['line']) ? (':' . $frame['line']) : '';
            return $base . $line;
        }
        return null;
    }

    /**
     * @param array|null $context
     */
    private function extractExceptionClass($context) {
        if (!is_array($context)) {
            return null;
        }
        if (!empty($context['exceptionClass']) && is_string($context['exceptionClass'])) {
            return $context['exceptionClass'];
        }
        if (!empty($context['exception']) && is_string($context['exception'])) {
            return null;
        }
        return null;
    }
}
