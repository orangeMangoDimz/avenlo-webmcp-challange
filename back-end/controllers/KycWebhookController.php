<?php
/**
 * KYC Webhook Controller
 *
 * 处理第三方 KYC（Sumsub 等）的回调。
 * 路由不走 AuthMiddleware —— 来源 IP 来自 Sumsub 服务器，靠 HMAC 验签判定可信。
 */

require_once __DIR__ . '/../models/ClientKycSubmission.php';
require_once __DIR__ . '/../models/ExternalKycGateway.php';
require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../models/KycSubmissionActivityLog.php';
require_once __DIR__ . '/../models/KycTimeline.php';
require_once __DIR__ . '/../models/KycWebhookCallbackLog.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/KycStatusMapper.php';
require_once __DIR__ . '/../utils/SumsubStatusMapper.php';

class KycWebhookController
{
    private $submissionModel;
    private $gatewayModel;
    private $clientUserModel;
    private $activityLogModel;
    private $timelineModel;
    private $callbackLogModel;

    public function __construct()
    {
        $this->submissionModel = new ClientKycSubmission();
        $this->gatewayModel = new ExternalKycGateway();
        $this->clientUserModel = new ClientUser();
        $this->activityLogModel = new KycSubmissionActivityLog();
        $this->timelineModel = new KycTimeline();
        $this->callbackLogModel = new KycWebhookCallbackLog();
    }

    /**
     * POST /api/kyc-webhook/sumsub
     *
     * 每次进来都先建一条 kycWebhookCallbackLogs 记录，
     * 随流程推进往同一条上 update（isValid / isProcessed / processResult / errorMessage），
     * 出问题时直接拿 rawPayload 比对。
     */
    public function handleSumsub()
    {
        $rawBody = file_get_contents('php://input');
        if ($rawBody === false) {
            $rawBody = '';
        }

        $headers = $this->getHeaders();
        $signature = $headers['x-payload-digest'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        // 立刻落一条原始记录，后面所有分支都往这条上 update
        $logId = $this->callbackLogModel->create([
            'provider'      => 'sumsub',
            'ip'            => $ip,
            'rawPayload'    => $rawBody,
            'isValid'       => 0,
            'isProcessed'   => 0,
            'processResult' => 'pending',
        ]);

        // 1. 找当前启用的 sumsub 网关
        $gateway = $this->gatewayModel->findByProviderWithSecrets('sumsub');
        if (!$gateway || empty($gateway['isEnabled'])) {
            Logger::error('Sumsub webhook: gateway not enabled, dropping silently');
            $this->finalizeLog($logId, 'failed', 'gateway-disabled');
            $this->respondOk('gateway-disabled');
            return;
        }

        // 2. 验签 —— 没配 secret 不放行
        if (empty($gateway['webhookSecret'])) {
            Logger::error('Sumsub webhook: webhookSecret not configured, refusing');
            $this->finalizeLog($logId, 'failed', 'webhook-secret-missing');
            $this->respondError(401, 'webhook-secret-missing');
            return;
        }

        require_once __DIR__ . '/../services/SumsubService.php';
        try {
            $service = new SumsubService($gateway);
        } catch (Exception $e) {
            Logger::error('Sumsub webhook: failed to build service', ['err' => $e->getMessage()]);
            $this->finalizeLog($logId, 'failed', 'service-init-failed: ' . $e->getMessage());
            $this->respondError(500, 'service-init-failed');
            return;
        }
        if (!$service->verifyWebhookSignature($rawBody, $signature)) {
            Logger::error('Sumsub webhook: invalid signature');
            $this->finalizeLog($logId, 'failed', 'invalid-signature');
            $this->respondError(401, 'invalid-signature');
            return;
        }
        // 签名通过，记一下
        $this->callbackLogModel->update($logId, ['isValid' => 1]);

        // 3. 解析 payload
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            Logger::error('Sumsub webhook: invalid JSON');
            $this->finalizeLog($logId, 'failed', 'invalid-payload');
            $this->respondError(400, 'invalid-payload');
            return;
        }

        $type = $payload['type'] ?? '';
        $applicantId = $payload['applicantId'] ?? '';
        $externalUserId = $payload['externalUserId'] ?? '';
        $reviewStatus = $payload['reviewStatus'] ?? null;
        $reviewAnswer = $payload['reviewResult']['reviewAnswer'] ?? null;

        // 把解析出来的关键字段填进 log
        $this->callbackLogModel->update($logId, [
            'eventType'      => $type,
            'externalUserId' => $externalUserId ?: null,
            'applicantId'    => $applicantId ?: null,
            'reviewStatus'   => $reviewStatus,
            'reviewAnswer'   => $reviewAnswer,
        ]);

        // 4. 找对应的 submission
        $submission = $this->findSubmission($applicantId, $externalUserId);
        if (!$submission) {
            Logger::error('Sumsub webhook: submission not found', [
                'applicantId'    => $applicantId,
                'externalUserId' => $externalUserId,
                'type'           => $type,
            ]);
            $this->finalizeLog($logId, 'failed', 'submission-not-found');
            $this->respondOk('submission-not-found');
            return;
        }
        $this->callbackLogModel->update($logId, ['submissionId' => (int)$submission['id']]);

        // 5a. 第一次拿到 Sumsub 的 applicantId 就写到 externalId 列（拼后台查看 URL 用）。
        //     注意 providerApplicantId 那一列存的是我们的 uuid，不要覆盖。
        if (empty($submission['externalId']) && !empty($applicantId)) {
            try {
                $this->submissionModel->update($submission['id'], [
                    'externalId' => $applicantId,
                ]);
                $submission['externalId'] = $applicantId;
            } catch (Exception $e) {
                Logger::error('Sumsub webhook: failed to write externalId', [
                    'submissionId' => $submission['id'],
                    'applicantId' => $applicantId,
                    'err' => $e->getMessage(),
                ]);
            }
        }

        // 5b. 写一条 activity log（不管 status 变不变，方便排查）
        $this->activityLogModel->logActivity(
            $submission['id'],
            'external_webhook',
            sprintf(
                'Sumsub %s (reviewStatus=%s, answer=%s)',
                $type ?: 'unknown',
                $reviewStatus ?? '',
                $reviewAnswer ?? '-'
            ),
            null,
            $payload
        );

        // 6. 映射 webhook → 本地 status
        $newStatus = SumsubStatusMapper::mapToSubmissionStatus($payload);

        // 7. 时间线：只要是我们能映射的事件就写一条。
        //    注意 kycTimeline.eventType 是 ENUM（固定枚举值），
        //    自定义 'external_*' 会被 MySQL 拒掉，所以这里映射到已有 enum。
        //    原始 Sumsub event type 保留在 eventDescription / eventData(JSON) 里供排查。
        if ($newStatus !== null) {
            $timelineEventType = $this->mapToTimelineEventType($newStatus, $type);
            if ($timelineEventType) {
                try {
                    $this->timelineModel->addEvent(
                        $submission['id'],
                        $submission['clientId'],
                        $timelineEventType,
                        $this->timelineTitleFor($newStatus, $type),
                        $this->timelineDescriptionFor($newStatus, $type),
                        $payload,
                        null,
                        'completed'
                    );
                } catch (Exception $e) {
                    Logger::error('Sumsub webhook: failed to add timeline event', ['err' => $e->getMessage()]);
                }
            }
        }

        // 8. status 没变 → 仅记录、不更新 submission
        if ($newStatus === null || $newStatus === $submission['submissionStatus']) {
            $this->finalizeLog($logId, 'ignored', null);
            $this->respondOk('no-status-change');
            return;
        }

        // 9. 更新 submission
        $now = date('Y-m-d H:i:s');
        $updateData = ['submissionStatus' => $newStatus];

        if ($newStatus === 'pending' && empty($submission['submittedAt'])) {
            $updateData['submittedAt'] = $now;
        }
        if ($newStatus === 'approved' || $newStatus === 'rejected') {
            $updateData['reviewedAt'] = $now;
        }
        if ($newStatus === 'rejected') {
            $reason = SumsubStatusMapper::extractRejectionReason($payload);
            if ($reason) {
                $updateData['rejectionReason'] = $reason;
            }
        }

        try {
            $this->submissionModel->update($submission['id'], $updateData);
        } catch (Exception $e) {
            Logger::error('Sumsub webhook: update submission failed', ['err' => $e->getMessage()]);
            $this->finalizeLog($logId, 'failed', 'update-submission-failed: ' . $e->getMessage());
            $this->respondOk('update-submission-failed');
            return;
        }

        // 10. 同步 clientUsers.kycStatus
        $clientKycStatus = KycStatusMapper::templateStatusToClientStatus($newStatus);
        if ($clientKycStatus) {
            try {
                $this->clientUserModel->update($submission['clientId'], [
                    'kycStatus' => $clientKycStatus,
                ]);
            } catch (Exception $e) {
                Logger::error('Sumsub webhook: update clientUser kycStatus failed', ['err' => $e->getMessage()]);
            }
        }

        $this->finalizeLog($logId, 'success', null);
        $this->respondOk();
    }

    /**
     * 完结 log 行（不管成功/失败/忽略都过这里），让 processedAt 一定有值
     */
    private function finalizeLog($logId, $processResult, $errorMessage)
    {
        if (!$logId) return;
        $update = [
            'isProcessed'   => 1,
            'processResult' => $processResult,
            'processedAt'   => date('Y-m-d H:i:s'),
        ];
        if ($errorMessage !== null) {
            // VARCHAR(500) 上限，超了截一下
            $update['errorMessage'] = substr($errorMessage, 0, 500);
        }
        try {
            $this->callbackLogModel->update($logId, $update);
        } catch (Exception $e) {
            // 落日志失败兜底用 error log
            Logger::error('Sumsub webhook: failed to finalize callback log', [
                'logId' => $logId,
                'err'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * 找 submission：按 webhook 里的 externalUserId 匹配 clientKycSubmissions.providerApplicantId
     * （那列存的就是我们生成的 uuid，传给 Sumsub 当 userId 用的）
     */
    private function findSubmission($applicantId, $externalUserId)
    {
        if (!empty($externalUserId)) {
            $rows = $this->submissionModel->findAll(['providerApplicantId' => $externalUserId]);
            if (!empty($rows)) {
                return $rows[0];
            }
        }
        return null;
    }

    /**
     * 把 header key 转小写，方便不区分大小写读
     */
    private function getHeaders()
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                $headers[strtolower($k)] = $v;
            }
        } else {
            foreach ($_SERVER as $k => $v) {
                if (strpos($k, 'HTTP_') === 0) {
                    $name = strtolower(str_replace('_', '-', substr($k, 5)));
                    $headers[$name] = $v;
                }
            }
        }
        return $headers;
    }

    /**
     * 把 Sumsub 事件 / 本地新状态映射到 kycTimeline.eventType 这个 ENUM 上。
     * ENUM 定义在 kyc_change.sql：application_started / personal_info_submitted /
     * financial_info_submitted / documents_uploaded / application_submitted /
     * under_review / additional_documents_requested / review_completed /
     * approved / rejected / expired / resubmit_required / progress_updated
     *
     * 返回 null 表示这个事件不写时间线（理论上不会进到这里，map 失败兜底）。
     */
    private function mapToTimelineEventType($newStatus, $eventType)
    {
        // Sumsub 那个明确"等用户补传文档"语义和 enum 的 additional_documents_requested 对得上
        if ($eventType === 'applicantAwaitingUser') {
            return 'additional_documents_requested';
        }
        switch ($newStatus) {
            case 'pending':           return 'under_review';   // applicantPending / applicantAwaitingService
            case 'under_review':      return 'under_review';   // applicantOnHold
            case 'approved':          return 'approved';
            case 'rejected':          return 'rejected';
            case 'resubmit_required': return 'resubmit_required';
        }
        return null;
    }

    /**
     * 根据本地 status + Sumsub 原始事件类型，给出时间线标题。
     * 同样落在 pending 上的几种 Sumsub 事件，标题不同，让客户能看出阶段。
     * 文案不对客户暴露"第三方/Sumsub"语义，统一以"Verification"出现。
     */
    private function timelineTitleFor($status, $eventType = null)
    {
        // applicantOnHold 和 applicantPending、applicantAwaitingService 都合并到 under_review，
        // 这里只针对真正需要区分的 applicantAwaitingUser（需要用户操作）单独显示
        if ($eventType === 'applicantAwaitingUser') {
            return 'Awaiting documents from you';
        }
        switch ($status) {
            case 'pending':           return 'Verification in progress';
            case 'under_review':      return 'Verification under review';
            case 'approved':          return 'Verification approved';
            case 'rejected':          return 'Verification rejected';
            case 'resubmit_required': return 'Verification needs more documents';
        }
        return 'Verification update';
    }

    /**
     * 时间线 description（给客户看）。不暴露 provider 名字、不带技术 status code，
     * 只给一句易懂的进度说明。原始 webhook payload 仍存在 eventData JSON 里供 admin 查。
     */
    private function timelineDescriptionFor($status, $eventType = null)
    {
        if ($eventType === 'applicantAwaitingUser') {
            return 'Please upload the additional documents requested.';
        }
        switch ($status) {
            case 'pending':           return 'We have received your submission and are processing it.';
            case 'under_review':      return 'Your documents are being reviewed.';
            case 'approved':          return 'Your verification has been approved.';
            case 'rejected':          return 'Your verification did not pass review.';
            case 'resubmit_required': return 'Additional documents are required to complete your verification.';
        }
        return 'Your verification status has been updated.';
    }

    private function respondOk($reason = null)
    {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'reason' => $reason]);
    }

    private function respondError($code, $reason)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'reason' => $reason]);
    }
}
