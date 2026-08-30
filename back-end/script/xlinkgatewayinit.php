<?php
/**
 * X-Link KRW gateway bootstrap initializer.
 *
 * This script only provisions local gateway configuration. It does not call
 * X-Link, register callback routes, or create payment-method type IDs.
 *
 * Usage:
 *   php script/xlinkgatewayinit.php --shop-id=SHOP_ID --api-key=API_KEY --secret-key=SECRET_KEY
 *   php script/xlinkgatewayinit.php --shop-id=SHOP_ID --api-key=API_KEY --secret-key=SECRET_KEY \
 *       --environment=production --base-url=https://api.x-link.asia/api/v1/p2p \
 *       --callback-url=https://example.com/index.php?path=api/callback/xlink/status \
 *       --return-base-url=https://example.com
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplate.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionCategory.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestion.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionOption.php';
require_once __DIR__ . '/../models/PaymentSupportQuestion.php';
require_once __DIR__ . '/../services/XLinkService.php';

const XLINK_PROVIDER_LABEL = 'X-Link';
const XLINK_PROVIDER_KEY = 'xlink';
const XLINK_GATEWAY_KEY = 'xlink-krw';
const XLINK_GATEWAY_NAME = 'X-Link KRW';
const XLINK_TEMPLATE_CATEGORY = 'Bank Account Details';
const XLINK_DEFAULT_SANDBOX_BASE_URL = 'https://api.stage.x-link.asia/api/v1/p2p';
const XLINK_DEFAULT_PRODUCTION_BASE_URL = 'https://api.x-link.asia/api/v1/p2p';
const XLINK_DEFAULT_SANDBOX_CALLBACK_URL = 'https://devcrm.utrada.com/index.php?path=api/callback/xlink/status';
const XLINK_DEFAULT_SANDBOX_RETURN_BASE_URL = 'https://devcrm.utrada.com';
const XLINK_DEFAULT_DEPOSIT_FIXED_FEE = 0.00;
const XLINK_DEFAULT_WITHDRAWAL_FIXED_FEE = 0.00;

function xlinkOut($message): void {
    echo $message . PHP_EOL;
}

function getXLinkGatewayConfig(): array {
    return [
        'currency' => 'KRW',
        'country' => 'KR',
        'gatewayKey' => XLINK_GATEWAY_KEY,
        'gatewayName' => XLINK_GATEWAY_NAME,
        'methodName' => XLINK_GATEWAY_NAME,
        'shortCode' => 'KRW',
        'displayOrder' => 29,
        'amountDecimalPlaces' => 0
    ];
}

function getXLinkDepositQuestions(): array {
    return XLinkService::depositSupportQuestions();
}

function getXLinkWithdrawSupportQuestions(): array {
    $required = [
        ['name' => 'account_number', 'hintText' => 'Beneficiary bank account number.'],
        ['name' => 'account_name', 'hintText' => 'Beneficiary account holder name.'],
        ['name' => 'bank_province', 'hintText' => 'Beneficiary bank province.'],
        ['name' => 'bank_city', 'hintText' => 'Beneficiary bank city.'],
        ['name' => 'bank_code', 'hintText' => 'Beneficiary bank code.']
    ];
    $optional = [
        ['name' => 'bank_branch', 'hintText' => 'Beneficiary bank branch, when required by the payout channel.'],
        ['name' => 'customer_name', 'hintText' => 'Beneficiary given name, when required by the payout channel.'],
        ['name' => 'customer_lastname', 'hintText' => 'Beneficiary family name, when required by the payout channel.']
    ];

    $questions = [];
    foreach ($required as $field) {
        $questions[] = xlinkQuestion($field['name'], $field['hintText'], 'required|max:255');
    }
    foreach ($optional as $field) {
        $questions[] = xlinkQuestion($field['name'], $field['hintText'], 'max:255');
    }

    return $questions;
}

function getXLinkWithdrawalTemplateQuestions(): array {
    $questions = [];
    foreach (getXLinkWithdrawSupportQuestions() as $supportQuestion) {
        $required = strpos((string)$supportQuestion['validationRules'], 'required') !== false;
        $questions[] = [
            'scope' => $supportQuestion['name'],
            'questionText' => 'Enter the ' . str_replace('_', ' ', $supportQuestion['name']) . '.',
            'helpText' => $supportQuestion['hintText'],
            'questionType' => 'text',
            'validationRules' => $supportQuestion['validationRules'],
            'options' => null,
            'isRequired' => $required ? 1 : 0
        ];
    }

    return $questions;
}

function xlinkQuestion(string $name, string $hintText, string $validationRules): array {
    return [
        'name' => $name,
        'hintText' => $hintText,
        'questionType' => 'text',
        'validationRules' => $validationRules,
        'options' => null,
        'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
        'isLocked' => 1
    ];
}

function getXLinkCliOptions(): array {
    $options = getopt('', [
        'shop-id::',
        'api-key::',
        'secret-key::',
        'environment::',
        'base-url::',
        'callback-url::',
        'return-base-url::'
    ]);

    return [
        'shopId' => trim((string)($options['shop-id'] ?? '')),
        'apiKey' => trim((string)($options['api-key'] ?? '')),
        'secretKey' => trim((string)($options['secret-key'] ?? '')),
        'environment' => strtolower(trim((string)($options['environment'] ?? ''))),
        'baseUrl' => trim((string)($options['base-url'] ?? '')),
        'callbackUrl' => trim((string)($options['callback-url'] ?? '')),
        'returnBaseUrl' => rtrim(trim((string)($options['return-base-url'] ?? '')), '/')
    ];
}

/**
 * Build database-ready gateway/config data without opening a database.
 * Existing values are used whenever the corresponding CLI value is omitted.
 */
function buildXLinkGatewayPayload(
    array $config,
    array $cli,
    array $existingGateway = [],
    array $existingConfig = []
): array {
    $currency = strtoupper(trim((string)($config['currency'] ?? 'KRW')));
    $gatewayKey = trim((string)($config['gatewayKey'] ?? XLINK_GATEWAY_KEY));
    $gatewayName = trim((string)($config['gatewayName'] ?? XLINK_GATEWAY_NAME));
    $methodName = trim((string)($config['methodName'] ?? $gatewayName));
    $shortCode = trim((string)($config['shortCode'] ?? $currency));
    $displayOrder = (int)($config['displayOrder'] ?? 29);
    $amountDecimalPlaces = (int)($config['amountDecimalPlaces'] ?? 0);

    $apiKey = xlinkCliString($cli, 'apiKey', 'api-key');
    if ($apiKey === '') {
        $apiKey = trim((string)($existingGateway['apiKey'] ?? ''));
    }

    $secretKey = xlinkCliString($cli, 'secretKey', 'secret-key');
    if ($secretKey === '') {
        $secretKey = trim((string)($existingGateway['secretKey'] ?? ''));
    }

    $shopIdRaw = xlinkCliString($cli, 'shopId', 'shop-id');
    if ($shopIdRaw === '') {
        $shopIdRaw = (string)($existingConfig['shop_id'] ?? '');
    }
    $shopId = $shopIdRaw !== '' && ctype_digit($shopIdRaw) ? (int)$shopIdRaw : null;

    $cliEnvironment = strtolower(xlinkCliString($cli, 'environment'));
    $existingBaseUrl = trim((string)($existingConfig['base_url'] ?? ''));
    $baseUrlArgument = xlinkCliString($cli, 'baseUrl', 'base-url');
    $environment = $cliEnvironment !== ''
        ? xlinkNormalizeEnvironment($cliEnvironment)
        : strtolower(trim((string)($existingGateway['environment'] ?? 'sandbox')));
    $environment = xlinkNormalizeEnvironment($environment);

    if ($baseUrlArgument !== '') {
        $baseUrl = $baseUrlArgument;
    } elseif ($cliEnvironment === '' && $existingBaseUrl !== '') {
        $baseUrl = $existingBaseUrl;
    } else {
        $baseUrl = xlinkDefaultBaseUrl($environment);
    }

    $callbackUrlArgument = xlinkCliString($cli, 'callbackUrl', 'callback-url');
    $existingCallbackUrl = trim((string)($existingConfig['callback_url'] ?? ''));
    if ($callbackUrlArgument !== '') {
        $callbackUrl = $callbackUrlArgument;
    } elseif ($existingCallbackUrl !== '') {
        $callbackUrl = $existingCallbackUrl;
    } else {
        $callbackUrl = xlinkDefaultCallbackUrl($environment);
    }

    $returnBaseUrlArgument = rtrim(xlinkCliString($cli, 'returnBaseUrl', 'return-base-url'), '/');
    $existingReturnBaseUrl = rtrim(trim((string)($existingConfig['return_base_url'] ?? '')), '/');
    if ($returnBaseUrlArgument !== '') {
        $returnBaseUrl = $returnBaseUrlArgument;
    } elseif ($existingReturnBaseUrl !== '') {
        $returnBaseUrl = $existingReturnBaseUrl;
    } else {
        $returnBaseUrl = xlinkDefaultReturnBaseUrl($environment);
    }

    $configData = [
        'providerKey' => XLINK_PROVIDER_KEY,
        'shop_id' => $shopId,
        'base_url' => $baseUrl,
        'callback_url' => $callbackUrl,
        'return_base_url' => $returnBaseUrl,
        'currency' => $currency
    ];

    return [
        'gatewayData' => [
            'gatewayKey' => $gatewayKey,
            'gatewayName' => $gatewayName,
            'iconClass' => 'fas fa-won-sign',
            'integration' => 1,
            'isEnabled' => xlinkIsRuntimeReady($shopId, $apiKey, $secretKey, $callbackUrl, $returnBaseUrl) ? 1 : 0,
            'isDepositEnabled' => 1,
            'isWithdrawalEnabled' => 1,
            'processingTime' => 'Instant - 1 business day',
            'environment' => $environment,
            'appId' => null,
            'apiKey' => $apiKey !== '' ? $apiKey : null,
            'secretKey' => $secretKey !== '' ? $secretKey : null,
            'merchantName' => strtolower(XLINK_PROVIDER_LABEL),
            'webhookUrl' => null,
            'returnUrl' => null,
            'supportedFiatCurrencies' => json_encode([$currency], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'supportedCryptoCurrencies' => null,
            'amountDecimalPlaces' => $amountDecimalPlaces,
            'configData' => json_encode($configData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updatedBy' => null
        ],
        'configData' => $configData,
        'paymentMethodData' => [
            'methodKey' => $gatewayKey,
            'methodName' => $methodName,
            'methodType' => 'fiat',
            'iconClass' => 'fas fa-won-sign',
            'shortCode' => $shortCode,
            'networkName' => XLINK_PROVIDER_LABEL,
            'country' => (string)($config['country'] ?? 'KR'),
            'minPurchaseAmount' => null,
            'maxPurchaseAmount' => null,
            'isDepositEnabled' => 1,
            'isWithdrawalEnabled' => 1,
            'processingTime' => 'Instant - 1 business day',
            'displayOrder' => $displayOrder
        ]
    ];
}

function xlinkCliString(array $cli, string $key, string $alias = ''): string {
    $snakeKey = preg_replace('/([A-Z])/', '_$1', $key);
    $snakeKey = strtolower((string)$snakeKey);
    $value = $cli[$key]
        ?? ($alias !== '' ? ($cli[$alias] ?? ($cli[$snakeKey] ?? '')) : ($cli[$snakeKey] ?? ''));
    return trim((string)$value);
}

function xlinkNormalizeEnvironment(string $environment): string {
    if ($environment === 'production' || $environment === 'prod') {
        return 'production';
    }
    return 'sandbox';
}

function xlinkDefaultBaseUrl(string $environment): string {
    return xlinkNormalizeEnvironment($environment) === 'production'
        ? XLINK_DEFAULT_PRODUCTION_BASE_URL
        : XLINK_DEFAULT_SANDBOX_BASE_URL;
}

function xlinkDefaultCallbackUrl(string $environment): string {
    return xlinkNormalizeEnvironment($environment) === 'sandbox'
        ? XLINK_DEFAULT_SANDBOX_CALLBACK_URL
        : '';
}

function xlinkDefaultReturnBaseUrl(string $environment): string {
    return xlinkNormalizeEnvironment($environment) === 'sandbox'
        ? XLINK_DEFAULT_SANDBOX_RETURN_BASE_URL
        : '';
}

function xlinkIsRuntimeReady($shopId, string $apiKey, string $secretKey, string $callbackUrl, string $returnBaseUrl): bool {
    return $shopId !== null
        && (int)$shopId > 0
        && $apiKey !== ''
        && $secretKey !== ''
        && $callbackUrl !== ''
        && $returnBaseUrl !== '';
}

function runXLinkGatewayInit(array $config, array $cli): array {
    $gatewayKey = trim((string)($config['gatewayKey'] ?? XLINK_GATEWAY_KEY));
    $gatewayName = trim((string)($config['gatewayName'] ?? XLINK_GATEWAY_NAME));
    if ($gatewayKey === '' || $gatewayName === '') {
        throw new InvalidArgumentException('X-Link config is incomplete.');
    }

    $db = Database::getInstance();
    $templateModel = new WithdrawalVerificationTemplate();
    $categoryModel = new WithdrawalVerificationQuestionCategory();
    $questionModel = new WithdrawalVerificationQuestion();
    $questionOptionModel = new WithdrawalVerificationQuestionOption();
    $paymentSupportQuestionModel = new PaymentSupportQuestion();

    try {
        $db->beginTransaction();
        xlinkOut('=== ' . $gatewayName . ' Init ===');

        $gateway = $db->fetchOne(
            'SELECT * FROM paymentGatewaySettings WHERE gatewayKey = :gatewayKey LIMIT 1',
            ['gatewayKey' => $gatewayKey]
        );
        $existingConfig = [];
        if ($gateway && !empty($gateway['configData'])) {
            $decoded = json_decode((string)$gateway['configData'], true);
            if (is_array($decoded)) {
                $existingConfig = $decoded;
            }
        }

        $payload = buildXLinkGatewayPayload($config, $cli, $gateway ?: [], $existingConfig);
        $shopId = $payload['configData']['shop_id'];
        if ($shopId === null || $shopId <= 0) {
            throw new InvalidArgumentException('A positive numeric --shop-id is required.');
        }

        if (!$gateway) {
            $gatewayId = $db->insert('paymentGatewaySettings', $payload['gatewayData']);
            $gateway = $db->fetchOne('SELECT * FROM paymentGatewaySettings WHERE id = :id LIMIT 1', ['id' => $gatewayId]);
            xlinkOut('[OK] paymentGatewaySettings inserted: id=' . $gatewayId);
        } else {
            $db->update('paymentGatewaySettings', $payload['gatewayData'], 'id = :id', ['id' => $gateway['id']]);
            $gateway = $db->fetchOne('SELECT * FROM paymentGatewaySettings WHERE id = :id LIMIT 1', ['id' => $gateway['id']]);
            xlinkOut('[SKIP] paymentGatewaySettings already exists, normalized: id=' . $gateway['id']);
        }

        $funding = $db->fetchOne(
            'SELECT * FROM paymentGatewayFundingSettings WHERE gatewaySettingId = :gatewaySettingId LIMIT 1',
            ['gatewaySettingId' => $gateway['id']]
        );
        $fundingData = [
            'calculationMode' => 'single',
            'minDeposit' => null,
            'maxDeposit' => null,
            'minWithdrawal' => null,
            'maxWithdrawal' => null,
            'isActive' => 1,
            'notes' => 'Initialized by X-Link KRW bootstrap script.',
            'updatedBy' => null
        ];
        if (!$funding) {
            $fundingId = $db->insert('paymentGatewayFundingSettings', array_merge(
                ['gatewaySettingId' => (int)$gateway['id']],
                $fundingData
            ));
            xlinkOut('[OK] paymentGatewayFundingSettings inserted: id=' . $fundingId);
        } else {
            $db->update('paymentGatewayFundingSettings', $fundingData, 'id = :id', ['id' => $funding['id']]);
            $fundingId = (int)$funding['id'];
            xlinkOut('[SKIP] paymentGatewayFundingSettings normalized: id=' . $fundingId);
        }

        $db->delete('paymentGatewayFeeRules', 'gatewayFundingSettingId = :gatewayFundingSettingId', [
            'gatewayFundingSettingId' => $fundingId
        ]);
        foreach ([['transactionType' => 'deposit', 'fixed' => XLINK_DEFAULT_DEPOSIT_FIXED_FEE], ['transactionType' => 'withdrawal', 'fixed' => XLINK_DEFAULT_WITHDRAWAL_FIXED_FEE]] as $fee) {
            $db->insert('paymentGatewayFeeRules', [
                'gatewayFundingSettingId' => $fundingId,
                'transactionType' => $fee['transactionType'],
                'thresholdAmount' => 0,
                'feeMode' => 'none',
                'percentage' => 0,
                'fixed' => $fee['fixed'],
                'minFee' => null,
                'maxFee' => null,
                'chargeToClient' => 1,
                'sortOrder' => 0,
                'isActive' => 1
            ]);
        }

        $paymentMethod = $db->fetchOne('SELECT * FROM paymentMethods WHERE methodKey = :methodKey LIMIT 1', [
            'methodKey' => $gatewayKey
        ]);
        if (!$paymentMethod) {
            $paymentMethodId = $db->insert('paymentMethods', $payload['paymentMethodData']);
            $paymentMethod = $db->fetchOne('SELECT * FROM paymentMethods WHERE id = :id LIMIT 1', ['id' => $paymentMethodId]);
            xlinkOut('[OK] paymentMethods inserted: id=' . $paymentMethodId);
        } else {
            $db->update('paymentMethods', $payload['paymentMethodData'], 'id = :id', ['id' => $paymentMethod['id']]);
            $paymentMethod = $db->fetchOne('SELECT * FROM paymentMethods WHERE id = :id LIMIT 1', ['id' => $paymentMethod['id']]);
            xlinkOut('[SKIP] paymentMethods normalized: id=' . $paymentMethod['id']);
        }

        $templateName = XLINK_GATEWAY_NAME . ' Withdrawal Verification';
        $template = $templateModel->findOne([
            'gatewaySettingId' => $gateway['id'],
            'templateName' => $templateName
        ]);
        $templateData = [
            'gatewaySettingId' => (int)$gateway['id'],
            'templateName' => $templateName,
            'description' => 'Locked X-Link KRW bank withdrawal fields.',
            'status' => 'active',
            'isAutoApproveEnabled' => 0,
            'requireDocumentSignature' => 0,
            'displayOrder' => 1,
            'createdBy' => null,
            'updatedBy' => null
        ];
        if (!$template) {
            $templateId = $templateModel->create($templateData);
            $template = $templateModel->findById($templateId);
            xlinkOut('[OK] withdrawalVerificationTemplates inserted: id=' . $templateId);
        } else {
            $templateModel->update($template['id'], $templateData);
            $template = $templateModel->findById($template['id']);
            xlinkOut('[SKIP] withdrawalVerificationTemplates normalized: id=' . $template['id']);
        }

        $category = $categoryModel->findOne([
            'templateId' => $template['id'],
            'categoryName' => XLINK_TEMPLATE_CATEGORY
        ]);
        $categoryData = [
            'templateId' => (int)$template['id'],
            'categoryName' => XLINK_TEMPLATE_CATEGORY,
            'description' => 'Locked X-Link KRW bank withdrawal fields.',
            'displayOrder' => 1,
            'isExpanded' => 1,
            'isActive' => 1,
            'isLocked' => 1
        ];
        if (!$category) {
            $categoryId = $categoryModel->createCategory($categoryData);
            $category = $categoryModel->findById($categoryId);
            xlinkOut('[OK] withdrawalVerificationQuestionCategories inserted: id=' . $categoryId);
        } else {
            $categoryModel->update($category['id'], $categoryData);
            $category = $categoryModel->findById($category['id']);
            xlinkOut('[SKIP] withdrawalVerificationQuestionCategories normalized: id=' . $category['id']);
        }

        foreach (getXLinkWithdrawalTemplateQuestions() as $index => $questionSpec) {
            $existingQuestion = $questionModel->findOne([
                'templateId' => $template['id'],
                'categoryId' => $category['id'],
                'scope' => $questionSpec['scope']
            ]);
            $questionData = [
                'templateId' => (int)$template['id'],
                'categoryId' => (int)$category['id'],
                'questionNumber' => $index + 1,
                'questionText' => $questionSpec['questionText'],
                'helpText' => $questionSpec['helpText'],
                'questionType' => $questionSpec['questionType'],
                'scope' => $questionSpec['scope'],
                'validationRules' => $questionSpec['validationRules'],
                'isRequired' => $questionSpec['isRequired'],
                'isActive' => 1,
                'isLocked' => 1,
                'displayOrder' => $index + 1,
                'metadata' => null,
                'updatedBy' => null
            ];
            if (!$existingQuestion) {
                $questionId = $questionModel->createQuestion($questionData);
            } else {
                $questionId = (int)$existingQuestion['id'];
                $questionModel->update($questionId, $questionData);
            }
            $questionOptionModel->updateQuestionOptions($questionId, $questionSpec['options'] ?? []);
        }

        $templateModel->update($template['id'], [
            'totalQuestions' => $questionModel->count(['templateId' => $template['id']]),
            'totalRules' => 0,
            'updatedBy' => null
        ]);

        $supportQuestions = array_merge(getXLinkDepositQuestions(), getXLinkWithdrawSupportQuestions());
        $desiredKeys = [];
        foreach ($supportQuestions as $supportQuestion) {
            $key = $supportQuestion['name'] . '|' . $supportQuestion['scope'];
            $desiredKeys[] = $key;
            $existingSupportQuestion = $paymentSupportQuestionModel->findOne([
                'paymentGatewayId' => $gateway['id'],
                'name' => $supportQuestion['name'],
                'scope' => $supportQuestion['scope']
            ]);
            $supportData = [
                'paymentGatewayId' => (int)$gateway['id'],
                'name' => $supportQuestion['name'],
                'hintText' => $supportQuestion['hintText'],
                'questionType' => $supportQuestion['questionType'],
                'validationRules' => $supportQuestion['validationRules'],
                'options' => $supportQuestion['options'],
                'scope' => $supportQuestion['scope'],
                'isLocked' => $supportQuestion['isLocked'],
                'isActive' => 1,
                'updatedBy' => null
            ];
            if (!$existingSupportQuestion) {
                $paymentSupportQuestionModel->createQuestion($supportData);
            } else {
                $paymentSupportQuestionModel->updateQuestion($existingSupportQuestion['id'], $supportData);
            }
        }

        foreach ($paymentSupportQuestionModel->getAdminQuestions((int)$gateway['id']) as $existingSupportQuestion) {
            $key = ($existingSupportQuestion['name'] ?? '') . '|' . ($existingSupportQuestion['scope'] ?? '');
            if (!in_array($key, $desiredKeys, true)) {
                $paymentSupportQuestionModel->delete((int)$existingSupportQuestion['id']);
            }
        }

        $db->commit();
        xlinkOut('[DONE] X-Link KRW bootstrap finished successfully.');
        xlinkOut('Gateway ID: ' . $gateway['id']);
        xlinkOut('Payment Method ID: ' . $paymentMethod['id']);
        xlinkOut('Template ID: ' . $template['id']);
        xlinkOut('Category ID: ' . $category['id']);
        if (!xlinkIsRuntimeReady(
            $payload['configData']['shop_id'],
            (string)($gateway['apiKey'] ?? ''),
            (string)($gateway['secretKey'] ?? ''),
            (string)($payload['configData']['callback_url'] ?? ''),
            (string)($payload['configData']['return_base_url'] ?? '')
        )) {
            xlinkOut('[NOTE] Gateway runtime configuration is incomplete; it remains disabled until shop ID, API key, secret key, callback URL, and return base URL are stored.');
        }

        return [
            'gatewayId' => (int)$gateway['id'],
            'paymentMethodId' => (int)$paymentMethod['id'],
            'templateId' => (int)$template['id'],
            'categoryId' => (int)$category['id'],
            'credentialsComplete' => xlinkIsRuntimeReady(
                $payload['configData']['shop_id'],
                (string)($gateway['apiKey'] ?? ''),
                (string)($gateway['secretKey'] ?? ''),
                (string)($payload['configData']['callback_url'] ?? ''),
                (string)($payload['configData']['return_base_url'] ?? '')
            )
        ];
    } catch (Throwable $e) {
        if ($db->getConnection()->inTransaction()) {
            $db->rollback();
        }
        throw $e;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        runXLinkGatewayInit(getXLinkGatewayConfig(), getXLinkCliOptions());
        exit(0);
    } catch (Throwable $e) {
        xlinkOut('[ERROR] ' . $e->getMessage());
        if ($e instanceof InvalidArgumentException) {
            xlinkOut('Usage: php xlinkgatewayinit.php --shop-id=SHOP_ID --api-key=API_KEY --secret-key=SECRET_KEY [--environment=sandbox|production] [--base-url=URL] [--callback-url=URL] [--return-base-url=URL]');
        }
        exit(1);
    }
}
