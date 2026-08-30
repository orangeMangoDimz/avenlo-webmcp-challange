<?php

require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/ExportJobTimeoutReaper.php';
require_once __DIR__ . '/AdminOperationLogWriter.php';
require_once __DIR__ . '/OperationLogPages.php';

class IbStatementReportExportService
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

    public static function activePath(int $adminUserId): string
    {
        return self::exportDir() . '/active_admin_isr_' . (int) $adminUserId . '.json';
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

    public static function readActive(int $adminUserId): ?array
    {
        $file = self::activePath($adminUserId);
        if (!file_exists($file)) {
            return null;
        }
        $data = @json_decode((string) @file_get_contents($file), true);
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
        $status = (string) ($progress['status'] ?? '');
        if (!in_array($status, ['queued', 'running', 'cancelling', 'done'], true)) {
            return false;
        }

        $adminUserId = (int) ($progress['adminUserId'] ?? 0);
        $percent = max(0, min(100, (int) ($progress['percent'] ?? 0)));
        $processed = max(0, (int) ($progress['processed'] ?? 0));
        $total = max(0, (int) ($progress['total'] ?? 0));

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
        $jobId = (string) $active['jobId'];
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
        $status = (string) ($progress['status'] ?? '');
        if (!in_array($status, ['queued', 'running', 'cancelling', 'done'], true)) {
            self::clearActive($adminUserId);
            return null;
        }
        return $progress;
    }

    public static function writeStatementCsv($fp, array $statement, ?callable $shouldAbort = null): int
    {
        fwrite($fp, "\xEF\xBB\xBF");

        $partner = $statement['partner'] ?? [];
        $period = $statement['period'] ?? [];
        $headline = $statement['headline'] ?? [];
        $movement = $statement['movement'] ?? [];
        $accounts = is_array($statement['accounts'] ?? null) ? $statement['accounts'] : [];
        $accountsTotal = $statement['accountsTotal'] ?? [];
        $instruments = $statement['instruments']['items'] ?? [];
        $instrumentTotal = $statement['instruments']['total'] ?? [];
        $weeks = $statement['weeks']['items'] ?? [];
        $weekTotal = $statement['weeks']['total'] ?? [];

        fputcsv($fp, ['IB Statement']);
        fputcsv($fp, ['Introducing Broker', $partner['name'] ?? '', $partner['ibCode'] ?? '']);
        fputcsv($fp, ['Period', $period['start'] ?? '', $period['end'] ?? '']);
        fputcsv($fp, ['Currency', $statement['currency'] ?? 'USD']);
        fputcsv($fp, []);

        fputcsv($fp, ['Headline']);
        fputcsv($fp, ['Clients', $headline['clientCount'] ?? 0]);
        fputcsv($fp, ['Accounts', $headline['accountCount'] ?? 0]);
        fputcsv($fp, ['Funded', $headline['fundedCount'] ?? 0]);
        fputcsv($fp, ['Traded', $headline['tradedCount'] ?? 0]);
        fputcsv($fp, ['Total deposits', $headline['totalDeposits'] ?? 0]);
        fputcsv($fp, ['Total withdrawals', $headline['totalWithdrawals'] ?? 0]);
        fputcsv($fp, ['Net deposits', $headline['netDeposits'] ?? 0]);
        fputcsv($fp, ['Closing balance', $headline['closingBalance'] ?? 0]);
        fputcsv($fp, []);

        fputcsv($fp, ['Account Movement']);
        fputcsv($fp, ['Item', 'Amount (USD)']);
        fputcsv($fp, ['Opening balance', $movement['openingBalance'] ?? 0]);
        fputcsv($fp, ['Client deposits', $movement['deposits'] ?? 0]);
        fputcsv($fp, ['Client withdrawals', $movement['withdrawals'] ?? 0]);
        fputcsv($fp, ['Net client deposits', $movement['netDeposits'] ?? 0]);
        fputcsv($fp, ['Trading result on closed positions', $movement['tradingResult'] ?? 0]);
        fputcsv($fp, ['Commission and fees', $movement['commissionFees'] ?? 0]);
        fputcsv($fp, ['Swap', $movement['swap'] ?? 0]);
        fputcsv($fp, ['Closing balance', $movement['closingBalance'] ?? 0]);
        fputcsv($fp, ['Unrealised result on open positions', $movement['unrealised'] ?? 0]);
        fputcsv($fp, ['Closing equity', $movement['closingEquity'] ?? 0]);
        fputcsv($fp, []);

        fputcsv($fp, ['Client Account Detail']);
        fputcsv($fp, [
            'Login',
            'Client name',
            'Type',
            'Client ID',
            'Opened',
            'Deposits',
            'Withdrawals',
            'Net deposits',
            'Lots',
            'Trades',
            'Trading result',
            'Balance',
        ]);

        $processed = 0;
        foreach ($accounts as $row) {
            if ($shouldAbort && $shouldAbort()) {
                return -1;
            }
            fputcsv($fp, [
                $row['login'] ?? '',
                $row['clientName'] ?? '',
                $row['clientType'] ?? 'Direct Client',
                $row['clientId'] ?? '',
                $row['opened'] ?? '',
                $row['deposits'] ?? 0,
                $row['withdrawals'] ?? 0,
                $row['netDeposits'] ?? 0,
                $row['lots'] ?? 0,
                $row['trades'] ?? 0,
                $row['tradingResult'] ?? 0,
                $row['balance'] ?? 0,
            ]);
            $processed++;
        }
        fputcsv($fp, [
            'TOTAL',
            ($accountsTotal['accountCount'] ?? 0) . ' accounts',
            '',
            '',
            '',
            $accountsTotal['deposits'] ?? 0,
            $accountsTotal['withdrawals'] ?? 0,
            $accountsTotal['netDeposits'] ?? 0,
            $accountsTotal['lots'] ?? 0,
            $accountsTotal['trades'] ?? 0,
            $accountsTotal['tradingResult'] ?? 0,
            $accountsTotal['balance'] ?? 0,
        ]);
        fputcsv($fp, []);

        fputcsv($fp, ['Volume and Result by Instrument']);
        fputcsv($fp, ['Instrument', 'Closed volume (lots)', 'Share of volume', 'Trading result (USD)', 'Accounts traded']);
        foreach ($instruments as $row) {
            fputcsv($fp, [
                $row['instrument'] ?? '',
                $row['lots'] ?? 0,
                ($row['shareOfVolume'] ?? 0) . '%',
                $row['tradingResult'] ?? 0,
                $row['accountsTraded'] ?? 0,
            ]);
        }
        fputcsv($fp, [
            'TOTAL',
            $instrumentTotal['lots'] ?? 0,
            ($instrumentTotal['shareOfVolume'] ?? 0) . '%',
            $instrumentTotal['tradingResult'] ?? 0,
            $instrumentTotal['accountsTraded'] ?? 0,
        ]);
        fputcsv($fp, []);

        fputcsv($fp, ['Trading Activity by Week']);
        fputcsv($fp, ['Week', 'Lots', 'Positions closed', 'Accounts trading', 'Trading result (USD)']);
        foreach ($weeks as $row) {
            fputcsv($fp, [
                $row['week'] ?? '',
                $row['lots'] ?? 0,
                $row['trades'] ?? 0,
                $row['accountsTrading'] ?? 0,
                $row['tradingResult'] ?? 0,
            ]);
        }
        fputcsv($fp, [
            'TOTAL',
            $weekTotal['lots'] ?? 0,
            $weekTotal['trades'] ?? 0,
            $weekTotal['accountsTrading'] ?? 0,
            $weekTotal['tradingResult'] ?? 0,
        ]);

        return $processed;
    }

    public function run(array $data): void
    {
        $jobId = (string) ($data['jobId'] ?? '');
        $adminUserId = (int) ($data['adminUserId'] ?? 0);
        $query = is_array($data['query'] ?? null) ? $data['query'] : [];

        if ($jobId === '' || $adminUserId <= 0) {
            Logger::error('export_admin_ib_statement_report invalid payload', ['data' => $data]);
            return;
        }

        self::ensureExportDir();
        $csvFile = self::csvPath($jobId);

        try {
            $existing = self::readProgress($jobId) ?: [];
            $existingStatus = (string) ($existing['status'] ?? '');
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
            Logger::error('export_admin_ib_statement_report failed', [
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
        require_once __DIR__ . '/../controllers/IbStatementReportController.php';

        $ibPartnerId = (int) ($query['ibPartnerId'] ?? 0);
        $startDate = (string) ($query['startDate'] ?? '');
        $endDate = (string) ($query['endDate'] ?? '');
        $scope = is_array($query['scope'] ?? null) ? $query['scope'] : ['scope' => 'none', 'restrict_to_sales_id' => 0];
        $fileName = (string) ($existing['fileName'] ?? '');

        if ($this->writeRunningProgress($jobId, $adminUserId, [
            'percent' => 10,
            'processed' => 0,
            'total' => 1,
            'message' => 'Building statement',
            'file' => basename($csvFile),
            'fileName' => $fileName,
        ])) {
            $this->cancelJob($jobId, $adminUserId, $csvFile);
            return;
        }

        $controller = new IbStatementReportController();
        $statement = $controller->buildStatement($ibPartnerId, $startDate, $endDate, $scope);
        if ($statement === null) {
            throw new RuntimeException('IB partner not found');
        }

        $accountCount = count($statement['accounts'] ?? []);
        $total = max(1, $accountCount);

        if ($this->writeRunningProgress($jobId, $adminUserId, [
            'percent' => 40,
            'processed' => 0,
            'total' => $total,
            'message' => 'Writing CSV',
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
        $processed = self::writeStatementCsv($fp, $statement, function () use ($jobId) {
            return $this->isCancelRequested($jobId);
        });
        fclose($fp);
        if ($processed < 0) {
            $this->cancelJob($jobId, $adminUserId, $csvFile);
            return;
        }

        if ($this->isCancelRequested($jobId)) {
            $this->cancelJob($jobId, $adminUserId, $csvFile);
            return;
        }

        $this->finishDone($jobId, $adminUserId, $processed, $total, $csvFile, $fileName);

        $writer = new AdminOperationLogWriter();
        $writer->logReportExport(
            OperationLogPages::subModuleKeyByAlias('page_ib_statement'),
            '导出 IB 结单（CSV），共 ' . $processed . ' 个账户',
            'Exported IB statement (CSV), ' . $processed . ' account(s)'
        );
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
            'percent' => max(0, min(100, (int) ($existing['percent'] ?? 0))),
            'processed' => max(0, (int) ($existing['processed'] ?? 0)),
            'total' => max(0, (int) ($existing['total'] ?? 0)),
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
