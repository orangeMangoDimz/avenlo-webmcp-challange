<?php

require_once __DIR__ . '/../utils/Logger.php';

class FivePayService {
    const DEFAULT_BASE_URL = 'http://uat.en-payment.my5pay.com';
    const DEPOSIT_METHOD_F2F = 3;
    const WALLET_F2F = 'Fiat2Fiat';
    const TOKEN_HKD = 'HKD';
    const CHANNEL_F2F = 'Fiat2Fiat';
    const BANK_FPS = 'FPS';
    const PAYOUT_BANK_FPS = 'HKFPS';
    const TIMESTAMP_MAX_AGE_SECONDS = 10;
    const CALLBACK_TIMEZONE = 'Asia/Hong_Kong';
    const DEPOSIT_PATH = '/v2/deposit/Payment';
    const ORDER_STATUS_PATH = '/Order/GetStatus';
    const PAYOUT_PATH = '/v2/Payout/SubmitWithdrawal';
    const WALLET_BALANCE_PATH = '/V2/Payout/WalletBalance';

    public static function isCallbackPsp($psp): bool {
        $normalized = strtolower(trim((string)$psp));
        return $normalized === '5pay' || $normalized === 'spay';
    }

    public static function isProviderKey($key): bool {
        return self::isCallbackPsp($key);
    }

    public static function isGatewayKey($key): bool {
        $normalized = strtolower(trim((string)$key));
        return strpos($normalized, '5pay') === 0 || strpos($normalized, 'spay') === 0;
    }

    public static function hkdPayoutBanks(): array {
        return [
            ['code' => 'HKFPS', 'name' => 'Faster Payment System'],
            ['code' => '395_11', 'name' => 'AIRSTAR BANK'],
            ['code' => '393_12', 'name' => 'ANT BANK'],
            ['code' => '012_3', 'name' => 'BANK OF CHINA'],
            ['code' => '027_20', 'name' => 'BANK OF COMMUNICATIONS'],
            ['code' => '018_17', 'name' => 'CHINA CITIC BANK'],
            ['code' => '009_14', 'name' => 'CHINA CONSTRUCTION BANK'],
            ['code' => '039_24', 'name' => 'CHIYU BANKING CORPORATION'],
            ['code' => '041_26', 'name' => 'CHONG HING BANK'],
            ['code' => '250_7', 'name' => 'CITIBANK'],
            ['code' => '020_18', 'name' => 'CMB WING LUNG BANK'],
            ['code' => '040_25', 'name' => 'DAH SING BANK'],
            ['code' => '016_16', 'name' => 'DBS BANK'],
            ['code' => '128_6', 'name' => 'FUBON BANK'],
            ['code' => '391_29', 'name' => 'FUSION BANK'],
            ['code' => '024_4', 'name' => 'HANG SENG BANK'],
            ['code' => '072_5', 'name' => 'ICBC'],
            ['code' => '388_9', 'name' => 'LIVI BANK'],
            ['code' => '389_10', 'name' => 'MOX BANK'],
            ['code' => '043_27', 'name' => 'NANYANG COMMERCIAL BANK'],
            ['code' => '035_22', 'name' => 'OCBC WING HANG BANK'],
            ['code' => '392_13', 'name' => 'PING AN ONECONNECT BANK'],
            ['code' => '028_21', 'name' => 'PUBLIC BANK'],
            ['code' => '025_19', 'name' => 'SHANGHAI COMMERCIAL'],
            ['code' => '003_1', 'name' => 'STANDARD CHARTERED BANK'],
            ['code' => '038_23', 'name' => 'TAI YAU BANK'],
            ['code' => '015_15', 'name' => 'THE BANK OF EAST ASIA'],
            ['code' => '004_2', 'name' => 'HSBC'],
            ['code' => '390_28', 'name' => 'WELAB BANK'],
            ['code' => '387_8', 'name' => 'ZA BANK LIMITED'],
        ];
    }

    public static function resolvePayoutBankName(string $bankCode): string {
        foreach (self::hkdPayoutBanks() as $row) {
            if ($row['code'] === $bankCode) {
                return (string)$row['name'];
            }
        }
        return '';
    }

    private $merchantId;
    private $privateKey;
    private $platformPublicKey;
    private $baseUrl;
    private $config;

    public function __construct(array $gateway) {
        $this->config = $this->parseConfigData($gateway['configData'] ?? null);
        $this->merchantId = trim((string)($gateway['apiKey'] ?? ''));
        $this->privateKey = trim((string)($this->config['private_key'] ?? $gateway['secretKey'] ?? ''));
        $this->platformPublicKey = trim((string)($this->config['platform_public_key'] ?? ''));
        $this->baseUrl = rtrim(trim((string)($this->config['base_url'] ?? self::DEFAULT_BASE_URL)), '/');
    }

    public function isConfigured(): bool {
        return $this->merchantId !== ''
            && $this->privateKey !== ''
            && $this->platformPublicKey !== ''
            && $this->baseUrl !== '';
    }

    public function getConfig(): array {
        return $this->config;
    }

    public function getMerchantId(): string {
        return $this->merchantId;
    }

    public function getBaseUrl(): string {
        return $this->baseUrl;
    }

    public static function buildSignValue(array $params): string {
        unset($params['Sign'], $params['sign']);
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
        return implode('', array_values($filtered));
    }

    public function generateSign(array $params): string {
        $data = self::buildSignValue($params);
        $privateKey = $this->loadPrivateKey($this->privateKey);
        if ($privateKey === false) {
            throw new RuntimeException('5Pay merchant private key is invalid');
        }

        $signature = '';
        $ok = openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            throw new RuntimeException('5Pay RSA sign failed');
        }
        return base64_encode($signature);
    }

    public function verifySign(array $payload): bool {
        $sign = trim((string)($payload['Sign'] ?? $payload['sign'] ?? ''));
        if ($sign === '' || $this->platformPublicKey === '') {
            return false;
        }

        $publicKey = $this->loadPublicKey($this->platformPublicKey);
        if ($publicKey === false) {
            return false;
        }

        $data = self::buildSignValue($payload);
        $decodedSign = base64_decode($sign, true);
        if ($decodedSign === false) {
            return false;
        }

        return openssl_verify($data, $decodedSign, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    public static function utcTimestamp(): string {
        return gmdate('YmdHis');
    }

    public static function isTimestampFresh($timestamp, $now = null): bool {
        $raw = trim((string)$timestamp);
        if (!preg_match('/^\d{14}$/', $raw)) {
            return false;
        }
        $nowTs = $now === null ? time() : (int)$now;

        // The integration guide specifies UTC, but live HK callbacks have been
        // observed using the gateway's fixed UTC+8 representation. Both forms
        // still use the same signed timestamp and freshness window.
        foreach ([new DateTimeZone('UTC'), new DateTimeZone(self::CALLBACK_TIMEZONE)] as $timezone) {
            $parsed = DateTimeImmutable::createFromFormat('!YmdHis', $raw, $timezone);
            if ($parsed !== false && abs($nowTs - $parsed->getTimestamp()) <= self::TIMESTAMP_MAX_AGE_SECONDS) {
                return true;
            }
        }

        return false;
    }

    public static function formatAmount($amount): string {
        return number_format((float)$amount, 2, '.', '');
    }

    public static function buildMerchantOrderNo(string $transactionId): string {
        $orderNo = preg_replace('/[^A-Za-z0-9]/', '', str_replace('-', '', trim($transactionId)));
        return substr((string)$orderNo, 0, 64);
    }

    public static function mapDepositStatus($status): string {
        $status = (string)$status;
        if ($status === '4') {
            return 'success';
        }
        if (in_array($status, ['1', '2', '3', '9'], true)) {
            return 'processing';
        }
        if ($status === '6') {
            return 'expired';
        }
        if ($status === '7') {
            return 'cancelled';
        }
        return 'unknown';
    }

    public static function mapPayoutStatus($status): string {
        $status = (string)$status;
        if ($status === '3') {
            return 'success';
        }
        if ($status === '4') {
            return 'failed';
        }
        if (in_array($status, ['1', '2', '5', '6'], true)) {
            return 'processing';
        }
        return 'unknown';
    }

    public static function isDepositAccepted($response): bool {
        if (!is_array($response)) {
            return false;
        }
        return ($response['Success'] ?? null) === true
            || ($response['Success'] ?? null) === 'true'
            || ($response['Success'] ?? null) === 1;
    }

    public static function depositErrorMessage($response): string {
        if (!is_array($response)) {
            return '5Pay deposit request failed';
        }
        $message = trim((string)($response['Messsage'] ?? $response['Message'] ?? ''));
        if ($message === '' && isset($response['Data']) && is_array($response['Data'])) {
            $message = trim((string)($response['Data']['Messsage'] ?? $response['Data']['Message'] ?? ''));
        }
        return $message !== '' ? $message : '5Pay deposit request failed';
    }

    public static function isOrderNotFoundResponse($response): bool {
        if (!is_array($response) || self::isDepositAccepted($response)) {
            return false;
        }
        return self::isOrderNotFoundMessage(self::depositErrorMessage($response));
    }

    public static function isOrderNotFoundMessage($message): bool {
        $normalized = preg_replace('/\s+/', ' ', (string)$message);
        $normalized = strtolower(trim((string)$normalized));
        return (bool)preg_match('/\border\s+no(?:t)?\s+found\b/', $normalized);
    }

    public static function isPayoutAccepted($response): bool {
        if (!is_array($response)) {
            return false;
        }
        $first = self::firstPayoutItem($response);
        if ($first !== null) {
            return $first['Status'] === true || $first['Status'] === 'true' || $first['Status'] === 1;
        }
        return $response['Success'] === true || $response['Success'] === 'true' || $response['Success'] === 1;
    }

    public static function payoutErrorMessage($response): string {
        if (!is_array($response)) {
            return '5Pay payout request failed';
        }
        $first = self::firstPayoutItem($response);
        if ($first !== null) {
            $itemMessage = trim((string)($first['Message'] ?? ''));
            if ($itemMessage !== '') {
                return $itemMessage;
            }
        }
        $message = trim((string)($response['Message'] ?? $response['Messsage'] ?? ''));
        return $message !== '' ? $message : '5Pay payout request failed';
    }

    public static function extractPayoutId($response): string {
        $first = self::firstPayoutItem($response);
        if ($first === null) {
            return '';
        }
        $id = trim((string)($first['Id'] ?? $first['Data']['WithdrawalId'] ?? ''));
        return $id;
    }

    public function createDeposit(array $payload): array {
        return $this->postJson(self::DEPOSIT_PATH, $payload);
    }

    public function getOrderStatus(array $query): array {
        return $this->getQuery(self::ORDER_STATUS_PATH, $query);
    }

    public function submitWithdrawal(array $payload): array {
        return $this->requestJson('POST', self::PAYOUT_PATH, $payload, true);
    }

    public function getWalletBalance(array $query): array {
        return $this->getQuery(self::WALLET_BALANCE_PATH, $query);
    }

    public function signPayload(array $payload): array {
        $signed = $payload;
        unset($signed['Sign'], $signed['sign']);
        $signed['Sign'] = $this->generateSign($signed);
        return $signed;
    }

    private static function firstPayoutItem($response): ?array {
        if (!is_array($response)) {
            return null;
        }
        if (isset($response[0]) && is_array($response[0])) {
            return $response[0];
        }
        if (isset($response['Data'][0]) && is_array($response['Data'][0])) {
            return $response['Data'][0];
        }
        if (isset($response['Id']) || isset($response['Status'])) {
            return $response;
        }
        return null;
    }

    private function postJson(string $path, array $payload): array {
        return $this->requestJson('POST', $path, $payload, false);
    }

    private function getQuery(string $path, array $query): array {
        if (!$this->isConfigured()) {
            throw new RuntimeException('5Pay gateway is not configured');
        }
        if (!isset($query['MerchantId']) || trim((string)$query['MerchantId']) === '') {
            $query['MerchantId'] = $this->merchantId;
        }
        if (!isset($query['TimeStamp']) || trim((string)$query['TimeStamp']) === '') {
            $query['TimeStamp'] = self::utcTimestamp();
        }
        $signed = $this->signPayload($query);
        $url = $this->baseUrl . $path . '?' . http_build_query($signed);
        return $this->execute($url, 'GET', null, $signed);
    }

    private function requestJson(string $method, string $path, array $payload, bool $allowListResponse): array {
        if (!$this->isConfigured()) {
            throw new RuntimeException('5Pay gateway is not configured');
        }
        if (!isset($payload['MerchantId']) || trim((string)$payload['MerchantId']) === '') {
            $payload['MerchantId'] = $this->merchantId;
        }
        if (!isset($payload['TimeStamp']) || trim((string)$payload['TimeStamp']) === '') {
            $payload['TimeStamp'] = self::utcTimestamp();
        }
        $signed = $this->signPayload($payload);
        $url = $this->baseUrl . $path;
        $body = json_encode($signed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $this->execute($url, $method, $body, $signed, $allowListResponse);
    }

    private function execute(string $url, string $method, $body, array $requestForLog, bool $allowListResponse = false): array {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize curl for 5Pay request');
        }

        $headers = [
            'Content-Type: application/json',
            'User-Agent: CRM-5Pay/1.0'
        ];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $responseForLog = is_array($decoded)
            ? $decoded
            : ['raw' => is_string($raw) ? $raw : null];

        Logger::info('5Pay outbound', [
            'url' => $url,
            'merchantId' => $this->merchantId,
            'httpCode' => $httpCode,
            'curlErrNo' => $curlErrNo,
            'curlErr' => $curlErr !== '' ? $curlErr : null,
            'request' => $this->redactForLog($requestForLog),
            'response' => $this->redactForLog($responseForLog),
        ]);

        if ($curlErrNo !== 0) {
            throw new RuntimeException('5Pay request failed: ' . $curlErr);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('5Pay response is not valid JSON (HTTP ' . $httpCode . ')');
        }

        if ($allowListResponse && isset($decoded[0]) && is_array($decoded[0])) {
            return $decoded;
        }

        return $decoded;
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

    private function redactForLog($value) {
        if (!is_array($value)) {
            return $value;
        }
        $copy = $value;
        foreach (['Sign', 'sign', 'secretKey', 'privateKey', 'private_key', 'platform_public_key'] as $key) {
            if (isset($copy[$key]) && is_string($copy[$key]) && $copy[$key] !== '') {
                $copy[$key] = '[REDACTED]';
            }
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
