<?php
/**
 * App 端 (api/external/*) 业务接口桥接
 *
 * 把 App 路由直接代理到现有的 client / settings / countries / trading 控制器，
 * 避免重复实现业务逻辑。authenticate 已经由路由层在调用前完成：
 *   - 受保护方法之前调用 AppAuthMiddleware::authenticate()
 *   - 公开方法不需要任何中间件
 *
 * 由于 ClientAuthContext::getCurrentClientUserId() 已支持 type='app'，
 * 下游 controller 里的 requireClient() 等会无缝识别出当前用户。
 */

require_once __DIR__ . '/../utils/Response.php';

class ExternalAppBridgeController {

    // ========== 公开认证类（复用 ClientAuthController） ==========

    public function register() {
        require_once __DIR__ . '/ClientAuthController.php';
        (new ClientAuthController())->register();
    }

    public function forgotPassword() {
        require_once __DIR__ . '/ClientAuthController.php';
        (new ClientAuthController())->forgotPassword();
    }

    public function resendVerification() {
        require_once __DIR__ . '/ClientAuthController.php';
        (new ClientAuthController())->resendVerification();
    }

    // ========== 公开设置类（复用 LoginPageSettingsController / CountryController） ==========

    public function getCountriesAll() {
        require_once __DIR__ . '/CountryController.php';
        (new CountryController())->list(true);
    }

    /**
     * 注册页一次性拉取所有前置数据，替代原先 5 个独立接口：
     *   form-fields/enabled、password-strength、legal-documents/active、
     *   email-verification、countries/without-all
     *
     * 直接走底层 model，避免 LoginPageSettingsController / CountryController
     * 里的 Response::success() exit 终止整个响应。
     *
     * Query: ?lang=en  仅用于 legalDocuments 的多语言版本筛选
     */
    public function getRegisterBootstrap() {
        require_once __DIR__ . '/../models/RegistrationFormField.php';
        require_once __DIR__ . '/../models/PasswordStrengthSettings.php';
        require_once __DIR__ . '/../models/LegalDocument.php';
        require_once __DIR__ . '/../models/EmailVerificationSettings.php';
        require_once __DIR__ . '/../models/Countrylist.php';

        $languageCode = $_GET['lang'] ?? 'en';

        $formFieldModel = new RegistrationFormField();

        Response::success([
            // 只回运营可配置的"可选字段"，mandatory 已拆到 required
            'formFields'        => $formFieldModel->getEnabledOptionalFields(),
            'required'          => $formFieldModel->getMandatoryFieldIds(),
            'passwordStrength'  => (new PasswordStrengthSettings())->getSettings(),
            'legalDocuments'    => (new LegalDocument())->getActiveDocuments($languageCode),
            'emailVerification' => (new EmailVerificationSettings())->getSettings(),
            // 禁用国家 code 列表（不含 ALL 项），App 端用作注册时的 blocked countries
            'countries'         => (new Countrylist())->getCountryCodesByActive(false),
        ]);
    }

    // ========== 受保护：交易类（复用 TradingAccountController / TradingPlatformLeverageController） ==========
    // 调用前路由层已 AppAuthMiddleware::authenticate()，下游 requireClient() 通过 ClientAuthContext 拿到 userId。
    // 开户相关接口（platforms / create / default trading-group）已下线，App 端通过 WebView /app/openaccount 完成开户。

    public function getAccounts() {
        require_once __DIR__ . '/TradingAccountController.php';
        (new TradingAccountController())->getAccounts();
    }

    public function transactionHistory() {
        require_once __DIR__ . '/TradingAccountController.php';
        (new TradingAccountController())->transactionHistory();
    }

    /**
     * 取消一笔 pending 的 deposit / withdrawal / internal transfer。
     * body: { id: int, type: 'deposit' | 'withdraw' | 'transfer' }
     *
     * 直接复用各自原有的 cancel 方法（owner 校验、状态校验、平台回滚、状态写回都在下游），
     * 这里只做 type 分发和参数校验。reason 不必填，下游会落到默认 'Cancelled by client'。
     */
    public function cancelTransaction() {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $type = isset($data['type']) ? strtolower(trim((string)$data['type'])) : '';

        $errors = [];
        if ($id <= 0) {
            $errors['id'] = ['id is required'];
        }
        if (!in_array($type, ['deposit', 'withdraw', 'transfer'], true)) {
            $errors['type'] = ['type must be one of: deposit, withdraw, transfer'];
        }
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        switch ($type) {
            case 'deposit':
                require_once __DIR__ . '/DepositController.php';
                (new DepositController())->cancel($id);
                return;
            case 'withdraw':
                require_once __DIR__ . '/WithdrawalController.php';
                (new WithdrawalController())->cancel($id);
                return;
            case 'transfer':
                require_once __DIR__ . '/InternalTransferController.php';
                (new InternalTransferController())->cancel($id);
                return;
        }
    }

    public function getPlatformLeverages($platformKey) {
        require_once __DIR__ . '/TradingPlatformLeverageController.php';
        (new TradingPlatformLeverageController())->getUserLeverages($platformKey);
    }

    // ========== 受保护：当前用户信息 ==========

    /**
     * 仅返回 user 字段（App 端把原 web /me 的聚合响应拆成 user / kyc / ib 三个接口分别拉取）
     */
    public function me() {
        require_once __DIR__ . '/ClientAuthController.php';
        (new ClientAuthController())->meUserOnly();
    }

    /**
     * 仅返回 kycStatus 摘要
     */
    public function meKyc() {
        require_once __DIR__ . '/ClientAuthController.php';
        (new ClientAuthController())->meKycOnly();
    }

    // ========== 受保护：客户通知 ==========

    public function listNotifications() {
        require_once __DIR__ . '/ClientNotificationController.php';
        (new ClientNotificationController())->listForClient();
    }

    public function markReadNotification() {
        require_once __DIR__ . '/ClientNotificationController.php';
        (new ClientNotificationController())->markReadForClient();
    }

    public function markAllReadNotifications() {
        require_once __DIR__ . '/ClientNotificationController.php';
        (new ClientNotificationController())->markAllReadForClient();
    }
}
