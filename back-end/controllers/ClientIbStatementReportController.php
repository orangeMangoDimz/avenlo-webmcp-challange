<?php

require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/IbStatementReportController.php';

class ClientIbStatementReportController
{
    private $ibPartnerModel;

    public function __construct()
    {
        $this->ibPartnerModel = new IbPartner();
    }

    public function statement()
    {
        $currentUserId = (int) $this->getCurrentUserId();
        $ibPartner = $this->getReportIbPartner($currentUserId);
        $startRaw = $_GET['startDate'] ?? $_GET['start_date'] ?? '';
        $endRaw = $_GET['endDate'] ?? $_GET['end_date'] ?? '';
        $startServer = $this->convertDateToServerTimezone($startRaw);
        $endServer = $this->convertEndDateToServerTimezone($endRaw);
        if ($startServer === null || $endServer === null) {
            Response::validationError(['startDate' => 'A valid date range is required']);
            return;
        }

        $engine = new IbStatementReportController();
        $payload = $engine->buildStatement((int) $ibPartner['id'], $startServer, $endServer, ['scope' => 'all']);
        if ($payload === null) {
            Response::notFound('IB partner not found');
            return;
        }
        Response::success($payload);
    }

    public function exportReport()
    {
        require_once __DIR__ . '/../services/ClientIbStatementReportExportService.php';

        $currentUserId = (int) $this->getCurrentUserId();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $ibPartner = $this->getReportIbPartner($currentUserId, (int) ($input['ibPartnerId'] ?? 0));
        $startRaw = $input['startDate'] ?? $input['start_date'] ?? '';
        $endRaw = $input['endDate'] ?? $input['end_date'] ?? '';
        $startServer = $this->convertDateToServerTimezone($startRaw);
        $endServer = $this->convertEndDateToServerTimezone($endRaw);
        if ($startServer === null || $endServer === null) {
            Response::validationError(['ibPartnerId' => 'A valid IB partner and date range are required']);
            return;
        }

        $active = ClientIbStatementReportExportService::getActiveForUser($currentUserId);
        $activeStatus = (string) ($active['status'] ?? '');
        if (in_array($activeStatus, ['queued', 'running', 'cancelling'], true)) {
            Response::error('Export already in progress', 409, $this->exportProgressPayload($active));
            return;
        }
        if ($activeStatus === 'done') {
            ClientIbStatementReportExportService::clearActive($currentUserId);
        }

        $format = strtolower(trim((string) ($input['format'] ?? 'csv')));
        if ($format !== 'excel') {
            $format = 'csv';
        }
        $ext = $format === 'excel' ? 'xls' : 'csv';
        $fileName = 'ib_statement_' . date('Y-m-d') . '.' . $ext;
        $jobId = str_replace('.', '', uniqid('cis_', true));

        ClientIbStatementReportExportService::ensureExportDir();
        ClientIbStatementReportExportService::writeProgress($jobId, [
            'clientUserId' => $currentUserId,
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
        ClientIbStatementReportExportService::writeActive($currentUserId, $jobId);

        $payload = [
            'type' => 'export_client_ib_statement_report',
            'jobId' => $jobId,
            'clientUserId' => $currentUserId,
            'userId' => $currentUserId,
            'userType' => 'client',
            'query' => [
                'ibPartnerId' => (int) $ibPartner['id'],
                'startDate' => $startServer,
                'endDate' => $endServer,
            ],
            'requestedAt' => time(),
        ];

        try {
            $this->dispatchSwooleTask($payload);
        } catch (Exception $e) {
            ClientIbStatementReportExportService::writeProgress($jobId, [
                'clientUserId' => $currentUserId,
                'status' => 'error',
                'cancelRequested' => false,
                'percent' => 0,
                'message' => $e->getMessage(),
                'file' => null,
            ]);
            ClientIbStatementReportExportService::clearActive($currentUserId);
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
        require_once __DIR__ . '/../services/ClientIbStatementReportExportService.php';
        $currentUserId = (int) $this->getCurrentUserId();
        $progress = ClientIbStatementReportExportService::getActiveForUser($currentUserId);
        Response::success($this->exportProgressPayload($progress));
    }

    public function exportStatus()
    {
        require_once __DIR__ . '/../services/ClientIbStatementReportExportService.php';
        $currentUserId = (int) $this->getCurrentUserId();
        $jobId = isset($_GET['jobId']) ? trim((string) $_GET['jobId']) : '';
        if (!$this->isSafeJobId($jobId)) {
            Response::validationError(['jobId' => 'jobId is required']);
            return;
        }
        $progress = ClientIbStatementReportExportService::readProgress($jobId);
        if ($progress === null || (int) ($progress['clientUserId'] ?? 0) !== $currentUserId) {
            Response::notFound('Export job not found');
            return;
        }
        require_once __DIR__ . '/../services/ExportJobTimeoutReaper.php';
        $progress = ExportJobTimeoutReaper::reapIfStale(
            $jobId,
            $progress,
            [ClientIbStatementReportExportService::class, 'writeProgress'],
            [ClientIbStatementReportExportService::class, 'clearActive']
        );
        Response::success($this->exportProgressPayload($progress));
    }

    public function exportCancel()
    {
        require_once __DIR__ . '/../services/ClientIbStatementReportExportService.php';
        $currentUserId = (int) $this->getCurrentUserId();
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
        $progress = ClientIbStatementReportExportService::readProgress($jobId);
        if ($progress === null || (int) ($progress['clientUserId'] ?? 0) !== $currentUserId) {
            Response::notFound('Export job not found');
            return;
        }
        $status = (string) ($progress['status'] ?? '');
        if ($status === 'cancelled') {
            Response::success($this->exportProgressPayload($progress), 'Already cancelled');
            return;
        }
        if ($status === 'error') {
            ClientIbStatementReportExportService::clearActive($currentUserId);
            Response::success($this->exportProgressPayload($progress), 'Export already failed');
            return;
        }
        ClientIbStatementReportExportService::requestCancel($jobId);
        $updated = ClientIbStatementReportExportService::readProgress($jobId);
        Response::success($this->exportProgressPayload($updated), 'Cancel requested');
    }

    public function exportDownload()
    {
        require_once __DIR__ . '/../services/ClientIbStatementReportExportService.php';
        $currentUserId = (int) $this->getCurrentUserId();
        $jobId = isset($_GET['jobId']) ? trim((string) $_GET['jobId']) : '';
        if (!$this->isSafeJobId($jobId)) {
            Response::validationError(['jobId' => 'jobId is required']);
            return;
        }
        $progress = ClientIbStatementReportExportService::readProgress($jobId);
        if ($progress === null || (int) ($progress['clientUserId'] ?? 0) !== $currentUserId) {
            Response::notFound('Export job not found');
            return;
        }
        if (($progress['status'] ?? '') !== 'done') {
            Response::error('Export is not ready', 400);
            return;
        }
        $csvFile = ClientIbStatementReportExportService::csvPath($jobId);
        if (!file_exists($csvFile) || !is_readable($csvFile)) {
            Response::error('Export file missing', 404);
            return;
        }
        ClientIbStatementReportExportService::clearActive($currentUserId);
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

    private function getCurrentUserId()
    {
        $userId = ClientAuthContext::getCurrentClientUserId();
        if ($userId !== null) {
            return $userId;
        }
        $token = JWT::getTokenFromHeader();
        if (!$token) {
            Response::error('Unauthorized - No token provided', 401);
        }
        try {
            $payload = JWT::decode($token);
            if (!isset($payload['userId'])) {
                Response::error('Invalid token - No user ID', 401);
            }
            return $payload['userId'];
        } catch (Exception $e) {
            Response::error('Invalid token: ' . $e->getMessage(), 401);
        }
    }

    private function getReportIbPartner($currentUserId, $requestedIbPartnerId = null)
    {
        if ($requestedIbPartnerId === null) {
            $requestedIbPartnerId = (int) ($_GET['ibPartnerId'] ?? 0);
        }
        $requestedIbPartnerId = (int) $requestedIbPartnerId;
        $ibPartners = $this->ibPartnerModel->getAllByClientId($currentUserId);
        $approvedPartners = array_values(array_filter($ibPartners, function ($ibPartner) {
            return ($ibPartner['status'] ?? '') === IbPartner::STATUS_APPROVED;
        }));

        if (empty($approvedPartners)) {
            Response::error('IB partner not found', 404);
        }

        $selectedId = (int) ($approvedPartners[0]['id'] ?? 0);
        if ($requestedIbPartnerId > 0) {
            $selectedId = 0;
            foreach ($approvedPartners as $ibPartner) {
                if ((int) ($ibPartner['id'] ?? 0) === $requestedIbPartnerId) {
                    $selectedId = $requestedIbPartnerId;
                    break;
                }
            }
            if ($selectedId <= 0) {
                Response::error('IB partner not found', 404);
            }
        }

        $ibPartner = $this->ibPartnerModel->findById($selectedId);
        if (!$ibPartner || (int) ($ibPartner['userId'] ?? 0) !== (int) $currentUserId || ($ibPartner['status'] ?? '') !== IbPartner::STATUS_APPROVED) {
            Response::error('IB partner not found', 404);
        }

        return $ibPartner;
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
