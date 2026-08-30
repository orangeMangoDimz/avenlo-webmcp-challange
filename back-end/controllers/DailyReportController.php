<?php
/**
 * Daily Report Controller (Sales)
 * One row per sales user for a selected day: deposit, withdrawal, net deposit,
 * new leads and new clients, counted over the clients bound to that sales user
 * (sales_bind). Each row also carries the sales user's monthly KPI target and
 * their month-to-date net deposit, so the achievement rate compares like with like.
 *
 * Amounts come from vAllTransactions filtered on requestedAt, counting only
 * completed deposits and withdrawals — pending, processing, rejected, cancelled and
 * failed rows never reach a sales person's KPI. Figures are therefore lower than
 * Funding Report, which sums every status.
 *
 * Day boundaries follow the caller's timezone offset (tzOffset, minutes east of
 * UTC); when it is missing the backend falls back to UTC+10 (600).
 *
 * Visibility: a sales manager (or admin) sees every sales user and may edit any
 * KPI when granted page_dailyreport_edit_kpi; a plain sales user sees only their
 * own row, read-only.
 */

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../models/AdminPermission.php';
require_once __DIR__ . '/../models/AdminUser.php';
require_once __DIR__ . '/../services/SalesPerformanceMetrics.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class DailyReportController {
    private const EDIT_KPI_PERMISSION_KEY = 'page_dailyreport_edit_kpi';

    /** KPI target ceiling, guards against fat-fingered input */
    private const MAX_KPI_TARGET = 1000000000000.0;

    /**
     * Per-sales metrics for one day
     * GET /api/daily-reports/summary?date=&tzOffset=&search=
     */
    public function summary() {
        $this->requireAdmin();

        $tzOffsetMinutes = $this->resolveTzOffsetMinutes($_GET['tzOffset'] ?? null);
        $date = $this->parseDate($_GET['date'] ?? null) ?? $this->todayInOffset($tzOffsetMinutes);
        $month = substr($date, 0, 7);
        $monthStart = $month . '-01';
        $search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';

        $access = $this->resolveAccess();
        if (!$access['canView']) {
            Response::forbidden('You do not have permission to view the daily sales report');
            return;
        }

        $salesUsers = $this->fetchSalesUsers($access, $search);
        $salesIds = array_map(function ($user) { return (int) $user['id']; }, $salesUsers);

        $dayTotals = $this->fetchTransactionTotals($salesIds, $date, $date, $tzOffsetMinutes);
        $monthTotals = $this->fetchTransactionTotals($salesIds, $monthStart, $date, $tzOffsetMinutes);
        $registrations = $this->fetchRegistrationTotals($salesIds, $date, $tzOffsetMinutes);
        $kpiMap = $this->fetchKpiMap($salesIds, $month);

        Response::success($this->buildPayload(
            $salesUsers,
            $dayTotals,
            $monthTotals,
            $registrations,
            $kpiMap,
            $date,
            $month,
            $tzOffsetMinutes,
            $access
        ));
    }

    /**
     * Save one sales user's monthly KPI target
     * PUT /api/daily-reports/kpi   body: { salesId, month, kpiTarget }
     */
    public function saveKpi() {
        $this->requireAdmin();
        AuthMiddleware::checkPermission(self::EDIT_KPI_PERMISSION_KEY);

        $access = $this->resolveAccess();
        if (!$access['canViewAllSales']) {
            // Only sales management / admin may write KPI targets
            Response::forbidden('You do not have permission to edit sales KPI targets');
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $salesId = isset($data['salesId']) ? (int) $data['salesId'] : 0;
        if ($salesId <= 0 || !$this->isSalesUser($salesId)) {
            Response::validationError(['salesId' => 'A valid sales user is required']);
            return;
        }

        $month = $this->parseMonth($data['month'] ?? null);
        if ($month === null) {
            Response::validationError(['month' => 'A valid month (YYYY-MM) is required']);
            return;
        }

        $rawTarget = $data['kpiTarget'] ?? null;
        if ($rawTarget === null || $rawTarget === '' || !is_numeric($rawTarget)) {
            Response::validationError(['kpiTarget' => 'KPI target must be a number']);
            return;
        }

        $kpiTarget = round((float) $rawTarget, 2);
        if ($kpiTarget < 0 || $kpiTarget > self::MAX_KPI_TARGET) {
            Response::validationError(['kpiTarget' => 'KPI target must be between 0 and ' . self::MAX_KPI_TARGET]);
            return;
        }

        $adminUserId = (int) (JWT::getPayload()['userId'] ?? 0);

        $db = Database::getInstance();
        $db->query(
            "INSERT INTO salesMonthlyKpis (salesId, kpiMonth, kpiTarget, updatedByAdminId)
             VALUES (:salesId, :kpiMonth, :kpiTarget, :updatedByAdminId)
             ON DUPLICATE KEY UPDATE
                kpiTarget = VALUES(kpiTarget),
                updatedByAdminId = VALUES(updatedByAdminId)",
            [
                'salesId' => $salesId,
                'kpiMonth' => $month,
                'kpiTarget' => $kpiTarget,
                'updatedByAdminId' => $adminUserId > 0 ? $adminUserId : null,
            ]
        );

        $row = $db->fetchOne(
            "SELECT kpiTarget, updatedByAdminId, updatedAt
             FROM salesMonthlyKpis WHERE salesId = :salesId AND kpiMonth = :kpiMonth",
            ['salesId' => $salesId, 'kpiMonth' => $month]
        );

        Response::success([
            'salesId' => $salesId,
            'month' => $month,
            'kpiTarget' => (float) ($row['kpiTarget'] ?? $kpiTarget),
            'kpiUpdatedAt' => $row['updatedAt'] ?? null,
            'kpiUpdatedBy' => isset($row['updatedByAdminId']) ? (int) $row['updatedByAdminId'] : null,
        ], 'KPI saved');
    }

    /**
     * Who the caller is allowed to see.
     * Sales management and admin see every sales user; a plain sales user sees only
     * their own row. Mirrors AdminSalesPermission's definition of those two roles.
     */
    private function resolveAccess() {
        $current = AuthMiddleware::getCurrentUser();
        $userId = isset($current['userId']) ? (int) $current['userId'] : 0;
        $roleId = isset($current['roleId']) ? (int) $current['roleId'] : 0;

        $config = require __DIR__ . '/../config/app.php';
        $special = $config['special_roles'] ?? [];
        $salesManagerRoleId = (int) ($special['sales_manager_role_id'] ?? 0);
        $salesRoleId = (int) ($special['sales_role_id'] ?? 0);

        $permModel = new AdminPermission();
        $isSalesManagement = ($roleId === $salesManagerRoleId)
            || $permModel->userHasPermission($userId, 'page_saleslist_view');
        $isSales = ($roleId === $salesRoleId)
            || $permModel->userHasPermission($userId, 'page_salesdashboard_view');
        $isSuperAdmin = ($roleId === 1);

        $canViewAllSales = $isSuperAdmin || $isSalesManagement;

        return [
            'adminUserId' => $userId,
            'canView' => $canViewAllSales || $isSales,
            'canViewAllSales' => $canViewAllSales,
            'canEditKpi' => $canViewAllSales && $permModel->userHasPermission($userId, self::EDIT_KPI_PERMISSION_KEY),
        ];
    }

    /**
     * Sales users in scope. "Sales" is whatever AdminUser says it is - the Sales role,
     * any role holding page_salesdashboard_view, or a super admin - so this page can
     * never list a different set of people than the assign dropdowns do.
     */
    private function fetchSalesUsers(array $access, $search) {
        $config = require __DIR__ . '/../config/app.php';
        $salesRoleId = (int) ($config['special_roles']['sales_role_id'] ?? 6);

        $permRow = (new AdminPermission())->findByKey('page_salesdashboard_view');
        $salesDashboardViewPid = $permRow ? (int) ($permRow['id'] ?? 0) : 0;

        // A plain sales user only ever gets their own row
        $onlyUserId = $access['canViewAllSales'] ? null : (int) $access['adminUserId'];

        return (new AdminUser())->getSalesUsers($salesRoleId, $salesDashboardViewPid, $search, $onlyUserId);
    }

    private function fetchTransactionTotals(array $salesIds, $startDate, $endDate, $tzOffsetMinutes) {
        return SalesPerformanceMetrics::transactionTotals($salesIds, $startDate, $endDate, $tzOffsetMinutes);
    }

    private function fetchRegistrationTotals(array $salesIds, $date, $tzOffsetMinutes) {
        return SalesPerformanceMetrics::registrationTotals($salesIds, $date, $date, $tzOffsetMinutes);
    }

    /**
     * Monthly KPI rows for the sales users in scope, keyed by salesId
     */
    private function fetchKpiMap(array $salesIds, $month) {
        if (empty($salesIds)) {
            return [];
        }

        [$inClause, $params] = $this->buildIdInClause($salesIds, 'sid');
        $params['kpiMonth'] = $month;

        $rows = Database::getInstance()->fetchAll(
            "SELECT k.salesId, k.kpiTarget, k.updatedAt, au.fullName AS updatedByName
             FROM salesMonthlyKpis k
             LEFT JOIN adminUsers au ON au.id = k.updatedByAdminId
             WHERE k.salesId IN ({$inClause}) AND k.kpiMonth = :kpiMonth",
            $params
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['salesId']] = $row;
        }
        return $map;
    }

    /**
     * Fold the aggregates into one row per sales user plus a range total
     */
    private function buildPayload(array $salesUsers, array $dayTotals, array $monthTotals, array $registrations, array $kpiMap, $date, $month, $tzOffsetMinutes, array $access) {
        $day = $this->indexTransactionTotals($dayTotals);
        $monthToDate = $this->indexTransactionTotals($monthTotals);

        $leads = [];
        $clients = [];
        foreach ($registrations as $row) {
            $salesId = (int) $row['salesId'];
            $leads[$salesId] = (int) ($row['newLeads'] ?? 0);
            $clients[$salesId] = (int) ($row['newClients'] ?? 0);
        }

        $rows = [];
        $totals = [
            'deposits' => 0.0,
            'depositCount' => 0,
            'withdrawals' => 0.0,
            'withdrawalCount' => 0,
            'netDeposit' => 0.0,
            'newLeads' => 0,
            'newClients' => 0,
            'monthToDateNetDeposit' => 0.0,
            'kpiTarget' => 0.0,
        ];

        foreach ($salesUsers as $user) {
            $salesId = (int) $user['id'];
            $dayRow = $day[$salesId] ?? null;
            $mtdRow = $monthToDate[$salesId] ?? null;

            $deposits = $dayRow['deposits'] ?? 0.0;
            $withdrawals = $dayRow['withdrawals'] ?? 0.0;
            $netDeposit = $deposits - $withdrawals;
            $mtdNetDeposit = ($mtdRow['deposits'] ?? 0.0) - ($mtdRow['withdrawals'] ?? 0.0);

            $kpiRow = $kpiMap[$salesId] ?? null;
            $kpiTarget = $kpiRow !== null ? (float) $kpiRow['kpiTarget'] : null;

            $rows[] = [
                'salesId' => $salesId,
                'salesName' => $this->displayName($user),
                'email' => $user['email'] ?? '',
                'status' => $user['status'] ?? '',
                'deposits' => $deposits,
                'depositCount' => $dayRow['depositCount'] ?? 0,
                'withdrawals' => $withdrawals,
                'withdrawalCount' => $dayRow['withdrawalCount'] ?? 0,
                'netDeposit' => $netDeposit,
                'newLeads' => $leads[$salesId] ?? 0,
                'newClients' => $clients[$salesId] ?? 0,
                'monthToDateNetDeposit' => $mtdNetDeposit,
                'kpiTarget' => $kpiTarget,
                'kpiAchievementRate' => $this->achievementRate($mtdNetDeposit, $kpiTarget),
                'kpiUpdatedAt' => $kpiRow['updatedAt'] ?? null,
                'kpiUpdatedByName' => $kpiRow['updatedByName'] ?? null,
            ];

            $totals['deposits'] += $deposits;
            $totals['depositCount'] += $dayRow['depositCount'] ?? 0;
            $totals['withdrawals'] += $withdrawals;
            $totals['withdrawalCount'] += $dayRow['withdrawalCount'] ?? 0;
            $totals['netDeposit'] += $netDeposit;
            $totals['newLeads'] += $leads[$salesId] ?? 0;
            $totals['newClients'] += $clients[$salesId] ?? 0;
            $totals['monthToDateNetDeposit'] += $mtdNetDeposit;
            $totals['kpiTarget'] += $kpiTarget ?? 0.0;
        }

        $totals['kpiAchievementRate'] = $this->achievementRate(
            $totals['monthToDateNetDeposit'],
            $totals['kpiTarget'] > 0 ? $totals['kpiTarget'] : null
        );

        return [
            'date' => $date,
            'month' => $month,
            'timezone' => [
                'offsetMinutes' => $tzOffsetMinutes,
                'label' => $this->offsetLabel($tzOffsetMinutes),
            ],
            'permissions' => [
                'canEditKpi' => $access['canEditKpi'],
                'canViewAllSales' => $access['canViewAllSales'],
            ],
            'summary' => $totals,
            'rows' => $rows,
        ];
    }

    private function indexTransactionTotals(array $totals) {
        return SalesPerformanceMetrics::indexTransactionTotals($totals);
    }

    private function displayName(array $user) {
        $fullName = trim((string) ($user['fullName'] ?? ''));
        return $fullName !== '' ? $fullName : (string) ($user['username'] ?? '');
    }

    private function buildIdInClause(array $ids, $prefix) {
        return SalesPerformanceMetrics::buildIdInClause($ids, $prefix);
    }

    /**
     * A KPI may only be written against someone the page itself would list, so this
     * has to use the same definition of "sales" rather than its own query.
     */
    private function isSalesUser($salesId) {
        $config = require __DIR__ . '/../config/app.php';
        $salesRoleId = (int) ($config['special_roles']['sales_role_id'] ?? 6);

        $permRow = (new AdminPermission())->findByKey('page_salesdashboard_view');
        $salesDashboardViewPid = $permRow ? (int) ($permRow['id'] ?? 0) : 0;

        $rows = (new AdminUser())->getSalesUsers($salesRoleId, $salesDashboardViewPid, '', (int) $salesId);

        return !empty($rows);
    }

    /**
     * Achievement rate in percent; null when there is no usable target
     */
    private function achievementRate($actual, $target) {
        if ($target === null || (float) $target <= 0) {
            return null;
        }
        return round(((float) $actual / (float) $target) * 100, 2);
    }

    private function shiftMinutes($tzOffsetMinutes) {
        return SalesPerformanceMetrics::shiftMinutes($tzOffsetMinutes);
    }

    private function resolveTzOffsetMinutes($raw) {
        return SalesPerformanceMetrics::resolveTzOffsetMinutes($raw);
    }

    private function todayInOffset($tzOffsetMinutes) {
        return SalesPerformanceMetrics::todayInOffset($tzOffsetMinutes);
    }

    private function offsetLabel($tzOffsetMinutes) {
        return SalesPerformanceMetrics::offsetLabel($tzOffsetMinutes);
    }

    private function parseDate($raw) {
        return SalesPerformanceMetrics::parseDate($raw);
    }

    private function parseMonth($raw) {
        return SalesPerformanceMetrics::parseMonth($raw);
    }

    private function requireAdmin() {
        $payload = JWT::getPayload();

        if (!$payload || ($payload['type'] ?? '') !== 'admin') {
            Response::forbidden('Admin authentication required');
        }

        if (empty($payload['userId'])) {
            Response::unauthorized('Invalid token payload');
        }

        return ['userId' => $payload['userId']];
    }
}
