<?php
/**
 * Trading Account Controller
 * 负责客户端开户相关接口
 */

require_once __DIR__ . '/../models/TradingPlatform.php';
require_once __DIR__ . '/../models/TradingPlatformLeverage.php';
require_once __DIR__ . '/../models/TradingAccount.php';
require_once __DIR__ . '/../models/TradingAccountExternalAccount.php';
require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../models/ClientKycSubmission.php';
require_once __DIR__ . '/../models/InternalTransfer.php';
require_once __DIR__ . '/../models/Deposit.php';
require_once __DIR__ . '/../models/Withdrawal.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/TradingAccountBalance.php';
require_once __DIR__ . '/../models/TradingGroup.php';
require_once __DIR__ . '/../models/EmailOtpVerification.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/FinanceProApiClient.php';
require_once __DIR__ . '/../utils/Mt5ApiClient.php';
require_once __DIR__ . '/../utils/Mt5GatewayApiClient.php';
require_once __DIR__ . '/../utils/Mt4ApiClient.php';
require_once __DIR__ . '/../utils/Password.php';
require_once __DIR__ . '/../models/FinanceProToken.php';
require_once __DIR__ . '/../utils/Encryptor.php';
require_once __DIR__ . '/../services/TradingAccountAmountService.php';
require_once __DIR__ . '/../services/WalletBalanceService.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';
require_once __DIR__ . '/../services/AssignableIbRebatePackage.php';

class TradingAccountController {
    private $platformModel;
    private $leverageModel;
    private $accountModel;
    private $externalAccountModel;
    private $userModel;
    private $kycSubmissionModel;
    private $transferModel;
    private $orderModel;
    private $balanceModel;
    private $tradingGroupModel;
    private $config;
    private $financePro;
    private $financeProClient;
    private $tradingAccountAmountService;
    private $walletBalanceService;

    private $mt5ApiClient;
    private $mt5GatewayClient;
    private $mt4ApiClient;
    private $tradingPlatformsConfig;
    private $symbolNameIdMapByPlatform;

    public function __construct() {
        $this->platformModel = new TradingPlatform();
        $this->leverageModel = new TradingPlatformLeverage();
        $this->accountModel = new TradingAccount();
        $this->externalAccountModel = new TradingAccountExternalAccount();
        $this->userModel = new ClientUser();
        $this->kycSubmissionModel = new ClientKycSubmission();
        $this->transferModel = new InternalTransfer();
        $this->orderModel = new Order();
        $this->balanceModel = new TradingAccountBalance();
        $this->tradingGroupModel = new TradingGroup();
        $this->config = require __DIR__ . '/../config/app.php';
        $this->tradingAccountAmountService = new TradingAccountAmountService();
        $this->walletBalanceService = new WalletBalanceService();

        $this->financePro = isset($this->config['integrations']['finance_pro']) ? $this->config['integrations']['finance_pro'] : [];
        $this->financeProClient = new FinanceProApiClient($this->financePro);
        $this->mt5ApiClient = new Mt5ApiClient();
        // MT5 读操作（持仓 / 挂单快照）走只读网关；mt5ApiClient 仅留给建户等写操作
        $this->mt5GatewayClient = new Mt5GatewayApiClient();
        $this->mt4ApiClient = new Mt4ApiClient();
        $this->tradingPlatformsConfig = isset($this->config['trading_platforms']) ? $this->config['trading_platforms'] : [];
        $this->symbolNameIdMapByPlatform = [];
    }

    /**
     * 检查平台是否已对接
     *
     * @param string $platformKey 平台标识（mt4, mt5, financepro）
     * @return bool
     */
    private function isPlatformEnabled($platformKey) {
        return isset($this->tradingPlatformsConfig[$platformKey]) && $this->tradingPlatformsConfig[$platformKey] === true;
    }

    /**
     * 获取平台允许创建的活跃账户上限，默认 1。
     * accountLimit 在 PlatformSettings 里维护，原 app.php 配置已废弃。
     */
    private function getPlatformAccountLimit($platform) {
        if (!is_array($platform)) {
            return 1;
        }
        $limit = isset($platform['accountLimit']) ? (int)$platform['accountLimit'] : 0;
        return $limit > 0 ? $limit : 1;
    }

    /**
     * 平台开户密码模式：manual=客户必须传密码；random=后端随机生成。
     * 从 tradingPlatforms.passwordMode 读取，缺省回到 'random'。
     */
    private function getPlatformPasswordMode($platform) {
        if (!is_array($platform)) {
            return 'random';
        }
        $mode = isset($platform['passwordMode']) ? (string)$platform['passwordMode'] : '';
        return $mode === 'manual' ? 'manual' : 'random';
    }

    private function isPasswordRequiredForPlatform($platform) {
        return $this->getPlatformPasswordMode($platform) === 'manual';
    }

    /**
     * 获取平台下载配置。
     */
    private function getPlatformDownloadConfig($platformKey) {
        $integrationKeyMap = [
            'financepro' => 'finance_pro'
        ];

        $integrationKey = isset($integrationKeyMap[$platformKey]) ? $integrationKeyMap[$platformKey] : $platformKey;
        $integrationConfig = $this->config['integrations'][$integrationKey] ?? [];

        return [
            'download' => $integrationConfig['download'] ?? null,
            'url' => $integrationConfig['url'] ?? null
        ];
    }

    /**
     * 根据平台调用第三方API获取开单持仓订单
     *
     * @param string $platformKey 平台标识
     * @param string $login 交易账户ID
     * @return array 订单数组
     */
    private function fetchOpenOrdersByPlatform($platformKey, $login) {
        if ($platformKey === 'financepro' && $this->isPlatformEnabled('financepro')) {
            // 检查配置中是否有 portfolio_positions 接口
            if (!isset($this->financePro['portfolio_positions']) || empty($this->financePro['portfolio_positions'])) {
                return [];
            }

            $response = $this->financeProClient->request(
                $this->financePro['portfolio_positions'],
                'GET',
                ['uid' => $login],
                [
                    'expect_success_key' => 'Success',
                    'success_value' => true,
                    'error_message_key' => 'ErrMsg'
                ]
            );

            if (isset($response['ResData']) && is_array($response['ResData'])) {
                $orders = [];
                foreach ($response['ResData'] as $item) {
                    if (isset($item['Info']) && is_array($item['Info'])) {
                        $orders = array_merge($orders, $item['Info']);
                    }
                }
                return $orders;
            }
            return [];
        }
        if ($platformKey === 'mt5' && $this->isPlatformEnabled('mt5')) {
            $snapshot = $this->mt5GatewayClient->getAccountOrdersBalanceSnapshot($login, [
                'includeBalance' => false,
                'includePositions' => true,
                'includePending' => false,
                'includeHistory' => false,
                'offset' => 0,
                'limit' => 500,
            ]);
            $orders = isset($snapshot['positions']) && is_array($snapshot['positions']) ? $snapshot['positions'] : [];
            return $this->applyLocalSymbolIds($orders, $platformKey);
        }
        if ($platformKey === 'mt4' && $this->isPlatformEnabled('mt4')) {
            $snapshot = $this->mt4ApiClient->getAccountOrdersBalanceSnapshot($login, [
                'includeBalance' => false,
                'includePositions' => true,
                'includePending' => false,
                'includeHistory' => false,
            ]);
            $orders = isset($snapshot['positions']) && is_array($snapshot['positions']) ? $snapshot['positions'] : [];
            return $this->applyLocalSymbolIds($orders, $platformKey);
        }

        return [];
    }

    /**
     * 根据平台调用第三方API获取未开单订单
     *
     * @param string $platformKey 平台标识
     * @param string $login 交易账户ID
     * @return array 订单数组
     */
    private function fetchPendingOrdersByPlatform($platformKey, $login) {
        if ($platformKey === 'financepro' && $this->isPlatformEnabled('financepro')) {
            // 检查配置中是否有 pending_orders 接口
            if (!isset($this->financePro['pending_orders']) || empty($this->financePro['pending_orders'])) {
                return [];
            }

            // 构建请求参数
            $payload = [
                'Logon' => $login,
                'Keywords' => null,
                'EnCode' => null,
                'Order' => null,
                'Sort' => 'Id',
                'PeriodFrom' => null,
                'PeriodTo' => null,
                'Filter' => [
                    'Login' => $login,
                    'SecurityId' => null,
                    'State' => null,
                    'Side' => null,
                    'Cmd' => null,
                    'DataPeriod' => null
                ],
                'CurrenetPageIndex' => 1,
                'PageSize' => 100,
                'RecordCount' => 0
            ];

            $response = $this->financeProClient->request(
                $this->financePro['pending_orders'],
                'POST',
                $payload,
                [
                    'expect_success_key' => 'Success',
                    'success_value' => true,
                    'error_message_key' => 'ErrMsg'
                ]
            );

            if (isset($response['Result']) && is_array($response['Result'])) {
                return $response['Result'];
            }
            return [];
        }
        if ($platformKey === 'mt5' && $this->isPlatformEnabled('mt5')) {
            $snapshot = $this->mt5GatewayClient->getAccountOrdersBalanceSnapshot($login, [
                'includeBalance' => false,
                'includePositions' => false,
                'includePending' => true,
                'includeHistory' => false,
                'offset' => 0,
                'limit' => 500,
            ]);
            $orders = isset($snapshot['pendingOrders']) && is_array($snapshot['pendingOrders']) ? $snapshot['pendingOrders'] : [];
            return $this->applyLocalSymbolIds($orders, $platformKey);
        }
        if ($platformKey === 'mt4' && $this->isPlatformEnabled('mt4')) {
            $snapshot = $this->mt4ApiClient->getAccountOrdersBalanceSnapshot($login, [
                'includeBalance' => false,
                'includePositions' => false,
                'includePending' => true,
                'includeHistory' => false,
            ]);
            $orders = isset($snapshot['pendingOrders']) && is_array($snapshot['pendingOrders']) ? $snapshot['pendingOrders'] : [];
            return $this->applyLocalSymbolIds($orders, $platformKey);
        }

        return [];
    }

    /**
     * 获取可用平台及杠杆选项
     */
    public function getPlatforms() {
        $client = $this->requireClient();
        $this->respondPlatformsForClient($client);
    }

    /**
     * 后台获取可开户平台；不绑定具体客户时不返回 parentIbGroup。
     */
    public function getAdminPlatforms() {
        $this->respondPlatformsForClient(null);
    }

    private function respondPlatformsForClient(?array $client) {
        $platforms = $this->platformModel->getEnabledPlatforms();
        $result = [];

        foreach ($platforms as $platform) {
            if (!empty($platform['features'])) {
                $decoded = json_decode($platform['features'], true);
                $platform['features'] = is_array($decoded) ? $decoded : [];
            } else {
                $platform['features'] = [];
            }
            $leverages = $this->leverageModel->getEnabledByPlatform($platform['id']);
            $platform['leverages'] = $leverages;
            $platform['requirePassword'] = $this->isPasswordRequiredForPlatform($platform);
            $downloadConfig = $this->getPlatformDownloadConfig($platform['platformKey'] ?? '');
            if ($downloadConfig['download'] !== null && $downloadConfig['url'] !== null) {
                $platform['download'] = $downloadConfig['download'];
                $platform['url'] = $downloadConfig['url'];
            }
            $result[] = $platform;
        }

        // 若客户绑定了父级 IB，返回其组别信息（页面显示组别 Label，开户时传 trading_id）
        $parentIbGroup = $client ? $this->getParentIbGroupInfo($client['userId']) : null;

        Response::success([
            'platforms' => $result,
            'currencyOptions' => [
                [
                    'code' => 'USD',
                    'displayName' => 'USD - US Dollar',
                    'isDefault' => true
                ]
            ],
            'defaultAccountType' => 'Standard',
            'parentIbGroup' => $parentIbGroup
        ], 'Platforms loaded');
    }

    /**
     * 获取已开启的平台下载配置。
     */
    public function getPlatformDownloads() {
        $this->requireClient();

        $platforms = $this->platformModel->getEnabledPlatforms();
        $result = [];

        foreach ($platforms as $platform) {
            $platformKey = $platform['platformKey'] ?? '';
            $downloadConfig = $this->getPlatformDownloadConfig($platformKey);
            if ($downloadConfig['download'] === null || $downloadConfig['url'] === null) {
                continue;
            }
            $result[$platformKey] = $downloadConfig;
        }

        Response::success($result, 'Platform downloads loaded');
    }

    /**
     * 获取客户绑定的父级 IB 的组别信息（若有）
     * 用于开户页：若绑定则 Account Type 显示该组别 Label，接口传该组别 trading_id
     * @param int $clientUserId clientUsers.id
     * @return array|null ['groupName' => string, 'groupLabel' => string, 'tradingId' => int, 'platformKey' => string] 或 null
     */
    private function getParentIbGroupInfo($clientUserId) {
        $db = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT tg.name AS groupName, tg.label AS groupLabel, tg.trading_id AS tradingId, tg.trading_platforms_key AS platformKey
             FROM ib_partner_bind b
             INNER JOIN ibPartners ib ON ib.id = b.parentId
             INNER JOIN trading_group tg ON tg.id = ib.groupId
             WHERE b.childClientId = :clientId AND b.isClient = 1 AND ib.groupId IS NOT NULL
             LIMIT 1',
            ['clientId' => $clientUserId]
        );
        if (!$row || $row['tradingId'] === null) {
            return null;
        }
        return [
            'groupName' => (string) $row['groupName'],
            'groupLabel' => trim((string)($row['groupLabel'] ?? '')),
            'tradingId' => (int) $row['tradingId'],
            'platformKey' => (string) ($row['platformKey'] ?? '')
        ];
    }

    /**
     * 获取当前用户的交易账户
     * 优先使用 platformBalance（从交易平台同步的余额）
     * 如果没有 platformBalance，则从 internalTransfers 表中计算余额
     */
    public function getAccounts() {
        $client = $this->requireClient();
        $userId = $client['userId'];
        $accounts = $this->accountModel->getAccountsByUser($userId);

        // 为每个账户设置余额
        $accountsWithBalance = [];
        foreach ($accounts as $account) {
            $accountId = $account['id'];

            $account = $this->applyGroupDefaultsToAccount($account);

            // 优先使用 platformBalance（从交易平台同步的余额）
            if (isset($account['platformBalance']) && $account['platformBalance'] !== null) {
                $account['availableBalance'] = (float)$account['platformBalance'];
            } else {
                // 如果没有 platformBalance，从 internalTransfers 表中计算余额
                // 余额 = 作为接收方的转入金额总和 - 作为转出方的转出金额总和
                $balance = $this->calculateAccountBalanceFromTransfers($accountId, $userId);
                $account['availableBalance'] = $balance;
            }

            // credit 直接读 platformCredit 快照（getAccountsByUser 已 JOIN），不调实时接口
            $account['credit'] = isset($account['platformCredit']) ? (float)$account['platformCredit'] : 0.0;

            $accountsWithBalance[] = $account;
        }

        Response::success([
            'accounts' => $accountsWithBalance
        ], 'Accounts loaded');
    }

    /**
     * 客户自助修改交易账户杠杆 — POST /api/trading/accounts/leverage
     * Body: { tradingAccountId, leverageValue }
     * 校验账户归属 + 档位是否平台启用，再调平台改杠杆，本地快照同步。
     */
    public function changeLeverage() {
        $client = $this->requireClient();
        $userId = (int)$client['userId'];

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $tradingAccountId = isset($data['tradingAccountId']) ? (int)$data['tradingAccountId'] : 0;
        $leverageValue = trim((string)($data['leverageValue'] ?? ''));

        if ($tradingAccountId <= 0) {
            Response::error('tradingAccountId is required', 400);
        }
        if ($leverageValue === '') {
            Response::error('leverageValue is required', 400);
        }

        // 账户归属校验：只能改自己的账户
        $account = $this->accountModel->findById($tradingAccountId);
        if (!$account || (int)($account['userId'] ?? 0) !== $userId) {
            Response::error('Trading account not found', 404);
        }
        $platformId = (int)($account['platformId'] ?? 0);

        // 档位必须是该平台启用的
        if ($platformId <= 0 || !$this->leverageModel->isLeverageEnabled($platformId, $leverageValue)) {
            Response::error('Selected leverage is not available for this platform', 400, [
                'leverageValue' => ['Selected leverage is not available for this platform']
            ]);
        }

        // 平台绑定
        $ext = $this->externalAccountModel->findByTradingAccount($tradingAccountId);
        if (!$ext || empty($ext['providerAccountId'])) {
            Response::error('Trading account platform binding not found', 404);
        }
        $platformKey = (string)($ext['providerKey'] ?? '');
        $login = (string)$ext['providerAccountId'];
        $numeric = $this->extractMt5LeverageNumberOrZero($leverageValue);

        if (!in_array($platformKey, ['mt5', 'mt4', 'financepro'], true)) {
            Response::error('Unsupported platform: ' . $platformKey, 400);
        }

        try {
            if ($platformKey === 'mt5') {
                try {
                    $this->mt5ApiClient->updateUser($login, ['leverage' => $numeric]);
                } finally {
                    $this->mt5ApiClient->disconnect();
                }
            } elseif ($platformKey === 'mt4') {
                $this->mt4ApiClient->updateUser($login, ['leverage' => $numeric]);
            } else {
                // financepro：改杠杆走 EditAccount，需带一整套必填字段（用落库资料兜底）
                $this->editFinanceProAccount($login, [
                    'groupTradingId' => (int)($ext['groupId'] ?? 0),
                    'status'         => (int)($ext['status'] ?? 1),
                    'name'           => (string)($ext['name'] ?? ''),
                    'email'          => (string)($ext['email'] ?? ''),
                    'leverageValue'  => $leverageValue,
                ]);
            }
        } catch (Exception $e) {
            Logger::error('Client change leverage failed', [
                'tradingAccountId' => $tradingAccountId,
                'platformKey'      => $platformKey,
                'leverageValue'    => $leverageValue,
                'userId'           => $userId,
                'error'            => $e->getMessage(),
            ]);
            Response::error('Failed to change leverage: ' . $e->getMessage(), 500);
            return;
        }

        // 本地杠杆快照（平台为准，后续同步也会覆盖）
        if ($numeric > 0) {
            $this->externalAccountModel->updateLeverage($login, $numeric);
        }

        Logger::info('Client changed leverage', [
            'tradingAccountId' => $tradingAccountId,
            'platformKey'      => $platformKey,
            'leverageValue'    => $leverageValue,
            'userId'           => $userId,
        ]);

        Response::success([
            'tradingAccountId' => $tradingAccountId,
            'leverageValue'    => $leverageValue,
        ], 'Leverage updated');
    }

    /**
     * 客户自助修改交易密码 — POST /api/trading/accounts/password
     * Body: { tradingAccountId, code, newPassword }
     * 先校验账户归属 + 邮箱验证码（发到本人邮箱），再改平台交易密码（MT5=main / MT4=trade）。
     * 密码不落库、响应不回显。
     */
    public function changeTradingPassword() {
        $client = $this->requireClient();
        $userId = (int)$client['userId'];

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $tradingAccountId = isset($data['tradingAccountId']) ? (int)$data['tradingAccountId'] : 0;
        $code = trim((string)($data['code'] ?? ''));
        $newPassword = (string)($data['newPassword'] ?? '');

        if ($tradingAccountId <= 0) {
            Response::error('tradingAccountId is required', 400);
        }
        if ($code === '') {
            Response::error('verification code is required', 400);
        }
        if (!Password::validateMt5Password($newPassword)) {
            Response::error('Password does not meet complexity requirements', 400, [
                'newPassword' => ['Password does not meet complexity requirements']
            ]);
        }

        // 归属校验：只能改自己的账户
        $account = $this->accountModel->findById($tradingAccountId);
        if (!$account || (int)($account['userId'] ?? 0) !== $userId) {
            Response::error('Trading account not found', 404);
        }

        // 邮箱验证码：发到本人邮箱、按 userId + client 校验（复用通用 OTP）
        $user = $this->userModel->findById($userId);
        $email = trim((string)($user['email'] ?? ''));
        if ($email === '') {
            Response::error('No email on file for verification', 400);
        }
        $otpModel = new EmailOtpVerification();
        $verify = $otpModel->verifyOTP($userId, 'client', $code, $email);
        if (empty($verify['success'])) {
            Response::error($verify['error'] ?? 'Invalid verification code', 400, [
                'code' => ['Invalid verification code']
            ]);
        }

        // 平台绑定
        $ext = $this->externalAccountModel->findByTradingAccount($tradingAccountId);
        if (!$ext || empty($ext['providerAccountId'])) {
            Response::error('Trading account platform binding not found', 404);
        }
        $platformKey = (string)($ext['providerKey'] ?? '');
        $login = (string)$ext['providerAccountId'];
        if (!in_array($platformKey, ['mt5', 'mt4', 'financepro'], true)) {
            Response::error('Unsupported platform: ' . $platformKey, 400);
        }

        try {
            if ($platformKey === 'mt5') {
                try { $this->mt5ApiClient->changePassword($login, $newPassword, 'main'); }
                finally { $this->mt5ApiClient->disconnect(); }
            } elseif ($platformKey === 'mt4') {
                $this->mt4ApiClient->changePassword($login, $newPassword, 'trade');
            } else {
                $this->financeProClient->resetPassword($email, $newPassword);
            }
        } catch (Exception $e) {
            Logger::error('Client change trading password failed', [
                'tradingAccountId' => $tradingAccountId,
                'platformKey'      => $platformKey,
                'userId'           => $userId,
                'error'            => $e->getMessage(),
            ]);
            Response::error('Failed to change password: ' . $e->getMessage(), 500);
            return;
        }

        Logger::info('Client changed trading password', [
            'tradingAccountId' => $tradingAccountId,
            'platformKey'      => $platformKey,
            'userId'           => $userId,
        ]);

        // 响应绝不含密码
        Response::success(['tradingAccountId' => $tradingAccountId], 'Password updated');
    }

    /**
     * 客户修改交易账户在 CRM 上显示的名字 — POST /api/trading/accounts/name
     * Body: { tradingAccountId, name }
     * 只更新 CRM 展示名 tradingAccounts.accountNickname，不碰平台。
     */
    public function changeAccountName() {
        $client = $this->requireClient();
        $userId = (int)$client['userId'];

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $tradingAccountId = isset($data['tradingAccountId']) ? (int)$data['tradingAccountId'] : 0;
        $name = trim((string)($data['name'] ?? ''));

        if ($tradingAccountId <= 0) {
            Response::error('tradingAccountId is required', 400);
        }
        if ($name === '') {
            Response::error('name is required', 400);
        }
        if (mb_strlen($name) > 100) {
            Response::error('name is too long (max 100)', 400);
        }

        // 归属校验：只能改自己的账户
        $account = $this->accountModel->findById($tradingAccountId);
        if (!$account || (int)($account['userId'] ?? 0) !== $userId) {
            Response::error('Trading account not found', 404);
        }

        $this->accountModel->update($tradingAccountId, ['accountNickname' => $name]);

        Response::success([
            'tradingAccountId' => $tradingAccountId,
            'accountNickname'  => $name,
        ], 'Account name updated');
    }

    /**
     * 用外部平台账户数据修正账户展示字段。
     */
    private function applyGroupDefaultsToAccount(array $account) {
        if (!empty($account['providerAccountId'])) {
            $account['accountNumber'] = (string)$account['providerAccountId'];
        }

        if (array_key_exists('externalLeverage', $account) && $account['externalLeverage'] !== null && $account['externalLeverage'] !== '') {
            $account['leverageValue'] = $this->formatLeverageValueForDisplay($account['externalLeverage']);
        }

        if (!empty($account['groupDepositCurrency'])) {
            $account['accountCurrency'] = strtoupper((string)$account['groupDepositCurrency']);
        }

        if (isset($account['groupDepositByDefault']) && $account['groupDepositByDefault'] !== null && $account['groupDepositByDefault'] !== '') {
            $account['initialDeposit'] = (float)$account['groupDepositByDefault'];
        }

        return $account;
    }

    /**
     * 从internalTransfers表中计算账户余额
     * 余额 = 作为接收方(toTradingAccountId)的转入金额总和 - 作为转出方(fromTradingAccountId)的转出金额总和
     * 只计算状态为'completed'的转账记录
     *
     * @param int $tradingAccountId 交易账户ID
     * @param int $userId 用户ID
     * @return float 账户余额
     */
    private function calculateAccountBalanceFromTransfers($tradingAccountId, $userId) {
        try {
            $db = Database::getInstance();
            $context = $this->tradingAccountAmountService->getAccountContext((int)$tradingAccountId);

            // 优先使用转账快照中的平台金额；旧数据缺失时按当前账户 scale 回退换算。
            $incomingSql = "SELECT amount, toPlatformAmount
                           FROM internalTransfers
                           WHERE toTradingAccountId = :accountId
                           AND userId = :userId
                           AND status = 'completed'";
            $incomingRows = $db->fetchAll($incomingSql, [
                'accountId' => $tradingAccountId,
                'userId' => $userId
            ]);
            $totalIncoming = 0.0;
            foreach ($incomingRows as $row) {
                if (isset($row['toPlatformAmount']) && $row['toPlatformAmount'] !== null && $row['toPlatformAmount'] !== '') {
                    $totalIncoming += (float)$row['toPlatformAmount'];
                } else {
                    $totalIncoming += $this->tradingAccountAmountService->convertBaseToPlatformAmount((float)($row['amount'] ?? 0), $context);
                }
            }

            $outgoingSql = "SELECT amount, fromPlatformAmount
                           FROM internalTransfers
                           WHERE fromTradingAccountId = :accountId
                           AND userId = :userId
                           AND fromType = 'trading_account'
                           AND status = 'completed'";
            $outgoingRows = $db->fetchAll($outgoingSql, [
                'accountId' => $tradingAccountId,
                'userId' => $userId
            ]);
            $totalOutgoing = 0.0;
            foreach ($outgoingRows as $row) {
                if (isset($row['fromPlatformAmount']) && $row['fromPlatformAmount'] !== null && $row['fromPlatformAmount'] !== '') {
                    $totalOutgoing += (float)$row['fromPlatformAmount'];
                } else {
                    $totalOutgoing += $this->tradingAccountAmountService->convertBaseToPlatformAmount((float)($row['amount'] ?? 0), $context);
                }
            }

            // 余额 = 转入总额 - 转出总额
            $balance = $totalIncoming - $totalOutgoing;

            return max(0, $balance); // 确保余额不为负数
        } catch (Exception $e) {
//            Logger::error('Failed to calculate account balance from transfers', [
//                'accountId' => $tradingAccountId,
//                'userId' => $userId,
//                'exception' => $e->getMessage()
//            ]);
            return 0;
        }
    }

    /**
     * 计算 CRM Wallet 可用余额。
     * 公式与 deposits/available-balance 接口保持一致。
     */
    private function calculateWalletAvailableBalance($userId) {
        return $this->walletBalanceService->getAvailableBalance((int)$userId);
    }

    /**
     * 将平台返回的金额按账户组 scale 归一化到 CRM 基准金额。
     */
    private function normalizeSummaryAmount($amount, array $account): float {
        return $this->tradingAccountAmountService->convertPlatformToBaseAmount(
            (float)$amount,
            ['scale' => $account['groupScale'] ?? 1]
        );
    }

    /**
     * 获取账户总览信息
     * 通过第三方交易平台接口获取账户总览数据
     */
    public function getAccountSummary() {
        $client = $this->requireClient();
        $userId = $client['userId'];
        try {
            $walletBalance = $this->calculateWalletAvailableBalance($userId);
            $accounts = $this->accountModel->getAccountsByUser($userId);

            $summary = [
                'Total' => $walletBalance,
                'Wallet' => $walletBalance,
                'Balance' => 0.0,
                'Credit' => 0.0,
                'Equity' => 0.0,
                'Margin' => 0.0,
                'Currency' => 'USD'
            ];

            if (empty($accounts)) {
                Response::success($summary, 'Account summary loaded');
                return;
            }

            foreach ($accounts as $account) {
                $platformKey = (string)($account['platformKey'] ?? '');
                $accountCurrency = strtoupper((string)($account['accountCurrency'] ?? 'USD'));
                if ($summary['Currency'] === 'USD' && $accountCurrency !== '') {
                    $summary['Currency'] = $accountCurrency;
                }

                // Credit 不走实时接口，直接读 tradingAccountExternalAccounts.platformCredit
                // （getAccountsByUser 已 JOIN 这一列）；同时纳入合计但绝不并入 wallet 余额
                $platformCredit = isset($account['platformCredit']) ? (float)$account['platformCredit'] : 0.0;
                $summary['Credit'] += $this->normalizeSummaryAmount($platformCredit, $account);

//                $externalAccount = $this->externalAccountModel->findByTradingAccount($account['id']);
//                $providerAccountId = (string)($externalAccount['providerAccountId'] ?? '');
//
//                if ($platformKey === 'mt5' && $providerAccountId !== '') {
//                    try {
//                        $mt5Balance = $this->mt5ApiClient->getBalance($providerAccountId);
//                        $balance = isset($mt5Balance['balance']) ? (float)$mt5Balance['balance'] : (float)($account['platformBalance'] ?? 0);
//                        $equity = isset($mt5Balance['equity']) ? (float)$mt5Balance['equity'] : $balance;
//                        $margin = isset($mt5Balance['margin']) ? (float)$mt5Balance['margin'] : 0.0;
//
//                        $summary['Balance'] += $this->normalizeSummaryAmount($balance, $account);
//                        $summary['Equity'] += $this->normalizeSummaryAmount($equity, $account);
//                        $summary['Margin'] += $this->normalizeSummaryAmount($margin, $account);
//                        continue;
//                    } catch (Exception $e) {
//                        // MT5 拉取失败时回退到已同步的 platformBalance
//                    }
//                }
//
//                if ($platformKey === 'mt4' && $providerAccountId !== '') {
//                    try {
//                        $mt4Balance = $this->mt4ApiClient->getBalance($providerAccountId);
//                        $balance = isset($mt4Balance['balance']) ? (float)$mt4Balance['balance'] : (float)($account['platformBalance'] ?? 0);
//                        $equity = isset($mt4Balance['equity']) ? (float)$mt4Balance['equity'] : $balance;
//                        $margin = isset($mt4Balance['margin']) ? (float)$mt4Balance['margin'] : 0.0;
//
//                        $summary['Balance'] += $this->normalizeSummaryAmount($balance, $account);
//                        $summary['Equity'] += $this->normalizeSummaryAmount($equity, $account);
//                        $summary['Margin'] += $this->normalizeSummaryAmount($margin, $account);
//                        continue;
//                    } catch (Exception $e) {
//                        // MT4 拉取失败时回退到已同步的 platformBalance
//                    }
//                }
//
//                if ($platformKey === 'financepro'
//                    && !empty($this->financePro['get_client_summary'])
//                    && $providerAccountId !== '') {
//                    try {
//                        $response = $this->financeProClient->request(
//                            $this->financePro['get_client_summary'],
//                            'GET',
//                            ['accountId' => $providerAccountId],
//                            [
//                                'expect_success_key' => 'Success',
//                                'success_value' => true,
//                                'error_message_key' => 'ErrMsg'
//                            ]
//                        );
//
//                        $resData = isset($response['ResData']) && is_array($response['ResData']) ? $response['ResData'] : [];
//                        $balance = isset($resData['Balance']) ? (float)$resData['Balance'] : (float)($account['platformBalance'] ?? 0);
//                        $equity = isset($resData['Equity']) ? (float)$resData['Equity'] : $balance;
//                        $margin = isset($resData['Margin']) ? (float)$resData['Margin'] : 0.0;
//
//                        $summary['Balance'] += $this->normalizeSummaryAmount($balance, $account);
//                        $summary['Equity'] += $this->normalizeSummaryAmount($equity, $account);
//                        $summary['Margin'] += $this->normalizeSummaryAmount($margin, $account);
//
//                        if (!empty($resData['Currency']) && $summary['Currency'] === 'USD') {
//                            $summary['Currency'] = (string)$resData['Currency'];
//                        }
//                        continue;
//                    } catch (Exception $e) {
//                        // FinancePro 拉取失败时回退到已同步的 platformBalance
//                    }
//                }

                $fallbackBalance = isset($account['platformBalance']) && $account['platformBalance'] !== null
                    ? (float)$account['platformBalance']
                    : $this->calculateAccountBalanceFromTransfers((int)$account['id'], $userId);

                $normalizedFallbackBalance = $this->normalizeSummaryAmount($fallbackBalance, $account);
                $summary['Balance'] += $normalizedFallbackBalance;
                $summary['Equity'] += $normalizedFallbackBalance;
            }

            // 总计 = wallet 可用 + 交易账户余额 + 交易账户 credit；credit 仅在此处合计，不进 wallet
            $summary['Total'] = $walletBalance + $summary['Balance'] + $summary['Credit'];

            Response::success($summary, 'Account summary loaded');
        } catch (Exception $e) {
            Response::error('Failed to fetch account summary: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取历史订单列表
     * 从本地数据库分页查询历史订单数据（定时任务已同步）
     * 支持多平台（mt4、mt5、financepro），目前只对接financepro
     */
    public function getOrderHistory() {
        $client = $this->requireClient();
        $userId = $client['userId'];

        // 获取请求参数
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 20;
        $keywords = isset($_GET['keywords']) ? $_GET['keywords'] : null;
        $cmd = isset($_GET['cmd']) ? $_GET['cmd'] : null;
        $periodFrom = isset($_GET['periodFrom']) ? $_GET['periodFrom'] : null;
        $periodTo = isset($_GET['periodTo']) ? $_GET['periodTo'] : null;
        $sort = isset($_GET['sort']) ? $_GET['sort'] : 'Id';
        $sortOrder = isset($_GET['sortOrder']) ? strtoupper($_GET['sortOrder']) : 'DESC';

        try {
            // 第一步：根据userId查询tradingAccounts和tradingAccountExternalAccounts表，获取所有平台的账户
            $db = Database::getInstance();
            $sql = "SELECT
                        ta.id as tradingAccountId,
                        ta.userId,
                        ta.status as accountStatus,
                        tp.id as platformId,
                        tp.platformKey,
                        tp.displayName as platformName,
                        tea.id as externalAccountId,
                        tea.providerAccountId,
                        tea.providerKey
                    FROM tradingAccounts ta
                    INNER JOIN tradingPlatforms tp ON tp.id = ta.platformId
                    LEFT JOIN tradingAccountExternalAccounts tea ON tea.tradingAccountId = ta.id
                    WHERE ta.userId = :userId
                    AND ta.status = 'active'
                    AND tea.providerAccountId IS NOT NULL
                    AND tea.providerAccountId != ''
                    ORDER BY tp.platformKey";

            $userAccounts = $db->fetchAll($sql, ['userId' => $userId]);

            if (empty($userAccounts)) {
                // 用户没有任何交易账户，返回空数据
                $result = [
                    'items' => [],
                    'filters' => [],
                    'pagination' => [
                        'currentPage' => $page,
                        'pageSize' => $pageSize,
                        'total' => 0,
                        'hasMore' => false
                    ]
                ];
                Response::success($result, 'Order history loaded');
                return;
            }

            // 第二步：从数据库分页查询订单数据（合并所有平台的订单）
            // 构建筛选条件
            $filtersForDb = [
                'trading_status' => 2, // 历史订单
            ];

            if ($keywords) {
                $filtersForDb['keywords'] = $keywords;
            }

            if ($cmd) {
                $filtersForDb['cmd'] = $cmd;
            }

            if ($periodFrom) {
                $filtersForDb['periodFrom'] = $periodFrom;
            }

            if ($periodTo) {
                $filtersForDb['periodTo'] = $periodTo;
            }

            // 获取所有平台的登录账户列表和平台标识
            $logins = [];
            $platformKeys = [];
            foreach ($userAccounts as $account) {
                if (!empty($account['providerAccountId'])) {
                    $logins[] = $account['providerAccountId'];
                    if (!in_array($account['platformKey'], $platformKeys)) {
                        $platformKeys[] = $account['platformKey'];
                    }
                }
            }

            // 如果没有任何账户，返回空数据
            if (empty($logins)) {
                $result = [
                    'items' => [],
                    'filters' => [],
                    'pagination' => [
                        'currentPage' => $page,
                        'pageSize' => $pageSize,
                        'total' => 0,
                        'hasMore' => false
                    ]
                ];
                Response::success($result, 'Order history loaded');
                return;
            }

            // 从数据库查询订单（支持多平台）
            $dbResult = $this->orderModel->getOrderHistoryByLogins(
                $logins,
                $platformKeys,
                $filtersForDb,
                $page,
                $pageSize,
                $sort,
                $sortOrder
            );

            // 为每个订单添加平台名称
            $items = isset($dbResult['items']) && is_array($dbResult['items']) ? $dbResult['items'] : [];
            $platformNames = []; // 存储平台名称映射
            foreach ($userAccounts as $account) {
                $platformKey = $account['platformKey'];
                if (!in_array($platformKey, $platformKeys)) {
                    continue;
                }
                if (!isset($platformNames[$platformKey])) {
                    $platformNames[$platformKey] = $account['platformName'];
                }
            }

            foreach ($items as &$item) {
                // 根据trading_platforms_key查找平台名称
                $platformKey = isset($item['trading_platforms_key']) ? $item['trading_platforms_key'] : '';
                $item['PlatformName'] = isset($platformNames[$platformKey]) ? $platformNames[$platformKey] : '';
            }

            // 返回结果
            $result = [
                'items' => $items,
                'filters' => [],
                    'pagination' => [
                        'currentPage' => isset($dbResult['page']) ? $dbResult['page'] : $page,
                        'pageSize' => isset($dbResult['pageSize']) ? $dbResult['pageSize'] : $pageSize,
                        'total' => isset($dbResult['total']) ? $dbResult['total'] : 0,
                        'hasMore' => isset($dbResult['hasMore']) ? $dbResult['hasMore'] : false
                    ]
            ];

            Response::success($result, 'Order history loaded');
        } catch (Exception $e) {
            // Logger::error('Failed to fetch order history: ' . $e->getMessage());
            Response::error('Failed to fetch order history: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取持仓列表（开单持仓订单）
     * 调用第三方API获取订单并存储到数据库，然后从数据库查询返回
     */
    public function getOpenPositions() {
        $client = $this->requireClient();
        $userId = $client['userId'];

        try {
            // 第一步：根据userId查询tradingAccounts和tradingAccountExternalAccounts表，获取所有平台的账户
            $db = Database::getInstance();
            $sql = "SELECT
                        ta.id as tradingAccountId,
                        ta.userId,
                        ta.status as accountStatus,
                        tp.id as platformId,
                        tp.platformKey,
                        tp.displayName as platformName,
                        tea.id as externalAccountId,
                        tea.providerAccountId,
                        tea.providerKey
                    FROM tradingAccounts ta
                    INNER JOIN tradingPlatforms tp ON tp.id = ta.platformId
                    LEFT JOIN tradingAccountExternalAccounts tea ON tea.tradingAccountId = ta.id
                    WHERE ta.userId = :userId
                    AND ta.status = 'active'
                    AND tea.providerAccountId IS NOT NULL
                    AND tea.providerAccountId != ''
                    ORDER BY tp.platformKey";

            $userAccounts = $db->fetchAll($sql, ['userId' => $userId]);

            if (empty($userAccounts)) {
                Response::success(['items' => []], 'Open positions loaded');
                return;
            }

            // 开单持仓只读后台已同步的数据，不再每次请求都实时调用第三方 API 拉取 + 回写库。
            // 订单同步由后台定时任务维护，前端只负责展示。下面第二步已整体注释保留。

            // 第二步：遍历平台账户，调用第三方API获取开单持仓订单并存储到数据库
//            $processedPlatforms = [];
//            foreach ($userAccounts as $account) {
//                $platformKey = $account['platformKey'];
//
//                // 根据配置判断平台是否已对接
//                if (!$this->isPlatformEnabled($platformKey)) {
//                    continue;
//                }
//
//                // 检查是否有外部账户信息
//                if (empty($account['providerAccountId'])) {
//                    continue;
//                }
//
//                $login = $account['providerAccountId'];
//
//                try {
//                    // 根据平台调用第三方API获取开单持仓订单
//                    $orders = $this->fetchOpenOrdersByPlatform($platformKey, $login);
//
//                    // 将订单数据批量存储到数据库
//                    if (!empty($orders)) {
//                        try {
//                            //开单，$tradingStatus = 0
//                            $this->orderModel->batchUpsertActiveOrders(0, $orders, $login, $platformKey);
//                        } catch (Exception $e) {
//                            // Logger::error('Failed to batch upsert open orders: ' . $e->getMessage());
//                        }
//                    }
//
//                    $processedPlatforms[] = $platformKey;
//                } catch (Exception $e) {
//                    // Logger::error("Failed to fetch open orders for platform {$platformKey}: " . $e->getMessage());
//                }
//            }

            // 第三步：从数据库查询开单持仓订单（trading_status = 0）
            $logins = [];
            $platformKeys = [];
            $platformNames = []; // 存储平台名称映射
            // 取所有已对接平台、且有外部账号的活跃账户（原先按 $processedPlatforms 过滤，现改为已对接平台）
            foreach ($userAccounts as $account) {
                $platformKey = $account['platformKey'];
                if ($this->isPlatformEnabled($platformKey) && !empty($account['providerAccountId'])) {
                    $logins[] = $account['providerAccountId'];
                    if (!in_array($platformKey, $platformKeys)) {
                        $platformKeys[] = $platformKey;
                        $platformNames[$platformKey] = $account['platformName'];
                    }
                }
            }

            if (empty($logins)) {
                Response::success(['items' => []], 'Open positions loaded');
                return;
            }

            // 根据有效账户及对应的平台从数据库查询开单（不分页，返回所有数据）
            $dbResult = $this->orderModel->getOrderHistoryByLogins(
                $logins,
                $platformKeys,
                ['trading_status' => 0], // 开单持仓
                1,
                100, // 设置一个很大的值，返回所有数据
                'Id',
                'DESC'
            );

            // 为每个订单添加平台名称
            $items = isset($dbResult['items']) && is_array($dbResult['items']) ? $dbResult['items'] : [];
            foreach ($items as &$item) {
                // 根据trading_platforms_key查找平台名称
                $platformKey = isset($item['trading_platforms_key']) ? $item['trading_platforms_key'] : '';
                $item['PlatformName'] = isset($platformNames[$platformKey]) ? $platformNames[$platformKey] : '';
            }

            // 返回结果（不分页）
            $result = [
                'items' => $items
            ];

            Response::success($result, 'Open positions loaded');
        } catch (Exception $e) {
            // Logger::error('Failed to fetch open positions: ' . $e->getMessage());
            Response::error('Failed to fetch open positions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取挂单列表（未开单订单）
     * 调用第三方API获取订单并存储到数据库，然后从数据库查询返回
     */
    public function getPendingOrders() {
        $client = $this->requireClient();
        $userId = $client['userId'];

        try {
            // 第一步：根据userId查询tradingAccounts和tradingAccountExternalAccounts表，获取所有平台的账户
            $db = Database::getInstance();
            $sql = "SELECT
                        ta.id as tradingAccountId,
                        ta.userId,
                        ta.status as accountStatus,
                        tp.id as platformId,
                        tp.platformKey,
                        tp.displayName as platformName,
                        tea.id as externalAccountId,
                        tea.providerAccountId,
                        tea.providerKey
                    FROM tradingAccounts ta
                    INNER JOIN tradingPlatforms tp ON tp.id = ta.platformId
                    LEFT JOIN tradingAccountExternalAccounts tea ON tea.tradingAccountId = ta.id
                    WHERE ta.userId = :userId
                    AND ta.status = 'active'
                    AND tea.providerAccountId IS NOT NULL
                    AND tea.providerAccountId != ''
                    ORDER BY tp.platformKey";

            $userAccounts = $db->fetchAll($sql, ['userId' => $userId]);

            if (empty($userAccounts)) {
                Response::success(['items' => []], 'Pending orders loaded');
                return;
            }

            // 挂单只读后台已同步的数据，不再每次请求都实时调用第三方 API 拉取 + 回写库。
            // 订单同步由后台定时任务维护，前端只负责展示。下面第二步已整体注释保留。

            // 第二步：遍历平台账户，调用第三方API获取未开单订单并存储到数据库
//            $processedPlatforms = [];
//            foreach ($userAccounts as $account) {
//                $platformKey = $account['platformKey'];
//
//                // 根据配置判断平台是否已对接
//                if (!$this->isPlatformEnabled($platformKey)) {
//                    continue;
//                }
//
//                // 检查是否有外部账户信息
//                if (empty($account['providerAccountId'])) {
//                    continue;
//                }
//
//                $login = $account['providerAccountId'];
//
//                try {
//                    // 根据平台调用第三方API获取未开单订单
//                    $orders = $this->fetchPendingOrdersByPlatform($platformKey, $login);
//
//                    // 将订单数据批量存储到数据库
//                    if (!empty($orders)) {
//                        try {
//                            //未开单，$tradingStatus = 1
//                            $this->orderModel->batchUpsertActiveOrders(1,$orders, $login, $platformKey);
//                        } catch (Exception $e) {
//                            // Logger::error('Failed to batch upsert pending orders: ' . $e->getMessage());
//                        }
//                    }
//
//                    $processedPlatforms[] = $platformKey;
//                } catch (Exception $e) {
//                    // Logger::error("Failed to fetch pending orders for platform {$platformKey}: " . $e->getMessage());
//                }
//            }

            // 第三步：从数据库查询未开单订单（trading_status = 1）
            $logins = [];
            $platformKeys = [];
            $platformNames = []; // 存储平台名称映射
            // 取所有已对接平台、且有外部账号的活跃账户（原先按 $processedPlatforms 过滤，现改为已对接平台）
            foreach ($userAccounts as $account) {
                $platformKey = $account['platformKey'];
                if ($this->isPlatformEnabled($platformKey) && !empty($account['providerAccountId'])) {
                    $logins[] = $account['providerAccountId'];
                    if (!in_array($platformKey, $platformKeys)) {
                        $platformKeys[] = $platformKey;
                        $platformNames[$platformKey] = $account['platformName'];
                    }
                }
            }

            if (empty($logins)) {
                Response::success(['items' => []], 'Pending orders loaded');
                return;
            }

            // 根据有效账户及对应的平台从数据库查询未开单（不分页，返回所有数据）
            $dbResult = $this->orderModel->getOrderHistoryByLogins(
                $logins,
                $platformKeys,
                ['trading_status' => 1], // 未开单/挂单
                1,
                100, // 设置一个很大的值，返回所有数据
                'Id',
                'DESC'
            );

            // 为每个订单添加平台名称
            $items = isset($dbResult['items']) && is_array($dbResult['items']) ? $dbResult['items'] : [];
            foreach ($items as &$item) {
                // 根据trading_platforms_key查找平台名称
                $platformKey = isset($item['trading_platforms_key']) ? $item['trading_platforms_key'] : '';
                $item['PlatformName'] = isset($platformNames[$platformKey]) ? $platformNames[$platformKey] : '';
            }

            // 返回结果（不分页）
            $result = [
                'items' => $items
            ];

            Response::success($result, 'Pending orders loaded');
        } catch (Exception $e) {
            // Logger::error('Failed to fetch pending orders: ' . $e->getMessage());
            Response::error('Failed to fetch pending orders: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 创建新的交易账户
     * 客户端不再传交易密码——服务端生成符合 MT5/FP 要求的随机密码，回写到 response 由前端展示一次。
     */
    public function createAccount() {
        $client = $this->requireClient(true);
        $data = $this->readAccountCreateInput();
        $data['password'] = Password::generateMt5Password(8);
        $data['confirmPassword'] = $data['password'];

        $this->createAccountForClient($client, $data, [
            'includePasswordInResponse' => true,
            'generatedPassword' => $data['password']
        ]);
    }

    /**
     * 后台代客户开户：客户由 URL 指定，交易密码由系统生成并通过现有凭证邮件发送。
     */
    public function createAdminAccount($clientUserId) {
        $data = $this->readAccountCreateInput();
        $client = $this->requireClientById($clientUserId, true, $data);
        $data['password'] = Password::generateMt5Password(12);
        $data['confirmPassword'] = $data['password'];

        $this->createAccountForClient($client, $data, [
            'includePasswordInResponse' => true,
            'generatedPassword' => $data['password']
        ]);
    }

    private function readAccountCreateInput() {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $data = array_map(static function ($value) {
            return is_string($value) ? trim($value) : $value;
        }, $data);

        return $data;
    }

    private function createAccountForClient(array $client, array $data, array $options = []) {
        $clientUserId = (int) ($client['userId'] ?? 0);
        $accountValidator = new Validator($data, [
            'platformKey' => 'required',
            'accountNickname' => 'required|min:3|max:150',
            'initialDeposit' => 'required|numeric'
        ]);
        if (!$accountValidator->validate()) {
            $this->failTradingAccountCreate(
                $data,
                $clientUserId,
                OperationLogTextHelpers::validationErrorsToMessage($accountValidator->getErrors())
            );
            Response::validationError($accountValidator->getErrors());
        }

        $initialDeposit = (float)$data['initialDeposit'];
        if ($initialDeposit < 100) {
            $this->failTradingAccountCreate($data, $clientUserId, 'Initial deposit must be at least 100');
            Response::validationError([
                'initialDeposit' => ['Initial deposit must be at least 100']
            ], 'Initial deposit must be at least 100');
        }

        // 获取平台
        $platform = $this->platformModel->findByKey($data['platformKey']);
        if (!$platform || !$platform['isEnabled'] || !$this->isPlatformEnabled($platform['platformKey'] ?? '')) {
            $this->failTradingAccountCreate($data, $clientUserId, 'Selected platform is not available');
            Response::validationError([
                'platformKey' => ['Selected platform is not available']
            ], 'Selected platform is not available');
        }

        $platformAccountLimit = $this->getPlatformAccountLimit($platform);
        $activeAccountsOnPlatform = $this->accountModel->countActiveAccountsOnPlatform($client['userId'], $platform['id']);

        // 检查用户是否已经达到该平台的开户上限
        if ($activeAccountsOnPlatform >= $platformAccountLimit) {
            $limitMessage = "You have reached the account limit for this platform. Maximum allowed: {$platformAccountLimit} active account(s).";
            $this->failTradingAccountCreate($data, $clientUserId, $limitMessage);
            Response::validationError([
                'platformKey' => [$limitMessage]
            ], $limitMessage);
        }

        // 校验杠杆
        $leverageValue = isset($data['leverageValue']) ? trim((string)$data['leverageValue']) : '';

        $accountNickname = $data['accountNickname'];
        $accountCurrency = $data['accountCurrency'] ?? 'USD';
        $assignedCommissionRuleId = $this->parseAssignableCommissionRuleId(
            $data['assignedCommissionRuleId'] ?? null,
            (int) $client['userId'],
            true,
            $data
        );

        if ($this->isPasswordRequiredForPlatform($platform)
            && trim((string)($data['password'] ?? '')) === '') {
            $this->failTradingAccountCreate($data, $clientUserId, 'Password is required for this platform.');
            Response::validationError([
                'password' => ['Password is required for this platform.']
            ], 'Password is required for this platform.');
        }

        // 客户绑定了父级 IB
        $parentIbGroup = $this->getParentIbGroupInfo($client['userId']);
        if ($parentIbGroup && ($parentIbGroup['platformKey'] ?? '') === $platform['platformKey']) {
            $accountType = $parentIbGroup['groupLabel'] !== ''
                ? $parentIbGroup['groupLabel']
                : $parentIbGroup['groupName'];
            // 前端可传 trading_id，但以服务端查到的为准，供后续第三方开户等使用
            $groupTradingId = $parentIbGroup['tradingId'];
        } else {
            $requestedGroupTradingId = isset($data['groupTradingId']) && $data['groupTradingId'] !== ''
                ? (int)$data['groupTradingId']
                : null;

            $selectedGroup = null;
            if ($requestedGroupTradingId !== null) {
                $selectedGroup = $this->tradingGroupModel->findByTradingId($requestedGroupTradingId, $platform['platformKey']);
                if (!$selectedGroup || empty($selectedGroup['isDefault'])) {
                    $this->failTradingAccountCreate($data, $clientUserId, 'Selected trading group is not available for account opening.');
                    Response::validationError([
                        'groupTradingId' => ['Selected trading group is not available for account opening.']
                    ], 'Selected trading group is not available for account opening.');
                }
            }

            if ($selectedGroup) {
                $groupLabel = trim((string)($selectedGroup['label'] ?? ''));
                $accountType = $groupLabel !== ''
                    ? $groupLabel
                    : trim((string)($selectedGroup['name'] ?? ''));
            } else {
                $accountType = $data['accountType'] ?? 'Standard';
            }
            $groupTradingId = $selectedGroup['trading_id'] ?? null;
        }

        $mt5Group = null;
        if ($platform['platformKey'] === 'mt5') {
            if ($groupTradingId === null || $groupTradingId === '') {
                $this->failTradingAccountCreate($data, $clientUserId, 'MT5 account opening requires a valid trading group');
                Response::validationError([
                    'groupTradingId' => ['MT5 account opening requires a valid trading group']
                ], 'MT5 account opening requires a valid trading group');
            }
            $mt5Group = $this->resolveMt5GroupForOpenAccount((int)$groupTradingId);
        }

        $mt4Group = null;
        if ($platform['platformKey'] === 'mt4') {
            if ($groupTradingId === null || $groupTradingId === '') {
                $this->failTradingAccountCreate($data, $clientUserId, 'MT4 account opening requires a valid trading group');
                Response::validationError([
                    'groupTradingId' => ['MT4 account opening requires a valid trading group']
                ], 'MT4 account opening requires a valid trading group');
            }
            $mt4Group = $this->resolveMt4GroupForOpenAccount((int)$groupTradingId);
        }

        if ($leverageValue === '') {
            $this->failTradingAccountCreate($data, $clientUserId, 'Selected leverage is not available for this platform');
            Response::validationError([
                'leverageValue' => ['Selected leverage is not available for this platform']
            ], 'Selected leverage is not available for this platform');
        }

        if (!$this->leverageModel->isLeverageEnabled($platform['id'], $leverageValue)) {
            $this->failTradingAccountCreate($data, $clientUserId, 'Selected leverage is not available for this platform');
            Response::validationError([
                'leverageValue' => ['Selected leverage is not available for this platform']
            ], 'Selected leverage is not available for this platform');
        }

        // 生成唯一账号
        $accountNumber = $this->generateAccountNumber($platform['shortCode'], $data, $clientUserId);

        $accountId = null;
        $predefinedLogin = $client['user']['email'] ?? '';
        $plainCredentials = [];

        try {
            // 创建账户记录（不再传密码，InvestorPin 已删除）
            $accountId = $this->accountModel->create([
                'userId' => $client['userId'],
                'platformId' => $platform['id'],
                'accountNumber' => $accountNumber,
                'accountNickname' => $accountNickname,
                'accountCurrency' => strtoupper($accountCurrency),
                'accountType' => $accountType,
                'assignedCommissionRuleId' => $assignedCommissionRuleId,
                'leverageValue' => $leverageValue,
                'initialDeposit' => $initialDeposit,
                'predefinedLogin' => $predefinedLogin,
                'status' => 'active'
            ]);

            $createdAccount = $this->accountModel->findById($accountId);
            $accountDetails = $this->prepareAccountResponse($createdAccount, $platform);
            // 只有 financepro 平台才调用第三方接口
            if($platform['platformKey'] === 'financepro' && $this->financePro['openaccount']){
                $thirdPartyPayload = $this->buildThirdPartyPayload($client['user'], $createdAccount, $data, $platform['platformKey'], $groupTradingId, $leverageValue);
                $thirdPartyResponse = $this->financeProClient->request(
                    $this->financePro['openaccount'],
                    'POST',
                    $thirdPartyPayload,
                    [
                        'expect_success_key' => 'Success',
                        'success_value' => true,
                        'error_message_key' => 'ErrMsg'
                    ]
                );
                $this->persistThirdPartyAccount($accountId, $thirdPartyPayload, $thirdPartyResponse, $platform['platformKey'], $leverageValue);
                $plainCredentials = [
                    'masterPin' => $thirdPartyResponse['ResData']['MasterPin'] ?? ($thirdPartyPayload['MasterPin'] ?? null),
                    'investorPin' => $thirdPartyResponse['ResData']['InvestorPin'] ?? ($thirdPartyPayload['InvestorPin'] ?? null),
                    'phonePin' => $thirdPartyResponse['ResData']['PhonePin'] ?? ($thirdPartyPayload['PhonePin'] ?? null),
                ];
            } elseif ($platform['platformKey'] === 'mt5') {
                if (!$this->isPlatformEnabled('mt5')) {
                    throw new Exception('MT5 platform integration is disabled');
                }

                if ($mt5Group === null) {
                    $mt5Group = $this->resolveMt5GroupForOpenAccount((int)$groupTradingId);
                }
                $mt5Payload = $this->buildMt5OpenAccountPayload(
                    $client['user'],
                    $createdAccount,
                    $data,
                    $mt5Group['name'],
                    $mt5Group['passwordMinLength'],
                    $leverageValue
                );

                $mt5User = [];
                try {
                    $mt5CreateResult = $this->mt5ApiClient->createUser($mt5Payload);
                    if (!empty($mt5CreateResult['login'])) {
                        $mt5User = $this->mt5ApiClient->getUser($mt5CreateResult['login']);
                    }
                } finally {
                    $this->mt5ApiClient->disconnect();
                }

                $this->persistMt5ThirdPartyAccount(
                    $accountId,
                    $mt5Payload,
                    $mt5CreateResult,
                    $mt5User,
                    $mt5Group
                );
                $plainCredentials = [
                    'masterPin' => $mt5CreateResult['mainPassword'] ?? null,
                    'investorPin' => $mt5CreateResult['investPassword'] ?? null,
                    'phonePin' => $mt5User['PhonePassword'] ?? null,
                ];
            } elseif ($platform['platformKey'] === 'mt4') {
                if (!$this->isPlatformEnabled('mt4')) {
                    throw new Exception('MT4 platform integration is disabled');
                }

                if ($mt4Group === null) {
                    $mt4Group = $this->resolveMt4GroupForOpenAccount((int)$groupTradingId);
                }
                $mt4Payload = $this->buildMt4OpenAccountPayload(
                    $client['user'],
                    $createdAccount,
                    $data,
                    $mt4Group['name'],
                    $leverageValue
                );

                $mt4User = [];
                try {
                    $mt4CreateResult = $this->mt4ApiClient->createUser($mt4Payload);
                    if (!empty($mt4CreateResult['login'])) {
                        $mt4User = $this->mt4ApiClient->getUser($mt4CreateResult['login']);
                    }
                } finally {
                    $this->mt4ApiClient->disconnect();
                }

                $this->persistMt4ThirdPartyAccount(
                    $accountId,
                    $mt4Payload,
                    $mt4CreateResult,
                    $mt4User,
                    $mt4Group
                );
                $plainCredentials = [
                    'masterPin' => $mt4CreateResult['mainPassword'] ?? null,
                    'investorPin' => null, // MT4 gateway 不返回 investor 密码
                    'phonePin' => null,
                ];
            }
            // 重新获取最新状态（确保邮件状态同步）
            $latestAccount = $this->accountModel->findById($accountId);
            $accountDetails = $this->prepareAccountResponse($latestAccount, $platform);

            // 发送邮件通知（使用最新账户数据，确保 externalAccount 凭据可用）
            $this->sendAccountEmail($client['user'], $accountDetails, $plainCredentials);

            $responsePayload = $accountDetails;
            if (is_array($responsePayload) && isset($responsePayload['externalAccount'])) {
                unset($responsePayload['externalAccount']);
            }
            if (!empty($options['includePasswordInResponse'])) {
                $responsePayload['password'] = (string)($options['generatedPassword'] ?? '');
            }

            $subModule = OperationLogPages::resolveLogClient($data, OperationLogPages::subModuleKeyByAlias('page_clients_list'));
            $login = $accountDetails['accountLogin'] ?? $accountDetails['login'] ?? $data['accountNickname'] ?? '';
            $platformLabel = $platform['platformName'] ?? $platform['platformKey'] ?? '';
            (new AdminOperationLogWriter())->logClientTradingAccountCreate(
                $subModule,
                (int) $client['userId'],
                $platformLabel,
                $login
            );

            Response::created($responsePayload, 'Trading account created successfully');
        } catch (Exception $e) {
            if ($accountId) {
                $this->accountModel->delete($accountId);
            }

//            Logger::error('Create trading account failed', [
//                'exception' => $e->getMessage(),
//                'trace' => $e->getTraceAsString()
//            ]);

            $this->failTradingAccountCreate($data, $clientUserId, 'Failed to create trading account: ' . $e->getMessage());
            Response::serverError('Failed to create trading account: ' . $e->getMessage());
        }
    }

    private function applyLocalSymbolIds(array $orders, string $platformKey): array {
        $mapped = [];
        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }
            $symbolName = isset($order['Symbol']) ? (string)$order['Symbol'] : '';
            $order['SymbolId'] = $this->resolveLocalSymbolIdByName($platformKey, $symbolName);
            $mapped[] = $order;
        }
        return $mapped;
    }

    /**
     *
     */
    private function resolveLocalSymbolIdByName(string $platformKey, string $symbolName): int {
        $this->loadSymbolMapForPlatform($platformKey);
        $key = strtoupper(trim($symbolName));
        if ($key === '') {
            return 0;
        }
        return isset($this->symbolNameIdMapByPlatform[$platformKey][$key])
            ? (int)$this->symbolNameIdMapByPlatform[$platformKey][$key]
            : 0;
    }

    private function loadSymbolMapForPlatform(string $platformKey): void {
        if (isset($this->symbolNameIdMapByPlatform[$platformKey])) {
            return;
        }

        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT id, symbolName FROM ibCustomSymbols WHERE trading_platforms_key = :platform_key",
            ['platform_key' => $platformKey]
        );

        $nameMap = [];
        foreach ($rows as $row) {
            $localId = isset($row['id']) ? (int)$row['id'] : 0;
            if ($localId <= 0) {
                continue;
            }

            $name = strtoupper(trim((string)($row['symbolName'] ?? '')));
            if ($name !== '') {
                $nameMap[$name] = $localId;
            }
        }

        $this->symbolNameIdMapByPlatform[$platformKey] = $nameMap;
    }


    /**
     * 确保当前请求来自客户端并返回相关信息（支持预览 X-Preview-Token 与 JWT）
     */
    private function requireClient($requireKycApproved = false) {
        $userId = ClientAuthContext::getCurrentClientUserId();
        if ($userId !== null) {
            return $this->requireClientById($userId, $requireKycApproved);
        }

        $payload = JWT::getPayload();
        if (!$payload || ($payload['type'] ?? '') !== 'client') {
            Response::forbidden('Client authentication required');
        }

        $userId = $payload['userId'] ?? null;
        if (!$userId) {
            Response::unauthorized('Invalid token payload');
        }

        return $this->requireClientById($userId, $requireKycApproved);
    }

    /**
     * 按 clientUsers.id 解析客户；后台代开户和客户端 JWT 都走同一套客户/KYC 校验。
     */
    private function requireClientById($userId, $requireKycApproved = false, array $logData = null) {
        $userId = (int)$userId;
        if ($userId <= 0) {
            if ($logData !== null) {
                $this->failTradingAccountCreate($logData, 0, 'User not found');
            }
            Response::unauthorized('Invalid token payload');
        }

        $user = $this->userModel->findById($userId);
        if (!$user) {
            if ($logData !== null) {
                $this->failTradingAccountCreate($logData, 0, 'User not found');
            }
            Response::unauthorized('User not found');
        }

        if ($requireKycApproved) {
            $submission = $this->kycSubmissionModel->getLatestSubmission($userId);
            if (!$submission || ($submission['submissionStatus'] ?? null) !== 'approved') {
                if ($logData !== null) {
                    $this->failTradingAccountCreate(
                        $logData,
                        $userId,
                        'KYC verification is required before opening a trading account'
                    );
                }
                Response::forbidden('KYC verification is required before opening a trading account');
            }
        }

        return [
            'userId' => $userId,
            'user' => $user
        ];
    }

    private function failTradingAccountCreate(array $data, int $clientUserId, string $message) {
        $subModule = OperationLogPages::resolveLogClient($data, OperationLogPages::subModuleKeyByAlias('page_clients_list'));
        (new AdminOperationLogWriter())->logClientTradingAccountCreate(
            $subModule,
            $clientUserId,
            '',
            '',
            false,
            $message
        );
    }

    /**
     * 生成唯一的交易账号
     */
    private function generateAccountNumber($shortCode, array $logData = [], int $clientUserId = 0) {
        $shortCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', $shortCode));
        if ($shortCode === '') {
            $shortCode = 'ACC';
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $number = $shortCode . str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            if (!$this->accountModel->findByAccountNumber($number)) {
                return $number;
            }
        }

        $this->failTradingAccountCreate($logData, $clientUserId, 'Unable to generate unique account number, please try again');
        Response::serverError('Unable to generate unique account number, please try again');
        return null; // unreachable
    }

    /**
     * 准备返回给客户端的账户信息
     */
    private function prepareAccountResponse($account, $platform) {
        if (!$account) {
            return null;
        }

        $response = [
            'id' => (int)$account['id'],
            'accountNickname' => $account['accountNickname'],
            'platformId' => (int)$account['platformId'],
            'platformName' => $platform['displayName'] ?? '',
            'platformCode' => $platform['shortCode'] ?? '',
            'leverageValue' => $account['leverageValue'],
            'accountCurrency' => $account['accountCurrency'],
            'accountType' => $account['accountType'],
            'assignedCommissionRuleId' => isset($account['assignedCommissionRuleId'])
                ? (int) $account['assignedCommissionRuleId']
                : null,
            'initialDeposit' => (float)$account['initialDeposit'],
            // 'credentials' => [
            //     'predefinedLogin' => $account['predefinedLogin'] ?? null,
            //     'investorPin' => $this->decryptInvestorPin($account['investorPinEncrypted'] ?? null)
            // ],
            'status' => $account['status'],
            'emailSent' => (int)$account['emailSent'],
            'emailSentAt' => $account['emailSentAt'],
            'createdAt' => $account['createdAt']
        ];

        $externalAccount = $this->externalAccountModel->findByTradingAccount($account['id']);
        $displayAccountNumber = $externalAccount['providerAccountId'] ?? $account['providerAccountId'] ?? $account['accountNumber'];
        $response['accountNumber'] = $displayAccountNumber;

        if ($externalAccount) {
            $response['externalAccount'] = [
                'providerKey' => $externalAccount['providerKey'],
                'providerAccountId' => $externalAccount['providerAccountId'],
                'predefinedLogin' => $externalAccount['predefinedLogin'],
                'status' => $externalAccount['status'],
                'isEnable' => $externalAccount['isEnable'],
                'createdAt' => $externalAccount['createdAt'] ?? null
            ];
        }

        return $response;
    }

    /**
     * 解密投资者密码
     */
    private function decryptInvestorPin($encrypted)
    {
        if (!$encrypted) {
            return null;
        }

        try {
            return Encryptor::decrypt($encrypted);
        } catch (Exception $e) {
//            Logger::error('Failed to decrypt investor pin', ['exception' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 构建第三方开户请求数据（FinancePro）
     * GroupId：若传入 $groupId（客户绑定父级 IB 或用户主动选择时）则使用；否则使用该平台默认组别列表中的首个 trading_id。
     * MasterPin、InvestorPin、PhonePin：从 tradingPlatforms.defaultOpenPassword 读取并作为默认密码传入 OpenAccount 接口。
     */
    private function buildThirdPartyPayload($user, $account, $inputData, $platformKey, $groupId = null, $leverageValue = null) {
        $fullName = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
        if ($fullName === '') {
            $fullName = $user['email'] ?? 'Client';
        }

        // 未传或未绑定父级 IB 时，使用平台默认组别列表中的首个组别
        if ($platformKey === 'financepro' && ($groupId === null || $groupId === '')) {
            $defaultGroups = $this->tradingGroupModel->getDefaultGroups('financepro');
            if (!empty($defaultGroups) && isset($defaultGroups[0]['trading_id'])) {
                $groupId = (int) $defaultGroups[0]['trading_id'];
            }
        }
        if ($groupId === null || $groupId === '') {
            $groupId = 1;
        }

        $payload = [
            'GroupId' => (int) $groupId,
            'PredefinedLogin' => $user['email'] ?? '',
            'Status' => 1,
            'Name' => $inputData['thirdPartyName'] ?? $fullName,
            'City' => '',
            'State' => '',
            'Country' => $inputData['country'] ?? ($user['country'] ?? ''),
            'ZipCode' => '',
            'Address' => '',
            'Email' => $user['email'] ?? '',
            'IdNumber' => (string)($inputData['idNumber'] ?? $user['id'] ?? $account['userId']),
            'Leverage' => $this->resolveFinanceProLeverageId($leverageValue ?? ($inputData['leverageValue'] ?? $account['leverageValue'] ?? null)),
            'TaxRate' => '',
            'AgentAccount' => null,
            'Comment' => '',
            'AllowChangePin' => 1,
            'IsReadOnly' => 0,
            'SendReport' => 1
        ];

        // phone 为空时不传该字段，有值才带上
        $phone = trim((string)($inputData['phone'] ?? ($user['phone'] ?? '')));
        if ($phone !== '') {
            $payload['Phone'] = $phone;
        }

        // FP 开户密码：manual 模式用前端传入的（前置校验已保证非空）；
        // random 模式后端随机生成一组，三个 Pin 共用同一份。
        $requestedPin = isset($inputData['password']) ? trim((string)$inputData['password']) : '';
        if ($platformKey === 'financepro') {
            $platformRecord = $this->platformModel->findByKey('financepro');
            $platformPasswordMode = $this->getPlatformPasswordMode($platformRecord);

            $resolvedPin = '';
            if ($platformPasswordMode === 'manual') {
                $resolvedPin = $requestedPin;
            } else {
                $resolvedPin = $requestedPin !== '' ? $requestedPin : Password::generateRandomPassword(12);
            }

            $payload['MasterPin'] = $resolvedPin;
            $payload['InvestorPin'] = $resolvedPin;
            $payload['PhonePin'] = $resolvedPin;

            // 同一用户在 FP 已有账号时，需要把首次开户返回的 SysUserId 回传给 OpenAccount 接口；
            // 首次开户或历史账号没存到 SysUserId 时保持空字符串，让 FP 端生成新的。
            $payload['SysUserId'] = '';
            if (!empty($account['userId'])) {
                $existingSysUserId = $this->externalAccountModel->findFinanceProSysUserIdByUserId((int)$account['userId']);
                if ($existingSysUserId !== null && $existingSysUserId !== '') {
                    $payload['SysUserId'] = $existingSysUserId;
                }
            }

            // 非首次开户（已有 SysUserId）：phone 在 FP 是用户级、已经存在，开户不再重复带 Phone，避免唯一冲突。
            // 只有首次开户（SysUserId 为空、FP 端新建用户）才带 Phone。
            if ($payload['SysUserId'] !== '') {
                unset($payload['Phone']);
            }
        }

        return $payload;
    }

    /**
     * 构造 FinancePro EditAccount 请求 payload（改基础资料 / 杠杆 / 分组，不含密码）。
     *
     * - Id 用开户时落库的 FP 会员Id（tradingAccountExternalAccounts.providerAccountId）
     * - GroupId 用组 trading_id；Leverage 经 resolveFinanceProLeverageId 转成 FP 档位 id（和 openAccount 一致）
     * - Status / Name / Email 是 FP 必填字段，其余字段有传才带（不传即不改）
     *
     * @param string $memberId FP 会员Id
     * @param array $data groupTradingId, status, name, email 必填；leverageValue 及其余资料字段可选
     */
    private function buildFinanceProEditAccountPayload($memberId, array $data) {
        if ($memberId === null || $memberId === '') {
            throw new Exception('FinancePro EditAccount requires the member id');
        }
        $groupTradingId = (int)($data['groupTradingId'] ?? 0);
        if ($groupTradingId <= 0) {
            throw new Exception('FinancePro EditAccount requires a valid group id');
        }

        $payload = [
            'Id'      => (string)$memberId,
            'GroupId' => $groupTradingId,
            'Status'  => (int)($data['status'] ?? 1),
            'Name'    => (string)($data['name'] ?? ''),
            'Email'   => (string)($data['email'] ?? ''),
        ];

        // Leverage 传的是档位值（如 "1:1000"），转成 FP 端的档位 id
        if (isset($data['leverageValue']) && $data['leverageValue'] !== '') {
            $payload['Leverage'] = $this->resolveFinanceProLeverageId($data['leverageValue']);
        }

        // 其余资料字段：有传才带
        $optional = [
            'predefinedLogin' => 'PredefinedLogin',
            'phone'           => 'Phone',
            'country'         => 'Country',
            'city'            => 'City',
            'state'           => 'State',
            'zipCode'         => 'ZipCode',
            'address'         => 'Address',
            'idNumber'        => 'IdNumber',
            'taxRate'         => 'TaxRate',
            'agentAccount'    => 'AgentAccount',
            'comment'         => 'Comment',
            'allowChangePin'  => 'AllowChangePin',
            'isReadOnly'      => 'IsReadOnly',
            'sendReport'      => 'SendReport',
        ];
        foreach ($optional as $inputKey => $fpKey) {
            if (array_key_exists($inputKey, $data) && $data[$inputKey] !== null) {
                $payload[$fpKey] = $data[$inputKey];
            }
        }

        return $payload;
    }

    /**
     * 调 FinancePro EditAccount 更新账户（改资料 / 杠杆 / 分组，不含密码）。
     * 成功判定复用 openAccount 那套（Success=true）。返回 FP 原始响应。
     */
    private function editFinanceProAccount($memberId, array $data) {
        $payload = $this->buildFinanceProEditAccountPayload($memberId, $data);
        return $this->financeProClient->request(
            $this->financePro['edit_account'],
            'POST',
            $payload,
            [
                'expect_success_key' => 'Success',
                'success_value'      => true,
                'error_message_key'  => 'ErrMsg',
            ]
        );
    }

    /**
     * 解析 MT5 开户组别（按 groupTradingId 精确匹配）
     */
    private function resolveMt5GroupForOpenAccount($groupTradingId) {
        $groupTradingId = (int)$groupTradingId;
        if ($groupTradingId <= 0) {
            throw new Exception('MT5 trading group id is required');
        }

        $group = $this->tradingGroupModel->findByTradingId($groupTradingId, 'mt5');
        if (!$group || empty($group['name'])) {
            throw new Exception('MT5 group not found. Please sync MT5 groups and configure a default group.');
        }

        $candidateGroupName = (string)$group['name'];
        $remoteGroup = [];
        $shouldFetchRemoteGroup = empty($group['leverageByDefault']);

        if ($shouldFetchRemoteGroup) {
            try {
                $remoteGroup = $this->mt5ApiClient->getGroup($candidateGroupName);
            } catch (Exception $e) {
                if (!$group || empty($group['name'])) {
                    throw new Exception('MT5 group get failed: ' . $e->getMessage());
                }
            }
        }

        $resolvedGroupName = trim((string)($remoteGroup['Group'] ?? $candidateGroupName));
        if ($resolvedGroupName === '') {
            throw new Exception('MT5 group name is empty from GroupGet');
        }

        $passwordMinLength = $this->extractMt5PasswordMinLengthFromGroup($group);
        if (isset($remoteGroup['AuthPasswordMin']) && (int)$remoteGroup['AuthPasswordMin'] > 0) {
            $remoteMin = (int)$remoteGroup['AuthPasswordMin'];
            if ($remoteMin < 8) {
                $remoteMin = 8;
            } elseif ($remoteMin > 16) {
                $remoteMin = 16;
            }
            $passwordMinLength = $remoteMin;
        }

        return [
            'id' => isset($group['id']) ? (int)$group['id'] : null,
            'trading_id' => (int)($group['trading_id'] ?? 0),
            'name' => $resolvedGroupName,
            'leverageByDefault' => isset($group['leverageByDefault']) ? (int)$group['leverageByDefault'] : null,
            'passwordMinLength' => $passwordMinLength,
            'remoteGroup' => $remoteGroup,
        ];
    }

    /**
     * 从同步到 trading_group.accountTag 的 AuthPasswordMin 提取密码最小长度
     */
    private function extractMt5PasswordMinLengthFromGroup($group) {
        $tag = (string)($group['accountTag'] ?? '');
        if (preg_match('/AuthPasswordMin:(\d+)/', $tag, $m)) {
            $min = (int)$m[1];
            if ($min >= 8 && $min <= 16) {
                return $min;
            }
        }
        return 8;
    }

    /**
     * 校验用户传入的 MT5 密码是否满足本地规则和组策略长度要求。
     */
    private function validateProvidedMt5Password($password, $passwordMinLength = 8) {
        $password = (string)$password;
        $passwordMinLength = (int)$passwordMinLength;
        if ($passwordMinLength < 8) {
            $passwordMinLength = 8;
        } elseif ($passwordMinLength > 16) {
            $passwordMinLength = 16;
        }

        if (strlen($password) < $passwordMinLength) {
            Response::validationError([
                'password' => ["Password must be at least {$passwordMinLength} characters for this MT5 group."]
            ], "Password must be at least {$passwordMinLength} characters for this MT5 group.");
        }

        if (!Password::validateMt5Password($password)) {
            Response::validationError([
                'password' => ['Password must be 8-16 characters and include uppercase, lowercase, number, and one of #@!$%&*.']
            ], 'Password must be 8-16 characters and include uppercase, lowercase, number, and one of #@!$%&*.');
        }

        return $password;
    }

    /**
     * 组装 MT5 开户 payload
     */
    private function buildMt5OpenAccountPayload($user, $account, $inputData, $groupName, $passwordMinLength = 8, $leverageValue = null) {
        $fullName = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
        if ($fullName === '') {
            $fullName = $user['email'] ?? 'Client';
        }

        $passwordLength = isset($inputData['passwordLength']) ? (int)$inputData['passwordLength'] : (int)$passwordMinLength;
        if ($passwordLength < 8) {
            $passwordLength = 8;
        } elseif ($passwordLength > 16) {
            $passwordLength = 16;
        }

        $providedPassword = isset($inputData['password']) ? trim((string)$inputData['password']) : '';
        if ($providedPassword !== '') {
            $mainPassword = $this->validateProvidedMt5Password($providedPassword, $passwordMinLength);
            $investPassword = $mainPassword;
        } else {
            $mainPassword = Password::generateMt5Password($passwordLength);
            $investPassword = Password::generateMt5Password($passwordLength);
        }

        return [
            'group' => (string)$groupName,
            'name' => $inputData['thirdPartyName'] ?? $fullName,
            'email' => $user['email'] ?? '',
            'phone' => $inputData['phone'] ?? ($user['phone'] ?? ''),
            'country' => $inputData['country'] ?? ($user['country'] ?? ''),
            'company' => $inputData['company'] ?? '',
            'idNumber' => (string)($inputData['idNumber'] ?? $user['id'] ?? $account['userId']),
            'leverage' => $this->extractMt5LeverageNumberOrZero($leverageValue ?? ($inputData['leverageValue'] ?? $account['leverageValue'] ?? null)),
            'mainPassword' => $mainPassword,
            'investPassword' => $investPassword,
            'passwordMinLength' => (int)$passwordMinLength,
        ];
    }

    private function formatLeverageValueForDisplay($value) {
        $numeric = $this->extractMt5LeverageNumberOrZero($value);
        return $numeric > 0 ? ('1:' . $numeric) : null;
    }

    /**
     * 对交易账户凭据做不可逆哈希存储，避免数据库保存明文。
     */
    private function hashTradingCredential($value) {
        $value = (string)$value;
        if ($value === '') {
            return null;
        }

        return password_hash($value, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    /**
     * FP 开户 Leverage 字段要传 FP 端 leverage 表的 id，不是 "1:500" 里的数字 500。
     * 通过 tradingPlatformLeverages.financeProLeverageId 做映射，缺映射就抛错让事务回滚，
     * 避免发出错档位的开户请求。
     */
    private function resolveFinanceProLeverageId($leverageValue) {
        $value = trim((string)$leverageValue);
        if ($value === '') {
            throw new Exception('Trading Platform account requires a leverage value');
        }

        $platform = $this->platformModel->findByKey('financepro');
        if (!$platform) {
            throw new Exception('Trading Platform is not configured');
        }

        $row = $this->leverageModel->findByPlatformAndValue((int)$platform['id'], $value);
        if (!$row || !isset($row['financeProLeverageId']) || $row['financeProLeverageId'] === null || $row['financeProLeverageId'] === '') {
            throw new Exception("Trading Platform leverage {$value} has no id mapping");
        }

        return (int)$row['financeProLeverageId'];
    }

    private function extractMt5LeverageNumberOrZero($value) {
        if (is_numeric($value)) {
            $numeric = (int)$value;
            return $numeric > 0 ? $numeric : 0;
        }

        $value = trim((string)$value);
        if ($value === '') {
            return 0;
        }

        if (preg_match('/^\s*\d+\s*:\s*(\d+)\s*$/', $value, $matches)) {
            $numeric = (int)$matches[1];
            return $numeric > 0 ? $numeric : 0;
        }

        if (preg_match('/(\d+)/', $value, $matches)) {
            $numeric = (int)$matches[1];
            return $numeric > 0 ? $numeric : 0;
        }

        return 0;
    }

    /**
     * 持久化 MT5 开户返回数据到 tradingAccountExternalAccounts
     */
    private function persistMt5ThirdPartyAccount($accountId, $payload, $createResult, $userInfo, $group) {
        $mt5User = is_array($userInfo) ? $userInfo : [];
        $record = [
            'tradingAccountId' => $accountId,
            'providerKey' => 'mt5',
            'providerAccountId' => isset($createResult['login']) ? (string)$createResult['login'] : null,
            'groupId' => isset($group['id']) ? (int)$group['id'] : null,
            'predefinedLogin' => $mt5User['Email'] ?? ($payload['email'] ?? null),
            'status' => 1,
            'name' => $mt5User['Name'] ?? ($payload['name'] ?? null),
            'color' => $mt5User['Color'] ?? null,
            'city' => $mt5User['City'] ?? null,
            'state' => $mt5User['State'] ?? null,
            'country' => $mt5User['Country'] ?? ($payload['country'] ?? null),
            'zipCode' => $mt5User['ZipCode'] ?? null,
            'phone' => $mt5User['Phone'] ?? ($payload['phone'] ?? null),
            'address' => $mt5User['Address'] ?? null,
            'email' => $mt5User['Email'] ?? ($payload['email'] ?? null),
            'idNumber' => $mt5User['ID'] ?? ($payload['idNumber'] ?? null),
            'leverage' => $mt5User['Leverage'] ?? ($payload['leverage'] ?? null),
            'agentAccount' => $mt5User['Agent'] ?? null,
            'comment' => $mt5User['Comment'] ?? ($payload['comment'] ?? null),
            'masterPin' => $this->hashTradingCredential($createResult['mainPassword'] ?? null),
            'investorPin' => $this->hashTradingCredential($createResult['investPassword'] ?? null),
            'phonePin' => $this->hashTradingCredential($mt5User['PhonePassword'] ?? null),
            'isEnable' => 1,
            'rawResponse' => json_encode([
                'createResult' => $createResult,
                'user' => $userInfo,
                'group' => $group,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'requestPayload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ];

        $this->externalAccountModel->create($record);
    }

    /**
     * 解析 MT4 开户组别（按 groupTradingId 精确匹配本地 trading_group，platformKey=mt4）。
     * MT4 组名通常含反斜杠（real\\USD），原样使用，签名/URL-encode 由 Mt4ApiClient 处理。
     */
    private function resolveMt4GroupForOpenAccount($groupTradingId) {
        $groupTradingId = (int)$groupTradingId;
        if ($groupTradingId <= 0) {
            throw new Exception('MT4 trading group id is required');
        }

        $group = $this->tradingGroupModel->findByTradingId($groupTradingId, 'mt4');
        if (!$group || empty($group['name'])) {
            throw new Exception('MT4 group not found. Please sync MT4 groups and configure a default group.');
        }

        return [
            'id' => isset($group['id']) ? (int)$group['id'] : null,
            'trading_id' => (int)($group['trading_id'] ?? 0),
            'name' => (string)$group['name'],
            'leverageByDefault' => isset($group['leverageByDefault']) ? (int)$group['leverageByDefault'] : null,
        ];
    }

    /**
     * 组装 MT4 开户 payload（喂给 Mt4ApiClient::createUser）。
     * 密码策略复用 MT5 那套（Password::generateMt5Password / validateMt5Password）。
     */
    private function buildMt4OpenAccountPayload($user, $account, $inputData, $groupName, $leverageValue = null) {
        $fullName = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
        if ($fullName === '') {
            $fullName = $user['email'] ?? 'Client';
        }

        $passwordLength = isset($inputData['passwordLength']) ? (int)$inputData['passwordLength'] : 12;
        if ($passwordLength < 8) {
            $passwordLength = 8;
        } elseif ($passwordLength > 16) {
            $passwordLength = 16;
        }

        $providedPassword = isset($inputData['password']) ? trim((string)$inputData['password']) : '';
        if ($providedPassword !== '') {
            $mainPassword = $this->validateProvidedMt5Password($providedPassword);
        } else {
            $mainPassword = Password::generateMt5Password($passwordLength);
        }

        return [
            'group' => (string)$groupName,
            'name' => $inputData['thirdPartyName'] ?? $fullName,
            'email' => $user['email'] ?? '',
            'phone' => $inputData['phone'] ?? ($user['phone'] ?? ''),
            'country' => $inputData['country'] ?? ($user['country'] ?? ''),
            'comment' => $inputData['comment'] ?? '',
            'leverage' => $this->extractMt5LeverageNumberOrZero($leverageValue ?? ($inputData['leverageValue'] ?? $account['leverageValue'] ?? null)),
            'mainPassword' => $mainPassword,
        ];
    }

    /**
     * 持久化 MT4 开户返回数据到 tradingAccountExternalAccounts。
     * MT4 gateway 返回字段是 snake_case；getUser 拿到完整 UserRecord。
     */
    private function persistMt4ThirdPartyAccount($accountId, $payload, $createResult, $userInfo, $group) {
        $mt4User = is_array($userInfo) ? $userInfo : [];
        $record = [
            'tradingAccountId' => $accountId,
            'providerKey' => 'mt4',
            'providerAccountId' => isset($createResult['login']) ? (string)$createResult['login'] : null,
            'groupId' => isset($group['id']) ? (int)$group['id'] : null,
            'predefinedLogin' => $mt4User['email'] ?? ($payload['email'] ?? null),
            'status' => 1,
            'name' => $mt4User['name'] ?? ($payload['name'] ?? null),
            'city' => $mt4User['city'] ?? null,
            'state' => $mt4User['state'] ?? null,
            'country' => $mt4User['country'] ?? ($payload['country'] ?? null),
            'zipCode' => $mt4User['zip_code'] ?? null,
            'phone' => $mt4User['phone'] ?? ($payload['phone'] ?? null),
            'address' => $mt4User['address'] ?? null,
            'email' => $mt4User['email'] ?? ($payload['email'] ?? null),
            'idNumber' => $mt4User['id'] ?? null,
            'leverage' => $mt4User['leverage'] ?? ($payload['leverage'] ?? null),
            'agentAccount' => $mt4User['agent_account'] ?? null,
            'comment' => $mt4User['comment'] ?? ($payload['comment'] ?? null),
            'masterPin' => $this->hashTradingCredential($createResult['mainPassword'] ?? null),
            'investorPin' => null,
            'phonePin' => null,
            'isEnable' => 1,
            'rawResponse' => json_encode([
                'createResult' => $createResult,
                'user' => $userInfo,
                'group' => $group,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'requestPayload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ];

        $this->externalAccountModel->create($record);
    }

    /**
     * 持久化第三方交易账号数据
     */
    private function persistThirdPartyAccount($accountId, $payload, $response, $platform='', $leverageValue=null) {
        $resData = $response['ResData'] ?? [];

        // FP 的 Leverage 字段是 id（如 1 = 1:1000），不能直接当杠杆值存；
        // 这里用前端传进来的 leverageValue（"1:1000"）抽出实际数字（1000）入库，
        // 否则展示侧 formatLeverageValueForDisplay 会把 id 1 拼成 "1:1"。
        $leverageStore = $resData['Leverage'] ?? $payload['Leverage'] ?? null;
        if ($leverageValue !== null && $leverageValue !== '') {
            $extracted = $this->extractMt5LeverageNumberOrZero($leverageValue);
            if ($extracted > 0) {
                $leverageStore = $extracted;
            }
        }

        $record = [
            'tradingAccountId' => $accountId,
            'providerKey' => $platform,
            'providerAccountId' => $resData['Id'] ?? null,
            // FinancePro 返回的用户级标识，同一用户再次开户需回传给 OpenAccount 接口
            'sysUserId' => $resData['SysUserId'] ?? null,
            'groupId' => $resData['GroupId'] ?? ($payload['GroupId'] ?? null),
            'predefinedLogin' => $resData['PredefinedLogin'] ?? ($payload['PredefinedLogin'] ?? null),
            'status' => $resData['Status'] ?? $payload['Status'] ?? null,
            'name' => $resData['Name'] ?? $payload['Name'] ?? null,
            'color' => $resData['Color'] ?? null,
            'city' => $resData['City'] ?? $payload['City'] ?? null,
            'state' => $resData['State'] ?? $payload['State'] ?? null,
            'country' => $resData['Country'] ?? $payload['Country'] ?? null,
            'zipCode' => $resData['ZipCode'] ?? $payload['ZipCode'] ?? null,
            'phone' => $resData['Phone'] ?? $payload['Phone'] ?? null,
            'address' => $resData['Address'] ?? $payload['Address'] ?? null,
            'email' => $resData['Email'] ?? $payload['Email'] ?? null,
            'idNumber' => $resData['IdNumber'] ?? $payload['IdNumber'] ?? null,
            'leverage' => $leverageStore,
            'taxRate' => $resData['TaxRate'] ?? $payload['TaxRate'] ?? null,
            'agentAccount' => $resData['AgentAccount'] ?? $payload['AgentAccount'] ?? null,
            'comment' => $resData['Comment'] ?? $payload['Comment'] ?? null,
            'allowChangePin' => $resData['AllowChangePin'] ?? $payload['AllowChangePin'] ?? null,
            'isReadOnly' => $resData['IsReadOnly'] ?? $payload['IsReadOnly'] ?? null,
            'sendReport' => $resData['SendReport'] ?? $payload['SendReport'] ?? null,
            'masterPin' => $this->hashTradingCredential($resData['MasterPin'] ?? ($payload['MasterPin'] ?? null)),
            'investorPin' => $this->hashTradingCredential($resData['InvestorPin'] ?? ($payload['InvestorPin'] ?? null)),
            'phonePin' => $this->hashTradingCredential($resData['PhonePin'] ?? ($payload['PhonePin'] ?? null)),
            'isEnable' => isset($resData['IsEnable']) ? (int)$resData['IsEnable'] : null,
            'enabledMark' => isset($resData['EnabledMark']) ? (int)$resData['EnabledMark'] : null,
            'deleteMark' => isset($resData['DeleteMark']) ? (int)$resData['DeleteMark'] : null,
            'creatorTime' => $resData['CreatorTime'] ?? null,
            'deleteTime' => $resData['DeleteTime'] ?? null,
            'deleteUserId' => $resData['DeleteUserId'] ?? null,
            'rawResponse' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'requestPayload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ];

        $this->externalAccountModel->create($record);
    }

    /**
     * 发送开户成功邮件
     */
    private function sendAccountEmail($user, $accountDetails, $plainCredentials = []) {
        if (!$user || !$accountDetails) {
            return;
        }

        try {
            require_once __DIR__ . '/../utils/EmailSender.php';
            $emailSender = new EmailSender();

            $fullName = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
            if ($fullName === '') {
                $fullName = $user['email'] ?? 'Trader';
            }

            // 只给 MT5 账户发送凭据邮件（含 login/main/investor 密码）
            $external = $accountDetails['externalAccount'] ?? null;
            if (is_array($external)
                && ($external['providerKey'] ?? '') === 'mt5'
                && !empty($external['providerAccountId'])
                && !empty($plainCredentials['masterPin'])
                && !empty($plainCredentials['investorPin'])) {

                $appConfig = require __DIR__ . '/../config/app.php';
                $serverName = (string)($appConfig['integrations']['mt5']['host'] ?? '');
                $result = $emailSender->sendMt5CredentialsEmail(
                    $user['email'],
                    $fullName,
                    $external['providerAccountId'],
                    [
                        'platformName' => $accountDetails['platformName'] ?? 'MetaTrader 5',
                        'serverName' => $serverName,
                        'mainPassword' => (string)$plainCredentials['masterPin'],
                        'investPassword' => (string)$plainCredentials['investorPin'],
                        'subject' => 'Your MT5 Trading Account Credentials',
                    ]
                );

                if (!empty($result['success'])) {
                    $this->accountModel->markEmailSent($accountDetails['id']);
                }
            } elseif (is_array($external)
                && ($external['providerKey'] ?? '') === 'mt4'
                && !empty($external['providerAccountId'])
                && !empty($plainCredentials['masterPin'])) {

                // MT4 只有 master 一个密码，单独发凭据邮件
                $appConfig = require __DIR__ . '/../config/app.php';
                $serverName = (string)($appConfig['integrations']['mt4']['server_name'] ?? '');
                $result = $emailSender->sendMt4CredentialsEmail(
                    $user['email'],
                    $fullName,
                    $external['providerAccountId'],
                    [
                        'platformName' => $accountDetails['platformName'] ?? 'MetaTrader 4',
                        'serverName' => $serverName,
                        'mainPassword' => (string)$plainCredentials['masterPin'],
                        'subject' => 'Your MT4 Trading Account Credentials',
                    ]
                );

                if (!empty($result['success'])) {
                    $this->accountModel->markEmailSent($accountDetails['id']);
                }
            } elseif (is_array($external)
                && ($external['providerKey'] ?? '') === 'financepro'
                && !empty($external['providerAccountId'])
                && !empty($plainCredentials['masterPin'])) {

                // 给 FinancePro 账户发送凭据邮件（含 Account Login、MasterPin、InvestorPin、PhonePin）
                $success = $this->sendFinanceProCredentialsEmail(
                    $emailSender,
                    $user['email'],
                    $fullName,
                    array_merge($external, $plainCredentials),
                    $accountDetails
                );
                if ($success) {
                    $this->accountModel->markEmailSent($accountDetails['id']);
                }
            }
        } catch (Exception $e) {
            error_log('Trading account email failed: ' . $e->getMessage());
        }
    }

    /**
     * 发送 FinancePro 开户凭据邮件（Account Login、MasterPin、InvestorPin、PhonePin）
     * 在 Controller 内构建邮件内容，仅调用 EmailSender::send 发送，不修改 EmailSender 类
     */
    private function sendFinanceProCredentialsEmail($emailSender, $to, $fullName, $external, $accountDetails) {
        $platformName = $accountDetails['platformName'] ?? 'FinancePro';
        $subject = 'Your FinancePro Trading Account Credentials';
        $accountLogin = $external['providerAccountId'];
        $masterPin = (string)$external['masterPin'];
        $investorPin = (string)($external['investorPin'] ?? '');
        $phonePin = (string)($external['phonePin'] ?? '');

        $safeUserName = htmlspecialchars($fullName ?: 'Client', ENT_QUOTES, 'UTF-8');
        $safePlatformName = htmlspecialchars($platformName, ENT_QUOTES, 'UTF-8');
        $safeLogin = htmlspecialchars((string)$accountLogin, ENT_QUOTES, 'UTF-8');
        $safeMasterPin = htmlspecialchars($masterPin, ENT_QUOTES, 'UTF-8');
        $safeInvestorPin = htmlspecialchars($investorPin, ENT_QUOTES, 'UTF-8');
        $safePhonePin = htmlspecialchars($phonePin, ENT_QUOTES, 'UTF-8');

        $investorLine = '';
        if ($safeInvestorPin !== '') {
            $investorLine = "<tr><td style=\"padding: 8px 0; color: #1a202c;\"><strong>Investor Pin:</strong> <span style=\"font-family: monospace; background: #edf2f7; padding: 2px 6px; border-radius: 4px;\">{$safeInvestorPin}</span></td></tr>";
        }
        $phoneLine = '';
        if ($safePhonePin !== '') {
            $phoneLine = "<tr><td style=\"padding: 8px 0; color: #1a202c;\"><strong>Phone Pin:</strong> <span style=\"font-family: monospace; background: #edf2f7; padding: 2px 6px; border-radius: 4px;\">{$safePhonePin}</span></td></tr>";
        }

        $content = <<<HTML
<p style="margin: 0 0 16px 0;">Dear {$safeUserName},</p>
<p style="margin: 0 0 20px 0;">Your {$safePlatformName} trading account has been created successfully. Please find your login credentials below:</p>

<table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e2e8f0; border-radius: 8px; background: #fafcff; margin: 0 0 20px 0;">
    <tr>
        <td style="padding: 18px 20px;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr><td style="padding: 0 0 8px 0; color: #1a202c;"><strong>Platform:</strong> {$safePlatformName}</td></tr>
                <tr><td style="padding: 8px 0; color: #1a202c;"><strong>Account Login:</strong> {$safeLogin}</td></tr>
                <tr><td style="padding: 8px 0; color: #1a202c;"><strong>Master Pin:</strong> <span style="font-family: monospace; background: #edf2f7; padding: 2px 6px; border-radius: 4px;">{$safeMasterPin}</span></td></tr>
                {$investorLine}
                {$phoneLine}
            </table>
        </td>
    </tr>
</table>

<div style="border-left: 4px solid #ed8936; background: #fffaf0; padding: 12px 14px; margin: 0 0 18px 0;">
    <p style="margin: 0; color: #744210; font-size: 14px;">
        Security reminder: Please keep your credentials secure and change your password after first login if required.
    </p>
</div>

<p style="margin: 0;">If you did not request this account, please contact support immediately.</p>
HTML;

        $logoname = $this->config['logoname'] ?? 'CRM';
        $body = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trading Account Credentials</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 32px; font-weight: 700;">{$logoname}</h1>
                            <p style="margin: 10px 0 0 0; color: #ffffff; opacity: 0.9; font-size: 16px;">Trading Platform</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px 30px; color: #333333; line-height: 1.6;">
                            {$content}
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0; color: #718096; font-size: 14px;">© 2025 {$logoname}. All rights reserved.</p>
                            <p style="margin: 10px 0 0 0; color: #a0aec0; font-size: 12px;">This is an automated message. Please do not reply to this email.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

        return $emailSender->send($to, $subject, $body);
    }

    /**
     * 获取交易历史（混合查询 Deposit、Withdrawal 和 Internal Transfer）
     * GET /api/trading/accounts/transaction-history
     */
    public function transactionHistory() {
        $client = $this->requireClient();
        $userId = $client['userId'];

        try {
            $page = (int)($_GET['page'] ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 20);
            $offset = ($page - 1) * $perPage;

            // 获取筛选参数
            $keywords = $_GET['keywords'] ?? null;
            $type = $_GET['type'] ?? null; // 'deposit', 'withdrawal', 'internal_transfer', 'commission', 'credit'
            $periodFrom = $_GET['periodFrom'] ?? null;
            $periodTo = $_GET['periodTo'] ?? null;

            $db = Database::getInstance();

            // 确保 limit 和 offset 是整数，防止 SQL 注入
            $perPage = (int)$perPage;
            $offset = (int)$offset;

            // 构建参数数组（动态添加）
            $params = [];
            $paramCounter = 1;

            // 根据类型筛选决定查询哪些表
            $depositSelect = "";
            $withdrawalSelect = "";
            $transferSelect = "";
            $countParts = [];

            // Deposit 查询
            if (!$type || $type === 'deposit') {
                $depositParams = [];
                $depositWhere = "d.userId = :userId" . $paramCounter;
                $depositParams['userId' . $paramCounter] = $userId;
                $paramCounter++;

                // 关键字搜索（transactionId 和 Account Nickname）
                $depositJoin = "LEFT JOIN tradingAccounts ta_d ON ta_d.id = d.tradingAccountId
                                LEFT JOIN tradingPlatforms tp_d ON ta_d.platformId = tp_d.id
                                LEFT JOIN tradingAccountExternalAccounts tea_d ON tea_d.tradingAccountId = ta_d.id
                                LEFT JOIN trading_group tg_d
                                    ON tg_d.trading_platforms_key = tp_d.platformKey
                                   AND (
                                        (tp_d.platformKey IN ('mt5', 'mt4') AND tg_d.id = tea_d.groupId)
                                        OR
                                        (tp_d.platformKey NOT IN ('mt5', 'mt4') AND tg_d.trading_id = tea_d.groupId)
                                   )
                                LEFT JOIN paymentGatewaySettings pgs_d ON pgs_d.id = d.gatewaySettingId";
                if ($keywords) {
                    $searchPattern = "%{$keywords}%";
                    $depositWhere .= " AND (d.transactionId LIKE :search" . $paramCounter;
                    $depositParams['search' . $paramCounter] = $searchPattern;
                    $paramCounter++;

                    // 关联tradingAccounts表搜索accountNickname
                    $depositWhere .= " OR ta_d.accountNickname LIKE :search" . $paramCounter;
                    $depositParams['search' . $paramCounter] = $searchPattern;
                    $paramCounter++;
                    $depositWhere .= ")";
                }

                // 日期范围筛选
                if ($periodFrom) {
                    $depositWhere .= " AND DATE(d.requestedAt) >= :dateFrom" . $paramCounter;
                    $depositParams['dateFrom' . $paramCounter] = $periodFrom;
                    $paramCounter++;
                }

                if ($periodTo) {
                    $depositWhere .= " AND DATE(d.requestedAt) <= :dateTo" . $paramCounter;
                    $depositParams['dateTo' . $paramCounter] = $periodTo;
                    $paramCounter++;
                }

                $depositSelect = "
                    SELECT
                        'deposit' as type,
                        d.id,
                        d.transactionId,
                        d.amount as amount,
                        d.status,
                        d.requestedAt,
                        d.approvedAt,
                        d.completedAt,
                        NULL as fromTradingAccountId,
                        d.tradingAccountId as toTradingAccountId,
                        CASE WHEN d.tradingAccountId IS NULL THEN 'wallet' ELSE 'platform' END as fromType,
                        NULL as fromAccountNumber,
                        NULL as fromAccountNickname,
                        CASE
                            WHEN tea_d.providerAccountId IS NOT NULL AND tea_d.providerAccountId <> '' THEN tea_d.providerAccountId
                            ELSE ta_d.accountNumber
                        END as toAccountNumber,
                        CASE
                            WHEN tea_d.providerAccountId IS NOT NULL AND tea_d.providerAccountId <> '' THEN tea_d.providerAccountId
                            ELSE ta_d.accountNumber
                        END as toAccountNickname,
                        tp_d.platformKey as platformKey,
                        NULL as fromPlatformKey,
                        NULL as toPlatformKey,
                        tp_d.shortCode as platformShortCode,
                        NULL as fromPlatformShortCode,
                        NULL as toPlatformShortCode,
                        pgs_d.gatewayName as gatewayName,
                        pgs_d.gatewayKey as gatewayKey,
                        d.currencyCode as currencyCode,
                        d.platformFee as platformFee,
                        d.platformAmount as platformAmount,
                        NULL as fromPlatformAmount,
                        NULL as toPlatformAmount,
                        d.adminNotes as adminNotes,
                        NULL as reason,
                        COALESCE(d.rejectionNotes, d.failureReason) as rejectionReason
                    FROM deposits d
                    {$depositJoin}
                    WHERE {$depositWhere}
                ";

                $countParts[] = "SELECT d.id FROM deposits d {$depositJoin} WHERE {$depositWhere}";
                $params = array_merge($params, $depositParams);
            }

            // Withdrawal 查询
            if (!$type || $type === 'withdrawal') {
                $withdrawalParams = [];
                $withdrawalWhere = "w.userId = :userId" . $paramCounter;
                $withdrawalParams['userId' . $paramCounter] = $userId;
                $paramCounter++;

                // 关键字搜索（transactionId 和 Account Nickname）
                $withdrawalJoin = "LEFT JOIN tradingAccounts ta_w ON ta_w.id = w.tradingAccountId
                                   LEFT JOIN tradingPlatforms tp_w ON ta_w.platformId = tp_w.id
                                   LEFT JOIN tradingAccountExternalAccounts tea_w ON tea_w.tradingAccountId = ta_w.id
                                   LEFT JOIN trading_group tg_w
                                       ON tg_w.trading_platforms_key = tp_w.platformKey
                                      AND (
                                            (tp_w.platformKey IN ('mt5', 'mt4') AND tg_w.id = tea_w.groupId)
                                            OR
                                            (tp_w.platformKey NOT IN ('mt5', 'mt4') AND tg_w.trading_id = tea_w.groupId)
                                      )
                                   LEFT JOIN paymentGatewaySettings pgs_w ON pgs_w.id = w.gatewaySettingId";
                if ($keywords) {
                    $searchPattern = "%{$keywords}%";
                    $withdrawalWhere .= " AND (w.transactionId LIKE :search" . $paramCounter;
                    $withdrawalParams['search' . $paramCounter] = $searchPattern;
                    $paramCounter++;

                    // 关联tradingAccounts表搜索accountNickname
                    $withdrawalWhere .= " OR ta_w.accountNickname LIKE :search" . $paramCounter;
                    $withdrawalParams['search' . $paramCounter] = $searchPattern;
                    $paramCounter++;
                    $withdrawalWhere .= ")";
                }

                // 日期范围筛选
                if ($periodFrom) {
                    $withdrawalWhere .= " AND DATE(w.requestedAt) >= :dateFrom" . $paramCounter;
                    $withdrawalParams['dateFrom' . $paramCounter] = $periodFrom;
                    $paramCounter++;
                }

                if ($periodTo) {
                    $withdrawalWhere .= " AND DATE(w.requestedAt) <= :dateTo" . $paramCounter;
                    $withdrawalParams['dateTo' . $paramCounter] = $periodTo;
                    $paramCounter++;
                }

                $withdrawalSelect = "
                    SELECT
                        'withdrawal' as type,
                        w.id,
                        w.transactionId,
                        w.amount as amount,
                        w.status,
                        w.requestedAt,
                        w.approvedAt,
                        w.completedAt,
                        w.tradingAccountId as fromTradingAccountId,
                        NULL as toTradingAccountId,
                        CASE WHEN w.tradingAccountId IS NULL THEN 'wallet' ELSE 'platform' END as fromType,
                        CASE
                            WHEN tea_w.providerAccountId IS NOT NULL AND tea_w.providerAccountId <> '' THEN tea_w.providerAccountId
                            ELSE ta_w.accountNumber
                        END as fromAccountNumber,
                        CASE
                            WHEN tea_w.providerAccountId IS NOT NULL AND tea_w.providerAccountId <> '' THEN tea_w.providerAccountId
                            ELSE ta_w.accountNumber
                        END as fromAccountNickname,
                        NULL as toAccountNumber,
                        NULL as toAccountNickname,
                        tp_w.platformKey as platformKey,
                        NULL as fromPlatformKey,
                        NULL as toPlatformKey,
                        tp_w.shortCode as platformShortCode,
                        NULL as fromPlatformShortCode,
                        NULL as toPlatformShortCode,
                        pgs_w.gatewayName as gatewayName,
                        pgs_w.gatewayKey as gatewayKey,
                        w.currencyCode as currencyCode,
                        w.platformFee as platformFee,
                        w.platformAmount as platformAmount,
                        NULL as fromPlatformAmount,
                        NULL as toPlatformAmount,
                        w.adminNotes as adminNotes,
                        w.withdrawalReason as reason,
                        w.rejectionNotes as rejectionReason
                    FROM withdrawals w
                    {$withdrawalJoin}
                    WHERE {$withdrawalWhere}
                ";

                $countParts[] = "SELECT w.id FROM withdrawals w {$withdrawalJoin} WHERE {$withdrawalWhere}";
                $params = array_merge($params, $withdrawalParams);
            }

            // Internal Transfer 查询
            if (!$type || $type === 'internal_transfer') {
                $transferParams = [];
                $transferWhere = "it.userId = :userId" . $paramCounter;
                $transferParams['userId' . $paramCounter] = $userId;
                $paramCounter++;

                // 关键字搜索（transactionId 和 Account Nickname）
                if ($keywords) {
                    $searchPattern = "%{$keywords}%";
                    $transferWhere .= " AND (it.transactionId LIKE :search" . $paramCounter;
                    $transferParams['search' . $paramCounter] = $searchPattern;
                    $paramCounter++;

                    // 搜索转出和转入账户的accountNickname
                    $transferWhere .= " OR ta_from.accountNickname LIKE :search" . $paramCounter;
                    $transferParams['search' . $paramCounter] = $searchPattern;
                    $paramCounter++;
                    $transferWhere .= " OR ta_to.accountNickname LIKE :search" . $paramCounter;
                    $transferParams['search' . $paramCounter] = $searchPattern;
                    $paramCounter++;
                    $transferWhere .= ")";
                }

                // 日期范围筛选
                if ($periodFrom) {
                    $transferWhere .= " AND DATE(it.requestedAt) >= :dateFrom" . $paramCounter;
                    $transferParams['dateFrom' . $paramCounter] = $periodFrom;
                    $paramCounter++;
                }

                if ($periodTo) {
                    $transferWhere .= " AND DATE(it.requestedAt) <= :dateTo" . $paramCounter;
                    $transferParams['dateTo' . $paramCounter] = $periodTo;
                    $paramCounter++;
                }

                $transferSelect = "
                    SELECT
                        'internal_transfer' as type,
                        it.id,
                        it.transactionId,
                        it.amount,
                        it.status,
                        it.requestedAt,
                        it.approvedAt,
                        it.completedAt,
                        it.fromTradingAccountId,
                        it.toTradingAccountId,
                        it.fromType,
                        CASE
                            WHEN tea_from.providerAccountId IS NOT NULL AND tea_from.providerAccountId <> '' THEN tea_from.providerAccountId
                            ELSE ta_from.accountNumber
                        END as fromAccountNumber,
                        CASE
                            WHEN tea_from.providerAccountId IS NOT NULL AND tea_from.providerAccountId <> '' THEN tea_from.providerAccountId
                            ELSE ta_from.accountNumber
                        END as fromAccountNickname,
                        CASE
                            WHEN tea_to.providerAccountId IS NOT NULL AND tea_to.providerAccountId <> '' THEN tea_to.providerAccountId
                            ELSE ta_to.accountNumber
                        END as toAccountNumber,
                        CASE
                            WHEN tea_to.providerAccountId IS NOT NULL AND tea_to.providerAccountId <> '' THEN tea_to.providerAccountId
                            ELSE ta_to.accountNumber
                        END as toAccountNickname,
                        CASE
                            WHEN it.fromType = 'trading_account' THEN tp_from.platformKey
                            ELSE tp_to.platformKey
                        END as platformKey,
                        tp_from.platformKey as fromPlatformKey,
                        tp_to.platformKey as toPlatformKey,
                        CASE
                            WHEN it.fromType = 'trading_account' THEN tp_from.shortCode
                            ELSE tp_to.shortCode
                        END as platformShortCode,
                        tp_from.shortCode as fromPlatformShortCode,
                        tp_to.shortCode as toPlatformShortCode,
                        NULL as gatewayName,
                        NULL as gatewayKey,
                        NULL as currencyCode,
                        NULL as platformFee,
                        NULL as platformAmount,
                        it.fromPlatformAmount as fromPlatformAmount,
                        it.toPlatformAmount as toPlatformAmount,
                        it.adminNotes as adminNotes,
                        NULL as reason,
                        COALESCE(it.rejectionNotes, it.failureReason) as rejectionReason
                    FROM internalTransfers it
                    LEFT JOIN tradingAccounts ta_from ON it.fromTradingAccountId = ta_from.id
                    LEFT JOIN tradingPlatforms tp_from ON ta_from.platformId = tp_from.id
                    LEFT JOIN tradingAccountExternalAccounts tea_from ON tea_from.tradingAccountId = ta_from.id
                    LEFT JOIN trading_group tg_from
                        ON tg_from.trading_platforms_key = tp_from.platformKey
                       AND (
                            (tp_from.platformKey IN ('mt5', 'mt4') AND tg_from.id = tea_from.groupId)
                            OR
                            (tp_from.platformKey NOT IN ('mt5', 'mt4') AND tg_from.trading_id = tea_from.groupId)
                       )
                    INNER JOIN tradingAccounts ta_to ON it.toTradingAccountId = ta_to.id
                    LEFT JOIN tradingPlatforms tp_to ON ta_to.platformId = tp_to.id
                    LEFT JOIN tradingAccountExternalAccounts tea_to ON tea_to.tradingAccountId = ta_to.id
                    LEFT JOIN trading_group tg_to
                        ON tg_to.trading_platforms_key = tp_to.platformKey
                       AND (
                            (tp_to.platformKey IN ('mt5', 'mt4') AND tg_to.id = tea_to.groupId)
                            OR
                            (tp_to.platformKey NOT IN ('mt5', 'mt4') AND tg_to.trading_id = tea_to.groupId)
                       )
                    WHERE {$transferWhere}
                ";

                $countParts[] = "SELECT it.id FROM internalTransfers it LEFT JOIN tradingAccounts ta_from ON it.fromTradingAccountId = ta_from.id INNER JOIN tradingAccounts ta_to ON it.toTradingAccountId = ta_to.id WHERE {$transferWhere}";
                $params = array_merge($params, $transferParams);
            }

            // Commission 查询（仅 status=completed，且当前用户为 IB 时）
            $commissionSelect = "";
            $ibPartnerRow = $db->fetchOne('SELECT id FROM ibPartners WHERE userId = :userId LIMIT 1', ['userId' => $userId]);
            if ($ibPartnerRow && (!$type || $type === 'commission')) {
                $commParams = [];
                $commWhere = "ico.ibPartnerId = :ibPartnerId" . $paramCounter . " AND ico.status = 'completed'";
                $commParams['ibPartnerId' . $paramCounter] = $ibPartnerRow['id'];
                $paramCounter++;

                if ($keywords) {
                    $searchPattern = "%{$keywords}%";
                    $commWhere .= " AND CONCAT('CO-', ico.id) LIKE :search" . $paramCounter;
                    $commParams['search' . $paramCounter] = $searchPattern;
                    $paramCounter++;
                }
                if ($periodFrom) {
                    $commWhere .= " AND DATE(COALESCE(ico.payoutDate, ico.orderDate)) >= :dateFrom" . $paramCounter;
                    $commParams['dateFrom' . $paramCounter] = $periodFrom;
                    $paramCounter++;
                }
                if ($periodTo) {
                    $commWhere .= " AND DATE(COALESCE(ico.payoutDate, ico.orderDate)) <= :dateTo" . $paramCounter;
                    $commParams['dateTo' . $paramCounter] = $periodTo;
                    $paramCounter++;
                }

                $commissionSelect = "
                    SELECT
                        'commission' as type,
                        ico.id,
                        CONCAT('CO-', ico.id) as transactionId,
                        ico.commission as amount,
                        'completed' as status,
                        COALESCE(ico.payoutDate, ico.orderDate) as requestedAt,
                        NULL as approvedAt,
                        ico.payoutDate as completedAt,
                        NULL as fromTradingAccountId,
                        NULL as toTradingAccountId,
                        NULL as fromType,
                        NULL as fromAccountNumber,
                        NULL as fromAccountNickname,
                        NULL as toAccountNumber,
                        NULL as toAccountNickname,
                        NULL as platformKey,
                        NULL as fromPlatformKey,
                        NULL as toPlatformKey,
                        NULL as platformShortCode,
                        NULL as fromPlatformShortCode,
                        NULL as toPlatformShortCode,
                        NULL as gatewayName,
                        NULL as gatewayKey,
                        NULL as currencyCode,
                        NULL as platformFee,
                        NULL as platformAmount,
                        NULL as fromPlatformAmount,
                        NULL as toPlatformAmount,
                        NULL as adminNotes,
                        NULL as reason,
                        NULL as rejectionReason
                    FROM ib_commission_order ico
                    WHERE {$commWhere}
                ";
                $countParts[] = "SELECT ico.id FROM ib_commission_order ico WHERE {$commWhere}";
                $params = array_merge($params, $commParams);
            }

            // Credit 查询：trading_credit_deals 是同步任务从平台拉来的赠金/信用流水
            // - 通过 trading_account → userId 限定为当前用户
            // - direction=2 (out) 时 amount 取负，跟 deposit/withdrawal 在 UI 表达方向的方式一致
            // - 走 toAccountNumber / toPlatformKey 字段（credit 落到指定交易账户上，behaviour 类似 deposit）
            $creditSelect = "";
            if (!$type || $type === 'credit') {
                $creditParams = [];
                $creditWhere = "ta_c.userId = :userId" . $paramCounter;
                $creditParams['userId' . $paramCounter] = $userId;
                $paramCounter++;

                if ($keywords) {
                    $searchPattern = "%{$keywords}%";
                    $creditWhere .= " AND (CONCAT('CR-', tcd.id) LIKE :search" . $paramCounter
                        . " OR ta_c.accountNickname LIKE :search" . ($paramCounter + 1) . ")";
                    $creditParams['search' . $paramCounter] = $searchPattern;
                    $creditParams['search' . ($paramCounter + 1)] = $searchPattern;
                    $paramCounter += 2;
                }
                if ($periodFrom) {
                    $creditWhere .= " AND DATE(tcd.deal_time) >= :dateFrom" . $paramCounter;
                    $creditParams['dateFrom' . $paramCounter] = $periodFrom;
                    $paramCounter++;
                }
                if ($periodTo) {
                    $creditWhere .= " AND DATE(tcd.deal_time) <= :dateTo" . $paramCounter;
                    $creditParams['dateTo' . $paramCounter] = $periodTo;
                    $paramCounter++;
                }

                $creditSelect = "
                    SELECT
                        'credit' as type,
                        tcd.id,
                        CONCAT('CR-', tcd.id) as transactionId,
                        CASE WHEN tcd.direction = 2 THEN -tcd.amount ELSE tcd.amount END as amount,
                        'completed' as status,
                        tcd.deal_time as requestedAt,
                        NULL as approvedAt,
                        tcd.deal_time as completedAt,
                        NULL as fromTradingAccountId,
                        tcd.trading_account_id as toTradingAccountId,
                        NULL as fromType,
                        NULL as fromAccountNumber,
                        NULL as fromAccountNickname,
                        CASE
                            WHEN tea_c.providerAccountId IS NOT NULL AND tea_c.providerAccountId <> '' THEN tea_c.providerAccountId
                            ELSE ta_c.accountNumber
                        END as toAccountNumber,
                        ta_c.accountNickname as toAccountNickname,
                        tp_c.platformKey as platformKey,
                        NULL as fromPlatformKey,
                        tp_c.platformKey as toPlatformKey,
                        tp_c.shortCode as platformShortCode,
                        NULL as fromPlatformShortCode,
                        tp_c.shortCode as toPlatformShortCode,
                        NULL as gatewayName,
                        NULL as gatewayKey,
                        ta_c.accountCurrency as currencyCode,
                        NULL as platformFee,
                        tcd.amount as platformAmount,
                        NULL as fromPlatformAmount,
                        tcd.amount as toPlatformAmount,
                        NULL as adminNotes,
                        tcd.comment as reason,
                        NULL as rejectionReason
                    FROM trading_credit_deals tcd
                    INNER JOIN tradingAccounts ta_c ON ta_c.id = tcd.trading_account_id
                    LEFT JOIN tradingPlatforms tp_c ON tp_c.id = ta_c.platformId
                    LEFT JOIN tradingAccountExternalAccounts tea_c ON tea_c.tradingAccountId = ta_c.id
                    WHERE {$creditWhere}
                ";
                $countParts[] = "SELECT tcd.id FROM trading_credit_deals tcd INNER JOIN tradingAccounts ta_c ON ta_c.id = tcd.trading_account_id WHERE {$creditWhere}";
                $params = array_merge($params, $creditParams);
            }

            // 组合 SQL
            $parts = array_filter([$depositSelect, $withdrawalSelect, $transferSelect, $commissionSelect, $creditSelect]);
            if (empty($parts)) {
                $items = [];
                $total = 0;
            } else {
                $sql = "(" . implode(") UNION ALL (", $parts) . ") ORDER BY requestedAt DESC LIMIT {$perPage} OFFSET {$offset}";

                // 获取总数
                if (empty($countParts)) {
                    $total = 0;
                } else {
                    $countSql = "SELECT COUNT(*) as total FROM (" . implode(" UNION ALL ", $countParts) . ") as combined";
                    $totalResult = $db->fetchOne($countSql, $params);
                    $total = (int)($totalResult['total'] ?? 0);
                }

                $items = $db->fetchAll($sql, $params);
            }

            Response::paginated(
                $items,
                $total,
                $page,
                $perPage
            );
        } catch (Exception $e) {
            Response::error('Failed to load transaction history: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List commission rules that can be assigned to this client's trading accounts
     * (active rules on approved IB profiles owned by the client).
     */
    public function getAssignableCommissionRules($clientUserId) {
        $clientUserId = (int) $clientUserId;
        $client = $this->userModel->findById($clientUserId);
        if (!$client) {
            Response::notFound('Client not found');
        }

        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT cr.id AS ruleId, cr.ruleName, cr.ruleType, cr.fixed_amount, cr.rebateAmount,
                    cr.status, ib.id AS ibPartnerId, ib.ibCode
             FROM ibPartners ib
             INNER JOIN ib_partner_rules pr ON pr.ibPartnerId = ib.id
             INNER JOIN ibCommissionRules cr ON cr.id = pr.ruleId AND cr.status = 'active'
             WHERE ib.userId = :uid AND ib.status = 'approved'
               AND TRIM(COALESCE(ib.ibCode, '')) <> ''
             ORDER BY ib.id ASC, cr.id ASC",
            ['uid' => $clientUserId]
        );

        $items = array_map(static function ($r) {
            return [
                'ruleId' => (int) $r['ruleId'],
                'ibPartnerId' => (int) $r['ibPartnerId'],
                'ibCode' => $r['ibCode'] ?? '',
            ];
        }, $rows ?: []);

        Response::success(['items' => AssignableIbRebatePackage::fromRuleRows($items)]);
    }

    /**
     * PATCH assigned commission rule on an existing trading account owned by the client.
     */
    public function updateAssignedCommissionRule($clientUserId, $tradingAccountId) {
        $clientUserId = (int) $clientUserId;
        $tradingAccountId = (int) $tradingAccountId;
        $client = $this->userModel->findById($clientUserId);
        if (!$client) {
            Response::notFound('Client not found');
        }

        $account = $this->accountModel->findById($tradingAccountId);
        if (!$account || (int) ($account['userId'] ?? 0) !== $clientUserId) {
            Response::notFound('Trading account not found');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $hasKey = array_key_exists('assignedCommissionRuleId', $data);
        if (!$hasKey) {
            Response::validationError([
                'assignedCommissionRuleId' => ['assignedCommissionRuleId is required (null to clear)']
            ]);
        }

        $ruleId = $this->parseAssignableCommissionRuleId(
            $data['assignedCommissionRuleId'],
            $clientUserId,
            false,
            null
        );

        $this->accountModel->update($tradingAccountId, [
            'assignedCommissionRuleId' => $ruleId,
        ]);

        $updated = $this->accountModel->findById($tradingAccountId);
        Response::success([
            'tradingAccountId' => $tradingAccountId,
            'assignedCommissionRuleId' => isset($updated['assignedCommissionRuleId']) && $updated['assignedCommissionRuleId'] !== null
                ? (int) $updated['assignedCommissionRuleId']
                : null,
        ], 'Assigned commission rule updated');
    }

    /**
     * @return int|null null clears assignment; positive int sets rule
     */
    private function parseAssignableCommissionRuleId($raw, int $userId, bool $fromCreate, $createData) {
        if ($raw === null || $raw === '' || $raw === false) {
            return null;
        }
        if (is_string($raw) && strtolower(trim($raw)) === 'null') {
            return null;
        }
        $ruleId = (int) $raw;
        if ($ruleId <= 0) {
            return null;
        }
        if (!$this->isAssignableCommissionRuleForUser($ruleId, $userId)) {
            if ($fromCreate && is_array($createData)) {
                $this->failTradingAccountCreate($createData, $userId, 'Invalid or unauthorized commission rule');
            }
            Response::validationError([
                'assignedCommissionRuleId' => ['Invalid or unauthorized commission rule for this client']
            ], 'Invalid or unauthorized commission rule for this client');
        }
        return $ruleId;
    }

    private function isAssignableCommissionRuleForUser(int $ruleId, int $userId): bool {
        if ($ruleId <= 0 || $userId <= 0) {
            return false;
        }
        $db = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT cr.id
             FROM ib_partner_rules pr
             INNER JOIN ibCommissionRules cr ON cr.id = pr.ruleId AND cr.status = 'active'
             INNER JOIN ibPartners ib ON ib.id = pr.ibPartnerId
               AND ib.userId = :uid AND ib.status = 'approved'
               AND TRIM(COALESCE(ib.ibCode, '')) <> ''
             WHERE pr.ruleId = :rid
             LIMIT 1",
            ['uid' => $userId, 'rid' => $ruleId]
        );
        return (bool) $row;
    }
}
