<?php
/**
 * X-Link P2P API client.
 *
 * Credentials and configuration are supplied from paymentGatewaySettings:
 * - apiKey             X-Link X-API-KEY request header
 * - secretKey          webhook HMAC-SHA256 secret
 * - configData         shop_id, base_url, callback_url and return_base_url
 *
 * This service deliberately does not settle deposits or withdrawals. It only
 * owns provider communication and the pure webhook validation helpers used by
 * the payment controllers.
 */

require_once __DIR__ . '/../utils/Logger.php';

class XLinkApiException extends RuntimeException {
    private $httpStatus;
    private $response;
    private $ambiguous;

    public function __construct(
        string $message,
        int $httpStatus = 0,
        array $response = [],
        bool $ambiguous = false,
        Throwable $previous = null
    ) {
        parent::__construct($message, $httpStatus, $previous);
        $this->httpStatus = $httpStatus;
        $this->response = $response;
        $this->ambiguous = $ambiguous;
    }

    public function getHttpStatus(): int {
        return $this->httpStatus;
    }

    public function getResponse(): array {
        return $this->response;
    }

    public function isAmbiguous(): bool {
        return $this->ambiguous;
    }
}

class XLinkService {
    const GATEWAY_KEY = 'xlink-krw';
    const PROVIDER_KEY = 'xlink';
    const DEFAULT_CURRENCY = 'KRW';
    const INSTRUMENT_BANK_TRANSFER = 'BANK_TRANSFER';
    const WEBHOOK_EVENT_STATUS_UPDATED = 'transaction.status.updated';
    const PRODUCTION_API_HOST = 'api.x-link.asia';

    public static function depositSupportQuestions(): array {
        return [
            [
                'name' => 'account_name',
                'hintText' => 'Payer account holder name.',
                'questionType' => 'text',
                'validationRules' => 'required|max:255',
                'options' => null,
                'scope' => 'deposit',
                'isLocked' => 1
            ]
        ];
    }

    private $apiKey;
    private $secretKey;
    private $baseUrl;
    private $shopId;
    private $callbackUrl;
    private $returnBaseUrl;
    private $config;
    private $transport;

    /**
     * The optional transport receives method, URL, body and headers. It keeps
     * controller tests independent from curl without changing production I/O.
     */
    public function __construct(array $gateway, callable $transport = null) {
        $this->config = $this->parseConfigData($gateway['configData'] ?? null);
        $this->apiKey = trim((string)($gateway['apiKey'] ?? ''));
        $this->secretKey = trim((string)($gateway['secretKey'] ?? ''));
        $this->baseUrl = rtrim(trim((string)($this->config['base_url'] ?? '')), '/');
        $this->shopId = trim((string)($this->config['shop_id'] ?? ''));
        $this->callbackUrl = trim((string)($this->config['callback_url'] ?? ''));
        $this->returnBaseUrl = rtrim(trim((string)($this->config['return_base_url'] ?? '')), '/');
        $this->transport = $transport;
    }

    public function isConfigured(): bool {
        return $this->apiKey !== ''
            && $this->secretKey !== ''
            && $this->baseUrl !== ''
            && ctype_digit($this->shopId)
            && (int)$this->shopId > 0
            && $this->callbackUrl !== ''
            && $this->returnBaseUrl !== '';
    }

    public function getConfig(): array {
        return $this->config;
    }

    public function getShopId(): int {
        return ctype_digit($this->shopId) ? (int)$this->shopId : 0;
    }

    public function getCallbackUrl(): string {
        return $this->callbackUrl;
    }

    public function getReturnBaseUrl(): string {
        return $this->returnBaseUrl;
    }

    /**
     * Find the first channel that can accept the requested KRW amount.
     */
    public function getPaymentMethodType(string $operationType, $amount, string $currency = self::DEFAULT_CURRENCY): array {
        $operationType = self::normalizeOperationType($operationType);
        $currency = strtoupper(trim($currency));
        $normalizedAmount = self::normalizeKrwAmount($amount);

        if ($operationType === '' || $currency !== self::DEFAULT_CURRENCY) {
            throw new InvalidArgumentException('X-Link supports KRW PAYIN or PAYOUT payment-method discovery only.');
        }

        $response = $this->requestJson('GET', '/payment-method-types', null, [
            'shop_id' => (string)$this->requireShopId(),
            'operation_type' => $operationType,
            'amount' => $normalizedAmount,
            'currency' => $currency,
        ]);

        foreach ($this->extractPaymentMethodTypes($response) as $method) {
            if (!$this->isEligiblePaymentMethod($method, $operationType, $normalizedAmount, $currency)) {
                continue;
            }
            return $method;
        }

        throw new XLinkApiException(
            'X-Link has no eligible ' . $operationType . ' BANK_TRANSFER method for the requested KRW amount.',
            422,
            self::sanitizeForLog($response),
            false
        );
    }

    /**
     * POST /public/payment-links. The caller supplies its transactionId as
     * operation_number, which makes the one retry safe at the provider.
     */
    public function createPaymentLink(array $payload): array {
        $this->validateOperationPayload($payload, 'PAYIN');
        return $this->requestJson('POST', '/public/payment-links', $payload, [], true);
    }

    /** POST /sessions for a PAYOUT session. */
    public function initializeSession(array $payload): array {
        $this->validateOperationPayload($payload, 'PAYOUT');
        return $this->requestJson('POST', '/sessions', $payload, [], true);
    }

    public function autoCreatesPayoutOperation(): bool {
        $host = strtolower((string)parse_url($this->baseUrl, PHP_URL_HOST));
        return $host === self::PRODUCTION_API_HOST;
    }

    /** POST /operations. A session already carries the idempotent operation number. */
    public function createOperation(string $sessionId, array $payerRequisites): array {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            throw new InvalidArgumentException('X-Link session_id is required to create an operation.');
        }
        return $this->requestJson('POST', '/operations', [
            'session_id' => $sessionId,
            'payer_requisites' => $payerRequisites,
        ]);
    }

    /** GET /operations/{shop_id}/{operation_number}. */
    public function getOperation(string $operationNumber): array {
        $operationNumber = trim($operationNumber);
        if ($operationNumber === '') {
            throw new InvalidArgumentException('X-Link operation_number is required.');
        }

        $shopId = $this->requireShopId();
        return $this->requestJson(
            'GET',
            '/operations/' . rawurlencode((string)$shopId) . '/' . rawurlencode($operationNumber)
        );
    }

    public static function buildPayerRequisites(array $sessionData, array $values): array {
        $templates = self::extractPayerRequisiteTemplates($sessionData);
        if ($templates === []) {
            foreach ($values as $name => $value) {
                $name = trim((string)$name);
                if ($name === '' || trim((string)$value) === '') {
                    continue;
                }
                $templates[] = [
                    'name' => $name,
                    'title' => self::humanizeRequisiteName($name),
                    'type' => 'string',
                ];
            }
        }

        $requisites = [];
        foreach ($templates as $template) {
            if (!is_array($template)) {
                continue;
            }
            $name = trim((string)($template['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $value = self::lookupRequisiteValue($values, $name);
            if ($value === '') {
                $value = trim((string)($template['value'] ?? ''));
            }
            if ($value === '') {
                continue;
            }
            $title = trim((string)($template['title'] ?? ''));
            $type = trim((string)($template['type'] ?? ''));
            $requisites[] = [
                'name' => $name,
                'title' => $title !== '' ? $title : self::humanizeRequisiteName($name),
                'type' => $type !== '' ? $type : 'string',
                'value' => $value,
            ];
        }

        return $requisites;
    }

    /**
     * Verify the provider signature against the unmodified request body.
     * X-Link callbacks may supply a hex digest or Base64-encoded raw digest.
     */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool {
        $signature = trim($signature);
        if ($rawBody === '' || $signature === '' || $this->secretKey === '') {
            return false;
        }

        if (stripos($signature, 'sha256=') === 0) {
            $signature = trim(substr($signature, 7));
        }
        if ($signature === '') {
            return false;
        }

        $hex = hash_hmac('sha256', $rawBody, $this->secretKey);
        if (ctype_xdigit($signature) && strlen($signature) === strlen($hex)) {
            return hash_equals(strtolower($hex), strtolower($signature));
        }

        $decodedSignature = base64_decode($signature, true);
        if ($decodedSignature === false) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $this->secretKey, true), $decodedSignature);
    }

    /**
     * Convert the provider callback to a controller-friendly invariant shape.
     * Invalid callback structures are rejected before any database lookup.
     */
    public function normalizeCallback(array $payload): array {
        $event = trim((string)($payload['event'] ?? ''));
        $data = $payload['data'] ?? null;
        if ($event !== self::WEBHOOK_EVENT_STATUS_UPDATED || !is_array($data)) {
            throw new InvalidArgumentException('Invalid X-Link callback event or payload.');
        }

        $operationNumber = trim((string)($data['operation_number'] ?? ''));
        $status = strtoupper(trim((string)($data['status'] ?? '')));
        $operationType = self::normalizeOperationType((string)($data['operation_type'] ?? ''));
        $currency = strtoupper(trim((string)($data['currency'] ?? '')));
        if ($operationNumber === '' || $status === '' || $operationType === '' || $currency === '') {
            throw new InvalidArgumentException('X-Link callback is missing required operation fields.');
        }

        return [
            'event' => $event,
            'operationId' => trim((string)($data['operation_id'] ?? '')),
            'operationNumber' => $operationNumber,
            'status' => $status,
            'mappedStatus' => self::mapOperationStatus($status),
            'operationType' => $operationType,
            'currency' => $currency,
            'initiatedAmount' => self::optionalAmount($data['initiated_amount'] ?? null),
            'amount' => self::optionalAmount($data['amount'] ?? null),
            'createdAt' => trim((string)($data['created_at'] ?? '')),
            'updatedAt' => trim((string)($data['updated_at'] ?? '')),
            'rawData' => self::sanitizeForLog($data),
        ];
    }

    public static function mapOperationStatus($status): string {
        switch (strtoupper(trim((string)$status))) {
            case 'PENDING':
                return 'pending';
            case 'PROCESSING':
                return 'processing';
            case 'SUCCESS':
                return 'success';
            case 'FAILED':
            case 'DECLINED':
                return 'failed';
            case 'CANCELLED':
                return 'cancelled';
            case 'EXPIRED':
                return 'expired';
            case 'REFUNDED':
                return 'refunded';
            default:
                return 'unknown';
        }
    }

    public static function normalizeKrwAmount($amount): string {
        // Keep the prior is_numeric-style input contract while avoiding the
        // bool/object string casts that would otherwise turn true into "1".
        if (!is_int($amount) && !is_float($amount) && !is_string($amount)) {
            throw new InvalidArgumentException('X-Link KRW amount must be a positive number.');
        }
        $rawAmount = trim((string)$amount);
        if (!preg_match('/^\+?(\d+)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/', $rawAmount, $matches)) {
            throw new InvalidArgumentException('X-Link KRW amount must be a positive number.');
        }

        // Decide whether the provider amount is whole from its original text.
        // A float comparison loses the .5 in values such as 9007199254740992.5.
        $integerPart = $matches[1];
        $fractionalPart = $matches[2] ?? '';
        $exponent = isset($matches[3]) && $matches[3] !== '' ? (int)$matches[3] : 0;
        $digits = ltrim($integerPart . $fractionalPart, '0');
        if ($digits === '') {
            throw new InvalidArgumentException('X-Link KRW amount must be a positive number.');
        }

        $numericAmount = (float)$rawAmount;
        if (!is_finite($numericAmount) || $numericAmount <= 0) {
            throw new InvalidArgumentException('X-Link KRW amount must be a finite positive number.');
        }

        $decimalShift = $exponent - strlen($fractionalPart);
        if ($decimalShift >= 0) {
            $normalized = $digits . str_repeat('0', $decimalShift);
        } else {
            $requiredTrailingZeros = -$decimalShift;
            if ($requiredTrailingZeros > strlen($digits)
                || substr($digits, -$requiredTrailingZeros) !== str_repeat('0', $requiredTrailingZeros)
            ) {
                throw new InvalidArgumentException('X-Link KRW amount must be a whole number.');
            }
            $normalized = substr($digits, 0, strlen($digits) - $requiredTrailingZeros);
        }

        $normalized = ltrim($normalized, '0');
        if ($normalized === '') {
            throw new InvalidArgumentException('X-Link KRW amount must be a whole number.');
        }
        return $normalized;
    }

    public static function sanitizeForLog($value) {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                $keyString = (string)$key;
                if (preg_match('/(api[._-]?key|secret|authorization|signature|password|token)/i', $keyString)) {
                    $sanitized[$key] = '[REDACTED]';
                    continue;
                }
                $sanitized[$key] = self::sanitizeForLog($item);
            }
            return $sanitized;
        }
        return $value;
    }

    private function requestJson(string $method, string $path, array $payload = null, array $query = [], bool $retryIdempotent = false): array {
        $this->requireRequestConfiguration();
        $attempts = $retryIdempotent ? 2 : 1;
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $this->performRequest($method, $path, $payload, $query);
            } catch (XLinkApiException $exception) {
                $lastException = $exception;
                if (!$exception->isAmbiguous() || $attempt === $attempts) {
                    throw $exception;
                }
                $this->log('warning', 'X-Link ambiguous request; retrying idempotent operation.', [
                    'method' => $method,
                    'path' => $path,
                    'attempt' => $attempt,
                    'operationNumber' => $payload['operation_number'] ?? null,
                    'httpStatus' => $exception->getHttpStatus(),
                ]);
            }
        }

        throw $lastException ?: new XLinkApiException('X-Link request failed unexpectedly.', 0, [], true);
    }

    private function performRequest(string $method, string $path, array $payload = null, array $query = []): array {
        $url = $this->buildUrl($path, $query);
        $body = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload !== null && $body === false) {
            throw new InvalidArgumentException('Unable to encode X-Link request payload as JSON.');
        }

        $headers = [
            'Accept: application/json',
            'X-API-KEY: ' . $this->apiKey,
        ];
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        try {
            $result = $this->transport
                ? call_user_func($this->transport, $method, $url, $body, $headers)
                : $this->curlRequest($method, $url, $body, $headers);
        } catch (Throwable $exception) {
            $this->log('error', 'X-Link transport request failed.', [
                'method' => $method,
                'path' => $path,
                'request' => self::sanitizeForLog($payload),
                'error' => $exception->getMessage(),
            ]);
            throw new XLinkApiException('X-Link transport request failed.', 0, [], true, $exception);
        }

        $httpStatus = (int)($result['status'] ?? 0);
        $raw = isset($result['body']) && is_string($result['body']) ? $result['body'] : '';
        $decoded = $raw === '' ? [] : json_decode($raw, true);
        $response = is_array($decoded) ? $decoded : ['raw' => $raw];
        $ambiguous = $this->isAmbiguousResponse($httpStatus, $result, $decoded);

        $this->log($httpStatus >= 200 && $httpStatus < 300 && is_array($decoded) ? 'info' : 'warning', 'X-Link outbound request.', [
            'method' => $method,
            'path' => $path,
            'httpStatus' => $httpStatus,
            'curlError' => trim((string)($result['error'] ?? '')) ?: null,
            'request' => self::sanitizeForLog($payload),
            'response' => self::sanitizeForLog($response),
        ]);

        if (!empty($result['error']) || $httpStatus < 200 || $httpStatus >= 300) {
            throw new XLinkApiException(
                'X-Link request failed' . ($httpStatus > 0 ? ' (HTTP ' . $httpStatus . ')' : '.') ,
                $httpStatus,
                self::sanitizeForLog($response),
                $ambiguous
            );
        }
        if (!is_array($decoded)) {
            throw new XLinkApiException(
                'X-Link returned an invalid JSON response.',
                $httpStatus,
                self::sanitizeForLog($response),
                true
            );
        }

        return $decoded;
    }

    private function curlRequest(string $method, string $url, ?string $body, array $headers): array {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize cURL for X-Link request.');
        }

        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($handle, CURLOPT_TIMEOUT, 30);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);
        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($handle);
        $errorNumber = curl_errno($handle);
        $error = curl_error($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        return [
            'status' => $status,
            'body' => is_string($raw) ? $raw : '',
            'error' => $errorNumber !== 0 ? $error : '',
            'errorNumber' => $errorNumber,
        ];
    }

    private function extractPaymentMethodTypes(array $response): array {
        $candidates = [
            $response['data'] ?? null,
            $response['payment_method_types'] ?? null,
            $response['data']['payment_method_types'] ?? null,
            $response['data']['items'] ?? null,
            $response['items'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            if (self::isListArray($candidate)) {
                return $candidate;
            }
        }
        return [];
    }

    private function isEligiblePaymentMethod(array $method, string $operationType, string $amount, string $currency): bool {
        $id = trim((string)($method['id'] ?? $method['payment_method_type_id'] ?? ''));
        // The method-types response is filtered by operation_type and its
        // documented schema does not repeat that field. Honor it when a
        // provider response includes it, otherwise trust the request filter.
        $methodOperationType = self::normalizeOperationType((string)($method['operation_type'] ?? $method['operationType'] ?? ''));
        $methodCurrency = strtoupper(trim((string)($method['currency'] ?? '')));
        $instrument = strtoupper(trim((string)($method['instrument_type'] ?? $method['instrument'] ?? $method['payment_method'] ?? $method['type'] ?? '')));
        if ($id === ''
            || ($methodOperationType !== '' && $methodOperationType !== $operationType)
            || $methodCurrency !== $currency
            || $instrument !== self::INSTRUMENT_BANK_TRANSFER
        ) {
            return false;
        }

        $numericAmount = (float)$amount;
        $minimum = $method['min_amount'] ?? $method['minAmount'] ?? $method['minimum_amount'] ?? null;
        $maximum = $method['max_amount'] ?? $method['maxAmount'] ?? $method['maximum_amount'] ?? null;
        if ($minimum !== null && $minimum !== '' && $numericAmount < (float)$minimum) {
            return false;
        }
        if ($maximum !== null && $maximum !== '' && $numericAmount > (float)$maximum) {
            return false;
        }
        return true;
    }

    private function validateOperationPayload(array $payload, string $operationType): void {
        $operationNumber = trim((string)($payload['operation_number'] ?? ''));
        if ($operationNumber === '') {
            throw new InvalidArgumentException('X-Link operation_number is required.');
        }
        if (self::normalizeOperationType((string)($payload['operation_type'] ?? '')) !== $operationType) {
            throw new InvalidArgumentException('X-Link operation_type must be ' . $operationType . '.');
        }
        if (strtoupper(trim((string)($payload['currency'] ?? ''))) !== self::DEFAULT_CURRENCY) {
            throw new InvalidArgumentException('X-Link currency must be KRW.');
        }
    }

    private function requireRequestConfiguration(): void {
        if ($this->apiKey === '' || $this->baseUrl === '') {
            throw new RuntimeException('X-Link API key or base URL is not configured.');
        }
        $this->requireShopId();
    }

    private function requireShopId(): int {
        if (!ctype_digit($this->shopId) || (int)$this->shopId <= 0) {
            throw new RuntimeException('X-Link shop_id is not configured.');
        }
        return (int)$this->shopId;
    }

    private function buildUrl(string $path, array $query): string {
        $path = '/' . ltrim($path, '/');
        $url = $this->resolveRequestBaseUrl($path) . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        return $url;
    }

    private function resolveRequestBaseUrl(string $path): string {
        if ($path !== '/public/payment-links') {
            return $this->baseUrl;
        }

        return (string)preg_replace('#/p2p$#i', '', $this->baseUrl);
    }

    private function isAmbiguousResponse(int $httpStatus, array $result, $decoded): bool {
        if (!empty($result['error'])) {
            return true;
        }
        if ($httpStatus === 408 || $httpStatus === 429 || $httpStatus >= 500) {
            return true;
        }
        return $httpStatus >= 200 && $httpStatus < 300 && !is_array($decoded);
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

    private function log(string $level, string $message, array $context): void {
        $context = array_merge(['domain' => 'payments', 'provider' => self::PROVIDER_KEY], $context);
        if ($level === 'error') {
            Logger::error($message, $context);
            return;
        }
        if ($level === 'warning') {
            Logger::warning($message, $context);
            return;
        }
        Logger::info($message, $context);
    }

    private static function normalizeOperationType(string $operationType): string {
        $operationType = strtoupper(trim($operationType));
        return in_array($operationType, ['PAYIN', 'PAYOUT'], true) ? $operationType : '';
    }

    private static function optionalAmount($amount): ?string {
        if ($amount === null || $amount === '') {
            return null;
        }
        return self::normalizeKrwAmount($amount);
    }

    private static function extractPayerRequisiteTemplates(array $sessionData): array {
        $candidates = [
            $sessionData['payer_requisites'] ?? null,
            $sessionData['payerRequisites'] ?? null,
            is_array($sessionData['data'] ?? null) ? ($sessionData['data']['payer_requisites'] ?? null) : null,
            is_array($sessionData['data'] ?? null) ? ($sessionData['data']['payerRequisites'] ?? null) : null,
        ];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate) || $candidate === []) {
                continue;
            }
            if (self::isListArray($candidate)) {
                return $candidate;
            }
            $templates = [];
            foreach ($candidate as $name => $spec) {
                if (is_array($spec)) {
                    $templates[] = array_merge(['name' => (string)$name], $spec);
                    continue;
                }
                $templates[] = [
                    'name' => (string)$name,
                    'value' => is_scalar($spec) ? (string)$spec : '',
                    'type' => 'string',
                ];
            }
            if ($templates !== []) {
                return $templates;
            }
        }

        return [];
    }

    private static function lookupRequisiteValue(array $values, string $name): string {
        if (array_key_exists($name, $values)) {
            return trim((string)$values[$name]);
        }
        $snake = strtolower((string)preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));
        if ($snake !== $name && array_key_exists($snake, $values)) {
            return trim((string)$values[$snake]);
        }
        return '';
    }

    private static function humanizeRequisiteName(string $name): string {
        return ucwords(str_replace('_', ' ', $name));
    }

    private static function isListArray(array $value): bool {
        if ($value === []) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }
}
