<?php
/**
 * Coinsbuy API Service
 * 提供调用 Coinsbuy 第三方接口的方法（auth token / deposit / payout）
 *
 * 凭证 / 配置都来自 paymentGatewaySettings：
 *   - appId       => client_id
 *   - secretKey   => client_secret
 *   - configData  => { wallet_d, wallet_w, currency, url }
 *
 * Coinsbuy 接口文档: https://coinsbuy.com/api
 */

require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../models/PaymentGatewaySetting.php';

class CoinsbuyService
{
    const GATEWAY_KEY = 'coinsbuy';

    /**
     * Process 内 token 缓存：避免同一次 PHP 请求里重复换 token
     * key 用 client_id，防止多份配置串味
     * value: ['token' => string, 'expires_at' => int(unix ts)]
     */
    private static $tokenCache = [];

    private $clientId;
    private $clientSecret;
    private $baseUrl;
    private $walletDeposit;
    private $walletWithdraw;
    private $currencyDeposit;
    private $currencyWithdraw;
    private $inaccuracy;
    private $connectTimeout = 10;
    private $timeout = 30;

    public function __construct()
    {
        $gatewayModel = new PaymentGatewaySetting();
        $gateway = $gatewayModel->findByKeyWithSecrets(self::GATEWAY_KEY);

        if (!$gateway || empty($gateway['isEnabled'])) {
            throw new Exception('Coinsbuy gateway is not configured or not enabled');
        }

        $this->clientId = (string)($gateway['appId'] ?? '');
        $this->clientSecret = (string)($gateway['secretKey'] ?? '');

        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new Exception('Coinsbuy client_id or client_secret is not configured');
        }

        $config = $this->decodeConfigData($gateway['configData'] ?? null);

        $this->baseUrl = rtrim((string)($config['url'] ?? ''), '/');
        $this->walletDeposit = (string)($config['wallet_d'] ?? '');
        $this->walletWithdraw = (string)($config['wallet_w'] ?? '');
        $this->currencyDeposit = (string)($config['currency_d'] ?? '');
        $this->currencyWithdraw = (string)($config['currency_w'] ?? '');
        // inaccuracy = 允许的支付金额下浮比例（Coinsbuy 字段），由前端 config 配置；空则不传，让 Coinsbuy 用默认 0
        $this->inaccuracy = isset($config['inaccuracy']) ? (string)$config['inaccuracy'] : '';

        if ($this->baseUrl === '') {
            throw new Exception('Coinsbuy configData.url is not configured');
        }
    }

    public function getDepositWalletId()
    {
        return $this->walletDeposit;
    }

    public function getWithdrawWalletId()
    {
        return $this->walletWithdraw;
    }

    public function getCurrencyDepositId()
    {
        return $this->currencyDeposit;
    }

    public function getCurrencyWithdrawId()
    {
        return $this->currencyWithdraw;
    }

    /**
     * 创建 deposit
     *
     * @param array $params
     *   - tracking_id (string, required)  CRM 侧 transactionId
     *   - label (string, required)        Coinsbuy 后台展示名
     *   - callback_url (string, required) 后端 callback 地址
     *   - confirmations_needed (int, optional, default 2)
     *   - payment_page_redirect_url (string, optional)
     *   - payment_page_button_text (string, optional)
     *   - target_amount_requested (string|number, optional) 期望客户支付的目标币种金额（已含汇率换算）
     * @return array decoded JSON response
     */
    public function createDeposit(array $params)
    {
        $attributes = [
            'label' => (string)($params['label'] ?? ''),
            'tracking_id' => (string)($params['tracking_id'] ?? ''),
            'confirmations_needed' => isset($params['confirmations_needed']) ? (int)$params['confirmations_needed'] : 2,
            'callback_url' => (string)($params['callback_url'] ?? ''),
        ];

        if (isset($params['target_amount_requested']) && $params['target_amount_requested'] !== '' && $params['target_amount_requested'] !== null) {
            $attributes['target_amount_requested'] = (string)$params['target_amount_requested'];
        }

        // inaccuracy 是 gateway 级别配置（非每笔单独传），来自 paymentGatewaySetting.configData.inaccuracy
        if ($this->inaccuracy !== '') {
            $attributes['inaccuracy'] = $this->inaccuracy;
        }

        if (!empty($params['payment_page_redirect_url'])) {
            $attributes['payment_page_redirect_url'] = (string)$params['payment_page_redirect_url'];
        }
        if (!empty($params['payment_page_button_text'])) {
            $attributes['payment_page_button_text'] = (string)$params['payment_page_button_text'];
        }

        $body = [
            'data' => [
                'type' => 'deposit',
                'attributes' => $attributes,
                'relationships' => [
                    'wallet' => [
                        'data' => [
                            'type' => 'wallet',
                            'id' => $this->walletDeposit,
                        ],
                    ],
                    'currency' => [
                        'data' => [
                            'type' => 'currency',
                            'id' => $this->currencyDeposit,
                        ],
                    ],
                ],
            ],
        ];

        return $this->request('POST', '/deposit/', $body);
    }

    /**
     * 创建 payout（出金）
     *
     * Coinsbuy payout 强制要求 Idempotency-Key 请求头（缺则 HTTP 428 / 1003，重复则 409 / 1004）
     * 直接用 tracking_id (= 本地 transactionId, 全局唯一) 当 idempotency key
     *
     * 流程：先调 /payout/calculate/ 拿当前链上 gas 的 medium 档报价（low/medium/high 三档由 Coinsbuy 估算），
     * 把 medium 作为 fee_amount 写进 payout 请求；is_fee_included=true 表示 amount 已含手续费，
     * Coinsbuy 会从中扣除 fee_amount 给链上矿工，剩余打给客户地址
     *
     * @param array $params
     *   - tracking_id (string, required)
     *   - label (string, required)
     *   - amount (string|number, required)        出金金额（含手续费）
     *   - address (string, required)              客户的链上接收地址
     *   - callback_url (string, required)
     *   - travel_rule_info (array, optional)      Coinsbuy 反洗钱信息（natural person）
     * @return array decoded JSON response
     */
    public function createPayout(array $params)
    {
        $trackingId = (string)($params['tracking_id'] ?? '');
        $amount = (string)($params['amount'] ?? '');
        $address = (string)($params['address'] ?? '');

        // 先拿一次 fee 报价（medium 档），后续作为 fee_amount 塞到 payout 请求里
        $mediumFee = $this->calculatePayoutMediumFee($amount, $address);

        $attributes = [
            'label' => (string)($params['label'] ?? ''),
            'amount' => $amount,
            'address' => $address,
            'tracking_id' => $trackingId,
            'callback_url' => (string)($params['callback_url'] ?? ''),
            'is_fee_included' => false,
            'fee_amount' => $mediumFee,
        ];

        if (!empty($params['travel_rule_info']) && is_array($params['travel_rule_info'])) {
            $attributes['travel_rule_info'] = $params['travel_rule_info'];
        }

        $body = [
            'data' => [
                'type' => 'payout',
                'attributes' => $attributes,
                'relationships' => [
                    'wallet' => [
                        'data' => [
                            'type' => 'wallet',
                            'id' => $this->walletWithdraw,
                        ],
                    ],
                    'currency' => [
                        'data' => [
                            'type' => 'currency',
                            'id' => $this->currencyWithdraw,
                        ],
                    ],
                ],
            ],
        ];

        $extraHeaders = [];
        if ($trackingId !== '') {
            // Coinsbuy 强制 Idempotency-Key 必须是合法 UUID v4 格式；本地 trackingId（TXN-YYYYMMDD-XXXXXX）不符
            // 用 namespace + trackingId 做 SHA-256 哈希再按 v4 规范填位 → 同一个 trackingId 永远映射到同一 UUID，
            // 重试时仍走 Coinsbuy 的幂等去重，避免重复出金
            $extraHeaders[] = 'Idempotency-Key: ' . $this->trackingIdToUuid4($trackingId);
        }

        return $this->request('POST', '/payout/', $body, $extraHeaders);
    }

    /**
     * 调 /payout/calculate/ 拿链上手续费报价，返回 medium 档（用作 createPayout 的 fee_amount）
     * Coinsbuy 会返回 low/medium/high 三档由 fee 估算器给的结果，medium 是常用平衡选择
     */
    private function calculatePayoutMediumFee(string $amount, string $toAddress): string
    {
        if ($amount === '' || $toAddress === '') {
            throw new Exception('Coinsbuy /payout/calculate requires non-empty amount and to_address');
        }

        $body = [
            'data' => [
                'type' => 'payout-calculation',
                'attributes' => [
                    'amount' => $amount,
                    'to_address' => $toAddress,
                ],
                'relationships' => [
                    'wallet' => [
                        'data' => [
                            'type' => 'wallet',
                            'id' => $this->walletWithdraw,
                        ],
                    ],
                    'currency' => [
                        'data' => [
                            'type' => 'currency',
                            'id' => $this->currencyWithdraw,
                        ],
                    ],
                ],
            ],
        ];

        $decoded = $this->request('POST', '/payout/calculate/', $body);

        $medium = $decoded['data']['attributes']['fee']['medium'] ?? null;
        if ($medium === null || $medium === '') {
            throw new Exception('Coinsbuy /payout/calculate did not return fee.medium');
        }
        return (string)$medium;
    }

    /**
     * 把本地 transactionId（如 TXN-20260507-W840389）确定性映射成合法 UUID v4
     * Coinsbuy 强制 Idempotency-Key 必须是 UUID v4 格式；用 SHA-256 哈希保证：
     *   - 同一 trackingId → 同一 UUID（幂等性）
     *   - 不同 trackingId → 几乎不可能碰撞（128 bit 空间）
     *   - 输出格式合法 v4（version nibble=4，variant bits=10）
     */
    private function trackingIdToUuid4(string $trackingId): string
    {
        // namespace 用一个项目固定字符串，避免不同业务/环境之间偶然撞到同一 UUID
        $hash = hash('sha256', 'utrada-coinsbuy-payout:' . $trackingId, true);
        $bytes = substr($hash, 0, 16);
        // RFC 4122：byte[6] 高 4 位 = 4（版本），byte[8] 高 2 位 = 10（变体）
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /**
     * 获取 access token，命中缓存就直接返回
     * Coinsbuy token expires_in 一般几百秒，提前 30s 失效避免临界过期
     */
    private function getAccessToken()
    {
        $now = time();
        $cacheKey = $this->clientId;

        if (isset(self::$tokenCache[$cacheKey])) {
            $cached = self::$tokenCache[$cacheKey];
            if (!empty($cached['token']) && (int)$cached['expires_at'] > $now + 30) {
                return $cached['token'];
            }
        }

        $body = [
            'data' => [
                'type' => 'auth-token',
                'attributes' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
            ],
        ];

        $decoded = $this->httpJsonRequest('POST', '/token/', $body, null);

        $token = (string)($decoded['data']['attributes']['access'] ?? '');
        $expiresIn = (int)($decoded['data']['attributes']['expires_in'] ?? 0);

        if ($token === '') {
            throw new Exception('Coinsbuy auth-token response missing access token');
        }

        self::$tokenCache[$cacheKey] = [
            'token' => $token,
            'expires_at' => $now + ($expiresIn > 0 ? $expiresIn : 300),
        ];

        return $token;
    }

    /**
     * 通用业务请求：自动带上 bearer token
     * @param array $extraHeaders 额外请求头（如 payout 用的 Idempotency-Key）
     */
    private function request($method, $endpoint, $body = null, array $extraHeaders = [])
    {
        $token = $this->getAccessToken();
        return $this->httpJsonRequest($method, $endpoint, $body, $token, $extraHeaders);
    }

    /**
     * 底层 HTTP 调用
     * Coinsbuy 用的是 jsonapi 风格：Content-Type: application/vnd.api+json
     */
    private function httpJsonRequest($method, $endpoint, $body, $bearerToken, array $extraHeaders = [])
    {
        $url = $this->baseUrl . $endpoint;
        $bodyString = $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $headers = [
            'Content-Type: application/vnd.api+json',
            'Accept: application/vnd.api+json',
        ];
        if ($bearerToken !== null && $bearerToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $bearerToken;
        }
        foreach ($extraHeaders as $h) {
            if (is_string($h) && $h !== '') {
                $headers[] = $h;
            }
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($bodyString !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyString);
        }

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            Logger::error('Coinsbuy curl failed', ['endpoint' => $endpoint, 'error' => $error]);
            throw new Exception('Coinsbuy API request failed: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($responseBody, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            Logger::error('Coinsbuy invalid JSON response', [
                'endpoint' => $endpoint,
                'httpCode' => $httpCode,
                'body' => $responseBody,
            ]);
            throw new Exception('Coinsbuy API responded with invalid JSON: ' . json_last_error_msg());
        }

        if ($httpCode >= 400) {
            // jsonapi 错误格式：{"errors":[{"detail":"...","title":"..."}]}
            $errMsg = 'Unknown error';
            if (!empty($decoded['errors'][0]['detail'])) {
                $errMsg = (string)$decoded['errors'][0]['detail'];
            } elseif (!empty($decoded['errors'][0]['title'])) {
                $errMsg = (string)$decoded['errors'][0]['title'];
            }
            Logger::error('Coinsbuy API HTTP error', [
                'endpoint' => $endpoint,
                'httpCode' => $httpCode,
                'response' => $decoded,
            ]);
            throw new Exception(sprintf('Coinsbuy API HTTP %d: %s', $httpCode, $errMsg));
        }

        return $decoded ?: [];
    }

    private function decodeConfigData($raw)
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }
}
