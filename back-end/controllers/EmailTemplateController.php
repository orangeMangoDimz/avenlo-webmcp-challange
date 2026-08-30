<?php
/**
 * Email Template Controller
 * 处理邮件模板管理相关接口
 */

require_once __DIR__ . '/../models/EmailTemplate.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/RequestInput.php';
require_once __DIR__ . '/../services/OperationLog/EmailTemplatesOperationLog.php';
require_once __DIR__ . '/../services/OperationLog/EmailTemplatesLogSnapshot.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class EmailTemplateController {
    private $templateModel;

    public function __construct() {
        $this->templateModel = new EmailTemplate();
    }

    /**
     * 检查管理员权限
     */
    private function requireAdmin() {
        $payload = JWT::getPayload();

        if (!$payload) {
            Response::unauthorized('Invalid or missing token');
        }

        $userType = $payload['type'] ?? $payload['userType'] ?? '';
        if ($userType !== 'admin') {
            Response::forbidden('Admin authentication required');
        }

        return $payload;
    }

    private function resolveOperatorId() {
        $payload = JWT::getPayload();
        if (!$payload) {
            return 0;
        }
        return (int) ($payload['userId'] ?? $payload['id'] ?? 0);
    }

    /**
     * 获取模板列表（分页）
     * GET /api/email-templates
     */
    public function index() {
        $this->requireAdmin();

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? max(1, min(100, (int)$_GET['per_page'])) : 10;

        $filters = [];
        if (isset($_GET['category'])) {
            $filters['category'] = $_GET['category'];
        }
        if (isset($_GET['recipientType'])) {
            $filters['recipientType'] = $_GET['recipientType'];
        }
        if (isset($_GET['isActive'])) {
            $filters['isActive'] = (int)$_GET['isActive'];
        }
        if (isset($_GET['search'])) {
            $filters['search'] = $_GET['search'];
        }

        $result = $this->templateModel->getTemplates($page, $perPage, $filters);
        Response::paginated($result['items'], $result['total'], $page, $perPage);
    }

    /**
     * 获取单个模板
     * GET /api/email-templates/{id}
     */
    public function show($id) {
        $this->requireAdmin();

        $template = $this->templateModel->findById($id);
        if (!$template) {
            Response::notFound('Template not found');
        }

        if ($template['variables']) {
            $template['variables'] = json_decode($template['variables'], true);
        }

        Response::success($template);
    }

    /**
     * 根据key获取模板（用于发送邮件时调用）
     * GET /api/email-templates/key/{key}
     */
    public function getByKey($key) {
        $template = $this->templateModel->getByKey($key);
        if (!$template) {
            Response::notFound('Template not found');
        }

        Response::success($template);
    }

    /**
     * 创建模板
     * POST /api/email-templates/create
     */
    public function create() {
        $operatorId = $this->resolveOperatorId();
        $data = RequestInput::readJsonBody();
        $input = EmailTemplatesOperationLog::inputFromRequest(is_array($data) ? $data : null);

        $this->requireAdmin();

        if (!is_array($data)) {
            EmailTemplatesOperationLog::logFailure(
                $input,
                'add',
                'emailTemplateCreateFailure',
                'Invalid JSON body',
                null,
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
            return;
        }

        $errors = Validator::validateData($data, [
            'templateKey' => 'required|string|max:100',
            'templateName' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'emailSubject' => 'required|string',
            'emailBody' => 'required|string',
            'recipientType' => 'required|in:client,admin,both',
        ]);
        if (!empty($errors)) {
            EmailTemplatesOperationLog::logFailure(
                $input,
                'add',
                'emailTemplateCreateFailure',
                OperationLogTextHelpers::validationErrorsToMessage($errors),
                null,
                $operatorId
            );
            Response::validationError($errors);
            return;
        }

        $existing = $this->templateModel->getByKey($data['templateKey']);
        if ($existing) {
            EmailTemplatesOperationLog::logFailure(
                $input,
                'add',
                'emailTemplateCreateFailure',
                'Template key already exists',
                null,
                $operatorId
            );
            Response::validationError([
                'templateKey' => ['Template key already exists'],
            ]);
            return;
        }

        if (isset($data['variables']) && is_array($data['variables'])) {
            // keep array
        } else {
            $data['variables'] = [];
        }

        if (isset($data['isActive'])) {
            $data['isActive'] = filter_var($data['isActive'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        } else {
            $data['isActive'] = 1;
        }

        $id = $this->templateModel->create($data);
        $template = $this->templateModel->findById($id);

        if ($template && $template['variables']) {
            $template['variables'] = json_decode($template['variables'], true);
        }

        if ($template) {
            EmailTemplatesOperationLog::logCreateSuccess(
                $input,
                EmailTemplatesLogSnapshot::fromDbRow($template),
                (int) $id,
                $operatorId
            );
        }

        Response::success($template, 'Template created successfully');
    }

    /**
     * 更新模板
     * PUT /api/email-templates/modify/{id}
     */
    public function update($id) {
        $operatorId = $this->resolveOperatorId();
        $templateId = (int) $id;
        $data = RequestInput::readJsonBody();
        $input = EmailTemplatesOperationLog::inputFromRequest(is_array($data) ? $data : null);

        $this->requireAdmin();

        $template = $this->templateModel->findById($templateId);
        if (!$template) {
            EmailTemplatesOperationLog::logFailure(
                $input,
                'edit',
                'emailTemplateUpdateFailure',
                'Template not found',
                $templateId,
                $operatorId
            );
            Response::notFound('Template not found');
            return;
        }

        if (!is_array($data)) {
            EmailTemplatesOperationLog::logFailure(
                $input,
                'edit',
                'emailTemplateUpdateFailure',
                'Invalid JSON body',
                $templateId,
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
            return;
        }

        $beforeState = EmailTemplatesLogSnapshot::fromDbRow($template);

        $errors = Validator::validateData($data, [
            'templateName' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|string|max:50',
            'emailSubject' => 'sometimes|required|string',
            'emailBody' => 'sometimes|required|string',
            'recipientType' => 'sometimes|required|in:client,admin,both',
        ]);
        if (!empty($errors)) {
            EmailTemplatesOperationLog::logFailure(
                $input,
                'edit',
                'emailTemplateUpdateFailure',
                OperationLogTextHelpers::validationErrorsToMessage($errors),
                $templateId,
                $operatorId
            );
            Response::validationError($errors);
            return;
        }

        if (isset($data['templateKey']) && $data['templateKey'] !== $template['templateKey']) {
            $existing = $this->templateModel->getByKey($data['templateKey']);
            if ($existing) {
                EmailTemplatesOperationLog::logFailure(
                    $input,
                    'edit',
                    'emailTemplateUpdateFailure',
                    'Template key already exists',
                    $templateId,
                    $operatorId
                );
                Response::validationError([
                    'templateKey' => ['Template key already exists'],
                ]);
                return;
            }
        }

        if (isset($data['variables']) && is_array($data['variables'])) {
            // keep
        } elseif (!isset($data['variables'])) {
            unset($data['variables']);
        }

        if (isset($data['isActive'])) {
            $data['isActive'] = filter_var($data['isActive'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        $this->templateModel->update($templateId, $data);
        $updated = $this->templateModel->findById($templateId);

        if ($updated && $updated['variables']) {
            $updated['variables'] = json_decode($updated['variables'], true);
        }

        if ($updated) {
            EmailTemplatesOperationLog::logUpdateSuccess(
                $input,
                $beforeState,
                EmailTemplatesLogSnapshot::fromDbRow($updated),
                $templateId,
                $operatorId
            );
        }

        Response::success($updated, 'Template updated successfully');
    }

    /**
     * 删除模板
     * DELETE /api/email-templates/remove/{id}
     */
    public function delete($id) {
        $operatorId = $this->resolveOperatorId();
        $templateId = (int) $id;
        $input = EmailTemplatesOperationLog::inputFromRequest();

        $this->requireAdmin();

        $template = $this->templateModel->findById($templateId);
        if (!$template) {
            EmailTemplatesOperationLog::logFailure(
                $input,
                'delete',
                'emailTemplateDeleteFailure',
                'Template not found',
                $templateId,
                $operatorId
            );
            Response::notFound('Template not found');
            return;
        }

        $beforeState = EmailTemplatesLogSnapshot::fromDbRow($template);
        $this->templateModel->delete($templateId);

        EmailTemplatesOperationLog::logDeleteSuccess(
            $input,
            $beforeState,
            $templateId,
            $operatorId
        );

        Response::success(null, 'Template deleted successfully');
    }

    /**
     * 获取所有分类
     * GET /api/email-templates/categories
     */
    public function getCategories() {
        $this->requireAdmin();

        $categories = $this->templateModel->getCategories();
        Response::success($categories);
    }

    /**
     * 切换模板启用状态
     * GET /api/email-templates/{id}/toggle-active
     */
    public function toggleActive($id) {
        $operatorId = $this->resolveOperatorId();
        $templateId = (int) $id;
        $input = EmailTemplatesOperationLog::inputFromRequest();

        $this->requireAdmin();

        $template = $this->templateModel->findById($templateId);
        if (!$template) {
            EmailTemplatesOperationLog::logFailure(
                $input,
                'edit',
                'emailTemplateToggleFailure',
                'Template not found',
                $templateId,
                $operatorId
            );
            Response::notFound('Template not found');
            return;
        }

        $newStatus = $template['isActive'] ? 0 : 1;
        $result = $this->templateModel->update($templateId, ['isActive' => $newStatus]);

        if ($result === false) {
            EmailTemplatesOperationLog::logFailure(
                $input,
                $newStatus ? 'enable' : 'disable',
                'emailTemplateToggleFailure',
                'Failed to update template status',
                $templateId,
                $operatorId
            );
            Response::error('Failed to update template status', 500);
            return;
        }

        $updated = $this->templateModel->findById($templateId);
        if (!$updated) {
            EmailTemplatesOperationLog::logFailure(
                $input,
                $newStatus ? 'enable' : 'disable',
                'emailTemplateToggleFailure',
                'Template not found after update',
                $templateId,
                $operatorId
            );
            Response::error('Template not found after update', 500);
            return;
        }

        EmailTemplatesOperationLog::logToggleSuccess(
            $input,
            EmailTemplatesLogSnapshot::fromDbRow($updated),
            !empty($updated['isActive']),
            $templateId,
            $operatorId
        );

        Response::success($updated, 'Template status updated');
    }
}
