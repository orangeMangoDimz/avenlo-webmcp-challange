<?php
/**
 * Per-request / per-task application log context.
 * Holds requestId, correlationId, user identity for Logger.
 */

class RequestLogContext {
    private const CRITICAL_INFO_DOMAINS = ['kyc', 'payment'];

    /**
     * Initialize context for an HTTP API request. Returns the requestId.
     */
    public static function initForHttpRequest() {
        $requestId = self::generateId();
        $environment = 'development';
        try {
            $config = require __DIR__ . '/../config/app.php';
            $environment = (string) ($config['env'] ?? 'development');
        } catch (Throwable $e) {
            // Keep default environment.
        }

        $method = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper(trim((string) $_SERVER['REQUEST_METHOD']))
            : null;

        $GLOBALS['appLogContext'] = [
            'requestId' => $requestId,
            'correlationId' => $requestId,
            'userId' => null,
            'userType' => 'anonymous',
            'service' => 'api',
            'environment' => $environment,
            'jobId' => null,
            'requestMethod' => $method !== '' ? $method : null,
            'requestPath' => null,
            'route' => null,
            'startedAtMs' => (int) round(microtime(true) * 1000),
            'serverErrorLogged' => false,
            'serverErrorLogId' => null,
        ];

        return $requestId;
    }

    /**
     * Set sanitized request path after routing parse.
     */
    public static function setRequestPath($path) {
        self::ensureInitialized();
        $sanitized = self::sanitizePath($path);
        $GLOBALS['appLogContext']['requestPath'] = $sanitized;
    }

    /**
     * Set matched route prefix / file key.
     */
    public static function setRoute($route) {
        self::ensureInitialized();
        $route = trim((string) $route);
        $GLOBALS['appLogContext']['route'] = $route !== '' ? mb_substr($route, 0, 255) : null;
    }

    /**
     * Restore / set context from a Swoole task payload.
     */
    public static function initFromTaskPayload(array $data) {
        $requestId = trim((string) ($data['requestId'] ?? ''));
        if ($requestId === '') {
            $requestId = self::generateId();
        }

        $correlationId = trim((string) ($data['correlationId'] ?? ''));
        if ($correlationId === '') {
            $correlationId = $requestId;
        }

        $jobId = isset($data['jobId']) ? trim((string) $data['jobId']) : null;
        if ($jobId === '') {
            $jobId = null;
        }

        $userId = isset($data['userId']) ? (int) $data['userId'] : null;
        if ($userId !== null && $userId <= 0) {
            $userId = null;
        }

        $userType = trim((string) ($data['userType'] ?? ''));
        if ($userType === '') {
            $userType = $userId !== null ? 'system' : 'system';
        }

        $environment = 'development';
        try {
            $config = require __DIR__ . '/../config/app.php';
            $environment = (string) ($config['env'] ?? 'development');
        } catch (Throwable $e) {
            // Keep default.
        }

        $GLOBALS['appLogContext'] = [
            'requestId' => $requestId,
            'correlationId' => $correlationId,
            'userId' => $userId,
            'userType' => $userType,
            'service' => 'swoole',
            'environment' => $environment,
            'jobId' => $jobId,
            'requestMethod' => null,
            'requestPath' => null,
            'route' => null,
            'startedAtMs' => (int) round(microtime(true) * 1000),
            'serverErrorLogged' => false,
            'serverErrorLogId' => null,
        ];
    }

    /**
     * Attach authenticated user after JWT validation.
     */
    public static function attachUser($userId, $userType) {
        if (!isset($GLOBALS['appLogContext']) || !is_array($GLOBALS['appLogContext'])) {
            self::initForHttpRequest();
        }

        $id = $userId !== null ? (int) $userId : null;
        if ($id !== null && $id <= 0) {
            $id = null;
        }

        $type = trim((string) $userType);
        if ($type === '') {
            $type = $id !== null ? 'anonymous' : 'anonymous';
        }

        $GLOBALS['appLogContext']['userId'] = $id;
        $GLOBALS['appLogContext']['userType'] = $id !== null ? $type : 'anonymous';
    }

    public static function isServerErrorLogged() {
        $ctx = self::get();
        return !empty($ctx['serverErrorLogged']);
    }

    /**
     * @param int|null $logId
     */
    public static function markServerErrorLogged($logId = null) {
        self::ensureInitialized();
        $GLOBALS['appLogContext']['serverErrorLogged'] = true;
        if ($logId !== null) {
            $GLOBALS['appLogContext']['serverErrorLogId'] = (int) $logId;
        }
    }

    /**
     * Elapsed ms since request start, or null.
     *
     * @return int|null
     */
    public static function elapsedMs() {
        $ctx = self::get();
        if (!isset($ctx['startedAtMs'])) {
            return null;
        }
        $start = (int) $ctx['startedAtMs'];
        if ($start <= 0) {
            return null;
        }
        return max(0, (int) round(microtime(true) * 1000) - $start);
    }

    /**
     * Fields to merge into Swoole TCP task payloads.
     *
     * @return array{requestId:?string,correlationId:?string,userId:?int,userType:?string,jobId:?string}
     */
    public static function toTaskFields() {
        $ctx = self::get();
        return [
            'requestId' => $ctx['requestId'] ?? null,
            'correlationId' => $ctx['correlationId'] ?? null,
            'userId' => $ctx['userId'] ?? null,
            'userType' => $ctx['userType'] ?? null,
            'jobId' => $ctx['jobId'] ?? null,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function get() {
        if (!isset($GLOBALS['appLogContext']) || !is_array($GLOBALS['appLogContext'])) {
            return [];
        }
        return $GLOBALS['appLogContext'];
    }

    public static function isCriticalInfoDomain($domain) {
        $domain = strtolower(trim((string) $domain));
        return in_array($domain, self::CRITICAL_INFO_DOMAINS, true);
    }

    public static function generateId() {
        return strtoupper(bin2hex(random_bytes(16)));
    }

    private static function ensureInitialized() {
        if (!isset($GLOBALS['appLogContext']) || !is_array($GLOBALS['appLogContext'])) {
            self::initForHttpRequest();
        }
    }

    /**
     * Path without sensitive query values.
     *
     * @param mixed $path
     * @return string|null
     */
    private static function sanitizePath($path) {
        $raw = trim((string) $path);
        if ($raw === '') {
            return null;
        }

        // Drop query string entirely for persistence safety.
        $qPos = strpos($raw, '?');
        if ($qPos !== false) {
            $raw = substr($raw, 0, $qPos);
        }

        $raw = '/' . ltrim($raw, '/');
        return mb_substr($raw, 0, 512);
    }
}
