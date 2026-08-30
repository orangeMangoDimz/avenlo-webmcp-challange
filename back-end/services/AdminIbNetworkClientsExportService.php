<?php

require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/WalletBalanceService.php';
require_once __DIR__ . '/ExportJobTimeoutReaper.php';
require_once __DIR__ . '/ExportProgressGuard.php';

class AdminIbNetworkClientsExportService
{
    private const BATCH_SIZE = 500;
    private const PROGRESS_EVERY = 50;

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
        return self::exportDir() . '/active_admin_ibnc_' . (int)$adminUserId . '.json';
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
        $ibPartnerId = (int)($data['ibPartnerId'] ?? 0);

        if ($jobId === '' || $adminUserId <= 0 || $ibPartnerId <= 0) {
            Logger::error('export_admin_ib_network_clients invalid payload', ['data' => $data]);
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

            $this->runExport($jobId, $adminUserId, $ibPartnerId, $csvFile, $existing);
        } catch (Throwable $e) {
            Logger::error('export_admin_ib_network_clients failed', [
                'jobId' => $jobId,
                'adminUserId' => $adminUserId,
                'ibPartnerId' => $ibPartnerId,
                'error' => $e->getMessage(),
            ]);
            $this->failJob($jobId, $adminUserId, $e->getMessage(), $csvFile);
        }
    }

    private function runExport(
        string $jobId,
        int $adminUserId,
        int $ibPartnerId,
        string $csvFile,
        array $existing
    ): void {
        $ibPartnerModel = new IbPartner();
        if (!$ibPartnerModel->findById($ibPartnerId)) {
            throw new RuntimeException('IB partner not found');
        }

        $fileName = (string)($existing['fileName'] ?? '');
        $walletService = new WalletBalanceService();
        $firstPage = $ibPartnerModel->getNetworkClientsBatch($ibPartnerId, 1, self::BATCH_SIZE);
        $total = (int)($firstPage['total'] ?? 0);
        $perPage = (int)($firstPage['per_page'] ?? self::BATCH_SIZE);
        if ($perPage < 1) {
            $perPage = self::BATCH_SIZE;
        }

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
            'Client ID',
            'Name',
            'Email',
            'Phone',
            'Wallet Balance',
            'Is IB',
            'KYC',
            'Status',
            'Upline IB',
            'Country',
            'Registered',
        ]);

        $processed = 0;
        $page = 1;
        $pageItems = is_array($firstPage['items'] ?? null) ? $firstPage['items'] : [];

        while (true) {
            if ($this->isCancelRequested($jobId)) {
                fclose($fp);
                $this->cancelJob($jobId, $adminUserId, $csvFile);
                return;
            }

            foreach ($pageItems as $row) {
                $clientId = (int)($row['clientId'] ?? 0);
                $walletBalance = $clientId > 0 ? $walletService->getAvailableBalance($clientId) : 0;
                fputcsv($fp, [
                    $clientId > 0 ? $clientId : '',
                    $this->fullName($row),
                    $this->displayText($row['email'] ?? ''),
                    $this->phoneText($row),
                    $this->formatCurrency($walletBalance),
                    ((int)($row['isIb'] ?? 0) === 1) ? 'Yes' : 'No',
                    $this->displayText($row['kycStatus'] ?? ''),
                    $this->displayText($row['clientStatus'] ?? ''),
                    $this->displayText($row['parentIbCode'] ?? ''),
                    $this->displayText($row['country'] ?? ''),
                    $this->formatDate($row['clientRegistrationDate'] ?? ''),
                ]);
                $processed++;
                if ($processed % self::PROGRESS_EVERY === 0) {
                    if ($this->publishProgress($jobId, $adminUserId, $processed, $total, $csvFile, $fileName)) {
                        fclose($fp);
                        $this->cancelJob($jobId, $adminUserId, $csvFile);
                        return;
                    }
                    @fflush($fp);
                }
            }

            if ($processed >= $total || count($pageItems) < $perPage) {
                break;
            }
            $page++;
            $next = $ibPartnerModel->getNetworkClientsBatch($ibPartnerId, $page, $perPage);
            $pageItems = is_array($next['items'] ?? null) ? $next['items'] : [];
            if (!$pageItems) {
                break;
            }
        }

        fclose($fp);

        if ($this->isCancelRequested($jobId)) {
            $this->cancelJob($jobId, $adminUserId, $csvFile);
            return;
        }

        $this->finishDone($jobId, $adminUserId, $processed, $total, $csvFile, $fileName);
    }

    private function fullName(array $row): string
    {
        $name = trim((string)($row['firstName'] ?? '') . ' ' . (string)($row['lastName'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $email = trim((string)($row['email'] ?? ''));
        return $email !== '' ? $email : '--';
    }

    private function phoneText(array $row): string
    {
        $phone = trim((string)($row['phone'] ?? ''));
        if ($phone === '') {
            return '--';
        }
        $code = trim((string)($row['phoneCountryCode'] ?? ''));
        return $code !== '' ? $code . ' ' . $phone : $phone;
    }

    private function displayText($value): string
    {
        $text = trim((string)($value ?? ''));
        return $text !== '' ? $text : '--';
    }

    private function formatCurrency($amount): string
    {
        return '$' . number_format((float)$amount, 2, '.', ',');
    }

    private function formatDate($value): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return '--';
        }
        $ts = strtotime(str_replace(' ', 'T', $text));
        if ($ts === false) {
            return $text;
        }
        return date('Y-m-d', $ts);
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
