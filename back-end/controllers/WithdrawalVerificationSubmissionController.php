<?php
/**
 * Withdrawal Verification Submission Controller
 */

require_once __DIR__ . '/../models/ClientWithdrawalVerificationSubmission.php';
require_once __DIR__ . '/../models/ClientWithdrawalVerificationAnswer.php';
require_once __DIR__ . '/../models/ClientWithdrawalVerificationDocumentSignature.php';
require_once __DIR__ . '/../models/WithdrawalVerificationActivityLog.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplate.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestion.php';
require_once __DIR__ . '/../models/WithdrawalVerificationConditionalRule.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTimeline.php';
require_once __DIR__ . '/../models/WithdrawalVerificationResubmitRequest.php';
require_once __DIR__ . '/../models/WithdrawalVerificationResubmitAnswer.php';
require_once __DIR__ . '/../models/ClientNotification.php';
require_once __DIR__ . '/../models/ClientSystemNotification.php';
require_once __DIR__ . '/../models/AdminNotification.php';
require_once __DIR__ . '/../models/AdminNotificationDelivery.php';
require_once __DIR__ . '/../models/AdminSystemNotification.php';
require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../utils/S3Uploader.php';
require_once __DIR__ . '/../utils/UploadedFilePayload.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/AdminUser.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';

class WithdrawalVerificationSubmissionController {
    private $submissionModel;
    private $answerModel;
    private $signatureModel;
    private $activityLogModel;
    private $templateModel;
    private $questionModel;
    private $ruleModel;
    private $timelineModel;
    private $resubmitRequestModel;
    private $resubmitAnswerModel;
    private $clientNotificationModel;
    private $clientSystemNotificationModel;
    private $adminNotificationModel;
    private $adminNotificationDeliveryModel;
    private $adminSystemNotificationModel;
    private $clientUserModel;

    public function __construct() {
        $this->submissionModel = new ClientWithdrawalVerificationSubmission();
        $this->answerModel = new ClientWithdrawalVerificationAnswer();
        $this->signatureModel = new ClientWithdrawalVerificationDocumentSignature();
        $this->activityLogModel = new WithdrawalVerificationActivityLog();
        $this->templateModel = new WithdrawalVerificationTemplate();
        $this->questionModel = new WithdrawalVerificationQuestion();
        $this->ruleModel = new WithdrawalVerificationConditionalRule();
        $this->timelineModel = new WithdrawalVerificationTimeline();
        $this->resubmitRequestModel = new WithdrawalVerificationResubmitRequest();
        $this->resubmitAnswerModel = new WithdrawalVerificationResubmitAnswer();
        $this->clientNotificationModel = new ClientNotification();
        $this->clientSystemNotificationModel = new ClientSystemNotification();
        $this->adminNotificationModel = new AdminNotification();
        $this->adminNotificationDeliveryModel = new AdminNotificationDelivery();
        $this->adminSystemNotificationModel = new AdminSystemNotification();
        $this->clientUserModel = new ClientUser();
    }

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
        if (isset($_GET['gateway_setting_id'])) {
            $filters['gatewaySettingId'] = $_GET['gateway_setting_id'];
        }
        if (isset($_GET['client_id'])) {
            $filters['clientId'] = $_GET['client_id'];
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_addressverification');
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

    public function getPending() {
        $limit = $_GET['limit'] ?? 50;
        $pending = $this->submissionModel->getPendingReviews($limit);

        Response::success([
            'submissions' => $pending,
            'total' => count($pending)
        ]);
    }

    public function show($id) {
        $submission = $this->submissionModel->getSubmissionProgress($id);
        if (!$submission) {
            Response::notFound('Submission not found');
        }

        $fullSubmission = $this->submissionModel->findById($id);
        if ($fullSubmission) {
            $submission['rejectionReason'] = $fullSubmission['rejectionReason'] ?? null;
            $submission['approvalNotes'] = $fullSubmission['approvalNotes'] ?? null;
            $submission['gatewaySettingId'] = $fullSubmission['gatewaySettingId'] ?? ($submission['gatewaySettingId'] ?? null);
            $submission['paymentMethodId'] = $fullSubmission['paymentMethodId'] ?? ($submission['paymentMethodId'] ?? null);
        }

        $answers = $this->answerModel->getSubmissionAnswers($id);
        $submission['answers'] = $this->groupAnswersByCategory($answers);
        $submission['signatures'] = $this->signatureModel->getSubmissionSignatures($id);
        $submission['activities'] = $this->activityLogModel->getSubmissionActivities($id, 20);
        $submission['timeline'] = $this->timelineModel->getSubmissionTimeline($id);

        Response::success($submission);
    }

    public function create() {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $currentUser = AuthMiddleware::getCurrentUser();
        $clientId = (int)($currentUser['userId'] ?? 0);

        if ($clientId <= 0) {
            Response::unauthorized('Invalid current user');
        }

        if (empty($input['templateId'])) {
            Response::validationError([
                'templateId' => 'templateId is required'
            ]);
        }

        $template = $this->templateModel->findById((int)$input['templateId']);
        if (!$template) {
            Response::notFound('Template not found');
        }
        if (($template['status'] ?? null) !== 'active') {
            Response::error('Template not found or inactive', 400);
        }

        $data = [
            'clientId' => $clientId,
            'templateId' => (int)$input['templateId'],
            'gatewaySettingId' => (int)$template['gatewaySettingId'],
            'paymentMethodId' => isset($input['paymentMethodId']) && $input['paymentMethodId'] !== ''
                ? (int)$input['paymentMethodId']
                : null,
            'submissionStatus' => 'draft',
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];

        try {
            $submissionId = $this->submissionModel->create($data);

            $this->activityLogModel->logActivity(
                $submissionId,
                'submission_created',
                'Withdrawal verification submission started'
            );

            $this->timelineModel->addEvent(
                $submissionId,
                $clientId,
                'draft',
                'Draft Created',
                'Withdrawal verification submission has been created',
                null,
                null,
                'current'
            );

            $submission = $this->submissionModel->getSubmissionProgress($submissionId);
            Response::created($submission, 'Withdrawal submission created successfully');
        } catch (Exception $e) {
            Response::error('Failed to create withdrawal submission: ' . $e->getMessage(), 500);
        }
    }

    // 客户软删除自己某条已保存地址：只对客户隐藏，admin 仍可见
    public function hide($id) {
        $submission = $this->submissionModel->findById($id);
        if (!$submission) {
            Response::notFound('Submission not found');
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $clientId = (int)($currentUser['userId'] ?? 0);
        // 只能隐藏自己的记录
        if ($clientId <= 0 || (int)$submission['clientId'] !== $clientId) {
            Response::forbidden('You can only hide your own withdrawal address');
        }

        try {
            $this->submissionModel->hideForClient($id);

            $this->activityLogModel->logActivity(
                $id,
                'submission_client_hidden',
                'Client hid the withdrawal address from their list'
            );

            Response::success(null, 'Withdrawal address hidden successfully');
        } catch (Exception $e) {
            Response::error('Failed to hide withdrawal address: ' . $e->getMessage(), 500);
        }
    }

    public function saveAnswers($id) {
        $submission = $this->submissionModel->findById($id);
        if (!$submission) {
            Response::notFound('Submission not found');
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!isset($input['answers']) || !is_array($input['answers'])) {
            Response::validationError(['answers' => 'Answers array is required']);
        }

        try {
            $isResubmitPayload = $this->isResubmitAnswersPayload($input['answers']);

            if ($submission['submissionStatus'] === 'resubmit_required' && $isResubmitPayload) {
                $request = $this->resubmitRequestModel->getLatestRequest($id);
                if (!$request) {
                    Response::error('Resubmit request not found', 404);
                }

                $saved = $this->resubmitAnswerModel->saveAnswers($request['id'], $id, $input['answers']);
                $this->resubmitRequestModel->markAsCompleted($request['id']);
                $this->submissionModel->updateStatus($id, 'under_review', null);

                $this->activityLogModel->logActivity(
                    $id,
                    'resubmit_answers_saved',
                    "{$saved} resubmit answers saved"
                );

                Response::success([
                    'saved' => $saved,
                    'message' => "{$saved} resubmit answers saved successfully"
                ]);
            }

            $normalizedAnswers = $this->normalizeSubmissionAnswers($input['answers']);
            $saved = $this->answerModel->saveAnswersBatch($id, $normalizedAnswers);

            if ($saved > 0 && in_array($submission['submissionStatus'], ['draft', 'resubmit_required'], true)) {
                $this->submissionModel->update($id, [
                    'submissionStatus' => 'incomplete'
                ]);
            }

            $this->activityLogModel->logActivity(
                $id,
                'answers_saved',
                "{$saved} answers saved"
            );

            Response::success([
                'saved' => $saved,
                'message' => "{$saved} answers saved successfully"
            ]);
        } catch (Exception $e) {
            Response::error('Failed to save answers: ' . $e->getMessage(), 500);
        }
    }

    public function signDocuments($id) {
        $submission = $this->submissionModel->findById($id);
        if (!$submission) {
            Response::notFound('Submission not found');
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
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

            $this->activityLogModel->logActivity(
                $id,
                'documents_signed',
                count($results) . ' documents signed'
            );

            Response::success([
                'signed' => count($results),
                'message' => count($results) . ' documents signed successfully'
            ]);
        } catch (Exception $e) {
            Response::error('Failed to sign documents: ' . $e->getMessage(), 500);
        }
    }

    public function submit($id) {
        $submission = $this->submissionModel->findById($id);
        if (!$submission) {
            Response::notFound('Submission not found');
        }

        if (!in_array($submission['submissionStatus'], ['draft', 'incomplete', 'resubmit_required'], true)) {
            Response::error('Only draft, incomplete, or resubmit_required submissions can be submitted', 400);
        }

        $template = $this->templateModel->findById($submission['templateId']);
        if (!$template) {
            Response::notFound('Template not found');
        }

        $missingRequiredQuestions = $this->getMissingRequiredQuestionIds($submission['templateId'], $id);
        if (!empty($missingRequiredQuestions)) {
            Response::error('Please answer all required questions before submitting', 400, [
                'missingQuestionIds' => $missingRequiredQuestions
            ]);
        }

        if (!empty($template['requireDocumentSignature'])) {
            $hasSignedAll = $this->signatureModel->hasSignedAllRequired($id, $submission['templateId']);
            if (!$hasSignedAll) {
                Response::error('Please sign all required documents before submitting', 400);
            }
        }

        try {
            $this->submissionModel->submitVerification($id);

            $this->activityLogModel->logActivity(
                $id,
                'submitted',
                'Withdrawal verification submitted for review'
            );

            $this->timelineModel->addEvent(
                $id,
                (int)$submission['clientId'],
                'submitted',
                'Submitted',
                'Withdrawal verification form submitted for review',
                null,
                null,
                'current'
            );

            if (!empty($template['isAutoApproveEnabled'])) {
                $this->submissionModel->approve($id, null, 'Auto-approved');
                $this->activityLogModel->logActivity(
                    $id,
                    'approved',
                    'Automatically approved'
                );
                $this->timelineModel->updateCurrentEventToApproved($id, null, 'Auto-approved');
            } else {
                try {
                    $this->notifyAdminsOfWithdrawalVerificationSubmission($submission);
                } catch (Exception $e) {
                    // Admin notice failure should not block submission.
                }
            }

            $updatedSubmission = $this->submissionModel->getSubmissionProgress($id);
            Response::success($updatedSubmission, 'Withdrawal verification submitted successfully');
        } catch (Exception $e) {
            Response::error('Failed to submit: ' . $e->getMessage(), 500);
        }
    }

    public function approve($id) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogAddressVerification($input);
        $opLog = new AdminOperationLogWriter();

        $submission = $this->submissionModel->findById($id);
        if (!$submission) {
            $opLog->logAddressVerificationApprove($subModule, 0, (int) $id, '', null, false, 'Submission not found');
            Response::notFound('Submission not found');
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;
        $notes = $input['notes'] ?? null;
        $clientId = (int) ($submission['clientId'] ?? 0);

        // 草稿尚未提交，不允许审批
        if (($submission['submissionStatus'] ?? null) === 'draft') {
            $opLog->logAddressVerificationApprove($subModule, $clientId, (int) $id, '', null, false, 'Cannot approve draft submission');
            Response::error('Draft submissions cannot be approved', 400);
        }

        try {
            $this->submissionModel->approve($id, $adminId, $notes);
            $this->timelineModel->updateCurrentEventToApproved($id, $adminId, $notes);
            $this->timelineModel->deletePendingEvents($id);
            $this->activityLogModel->logActivity(
                $id,
                'approved',
                'Withdrawal verification approved' . ($notes ? ": {$notes}" : ''),
                $adminId
            );

            try {
                $this->sendClientVerificationOutcomeNotification($submission, 'approved', $notes);
            } catch (Exception $e) {
                // 通知失败不应该影响主流程
            }

            $updatedSubmission = $this->submissionModel->getSubmissionProgress($id);
            $gatewayName = $this->resolveSubmissionGatewayName($id, $updatedSubmission);

            $opLog->logAddressVerificationApprove(
                $subModule,
                $clientId,
                (int) $id,
                $gatewayName,
                $notes
            );

            Response::success($updatedSubmission, 'Withdrawal verification approved successfully');
        } catch (Exception $e) {
            $opLog->logAddressVerificationApprove(
                $subModule,
                $clientId,
                (int) $id,
                $this->resolveSubmissionGatewayName($id),
                null,
                false,
                'Failed to approve: ' . $e->getMessage()
            );
            Response::error('Failed to approve: ' . $e->getMessage(), 500);
        }
    }

    public function reject($id) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogAddressVerification($input);
        $opLog = new AdminOperationLogWriter();

        $submission = $this->submissionModel->findById($id);
        if (!$submission) {
            $opLog->logAddressVerificationReject($subModule, 0, (int) $id, '', '', false, 'Submission not found');
            Response::notFound('Submission not found');
        }

        $clientId = (int) ($submission['clientId'] ?? 0);

        if (empty($input['reason'])) {
            $opLog->logAddressVerificationReject(
                $subModule,
                $clientId,
                (int) $id,
                $this->resolveSubmissionGatewayName($id),
                '',
                false,
                'Rejection reason is required'
            );
            Response::validationError(['reason' => 'Rejection reason is required']);
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;
        $rejectionReason = $input['reason'];

        try {
            $this->submissionModel->reject($id, $adminId, $rejectionReason);
            $this->timelineModel->updateCurrentEventToRejected($id, $rejectionReason, $adminId);
            $this->timelineModel->deletePendingEvents($id);
            $this->activityLogModel->logActivity(
                $id,
                'rejected',
                "Withdrawal verification rejected: {$rejectionReason}",
                $adminId,
                ['rejectionReason' => $rejectionReason]
            );

            try {
                $this->sendClientVerificationOutcomeNotification($submission, 'rejected', $rejectionReason);
            } catch (Exception $e) {
                // 通知失败不应该影响主流程
            }

            $updatedSubmission = $this->submissionModel->getSubmissionProgress($id);
            $gatewayName = $this->resolveSubmissionGatewayName($id, $updatedSubmission);

            $opLog->logAddressVerificationReject(
                $subModule,
                $clientId,
                (int) $id,
                $gatewayName,
                $rejectionReason
            );

            Response::success($updatedSubmission, 'Withdrawal verification rejected');
        } catch (Exception $e) {
            $opLog->logAddressVerificationReject(
                $subModule,
                $clientId,
                (int) $id,
                $this->resolveSubmissionGatewayName($id),
                '',
                false,
                'Failed to reject: ' . $e->getMessage()
            );
            Response::error('Failed to reject: ' . $e->getMessage(), 500);
        }
    }

    public function needDocs($id) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogAddressVerification($input);
        $opLog = new AdminOperationLogWriter();

        $submission = $this->submissionModel->findById($id);
        if (!$submission) {
            $opLog->logAddressVerificationNeedDocs($subModule, 0, (int) $id, '', 0, false, 'Submission not found');
            Response::notFound('Submission not found');
        }

        $clientId = (int) ($submission['clientId'] ?? 0);

        if (empty($input['items']) || !is_array($input['items'])) {
            $opLog->logAddressVerificationNeedDocs(
                $subModule,
                $clientId,
                (int) $id,
                $this->resolveSubmissionGatewayName($id),
                0,
                false,
                'At least one item is required'
            );
            Response::validationError(['items' => 'At least one item is required']);
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;
        $requestedItems = $input['items'];
        $additionalNotes = $input['notes'] ?? null;

        try {
            $requestId = $this->resubmitRequestModel->createRequest(
                $id,
                (int)$submission['clientId'],
                $adminId,
                $requestedItems,
                $additionalNotes
            );

            $this->submissionModel->updateStatus($id, 'resubmit_required', $adminId);

            $this->timelineModel->updateCurrentEventToResubmitRequired(
                $id,
                (int)$submission['clientId'],
                'Resubmission Required',
                'Please update the requested items and resubmit',
                [
                    'requestedBy' => $adminId,
                    'requestId' => $requestId,
                    'items' => $requestedItems,
                    'notes' => $additionalNotes
                ],
                $adminId
            );
            $this->timelineModel->deletePendingEvents($id);

            $this->activityLogModel->logActivity(
                $id,
                'resubmit_required',
                'Resubmission requested',
                $adminId,
                ['requestId' => $requestId, 'items' => $requestedItems]
            );

            try {
                $this->sendClientResubmitRequiredNotification($submission, $requestId, $requestedItems, $additionalNotes);
            } catch (Exception $e) {
                // 通知失败不应该影响主流程
            }

            $updatedSubmission = $this->submissionModel->getSubmissionProgress($id);
            $gatewayName = $this->resolveSubmissionGatewayName($id, $updatedSubmission);

            $opLog->logAddressVerificationNeedDocs(
                $subModule,
                $clientId,
                (int) $id,
                $gatewayName,
                count($requestedItems)
            );

            Response::success($updatedSubmission, 'Resubmission request sent successfully');
        } catch (Exception $e) {
            $opLog->logAddressVerificationNeedDocs(
                $subModule,
                $clientId,
                (int) $id,
                $this->resolveSubmissionGatewayName($id),
                0,
                false,
                'Failed to request resubmission: ' . $e->getMessage()
            );
            Response::error('Failed to request resubmission: ' . $e->getMessage(), 500);
        }
    }

    public function assign($id) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogAddressVerification($input);
        $opLog = new AdminOperationLogWriter();

        $submission = $this->submissionModel->findById($id);
        if (!$submission) {
            $opLog->logAddressVerificationAssign($subModule, 0, (int) $id, '', '', false, 'Submission not found');
            Response::notFound('Submission not found');
        }

        $clientId = (int) ($submission['clientId'] ?? 0);
        $reviewerId = $input['reviewerId'] ?? $input['assigneeId'] ?? null;

        if (empty($reviewerId)) {
            $opLog->logAddressVerificationAssign(
                $subModule,
                $clientId,
                (int) $id,
                $this->resolveSubmissionGatewayName($id),
                '',
                false,
                'Reviewer ID is required'
            );
            Response::validationError(['reviewerId' => 'Reviewer ID is required']);
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        try {
            $this->submissionModel->assignReviewer($id, $reviewerId);
            $this->activityLogModel->logActivity(
                $id,
                'assigned',
                "Assigned to reviewer ID: {$reviewerId}",
                $adminId
            );

            $updatedSubmission = $this->submissionModel->getSubmissionProgress($id);
            $gatewayName = $this->resolveSubmissionGatewayName($id, $updatedSubmission);
            $reviewerName = $this->resolveReviewerName($reviewerId);

            $opLog->logAddressVerificationAssign(
                $subModule,
                $clientId,
                (int) $id,
                $gatewayName,
                $reviewerName
            );

            Response::success($updatedSubmission, 'Reviewer assigned successfully');
        } catch (Exception $e) {
            $opLog->logAddressVerificationAssign(
                $subModule,
                $clientId,
                (int) $id,
                $this->resolveSubmissionGatewayName($id),
                '',
                false,
                'Failed to assign reviewer: ' . $e->getMessage()
            );
            Response::error('Failed to assign reviewer: ' . $e->getMessage(), 500);
        }
    }

    public function statistics() {
        $templateId = $_GET['template_id'] ?? null;
        $gatewaySettingId = $_GET['gateway_setting_id'] ?? null;
        $stats = $this->submissionModel->getSubmissionStatistics($templateId, $gatewaySettingId);

        $formatted = [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0
        ];

        foreach ($stats as $stat) {
            $status = $stat['submissionStatus'];
            $count = (int)$stat['count'];
            $formatted['total'] += $count;

            if ($status === 'approved') {
                $formatted['approved'] += $count;
            } elseif ($status === 'rejected') {
                $formatted['rejected'] += $count;
            } else {
                $formatted['pending'] += $count;
            }
        }

        Response::success($formatted);
    }

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

    public function evaluateRules($id) {
        $submission = $this->submissionModel->findById($id);
        if (!$submission) {
            Response::notFound('Submission not found');
        }

        $answers = $this->answerModel->getSubmissionAnswers($id);
        $answerMap = [];
        foreach ($answers as $answer) {
            $answerMap[$answer['questionId']] = $this->answerModel->getAnswerValue($answer);
        }

        $actions = $this->ruleModel->evaluateTemplateRules($submission['templateId'], $answerMap);
        Response::success([
            'actions' => $actions,
            'count' => count($actions)
        ]);
    }

    public function getResubmitRequest($id) {
        $submission = $this->submissionModel->findById($id);
        if (!$submission) {
            Response::notFound('Submission not found');
        }

        $request = $this->resubmitRequestModel->getLatestRequest($id);
        if (!$request) {
            Response::success(null, 'No resubmit request found');
        }

        Response::success($this->resubmitRequestModel->getRequestWithItems($request['id']));
    }

    public function getResubmitAnswers($id) {
        $submission = $this->submissionModel->findById($id);
        if (!$submission) {
            Response::notFound('Submission not found');
        }

        $request = $this->resubmitRequestModel->getLatestRequest($id);
        if (!$request) {
            Response::success([
                'answers' => [],
                'total' => 0
            ]);
        }

        $answers = $this->resubmitAnswerModel->getRequestAnswers($request['id']);
        foreach ($answers as &$answer) {
            if (($answer['questionType'] ?? null) === 'file_upload') {
                $files = UploadedFilePayload::normalizeForResponse($answer['uploadedFiles'] ?? [], true);
                $answer['uploadedFiles'] = $files;
                $answer['files'] = $files;
                $answer['value'] = $files;
            }
        }

        Response::success([
            'answers' => $answers,
            'total' => count($answers)
        ]);
    }

    public function uploadFile($submissionId) {
        $currentUser = AuthMiddleware::getCurrentUser();
        $clientId = (int)($currentUser['userId'] ?? 0);

        if (($currentUser['type'] ?? '') !== 'client' || $clientId <= 0) {
            Response::unauthorized('Client authentication required');
        }

        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission) {
            Response::notFound('Submission not found');
        }

        if ((int)($submission['clientId'] ?? 0) !== $clientId) {
            Response::forbidden('Access denied');
        }

        if (!isset($_FILES['file']) || !isset($_POST['questionId'])) {
            Response::validationError([
                'file' => 'file is required',
                'questionId' => 'questionId is required'
            ]);
        }

        $file = $_FILES['file'];
        $questionId = (int)$_POST['questionId'];

        if ($questionId <= 0) {
            Response::validationError([
                'questionId' => 'questionId must be a positive integer'
            ]);
        }

        $question = $this->questionModel->findById($questionId);
        if (!$question || (int)($question['templateId'] ?? 0) !== (int)($submission['templateId'] ?? 0)) {
            Response::error('Invalid question for this submission', 400);
        }

        if (($question['questionType'] ?? null) !== 'file_upload') {
            Response::error('Question is not a file upload field', 400);
        }

        $maxSize = 5 * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxSize) {
            Response::error('File size exceeds 5MB limit', 400);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($file['type'] ?? '', $allowedTypes, true)) {
            Response::error('Invalid file type. Only JPG, PNG, and PDF are allowed', 400);
        }

        try {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'withdraw_verification_q' . $questionId . '_' . $submissionId . '_' . time() . '_' . uniqid() . ($extension ? '.' . $extension : '');

            $s3Uploader = new S3Uploader();
            $s3Key = $s3Uploader->generateS3Key($filename, 'withdraw');
            $uploadResult = $s3Uploader->uploadFile(
                $file['tmp_name'],
                $s3Key,
                $file['type']
            );

            if (empty($uploadResult['success'])) {
                Response::error('Failed to upload file to S3: ' . ($uploadResult['error'] ?? 'Unknown error'), 500);
            }

            $uploadedFile = [
                'fileName' => $file['name'],
                'fileSize' => (int)$file['size'],
                'mimeType' => $file['type'],
                'filePath' => $uploadResult['url'],
                's3Key' => $s3Key,
                's3Url' => $uploadResult['url'],
                'uploadedAt' => date('Y-m-d H:i:s')
            ];

            $existingAnswer = $this->answerModel->findOne([
                'submissionId' => $submissionId,
                'questionId' => $questionId
            ]);

            if ($existingAnswer) {
                $existingFiles = UploadedFilePayload::normalizeForStorage($existingAnswer['uploadedFiles'] ?? []);
                $existingFiles[] = $uploadedFile;
                $this->answerModel->update($existingAnswer['id'], [
                    'uploadedFiles' => json_encode(UploadedFilePayload::normalizeForStorage($existingFiles))
                ]);
            } else {
                $this->answerModel->create([
                    'submissionId' => $submissionId,
                    'questionId' => $questionId,
                    'uploadedFiles' => json_encode([$uploadedFile])
                ]);
            }

            $this->activityLogModel->logActivity(
                $submissionId,
                'file_uploaded',
                'Withdrawal verification file uploaded',
                null,
                [
                    'clientId' => $clientId,
                    'questionId' => $questionId,
                    'fileName' => $file['name'],
                    's3Key' => $s3Key
                ]
            );

            Response::success(
                UploadedFilePayload::normalizeForResponse([$uploadedFile], true)[0],
                'File uploaded successfully'
            );
        } catch (Exception $e) {
            Response::error('Failed to upload file: ' . $e->getMessage(), 500);
        }
    }

    public function uploadResubmitFile($submissionId) {
        $currentUser = AuthMiddleware::getCurrentUser();
        $clientId = (int)($currentUser['userId'] ?? 0);

        if (($currentUser['type'] ?? '') !== 'client' || $clientId <= 0) {
            Response::unauthorized('Client authentication required');
        }

        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission) {
            Response::notFound('Submission not found');
        }

        if ((int)($submission['clientId'] ?? 0) !== $clientId) {
            Response::forbidden('Access denied');
        }

        if (($submission['submissionStatus'] ?? null) !== 'resubmit_required') {
            Response::error('Submission is not in resubmit_required status', 400);
        }

        if (!isset($_FILES['file'])) {
            Response::validationError([
                'file' => 'file is required'
            ]);
        }

        $file = $_FILES['file'];
        $itemIndex = isset($_POST['itemIndex']) ? (int)$_POST['itemIndex'] : null;

        if ($itemIndex === null || $itemIndex < 0) {
            Response::validationError([
                'itemIndex' => 'itemIndex is required'
            ]);
        }

        $maxSize = 5 * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxSize) {
            Response::error('File size exceeds 5MB limit', 400);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($file['type'] ?? '', $allowedTypes, true)) {
            Response::error('Invalid file type. Only JPG, PNG, and PDF are allowed', 400);
        }

        try {
            $request = $this->resubmitRequestModel->getLatestRequest($submissionId);
            if (!$request) {
                Response::error('Resubmit request not found', 404);
            }

            $requestedItems = json_decode($request['requestedItems'] ?? '[]', true);
            if (!isset($requestedItems[$itemIndex])) {
                Response::error('Invalid item index', 400);
            }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'withdraw_resubmit_' . $submissionId . '_item' . $itemIndex . '_' . time() . '_' . uniqid() . ($extension ? '.' . $extension : '');

            $s3Uploader = new S3Uploader();
            $s3Key = $s3Uploader->generateS3Key($filename, 'withdraw');
            $uploadResult = $s3Uploader->uploadFile(
                $file['tmp_name'],
                $s3Key,
                $file['type']
            );

            if (empty($uploadResult['success'])) {
                Response::error('Failed to upload file to S3: ' . ($uploadResult['error'] ?? 'Unknown error'), 500);
            }

            $uploadedFile = [
                'fileName' => $file['name'],
                'fileSize' => (int)$file['size'],
                'mimeType' => $file['type'],
                'filePath' => $uploadResult['url'],
                's3Key' => $s3Key,
                's3Url' => $uploadResult['url'],
                'uploadedAt' => date('Y-m-d H:i:s'),
                'itemIndex' => $itemIndex
            ];

            Response::success(
                UploadedFilePayload::normalizeForResponse([$uploadedFile], true)[0],
                'File uploaded successfully'
            );
        } catch (Exception $e) {
            Response::error('Failed to upload resubmit file: ' . $e->getMessage(), 500);
        }
    }

    private function getMissingRequiredQuestionIds($templateId, $submissionId) {
        $questions = $this->questionModel->getTemplateQuestions($templateId, true);
        $answers = $this->answerModel->getSubmissionAnswers($submissionId);
        $answerMap = [];

        foreach ($answers as $answer) {
            $answerMap[(int)$answer['questionId']] = $this->answerModel->getAnswerValue($answer);
        }

        $missing = [];
        foreach ($questions as $question) {
            if ((int)($question['isRequired'] ?? 0) !== 1) {
                continue;
            }

            $questionId = (int)$question['id'];
            if (!array_key_exists($questionId, $answerMap)) {
                $missing[] = $questionId;
                continue;
            }

            $value = $answerMap[$questionId];
            if ($value === null || $value === '' || (is_array($value) && count($value) === 0)) {
                $missing[] = $questionId;
            }
        }

        return $missing;
    }

    private function groupAnswersByCategory($answers) {
        $grouped = [];

        foreach ($answers as $answer) {
            $categoryId = (int)($answer['categoryId'] ?? 0);
            if (!isset($grouped[$categoryId])) {
                $grouped[$categoryId] = [
                    'categoryId' => $categoryId,
                    'categoryName' => $answer['categoryName'] ?? null,
                    'answers' => []
                ];
            }

            $answer['value'] = $this->answerModel->getAnswerValue($answer);
            if (($answer['questionType'] ?? null) === 'file_upload') {
                $answer['files'] = $this->answerModel->getNormalizedUploadedFiles($answer['uploadedFiles'] ?? [], true);
                $answer['value'] = $answer['files'];
            }
            $grouped[$categoryId]['answers'][] = $answer;
        }

        return array_values($grouped);
    }

    private function isResubmitAnswersPayload($answers) {
        foreach ($answers as $answer) {
            if (array_key_exists('questionId', $answer)) {
                return false;
            }

            if (array_key_exists('itemId', $answer) || array_key_exists('questionText', $answer) || array_key_exists('answerText', $answer)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeSubmissionAnswers($answers) {
        $normalized = [];

        foreach ($answers as $index => $answer) {
            $questionId = $answer['questionId'] ?? $answer['itemId'] ?? null;
            $questionType = $answer['questionType'] ?? $answer['type'] ?? null;

            if ($questionId === null || (int)$questionId <= 0) {
                Response::validationError([
                    "answers.{$index}.questionId" => 'questionId or itemId is required and must be a positive integer'
                ]);
            }

            if (empty($questionType)) {
                Response::validationError([
                    "answers.{$index}.questionType" => 'questionType is required'
                ]);
            }

            $normalized[] = [
                'questionId' => (int)$questionId,
                'questionType' => $questionType,
                'answer' => $this->extractAnswerValue($answer, $questionType)
            ];
        }

        return $normalized;
    }

    private function extractAnswerValue($answer, $questionType) {
        if (array_key_exists('answer', $answer)) {
            return $answer['answer'];
        }

        switch ($questionType) {
            case 'multiple_choice':
                return $answer['answerValues'] ?? [];
            case 'file_upload':
                return $answer['uploadedFiles'] ?? [];
            case 'number':
                return $answer['answerNumber'] ?? null;
            case 'date':
                return $answer['answerDate'] ?? null;
            default:
                return $answer['answerText'] ?? null;
        }
    }

    private function sendClientVerificationOutcomeNotification($submission, $status, $details = null) {
        $clientId = (int)($submission['clientId'] ?? 0);
        if ($clientId <= 0) {
            return;
        }

        $submissionId = (int)($submission['id'] ?? 0);
        $now = date('Y-m-d H:i:s');

        if ($status === 'approved') {
            $subject = 'Your withdrawal verification was approved';
            $message = 'Your withdrawal verification has been approved.';
            if (!empty($details)) {
                $message .= " Notes: {$details}";
            }
            $type = 'withdrawal_verification_approved';
        } else {
            $subject = 'Your withdrawal verification was rejected';
            $message = 'Your withdrawal verification was rejected.';
            if (!empty($details)) {
                $message .= " Reason: {$details}";
            }
            $type = 'withdrawal_verification_rejected';
        }

        $notificationId = $this->clientNotificationModel->create([
            'clientId' => $clientId,
            'subject' => $subject,
            'message' => $message,
            'priority' => 'high',
            'scheduleType' => 'immediate',
            'status' => 'sent',
            'emailTemplate' => null,
            'createdBy' => null,
            'createdAt' => $now,
            'updatedAt' => $now
        ]);

        if (!$notificationId) {
            return;
        }

        $this->clientSystemNotificationModel->create([
            'notificationId' => $notificationId,
            'type' => $type,
            'metadata' => json_encode([
                'submissionId' => $submissionId,
                'clientId' => $clientId,
                'status' => $status,
                'details' => $details
            ]),
            'clientId' => $clientId,
            'subject' => $subject,
            'message' => $message,
            'isRead' => 0,
            'readAt' => null,
            'createdAt' => $now
        ]);
    }

    private function sendClientResubmitRequiredNotification($submission, $requestId, $requestedItems, $additionalNotes = null) {
        $clientId = (int)($submission['clientId'] ?? 0);
        if ($clientId <= 0) {
            return;
        }

        $subject = 'Additional information required for withdrawal verification';
        $message = 'We need additional information to continue your withdrawal verification review.';
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
            'emailTemplate' => null,
            'createdBy' => null,
            'createdAt' => $now,
            'updatedAt' => $now
        ]);

        if (!$notificationId) {
            return;
        }

        $this->clientSystemNotificationModel->create([
            'notificationId' => $notificationId,
            'type' => 'withdrawal_verification_resubmit_required',
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

    private function notifyAdminsOfWithdrawalVerificationSubmission(array $submission): void
    {
        $clientId = (int)($submission['clientId'] ?? 0);
        if ($clientId <= 0) {
            return;
        }

        $client = $this->clientUserModel->findById($clientId);
        if (!$client) {
            return;
        }

        $adminId = (int)($client['accountManagerId'] ?? 0);
        if ($adminId <= 0) {
            return;
        }

        $clientName = trim(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? ''));
        if ($clientName === '') {
            $clientName = $client['email'] ?? ('Client #' . $clientId);
        }

        $subject = "New Withdrawal Verification Submission from {$clientName}";
        $message = "Client {$clientName} has submitted a withdrawal verification form for review.";
        $metadata = json_encode([
            'submissionId' => (int)($submission['id'] ?? 0),
            'clientId' => $clientId,
            'templateId' => (int)($submission['templateId'] ?? 0),
            'action' => 'view_withdrawal_verification_submission',
            'actionUrl' => '/withdrawal-submissions'
        ]);

        $this->createAdminNotification($adminId, $subject, $message, $metadata, 'withdrawal_verification_submission');
    }

    private function createAdminNotification(int $adminId, string $subject, string $message, string $metadata, string $type): void
    {
        if ($adminId <= 0) {
            return;
        }

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

    private function resolveSubmissionGatewayName($submissionId, $progress = null) {
        if (is_array($progress) && !empty($progress['gatewayName'])) {
            return trim((string) $progress['gatewayName']);
        }
        $row = $this->submissionModel->getSubmissionProgress($submissionId);
        return trim((string) ($row['gatewayName'] ?? ''));
    }

    private function resolveReviewerName($reviewerId) {
        $reviewerModel = new AdminUser();
        $reviewer = $reviewerModel->findById($reviewerId);
        $name = trim((string) ($reviewer['fullName'] ?? $reviewer['username'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        return 'ID:' . (int) $reviewerId;
    }
}
