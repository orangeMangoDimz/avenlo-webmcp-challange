<?php
/**
 * KYC Settings 控制器
 * 管理KYC通知设置、状态消息、邮件模板等
 */

require_once __DIR__ . '/../models/KycNoticeSetting.php';
require_once __DIR__ . '/../models/KycStatusMessageTemplate.php';
require_once __DIR__ . '/../models/KycRequirementItem.php';
require_once __DIR__ . '/../models/KycEmailNotificationTemplate.php';
require_once __DIR__ . '/../models/EmailTemplate.php';
require_once __DIR__ . '/../models/KycSettingsChangeLog.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogTexts/KycOperationLogTexts.php';

class KycSettingsController {
    private $noticeSettingModel;
    private $statusMessageModel;
    private $requirementItemModel;
    private $emailTemplateModel;
    private $changeLogModel;

    public function __construct() {
        $this->noticeSettingModel = new KycNoticeSetting();
        $this->statusMessageModel = new KycStatusMessageTemplate();
        $this->requirementItemModel = new KycRequirementItem();
        // 使用 EmailTemplate 模型（统一邮件模板管理）
        $this->emailTemplateModel = new EmailTemplate();
        $this->changeLogModel = new KycSettingsChangeLog();
    }

    // ============================================================
    // KYC 通知设置
    // ============================================================

    /**
     * 获取 KYC 通知设置
     * GET /api/kyc-settings/notice/{key}
     */
    public function getNoticeSettings($settingKey = 'default_kyc_notice') {

        $settings = $this->noticeSettingModel->getByKey($settingKey);

        if (!$settings) {
            Response::notFound('Notice settings not found');
        }

        // 获取相关的要求项
        $requirements = $this->requirementItemModel->getByNoticeSettingId($settings['id']);
        $settings['requirements'] = $requirements;

        Response::success($settings);
    }

    /**
     * 更新 KYC 通知设置
     * PUT /api/kyc-settings/notice
     */
    public function updateNoticeSettings() {
        $input = json_decode(file_get_contents('php://input'), true);
        $subModule = OperationLogPages::resolveLogKycSettings(is_array($input) ? $input : []);
        $opLog = new AdminOperationLogWriter();

        // 验证
//        $validator = new Validator($input);
//        $validator->required(['settingKey']);
//
//        if (!$validator->validate()) {
//            Response::validationError($validator->getErrors());
//        }
        // 验证输入数据
        if (!$input) {
            list($detailZh, $detailEn) = KycOperationLogTexts::noticeSettingsUpdateFailure('Invalid JSON input');
            $opLog->logKycSettingsMutation($subModule, 'edit', $detailZh, $detailEn);
            Response::error('Invalid JSON input', 400);
            return;
        }

        if (!isset($input['settingKey'])) {
            list($detailZh, $detailEn) = KycOperationLogTexts::noticeSettingsUpdateFailure('settingKey is required');
            $opLog->logKycSettingsMutation($subModule, 'edit', $detailZh, $detailEn);
            Response::error('settingKey is required', 400);
            return;
        }

        $settingKey = $input['settingKey'];
        $existingSetting = $this->noticeSettingModel->getByKey($settingKey);

        if (!$existingSetting) {
            list($detailZh, $detailEn) = KycOperationLogTexts::noticeSettingsUpdateFailure('Notice settings not found');
            $opLog->logKycSettingsMutation($subModule, 'edit', $detailZh, $detailEn);
            Response::notFound('Notice settings not found');
        }

        // 获取当前管理员ID
        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        // 准备更新数据
        $updateData = [];
        $changes = [];

        $fields = [
            'isEnabled', 'noticeTitle', 'noticeSubtitle', 'noticeDescription',
            'requirementsTitle', 'verificationTimeNotice',
            'primaryButtonText', 'primaryButtonAction',
            'secondaryButtonText', 'secondaryButtonAction',
            'displayPosition', 'displayPriority', 'isDismissible',
            'showIcon', 'iconClass', 'backgroundColor', 'borderColor'
        ];

        foreach ($fields as $field) {
            if (isset($input[$field])) {
                $updateData[$field] = $input[$field];

                // 记录更改
                if ($existingSetting[$field] != $input[$field]) {
                    $changes[] = [
                        'settingType' => 'notice_settings',
                        'settingId' => $existingSetting['id'],
                        'fieldName' => $field,
                        'oldValue' => $existingSetting[$field],
                        'newValue' => $input[$field]
                    ];
                }
            }
        }

        if ($adminId) {
            $updateData['updatedBy'] = $adminId;
        }

        // 更新设置
        $success = $this->noticeSettingModel->update($existingSetting['id'], $updateData);

        if ($success) {
            // 记录更改日志
            if (!empty($changes) && $adminId) {
                $this->changeLogModel->logBulkChanges($changes, $adminId);
            }

            if (!empty($changes)) {
                $changedFields = array_values(array_unique(array_column($changes, 'fieldName')));
                $subModule = OperationLogPages::resolveLogKycSettings($input);
                list($detailZh, $detailEn) = KycOperationLogTexts::noticeSettingsUpdate($changedFields);
                (new AdminOperationLogWriter())->logKycSettingsMutation($subModule, 'edit', $detailZh, $detailEn);
            }

            Response::success(null, 'Notice settings updated successfully');
        } else {
            list($detailZh, $detailEn) = KycOperationLogTexts::noticeSettingsUpdateFailure('Failed to update notice settings');
            $opLog->logKycSettingsMutation($subModule, 'edit', $detailZh, $detailEn);
            Response::error('Failed to update notice settings', 500);
        }
    }

    /**
     * 获取客户端通知配置（不需要认证）
     * GET /api/kyc-settings/client-notice
     */
    public function getClientNoticeConfig() {
        $config = $this->noticeSettingModel->getClientNoticeConfig();

        // 优先 X-Preview-Token（预览模式），否则 JWT，保证预览与正常登录返回同一客户的 requirements
        $clientId = ClientAuthContext::getCurrentClientUserId();

        if (!$config) {
            Response::success(null, 'Notice display is disabled');
        }

        // 获取要求项（按当前客户选择模板的 categories）
        $defaultSettings = $this->noticeSettingModel->getDefaultSettings();
        if ($defaultSettings) {
            // 获取客户端可用的KYC模板的Category信息作为requirements
            $config['requirements'] = $this->getKycTemplateCategories($clientId);
        }

        Response::success($config);
    }

    /**
     * 获取客户端可用的KYC模板的Category信息
     * 参考 LeadKycController::getClientTemplate() 的逻辑
     */
    private function getKycTemplateCategories($clientId = null) {
        try {
            // 引入必要的模型
            require_once __DIR__ . '/../models/KycTemplate.php';
            require_once __DIR__ . '/../models/KycQuestionCategory.php';
            require_once __DIR__ . '/../models/ClientUser.php';
            require_once __DIR__ . '/../models/ClientKycSubmission.php';

            $templateModel = new KycTemplate();
            $categoryModel = new KycQuestionCategory();
            $submissionModel = new ClientKycSubmission();
            $clientModel = new ClientUser();

            // 获取用户国家信息（如果有的话）
            $userCountry = null;
            if ($clientId) {
                $client = $clientModel->findById($clientId);
                $userCountry = $client['country'] ?? null;
            }

            // 按优先级选择模板
            $template = null;

            // 如果存在提交记录且仍在进行中，优先使用提交绑定的模板
            if ($clientId) {
                $latestSubmission = $submissionModel->getLatestSubmission($clientId);
                if ($latestSubmission && !empty($latestSubmission['templateId'])) {
                    $status = $latestSubmission['submissionStatus'] ?? 'draft';
                    if (!in_array($status, ['approved', 'rejected'], true)) {
                        $template = $templateModel->findById($latestSubmission['templateId']);
                    }
                }
            }

            // 1. 如果用户有国家信息，优先匹配国家特定模板
            if (!$template && $userCountry) {
                $template = $this->findTemplateByCountry($templateModel, $userCountry);

                // 检查国家特定模板是否有分类数据
                if ($template) {
                    $categories = $categoryModel->getTemplateCategories($template['id'], true);
                    if (empty($categories)) {
                        // 如果国家特定模板没有分类，继续查找其他模板
                        $template = null;
                    }
                }
            }

            // 2. 如果没有国家特定模板或国家模板没有分类，查找全球通用模板
            if (!$template) {
                $template = $this->findGlobalKycTemplate($templateModel);

                // 检查全球模板是否有分类数据
                if ($template) {
                    $categories = $categoryModel->getTemplateCategories($template['id'], true);
                    if (empty($categories)) {
                        // 如果全球模板没有分类，使用默认模板
                        $template = null;
                    }
                }
            }

            // 3. 最后如果全球模板没有分类，使用默认模板
            if (!$template) {
                $template = $this->findDefaultKycTemplate($templateModel);
            }

            if (!$template) {
                return []; // 没有可用模板
            }

            // 获取模板的分类信息
            $categories = $categoryModel->getTemplateCategories($template['id'], true);

            // 格式化为requirements格式
            $requirements = [];
            foreach ($categories as $category) {
                $requirements[] = [
                    'id' => $category['id'],
                    'itemTitle' => $category['categoryName'],
                    'itemDescription' => $category['description'] ?? '',
                    'displayOrder' => $category['displayOrder'] ?? 0,
                    'isRequired' => true // KYC分类通常都是必需的
                ];
            }

            return $requirements;
        } catch (Exception $e) {
            // 如果出现错误，返回空数组而不是让整个接口失败
            error_log("Error getting KYC template categories: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 根据国家查找最匹配的模板
     *
     * @param object $templateModel 模板模型实例
     * @param string $countryCode 国家代码
     * @return array|null
     */
    private function findTemplateByCountry($templateModel, $countryCode) {
        // 查找精确匹配用户国家的活跃模板，按优先级排序
        $sql = "SELECT t.*
                FROM kycTemplates t
                INNER JOIN kycTemplateCountries tc ON t.id = tc.templateId
                WHERE t.status = 'active'
                AND tc.countryCode = :country
                ORDER BY t.displayOrder ASC, t.id ASC
                LIMIT 1";

        return $templateModel->queryOne($sql, ['country' => $countryCode]);
    }

    /**
     * 查找全球通用KYC模板
     */
    private function findGlobalKycTemplate($templateModel) {
        $sql = "SELECT t.*
                FROM kycTemplates t
                INNER JOIN kycTemplateCountries tc ON t.id = tc.templateId
                WHERE t.status = 'active'
                AND tc.countryCode = 'ALL'
                ORDER BY t.displayOrder ASC, t.id ASC
                LIMIT 1";

        return $templateModel->queryOne($sql, []);
    }

    /**
     * 查找默认KYC模板
     */
    private function findDefaultKycTemplate($templateModel) {
        $sql = "SELECT * FROM kycTemplates WHERE status = 'active' ORDER BY displayOrder ASC, id ASC LIMIT 1";
        return $templateModel->queryOne($sql, []);
    }

    // ============================================================
    // 状态消息模板
    // ============================================================

    /**
     * 获取所有状态消息模板
     * GET /api/kyc-settings/status-messages
     */
    public function getStatusMessages() {
        $statusType = $_GET['status_type'] ?? null;
        $activeOnly = isset($_GET['active_only']) && $_GET['active_only'] === 'true';

        if ($statusType) {
            $message = $this->statusMessageModel->getByStatusType($statusType);
            Response::success($message);
        } elseif ($activeOnly) {
            $messages = $this->statusMessageModel->getActiveTemplates();
            Response::success($messages);
        } else {
            $messages = $this->statusMessageModel->findAll([], 'displayOrder ASC');
            Response::success($messages);
        }
    }

    /**
     * 更新状态消息模板
     * PUT /api/kyc-settings/status-messages/{id}
     */
    public function updateStatusMessage($id) {
        $input = json_decode(file_get_contents('php://input'), true);

        // 验证
        $validator = new Validator($input);

        if (isset($input['messageType'])) {
            $validator->in('messageType', ['info', 'success', 'warning', 'error']);
        }

        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }

        $existingMessage = $this->statusMessageModel->findById($id);

        if (!$existingMessage) {
            Response::notFound('Status message template not found');
        }

        // 获取当前管理员ID
        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        // 记录更改
        $changes = [];
        $fields = [
            'messageTitle', 'messageContent', 'messageType',
            'showActionButton', 'actionButtonText', 'actionButtonUrl',
            'iconClass', 'isActive', 'displayOrder'
        ];

        foreach ($fields as $field) {
            if (isset($input[$field]) && $existingMessage[$field] != $input[$field]) {
                $changes[] = [
                    'settingType' => 'status_messages',
                    'settingId' => $id,
                    'fieldName' => $field,
                    'oldValue' => $existingMessage[$field],
                    'newValue' => $input[$field]
                ];
            }
        }

        if ($adminId) {
            $input['updatedBy'] = $adminId;
        }

        $success = $this->statusMessageModel->update($id, $input);

        if ($success) {
            // 记录更改日志
            if (!empty($changes) && $adminId) {
                $this->changeLogModel->logBulkChanges($changes, $adminId);
            }

            Response::success(null, 'Status message updated successfully');
        } else {
            Response::error('Failed to update status message', 500);
        }
    }

    /**
     * 批量更新状态消息
     * PUT /api/kyc-settings/status-messages/bulk
     */
    public function bulkUpdateStatusMessages() {
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input) || !is_array($input)) {
            Response::error('Invalid data format', 400);
        }

        // 获取当前管理员ID
        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        try {
            $success = $this->statusMessageModel->bulkUpdate($input, $adminId);

            if ($success) {
                Response::success(null, 'Status messages updated successfully');
            } else {
                Response::error('Failed to update status messages', 500);
            }
        } catch (Exception $e) {
            Response::error('Failed to update status messages: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取客户端状态消息（不需要认证）
     * GET /api/kyc-settings/client-status-message/{statusType}
     */
    public function getClientStatusMessage($statusType) {
        $message = $this->statusMessageModel->getClientMessage($statusType);

        if (!$message) {
            Response::notFound('Status message not found or inactive');
        }

        Response::success($message);
    }

    // ============================================================
    // 要求项管理
    // ============================================================

    /**
     * 获取要求项列表
     * GET /api/kyc-settings/requirements
     */
    public function getRequirements() {
        $noticeSettingId = $_GET['notice_setting_id'] ?? null;

        if (!$noticeSettingId) {
            // 获取默认通知设置的要求项
            $defaultSettings = $this->noticeSettingModel->getDefaultSettings();
            if ($defaultSettings) {
                $noticeSettingId = $defaultSettings['id'];
            }
        }

        if (!$noticeSettingId) {
            Response::error('Notice setting not found', 404);
        }

        $requirements = $this->requirementItemModel->getByNoticeSettingId($noticeSettingId);

        Response::success($requirements);
    }

    /**
     * 创建要求项
     * POST /api/kyc-settings/requirements
     */
    public function createRequirement() {
        $input = json_decode(file_get_contents('php://input'), true);

        // 验证
        $validator = new Validator($input);
        $validator->required(['noticeSettingId', 'itemTitle']);
        $validator->in('itemType', ['document', 'information', 'action']);

        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }

        $data = [
            'noticeSettingId' => $input['noticeSettingId'],
            'itemTitle' => $input['itemTitle'],
            'itemDescription' => $input['itemDescription'] ?? null,
            'itemType' => $input['itemType'] ?? 'document',
            'iconClass' => $input['iconClass'] ?? 'fas fa-file-alt',
            'isRequired' => $input['isRequired'] ?? 1,
            'displayOrder' => $input['displayOrder'] ?? 0,
            'isActive' => $input['isActive'] ?? 1
        ];

        $id = $this->requirementItemModel->create($data);

        if ($id) {
            Response::success(['id' => $id], 'Requirement item created successfully', 201);
        } else {
            Response::error('Failed to create requirement item', 500);
        }
    }

    /**
     * 更新要求项
     * PUT /api/kyc-settings/requirements/{id}
     */
    public function updateRequirement($id) {
        $input = json_decode(file_get_contents('php://input'), true);

        $existingItem = $this->requirementItemModel->findById($id);

        if (!$existingItem) {
            Response::notFound('Requirement item not found');
        }

        $success = $this->requirementItemModel->update($id, $input);

        if ($success) {
            Response::success(null, 'Requirement item updated successfully');
        } else {
            Response::error('Failed to update requirement item', 500);
        }
    }

    /**
     * 删除要求项
     * DELETE /api/kyc-settings/requirements/{id}
     */
    public function deleteRequirement($id) {
        $existingItem = $this->requirementItemModel->findById($id);

        if (!$existingItem) {
            Response::notFound('Requirement item not found');
        }

        $success = $this->requirementItemModel->delete($id);

        if ($success) {
            Response::success(null, 'Requirement item deleted successfully');
        } else {
            Response::error('Failed to delete requirement item', 500);
        }
    }

    /**
     * 更新要求项顺序
     * PUT /api/kyc-settings/requirements/reorder
     */
    public function reorderRequirements() {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['itemIds']) || !is_array($input['itemIds'])) {
            Response::error('Invalid data format. itemIds array is required', 400);
        }

        try {
            $success = $this->requirementItemModel->updateOrder($input['itemIds']);

            if ($success) {
                Response::success(null, 'Requirements reordered successfully');
            } else {
                Response::error('Failed to reorder requirements', 500);
            }
        } catch (Exception $e) {
            Response::error('Failed to reorder requirements: ' . $e->getMessage(), 500);
        }
    }

    // ============================================================
    // 邮件模板管理
    // ============================================================

    /**
     * 获取邮件模板列表
     * GET /api/kyc-settings/email-templates
     * 从 emailTemplates 表获取 category='kyc' 的模板
     */
    public function getEmailTemplates() {
        $activeOnly = isset($_GET['active_only']) && $_GET['active_only'] === 'true';

        $filters = [
            'category' => 'kyc'
        ];

        if ($activeOnly) {
            $filters['isActive'] = 1;
        }

        $templates = $this->emailTemplateModel->findAll($filters, 'templateName');

        Response::success($templates);
    }

    /**
     * 获取单个邮件模板
     * GET /api/kyc-settings/email-templates/{id}
     */
    public function getEmailTemplate($id) {
        $template = $this->emailTemplateModel->findById($id);

        if (!$template) {
            Response::notFound('Email template not found');
        }

        Response::success($template);
    }

    /**
     * 更新邮件模板
     * PUT /api/kyc-settings/email-templates/{id}
     * 更新 emailTemplates 表中的模板
     */
    public function updateEmailTemplate($id) {
        $input = json_decode(file_get_contents('php://input'), true);

        // 验证
        $validator = new Validator($input);

        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }

        $existingTemplate = $this->emailTemplateModel->findById($id);

        if (!$existingTemplate) {
            Response::notFound('Email template not found');
        }

        // 确保只更新 KYC 类别的模板
        if ($existingTemplate['category'] !== 'kyc') {
            Response::error('This template is not a KYC email template', 400);
        }

        // 获取当前管理员ID
        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        // 记录更改
        $changes = [];
        $fields = [
            'templateName', 'emailSubject', 'emailBody', 'description', 'variables', 'isActive'
        ];

        foreach ($fields as $field) {
            if (isset($input[$field])) {
                $oldValue = $existingTemplate[$field] ?? null;
                $newValue = $input[$field];

                // 对于 variables 字段，需要比较 JSON 字符串
                if ($field === 'variables') {
                    if (is_array($oldValue)) {
                        $oldValue = json_encode($oldValue, JSON_UNESCAPED_UNICODE);
                    }
                    if (is_array($newValue)) {
                        $newValue = json_encode($newValue, JSON_UNESCAPED_UNICODE);
                    }
                }

                if ($oldValue != $newValue) {
                    $changes[] = [
                        'settingType' => 'email_templates',
                        'settingId' => $id,
                        'fieldName' => $field,
                        'oldValue' => is_array($existingTemplate[$field] ?? null) ? json_encode($existingTemplate[$field]) : ($existingTemplate[$field] ?? ''),
                        'newValue' => is_array($newValue) ? json_encode($newValue) : $newValue
                    ];
                }
            }
        }

        $success = $this->emailTemplateModel->update($id, $input);

        if ($success) {
            // 记录更改日志
            if (!empty($changes) && $adminId) {
                $this->changeLogModel->logBulkChanges($changes, $adminId);
            }

            Response::success(null, 'Email template updated successfully');
        } else {
            Response::error('Failed to update email template', 500);
        }
    }

    /**
     * 预览邮件模板（处理变量替换）
     * POST /api/kyc-settings/email-templates/{id}/preview
     * 使用 EmailTemplate 的 replaceVariables 方法处理变量替换
     */
    public function previewEmailTemplate($id) {
        $input = json_decode(file_get_contents('php://input'), true);

        $template = $this->emailTemplateModel->findById($id);

        if (!$template) {
            Response::notFound('Email template not found');
        }

        // 获取平台名称配置
        $config = require __DIR__ . '/../config/app.php';
        $platformName = $config['branding']['companyShortName']
            ?? $config['branding']['logoText']
            ?? $config['branding']['companyName']
            ?? 'CRM';

        // 示例变量（包含 platformName）
        $variables = $input['variables'] ?? [
            'clientName' => 'John Doe',
            'platformName' => $platformName,
            'dashboardUrl' => 'https://example.com/dashboard',
            'statusUrl' => 'https://example.com/kyc-status',
            'resubmitUrl' => 'https://example.com/kyc-verification',
            'updateUrl' => 'https://example.com/kyc-update',
            'expiryDate' => date('Y-m-d', strtotime('+30 days')),
            'rejectionReason' => 'Document quality is not clear enough',
            'requiredItems' => '<ul><li>Clear copy of passport</li><li>Updated proof of address</li></ul>'
        ];

        // 确保 platformName 始终存在
        if (!isset($variables['platformName'])) {
            $variables['platformName'] = $platformName;
        }

        // 使用 EmailTemplate 的静态方法替换变量
        $subject = EmailTemplate::replaceVariables($template['emailSubject'], $variables);
        $body = EmailTemplate::replaceVariables($template['emailBody'], $variables);

        Response::success([
            'subject' => $subject,
            'body' => $body
        ]);
    }

    // ============================================================
    // 更改历史
    // ============================================================

    /**
     * 获取更改历史
     * GET /api/kyc-settings/change-history
     */
    public function getChangeHistory() {
        $settingType = $_GET['setting_type'] ?? null;
        $settingId = $_GET['setting_id'] ?? null;
        $adminId = $_GET['admin_id'] ?? null;
        $limit = $_GET['limit'] ?? 50;

        if ($settingType && $settingId) {
            $history = $this->changeLogModel->getChangeHistory($settingType, $settingId, $limit);
        } elseif ($adminId) {
            $history = $this->changeLogModel->getChangesByAdmin($adminId, $limit);
        } else {
            $history = $this->changeLogModel->getRecentChanges($limit);
        }

        Response::success($history);
    }

    // ============================================================
    // 综合设置
    // ============================================================

    /**
     * 获取所有 KYC 设置（概览）
     * GET /api/kyc-settings
     */
    public function index() {
        $noticeSettings = $this->noticeSettingModel->getDefaultSettings();
        $statusMessages = $this->statusMessageModel->getActiveTemplates();
        $emailTemplates = $this->emailTemplateModel->getActiveTemplates();

        $requirements = [];
        if ($noticeSettings) {
            $requirements = $this->requirementItemModel->getByNoticeSettingId($noticeSettings['id']);
        }

        Response::success([
            'noticeSettings' => $noticeSettings,
            'requirements' => $requirements,
            'statusMessages' => $statusMessages,
            'emailTemplates' => $emailTemplates
        ]);
    }

    /**
     * 获取设置统计信息
     * GET /api/kyc-settings/statistics
     */
    public function statistics() {
        $noticeEnabled = $this->noticeSettingModel->count(['isEnabled' => 1]);
        $totalStatusMessages = $this->statusMessageModel->count(['isActive' => 1]);
        $totalEmailTemplates = $this->emailTemplateModel->count(['isActive' => 1]);
        $totalRequirements = $this->requirementItemModel->count(['isActive' => 1]);
        $recentChanges = $this->changeLogModel->count([]);

        Response::success([
            'noticeEnabled' => (bool)$noticeEnabled,
            'activeStatusMessages' => $totalStatusMessages,
            'activeEmailTemplates' => $totalEmailTemplates,
            'totalRequirements' => $totalRequirements,
            'totalChanges' => $recentChanges
        ]);
    }
}
