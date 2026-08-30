<?php
/**
 * Commission Order 控制器
 * 列表与状态操作：Approve、Complete、Cancel
 */

require_once __DIR__ . '/../models/IbCommissionOrder.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/OperationLog/IbCommissionOperationLog.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';

class CommissionOrderController {
    private $model;

    public function __construct() {
        $this->model = new IbCommissionOrder();
    }

    private function readRequestBody() {
        $data = json_decode(file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }

    /**
     * 列表（分页、搜索、按状态筛选）
     * GET /api/commission-orders?page=1&per_page=10&search=&status=
     */
    public function index() {
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? $_GET['per_page'] : 10;
        if ($perPage === 'all' || $perPage === '') {
            $perPage = 0;
        } else {
            $perPage = (int) $perPage;
        }
        $filters = [];
        if (!empty(trim((string)($_GET['search'] ?? '')))) {
            $filters['search'] = trim($_GET['search']);
        }
        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $filters['status'] = $_GET['status'];
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_ib_commission_order');
        if ($scope['scope'] === 'none') {
            Response::success([
                'items' => [],
                'pagination' => [
                    'total' => 0,
                    'per_page' => $perPage > 0 ? (int) $perPage : 0,
                    'page' => max(1, (int) $page),
                    'total_pages' => 0,
                    'has_more' => false
                ],
                'summary' => [
                    'total' => 0,
                    'pending' => 0,
                    'approved' => 0,
                    'completed' => 0,
                    'cancelled' => 0
                ]
            ], 'Success');
            return;
        }
        if ($scope['scope'] === 'own') {
            $filters['restrict_to_sales_id'] = $scope['restrict_to_sales_id'];
        }

        $result = $this->model->getList($page, $perPage > 0 ? $perPage : 99999, $filters);
        $perPageVal = $perPage > 0 ? $perPage : max(1, $result['total']);
        $summary = $this->model->getStats($filters);

        Response::success([
            'items' => $result['items'],
            'pagination' => [
                'total' => (int) $result['total'],
                'per_page' => (int) $perPageVal,
                'page' => (int) $result['page'],
                'total_pages' => (int) ceil($result['total'] / $perPageVal),
                'has_more' => ($result['page'] * $perPageVal) < $result['total']
            ],
            'summary' => $summary
        ], 'Success');
    }

    /**
     * Approve：pending -> approved，写入 statusDate
     * POST /api/commission-orders/{id}/approve
     */
    public function approve($id) {
        $data = $this->readRequestBody();
        $context = IbCommissionOperationLog::loadOrderContext($id);
        $clientId = IbCommissionOperationLog::resolveClientIdFromContext($context);

        if (!$context) {
            IbCommissionOperationLog::logFailure(
                $data,
                'approve',
                null,
                'commissionOrderApproveFailure',
                'Commission order not found'
            );
            Response::notFound('Commission order not found');
        }
        if (($context['status'] ?? '') !== IbCommissionOrder::STATUS_PENDING) {
            IbCommissionOperationLog::logFailure(
                $data,
                'approve',
                $clientId,
                'commissionOrderApproveFailure',
                'Only pending orders can be approved'
            );
            Response::error('Only pending orders can be approved', 400);
        }

        $this->model->setApproved($id);
        IbCommissionOperationLog::logApproveSuccess($data, $context);
        Response::success(['id' => (int) $id], 'Approved successfully');
    }

    /**
     * Complete：approved -> completed，写入 payoutDate
     * POST /api/commission-orders/{id}/complete
     */
    public function complete($id) {
        $data = $this->readRequestBody();
        $context = IbCommissionOperationLog::loadOrderContext($id);
        $clientId = IbCommissionOperationLog::resolveClientIdFromContext($context);

        if (!$context) {
            IbCommissionOperationLog::logFailure(
                $data,
                'approve',
                null,
                'commissionOrderCompleteFailure',
                'Commission order not found'
            );
            Response::notFound('Commission order not found');
        }
        if (($context['status'] ?? '') !== IbCommissionOrder::STATUS_APPROVED) {
            IbCommissionOperationLog::logFailure(
                $data,
                'approve',
                $clientId,
                'commissionOrderCompleteFailure',
                'Only approved orders can be completed'
            );
            Response::error('Only approved orders can be completed', 400);
        }

        $this->model->setCompleted($id);
        IbCommissionOperationLog::logCompleteSuccess($data, $context);
        Response::success(['id' => (int) $id], 'Completed successfully');
    }

    /**
     * Cancel：pending 或 approved -> cancelled，写入 cancelDate
     * POST /api/commission-orders/{id}/cancel
     */
    public function cancel($id) {
        $data = $this->readRequestBody();
        $context = IbCommissionOperationLog::loadOrderContext($id);
        $clientId = IbCommissionOperationLog::resolveClientIdFromContext($context);

        if (!$context) {
            IbCommissionOperationLog::logFailure(
                $data,
                'reject',
                null,
                'commissionOrderCancelFailure',
                'Commission order not found'
            );
            Response::notFound('Commission order not found');
        }
        $status = $context['status'] ?? '';
        if ($status !== IbCommissionOrder::STATUS_PENDING && $status !== IbCommissionOrder::STATUS_APPROVED) {
            IbCommissionOperationLog::logFailure(
                $data,
                'reject',
                $clientId,
                'commissionOrderCancelFailure',
                'Only pending or approved orders can be cancelled'
            );
            Response::error('Only pending or approved orders can be cancelled', 400);
        }

        $this->model->setCancelled($id);
        IbCommissionOperationLog::logCancelSuccess($data, $context);
        Response::success(['id' => (int) $id], 'Cancelled successfully');
    }
}
