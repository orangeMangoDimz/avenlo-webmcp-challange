<?php

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/SalesPerformanceMetrics.php';
require_once __DIR__ . '/../services/WebMcpSalesService.php';

class WebMcpSalesController {
    private const MAX_ID = 2147483647;
    private const MAX_PAGE = 1000;
    private const MAX_LIMIT = 50;
    private const MAX_QUERY_LENGTH = 100;

    private $service;

    public function __construct() {
        $this->service = new WebMcpSalesService();
    }

    public static function routeHandlers(): array {
        return [
            'admin/search-sales' => 'searchSales',
            'admin/get-sales-clients' => 'getSalesClients',
            'admin/get-sales-partners' => 'getSalesPartners',
            'admin/get-sales-performance' => 'getSalesPerformance',
            'admin/get-sales-daily-summary' => 'getSalesDailySummary',
            'admin/get-sales-leaderboard' => 'getSalesLeaderboard',
        ];
    }

    public static function normalizeSearchInput(array $input): array {
        self::rejectUnsupportedKeys($input, ['query', 'page', 'limit']);
        if (!array_key_exists('query', $input)) {
            throw new InvalidArgumentException('query is required.');
        }
        return [
            'query' => self::normalizeString($input['query'], 'query', self::MAX_QUERY_LENGTH),
            'page' => self::normalizePositiveInteger($input['page'] ?? 1, 'page', self::MAX_PAGE),
            'limit' => self::normalizePositiveInteger($input['limit'] ?? 25, 'limit', self::MAX_LIMIT),
        ];
    }

    public static function normalizeRelationshipInput(array $input): array {
        self::rejectUnsupportedKeys($input, ['salesId', 'search', 'page', 'limit']);
        $normalized = [
            'salesId' => self::normalizePositiveInteger($input['salesId'] ?? null, 'salesId', self::MAX_ID),
        ];
        if (array_key_exists('search', $input)) {
            $normalized['search'] = self::normalizeString($input['search'], 'search', self::MAX_QUERY_LENGTH);
        }
        $normalized['page'] = self::normalizePositiveInteger($input['page'] ?? 1, 'page', self::MAX_PAGE);
        $normalized['limit'] = self::normalizePositiveInteger($input['limit'] ?? 25, 'limit', self::MAX_LIMIT);
        return $normalized;
    }

    public static function normalizePerformanceInput(array $input): array {
        self::rejectUnsupportedKeys($input, ['salesId', 'month', 'tzOffset']);
        $normalized = [
            'salesId' => self::normalizePositiveInteger($input['salesId'] ?? null, 'salesId', self::MAX_ID),
        ];
        if (array_key_exists('month', $input)) {
            $month = SalesPerformanceMetrics::parseMonth($input['month']);
            if ($month === null) {
                throw new InvalidArgumentException('month must use a valid YYYY-MM value.');
            }
            $normalized['month'] = $month;
        }
        if (array_key_exists('tzOffset', $input)) {
            $normalized['tzOffset'] = self::normalizeTzOffset($input['tzOffset']);
        }
        return $normalized;
    }

    public static function normalizeDailyInput(array $input): array {
        self::rejectUnsupportedKeys($input, ['salesId', 'date', 'tzOffset']);
        $normalized = [
            'salesId' => self::normalizePositiveInteger($input['salesId'] ?? null, 'salesId', self::MAX_ID),
        ];
        if (array_key_exists('date', $input)) {
            $date = SalesPerformanceMetrics::parseDate($input['date']);
            if ($date === null) {
                throw new InvalidArgumentException('date must use a valid YYYY-MM-DD value.');
            }
            $normalized['date'] = $date;
        }
        if (array_key_exists('tzOffset', $input)) {
            $normalized['tzOffset'] = self::normalizeTzOffset($input['tzOffset']);
        }
        return $normalized;
    }

    public static function normalizeLeaderboardInput(array $input): array {
        self::rejectUnsupportedKeys($input, ['metric', 'period', 'date', 'month', 'tzOffset', 'limit']);
        $metric = isset($input['metric']) ? trim((string)$input['metric']) : 'newClients';
        if (!in_array($metric, ['newClients', 'newLeads', 'netDeposit', 'deposits'], true)) {
            throw new InvalidArgumentException('metric must be one of: newClients, newLeads, netDeposit, deposits.');
        }
        $period = isset($input['period']) ? strtolower(trim((string)$input['period'])) : 'month';
        if (!in_array($period, ['day', 'month'], true)) {
            throw new InvalidArgumentException('period must be day or month.');
        }
        if ($period === 'day' && array_key_exists('month', $input)) {
            throw new InvalidArgumentException('month is not supported for a daily leaderboard.');
        }
        if ($period === 'month' && array_key_exists('date', $input)) {
            throw new InvalidArgumentException('date is not supported for a monthly leaderboard.');
        }

        $normalized = ['metric' => $metric, 'period' => $period];
        if (array_key_exists('date', $input)) {
            $date = SalesPerformanceMetrics::parseDate($input['date']);
            if ($date === null) {
                throw new InvalidArgumentException('date must use a valid YYYY-MM-DD value.');
            }
            $normalized['date'] = $date;
        }
        if (array_key_exists('month', $input)) {
            $month = SalesPerformanceMetrics::parseMonth($input['month']);
            if ($month === null) {
                throw new InvalidArgumentException('month must use a valid YYYY-MM value.');
            }
            $normalized['month'] = $month;
        }
        if (array_key_exists('tzOffset', $input)) {
            $normalized['tzOffset'] = self::normalizeTzOffset($input['tzOffset']);
        }
        $normalized['limit'] = self::normalizePositiveInteger($input['limit'] ?? 10, 'limit', self::MAX_LIMIT);
        return $normalized;
    }

    public static function targetInScope(array $access, int $salesId): bool {
        return !empty($access['can_view_all_sales'])
            || (int)($access['admin_user_id'] ?? 0) === $salesId;
    }

    public static function leaderboardInScope(array $access): bool {
        return !empty($access['can_view_daily']) && !empty($access['can_view_all_sales']);
    }

    public function searchSales(): void {
        $access = $this->requireSalesAccess();
        $input = $this->normalizeRequest([self::class, 'normalizeSearchInput']);
        $onlyUserId = $access['can_view_all_sales'] ? null : (int)$access['admin_user_id'];
        Response::success($this->service->searchSales($input, $onlyUserId));
    }

    public function getSalesClients(): void {
        $access = $this->requireSalesAccess();
        $input = $this->normalizeRequest([self::class, 'normalizeRelationshipInput']);
        $sales = $this->visibleSales($input['salesId'], $access);
        Response::success($this->service->getSalesClients($sales, $input));
    }

    public function getSalesPartners(): void {
        $access = $this->requireSalesAccess();
        $input = $this->normalizeRequest([self::class, 'normalizeRelationshipInput']);
        $sales = $this->visibleSales($input['salesId'], $access);
        Response::success($this->service->getSalesPartners($sales, $input));
    }

    public function getSalesPerformance(): void {
        $access = $this->requireSalesAccess();
        $input = $this->normalizeRequest([self::class, 'normalizePerformanceInput']);
        $sales = $this->visibleSales($input['salesId'], $access);
        $tzOffset = $input['tzOffset'] ?? SalesPerformanceMetrics::DEFAULT_TZ_OFFSET_MINUTES;
        $month = $input['month'] ?? substr(SalesPerformanceMetrics::todayInOffset($tzOffset), 0, 7);
        Response::success($this->service->getPerformance($sales, $month, $tzOffset));
    }

    public function getSalesDailySummary(): void {
        $access = $this->requireSalesAccess();
        if (empty($access['can_view_daily'])) {
            Response::forbidden('You do not have permission to view daily sales results');
        }
        $input = $this->normalizeRequest([self::class, 'normalizeDailyInput']);
        $sales = $this->visibleSales($input['salesId'], $access);
        $tzOffset = $input['tzOffset'] ?? SalesPerformanceMetrics::DEFAULT_TZ_OFFSET_MINUTES;
        $date = $input['date'] ?? SalesPerformanceMetrics::todayInOffset($tzOffset);
        Response::success($this->service->getDailySummary($sales, $date, $tzOffset));
    }

    public function getSalesLeaderboard(): void {
        $access = $this->requireSalesAccess();
        if (!self::leaderboardInScope($access)) {
            Response::forbidden('You do not have permission to view the sales leaderboard');
        }
        $input = $this->normalizeRequest([self::class, 'normalizeLeaderboardInput']);
        $input['tzOffset'] = $input['tzOffset'] ?? SalesPerformanceMetrics::DEFAULT_TZ_OFFSET_MINUTES;
        if ($input['period'] === 'day') {
            $input['date'] = $input['date'] ?? SalesPerformanceMetrics::todayInOffset($input['tzOffset']);
        } else {
            $input['month'] = $input['month'] ?? substr(SalesPerformanceMetrics::todayInOffset($input['tzOffset']), 0, 7);
        }
        Response::success($this->service->getLeaderboard($input));
    }

    private function requireSalesAccess(): array {
        AuthMiddleware::requireAdmin();
        $access = AdminSalesPermission::getCurrentSalesAccess();
        if (empty($access['can_view_sales'])) {
            Response::forbidden('You do not have permission to view sales data');
        }
        return $access;
    }

    private function visibleSales(int $salesId, array $access): array {
        if (!self::targetInScope($access, $salesId)) {
            Response::notFound('Sales user not found');
        }
        $sales = $this->service->findSales($salesId);
        if (!$sales) {
            Response::notFound('Sales user not found');
        }
        return $sales;
    }

    private function normalizeRequest(callable $normalizer): array {
        $input = $_GET;
        unset($input['path']);
        try {
            return $normalizer($input);
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }
    }

    private static function rejectUnsupportedKeys(array $input, array $allowed): void {
        $unsupported = array_diff(array_keys($input), $allowed);
        if ($unsupported) {
            throw new InvalidArgumentException((string)reset($unsupported) . ' is not supported.');
        }
    }

    private static function normalizePositiveInteger($value, string $name, int $maximum): int {
        if (is_int($value)) {
            $number = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', trim($value))) {
            $number = (int)trim($value);
        } else {
            throw new InvalidArgumentException("{$name} must be an integer between 1 and {$maximum}.");
        }
        if ($number < 1 || $number > $maximum) {
            throw new InvalidArgumentException("{$name} must be an integer between 1 and {$maximum}.");
        }
        return $number;
    }

    private static function normalizeString($value, string $name, int $maximum): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException("{$name} must be a string.");
        }
        $value = trim($value);
        if ($value === '' || strlen($value) > $maximum) {
            throw new InvalidArgumentException("{$name} must be between 1 and {$maximum} characters.");
        }
        return $value;
    }

    private static function normalizeTzOffset($value): int {
        if (is_int($value)) {
            $offset = $value;
        } elseif (is_string($value) && preg_match('/^-?\d+$/', trim($value))) {
            $offset = (int)trim($value);
        } else {
            throw new InvalidArgumentException('tzOffset must be an integer between -720 and 840.');
        }
        if ($offset < SalesPerformanceMetrics::MIN_TZ_OFFSET_MINUTES
            || $offset > SalesPerformanceMetrics::MAX_TZ_OFFSET_MINUTES) {
            throw new InvalidArgumentException('tzOffset must be an integer between -720 and 840.');
        }
        return $offset;
    }
}
