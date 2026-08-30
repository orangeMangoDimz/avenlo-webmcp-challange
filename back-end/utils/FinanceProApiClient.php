<?php
/**
 * FinancePro API Client
 * 提供调用 FinancePro 第三方接口的通用方法
 */

require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../models/FinanceProToken.php';

class FinanceProApiClient
{
    private $baseUrl;
    private $defaultHeaders;
    private $connectTimeout;
    private $timeout;
    private $appId;
    private $appSecret;
    private $platform;
    private $creditInEndpoint;
    private $creditOutEndpoint;
    private $resetPasswordEndpoint;

    public function __construct(array $config = [])
    {
        if (empty($config)) {
            $appConfig = require __DIR__ . '/../config/app.php';
            $config = $appConfig['integrations']['finance_pro'] ?? [];
        }

        $baseUrl = $config['base_url'] ?? 'https://gateway.finance-pro.com.au';
        $this->baseUrl = rtrim($baseUrl, '/');

        $headers = $config['headers'] ?? [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
        $this->defaultHeaders = $this->normalizeHeaders($headers);

        $this->connectTimeout = (int)($config['connect_timeout'] ?? 10);
        $this->timeout = (int)($config['timeout'] ?? 30);

        $this->appId = $config['app_id'] ?? 'wmsapp';
        $this->appSecret = $config['app_secret'] ?? 'B8548D08B8BA023677939E3AE8A4388E';
        $this->platform = $config['platform'] ?? 'pcweb';

        $this->creditInEndpoint = $config['creditin'] ?? '/CRMManagerService/api/Finance/CreditIn';
        $this->creditOutEndpoint = $config['creditout'] ?? '/CRMManagerService/api/Finance/CreditOut';
        $this->resetPasswordEndpoint = $config['reset_password'] ?? '/CRMManagerService/api/Account/ResetPassword';
    }

    /**
     * 调用 FinancePro 接口
     *
     * @param string $endpoint 接口路径（例如 /CRMManagerService/api/Account/OpenAccount）
     * @param string $method HTTP 方法（GET/POST/PUT/...）
     * @param mixed $payload 请求体或查询参数
     * @param array $options 其他配置（headers、expect_success_key 等）
     * @return array 返回解析后的 JSON 数据
     * @throws Exception
     */
    public function request($endpoint, $method = 'GET', $payload = null, array $options = [])
    {
        if (!is_string($endpoint) || trim($endpoint) === '') {
            throw new InvalidArgumentException('Endpoint must be a non-empty string');
        }

        $method = strtoupper($method);

        $url = $this->buildUrl($endpoint);
        $body = null;

        if ($method === 'GET' && !empty($payload) && is_array($payload)) {
            $queryString = http_build_query($payload);
            if ($queryString !== '') {
                $url .= (strpos($url, '?') === false ? '?' : '&') . $queryString;
            }
//            Logger::error("aaaa1",['$url'=>$url]);
        } elseif ($payload !== null) {
            if (is_string($payload)) {
                $body = $payload;
            } else {
                $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
//            Logger::error("aaaa1",['$url'=>$url]);
//            Logger::error("aaaa11",['$body'=>$body]);
        }
        // 准备认证和签名Header（在body转换之前，使用原始payload）
        $authHeaders = $this->prepareAuthHeaders($method, $payload, $url);
//        Logger::error("aaaa2",['$authHeaders'=>$authHeaders]);

        // 合并自定义Header和认证Header
        $customHeaders = $options['headers'] ?? [];
        $allHeaders = array_merge($authHeaders, $customHeaders);
        $headers = $this->prepareHeaders($allHeaders);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)($options['connect_timeout'] ?? $this->connectTimeout));
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)($options['timeout'] ?? $this->timeout));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Trading Platform request failed: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($responseBody, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Trading Platform responded with invalid JSON: ' . json_last_error_msg());
        }

        if ($httpCode >= 400) {
            $errorMessage = $this->resolveErrorMessage($decoded, $options['error_message_key'] ?? 'ErrMsg');
            throw new Exception(sprintf('Trading Platform HTTP %d: %s', $httpCode, $errorMessage));
        }

        $expectSuccessKey = $options['expect_success_key'] ?? null;
        if ($expectSuccessKey) {
            $expectedValue = $options['success_value'] ?? true;
            $actualValue = $decoded[$expectSuccessKey] ?? null;

            if ($actualValue !== $expectedValue) {
                $errorMessage = $this->resolveErrorMessage($decoded, $options['error_message_key'] ?? 'ErrMsg');
                throw new Exception('Trading Platform error: ' . $errorMessage);
            }

            // Finance/Balance 等接口即使 Success=true，也可能带业务失败码（如 60002：originOrderId 重复、未真正入账），
            // 这类必须按失败处理，否则会把"没入账"当成功。
            $errCode = isset($decoded['ErrCode']) ? (string)$decoded['ErrCode'] : '';
            if (in_array($errCode, ['60002'], true)) {
                $errorMessage = $this->resolveErrorMessage($decoded, $options['error_message_key'] ?? 'ErrMsg');
                throw new Exception('Trading Platform error (ErrCode ' . $errCode . '): ' . $errorMessage);
            }
        }

        if (!empty($options['return_raw'])) {
            return [
                'httpCode' => $httpCode,
                'body' => $responseBody,
                'data' => $decoded
            ];
        }

        return $decoded;
    }

    /**
     * Credit In（加赠金）— POST /CRMManagerService/api/Finance/CreditIn
     *
     * @param int|string $login FinancePro 账户 login
     * @param float|int|string $amount 正数金额
     * @param string $comment 备注
     * @param string|null $originOrderId 上游订单ID（用于 FP 侧去重）
     * @return array FP 原始响应（ResData 含 TradeId 等）
     * @throws Exception FP 返回 Success=false 时抛出
     */
    public function creditIn($login, $amount, $comment = '', $originOrderId = null, array $options = [])
    {
        return $this->creditChange($this->creditInEndpoint, $login, $amount, $comment, $originOrderId, $options);
    }

    /**
     * Credit Out（扣赠金）— POST /CRMManagerService/api/Finance/CreditOut
     * amount 同样传正数，FP 端内部转负；Credit 余额不足时 FP 返 Success=false，这里按失败抛出。
     */
    public function creditOut($login, $amount, $comment = '', $originOrderId = null, array $options = [])
    {
        return $this->creditChange($this->creditOutEndpoint, $login, $amount, $comment, $originOrderId, $options);
    }

    // creditIn / creditOut 共用：拼 {Login, Amount, Comment, OriginOrderId} 调 FP，按 Success 判定成败。
    private function creditChange($endpoint, $login, $amount, $comment, $originOrderId, array $options)
    {
        $login = (int)$login;
        if ($login <= 0) {
            throw new InvalidArgumentException('FinancePro credit requires a valid login');
        }
        if (!is_numeric($amount) || (float)$amount <= 0) {
            throw new InvalidArgumentException('FinancePro credit amount must be greater than 0');
        }

        $payload = [
            'Login' => $login,
            'Amount' => (float)$amount,
            'Comment' => (string)$comment,
        ];
        if ($originOrderId !== null && $originOrderId !== '') {
            $payload['OriginOrderId'] = (string)$originOrderId;
        }

        $options = array_merge([
            'expect_success_key' => 'Success',
            'success_value' => true,
            'error_message_key' => 'ErrMsg',
        ], $options);

        return $this->request($endpoint, 'POST', $payload, $options);
    }

    /**
     * 修改密码 — POST /CRMManagerService/api/Account/ResetPassword
     *
     * - userId 传用户 email（服务端 GetUserByLogin 支持按 Account/Email/Mobile 查）
     * - 服务端已废弃 oldPassword / confirmPassword，只需 email + 新密码；新密码长度 6~50
     * - userId / newPassword 是裸简单参数、无 [FromBody]/[FromForm]，ASP.NET Core 对 POST
     *   简单类型默认从 query string 绑定，所以参数拼到 URL query 上、不发 body
     *
     * @param string $email 用户 email（作为 userId）
     * @param string $newPassword 新密码（6~50 位）
     * @return array FP 原始响应
     * @throws Exception FP 返回 Success=false 时抛出
     */
    public function resetPassword($email, $newPassword, array $options = [])
    {
        $email = trim((string)$email);
        $newPassword = (string)$newPassword;
        if ($email === '') {
            throw new InvalidArgumentException('FinancePro resetPassword requires the account email');
        }
        $len = strlen($newPassword);
        if ($len < 6 || $len > 50) {
            throw new InvalidArgumentException('FinancePro password must be 6-50 characters');
        }

        // FP ResetPasswordAsync(string userId, string newPassword) 是裸简单参数、无 [FromBody]/[FromForm]，
        // ASP.NET Core 对 POST 简单类型默认从 query string 绑定，不读 body。所以参数拼到 query 上，不发 body。
        // 接口是 [AllowAnonymous]，不涉及签名。http_build_query 会做 URL 编码，特殊字符安全。
        $query = http_build_query(['userId' => $email, 'newPassword' => $newPassword]);
        $endpoint = $this->resetPasswordEndpoint
            . (strpos($this->resetPasswordEndpoint, '?') === false ? '?' : '&')
            . $query;

        $options = array_merge([
            'expect_success_key' => 'Success',
            'success_value'      => true,
            'error_message_key'  => 'ErrMsg',
        ], $options);

        return $this->request($endpoint, 'POST', null, $options);
    }

    private function buildUrl($endpoint)
    {
        $trimmed = trim($endpoint);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Endpoint cannot be empty');
        }

        if ($trimmed[0] !== '/') {
            $trimmed = '/' . $trimmed;
        }

        return $this->baseUrl . $trimmed;
    }

    private function prepareHeaders($customHeaders)
    {
        $headersAssoc = $this->defaultHeaders;

        if (!empty($customHeaders) && is_array($customHeaders)) {
            $customAssoc = $this->normalizeHeaders($customHeaders);
            $headersAssoc = array_merge($headersAssoc, $customAssoc);
        }

        $headers = [];
        foreach ($headersAssoc as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }

        return $headers;
    }

    private function normalizeHeaders(array $headers)
    {
        $normalized = [];

        foreach ($headers as $key => $value) {
            if (is_int($key)) {
                $parts = explode(':', $value, 2);
                if (count($parts) === 2) {
                    $normalized[trim($parts[0])] = trim($parts[1]);
                }
            } else {
                $normalized[trim($key)] = trim($value);
            }
        }

        return $normalized;
    }

    private function resolveErrorMessage($decoded, $errorKey)
    {
        if (is_array($decoded) && isset($decoded[$errorKey]) && $decoded[$errorKey] !== '') {
            return $decoded[$errorKey];
        }

        if (is_array($decoded) && isset($decoded['message'])) {
            return $decoded['message'];
        }

        return 'Unknown error';
    }

    /**
     * 获取FinancePro访问Token
     *
     * @param array $credentials 认证凭据（可选，默认使用配置中的值）
     * @return array 包含access_token、expires_in、token_type、scope等信息
     * @throws Exception
     */
    public function getToken(array $credentials = [])
    {
        $appConfig = require __DIR__ . '/../config/app.php';
        $tokenUrl = $appConfig['integrations']['finance_pro']['token_url'] ?? null;

        if (!$tokenUrl) {
            throw new Exception('Token URL not configured in app.php');
        }

        // 从配置文件获取默认凭据
        $defaultCredentials = $appConfig['integrations']['finance_pro']['token_credentials'] ?? [];

        // 使用提供的凭据或配置文件中的值
        $payload = [
            'grant_type' => $credentials['grant_type'] ?? $defaultCredentials['grant_type'] ?? 'password',
            'client_id' => $credentials['client_id'] ?? $defaultCredentials['client_id'] ?? 'FinancePro',
            'client_secret' => $credentials['client_secret'] ?? $defaultCredentials['client_secret'] ?? 'FinancePro666',
            'username' => $credentials['username'] ?? $defaultCredentials['username'] ?? 'crmadmin',
            'password' => $credentials['password'] ?? $defaultCredentials['password'] ?? '2qRBpxmPVPCPjzVJk1hH'
        ];

//        Logger::info('Requesting FinancePro Token', [
//            'url' => $tokenUrl,
//            'username' => $payload['username']
//        ]);

        // Token接口通常使用application/x-www-form-urlencoded格式
        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
//            Logger::error('FinancePro Token request failed', [
//                'curl_error' => $curlError
//            ]);
            throw new Exception('Token request failed: ' . $curlError);
        }

        $decoded = json_decode($responseBody, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
//            Logger::error('FinancePro Token response invalid JSON', [
//                'http_code' => $httpCode,
//                'raw_response' => $responseBody
//            ]);
            throw new Exception('Token API responded with invalid JSON: ' . json_last_error_msg());
        }

        if ($httpCode >= 400 || !isset($decoded['access_token'])) {
            $errorMessage = $decoded['error_description'] ?? $decoded['error'] ?? 'Unknown error';
//            Logger::error('FinancePro Token request failed', [
//                'http_code' => $httpCode,
//                'error' => $errorMessage,
//                'response' => $decoded
//            ]);
            throw new Exception(sprintf('Token API HTTP %d: %s', $httpCode, $errorMessage));
        }

//        Logger::info('FinancePro Token obtained successfully', [
//            'token_type' => $decoded['token_type'] ?? null,
//            'expires_in' => $decoded['expires_in'] ?? null,
//            'scope' => $decoded['scope'] ?? null
//        ]);

        return $decoded;
    }

    /**
     * 准备认证和签名Header
     *
     * @param string $method HTTP方法
     * @param mixed $payload 请求体或查询参数
     * @param string $url 完整URL（用于GET请求提取查询参数）
     * @return array Header数组
     * @throws Exception
     */
    private function prepareAuthHeaders($method, $payload, $url)
    {
        $headers = [];

        // 1. 获取Bearer Token（如果不存在或过期，自动获取新token）
        $tokenModel = new FinanceProToken();
        $token = $tokenModel->getActiveToken();

        // 如果token不存在或过期，自动获取新token
        if (!$token || !isset($token['accessToken'])) {
            try {
                $tokenData = $this->getToken();
                $tokenModel->saveToken($tokenData);
                // 重新获取token数据
                $token = $tokenModel->getActiveToken();
            } catch (Exception $e) {
                throw new Exception('Failed to get Trading Platform access token: ' . $e->getMessage());
            }
        }

        if (!$token || !isset($token['accessToken'])) {
            throw new Exception('Trading Platform access token not found or expired. Please login again.');
        }

        $headers['Authorization'] = 'Bearer ' . $token['accessToken'];

        // 2. 生成签名相关参数
        $timeStamp = (string)time();
        $nonce = $this->generateNonce();

        // 3. 生成签名
        $signature = $this->generateSignature($method, $payload, $url, $timeStamp, $nonce);

        // 4. 添加签名相关Header
        $headers['appId'] = $this->appId;
        $headers['nonce'] = $nonce;
        $headers['timeStamp'] = $timeStamp;
        $headers['signature'] = $signature;
        $headers['platform'] = $this->platform;

        return $headers;
    }

    /**
     * 生成32位随机字符串（nonce）
     *
     * @return string
     */
    private function generateNonce()
    {
        return bin2hex(random_bytes(16)); // 32位十六进制字符串
    }

    /**
     * 生成签名
     *
     * @param string $method HTTP方法
     * @param mixed $payload 请求体或查询参数
     * @param string $url 完整URL
     * @param string $timeStamp 时间戳
     * @param string $nonce 随机字符串
     * @return string MD5签名
     */
    private function generateSignature($method, $payload, $url, $timeStamp, $nonce)
    {
        $method = strtoupper($method);
        $sortedParams = '';

        if ($method === 'GET') {
            // GET请求：从URL提取查询参数，按字母数字升序排列，去掉"="
            $parsedUrl = parse_url($url);
            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);
                $sortedParams = $this->sortAndConcatParams($queryParams);
            }
        } else {
            // POST/PUT等请求：将payload转为JSON，按字母数字升序排列
            if ($payload !== null) {
                if (is_array($payload)) {
                    $jsonString = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $sortedParams = $this->sortJsonString($jsonString);
                } elseif (is_string($payload)) {
                    // 如果已经是JSON字符串，直接排序
                    $sortedParams = $this->sortJsonString($payload);
                }
            }
        }

        // 生成签名：md5(timeStamp + nonce + sortedParams + appSecret)
        $signString = $timeStamp . $nonce . $sortedParams . $this->appSecret;
        $signature = md5($signString);

        // 记录签名生成信息（用于调试）
//        Logger::info('FinancePro API Signature Generated', [
//            'method' => $method,
//            'timeStamp' => $timeStamp,
//            'nonce' => $nonce,
//            'sortedParams' => $sortedParams,
//            'signString_length' => strlen($signString),
//            'signature' => $signature
//        ]);

        return $signature;
    }

    /**
     * 对参数按字母数字升序排列并拼接（去掉"="）
     * 例如：{"a":1, "b":2, "c":3} -> "a1b2c3"
     * 如果值是数组或对象，先转换为JSON字符串
     *
     * @param array $params
     * @return string
     */
    private function sortAndConcatParams(array $params)
    {
        ksort($params, SORT_STRING);
        $result = '';
        foreach ($params as $key => $value) {
            // 如果值是数组或对象，先转换为JSON字符串（参考Vue项目的处理方式）
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $result .= $key . $value;
        }
        return $result;
    }

    /**
     * 对JSON字符串按字母数字升序排列
     * 将JSON解析为数组，排序后重新拼接
     *
     * @param string $jsonString
     * @return string
     */
    private function sortJsonString($jsonString)
    {
        $decoded = json_decode($jsonString, true);
        if ($decoded === null || !is_array($decoded)) {
            // 如果不是有效的JSON数组，返回空字符串
            return '';
        }
        return $this->sortAndConcatParams($decoded);
    }
}
