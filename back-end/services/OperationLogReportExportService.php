<?php

require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../models/AdminOperationLog.php';
require_once __DIR__ . '/../models/AdminDictionaryItem.php';
require_once __DIR__ . '/ExportJobTimeoutReaper.php';
require_once __DIR__ . '/AdminOperationLogWriter.php';
require_once __DIR__ . '/OperationLogPages.php';

class OperationLogReportExportService
{
    private const BATCH_SIZE = 500;
    private const REPORT_MODULE_KEY = 'log_report';

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
        return self::exportDir() . '/active_admin_olr_' . (int)$adminUserId . '.json';
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
            Logger::error('export_admin_operation_log_report invalid payload', ['data' => $data]);
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
            Logger::error('export_admin_operation_log_report failed', [
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
        $filters = is_array($query['filters'] ?? null) ? $query['filters'] : [];
        $language = strtolower((string)($query['language'] ?? 'en')) === 'zh' ? 'zh' : 'en';
        $zh = $language === 'zh';
        $modelKey = trim((string)($filters['modelKey'] ?? ''));
        if ($modelKey === '') {
            throw new RuntimeException('modelKey is required');
        }

        $logModel = new AdminOperationLog();
        $matched = $logModel->countByFilters($filters);
        $cap = AdminOperationLog::MAX_EXPORT;
        $truncated = $matched > $cap;
        $total = min($matched, $cap);
        $fileName = (string)($existing['fileName'] ?? '');
        $reportTab = $modelKey === self::REPORT_MODULE_KEY;

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
        fputcsv($fp, $this->headers($zh, $reportTab));

        $operationTypes = $this->operationTypeMap();
        $subModules = $this->subModuleMap($modelKey);
        $processed = 0;
        $page = 1;

        while ($processed < $total) {
            if ($this->isCancelRequested($jobId)) {
                fclose($fp);
                $this->cancelJob($jobId, $adminUserId, $csvFile);
                return;
            }

            $rows = $logModel->findByFilters($filters, $page, self::BATCH_SIZE);
            if (!$rows) {
                break;
            }

            foreach ($rows as $row) {
                if ($processed >= $total) {
                    break;
                }
                fputcsv($fp, $this->csvRow($row, $zh, $reportTab, $operationTypes, $subModules));
                $processed++;
            }

            if ($this->publishProgress($jobId, $adminUserId, $processed, $total, $csvFile, $fileName)) {
                fclose($fp);
                $this->cancelJob($jobId, $adminUserId, $csvFile);
                return;
            }
            @fflush($fp);
            $page++;
        }

        fclose($fp);

        if ($this->isCancelRequested($jobId)) {
            $this->cancelJob($jobId, $adminUserId, $csvFile);
            return;
        }

        $this->finishDone($jobId, $adminUserId, $processed, $total, $csvFile, $fileName);

        $range = '';
        if (!empty($filters['startDate']) || !empty($filters['endDate'])) {
            $range = ($filters['startDate'] ?: '…') . ' ~ ' . ($filters['endDate'] ?: '…');
        }
        $detailSuffix = $truncated
            ? '（共 ' . $matched . ' 条，已导出上限 ' . $cap . ' 条）'
            : '，共 ' . $processed . ' 条';
        $detailSuffixEn = $truncated
            ? ' (total ' . $matched . ', capped at ' . $cap . ')'
            : ', ' . $processed . ' row(s)';
        $writer = new AdminOperationLogWriter();
        $writer->logReportExport(
            OperationLogPages::subModuleKeyByAlias('page_operation_log_report'),
            '导出操作日志报表' . $detailSuffix . ($range !== '' ? '，日期：' . $range : ''),
            'Exported operation log report' . $detailSuffixEn . ($range !== '' ? ', date: ' . $range : '')
        );
    }

    private function headers(bool $zh, bool $reportTab): array
    {
        $cols = $zh
            ? ['操作时间', '操作人', '模块', '子模块']
            : ['Operation Time', 'Operator', 'Module', 'Sub-module'];
        if (!$reportTab) {
            $cols[] = $zh ? '操作类型' : 'Operation Type';
            $cols[] = $zh ? '操作对象' : 'Target';
        }
        $cols[] = $zh ? '操作详情' : 'Detail';
        $cols[] = $zh ? 'IP 地址' : 'IP Address';
        return $cols;
    }

    private function csvRow(
        array $row,
        bool $zh,
        bool $reportTab,
        array $operationTypes,
        array $subModules
    ): array {
        $out = [
            $this->formatOperatedAt($row['operatedAt'] ?? null),
            (string)($row['operatorFullName'] ?? ''),
            $this->pickZhEn($row, 'moduleNameZh', 'moduleNameEn', $zh),
            $this->subModuleLabel($row, $zh, $subModules),
        ];
        if (!$reportTab) {
            $out[] = $this->operationTypeLabel((string)($row['operationTypeKey'] ?? ''), $zh, $operationTypes);
            $out[] = $this->targetLabel($row, $zh);
        }
        $out[] = $this->pickZhEn($row, 'detailZh', 'detailEn', $zh);
        $out[] = (string)($row['ipAddress'] ?? '');
        return $out;
    }

    private function operationTypeMap(): array
    {
        $dict = new AdminDictionaryItem();
        $map = [];
        foreach ($dict->findOperationLogTypes() as $item) {
            $key = trim((string)($item['value'] ?? ''));
            if ($key === '') {
                continue;
            }
            $map[$key] = $item;
        }
        return $map;
    }

    private function subModuleMap(string $modelKey): array
    {
        $dict = new AdminDictionaryItem();
        $grouped = $dict->findOperationLogSubModulesByModelKeys([$modelKey]);
        $map = [];
        foreach (($grouped[$modelKey] ?? []) as $item) {
            $key = trim((string)($item['value'] ?? ''));
            if ($key === '') {
                continue;
            }
            $map[$key] = $item;
        }
        return $map;
    }

    private function subModuleLabel(array $row, bool $zh, array $subModules): string
    {
        $key = trim((string)($row['subModuleKey'] ?? ''));
        if ($key !== '' && isset($subModules[$key])) {
            $fromDict = $this->pickLabel($subModules[$key], $zh);
            if ($fromDict !== '') {
                return $fromDict;
            }
        }
        return $this->pickZhEn($row, 'subModuleNameZh', 'subModuleNameEn', $zh);
    }

    private function operationTypeLabel(string $key, bool $zh, array $operationTypes): string
    {
        if ($key !== '' && isset($operationTypes[$key])) {
            $fromDict = $this->pickLabel($operationTypes[$key], $zh);
            if ($fromDict !== '') {
                return $fromDict;
            }
        }
        return $key;
    }

    private function targetLabel(array $row, bool $zh): string
    {
        $targetId = isset($row['targetId']) ? (int)$row['targetId'] : 0;
        if ($targetId <= 0) {
            return '';
        }
        $name = $this->pickZhEn($row, 'targetDisplayNameZh', 'targetDisplayNameEn', $zh);
        if ($name === '') {
            $name = trim((string)($row['targetDisplayName'] ?? ''));
        }
        if ($name === '') {
            return '';
        }

        $sub = (string)($row['subModuleKey'] ?? '');
        $modelKey = (string)($row['modelKey'] ?? '');
        if ($sub === 'kyc_templates') {
            $line2 = $zh ? 'KYC 模板' : 'KYC Template';
        } elseif ($modelKey === 'log_sales') {
            $line2 = ($zh ? '销售' : 'Sales') . ' ' . $this->targetIdText($targetId, $zh);
        } elseif ($sub === 'accounts') {
            $line2 = ($zh ? '管理员' : 'Administrator') . ' ' . $this->targetIdText($targetId, $zh);
        } elseif ($sub === 'role_management') {
            $line2 = ($zh ? '角色' : 'Role') . ' ' . $this->targetIdText($targetId, $zh);
        } else {
            $line2 = ($zh ? '客户' : 'Client') . ' ' . $this->targetIdText($targetId, $zh);
        }

        return $name . ' / ' . $line2;
    }

    private function targetIdText(int $id, bool $zh): string
    {
        return $zh ? ('ID：' . $id) : ('ID: ' . $id);
    }

    private function pickZhEn(array $row, string $zhKey, string $enKey, bool $zh): string
    {
        $zhVal = trim((string)($row[$zhKey] ?? ''));
        $enVal = trim((string)($row[$enKey] ?? ''));
        return $zh ? ($zhVal !== '' ? $zhVal : $enVal) : ($enVal !== '' ? $enVal : $zhVal);
    }

    private function pickLabel(array $item, bool $zh): string
    {
        $zhVal = trim((string)($item['labelZh'] ?? ''));
        $enVal = trim((string)($item['labelEn'] ?? ''));
        return $zh ? ($zhVal !== '' ? $zhVal : $enVal) : ($enVal !== '' ? $enVal : $zhVal);
    }

    private function formatOperatedAt($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        try {
            $dt = new DateTime((string)$value, new DateTimeZone('UTC'));
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return (string)$value;
        }
    }

    private function failJob(string $jobId, int $adminUserId, string $message, ?string $csvFile = null): void
    {
        if ($csvFile && file_exists($csvFile)) {
            @unlink($csvFile);
        }
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
