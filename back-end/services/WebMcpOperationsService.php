<?php

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';
require_once __DIR__ . '/SalesPerformanceMetrics.php';
require_once __DIR__ . '/WebMcpReportService.php';
require_once __DIR__ . '/WebMcpAdminLogService.php';
require_once __DIR__ . '/WebMcpSalesService.php';
require_once __DIR__ . '/../models/AdminUser.php';
require_once __DIR__ . '/../models/AdminPermission.php';

class WebMcpOperationsService
{
    private $db;
    private $policy;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $config = require __DIR__ . '/../config/app.php';
        $this->policy = self::policy($config['operations_dashboard'] ?? []);
    }

    public static function policy(array $config = []): array
    {
        return [
            'highValueAmount' => (float)self::bounded($config['high_value_amount'] ?? 10000, 1, 1000000000),
            'kycOverdueHours' => self::bounded($config['kyc_overdue_hours'] ?? 24, 1, 87600),
            'auditMutationBurstCount' => self::bounded($config['audit_mutation_burst_count'] ?? 5, 2, 100),
            'auditMutationWindowMinutes' => self::bounded($config['audit_mutation_window_minutes'] ?? 15, 1, 1440),
            'queueLimit' => self::bounded($config['queue_limit'] ?? 25, 1, 100),
            'queueReservePerType' => self::bounded($config['queue_reserve_per_type'] ?? 1, 0, 10),
            'staleAfterSeconds' => self::bounded($config['stale_after_seconds'] ?? 300, 30, 3600),
        ];
    }

    public function getOverview(array $input): array
    {
        $scopes = $this->resolveScopes();
        $metrics = [
            'netFunding' => ['status' => 'restricted'],
            'pendingHighValueTransactions' => ['status' => 'restricted'],
            'overdueKyc' => ['status' => 'restricted'],
            'operationalAlerts' => ['status' => 'restricted'],
        ];
        $fundingTrend = ['status' => 'restricted'];
        $sales = ['status' => 'restricted'];
        $ib = ['status' => 'restricted'];
        $recentActivity = ['status' => 'restricted', 'items' => []];
        $attentionItems = [];
        $attentionTotal = 0;
        $sectionErrors = [];

        if ($scopes['funding']['access'] !== 'restricted') {
            try {
                $funding = $this->fundingSection($input, $scopes['funding']);
                $metrics['netFunding'] = $funding['netFunding'];
                $metrics['pendingHighValueTransactions'] = $funding['pendingHighValueTransactions'];
                $fundingTrend = $funding['trend'];
                $attentionItems = array_merge($attentionItems, $funding['attentionItems']);
                $attentionTotal += $funding['attentionTotal'];
            } catch (Throwable $exception) {
                $this->sectionFailure('funding', $exception, $sectionErrors);
                $metrics['netFunding'] = self::errorSection();
                $metrics['pendingHighValueTransactions'] = self::errorSection();
                $fundingTrend = self::errorSection();
            }
        }

        if ($scopes['kyc']['access'] !== 'restricted') {
            try {
                $kyc = $this->kycSection($input, $scopes['kyc']);
                $metrics['overdueKyc'] = $kyc['metric'];
                $attentionItems = array_merge($attentionItems, $kyc['attentionItems']);
                $attentionTotal += $kyc['attentionTotal'];
            } catch (Throwable $exception) {
                $this->sectionFailure('kyc', $exception, $sectionErrors);
                $metrics['overdueKyc'] = self::errorSection();
            }
        }

        if ($scopes['clients']['access'] !== 'restricted') {
            try {
                $clients = $this->unassignedClientSection($input, $scopes['clients']);
                $attentionItems = array_merge($attentionItems, $clients['attentionItems']);
                $attentionTotal += $clients['attentionTotal'];
            } catch (Throwable $exception) {
                $this->sectionFailure('clients', $exception, $sectionErrors);
            }
        }

        if ($scopes['audit']['access'] !== 'restricted') {
            try {
                $audit = $this->auditSection($input);
                $metrics['operationalAlerts'] = $audit['metric'];
                $attentionItems = array_merge($attentionItems, $audit['attentionItems']);
                $attentionTotal += $audit['attentionTotal'];
                $recentActivity = $this->recentExportActivity();
            } catch (Throwable $exception) {
                $this->sectionFailure('audit', $exception, $sectionErrors);
                $metrics['operationalAlerts'] = self::errorSection();
            }
        }

        if ($scopes['sales']['access'] !== 'restricted') {
            try {
                $sales = $this->salesSection($input, $scopes['sales']);
            } catch (Throwable $exception) {
                $this->sectionFailure('sales', $exception, $sectionErrors);
                $sales = self::errorSection();
            }
        }

        if ($scopes['ib']['access'] !== 'restricted') {
            try {
                $ib = $this->ibSection($input, $scopes['ib']);
            } catch (Throwable $exception) {
                $this->sectionFailure('ib', $exception, $sectionErrors);
                $ib = self::errorSection();
            }
        }

        $queueSeverity = (string)($input['severity'] ?? 'all');
        $queueType = (string)($input['exceptionType'] ?? 'all');
        $filteredAttentionItems = self::filterAttentionItems($attentionItems, $queueSeverity, $queueType);
        $attentionItems = self::rankAndLimitAttentionItems(
            $filteredAttentionItems,
            $this->policy['queueLimit'],
            $this->policy['queueReservePerType']
        );
        $queueTotal = ($queueSeverity !== 'all' || $queueType !== 'all')
            ? count($filteredAttentionItems)
            : $attentionTotal;

        return [
            'generatedAt' => gmdate('c'),
            'period' => [
                'startDate' => $input['startDate'],
                'endDate' => $input['endDate'],
                'timezone' => [
                    'offsetMinutes' => $input['tzOffset'],
                    'label' => SalesPerformanceMetrics::offsetLabel($input['tzOffset']),
                ],
            ],
            'policy' => $this->policy,
            'scope' => array_map([self::class, 'publicScope'], $scopes),
            'metrics' => $metrics,
            'attentionQueue' => [
                'items' => $attentionItems,
                'total' => $queueTotal,
                'truncated' => $queueTotal > count($attentionItems),
            ],
            'fundingTrend' => $fundingTrend,
            'sales' => $sales,
            'ib' => $ib,
            'recentActivity' => $recentActivity,
            'sectionErrors' => $sectionErrors,
        ];
    }

    public static function rankAndLimitAttentionItems(array $items, int $limit, int $reservePerKind = 0): array
    {
        $weights = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
        $compare = static function (array $left, array $right) use ($weights): int {
            $severity = ($weights[$right['severity'] ?? 'low'] ?? 0)
                <=> ($weights[$left['severity'] ?? 'low'] ?? 0);
            if ($severity !== 0) {
                return $severity;
            }
            $age = (float)($right['ageHours'] ?? 0) <=> (float)($left['ageHours'] ?? 0);
            if ($age !== 0) {
                return $age;
            }
            return strcmp((string)($left['id'] ?? ''), (string)($right['id'] ?? ''));
        };
        usort($items, $compare);
        $limit = max(1, $limit);
        $reservePerKind = max(0, $reservePerKind);
        if ($reservePerKind === 0 || count($items) <= $limit) {
            return array_slice($items, 0, $limit);
        }

        $reservedIndexes = [];
        $reservedByKind = [];
        foreach ($items as $index => $item) {
            $kind = trim((string)($item['kind'] ?? ''));
            if ($kind === '' || (($reservedByKind[$kind] ?? 0) >= $reservePerKind)) {
                continue;
            }
            $reservedIndexes[$index] = true;
            $reservedByKind[$kind] = ($reservedByKind[$kind] ?? 0) + 1;
        }

        $reserved = [];
        foreach (array_keys($reservedIndexes) as $index) {
            $reserved[] = $items[$index];
            if (count($reserved) >= $limit) break;
        }
        $selectedIndexes = array_fill_keys(array_slice(array_keys($reservedIndexes), 0, $limit), true);
        $result = $reserved;
        foreach ($items as $index => $item) {
            if (isset($selectedIndexes[$index])) continue;
            if (count($result) >= $limit) break;
            $result[] = $item;
        }
        usort($result, $compare);
        return $result;
    }

    public static function filterAttentionItems(array $items, string $severity = 'all', string $type = 'all'): array
    {
        return array_values(array_filter($items, static function (array $item) use ($severity, $type): bool {
            $matchesSeverity = $severity === 'all' || (string)($item['severity'] ?? '') === $severity;
            $matchesType = $type === 'all' || (string)($item['kind'] ?? '') === $type;
            return $matchesSeverity && $matchesType;
        }));
    }

    public static function projectCurrencyTotals(array $rows): array
    {
        $totals = [];
        foreach ($rows as $row) {
            $currency = self::currency($row['currency'] ?? null);
            if (!isset($totals[$currency])) {
                $totals[$currency] = [
                    'currency' => $currency,
                    'deposits' => 0.0,
                    'withdrawals' => 0.0,
                    'netFlow' => 0.0,
                ];
            }
            $amount = (float)($row['totalAmount'] ?? 0);
            if (($row['transactionType'] ?? '') === 'deposit') {
                $totals[$currency]['deposits'] += $amount;
            } elseif (($row['transactionType'] ?? '') === 'withdrawal') {
                $totals[$currency]['withdrawals'] += $amount;
            }
        }
        ksort($totals, SORT_STRING);
        foreach ($totals as &$total) {
            $total['netFlow'] = $total['deposits'] - $total['withdrawals'];
        }
        unset($total);
        return array_values($totals);
    }

    public static function ibDisplayName(array $row): string
    {
        $isSeededDemo = (bool)preg_match('/^DEMO-/i', trim((string)($row['ibCode'] ?? '')));
        foreach (['companyName', 'adminAlias', 'clientAlias'] as $key) {
            $value = trim((string)($row[$key] ?? ''));
            if ($value !== '') {
                if ($isSeededDemo) {
                    $value = trim((string)preg_replace('/\bdemo\b\s*/i', '', $value));
                }
                if ($value !== '') return $value;
            }
        }
        $clientName = trim((string)($row['firstName'] ?? '') . ' ' . (string)($row['lastName'] ?? ''));
        return $clientName !== '' ? $clientName : 'IB #' . (int)($row['id'] ?? 0);
    }

    public static function ibDisplayCode(array $row): string
    {
        $code = trim((string)($row['ibCode'] ?? ''));
        return preg_match('/^DEMO-/i', $code)
            ? 'IB #' . (int)($row['id'] ?? 0)
            : ($code !== '' ? $code : 'IB #' . (int)($row['id'] ?? 0));
    }

    private function resolveScopes(): array
    {
        $salesAccess = AdminSalesPermission::getCurrentSalesAccess();
        return [
            'funding' => $this->clientScope(
                'page_fundingreport_readonly',
                'page_fundingreport',
                ['page_fundingreport_export']
            ),
            'kyc' => $this->clientScope(
                'page_kyclist_readonly',
                'page_kyclist',
                ['page_kyclist_export']
            ),
            'clients' => $this->unassignedClientScope(),
            'audit' => $this->simpleScope(
                ['page_operationlogreport_readonly'],
                ['page_operationlogreport_export']
            ),
            'sales' => !empty($salesAccess['can_view_daily']) && !empty($salesAccess['can_view_sales'])
                ? [
                    'access' => !empty($salesAccess['can_view_all_sales']) ? 'team' : 'self',
                    'canExport' => false,
                    'salesAccess' => $salesAccess,
                ]
                : self::restrictedScope(),
            'ib' => $this->clientScope(
                'page_iblist_readonly',
                'page_iblist',
                ['page_ibstatement_export']
            ),
        ];
    }

    private function clientScope(string $readPermission, string $scopePage, array $exportPermissions): array
    {
        if (!AuthMiddleware::hasPermission($readPermission)) {
            return self::restrictedScope();
        }
        $scope = AdminSalesPermission::getClientDataScopeForPage($scopePage);
        if (($scope['scope'] ?? 'none') === 'none') {
            return self::restrictedScope();
        }
        return [
            'access' => $scope['scope'],
            'canExport' => $this->hasAnyPermission($exportPermissions),
            'restrictToSalesId' => $scope['restrict_to_sales_id'] ?? null,
        ];
    }

    private function unassignedClientScope(): array
    {
        if (!AuthMiddleware::hasPermission('page_clientslist_readonly')) {
            return self::restrictedScope();
        }
        $scope = AdminSalesPermission::getClientDataScopeForPage('page_clientslist');
        if (($scope['scope'] ?? 'none') !== 'all') {
            return self::restrictedScope();
        }
        return [
            'access' => 'all',
            'canExport' => AuthMiddleware::hasPermission('page_clientslist_export'),
        ];
    }

    private function simpleScope(array $readPermissions, array $exportPermissions): array
    {
        if (!$this->hasAnyPermission($readPermissions)) {
            return self::restrictedScope();
        }
        return [
            'access' => 'all',
            'canExport' => $this->hasAnyPermission($exportPermissions),
        ];
    }

    private function hasAnyPermission(array $permissionKeys): bool
    {
        foreach ($permissionKeys as $permissionKey) {
            if (AuthMiddleware::hasPermission($permissionKey)) {
                return true;
            }
        }
        return false;
    }

    private function fundingSection(array $input, array $scope): array
    {
        $dateExpression = $this->localDateExpression('requestedAt', $input['tzOffset']);
        $conditions = [
            "transactionType IN ('deposit', 'withdrawal')",
            "LOWER(status) = 'completed'",
            "{$dateExpression} >= :fundingStartDate",
            "{$dateExpression} <= :fundingEndDate",
        ];
        $params = [
            'fundingStartDate' => $input['startDate'],
            'fundingEndDate' => $input['endDate'],
        ];
        $this->applyClientScope($conditions, $params, $scope, 'userId', 'fundingSalesId');
        $where = implode(' AND ', $conditions);
        $currency = "COALESCE(NULLIF(UPPER(TRIM(currency)), ''), 'USD')";

        $totals = $this->db->fetchAll(
            "SELECT {$currency} AS currency, transactionType,
                    COALESCE(SUM(amount), 0) AS totalAmount
             FROM vAllTransactions WHERE {$where}
             GROUP BY {$currency}, transactionType",
            $params
        );
        $trendRows = $this->db->fetchAll(
            "SELECT {$dateExpression} AS activityDate, {$currency} AS currency,
                    transactionType, COALESCE(SUM(amount), 0) AS totalAmount
             FROM vAllTransactions WHERE {$where}
             GROUP BY activityDate, {$currency}, transactionType
             ORDER BY activityDate ASC, currency ASC",
            $params
        );
        $highValue = $this->highValueTransactions($input, $scope);

        return [
            'netFunding' => [
                'status' => 'ready',
                'totals' => self::projectCurrencyTotals($totals),
            ],
            'pendingHighValueTransactions' => [
                'status' => 'ready',
                'count' => $highValue['pendingCount'],
                'totals' => $highValue['pendingTotals'],
            ],
            'trend' => [
                'status' => 'ready',
                'points' => self::projectTrendRows($trendRows),
            ],
            'attentionItems' => $highValue['items'],
            'attentionTotal' => $highValue['attentionTotal'],
        ];
    }

    private function highValueTransactions(array $input, array $scope): array
    {
        $dateExpression = $this->localDateExpression('requestedAt', $input['tzOffset']);
        $asOf = $this->asOfServerTime($input);
        $conditions = [
            "transactionType IN ('deposit', 'withdrawal')",
            'ABS(amount) >= :highValueAmount',
            "{$dateExpression} <= :highValueEndDate",
            "(LOWER(status) IN ('pending', 'processing')
                OR (LOWER(status) = 'rejected' AND {$dateExpression} >= :highValueStartDate))",
        ];
        $params = [
            'highValueAmount' => $this->policy['highValueAmount'],
            'highValueStartDate' => $input['startDate'],
            'highValueEndDate' => $input['endDate'],
            'highValueAsOf' => $asOf,
        ];
        $this->applyClientScope($conditions, $params, $scope, 'userId', 'highValueSalesId');
        $where = implode(' AND ', $conditions);
        $currency = "COALESCE(NULLIF(UPPER(TRIM(currency)), ''), 'USD')";
        $rows = $this->db->fetchAll(
            "SELECT id, transactionId, transactionType, status, amount,
                    {$currency} AS currency, requestedAt, userId,
                    CONCAT_WS(' ', firstName, lastName) AS clientName,
                    GREATEST(0, TIMESTAMPDIFF(HOUR, requestedAt, :highValueAsOf)) AS ageHours
             FROM vAllTransactions WHERE {$where}
             ORDER BY requestedAt ASC, id ASC LIMIT 100",
            $params
        );
        $aggregateParams = $params;
        unset($aggregateParams['highValueAsOf']);
        $aggregateConditions = array_values(array_filter(
            $conditions,
            static fn($condition) => strpos($condition, 'highValueAsOf') === false
        ));
        $aggregateWhere = implode(' AND ', $aggregateConditions)
            . " AND LOWER(status) IN ('pending', 'processing')";
        $pendingRows = $this->db->fetchAll(
            "SELECT {$currency} AS currency, COUNT(*) AS transactionCount,
                    COALESCE(SUM(amount), 0) AS totalAmount
             FROM vAllTransactions WHERE {$aggregateWhere}
             GROUP BY {$currency} ORDER BY currency ASC",
            $aggregateParams
        );
        $pendingCount = 0;
        $pendingTotals = [];
        foreach ($pendingRows as $row) {
            $count = (int)($row['transactionCount'] ?? 0);
            $pendingCount += $count;
            $pendingTotals[] = [
                'currency' => self::currency($row['currency'] ?? null),
                'amount' => (float)($row['totalAmount'] ?? 0),
                'count' => $count,
            ];
        }
        $attentionCount = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM vAllTransactions WHERE {$aggregateWhere}",
            $aggregateParams
        );
        $rejectedCount = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM vAllTransactions WHERE "
                . implode(' AND ', $aggregateConditions)
                . " AND LOWER(status) = 'rejected'",
            $aggregateParams
        );

        return [
            'pendingCount' => $pendingCount,
            'pendingTotals' => $pendingTotals,
            'attentionTotal' => (int)($attentionCount['total'] ?? 0)
                + (int)($rejectedCount['total'] ?? 0),
            'items' => array_map(static function (array $row): array {
                $status = strtolower((string)($row['status'] ?? ''));
                $transactionId = (string)($row['transactionId'] ?? '');
                return [
                    'id' => 'transaction:' . ($row['transactionType'] ?? 'unknown') . ':' . (int)$row['id'],
                    'kind' => 'transaction',
                    'severity' => $status === 'rejected' ? 'medium' : 'high',
                    'title' => ucfirst(str_replace('_', ' ', (string)$row['transactionType'])) . ' ' . $transactionId,
                    'reason' => $status === 'rejected'
                        ? 'A high-value transaction was rejected during the selected period.'
                        : 'A high-value transaction is still awaiting completion.',
                    'ageHours' => max(0, (int)($row['ageHours'] ?? 0)),
                    'occurredAt' => $row['requestedAt'] ?? null,
                    'relatedLabel' => trim((string)($row['clientName'] ?? '')) ?: $transactionId,
                    'transactionType' => $row['transactionType'] ?? null,
                    'transactionId' => $transactionId,
                    'recordId' => (int)($row['id'] ?? 0),
                    'clientId' => (int)($row['userId'] ?? 0),
                    'amount' => (float)($row['amount'] ?? 0),
                    'currency' => self::currency($row['currency'] ?? null),
                    'status' => $status,
                    'exportDomain' => 'funding',
                ];
            }, $rows),
        ];
    }

    private function kycSection(array $input, array $scope): array
    {
        $asOf = $this->asOfServerTime($input);
        $waiting = 'GREATEST(0, TIMESTAMPDIFF(HOUR, COALESCE(s.submittedAt, s.createdAt), :kycAsOf))';
        $conditions = [
            "s.submissionStatus IN ('pending', 'submitted', 'under_review')",
            "COALESCE(s.submittedAt, s.createdAt) <= :kycAsOf",
            "{$waiting} >= :kycOverdueHours",
        ];
        $params = [
            'kycAsOf' => $asOf,
            'kycOverdueHours' => $this->policy['kycOverdueHours'],
        ];
        $this->applyClientScope($conditions, $params, $scope, 's.clientId', 'kycSalesId');
        $where = implode(' AND ', $conditions);
        $count = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM clientKycSubmissions s WHERE {$where}",
            $params
        );
        $rows = $this->db->fetchAll(
            "SELECT s.id AS submissionId, s.clientId, s.submissionStatus,
                    s.submittedAt, s.createdAt, s.reviewedBy,
                    CONCAT_WS(' ', cu.firstName, cu.lastName) AS clientName,
                    {$waiting} AS waitingHours
             FROM clientKycSubmissions s
             INNER JOIN clientUsers cu ON cu.id = s.clientId
             WHERE {$where}
             ORDER BY waitingHours DESC, s.id ASC LIMIT 100",
            $params
        );
        $sla = $this->policy['kycOverdueHours'];
        return [
            'metric' => ['status' => 'ready', 'count' => (int)($count['total'] ?? 0)],
            'attentionTotal' => (int)($count['total'] ?? 0),
            'attentionItems' => array_map(static function (array $row) use ($sla): array {
                $hours = max(0, (int)($row['waitingHours'] ?? 0));
                $submissionId = (int)($row['submissionId'] ?? 0);
                return [
                    'id' => 'kyc:' . $submissionId,
                    'kind' => 'kyc',
                    'severity' => $hours >= ($sla * 2) ? 'high' : 'medium',
                    'title' => 'KYC submission #' . $submissionId,
                    'reason' => 'The KYC review has exceeded the expected review time.',
                    'ageHours' => $hours,
                    'occurredAt' => $row['submittedAt'] ?? $row['createdAt'] ?? null,
                    'relatedLabel' => trim((string)($row['clientName'] ?? '')) ?: 'KYC #' . $submissionId,
                    'submissionId' => $submissionId,
                    'clientId' => (int)($row['clientId'] ?? 0),
                    'status' => (string)($row['submissionStatus'] ?? ''),
                    'assigned' => !empty($row['reviewedBy']),
                    'exportDomain' => 'kyc',
                ];
            }, $rows),
        ];
    }

    private function unassignedClientSection(array $input, array $scope): array
    {
        if (($scope['access'] ?? '') !== 'all') {
            return ['attentionItems' => [], 'attentionTotal' => 0];
        }
        $asOf = $this->asOfServerTime($input);
        $count = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM clientUsers cu
             WHERE cu.createdAt <= :clientAsOf
               AND NOT EXISTS (SELECT 1 FROM sales_bind sb WHERE sb.clientId = cu.id)",
            ['clientAsOf' => $asOf]
        );
        $rows = $this->db->fetchAll(
            "SELECT cu.id, cu.firstName, cu.lastName, cu.createdAt,
                    GREATEST(0, TIMESTAMPDIFF(HOUR, cu.createdAt, :clientAsOf)) AS ageHours
             FROM clientUsers cu
             WHERE cu.createdAt <= :clientAsOf
               AND NOT EXISTS (
                   SELECT 1 FROM sales_bind sb WHERE sb.clientId = cu.id
               )
             ORDER BY cu.createdAt ASC, cu.id ASC LIMIT 100",
            ['clientAsOf' => $asOf]
        );
        $items = array_map(static function (array $row): array {
            $clientId = (int)($row['id'] ?? 0);
            $age = max(0, (int)($row['ageHours'] ?? 0));
            $name = trim((string)($row['firstName'] ?? '') . ' ' . (string)($row['lastName'] ?? ''));
            return [
                'id' => 'client:' . $clientId,
                'kind' => 'client',
                'severity' => $age >= 168 ? 'high' : 'medium',
                'title' => 'Client without Sales assignment',
                'reason' => 'No Sales representative is assigned to this client.',
                'ageHours' => $age,
                'occurredAt' => $row['createdAt'] ?? null,
                'relatedLabel' => $name !== '' ? $name : 'Client #' . $clientId,
                'clientId' => $clientId,
                'exportDomain' => 'clients',
            ];
        }, $rows);
        return [
            'attentionItems' => $items,
            'attentionTotal' => (int)($count['total'] ?? 0),
        ];
    }

    private function auditSection(array $input): array
    {
        $logService = new WebMcpAdminLogService();
        $filters = $logService->scopeOperationLogFilters(['modelKey' => 'all']);
        $modelKeys = $filters['modelKeys'] ?? [];
        if (!$modelKeys) {
            return [
                'metric' => ['status' => 'ready', 'count' => 0],
                'attentionItems' => [],
                'attentionTotal' => 0,
            ];
        }
        $whereModels = [];
        $params = $this->utcBoundaries($input);
        foreach (array_values($modelKeys) as $index => $modelKey) {
            $key = 'auditModel' . $index;
            $whereModels[] = "l.modelKey = :{$key}";
            $params[$key] = $modelKey;
        }
        $rows = $this->db->fetchAll(
            "SELECT l.id, l.operatorId, l.modelKey, l.subModuleKey,
                    l.operationTypeKey, l.targetId, l.detailEn, l.operatedAt,
                    COALESCE(NULLIF(au.fullName, ''), au.username, CONCAT('Admin #', l.operatorId)) AS operatorName
             FROM adminOperationLogs l
             LEFT JOIN adminUsers au ON au.id = l.operatorId
             WHERE (" . implode(' OR ', $whereModels) . ")
               AND l.operatedAt >= :auditStartAt
               AND l.operatedAt <= :auditEndAt
             ORDER BY l.operatedAt ASC, l.id ASC LIMIT 1000",
            $params
        );
        $items = [];
        $auditAsOf = strtotime((string)$params['auditEndAt'] . ' UTC') ?: time();
        $sensitiveModules = ['accounts', 'role_management'];
        $mutationTypes = ['add', 'edit', 'delete', 'enable', 'disable', 'assign'];
        foreach ($rows as $row) {
            if (
                in_array((string)($row['subModuleKey'] ?? ''), $sensitiveModules, true)
                && in_array((string)($row['operationTypeKey'] ?? ''), $mutationTypes, true)
            ) {
                $items[] = $this->auditItem(
                    $row,
                    'critical',
                    'A sensitive administrator account or role permission was changed.',
                    $auditAsOf
                );
            }
        }
        foreach ($this->mutationBursts($rows) as $burst) {
            $items[] = $this->auditItem(
                $burst['row'],
                'medium',
                $burst['count'] . ' administrator mutations occurred within '
                    . $this->policy['auditMutationWindowMinutes'] . ' minutes.',
                $auditAsOf
            );
        }
        return [
            'metric' => ['status' => 'ready', 'count' => count($items)],
            'attentionItems' => $items,
            'attentionTotal' => count($items),
        ];
    }

    private function recentExportActivity(): array
    {
        $logService = new WebMcpAdminLogService();
        $filters = $logService->scopeOperationLogFilters(['modelKey' => 'all']);
        $modelKeys = $filters['modelKeys'] ?? [];
        if (!$modelKeys) {
            return ['status' => 'ready', 'items' => []];
        }

        $whereModels = [];
        $params = [];
        foreach (array_values($modelKeys) as $index => $modelKey) {
            $key = 'recentExportModel' . $index;
            $whereModels[] = "l.modelKey = :{$key}";
            $params[$key] = $modelKey;
        }
        $rows = $this->db->fetchAll(
            "SELECT l.id, l.subModuleNameEn, l.moduleNameEn, l.operationTypeKey,
                    l.operatedAt, l.targetId,
                    COALESCE(NULLIF(au.fullName, ''), au.username, CONCAT('Admin #', l.operatorId)) AS operatorName
             FROM adminOperationLogs l
             LEFT JOIN adminUsers au ON au.id = l.operatorId
             WHERE (" . implode(' OR ', $whereModels) . ")
               AND l.operationTypeKey = 'export'
             ORDER BY l.operatedAt DESC, l.id DESC
             LIMIT 6",
            $params
        );
        return [
            'status' => 'ready',
            'items' => array_map(static function (array $row): array {
                $id = (int)($row['id'] ?? 0);
                $module = trim((string)($row['subModuleNameEn'] ?? ''))
                    ?: trim((string)($row['moduleNameEn'] ?? ''))
                    ?: 'administrative data';
                return [
                    'id' => 'system-export:' . $id,
                    'kind' => 'export',
                    'label' => 'Exported ' . $module,
                    'createdAt' => $row['operatedAt'] ?? null,
                    'operationLogId' => $id,
                    'operatorName' => (string)($row['operatorName'] ?? ''),
                ];
            }, $rows),
        ];
    }

    private function mutationBursts(array $rows): array
    {
        $byOperator = [];
        foreach ($rows as $row) {
            if (($row['operationTypeKey'] ?? '') === 'view') {
                continue;
            }
            $byOperator[(int)($row['operatorId'] ?? 0)][] = $row;
        }
        $bursts = [];
        $window = $this->policy['auditMutationWindowMinutes'] * 60;
        $minimum = $this->policy['auditMutationBurstCount'];
        foreach ($byOperator as $operatorRows) {
            $start = 0;
            $best = null;
            foreach ($operatorRows as $end => $row) {
                $endTime = strtotime((string)($row['operatedAt'] ?? '')) ?: 0;
                while ($start < $end) {
                    $startTime = strtotime((string)($operatorRows[$start]['operatedAt'] ?? '')) ?: 0;
                    if ($endTime - $startTime <= $window) {
                        break;
                    }
                    $start++;
                }
                $count = $end - $start + 1;
                if ($count >= $minimum && ($best === null || $count > $best['count'])) {
                    $best = ['count' => $count, 'row' => $row];
                }
            }
            if ($best !== null) {
                $bursts[] = $best;
            }
        }
        return $bursts;
    }

    private function auditItem(array $row, string $severity, string $reason, int $asOf): array
    {
        $id = (int)($row['id'] ?? 0);
        return [
            'id' => 'audit:' . $severity . ':' . $id,
            'kind' => 'audit',
            'severity' => $severity,
            'title' => 'Administrator activity by ' . (string)($row['operatorName'] ?? 'Unknown'),
            'reason' => $reason,
            'ageHours' => self::ageHours($row['operatedAt'] ?? null, $asOf),
            'occurredAt' => $row['operatedAt'] ?? null,
            'relatedLabel' => (string)($row['operatorName'] ?? 'Administrator'),
            'operationLogId' => $id,
            'operatorId' => (int)($row['operatorId'] ?? 0),
            'operationType' => (string)($row['operationTypeKey'] ?? ''),
            'module' => (string)($row['subModuleKey'] ?? ''),
            'exportDomain' => 'audit',
        ];
    }

    private function salesSection(array $input, array $scope): array
    {
        $config = require __DIR__ . '/../config/app.php';
        $salesRoleId = (int)($config['special_roles']['sales_role_id'] ?? 6);
        $permission = (new AdminPermission())->findByKey('page_salesdashboard_view');
        $permissionId = $permission ? (int)($permission['id'] ?? 0) : 0;
        $onlyUserId = ($scope['access'] ?? '') === 'team'
            ? null
            : (int)($scope['salesAccess']['admin_user_id'] ?? 0);
        $users = (new AdminUser())->getSalesUsers($salesRoleId, $permissionId, '', $onlyUserId);
        $ids = array_map(static fn(array $user): int => (int)$user['id'], $users);
        $transactions = SalesPerformanceMetrics::indexTransactionTotals(
            SalesPerformanceMetrics::transactionTotals(
                $ids,
                $input['startDate'],
                $input['endDate'],
                $input['tzOffset']
            )
        );
        $registrations = [];
        foreach (SalesPerformanceMetrics::registrationTotals(
            $ids,
            $input['startDate'],
            $input['endDate'],
            $input['tzOffset']
        ) as $row) {
            $registrations[(int)$row['salesId']] = $row;
        }
        $targets = $this->salesTargets($ids, substr($input['endDate'], 0, 7));
        $summary = [
            'deposits' => 0.0,
            'depositCount' => 0,
            'withdrawals' => 0.0,
            'withdrawalCount' => 0,
            'netDeposit' => 0.0,
            'newLeads' => 0,
            'newClients' => 0,
        ];
        $rows = [];
        foreach ($users as $user) {
            $id = (int)$user['id'];
            $transaction = $transactions[$id] ?? [
                'deposits' => 0.0,
                'depositCount' => 0,
                'withdrawals' => 0.0,
                'withdrawalCount' => 0,
            ];
            $registration = $registrations[$id] ?? [];
            $target = $targets[$id] ?? null;
            $targetAmount = $target['netDeposit'] ?? null;
            $row = [
                'id' => $id,
                'name' => trim((string)($user['fullName'] ?? '')) ?: (string)($user['username'] ?? 'Sales user'),
                'email' => $user['email'] ?? null,
                'deposits' => (float)$transaction['deposits'],
                'depositCount' => (int)$transaction['depositCount'],
                'withdrawals' => (float)$transaction['withdrawals'],
                'withdrawalCount' => (int)$transaction['withdrawalCount'],
                'netDeposit' => (float)$transaction['deposits'] - (float)$transaction['withdrawals'],
                'newLeads' => (int)($registration['newLeads'] ?? 0),
                'newClients' => (int)($registration['newClients'] ?? 0),
                'target' => [
                    'netDeposit' => $targetAmount,
                    'achievementRate' => $targetAmount !== null && $targetAmount > 0
                        ? round((((float)$transaction['deposits'] - (float)$transaction['withdrawals']) / $targetAmount) * 100, 2)
                        : null,
                ],
            ];
            $rows[] = $row;
            foreach ($summary as $key => $_) {
                $summary[$key] += $row[$key];
            }
        }
        $rows = array_values(array_filter($rows, static function (array $row): bool {
            return (float)$row['netDeposit'] !== 0.0
                || (int)$row['depositCount'] > 0
                || (int)$row['withdrawalCount'] > 0
                || (int)$row['newLeads'] > 0
                || (int)$row['newClients'] > 0;
        }));
        usort($rows, static function (array $left, array $right): int {
            $value = $right['netDeposit'] <=> $left['netDeposit'];
            return $value !== 0 ? $value : strcasecmp($left['name'], $right['name']);
        });
        $rankings = [];
        $lastValue = null;
        $lastRank = 0;
        foreach (array_slice($rows, 0, 5) as $index => $row) {
            if ($lastValue === null || $row['netDeposit'] !== $lastValue) {
                $lastRank = $index + 1;
                $lastValue = $row['netDeposit'];
            }
            $rankings[] = [
                'rank' => $lastRank,
                'sales' => ['id' => $row['id'], 'name' => $row['name'], 'email' => $row['email']],
                'value' => $row['netDeposit'],
                'target' => $row['target'],
            ];
        }
        return [
            'status' => 'ready',
            'range' => ['startDate' => $input['startDate'], 'endDate' => $input['endDate']],
            'scope' => $scope['access'],
            'summary' => $summary,
            'rankings' => $rankings,
        ];
    }

    private function salesTargets(array $ids, string $month): array
    {
        if (!$ids) return [];
        [$placeholders, $params] = SalesPerformanceMetrics::buildIdInClause($ids, 'targetSales');
        $params['targetMonth'] = $month;
        $rows = $this->db->fetchAll(
            "SELECT salesId, kpiTarget FROM salesMonthlyKpis
             WHERE salesId IN ({$placeholders}) AND kpiMonth = :targetMonth",
            $params
        );
        $targets = [];
        foreach ($rows as $row) {
            $target = isset($row['kpiTarget']) ? (float)$row['kpiTarget'] : null;
            $targets[(int)$row['salesId']] = ['netDeposit' => $target];
        }
        return $targets;
    }

    private function ibSection(array $input, array $scope): array
    {
        $conditions = ["ib.status = 'approved'"];
        $params = [];
        $this->applyClientScope($conditions, $params, $scope, 'ib.userId', 'ibSalesId');
        $where = implode(' AND ', $conditions);
        $dateExpression = $this->localDateExpression('ib.registrationDate', $input['tzOffset']);
        $summary = $this->db->fetchOne(
            "SELECT COUNT(DISTINCT ib.id) AS partnerCount,
                    COUNT(DISTINCT CASE WHEN b.isClient = 1 THEN b.childClientId END) AS clientCount,
                    COUNT(DISTINCT CASE
                        WHEN {$dateExpression} >= :ibStartDate AND {$dateExpression} <= :ibEndDate
                        THEN ib.id END) AS newPartnerCount
             FROM ibPartners ib
             LEFT JOIN ib_partner_bind b ON b.parentId = ib.id
             WHERE {$where}",
            array_merge($params, [
                'ibStartDate' => $input['startDate'],
                'ibEndDate' => $input['endDate'],
            ])
        );
        $leaders = $this->db->fetchAll(
            "SELECT ib.id, ib.ibCode, ib.companyName, ib.adminAlias, ib.clientAlias,
                    cu.firstName, cu.lastName,
                    COUNT(DISTINCT CASE WHEN b.isClient = 1 THEN b.childClientId END) AS clientCount
             FROM ibPartners ib
             LEFT JOIN clientUsers cu ON cu.id = ib.userId
             LEFT JOIN ib_partner_bind b ON b.parentId = ib.id
             WHERE {$where}
             GROUP BY ib.id, ib.ibCode, ib.companyName, ib.adminAlias, ib.clientAlias, cu.firstName, cu.lastName
             ORDER BY clientCount DESC, ib.id ASC LIMIT 5",
            $params
        );
        return [
            'status' => 'ready',
            'summary' => [
                'partnerCount' => (int)($summary['partnerCount'] ?? 0),
                'clientCount' => (int)($summary['clientCount'] ?? 0),
                'newPartnerCount' => (int)($summary['newPartnerCount'] ?? 0),
            ],
            'leaders' => array_map(static fn(array $row): array => [
                'id' => (int)($row['id'] ?? 0),
                'code' => self::ibDisplayCode($row),
                'name' => self::ibDisplayName($row),
                'clientCount' => (int)($row['clientCount'] ?? 0),
            ], $leaders),
        ];
    }

    private function applyClientScope(
        array &$conditions,
        array &$params,
        array $scope,
        string $clientColumn,
        string $parameter
    ): void {
        if (($scope['access'] ?? '') !== 'own') {
            return;
        }
        $conditions[] = "{$clientColumn} IN (
            SELECT clientId FROM sales_bind WHERE salesId = :{$parameter}
        )";
        $params[$parameter] = (int)($scope['restrictToSalesId'] ?? 0);
    }

    private function localDateExpression(string $column, int $tzOffset): string
    {
        $shift = SalesPerformanceMetrics::shiftMinutes($tzOffset);
        return "DATE(DATE_ADD({$column}, INTERVAL " . (int)$shift . ' MINUTE))';
    }

    private function asOfServerTime(array $input): string
    {
        $shift = SalesPerformanceMetrics::shiftMinutes($input['tzOffset']);
        $timestamp = strtotime($input['endDate'] . ' 23:59:59') - ($shift * 60);
        return date('Y-m-d H:i:s', $timestamp);
    }

    private function utcBoundaries(array $input): array
    {
        $offsetSeconds = $input['tzOffset'] * 60;
        return [
            'auditStartAt' => gmdate('Y-m-d H:i:s', strtotime($input['startDate'] . ' 00:00:00 UTC') - $offsetSeconds),
            'auditEndAt' => gmdate('Y-m-d H:i:s', strtotime($input['endDate'] . ' 23:59:59 UTC') - $offsetSeconds),
        ];
    }

    private static function projectTrendRows(array $rows): array
    {
        $points = [];
        foreach ($rows as $row) {
            $date = (string)($row['activityDate'] ?? '');
            $currency = self::currency($row['currency'] ?? null);
            $key = $date . ':' . $currency;
            if (!isset($points[$key])) {
                $points[$key] = [
                    'date' => $date,
                    'currency' => $currency,
                    'deposits' => 0.0,
                    'withdrawals' => 0.0,
                    'netFlow' => 0.0,
                ];
            }
            $amount = (float)($row['totalAmount'] ?? 0);
            if (($row['transactionType'] ?? '') === 'deposit') {
                $points[$key]['deposits'] = $amount;
            } elseif (($row['transactionType'] ?? '') === 'withdrawal') {
                $points[$key]['withdrawals'] = $amount;
            }
        }
        foreach ($points as &$point) {
            $point['netFlow'] = $point['deposits'] - $point['withdrawals'];
        }
        unset($point);
        return array_values($points);
    }

    private function sectionFailure(string $section, Throwable $exception, array &$errors): void
    {
        error_log('WebMCP operations dashboard ' . $section . ' error: ' . $exception->getMessage());
        $errors[] = [
            'section' => $section,
            'code' => 'SECTION_UNAVAILABLE',
            'message' => 'This dashboard section is temporarily unavailable.',
        ];
    }

    private static function publicScope(array $scope): array
    {
        return [
            'access' => $scope['access'] ?? 'restricted',
            'canExport' => (bool)($scope['canExport'] ?? false),
        ];
    }

    private static function restrictedScope(): array
    {
        return ['access' => 'restricted', 'canExport' => false];
    }

    private static function errorSection(): array
    {
        return [
            'status' => 'error',
            'message' => 'Unable to load this section.',
        ];
    }

    private static function currency($value): string
    {
        $currency = strtoupper(trim((string)$value));
        return preg_match('/^[A-Z0-9]{2,12}$/', $currency) ? $currency : 'USD';
    }

    private static function ageHours($value, ?int $asOf = null): int
    {
        $timestamp = strtotime((string)$value);
        return $timestamp === false
            ? 0
            : max(0, (int)floor((($asOf ?? time()) - $timestamp) / 3600));
    }

    private static function bounded($value, int $minimum, int $maximum): int
    {
        $number = is_numeric($value) ? (int)$value : $minimum;
        return max($minimum, min($maximum, $number));
    }
}
