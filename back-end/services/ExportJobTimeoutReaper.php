<?php
/**
 * Shared stale-job timeout for async report CSV exports.
 * Reusable by commission, deposit-withdraw, and future report export services.
 */

class ExportJobTimeoutReaper
{
    public const TIMEOUT_SECONDS = 900;
    public const TIMEOUT_MESSAGE = 'Export timed out';

    /** @var list<string> */
    public const NON_TERMINAL_STATUSES = ['queued', 'running', 'cancelling'];

    public static function isStale(array $progress, ?int $timeoutSeconds = null): bool
    {
        $status = (string)($progress['status'] ?? '');
        if (!in_array($status, self::NON_TERMINAL_STATUSES, true)) {
            return false;
        }

        $timeout = $timeoutSeconds !== null ? (int)$timeoutSeconds : self::TIMEOUT_SECONDS;
        if ($timeout <= 0) {
            return false;
        }

        $updatedAt = (string)($progress['updatedAt'] ?? '');
        if ($updatedAt === '') {
            return true;
        }

        $ts = strtotime($updatedAt);
        if ($ts === false) {
            return true;
        }

        return (time() - $ts) > $timeout;
    }

    /**
     * If the job is non-terminal and stale, mark it error, clear active, return updated progress.
     * Otherwise return $progress unchanged.
     *
     * @param callable(string $jobId, array $data): void $writeProgress
     * @param callable(int $clientUserId): void $clearActive
     */
    public static function reapIfStale(
        string $jobId,
        ?array $progress,
        callable $writeProgress,
        callable $clearActive,
        ?int $timeoutSeconds = null
    ): ?array {
        if ($progress === null || $jobId === '') {
            return $progress;
        }

        if (!self::isStale($progress, $timeoutSeconds)) {
            return $progress;
        }

        $ownerUserId = (int)($progress['adminUserId'] ?? 0);
        if ($ownerUserId <= 0) {
            $ownerUserId = (int)($progress['clientUserId'] ?? 0);
        }
        $reaped = array_merge($progress, [
            'status' => 'error',
            'cancelRequested' => false,
            'percent' => (int)($progress['percent'] ?? 0),
            'message' => self::TIMEOUT_MESSAGE,
            'downloadReady' => false,
            'file' => null,
        ]);

        $writeProgress($jobId, $reaped);
        if ($ownerUserId > 0) {
            $clearActive($ownerUserId);
        }

        return $reaped;
    }
}
