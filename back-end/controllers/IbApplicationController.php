<?php
/**
 * IB Application 控制器
 * 管理IB申请的审批流程
 */

require_once __DIR__ . '/../models/IbApplication.php';
require_once __DIR__ . '/../models/IbActivityLog.php';
require_once __DIR__ . '/../models/AdminPermission.php';
require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../models/ClientKycSubmission.php';
require_once __DIR__ . '/../models/ClientKycAnswer.php';
require_once __DIR__ . '/../models/ClientNotification.php';
require_once __DIR__ . '/../models/ClientNotificationDelivery.php';
require_once __DIR__ . '/../models/ClientSystemNotification.php';
require_once __DIR__ . '/../models/AdminNotification.php';
require_once __DIR__ . '/../models/EmailTemplate.php';
require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/../models/IbPartnerStatusHistory.php';
require_once __DIR__ . '/../models/IbTierLevel.php';
require_once __DIR__ . '/../models/IbCommissionRule.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/S3Uploader.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/EmailSender.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../services/OperationLog/IbInitialOperationLog.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class IbApplicationController {
    private $applicationModel;
    private $activityLogModel;
    private $partnerStatusHistoryModel;

    public function __construct() {
        $this->applicationModel = new IbApplication();
        $this->activityLogModel = new IbActivityLog();
        $this->partnerStatusHistoryModel = new IbPartnerStatusHistory();
    }

    /**
     * 多 IB 创建允许后台管理员操作；客户端只能基于自己名下的 IB 身份继续创建。
     */
    private function assertCanCreateAdditionalIb(array $sourceIb, array $logData = []) {
        $clientId = (int) ($sourceIb['userId'] ?? 0);
        $currentUser = AuthMiddleware::getCurrentUser();
        if (!$currentUser) {
            IbInitialOperationLog::logFailure($logData, 'add', $clientId > 0 ? $clientId : null, 'createMultiIbFailure', 'No authenticated user found');
            Response::unauthorized('No authenticated user found');
        }

        if (($currentUser['type'] ?? '') === 'admin') {
            return;
        }

        if (($currentUser['type'] ?? '') === 'client' && isset($currentUser['userId'])) {
            if ((int)$currentUser['userId'] === $clientId) {
                return;
            }
            IbInitialOperationLog::logFailure(
                $logData,
                'add',
                $clientId > 0 ? $clientId : null,
                'createMultiIbFailure',
                'You can only create an additional IB for your own account'
            );
            Response::forbidden('You can only create an additional IB for your own account');
        }

        IbInitialOperationLog::logFailure(
            $logData,
            'add',
            $clientId > 0 ? $clientId : null,
            'createMultiIbFailure',
            'You do not have permission to create an additional IB'
        );
        Response::forbidden('You do not have permission to create an additional IB');
    }

    /**
     * 获取IB申请列表
     * GET /api/ib-applications
     */
    public function index() {
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 10;

        // 构建筛选条件
        $filters = [];

        if (isset($_GET['applicationStatus'])) {
            $filters['applicationStatus'] = $_GET['applicationStatus'];
        }

        if (isset($_GET['ibType'])) {
            $filters['ibType'] = $_GET['ibType'];
        }

        if (isset($_GET['reviewerId'])) {
            $filters['reviewerId'] = $_GET['reviewerId'];
        }

        $result = $this->applicationModel->getApplications($page, $perPage, $filters);

        Response::paginated(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * 获取单个申请详情
     * GET /api/ib-applications/{id}
     * 客户端（含预览）仅能查看自己的申请
     */
    public function show($id) {
        $application = $this->applicationModel->getApplicationDetails($id);

        if (!$application) {
            Response::notFound('Application not found');
        }

        $clientUserId = ClientAuthContext::getCurrentClientUserId();
        if ($clientUserId !== null && (int)($application['clientId'] ?? 0) !== (int)$clientUserId) {
            Response::forbidden('You can only view your own application');
        }

        Response::success($application);
    }

    /**
     * 创建IB申请（客户端同意成为IB）
     * POST /api/ib-applications/agree
     */
    public function agree() {
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证
        $validator = new Validator($data, [
            'applicantName' => 'required|string|max:200',
            'applicantEmail' => 'required|email|max:200',
            'ibType' => 'required|in:individual,company',
            'clientId' => 'required'
        ]);

        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }

        $ibPartnerModel = new IbPartner();
        // 旧接口继续保持原来的重复 IB 校验，避免影响现有调用方。
        $existingIb = $ibPartnerModel->getByClientId($data['clientId']);
        if ($existingIb) {
            Response::error('You have already registered as an IB partner or your request is pending', 409);
        }
        $skipSign = isset($data['skipSign']) && filter_var($data['skipSign'], FILTER_VALIDATE_BOOLEAN);

        // 仅写入 ibPartners，状态为 Pending Initial Review（数据库存 pending_initial_review），不创建 ibApplications
        $ibPartnerId = $ibPartnerModel->create([
            'ibCode' => '',
            'userId' => (int)$data['clientId'],
            'companyName' => trim((string)($data['companyName'] ?? '')),
            'status' => IbPartner::STATUS_PENDING_INITIAL_REVIEW,
            'ibType' => $data['ibType'] ?? 'individual',
            'contactEmail' => $data['applicantEmail'] ?? '',
            'registrationDate' => date('Y-m-d H:i:s'),
        ]);

        // 新建 IB 后挂 IB 标签，clients list 据此区分/筛选 IB
        $ibPartnerModel->tagClientAsIb((int)$data['clientId']);

        // 文档确认写入 ibPartnerDocumentAcknowledgements（不再使用 ibApplicationDocumentAcknowledgements）
        if (!$skipSign && isset($data['documentIds']) && is_array($data['documentIds']) && !empty($data['documentIds'])) {
            $db = Database::getInstance();
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $acknowledgedAt = date('Y-m-d H:i:s');

            foreach ($data['documentIds'] as $documentTemplateId) {
                // 检查是否已存在记录
                $existing = $db->fetchOne(
                    "SELECT id FROM ibPartnerDocumentAcknowledgements
                     WHERE ibPartnerId = :ibPartnerId AND documentTemplateId = :documentTemplateId",
                    [
                        'ibPartnerId' => $ibPartnerId,
                        'documentTemplateId' => $documentTemplateId
                    ]
                );

                if (!$existing) {
                    $db->insert('ibPartnerDocumentAcknowledgements', [
                        'ibPartnerId' => $ibPartnerId,
                        'documentTemplateId' => $documentTemplateId,
                        'acknowledged' => 1,
                        'acknowledgedAt' => $acknowledgedAt,
                        'ipAddress' => $ipAddress
                    ]);
                }
            }
        }

        // 记录活动日志（按 ibPartnerId）
        $this->activityLogModel->logActivity(
            $ibPartnerId,
            'ib_partner_created',
            'Client agreed to become IB (Pending Initial Review): ' . ($data['applicantName'] ?? $data['applicantEmail']),
            null,
            'client'
        );

        // 添加时间线：客户端同意成为 IB，首条状态 pending_initial_review（changedBy 为空表示客户操作）
        try {
            $this->partnerStatusHistoryModel->logStatusHistory(
                $ibPartnerId,
                null,
                IbPartner::STATUS_PENDING_INITIAL_REVIEW,
                null,
                null
            );
        } catch (\Throwable $e) {
            error_log('IbApplication agree addPartnerTimeline failed: ' . $e->getMessage());
        }

        Response::created(['id' => $ibPartnerId], 'IB registration submitted successfully');
    }

    /**
     * 创建额外 IB 申请：clientId 表示已有 ibPartners.id，只允许从已通过的 IB 派生新的待初审 IB。
     * POST /api/ib-applications/agree-multiple
     */
    public function agreeMultiple() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            IbInitialOperationLog::logFailure([], 'add', null, 'createMultiIbFailure', 'Invalid JSON body');
            Response::error('Invalid JSON body', 400);
        }

        $validator = new Validator($data, [
            'clientId' => 'required|numeric'
        ]);

        if (!$validator->validate()) {
            IbInitialOperationLog::logFailure(
                $data,
                'add',
                null,
                'createMultiIbFailure',
                OperationLogTextHelpers::validationErrorsToMessage($validator->getErrors())
            );
            Response::validationError($validator->getErrors());
        }

        $sourceIbId = (int)$data['clientId'];
        $ibPartnerModel = new IbPartner();
        $sourceIb = $ibPartnerModel->findById($sourceIbId);
        $clientIdForLog = is_array($sourceIb) ? (int) ($sourceIb['userId'] ?? 0) : 0;
        if (!$sourceIb) {
            IbInitialOperationLog::logFailure($data, 'add', null, 'createMultiIbFailure', 'IB partner not found');
            Response::notFound('IB partner not found');
        }
        if (($sourceIb['status'] ?? '') !== IbPartner::STATUS_APPROVED) {
            IbInitialOperationLog::logFailure(
                $data,
                'add',
                $clientIdForLog > 0 ? $clientIdForLog : null,
                'createMultiIbFailure',
                'Only approved IB partners can create an additional IB'
            );
            Response::error('Only approved IB partners can create an additional IB', 400);
        }

        $clientId = (int)($sourceIb['userId'] ?? 0);
        if ($clientId <= 0) {
            IbInitialOperationLog::logFailure(
                $data,
                'add',
                null,
                'createMultiIbFailure',
                'IB partner is not linked to a client user'
            );
            Response::error('IB partner is not linked to a client user', 400);
        }
        $this->assertCanCreateAdditionalIb($sourceIb, $data);

        $clientUserModel = new ClientUser();
        $client = $clientUserModel->findById($clientId);
        if (!$client) {
            IbInitialOperationLog::logFailure($data, 'add', $clientId, 'createMultiIbFailure', 'Client not found');
            Response::notFound('Client not found');
        }

        $fullName = trim(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? ''));
        $displayName = $fullName !== '' ? $fullName : ($client['email'] ?? ('IB #' . $sourceIbId));
        $contactEmail = $sourceIb['contactEmail'] ?? ($client['email'] ?? '');

        $ibPartnerId = $ibPartnerModel->create([
            'ibCode' => '',
            'userId' => $clientId,
            'companyName' => '',
            'status' => IbPartner::STATUS_PENDING_INITIAL_REVIEW,
            'ibType' => $sourceIb['ibType'] ?? 'individual',
            'contactEmail' => $contactEmail,
            'registrationDate' => date('Y-m-d H:i:s'),
        ]);

        // 新建 IB 后挂 IB 标签，clients list 据此区分/筛选 IB
        $ibPartnerModel->tagClientAsIb((int)$clientId);

        $this->activityLogModel->logActivity(
            $ibPartnerId,
            'ib_partner_created',
            'Additional IB created from existing IB #' . $sourceIbId . ' for ' . $displayName,
            null,
            'client',
            ['sourceIbPartnerId' => $sourceIbId]
        );

        try {
            $this->partnerStatusHistoryModel->logStatusHistory(
                $ibPartnerId,
                null,
                IbPartner::STATUS_PENDING_INITIAL_REVIEW,
                null,
                null
            );
        } catch (\Throwable $e) {
            error_log('IbApplication agreeMultiple addPartnerTimeline failed: ' . $e->getMessage());
        }

        IbInitialOperationLog::logCreateMultiIbSuccess($data, $sourceIb, $sourceIbId, $client, $ibPartnerId);

        Response::created([
            'id' => $ibPartnerId,
            'sourceIbPartnerId' => $sourceIbId
        ], 'Additional IB registration submitted successfully');
    }

    /**
     * 更新申请信息
     * PUT /api/ib-applications/{id}
     */
    public function update($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证申请是否存在
        $application = $this->applicationModel->findById($id);
        if (!$application) {
            Response::notFound('Application not found');
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        $data['updatedBy'] = $adminId;

        // 更新申请
        $this->applicationModel->update($id, $data);

        Response::success(['id' => $id], 'Application updated successfully');
    }

    /**
     * 分配审核人
     * POST /api/ib-applications/{id}/assign-reviewer
     */
    public function assignReviewer($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证
        $validator = new Validator($data, [
            'reviewerId' => 'required|numeric'
        ]);

        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        // 分配审核人
        $this->applicationModel->assignReviewer($id, $data['reviewerId'], $adminId);

        // 记录活动日志
        $this->activityLogModel->logApplicationActivity(
            $id,
            'reviewer_assigned',
            'Reviewer assigned to application',
            $adminId,
            'admin',
            ['reviewerId' => $data['reviewerId']]
        );

        Response::success(null, 'Reviewer assigned successfully');
    }

    /**
     * 批准申请
     * POST /api/ib-applications/{id}/approve
     */
    public function approve($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证
        $validator = new Validator($data, [
            'tierLevelId' => 'required|numeric'
        ]);

        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        // 获取当前状态
        $application = $this->applicationModel->findById($id);
        if (!$application) {
            Response::notFound('Application not found');
        }

        // 检查状态，只有 in_review 状态才能批准
        if ($application['applicationStatus'] !== 'in_review') {
            Response::error('Only applications with "in_review" status can be approved', 400);
        }

        $previousStatus = $application['applicationStatus'];

        // 调用存储过程批准申请
        $result = $this->applicationModel->approveApplication($id, $adminId, $data['tierLevelId']);

        if ($result['success']) {
            // 记录时间线
            $this->applicationModel->logStatusHistory(
                $id,
                $previousStatus,
                'approved',
                $adminId,
                "Application approved with tier level {$data['tierLevelId']}"
            );

            // 先发送客户端通知，再发送邮件（防止邮件超时影响通知创建）
            $this->sendApprovalClientNotification($application, $result['ibPartnerId'], $data['tierLevelId']);
            $this->sendApprovalEmail($application, $result['ibPartnerId'], $data['tierLevelId']);

            Response::success([
                'ibPartnerId' => $result['ibPartnerId'],
                'message' => $result['message']
            ], 'Application approved successfully');
        } else {
            Response::error($result['message'], 400);
        }
    }

    /**
     * 拒绝申请
     * POST /api/ib-applications/{id}/reject
     */
    public function reject($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证
        $validator = new Validator($data, [
            'rejectionReason' => 'required|string'
        ]);

        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        // 获取当前状态
        $application = $this->applicationModel->findById($id);
        $previousStatus = $application['applicationStatus'] ?? 'in_review';

        // 调用存储过程拒绝申请
        $result = $this->applicationModel->rejectApplication($id, $adminId, $data['rejectionReason']);

        if ($result['success']) {
            // 记录时间线
            $this->applicationModel->logStatusHistory(
                $id,
                $previousStatus,
                'rejected',
                $adminId,
                "Application rejected. Reason: {$data['rejectionReason']}"
            );

            // 发送通知给操作员（operator）
            $this->sendRejectionNotificationToOperator($application, $data['rejectionReason']);

            Response::success(null, 'Application rejected successfully');
        } else {
            Response::error($result['message'], 400);
        }
    }

    /**
     * 请求更多信息
     * POST /api/ib-applications/{id}/request-info
     */
    public function requestMoreInfo($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证
        $validator = new Validator($data, [
            'infoRequest' => 'required|string'
        ]);

        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        // 请求更多信息
        $this->applicationModel->requestMoreInfo($id, $data['infoRequest'], $adminId);

        // 记录活动日志
        $this->activityLogModel->logApplicationActivity(
            $id,
            'info_requested',
            'Additional information requested from applicant',
            $adminId,
            'admin',
            ['infoRequest' => $data['infoRequest']]
        );

        Response::success(null, 'Information request sent successfully');
    }

    /**
     * 分配层级
     * POST /api/ib-applications/{id}/assign-tier
     */
    public function assignTier($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证
        $validator = new Validator($data, [
            'tierLevelId' => 'required|numeric'
        ]);

        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        // 分配层级
        $this->applicationModel->assignTierLevel($id, $data['tierLevelId'], $adminId);

        // 记录活动日志
        $this->activityLogModel->logApplicationActivity(
            $id,
            'tier_assigned',
            'Tier level assigned to application',
            $adminId,
            'admin',
            ['tierLevelId' => $data['tierLevelId']]
        );
        // 记录时间线
        $application = $this->applicationModel->findById($id);
        $this->applicationModel->logStatusHistory(
            $id,
            $application['applicationStatus'],
            $application['applicationStatus'],
            $adminId,
            "Tier level {$data['tierLevelId']} assigned"
        );

        Response::success(null, 'Tier assigned successfully');
    }

    /**
     * 分配佣金规则（预分配）
     * POST /api/ib-applications/{id}/assign-rule
     */
    public function assignRule($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证
        $validator = new Validator($data, [
            'ruleId' => 'required|numeric'
        ]);

        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        // 分配规则
        $this->applicationModel->assignRule($id, $data['ruleId'], $adminId);

        // 记录活动日志
        $this->activityLogModel->logApplicationActivity(
            $id,
            'rule_pre_assigned',
            'Commission rule pre-assigned to application',
            $adminId,
            'admin',
            ['ruleId' => $data['ruleId']]
        );

        Response::success(null, 'Rule assigned successfully');
    }

    /**
     * 移除预分配规则
     * DELETE /api/ib-applications/{id}/rules/{ruleId}
     */
    public function removeRule($id, $ruleId) {
        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        // 移除规则
        $this->applicationModel->removeRule($id, $ruleId);

        // 记录活动日志
        $this->activityLogModel->logApplicationActivity(
            $id,
            'rule_removed',
            'Pre-assigned rule removed from application',
            $adminId,
            'admin',
            ['ruleId' => $ruleId]
        );

        Response::success(null, 'Rule removed successfully');
    }

    /**
     * 获取申请统计
     * GET /api/ib-applications/statistics
     */
    public function statistics() {
        $stats = $this->applicationModel->getApplicationStatistics();
        Response::success($stats);
    }

    /**
     * 批量分配审核人（分别分配操作员和审批人）
     * POST /api/ib-applications/bulk-assign-reviewer
     */
    public function bulkAssignReviewer() {
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证
        $validator = new Validator($data, [
            'applicationIds' => 'required|array',
            'operatorId' => 'required|numeric',
            'auditorId' => 'required|numeric'
        ]);

        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        $applicationIds = $data['applicationIds'];
        $operatorId = $data['operatorId']; // 销售操作员（reviewerId）
        $auditorId = $data['auditorId']; // 审核审批人（auditorId）

        // 检查是否都已分配审批人
        $alreadyAssigned = [];
        foreach ($applicationIds as $appId) {
            $app = $this->applicationModel->findById($appId);
            if ($app && ($app['reviewerId'] || $app['auditorId'])) {
                $alreadyAssigned[] = $appId;
            }
        }

        if (!empty($alreadyAssigned)) {
            Response::error('Some applications already have reviewers assigned', 400, [
                'alreadyAssigned' => $alreadyAssigned
            ]);
        }

        $successCount = 0;

        foreach ($applicationIds as $appId) {
            try {
                // 分配操作员和审批人，并更新状态为in_review
                $this->applicationModel->bulkAssignReviewers($appId, $operatorId, $auditorId, $adminId);
                $this->activityLogModel->logApplicationActivity(
                    $appId,
                    'bulk_reviewer_assigned',
                    'Reviewer assigned via bulk operation',
                    $adminId
                );
                // 记录时间线
                $this->applicationModel->logStatusHistory(
                    $appId,
                    'pending',
                    'in_review',
                    $adminId,
                    "Operator and auditor assigned. Operator: {$operatorId}, Auditor: {$auditorId}"
                );

                $successCount++;
            } catch (Exception $e) {
                // 继续处理其他申请
                continue;
            }
        }

        Response::success([
            'totalProcessed' => count($applicationIds),
            'successCount' => $successCount
        ], "Successfully assigned reviewers to {$successCount} applications");
    }

    /**
     * 批量批准申请
     * POST /api/ib-applications/bulk-approve
     */
    public function bulkApprove() {
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证
        $validator = new Validator($data, [
            'applicationIds' => 'required|array',
            'tierLevelId' => 'required|numeric',
            'ruleIds' => 'array' // 规则ID数组（可选，但如果提供则必须至少有一个）
        ]);

        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }

        // 如果提供了 ruleIds，验证至少有一个规则
        $ruleIds = $data['ruleIds'] ?? [];
        if (empty($ruleIds)) {
            Response::validationError(['ruleIds' => 'At least one commission rule must be selected']);
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        $applicationIds = $data['applicationIds'];
        $tierLevelId = $data['tierLevelId'];

        // 验证权限：检查每个申请的 Operator 或 Reviewer 是否是当前用户
        foreach ($applicationIds as $appId) {
            $application = $this->applicationModel->findById($appId);
            if (!$application) {
                continue;
            }

            $operatorId = $application['reviewerId'] ? (string)$application['reviewerId'] : null;
            $reviewerId = $application['auditorId'] ? (string)$application['auditorId'] : null;
            $currentUserIdStr = (string)$adminId;

            if ($operatorId !== $currentUserIdStr && $reviewerId !== $currentUserIdStr) {
                Response::error("You are not the Operator or Reviewer for application #{$appId}", 403);
            }
        }

        // 获取所有规则的详细信息（用于构建完整的 rules 数组）
        $ruleModel = new IbCommissionRule();
        $rulesData = [];

        foreach ($ruleIds as $ruleId) {
            $ruleDetails = $ruleModel->getRuleDetails($ruleId);
            if ($ruleDetails) {
                // 格式化 products（确保格式正确）
                $products = [];
                if (isset($ruleDetails['products']) && is_array($ruleDetails['products'])) {
                    foreach ($ruleDetails['products'] as $product) {
                        $products[] = [
                            'productType' => $product['productType'] ?? 'security',
                            'productName' => $product['productName'] ?? '',
                            'commissionType' => $product['commissionType'] ?? 'per_lot',
                            'commissionRate' => isset($product['commissionRate']) ? (float)$product['commissionRate'] : 0,
                            'additionalRate' => isset($product['additionalRate']) ? (float)$product['additionalRate'] : 0,
                            'minimumVolume' => $product['minimumVolume'] ?? '0.01 lots'
                        ];
                    }
                }

                // 格式化 additionalRules（确保格式正确）
                $additionalRules = [];
                if (isset($ruleDetails['additionalRules']) && is_array($ruleDetails['additionalRules'])) {
                    foreach ($ruleDetails['additionalRules'] as $ar) {
                        $additionalRule = [
                            'productType' => $ar['productType'] ?? 'security',
                            'productName' => $ar['productName'] ?? null,
                            'ruleType' => $ar['ruleType'] ?? 'fixed',
                            'ruleValue' => isset($ar['ruleValue']) ? (float)$ar['ruleValue'] : null,
                            'ruleCondition' => $ar['ruleCondition'] ?? '',
                            'isActive' => isset($ar['isActive']) ? (int)$ar['isActive'] : 1
                        ];

                        // 如果是 volume_tiers 类型，包含 tiers
                        if ($ar['ruleType'] === 'volume_tiers' && isset($ar['tiers']) && is_array($ar['tiers'])) {
                            $additionalRule['tiers'] = [];
                            foreach ($ar['tiers'] as $tier) {
                                $additionalRule['tiers'][] = [
                                    'tierLevel' => (int)$tier['tierLevel'],
                                    'tierName' => $tier['tierName'] ?? null,
                                    'commissionRate' => isset($tier['commissionRate']) ? (float)$tier['commissionRate'] : 0,
                                    'minimumVolume' => isset($tier['minimumVolume']) ? (float)$tier['minimumVolume'] : 0,
                                    'maximumVolume' => isset($tier['maximumVolume']) ? (float)$tier['maximumVolume'] : null
                                ];
                            }
                        }

                        $additionalRules[] = $additionalRule;
                    }
                }

                // 构建规则数据（使用默认配置）
                $rulesData[] = [
                    'ruleId' => $ruleId,
                    'ruleName' => $ruleDetails['ruleName'] ?? "Rule {$ruleId}",
                    'ruleType' => $ruleDetails['ruleType'] ?? 'custom',
                    'paymentCycle' => $ruleDetails['paymentCycle'] ?? 'monthly',
                    'paymentDay' => $ruleDetails['paymentDay'] ?? '',
                    'minimumPayout' => isset($ruleDetails['minimumPayout']) ? (float)$ruleDetails['minimumPayout'] : 100.00,
                    'payoutCurrency' => $ruleDetails['payoutCurrency'] ?? 'USD',
                    'products' => $products,
                    'additionalRules' => $additionalRules
                ];
            }
        }

        $successCount = 0;
        $createdIbPartners = [];
        $skippedCount = 0;
        $skippedReasons = [];

        foreach ($applicationIds as $appId) {
            try {
                // 获取申请信息
                $application = $this->applicationModel->findById($appId);
                if (!$application) {
                    $skippedCount++;
                    $skippedReasons[] = "Application #{$appId} not found";
                    continue;
                }

                // 检查状态，只有 in_review 状态才能批准
                if ($application['applicationStatus'] !== 'in_review') {
                    $skippedCount++;
                    $skippedReasons[] = "Application #{$appId} is not in 'in_review' status";
                    continue;
                }

                // 先保存 Tier（在批准之前）
                try {
                    $this->applicationModel->assignTierLevel($appId, $tierLevelId, $adminId);
                } catch (Exception $e) {
                    $skippedCount++;
                    $skippedReasons[] = "Application #{$appId}: Failed to save tier - " . $e->getMessage();
                    continue;
                }

                // 再保存 Rules（在批准之前）
                try {
                    $this->applicationModel->saveCustomRules($appId, $rulesData, $adminId);
                } catch (Exception $e) {
                    $skippedCount++;
                    $skippedReasons[] = "Application #{$appId}: Failed to save rules - " . $e->getMessage();
                    continue;
                }

                $previousStatus = $application['applicationStatus'];

                // 调用存储过程批准申请
                $result = $this->applicationModel->approveApplication($appId, $adminId, $tierLevelId);
                if ($result['success']) {
                    $successCount++;
                    $createdIbPartners[] = $result['ibPartnerId'];

                    // 记录时间线
                    $this->applicationModel->logStatusHistory(
                        $appId,
                        $previousStatus,
                        'approved',
                        $adminId,
                        "Application approved with tier level {$tierLevelId} and " . count($ruleIds) . " rule(s)"
                    );

                    // 先发送客户端通知，再发送邮件（防止邮件超时影响通知创建）
                    $this->sendApprovalClientNotification($application, $result['ibPartnerId'], $tierLevelId);
                    $this->sendApprovalEmail($application, $result['ibPartnerId'], $tierLevelId);
                } else {
                    $skippedCount++;
                    $skippedReasons[] = "Application #{$appId}: {$result['message']}";
                }
            } catch (Exception $e) {
                // 继续处理其他申请
                $skippedCount++;
                $skippedReasons[] = "Application #{$appId}: " . $e->getMessage();
                continue;
            }
        }

        $responseData = [
            'totalProcessed' => count($applicationIds),
            'successCount' => $successCount,
            'skippedCount' => $skippedCount,
            'createdIbPartners' => $createdIbPartners
        ];

        if ($skippedCount > 0) {
            $responseData['skippedReasons'] = $skippedReasons;
        }

        Response::success($responseData, "Successfully approved {$successCount} application(s)");
    }

    /**
     * 导出申请列表
     * POST /api/ib-applications/export
     */
    public function export() {
        $data = json_decode(file_get_contents('php://input'), true);
        $format = $data['format'] ?? 'csv';
        $selectedIds = $data['selectedIds'] ?? [];

        // 获取要导出的申请数据
        if (empty($selectedIds)) {
            Response::error('No applications selected for export', 400);
        }

        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $sql = "SELECT * FROM vw_ibApplicationsDetails WHERE id IN ($placeholders)";

        $applications = $this->applicationModel->query($sql, $selectedIds);

        Response::success([
            'format' => $format,
            'count' => count($applications),
            'data' => $applications
        ], 'Export data prepared successfully');
    }

    /**
     * 获取申请活动日志
     * GET /api/ib-applications/{id}/activities
     * 客户端（含预览）仅能查看自己申请的活动
     */
    public function getActivities($id) {
        $clientUserId = ClientAuthContext::getCurrentClientUserId();
        if ($clientUserId !== null) {
            $application = $this->applicationModel->findById($id);
            if (!$application || (int)($application['clientId'] ?? 0) !== (int)$clientUserId) {
                Response::forbidden('You can only view activities of your own application');
            }
        }

        $limit = $_GET['limit'] ?? 50;
        $activities = $this->activityLogModel->getApplicationActivities($id, $limit);

        Response::success($activities);
    }

    /**
     * 获取IB申请的KYC数据
     * GET /api/ib-applications/{id}/kyc-data
     */
    public function getKycData($id) {
        // 获取申请信息
        $application = $this->applicationModel->findById($id);
        if (!$application) {
            Response::notFound('Application not found');
        }

        // 通过applicantEmail查找clientUser
        $clientUserModel = new ClientUser();
        $client = $clientUserModel->findByEmail($application['applicantEmail'], false);

        if (!$client || !$client['kycSubmissionId']) {
            Response::success([]);
        }

        // 获取KYC提交详情
        $submissionModel = new ClientKycSubmission();
        $submission = $submissionModel->findById($client['kycSubmissionId']);

        if (!$submission) {
            Response::success([]);
        }

        // 获取答案并按分类分组
        $answerModel = new ClientKycAnswer();
        $answers = $answerModel->getSubmissionAnswers($submission['id']);

        // 按分类分组答案
        $groupedAnswers = $this->groupAnswersByCategory($answers);

        Response::success($groupedAnswers);
    }

    /**
     * 按分类分组答案
     */
    private function groupAnswersByCategory($answers) {
        if (empty($answers)) {
            return [];
        }

        $categories = [];

        foreach ($answers as $answer) {
            $categoryName = $answer['categoryName'] ?? 'Other';
            $categoryId = $answer['categoryId'] ?? 0;

            if (!isset($categories[$categoryName])) {
                $categories[$categoryName] = [
                    'id' => $categoryId,
                    'categoryName' => $categoryName,
                    'answers' => []
                ];
            }

            // 获取答案值
            $answerValue = $this->getAnswerValue($answer);

            // 处理文件上传
            $files = null;
            if ($answer['questionType'] === 'file_upload' && !empty($answer['uploadedFiles'])) {
                $uploadedFiles = json_decode($answer['uploadedFiles'], true);
                if (is_array($uploadedFiles) && !empty($uploadedFiles)) {
                    $s3Uploader = new S3Uploader();
                    $files = array_map(function($file) use ($s3Uploader) {
                        if (is_string($file)) {
                            $filePath = $file;
                            return [
                                'filePath' => $filePath,
                                'fileName' => basename($filePath),
                                'downloadUrl' => $s3Uploader->getFileDownloadUrl($filePath)
                            ];
                        }
                        // 如果已经是对象，确保有downloadUrl
                        if (is_array($file) && !isset($file['downloadUrl']) && isset($file['filePath'])) {
                            $file['downloadUrl'] = $s3Uploader->getFileDownloadUrl($file['filePath']);
                        }
                        return $file;
                    }, $uploadedFiles);
                }
            }

            $categories[$categoryName]['answers'][] = [
                'questionId' => $answer['questionId'],
                'questionText' => $answer['questionText'],
                'questionType' => $answer['questionType'] ?? 'text',
                'answerValue' => $answerValue,
                'files' => $files
            ];
        }

        return array_values($categories);
    }

    /**
     * 获取答案值
     */
    private function getAnswerValue($answer) {
        $questionType = $answer['questionType'] ?? 'text';

        switch ($questionType) {
            case 'number':
                return $answer['answerNumber'] ?? ($answer['answerText'] ?? '-');
            case 'date':
                return $answer['answerDate'] ?? ($answer['answerText'] ?? '-');
            case 'multiple_choice':
                if (!empty($answer['answerValues'])) {
                    $values = json_decode($answer['answerValues'], true);
                    if (is_array($values)) {
                        return implode(', ', $values);
                    }
                    return $answer['answerValues'];
                }
                return $answer['answerText'] ?? '-';
            case 'file_upload':
                return $answer['answerText'] ?? 'No file uploaded';
            default:
                return $answer['answerText'] ?? '-';
        }
    }

    /**
     * 保存个性化规则配置
     * POST /api/ib-applications/{id}/custom-rules
     */
    public function saveCustomRules($id) {
        // 检查编辑权限（只能编辑自己负责的）
        $this->checkEditPermission($id);

        $data = json_decode(file_get_contents('php://input'), true);

        // 验证
        $validator = new Validator($data, [
            'rules' => 'required|array'
        ]);

        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        // 保存个性化规则
        $result = $this->applicationModel->saveCustomRules($id, $data['rules'], $adminId);

        // 如果是rejected状态，修改后变更为in_review
        // 注意：isEdit 的更新已经在 saveCustomRules 方法中完成（所有数据保存成功后）
        $application = $this->applicationModel->findById($id);
        if ($application && $application['applicationStatus'] === 'rejected') {
            $this->applicationModel->update($id, [
                'applicationStatus' => 'in_review',
                'isEdit' => 0, // 重新编辑时重置为编辑模式
                'updatedBy' => $adminId
            ]);

            // 记录时间线
            $this->applicationModel->logStatusHistory(
                $id,
                'rejected',
                'in_review',
                $adminId,
                'Rules modified, resubmitted for review'
            );
        } else {
            // 记录Rules修改到时间线
            $this->applicationModel->logStatusHistory(
                $id,
                $application['applicationStatus'],
                $application['applicationStatus'],
                $adminId,
                'Custom rules updated'
            );
        }

        Response::success($result, 'Custom rules saved successfully');
    }

    /**
     * 获取个性化规则配置
     * GET /api/ib-applications/{id}/custom-rules
     */
    public function getCustomRules($id) {
        $rules = $this->applicationModel->getCustomRules($id);
        Response::success($rules);
    }

    /**
     * 检查编辑权限（只能编辑自己负责的）
     */
    private function checkEditPermission($applicationId) {
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        // 检查是否有编辑权限
        $permissionModel = new AdminPermission();
        $hasPermission = $permissionModel->userHasPermission($adminId, 'ib_application_edit_rules');

        if (!$hasPermission) {
            Response::forbidden('You do not have permission to edit rules');
        }

        // 检查是否是负责的操作员
        $application = $this->applicationModel->findById($applicationId);
        if (!$application) {
            Response::notFound('Application not found');
        }

        if ($application['reviewerId'] != $adminId) {
            Response::forbidden('You can only edit rules for applications assigned to you');
        }

        return true;
    }

    /**
     * 获取审批通知的邮件内容（公共方法）
     */
    private function getApprovalNotificationContent($application, $ibPartnerId, $tierLevelId) {
        $ibPartnerModel = new IbPartner();
        $tierLevelModel = new IbTierLevel();
        $emailTemplateModel = new EmailTemplate();

        // 获取品牌配置
        $appConfig = require __DIR__ . '/../config/app.php';
        $teamName = $appConfig['branding']['teamName'] ?? $appConfig['branding']['companyName'] ?? $appConfig['logoname'] ?? 'Trading Platform Team';

        // 获取IB Partner信息
        $ibPartner = $ibPartnerModel->findById($ibPartnerId);
        $ibCode = $ibPartner ? $ibPartner['ibCode'] : 'N/A';

        // 获取Tier Level信息
        $tierLevel = $tierLevelModel->findById($tierLevelId);
        $tierLevelName = $tierLevel ? "Tier {$tierLevel['tierLevel']} - {$tierLevel['tierName']}" : 'N/A';

        // 获取邮件模板
        $template = $emailTemplateModel->getByKey('ib_application_approved');

        // 准备变量（包含品牌名称）
        $variables = [
            'applicantName' => $application['applicantName'],
            'applicationId' => $application['id'],
            'ibCode' => $ibCode,
            'tierLevel' => $tierLevelName,
            'approvalDate' => date('Y-m-d H:i:s'),
            'teamName' => $teamName // 添加品牌团队名称变量
        ];

        // 处理邮件模板
        if ($template) {
            $emailSubject = EmailTemplate::replaceVariables($template['emailSubject'], $variables);
            $emailBody = EmailTemplate::replaceVariables($template['emailBody'], $variables);

        } else {
            // 默认邮件内容
            $emailSubject = 'Congratulations! Your IB Partnership Application Has Been Approved';
            $emailBody = "Dear {$application['applicantName']},\n\nWe are pleased to inform you that your IB (Introducing Broker) partnership application has been approved!\n\nApplication ID: {$application['id']}\nIB Code: {$ibCode}\nTier Level: {$tierLevelName}\nApproval Date: " . date('Y-m-d H:i:s') . "\n\nYou can now start referring clients and earning commissions.\n\nBest regards,\n{$teamName}";
        }

        return [
            'subject' => $emailSubject,
            'body' => $emailBody
        ];
    }

    /**
     * 发送审批同意的客户端通知
     * 先执行此方法，确保通知创建成功，不受邮件发送影响
     */
    private function sendApprovalClientNotification($application, $ibPartnerId, $tierLevelId) {
        try {
            $clientModel = new ClientUser();

            // 查找客户端用户（通过邮箱）
            $client = $clientModel->findOne(['email' => $application['applicantEmail']]);
            if (!$client) {
                // 如果没有找到客户端用户，不创建通知
                return;
            }

            $clientId = $client['id'];

            // 获取通知内容
            $content = $this->getApprovalNotificationContent($application, $ibPartnerId, $tierLevelId);

            // 1. 先创建 clientNotifications 记录（主通知表）
            $clientNotificationModel = new ClientNotification();
            $notificationId = $clientNotificationModel->create([
                'clientId' => $clientId,
                'subject' => $content['subject'],
                'message' => strip_tags($content['body']),
                'priority' => 'high',
                'scheduleType' => 'immediate',
                'status' => 'sent', // 直接标记为已发送，因为系统通知会立即创建
                'emailTemplate' => 'ib_application_approved',
                'createdBy' => null, // 系统创建
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s')
            ]);

            if (!$notificationId) {
                error_log("Failed to create clientNotifications record for application #{$application['id']}");
                return;
            }

            // 2. 创建 clientSystemNotifications 记录（客户端通过这个表读取通知）
            $systemNotificationModel = new ClientSystemNotification();

            $systemNotificationModel->create([
                'notificationId' => $notificationId, // 关联到 clientNotifications 的 id
                'type' => 'ib_application_approved',
                'metadata' => json_encode([
                    'applicationId' => $application['id'],
                    'ibPartnerId' => $ibPartnerId,
                    'tierLevelId' => $tierLevelId
                ]),
                'clientId' => $clientId,
                'subject' => $content['subject'],
                'message' => strip_tags($content['body']),
                'isRead' => 0,
                'readAt' => null,
                'createdAt' => date('Y-m-d H:i:s')
            ]);

        } catch (Exception $e) {
            // 记录通知创建错误
            error_log("Failed to create client notification for application #{$application['id']}: " . $e->getMessage());
        }
    }

    /**
     * 发送审批同意的邮件
     * 在客户端通知创建成功后执行，即使超时也不影响通知
     */
    private function sendApprovalEmail($application, $ibPartnerId, $tierLevelId) {
        try {
            // 获取邮件内容
            $content = $this->getApprovalNotificationContent($application, $ibPartnerId, $tierLevelId);

            // 发送邮件
            $emailSender = new EmailSender();
            $emailSent = $emailSender->sendClientNotification(
                $application['applicantEmail'],
                $content['subject'],
                strip_tags($content['body']), // 转换为纯文本
                ['type' => 'ib_application_approved', 'applicationId' => $application['id']]
            );

            // 如果邮件发送成功，更新客户端通知的邮件投递状态
            if ($emailSent) {
                try {
                    $clientModel = new ClientUser();
                    $client = $clientModel->findOne(['email' => $application['applicantEmail']]);

                    if ($client) {
                        $deliveryModel = new ClientNotificationDelivery();

                        // 查找该客户端最近的 ib_application_approved 通知
                        $clientNotificationModel = new ClientNotification();
                        $notifications = $clientNotificationModel->findAll([
                            'clientId' => $client['id'],
                            'emailTemplate' => 'ib_application_approved'
                        ], 'createdAt DESC', 1);

                        if (!empty($notifications)) {
                            $notification = $notifications[0];
                            // 更新邮件投递状态为 sent
                            $deliveries = $deliveryModel->findAll([
                                'notificationId' => $notification['id'],
                                'channel' => 'email'
                            ]);

                            if (!empty($deliveries)) {
                                $deliveryModel->update($deliveries[0]['id'], [
                                    'status' => 'sent',
                                    'sentAt' => date('Y-m-d H:i:s')
                                ]);
                            }
                        }
                    }
                } catch (Exception $e) {
                    // 更新投递状态失败不影响主流程
                    error_log("Failed to update email delivery status: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            // 记录邮件发送错误，但不影响主流程
            error_log("Failed to send approval email to {$application['applicantEmail']}: " . $e->getMessage());
        }
    }

    /**
     * 发送拒绝通知给操作员
     */
    private function sendRejectionNotificationToOperator($application, $rejectionReason) {
        try {
            $adminNotificationModel = new AdminNotification();

            // 获取操作员ID（operatorId/auditorId）
            $operatorId = $application['auditorId'] ?? $application['reviewerId'] ?? null;

            if (!$operatorId) {
                // 如果没有操作员，不发送通知
                return;
            }

            // 创建管理员通知
            $adminNotificationModel->create([
                'adminId' => $operatorId,
                'subject' => 'IB Application Rejected',
                'message' => "IB Application #{$application['id']} from {$application['applicantName']} ({$application['applicantEmail']}) has been rejected.\n\nRejection Reason: {$rejectionReason}",
                'priority' => 'normal',
                'scheduleType' => 'immediate',
                'status' => 'pending',
                'createdBy' => null // 系统创建
            ]);

        } catch (Exception $e) {
            // 记录错误但不影响主流程
            error_log("Failed to send rejection notification to operator: " . $e->getMessage());
        }
    }
}
