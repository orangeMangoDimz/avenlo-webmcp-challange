<?php
/**
 * Vexora Cambodia gateway bootstrap (KHR + USD)
 *
 * Usage:
 *   php script/vexoracambodiagatewayinit.php
 *   php script/vexoracambodiagatewayinit.php --currency=KHR --merchant-no=NO --secret=SECRET
 *   php script/vexoracambodiagatewayinit.php --currency=USD --merchant-no=NO --secret=SECRET --base-url=URL --environment=production
 *
 * Mapping:
 * - merchant-no  -> paymentGatewaySettings.apiKey (header merchantNo)
 * - secret       -> paymentGatewaySettings.secretKey (MD5 salt)
 * - base-url     -> configData.base_url
 * - No subMerchantNo for Cambodia
 * - Idempotent: re-run normalizes, does not duplicate
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplate.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionCategory.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestion.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionOption.php';
require_once __DIR__ . '/../models/PaymentSupportQuestion.php';

const VEXORA_KH_PROVIDER_LABEL = 'Vexora';
const VEXORA_KH_PROVIDER_KEY = 'vexora';
const VEXORA_KH_REGION = 'cambodia';
const VEXORA_KH_TEMPLATE_CATEGORY = 'Bank Account Details';
const VEXORA_KH_DEFAULT_DEPOSIT_FIXED_FEE = 0.00;
const VEXORA_KH_DEFAULT_WITHDRAWAL_FIXED_FEE = 0.00;
const VEXORA_KH_SANDBOX_KHR_BASE_URL = 'https://sandbox-api.vexora.com/cambodia';
const VEXORA_KH_SANDBOX_USD_BASE_URL = 'https://sandbox-api.vexora.com/cambodia-usd';

function vexoraKhOut($message) {
    echo $message . PHP_EOL;
}

function getVexoraCambodiaGatewayConfigs() {
    return [
        'KHR' => [
            'currency' => 'KHR',
            'gatewayKey' => 'vexora-khr',
            'gatewayName' => 'Vexora KHR Cambodia',
            'methodName' => 'Vexora KHR Cambodia',
            'shortCode' => 'KHR',
            'displayOrder' => 30,
            'amountDecimalPlaces' => 0,
            'defaultBaseUrl' => VEXORA_KH_SANDBOX_KHR_BASE_URL,
            'iconClass' => 'fas fa-money-bill-wave'
        ],
        'USD' => [
            'currency' => 'USD',
            'gatewayKey' => 'vexora-usd',
            'gatewayName' => 'Vexora USD Cambodia',
            'methodName' => 'Vexora USD Cambodia',
            'shortCode' => 'USD',
            'displayOrder' => 31,
            'amountDecimalPlaces' => 2,
            'defaultBaseUrl' => VEXORA_KH_SANDBOX_USD_BASE_URL,
            'iconClass' => 'fas fa-dollar-sign'
        ]
    ];
}

function getVexoraCambodiaDepositQuestions() {
    return [
        [
            'name' => 'first_name',
            'hintText' => 'Payer legal first name as registered with the payment method.',
            'questionType' => 'text',
            'validationRules' => 'required|min:1|max:100',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
            'isLocked' => 1
        ],
        [
            'name' => 'last_name',
            'hintText' => 'Payer legal last name as registered with the payment method.',
            'questionType' => 'text',
            'validationRules' => 'required|min:1|max:100',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
            'isLocked' => 1
        ],
        [
            'name' => 'phone',
            'hintText' => 'Cambodia mobile: 8 digits, or 9 digits starting with 0. No country code. Example: 12345678.',
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
            'hintText' => 'Choose the Cambodia payment method for this deposit.',
            'questionType' => 'single_choice',
            'validationRules' => 'required',
            'options' => [
                ['value' => 'khqr', 'label' => 'KHQR'],
                ['value' => 'va', 'label' => 'Bakong Virtual Account']
            ],
            'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
            'isLocked' => 1
        ]
    ];
}

/**
 * Official Vexora Cambodia disbursement bank codes:
 * https://docs.vexora.com/guide/areas/Cambodia/bankList/
 *
 * @return array<int, array{value: string, label: string}>
 */
function getVexoraCambodiaBankCodeOptions() {
    return [
        ['value' => '0001', 'label' => 'ABA Bank (0001)'],
        ['value' => '0002', 'label' => 'Wing Bank (Cambodia) Plc (0002)'],
        ['value' => '0003', 'label' => 'PEAK WEALTH BANK PLC (0003)'],
        ['value' => '0004', 'label' => 'Lanton Pay (0004)'],
        ['value' => '0005', 'label' => 'Sathapana Bank Plc (0005)'],
        ['value' => '0006', 'label' => 'ACLEDA Bank Plc. (0006)'],
        ['value' => '0007', 'label' => 'Phillip Bank Plc (0007)'],
        ['value' => '0008', 'label' => 'Phnom Penh Commercial Bank (0008)'],
        ['value' => '0009', 'label' => 'eMoney (0009)'],
        ['value' => '0010', 'label' => 'Canadia Bank Plc (0010)'],
        ['value' => '0011', 'label' => 'U-Pay Digital Plc (0011)'],
        ['value' => '0012', 'label' => 'TrueMoney Cambodia (0012)'],
        ['value' => '0013', 'label' => 'LY HOUR PAY PRO PLC (0013)'],
        ['value' => '0014', 'label' => 'BongLoy (0014)'],
        ['value' => '0015', 'label' => 'KB PRASAC Bank Plc (0015)'],
        ['value' => '0016', 'label' => 'Amret Plc. (0016)'],
        ['value' => '0017', 'label' => 'MB BANK (CAMBODIA) PLC (0017)'],
        ['value' => '0018', 'label' => 'Heng Feng (Cambodia) Bank (0018)'],
        ['value' => '0019', 'label' => 'Bank of China (Hong Kong) Limited Phnom Penh Branch (0019)'],
        ['value' => '0020', 'label' => 'Union Commercial Bank Plc. (0020)'],
        ['value' => '0021', 'label' => 'Maybank Cambodia PLC (0021)'],
        ['value' => '0022', 'label' => 'SBI LY HOUR Bank Plc. (0022)'],
        ['value' => '0023', 'label' => 'Chief (Cambodia) Commercial Bank Plc. (0023)'],
        ['value' => '0024', 'label' => 'Pi Pay Plc. (0024)'],
        ['value' => '0025', 'label' => 'RHB Bank(Cambodia) Plc. (0025)'],
        ['value' => '0026', 'label' => 'AMK Microfinance Plc. (0026)'],
        ['value' => '0027', 'label' => 'CIMB (0027)'],
        ['value' => '0028', 'label' => 'B.I.C (Cambodia) Bank Plc. (0028)'],
        ['value' => '0029', 'label' => 'Cambodian Public Bank Plc (0029)'],
        ['value' => '0030', 'label' => 'Chip Mong Commercial Bank Plc. (0030)'],
        ['value' => '0031', 'label' => 'Dara Sakor Pay PLC (0031)'],
        ['value' => '0032', 'label' => 'LOLC (Cambodia) Plc. (0032)'],
        ['value' => '0033', 'label' => 'Hattha Bank Plc (0033)'],
        ['value' => '0034', 'label' => 'Vattanac Bank (0034)'],
        ['value' => '0035', 'label' => 'ICBC (0035)'],
        ['value' => '0036', 'label' => 'APD Bank (0036)'],
        ['value' => '0037', 'label' => 'Shinhan Bank Cambodia Plc (0037)'],
        ['value' => '0038', 'label' => 'Cambodia Post Bank Plc (0038)'],
        ['value' => '0039', 'label' => 'BIDC Bank (0039)'],
        ['value' => '0040', 'label' => 'Foreign Trade Bank of Cambodia (0040)'],
        ['value' => '0041', 'label' => 'CATHAY UNITED BANK (CAMBODIA) PLC. (0041)'],
        ['value' => '0042', 'label' => 'DGB Bank (0042)'],
        ['value' => '0043', 'label' => 'Alpha Commercial Bank PLC (0043)'],
        ['value' => '0044', 'label' => 'First Commercial Bank (0044)'],
        ['value' => '0045', 'label' => 'Hong Leong Bank (Cambodia) Plc (0045)'],
        ['value' => '0046', 'label' => 'ARDB Bank (0046)'],
        ['value' => '0047', 'label' => 'Cambodia Asia Bank (0047)'],
        ['value' => '0048', 'label' => 'Sacombank Cambodia (0048)'],
        ['value' => '0049', 'label' => 'Oriental Bank (0049)'],
        ['value' => '0050', 'label' => 'BRIDGE Bank (0050)'],
        ['value' => '0051', 'label' => 'Woori Bank (Cambodia) Plc. (0051)'],
        ['value' => '0052', 'label' => 'Asia Wei Luy (0052)'],
        ['value' => '0053', 'label' => 'MOHANOKOR MFI Plc. (0053)'],
        ['value' => '0054', 'label' => 'CCU Commercial Bank PLC. (0054)'],
        ['value' => '0055', 'label' => 'BRED Bank (Cambodia) Plc (0055)'],
        ['value' => '0056', 'label' => 'J Trust Royal Bank Plc. (0056)'],
        ['value' => '0057', 'label' => 'Kess Innovation Plc. (0057)'],
        ['value' => '0058', 'label' => 'Booyoung Khmer Bank (0058)'],
        ['value' => '0059', 'label' => 'IBK Bank Cambodia (0059)'],
        ['value' => '0060', 'label' => 'Aeon Specialized Bank (Cambodia) PLC. (0060)'],
    ];
}

function getVexoraCambodiaWithdrawSupportQuestions() {
    return [
        [
            'name' => 'bank_code',
            'hintText' => 'Beneficiary bank. Options = Vexora Cambodia 4-digit bank codes.',
            'questionType' => 'single_choice',
            'validationRules' => 'required',
            'options' => getVexoraCambodiaBankCodeOptions(),
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
            'hintText' => 'Beneficiary Cambodia mobile: 8 digits, or 9 digits starting with 0. No country code.',
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

function getVexoraCambodiaWithdrawalTemplateQuestions() {
    return [
        [
            'scope' => 'bank_code',
            'questionText' => 'Which bank should receive the withdrawal?',
            'helpText' => 'Options are Vexora Cambodia 4-digit bank codes from the official bankList.',
            'questionType' => 'single_choice',
            'validationRules' => 'required',
            'options' => getVexoraCambodiaBankCodeOptions(),
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
            'helpText' => 'Cambodia mobile: 8 digits, or 9 digits starting with 0. Example: 12345678.',
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

function getVexoraCambodiaCliOptions() {
    $options = getopt('', ['currency::', 'merchant-no::', 'secret::', 'base-url::', 'environment::']);
    $currency = isset($options['currency']) ? strtoupper(trim((string)$options['currency'])) : null;
    return [
        'currency' => $currency,
        'merchantNo' => trim((string)($options['merchant-no'] ?? '')),
        'secret' => trim((string)($options['secret'] ?? '')),
        'baseUrl' => trim((string)($options['base-url'] ?? '')),
        'environment' => trim((string)($options['environment'] ?? 'sandbox'))
    ];
}

function runVexoraCambodiaGatewayInit(array $config, array $cli) {
    $currency = strtoupper(trim((string)($config['currency'] ?? '')));
    $gatewayKey = trim((string)($config['gatewayKey'] ?? ''));
    $gatewayName = trim((string)($config['gatewayName'] ?? ''));
    $methodName = trim((string)($config['methodName'] ?? $gatewayName));
    $shortCode = trim((string)($config['shortCode'] ?? $currency));
    $displayOrder = isset($config['displayOrder']) ? (int)$config['displayOrder'] : 0;
    $amountDecimalPlaces = isset($config['amountDecimalPlaces']) ? (int)$config['amountDecimalPlaces'] : 0;
    $iconClass = trim((string)($config['iconClass'] ?? 'fas fa-money-bill-wave'));
    $defaultBaseUrl = trim((string)($config['defaultBaseUrl'] ?? VEXORA_KH_SANDBOX_KHR_BASE_URL));
    $baseUrl = $cli['baseUrl'] !== '' ? $cli['baseUrl'] : $defaultBaseUrl;

    if ($currency === '' || $gatewayKey === '' || $gatewayName === '') {
        throw new InvalidArgumentException('Vexora Cambodia config is incomplete.');
    }

    $db = Database::getInstance();
    $templateModel = new WithdrawalVerificationTemplate();
    $categoryModel = new WithdrawalVerificationQuestionCategory();
    $questionModel = new WithdrawalVerificationQuestion();
    $questionOptionModel = new WithdrawalVerificationQuestionOption();
    $paymentSupportQuestionModel = new PaymentSupportQuestion();

    $templateName = VEXORA_KH_PROVIDER_LABEL . ' ' . $currency . ' Withdrawal Verification';
    $templateDescription = 'Locked withdrawal verification fields for Vexora Cambodia ' . $currency . '.';
    $depositQuestions = getVexoraCambodiaDepositQuestions();
    $withdrawSupportQuestions = getVexoraCambodiaWithdrawSupportQuestions();
    $withdrawTemplateQuestions = getVexoraCambodiaWithdrawalTemplateQuestions();
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

        vexoraKhOut('=== ' . $gatewayName . ' Init ===');

        $gateway = $db->fetchOne(
            "SELECT * FROM paymentGatewaySettings WHERE gatewayKey = :gatewayKey LIMIT 1",
            ['gatewayKey' => $gatewayKey]
        );

        $gatewayData = [
            'gatewayKey' => $gatewayKey,
            'gatewayName' => $gatewayName,
            'iconClass' => $iconClass,
            'integration' => 1,
            'isEnabled' => $cli['merchantNo'] !== '' && $cli['secret'] !== '' ? 1 : 0,
            'isDepositEnabled' => 1,
            'isWithdrawalEnabled' => 1,
            'processingTime' => 'Instant - 1 business day',
            'environment' => $cli['environment'] === 'production' ? 'production' : 'sandbox',
            'appId' => null,
            'apiKey' => $cli['merchantNo'] !== '' ? $cli['merchantNo'] : null,
            'secretKey' => $cli['secret'] !== '' ? $cli['secret'] : null,
            'merchantName' => strtolower(VEXORA_KH_PROVIDER_LABEL),
            'webhookUrl' => $notifyUrl !== '' ? $notifyUrl : null,
            'returnUrl' => null,
            'supportedFiatCurrencies' => json_encode([$currency], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'supportedCryptoCurrencies' => null,
            'amountDecimalPlaces' => $amountDecimalPlaces,
            'configData' => json_encode([
                'providerKey' => VEXORA_KH_PROVIDER_KEY,
                'region' => VEXORA_KH_REGION,
                'base_url' => $baseUrl,
                'default_channel_code' => 'KHQR',
                'default_way_code' => 'KHQR',
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
            vexoraKhOut("[OK] paymentGatewaySettings inserted: id={$gatewayId}");
        } else {
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
            vexoraKhOut("[SKIP] paymentGatewaySettings already exists, normalized: id={$gateway['id']}");
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
            'notes' => 'Initialized by Vexora Cambodia bootstrap script for ' . $currency . '.',
            'updatedBy' => null
        ];

        if (!$gatewayFundingSetting) {
            $gatewayFundingSettingId = $db->insert('paymentGatewayFundingSettings', array_merge(
                ['gatewaySettingId' => (int)$gateway['id']],
                $gatewayFundingData
            ));
            vexoraKhOut("[OK] paymentGatewayFundingSettings inserted: id={$gatewayFundingSettingId}");
        } else {
            $db->update(
                'paymentGatewayFundingSettings',
                $gatewayFundingData,
                'id = :id',
                ['id' => $gatewayFundingSetting['id']]
            );
            $gatewayFundingSettingId = (int)$gatewayFundingSetting['id'];
            vexoraKhOut("[SKIP] paymentGatewayFundingSettings already exists, normalized: id={$gatewayFundingSettingId}");
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
            'fixed' => VEXORA_KH_DEFAULT_DEPOSIT_FIXED_FEE,
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
            'fixed' => VEXORA_KH_DEFAULT_WITHDRAWAL_FIXED_FEE,
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
            'iconClass' => $iconClass,
            'shortCode' => $shortCode,
            'networkName' => VEXORA_KH_PROVIDER_LABEL,
            'country' => 'KH',
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
            vexoraKhOut("[OK] paymentMethods inserted: id={$paymentMethodId}");
        } else {
            $db->update('paymentMethods', $paymentMethodData, 'id = :id', ['id' => $paymentMethod['id']]);
            $paymentMethod = $db->fetchOne(
                "SELECT * FROM paymentMethods WHERE id = :id LIMIT 1",
                ['id' => $paymentMethod['id']]
            );
            vexoraKhOut("[SKIP] paymentMethods already exists, normalized: id={$paymentMethod['id']}");
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
            vexoraKhOut("[OK] withdrawalVerificationTemplates inserted: id={$templateId}");
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
            vexoraKhOut("[SKIP] withdrawalVerificationTemplates already exists, normalized: id={$template['id']}");
        }

        $category = $categoryModel->findOne([
            'templateId' => $template['id'],
            'categoryName' => VEXORA_KH_TEMPLATE_CATEGORY
        ]);

        if (!$category) {
            $categoryId = $categoryModel->createCategory([
                'templateId' => (int)$template['id'],
                'categoryName' => VEXORA_KH_TEMPLATE_CATEGORY,
                'description' => 'Locked Vexora Cambodia bank withdrawal fields.',
                'displayOrder' => 1,
                'isExpanded' => 1,
                'isActive' => 1,
                'isLocked' => 1
            ]);
            $category = $categoryModel->findById($categoryId);
            vexoraKhOut("[OK] withdrawalVerificationQuestionCategories inserted: id={$categoryId}");
        } else {
            $categoryModel->update($category['id'], [
                'description' => 'Locked Vexora Cambodia bank withdrawal fields.',
                'displayOrder' => 1,
                'isExpanded' => 1,
                'isActive' => 1,
                'isLocked' => 1
            ]);
            $category = $categoryModel->findById($category['id']);
            vexoraKhOut("[SKIP] withdrawalVerificationQuestionCategories already exists, normalized: id={$category['id']}");
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
                vexoraKhOut("[OK] withdrawal question inserted: scope={$questionSpec['scope']} id={$questionId}");
            } else {
                $questionModel->update($existing['id'], $questionData);
                if ($questionSpec['questionType'] === 'single_choice') {
                    $questionOptionModel->updateQuestionOptions($existing['id'], $questionSpec['options'] ?? []);
                }
                vexoraKhOut("[SKIP] withdrawal question already exists, normalized: scope={$questionSpec['scope']} id={$existing['id']}");
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
                vexoraKhOut("[OK] paymentSupportQuestions inserted: name={$supportQuestion['name']} scope={$supportQuestion['scope']} id={$supportQuestionId}");
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
                vexoraKhOut("[SKIP] paymentSupportQuestions already exists, normalized: name={$supportQuestion['name']} scope={$supportQuestion['scope']} id={$existingSupportQuestion['id']}");
            }
        }

        $existingSupportQuestions = $paymentSupportQuestionModel->getAdminQuestions((int)$gateway['id']);
        foreach ($existingSupportQuestions as $existingSupportQuestion) {
            $key = ($existingSupportQuestion['name'] ?? '') . '|' . ($existingSupportQuestion['scope'] ?? '');
            if (in_array($key, $desiredSupportQuestionKeys, true)) {
                continue;
            }

            $paymentSupportQuestionModel->delete((int)$existingSupportQuestion['id']);
            vexoraKhOut("[CLEAN] paymentSupportQuestions removed: name={$existingSupportQuestion['name']} scope={$existingSupportQuestion['scope']} id={$existingSupportQuestion['id']}");
        }

        $db->commit();

        vexoraKhOut('');
        vexoraKhOut('[DONE] Vexora Cambodia bootstrap finished successfully.');
        vexoraKhOut('Gateway ID: ' . $gateway['id']);
        vexoraKhOut('Payment Method ID: ' . $paymentMethod['id']);
        vexoraKhOut('Template ID: ' . $template['id']);
        vexoraKhOut('Category ID: ' . $category['id']);
        if (empty($gateway['apiKey']) || empty($gateway['secretKey'])) {
            vexoraKhOut('[NOTE] Gateway created DISABLED: merchant credentials not provided yet.');
            vexoraKhOut('       Re-run with --currency/--merchant-no/--secret once Vexora issues them,');
            vexoraKhOut('       then enable via admin panel.');
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
        $cli = getVexoraCambodiaCliOptions();
        $configs = getVexoraCambodiaGatewayConfigs();

        if ($cli['currency'] !== null && $cli['currency'] !== '') {
            if (!isset($configs[$cli['currency']])) {
                throw new InvalidArgumentException('Unsupported currency. Use KHR or USD.');
            }
            runVexoraCambodiaGatewayInit($configs[$cli['currency']], $cli);
        } else {
            foreach ($configs as $config) {
                runVexoraCambodiaGatewayInit($config, $cli);
            }
        }
        exit(0);
    } catch (Throwable $e) {
        vexoraKhOut('[ERROR] ' . $e->getMessage());
        if ($e instanceof InvalidArgumentException) {
            vexoraKhOut('Usage: php vexoracambodiagatewayinit.php [--currency=KHR|USD] [--merchant-no=NO] [--secret=SECRET] [--base-url=URL] [--environment=sandbox|production]');
        }
        exit(1);
    }
}
