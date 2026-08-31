<?php

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';
require_once __DIR__ . '/../models/IbPartnerBind.php';
require_once __DIR__ . '/../models/Order.php';

class IbStatementReportController
{
    private const PAGE_KEY = 'page_ibstatement';
    private const READ_PERMISSION = 'page_ibstatement_readonly';
    private const EXPORT_PERMISSION = 'page_ibstatement_export';

    public function partners()
    {
        $this->requireReadAccess();
        $scope = AdminSalesPermission::getClientDataScopeForPage(self::PAGE_KEY);
        if ($scope['scope'] === 'none') {
            Response::success(['items' => []]);
            return;
        }

        $db = Database::getInstance();
        $sql = "SELECT
                    ib.id,
                    ib.ibCode,
                    COALESCE(
                        NULLIF(TRIM(ib.adminAlias), ''),
                        NULLIF(TRIM(ib.clientAlias), ''),
                        NULLIF(TRIM(ib.companyName), ''),
                        TRIM(CONCAT(IFNULL(cu.firstName, ''), ' ', IFNULL(cu.lastName, '')))
                    ) AS name
                FROM ibPartners ib
                LEFT JOIN clientUsers cu ON cu.id = ib.userId
                WHERE ib.status = 'approved'";
        $params = [];
        if ($scope['scope'] === 'own') {
            $sql .= ' AND ib.userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)';
            $params['restrict_to_sales_id'] = (int) $scope['restrict_to_sales_id'];
        }
        $sql .= ' ORDER BY name ASC, ib.ibCode ASC, ib.id ASC';
        $rows = $db->fetchAll($sql, $params);
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row['id'],
                'ibCode' => (string) ($row['ibCode'] ?? ''),
                'name' => trim((string) ($row['name'] ?? '')) ?: ('IB #' . (int) $row['id']),
            ];
        }
        Response::success(['items' => $items]);
    }

    public function statement()
    {
        $this->requireReadAccess();
        $ibPartnerId = (int) ($_GET['ibPartnerId'] ?? 0);
        $startRaw = $_GET['startDate'] ?? $_GET['start_date'] ?? '';
        $endRaw = $_GET['endDate'] ?? $_GET['end_date'] ?? '';
        if ($ibPartnerId <= 0) {
            Response::validationError(['ibPartnerId' => 'A valid IB partner is required']);
            return;
        }
        $startServer = $this->convertDateToServerTimezone($startRaw);
        $endServer = $this->convertEndDateToServerTimezone($endRaw);
        if ($startServer === null || $endServer === null) {
            Response::validationError(['startDate' => 'A valid date range is required']);
            return;
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage(self::PAGE_KEY);
        $payload = $this->buildStatement($ibPartnerId, $startServer, $endServer, $scope);
        if ($payload === null) {
            Response::notFound('IB partner not found');
            return;
        }
        Response::success($payload);
    }

    public function exportReport(?array $inputOverride = null)
    {
        $this->requireExportAccess();
        require_once __DIR__ . '/../services/IbStatementReportExportService.php';

        $admin = $this->requireAdmin();
        $adminUserId = (int) ($admin['userId'] ?? 0);
        if ($adminUserId <= 0) {
            Response::error('Unauthorized', 401);
            return;
        }

        $active = IbStatementReportExportService::getActiveForAdmin($adminUserId);
        $activeStatus = (string) ($active['status'] ?? '');
        if (in_array($activeStatus, ['queued', 'running', 'cancelling'], true)) {
            Response::error('Export already in progress', 409, $this->exportProgressPayload($active));
            return;
        }
        if ($activeStatus === 'done') {
            IbStatementReportExportService::clearActive($adminUserId);
        }

        $input = $inputOverride ?? (json_decode(file_get_contents('php://input'), true) ?? []);
        $ibPartnerId = (int) ($input['ibPartnerId'] ?? 0);
        $startRaw = $input['startDate'] ?? $input['start_date'] ?? '';
        $endRaw = $input['endDate'] ?? $input['end_date'] ?? '';
        $startServer = $this->convertDateToServerTimezone($startRaw);
        $endServer = $this->convertEndDateToServerTimezone($endRaw);
        if ($ibPartnerId <= 0 || $startServer === null || $endServer === null) {
            Response::validationError(['ibPartnerId' => 'A valid IB partner and date range are required']);
            return;
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage(self::PAGE_KEY);
        $query = [
            'ibPartnerId' => $ibPartnerId,
            'startDate' => $startServer,
            'endDate' => $endServer,
            'scope' => [
                'scope' => (string) ($scope['scope'] ?? 'none'),
                'restrict_to_sales_id' => (int) ($scope['restrict_to_sales_id'] ?? 0),
            ],
        ];

        $jobId = str_replace('.', '', uniqid('ais_', true));
        $format = strtolower(trim((string) ($input['format'] ?? 'csv')));
        if ($format !== 'excel') {
            $format = 'csv';
        }
        $ext = $format === 'excel' ? 'xls' : 'csv';
        $fileName = 'ib_statement_' . date('Y-m-d') . '.' . $ext;
        IbStatementReportExportService::ensureExportDir();
        IbStatementReportExportService::writeProgress($jobId, [
            'adminUserId' => $adminUserId,
            'status' => 'queued',
            'cancelRequested' => false,
            'percent' => 0,
            'processed' => 0,
            'total' => 0,
            'message' => 'Queued',
            'file' => $jobId . '.csv',
            'fileName' => $fileName,
            'format' => $format,
        ]);
        IbStatementReportExportService::writeActive($adminUserId, $jobId);

        $payload = [
            'type' => 'export_admin_ib_statement_report',
            'jobId' => $jobId,
            'adminUserId' => $adminUserId,
            'userId' => $adminUserId,
            'userType' => 'admin',
            'query' => $query,
            'requestedAt' => time(),
        ];

        try {
            $this->dispatchSwooleTask($payload);
        } catch (Exception $e) {
            IbStatementReportExportService::writeProgress($jobId, [
                'adminUserId' => $adminUserId,
                'status' => 'error',
                'cancelRequested' => false,
                'percent' => 0,
                'message' => $e->getMessage(),
                'file' => null,
            ]);
            IbStatementReportExportService::clearActive($adminUserId);
            Response::error('Failed to queue export task: ' . $e->getMessage(), 500);
            return;
        }

        Response::success([
            'jobId' => $jobId,
            'queued' => true,
        ], 'Export task accepted');
    }

    public function exportActive()
    {
        $this->requireExportAccess();
        require_once __DIR__ . '/../services/IbStatementReportExportService.php';
        $admin = $this->requireAdmin();
        $adminUserId = (int) ($admin['userId'] ?? 0);
        if ($adminUserId <= 0) {
            Response::error('Unauthorized', 401);
            return;
        }
        $progress = IbStatementReportExportService::getActiveForAdmin($adminUserId);
        Response::success($this->exportProgressPayload($progress));
    }

    public function exportStatus()
    {
        $this->requireExportAccess();
        require_once __DIR__ . '/../services/IbStatementReportExportService.php';
        $admin = $this->requireAdmin();
        $adminUserId = (int) ($admin['userId'] ?? 0);
        if ($adminUserId <= 0) {
            Response::error('Unauthorized', 401);
            return;
        }
        $jobId = isset($_GET['jobId']) ? trim((string) $_GET['jobId']) : '';
        if (!$this->isSafeJobId($jobId)) {
            Response::validationError(['jobId' => 'jobId is required']);
            return;
        }
        $progress = IbStatementReportExportService::readProgress($jobId);
        if ($progress === null || (int) ($progress['adminUserId'] ?? 0) !== $adminUserId) {
            Response::notFound('Export job not found');
            return;
        }
        require_once __DIR__ . '/../services/ExportJobTimeoutReaper.php';
        $progress = ExportJobTimeoutReaper::reapIfStale(
            $jobId,
            $progress,
            [IbStatementReportExportService::class, 'writeProgress'],
            [IbStatementReportExportService::class, 'clearActive']
        );
        Response::success($this->exportProgressPayload($progress));
    }

    public function exportCancel()
    {
        $this->requireExportAccess();
        require_once __DIR__ . '/../services/IbStatementReportExportService.php';
        $admin = $this->requireAdmin();
        $adminUserId = (int) ($admin['userId'] ?? 0);
        if ($adminUserId <= 0) {
            Response::error('Unauthorized', 401);
            return;
        }
        $rawBody = json_decode(file_get_contents('php://input'), true);
        $jobId = '';
        if (is_array($rawBody) && !empty($rawBody['jobId'])) {
            $jobId = trim((string) $rawBody['jobId']);
        } elseif (!empty($_GET['jobId'])) {
            $jobId = trim((string) $_GET['jobId']);
        }
        if (!$this->isSafeJobId($jobId)) {
            Response::validationError(['jobId' => 'jobId is required']);
            return;
        }
        $progress = IbStatementReportExportService::readProgress($jobId);
        if ($progress === null || (int) ($progress['adminUserId'] ?? 0) !== $adminUserId) {
            Response::notFound('Export job not found');
            return;
        }
        $status = (string) ($progress['status'] ?? '');
        if ($status === 'cancelled') {
            Response::success($this->exportProgressPayload($progress), 'Already cancelled');
            return;
        }
        if ($status === 'error') {
            IbStatementReportExportService::clearActive($adminUserId);
            Response::success($this->exportProgressPayload($progress), 'Export already failed');
            return;
        }
        IbStatementReportExportService::requestCancel($jobId);
        $updated = IbStatementReportExportService::readProgress($jobId);
        Response::success($this->exportProgressPayload($updated), 'Cancel requested');
    }

    public function exportDownload()
    {
        $this->requireExportAccess();
        require_once __DIR__ . '/../services/IbStatementReportExportService.php';
        $admin = $this->requireAdmin();
        $adminUserId = (int) ($admin['userId'] ?? 0);
        if ($adminUserId <= 0) {
            Response::error('Unauthorized', 401);
            return;
        }
        $jobId = isset($_GET['jobId']) ? trim((string) $_GET['jobId']) : '';
        if (!$this->isSafeJobId($jobId)) {
            Response::validationError(['jobId' => 'jobId is required']);
            return;
        }
        $progress = IbStatementReportExportService::readProgress($jobId);
        if ($progress === null || (int) ($progress['adminUserId'] ?? 0) !== $adminUserId) {
            Response::notFound('Export job not found');
            return;
        }
        if (($progress['status'] ?? '') !== 'done') {
            Response::error('Export is not ready', 400);
            return;
        }
        $csvFile = IbStatementReportExportService::csvPath($jobId);
        if (!file_exists($csvFile) || !is_readable($csvFile)) {
            Response::error('Export file missing', 404);
            return;
        }
        IbStatementReportExportService::clearActive($adminUserId);
        $filename = trim((string) ($progress['fileName'] ?? ''));
        if ($filename === '' || !preg_match('/^[A-Za-z0-9._-]+\.(csv|xls)$/', $filename)) {
            $filename = 'ib_statement_' . date('Y-m-d') . '.csv';
        }
        $isExcel = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) === 'xls';
        header('Content-Type: ' . ($isExcel ? 'application/vnd.ms-excel; charset=utf-8' : 'text/csv; charset=utf-8'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($csvFile));
        header('Cache-Control: no-store');
        readfile($csvFile);
        exit;
    }

    public function buildStatement($ibPartnerId, $startServer, $endServer, array $scope)
    {
        $ibPartnerId = (int) $ibPartnerId;
        if ($ibPartnerId <= 0) {
            return null;
        }
        if (($scope['scope'] ?? '') === 'none') {
            return $this->emptyStatement($ibPartnerId, $startServer, $endServer, null);
        }

        $partner = $this->fetchAllowedPartner($ibPartnerId, $scope);
        if ($partner === null) {
            return null;
        }

        $startTs = strtotime($startServer);
        $endTs = strtotime($endServer);
        if ($startTs === false || $endTs === false) {
            return $this->emptyStatement($ibPartnerId, $startServer, $endServer, $partner);
        }

        $userIds = $this->collectTreeUserIds($ibPartnerId);
        if (empty($userIds)) {
            return $this->emptyStatement($ibPartnerId, $startServer, $endServer, $partner);
        }

        $accounts = $this->fetchAccounts($userIds, $endServer);
        if (empty($accounts)) {
            return $this->emptyStatement($ibPartnerId, $startServer, $endServer, $partner);
        }

        $accountIds = array_values(array_unique(array_map(function ($row) {
            return (int) $row['tradingAccountId'];
        }, $accounts)));
        $logins = array_values(array_unique(array_filter(array_map(function ($row) {
            return (string) $row['login'];
        }, $accounts))));

        $depositsIn = $this->sumFundingByAccount('deposits', $accountIds, $startServer, $endServer, false);
        $withdrawalsIn = $this->sumFundingByAccount('withdrawals', $accountIds, $startServer, $endServer, false);
        $depositsBefore = $this->sumFundingByAccount('deposits', $accountIds, $startServer, $endServer, true);
        $withdrawalsBefore = $this->sumFundingByAccount('withdrawals', $accountIds, $startServer, $endServer, true);

        $closedIn = $this->sumOrdersByLogin($logins, $startTs, $endTs, false);
        $closedBefore = $this->sumOrdersByLogin($logins, $startTs, $endTs, true);
        $unrealisedByLogin = $this->sumUnrealisedByLogin($logins, $endTs);
        $instruments = $this->sumOrdersByInstrument($logins, $startTs, $endTs);
        $weeks = $this->sumOrdersByWeek($logins, $startTs, $endTs, $startServer, $endServer);

        $clientIdList = array_values(array_unique(array_filter(array_map(function ($row) {
            return (int) ($row['clientId'] ?? 0);
        }, $accounts), function ($id) {
            return $id > 0;
        })));
        $affiliations = $this->fetchClientAffiliations($clientIdList, $partner);

        $detail = [];
        $fundedCount = 0;
        $tradedCount = 0;
        $clientIds = [];
        $sumOpening = 0.0;
        $sumDeposits = 0.0;
        $sumWithdrawals = 0.0;
        $sumLots = 0.0;
        $sumTrades = 0;
        $sumResult = 0.0;
        $sumCommission = 0.0;
        $sumSwap = 0.0;
        $sumUnrealised = 0.0;
        $sumClosing = 0.0;

        foreach ($accounts as $account) {
            $login = (string) $account['login'];
            $accountId = (int) $account['tradingAccountId'];
            $clientId = (int) $account['clientId'];
            $clientIds[$clientId] = true;

            $deposits = $this->money($depositsIn[$accountId] ?? 0);
            $withdrawals = $this->money($withdrawalsIn[$accountId] ?? 0);
            $netDeposits = $this->money($deposits - $withdrawals);

            $inStats = $closedIn[$login] ?? $this->emptyOrderStats();
            $beforeStats = $closedBefore[$login] ?? $this->emptyOrderStats();
            $lots = $this->lots($inStats['volume']);
            $trades = (int) $inStats['trades'];
            $tradingResult = $this->money($this->netFromStats($inStats));
            $commissionFees = $this->money($inStats['commission'] + $inStats['taxes']);
            $swap = $this->money($inStats['swap']);

            $opening = $this->money(
                ($depositsBefore[$accountId] ?? 0)
                - ($withdrawalsBefore[$accountId] ?? 0)
                + $this->netFromStats($beforeStats)
            );
            $closing = $this->money($opening + $netDeposits + $tradingResult);
            $unrealised = $this->money($unrealisedByLogin[$login] ?? 0);

            $unfunded = $deposits == 0.0 && $trades === 0;
            if ($deposits > 0) {
                $fundedCount++;
            }
            if ($trades > 0) {
                $tradedCount++;
            }

            $sumOpening += $opening;
            $sumDeposits += $deposits;
            $sumWithdrawals += $withdrawals;
            $sumLots += $lots;
            $sumTrades += $trades;
            $sumResult += $tradingResult;
            $sumCommission += $commissionFees;
            $sumSwap += $swap;
            $sumUnrealised += $unrealised;
            $sumClosing += $closing;

            $opened = $account['openedAt'] ? substr((string) $account['openedAt'], 0, 10) : '';
            $aff = $affiliations[$clientId] ?? $this->defaultAffiliation($partner);

            $detail[] = [
                'login' => $login,
                'clientId' => $clientId,
                'clientName' => (string) $account['clientName'],
                'ibName' => $aff['ibName'],
                'ibCode' => $aff['ibCode'],
                'subIbName' => $aff['subIbName'],
                'subIbCode' => $aff['subIbCode'],
                'clientType' => $aff['clientType'],
                'affiliationCode' => $aff['affiliationCode'],
                'opened' => $opened,
                'deposits' => $deposits,
                'withdrawals' => $withdrawals,
                'netDeposits' => $netDeposits,
                'lots' => $lots,
                'trades' => $trades,
                'tradingResult' => $tradingResult,
                'balance' => $closing,
                'unfunded' => $unfunded,
            ];
        }

        usort($detail, function ($a, $b) {
            return strnatcmp((string) $a['login'], (string) $b['login']);
        });

        $sumOpening = $this->money($sumOpening);
        $sumDeposits = $this->money($sumDeposits);
        $sumWithdrawals = $this->money($sumWithdrawals);
        $sumNet = $this->money($sumDeposits - $sumWithdrawals);
        $sumResult = $this->money($sumResult);
        $sumCommission = $this->money($sumCommission);
        $sumSwap = $this->money($sumSwap);
        $sumClosing = $this->money($sumClosing);
        $sumUnrealised = $this->money($sumUnrealised);
        $sumLots = round($sumLots, 2);
        $closingEquity = $this->money($sumClosing + $sumUnrealised);

        $startDate = substr($startServer, 0, 10);
        $endDate = substr($endServer, 0, 10);

        return [
            'partner' => [
                'id' => (int) $partner['id'],
                'ibCode' => (string) ($partner['ibCode'] ?? ''),
                'name' => (string) $partner['name'],
            ],
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
                'issued' => $endDate,
            ],
            'currency' => 'USD',
            'headline' => [
                'clientCount' => count($clientIds),
                'accountCount' => count($detail),
                'fundedCount' => $fundedCount,
                'tradedCount' => $tradedCount,
                'totalDeposits' => $sumDeposits,
                'totalWithdrawals' => $sumWithdrawals,
                'netDeposits' => $sumNet,
                'closingBalance' => $sumClosing,
            ],
            'movement' => [
                'openingBalance' => $sumOpening,
                'deposits' => $sumDeposits,
                'withdrawals' => $sumWithdrawals,
                'netDeposits' => $sumNet,
                'tradingResult' => $sumResult,
                'commissionFees' => $sumCommission,
                'swap' => $sumSwap,
                'closingBalance' => $sumClosing,
                'unrealised' => $sumUnrealised,
                'closingEquity' => $closingEquity,
            ],
            'accounts' => $detail,
            'accountsTotal' => [
                'accountCount' => count($detail),
                'deposits' => $sumDeposits,
                'withdrawals' => $sumWithdrawals,
                'netDeposits' => $sumNet,
                'lots' => $sumLots,
                'trades' => $sumTrades,
                'tradingResult' => $sumResult,
                'balance' => $sumClosing,
            ],
            'instruments' => $instruments,
            'weeks' => $weeks,
        ];
    }

    private function partnerDisplayNameSql($ibAlias = 'ib', $userAlias = 'cu')
    {
        return "COALESCE(
                    NULLIF(TRIM({$ibAlias}.adminAlias), ''),
                    NULLIF(TRIM({$ibAlias}.clientAlias), ''),
                    NULLIF(TRIM({$ibAlias}.companyName), ''),
                    TRIM(CONCAT(IFNULL({$userAlias}.firstName, ''), ' ', IFNULL({$userAlias}.lastName, '')))
                )";
    }

    private function formatPartnerName($name, $id)
    {
        $name = trim((string) $name);
        return $name !== '' ? $name : ('IB #' . (int) $id);
    }

    private function defaultAffiliation(array $partner)
    {
        return [
            'ibName' => $this->formatPartnerName($partner['name'] ?? '', $partner['id'] ?? 0),
            'ibCode' => (string) ($partner['ibCode'] ?? ''),
            'subIbName' => '',
            'subIbCode' => '',
            'clientType' => 'Direct Client',
            'affiliationCode' => (string) ($partner['ibCode'] ?? ''),
        ];
    }

    private function fetchClientAffiliations(array $clientIds, array $partner)
    {
        $fallback = $this->defaultAffiliation($partner);
        $selectedId = (int) ($partner['id'] ?? 0);
        $map = [];
        foreach ($clientIds as $clientId) {
            $map[(int) $clientId] = $fallback;
        }
        if (empty($clientIds) || $selectedId <= 0) {
            return $map;
        }

        list($placeholders, $params) = $this->inParams($clientIds, 'c');
        $nameSql = $this->partnerDisplayNameSql('ib', 'cu');
        $db = Database::getInstance();

        $directParent = [];
        $directRows = $db->fetchAll(
            "SELECT
                    b.childClientId AS clientId,
                    ib.id,
                    ib.ibCode,
                    {$nameSql} AS name
             FROM ib_partner_bind b
             INNER JOIN ibPartners ib ON ib.id = b.parentId
             LEFT JOIN clientUsers cu ON cu.id = ib.userId
             WHERE b.isClient = 1
               AND b.childClientId IN ({$placeholders})
             ORDER BY b.createdAt DESC, b.id DESC",
            $params
        );
        foreach ($directRows as $row) {
            $clientId = (int) ($row['clientId'] ?? 0);
            if ($clientId <= 0 || isset($directParent[$clientId])) {
                continue;
            }
            $directParent[$clientId] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => $this->formatPartnerName($row['name'] ?? '', $row['id'] ?? 0),
                'code' => (string) ($row['ibCode'] ?? ''),
            ];
        }

        $selfIb = [];
        $selfSelected = [];
        $selfRows = $db->fetchAll(
            "SELECT
                    ib.userId AS clientId,
                    ib.id,
                    ib.ibCode,
                    {$nameSql} AS name
             FROM ibPartners ib
             LEFT JOIN clientUsers cu ON cu.id = ib.userId
             WHERE ib.status = 'approved'
               AND ib.userId IN ({$placeholders})",
            $params
        );
        foreach ($selfRows as $row) {
            $clientId = (int) ($row['clientId'] ?? 0);
            $ibId = (int) ($row['id'] ?? 0);
            if ($clientId <= 0 || $ibId <= 0) {
                continue;
            }
            $item = [
                'id' => $ibId,
                'name' => $this->formatPartnerName($row['name'] ?? '', $ibId),
                'code' => (string) ($row['ibCode'] ?? ''),
            ];
            if ($ibId === $selectedId) {
                if (!isset($selfSelected[$clientId])) {
                    $selfSelected[$clientId] = $item;
                }
                continue;
            }
            if (!isset($selfIb[$clientId])) {
                $selfIb[$clientId] = $item;
            }
        }

        foreach ($clientIds as $clientId) {
            $clientId = (int) $clientId;
            if (isset($selfSelected[$clientId])) {
                $self = $selfSelected[$clientId];
                $map[$clientId] = [
                    'ibName' => $fallback['ibName'],
                    'ibCode' => $fallback['ibCode'],
                    'subIbName' => '',
                    'subIbCode' => '',
                    'clientType' => 'IB',
                    'affiliationCode' => $self['code'] !== '' ? $self['code'] : $fallback['ibCode'],
                ];
                continue;
            }
            if (isset($selfIb[$clientId])) {
                $sub = $selfIb[$clientId];
                $map[$clientId] = [
                    'ibName' => $fallback['ibName'],
                    'ibCode' => $fallback['ibCode'],
                    'subIbName' => $sub['name'],
                    'subIbCode' => $sub['code'],
                    'clientType' => 'Sub-IB',
                    'affiliationCode' => $sub['code'],
                ];
                continue;
            }
            $parent = $directParent[$clientId] ?? null;
            $sub = ($parent && (int) $parent['id'] !== $selectedId) ? $parent : null;
            $map[$clientId] = [
                'ibName' => $fallback['ibName'],
                'ibCode' => $fallback['ibCode'],
                'subIbName' => $sub ? $sub['name'] : '',
                'subIbCode' => $sub ? $sub['code'] : '',
                'clientType' => 'Direct Client',
                'affiliationCode' => $sub && $sub['code'] !== '' ? $sub['code'] : $fallback['ibCode'],
            ];
        }

        return $map;
    }

    private function fetchAllowedPartner($ibPartnerId, array $scope)
    {
        $db = Database::getInstance();
        $sql = "SELECT
                    ib.id,
                    ib.ibCode,
                    COALESCE(
                        NULLIF(TRIM(ib.adminAlias), ''),
                        NULLIF(TRIM(ib.clientAlias), ''),
                        NULLIF(TRIM(ib.companyName), ''),
                        TRIM(CONCAT(IFNULL(cu.firstName, ''), ' ', IFNULL(cu.lastName, '')))
                    ) AS name
                FROM ibPartners ib
                LEFT JOIN clientUsers cu ON cu.id = ib.userId
                WHERE ib.id = :id AND ib.status = 'approved'";
        $params = ['id' => (int) $ibPartnerId];
        if (($scope['scope'] ?? '') === 'own') {
            $sql .= ' AND ib.userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)';
            $params['restrict_to_sales_id'] = (int) $scope['restrict_to_sales_id'];
        }
        $row = $db->fetchOne($sql, $params);
        if (!$row) {
            return null;
        }
        $row['name'] = trim((string) ($row['name'] ?? '')) ?: ('IB #' . (int) $row['id']);
        return $row;
    }

    private function collectTreeUserIds($ibPartnerId)
    {
        $bind = new IbPartnerBind();
        $ibIds = $bind->getDescendantIbPartnerIds($ibPartnerId, true);
        $clientIds = $bind->getClientIdsUnderIbTree($ibPartnerId);
        $userIds = [];
        foreach ($clientIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $userIds[$id] = true;
            }
        }
        $ibIds = array_values(array_unique(array_filter(array_map('intval', $ibIds), function ($id) {
            return $id > 0;
        })));
        if (!empty($ibIds)) {
            list($placeholders, $params) = $this->inParams($ibIds, 'ib');
            $rows = Database::getInstance()->fetchAll(
                "SELECT userId FROM ibPartners WHERE id IN ({$placeholders}) AND userId IS NOT NULL",
                $params
            );
            foreach ($rows as $row) {
                $id = (int) ($row['userId'] ?? 0);
                if ($id > 0) {
                    $userIds[$id] = true;
                }
            }
        }
        return array_keys($userIds);
    }

    private function fetchAccounts(array $userIds, $endServer)
    {
        list($placeholders, $params) = $this->inParams($userIds, 'u');
        $params['endDate'] = $endServer;
        $sql = "SELECT
                    ta.id AS tradingAccountId,
                    ta.userId AS clientId,
                    ta.createdAt AS openedAt,
                    taea.providerAccountId AS login,
                    TRIM(CONCAT_WS(' ', cu.firstName, cu.lastName)) AS clientName
                FROM tradingAccounts ta
                INNER JOIN tradingAccountExternalAccounts taea
                    ON taea.id = (
                        SELECT MAX(x.id)
                        FROM tradingAccountExternalAccounts x
                        WHERE x.tradingAccountId = ta.id
                          AND x.providerAccountId IS NOT NULL
                          AND x.providerAccountId != ''
                    )
                INNER JOIN clientUsers cu ON cu.id = ta.userId
                WHERE ta.userId IN ({$placeholders})
                  AND (ta.createdAt IS NULL OR ta.createdAt <= :endDate)";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    private function sumFundingByAccount($table, array $accountIds, $startServer, $endServer, $beforePeriod)
    {
        if (!in_array($table, ['deposits', 'withdrawals'], true) || empty($accountIds)) {
            return [];
        }
        list($placeholders, $params) = $this->inParams($accountIds, 'a');
        $params['startDate'] = $startServer;
        $dateExpr = 'COALESCE(completedAt, requestedAt)';
        if ($beforePeriod) {
            $rangeSql = "AND {$dateExpr} < :startDate";
        } else {
            $params['endDate'] = $endServer;
            $rangeSql = "AND {$dateExpr} >= :startDate AND {$dateExpr} <= :endDate";
        }
        $sql = "SELECT tradingAccountId, COALESCE(SUM(amount), 0) AS total
                FROM {$table}
                WHERE status = 'completed'
                  AND tradingAccountId IN ({$placeholders})
                  {$rangeSql}
                GROUP BY tradingAccountId";
        $rows = Database::getInstance()->fetchAll($sql, $params);
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['tradingAccountId']] = (float) $row['total'];
        }
        return $map;
    }

    private function sumOrdersByLogin(array $logins, $startTs, $endTs, $beforePeriod)
    {
        if (empty($logins)) {
            return [];
        }
        list($placeholders, $params) = $this->inParams($logins, 'l');
        $params['startTs'] = (int) $startTs;
        if ($beforePeriod) {
            $rangeSql = 'AND o.closetime > 0 AND o.closetime < :startTs';
        } else {
            $params['endTs'] = (int) $endTs;
            $rangeSql = 'AND o.closetime >= :startTs AND o.closetime <= :endTs';
        }
        $sql = "SELECT
                    o.trading_login AS login,
                    COALESCE(SUM(o.volume), 0) AS volume,
                    COUNT(*) AS trades,
                    COALESCE(SUM(o.profit), 0) AS profit,
                    COALESCE(SUM(o.commission), 0) AS commission,
                    COALESCE(SUM(o.storage), 0) AS swap,
                    COALESCE(SUM(o.taxes), 0) AS taxes
                FROM orders o
                WHERE o.trading_status = 2
                  AND o.trading_login IN ({$placeholders})
                  {$rangeSql}
                GROUP BY o.trading_login";
        $rows = Database::getInstance()->fetchAll($sql, $params);
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['login']] = [
                'volume' => (float) $row['volume'],
                'trades' => (int) $row['trades'],
                'profit' => (float) $row['profit'],
                'commission' => (float) $row['commission'],
                'swap' => (float) $row['swap'],
                'taxes' => (float) $row['taxes'],
            ];
        }
        return $map;
    }

    private function sumUnrealisedByLogin(array $logins, $endTs)
    {
        if (empty($logins)) {
            return [];
        }
        list($placeholders, $params) = $this->inParams($logins, 'l');
        $params['endTsOpen'] = (int) $endTs;
        $params['endTsClose'] = (int) $endTs;
        $sql = "SELECT
                    o.trading_login AS login,
                    COALESCE(SUM(o.profit), 0) AS unrealised
                FROM orders o
                WHERE o.trading_status = 0
                  AND o.opentime > 0
                  AND o.opentime <= :endTsOpen
                  AND (o.closetime IS NULL OR o.closetime = 0 OR o.closetime > :endTsClose)
                  AND o.trading_login IN ({$placeholders})
                GROUP BY o.trading_login";
        $rows = Database::getInstance()->fetchAll($sql, $params);
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['login']] = (float) $row['unrealised'];
        }
        return $map;
    }

    private function sumOrdersByInstrument(array $logins, $startTs, $endTs)
    {
        if (empty($logins)) {
            return $this->emptyInstrumentPayload();
        }
        list($placeholders, $params) = $this->inParams($logins, 'l');
        $params['startTs'] = (int) $startTs;
        $params['endTs'] = (int) $endTs;
        $sql = "SELECT
                    IFNULL(NULLIF(TRIM(o.symbol), ''), 'UNKNOWN') AS instrument,
                    COALESCE(SUM(o.volume), 0) AS volume,
                    COUNT(*) AS trades,
                    COUNT(DISTINCT o.trading_login) AS accountsTraded,
                    COALESCE(SUM(o.profit), 0) AS profit,
                    COALESCE(SUM(o.commission), 0) AS commission,
                    COALESCE(SUM(o.storage), 0) AS swap,
                    COALESCE(SUM(o.taxes), 0) AS taxes
                FROM orders o
                WHERE o.trading_status = 2
                  AND o.closetime >= :startTs
                  AND o.closetime <= :endTs
                  AND o.trading_login IN ({$placeholders})
                GROUP BY instrument
                ORDER BY volume DESC";
        $rows = Database::getInstance()->fetchAll($sql, $params);
        $totalLots = 0.0;
        $items = [];
        foreach ($rows as $row) {
            $lots = $this->lots($row['volume']);
            $totalLots += $lots;
            $items[] = [
                'instrument' => (string) $row['instrument'],
                'lots' => $lots,
                'tradingResult' => $this->money($this->netFromStats($row)),
                'accountsTraded' => (int) $row['accountsTraded'],
            ];
        }
        $totalLots = round($totalLots, 2);
        foreach ($items as &$item) {
            $item['shareOfVolume'] = $totalLots > 0
                ? round(($item['lots'] / $totalLots) * 100, 1)
                : 0.0;
        }
        unset($item);

        $totalResult = 0.0;
        foreach ($items as $item) {
            $totalResult += $item['tradingResult'];
        }
        $tradedSql = "SELECT COUNT(DISTINCT o.trading_login) AS traded
                      FROM orders o
                      WHERE o.trading_status = 2
                        AND o.closetime >= :startTs
                        AND o.closetime <= :endTs
                        AND o.trading_login IN ({$placeholders})";
        $tradedRow = Database::getInstance()->fetchOne($tradedSql, $params);

        return [
            'items' => $items,
            'total' => [
                'lots' => $totalLots,
                'shareOfVolume' => $totalLots > 0 ? 100.0 : 0.0,
                'tradingResult' => $this->money($totalResult),
                'accountsTraded' => (int) ($tradedRow['traded'] ?? 0),
            ],
        ];
    }

    private function sumOrdersByWeek(array $logins, $startTs, $endTs, $startServer, $endServer)
    {
        if (empty($logins)) {
            return $this->emptyWeekPayload();
        }
        list($placeholders, $params) = $this->inParams($logins, 'l');
        $params['startTs'] = (int) $startTs;
        $params['endTs'] = (int) $endTs;
        $sql = "SELECT
                    o.closetime,
                    o.volume,
                    o.trading_login,
                    o.profit,
                    o.commission,
                    o.storage,
                    o.taxes
                FROM orders o
                WHERE o.trading_status = 2
                  AND o.closetime >= :startTs
                  AND o.closetime <= :endTs
                  AND o.trading_login IN ({$placeholders})";
        $rows = Database::getInstance()->fetchAll($sql, $params);
        $tz = new DateTimeZone('Asia/Shanghai');
        $periodStart = new DateTime($startServer, $tz);
        $periodEnd = new DateTime($endServer, $tz);
        $buckets = [];
        foreach ($rows as $row) {
            $closeTs = (int) $row['closetime'];
            if ($closeTs <= 0) {
                continue;
            }
            $dt = new DateTime('@' . $closeTs);
            $dt->setTimezone($tz);
            $n = (int) $dt->format('N');
            $monday = clone $dt;
            if ($n > 1) {
                $monday->modify('-' . ($n - 1) . ' days');
            }
            $monday->setTime(0, 0, 0);
            $key = $monday->format('Y-m-d');
            if (!isset($buckets[$key])) {
                $sunday = clone $monday;
                $sunday->modify('+6 days');
                $labelStart = $monday < $periodStart ? clone $periodStart : clone $monday;
                $labelEnd = $sunday > $periodEnd ? clone $periodEnd : clone $sunday;
                $buckets[$key] = [
                    'week' => $labelStart->format('d M') . ' – ' . $labelEnd->format('d M'),
                    'lots' => 0.0,
                    'trades' => 0,
                    'logins' => [],
                    'tradingResult' => 0.0,
                ];
            }
            $buckets[$key]['lots'] += $this->lots($row['volume']);
            $buckets[$key]['trades'] += 1;
            $buckets[$key]['logins'][(string) $row['trading_login']] = true;
            $buckets[$key]['tradingResult'] += $this->netFromStats($row);
        }
        ksort($buckets);
        $items = [];
        $totalLots = 0.0;
        $totalTrades = 0;
        $totalResult = 0.0;
        $allLogins = [];
        foreach ($buckets as $bucket) {
            $lots = round($bucket['lots'], 2);
            $result = $this->money($bucket['tradingResult']);
            $accounts = count($bucket['logins']);
            $items[] = [
                'week' => $bucket['week'],
                'lots' => $lots,
                'trades' => $bucket['trades'],
                'accountsTrading' => $accounts,
                'tradingResult' => $result,
            ];
            $totalLots += $lots;
            $totalTrades += $bucket['trades'];
            $totalResult += $result;
            foreach (array_keys($bucket['logins']) as $login) {
                $allLogins[$login] = true;
            }
        }
        return [
            'items' => $items,
            'total' => [
                'lots' => round($totalLots, 2),
                'trades' => $totalTrades,
                'accountsTrading' => count($allLogins),
                'tradingResult' => $this->money($totalResult),
            ],
        ];
    }

    private function emptyStatement($ibPartnerId, $startServer, $endServer, $partner)
    {
        $startDate = $startServer ? substr($startServer, 0, 10) : '';
        $endDate = $endServer ? substr($endServer, 0, 10) : '';
        return [
            'partner' => [
                'id' => (int) $ibPartnerId,
                'ibCode' => (string) ($partner['ibCode'] ?? ''),
                'name' => (string) ($partner['name'] ?? ''),
            ],
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
                'issued' => $endDate,
            ],
            'currency' => 'USD',
            'headline' => [
                'clientCount' => 0,
                'accountCount' => 0,
                'fundedCount' => 0,
                'tradedCount' => 0,
                'totalDeposits' => 0.0,
                'totalWithdrawals' => 0.0,
                'netDeposits' => 0.0,
                'closingBalance' => 0.0,
            ],
            'movement' => [
                'openingBalance' => 0.0,
                'deposits' => 0.0,
                'withdrawals' => 0.0,
                'netDeposits' => 0.0,
                'tradingResult' => 0.0,
                'commissionFees' => 0.0,
                'swap' => 0.0,
                'closingBalance' => 0.0,
                'unrealised' => 0.0,
                'closingEquity' => 0.0,
            ],
            'accounts' => [],
            'accountsTotal' => [
                'accountCount' => 0,
                'deposits' => 0.0,
                'withdrawals' => 0.0,
                'netDeposits' => 0.0,
                'lots' => 0.0,
                'trades' => 0,
                'tradingResult' => 0.0,
                'balance' => 0.0,
            ],
            'instruments' => $this->emptyInstrumentPayload(),
            'weeks' => $this->emptyWeekPayload(),
        ];
    }

    private function emptyInstrumentPayload()
    {
        return [
            'items' => [],
            'total' => [
                'lots' => 0.0,
                'shareOfVolume' => 0.0,
                'tradingResult' => 0.0,
                'accountsTraded' => 0,
            ],
        ];
    }

    private function emptyWeekPayload()
    {
        return [
            'items' => [],
            'total' => [
                'lots' => 0.0,
                'trades' => 0,
                'accountsTrading' => 0,
                'tradingResult' => 0.0,
            ],
        ];
    }

    private function emptyOrderStats()
    {
        return [
            'volume' => 0.0,
            'trades' => 0,
            'profit' => 0.0,
            'commission' => 0.0,
            'swap' => 0.0,
            'taxes' => 0.0,
        ];
    }

    private function netFromStats($stats)
    {
        return (float) ($stats['profit'] ?? 0)
            + (float) ($stats['commission'] ?? 0)
            + (float) ($stats['swap'] ?? 0)
            + (float) ($stats['taxes'] ?? 0);
    }

    private function lots($volume)
    {
        return round((float) $volume / Order::VOLUME_PER_LOT, 2);
    }

    private function money($value)
    {
        return round((float) $value, 2);
    }

    private function inParams(array $values, $prefix)
    {
        $placeholders = [];
        $params = [];
        foreach (array_values($values) as $index => $value) {
            $key = $prefix . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $value;
        }
        return [implode(',', $placeholders), $params];
    }

    private function convertDateToServerTimezone($dateString)
    {
        if ($dateString === null || $dateString === '') {
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
            return null;
        }
    }

    private function convertEndDateToServerTimezone($dateString)
    {
        if ($dateString === null || $dateString === '') {
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
            return null;
        }
    }

    private function requireReadAccess()
    {
        $this->requireAdmin();
        AuthMiddleware::checkAnyPermission([self::READ_PERMISSION, self::PAGE_KEY]);
    }

    private function requireExportAccess()
    {
        $this->requireReadAccess();
        AuthMiddleware::checkPermission(self::EXPORT_PERMISSION);
    }

    private function requireAdmin()
    {
        $payload = JWT::getPayload();
        if (!$payload || ($payload['type'] ?? '') !== 'admin') {
            Response::forbidden('Admin authentication required');
        }
        $userId = $payload['userId'] ?? null;
        if (!$userId) {
            Response::unauthorized('Invalid token payload');
        }
        return ['userId' => $userId];
    }

    private function isSafeJobId($value)
    {
        $value = trim((string) $value);
        return $value !== '' && strlen($value) <= 80 && preg_match('/^[A-Za-z0-9._-]+$/', $value);
    }

    private function dispatchSwooleTask(array $payload)
    {
        $address = config_swoole_address();
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($address, $errno, $errstr, 1.0);
        if (!$socket) {
            throw new Exception('Failed to connect myswoole: ' . $errstr . ' (' . $errno . ')');
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            fclose($socket);
            throw new Exception('Failed to encode task payload');
        }
        $written = @fwrite($socket, $json . '$$$###');
        fclose($socket);
        if ($written === false || $written <= 0) {
            throw new Exception('Failed to send task to myswoole');
        }
    }

    private function exportProgressPayload($progress)
    {
        if ($progress === null) {
            return ['active' => false];
        }
        return [
            'active' => true,
            'jobId' => (string) ($progress['jobId'] ?? ''),
            'status' => (string) ($progress['status'] ?? ''),
            'percent' => (int) ($progress['percent'] ?? 0),
            'message' => (string) ($progress['message'] ?? ''),
            'processed' => (int) ($progress['processed'] ?? 0),
            'total' => (int) ($progress['total'] ?? 0),
            'downloadReady' => !empty($progress['downloadReady']) || (($progress['status'] ?? '') === 'done'),
            'fileName' => (string) ($progress['fileName'] ?? ''),
        ];
    }
}
