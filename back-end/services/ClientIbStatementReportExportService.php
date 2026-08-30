<?php

require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/ExportJobTimeoutReaper.php';
require_once __DIR__ . '/IbStatementReportExportService.php';
require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/../controllers/IbStatementReportController.php';

class ClientIbStatementReportExportService
{
    private static function exportDir(): string
    {
        return __DIR__ . '/../storage/exports';
    }

    public static function ensureExportDir(): void
    {
        $dir = self::exportDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    public static function progressPath(string $jobId): string
    {
        return self::exportDir() . '/progress_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $jobId) . '.json';
    }

    public static function activePath(int $clientUserId): string
    {
        return self::exportDir() . '/active_client_isr_' . (int) $clientUserId . '.json';
    }

    public static function csvPath(string $jobId): string
    {
        return self::exportDir() . '/' . preg_replace('/[^a-zA-Z0-9._-]/', '', $jobId) . '.csv';
    }

    public static function readProgress(string $jobId): ?array
    {
        $file = self::progressPath($jobId);
        if (!file_exists($file)) {
            return null;
        }
        $data = @json_decode((string) @file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    public static function writeProgress(string $jobId, array $data): void
    {
        self::ensureExportDir();
        $path = self::progressPath($jobId);
        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            return;
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                return;
            }

            rewind($fp);
            $raw = stream_get_contents($fp);
            $existing = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            if (!is_array($existing)) {
                $existing = [];
            }

            require_once __DIR__ . '/ExportProgressGuard.php';
            $data = ExportProgressGuard::applyCancelIntent($existing, $data, self::csvPath($jobId));

            $data['jobId'] = $jobId;
            $data['updatedAt'] = date('Y-m-d H:i:s');
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                return;
            }

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $json);
            fflush($fp);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        try {
            require_once __DIR__ . '/BackgroundJobService.php';
            (new BackgroundJobService())->syncExportProgress($jobId, $data);
        } catch (Throwable $e) {
        }
    }

    public static function readActive(int $clientUserId): ?array
    {
        $file = self::activePath($clientUserId);
        if (!file_exists($file)) {
            return null;
        }
        $data = @json_decode((string) @file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    public static function writeActive(int $clientUserId, string $jobId): void
    {
        self::ensureExportDir();
        @file_put_contents(
            self::activePath($clientUserId),
            json_encode([
                'jobId' => $jobId,
                'updatedAt' => date('Y-m-d H:i:s'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    public static function clearActive(int $clientUserId): void
    {
        $file = self::activePath($clientUserId);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    public static function requestCancel(string $jobId): bool
    {
        $progress = self::readProgress($jobId);
        if ($progress === null) {
            return false;
        }
        $status = (string) ($progress['status'] ?? '');
        if (!in_array($status, ['queued', 'running', 'cancelling', 'done'], true)) {
            return false;
        }

        $clientUserId = (int) ($progress['clientUserId'] ?? 0);
        $percent = max(0, min(100, (int) ($progress['percent'] ?? 0)));
        $processed = max(0, (int) ($progress['processed'] ?? 0));
        $total = max(0, (int) ($progress['total'] ?? 0));

        if ($status === 'queued' || $status === 'done') {
            $csvFile = self::csvPath($jobId);
            if (file_exists($csvFile)) {
                @unlink($csvFile);
            }
            self::writeProgress($jobId, [
                'clientUserId' => $clientUserId,
                'status' => 'cancelled',
                'cancelRequested' => true,
                'percent' => $percent,
                'processed' => $processed,
                'total' => $total,
                'message' => 'Export cancelled',
                'file' => null,
                'downloadReady' => false,
                'fileName' => $progress['fileName'] ?? null,
            ]);
            if ($clientUserId > 0) {
                self::clearActive($clientUserId);
            }
            return true;
        }

        $progress['cancelRequested'] = true;
        $progress['status'] = 'cancelling';
        $progress['message'] = 'Cancelling export...';
        self::writeProgress($jobId, $progress);
        return true;
    }

    public static function getActiveForUser(int $clientUserId): ?array
    {
        $active = self::readActive($clientUserId);
        if ($active === null || empty($active['jobId'])) {
            return null;
        }
        $jobId = (string) $active['jobId'];
        $progress = self::readProgress($jobId);
        if ($progress === null) {
            self::clearActive($clientUserId);
            return null;
        }
        $progress = ExportJobTimeoutReaper::reapIfStale(
            $jobId,
            $progress,
            [self::class, 'writeProgress'],
            [self::class, 'clearActive']
        );
        $status = (string) ($progress['status'] ?? '');
        if (!in_array($status, ['queued', 'running', 'cancelling', 'done'], true)) {
            self::clearActive($clientUserId);
            return null;
        }
        return $progress;
    }

    public function run(array $data): void
    {
        $jobId = (string) ($data['jobId'] ?? '');
        $clientUserId = (int) ($data['clientUserId'] ?? 0);
        $query = is_array($data['query'] ?? null) ? $data['query'] : [];

        if ($jobId === '' || $clientUserId <= 0) {
            Logger::error('export_client_ib_statement_report invalid payload', ['data' => $data]);
            return;
        }

        self::ensureExportDir();
        $csvFile = self::csvPath($jobId);

        try {
            $existing = self::readProgress($jobId) ?: [];
            $existingStatus = (string) ($existing['status'] ?? '');
            if ($existingStatus === 'cancelled' || !empty($existing['cancelRequested'])) {
                $this->cancelJob($jobId, $clientUserId, $csvFile);
                return;
            }

            if ($this->writeRunningProgress($jobId, $clientUserId, [
                'percent' => 0,
                'processed' => 0,
                'total' => 0,
                'message' => 'Export started',
                'file' => basename($csvFile),
                'fileName' => $existing['fileName'] ?? null,
            ])) {
                $this->cancelJob($jobId, $clientUserId, $csvFile);
                return;
            }

            $this->runExport($jobId, $clientUserId, $query, $csvFile, $existing);
        } catch (Throwable $e) {
            Logger::error('export_client_ib_statement_report failed', [
                'jobId' => $jobId,
                'clientUserId' => $clientUserId,
                'error' => $e->getMessage(),
            ]);
            $this->failJob($jobId, $clientUserId, $e->getMessage(), $csvFile);
        }
    }

    private function runExport(
        string $jobId,
        int $clientUserId,
        array $query,
        string $csvFile,
        array $existing
    ): void {
        $ibPartnerId = (int) ($query['ibPartnerId'] ?? 0);
        $startDate = (string) ($query['startDate'] ?? '');
        $endDate = (string) ($query['endDate'] ?? '');
        $fileName = (string) ($existing['fileName'] ?? '');

        $partnerModel = new IbPartner();
        $partner = $partnerModel->findById($ibPartnerId);
        if (
            !$partner
            || (int) ($partner['userId'] ?? 0) !== $clientUserId
            || ($partner['status'] ?? '') !== IbPartner::STATUS_APPROVED
        ) {
            throw new RuntimeException('IB partner not found');
        }

        if ($this->writeRunningProgress($jobId, $clientUserId, [
            'percent' => 10,
            'processed' => 0,
            'total' => 1,
            'message' => 'Building statement',
            'file' => basename($csvFile),
            'fileName' => $fileName,
        ])) {
            $this->cancelJob($jobId, $clientUserId, $csvFile);
            return;
        }

        $controller = new IbStatementReportController();
        $statement = $controller->buildStatement($ibPartnerId, $startDate, $endDate, ['scope' => 'all']);
        if ($statement === null) {
            throw new RuntimeException('IB partner not found');
        }

        $accountCount = count($statement['accounts'] ?? []);
        $total = max(1, $accountCount);

        if ($this->writeRunningProgress($jobId, $clientUserId, [
            'percent' => 40,
            'processed' => 0,
            'total' => $total,
            'message' => 'Writing CSV',
            'file' => basename($csvFile),
            'fileName' => $fileName,
        ])) {
            $this->cancelJob($jobId, $clientUserId, $csvFile);
            return;
        }

        $fp = fopen($csvFile, 'w');
        if ($fp === false) {
            throw new RuntimeException('Failed to open CSV file for writing');
        }
        $processed = IbStatementReportExportService::writeStatementCsv($fp, $statement, function () use ($jobId) {
            return $this->isCancelRequested($jobId);
        });
        fclose($fp);
        if ($processed < 0) {
            $this->cancelJob($jobId, $clientUserId, $csvFile);
            return;
        }

        if ($this->isCancelRequested($jobId)) {
            $this->cancelJob($jobId, $clientUserId, $csvFile);
            return;
        }

        $this->finishDone($jobId, $clientUserId, $processed, $total, $csvFile, $fileName);
    }

    private function failJob(string $jobId, int $clientUserId, string $message, ?string $csvFile = null): void
    {
        if ($csvFile && file_exists($csvFile)) {
            @unlink($csvFile);
        }
        self::writeProgress($jobId, [
            'clientUserId' => $clientUserId,
            'status' => 'error',
            'cancelRequested' => false,
            'percent' => 0,
            'message' => $message,
            'file' => null,
        ]);
        self::clearActive($clientUserId);
    }

    private function cancelJob(string $jobId, int $clientUserId, ?string $csvFile = null): void
    {
        if ($csvFile && file_exists($csvFile)) {
            @unlink($csvFile);
        }
        $existing = self::readProgress($jobId) ?: [];
        self::writeProgress($jobId, [
            'clientUserId' => $clientUserId,
            'status' => 'cancelled',
            'cancelRequested' => true,
            'percent' => max(0, min(100, (int) ($existing['percent'] ?? 0))),
            'processed' => max(0, (int) ($existing['processed'] ?? 0)),
            'total' => max(0, (int) ($existing['total'] ?? 0)),
            'message' => 'Export cancelled',
            'file' => null,
            'fileName' => $existing['fileName'] ?? null,
        ]);
        self::clearActive($clientUserId);
    }

    private function isCancelRequested(string $jobId): bool
    {
        $progress = self::readProgress($jobId);
        return !empty($progress['cancelRequested']);
    }

    private function writeRunningProgress(string $jobId, int $clientUserId, array $fields): bool
    {
        $cancelRequested = !empty($fields['cancelRequested']) || $this->isCancelRequested($jobId);
        $payload = array_merge([
            'clientUserId' => $clientUserId,
            'status' => 'running',
            'cancelRequested' => false,
        ], $fields);
        if ($cancelRequested) {
            $payload['cancelRequested'] = true;
            $payload['status'] = 'cancelling';
            $payload['message'] = 'Cancelling export...';
        } else {
            $payload['cancelRequested'] = false;
        }
        self::writeProgress($jobId, $payload);
        return $cancelRequested;
    }

    private function finishDone(
        string $jobId,
        int $clientUserId,
        int $processed,
        int $total,
        string $csvFile,
        string $fileName
    ): void {
        self::writeProgress($jobId, [
            'clientUserId' => $clientUserId,
            'status' => 'done',
            'cancelRequested' => false,
            'percent' => 100,
            'processed' => $processed,
            'total' => $total,
            'message' => $total > 0 ? 'Export ready' : 'No data to export',
            'downloadReady' => true,
            'file' => basename($csvFile),
            'fileName' => $fileName !== '' ? $fileName : basename($csvFile),
        ]);
    }
}
