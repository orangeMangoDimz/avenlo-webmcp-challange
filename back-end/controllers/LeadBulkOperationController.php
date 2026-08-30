<?php
/**
 * Lead批量操作日志控制器
 */

require_once __DIR__ . '/../models/LeadBulkOperation.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class LeadBulkOperationController {
    private $bulkOpModel;

    public function __construct() {
        $this->bulkOpModel = new LeadBulkOperation();
    }

    /**
     * 获取批量操作历史（分页）
     * GET /api/lead-bulk-operations
     */
    public function index() {
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 20;
        $operationType = $_GET['operation_type'] ?? null;

        $result = $this->bulkOpModel->getOperationHistory($page, $perPage, $operationType);

        // 解析 JSON 数据
        foreach ($result['items'] as &$item) {
            $item['leadIds'] = json_decode($item['leadIds'], true);
            $item['operationData'] = json_decode($item['operationData'], true);
        }

        Response::paginated(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * 获取单个批量操作详情
     * GET /api/lead-bulk-operations/{id}
     */
    public function show($id) {
        $operation = $this->bulkOpModel->getOperationDetails($id);

        if (!$operation) {
            Response::notFound('Bulk operation not found');
        }

        Response::success($operation);
    }

    /**
     * 获取特定管理员的批量操作历史
     * GET /api/lead-bulk-operations/admin/{adminId}
     */
    public function getOperationsByAdmin($adminId) {
        $limit = $_GET['limit'] ?? 20;

        $operations = $this->bulkOpModel->getOperationsByAdmin($adminId, $limit);

        // 解析 JSON 数据
        foreach ($operations as &$item) {
            $item['leadIds'] = json_decode($item['leadIds'], true);
            $item['operationData'] = json_decode($item['operationData'], true);
        }

        Response::success($operations);
    }

    /**
     * 获取批量操作类型统计
     * GET /api/lead-bulk-operations/stats
     */
    public function getStats() {
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        $stats = $this->bulkOpModel->getOperationTypeStats($startDate, $endDate);

        Response::success($stats);
    }

    /**
     * 获取最近的批量操作
     * GET /api/lead-bulk-operations/recent
     */
    public function getRecent() {
        $limit = $_GET['limit'] ?? 10;

        $operations = $this->bulkOpModel->getRecentOperations($limit);

        // 解析 JSON 数据
        foreach ($operations as &$item) {
            $item['leadIds'] = json_decode($item['leadIds'], true);
            $item['operationData'] = json_decode($item['operationData'], true);
        }

        Response::success($operations);
    }

    /**
     * 获取操作趋势（按日期分组）
     * GET /api/lead-bulk-operations/trends
     */
    public function getTrends() {
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $operationType = $_GET['operation_type'] ?? null;

        $conditions = [];
        $params = [];

        $conditions[] = "DATE(createdAt) BETWEEN :startDate AND :endDate";
        $params['startDate'] = $startDate;
        $params['endDate'] = $endDate;

        if ($operationType) {
            $conditions[] = "operationType = :operationType";
            $params['operationType'] = $operationType;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        $sql = "SELECT
                    DATE(createdAt) as operationDate,
                    operationType,
                    COUNT(*) as operationCount,
                    SUM(totalLeads) as totalLeadsAffected
                FROM leadBulkOperations
                {$whereClause}
                GROUP BY DATE(createdAt), operationType
                ORDER BY operationDate DESC, operationType";

        $trends = $this->bulkOpModel->db->fetchAll($sql, $params);

        Response::success($trends);
    }

    /**
     * 获取管理员操作排行
     * GET /api/lead-bulk-operations/admin-rankings
     */
    public function getAdminRankings() {
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;
        $limit = $_GET['limit'] ?? 10;

        $conditions = [];
        $params = [];

        if ($startDate) {
            $conditions[] = "lbo.createdAt >= :startDate";
            $params['startDate'] = $startDate;
        }

        if ($endDate) {
            $conditions[] = "lbo.createdAt <= :endDate";
            $params['endDate'] = $endDate;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT
                    lbo.performedBy,
                    au.fullName as adminName,
                    COUNT(*) as operationCount,
                    SUM(lbo.totalLeads) as totalLeadsAffected
                FROM leadBulkOperations lbo
                LEFT JOIN adminUsers au ON lbo.performedBy = au.id
                {$whereClause}
                GROUP BY lbo.performedBy, au.fullName
                ORDER BY operationCount DESC
                LIMIT {$limit}";

        $rankings = $this->bulkOpModel->db->fetchAll($sql, $params);

        Response::success($rankings);
    }

    /**
     * 删除批量操作记录（谨慎使用）
     * DELETE /api/lead-bulk-operations/{id}
     */
    public function delete($id) {
        $operation = $this->bulkOpModel->findById($id);

        if (!$operation) {
            Response::notFound('Bulk operation not found');
        }

        $this->bulkOpModel->delete($id);

        Response::success(null, 'Bulk operation record deleted successfully');
    }

    /**
     * 获取特定Lead涉及的批量操作
     * GET /api/lead-bulk-operations/lead/{leadId}
     */
    public function getOperationsByLead($leadId) {
        $sql = "SELECT lbo.*, au.fullName as performedByName
                FROM leadBulkOperations lbo
                LEFT JOIN adminUsers au ON lbo.performedBy = au.id
                WHERE JSON_CONTAINS(lbo.leadIds, :leadId, '$')
                ORDER BY lbo.createdAt DESC";

        $operations = $this->bulkOpModel->db->fetchAll($sql, [
            'leadId' => json_encode((int)$leadId)
        ]);

        // 解析 JSON 数据
        foreach ($operations as &$item) {
            $item['leadIds'] = json_decode($item['leadIds'], true);
            $item['operationData'] = json_decode($item['operationData'], true);
        }

        Response::success($operations);
    }

    /**
     * 导出批量操作记录
     * POST /api/lead-bulk-operations/export
     */
    public function export() {
        $data = json_decode(file_get_contents('php://input'), true);

        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;
        $operationType = $data['operation_type'] ?? null;

        $conditions = [];
        $params = [];

        if ($startDate) {
            $conditions[] = "lbo.createdAt >= :startDate";
            $params['startDate'] = $startDate;
        }

        if ($endDate) {
            $conditions[] = "lbo.createdAt <= :endDate";
            $params['endDate'] = $endDate;
        }

        if ($operationType) {
            $conditions[] = "lbo.operationType = :operationType";
            $params['operationType'] = $operationType;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT
                    lbo.*,
                    au.fullName as performedByName,
                    au.email as performedByEmail
                FROM leadBulkOperations lbo
                LEFT JOIN adminUsers au ON lbo.performedBy = au.id
                {$whereClause}
                ORDER BY lbo.createdAt DESC";

        $operations = $this->bulkOpModel->db->fetchAll($sql, $params);

        // 解析 JSON 数据
        foreach ($operations as &$item) {
            $item['leadIds'] = json_decode($item['leadIds'], true);
            $item['operationData'] = json_decode($item['operationData'], true);
        }

        Response::success([
            'operations' => $operations,
            'count' => count($operations),
            'exportDate' => date('Y-m-d H:i:s')
        ]);
    }
}
