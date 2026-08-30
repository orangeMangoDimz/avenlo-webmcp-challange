<?php
/**
 * Transaction Settings Controller
 * 负责交易设置相关接口
 */

require_once __DIR__ . '/../models/CryptoDepositAddress.php';
require_once __DIR__ . '/../models/PaymentGatewaySetting.php';
require_once __DIR__ . '/../models/PaymentGatewayFundingSetting.php';
require_once __DIR__ . '/../models/PaymentGatewayFeeRule.php';
require_once __DIR__ . '/../models/PaymentGatewayQuickAmount.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplate.php';
require_once __DIR__ . '/../models/TransactionLimit.php';
require_once __DIR__ . '/../models/TransactionNotificationSetting.php';
require_once __DIR__ . '/../models/PaymentMethod.php';
require_once __DIR__ . '/../models/PaymentSupportQuestion.php';
require_once __DIR__ . '/../models/TransactionDisplayContent.php';
require_once __DIR__ . '/../models/CurrencyExchangeRate.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../services/OperationLog/TransactionSettingsOperationLog.php';
require_once __DIR__ . '/../services/XLinkService.php';

class TransactionSettingsController {
    private const PAYMENT_SUPPORT_QUESTION_TYPES = ['text', 'date', 'tel', 'email', 'single_choice'];
    private const DEFAULT_GATEWAY_AMOUNT_DECIMAL_PLACES = 2;
    private const MAX_GATEWAY_AMOUNT_DECIMAL_PLACES = 4;

    private $cryptoAddressModel;
    private $gatewayModel;
    private $gatewayFeeModel;
    private $gatewayFeeRuleModel;
    private $quickAmountModel;
    private $withdrawalTemplateModel;
    private $limitModel;
    private $notificationModel;
    private $paymentMethodModel;
    private $paymentSupportQuestionModel;
    private $transactionDisplayContentModel;
    private $exchangeRateModel;

    public function __construct() {
        $this->cryptoAddressModel = new CryptoDepositAddress();
        $this->gatewayModel = new PaymentGatewaySetting();
        $this->gatewayFeeModel = new PaymentGatewayFundingSetting();
        $this->gatewayFeeRuleModel = new PaymentGatewayFeeRule();
        $this->quickAmountModel = new PaymentGatewayQuickAmount();
        $this->withdrawalTemplateModel = new WithdrawalVerificationTemplate();
        $this->limitModel = new TransactionLimit();
        $this->notificationModel = new TransactionNotificationSetting();
        $this->paymentMethodModel = new PaymentMethod();
        $this->paymentSupportQuestionModel = new PaymentSupportQuestion();
        $this->transactionDisplayContentModel = new TransactionDisplayContent();
        $this->exchangeRateModel = new CurrencyExchangeRate();
    }

    /**
     * 获取所有交易设置
     * GET /api/transaction-settings
     */
    public function index() {
        $this->requireAdmin();

        $settings = [
            'cryptoAddresses' => $this->cryptoAddressModel->getActiveAddresses(),
            'gateways' => $this->gatewayModel->getEnabledGateways(),
            'gatewayFees' => $this->gatewayFeeModel->getAllWithGateways(),
            'displayContents' => $this->transactionDisplayContentModel->getAllContents(),
            'limits' => [
                'deposit' => $this->limitModel->getActiveLimits('deposit'),
                'withdrawal' => $this->limitModel->getActiveLimits('withdrawal')
            ],
            'notifications' => $this->notificationModel->getAllGrouped()
        ];

        Response::success($settings);
    }

    /**
     * 获取加密货币地址配置
     * GET /api/transaction-settings/crypto-addresses?gateway=alchemy_pay
     */
    public function getCryptoAddresses() {
        $gatewayKey = $_GET['gateway'] ?? null;

        // 如果指定了gateway，根据gateway的supportedCryptoCurrencies获取对应的地址
        if ($gatewayKey) {
            $gateway = $this->gatewayModel->findByKey($gatewayKey);
            if (!$gateway || !$gateway['isEnabled']) {
                Response::success([]);
            }

            // 解析支持的加密货币
            $supportedCryptos = [];
            if (!empty($gateway['supportedCryptoCurrencies'])) {
                if (is_string($gateway['supportedCryptoCurrencies'])) {
                    $supportedCryptos = json_decode($gateway['supportedCryptoCurrencies'], true) ?: [];
                } else {
                    $supportedCryptos = $gateway['supportedCryptoCurrencies'];
                }
            }

            if (empty($supportedCryptos)) {
                Response::success([]);
            }

            // 币种代码到methodKey的映射
            $cryptoToMethodKey = [
                'BTC' => 'bitcoin',
                'ETH' => 'ethereum',
                'USDT' => 'usdt',
                'USDC' => 'usdc',
                'BNB' => 'bnb',
                'MATIC' => 'matic',
                'SOL' => 'sol'
            ];

            // 获取所有支付方式
            $allMethods = $this->paymentMethodModel->getCryptoMethods();

            // 根据支持的币种找到对应的paymentMethodId
            $methodIds = [];
            foreach ($supportedCryptos as $cryptoCode) {
                $methodKey = $cryptoToMethodKey[$cryptoCode] ?? strtolower($cryptoCode);
                foreach ($allMethods as $method) {
                    if ($method['methodKey'] === $methodKey) {
                        $methodIds[] = $method['id'];
                        break;
                    }
                }
            }

            if (empty($methodIds)) {
                Response::success([]);
            }

            // 获取对应的地址配置（包括不存在的，用于创建）
            $addresses = [];
            foreach ($methodIds as $methodId) {
                $address = $this->cryptoAddressModel->getByPaymentMethod($methodId);
                $method = array_filter($allMethods, function($m) use ($methodId) {
                    return $m['id'] == $methodId;
                });
                $method = reset($method);

                if ($address) {
                    $addresses[] = array_merge($address, [
                        'methodKey' => $method['methodKey'] ?? null,
                        'methodName' => $method['methodName'] ?? null,
                        'shortCode' => $method['shortCode'] ?? null,
                        'iconClass' => $method['iconClass'] ?? null
                    ]);
                } else {
                    // 如果地址不存在，创建一个默认结构
                    $addresses[] = [
                        'id' => null,
                        'paymentMethodId' => $methodId,
                        'walletAddress' => '',
                        'networkType' => 'mainnet',
                        'minimumDeposit' => 0,
                        'minimumDepositUsd' => 0,
                        'confirmationBlocks' => 3,
                        'isActive' => 1,
                        'methodKey' => $method['methodKey'] ?? null,
                        'methodName' => $method['methodName'] ?? null,
                        'shortCode' => $method['shortCode'] ?? null,
                        'iconClass' => $method['iconClass'] ?? null
                    ];
                }
            }

            Response::success($addresses);
        } else {
            // 原有逻辑：获取所有活跃地址
            $addresses = $this->cryptoAddressModel->getActiveAddresses();
            Response::success($addresses);
        }
    }

    /**
     * 更新加密货币地址
     * PUT /api/transaction-settings/crypto-addresses/{paymentMethodId}
     */
    public function updateCryptoAddress($paymentMethodId) {
        $admin = $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        Validator::make($data, [
            'walletAddress' => 'required|string'
        ]);

        $updateData = [
            'walletAddress' => trim($data['walletAddress']),
            'networkType' => $data['networkType'] ?? null,
            'minimumDeposit' => $data['minimumDeposit'] ?? 0,
            'minimumDepositUsd' => $data['minimumDepositUsd'] ?? 0,
            'confirmationBlocks' => $data['confirmationBlocks'] ?? 3,
            'isActive' => isset($data['isActive']) ? ($data['isActive'] ? 1 : 0) : 1,
            'updatedBy' => $admin['userId']
        ];

        $this->cryptoAddressModel->upsert($paymentMethodId, $updateData);

        $updated = $this->cryptoAddressModel->getByPaymentMethod($paymentMethodId);

        Response::success($updated, 'Crypto address updated successfully');
    }

    /**
     * 获取网关配置
     * GET /api/transaction-settings/gateways
     */
    public function getGateways() {
        $this->requireAdmin();

        $gateways = $this->gatewayModel->getAllGateways();
        foreach ($gateways as &$gateway) {
            $gateway['feeSettings'] = $this->gatewayFeeModel->getByGatewaySettingId((int)$gateway['id']);
        }
        unset($gateway);

        Response::success($gateways);
    }

    /**
     * 获取支付网关手续费配置
     * GET /api/transaction-settings/gateway-fees
     */
    public function getGatewayFees() {
        $this->requireAdmin();

        $gatewaySettingId = isset($_GET['gateway_setting_id']) ? (int)$_GET['gateway_setting_id'] : null;
        if ($gatewaySettingId > 0) {
            Response::success($this->gatewayFeeModel->getByGatewaySettingId($gatewaySettingId));
        }

        Response::success($this->gatewayFeeModel->getAllWithGateways());
    }

    public function getGatewayDisplayContent($gatewaySettingId) {
        $this->requireAdmin();

        $gateway = $this->gatewayModel->findById((int)$gatewaySettingId);
        if (!$gateway) {
            Response::notFound('Gateway not found');
        }

        Response::success([
            'gatewaySettingId' => (int)$gateway['id'],
            'gatewayKey' => $gateway['gatewayKey'] ?? null,
            'gatewayName' => $gateway['gatewayName'] ?? null,
            'depositContent' => $gateway['depositContent'] ?? null,
            'withdrawalContent' => $gateway['withdrawalContent'] ?? null
        ]);
    }

    /**
     * 获取支付支持问题（后台）
     * GET /api/transaction-settings/payment-support-questions
     */
    public function getPaymentSupportQuestions() {
        $this->requireAdmin();

        $gatewaySettingId = isset($_GET['gateway_setting_id']) ? (int)$_GET['gateway_setting_id'] : 0;
        if ($gatewaySettingId <= 0) {
            Response::validationError([
                'gateway_setting_id' => ['gateway_setting_id is required']
            ]);
        }

        $gateway = $this->gatewayModel->findById($gatewaySettingId);
        if (!$gateway) {
            Response::notFound('Gateway not found');
        }

        $questions = array_map(
            [$this, 'formatPaymentSupportQuestionForResponse'],
            $this->paymentSupportQuestionModel->getAdminQuestions($gatewaySettingId)
        );

        $grouped = [
            PaymentSupportQuestion::SCOPE_DEPOSIT => [],
            PaymentSupportQuestion::SCOPE_WITHDRAW => []
        ];
        foreach ($questions as $question) {
            $scope = $question['scope'] ?? null;
            if (!isset($grouped[$scope])) {
                continue;
            }
            $grouped[$scope][] = $question;
        }

        $withdrawTemplates = $this->withdrawalTemplateModel->getTemplatesSummary([
            'gatewaySettingId' => $gatewaySettingId
        ]);
        $grouped['withdrawTemplateActive'] = false;
        foreach ($withdrawTemplates as $template) {
            if (($template['status'] ?? null) !== 'inactive') {
                $grouped['withdrawTemplateActive'] = true;
                break;
            }
        }

        Response::success($grouped);
    }

    /**
     * 更新支付支持问题（后台）
     * PUT /api/transaction-settings/payment-support-questions/{id}
     */
    public function createPaymentSupportQuestion() {
        $admin = $this->requireAdmin();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $gatewaySettingId = isset($input['gatewaySettingId'])
            ? (int)$input['gatewaySettingId']
            : (isset($input['paymentGatewayId']) ? (int)$input['paymentGatewayId'] : 0);

        if ($gatewaySettingId <= 0) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'add',
                'transactionSettingsSupportQuestionAddFailure',
                'gatewaySettingId is required'
            );
            Response::validationError([
                'gatewaySettingId' => ['gatewaySettingId is required']
            ]);
        }

        $gateway = $this->gatewayModel->findById($gatewaySettingId);
        if (!$gateway) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'add',
                'transactionSettingsSupportQuestionAddFailure',
                'Payment gateway not found'
            );
            Response::notFound('Gateway not found');
        }

        try {
            $createData = $this->buildPaymentSupportQuestionCreateData($input, $gatewaySettingId);
            $this->validatePaymentSupportQuestionUniqueName($gatewaySettingId, $createData['scope'], $createData['name']);

            $createData['updatedBy'] = $admin['userId'];
            $questionId = $this->paymentSupportQuestionModel->createQuestion($createData);

            TransactionSettingsOperationLog::logSupportQuestion(
                $input,
                'add',
                $gatewaySettingId,
                (string) ($createData['name'] ?? ''),
                (string) ($createData['scope'] ?? '')
            );

            Response::created(
                $this->formatPaymentSupportQuestionForResponse(
                    $this->paymentSupportQuestionModel->getQuestionById((int)$questionId)
                ),
                'Payment support question created successfully'
            );
        } catch (Exception $e) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'add',
                'transactionSettingsSupportQuestionAddFailure',
                'Failed to create payment support question: ' . $e->getMessage()
            );
            Response::error('Failed to create payment support question: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新支付支持问题（后台）
     * PUT /api/transaction-settings/payment-support-questions/{id}
     */
    public function updatePaymentSupportQuestion($id) {
        $admin = $this->requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $question = $this->paymentSupportQuestionModel->getQuestionById((int)$id);
        if (!$question) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsSupportQuestionEditFailure',
                'Payment support question not found'
            );
            Response::notFound('Payment support question not found');
        }

        $updateData = $this->buildPaymentSupportQuestionUpdateData($input);
        if (empty($updateData)) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsSupportQuestionEditFailure',
                'No valid payment support question fields provided'
            );
            Response::validationError([
                'payload' => ['No valid payment support question fields provided']
            ]);
        }

        $restrictedFieldErrors = $this->getRestrictedPaymentSupportQuestionUpdateErrors($question, $updateData);
        if (!empty($restrictedFieldErrors)) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsSupportQuestionEditFailure',
                'This row is locked. Only hintText can be updated.'
            );
            Response::validationError($restrictedFieldErrors);
        }

        $targetName = isset($updateData['name']) ? $updateData['name'] : (string)($question['name'] ?? '');
        $targetScope = isset($updateData['scope']) ? $updateData['scope'] : (string)($question['scope'] ?? '');
        try {
            $this->validatePaymentSupportQuestionUniqueName(
                (int)$question['paymentGatewayId'],
                $targetScope,
                $targetName,
                (int)$question['id']
            );

            $updateData['updatedBy'] = $admin['userId'];
            $this->paymentSupportQuestionModel->updateQuestion((int)$id, $updateData);

            TransactionSettingsOperationLog::logSupportQuestion(
                $input,
                'edit',
                (int) ($question['paymentGatewayId'] ?? 0),
                $targetName,
                $targetScope
            );

            Response::success(
                $this->formatPaymentSupportQuestionForResponse(
                    $this->paymentSupportQuestionModel->getQuestionById((int)$id)
                ),
                'Payment support question updated successfully'
            );
        } catch (Exception $e) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsSupportQuestionEditFailure',
                'Failed to update payment support question: ' . $e->getMessage()
            );
            Response::error('Failed to update payment support question: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 删除支付支持问题（后台）
     * DELETE /api/transaction-settings/payment-support-questions/{id}
     */
    public function deletePaymentSupportQuestion($id) {
        $this->requireAdmin();
        $input = $_GET;

        $question = $this->paymentSupportQuestionModel->getQuestionById((int)$id);
        if (!$question) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'delete',
                'transactionSettingsSupportQuestionDeleteFailure',
                'Payment support question not found'
            );
            Response::notFound('Payment support question not found');
        }

        if (!empty($question['isLocked'])) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'delete',
                'transactionSettingsSupportQuestionDeleteFailure',
                'This row is locked. Only hintText can be updated.'
            );
            Response::validationError([
                'isLocked' => ['This row is locked. Only hintText can be updated.']
            ]);
        }

        try {
            $this->paymentSupportQuestionModel->delete((int)$id);
            TransactionSettingsOperationLog::logSupportQuestion(
                $input,
                'delete',
                (int) ($question['paymentGatewayId'] ?? 0),
                (string) ($question['name'] ?? ''),
                (string) ($question['scope'] ?? '')
            );
            Response::success(null, 'Payment support question deleted successfully');
        } catch (Exception $e) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'delete',
                'transactionSettingsSupportQuestionDeleteFailure',
                'Failed to delete payment support question: ' . $e->getMessage()
            );
            Response::error('Failed to delete payment support question: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 切换支付支持问题选项启用状态（后台）
     * POST /api/transaction-settings/payment-support-questions/{id}/options/toggle
     */
    public function togglePaymentSupportQuestionOption($id) {
        $this->requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $optionValue = trim((string)($input['value'] ?? $input['optionValue'] ?? ''));
        if ($optionValue === '') {
            Response::validationError([
                'value' => ['Option value is required']
            ]);
        }

        if (!array_key_exists('isEnabled', $input) && !array_key_exists('enabled', $input)) {
            Response::validationError([
                'isEnabled' => ['isEnabled is required']
            ]);
        }

        $isEnabled = array_key_exists('isEnabled', $input)
            ? (bool)$input['isEnabled']
            : (bool)$input['enabled'];

        $question = $this->paymentSupportQuestionModel->getQuestionById((int)$id);
        if (!$question) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsSupportQuestionEditFailure',
                'Payment support question not found'
            );
            Response::notFound('Payment support question not found');
        }

        try {
            $updated = $this->paymentSupportQuestionModel->toggleOptionEnabled(
                (int)$id,
                $optionValue,
                $isEnabled
            );

            if ($updated === false) {
                Response::validationError([
                    'value' => ['Option value not found']
                ]);
            }

            if ($updated === null) {
                Response::notFound('Payment support question not found');
            }

            TransactionSettingsOperationLog::logSupportQuestion(
                $input,
                'edit',
                (int)($question['paymentGatewayId'] ?? 0),
                (string)($question['name'] ?? ''),
                (string)($question['scope'] ?? '')
            );

            Response::success(
                $this->formatPaymentSupportQuestionForResponse($updated),
                'Payment method option updated successfully'
            );
        } catch (Exception $e) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsSupportQuestionEditFailure',
                'Failed to update payment method option: ' . $e->getMessage()
            );
            Response::error('Failed to update payment method option: ' . $e->getMessage(), 500);
        }
    }

    public function updateGatewayDisplayContent($gatewaySettingId) {
        $admin = $this->requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $gateway = $this->gatewayModel->findById((int)$gatewaySettingId);
        if (!$gateway) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsGatewayDisplayFailure',
                'Payment gateway not found'
            );
            Response::notFound('Gateway not found');
        }

        $updateData = [];

        foreach (['depositContent', 'withdrawalContent'] as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            $value = is_string($input[$field]) ? trim($input[$field]) : $input[$field];
            $updateData[$field] = ($value === '' || $value === null) ? null : $value;
        }

        if (empty($updateData)) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsGatewayDisplayFailure',
                'No valid display content fields provided'
            );
            Response::validationError([
                'payload' => ['No valid display content fields provided']
            ]);
        }

        try {
            $updateData['updatedBy'] = $admin['userId'];
            $this->gatewayModel->update((int)$gateway['id'], $updateData);

            TransactionSettingsOperationLog::logGatewayByIdSuccess(
                $input,
                (int) $gatewaySettingId,
                'transactionSettingsGatewayDisplayContent'
            );

            $updated = $this->gatewayModel->findById((int)$gateway['id']);
            Response::success([
                'gatewaySettingId' => (int)$updated['id'],
                'gatewayKey' => $updated['gatewayKey'] ?? null,
                'gatewayName' => $updated['gatewayName'] ?? null,
                'depositContent' => $updated['depositContent'] ?? null,
                'withdrawalContent' => $updated['withdrawalContent'] ?? null
            ], 'Gateway display content updated successfully');
        } catch (Exception $e) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsGatewayDisplayFailure',
                'Failed to update gateway display content: ' . $e->getMessage()
            );
            Response::error('Failed to update gateway display content: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取交易展示内容配置
     * GET /api/transaction-settings/display-contents
     * GET /api/transaction-settings/display-contents/{scope}
     */
    public function getDisplayContents($scope = null) {
        $this->requireAdmin();

        if ($scope !== null) {
            $scope = $this->normalizeDisplayContentScope($scope);
            $content = $this->transactionDisplayContentModel->getByScope($scope);
            if (!$content) {
                Response::success($this->buildDefaultDisplayContent($scope));
            }

            Response::success($content);
        }

        $contents = $this->transactionDisplayContentModel->getAllContents();
        $byScope = [];
        foreach ($contents as $item) {
            $byScope[$item['scope']] = $item;
        }

        $result = [];
        foreach ($this->getSupportedDisplayContentScopes() as $supportedScope) {
            $result[] = $byScope[$supportedScope] ?? $this->buildDefaultDisplayContent($supportedScope);
        }

        Response::success($result);
    }

    /**
     * 更新交易展示内容配置
     * PUT /api/transaction-settings/display-contents/{scope}
     */
    public function updateDisplayContent($scope) {
        $admin = $this->requireAdmin();
        $scope = $this->normalizeDisplayContentScope($scope);

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        try {
            $payload = $this->buildDisplayContentPayload($scope, $input);
            $payload['updatedBy'] = $admin['userId'];

            $existing = $this->transactionDisplayContentModel->getByScope($scope);
            if (!$existing) {
                $payload['createdBy'] = $admin['userId'];
            }

            $this->transactionDisplayContentModel->upsertByScope($scope, $payload);

            TransactionSettingsOperationLog::logDisplayContentScope($input, $scope);

            Response::success(
                $this->transactionDisplayContentModel->getByScope($scope),
                'Display content updated successfully'
            );
        } catch (Exception $e) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsDisplayContentFailure',
                'Failed to update display content: ' . $e->getMessage()
            );
            Response::error('Failed to update display content: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新支付网关限额配置
     * PUT /api/transaction-settings/gateway-fees-modify/{gatewaySettingId}/limit
     */
    public function updateGatewayFeeLimits($gatewaySettingId) {
        $admin = $this->requireAdmin();
        $gatewaySettingId = (int)$gatewaySettingId;
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $gateway = $this->gatewayModel->findById($gatewaySettingId);
        if (!$gateway) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsGatewayFeeFailure',
                'Payment gateway not found'
            );
            Response::notFound('Gateway not found');
        }

        $updateData = $this->buildGatewayFeeUpdateData($input);
        $limitFields = ['minDeposit', 'maxDeposit', 'minWithdrawal', 'maxWithdrawal', 'isActive', 'notes'];
        $updateData = array_intersect_key($updateData, array_flip($limitFields));

        if (empty($updateData)) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsGatewayFeeFailure',
                'No valid gateway fee limit fields provided'
            );
            Response::validationError([
                'payload' => ['No valid gateway fee limit fields provided']
            ]);
        }

        try {
            $existing = $this->gatewayFeeModel->getByGatewaySettingId($gatewaySettingId);
            $merged = array_merge($existing ?? $this->getDefaultGatewayFeeConfig($gatewaySettingId), $updateData);
            $this->validateGatewayFeeLimits($merged);

            $updateData['calculationMode'] = $existing['calculationMode'] ?? 'single';
            $updateData['updatedBy'] = $admin['userId'];

            $this->gatewayFeeModel->upsertByGatewaySettingId($gatewaySettingId, $updateData);

            TransactionSettingsOperationLog::logGatewayByIdSuccess(
                $input,
                $gatewaySettingId,
                'transactionSettingsGatewayLimits'
            );

            Response::success(
                $this->gatewayFeeModel->getByGatewaySettingId($gatewaySettingId),
                'Gateway fee limits updated successfully'
            );
        } catch (Exception $e) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsGatewayFeeFailure',
                'Failed to update gateway fee limits: ' . $e->getMessage()
            );
            Response::error('Failed to update gateway fee limits: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 添加网关快捷金额
     * POST /api/transaction-settings/gateway-fees-modify/{gatewaySettingId}/quick-amounts
     */
    public function addGatewayQuickAmount($gatewaySettingId) {
        $admin = $this->requireAdmin();
        $gatewaySettingId = (int)$gatewaySettingId;
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $gateway = $this->gatewayModel->findById($gatewaySettingId);
        if (!$gateway) {
            Response::notFound('Gateway not found');
        }

        $transactionType = $input['transactionType'] ?? $input['type'] ?? '';
        $amount = $input['amount'] ?? null;

        try {
            $created = $this->quickAmountModel->addAmount($gatewaySettingId, $transactionType, $amount);
            TransactionSettingsOperationLog::logGatewayByIdSuccess(
                array_merge($input, ['updatedBy' => $admin['userId'] ?? null]),
                $gatewaySettingId,
                'transactionSettingsGatewayLimits'
            );
            Response::success([
                'item' => $created,
                'funding' => $this->gatewayFeeModel->getByGatewaySettingId($gatewaySettingId),
            ], 'Quick amount added successfully');
        } catch (InvalidArgumentException $e) {
            Response::validationError(['amount' => [$e->getMessage()]]);
        } catch (Exception $e) {
            Response::error('Failed to add quick amount: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 删除网关快捷金额
     * DELETE /api/transaction-settings/gateway-fees-modify/{gatewaySettingId}/quick-amounts/{quickAmountId}
     */
    public function deleteGatewayQuickAmount($gatewaySettingId, $quickAmountId) {
        $this->requireAdmin();
        $gatewaySettingId = (int)$gatewaySettingId;
        $quickAmountId = (int)$quickAmountId;

        $gateway = $this->gatewayModel->findById($gatewaySettingId);
        if (!$gateway) {
            Response::notFound('Gateway not found');
        }

        $existing = $this->quickAmountModel->findById($quickAmountId);
        if (!$existing || (int)$existing['gatewaySettingId'] !== $gatewaySettingId) {
            Response::notFound('Quick amount not found');
        }

        try {
            $deleted = $this->quickAmountModel->deleteAmount($quickAmountId);
            TransactionSettingsOperationLog::logGatewayByIdSuccess(
                ['quickAmountId' => $quickAmountId],
                $gatewaySettingId,
                'transactionSettingsGatewayLimits'
            );
            Response::success([
                'item' => $deleted,
                'funding' => $this->gatewayFeeModel->getByGatewaySettingId($gatewaySettingId),
            ], 'Quick amount deleted successfully');
        } catch (RuntimeException $e) {
            Response::validationError(['amount' => [$e->getMessage()]]);
        } catch (Exception $e) {
            Response::error('Failed to delete quick amount: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新入金手续费配置
     * PUT /api/transaction-settings/gateway-fees-modify/{gatewaySettingId}/deposit-fee
     */
    public function updateGatewayDepositFee($gatewaySettingId) {
        $this->updateGatewayFeeRulesByType($gatewaySettingId, 'deposit');
    }

    /**
     * 更新出金手续费配置
     * PUT /api/transaction-settings/gateway-fees-modify/{gatewaySettingId}/withdraw-fee
     */
    public function updateGatewayWithdrawalFee($gatewaySettingId) {
        $this->updateGatewayFeeRulesByType($gatewaySettingId, 'withdrawal');
    }

    /**
     * 更新网关配置
     * PUT /api/transaction-settings/gateways/{gatewayKey}
     */
    public function updateGateway($gatewayKey) {
        $admin = $this->requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        // system 网关只用于 admin 手工打钱内部使用，不允许修改任何配置
        if ($gatewayKey === PaymentGatewaySetting::GATEWAY_KEY_SYSTEM) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsGatewayEditFailure',
                'System payment gateway cannot be modified'
            );
            Response::forbidden('System payment gateway cannot be modified');
        }

        $gateway = $this->gatewayModel->findByKey($gatewayKey);
        if (!$gateway) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsGatewayEditFailure',
                'Payment gateway not found'
            );
            Response::notFound('Gateway not found');
        }

        $updateData = [];

        $allowedFields = [
            'gatewayName', 'type', 'iconClass', 'isEnabled', 'isDepositEnabled', 'isWithdrawalEnabled',
            'displayOrder', 'environment', 'appId', 'apiKey', 'secretKey',
            'merchantName', 'webhookUrl', 'returnUrl', 'processingTime', 'withdrawalProcessingTime',
            'depositContent', 'withdrawalContent', 'integration',
            'supportedFiatCurrencies', 'supportedCryptoCurrencies', 'amountDecimalPlaces', 'configData'
        ];

        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                if ($field === 'type') {
                    // 网关类型只允许 fiat / crypto，客户端入金出金按它来分组筛选
                    if (!in_array($input[$field], ['fiat', 'crypto'], true)) {
                        TransactionSettingsOperationLog::logFailure(
                            $input,
                            'edit',
                            'transactionSettingsGatewayEditFailure',
                            'type must be either fiat or crypto'
                        );
                        Response::validationError([
                            'type' => 'type must be either fiat or crypto'
                        ]);
                    }
                    $updateData[$field] = $input[$field];
                } elseif ($field === 'displayOrder') {
                    $updateData[$field] = (int)$input[$field];
                } elseif (in_array($field, ['supportedFiatCurrencies', 'supportedCryptoCurrencies'], true)) {
                    $updateData[$field] = is_array($input[$field]) ? json_encode($input[$field]) : $input[$field];
                } elseif ($field === 'configData') {
                    if (is_array($input[$field])) {
                        $updateData[$field] = json_encode($input[$field], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    } elseif (is_string($input[$field])) {
                        $decoded = json_decode($input[$field], true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            TransactionSettingsOperationLog::logFailure(
                                $input,
                                'edit',
                                'transactionSettingsGatewayEditFailure',
                                'configData must be a valid JSON string or object'
                            );
                            Response::validationError([
                                'configData' => 'configData must be a valid JSON string or object'
                            ]);
                        }

                        $updateData[$field] = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    } else {
                        TransactionSettingsOperationLog::logFailure(
                            $input,
                            'edit',
                            'transactionSettingsGatewayEditFailure',
                            'configData must be a valid JSON string or object'
                        );
                        Response::validationError([
                            'configData' => 'configData must be a valid JSON string or object'
                        ]);
                    }
                } elseif ($field === 'amountDecimalPlaces') {
                    $updateData[$field] = $this->normalizeGatewayAmountDecimalPlaces($input[$field]);
                } elseif (in_array($field, ['isEnabled', 'isDepositEnabled', 'isWithdrawalEnabled', 'integration'], true)) {
                    $updateData[$field] = $input[$field] ? 1 : 0;
                } elseif (in_array($field, ['depositContent', 'withdrawalContent', 'processingTime', 'withdrawalProcessingTime'], true)) {
                    $value = is_string($input[$field]) ? trim($input[$field]) : $input[$field];
                    $updateData[$field] = ($value === '' || $value === null) ? null : $value;
                } else {
                    $updateData[$field] = $input[$field];
                }
            }
        }

        if (empty($updateData)) {
            $updated = $this->gatewayModel->findById($gateway['id']);
            Response::success($updated, 'Gateway settings updated successfully');
            return;
        }

        try {
            $updateData['updatedBy'] = $admin['userId'];
            $this->gatewayModel->update($gateway['id'], $updateData);

            TransactionSettingsOperationLog::logGatewayUpdateSuccess($input, $gateway);

            $updated = $this->gatewayModel->findById($gateway['id']);
            Response::success($updated, 'Gateway settings updated successfully');
        } catch (Exception $e) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsGatewayEditFailure',
                'Failed to update gateway settings: ' . $e->getMessage()
            );
            Response::error('Failed to update gateway settings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 软删除网关配置，并联动软删除关联的 withdrawal template
     * DELETE /api/transaction-settings/gateways/{gatewayKey}
     */
    public function deleteGateway($gatewayKey) {
        $admin = $this->requireAdmin();
        $input = $_GET;
        if (!is_array($input)) {
            $input = [];
        }

        // system 网关受保护，不允许删除（会导致 admin 打钱功能瘫痪）
        if ($gatewayKey === PaymentGatewaySetting::GATEWAY_KEY_SYSTEM) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'delete',
                'transactionSettingsGatewayDeleteFailure',
                'System payment gateway cannot be deleted'
            );
            Response::forbidden('System payment gateway cannot be deleted');
        }

        $gateway = $this->gatewayModel->findByKey($gatewayKey);
        if (!$gateway) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'delete',
                'transactionSettingsGatewayDeleteFailure',
                'Payment gateway not found'
            );
            Response::notFound('Gateway not found');
        }

        $deletedAt = date('Y-m-d H:i:s');
        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            $this->withdrawalTemplateModel->softDeleteByGatewaySettingId(
                (int)$gateway['id'],
                $deletedAt,
                $admin['userId']
            );

            $this->gatewayModel->update((int)$gateway['id'], [
                'deletedAt' => $deletedAt,
                'updatedBy' => $admin['userId']
            ]);

            $db->commit();

            TransactionSettingsOperationLog::logGatewayDeleteSuccess($input, $gateway);
            Response::success(null, 'Gateway soft deleted successfully');
        } catch (Exception $e) {
            $db->rollback();
            TransactionSettingsOperationLog::logFailure(
                $input,
                'delete',
                'transactionSettingsGatewayDeleteFailure',
                'Failed to soft delete gateway: ' . $e->getMessage()
            );
            Response::error('Failed to soft delete gateway: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取交易限额
     * GET /api/transaction-settings/limits
     */
    public function getLimits() {
        $transactionType = $_GET['type'] ?? null;
        $paymentType = $_GET['payment_type'] ?? null;

        $limits = $this->limitModel->getActiveLimits($transactionType, $paymentType);

        Response::success($limits);
    }

    /**
     * 更新交易限额
     * PUT /api/transaction-settings/limits/{id}
     */
    public function updateLimit($id) {
        $admin = $this->requireAdmin();

        $limit = $this->limitModel->findById($id);
        if (!$limit) {
            Response::notFound('Limit configuration not found');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $updateData = [];
        $allowedFields = ['minimumAmount', 'maximumAmount', 'dailyLimit', 'monthlyLimit', 'isActive'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        $minimumAmount = $updateData['minimumAmount'] ?? $limit['minimumAmount'];
        $maximumAmount = $updateData['maximumAmount'] ?? $limit['maximumAmount'];
        $dailyLimit    = $updateData['dailyLimit'] ?? $limit['dailyLimit'];
        $monthlyLimit  = $updateData['monthlyLimit'] ?? $limit['monthlyLimit'];

        // 1. Maximum > Minimum
        if ($maximumAmount <= $minimumAmount) {
            Response::error('Maximum must be greater than Minimum', 400);
        }

        // 2. Daily > Maximum
        if ($maximumAmount >= $dailyLimit) {
            Response::error('Maximum must be less than Daily', 400);
        }

        // 3. Monthly > Daily
        if ($dailyLimit >= $monthlyLimit) {
            Response::error('Daily must be less than Monthly', 400);
        }


        if (!empty($updateData)) {
            $updateData['updatedBy'] = $admin['userId'];
            $this->limitModel->update($id, $updateData);
        }

        $updated = $this->limitModel->findById($id);

        Response::success($updated, 'Transaction limit updated successfully');
    }

    /**
     * 获取通知设置
     * GET /api/transaction-settings/notifications
     */
    public function getNotifications() {
        $this->requireAdmin();

        $category = $_GET['category'] ?? null;

        if ($category) {
            $notifications = $this->notificationModel->getByCategory($category);
        } else {
            $notifications = $this->notificationModel->getAllGrouped();
        }

        Response::success($notifications);
    }

    /**
     * 更新通知设置
     * PUT /api/transaction-settings/notifications
     */
    public function updateNotification() {
        $admin = $this->requireAdmin();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $errors = Validator::validateData($input, [
            'settingKey' => 'required|string',
            'settingValue' => 'required'
        ]);
        if ($errors) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsNotificationFailure',
                'Validation failed'
            );
            Response::validationError($errors);
        }

        $settingKey = $input['settingKey'];
        $settingValue = $input['settingValue'];

        try {
            $this->notificationModel->setValue($settingKey, $settingValue, $admin['userId']);

            TransactionSettingsOperationLog::logNotification($input, $settingKey, $settingValue);

            $updated = $this->notificationModel->findOne(['settingKey' => $settingKey]);

            Response::success($updated, 'Notification setting updated successfully');
        } catch (Exception $e) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsNotificationFailure',
                'Failed to update notification setting: ' . $e->getMessage()
            );
            Response::error('Failed to update notification setting: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取客户端可用的支付网关（公开接口）
     * GET /api/transaction-settings/client/gateways
     */
    public function getClientGateways() {
        $gateways = $this->gatewayModel->getEnabledGateways();

        // 只返回客户端需要的字段
        $clientGateways = [];
        foreach ($gateways as $gateway) {
            $feeSettings = $this->gatewayFeeModel->getByGatewaySettingId((int)$gateway['id'], true);
            $isDepositEnabled = (bool)($gateway['isDepositEnabled'] ?? false);
            $isWithdrawalEnabled = (bool)($gateway['isWithdrawalEnabled'] ?? false);

            $linkedMethod = $this->paymentMethodModel->findByKey($gateway['gatewayKey'] ?? '');
            if ($linkedMethod) {
                if (empty($linkedMethod['isDepositEnabled'])) {
                    $isDepositEnabled = false;
                }
                if (empty($linkedMethod['isWithdrawalEnabled'])) {
                    $isWithdrawalEnabled = false;
                }
            }

            $clientGateways[] = [
                'id' => $gateway['id'],
                'gatewayKey' => $gateway['gatewayKey'],
                'gatewayName' => $gateway['gatewayName'],
                'type' => $gateway['type'] ?? null,
                'iconClass' => $gateway['iconClass'] ?? null,
                'isEnabled' => (bool)$gateway['isEnabled'],
                'isDepositEnabled' => $isDepositEnabled,
                'isWithdrawalEnabled' => $isWithdrawalEnabled,
                'isMultiCurrency' => (bool)($gateway['isMultiCurrency'] ?? false),
                'displayOrder' => (int)($gateway['displayOrder'] ?? 0),
                'processingTime' => $gateway['processingTime'] ?? null,
                'withdrawalProcessingTime' => $gateway['withdrawalProcessingTime'] ?? null,
                'amountDecimalPlaces' => $this->resolveGatewayAmountDecimalPlaces($gateway),
                'content' => $gateway['depositContent'] ?? null,
                'supportedCryptoCurrencies' => $this->parseJsonField($gateway['supportedCryptoCurrencies']),
                'supportedFiatCurrencies' => $this->parseJsonField($gateway['supportedFiatCurrencies']),
                'country' => $linkedMethod['country'] ?? null,
                'feeSettings' => $this->formatGatewayFeeForClient($feeSettings, 'deposit')
            ];
        }

        Response::success($this->sortClientGatewaysByName($clientGateways));
    }

    /**
     * 获取客户端可见的交易展示内容
     * GET /api/transaction-settings/client/display-contents
     * GET /api/transaction-settings/client/display-contents?scope=deposit
     */
    public function getClientDisplayContents() {
        $scope = isset($_GET['scope']) ? $this->normalizeDisplayContentScope($_GET['scope']) : null;

        if ($scope !== null) {
            $content = $this->transactionDisplayContentModel->getByScope($scope, true);
            Response::success($content ?: null);
        }

        Response::success($this->transactionDisplayContentModel->getActiveContents());
    }

    public function getClientDepositPayments($gatewaySettingId) {
        $gateway = $this->gatewayModel->findById((int)$gatewaySettingId);
        if (!$gateway || empty($gateway['isEnabled']) || empty($gateway['isDepositEnabled'])) {
            Response::notFound('Deposit gateway not found');
        }
        // system 网关只能被 admin 内部使用，客户端通过 id 直接访问也要 404
        if (($gateway['gatewayKey'] ?? '') === PaymentGatewaySetting::GATEWAY_KEY_SYSTEM) {
            Response::notFound('Deposit gateway not found');
        }

        $linkedMethod = $this->paymentMethodModel->findByKey($gateway['gatewayKey'] ?? '');
        if ($linkedMethod && empty($linkedMethod['isDepositEnabled'])) {
            Response::notFound('Deposit gateway not found');
        }

        Response::success([
            'id' => (int)$gateway['id'],
            'gatewayKey' => $gateway['gatewayKey'],
            'gatewayName' => $gateway['gatewayName'],
            'type' => $gateway['type'] ?? null,
            'iconClass' => $gateway['iconClass'] ?? null,
            'amountDecimalPlaces' => $this->resolveGatewayAmountDecimalPlaces($gateway),
            'content' => $gateway['depositContent'] ?? null,
            'supportedFiatCurrencies' => $this->parseJsonField($gateway['supportedFiatCurrencies'] ?? null),
            'supportedCryptoCurrencies' => $this->parseJsonField($gateway['supportedCryptoCurrencies'] ?? null),
            'questions' => $this->paymentSupportQuestionModel->filterQuestionsForClient(
                $this->resolveClientDepositQuestions($gateway, (int)$gatewaySettingId)
            ),
            'fees' => $this->formatGatewayFeeForClient(
                $this->gatewayFeeModel->getByGatewaySettingId((int)$gatewaySettingId, true),
                'deposit'
            )
        ]);
    }

    private function resolveClientDepositQuestions(array $gateway, int $gatewaySettingId): array {
        if ($this->isXLinkGateway($gateway)) {
            return $this->paymentSupportQuestionModel->syncLockedScopeQuestions(
                $gatewaySettingId,
                PaymentSupportQuestion::SCOPE_DEPOSIT,
                XLinkService::depositSupportQuestions()
            );
        }

        return $this->paymentSupportQuestionModel->getGatewayQuestions(
            $gatewaySettingId,
            PaymentSupportQuestion::SCOPE_DEPOSIT
        );
    }

    private function isXLinkGateway(array $gateway): bool {
        if (strtolower(trim((string)($gateway['gatewayKey'] ?? ''))) === XLinkService::GATEWAY_KEY) {
            return true;
        }

        $config = $this->parseJsonField($gateway['configData'] ?? null);
        if (!is_array($config)) {
            return false;
        }

        return strtolower(trim((string)($config['providerKey'] ?? ''))) === XLinkService::PROVIDER_KEY;
    }

    /**
     * 获取客户端可用的出金网关（公开接口）
     * GET /api/transaction-settings/client/withdraw/gateways
     */
    public function getClientWithdrawGateways() {
        $gateways = $this->gatewayModel->getEnabledGateways();

        $clientGateways = [];
        foreach ($gateways as $gateway) {
            if ((int)($gateway['isWithdrawalEnabled'] ?? 0) !== 1) {
                continue;
            }

            $linkedMethod = $this->paymentMethodModel->findByKey($gateway['gatewayKey'] ?? '');
            if ($linkedMethod && empty($linkedMethod['isWithdrawalEnabled'])) {
                continue;
            }

            $feeSettings = $this->gatewayFeeModel->getByGatewaySettingId((int)$gateway['id'], true);
            $clientGateways[] = [
                'id' => $gateway['id'],
                'gatewayKey' => $gateway['gatewayKey'],
                'gatewayName' => $gateway['gatewayName'],
                'type' => $gateway['type'] ?? null,
                'iconClass' => $gateway['iconClass'] ?? null,
                'isEnabled' => (bool)($gateway['isEnabled'] ?? false),
                'isWithdrawalEnabled' => (bool)($gateway['isWithdrawalEnabled'] ?? false),
                'displayOrder' => (int)($gateway['displayOrder'] ?? 0),
                'processingTime' => $gateway['processingTime'] ?? null,
                'withdrawalProcessingTime' => $gateway['withdrawalProcessingTime'] ?? null,
                'amountDecimalPlaces' => $this->resolveGatewayAmountDecimalPlaces($gateway),
                'content' => $gateway['withdrawalContent'] ?? null,
                'supportedCryptoCurrencies' => $this->parseJsonField($gateway['supportedCryptoCurrencies'] ?? null),
                'supportedFiatCurrencies' => $this->parseJsonField($gateway['supportedFiatCurrencies'] ?? null),
                'country' => $linkedMethod['country'] ?? null,
                'configData' => $this->parseJsonField($gateway['configData'] ?? null),
                'feeSettings' => $this->formatGatewayFeeForClient($feeSettings, 'withdrawal')
            ];
        }

        Response::success($this->sortClientGatewaysByName($clientGateways));
    }

    /**
     * 获取客户端汇率（公开接口）
     * GET /api/transaction-settings/client/exchange-rates
     */
    public function getClientExchangeRates() {
        $rawType = strtolower(trim((string)($_GET['type'] ?? '')));
        $assetType = strtolower(trim((string)($_GET['assetType'] ?? '')));
        $transactionType = strtolower(trim((string)($_GET['transactionType'] ?? '')));
        $currencyParam = $_GET['currency'] ?? ($_GET['currencyCode'] ?? null);

        if ($assetType === '' && in_array($rawType, ['fiat', 'crypto'], true)) {
            $assetType = $rawType;
        }
        if ($transactionType === '' && in_array($rawType, ['deposit', 'withdraw'], true)) {
            $transactionType = $rawType;
        }

        if ($assetType !== '' && !in_array($assetType, ['fiat', 'crypto'], true)) {
            Response::validationError([
                'assetType' => ['assetType must be fiat or crypto']
            ]);
        }

        if ($transactionType !== '' && !in_array($transactionType, ['deposit', 'withdraw'], true)) {
            Response::validationError([
                'type' => ['type must be deposit or withdraw']
            ]);
        }

        $currencies = [];
        if (is_array($currencyParam)) {
            foreach ($currencyParam as $value) {
                $currency = strtoupper(trim((string)$value));
                if ($currency !== '') {
                    $currencies[] = $currency;
                }
            }
        } else {
            $currency = strtoupper(trim((string)($currencyParam ?? '')));
            if ($currency !== '') {
                $currencies[] = $currency;
            }
        }

        if (empty($currencies)) {
            Response::validationError([
                'currency' => ['currency is required']
            ]);
        }

        $currencies = array_values(array_unique($currencies));
        $results = [];

        foreach ($currencies as $currency) {
            $rate = null;
            if ($assetType !== '') {
                $rate = $this->exchangeRateModel->getRateByCurrencyCode($currency, $assetType);
            }

            $exchangeRate = $this->exchangeRateModel->resolveAdjustedRate($rate, $transactionType);

            $results[] = [
                'type' => $assetType === '' ? null : $assetType,
                'transactionType' => $transactionType === '' ? null : $transactionType,
                'currency' => $currency,
                'exchangeRate' => $exchangeRate
            ];
        }

        if (count($results) === 1 && !is_array($currencyParam)) {
            if ($results[0]['exchangeRate'] === null) {
                Response::success(null);
            }

            Response::success($results[0]);
        }

        Response::success($results);
    }

    /**
     * 获取网关支持的币种列表（包含paymentMethods的样式信息）
     * GET /api/transaction-settings/client/gateways/{gatewayKey}/cryptos
     */
    public function getGatewayCryptos($gatewayKey) {
        // 客户端拼接 URL 直接打 system 也要返回空，对应隐藏
        if ($gatewayKey === PaymentGatewaySetting::GATEWAY_KEY_SYSTEM) {
            Response::success([]);
        }
        $gateway = $this->gatewayModel->findByKey($gatewayKey);
        if (!$gateway || !$gateway['isEnabled']) {
            Response::success([]);
        }

        // 解析支持的加密货币
        $supportedCryptos = $this->parseJsonField($gateway['supportedCryptoCurrencies']);
        if (empty($supportedCryptos)) {
            Response::success([]);
        }

        // 币种代码到methodKey的映射
        $cryptoToMethodKey = [
            'BTC' => 'bitcoin',
            'ETH' => 'ethereum',
            'USDT' => 'usdt',
            'USDC' => 'usdc',
            'BNB' => 'bnb',
            'MATIC' => 'matic',
            'SOL' => 'sol'
        ];

        // 获取所有支付方式
        $allMethods = $this->paymentMethodModel->getCryptoMethods();

        // 根据支持的币种找到对应的支付方式，并添加地址信息
        $cryptos = [];
        foreach ($supportedCryptos as $cryptoCode) {
            $methodKey = $cryptoToMethodKey[$cryptoCode] ?? strtolower($cryptoCode);
            foreach ($allMethods as $method) {
                if ($method['methodKey'] === $methodKey) {
                    if (empty($method['isDepositEnabled']) && empty($method['isWithdrawalEnabled'])) {
                        break;
                    }
                    // 获取对应的地址配置
                    $address = $this->cryptoAddressModel->getByPaymentMethod($method['id']);
                    $cryptos[] = [
                        'id' => $method['id'],
                        'methodKey' => $method['methodKey'],
                        'methodName' => $method['methodName'],
                        'shortCode' => $method['shortCode'],
                        'iconClass' => $method['iconClass'],
                        'cryptoCode' => $cryptoCode,
                        'walletAddress' => $address['walletAddress'] ?? '',
                        'networkType' => $address['networkType'] ?? 'mainnet',
                        'minimumDeposit' => $address['minimumDeposit'] ?? 0,
                        'minimumDepositUsd' => $address['minimumDepositUsd'] ?? 0,
                        'confirmationBlocks' => $address['confirmationBlocks'] ?? 3,
                        'qrCodePath' => $address['qrCodePath'] ?? null,
                        'isActive' => ($address['isActive'] ?? 1) == 1,
                        'isDepositEnabled' => $method['isDepositEnabled'] == 1,
                        'isWithdrawalEnabled' => $method['isWithdrawalEnabled'] == 1
                    ];
                    break;
                }
            }
        }

        Response::success($cryptos);
    }

    /**
     * 解析JSON字段
     */
    private function parseJsonField($field) {
        if (empty($field)) {
            return [];
        }
        if (is_string($field)) {
            $parsed = json_decode($field, true);
            return is_array($parsed) ? $parsed : [];
        }
        if (is_array($field)) {
            return $field;
        }
        return [];
    }

    private function buildGatewayFeeUpdateData($data) {
        $allowedFields = [
            'minDeposit',
            'maxDeposit',
            'minWithdrawal',
            'maxWithdrawal',
            'isActive',
            'notes'
        ];

        $updateData = [];
        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if (in_array($field, ['minDeposit', 'maxDeposit', 'minWithdrawal', 'maxWithdrawal'], true)) {
                $updateData[$field] = $this->normalizeNullableDecimal($data[$field], $field);
            } elseif ($field === 'isActive') {
                $updateData[$field] = $data[$field] ? 1 : 0;
            } elseif ($field === 'notes') {
                $updateData[$field] = $data[$field] === null ? null : trim((string)$data[$field]);
            } else {
                $updateData[$field] = $data[$field];
            }
        }

        return $updateData;
    }

    private function buildGatewayFeeRulesUpdateData($value) {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            Response::validationError([
                'feeRules' => ['feeRules must be an array']
            ]);
        }

        $rules = [];
        foreach ($value as $index => $rule) {
            if (!is_array($rule)) {
                Response::validationError([
                    'feeRules' => ["feeRules[{$index}] must be an object"]
                ]);
            }

            $rules[] = [
                'transactionType' => trim((string)($rule['transactionType'] ?? '')),
                'thresholdAmount' => $this->normalizeDecimal($rule['thresholdAmount'] ?? null, "feeRules[{$index}].thresholdAmount"),
                'feeMode' => trim((string)($rule['feeMode'] ?? ($rule['mode'] ?? ''))),
                'percentage' => $this->normalizeDecimal($rule['percentage'] ?? 0, "feeRules[{$index}].percentage"),
                'fixed' => $this->normalizeDecimal($rule['fixed'] ?? 0, "feeRules[{$index}].fixed"),
                'minFee' => $this->normalizeNullableDecimal($rule['minFee'] ?? null, "feeRules[{$index}].minFee"),
                'maxFee' => $this->normalizeNullableDecimal($rule['maxFee'] ?? null, "feeRules[{$index}].maxFee"),
                'chargeToClient' => array_key_exists('chargeToClient', $rule) ? ($rule['chargeToClient'] ? 1 : 0) : 1,
                'sortOrder' => isset($rule['sortOrder']) && $rule['sortOrder'] !== '' ? (int)$rule['sortOrder'] : $index,
                'isActive' => array_key_exists('isActive', $rule) ? ($rule['isActive'] ? 1 : 0) : 1
            ];
        }

        return $rules;
    }

    private function buildGatewayFeeRulesUpdateDataForType($value, $transactionType) {
        $rules = $this->buildGatewayFeeRulesUpdateData($value);
        if ($rules === null || empty($rules)) {
            Response::validationError([
                'feeRules' => ['feeRules is required']
            ]);
        }

        foreach ($rules as $index => &$rule) {
            if (!empty($rule['transactionType']) && $rule['transactionType'] !== $transactionType) {
                Response::validationError([
                    'feeRules' => ["feeRules[{$index}].transactionType must be {$transactionType}"]
                ]);
            }

            $rule['transactionType'] = $transactionType;
        }
        unset($rule);

        return $rules;
    }

    private function normalizeDecimal($value, $fieldName) {
        if ($value === null || $value === '') {
            return 0;
        }

        if (!is_numeric($value)) {
            Response::validationError([
                $fieldName => ["{$fieldName} must be numeric"]
            ]);
        }

        return (float)$value;
    }

    private function normalizeNullableDecimal($value, $fieldName) {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            Response::validationError([
                $fieldName => ["{$fieldName} must be numeric"]
            ]);
        }

        return (float)$value;
    }

    private function normalizeGatewayAmountDecimalPlaces($value) {
        if ($value === null || $value === '') {
            return self::DEFAULT_GATEWAY_AMOUNT_DECIMAL_PLACES;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            Response::validationError([
                'amountDecimalPlaces' => ['amountDecimalPlaces must be an integer']
            ]);
        }

        $decimalPlaces = (int)$value;
        if ($decimalPlaces < 0 || $decimalPlaces > self::MAX_GATEWAY_AMOUNT_DECIMAL_PLACES) {
            Response::validationError([
                'amountDecimalPlaces' => [
                    'amountDecimalPlaces must be between 0 and ' . self::MAX_GATEWAY_AMOUNT_DECIMAL_PLACES
                ]
            ]);
        }

        return $decimalPlaces;
    }

    private function resolveGatewayAmountDecimalPlaces(array $gateway) {
        $value = $gateway['amountDecimalPlaces'] ?? null;
        if ($value === null || $value === '') {
            return self::DEFAULT_GATEWAY_AMOUNT_DECIMAL_PLACES;
        }

        $decimalPlaces = (int)$value;
        if ($decimalPlaces < 0 || $decimalPlaces > self::MAX_GATEWAY_AMOUNT_DECIMAL_PLACES) {
            return self::DEFAULT_GATEWAY_AMOUNT_DECIMAL_PLACES;
        }

        return $decimalPlaces;
    }

    private function getDefaultGatewayFeeConfig($gatewaySettingId) {
        return [
            'gatewaySettingId' => (int)$gatewaySettingId,
            'calculationMode' => 'single',
            'minDeposit' => null,
            'maxDeposit' => null,
            'minWithdrawal' => null,
            'maxWithdrawal' => null,
            'feeRules' => [],
            'isActive' => true,
            'notes' => null
        ];
    }

    private function assertGatewayExists($gatewaySettingId) {
        $gateway = $this->gatewayModel->findById((int)$gatewaySettingId);
        if (!$gateway) {
            Response::notFound('Gateway not found');
        }
    }

    private function validateGatewayFeeLimits($config) {
        $this->validateFundingAmountRange('Deposit', $config['minDeposit'] ?? null, $config['maxDeposit'] ?? null, 'minDeposit', 'maxDeposit');
        $this->validateFundingAmountRange('Withdrawal', $config['minWithdrawal'] ?? null, $config['maxWithdrawal'] ?? null, 'minWithdrawal', 'maxWithdrawal');
    }

    private function validateGatewayFeeConfig($config) {
        $rules = $config['feeRules'] ?? [];
        if (!is_array($rules) || empty($rules)) {
            Response::validationError([
                'feeRules' => ['feeRules is required']
            ]);
        }

        $this->validateGatewayFeeLimits($config);

        $calculationMode = $this->inferGatewayFeeCalculationMode($rules);
        if ($calculationMode === 'single') {
            $this->validateSingleRules($rules);
        } else {
            $this->validateThresholdRules($rules);
        }
    }

    private function inferGatewayFeeCalculationMode($rules) {
        if (!is_array($rules) || empty($rules)) {
            return 'single';
        }

        $groupedCounts = [];
        foreach ($rules as $rule) {
            $transactionType = $rule['transactionType'] ?? '';
            if (!in_array($transactionType, ['deposit', 'withdrawal'], true)) {
                continue;
            }

            $groupedCounts[$transactionType] = ($groupedCounts[$transactionType] ?? 0) + 1;
            if ($groupedCounts[$transactionType] > 1) {
                return 'threshold';
            }
        }

        return 'single';
    }

    private function updateGatewayFeeRulesByType($gatewaySettingId, $transactionType) {
        $admin = $this->requireAdmin();
        $gatewaySettingId = (int)$gatewaySettingId;
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            $input = [];
        }

        $gateway = $this->gatewayModel->findById($gatewaySettingId);
        if (!$gateway) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsGatewayFeeFailure',
                'Payment gateway not found'
            );
            Response::notFound('Gateway not found');
        }

        // 操作日志会往 body 顶层注入 logSubModuleKey / operationLogSubModule，剔除后剩下的才是手续费规则
        $rulesInput = is_array($input) ? $input : [];
        unset($rulesInput['logSubModuleKey'], $rulesInput['operationLogSubModule']);
        $typedRules = $this->buildGatewayFeeRulesUpdateDataForType($rulesInput, $transactionType);

        $existing = $this->gatewayFeeModel->getByGatewaySettingId($gatewaySettingId);
        $merged = $existing ?? $this->getDefaultGatewayFeeConfig($gatewaySettingId);
        $merged['feeRules'] = $this->mergeGatewayFeeRulesByType($existing['feeRules'] ?? [], $typedRules, $transactionType);
        $merged['calculationMode'] = $this->inferGatewayFeeCalculationMode($merged['feeRules']);
        $this->validateGatewayFeeConfig($merged);

        $db = Database::getInstance();
        $textMethod = $transactionType === 'withdrawal'
            ? 'transactionSettingsGatewayWithdrawFee'
            : 'transactionSettingsGatewayDepositFee';
        try {
            $db->beginTransaction();

            $gatewayFundingSettingId = $this->gatewayFeeModel->upsertByGatewaySettingId($gatewaySettingId, [
                'calculationMode' => $merged['calculationMode'],
                'updatedBy' => $admin['userId']
            ]);
            $this->gatewayFeeRuleModel->replaceByGatewayFundingSettingId($gatewayFundingSettingId, $merged['feeRules']);

            $db->commit();
        } catch (Exception $e) {
            $db->rollback();
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsGatewayFeeFailure',
                'Failed to update gateway fee settings: ' . $e->getMessage()
            );
            Response::error('Failed to update gateway fee settings: ' . $e->getMessage(), 500);
        }

        TransactionSettingsOperationLog::logGatewayByIdSuccess($input, $gatewaySettingId, $textMethod);

        Response::success(
            $this->gatewayFeeModel->getByGatewaySettingId($gatewaySettingId),
            ucfirst($transactionType) . ' fee settings updated successfully'
        );
    }

    private function mergeGatewayFeeRulesByType($existingRules, $typedRules, $transactionType) {
        $keptRules = [];
        foreach ($existingRules as $rule) {
            if (($rule['transactionType'] ?? null) !== $transactionType) {
                $keptRules[] = $rule;
            }
        }

        return array_merge($keptRules, $typedRules);
    }

    private function validateFundingAmountRange($label, $minAmount, $maxAmount, $minField, $maxField) {
        if ($minAmount !== null && (float)$minAmount < 0) {
            Response::validationError([
                $minField => ["{$minField} cannot be negative"]
            ]);
        }

        if ($maxAmount !== null && (float)$maxAmount < 0) {
            Response::validationError([
                $maxField => ["{$maxField} cannot be negative"]
            ]);
        }

        if ($minAmount !== null && $maxAmount !== null && (float)$maxAmount < (float)$minAmount) {
            Response::validationError([
                $maxField => ["{$label} max amount must be greater than or equal to min amount"]
            ]);
        }
    }

    private function validateFeeRuleValues($label, $mode, $percentage, $fixed, $minFee, $maxFee) {
        $validModes = ['none', 'fixed', 'dynamic'];
        if (!in_array($mode, $validModes, true)) {
            Response::validationError([
                'feeRules' => ["{$label}.feeMode must be one of: " . implode(', ', $validModes)]
            ]);
        }

        if ($percentage < 0 || $fixed < 0 || ($minFee !== null && $minFee < 0) || ($maxFee !== null && $maxFee < 0)) {
            Response::validationError([
                'feeRules' => ["{$label} fee values cannot be negative"]
            ]);
        }

        if ($mode === 'dynamic' && $percentage <= 0) {
            Response::validationError([
                'feeRules' => ["{$label}.percentage must be greater than 0 when fee mode is dynamic"]
            ]);
        }

        if ($mode === 'none' && ($percentage > 0 || $fixed > 0)) {
            Response::validationError([
                'feeRules' => ["{$label} fee mode is none, percentage/fixed must be 0"]
            ]);
        }

        if ($minFee !== null && $maxFee !== null && $maxFee < $minFee) {
            Response::validationError([
                'feeRules' => ["{$label}.maxFee must be greater than or equal to minFee"]
            ]);
        }
    }

    private function validateSingleRules($rules) {
        $grouped = [];
        foreach ($rules as $index => $rule) {
            $transactionType = $rule['transactionType'] ?? '';
            if (!in_array($transactionType, ['deposit', 'withdrawal'], true)) {
                Response::validationError([
                    'feeRules' => ["feeRules[{$index}].transactionType must be deposit or withdrawal"]
                ]);
            }

            $this->validateRuleRow($index, $rule);
            $grouped[$transactionType][] = $rule;
        }

        foreach ($grouped as $transactionType => $transactionRules) {
            if (count($transactionRules) !== 1) {
                Response::validationError([
                    'feeRules' => ["single mode requires exactly one {$transactionType} rule"]
                ]);
            }

            $thresholdAmount = (float)($transactionRules[0]['thresholdAmount'] ?? -1);
            if (abs($thresholdAmount) > 0.0001) {
                Response::validationError([
                    'feeRules' => ["single mode {$transactionType} rule thresholdAmount must be 0"]
                ]);
            }
        }
    }

    private function validateThresholdRules($rules) {
        $grouped = [];
        foreach ($rules as $index => $rule) {
            $transactionType = $rule['transactionType'] ?? '';
            if (!in_array($transactionType, ['deposit', 'withdrawal'], true)) {
                Response::validationError([
                    'feeRules' => ["feeRules[{$index}].transactionType must be deposit or withdrawal"]
                ]);
            }

            $this->validateRuleRow($index, $rule);
            $grouped[$transactionType][] = $rule;
        }

        foreach ($grouped as $transactionType => $transactionRules) {
            usort($transactionRules, function ($a, $b) {
                $thresholdCompare = (float)$a['thresholdAmount'] <=> (float)$b['thresholdAmount'];
                if ($thresholdCompare !== 0) {
                    return $thresholdCompare;
                }

                return (int)($a['sortOrder'] ?? 0) <=> (int)($b['sortOrder'] ?? 0);
            });

            $seenThresholds = [];
            foreach ($transactionRules as $idx => $rule) {
                $thresholdAmount = (float)$rule['thresholdAmount'];
                if ($idx === 0 && abs($thresholdAmount) > 0.0001) {
                    Response::validationError([
                        'feeRules' => ["{$transactionType} threshold rules must start from thresholdAmount 0"]
                    ]);
                }

                $thresholdKey = number_format($thresholdAmount, 4, '.', '');
                if (isset($seenThresholds[$thresholdKey])) {
                    Response::validationError([
                        'feeRules' => ["{$transactionType} thresholdAmount cannot repeat"]
                    ]);
                }
                $seenThresholds[$thresholdKey] = true;
            }
        }
    }

    private function validateRuleRow($index, $rule) {
        $thresholdAmount = isset($rule['thresholdAmount']) ? (float)$rule['thresholdAmount'] : -1;
        if ($thresholdAmount < 0) {
            Response::validationError([
                'feeRules' => ["feeRules[{$index}].thresholdAmount cannot be negative"]
            ]);
        }

        $mode = $rule['feeMode'] ?? ($rule['mode'] ?? null);
        $percentage = isset($rule['percentage']) && $rule['percentage'] !== '' ? (float)$rule['percentage'] : 0;
        $fixed = isset($rule['fixed']) && $rule['fixed'] !== '' ? (float)$rule['fixed'] : 0;
        $minFee = array_key_exists('minFee', $rule) && $rule['minFee'] !== '' ? (float)$rule['minFee'] : null;
        $maxFee = array_key_exists('maxFee', $rule) && $rule['maxFee'] !== '' ? (float)$rule['maxFee'] : null;

        $this->validateFeeRuleValues("feeRules[{$index}]", $mode, $percentage, $fixed, $minFee, $maxFee);

        if (isset($rule['sortOrder']) && (int)$rule['sortOrder'] < 0) {
            Response::validationError([
                'feeRules' => ["feeRules[{$index}].sortOrder cannot be negative"]
            ]);
        }
    }

    private function sortClientGatewaysByName(array $gateways): array {
        usort($gateways, static function ($left, $right) {
            return strcasecmp(
                (string)($left['gatewayName'] ?? ''),
                (string)($right['gatewayName'] ?? '')
            );
        });

        return $gateways;
    }

    private function formatGatewayFeeForClient($feeSettings, $transactionType) {
        if (!$feeSettings || empty($feeSettings['isActive'])) {
            return null;
        }

        $quickKey = $transactionType === 'withdrawal' ? 'withdrawalQuickAmounts' : 'depositQuickAmounts';
        $quickAmounts = array_values(array_map(static function ($row) {
            return [
                'id' => (int)($row['id'] ?? 0),
                'amount' => (float)($row['amount'] ?? 0),
                'sortOrder' => (int)($row['sortOrder'] ?? 0),
            ];
        }, $feeSettings[$quickKey] ?? []));

        return [
            'calculationMode' => $feeSettings['calculationMode'] ?? 'single',
            'minAmount' => $transactionType === 'withdrawal'
                ? ($feeSettings['minWithdrawal'] ?? null)
                : ($feeSettings['minDeposit'] ?? null),
            'maxAmount' => $transactionType === 'withdrawal'
                ? ($feeSettings['maxWithdrawal'] ?? null)
                : ($feeSettings['maxDeposit'] ?? null),
            'quickAmounts' => $quickAmounts,
            'rules' => array_values(array_filter($feeSettings['feeRules'] ?? [], function ($rule) use ($transactionType) {
                return ($rule['transactionType'] ?? null) === $transactionType;
            }))
        ];
    }

    /**
     * 获取所有汇率设置
     * GET /api/transaction-settings/exchange-rates
     */
    public function getExchangeRates() {
        $this->requireAdmin();

        $includeInactive = isset($_GET['includeInactive']) && $_GET['includeInactive'] === 'true';

        if ($includeInactive) {
            $rates = $this->exchangeRateModel->getAllRates();
        } else {
            $rates = $this->exchangeRateModel->getActiveRates();
        }

        Response::success($rates);
    }

    /**
     * 创建汇率
     * POST /api/transaction-settings/exchange-rates
     */
    public function createExchangeRate() {
        $user = $this->requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        // 验证必填字段
        if (empty($input['currencyCode'])) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'add',
                'transactionSettingsExchangeRateAddFailure',
                'Currency code is required'
            );
            Response::error('Currency code is required', 400);
        }

        if (!isset($input['exchangeRate']) || $input['exchangeRate'] <= 0) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'add',
                'transactionSettingsExchangeRateAddFailure',
                'Exchange rate must be greater than 0'
            );
            Response::error('Exchange rate must be greater than 0', 400);
        }

        $currencyCode = strtoupper(trim($input['currencyCode']));
        $type = strtolower(trim((string)($input['type'] ?? 'fiat')));

        if (!in_array($type, ['fiat', 'crypto'], true)) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'add',
                'transactionSettingsExchangeRateAddFailure',
                'Type must be fiat or crypto'
            );
            Response::error('Type must be fiat or crypto', 400);
        }

        // 检查是否已存在
        if ($this->exchangeRateModel->currencyCodeExists($currencyCode, null, $type)) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'add',
                'transactionSettingsExchangeRateAddFailure',
                'Currency code already exists'
            );
            Response::error('Currency code already exists', 409);
        }

        // 准备数据
        $rateData = [
            'type' => $type,
            'currencyCode' => $currencyCode,
            'currencyName' => $input['currencyName'] ?? null,
            'currencySymbol' => $input['currencySymbol'] ?? '$',
            'exchangeRate' => (float)$input['exchangeRate'],
            'syncMode' => $this->normalizeSyncMode($input['syncMode'] ?? 'manual'),
            'depositType' => $this->normalizeExchangeRateMode($input['depositType'] ?? 'fixed', 'depositType'),
            'withdrawType' => $this->normalizeExchangeRateMode($input['withdrawType'] ?? 'fixed', 'withdrawType'),
            'depositBias' => $this->normalizeExchangeRateBias($input['depositBias'] ?? 0, 'depositBias'),
            'withdrawBias' => $this->normalizeExchangeRateBias($input['withdrawBias'] ?? 0, 'withdrawBias'),
            'sortOrder' => isset($input['sortOrder']) ? (int)$input['sortOrder'] : 0,
            'isActive' => isset($input['isActive']) ? (int)$input['isActive'] : 1,
            'updatedBy' => $user['userId']
        ];

        $this->validateExchangeRateAdminConfig($rateData);

        try {
            $id = $this->exchangeRateModel->create($rateData);

            if ($id) {
                TransactionSettingsOperationLog::logExchangeRate($input, 'add', $currencyCode);
                $newRate = $this->exchangeRateModel->findById($id);
                Response::success($newRate, 'Exchange rate created successfully', 201);
            } else {
                TransactionSettingsOperationLog::logFailure(
                    $input,
                    'add',
                    'transactionSettingsExchangeRateAddFailure',
                    'Failed to create exchange rate'
                );
                Response::error('Failed to create exchange rate', 500);
            }
        } catch (Exception $e) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'add',
                'transactionSettingsExchangeRateAddFailure',
                'Failed to create exchange rate: ' . $e->getMessage()
            );
            Response::error('Failed to create exchange rate: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新汇率
     * PUT /api/transaction-settings/exchange-rates/{id}
     */
    public function updateExchangeRate($id) {
        $user = $this->requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $rate = $this->exchangeRateModel->findById($id);
        if (!$rate) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsExchangeRateEditFailure',
                'Exchange rate not found'
            );
            Response::error('Exchange rate not found', 404);
        }

        // 如果更新了货币代码，检查是否冲突
        if (isset($input['currencyCode'])) {
            $newCurrencyCode = strtoupper(trim($input['currencyCode']));
            $lookupType = strtolower(trim((string)($input['type'] ?? ($rate['type'] ?? 'fiat'))));
            if ($newCurrencyCode !== $rate['currencyCode'] && $this->exchangeRateModel->currencyCodeExists($newCurrencyCode, $id, $lookupType)) {
                TransactionSettingsOperationLog::logFailure(
                    $input,
                    'edit',
                    'transactionSettingsExchangeRateEditFailure',
                    'Currency code already exists'
                );
                Response::error('Currency code already exists', 409);
            }
            $input['currencyCode'] = $newCurrencyCode;
        }

        if (isset($input['type'])) {
            $newType = strtolower(trim((string)$input['type']));
            if (!in_array($newType, ['fiat', 'crypto'], true)) {
                TransactionSettingsOperationLog::logFailure(
                    $input,
                    'edit',
                    'transactionSettingsExchangeRateEditFailure',
                    'Type must be fiat or crypto'
                );
                Response::error('Type must be fiat or crypto', 400);
            }
            if (
                (!isset($input['currencyCode']) || $input['currencyCode'] === $rate['currencyCode'])
                && $newType !== ($rate['type'] ?? 'fiat')
                && $this->exchangeRateModel->currencyCodeExists($rate['currencyCode'], $id, $newType)
            ) {
                TransactionSettingsOperationLog::logFailure(
                    $input,
                    'edit',
                    'transactionSettingsExchangeRateEditFailure',
                    'Currency code already exists'
                );
                Response::error('Currency code already exists', 409);
            }
            $input['type'] = $newType;
        }

        // 验证汇率值
        if (isset($input['exchangeRate']) && $input['exchangeRate'] <= 0) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsExchangeRateEditFailure',
                'Exchange rate must be greater than 0'
            );
            Response::error('Exchange rate must be greater than 0', 400);
        }

        $updateData = [];
        $allowedFields = [
            'type',
            'currencyCode',
            'currencyName',
            'currencySymbol',
            'exchangeRate',
            'syncMode',
            'depositType',
            'withdrawType',
            'depositBias',
            'withdrawBias',
            'sortOrder',
            'isActive'
        ];
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                if ($field === 'sortOrder') {
                    $updateData[$field] = (int)$input[$field];
                } elseif ($field === 'currencyName') {
                    $updateData[$field] = $this->normalizeOptionalText($input[$field]);
                } elseif ($field === 'currencySymbol') {
                    $updateData[$field] = $this->normalizeCurrencySymbol($input[$field], null);
                } elseif (in_array($field, ['depositType', 'withdrawType'], true)) {
                    $updateData[$field] = $this->normalizeExchangeRateMode($input[$field], $field);
                } elseif (in_array($field, ['depositBias', 'withdrawBias'], true)) {
                    $updateData[$field] = $this->normalizeExchangeRateBias($input[$field], $field);
                } elseif ($field === 'exchangeRate') {
                    $updateData[$field] = (float)$input[$field];
                } elseif ($field === 'syncMode') {
                    $updateData[$field] = $this->normalizeSyncMode($input[$field]);
                } else {
                    $updateData[$field] = $input[$field];
                }
            }
        }

        $updateData['updatedBy'] = $user['userId'];

        $mergedRateData = [
            'exchangeRate' => array_key_exists('exchangeRate', $updateData)
                ? $updateData['exchangeRate']
                : (float)($rate['exchangeRate'] ?? 0),
            'depositType' => array_key_exists('depositType', $updateData)
                ? $updateData['depositType']
                : $this->normalizeExchangeRateMode($rate['depositType'] ?? 'fixed', 'depositType'),
            'withdrawType' => array_key_exists('withdrawType', $updateData)
                ? $updateData['withdrawType']
                : $this->normalizeExchangeRateMode($rate['withdrawType'] ?? 'fixed', 'withdrawType'),
            'depositBias' => array_key_exists('depositBias', $updateData)
                ? $updateData['depositBias']
                : $this->normalizeExchangeRateBias($rate['depositBias'] ?? 0, 'depositBias'),
            'withdrawBias' => array_key_exists('withdrawBias', $updateData)
                ? $updateData['withdrawBias']
                : $this->normalizeExchangeRateBias($rate['withdrawBias'] ?? 0, 'withdrawBias')
        ];
        $this->validateExchangeRateAdminConfig($mergedRateData);

        try {
            $success = $this->exchangeRateModel->update($id, $updateData);

            if ($success) {
                $currencyCode = (string) ($updateData['currencyCode'] ?? $rate['currencyCode'] ?? '');
                list($extraZh, $extraEn) = TransactionSettingsOperationLog::detectExchangeRateChanges($rate, $updateData);
                TransactionSettingsOperationLog::logExchangeRate($input, 'edit', $currencyCode, $extraZh, $extraEn);
                $updatedRate = $this->exchangeRateModel->findById($id);
                Response::success($updatedRate, 'Exchange rate updated successfully');
            } else {
                TransactionSettingsOperationLog::logFailure(
                    $input,
                    'edit',
                    'transactionSettingsExchangeRateEditFailure',
                    'Failed to update exchange rate'
                );
                Response::error('Failed to update exchange rate', 500);
            }
        } catch (Exception $e) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsExchangeRateEditFailure',
                'Failed to update exchange rate: ' . $e->getMessage()
            );
            Response::error('Failed to update exchange rate: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 删除汇率
     * DELETE /api/transaction-settings/exchange-rates/{id}
     */
    public function deleteExchangeRate($id) {
        $this->requireAdmin();
        $input = $_GET;
        if (!is_array($input)) {
            $input = [];
        }

        $rate = $this->exchangeRateModel->findById($id);
        if (!$rate) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'delete',
                'transactionSettingsExchangeRateDeleteFailure',
                'Exchange rate not found'
            );
            Response::error('Exchange rate not found', 404);
        }

        try {
            $success = $this->exchangeRateModel->delete($id);

            if ($success) {
                TransactionSettingsOperationLog::logExchangeRate(
                    $input,
                    'delete',
                    (string) ($rate['currencyCode'] ?? '')
                );
                Response::success(null, 'Exchange rate deleted successfully');
            } else {
                TransactionSettingsOperationLog::logFailure(
                    $input,
                    'delete',
                    'transactionSettingsExchangeRateDeleteFailure',
                    'Failed to delete exchange rate'
                );
                Response::error('Failed to delete exchange rate', 500);
            }
        } catch (Exception $e) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'delete',
                'transactionSettingsExchangeRateDeleteFailure',
                'Failed to delete exchange rate: ' . $e->getMessage()
            );
            Response::error('Failed to delete exchange rate: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 切换汇率启用状态
     * POST /api/transaction-settings/exchange-rates/{id}/toggle
     */
    public function toggleExchangeRate($id) {
        $this->requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $rate = $this->exchangeRateModel->findById($id);
        if (!$rate) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsExchangeRateEditFailure',
                'Exchange rate not found'
            );
            Response::error('Exchange rate not found', 404);
        }

        try {
            $success = $this->exchangeRateModel->toggleActive($id);

            if ($success) {
                $updatedRate = $this->exchangeRateModel->findById($id);
                $enabled = !empty($updatedRate['isActive']);
                TransactionSettingsOperationLog::logExchangeRate(
                    $input,
                    $enabled ? 'enable' : 'disable',
                    (string) ($rate['currencyCode'] ?? '')
                );
                Response::success($updatedRate, 'Exchange rate status updated successfully');
            } else {
                TransactionSettingsOperationLog::logFailure(
                    $input,
                    'edit',
                    'transactionSettingsExchangeRateEditFailure',
                    'Failed to update exchange rate status'
                );
                Response::error('Failed to update exchange rate status', 500);
            }
        } catch (Exception $e) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsExchangeRateEditFailure',
                'Failed to update exchange rate status: ' . $e->getMessage()
            );
            Response::error('Failed to update exchange rate status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 切换某条汇率的 auto / manual。
     * POST /api/transaction-settings/exchange-rates/{id}/sync-mode
     */
    public function toggleExchangeRateSyncMode($id) {
        $this->requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $rate = $this->exchangeRateModel->findById($id);
        if (!$rate) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsExchangeRateEditFailure',
                'Exchange rate not found'
            );
            Response::error('Exchange rate not found', 404);
        }

        try {
            $success = $this->exchangeRateModel->toggleSyncMode($id);

            if ($success) {
                $updatedRate = $this->exchangeRateModel->findById($id);
                $newMode = ($updatedRate['syncMode'] ?? 'auto') === 'auto' ? 'auto' : 'manual';
                TransactionSettingsOperationLog::logExchangeRate(
                    $input,
                    'edit',
                    (string) ($rate['currencyCode'] ?? ''),
                    $newMode === 'auto' ? '汇率同步模式改为自动' : '汇率同步模式改为手动',
                    $newMode === 'auto' ? 'Sync mode set to auto' : 'Sync mode set to manual'
                );
                Response::success($updatedRate, 'Exchange rate sync mode updated successfully');
            } else {
                TransactionSettingsOperationLog::logFailure(
                    $input,
                    'edit',
                    'transactionSettingsExchangeRateEditFailure',
                    'Failed to update exchange rate sync mode'
                );
                Response::error('Failed to update exchange rate sync mode', 500);
            }
        } catch (Exception $e) {
            TransactionSettingsOperationLog::logFailure(
                $input,
                'edit',
                'transactionSettingsExchangeRateEditFailure',
                'Failed to update exchange rate sync mode: ' . $e->getMessage()
            );
            Response::error('Failed to update exchange rate sync mode: ' . $e->getMessage(), 500);
        }
    }

    private function normalizeExchangeRateMode($value, $fieldName) {
        $mode = strtolower(trim((string)$value));
        if (!in_array($mode, ['fixed', 'dynamic'], true)) {
            Response::validationError([
                $fieldName => ["{$fieldName} must be fixed or dynamic"]
            ]);
        }

        return $mode;
    }

    private function normalizeSyncMode($value) {
        $mode = strtolower(trim((string)$value));
        if (!in_array($mode, ['auto', 'manual'], true)) {
            Response::validationError([
                'syncMode' => ['syncMode must be auto or manual']
            ]);
        }

        return $mode;
    }

    private function normalizeExchangeRateBias($value, $fieldName) {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (!is_numeric($value)) {
            Response::validationError([
                $fieldName => ["{$fieldName} must be numeric"]
            ]);
        }

        return (float)$value;
    }

    private function validateExchangeRateAdminConfig(array $config) {
        $exchangeRate = isset($config['exchangeRate']) ? (float)$config['exchangeRate'] : 0.0;
        if ($exchangeRate <= 0) {
            Response::validationError([
                'exchangeRate' => ['exchangeRate must be greater than 0']
            ]);
        }

        $depositType = $this->normalizeExchangeRateMode($config['depositType'] ?? 'fixed', 'depositType');
        $withdrawType = $this->normalizeExchangeRateMode($config['withdrawType'] ?? 'fixed', 'withdrawType');
        $depositBias = $this->normalizeExchangeRateBias($config['depositBias'] ?? 0, 'depositBias');
        $withdrawBias = $this->normalizeExchangeRateBias($config['withdrawBias'] ?? 0, 'withdrawBias');

        $this->validateExchangeRateAdminScope('deposit', $exchangeRate, $depositType, $depositBias);
        $this->validateExchangeRateAdminScope('withdraw', $exchangeRate, $withdrawType, $withdrawBias);
    }

    private function validateExchangeRateAdminScope($scope, $exchangeRate, $mode, $bias) {
        $typeField = $scope === 'withdraw' ? 'withdrawType' : 'depositType';
        $biasField = $scope === 'withdraw' ? 'withdrawBias' : 'depositBias';
        $targetField = $scope === 'withdraw' ? 'withdrawTarget' : 'depositTarget';

        if ($mode === 'dynamic') {
            if ($bias <= -1) {
                Response::validationError([
                    $biasField => ["{$biasField} must be greater than -1 when {$typeField} is dynamic"]
                ]);
            }

            return;
        }

        $targetRate = $exchangeRate + $bias;
        if ($targetRate <= 0) {
            Response::validationError([
                $targetField => ["{$targetField} must be greater than 0 when {$typeField} is fixed"]
            ]);
        }
    }

    private function normalizeOptionalText($value) {
        if ($value === null) {
            return null;
        }

        $text = trim((string)$value);
        return $text === '' ? null : $text;
    }

    private function normalizeCurrencySymbol($value, $default = '$') {
        if ($value === null) {
            return $default;
        }

        $symbol = trim((string)$value);
        if ($symbol === '') {
            return $default;
        }

        return $symbol;
    }

    /**
     * 要求管理员认证
     */
    private function requireAdmin() {
        $payload = JWT::getPayload();

        if (!$payload || ($payload['type'] ?? '') !== 'admin') {
            Response::forbidden('Admin authentication required');
        }

        $userId = $payload['userId'] ?? null;
        if (!$userId) {
            Response::unauthorized('Invalid token payload');
        }

        return [
            'userId' => $userId
        ];
    }

    private function getSupportedDisplayContentScopes() {
        return ['internal_transfer', 'deposit', 'withdrawal'];
    }

    private function normalizeDisplayContentScope($scope) {
        $scope = trim((string)$scope);
        if (!in_array($scope, $this->getSupportedDisplayContentScopes(), true)) {
            Response::validationError([
                'scope' => ['Invalid display content scope']
            ]);
        }

        return $scope;
    }

    private function buildDefaultDisplayContent($scope) {
        $scope = $this->normalizeDisplayContentScope($scope);

        return [
            'id' => null,
            'scope' => $scope,
            'contentJson' => [],
            'isActive' => false,
            'createdBy' => null,
            'updatedBy' => null,
            'createdAt' => null,
            'updatedAt' => null
        ];
    }

    private function normalizePaymentSupportQuestionScope($scope) {
        $scope = $this->paymentSupportQuestionModel->normalizeScope($scope);
        if (!in_array($scope, [
            PaymentSupportQuestion::SCOPE_WITHDRAW,
            PaymentSupportQuestion::SCOPE_DEPOSIT
        ], true)) {
            Response::validationError([
                'scope' => ['Invalid payment support question scope']
            ]);
        }

        return $scope;
    }

    private function buildPaymentSupportQuestionUpdateData(array $input) {
        $updateData = [];
        $stringFields = ['name', 'hintText', 'questionType', 'validationRules'];
        foreach ($stringFields as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            $value = $input[$field];
            if ($value === null) {
                if (in_array($field, ['name', 'questionType'], true)) {
                    Response::validationError([
                        $field => [$field . ' is required']
                    ]);
                }
                $updateData[$field] = null;
                continue;
            }

            $trimmed = trim((string)$value);
            if (in_array($field, ['name', 'questionType'], true) && $trimmed === '') {
                Response::validationError([
                    $field => [$field . ' is required']
                ]);
            }
            $updateData[$field] = $field === 'hintText' && $trimmed === '' ? null : $trimmed;
        }

        if (array_key_exists('scope', $input)) {
            $updateData['scope'] = $this->normalizePaymentSupportQuestionScope($input['scope']);
        }

        if (array_key_exists('questionType', $updateData)) {
            $this->validatePaymentSupportQuestionType($updateData['questionType']);
        }

        foreach (['isLocked', 'isActive'] as $field) {
            if (array_key_exists($field, $input)) {
                $updateData[$field] = (int)(bool)$input[$field];
            }
        }

        if (array_key_exists('options', $input)) {
            if ($input['options'] === null || $input['options'] === '') {
                $updateData['options'] = [];
            } elseif (is_array($input['options'])) {
                $updateData['options'] = array_values($input['options']);
            } elseif (is_string($input['options'])) {
                $decoded = json_decode($input['options'], true);
                if (!is_array($decoded)) {
                    Response::validationError([
                        'options' => ['options must be an array or valid JSON array']
                    ]);
                }
                $updateData['options'] = array_values($decoded);
            } else {
                Response::validationError([
                    'options' => ['options must be an array or null']
                ]);
            }
        }

        return $updateData;
    }

    private function buildPaymentSupportQuestionCreateData(array $input, $gatewaySettingId) {
        $createData = $this->buildPaymentSupportQuestionUpdateData($input);

        foreach (['name', 'questionType', 'scope'] as $field) {
            if (!array_key_exists($field, $createData) || $createData[$field] === null || $createData[$field] === '') {
                Response::validationError([
                    $field => [$field . ' is required']
                ]);
            }
        }

        if (!array_key_exists('options', $createData)) {
            $createData['options'] = [];
        }

        if (!array_key_exists('isLocked', $createData)) {
            $createData['isLocked'] = 0;
        }

        if (!array_key_exists('isActive', $createData)) {
            $createData['isActive'] = 1;
        }

        $createData['paymentGatewayId'] = (int)$gatewaySettingId;
        return $createData;
    }

    private function validatePaymentSupportQuestionUniqueName($gatewaySettingId, $scope, $name, $ignoreId = null) {
        $normalizedName = mb_strtolower(trim((string)$name));
        if ($normalizedName === '') {
            return;
        }

        $normalizedScope = $this->normalizePaymentSupportQuestionScope($scope);
        $questions = $this->paymentSupportQuestionModel->getAdminQuestions((int)$gatewaySettingId, $normalizedScope);
        foreach ($questions as $question) {
            if ($ignoreId !== null && (int)($question['id'] ?? 0) === (int)$ignoreId) {
                continue;
            }

            $existingName = mb_strtolower(trim((string)($question['name'] ?? '')));
            if ($existingName === $normalizedName) {
                Response::validationError([
                    'name' => ['name must be unique within this gateway']
                ]);
            }
        }
    }

    private function validatePaymentSupportQuestionType($questionType) {
        $normalized = trim((string)$questionType);
        if (!in_array($normalized, self::PAYMENT_SUPPORT_QUESTION_TYPES, true)) {
            Response::validationError([
                'questionType' => ['questionType must be one of: ' . implode(', ', self::PAYMENT_SUPPORT_QUESTION_TYPES)]
            ]);
        }
    }

    private function getRestrictedPaymentSupportQuestionUpdateErrors(array $existingQuestion, array $updateData) {
        if (empty($existingQuestion['isLocked'])) {
            return [];
        }

        $errors = [];
        foreach ($updateData as $field => $value) {
            if ($field === 'hintText' || $field === 'updatedBy') {
                continue;
            }

            if ($this->paymentSupportQuestionFieldChanged($existingQuestion, $field, $value)) {
                $errors[$field] = ['This row is locked. Only hintText can be updated.'];
            }
        }

        return $errors;
    }

    private function paymentSupportQuestionFieldChanged(array $existingQuestion, $field, $incomingValue) {
        $currentValue = $existingQuestion[$field] ?? null;

        if ($field === 'options') {
            $currentOptions = $this->paymentSupportQuestionModel->normalizeOptionsPayload(
                is_array($currentValue) ? $currentValue : []
            );
            $incomingOptions = $this->paymentSupportQuestionModel->normalizeOptionsPayload(
                is_array($incomingValue) ? $incomingValue : []
            );
            return $currentOptions !== $incomingOptions;
        }

        if (in_array($field, ['isLocked', 'isActive'], true)) {
            return (int)(bool)$currentValue !== (int)(bool)$incomingValue;
        }

        if ($field === 'scope') {
            return $this->normalizePaymentSupportQuestionScope($currentValue) !== $this->normalizePaymentSupportQuestionScope($incomingValue);
        }

        return (string)($currentValue ?? '') !== (string)($incomingValue ?? '');
    }

    private function formatPaymentSupportQuestionForResponse(array $question) {
        $question['paymentGatewayId'] = (int)($question['paymentGatewayId'] ?? 0);
        $question['gatewaySettingId'] = (int)($question['paymentGatewayId'] ?? 0);
        $question['updatedBy'] = isset($question['updatedBy']) ? (int)$question['updatedBy'] : null;
        return $question;
    }

    private function buildDisplayContentPayload($scope, $input) {
        $isActive = isset($input['isActive']) ? (int)(bool)$input['isActive'] : 1;

        $contentJson = $input['contentJson'] ?? null;
        if (!is_array($contentJson) || $this->isAssociativeArray($contentJson)) {
            $contentJson = [[
                'title' => $input['title'] ?? ($contentJson['title'] ?? ''),
                'content' => $input['content'] ?? ($contentJson['content'] ?? ''),
                'iconClass' => $input['iconClass'] ?? ($contentJson['iconClass'] ?? '')
            ]];
        }

        $normalizedEntries = [];
        foreach ($contentJson as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = trim((string)($item['title'] ?? ''));
            $content = trim((string)($item['content'] ?? ''));
            $iconClass = trim((string)($item['iconClass'] ?? ''));

            if ($isActive === 1) {
                if ($scope === 'internal_transfer' && $title === '') {
                    Response::validationError([
                        'contentJson' => ["title is required for internal_transfer item at index {$index}"]
                    ]);
                }

                if ($content === '') {
                    Response::validationError([
                        'contentJson' => ["content is required for item at index {$index}"]
                    ]);
                }
            }

            if ($title === '' && $content === '' && $iconClass === '') {
                continue;
            }

            $normalizedEntries[] = [
                'title' => $title,
                'content' => $content,
                'iconClass' => $iconClass
            ];
        }

        if ($isActive === 1) {
            if (empty($normalizedEntries)) {
                Response::validationError([
                    'contentJson' => ['At least one content item is required']
                ]);
            }
        }

        return [
            'contentJson' => json_encode($normalizedEntries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'isActive' => $isActive
        ];
    }

    private function isAssociativeArray(array $value) {
        return array_keys($value) !== range(0, count($value) - 1);
    }
}
