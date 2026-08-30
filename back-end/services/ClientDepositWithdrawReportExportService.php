<?php
/**
 * Async Deposit / Withdraw report CSV export (myswoole worker).
 * Progress + active pointer under storage/exports/ (active_dwr_* to avoid colliding with commission export).
 */

require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../controllers/ClientDepositWithdrawReportController.php';

class ClientDepositWithdrawReportExportService
{
    private const BATCH_SIZE = 50;

    private const CSV_HEADERS = [
        'Referral',
        'Type',
        'Level',
        'Direction',
        'Date',
        'Transaction ID',
        'Account Number',
        'Currency',
        'Amount',
        'Status',
        'Handled At',
    ];

    private const CSV_FIELDS = [
        'referralName',
        'typeLabel',
        'levelLabel',
        'direction',
        'date',
        'transactionId',
        'accountNumber',
        'currencyCode',
        'amount',
        'status',
        'handledAt',
    ];

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
        return self::exportDir() . '/active_dwr_' . (int)$clientUserId . '.json';
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

        self::syncBackgroundJobProgress($jobId, $data);
    }

    /**
     * Mirror progress file onto backgroundJobs (no-op if values unchanged).
     */
    private static function syncBackgroundJobProgress(string $jobId, array $data): void
    {
        try {
            require_once __DIR__ . '/BackgroundJobService.php';
            (new BackgroundJobService())->syncExportProgress($jobId, $data);
        } catch (Throwable $e) {
            // Tracking must never break export progress writes.
        }
    }

    public static function readActive(int $clientUserId): ?array
    {
        $file = self::activePath($clientUserId);
        if (!file_exists($file)) {
            return null;
        }
        $data = @json_decode((string)@file_get_contents($file), true);
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
        $status = (string)($progress['status'] ?? '');
        if (!in_array($status, ['queued', 'running', 'cancelling', 'done'], true)) {
            return false;
        }
        $clientUserId = (int)($progress['clientUserId'] ?? 0);
        if ($status === 'queued' || $status === 'done') {
            $csvFile = self::csvPath($jobId);
            if (file_exists($csvFile)) {
                @unlink($csvFile);
            }
            self::writeProgress($jobId, [
                'clientUserId' => $clientUserId,
                'status' => 'cancelled',
                'cancelRequested' => true,
                'percent' => max(0, min(100, (int)($progress['percent'] ?? 0))),
                'processed' => max(0, (int)($progress['processed'] ?? 0)),
                'total' => max(0, (int)($progress['total'] ?? 0)),
                'message' => 'Export cancelled',
                'file' => null,
                'downloadReady' => false,
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
        $jobId = (string)$active['jobId'];
        $progress = self::readProgress($jobId);
        if ($progress === null) {
            self::clearActive($clientUserId);
            return null;
        }
        require_once __DIR__ . '/ExportJobTimeoutReaper.php';
        $progress = ExportJobTimeoutReaper::reapIfStale(
            $jobId,
            $progress,
            [self::class, 'writeProgress'],
            [self::class, 'clearActive']
        );
        $status = (string)($progress['status'] ?? '');
        if (!in_array($status, ['queued', 'running', 'cancelling', 'done'], true)) {
            self::clearActive($clientUserId);
            return null;
        }
        return $progress;
    }

    private static function convertDateToServerTimezone($dateString): ?string
    {
        if (empty($dateString)) {
            return null;
        }
        try {
            if (strpos($dateString, 'T') !== false || strpos($dateString, 'Z') !== false || strpos($dateString, '+') !== false) {
                $date = new DateTime($dateString);
                $date->setTimezone(new DateTimeZone('Asia/Shanghai'));
                return $date->format('Y-m-d H:i:s');
            }
            return $dateString . ' 00:00:00';
        } catch (Exception $e) {
            return $dateString;
        }
    }

    private static function convertEndDateToServerTimezone($dateString): ?string
    {
        if (empty($dateString)) {
            return null;
        }
        try {
            if (strpos($dateString, 'T') !== false || strpos($dateString, 'Z') !== false || strpos($dateString, '+') !== false) {
                $date = new DateTime($dateString);
                $date->setTimezone(new DateTimeZone('Asia/Shanghai'));
                $date->setTime(23, 59, 59);
                return $date->format('Y-m-d H:i:s');
            }
            return $dateString . ' 23:59:59';
        } catch (Exception $e) {
            return (strpos($dateString, ' ') === false) ? $dateString . ' 23:59:59' : $dateString;
        }
    }

    private function resolveIbPartner(int $clientUserId, int $requestedIbPartnerId): array
    {
        $ibPartnerModel = new IbPartner();
        $ibPartners = $ibPartnerModel->getAllByClientId($clientUserId);
        $approvedPartners = array_values(array_filter($ibPartners, function ($ibPartner) {
            return ($ibPartner['status'] ?? '') === IbPartner::STATUS_APPROVED;
        }));
        if (empty($approvedPartners)) {
            throw new RuntimeException('IB partner not found');
        }

        $selectedId = (int)($approvedPartners[0]['id'] ?? 0);
        if ($requestedIbPartnerId > 0) {
            $selectedId = 0;
            foreach ($approvedPartners as $ibPartner) {
                if ((int)($ibPartner['id'] ?? 0) === $requestedIbPartnerId) {
                    $selectedId = $requestedIbPartnerId;
                    break;
                }
            }
            if ($selectedId <= 0) {
                throw new RuntimeException('IB partner not found');
            }
        }

        $ibPartner = $ibPartnerModel->findById($selectedId);
        if (!$ibPartner || (int)($ibPartner['userId'] ?? 0) !== $clientUserId || ($ibPartner['status'] ?? '') !== IbPartner::STATUS_APPROVED) {
            throw new RuntimeException('IB partner not found');
        }
        return $ibPartner;
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
        $percent = max(0, min(100, (int)($existing['percent'] ?? 0)));
        $processed = max(0, (int)($existing['processed'] ?? 0));
        $total = max(0, (int)($existing['total'] ?? 0));
        self::writeProgress($jobId, [
            'clientUserId' => $clientUserId,
            'status' => 'cancelled',
            'cancelRequested' => true,
            'percent' => $percent,
            'processed' => $processed,
            'total' => $total,
            'message' => 'Export cancelled',
            'file' => null,
        ]);
        self::clearActive($clientUserId);
    }

    /**
     * @param array $data type, jobId, clientUserId, filters, items, requestedAt
     */
    public function run(array $data): void
    {
        $jobId = (string)($data['jobId'] ?? '');
        $clientUserId = (int)($data['clientUserId'] ?? 0);
        $filters = is_array($data['filters'] ?? null) ? $data['filters'] : [];
        $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];

        if ($jobId === '' || $clientUserId <= 0) {
            Logger::error('export_deposit_withdraw_report invalid payload', ['data' => $data]);
            return;
        }

        self::ensureExportDir();
        $csvFile = self::csvPath($jobId);

        try {
            $existing = self::readProgress($jobId);
            if ($existing !== null
                && (!empty($existing['cancelRequested']) || ($existing['status'] ?? '') === 'cancelled')) {
                $this->cancelJob($jobId, $clientUserId, $csvFile);
                return;
            }

            $ibPartner = $this->resolveIbPartner($clientUserId, (int)($filters['ibPartnerId'] ?? 0));
            $ibPartnerId = (int)$ibPartner['id'];

            self::writeProgress($jobId, [
                'clientUserId' => $clientUserId,
                'status' => 'running',
                'cancelRequested' => false,
                'percent' => 0,
                'processed' => 0,
                'total' => 0,
                'message' => 'Export started',
                'file' => basename($csvFile),
            ]);

            $startDate = !empty($filters['start_date']) ? self::convertDateToServerTimezone($filters['start_date']) : null;
            $endDate = !empty($filters['end_date']) ? self::convertEndDateToServerTimezone($filters['end_date']) : null;
            $search = (string)($filters['search'] ?? '');

            $db = Database::getInstance();
            $controller = new ClientDepositWithdrawReportController();
            $rows = $controller->buildDetailRows($ibPartnerId, $startDate, $endDate, $search, $db);

            $selectedItems = array_slice($rawItems, 0, 500);
            if (!empty($selectedItems)) {
                $selectedKeys = [];
                foreach ($selectedItems as $sel) {
                    $sid = isset($sel['id']) ? (int)$sel['id'] : 0;
                    if ($sid <= 0) {
                        continue;
                    }
                    $stype = (string)($sel['type'] ?? '');
                    if ($stype === '') {
                        continue;
                    }
                    $selectedKeys[$stype . '#' . $sid] = true;
                }
                $rows = array_values(array_filter($rows, function ($row) use ($selectedKeys) {
                    $key = ($row['type'] ?? '') . '#' . (int)$row['id'];
                    return !empty($selectedKeys[$key]);
                }));
            }

            $total = count($rows);

            self::writeProgress($jobId, [
                'clientUserId' => $clientUserId,
                'status' => 'running',
                'cancelRequested' => false,
                'percent' => $total > 0 ? 1 : 0,
                'processed' => 0,
                'total' => $total,
                'message' => $total > 0 ? "Processing 0 of {$total} data left" : 'No data to export',
                'file' => basename($csvFile),
            ]);

            $fp = fopen($csvFile, 'w');
            if ($fp === false) {
                throw new RuntimeException('Failed to open CSV file for writing');
            }
            fwrite($fp, "\xEF\xBB\xBF");
            fputcsv($fp, self::CSV_HEADERS);

            if ($total === 0) {
                fclose($fp);
                self::writeProgress($jobId, [
                    'clientUserId' => $clientUserId,
                    'status' => 'done',
                    'cancelRequested' => false,
                    'percent' => 100,
                    'processed' => 0,
                    'total' => 0,
                    'message' => 'No data to export',
                    'downloadReady' => true,
                    'file' => basename($csvFile),
                ]);
                return;
            }

            $processed = 0;
            for ($offset = 0; $offset < $total; $offset += self::BATCH_SIZE) {
                $progress = self::readProgress($jobId);
                if (!empty($progress['cancelRequested'])) {
                    fclose($fp);
                    $this->cancelJob($jobId, $clientUserId, $csvFile);
                    return;
                }

                $chunk = array_slice($rows, $offset, self::BATCH_SIZE);
                foreach ($chunk as $item) {
                    $this->writeCsvRow($fp, [
                        'referralName' => (string)($item['referralName'] ?? '—'),
                        'typeLabel' => (string)($item['typeLabel'] ?? ''),
                        'direction' => (string)($item['direction'] ?? ''),
                        'date' => (string)($item['date'] ?? '—'),
                        'transactionId' => (string)($item['transactionId'] ?? '—'),
                        'accountNumber' => (string)($item['accountNumber'] ?? ''),
                        'currencyCode' => (string)($item['currencyCode'] ?? ''),
                        'amount' => number_format((float)($item['amount'] ?? 0), 2, '.', ''),
                        'status' => (string)($item['status'] ?? ''),
                        'handledAt' => (string)($item['handledAt'] ?? '—'),
                    ]);
                    $processed++;
                    $progress = self::readProgress($jobId);
                    if (!empty($progress['cancelRequested'])) {
                        fclose($fp);
                        $this->cancelJob($jobId, $clientUserId, $csvFile);
                        return;
                    }
                    $this->publishBatchProgress($jobId, $clientUserId, $processed, $total, $csvFile, $progress);
                }
                @fflush($fp);
            }

            fclose($fp);

            $progress = self::readProgress($jobId);
            if (!empty($progress['cancelRequested'])) {
                $this->cancelJob($jobId, $clientUserId, $csvFile);
                return;
            }

            self::writeProgress($jobId, [
                'clientUserId' => $clientUserId,
                'status' => 'done',
                'cancelRequested' => false,
                'percent' => 100,
                'processed' => $processed,
                'total' => $total,
                'message' => 'Export ready',
                'downloadReady' => true,
                'file' => basename($csvFile),
            ]);
        } catch (Throwable $e) {
            Logger::error('export_deposit_withdraw_report failed', [
                'jobId' => $jobId,
                'clientUserId' => $clientUserId,
                'error' => $e->getMessage(),
            ]);
            if (isset($fp) && is_resource($fp)) {
                @fclose($fp);
            }
            $this->failJob($jobId, $clientUserId, $e->getMessage(), $csvFile);
        }
    }

    private function publishBatchProgress(
        string $jobId,
        int $clientUserId,
        int $processed,
        int $total,
        string $csvFile,
        ?array $lastProgress
    ): void {
        $percent = $total > 0 ? (int)min(99, max(1, floor(($processed / $total) * 100))) : 0;
        self::writeProgress($jobId, [
            'clientUserId' => $clientUserId,
            'status' => 'running',
            'cancelRequested' => !empty($lastProgress['cancelRequested']),
            'percent' => $percent,
            'processed' => $processed,
            'total' => $total,
            'message' => "Processing {$processed} of {$total} data left",
            'file' => basename($csvFile),
        ]);
    }

    private function writeCsvRow($fp, array $row): void
    {
        $line = [];
        foreach (self::CSV_FIELDS as $field) {
            $line[] = $row[$field] ?? '';
        }
        fputcsv($fp, $line);
    }
}
