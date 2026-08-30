<?php
/**
 * MT4 API Client
 *
 * 调用 MT4 WebApi Gateway 的 HTTP 接口。
 * 所有请求带 HMAC 签名（X-Timestamp / X-Nonce / X-Signature），
 * 写接口（POST /users、deposit、withdraw）body 必须带 idempotency_key。
 *
 * 对外方法名称、入参、出参刻意对齐 Mt5ApiClient，方便 controller / service 按平台分发时
 * 直接复用现有 mt5 的调用形态（createUser / getBalance / deposit / withdraw /
 * getAccountOrdersBalanceSnapshot / getGroups / getGroup / getSymbols / testConnection）。
 */

require_once __DIR__ . '/Logger.php';

class Mt4ApiClient
{
    /** open_orders 中属于"持仓"的 cmd（其余视为挂单） */
    private const POSITION_CMDS = [0, 1];

    private $config;
    private string $baseUrl;
    private string $secret;
    private int $connectTimeout;
    private int $timeout;

    public function __construct(array $config = [])
    {
        if (empty($config)) {
            $appConfig = require __DIR__ . '/../config/app.php';
            $config = $appConfig['integrations']['mt4'] ?? [];
        }

        $this->config = $config;
        $this->baseUrl = rtrim((string)($config['base_url'] ?? ''), '/');
        $this->secret = (string)($config['hmac_secret'] ?? '');
        $this->connectTimeout = (int)($config['connect_timeout'] ?? 10);
        $this->timeout = (int)($config['timeout'] ?? 30);
    }

    /**
     * Gateway 是 HTTP 服务，没有长连接；保留方法只是为了和 Mt5ApiClient 调用形态保持一致，
     * 让分发代码 `} finally { $client->disconnect(); }` 不用判断平台。
     */
    public function disconnect(): void
    {
    }

    /**
     * 探活：GET /health
     */
    public function testConnection(): array
    {
        try {
            $health = $this->request('GET', '/health');
            return [
                'success' => true,
                'message' => 'MT4 gateway OK',
                'health'  => $health,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    // ====================================================================
    // 账户
    // ====================================================================

    /**
     * 创建 MT4 用户（交易账户）— POST /mt4/users
     *
     * @param array $data 支持字段：
     *   - idempotency_key (可选；未传时自动生成)
     *   - group (required)
     *   - name (required)
     *   - mainPassword / password (required；优先用 mainPassword 以对齐 Mt5ApiClient 入参)
     *   - leverage (required；未传走 config default_leverage)
     *   - email / phone / country / city / state / zipCode / address / comment
     *   - agentAccount / enable / enableReadOnly / leadSource
     * @return array 与 Mt5ApiClient::createUser 输出形状对齐：
     *   ['login' => string, 'mainPassword' => string, 'investPassword' => '', 'user' => array]
     */
    public function createUser(array $data): array
    {
        $group = (string)($data['group'] ?? '');
        if (trim($group) === '') {
            throw new InvalidArgumentException('MT4 createUser requires group');
        }
        $name = (string)($data['name'] ?? '');
        if (trim($name) === '') {
            throw new InvalidArgumentException('MT4 createUser requires name');
        }
        $mainPassword = (string)($data['mainPassword'] ?? $data['password'] ?? '');
        if ($mainPassword === '') {
            throw new InvalidArgumentException('MT4 createUser requires mainPassword');
        }
        $leverage = isset($data['leverage']) && $data['leverage'] !== ''
            ? (int)$data['leverage']
            : (int)($this->config['default_leverage'] ?? 100);

        $body = [
            'idempotency_key' => $this->ensureIdempotencyKey($data['idempotency_key'] ?? null, 'mt4_create_'),
            'group'           => $group,
            'name'            => $name,
            'password'        => $mainPassword,
            'leverage'        => $leverage,
        ];

        // 可选字段：传了再发，避免覆盖 MT4 默认值（与文档 5.1 一致）
        $optional = [
            'email'            => 'email',
            'phone'            => 'phone',
            'country'          => 'country',
            'city'             => 'city',
            'state'            => 'state',
            'zipCode'          => 'zip_code',
            'address'          => 'address',
            'comment'          => 'comment',
            'agentAccount'     => 'agent_account',
            'enable'           => 'enable',
            'enableReadOnly'   => 'enable_read_only',
            'leadSource'       => 'lead_source',
        ];
        foreach ($optional as $inKey => $outKey) {
            if (array_key_exists($inKey, $data) && $data[$inKey] !== null && $data[$inKey] !== '') {
                $body[$outKey] = $data[$inKey];
            }
        }

        $resp = $this->request('POST', '/mt4/users', null, $body);

        return [
            'login'          => isset($resp['login']) ? (string)$resp['login'] : '',
            'mainPassword'   => $mainPassword,
            'investPassword' => '',
            'user'           => $resp,
        ];
    }

    /**
     * 查账户 — GET /mt4/users/{login}
     */
    public function getUser($login): array
    {
        $login = $this->normalizeLogin($login);
        return $this->request('GET', '/mt4/users/' . $login);
    }

    // ====================================================================
    // 余额
    // ====================================================================

    /**
     * 查余额 — GET /mt4/users/{login}/balance
     *
     * 返回字段对齐 Mt5ApiClient::getBalance 的输出形状：
     *   login / balance / credit / equity / margin / marginFree / marginLevel / account（原始）
     */
    public function getBalance($login): array
    {
        $login = $this->normalizeLogin($login);
        $raw = $this->request('GET', '/mt4/users/' . $login . '/balance');

        return [
            'login'         => isset($raw['login']) ? (string)$raw['login'] : (string)$login,
            'balance'       => $this->toFloat($raw['balance'] ?? null),
            'credit'        => $this->toFloat($raw['credit'] ?? null),
            'equity'        => $this->toFloat($raw['equity'] ?? null),
            'profit'        => 0.0, // MT4 MarginLevel 不直接给 profit，留 0 由 equity - balance 旁路计算（与 mt5 形态保持一致字段名）
            'margin'        => $this->toFloat($raw['margin'] ?? null),
            'marginFree'    => $this->toFloat($raw['margin_free'] ?? null),
            'marginLevel'   => $this->toFloat($raw['margin_level'] ?? null),
            'marginLeverage'=> isset($raw['leverage']) ? (int)$raw['leverage'] : 0,
            'currencyDigits'=> 0,
            'account'       => $raw,
        ];
    }

    /**
     * 入金 — POST /mt4/users/{login}/deposit
     *
     * idempotencyKey 必须由调用方传入（建议传 CRM transactionId），否则按时间生成兜底值，
     * 但兜底值不能保证重放幂等，不要用在真正的资金接口里。
     */
    public function deposit($login, $amount, string $comment = '', ?string $idempotencyKey = null): array
    {
        return $this->changeBalance($login, $amount, $comment, false, $idempotencyKey);
    }

    /**
     * 出金 — POST /mt4/users/{login}/withdraw
     *
     * 对外仍传**正数** amount，gateway 内部负责加负号（与 Mt5ApiClient::withdraw 行为一致）。
     */
    public function withdraw($login, $amount, string $comment = '', ?string $idempotencyKey = null): array
    {
        return $this->changeBalance($login, $amount, $comment, true, $idempotencyKey);
    }

    private function changeBalance($login, $amount, string $comment, bool $isWithdraw, ?string $idempotencyKey): array
    {
        $login = $this->normalizeLogin($login);
        $amount = (float)$amount;
        if ($amount <= 0) {
            throw new InvalidArgumentException(($isWithdraw ? 'withdraw' : 'deposit') . ' amount must be greater than 0');
        }

        // 不再单独发 comment：MT4 comment 字段上限 31 字符，而网关会把 idempotency_key 拼进去，
        // 长 comment 会触发 RET_INVALID_DATA。orderid 本身已经在 idempotency_key 里（CRM transactionId 派生），
        // 网关把 "idem:<key>" 写进 MT4 comment 就够追溯了，$comment 入参在 MT4 这里忽略。
        $body = [
            'idempotency_key' => $this->ensureIdempotencyKey($idempotencyKey, $isWithdraw ? 'mt4_wd_' : 'mt4_dep_'),
            // 文档 1.2：金额永远用字符串，避免浮点精度丢失
            'amount'          => $this->formatAmount($amount),
        ];

        $endpoint = '/mt4/users/' . $login . '/' . ($isWithdraw ? 'withdraw' : 'deposit');
        $resp = $this->request('POST', $endpoint, null, $body);

        return [
            'login'   => isset($resp['login']) ? (string)$resp['login'] : (string)$login,
            'ticket'  => isset($resp['mt4_ticket']) ? (string)$resp['mt4_ticket'] : '',
            'amount'  => $isWithdraw ? -1 * $amount : $amount,
            'type'    => $isWithdraw ? 'charge' : 'balance',
            'comment' => $comment,
            'balance' => [
                'balance' => $this->toFloat($resp['balance_after'] ?? null),
            ],
            'raw'     => $resp,
        ];
    }

    // ====================================================================
    // Credit（赠金）/ 密码 / 资料（对齐 gateway 写接口，API.md 6.12–6.14）
    // ====================================================================

    /**
     * 增加赠金（credit in）— POST /mt4/users/{login}/credit
     * amount 传正数；底层 TradeTransaction(Cmd=Credit) 只影响 Credit、不动 Balance。
     * expiration：credit 到期日（unix 秒），不传由 gateway 用远期默认。
     */
    public function creditIn($login, $amount, string $comment = '', ?string $idempotencyKey = null, ?int $expiration = null): array
    {
        return $this->changeCredit($login, $amount, false, $comment, $idempotencyKey, $expiration);
    }

    /**
     * 扣减赠金（credit out）— POST /mt4/users/{login}/credit
     * 对外仍传**正数** amount，内部转负；Credit 余额不足时 gateway 报错。
     */
    public function creditOut($login, $amount, string $comment = '', ?string $idempotencyKey = null, ?int $expiration = null): array
    {
        return $this->changeCredit($login, $amount, true, $comment, $idempotencyKey, $expiration);
    }

    private function changeCredit($login, $amount, bool $isOut, string $comment, ?string $idempotencyKey, ?int $expiration): array
    {
        $login = $this->normalizeLogin($login);
        $amount = (float)$amount;
        if ($amount <= 0) {
            throw new InvalidArgumentException('credit amount must be greater than 0');
        }
        $signed = $isOut ? -1 * $amount : $amount;

        $body = [
            'idempotency_key' => $this->ensureIdempotencyKey($idempotencyKey, $isOut ? 'mt4_crd_out_' : 'mt4_crd_in_'),
            // 文档 1.2：金额用字符串防浮点；credit 带符号（正=增、负=减）
            'amount'          => $this->formatAmount($signed),
        ];
        // expiration 可选：不传由 gateway 用远期默认
        if ($expiration !== null && $expiration > 0) {
            $body['expiration'] = (int)$expiration;
        }

        $resp = $this->request('POST', '/mt4/users/' . $login . '/credit', null, $body);

        return [
            'login'       => isset($resp['login']) ? (string)$resp['login'] : (string)$login,
            'ticket'      => isset($resp['mt4_ticket']) ? (string)$resp['mt4_ticket'] : '',
            'amount'      => $signed,
            'creditAfter' => $this->toFloat($resp['credit_after'] ?? null),
            'expiration'  => isset($resp['expiration']) ? (int)$resp['expiration'] : null,
            'comment'     => $comment,
            'raw'         => $resp,
        ];
    }

    /**
     * 修改密码 — POST /mt4/users/{login}/password
     * type: 'trade'（默认，交易密码）/ 'investor'（只读密码）。
     * password 字段名固定为 password，request() 日志里由 maskSensitive 脱敏。
     */
    public function changePassword($login, string $newPassword, string $type = 'trade', ?string $idempotencyKey = null): array
    {
        $login = $this->normalizeLogin($login);
        if ($newPassword === '') {
            throw new InvalidArgumentException('changePassword requires a non-empty password');
        }
        $type = $this->normalizePasswordType($type);

        $body = [
            'idempotency_key' => $this->ensureIdempotencyKey($idempotencyKey, 'mt4_pwd_'),
            'password'        => $newPassword,
            'type'            => $type,
        ];

        $resp = $this->request('POST', '/mt4/users/' . $login . '/password', null, $body);

        return [
            'login'   => isset($resp['login']) ? (string)$resp['login'] : (string)$login,
            'type'    => isset($resp['type']) ? (string)$resp['type'] : $type,
            'changed' => true,
            'raw'     => $resp,
        ];
    }

    // 密码类型归一：trade/main/master → trade；investor/invest/readonly → investor
    private function normalizePasswordType(string $type): string
    {
        $v = strtolower(trim($type));
        if ($v === '' || $v === 'trade' || $v === 'main' || $v === 'master') {
            return 'trade';
        }
        if ($v === 'investor' || $v === 'invest' || $v === 'readonly') {
            return 'investor';
        }
        throw new InvalidArgumentException('Unsupported MT4 password type: ' . $type);
    }

    /**
     * 改账户（杠杆 / 基础资料 / 切 group）— POST /mt4/users/{login}/update
     * 局部更新：只发 $patch 里出现的字段，gateway read-modify-write，未传的不动。
     * 字符串字段传 '' = 清空该字段（group 例外，不接受空串）；至少要带一个可改字段。
     *
     * $patch（camelCase → gateway snake_case）：group, leverage, name, email, phone,
     * country, city, state, zipCode, address, comment, idNumber, statusText, leadSource,
     * agentAccount, enable, enableReadOnly, enableChangePassword, enableOtp, sendReports
     */
    public function updateUser($login, array $patch = [], ?string $idempotencyKey = null): array
    {
        $login = $this->normalizeLogin($login);

        $map = [
            'group'                => 'group',
            'leverage'             => 'leverage',
            'name'                 => 'name',
            'email'                => 'email',
            'phone'                => 'phone',
            'country'              => 'country',
            'city'                 => 'city',
            'state'                => 'state',
            'zipCode'              => 'zip_code',
            'address'              => 'address',
            'comment'              => 'comment',
            'idNumber'             => 'id',
            'statusText'           => 'status',
            'leadSource'           => 'lead_source',
            'agentAccount'         => 'agent_account',
            'enable'               => 'enable',
            'enableReadOnly'       => 'enable_read_only',
            'enableChangePassword' => 'enable_change_password',
            'enableOtp'            => 'enable_otp',
            'sendReports'          => 'send_reports',
        ];
        $intFields = ['leverage', 'agent_account', 'enable', 'enable_read_only', 'enable_change_password', 'enable_otp', 'send_reports'];

        $body = [];
        foreach ($map as $inKey => $outKey) {
            if (!array_key_exists($inKey, $patch)) {
                continue;
            }
            $value = $patch[$inKey];
            // group 不接受空串（契约约定），避免误把账户清成空组
            if ($outKey === 'group' && (string)$value === '') {
                throw new InvalidArgumentException('MT4 updateUser group cannot be empty');
            }
            $body[$outKey] = in_array($outKey, $intFields, true) ? (int)$value : (string)$value;
        }

        if (empty($body)) {
            throw new InvalidArgumentException('MT4 updateUser requires at least one field to change');
        }

        $body['idempotency_key'] = $this->ensureIdempotencyKey($idempotencyKey, 'mt4_upd_');

        $resp = $this->request('POST', '/mt4/users/' . $login . '/update', null, $body);

        return [
            'login'   => isset($resp['login']) ? (string)$resp['login'] : (string)$login,
            'updated' => true,
            'user'    => $resp,
            'raw'     => $resp,
        ];
    }

    // ====================================================================
    // Snapshot + 订单
    // ====================================================================

    /**
     * MT4 统一账户快照（余额 + 订单）— GET /mt4/users/{login}/snapshot
     *
     * 返回结构刻意对齐 Mt5ApiClient::getAccountOrdersBalanceSnapshot：
     *   ['login', 'balance', 'credit', 'positions', 'pendingOrders', 'historyOrders']
     *
     * snapshot 必带 from/to 窗口；客户端把窗口裁到 ≤30 天（冷启动控量 + 规避 MT4 time_t 溢出），
     * includeHistory=false 时也走一次小窗口，把 closed_orders 丢弃即可。
     */
    public function getAccountOrdersBalanceSnapshot($login, array $options = []): array
    {
        $login = $this->normalizeLogin($login);

        $includeBalance = array_key_exists('includeBalance', $options) ? (bool)$options['includeBalance'] : true;
        $includePositions = array_key_exists('includePositions', $options) ? (bool)$options['includePositions'] : true;
        $includePending = array_key_exists('includePending', $options) ? (bool)$options['includePending'] : true;
        $includeHistory = array_key_exists('includeHistory', $options) ? (bool)$options['includeHistory'] : false;

        $to = isset($options['to']) && $options['to'] !== null && $options['to'] !== ''
            ? (int)$options['to']
            : time();
        $defaultFrom = $to - 30 * 86400;
        $from = isset($options['from']) && $options['from'] !== null && $options['from'] !== ''
            ? (int)$options['from']
            : $defaultFrom;
        // gateway 限制窗口 ≤ 30 天，超出就裁到 30 天
        if ($to - $from > 30 * 86400) {
            $from = $to - 30 * 86400;
        }

        $raw = $this->request('GET', '/mt4/users/' . $login . '/snapshot', [
            'from' => (string)$from,
            'to'   => (string)$to,
        ]);

        return $this->mapSnapshotBody($raw, (string)$login, $includeBalance, $includePositions, $includePending, $includeHistory);
    }

    /**
     * POST /mt4/users/snapshots — 批量快照（文档 6.7，单次 ≤500 login）。
     * 每个返回元素的内部结构跟单接口一致，逐个套用同一套映射。
     *
     * @param int[]|string[] $logins 账号列表
     * @param int $from 历史单起始（unix 秒）
     * @param int $to   历史单结束（unix 秒）；与 from 跨度超过 30 天会被裁到 30 天
     * @return array 按 login(string) 索引：成功项结构同 getAccountOrdersBalanceSnapshot；
     *               该 login 在网关侧出错时为 ['login'=>..., 'error'=>'USER_NOT_FOUND', ...空数据]
     */
    public function getAccountOrdersBalanceSnapshots(array $logins, $from, $to): array
    {
        $normalized = [];
        foreach ($logins as $login) {
            $normalized[] = (int)$this->normalizeLogin($login);
        }
        if (empty($normalized)) {
            return [];
        }
        if (count($normalized) > 500) {
            throw new InvalidArgumentException('getAccountOrdersBalanceSnapshots supports at most 500 logins per call');
        }

        $to = (int)$to;
        $from = (int)$from;
        if ($to - $from > 30 * 86400) {
            $from = $to - 30 * 86400;
        }

        $resp = $this->request('POST', '/mt4/users/snapshots', null, [
            'logins' => $normalized,
            'from'   => $from,
            'to'     => $to,
        ]);

        $snapshots = isset($resp['snapshots']) && is_array($resp['snapshots']) ? $resp['snapshots'] : [];
        $result = [];
        foreach ($snapshots as $item) {
            if (!is_array($item) || !isset($item['login'])) {
                continue;
            }
            $login = (string)$item['login'];

            // 单个 login 出错：网关返回 {login, error}，整批仍 200。原样透传给上层处理。
            if (isset($item['error'])) {
                $result[$login] = [
                    'login'         => $login,
                    'error'         => (string)$item['error'],
                    'balance'       => null,
                    'credit'        => null,
                    'positions'     => [],
                    'pendingOrders' => [],
                    'historyOrders' => [],
                ];
                continue;
            }

            $mapped = $this->mapSnapshotBody($item, $login, true, true, true, true);
            // user 块（group/leverage/name 等）透传给上层做资料同步
            if (isset($item['user']) && is_array($item['user'])) {
                $mapped['user'] = $item['user'];
            }
            $result[$login] = $mapped;
        }

        return $result;
    }

    /**
     * GET /mt4/users/{login}/credits?from=&to= — 该账号 credit 流水（赠金发放/撤回）。
     * 返回 credits 数组（每条字段同 closed_orders，cmd="credit"）；窗口上限 30 天，与 snapshot 一致。
     */
    public function getCredits($login, $from, $to): array
    {
        $login = $this->normalizeLogin($login);
        $to = (int)$to;
        $from = (int)$from;
        if ($to - $from > 30 * 86400) {
            $from = $to - 30 * 86400;
        }

        $resp = $this->request('GET', '/mt4/users/' . $login . '/credits', [
            'from' => (string)$from,
            'to'   => (string)$to,
        ]);

        return isset($resp['credits']) && is_array($resp['credits']) ? $resp['credits'] : [];
    }

    /**
     * 把单个快照 body（margin/open_orders/closed_orders）映射成 CRM 用结构。
     * 单接口和批量接口的每个元素共用这套，保证字段口径一致。
     */
    private function mapSnapshotBody(array $raw, string $login, bool $includeBalance, bool $includePositions, bool $includePending, bool $includeHistory): array
    {
        $snapshot = [
            'login'         => $login,
            'balance'       => null,
            'credit'        => null,
            'positions'     => [],
            'pendingOrders' => [],
            'historyOrders' => [],
        ];

        if ($includeBalance) {
            $margin = isset($raw['margin']) && is_array($raw['margin']) ? $raw['margin'] : [];
            $snapshot['balance'] = isset($margin['balance']) ? (float)$margin['balance'] : null;
            $snapshot['credit'] = isset($margin['credit']) && $margin['credit'] !== '' ? (float)$margin['credit'] : null;
        }

        $openOrders = isset($raw['open_orders']) && is_array($raw['open_orders']) ? $raw['open_orders'] : [];
        [$positions, $pending] = $this->splitOpenOrders($openOrders);

        if ($includePositions) {
            $snapshot['positions'] = $positions;
        }
        if ($includePending) {
            $snapshot['pendingOrders'] = $pending;
        }
        if ($includeHistory) {
            $closed = isset($raw['closed_orders']) && is_array($raw['closed_orders']) ? $raw['closed_orders'] : [];
            $snapshot['historyOrders'] = $this->mapClosedOrders($closed);
        }

        return $snapshot;
    }

    /**
     * 把 open_orders 按 cmd 拆成 positions (cmd 0/1) 和 pending (cmd 2-5)。
     * 输出结构对齐 Mt5ApiClient 的 normalize 结果，便于 orderModel batchUpsert 直接吃。
     */
    private function splitOpenOrders(array $openOrders): array
    {
        $positions = [];
        $pending = [];

        foreach ($openOrders as $item) {
            $cmdInt = $this->cmdToInt($item['cmd'] ?? null);
            if ($cmdInt === null) {
                continue;
            }

            $base = $this->mapOpenOrderToCrm($item, $cmdInt);
            if (in_array($cmdInt, self::POSITION_CMDS, true)) {
                $base['State'] = 1;
                $base['OrderType'] = 'position';
                $base['TargetPrice'] = null;
                $positions[] = $base;
            } else {
                $base['State'] = 0;
                $base['OrderType'] = 'pending';
                $base['TargetPrice'] = isset($item['open_price']) ? (float)$item['open_price'] : null;
                $pending[] = $base;
            }
        }

        return [$positions, $pending];
    }

    private function mapOpenOrderToCrm(array $item, int $cmdInt): array
    {
        $symbolName = isset($item['symbol']) ? (string)$item['symbol'] : '';
        $orderId = isset($item['order']) ? (int)$item['order'] : 0;

        return [
            'Id'              => $orderId,
            'SymbolId'        => 0,
            'Symbol'          => $symbolName !== '' ? $symbolName : null,
            'Source'          => $symbolName !== '' ? $symbolName : null,
            'Digits'          => 0,
            'Cmd'             => $cmdInt,
            // MT4 volume 已经是 lot×100（与 CRM orders.volume 口径一致），直接透传
            'Volume'          => isset($item['volume']) ? (float)$item['volume'] : 0.0,
            'OpenTime'        => isset($item['open_time']) ? (int)$item['open_time'] : 0,
            'OpenPrice'       => isset($item['open_price']) ? (float)$item['open_price'] : 0.0,
            'Sl'              => isset($item['sl']) ? (float)$item['sl'] : null,
            'Tp'              => isset($item['tp']) ? (float)$item['tp'] : null,
            'CloseTime'       => 0,
            'Expiration'      => isset($item['expiration']) ? (int)$item['expiration'] : 0,
            'Reason'          => null,
            'Commission'      => isset($item['commission']) ? (float)$item['commission'] : 0.0,
            'CommissionAgent' => isset($item['commission_agent']) ? (float)$item['commission_agent'] : null,
            'Storage'         => isset($item['storage']) ? (float)$item['storage'] : null,
            'ClosePrice'      => null,
            'Ask'             => null,
            'Bid'             => null,
            'Profit'          => isset($item['profit']) ? (float)$item['profit'] : null,
            'Taxes'           => isset($item['taxes']) ? (float)$item['taxes'] : null,
            'Magic'           => isset($item['magic']) ? (int)$item['magic'] : null,
            'Comment'         => $item['comment'] ?? null,
        ];
    }

    private function mapClosedOrders(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $cmdInt = $this->cmdToInt($item['cmd'] ?? null);
            // 跳过 balance/credit 之外的非交易类型也无所谓——后端按 cmd int 入库即可
            if ($cmdInt === null) {
                continue;
            }
            $orderId = isset($item['order']) ? (int)$item['order'] : 0;
            if ($orderId <= 0) {
                continue;
            }
            $symbolName = isset($item['symbol']) ? (string)$item['symbol'] : '';

            $result[] = [
                'Id'              => $orderId,
                'SymbolId'        => 0,
                'Symbol'          => $symbolName !== '' ? $symbolName : null,
                'Source'          => $symbolName !== '' ? $symbolName : null,
                'Digits'          => 0,
                'Cmd'             => $cmdInt,
                'Volume'          => isset($item['volume']) ? (float)$item['volume'] : 0.0,
                'OpenTime'        => isset($item['open_time']) ? (int)$item['open_time'] : 0,
                'State'           => 2,
                'OrderType'       => 'history',
                'TargetPrice'     => null,
                'OpenPrice'       => isset($item['open_price']) ? (float)$item['open_price'] : 0.0,
                'Sl'              => null,
                'Tp'              => null,
                'CloseTime'       => isset($item['close_time']) ? (int)$item['close_time'] : 0,
                'Expiration'      => 0,
                'Reason'          => null,
                'Commission'      => isset($item['commission']) ? (float)$item['commission'] : 0.0,
                'CommissionAgent' => isset($item['commission_agent']) ? (float)$item['commission_agent'] : null,
                'Storage'         => isset($item['storage']) ? (float)$item['storage'] : null,
                'ClosePrice'      => isset($item['close_price']) ? (float)$item['close_price'] : null,
                'Ask'             => null,
                'Bid'             => null,
                'Profit'          => isset($item['profit']) ? (float)$item['profit'] : null,
                'Taxes'           => isset($item['taxes']) ? (float)$item['taxes'] : null,
                'Magic'           => isset($item['magic']) ? (int)$item['magic'] : null,
                'Comment'         => $item['comment'] ?? null,
            ];
        }
        return $result;
    }

    private function cmdToInt($cmd): ?int
    {
        // v0.2 网关 cmd 已是整数（旧版字符串枚举已废弃），直接取整；非数字视为缺失跳过
        if ($cmd === null || $cmd === '' || !is_numeric($cmd)) {
            return null;
        }
        return (int)$cmd;
    }

    // ====================================================================
    // Group / Symbol
    // ====================================================================

    /**
     * GET /mt4/groups — 返回 groups 数组（去掉外层 envelope）
     */
    public function getGroups(): array
    {
        $resp = $this->request('GET', '/mt4/groups');
        return isset($resp['groups']) && is_array($resp['groups']) ? $resp['groups'] : [];
    }

    /**
     * GET /mt4/groups/{name} — name 含反斜杠需要 URL-encode（文档 6.10）
     */
    public function getGroup(string $groupName): array
    {
        $groupName = trim($groupName);
        if ($groupName === '') {
            throw new InvalidArgumentException('getGroup requires non-empty group name');
        }
        return $this->request('GET', '/mt4/groups/' . rawurlencode($groupName));
    }

    /**
     * GET /mt4/symbols
     */
    public function getSymbols(): array
    {
        $resp = $this->request('GET', '/mt4/symbols');
        return isset($resp['symbols']) && is_array($resp['symbols']) ? $resp['symbols'] : [];
    }

    // ====================================================================
    // HTTP + 签名
    // ====================================================================

    /**
     * 统一请求入口，处理签名、错误信封。
     *
     * @param string $method GET / POST / PATCH ...
     * @param string $path path（已替换路径参数，例如 /mt4/users/12345/deposit）
     * @param array|null $query GET query 参数（值会被强转字符串，按 key 字典序排序进签名）
     * @param array|null $body  POST body（数组）；为 null 表示无 body
     * @return array
     * @throws Exception
     */
    private function request(string $method, string $path, ?array $query = null, ?array $body = null): array
    {
        if ($this->baseUrl === '') {
            throw new RuntimeException('MT4 gateway base_url is not configured');
        }
        if ($this->secret === '') {
            throw new RuntimeException('MT4 gateway hmac_secret is not configured');
        }

        $method = strtoupper($method);
        $bodyBytes = $body === null ? '' : (string)json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $queryString = $this->buildSortedQuery($query);
        $url = $this->baseUrl . $path . ($queryString !== '' ? ('?' . $queryString) : '');

        $ts = (string)time();
        $nonce = $this->generateUuidV4();
        $signature = $this->sign($method, $path, $queryString, $ts, $nonce, $bodyBytes);

        $headers = [
            'X-Timestamp: ' . $ts,
            'X-Nonce: ' . $nonce,
            'X-Signature: ' . $signature,
        ];
        if ($bodyBytes !== '') {
            $headers[] = 'Content-Type: application/json';
        }
        $headers[] = 'Accept: application/json';

        // 联调期记录发出的请求（body 里的 password 脱敏，文档约定 password 永不回显）
        Logger::info('MT4 gateway request', [
            'method'  => $method,
            'url'     => $url,
            'headers' => $headers,
            'body'    => $this->maskSensitive($bodyBytes),
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($bodyBytes !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyBytes);
        }

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $err = curl_error($ch);
            curl_close($ch);
            Logger::error('MT4 gateway request failed', [
                'method' => $method,
                'url'    => $url,
                'error'  => $err,
            ]);
            throw new Exception('MT4 gateway request failed: ' . $err);
        }
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 联调期记录返回值
        Logger::info('MT4 gateway response', [
            'method'    => $method,
            'url'       => $url,
            'http_code' => $httpCode,
            'body'      => (string)$responseBody,
        ]);

        // 空 body 但 2xx：兜底返回空数组（理论上 gateway 不会这么干）
        if ($responseBody === '' && $httpCode >= 200 && $httpCode < 300) {
            return [];
        }

        $decoded = json_decode((string)$responseBody, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(sprintf('MT4 gateway returned invalid JSON (HTTP %d): %s', $httpCode, json_last_error_msg()));
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $code = is_array($decoded) ? (string)($decoded['code'] ?? '') : '';
            $message = is_array($decoded) ? (string)($decoded['message'] ?? '') : '';
            $requestId = is_array($decoded) ? (string)($decoded['request_id'] ?? '') : '';
            $extras = is_array($decoded) && isset($decoded['extras']) ? $decoded['extras'] : null;

            Logger::error('MT4 gateway error', [
                'method'     => $method,
                'path'       => $path,
                'http_code'  => $httpCode,
                'code'       => $code,
                'message'    => $message,
                'request_id' => $requestId,
                'extras'     => $extras,
            ]);

            $errText = sprintf(
                'MT4 gateway HTTP %d %s: %s (request_id=%s)',
                $httpCode,
                $code !== '' ? $code : 'ERROR',
                $message !== '' ? $message : 'unknown',
                $requestId
            );
            throw new Exception($errText);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 拼 signing_string 并产出 base64(HMAC-SHA256)。
     * 严格按文档 2.2，每段一个 \n，无 query 时第 3 段就是空字符串但分隔符仍保留。
     */
    private function sign(string $method, string $path, string $sortedQuery, string $ts, string $nonce, string $bodyBytes): string
    {
        $bodyHash = hash('sha256', $bodyBytes);
        $signingString = $method . "\n" . $path . "\n" . $sortedQuery . "\n" . $ts . "\n" . $nonce . "\n" . $bodyHash;
        return base64_encode(hash_hmac('sha256', $signingString, $this->secret, true));
    }

    /**
     * query 按 key 字典序排序后用 & 拼接；不做任何 URL-encode（与文档 2.2 一致）。
     */
    private function buildSortedQuery(?array $query): string
    {
        if (empty($query)) {
            return '';
        }
        $copy = [];
        foreach ($query as $k => $v) {
            $copy[(string)$k] = $v === null ? '' : (string)$v;
        }
        ksort($copy, SORT_STRING);
        $pairs = [];
        foreach ($copy as $k => $v) {
            $pairs[] = $k . '=' . $v;
        }
        return implode('&', $pairs);
    }

    private function generateUuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * idempotency_key：调用方传了优先用（≤24 字符），否则按时间 + 随机串兜底。
     * 注意：兜底值不能跨进程重放保证一致，不要用在真正资金接口里。
     */
    private function ensureIdempotencyKey(?string $key, string $fallbackPrefix): string
    {
        if ($key !== null && trim($key) !== '') {
            return substr(trim($key), 0, 24);
        }
        $candidate = $fallbackPrefix . bin2hex(random_bytes(6));
        return substr($candidate, 0, 24);
    }

    private function normalizeLogin($login): string
    {
        $login = (string)$login;
        if (!ctype_digit($login) || (int)$login <= 0) {
            throw new InvalidArgumentException('MT4 login must be a positive integer');
        }
        return $login;
    }

    private function formatAmount(float $amount): string
    {
        // 文档 1.2 要求 amount 用 string 防浮点精度；统一两位小数即可（MT4 货币精度由 group 决定，
        // 但 gateway 端会校验/规范化）
        return number_format($amount, 2, '.', '');
    }

    private function toFloat($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        return (float)$value;
    }

    /**
     * 日志脱敏：把 body 里的 password 类字段替换成 ***，其它原样返回。
     */
    private function maskSensitive(string $bodyBytes): string
    {
        if ($bodyBytes === '') {
            return '';
        }
        $decoded = json_decode($bodyBytes, true);
        if (!is_array($decoded)) {
            return $bodyBytes;
        }
        foreach (['password', 'password_investor', 'password_phone'] as $key) {
            if (array_key_exists($key, $decoded)) {
                $decoded[$key] = '***';
            }
        }
        return (string)json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
