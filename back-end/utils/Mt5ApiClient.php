<?php
/**
 * MT5 API Client
 * 对官方 MT5 WebAPI PHP 封装 CRM 适配
 */

require_once __DIR__ . '/Password.php';

class Mt5ApiClient
{
    private $config;
    private $api;
    private bool $connected = false;

    public function __construct(array $config = [])
    {
        if (empty($config)) {
            $appConfig = require __DIR__ . '/../config/app.php';
            $config = $appConfig['integrations']['mt5'] ?? [];
        }

        $this->config = $config;
        $this->api = null;
        $this->connected = false;

        $this->loadMt5Library();
    }

    /**
     * 建立到 MT5 WebAPI 的连接
     *
     * @return bool
     * @throws Exception
     */
    public function connect(): bool
    {
        if ($this->connected && $this->api instanceof MTWebAPI) {
            return true;
        }

        $host = trim((string)($this->config['host'] ?? ''));
        $port = (int)($this->config['port'] ?? 0);
        $timeout = (int)($this->config['timeout'] ?? 10);
        $login = (string)($this->config['manager_login'] ?? '');
        $password = (string)($this->config['manager_password'] ?? '');
        $agent = (string)($this->config['agent'] ?? 'CRM-MT5');
        $logPath = (string)($this->config['log_path'] ?? '/tmp/');
        $isCrypt = isset($this->config['is_crypt']) ? (bool)$this->config['is_crypt'] : true;

        if ($host === '' || $port <= 0 || $login === '' || $password === '') {
            throw new Exception('MT5 config is incomplete: host/port/manager_login/manager_password are required');
        }

        $this->api = new MTWebAPI($agent, $logPath, $isCrypt);
        $retCode = $this->api->Connect($host, $port, $timeout, $login, $password);
        $this->assertMt5Success($retCode, 'Connect');
        $this->connected = true;

        return true;
    }

    /**
     * 断开连接
     *
     * @return void
     */
    public function disconnect()
    {
        if ($this->api instanceof MTWebAPI) {
            $this->api->Disconnect();
        }
        $this->connected = false;
    }

    /**
     * 检查当前对象是否已建立连接
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->connected && $this->api instanceof MTWebAPI;
    }

    /**
     * Ping MT5 服务
     *
     * @return bool
     * @throws Exception
     */
    public function ping(): bool
    {
        $this->ensureConnected();
        $retCode = $this->api->Ping();
        $this->assertMt5Success($retCode, 'Ping');
        return true;
    }

    /**
     * 获取底层 MTWebAPI 实例
     *
     * @return MTWebAPI
     * @throws Exception
     */
    public function getRawClient(): ?MTWebAPI
    {
        $this->ensureConnected();
        return $this->api;
    }

    /**
     * 快速测试连接
     *
     * @return array
     */
    public function testConnection(): array
    {
        try {
            $this->connect();
            $this->ping();
            return [
                'success' => true,
                'message' => 'MT5 connection OK'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        } finally {
            $this->disconnect();
        }
    }

    /**
     * 创建 MT5 用户（交易账户）
     *
     * 接收字段：
     * - group (required)
     * - login (optional, int; usually 0 for server-assigned)
     * - name, company, country, city, state, zipCode, address, phone, email
     * - idNumber, statusText, comment
     * - leverage, agent, language, color, phonePassword, rights
     * - mainPassword, investPassword (required unless you generate before calling)
     * - passwordMinLength (optional, group policy min length for validation)
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function createUser(array $data): array
    {
        $group = isset($data['group']) ? (string)$data['group'] : '';
        if (trim($group) === '') {
            throw new InvalidArgumentException('MT5 createUser requires group');
        }

        $user = MTUser::CreateDefault();
        $user->Login = isset($data['login']) ? (int)$data['login'] : 0;
        $user->Group = $group;
        $user->Name = isset($data['name']) ? (string)$data['name'] : '';
        $user->Company = isset($data['company']) ? (string)$data['company'] : '';
        $user->Country = isset($data['country']) ? (string)$data['country'] : '';
        $user->City = isset($data['city']) ? (string)$data['city'] : '';
        $user->State = isset($data['state']) ? (string)$data['state'] : '';
        $user->ZipCode = isset($data['zipCode']) ? (string)$data['zipCode'] : '';
        $user->Address = isset($data['address']) ? (string)$data['address'] : '';
        $user->Phone = isset($data['phone']) ? (string)$data['phone'] : '';
        $user->Email = isset($data['email']) ? (string)$data['email'] : '';
        $user->ID = isset($data['idNumber']) ? (string)$data['idNumber'] : '';
        $user->Status = isset($data['statusText']) ? (string)$data['statusText'] : '';
        $user->Comment = isset($data['comment']) ? (string)$data['comment'] : '';
        $user->Leverage = isset($data['leverage']) ? (int)$data['leverage'] : (int)($this->config['default_leverage'] ?? 100);
        $user->Agent = isset($data['agent']) ? (int)$data['agent'] : 0;
        $user->Language = isset($data['language']) ? (int)$data['language'] : 0;
        $user->Color = isset($data['color']) ? (int)$data['color'] : (int)$user->Color;
        $user->PhonePassword = isset($data['phonePassword']) ? (string)$data['phonePassword'] : '';
        $user->Rights = isset($data['rights']) ? (int)$data['rights'] : (int)$user->Rights;

        $mainPassword = isset($data['mainPassword']) ? (string)$data['mainPassword'] : '';
        $investPassword = isset($data['investPassword']) ? (string)$data['investPassword'] : '';

        if (Password::validateMt5Password($mainPassword, $data['passwordMinLength'] ?? 8) === false) {
            throw new InvalidArgumentException('Main password does not meet MT5 policy requirements');
        }

        $user->MainPassword = $mainPassword;
        $user->InvestPassword = $investPassword;

        $this->ensureConnected();
        $createdUser = null;
        $retCode = $this->api->UserAdd($user, $createdUser);
        $this->assertMt5Success($retCode, 'UserAdd');

        $createdUserArray = $this->objectToArray($createdUser);
        $createdLogin = isset($createdUser->Login) ? (string)$createdUser->Login : (string)$user->Login;

        return [
            'login' => $createdLogin,
            'mainPassword' => $mainPassword,
            'investPassword' => $investPassword,
            'user' => $createdUserArray,
        ];
    }

    /**
     * 获取 MT5 用户信息
     *
     * @param int|string $login
     * @return array
     * @throws Exception
     */
    public function getUser($login): array
    {
        $this->ensureConnected();

        $user = null;
        $retCode = $this->api->UserGet((int)$login, $user);
        $this->assertMt5Success($retCode, 'UserGet');

        return $this->objectToArray($user);
    }

    /**
     * 删除 MT5 用户
     *
     * @param int|string $login
     * @return array
     * @throws Exception
     */
    public function deleteUser($login): array
    {
        $login = (int)$login;
        if ($login <= 0) {
            throw new InvalidArgumentException('deleteUser requires valid login');
        }

        $this->ensureConnected();
        $retCode = $this->api->UserDelete($login);
        $this->assertMt5Success($retCode, 'UserDelete');

        return [
            'deleted' => true,
            'login' => (string)$login,
        ];
    }

    /**
     * 增量更新 MT5 用户信息
     *
     * 用法：
     * - updateUser(100283, ['name' => 'New Name', 'phone' => '123'])
     * - updateUser(['login' => 100283, 'email' => 'a@b.com'])
     *
     * 支持字段（增量）：
     * group, name, company, country, city, state, zipCode, address, phone, email,
     * idNumber, statusText, comment, leverage, agent, language, color, phonePassword, rights
     *
     * @param int|string|array $loginOrData
     * @param array $patch
     * @return array
     * @throws Exception
     */
    public function updateUser($loginOrData, array $patch = []): array
    {
        if (is_array($loginOrData)) {
            $patch = $loginOrData;
            $login = isset($patch['login']) ? (int)$patch['login'] : 0;
        } else {
            $login = (int)$loginOrData;
        }

        if ($login <= 0) {
            throw new InvalidArgumentException('MT5 updateUser requires valid login');
        }

        $this->ensureConnected();

        $existingUser = null;
        $retCode = $this->api->UserGet($login, $existingUser);
        $this->assertMt5Success($retCode, 'UserGet');

        if (!$existingUser instanceof MTUser) {
            throw new Exception('UserGet returned invalid MTUser object');
        }

        $before = $this->objectToArray($existingUser);

        // Normalize aliases and apply only provided fields.
        $fieldMap = [
            'group' => 'Group',
            'name' => 'Name',
            'company' => 'Company',
            'country' => 'Country',
            'city' => 'City',
            'state' => 'State',
            'zipCode' => 'ZipCode',
            'address' => 'Address',
            'phone' => 'Phone',
            'email' => 'Email',
            'idNumber' => 'ID',
            'statusText' => 'Status',
            'comment' => 'Comment',
            'leverage' => 'Leverage',
            'agent' => 'Agent',
            'language' => 'Language',
            'color' => 'Color',
            'phonePassword' => 'PhonePassword',
            'rights' => 'Rights',
        ];

        $applied = [];
        foreach ($fieldMap as $inputKey => $mt5Prop) {
            if (!array_key_exists($inputKey, $patch)) {
                continue;
            }

            $value = $patch[$inputKey];
            switch ($mt5Prop) {
                case 'Leverage':
                case 'Agent':
                case 'Language':
                case 'Color':
                case 'Rights':
                    $existingUser->$mt5Prop = ($value === null || $value === '') ? 0 : (int)$value;
                    break;
                default:
                    $existingUser->$mt5Prop = ($value === null) ? '' : (string)$value;
                    break;
            }

            $applied[$inputKey] = $existingUser->$mt5Prop;
        }

        if (empty($applied)) {
            return [
                'login' => (string)$login,
                'updated' => false,
                'message' => 'No patch fields provided',
                'before' => $before,
                'after' => $before,
                'applied' => [],
            ];
        }

        $existingUser->Login = $login;

        $updatedUser = null;
        $retCode = $this->api->UserUpdate($existingUser, $updatedUser);
        $this->assertMt5Success($retCode, 'UserUpdate');

        $after = $this->objectToArray($updatedUser);

        return [
            'login' => (string)$login,
            'updated' => true,
            'applied' => $applied,
            'before' => $before,
            'after' => $after,
        ];
    }

    /**
     * 获取 MT5 账户资金信息（余额/净值/保证金等）
     *
     * @param int|string $login
     * @return array
     * @throws Exception
     */
    public function getBalance($login): array
    {
        $this->ensureConnected();

        $account = null;
        $retCode = $this->api->UserAccountGet((int)$login, $account);
        $this->assertMt5Success($retCode, 'UserAccountGet');

        $accountArray = $this->objectToArray($account);

        return [
            'login' => isset($accountArray['Login']) ? (string)$accountArray['Login'] : (string)$login,
            'balance' => isset($accountArray['Balance']) ? (float)$accountArray['Balance'] : 0.0,
            'credit' => isset($accountArray['Credit']) ? (float)$accountArray['Credit'] : 0.0,
            'equity' => isset($accountArray['Equity']) ? (float)$accountArray['Equity'] : 0.0,
            'profit' => isset($accountArray['Profit']) ? (float)$accountArray['Profit'] : 0.0,
            'margin' => isset($accountArray['Margin']) ? (float)$accountArray['Margin'] : 0.0,
            'marginFree' => isset($accountArray['MarginFree']) ? (float)$accountArray['MarginFree'] : 0.0,
            'marginLevel' => isset($accountArray['MarginLevel']) ? (float)$accountArray['MarginLevel'] : 0.0,
            'marginLeverage' => isset($accountArray['MarginLeverage']) ? (int)$accountArray['MarginLeverage'] : 0,
            'currencyDigits' => isset($accountArray['CurrencyDigits']) ? (int)$accountArray['CurrencyDigits'] : 0,
            'account' => $accountArray,
        ];
    }

    /**
     * 调整 MT5 账户余额
     *
     * - deposit 和 withdraw 使用对应的 function，不要直接调用
     *
     * @param int|string $login
     * @param float|int|string $amount
     * @param string $comment
     * @param int|string $type
     * @param bool $marginCheck
     * @return array
     * @throws Exception
     */
    public function updateBalance($login, $amount, string $comment = '', $type = 'balance', bool $marginCheck = true): array
    {
        $this->ensureConnected();

        $login = (int)$login;
        $amount = (float)$amount;
        if ($login <= 0) {
            throw new InvalidArgumentException('updateBalance requires valid login');
        }
        if ($amount == 0.0) {
            throw new InvalidArgumentException('updateBalance amount cannot be zero');
        }

        $dealAction = $this->normalizeDealAction($type);
        if ($comment === '') {
            $comment = 'CRM updateBalance ' . date('Y-m-d H:i:s');
        }

        $ticket = 0;
        $retCode = $this->api->TradeBalance($login, $dealAction, $amount, $comment, $ticket, (bool)$marginCheck);
        $this->assertMt5Success($retCode, 'TradeBalance');

        $balanceInfo = $this->getBalance($login);

        return [
            'login' => (string)$login,
            'ticket' => (string)$ticket,
            'amount' => $amount,
            'type' => $dealAction,
            'comment' => $comment,
            'balance' => $balanceInfo,
        ];
    }

    /**
     * MT5 入金
     *
     * @param int|string $login
     * @param float|int|string $amount
     * @param string $comment
     * @param bool $marginCheck
     * @return array
     * @throws Exception
     */
    public function deposit($login, $amount, string $comment = '', bool $marginCheck = true): array
    {
        $amount = (float)$amount;
        if ($amount <= 0) {
            throw new InvalidArgumentException('deposit amount must be greater than 0');
        }

        if ($comment === '') {
            $comment = 'CRM deposit ' . date('Y-m-d H:i:s');
        }

        return $this->updateBalance($login, $amount, $comment, 'balance', $marginCheck);
    }

    /**
     * MT5 提现/扣款
     *
     * 说明：
     * - 对外统一传正数金额
     * - 内部转换为负数并使用 charge 类型提交
     *
     * @param int|string $login
     * @param float|int|string $amount
     * @param string $comment
     * @param bool $marginCheck
     * @return array
     * @throws Exception
     */
    public function withdraw($login, $amount, string $comment = '', bool $marginCheck = true): array
    {
        $amount = (float)$amount;
        if ($amount <= 0) {
            throw new InvalidArgumentException('withdraw amount must be greater than 0');
        }

        if ($comment === '') {
            $comment = 'CRM withdraw ' . date('Y-m-d H:i:s');
        }

        return $this->updateBalance($login, -1 * $amount, $comment, 'charge', $marginCheck);
    }

    /**
     * 获取当前持仓（positions）
     *
     * @param int|string $login
     * @param int $offset
     * @param int $limit
     * @return array
     * @throws Exception
     */
    public function getPositions($login, int $offset = 0, int $limit = 100): array
    {
        $this->ensureConnected();

        $login = (int)$login;
        $offset = max(0, (int)$offset);
        $limit = max(1, (int)$limit);
        if ($login <= 0) {
            throw new InvalidArgumentException('getPositions requires valid login');
        }

        $total = 0;
        $retCode = $this->api->PositionGetTotal($login, $total);
        $this->assertMt5Success($retCode, 'PositionGetTotal');

        $items = [];
        if ($total > 0 && $offset < $total) {
            $fetch = min($limit, $total - $offset);
            $positions = [];
            $retCode = $this->api->PositionGetPage($login, $offset, $fetch, $positions);
            $this->assertMt5Success($retCode, 'PositionGetPage', true);
            $items = $this->objectToArray($positions);
        }

        return [
            'login' => (string)$login,
            'total' => (int)$total,
            'offset' => $offset,
            'limit' => $limit,
            'items' => is_array($items) ? $items : [],
        ];
    }

    /**
     * 获取当前挂单（orders）
     *
     * @param int|string $login
     * @param int $offset
     * @param int $limit
     * @return array
     * @throws Exception
     */
    public function getOrders($login, int $offset = 0, int $limit = 100): array
    {
        $this->ensureConnected();

        $login = (int)$login;
        $offset = max(0, (int)$offset);
        $limit = max(1, (int)$limit);
        if ($login <= 0) {
            throw new InvalidArgumentException('getOrders requires valid login');
        }

        $total = 0;
        $retCode = $this->api->OrderGetTotal($login, $total);
        $this->assertMt5Success($retCode, 'OrderGetTotal');

        $items = [];
        if ($total > 0 && $offset < $total) {
            $fetch = min($limit, $total - $offset);
            $orders = [];
            $retCode = $this->api->OrderGetPage($login, $offset, $fetch, $orders);
            $this->assertMt5Success($retCode, 'OrderGetPage', true);
            $items = $this->objectToArray($orders);
        }

        return [
            'login' => (string)$login,
            'total' => (int)$total,
            'offset' => $offset,
            'limit' => $limit,
            'items' => is_array($items) ? $items : [],
        ];
    }

    /**
     * 获取并按 CRM orders 结构映射 MT5 持仓
     *
     * @param int|string $login
     * @param int $offset
     * @param int $limit
     * @return array
     * @throws Exception
     */
    public function getNormalizedPositionsForOrders($login, int $offset = 0, int $limit = 500): array
    {
        $positionsData = $this->getPositions($login, $offset, $limit);
        $items = isset($positionsData['items']) && is_array($positionsData['items']) ? $positionsData['items'] : [];
        $result = [];

        foreach ($items as $item) {
            $id = isset($item['Position']) ? (int)$item['Position'] : 0;
            if ($id <= 0) {
                continue;
            }

            $symbolName = isset($item['Symbol']) ? (string)$item['Symbol'] : '';
            $result[] = [
                'Id' => $id,
                'SymbolId' => 0,
                'Symbol' => $symbolName !== '' ? $symbolName : null,
                'Source' => $symbolName !== '' ? $symbolName : null,
                'Digits' => isset($item['Digits']) ? (int)$item['Digits'] : 0,
                'Cmd' => isset($item['Action']) ? (int)$item['Action'] : 0,
                'Volume' => $this->normalizeMt5VolumeForOrder($item),
                'OpenTime' => isset($item['TimeCreate']) ? (int)$item['TimeCreate'] : 0,
                'State' => 1,
                'OrderType' => 'position',
                'TargetPrice' => null,
                'OpenPrice' => isset($item['PriceOpen']) ? (float)$item['PriceOpen'] : 0.0,
                'Sl' => isset($item['PriceSL']) ? (float)$item['PriceSL'] : null,
                'Tp' => isset($item['PriceTP']) ? (float)$item['PriceTP'] : null,
                'CloseTime' => 0,
                'Expiration' => 0,
                'Reason' => isset($item['Reason']) ? (int)$item['Reason'] : null,
                'Commission' => 0.0,
                'CommissionAgent' => null,
                'Storage' => isset($item['Storage']) ? (float)$item['Storage'] : null,
                'ClosePrice' => null,
                'Ask' => isset($item['PriceCurrent']) ? (float)$item['PriceCurrent'] : null,
                'Bid' => isset($item['PriceCurrent']) ? (float)$item['PriceCurrent'] : null,
                'Profit' => isset($item['Profit']) ? (float)$item['Profit'] : null,
                'Taxes' => null,
                'Magic' => isset($item['ExpertID']) ? (int)$item['ExpertID'] : null,
                'Comment' => $item['Comment'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * 获取并按 CRM orders 结构映射 MT5 挂单
     *
     * @param int|string $login
     * @param int $offset
     * @param int $limit
     * @return array
     * @throws Exception
     */
    public function getNormalizedPendingOrdersForOrders($login, int $offset = 0, int $limit = 500): array
    {
        $ordersData = $this->getOrders($login, $offset, $limit);
        $items = isset($ordersData['items']) && is_array($ordersData['items']) ? $ordersData['items'] : [];
        $result = [];

        foreach ($items as $item) {
            $id = isset($item['Order']) ? (int)$item['Order'] : 0;
            if ($id <= 0) {
                continue;
            }

            $symbolName = isset($item['Symbol']) ? (string)$item['Symbol'] : '';
            $result[] = [
                'Id' => $id,
                'SymbolId' => 0,
                'Symbol' => $symbolName !== '' ? $symbolName : null,
                'Source' => $symbolName !== '' ? $symbolName : null,
                'Digits' => isset($item['Digits']) ? (int)$item['Digits'] : 0,
                'Cmd' => isset($item['Type']) ? (int)$item['Type'] : 0,
                'Volume' => $this->normalizeMt5VolumeForOrder($item),
                'OpenTime' => isset($item['TimeSetup']) ? (int)$item['TimeSetup'] : 0,
                'State' => 0,
                'OrderType' => 'pending',
                'TargetPrice' => isset($item['PriceOrder']) ? (float)$item['PriceOrder'] : null,
                'OpenPrice' => isset($item['PriceOrder']) ? (float)$item['PriceOrder'] : 0.0,
                'Sl' => isset($item['PriceSL']) ? (float)$item['PriceSL'] : null,
                'Tp' => isset($item['PriceTP']) ? (float)$item['PriceTP'] : null,
                'CloseTime' => 0,
                'Expiration' => isset($item['TimeExpiration']) ? (int)$item['TimeExpiration'] : 0,
                'Reason' => isset($item['Reason']) ? (int)$item['Reason'] : null,
                'Commission' => 0.0,
                'CommissionAgent' => null,
                'Storage' => null,
                'ClosePrice' => null,
                'Ask' => isset($item['PriceCurrent']) ? (float)$item['PriceCurrent'] : null,
                'Bid' => isset($item['PriceCurrent']) ? (float)$item['PriceCurrent'] : null,
                'Profit' => null,
                'Taxes' => null,
                'Magic' => isset($item['ExpertID']) ? (int)$item['ExpertID'] : null,
                'Comment' => $item['Comment'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * 获取并按 CRM orders 结构映射 MT5 历史（优先 deals，失败回退 history orders）
     *
     * @param int|string $login
     * @param int|string|null $from Unix 时间戳
     * @param int|string|null $to Unix 时间戳
     * @param int $offset
     * @param int $limit
     * @return array
     * @throws Exception
     */
    public function getNormalizedHistoryForOrders($login, $from = null, $to = null, int $offset = 0, int $limit = 500): array
    {
        try {
            $historyData = $this->getHistoryDeals($login, $from, $to, $offset, $limit);
            $items = isset($historyData['items']) && is_array($historyData['items']) ? $historyData['items'] : [];
            return $this->mapMt5DealsToOrders($items);
        } catch (Exception $e) {
            $historyData = $this->getHistoryOrders($login, $from, $to, $offset, $limit);
            $items = isset($historyData['items']) && is_array($historyData['items']) ? $historyData['items'] : [];
            return $this->mapMt5HistoryOrdersToOrders($items);
        }
    }

    /**
     * MT5 统一账户快照（余额 + 订单）
     *
     * @param int|string $login
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getAccountOrdersBalanceSnapshot($login, array $options = []): array
    {
        $offset = isset($options['offset']) ? max(0, (int)$options['offset']) : 0;
        $limit = isset($options['limit']) ? max(1, (int)$options['limit']) : 500;
        $from = $options['from'] ?? null;
        $to = $options['to'] ?? null;

        $includeBalance = array_key_exists('includeBalance', $options) ? (bool)$options['includeBalance'] : true;
        $includePositions = array_key_exists('includePositions', $options) ? (bool)$options['includePositions'] : true;
        $includePending = array_key_exists('includePending', $options) ? (bool)$options['includePending'] : true;
        $includeHistory = array_key_exists('includeHistory', $options) ? (bool)$options['includeHistory'] : false;

        $snapshot = [
            'login' => (string)$login,
            'balance' => null,
            'credit' => null,
            'positions' => [],
            'pendingOrders' => [],
            'historyOrders' => [],
        ];

        if ($includeBalance) {
            $balanceData = $this->getBalance($login);
            $snapshot['balance'] = isset($balanceData['balance']) ? (float)$balanceData['balance'] : null;
            // credit 来自同一次 UserAccountGet，没有额外网络开销
            $snapshot['credit'] = isset($balanceData['credit']) ? (float)$balanceData['credit'] : null;
        }

        if ($includePositions) {
            $snapshot['positions'] = $this->getNormalizedPositionsForOrders($login, $offset, $limit);
        }

        if ($includePending) {
            $snapshot['pendingOrders'] = $this->getNormalizedPendingOrdersForOrders($login, $offset, $limit);
        }

        if ($includeHistory) {
            $snapshot['historyOrders'] = $this->getNormalizedHistoryForOrders($login, $from, $to, $offset, $limit);
        }

        return $snapshot;
    }

    /**
     * 获取历史订单（history orders）
     *
     * @param int|string $login
     * @param int|string|null $from Unix 时间戳；null=30天前
     * @param int|string|null $to Unix 时间戳；null=当前时间
     * @param int $offset
     * @param int $limit
     * @return array
     * @throws Exception
     */
    public function getHistoryOrders($login, $from = null, $to = null, int $offset = 0, int $limit = 100)
    {
        $this->ensureConnected();

        $login = (int)$login;
        $offset = max(0, (int)$offset);
        $limit = max(1, (int)$limit);
        if ($login <= 0) {
            throw new InvalidArgumentException('getHistoryOrders requires valid login');
        }

        $toTs = $this->normalizeUnixTimestamp($to, time());
        $fromTs = $this->normalizeUnixTimestamp($from, $toTs - 30 * 86400);
        if ($fromTs > $toTs) {
            throw new InvalidArgumentException('getHistoryOrders requires from <= to');
        }

        $total = 0;
        $retCode = $this->api->HistoryGetTotal($login, $fromTs, $toTs, $total);
        $this->assertMt5Success($retCode, 'HistoryGetTotal');

        $items = [];
        if ($total > 0 && $offset < $total) {
            $fetch = min($limit, $total - $offset);
            $orders = [];
            $retCode = $this->api->HistoryGetPage($login, $fromTs, $toTs, $offset, $fetch, $orders);
            $this->assertMt5Success($retCode, 'HistoryGetPage', true);
            $items = $this->objectToArray($orders);
        }

        return [
            'login' => (string)$login,
            'from' => $fromTs,
            'to' => $toTs,
            'total' => (int)$total,
            'offset' => $offset,
            'limit' => $limit,
            'items' => is_array($items) ? $items : [],
        ];
    }

    /**
     * 统一 MT5 volume 到 CRM 内部 volume（lots）
     *
     * @param array $item MT5 原始订单/持仓项
     * @return float
     */
    private function normalizeMt5VolumeForOrder(array $item): float
    {
        if (isset($item['VolumeInitialExt']) && is_numeric($item['VolumeInitialExt'])) {
            return ((float)$item['VolumeInitialExt'] / 100000000.0) * 100.0;
        }
        if (isset($item['VolumeInitial']) && is_numeric($item['VolumeInitial'])) {
            return ((float)$item['VolumeInitial'] / 10000.0) * 100.0;
        }
        return 0.0;
    }

    /**
     * 获取历史成交（deals）
     *
     * @param int|string $login
     * @param int|string|null $from Unix 时间戳；null=30天前
     * @param int|string|null $to Unix 时间戳；null=当前时间
     * @param int $offset
     * @param int $limit
     * @return array
     * @throws Exception
     */
    public function getHistoryDeals($login, $from = null, $to = null, int $offset = 0, int $limit = 100)
    {
        $this->ensureConnected();

        $login = (int)$login;
        $offset = max(0, (int)$offset);
        $limit = max(1, (int)$limit);
        if ($login <= 0) {
            throw new InvalidArgumentException('getHistoryDeals requires valid login');
        }

        $toTs = $this->normalizeUnixTimestamp($to, time());
        $fromTs = $this->normalizeUnixTimestamp($from, $toTs - 30 * 86400);
        if ($fromTs > $toTs) {
            throw new InvalidArgumentException('getHistoryDeals requires from <= to');
        }

        $total = 0;
        $retCode = $this->api->DealGetTotal($login, $fromTs, $toTs, $total);
        $this->assertMt5Success($retCode, 'DealGetTotal');

        $items = [];
        if ($total > 0 && $offset < $total) {
            $fetch = min($limit, $total - $offset);
            $deals = [];
            $retCode = $this->api->DealGetPage($login, $fromTs, $toTs, $offset, $fetch, $deals);
            $this->assertMt5Success($retCode, 'DealGetPage', true);
            $items = $this->objectToArray($deals);
        }

        return [
            'login' => (string)$login,
            'from' => $fromTs,
            'to' => $toTs,
            'total' => (int)$total,
            'offset' => $offset,
            'limit' => $limit,
            'items' => is_array($items) ? $items : [],
        ];
    }

    /**
     * 将 MT5 history orders 映射为 CRM orders 入库结构（trading_status=2）
     *
     * @param array $items
     * @return array
     */
    private function mapMt5HistoryOrdersToOrders(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $id = isset($item['Order']) ? (int)$item['Order'] : 0;
            if ($id <= 0) {
                continue;
            }

            // 仅保留真实成交的 history order（PARTIAL / FILLED），过滤掉 CANCELED / REJECTED / EXPIRED，
            // 避免污染 orders 表与 getLastSyncTime 的 MAX(closetime)。
            $state = isset($item['State']) ? (int)$item['State'] : -1;
            if ($state !== 3 && $state !== 4) {
                continue;
            }

            $symbolName = isset($item['Symbol']) ? (string)$item['Symbol'] : '';
            $result[] = [
                'Id' => $id,
                'SymbolId' => 0,
                'Symbol' => $symbolName !== '' ? $symbolName : null,
                'Source' => $symbolName !== '' ? $symbolName : null,
                'Digits' => isset($item['Digits']) ? (int)$item['Digits'] : 0,
                'Cmd' => isset($item['Type']) ? (int)$item['Type'] : 0,
                'Volume' => $this->normalizeMt5VolumeForOrder($item),
                'OpenTime' => isset($item['TimeSetup']) ? (int)$item['TimeSetup'] : 0,
                'State' => 2,
                'OrderType' => 'history',
                'TargetPrice' => isset($item['PriceOrder']) ? (float)$item['PriceOrder'] : null,
                'OpenPrice' => isset($item['PriceCurrent']) ? (float)$item['PriceCurrent'] : 0.0,
                'Sl' => isset($item['PriceSL']) ? (float)$item['PriceSL'] : null,
                'Tp' => isset($item['PriceTP']) ? (float)$item['PriceTP'] : null,
                'CloseTime' => isset($item['TimeDone']) ? (int)$item['TimeDone'] : 0,
                'Expiration' => isset($item['TimeExpiration']) ? (int)$item['TimeExpiration'] : 0,
                'Reason' => isset($item['Reason']) ? (int)$item['Reason'] : null,
                'Commission' => 0.0,
                'CommissionAgent' => null,
                'Storage' => null,
                'ClosePrice' => isset($item['PriceCurrent']) ? (float)$item['PriceCurrent'] : null,
                'Ask' => isset($item['PriceCurrent']) ? (float)$item['PriceCurrent'] : null,
                'Bid' => isset($item['PriceCurrent']) ? (float)$item['PriceCurrent'] : null,
                'Profit' => null,
                'Taxes' => null,
                'Magic' => isset($item['ExpertID']) ? (int)$item['ExpertID'] : null,
                'Comment' => $item['Comment'] ?? null,
            ];
        }
        return $result;
    }

    /**
     * 将 MT5 deals 映射为 CRM orders 入库结构（trading_status=2）
     *
     * @param array $items
     * @return array
     */
    private function mapMt5DealsToOrders(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $id = isset($item['Deal']) ? (int)$item['Deal'] : 0;
            if ($id <= 0) {
                continue;
            }

            $action = isset($item['Action']) ? (int)$item['Action'] : -1;
            if (!$this->isMt5TradeDealAction($action)) {
                continue;
            }
            $entry = isset($item['Entry']) ? (int)$item['Entry'] : -1;
            if (!$this->isMt5ClosingDealEntry($entry)) {
                continue;
            }

            $symbolName = isset($item['Symbol']) ? (string)$item['Symbol'] : '';
            if (trim($symbolName) === '') {
                continue;
            }

            $price = isset($item['Price']) ? (float)$item['Price'] : 0.0;
            $pricePosition = isset($item['PricePosition']) ? (float)$item['PricePosition'] : 0.0;
            $openPrice = $pricePosition > 0 ? $pricePosition : $price;

            $result[] = [
                'Id' => $id,
                'SymbolId' => 0,
                'Symbol' => $symbolName,
                'Source' => $symbolName,
                'Digits' => isset($item['Digits']) ? (int)$item['Digits'] : 0,
                'Cmd' => $action,
                'Volume' => $this->normalizeMt5DealVolumeForOrder($item),
                'OpenTime' => isset($item['Time']) ? (int)$item['Time'] : 0,
                'State' => 2,
                'OrderType' => 'history',
                'TargetPrice' => null,
                'OpenPrice' => $openPrice,
                'Sl' => null,
                'Tp' => null,
                'CloseTime' => isset($item['Time']) ? (int)$item['Time'] : 0,
                'Expiration' => 0,
                'Reason' => isset($item['Reason']) ? (int)$item['Reason'] : null,
                'Commission' => isset($item['Commission']) ? (float)$item['Commission'] : 0.0,
                'CommissionAgent' => null,
                'Storage' => isset($item['Storage']) ? (float)$item['Storage'] : null,
                'ClosePrice' => $price > 0 ? $price : null,
                'Ask' => null,
                'Bid' => null,
                'Profit' => isset($item['Profit']) ? (float)$item['Profit'] : null,
                'Taxes' => null,
                'Magic' => isset($item['ExpertID']) ? (int)$item['ExpertID'] : null,
                'Comment' => $item['Comment'] ?? null,
            ];
        }
        return $result;
    }

    /**
     * 判断 deal action 是否属于交易成交（buy/sell）
     *
     * @param int $action
     * @return bool
     */
    private function isMt5TradeDealAction(int $action): bool
    {
        return $action === 0 || $action === 1;
    }

    /**
     * 仅将平仓类 deal 映射到 CRM history orders，避免开仓和平仓各记一笔。
     */
    private function isMt5ClosingDealEntry(int $entry): bool
    {
        return $entry === 1 || $entry === 2 || $entry === 3;
    }

    /**
     * 统一 MT5 deal volume 到 CRM 内部 volume（lots）口径
     *
     * @param array $item
     * @return float
     */
    private function normalizeMt5DealVolumeForOrder(array $item): float
    {
        if (isset($item['VolumeExt']) && is_numeric($item['VolumeExt'])) {
            return ((float)$item['VolumeExt'] / 100000000.0) * 100.0;
        }
        if (isset($item['Volume']) && is_numeric($item['Volume'])) {
            return ((float)$item['Volume'] / 10000.0) * 100.0;
        }
        return 0.0;
    }

    /**
     * 获取 MT5 所有组别（原始对象转数组）
     *
     * @return array
     * @throws Exception
     */
    public function getGroups(): array
    {
        $this->ensureConnected();

        $total = 0;
        $retCode = $this->api->GroupTotal($total);
        $this->assertMt5Success($retCode, 'GroupTotal');

        $groups = [];
        for ($i = 0; $i < (int)$total; $i++) {
            $group = null;
            $retCode = $this->api->GroupNext($i, $group);
            $this->assertMt5Success($retCode, 'GroupNext');
            $groups[] = $this->objectToArray($group);
        }

        return $groups;
    }

    /**
     * 按组名获取单个 MT5 组配置
     *
     * @param string $groupName
     * @return array
     * @throws Exception
     */
    public function getGroup(string $groupName): array
    {
        $groupName = trim((string)$groupName);
        if ($groupName === '') {
            throw new InvalidArgumentException('getGroup requires non-empty group name');
        }

        $this->ensureConnected();

        $group = null;
        $retCode = $this->api->GroupGet($groupName, $group);
        $this->assertMt5Success($retCode, 'GroupGet');

        return $this->objectToArray($group);
    }

    /**
     * 获取 MT5 所有 Symbols（原始对象转数组）
     *
     * @return array
     * @throws Exception
     */
    public function getSymbols(): array
    {
        $this->ensureConnected();

        $total = 0;
        $retCode = $this->api->SymbolTotal($total);
        $this->assertMt5Success($retCode, 'SymbolTotal');

        $symbols = [];
        for ($i = 0; $i < (int)$total; $i++) {
            $symbol = null;
            $retCode = $this->api->SymbolNext($i, $symbol);
            $this->assertMt5Success($retCode, 'SymbolNext', true);
            $symbols[] = $this->objectToArray($symbol);
        }

        return $symbols;
    }

    /**
     * 修改 MT5 用户密码（主密码 / 投资者密码）
     *
     * @param int|string $login
     * @param string $newPassword
     * @param string $type 支持 main / investor（或直接传 MT5 常量值）
     * @return array
     * @throws Exception
     */
    public function changePassword($login, string $newPassword, string $type = 'main'): array
    {
        $login = (int)$login;
        $newPassword = (string)$newPassword;

        if ($login <= 0) {
            throw new InvalidArgumentException('changePassword requires valid login');
        }

        if (!Password::validateMt5Password($newPassword)) {
            throw new InvalidArgumentException('New password does not meet MT5 policy requirements');
        }

        $passwordType = $this->normalizePasswordType($type);

        $this->ensureConnected();
        $retCode = $this->api->UserPasswordChange($login, $newPassword, $passwordType);
        $this->assertMt5Success($retCode, 'UserPasswordChange');

        return [
            'login' => (string)$login,
            'type' => $passwordType,
            'changed' => true,
        ];
    }

    /**
     * 修改主密码
     *
     * @param int|string $login
     * @param string $newPassword
     * @return array
     * @throws Exception
     */
    public function changeMainPassword($login, string $newPassword): array
    {
        return $this->changePassword($login, $newPassword, 'main');
    }

    /**
     * 修改投资者密码
     *
     * @param int|string $login
     * @param string $newPassword
     * @return array
     * @throws Exception
     */
    public function changeInvestorPassword($login, $newPassword)
    {
        return $this->changePassword($login, $newPassword, 'investor');
    }

    /**
     * 执行回调并自动维护连接生命周期（临时用）
     *
     * @param callable $callback function(MTWebAPI $api)
     * @return mixed
     * @throws Exception
     */
    public function withConnection(callable $callback)
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('Callback must be callable');
        }

        $this->connect();
        try {
            return $callback($this->api);
        } finally {
            $this->disconnect();
        }
    }

    /**
     * 确保 MT5 连接可用；未连接时自动连接
     *
     * @return void
     * @throws Exception
     */
    private function ensureConnected()
    {
        if (!$this->isConnected()) {
            $this->connect();
        }
    }

    /**
     * 校验 MT5 返回码，非成功时抛出异常
     *
     * @param mixed $retCode
     * @param string $action
     * @param bool $allowNoData
     * @return void
     * @throws Exception
     */
    private function assertMt5Success($retCode, $action = 'MT5 API call', $allowNoData = false)
    {
        if ($retCode === null || $retCode === false) {
            throw new Exception($action . ' failed: empty MT5 return code');
        }

        $code = (int)$retCode;

        if ($code === MTRetCode::MT_RET_OK) {
            return;
        }

        if ($allowNoData && $code === MTRetCode::MT_RET_OK_NONE) {
            return;
        }

        $message = method_exists('MTRetCode', 'GetError')
            ? MTRetCode::GetError($code)
            : 'Unknown MT5 error';

        throw new Exception(sprintf('%s failed (code=%d): %s', $action, $code, $message));
    }

    /**
     * @param int|string $type
     * @return int
     */
    private function normalizeDealAction($type): int
    {
        if (is_int($type) || ctype_digit((string)$type)) {
            return (int)$type;
        }

        $value = strtolower(trim((string)$type));
        switch ($value) {
            case 'balance':
            case 'deposit':
                return MTEnDealAction::DEAL_BALANCE;
            case 'credit':
                return MTEnDealAction::DEAL_CREDIT;
            case 'charge':
            case 'fee':
                return MTEnDealAction::DEAL_CHARGE;
            case 'bonus':
                return MTEnDealAction::DEAL_BONUS;
            default:
                throw new InvalidArgumentException('Unsupported MT5 balance type: ' . (string)$type);
        }
    }

    /**
     * @param string $type
     * @return string
     */
    private function normalizePasswordType($type): string
    {
        $value = strtoupper(trim((string)$type));
        if ($value === '') {
            $value = 'MAIN';
        }

        if ($value === 'MAIN' || $value === MTProtocolConsts::WEB_VAL_USER_PASS_MAIN) {
            return MTProtocolConsts::WEB_VAL_USER_PASS_MAIN;
        }

        if ($value === 'INVESTOR' || $value === MTProtocolConsts::WEB_VAL_USER_PASS_INVESTOR || $value === 'INVEST') {
            return MTProtocolConsts::WEB_VAL_USER_PASS_INVESTOR;
        }

        throw new InvalidArgumentException('Unsupported password type: ' . (string)$type);
    }

    /**
     * @param int|string|null $value
     * @param int $default
     * @return int
     */
    private function normalizeUnixTimestamp($value, int $default): int
    {
        if ($value === null || $value === '') {
            return (int)$default;
        }

        if (!is_int($value) && !ctype_digit((string)$value)) {
            throw new InvalidArgumentException('Invalid unix timestamp: ' . (string)$value);
        }

        return (int)$value;
    }

    /**
     * 递归将对象结构转换为数组
     *
     * @param mixed $value
     * @return mixed
     */
    private function objectToArray($value)
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) {
                $result[$k] = $this->objectToArray($v);
            }
            return $result;
        }

        if (is_object($value)) {
            return $this->objectToArray(get_object_vars($value));
        }

        return $value;
    }

    /**
     * 延迟加载 MT5 官方 SDK 文件
     *
     * @return void
     * @throws Exception
     */
    private function loadMt5Library()
    {
        if (class_exists('MTWebAPI', false)) {
            return;
        }

        $mt5Dir = realpath(__DIR__ . '/../mt5_api');
        if ($mt5Dir === false) {
            throw new Exception('MT5 library directory not found: mt5_api');
        }

        $previousIncludePath = get_include_path();
        set_include_path($mt5Dir . PATH_SEPARATOR . $previousIncludePath);
        try {
            require_once $mt5Dir . '/mt5_api.php';
        } finally {
            set_include_path($previousIncludePath);
        }
    }

}
