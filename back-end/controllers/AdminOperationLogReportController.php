<?php
/**
 * 后台操作日志报表
 */

require_once __DIR__ . '/../models/AdminOperationLog.php';
require_once __DIR__ . '/../models/AdminOperationLogModuleSetting.php';
require_once __DIR__ . '/../models/AdminDictionaryItem.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

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

        Response::success([
            'modules' => $tabs,
            'operationTypes' => $this->dictModel->findOperationLogTypes(),
            'subModulesByModelKey' => $this->dictModel->findOperationLogSubModulesByModelKeys($modelKeys),
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

            $active = OperationLogReportExportService::getActiveForAdmin($adminUserId);
            $activeStatus = (string)($active['status'] ?? '');
            if (in_array($activeStatus, ['queued', 'running', 'cancelling'], true)) {
                Response::error('Export already in progress', 409, $this->exportProgressPayload($active));
            }
            if ($activeStatus === 'done') {
                OperationLogReportExportService::clearActive($adminUserId);
            }

            $query = $this->sanitizeExportQuery($this->getJsonBody());
            if ($query === null) {
                Response::validationError(['modelKey' => 'modelKey is required']);
            }

            $jobId = str_replace('.', '', uniqid('aolr_', true));
            $fileName = 'operation-log-report_' . date('Y-m-d') . '.csv';
            OperationLogReportExportService::ensureExportDir();
            OperationLogReportExportService::writeProgress($jobId, [
                'adminUserId' => $adminUserId,
                'status' => 'queued',
                'cancelRequested' => false,
                'percent' => 0,
                'processed' => 0,
                'total' => 0,
                'message' => 'Queued',
                'file' => $jobId . '.csv',
                'fileName' => $fileName,
            ]);
            OperationLogReportExportService::writeActive($adminUserId, $jobId);

            $payload = [
                'type' => 'export_admin_operation_log_report',
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
                OperationLogReportExportService::writeProgress($jobId, [
                    'adminUserId' => $adminUserId,
                    'status' => 'error',
                    'cancelRequested' => false,
                    'percent' => 0,
                    'message' => $e->getMessage(),
                    'file' => null,
                ]);
                OperationLogReportExportService::clearActive($adminUserId);
                Response::error('Failed to queue export task: ' . $e->getMessage(), 500);
            }

            Response::success([
                'jobId' => $jobId,
                'queued' => true,
            ], 'Export task accepted');
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

            OperationLogReportExportService::clearActive($adminUserId);

            $filename = trim((string)($progress['fileName'] ?? ''));
            if ($filename === '' || !preg_match('/^[A-Za-z0-9._-]+\.csv$/', $filename)) {
                $filename = 'operation-log-report_' . date('Y-m-d') . '.csv';
            }
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
        return [
            'modelKey' => trim((string) ($_GET['model_key'] ?? $_GET['modelKey'] ?? '')),
            'startDate' => trim((string) ($_GET['start_date'] ?? $_GET['startDate'] ?? '')),
            'endDate' => trim((string) ($_GET['end_date'] ?? $_GET['endDate'] ?? '')),
            'keyword' => trim((string) ($_GET['keyword'] ?? '')),
            'subModule' => trim((string) ($_GET['sub_module'] ?? $_GET['subModule'] ?? 'all')) ?: 'all',
            'operationType' => trim((string) ($_GET['operation_type'] ?? $_GET['operationType'] ?? 'all')) ?: 'all',
        ];
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

        return [
            'filters' => [
                'modelKey' => $modelKey,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'keyword' => $keyword,
                'subModule' => $subModule,
                'operationType' => $operationType,
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
            'status' => (string)($progress['status'] ?? ''),
            'percent' => (int)($progress['percent'] ?? 0),
            'message' => (string)($progress['message'] ?? ''),
            'processed' => (int)($progress['processed'] ?? 0),
            'total' => (int)($progress['total'] ?? 0),
            'downloadReady' => !empty($progress['downloadReady']) || (($progress['status'] ?? '') === 'done'),
            'fileName' => (string)($progress['fileName'] ?? ''),
        ];
    }

    private function parsePerPage($raw) {
        if ($raw === 'all') {
            return 'all';
        }
        return max(1, min(100, (int) $raw));
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
