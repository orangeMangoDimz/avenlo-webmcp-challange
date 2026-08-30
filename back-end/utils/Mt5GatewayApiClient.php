<?php
/**
 * MT5 Gateway API Client（只读）
 *
 * 调用 MT5 WebApi Gateway 的 HTTP 读接口（API.md v0.2，路径前缀 /mt5）。
 * MT5 网关全部只读：没有建户 / 出入金通道，那些写操作仍走 utils/Mt5ApiClient.php（官方 WebAPI 二进制）。
 *
 * 设计目标：对外方法名 / 出参刻意对齐二进制版 Mt5ApiClient（getAccountOrdersBalanceSnapshot /
 * getCredits / getGroups / getSymbols / getUser / getBalance / disconnect），让 sync / controller
 * 把 MT5 读操作从二进制版换成本类时，下游映射代码不用改。
 *
 * 与 Mt4ApiClient 的差异：
 * - 路径前缀 /mt5；cmd / state 是整数（不是字符串枚举）。
 * - volume 是 VolumeExt（1e-8 手），订单换算 ÷1e8×100（和现有 MT5 口径一致，与 MT4 的 lot×100 透传不同）。
 * - 快照窗口不限跨度（MT5 无 30 天上限）。
 */

require_once __DIR__ . '/Logger.php';

class Mt5GatewayApiClient
{
    /** open_orders 中属于"持仓"的 cmd（其余视为挂单：限价 2-5 / MT5 的 8-10） */
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
            $config = $appConfig['integrations']['mt5'] ?? [];
        }

        $this->config = $config;
        $this->baseUrl = rtrim((string)($config['base_url'] ?? ''), '/');
        $this->secret = (string)($config['hmac_secret'] ?? '');
        $this->connectTimeout = (int)($config['connect_timeout'] ?? 10);
        // 用独立 key：integrations.mt5.timeout 是二进制 WebAPI 的连接超时(10s)，HTTP 网关读历史窗口可能更久
        $this->timeout = (int)($config['gateway_timeout'] ?? 60);
    }

    /**
     * 网关是 HTTP 服务没有长连接；保留 disconnect 只为和二进制版 Mt5ApiClient 调用形态一致，
     * 让 `finally { $client->disconnect(); }` 这类分发代码不用判断平台。
     */
    public function disconnect(): void
    {
    }

    /**
     * 探活：GET /mt5/health（免签名）
     */
    public function testConnection(): array
    {
        try {
            $health = $this->request('GET', '/mt5/health');
            return [
                'success' => true,
                'message' => 'MT5 gateway OK',
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
    // 账户 / 余额
    // ====================================================================

    /**
     * 查账户 — GET /mt5/users/{login}
     *
     * 网关返回 snake_case，这里映射回 PascalCase 与二进制版 getUser 对齐，方便资料展示 / 同步代码复用。
     * 注意：网关只返 group/name/email/status/leverage/enable/balance/credit，
     * 没有 country/phone/comment 等字段（响应里直接不输出），上层用 `?? null` 兜底，不要据此覆盖本地已有资料。
     */
    public function getUser($login): array
    {
        $login = $this->normalizeLogin($login);
        $raw = $this->request('GET', '/mt5/users/' . $login);

        return [
            'Login'    => isset($raw['login']) ? (string)$raw['login'] : (string)$login,
            'Group'    => $raw['group'] ?? null,
            'Name'     => $raw['name'] ?? null,
            'Email'    => $raw['email'] ?? null,
            'Status'   => $raw['status'] ?? null,
            'Leverage' => isset($raw['leverage']) ? (int)$raw['leverage'] : null,
            'Enable'   => isset($raw['enable']) ? (int)$raw['enable'] : null,
            'Balance'  => isset($raw['balance']) ? (float)$raw['balance'] : null,
            'Credit'   => isset($raw['credit']) ? (float)$raw['credit'] : null,
        ];
    }

    /**
     * 查余额 — GET /mt5/users/{login}/balance
     *
     * 出参对齐二进制版 getBalance（小写键），便于 buildMt5BalanceSummary 等消费方直接复用。
     * 网关契约没有 margin_free / profit：marginFree 按文档建议用 equity - margin 估算，profit 留 0。
     */
    public function getBalance($login): array
    {
        $login = $this->normalizeLogin($login);
        $raw = $this->request('GET', '/mt5/users/' . $login . '/balance');

        $equity = $this->toFloat($raw['equity'] ?? null);
        $margin = $this->toFloat($raw['margin'] ?? null);

        return [
            'login'          => isset($raw['login']) ? (string)$raw['login'] : (string)$login,
            'balance'        => $this->toFloat($raw['balance'] ?? null),
            'credit'         => $this->toFloat($raw['credit'] ?? null),
            'equity'         => $equity,
            'profit'         => 0.0,
            'margin'         => $margin,
            'marginFree'     => $equity - $margin,
            'marginLevel'    => $this->toFloat($raw['margin_level'] ?? null),
            'marginLeverage' => isset($raw['leverage']) ? (int)$raw['leverage'] : 0,
            'currencyDigits' => 0,
            'account'        => $raw,
        ];
    }

    // ====================================================================
    // Snapshot + 订单
    // ====================================================================

    /**
     * MT5 统一账户快照（余额 + 持仓 + 挂单 + 区间内已平仓单）— GET /mt5/users/{login}/snapshot
     *
     * 出参对齐二进制版：['login','balance','credit','positions','pendingOrders','historyOrders']。
     * 网关要求必带 from/to（>0 且 to>from），但 from/to 只作用于 closed_orders；
     * 持仓/挂单永远返当前全量。即使只读持仓（includeHistory=false）也得给个合法窗口，默认回看 30 天。
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
        $from = isset($options['from']) && $options['from'] !== null && $options['from'] !== ''
            ? (int)$options['from']
            : $to - 30 * 86400;

        $raw = $this->request('GET', '/mt5/users/' . $login . '/snapshot', [
            'from' => (string)$from,
            'to'   => (string)$to,
        ]);

        return $this->mapSnapshotBody($raw, (string)$login, $includeBalance, $includePositions, $includePending, $includeHistory);
    }

    /**
     * POST /mt5/users/snapshots — 批量快照（单次 ≤500 login），用法同 MT4 网关批量接口。
     * 每个返回元素的内部结构跟单接口一致，逐个套用同一套映射。
     * MT5 网关窗口不限跨度，from/to 不做 30 天裁剪（与单账户 snapshot 口径一致）。
     *
     * @param int[]|string[] $logins 账号列表
     * @param int $from 历史单起始（unix 秒）
     * @param int $to   历史单结束（unix 秒）
     * @return array 按 login(string) 索引：成功项结构同 getAccountOrdersBalanceSnapshot，外加 user 块（group/leverage/name 等）；
     *               该 login 在网关侧出错时为 ['login'=>..., 'error'=>..., ...空数据]
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

        $resp = $this->request('POST', '/mt5/users/snapshots', null, [
            'logins' => $normalized,
            'from'   => (int)$from,
            'to'     => (int)$to,
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
     * GET /mt5/users/{login}/credits?from=&to= — 该账号赠金流水（cmd 固定 7，金额在 profit）。
     * 返回 credits 数组（snake_case 原样透传，字段同 closed_orders），供 OrderSyncService 写 trading_credit_deals。
     * MT5 网关窗口不限跨度，这里不裁剪。
     */
    public function getCredits($login, $from, $to): array
    {
        $login = $this->normalizeLogin($login);
        $resp = $this->request('GET', '/mt5/users/' . $login . '/credits', [
            'from' => (string)(int)$from,
            'to'   => (string)(int)$to,
        ]);

        return isset($resp['credits']) && is_array($resp['credits']) ? $resp['credits'] : [];
    }

    /**
     * 把单个快照 body（margin/open_orders/closed_orders）映射成 CRM 用结构。
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
            // MT5 网关的 margin 块带 credit（和 MT4 不同），余额 / 赠金一次拿全
            $margin = isset($raw['margin']) && is_array($raw['margin']) ? $raw['margin'] : [];
            $snapshot['balance'] = isset($margin['balance']) ? (float)$margin['balance'] : null;
            $snapshot['credit'] = isset($margin['credit']) ? (float)$margin['credit'] : null;
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
     * 把 open_orders 按 cmd 拆成 positions（cmd 0/1）和 pending（限价 2-5 / MT5 的 8-10）。
     * 出参结构对齐二进制版的 normalize 结果，便于 orderModel batchUpsert 直接吃。
     */
    private function splitOpenOrders(array $openOrders): array
    {
        $positions = [];
        $pending = [];

        foreach ($openOrders as $item) {
            if (!is_array($item)) {
                continue;
            }
            $cmdInt = isset($item['cmd']) ? (int)$item['cmd'] : null;
            if ($cmdInt === null) {
                continue;
            }

            $base = $this->mapOrderToCrm($item, $cmdInt);
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

    private function mapClosedOrders(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $orderId = isset($item['order']) ? (int)$item['order'] : 0;
            if ($orderId <= 0) {
                continue;
            }
            $cmdInt = isset($item['cmd']) ? (int)$item['cmd'] : 0;

            $base = $this->mapOrderToCrm($item, $cmdInt);
            $base['State'] = 2;
            $base['OrderType'] = 'history';
            $base['TargetPrice'] = null;
            $base['CloseTime'] = isset($item['close_time']) ? (int)$item['close_time'] : 0;
            $base['ClosePrice'] = isset($item['close_price']) ? (float)$item['close_price'] : null;
            $result[] = $base;
        }
        return $result;
    }

    /**
     * 网关订单对象（snake_case，整数 cmd）→ CRM orders 入库结构。
     * v0.2 已精简掉 sl/tp/storage/magic 等字段，响应里没有的统一留 null。
     */
    private function mapOrderToCrm(array $item, int $cmdInt): array
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
            // MT5 volume 是 VolumeExt（1e-8 手），÷1e8×100 转 CRM 内部 volume（lot×100），与现有 MT5 webapi 口径一致
            'Volume'          => $this->normalizeVolume($item['volume'] ?? null),
            'OpenTime'        => isset($item['open_time']) ? (int)$item['open_time'] : 0,
            'OpenPrice'       => isset($item['open_price']) ? (float)$item['open_price'] : 0.0,
            'Sl'              => null,
            'Tp'              => null,
            'CloseTime'       => isset($item['close_time']) ? (int)$item['close_time'] : 0,
            'Expiration'      => 0,
            'Reason'          => null,
            'Commission'      => isset($item['commission']) ? (float)$item['commission'] : 0.0,
            'CommissionAgent' => null,
            'Storage'         => null,
            'ClosePrice'      => isset($item['close_price']) ? (float)$item['close_price'] : null,
            'Ask'             => null,
            'Bid'             => null,
            'Profit'          => isset($item['profit']) ? (float)$item['profit'] : null,
            'Taxes'           => null,
            'Magic'           => null,
            'Comment'         => null,
        ];
    }

    /**
     * VolumeExt（1e-8 手）→ CRM 内部 volume（lot×100）。和现有 MT5 webapi 的 normalizeMt5VolumeForOrder 同口径。
     */
    private function normalizeVolume($volume): float
    {
        if ($volume === null || $volume === '' || !is_numeric($volume)) {
            return 0.0;
        }
        return ((float)$volume / 100000000.0) * 100.0;
    }

    // ====================================================================
    // Group / Symbol（出参映射回二进制版的 PascalCase，复用既有 syncMt5Groups / syncMt5ProductsToCustomTables）
    // ====================================================================

    /**
     * GET /mt5/groups — 返回 groups 数组。
     * 网关 snake_case 映射回二进制版 getGroups 的 PascalCase 键，让 TradingGroup::syncMt5Groups 不用改。
     * 网关没有的字段（AuthPasswordMin / MarginSOMode / CompanyCatalog 等）直接不映射，syncMt5Groups 会按缺省处理。
     */
    public function getGroups(): array
    {
        $resp = $this->request('GET', '/mt5/groups');
        $groups = isset($resp['groups']) && is_array($resp['groups']) ? $resp['groups'] : [];

        return array_map([$this, 'mapGroupToPascal'], array_filter($groups, 'is_array'));
    }

    private function mapGroupToPascal(array $g): array
    {
        return [
            'Group'              => $g['name'] ?? '',
            'Currency'           => $g['currency'] ?? null,
            'DemoLeverage'       => $g['default_leverage'] ?? null,
            'DemoDeposit'        => $g['demo_deposit'] ?? null,
            'MarginCall'         => $g['margin_call'] ?? null,
            'MarginStopOut'      => $g['margin_stopout'] ?? null,
            'TradeInterestrate'  => $g['interest_rate'] ?? null,
            'Company'            => $g['company'] ?? null,
            'CompanySupportPage' => $g['support_page'] ?? null,
        ];
    }

    /**
     * GET /mt5/symbols — 返回 symbols 数组。
     * 网关 snake_case 映射回二进制版 getSymbols 的 PascalCase 键，让 IbProductSyncService::syncMt5ProductsToCustomTables 不用改。
     * volume_min/max/step 是 VolumeExt（1e-8 手），映射到 *Ext 键，normalizeMt5Lots 会按 ÷1e8 还原成手数。
     */
    public function getSymbols(): array
    {
        $resp = $this->request('GET', '/mt5/symbols');
        $symbols = isset($resp['symbols']) && is_array($resp['symbols']) ? $resp['symbols'] : [];

        return array_map([$this, 'mapSymbolToPascal'], array_filter($symbols, 'is_array'));
    }

    private function mapSymbolToPascal(array $s): array
    {
        return [
            'Symbol'         => $s['name'] ?? '',
            'Path'           => $s['path'] ?? null,
            'Description'    => $s['description'] ?? null,
            'Digits'         => $s['digits'] ?? null,
            'CurrencyBase'   => $s['currency_base'] ?? null,
            'CurrencyProfit' => $s['currency_profit'] ?? null,
            'CurrencyMargin' => $s['currency_margin'] ?? null,
            'VolumeMinExt'   => $s['volume_min'] ?? null,
            'VolumeMaxExt'   => $s['volume_max'] ?? null,
            'VolumeStepExt'  => $s['volume_step'] ?? null,
            'ContractSize'   => $s['contract_size'] ?? null,
            'MarginInitial'  => $s['margin_initial'] ?? null,
            'TickSize'       => $s['tick_size'] ?? null,
            'TickValue'      => $s['tick_value'] ?? null,
            'Spread'         => $s['spread'] ?? null,
            'StopsLevel'     => $s['stops_level'] ?? null,
        ];
    }

    /**
     * POST /mt5/symbols/prices — 批量查交易对中间价（(bid+ask)/2，仅 MT5）。
     *
     * @param string[] $symbols 交易对名，1~500 个（大小写不敏感，name 按同步库实际名回显）
     * @return array 列表，元素为 ['name'=>..., 'price'=>...] 或 ['name'=>..., 'error'=>...]
     */
    public function getSymbolPrices(array $symbols): array
    {
        $normalized = [];
        foreach ($symbols as $s) {
            $s = trim((string)$s);
            if ($s !== '') {
                $normalized[] = $s;
            }
        }
        if (empty($normalized)) {
            return [];
        }
        if (count($normalized) > 500) {
            throw new InvalidArgumentException('getSymbolPrices supports at most 500 symbols per call');
        }

        $resp = $this->request('POST', '/mt5/symbols/prices', null, [
            'symbols' => array_values($normalized),
        ]);

        $prices = isset($resp['prices']) && is_array($resp['prices']) ? $resp['prices'] : [];
        return array_map([$this, 'mapSymbolPrice'], array_filter($prices, 'is_array'));
    }

    private function mapSymbolPrice(array $p): array
    {
        $name = isset($p['name']) ? (string)$p['name'] : '';
        if (isset($p['error'])) {
            return ['name' => $name, 'error' => (string)$p['error']];
        }
        return ['name' => $name, 'price' => isset($p['price']) ? (string)$p['price'] : null];
    }

    // ====================================================================
    // HTTP + 签名（与 Mt4ApiClient 同一套 HMAC 方案，仅路径换 /mt5 前缀）
    // ====================================================================

    /**
     * 统一请求入口，处理签名、错误信封。
     *
     * @param string $method GET / POST ...
     * @param string $path   含 /mt5 前缀的 path（已替换路径参数）
     * @param array|null $query GET query（值强转字符串，按 key 字典序排序进签名）
     * @param array|null $body  POST body；为 null 表示无 body
     * @throws Exception
     */
    private function request(string $method, string $path, ?array $query = null, ?array $body = null): array
    {
        if ($this->baseUrl === '') {
            throw new RuntimeException('MT5 gateway base_url is not configured');
        }
        if ($this->secret === '') {
            throw new RuntimeException('MT5 gateway hmac_secret is not configured');
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

        Logger::info('MT5 gateway request', [
            'method'  => $method,
            'url'     => $url,
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
            Logger::error('MT5 gateway request failed', [
                'method' => $method,
                'url'    => $url,
                'error'  => $err,
            ]);
            throw new Exception('MT5 gateway request failed: ' . $err);
        }
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseBody === '' && $httpCode >= 200 && $httpCode < 300) {
            return [];
        }

        $decoded = json_decode((string)$responseBody, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(sprintf('MT5 gateway returned invalid JSON (HTTP %d): %s', $httpCode, json_last_error_msg()));
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $code = is_array($decoded) ? (string)($decoded['code'] ?? '') : '';
            $message = is_array($decoded) ? (string)($decoded['message'] ?? '') : '';
            $requestId = is_array($decoded) ? (string)($decoded['request_id'] ?? '') : '';

            Logger::error('MT5 gateway error', [
                'method'     => $method,
                'path'       => $path,
                'http_code'  => $httpCode,
                'code'       => $code,
                'message'    => $message,
                'request_id' => $requestId,
            ]);

            throw new Exception(sprintf(
                'MT5 gateway HTTP %d %s: %s (request_id=%s)',
                $httpCode,
                $code !== '' ? $code : 'ERROR',
                $message !== '' ? $message : 'unknown',
                $requestId
            ));
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 拼 signing_string 并产出 base64(HMAC-SHA256)。
     * 严格按文档 2.2：每段一个 \n，无 query 时第 3 段是空字符串但分隔符仍保留。
     */
    private function sign(string $method, string $path, string $sortedQuery, string $ts, string $nonce, string $bodyBytes): string
    {
        $bodyHash = hash('sha256', $bodyBytes);
        $signingString = $method . "\n" . $path . "\n" . $sortedQuery . "\n" . $ts . "\n" . $nonce . "\n" . $bodyHash;
        return base64_encode(hash_hmac('sha256', $signingString, $this->secret, true));
    }

    /**
     * query 按 key 字典序排序后用 & 拼接；不做 URL-encode（与文档 2.2 一致）。
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

    private function normalizeLogin($login): string
    {
        $login = (string)$login;
        // MT5 login 可能很大（int64），只校验是正整数字符串，不做 int 截断
        if (!ctype_digit($login) || $login === '0') {
            throw new InvalidArgumentException('MT5 login must be a positive integer');
        }
        return $login;
    }

    private function toFloat($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        return (float)$value;
    }
}
