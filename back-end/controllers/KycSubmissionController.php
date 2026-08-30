<?php
/**
 * KYC Submission 控制器（客户端和管理端）
 */

require_once __DIR__ . '/../models/ClientKycSubmission.php';
require_once __DIR__ . '/../models/ClientKycAnswer.php';
require_once __DIR__ . '/../models/ClientKycDocumentSignature.php';
require_once __DIR__ . '/../models/KycSubmissionActivityLog.php';
require_once __DIR__ . '/../models/KycTemplate.php';
require_once __DIR__ . '/../models/KycQuestion.php';
require_once __DIR__ . '/../models/KycConditionalRule.php';
require_once __DIR__ . '/../models/KycTimeline.php';
require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../models/KycResubmitRequest.php';
require_once __DIR__ . '/../models/KycResubmitAnswer.php';
require_once __DIR__ . '/../models/AdminNotification.php';
require_once __DIR__ . '/../models/AdminNotificationDelivery.php';
require_once __DIR__ . '/../models/AdminSystemNotification.php';
require_once __DIR__ . '/../models/ClientNotification.php';
require_once __DIR__ . '/../models/ClientSystemNotification.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/S3Uploader.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';
require_once __DIR__ . '/../models/AdminUser.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';

class KycSubmissionController {
    private $submissionModel;
    private $answerModel;
    private $signatureModel;
    private $activityLogModel;
    private $templateModel;
    private $questionModel;
    private $ruleModel;
    private $timelineModel;
    private $clientUserModel;
    private $resubmitRequestModel;
    private $resubmitAnswerModel;
    private $adminNotificationModel;
    private $adminNotificationDeliveryModel;
    private $adminSystemNotificationModel;
    private $clientNotificationModel;
    private $clientSystemNotificationModel;
    private $logoText;

    public function __construct() {
        $this->submissionModel = new ClientKycSubmission();
        $this->answerModel = new ClientKycAnswer();
        $this->signatureModel = new ClientKycDocumentSignature();
        $this->activityLogModel = new KycSubmissionActivityLog();
        $this->templateModel = new KycTemplate();
        $this->questionModel = new KycQuestion();
        $this->ruleModel = new KycConditionalRule();
        $this->timelineModel = new KycTimeline();
        $this->clientUserModel = new ClientUser();
        $this->resubmitRequestModel = new KycResubmitRequest();
        $this->resubmitAnswerModel = new KycResubmitAnswer();
        $this->adminNotificationModel = new AdminNotification();
        $this->adminNotificationDeliveryModel = new AdminNotificationDelivery();
        $this->adminSystemNotificationModel = new AdminSystemNotification();
        $this->clientNotificationModel = new ClientNotification();
        $this->clientSystemNotificationModel = new ClientSystemNotification();
        $config = require __DIR__ . '/../config/app.php';
        $this->logoText = $config['branding']['logoText'] ?? 'CRM';
    }

    /**
     * 获取提交列表（管理端）
     * GET /api/kyc-submissions
     */
    public function index() {
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 10;

        $filters = [];

        if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        if (isset($_GET['template_id'])) {
            $filters['templateId'] = $_GET['template_id'];
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_kyclist');
        if ($scope['scope'] === 'none') {
            Response::paginated([], 0, $page, $perPage);
            return;
        }
        if ($scope['scope'] === 'own') {
            $filters['restrict_to_sales_id'] = $scope['restrict_to_sales_id'];
        }

        $result = $this->submissionModel->getAllSubmissionsProgress($page, $perPage, $filters);

        Response::paginated(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * 获取待审核列表
     * GET /api/kyc-submissions/pending
     */
    public function getPending() {
        $limit = $_GET['limit'] ?? 50;
        $pending = $this->submissionModel->getPendingReviews($limit);

        Response::success([
            'submissions' => $pending,
            'total' => count($pending)
        ]);
    }

    /**
     * 获取提交详情
     * GET /api/kyc-submissions/{id}
     */
    public function show($id) {
        $submission = $this->submissionModel->getSubmissionProgress($id);

        if (!$submission) {
            Response::notFound('Submission not found');
        }

        // 如果视图没有包含rejectionReason，从数据库获取
        if (!isset($submission['rejectionReason'])) {
            $fullSubmission = $this->submissionModel->findById($id);
            if ($fullSubmission) {
                $submission['rejectionReason'] = $fullSubmission['rejectionReason'] ?? null;
            }
        }

        // 获取答案并按分类分组
        $answers = $this->answerModel->getSubmissionAnswers($id);
        $submission['answers'] = $this->groupAnswersByCategory($answers);

        // 获取签名
        $submission['signatures'] = $this->signatureModel->getSubmissionSignatures($id);

        // 获取活动日志
        $submission['activities'] = $this->activityLogModel->getSubmissionActivities($id, 20);

        Response::success($submission);
    }

    /**
     * 创建新提交（客户端）
     * POST /api/kyc-submissions
     */
    public function create() {
        $input = json_decode(file_get_contents('php://input'), true);

        // 验证
        $validator = new Validator($input);
        $validator->required(['clientId', 'templateId']);

        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }

        // 检查模板是否存在且活跃
        $template = $this->templateModel->findById($input['templateId']);

        if (!$template || $template['status'] !== 'active') {
            Response::error('Template not found or inactive', 400);
        }

        // 创建前校验：同一客户 + 同一模板下，避免重复创建 KYC。
        // 仅 rejected / expired 视为「可重新申请」，其余已有提交都不应再新建：
        //  - pending / under_review / pending_documents / resubmit_required / approved：在途或已通过，直接阻止
        //  - draft / incomplete：已有未完成的草稿，直接返回让客户继续，不再新建
        $existingSubmissions = $this->submissionModel->getByClientAndTemplate(
            $input['clientId'],
            $input['templateId']
        );
        if (!empty($existingSubmissions)) {
            $blockingStatuses = ['pending', 'under_review', 'pending_documents', 'resubmit_required', 'approved'];
            foreach ($existingSubmissions as $existing) {
                if (in_array($existing['submissionStatus'], $blockingStatuses, true)) {
                    Response::error(
                        'This client already has an active or approved KYC submission for this template.',
                        409,
                        [
                            'existingSubmissionId' => (int) $existing['id'],
                            'existingStatus' => $existing['submissionStatus']
                        ],
                        'KYC_SUBMISSION_EXISTS'
                    );
                }
            }
            foreach ($existingSubmissions as $existing) {
                if (in_array($existing['submissionStatus'], ['draft', 'incomplete'], true)) {
                    $progress = $this->submissionModel->getSubmissionProgress($existing['id']);
                    Response::success($progress, 'Existing draft submission returned');
                }
            }
            // 走到这里说明已有提交全是 rejected / expired，可继续新建
        }

        $data = [
            'clientId' => $input['clientId'],
            'templateId' => $input['templateId'],
            'submissionStatus' => 'draft',
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];

        try {
            $submissionId = $this->submissionModel->create($data);

            // 记录活动
            $this->activityLogModel->logActivity(
                $submissionId,
                'submission_created',
                'KYC submission started'
            );

            $submission = $this->submissionModel->getSubmissionProgress($submissionId);

            Response::created($submission, 'Submission created successfully');

        } catch (Exception $e) {
            Response::error('Failed to create submission: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 保存答案（客户端）
     * POST /api/kyc-submissions/{id}/answers
     */
    public function saveAnswers($id) {
        $submission = $this->submissionModel->findById($id);

        if (!$submission) {
            Response::notFound('Submission not found');
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['answers']) || !is_array($input['answers'])) {
            Response::validationError(['answers' => 'Answers array is required']);
        }

        try {
            $saved = $this->answerModel->saveAnswersBatch($id, $input['answers']);

            // Update submission status to 'incomplete' if it's currently 'draft'
            if ($submission['submissionStatus'] === 'draft' && $saved > 0) {
                $this->submissionModel->update($id, [
                    'submissionStatus' => 'incomplete'
                ]);
            }

            Response::success([
                'saved' => $saved,
                'message' => "{$saved} answers saved successfully"
            ]);

        } catch (Exception $e) {
            Response::error('Failed to save answers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 签署文档（客户端）
     * POST /api/kyc-submissions/{id}/sign-documents
     */
    public function signDocuments($id) {
        $submission = $this->submissionModel->findById($id);

        if (!$submission) {
            Response::notFound('Submission not found');
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['documentIds']) || !is_array($input['documentIds'])) {
            Response::validationError(['documentIds' => 'Document IDs array is required']);
        }

        try {
            $results = $this->signatureModel->signDocumentsBatch(
                $id,
                $input['documentIds'],
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );

            Response::success([
                'signed' => count($results),
                'message' => count($results) . ' documents signed successfully'
            ]);

        } catch (Exception $e) {
            Response::error('Failed to sign documents: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 提交KYC表单（客户端）
     * POST /api/kyc-submissions/{id}/submit
     */
    public function submit($id) {
        $submission = $this->submissionModel->findById($id);

        if (!$submission) {
            Response::notFound('Submission not found');
        }

        if ($submission['submissionStatus'] !== 'draft') {
            Response::error('Only draft submissions can be submitted', 400);
        }

        // 检查是否回答了所有必需问题
        $template = $this->templateModel->findById($submission['templateId']);
        $progress = $this->submissionModel->getSubmissionProgress($id);

        // 检查是否签署了所有必需文档
        if ($template['requireDocumentSignature']) {
            $hasSignedAll = $this->signatureModel->hasSignedAllRequired(
                $id,
                $submission['templateId']
            );

            if (!$hasSignedAll) {
                Response::error('Please sign all required documents before submitting', 400);
            }
        }

        try {
            $this->submissionModel->submitKyc($id);

            // 记录活动
            $this->activityLogModel->logActivity(
                $id,
                'submitted',
                'KYC form submitted for review'
            );

            // 如果启用了自动审批
            if ($template['isAutoApproveEnabled']) {
                $this->submissionModel->approve($id, null, 'Auto-approved');

                $this->activityLogModel->logActivity(
                    $id,
                    'approved',
                    'Automatically approved'
                );
            } else {
                try {
                    $this->notifyAdminsOfKycSubmission($id, $submission['clientId']);
                } catch (Exception $e) {
                    // 通知失败不应该影响主流程
                }
            }

            $updatedSubmission = $this->submissionModel->getSubmissionProgress($id);

            Logger::info('KYC form submitted', [
                'domain' => 'kyc',
                'submissionId' => (int) $id,
                'clientId' => (int) ($submission['clientId'] ?? 0),
                'autoApproved' => !empty($template['isAutoApproveEnabled']),
            ], true);

            Response::success($updatedSubmission, 'KYC form submitted successfully');

        } catch (Exception $e) {
            Response::error('Failed to submit: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 审批KYC（管理端）
     * POST /api/kyc-submissions/{id}/approve
     */
    public function approve($id) {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $subModule = OperationLogPages::resolveLogKyc($input);
        $opLog = new AdminOperationLogWriter();

        $submission = $this->submissionModel->findById($id);

        if (!$submission) {
            $opLog->logKycSubmissionApprove($subModule, 0, (int) $id, '', null, false, 'Submission not found');
            Response::notFound('Submission not found');
        }

        $adminId = JWT::getPayload()['userId'] ?? null;

        $notes = $input['notes'] ?? null;

        try {
            // 1. 审批提交（更新状态为approved）
            $this->submissionModel->approve($id, $adminId, $notes);

            // 2. 更新客户表的kycStatus为approved
            $this->clientUserModel->update($submission['clientId'], [
                'kycStatus' => 'approved',
                'updatedAt' => date('Y-m-d H:i:s')
            ]);

            // 3. 更新kycTimeline表中eventStatus=current的记录为approved时间线事件
            $this->timelineModel->updateCurrentEventToApproved($id, $adminId, $notes);

            // 4. 删除kycTimeline表中eventStatus=pending的记录
            $this->timelineModel->deletePendingEvents($id);

            // 5. 记录活动日志
            $this->activityLogModel->logActivity(
                $id,
                'approved',
                'KYC approved' . ($notes ? ": {$notes}" : ''),
                $adminId
            );

            // 创建或更新客户记录（同步到Client List）
            $this->syncToClientList($submission['clientId'], $id);

            // 发送邮件通知
            $this->sendApprovalEmail($submission['clientId'], $id);

            try {
                $this->sendApprovalClientNotification($submission, $notes);
            } catch (Exception $e) {
                // 通知失败不应该影响主流程
            }

            $updatedSubmission = $this->submissionModel->getSubmissionProgress($id);

            $opLog->logKycSubmissionApprove(
                $subModule,
                (int) ($submission['clientId'] ?? 0),
                (int) $id,
                (string) ($updatedSubmission['templateName'] ?? ''),
                $notes
            );

            Logger::info('KYC submission approved', [
                'domain' => 'kyc',
                'submissionId' => (int) $id,
                'clientId' => (int) ($submission['clientId'] ?? 0),
                'adminId' => $adminId,
            ], true);

            Response::success($updatedSubmission, 'KYC approved successfully');

        } catch (Exception $e) {
            $opLog->logKycSubmissionApprove(
                $subModule,
                (int) ($submission['clientId'] ?? 0),
                (int) $id,
                '',
                null,
                false,
                'Failed to approve: ' . $e->getMessage()
            );
            Response::error('Failed to approve: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 拒绝KYC（管理端）
     * POST /api/kyc-submissions/{id}/reject
     */
    public function reject($id) {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $subModule = OperationLogPages::resolveLogKyc($input);
        $opLog = new AdminOperationLogWriter();

        $submission = $this->submissionModel->findById($id);

        if (!$submission) {
            $opLog->logKycSubmissionReject($subModule, 0, (int) $id, '', '', false, 'Submission not found');
            Response::notFound('Submission not found');
        }

        if (empty($input['reason'])) {
            $opLog->logKycSubmissionReject(
                $subModule,
                (int) ($submission['clientId'] ?? 0),
                (int) $id,
                '',
                '',
                false,
                'Rejection reason is required'
            );
            Response::validationError(['reason' => 'Rejection reason is required']);
        }

        $adminId = JWT::getPayload()['userId'] ?? null;
        $rejectionReason = $input['reason'];

        try {
            // 1. 更新提交状态为rejected
            $this->submissionModel->reject($id, $adminId, $rejectionReason);

            // 2. 更新客户表的kycStatus为rejected
            $this->clientUserModel->update($submission['clientId'], [
                'kycStatus' => 'rejected',
                'updatedAt' => date('Y-m-d H:i:s')
            ]);

            // 3. 更新kycTimeline表中eventStatus=current的记录为rejected时间线事件（合并原步骤3和5）
            $this->timelineModel->updateCurrentEventToRejected($id, $rejectionReason, $adminId);

            // 4. 删除kycTimeline表中eventStatus=pending的记录
            $this->timelineModel->deletePendingEvents($id);

            // 5. 记录活动日志（包含拒绝理由）
            $this->activityLogModel->logActivity(
                $id,
                'rejected',
                "KYC rejected: {$rejectionReason}",
                $adminId,
                ['rejectionReason' => $rejectionReason]
            );

            try {
                $this->sendRejectionClientNotification($submission, $rejectionReason);
            } catch (Exception $e) {
                // 通知失败不应该影响主流程
            }

            $updatedSubmission = $this->submissionModel->getSubmissionProgress($id);

            $opLog->logKycSubmissionReject(
                $subModule,
                (int) ($submission['clientId'] ?? 0),
                (int) $id,
                (string) ($updatedSubmission['templateName'] ?? ''),
                $rejectionReason
            );

            Logger::info('KYC submission rejected', [
                'domain' => 'kyc',
                'submissionId' => (int) $id,
                'clientId' => (int) ($submission['clientId'] ?? 0),
                'adminId' => $adminId,
            ], true);

            Response::success($updatedSubmission, 'KYC rejected');

        } catch (Exception $e) {
            $opLog->logKycSubmissionReject(
                $subModule,
                (int) ($submission['clientId'] ?? 0),
                (int) $id,
                '',
                $rejectionReason,
                false,
                'Failed to reject: ' . $e->getMessage()
            );
            Response::error('Failed to reject: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 需要更多文档（管理端）
     * POST /api/kyc-submissions/{id}/need-docs
     */
    public function needDocs($id) {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $subModule = OperationLogPages::resolveLogKyc($input);
        $opLog = new AdminOperationLogWriter();

        $submission = $this->submissionModel->findById($id);

        if (!$submission) {
            $opLog->logKycSubmissionNeedDocs($subModule, 0, (int) $id, '', 0, false, 'Submission not found');
            Response::notFound('Submission not found');
        }

        // 验证必填字段
        if (empty($input['items']) || !is_array($input['items']) || count($input['items']) === 0) {
            $opLog->logKycSubmissionNeedDocs(
                $subModule,
                (int) ($submission['clientId'] ?? 0),
                (int) $id,
                '',
                0,
                false,
                'At least one item (question or document) is required'
            );
            Response::validationError(['items' => 'At least one item (question or document) is required']);
        }

        $adminId = JWT::getPayload()['userId'] ?? null;

        try {
            // 1. 保存请求的问题和文件信息
            $requestedItems = $input['items'];
            $additionalNotes = $input['notes'] ?? null;

            $requestId = $this->resubmitRequestModel->createRequest(
                $id,
                $submission['clientId'],
                $adminId,
                $requestedItems,
                $additionalNotes
            );

            // 2. 更新提交状态为 resubmit_required
            $this->submissionModel->updateStatus($id, 'resubmit_required', $adminId);

//            // 3. 更新客户表的kycStatus为resubmit_required
//            $this->clientUserModel->update($submission['clientId'], [
//                'kycStatus' => 'resubmit_required',
//                'updatedAt' => date('Y-m-d H:i:s')
//            ]);

            // 4. 处理时间线：更新或创建 resubmit_required 事件
            $itemsSummary = [];
            foreach ($requestedItems as $item) {
                if ($item['type'] === 'question') {
                    $itemsSummary[] = "Question: " . ($item['name'] || $item['title']);
                } else {
                    $itemsSummary[] = "Document: " . ($item['name'] || $item['title']);
                }
            }
            $itemsList = implode(', ', $itemsSummary);
            $eventDescription = "Upload the requested documents to continue";
            $this->timelineModel->updateCurrentEventToResubmitRequired(
                $id,
                $submission['clientId'],
                'Resubmission Required',
                $eventDescription,
                [
                    'requestedBy' => $adminId,
                    'requestId' => $requestId,
                    'items' => $requestedItems,
                    'notes' => $additionalNotes
                ],
                $adminId
            );

            // 5. 删除pending状态的时间线
            $this->timelineModel->deletePendingEvents($id);

            // 6. 记录活动日志
            $this->activityLogModel->logActivity(
                $id,
                'resubmit_required',
                "Resubmission requested: {$itemsList}",
                $adminId,
                ['requestId' => $requestId, 'items' => $requestedItems]
            );

            // 7. 发送邮件通知
            $this->sendResubmitRequestEmail($submission['clientId'], $id, $requestedItems, $additionalNotes);

            try {
                $this->sendResubmitClientNotification($submission, $requestId, $requestedItems, $additionalNotes);
            } catch (Exception $e) {
                // 通知失败不应该影响主流程
            }

            $updatedSubmission = $this->submissionModel->getSubmissionProgress($id);

            $opLog->logKycSubmissionNeedDocs(
                $subModule,
                (int) ($submission['clientId'] ?? 0),
                (int) $id,
                (string) ($updatedSubmission['templateName'] ?? ''),
                count($requestedItems)
            );

            Response::success($updatedSubmission, 'Resubmission request sent successfully');

        } catch (Exception $e) {
            $opLog->logKycSubmissionNeedDocs(
                $subModule,
                (int) ($submission['clientId'] ?? 0),
                (int) $id,
                '',
                0,
                false,
                'Failed to request resubmission: ' . $e->getMessage()
            );
            Response::error('Failed to request resubmission: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 分配审核员（管理端）
     * POST /api/kyc-submissions/{id}/assign
     */
    public function assign($id) {
        $submission = $this->submissionModel->findById($id);

        if (!$submission) {
            Response::notFound('Submission not found');
        }

        $input = json_decode(file_get_contents('php://input'), true);

        // 支持 assigneeId 或 reviewerId 字段名（保持向后兼容）
        $reviewerId = $input['reviewerId'] ?? $input['assigneeId'] ?? null;

        if (empty($reviewerId)) {
            Response::validationError(['reviewerId' => 'Reviewer ID is required']);
        }

        $adminId = JWT::getPayload()['userId'] ?? null;

        try {
            // 更新分配的审核员
            $this->submissionModel->assignReviewer($id, $reviewerId, $adminId);

            // 记录活动
            $this->activityLogModel->logActivity(
                $id,
                'assigned',
                "Assigned to reviewer ID: {$reviewerId}",
                $adminId
            );

            $updatedSubmission = $this->submissionModel->getSubmissionProgress($id);

            Response::success($updatedSubmission, 'Reviewer assigned successfully');

        } catch (Exception $e) {
            Response::error('Failed to assign reviewer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取客户的KYC提交
     * GET /api/clients/{clientId}/kyc-submissions
     */
    public function getClientSubmissions($clientId) {
        $submissions = $this->submissionModel->getClientSubmissions($clientId);

        // 为每个提交添加进度信息
        foreach ($submissions as &$sub) {
            $progress = $this->submissionModel->getSubmissionProgress($sub['id']);
            $sub['progress'] = $progress;
        }

        Response::success([
            'submissions' => $submissions,
            'total' => count($submissions)
        ]);
    }

    /**
     * 获取提交统计
     * GET /api/kyc-submissions/statistics
     */
    public function statistics() {
        $templateId = $_GET['template_id'] ?? null;

        $stats = $this->submissionModel->getSubmissionStatistics($templateId);

        // 转换为更友好的格式
        $formatted = [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0
        ];

        // 计算总数和各状态数量
        $totalCount = 0;
        $pendingCount = 0;
        $approvedCount = 0;
        $rejectedCount = 0;

        foreach ($stats as $stat) {
            $status = $stat['submissionStatus'];
            $count = (int)$stat['count'];

            // 累加总数（已经排除了draft和incomplete）
            $totalCount += $count;

            // 按状态分类统计
            if ($status === 'approved') {
                $approvedCount += $count;
            } elseif ($status === 'rejected') {
                $rejectedCount += $count;
            } else {
                // 所有不是approved和rejected的状态都算作pending（已排除draft和incomplete）
                $pendingCount += $count;
            }
        }

        $formatted['total'] = $totalCount;
        $formatted['pending'] = $pendingCount;
        $formatted['approved'] = $approvedCount;
        $formatted['rejected'] = $rejectedCount;

        Response::success($formatted);
    }

    /**
     * 获取提交的活动日志
     * GET /api/kyc-submissions/{id}/activities
     */
    public function getActivities($id) {
        $submission = $this->submissionModel->findById($id);

        if (!$submission) {
            Response::notFound('Submission not found');
        }

        $limit = $_GET['limit'] ?? 50;
        $activities = $this->activityLogModel->getSubmissionActivities($id, $limit);

        Response::success([
            'activities' => $activities,
            'total' => count($activities)
        ]);
    }

    /**
     * 评估条件规则
     * POST /api/kyc-submissions/{id}/evaluate-rules
     */
    public function evaluateRules($id) {
        $submission = $this->submissionModel->findById($id);

        if (!$submission) {
            Response::notFound('Submission not found');
        }

        // 获取所有答案
        $answers = $this->answerModel->getSubmissionAnswers($id);

        // 将答案转换为问题ID => 答案值的格式
        $answerMap = [];
        foreach ($answers as $answer) {
            $answerMap[$answer['questionId']] = $this->answerModel->getAnswerValue($answer);
        }

        // 评估规则
        $actions = $this->ruleModel->evaluateTemplateRules($submission['templateId'], $answerMap);

        Response::success([
            'actions' => $actions,
            'count' => count($actions)
        ]);
    }

    /**
     * 同步到客户列表
     */
    private function syncToClientList($clientId, $submissionId) {
        require_once __DIR__ . '/../models/ClientUser.php';

        $clientModel = new ClientUser();
        $client = $clientModel->findById($clientId);

        if ($client) {
            // 更新客户的KYC状态
            $clientModel->update($clientId, [
                'kycStatus' => 'approved',
                'kycSubmissionId' => $submissionId,
                'verifiedAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * 发送审批邮件通知
     */
    private function sendApprovalEmail($clientId, $submissionId) {
        try {
            require_once __DIR__ . '/../utils/EmailSender.php';
            require_once __DIR__ . '/../models/ClientUser.php';
            require_once __DIR__ . '/../models/EmailTemplate.php';

            $clientModel = new ClientUser();
            $templateModel = new EmailTemplate();

            $client = $clientModel->findById($clientId);
            if (!$client) return;

            // 获取邮件模板（从 emailTemplates 表）
            $template = $templateModel->getByKey('kyc_approval_notification');

            $emailSender = new EmailSender();

            if (!$template) {
                // 如果没有模板，使用默认内容
                $subject = 'KYC Verification Approved';
                $body = "Dear {$client['firstName']} {$client['lastName']},\n\nYour KYC verification has been approved. You can now access all platform features.\n\nThank you for your patience.";
            } else {
                // 准备变量
                $config = require __DIR__ . '/../config/app.php';
                $platformName = $config['branding']['companyShortName']
                    ?? $config['branding']['logoText']
                    ?? $config['branding']['companyName']
                    ?? 'CRM';
                $clientName = trim(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? ''));
                $dashboardUrl = $config['client_frontend_url'] ?? 'https://example.com';

                $variables = [
                    'clientName' => $clientName,
                    'platformName' => $platformName,
                    'dashboardUrl' => $dashboardUrl
                ];

                // 替换模板变量（使用双花括号格式）
                $subject = EmailTemplate::replaceVariables($template['emailSubject'], $variables);
                $body = EmailTemplate::replaceVariables($template['emailBody'], $variables);
            }

            $emailSender->send(
                $client['email'],
                $subject,
                $body
            );

        } catch (Exception $e) {
            // 记录错误但不影响主流程
            error_log("Failed to send approval email: " . $e->getMessage());
        }
    }

    /**
     * 发送重新提交请求邮件通知
     */
    private function sendResubmitRequestEmail($clientId, $submissionId, $requestedItems, $additionalNotes = null) {
        try {
            require_once __DIR__ . '/../utils/EmailSender.php';
            require_once __DIR__ . '/../models/ClientUser.php';
            require_once __DIR__ . '/../models/EmailTemplate.php';

            $clientModel = new ClientUser();
            $templateModel = new EmailTemplate();

            $client = $clientModel->findById($clientId);
            if (!$client) return;

            // 获取邮件模板（从 emailTemplates 表）
            $template = $templateModel->getByKey('kyc_resubmit_request');
            if (!$template) {
                // 如果没有模板，使用默认内容
                $subject = 'Additional Information Required for KYC Verification';
                $body = $this->generateResubmitEmailBody($client, $requestedItems, $additionalNotes);
            } else {
                // 构建请求项目列表
                $requiredItemsHtml = '<ul>';
                foreach ($requestedItems as $item) {
                    if ($item['type'] === 'question') {
                        $requiredItemsHtml .= '<li>Question: ' . htmlspecialchars($item['name'] || $item['title']) . '</li>';
                    } else {
                        $requiredItemsHtml .= '<li>Document: ' . htmlspecialchars($item['name'] || $item['title']) . '</li>';
                    }
                }
                $requiredItemsHtml .= '</ul>';

                // 准备变量
                $config = require __DIR__ . '/../config/app.php';
                $platformName = $config['branding']['companyShortName']
                    ?? $config['branding']['logoText']
                    ?? $config['branding']['companyName']
                    ?? 'CRM';
                $clientName = trim(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? ''));
                $resubmitUrl = ($config['client_frontend_url'] ?? 'https://example.com') . '/client/kyc-verification';

                $variables = [
                    'clientName' => $clientName,
                    'platformName' => $platformName,
                    'requiredItems' => $requiredItemsHtml,
                    'resubmitUrl' => $resubmitUrl
                ];

                // 替换模板变量（使用双花括号格式）
                $subject = EmailTemplate::replaceVariables($template['emailSubject'], $variables);
                $body = EmailTemplate::replaceVariables($template['emailBody'], $variables);
            }

            $emailSender = new EmailSender();
            $emailSender->send(
                $client['email'],
                $subject,
                $body
            );

        } catch (Exception $e) {
            // 记录错误但不影响主流程
            error_log("Failed to send resubmit request email: " . $e->getMessage());
        }
    }

    /**
     * 生成重新提交邮件内容（如果没有模板）
     */
    private function generateResubmitEmailBody($client, $requestedItems, $additionalNotes) {
        $clientName = $client['firstName'] . ' ' . $client['lastName'];
        $itemsList = '<ul>';
        foreach ($requestedItems as $item) {
            if ($item['type'] === 'question') {
                $itemsList .= '<li>Question: ' . htmlspecialchars($item['name'] || $item['title']) . '</li>';
            } else {
                $itemsList .= '<li>Document: ' . htmlspecialchars($item['name'] || $item['title']) . '</li>';
            }
        }
        $itemsList .= '</ul>';

        $notesHtml = $additionalNotes ? '<p><strong>Additional Notes:</strong> ' . htmlspecialchars($additionalNotes) . '</p>' : '';

        return "<html><body>
            <p>Dear {$clientName},</p>
            <p>We need additional information to complete your KYC verification.</p>
            <p><strong>Required items:</strong></p>
            {$itemsList}
            {$notesHtml}
            <p><strong>Please note:</strong></p>
            <ul>
                <li>Submit the requested documents within 7 days</li>
                <li>Ensure all documents are clear and legible</li>
                <li>Documents must be recent and valid</li>
            </ul>
            <p><a href=\"https://" . ($_SERVER['HTTP_HOST'] ?? 'example.com') . "/client/kyc-verification\">Submit Documents</a></p>
            <p>If you have questions, please contact our support team.</p>
            <p>Best regards,<br>" . $this->logoText . " Team</p>
        </body></html>";
    }

    /**
     * 获取重新提交请求信息（管理端和客户端）
     * GET /api/kyc-submissions/{id}/resubmit-request
     */
    public function getResubmitRequest($id) {
        $submission = $this->submissionModel->findById($id);

        if (!$submission) {
            Response::notFound('Submission not found');
        }

        // 获取最新的请求
        $latestRequest = $this->resubmitRequestModel->getLatestRequest($id);
        if (!$latestRequest) {
            Response::notFound('Resubmit request not found');
        }

        // 获取完整的请求信息（包含项目和答案）
        $request = $this->resubmitRequestModel->getRequestWithItems($latestRequest['id']);

        Response::success($request);
    }

    /**
     * 获取重新提交的答案（管理端）
     * GET /api/kyc-submissions/{id}/resubmit-answers
     */
    public function getResubmitAnswers($id) {
        $submission = $this->submissionModel->findById($id);

        if (!$submission) {
            Response::notFound('Submission not found');
        }

        // 获取最新的请求（包含请求的项目和答案）
        $latestRequest = $this->resubmitRequestModel->getLatestRequest($id);
        if (!$latestRequest) {
            Response::notFound('Resubmit request not found');
        }

        $request = $this->resubmitRequestModel->getRequestWithItems($latestRequest['id']);

        // 处理答案中的文件链接
        if (isset($request['answers']) && is_array($request['answers'])) {
            foreach ($request['answers'] as &$answer) {
                // 处理 uploadedFiles
                if (isset($answer['uploadedFiles'])) {
                    // 如果已经是数组
                    if (is_array($answer['uploadedFiles'])) {
                        $files = [];
                        foreach ($answer['uploadedFiles'] as $file) {
                            // 如果已经是对象（包含 downloadUrl），直接使用
                            if (is_array($file) && isset($file['downloadUrl'])) {
                                $files[] = $file;
                            } else {
                                // 如果是字符串路径，转换为对象
                                $filePath = is_string($file) ? $file : ($file['filePath'] ?? $file['path'] ?? $file);
                                $s3Uploader = new S3Uploader();
                                $files[] = [
                                    'filePath' => $filePath,
                                    'fileName' => basename($filePath),
                                    'downloadUrl' => $s3Uploader->getFileDownloadUrl($filePath)
                                ];
                            }
                        }
                        $answer['uploadedFiles'] = $files;
                    }
                }
            }
        }

        Response::success($request);
    }


    /**
     * 将答案按分类分组
     * @param array $answers 原始答案数据
     * @return array 按分类分组的答案
     */
    private function groupAnswersByCategory($answers) {
        $groupedAnswers = [];

        foreach ($answers as $answer) {
            $categoryName = $answer['categoryName'];

            // 如果分类不存在，创建分类
            if (!isset($groupedAnswers[$categoryName])) {
                $groupedAnswers[$categoryName] = [
                    'categoryName' => $categoryName,
                    'categoryId' => $answer['categoryId'] ?? 0,
                    'questions' => []
                ];
            }

            // 根据questionType处理答案显示
            $displayAnswer = $this->formatAnswerForDisplay($answer);

            // 构建问题数据
            $questionData = [
                'questionId' => (int)$answer['questionId'],
                'questionText' => $answer['questionText'],
                'answerId' => (int)$answer['id'],
                'answer' => $displayAnswer,
                'questionType' => $answer['questionType'] ?? 'text'
            ];

            // 如果是文件上传类型，添加文件信息
            if ($answer['questionType'] === 'file_upload' && $answer['uploadedFiles']) {
                $files = json_decode($answer['uploadedFiles'], true);
                if (is_array($files) && !empty($files)) {
                    $fileInfos = [];
                    $s3Uploader = new S3Uploader();
                    foreach ($files as $filePath) {
                        $fileName = basename($filePath);
                        $fileInfos[] = [
                            'fileName' => $fileName,
                            'filePath' => $filePath,
                            'downloadUrl' => $s3Uploader->getFileDownloadUrl($filePath)
                        ];
                    }
                    $questionData['files'] = $fileInfos;
                }
            }

            // 添加问题和答案到对应分类
            $groupedAnswers[$categoryName]['questions'][] = $questionData;
        }

        // 转换为数组格式（保持分类顺序）
        return array_values($groupedAnswers);
    }

    /**
     * 根据问题类型格式化答案显示
     * @param array $answer 答案数据（包含问题的questionType）
     * @return string 格式化后的答案字符串
     */
    private function formatAnswerForDisplay($answer) {
        $questionType = $answer['questionType']; // 从问题表获取的类型

        switch ($questionType) {
            case 'text':
            case 'textarea':
            case 'email':
            case 'phone':
                // 使用answerText字段
                return $answer['answerText'] ?? '';

            case 'number':
                // 使用answerNumber字段
                return $answer['answerNumber'] ? number_format($answer['answerNumber'], 2) : '';

            case 'date':
            case 'datetime':
                // 使用answerDate字段
                if ($answer['answerDate']) {
                    return date('Y-m-d', strtotime($answer['answerDate']));
                }
                return '';

            case 'single_choice':
                // 使用answerText字段（存储选中的选项文本）
                return $answer['answerText'] ?? '';

            case 'multiple_choice':
                // 使用answerValues字段（JSON数组）
                if ($answer['answerValues']) {
                    $values = json_decode($answer['answerValues'], true);
                    return is_array($values) ? implode(', ', $values) : $answer['answerValues'];
                }
                return '';

            case 'boolean':
            case 'checkbox':
                // 使用answerText字段，转换为Yes/No
                $value = $answer['answerText'] ?? '';
                return ($value === '1' || strtolower($value) === 'true' || strtolower($value) === 'yes') ? 'Yes' : 'No';

            case 'file_upload':
                // 使用uploadedFiles字段（JSON数组格式）
                if ($answer['uploadedFiles']) {
                    $files = json_decode($answer['uploadedFiles'], true);
                    if (is_array($files) && !empty($files)) {
                        $fileNames = [];
                        foreach ($files as $filePath) {
                            // 从完整路径中提取文件名
                            $fileName = basename($filePath);
                            $fileNames[] = $fileName;
                        }
                        // 返回文件名列表，用逗号分隔
                        return implode(', ', $fileNames);
                    }
                }
                return 'No file uploaded';

            case 'signature':
                // 使用answerText字段
                return $answer['answerText'] ? 'Signed' : 'Not signed';

            default:
                // 默认优先使用answerText
                return $answer['answerText'] ?? '';
        }
    }

    /**
     * 批量分配审核员（管理端）
     * POST /api/kyc-submissions/bulk-assign
     */
    public function bulkAssign() {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $subModule = OperationLogPages::resolveLogKyc($input);
        $opLog = new AdminOperationLogWriter();

        $bulkAssignErrors = [];
        if (empty($input['submissionIds']) || !is_array($input['submissionIds'])) {
            $bulkAssignErrors['submissionIds'] = ['Submission IDs array is required'];
        }
        if (empty($input['reviewerId'])) {
            $bulkAssignErrors['reviewerId'] = ['Reviewer ID is required'];
        }
        if (!empty($bulkAssignErrors)) {
            $opLog->logKycReviewerAssignBulk(
                $subModule,
                [],
                0,
                '',
                '',
                false,
                OperationLogTextHelpers::validationErrorsToMessage($bulkAssignErrors)
            );
            Response::validationError($bulkAssignErrors);
        }

        $adminId = JWT::getPayload()['userId'] ?? null;
        $notes = $input['notes'] ?? null;

        try {
            // 第一步：检查所有提交是否已有审核员
            $submissionsWithReviewer = [];
            foreach ($input['submissionIds'] as $submissionId) {
                $submission = $this->submissionModel->findById($submissionId);

                if (!$submission) {
                    $opLog->logKycReviewerAssignBulk(
                        $subModule,
                        [],
                        0,
                        '',
                        '',
                        false,
                        "Submission {$submissionId} not found"
                    );
                    Response::validationError(['submissionIds' => "Submission {$submissionId} not found"]);
                }

                // 检查是否已有审核员
                if (!empty($submission['reviewedBy'])) {
                    $submissionsWithReviewer[] = $submissionId;
                }
            }

            // 如果有任何提交已有审核员，返回错误信息，不执行任何分配
            if (!empty($submissionsWithReviewer)) {
                $opLog->logKycReviewerAssignBulk(
                    $subModule,
                    [],
                    0,
                    '',
                    '',
                    false,
                    'Submissions with assigned reviewers cannot be reassigned'
                );
                Response::error('Submissions with assigned reviewers cannot be reassigned', 400);
            }

            // 第二步：所有提交都没有审核员，执行批量分配
            $successCount = 0;
            $clientIds = [];
            foreach ($input['submissionIds'] as $submissionId) {
                $submission = $this->submissionModel->findById($submissionId);
                if ($submission && !empty($submission['clientId'])) {
                    $clientIds[] = (int) $submission['clientId'];
                }

                // 更新分配的审核员（会自动将状态改为under_review）
                $this->submissionModel->assignReviewer($submissionId, $input['reviewerId'], $adminId);

                // 记录活动
                $this->activityLogModel->logActivity(
                    $submissionId,
                    'assigned',
                    "Bulk assigned to reviewer ID: {$input['reviewerId']}" . ($notes ? " - {$notes}" : ''),
                    $adminId
                );

                $successCount++;
            }

            $reviewerModel = new AdminUser();
            $reviewer = $reviewerModel->findById($input['reviewerId']);
            $reviewerName = trim((string) ($reviewer['fullName'] ?? $reviewer['username'] ?? ''));
            if ($reviewerName === '') {
                $reviewerName = 'ID:' . $input['reviewerId'];
            }

            $opLog->logKycReviewerAssignBulk(
                $subModule,
                $clientIds,
                $successCount,
                $reviewerName,
                (string) ($notes ?? '')
            );

            Response::success([
                'message' => "Successfully assigned {$successCount} submissions",
                'successCount' => $successCount
            ]);

        } catch (Exception $e) {
            $opLog->logKycReviewerAssignBulk(
                $subModule,
                [],
                0,
                '',
                '',
                false,
                'Failed to bulk assign: ' . $e->getMessage()
            );
            Response::error('Failed to bulk assign: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 批量审批KYC（管理端）
     * POST /api/kyc-submissions/bulk-approve
     */
    public function bulkApprove() {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $subModule = OperationLogPages::resolveLogKyc($input);
        $opLog = new AdminOperationLogWriter();

        if (empty($input['submissionIds']) || !is_array($input['submissionIds'])) {
            $opLog->logKycSubmissionApproveBulk($subModule, [], 0, 0, '', false, 'Submission IDs array is required');
            Response::validationError(['submissionIds' => 'Submission IDs array is required']);
        }

        $adminId = JWT::getPayload()['userId'] ?? null;
        $notes = $input['notes'] ?? null;
        $successCount = 0;
        $successClientIds = [];
        $errors = [];
        $selectedCount = count($input['submissionIds']);

        try {
            foreach ($input['submissionIds'] as $submissionId) {
                $submission = $this->submissionModel->findById($submissionId);

                if (!$submission) {
                    $errors[] = "Submission {$submissionId} not found";
                    continue;
                }

                // 1. 审批提交（更新状态为approved）
                $this->submissionModel->approve($submissionId, $adminId, $notes);

                // 2. 更新客户表的kycStatus为approved
                $this->clientUserModel->update($submission['clientId'], [
                    'kycStatus' => 'approved',
                    'updatedAt' => date('Y-m-d H:i:s')
                ]);

                // 3. 更新kycTimeline表中eventStatus=current的记录为approved时间线事件
                $this->timelineModel->updateCurrentEventToApproved($submissionId, $adminId, $notes);

                // 4. 删除kycTimeline表中eventStatus=pending的记录
                $this->timelineModel->deletePendingEvents($submissionId);

                // 5. 记录活动日志
                $this->activityLogModel->logActivity(
                    $submissionId,
                    'approved',
                    'Bulk approved' . ($notes ? ": {$notes}" : ''),
                    $adminId
                );

                // 同步到客户列表
                $this->syncToClientList($submission['clientId'], $submissionId);

                // 发送邮件通知
                $this->sendApprovalEmail($submission['clientId'], $submissionId);

                $successCount++;
                if (!empty($submission['clientId'])) {
                    $successClientIds[] = (int) $submission['clientId'];
                }
            }

            if ($successCount > 0) {
                $opLog->logKycSubmissionApproveBulk(
                    $subModule,
                    $successClientIds,
                    $selectedCount,
                    $successCount,
                    (string) ($notes ?? '')
                );
            }

            Response::success([
                'message' => "Successfully approved {$successCount} submissions",
                'successCount' => $successCount,
                'errors' => $errors
            ]);

        } catch (Exception $e) {
            $opLog->logKycSubmissionApproveBulk(
                $subModule,
                [],
                $selectedCount,
                0,
                (string) ($notes ?? ''),
                false,
                'Failed to bulk approve: ' . $e->getMessage()
            );
            Response::error('Failed to bulk approve: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 批量拒绝KYC（管理端）
     * POST /api/kyc-submissions/bulk-reject
     */
    public function bulkReject() {
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['submissionIds']) || !is_array($input['submissionIds'])) {
            Response::validationError(['submissionIds' => 'Submission IDs array is required']);
        }

        if (empty($input['reason'])) {
            Response::validationError(['reason' => 'Rejection reason is required']);
        }

        $adminId = JWT::getPayload()['userId'] ?? null;
        $successCount = 0;
        $errors = [];

        try {
            foreach ($input['submissionIds'] as $submissionId) {
                $submission = $this->submissionModel->findById($submissionId);

                if (!$submission) {
                    $errors[] = "Submission {$submissionId} not found";
                    continue;
                }

                // 拒绝提交
                $this->submissionModel->reject($submissionId, $adminId, $input['reason']);

                // 记录活动
                $this->activityLogModel->logActivity(
                    $submissionId,
                    'rejected',
                    "Bulk rejected: {$input['reason']}",
                    $adminId
                );

                $successCount++;
            }

            Response::success([
                'message' => "Successfully rejected {$successCount} submissions",
                'successCount' => $successCount,
                'errors' => $errors
            ]);

        } catch (Exception $e) {
            Response::error('Failed to bulk reject: ' . $e->getMessage(), 500);
        }
    }

    private function notifyAdminsOfKycSubmission($submissionId, $clientId) {
        $client = $this->clientUserModel->findById($clientId);
        if (!$client) {
            return;
        }

        $clientName = trim(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? ''));
        if ($clientName === '') {
            $clientName = $client['email'] ?? ('Client #' . $clientId);
        }

        $clientEmail = $client['email'] ?? 'unknown-email';
        $subject = "New KYC Submission from {$clientName}";
        $message = "Client {$clientName} ({$clientEmail}) has submitted a KYC application for review.";
        $metadata = json_encode([
            'submissionId' => (int)$submissionId,
            'clientId' => (int)$clientId,
            'action' => 'view_kyc_submission',
            'actionUrl' => '/kyc-submissions'
        ]);

        $adminId = !empty($client['accountManagerId']) ? (int)$client['accountManagerId'] : 0;
        $this->createAdminNotification($adminId, $subject, $message, $metadata, 'kyc_submission');
    }

    private function createAdminNotification($adminId, $subject, $message, $metadata, $type) {
        $now = date('Y-m-d H:i:s');

        $notificationId = $this->adminNotificationModel->create([
            'adminId' => $adminId,
            'subject' => $subject,
            'message' => $message,
            'priority' => 'normal',
            'scheduleType' => 'immediate',
            'status' => 'sent',
            'createdBy' => null,
            'createdAt' => $now
        ]);

        if (!$notificationId) {
            return;
        }

        $this->adminNotificationDeliveryModel->create([
            'notificationId' => $notificationId,
            'channel' => 'system',
            'status' => 'sent',
            'sentAt' => $now,
            'createdAt' => $now
        ]);

        $this->adminSystemNotificationModel->create([
            'notificationId' => $notificationId,
            'type' => $type,
            'metadata' => $metadata,
            'adminId' => $adminId,
            'subject' => $subject,
            'message' => $message,
            'isRead' => 0,
            'createdAt' => $now
        ]);
    }

    private function sendRejectionClientNotification($submission, $rejectionReason) {
        $clientId = (int)($submission['clientId'] ?? 0);
        if ($clientId <= 0) {
            return;
        }

        $subject = 'Your KYC submission was rejected';
        $message = "Your KYC submission was rejected. Reason: {$rejectionReason}";
        $now = date('Y-m-d H:i:s');

        $notificationId = $this->clientNotificationModel->create([
            'clientId' => $clientId,
            'subject' => $subject,
            'message' => $message,
            'priority' => 'high',
            'scheduleType' => 'immediate',
            'status' => 'sent',
            'emailTemplate' => 'kyc_rejection_notification',
            'createdBy' => null,
            'createdAt' => $now,
            'updatedAt' => $now
        ]);

        if (!$notificationId) {
            return;
        }

        $this->clientSystemNotificationModel->create([
            'notificationId' => $notificationId,
            'type' => 'kyc_rejected',
            'metadata' => json_encode([
                'submissionId' => (int)($submission['id'] ?? 0),
                'clientId' => $clientId,
                'rejectionReason' => $rejectionReason
            ]),
            'clientId' => $clientId,
            'subject' => $subject,
            'message' => $message,
            'isRead' => 0,
            'readAt' => null,
            'createdAt' => $now
        ]);
    }

    private function sendApprovalClientNotification($submission, $notes = null) {
        $clientId = (int)($submission['clientId'] ?? 0);
        if ($clientId <= 0) {
            return;
        }

        $subject = 'Your KYC submission was approved';
        $message = 'Your KYC submission has been approved.';
        if (!empty($notes)) {
            $message .= " Notes: {$notes}";
        }
        $now = date('Y-m-d H:i:s');

        $notificationId = $this->clientNotificationModel->create([
            'clientId' => $clientId,
            'subject' => $subject,
            'message' => $message,
            'priority' => 'high',
            'scheduleType' => 'immediate',
            'status' => 'sent',
            'emailTemplate' => 'kyc_approval_notification',
            'createdBy' => null,
            'createdAt' => $now,
            'updatedAt' => $now
        ]);

        if (!$notificationId) {
            return;
        }

        $this->clientSystemNotificationModel->create([
            'notificationId' => $notificationId,
            'type' => 'kyc_approved',
            'metadata' => json_encode([
                'submissionId' => (int)($submission['id'] ?? 0),
                'clientId' => $clientId,
                'notes' => $notes
            ]),
            'clientId' => $clientId,
            'subject' => $subject,
            'message' => $message,
            'isRead' => 0,
            'readAt' => null,
            'createdAt' => $now
        ]);
    }

    private function sendResubmitClientNotification($submission, $requestId, $requestedItems, $additionalNotes = null) {
        $clientId = (int)($submission['clientId'] ?? 0);
        if ($clientId <= 0) {
            return;
        }

        $subject = 'Additional information required for KYC';
        $message = 'We need additional information to continue your KYC review.';
        if (!empty($additionalNotes)) {
            $message .= " Notes: {$additionalNotes}";
        }
        $now = date('Y-m-d H:i:s');

        $notificationId = $this->clientNotificationModel->create([
            'clientId' => $clientId,
            'subject' => $subject,
            'message' => $message,
            'priority' => 'high',
            'scheduleType' => 'immediate',
            'status' => 'sent',
            'emailTemplate' => 'kyc_resubmit_request',
            'createdBy' => null,
            'createdAt' => $now,
            'updatedAt' => $now
        ]);

        if (!$notificationId) {
            return;
        }

        $this->clientSystemNotificationModel->create([
            'notificationId' => $notificationId,
            'type' => 'kyc_resubmit_required',
            'metadata' => json_encode([
                'submissionId' => (int)($submission['id'] ?? 0),
                'clientId' => $clientId,
                'requestId' => (int)$requestId,
                'items' => $requestedItems,
                'notes' => $additionalNotes
            ]),
            'clientId' => $clientId,
            'subject' => $subject,
            'message' => $message,
            'isRead' => 0,
            'readAt' => null,
            'createdAt' => $now
        ]);
    }
}
