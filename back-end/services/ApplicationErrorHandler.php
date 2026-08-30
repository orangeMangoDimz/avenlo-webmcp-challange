<?php
/**
 * Centralized recorder for logical 5xx API failures → applicationLogs.
 * Fail-open. Dedupes one centralized incident per request via RequestLogContext.
 */

require_once __DIR__ . '/ApplicationLogService.php';
require_once __DIR__ . '/../utils/RequestLogContext.php';
require_once __DIR__ . '/../utils/Response.php';

class ApplicationErrorHandler {
    public const FAILURE_RESPONSE_ERROR = 'response_error';
    public const FAILURE_EXCEPTION = 'exception';
    public const FAILURE_FATAL = 'fatal_error';

    private const CLIENT_MESSAGE = 'Internal server error';

    /** @var ApplicationLogService|null */
    private static $logService = null;

    /**
     * Record a logical 5xx from Response::error() and mark request handled.
     *
     * @param mixed $errors
     * @param string|null $errorCode
     */
    public static function recordResponseError($message, $statusCode, $errors = null, $errorCode = null) {
        $statusCode = (int) $statusCode;
        if ($statusCode < 500) {
            return;
        }

        self::record5xx([
            'message' => (string) $message,
            'applicationStatusCode' => $statusCode,
            'failureType' => self::FAILURE_RESPONSE_ERROR,
            'errorCode' => $errorCode,
            'context' => $errors !== null ? ['errors' => $errors] : null,
            'source' => 'Response::error',
        ]);
    }

    /**
     * Record exception, then emit generic 500 client response.
     *
     * @param Throwable $e
     */
    public static function handleException($e) {
        self::recordException($e);
        Response::serverError(self::CLIENT_MESSAGE);
    }

    /**
     * Persist exception details without emitting a response.
     *
     * @param Throwable $e
     * @param array<string,mixed> $extra
     */
    public static function recordException($e, array $extra = []) {
        if (!$e instanceof Throwable) {
            return;
        }

        self::record5xx(array_merge([
            'message' => self::CLIENT_MESSAGE . ': ' . $e->getMessage(),
            'applicationStatusCode' => 500,
            'failureType' => self::FAILURE_EXCEPTION,
            'exceptionClass' => get_class($e),
            'stackTrace' => $e->getTraceAsString(),
            'context' => [
                'exceptionMessage' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
            ],
            'source' => basename($e->getFile()) . ':' . $e->getLine(),
        ], $extra));
    }

    /**
     * Persist fatal error from shutdown handler.
     *
     * @param array<string,mixed> $error error_get_last() shape
     */
    public static function recordFatal(array $error) {
        $type = (int) ($error['type'] ?? 0);
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($type, $fatalTypes, true)) {
            return;
        }

        self::record5xx([
            'message' => self::CLIENT_MESSAGE . ': ' . (string) ($error['message'] ?? 'fatal error'),
            'applicationStatusCode' => 500,
            'failureType' => self::FAILURE_FATAL,
            'exceptionClass' => 'FatalError',
            'stackTrace' => null,
            'context' => [
                'type' => $type,
                'file' => $error['file'] ?? null,
                'line' => $error['line'] ?? null,
                'message' => $error['message'] ?? null,
            ],
            'source' => isset($error['file'])
                ? (basename((string) $error['file']) . ':' . (int) ($error['line'] ?? 0))
                : 'shutdown',
        ]);
    }

    /**
     * Top-level uncaught exception handler (registers response).
     *
     * @param Throwable $e
     */
    public static function uncaughtExceptionHandler($e) {
        try {
            self::recordException($e);
        } catch (Throwable $ignored) {
            // ignore
        }

        if (!headers_sent()) {
            try {
                Response::serverError(self::CLIENT_MESSAGE);
            } catch (Throwable $ignored) {
                http_response_code(200);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'message' => self::CLIENT_MESSAGE,
                    'statusCode' => 500,
                    'timestamp' => time(),
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    /**
     * Shutdown fatal capture.
     */
    public static function shutdownHandler() {
        $error = error_get_last();
        if (!is_array($error)) {
            return;
        }

        $type = (int) ($error['type'] ?? 0);
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($type, $fatalTypes, true)) {
            return;
        }

        if (RequestLogContext::isServerErrorLogged()) {
            return;
        }

        try {
            self::recordFatal($error);
        } catch (Throwable $ignored) {
            // ignore
        }

        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => self::CLIENT_MESSAGE,
                'statusCode' => 500,
                'timestamp' => time(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * @param array<string,mixed> $attrs
     */
    private static function record5xx(array $attrs) {
        try {
            if (RequestLogContext::isServerErrorLogged()) {
                return;
            }

            // Mark early to prevent recursive / duplicate centralized rows.
            RequestLogContext::markServerErrorLogged(null);

            $requestCtx = RequestLogContext::get();
            $durationMs = RequestLogContext::elapsedMs();

            $message = trim((string) ($attrs['message'] ?? self::CLIENT_MESSAGE));
            if ($message === '') {
                $message = self::CLIENT_MESSAGE;
            }

            $meta = [
                'requestMethod' => $requestCtx['requestMethod'] ?? ($_SERVER['REQUEST_METHOD'] ?? null),
                'requestPath' => $requestCtx['requestPath'] ?? null,
                'route' => $requestCtx['route'] ?? null,
                'actualHttpStatus' => 200,
                'applicationStatusCode' => (int) ($attrs['applicationStatusCode'] ?? 500),
                'errorCode' => isset($attrs['errorCode']) && $attrs['errorCode'] !== ''
                    ? (string) $attrs['errorCode']
                    : null,
                'failureType' => (string) ($attrs['failureType'] ?? self::FAILURE_RESPONSE_ERROR),
                'durationMs' => $durationMs,
                'exceptionClass' => $attrs['exceptionClass'] ?? null,
                'stackTrace' => $attrs['stackTrace'] ?? null,
                'source' => $attrs['source'] ?? null,
                'service' => $requestCtx['service'] ?? 'api',
            ];

            $context = $attrs['context'] ?? null;
            if (!is_array($context)) {
                $context = $context === null ? [] : ['value' => $context];
            }
            $context['failureType'] = $meta['failureType'];
            $context['applicationStatusCode'] = $meta['applicationStatusCode'];

            $logId = self::service()->write('ERROR', $message, $context, $meta);
            RequestLogContext::markServerErrorLogged($logId);
        } catch (Throwable $e) {
            @error_log('ApplicationErrorHandler::record5xx failed: ' . $e->getMessage());
            RequestLogContext::markServerErrorLogged(null);
        }
    }

    /**
     * @return ApplicationLogService
     */
    private static function service() {
        if (self::$logService === null) {
            self::$logService = new ApplicationLogService();
        }
        return self::$logService;
    }
}
