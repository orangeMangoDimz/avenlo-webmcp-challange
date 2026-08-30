<?php
/**
 * Vexora gateway bootstrap initializer (South Korea / KRW)
 *
 * 用法：
 *   php script/vexoragatewayinit.php --merchant-no=MERCHANT_NO --secret=APP_SECRET --sub-merchant-no=SUB_NO
 *   php script/vexoragatewayinit.php --merchant-no=... --secret=... --sub-merchant-no=... --base-url=https://sandbox-api.vexora.tech/korea
 *
 * 说明：
 * - merchant-no  -> paymentGatewaySettings.apiKey（请求头 merchantNo）
 * - secret       -> paymentGatewaySettings.secretKey（MD5 签名盐）
 * - sub-merchant-no / base-url -> configData
 * - 幂等：重复执行只做 normalize，不重复插入
 * - 出金 bank_code 选项按官方 bankList 种子化（可重复执行覆盖同步）：
 *   https://docs.vexora.com/guide/areas/SouthKorea/bankList/
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplate.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionCategory.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestion.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionOption.php';
require_once __DIR__ . '/../models/PaymentSupportQuestion.php';

const VEXORA_PROVIDER_LABEL = 'Vexora';
const VEXORA_PROVIDER_KEY = 'vexora';
const VEXORA_TEMPLATE_CATEGORY = 'Bank Account Details';
const VEXORA_DEFAULT_DEPOSIT_FIXED_FEE = 0.00;
const VEXORA_DEFAULT_WITHDRAWAL_FIXED_FEE = 0.00;
const VEXORA_DEFAULT_SANDBOX_BASE_URL = 'https://sandbox-api.vexora.tech/korea';

function vexoraOut($message) {
    echo $message . PHP_EOL;
}

function getVexoraGatewayConfig() {
    return [
        'currency' => 'KRW',
        'gatewayKey' => 'vexora-krw',
        'gatewayName' => 'Vexora KRW',
        'methodName' => 'Vexora KRW',
        'shortCode' => 'KRW',
        'displayOrder' => 25,
        // KRW 只允许整数金额（Vexora checkout amount: integer only）
        'amountDecimalPlaces' => 0
    ];
}

function getVexoraDepositQuestions() {
    return [
        [
            'name' => 'first_name',
            'hintText' => 'Payer given name as registered with the payment method.',
            'questionType' => 'text',
            'validationRules' => 'required|min:1|max:100',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
            'isLocked' => 1
        ],
        [
            'name' => 'last_name',
            'hintText' => 'Payer family name as registered with the payment method.',
            'questionType' => 'text',
            'validationRules' => 'required|min:1|max:100',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
            'isLocked' => 1
        ],
        [
            'name' => 'phone',
            'hintText' => 'Korean mobile number in local format, e.g. 01012345678 (no country code).',
            'questionType' => 'tel',
            'validationRules' => 'required|max:30',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
            'isLocked' => 1
        ],
        [
            'name' => 'email',
            'hintText' => 'Payer email address.',
            'questionType' => 'email',
            'validationRules' => 'required|email|max:255',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
            'isLocked' => 1
        ],
        [
            'name' => 'payment_method',
            'hintText' => 'Choose the Korean payment method for this deposit.',
            'questionType' => 'single_choice',
            'validationRules' => 'required',
            'options' => [
                ['value' => 'kakaopay', 'label' => 'KakaoPay'],
                ['value' => 'tosspay', 'label' => 'Toss Pay'],
                ['value' => 'samsungpay', 'label' => 'Samsung Pay'],
                ['value' => 'card', 'label' => 'Korean Card'],
                ['value' => 'va', 'label' => 'Virtual Account']
            ],
            'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
            'isLocked' => 1
        ],
        [
            'name' => 'dob',
            'hintText' => 'Date of birth (YYYY-MM-DD). Required for Virtual Account deposits (age 18-59).',
            'questionType' => 'date',
            'validationRules' => 'max:10',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
            'isLocked' => 1
        ]
    ];
}

/**
 * Official Vexora KR disbursement bank codes:
 * https://docs.vexora.com/guide/areas/SouthKorea/bankList/
 *
 * @return array<int, array{value: string, label: string}>
 */
function getVexoraBankCodeOptions() {
    return [
        ['value' => '0001', 'label' => 'Shinhan Bank (0001)'],
        ['value' => '0002', 'label' => 'Woori Bank (0002)'],
        ['value' => '0003', 'label' => 'Kookmin Bank (0003)'],
        ['value' => '0004', 'label' => 'NH NongHyup Bank (0004)'],
        ['value' => '0005', 'label' => 'National Agricultural Cooperative Federation (0005)'],
        ['value' => '0006', 'label' => 'KEB Hana Bank (0006)'],
        ['value' => '0007', 'label' => 'Citibank Korea (0007)'],
        ['value' => '0008', 'label' => 'Korea Federation of Community Credit Cooperative (0008)'],
        ['value' => '0009', 'label' => 'National Credit Union Federation of Korea (0009)'],
        ['value' => '0010', 'label' => 'Korea Post Office (0010)'],
        ['value' => '0011', 'label' => 'Industrial Bank of Korea (0011)'],
        ['value' => '0012', 'label' => 'Korea Development Bank (0012)'],
        ['value' => '0013', 'label' => 'Kakao Bank (0013)'],
        ['value' => '0014', 'label' => 'KBank (0014)'],
        ['value' => '0015', 'label' => 'Toss Bank (0015)'],
        ['value' => '0016', 'label' => 'Standard Chartered First Bank Korea (0016)'],
        ['value' => '0017', 'label' => 'Daegu Bank (0017)'],
        ['value' => '0018', 'label' => 'BNK Busan Bank (0018)'],
        ['value' => '0019', 'label' => 'Gwangju Bank (0019)'],
        ['value' => '0020', 'label' => 'Jeju Bank (0020)'],
        ['value' => '0021', 'label' => 'Jeonbuk Bank (0021)'],
        ['value' => '0022', 'label' => 'Kyongnam Bank (0022)'],
        ['value' => '0023', 'label' => 'Saving Bank (0023)'],
        ['value' => '0024', 'label' => 'HSBC Bank (0024)'],
        ['value' => '0025', 'label' => 'Deutsche Bank (0025)'],
        ['value' => '0026', 'label' => 'JPMorgan Chase Bank (0026)'],
        ['value' => '0027', 'label' => 'BOA Bank (0027)'],
        ['value' => '0028', 'label' => 'BNP Paribas Bank (0028)'],
        ['value' => '0029', 'label' => 'Industrial and Commercial Bank of China (0029)'],
        ['value' => '0030', 'label' => 'Bank of China (0030)'],
        ['value' => '0031', 'label' => 'National Forestry Cooperative Federation (0031)'],
        ['value' => '0032', 'label' => 'China Construction Bank (0032)'],
        ['value' => '0033', 'label' => 'Suhyup Bank (0033)'],
        ['value' => '0034', 'label' => 'Welcome Savings Bank (0034)'],
    ];
}

function getVexoraWithdrawSupportQuestions() {
    return [
        [
            'name' => 'bank_code',
            'hintText' => 'Beneficiary bank. Options = Vexora 4-digit bank codes from the official bankList.',
            'questionType' => 'single_choice',
            'validationRules' => 'required',
            'options' => getVexoraBankCodeOptions(),
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
            'isLocked' => 1
        ],
        [
            'name' => 'first_name',
            'hintText' => 'Beneficiary given name exactly as registered at the bank.',
            'questionType' => 'text',
            'validationRules' => 'required|min:1|max:100',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
            'isLocked' => 1
        ],
        [
            'name' => 'last_name',
            'hintText' => 'Beneficiary family name exactly as registered at the bank.',
            'questionType' => 'text',
            'validationRules' => 'required|min:1|max:100',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
            'isLocked' => 1
        ],
        [
            'name' => 'email',
            'hintText' => 'Beneficiary email address.',
            'questionType' => 'email',
            'validationRules' => 'required|email|max:255',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
            'isLocked' => 1
        ],
        [
            'name' => 'phone',
            'hintText' => 'Beneficiary Korean mobile number, e.g. 01012345678.',
            'questionType' => 'tel',
            'validationRules' => 'required|max:30',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
            'isLocked' => 1
        ],
        [
            'name' => 'account_number',
            'hintText' => 'Beneficiary bank account number.',
            'questionType' => 'text',
            'validationRules' => 'required|min:4|max:50',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
            'isLocked' => 1
        ]
    ];
}

function getVexoraWithdrawalTemplateQuestions() {
    return [
        [
            'scope' => 'bank_code',
            'questionText' => 'Which bank should receive the withdrawal?',
            'helpText' => 'Options are Vexora 4-digit bank codes from the official bankList.',
            'questionType' => 'single_choice',
            'validationRules' => 'required',
            'options' => getVexoraBankCodeOptions(),
            'isRequired' => 1
        ],
        [
            'scope' => 'first_name',
            'questionText' => 'What is the beneficiary first name?',
            'helpText' => 'Use the bank beneficiary first name exactly as registered.',
            'questionType' => 'text',
            'validationRules' => 'required|min:1|max:100',
            'options' => null,
            'isRequired' => 1
        ],
        [
            'scope' => 'last_name',
            'questionText' => 'What is the beneficiary last name?',
            'helpText' => 'Use the bank beneficiary last name exactly as registered.',
            'questionType' => 'text',
            'validationRules' => 'required|min:1|max:100',
            'options' => null,
            'isRequired' => 1
        ],
        [
            'scope' => 'email',
            'questionText' => 'What is the beneficiary email address?',
            'helpText' => 'Email address for disbursement notification.',
            'questionType' => 'email',
            'validationRules' => 'required|email|max:255',
            'options' => null,
            'isRequired' => 1
        ],
        [
            'scope' => 'phone',
            'questionText' => 'What is the beneficiary phone number?',
            'helpText' => 'Korean mobile number in local format, e.g. 01012345678.',
            'questionType' => 'tel',
            'validationRules' => 'required|max:30',
            'options' => null,
            'isRequired' => 1
        ],
        [
            'scope' => 'account_number',
            'questionText' => 'What is the beneficiary account number?',
            'helpText' => 'Enter the full bank account number for this withdrawal.',
            'questionType' => 'text',
            'validationRules' => 'required|min:4|max:50',
            'options' => null,
            'isRequired' => 1
        ]
    ];
}

function getVexoraCliOptions() {
    $options = getopt('', ['merchant-no::', 'secret::', 'sub-merchant-no::', 'base-url::', 'environment::']);
    return [
        'merchantNo' => trim((string)($options['merchant-no'] ?? '')),
        'secret' => trim((string)($options['secret'] ?? '')),
        'subMerchantNo' => trim((string)($options['sub-merchant-no'] ?? '')),
        'baseUrl' => trim((string)($options['base-url'] ?? VEXORA_DEFAULT_SANDBOX_BASE_URL)),
        'environment' => trim((string)($options['environment'] ?? 'sandbox'))
    ];
}

function runVexoraGatewayInit(array $config, array $cli) {
    $currency = strtoupper(trim((string)($config['currency'] ?? '')));
    $gatewayKey = trim((string)($config['gatewayKey'] ?? ''));
    $gatewayName = trim((string)($config['gatewayName'] ?? ''));
    $methodName = trim((string)($config['methodName'] ?? $gatewayName));
    $shortCode = trim((string)($config['shortCode'] ?? $currency));
    $displayOrder = isset($config['displayOrder']) ? (int)$config['displayOrder'] : 0;
    $amountDecimalPlaces = isset($config['amountDecimalPlaces']) ? (int)$config['amountDecimalPlaces'] : 0;

    if ($currency === '' || $gatewayKey === '' || $gatewayName === '') {
        throw new InvalidArgumentException('Vexora config is incomplete.');
    }

    $db = Database::getInstance();
    $templateModel = new WithdrawalVerificationTemplate();
    $categoryModel = new WithdrawalVerificationQuestionCategory();
    $questionModel = new WithdrawalVerificationQuestion();
    $questionOptionModel = new WithdrawalVerificationQuestionOption();
    $paymentSupportQuestionModel = new PaymentSupportQuestion();

    $templateName = VEXORA_PROVIDER_LABEL . ' ' . $currency . ' Withdrawal Verification';
    $templateDescription = 'Locked withdrawal verification fields for Vexora ' . $currency . '.';
    $depositQuestions = getVexoraDepositQuestions();
    $withdrawSupportQuestions = getVexoraWithdrawSupportQuestions();
    $withdrawTemplateQuestions = getVexoraWithdrawalTemplateQuestions();
    $supportQuestions = array_merge($depositQuestions, $withdrawSupportQuestions);

    $appConfig = require __DIR__ . '/../config/app.php';
    $backendBaseUrl = rtrim((string)($appConfig['file_base_url'] ?? ''), '/');
    if ($backendBaseUrl !== '') {
        $backendBaseUrl = preg_replace('#/index\.php$#i', '', $backendBaseUrl);
    }
    $notifyUrl = $backendBaseUrl !== ''
        ? $backendBaseUrl . '/index.php?path=api/callback/vexora/deposit'
        : '';

    try {
        $db->beginTransaction();

        vexoraOut('=== ' . $gatewayName . ' Init ===');

        $gateway = $db->fetchOne(
            "SELECT * FROM paymentGatewaySettings WHERE gatewayKey = :gatewayKey LIMIT 1",
            ['gatewayKey' => $gatewayKey]
        );

        $gatewayData = [
            'gatewayKey' => $gatewayKey,
            'gatewayName' => $gatewayName,
            'iconClass' => 'fas fa-won-sign',
            'integration' => 1,
            // 拿到正式商户号前先建为停用，避免出现在客户端列表
            'isEnabled' => $cli['merchantNo'] !== '' && $cli['secret'] !== '' ? 1 : 0,
            'isDepositEnabled' => 1,
            'isWithdrawalEnabled' => 1,
            'processingTime' => 'Instant - 1 business day',
            'environment' => $cli['environment'] === 'production' ? 'production' : 'sandbox',
            'appId' => null,
            'apiKey' => $cli['merchantNo'] !== '' ? $cli['merchantNo'] : null,
            'secretKey' => $cli['secret'] !== '' ? $cli['secret'] : null,
            'merchantName' => strtolower(VEXORA_PROVIDER_LABEL),
            'webhookUrl' => $notifyUrl !== '' ? $notifyUrl : null,
            'returnUrl' => null,
            'supportedFiatCurrencies' => json_encode([$currency], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'supportedCryptoCurrencies' => null,
            'amountDecimalPlaces' => $amountDecimalPlaces,
            'configData' => json_encode([
                'providerKey' => VEXORA_PROVIDER_KEY,
                'base_url' => $cli['baseUrl'],
                'sub_merchant_no' => $cli['subMerchantNo'],
                'default_channel_code' => 'EWALLET',
                'default_way_code' => 'KAKAOPAY',
                'currency' => $currency
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updatedBy' => null
        ];

        if (!$gateway) {
            $gatewayId = $db->insert('paymentGatewaySettings', $gatewayData);
            $gateway = $db->fetchOne(
                "SELECT * FROM paymentGatewaySettings WHERE id = :id LIMIT 1",
                ['id' => $gatewayId]
            );
            vexoraOut("[OK] paymentGatewaySettings inserted: id={$gatewayId}");
        } else {
            // 已存在时不清空已配置的密钥
            if ($cli['merchantNo'] === '') {
                unset($gatewayData['apiKey']);
            }
            if ($cli['secret'] === '') {
                unset($gatewayData['secretKey']);
            }
            unset($gatewayData['isEnabled']);
            $db->update('paymentGatewaySettings', $gatewayData, 'id = :id', ['id' => $gateway['id']]);
            $gateway = $db->fetchOne(
                "SELECT * FROM paymentGatewaySettings WHERE id = :id LIMIT 1",
                ['id' => $gateway['id']]
            );
            vexoraOut("[SKIP] paymentGatewaySettings already exists, normalized: id={$gateway['id']}");
        }

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
            'notes' => 'Initialized by Vexora bootstrap script for ' . $currency . '.',
            'updatedBy' => null
        ];

        if (!$gatewayFundingSetting) {
            $gatewayFundingSettingId = $db->insert('paymentGatewayFundingSettings', array_merge(
                ['gatewaySettingId' => (int)$gateway['id']],
                $gatewayFundingData
            ));
            vexoraOut("[OK] paymentGatewayFundingSettings inserted: id={$gatewayFundingSettingId}");
        } else {
            $db->update(
                'paymentGatewayFundingSettings',
                $gatewayFundingData,
                'id = :id',
                ['id' => $gatewayFundingSetting['id']]
            );
            $gatewayFundingSettingId = (int)$gatewayFundingSetting['id'];
            vexoraOut("[SKIP] paymentGatewayFundingSettings already exists, normalized: id={$gatewayFundingSettingId}");
        }

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
            'fixed' => VEXORA_DEFAULT_DEPOSIT_FIXED_FEE,
            'minFee' => null,
            'maxFee' => null,
            'chargeToClient' => 1,
            'sortOrder' => 0,
            'isActive' => 1
        ]);

        $db->insert('paymentGatewayFeeRules', [
            'gatewayFundingSettingId' => $gatewayFundingSettingId,
            'transactionType' => 'withdrawal',
            'thresholdAmount' => 0,
            'feeMode' => 'none',
            'percentage' => 0,
            'fixed' => VEXORA_DEFAULT_WITHDRAWAL_FIXED_FEE,
            'minFee' => null,
            'maxFee' => null,
            'chargeToClient' => 1,
            'sortOrder' => 0,
            'isActive' => 1
        ]);

        $paymentMethod = $db->fetchOne(
            "SELECT * FROM paymentMethods WHERE methodKey = :methodKey LIMIT 1",
            ['methodKey' => $gatewayKey]
        );

        $paymentMethodData = [
            'methodKey' => $gatewayKey,
            'methodName' => $methodName,
            'methodType' => 'fiat',
            'iconClass' => 'fas fa-won-sign',
            'shortCode' => $shortCode,
            'networkName' => VEXORA_PROVIDER_LABEL,
            'country' => 'KR',
            'minPurchaseAmount' => null,
            'maxPurchaseAmount' => null,
            'isDepositEnabled' => 1,
            'isWithdrawalEnabled' => 1,
            'processingTime' => 'Instant - 1 business day',
            'displayOrder' => $displayOrder
        ];

        if (!$paymentMethod) {
            $paymentMethodId = $db->insert('paymentMethods', $paymentMethodData);
            $paymentMethod = $db->fetchOne(
                "SELECT * FROM paymentMethods WHERE id = :id LIMIT 1",
                ['id' => $paymentMethodId]
            );
            vexoraOut("[OK] paymentMethods inserted: id={$paymentMethodId}");
        } else {
            $db->update('paymentMethods', $paymentMethodData, 'id = :id', ['id' => $paymentMethod['id']]);
            $paymentMethod = $db->fetchOne(
                "SELECT * FROM paymentMethods WHERE id = :id LIMIT 1",
                ['id' => $paymentMethod['id']]
            );
            vexoraOut("[SKIP] paymentMethods already exists, normalized: id={$paymentMethod['id']}");
        }

        $template = $templateModel->findOne([
            'gatewaySettingId' => $gateway['id'],
            'templateName' => $templateName
        ]);

        if (!$template) {
            $templateId = $templateModel->create([
                'gatewaySettingId' => (int)$gateway['id'],
                'templateName' => $templateName,
                'description' => $templateDescription,
                'status' => 'active',
                'isAutoApproveEnabled' => 0,
                'requireDocumentSignature' => 0,
                'displayOrder' => 1,
                'createdBy' => null,
                'updatedBy' => null
            ]);

            $template = $templateModel->findById($templateId);
            vexoraOut("[OK] withdrawalVerificationTemplates inserted: id={$templateId}");
        } else {
            $templateModel->update($template['id'], [
                'description' => $templateDescription,
                'status' => 'active',
                'isAutoApproveEnabled' => 0,
                'requireDocumentSignature' => 0,
                'displayOrder' => 1,
                'updatedBy' => null
            ]);
            $template = $templateModel->findById($template['id']);
            vexoraOut("[SKIP] withdrawalVerificationTemplates already exists, normalized: id={$template['id']}");
        }

        $category = $categoryModel->findOne([
            'templateId' => $template['id'],
            'categoryName' => VEXORA_TEMPLATE_CATEGORY
        ]);

        if (!$category) {
            $categoryId = $categoryModel->createCategory([
                'templateId' => (int)$template['id'],
                'categoryName' => VEXORA_TEMPLATE_CATEGORY,
                'description' => 'Locked Vexora bank withdrawal fields.',
                'displayOrder' => 1,
                'isExpanded' => 1,
                'isActive' => 1,
                'isLocked' => 1
            ]);
            $category = $categoryModel->findById($categoryId);
            vexoraOut("[OK] withdrawalVerificationQuestionCategories inserted: id={$categoryId}");
        } else {
            $categoryModel->update($category['id'], [
                'description' => 'Locked Vexora bank withdrawal fields.',
                'displayOrder' => 1,
                'isExpanded' => 1,
                'isActive' => 1,
                'isLocked' => 1
            ]);
            $category = $categoryModel->findById($category['id']);
            vexoraOut("[SKIP] withdrawalVerificationQuestionCategories already exists, normalized: id={$category['id']}");
        }

        foreach ($withdrawTemplateQuestions as $index => $questionSpec) {
            $existing = $questionModel->findOne([
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

            if (!$existing) {
                $questionData['createdBy'] = null;
                $questionId = $questionModel->createQuestion($questionData);
                if ($questionSpec['questionType'] === 'single_choice') {
                    $questionOptionModel->updateQuestionOptions($questionId, $questionSpec['options'] ?? []);
                }
                vexoraOut("[OK] withdrawal question inserted: scope={$questionSpec['scope']} id={$questionId}");
            } else {
                $questionModel->update($existing['id'], $questionData);
                if ($questionSpec['questionType'] === 'single_choice') {
                    $questionOptionModel->updateQuestionOptions($existing['id'], $questionSpec['options'] ?? []);
                }
                vexoraOut("[SKIP] withdrawal question already exists, normalized: scope={$questionSpec['scope']} id={$existing['id']}");
            }
        }

        $questionCount = $questionModel->count(['templateId' => $template['id']]);
        $templateModel->update($template['id'], [
            'totalQuestions' => $questionCount,
            'totalRules' => 0,
            'updatedBy' => null
        ]);

        $desiredSupportQuestionKeys = [];
        foreach ($supportQuestions as $supportQuestion) {
            $desiredSupportQuestionKeys[] = $supportQuestion['name'] . '|' . $supportQuestion['scope'];
        }

        foreach ($supportQuestions as $supportQuestion) {
            $existingSupportQuestion = $paymentSupportQuestionModel->findOne([
                'paymentGatewayId' => $gateway['id'],
                'name' => $supportQuestion['name'],
                'scope' => $supportQuestion['scope']
            ]);

            $supportQuestionData = [
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
                $supportQuestionId = $paymentSupportQuestionModel->createQuestion($supportQuestionData);
                vexoraOut("[OK] paymentSupportQuestions inserted: name={$supportQuestion['name']} scope={$supportQuestion['scope']} id={$supportQuestionId}");
            } else {
                $paymentSupportQuestionModel->updateQuestion($existingSupportQuestion['id'], [
                    'hintText' => $supportQuestion['hintText'],
                    'questionType' => $supportQuestion['questionType'],
                    'validationRules' => $supportQuestion['validationRules'],
                    'options' => $supportQuestion['options'],
                    'isLocked' => $supportQuestion['isLocked'],
                    'isActive' => 1,
                    'updatedBy' => null
                ]);
                vexoraOut("[SKIP] paymentSupportQuestions already exists, normalized: name={$supportQuestion['name']} scope={$supportQuestion['scope']} id={$existingSupportQuestion['id']}");
            }
        }

        $existingSupportQuestions = $paymentSupportQuestionModel->getAdminQuestions((int)$gateway['id']);
        foreach ($existingSupportQuestions as $existingSupportQuestion) {
            $key = ($existingSupportQuestion['name'] ?? '') . '|' . ($existingSupportQuestion['scope'] ?? '');
            if (in_array($key, $desiredSupportQuestionKeys, true)) {
                continue;
            }

            $paymentSupportQuestionModel->delete((int)$existingSupportQuestion['id']);
            vexoraOut("[CLEAN] paymentSupportQuestions removed: name={$existingSupportQuestion['name']} scope={$existingSupportQuestion['scope']} id={$existingSupportQuestion['id']}");
        }

        $db->commit();

        vexoraOut('');
        vexoraOut('[DONE] Vexora bootstrap finished successfully.');
        vexoraOut('Gateway ID: ' . $gateway['id']);
        vexoraOut('Payment Method ID: ' . $paymentMethod['id']);
        vexoraOut('Template ID: ' . $template['id']);
        vexoraOut('Category ID: ' . $category['id']);
        if (empty($gateway['apiKey']) || empty($gateway['secretKey'])) {
            vexoraOut('[NOTE] Gateway created DISABLED: merchant credentials not provided yet.');
            vexoraOut('       Re-run with --merchant-no/--secret/--sub-merchant-no once Vexora issues them,');
            vexoraOut('       then enable via admin panel.');
        }

        return [
            'gatewayId' => (int)$gateway['id'],
            'paymentMethodId' => (int)$paymentMethod['id'],
            'templateId' => (int)$template['id'],
            'categoryId' => (int)$category['id']
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
        runVexoraGatewayInit(getVexoraGatewayConfig(), getVexoraCliOptions());
        exit(0);
    } catch (Throwable $e) {
        vexoraOut('[ERROR] ' . $e->getMessage());
        if ($e instanceof InvalidArgumentException) {
            vexoraOut('Usage: php vexoragatewayinit.php [--merchant-no=NO] [--secret=SECRET] [--sub-merchant-no=SUB] [--base-url=URL] [--environment=sandbox|production]');
        }
        exit(1);
    }
}
