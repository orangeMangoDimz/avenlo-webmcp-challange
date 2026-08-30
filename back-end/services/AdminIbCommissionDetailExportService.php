<?php

require_once __DIR__ . '/../models/IbCommissionOrder.php';
require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/../models/IbPartnerBind.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../controllers/ClientCommissionReportController.php';
require_once __DIR__ . '/AdminOperationLogWriter.php';
require_once __DIR__ . '/OperationLogPages.php';

class AdminIbCommissionDetailExportService
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
        return self::exportDir() . '/active_admin_ibcd_' . (int)$adminUserId . '.json';
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

    private static function syncBackgroundJobProgress(string $jobId, array $data): void
    {
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
        require_once __DIR__ . '/ExportJobTimeoutReaper.php';
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
        $percent = max(0, min(100, (int)($existing['percent'] ?? 0)));
        $processed = max(0, (int)($existing['processed'] ?? 0));
        $total = max(0, (int)($existing['total'] ?? 0));
        self::writeProgress($jobId, [
            'adminUserId' => $adminUserId,
            'status' => 'cancelled',
            'cancelRequested' => true,
            'percent' => $percent,
            'processed' => $processed,
            'total' => $total,
            'message' => 'Export cancelled',
            'file' => null,
        ]);
        self::clearActive($adminUserId);
    }

    private function isCancelRequested(string $jobId): bool
    {
        $progress = self::readProgress($jobId);
        return !empty($progress['cancelRequested']);
    }

    private function mergeCancelRequested(string $jobId, ?array $hint = null): bool
    {
        if (!empty($hint['cancelRequested'])) {
            return true;
        }
        return $this->isCancelRequested($jobId);
    }

    private function writeRunningProgress(string $jobId, int $adminUserId, array $fields): bool
    {
        $cancelRequested = $this->mergeCancelRequested($jobId, $fields);
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
        ?array $lastProgress = null,
        string $messagePrefix = 'Processing'
    ): bool {
        $percent = $total > 0 ? (int)min(99, max(1, floor(($processed / $total) * 100))) : 0;
        return $this->writeRunningProgress($jobId, $adminUserId, [
            'percent' => $percent,
            'processed' => $processed,
            'total' => $total,
            'message' => "{$messagePrefix} {$processed} of {$total}",
            'file' => basename($csvFile),
            'cancelRequested' => $this->mergeCancelRequested($jobId, $lastProgress),
        ]);
    }

    private function finishDone(
        string $jobId,
        int $adminUserId,
        int $processed,
        int $total,
        string $csvFile,
        string $message = 'Export ready'
    ): void {
        self::writeProgress($jobId, [
            'adminUserId' => $adminUserId,
            'status' => 'done',
            'cancelRequested' => false,
            'percent' => 100,
            'processed' => $processed,
            'total' => $total,
            'message' => $message,
            'downloadReady' => true,
            'file' => basename($csvFile),
        ]);
    }

    private function writeEmptyCsv(string $csvFile, array $identityHeaders): void
    {
        $fp = fopen($csvFile, 'w');
        if ($fp === false) {
            throw new RuntimeException('Failed to open CSV file for writing');
        }
        fwrite($fp, "\xEF\xBB\xBF");
        fputcsv($fp, array_merge($identityHeaders, ClientCommissionReportController::detailExportHeaders()));
        fclose($fp);
    }

    private function runAllIbs(string $jobId, int $adminUserId, array $filters, string $csvFile): void
    {
        $identityHeaders = ['IB Code', 'IB Name'];

        if (!empty($filters['scope_none'])) {
            $this->writeEmptyCsv($csvFile, $identityHeaders);
            $this->finishDone($jobId, $adminUserId, 0, 0, $csvFile, 'No data to export');
            return;
        }

        $listFilters = [];
        if (!empty($filters['startDate'])) {
            $listFilters['startDate'] = $filters['startDate'];
        } elseif (!empty($filters['start_date'])) {
            $listFilters['startDate'] = self::convertDateToServerTimezone($filters['start_date']);
        }
        if (!empty($filters['endDate'])) {
            $listFilters['endDate'] = $filters['endDate'];
        } elseif (!empty($filters['end_date'])) {
            $listFilters['endDate'] = self::convertEndDateToServerTimezone($filters['end_date']);
        }
        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $listFilters['status'] = $filters['status'];
        }
        if (isset($filters['search']) && trim((string)$filters['search']) !== '') {
            $listFilters['search'] = trim((string)$filters['search']);
        }
        if (isset($filters['sort']) && $filters['sort'] !== '' && $filters['sort'] !== null) {
            $listFilters['sort'] = $filters['sort'];
        }
        if (!empty($filters['restrict_to_sales_id'])) {
            $listFilters['restrict_to_sales_id'] = (int)$filters['restrict_to_sales_id'];
        }

        $ibPartnerModel = new IbPartner();
        $ibPartnerBindModel = new IbPartnerBind();
        $commissionOrderModel = new IbCommissionOrder();

        $ibPartnerModel->syncAllTotalClientsFromBindTable();
        $clientCountsMap = $ibPartnerBindModel->getClientCountsMapRecursive();
        $result = $commissionOrderModel->getAllIbCommissionReport(1, 999999, $listFilters, $clientCountsMap);
        $items = $result['items'] ?? [];
        $total = count($items);

        self::writeProgress($jobId, [
            'adminUserId' => $adminUserId,
            'status' => 'running',
            'cancelRequested' => false,
            'percent' => $total > 0 ? 1 : 0,
            'processed' => 0,
            'total' => $total,
            'message' => $total > 0 ? "Processing 0 of {$total}" : 'No data to export',
            'file' => basename($csvFile),
        ]);

        $detailFilters = [];
        if (!empty($listFilters['startDate'])) {
            $detailFilters['startDate'] = $listFilters['startDate'];
        }
        if (!empty($listFilters['endDate'])) {
            $detailFilters['endDate'] = $listFilters['endDate'];
        }

        $fp = fopen($csvFile, 'w');
        if ($fp === false) {
            throw new RuntimeException('Failed to open CSV file for writing');
        }
        fwrite($fp, "\xEF\xBB\xBF");
        fputcsv($fp, array_merge($identityHeaders, ClientCommissionReportController::detailExportHeaders()));

        if ($total === 0) {
            fclose($fp);
            $this->finishDone($jobId, $adminUserId, 0, 0, $csvFile, 'No data to export');
            $this->logAllIbsExport($adminUserId, 0, $listFilters);
            return;
        }

        $rowCount = 0;
        $processed = 0;
        foreach ($items as $ib) {
            if ($this->isCancelRequested($jobId)) {
                fclose($fp);
                $this->cancelJob($jobId, $adminUserId, $csvFile);
                return;
            }

            $ibId = (int)($ib['ibPartnerId'] ?? 0);
            if ($ibId <= 0) {
                $processed++;
                if ($this->publishProgress($jobId, $adminUserId, $processed, $total, $csvFile)) {
                    fclose($fp);
                    $this->cancelJob($jobId, $adminUserId, $csvFile);
                    return;
                }
                continue;
            }

            $identity = [(string)($ib['ibCode'] ?? ''), (string)($ib['displayName'] ?? $ib['companyName'] ?? '')];
            $list = $commissionOrderModel->getIbCommissionList($ibId, 1, 1000000, $detailFilters);
            if ($this->isCancelRequested($jobId)) {
                fclose($fp);
                $this->cancelJob($jobId, $adminUserId, $csvFile);
                return;
            }
            foreach (ClientCommissionReportController::formatClientDetailOrders($list['items'] ?? []) as $detailRow) {
                fputcsv($fp, array_merge($identity, ClientCommissionReportController::detailExportValues($detailRow)));
                $rowCount++;
            }

            $processed++;
            if ($this->publishProgress($jobId, $adminUserId, $processed, $total, $csvFile)) {
                fclose($fp);
                $this->cancelJob($jobId, $adminUserId, $csvFile);
                return;
            }
            @fflush($fp);
        }

        fclose($fp);

        if ($this->isCancelRequested($jobId)) {
            $this->cancelJob($jobId, $adminUserId, $csvFile);
            return;
        }

        $this->finishDone($jobId, $adminUserId, $processed, $total, $csvFile);
        $this->logAllIbsExport($adminUserId, $rowCount, $listFilters);
    }

    private function logAllIbsExport(int $adminUserId, int $count, array $filters): void
    {
        $range = '';
        if (!empty($filters['startDate']) || !empty($filters['endDate'])) {
            $range = ($filters['startDate'] ?? '…') . ' ~ ' . ($filters['endDate'] ?? '…');
        }
        $writer = new AdminOperationLogWriter();
        $writer->record([
            'modelKey' => 'log_report',
            'subModuleKey' => OperationLogPages::subModuleKeyByAlias('page_ib_report'),
            'operationTypeKey' => 'export',
            'detailZh' => '导出 IB 佣金明细，共 ' . $count . ' 行' . ($range !== '' ? '，日期：' . $range : ''),
            'detailEn' => 'Exported IB commission detail, ' . $count . ' row(s)' . ($range !== '' ? ', date: ' . $range : ''),
            'targetId' => null,
            'operatorId' => $adminUserId,
        ]);
    }

    private function runSingleIb(string $jobId, int $adminUserId, array $filters, string $csvFile): void
    {
        $ibPartnerId = (int)($filters['ibPartnerId'] ?? 0);
        if ($ibPartnerId <= 0) {
            throw new RuntimeException('ibPartnerId is required');
        }

        $ibPartnerModel = new IbPartner();
        if (!$ibPartnerModel->findById($ibPartnerId)) {
            throw new RuntimeException('IB partner not found');
        }

        $startDate = !empty($filters['start_date'])
            ? self::convertDateToServerTimezone($filters['start_date'])
            : (!empty($filters['startDate']) ? $filters['startDate'] : null);
        $endDate = !empty($filters['end_date'])
            ? self::convertEndDateToServerTimezone($filters['end_date'])
            : (!empty($filters['endDate']) ? $filters['endDate'] : null);
        $search = trim((string)($filters['search'] ?? ''));

        $identityHeaders = ['Referral Name', 'Referral Code', 'Type'];
        $controller = new ClientCommissionReportController();

        if ($this->writeRunningProgress($jobId, $adminUserId, [
            'percent' => 1,
            'processed' => 0,
            'total' => 0,
            'message' => 'Loading referrals',
            'file' => basename($csvFile),
        ])) {
            $this->cancelJob($jobId, $adminUserId, $csvFile);
            return;
        }

        $referrals = $controller->listDetailExportReferrals($ibPartnerId, $startDate, $endDate, $search);
        $total = count($referrals);

        $detailFilters = [];
        if ($startDate) {
            $detailFilters['startDate'] = $startDate;
        }
        if ($endDate) {
            $detailFilters['endDate'] = $endDate;
        }

        $fp = fopen($csvFile, 'w');
        if ($fp === false) {
            throw new RuntimeException('Failed to open CSV file for writing');
        }
        fwrite($fp, "\xEF\xBB\xBF");
        fputcsv($fp, array_merge($identityHeaders, ClientCommissionReportController::detailExportHeaders()));

        if ($total === 0) {
            fclose($fp);
            if ($this->isCancelRequested($jobId)) {
                $this->cancelJob($jobId, $adminUserId, $csvFile);
                return;
            }
            $this->finishDone($jobId, $adminUserId, 0, 0, $csvFile, 'No data to export');
            return;
        }

        if ($this->writeRunningProgress($jobId, $adminUserId, [
            'percent' => 1,
            'processed' => 0,
            'total' => $total,
            'message' => "Processing 0 of {$total}",
            'file' => basename($csvFile),
        ])) {
            fclose($fp);
            $this->cancelJob($jobId, $adminUserId, $csvFile);
            return;
        }

        $processed = 0;
        foreach ($referrals as $ref) {
            if ($this->isCancelRequested($jobId)) {
                fclose($fp);
                $this->cancelJob($jobId, $adminUserId, $csvFile);
                return;
            }

            $records = $controller->fetchDetailRecordsForReferral($ibPartnerId, $ref, $detailFilters);
            if ($this->isCancelRequested($jobId)) {
                fclose($fp);
                $this->cancelJob($jobId, $adminUserId, $csvFile);
                return;
            }
            foreach ($records as $rec) {
                fputcsv($fp, array_merge($rec['identity'] ?? [], ClientCommissionReportController::detailExportValues($rec['detail'] ?? [])));
            }

            $processed++;
            if ($this->publishProgress($jobId, $adminUserId, $processed, $total, $csvFile)) {
                fclose($fp);
                $this->cancelJob($jobId, $adminUserId, $csvFile);
                return;
            }
            @fflush($fp);
        }

        fclose($fp);

        if ($this->isCancelRequested($jobId)) {
            $this->cancelJob($jobId, $adminUserId, $csvFile);
            return;
        }

        $this->finishDone(
            $jobId,
            $adminUserId,
            $processed,
            $total,
            $csvFile,
            'Export ready'
        );
    }

    public function run(array $data): void
    {
        $jobId = (string)($data['jobId'] ?? '');
        $adminUserId = (int)($data['adminUserId'] ?? 0);
        $scope = (string)($data['scope'] ?? '');
        $filters = is_array($data['filters'] ?? null) ? $data['filters'] : [];

        if ($jobId === '' || $adminUserId <= 0 || !in_array($scope, ['all_ibs', 'single_ib'], true)) {
            Logger::error('export_admin_ib_commission_detail invalid payload', ['data' => $data]);
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
            ])) {
                $this->cancelJob($jobId, $adminUserId, $csvFile);
                return;
            }

            if ($scope === 'all_ibs') {
                $this->runAllIbs($jobId, $adminUserId, $filters, $csvFile);
            } else {
                $this->runSingleIb($jobId, $adminUserId, $filters, $csvFile);
            }
        } catch (Throwable $e) {
            Logger::error('export_admin_ib_commission_detail failed', [
                'jobId' => $jobId,
                'adminUserId' => $adminUserId,
                'scope' => $scope,
                'error' => $e->getMessage(),
            ]);
            $this->failJob($jobId, $adminUserId, $e->getMessage(), $csvFile);
        }
    }
}
