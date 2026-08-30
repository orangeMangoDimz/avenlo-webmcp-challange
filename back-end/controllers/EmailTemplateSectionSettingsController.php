<?php
/**
 * Email Template Section Settings Controller
 * 邮件模板板块设置控制器
 */

require_once __DIR__ . '/../models/EmailTemplateSectionSettings.php';
require_once __DIR__ . '/../models/EmailTemplate.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/RequestInput.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../services/OperationLog/EmailSettingsOperationLog.php';
require_once __DIR__ . '/../services/OperationLog/EmailSettingsLogSnapshot.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class EmailTemplateSectionSettingsController {
    private $settingsModel;
    private $templateModel;

    public function __construct() {
        $this->settingsModel = new EmailTemplateSectionSettings();
        $this->templateModel = new EmailTemplate();
    }

    /** 支持的板块：Leads、Client List、IB List，与各页面 Send Notification 弹窗的 sectionKey 对应 */
    private static $defaultSectionKeys = [
        'leads' => 'Leads',
        'client_list' => 'Client List',
        'ib_list' => 'IB List'
    ];

    /**
     * 获取所有板块设置
     * GET /api/email-template-section-settings
     */
    public function index() {
        try {
            $this->requireAdmin();

            $dbSections = $this->settingsModel->getAllSections();
            $byKey = [];
            foreach ($dbSections as $s) {
                $byKey[$s['sectionKey']] = $s;
            }

            // 确保返回 leads、client_list、ib_list 三个板块（缺的用默认项，保存时会写入 DB）
            $sections = [];
            foreach (self::$defaultSectionKeys as $key => $name) {
                $section = $byKey[$key] ?? null;
                if ($section) {
                    $templateIds = json_decode($section['templateIds'] ?? '[]', true);
                    $section['selectedTemplateIds'] = is_array($templateIds) ? $templateIds : [];
                    $sections[] = $section;
                } else {
                    $sections[] = [
                        'sectionKey' => $key,
                        'sectionName' => $name,
                        'templateIds' => '[]',
                        'selectedTemplateIds' => []
                    ];
                }
            }

            // 获取所有激活的邮件模板
            $allTemplates = $this->templateModel->getTemplates(1, 1000, ['isActive' => 1]);
            $activeTemplates = $allTemplates['items'] ?? [];

            Response::success([
                'sections' => $sections,
                'availableTemplates' => $activeTemplates
            ]);
        } catch (Exception $e) {
            Response::error('Failed to load settings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取单个板块的设置
     * GET /api/email-template-section-settings/{sectionKey}
     */
    public function show($sectionKey) {
        try {
            $this->requireAdmin();

            $section = $this->settingsModel->getBySectionKey($sectionKey);
            if (!$section) {
                Response::error('Section not found', 404);
                return;
            }

            $templateIds = json_decode($section['templateIds'] ?? '[]', true);
            $section['selectedTemplateIds'] = is_array($templateIds) ? $templateIds : [];

            Response::success($section);
        } catch (Exception $e) {
            Response::error('Failed to load section settings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新板块的模板设置
     * PUT /api/email-template-section-settings/{sectionKey}
     */
    public function update($sectionKey) {
        $operatorId = $this->resolveOperatorId();
        $data = RequestInput::readJsonBody();
        $input = EmailSettingsOperationLog::inputFromRequest(is_array($data) ? $data : null);

        try {
            $admin = $this->requireAdmin();
            $adminId = $operatorId > 0 ? $operatorId : (int)($admin['userId'] ?? $admin['id'] ?? 0);
            if ($adminId <= 0) {
                $adminId = null;
            }

            if (!is_array($data)) {
                EmailSettingsOperationLog::logFailure(
                    $input,
                    'edit',
                    'emailSettingsSectionUpdateFailure',
                    'Invalid JSON body',
                    $operatorId
                );
                Response::error('Invalid JSON body', 400);
                return;
            }

            if (!isset($data['templateIds']) || !is_array($data['templateIds'])) {
                $errors = [
                    'templateIds' => ['The templateIds field is required and must be an array'],
                ];
                EmailSettingsOperationLog::logFailure(
                    $input,
                    'edit',
                    'emailSettingsSectionUpdateFailure',
                    OperationLogTextHelpers::validationErrorsToMessage($errors),
                    $operatorId
                );
                Response::validationError($errors);
                return;
            }

            $templateIds = array_values(array_unique(array_filter(array_map('intval', $data['templateIds']), function ($id) {
                return $id > 0;
            })));
            $beforeIds = $this->settingsModel->getTemplateIds($sectionKey);
            $sectionNameEn = $this->resolveSectionNameEn($sectionKey);

            $result = $this->settingsModel->updateTemplateIds($sectionKey, $templateIds, $adminId);

            if ($result) {
                if (!EmailSettingsLogSnapshot::idsEqual($beforeIds, $templateIds)) {
                    $nameMap = $this->templateModel->findNamesByIds(array_merge($beforeIds, $templateIds));
                    EmailSettingsOperationLog::logSectionUpdateSuccess(
                        $input,
                        $sectionKey,
                        $sectionNameEn,
                        $beforeIds,
                        $templateIds,
                        $nameMap,
                        $operatorId
                    );
                }
                Response::success([
                    'message' => 'Settings updated successfully',
                    'section' => $this->settingsModel->getBySectionKey($sectionKey),
                ]);
                return;
            }

            EmailSettingsOperationLog::logFailure(
                $input,
                'edit',
                'emailSettingsSectionUpdateFailure',
                'Failed to update settings',
                $operatorId
            );
            Response::error('Failed to update settings', 500);
        } catch (Exception $e) {
            EmailSettingsOperationLog::logFailure(
                $input,
                'edit',
                'emailSettingsSectionUpdateFailure',
                $e->getMessage(),
                $operatorId
            );
            Response::error('Failed to update settings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 批量更新多个板块的设置
     * PUT /api/email-template-section-settings/batch
     */
    public function updateBatch() {
        try {
            $admin = $this->requireAdmin();
            $adminId = $admin['id'] ?? null;

            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            if (!is_array($data)) {
                Response::validationError([
                    'settings' => ['The settings field must be an object']
                ]);
                return;
            }

            $result = $this->settingsModel->updateBatch($data, $adminId);

            if ($result) {
                Response::success([
                    'message' => 'Settings updated successfully',
                    'sections' => $this->settingsModel->getAllSections()
                ]);
            } else {
                Response::error('Failed to update settings', 500);
            }
        } catch (Exception $e) {
            Response::error('Failed to update settings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取板块可用的邮件模板列表（用于 Send Notification 弹窗）
     * GET /api/email-template-section-settings/{sectionKey}/templates
     */
    public function getTemplates($sectionKey) {
        try {
            $this->requireAdmin();

            $templates = $this->settingsModel->getAvailableTemplates($sectionKey);

            // 格式化返回数据
            $formattedTemplates = array_map(function($template) {
                return [
                    'id' => $template['id'],
                    'templateKey' => $template['templateKey'],
                    'name' => $template['templateName'],
                    'subject' => $template['emailSubject'],
                    'body' => $template['emailBody'],
                    'category' => $template['category'],
                    'recipientType' => $template['recipientType']
                ];
            }, $templates);

            Response::success($formattedTemplates);
        } catch (Exception $e) {
            Response::error('Failed to load templates: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 验证管理员权限
     */
    private function requireAdmin() {
        $token = JWT::getTokenFromHeader();
        if (!$token) {
            Response::error('Unauthorized', 401);
            exit;
        }

        $decoded = JWT::decode($token);
        if (!$decoded || ($decoded['type'] ?? '') !== 'admin') {
            Response::error('Forbidden: Admin access required', 403);
            exit;
        }

        return $decoded;
    }

    private function resolveOperatorId() {
        $token = JWT::getTokenFromHeader();
        if (!$token) {
            return 0;
        }
        $payload = JWT::decode($token);
        return (int) ($payload['userId'] ?? $payload['id'] ?? 0);
    }

    private function resolveSectionNameEn($sectionKey) {
        $key = trim((string) $sectionKey);
        if (isset(self::$defaultSectionKeys[$key])) {
            return self::$defaultSectionKeys[$key];
        }
        $section = $this->settingsModel->getBySectionKey($key);
        $name = trim((string) ($section['sectionName'] ?? ''));
        return $name !== '' ? $name : $key;
    }
}
