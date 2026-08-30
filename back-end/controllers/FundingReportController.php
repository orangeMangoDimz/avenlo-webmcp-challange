<?php
/**
 * Funding Report Controller
 * 负责资金报告相关接口
 */

require_once __DIR__ . '/../models/Deposit.php';
require_once __DIR__ . '/../models/Withdrawal.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';

class FundingReportController {
    private $depositModel;
    private $withdrawalModel;

    public function __construct() {
        $this->depositModel = new Deposit();
        $this->withdrawalModel = new Withdrawal();
    }

    /**
     * 获取资金报告统计
     * 与下方交易列表使用相同数据源 vAllTransactions、相同日期字段 requestedAt，保证统计与列表一致
     * GET /api/funding-reports/statistics
     */
    public function statistics() {
        $this->requireAdmin();

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_fundingreport');
        if ($scope['scope'] === 'none') {
            Response::success([
                'summary' => [
                    'totalDeposits' => 0,
                    'depositCount' => 0,
                    'totalWithdrawals' => 0,
                    'withdrawalCount' => 0,
                    'totalInternalTransfer' => 0,
                    'internalTransferCount' => 0,
                    'netFlow' => 0
                ]
            ]);
            return;
        }

        $startDate = $_GET['startDate'] ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $_GET['endDate'] ?? date('Y-m-d');

        $db = Database::getInstance();
        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate
        ];
        $salesRestrictSql = '';
        if ($scope['scope'] === 'own') {
            $salesRestrictSql = ' AND userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)';
            $params['restrict_to_sales_id'] = (int)$scope['restrict_to_sales_id'];
        }

        // 与列表一致：基于 vAllTransactions，按 requestedAt 筛选
        $sql = "SELECT
                    transactionType,
                    COALESCE(SUM(amount), 0) AS totalAmount,
                    COUNT(*) AS transactionCount
                FROM vAllTransactions
                WHERE DATE(requestedAt) >= :startDate AND DATE(requestedAt) <= :endDate{$salesRestrictSql}
                GROUP BY transactionType";

        $rows = $db->fetchAll($sql, $params);

        $totalDeposits = 0;
        $depositCount = 0;
        $totalWithdrawals = 0;
        $withdrawalCount = 0;
        $totalInternalTransfer = 0;
        $internalTransferCount = 0;

        foreach ($rows as $row) {
            $type = $row['transactionType'] ?? '';
            $amount = (float)($row['totalAmount'] ?? 0);
            $count = (int)($row['transactionCount'] ?? 0);
            if ($type === 'deposit') {
                $totalDeposits = $amount;
                $depositCount = $count;
            } elseif ($type === 'withdrawal') {
                $totalWithdrawals = $amount;
                $withdrawalCount = $count;
            } elseif ($type === 'internal_transfer') {
                $totalInternalTransfer = $amount;
                $internalTransferCount = $count;
            }
        }

        $netFlow = $totalDeposits - $totalWithdrawals;

        Response::success([
            'summary' => [
                'totalDeposits' => $totalDeposits,
                'depositCount' => $depositCount,
                'totalWithdrawals' => $totalWithdrawals,
                'withdrawalCount' => $withdrawalCount,
                'totalInternalTransfer' => $totalInternalTransfer,
                'internalTransferCount' => $internalTransferCount,
                'netFlow' => $netFlow
            ]
        ]);
    }

    /**
     * 获取所有交易（存款+提款）
     * GET /api/funding-reports/transactions
     */
    public function getAllTransactions() {
        $this->requireAdmin();

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_fundingreport');

        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 10;
        if ($scope['scope'] === 'none') {
            Response::paginated([], 0, $page, $perPage);
            return;
        }

        $startDate = $_GET['startDate'] ?? null;
        $endDate = $_GET['endDate'] ?? null;
        $transactionType = $_GET['type'] ?? null; // 'deposit', 'withdrawal', or 'internal_transfer'

        $offset = ($page - 1) * $perPage;

        // 使用视图查询
        $sql = "SELECT * FROM vAllTransactions WHERE 1=1";
        $params = [];

        if ($scope['scope'] === 'own') {
            $sql .= " AND userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)";
            $params['restrict_to_sales_id'] = (int)$scope['restrict_to_sales_id'];
        }

        if ($startDate) {
            $sql .= " AND DATE(requestedAt) >= :startDate";
            $params['startDate'] = $startDate;
        }

        if ($endDate) {
            $sql .= " AND DATE(requestedAt) <= :endDate";
            $params['endDate'] = $endDate;
        }

        if ($transactionType) {
            $sql .= " AND transactionType = :transactionType";
            $params['transactionType'] = $transactionType;
        }

        $sql .= " ORDER BY requestedAt DESC";

        // 获取总数
        $db = Database::getInstance();
        $countSql = str_replace('SELECT *', 'SELECT COUNT(*) as count', $sql);
        $countSql = substr($countSql, 0, strpos($countSql, 'ORDER BY'));
        $countResult = $db->fetchOne($countSql, $params);
        $total = $countResult['count'] ?? 0;

        // 分页
        $perPage = max(1, (int)$perPage);
        $offset = max(0, (int)$offset);
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";

        $transactions = $db->fetchAll($sql, $params);

        Response::paginated($transactions, $total, $page, $perPage);
    }

    /**
     * POST /api/funding-reports/export
     */
    public function exportReport() {
        try {
            require_once __DIR__ . '/../services/FundingReportExportService.php';

            $admin = $this->requireAdmin();
            $adminUserId = (int)($admin['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }

            $active = FundingReportExportService::getActiveForAdmin($adminUserId);
            $activeStatus = (string)($active['status'] ?? '');
            if (in_array($activeStatus, ['queued', 'running', 'cancelling'], true)) {
                Response::error('Export already in progress', 409, $this->exportProgressPayload($active));
            }
            if ($activeStatus === 'done') {
                FundingReportExportService::clearActive($adminUserId);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $items = $this->sanitizeSelectedExportItems($input['items'] ?? null);
            if (!$items) {
                Response::error('No rows selected', 400);
                return;
            }

            $scope = AdminSalesPermission::getClientDataScopeForPage('page_fundingreport');
            $query = [
                'scope' => [
                    'scope' => (string)($scope['scope'] ?? 'none'),
                    'restrict_to_sales_id' => (int)($scope['restrict_to_sales_id'] ?? 0),
                ],
            ];

            $jobId = str_replace('.', '', uniqid('afr_', true));
            $fileName = 'funding_report_' . date('Y-m-d') . '.csv';
            FundingReportExportService::ensureExportDir();
            FundingReportExportService::writeProgress($jobId, [
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
            FundingReportExportService::writeActive($adminUserId, $jobId);

            try {
                FundingReportExportService::writeSelectedItems($jobId, $items);
            } catch (Exception $e) {
                FundingReportExportService::writeProgress($jobId, [
                    'adminUserId' => $adminUserId,
                    'status' => 'error',
                    'cancelRequested' => false,
                    'percent' => 0,
                    'message' => $e->getMessage(),
                    'file' => null,
                ]);
                FundingReportExportService::clearActive($adminUserId);
                Response::error('Failed to store selected rows: ' . $e->getMessage(), 500);
            }

            $payload = [
                'type' => 'export_admin_funding_report',
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
                FundingReportExportService::clearSelectedItems($jobId);
                FundingReportExportService::writeProgress($jobId, [
                    'adminUserId' => $adminUserId,
                    'status' => 'error',
                    'cancelRequested' => false,
                    'percent' => 0,
                    'message' => $e->getMessage(),
                    'file' => null,
                ]);
                FundingReportExportService::clearActive($adminUserId);
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
            require_once __DIR__ . '/../services/FundingReportExportService.php';
            $admin = $this->requireAdmin();
            $adminUserId = (int)($admin['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $progress = FundingReportExportService::getActiveForAdmin($adminUserId);
            Response::success($this->exportProgressPayload($progress));
        } catch (Exception $e) {
            Response::error('Failed to fetch export status: ' . $e->getMessage(), 500);
        }
    }

    public function exportStatus() {
        try {
            require_once __DIR__ . '/../services/FundingReportExportService.php';
            $admin = $this->requireAdmin();
            $adminUserId = (int)($admin['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $jobId = isset($_GET['jobId']) ? trim((string)$_GET['jobId']) : '';
            if ($jobId === '' || !$this->isSafeJobId($jobId)) {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = FundingReportExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['adminUserId'] ?? 0) !== $adminUserId) {
                Response::notFound('Export job not found');
            }
            require_once __DIR__ . '/../services/ExportJobTimeoutReaper.php';
            $progress = ExportJobTimeoutReaper::reapIfStale(
                $jobId,
                $progress,
                [FundingReportExportService::class, 'writeProgress'],
                [FundingReportExportService::class, 'clearActive']
            );
            Response::success($this->exportProgressPayload($progress));
        } catch (Exception $e) {
            Response::error('Failed to fetch export status: ' . $e->getMessage(), 500);
        }
    }

    public function exportCancel() {
        try {
            require_once __DIR__ . '/../services/FundingReportExportService.php';
            $admin = $this->requireAdmin();
            $adminUserId = (int)($admin['userId'] ?? 0);
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
            $progress = FundingReportExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['adminUserId'] ?? 0) !== $adminUserId) {
                Response::notFound('Export job not found');
            }
            $status = (string)($progress['status'] ?? '');
            if ($status === 'cancelled') {
                Response::success($this->exportProgressPayload($progress), 'Already cancelled');
            }
            if ($status === 'error') {
                FundingReportExportService::clearActive($adminUserId);
                Response::success($this->exportProgressPayload($progress), 'Export already failed');
            }
            FundingReportExportService::requestCancel($jobId);
            $updated = FundingReportExportService::readProgress($jobId);
            Response::success($this->exportProgressPayload($updated), 'Cancel requested');
        } catch (Exception $e) {
            Response::error('Failed to cancel export: ' . $e->getMessage(), 500);
        }
    }

    public function exportDownload() {
        try {
            require_once __DIR__ . '/../services/FundingReportExportService.php';
            $admin = $this->requireAdmin();
            $adminUserId = (int)($admin['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $jobId = isset($_GET['jobId']) ? trim((string)$_GET['jobId']) : '';
            if ($jobId === '' || !$this->isSafeJobId($jobId)) {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = FundingReportExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['adminUserId'] ?? 0) !== $adminUserId) {
                Response::notFound('Export job not found');
            }
            if (($progress['status'] ?? '') !== 'done') {
                Response::error('Export is not ready', 400);
            }
            $csvFile = FundingReportExportService::csvPath($jobId);
            if (!file_exists($csvFile) || !is_readable($csvFile)) {
                Response::error('Export file missing', 404);
            }

            FundingReportExportService::clearActive($adminUserId);

            $filename = trim((string)($progress['fileName'] ?? ''));
            if ($filename === '' || !preg_match('/^[A-Za-z0-9._-]+\.csv$/', $filename)) {
                $filename = 'funding_report_' . date('Y-m-d') . '.csv';
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

    private function sanitizeSelectedExportItems($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $allowed = [
            'deposit' => true,
            'withdrawal' => true,
            'internal_transfer' => true,
        ];
        $items = [];
        $seen = [];

        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = trim((string)($item['type'] ?? ''));
            $id = (int)($item['id'] ?? 0);
            if ($id <= 0 || !isset($allowed[$type])) {
                continue;
            }
            $key = $type . '-' . $id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $items[] = [
                'type' => $type,
                'id' => $id,
            ];
            if (count($items) >= 500) {
                break;
            }
        }

        return $items;
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

    /**
     * 要求管理员认证
     */
    private function requireAdmin() {
        $payload = JWT::getPayload();

        if (!$payload || ($payload['type'] ?? '') !== 'admin') {
            Response::forbidden('Admin authentication required');
        }

        $userId = $payload['userId'] ?? null;
        if (!$userId) {
            Response::unauthorized('Invalid token payload');
        }

        return [
            'userId' => $userId
        ];
    }
}
