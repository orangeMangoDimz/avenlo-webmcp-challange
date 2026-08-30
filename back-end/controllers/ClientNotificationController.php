<?php
/**
 * 客户通知控制器
 */

require_once __DIR__ . '/../models/ClientNotification.php';
require_once __DIR__ . '/../models/ClientNotificationDelivery.php';
require_once __DIR__ . '/../models/ClientSystemNotification.php';
require_once __DIR__ . '/../models/EmailTemplate.php';
require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/EmailSender.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class ClientNotificationController {
    private $notificationModel;
    private $deliveryModel;
    private $systemNotificationModel;
    /** @var EmailTemplate 邮件模板统一来自后台 Email Templates 与 Email Settings */
    private $emailTemplateModel;
    private $clientModel;
    private $emailSender;
    private $db;

    public function __construct() {
        $this->notificationModel = new ClientNotification();
        $this->deliveryModel = new ClientNotificationDelivery();
        $this->systemNotificationModel = new ClientSystemNotification();
        $this->emailTemplateModel = new EmailTemplate();
        $this->clientModel = new ClientUser();
        $this->emailSender = new EmailSender();
        $this->db = Database::getInstance();
    }

    /**
     * 根据 templateKey 从 emailTemplates 表解析邮件模板（与后台 Email Setting 一致）
     * 返回统一结构：['subject' => ..., 'body' => ..., 'isActive' => ...]，供 buildEmailPayload 与校验使用
     */
    private function resolveEmailTemplateByKey(string $templateKey): ?array {
        $key = trim($templateKey);
        if ($key === '') {
            return null;
        }
        $row = $this->emailTemplateModel->getByKey($key);
        if ($row === null) {
            return null;
        }
        return [
            'subject' => $row['emailSubject'] ?? $row['subject'] ?? '',
            'body' => $row['emailBody'] ?? $row['body'] ?? '',
            'isActive' => (int)($row['isActive'] ?? 1)
        ];
    }

    /**
     * 创建并发送/计划通知
     * POST /api/client-notifications
     */
    public function create() {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = new Validator($input, [
            'clientId' => 'required|numeric',
            'subject' => 'required',
            'message' => 'required',
            'scheduleType' => 'required|in:immediate,scheduled',
            'priority' => 'in:low,normal,high,urgent'
        ]);

        if (!$validator->validate()) {
            $this->failNotificationSingle(
                $input,
                0,
                OperationLogTextHelpers::validationErrorsToMessage($validator->getErrors())
            );
            Response::validationError($validator->getErrors());
        }

        $sendSystem = filter_var($input['sendSystemNotification'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sendEmail = filter_var($input['sendEmail'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$sendSystem && !$sendEmail) {
            $this->failNotificationSingle($input, 0, 'At least one notification channel must be selected.');
            Response::validationError([
                'channels' => 'At least one notification channel must be selected.'
            ]);
        }

        $scheduleType = $input['scheduleType'];
        $scheduledAt = null;
        $now = date('Y-m-d H:i:s');

        if ($scheduleType === 'scheduled') {
            if (empty($input['scheduledAt'])) {
                $this->failNotificationSingle($input, 0, 'Scheduled time is required for scheduled notifications.');
                Response::validationError(['scheduledAt' => 'Scheduled time is required for scheduled notifications.']);
            }

            $scheduledAt = $this->normalizeDateTime($input['scheduledAt']);

            if (!$scheduledAt) {
                $this->failNotificationSingle($input, 0, 'Invalid scheduled time format.');
                Response::validationError(['scheduledAt' => 'Invalid scheduled time format.']);
            }

            // 使用 UTC 时区来比较时间，因为 scheduledAt 是 UTC 时间
            try {
                $scheduledDateTime = new \DateTime($scheduledAt, new \DateTimeZone('UTC'));
                $nowDateTime = new \DateTime('now', new \DateTimeZone('UTC'));

                if ($scheduledDateTime <= $nowDateTime) {
                $this->failNotificationSingle($input, 0, 'Scheduled time must be in the future.');
                Response::validationError(['scheduledAt' => 'Scheduled time must be in the future.']);
                }
            } catch (\Exception $e) {
                error_log("Failed to validate scheduled time: {$scheduledAt}, Error: " . $e->getMessage());
                $this->failNotificationSingle($input, 0, 'Invalid scheduled time format.');
                Response::validationError(['scheduledAt' => 'Invalid scheduled time format.']);
            }
        }

        $client = $this->clientModel->findById($input['clientId']);
        if (!$client) {
            $this->failNotificationSingle($input, 0, 'Client not found.');
            Response::notFound('Client not found.');
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        $emailTemplateKey = isset($input['emailTemplate']) ? trim($input['emailTemplate']) : null;
        $selectedTemplate = null;
        if ($emailTemplateKey !== null && $emailTemplateKey !== '') {
            $selectedTemplate = $this->resolveEmailTemplateByKey($emailTemplateKey);

            if (!$selectedTemplate) {
                $this->failNotificationSingle($input, (int) $input['clientId'], 'Selected email template does not exist.');
                Response::validationError(['emailTemplate' => 'Selected email template does not exist.']);
            }

            if (isset($selectedTemplate['isActive']) && (int)$selectedTemplate['isActive'] !== 1) {
                $this->failNotificationSingle($input, (int) $input['clientId'], 'Selected email template is not active.');
                Response::validationError(['emailTemplate' => 'Selected email template is not active.']);
            }
        }

        $safeSubject = trim($input['subject']);
        $rawMessage = trim($input['message']);

        $notificationData = [
            'clientId' => (int)$input['clientId'],
            'subject' => $safeSubject,
            'message' => $rawMessage,
            'priority' => $input['priority'] ?? 'normal',
            'scheduleType' => $scheduleType,
            'scheduledAt' => $scheduledAt,
            'status' => $scheduleType === 'immediate' ? 'sending' : 'pending',
            'emailTemplate' => $emailTemplateKey,
            'createdBy' => $adminId,
            'createdAt' => $now,
            'updatedAt' => $now
        ];

        $deliveriesResponse = [];
        $successfulDeliveries = 0;

        $connection = $this->db;
        $pdo = $connection->getConnection();

        try {
            $connection->beginTransaction();

            $notificationId = $this->notificationModel->create($notificationData);

            if ($sendSystem) {
                $deliveryData = [
                    'notificationId' => $notificationId,
                    'channel' => 'system',
                    'status' => $scheduleType === 'immediate' ? 'sending' : 'pending',
                    'createdAt' => $now
                ];
                $deliveryId = $this->deliveryModel->create($deliveryData);

                $deliveryResult = [
                    'id' => $deliveryId,
                    'channel' => 'system',
                    'status' => $deliveryData['status'],
                    'sentAt' => null,
                    'errorMessage' => null
                ];

                if ($scheduleType === 'immediate') {
                    try {
                        $result = $this->deliverSystemNotification($notificationId, $client, $safeSubject, $rawMessage);
                        $this->deliveryModel->update($deliveryId, [
                            'status' => $result['status'],
                            'sentAt' => $result['sentAt'],
                            'errorMessage' => null
                        ]);
                        $deliveryResult['status'] = $result['status'];
                        $deliveryResult['sentAt'] = $result['sentAt'];
                        $successfulDeliveries++;
                    } catch (Exception $e) {
                        $errorMessage = $e->getMessage();
                        $this->deliveryModel->update($deliveryId, [
                            'status' => 'failed',
                            'errorMessage' => $errorMessage
                        ]);
                        $deliveryResult['status'] = 'failed';
                        $deliveryResult['errorMessage'] = $errorMessage;
                    }
                }

                $deliveriesResponse[] = $deliveryResult;
            }

            if ($sendEmail) {
                $deliveryData = [
                    'notificationId' => $notificationId,
                    'channel' => 'email',
                    'status' => $scheduleType === 'immediate' ? 'sending' : 'pending',
                    'createdAt' => $now
                ];
                $deliveryId = $this->deliveryModel->create($deliveryData);

                $deliveryResult = [
                    'id' => $deliveryId,
                    'channel' => 'email',
                    'status' => $deliveryData['status'],
                    'sentAt' => null,
                    'errorMessage' => null
                ];

                if ($scheduleType === 'immediate') {
                    $emailPayload = $this->buildEmailPayload(
                        $client,
                        $safeSubject,
                        $rawMessage,
                        $selectedTemplate
                    );

                    try {
                        $sendResult = $this->emailSender->send(
                            $client['email'],
                            $emailPayload['subject'],
                            $emailPayload['body']
                        );

                        if ($sendResult) {
                            $this->deliveryModel->update($deliveryId, [
                                'status' => 'sent',
                                'sentAt' => $now,
                                'errorMessage' => null
                            ]);
                            $deliveryResult['status'] = 'sent';
                            $deliveryResult['sentAt'] = $now;
                            $successfulDeliveries++;
                        } else {
                            $errorMessage = 'Email sending failed. Please check email configuration.';
                            $this->deliveryModel->update($deliveryId, [
                                'status' => 'failed',
                                'errorMessage' => $errorMessage
                            ]);
                            $deliveryResult['status'] = 'failed';
                            $deliveryResult['errorMessage'] = $errorMessage;
                        }
                    } catch (Exception $e) {
                        $errorMessage = $e->getMessage();
                        $this->deliveryModel->update($deliveryId, [
                            'status' => 'failed',
                            'errorMessage' => $errorMessage
                        ]);
                        $deliveryResult['status'] = 'failed';
                        $deliveryResult['errorMessage'] = $errorMessage;
                    }
                }

                $deliveriesResponse[] = $deliveryResult;
            }

            $finalStatus = $scheduleType === 'immediate'
                ? ($successfulDeliveries > 0 ? 'sent' : 'failed')
                : 'pending';

            $this->notificationModel->update($notificationId, [
                'status' => $finalStatus,
                'updatedAt' => $now
            ]);

            $connection->commit();

            $subModule = OperationLogPages::resolveLogClient($input, OperationLogPages::subModuleKeyByAlias('page_leads'));
            $opLog = new AdminOperationLogWriter();
            $channelsZh = AdminOperationLogWriter::formatNotificationChannelsZh($sendSystem, $sendEmail);
            $channelsEn = AdminOperationLogWriter::formatNotificationChannelsEn($sendSystem, $sendEmail);
            $scheduleZh = AdminOperationLogWriter::formatScheduleDescZh($scheduleType, $scheduledAt);
            $scheduleEn = AdminOperationLogWriter::formatScheduleDescEn($scheduleType, $scheduledAt);
            $opLog->logClientNotificationSingle(
                $subModule,
                (int) $input['clientId'],
                AdminOperationLogWriter::formatClientDisplayName($client),
                $channelsZh,
                $channelsEn,
                $scheduleZh,
                $scheduleEn
            );

            Response::success([
                'notificationId' => $notificationId,
                'status' => $finalStatus,
                'scheduleType' => $scheduleType,
                'scheduledAt' => $scheduledAt,
                'deliveries' => $deliveriesResponse
            ], $scheduleType === 'immediate'
                ? ($successfulDeliveries > 0 ? 'Notification sent successfully.' : 'Notification sending failed.')
                : 'Notification scheduled successfully.');

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $connection->rollback();
            }
            $this->failNotificationSingle(
                $input,
                (int) ($input['clientId'] ?? 0),
                'Failed to create notification: ' . $e->getMessage()
            );
            Response::error('Failed to create notification: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 批量创建并发送/计划通知（内部复用单条逻辑）
     * POST /api/client-notifications/bulk
     */
    public function createBulk() {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($input['clientIds']) || !is_array($input['clientIds'])) {
            $this->failNotificationBulk($input, 'clientIds must be a non-empty array.');
            Response::validationError(['clientIds' => 'clientIds must be a non-empty array.']);
        }

        $validator = new Validator($input, [
            'subject' => 'required',
            'message' => 'required',
            'scheduleType' => 'required|in:immediate,scheduled',
            'priority' => 'in:low,normal,high,urgent'
        ]);

        if (!$validator->validate()) {
            $this->failNotificationBulk(
                $input,
                OperationLogTextHelpers::validationErrorsToMessage($validator->getErrors())
            );
            Response::validationError($validator->getErrors());
        }

        $sendSystem = filter_var($input['sendSystemNotification'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sendEmail = filter_var($input['sendEmail'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$sendSystem && !$sendEmail) {
            $this->failNotificationBulk($input, 'At least one notification channel must be selected.');
            Response::validationError([
                'channels' => 'At least one notification channel must be selected.'
            ]);
        }

        $scheduleType = $input['scheduleType'];
        $scheduledAt = null;

        if ($scheduleType === 'scheduled') {
            if (empty($input['scheduledAt'])) {
                $this->failNotificationBulk($input, 'Scheduled time is required for scheduled notifications.');
                Response::validationError(['scheduledAt' => 'Scheduled time is required for scheduled notifications.']);
            }
            $scheduledAt = $this->normalizeDateTime($input['scheduledAt']);
            if (!$scheduledAt) {
                $this->failNotificationBulk($input, 'Invalid scheduled time format.');
                Response::validationError(['scheduledAt' => 'Invalid scheduled time format.']);
            }
            try {
                $scheduledDateTime = new \DateTime($scheduledAt, new \DateTimeZone('UTC'));
                $nowDateTime = new \DateTime('now', new \DateTimeZone('UTC'));
                if ($scheduledDateTime <= $nowDateTime) {
                    $this->failNotificationBulk($input, 'Scheduled time must be in the future.');
                    Response::validationError(['scheduledAt' => 'Scheduled time must be in the future.']);
                }
            } catch (\Exception $e) {
                $this->failNotificationBulk($input, 'Invalid scheduled time format.');
                Response::validationError(['scheduledAt' => 'Invalid scheduled time format.']);
            }
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        $emailTemplateKey = isset($input['emailTemplate']) ? trim($input['emailTemplate']) : null;
        $selectedTemplate = null;
        if ($emailTemplateKey !== null && $emailTemplateKey !== '') {
            $selectedTemplate = $this->resolveEmailTemplateByKey($emailTemplateKey);
            if (!$selectedTemplate) {
                $this->failNotificationBulk($input, 'Selected email template does not exist.');
                Response::validationError(['emailTemplate' => 'Selected email template does not exist.']);
            }
            if (isset($selectedTemplate['isActive']) && (int)$selectedTemplate['isActive'] !== 1) {
                $this->failNotificationBulk($input, 'Selected email template is not active.');
                Response::validationError(['emailTemplate' => 'Selected email template is not active.']);
            }
        }

        $safeSubject = trim($input['subject']);
        $rawMessage = trim($input['message']);
        $priority = $input['priority'] ?? 'normal';

        $successCount = 0;
        $failCount = 0;
        $results = [];

        foreach ($input['clientIds'] as $index => $clientId) {
            $clientId = (int) $clientId;
            if ($clientId <= 0) {
                $failCount++;
                $results[] = ['clientId' => $clientId, 'success' => false, 'error' => 'Invalid client id.'];
                continue;
            }

            $oneResult = $this->processOneNotification($clientId, [
                'subject' => $safeSubject,
                'message' => $rawMessage,
                'scheduleType' => $scheduleType,
                'scheduledAt' => $scheduledAt,
                'priority' => $priority,
                'sendSystemNotification' => $sendSystem,
                'sendEmail' => $sendEmail,
                'emailTemplateKey' => $emailTemplateKey,
                'selectedTemplate' => $selectedTemplate,
                'adminId' => $adminId,
            ]);

            if ($oneResult['success']) {
                $successCount++;
                $results[] = ['clientId' => $clientId, 'success' => true, 'notificationId' => $oneResult['notificationId']];
            } else {
                $failCount++;
                $results[] = ['clientId' => $clientId, 'success' => false, 'error' => $oneResult['error']];
            }
        }

        $subModule = OperationLogPages::resolveLogClient($input, OperationLogPages::subModuleKeyByAlias('page_leads'));
        $clientIds = array_values(array_unique(array_map('intval', $input['clientIds'])));
        $channelsZh = AdminOperationLogWriter::formatNotificationChannelsZh($sendSystem, $sendEmail);
        $channelsEn = AdminOperationLogWriter::formatNotificationChannelsEn($sendSystem, $sendEmail);
        $scheduleZh = AdminOperationLogWriter::formatScheduleDescZh($scheduleType, $scheduledAt);
        $scheduleEn = AdminOperationLogWriter::formatScheduleDescEn($scheduleType, $scheduledAt);
        (new AdminOperationLogWriter())->logClientNotificationBulk(
            $subModule,
            $clientIds,
            $channelsZh,
            $channelsEn,
            $scheduleZh,
            $scheduleEn,
            $successCount,
            $failCount
        );

        Response::success([
            'successCount' => $successCount,
            'failCount' => $failCount,
            'results' => $results
        ], "Bulk notification finished. Sent: {$successCount}, Failed: {$failCount}.");
    }

    /**
     * 单条通知的创建与发送（供 create 与 createBulk 复用）
     * @param int $clientId
     * @param array $params subject, message, scheduleType, scheduledAt, priority, sendSystemNotification, sendEmail, emailTemplateKey, selectedTemplate, adminId
     * @return array ['success' => bool, 'notificationId' => int|null, 'error' => string|null]
     */
    private function processOneNotification($clientId, array $params) {
        $client = $this->clientModel->findById($clientId);
        if (!$client) {
            return ['success' => false, 'notificationId' => null, 'error' => 'Client not found.'];
        }

        $now = date('Y-m-d H:i:s');
        $notificationData = [
            'clientId' => (int) $clientId,
            'subject' => $params['subject'],
            'message' => $params['message'],
            'priority' => $params['priority'] ?? 'normal',
            'scheduleType' => $params['scheduleType'],
            'scheduledAt' => $params['scheduledAt'],
            'status' => $params['scheduleType'] === 'immediate' ? 'sending' : 'pending',
            'emailTemplate' => $params['emailTemplateKey'],
            'createdBy' => $params['adminId'],
            'createdAt' => $now,
            'updatedAt' => $now
        ];

        $connection = $this->db;
        $pdo = $connection->getConnection();

        try {
            $connection->beginTransaction();

            $notificationId = $this->notificationModel->create($notificationData);
            $successfulDeliveries = 0;
            $sendSystem = $params['sendSystemNotification'];
            $sendEmail = $params['sendEmail'];
            $scheduleType = $params['scheduleType'];
            $selectedTemplate = $params['selectedTemplate'] ?? null;

            if ($sendSystem) {
                $deliveryData = [
                    'notificationId' => $notificationId,
                    'channel' => 'system',
                    'status' => $scheduleType === 'immediate' ? 'sending' : 'pending',
                    'createdAt' => $now
                ];
                $deliveryId = $this->deliveryModel->create($deliveryData);

                if ($scheduleType === 'immediate') {
                    try {
                        $result = $this->deliverSystemNotification($notificationId, $client, $params['subject'], $params['message']);
                        $this->deliveryModel->update($deliveryId, [
                            'status' => $result['status'],
                            'sentAt' => $result['sentAt'],
                            'errorMessage' => null
                        ]);
                        $successfulDeliveries++;
                    } catch (Exception $e) {
                        $this->deliveryModel->update($deliveryId, [
                            'status' => 'failed',
                            'errorMessage' => $e->getMessage()
                        ]);
                    }
                }
            }

            if ($sendEmail) {
                $deliveryData = [
                    'notificationId' => $notificationId,
                    'channel' => 'email',
                    'status' => $scheduleType === 'immediate' ? 'sending' : 'pending',
                    'createdAt' => $now
                ];
                $deliveryId = $this->deliveryModel->create($deliveryData);

                if ($scheduleType === 'immediate') {
                    $emailPayload = $this->buildEmailPayload(
                        $client,
                        $params['subject'],
                        $params['message'],
                        $selectedTemplate
                    );
                    try {
                        $sendResult = $this->emailSender->send(
                            $client['email'],
                            $emailPayload['subject'],
                            $emailPayload['body']
                        );
                        if ($sendResult) {
                            $this->deliveryModel->update($deliveryId, [
                                'status' => 'sent',
                                'sentAt' => $now,
                                'errorMessage' => null
                            ]);
                            $successfulDeliveries++;
                        } else {
                            $this->deliveryModel->update($deliveryId, [
                                'status' => 'failed',
                                'errorMessage' => 'Email sending failed.'
                            ]);
                        }
                    } catch (Exception $e) {
                        $this->deliveryModel->update($deliveryId, [
                            'status' => 'failed',
                            'errorMessage' => $e->getMessage()
                        ]);
                    }
                }
            }

            $finalStatus = $scheduleType === 'immediate'
                ? ($successfulDeliveries > 0 ? 'sent' : 'failed')
                : 'pending';

            $this->notificationModel->update($notificationId, [
                'status' => $finalStatus,
                'updatedAt' => $now
            ]);

            $connection->commit();
            return [
                'success' => true,
                'notificationId' => $notificationId,
                'status' => $finalStatus,
                'deliveries' => [],
                'error' => null
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $connection->rollback();
            }
            return ['success' => false, 'notificationId' => null, 'status' => null, 'deliveries' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * TODO: 定时任务处理接口
     * 该接口将在后续集成定时任务时实现，负责轮询 scheduledAt <= NOW() 的通知并发送。
     */
    public function processDue() {
        Response::success([
            'message' => 'Scheduled notification processing endpoint is not implemented yet. Pending task runner integration.'
        ]);
    }

    /**
     * 构建邮件主题与正文
     */
    private function buildEmailPayload(array $client, string $subject, string $message, ?array $template = null): array {
        $finalSubject = $this->replacePlaceholders($subject, $client, $message, false);
        $safeMessageHtml = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $finalBody = '<p>' . $safeMessageHtml . '</p>';

        if ($template) {
            $templateSubject = $template['subject'] ?? $finalSubject;
            $finalSubject = $this->replacePlaceholders($templateSubject, $client, $message, false);

            $templateBody = $template['body'] ?? '';
            if ($templateBody !== '') {
                $finalBody = $this->replacePlaceholders($templateBody, $client, $message, true);

                if (strpos($templateBody, '{{message}}') === false) {
                    $finalBody .= '<p>' . $safeMessageHtml . '</p>';
                }
            }
        }

        return [
            'subject' => $finalSubject,
            'body' => $finalBody
        ];
    }

    /**
     * 发送系统通知
     */
    private function deliverSystemNotification(int $notificationId, array $client, string $subject, string $message): array {
        $safeMessage = $this->sanitizeSystemMessage($message);
        $recordId = $this->systemNotificationModel->create([
            'notificationId' => $notificationId,
            'clientId' => $client['id'],
            'subject' => $subject,
            'message' => $safeMessage,
            'isRead' => 0,
            'createdAt' => date('Y-m-d H:i:s')
        ]);

        return [
            'status' => 'sent',
            'sentAt' => date('Y-m-d H:i:s'),
            'recordId' => $recordId
        ];
    }

    /**
     * 占位符替换
     */
    private function replacePlaceholders(string $text, array $client, string $message, bool $isHtml): string {
        $fullName = trim(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? ''));

        $replacements = [
            '{{firstName}}' => $client['firstName'] ?? '',
            '{{lastName}}' => $client['lastName'] ?? '',
            '{{fullName}}' => $fullName,
            '{{email}}' => $client['email'] ?? '',
            '{{clientId}}' => $client['id'] ?? '',
            '{{now}}' => date('Y-m-d H:i'),
        ];

        if ($isHtml) {
            $replacements['{{message}}'] = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        } else {
            $replacements['{{message}}'] = trim(preg_replace('/\s+/', ' ', $message));
        }

        return strtr($text, $replacements);
    }

    /**
     * 系统通知内容去除多余HTML
     */
    private function sanitizeSystemMessage(string $message): string {
        // 保留基础换行
        $message = strip_tags($message);
        return preg_replace('/\s+/', ' ', $message);
    }

    /**
     * 归一化日期时间字符串
     */
    private function normalizeDateTime(?string $value): ?string {
        if (empty($value)) {
            return null;
        }

        try {
            // 尝试解析 ISO 8601 格式（如：2025-12-04T17:21:00.000Z 或 2025-12-04T17:21:00+08:00）
            $dateTime = null;

            // 首先尝试使用 DateTime 解析 ISO 8601 格式
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)) {
                // ISO 8601 格式（带或不带时区）
                $dateTime = new \DateTime($value);
            } else {
                // 兼容旧格式：YYYY-MM-DD HH:MM:SS（假设为服务器本地时区，需要转换为 UTC）
                $dateTime = new \DateTime($value, new \DateTimeZone(date_default_timezone_get()));
            }

            // 转换为 UTC 时区
            $dateTime->setTimezone(new \DateTimeZone('UTC'));

            // 返回 MySQL datetime 格式（UTC）
            return $dateTime->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            error_log("Failed to parse datetime: {$value}, Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 将 MySQL datetime 格式（UTC）转换为 ISO 8601 格式
     * 这样前端 JavaScript 的 new Date() 能正确解析为 UTC 时间
     */
    private function convertToISO8601(?string $datetime): ?string {
        if (empty($datetime)) {
            return null;
        }

        try {
            // 将 MySQL datetime 格式（UTC）解析为 DateTime 对象
            $dateTime = new \DateTime($datetime, new \DateTimeZone('UTC'));
            // 转换为 ISO 8601 格式（带 Z 后缀表示 UTC）
            return $dateTime->format('Y-m-d\TH:i:s\Z');
        } catch (\Exception $e) {
            error_log("Failed to convert datetime to ISO8601: {$datetime}, Error: " . $e->getMessage());
            return $datetime; // 如果转换失败，返回原值
        }
    }
    /**
     * 客户端：获取通知列表
     */
    public function listForClient() {
        $clientId = $this->getCurrentClientId();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int)($_GET['perPage'] ?? 5);
        $perPage = min(max($perPage, 1), 50);
        $offset = ($page - 1) * $perPage;

        list($items, $total) = $this->systemNotificationModel->getPaginatedByClient($clientId, $perPage, $offset);

        $notifications = array_map(function ($item) {
            return [
                'id' => (int)$item['id'],
                'notificationId' => (int)$item['notificationId'],
                'type' => $item['type'] ?? 'common',  // 默认为'common'
                'metadata' => $item['metadata'] ? json_decode($item['metadata'], true) : null,
                'subject' => $item['subject'],
                'message' => $item['message'],
                'isRead' => (bool)$item['isRead'],
                'readAt' => $this->convertToISO8601($item['readAt']),
                'createdAt' => $this->convertToISO8601($item['createdAt'])
            ];
        }, $items);

        $unreadCount = $this->systemNotificationModel->getUnreadCount($clientId);

        Response::success([
            'items' => $notifications,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'hasMore' => ($offset + count($notifications)) < $total
            ],
            'unreadCount' => $unreadCount
        ]);
    }

    /**
     * 客户端：标记通知为已读
     */
    public function markReadForClient() {
        $clientId = $this->getCurrentClientId();
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];

        $ids = [];
        if (isset($payload['id'])) {
            $ids[] = (int)$payload['id'];
        }
        if (isset($payload['ids']) && is_array($payload['ids'])) {
            foreach ($payload['ids'] as $id) {
                $ids[] = (int)$id;
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));

        if (empty($ids)) {
            Response::error('No notification id provided', 400);
        }

        $updated = $this->systemNotificationModel->markAsReadByIds($ids, $clientId);
        $unreadCount = $this->systemNotificationModel->getUnreadCount($clientId);

        Response::success([
            'updated' => $updated,
            'unreadCount' => $unreadCount
        ]);
    }

    /**
     * 客户端：标记全部通知为已读
     */
    public function markAllReadForClient() {
        $clientId = $this->getCurrentClientId();
        $updated = $this->systemNotificationModel->markAllAsRead($clientId);

        Response::success([
            'updated' => $updated
        ]);
    }

    /**
     * 获取当前客户端用户ID（支持预览 X-Preview-Token 与 JWT）
     */
    private function getCurrentClientId() {
        $userId = ClientAuthContext::getCurrentClientUserId();
        if ($userId !== null) {
            return $userId;
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        if (!$currentUser || ($currentUser['type'] ?? '') !== 'client') {
            Response::forbidden('Only client users can access this resource');
        }

        return $currentUser['userId'];
    }

    private function failNotificationSingle(array $input, int $clientId, string $message) {
        $subModule = OperationLogPages::resolveLogClient($input, OperationLogPages::subModuleKeyByAlias('page_leads'));
        (new AdminOperationLogWriter())->logClientNotificationSingle(
            $subModule,
            $clientId,
            '',
            '—',
            '—',
            '—',
            '—',
            false,
            $message
        );
    }

    private function failNotificationBulk(array $input, string $message) {
        $subModule = OperationLogPages::resolveLogClient($input, OperationLogPages::subModuleKeyByAlias('page_leads'));
        (new AdminOperationLogWriter())->logClientNotificationBulk(
            $subModule,
            [],
            '—',
            '—',
            '—',
            '—',
            0,
            0,
            false,
            $message
        );
    }
}
