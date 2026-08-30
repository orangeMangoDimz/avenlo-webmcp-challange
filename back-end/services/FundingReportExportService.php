<?php

require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/ExportJobTimeoutReaper.php';
require_once __DIR__ . '/AdminOperationLogWriter.php';
require_once __DIR__ . '/OperationLogPages.php';

class FundingReportExportService
{
    private const BATCH_SIZE = 500;
    private const ALLOWED_TYPES = ['deposit', 'withdrawal', 'internal_transfer'];

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

    public static function activePath(int $adminUserId): string
    {
        return self::exportDir() . '/active_admin_fr_' . (int)$adminUserId . '.json';
    }

    public static function csvPath(string $jobId): string
    {
        return self::exportDir() . '/' . preg_replace('/[^a-zA-Z0-9._-]/', '', $jobId) . '.csv';
    }

    public static function selectedItemsPath(string $jobId): string
    {
        return self::exportDir() . '/selected_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $jobId) . '.json';
    }

    public static function writeSelectedItems(string $jobId, array $items): void
    {
        self::ensureExportDir();
        $json = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to store selected rows');
        }
        $ok = @file_put_contents(self::selectedItemsPath($jobId), $json, LOCK_EX);
        if ($ok === false) {
            throw new RuntimeException('Failed to store selected rows');
        }
    }

    public static function readSelectedItems(string $jobId): array
    {
        $file = self::selectedItemsPath($jobId);
        if (!file_exists($file)) {
            throw new RuntimeException('Selected rows not found');
        }
        $data = @json_decode((string)@file_get_contents($file), true);
        if (!is_array($data)) {
            throw new RuntimeException('Selected rows are invalid');
        }
        return $data;
    }

    public static function clearSelectedItems(string $jobId): void
    {
        $file = self::selectedItemsPath($jobId);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    public static function readProgress(string $jobId): ?array
    {
        $file = self::progressPath($jobId);
        if (!file_exists($file)) {
            return null;
        }
        $data = @json_decode((string)@file_get_contents($file), true);
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

    public static function readActive(int $adminUserId): ?array
    {
        $file = self::activePath($adminUserId);
        if (!file_exists($file)) {
            return null;
        }
        $data = @json_decode((string)@file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    public static function writeActive(int $adminUserId, string $jobId): void
    {
        self::ensureExportDir();
        @file_put_contents(
            self::activePath($adminUserId),
            json_encode([
                'jobId' => $jobId,
                'updatedAt' => date('Y-m-d H:i:s'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    public static function clearActive(int $adminUserId): void
    {
        $file = self::activePath($adminUserId);
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
        $status = (string)($progress['status'] ?? '');
        if (!in_array($status, ['queued', 'running', 'cancelling', 'done'], true)) {
            return false;
        }

        $adminUserId = (int)($progress['adminUserId'] ?? 0);
        $percent = max(0, min(100, (int)($progress['percent'] ?? 0)));
        $processed = max(0, (int)($progress['processed'] ?? 0));
        $total = max(0, (int)($progress['total'] ?? 0));

        if ($status === 'queued' || $status === 'done') {
            $csvFile = self::csvPath($jobId);
            if (file_exists($csvFile)) {
                @unlink($csvFile);
            }
            self::writeProgress($jobId, [
                'adminUserId' => $adminUserId,
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
            self::clearSelectedItems($jobId);
            if ($adminUserId > 0) {
                self::clearActive($adminUserId);
            }
            return true;
        }

        $progress['cancelRequested'] = true;
        $progress['status'] = 'cancelling';
        $progress['message'] = 'Cancelling export...';
        self::writeProgress($jobId, $progress);
        return true;
    }

    public static function getActiveForAdmin(int $adminUserId): ?array
    {
        $active = self::readActive($adminUserId);
        if ($active === null || empty($active['jobId'])) {
            return null;
        }
        $jobId = (string)$active['jobId'];
        $progress = self::readProgress($jobId);
        if ($progress === null) {
            self::clearSelectedItems($jobId);
            self::clearActive($adminUserId);
            return null;
        }
        $progress = ExportJobTimeoutReaper::reapIfStale(
            $jobId,
            $progress,
            [self::class, 'writeProgress'],
            [self::class, 'clearActive']
        );
        $status = (string)($progress['status'] ?? '');
        if (!in_array($status, ['queued', 'running', 'cancelling', 'done'], true)) {
            self::clearSelectedItems($jobId);
            self::clearActive($adminUserId);
            return null;
        }
        return $progress;
    }

    public function run(array $data): void
    {
        $jobId = (string)($data['jobId'] ?? '');
        $adminUserId = (int)($data['adminUserId'] ?? 0);
        $query = is_array($data['query'] ?? null) ? $data['query'] : [];

        if ($jobId === '' || $adminUserId <= 0) {
            Logger::error('export_admin_funding_report invalid payload', ['data' => $data]);
            return;
        }

        self::ensureExportDir();
        $csvFile = self::csvPath($jobId);

        try {
            $existing = self::readProgress($jobId) ?: [];
            $existingStatus = (string)($existing['status'] ?? '');
            if ($existingStatus === 'cancelled' || !empty($existing['cancelRequested'])) {
                $this->cancelJob($jobId, $adminUserId, $csvFile);
                return;
            }

            if ($this->writeRunningProgress($jobId, $adminUserId, [
                'percent' => 0,
                'processed' => 0,
                'total' => 0,
                'message' => 'Export started',
                'file' => basename($csvFile),
                'fileName' => $existing['fileName'] ?? null,
            ])) {
                $this->cancelJob($jobId, $adminUserId, $csvFile);
                return;
            }

            $this->runExport($jobId, $adminUserId, $query, $csvFile, $existing);
        } catch (Throwable $e) {
            Logger::error('export_admin_funding_report failed', [
                'jobId' => $jobId,
                'adminUserId' => $adminUserId,
                'error' => $e->getMessage(),
            ]);
            $this->failJob($jobId, $adminUserId, $e->getMessage(), $csvFile);
        }
    }

    private function runExport(
        string $jobId,
        int $adminUserId,
        array $query,
        string $csvFile,
        array $existing
    ): void {
        $items = self::readSelectedItems($jobId);
        $scope = is_array($query['scope'] ?? null) ? $query['scope'] : [];
        $rows = $this->fetchSelectedRows($items, $scope);
        $total = count($rows);
        $fileName = (string)($existing['fileName'] ?? '');

        if ($this->writeRunningProgress($jobId, $adminUserId, [
            'percent' => $total > 0 ? 1 : 0,
            'processed' => 0,
            'total' => $total,
            'message' => $total > 0 ? "Processing 0 of {$total}" : 'No data to export',
            'file' => basename($csvFile),
            'fileName' => $fileName,
        ])) {
            $this->cancelJob($jobId, $adminUserId, $csvFile);
            return;
        }

        $fp = fopen($csvFile, 'w');
        if ($fp === false) {
            throw new RuntimeException('Failed to open CSV file for writing');
        }
        fwrite($fp, "\xEF\xBB\xBF");
        fputcsv($fp, [
            'Type',
            'Transaction ID',
            'Client Name',
            'Email',
            'Amount',
            'Payment Method',
            'Status',
            'Date',
        ]);

        $processed = 0;
        foreach ($rows as $row) {
            if ($processed > 0 && $processed % self::BATCH_SIZE === 0 && $this->isCancelRequested($jobId)) {
                fclose($fp);
                $this->cancelJob($jobId, $adminUserId, $csvFile);
                return;
            }
            fputcsv($fp, [
                $this->typeLabel((string)($row['transactionType'] ?? '')),
                (string)($row['transactionId'] ?? ''),
                $this->clientName($row),
                (string)($row['email'] ?? ''),
                $this->cellValue($row['amount'] ?? ''),
                (string)($row['paymentMethod'] ?? ''),
                (string)($row['status'] ?? ''),
                (string)($row['requestedAt'] ?? ''),
            ]);
            $processed++;
            if ($processed % self::BATCH_SIZE === 0) {
                if ($this->publishProgress($jobId, $adminUserId, $processed, $total, $csvFile, $fileName)) {
                    fclose($fp);
                    $this->cancelJob($jobId, $adminUserId, $csvFile);
                    return;
                }
                @fflush($fp);
            }
        }

        fclose($fp);
        self::clearSelectedItems($jobId);

        if ($this->isCancelRequested($jobId)) {
            $this->cancelJob($jobId, $adminUserId, $csvFile);
            return;
        }

        $this->finishDone($jobId, $adminUserId, $processed, $total, $csvFile, $fileName);

        $writer = new AdminOperationLogWriter();
        $writer->logReportExport(
            OperationLogPages::subModuleKeyByAlias('page_funding_report'),
            '导出资金报表（CSV），共 ' . $processed . ' 笔',
            'Exported funding report (CSV), ' . $processed . ' row(s)'
        );
    }

    private function fetchSelectedRows(array $items, array $scope): array
    {
        if (($scope['scope'] ?? '') === 'none' || !$items) {
            return [];
        }

        $pairs = [];
        $params = [];
        foreach ($items as $index => $item) {
            $type = (string)($item['type'] ?? '');
            $id = (int)($item['id'] ?? 0);
            if ($id <= 0 || !in_array($type, self::ALLOWED_TYPES, true)) {
                continue;
            }
            $pairs[] = "(:t{$index}, :i{$index})";
            $params["t{$index}"] = $type;
            $params["i{$index}"] = $id;
        }
        if (!$pairs) {
            return [];
        }

        $sql = "SELECT id, transactionType, transactionId, firstName, lastName, email, amount, paymentMethod, status, requestedAt
                FROM vAllTransactions
                WHERE (transactionType, id) IN (" . implode(', ', $pairs) . ")";
        if (($scope['scope'] ?? '') === 'own') {
            $sql .= ' AND userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)';
            $params['restrict_to_sales_id'] = (int)($scope['restrict_to_sales_id'] ?? 0);
        }

        $rows = Database::getInstance()->fetchAll($sql, $params);
        $byKey = [];
        foreach ($rows as $row) {
            $byKey[(string)($row['transactionType'] ?? '') . '-' . (int)($row['id'] ?? 0)] = $row;
        }

        $ordered = [];
        foreach ($items as $item) {
            $key = (string)($item['type'] ?? '') . '-' . (int)($item['id'] ?? 0);
            if (isset($byKey[$key])) {
                $ordered[] = $byKey[$key];
            }
        }
        return $ordered;
    }

    private function typeLabel(string $type): string
    {
        if ($type === 'deposit') {
            return 'Deposit';
        }
        if ($type === 'withdrawal') {
            return 'Withdrawal';
        }
        if ($type === 'internal_transfer') {
            return 'Internal Transfer';
        }
        return $type;
    }

    private function clientName(array $row): string
    {
        return trim((string)($row['firstName'] ?? '') . ' ' . (string)($row['lastName'] ?? ''));
    }

    private function cellValue($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }
        return (string)$value;
    }

    private function failJob(string $jobId, int $adminUserId, string $message, ?string $csvFile = null): void
    {
        if ($csvFile && file_exists($csvFile)) {
            @unlink($csvFile);
        }
        self::clearSelectedItems($jobId);
        self::writeProgress($jobId, [
            'adminUserId' => $adminUserId,
            'status' => 'error',
            'cancelRequested' => false,
            'percent' => 0,
            'message' => $message,
            'file' => null,
        ]);
        self::clearActive($adminUserId);
    }

    private function cancelJob(string $jobId, int $adminUserId, ?string $csvFile = null): void
    {
        if ($csvFile && file_exists($csvFile)) {
            @unlink($csvFile);
        }
        self::clearSelectedItems($jobId);
        $existing = self::readProgress($jobId) ?: [];
        self::writeProgress($jobId, [
            'adminUserId' => $adminUserId,
            'status' => 'cancelled',
            'cancelRequested' => true,
            'percent' => max(0, min(100, (int)($existing['percent'] ?? 0))),
            'processed' => max(0, (int)($existing['processed'] ?? 0)),
            'total' => max(0, (int)($existing['total'] ?? 0)),
            'message' => 'Export cancelled',
            'file' => null,
            'fileName' => $existing['fileName'] ?? null,
        ]);
        self::clearActive($adminUserId);
    }

    private function isCancelRequested(string $jobId): bool
    {
        $progress = self::readProgress($jobId);
        return !empty($progress['cancelRequested']);
    }

    private function writeRunningProgress(string $jobId, int $adminUserId, array $fields): bool
    {
        $cancelRequested = !empty($fields['cancelRequested']) || $this->isCancelRequested($jobId);
        $payload = array_merge([
            'adminUserId' => $adminUserId,
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

    private function publishProgress(
        string $jobId,
        int $adminUserId,
        int $processed,
        int $total,
        string $csvFile,
        string $fileName
    ): bool {
        $percent = $total > 0 ? (int)min(99, max(1, floor(($processed / max($total, 1)) * 100))) : 0;
        return $this->writeRunningProgress($jobId, $adminUserId, [
            'percent' => $percent,
            'processed' => $processed,
            'total' => $total,
            'message' => $total > 0 ? "Processing {$processed} of {$total}" : 'No data to export',
            'file' => basename($csvFile),
            'fileName' => $fileName,
        ]);
    }

    private function finishDone(
        string $jobId,
        int $adminUserId,
        int $processed,
        int $total,
        string $csvFile,
        string $fileName
    ): void {
        self::writeProgress($jobId, [
            'adminUserId' => $adminUserId,
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
