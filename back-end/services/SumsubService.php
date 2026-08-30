<?php
/**
 * Sumsub API Service
 *
 * 负责调用 Sumsub 第三方 KYC 接口。
 * 鉴权方式：
 *   X-App-Token:      App Token
 *   X-App-Access-Ts:  Unix 秒级时间戳
 *   X-App-Access-Sig: HMAC-SHA256(secretKey, ts + method + path + body)
 *
 * 参考文档：https://docs.sumsub.com/reference/about-app-tokens
 */

require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../models/ExternalKycGateway.php';

class SumsubService
{
    const PROVIDER_KEY = 'sumsub';
    const DEFAULT_BASE_URL = 'https://api.sumsub.com';

    private $baseUrl;
    private $appToken;
    private $secretKey;
    private $webhookSecret;
    private $connectTimeout = 10;
    private $timeout = 30;

    /**
     * 构造时直接传入 gateway 配置（包含 secret 的版本），
     * 让 controller 决定加载哪一行配置，service 不再重复查库。
     */
    public function __construct(array $gateway)
    {
        if (empty($gateway['isEnabled'])) {
            throw new Exception('Sumsub gateway is not enabled');
        }

        $this->baseUrl = rtrim($gateway['baseUrl'] ?: self::DEFAULT_BASE_URL, '/');
        $this->appToken = $gateway['appToken'] ?? '';
        $this->secretKey = $gateway['secretKey'] ?? '';
        $this->webhookSecret = $gateway['webhookSecret'] ?? '';

        if ($this->appToken === '' || $this->secretKey === '') {
            throw new Exception('Sumsub appToken or secretKey is not configured');
        }
    }

    /**
     * 校验 Sumsub webhook 签名。
     * 固定用 HMAC-SHA256（Sumsub 后台 webhook 配置默认就是 HMAC_SHA256_HEX）。
     *
     * @param string $rawBody    完整请求体（必须是原始未解析的）
     * @param string $signature  X-Payload-Digest 头的值
     * @return bool
     */
    public function verifyWebhookSignature($rawBody, $signature)
    {
        if (empty($this->webhookSecret)) {
            // 没配 secret 一律不放行
            return false;
        }
        if (!is_string($signature) || $signature === '') {
            return false;
        }
        $expected = hash_hmac('sha256', (string)$rawBody, $this->webhookSecret);
        return hash_equals($expected, strtolower($signature));
    }

    /**
     * 拉取 Sumsub 平台上配置的所有 applicant levels
     *
     * Sumsub 接口：GET {baseUrl}/resources/applicants/-/levels
     * 返回结构：
     *   { "list": { "items": [ { "id": "...", "name": "...", ... }, ... ], "totalItems": N } }
     *
     * @return array Sumsub 返回的 level 原始数组
     */
    public function fetchLevels()
    {
        $response = $this->request('GET', '/resources/applicants/-/levels');

        // Sumsub 这个接口分两种返回结构（分页 / 直接列表），都兼容一下
        if (isset($response['list']['items']) && is_array($response['list']['items'])) {
            return $response['list']['items'];
        }
        if (isset($response['items']) && is_array($response['items'])) {
            return $response['items'];
        }
        if (is_array($response) && array_values($response) === $response) {
            return $response;
        }

        return [];
    }

    /**
     * 生成 WebSDK 用的短期 access token。
     *
     * Sumsub 接口：POST /resources/accessTokens/sdk
     * Body:
     *   {
     *     "userId":      "<uuid4>",                    // 我们生成的、每个 submission 唯一
     *     "levelName":   "<externalLevelName>",
     *     "ttlInSecs":   600,
     *     "applicantIdentifiers": {                    // 可选，传过来 Sumsub 那边会附在 applicant 上
     *       "email": "...",
     *       "phone": "..."
     *     }
     *   }
     *
     * applicantId 不在这个接口返回里 —— 第一条 webhook (applicantCreated / applicantPending) 会带过来。
     *
     * @param string      $userId    我们生成的 uuid4（同时存到 clientKycSubmissions.providerExternalUserId）
     * @param string      $levelName Sumsub level 名
     * @param string|null $email     可选，applicant 邮箱
     * @param string|null $phone     可选，applicant 手机号
     * @param int         $ttlInSecs token 有效期（秒）
     * @return string|null           token
     */
    public function generateAccessToken($userId, $levelName, $email = null, $phone = null, $ttlInSecs = 600)
    {
        $payload = [
            'userId'    => $userId,
            'levelName' => $levelName,
            'ttlInSecs' => (int)$ttlInSecs,
        ];
        $identifiers = [];
        if (!empty($email)) {
            $identifiers['email'] = $email;
        }
        if (!empty($phone)) {
            $identifiers['phone'] = $phone;
        }
        if (!empty($identifiers)) {
            $payload['applicantIdentifiers'] = $identifiers;
        }

        $response = $this->request('POST', '/resources/accessTokens/sdk', $payload);
        return $response['token'] ?? null;
    }

    /**
     * 通用请求（GET/POST/PUT/PATCH/DELETE），自动加签
     *
     * 签名规则与官方 Postman 默认 pre-request script 完全一致：
     *   ts       = floor(now / 1000)
     *   method   = uppercase
     *   path     = "/<resource>?query"（path + query string，必须以 / 开头）
     *   body     = raw body string，或空串
     *   sig      = HMAC-SHA256(secretKey, ts + method + path + body) → hex
     */
    private function request($method, $path, $payload = null)
    {
        $method = strtoupper($method);
        $body = '';

        if ($method === 'GET' && is_array($payload) && !empty($payload)) {
            $sep = strpos($path, '?') === false ? '?' : '&';
            $path .= $sep . http_build_query($payload);
        } elseif (in_array($method, ['POST', 'PUT', 'PATCH'], true) && $payload !== null) {
            $body = is_string($payload) ? $payload : json_encode($payload);
        }

        // path 必须以 / 开头，否则签名串和服务端算出来的不一致
        $path = '/' . ltrim($path, '/');

        $ts = (string)time();
        $stringToSign = $ts . $method . $path . $body;
        $signature = hash_hmac('sha256', $stringToSign, $this->secretKey);

        // Sumsub 官方 Postman 脚本无条件设置以下 4 个 header，照搬保证一致
        $headers = [
            'X-App-Token: ' . $this->appToken,
            'X-App-Access-Ts: ' . $ts,
            'X-App-Access-Sig: ' . $signature,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . $path,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($rawResponse === false) {
            Logger::error('Sumsub request failed', ['path' => $path, 'err' => $curlErr]);
            throw new Exception('Sumsub request failed: ' . $curlErr);
        }

        $decoded = json_decode($rawResponse, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            Logger::error('Sumsub returned non-2xx', [
                'path' => $path,
                'status' => $httpCode,
                'body' => $rawResponse,
            ]);
            $msg = is_array($decoded) && isset($decoded['description'])
                ? $decoded['description']
                : 'HTTP ' . $httpCode;
            throw new Exception('Sumsub error: ' . $msg);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
