<?php

require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/ExportJobTimeoutReaper.php';
require_once __DIR__ . '/OperationLog/CustomReportOperationLog.php';

class CustomReportWidgetExportService
{
    private const BATCH_SIZE = 500;

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
        return self::exportDir() . '/active_admin_crw_' . (int)$adminUserId . '.json';
    }

    public static function csvPath(string $jobId): string
    {
        return self::exportDir() . '/' . preg_replace('/[^a-zA-Z0-9._-]/', '', $jobId) . '.csv';
    }

    public static function selectedRowsPath(string $jobId): string
    {
        return self::exportDir() . '/selected_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $jobId) . '.json';
    }

    public static function writeSelectedRows(string $jobId, array $rows): void
    {
        self::ensureExportDir();
        $json = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to store selected rows');
        }
        $ok = @file_put_contents(self::selectedRowsPath($jobId), $json, LOCK_EX);
        if ($ok === false) {
            throw new RuntimeException('Failed to store selected rows');
        }
    }

    public static function readSelectedRows(string $jobId): array
    {
        $file = self::selectedRowsPath($jobId);
        if (!file_exists($file)) {
            throw new RuntimeException('Selected rows not found');
        }
        $data = @json_decode((string)@file_get_contents($file), true);
        if (!is_array($data)) {
            throw new RuntimeException('Selected rows are invalid');
        }
        return $data;
    }

    public static function clearSelectedRows(string $jobId): void
    {
        $file = self::selectedRowsPath($jobId);
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
            self::clearSelectedRows($jobId);
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
            self::clearSelectedRows($jobId);
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
            self::clearSelectedRows($jobId);
            self::clearActive($adminUserId);
            return null;
        }
        return $progress;
    }

    public function run(array $data): void
    {
        $jobId = (string)($data['jobId'] ?? '');
        $adminUserId = (int)($data['adminUserId'] ?? 0);
        $reportId = trim((string)($data['reportId'] ?? ''));
        $widgetId = trim((string)($data['widgetId'] ?? ''));
        $dataSourceId = trim((string)($data['dataSourceId'] ?? ''));
        $query = is_array($data['query'] ?? null) ? $data['query'] : [];

        if ($jobId === '' || $adminUserId <= 0 || ($dataSourceId === '' && ($reportId === '' || $widgetId === ''))) {
            Logger::error('export_custom_report_widget invalid payload', ['data' => $data]);
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

            $this->runExport($jobId, $adminUserId, $reportId, $widgetId, $query, $csvFile, $existing, $dataSourceId);
        } catch (Throwable $e) {
            Logger::error('export_custom_report_widget failed', [
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
        string $reportId,
        string $widgetId,
        array $query,
        string $csvFile,
        array $existing,
        string $dataSourceId = ''
    ): void {
        if (($query['mode'] ?? 'all') === 'selected') {
            $this->runSelectedExport($jobId, $adminUserId, $reportId, $widgetId, $query, $csvFile, $existing, $dataSourceId);
            return;
        }

        require_once __DIR__ . '/../controllers/CustomReportController.php';
        $controller = new CustomReportController();

        $pageQuery = array_merge($query, [
            'offset' => 0,
            'limit' => self::BATCH_SIZE,
        ]);
        $first = $dataSourceId !== ''
            ? $controller->fetchDataSourceExportPage($dataSourceId, $pageQuery)
            : $controller->fetchWidgetExportPage($reportId, $widgetId, $pageQuery);
        $total = (int)($first['total'] ?? 0);
        $fieldsByName = is_array($first['fieldsByName'] ?? null) ? $first['fieldsByName'] : [];
        $columnNames = is_array($first['columnNames'] ?? null) ? $first['columnNames'] : [];
        $columns = $this->resolveColumns($query['columns'] ?? [], $columnNames, $fieldsByName);
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
        fputcsv($fp, array_column($columns, 'label'));

        $processed = 0;
        $offset = 0;
        $rows = is_array($first['rows'] ?? null) ? $first['rows'] : [];

        while (true) {
            if ($this->isCancelRequested($jobId)) {
                fclose($fp);
                $this->cancelJob($jobId, $adminUserId, $csvFile);
                return;
            }

            foreach ($rows as $row) {
                $line = [];
                foreach ($columns as $column) {
                    $line[] = $this->cellValue($row[$column['field']] ?? null);
                }
                fputcsv($fp, $line);
                $processed++;
            }

            if ($this->publishProgress($jobId, $adminUserId, $processed, $total, $csvFile, $fileName)) {
                fclose($fp);
                $this->cancelJob($jobId, $adminUserId, $csvFile);
                return;
            }
            @fflush($fp);

            if ($processed >= $total || count($rows) < self::BATCH_SIZE) {
                break;
            }

            $offset += self::BATCH_SIZE;
            $nextQuery = array_merge($query, [
                'offset' => $offset,
                'limit' => self::BATCH_SIZE,
            ]);
            $page = $dataSourceId !== ''
                ? $controller->fetchDataSourceExportPage($dataSourceId, $nextQuery)
                : $controller->fetchWidgetExportPage($reportId, $widgetId, $nextQuery);
            $rows = is_array($page['rows'] ?? null) ? $page['rows'] : [];
            if (!$rows) {
                break;
            }
        }

        fclose($fp);

        if ($this->isCancelRequested($jobId)) {
            $this->cancelJob($jobId, $adminUserId, $csvFile);
            return;
        }

        $this->finishDone($jobId, $adminUserId, $processed, $total, $csvFile, $fileName);
        $this->logExportComplete(
            $dataSourceId,
            $reportId,
            $widgetId,
            (string)($query['widgetName'] ?? ''),
            $processed,
            $adminUserId
        );
    }

    private function runSelectedExport(
        string $jobId,
        int $adminUserId,
        string $reportId,
        string $widgetId,
        array $query,
        string $csvFile,
        array $existing,
        string $dataSourceId = ''
    ): void {
        $rows = self::readSelectedRows($jobId);
        $total = count($rows);
        $columnNames = [];
        $rawColumns = is_array($query['columns'] ?? null) ? $query['columns'] : [];
        foreach ($rawColumns as $item) {
            if (!is_array($item)) {
                continue;
            }
            $field = trim((string)($item['field'] ?? ''));
            if ($field === '' || in_array($field, $columnNames, true)) {
                continue;
            }
            $columnNames[] = $field;
        }
        if (!$columnNames && $rows && is_array($rows[0] ?? null)) {
            $columnNames = array_keys($rows[0]);
        }
        $columns = $this->resolveColumns($query['columns'] ?? [], $columnNames, []);
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
        fputcsv($fp, array_column($columns, 'label'));

        $processed = 0;
        foreach ($rows as $row) {
            if ($processed > 0 && $processed % self::BATCH_SIZE === 0 && $this->isCancelRequested($jobId)) {
                fclose($fp);
                $this->cancelJob($jobId, $adminUserId, $csvFile);
                return;
            }
            $line = [];
            foreach ($columns as $column) {
                $line[] = $this->cellValue($row[$column['field']] ?? null);
            }
            fputcsv($fp, $line);
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
        self::clearSelectedRows($jobId);

        if ($this->isCancelRequested($jobId)) {
            $this->cancelJob($jobId, $adminUserId, $csvFile);
            return;
        }

        $this->finishDone($jobId, $adminUserId, $processed, $total, $csvFile, $fileName);
        $this->logExportComplete(
            $dataSourceId,
            $reportId,
            $widgetId,
            (string)($query['widgetName'] ?? ''),
            $processed,
            $adminUserId
        );
    }

    private function logExportComplete(
        string $dataSourceId,
        string $reportId,
        string $widgetId,
        string $name,
        int $processed,
        int $adminUserId
    ): void {
        if ($dataSourceId !== '') {
            CustomReportOperationLog::dataSourceExported(
                $dataSourceId,
                $name,
                $processed,
                $adminUserId
            );
            return;
        }
        CustomReportOperationLog::widgetExported(
            $reportId,
            $widgetId,
            $name,
            $processed,
            $adminUserId
        );
    }

    private function resolveColumns($raw, array $columnNames, array $fieldsByName): array
    {
        $allowed = array_fill_keys($columnNames, true);
        $columns = [];
        $seen = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $field = trim((string)($item['field'] ?? ''));
                if ($field === '' || !isset($allowed[$field]) || isset($seen[$field])) {
                    continue;
                }
                $seen[$field] = true;
                $label = trim((string)($item['label'] ?? ''));
                if ($label === '') {
                    $label = (string)($fieldsByName[$field]['displayName'] ?? $field);
                }
                $columns[] = [
                    'field' => $field,
                    'label' => function_exists('mb_substr') ? mb_substr($label, 0, 120) : substr($label, 0, 120),
                ];
                if (count($columns) >= 80) {
                    break;
                }
            }
        }
        if ($columns) {
            return $columns;
        }
        foreach ($columnNames as $field) {
            $columns[] = [
                'field' => $field,
                'label' => (string)($fieldsByName[$field]['displayName'] ?? $field),
            ];
        }
        return $columns;
    }

    private function cellValue($value)
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
        self::clearSelectedRows($jobId);
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
        self::clearSelectedRows($jobId);
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
