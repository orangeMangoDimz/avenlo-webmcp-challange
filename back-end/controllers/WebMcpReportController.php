<?php

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/SalesPerformanceMetrics.php';
require_once __DIR__ . '/../services/WebMcpReportService.php';
require_once __DIR__ . '/WebMcpClientController.php';
require_once __DIR__ . '/IbStatementReportController.php';

class WebMcpReportController
{
    private const MAX_ID = 2147483647;
    private const MAX_PAGE = 1000;
    private const MAX_LIMIT = 50;
    private const MAX_TEXT = 200;

    private $service;

    public function __construct()
    {
        $this->service = new WebMcpReportService();
    }

    public static function routeHandlers(): array
    {
        return [
            'admin/get-funding-summary' => ['handler' => 'getFundingSummary', 'method' => 'GET'],
            'admin/search-funding-transactions' => ['handler' => 'searchFundingTransactions', 'method' => 'GET'],
            'admin/get-daily-sales-performance' => ['handler' => 'getDailySalesPerformance', 'method' => 'GET'],
            'admin/search-ib-partners' => ['handler' => 'searchIbPartners', 'method' => 'GET'],
            'admin/get-ib-statement' => ['handler' => 'getIbStatement', 'method' => 'GET'],
            'admin/list-custom-reports' => ['handler' => 'listCustomReports', 'method' => 'GET'],
            'admin/get-custom-report-results' => ['handler' => 'getCustomReportResults', 'method' => 'GET'],
            'admin/export-funding-report' => ['handler' => 'exportFundingReport', 'method' => 'POST'],
            'admin/export-ib-statement' => ['handler' => 'exportIbStatement', 'method' => 'POST'],
            'admin/report-export-status' => ['handler' => 'reportExportStatus', 'method' => 'GET'],
            'admin/report-export-download' => ['handler' => 'reportExportDownload', 'method' => 'GET'],
        ];
    }

    public static function normalizeFundingPeriod(array $input): array
    {
        self::rejectUnsupportedKeys($input, ['startDate', 'endDate']);
        return self::normalizePeriod($input);
    }

    public static function normalizeFundingSearch(array $input): array
    {
        self::rejectUnsupportedKeys($input, [
            'startDate', 'endDate', 'type', 'status', 'query', 'minAmount', 'maxAmount', 'page', 'limit',
        ]);
        $period = self::normalizePeriod($input);
        $normalized = $period;
        if (isset($input['type'])) {
            $type = strtolower(trim((string)$input['type']));
            if (!in_array($type, ['all', 'deposit', 'withdrawal', 'internal_transfer'], true)) {
                throw new InvalidArgumentException('type must be all, deposit, withdrawal, or internal_transfer.');
            }
            $normalized['type'] = $type;
        }
        foreach (['status' => 50, 'query' => self::MAX_TEXT] as $key => $maximum) {
            if (array_key_exists($key, $input)) {
                $normalized[$key] = self::normalizeString($input[$key], $key, $maximum);
            }
        }
        foreach (['minAmount', 'maxAmount'] as $key) {
            if (array_key_exists($key, $input)) {
                $normalized[$key] = self::normalizeNonNegativeNumber($input[$key], $key);
            }
        }
        if (isset($normalized['minAmount'], $normalized['maxAmount'])
            && $normalized['minAmount'] > $normalized['maxAmount']) {
            throw new InvalidArgumentException('minAmount cannot be greater than maxAmount.');
        }
        $normalized['page'] = self::normalizePositiveInteger($input['page'] ?? 1, 'page', self::MAX_PAGE);
        $normalized['limit'] = self::normalizePositiveInteger($input['limit'] ?? 25, 'limit', self::MAX_LIMIT);
        return $normalized;
    }

    public static function normalizeFundingExport(array $input): array
    {
        self::rejectUnsupportedKeys($input, [
            'startDate', 'endDate', 'type', 'status', 'minAmount', 'maxAmount',
        ]);
        $normalized = self::normalizeFundingSearch(array_merge($input, ['page' => 1, 'limit' => 25]));
        unset($normalized['page'], $normalized['limit']);
        return $normalized;
    }

    public static function normalizeDailyPerformance(array $input): array
    {
        self::rejectUnsupportedKeys($input, ['date', 'tzOffset', 'rankBy', 'limit']);
        $tzOffset = array_key_exists('tzOffset', $input)
            ? self::normalizeTzOffset($input['tzOffset'])
            : SalesPerformanceMetrics::DEFAULT_TZ_OFFSET_MINUTES;
        $date = isset($input['date'])
            ? self::normalizeDate($input['date'], 'date')
            : SalesPerformanceMetrics::todayInOffset($tzOffset);
        $rankBy = isset($input['rankBy']) ? trim((string)$input['rankBy']) : 'netDeposit';
        if (!in_array($rankBy, ['deposits', 'withdrawals', 'netDeposit', 'newLeads', 'newClients'], true)) {
            throw new InvalidArgumentException(
                'rankBy must be deposits, withdrawals, netDeposit, newLeads, or newClients.'
            );
        }
        return [
            'date' => $date,
            'tzOffset' => $tzOffset,
            'rankBy' => $rankBy,
            'limit' => self::normalizePositiveInteger($input['limit'] ?? 25, 'limit', self::MAX_LIMIT),
        ];
    }

    public static function normalizeIbSearch(array $input): array
    {
        self::rejectUnsupportedKeys($input, ['query', 'page', 'limit']);
        return [
            'query' => self::normalizeString($input['query'] ?? null, 'query', 100),
            'page' => self::normalizePositiveInteger($input['page'] ?? 1, 'page', self::MAX_PAGE),
            'limit' => self::normalizePositiveInteger($input['limit'] ?? 25, 'limit', self::MAX_LIMIT),
        ];
    }

    public static function normalizeIbStatement(array $input): array
    {
        self::rejectUnsupportedKeys($input, ['ibPartnerId', 'ibCode', 'startDate', 'endDate', 'page', 'limit', 'format']);
        $selectors = array_values(array_filter(
            ['ibPartnerId', 'ibCode'],
            static fn($key) => array_key_exists($key, $input)
        ));
        if (count($selectors) !== 1) {
            throw new InvalidArgumentException('Exactly one of ibPartnerId or ibCode is required.');
        }
        $normalized = [];
        if ($selectors[0] === 'ibPartnerId') {
            $normalized['ibPartnerId'] = self::normalizePositiveInteger(
                $input['ibPartnerId'],
                'ibPartnerId',
                self::MAX_ID
            );
        } else {
            $normalized['ibCode'] = self::normalizeString($input['ibCode'], 'ibCode', 64);
        }
        $normalized += self::normalizePeriod($input);
        $normalized['page'] = self::normalizePositiveInteger($input['page'] ?? 1, 'page', self::MAX_PAGE);
        $normalized['limit'] = self::normalizePositiveInteger($input['limit'] ?? 25, 'limit', self::MAX_LIMIT);
        if (isset($input['format'])) {
            $format = strtolower(trim((string)$input['format']));
            if (!in_array($format, ['csv', 'excel'], true)) {
                throw new InvalidArgumentException('format must be csv or excel.');
            }
            $normalized['format'] = $format;
        }
        return $normalized;
    }

    public static function normalizeIbExport(array $input): array
    {
        self::rejectUnsupportedKeys($input, [
            'ibPartnerId', 'ibCode', 'startDate', 'endDate', 'format',
        ]);
        $normalized = self::normalizeIbStatement(array_merge($input, ['page' => 1, 'limit' => 25]));
        unset($normalized['page'], $normalized['limit']);
        return $normalized;
    }

    public static function normalizeCustomReportList(array $input): array
    {
        self::rejectUnsupportedKeys($input, ['search', 'page', 'limit']);
        $normalized = [];
        if (array_key_exists('search', $input)) {
            $normalized['search'] = self::normalizeString($input['search'], 'search', 100);
        }
        $normalized['page'] = self::normalizePositiveInteger($input['page'] ?? 1, 'page', self::MAX_PAGE);
        $normalized['limit'] = self::normalizePositiveInteger($input['limit'] ?? 25, 'limit', self::MAX_LIMIT);
        return $normalized;
    }

    public static function normalizeCustomReportResults(array $input): array
    {
        self::rejectUnsupportedKeys($input, ['reportId', 'widgetId', 'page', 'limit']);
        $normalized = ['reportId' => self::normalizeSafeId($input['reportId'] ?? null, 'reportId')];
        if (array_key_exists('widgetId', $input)) {
            $normalized['widgetId'] = self::normalizeSafeId($input['widgetId'], 'widgetId');
        }
        $normalized['page'] = self::normalizePositiveInteger($input['page'] ?? 1, 'page', self::MAX_PAGE);
        $normalized['limit'] = self::normalizePositiveInteger($input['limit'] ?? 25, 'limit', self::MAX_LIMIT);
        return $normalized;
    }

    public function getFundingSummary(): void
    {
        $this->requirePermission('page_fundingreport_readonly');
        $input = $this->normalizeQuery([self::class, 'normalizeFundingPeriod']);
        $scope = AdminSalesPermission::getClientDataScopeForPage('page_fundingreport');
        Response::success($this->service->getFundingSummary($input, $scope));
    }

    public function searchFundingTransactions(): void
    {
        $this->requirePermission('page_fundingreport_readonly');
        $input = $this->normalizeQuery([self::class, 'normalizeFundingSearch']);
        $scope = AdminSalesPermission::getClientDataScopeForPage('page_fundingreport');
        Response::success($this->service->searchFundingTransactions($input, $scope));
    }

    public function getDailySalesPerformance(): void
    {
        $this->requirePermission('page_dailyreport_readonly');
        $access = AdminSalesPermission::getCurrentSalesAccess();
        if (empty($access['can_view_daily'])) {
            Response::forbidden('You do not have permission to view daily sales performance.');
        }
        $input = $this->normalizeQuery([self::class, 'normalizeDailyPerformance']);
        Response::success($this->service->getDailySalesPerformance($input, $access));
    }

    public function searchIbPartners(): void
    {
        $this->requireIbReadPermission();
        $input = $this->normalizeQuery([self::class, 'normalizeIbSearch']);
        $scope = AdminSalesPermission::getClientDataScopeForPage('page_ibstatement');
        Response::success($this->service->searchIbPartners($input, $scope));
    }

    public function getIbStatement(): void
    {
        $this->requireIbReadPermission();
        $input = $this->normalizeQuery([self::class, 'normalizeIbStatement']);
        $scope = AdminSalesPermission::getClientDataScopeForPage('page_ibstatement');
        $result = $this->service->getIbStatement($input, $scope);
        if ($result === null) {
            Response::notFound('IB partner not found.');
        }
        Response::success($result);
    }

    public function listCustomReports(): void
    {
        $this->requirePermission('page_fundingreport_readonly');
        $input = $this->normalizeQuery([self::class, 'normalizeCustomReportList']);
        Response::success($this->service->listCustomReports($input));
    }

    public function getCustomReportResults(): void
    {
        $this->requirePermission('page_fundingreport_readonly');
        $input = $this->normalizeQuery([self::class, 'normalizeCustomReportResults']);
        $scope = AdminSalesPermission::getClientDataScopeForPage('page_fundingreport');
        $result = $this->service->getCustomReportResults($input, $scope);
        if ($result === null) {
            Response::notFound('Custom report or widget not found.');
        }
        Response::success($result);
    }

    public function exportFundingReport(): void
    {
        $this->requirePermission('page_fundingreport_export');
        $input = $this->normalizeJsonBody([self::class, 'normalizeFundingExport']);
        (new WebMcpClientController())->exportFundingReport($input);
    }

    public function exportIbStatement(): void
    {
        $this->requireIbReadPermission();
        AuthMiddleware::checkPermission('page_ibstatement_export');
        $input = $this->normalizeJsonBody([self::class, 'normalizeIbExport']);
        $scope = AdminSalesPermission::getClientDataScopeForPage('page_ibstatement');
        $partnerId = $this->service->resolveIbPartnerId($input, $scope);
        if ($partnerId === null) {
            Response::notFound('IB partner not found.');
        }
        unset($input['ibCode']);
        $input['ibPartnerId'] = $partnerId;
        (new IbStatementReportController())->exportReport($input);
    }

    public function reportExportStatus(): void
    {
        $kind = $this->exportKind();
        if ($kind === 'funding_report') {
            (new WebMcpClientController())->exportStatus();
        }
        (new IbStatementReportController())->exportStatus();
    }

    public function reportExportDownload(): void
    {
        $kind = $this->exportKind();
        if ($kind === 'funding_report') {
            (new WebMcpClientController())->exportDownload();
        }
        (new IbStatementReportController())->exportDownload();
    }

    private function requirePermission(string $permission): void
    {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::checkPermission($permission);
    }

    private function requireIbReadPermission(): void
    {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::checkAnyPermission(['page_ibstatement_readonly', 'page_ibstatement']);
    }

    private function normalizeQuery(callable $normalizer): array
    {
        $input = $_GET;
        unset($input['path']);
        try {
            return $normalizer($input);
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }
    }

    private function normalizeJsonBody(callable $normalizer): array
    {
        $body = json_decode((string)file_get_contents('php://input'), true);
        try {
            return $normalizer(is_array($body) ? $body : []);
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }
    }

    private function exportKind(): string
    {
        $kind = trim((string)($_GET['exportKind'] ?? ''));
        if (!in_array($kind, ['funding_report', 'ib_statement'], true)) {
            Response::error('exportKind must be funding_report or ib_statement.', 422);
        }
        return $kind;
    }

    private static function normalizePeriod(array $input): array
    {
        $hasStart = array_key_exists('startDate', $input);
        $hasEnd = array_key_exists('endDate', $input);
        if ($hasStart !== $hasEnd) {
            throw new InvalidArgumentException('startDate and endDate must both be provided.');
        }
        $start = $hasStart ? self::normalizeDate($input['startDate'], 'startDate') : date('Y-m-01');
        $end = $hasEnd ? self::normalizeDate($input['endDate'], 'endDate') : date('Y-m-d');
        if ($start > $end) {
            throw new InvalidArgumentException('startDate cannot be after endDate.');
        }
        return ['startDate' => $start, 'endDate' => $end];
    }

    private static function normalizeDate($value, string $name): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException("{$name} must use YYYY-MM-DD format.");
        }
        $value = trim($value);
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("{$name} must use a valid YYYY-MM-DD date.");
        }
        return $value;
    }

    private static function normalizePositiveInteger($value, string $name, int $maximum): int
    {
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

    private static function normalizeTzOffset($value): int
    {
        if (is_int($value)) {
            $offset = $value;
        } elseif (is_string($value) && preg_match('/^-?\d+$/', trim($value))) {
            $offset = (int)trim($value);
        } else {
            throw new InvalidArgumentException('tzOffset must be an integer between -720 and 840.');
        }
        if ($offset < -720 || $offset > 840) {
            throw new InvalidArgumentException('tzOffset must be an integer between -720 and 840.');
        }
        return $offset;
    }

    private static function normalizeString($value, string $name, int $maximum): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException("{$name} must be a string.");
        }
        $value = trim($value);
        if ($value === '' || strlen($value) > $maximum) {
            throw new InvalidArgumentException("{$name} must be between 1 and {$maximum} characters.");
        }
        return $value;
    }

    private static function normalizeSafeId($value, string $name): string
    {
        $value = self::normalizeString($value, $name, 64);
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            throw new InvalidArgumentException("{$name} contains unsupported characters.");
        }
        return $value;
    }

    private static function normalizeNonNegativeNumber($value, string $name): float
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException("{$name} must be a non-negative number.");
        }
        $number = (float)$value;
        if (!is_finite($number) || $number < 0) {
            throw new InvalidArgumentException("{$name} must be a non-negative number.");
        }
        return $number;
    }

    private static function rejectUnsupportedKeys(array $input, array $allowed): void
    {
        $unsupported = array_values(array_diff(array_keys($input), $allowed));
        if ($unsupported) {
            throw new InvalidArgumentException($unsupported[0] . ' is not supported.');
        }
    }
}
