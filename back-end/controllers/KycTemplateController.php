<?php
/**
 * KYC Template 控制器
 */

require_once __DIR__ . '/../models/KycTemplate.php';
require_once __DIR__ . '/../models/KycTemplateCountry.php';
require_once __DIR__ . '/../models/KycQuestionCategory.php';
require_once __DIR__ . '/../models/KycQuestion.php';
require_once __DIR__ . '/../models/KycConditionalRule.php';
require_once __DIR__ . '/../models/KycTemplateDocument.php';
require_once __DIR__ . '/../models/KycTemplateEditHistory.php';
require_once __DIR__ . '/../models/ExternalKycGateway.php';
require_once __DIR__ . '/../models/ExternalKycTemplate.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogTexts/KycOperationLogTexts.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class KycTemplateController {
    private $templateModel;
    private $countryModel;
    private $categoryModel;
    private $questionModel;
    private $ruleModel;
    private $documentModel;
    private $historyModel;

    public function __construct() {
        $this->templateModel = new KycTemplate();
        $this->countryModel = new KycTemplateCountry();
        $this->categoryModel = new KycQuestionCategory();
        $this->questionModel = new KycQuestion();
        $this->ruleModel = new KycConditionalRule();
        $this->documentModel = new KycTemplateDocument();
        $this->historyModel = new KycTemplateEditHistory();
    }

    private function boolToInt($val) {
        if ($val === '' || $val === null) return 0;
        if (is_bool($val)) return $val ? 1 : 0;
        if (is_numeric($val)) return intval($val) ? 1 : 0;
        return filter_var($val, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    /**
     * 从 countries 入参提取国家代码数组
     */
    private function normalizeCountryCodesFromInput($countries) {
        $codes = [];
        foreach ($countries as $c) {
            if (is_array($c) && isset($c['code'])) {
                $codes[] = $c['code'];
            } elseif (is_string($c)) {
                $codes[] = $c;
            }
        }
        return $codes;
    }

    /**
     * 获取已被其他模板占用的国家代码（用于前端置灰不可选）
     * GET /api/kyc-templates/taken-country-codes?exclude_template_id=123
     */
    public function getTakenCountryCodes() {
        $exclude = isset($_GET['exclude_template_id']) ? trim($_GET['exclude_template_id']) : null;
        if ($exclude !== null && $exclude !== '' && is_numeric($exclude)) {
            $exclude = (int) $exclude;
        } else {
            $exclude = null;
        }
        $taken = $this->countryModel->getTakenCountryCodes($exclude);
        Response::success(['takenCountryCodes' => $taken]);
    }

    /**
     * 获取模板列表
     * GET /api/kyc-templates
     */
    public function index() {
        $status = $_GET['status'] ?? null;
        $isThirdParty = $_GET['third_party'] ?? null;

        $filters = [];

        if ($status) {
            $filters['status'] = $status;
        }

        if ($isThirdParty !== null) {
            $filters['isThirdPartyEnabled'] = $isThirdParty;
        }

        $templates = $this->templateModel->getTemplatesSummary($filters);

        // 列表 Questions 列显示总题数（含 Inactive），与 Detail 内 Total Questions 一致，避免用户误解
        $questionCounts = $this->questionModel->getTotalQuestionCountByTemplate();

        // Attach countries list for each template
        foreach ($templates as &$template) {
            $template['totalQuestions'] = $questionCounts[$template['templateId']] ?? 0;
            $countries = $this->countryModel->getTemplateCountries($template['templateId']);
            $template['countries'] = array_map(function ($row) {
                return [
                    'countryCode' => $row['countryCode'],
                    'countryName' => $row['countryName']
                ];
            }, $countries);
        }
        unset($template);

        Response::success([
            'templates' => $templates,
            'total' => count($templates)
        ]);
    }

    /**
     * 获取单个模板详情
     * GET /api/kyc-templates/{id}
     */
    public function show($id) {
        $template = $this->templateModel->getTemplateDetails($id);

        if (!$template) {
            Response::notFound('Template not found');
        }

        Response::success($template);
    }

    /**
     * 创建新模板
     * POST /api/kyc-templates
     */
    public function create() {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $subModule = OperationLogPages::resolveLogKycTemplates($input);
        $opLog = new AdminOperationLogWriter();
//        // 验证（使用通用 Validator 规则式接口）
//        Validator::make($input, [
//            'templateName' => 'required|max:200',
//            'status' => 'required|in:draft,active,inactive,archived'
//        ]);
        // 获取当前管理员ID
        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;
        $data = [
            'templateName' => $input['name'],
            'description' => $input['description'] ?? null,
            'status' => $input['status'] ?? 'draft',
            'isThirdPartyEnabled' => isset($input['isThirdPartyEnabled']) ? (filter_var($input['isThirdPartyEnabled'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 0,
            'thirdPartyProvider' => $input['thirdPartyProvider'] ?? null,
            'isAutoApproveEnabled' => isset($input['isAutoApproveEnabled']) ? (filter_var($input['isAutoApproveEnabled'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 0,
            'requireDocumentSignature' => isset($input['requireDocumentSignature']) ? (filter_var($input['requireDocumentSignature'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 1,
            'displayOrder' => $input['displayOrder'] ?? 0,
            'createdBy' => $adminId
        ];
        try {
            $templateId = $this->templateModel->create($data);
            // 如果提供了国家列表，添加国家（每个国家/ALL 只能被一个模板选择）
            if (isset($input['countries']) && is_array($input['countries'])) {
                $codes = $this->normalizeCountryCodesFromInput($input['countries']);
                $taken = $this->countryModel->getTakenCountryCodes(null);
                $takenUpper = array_map('strtoupper', $taken);
                foreach ($codes as $code) {
                    if (in_array(strtoupper($code), $takenUpper)) {
                        $countryMsg = 'One or more selected countries (or All Countries) are already assigned to another template. Each country can only be used by one template.';
                        $opLog->logKycTemplateAdd($subModule, (int) $templateId, $input['name'] ?? '', false, $countryMsg);
                        Response::error($countryMsg, 400);
                    }
                }
                $this->countryModel->assignCountriesToTemplate($templateId, $input['countries']);
            }
            // 记录历史
            $this->historyModel->logChange(
                $templateId,
                'template_created',
                "Template '{$input['name']}' created",
                $adminId
            );
            $template = $this->templateModel->getTemplateDetails($templateId);
            $opLog->logKycTemplateAdd(
                $subModule,
                $templateId,
                $input['name'] ?? ($template['templateName'] ?? '')
            );
            Response::created($template, 'Template created successfully');
        } catch (Exception $e) {
            $opLog->logKycTemplateAdd($subModule, 0, $input['name'] ?? '', false, 'Failed to create template: ' . $e->getMessage());
            Response::error('Failed to create template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新模板信息
     * PUT /api/kyc-templates/{id}
     */
    public function update($id) {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        $template = $this->templateModel->findById($id);

        if (!$template) {
            $this->logTemplateMutationFailure($input, 'edit', 0, 'templateEditFailure', 'Template not found');
            Response::notFound('Template not found');
        }
        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        $data = [];
        $changes = [];

        // 只更新提供的字段
        if (isset($input['templateName'])) {
            if ($input['templateName'] !== $template['templateName']) {
                $changes[] = "Name: '{$template['templateName']}' → '{$input['templateName']}'";
            }
            $data['templateName'] = $input['templateName'];
        }

        if (isset($input['description'])) {
            $data['description'] = $input['description'];
        }

        if (isset($input['status'])) {
            if ($input['status'] !== $template['status']) {
                $changes[] = "Status: '{$template['status']}' → '{$input['status']}'";
            }
            $data['status'] = $input['status'];
        }

        // 注意：第三方相关三个字段（isThirdPartyEnabled / thirdPartyProvider / externalTemplateId）
        //       不在这里改，统一走 PUT /api/kyc-templates/{id}/third-party，
        //       由那个接口保证三者一致并校验 gateway / level 有效

        if (isset($input['isAutoApproveEnabled'])) {
            $data['isAutoApproveEnabled'] = filter_var($input['isAutoApproveEnabled'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        if (isset($input['requireDocumentSignature'])) {
            $data['requireDocumentSignature'] = filter_var($input['requireDocumentSignature'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        if (isset($input['displayOrder'])) {
            $data['displayOrder'] = $input['displayOrder'];
        }

        $data['updatedBy'] = $adminId;

        try {
            $this->templateModel->update($id, $data);

            // 记录历史
            if (!empty($changes)) {
                $this->historyModel->logChange(
                    $id,
                    'template_info',
                    'Template info updated: ' . implode(', ', $changes),
                    $adminId
                );
            }

            $updatedTemplate = $this->templateModel->getTemplateDetails($id);
            $this->logTemplateUpdate($input, $id, $template, $updatedTemplate, $changes);

            Response::success($updatedTemplate, 'Template updated successfully');

        } catch (Exception $e) {
            $this->logTemplateUpdateFailure($input, (int) $id, 'Failed to update template: ' . $e->getMessage());
            Response::error('Failed to update template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 删除模板
     * DELETE /api/kyc-templates/{id}
     * 仅当模板未被任何 clientUsers 使用（无 clientKycSubmissions）且状态为 Inactive 时允许删除。
     */
    public function delete($id) {
        $subModule = OperationLogPages::resolveLogKycTemplatesFromRequest();
        $opLog = new AdminOperationLogWriter();
        $template = $this->templateModel->findById($id);

        if (!$template) {
            $opLog->logKycTemplateDelete($subModule, 0, '', false, 'Template not found');
            Response::notFound('Template not found');
        }

        $status = isset($template['status']) ? strtolower(trim($template['status'])) : '';
        $templateName = (string) ($template['templateName'] ?? '');

        // 1. 是否已被客户使用：存在任意 clientKycSubmissions 记录（不论状态）则不允许删除
        $sql = "SELECT 1 FROM clientKycSubmissions WHERE templateId = :id LIMIT 1";
        $row = $this->templateModel->queryOne($sql, ['id' => $id]);

        if ($row) {
            $opLog->logKycTemplateDelete(
                $subModule,
                (int) $id,
                $templateName,
                false,
                'This template cannot be deleted because it has been used by client users. Please archive it instead.'
            );
            Response::error(
                'This template cannot be deleted because it has been used by client users. Please archive it instead.',
                400
            );
        }

        // 2. 仅允许删除状态为 Inactive 的模板
        if ($status !== 'inactive') {
            $statusLabel = $status ?: 'unknown';
            $inactiveMsg = "Only inactive templates can be deleted. Current status is \"{$statusLabel}\". Please set the template to Inactive first.";
            $opLog->logKycTemplateDelete($subModule, (int) $id, $templateName, false, $inactiveMsg);
            Response::error($inactiveMsg, 400);
        }

        try {
            $this->templateModel->delete($id);
            $opLog->logKycTemplateDelete($subModule, (int) $id, $templateName);
            Response::success(null, 'Template deleted successfully');
        } catch (Exception $e) {
            $opLog->logKycTemplateDelete(
                $subModule,
                (int) $id,
                $templateName,
                false,
                'Failed to delete template: ' . $e->getMessage()
            );
            Response::error('Failed to delete template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 克隆模板
     * POST /api/kyc-templates/{id}/clone
     */
    public function clone($id) {
        $template = $this->templateModel->findById($id);

        if (!$template) {
            Response::notFound('Template not found');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $newName = $input['newName'] ?? $template['templateName'] . ' (Copy)';

        try {
            $newTemplateId = $this->templateModel->cloneTemplate($id, $newName);

            $newTemplate = $this->templateModel->getTemplateDetails($newTemplateId);
            $subModule = OperationLogPages::resolveLogKycTemplates($input ?? []);
            (new AdminOperationLogWriter())->logKycTemplateAdd(
                $subModule,
                $newTemplateId,
                $newName
            );

            Response::created($newTemplate, 'Template cloned successfully');

        } catch (Exception $e) {
            Response::error('Failed to clone template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新模板国家列表
     * PUT /api/kyc-templates/{id}/countries
     */
    public function updateCountries($id) {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        $template = $this->templateModel->findById($id);

        if (!$template) {
            $this->logTemplateMutationFailure($input, 'edit', 0, 'templateEditFailure', 'Template not found');
            Response::notFound('Template not found');
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        if (!isset($input['countries']) || !is_array($input['countries'])) {
            $this->logTemplateMutationFailure(
                $input,
                'edit',
                (int) $id,
                'templateEditFailure',
                OperationLogTextHelpers::validationErrorsToMessage(['countries' => ['Countries array is required']])
            );
            Response::validationError(['countries' => 'Countries array is required']);
        }

        $codes = $this->normalizeCountryCodesFromInput($input['countries']);
        $taken = $this->countryModel->getTakenCountryCodes($id);
        $takenUpper = array_map('strtoupper', $taken);
        foreach ($codes as $code) {
            if (in_array(strtoupper($code), $takenUpper)) {
                $countryMsg = 'One or more selected countries (or All Countries) are already assigned to another template. Each country can only be used by one template.';
                $this->logTemplateMutationFailure($input, 'edit', (int) $id, 'templateEditFailure', $countryMsg);
                Response::error($countryMsg, 400);
            }
        }

        try {
            $this->countryModel->updateTemplateCountries($id, $input['countries']);

            // 记录历史
            $countryNames = array_column($input['countries'], 'name');
            $this->historyModel->logChange(
                $id,
                'country_updated',
                'Countries updated: ' . implode(', ', $countryNames),
                $adminId
            );

            $countries = $this->countryModel->getTemplateCountries($id);
            $subModule = OperationLogPages::resolveLogKycTemplates($input);
            $countryNames = array_column($input['countries'], 'name');
            list($detailZh, $detailEn) = KycOperationLogTexts::updateCountries(
                $template['templateName'] ?? '',
                $countryNames
            );
            (new AdminOperationLogWriter())->logKycTemplateMutation(
                $subModule,
                'edit',
                $id,
                $detailZh,
                $detailEn
            );

            Response::success([
                'countries' => $countries,
                'message' => 'Countries updated successfully'
            ]);

        } catch (Exception $e) {
            $this->logTemplateMutationFailure(
                $input,
                'edit',
                (int) $id,
                'templateEditFailure',
                'Failed to update countries: ' . $e->getMessage()
            );
            Response::error('Failed to update countries: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取模板编辑历史
     * GET /api/kyc-templates/{id}/history
     */
    public function getHistory($id) {
        $template = $this->templateModel->findById($id);

        if (!$template) {
            Response::notFound('Template not found');
        }

        $limit = $_GET['limit'] ?? 50;
        $history = $this->historyModel->getTemplateHistory($id, $limit);

        Response::success([
            'history' => $history,
            'total' => count($history)
        ]);
    }

    /**
     * 获取统计信息
     * GET /api/kyc-templates/statistics
     */
    public function statistics() {
        $sql = "SELECT
                    COUNT(*) AS totalTemplates,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS activeTemplates,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draftTemplates,
                    SUM(CASE WHEN isThirdPartyEnabled = 1 THEN 1 ELSE 0 END) AS thirdPartyTemplates
                FROM kycTemplates";

        $stats = $this->templateModel->queryOne($sql);

        Response::success($stats);
    }

    /**
     * 原子更新第三方绑定（isThirdPartyEnabled + thirdPartyProvider + externalTemplateId）
     * PUT /api/kyc-templates/{id}/third-party
     *
     * Body 形态一：启用并指定 platform + level
     *   {
     *     "isThirdPartyEnabled": true,
     *     "thirdPartyProvider":  "sumsub",
     *     "externalTemplateId":  5
     *   }
     *
     * Body 形态二：停用（不动 provider / externalTemplateId，留作下次启用复用）
     *   { "isThirdPartyEnabled": false }
     *
     * 校验规则：
     *   - 启用时 provider 和 externalTemplateId 必填
     *   - externalKycTemplates 存在 + isActive=1
     *   - 它挂的 externalKycGateways 存在 + isEnabled=1
     *   - 传入的 provider 必须和 gateway.provider 一致
     */
    public function updateThirdPartyBinding($id) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $template = $this->templateModel->findById($id);
        if (!$template) {
            $this->logTemplateMutationFailure($input, 'edit', 0, 'templateThirdPartyFailure', 'Template not found');
            Response::notFound('Template not found');
            return;
        }

        if (!array_key_exists('isThirdPartyEnabled', $input)) {
            $this->logTemplateMutationFailure($input, 'edit', (int) $id, 'templateThirdPartyFailure', 'isThirdPartyEnabled is required');
            Response::error('isThirdPartyEnabled is required', 400);
            return;
        }

        $enabled = filter_var($input['isThirdPartyEnabled'], FILTER_VALIDATE_BOOLEAN);
        $data = ['isThirdPartyEnabled' => $enabled ? 1 : 0];
        $changes = [];
        $externalTemplate = null;
        $gateway = null;

        if ($enabled) {
            $provider = strtolower(trim((string)($input['thirdPartyProvider'] ?? '')));
            $externalTemplateId = isset($input['externalTemplateId']) ? (int)$input['externalTemplateId'] : 0;

            if ($provider === '' || $externalTemplateId <= 0) {
                $this->logTemplateMutationFailure(
                    $input,
                    'enable',
                    (int) $id,
                    'templateThirdPartyFailure',
                    'thirdPartyProvider and externalTemplateId are required when enabling'
                );
                Response::error('thirdPartyProvider and externalTemplateId are required when enabling', 400);
                return;
            }

            $externalTemplate = (new ExternalKycTemplate())->findById($externalTemplateId);
            if (!$externalTemplate) {
                $this->logTemplateMutationFailure($input, 'enable', (int) $id, 'templateThirdPartyFailure', 'External KYC template not found');
                Response::error('External KYC template not found', 404);
                return;
            }
            if (empty($externalTemplate['isActive'])) {
                $this->logTemplateMutationFailure($input, 'enable', (int) $id, 'templateThirdPartyFailure', 'External KYC template is not active');
                Response::error('External KYC template is not active', 400);
                return;
            }

            $gateway = (new ExternalKycGateway())->findById((int)$externalTemplate['gatewayId']);
            if (!$gateway) {
                $this->logTemplateMutationFailure($input, 'enable', (int) $id, 'templateThirdPartyFailure', 'External KYC gateway not found');
                Response::error('External KYC gateway not found', 404);
                return;
            }
            if (empty($gateway['isEnabled'])) {
                $this->logTemplateMutationFailure($input, 'enable', (int) $id, 'templateThirdPartyFailure', 'External KYC gateway is not enabled');
                Response::error('External KYC gateway is not enabled', 400);
                return;
            }
            if (strtolower((string)$gateway['provider']) !== $provider) {
                $this->logTemplateMutationFailure(
                    $input,
                    'enable',
                    (int) $id,
                    'templateThirdPartyFailure',
                    'Provider does not match the gateway of the selected template'
                );
                Response::error('Provider does not match the gateway of the selected template', 400);
                return;
            }

            $data['thirdPartyProvider'] = $gateway['provider'];
            $data['externalTemplateId'] = $externalTemplateId;

            if ((int)($template['externalTemplateId'] ?? 0) !== $externalTemplateId) {
                $changes[] = "externalTemplateId: " . ($template['externalTemplateId'] ?? 'null') . " → {$externalTemplateId}";
            }
            if (strtolower((string)($template['thirdPartyProvider'] ?? '')) !== $provider) {
                $changes[] = "thirdPartyProvider: " . ($template['thirdPartyProvider'] ?? 'null') . " → {$gateway['provider']}";
            }
        }

        if ((bool)$template['isThirdPartyEnabled'] !== $enabled) {
            $changes[] = 'isThirdPartyEnabled: ' . ($template['isThirdPartyEnabled'] ? 'true' : 'false') . ' → ' . ($enabled ? 'true' : 'false');
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;
        if ($adminId !== null) {
            $data['updatedBy'] = (int)$adminId;
        }

        try {
            $this->templateModel->update($id, $data);
            if (!empty($changes)) {
                $this->historyModel->logChange(
                    $id,
                    'template_info',
                    'Third-party binding updated: ' . implode(', ', $changes),
                    $adminId
                );
            }
        } catch (Exception $e) {
            $this->logThirdPartyUpdateFailure($input, (int) $id, $enabled, 'Failed to update third-party binding: ' . $e->getMessage());
            Response::error('Failed to update third-party binding: ' . $e->getMessage(), 500);
            return;
        }

        $this->logThirdPartyBinding($input, $id, $template, $enabled, $externalTemplate, $gateway);
        Response::success($this->templateModel->findById($id), 'Third-party binding saved');
    }

    private function logTemplateUpdate($input, $id, $template, $updatedTemplate, array $changes) {
        $subModule = OperationLogPages::resolveLogKycTemplates(is_array($input) ? $input : []);
        $opLog = new AdminOperationLogWriter();
        $tplName = (string) ($updatedTemplate['templateName'] ?? $template['templateName'] ?? '');

        $onlyAuto = isset($input['isAutoApproveEnabled'])
            && !isset($input['templateName'])
            && !isset($input['description'])
            && !isset($input['status'])
            && !isset($input['requireDocumentSignature'])
            && !isset($input['displayOrder']);
        if ($onlyAuto) {
            $on = filter_var($input['isAutoApproveEnabled'], FILTER_VALIDATE_BOOLEAN);
            list($detailZh, $detailEn) = KycOperationLogTexts::toggleAutoApprove($tplName, $on);
            $opLog->logKycTemplateMutation($subModule, $on ? 'enable' : 'disable', $id, $detailZh, $detailEn);
            return;
        }

        $onlyDocSig = isset($input['requireDocumentSignature'])
            && !isset($input['templateName'])
            && !isset($input['description'])
            && !isset($input['status'])
            && !isset($input['isAutoApproveEnabled'])
            && !isset($input['displayOrder']);
        if ($onlyDocSig) {
            $on = filter_var($input['requireDocumentSignature'], FILTER_VALIDATE_BOOLEAN);
            list($detailZh, $detailEn) = KycOperationLogTexts::toggleDocSignature($tplName, $on);
            $opLog->logKycTemplateMutation($subModule, $on ? 'enable' : 'disable', $id, $detailZh, $detailEn);
            return;
        }

        if (isset($input['templateName']) && (string) $input['templateName'] !== (string) ($template['templateName'] ?? '')) {
            list($detailZh, $detailEn) = KycOperationLogTexts::renameTemplate(
                $template['templateName'] ?? '',
                $input['templateName']
            );
        } elseif (isset($input['status']) && (string) $input['status'] !== (string) ($template['status'] ?? '')) {
            list($detailZh, $detailEn) = KycOperationLogTexts::changeStatus(
                $tplName,
                $template['status'] ?? '',
                $input['status']
            );
        } elseif (isset($input['description'])) {
            list($detailZh, $detailEn) = KycOperationLogTexts::updateDescription($tplName);
        } else {
            list($detailZh, $detailEn) = KycOperationLogTexts::updateDescription($tplName);
        }

        $opLog->logKycTemplateMutation($subModule, 'edit', $id, $detailZh, $detailEn);
    }

    private function logThirdPartyBinding($input, $id, $template, $enabled, $externalTemplate = null, $gateway = null) {
        $subModule = OperationLogPages::resolveLogKycTemplates(is_array($input) ? $input : []);
        $opLog = new AdminOperationLogWriter();
        $tplName = (string) ($template['templateName'] ?? '');
        $wasEnabled = !empty($template['isThirdPartyEnabled']);
        $providerLabel = $this->resolveThirdPartyProviderLabel($gateway);
        $levelLabel = $this->resolveThirdPartyLevelLabel($externalTemplate);

        if (!$enabled && $wasEnabled) {
            list($detailZh, $detailEn) = KycOperationLogTexts::thirdPartyDisable($tplName);
            $opLog->logKycTemplateMutation($subModule, 'disable', $id, $detailZh, $detailEn);
            return;
        }

        if ($enabled && !$wasEnabled) {
            list($detailZh, $detailEn) = KycOperationLogTexts::thirdPartyEnable(
                $tplName,
                $providerLabel,
                $levelLabel
            );
            $opLog->logKycTemplateMutation($subModule, 'enable', $id, $detailZh, $detailEn);
            return;
        }

        if ($enabled && $wasEnabled) {
            list($detailZh, $detailEn) = KycOperationLogTexts::thirdPartyRebind(
                $tplName,
                $providerLabel,
                $levelLabel
            );
            $opLog->logKycTemplateMutation($subModule, 'edit', $id, $detailZh, $detailEn);
        }
    }

    private function resolveThirdPartyProviderLabel($gateway) {
        if (!is_array($gateway)) {
            return '';
        }
        $display = trim((string) ($gateway['displayName'] ?? ''));
        if ($display !== '') {
            return $display;
        }
        $provider = trim((string) ($gateway['provider'] ?? ''));
        return $provider !== '' ? ucfirst($provider) : '';
    }

    private function resolveThirdPartyLevelLabel($externalTemplate) {
        if (!is_array($externalTemplate)) {
            return '';
        }
        $name = trim((string) ($externalTemplate['externalLevelName'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        return trim((string) ($externalTemplate['externalLevelId'] ?? ''));
    }

    private function logTemplateMutationFailure($input, $operationTypeKey, $templateId, $failureMethod, $apiMessage) {
        $subModule = OperationLogPages::resolveLogKycTemplates(is_array($input) ? $input : []);
        list($detailZh, $detailEn) = call_user_func(
            ['KycOperationLogTexts', $failureMethod],
            $apiMessage
        );
        (new AdminOperationLogWriter())->logKycTemplateMutation(
            $subModule,
            trim((string) $operationTypeKey) ?: 'edit',
            (int) $templateId,
            $detailZh,
            $detailEn
        );
    }

    private function logTemplateUpdateFailure($input, $templateId, $apiMessage) {
        list($failureMethod, $opType) = $this->resolveTemplateUpdateFailureContext($input);
        $this->logTemplateMutationFailure($input, $opType, $templateId, $failureMethod, $apiMessage);
    }

    private function logThirdPartyUpdateFailure($input, $templateId, $enabled, $apiMessage) {
        $opType = $enabled ? 'enable' : 'disable';
        $this->logTemplateMutationFailure($input, $opType, $templateId, 'templateThirdPartyFailure', $apiMessage);
    }

    /**
     * @return array{0:string,1:string} [failureMethod, operationTypeKey]
     */
    private function resolveTemplateUpdateFailureContext($input) {
        if (!is_array($input)) {
            return ['templateEditFailure', 'edit'];
        }

        $onlyAuto = isset($input['isAutoApproveEnabled'])
            && !isset($input['templateName'])
            && !isset($input['description'])
            && !isset($input['status'])
            && !isset($input['requireDocumentSignature'])
            && !isset($input['displayOrder']);
        if ($onlyAuto) {
            $on = filter_var($input['isAutoApproveEnabled'], FILTER_VALIDATE_BOOLEAN);
            return ['templateAutoApproveFailure', $on ? 'enable' : 'disable'];
        }

        $onlyDocSig = isset($input['requireDocumentSignature'])
            && !isset($input['templateName'])
            && !isset($input['description'])
            && !isset($input['status'])
            && !isset($input['isAutoApproveEnabled'])
            && !isset($input['displayOrder']);
        if ($onlyDocSig) {
            $on = filter_var($input['requireDocumentSignature'], FILTER_VALIDATE_BOOLEAN);
            return ['templateDocSignatureFailure', $on ? 'enable' : 'disable'];
        }

        return ['templateEditFailure', 'edit'];
    }
}
