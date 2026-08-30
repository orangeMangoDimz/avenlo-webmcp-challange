<?php
/**
 * Admin application log search (Phase 2).
 * GET /api/application-logs
 */

require_once __DIR__ . '/../models/ApplicationLog.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

class ApplicationLogController {
    /** @var ApplicationLog */
    private $logModel;

    public function __construct() {
        $this->logModel = new ApplicationLog();
    }

    /**
     * GET /api/application-logs
     */
    public function index() {
        AuthMiddleware::authenticate();
        AuthMiddleware::requireAdmin();
        AuthMiddleware::checkPermission('application_logs.view');

        $filters = [
            'level' => $_GET['level'] ?? '',
            'service' => $_GET['service'] ?? '',
            'environment' => $_GET['environment'] ?? '',
            'requestId' => $_GET['requestId'] ?? '',
            'startDate' => $_GET['startDate'] ?? '',
            'endDate' => $_GET['endDate'] ?? '',
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = (int) ($_GET['per_page'] ?? $_GET['perPage'] ?? 10);
        if ($perPage < 1) {
            $perPage = 10;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $startDate = trim((string) $filters['startDate']);
        if ($startDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            Response::error('Invalid startDate; expected YYYY-MM-DD', 400);
        }

        $endDate = trim((string) $filters['endDate']);
        if ($endDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            Response::error('Invalid endDate; expected YYYY-MM-DD', 400);
        }

        $level = strtoupper(trim((string) $filters['level']));
        if ($level !== '' && !in_array($level, ['ERROR', 'WARNING', 'INFO'], true)) {
            Response::error('Invalid level; expected ERROR, WARNING, or INFO', 400);
        }

        $result = $this->logModel->findByFilters($filters, $page, $perPage);
        Response::paginated(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }
}
