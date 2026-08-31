<?php

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../models/AdminUser.php';
require_once __DIR__ . '/../models/AdminPermission.php';
require_once __DIR__ . '/SalesPerformanceMetrics.php';
require_once __DIR__ . '/../controllers/IbStatementReportController.php';
require_once __DIR__ . '/../controllers/CustomReportController.php';

class WebMcpReportService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getFundingSummary(array $input, array $scope): array
    {
        $summary = [
            'totalDeposits' => 0.0,
            'depositCount' => 0,
            'totalWithdrawals' => 0.0,
            'withdrawalCount' => 0,
            'totalInternalTransfer' => 0.0,
            'internalTransferCount' => 0,
            'netFlow' => 0.0,
        ];
        if (($scope['scope'] ?? '') === 'none') {
            return ['period' => $input, 'summary' => $summary];
        }

        $conditions = [
            'DATE(requestedAt) >= :startDate',
            'DATE(requestedAt) <= :endDate',
            "transactionType IN ('deposit', 'withdrawal', 'internal_transfer')",
        ];
        $params = ['startDate' => $input['startDate'], 'endDate' => $input['endDate']];
        $this->applyClientScope($conditions, $params, $scope, 'userId');
        $rows = $this->db->fetchAll(
            'SELECT transactionType, COALESCE(SUM(amount), 0) AS totalAmount, COUNT(*) AS transactionCount'
            . ' FROM vAllTransactions WHERE ' . implode(' AND ', $conditions)
            . ' GROUP BY transactionType',
            $params
        );
        foreach ($rows as $row) {
            $amount = (float)($row['totalAmount'] ?? 0);
            $count = (int)($row['transactionCount'] ?? 0);
            if (($row['transactionType'] ?? '') === 'deposit') {
                $summary['totalDeposits'] = $amount;
                $summary['depositCount'] = $count;
            } elseif (($row['transactionType'] ?? '') === 'withdrawal') {
                $summary['totalWithdrawals'] = $amount;
                $summary['withdrawalCount'] = $count;
            } elseif (($row['transactionType'] ?? '') === 'internal_transfer') {
                $summary['totalInternalTransfer'] = $amount;
                $summary['internalTransferCount'] = $count;
            }
        }
        $summary['netFlow'] = $summary['totalDeposits'] - $summary['totalWithdrawals'];
        return ['period' => $input, 'summary' => $summary];
    }

    public function searchFundingTransactions(array $input, array $scope): array
    {
        if (($scope['scope'] ?? '') === 'none') {
            return ['transactions' => [], 'pagination' => self::pagination($input['page'], $input['limit'], 0)];
        }
        $conditions = ["transactionType IN ('deposit', 'withdrawal', 'internal_transfer')"];
        $params = [];
        $this->applyClientScope($conditions, $params, $scope, 'userId');
        foreach (['startDate' => '>=', 'endDate' => '<='] as $key => $operator) {
            if (isset($input[$key])) {
                $conditions[] = "DATE(requestedAt) {$operator} :{$key}";
                $params[$key] = $input[$key];
            }
        }
        if (isset($input['type']) && $input['type'] !== 'all') {
            $conditions[] = 'transactionType = :transactionType';
            $params['transactionType'] = $input['type'];
        }
        if (isset($input['status'])) {
            $conditions[] = 'status = :status';
            $params['status'] = $input['status'];
        }
        if (isset($input['query'])) {
            $like = '%' . $input['query'] . '%';
            $conditions[] = '(transactionId LIKE :queryTransaction OR email LIKE :queryEmail'
                . " OR CONCAT_WS(' ', firstName, lastName) LIKE :queryName OR CAST(userId AS CHAR) LIKE :queryUser)";
            foreach (['queryTransaction', 'queryEmail', 'queryName', 'queryUser'] as $key) {
                $params[$key] = $like;
            }
        }
        if (isset($input['minAmount'])) {
            $conditions[] = 'amount >= :minAmount';
            $params['minAmount'] = $input['minAmount'];
        }
        if (isset($input['maxAmount'])) {
            $conditions[] = 'amount <= :maxAmount';
            $params['maxAmount'] = $input['maxAmount'];
        }

        $where = implode(' AND ', $conditions);
        $count = $this->db->fetchOne("SELECT COUNT(*) AS total FROM vAllTransactions WHERE {$where}", $params);
        $total = (int)($count['total'] ?? 0);
        $offset = ($input['page'] - 1) * $input['limit'];
        $rows = $this->db->fetchAll(
            "SELECT id, userId, transactionId, transactionType, status, amount, currency,"
            . " requestedAt, paymentMethod, firstName, lastName, email"
            . " FROM vAllTransactions WHERE {$where} ORDER BY requestedAt DESC, id DESC"
            . ' LIMIT ' . (int)$input['limit'] . ' OFFSET ' . (int)$offset,
            $params
        );
        return [
            'transactions' => array_map([self::class, 'projectFundingTransaction'], $rows),
            'pagination' => self::pagination($input['page'], $input['limit'], $total),
        ];
    }

    public function getDailySalesPerformance(array $input, array $access): array
    {
        $config = require __DIR__ . '/../config/app.php';
        $salesRoleId = (int)($config['special_roles']['sales_role_id'] ?? 6);
        $permission = (new AdminPermission())->findByKey('page_salesdashboard_view');
        $permissionId = $permission ? (int)($permission['id'] ?? 0) : 0;
        $onlyUserId = !empty($access['can_view_all_sales']) ? null : (int)($access['admin_user_id'] ?? 0);
        $salesUsers = (new AdminUser())->getSalesUsers($salesRoleId, $permissionId, '', $onlyUserId);
        $salesIds = array_map(static fn($row) => (int)$row['id'], $salesUsers);
        $transactionRows = SalesPerformanceMetrics::indexTransactionTotals(
            SalesPerformanceMetrics::transactionTotals($salesIds, $input['date'], $input['date'], $input['tzOffset'])
        );
        $registrationRows = SalesPerformanceMetrics::registrationTotals(
            $salesIds,
            $input['date'],
            $input['date'],
            $input['tzOffset']
        );
        $registrations = [];
        foreach ($registrationRows as $row) {
            $registrations[(int)$row['salesId']] = $row;
        }

        $rows = [];
        $summary = [
            'deposits' => 0.0,
            'depositCount' => 0,
            'withdrawals' => 0.0,
            'withdrawalCount' => 0,
            'netDeposit' => 0.0,
            'newLeads' => 0,
            'newClients' => 0,
        ];
        foreach ($salesUsers as $user) {
            $salesId = (int)$user['id'];
            $transaction = $transactionRows[$salesId] ?? [
                'deposits' => 0.0,
                'depositCount' => 0,
                'withdrawals' => 0.0,
                'withdrawalCount' => 0,
            ];
            $registration = $registrations[$salesId] ?? [];
            $row = [
                'salesId' => $salesId,
                'salesName' => self::salesName($user),
                'email' => $user['email'] ?? null,
                'deposits' => (float)$transaction['deposits'],
                'depositCount' => (int)$transaction['depositCount'],
                'withdrawals' => (float)$transaction['withdrawals'],
                'withdrawalCount' => (int)$transaction['withdrawalCount'],
                'netDeposit' => (float)$transaction['deposits'] - (float)$transaction['withdrawals'],
                'newLeads' => (int)($registration['newLeads'] ?? 0),
                'newClients' => (int)($registration['newClients'] ?? 0),
            ];
            $rows[] = $row;
            foreach ($summary as $key => $_) {
                $summary[$key] += $row[$key];
            }
        }
        $ranked = self::rankDailyRows($rows, $input['rankBy'], $input['limit']);
        $rankings = array_map(static function ($row) use ($input) {
            return [
                'rank' => $row['rank'],
                'sales' => [
                    'id' => $row['salesId'],
                    'name' => $row['salesName'],
                    'email' => $row['email'],
                ],
                'metrics' => [
                    'deposits' => $row['deposits'],
                    'depositCount' => $row['depositCount'],
                    'withdrawals' => $row['withdrawals'],
                    'withdrawalCount' => $row['withdrawalCount'],
                    'netDeposit' => $row['netDeposit'],
                    'newLeads' => $row['newLeads'],
                    'newClients' => $row['newClients'],
                ],
                'value' => $row[$input['rankBy']],
            ];
        }, $ranked);
        return [
            'date' => $input['date'],
            'timezone' => [
                'offsetMinutes' => $input['tzOffset'],
                'label' => SalesPerformanceMetrics::offsetLabel($input['tzOffset']),
            ],
            'scope' => !empty($access['can_view_all_sales']) ? 'team' : 'self',
            'rankBy' => $input['rankBy'],
            'summary' => $summary,
            'rankings' => $rankings,
            'truncated' => count($rows) > count($rankings),
        ];
    }

    public function searchIbPartners(array $input, array $scope): array
    {
        if (($scope['scope'] ?? '') === 'none') {
            return ['partners' => [], 'pagination' => self::pagination($input['page'], $input['limit'], 0)];
        }
        $conditions = ["ib.status = 'approved'"];
        $params = [];
        if (($scope['scope'] ?? '') === 'own') {
            $conditions[] = 'ib.userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrictSalesId)';
            $params['restrictSalesId'] = (int)$scope['restrict_to_sales_id'];
        }
        $like = '%' . $input['query'] . '%';
        $conditions[] = '(ib.ibCode LIKE :queryCode OR ib.adminAlias LIKE :queryAdminAlias'
            . ' OR ib.clientAlias LIKE :queryClientAlias OR ib.companyName LIKE :queryCompany'
            . " OR CONCAT_WS(' ', cu.firstName, cu.lastName) LIKE :queryName)";
        foreach (['queryCode', 'queryAdminAlias', 'queryClientAlias', 'queryCompany', 'queryName'] as $key) {
            $params[$key] = $like;
        }
        $where = implode(' AND ', $conditions);
        $count = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM ibPartners ib LEFT JOIN clientUsers cu ON cu.id = ib.userId WHERE {$where}",
            $params
        );
        $total = (int)($count['total'] ?? 0);
        $offset = ($input['page'] - 1) * $input['limit'];
        $rows = $this->db->fetchAll(
            "SELECT ib.id, ib.ibCode, COALESCE(NULLIF(TRIM(ib.adminAlias), ''),"
            . " NULLIF(TRIM(ib.clientAlias), ''), NULLIF(TRIM(ib.companyName), ''),"
            . " NULLIF(TRIM(CONCAT_WS(' ', cu.firstName, cu.lastName)), ''), CONCAT('IB #', ib.id)) AS name"
            . " FROM ibPartners ib LEFT JOIN clientUsers cu ON cu.id = ib.userId WHERE {$where}"
            . ' ORDER BY name ASC, ib.id ASC LIMIT ' . (int)$input['limit'] . ' OFFSET ' . (int)$offset,
            $params
        );
        return [
            'partners' => array_map(static fn($row) => [
                'id' => (int)$row['id'],
                'ibCode' => (string)($row['ibCode'] ?? ''),
                'name' => (string)($row['name'] ?? ''),
            ], $rows),
            'pagination' => self::pagination($input['page'], $input['limit'], $total),
        ];
    }

    public function getIbStatement(array $input, array $scope): ?array
    {
        $partnerId = $this->resolveIbPartnerId($input, $scope);
        if ($partnerId === null) {
            return null;
        }
        $payload = (new IbStatementReportController())->buildStatement(
            $partnerId,
            $input['startDate'] . ' 00:00:00',
            $input['endDate'] . ' 23:59:59',
            $scope
        );
        if ($payload === null) {
            return null;
        }
        $accounts = is_array($payload['accounts'] ?? null) ? $payload['accounts'] : [];
        $total = count($accounts);
        $offset = ($input['page'] - 1) * $input['limit'];
        $payload['accounts'] = array_slice($accounts, $offset, $input['limit']);
        $payload['accountsPagination'] = self::pagination($input['page'], $input['limit'], $total);
        return $payload;
    }

    public function resolveIbPartnerId(array $input, array $scope): ?int
    {
        if (($scope['scope'] ?? '') === 'none') {
            return null;
        }
        $conditions = ["status = 'approved'"];
        $params = [];
        if (isset($input['ibPartnerId'])) {
            $conditions[] = 'id = :ibPartnerId';
            $params['ibPartnerId'] = (int)$input['ibPartnerId'];
        } else {
            $conditions[] = 'ibCode = :ibCode';
            $params['ibCode'] = (string)($input['ibCode'] ?? '');
        }
        if (($scope['scope'] ?? '') === 'own') {
            $conditions[] = 'userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrictSalesId)';
            $params['restrictSalesId'] = (int)($scope['restrict_to_sales_id'] ?? 0);
        }
        $row = $this->db->fetchOne(
            'SELECT id FROM ibPartners WHERE ' . implode(' AND ', $conditions) . ' LIMIT 1',
            $params
        );
        $partnerId = (int)($row['id'] ?? 0);
        return $partnerId > 0 ? $partnerId : null;
    }

    public function listCustomReports(array $input): array
    {
        $conditions = ['1=1'];
        $params = [];
        if (isset($input['search'])) {
            $conditions[] = 'cr.name LIKE :search';
            $params['search'] = '%' . $input['search'] . '%';
        }
        $where = implode(' AND ', $conditions);
        $count = $this->db->fetchOne("SELECT COUNT(*) AS total FROM custom_reports cr WHERE {$where}", $params);
        $total = (int)($count['total'] ?? 0);
        $offset = ($input['page'] - 1) * $input['limit'];
        $rows = $this->db->fetchAll(
            "SELECT cr.id, cr.name, cr.created_by AS createdBy, cr.created_at AS createdAt,"
            . " cr.updated_at AS updatedAt, au.fullName AS createdByName,"
            . " (SELECT COUNT(*) FROM report_widgets rw WHERE rw.report_id = cr.id) AS widgetCount"
            . " FROM custom_reports cr LEFT JOIN adminUsers au ON au.id = CAST(cr.created_by AS UNSIGNED)"
            . " WHERE {$where} ORDER BY cr.created_at DESC"
            . ' LIMIT ' . (int)$input['limit'] . ' OFFSET ' . (int)$offset,
            $params
        );
        foreach ($rows as &$row) {
            $row['widgetCount'] = (int)($row['widgetCount'] ?? 0);
        }
        unset($row);
        return ['reports' => $rows, 'pagination' => self::pagination($input['page'], $input['limit'], $total)];
    }

    public function getCustomReportResults(array $input, array $scope): ?array
    {
        $report = $this->db->fetchOne(
            'SELECT id, name, created_by AS createdBy, created_at AS createdAt, updated_at AS updatedAt'
            . ' FROM custom_reports WHERE id = :id',
            ['id' => $input['reportId']]
        );
        if (!$report) {
            return null;
        }
        $params = ['reportId' => $input['reportId']];
        $where = 'w.report_id = :reportId';
        if (isset($input['widgetId'])) {
            $where .= ' AND w.id = :widgetId';
            $params['widgetId'] = $input['widgetId'];
        }
        $widgets = $this->db->fetchAll(
            'SELECT w.id, w.report_id AS reportId, w.widget_type AS widgetType, w.name,'
            . ' w.view_config AS viewConfig, w.data_source_id AS dataSourceId,'
            . ' ds.display_name AS dataSourceName FROM report_widgets w'
            . ' LEFT JOIN report_data_sources ds ON ds.id = w.data_source_id'
            . " WHERE {$where} ORDER BY w.created_at ASC",
            $params
        );
        if (isset($input['widgetId']) && !$widgets) {
            return null;
        }
        $totalWidgets = count($widgets);
        $previewWidgets = array_slice($widgets, 0, isset($input['widgetId']) ? 1 : 10);
        $controller = new CustomReportController();
        $results = [];
        foreach ($previewWidgets as $widget) {
            $viewConfig = json_decode((string)($widget['viewConfig'] ?? ''), true);
            $viewConfig = is_array($viewConfig) ? $viewConfig : [];
            $activeId = (string)($viewConfig['activeView'] ?? '');
            $active = is_array($viewConfig['views'][$activeId] ?? null) ? $viewConfig['views'][$activeId] : [];
            $kind = (string)($widget['widgetType'] ?? 'table') === 'chart' ? 'chart' : 'table';
            foreach (($viewConfig['types'] ?? []) as $type) {
                if (is_array($type) && ($type['id'] ?? '') === $activeId) {
                    $kind = ($type['kind'] ?? '') === 'chart' ? 'chart' : 'table';
                    break;
                }
            }
            $query = [
                'offset' => ($input['page'] - 1) * $input['limit'],
                'limit' => $input['limit'],
                'search' => (string)($active['search'] ?? ''),
                'filters' => is_array($active['filters'] ?? null) ? $active['filters'] : [],
                'sorts' => is_array($active['sorts'] ?? null) ? $active['sorts'] : [],
                'scope' => $scope,
            ];
            $page = $controller->fetchWidgetExportPage($input['reportId'], $widget['id'], $query);
            $visible = is_array($active['visibleColumns'] ?? null) ? $active['visibleColumns'] : [];
            if (!$visible) {
                $visible = array_slice($page['columnNames'] ?? [], 0, 10);
            }
            $visible = array_values(array_intersect($visible, $page['columnNames'] ?? []));
            $projectedRows = array_map(static function ($row) use ($visible) {
                $out = [];
                foreach ($visible as $field) {
                    $out[$field] = $row[$field] ?? null;
                }
                return $out;
            }, $page['rows'] ?? []);
            $results[] = [
                'widget' => [
                    'id' => (string)$widget['id'],
                    'name' => (string)($widget['name'] ?? ''),
                    'kind' => $kind,
                    'dataSourceId' => (string)($widget['dataSourceId'] ?? ''),
                    'dataSourceName' => (string)($widget['dataSourceName'] ?? ''),
                ],
                'columns' => $visible,
                'rows' => $projectedRows,
                'pagination' => self::pagination($input['page'], $input['limit'], (int)($page['total'] ?? 0)),
            ];
        }
        return [
            'report' => $report,
            'widgets' => $results,
            'previewedWidgetCount' => count($results),
            'totalWidgets' => $totalWidgets,
            'truncated' => $totalWidgets > count($results),
        ];
    }

    public static function rankDailyRows(array $rows, string $metric, int $limit): array
    {
        usort($rows, static function ($left, $right) use ($metric) {
            $comparison = (float)($right[$metric] ?? 0) <=> (float)($left[$metric] ?? 0);
            if ($comparison !== 0) {
                return $comparison;
            }
            $nameComparison = strcasecmp((string)($left['salesName'] ?? ''), (string)($right['salesName'] ?? ''));
            return $nameComparison !== 0
                ? $nameComparison
                : (int)($left['salesId'] ?? 0) <=> (int)($right['salesId'] ?? 0);
        });
        $ranked = [];
        $previous = null;
        $rank = 0;
        foreach (array_slice($rows, 0, $limit) as $index => $row) {
            $value = (float)($row[$metric] ?? 0);
            if ($previous === null || $value != $previous) {
                $rank = $index + 1;
            }
            $row['rank'] = $rank;
            $ranked[] = $row;
            $previous = $value;
        }
        return $ranked;
    }

    private static function projectFundingTransaction(array $row): array
    {
        $name = trim((string)($row['firstName'] ?? '') . ' ' . (string)($row['lastName'] ?? ''));
        return [
            'id' => (int)($row['id'] ?? 0),
            'transactionId' => (string)($row['transactionId'] ?? ''),
            'type' => (string)($row['transactionType'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'amount' => (float)($row['amount'] ?? 0),
            'currency' => self::nullableString($row['currency'] ?? null),
            'date' => self::isoDateTime($row['requestedAt'] ?? null),
            'paymentMethod' => self::nullableString($row['paymentMethod'] ?? null),
            'client' => [
                'id' => (int)($row['userId'] ?? 0),
                'name' => $name !== '' ? $name : null,
                'email' => self::nullableString($row['email'] ?? null),
            ],
        ];
    }

    private function applyClientScope(array &$conditions, array &$params, array $scope, string $column): void
    {
        if (($scope['scope'] ?? '') === 'own') {
            $conditions[] = "{$column} IN (SELECT clientId FROM sales_bind WHERE salesId = :restrictSalesId)";
            $params['restrictSalesId'] = (int)($scope['restrict_to_sales_id'] ?? 0);
        }
    }

    private static function pagination(int $page, int $limit, int $total): array
    {
        $totalPages = $total === 0 ? 0 : (int)ceil($total / $limit);
        return [
            'page' => $page,
            'limit' => $limit,
            'perPage' => $limit,
            'total' => $total,
            'totalPages' => $totalPages,
            'hasMore' => $page < $totalPages,
        ];
    }

    private static function salesName(array $row): ?string
    {
        $name = trim((string)($row['fullName'] ?? $row['username'] ?? ''));
        return $name !== '' ? $name : null;
    }

    private static function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private static function isoDateTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return (new DateTime((string)$value, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
        } catch (Throwable $exception) {
            return null;
        }
    }
}
