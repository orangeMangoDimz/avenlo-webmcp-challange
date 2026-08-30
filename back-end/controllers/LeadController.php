<?php
/**
 * Lead管理控制器
 */

require_once __DIR__ . '/../models/Lead.php';
require_once __DIR__ . '/../models/LeadStatusHistory.php';
require_once __DIR__ . '/../models/LeadTagAssignment.php';
require_once __DIR__ . '/../models/LeadActivityLog.php';
require_once __DIR__ . '/../models/LeadEditHistory.php';
require_once __DIR__ . '/../models/LeadKycStatus.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogPages.php';

class LeadController {
    private $leadModel;
    private $statusHistoryModel;
    private $tagAssignmentModel;
    private $activityLogModel;
    private $editHistoryModel;
    private $kycStatusModel;

    public function __construct() {
        $this->leadModel = new Lead();
        $this->statusHistoryModel = new LeadStatusHistory();
        $this->tagAssignmentModel = new LeadTagAssignment();
        $this->activityLogModel = new LeadActivityLog();
        $this->editHistoryModel = new LeadEditHistory();
        $this->kycStatusModel = new LeadKycStatus();
    }

    /**
     * 构建后台“View as client”完整跳转地址，避免由前端拼接路由
     */
    private function buildPreviewUrl(string $token): string {
        $appConfig = require __DIR__ . '/../config/app.php';
        $clientFrontendUrl = rtrim((string) ($appConfig['client_frontend_url'] ?? 'http://localhost:5173'), '/');

        return $clientFrontendUrl . '/#/preview?token=' . rawurlencode($token);
    }

    /**
     * 获取Leads列表
     * GET /api/leads
     */
    public function index() {
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 10;
        $search = $_GET['search'] ?? null;

        // 构建筛选条件
        $filters = [];

        if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        if (isset($_GET['country'])) {
            $filters['country'] = $_GET['country'];
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_leads');
        if ($scope['scope'] === 'none') {
            Response::paginated([], 0, (int)$page, (int)$perPage);
            return;
        }
        if ($scope['scope'] === 'own') {
            $filters['sales_id'] = $scope['restrict_to_sales_id'];
        }

        // 如果有搜索关键词
        if ($search) {
            $result = $this->leadModel->searchLeads($search, $page, $perPage, isset($filters['sales_id']) ? $filters['sales_id'] : null);
        } else {
            $result = $this->leadModel->getLeads($page, $perPage, $filters);
        }

        // 为每个lead添加标签和文档信息
        foreach ($result['items'] as &$lead) {
            $lead['tags'] = $this->leadModel->getLeadTags($lead['leadId']);
            $lead['documents'] = $this->leadModel->getLeadDocuments($lead['leadId']);
        }

        Response::paginated(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * 获取单个Lead
     * GET /api/leads/{id}
     */
    public function show($id) {
        $lead = $this->leadModel->getLeadSummary($id);

        if (!$lead) {
            Response::notFound('Lead not found');
        }

        // 获取完整信息
        $lead['tags'] = $this->leadModel->getLeadTags($id);
        $lead['documents'] = $this->leadModel->getLeadDocuments($id);
        $lead['activityLog'] = $this->leadModel->getLeadActivityLog($id, 10);
        $lead['kycStatus'] = $this->kycStatusModel->getLeadKycStatus($id);

        Response::success($lead);
    }

    /**
     * 更新Lead信息
     * PUT /api/leads/{id}
     */
    public function update($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        $subModule = OperationLogPages::resolveLogClient($data, OperationLogPages::subModuleKeyByAlias('page_leads'));
        $opLog = new AdminOperationLogWriter();

        // 验证Lead是否存在
        $lead = $this->leadModel->findById($id);
        if (!$lead) {
            $opLog->logClientProfileUpdate($subModule, 0, '', [], false, 'Lead not found');
            Response::notFound('Lead not found');
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        // 记录变更
        $changes = [];
        $updateData = [];

        $editableFields = ['firstName', 'lastName', 'email', 'phone', 'country', 'status'];

        foreach ($editableFields as $field) {
            if (isset($data[$field]) && $data[$field] != $lead[$field]) {
                $changes[$field] = [
                    'old' => $lead[$field],
                    'new' => $data[$field]
                ];
                $updateData[$field] = $data[$field];
            }
        }

        if (empty($updateData)) {
            $opLog->logClientProfileUpdate(
                $subModule,
                (int) $id,
                AdminOperationLogWriter::formatClientDisplayName($lead),
                [],
                false,
                'No changes detected'
            );
            Response::error('No changes detected', 400);
        }

        // 更新Lead
        $this->leadModel->update($id, $updateData);

        // 记录编辑历史
        $this->editHistoryModel->logBulkEdit($id, $changes, $adminId, $ipAddress);

        // 记录活动日志
        $this->activityLogModel->logActivity(
            $id,
            'info_updated',
            'Lead information updated: ' . implode(', ', array_keys($changes)),
            $adminId,
            ['changes' => $changes],
            $ipAddress
        );

        $opLog->logClientProfileUpdate(
            $subModule,
            $id,
            AdminOperationLogWriter::formatClientDisplayName($lead),
            $changes
        );

        // 获取更新后的Lead
        $updatedLead = $this->leadModel->getLeadSummary($id);
        $updatedLead['tags'] = $this->leadModel->getLeadTags($id);

        Response::success($updatedLead, 'Lead updated successfully');
    }

    /**
     * 更新Lead状态
     * POST /api/leads/{id}/status
     */
    public function updateStatus($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        Validator::make($data, [
            'status' => 'required|in:new,contacted,converted,inactive'
        ]);

        $lead = $this->leadModel->findById($id);
        if (!$lead) {
            Response::notFound('Lead not found');
        }

        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        $previousStatus = $lead['status'];
        $newStatus = $data['status'];
        $notes = $data['notes'] ?? null;

        // 更新状态
        $this->leadModel->update($id, ['status' => $newStatus]);

        // 记录状态历史
        $this->statusHistoryModel->logStatusChange(
            $id,
            $previousStatus,
            $newStatus,
            $adminId,
            $notes
        );

        // 记录活动日志
        $this->activityLogModel->logActivity(
            $id,
            'status_change',
            "Status changed from {$previousStatus} to {$newStatus}",
            $adminId,
            ['previousStatus' => $previousStatus, 'newStatus' => $newStatus],
            $ipAddress
        );

        Response::success([
            'leadId' => $id,
            'previousStatus' => $previousStatus,
            'newStatus' => $newStatus
        ], 'Status updated successfully');
    }

    /**
     * 获取Lead的活动日志
     * GET /api/leads/{id}/activities
     */
    public function getActivities($id) {
        $limit = $_GET['limit'] ?? 50;

        $activities = $this->activityLogModel->getLeadActivities($id, $limit);

        Response::success($activities);
    }

    /**
     * 获取Lead的编辑历史
     * GET /api/leads/{id}/edit-history
     */
    public function getEditHistory($id) {
        $limit = $_GET['limit'] ?? 50;

        $history = $this->editHistoryModel->getLeadEditHistory($id, $limit);

        Response::success($history);
    }

    /**
     * 获取Lead的状态历史
     * GET /api/leads/{id}/status-history
     */
    public function getStatusHistory($id) {
        $history = $this->statusHistoryModel->getLeadHistory($id);

        Response::success($history);
    }

    /**
     * 获取统计信息
     * GET /api/leads/statistics
     */
    public function statistics() {
        $leadsStats = $this->leadModel->getStatistics();
        $kycStats = $this->leadModel->getKycStatistics();

        Response::success([
            'leads' => $leadsStats,
            'kyc' => $kycStats
        ]);
    }

    /**
     * 导出Leads
     * POST /api/leads/export
     */
    public function export() {
        $data = json_decode(file_get_contents('php://input'), true);

        $leadIds = $data['leadIds'] ?? [];
        $format = $data['format'] ?? 'csv';

        if (empty($leadIds)) {
            Response::error('No leads selected for export', 400);
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        // 记录批量操作
        require_once __DIR__ . '/../models/LeadBulkOperation.php';
        $bulkOpModel = new LeadBulkOperation();
        $bulkOpModel->logBulkOperation(
            'bulk_export',
            $leadIds,
            ['format' => $format],
            $adminId,
            $ipAddress
        );

        // 实际导出逻辑会在这里实现
        // 这里只是返回成功消息
        Response::success([
            'message' => 'Export initiated',
            'leadCount' => count($leadIds),
            'format' => $format
        ]);
    }

    /**
     * 生成预览 Token（View as client）
     * POST /api/leads/{id}/preview-token
     * 允许权限：Leads / Client List / IB List 任一页的 View as client
     */
    public function createPreviewToken($id) {
        $payload = AuthMiddleware::getCurrentUser();
        if (!$payload || empty($payload['userId'])) {
            Response::unauthorized();
        }
        require_once __DIR__ . '/../models/AdminPermission.php';
        $permModel = new AdminPermission();
        $adminUserId = (int) $payload['userId'];
        $allowed = $permModel->userHasPermission($adminUserId, 'page_leads_view_as_client')
            || $permModel->userHasPermission($adminUserId, 'page_clientslist_view_as_client')
            || $permModel->userHasPermission($adminUserId, 'page_iblist_view_as_client');
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogClient($body, OperationLogPages::subModuleKeyByAlias('page_leads'));

        if (!$allowed) {
            (new AdminOperationLogWriter())->logClientViewAsClient(
                $subModule,
                0,
                '',
                false,
                'You do not have permission to perform this action'
            );
            Response::forbidden('You do not have permission to perform this action');
        }

        $lead = $this->leadModel->findById($id);
        if (!$lead) {
            (new AdminOperationLogWriter())->logClientViewAsClient($subModule, 0, '', false, 'Lead not found');
            Response::notFound('Lead not found');
        }

        // Lead 即 clientUsers 表，id 即为 clientUserId
        $clientUserId = (int) $id;

        $payload = AuthMiddleware::getCurrentUser();
        $adminUserId = $payload['userId'] ?? null;

        require_once __DIR__ . '/../models/AdminPreviewToken.php';
        $tokenModel = new AdminPreviewToken();
        $result = $tokenModel->createToken($clientUserId, $adminUserId);
        $result['previewUrl'] = $this->buildPreviewUrl($result['token']);

        (new AdminOperationLogWriter())->logClientViewAsClient(
            $subModule,
            $clientUserId,
            AdminOperationLogWriter::formatClientDisplayName($lead)
        );

        Response::success($result);
    }

    /**
     * 直接重置Lead密码
     * POST /api/leads/{id}/reset-password
     */
    public function resetPassword($id) {
        $lead = $this->leadModel->findById($id);
        if (!$lead) {
            Response::notFound('Lead not found');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        Validator::make($data, [
            'newPassword' => 'required|string|min:6'
        ]);

        $passwordHash = password_hash($data['newPassword'], PASSWORD_BCRYPT, ['cost' => 10]);
        $this->leadModel->update($id, ['passwordHash' => $passwordHash]);

        $payload = JWT::decode(JWT::getTokenFromHeader());
        $this->activityLogModel->logActivity(
            $id,
            'password_reset_by_admin',
            'Password directly reset by administrator',
            $payload['userId'] ?? null
        );

        Response::success(null, 'Password reset successfully');
    }

    /**
     * 发送Lead密码重置邮件
     * POST /api/leads/{id}/send-password-reset
     */
    public function sendPasswordReset($id) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogClient($data, OperationLogPages::subModuleKeyByAlias('page_leads'));

        $lead = $this->leadModel->findById($id);
        if (!$lead) {
            (new AdminOperationLogWriter())->logClientPasswordResetEmail(
                $subModule,
                0,
                '',
                true,
                false,
                'Lead not found'
            );
            Response::notFound('Lead not found');
        }
        $useHashMode = isset($data['useHashMode']) && $data['useHashMode'] === true;

        require_once __DIR__ . '/../models/ClientPasswordReset.php';
        require_once __DIR__ . '/../utils/EmailSender.php';

        $passwordResetModel = new ClientPasswordReset();
        $token = $passwordResetModel->createToken($lead['email']);

        $appConfig = require __DIR__ . '/../config/app.php';
        $clientFrontendUrl = $appConfig['client_frontend_url'] ?? 'http://localhost:5173';
        $hashPrefix = $useHashMode ? '#' : '';
        $resetLink = "{$clientFrontendUrl}{$hashPrefix}/client/reset-password?token={$token}";

        $userName = trim(($lead['firstName'] ?? '') . ' ' . ($lead['lastName'] ?? ''));
        if ($userName === '') {
            $userName = $lead['email'];
        }

        $emailSent = (new EmailSender())->sendPasswordResetEmail(
            $lead['email'],
            $userName,
            $resetLink,
            1
        );

        $payload = JWT::decode(JWT::getTokenFromHeader());
        $this->activityLogModel->logActivity(
            $id,
            'password_reset_email_sent_by_admin',
            'Password reset email sent by administrator',
            $payload['userId'] ?? null,
            [
                'email' => $lead['email'],
                'emailSent' => (bool)$emailSent
            ]
        );

        (new AdminOperationLogWriter())->logClientPasswordResetEmail(
            $subModule,
            $id,
            $lead['email'],
            (bool) $emailSent
        );

        Response::success([
            'email' => $lead['email'],
            'emailSent' => (bool)$emailSent
        ], 'Password reset email sent successfully');
    }
}
