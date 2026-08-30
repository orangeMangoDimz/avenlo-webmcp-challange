<?php
/**
 * 登录页设置控制器
 * 处理登录页品牌、字段配置、密码强度等设置
 */

require_once __DIR__ . '/../models/LoginPageBranding.php';
require_once __DIR__ . '/../models/RegistrationFormField.php';
require_once __DIR__ . '/../models/PasswordStrengthSettings.php';
require_once __DIR__ . '/../models/LegalDocument.php';
require_once __DIR__ . '/../models/LanguagePack.php';
require_once __DIR__ . '/../models/IpLanguageDetectionSettings.php';
require_once __DIR__ . '/../models/EmailVerificationSettings.php';
require_once __DIR__ . '/../models/LoginSettingsChangeLog.php';
require_once __DIR__ . '/../models/TradingGroup.php';
require_once __DIR__ . '/../models/TradingPlatform.php';
require_once __DIR__ . '/../models/TradingPlatformLeverage.php';
require_once __DIR__ . '/../models/Countrylist.php';
require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/FinanceProApiClient.php';
require_once __DIR__ . '/../utils/Mt5ApiClient.php';
require_once __DIR__ . '/../utils/Mt5GatewayApiClient.php';
require_once __DIR__ . '/../utils/Mt4ApiClient.php';
require_once __DIR__ . '/../services/OperationLog/LoginPageSettingsOperationLog.php';
require_once __DIR__ . '/../services/OperationLog/LoginPageSettingsLogSnapshot.php';
require_once __DIR__ . '/../services/OperationLog/PlatformSettingsOperationLog.php';
require_once __DIR__ . '/../services/OperationLog/PlatformSettingsLogSnapshot.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';
require_once __DIR__ . '/../utils/RequestInput.php';

class LoginPageSettingsController {
    private $brandingModel;
    private $formFieldsModel;
    private $passwordStrengthModel;
    private $legalDocModel;
    private $languagePackModel;
    private $ipLanguageModel;
    private $emailVerificationModel;
    private $changeLogModel;
    private $tradingGroupModel;
    private $platformModel;
    private $leverageModel;
    private $countryListModel;
    private $ibPartnerModel;

    public function __construct() {
        $this->brandingModel = new LoginPageBranding();
        $this->formFieldsModel = new RegistrationFormField();
        $this->passwordStrengthModel = new PasswordStrengthSettings();
        $this->legalDocModel = new LegalDocument();
        $this->languagePackModel = new LanguagePack();
        $this->ipLanguageModel = new IpLanguageDetectionSettings();
        $this->emailVerificationModel = new EmailVerificationSettings();
        $this->changeLogModel = new LoginSettingsChangeLog();
        $this->tradingGroupModel = new TradingGroup();
        $this->platformModel = new TradingPlatform();
        $this->leverageModel = new TradingPlatformLeverage();
        $this->countryListModel = new Countrylist();
        $this->ibPartnerModel = new IbPartner();
    }

    // ========== Branding Settings ==========

    /**
     * 获取品牌设置
     * GET /api/login-settings/branding
     */
    public function getBranding() {
        $settings = $this->brandingModel->getSettings();
        Response::success($settings);
    }

    /**
     * 更新品牌设置
     * PUT /api/login-settings/branding
     */
    public function updateBranding() {
        $this->requireAuth();
        $operatorId = $this->resolveOperatorId();

        $data = json_decode(file_get_contents('php://input'), true);
        $input = LoginPageSettingsOperationLog::inputFromRequest(is_array($data) ? $data : null);

        if (!is_array($data)) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPageBrandingEditFailure',
                'Invalid JSON body',
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
            return;
        }

        // 记录变更
        $oldSettings = $this->brandingModel->getSettings();
        $beforeState = LoginPageSettingsLogSnapshot::brandingFromRow($oldSettings);

        // 更新设置
        $this->brandingModel->updateSettings($data, $operatorId ?: null);

        $newSettings = $this->brandingModel->getSettings();
        LoginPageSettingsOperationLog::logBrandingEditSuccess(
            $input,
            $beforeState,
            LoginPageSettingsLogSnapshot::brandingFromRow($newSettings),
            $operatorId
        );

        // 记录变更日志
        $this->changeLogModel->logChange([
            'settingType' => 'branding',
            'settingName' => 'Login Page Branding',
            'oldValue' => json_encode($oldSettings),
            'newValue' => json_encode($data),
            'changedBy' => $operatorId ?: null
        ]);

        Response::success(null, 'Branding settings updated successfully');
    }

    /**
     * 上传Logo
     * POST /api/login-settings/branding/upload-logo
     */
    public function uploadLogo() {
        $this->requireAuth();
        $input = LoginPageSettingsOperationLog::inputFromRequest();
        $operatorId = $this->resolveOperatorId();

        if (!isset($_FILES['logo'])) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPageBrandingUploadLogoFailure',
                'No file uploaded',
                $operatorId
            );
            Response::error('No file uploaded', 400);
        }

        try {
            $logoPath = $this->brandingModel->uploadLogo($_FILES['logo']);

            // 更新设置
            $this->brandingModel->updateSettings([
                'logoType' => 'image',
                'logoImagePath' => $logoPath
            ], $operatorId ?: null);

            LoginPageSettingsOperationLog::logBrandingUploadLogoSuccess($input, $operatorId);

            Response::success(['logoPath' => $logoPath], 'Logo uploaded successfully');
        } catch (Exception $e) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPageBrandingUploadLogoFailure',
                $e->getMessage(),
                $operatorId
            );
            Response::error($e->getMessage(), 400);
        }
    }

    // ========== Registration Form Fields ==========

    /**
     * 获取所有表单字段
     * GET /api/login-settings/form-fields
     */
    public function getFormFields() {
        $fields = $this->formFieldsModel->getAllFields();
        Response::success($fields);
    }

    /**
     * 获取启用的表单字段
     * GET /api/login-settings/form-fields/enabled
     */
    public function getEnabledFormFields() {
        $fields = $this->formFieldsModel->getEnabledFields();
        Response::success($fields);
    }

    /**
     * 创建表单字段
     * POST /api/login-settings/form-fields
     */
    public function createFormField() {
        $this->requireAuth();
        $operatorId = $this->resolveOperatorId();

        $data = json_decode(file_get_contents('php://input'), true);
        $input = LoginPageSettingsOperationLog::inputFromRequest(is_array($data) ? $data : null);

        if (!is_array($data)) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'add',
                'loginPageFormFieldAddFailure',
                'Invalid JSON body',
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
            return;
        }

        $errors = Validator::validateData($data, [
            'fieldId' => 'required',
            'fieldName' => 'required',
            'fieldType' => 'required'
        ]);
        if (!empty($errors)) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'add',
                'loginPageFormFieldAddFailure',
                OperationLogTextHelpers::validationErrorsToMessage($errors),
                $operatorId
            );
            Response::validationError($errors);
            return;
        }

        $data['isEnabled'] = $this->normalizeBoolean($data['isEnabled'] ?? null) ?? 1;
        $data['isRequired'] = $this->normalizeBoolean($data['isRequired'] ?? null) ?? 0;
        $data['isMandatory'] = 0;

        $fieldId = $this->formFieldsModel->create($data);

        LoginPageSettingsOperationLog::logFormFieldAddSuccess($input, $data['fieldName'] ?? '', $operatorId);

        // 记录变更
        $this->changeLogModel->logChange([
            'settingType' => 'registration',
            'settingName' => 'Form Field Created: ' . $data['fieldName'],
            'oldValue' => null,
            'newValue' => json_encode($data),
            'changedBy' => $operatorId ?: null
        ]);

        Response::success(['id' => $fieldId], 'Form field created successfully', 201);
    }

    /**
     * 更新表单字段
     * PUT /api/login-settings/form-fields/:id
     */
    public function updateFormField($id) {
        $this->requireAuth();
        $operatorId = $this->resolveOperatorId();

        $data = json_decode(file_get_contents('php://input'), true);
        $input = LoginPageSettingsOperationLog::inputFromRequest(is_array($data) ? $data : null);

        if (!is_array($data)) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPageFormFieldEditFailure',
                'Invalid JSON body',
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
            return;
        }

        $oldField = $this->formFieldsModel->findById($id);
        if (!$oldField) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPageFormFieldEditFailure',
                'Form field not found',
                $operatorId
            );
            Response::notFound('Form field not found');
        }
        if (!empty($oldField['isMandatory'])) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPageFormFieldEditFailure',
                'Mandatory system fields cannot be edited here',
                $operatorId
            );
            Response::validationError([
                'isMandatory' => ['Mandatory system fields cannot be edited here']
            ]);
        }

        $beforeState = LoginPageSettingsLogSnapshot::formFieldFromRow($oldField);

        $data['isEnabled'] = $this->normalizeBoolean($data['isEnabled'] ?? null) ?? ($oldField['isEnabled'] ?? 1);
        $data['isRequired'] = $this->normalizeBoolean($data['isRequired'] ?? null) ?? ($oldField['isRequired'] ?? 0);
        $data['isMandatory'] = 0;

        $this->formFieldsModel->update($id, $data);

        $afterField = $this->formFieldsModel->findById($id);
        $fieldName = $afterField['fieldName'] ?? ($oldField['fieldName'] ?? '');
        LoginPageSettingsOperationLog::logFormFieldEditSuccess(
            $input,
            $beforeState,
            LoginPageSettingsLogSnapshot::formFieldFromRow(is_array($afterField) ? $afterField : []),
            $fieldName,
            $operatorId
        );

        // 记录变更
        $this->changeLogModel->logChange([
            'settingType' => 'registration',
            'settingName' => 'Form Field Updated: ' . ($oldField['fieldName'] ?? 'Unknown'),
            'oldValue' => json_encode($oldField),
            'newValue' => json_encode($data),
            'changedBy' => $operatorId ?: null
        ]);

        Response::success(null, 'Form field updated successfully');
    }

    /**
     * 删除表单字段
     * DELETE /api/login-settings/form-fields/:id
     */
    public function deleteFormField($id) {
        $this->requireAuth();
        $input = LoginPageSettingsOperationLog::inputFromRequest();
        $operatorId = $this->resolveOperatorId();

        try {
            $field = $this->formFieldsModel->findById($id);
            if (!$field) {
                LoginPageSettingsOperationLog::logFailure(
                    $input,
                    'delete',
                    'loginPageFormFieldDeleteFailure',
                    'Form field not found',
                    $operatorId
                );
                Response::notFound('Form field not found');
            }
            $this->formFieldsModel->deleteField($id);

            LoginPageSettingsOperationLog::logFormFieldDeleteSuccess(
                $input,
                $field['fieldName'] ?? '',
                $operatorId
            );

            // 记录变更
            $this->changeLogModel->logChange([
                'settingType' => 'registration',
                'settingName' => 'Form Field Deleted: ' . ($field['fieldName'] ?? 'Unknown'),
                'oldValue' => json_encode($field),
                'newValue' => null,
                'changedBy' => $operatorId ?: null
            ]);

            Response::success(null, 'Form field deleted successfully');
        } catch (Exception $e) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'delete',
                'loginPageFormFieldDeleteFailure',
                $e->getMessage(),
                $operatorId
            );
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * 批量更新字段顺序
     * PUT /api/login-settings/form-fields/order
     */
    public function updateFieldsOrder() {
        $this->requireAuth();
        $operatorId = $this->resolveOperatorId();

        $data = json_decode(file_get_contents('php://input'), true);
        $input = LoginPageSettingsOperationLog::inputFromRequest(is_array($data) ? $data : null);

        if (!is_array($data)) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPageFormFieldsOrderFailure',
                'Invalid JSON body',
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
            return;
        }

        if (!isset($data['orders']) || !is_array($data['orders'])) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPageFormFieldsOrderFailure',
                'Invalid data format',
                $operatorId
            );
            Response::error('Invalid data format', 400);
        }

        $this->formFieldsModel->batchUpdateOrder($data['orders']);

        LoginPageSettingsOperationLog::logFormFieldsOrderSuccess($input, $operatorId);

        Response::success(null, 'Field order updated successfully');
    }

    // ========== Password Strength Settings ==========

    /**
     * 获取密码强度设置
     * GET /api/login-settings/password-strength
     */
    public function getPasswordStrength() {
        $settings = $this->passwordStrengthModel->getSettings();
        Response::success($settings);
    }

    /**
     * 更新密码强度设置
     * PUT /api/login-settings/password-strength
     */
    public function updatePasswordStrength() {
        $this->requireAuth();
        $payload = JWT::decode(JWT::getTokenFromHeader());

        $data = json_decode(file_get_contents('php://input'), true);

        $oldSettings = $this->passwordStrengthModel->getSettings();

        $this->passwordStrengthModel->updateSettings($data);

        // 记录变更
        $this->changeLogModel->logChange([
            'settingType' => 'registration',
            'settingName' => 'Password Strength Settings',
            'oldValue' => json_encode($oldSettings),
            'newValue' => json_encode($data),
            'changedBy' => $payload['userId'] ?? null
        ]);

        Response::success(null, 'Password strength settings updated successfully');
    }

    /**
     * 应用密码强度级别
     * POST /api/login-settings/password-strength/apply-level
     */
    public function applyPasswordLevel() {
        $this->requireAuth();
        $operatorId = $this->resolveOperatorId();

        $data = json_decode(file_get_contents('php://input'), true);
        $input = LoginPageSettingsOperationLog::inputFromRequest(is_array($data) ? $data : null);

        if (!is_array($data)) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPagePasswordLevelApplyFailure',
                'Invalid JSON body',
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
            return;
        }

        $errors = Validator::validateData($data, [
            'level' => 'required'
        ]);
        if (!empty($errors)) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPagePasswordLevelApplyFailure',
                OperationLogTextHelpers::validationErrorsToMessage($errors),
                $operatorId
            );
            Response::validationError($errors);
            return;
        }

        try {
            $oldSettings = $this->passwordStrengthModel->getSettings();

            $this->passwordStrengthModel->applyStrengthLevel($data['level']);

            $newSettings = $this->passwordStrengthModel->getSettings();

            LoginPageSettingsOperationLog::logPasswordLevelApplySuccess($input, $data['level'], $operatorId);

            // 记录变更
            $this->changeLogModel->logChange([
                'settingType' => 'registration',
                'settingName' => 'Password Strength Level: ' . $data['level'],
                'oldValue' => json_encode($oldSettings),
                'newValue' => json_encode($newSettings),
                'changedBy' => $operatorId ?: null
            ]);

            Response::success(null, 'Password strength level applied successfully');
        } catch (Exception $e) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPagePasswordLevelApplyFailure',
                $e->getMessage(),
                $operatorId
            );
            Response::error($e->getMessage(), 400);
        }
    }

    // ========== Legal Documents ==========

    /**
     * 获取所有法律文档
     * GET /api/login-settings/legal-documents
     */
    public function getLegalDocuments() {
        $languageCode = $_GET['lang'] ?? 'en';
        $documents = $this->legalDocModel->getAllDocuments($languageCode);
        Response::success($documents);
    }

    /**
     * 获取活跃的法律文档
     * GET /api/login-settings/legal-documents/active
     */
    public function getActiveLegalDocuments() {
        // 后台暂未提供多语言配置入口，按 lang 过滤会出现空结果（zh 等无数据）。
        // 暂时忽略 lang，直接返回所有活跃文档；后续补上多语言后再恢复按语言过滤。
        $documents = $this->legalDocModel->getAllDocuments();
        Response::success($documents);
    }

    /**
     * 创建法律文档
     * POST /api/login-settings/legal-documents
     */
    public function createLegalDocument() {
        $this->requireAuth();
        $operatorId = $this->resolveOperatorId();

        $data = json_decode(file_get_contents('php://input'), true);
        $input = LoginPageSettingsOperationLog::inputFromRequest(is_array($data) ? $data : null);

        if (!is_array($data)) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'add',
                'loginPageLegalDocAddFailure',
                'Invalid JSON body',
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
            return;
        }

        $data['isActive'] = $this->normalizeBoolean($data['isActive'] ?? null) ?? 1;
        $data['isMandatory'] = $this->normalizeBoolean($data['isMandatory'] ?? null) ?? 0;

        $errors = Validator::validateData($data, [
            'documentType' => 'required',
            'title' => 'required',
            'content' => 'required'
        ]);
        if (!empty($errors)) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'add',
                'loginPageLegalDocAddFailure',
                OperationLogTextHelpers::validationErrorsToMessage($errors),
                $operatorId
            );
            Response::validationError($errors);
            return;
        }

        if (!$this->legalDocModel->isTitleAvailable(
            $data['title'],
            $data['version'] ?? '1.0',
            $data['languageCode'] ?? 'en')
        ) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'add',
                'loginPageLegalDocAddFailure',
                'A legal document with the same title and version already exists.',
                $operatorId
            );
            Response::error('A legal document with the same title and version already exists.', 400);
        }

        $data['updatedBy'] = $operatorId ?: null;
        $docId = $this->legalDocModel->create($data);

        LoginPageSettingsOperationLog::logLegalDocAddSuccess($input, $data['title'] ?? '', $operatorId);

        // 记录变更
        $this->changeLogModel->logChange([
            'settingType' => 'legal',
            'settingName' => 'Legal Document Created: ' . $data['title'],
            'oldValue' => null,
            'newValue' => json_encode($data),
            'changedBy' => $operatorId ?: null
        ]);

        Response::success(['id' => $docId], 'Legal document created successfully', 201);
    }

    /**
     * 更新法律文档
     * PUT /api/login-settings/legal-documents/:id
     */
    public function updateLegalDocument($id) {
        $this->requireAuth();
        $operatorId = $this->resolveOperatorId();

        $data = json_decode(file_get_contents('php://input'), true);
        $input = LoginPageSettingsOperationLog::inputFromRequest(is_array($data) ? $data : null);

        if (!is_array($data)) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPageLegalDocEditFailure',
                'Invalid JSON body',
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
            return;
        }

        $oldDoc = $this->legalDocModel->findById($id);
        if (!$oldDoc) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPageLegalDocEditFailure',
                'Legal document not found',
                $operatorId
            );
            Response::notFound('Legal document not found');
        }

        $data['isActive'] = $this->normalizeBoolean($data['isActive'] ?? null) ?? ($oldDoc['isActive'] ?? 1);
        $data['isMandatory'] = $this->normalizeBoolean($data['isMandatory'] ?? null) ?? ($oldDoc['isMandatory'] ?? 0);

        if ($oldDoc['title'] != $data['title'] && !$this->legalDocModel->isTitleAvailable(
            $data['title'],
            $data['version'] ?? $oldDoc['version'],
            $data['languageCode'] ?? $oldDoc['languageCode']
        )) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPageLegalDocEditFailure',
                'A legal document with the same title and version already exists.',
                $operatorId
            );
            Response::error('A legal document with the same title and version already exists.', 400);
        }

        $data['updatedBy'] = $operatorId ?: null;
        $this->legalDocModel->update($id, $data);

        LoginPageSettingsOperationLog::logLegalDocEditSuccess(
            $input,
            $data['title'] ?? ($oldDoc['title'] ?? ''),
            $operatorId
        );

        // 记录变更
        $this->changeLogModel->logChange([
            'settingType' => 'legal',
            'settingName' => 'Legal Document Updated: ' . ($oldDoc['title'] ?? 'Unknown'),
            'oldValue' => json_encode($oldDoc),
            'newValue' => json_encode($data),
            'changedBy' => $operatorId ?: null
        ]);

        Response::success(null, 'Legal document updated successfully');
    }

    /**
     * 删除法律文档
     * DELETE /api/login-settings/legal-documents/:id
     */
    public function deleteLegalDocument($id) {
        $this->requireAuth();
        $input = LoginPageSettingsOperationLog::inputFromRequest();
        $operatorId = $this->resolveOperatorId();

        $doc = $this->legalDocModel->findById($id);
        if (!$doc) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'delete',
                'loginPageLegalDocDeleteFailure',
                'Legal document not found',
                $operatorId
            );
            Response::notFound('Legal document not found');
        }

        $this->legalDocModel->delete($id);

        LoginPageSettingsOperationLog::logLegalDocDeleteSuccess($input, $doc['title'] ?? '', $operatorId);

        // 记录变更
        $this->changeLogModel->logChange([
            'settingType' => 'legal',
            'settingName' => 'Legal Document Deleted: ' . ($doc['title'] ?? 'Unknown'),
            'oldValue' => json_encode($doc),
            'newValue' => null,
            'changedBy' => $operatorId ?: null
        ]);

        Response::success(null, 'Legal document deleted successfully');
    }

    // ========== Language Packs ==========

    /**
     * 获取所有语言包
     * GET /api/login-settings/language-packs
     */
    public function getLanguagePacks() {
        $languages = $this->languagePackModel->getLanguageList();
        Response::success($languages);
    }

    /**
     * 获取启用的语言包
     * GET /api/login-settings/language-packs/enabled
     */
    public function getEnabledLanguagePacks() {
        $languages = $this->languagePackModel->getEnabledLanguages();
        Response::success($languages);
    }

    /**
     * 获取翻译
     * GET /api/login-settings/language-packs/:code/translations
     */
    public function getTranslations($languageCode) {
        $translations = $this->languagePackModel->getTranslations($languageCode);
        Response::success($translations);
    }

    /**
     * 上传语言包
     * POST /api/login-settings/language-packs
     */
    public function uploadLanguagePack() {
        $this->requireAuth();
        $operatorId = $this->resolveOperatorId();

        $data = json_decode(file_get_contents('php://input'), true);
        $input = LoginPageSettingsOperationLog::inputFromRequest(is_array($data) ? $data : null);

        if (!is_array($data)) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'add',
                'loginPageLanguagePackUploadFailure',
                'Invalid JSON body',
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
            return;
        }

        try {
            $langId = $this->languagePackModel->uploadLanguagePack($data);

            LoginPageSettingsOperationLog::logLanguagePackUploadSuccess(
                $input,
                $data['languageName'] ?? '',
                $data['languageCode'] ?? '',
                $operatorId
            );

            // 记录变更
            $this->changeLogModel->logChange([
                'settingType' => 'language',
                'settingName' => 'Language Pack Uploaded: ' . ($data['languageName'] ?? 'Unknown'),
                'oldValue' => null,
                'newValue' => json_encode($data),
                'changedBy' => $operatorId ?: null
            ]);

            Response::success(['id' => $langId], 'Language pack uploaded successfully', 201);
        } catch (Exception $e) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'add',
                'loginPageLanguagePackUploadFailure',
                $e->getMessage(),
                $operatorId
            );
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * 更新语言包
     * PUT /api/login-settings/language-packs/:code
     */
    public function updateLanguagePack($languageCode) {
        $this->requireAuth();
        $operatorId = $this->resolveOperatorId();

        $data = json_decode(file_get_contents('php://input'), true);
        $input = LoginPageSettingsOperationLog::inputFromRequest(is_array($data) ? $data : null);

        if (!is_array($data)) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPageLanguagePackEditFailure',
                'Invalid JSON body',
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
            return;
        }

        try {
            $oldPack = $this->languagePackModel->getByCode($languageCode);
            if (!$oldPack) {
                LoginPageSettingsOperationLog::logFailure(
                    $input,
                    'edit',
                    'loginPageLanguagePackEditFailure',
                    'Language pack not found',
                    $operatorId
                );
                Response::notFound('Language pack not found');
            }

            $this->languagePackModel->updateLanguagePack($languageCode, $data);

            if (array_key_exists('isEnabled', $data)) {
                LoginPageSettingsOperationLog::logLanguagePackEditSuccess(
                    $input,
                    $languageCode,
                    $oldPack['isEnabled'] ?? 0,
                    $data['isEnabled'],
                    $operatorId
                );
            }

            // 记录变更
            $this->changeLogModel->logChange([
                'settingType' => 'language',
                'settingName' => 'Language Pack Updated: ' . $languageCode,
                'oldValue' => json_encode($oldPack),
                'newValue' => json_encode($data),
                'changedBy' => $operatorId ?: null
            ]);

            Response::success(null, 'Language pack updated successfully');
        } catch (Exception $e) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPageLanguagePackEditFailure',
                $e->getMessage(),
                $operatorId
            );
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * 设置默认语言
     * POST /api/login-settings/language-packs/set-default
     */
    public function setDefaultLanguage() {
        $this->requireAuth();
        $operatorId = $this->resolveOperatorId();

        $data = json_decode(file_get_contents('php://input'), true);
        $input = LoginPageSettingsOperationLog::inputFromRequest(is_array($data) ? $data : null);

        if (!is_array($data)) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPageDefaultLanguageSetFailure',
                'Invalid JSON body',
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
            return;
        }

        $errors = Validator::validateData($data, [
            'languageCode' => 'required'
        ]);
        if (!empty($errors)) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPageDefaultLanguageSetFailure',
                OperationLogTextHelpers::validationErrorsToMessage($errors),
                $operatorId
            );
            Response::validationError($errors);
            return;
        }

        $this->languagePackModel->setDefault($data['languageCode']);

        LoginPageSettingsOperationLog::logDefaultLanguageSetSuccess($input, $data['languageCode'], $operatorId);

        // 记录变更
        $this->changeLogModel->logChange([
            'settingType' => 'language',
            'settingName' => 'Default Language Set: ' . $data['languageCode'],
            'oldValue' => null,
            'newValue' => $data['languageCode'],
            'changedBy' => $operatorId ?: null
        ]);

        Response::success(null, 'Default language set successfully');
    }

    // ========== IP Language Detection ==========

    /**
     * IP语言检测
     * GET /api/login-settings/detect-language
     */
    public function getIpDetectionLanguage() {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        $ip = $forwarded ? trim(explode(',', $forwarded)[0]) : null;
        if (!$ip) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        }

        $languageCode = $this->ipLanguageModel->detectLanguageByIp($ip ?? '');
        Response::success(['lang' => $languageCode]);
    }

    /**
     * 获取IP语言检测设置
     * GET /api/login-settings/ip-language-detection
     */
    public function getIpLanguageDetection() {
        $settings = $this->ipLanguageModel->getSettings();
        Response::success($settings);
    }

    /**
     * 更新IP语言检测设置
     * PUT /api/login-settings/ip-language-detection
     */
    public function updateIpLanguageDetection() {
        $this->requireAuth();
        $operatorId = $this->resolveOperatorId();

        $data = json_decode(file_get_contents('php://input'), true);
        $input = LoginPageSettingsOperationLog::inputFromRequest(is_array($data) ? $data : null);

        if (!is_array($data)) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPageIpLanguageDetectionEditFailure',
                'Invalid JSON body',
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
            return;
        }

        $oldSettings = $this->ipLanguageModel->getSettings();
        $beforeState = LoginPageSettingsLogSnapshot::ipLanguageFromRow($oldSettings);

        $this->ipLanguageModel->updateSettings($data);

        $newSettings = $this->ipLanguageModel->getSettings();
        LoginPageSettingsOperationLog::logIpLanguageDetectionEditSuccess(
            $input,
            $beforeState,
            LoginPageSettingsLogSnapshot::ipLanguageFromRow($newSettings),
            $operatorId
        );

        // 记录变更
        $this->changeLogModel->logChange([
            'settingType' => 'language',
            'settingName' => 'IP Language Detection Settings',
            'oldValue' => json_encode($oldSettings),
            'newValue' => json_encode($data),
            'changedBy' => $operatorId ?: null
        ]);

        Response::success(null, 'IP language detection settings updated successfully');
    }

    // ========== Email Verification Settings ==========

    /**
     * 获取邮件验证设置
     * GET /api/login-settings/email-verification
     */
    public function getEmailVerification() {
        $settings = $this->emailVerificationModel->getSettings();
        Response::success($settings);
    }

    /**
     * 更新邮件验证设置
     * PUT /api/login-settings/email-verification
     */
    public function updateEmailVerification() {
        $this->requireAuth();
        $operatorId = $this->resolveOperatorId();

        $data = json_decode(file_get_contents('php://input'), true);
        $input = LoginPageSettingsOperationLog::inputFromRequest(is_array($data) ? $data : null);

        if (!is_array($data)) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPageEmailVerificationEditFailure',
                'Invalid JSON body',
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
            return;
        }

        $oldSettings = $this->emailVerificationModel->getSettings();
        $beforeState = LoginPageSettingsLogSnapshot::emailVerificationFromRow($oldSettings);

        $this->emailVerificationModel->updateSettings($data);

        $newSettings = $this->emailVerificationModel->getSettings();
        LoginPageSettingsOperationLog::logEmailVerificationEditSuccess(
            $input,
            $beforeState,
            LoginPageSettingsLogSnapshot::emailVerificationFromRow($newSettings),
            $operatorId
        );

        // 记录变更
        $this->changeLogModel->logChange([
            'settingType' => 'email',
            'settingName' => 'Email Verification Settings',
            'oldValue' => json_encode($oldSettings),
            'newValue' => json_encode($data),
            'changedBy' => $operatorId ?: null
        ]);

        Response::success(null, 'Email verification settings updated successfully');
    }

    // ========== Trading Groups ==========

    /**
     * 获取已启用的交易平台列表（与客户端 Open new account 一致：来自 tradingPlatforms 表，isEnabled=1）
     * GET /api/login-settings/trading-groups/platforms
     */
    public function getTradingGroupPlatforms() {
        $platforms = $this->platformModel->getEnabledPlatforms();
        $list = [];
        foreach ($platforms as $p) {
            $list[] = [
                'key' => $p['platformKey'] ?? '',
                'name' => $p['displayName'] ?? $p['platformKey'] ?? '',
                'allowLeverageManagement' => (int)($p['allowLeverageManagement'] ?? 1),
                'accountLimit' => isset($p['accountLimit']) ? (int)$p['accountLimit'] : 1,
                'passwordMode' => $p['passwordMode'] === 'manual' ? 'manual' : 'random',
            ];
        }
        Response::success($list);
    }

    /**
     * 更新平台的开户相关配置（账户上限、默认开户密码）
     * PUT /api/login-settings/trading-groups/platforms/{platformKey}/account-settings
     */
    public function updatePlatformAccountSettings($platformKey) {
        $this->requireAuth();
        $operatorId = $this->resolveOperatorId();

        $platformKey = trim((string)$platformKey);
        $data = RequestInput::readJsonBody();
        $input = PlatformSettingsOperationLog::inputFromRequest(is_array($data) ? $data : null);

        if ($platformKey === '') {
            PlatformSettingsOperationLog::logFailure(
                $input,
                'edit',
                'platformSettingsAccountUpdateFailure',
                'platformKey is required',
                $operatorId
            );
            Response::validationError(['platformKey' => ['platformKey is required']]);
        }

        $platform = $this->platformModel->findByKey($platformKey);
        if (!$platform) {
            PlatformSettingsOperationLog::logFailure(
                $input,
                'edit',
                'platformSettingsAccountUpdateFailure',
                'Trading platform not found',
                $operatorId
            );
            Response::notFound('Trading platform not found');
        }

        if (!is_array($data)) {
            $data = [];
        }

        $beforeState = PlatformSettingsLogSnapshot::platformAccountFromRow(array_merge($platform, [
            'platformKey' => $platform['platformKey'] ?? $platformKey,
        ]));

        $update = [];

        if (array_key_exists('accountLimit', $data)) {
            if (!is_numeric($data['accountLimit']) || (int)$data['accountLimit'] < 1) {
                PlatformSettingsOperationLog::logFailure(
                    $input,
                    'edit',
                    'platformSettingsAccountUpdateFailure',
                    'Account limit must be a positive integer.',
                    $operatorId
                );
                Response::validationError(['accountLimit' => ['Account limit must be a positive integer.']]);
            }
            $update['accountLimit'] = (int)$data['accountLimit'];
        }

        if (array_key_exists('passwordMode', $data)) {
            $mode = is_string($data['passwordMode']) ? trim($data['passwordMode']) : '';
            if ($mode !== 'manual' && $mode !== 'random') {
                PlatformSettingsOperationLog::logFailure(
                    $input,
                    'edit',
                    'platformSettingsAccountUpdateFailure',
                    'Password mode must be manual or random.',
                    $operatorId
                );
                Response::validationError(['passwordMode' => ['Password mode must be manual or random.']]);
            }
            $update['passwordMode'] = $mode;
        }

        if (empty($update)) {
            PlatformSettingsOperationLog::logFailure(
                $input,
                'edit',
                'platformSettingsAccountUpdateFailure',
                'At least one field is required.',
                $operatorId
            );
            Response::validationError(['payload' => ['At least one field is required.']]);
        }

        $this->platformModel->update((int)$platform['id'], $update);

        $updated = $this->platformModel->findById((int)$platform['id']);
        $afterState = PlatformSettingsLogSnapshot::platformAccountFromRow(array_merge($updated, [
            'platformKey' => $updated['platformKey'] ?? $platformKey,
        ]));
        PlatformSettingsOperationLog::logAccountUpdateSuccess($input, $beforeState, $afterState, $operatorId);

        Response::success([
            'key' => $updated['platformKey'] ?? '',
            'name' => $updated['displayName'] ?? '',
            'accountLimit' => isset($updated['accountLimit']) ? (int)$updated['accountLimit'] : 1,
            'passwordMode' => $updated['passwordMode'] === 'manual' ? 'manual' : 'random',
        ], 'Platform account settings updated');
    }

    /**
     * 获取所有组别
     * GET /api/login-settings/trading-groups
     */
    public function getTradingGroups() {
        $platformKey = $_GET['platform'] ?? null;
        $groups = $platformKey
            ? $this->tradingGroupModel->getByPlatform($platformKey)
            : $this->tradingGroupModel->findAll([], 'trading_platforms_key ASC, name ASC');
        Response::success($groups);
    }

    /**
     * 更新交易组显示标签
     * PUT /api/login-settings/trading-groups/{id}/label
     */
    public function updateTradingGroupLabel($id) {
        $this->requireAuth();

        if (!is_numeric($id) || (int)$id <= 0) {
            Response::validationError([
                'id' => ['Trading group id must be a positive integer.']
            ]);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!array_key_exists('label', $data)) {
            Response::validationError([
                'label' => ['label is required']
            ]);
        }

        try {
            $group = $this->tradingGroupModel->updateLabel((int)$id, $data['label']);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }

        Response::success($group, 'Trading group label updated successfully');
    }

    /**
     * 更新交易组金额单位配置
     * PUT /api/login-settings/trading-groups/{id}/config
     */
    public function updateTradingGroupConfig($id) {
        $this->requireAuth();
        $operatorId = $this->resolveOperatorId();
        $payload = JWT::decode(JWT::getTokenFromHeader());

        $data = RequestInput::readJsonBody();
        $input = PlatformSettingsOperationLog::inputFromRequest(is_array($data) ? $data : null);

        if (!is_numeric($id) || (int)$id <= 0) {
            PlatformSettingsOperationLog::logFailure(
                $input,
                'edit',
                'platformSettingsGroupEditFailure',
                'Trading group id must be a positive integer.',
                $operatorId
            );
            Response::validationError([
                'id' => ['Trading group id must be a positive integer.']
            ]);
        }

        if (!is_array($data)) {
            PlatformSettingsOperationLog::logFailure(
                $input,
                'edit',
                'platformSettingsGroupEditFailure',
                'Invalid JSON body',
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
        }

        $operationLogGroupBefore = null;
        if (array_key_exists('operationLogGroupBefore', $data) && is_array($data['operationLogGroupBefore'])) {
            $operationLogGroupBefore = $data['operationLogGroupBefore'];
        }
        unset($data['operationLogGroupBefore'], $data['logSubModuleKey'], $data['operationLogSubModule']);

        if (!array_key_exists('unit', $data) && !array_key_exists('scale', $data)) {
            PlatformSettingsOperationLog::logFailure(
                $input,
                'edit',
                'platformSettingsGroupEditFailure',
                'At least one of unit or scale is required.',
                $operatorId
            );
            Response::validationError([
                'config' => ['At least one of unit or scale is required.']
            ]);
        }

        $groupId = (int)$id;
        $oldGroup = $this->tradingGroupModel->findById($groupId);
        if (!$oldGroup) {
            PlatformSettingsOperationLog::logFailure(
                $input,
                'edit',
                'platformSettingsGroupEditFailure',
                'Trading group not found',
                $operatorId
            );
            Response::notFound('Trading group not found');
        }

        if (is_array($operationLogGroupBefore)) {
            $beforeState = PlatformSettingsLogSnapshot::tradingGroupBeforeFromRequest($operationLogGroupBefore, $oldGroup);
        } else {
            $beforeState = PlatformSettingsLogSnapshot::tradingGroupFromRow($oldGroup);
        }

        try {
            $group = $this->tradingGroupModel->updateAmountConfig($groupId, $data);
        } catch (InvalidArgumentException $e) {
            PlatformSettingsOperationLog::logFailure(
                $input,
                'edit',
                'platformSettingsGroupEditFailure',
                $e->getMessage(),
                $operatorId
            );
            Response::error($e->getMessage(), 400);
        }

        $afterState = PlatformSettingsLogSnapshot::tradingGroupFromRow($group);

        $this->changeLogModel->logChange([
            'settingType' => 'trading_groups',
            'settingName' => 'Trading Group Amount Config Updated: ' . ($group['name'] ?? 'Unknown'),
            'oldValue' => json_encode([
                'unit' => $beforeState['unit'] ?? null,
                'scale' => $beforeState['scale'] ?? null,
            ]),
            'newValue' => json_encode([
                'unit' => $afterState['unit'] ?? null,
                'scale' => $afterState['scale'] ?? null,
            ]),
            'changedBy' => $payload['userId'] ?? null
        ]);

        if (is_array($operationLogGroupBefore)) {
            PlatformSettingsOperationLog::logGroupEditSuccess($input, $beforeState, $afterState, $operatorId);
        }

        Response::success($group, 'Trading group amount config updated successfully');
    }

    /**
     * 获取默认组别
     * GET /api/login-settings/trading-groups/default
     *
     * 规则：
     * 1) 请求携带有效 JWT 且该用户（clientUsers.id）是某 IB 的直属客户
     *    （ib_partner_bind.isClient=1），且该 IB 在 ib_partner_trading_groups
     *    里配置了组别 → 返回 IB 的这些组别（按 platform 过滤）。
     * 2) 未登录 / 不是任何 IB 的客户 / IB 未配置组别 → 回退到全局默认组别。
     */
    public function getDefaultTradingGroup() {
        $platformKey = $_GET['platform'] ?? null;

        $groups = $this->resolveIbTradingGroups($platformKey);
        $this->respondDefaultTradingGroups($platformKey, $groups);
    }

    /**
     * 后台按指定客户查询开户组别；客户不在 IB 下时仍回退全局默认组。
     */
    public function getDefaultTradingGroupForClient($clientUserId) {
        $platformKey = $_GET['platform'] ?? null;
        $groups = $this->resolveIbTradingGroupsForClientId((int)$clientUserId, $platformKey);
        $this->respondDefaultTradingGroups($platformKey, $groups);
    }

    private function respondDefaultTradingGroups($platformKey, $groups) {
        if ($groups === null) {
            $groups = $this->tradingGroupModel->getDefaultGroups($platformKey);
        }
        $groups = array_map(function ($group) {
            $group['scale'] = isset($group['scale']) && $group['scale'] !== null && $group['scale'] !== ''
                ? (float)$group['scale']
                : null;
            $group['unit'] = isset($group['unit']) && $group['unit'] !== ''
                ? (string)$group['unit']
                : null;
            return $group;
        }, $groups);
        $leverages = [];

        if ($platformKey !== null && $platformKey !== '') {
            $platform = $this->platformModel->findByKey($platformKey);
            if ($platform && !empty($platform['isEnabled'])) {
                $leverages = $this->leverageModel->getByPlatform((int)$platform['id'], true);
            }
        }

        Response::success([
            'groups' => $groups,
            'leverage' => $leverages,
        ]);
    }

    /**
     * 如果当前请求携带有效 JWT 且用户被绑定到某 IB，则返回该 IB 配置的交易组别。
     * 否则返回 null，表示应走全局默认组逻辑。
     *
     * 返回：
     *   null  → 未登录 / JWT 无效 / 用户不在 IB 下 / IB 未配置任何组别
     *   array → IB 配置的组别（可能按 platform 过滤后为空数组 []，
     *           此时表明该 IB 在此平台下没有开放组别，不再回退到默认）
     */
    private function resolveIbTradingGroups($platformKey) {
        $token = JWT::getTokenFromHeader();
        if (!$token) {
            return null;
        }
        try {
            $payload = JWT::decode($token);
        } catch (Exception $e) {
            return null;
        }
        $type = $payload['type'] ?? '';
        // 同时认 client（Web）和 app（移动端）：两边账号体系一致，IB 绑定要在两边都生效
        if ($type !== 'client' && $type !== 'app') {
            return null;
        }
        $userId = (int)($payload['userId'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        return $this->resolveIbTradingGroupsForClientId($userId, $platformKey);
    }

    private function resolveIbTradingGroupsForClientId($userId, $platformKey) {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return null;
        }

        $ibPartnerId = $this->ibPartnerModel->getDirectIbPartnerIdByClientUserId($userId);
        if ($ibPartnerId <= 0) {
            return null;
        }

        $groupIds = $this->ibPartnerModel->getTradingGroupIdsByIbPartnerId($ibPartnerId);
        if (empty($groupIds)) {
            return null;
        }

        return $this->tradingGroupModel->getByIds($groupIds, $platformKey);
    }

    /**
     * 设置默认组别（允许同平台多个默认组别）
     * POST /api/login-settings/trading-groups/set-default
     */
    public function setDefaultTradingGroup() {
        $this->requireAuth();
        $operatorId = $this->resolveOperatorId();
        $payload = JWT::decode(JWT::getTokenFromHeader());

        $data = RequestInput::readJsonBody();
        $input = PlatformSettingsOperationLog::inputFromRequest(is_array($data) ? $data : null);

        if (!is_array($data)) {
            PlatformSettingsOperationLog::logFailure(
                $input,
                'edit',
                'platformSettingsGroupDefaultFailure',
                'Invalid JSON body',
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
        }

        $errors = Validator::validateData($data, [
            'groupId' => 'required',
            'platformKey' => 'required|string'
        ]);
        if (!empty($errors)) {
            PlatformSettingsOperationLog::logFailure(
                $input,
                'edit',
                'platformSettingsGroupDefaultFailure',
                OperationLogTextHelpers::validationErrorsToMessage($errors),
                $operatorId
            );
            Response::validationError($errors);
        }

        $platformKey = trim($data['platformKey']);
        $groupId = $data['groupId'];
        if (!is_numeric($groupId) || (int)$groupId <= 0) {
            PlatformSettingsOperationLog::logFailure(
                $input,
                'edit',
                'platformSettingsGroupDefaultFailure',
                'Trading group id must be a positive integer.',
                $operatorId
            );
            Response::validationError([
                'groupId' => ['Trading group id must be a positive integer.']
            ], 'Trading group id must be a positive integer.');
        }

        try {
            $this->tradingGroupModel->setDefault((int)$groupId, $platformKey);
        } catch (InvalidArgumentException $e) {
            PlatformSettingsOperationLog::logFailure(
                $input,
                'edit',
                'platformSettingsGroupDefaultFailure',
                $e->getMessage(),
                $operatorId
            );
            Response::error($e->getMessage(), 400);
        }

        $group = $this->tradingGroupModel->findById((int)$groupId);
        $groupState = PlatformSettingsLogSnapshot::tradingGroupFromRow(is_array($group) ? $group : []);

        $this->changeLogModel->logChange([
            'settingType' => 'trading_groups',
            'settingName' => 'Default Trading Group Added: ' . ($group['name'] ?? 'Unknown') . ' (platform: ' . $platformKey . ')',
            'oldValue' => null,
            'newValue' => json_encode($group),
            'changedBy' => $payload['userId'] ?? null
        ]);

        PlatformSettingsOperationLog::logGroupSetDefaultSuccess($input, $groupState, $platformKey, $operatorId);

        Response::success(null, 'Default trading group added successfully');
    }

    /**
     * 取消默认组别
     * POST /api/login-settings/trading-groups/remove-default
     */
    public function removeDefaultTradingGroup() {
        $this->requireAuth();
        $operatorId = $this->resolveOperatorId();
        $payload = JWT::decode(JWT::getTokenFromHeader());

        $data = RequestInput::readJsonBody();
        $input = PlatformSettingsOperationLog::inputFromRequest(is_array($data) ? $data : null);

        if (!is_array($data)) {
            PlatformSettingsOperationLog::logFailure(
                $input,
                'edit',
                'platformSettingsGroupDefaultFailure',
                'Invalid JSON body',
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
        }

        $errors = Validator::validateData($data, [
            'groupId' => 'required',
            'platformKey' => 'required|string'
        ]);
        if (!empty($errors)) {
            PlatformSettingsOperationLog::logFailure(
                $input,
                'edit',
                'platformSettingsGroupDefaultFailure',
                OperationLogTextHelpers::validationErrorsToMessage($errors),
                $operatorId
            );
            Response::validationError($errors);
        }

        $platformKey = trim($data['platformKey']);
        $groupId = $data['groupId'];
        if (!is_numeric($groupId) || (int)$groupId <= 0) {
            PlatformSettingsOperationLog::logFailure(
                $input,
                'edit',
                'platformSettingsGroupDefaultFailure',
                'Trading group id must be a positive integer.',
                $operatorId
            );
            Response::validationError([
                'groupId' => ['Trading group id must be a positive integer.']
            ], 'Trading group id must be a positive integer.');
        }

        try {
            $this->tradingGroupModel->removeDefault((int)$groupId, $platformKey);
        } catch (InvalidArgumentException $e) {
            PlatformSettingsOperationLog::logFailure(
                $input,
                'edit',
                'platformSettingsGroupDefaultFailure',
                $e->getMessage(),
                $operatorId
            );
            Response::error($e->getMessage(), 400);
        }

        $group = $this->tradingGroupModel->findById((int)$groupId);
        $groupState = PlatformSettingsLogSnapshot::tradingGroupFromRow(is_array($group) ? $group : []);

        $this->changeLogModel->logChange([
            'settingType' => 'trading_groups',
            'settingName' => 'Default Trading Group Removed: ' . ($group['name'] ?? 'Unknown') . ' (platform: ' . $platformKey . ')',
            'oldValue' => null,
            'newValue' => json_encode($group),
            'changedBy' => $payload['userId'] ?? null
        ]);

        PlatformSettingsOperationLog::logGroupRemoveDefaultSuccess($input, $groupState, $platformKey, $operatorId);

        Response::success(null, 'Default trading group removed successfully');
    }

    /**
     * 同步交易平台组别
     * POST /api/login-settings/trading-groups/sync
     */
    public function syncTradingGroups() {
        $this->requireAuth();
        $operatorId = $this->resolveOperatorId();
        $payload = JWT::decode(JWT::getTokenFromHeader());

        $data = RequestInput::readJsonBody();
        $input = PlatformSettingsOperationLog::inputFromRequest(is_array($data) ? $data : null);
        $platformKey = is_array($data) ? ($data['platform'] ?? null) : null;

        if (!$platformKey) {
            PlatformSettingsOperationLog::logFailure(
                $input,
                'import',
                'platformSettingsGroupSyncFailure',
                'Platform key is required',
                $operatorId
            );
            Response::error('Platform key is required', 400);
        }

        $appConfig = require __DIR__ . '/../config/app.php';
        $tradingPlatforms = $appConfig['trading_platforms'] ?? [];

        if (!isset($tradingPlatforms[$platformKey]) || !$tradingPlatforms[$platformKey]) {
            PlatformSettingsOperationLog::logFailure(
                $input,
                'import',
                'platformSettingsGroupSyncFailure',
                'Platform is not configured or enabled',
                $operatorId
            );
            Response::error('Platform is not configured or enabled', 400);
        }

        $results = [];

        try {
            // FinancePro 平台
            if ($platformKey === 'financepro') {
                $apiClient = new FinanceProApiClient();
                $endpoint = $appConfig['integrations']['finance_pro']['get_all_groups'] ?? '';
                if(empty($endpoint)){
                    $results[$platformKey] = [
                        'success' => false,
                        'error' => 'Trading Platform group sync is not yet implemented. Waiting for platform documentation.'
                    ];
                }
                // 调用接口，includeDisabled=false
                $response = $apiClient->request($endpoint, 'GET', null, [
                    'expect_success_key' => 'Success',
                    'success_value' => true
                ]);

                if (isset($response['ResData']) && is_array($response['ResData'])) {
                    $syncResult = $this->tradingGroupModel->syncGroups($platformKey, $response['ResData']);
                    $results[$platformKey] = [
                        'success' => true,
                        'synced' => $syncResult['synced'],
                        'updated' => $syncResult['updated'],
                        'total' => $syncResult['total']
                    ];
                } else {
                    $results[$platformKey] = [
                        'success' => false,
                        'error' => 'Invalid response format'
                    ];
                }
            }
            // MT4 平台
            elseif ($platformKey === 'mt4') {
                $mt4Client = new Mt4ApiClient($appConfig['integrations']['mt4'] ?? []);
                $groups = $mt4Client->getGroups();

                if (!is_array($groups)) {
                    $results[$platformKey] = [
                        'success' => false,
                        'error' => 'Invalid MT4 response format'
                    ];
                } else {
                    $syncResult = $this->tradingGroupModel->syncMt4Groups($groups);
                    $results[$platformKey] = [
                        'success' => true,
                        'synced' => $syncResult['synced'],
                        'updated' => $syncResult['updated'],
                        'total' => $syncResult['total']
                    ];
                }
            }
            // MT5 平台：默认走只读网关；group_symbol_sync_use_webapi=true 时回退 WebAPI 二进制。
            // 两个客户端的 getGroups() 出参都是 PascalCase，syncMt5Groups 不用区分来源。
            elseif ($platformKey === 'mt5') {
                $useWebapi = !empty($appConfig['integrations']['mt5']['group_symbol_sync_use_webapi']);
                $mt5Client = $useWebapi
                    ? new Mt5ApiClient($appConfig['integrations']['mt5'] ?? [])
                    : new Mt5GatewayApiClient($appConfig['integrations']['mt5'] ?? []);
                $groups = $mt5Client->getGroups();

                if (!is_array($groups)) {
                    $results[$platformKey] = [
                        'success' => false,
                        'error' => 'Invalid MT5 response format'
                    ];
                } else {
                    $syncResult = $this->tradingGroupModel->syncMt5Groups($groups);
                    $results[$platformKey] = [
                        'success' => true,
                        'synced' => $syncResult['synced'],
                        'updated' => $syncResult['updated'],
                        'total' => $syncResult['total']
                    ];
                }
            }
            else {
                PlatformSettingsOperationLog::logFailure(
                    $input,
                    'import',
                    'platformSettingsGroupSyncFailure',
                    'Unsupported platform: ' . $platformKey,
                    $operatorId
                );
                Response::error('Unsupported platform: ' . $platformKey, 400);
            }

            $this->changeLogModel->logChange([
                'settingType' => 'trading_groups',
                'settingName' => 'Trading Groups Synced: ' . $platformKey,
                'oldValue' => null,
                'newValue' => json_encode($results),
                'changedBy' => $payload['userId'] ?? null
            ]);

            $platformResult = $results[$platformKey] ?? null;
            if (is_array($platformResult) && empty($platformResult['success'])) {
                PlatformSettingsOperationLog::logFailure(
                    $input,
                    'import',
                    'platformSettingsGroupSyncFailure',
                    (string) ($platformResult['error'] ?? 'Sync failed'),
                    $operatorId
                );
            } else {
                PlatformSettingsOperationLog::logGroupSyncSuccess(
                    $input,
                    $platformKey,
                    is_array($platformResult) ? $platformResult : [],
                    $operatorId
                );
            }

            Response::success($results, 'Trading groups synced successfully');
        } catch (Exception $e) {
            PlatformSettingsOperationLog::logFailure(
                $input,
                'import',
                'platformSettingsGroupSyncFailure',
                'Failed to sync trading groups: ' . $e->getMessage(),
                $operatorId
            );
            Response::error('Failed to sync trading groups: ' . $e->getMessage(), 500);
        }
    }

    // ========== Change Log ==========

    /**
     * 获取变更历史
     * GET /api/login-settings/change-log
     */
    public function getChangeLog() {
        $this->requireAuth();

        $settingType = $_GET['type'] ?? null;
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['perPage'] ?? 50;

        $logs = $this->changeLogModel->getChangeHistory($settingType, $page, $perPage);

        Response::success($logs);
    }

    // ========== Country Settings ==========

    /**
     * 获取KYC国家列表
     * GET /api/login-settings/countries
     */
    public function getCountriesList() {
        $this->requireAuth();
        $countries = $this->countryListModel->getAllCountries();
        Response::success($countries);
    }

    /**
     * 批量更新KYC国家启用状态
     * PUT /api/login-settings/countries/status
     *
     * Body: {
     *   "enableIds": [1, 2, 3],
     *   "disableIds": [4, 5]
     * }
     */
    public function updateCountriesStatus() {
        $this->requireAuth();
        $operatorId = $this->resolveOperatorId();

        $inputRaw = json_decode(file_get_contents('php://input'), true) ?? [];
        $input = LoginPageSettingsOperationLog::inputFromRequest(is_array($inputRaw) ? $inputRaw : null);

        if (!is_array($inputRaw)) {
            LoginPageSettingsOperationLog::logFailure(
                $input,
                'edit',
                'loginPageCountriesStatusFailure',
                'Invalid JSON body',
                $operatorId
            );
            Response::error('Invalid JSON body', 400);
            return;
        }

        $enableIds = $inputRaw['enableIds'] ?? [];
        $disableIds = $inputRaw['disableIds'] ?? [];

        $enableIds = $this->normalizeIdList($enableIds, 'enableIds');
        $disableIds = $this->normalizeIdList($disableIds, 'disableIds');

        $updated = 0;
        if (!empty($enableIds)) {
            $updated += $this->countryListModel->updateCountryStatus($enableIds, 1);
        }
        if (!empty($disableIds)) {
            $updated += $this->countryListModel->updateCountryStatus($disableIds, 0);
        }

        if (!empty($enableIds) || !empty($disableIds)) {
            LoginPageSettingsOperationLog::logCountriesStatusSuccess(
                $input,
                count($enableIds),
                count($disableIds),
                $operatorId
            );
        }

        // 记录变更
        $this->changeLogModel->logChange([
            'settingType' => 'countries',
            'settingName' => 'Countries status updated',
            'oldValue' => null,
            'newValue' => json_encode([
                'enableIds' => $enableIds,
                'disableIds' => $disableIds,
                'updated' => $updated
            ]),
            'changedBy' => $operatorId ?: null
        ]);

        Response::success([
            'updated' => $updated,
            'enabled' => count($enableIds),
            'disabled' => count($disableIds)
        ], 'Countries updated successfully');
    }

    // ========== Helper Methods ==========

    private function requireAuth() {
        $token = JWT::getTokenFromHeader();
        if (!$token) {
            Response::unauthorized();
        }

        try {
            JWT::decode($token);
        } catch (Exception $e) {
            Response::unauthorized('Invalid or expired token');
        }
    }

    private function normalizeBoolean($value) {
        if ($value === null) {
            return null;
        }

        return ($value === true || $value === 1 || $value === '1') ? 1 : 0;
    }

    private function normalizeIdList($value, $fieldName) {
        if ($value === null || $value === []) {
            return [];
        }
        if (!is_array($value)) {
            Response::validationError([$fieldName => "{$fieldName} must be an array of numeric ids"]);
        }

        $invalid = [];
        foreach ($value as $item) {
            if (is_int($item)) {
                continue;
            }
            if (is_string($item) && ctype_digit($item)) {
                continue;
            }
            $invalid[] = $item;
        }

        if (!empty($invalid)) {
            Response::validationError([$fieldName => "{$fieldName} must contain only numeric ids"]);
        }

        $ids = array_values(array_unique(array_map('intval', $value)));
        $ids = array_filter($ids, function ($id) {
            return $id > 0;
        });

        return array_values($ids);
    }

    private function resolveOperatorId() {
        $token = JWT::getTokenFromHeader();
        if (!$token) {
            return 0;
        }
        try {
            $payload = JWT::decode($token);
        } catch (Exception $e) {
            return 0;
        }
        return (int) ($payload['userId'] ?? 0);
    }
}
