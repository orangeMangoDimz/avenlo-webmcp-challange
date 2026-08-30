<?php
/**
 * Deposit Controller
 * 负责存款管理相关接口
 */

require_once __DIR__ . '/../models/Deposit.php';
require_once __DIR__ . '/../models/DepositStatusHistory.php';
require_once __DIR__ . '/../models/DepositTag.php';
require_once __DIR__ . '/../models/DepositTagAssignment.php';
require_once __DIR__ . '/../models/DepositNote.php';
require_once __DIR__ . '/../models/RejectionReason.php';
require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../models/TradingAccount.php';
require_once __DIR__ . '/../models/TradingAccountExternalAccount.php';
require_once __DIR__ . '/../models/TradingPlatform.php';
require_once __DIR__ . '/../models/ClientNotification.php';
require_once __DIR__ . '/../models/ClientSystemNotification.php';
require_once __DIR__ . '/../models/AdminNotification.php';
require_once __DIR__ . '/../models/AdminNotificationDelivery.php';
require_once __DIR__ . '/../models/AdminSystemNotification.php';
require_once __DIR__ . '/../models/TransactionLimit.php';
require_once __DIR__ . '/../models/Withdrawal.php';
require_once __DIR__ . '/../models/PaymentGatewaySetting.php';
require_once __DIR__ . '/../models/PaymentGatewayFundingSetting.php';
require_once __DIR__ . '/../models/PaymentSupportQuestion.php';
require_once __DIR__ . '/../models/PaymentMethod.php';
require_once __DIR__ . '/../models/CurrencyExchangeRate.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/ClientIp.php';
require_once __DIR__ . '/../utils/EmailSender.php';
require_once __DIR__ . '/../utils/FinanceProApiClient.php';
require_once __DIR__ . '/../utils/Mt5ApiClient.php';
require_once __DIR__ . '/../models/AutoApprovalRule.php';
require_once __DIR__ . '/../models/AutoApprovalLog.php';
require_once __DIR__ . '/../services/CommissionOrderService.php';
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

class DepositController {
    private const DEFAULT_GATEWAY_AMOUNT_DECIMAL_PLACES = 2;
    private const MAX_GATEWAY_AMOUNT_DECIMAL_PLACES = 4;

    private $depositModel;
    private $statusHistoryModel;
    private $tagModel;
    private $tagAssignmentModel;
    private $noteModel;
    private $rejectionReasonModel;
    private $userModel;
    private $tradingAccountModel;
    private $externalAccountModel;
    private $platformModel;
    private $clientNotificationModel;
    private $clientSystemNotificationModel;
    private $adminNotificationModel;
    private $adminNotificationDeliveryModel;
    private $adminSystemNotificationModel;
    private $limitModel;
    private $gatewayModel;
    private $gatewayFeeModel;
    private $paymentSupportQuestionModel;
    private $paymentMethodModel;
    private $exchangeRateModel;
    private $financePro;
    private $financeProClient;
    private $ibeepay;
    private $mt5;
    private $mt5Client;
    private $appConfig;
    private $clientFrontendUrl;
    private $depositCallbackTemplates;
    private $autoApprovalRuleModel;
    private $autoApprovalLogModel;
    private $tradingAccountAmountService;
    private $walletBalanceService;
    private $paymentService;
    private $requestLogService;

    public function __construct() {
        $this->depositModel = new Deposit();
        $this->statusHistoryModel = new DepositStatusHistory();
        $this->tagModel = new DepositTag();
        $this->tagAssignmentModel = new DepositTagAssignment();
        $this->noteModel = new DepositNote();
        $this->rejectionReasonModel = new RejectionReason();
        $this->userModel = new ClientUser();
        $this->tradingAccountModel = new TradingAccount();
        $this->externalAccountModel = new TradingAccountExternalAccount();
        $this->platformModel = new TradingPlatform();
        $this->clientNotificationModel = new ClientNotification();
        $this->clientSystemNotificationModel = new ClientSystemNotification();
        $this->adminNotificationModel = new AdminNotification();
        $this->adminNotificationDeliveryModel = new AdminNotificationDelivery();
        $this->adminSystemNotificationModel = new AdminSystemNotification();
        $this->limitModel = new TransactionLimit();
        $this->gatewayModel = new PaymentGatewaySetting();
        $this->gatewayFeeModel = new PaymentGatewayFundingSetting();
        $this->paymentSupportQuestionModel = new PaymentSupportQuestion();
        $this->paymentMethodModel = new PaymentMethod();
        $this->exchangeRateModel = new CurrencyExchangeRate();

        $appConfig = require __DIR__ . '/../config/app.php';
        $this->appConfig = $appConfig;
        $this->financePro = $appConfig['integrations']['finance_pro'] ?? [];
        $this->financeProClient = new FinanceProApiClient($this->financePro);
        $this->ibeepay = $appConfig['integrations']['ibeepay'] ?? [];
        $this->mt5 = $appConfig['integrations']['mt5'] ?? [];
        $this->mt5Client = new Mt5ApiClient($this->mt5);
        $this->clientFrontendUrl = rtrim((string)($appConfig['client_frontend_url'] ?? ''), '/');
        $this->depositCallbackTemplates = $appConfig['transaction_callbacks']['deposit'] ?? [];
        $this->autoApprovalRuleModel = new AutoApprovalRule();
        $this->autoApprovalLogModel = new AutoApprovalLog();
        $this->tradingAccountAmountService = new TradingAccountAmountService();
        $this->walletBalanceService = new WalletBalanceService();
        $this->paymentService = new PaymentSettlementService();
        $this->requestLogService = new PaymentProcessorRequestLogService();
    }

    /**
     * 获取存款列表 (管理员)
     * GET /api/deposits
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

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_deposits');
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
            $result = $this->depositModel->searchDeposits($search, $page, $perPage, $restrictToSalesId);
        } else {
            $result = $this->depositModel->getDeposits($page, $perPage, $filters);
        }

        // 为每个存款添加标签信息
        foreach ($result['items'] as &$deposit) {
            $deposit['tags'] = $this->tagAssignmentModel->getDepositTags($deposit['id']);
        }

        Response::paginated(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * 获取单个存款详情
     * GET /api/deposits/{id}
     */
    public function show($id) {
        $deposit = $this->depositModel->getDepositDetails($id);

        if (!$deposit) {
            Response::notFound('Deposit not found');
        }

        // 获取完整信息
        $deposit['tags'] = $this->tagAssignmentModel->getDepositTags($id);
        $deposit['statusHistory'] = $this->statusHistoryModel->getDepositHistory($id);
        $deposit['notes'] = $this->noteModel->getDepositNotes($id);
        $deposit['supportQuestions'] = $this->buildDepositSupportQuestions($deposit);

        Response::success($deposit);
    }

    /**
     * 创建存款 (客户端)
     * POST /api/deposits
     */
    public function create() {
        $client = $this->requireClient();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!isset($data['amount']) && isset($data['total'])) {
            $data['amount'] = $data['total'];
        }
        Validator::make($data, [
            'gatewaySettingId' => 'required|numeric',
            'amount' => 'required|numeric',
            'tradingAccountId' => 'numeric'
        ]);

        $gatewaySettingId = (int)$data['gatewaySettingId'];
        $gateway = $this->requireEnabledDepositGateway($gatewaySettingId);
        $gatewayWithSecrets = $this->getGatewayWithSecrets($gateway);
        $isXLinkGateway = $this->isXLinkGateway($gateway);
        $supportData = $data['supportData'] ?? null;
        if (!is_array($supportData)) {
            Response::validationError([
                'supportData' => ['supportData must be an object']
            ]);
        }

        $questions = $this->paymentSupportQuestionModel->getGatewayQuestions(
            $gatewaySettingId,
            PaymentSupportQuestion::SCOPE_DEPOSIT
        );
        if ($isXLinkGateway) {
            $supportData = $this->normalizeXLinkDepositSupportData($supportData);
            $questions = $this->paymentSupportQuestionModel->syncLockedScopeQuestions(
                $gatewaySettingId,
                PaymentSupportQuestion::SCOPE_DEPOSIT,
                XLinkService::depositSupportQuestions()
            );
        }

        // 没有配置 support questions 时也允许提交，supportData 走空校验直接通过
        $normalizedSupportData = $this->validateSupportDataAgainstQuestions($supportData, $questions);

        $result = $this->createDepositRequest(
            $client,
            $data,
            json_encode($normalizedSupportData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $gatewaySettingId,
            !$isXLinkGateway
        );

        if ($this->isIbeepayGateway($gateway)) {
            $gatewayConfig = $this->getIbeepayGatewayConfig($gatewayWithSecrets);
            $depositUrl = trim((string)($gatewayConfig['deposit_url'] ?? ''));
            $postData = $this->buildIbeepayPostData(
                $gatewayWithSecrets,
                $result['deposit'],
                $normalizedSupportData,
                (float)($result['deposit']['quotedAmount'] ?? $result['amount']),
                $result['tradingAccountId'],
                $client
            );
            $this->logDepositRedirectAttempt(
                'ibeepay',
                $gatewayWithSecrets,
                $result['deposit'],
                $depositUrl,
                $postData
            );
            $this->applyPostRedirectResponseData(
                $result['responseData'],
                $depositUrl,
                $postData
            );
        }

        if ($this->isPaymentAsiaGateway($gateway)) {
            $depositUrl = $this->buildPaymentAsiaDepositUrl($gatewayWithSecrets);
            $postData = $this->buildPaymentAsiaPostData(
                $gatewayWithSecrets,
                $result['deposit'],
                $normalizedSupportData
            );
            $this->logDepositRedirectAttempt(
                'payment_asia',
                $gatewayWithSecrets,
                $result['deposit'],
                $depositUrl,
                $postData
            );
            $this->applyPostRedirectResponseData(
                $result['responseData'],
                $depositUrl,
                $postData
            );
        }

        if ($this->isCoinsbuyGateway($gateway)) {
            $this->requestCoinsbuyDeposit($result['deposit'], $result['responseData']);
        }

        if ($this->isVexoraGateway($gateway)) {
            $this->requestVexoraDeposit(
                $result['deposit'],
                $result['responseData'],
                $gatewayWithSecrets,
                $normalizedSupportData
            );
        }

        if ($this->isFlashPayGateway($gateway)) {
            $this->requestFlashPayDeposit(
                $result['deposit'],
                $result['responseData'],
                $gatewayWithSecrets,
                $normalizedSupportData
            );
        }

        if ($this->isCvPayGateway($gateway)) {
            $this->requestCvPayDeposit(
                $result['deposit'],
                $result['responseData'],
                $gatewayWithSecrets,
                $normalizedSupportData
            );
        }

        if ($this->isFivePayGateway($gateway)) {
            $this->requestFivePayDeposit(
                $result['deposit'],
                $result['responseData'],
                $gatewayWithSecrets,
                $normalizedSupportData
            );
        }

        if ($isXLinkGateway) {
            $this->requestXLinkDeposit(
                $result['deposit'],
                $result['responseData'],
                $gatewayWithSecrets,
                $normalizedSupportData
            );
        }

        Response::created($result['responseData'], 'Deposit request created successfully');
    }

    public function createLegacy() {
        $client = $this->requireClient();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        Validator::make($data, [
            'gatewaySettingId' => 'required|numeric'
        ]);
        $result = $this->createDepositRequest($client, $data, null, (int)$data['gatewaySettingId']);

        Response::created($result['responseData'], 'Deposit request created successfully');
    }

    /**
     * 创建 Ibeepay 存款 (客户端)
     * POST /api/deposits/create/ibeepay
     */
    public function createLegacyIbeepay() {
        $client = $this->requireClient();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!isset($data['amount']) && isset($data['total'])) {
            $data['amount'] = $data['total'];
        }

        Validator::make($data, [
            'amount' => 'required|numeric',
            'dob' => 'required|string',
            'email' => 'required|email',
            'name' => 'required|string',
            'phone' => 'required|string',
            'tradingAccountId' => 'numeric'
        ]);

        $gateway = $this->findEnabledIbeepayGateway();
        if (!$gateway) {
            Response::validationError([
                'gatewayKey' => ['Ibeepay gateway is not active']
            ]);
        }

        $supportContent = json_encode([
            'name' => trim((string)($data['name'] ?? '')),
            'dob' => trim((string)($data['dob'] ?? '')),
            'email' => trim((string)($data['email'] ?? '')),
            'phone' => trim((string)($data['phone'] ?? '')),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $result = $this->createDepositRequest($client, $data, $supportContent, (int)$gateway['id']);

        $depositUrl = (string)($this->getIbeepayDepositBaseUrl() ?? '');
        $postData = $this->buildIbeepayPostData(
            $gateway,
            $result['deposit'],
            $data,
            $result['amount'],
            $result['tradingAccountId'],
            $client
        );
        $this->logDepositRedirectAttempt(
            'ibeepay',
            $gateway,
            $result['deposit'],
            $depositUrl,
            $postData
        );
        $this->applyPostRedirectResponseData(
            $result['responseData'],
            $depositUrl,
            $postData
        );

        Response::created($result['responseData'], 'Deposit request created successfully');
    }

    private function createDepositRequest($client, $data, $supportContent = null, $gatewaySettingId = null, bool $allowAutoApproval = true) {
        if (!isset($data['amount']) && isset($data['total'])) {
            $data['amount'] = $data['total'];
        }
        Validator::make($data, [
            'amount' => 'required|numeric',
            'currency' => 'required|string',
            'tradingAccountId' => 'numeric'
        ]);

        $amount = (float)$data['amount'];
        $tradingAccountId = !empty($data['tradingAccountId']) ? (int)$data['tradingAccountId'] : null;
        if ($tradingAccountId) {
            $tradingAccount = $this->tradingAccountModel->findById($tradingAccountId);
            if (!$tradingAccount || (int)$tradingAccount['userId'] !== (int)$client['userId']) {
                Response::validationError([
                    'tradingAccountId' => ['Invalid trading account']
                ]);
            }
        }

        $quotePayload = $this->resolveGatewayQuote(
            'deposit',
            $gatewaySettingId,
            $amount,
            $data
        );

        $platformSnapshot = $this->buildDepositCreateSnapshot($tradingAccountId, $amount);

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $sql = "CALL spCreateDeposit(:userId, :tradingAccountId, :amount, :amountScale, :platformAmount, :displayUnit, :currencyCode, :exchangeRate, :platformFee, :quotedAmount, :ipAddress, :gatewaySettingId, :supportContent, :cryptoAddressId, :fiatCode, :cryptoCode, :network, @transactionId, @depositId)";

        $db = Database::getInstance();
        $db->query($sql, [
            'userId' => $client['userId'],
            'tradingAccountId' => $tradingAccountId,
            'amount' => $amount,
            'amountScale' => $platformSnapshot['amountScale'] ?? null,
            'platformAmount' => $platformSnapshot['platformAmount'] ?? null,
            'displayUnit' => $platformSnapshot['displayUnit'] ?? null,
            'currencyCode' => $quotePayload['currencyCode'],
            'exchangeRate' => $quotePayload['exchangeRate'],
            'platformFee' => $quotePayload['platformFee'],
            'quotedAmount' => $quotePayload['quotedAmount'],
            'ipAddress' => $ipAddress,
            'gatewaySettingId' => $gatewaySettingId !== null ? (int)$gatewaySettingId : null,
            'supportContent' => $supportContent,
            'cryptoAddressId' => null,
            'fiatCode' => null,
            'cryptoCode' => null,
            'network' => null,
        ]);

        $result = $db->fetchOne("SELECT @transactionId as transactionId, @depositId as depositId");
        if (!$result || !$result['depositId']) {
            Response::serverError('Failed to create deposit');
        }

        $deposit = $this->depositModel->getDepositDetails($result['depositId']);

        try {
            $this->notifyAdminsOfDepositCreated($deposit);
        } catch (Exception $e) {
            Logger::error('Failed to create admin deposit notice: ' . $e->getMessage());
        }

        // 自动审批通过后通知由 markDepositSuccess 内部发送，这里只需重新拉一次最新状态
        $autoApproved = $allowAutoApproval && $this->tryAutoApproveDeposit((int) $result['depositId']);
        if ($autoApproved) {
            $deposit = $this->depositModel->getDepositDetails($result['depositId']);
        }

        $responseData = $this->buildDepositResponseData($client['userId'], $deposit);

        return [
            'responseData' => $responseData,
            'deposit' => $deposit,
            'amount' => $amount,
            'tradingAccountId' => $tradingAccountId
        ];
    }

    private function resolveGatewayQuote($transactionType, $gatewaySettingId, $amount, array $data, $fallbackGatewayType = 'fiat') {
        if ($gatewaySettingId === null || (int)$gatewaySettingId <= 0) {
            Response::validationError([
                'gatewaySettingId' => ['gatewaySettingId is required']
            ]);
        }

        $gateway = $this->gatewayModel->findById((int)$gatewaySettingId);
        if (!$gateway || empty($gateway['isEnabled'])) {
            Response::validationError([
                'gatewaySettingId' => ['Selected gateway is not available']
            ]);
        }

        $gatewayType = strtolower((string)($gateway['type'] ?? $fallbackGatewayType));
        $amountDecimalPlaces = $this->resolveGatewayAmountDecimalPlaces($gateway);

        if (!empty($gateway['isMultiCurrency'])) {
            // 多币种网关（如 AEON）：provider 自己处理币种，我们只按原本币种 USD 按 1:1 记账；
            // 不校验、不要求 currency，汇率固定 1
            $currencyCode = 'USD';
            $expectedExchangeRate = 1.0;
        } else {
            $currencyCode = strtoupper(trim((string)($data['currency'] ?? '')));
            if ($currencyCode === '') {
                Response::validationError([
                    'currency' => ['currency is required']
                ]);
            }

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
        }

        $feeResult = $this->gatewayFeeModel->calculateForTransaction((int)$gatewaySettingId, $transactionType, $amount);
        $fundingSettings = $feeResult['feeSettings'] ?? [];
        $minDeposit = isset($fundingSettings['minDeposit']) && $fundingSettings['minDeposit'] !== null
            ? (float)$fundingSettings['minDeposit']
            : null;
        $maxDeposit = isset($fundingSettings['maxDeposit']) && $fundingSettings['maxDeposit'] !== null
            ? (float)$fundingSettings['maxDeposit']
            : null;

        if ($minDeposit !== null && $amount < $minDeposit) {
            Response::validationError([
                'amount' => ['Amount is below this gateway minimum deposit']
            ]);
        }

        if ($maxDeposit !== null && $amount > $maxDeposit) {
            Response::validationError([
                'amount' => ['Amount exceeds this gateway maximum deposit']
            ]);
        }

        $platformFee = round((float)$feeResult['platformFee'], 2);
        $chargeToClient = (bool)($feeResult['appliedRule']['chargeToClient'] ?? true);

        $quotedBaseAmount = $chargeToClient ? ($amount + $platformFee) : $amount;
        $quotedAmount = round($quotedBaseAmount * $expectedExchangeRate, $amountDecimalPlaces);

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
            'quotedAmount' => $quotedAmount
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

    private function buildDepositResponseData($userId, $deposit) {
        $db = Database::getInstance();
        $walletBreakdown = $this->walletBalanceService->getBreakdown((int)$userId);

        $todayDepositResult = $db->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) as todayTotal FROM deposits WHERE userId = :userId AND DATE(requestedAt) = CURDATE()",
            ['userId' => $userId]
        );
        $todayDeposits = (float)($todayDepositResult['todayTotal'] ?? 0);

        $monthlyDepositResult = $db->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) as monthlyTotal FROM deposits WHERE userId = :userId AND YEAR(requestedAt) = YEAR(CURDATE()) AND MONTH(requestedAt) = MONTH(CURDATE())",
            ['userId' => $userId]
        );
        $monthlyDeposits = (float)($monthlyDepositResult['monthlyTotal'] ?? 0);

        $responseData = $deposit;
        $responseData['availableBalance'] = round((float)$walletBreakdown['availableBalance'], 2);
        $responseData['totalDeposits'] = round((float)$walletBreakdown['totalDeposits'], 2);
        $responseData['totalWithdrawals'] = round((float)$walletBreakdown['totalWithdrawals'], 2);
        $responseData['depositStats'] = [
            'todayDeposits' => round($todayDeposits, 2),
            'monthlyDeposits' => round($monthlyDeposits, 2)
        ];

        return $responseData;
    }

    private function findEnabledIbeepayGateway() {
        $gateway = $this->gatewayModel->findByKeyWithSecrets("ibeepay");
        return ($gateway && $gateway['isEnabled']) ? $gateway : null;
    }

    private function isIbeepayGateway($gateway) {
        if (!$gateway) {
            return false;
        }

        $gatewayKey = strtolower(trim((string)($gateway['gatewayKey'] ?? '')));
        return $gatewayKey === 'ibeepay';
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

    private function normalizeXLinkDepositSupportData(array $supportData): array {
        $accountName = trim((string)($supportData['account_name'] ?? ''));
        if ($accountName === '') {
            $accountName = trim(
                trim((string)($supportData['customer_name'] ?? '')) . ' ' .
                trim((string)($supportData['customer_lastname'] ?? ''))
            );
        }

        return [
            'account_name' => $accountName,
        ];
    }

    /**
     * Create an X-Link hosted PAYIN payment link after the CRM deposit has been
     * saved as pending. The provider callback, not this initiation response,
     * remains the settlement authority.
     */
    private function requestXLinkDeposit(array $deposit, array &$responseData, array $gateway, array $supportData): void {
        $depositId = (int)($deposit['id'] ?? 0);
        $transactionId = trim((string)($deposit['transactionId'] ?? ''));
        if ($depositId <= 0 || $transactionId === '') {
            return;
        }

        $service = new XLinkService($gateway);
        if (!$service->isConfigured()) {
            $this->rejectXLinkDeposit($deposit, 'X-Link gateway is not configured.');
            Response::serverError('X-Link gateway is not configured.');
        }

        $accountName = trim((string)($supportData['account_name'] ?? ''));
        if ($accountName === '') {
            $accountName = trim(
                trim((string)($supportData['customer_name'] ?? '')) . ' ' .
                trim((string)($supportData['customer_lastname'] ?? ''))
            );
        }
        if ($accountName === '') {
            $this->rejectXLinkDeposit($deposit, 'X-Link account_name is required.');
            Response::validationError([
                'account_name' => ['account_name is required']
            ]);
        }

        try {
            $amount = XLinkService::normalizeKrwAmount($deposit['quotedAmount'] ?? null);
        } catch (Throwable $exception) {
            $message = 'Invalid X-Link KRW quoted amount.';
            $this->rejectXLinkDeposit($deposit, $message);
            Logger::error($message, [
                'depositId' => $depositId,
                'transactionId' => $transactionId,
                'error' => $exception->getMessage(),
            ]);
            Response::serverError($message);
        }

        $methodRequest = [
            'shop_id' => $service->getShopId(),
            'operation_type' => 'PAYIN',
            'amount' => $amount,
            'currency' => XLinkService::DEFAULT_CURRENCY,
        ];
        $methodStartedMs = (int)round(microtime(true) * 1000);
        $methodLogId = $this->requestLogService->beginAttempt([
            'provider' => XLinkService::PROVIDER_KEY,
            'environment' => $this->requestLogService->resolveEnvironment(null, $gateway),
            'transactionType' => 'deposit',
            'operation' => 'payment_method_types',
            'deliveryMode' => 'server_http',
            'depositId' => $depositId,
            'localOrderId' => $transactionId,
            'amount' => $amount,
            'currencyCode' => XLinkService::DEFAULT_CURRENCY,
            'requestMethod' => 'GET',
            'endpointPath' => '/payment-method-types',
            'requestPayload' => $methodRequest,
        ]);
        if ($methodLogId) {
            $this->requestLogService->markSent($methodLogId);
        }

        try {
            $paymentMethod = $service->getPaymentMethodType('PAYIN', $amount, XLinkService::DEFAULT_CURRENCY);
        } catch (XLinkApiException $exception) {
            $status = $exception->isAmbiguous()
                ? PaymentProcessorRequestLogService::STATUS_UNKNOWN
                : PaymentProcessorRequestLogService::STATUS_FAILED;
            if ($methodLogId) {
                $this->requestLogService->completeAttempt($methodLogId, $status, [
                    'responsePayload' => $exception->getResponse(),
                    'errorMessage' => $exception->getMessage(),
                    'durationMs' => max(0, (int)round(microtime(true) * 1000) - $methodStartedMs),
                ]);
            }
            $this->handleXLinkDepositInitiationFailure($deposit, $exception, 'payment-method discovery');
        } catch (Throwable $exception) {
            if ($methodLogId) {
                $this->requestLogService->completeAttempt($methodLogId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'errorMessage' => $exception->getMessage(),
                    'durationMs' => max(0, (int)round(microtime(true) * 1000) - $methodStartedMs),
                ]);
            }
            $this->rejectXLinkDeposit($deposit, 'X-Link payment-method discovery failed.');
            Logger::error('X-Link payment-method discovery failed.', [
                'depositId' => $depositId,
                'transactionId' => $transactionId,
                'error' => $exception->getMessage(),
            ]);
            Response::serverError('X-Link payment-method discovery failed.');
        }

        $paymentMethodTypeId = trim((string)($paymentMethod['id'] ?? $paymentMethod['payment_method_type_id'] ?? ''));
        if ($paymentMethodTypeId === '') {
            if ($methodLogId) {
                $this->requestLogService->completeAttempt($methodLogId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'responsePayload' => XLinkService::sanitizeForLog($paymentMethod),
                    'errorMessage' => 'Missing payment_method_type_id',
                    'durationMs' => max(0, (int)round(microtime(true) * 1000) - $methodStartedMs),
                ]);
            }
            $this->rejectXLinkDeposit($deposit, 'X-Link did not return a payment method.');
            Response::serverError('X-Link did not return a payment method.');
        }

        if ($methodLogId) {
            $this->requestLogService->completeAttempt($methodLogId, PaymentProcessorRequestLogService::STATUS_ACCEPTED, [
                'providerOrderId' => $paymentMethodTypeId,
                'responsePayload' => XLinkService::sanitizeForLog($paymentMethod),
                'durationMs' => max(0, (int)round(microtime(true) * 1000) - $methodStartedMs),
            ]);
        }

        $payload = [
            'payment_method_type_id' => $paymentMethodTypeId,
            'operation_type' => 'PAYIN',
            'shop_id' => $service->getShopId(),
            'operation_number' => $transactionId,
            'payer_id' => 'utrada-client-' . (int)($deposit['userId'] ?? 0),
            'amount' => (int)$amount,
            'currency' => XLinkService::DEFAULT_CURRENCY,
            'callback_url' => $service->getCallbackUrl(),
            'return_url' => $this->buildXLinkDepositReturnUrl($service->getReturnBaseUrl(), $transactionId),
            'metadata' => [
                'account_name' => $accountName,
            ],
        ];

        $linkStartedMs = (int)round(microtime(true) * 1000);
        $linkLogId = $this->requestLogService->beginAttempt([
            'provider' => XLinkService::PROVIDER_KEY,
            'environment' => $this->requestLogService->resolveEnvironment(null, $gateway),
            'transactionType' => 'deposit',
            'operation' => 'create_payment_link',
            'deliveryMode' => 'server_http',
            'depositId' => $depositId,
            'localOrderId' => $transactionId,
            'amount' => $amount,
            'currencyCode' => XLinkService::DEFAULT_CURRENCY,
            'requestMethod' => 'POST',
            'endpointPath' => '/public/payment-links',
            'requestPayload' => $payload,
        ]);
        if ($linkLogId) {
            $this->requestLogService->markSent($linkLogId);
        }

        try {
            $apiResponse = $service->createPaymentLink($payload);
        } catch (XLinkApiException $exception) {
            $status = $exception->isAmbiguous()
                ? PaymentProcessorRequestLogService::STATUS_UNKNOWN
                : PaymentProcessorRequestLogService::STATUS_FAILED;
            if ($linkLogId) {
                $this->requestLogService->completeAttempt($linkLogId, $status, [
                    'responsePayload' => $exception->getResponse(),
                    'errorMessage' => $exception->getMessage(),
                    'durationMs' => max(0, (int)round(microtime(true) * 1000) - $linkStartedMs),
                ]);
            }
            $this->handleXLinkDepositInitiationFailure($deposit, $exception, 'payment-link creation');
        } catch (Throwable $exception) {
            if ($linkLogId) {
                $this->requestLogService->completeAttempt($linkLogId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'errorMessage' => $exception->getMessage(),
                    'durationMs' => max(0, (int)round(microtime(true) * 1000) - $linkStartedMs),
                ]);
            }
            $this->rejectXLinkDeposit($deposit, 'X-Link payment-link creation failed.');
            Logger::error('X-Link payment-link creation failed.', [
                'depositId' => $depositId,
                'transactionId' => $transactionId,
                'error' => $exception->getMessage(),
            ]);
            Response::serverError('X-Link payment-link creation failed.');
        }

        $sanitizedResponse = XLinkService::sanitizeForLog($apiResponse);
        $sessionId = trim((string)($apiResponse['session_id'] ?? $apiResponse['sessionId'] ?? ''));
        $paymentUrl = trim((string)($apiResponse['payment_url'] ?? $apiResponse['paymentUrl'] ?? ''));
        $expiresAt = $this->resolveXLinkDepositExpiresAt($apiResponse);
        $durationMs = max(0, (int)round(microtime(true) * 1000) - $linkStartedMs);

        $update = [
            'gatewayResponse' => json_encode($sanitizedResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'expiredAt' => $expiresAt,
        ];
        if ($sessionId !== '') {
            $update['gatewayTransactionId'] = $sessionId;
        }
        if ($paymentUrl !== '') {
            $update['paymentUrl'] = $paymentUrl;
        }
        try {
            $this->depositModel->update($depositId, $update);
        } catch (Throwable $exception) {
            if ($linkLogId) {
                $this->requestLogService->completeAttempt($linkLogId, PaymentProcessorRequestLogService::STATUS_UNKNOWN, [
                    'providerOrderId' => $sessionId !== '' ? $sessionId : null,
                    'responsePayload' => $sanitizedResponse,
                    'errorMessage' => 'Unable to persist X-Link payment-link response: ' . $exception->getMessage(),
                    'durationMs' => $durationMs,
                ]);
            }
            Logger::error('Failed to persist X-Link deposit response.', [
                'depositId' => $depositId,
                'transactionId' => $transactionId,
                'error' => $exception->getMessage(),
            ]);
            Response::serverError('X-Link payment request needs reconciliation before it can be continued.');
        }

        if ($sessionId === '' || $paymentUrl === '') {
            if ($linkLogId) {
                $this->requestLogService->completeAttempt($linkLogId, PaymentProcessorRequestLogService::STATUS_UNKNOWN, [
                    'providerOrderId' => $sessionId !== '' ? $sessionId : null,
                    'responsePayload' => $sanitizedResponse,
                    'errorMessage' => 'Missing session_id or payment_url',
                    'durationMs' => $durationMs,
                ]);
            }
            Logger::warning('X-Link payment-link response is incomplete; leaving deposit pending.', [
                'depositId' => $depositId,
                'transactionId' => $transactionId,
                'hasSessionId' => $sessionId !== '',
                'hasPaymentUrl' => $paymentUrl !== '',
            ]);
            Response::serverError('X-Link payment request needs reconciliation before it can be continued.');
        }

        if ($linkLogId) {
            $this->requestLogService->completeAttempt($linkLogId, PaymentProcessorRequestLogService::STATUS_ACCEPTED, [
                'providerOrderId' => $sessionId,
                'responsePayload' => $sanitizedResponse,
                'durationMs' => $durationMs,
            ]);
        }

        $responseData['status'] = 'pending';
        $responseData['gatewayTransactionId'] = $sessionId;
        $responseData['paymentUrl'] = $paymentUrl;
        $responseData['redirect'] = 'get';
        $responseData['redirectUrl'] = $paymentUrl;
        $responseData['xlink'] = [
            'status' => 'pending',
            'sessionId' => $sessionId,
        ];
    }

    private function buildXLinkDepositReturnUrl(string $returnBaseUrl, string $transactionId): string {
        $returnBaseUrl = rtrim(trim($returnBaseUrl), '/');
        if ($returnBaseUrl === '' || $transactionId === '') {
            return '';
        }

        return $returnBaseUrl
            . '/#/client/transactions/pending?type=deposit&id='
            . rawurlencode($transactionId);
    }

    private function resolveXLinkDepositExpiresAt(array $apiResponse): string {
        $raw = trim((string)($apiResponse['expires_at'] ?? $apiResponse['expiresAt'] ?? ''));
        if ($raw !== '') {
            $timestamp = strtotime($raw);
            if ($timestamp !== false) {
                return gmdate('Y-m-d H:i:s', $timestamp);
            }
        }

        return gmdate('Y-m-d H:i:s', time() + 900);
    }

    private function handleXLinkDepositInitiationFailure(array $deposit, XLinkApiException $exception, string $operation): void {
        $depositId = (int)($deposit['id'] ?? 0);
        $transactionId = trim((string)($deposit['transactionId'] ?? ''));
        if ($exception->isAmbiguous()) {
            Logger::warning('X-Link ' . $operation . ' result is unknown; leaving deposit pending.', [
                'depositId' => $depositId,
                'transactionId' => $transactionId,
                'httpStatus' => $exception->getHttpStatus(),
            ]);
            Response::serverError('X-Link deposit request status is unknown. Please check transaction history before retrying.');
        }

        $this->rejectXLinkDeposit($deposit, 'X-Link ' . $operation . ' was rejected.');
        Logger::error('X-Link ' . $operation . ' was rejected.', [
            'depositId' => $depositId,
            'transactionId' => $transactionId,
            'httpStatus' => $exception->getHttpStatus(),
        ]);
        Response::serverError('X-Link deposit request was rejected.');
    }

    private function rejectXLinkDeposit(array $deposit, string $reason): void {
        $status = strtolower(trim((string)($deposit['status'] ?? '')));
        if (!in_array($status, ['unpaid', 'pending', 'processing'], true)) {
            return;
        }

        $this->paymentService->markDepositProviderTerminal(
            $deposit,
            'cancelled',
            $reason,
            'init_failed',
            XLinkService::PROVIDER_KEY
        );
    }

    /**
     * Vexora payment_method -> channelCode/wayCode (Korea + Cambodia).
     */
    private function resolveVexoraWayCodes(array $supportData, array $gatewayConfig, bool $isCambodia = false): array {
        if ($isCambodia) {
            $map = [
                'khqr' => ['KHQR', 'KHQR'],
                'va' => ['BAKONG', 'VA'],
                'bakong' => ['BAKONG', 'VA'],
            ];
            $defaultChannel = 'KHQR';
            $defaultWay = 'KHQR';
        } else {
            $map = [
                'kakaopay' => ['EWALLET', 'KAKAOPAY'],
                'tosspay' => ['EWALLET', 'TOSSPAY'],
                'samsungpay' => ['EWALLET', 'SAMSUNGPAY'],
                'naverpay' => ['EWALLET', 'NAVERPAY'],
                'card' => ['DOMESTIC_CARD', 'KOREANCARD'],
                'va' => ['NET_BANKING', 'VA'],
                'bank_transfer' => ['NET_BANKING', 'BANK_TRANSFER'],
            ];
            $defaultChannel = 'EWALLET';
            $defaultWay = 'KAKAOPAY';
        }

        $method = strtolower(trim((string)($supportData['payment_method'] ?? '')));
        if ($method !== '' && isset($map[$method])) {
            return $map[$method];
        }

        return [
            strtoupper(trim((string)($gatewayConfig['default_channel_code'] ?? $defaultChannel))),
            strtoupper(trim((string)($gatewayConfig['default_way_code'] ?? $defaultWay)))
        ];
    }

    /**
     * Create Vexora checkout (Korea KRW or Cambodia KHR/USD).
     * Returns paymentLink as redirect=get, or VA details via vexoraPayInfo.
     */
    private function requestVexoraDeposit(array $deposit, array &$responseData, array $gateway, array $supportData): void {
        $depositId = (int)($deposit['id'] ?? 0);
        $transactionId = trim((string)($deposit['transactionId'] ?? ''));
        if ($depositId <= 0 || $transactionId === '') {
            return;
        }

        $service = new VexoraService($gateway);
        $isCambodia = $service->isCambodia();
        if (!$service->isConfigured()) {
            $hint = $isCambodia
                ? 'need merchantNo, secret, and base_url'
                : 'need merchantNo, secret, base_url, and subMerchantNo';
            Response::serverError('Vexora gateway is not configured (' . $hint . ')');
        }

        $gatewayConfig = $service->getConfig();
        list($channelCode, $wayCode) = $this->resolveVexoraWayCodes($supportData, $gatewayConfig, $isCambodia);

        if ($isCambodia) {
            $mobile = VexoraService::normalizeCambodiaMobile($supportData['phone'] ?? '');
            if (!VexoraService::isValidCambodiaMobile($mobile)) {
                Response::validationError([
                    'phone' => ['Use 8 digits with no country code, or 9 digits starting with 0 (e.g. 12345678 or 012345678)']
                ]);
            }
        } else {
            $mobile = VexoraService::normalizeKoreanMobile($supportData['phone'] ?? '');
            if (strlen($mobile) < 10 || strlen($mobile) > 11) {
                Response::validationError([
                    'phone' => ['A valid Korean mobile number (010XXXXXXXX) is required for Vexora deposits']
                ]);
            }
        }

        $firstName = trim((string)($supportData['first_name'] ?? ''));
        $lastName = trim((string)($supportData['last_name'] ?? ''));
        $email = trim((string)($supportData['email'] ?? ''));
        if ($firstName === '' || $lastName === '' || $email === '') {
            Response::validationError([
                'supportData' => ['first_name, last_name and email are required for Vexora deposits']
            ]);
        }

        $tradeNo = VexoraService::buildTradeNo($transactionId);
        $payload = [
            'tradeNo' => $tradeNo,
            'amount' => $service->formatAmount($deposit['quotedAmount'] ?? 0),
            'mobile' => $mobile,
            'firstname' => $firstName,
            'lastname' => $lastName,
            'email' => $email,
            'channelCode' => $channelCode,
            'wayCode' => $wayCode,
            'notifyUrl' => $this->buildVexoraBackendCallbackUrl('deposit'),
            'returnUrl' => $this->buildDepositCallbackUrl('pending', $deposit),
        ];

        if ($isCambodia) {
            $payload['userId'] = (string)(int)($deposit['userId'] ?? 0);
            $payload['remark'] = 'checkout_' . $tradeNo;
        } else {
            $payload['subMerchantNo'] = $service->getSubMerchantNo();
            $payload['ipAddress'] = ClientIp::getClientIp();

            $dateOfBirth = trim((string)($supportData['dob'] ?? $supportData['date_of_birth'] ?? ''));
            if ($wayCode === 'VA') {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth)) {
                    Response::validationError([
                        'dob' => ['Date of birth (YYYY-MM-DD) is required for virtual account deposits']
                    ]);
                }
                $payload['dateOfBirth'] = $dateOfBirth;
            }
        }

        $startedMs = (int) round(microtime(true) * 1000);
        $logId = $this->requestLogService->beginAttempt([
            'provider' => 'vexora',
            'environment' => $this->requestLogService->resolveEnvironment(null, $gateway),
            'transactionType' => 'deposit',
            'operation' => 'create',
            'deliveryMode' => 'server_http',
            'depositId' => $depositId,
            'localOrderId' => $transactionId,
            'amount' => $deposit['quotedAmount'] ?? $deposit['amount'] ?? null,
            'currencyCode' => $deposit['currencyCode'] ?? null,
            'requestMethod' => 'POST',
            'endpointPath' => '/checkout',
            'requestPayload' => $payload,
        ]);
        if ($logId) {
            $this->requestLogService->markSent($logId);
        }

        try {
            $apiResponse = $service->createCheckout($payload);
        } catch (Throwable $e) {
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'errorMessage' => $e->getMessage(),
                    'durationMs' => max(0, (int) round(microtime(true) * 1000) - $startedMs),
                ]);
            }
            Logger::error('Vexora createCheckout failed: ' . $e->getMessage(), [
                'depositId' => $depositId,
                'transactionId' => $transactionId,
            ]);
            Response::serverError('Vexora deposit request failed: ' . $e->getMessage());
        }

        $durationMs = max(0, (int) round(microtime(true) * 1000) - $startedMs);
        $platFormTradeNo = trim((string)($apiResponse['data']['platFormTradeNo'] ?? ''));
        $providerStatus = trim((string)($apiResponse['code'] ?? $apiResponse['data']['status'] ?? ''));

        $update = [
            'gatewayResponse' => json_encode($apiResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        if ($platFormTradeNo !== '') {
            $update['gatewayTransactionId'] = $platFormTradeNo;
        }
        try {
            $this->depositModel->update($depositId, $update);
        } catch (Throwable $e) {
            Logger::error('Failed to persist Vexora deposit response: ' . $e->getMessage(), [
                'depositId' => $depositId,
            ]);
        }

        if (VexoraService::isSystemException($apiResponse)) {
            $message = trim((string)($apiResponse['msg'] ?? $apiResponse['data']['message'] ?? 'Vexora system exception'));
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_UNKNOWN, [
                    'providerOrderId' => $platFormTradeNo !== '' ? $platFormTradeNo : null,
                    'providerStatus' => $providerStatus !== '' ? $providerStatus : null,
                    'responsePayload' => $apiResponse,
                    'errorMessage' => $message,
                    'durationMs' => $durationMs,
                ]);
            }
            Logger::error('Vexora checkout system exception (code=5000); leaving deposit pending', [
                'depositId' => $depositId,
                'transactionId' => $transactionId,
                'msg' => $message,
            ]);
            $responseData['vexora'] = [
                'success' => true,
                'status' => 'processing',
                'code' => VexoraService::CODE_SYSTEM_EXCEPTION,
                'message' => $message,
                'response' => $apiResponse,
            ];
            return;
        }

        if (!VexoraService::isRequestAccepted($apiResponse)) {
            $message = trim((string)($apiResponse['msg'] ?? $apiResponse['data']['message'] ?? 'unknown error'));
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'providerOrderId' => $platFormTradeNo !== '' ? $platFormTradeNo : null,
                    'providerStatus' => $providerStatus !== '' ? $providerStatus : null,
                    'responsePayload' => $apiResponse,
                    'errorMessage' => $message,
                    'durationMs' => $durationMs,
                ]);
            }
            Logger::error('Vexora checkout rejected', [
                'depositId' => $depositId,
                'code' => (string)($apiResponse['code'] ?? ''),
            ]);
            Response::serverError('Vexora deposit request rejected: ' . $message);
        }

        $businessStatus = trim((string)($apiResponse['data']['status'] ?? ''));
        $mappedStatus = VexoraService::mapStatus($businessStatus);
        if ($mappedStatus === 'failed') {
            $message = trim((string)($apiResponse['data']['message'] ?? $apiResponse['msg'] ?? 'Vexora deposit failed'));
            if ($message === '') {
                $message = 'Vexora deposit failed';
            }
            $statusLabel = $businessStatus !== '' ? $businessStatus : 'unknown';
            $clientMessage = 'Vexora deposit failed (status ' . $statusLabel . '): ' . $message;

            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'providerOrderId' => $platFormTradeNo !== '' ? $platFormTradeNo : null,
                    'providerStatus' => $businessStatus !== '' ? $businessStatus : $providerStatus,
                    'responsePayload' => $apiResponse,
                    'errorMessage' => $clientMessage,
                    'durationMs' => $durationMs,
                ]);
            }

            try {
                $this->paymentService->markDepositProviderTerminal(
                    $deposit,
                    'cancelled',
                    $clientMessage,
                    $businessStatus !== '' ? $businessStatus : 'failed',
                    'vexora'
                );
            } catch (Throwable $e) {
                Logger::error('Failed to cancel Vexora deposit after provider failure: ' . $e->getMessage(), [
                    'depositId' => $depositId,
                    'transactionId' => $transactionId,
                ]);
            }

            if (stripos($message, 'mobile') !== false) {
                Response::validationError(['phone' => [$message]], $clientMessage);
            }

            Response::error($clientMessage, 400);
        }

        $paymentLink = trim((string)($apiResponse['data']['paymentLink'] ?? ''));
        $payInfo = $apiResponse['data']['payInfo'] ?? null;
        $payInfoVA = $apiResponse['data']['payInfoVA'] ?? $apiResponse['data']['payInfoVa'] ?? null;

        $hasVaInfo = is_array($payInfoVA) && !empty($payInfoVA);
        $hasPayInfoArray = is_array($payInfo) && !empty($payInfo);
        $hasPayInfoString = is_string($payInfo) && trim($payInfo) !== '';

        if ($paymentLink === '' && !$hasVaInfo && !$hasPayInfoArray && !$hasPayInfoString) {
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'providerOrderId' => $platFormTradeNo !== '' ? $platFormTradeNo : null,
                    'providerStatus' => $providerStatus !== '' ? $providerStatus : null,
                    'responsePayload' => $apiResponse,
                    'errorMessage' => 'Missing paymentLink, payInfoVA, and payInfo',
                    'durationMs' => $durationMs,
                ]);
            }
            Logger::error('Vexora checkout response missing paymentLink and pay info', [
                'depositId' => $depositId,
                'transactionId' => $transactionId,
            ]);
            Response::serverError('Vexora deposit created but payment link is missing');
        }

        if ($logId) {
            $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_ACCEPTED, [
                'providerOrderId' => $platFormTradeNo !== '' ? $platFormTradeNo : null,
                'providerStatus' => $providerStatus !== '' ? $providerStatus : null,
                'responsePayload' => $apiResponse,
                'durationMs' => $durationMs,
            ]);
        }

        if ($paymentLink !== '') {
            $responseData['redirect'] = 'get';
            $responseData['redirectUrl'] = $paymentLink;
        }

        if ($hasVaInfo) {
            $responseData['vexoraPayInfo'] = $payInfoVA;
        } elseif ($hasPayInfoArray) {
            $responseData['vexoraPayInfo'] = $payInfo;
        } elseif ($hasPayInfoString && $paymentLink === '') {
            $responseData['vexoraPayInfo'] = ['payInfo' => trim($payInfo)];
        }
    }

    private function buildVexoraBackendCallbackUrl(string $type): string {
        $baseUrl = rtrim((string)($this->appConfig['file_base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            return '';
        }

        $baseUrl = preg_replace('#/index\.php$#i', '', $baseUrl);
        return $baseUrl . '/index.php?path=api/callback/vexora/' . rawurlencode($type);
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

    private function requestFlashPayDeposit(array $deposit, array &$responseData, array $gateway, array $supportData): void {
        $depositId = (int)($deposit['id'] ?? 0);
        $transactionId = trim((string)($deposit['transactionId'] ?? ''));
        if ($depositId <= 0 || $transactionId === '') {
            return;
        }

        $service = new FlashPayService($gateway);
        if (!$service->isConfigured()) {
            Response::serverError('FlashPay gateway is not configured (need mchNo, appId, private key, platform public key, base_url)');
        }

        $notifyUrl = $this->buildFlashPayBackendCallbackUrl('deposit');
        if ($notifyUrl === '') {
            Response::serverError('FlashPay deposit notifyUrl is not configured (file_base_url is empty)');
        }

        $firstName = trim((string)($supportData['first_name'] ?? ''));
        $lastName = trim((string)($supportData['last_name'] ?? ''));
        $fullName = trim(implode(' ', array_filter([$firstName, $lastName], static function ($value) {
            return $value !== '';
        })));
        if ($fullName === '') {
            Response::validationError([
                'supportData' => ['first_name is required for FlashPay deposits']
            ]);
        }

        $mchOrderNo = FlashPayService::buildMchOrderNo($transactionId);
        $amount = FlashPayService::toFlashPayAmountCents($deposit['quotedAmount'] ?? $deposit['amount'] ?? 0);
        $userIden = trim((string)($deposit['userId'] ?? $deposit['clientId'] ?? ''));
        if ($userIden === '') {
            $userIden = (string)$depositId;
        }

        $payload = [
            'mchOrderNo' => $mchOrderNo,
            'wayCode' => $service->getWayCode(),
            'amount' => $amount,
            'currency' => $service->getCurrency(),
            'clientIp' => ClientIp::getClientIp(),
            'userLevel' => 2,
            'userIden' => $userIden,
            'subject' => 'Deposit ' . $mchOrderNo,
            'body' => $fullName,
            'notifyUrl' => $notifyUrl,
            'returnUrl' => $this->buildDepositCallbackUrl('pending', $deposit),
            'channelExtra' => FlashPayService::encodeChannelExtra([
                'firstName' => $fullName
            ]),
        ];

        $startedMs = (int) round(microtime(true) * 1000);
        $logId = $this->requestLogService->beginAttempt([
            'provider' => 'flashpay',
            'environment' => $this->requestLogService->resolveEnvironment(null, $gateway),
            'transactionType' => 'deposit',
            'operation' => 'create',
            'deliveryMode' => 'server_http',
            'depositId' => $depositId,
            'localOrderId' => $transactionId,
            'amount' => $deposit['quotedAmount'] ?? $deposit['amount'] ?? null,
            'currencyCode' => $deposit['currencyCode'] ?? null,
            'requestMethod' => 'POST',
            'endpointPath' => '/api/pay/unifiedOrder',
            'requestPayload' => $payload,
        ]);
        if ($logId) {
            $this->requestLogService->markSent($logId);
        }

        try {
            $apiResponse = $service->createUnifiedOrder($payload);
        } catch (Throwable $e) {
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'errorMessage' => $e->getMessage(),
                    'durationMs' => max(0, (int) round(microtime(true) * 1000) - $startedMs),
                ]);
            }
            Logger::error('FlashPay unifiedOrder failed: ' . $e->getMessage(), [
                'depositId' => $depositId,
                'transactionId' => $transactionId,
            ]);
            Response::serverError('FlashPay deposit request failed: ' . $e->getMessage());
        }

        $durationMs = max(0, (int) round(microtime(true) * 1000) - $startedMs);
        $payOrderId = trim((string)($apiResponse['data']['payOrderId'] ?? ''));
        $providerStatus = trim((string)($apiResponse['code'] ?? $apiResponse['data']['orderState'] ?? $apiResponse['data']['state'] ?? ''));
        $payDataType = strtolower(trim((string)($apiResponse['data']['payDataType'] ?? '')));
        $payData = $apiResponse['data']['payData'] ?? null;

        $update = [
            'gatewayResponse' => json_encode($apiResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        if ($payOrderId !== '') {
            $update['gatewayTransactionId'] = $payOrderId;
        }
        try {
            $this->depositModel->update($depositId, $update);
        } catch (Throwable $e) {
            Logger::error('Failed to persist FlashPay deposit response: ' . $e->getMessage(), [
                'depositId' => $depositId,
            ]);
        }

        if (!FlashPayService::isRequestAccepted($apiResponse)) {
            $message = trim((string)($apiResponse['msg'] ?? 'unknown error'));
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'providerOrderId' => $payOrderId !== '' ? $payOrderId : null,
                    'providerStatus' => $providerStatus !== '' ? $providerStatus : null,
                    'responsePayload' => $apiResponse,
                    'errorMessage' => $message,
                    'durationMs' => $durationMs,
                ]);
            }
            Logger::error('FlashPay unifiedOrder rejected', [
                'depositId' => $depositId,
                'code' => (string)($apiResponse['code'] ?? ''),
            ]);
            Response::serverError('FlashPay deposit request rejected: ' . $message);
        }

        if ($logId) {
            $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_ACCEPTED, [
                'providerOrderId' => $payOrderId !== '' ? $payOrderId : null,
                'providerStatus' => $providerStatus !== '' ? $providerStatus : null,
                'responsePayload' => $apiResponse,
                'durationMs' => $durationMs,
            ]);
        }

        $flashpayPayInfo = [
            'payOrderId' => $payOrderId,
            'payDataType' => $payDataType,
            'payData' => $payData,
            'mchOrderNo' => $mchOrderNo,
        ];

        if ($payDataType === 'payurl' && is_string($payData) && trim($payData) !== '') {
            $responseData['redirect'] = 'get';
            $responseData['redirectUrl'] = trim($payData);
        } elseif ($payDataType === 'codeimgurl' && is_string($payData) && trim($payData) !== '') {
            $flashpayPayInfo['codeImgUrl'] = trim($payData);
        } elseif ($payDataType === 'codeurl' && is_string($payData) && trim($payData) !== '') {
            $flashpayPayInfo['codeUrl'] = trim($payData);
        } elseif ($payDataType === 'bankcard') {
            $normalizedBankcard = FlashPayService::normalizeBankcardPayData($payData);
            if ($normalizedBankcard !== []) {
                $flashpayPayInfo = array_merge($flashpayPayInfo, $normalizedBankcard);
            }
        } elseif ($payDataType === 'form' && (is_string($payData) || is_array($payData))) {
            $flashpayPayInfo['form'] = $payData;
        }

        $responseData['flashpayPayInfo'] = $flashpayPayInfo;
        $responseData['flashpay'] = [
            'success' => true,
            'status' => 'processing',
            'payOrderId' => $payOrderId,
            'payDataType' => $payDataType,
        ];
    }

    private function buildFlashPayBackendCallbackUrl(string $type): string {
        $baseUrl = rtrim((string)($this->appConfig['file_base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            return '';
        }

        $baseUrl = preg_replace('#/index\.php$#i', '', $baseUrl);
        return $baseUrl . '/api/callback/flashpay/' . rawurlencode($type);
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

    private function requestCvPayDeposit(array $deposit, array &$responseData, array $gateway, array $supportData): void {
        $depositId = (int)($deposit['id'] ?? 0);
        $transactionId = trim((string)($deposit['transactionId'] ?? ''));
        if ($depositId <= 0 || $transactionId === '') {
            return;
        }

        $service = new CvPayService($gateway);
        if (!$service->isConfigured()) {
            Response::serverError('CVPay gateway is not configured (need mchNo, appId, appKey, base_url)');
        }

        $notifyUrl = $this->buildCvPayBackendCallbackUrl('deposit');
        if ($notifyUrl === '') {
            Response::serverError('CVPay deposit notifyUrl is not configured (file_base_url is empty)');
        }

        $phone = trim((string)($supportData['phone'] ?? ''));
        $email = trim((string)($supportData['email'] ?? ''));
        if ($phone === '' && $email === '') {
            Response::validationError([
                'supportData' => ['phone or email is required for CVPay deposits']
            ]);
        }

        $buyerName = strtoupper(trim((string)($supportData['buyer_name'] ?? '')));
        $mchOrderNo = CvPayService::buildMchOrderNo($transactionId);
        $amount = CvPayService::toCvPayAmount($deposit['quotedAmount'] ?? $deposit['amount'] ?? 0);
        $buyerCode = trim((string)($deposit['userId'] ?? $deposit['clientId'] ?? ''));
        if ($buyerCode === '') {
            $buyerCode = (string)$depositId;
        }

        $payload = [
            'mchOrderNo' => $mchOrderNo,
            'wayCode' => $service->getWayCode(),
            'amount' => $amount,
            'currency' => $service->getCurrency(),
            'notifyUrl' => $notifyUrl,
            'buyerCode' => $buyerCode,
        ];
        $returnUrl = $this->buildCvPayDepositReturnUrl($deposit);
        if ($returnUrl !== '') {
            $payload['returnUrl'] = $returnUrl;
        }
        if ($buyerName !== '') {
            $payload['buyerName'] = $buyerName;
        }
        if ($phone !== '') {
            $payload['buyerPhone'] = $phone;
        }
        if ($email !== '') {
            $payload['buyerEmail'] = $email;
        }

        $startedMs = (int) round(microtime(true) * 1000);
        $logId = $this->requestLogService->beginAttempt([
            'provider' => 'cvpay',
            'environment' => $this->requestLogService->resolveEnvironment(null, $gateway),
            'transactionType' => 'deposit',
            'operation' => 'create',
            'deliveryMode' => 'server_http',
            'depositId' => $depositId,
            'localOrderId' => $transactionId,
            'amount' => $deposit['quotedAmount'] ?? $deposit['amount'] ?? null,
            'currencyCode' => $deposit['currencyCode'] ?? null,
            'requestMethod' => 'POST',
            'endpointPath' => '/api/pay/create',
            'requestPayload' => $payload,
        ]);
        if ($logId) {
            $this->requestLogService->markSent($logId);
        }

        try {
            $apiResponse = $service->createPayment($payload);
        } catch (Throwable $e) {
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'errorMessage' => $e->getMessage(),
                    'durationMs' => max(0, (int) round(microtime(true) * 1000) - $startedMs),
                ]);
            }
            Logger::error('CVPay pay/create failed: ' . $e->getMessage(), [
                'depositId' => $depositId,
                'transactionId' => $transactionId,
            ]);
            Response::serverError('CVPay deposit request failed: ' . $e->getMessage());
        }

        $durationMs = max(0, (int) round(microtime(true) * 1000) - $startedMs);
        $payOrderId = trim((string)($apiResponse['data']['payOrderId'] ?? ''));
        $providerStatus = trim((string)($apiResponse['code'] ?? $apiResponse['data']['orderState'] ?? $apiResponse['data']['state'] ?? ''));
        $payDataType = strtoupper(trim((string)($apiResponse['data']['payDataType'] ?? '')));
        $payData = $apiResponse['data']['payData'] ?? null;

        $update = [
            'gatewayResponse' => json_encode($apiResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        if ($payOrderId !== '') {
            $update['gatewayTransactionId'] = $payOrderId;
        }
        try {
            $this->depositModel->update($depositId, $update);
        } catch (Throwable $e) {
            Logger::error('Failed to persist CVPay deposit response: ' . $e->getMessage(), [
                'depositId' => $depositId,
            ]);
        }

        if (!CvPayService::isRequestAccepted($apiResponse)) {
            $message = trim((string)($apiResponse['msg'] ?? $apiResponse['data']['errMsg'] ?? 'unknown error'));
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'providerOrderId' => $payOrderId !== '' ? $payOrderId : null,
                    'providerStatus' => $providerStatus !== '' ? $providerStatus : null,
                    'responsePayload' => $apiResponse,
                    'errorMessage' => $message,
                    'durationMs' => $durationMs,
                ]);
            }
            Logger::error('CVPay pay/create rejected', [
                'depositId' => $depositId,
                'code' => (string)($apiResponse['code'] ?? ''),
            ]);
            Response::serverError('CVPay deposit request rejected: ' . $message);
        }

        if ($logId) {
            $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_ACCEPTED, [
                'providerOrderId' => $payOrderId !== '' ? $payOrderId : null,
                'providerStatus' => $providerStatus !== '' ? $providerStatus : null,
                'responsePayload' => $apiResponse,
                'durationMs' => $durationMs,
            ]);
        }

        $cvpayPayInfo = [
            'payOrderId' => $payOrderId,
            'payDataType' => $payDataType,
            'payData' => $payData,
            'mchOrderNo' => $mchOrderNo,
        ];

        if ($payDataType === 'PAY_URL' && is_string($payData) && trim($payData) !== '') {
            $responseData['redirect'] = 'get';
            $responseData['redirectUrl'] = trim($payData);
        } elseif ($payDataType === 'CODE_URL' && is_string($payData) && trim($payData) !== '') {
            $cvpayPayInfo['codeUrl'] = trim($payData);
        } elseif ($payDataType === 'CODE' && is_string($payData) && trim($payData) !== '') {
            $cvpayPayInfo['code'] = trim($payData);
        } elseif ($payDataType === 'JSON') {
            if (is_string($payData) && trim($payData) !== '') {
                $decodedPayData = json_decode(trim($payData), true);
                $cvpayPayInfo['json'] = is_array($decodedPayData) ? $decodedPayData : ['raw' => trim($payData)];
            } elseif (is_array($payData)) {
                $cvpayPayInfo['json'] = $payData;
            }
        }

        $responseData['cvpayPayInfo'] = $cvpayPayInfo;
        $responseData['cvpay'] = [
            'success' => true,
            'status' => 'processing',
            'payOrderId' => $payOrderId,
            'payDataType' => $payDataType,
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

    private function buildFivePayBackendCallbackUrl(string $type): string {
        $baseUrl = rtrim((string)($this->appConfig['file_base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            return '';
        }

        $baseUrl = preg_replace('#/index\.php$#i', '', $baseUrl);
        return $baseUrl . '/api/callback/5pay/' . rawurlencode($type);
    }

    private function requestFivePayDeposit(array $deposit, array &$responseData, array $gateway, array $supportData): void {
        $depositId = (int)($deposit['id'] ?? 0);
        $transactionId = trim((string)($deposit['transactionId'] ?? ''));
        if ($depositId <= 0 || $transactionId === '') {
            return;
        }

        $service = new FivePayService($gateway);
        if (!$service->isConfigured()) {
            Response::serverError('5Pay gateway is not configured (need merchantId, private key, platform public key, base_url)');
        }

        $notifyUrl = $this->buildFivePayBackendCallbackUrl('deposit');
        if ($notifyUrl === '') {
            Response::serverError('5Pay deposit notifyUrl is not configured (file_base_url is empty)');
        }

        $firstName = trim((string)($supportData['first_name'] ?? ''));
        $lastName = trim((string)($supportData['last_name'] ?? ''));
        if ($firstName === '' || $lastName === '') {
            Response::validationError([
                'supportData' => ['first_name and last_name are required for 5Pay deposits']
            ]);
        }

        $merchantOrderNo = FivePayService::buildMerchantOrderNo($transactionId);
        $memberId = trim((string)($deposit['userId'] ?? $deposit['clientId'] ?? ''));
        if ($memberId === '') {
            $memberId = (string)$depositId;
        }

        $memberEmail = trim((string)($supportData['email'] ?? ''));
        $userId = (int)($deposit['userId'] ?? 0);
        if ($memberEmail === '' && $userId > 0) {
            $user = $this->userModel->findById($userId);
            $memberEmail = trim((string)($user['email'] ?? ''));
        }
        if ($memberEmail === '') {
            Response::validationError([
                'supportData' => ['client email is required for 5Pay deposits']
            ]);
        }

        $payload = [
            'DepositMethod' => FivePayService::DEPOSIT_METHOD_F2F,
            'MerchantId' => $service->getMerchantId(),
            'OrderAmount' => FivePayService::formatAmount($deposit['quotedAmount'] ?? $deposit['amount'] ?? 0),
            'MerchantOrderNo' => $merchantOrderNo,
            'NotifyUrl' => $notifyUrl,
            'ReturnUrl' => $this->buildDepositCallbackUrl('pending', $deposit),
            'TimeStamp' => FivePayService::utcTimestamp(),
            'MemberId' => $memberId,
            'MemberEmail' => $memberEmail,
            'CurrencyCode' => 'HKD',
            'PayerFirstName' => $firstName,
            'PayerLastName' => $lastName,
            'BankCode' => FivePayService::BANK_FPS,
            'Lang' => 'en-US',
        ];

        $startedMs = (int) round(microtime(true) * 1000);
        $logId = $this->requestLogService->beginAttempt([
            'provider' => '5pay',
            'environment' => $this->requestLogService->resolveEnvironment(null, $gateway),
            'transactionType' => 'deposit',
            'operation' => 'create',
            'deliveryMode' => 'server_http',
            'depositId' => $depositId,
            'localOrderId' => $transactionId,
            'amount' => $deposit['quotedAmount'] ?? $deposit['amount'] ?? null,
            'currencyCode' => $deposit['currencyCode'] ?? null,
            'requestMethod' => 'POST',
            'endpointPath' => FivePayService::DEPOSIT_PATH,
            'requestPayload' => $payload,
        ]);
        if ($logId) {
            $this->requestLogService->markSent($logId);
        }

        try {
            $apiResponse = $service->createDeposit($payload);
        } catch (Throwable $e) {
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'errorMessage' => $e->getMessage(),
                    'durationMs' => max(0, (int) round(microtime(true) * 1000) - $startedMs),
                ]);
            }
            Logger::error('5Pay deposit Payment failed: ' . $e->getMessage(), [
                'depositId' => $depositId,
                'transactionId' => $transactionId,
            ]);
            Response::serverError('5Pay deposit request failed: ' . $e->getMessage());
        }

        $durationMs = max(0, (int) round(microtime(true) * 1000) - $startedMs);
        $paymentUrl = trim((string)($apiResponse['PaymentURL'] ?? ''));
        $providerOrderNo = trim((string)($apiResponse['OrderNo'] ?? $apiResponse['orderNo'] ?? ''));
        $accepted = FivePayService::isDepositAccepted($apiResponse);

        $update = [
            'gatewayResponse' => json_encode($apiResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        if ($accepted && $paymentUrl !== '') {
            $update['expiredAt'] = gmdate('Y-m-d H:i:s', time() + 1800);
            $update['spayLastStatusCheckAt'] = null;
            $update['spayStatusCheckAttempts'] = 0;
            $update['spayStatusCheckLeaseUntil'] = null;
        }
        if ($providerOrderNo !== '') {
            $update['gatewayTransactionId'] = $providerOrderNo;
        }
        try {
            $this->depositModel->update($depositId, $update);
        } catch (Throwable $e) {
            Logger::error('Failed to persist 5Pay deposit response: ' . $e->getMessage(), [
                'depositId' => $depositId,
            ]);
        }

        if (!$accepted || $paymentUrl === '') {
            $message = FivePayService::depositErrorMessage($apiResponse);
            if ($paymentUrl === '' && $accepted) {
                $message = '5Pay deposit succeeded without PaymentURL';
            }
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'providerOrderId' => $providerOrderNo !== '' ? $providerOrderNo : null,
                    'responsePayload' => $apiResponse,
                    'errorMessage' => $message,
                    'durationMs' => $durationMs,
                ]);
            }
            Logger::error('5Pay deposit Payment rejected', [
                'depositId' => $depositId,
                'message' => $message,
            ]);
            Response::serverError('5Pay deposit request rejected: ' . $message);
        }

        if ($logId) {
            $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_ACCEPTED, [
                'providerOrderId' => $providerOrderNo !== '' ? $providerOrderNo : null,
                'responsePayload' => $apiResponse,
                'durationMs' => $durationMs,
            ]);
        }

        $responseData['redirect'] = 'get';
        $responseData['redirectUrl'] = $paymentUrl;
        $responseData['fivepay'] = [
            'success' => true,
            'status' => 'processing',
            'merchantOrderNo' => $merchantOrderNo,
            'paymentUrl' => $paymentUrl,
        ];
    }

    private function buildCvPayBackendCallbackUrl(string $type): string {
        $baseUrl = rtrim((string)($this->appConfig['file_base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            return '';
        }

        $baseUrl = preg_replace('#/index\.php$#i', '', $baseUrl);
        return $baseUrl . '/api/callback/cvpay/' . rawurlencode($type);
    }

    private function buildCvPayDepositReturnUrl(array $deposit): string {
        $transactionId = trim((string)($deposit['transactionId'] ?? ''));
        if ($transactionId === '') {
            return '';
        }

        $template = trim((string)($this->depositCallbackTemplates['pending'] ?? ''));
        if ($template === '') {
            return '';
        }

        $basePath = $template;
        $queryPos = strpos($basePath, '?');
        if ($queryPos !== false) {
            $basePath = substr($basePath, 0, $queryPos);
        }

        $path = $basePath
            . '?type=' . rawurlencode('deposit')
            . '&id=' . rawurlencode($transactionId);
        $returnUrl = $this->buildFrontendUrl($path);

        $maxLength = 128;
        if ($returnUrl === '' || strlen($returnUrl) > $maxLength) {
            return '';
        }

        return $returnUrl;
    }

    private function buildDepositCreateSnapshot($tradingAccountId, $baseAmount): array {
        $tradingAccountId = (int)$tradingAccountId;
        if ($tradingAccountId <= 0) {
            return [];
        }

        $context = $this->tradingAccountAmountService->getAccountContext($tradingAccountId);
        return [
            'amountScale' => isset($context['scale']) ? (float)$context['scale'] : null,
            'platformAmount' => $this->tradingAccountAmountService->convertBaseToPlatformAmount((float)$baseAmount, $context),
            'displayUnit' => $context['unit'] ?? null,
        ];
    }

    private function requireEnabledDepositGateway($gatewaySettingId) {
        $gateway = $this->gatewayModel->findById($gatewaySettingId);
        if (!$gateway || empty($gateway['isEnabled']) || empty($gateway['isDepositEnabled'])) {
            Response::validationError([
                'gatewaySettingId' => ['Selected gateway is not available for deposits']
            ]);
        }

        $linkedMethod = $this->paymentMethodModel->findByKey($gateway['gatewayKey'] ?? '');
        if ($linkedMethod && empty($linkedMethod['isDepositEnabled'])) {
            Response::validationError([
                'gatewaySettingId' => ['Selected payment method is not available for deposits']
            ]);
        }

        return $gateway;
    }

    private function getGatewayWithSecrets(array $gateway) {
        $gatewayKey = trim((string)($gateway['gatewayKey'] ?? ''));
        if ($gatewayKey === '') {
            return $gateway;
        }

        $gatewayWithSecrets = $this->gatewayModel->findByKeyWithSecrets($gatewayKey);
        return $gatewayWithSecrets ?: $gateway;
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
            if ($name === 'state') {
                $errors[] = 'country and state do not match';
            } else {
                $errors[] = "{$name} must be one of: " . implode(', ', $allowedOptionValues);
            }
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

    private function buildDepositSupportQuestions(array $deposit) {
        $gatewaySettingId = (int)($deposit['gatewaySettingId'] ?? 0);
        $rawSupportContent = trim((string)($deposit['supportContent'] ?? ''));

        if ($gatewaySettingId <= 0 || $rawSupportContent === '') {
            return [];
        }

        $answers = json_decode($rawSupportContent, true);
        if (!is_array($answers)) {
            return [];
        }

        $questions = $this->paymentSupportQuestionModel->getGatewayQuestions(
            $gatewaySettingId,
            PaymentSupportQuestion::SCOPE_DEPOSIT
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
                'name' => $name,
                'answer' => $answers[$name]
            ];
        }

        return $supportQuestions;
    }

    private function applyPostRedirectResponseData(array &$responseData, $redirectUrl, array $redirectPayloadData): void {
        unset(
            $responseData['paymentGateway'],
            $responseData['depositUrl'],
            $responseData['ibeepayPostData'],
            $responseData['paymentAsiaPostData']
        );

        $responseData['redirect'] = 'post';
        $responseData['redirectUrl'] = trim((string)$redirectUrl);
        $responseData['redirectPayloadData'] = $redirectPayloadData;
    }

    private function getIbeepayDepositBaseUrl() {
        $gateway = $this->findEnabledIbeepayGateway();
        $gatewayConfig = $this->getIbeepayGatewayConfig($gateway);
        $baseUrl = trim((string)($gatewayConfig['deposit_url'] ?? ''));
        return $baseUrl !== '' ? $baseUrl : null;
    }

    private function buildIbeepayPostData($gateway, $deposit, $data, $amount, $tradingAccountId, $client) {
        $gatewayConfig = $this->getIbeepayGatewayConfig($gateway);
        $merchant = trim((string)($gateway['merchantName'] ?? ''));
        if ($merchant === '') {
            $merchant = trim((string)($gateway['appId'] ?? ''));
        }
        $orderId = $this->buildDepositProcessorOrderId($deposit);
        $email = trim((string)($data['email'] ?? ''));
        $name = trim((string)($data['name'] ?? ''));
        $phone = preg_replace('/\s+/', '', trim((string)($data['phone'] ?? '')));
        $contact = $phone;
        $birthday = preg_replace('/[^0-9]/', '', trim((string)($data['dob'] ?? '')));
        $secAcc = $this->resolveIbeepaySecAcc($deposit, $tradingAccountId, $client);
        $amountValue = (string)((int)round((float)($deposit['quotedAmount'] ?? $amount)));
        $authKey = trim((string)($gateway['secretKey'] ?? ''));
        $signSource = $orderId . $birthday . $amountValue . $authKey;
        $sign = hash('sha256', $signSource);

        return [
            'merchant' => $merchant,
            'order_id' => $orderId,
            'email' => $email,
            'name' => $name,
            'contact' => $contact,
            'birthday' => $birthday,
            'sec_acc' => $secAcc,
            'amount' => $amountValue,
            'sign' => $sign,
            'abdir' => $this->buildDepositCallbackUrl('fail', $deposit),
            'redir' => $this->buildDepositCallbackUrl('success', $deposit),
            'lang' => (string)($gatewayConfig['lang'] ?? '')
        ];
    }

    private function getIbeepayGatewayConfig($gateway) {
        return $this->getGatewayConfigData($gateway);
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

    private function buildPaymentAsiaDepositUrl(array $gateway) {
        $gatewayConfig = $this->getGatewayConfigData($gateway);
        $depositUrl = trim((string)($gatewayConfig['deposit_url'] ?? ''));
        if ($depositUrl === '') {
            return '';
        }

        $merchantToken = trim((string)($gateway['apiKey'] ?? ''));
        if ($merchantToken === '') {
            return '';
        }

        return str_replace(
            ['{MerchantToken}', '{merchant_token}'],
            rawurlencode($merchantToken),
            $depositUrl
        );
    }

    private function buildPaymentAsiaPostData(array $gateway, array $deposit, array $supportData) {
        $amountDecimalPlaces = $this->resolveGatewayAmountDecimalPlaces($gateway);
        $quotedAmount = (float)($deposit['quotedAmount'] ?? 0);
        $payload = [
            'merchant_reference' => $this->buildDepositProcessorOrderId($deposit),
            'currency' => strtoupper(trim((string)($deposit['currencyCode'] ?? ''))),
            'amount' => $this->formatRoundedAmount($quotedAmount, $amountDecimalPlaces),
            'customer_ip' => ClientIp::getClientIp(),
            'customer_first_name' => trim((string)($supportData['first_name'] ?? '')),
            'customer_last_name' => trim((string)($supportData['last_name'] ?? '')),
            'customer_phone' => trim((string)($supportData['phone'] ?? '')),
            'customer_email' => trim((string)($supportData['email'] ?? '')),
            'network' => 'DirectDebit'
        ];

        $returnUrl = $this->buildDepositCallbackUrl('success', $deposit);
        $notifyUrl = $this->buildPaymentAsiaBackendCallbackUrl();

        if ($returnUrl !== '') {
            $payload['return_url'] = $returnUrl;
        }
        if ($notifyUrl !== '') {
            $payload['notify_url'] = $notifyUrl;
        }

        $payload['sign'] = $this->generatePaymentAsiaSign($payload, $this->getPaymentAsiaMerchantSecret($gateway));

        return $payload;
    }

    private function buildPaymentAsiaBackendCallbackUrl(): string {
        $baseUrl = rtrim((string)($this->appConfig['file_base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            return '';
        }

        $baseUrl = preg_replace('#/index\.php$#i', '', $baseUrl);
        return $baseUrl . '/index.php?path=api/callback/payment-asia/deposit';
    }

    private function getPaymentAsiaMerchantSecret(array $gateway) {
        return trim((string)($gateway['secretKey'] ?? ''));
    }

    private function generatePaymentAsiaSign(array $params, $secret) {
        unset($params['sign']);
        ksort($params, SORT_STRING);
        return hash('sha512', http_build_query($params, '', '&', PHP_QUERY_RFC1738) . (string)$secret);
    }

    private function buildDepositCallbackUrl($status, array $deposit) {
        $template = trim((string)($this->depositCallbackTemplates[$status] ?? ''));
        if ($template === '') {
            return '';
        }

        $path = strtr($template, [
            '{type}' => rawurlencode('deposit'),
            '{id}' => rawurlencode(trim((string)($deposit['transactionId'] ?? ''))),
            '{amount}' => rawurlencode($this->formatCallbackValue($deposit['amount'] ?? null)),
            '{fee}' => rawurlencode($this->formatCallbackValue($deposit['platformFee'] ?? null)),
            '{total}' => rawurlencode($this->formatCallbackValue($deposit['quotedAmount'] ?? null)),
            '{currency}' => rawurlencode(strtoupper(trim((string)($deposit['currencyCode'] ?? '')))),
            '{exchangeRate}' => rawurlencode($this->formatCallbackValue($deposit['exchangeRate'] ?? null)),
            '{method}' => rawurlencode(trim((string)($deposit['gatewayName'] ?? $deposit['gatewayKey'] ?? ''))),
        ]);

        return $this->buildFrontendUrl($path);
    }

    private function formatCallbackValue($value) {
        if ($value === null || $value === '') {
            return '';
        }

        if (!is_numeric($value)) {
            return trim((string)$value);
        }

        $formatted = number_format((float)$value, 8, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function buildFrontendUrl($path) {
        $value = trim((string)$path);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $value)) {
            return $value;
        }

        if ($this->clientFrontendUrl === '') {
            return $value;
        }

        return $this->clientFrontendUrl . '/' . ltrim($value, '/');
    }

    private function resolveIbeepaySecAcc($deposit, $tradingAccountId, $client) {
        return 'utrada_' . (string)($client['userId'] ?? '');
    }

    private function buildDepositProcessorOrderId($deposit) {
        $transactionId = trim((string)($deposit['transactionId'] ?? ''));
        if ($transactionId !== '') {
            return $transactionId;
        }

        return 'utrada-deposit-' . time();
    }

    private function buildCoinsbuyBackendCallbackUrl(): string {
        $baseUrl = rtrim((string)($this->appConfig['file_base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            return '';
        }

        $baseUrl = preg_replace('#/index\.php$#i', '', $baseUrl);
        return $baseUrl . '/index.php?path=api/callback/coinsbuy/deposit';
    }

    /**
     * 调 Coinsbuy 创建 deposit
     * - tracking_id / label 用本地 transactionId（TXN-YYYYMMDD-XXXXXX）
     * - 入库 gatewayResponse + gatewayTransactionId 方便日后 callback 对账
     * - 前端只需要 payment_page 这个 URL，跳过去就行，所以塞 redirect / redirectUrl
     */
    private function requestCoinsbuyDeposit(array $deposit, array &$responseData): void {
        $depositId = (int)($deposit['id'] ?? 0);
        $transactionId = trim((string)($deposit['transactionId'] ?? ''));
        if ($depositId <= 0 || $transactionId === '') {
            return;
        }

        $requestPayload = [
            'tracking_id' => $transactionId,
            'label' => str_replace('-', '', $transactionId),
            'callback_url' => $this->buildCoinsbuyBackendCallbackUrl(),
            'target_amount_requested' => $deposit['quotedAmount'] ?? null,
            'payment_page_redirect_url' => $this->buildDepositCallbackUrl('pending', $deposit),
            'payment_page_button_text' => 'Return to Merchant',
        ];

        $startedMs = (int) round(microtime(true) * 1000);
        $logId = $this->requestLogService->beginAttempt([
            'provider' => 'coinsbuy',
            'environment' => $this->requestLogService->resolveEnvironment(),
            'transactionType' => 'deposit',
            'operation' => 'create',
            'deliveryMode' => 'server_http',
            'depositId' => $depositId,
            'localOrderId' => $transactionId,
            'amount' => $deposit['quotedAmount'] ?? $deposit['amount'] ?? null,
            'currencyCode' => $deposit['currencyCode'] ?? null,
            'requestMethod' => 'POST',
            'endpointPath' => '/deposit',
            'requestPayload' => $requestPayload,
        ]);
        if ($logId) {
            $this->requestLogService->markSent($logId);
        }

        try {
            $service = new CoinsbuyService();
            // Coinsbuy 的 label 不允许特殊符号（- 也算），tracking_id 不受此限制，保留原始格式用于对账
            // target_amount_requested 用本地 quotedAmount（已经按 expectedExchangeRate 换算过的金额），让客户在支付页看到具体应付数额
            // payment_page_redirect_url 用 pending 跳转模板：Coinsbuy 付完款只代表客户那边操作完，链上确认还要等
            // callback 推 transfer.status=2 才算真正入金成功，所以前端先落到 pending 页面
            $apiResponse = $service->createDeposit($requestPayload);

            $durationMs = max(0, (int) round(microtime(true) * 1000) - $startedMs);
            $update = [
                'gatewayResponse' => json_encode($apiResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];

            $gatewayDepositId = trim((string)($apiResponse['data']['id'] ?? ''));
            if ($gatewayDepositId !== '') {
                $update['gatewayTransactionId'] = $gatewayDepositId;
            }

            try {
                $this->depositModel->update($depositId, $update);
            } catch (Throwable $e) {
                Logger::error('Failed to persist Coinsbuy deposit response: ' . $e->getMessage(), [
                    'depositId' => $depositId,
                ]);
            }

            $paymentPage = trim((string)($apiResponse['data']['attributes']['payment_page'] ?? ''));
            if ($paymentPage === '') {
                if ($logId) {
                    $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                        'providerOrderId' => $gatewayDepositId !== '' ? $gatewayDepositId : null,
                        'responsePayload' => $apiResponse,
                        'errorMessage' => 'Missing payment_page',
                        'durationMs' => $durationMs,
                    ]);
                }
                Logger::error('Coinsbuy deposit response missing payment_page', [
                    'depositId' => $depositId,
                    'transactionId' => $transactionId,
                    'response' => $apiResponse,
                ]);
                Response::serverError('Coinsbuy deposit created but payment_page is missing');
            }

            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_ACCEPTED, [
                    'providerOrderId' => $gatewayDepositId !== '' ? $gatewayDepositId : null,
                    'responsePayload' => $apiResponse,
                    'durationMs' => $durationMs,
                ]);
            }

            $responseData['redirect'] = 'get';
            $responseData['redirectUrl'] = $paymentPage;
        } catch (Throwable $e) {
            if ($logId) {
                $this->requestLogService->completeAttempt($logId, PaymentProcessorRequestLogService::STATUS_FAILED, [
                    'errorMessage' => $e->getMessage(),
                    'durationMs' => max(0, (int) round(microtime(true) * 1000) - $startedMs),
                ]);
            }
            Logger::error('Coinsbuy createDeposit failed: ' . $e->getMessage(), [
                'depositId' => $depositId,
                'transactionId' => $transactionId,
            ]);
            Response::serverError('Coinsbuy deposit request failed: ' . $e->getMessage());
        }
    }

    /**
     * Fail-open outbound request log for browser-redirect deposit gateways.
     */
    private function logDepositRedirectAttempt(
        string $provider,
        array $gateway,
        array $deposit,
        string $endpointUrl,
        array $requestPayload
    ): void {
        $depositId = (int)($deposit['id'] ?? 0);
        $localOrderId = $this->buildDepositProcessorOrderId($deposit);
        if ($depositId <= 0 || $localOrderId === '') {
            return;
        }

        $logId = $this->requestLogService->beginAttempt([
            'provider' => $provider,
            'environment' => $this->requestLogService->resolveEnvironment(null, $gateway),
            'transactionType' => 'deposit',
            'operation' => 'create',
            'deliveryMode' => 'client_redirect',
            'depositId' => $depositId,
            'localOrderId' => $localOrderId,
            'amount' => $deposit['quotedAmount'] ?? $deposit['amount'] ?? null,
            'currencyCode' => $deposit['currencyCode'] ?? null,
            'requestMethod' => 'POST',
            'endpointPath' => $endpointUrl,
            'requestPayload' => $requestPayload,
        ]);
        if ($logId) {
            $this->requestLogService->markRedirectIssued($logId, [
                'requestPayload' => $requestPayload,
                'endpointPath' => $endpointUrl,
            ]);
        }
    }

    /**
     * 批准存款 (管理员)
     * POST /api/deposits/{id}/approve
     */
    public function approve($id) {
        $admin = $this->requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogDeposits($data);
        $opLog = new AdminOperationLogWriter();

        $deposit = $this->depositModel->findById($id);
        if (!$deposit) {
            $opLog->logDepositApprove($subModule, 0, '', '', 0, false, 'Deposit not found');
            Response::notFound('Deposit not found');
        }

        $clientId = (int) ($deposit['userId'] ?? 0);
        $transactionId = (string) ($deposit['transactionId'] ?? '');
        $clientName = $this->resolveDepositClientName($deposit);

        if (($deposit['status'] ?? '') !== 'pending') {
            $opLog->logDepositApprove(
                $subModule,
                $clientId,
                $transactionId,
                $clientName,
                $deposit['amount'] ?? 0,
                false,
                'Only pending deposits can be approved'
            );
            Response::error('Only pending deposits can be approved', 400);
        }

        $adminNotes = $data['adminNotes'] ?? null;

        try {
            $updatedDeposit = $this->paymentService->markDepositSuccess(
                $deposit,
                (int)$admin['userId'],
                $adminNotes,
                'approve'
            );
        } catch (RuntimeException $e) {
            $opLog->logDepositApprove(
                $subModule,
                $clientId,
                $transactionId,
                $clientName,
                $deposit['amount'] ?? 0,
                false,
                $e->getMessage()
            );
            Response::serverError($e->getMessage());
        }

        $opLog->logDepositApprove(
            $subModule,
            (int) ($updatedDeposit['userId'] ?? $clientId),
            (string) ($updatedDeposit['transactionId'] ?? $transactionId),
            $this->resolveDepositClientName($updatedDeposit),
            $updatedDeposit['amount'] ?? 0,
            true
        );

        Response::success($updatedDeposit, 'Deposit approved successfully');
    }

    /**
     * 拒绝存款 (管理员)
     * POST /api/deposits/{id}/reject
     */
    public function reject($id) {
        $admin = $this->requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogDeposits($data);
        $opLog = new AdminOperationLogWriter();

        $deposit = $this->depositModel->findById($id);
        if (!$deposit) {
            $opLog->logDepositReject($subModule, 0, '', '', false, 'Deposit not found');
            Response::notFound('Deposit not found');
        }

        $clientId = (int) ($deposit['userId'] ?? 0);
        $transactionId = (string) ($deposit['transactionId'] ?? '');

        if (($deposit['status'] ?? '') !== 'pending') {
            $opLog->logDepositReject(
                $subModule,
                $clientId,
                $transactionId,
                '',
                false,
                'Only pending deposits can be rejected'
            );
            Response::error('Only pending deposits can be rejected', 400);
        }

        $validator = new Validator($data, [
            'rejectionReasonId' => 'required|numeric'
        ]);
        if (!$validator->validate()) {
            $rejectErrors = $validator->getErrors();
            $opLog->logDepositReject(
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
        if (!$reason || ($reason['scope'] ?? null) !== 'deposit') {
            $opLog->logDepositReject($subModule, $clientId, $transactionId, '', false, 'Invalid rejection reason');
            Response::validationError([
                'rejectionReasonId' => ['Invalid rejection reason']
            ]);
        }

        if (($reason['reasonKey'] ?? '') === 'custom' && empty($customReason)) {
            $opLog->logDepositReject(
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

        $adminNotes = isset($data['adminNotes']) ? trim((string)$data['adminNotes']) : null;
        $adminNotes = $adminNotes !== '' ? $adminNotes : null;

        try {
            $updatedDeposit = $this->paymentService->markDepositRejected(
                $deposit,
                'reject',
                (int)$admin['userId'],
                [
                    'rejectionReasonId' => $rejectionReasonId,
                    'rejectionNotes' => $rejectionNotes,
                    'customReason' => $customReason,
                    'adminNotes' => $adminNotes,
                    'rejectionReasonTitle' => $reason['reasonTitle'] ?? null
                ]
            );
        } catch (RuntimeException $e) {
            $opLog->logDepositReject($subModule, $clientId, $transactionId, '', false, $e->getMessage());
            Response::serverError($e->getMessage());
        }

        $reasonTitle = ($reason['reasonKey'] ?? '') === 'custom'
            ? trim((string) $customReason)
            : trim((string) ($reason['reasonTitle'] ?? ''));
        $opLog->logDepositReject(
            $subModule,
            (int) ($updatedDeposit['userId'] ?? $clientId),
            (string) ($updatedDeposit['transactionId'] ?? $transactionId),
            $reasonTitle !== '' ? $reasonTitle : '—',
            true
        );

        Response::success($updatedDeposit, 'Deposit rejected successfully');
    }

    /**
     * 取消存款 (客户端，仅自己的 pending deposit)
     * POST /api/deposits/{id}/cancel
     */
    public function cancel($id) {
        $client = $this->requireClient();

        $deposit = $this->depositModel->findById($id);
        if (!$deposit) {
            Response::notFound('Deposit not found');
        }

        if ((int)($deposit['userId'] ?? 0) !== (int)$client['userId']) {
            Response::forbidden('You can only cancel your own deposit');
        }

        if (($deposit['status'] ?? '') !== 'pending') {
            Response::error('Only pending deposits can be cancelled', 400);
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

        $updatedDeposit = $this->paymentService->markDepositRejected(
            $deposit,
            'cancel',
            (int)$client['userId'],
            ['cancelReason' => $reason]
        );

        Response::success($updatedDeposit, 'Deposit cancelled successfully');
    }

    /**
     * 批量批准存款
     * POST /api/deposits/bulk-approve
     */
    public function bulkApprove() {
        $admin = $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogDeposits($data);
        $opLog = new AdminOperationLogWriter();

        $validator = new Validator($data, [
            'depositIds' => 'required|array'
        ]);
        if (!$validator->validate()) {
            $bulkApproveErrors = $validator->getErrors();
            $opLog->logDepositBulkApprove(
                $subModule,
                [],
                false,
                OperationLogTextHelpers::validationErrorsToMessage($bulkApproveErrors)
            );
            Response::validationError($bulkApproveErrors);
        }

        $depositIds = $data['depositIds'];
        $adminNotes = $data['adminNotes'] ?? null;

        $results = [];
        $successCount = 0;
        $failCount = 0;
        $approvedTransactionIds = [];

        foreach ($depositIds as $depositId) {
            try {
                $deposit = $this->depositModel->findById($depositId);

                if (!$deposit || ($deposit['status'] ?? '') !== 'pending') {
                    $errorMsg = 'Deposit not found or not in pending status';
                    $apiMessage = "Bulk approval failed at deposit ID {$depositId}: {$errorMsg}";
                    $opLog->logDepositBulkApprove($subModule, [], false, $apiMessage);
                    Response::error($apiMessage, 400);
                }

                $this->paymentService->markDepositSuccess(
                    $deposit,
                    (int)$admin['userId'],
                    $adminNotes,
                    'bulk-approve'
                );

                $txnId = trim((string) ($deposit['transactionId'] ?? ''));
                if ($txnId !== '') {
                    $approvedTransactionIds[] = $txnId;
                }

                $results[] = [
                    'depositId' => $depositId,
                    'success' => true,
                    'message' => 'Approved'
                ];
                $successCount++;

            } catch (Exception $e) {
                $errorMsg = $e->getMessage();
                $apiMessage = "Bulk approval failed at deposit ID {$depositId}: {$errorMsg}";
                $opLog->logDepositBulkApprove($subModule, [], false, $apiMessage);
                Response::error($apiMessage, 400);
            }
        }

        $opLog->logDepositBulkApprove($subModule, $approvedTransactionIds, true);

        Response::success([
            'results' => $results,
            'summary' => [
                'total' => count($depositIds),
                'success' => $successCount,
                'failed' => $failCount
            ]
        ], "Bulk approval completed: {$successCount} succeeded, {$failCount} failed");
    }

    /**
     * 添加标签到存款
     * POST /api/deposits/{id}/tags
     */
    public function addTag($id) {
        $admin = $this->requireAdmin();

        $deposit = $this->depositModel->findById($id);
        if (!$deposit) {
            Response::notFound('Deposit not found');
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
        $tags = $this->tagAssignmentModel->getDepositTags($id);

        Response::success($tags, 'Tag added successfully');
    }

    /**
     * 移除存款的标签
     * DELETE /api/deposits/{id}/tags/{tagId}
     */
    public function removeTag($id, $tagId) {
        $this->requireAdmin();
        $subModule = OperationLogPages::resolveLogDepositsFromRequest();
        $opLog = new AdminOperationLogWriter();

        $deposit = $this->depositModel->findById($id);
        if (!$deposit) {
            $opLog->logDepositTagRemove($subModule, 0, '', '', false, 'Deposit not found');
            Response::notFound('Deposit not found');
        }

        $tag = $this->tagModel->findById($tagId);
        $tagName = trim((string) ($tag['tagName'] ?? ''));
        $clientId = (int) ($deposit['userId'] ?? 0);

        $this->tagAssignmentModel->removeTag($id, $tagId);

        $opLog->logDepositTagRemove(
            $subModule,
            $clientId,
            (string) ($deposit['transactionId'] ?? ''),
            $tagName !== '' ? $tagName : '—',
            true
        );

        Response::success(null, 'Tag removed successfully');
    }

    /**
     * 批量添加标签
     * POST /api/deposits/bulk-add-tags
     */
    public function bulkAddTags() {
        $admin = $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogDeposits($data);
        $opLog = new AdminOperationLogWriter();

        $validator = new Validator($data, [
            'depositIds' => 'required|array',
            'tagName' => 'required|string'
        ]);
        if (!$validator->validate()) {
            $tagBulkErrors = $validator->getErrors();
            $opLog->logDepositTagBulk(
                $subModule,
                [],
                '',
                false,
                OperationLogTextHelpers::validationErrorsToMessage($tagBulkErrors)
            );
            Response::validationError($tagBulkErrors);
        }

        $depositIds = $data['depositIds'];
        $tagName = trim($data['tagName']);

        try {
            $tag = $this->tagModel->findOrCreate($tagName, $admin['userId']);
            $this->tagAssignmentModel->bulkAssignTag($depositIds, $tag['id'], $admin['userId']);
        } catch (Exception $e) {
            $opLog->logDepositTagBulk($subModule, [], $tagName, false, $e->getMessage());
            Response::serverError($e->getMessage());
        }

        $depositSnapshots = [];
        foreach ($depositIds as $depositId) {
            $row = $this->depositModel->findById($depositId);
            if (!$row) {
                continue;
            }
            $depositSnapshots[] = [
                'userId' => (int) ($row['userId'] ?? 0),
                'transactionId' => (string) ($row['transactionId'] ?? ''),
            ];
        }
        $opLog->logDepositTagBulk($subModule, $depositSnapshots, $tagName, true);

        Response::success([
            'tag' => $tag,
            'assignedCount' => count($depositIds)
        ], "Tag '{$tagName}' added to " . count($depositIds) . " deposits");
    }

    /**
     * 获取存款的状态历史
     * GET /api/deposits/{id}/history
     */
    public function getHistory($id) {
        $deposit = $this->depositModel->findById($id);
        if (!$deposit) {
            Response::notFound('Deposit not found');
        }

        $history = $this->statusHistoryModel->getDepositHistory($id);

        Response::success($history);
    }

    /**
     * 获取拒绝原因列表
     * GET /api/deposits/rejection-reasons
     */
    public function getRejectionReasons() {
        $scope = trim((string)($_GET['scope'] ?? 'deposit'));
        if (!in_array($scope, ['deposit', 'withdrawal'], true)) {
            Response::validationError([
                'scope' => ['Invalid rejection reason scope']
            ]);
        }
        $reasons = $this->rejectionReasonModel->getActiveReasons($scope);
        Response::success($reasons);
    }

    /**
     * 添加存款备注
     * POST /api/deposits/{id}/notes
     */
    public function addNote($id) {
        $admin = $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogDeposits($data);
        $opLog = new AdminOperationLogWriter();

        $deposit = $this->depositModel->findById($id);
        if (!$deposit) {
            $opLog->logDepositNoteAdd($subModule, 0, '', false, 'Deposit not found');
            Response::notFound('Deposit not found');
        }

        $clientId = (int) ($deposit['userId'] ?? 0);
        $transactionId = (string) ($deposit['transactionId'] ?? '');

        $validator = new Validator($data, [
            'noteContent' => 'required|string'
        ]);
        if (!$validator->validate()) {
            $noteErrors = $validator->getErrors();
            $opLog->logDepositNoteAdd(
                $subModule,
                $clientId,
                $transactionId,
                false,
                OperationLogTextHelpers::validationErrorsToMessage($noteErrors)
            );
            Response::validationError($noteErrors);
        }

        $noteContent = trim($data['noteContent']);
        if (empty($noteContent)) {
            $opLog->logDepositNoteAdd($subModule, $clientId, $transactionId, false, 'Note content cannot be empty');
            Response::validationError([
                'noteContent' => ['Note content cannot be empty']
            ]);
        }

        $noteId = $this->noteModel->addNote($id, $noteContent, $admin['userId']);

        // 获取新添加的备注（包含创建人信息）
        $notes = $this->noteModel->getDepositNotes($id);
        $note = null;
        foreach ($notes as $n) {
            if ($n['id'] == $noteId) {
                $note = $n;
                break;
            }
        }

        if (!$note) {
            // 如果找不到，直接查询并获取管理员信息
            $note = $this->noteModel->findById($noteId);
            require_once __DIR__ . '/../models/AdminUser.php';
            $adminUserModel = new AdminUser();
            $adminUser = $adminUserModel->findById($admin['userId']);
            $note['createdByName'] = $adminUser['fullName'] ?? 'Unknown';
        }

        $opLog->logDepositNoteAdd($subModule, $clientId, $transactionId, true);

        Response::created($note, 'Note added successfully');
    }

    /**
     * 获取存款备注列表
     * GET /api/deposits/{id}/notes
     */
    public function getNotes($id) {
        $deposit = $this->depositModel->findById($id);
        if (!$deposit) {
            Response::notFound('Deposit not found');
        }

        $notes = $this->noteModel->getDepositNotes($id);

        Response::success($notes);
    }

    /**
     * 获取存款统计数据
     * GET /api/deposits/statistics
     */
    public function statistics() {
        $startDate = $_GET['startDate'] ?? null;
        $endDate = $_GET['endDate'] ?? null;

        $stats = $this->depositModel->getStatistics($startDate, $endDate);

        Response::success($stats);
    }

    /**
     * 导出存款数据
     * POST /api/deposits/export
     */
    public function export() {
        $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogDeposits($data);
        $opLog = new AdminOperationLogWriter();
        $depositIds = $data['depositIds'] ?? [];
        $format = $data['format'] ?? 'csv';

        if (empty($depositIds)) {
            $opLog->logDepositExport(
                $subModule,
                0,
                $format,
                false,
                'Please select at least one deposit to export'
            );
            Response::validationError([
                'depositIds' => ['Please select at least one deposit to export']
            ]);
        }

        // 获取存款数据
        $deposits = [];
        foreach ($depositIds as $depositId) {
            $deposit = $this->depositModel->getDepositDetails($depositId);
            if ($deposit) {
                $deposits[] = $deposit;
            }
        }

        if (empty($deposits)) {
            $opLog->logDepositExport($subModule, 0, $format, false, 'No valid deposits found for export');
            Response::error('No valid deposits found for export', 404);
        }

        // 返回数据用于前端导出
        Response::success([
            'deposits' => $deposits,
            'format' => $format,
            'count' => count($deposits)
        ], 'Export data ready');
    }

    /**
     * 获取客户的存款列表 (客户端)
     * GET /api/deposits/my-deposits
     */
    public function myDeposits() {
        $client = $this->requireClient();

        $limit = $_GET['limit'] ?? 10;
        $deposits = $this->depositModel->getUserDeposits($client['userId'], $limit);

        Response::success($deposits);
    }

    /**
     * 获取可用余额和存款限制 (客户端)
     * GET /api/deposits/available-balance
     * Wallet 公式：availableBalance = totalDeposits - totalWithdrawals + totalCompletedCommission - walletToTradingAmount
     * （totalDeposits=已完成入金，totalWithdrawals=已提交出金，totalCompletedCommission=IB已完成佣金，walletToTradingAmount=内部转账从Wallet转出到交易账户的金额之和）
     */
    public function getAvailableBalance() {
        $client = $this->requireClient();
        $db = Database::getInstance();
        $walletBreakdown = $this->walletBalanceService->getBreakdown((int)$client['userId']);

        // 获取存款限制配置
        $limits = $this->limitModel->getActiveLimits('deposit');

        // 查找 'all' 类型的限制
        $defaultLimit = null;
        foreach ($limits as $limit) {
            if ($limit['paymentType'] === 'all') {
                $defaultLimit = $limit;
                break;
            }
        }

        // 如果没有找到，使用默认值
        if (!$defaultLimit) {
            $defaultLimit = [
                'minimumAmount' => 10,
                'maximumAmount' => 50000,
                'dailyLimit' => 100000,
                'monthlyLimit' => 500000
            ];
        }

        // 计算今日和本月的存款总额（用于前端验证）
        $todayDepositSql = "SELECT COALESCE(SUM(amount), 0) as todayTotal
                            FROM deposits
                            WHERE userId = :userId
                            AND DATE(requestedAt) = CURDATE()";
        $todayDepositResult = $db->fetchOne($todayDepositSql, ['userId' => $client['userId']]);
        $todayDeposits = (float)($todayDepositResult['todayTotal'] ?? 0);

        $monthlyDepositSql = "SELECT COALESCE(SUM(amount), 0) as monthlyTotal
                              FROM deposits
                              WHERE userId = :userId
                              AND YEAR(requestedAt) = YEAR(CURDATE())
                              AND MONTH(requestedAt) = MONTH(CURDATE())";
        $monthlyDepositResult = $db->fetchOne($monthlyDepositSql, ['userId' => $client['userId']]);
        $monthlyDeposits = (float)($monthlyDepositResult['monthlyTotal'] ?? 0);

        Response::success([
            'availableBalance' => round((float)$walletBreakdown['availableBalance'], 2),
            'totalDeposits' => round((float)$walletBreakdown['totalDeposits'], 2),
            'totalWithdrawals' => round((float)$walletBreakdown['totalWithdrawals'], 2),
            'depositLimits' => [
                'dailyLimit' => (float)$defaultLimit['dailyLimit'],
                'monthlyLimit' => (float)$defaultLimit['monthlyLimit']
            ],
            'depositStats' => [
                'todayDeposits' => round($todayDeposits, 2),
                'monthlyDeposits' => round($monthlyDeposits, 2)
            ]
        ]);
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

    /**
     * 发送邮件给客户
     * POST /api/deposits/{id}/send-email
     */
    public function sendEmail($id) {
        $admin = $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogDeposits($data);
        $opLog = new AdminOperationLogWriter();

        $deposit = $this->depositModel->findById($id);
        if (!$deposit) {
            $opLog->logDepositEmail($subModule, 0, '', false, 'Deposit not found');
            Response::notFound('Deposit not found');
        }

        $clientId = (int) ($deposit['userId'] ?? 0);

        $validator = new Validator($data, [
            'email' => 'required|email',
            'subject' => 'required|string|max:255',
            'content' => 'required|string'
        ]);
        if (!$validator->validate()) {
            $emailErrors = $validator->getErrors();
            $opLog->logDepositEmail(
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

        $user = $this->userModel->findById($deposit['userId']);
        if (!$user || $user['email'] !== $email) {
            $opLog->logDepositEmail(
                $subModule,
                $clientId,
                $email,
                false,
                'Email address does not match the deposit client'
            );
            Response::validationError([
                'email' => ['Email address does not match the deposit client']
            ], 'Email address does not match the deposit client');
        }

        try {
            $emailSender = new EmailSender();

            $success = $emailSender->sendClientNotification(
                $email,
                $subject,
                $content,
                [
                    'depositId' => $id,
                    'sentBy' => $admin['userId'],
                    'type' => 'deposit'
                ]
            );

            if ($success) {
                $opLog->logDepositEmail($subModule, $clientId, $email, true);

                Response::success([
                    'message' => 'Email sent successfully',
                    'email' => $email,
                    'subject' => $subject
                ], 'Email sent successfully');
            } else {
                $opLog->logDepositEmail($subModule, $clientId, $email, false, 'Failed to send email');
                Response::error('Failed to send email', 500);
            }
        } catch (Exception $e) {
            $opLog->logDepositEmail(
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
     * 入金自动审批：若后台入金自动审批已开启且满足规则，则执行与后台「同意审批」相同的流程（spApproveDeposit）
     * @param int $depositId
     * @return bool 是否已自动通过
     */
    private function tryAutoApproveDeposit($depositId) {
        $rule = $this->autoApprovalRuleModel->findById(1);
        if (!$rule || !$rule['isEnabled']) {
            return false;
        }

        $deposit = $this->depositModel->getDepositDetails($depositId);
        if (!$deposit || ($deposit['status'] ?? '') !== 'pending') {
            return false;
        }

        $ruleDetails = $this->autoApprovalRuleModel->getRuleDetails(1);
        if (!$ruleDetails) {
            return false;
        }

        $user = $this->userModel->findById($deposit['userId']);
        if (!$user) {
            return false;
        }

        $amount = (float) $deposit['amount'];
        if ($ruleDetails['minAmount'] !== null && $ruleDetails['minAmount'] !== '' && $amount < (float) $ruleDetails['minAmount']) {
            $this->logAutoApprovalCheck($depositId, $deposit, $user, null, false, 'Amount below minimum');
            return false;
        }
        if ($ruleDetails['maxAmount'] !== null && $ruleDetails['maxAmount'] !== '' && $amount > (float) $ruleDetails['maxAmount']) {
            $this->logAutoApprovalCheck($depositId, $deposit, $user, null, false, 'Amount above maximum');
            return false;
        }

        $allowedCountries = is_string($ruleDetails['allowedCountries'] ?? null)
            ? json_decode($ruleDetails['allowedCountries'], true)
            : ($ruleDetails['allowedCountries'] ?? []);
        if (!empty($allowedCountries) && !in_array('ALL', $allowedCountries)) {
            $userCountry = $user['country'] ?? null;
            if (!$userCountry || !in_array($userCountry, $allowedCountries)) {
                $this->logAutoApprovalCheck($depositId, $deposit, $user, null, false, 'Country not in allowed list');
                return false;
            }
        }

        $clientTagNames = $this->getClientTagNames($deposit['userId']);
        $requiredTags = is_array($ruleDetails['requiredClientTags'] ?? null)
            ? $ruleDetails['requiredClientTags']
            : array_filter(array_map('trim', explode(',', (string) ($ruleDetails['requiredClientTags'] ?? ''))));
        foreach ($requiredTags as $tag) {
            if (!in_array($tag, $clientTagNames)) {
                $this->logAutoApprovalCheck($depositId, $deposit, $user, null, false, 'Missing required tag: ' . $tag);
                return false;
            }
        }

        $excludedTags = is_array($ruleDetails['excludedClientTags'] ?? null)
            ? $ruleDetails['excludedClientTags']
            : array_filter(array_map('trim', explode(',', (string) ($ruleDetails['excludedClientTags'] ?? ''))));
        foreach ($excludedTags as $tag) {
            if (in_array($tag, $clientTagNames)) {
                $this->logAutoApprovalCheck($depositId, $deposit, $user, null, false, 'Client has excluded tag: ' . $tag);
                return false;
            }
        }

        try {
            $this->paymentService->markDepositSuccess(
                $deposit,
                0,
                'Auto-approved by system',
                'auto-approve'
            );
        } catch (Exception $e) {
            $this->logAutoApprovalCheck($depositId, $deposit, $user, null, false, 'Approval failed: ' . $e->getMessage());
            return false;
        }

        $this->autoApprovalRuleModel->recordApplication(1);
        $this->logAutoApprovalCheck($depositId, $deposit, $user, 1, true, null);
        return true;
    }

    /**
     * 获取客户标签名列表（leadTagAssignments + leadTags）
     */
    private function getClientTagNames($userId) {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            'SELECT lt.tagName FROM leadTagAssignments lta INNER JOIN leadTags lt ON lt.id = lta.tagId WHERE lta.leadId = :leadId',
            ['leadId' => $userId]
        );
        return array_column($rows ?: [], 'tagName');
    }

    /**
     * 记录自动审批检查到 autoApprovalLog
     */
    private function logAutoApprovalCheck($depositId, $deposit, $user, $ruleId, $wasAutoApproved, $rejectionReason) {
        $this->autoApprovalLogModel->logCheck([
            'transactionType' => 'deposit',
            'transactionId' => $depositId,
            'transactionRefId' => $deposit['transactionId'] ?? null,
            'userId' => $deposit['userId'],
            'ruleId' => $ruleId,
            'wasAutoApproved' => $wasAutoApproved ? 1 : 0,
            'checkResults' => [],
            'rejectionReason' => $rejectionReason,
            'amount' => $deposit['amount'],
            'clientCountry' => $user['country'] ?? null,
            'clientTags' => implode(',', $this->getClientTagNames($deposit['userId'])),
            'kycStatus' => $user['kycStatus'] ?? null,
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }


    private function sendDepositAdminStatusNotice(array $deposit, string $status, array $context = []): void
    {
        $clientId = (int)($deposit['userId'] ?? 0);
        $depositId = (int)($deposit['id'] ?? 0);
        if ($clientId <= 0 || $depositId <= 0) {
            return;
        }

        $client = $this->userModel->findById($clientId);
        if (!$client) {
            return;
        }

        [$subject, $message, $type] = $this->buildDepositAdminStatusNoticePayload($deposit, $client, $status, $context);
        if ($subject === '' || $message === '' || $type === '') {
            return;
        }

        $metadata = json_encode([
            'depositId' => $depositId,
            'clientId' => $clientId,
            'status' => $status,
            'amount' => (float)($deposit['amount'] ?? 0),
            'tradingAccountId' => $deposit['tradingAccountId'] ?? null,
            'adminNotes' => $context['adminNotes'] ?? null,
            'rejectionReasonId' => $context['rejectionReasonId'] ?? null,
            'rejectionReasonTitle' => $context['rejectionReasonTitle'] ?? null,
            'rejectionNotes' => $context['rejectionNotes'] ?? null,
            'customReason' => $context['customReason'] ?? null,
            'action' => 'view_deposit',
            'actionUrl' => '/deposits'
        ]);

        $this->createAdminNotification(0, $subject, $message, $metadata, $type);
    }

    private function notifyAdminsOfDepositCreated(array $deposit): void
    {
        $clientId = (int)($deposit['userId'] ?? 0);
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

        $subject = "New Deposit Request from {$clientName}";
        $message = "Client {$clientName} has created a deposit request for review.";
        $metadata = json_encode([
            'depositId' => (int)($deposit['id'] ?? 0),
            'clientId' => $clientId,
            'amount' => (float)($deposit['amount'] ?? 0),
            'tradingAccountId' => $deposit['tradingAccountId'] ?? null,
            'action' => 'view_deposit',
            'actionUrl' => '/deposits'
        ]);

        $this->createAdminNotification(0, $subject, $message, $metadata, 'deposit_created');
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

    private function buildDepositAdminStatusNoticePayload(array $deposit, array $client, string $status, array $context = []): array
    {
        $clientName = trim(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? ''));
        if ($clientName === '') {
            $clientName = $client['email'] ?? ('Client #' . (int)($deposit['userId'] ?? 0));
        }

        if ($status === 'approved') {
            $subject = "Deposit approved for {$clientName}";
            $message = "Deposit request from {$clientName} has been approved.";
            $adminNotes = trim((string)($context['adminNotes'] ?? ''));
            if ($adminNotes !== '') {
                $message .= ' Notes: ' . $adminNotes;
            }

            return [$subject, $message, 'deposit_approved'];
        }

        if ($status === 'rejected') {
            $subject = "Deposit rejected for {$clientName}";
            $message = "Deposit request from {$clientName} has been rejected.";
            $reasonText = trim((string)($context['customReason'] ?? $context['rejectionNotes'] ?? $context['rejectionReasonTitle'] ?? ''));
            if ($reasonText !== '') {
                $message .= ' Reason: ' . $reasonText;
            }

            return [$subject, $message, 'deposit_rejected'];
        }

        return ['', '', ''];
    }

    private function resolveDepositClientName(array $deposit) {
        $name = trim((string) (($deposit['firstName'] ?? '') . ' ' . ($deposit['lastName'] ?? '')));
        if ($name !== '') {
            return $name;
        }
        $userId = (int) ($deposit['userId'] ?? 0);
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
