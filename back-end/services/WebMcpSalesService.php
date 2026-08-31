<?php

require_once __DIR__ . '/../models/AdminUser.php';
require_once __DIR__ . '/../models/AdminPermission.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/SalesPerformanceMetrics.php';

class WebMcpSalesService {
    private $db;
    private $userModel;
    private $salesRoleId;
    private $salesDashboardPermissionId;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->userModel = new AdminUser();
        $config = require __DIR__ . '/../config/app.php';
        $this->salesRoleId = (int)($config['special_roles']['sales_role_id'] ?? 6);
        $permission = (new AdminPermission())->findByKey('page_salesdashboard_view');
        $this->salesDashboardPermissionId = $permission ? (int)($permission['id'] ?? 0) : 0;
    }

    public function searchSales(array $input, ?int $onlyUserId): array {
        if ($onlyUserId !== null) {
            $rows = $this->userModel->getSalesUsers(
                $this->salesRoleId,
                $this->salesDashboardPermissionId,
                $input['query'],
                $onlyUserId
            );
        } elseif (ctype_digit($input['query'])) {
            $rows = $this->userModel->getSalesUsers(
                $this->salesRoleId,
                $this->salesDashboardPermissionId,
                '',
                (int)$input['query']
            );
        } else {
            $rows = $this->userModel->getSalesUsers(
                $this->salesRoleId,
                $this->salesDashboardPermissionId,
                $input['query']
            );
        }

        $total = count($rows);
        $offset = ($input['page'] - 1) * $input['limit'];
        $pageRows = array_slice($rows, $offset, $input['limit']);
        $pageRows = $this->enrichSalesUsers($pageRows);

        return [
            'sales' => array_map([self::class, 'projectSales'], $pageRows),
            'pagination' => self::pagination($input['page'], $input['limit'], $total),
        ];
    }

    public function findSales(int $salesId): ?array {
        $rows = $this->userModel->getSalesUsers(
            $this->salesRoleId,
            $this->salesDashboardPermissionId,
            '',
            $salesId
        );
        if (!$rows) {
            return null;
        }
        $enriched = $this->enrichSalesUsers([$rows[0]]);
        return $enriched ? self::projectSales($enriched[0]) : null;
    }

    public function getSalesClients(array $sales, array $input): array {
        $conditions = [
            'sb.salesId = :salesId',
            'ibApproved.userId IS NULL',
        ];
        $params = ['salesId' => (int)$sales['id']];
        if (isset($input['search'])) {
            $pattern = '%' . $input['search'] . '%';
            $searchConditions = [
                'cu.firstName LIKE :searchFirst',
                'cu.lastName LIKE :searchLast',
                'cu.email LIKE :searchEmail',
                "CONCAT_WS(' ', cu.firstName, cu.lastName) LIKE :searchName",
            ];
            if (ctype_digit($input['search'])) {
                $searchConditions[] = 'cu.id = :searchId';
                $params['searchId'] = (int)$input['search'];
            }
            $params['searchFirst'] = $pattern;
            $params['searchLast'] = $pattern;
            $params['searchEmail'] = $pattern;
            $params['searchName'] = $pattern;
            $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
        }
        $where = implode(' AND ', $conditions);
        $from = "FROM sales_bind sb
                 INNER JOIN clientUsers cu ON cu.id = sb.clientId
                 LEFT JOIN ibPartners ibApproved
                    ON ibApproved.userId = cu.id AND ibApproved.status = 'approved'
                 LEFT JOIN countryList cl ON cl.code = cu.country
                 WHERE {$where}";

        $count = $this->db->fetchOne("SELECT COUNT(DISTINCT cu.id) AS total {$from}", $params);
        $total = (int)($count['total'] ?? 0);
        $offset = ($input['page'] - 1) * $input['limit'];
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT
                cu.id,
                cu.firstName,
                cu.lastName,
                cu.email,
                COALESCE(cl.name, cu.country) AS country,
                cu.kycStatus,
                COALESCE((
                    SELECT SUM(COALESCE(tea.platformBalance, 0))
                    FROM tradingAccounts ta
                    LEFT JOIN tradingAccountExternalAccounts tea ON tea.tradingAccountId = ta.id
                    WHERE ta.userId = cu.id
                ), 0) AS balance,
                (SELECT COUNT(*)
                 FROM orders o
                 INNER JOIN tradingAccountExternalAccounts tea_o ON tea_o.providerAccountId = o.trading_login
                 INNER JOIN tradingAccounts ta_o ON ta_o.id = tea_o.tradingAccountId
                 WHERE ta_o.userId = cu.id) AS trades
             {$from}
             ORDER BY cu.id ASC
             LIMIT " . (int)$input['limit'] . ' OFFSET ' . (int)$offset,
            $params
        );

        return [
            'sales' => $sales,
            'clients' => array_map([self::class, 'projectClient'], $rows),
            'pagination' => self::pagination($input['page'], $input['limit'], $total),
        ];
    }

    public function getSalesPartners(array $sales, array $input): array {
        $conditions = [
            'sb.salesId = :salesId',
            "ib.status = 'approved'",
        ];
        $params = ['salesId' => (int)$sales['id']];
        if (isset($input['search'])) {
            $pattern = '%' . $input['search'] . '%';
            $searchConditions = [
                'ib.ibCode LIKE :searchCode',
                'ib.companyName LIKE :searchCompany',
                'ib.adminAlias LIKE :searchAlias',
                'COALESCE(cu.email, ib.contactEmail) LIKE :searchEmail',
                "CONCAT_WS(' ', cu.firstName, cu.lastName) LIKE :searchName",
            ];
            if (ctype_digit($input['search'])) {
                $searchConditions[] = 'ib.id = :searchId';
                $params['searchId'] = (int)$input['search'];
            }
            foreach (['searchCode', 'searchCompany', 'searchAlias', 'searchEmail', 'searchName'] as $key) {
                $params[$key] = $pattern;
            }
            $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
        }
        $where = implode(' AND ', $conditions);
        $from = "FROM sales_bind sb
                 INNER JOIN ibPartners ib ON ib.userId = sb.clientId
                 LEFT JOIN clientUsers cu ON cu.id = ib.userId
                 LEFT JOIN countryList cl ON cl.code = cu.country
                 WHERE {$where}";
        $count = $this->db->fetchOne("SELECT COUNT(DISTINCT ib.id) AS total {$from}", $params);
        $total = (int)($count['total'] ?? 0);
        $offset = ($input['page'] - 1) * $input['limit'];
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT
                ib.id,
                ib.userId,
                ib.ibCode,
                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', cu.firstName, cu.lastName)), ''), ib.companyName, ib.adminAlias) AS name,
                COALESCE(cu.email, ib.contactEmail) AS email,
                COALESCE(cl.name, cu.country) AS country,
                ib.totalClients,
                ib.status,
                (SELECT COALESCE(SUM(ico.commission), 0)
                 FROM ib_commission_order ico
                 WHERE ico.ibPartnerId = ib.id AND ico.status = 'completed') AS totalCommission
             {$from}
             ORDER BY ib.id ASC
             LIMIT " . (int)$input['limit'] . ' OFFSET ' . (int)$offset,
            $params
        );

        return [
            'sales' => $sales,
            'partners' => array_map([self::class, 'projectPartner'], $rows),
            'pagination' => self::pagination($input['page'], $input['limit'], $total),
        ];
    }

    public function getPerformance(array $sales, string $month, int $tzOffset): array {
        [$startDate, $endDate] = SalesPerformanceMetrics::monthBounds($month);
        $metrics = SalesPerformanceMetrics::metricsForSales(
            (int)$sales['id'],
            $startDate,
            $endDate,
            $tzOffset
        );
        return self::buildPerformancePayload(
            $sales,
            $month,
            $startDate,
            $endDate,
            $tzOffset,
            $metrics,
            $this->kpiTarget((int)$sales['id'], $month)
        );
    }

    public function getDailySummary(array $sales, string $date, int $tzOffset): array {
        $month = substr($date, 0, 7);
        $metrics = SalesPerformanceMetrics::metricsForSales(
            (int)$sales['id'],
            $date,
            $date,
            $tzOffset
        );
        $monthMetrics = SalesPerformanceMetrics::metricsForSales(
            (int)$sales['id'],
            $month . '-01',
            $date,
            $tzOffset
        );
        return self::buildDailyPayload(
            $sales,
            $date,
            $tzOffset,
            $metrics,
            (float)($monthMetrics['netDeposit'] ?? 0),
            $this->kpiTarget((int)$sales['id'], $month)
        );
    }

    public function getLeaderboard(array $input): array {
        $tzOffset = $input['tzOffset'];
        if ($input['period'] === 'day') {
            $periodValue = $input['date'];
            $startDate = $periodValue;
            $endDate = $periodValue;
        } else {
            $periodValue = $input['month'];
            [$startDate, $endDate] = SalesPerformanceMetrics::monthBounds($periodValue);
        }

        $salesUsers = $this->userModel->getSalesUsers(
            $this->salesRoleId,
            $this->salesDashboardPermissionId
        );
        $salesUsers = $this->enrichSalesUsers($salesUsers);
        $salesIds = array_map(static fn($row) => (int)$row['id'], $salesUsers);
        $transactions = SalesPerformanceMetrics::indexTransactionTotals(
            SalesPerformanceMetrics::transactionTotals($salesIds, $startDate, $endDate, $tzOffset)
        );
        $registrationRows = SalesPerformanceMetrics::registrationTotals(
            $salesIds,
            $startDate,
            $endDate,
            $tzOffset
        );
        $registrations = [];
        foreach ($registrationRows as $row) {
            $registrations[(int)$row['salesId']] = $row;
        }

        $rows = [];
        foreach ($salesUsers as $salesUser) {
            $salesId = (int)$salesUser['id'];
            $transaction = $transactions[$salesId] ?? [
                'deposits' => 0.0,
                'depositCount' => 0,
                'withdrawals' => 0.0,
                'withdrawalCount' => 0,
            ];
            $registration = $registrations[$salesId] ?? [];
            $rows[] = array_merge($salesUser, [
                'deposits' => (float)$transaction['deposits'],
                'netDeposit' => (float)$transaction['deposits'] - (float)$transaction['withdrawals'],
                'newLeads' => (int)($registration['newLeads'] ?? 0),
                'newClients' => (int)($registration['newClients'] ?? 0),
            ]);
        }

        $ranked = self::rankRows($rows, $input['metric'], $input['limit']);
        $rankings = array_map(static function ($row) {
            return [
                'rank' => $row['rank'],
                'sales' => self::projectSales($row),
                'value' => $row['value'],
            ];
        }, $ranked);

        return [
            'metric' => $input['metric'],
            'period' => $input['period'],
            $input['period'] === 'day' ? 'date' : 'month' => $periodValue,
            'range' => ['startDate' => $startDate, 'endDate' => $endDate],
            'timezone' => [
                'offsetMinutes' => $tzOffset,
                'label' => SalesPerformanceMetrics::offsetLabel($tzOffset),
            ],
            'rankings' => $rankings,
        ];
    }

    public static function buildPerformancePayload(
        array $sales,
        string $month,
        string $startDate,
        string $endDate,
        int $tzOffset,
        array $metrics,
        ?float $kpiTarget
    ): array {
        return [
            'sales' => self::projectSales($sales),
            'month' => $month,
            'range' => ['startDate' => $startDate, 'endDate' => $endDate],
            'timezone' => [
                'offsetMinutes' => $tzOffset,
                'label' => SalesPerformanceMetrics::offsetLabel($tzOffset),
            ],
            'metrics' => $metrics,
            'target' => [
                'netDeposit' => $kpiTarget,
                'achievementRate' => self::achievementRate(
                    (float)($metrics['netDeposit'] ?? 0),
                    $kpiTarget
                ),
            ],
        ];
    }

    public static function buildDailyPayload(
        array $sales,
        string $date,
        int $tzOffset,
        array $metrics,
        float $monthToDateNetDeposit,
        ?float $kpiTarget
    ): array {
        return [
            'sales' => self::projectSales($sales),
            'date' => $date,
            'month' => substr($date, 0, 7),
            'timezone' => [
                'offsetMinutes' => $tzOffset,
                'label' => SalesPerformanceMetrics::offsetLabel($tzOffset),
            ],
            'metrics' => $metrics,
            'monthToDateNetDeposit' => $monthToDateNetDeposit,
            'target' => [
                'netDeposit' => $kpiTarget,
                'achievementRate' => self::achievementRate($monthToDateNetDeposit, $kpiTarget),
            ],
        ];
    }

    public static function achievementRate(float $actual, ?float $target): ?float {
        if ($target === null || $target <= 0) {
            return null;
        }
        return round(($actual / $target) * 100, 2);
    }

    public static function rankRows(array $rows, string $metric, int $limit): array {
        usort($rows, static function ($left, $right) use ($metric) {
            $valueComparison = (float)($right[$metric] ?? 0) <=> (float)($left[$metric] ?? 0);
            if ($valueComparison !== 0) {
                return $valueComparison;
            }
            $leftName = (string)($left['salesName'] ?? $left['fullName'] ?? $left['name'] ?? '');
            $rightName = (string)($right['salesName'] ?? $right['fullName'] ?? $right['name'] ?? '');
            $nameComparison = strcasecmp($leftName, $rightName);
            return $nameComparison !== 0
                ? $nameComparison
                : (int)($left['salesId'] ?? $left['id'] ?? 0) <=> (int)($right['salesId'] ?? $right['id'] ?? 0);
        });

        $ranked = [];
        $previousValue = null;
        $rank = 0;
        foreach (array_slice($rows, 0, $limit) as $index => $row) {
            $value = is_float($row[$metric] ?? null)
                ? (float)$row[$metric]
                : (int)($row[$metric] ?? 0);
            if ($previousValue === null || $value != $previousValue) {
                $rank = $index + 1;
            }
            $row['salesId'] = (int)($row['salesId'] ?? $row['id'] ?? 0);
            $row['rank'] = $rank;
            $row['value'] = $value;
            $ranked[] = $row;
            $previousValue = $value;
        }
        return $ranked;
    }

    public static function projectSales(array $row): array {
        $name = trim((string)($row['salesName'] ?? $row['fullName'] ?? $row['name'] ?? $row['username'] ?? ''));
        return [
            'id' => (int)($row['salesId'] ?? $row['id'] ?? 0),
            'name' => $name !== '' ? $name : null,
            'email' => self::nullableString($row['email'] ?? null),
            'status' => self::nullableString($row['status'] ?? null),
            'department' => self::nullableString($row['department'] ?? $row['departmentName'] ?? null),
            'position' => self::nullableString($row['position'] ?? $row['positionName'] ?? null),
            'totalClients' => (int)($row['totalClients'] ?? 0),
            'totalPartners' => (int)($row['totalPartners'] ?? $row['totalIbs'] ?? 0),
        ];
    }

    public static function projectClient(array $row): array {
        $name = trim((string)($row['firstName'] ?? '') . ' ' . (string)($row['lastName'] ?? ''));
        return [
            'id' => (int)($row['id'] ?? 0),
            'name' => $name !== '' ? $name : null,
            'email' => self::nullableString($row['email'] ?? null),
            'country' => self::nullableString($row['country'] ?? null),
            'kycStatus' => self::nullableString($row['kycStatus'] ?? null),
            'balance' => (float)($row['balance'] ?? 0),
            'trades' => (int)($row['trades'] ?? 0),
        ];
    }

    public static function projectPartner(array $row): array {
        return [
            'id' => (int)($row['id'] ?? 0),
            'userId' => (int)($row['userId'] ?? 0),
            'code' => self::nullableString($row['ibCode'] ?? null),
            'name' => self::nullableString($row['name'] ?? null),
            'email' => self::nullableString($row['email'] ?? null),
            'country' => self::nullableString($row['country'] ?? null),
            'clientCount' => (int)($row['totalClients'] ?? 0),
            'totalCommission' => (float)($row['totalCommission'] ?? 0),
            'status' => self::nullableString($row['status'] ?? null),
        ];
    }

    public static function pagination(int $page, int $limit, int $total): array {
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

    private function enrichSalesUsers(array $rows): array {
        if (!$rows) {
            return [];
        }
        $ids = array_values(array_unique(array_map(static fn($row) => (int)$row['id'], $rows)));
        [$inClause, $params] = SalesPerformanceMetrics::buildIdInClause($ids, 'sales');
        $details = $this->db->fetchAll(
            "SELECT
                u.id,
                u.username,
                u.fullName,
                u.email,
                u.status,
                d.name AS department,
                p.name AS position,
                COUNT(DISTINCT CASE WHEN ibApproved.userId IS NULL THEN sb.clientId END) AS totalClients,
                COUNT(DISTINCT ibApproved.id) AS totalPartners
             FROM adminUsers u
             LEFT JOIN departments d ON d.id = u.departmentId
             LEFT JOIN positions p ON p.id = u.positionId
             LEFT JOIN sales_bind sb ON sb.salesId = u.id
             LEFT JOIN ibPartners ibApproved
                ON ibApproved.userId = sb.clientId AND ibApproved.status = 'approved'
             WHERE u.id IN ({$inClause})
             GROUP BY u.id, u.username, u.fullName, u.email, u.status, d.name, p.name",
            $params
        );
        $byId = [];
        foreach ($details as $detail) {
            $byId[(int)$detail['id']] = $detail;
        }
        return array_map(static function ($row) use ($byId) {
            $id = (int)$row['id'];
            return array_merge($row, $byId[$id] ?? []);
        }, $rows);
    }

    private function kpiTarget(int $salesId, string $month): ?float {
        $row = $this->db->fetchOne(
            'SELECT kpiTarget FROM salesMonthlyKpis WHERE salesId = :salesId AND kpiMonth = :month LIMIT 1',
            ['salesId' => $salesId, 'month' => $month]
        );
        return $row && array_key_exists('kpiTarget', $row)
            ? (float)$row['kpiTarget']
            : null;
    }

    private static function nullableString($value): ?string {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}
