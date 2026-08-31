<?php

require_once __DIR__ . '/../controllers/WebMcpClientController.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/ExportJobTimeoutReaper.php';
require_once __DIR__ . '/ExportProgressGuard.php';

/**
 * Async exports started by the admin WebMCP tools.
 *
 * The job contains only normalized filters and an admin owner. The worker
 * re-queries the database with the saved sales scope before writing the file.
 */
class WebMcpClientExportService
{
    private const BATCH_SIZE = 100;
    private const MAX_TRANSACTION_ROWS = 100000;

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
        return self::exportDir() . '/progress_' . self::safeJobId($jobId) . '.json';
    }

    public static function activePath(int $adminUserId): string
    {
        return self::exportDir() . '/active_wmcp_' . (int)$adminUserId . '.json';
    }

    public static function csvPath(string $jobId): string
    {
        return self::exportDir() . '/' . self::safeJobId($jobId) . '.xls';
    }

    public static function readProgress(string $jobId): ?array
    {
        if (!self::isSafeJobId($jobId)) {
            return null;
        }
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
            throw new RuntimeException('Unable to write export progress.');
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                throw new RuntimeException('Unable to lock export progress.');
            }
            rewind($fp);
            $existing = json_decode((string)stream_get_contents($fp), true);
            if (!is_array($existing)) {
                $existing = [];
            }
            foreach (['inputFingerprint', 'completedAt', 'downloadRequestedAt', 'downloadRequestCount'] as $key) {
                if (!array_key_exists($key, $data) && array_key_exists($key, $existing)) {
                    $data[$key] = $existing[$key];
                }
            }
            $data['jobId'] = $jobId;
            $data['updatedAt'] = date('Y-m-d H:i:s');
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new RuntimeException('Unable to encode export progress.');
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
        } catch (Throwable $exception) {
            // Progress persistence must not fail an otherwise valid export.
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
        if (!in_array($status, ['queued', 'running', 'done'], true)) {
            self::clearActive($adminUserId);
            return null;
        }
        return $progress;
    }

    /**
     * Worker entry point.
     *
     * @param array $data type, jobId, exportType, adminUserId, input, scope
     */
    public function run(array $data): void
    {
        $jobId = trim((string)($data['jobId'] ?? ''));
        $adminUserId = (int)($data['adminUserId'] ?? 0);
        $exportType = (string)($data['exportType'] ?? '');
        $scope = is_array($data['scope'] ?? null) ? $data['scope'] : [];
        $rawInput = is_array($data['input'] ?? null) ? $data['input'] : [];

        if (!self::isSafeJobId($jobId) || $adminUserId <= 0 || !in_array($exportType, ['clients', 'transactions'], true)) {
            Logger::error('export_webmcp_client invalid payload', [
                'jobId' => $jobId,
                'adminUserId' => $adminUserId,
                'exportType' => $exportType,
            ]);
            return;
        }

        $csvFile = self::csvPath($jobId);
        $fp = null;
        try {
            $input = $exportType === 'clients'
                ? WebMcpClientController::normalizeExportClientInput($rawInput)
                : (array_key_exists('clientIds', $rawInput)
                    ? WebMcpClientController::normalizeExportTransactionInput($rawInput)
                    : WebMcpClientController::normalizeExportTransactionsInput($rawInput));

            self::writeProgress($jobId, [
                'adminUserId' => $adminUserId,
                'exportType' => $exportType,
                'status' => 'running',
                'percent' => 0,
                'processed' => 0,
                'total' => 0,
                'message' => 'Preparing export',
                'downloadReady' => false,
                'file' => basename($csvFile),
                'fileName' => (string)($data['fileName'] ?? ''),
            ]);

            $rows = $exportType === 'clients'
                ? $this->fetchClientRows($input, $scope)
                : $this->fetchTransactionRows($input, $scope);
            $total = count($rows);

            self::writeProgress($jobId, [
                'adminUserId' => $adminUserId,
                'exportType' => $exportType,
                'status' => 'running',
                'percent' => $total > 0 ? 1 : 0,
                'processed' => 0,
                'total' => $total,
                'message' => $total > 0 ? "Processing 0 of {$total}" : 'No data to export',
                'downloadReady' => false,
                'file' => basename($csvFile),
                'fileName' => (string)($data['fileName'] ?? ''),
            ]);

            self::ensureExportDir();
            $fp = @fopen($csvFile, 'wb');
            if ($fp === false) {
                throw new RuntimeException('Unable to create export file.');
            }
            fwrite($fp, "\xEF\xBB\xBF");
            fputcsv($fp, $exportType === 'clients'
                ? ['ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Country', 'Status', 'KYC Status', 'Manager', 'Tags', 'Registered At', 'Last Login']
                : ['Client ID', 'Client Name', 'Client Email', 'Transaction ID', 'Type', 'Status', 'Amount', 'Currency', 'Transaction Date']
            );

            $processed = 0;
            foreach ($rows as $row) {
                if ($exportType === 'clients') {
                    fputcsv($fp, $this->clientCsvRow($row));
                } else {
                    fputcsv($fp, $this->transactionCsvRow($row));
                }
                $processed++;

                if ($processed % self::BATCH_SIZE === 0 || $processed === $total) {
                    self::writeProgress($jobId, [
                        'adminUserId' => $adminUserId,
                        'exportType' => $exportType,
                        'status' => 'running',
                        'percent' => $total > 0 ? (int)min(99, floor(($processed / $total) * 100)) : 0,
                        'processed' => $processed,
                        'total' => $total,
                        'message' => "Processing {$processed} of {$total}",
                        'downloadReady' => false,
                        'file' => basename($csvFile),
                        'fileName' => (string)($data['fileName'] ?? ''),
                    ]);
                }
            }
            fclose($fp);
            $fp = null;

            self::writeProgress($jobId, [
                'adminUserId' => $adminUserId,
                'exportType' => $exportType,
                'status' => 'done',
                'percent' => 100,
                'processed' => $processed,
                'total' => $total,
                'message' => 'Export ready',
                'downloadReady' => true,
                'completedAt' => date('Y-m-d H:i:s'),
                'file' => basename($csvFile),
                'fileName' => (string)($data['fileName'] ?? ''),
            ]);
        } catch (Throwable $exception) {
            if (is_resource($fp)) {
                @fclose($fp);
            }
            if (file_exists($csvFile)) {
                @unlink($csvFile);
            }
            Logger::error('export_webmcp_client failed', [
                'jobId' => $jobId,
                'adminUserId' => $adminUserId,
                'exportType' => $exportType,
                'error' => $exception->getMessage(),
            ]);
            self::writeProgress($jobId, [
                'adminUserId' => $adminUserId,
                'exportType' => $exportType,
                'status' => 'error',
                'percent' => 0,
                'processed' => 0,
                'total' => 0,
                'message' => 'Export failed',
                'downloadReady' => false,
                'file' => null,
                'fileName' => (string)($data['fileName'] ?? ''),
            ]);
            self::clearActive($adminUserId);
        }
    }

    private function fetchClientRows(array $input, array $scope): array
    {
        [$idSql, $params] = $this->idCondition('cu.id', $input['clientIds'], 'client_id_');
        $conditions = [$idSql];

        if (($scope['scope'] ?? '') === 'own') {
            $conditions[] = 'cu.id IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)';
            $params['restrict_to_sales_id'] = (int)($scope['restrict_to_sales_id'] ?? 0);
        }
        if (isset($input['country'])) {
            $conditions[] = "cu.country = COALESCE(
                (SELECT cl.code FROM countryList cl
                 WHERE UPPER(cl.code) = UPPER(:export_country)
                    OR UPPER(cl.name) = UPPER(:export_country_name)
                 LIMIT 1),
                :export_country_fallback
            )";
            $params['export_country'] = $input['country'];
            $params['export_country_name'] = $input['country'];
            $params['export_country_fallback'] = $input['country'];
        }
        if (isset($input['tag'])) {
            $conditions[] = "EXISTS (
                SELECT 1 FROM leadTagAssignments lta_export
                INNER JOIN leadTags lt_export ON lt_export.id = lta_export.tagId
                WHERE lta_export.leadId = cu.id
                  AND UPPER(lt_export.tagName) = UPPER(:export_tag)
            )";
            $params['export_tag'] = $input['tag'];
        }
        if (array_key_exists('neverLoggedIn', $input)) {
            $conditions[] = $input['neverLoggedIn'] ? 'cu.lastLoginAt IS NULL' : 'cu.lastLoginAt IS NOT NULL';
        }
        if (isset($input['kycStatus'])) {
            $conditions[] = 'cu.kycStatus = :export_kyc_status';
            $params['export_kyc_status'] = $input['kycStatus'];
        }
        if (isset($input['status'])) {
            $conditions[] = 'cu.status = :export_client_status';
            $params['export_client_status'] = $input['status'];
        }
        if (isset($input['search'])) {
            $search = '%' . $input['search'] . '%';
            $conditions[] = "(
                cu.firstName LIKE :export_first_name
                OR cu.lastName LIKE :export_last_name
                OR cu.email LIKE :export_email
                OR cu.phone LIKE :export_phone
                OR CONCAT_WS(' ', cu.firstName, cu.lastName) LIKE :export_full_name
            )";
            $params['export_first_name'] = $search;
            $params['export_last_name'] = $search;
            $params['export_email'] = $search;
            $params['export_phone'] = $search;
            $params['export_full_name'] = $search;
        }
        if (isset($input['registeredFrom'])) {
            $conditions[] = 'cu.createdAt >= :registered_from';
            $params['registered_from'] = $input['registeredFrom'];
        }
        if (isset($input['registeredTo'])) {
            $conditions[] = 'cu.createdAt < DATE_ADD(:registered_to, INTERVAL 1 DAY)';
            $params['registered_to'] = $input['registeredTo'];
        }

        $sql = "SELECT
                    cu.id,
                    cu.firstName,
                    cu.lastName,
                    cu.email,
                    cu.phone,
                    cu.country,
                    cu.status,
                    cu.kycStatus,
                    cu.createdAt,
                    cu.lastLoginAt,
                    COALESCE(au.fullName, au.email) AS manager,
                    (
                        SELECT GROUP_CONCAT(DISTINCT lt.tagName ORDER BY lt.tagName SEPARATOR ',')
                        FROM leadTagAssignments lta
                        INNER JOIN leadTags lt ON lt.id = lta.tagId
                        WHERE lta.leadId = cu.id
                    ) AS tagNames
                FROM clientUsers cu
                LEFT JOIN adminUsers au ON au.id = cu.accountManagerId
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY cu.createdAt DESC, cu.id DESC";

        return Database::getInstance()->fetchAll($sql, $params);
    }

    private function fetchTransactionRows(array $input, array $scope): array
    {
        $visibleIds = null;
        if (array_key_exists('clientIds', $input)) {
            [$visibleIdSql, $visibleParams] = $this->idCondition('cu.id', $input['clientIds'], 'visible_client_id_');
            $visibleConditions = [$visibleIdSql];
            if (($scope['scope'] ?? '') === 'own') {
                $visibleConditions[] = 'cu.id IN (SELECT clientId FROM sales_bind WHERE salesId = :visible_restrict_to_sales_id)';
                $visibleParams['visible_restrict_to_sales_id'] = (int)($scope['restrict_to_sales_id'] ?? 0);
            }
            $visibleRows = Database::getInstance()->fetchAll(
                "SELECT cu.id FROM clientUsers cu WHERE " . implode(' AND ', $visibleConditions),
                $visibleParams
            );
            if (!$visibleRows) {
                return [];
            }

            $visibleIds = array_map(static function (array $row): int {
                return (int)$row['id'];
            }, $visibleRows);
        }

        $queries = [];
        $params = [];

        if ($input['type'] === 'all' || $input['type'] === 'deposit') {
            $idSql = '1 = 1';
            $idParams = [];
            if ($visibleIds !== null) {
                [$idSql, $idParams] = $this->idCondition('d.userId', $visibleIds, 'deposit_client_id_');
            }
            $queries[] = "SELECT
                d.userId AS clientId,
                CONCAT_WS(' ', cu.firstName, cu.lastName) AS clientName,
                cu.email AS clientEmail,
                d.id,
                d.transactionId,
                'deposit' AS transactionType,
                d.status,
                d.amount,
                COALESCE(d.currencyCode, 'USD') AS currency,
                d.requestedAt AS transactionDate
             FROM deposits d
             INNER JOIN clientUsers cu ON cu.id = d.userId
             WHERE {$idSql}";
            $params = array_merge($params, $idParams);
        }
        if ($input['type'] === 'all' || $input['type'] === 'withdrawal') {
            $idSql = '1 = 1';
            $idParams = [];
            if ($visibleIds !== null) {
                [$idSql, $idParams] = $this->idCondition('w.userId', $visibleIds, 'withdrawal_client_id_');
            }
            $queries[] = "SELECT
                w.userId AS clientId,
                CONCAT_WS(' ', cu.firstName, cu.lastName) AS clientName,
                cu.email AS clientEmail,
                w.id,
                w.transactionId,
                'withdrawal' AS transactionType,
                w.status,
                w.amount,
                COALESCE(w.currencyCode, 'USD') AS currency,
                w.requestedAt AS transactionDate
             FROM withdrawals w
             INNER JOIN clientUsers cu ON cu.id = w.userId
             WHERE {$idSql}";
            $params = array_merge($params, $idParams);
        }
        if (($input['includeCredit'] ?? true) && ($input['type'] === 'all' || $input['type'] === 'credit')) {
            $idSql = '1 = 1';
            $idParams = [];
            if ($visibleIds !== null) {
                [$idSql, $idParams] = $this->idCondition('ta_credit.userId', $visibleIds, 'credit_client_id_');
            }
            $queries[] = "SELECT
                ta_credit.userId AS clientId,
                CONCAT_WS(' ', cu.firstName, cu.lastName) AS clientName,
                cu.email AS clientEmail,
                tcd.id,
                CONCAT('CR-', tcd.id) AS transactionId,
                'credit' AS transactionType,
                'completed' AS status,
                CASE WHEN tcd.direction = 2 THEN -tcd.amount ELSE tcd.amount END AS amount,
                COALESCE(ta_credit.accountCurrency, 'USD') AS currency,
                tcd.deal_time AS transactionDate
             FROM trading_credit_deals tcd
             INNER JOIN tradingAccounts ta_credit ON ta_credit.id = tcd.trading_account_id
             INNER JOIN clientUsers cu ON cu.id = ta_credit.userId
             WHERE {$idSql}";
            $params = array_merge($params, $idParams);
        }
        if ($input['type'] === 'all' || $input['type'] === 'internal_transfer') {
            $idSql = '1 = 1';
            $idParams = [];
            if ($visibleIds !== null) {
                [$idSql, $idParams] = $this->idCondition('it.userId', $visibleIds, 'transfer_client_id_');
            }
            $queries[] = "SELECT
                it.userId AS clientId,
                CONCAT_WS(' ', cu.firstName, cu.lastName) AS clientName,
                cu.email AS clientEmail,
                it.id,
                it.transactionId,
                'internal_transfer' AS transactionType,
                it.status,
                it.amount,
                'USD' AS currency,
                it.requestedAt AS transactionDate
             FROM internalTransfers it
             INNER JOIN clientUsers cu ON cu.id = it.userId
             WHERE {$idSql}";
            $params = array_merge($params, $idParams);
        }

        $conditions = ['1 = 1'];
        if (($scope['scope'] ?? '') === 'own') {
            $conditions[] = 'transactions.clientId IN (SELECT clientId FROM sales_bind WHERE salesId = :transaction_export_sales_id)';
            $params['transaction_export_sales_id'] = (int)($scope['restrict_to_sales_id'] ?? 0);
        }
        if (isset($input['dateFrom'])) {
            $conditions[] = 'transactions.transactionDate >= :transaction_date_from';
            $params['transaction_date_from'] = $input['dateFrom'];
        }
        if (isset($input['dateTo'])) {
            $conditions[] = 'transactions.transactionDate < DATE_ADD(:transaction_date_to, INTERVAL 1 DAY)';
            $params['transaction_date_to'] = $input['dateTo'];
        }
        if (isset($input['status'])) {
            $conditions[] = 'transactions.status = :transaction_status';
            $params['transaction_status'] = $input['status'];
        }
        if (isset($input['minAmount'])) {
            $conditions[] = 'transactions.amount >= :transaction_min_amount';
            $params['transaction_min_amount'] = $input['minAmount'];
        }
        if (isset($input['maxAmount'])) {
            $conditions[] = 'transactions.amount <= :transaction_max_amount';
            $params['transaction_max_amount'] = $input['maxAmount'];
        }

        $unionSql = implode(' UNION ALL ', $queries);
        $sql = "SELECT * FROM ({$unionSql}) transactions
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY transactions.transactionDate DESC, transactions.id DESC
                LIMIT " . self::MAX_TRANSACTION_ROWS;
        return Database::getInstance()->fetchAll($sql, $params);
    }

    private function idCondition(string $column, array $ids, string $prefix): array
    {
        $placeholders = [];
        $params = [];
        foreach (array_values($ids) as $index => $id) {
            $name = $prefix . $index;
            $placeholders[] = ':' . $name;
            $params[$name] = (int)$id;
        }
        return [$column . ' IN (' . implode(', ', $placeholders) . ')', $params];
    }

    private function clientCsvRow(array $row): array
    {
        $tagNames = trim((string)($row['tagNames'] ?? ''));
        return [
            (int)($row['id'] ?? 0),
            self::safeText($row['firstName'] ?? ''),
            self::safeText($row['lastName'] ?? ''),
            self::safeText($row['email'] ?? ''),
            self::safeText($row['phone'] ?? ''),
            self::safeText($row['country'] ?? ''),
            self::safeText($row['status'] ?? ''),
            self::safeText($row['kycStatus'] ?? ''),
            self::safeText($row['manager'] ?? ''),
            self::safeText($tagNames),
            self::safeText($row['createdAt'] ?? ''),
            self::safeText($row['lastLoginAt'] ?? ''),
        ];
    }

    private function transactionCsvRow(array $row): array
    {
        return [
            (int)($row['clientId'] ?? 0),
            self::safeText($row['clientName'] ?? ''),
            self::safeText($row['clientEmail'] ?? ''),
            self::safeText($row['transactionId'] ?? ''),
            self::safeText($row['transactionType'] ?? ''),
            self::safeText($row['status'] ?? ''),
            isset($row['amount']) && $row['amount'] !== '' ? number_format((float)$row['amount'], 2, '.', '') : '',
            self::safeText($row['currency'] ?? ''),
            self::safeText($row['transactionDate'] ?? ''),
        ];
    }

    private static function safeText($value): string
    {
        $value = (string)$value;
        return preg_match('/^\s*[=+\-@]/', $value) === 1 ? "'" . $value : $value;
    }

    private static function isSafeJobId(string $jobId): bool
    {
        return $jobId !== '' && strlen($jobId) <= 80 && preg_match('/^[A-Za-z0-9._-]+$/', $jobId) === 1;
    }

    private static function safeJobId(string $jobId): string
    {
        return preg_replace('/[^a-zA-Z0-9._-]/', '', $jobId);
    }
}
