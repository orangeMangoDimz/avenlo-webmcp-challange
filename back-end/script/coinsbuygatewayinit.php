<?php
/**
 * Coinsbuy gateway bootstrap initializer
 *
 * 用法：
 *   php script/coinsbuygatewayinit.php
 *
 * Coinsbuy 入金 / 出金都启用，币种 USDT。
 *   - 创建 withdrawalVerificationTemplate / Category / Question
 *   - paymentSupportQuestions 当前只配置了 withdraw scope（出金需要收款方信息）
 *   - deposit fee 与 withdrawal fee 各占位一条 0 费率，后续按需调整
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplate.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionCategory.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestion.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionOption.php';
require_once __DIR__ . '/../models/PaymentSupportQuestion.php';

const COINSBUY_GATEWAY_KEY = 'coinsbuy';
const COINSBUY_TEMPLATE_NAME = 'Coinsbuy USDT Withdrawal Verification';
const COINSBUY_TEMPLATE_DESCRIPTION = 'Locked fields for Coinsbuy USDT withdrawal verification.';
const COINSBUY_CATEGORY_NAME = 'Account & Address Details';
const COINSBUY_DEFAULT_WITHDRAWAL_FIXED_FEE = 0.00;
const COINSBUY_DEFAULT_DEPOSIT_FIXED_FEE = 0.00;
const COINSBUY_INACCURACY = '2';

// Coinsbuy credentials are supplied through the environment and never stored in source control.
$coinsbuyClientId = trim((string)config_env('COINSBUY_CLIENT_ID', ''));
$coinsbuyClientSecret = trim((string)config_env('COINSBUY_CLIENT_SECRET', ''));
$coinsbuyWalletId = trim((string)config_env('COINSBUY_WALLET_ID', ''));
$coinsbuyCurrencyDeposit = trim((string)config_env('COINSBUY_CURRENCY_DEPOSIT', '5003'));
$coinsbuyCurrencyWithdraw = trim((string)config_env('COINSBUY_CURRENCY_WITHDRAW', '5003'));
$coinsbuyApiBaseUrl = rtrim(trim((string)config_env('COINSBUY_API_BASE_URL', 'https://dev.willcode.io')), '/');
$coinsbuyCallbackSecret = trim((string)config_env('COINSBUY_CALLBACK_SECRET', ''));

if ($coinsbuyClientId === '' || $coinsbuyClientSecret === '' || $coinsbuyWalletId === '' || $coinsbuyCallbackSecret === '') {
    throw new RuntimeException('Coinsbuy credentials are required in the environment before running this initializer.');
}

function out($message) {
    echo $message . PHP_EOL;
}

/**
 * 加载 ISO 3166-1 alpha-2 国家选项（复用 Payment Asia 的 pa-na.json，247 个国家两位代码）
 * Coinsbuy travel_rule_info.geographicAddress.country 要求两位 ISO code，所以用统一的国家文件
 *
 * @param string $shape 'verification' 用于 withdrawalVerificationQuestionOptions 表（optionValue/optionLabel）
 *                      'support'      用于 paymentSupportQuestions.options（value/label，PA loader 同款）
 */
function loadCoinsbuyCountryOptions(string $shape): array {
    static $cache = [];
    if (isset($cache[$shape])) {
        return $cache[$shape];
    }

    $path = __DIR__ . '/support/pa-na.json';
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Country options file not found: ' . $path);
    }
    $decoded = json_decode((string)file_get_contents($path), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Country options file must be a JSON array: ' . $path);
    }

    $out = [];
    foreach ($decoded as $opt) {
        if (!is_array($opt)) {
            continue;
        }
        $value = trim((string)($opt['value'] ?? ''));
        $label = trim((string)($opt['label'] ?? $value));
        if ($value === '') {
            continue;
        }
        $out[] = $shape === 'verification'
            ? ['optionValue' => $value, 'optionLabel' => $label]
            : ['value' => $value, 'label' => $label];
    }
    return $cache[$shape] = $out;
}

$db = Database::getInstance();
$templateModel = new WithdrawalVerificationTemplate();
$categoryModel = new WithdrawalVerificationQuestionCategory();
$questionModel = new WithdrawalVerificationQuestion();
$questionOptionModel = new WithdrawalVerificationQuestionOption();
$paymentSupportQuestionModel = new PaymentSupportQuestion();

// 地址类型选项：value 是 Coinsbuy 接口要求的代码，label 是地址性质的描述
$addressTypeOptions = [
    ['optionLabel' => 'Residential', 'optionValue' => 'HOME'],
    ['optionLabel' => 'Business',    'optionValue' => 'BIZZ'],
    ['optionLabel' => 'Geographic',  'optionValue' => 'GEOG'],
];

// 标准地址格式（参照澳洲）：address1 + address2 + city + state + postcode + country
// withdrawalVerificationQuestions —— 出金前的客户问题（带 questionText / helpText 的完整版）
$questions = [
    [
        'scope' => 'wallet_address',
        'questionText' => 'What is your USDT receiving wallet address?',
        'helpText' => 'Enter the destination wallet address that will receive the USDT payout.',
        'questionType' => 'text',
        'validationRules' => 'required|min:8|max:255',
        'options' => null,
    ],
    [
        'scope' => 'first_name',
        'questionText' => 'What is your first name?',
        'helpText' => 'Use the first name registered with Coinsbuy.',
        'questionType' => 'text',
        'validationRules' => 'required|min:1|max:100',
        'options' => null,
    ],
    [
        'scope' => 'last_name',
        'questionText' => 'What is your last name?',
        'helpText' => 'Use the last name registered with Coinsbuy.',
        'questionType' => 'text',
        'validationRules' => 'required|min:1|max:100',
        'options' => null,
    ],
    [
        'scope' => 'address1',
        'questionText' => 'What is your address line 1?',
        'helpText' => 'Street number and name.',
        'questionType' => 'text',
        'validationRules' => 'required|max:255',
        'options' => null,
    ],
    [
        'scope' => 'address2',
        'questionText' => 'What is your address line 2?',
        'helpText' => 'Apartment / suite / unit (optional).',
        'questionType' => 'text',
        'validationRules' => 'max:255',
        'options' => null,
    ],
    [
        'scope' => 'city',
        'questionText' => 'What is your city / suburb?',
        'helpText' => 'City or suburb.',
        'questionType' => 'text',
        'validationRules' => 'required|max:100',
        'options' => null,
    ],
    [
        'scope' => 'state',
        'questionText' => 'What is your state / region?',
        'helpText' => 'State or region',
        'questionType' => 'text',
        'validationRules' => 'required|max:100',
        'options' => null,
    ],
    [
        'scope' => 'postcode',
        'questionText' => 'What is your postcode?',
        'helpText' => 'Postal / zip code.',
        'questionType' => 'text',
        'validationRules' => 'required|max:20',
        'options' => null,
    ],
    [
        'scope' => 'country',
        'questionText' => 'Which country is your address in?',
        'helpText' => 'Country',
        'questionType' => 'single_choice',
        'validationRules' => 'required',
        'options' => loadCoinsbuyCountryOptions('verification'),
    ],
    [
        'scope' => 'address_type',
        'questionText' => 'What type of address is this?',
        'helpText' => 'Residential / Business / Geographic. Coinsbuy expects the matching code.',
        'questionType' => 'single_choice',
        'validationRules' => 'required',
        'options' => $addressTypeOptions,
    ],
];

// paymentSupportQuestions —— gateway 级别的兜底问题（withdraw scope only）
$supportQuestions = [
    [
        'name' => 'wallet_address',
        'hintText' => 'USDT receiving wallet address.',
        'questionType' => 'text',
        'validationRules' => 'required|min:8|max:255',
        'options' => null,
    ],
    [
        'name' => 'firstname',
        'hintText' => 'First name on the Coinsbuy account.',
        'questionType' => 'text',
        'validationRules' => 'required|min:1|max:100',
        'options' => null,
    ],
    [
        'name' => 'lastname',
        'hintText' => 'Last name on the Coinsbuy account.',
        'questionType' => 'text',
        'validationRules' => 'required|min:1|max:100',
        'options' => null,
    ],
    [
        'name' => 'address1',
        'hintText' => 'Street number and name.',
        'questionType' => 'text',
        'validationRules' => 'required|max:255',
        'options' => null,
    ],
    [
        'name' => 'address2',
        'hintText' => 'Apartment / suite / unit (optional).',
        'questionType' => 'text',
        'validationRules' => 'max:255',
        'options' => null,
    ],
    [
        'name' => 'city',
        'hintText' => 'City / suburb.',
        'questionType' => 'text',
        'validationRules' => 'required|max:100',
        'options' => null,
    ],
    [
        'name' => 'state',
        'hintText' => 'State / region (e.g. NSW, VIC, QLD).',
        'questionType' => 'text',
        'validationRules' => 'required|max:100',
        'options' => null,
    ],
    [
        'name' => 'postcode',
        'hintText' => 'Postal / zip code.',
        'questionType' => 'text',
        'validationRules' => 'required|max:20',
        'options' => null,
    ],
    [
        'name' => 'country',
        'hintText' => 'Country',
        'questionType' => 'single_choice',
        'validationRules' => 'required',
        'options' => loadCoinsbuyCountryOptions('support'),
    ],
    [
        'name' => 'addressType',
        'hintText' => 'Address type: Residential / Business / Geographic.',
        'questionType' => 'single_choice',
        'validationRules' => 'required',
        // PaymentSupportQuestion::normalizeOptionsPayload 接受 {label, value} 形式
        'options' => [
            ['label' => 'Residential', 'value' => 'HOME'],
            ['label' => 'Business',    'value' => 'BIZZ'],
            ['label' => 'Geographic',  'value' => 'GEOG'],
        ],
    ],
];

try {
    $db->beginTransaction();

    out('=== Coinsbuy Gateway Init ===');

    // 1) paymentGatewaySettings
    $gateway = $db->fetchOne(
        "SELECT * FROM paymentGatewaySettings WHERE gatewayKey = :gatewayKey LIMIT 1",
        ['gatewayKey' => COINSBUY_GATEWAY_KEY]
    );

    $coinsbuyConfigData = json_encode(
        [
            'wallet_d' => $coinsbuyWalletId,
            'wallet_w' => $coinsbuyWalletId,
            'currency_d' => $coinsbuyCurrencyDeposit,
            'currency_w' => $coinsbuyCurrencyWithdraw,
            'inaccuracy' => COINSBUY_INACCURACY,
            'url' => $coinsbuyApiBaseUrl,
            'callback_secret' => $coinsbuyCallbackSecret,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if (!$gateway) {
        $gatewayId = $db->insert('paymentGatewaySettings', [
            'gatewayKey' => 'coinsbuy',
            'gatewayName' => 'Coinsbuy',
            'iconClass' => 'fas fa-coins',
            'integration' => 1,
            'type' => 'crypto',
            'isEnabled' => 1,
            'isDepositEnabled' => 1,
            'isWithdrawalEnabled' => 1,
            'processingTime' => '5-30 minutes',
            'environment' => 'production',
            'appId' => $coinsbuyClientId,
            'apiKey' => null,
            'secretKey' => $coinsbuyClientSecret,
            'merchantName' => 'Coinsbuy',
            'webhookUrl' => null,
            'returnUrl' => null,
            'supportedFiatCurrencies' => null,
            'supportedCryptoCurrencies' => '["USDT"]',
            'configData' => $coinsbuyConfigData,
            'updatedBy' => null,
        ]);

        $gateway = $db->fetchOne(
            "SELECT * FROM paymentGatewaySettings WHERE id = :id LIMIT 1",
            ['id' => $gatewayId]
        );
        out("[OK] paymentGatewaySettings inserted: id={$gatewayId}");
    } else {
        $db->update('paymentGatewaySettings', [
            'iconClass' => 'fas fa-coins',
            'integration' => 1,
            'type' => 'crypto',
            'isEnabled' => 1,
            'isDepositEnabled' => 1,
            'isWithdrawalEnabled' => 1,
            'processingTime' => '5-30 minutes',
            'gatewayName' => 'Coinsbuy',
            'merchantName' => 'Coinsbuy',
            'appId' => $coinsbuyClientId,
            'secretKey' => $coinsbuyClientSecret,
            'supportedCryptoCurrencies' => '["USDT"]',
            'configData' => $coinsbuyConfigData,
            'updatedBy' => null,
        ], 'id = :id', ['id' => $gateway['id']]);
        $gateway = $db->fetchOne(
            "SELECT * FROM paymentGatewaySettings WHERE id = :id LIMIT 1",
            ['id' => $gateway['id']]
        );
        out("[SKIP] paymentGatewaySettings already exists, normalized: id={$gateway['id']}");
    }

    // 2) paymentGatewayFundingSettings
    $gatewayFundingSetting = $db->fetchOne(
        "SELECT * FROM paymentGatewayFundingSettings WHERE gatewaySettingId = :gatewaySettingId LIMIT 1",
        ['gatewaySettingId' => $gateway['id']]
    );

    $gatewayFundingData = [
        'calculationMode' => 'single',
        'minDeposit' => null,
        'maxDeposit' => null,
        'minWithdrawal' => null,
        'maxWithdrawal' => null,
        'isActive' => 1,
        'notes' => 'Initialized by Coinsbuy bootstrap script.',
        'updatedBy' => null,
    ];

    if (!$gatewayFundingSetting) {
        $gatewayFundingSettingId = $db->insert('paymentGatewayFundingSettings', array_merge(
            ['gatewaySettingId' => (int)$gateway['id']],
            $gatewayFundingData
        ));
        out("[OK] paymentGatewayFundingSettings inserted: id={$gatewayFundingSettingId}");
    } else {
        $db->update(
            'paymentGatewayFundingSettings',
            $gatewayFundingData,
            'id = :id',
            ['id' => $gatewayFundingSetting['id']]
        );
        $gatewayFundingSettingId = (int)$gatewayFundingSetting['id'];
        out("[SKIP] paymentGatewayFundingSettings already exists, normalized: id={$gatewayFundingSettingId}");
    }

    // 3) 出金费率（仅 withdrawal；先用 0 占位）
    $db->delete(
        'paymentGatewayFeeRules',
        'gatewayFundingSettingId = :gatewayFundingSettingId',
        ['gatewayFundingSettingId' => $gatewayFundingSettingId]
    );

    $db->insert('paymentGatewayFeeRules', [
        'gatewayFundingSettingId' => $gatewayFundingSettingId,
        'transactionType' => 'deposit',
        'thresholdAmount' => 0,
        'feeMode' => 'none',
        'percentage' => 0,
        'fixed' => COINSBUY_DEFAULT_DEPOSIT_FIXED_FEE,
        'minFee' => null,
        'maxFee' => null,
        'chargeToClient' => 1,
        'sortOrder' => 0,
        'isActive' => 1,
    ]);

    $db->insert('paymentGatewayFeeRules', [
        'gatewayFundingSettingId' => $gatewayFundingSettingId,
        'transactionType' => 'withdrawal',
        'thresholdAmount' => 0,
        'feeMode' => 'none',
        'percentage' => 0,
        'fixed' => COINSBUY_DEFAULT_WITHDRAWAL_FIXED_FEE,
        'minFee' => null,
        'maxFee' => null,
        'chargeToClient' => 1,
        'sortOrder' => 0,
        'isActive' => 1,
    ]);

    // 4) paymentMethods 入口（仅 withdrawal）
    $paymentMethod = $db->fetchOne(
        "SELECT * FROM paymentMethods WHERE methodKey = :methodKey LIMIT 1",
        ['methodKey' => COINSBUY_GATEWAY_KEY]
    );

    if (!$paymentMethod) {
        $paymentMethodId = $db->insert('paymentMethods', [
            'methodKey' => 'coinsbuy',
            'methodName' => 'Coinsbuy',
            'methodType' => 'crypto',
            'iconClass' => 'fas fa-coins',
            'shortCode' => 'COINSBUY',
            'networkName' => 'Coinsbuy USDT',
            'country' => null,
            'minPurchaseAmount' => null,
            'maxPurchaseAmount' => null,
            'isDepositEnabled' => 1,
            'isWithdrawalEnabled' => 1,
            'processingTime' => '5-30 minutes',
            'displayOrder' => 1,
        ]);

        $paymentMethod = $db->fetchOne(
            "SELECT * FROM paymentMethods WHERE id = :id LIMIT 1",
            ['id' => $paymentMethodId]
        );
        out("[OK] paymentMethods inserted: id={$paymentMethodId}");
    } else {
        out("[SKIP] paymentMethods already exists: id={$paymentMethod['id']}");
    }

    // 5) withdrawalVerificationTemplate
    $template = $templateModel->findOne([
        'gatewaySettingId' => $gateway['id'],
        'templateName' => COINSBUY_TEMPLATE_NAME,
    ]);

    if (!$template) {
        $templateId = $templateModel->create([
            'gatewaySettingId' => (int)$gateway['id'],
            'templateName' => COINSBUY_TEMPLATE_NAME,
            'description' => COINSBUY_TEMPLATE_DESCRIPTION,
            'status' => 'active',
            'isAutoApproveEnabled' => 0,
            'requireDocumentSignature' => 0,
            'displayOrder' => 1,
            'createdBy' => null,
            'updatedBy' => null,
        ]);

        $template = $templateModel->findById($templateId);
        out("[OK] withdrawalVerificationTemplates inserted: id={$templateId}");
    } else {
        out("[SKIP] withdrawalVerificationTemplates already exists: id={$template['id']}");
    }

    // 6) withdrawalVerificationQuestionCategory
    $category = $categoryModel->findOne([
        'templateId' => $template['id'],
        'categoryName' => COINSBUY_CATEGORY_NAME,
    ]);

    if (!$category) {
        $categoryId = $categoryModel->createCategory([
            'templateId' => (int)$template['id'],
            'categoryName' => COINSBUY_CATEGORY_NAME,
            'description' => 'Coinsbuy account & address fields.',
            'displayOrder' => 1,
            'isExpanded' => 1,
            'isActive' => 1,
            'isLocked' => 1,
        ]);
        $category = $categoryModel->findById($categoryId);
        out("[OK] withdrawalVerificationQuestionCategories inserted: id={$categoryId}");
    } else {
        $categoryModel->update($category['id'], [
            'description' => 'Coinsbuy account & address fields.',
            'displayOrder' => 1,
            'isExpanded' => 1,
            'isActive' => 1,
            'isLocked' => 1,
        ]);
        $category = $categoryModel->findById($category['id']);
        out("[SKIP] withdrawalVerificationQuestionCategories already exists, normalized: id={$category['id']}");
    }

    // 7) withdrawalVerificationQuestions
    foreach ($questions as $index => $questionSpec) {
        $existing = $questionModel->findOne([
            'templateId' => $template['id'],
            'categoryId' => $category['id'],
            'scope' => $questionSpec['scope'],
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
            'isRequired' => strpos((string)$questionSpec['validationRules'], 'required') !== false ? 1 : 0,
            'isActive' => 1,
            'isLocked' => 1,
            'displayOrder' => $index + 1,
            'metadata' => null,
            'updatedBy' => null,
        ];

        if (!$existing) {
            $questionData['createdBy'] = null;
            $questionId = $questionModel->createQuestion($questionData);
            if (!empty($questionSpec['options'])) {
                $questionOptionModel->updateQuestionOptions($questionId, $questionSpec['options']);
            }
            out("[OK] question inserted: scope={$questionSpec['scope']} id={$questionId}");
        } else {
            $questionModel->update($existing['id'], $questionData);
            if ($questionSpec['questionType'] === 'single_choice') {
                $questionOptionModel->updateQuestionOptions($existing['id'], $questionSpec['options'] ?? []);
            }
            out("[SKIP] question already exists, normalized: scope={$questionSpec['scope']} id={$existing['id']}");
        }
    }

    $questionCount = $questionModel->count(['templateId' => $template['id']]);
    $templateModel->update($template['id'], [
        'totalQuestions' => $questionCount,
        'totalRules' => 0,
        'updatedBy' => null,
    ]);

    // 8) paymentSupportQuestions（withdraw scope only）
    $desiredSupportQuestionKeys = [];
    foreach ($supportQuestions as $supportQuestion) {
        $desiredSupportQuestionKeys[] = $supportQuestion['name'] . '|' . PaymentSupportQuestion::SCOPE_WITHDRAW;
    }

    foreach ($supportQuestions as $supportQuestion) {
        $existingSupportQuestion = $paymentSupportQuestionModel->findOne([
            'paymentGatewayId' => $gateway['id'],
            'name' => $supportQuestion['name'],
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
        ]);

        if (!$existingSupportQuestion) {
            $supportQuestionId = $paymentSupportQuestionModel->createQuestion([
                'paymentGatewayId' => (int)$gateway['id'],
                'name' => $supportQuestion['name'],
                'hintText' => $supportQuestion['hintText'],
                'questionType' => $supportQuestion['questionType'],
                'validationRules' => $supportQuestion['validationRules'],
                'options' => $supportQuestion['options'],
                'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
                'isLocked' => 1,
                'isActive' => 1,
                'updatedBy' => null,
            ]);
            out("[OK] paymentSupportQuestions inserted: name={$supportQuestion['name']} scope=withdraw id={$supportQuestionId}");
        } else {
            $paymentSupportQuestionModel->updateQuestion($existingSupportQuestion['id'], [
                'hintText' => $supportQuestion['hintText'],
                'questionType' => $supportQuestion['questionType'],
                'validationRules' => $supportQuestion['validationRules'],
                'options' => $supportQuestion['options'],
                'isLocked' => 1,
                'isActive' => 1,
                'updatedBy' => null,
            ]);
            out("[SKIP] paymentSupportQuestions already exists, normalized: name={$supportQuestion['name']} scope=withdraw id={$existingSupportQuestion['id']}");
        }
    }

    // 清理掉模板里不再期望的旧 paymentSupportQuestions（包括残留的 deposit scope）
    $existingSupportQuestions = $paymentSupportQuestionModel->getAdminQuestions((int)$gateway['id']);
    foreach ($existingSupportQuestions as $existingSupportQuestion) {
        $key = ($existingSupportQuestion['name'] ?? '') . '|' . ($existingSupportQuestion['scope'] ?? '');
        if (in_array($key, $desiredSupportQuestionKeys, true)) {
            continue;
        }

        $paymentSupportQuestionModel->delete((int)$existingSupportQuestion['id']);
        out("[CLEAN] paymentSupportQuestions removed: name={$existingSupportQuestion['name']} scope={$existingSupportQuestion['scope']} id={$existingSupportQuestion['id']}");
    }

    $db->commit();

    out('');
    out('[DONE] Coinsbuy bootstrap finished successfully.');
    out('Gateway ID: ' . $gateway['id']);
    out('Payment Method ID: ' . $paymentMethod['id']);
    out('Template ID: ' . $template['id']);
    out('Category ID: ' . $category['id']);
    exit(0);
} catch (Throwable $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->rollback();
    }

    out('[ERROR] ' . $e->getMessage());
    exit(1);
}
