<?php
/**
 * Vexora PSP service (Korea + Cambodia corridors via config)
 *
 * paymentGatewaySettings field mapping:
 *   apiKey     -> merchantNo (request header)
 *   secretKey  -> app secret (MD5 sign salt)
 *   configData -> {
 *       "providerKey": "vexora",
 *       "region": "korea" | "cambodia",
 *       "base_url": "https://sandbox-api.vexora.../xxx",
 *       "sub_merchant_no": "...",   // Korea only
 *       "default_channel_code": "...",
 *       "default_way_code": "...",
 *       "currency": "KRW" | "KHR" | "USD"
 *   }
 *
 * Sign: exclude sign + empty values, sort keys a-z, concat key=value (no delimiter),
 * append app secret, lowercase MD5. Callbacks use the same rule.
 */

require_once __DIR__ . '/../utils/Logger.php';

class VexoraService {
    const CODE_SUCCESS = '0000';
    const CODE_SYSTEM_EXCEPTION = '5000';
    const CODE_REQUEST_FAILED = '8000';

    const STATUS_SUCCESS = '0000';
    const STATUS_PARTIAL = '0001';
    const STATUS_PROCESSING = '0015';
    const STATUS_FAILED = '00029';

    const REGION_KOREA = 'korea';
    const REGION_CAMBODIA = 'cambodia';

    private $merchantNo;
    private $secret;
    private $baseUrl;
    private $subMerchantNo;
    private $region;
    private $amountDecimalPlaces;
    private $config;

    public function __construct(array $gateway) {
        $this->config = $this->parseConfigData($gateway['configData'] ?? null);
        $this->merchantNo = trim((string)($gateway['apiKey'] ?? ''));
        $this->secret = trim((string)($gateway['secretKey'] ?? ''));
        $this->baseUrl = rtrim(trim((string)($this->config['base_url'] ?? '')), '/');
        $this->subMerchantNo = trim((string)($this->config['sub_merchant_no'] ?? ''));
        $this->region = strtolower(trim((string)($this->config['region'] ?? '')));
        if ($this->region === '') {
            $this->region = self::REGION_KOREA;
        }

        $decimals = $gateway['amountDecimalPlaces'] ?? $this->config['amount_decimal_places'] ?? null;
        if ($decimals === null || $decimals === '') {
            $currency = strtoupper(trim((string)($this->config['currency'] ?? '')));
            $decimals = $currency === 'USD' ? 2 : 0;
        }
        $this->amountDecimalPlaces = max(0, (int)$decimals);
    }

    public function isConfigured(): bool {
        if ($this->merchantNo === '' || $this->secret === '' || $this->baseUrl === '') {
            return false;
        }

        if ($this->isCambodia()) {
            return true;
        }

        return $this->subMerchantNo !== '';
    }

    public function isCambodia(): bool {
        return $this->region === self::REGION_CAMBODIA;
    }

    public function getRegion(): string {
        return $this->region;
    }

    public function getAmountDecimalPlaces(): int {
        return $this->amountDecimalPlaces;
    }

    public function getConfig(): array {
        return $this->config;
    }

    public function getSubMerchantNo(): string {
        return $this->subMerchantNo;
    }

    /**
     * Format amount as string for Vexora body.
     * KHR/KRW: integer. USD Cambodia: up to 2 decimal places.
     */
    public function formatAmount($amount): string {
        $value = (float)$amount;
        if ($this->amountDecimalPlaces <= 0) {
            return (string)(int)round($value);
        }

        return number_format(round($value, $this->amountDecimalPlaces), $this->amountDecimalPlaces, '.', '');
    }

    public function generateSign(array $params): string {
        unset($params['sign']);
        $filtered = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
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

        $concatenated = '';
        foreach ($filtered as $key => $value) {
            $concatenated .= $key . '=' . $value;
        }

        return strtolower(md5($concatenated . $this->secret));
    }

    public function verifySign(array $payload): bool {
        $sign = strtolower(trim((string)($payload['sign'] ?? '')));
        if ($sign === '' || $this->secret === '') {
            return false;
        }
        return hash_equals($this->generateSign($payload), $sign);
    }

    public function createCheckout(array $payload): array {
        return $this->post('/v1/vexora/checkout', $payload);
    }

    public function createDisbursement(array $payload): array {
        return $this->post('/v1/vexora/disbursements', $payload);
    }

    public function queryPayInResult(string $tradeNo): array {
        return $this->post('/v1/vexora/queryPayInResult', ['tradeNo' => $tradeNo]);
    }

    public function queryPayOutResult(string $tradeNo): array {
        return $this->post('/v1/vexora/queryPayOutResult', ['tradeNo' => $tradeNo]);
    }

    public static function isRequestAccepted($response): bool {
        return is_array($response) && (string)($response['code'] ?? '') === self::CODE_SUCCESS;
    }

    public static function isSystemException($response): bool {
        return is_array($response) && (string)($response['code'] ?? '') === self::CODE_SYSTEM_EXCEPTION;
    }

    public static function mapStatus($status): string {
        $status = trim((string)$status);
        if ($status === self::STATUS_SUCCESS) {
            return 'success';
        }
        if ($status === self::STATUS_PARTIAL) {
            return 'partial';
        }
        if ($status === self::STATUS_PROCESSING) {
            return 'processing';
        }
        if ($status === self::STATUS_FAILED) {
            return 'failed';
        }
        return $status === '' ? 'unknown' : 'failed';
    }

    public static function buildTradeNo(string $transactionId): string {
        $tradeNo = str_replace('-', '', trim($transactionId));
        return substr($tradeNo, 0, 32);
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

    public static function normalizeCambodiaMobile($mobile): string {
        $digits = preg_replace('/\D+/', '', (string)$mobile);
        if ($digits === '') {
            return '';
        }
        if (strpos($digits, '855') === 0 && strlen($digits) >= 11) {
            $digits = substr($digits, 3);
        }
        if (strpos($digits, '0') === 0) {
            $digits = substr($digits, 1);
        }
        return $digits;
    }

    public static function isValidCambodiaMobile(string $mobile): bool {
        return strlen($mobile) === 8 && ctype_digit($mobile);
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
            throw new RuntimeException('Vexora gateway is not configured');
        }

        $payload['timestamp'] = (string)(int)round(microtime(true) * 1000);

        if ($this->isCambodia()) {
            unset($payload['subMerchantNo']);
        } elseif (!isset($payload['subMerchantNo']) || trim((string)$payload['subMerchantNo']) === '') {
            $payload['subMerchantNo'] = $this->subMerchantNo;
        }

        if (array_key_exists('amount', $payload)) {
            $payload['amount'] = $this->formatAmount($payload['amount']);
        }

        foreach ($payload as $key => $value) {
            if ($value === null || is_array($value) || is_object($value)) {
                continue;
            }
            $payload[$key] = (string)$value;
        }

        if ($this->isCambodia()) {
            unset($payload['subMerchantNo']);
        }

        $payload['sign'] = $this->generateSign($payload);

        $url = $this->baseUrl . $path;
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize curl for Vexora request');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'merchantNo: ' . $this->merchantNo,
            'User-Agent: CRM-Vexora/1.0'
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

        Logger::info('Vexora outbound', [
            'url' => $url,
            'merchantNo' => $this->merchantNo,
            'region' => $this->region,
            'httpCode' => $httpCode,
            'curlErrNo' => $curlErrNo,
            'curlErr' => $curlErr !== '' ? $curlErr : null,
            'request' => $payload,
            'response' => $responseForLog,
        ]);

        if ($curlErrNo !== 0) {
            throw new RuntimeException('Vexora request failed: ' . $curlErr);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Vexora response is not valid JSON (HTTP ' . $httpCode . ')');
        }

        return $decoded;
    }
}
