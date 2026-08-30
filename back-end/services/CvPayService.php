<?php

require_once __DIR__ . '/../utils/Logger.php';

class CvPayService {
    const CODE_SUCCESS = 0;

    const PAY_STATE_CREATED = 0;
    const PAY_STATE_PAYING = 1;
    const PAY_STATE_SUCCESS = 2;
    const PAY_STATE_FAILED = 3;
    const PAY_STATE_CANCELLED = 4;
    const PAY_STATE_REFUNDED = 5;
    const PAY_STATE_CLOSED = 6;

    const TRANSFER_STATE_CREATED = 0;
    const TRANSFER_STATE_PROCESSING = 1;
    const TRANSFER_STATE_SUCCESS = 2;
    const TRANSFER_STATE_FAILED = 3;
    const TRANSFER_STATE_CLOSED = 4;

    const DEFAULT_BASE_URL = 'https://sandbox-payapi.cvpay.info';
    const DEFAULT_WAY_CODE = 'QRCODE247';
    const DEFAULT_CURRENCY = 'vnd';
    const DEFAULT_VERSION = '3.0';
    const DEFAULT_SIGN_TYPE = 'MD5';

    private $mchNo;
    private $appId;
    private $secretKey;
    private $baseUrl;
    private $config;

    public function __construct(array $gateway) {
        $this->config = $this->parseConfigData($gateway['configData'] ?? null);
        $this->mchNo = trim((string)($gateway['apiKey'] ?? ''));
        $this->appId = trim((string)($gateway['appId'] ?? ''));
        $this->secretKey = trim((string)($gateway['secretKey'] ?? ''));
        $this->baseUrl = rtrim(trim((string)($this->config['base_url'] ?? self::DEFAULT_BASE_URL)), '/');
    }

    public function isConfigured(): bool {
        return $this->mchNo !== ''
            && $this->appId !== ''
            && $this->secretKey !== ''
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

    public function getWayCode(): string {
        $value = trim((string)($this->config['default_way_code'] ?? self::DEFAULT_WAY_CODE));
        return $value !== '' ? $value : self::DEFAULT_WAY_CODE;
    }

    public function getCurrency(): string {
        $value = strtolower(trim((string)($this->config['currency'] ?? self::DEFAULT_CURRENCY)));
        return $value !== '' ? $value : self::DEFAULT_CURRENCY;
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
        return strtoupper(md5($data . '&key=' . $this->secretKey));
    }

    public function verifySign(array $payload): bool {
        $sign = strtoupper(trim((string)($payload['sign'] ?? '')));
        if ($sign === '' || $this->secretKey === '') {
            return false;
        }
        return hash_equals($this->generateSign($payload), $sign);
    }

    public function verifyResponseDataSign(array $data, string $sign): bool {
        return $this->verifySign(array_merge($data, ['sign' => $sign]));
    }

    public function createPayment(array $payload): array {
        return $this->post('/api/pay/create', $payload);
    }

    public function queryPayment(array $payload): array {
        return $this->post('/api/pay/query', $payload);
    }

    public function closePayment(array $payload): array {
        return $this->post('/api/pay/close', $payload);
    }

    public function createTransfer(array $payload): array {
        return $this->post('/api/transfer/create', $payload);
    }

    public function queryTransfer(array $payload): array {
        return $this->post('/api/transfer/query', $payload);
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
        if ($state === self::TRANSFER_STATE_CREATED || $state === self::TRANSFER_STATE_PROCESSING) {
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

    public static function toCvPayAmount($amount): int {
        return (int)round((float)$amount);
    }

    public static function parseCvPayAmount($providerAmount): int {
        return (int)round((float)$providerAmount);
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
            throw new RuntimeException('CVPay gateway is not configured');
        }

        if (!isset($payload['mchNo']) || trim((string)$payload['mchNo']) === '') {
            $payload['mchNo'] = $this->mchNo;
        }
        if (!isset($payload['appId']) || trim((string)$payload['appId']) === '') {
            $payload['appId'] = $this->appId;
        }
        if (!isset($payload['reqTime']) || trim((string)$payload['reqTime']) === '') {
            $payload['reqTime'] = (string)time();
        }
        if (!isset($payload['version']) || trim((string)$payload['version']) === '') {
            $payload['version'] = self::DEFAULT_VERSION;
        }
        $payload['signType'] = self::DEFAULT_SIGN_TYPE;

        if (array_key_exists('amount', $payload)) {
            $payload['amount'] = (string)self::parseCvPayAmount($payload['amount']);
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
            throw new RuntimeException('Failed to initialize curl for CVPay request');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: CRM-CvPay/1.0'
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

        Logger::info('CVPay outbound', [
            'url' => $url,
            'mchNo' => $this->mchNo,
            'httpCode' => $httpCode,
            'curlErrNo' => $curlErrNo,
            'curlErr' => $curlErr !== '' ? $curlErr : null,
            'request' => $this->redactForLog($payload),
            'response' => $this->redactForLog($responseForLog),
        ]);

        if ($curlErrNo !== 0) {
            throw new RuntimeException('CVPay request failed: ' . $curlErr);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('CVPay response is not valid JSON (HTTP ' . $httpCode . ')');
        }

        $responseSign = trim((string)($decoded['sign'] ?? ''));
        $responseData = $decoded['data'] ?? null;
        if ($responseSign !== '' && is_array($responseData) && !self::isListArray($responseData)) {
            if (!$this->verifyResponseDataSign($responseData, $responseSign)) {
                throw new RuntimeException('CVPay response signature verification failed');
            }
        }

        return $decoded;
    }

    private static function isListArray(array $value): bool {
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }
        if ($value === []) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }

    private function redactForLog($value) {
        if (!is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $key => $item) {
            $keyLower = strtolower((string)$key);
            if (
                strpos($keyLower, 'key') !== false
                || strpos($keyLower, 'secret') !== false
                || strpos($keyLower, 'sign') !== false
                || strpos($keyLower, 'account') !== false
                || strpos($keyLower, 'phone') !== false
                || strpos($keyLower, 'email') !== false
            ) {
                $redacted[$key] = '***';
                continue;
            }
            $redacted[$key] = is_array($item) ? $this->redactForLog($item) : $item;
        }
        return $redacted;
    }
}
