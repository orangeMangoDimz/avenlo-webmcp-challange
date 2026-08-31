<?php
/**
 * 后台操作日志报表
 */

require_once __DIR__ . '/../models/AdminOperationLog.php';
require_once __DIR__ . '/../models/AdminOperationLogModuleSetting.php';
require_once __DIR__ . '/../models/AdminDictionaryItem.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/WebMcpAdminLogService.php';

class AdminOperationLogReportController {
    private $logModel;
    private $moduleModel;
    private $dictModel;

    public function __construct() {
        $this->logModel = new AdminOperationLog();
        $this->moduleModel = new AdminOperationLogModuleSetting();
        $this->dictModel = new AdminDictionaryItem();
    }

    /**
     * GET /api/operation-log/reports/init
     */
    public function init() {
        AuthMiddleware::authenticate();
        AuthMiddleware::checkPermission('page_operationlogreport_readonly');

        $tabs = $this->formatTabs($this->moduleModel->findReportTabs());
        if (empty($tabs)) {
            Response::success([
                'modules' => [],
                'operationTypes' => $this->dictModel->findOperationLogTypes(),
                'subModulesByModelKey' => new stdClass(),
                'defaults' => null,
                'list' => ['items' => [], 'pagination' => ['total' => 0, 'page' => 1, 'per_page' => 10, 'total_pages' => 1]],
            ]);
            return;
        }

        $modelKeys = array_map(function ($tab) {
            return $tab['modelKey'];
        }, $tabs);

        $defaults = [
            'modelKey' => $tabs[0]['modelKey'],
            'startDate' => gmdate('Y-m-d', strtotime('first day of this month UTC')),
            'endDate' => gmdate('Y-m-d'),
            'keyword' => '',
            'subModule' => 'all',
            'operationType' => 'all',
            'page' => 1,
            'perPage' => 10,
        ];

        $filters = [
            'modelKey' => $defaults['modelKey'],
            'startDate' => $defaults['startDate'],
            'endDate' => $defaults['endDate'],
            'keyword' => '',
            'subModule' => 'all',
            'operationType' => 'all',
        ];

        $perPage = 10;
        $list = $this->fetchListPayload($filters, 1, $perPage);

        $subModulesByModelKey = $this->dictModel->findOperationLogSubModulesByModelKeys($modelKeys);
        $allSubModules = [];
        foreach ($subModulesByModelKey as $key => $items) {
            if ($key === 'all') {
                continue;
            }
            foreach ($items as $item) {
                $value = trim((string)($item['value'] ?? ''));
                if ($value !== '') {
                    $allSubModules[$value] = $item;
                }
            }
        }
        $subModulesByModelKey['all'] = array_values($allSubModules);

        Response::success([
            'modules' => $tabs,
            'operationTypes' => $this->dictModel->findOperationLogTypes(),
            'subModulesByModelKey' => $subModulesByModelKey,
            'defaults' => $defaults,
            'list' => $list,
        ]);
    }

    /**
     * GET /api/operation-log/reports
     */
    public function index() {
        AuthMiddleware::authenticate();
        AuthMiddleware::checkPermission('page_operationlogreport_readonly');

        $filters = $this->parseListFiltersFromQuery();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $this->parsePerPage($_GET['per_page'] ?? 10);
        if (($filters['modelKey'] ?? '') === 'all' && $perPage === 'all') {
            $perPage = 100;
        }

        Response::success($this->fetchListPayload($filters, $page, $perPage));
    }

    /**
     * POST /api/operation-log/reports/export
     */
    public function export() {
        try {
            require_once __DIR__ . '/../services/OperationLogReportExportService.php';

            AuthMiddleware::authenticate();
            AuthMiddleware::checkPermission('page_operationlogreport_export');

            $currentUser = AuthMiddleware::getCurrentUser();
            $adminUserId = (int)($currentUser['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }

            $query = $this->sanitizeExportQuery($this->getJsonBody());
            if ($query === null) {
                Response::validationError(['filters' => 'Operation-log export filters are invalid']);
            }
            try {
                $query['filters'] = WebMcpAdminLogService::scopeFiltersToVisibleModules(
                    $query['filters'],
                    $this->visibleModelKeys()
                );
            } catch (InvalidArgumentException $e) {
                Response::validationError(['module' => $e->getMessage()]);
            }
            try {
                $queued = OperationLogReportExportService::queueForAdmin($adminUserId, $query);
            } catch (RuntimeException $e) {
                $statusCode = strpos($e->getMessage(), 'already in progress') !== false ? 409 : 503;
                Response::error($e->getMessage(), $statusCode);
            }

            Response::success($queued, !empty($queued['reused'])
                ? 'Existing export remains available'
                : 'Export task accepted');
        } catch (Exception $e) {
            Response::error('Failed to export: ' . $e->getMessage(), 500);
        }
    }

    public function exportActive() {
        try {
            require_once __DIR__ . '/../services/OperationLogReportExportService.php';
            AuthMiddleware::authenticate();
            AuthMiddleware::checkPermission('page_operationlogreport_export');
            $currentUser = AuthMiddleware::getCurrentUser();
            $adminUserId = (int)($currentUser['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $progress = OperationLogReportExportService::getActiveForAdmin($adminUserId);
            Response::success($this->exportProgressPayload($progress));
        } catch (Exception $e) {
            Response::error('Failed to fetch export status: ' . $e->getMessage(), 500);
        }
    }

    public function exportStatus() {
        try {
            require_once __DIR__ . '/../services/OperationLogReportExportService.php';
            AuthMiddleware::authenticate();
            AuthMiddleware::checkPermission('page_operationlogreport_export');
            $currentUser = AuthMiddleware::getCurrentUser();
            $adminUserId = (int)($currentUser['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $jobId = isset($_GET['jobId']) ? trim((string)$_GET['jobId']) : '';
            if ($jobId === '' || !$this->isSafeJobId($jobId)) {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = OperationLogReportExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['adminUserId'] ?? 0) !== $adminUserId) {
                Response::notFound('Export job not found');
            }
            require_once __DIR__ . '/../services/ExportJobTimeoutReaper.php';
            $progress = ExportJobTimeoutReaper::reapIfStale(
                $jobId,
                $progress,
                [OperationLogReportExportService::class, 'writeProgress'],
                [OperationLogReportExportService::class, 'clearActive']
            );
            Response::success($this->exportProgressPayload($progress));
        } catch (Exception $e) {
            Response::error('Failed to fetch export status: ' . $e->getMessage(), 500);
        }
    }

    public function exportCancel() {
        try {
            require_once __DIR__ . '/../services/OperationLogReportExportService.php';
            AuthMiddleware::authenticate();
            AuthMiddleware::checkPermission('page_operationlogreport_export');
            $currentUser = AuthMiddleware::getCurrentUser();
            $adminUserId = (int)($currentUser['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $rawBody = json_decode(file_get_contents('php://input'), true);
            $jobId = '';
            if (is_array($rawBody) && !empty($rawBody['jobId'])) {
                $jobId = trim((string)$rawBody['jobId']);
            } elseif (!empty($_GET['jobId'])) {
                $jobId = trim((string)$_GET['jobId']);
            }
            if ($jobId === '' || !$this->isSafeJobId($jobId)) {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = OperationLogReportExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['adminUserId'] ?? 0) !== $adminUserId) {
                Response::notFound('Export job not found');
            }
            $status = (string)($progress['status'] ?? '');
            if ($status === 'cancelled') {
                Response::success($this->exportProgressPayload($progress), 'Already cancelled');
            }
            if ($status === 'error') {
                OperationLogReportExportService::clearActive($adminUserId);
                Response::success($this->exportProgressPayload($progress), 'Export already failed');
            }
            OperationLogReportExportService::requestCancel($jobId);
            $updated = OperationLogReportExportService::readProgress($jobId);
            Response::success($this->exportProgressPayload($updated), 'Cancel requested');
        } catch (Exception $e) {
            Response::error('Failed to cancel export: ' . $e->getMessage(), 500);
        }
    }

    public function exportDownload() {
        try {
            require_once __DIR__ . '/../services/OperationLogReportExportService.php';
            AuthMiddleware::authenticate();
            AuthMiddleware::checkPermission('page_operationlogreport_export');
            $currentUser = AuthMiddleware::getCurrentUser();
            $adminUserId = (int)($currentUser['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $jobId = isset($_GET['jobId']) ? trim((string)$_GET['jobId']) : '';
            if ($jobId === '' || !$this->isSafeJobId($jobId)) {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = OperationLogReportExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['adminUserId'] ?? 0) !== $adminUserId) {
                Response::notFound('Export job not found');
            }
            if (($progress['status'] ?? '') !== 'done') {
                Response::error('Export is not ready', 400);
            }
            $csvFile = OperationLogReportExportService::csvPath($jobId);
            if (!file_exists($csvFile) || !is_readable($csvFile)) {
                Response::error('Export file missing', 404);
            }

            $filename = trim((string)($progress['fileName'] ?? ''));
            if ($filename === '' || !preg_match('/^[A-Za-z0-9._-]+\.csv$/', $filename)) {
                $filename = 'operation-log-report_' . date('Y-m-d') . '.csv';
            }
            OperationLogReportExportService::writeProgress($jobId, array_merge($progress, [
                'downloadRequestedAt' => date('Y-m-d H:i:s'),
                'downloadRequestCount' => max(0, (int)($progress['downloadRequestCount'] ?? 0)) + 1,
            ]));
            OperationLogReportExportService::clearActive($adminUserId);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($csvFile));
            header('Cache-Control: no-store');
            readfile($csvFile);
            exit;
        } catch (Exception $e) {
            Response::error('Failed to download export: ' . $e->getMessage(), 500);
        }
    }

    private function fetchListPayload(array $filters, $page, $perPage) {
        if ($filters['modelKey'] === '') {
            Response::validationError(['modelKey' => ['modelKey is required']]);
        }
        try {
            $filters = WebMcpAdminLogService::scopeFiltersToVisibleModules(
                $filters,
                $this->visibleModelKeys()
            );
        } catch (InvalidArgumentException $e) {
            Response::validationError(['module' => $e->getMessage()]);
        }

        $total = $this->logModel->countByFilters($filters);
        if ($perPage === 'all') {
            $perPageInt = max(1, $total);
            $page = 1;
        } else {
            $perPageInt = (int) $perPage;
        }

        $rows = $this->logModel->findByFilters($filters, $page, $perPageInt);
        $items = array_map([$this, 'formatLogRow'], $rows);

        if ($perPage === 'all') {
            return [
                'items' => $items,
                'pagination' => [
                    'total' => $total,
                    'page' => 1,
                    'per_page' => 'all',
                    'total_pages' => 1,
                ],
            ];
        }

        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => (int) $page,
                'per_page' => $perPageInt,
                'total_pages' => max(1, (int) ceil($total / max(1, $perPageInt))),
            ],
        ];
    }

    private function parseListFiltersFromQuery() {
        $filters = [
            'modelKey' => trim((string) ($_GET['model_key'] ?? $_GET['modelKey'] ?? '')),
            'startDate' => trim((string) ($_GET['start_date'] ?? $_GET['startDate'] ?? '')),
            'endDate' => trim((string) ($_GET['end_date'] ?? $_GET['endDate'] ?? '')),
            'keyword' => trim((string) ($_GET['keyword'] ?? '')),
            'subModule' => trim((string) ($_GET['sub_module'] ?? $_GET['subModule'] ?? 'all')) ?: 'all',
            'operationType' => trim((string) ($_GET['operation_type'] ?? $_GET['operationType'] ?? 'all')) ?: 'all',
            'operatorId' => max(0, (int)($_GET['operator_id'] ?? $_GET['operatorId'] ?? 0)),
            'targetId' => max(0, (int)($_GET['target_id'] ?? $_GET['targetId'] ?? 0)),
            'query' => trim((string)($_GET['query'] ?? '')),
        ];
        $targetType = trim((string)($_GET['target_type'] ?? $_GET['targetType'] ?? ''));
        if ($targetType !== '') {
            $filters['targetScopes'] = WebMcpAdminLogService::targetScopes($targetType);
            if (!$filters['targetScopes']) {
                Response::validationError(['targetType' => 'targetType is not supported']);
            }
        } elseif ($filters['targetId'] > 0) {
            Response::validationError(['targetType' => 'targetType is required when targetId is provided']);
        }
        return $filters;
    }

    private function sanitizeExportQuery(array $body): ?array
    {
        $modelKey = trim((string)($body['modelKey'] ?? $body['model_key'] ?? ''));
        if ($modelKey === '' || strlen($modelKey) > 64 || !preg_match('/^[A-Za-z0-9_]+$/', $modelKey)) {
            return null;
        }

        $language = strtolower(trim((string)($body['language'] ?? 'en')));
        if ($language !== 'zh') {
            $language = 'en';
        }

        $startDate = trim((string)($body['startDate'] ?? $body['start_date'] ?? ''));
        if ($startDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = '';
        }
        $endDate = trim((string)($body['endDate'] ?? $body['end_date'] ?? ''));
        if ($endDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $endDate = '';
        }

        $keyword = trim((string)($body['keyword'] ?? ''));
        if (function_exists('mb_substr')) {
            $keyword = mb_substr($keyword, 0, 200);
        } else {
            $keyword = substr($keyword, 0, 200);
        }

        $subModule = trim((string)($body['subModule'] ?? $body['sub_module'] ?? 'all')) ?: 'all';
        if ($subModule !== 'all' && (strlen($subModule) > 64 || !preg_match('/^[A-Za-z0-9_.-]+$/', $subModule))) {
            $subModule = 'all';
        }

        $operationType = trim((string)($body['operationType'] ?? $body['operation_type'] ?? 'all')) ?: 'all';
        if ($operationType !== 'all' && (strlen($operationType) > 64 || !preg_match('/^[A-Za-z0-9_.-]+$/', $operationType))) {
            $operationType = 'all';
        }

        $operatorId = max(0, (int)($body['operatorId'] ?? $body['operator_id'] ?? 0));
        $targetId = max(0, (int)($body['targetId'] ?? $body['target_id'] ?? 0));
        $queryText = trim((string)($body['query'] ?? ''));
        if (function_exists('mb_substr')) {
            $queryText = mb_substr($queryText, 0, 200);
        } else {
            $queryText = substr($queryText, 0, 200);
        }
        $targetType = trim((string)($body['targetType'] ?? $body['target_type'] ?? ''));
        $targetScopes = $targetType !== ''
            ? WebMcpAdminLogService::targetScopes($targetType)
            : [];
        if (($targetType !== '' && !$targetScopes) || ($targetId > 0 && $targetType === '')) {
            return null;
        }

        return [
            'filters' => [
                'modelKey' => $modelKey,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'keyword' => $keyword,
                'subModule' => $subModule,
                'operationType' => $operationType,
                'operatorId' => $operatorId,
                'targetId' => $targetId,
                'query' => $queryText,
                'targetScopes' => $targetScopes,
            ],
            'language' => $language,
        ];
    }

    private function isSafeJobId($value) {
        $value = trim((string)$value);
        return $value !== '' && strlen($value) <= 80 && preg_match('/^[A-Za-z0-9._-]+$/', $value);
    }

    private function dispatchSwooleTask(array $payload): void
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

    private function exportProgressPayload(?array $progress): array
    {
        if ($progress === null) {
            return ['active' => false];
        }
        return [
            'active' => true,
            'jobId' => (string)($progress['jobId'] ?? ''),
            'exportType' => (string)($progress['exportType'] ?? 'operation_logs'),
            'status' => (string)($progress['status'] ?? ''),
            'percent' => (int)($progress['percent'] ?? 0),
            'message' => (string)($progress['message'] ?? ''),
            'processed' => (int)($progress['processed'] ?? 0),
            'total' => (int)($progress['total'] ?? 0),
            'matchedTotal' => (int)($progress['matchedTotal'] ?? $progress['total'] ?? 0),
            'truncated' => !empty($progress['truncated']),
            'downloadReady' => !empty($progress['downloadReady']) || (($progress['status'] ?? '') === 'done'),
            'downloadRequestedAt' => $progress['downloadRequestedAt'] ?? null,
            'downloadRequestCount' => max(0, (int)($progress['downloadRequestCount'] ?? 0)),
            'fileName' => (string)($progress['fileName'] ?? ''),
        ];
    }

    private function parsePerPage($raw) {
        if ($raw === 'all') {
            return 'all';
        }
        return max(1, min(100, (int) $raw));
    }

    private function visibleModelKeys(): array {
        return array_values(array_filter(array_map(
            static fn($row) => trim((string)($row['modelKey'] ?? '')),
            $this->moduleModel->findReportTabs()
        )));
    }

    private function formatTabs(array $rows) {
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'modelKey' => (string) ($row['modelKey'] ?? ''),
                'moduleNameZh' => (string) ($row['moduleNameZh'] ?? ''),
                'moduleNameEn' => (string) ($row['moduleNameEn'] ?? ''),
                'status' => (int) ($row['status'] ?? 0),
                'sortOrder' => (int) ($row['sortOrder'] ?? 0),
            ];
        }
        if ($out) {
            $out[] = [
                'id' => 0,
                'modelKey' => 'all',
                'moduleNameZh' => '全部模块',
                'moduleNameEn' => 'All modules',
                'status' => 1,
                'sortOrder' => 9999,
            ];
        }
        return $out;
    }

    public function formatLogRow(array $row) {
        $operatorName = trim((string) ($row['operatorFullName'] ?? ''));
        $targetNameZh = trim((string) ($row['targetDisplayNameZh'] ?? $row['targetDisplayName'] ?? ''));
        $targetNameEn = trim((string) ($row['targetDisplayNameEn'] ?? $row['targetDisplayName'] ?? ''));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'operatorId' => (int) ($row['operatorId'] ?? 0),
            'operatorFullName' => $operatorName,
            'operatorInitials' => $this->buildInitials($operatorName),
            'modelKey' => (string) ($row['modelKey'] ?? ''),
            'moduleNameZh' => (string) ($row['moduleNameZh'] ?? ''),
            'moduleNameEn' => (string) ($row['moduleNameEn'] ?? ''),
            'subModuleKey' => (string) ($row['subModuleKey'] ?? ''),
            'subModuleNameZh' => (string) ($row['subModuleNameZh'] ?? ''),
            'subModuleNameEn' => (string) ($row['subModuleNameEn'] ?? ''),
            'operationTypeKey' => (string) ($row['operationTypeKey'] ?? ''),
            'targetId' => isset($row['targetId']) ? (int) $row['targetId'] : null,
            'targetDisplayName' => $targetNameZh,
            'targetDisplayNameZh' => $targetNameZh,
            'targetDisplayNameEn' => $targetNameEn,
            'detailZh' => (string) ($row['detailZh'] ?? ''),
            'detailEn' => (string) ($row['detailEn'] ?? ''),
            'ipAddress' => (string) ($row['ipAddress'] ?? ''),
            'operatedAt' => $this->toIso8601Utc($row['operatedAt'] ?? null),
        ];
    }

    private function buildInitials($name) {
        $name = trim((string) $name);
        if ($name === '') {
            return '?';
        }
        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        if (!$parts) {
            return mb_strtoupper(mb_substr($name, 0, 2, 'UTF-8'), 'UTF-8');
        }
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $initials .= mb_substr($p, 0, 1, 'UTF-8');
        }
        return mb_strtoupper($initials, 'UTF-8');
    }

    private function toIso8601Utc($value) {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            $dt = new DateTime((string) $value, new DateTimeZone('UTC'));
            return $dt->format('Y-m-d\TH:i:s\Z');
        } catch (Exception $e) {
            return null;
        }
    }

    private function getJsonBody() {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
