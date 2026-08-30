<?php
/**
 * Alchemy Pay API Service
 * 提供调用 Alchemy Pay 第三方接口的通用方法
 *
 * 参考文档：
 * - API Sign: https://alchemypay.readme.io/docs/api-sign
 * - Get Token: https://alchemypay.readme.io/docs/get-token
 * - Fiat Query: https://alchemypay.readme.io/docs/fiat-query
 */

require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../models/PaymentGatewaySetting.php';
require_once __DIR__ . '/../utils/Encryptor.php';
require_once __DIR__ . '/../utils/Logger.php';

class AlchemyPayService
{
    private $baseUrl;
    private $connectTimeout;
    private $timeout;
    private $appId;
    private $appSecret;
    private $gatewayModel;

    public function __construct()
    {
        $appConfig = require __DIR__ . '/../config/app.php';
        $config = $appConfig['integrations']['alchemy_pay'] ?? [];

        $this->baseUrl = rtrim($config['base_url'] ?? 'https://openapi.alchemypay.org', '/');
        $this->connectTimeout = (int)($config['connect_timeout'] ?? 10);
        $this->timeout = (int)($config['timeout'] ?? 30);

        // 从 paymentGatewaySettings 表获取 appId 和 appSecret
        $this->gatewayModel = new PaymentGatewaySetting();
        $gateway = $this->gatewayModel->findByKeyWithSecrets('alchemy_pay');

        if (!$gateway || !$gateway['isEnabled']) {
            throw new Exception('Alchemy Pay gateway is not configured or not enabled');
        }

        $this->appId = $gateway['appId'] ?? '';
        $this->appSecret = $gateway['secretKey'] ?? '';

        if (empty($this->appId) || empty($this->appSecret)) {
            throw new Exception('Alchemy Pay appId or appSecret is not configured');
        }
    }

    /**
     * 调用 Alchemy Pay 接口
     *
     * @param string $endpoint 接口路径（例如 /open/api/v4/merchant/fiat/query）
     * @param string $method HTTP 方法（GET/POST）
     * @param mixed $payload 请求体或查询参数
     * @param array $options 其他配置
     * @return array 返回解析后的 JSON 数据
     * @throws Exception
     */
    public function request($endpoint, $method = 'GET', $payload = null, array $options = [])
    {
        if (!is_string($endpoint) || trim($endpoint) === '') {
            throw new InvalidArgumentException('Endpoint must be a non-empty string');
        }

        $method = strtoupper($method);

        // 构建完整 URL
        $url = $this->baseUrl . $endpoint;
        $bodyString = '';

        // 处理 GET 请求的查询参数
        $queryParams = [];
        if ($method === 'GET' && !empty($payload) && is_array($payload)) {
            // 移除空值并排序
            $payload = $this->removeEmptyKeys($payload);
            $payload = $this->sortObject($payload);
            $queryParams = $payload;
            $queryString = http_build_query($payload);
            if ($queryString !== '') {
                $url .= (strpos($url, '?') === false ? '?' : '&') . $queryString;
            }
        } elseif ($payload !== null) {
            // POST 请求：将 payload 转为 JSON 字符串
            if (is_string($payload)) {
                $bodyString = $payload;
            } else {
                // 移除空值并排序
                $payload = $this->removeEmptyKeys($payload);
                $payload = $this->sortObject($payload);
                $bodyString = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        // 生成签名
        $timestamp = (string)(time() * 1000); // 13位时间戳（毫秒）

        // 构建 requestPath（包含查询参数，已排序）
        $requestPath = parse_url($url, PHP_URL_PATH);
        if ($method === 'GET' && !empty($queryParams)) {
            // GET 请求：查询参数已排序，直接拼接
            $sortedQuery = http_build_query($queryParams);
            if ($sortedQuery !== '') {
                $requestPath .= '?' . $sortedQuery;
            }
        } elseif (parse_url($url, PHP_URL_QUERY)) {
            // 如果 URL 中已有查询参数（非 GET 请求的情况）
            $requestPath .= '?' . parse_url($url, PHP_URL_QUERY);
        }

        $sign = $this->generateSignature($timestamp, $method, $requestPath, $bodyString);

        // 准备请求头
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'appId: ' . $this->appId,
            'timestamp: ' . $timestamp,
            'sign: ' . $sign
        ];

        // 如果提供了 access-token，添加到 Header（某些 API 需要，如 Create Order）
        if (isset($options['accessToken']) && !empty($options['accessToken'])) {
            $headers[] = 'access-token: ' . $options['accessToken'];
        }
//        Logger::error("aaaa000",['headers'=>$headers]);
//        Logger::error("aaaa0001",['param'=>$payload]);

        // 执行请求
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST' && $bodyString !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyString);
        }

        $responseBody = curl_exec($ch);
//        Logger::error("aaaa01",['responseBody'=>$responseBody]);
        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Alchemy Pay API request failed: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//        Logger::error("aaaa02",['httpCode'=>$httpCode]);
        curl_close($ch);

        $decoded = json_decode($responseBody, true);
//        Logger::error("aaaa03",['decoded'=>$decoded]);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Alchemy Pay API responded with invalid JSON: ' . json_last_error_msg());
        }

        if ($httpCode >= 400) {
            $errorMessage = $decoded['returnMsg'] ?? $decoded['message'] ?? 'Unknown error';
            throw new Exception(sprintf('Alchemy Pay API HTTP %d: %s', $httpCode, $errorMessage));
        }

        // 检查返回码
        if (isset($decoded['returnCode']) && $decoded['returnCode'] !== '0000') {
            $errorMessage = $decoded['returnMsg'] ?? 'Unknown error';
            $returnCode = $decoded['returnCode'] ?? 'UNKNOWN';
            throw new Exception("Alchemy Pay API error [{$returnCode}]: {$errorMessage}");
        }

        return $decoded;
    }

    /**
     * 生成签名
     * 签名字符串 = timestamp + httpMethod + requestPath + bodyString
     * 使用 HMAC SHA256 加密，然后 Base64 编码
     *
     * @param string $timestamp 13位时间戳（毫秒）
     * @param string $httpMethod HTTP 方法（GET/POST）
     * @param string $requestPath 请求路径（包含查询参数）
     * @param string $bodyString 请求体（JSON 字符串）
     * @return string Base64 编码的签名
     */
    private function generateSignature($timestamp, $httpMethod, $requestPath, $bodyString)
    {
        // 签名字符串 = timestamp + httpMethod + requestPath + bodyString
        $content = $timestamp . strtoupper($httpMethod) . $requestPath . $bodyString;

        // 使用 HMAC SHA256 加密
        $signature = hash_hmac('sha256', $content, $this->appSecret, true);

        // Base64 编码
        return base64_encode($signature);
    }

    /**
     * 获取 Access Token
     *
     * @param string|null $email 用户邮箱（与uid二选一）
     * @param string|null $uid 用户UUID（与email二选一，36位）
     * @return array 包含 accessToken 和 expiresIn
     * @throws Exception
     */
    public function getToken($email = null, $uid = null)
    {
        $appConfig = require __DIR__ . '/../config/app.php';
        $tokenEndpoint = $appConfig['integrations']['alchemy_pay']['token_endpoint'] ?? '/open/api/v4/merchant/getToken';

        // 根据文档，必须提供 email 或 uid 中的一个
        $payload = [];
        if (!empty($email)) {
            $payload['email'] = $email;
        } elseif (!empty($uid)) {
            $payload['uid'] = $uid;
        } else {
            throw new Exception('Either email or uid must be provided for Get Token API');
        }

        $response = $this->request($tokenEndpoint, 'POST', $payload);

        if (!isset($response['data']['accessToken'])) {
            throw new Exception('Failed to get access token: token not found in response');
        }

        return [
            'accessToken' => $response['data']['accessToken'],
            'expiresIn' => $response['data']['expiresIn'] ?? 864000, // 默认10天（864000秒）
            'expiresAt' => date('Y-m-d H:i:s', time() + ($response['data']['expiresIn'] ?? 864000))
        ];
    }

    /**
     * 获取 Access Token（每次调用都获取新 token）
     * TODO: 后续可以添加 token 缓存机制
     *
     * @param string|null $email 用户邮箱（与uid二选一）
     * @param string|null $uid 用户UUID（与email二选一）
     * @return string Access Token
     * @throws Exception
     */
    public function getAccessToken($email = null, $uid = null)
    {
        $tokenData = $this->getToken($email, $uid);
        return $tokenData['accessToken'];
    }

    /**
     * 查询支持的 Fiat 货币列表
     * 根据文档：https://alchemypay.readme.io/docs/fiat-query
     *
     * @param string $type BUY 或 SELL（可选，默认 BUY）
     * @return array Fiat 货币列表（包含 currency, country, payWayCode, payWayName 等字段）
     * @throws Exception
     */
    public function getFiatCurrencies($type = null)
    {
        $appConfig = require __DIR__ . '/../config/app.php';
        $endpoint = $appConfig['integrations']['alchemy_pay']['fiat_query'] ?? '/open/api/v4/merchant/fiat/list';

        $payload = [];
        if ($type) {
            // 根据文档，参数名是 type，值为 BUY 或 SELL
            $payload['type'] = strtoupper($type);
        }

        $response = $this->request($endpoint, 'GET', $payload);

        if (!isset($response['data']) || !is_array($response['data'])) {
            return [];
        }

        return $response['data'];
    }

    /**
     * 查询支持的加密货币列表
     * 根据文档：https://alchemypay.readme.io/docs/crypto-query
     *
     * @param string $fiatCurrency 法币类型（可选，默认 USD）
     * @return array 加密货币列表（包含 buyEnable 和 sellEnable 字段）
     * @throws Exception
     */
    public function getCryptoCurrencies($fiatCurrency = null)
    {
        $appConfig = require __DIR__ . '/../config/app.php';
        $endpoint = $appConfig['integrations']['alchemy_pay']['crypto_query'] ?? '/open/api/v4/merchant/crypto/list';

        $payload = [];
        if ($fiatCurrency) {
            $payload['fiat'] = strtoupper($fiatCurrency);
        }

        $response = $this->request($endpoint, 'GET', $payload);

        if (!isset($response['data']) || !is_array($response['data'])) {
            return [];
        }

        return $response['data'];
    }

    /**
     * 创建订单
     * 根据文档：https://alchemypay.readme.io/docs/create-order-2
     *
     * 官方说明：
     * Step 1: To get the token, you need to create a separate accessToken for each user
     * Step 2: Create an order, you need to create an order through accessToken
     * Step 3: The user redirects to the payment URL
     *
     * @param array $orderData 订单数据
     *   - merchantOrderNo: 商户订单号（必填）
     *   - fiatCurrency: 法币代码（必填，如 USD/EUR）
     *   - cryptoCurrency: 加密货币代码（必填，大写，如 USDT）
     *   - network: 网络名称（必填，如 ETH/BSC）
     *   - amount: 金额（必填，字符串类型）
     *   - address: 接收数字货币的地址（必填）
     *   - payWayCode: 支付方式代码（必填）
     *   - side: 订单类型（必填，固定值 "BUY"）
     *   - depositType: 存款类型（必填，固定值 2）
     *   - userEmail: 用户邮箱（必填，用于获取 accessToken）
     *   - userUid: 用户UUID（可选，如果提供则使用uid而不是email）
     *   - redirectUrl: 支付成功后的重定向地址（可选）
     *   - callbackUrl: 支付成功后的回调地址（可选）
     *   - memo: 备注（可选，某些网络需要）
     *   - failRedirectUrl: 支付失败后的重定向地址（可选）
     *   - merchantName: 商户名称（可选）
     * @return array 订单信息（包含 orderNo 和 payUrl）
     * @throws Exception
     */
    public function createOrder(array $orderData)
    {
        $appConfig = require __DIR__ . '/../config/app.php';
        $endpoint = $appConfig['integrations']['alchemy_pay']['create_order'] ?? '/open/api/v4/merchant/trade/create';

        // 验证必填参数（根据文档）
        $requiredFields = [
            'merchantOrderNo',
            'fiatCurrency',
            'cryptoCurrency',
            'network',
            'amount',
            'address',
            'payWayCode',
            'side',
            'depositType'
        ];
        foreach ($requiredFields as $field) {
            if (empty($orderData[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        // 获取 access-token（根据文档，必须为每个用户创建单独的 accessToken）
        // 优先使用 userUid，如果没有则使用 userEmail
        $userEmail = $orderData['userEmail'] ?? null;
        $userUid = $orderData['userUid'] ?? null;

        if (empty($userEmail) && empty($userUid)) {
            throw new Exception('userEmail or userUid is required for Create Order API');
        }

        $accessToken = null;
        try {
            $tokenData = $this->getToken($userEmail, $userUid);
            $accessToken = $tokenData['accessToken'];
        } catch (Exception $e) {
            // 获取 token 失败，必须抛出异常，因为 Create Order API 需要 access-token
            Logger::error('Failed to get access token for Create Order', [
                'email' => $userEmail,
                'uid' => $userUid,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to get access token: ' . $e->getMessage());
        }

        // 构建请求体（根据文档要求）
        $payload = [
            'side' => 'BUY',  // 必填，固定值 "BUY"
            'merchantOrderNo' => $orderData['merchantOrderNo'],
            'amount' => $orderData['amount'].'',  // 必填，字符串类型
            'fiatCurrency' => strtoupper($orderData['fiatCurrency']),  // 必填
            'cryptoCurrency' => strtoupper($orderData['cryptoCurrency']),  // 必填，大写
            'depositType' => (int)$orderData['depositType'],  // 必填，固定值 2
            'address' => $orderData['address'],  // 必填，接收数字货币的地址
            'network' => $orderData['network'],  // 必填
            'payWayCode' => $orderData['payWayCode']  // 必填，支付方式代码
        ];

        // 可选参数
        if (!empty($orderData['redirectUrl'])) {
            $payload['redirectUrl'] = $orderData['redirectUrl'];
        }
        if (!empty($orderData['callbackUrl'])) {
            $payload['callbackUrl'] = $orderData['callbackUrl'];
        }
        if (!empty($orderData['memo'])) {
            $payload['memo'] = $orderData['memo'];
        }
        if (!empty($orderData['failRedirectUrl'])) {
            $payload['failRedirectUrl'] = $orderData['failRedirectUrl'];
        }
        if (!empty($orderData['merchantName'])) {
            $payload['merchantName'] = $orderData['merchantName'];
        }

        // 调用 API，传递 access-token（如果获取成功）
        $options = [];
        if ($accessToken) {
            $options['accessToken'] = $accessToken;
        }
        $response = $this->request($endpoint, 'POST', $payload, $options);

        // 检查响应是否成功
        if (!isset($response['success']) || !$response['success']) {
            $returnCode = $response['returnCode'] ?? 'UNKNOWN';
            $returnMsg = $response['returnMsg'] ?? 'Unknown error';
            throw new Exception("Alchemy Pay Create Order failed [{$returnCode}]: {$returnMsg}");
        }

        if (!isset($response['data'])) {
            throw new Exception('Invalid response from Alchemy Pay Create Order API: data field is missing');
        }

        // 验证返回的数据是否包含必要的字段
        if (empty($response['data']['payUrl'])) {
            throw new Exception('Alchemy Pay Create Order API did not return payment URL');
        }

        return $response;
    }

    /**
     * 查询订单状态
     * 根据文档：https://alchemypay.readme.io/docs/query-order
     *
     * @param string $merchantOrderNo 商户订单号
     * @return array 订单状态信息
     * @throws Exception
     */
    public function queryOrder($merchantOrderNo)
    {
        $appConfig = require __DIR__ . '/../config/app.php';
        $endpoint = $appConfig['integrations']['alchemy_pay']['query_order'] ?? '/open/api/v4/merchant/order/query';

        $payload = [
            'merchantOrderNo' => $merchantOrderNo
        ];

        $response = $this->request($endpoint, 'GET', $payload);

        if (!isset($response['data'])) {
            throw new Exception('Invalid response from Alchemy Pay Query Order API');
        }

        return $response;
    }

    /**
     * 移除对象中的空值
     *
     * @param mixed $data
     * @return mixed
     */
    private function removeEmptyKeys($data)
    {
        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                if ($value !== null && $value !== '') {
                    if (is_array($value) || is_object($value)) {
                        $cleaned = $this->removeEmptyKeys($value);
                        if (!empty($cleaned)) {
                            $result[$key] = $cleaned;
                        }
                    } else {
                        $result[$key] = $value;
                    }
                }
            }
            return $result;
        }
        return $data;
    }

    /**
     * 对对象按键名排序
     *
     * @param mixed $data
     * @return mixed
     */
    private function sortObject($data)
    {
        if (is_array($data)) {
            if ($this->isAssociativeArray($data)) {
                ksort($data, SORT_STRING);
                foreach ($data as $key => $value) {
                    if (is_array($value) || is_object($value)) {
                        $data[$key] = $this->sortObject($value);
                    }
                }
            } else {
                // 数组保持原样，但递归处理元素
                foreach ($data as $key => $value) {
                    if (is_array($value) || is_object($value)) {
                        $data[$key] = $this->sortObject($value);
                    }
                }
            }
        }
        return $data;
    }

    /**
     * 判断是否为关联数组
     *
     * @param array $array
     * @return bool
     */
    private function isAssociativeArray($array)
    {
        if (!is_array($array)) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * 验证 Webhook 签名
     * 根据文档：https://alchemypay.readme.io/docs/webhook-signature
     *
     * @param string $timestamp 请求头中的 timestamp
     * @param string $requestPath 请求路径（不含域名）
     * @param array $requestBody 请求体数据
     * @param string $newSignature 请求体中的 newSignature
     * @return bool
     */
    public function verifyWebhookSignature($timestamp, $requestPath, $requestBody, $newSignature) {
        try {
            // 1. 移除空值、signature 和 newSignature
            $filteredBody = [];
            foreach ($requestBody as $key => $value) {
                if ($key === 'signature' || $key === 'newSignature') {
                    continue;
                }
                if ($value !== null && $value !== '') {
                    $filteredBody[$key] = $value;
                }
            }

            // 2. 按参数名升序排序
            ksort($filteredBody);

            // 3. 转换为 JSON 字符串（不转义斜杠，紧凑格式）
            $requestBodyStr = json_encode($filteredBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // 4. 生成待签名字符串：timestamp + requestMethod + requestPath + requestBody
            $content = $timestamp . 'POST' . $requestPath . $requestBodyStr;

            // 5. 使用 HMAC-SHA256 生成签名
            $calculatedSignature = base64_encode(hash_hmac('sha256', $content, $this->appSecret, true));

            // 6. 比较签名（使用 hash_equals 防止时序攻击）
            return hash_equals($calculatedSignature, $newSignature);

        } catch (Exception $e) {
            Logger::error('Webhook signature verification failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
