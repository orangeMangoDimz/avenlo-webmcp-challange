<?php

require_once __DIR__ . '/../utils/Logger.php';

class FlashPayService {
    const CODE_SUCCESS = 0;

    const PAY_STATE_CREATED = 0;
    const PAY_STATE_PAYING = 1;
    const PAY_STATE_SUCCESS = 2;
    const PAY_STATE_FAILED = 3;
    const PAY_STATE_CANCELLED = 4;
    const PAY_STATE_REFUNDED = 5;
    const PAY_STATE_CLOSED = 6;

    const TRANSFER_STATE_CREATED = 0;
    const TRANSFER_STATE_TRANSFERRING = 1;
    const TRANSFER_STATE_SUCCESS = 2;
    const TRANSFER_STATE_FAILED = 3;
    const TRANSFER_STATE_CLOSED = 4;

    const DEFAULT_IF_CODE = 'pakrwpulpay';
    const DEFAULT_WAY_CODE = 'WAKRWPUL_CARD';
    const DEFAULT_CURRENCY = '410';
    const DEFAULT_ENTRY_TYPE = 'BANK_CARD';
    const DEFAULT_BASE_URL = 'https://pay.flashpay.fit';
    private const AMOUNT_CENTS_SCALE = 100;

    private $mchNo;
    private $appId;
    private $privateKey;
    private $platformPublicKey;
    private $baseUrl;
    private $config;

    public function __construct(array $gateway) {
        $this->config = $this->parseConfigData($gateway['configData'] ?? null);
        $this->mchNo = trim((string)($gateway['apiKey'] ?? ''));
        $this->appId = trim((string)($gateway['appId'] ?? ''));
        $this->privateKey = trim((string)($gateway['secretKey'] ?? ''));
        $this->platformPublicKey = trim((string)($this->config['platform_public_key'] ?? ''));
        $this->baseUrl = rtrim(trim((string)($this->config['base_url'] ?? self::DEFAULT_BASE_URL)), '/');
    }

    public function isConfigured(): bool {
        return $this->mchNo !== ''
            && $this->appId !== ''
            && $this->privateKey !== ''
            && $this->platformPublicKey !== ''
            && $this->baseUrl !== '';
    }

    public function getConfig(): array {
        return $this->config;
    }

    public function getMchNo(): string {
        return $this->mchNo;
    }

    public function getAppId(): string {
        return $this->appId;
    }

    public function getIfCode(): string {
        $value = trim((string)($this->config['if_code'] ?? self::DEFAULT_IF_CODE));
        return $value !== '' ? $value : self::DEFAULT_IF_CODE;
    }

    public function getWayCode(): string {
        $value = trim((string)($this->config['way_code'] ?? self::DEFAULT_WAY_CODE));
        return $value !== '' ? $value : self::DEFAULT_WAY_CODE;
    }

    public function getCurrency(): string {
        $value = trim((string)($this->config['currency'] ?? self::DEFAULT_CURRENCY));
        return $value !== '' ? $value : self::DEFAULT_CURRENCY;
    }

    public function getEntryType(): string {
        $value = trim((string)($this->config['entry_type'] ?? self::DEFAULT_ENTRY_TYPE));
        return $value !== '' ? $value : self::DEFAULT_ENTRY_TYPE;
    }

    public static function buildSignString(array $params): string {
        unset($params['sign']);
        $filtered = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_bool($value)) {
                $filtered[$key] = $value ? 'true' : 'false';
                continue;
            }
            if (is_array($value) || is_object($value)) {
                $filtered[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                continue;
            }
            $stringValue = (string)$value;
            if ($stringValue === '') {
                continue;
            }
            $filtered[$key] = $stringValue;
        }
        ksort($filtered, SORT_STRING);

        $parts = [];
        foreach ($filtered as $key => $value) {
            $parts[] = $key . '=' . $value;
        }
        return implode('&', $parts);
    }

    public function generateSign(array $params): string {
        $data = self::buildSignString($params);
        $privateKey = $this->loadPrivateKey($this->privateKey);
        if ($privateKey === false) {
            throw new RuntimeException('FlashPay merchant private key is invalid');
        }

        $signature = '';
        $ok = openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            throw new RuntimeException('FlashPay RSA sign failed');
        }
        return base64_encode($signature);
    }

    public function verifySign(array $payload): bool {
        $sign = trim((string)($payload['sign'] ?? ''));
        if ($sign === '' || $this->platformPublicKey === '') {
            return false;
        }

        $publicKey = $this->loadPublicKey($this->platformPublicKey);
        if ($publicKey === false) {
            return false;
        }

        $data = self::buildSignString($payload);
        $decodedSign = base64_decode($sign, true);
        if ($decodedSign === false) {
            return false;
        }

        $result = openssl_verify($data, $decodedSign, $publicKey, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }

    public function verifyResponseDataSign(array $data, string $sign): bool {
        if (self::isListArray($data)) {
            return $this->verifyRawSign(self::toJavaStyleListSignString($data), $sign);
        }
        return $this->verifySign(array_merge($data, ['sign' => $sign]));
    }

    public static function isListArray(array $value): bool {
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }
        if ($value === []) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }

    public static function toJavaStyleListSignString(array $list): string {
        $items = [];
        foreach ($list as $item) {
            if (is_array($item) && !self::isListArray($item)) {
                $items[] = self::toJavaStyleMapSignString($item, true);
                continue;
            }
            if (is_array($item)) {
                $items[] = self::toJavaStyleListSignString($item);
                continue;
            }
            $items[] = (string)$item;
        }
        return '[' . implode(',', $items) . ']';
    }

    public static function toJavaStyleMapSignString(array $map, bool $wrapBraces = false): string {
        ksort($map, SORT_STRING);
        $parts = [];
        foreach ($map as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_bool($value)) {
                $parts[] = $key . '=' . ($value ? 'true' : 'false');
                continue;
            }
            if (is_array($value)) {
                $parts[] = $key . '=' . (self::isListArray($value)
                    ? self::toJavaStyleListSignString($value)
                    : self::toJavaStyleMapSignString($value, true));
                continue;
            }
            $parts[] = $key . '=' . $value;
        }
        $joined = implode('&', $parts);
        return $wrapBraces ? '{' . $joined . '}' : $joined;
    }

    public static function normalizeBankOptionRow(array $item): ?array {
        $left = trim((string)($item['code'] ?? $item['bankCode'] ?? ''));
        $right = trim((string)($item['name'] ?? $item['bankName'] ?? ''));
        if ($left === '' || $right === '') {
            return null;
        }

        $leftIsCode = self::isBankCodeValue($left);
        $rightIsCode = self::isBankCodeValue($right);

        if ($leftIsCode && !$rightIsCode) {
            return ['code' => $left, 'name' => $right];
        }
        if ($rightIsCode && !$leftIsCode) {
            return ['code' => $right, 'name' => $left];
        }
        if ($leftIsCode && $rightIsCode) {
            return null;
        }
        return null;
    }

    public static function isBankCodeValue(string $value): bool {
        return (bool)preg_match('/^\d{2,6}$/', $value);
    }

    public function createUnifiedOrder(array $payload): array {
        return $this->post('/api/pay/unifiedOrder', $payload);
    }

    public function submitUtrOrder(array $payload): array {
        return $this->post('/api/pay/utrOrder', $payload);
    }

    public function queryPayOrder(array $payload): array {
        return $this->post('/api/pay/query', $payload);
    }

    public function closePayOrder(array $payload): array {
        return $this->post('/api/pay/close', $payload);
    }

    public function createTransferOrder(array $payload): array {
        return $this->post('/api/transferOrder', $payload);
    }

    public function queryTransferOrder(array $payload): array {
        return $this->post('/api/transfer/query', $payload);
    }

    public function queryBanks(array $payload = []): array {
        return $this->post('/api/channel/querybanks', $payload);
    }

    public function queryBalance(array $payload = []): array {
        return $this->post('/api/balance', $payload);
    }

    public static function isRequestAccepted($response): bool {
        if (!is_array($response)) {
            return false;
        }
        return (int)($response['code'] ?? -1) === self::CODE_SUCCESS;
    }

    public static function mapPayInState($state): string {
        $state = (int)$state;
        if ($state === self::PAY_STATE_SUCCESS) {
            return 'success';
        }
        if ($state === self::PAY_STATE_CREATED || $state === self::PAY_STATE_PAYING) {
            return 'processing';
        }
        if (
            $state === self::PAY_STATE_FAILED
            || $state === self::PAY_STATE_CANCELLED
            || $state === self::PAY_STATE_REFUNDED
            || $state === self::PAY_STATE_CLOSED
        ) {
            return 'failed';
        }
        return 'unknown';
    }

    public static function mapPayOutState($state): string {
        $state = (int)$state;
        if ($state === self::TRANSFER_STATE_SUCCESS) {
            return 'success';
        }
        if ($state === self::TRANSFER_STATE_CREATED || $state === self::TRANSFER_STATE_TRANSFERRING) {
            return 'processing';
        }
        if ($state === self::TRANSFER_STATE_FAILED || $state === self::TRANSFER_STATE_CLOSED) {
            return 'failed';
        }
        return 'unknown';
    }

    public static function buildMchOrderNo(string $transactionId): string {
        $orderNo = str_replace('-', '', trim($transactionId));
        return substr($orderNo, 0, 30);
    }

    public static function toFlashPayAmountCents($krwAmount): int {
        return (int)round((float)$krwAmount * self::AMOUNT_CENTS_SCALE);
    }

    public static function parseFlashPayAmountCents($providerAmount): int {
        return (int)round((float)$providerAmount);
    }

    public static function normalizeKoreanMobile($mobile): string {
        $digits = preg_replace('/\D+/', '', (string)$mobile);
        if ($digits === '') {
            return '';
        }
        if (strpos($digits, '82') === 0 && strlen($digits) > 9) {
            $digits = '0' . substr($digits, 2);
        }
        if (strpos($digits, '0') !== 0) {
            $digits = '0' . $digits;
        }
        return $digits;
    }

    public static function encodeChannelExtra(array $extra): string {
        return json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function normalizeBankcardPayData($payData): array {
        $payload = null;
        if (is_string($payData) && trim($payData) !== '') {
            $decoded = json_decode(trim($payData), true);
            $payload = is_array($decoded) ? $decoded : ['raw' => trim($payData)];
        } elseif (is_array($payData)) {
            $payload = $payData;
        }

        if (!is_array($payload)) {
            return [];
        }

        $bankInfo = (isset($payload['bankInfo']) && is_array($payload['bankInfo']))
            ? $payload['bankInfo']
            : $payload;

        $cashierLink = '';
        if (isset($payload['cashier']) && is_array($payload['cashier'])) {
            $cashierLink = trim((string)($payload['cashier']['link'] ?? ''));
        }
        if ($cashierLink === '') {
            $cashierLink = trim((string)($payload['cashierLink'] ?? $payload['link'] ?? ''));
        }

        $bankName = trim((string)($bankInfo['bankName'] ?? $bankInfo['bank_name'] ?? $bankInfo['bank'] ?? ''));
        $accountNo = trim((string)($bankInfo['bankNo']
            ?? $bankInfo['accountNo']
            ?? $bankInfo['account_no']
            ?? $bankInfo['cardNo']
            ?? $bankInfo['card_no']
            ?? ''));
        $accountName = trim((string)($bankInfo['name']
            ?? $bankInfo['accountName']
            ?? $bankInfo['account_name']
            ?? $bankInfo['accountHolder']
            ?? ''));
        $expireTime = trim((string)($bankInfo['expireTime'] ?? $bankInfo['expire_time'] ?? ''));
        $amount = $bankInfo['amount'] ?? null;

        $normalized = ['bankcard' => $payload];
        if ($bankName !== '') {
            $normalized['bankName'] = $bankName;
        }
        if ($accountNo !== '') {
            $normalized['accountNo'] = $accountNo;
        }
        if ($accountName !== '') {
            $normalized['accountName'] = $accountName;
        }
        if ($amount !== null && $amount !== '') {
            $normalized['amount'] = is_scalar($amount) ? (string)$amount : $amount;
        }
        if ($expireTime !== '') {
            $normalized['expireTime'] = $expireTime;
        }
        if ($cashierLink !== '') {
            $normalized['cashierLink'] = $cashierLink;
        }

        return $normalized;
    }

    private function parseConfigData($configData): array {
        if (is_array($configData)) {
            return $configData;
        }
        if (is_string($configData) && trim($configData) !== '') {
            $decoded = json_decode($configData, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    private function post(string $path, array $payload): array {
        if (!$this->isConfigured()) {
            throw new RuntimeException('FlashPay gateway is not configured');
        }

        if (!isset($payload['mchNo']) || trim((string)$payload['mchNo']) === '') {
            $payload['mchNo'] = $this->mchNo;
        }
        if (!isset($payload['appId']) || trim((string)$payload['appId']) === '') {
            $payload['appId'] = $this->appId;
        }
        if (!isset($payload['reqTime']) || trim((string)$payload['reqTime']) === '') {
            $payload['reqTime'] = (string)(int)round(microtime(true) * 1000);
        }
        if (!isset($payload['version']) || trim((string)$payload['version']) === '') {
            $payload['version'] = '1.0';
        }
        $payload['signType'] = 'RSA';

        if (array_key_exists('amount', $payload)) {
            $payload['amount'] = (string)self::parseFlashPayAmountCents($payload['amount']);
        }

        foreach ($payload as $key => $value) {
            if ($value === null || is_array($value) || is_object($value) || is_bool($value)) {
                continue;
            }
            $payload[$key] = (string)$value;
        }

        $payload['sign'] = $this->generateSign($payload);

        $url = $this->baseUrl . $path;
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize curl for FlashPay request');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: CRM-FlashPay/1.0'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $raw = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $responseForLog = is_array($decoded)
            ? $decoded
            : ['raw' => is_string($raw) ? $raw : null];

        Logger::info('FlashPay outbound', [
            'url' => $url,
            'mchNo' => $this->mchNo,
            'httpCode' => $httpCode,
            'curlErrNo' => $curlErrNo,
            'curlErr' => $curlErr !== '' ? $curlErr : null,
            'request' => $this->redactForLog($payload),
            'response' => $this->redactForLog($responseForLog),
        ]);

        if ($curlErrNo !== 0) {
            throw new RuntimeException('FlashPay request failed: ' . $curlErr);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('FlashPay response is not valid JSON (HTTP ' . $httpCode . ')');
        }

        $responseSign = trim((string)($decoded['sign'] ?? ''));
        $responseData = $decoded['data'] ?? null;
        if ($responseSign !== '' && is_array($responseData)) {
            $verified = $this->verifyResponseDataSign($responseData, $responseSign);
            if (!$verified) {
                if (self::isListArray($responseData)) {
                    Logger::warning('FlashPay list response signature verification failed; continuing', [
                        'url' => $url,
                        'mchNo' => $this->mchNo,
                        'code' => $decoded['code'] ?? null,
                        'msg' => $decoded['msg'] ?? null,
                    ]);
                } else {
                    throw new RuntimeException('FlashPay response signature verification failed');
                }
            }
        }

        return $decoded;
    }

    private function verifyRawSign(string $data, string $sign): bool {
        $sign = trim($sign);
        if ($data === '' || $sign === '' || $this->platformPublicKey === '') {
            return false;
        }
        $publicKey = $this->loadPublicKey($this->platformPublicKey);
        if ($publicKey === false) {
            return false;
        }
        $decodedSign = base64_decode($sign, true);
        if ($decodedSign === false) {
            return false;
        }
        return openssl_verify($data, $decodedSign, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    private function redactForLog($value) {
        if (!is_array($value)) {
            return $value;
        }
        $copy = $value;
        foreach (['sign', 'secretKey', 'privateKey', 'platform_public_key'] as $key) {
            if (isset($copy[$key]) && is_string($copy[$key]) && $copy[$key] !== '') {
                $copy[$key] = '[REDACTED]';
            }
        }
        if (isset($copy['accountNo']) && is_string($copy['accountNo']) && strlen($copy['accountNo']) > 4) {
            $copy['accountNo'] = str_repeat('*', max(0, strlen($copy['accountNo']) - 4)) . substr($copy['accountNo'], -4);
        }
        if (isset($copy['utr']) && is_string($copy['utr']) && $copy['utr'] !== '') {
            $copy['utr'] = '[REDACTED]';
        }
        return $copy;
    }

    private function loadPrivateKey(string $key) {
        $normalized = $this->normalizeKeyMaterial($key);
        if (strpos($normalized, 'BEGIN') !== false) {
            return openssl_pkey_get_private($normalized);
        }
        $privateKeyLabel = 'PRIVATE ' . 'KEY';
        $pem = '-----BEGIN ' . $privateKeyLabel . "-----\n"
            . chunk_split($normalized, 64, "\n")
            . '-----END ' . $privateKeyLabel . '-----';
        $resource = openssl_pkey_get_private($pem);
        if ($resource !== false) {
            return $resource;
        }
        $rsaKeyLabel = 'RSA ' . 'PRIVATE ' . 'KEY';
        $rsaPem = '-----BEGIN ' . $rsaKeyLabel . "-----\n"
            . chunk_split($normalized, 64, "\n")
            . '-----END ' . $rsaKeyLabel . '-----';
        return openssl_pkey_get_private($rsaPem);
    }

    private function loadPublicKey(string $key) {
        $normalized = $this->normalizeKeyMaterial($key);
        if (strpos($normalized, 'BEGIN') !== false) {
            return openssl_pkey_get_public($normalized);
        }
        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split($normalized, 64, "\n")
            . "-----END PUBLIC KEY-----";
        return openssl_pkey_get_public($pem);
    }

    private function normalizeKeyMaterial(string $key): string {
        $key = trim($key);
        $key = str_replace(["\r\n", "\r"], "\n", $key);
        if (strpos($key, 'BEGIN') !== false) {
            return $key;
        }
        return preg_replace('/\s+/', '', $key);
    }
}
