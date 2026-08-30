<?php
/**
 * Application logger — persist to applicationLogs.
 *
 * Usage:
 *   Logger::error('Something failed', ['input' => $input]);
 *   Logger::warning('Suspicious amount', ['amount' => $amount]);
 *   Logger::info('KYC approved', ['submissionId' => $id], true); // critical INFO → DB
 *   Logger::info('KYC approved', ['domain' => 'kyc', 'submissionId' => $id]); // domain also → DB
 */

require_once __DIR__ . '/RequestLogContext.php';

class Logger {
    /** @var ApplicationLogService|null */
    private static $logService = null;

    /**
     * @param string     $message
     * @param array|null $context
     */
    public static function error($message, $context = null) {
        self::write('ERROR', $message, $context, true);
    }

    /**
     * @param string     $message
     * @param array|null $context
     */
    public static function warning($message, $context = null) {
        self::write('WARNING', $message, $context, true);
    }

    /**
     * @param string     $message
     * @param array|null $context
     * @param bool       $toDatabase Force DB write for INFO (KYC/payment critical actions)
     */
    public static function info($message, $context = null, $toDatabase = false) {
        $persistInfo = (bool) $toDatabase;
        if (!$persistInfo && is_array($context)) {
            $domain = $context['domain'] ?? null;
            if (RequestLogContext::isCriticalInfoDomain($domain)) {
                $persistInfo = true;
            }
        }
        self::write('INFO', $message, $context, $persistInfo);
    }

    /**
     * @param string     $level
     * @param string     $message
     * @param array|null $context
     * @param bool       $toDatabase
     */
    private static function write($level, $message, $context = null, $toDatabase = false) {
        try {
            $service = self::getLogService();
            if ($service === null) {
                return;
            }
            $service->write(
                $level,
                $message,
                is_array($context) ? $context : ($context === null ? null : ['value' => $context]),
                [],
                $toDatabase
            );
        } catch (Throwable $e) {
            // Never break the caller.
            @error_log('Logger write failed: ' . $e->getMessage());
        }
    }

    /**
     * @return ApplicationLogService|null
     */
    private static function getLogService() {
        if (self::$logService !== null) {
            return self::$logService;
        }
        try {
            require_once __DIR__ . '/../services/ApplicationLogService.php';
            self::$logService = new ApplicationLogService();
            return self::$logService;
        } catch (Throwable $e) {
            return null;
        }
    }
}
