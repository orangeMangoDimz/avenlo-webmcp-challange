<?php
/**
 * Withdrawal Controller
 * 负责提款管理相关接口
 */

require_once __DIR__ . '/../models/Withdrawal.php';
require_once __DIR__ . '/../models/WithdrawalStatusHistory.php';
require_once __DIR__ . '/../models/WithdrawalTag.php';
require_once __DIR__ . '/../models/WithdrawalTagAssignment.php';
require_once __DIR__ . '/../models/RejectionReason.php';
require_once __DIR__ . '/../models/WithdrawalDocumentRequest.php';
require_once __DIR__ . '/../models/WithdrawalDocumentRequestItem.php';
require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../models/TradingAccount.php';
require_once __DIR__ . '/../models/TradingAccountExternalAccount.php';
require_once __DIR__ . '/../models/TradingPlatform.php';
require_once __DIR__ . '/../models/TransactionLimit.php';
require_once __DIR__ . '/../models/ClientNotification.php';
require_once __DIR__ . '/../models/ClientSystemNotification.php';
require_once __DIR__ . '/../models/AdminNotification.php';
require_once __DIR__ . '/../models/AdminNotificationDelivery.php';
require_once __DIR__ . '/../models/AdminSystemNotification.php';
require_once __DIR__ . '/../models/OTPVerification.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/ClientIp.php';
require_once __DIR__ . '/../utils/EmailSender.php';
require_once __DIR__ . '/../utils/S3Uploader.php';
require_once __DIR__ . '/../utils/FinanceProApiClient.php';
require_once __DIR__ . '/../utils/Mt5ApiClient.php';
require_once __DIR__ . '/../models/FinanceProToken.php';
require_once __DIR__ . '/../models/SecuritySettings.php';
require_once __DIR__ . '/../models/ClientPaymentAccount.php';
require_once __DIR__ . '/../models/PaymentGatewaySetting.php';
require_once __DIR__ . '/../models/PaymentGatewayFundingSetting.php';
require_once __DIR__ . '/../models/PaymentSupportQuestion.php';
require_once __DIR__ . '/../models/PaymentMethod.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplate.php';
require_once __DIR__ . '/../models/ClientWithdrawalVerificationSubmission.php';
require_once __DIR__ . '/../models/ClientWithdrawalVerificationAnswer.php';
require_once __DIR__ . '/../models/CurrencyExchangeRate.php';
require_once __DIR__ . '/../services/TradingAccountAmountService.php';
require_once __DIR__ . '/../services/WalletBalanceService.php';
require_once __DIR__ . '/../services/PaymentSettlementService.php';
require_once __DIR__ . '/../services/PaymentProcessorRequestLogService.php';
require_once __DIR__ . '/../services/CoinsbuyService.php';
require_once __DIR__ . '/../services/VexoraService.php';
require_once __DIR__ . '/../services/FlashPayService.php';
require_once __DIR__ . '/../services/CvPayService.php';
require_once __DIR__ . '/../services/FivePayService.php';
require_once __DIR__ . '/../services/XLinkService.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';

class WithdrawalController {
    private const DEFAULT_GATEWAY_AMOUNT_DECIMAL_PLACES = 2;
    private const MAX_GATEWAY_AMOUNT_DECIMAL_PLACES = 4;

    private $withdrawalModel;
    private $statusHistoryModel;
    private $tagModel;
    private $tagAssignmentModel;
    private $rejectionReasonModel;
    private $documentRequestModel;
    private $documentRequestItemModel;
    private $userModel;
    private $tradingAccountModel;
    private $externalAccountModel;
    private $platformModel;
    private $limitModel;
    private $clientNotificationModel;
    private $clientSystemNotificationModel;
    private $adminNotificationModel;
    private $adminNotificationDeliveryModel;
    private $adminSystemNotificationModel;
    private $otpModel;
    private $financePro;
    private $financeProClient;
    private $mt5;
    private $mt5Client;
    private $ibeepay;
    private $appConfig;
    private $clientFrontendUrl;
    private $gatewayModel;
    private $gatewayFeeModel;
    private $paymentSupportQuestionModel;
    private $paymentMethodModel;
    private $withdrawalTemplateModel;
    private $withdrawalSubmissionModel;
    private $withdrawalAnswerModel;
    private $exchangeRateModel;
    private $tradingAccountAmountService;
    private $walletBalanceService;
    private $paymentService;
    private $requestLogService;

    public function __construct() {
        $this->withdrawalModel = new Withdrawal();
        $this->statusHistoryModel = new WithdrawalStatusHistory();
        $this->tagModel = new WithdrawalTag();
        $this->tagAssignmentModel = new WithdrawalTagAssignment();
        $this->rejectionReasonModel = new RejectionReason();
        $this->documentRequestModel = new WithdrawalDocumentRequest();
        $this->documentRequestItemModel = new WithdrawalDocumentRequestItem();
        $this->userModel = new ClientUser();
        $this->tradingAccountModel = new TradingAccount();
        $this->externalAccountModel = new TradingAccountExternalAccount();
        $this->platformModel = new TradingPlatform();
        $this->limitModel = new TransactionLimit();
        $this->clientNotificationModel = new ClientNotification();
        $this->clientSystemNotificationModel = new ClientSystemNotification();
        $this->adminNotificationModel = new AdminNotification();
        $this->adminNotificationDeliveryModel = new AdminNotificationDelivery();
        $this->adminSystemNotificationModel = new AdminSystemNotification();
        $this->otpModel = new OTPVerification();

        $appConfig = require __DIR__ . '/../config/app.php';
        $this->appConfig = $appConfig;
        $this->financePro = $appConfig['integrations']['finance_pro'] ?? [];
        $this->financeProClient = new FinanceProApiClient($this->financePro);
        $this->mt5 = $appConfig['integrations']['mt5'] ?? [];
        $this->mt5Client = new Mt5ApiClient($this->mt5);
        $this->ibeepay = $appConfig['integrations']['ibeepay'] ?? [];
        $this->clientFrontendUrl = rtrim((string)($appConfig['client_frontend_url'] ?? ''), '/');
        $this->gatewayModel = new PaymentGatewaySetting();
        $this->gatewayFeeModel = new PaymentGatewayFundingSetting();
        $this->paymentSupportQuestionModel = new PaymentSupportQuestion();
        $this->paymentMethodModel = new PaymentMethod();
        $this->withdrawalTemplateModel = new WithdrawalVerificationTemplate();
        $this->withdrawalSubmissionModel = new ClientWithdrawalVerificationSubmission();
        $this->withdrawalAnswerModel = new ClientWithdrawalVerificationAnswer();
        $this->exchangeRateModel = new CurrencyExchangeRate();
        $this->tradingAccountAmountService = new TradingAccountAmountService();
        $this->walletBalanceService = new WalletBalanceService();
        $this->paymentService = new PaymentSettlementService();
        $this->requestLogService = new PaymentProcessorRequestLogService();
    }

    /**
     * 获取提款列表 (管理员)
     * GET /api/withdrawals
     */
    public function index() {
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 10;
        $search = $_GET['search'] ?? null;

        // 构建筛选条件
        $filters = [];

        // status 支持多选
        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $statusList = array_values(array_filter(array_map('trim', explode(',', $_GET['status'])), function ($s) {
                return $s !== '';
            }));
            if (!empty($statusList)) {
                $filters['status'] = $statusList;
            }
        }

        if (isset($_GET['startDate'])) {
            $filters['startDate'] = $_GET['startDate'];
        }

        if (isset($_GET['endDate'])) {
            $filters['endDate'] = $_GET['endDate'];
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_withdrawals');
        if ($scope['scope'] === 'none') {
            Response::paginated([], 0, $page, $perPage);
            return;
        }
        if ($scope['scope'] === 'own') {
            $filters['restrict_to_sales_id'] = $scope['restrict_to_sales_id'];
        }
        $restrictToSalesId = $scope['scope'] === 'own' ? (int)$scope['restrict_to_sales_id'] : null;

        // 如果有搜索关键词
        if ($search) {
            $result = $this->withdrawalModel->searchWithdrawals($search, $page, $perPage, $restrictToSalesId);
        } else {
            $result = $this->withdrawalModel->getWithdrawals($page, $perPage, $filters);
        }

        // 为每个提款添加标签信息
        foreach ($result['items'] as &$withdrawal) {
            $withdrawal['tags'] = $this->tagAssignmentModel->getWithdrawalTags($withdrawal['id']);
        }

        Response::paginated(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * 获取单个提款详情
     * GET /api/withdrawals/{id}
     */
    public function show($id) {
        $withdrawal = $this->withdrawalModel->getWithdrawalDetails($id);

        if (!$withdrawal) {
            Response::notFound('Withdrawal not found');
        }

        // 获取完整信息
        $withdrawal['tags'] = $this->tagAssignmentModel->getWithdrawalTags($id);
        $withdrawal['statusHistory'] = $this->statusHistoryModel->getWithdrawalHistory($id);
        $withdrawal['documentRequest'] = $this->documentRequestModel->getByWithdrawal($id);
        $withdrawal['supportQuestions'] = $this->buildWithdrawalSupportQuestions($withdrawal);

        // 如果有文档请求，获取项目列表
        if ($withdrawal['documentRequest']) {
            $withdrawal['documentRequest']['items'] = $this->documentRequestItemModel->getRequestItems(
                $withdrawal['documentRequest']['id']
            );
        }

        Response::success($withdrawal);
    }

    /**
     * 创建提款 (客户端)
     * POST /api/withdrawals
     */
    public function create() {
        $client = $this->requireClient();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!isset($data['amount']) && isset($data['total'])) {
            $data['amount'] = $data['total'];
        }
        Validator::make($data, [
            'amount' => 'required|numeric',
            'gatewaySettingId' => 'required|numeric',
            'withdrawalSubmissionId' => 'required|numeric',
            'tradingAccountId' => 'numeric'
        ]);

        $gatewaySettingId = (int)$data['gatewaySettingId'];
        $gateway = $this->requireEnabledWithdrawalGateway($gatewaySettingId);
        $submission = $this->requireUsableWithdrawalSubmission(
            (int)$data['withdrawalSubmissionId'],
            (int)$client['userId']
        );
        $template = $this->withdrawalTemplateModel->getTemplateSummary((int)$submission['templateId']);

        if (!$template || ($template['status'] ?? null) !== 'active') {
            Response::validationError([
                'withdrawalSubmissionId' => ['Withdrawal submission template is not active']
            ]);
        }

        if (
            (int)($submission['gatewaySettingId'] ?? 0) !== $gatewaySettingId
            || (int)($template['gatewaySettingId'] ?? 0) !== $gatewaySettingId
        ) {
            Response::validationError([
                'gatewaySettingId' => ['Gateway does not match the selected withdrawal submission/template']
            ]);
        }

        $responseData = $this->createWithdrawalRequest($client, $data, null, [
            'requirePaymentMethod' => false,
            'requirePaymentAccount' => false,
            'additionalUpdateData' => [
                'gatewaySettingId' => $gatewaySettingId,
                'withdrawalSubmissionId' => (int)$submission['id'],
                'supportContent' => json_encode(
                    $this->buildSubmissionSupportSnapshot((int)$submission['id']),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
            ]
        ]);

        Response::created($responseData, 'Withdrawal request submitted successfully');
    }

    public function createSupportData() {
        $client = $this->requireClient();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!isset($data['amount']) && isset($data['total'])) {
            $data['amount'] = $data['total'];
        }

        Validator::make($data, [
            'amount' => 'required|numeric',
            'gatewaySettingId' => 'required|numeric',
            'tradingAccountId' => 'numeric'
        ]);

        $gatewaySettingId = (int)$data['gatewaySettingId'];
        $this->requireEnabledWithdrawalGateway($gatewaySettingId);
        $activeTemplates = $this->withdrawalTemplateModel->getActiveTemplates($gatewaySettingId);
        if (!empty($activeTemplates)) {
            Response::validationError([
                'gatewaySettingId' => ['This gateway has an active verification template. Please use the verified withdrawal flow']
            ]);
        }

        $supportData = $data['supportData'] ?? null;
        if (!is_array($supportData)) {
            Response::validationError([
                'supportData' => ['supportData must be an object']
            ]);
        }

        $questions = $this->paymentSupportQuestionModel->getGatewayQuestions(
            $gatewaySettingId,
            PaymentSupportQuestion::SCOPE_WITHDRAW
        );
        if (empty($questions)) {
            Response::validationError([
                'gatewaySettingId' => ['No support questions are configured for this gateway']
            ]);
        }

        $normalizedSupportData = $this->validateSupportDataAgainstQuestions($supportData, $questions);
        $supportContent = json_encode($normalizedSupportData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $responseData = $this->createWithdrawalRequest($client, $data, $supportContent, [
            'requirePaymentMethod' => false,
            'requirePaymentAccount' => false,
            'additionalUpdateData' => [
                'gatewaySettingId' => $gatewaySettingId
            ]
        ]);

        Response::created($responseData, 'Withdrawal request submitted successfully');
    }

    public function createLegacy() {
        $client = $this->requireClient();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        Validator::make($data, [
            'gatewaySettingId' => 'required|numeric'
        ]);
        $responseData = $this->createWithdrawalRequest($client, $data, null, [
            'additionalUpdateData' => [
                'gatewaySettingId' => (int)$data['gatewaySettingId']
            ]
        ]);

        Response::created($responseData, 'Withdrawal request submitted successfully');
    }

    /**
     * 创建 Ibeepay 提款 (客户端)
     * POST /api/withdrawals/create/ibeepay
     */
    public function createIbeepay() {
        $client = $this->requireClient();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!isset($data['amount']) && isset($data['total'])) {
            $data['amount'] = $data['total'];
        }

        Validator::make($data, [
            'amount' => 'required|numeric',
            'name' => 'required|string',
            'dob' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string',
            'phoneCountryCode' => 'required|string',
            'tradingAccountId' => 'numeric'
        ]);

        $supportContent = json_encode([
            'name' => trim((string)($data['name'] ?? '')),
            'dob' => trim((string)($data['dob'] ?? '')),
            'email' => trim((string)($data['email'] ?? '')),
            'phone' => trim((string)($data['phone'] ?? '')),
            'phoneCountryCode' => trim((string)($data['phoneCountryCode'] ?? ''))
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $responseData = $this->createWithdrawalRequest($client, $data, $supportContent);

        Response::created($responseData, 'Withdrawal request submitted successfully');
    }

    private function createWithdrawalRequest($client, $data, $supportContent = null, $options = []) {
        if (!isset($data['amount']) && isset($data['total'])) {
            $data['amount'] = $data['total'];
        }
        $requirePaymentAccount = $options['requirePaymentAccount'] ?? true;
        $additionalUpdateData = $options['additionalUpdateData'] ?? [];

        $rules = [
            'amount' => 'required|numeric',
            'currency' => 'required|string',
            'tradingAccountId' => 'numeric'
        ];
        Validator::make($data, $rules);

        $amount = (float)$data['amount'];

        $tradingAccountId = !empty($data['tradingAccountId']) ? (int)$data['tradingAccountId'] : null;
        $supportPayload = $supportContent !== null ? (json_decode($supportContent, true) ?: []) : [];

        if ($requirePaymentAccount) {
            if (!empty($data['paymentAccountId'])) {
                $paymentAccountId = (int)$data['paymentAccountId'];
                $paymentAccountModel = new ClientPaymentAccount();
                $paymentAccount = $paymentAccountModel->findById($paymentAccountId);
                if (!$paymentAccount || $paymentAccount['userId'] != $client['userId']) {
                    Response::validationError([
                        'paymentAccountId' => ['Invalid payment account']
                    ]);
                }
                $supportPayload['paymentAccount'] = [
                    'paymentAccountId' => $paymentAccountId,
                    'legalName' => $paymentAccount['legalName'] ?? null,
                    'bsb' => $paymentAccount['bsb'] ?? null,
                    'accountNumber' => $paymentAccount['accountNumber'] ?? null
                ];
            } else {
                $legalName = isset($data['legalName']) ? trim($data['legalName']) : '';
                $bsb = isset($data['bsb']) ? trim($data['bsb']) : '';
                $accountNumber = isset($data['accountNumber']) ? trim($data['accountNumber']) : '';
                if ($legalName === '' || $bsb === '' || $accountNumber === '') {
                    Response::validationError([
                        'paymentAccountId' => ['Either paymentAccountId or (legalName, bsb, accountNumber) is required']
                    ]);
                }
                if (!ClientPaymentAccount::validateBSB($bsb)) {
                    Response::validationError(['bsb' => ['BSB must be 6 digits']]);
                }
                if (!ClientPaymentAccount::validateAccountNumber($accountNumber)) {
                    Response::validationError(['accountNumber' => ['Account number must be 6-50 digits']]);
                }
                if (strlen($legalName) > 255) {
                    Response::validationError(['legalName' => ['Legal name must not exceed 255 characters']]);
                }
                $supportPayload['paymentAccount'] = [
                    'legalName' => $legalName,
                    'bsb' => $bsb,
                    'accountNumber' => $accountNumber
                ];
            }
        }

        // 验证交易账户
        if ($tradingAccountId) {
            $tradingAccount = $this->tradingAccountModel->findById($tradingAccountId);
            if (!$tradingAccount || $tradingAccount['userId'] != $client['userId']) {
                Response::validationError([
                    'tradingAccountId' => ['Invalid trading account']
                ]);
            }
        }

        // 验证限额
        $gatewaySettingId = isset($additionalUpdateData['gatewaySettingId']) ? (int)$additionalUpdateData['gatewaySettingId'] : null;
        if ($gatewaySettingId <= 0) {
            Response::validationError([
                'gatewaySettingId' => ['gatewaySettingId is required']
            ]);
        }
        $quotePayload = $this->resolveGatewayQuote('withdrawal', $gatewaySettingId, $amount, $data, 'fiat');
        $limitValidation = $this->limitModel->validateAmount('withdrawal', $quotePayload['gatewayType'], $amount);
        if (!$limitValidation['valid']) {
            Response::validationError([
                'amount' => $limitValidation['errors']
            ]);
        }

        // 预生成 withdrawal transactionId，传给 platform debit 作为 FinancePro originOrderId，
        // 同时也作为 IN 参数传给 spCreateWithdrawal（SP 已改为接收外部 transactionId），
        // 保证平台扣款记录和 CRM withdrawal 记录使用同一个 transactionId。
        $newTransactionId = $this->withdrawalModel->generateTransactionId();

        $platformDebit = null;
        if ($tradingAccountId) {
            $platformDebitAmount = round((float)$amount, 2);
            $platformDebit = $this->paymentService->executeWithdrawalPlatformDebit(
                $tradingAccountId,
                $platformDebitAmount,
                'CRM withdrawal create user #' . (int)$client['userId'],
                null,
                $newTransactionId
            );
        }

        $mergedSupportPayload = $supportPayload;
        if (isset($additionalUpdateData['supportContent'])) {
            $prefilledSupport = json_decode((string)$additionalUpdateData['supportContent'], true);
            if (is_array($prefilledSupport)) {
                $mergedSupportPayload = array_merge($prefilledSupport, $mergedSupportPayload);
            }
        }

        $serializedSupportContent = null;
        if (!empty($mergedSupportPayload)) {
            $serializedSupportContent = json_encode($mergedSupportPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif ($supportContent !== null) {
            $serializedSupportContent = $supportContent;
        }

        $platformSnapshot = $this->paymentService->buildWithdrawalPlatformSnapshot($platformDebit);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $sql = "CALL spCreateWithdrawal(:transactionId, :userId, :tradingAccountId, :amount, :currencyCode, :exchangeRate, :platformFee, :quotedAmount, :networkFee, :withdrawalReason, :ipAddress, :gatewaySettingId, :supportContent, :amountScale, :platformAmount, :displayUnit, @withdrawalId)";
        $db = Database::getInstance();

        try {
            $db->query($sql, [
                'transactionId' => $newTransactionId,
                'userId' => $client['userId'],
                'tradingAccountId' => $tradingAccountId,
                'amount' => $amount,
                'currencyCode' => $quotePayload['currencyCode'],
                'exchangeRate' => $quotePayload['exchangeRate'],
                'platformFee' => $quotePayload['platformFee'],
                'quotedAmount' => $quotePayload['quotedAmount'],
                'networkFee' => $quotePayload['networkFee'],
                'withdrawalReason' => $data['withdrawalReason'] ?? null,
                'ipAddress' => $ipAddress,
                'gatewaySettingId' => $gatewaySettingId,
                'supportContent' => $serializedSupportContent,
                'amountScale' => $platformSnapshot['amountScale'] ?? null,
                'platformAmount' => $platformSnapshot['platformAmount'] ?? null,
                'displayUnit' => $platformSnapshot['displayUnit'] ?? null,
            ]);

            // 获取返回的ID
            $result = $db->fetchOne("SELECT @withdrawalId as withdrawalId");

            if (!$result || !$result['withdrawalId']) {
                throw new RuntimeException('Failed to create withdrawal');
            }

            // 获取完整的提款信息
            $withdrawal = $this->withdrawalModel->getWithdrawalDetails($result['withdrawalId']);
        } catch (Exception $e) {
            if ($platformDebit !== null) {
                // 回滚带上创建时用的 transactionId 作为 originOrderId，保持和扣款一致
                $rollbackResult = $this->paymentService->rollbackWithdrawalPlatformDebit($platformDebit, $newTransactionId);
                if (empty($rollbackResult['success'])) {
                    Response::serverError(
                        'Failed to create withdrawal after platform debit. Rollback failed: ' . (string)($rollbackResult['error'] ?? 'unknown error')
                    );
                }
            }
            Response::serverError('Failed to create withdrawal: ' . $e->getMessage());
        }

        if ($this->paymentService->needsBalanceSync($withdrawal)) {
            $this->paymentService->dispatchOrdersBalanceSyncTask();
        }

        try {
            $this->notifyAdminsOfWithdrawalCreated($withdrawal);
        } catch (Exception $e) {
            // Admin notice failure should not block request creation.
        }

        // 计算并返回可用余额
        $walletBreakdown = $this->walletBalanceService->getBreakdown((int)$client['userId']);

        $responseData = $withdrawal;
        $responseData['availableBalance'] = round((float)$walletBreakdown['availableBalance'], 2);
        $responseData['totalDeposits'] = round((float)$walletBreakdown['totalDeposits'], 2);
        $responseData['totalWithdrawals'] = round((float)$walletBreakdown['totalWithdrawals'], 2);
        return $responseData;
    }

    private function resolveGatewayQuote($transactionType, $gatewaySettingId, $amount, array $data, $fallbackGatewayType = 'fiat') {
        $currencyCode = strtoupper(trim((string)($data['currency'] ?? '')));
        if ($currencyCode === '') {
            Response::validationError([
                'currency' => ['currency is required']
            ]);
        }

        if ($gatewaySettingId === null || (int)$gatewaySettingId <= 0) {
            Response::validationError([
                'gatewaySettingId' => ['gatewaySettingId is required']
            ]);
        }

        $gatewayType = $fallbackGatewayType;
        $gateway = $this->gatewayModel->findById((int)$gatewaySettingId);
        if (!$gateway || empty($gateway['isEnabled'])) {
            Response::validationError([
                'gatewaySettingId' => ['Selected gateway is not available']
            ]);
        }

        $gatewayType = strtolower((string)($gateway['type'] ?? $fallbackGatewayType));
        $amountDecimalPlaces = $this->resolveGatewayAmountDecimalPlaces($gateway);
        $supportedCurrencies = $gatewayType === 'crypto'
            ? $this->parseJsonList($gateway['supportedCryptoCurrencies'] ?? null)
            : $this->parseJsonList($gateway['supportedFiatCurrencies'] ?? null);

        if (empty($supportedCurrencies) || !in_array($currencyCode, $supportedCurrencies, true)) {
            Response::validationError([
                'currency' => ['Selected currency is not supported by this gateway']
            ]);
        }

        $rate = $this->exchangeRateModel->getRateByCurrencyCode($currencyCode, $gatewayType);
        $expectedExchangeRate = $this->exchangeRateModel->resolveAdjustedRate($rate, $transactionType);
        if ($expectedExchangeRate === null || $expectedExchangeRate <= 0) {
            Response::validationError([
                'currency' => ['Exchange rate is not available for the selected currency']
            ]);
        }

        $feeResult = $this->gatewayFeeModel->calculateForTransaction((int)$gatewaySettingId, $transactionType, $amount);
        $fundingSettings = $feeResult['feeSettings'] ?? [];
        $minWithdrawal = isset($fundingSettings['minWithdrawal']) && $fundingSettings['minWithdrawal'] !== null
            ? (float)$fundingSettings['minWithdrawal']
            : null;
        $maxWithdrawal = isset($fundingSettings['maxWithdrawal']) && $fundingSettings['maxWithdrawal'] !== null
            ? (float)$fundingSettings['maxWithdrawal']
            : null;

        if ($minWithdrawal !== null && $amount < $minWithdrawal) {
            Response::validationError([
                'amount' => ['Amount is below this gateway minimum withdrawal']
            ]);
        }

        if ($maxWithdrawal !== null && $amount > $maxWithdrawal) {
            Response::validationError([
                'amount' => ['Amount exceeds this gateway maximum withdrawal']
            ]);
        }

        $platformFee = round((float)$feeResult['platformFee'], 2);
        $netAmount = isset($feeResult['netAmount'])
            ? round((float)$feeResult['netAmount'], 2)
            : round($amount - $platformFee, 2);
        $quotedAmount = round($netAmount * $expectedExchangeRate, $amountDecimalPlaces);

        if (array_key_exists('total', $data) && $data['total'] !== null && $data['total'] !== '') {
            if (!is_numeric($data['total'])) {
                Response::validationError([
                    'total' => ['total must be numeric']
                ]);
            }

            $submittedTotal = round((float)$data['total'], $amountDecimalPlaces);
            if ($this->formatRoundedAmount($submittedTotal, $amountDecimalPlaces) !== $this->formatRoundedAmount($quotedAmount, $amountDecimalPlaces)) {
                Response::validationError([
                    'total' => ['Price expired, please refresh price']
                ]);
            }
        }

        return [
            'gatewayType' => $gatewayType,
            'currencyCode' => $currencyCode,
            'exchangeRate' => $expectedExchangeRate,
            'platformFee' => $platformFee,
            'quotedAmount' => $quotedAmount,
            'networkFee' => isset($data['networkFee']) ? (float)$data['networkFee'] : 0.0
        ];
    }
    private function parseJsonList($value) {
        if (empty($value)) {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                return [];
            }
            return array_values(array_map('strtoupper', array_map('trim', $decoded)));
        }

        if (is_array($value)) {
            return array_values(array_map('strtoupper', array_map('trim', $value)));
        }

        return [];
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

    private function formatRoundedAmount($amount, $decimalPlaces) {
        return number_format(round((float)$amount, (int)$decimalPlaces), (int)$decimalPlaces, '.', '');
    }

    private function requireEnabledWithdrawalGateway($gatewaySettingId) {
        $gateway = $this->gatewayModel->findById($gatewaySettingId);
        if (!$gateway || empty($gateway['isEnabled']) || empty($gateway['isWithdrawalEnabled'])) {
            Response::validationError([
                'gatewaySettingId' => ['Selected gateway is not available for withdrawals']
            ]);
        }

        $linkedMethod = $this->paymentMethodModel->findByKey($gateway['gatewayKey'] ?? '');
        if ($linkedMethod && empty($linkedMethod['isWithdrawalEnabled'])) {
            Response::validationError([
                'gatewaySettingId' => ['Selected payment method is not available for withdrawals']
            ]);
        }

        return $gateway;
    }

    private function requireUsableWithdrawalSubmission($submissionId, $clientId) {
        $submission = $this->withdrawalSubmissionModel->findById($submissionId);
        if (!$submission || (int)($submission['clientId'] ?? 0) !== $clientId) {
            Response::validationError([
                'withdrawalSubmissionId' => ['Invalid withdrawal submission']
            ]);
        }

        if (($submission['submissionStatus'] ?? '') !== 'approved') {
            Response::validationError([
                'withdrawalSubmissionId' => ['Withdrawal submission is not approved']
            ]);
        }

        return $submission;
    }

    private function validateSupportDataAgainstQuestions(array $supportData, array $questions) {
        $errors = [];
        $normalized = [];

        foreach ($questions as $question) {
            $name = (string)($question['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $value = $supportData[$name] ?? null;
            $fieldErrors = $this->validateSupportAnswerValue($question, $value);
            if (!empty($fieldErrors)) {
                $errors[$name] = $fieldErrors;
                continue;
            }

            $normalized[$name] = $this->normalizeSupportAnswerValue($question, $value);
        }

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        return $normalized;
    }

    private function validateSupportAnswerValue(array $question, $value) {
        $errors = [];
        $name = (string)($question['name'] ?? 'field');
        $questionType = (string)($question['questionType'] ?? 'text');
        $validationRules = trim((string)($question['validationRules'] ?? ''));
        $options = is_array($question['options'] ?? null) ? $question['options'] : [];
        $allowedOptionValues = $this->paymentSupportQuestionModel->extractEnabledOptionValues($options);

        $rules = $validationRules !== '' ? explode('|', $validationRules) : [];
        $hasRequired = in_array('required', $rules, true);
        if ($hasRequired && ($value === null || $value === '')) {
            $errors[] = "{$name} is required";
            return $errors;
        }

        if ($value === null || $value === '') {
            return $errors;
        }

        if (in_array($questionType, ['single_choice', 'yes_no'], true) && !empty($allowedOptionValues) && !in_array((string)$value, $allowedOptionValues, true)) {
            $errors[] = "{$name} must be one of: " . implode(', ', $allowedOptionValues);
        }

        foreach ($rules as $rule) {
            if ($rule === 'required') {
                continue;
            }

            [$ruleName, $ruleParam] = array_pad(explode(':', $rule, 2), 2, null);
            switch ($ruleName) {
                case 'email':
                    if (!filter_var((string)$value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "{$name} must be a valid email";
                    }
                    break;
                case 'date':
                    if (strtotime((string)$value) === false) {
                        $errors[] = "{$name} must be a valid date";
                    }
                    break;
                case 'min':
                    if (mb_strlen((string)$value) < (int)$ruleParam) {
                        $errors[] = "{$name} must be at least {$ruleParam} characters";
                    }
                    break;
                case 'max':
                    if (mb_strlen((string)$value) > (int)$ruleParam) {
                        $errors[] = "{$name} must not exceed {$ruleParam} characters";
                    }
                    break;
            }
        }

        return $errors;
    }

    private function normalizeSupportAnswerValue(array $question, $value) {
        if ($value === null) {
            return null;
        }

        $questionType = (string)($question['questionType'] ?? 'text');
        if ($questionType === 'date') {
            $timestamp = strtotime((string)$value);
            return $timestamp !== false ? date('Y-m-d', $timestamp) : trim((string)$value);
        }

        return trim((string)$value);
    }

    private function buildWithdrawalSupportQuestions(array $withdrawal) {
        $gatewaySettingId = (int)($withdrawal['gatewaySettingId'] ?? 0);
        $rawSupportContent = trim((string)($withdrawal['supportContent'] ?? ''));

        if ($gatewaySettingId <= 0 || $rawSupportContent === '') {
            return [];
        }

        $answers = json_decode($rawSupportContent, true);
        if (!is_array($answers)) {
            return [];
        }

        $questions = $this->paymentSupportQuestionModel->getGatewayQuestions(
            $gatewaySettingId,
            PaymentSupportQuestion::SCOPE_WITHDRAW
        );
        if (empty($questions)) {
            return [];
        }

        $supportQuestions = [];
        foreach ($questions as $question) {
            $name = (string)($question['name'] ?? '');
            if ($name === '' || !array_key_exists($name, $answers)) {
                continue;
            }

            $supportQuestions[] = [
                'id' => (int)($question['id'] ?? 0),
                'name' => $name,
                'hintText' => $question['hintText'] ?? null,
                'questionType' => $question['questionType'] ?? 'text',
                'options' => is_array($question['options'] ?? null) ? $question['options'] : [],
                'answer' => $answers[$name]
            ];
        }

        return $supportQuestions;
    }

    /**
     * 批准提款 (管理员)
     * POST /api/withdrawals/{id}/approve
     */
    public function approve($id) {
        $admin = $this->requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogWithdrawals($data);
        $opLog = new AdminOperationLogWriter();

        $withdrawal = $this->withdrawalModel->findById($id);
        if (!$withdrawal) {
            $opLog->logWithdrawalApprove($subModule, 0, '', '', 0, false, 'Withdrawal not found');
            Response::notFound('Withdrawal not found');
        }

        $clientId = (int) ($withdrawal['userId'] ?? 0);
        $transactionId = (string) ($withdrawal['transactionId'] ?? '');
        $clientName = $this->resolveWithdrawalClientName($withdrawal);

        if ($withdrawal['status'] !== 'pending') {
            $opLog->logWithdrawalApprove(
                $subModule,
                $clientId,
                $transactionId,
                $clientName,
                $withdrawal['amount'] ?? 0,
                false,
                'Only pending withdrawals can be approved'
            );
            Response::error('Only pending withdrawals can be approved', 400);
        }

        $adminNotes = $data['adminNotes'] ?? null;
        $ibeepaySupportData = null;
        $paymentAsiaSupportData = null;
        $coinsbuySupportData = null;
        $gateway = $this->resolveWithdrawalGatewaySetting($withdrawal, true);

        if (($gateway['gatewayKey'] ?? null) === 'ibeepay') {
            $ibeepaySupportData = $this->resolveIbeepayWithdrawalSupportData($withdrawal);
        }
        if ($this->isPaymentAsiaGateway($gateway)) {
            $paymentAsiaSupportData = $this->resolvePaymentAsiaWithdrawalSupportData($withdrawal);
        }
        if ($this->isCoinsbuyGateway($gateway)) {
            $coinsbuySupportData = $this->resolveCoinsbuyWithdrawalSupportData($withdrawal);
        }
        $vexoraSupportData = null;
        if ($this->isVexoraGateway($gateway)) {
            $vexoraSupportData = $this->resolveVexoraWithdrawalSupportData($withdrawal);
        }
        $flashpaySupportData = null;
        if ($this->isFlashPayGateway($gateway)) {
            $flashpaySupportData = $this->resolveFlashPayWithdrawalSupportData($withdrawal);
        }
        $cvpaySupportData = null;
        if ($this->isCvPayGateway($gateway)) {
            $cvpaySupportData = $this->resolveCvPayWithdrawalSupportData($withdrawal);
        }
        $fivePaySupportData = null;
        if ($this->isFivePayGateway($gateway)) {
            $fivePaySupportData = $this->resolveFivePayWithdrawalSupportData($withdrawal);
        }
        $xlinkSupportData = null;
        if ($this->isXLinkGateway($gateway)) {
            $xlinkSupportData = $this->resolveXLinkWithdrawalSupportData($withdrawal);
        }

        try {
            $updatedWithdrawal = $this->paymentService->markWithdrawalSuccess(
                $withdrawal,
                (int)$admin['userId'],
                $adminNotes
            );
        } catch (RuntimeException $e) {
            $opLog->logWithdrawalApprove(
                $subModule,
                $clientId,
                $transactionId,
                $clientName,
                $withdrawal['amount'] ?? 0,
                false,
                $e->getMessage()
            );
            Response::serverError($e->getMessage());
        }

        // 审批通过后调第三方出金 API（Ibeepay / Payment Asia）；失败则交给 service 自动 reject + 回滚
        if (($gateway['gatewayKey'] ?? null) === 'ibeepay') {
            $user = $this->userModel->findById($updatedWithdrawal['userId']);
            $ibeepayResult = $this->requestIbeepayWithdrawal($updatedWithdrawal, $user, $gateway, $ibeepaySupportData);
            if ($ibeepayResult !== null) {
                $updatedWithdrawal['ibeepay'] = $ibeepayResult;
                if (empty($ibeepayResult['success'])) {
                    $rejectReasonText = (string)($ibeepayResult['reason'] ?? $ibeepayResult['error'] ?? 'Ibeepay withdrawal failed');
                    // 1) 走和 admin 同一条 reject 路径（rollback 平台扣款 + spRejectWithdrawal + 通知 + balance sync）
                    $this->paymentService->markWithdrawalRejected($updatedWithdrawal, 'reject', (int)$admin['userId'], [
                        'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('withdrawal'),
                        'rejectionNotes' => $rejectReasonText,
                        'customReason' => $rejectReasonText,
                    ]);
                    // 2) 单独把 PSP 失败响应落审计 log
                    $this->paymentService->logProcessorCallbackFailure('withdrawal', $updatedWithdrawal, 'ibeepay', $rejectReasonText, $ibeepayResult);

                    $updatedWithdrawal = $this->withdrawalModel->getWithdrawalDetails((int)$updatedWithdrawal['id']);
                    $updatedWithdrawal['ibeepay'] = $ibeepayResult;
                    Response::success($updatedWithdrawal, 'Withdrawal rejected due to Ibeepay failure');
                }
            }
        }

        if ($this->isPaymentAsiaGateway($gateway)) {
            $paymentAsiaResult = $this->requestPaymentAsiaWithdrawal($updatedWithdrawal, $gateway, $paymentAsiaSupportData);
            if ($paymentAsiaResult !== null) {
                $updatedWithdrawal['paymentAsia'] = $paymentAsiaResult;
                if (empty($paymentAsiaResult['success'])) {
                    $rejectReasonText = (string)($paymentAsiaResult['reason'] ?? $paymentAsiaResult['error'] ?? 'Payment Asia withdrawal failed');
                    $this->paymentService->markWithdrawalRejected($updatedWithdrawal, 'reject', (int)$admin['userId'], [
                        'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('withdrawal'),
                        'rejectionNotes' => $rejectReasonText,
                        'customReason' => $rejectReasonText,
                    ]);
                    $this->paymentService->logProcessorCallbackFailure('withdrawal', $updatedWithdrawal, 'payment_asia', $rejectReasonText, $paymentAsiaResult);

                    $updatedWithdrawal = $this->withdrawalModel->getWithdrawalDetails((int)$updatedWithdrawal['id']);
                    $updatedWithdrawal['paymentAsia'] = $paymentAsiaResult;
                    Response::success($updatedWithdrawal, 'Withdrawal rejected due to Payment Asia failure');
                }
            }
        }

        if ($this->isVexoraGateway($gateway)) {
            $vexoraResult = $this->requestVexoraWithdrawal($updatedWithdrawal, $gateway, $vexoraSupportData);
            if ($vexoraResult !== null) {
                $updatedWithdrawal['vexora'] = $vexoraResult;
                if (empty($vexoraResult['success'])) {
                    $rejectReasonText = (string)($vexoraResult['reason'] ?? $vexoraResult['error'] ?? 'Vexora withdrawal failed');
                    $this->paymentService->markWithdrawalRejected($updatedWithdrawal, 'reject', (int)$admin['userId'], [
                        'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('withdrawal'),
                        'rejectionNotes' => $rejectReasonText,
                        'customReason' => $rejectReasonText,
                    ]);
                    $this->paymentService->logProcessorCallbackFailure('withdrawal', $updatedWithdrawal, 'vexora', $rejectReasonText, $vexoraResult['response'] ?? $vexoraResult);

                    $updatedWithdrawal = $this->withdrawalModel->getWithdrawalDetails((int)$updatedWithdrawal['id']);
                    $updatedWithdrawal['vexora'] = $vexoraResult;
                    Response::success($updatedWithdrawal, 'Withdrawal rejected due to Vexora failure');
                }
            }
        }

        if ($this->isFlashPayGateway($gateway)) {
            $flashpayResult = $this->requestFlashPayWithdrawal($updatedWithdrawal, $gateway, $flashpaySupportData);
            if ($flashpayResult !== null) {
                $updatedWithdrawal['flashpay'] = $flashpayResult;
                if (empty($flashpayResult['success'])) {
                    $rejectReasonText = (string)($flashpayResult['reason'] ?? $flashpayResult['error'] ?? 'FlashPay withdrawal failed');
                    $this->paymentService->markWithdrawalRejected($updatedWithdrawal, 'reject', (int)$admin['userId'], [
                        'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('withdrawal'),
                        'rejectionNotes' => $rejectReasonText,
                        'customReason' => $rejectReasonText,
                    ]);
                    $this->paymentService->logProcessorCallbackFailure('withdrawal', $updatedWithdrawal, 'flashpay', $rejectReasonText, $flashpayResult['response'] ?? $flashpayResult);

                    $updatedWithdrawal = $this->withdrawalModel->getWithdrawalDetails((int)$updatedWithdrawal['id']);
                    $updatedWithdrawal['flashpay'] = $flashpayResult;
                    Response::success($updatedWithdrawal, 'Withdrawal rejected due to FlashPay failure');
                }
            }
        }

        if ($this->isCvPayGateway($gateway)) {
            $cvpayResult = $this->requestCvPayWithdrawal($updatedWithdrawal, $gateway, $cvpaySupportData);
            if ($cvpayResult !== null) {
                $updatedWithdrawal['cvpay'] = $cvpayResult;
                if (empty($cvpayResult['success'])) {
                    $rejectReasonText = (string)($cvpayResult['reason'] ?? $cvpayResult['error'] ?? 'CVPay withdrawal failed');
                    $this->paymentService->markWithdrawalRejected($updatedWithdrawal, 'reject', (int)$admin['userId'], [
                        'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('withdrawal'),
                        'rejectionNotes' => $rejectReasonText,
                        'customReason' => $rejectReasonText,
                    ]);
                    $this->paymentService->logProcessorCallbackFailure('withdrawal', $updatedWithdrawal, 'cvpay', $rejectReasonText, $cvpayResult['response'] ?? $cvpayResult);

                    $updatedWithdrawal = $this->withdrawalModel->getWithdrawalDetails((int)$updatedWithdrawal['id']);
                    $updatedWithdrawal['cvpay'] = $cvpayResult;
                    Response::success($updatedWithdrawal, 'Withdrawal rejected due to CVPay failure');
                }
            }
        }

        if ($this->isFivePayGateway($gateway)) {
            $fivePayResult = $this->requestFivePayWithdrawal($updatedWithdrawal, $gateway, $fivePaySupportData);
            if ($fivePayResult !== null) {
                $updatedWithdrawal['fivepay'] = $fivePayResult;
                if (empty($fivePayResult['success'])) {
                    $rejectReasonText = (string)($fivePayResult['reason'] ?? $fivePayResult['error'] ?? '5Pay withdrawal failed');
                    $this->paymentService->markWithdrawalRejected($updatedWithdrawal, 'reject', (int)$admin['userId'], [
                        'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('withdrawal'),
                        'rejectionNotes' => $rejectReasonText,
                        'customReason' => $rejectReasonText,
                    ]);
                    $this->paymentService->logProcessorCallbackFailure('withdrawal', $updatedWithdrawal, '5pay', $rejectReasonText, $fivePayResult['response'] ?? $fivePayResult);

                    $updatedWithdrawal = $this->withdrawalModel->getWithdrawalDetails((int)$updatedWithdrawal['id']);
                    $updatedWithdrawal['fivepay'] = $fivePayResult;
                    Response::success($updatedWithdrawal, 'Withdrawal rejected due to 5Pay failure');
                }
            }
        }

        if ($this->isXLinkGateway($gateway)) {
            $xlinkResult = $this->requestXLinkWithdrawal($updatedWithdrawal, $gateway, $xlinkSupportData);
            $updatedWithdrawal['xlink'] = $xlinkResult;
            if (empty($xlinkResult['success']) && empty($xlinkResult['ambiguous'])) {
                $rejectReasonText = (string)($xlinkResult['reason'] ?? $xlinkResult['error'] ?? 'X-Link withdrawal failed');
                $this->paymentService->markWithdrawalRejected($updatedWithdrawal, 'reject', (int)$admin['userId'], [
                    'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('withdrawal'),
                    'rejectionNotes' => $rejectReasonText,
                    'customReason' => $rejectReasonText,
                ]);
                $this->paymentService->logProcessorCallbackFailure(
                    'withdrawal',
                    $updatedWithdrawal,
                    'xlink',
                    $rejectReasonText,
                    $xlinkResult['response'] ?? $xlinkResult
                );

                $updatedWithdrawal = $this->withdrawalModel->getWithdrawalDetails((int)$updatedWithdrawal['id']);
                $updatedWithdrawal['xlink'] = $xlinkResult;
                Response::success($updatedWithdrawal, 'Withdrawal rejected due to X-Link failure');
            }
        }

        if ($this->isCoinsbuyGateway($gateway)) {
            $coinsbuyResult = $this->requestCoinsbuyWithdrawal($updatedWithdrawal, $coinsbuySupportData);
            if ($coinsbuyResult !== null) {
                $updatedWithdrawal['coinsbuy'] = $coinsbuyResult;
                if (empty($coinsbuyResult['success'])) {
                    $rejectReasonText = (string)($coinsbuyResult['reason'] ?? $coinsbuyResult['error'] ?? 'Coinsbuy withdrawal failed');
                    $this->paymentService->markWithdrawalRejected($updatedWithdrawal, 'reject', (int)$admin['userId'], [
                        'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('withdrawal'),
                        'rejectionNotes' => $rejectReasonText,
                        'customReason' => $rejectReasonText,
                    ]);
                    $this->paymentService->logProcessorCallbackFailure('withdrawal', $updatedWithdrawal, 'coinsbuy', $rejectReasonText, $coinsbuyResult);

                    $updatedWithdrawal = $this->withdrawalModel->getWithdrawalDetails((int)$updatedWithdrawal['id']);
                    $updatedWithdrawal['coinsbuy'] = $coinsbuyResult;
                    Response::success($updatedWithdrawal, 'Withdrawal rejected due to Coinsbuy failure');
                }
            }
        }

        if (!$this->isAsyncPayoutGateway($gateway)) {
            $updatedWithdrawal = $this->completeApprovedWithdrawalWithoutPsp(
                $updatedWithdrawal,
                (int)$admin['userId']
            );
        }

        if ($this->paymentService->needsBalanceSync($updatedWithdrawal)) {
            $this->paymentService->dispatchOrdersBalanceSyncTask();
        }

        $opLog->logWithdrawalApprove(
            $subModule,
            (int) ($updatedWithdrawal['userId'] ?? $clientId),
            (string) ($updatedWithdrawal['transactionId'] ?? $transactionId),
            $this->resolveWithdrawalClientName($updatedWithdrawal),
            $updatedWithdrawal['amount'] ?? 0,
            true
        );

        Response::success($updatedWithdrawal, 'Withdrawal approved successfully');
    }

    /**
     * 拒绝提款 (管理员)
     * POST /api/withdrawals/{id}/reject
     */
    public function reject($id) {
        $admin = $this->requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogWithdrawals($data);
        $opLog = new AdminOperationLogWriter();

        $withdrawal = $this->withdrawalModel->findById($id);
        if (!$withdrawal) {
            $opLog->logWithdrawalReject($subModule, 0, '', '', false, 'Withdrawal not found');
            Response::notFound('Withdrawal not found');
        }

        $clientId = (int) ($withdrawal['userId'] ?? 0);
        $transactionId = (string) ($withdrawal['transactionId'] ?? '');

        if ($withdrawal['status'] !== 'pending') {
            $opLog->logWithdrawalReject(
                $subModule,
                $clientId,
                $transactionId,
                '',
                false,
                'Only pending withdrawals can be rejected'
            );
            Response::error('Only pending withdrawals can be rejected', 400);
        }

        $validator = new Validator($data, [
            'rejectionReasonId' => 'required|numeric'
        ]);
        if (!$validator->validate()) {
            $rejectErrors = $validator->getErrors();
            $opLog->logWithdrawalReject(
                $subModule,
                $clientId,
                $transactionId,
                '',
                false,
                OperationLogTextHelpers::validationErrorsToMessage($rejectErrors)
            );
            Response::validationError($rejectErrors);
        }

        $rejectionReasonId = (int)$data['rejectionReasonId'];
        $rejectionNotes = $data['rejectionNotes'] ?? null;
        $customReason = $data['customReason'] ?? null;

        $reason = $this->rejectionReasonModel->findById($rejectionReasonId);
        if (!$reason || ($reason['scope'] ?? null) !== 'withdrawal') {
            $opLog->logWithdrawalReject($subModule, $clientId, $transactionId, '', false, 'Invalid rejection reason');
            Response::validationError([
                'rejectionReasonId' => ['Invalid rejection reason']
            ]);
        }

        if ($reason['reasonKey'] === 'custom' && empty($customReason)) {
            $opLog->logWithdrawalReject(
                $subModule,
                $clientId,
                $transactionId,
                '',
                false,
                'Custom reason is required when selecting "Other" option'
            );
            Response::validationError([
                'customReason' => ['Custom reason is required when selecting "Other" option']
            ]);
        }

        try {
            $updatedWithdrawal = $this->paymentService->markWithdrawalRejected(
                $withdrawal,
                'reject',
                (int)$admin['userId'],
                [
                    'rejectionReasonId' => $rejectionReasonId,
                    'rejectionNotes' => $rejectionNotes,
                    'customReason' => $customReason,
                    'rejectionReasonTitle' => $reason['reasonTitle'] ?? null
                ]
            );
        } catch (RuntimeException $e) {
            $opLog->logWithdrawalReject($subModule, $clientId, $transactionId, '', false, $e->getMessage());
            Response::serverError($e->getMessage());
        }

        $reasonTitle = ($reason['reasonKey'] ?? '') === 'custom'
            ? trim((string) $customReason)
            : trim((string) ($reason['reasonTitle'] ?? ''));
        $opLog->logWithdrawalReject(
            $subModule,
            (int) ($updatedWithdrawal['userId'] ?? $clientId),
            (string) ($updatedWithdrawal['transactionId'] ?? $transactionId),
            $reasonTitle !== '' ? $reasonTitle : '—',
            true
        );

        Response::success($updatedWithdrawal, 'Withdrawal rejected');
    }

    /**
     * 取消提款 (客户端，仅自己的 pending withdrawal)
     * POST /api/withdrawals/{id}/cancel
     */
    public function cancel($id) {
        $client = $this->requireClient();

        $withdrawal = $this->withdrawalModel->findById($id);
        if (!$withdrawal) {
            Response::notFound('Withdrawal not found');
        }

        if ((int)($withdrawal['userId'] ?? 0) !== (int)$client['userId']) {
            Response::forbidden('You can only cancel your own withdrawal');
        }

        if (($withdrawal['status'] ?? '') !== 'pending') {
            Response::error('Only pending withdrawals can be cancelled', 400);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $reason = isset($data['reason']) ? trim((string)$data['reason']) : '';
        if ($reason === '') {
            $reason = 'Cancelled by client';
        }
        if (mb_strlen($reason) > 500) {
            Response::validationError([
                'reason' => ['reason must not exceed 500 characters']
            ]);
        }

        try {
            $updatedWithdrawal = $this->paymentService->markWithdrawalRejected(
                $withdrawal,
                'cancel',
                (int)$client['userId'],
                ['cancelReason' => $reason]
            );
        } catch (RuntimeException $e) {
            Response::serverError($e->getMessage());
        }

        Response::success($updatedWithdrawal, 'Withdrawal cancelled successfully');
    }

    /**
     * 完成提款 (管理员/系统)
     * POST /api/withdrawals/{id}/complete
     */
    public function complete($id) {
        $admin = $this->requireAdmin();

        $withdrawal = $this->withdrawalModel->findById($id);
        if (!$withdrawal) {
            Response::notFound('Withdrawal not found');
        }

        if ($withdrawal['status'] !== 'processing') {
            Response::error('Only processing withdrawals can be completed', 400);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $transactionHash = $data['transactionHash'] ?? null;

        // 调用存储过程完成提款
        $sql = "CALL spCompleteWithdrawal(:withdrawalId, :transactionHash, :completedBy)";

        $db = Database::getInstance();
        $db->query($sql, [
            'withdrawalId' => $id,
            'transactionHash' => $transactionHash,
            'completedBy' => $admin['userId']
        ]);

        // 获取更新后的提款信息
        $updatedWithdrawal = $this->withdrawalModel->getWithdrawalDetails($id);

        if ($this->paymentService->needsBalanceSync($updatedWithdrawal)) {
            $this->paymentService->dispatchOrdersBalanceSyncTask();
        }

        Response::success($updatedWithdrawal, 'Withdrawal completed successfully');
    }

    /**
     * 批量批准提款
     * POST /api/withdrawals/bulk-approve
     */
    public function bulkApprove() {
        $admin = $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogWithdrawals($data);
        $opLog = new AdminOperationLogWriter();

        $validator = new Validator($data, [
            'withdrawalIds' => 'required|array'
        ]);
        if (!$validator->validate()) {
            $bulkApproveErrors = $validator->getErrors();
            $opLog->logWithdrawalBulkApprove(
                $subModule,
                [],
                false,
                OperationLogTextHelpers::validationErrorsToMessage($bulkApproveErrors)
            );
            Response::validationError($bulkApproveErrors);
        }

        $withdrawalIds = $data['withdrawalIds'];
        $adminNotes = $data['adminNotes'] ?? null;

        $results = [];
        $successCount = 0;
        $failCount = 0;
        $approvedTransactionIds = [];

        foreach ($withdrawalIds as $withdrawalId) {
            try {
                $withdrawal = $this->withdrawalModel->findById($withdrawalId);
                $ibeepaySupportData = null;
                $ibeepayResult = null;
                $paymentAsiaSupportData = null;
                $paymentAsiaResult = null;
                $xlinkSupportData = null;
                $xlinkResult = null;

                if (!$withdrawal || $withdrawal['status'] !== 'pending') {
                    $errorMsg = 'Withdrawal not found or not in pending status';
                    $apiMessage = "Bulk approval failed at withdrawal ID {$withdrawalId}: {$errorMsg}";
                    $opLog->logWithdrawalBulkApprove($subModule, [], false, $apiMessage);
                    Response::error($apiMessage, 400);
                }

                $gateway = $this->resolveWithdrawalGatewaySetting($withdrawal, true);
                if (($gateway['gatewayKey'] ?? null) === 'ibeepay') {
                    $ibeepaySupportData = $this->resolveIbeepayWithdrawalSupportData($withdrawal);
                }
                if ($this->isPaymentAsiaGateway($gateway)) {
                    $paymentAsiaSupportData = $this->resolvePaymentAsiaWithdrawalSupportData($withdrawal);
                }
                if ($this->isXLinkGateway($gateway)) {
                    $xlinkSupportData = $this->resolveXLinkWithdrawalSupportData($withdrawal);
                }

                // 批量场景保留原行为：不逐条发通知，balance sync 由循环外统一调度
                $approvedWithdrawal = $this->paymentService->markWithdrawalSuccess(
                    $withdrawal,
                    (int)$admin['userId'],
                    $adminNotes,
                    false
                );

                if (($gateway['gatewayKey'] ?? null) === 'ibeepay') {
                    $user = $this->userModel->findById($approvedWithdrawal['userId']);
                    $ibeepayResult = $this->requestIbeepayWithdrawal($approvedWithdrawal, $user, $gateway, $ibeepaySupportData);
                    if ($ibeepayResult !== null && empty($ibeepayResult['success'])) {
                        $reasonText = (string)($ibeepayResult['reason'] ?? $ibeepayResult['error'] ?? 'Ibeepay withdrawal failed');
                        $this->paymentService->markWithdrawalRejected($approvedWithdrawal, 'reject', (int)$admin['userId'], [
                            'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('withdrawal'),
                            'rejectionNotes' => $reasonText,
                            'customReason' => $reasonText,
                        ]);
                        $this->paymentService->logProcessorCallbackFailure('withdrawal', $approvedWithdrawal, 'ibeepay', $reasonText, $ibeepayResult);
                        $results[] = [
                            'withdrawalId' => $withdrawalId,
                            'success' => false,
                            'message' => 'Rejected due to Ibeepay failure',
                            'ibeepay' => $ibeepayResult
                        ];
                        $failCount++;
                        continue;
                    }
                }

                if ($this->isPaymentAsiaGateway($gateway)) {
                    $paymentAsiaResult = $this->requestPaymentAsiaWithdrawal($approvedWithdrawal, $gateway, $paymentAsiaSupportData);
                    if ($paymentAsiaResult !== null && empty($paymentAsiaResult['success'])) {
                        $reasonText = (string)($paymentAsiaResult['reason'] ?? $paymentAsiaResult['error'] ?? 'Payment Asia withdrawal failed');
                        $this->paymentService->markWithdrawalRejected($approvedWithdrawal, 'reject', (int)$admin['userId'], [
                            'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('withdrawal'),
                            'rejectionNotes' => $reasonText,
                            'customReason' => $reasonText,
                        ]);
                        $this->paymentService->logProcessorCallbackFailure('withdrawal', $approvedWithdrawal, 'payment_asia', $reasonText, $paymentAsiaResult);
                        $results[] = [
                            'withdrawalId' => $withdrawalId,
                            'success' => false,
                            'message' => 'Rejected due to Payment Asia failure',
                            'paymentAsia' => $paymentAsiaResult
                        ];
                        $failCount++;
                        continue;
                    }
                }

                if ($this->isXLinkGateway($gateway)) {
                    $xlinkResult = $this->requestXLinkWithdrawal($approvedWithdrawal, $gateway, $xlinkSupportData);
                    if (empty($xlinkResult['success']) && empty($xlinkResult['ambiguous'])) {
                        $reasonText = (string)($xlinkResult['reason'] ?? $xlinkResult['error'] ?? 'X-Link withdrawal failed');
                        $this->paymentService->markWithdrawalRejected($approvedWithdrawal, 'reject', (int)$admin['userId'], [
                            'rejectionReasonId' => $this->paymentService->resolveCustomRejectionReasonId('withdrawal'),
                            'rejectionNotes' => $reasonText,
                            'customReason' => $reasonText,
                        ]);
                        $this->paymentService->logProcessorCallbackFailure(
                            'withdrawal',
                            $approvedWithdrawal,
                            'xlink',
                            $reasonText,
                            $xlinkResult['response'] ?? $xlinkResult
                        );
                        $results[] = [
                            'withdrawalId' => $withdrawalId,
                            'success' => false,
                            'message' => 'Rejected due to X-Link failure',
                            'xlink' => $xlinkResult
                        ];
                        $failCount++;
                        continue;
                    }
                }

                if (!$this->isAsyncPayoutGateway($gateway)) {
                    $approvedWithdrawal = $this->completeApprovedWithdrawalWithoutPsp(
                        $approvedWithdrawal,
                        (int)$admin['userId']
                    );
                }

                $txnId = trim((string) ($withdrawal['transactionId'] ?? ''));
                if ($txnId !== '') {
                    $approvedTransactionIds[] = $txnId;
                }

                $results[] = [
                    'withdrawalId' => $withdrawalId,
                    'success' => true,
                    'message' => 'Approved',
                    'ibeepay' => $ibeepayResult,
                    'paymentAsia' => $paymentAsiaResult,
                    'xlink' => $xlinkResult
                ];
                $successCount++;

            } catch (Exception $e) {
                $errorMsg = $e->getMessage();
                $apiMessage = "Bulk approval failed at withdrawal ID {$withdrawalId}: {$errorMsg}";
                $opLog->logWithdrawalBulkApprove($subModule, [], false, $apiMessage);
                Response::error($apiMessage, 400);
            }
        }

        if ($successCount > 0 && $this->hasBalanceSyncWithdrawals($withdrawalIds)) {
            $this->paymentService->dispatchOrdersBalanceSyncTask();
        }

        $opLog->logWithdrawalBulkApprove($subModule, $approvedTransactionIds, true);

        Response::success([
            'results' => $results,
            'summary' => [
                'total' => count($withdrawalIds),
                'success' => $successCount,
                'failed' => $failCount
            ]
        ], "Bulk approval completed: {$successCount} succeeded, {$failCount} failed");
    }

    private function requestIbeepayWithdrawal($withdrawal, $user, $gateway, $supportContent = null) {
        $gatewayConfig = $this->getIbeepayGatewayConfig($gateway);
        $url = trim((string)($gatewayConfig['withdrawal_url'] ?? ''));
        $key = trim((string)($gateway['secretKey'] ?? ''));
        if ($url === '' || $key === '') {
            return null;
        }

        $supportContent = is_array($supportContent) ? $supportContent : $this->parseIbeepaySupportContent($withdrawal);
        $orderId = trim((string)($withdrawal['transactionId'] ?? ''));
        $email = trim((string)$supportContent['email']);
        $name = trim((string)$supportContent['name']);
        $phone = preg_replace('/\s+/', '', trim((string)$supportContent['phone']));
        $contact = $phone;
        $birthday = preg_replace('/[^0-9]/', '', trim((string)$supportContent['dob']));
        $secAcc = 'utrada_' . (string)($user['id'] ?? $user['userId'] ?? '');

        $amount = (string)((int)round((float)($withdrawal['quotedAmount'] ?? $withdrawal['amount'] ?? 0)));

        $postData = [
            'key' => $key,
            'order_id' => $orderId,
            'email' => $email,
            'name' => $name,
            'contact' => $contact,
            'birthday' => $birthday,
            'sec_acc' => $secAcc,
            'amount' => $amount
        ];

        Logger::info('Ibeepay withdrawal post data ready', [
            'withdrawalId' => (int)($withdrawal['id'] ?? 0),
            'orderId' => $orderId,
            'postData' => $postData
        ]);

        $withdrawalId = (int)($withdrawal['id'] ?? 0);
        $startedMs = (int) round(microtime(true) * 1000);
        $logId = null;
        if ($withdrawalId > 0 && $orderId !== '') {
            $logId = $this->requestLogService->beginAttempt([
                'provider' => 'ibeepay',
                'environment' => $this->requestLogService->resolveEnvironment(null, $gateway),
                'transactionType' => 'withdrawal',
                'operation' => 'payout',
                'deliveryMode' => 'server_http',
                'withdrawalId' => $withdrawalId,
                'localOrderId' => $orderId,
                'amount' => $withdrawal['quotedAmount'] ?? $withdrawal['amount'] ?? null,
                'currencyCode' => $withdrawal['currencyCode'] ?? null,
                'requestMethod' => 'POST',
                'endpointPath' => $url,
                'requestPayload' => $postData,
            ]);
            if ($logId) {
                $this->requestLogService->markSent($logId);
            }
        }

        $ch = curl_init($url);
        if ($ch === false) {
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'errorMessage' => 'Failed to initialize curl',
                    'durationMs' => max(0, (int) round(microtime(true) * 1000) - $startedMs),
                ]);
            }
            return ['success' => false, 'error' => 'Failed to initialize curl'];
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: CRM-Ibeepay-Withdrawal/1.0'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $raw = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $durationMs = max(0, (int) round(microtime(true) * 1000) - $startedMs);

        if ($curlErrNo !== 0) {
            if ($logId) {
                $this->requestLogService->completeAttempt(
                    $logId,
                    $this->requestLogService->statusFromCurlErrno($curlErrNo),
                    [
                        'responseHttpStatus' => $httpCode > 0 ? $httpCode : null,
                        'errorCode' => (string) $curlErrNo,
                        'errorMessage' => $curlErr,
                        'durationMs' => $durationMs,
                    ]
                );
            }
            Logger::error('Ibeepay withdrawal request failed', [
                'withdrawalId' => $withdrawal['id'] ?? null,
                'orderId' => $orderId,
                'curlErrNo' => $curlErrNo,
                'curlErr' => $curlErr
            ]);
            return ['success' => false, 'error' => $curlErr, 'httpCode' => $httpCode];
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'responseHttpStatus' => $httpCode,
                    'errorMessage' => 'Invalid JSON response',
                    'responsePayload' => ['raw' => mb_substr((string) $raw, 0, 4000)],
                    'durationMs' => $durationMs,
                ]);
            }
            Logger::error('Ibeepay withdrawal response is invalid JSON', [
                'withdrawalId' => $withdrawal['id'] ?? null,
                'orderId' => $orderId,
                'httpCode' => $httpCode,
                'raw' => $raw
            ]);
            return ['success' => false, 'error' => 'Invalid JSON response', 'httpCode' => $httpCode, 'raw' => $raw];
        }

        $result = (int)($decoded['result'] ?? 0);
        $ok = $httpCode >= 200 && $httpCode < 300 && $result === 1;
        if ($logId) {
            $this->requestLogService->completeAttempt(
                $logId,
                $ok
                    ? PaymentProcessorRequestLogService::STATUS_ACCEPTED
                    : PaymentProcessorRequestLogService::STATUS_FAILED,
                [
                    'responseHttpStatus' => $httpCode,
                    'providerStatus' => isset($decoded['result']) ? (string) $decoded['result'] : null,
                    'responsePayload' => $decoded,
                    'errorMessage' => $ok ? null : ($decoded['reason'] ?? 'Ibeepay withdrawal rejected'),
                    'durationMs' => $durationMs,
                ]
            );
        }
        if (!$ok) {
            Logger::error('Ibeepay withdrawal rejected', [
                'withdrawalId' => $withdrawal['id'] ?? null,
                'orderId' => $orderId,
                'httpCode' => $httpCode,
                'response' => $decoded
            ]);
        }

        return [
            'success' => $ok,
            'httpCode' => $httpCode,
            'result' => $decoded['result'] ?? null,
            'reason' => $decoded['reason'] ?? null
        ];
    }

    private function requestPaymentAsiaWithdrawal($withdrawal, $gateway, $supportContent = null) {
        $url = $this->buildPaymentAsiaPayoutUrl($gateway);
        $secret = trim((string)($gateway['secretKey'] ?? ''));
        if ($url === '' || $secret === '') {
            return null;
        }

        $supportContent = is_array($supportContent) ? $supportContent : $this->resolvePaymentAsiaWithdrawalSupportData($withdrawal);
        $amountDecimalPlaces = $this->resolveGatewayAmountDecimalPlaces($gateway);
        $requestReference = trim((string)($withdrawal['transactionId'] ?? ''));
        $amount = $this->formatRoundedAmount((float)($withdrawal['quotedAmount'] ?? $withdrawal['amount'] ?? 0), $amountDecimalPlaces);
        $beneficiaryPhone = preg_replace('/\s+/', '', trim((string)($supportContent['phone'] ?? '')));
        $beneficiaryCity = trim((string)($supportContent['beneficiary_city'] ?? $supportContent['city'] ?? ''));
        $beneficiaryIdentityCard = trim((string)($supportContent['beneficiary_identity_card'] ?? $supportContent['identity_card'] ?? ''));

        $postData = [
            'request_reference' => $requestReference,
            'beneficiary_name' => $this->buildPaymentAsiaBeneficiaryName($supportContent),
            'beneficiary_first_name' => trim((string)($supportContent['first_name'] ?? '')),
            'beneficiary_last_name' => trim((string)($supportContent['last_name'] ?? '')),
            'bank_name' => trim((string)($supportContent['bank_name'] ?? '')),
            'beneficiary_email' => trim((string)($supportContent['email'] ?? '')),
            'beneficiary_phone' => $beneficiaryPhone,
            'account_number' => trim((string)($supportContent['account_number'] ?? '')),
            'currency' => strtoupper(trim((string)($withdrawal['currencyCode'] ?? ''))),
            'amount' => $amount
        ];

        if ($beneficiaryCity !== '') {
            $postData['beneficiary_city'] = $beneficiaryCity;
        }
        if ($beneficiaryIdentityCard !== '') {
            $postData['beneficiary_identity_card'] = $beneficiaryIdentityCard;
        }

        $datafeedUrl = $this->buildPaymentAsiaWithdrawalDatafeedUrl();
        if ($datafeedUrl !== '') {
            $postData['datafeed_url'] = $datafeedUrl;
        }

        $postData['sign'] = $this->generatePaymentAsiaSign($postData, $secret);

        Logger::error('Payment Asia withdrawal request prepared', [
            'withdrawalId' => (int)($withdrawal['id'] ?? 0),
            'requestReference' => $requestReference,
            'url' => $url,
            'postData' => $postData
        ]);

        $withdrawalId = (int)($withdrawal['id'] ?? 0);
        $startedMs = (int) round(microtime(true) * 1000);
        $logId = null;
        if ($withdrawalId > 0 && $requestReference !== '') {
            $logId = $this->requestLogService->beginAttempt([
                'provider' => 'payment_asia',
                'environment' => $this->requestLogService->resolveEnvironment(null, $gateway),
                'transactionType' => 'withdrawal',
                'operation' => 'payout',
                'deliveryMode' => 'server_http',
                'withdrawalId' => $withdrawalId,
                'localOrderId' => $requestReference,
                'amount' => $withdrawal['quotedAmount'] ?? $withdrawal['amount'] ?? null,
                'currencyCode' => $withdrawal['currencyCode'] ?? null,
                'requestMethod' => 'POST',
                'endpointPath' => $url,
                'requestPayload' => $postData,
            ]);
            if ($logId) {
                $this->requestLogService->markSent($logId);
            }
        }

        $ch = curl_init($url);
        if ($ch === false) {
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'errorMessage' => 'Failed to initialize curl',
                    'durationMs' => max(0, (int) round(microtime(true) * 1000) - $startedMs),
                ]);
            }
            return ['success' => false, 'error' => 'Failed to initialize curl'];
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: CRM-PaymentAsia-Withdrawal/1.0'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $raw = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $durationMs = max(0, (int) round(microtime(true) * 1000) - $startedMs);

        if ($curlErrNo !== 0) {
            if ($logId) {
                $this->requestLogService->completeAttempt(
                    $logId,
                    $this->requestLogService->statusFromCurlErrno($curlErrNo),
                    [
                        'responseHttpStatus' => $httpCode > 0 ? $httpCode : null,
                        'errorCode' => (string) $curlErrNo,
                        'errorMessage' => $curlErr,
                        'durationMs' => $durationMs,
                    ]
                );
            }
            Logger::error('Payment Asia withdrawal request failed', [
                'withdrawalId' => $withdrawal['id'] ?? null,
                'requestReference' => $requestReference,
                'curlErrNo' => $curlErrNo,
                'curlErr' => $curlErr
            ]);
            return ['success' => false, 'error' => $curlErr, 'httpCode' => $httpCode];
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'responseHttpStatus' => $httpCode,
                    'errorMessage' => 'Invalid JSON response',
                    'responsePayload' => ['raw' => mb_substr((string) $raw, 0, 4000)],
                    'durationMs' => $durationMs,
                ]);
            }
            Logger::error('Payment Asia withdrawal response is invalid JSON', [
                'withdrawalId' => $withdrawal['id'] ?? null,
                'requestReference' => $requestReference,
                'httpCode' => $httpCode,
                'raw' => $raw
            ]);
            return ['success' => false, 'error' => 'Invalid JSON response', 'httpCode' => $httpCode, 'raw' => $raw];
        }

        $responseCode = trim((string)($decoded['response']['code'] ?? ''));
        $message = trim((string)($decoded['response']['message'] ?? ''));
        $requestId = trim((string)($decoded['request']['id'] ?? ''));
        $status = trim((string)($decoded['payload']['status'] ?? ''));
        $ok = $httpCode >= 200 && $httpCode < 300 && $responseCode === '200';

        Logger::error('Payment Asia withdrawal response received', [
            'withdrawalId' => (int)($withdrawal['id'] ?? 0),
            'requestReference' => $requestReference,
            'httpCode' => $httpCode,
            'rawResponse' => $raw,
            'decodedResponse' => $decoded,
            'result' => [
                'success' => $ok,
                'code' => $responseCode !== '' ? $responseCode : null,
                'message' => $message !== '' ? $message : null,
                'requestId' => $requestId !== '' ? $requestId : null,
                'status' => $status !== '' ? $status : null
            ]
        ]);

        if ($logId) {
            $this->requestLogService->completeAttempt(
                $logId,
                $ok
                    ? PaymentProcessorRequestLogService::STATUS_ACCEPTED
                    : PaymentProcessorRequestLogService::STATUS_FAILED,
                [
                    'responseHttpStatus' => $httpCode,
                    'providerStatus' => $status !== '' ? $status : ($responseCode !== '' ? $responseCode : null),
                    'providerOrderId' => $requestId !== '' ? $requestId : null,
                    'providerRequestId' => $requestId !== '' ? $requestId : null,
                    'responsePayload' => $decoded,
                    'errorMessage' => $ok ? null : ($message !== '' ? $message : 'Payment Asia withdrawal rejected'),
                    'durationMs' => $durationMs,
                ]
            );
        }

        if ($ok && $requestId !== '') {
            $this->persistWithdrawalGatewayTransactionId((int)($withdrawal['id'] ?? 0), $requestId);
        }

        if (!$ok) {
            Logger::error('Payment Asia withdrawal rejected', [
                'withdrawalId' => $withdrawal['id'] ?? null,
                'requestReference' => $requestReference,
                'httpCode' => $httpCode,
                'response' => $decoded
            ]);
        }

        return [
            'success' => $ok,
            'httpCode' => $httpCode,
            'code' => $responseCode !== '' ? $responseCode : null,
            'message' => $message !== '' ? $message : null,
            'reason' => $message !== '' ? $message : null,
            'requestId' => $requestId !== '' ? $requestId : null,
            'status' => $status !== '' ? $status : null,
            'requestReference' => trim((string)($decoded['payload']['request_reference'] ?? $requestReference))
        ];
    }

    private function hasSupportContent($withdrawal) {
        return trim((string)($withdrawal['supportContent'] ?? '')) !== '';
    }

    private function resolveWithdrawalGatewaySetting($withdrawal, $withSecrets = false) {
        $gatewayId = (int)($withdrawal['gatewaySettingId'] ?? 0);
        if ($gatewayId <= 0) {
            return null;
        }

        $gateway = $withSecrets
            ? $this->gatewayModel->findByIdWithSecrets($gatewayId)
            : $this->gatewayModel->findById($gatewayId);

        return $gateway ?: null;
    }

    private function isAsyncPayoutGateway($gateway): bool {
        if (!$gateway) {
            return false;
        }

        return (($gateway['gatewayKey'] ?? null) === 'ibeepay')
            || $this->isPaymentAsiaGateway($gateway)
            || $this->isVexoraGateway($gateway)
            || $this->isFlashPayGateway($gateway)
            || $this->isCvPayGateway($gateway)
            || $this->isFivePayGateway($gateway)
            || $this->isXLinkGateway($gateway)
            || $this->isCoinsbuyGateway($gateway);
    }

    private function completeApprovedWithdrawalWithoutPsp(array $withdrawal, int $operatorId): array {
        $withdrawalId = (int)($withdrawal['id'] ?? 0);
        if ($withdrawalId <= 0) {
            return $withdrawal;
        }

        if (($withdrawal['status'] ?? '') === 'completed') {
            return $withdrawal;
        }

        $db = Database::getInstance();
        $db->query(
            'CALL spCompleteWithdrawal(:withdrawalId, :completedBy, :transactionHash)',
            [
                'withdrawalId' => $withdrawalId,
                'completedBy' => $operatorId,
                'transactionHash' => $withdrawal['transactionHash'] ?? null,
            ]
        );

        return $this->withdrawalModel->getWithdrawalDetails($withdrawalId) ?: $withdrawal;
    }

    private function resolveIbeepayWithdrawalSupportData($withdrawal) {
        $supportData = $this->parseIbeepaySupportContent($withdrawal);

        $this->validateIbeepaySupportData($supportData);
        return $supportData;
    }

    private function resolvePaymentAsiaWithdrawalSupportData($withdrawal) {
        $supportData = $this->parsePaymentAsiaWithdrawalSupportContent($withdrawal);
        $this->validatePaymentAsiaWithdrawalSupportData($supportData);
        return $supportData;
    }

    private function buildSubmissionSupportSnapshot($submissionId) {
        $snapshot = [];
        $answers = $this->withdrawalAnswerModel->getSubmissionAnswers($submissionId);

        foreach ($answers as $answer) {
            $scope = trim((string)($answer['scope'] ?? ''));
            if ($scope === '') {
                continue;
            }

            $value = $this->withdrawalAnswerModel->getAnswerValue($answer);
            if (is_array($value)) {
                continue;
            }

            $snapshot[$scope] = trim((string)$value);
        }

        return $snapshot;
    }

    private function validateIbeepaySupportData($supportData) {
        $missingFields = [];
        foreach (['name', 'dob', 'email', 'phone'] as $field) {
            if (trim((string)($supportData[$field] ?? '')) === '') {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            Response::validationError([
                'supportData' => [
                    'Ibeepay withdrawal missing required fields: ' . implode(', ', $missingFields)
                ]
            ]);
        }
    }

    private function validatePaymentAsiaWithdrawalSupportData($supportData) {
        $missingFields = [];
        foreach (['bank_name', 'first_name', 'last_name', 'email', 'phone', 'account_number'] as $field) {
            if (trim((string)($supportData[$field] ?? '')) === '') {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            Response::validationError([
                'supportData' => [
                    'Payment Asia withdrawal missing required fields: ' . implode(', ', $missingFields)
                ]
            ]);
        }
    }

    private function parseIbeepaySupportContent($withdrawal) {
        $raw = trim((string)($withdrawal['supportContent'] ?? ''));
        if ($raw === '') {
            throw new InvalidArgumentException('Ibeepay withdrawal requires supportContent');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Ibeepay supportContent must be valid JSON');
        }

        $requiredFields = ['name', 'dob', 'email', 'phone'];
        foreach ($requiredFields as $field) {
            if (trim((string)($decoded[$field] ?? '')) === '') {
                throw new InvalidArgumentException("Ibeepay supportContent missing field: {$field}");
            }
        }

        return $decoded;
    }

    private function parsePaymentAsiaWithdrawalSupportContent($withdrawal) {
        $raw = trim((string)($withdrawal['supportContent'] ?? ''));
        if ($raw === '') {
            throw new InvalidArgumentException('Payment Asia withdrawal requires supportContent');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Payment Asia supportContent must be valid JSON');
        }

        return $decoded;
    }

    private function isPaymentAsiaGateway($gateway) {
        if (!$gateway) {
            return false;
        }

        $gatewayKey = strtolower(trim((string)($gateway['gatewayKey'] ?? '')));
        if (strpos($gatewayKey, 'pa-') === 0) {
            return true;
        }

        $config = $this->getGatewayConfigData($gateway);
        return strtolower(trim((string)($config['providerKey'] ?? ''))) === 'payment_asia';
    }

    private function isCoinsbuyGateway($gateway) {
        if (!$gateway) {
            return false;
        }
        return strtolower(trim((string)($gateway['gatewayKey'] ?? ''))) === 'coinsbuy';
    }

    private function usesPostApprovalWithdrawalProcessor($gateway): bool {
        return (($gateway['gatewayKey'] ?? null) === 'ibeepay')
            || $this->isPaymentAsiaGateway($gateway)
            || $this->isCoinsbuyGateway($gateway)
            || $this->isVexoraGateway($gateway)
            || $this->isFlashPayGateway($gateway)
            || $this->isCvPayGateway($gateway)
            || $this->isFivePayGateway($gateway);
    }

    private function isVexoraGateway($gateway) {
        if (!$gateway) {
            return false;
        }

        $gatewayKey = strtolower(trim((string)($gateway['gatewayKey'] ?? '')));
        if (strpos($gatewayKey, 'vexora') === 0) {
            return true;
        }

        $config = $this->getGatewayConfigData($gateway);
        return strtolower(trim((string)($config['providerKey'] ?? ''))) === 'vexora';
    }

    private function isFlashPayGateway($gateway) {
        if (!$gateway) {
            return false;
        }

        $gatewayKey = strtolower(trim((string)($gateway['gatewayKey'] ?? '')));
        if (strpos($gatewayKey, 'flashpay') === 0) {
            return true;
        }

        $config = $this->getGatewayConfigData($gateway);
        return strtolower(trim((string)($config['providerKey'] ?? ''))) === 'flashpay';
    }

    private function isCvPayGateway($gateway) {
        if (!$gateway) {
            return false;
        }

        $gatewayKey = strtolower(trim((string)($gateway['gatewayKey'] ?? '')));
        if (strpos($gatewayKey, 'cvpay') === 0) {
            return true;
        }

        $config = $this->getGatewayConfigData($gateway);
        return strtolower(trim((string)($config['providerKey'] ?? ''))) === 'cvpay';
    }

    private function resolveFlashPayWithdrawalSupportData($withdrawal) {
        $raw = trim((string)($withdrawal['supportContent'] ?? ''));
        if ($raw === '') {
            throw new InvalidArgumentException('FlashPay withdrawal requires supportContent');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('FlashPay supportContent must be valid JSON');
        }

        $missingFields = [];
        foreach (['first_name', 'last_name', 'bank_code', 'account_number'] as $field) {
            if (trim((string)($decoded[$field] ?? '')) === '') {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            Response::validationError([
                'supportData' => [
                    'FlashPay withdrawal missing required fields: ' . implode(', ', $missingFields)
                ]
            ]);
        }

        return $decoded;
    }

    private function buildFlashPayWithdrawalNotifyUrl(): string {
        $baseUrl = rtrim((string)($this->appConfig['file_base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            return '';
        }

        $baseUrl = preg_replace('#/index\.php$#i', '', $baseUrl);
        return $baseUrl . '/api/callback/flashpay/withdrawal';
    }

    private function requestFlashPayWithdrawal($withdrawal, $gateway, $supportContent = null) {
        $service = new FlashPayService($gateway);
        if (!$service->isConfigured()) {
            return ['success' => false, 'error' => 'FlashPay gateway is not configured (need mchNo, appId, private key, platform public key, base_url)'];
        }

        $supportContent = is_array($supportContent) ? $supportContent : $this->resolveFlashPayWithdrawalSupportData($withdrawal);
        $transactionId = trim((string)($withdrawal['transactionId'] ?? ''));
        if ($transactionId === '') {
            return ['success' => false, 'error' => 'Withdrawal transactionId is missing'];
        }

        $notifyUrl = $this->buildFlashPayWithdrawalNotifyUrl();
        if ($notifyUrl === '') {
            return ['success' => false, 'error' => 'FlashPay withdrawal notifyUrl is not configured (file_base_url is empty)'];
        }

        $firstName = trim((string)($supportContent['first_name'] ?? ''));
        $lastName = trim((string)($supportContent['last_name'] ?? ''));
        $accountName = trim(implode(' ', array_filter([$firstName, $lastName], static function ($value) {
            return $value !== '';
        })));
        $bankCode = trim((string)($supportContent['bank_code'] ?? ''));
        $accountNo = trim((string)($supportContent['account_number'] ?? ''));
        $mchOrderNo = FlashPayService::buildMchOrderNo($transactionId);
        $amount = FlashPayService::toFlashPayAmountCents($withdrawal['quotedAmount'] ?? $withdrawal['amount'] ?? 0);

        $postData = [
            'mchOrderNo' => $mchOrderNo,
            'ifCode' => $service->getIfCode(),
            'entryType' => $service->getEntryType(),
            'amount' => $amount,
            'currency' => $service->getCurrency(),
            'accountNo' => $accountNo,
            'accountName' => $accountName,
            'bankCode' => $bankCode,
            'transferDesc' => 'Withdrawal ' . $mchOrderNo,
            'notifyUrl' => $notifyUrl,
            'clientIp' => ClientIp::getClientIp(),
            'channelExtra' => FlashPayService::encodeChannelExtra([
                'bankCode' => $bankCode,
                'firstName' => $firstName,
                'lastName' => $lastName,
            ]),
        ];

        $withdrawalId = (int)($withdrawal['id'] ?? 0);
        $startedMs = (int) round(microtime(true) * 1000);
        $logId = null;
        if ($withdrawalId > 0) {
            $logId = $this->requestLogService->beginAttempt([
                'provider' => 'flashpay',
                'environment' => $this->requestLogService->resolveEnvironment(null, $gateway),
                'transactionType' => 'withdrawal',
                'operation' => 'payout',
                'deliveryMode' => 'server_http',
                'withdrawalId' => $withdrawalId,
                'localOrderId' => $transactionId,
                'amount' => $withdrawal['quotedAmount'] ?? $withdrawal['amount'] ?? null,
                'currencyCode' => $withdrawal['currencyCode'] ?? null,
                'requestMethod' => 'POST',
                'endpointPath' => '/api/transferOrder',
                'requestPayload' => $postData,
            ]);
            if ($logId) {
                $this->requestLogService->markSent($logId);
            }
        }

        try {
            $response = $service->createTransferOrder($postData);
        } catch (Throwable $e) {
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'errorMessage' => $e->getMessage(),
                    'durationMs' => max(0, (int) round(microtime(true) * 1000) - $startedMs),
                ]);
            }
            Logger::error('FlashPay transferOrder request failed', [
                'withdrawalId' => (int)($withdrawal['id'] ?? 0),
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $durationMs = max(0, (int) round(microtime(true) * 1000) - $startedMs);
        $transferId = trim((string)($response['data']['transferId'] ?? ''));
        $providerState = $response['data']['state'] ?? null;
        $mappedState = FlashPayService::mapPayOutState($providerState);

        if ($transferId !== '' && $withdrawalId > 0) {
            try {
                $this->withdrawalModel->update($withdrawalId, [
                    'gatewayTransactionId' => $transferId,
                    'gatewayResponse' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            } catch (Throwable $e) {
                Logger::error('Failed to persist FlashPay withdrawal response: ' . $e->getMessage(), [
                    'withdrawalId' => $withdrawalId,
                ]);
            }
        }

        if (!FlashPayService::isRequestAccepted($response) || $mappedState === 'failed') {
            $message = trim((string)($response['msg'] ?? $response['data']['errMsg'] ?? 'FlashPay transfer rejected'));
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'providerOrderId' => $transferId !== '' ? $transferId : null,
                    'providerStatus' => $providerState !== null ? (string)$providerState : null,
                    'responsePayload' => $response,
                    'errorMessage' => $message,
                    'durationMs' => $durationMs,
                ]);
            }
            return [
                'success' => false,
                'error' => $message,
                'reason' => $message,
                'response' => $response,
            ];
        }

        if ($logId) {
            $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_ACCEPTED, [
                'providerOrderId' => $transferId !== '' ? $transferId : null,
                'providerStatus' => $providerState !== null ? (string)$providerState : null,
                'responsePayload' => $response,
                'durationMs' => $durationMs,
            ]);
        }

        return [
            'success' => true,
            'status' => $mappedState,
            'transferId' => $transferId,
            'response' => $response,
        ];
    }

    private function resolveCvPayWithdrawalSupportData($withdrawal) {
        $raw = trim((string)($withdrawal['supportContent'] ?? ''));
        if ($raw === '') {
            throw new InvalidArgumentException('CVPay withdrawal requires supportContent');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('CVPay supportContent must be valid JSON');
        }

        $missingFields = [];
        foreach (['way_code', 'bank_name', 'account_name', 'account_number'] as $field) {
            if (trim((string)($decoded[$field] ?? '')) === '') {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            Response::validationError([
                'supportData' => [
                    'CVPay withdrawal missing required fields: ' . implode(', ', $missingFields)
                ]
            ]);
        }

        $wayCode = strtoupper(trim((string)$decoded['way_code']));
        if (!in_array($wayCode, ['BANKACCOUNT', 'ATMCARD'], true)) {
            Response::validationError([
                'supportData' => ['CVPay way_code must be BANKACCOUNT or ATMCARD']
            ]);
        }
        $decoded['way_code'] = $wayCode;

        return $decoded;
    }

    private function buildCvPayWithdrawalNotifyUrl(): string {
        $baseUrl = rtrim((string)($this->appConfig['file_base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            return '';
        }

        $baseUrl = preg_replace('#/index\.php$#i', '', $baseUrl);
        return $baseUrl . '/api/callback/cvpay/withdrawal';
    }

    private function requestCvPayWithdrawal($withdrawal, $gateway, $supportContent = null) {
        $service = new CvPayService($gateway);
        if (!$service->isConfigured()) {
            return ['success' => false, 'error' => 'CVPay gateway is not configured (need mchNo, appId, appKey, base_url)'];
        }

        $supportContent = is_array($supportContent) ? $supportContent : $this->resolveCvPayWithdrawalSupportData($withdrawal);
        $transactionId = trim((string)($withdrawal['transactionId'] ?? ''));
        if ($transactionId === '') {
            return ['success' => false, 'error' => 'Withdrawal transactionId is missing'];
        }

        $notifyUrl = $this->buildCvPayWithdrawalNotifyUrl();
        if ($notifyUrl === '') {
            return ['success' => false, 'error' => 'CVPay withdrawal notifyUrl is not configured (file_base_url is empty)'];
        }

        $wayCode = strtoupper(trim((string)($supportContent['way_code'] ?? '')));
        $bankName = trim((string)($supportContent['bank_name'] ?? ''));
        $accountName = trim((string)($supportContent['account_name'] ?? ''));
        $accountNo = trim((string)($supportContent['account_number'] ?? ''));
        $mchOrderNo = CvPayService::buildMchOrderNo($transactionId);
        $amount = CvPayService::toCvPayAmount($withdrawal['quotedAmount'] ?? $withdrawal['amount'] ?? 0);
        $buyerCode = trim((string)($withdrawal['userId'] ?? $withdrawal['clientId'] ?? ''));
        if ($buyerCode === '') {
            $buyerCode = (string)((int)($withdrawal['id'] ?? 0));
        }

        $postData = [
            'mchOrderNo' => $mchOrderNo,
            'wayCode' => $wayCode,
            'amount' => $amount,
            'currency' => $service->getCurrency(),
            'bankName' => $bankName,
            'accountNo' => $accountNo,
            'accountName' => $accountName,
            'notifyUrl' => $notifyUrl,
            'buyerCode' => $buyerCode,
        ];

        $withdrawalId = (int)($withdrawal['id'] ?? 0);
        $startedMs = (int) round(microtime(true) * 1000);
        $logId = null;
        if ($withdrawalId > 0) {
            $logId = $this->requestLogService->beginAttempt([
                'provider' => 'cvpay',
                'environment' => $this->requestLogService->resolveEnvironment(null, $gateway),
                'transactionType' => 'withdrawal',
                'operation' => 'payout',
                'deliveryMode' => 'server_http',
                'withdrawalId' => $withdrawalId,
                'localOrderId' => $transactionId,
                'amount' => $withdrawal['quotedAmount'] ?? $withdrawal['amount'] ?? null,
                'currencyCode' => $withdrawal['currencyCode'] ?? null,
                'requestMethod' => 'POST',
                'endpointPath' => '/api/transfer/create',
                'requestPayload' => $postData,
            ]);
            if ($logId) {
                $this->requestLogService->markSent($logId);
            }
        }

        try {
            $response = $service->createTransfer($postData);
        } catch (Throwable $e) {
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'errorMessage' => $e->getMessage(),
                    'durationMs' => max(0, (int) round(microtime(true) * 1000) - $startedMs),
                ]);
            }
            Logger::error('CVPay transfer/create request failed', [
                'withdrawalId' => (int)($withdrawal['id'] ?? 0),
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $durationMs = max(0, (int) round(microtime(true) * 1000) - $startedMs);
        $transferId = trim((string)($response['data']['transferId'] ?? ''));
        $providerState = $response['data']['state'] ?? null;
        $mappedState = CvPayService::mapPayOutState($providerState);

        if ($transferId !== '' && $withdrawalId > 0) {
            try {
                $this->withdrawalModel->update($withdrawalId, [
                    'gatewayTransactionId' => $transferId,
                    'gatewayResponse' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            } catch (Throwable $e) {
                Logger::error('Failed to persist CVPay withdrawal response: ' . $e->getMessage(), [
                    'withdrawalId' => $withdrawalId,
                ]);
            }
        }

        if (!CvPayService::isRequestAccepted($response) || $mappedState === 'failed') {
            $message = trim((string)($response['msg'] ?? $response['data']['errMsg'] ?? 'CVPay transfer rejected'));
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'providerOrderId' => $transferId !== '' ? $transferId : null,
                    'providerStatus' => $providerState !== null ? (string)$providerState : null,
                    'responsePayload' => $response,
                    'errorMessage' => $message,
                    'durationMs' => $durationMs,
                ]);
            }
            return [
                'success' => false,
                'error' => $message,
                'reason' => $message,
                'response' => $response,
            ];
        }

        if ($logId) {
            $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_ACCEPTED, [
                'providerOrderId' => $transferId !== '' ? $transferId : null,
                'providerStatus' => $providerState !== null ? (string)$providerState : null,
                'responsePayload' => $response,
                'durationMs' => $durationMs,
            ]);
        }

        return [
            'success' => true,
            'status' => $mappedState,
            'transferId' => $transferId,
            'response' => $response,
        ];
    }

    private function isFivePayGateway($gateway) {
        if (!$gateway) {
            return false;
        }

        if (FivePayService::isGatewayKey($gateway['gatewayKey'] ?? '')) {
            return true;
        }

        $config = $this->getGatewayConfigData($gateway);
        return FivePayService::isProviderKey($config['providerKey'] ?? '');
    }

    private function isXLinkGateway($gateway): bool {
        if (!$gateway) {
            return false;
        }

        if (strtolower(trim((string)($gateway['gatewayKey'] ?? ''))) === XLinkService::GATEWAY_KEY) {
            return true;
        }

        $config = $this->getGatewayConfigData($gateway);
        return strtolower(trim((string)($config['providerKey'] ?? ''))) === XLinkService::PROVIDER_KEY;
    }

    private function resolveXLinkWithdrawalSupportData($withdrawal): array {
        $raw = trim((string)($withdrawal['supportContent'] ?? ''));
        $decoded = $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            Response::validationError([
                'supportData' => ['X-Link withdrawal supportContent must be valid JSON']
            ]);
        }

        $requiredFields = [
            'account_number',
            'account_name',
            'bank_province',
            'bank_city',
            'bank_code',
        ];
        $missingFields = [];
        $supportData = [];
        foreach ($requiredFields as $field) {
            $value = trim((string)($decoded[$field] ?? ''));
            if ($value === '') {
                $missingFields[] = $field;
            }
            $supportData[$field] = $value;
        }

        if (!empty($missingFields)) {
            Response::validationError([
                'supportData' => [
                    'X-Link withdrawal missing required fields: ' . implode(', ', $missingFields)
                ]
            ]);
        }

        foreach (['bank_branch', 'customer_name', 'customer_lastname'] as $field) {
            $value = trim((string)($decoded[$field] ?? ''));
            if ($value !== '') {
                $supportData[$field] = $value;
            }
        }

        return $supportData;
    }

    private function requestXLinkWithdrawal($withdrawal, $gateway, array $supportData): array {
        $service = new XLinkService($gateway);
        if (!$service->isConfigured()) {
            return [
                'success' => false,
                'ambiguous' => false,
                'error' => 'X-Link gateway is not fully configured',
            ];
        }

        $withdrawalId = (int)($withdrawal['id'] ?? 0);
        $transactionId = trim((string)($withdrawal['transactionId'] ?? ''));
        $userId = (int)($withdrawal['userId'] ?? 0);
        if ($withdrawalId <= 0 || $transactionId === '' || $userId <= 0) {
            return [
                'success' => false,
                'ambiguous' => false,
                'error' => 'X-Link withdrawal is missing its id, transactionId, or userId',
            ];
        }

        try {
            $amount = XLinkService::normalizeKrwAmount(
                $withdrawal['quotedAmount'] ?? $withdrawal['amount'] ?? null
            );
        } catch (Throwable $e) {
            return [
                'success' => false,
                'ambiguous' => false,
                'error' => $e->getMessage(),
            ];
        }

        $environment = $this->requestLogService->resolveEnvironment(null, $gateway);
        $baseLog = [
            'provider' => XLinkService::PROVIDER_KEY,
            'environment' => $environment,
            'transactionType' => 'withdrawal',
            'deliveryMode' => 'server_http',
            'withdrawalId' => $withdrawalId,
            'localOrderId' => $transactionId,
            'amount' => $amount,
            'currencyCode' => XLinkService::DEFAULT_CURRENCY,
        ];

        $discoveryPayload = [
            'shop_id' => $service->getShopId(),
            'operation_type' => 'PAYOUT',
            'amount' => $amount,
            'currency' => XLinkService::DEFAULT_CURRENCY,
        ];
        $discoveryLogId = $this->requestLogService->beginAttempt(array_merge($baseLog, [
            'operation' => 'payment_method_types',
            'requestMethod' => 'GET',
            'endpointPath' => '/payment-method-types',
            'requestPayload' => $discoveryPayload,
        ]));
        $discoveryStartedMs = (int)round(microtime(true) * 1000);
        if ($discoveryLogId) {
            $this->requestLogService->markSent($discoveryLogId);
        }

        try {
            $method = $service->getPaymentMethodType('PAYOUT', $amount, XLinkService::DEFAULT_CURRENCY);
            $methodId = trim((string)($method['id'] ?? $method['payment_method_type_id'] ?? ''));
            if ($methodId === '') {
                throw new RuntimeException('X-Link payment-method response is missing id');
            }
            if ($discoveryLogId) {
                $this->requestLogService->completeAttempt($discoveryLogId, PaymentProcessorRequestLogService::STATUS_ACCEPTED, [
                    'providerRequestId' => $methodId,
                    'responsePayload' => XLinkService::sanitizeForLog($method),
                    'durationMs' => max(0, (int)round(microtime(true) * 1000) - $discoveryStartedMs),
                ]);
            }
        } catch (Throwable $e) {
            return $this->finishXLinkWithdrawalFailure($discoveryLogId, $discoveryStartedMs, $e);
        }

        $sessionPayload = [
            'payment_method_type_id' => $methodId,
            'operation_type' => 'PAYOUT',
            'shop_id' => $service->getShopId(),
            'operation_number' => $transactionId,
            'payer_id' => 'utrada-client-' . $userId,
            'amount' => $amount,
            'currency' => XLinkService::DEFAULT_CURRENCY,
            'callback_url' => $service->getCallbackUrl(),
            'metadata' => $supportData,
        ];
        $sessionLogId = $this->requestLogService->beginAttempt(array_merge($baseLog, [
            'operation' => 'initialize_session',
            'requestMethod' => 'POST',
            'endpointPath' => '/sessions',
            'requestPayload' => $sessionPayload,
        ]));
        $sessionStartedMs = (int)round(microtime(true) * 1000);
        if ($sessionLogId) {
            $this->requestLogService->markSent($sessionLogId);
        }

        try {
            $sessionResponse = $service->initializeSession($sessionPayload);
            $sessionData = is_array($sessionResponse['data'] ?? null) ? $sessionResponse['data'] : $sessionResponse;
            $sessionId = trim((string)($sessionData['session_id'] ?? ''));
            $sessionStatus = XLinkService::mapOperationStatus($sessionData['status'] ?? '');
            if (in_array($sessionStatus, ['failed', 'cancelled', 'refunded'], true)) {
                throw new XLinkApiException(
                    'X-Link rejected the payout session with status ' . strtoupper((string)$sessionData['status']),
                    422,
                    XLinkService::sanitizeForLog($sessionResponse),
                    false
                );
            }
            if ($sessionId === '') {
                throw new XLinkApiException(
                    'X-Link session response is missing session_id',
                    200,
                    XLinkService::sanitizeForLog($sessionResponse),
                    true
                );
            }
            $this->persistXLinkWithdrawalReference(
                $withdrawalId,
                $sessionId,
                $sessionResponse,
                'session'
            );
            if ($sessionLogId) {
                $this->requestLogService->completeAttempt($sessionLogId, PaymentProcessorRequestLogService::STATUS_ACCEPTED, [
                    'providerOrderId' => $sessionId,
                    'responsePayload' => XLinkService::sanitizeForLog($sessionResponse),
                    'durationMs' => max(0, (int)round(microtime(true) * 1000) - $sessionStartedMs),
                ]);
            }
        } catch (Throwable $e) {
            return $this->finishXLinkWithdrawalFailure(
                $sessionLogId,
                $sessionStartedMs,
                $e,
                !empty($sessionId) ? $sessionId : ''
            );
        }

        if ($service->autoCreatesPayoutOperation()) {
            $operationId = trim((string)($sessionData['operation_id'] ?? ''));
            if ($operationId !== '') {
                $this->persistXLinkWithdrawalReference(
                    $withdrawalId,
                    $operationId,
                    $sessionResponse,
                    'operation'
                );
            }
            $sessionStatusRaw = strtolower(trim((string)($sessionData['status'] ?? '')));
            return [
                'success' => true,
                'ambiguous' => false,
                'sessionId' => $sessionId,
                'operationId' => $operationId !== '' ? $operationId : null,
                'status' => $sessionStatusRaw !== '' ? $sessionStatusRaw : 'pending',
                'response' => XLinkService::sanitizeForLog($sessionResponse),
            ];
        }

        $payerRequisites = XLinkService::buildPayerRequisites($sessionData, $supportData);
        $operationPayload = [
            'session_id' => $sessionId,
            'payer_requisites' => $payerRequisites,
        ];
        $operationLogId = $this->requestLogService->beginAttempt(array_merge($baseLog, [
            'operation' => 'create_operation',
            'requestMethod' => 'POST',
            'endpointPath' => '/operations',
            'providerOrderId' => $sessionId,
            'requestPayload' => $operationPayload,
        ]));
        $operationStartedMs = (int)round(microtime(true) * 1000);
        if ($operationLogId) {
            $this->requestLogService->markSent($operationLogId);
        }

        try {
            $operationResponse = $service->createOperation($sessionId, $payerRequisites);
            $operationData = is_array($operationResponse['data'] ?? null) ? $operationResponse['data'] : $operationResponse;
            $operationId = trim((string)($operationData['operation_id'] ?? ''));
            $operationNumber = trim((string)($operationData['operation_number'] ?? ''));
            $operationStatus = strtoupper(trim((string)($operationData['status'] ?? '')));
            $mappedOperationStatus = XLinkService::mapOperationStatus($operationStatus);
            $isDefinitiveTerminal = in_array($mappedOperationStatus, ['failed', 'cancelled', 'refunded'], true);
            if ($operationId !== '') {
                try {
                    $this->persistXLinkWithdrawalReference(
                        $withdrawalId,
                        $operationId,
                        $operationResponse,
                        'operation'
                    );
                } catch (XLinkApiException $e) {
                    if (!$isDefinitiveTerminal) {
                        throw $e;
                    }
                    Logger::error('Failed to persist definitive X-Link payout operation reference', [
                        'withdrawalId' => $withdrawalId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            if ($isDefinitiveTerminal) {
                throw new XLinkApiException(
                    'X-Link rejected the payout operation with status ' . $operationStatus,
                    422,
                    XLinkService::sanitizeForLog($operationResponse),
                    false
                );
            }
            if ($operationNumber === ''
                || $operationNumber !== $transactionId
                || $mappedOperationStatus === 'unknown'
            ) {
                throw new XLinkApiException(
                    'X-Link operation response is malformed or belongs to another order',
                    200,
                    XLinkService::sanitizeForLog($operationResponse),
                    true
                );
            }
            if ($operationLogId) {
                $this->requestLogService->completeAttempt($operationLogId, PaymentProcessorRequestLogService::STATUS_ACCEPTED, [
                    'providerOrderId' => $operationId !== '' ? $operationId : $sessionId,
                    'responsePayload' => XLinkService::sanitizeForLog($operationResponse),
                    'durationMs' => max(0, (int)round(microtime(true) * 1000) - $operationStartedMs),
                ]);
            }
        } catch (Throwable $e) {
            return $this->finishXLinkWithdrawalFailure(
                $operationLogId,
                $operationStartedMs,
                $e,
                !empty($operationId) ? $operationId : $sessionId
            );
        }

        return [
            'success' => true,
            'ambiguous' => false,
            'sessionId' => $sessionId,
            'operationId' => $operationId !== '' ? $operationId : null,
            'status' => strtolower($operationStatus),
            'response' => XLinkService::sanitizeForLog($operationResponse),
        ];
    }

    private function finishXLinkWithdrawalFailure($logId, int $startedMs, Throwable $error, string $providerOrderId = ''): array {
        $ambiguous = $error instanceof XLinkApiException && $error->isAmbiguous();
        $response = $error instanceof XLinkApiException
            ? XLinkService::sanitizeForLog($error->getResponse())
            : [];

        if ($logId) {
            $this->requestLogService->completeAttempt(
                $logId,
                $ambiguous
                    ? PaymentProcessorRequestLogService::STATUS_UNKNOWN
                    : PaymentProcessorRequestLogService::STATUS_FAILED,
                [
                    'providerOrderId' => $providerOrderId !== '' ? $providerOrderId : null,
                    'responsePayload' => $response,
                    'errorMessage' => $error->getMessage(),
                    'durationMs' => max(0, (int)round(microtime(true) * 1000) - $startedMs),
                ]
            );
        }

        Logger::error('X-Link withdrawal request failed', [
            'ambiguous' => $ambiguous,
            'error' => $error->getMessage(),
        ]);

        return [
            'success' => false,
            'ambiguous' => $ambiguous,
            'error' => $error->getMessage(),
            'response' => $response,
        ];
    }

    private function persistXLinkWithdrawalReference(
        int $withdrawalId,
        string $providerReference,
        array $providerResponse,
        string $stage
    ): void {
        try {
            $updated = $this->withdrawalModel->update($withdrawalId, [
                'gatewayTransactionId' => $providerReference,
            ]);
            if (!$updated) {
                $freshWithdrawal = $this->withdrawalModel->findById($withdrawalId);
                if (!$freshWithdrawal
                    || trim((string)($freshWithdrawal['gatewayTransactionId'] ?? '')) !== $providerReference
                ) {
                    throw new RuntimeException('Withdrawal update returned false');
                }
            }
        } catch (Throwable $e) {
            throw new XLinkApiException(
                'Failed to persist the accepted X-Link payout ' . $stage,
                200,
                XLinkService::sanitizeForLog($providerResponse),
                true,
                $e
            );
        }
    }

    private function resolveFivePayWithdrawalSupportData($withdrawal) {
        $raw = trim((string)($withdrawal['supportContent'] ?? ''));
        if ($raw === '') {
            throw new InvalidArgumentException('5Pay withdrawal requires supportContent');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('5Pay supportContent must be valid JSON');
        }

        $missingFields = [];
        $accountName = trim((string)($decoded['account_name'] ?? $decoded['beneficiary_name'] ?? ''));
        $accountNumber = trim((string)($decoded['account_number'] ?? ''));
        $bankCode = trim((string)($decoded['bank_code'] ?? ''));
        $phone = trim((string)($decoded['phone'] ?? $decoded['phone_number'] ?? ''));
        $bankName = FivePayService::resolvePayoutBankName($bankCode);

        if ($bankCode === '' || $bankName === '') {
            $missingFields[] = 'bank_code';
        }
        if ($accountName === '') {
            $missingFields[] = 'account_name';
        }
        if ($accountNumber === '') {
            $missingFields[] = 'account_number';
        }
        if ($bankCode === FivePayService::PAYOUT_BANK_FPS && $phone === '') {
            $missingFields[] = 'phone';
        }

        if (!empty($missingFields)) {
            Response::validationError([
                'supportData' => [
                    '5Pay withdrawal missing required fields: ' . implode(', ', $missingFields)
                ]
            ]);
        }

        return [
            'bank_code' => $bankCode,
            'bank_name' => $bankName,
            'account_name' => $accountName,
            'account_number' => $accountNumber,
            'phone' => $phone,
        ];
    }

    private function buildFivePayWithdrawalNotifyUrl(): string {
        $baseUrl = rtrim((string)($this->appConfig['file_base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            return '';
        }

        $baseUrl = preg_replace('#/index\.php$#i', '', $baseUrl);
        return $baseUrl . '/api/callback/5pay/withdrawal';
    }

    private function requestFivePayWithdrawal($withdrawal, $gateway, $supportContent = null) {
        $service = new FivePayService($gateway);
        if (!$service->isConfigured()) {
            return ['success' => false, 'error' => '5Pay gateway is not configured (need merchantId, private key, platform public key, base_url)'];
        }

        $supportContent = is_array($supportContent) ? $supportContent : $this->resolveFivePayWithdrawalSupportData($withdrawal);
        $transactionId = trim((string)($withdrawal['transactionId'] ?? ''));
        if ($transactionId === '') {
            return ['success' => false, 'error' => 'Withdrawal transactionId is missing'];
        }

        $notifyUrl = $this->buildFivePayWithdrawalNotifyUrl();
        if ($notifyUrl === '') {
            return ['success' => false, 'error' => '5Pay withdrawal notifyUrl is not configured (file_base_url is empty)'];
        }

        $bankCode = trim((string)($supportContent['bank_code'] ?? ''));
        $bankName = trim((string)($supportContent['bank_name'] ?? FivePayService::resolvePayoutBankName($bankCode)));
        $beneficiaryName = trim((string)($supportContent['account_name'] ?? $supportContent['beneficiary_name'] ?? ''));
        $accountNumber = trim((string)($supportContent['account_number'] ?? ''));
        $phoneNumber = trim((string)($supportContent['phone'] ?? $supportContent['phone_number'] ?? ''));
        $merchantOrderNo = FivePayService::buildMerchantOrderNo($transactionId);
        if ($bankName === '') {
            return ['success' => false, 'error' => 'Invalid 5Pay beneficiary bank'];
        }

        $postData = [
            'MerchantId' => $service->getMerchantId(),
            'MerchantOrderNo' => $merchantOrderNo,
            'NotifyUrl' => $notifyUrl,
            'TimeStamp' => FivePayService::utcTimestamp(),
            'Wallet' => FivePayService::WALLET_F2F,
            'Token' => FivePayService::TOKEN_HKD,
            'WithdrawalAmount' => FivePayService::formatAmount($withdrawal['quotedAmount'] ?? $withdrawal['amount'] ?? 0),
            'ByReceivableAmount' => 'false',
            'BeneficiaryAccountNumber' => $accountNumber,
            'BeneficiaryName' => $beneficiaryName,
            'BeneficiaryBank' => $bankName,
            'BeneficiaryBankCode' => $bankCode,
        ];
        if ($bankCode === FivePayService::PAYOUT_BANK_FPS && $phoneNumber !== '') {
            $postData['BeneficiaryPhoneNumber'] = $phoneNumber;
        }

        $withdrawalId = (int)($withdrawal['id'] ?? 0);
        $startedMs = (int) round(microtime(true) * 1000);
        $logId = null;
        if ($withdrawalId > 0) {
            $logId = $this->requestLogService->beginAttempt([
                'provider' => '5pay',
                'environment' => $this->requestLogService->resolveEnvironment(null, $gateway),
                'transactionType' => 'withdrawal',
                'operation' => 'payout',
                'deliveryMode' => 'server_http',
                'withdrawalId' => $withdrawalId,
                'localOrderId' => $transactionId,
                'amount' => $withdrawal['quotedAmount'] ?? $withdrawal['amount'] ?? null,
                'currencyCode' => $withdrawal['currencyCode'] ?? null,
                'requestMethod' => 'POST',
                'endpointPath' => FivePayService::PAYOUT_PATH,
                'requestPayload' => $postData,
            ]);
            if ($logId) {
                $this->requestLogService->markSent($logId);
            }
        }

        try {
            $response = $service->submitWithdrawal($postData);
        } catch (Throwable $e) {
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'errorMessage' => $e->getMessage(),
                    'durationMs' => max(0, (int) round(microtime(true) * 1000) - $startedMs),
                ]);
            }
            Logger::error('5Pay SubmitWithdrawal request failed', [
                'withdrawalId' => (int)($withdrawal['id'] ?? 0),
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $durationMs = max(0, (int) round(microtime(true) * 1000) - $startedMs);
        $payoutId = FivePayService::extractPayoutId($response);

        if ($payoutId !== '' && $withdrawalId > 0) {
            try {
                $this->withdrawalModel->update($withdrawalId, [
                    'gatewayTransactionId' => $payoutId,
                    'gatewayResponse' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            } catch (Throwable $e) {
                Logger::error('Failed to persist 5Pay withdrawal response: ' . $e->getMessage(), [
                    'withdrawalId' => $withdrawalId,
                ]);
            }
        } elseif ($withdrawalId > 0) {
            try {
                $this->withdrawalModel->update($withdrawalId, [
                    'gatewayResponse' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            } catch (Throwable $e) {
                Logger::error('Failed to persist 5Pay withdrawal response: ' . $e->getMessage(), [
                    'withdrawalId' => $withdrawalId,
                ]);
            }
        }

        if (!FivePayService::isPayoutAccepted($response)) {
            $message = FivePayService::payoutErrorMessage($response);
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'providerOrderId' => $payoutId !== '' ? $payoutId : null,
                    'responsePayload' => $response,
                    'errorMessage' => $message,
                    'durationMs' => $durationMs,
                ]);
            }
            return [
                'success' => false,
                'error' => $message,
                'reason' => $message,
                'response' => $response,
            ];
        }

        if ($logId) {
            $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_ACCEPTED, [
                'providerOrderId' => $payoutId !== '' ? $payoutId : null,
                'responsePayload' => $response,
                'durationMs' => $durationMs,
            ]);
        }

        return [
            'success' => true,
            'status' => 'processing',
            'payoutId' => $payoutId,
            'response' => $response,
        ];
    }

    private function resolveVexoraWithdrawalSupportData($withdrawal) {
        $raw = trim((string)($withdrawal['supportContent'] ?? ''));
        if ($raw === '') {
            throw new InvalidArgumentException('Vexora withdrawal requires supportContent');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Vexora supportContent must be valid JSON');
        }

        $missingFields = [];
        foreach (['first_name', 'last_name', 'email', 'phone', 'bank_code', 'account_number'] as $field) {
            if (trim((string)($decoded[$field] ?? '')) === '') {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            Response::validationError([
                'supportData' => [
                    'Vexora withdrawal missing required fields: ' . implode(', ', $missingFields)
                ]
            ]);
        }

        return $decoded;
    }

    private function buildVexoraWithdrawalNotifyUrl(): string {
        $baseUrl = rtrim((string)($this->appConfig['file_base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            return '';
        }

        $baseUrl = preg_replace('#/index\.php$#i', '', $baseUrl);
        return $baseUrl . '/index.php?path=api/callback/vexora/withdrawal';
    }

    /**
     * Call Vexora disbursement API.
     * Returns ['success' => bool, ...]; on failure approve() rejects + rolls back.
     */
    private function requestVexoraWithdrawal($withdrawal, $gateway, $supportContent = null) {
        $service = new VexoraService($gateway);
        $isCambodia = $service->isCambodia();
        if (!$service->isConfigured()) {
            $hint = $isCambodia
                ? 'need merchantNo, secret, and base_url'
                : 'need merchantNo, secret, base_url, and subMerchantNo';
            return ['success' => false, 'error' => 'Vexora gateway is not configured (' . $hint . ')'];
        }

        $supportContent = is_array($supportContent) ? $supportContent : $this->resolveVexoraWithdrawalSupportData($withdrawal);
        $transactionId = trim((string)($withdrawal['transactionId'] ?? ''));
        if ($transactionId === '') {
            return ['success' => false, 'error' => 'Withdrawal transactionId is missing'];
        }

        $notifyUrl = $this->buildVexoraWithdrawalNotifyUrl();
        if ($notifyUrl === '') {
            return ['success' => false, 'error' => 'Vexora withdrawal notifyUrl is not configured (file_base_url is empty)'];
        }

        $tradeNo = VexoraService::buildTradeNo($transactionId);
        $firstName = trim((string)($supportContent['first_name'] ?? ''));
        $lastName = trim((string)($supportContent['last_name'] ?? ''));
        $email = trim((string)($supportContent['email'] ?? ''));
        $bankCode = trim((string)($supportContent['bank_code'] ?? ''));
        $accountNumber = trim((string)($supportContent['account_number'] ?? ''));

        if ($isCambodia) {
            $mobile = VexoraService::normalizeCambodiaMobile($supportContent['phone'] ?? '');
            if (!VexoraService::isValidCambodiaMobile($mobile)) {
                return ['success' => false, 'error' => 'Use 8 digits with no country code, or 9 digits starting with 0 (e.g. 12345678 or 012345678)'];
            }

            $postData = [
                'tradeNo' => $tradeNo,
                'amount' => $service->formatAmount($withdrawal['quotedAmount'] ?? $withdrawal['amount'] ?? 0),
                'userId' => (string)(int)($withdrawal['userId'] ?? 0),
                'firstname' => $firstName,
                'lastname' => $lastName,
                'mobile' => $mobile,
                'email' => $email,
                'channelCode' => 'BAKONG',
                'wayCode' => 'BAKONG',
                'bankCode' => $bankCode,
                'accountNo' => $accountNumber,
                'notifyUrl' => $notifyUrl,
                'remark' => 'payout_' . $tradeNo,
            ];
        } else {
            $customerName = trim(implode(' ', array_filter([
                $firstName,
                $lastName
            ], static function ($value) {
                return $value !== '';
            })));

            $mobile = VexoraService::normalizeKoreanMobile($supportContent['phone'] ?? '');

            $postData = [
                'tradeNo' => $tradeNo,
                'subMerchantNo' => $service->getSubMerchantNo(),
                'amount' => $service->formatAmount($withdrawal['quotedAmount'] ?? $withdrawal['amount'] ?? 0),
                'bankCode' => $bankCode,
                'bankAccountNo' => $accountNumber,
                'customerName' => $customerName,
                'mobile' => $mobile,
                'email' => $email,
                'notifyUrl' => $notifyUrl,
            ];
        }

        $withdrawalId = (int)($withdrawal['id'] ?? 0);
        $startedMs = (int) round(microtime(true) * 1000);
        $logId = null;
        if ($withdrawalId > 0) {
            $logId = $this->requestLogService->beginAttempt([
                'provider' => 'vexora',
                'environment' => $this->requestLogService->resolveEnvironment(null, $gateway),
                'transactionType' => 'withdrawal',
                'operation' => 'payout',
                'deliveryMode' => 'server_http',
                'withdrawalId' => $withdrawalId,
                'localOrderId' => $transactionId,
                'amount' => $withdrawal['quotedAmount'] ?? $withdrawal['amount'] ?? null,
                'currencyCode' => $withdrawal['currencyCode'] ?? null,
                'requestMethod' => 'POST',
                'endpointPath' => '/disbursement',
                'requestPayload' => $postData,
            ]);
            if ($logId) {
                $this->requestLogService->markSent($logId);
            }
        }

        try {
            $response = $service->createDisbursement($postData);
        } catch (Throwable $e) {
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'errorMessage' => $e->getMessage(),
                    'durationMs' => max(0, (int) round(microtime(true) * 1000) - $startedMs),
                ]);
            }
            Logger::error('Vexora disbursement request failed', [
                'withdrawalId' => (int)($withdrawal['id'] ?? 0),
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $durationMs = max(0, (int) round(microtime(true) * 1000) - $startedMs);
        $providerStatus = trim((string)($response['code'] ?? $response['data']['status'] ?? ''));
        $platFormTradeNo = trim((string)($response['data']['platFormTradeNo'] ?? ''));

        if (VexoraService::isSystemException($response)) {
            $reason = trim((string)($response['msg'] ?? $response['data']['message'] ?? 'Vexora system exception'));
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_UNKNOWN, [
                    'providerOrderId' => $platFormTradeNo !== '' ? $platFormTradeNo : null,
                    'providerStatus' => $providerStatus !== '' ? $providerStatus : null,
                    'responsePayload' => $response,
                    'errorMessage' => $reason,
                    'durationMs' => $durationMs,
                ]);
            }
            Logger::error('Vexora disbursement system exception (code=5000); leaving withdrawal processing', [
                'withdrawalId' => (int)($withdrawal['id'] ?? 0),
                'msg' => $reason,
            ]);
            return [
                'success' => true,
                'status' => 'processing',
                'code' => VexoraService::CODE_SYSTEM_EXCEPTION,
                'reason' => $reason,
                'response' => $response,
            ];
        }

        if (!VexoraService::isRequestAccepted($response)) {
            $reason = trim((string)($response['msg'] ?? $response['data']['message'] ?? 'Vexora disbursement rejected'));
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'providerOrderId' => $platFormTradeNo !== '' ? $platFormTradeNo : null,
                    'providerStatus' => $providerStatus !== '' ? $providerStatus : null,
                    'responsePayload' => $response,
                    'errorMessage' => $reason,
                    'durationMs' => $durationMs,
                ]);
            }
            return ['success' => false, 'reason' => $reason, 'response' => $response];
        }

        $status = VexoraService::mapStatus($response['data']['status'] ?? '');
        if ($status === 'failed') {
            $reason = trim((string)($response['data']['message'] ?? 'Vexora disbursement failed'));
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'providerOrderId' => $platFormTradeNo !== '' ? $platFormTradeNo : null,
                    'providerStatus' => $providerStatus !== '' ? $providerStatus : $status,
                    'responsePayload' => $response,
                    'errorMessage' => $reason,
                    'durationMs' => $durationMs,
                ]);
            }
            return ['success' => false, 'reason' => $reason, 'response' => $response];
        }

        if ($platFormTradeNo !== '') {
            $this->persistWithdrawalGatewayTransactionId((int)($withdrawal['id'] ?? 0), $platFormTradeNo);
        }

        if ($logId) {
            $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_ACCEPTED, [
                'providerOrderId' => $platFormTradeNo !== '' ? $platFormTradeNo : null,
                'providerStatus' => $providerStatus !== '' ? $providerStatus : $status,
                'responsePayload' => $response,
                'durationMs' => $durationMs,
            ]);
        }

        return [
            'success' => true,
            'status' => $status,
            'platFormTradeNo' => $platFormTradeNo,
            'response' => $response
        ];
    }

    private function getGatewayConfigData($gateway) {
        $configData = $gateway['configData'] ?? null;
        if (is_string($configData) && trim($configData) !== '') {
            $decoded = json_decode($configData, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function buildPaymentAsiaPayoutUrl(array $gateway): string {
        $gatewayConfig = $this->getGatewayConfigData($gateway);
        $withdrawalUrl = trim((string)($gatewayConfig['withdrawal_url'] ?? 'https://gateway.pa-sys.com/{MerchantToken}/payout/v1/request'));
        $merchantToken = trim((string)($gateway['apiKey'] ?? ''));

        if ($withdrawalUrl === '' || $merchantToken === '') {
            return '';
        }

        return str_replace(
            ['{MerchantToken}', '{merchant_token}'],
            rawurlencode($merchantToken),
            $withdrawalUrl
        );
    }

    private function buildPaymentAsiaWithdrawalDatafeedUrl(): string {
        $baseUrl = rtrim((string)($this->appConfig['file_base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            return '';
        }

        $baseUrl = preg_replace('#/index\.php$#i', '', $baseUrl);
        return $baseUrl . '/index.php?path=api/callback/payment-asia/withdrawal';
    }

    private function buildPaymentAsiaBeneficiaryName(array $supportData): string {
        $parts = [
            trim((string)($supportData['first_name'] ?? '')),
            trim((string)($supportData['middle_name'] ?? '')),
            trim((string)($supportData['last_name'] ?? ''))
        ];

        $parts = array_values(array_filter($parts, static function ($value) {
            return $value !== '';
        }));

        return trim(implode(' ', $parts));
    }

    private function generatePaymentAsiaSign(array $params, string $secret): string {
        unset($params['sign']);
        ksort($params, SORT_STRING);
        return hash('sha512', http_build_query($params, '', '&', PHP_QUERY_RFC1738) . $secret);
    }

    private function persistWithdrawalGatewayTransactionId(int $withdrawalId, string $transactionId): void {
        if ($withdrawalId <= 0 || trim($transactionId) === '') {
            return;
        }

        try {
            $this->withdrawalModel->update($withdrawalId, [
                'gatewayTransactionId' => trim($transactionId)
            ]);
        } catch (Throwable $e) {
            Logger::error('Failed to persist Payment Asia withdrawal transaction id: ' . $e->getMessage());
        }
    }

    private function resolveCoinsbuyWithdrawalSupportData($withdrawal) {
        $raw = trim((string)($withdrawal['supportContent'] ?? ''));
        if ($raw === '') {
            throw new InvalidArgumentException('Coinsbuy withdrawal requires supportContent');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Coinsbuy supportContent must be valid JSON');
        }

        // wallet_address 是必须的，因为 payout body 的 address 字段从这里取
        $required = ['wallet_address', 'first_name', 'last_name', 'country', 'address_type'];
        $missing = [];
        foreach ($required as $field) {
            if (trim((string)($decoded[$field] ?? '')) === '') {
                $missing[] = $field;
            }
        }
        if (!empty($missing)) {
            Response::validationError([
                'supportContent' => ['Coinsbuy withdrawal missing fields: ' . implode(', ', $missing)]
            ]);
        }

        return $decoded;
    }

    private function buildCoinsbuyBackendCallbackUrl(): string {
        $baseUrl = rtrim((string)($this->appConfig['file_base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            return '';
        }

        $baseUrl = preg_replace('#/index\.php$#i', '', $baseUrl);
        return $baseUrl . '/index.php?path=api/callback/coinsbuy/withdrawal';
    }

    /**
     * 把 supportContent 拼成 Coinsbuy 要求的 travel_rule_info 结构
     * addressLine 数组：address1 + address2（非空才进数组）
     */
    private function buildCoinsbuyTravelRuleInfo(array $supportData): array {
        $addressLines = [];
        $addr1 = trim((string)($supportData['address1'] ?? ''));
        $addr2 = trim((string)($supportData['address2'] ?? ''));
        if ($addr1 !== '') {
            $addressLines[] = $addr1;
        }
        if ($addr2 !== '') {
            $addressLines[] = $addr2;
        }

        return [
            'beneficiary' => [
                'beneficiaryPersons' => [[
                    'naturalPerson' => [
                        'name' => [[
                            'nameIdentifier' => [[
                                'primaryIdentifier' => trim((string)($supportData['first_name'] ?? '')),
                                'secondaryIdentifier' => trim((string)($supportData['last_name'] ?? '')),
                            ]],
                        ]],
                        'geographicAddress' => [[
                            'country' => trim((string)($supportData['country'] ?? '')),
                            'addressLine' => $addressLines,
                            'addressType' => trim((string)($supportData['address_type'] ?? '')),
                        ]],
                    ],
                ]],
            ],
        ];
    }

    /**
     * 调 Coinsbuy 创建 payout
     * label / tracking_id 用本地的 transactionId（TXN-...-W...）
     * address 直接用客户提交的 wallet_address
     */
    private function requestCoinsbuyWithdrawal($withdrawal, $supportContent = null) {
        $supportContent = is_array($supportContent) ? $supportContent : $this->resolveCoinsbuyWithdrawalSupportData($withdrawal);

        $transactionId = trim((string)($withdrawal['transactionId'] ?? ''));
        $amount = (string)($withdrawal['quotedAmount'] ?? $withdrawal['amount'] ?? '');
        $address = trim((string)($supportContent['wallet_address'] ?? ''));

        if ($transactionId === '' || $amount === '' || $address === '') {
            return ['success' => false, 'error' => 'Missing transactionId / amount / wallet_address'];
        }

        $requestPayload = [
            'tracking_id' => $transactionId,
            'label' => str_replace('-', '', $transactionId),
            'amount' => $amount,
            'address' => $address,
            'callback_url' => $this->buildCoinsbuyBackendCallbackUrl(),
            'travel_rule_info' => $this->buildCoinsbuyTravelRuleInfo($supportContent),
        ];

        $withdrawalId = (int)($withdrawal['id'] ?? 0);
        $startedMs = (int) round(microtime(true) * 1000);
        $logId = null;
        if ($withdrawalId > 0) {
            $logId = $this->requestLogService->beginAttempt([
                'provider' => 'coinsbuy',
                'environment' => $this->requestLogService->resolveEnvironment(),
                'transactionType' => 'withdrawal',
                'operation' => 'payout',
                'deliveryMode' => 'server_http',
                'withdrawalId' => $withdrawalId,
                'localOrderId' => $transactionId,
                'amount' => $withdrawal['quotedAmount'] ?? $withdrawal['amount'] ?? null,
                'currencyCode' => $withdrawal['currencyCode'] ?? null,
                'requestMethod' => 'POST',
                'endpointPath' => '/payout',
                'requestPayload' => $requestPayload,
            ]);
            if ($logId) {
                $this->requestLogService->markSent($logId);
            }
        }

        try {
            $service = new CoinsbuyService();
            // Coinsbuy 的 label 不允许特殊符号（- 也算）；tracking_id 不受此限制，保留原始格式用于对账
            $apiResponse = $service->createPayout($requestPayload);

            $durationMs = max(0, (int) round(microtime(true) * 1000) - $startedMs);
            $gatewayPayoutId = trim((string)($apiResponse['data']['id'] ?? ''));
            if ($gatewayPayoutId !== '') {
                $this->persistWithdrawalGatewayTransactionId((int)($withdrawal['id'] ?? 0), $gatewayPayoutId);
            }

            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_ACCEPTED, [
                    'providerOrderId' => $gatewayPayoutId !== '' ? $gatewayPayoutId : null,
                    'responsePayload' => $apiResponse,
                    'durationMs' => $durationMs,
                ]);
            }

            return [
                'success' => true,
                'requestId' => $gatewayPayoutId !== '' ? $gatewayPayoutId : null,
                'response' => $apiResponse,
            ];
        } catch (Throwable $e) {
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'errorMessage' => $e->getMessage(),
                    'durationMs' => max(0, (int) round(microtime(true) * 1000) - $startedMs),
                ]);
            }
            Logger::error('Coinsbuy createPayout failed: ' . $e->getMessage(), [
                'withdrawalId' => (int)($withdrawal['id'] ?? 0),
                'transactionId' => $transactionId,
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'reason' => $e->getMessage(),
            ];
        }
    }

    private function parseOptionalIbeepaySupportContent($withdrawal) {
        $raw = trim((string)($withdrawal['supportContent'] ?? ''));
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }


    private function notifyAdminsOfWithdrawalCreated(array $withdrawal): void
    {
        $clientId = (int)($withdrawal['userId'] ?? 0);
        if ($clientId <= 0) {
            return;
        }

        $client = $this->userModel->findById($clientId);
        if (!$client) {
            return;
        }

        $clientName = trim(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? ''));
        if ($clientName === '') {
            $clientName = $client['email'] ?? ('Client #' . $clientId);
        }

        $subject = "New Withdrawal Request from {$clientName}";
        $message = "Client {$clientName} has created a withdrawal request for review.";
        $metadata = json_encode([
            'withdrawalId' => (int)($withdrawal['id'] ?? 0),
            'clientId' => $clientId,
            'amount' => (float)($withdrawal['amount'] ?? 0),
            'action' => 'view_withdrawal',
            'actionUrl' => '/withdrawals'
        ]);

        $this->createAdminNotification(0, $subject, $message, $metadata, 'withdrawal_created');
    }

    private function createAdminNotification(int $adminId, string $subject, string $message, string $metadata, string $type): void
    {
        if ($adminId < 0) {
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

    private function sendWithdrawalAdminStatusNotice(array $withdrawal, string $status, array $context = []): void
    {
        $clientId = (int)($withdrawal['userId'] ?? 0);
        $withdrawalId = (int)($withdrawal['id'] ?? 0);
        if ($clientId <= 0 || $withdrawalId <= 0) {
            return;
        }

        $client = $this->userModel->findById($clientId);
        if (!$client) {
            return;
        }

        [$subject, $message, $type] = $this->buildWithdrawalAdminStatusNoticePayload($client, $status, $context);
        if ($subject === '' || $message === '' || $type === '') {
            return;
        }

        $metadata = json_encode([
            'withdrawalId' => $withdrawalId,
            'clientId' => $clientId,
            'status' => $status,
            'amount' => (float)($withdrawal['amount'] ?? 0),
            'tradingAccountId' => $withdrawal['tradingAccountId'] ?? null,
            'adminNotes' => $context['adminNotes'] ?? null,
            'rejectionReasonId' => $context['rejectionReasonId'] ?? null,
            'rejectionReasonTitle' => $context['rejectionReasonTitle'] ?? null,
            'rejectionNotes' => $context['rejectionNotes'] ?? null,
            'customReason' => $context['customReason'] ?? null,
            'action' => 'view_withdrawal',
            'actionUrl' => '/withdrawals'
        ]);

        $this->createAdminNotification(0, $subject, $message, $metadata, $type);
    }

    private function buildWithdrawalAdminStatusNoticePayload(array $client, string $status, array $context = []): array
    {
        $clientName = trim(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? ''));
        if ($clientName === '') {
            $clientName = $client['email'] ?? 'Client';
        }

        if ($status === 'approved') {
            $subject = "Withdrawal approved for {$clientName}";
            $message = "Withdrawal request from {$clientName} has been approved.";
            $adminNotes = trim((string)($context['adminNotes'] ?? ''));
            if ($adminNotes !== '') {
                $message .= ' Notes: ' . $adminNotes;
            }

            return [$subject, $message, 'withdrawal_approved'];
        }

        if ($status === 'rejected') {
            $subject = "Withdrawal rejected for {$clientName}";
            $message = "Withdrawal request from {$clientName} has been rejected.";
            $reasonText = trim((string)($context['customReason'] ?? $context['rejectionNotes'] ?? $context['rejectionReasonTitle'] ?? ''));
            if ($reasonText !== '') {
                $message .= ' Reason: ' . $reasonText;
            }

            return [$subject, $message, 'withdrawal_rejected'];
        }

        return ['', '', ''];
    }

    private function hasBalanceSyncWithdrawals(array $withdrawalIds): bool
    {
        foreach ($withdrawalIds as $withdrawalId) {
            $withdrawal = $this->withdrawalModel->findById((int)$withdrawalId);
            if ($withdrawal && $this->paymentService->needsBalanceSync($withdrawal)) {
                return true;
            }
        }

        return false;
    }

    private function buildWithdrawalProcessorOrderId($withdrawal) {
        $withdrawalId = (int)($withdrawal['id'] ?? 0);
        if ($withdrawalId > 0) {
            return 'utrada-withdraw-' . $withdrawalId;
        }

        $transactionId = trim((string)($withdrawal['transactionId'] ?? ''));
        if ($transactionId !== '') {
            return $transactionId;
        }

        return 'utrada-withdraw-' . time();
    }

    /**
     * 添加标签到提款
     * POST /api/withdrawals/{id}/tags
     */
    public function addTag($id) {
        $admin = $this->requireAdmin();

        $withdrawal = $this->withdrawalModel->findById($id);
        if (!$withdrawal) {
            Response::notFound('Withdrawal not found');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        Validator::make($data, [
            'tagName' => 'required|string'
        ]);

        $tagName = trim($data['tagName']);

        // 查找或创建标签
        $tag = $this->tagModel->findOrCreate($tagName, $admin['userId']);

        // 分配标签
        $this->tagAssignmentModel->assignTag($id, $tag['id'], $admin['userId']);

        // 获取更新后的标签列表
        $tags = $this->tagAssignmentModel->getWithdrawalTags($id);

        Response::success($tags, 'Tag added successfully');
    }

    /**
     * 移除提款的标签
     * DELETE /api/withdrawals/{id}/tags/{tagId}
     */
    public function removeTag($id, $tagId) {
        $this->requireAdmin();
        $subModule = OperationLogPages::resolveLogWithdrawalsFromRequest();
        $opLog = new AdminOperationLogWriter();

        $withdrawal = $this->withdrawalModel->findById($id);
        if (!$withdrawal) {
            $opLog->logWithdrawalTagRemove($subModule, 0, '', '', false, 'Withdrawal not found');
            Response::notFound('Withdrawal not found');
        }

        $tag = $this->tagModel->findById($tagId);
        $tagName = trim((string) ($tag['tagName'] ?? ''));
        $clientId = (int) ($withdrawal['userId'] ?? 0);
        $transactionId = (string) ($withdrawal['transactionId'] ?? '');

        $this->tagAssignmentModel->removeTag($id, $tagId);

        $opLog->logWithdrawalTagRemove(
            $subModule,
            $clientId,
            $transactionId,
            $tagName !== '' ? $tagName : '—',
            true
        );

        Response::success(null, 'Tag removed successfully');
    }

    /**
     * 批量添加标签
     * POST /api/withdrawals/bulk-add-tags
     */
    public function bulkAddTags() {
        $admin = $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogWithdrawals($data);
        $opLog = new AdminOperationLogWriter();

        $validator = new Validator($data, [
            'withdrawalIds' => 'required|array',
            'tagName' => 'required|string'
        ]);
        if (!$validator->validate()) {
            $tagBulkErrors = $validator->getErrors();
            $opLog->logWithdrawalTagBulk(
                $subModule,
                [],
                '',
                false,
                OperationLogTextHelpers::validationErrorsToMessage($tagBulkErrors)
            );
            Response::validationError($tagBulkErrors);
        }

        $withdrawalIds = $data['withdrawalIds'];
        $tagName = trim($data['tagName']);

        try {
            $tag = $this->tagModel->findOrCreate($tagName, $admin['userId']);
            $this->tagAssignmentModel->bulkAssignTag($withdrawalIds, $tag['id'], $admin['userId']);
        } catch (Exception $e) {
            $opLog->logWithdrawalTagBulk($subModule, [], $tagName, false, $e->getMessage());
            Response::serverError($e->getMessage());
        }

        $snapshots = [];
        foreach ($withdrawalIds as $withdrawalId) {
            $row = $this->withdrawalModel->findById($withdrawalId);
            if (!$row) {
                continue;
            }
            $snapshots[] = [
                'userId' => (int) ($row['userId'] ?? 0),
                'transactionId' => (string) ($row['transactionId'] ?? ''),
            ];
        }
        $opLog->logWithdrawalTagBulk($subModule, $snapshots, $tagName, true);

        Response::success([
            'tag' => $tag,
            'assignedCount' => count($withdrawalIds)
        ], "Tag '{$tagName}' added to " . count($withdrawalIds) . " withdrawals");
    }

    /**
     * 请求额外文档
     * POST /api/withdrawals/{id}/request-documents
     */
    public function requestDocuments($id) {
        $admin = $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogWithdrawals($data);
        $opLog = new AdminOperationLogWriter();

        $withdrawal = $this->withdrawalModel->findById($id);
        if (!$withdrawal) {
            $opLog->logWithdrawalRequestDocuments($subModule, 0, '', false, 'Withdrawal not found');
            Response::notFound('Withdrawal not found');
        }

        $clientId = (int) ($withdrawal['userId'] ?? 0);
        $transactionId = (string) ($withdrawal['transactionId'] ?? '');

        $validator = new Validator($data, [
            'items' => 'required|array'
        ]);
        if (!$validator->validate()) {
            $docRequestErrors = $validator->getErrors();
            $opLog->logWithdrawalRequestDocuments(
                $subModule,
                $clientId,
                $transactionId,
                false,
                OperationLogTextHelpers::validationErrorsToMessage($docRequestErrors)
            );
            Response::validationError($docRequestErrors);
        }

        $items = $data['items'];
        $adminInstructions = $data['adminInstructions'] ?? null;

        if (empty($items)) {
            $opLog->logWithdrawalRequestDocuments(
                $subModule,
                $clientId,
                $transactionId,
                false,
                'At least one question or document is required'
            );
            Response::validationError([
                'items' => ['At least one question or document is required']
            ]);
        }

        // 创建文档请求
        $requestId = $this->documentRequestModel->create([
            'withdrawalId' => $id,
            'requestStatus' => 'pending',
            'requestedBy' => $admin['userId'],
            'requestedAt' => date('Y-m-d H:i:s'),
            'adminInstructions' => $adminInstructions
        ]);

        // 批量创建项目
        $this->documentRequestItemModel->bulkCreate($requestId, $items);

        // 添加时间线记录：后台请求补充资料
        $currentStatus = $withdrawal['status'];
        $this->statusHistoryModel->logStatusChange(
            $id,
            $currentStatus,
            $currentStatus,
            'Additional documents and information requested from client',
            $admin['userId']
        );

        // 获取客户信息
        $user = $this->userModel->findById($withdrawal['userId']);

        // 创建系统通知
        try {
            // 创建主通知记录
            $notificationId = $this->clientNotificationModel->create([
                'clientId' => $withdrawal['userId'],
                'subject' => 'Additional Information Required for Withdrawal',
                'message' => 'We need additional information to process your withdrawal request. Please provide the requested documents and answers.',
                'priority' => 'high',
                'scheduleType' => 'immediate',
                'status' => 'sent',
                'createdBy' => $admin['userId'],
                'createdAt' => date('Y-m-d H:i:s')
            ]);

            // 创建系统通知记录（带类型和元数据）
            $metadata = json_encode([
                'withdrawalId' => $id,
                'requestId' => $requestId,
                'action' => 'supplement_documents',
                'actionUrl' => "/client/withdrawal-supplement/{$id}"
            ]);

            $this->clientSystemNotificationModel->create([
                'notificationId' => $notificationId,
                'type' => 'withdrawal_document_request',
                'metadata' => $metadata,
                'clientId' => $withdrawal['userId'],
                'subject' => 'Additional Information Required for Withdrawal',
                'message' => 'We need additional information to process your withdrawal request. Please provide the requested documents and answers.',
                'isRead' => 0,
                'createdAt' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            // 通知创建失败不影响主流程，记录错误但继续执行
            // Logger::error('Failed to create system notification', [
            //     'withdrawalId' => $id,
            //     'error' => $e->getMessage()
            // ]);
        }

        // 发送邮件（暂时注释保留，未来再补充）
        // try {
        //     $emailSender = new EmailSender();
        //     $emailSubject = 'Additional Information Required for Withdrawal Request';
        //     $emailContent = "Dear {$user['firstName']},\n\n";
        //     $emailContent .= "We need additional information to process your withdrawal request.\n\n";
        //     if ($adminInstructions) {
        //         $emailContent .= "Additional Instructions:\n{$adminInstructions}\n\n";
        //     }
        //     $emailContent .= "Please log in to your account to provide the requested information.\n\n";
        //     $emailContent .= "Best regards,\nThe Team";
        //
        //     $emailSender->sendClientNotification(
        //         $user['email'],
        //         $emailSubject,
        //         $emailContent,
        //         [
        //             'withdrawalId' => $id,
        //             'requestId' => $requestId,
        //             'type' => 'withdrawal_document_request'
        //         ]
        //     );
        // } catch (Exception $e) {
        //     // 邮件发送失败不影响主流程
        // }

        // 获取完整的请求信息
        $request = $this->documentRequestModel->getByWithdrawal($id);
        $request['items'] = $this->documentRequestItemModel->getRequestItems($requestId);

        $opLog->logWithdrawalRequestDocuments($subModule, $clientId, $transactionId, true);

        Response::created($request, 'Document request sent to client');
    }

    /**
     * 发送邮件给客户
     * POST /api/withdrawals/{id}/send-email
     */
    public function sendEmail($id) {
        $admin = $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogWithdrawals($data);
        $opLog = new AdminOperationLogWriter();

        $withdrawal = $this->withdrawalModel->findById($id);
        if (!$withdrawal) {
            $opLog->logWithdrawalEmail($subModule, 0, '', false, 'Withdrawal not found');
            Response::notFound('Withdrawal not found');
        }

        $clientId = (int) ($withdrawal['userId'] ?? 0);

        $validator = new Validator($data, [
            'email' => 'required|email',
            'subject' => 'required|string|max:255',
            'content' => 'required|string'
        ]);
        if (!$validator->validate()) {
            $emailErrors = $validator->getErrors();
            $opLog->logWithdrawalEmail(
                $subModule,
                $clientId,
                '',
                false,
                OperationLogTextHelpers::validationErrorsToMessage($emailErrors)
            );
            Response::validationError($emailErrors);
        }

        $email = $data['email'];
        $subject = $data['subject'];
        $content = $data['content'];

        // 验证邮箱是否匹配提款的客户邮箱
        $user = $this->userModel->findById($withdrawal['userId']);
        if (!$user || $user['email'] !== $email) {
            $opLog->logWithdrawalEmail(
                $subModule,
                $clientId,
                $email,
                false,
                'Email address does not match the withdrawal client'
            );
            Response::validationError([
                'email' => ['Email address does not match the withdrawal client']
            ]);
        }

        try {
            // 发送邮件
            $emailSender = new EmailSender();

            $success = $emailSender->sendClientNotification(
                $email,
                $subject,
                $content,
                [
                    'withdrawalId' => $id,
                    'sentBy' => $admin['userId'],
                    'type' => 'withdrawal'
                ]
            );

            if ($success) {
                $opLog->logWithdrawalEmail($subModule, $clientId, $email, true);
                Response::success([
                    'message' => 'Email sent successfully',
                    'email' => $email,
                    'subject' => $subject
                ], 'Email sent successfully');
            } else {
                $opLog->logWithdrawalEmail($subModule, $clientId, $email, false, 'Failed to send email');
                Response::error('Failed to send email', 500);
            }
        } catch (Exception $e) {
            $opLog->logWithdrawalEmail(
                $subModule,
                $clientId,
                $email,
                false,
                'Failed to send email: ' . $e->getMessage()
            );
            Response::error('Failed to send email: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取提款的状态历史
     * GET /api/withdrawals/{id}/history
     */
    public function getHistory($id) {
        $withdrawal = $this->withdrawalModel->findById($id);
        if (!$withdrawal) {
            Response::notFound('Withdrawal not found');
        }

        $history = $this->statusHistoryModel->getWithdrawalHistory($id);

        Response::success($history);
    }

    /**
     * 获取提款统计数据
     * GET /api/withdrawals/statistics
     */
    public function statistics() {
        $startDate = $_GET['startDate'] ?? null;
        $endDate = $_GET['endDate'] ?? null;

        $stats = $this->withdrawalModel->getStatistics($startDate, $endDate);

        Response::success($stats);
    }

    /**
     * 获取拒绝原因列表
     * GET /api/withdrawals/rejection-reasons
     */
    public function getRejectionReasons() {
        $scope = trim((string)($_GET['scope'] ?? 'withdrawal'));
        if (!in_array($scope, ['deposit', 'withdrawal'], true)) {
            Response::validationError([
                'scope' => ['Invalid rejection reason scope']
            ]);
        }
        $reasons = $this->rejectionReasonModel->getActiveReasons($scope);
        Response::success($reasons);
    }

    /**
     * 导出提款数据
     * POST /api/withdrawals/export
     */
    public function export() {
        $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogWithdrawals($data);
        $opLog = new AdminOperationLogWriter();
        $withdrawalIds = $data['withdrawalIds'] ?? [];
        $format = $data['format'] ?? 'csv';

        if (empty($withdrawalIds)) {
            $opLog->logWithdrawalExport(
                $subModule,
                0,
                $format,
                false,
                'Please select at least one withdrawal to export'
            );
            Response::validationError([
                'withdrawalIds' => ['Please select at least one withdrawal to export']
            ]);
        }

        // 获取提款数据
        $withdrawals = [];
        foreach ($withdrawalIds as $withdrawalId) {
            $withdrawal = $this->withdrawalModel->getWithdrawalDetails($withdrawalId);
            if ($withdrawal) {
                $withdrawals[] = $withdrawal;
            }
        }

        if (empty($withdrawals)) {
            $opLog->logWithdrawalExport($subModule, 0, $format, false, 'No valid withdrawals found for export');
            Response::error('No valid withdrawals found for export', 404);
        }

        Response::success([
            'withdrawals' => $withdrawals,
            'format' => $format,
            'count' => count($withdrawals)
        ], 'Export data ready');
    }

    /**
     * 获取客户的提款列表 (客户端)
     * GET /api/withdrawals/my-withdrawals
     */
    public function myWithdrawals() {
        $client = $this->requireClient();

        $limit = $_GET['limit'] ?? 10;
        $withdrawals = $this->withdrawalModel->getUserWithdrawals($client['userId'], $limit);

        Response::success($withdrawals);
    }

    /**
     * 获取提款的文档请求（客户端和后台）
     * GET /api/withdrawals/{id}/document-request
     */
    public function getDocumentRequest($id) {
        // 判断是客户端还是管理员
        $payload = JWT::getPayload();
        $isAdmin = isset($payload['type']) && $payload['type'] === 'admin';

        if (!$isAdmin) {
            $client = $this->requireClient();
            $userId = $client['userId'];

            $withdrawal = $this->withdrawalModel->findById($id);
            if (!$withdrawal) {
                Response::notFound('Withdrawal not found');
            }

            // 验证提款归属
            if ($withdrawal['userId'] != $userId) {
                Response::forbidden('Access denied');
            }
        } else {
            $this->requireAdmin();
        }

        // 获取文档请求
        $request = $this->documentRequestModel->getByWithdrawal($id);

        // 如果没有文档请求，返回成功但data为null（文档请求不是必须的）
        if (!$request) {
            Response::success(null, 'No document request found');
            return;
        }

        // 获取请求项目
        $items = $this->documentRequestItemModel->getRequestItems($request['id']);

        // 处理JSON字段和生成预签名URL（仅后台需要）
        foreach ($items as &$item) {
            if ($item['questionOptions']) {
                $item['questionOptions'] = json_decode($item['questionOptions'], true) ?: [];
            }
            if ($item['acceptedFileTypes']) {
                $item['acceptedFileTypes'] = json_decode($item['acceptedFileTypes'], true) ?: [];
            }
            // 解析clientResponse（如果是JSON）
            if ($item['clientResponse']) {
                $decoded = json_decode($item['clientResponse'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $item['clientResponse'] = $decoded;
                } else {
                    // 如果不是JSON，保持原样
                    $item['clientResponse'] = $item['clientResponse'];
                }
            }

            // 如果是后台请求，为文件生成预签名URL
            if ($isAdmin && isset($item['clientResponse']['files']) && is_array($item['clientResponse']['files'])) {
                $s3Uploader = new S3Uploader();
                foreach ($item['clientResponse']['files'] as &$file) {
                    if (isset($file['filePath']) || isset($file['s3Url'])) {
                        $filePath = $file['filePath'] ?? $file['s3Url'] ?? null;
                        if ($filePath) {
                            $file['downloadUrl'] = $s3Uploader->getFileDownloadUrl($filePath);
                        }
                    }
                }
            }
        }

        $request['items'] = $items;

        Response::success($request);
    }

    /**
     * 上传文件（客户端）
     * POST /api/withdrawals/{id}/upload-document
     */
    public function uploadDocument($id) {
        $client = $this->requireClient();
        $userId = $client['userId'];

        $withdrawal = $this->withdrawalModel->findById($id);
        if (!$withdrawal) {
            Response::notFound('Withdrawal not found');
        }

        // 验证提款归属
        if ($withdrawal['userId'] != $userId) {
            Response::forbidden('Access denied');
        }

        if (!isset($_FILES['file']) || !isset($_POST['itemId'])) {
            Response::error('File and itemId are required', 400);
        }

        $file = $_FILES['file'];
        $itemId = (int)$_POST['itemId'];

        // 验证文件
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            Response::error('File size exceeds 5MB limit', 400);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($file['type'], $allowedTypes)) {
            Response::error('Invalid file type. Only JPG, PNG, and PDF are allowed', 400);
        }

        // 验证itemId是否存在且属于该提款
        $request = $this->documentRequestModel->getByWithdrawal($id);
        if (!$request) {
            Response::error('Document request not found', 404);
        }

        $item = $this->documentRequestItemModel->findById($itemId);
        if (!$item || $item['requestId'] != $request['id']) {
            Response::error('Invalid item', 400);
        }

        try {
            // 生成唯一文件名
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'withdraw_' . $id . '_item' . $itemId . '_' . time() . '_' . uniqid() . '.' . $extension;

            // 初始化 S3 上传器
            $s3Uploader = new S3Uploader();

            // 生成 S3 Key（使用withdraw路径）
            $s3Key = $s3Uploader->generateS3Key($filename, 'withdraw');

            // 上传到 S3
            $uploadResult = $s3Uploader->uploadFile(
                $file['tmp_name'],
                $s3Key,
                $file['type']
            );

            if (!$uploadResult['success']) {
                Response::error('Failed to upload file to S3: ' . ($uploadResult['error'] ?? 'Unknown error'), 500);
            }

            // 获取 S3 URL
            $s3Url = $uploadResult['url'];

            // 保存文件信息到clientResponse（JSON格式）
            $existingResponse = $item['clientResponse'] ? json_decode($item['clientResponse'], true) : [];
            if (!is_array($existingResponse)) {
                $existingResponse = [];
            }

            if (!isset($existingResponse['files'])) {
                $existingResponse['files'] = [];
            }

            $fileInfo = [
                'fileName' => $file['name'],
                'filePath' => $s3Key,
                's3Url' => $s3Url,
                'uploadedAt' => date('Y-m-d H:i:s')
            ];

            $existingResponse['files'][] = $fileInfo;

            // 更新clientResponse
            $this->documentRequestItemModel->updateClientResponse($itemId, json_encode($existingResponse));

            Response::success([
                'filename' => $file['name'],
                's3Key' => $s3Key
            ], 'File uploaded successfully');

        } catch (Exception $e) {
            Response::error('Failed to upload file: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 提交答案和文档（客户端）
     * POST /api/withdrawals/{id}/submit-documents
     */
    public function submitDocuments($id) {
        $client = $this->requireClient();
        $userId = $client['userId'];

        $withdrawal = $this->withdrawalModel->findById($id);
        if (!$withdrawal) {
            Response::notFound('Withdrawal not found');
        }

        // 验证提款归属
        if ($withdrawal['userId'] != $userId) {
            Response::forbidden('Access denied');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        Validator::make($data, [
            'answers' => 'required|array'
        ]);

        // 获取文档请求
        $request = $this->documentRequestModel->getByWithdrawal($id);
        if (!$request) {
            Response::error('Document request not found', 404);
        }

        if ($request['requestStatus'] !== 'pending') {
            Response::error('Document request is not in pending status', 400);
        }

        $answers = $data['answers'];
        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            // 获取所有请求项目
            $items = $this->documentRequestItemModel->getRequestItems($request['id']);

            foreach ($items as $item) {
                $itemId = $item['id'];

                // 检查是否有对应的答案
                if (!isset($answers[$itemId])) {
                    // 如果是必填项，检查是否已有clientResponse
                    if ($item['isRequired'] && empty($item['clientResponse'])) {
                        throw new Exception("Required item '{$item['questionText']}' or '{$item['documentName']}' is missing");
                    }
                    continue;
                }

                $answer = $answers[$itemId];

                // 根据itemType处理答案
                if ($item['itemType'] === 'question') {
                    // 处理问题答案
                    $responseData = [];

                    if ($item['questionType'] === 'file_upload') {
                        // 文件上传类型，answer应该是文件信息数组
                        if (isset($answer['files']) && is_array($answer['files'])) {
                            $responseData = ['files' => $answer['files']];
                        } else {
                            throw new Exception("File upload question requires files array");
                        }
                    } else {
                        // 文本、选择等类型
                        $responseData = [
                            'answerText' => $answer['answerText'] ?? null,
                            'answerValues' => $answer['answerValues'] ?? null
                        ];
                    }

                    $this->documentRequestItemModel->updateClientResponse($itemId, json_encode($responseData));

                } else {
                    // 处理文档上传
                    if (isset($answer['files']) && is_array($answer['files'])) {
                        $responseData = ['files' => $answer['files']];
                        $this->documentRequestItemModel->updateClientResponse($itemId, json_encode($responseData));
                    } else {
                        throw new Exception("Document item requires files array");
                    }
                }
            }

            // 更新请求状态为submitted
            $this->documentRequestModel->update($request['id'], [
                'requestStatus' => 'submitted',
                'submittedAt' => date('Y-m-d H:i:s')
            ]);

            // 添加时间线记录：客户端提交补充资料
            $currentStatus = $withdrawal['status'];
            $this->statusHistoryModel->logStatusChange(
                $id,
                $currentStatus,
                $currentStatus,
                'Client submitted additional documents and information',
                null // 客户端提交，changedBy 为 null
            );

            $db->commit();

            // 获取更新后的请求信息
            $updatedRequest = $this->documentRequestModel->getByWithdrawal($id);
            $updatedRequest['items'] = $this->documentRequestItemModel->getRequestItems($request['id']);

            Response::success($updatedRequest, 'Documents submitted successfully');

        } catch (Exception $e) {
            $db->rollBack();
            Response::error('Failed to submit documents: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 要求客户端认证（支持预览 X-Preview-Token 与 JWT）
     */
    private function requireClient() {
        $userId = ClientAuthContext::getCurrentClientUserId();
        if ($userId !== null) {
            $user = $this->userModel->findById($userId);
            if (!$user) {
                Response::unauthorized('User not found');
            }
            return [
                'userId' => $userId,
                'user' => $user
            ];
        }

        $payload = JWT::getPayload();
        if (!$payload || ($payload['type'] ?? '') !== 'client') {
            Response::forbidden('Client authentication required');
        }

        $userId = $payload['userId'] ?? null;
        if (!$userId) {
            Response::unauthorized('Invalid token payload');
        }

        $user = $this->userModel->findById($userId);
        if (!$user) {
            Response::unauthorized('User not found');
        }

        return [
            'userId' => $userId,
            'user' => $user
        ];
    }

    private function findEnabledIbeepayGateway() {
        return $this->gatewayModel->findByKeyWithSecrets('ibeepay');
    }

    private function getIbeepayGatewayConfig($gateway) {
        return $this->getGatewayConfigData($gateway);
    }

    private function resolveWithdrawalClientName(array $withdrawal) {
        $name = trim((string) (($withdrawal['firstName'] ?? '') . ' ' . ($withdrawal['lastName'] ?? '')));
        if ($name !== '') {
            return $name;
        }
        $userId = (int) ($withdrawal['userId'] ?? 0);
        if ($userId > 0) {
            $user = $this->userModel->findById($userId);
            if ($user) {
                return OperationLogTextHelpers::formatClientDisplayName($user);
            }
        }
        return $userId > 0 ? ('Client #' . $userId) : '—';
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
}
