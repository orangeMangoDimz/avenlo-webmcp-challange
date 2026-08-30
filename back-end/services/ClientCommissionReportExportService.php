<?php
/**
 * Async batched commission report CSV export (myswoole worker).
 * Progress + active pointer under storage/exports/; cooperative cancel between batches.
 */

require_once __DIR__ . '/../models/IbCommissionOrder.php';
require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/../models/IbPartnerBind.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../controllers/ClientCommissionReportController.php';

class ClientCommissionReportExportService
{
    private const BATCH_SIZE = 50;
    private const BREAKDOWN_ROWS_PER_CLIENT_CAP = 1000;

    private const CSV_HEADERS = [
        'Referral Name', 'Referral Email', 'Referral Code', 'Type', 'Trading Volume', 'Commission Earned', 'Payment Status',
        'Date', 'Account Number', 'Name', 'Account Owner',
        'Email', 'KYC', 'Trade Date', 'ID', 'Symbol',
        'Lots', 'Last Deposit Time', 'Amount', 'Platform',
        'Account Type', 'Base Currency', 'Balance', 'Profit/Loss',
        'Margin Level', 'Account Equity', 'Credit', 'Status',
    ];

    private const CSV_FIELDS = [
        'referralName', 'summaryEmail', 'referralCode', 'type', 'tradingVolume', 'commissionEarned', 'summaryStatus',
        'bdDate', 'bdAccountNumber', 'bdName', 'bdAccountOwner',
        'bdEmail', 'bdKyc', 'bdTradeDate', 'bdTradingId', 'bdSymbol',
        'bdLots', 'bdLastDepositTime', 'bdAmount', 'bdPlatform',
        'bdAccountType', 'bdBaseCurrency', 'bdBalance', 'bdProfitLoss',
        'bdMarginLevel', 'bdAccountEquity', 'bdCredit', 'bdStatus',
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
        return self::exportDir() . '/active_' . (int)$clientUserId . '.json';
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

    /**
     * Active job for user if status is queued|running|cancelling|done.
     */
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

    /**
     * Resolve approved IB for clientUserId (no HTTP / Response).
     */
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

    private function isTopLevelIb(int $ibPartnerId): bool
    {
        $bindModel = new IbPartnerBind();
        $parent = $bindModel->getParentIbPartner($ibPartnerId);
        return empty($parent) || empty($parent['parentIbPartnerId']);
    }

    private function getCurrentIbDisplayInfo(int $ibPartnerId, Database $db): ?array
    {
        if ($ibPartnerId <= 0) {
            return null;
        }
        $sql = "SELECT ib.id, ib.ibCode, ib.companyName, ib.contactEmail, ib.userId,
                TRIM(CONCAT(COALESCE(cu.firstName,''), ' ', COALESCE(cu.lastName,''))) AS userFullName,
                cu.email AS userEmail
                FROM ibPartners ib
                LEFT JOIN clientUsers cu ON cu.id = ib.userId
                WHERE ib.id = :id
                LIMIT 1";
        $row = $db->fetchOne($sql, ['id' => $ibPartnerId]);
        if (!$row) {
            return null;
        }
        $name = trim((string)($row['userFullName'] ?? ''));
        if ($name === '') {
            $name = trim((string)($row['companyName'] ?? ''));
        }
        $email = $row['userEmail'] ?? ($row['contactEmail'] ?? '');
        return [
            'id' => $ibPartnerId,
            'userId' => (int)($row['userId'] ?? 0),
            'name' => $name !== '' ? $name : ($email !== '' ? $email : '—'),
            'email' => $email,
            'referralCode' => (string)($row['ibCode'] ?? ''),
        ];
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
     * Swoole entry: build CSV in batches of 50 referrals.
     *
     * @param array $data type, jobId, clientUserId, filters, items, requestedAt
     */
    public function run(array $data): void
    {
        $jobId = (string)($data['jobId'] ?? '');
        $clientUserId = (int)($data['clientUserId'] ?? 0);
        $filters = is_array($data['filters'] ?? null) ? $data['filters'] : [];
        $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];

        if ($jobId === '' || $clientUserId <= 0) {
            Logger::error('export_commission_report invalid payload', ['data' => $data]);
            return;
        }

        self::ensureExportDir();
        $csvFile = self::csvPath($jobId);

        try {
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
            $params = [
                'ibPartnerId' => $ibPartnerId,
                'ibPartnerId2' => $ibPartnerId,
                'ibPartnerId3' => $ibPartnerId,
            ];
            if ($startDate) {
                $params['startDate'] = $startDate;
            }
            if ($endDate) {
                $params['endDate'] = $endDate;
            }
            if ($search !== '') {
                $params['search'] = '%' . $search . '%';
            }

            $selectedItems = array_slice($rawItems, 0, 500);
            $selClientWhere = '';
            $selSubIbWhere = '';
            if (!empty($selectedItems)) {
                $selClientIds = [];
                $selSubIbIds = [];
                foreach ($selectedItems as $sel) {
                    $sid = isset($sel['id']) ? (int)$sel['id'] : 0;
                    if ($sid <= 0) {
                        continue;
                    }
                    $stype = $sel['type'] ?? '';
                    if ($stype === 'Direct Client') {
                        $selClientIds[] = $sid;
                    } elseif ($stype === 'Sub-IB') {
                        $selSubIbIds[] = $sid;
                    }
                }
                if (!empty($selClientIds)) {
                    $ph = [];
                    foreach (array_values(array_unique($selClientIds)) as $i => $cid) {
                        $ph[] = ":selClient{$i}";
                        $params["selClient{$i}"] = $cid;
                    }
                    $selClientWhere = ' AND cu.id IN (' . implode(',', $ph) . ')';
                } else {
                    $selClientWhere = ' AND 1=0';
                }
                if (!empty($selSubIbIds)) {
                    $ph = [];
                    foreach (array_values(array_unique($selSubIbIds)) as $i => $bid) {
                        $ph[] = ":selSub{$i}";
                        $params["selSub{$i}"] = $bid;
                    }
                    $selSubIbWhere = ' AND ib.id IN (' . implode(',', $ph) . ')';
                } else {
                    $selSubIbWhere = ' AND 1=0';
                }
            }

            $dateWhere = '1=1';
            if ($startDate) {
                $dateWhere .= ' AND ico.orderDate >= :startDate';
            }
            if ($endDate) {
                $dateWhere .= ' AND ico.orderDate <= :endDate';
            }
            $searchWhereClients = $search !== ''
                ? " AND (CONCAT(COALESCE(cu.firstName,''), ' ', COALESCE(cu.lastName,'')) LIKE :search OR cu.email LIKE :search)"
                : '';
            $searchWhereSub = $search !== ''
                ? ' AND (ib.companyName LIKE :search OR ib.ibCode LIKE :search OR ib.contactEmail LIKE :search)'
                : '';
            $searchWhereSelf = $search !== ''
                ? " AND (TRIM(CONCAT(COALESCE(cu_self.firstName,''), ' ', COALESCE(cu_self.lastName,''))) LIKE :search OR cu_self.email LIKE :search OR ib.companyName LIKE :search OR ib.ibCode LIKE :search OR ib.contactEmail LIKE :search)"
                : '';

            $clientsSql = "SELECT cu.id AS clientId, CONCAT(COALESCE(cu.firstName,''), ' ', COALESCE(cu.lastName,'')) AS referralName, cu.email, cu.id AS referralCode, 'Direct Client' AS type, SUM(ico.commission) AS commissionEarned, 0 AS tradingVolume, MAX(CASE WHEN ico.status = 'completed' THEN 1 ELSE 0 END) AS hasPaid, MAX(CASE WHEN ico.status IN ('pending','approved') THEN 1 ELSE 0 END) AS hasPending FROM ib_commission_order ico LEFT JOIN deposits d ON ico.depositId = d.id LEFT JOIN orders o ON ico.orderId = o.id LEFT JOIN tradingAccountExternalAccounts taea ON taea.providerAccountId = o.trading_login LEFT JOIN tradingAccounts ta ON ta.id = taea.tradingAccountId INNER JOIN clientUsers cu ON cu.id = COALESCE(d.userId, ta.userId) WHERE ico.ibPartnerId = :ibPartnerId AND ico.status != 'cancelled' AND (ico.depositId IS NOT NULL OR ico.orderId IS NOT NULL) AND COALESCE(d.userId, ta.userId) IN (SELECT childClientId FROM ib_partner_bind WHERE parentId = :ibPartnerId2 AND isClient = 1) AND {$dateWhere} {$searchWhereClients}{$selClientWhere} GROUP BY cu.id, cu.firstName, cu.lastName, cu.email";
            $subIbsSql = "SELECT ib.id AS clientId, ib.companyName AS referralName, ib.contactEmail AS email, ib.ibCode AS referralCode, 'Sub-IB' AS type, SUM(ico.commission) AS commissionEarned, 0 AS tradingVolume, MAX(CASE WHEN ico.status = 'completed' THEN 1 ELSE 0 END) AS hasPaid, MAX(CASE WHEN ico.status IN ('pending','approved') THEN 1 ELSE 0 END) AS hasPending FROM ib_commission_order ico INNER JOIN ib_partner_bind b ON b.childId = ico.ibPartnerId AND b.parentId = :ibPartnerId3 AND b.isClient = 0 INNER JOIN ibPartners ib ON ib.id = ico.ibPartnerId WHERE ico.status != 'cancelled' AND {$dateWhere} {$searchWhereSub}{$selSubIbWhere} GROUP BY ib.id, ib.companyName, ib.contactEmail, ib.ibCode";
            $queries = ["({$clientsSql})", "({$subIbsSql})"];

            $isTopLevelIb = $this->isTopLevelIb($ibPartnerId);
            if ($isTopLevelIb) {
                $params['ibPartnerIdSelf'] = $ibPartnerId;
                $selfSql = "SELECT ib.id AS clientId,
                    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(cu_self.firstName,''), ' ', COALESCE(cu_self.lastName,''))), ''), ib.companyName, COALESCE(cu_self.email, ib.contactEmail)) AS referralName,
                    COALESCE(cu_self.email, ib.contactEmail) AS email,
                    ib.ibCode AS referralCode,
                    'Sub-IB' AS type,
                    SUM(ico.commission) AS commissionEarned,
                    0 AS tradingVolume,
                    MAX(CASE WHEN ico.status = 'completed' THEN 1 ELSE 0 END) AS hasPaid,
                    MAX(CASE WHEN ico.status IN ('pending','approved') THEN 1 ELSE 0 END) AS hasPending
                    FROM ib_commission_order ico
                    INNER JOIN ibPartners ib ON ib.id = ico.ibPartnerId
                    LEFT JOIN clientUsers cu_self ON cu_self.id = ib.userId
                    WHERE ico.ibPartnerId = :ibPartnerIdSelf AND ico.status != 'cancelled' AND {$dateWhere} {$searchWhereSelf}{$selSubIbWhere}
                    GROUP BY ib.id, ib.companyName, ib.contactEmail, ib.ibCode, cu_self.firstName, cu_self.lastName, cu_self.email";
                $queries[] = "({$selfSql})";
            }

            $unionSql = implode(' UNION ALL ', $queries) . ' ORDER BY commissionEarned DESC';
            $summaryItems = $db->fetchAll($unionSql, $params);
            $total = count($summaryItems);

            // Publish total ASAP so clients don't sit on "Export started (0%)" during long first batches
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
            // UTF-8 BOM for Excel
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
                // Keep active pointer until download / start-new
                return;
            }

            $breakdownFilters = [];
            if ($startDate) {
                $breakdownFilters['startDate'] = $startDate;
            }
            if ($endDate) {
                $breakdownFilters['endDate'] = $endDate;
            }

            $selfUserId = 0;
            if ($isTopLevelIb) {
                $selfInfo = $this->getCurrentIbDisplayInfo($ibPartnerId, $db);
                $selfUserId = (int)($selfInfo['userId'] ?? 0);
            }

            $blankDetailRow = [
                'bdDate' => '', 'bdAccountNumber' => '', 'bdName' => '', 'bdAccountOwner' => '',
                'bdEmail' => '', 'bdKyc' => '', 'bdTradeDate' => '', 'bdTradingId' => '',
                'bdSymbol' => '', 'bdLots' => '', 'bdLastDepositTime' => '', 'bdAmount' => '',
                'bdPlatform' => '', 'bdAccountType' => '', 'bdBaseCurrency' => '', 'bdBalance' => '',
                'bdProfitLoss' => '', 'bdMarginLevel' => '', 'bdAccountEquity' => '', 'bdCredit' => '',
                'bdStatus' => '',
            ];

            $commissionOrderModel = new IbCommissionOrder();
            $processed = 0;
            // Update progress every referral so 7s polls see movement (first batch alone can take minutes)
            $progressEvery = 1;

            for ($offset = 0; $offset < $total; $offset += self::BATCH_SIZE) {
                $progress = self::readProgress($jobId);
                if (!empty($progress['cancelRequested'])) {
                    fclose($fp);
                    $this->cancelJob($jobId, $clientUserId, $csvFile);
                    return;
                }

                $chunk = array_slice($summaryItems, $offset, self::BATCH_SIZE);
                foreach ($chunk as $item) {
                    $status = 'Paid';
                    if (!empty($item['hasPending']) && empty($item['hasPaid'])) {
                        $status = 'Pending';
                    } elseif (!empty($item['hasPending']) && !empty($item['hasPaid'])) {
                        $status = 'Processing';
                    }

                    $summaryRow = [
                        'referralName' => $item['referralName'],
                        'summaryEmail' => $item['email'],
                        'referralCode' => $item['referralCode'],
                        'type' => $item['type'],
                        'tradingVolume' => (float)$item['tradingVolume'],
                        'commissionEarned' => (float)$item['commissionEarned'],
                        'summaryStatus' => $status,
                    ];

                    $itemClientId = (int)$item['clientId'];
                    $isSelfRow = $isTopLevelIb && ($itemClientId === $ibPartnerId);

                    try {
                        if ($item['type'] === 'Direct Client') {
                            $commissionList = $commissionOrderModel->getCommissionListByClient(
                                $ibPartnerId, $itemClientId, 1, self::BREAKDOWN_ROWS_PER_CLIENT_CAP, $breakdownFilters
                            );
                        } elseif ($isSelfRow && $selfUserId > 0) {
                            $commissionList = $commissionOrderModel->getCommissionListByClient(
                                $ibPartnerId, $selfUserId, 1, self::BREAKDOWN_ROWS_PER_CLIENT_CAP, $breakdownFilters
                            );
                        } else {
                            $commissionList = $commissionOrderModel->getIbCommissionList(
                                $itemClientId, 1, self::BREAKDOWN_ROWS_PER_CLIENT_CAP, $breakdownFilters
                            );
                        }
                    } catch (Exception $e) {
                        Logger::error('export breakdown fetch failed, skipping client', [
                            'jobId' => $jobId,
                            'clientId' => $itemClientId,
                            'type' => $item['type'],
                            'error' => $e->getMessage(),
                        ]);
                        $this->writeCsvRow($fp, array_merge($summaryRow, $blankDetailRow));
                        $processed++;
                        if ($processed % $progressEvery === 0 || $processed >= $total) {
                            $progress = self::readProgress($jobId);
                            if (!empty($progress['cancelRequested'])) {
                                fclose($fp);
                                $this->cancelJob($jobId, $clientUserId, $csvFile);
                                return;
                            }
                            $this->publishBatchProgress($jobId, $clientUserId, $processed, $total, $csvFile, $progress);
                        }
                        continue;
                    }

                    if ((int)($commissionList['total'] ?? 0) > self::BREAKDOWN_ROWS_PER_CLIENT_CAP) {
                        Logger::error('export breakdown truncated', [
                            'jobId' => $jobId,
                            'clientId' => $itemClientId,
                            'type' => $item['type'],
                            'total' => $commissionList['total'],
                            'cap' => self::BREAKDOWN_ROWS_PER_CLIENT_CAP,
                        ]);
                    }

                    $detailRows = ClientCommissionReportController::formatClientDetailOrders($commissionList['items'] ?? []);
                    if (empty($detailRows)) {
                        $this->writeCsvRow($fp, array_merge($summaryRow, $blankDetailRow));
                    } else {
                        foreach ($detailRows as $detailRow) {
                            $this->writeCsvRow($fp, array_merge($summaryRow, ClientCommissionReportController::mapDetailToBd($detailRow)));
                        }
                    }
                    $processed++;
                    if ($processed % $progressEvery === 0 || $processed >= $total) {
                        $progress = self::readProgress($jobId);
                        if (!empty($progress['cancelRequested'])) {
                            fclose($fp);
                            $this->cancelJob($jobId, $clientUserId, $csvFile);
                            return;
                        }
                        $this->publishBatchProgress($jobId, $clientUserId, $processed, $total, $csvFile, $progress);
                        @fflush($fp);
                    }
                }
            }

            fclose($fp);

            // Final cancel check
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
            // Keep active_{clientUserId}.json until download or start-new
        } catch (Throwable $e) {
            Logger::error('export_commission_report failed', [
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
            $value = $row[$field] ?? '';
            if (is_float($value) || is_int($value)) {
                $line[] = $value;
            } else {
                $line[] = (string)$value;
            }
        }
        fputcsv($fp, $line);
    }
}
