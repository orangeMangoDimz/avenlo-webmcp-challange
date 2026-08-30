<?php
/**
 * Payment Asia gateway bootstrap initializer
 *
 * 用法：
 *   php script/paymentasiagatewayinit.php
 *   php script/paymentasiagatewayinit.php --app-id=YOUR_APP_ID --secret=YOUR_SECRET
 *   php script/paymentasiagatewayinit.php --currency=MYR
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplate.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionCategory.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestion.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionOption.php';
require_once __DIR__ . '/../models/PaymentSupportQuestion.php';

const PAYMENT_ASIA_PROVIDER_LABEL = 'Payment Asia';
const PAYMENT_ASIA_PROVIDER_ALIAS = 'payment asia';
const PAYMENT_ASIA_TEMPLATE_CATEGORY = 'Bank Account Details';
const PAYMENT_ASIA_DEFAULT_DEPOSIT_FIXED_FEE = 0.00;
const PAYMENT_ASIA_DEFAULT_WITHDRAWAL_FIXED_FEE = 0.00;

function paymentAsiaOut($message) {
    echo $message . PHP_EOL;
}

function getPaymentAsiaGatewayConfigs() {
    return [
        'PHP' => [
            'currency' => 'PHP',
            'gatewayKey' => 'pa-php',
            'gatewayName' => 'Payment Asia PHP',
            'methodName' => 'Payment Asia PHP',
            'shortCode' => 'PHP',
            'displayOrder' => 21
        ],
        'IDR' => [
            'currency' => 'IDR',
            'gatewayKey' => 'pa-idr',
            'gatewayName' => 'Payment Asia IDR',
            'methodName' => 'Payment Asia IDR',
            'shortCode' => 'IDR',
            'displayOrder' => 22,
            'amountDecimalPlaces' => 0
        ],
        'MYR' => [
            'currency' => 'MYR',
            'gatewayKey' => 'pa-myr',
            'gatewayName' => 'Payment Asia MYR',
            'methodName' => 'Payment Asia MYR',
            'shortCode' => 'MYR',
            'displayOrder' => 23
        ],
        'VND' => [
            'currency' => 'VND',
            'gatewayKey' => 'pa-vnd',
            'gatewayName' => 'Payment Asia VND',
            'methodName' => 'Payment Asia VND',
            'shortCode' => 'VND',
            'displayOrder' => 24,
            'amountDecimalPlaces' => 0
        ]
    ];
}

function getPaymentAsiaDepositQuestions() {
    return [
        [
            'name' => 'first_name',
            'hintText' => 'First name used for the Payment Asia deposit profile.',
            'questionType' => 'text',
            'validationRules' => 'required|min:1|max:100',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
            'isLocked' => 1
        ],
        [
            'name' => 'last_name',
            'hintText' => 'Last name used for the Payment Asia deposit profile.',
            'questionType' => 'text',
            'validationRules' => 'required|min:1|max:100',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
            'isLocked' => 1
        ],
        [
            'name' => 'phone',
            'hintText' => 'Phone number linked to the Payment Asia account.',
            'questionType' => 'tel',
            'validationRules' => 'required|max:30',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
            'isLocked' => 1
        ],
        [
            'name' => 'email',
            'hintText' => 'Email used for the Payment Asia account.',
            'questionType' => 'email',
            'validationRules' => 'required|email|max:255',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
            'isLocked' => 1
        ]
    ];
}

function getPaymentAsiaWithdrawSupportQuestions() {
    return [
        [
            'name' => 'bank_name',
            'hintText' => 'Bank name option list can be configured later.',
            'questionType' => 'single_choice',
            'validationRules' => 'required',
            'options' => [],
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
            'isLocked' => 1
        ],
        [
            'name' => 'first_name',
            'hintText' => 'First name of the beneficiary.',
            'questionType' => 'text',
            'validationRules' => 'required|min:1|max:100',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
            'isLocked' => 1
        ],
        [
            'name' => 'last_name',
            'hintText' => 'Last name of the beneficiary.',
            'questionType' => 'text',
            'validationRules' => 'required|min:1|max:100',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
            'isLocked' => 1
        ],
        [
            'name' => 'middle_name',
            'hintText' => 'Middle name of the beneficiary, if any.',
            'questionType' => 'text',
            'validationRules' => 'max:100',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
            'isLocked' => 1
        ],
        [
            'name' => 'email',
            'hintText' => 'Email used for the Payment Asia account.',
            'questionType' => 'email',
            'validationRules' => 'required|email|max:255',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
            'isLocked' => 1
        ],
        [
            'name' => 'phone',
            'hintText' => 'Phone number linked to the Payment Asia account.',
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

function getPaymentAsiaWithdrawalTemplateQuestions() {
    return [
        [
            'scope' => 'bank_name',
            'questionText' => 'Which bank name should receive the withdrawal?',
            'helpText' => 'Bank name option list can be configured later.',
            'questionType' => 'single_choice',
            'validationRules' => 'required',
            'options' => [],
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
            'scope' => 'middle_name',
            'questionText' => 'What is the beneficiary middle name?',
            'helpText' => 'Leave empty if the bank account does not use a middle name.',
            'questionType' => 'text',
            'validationRules' => 'max:100',
            'options' => null,
            'isRequired' => 0
        ],
        [
            'scope' => 'email',
            'questionText' => 'What is the beneficiary email address?',
            'helpText' => 'Use the email address tied to the Payment Asia account.',
            'questionType' => 'email',
            'validationRules' => 'required|email|max:255',
            'options' => null,
            'isRequired' => 1
        ],
        [
            'scope' => 'phone',
            'questionText' => 'What is the beneficiary phone number?',
            'helpText' => 'Use the phone number tied to the Payment Asia account.',
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

function getPaymentAsiaCliSecrets() {
    $options = getopt('', ['currency::', 'app-id::', 'secret::']);
    return [
        'currency' => isset($options['currency']) ? strtoupper(trim((string)$options['currency'])) : null,
        'appId' => trim((string)($options['app-id'] ?? '')),
        'secret' => trim((string)($options['secret'] ?? ''))
    ];
}

function runPaymentAsiaGatewayInit(array $config, $appId, $secret) {
    $currency = strtoupper(trim((string)($config['currency'] ?? '')));
    $gatewayKey = trim((string)($config['gatewayKey'] ?? ''));
    $gatewayName = trim((string)($config['gatewayName'] ?? ''));
    $methodName = trim((string)($config['methodName'] ?? $gatewayName));
    $shortCode = trim((string)($config['shortCode'] ?? $currency));
    $displayOrder = isset($config['displayOrder']) ? (int)$config['displayOrder'] : 0;
    $amountDecimalPlaces = isset($config['amountDecimalPlaces']) ? (int)$config['amountDecimalPlaces'] : 2;

    if ($currency === '' || $gatewayKey === '' || $gatewayName === '') {
        throw new InvalidArgumentException('Payment Asia config is incomplete.');
    }

    $db = Database::getInstance();
    $templateModel = new WithdrawalVerificationTemplate();
    $categoryModel = new WithdrawalVerificationQuestionCategory();
    $questionModel = new WithdrawalVerificationQuestion();
    $questionOptionModel = new WithdrawalVerificationQuestionOption();
    $paymentSupportQuestionModel = new PaymentSupportQuestion();

    $templateName = PAYMENT_ASIA_PROVIDER_LABEL . ' ' . $currency . ' Withdrawal Verification';
    $templateDescription = 'Locked withdrawal verification fields for Payment Asia ' . $currency . '.';
    $depositQuestions = getPaymentAsiaDepositQuestions();
    $withdrawSupportQuestions = getPaymentAsiaWithdrawSupportQuestions();
    $withdrawTemplateQuestions = getPaymentAsiaWithdrawalTemplateQuestions();
    $supportQuestions = array_merge($depositQuestions, $withdrawSupportQuestions);
    $appConfig = require __DIR__ . '/../config/app.php';
    $backendBaseUrl = rtrim((string)($appConfig['file_base_url'] ?? ''), '/');
    if ($backendBaseUrl !== '') {
        $backendBaseUrl = preg_replace('#/index\.php$#i', '', $backendBaseUrl);
    }
    $notifyUrl = $backendBaseUrl !== ''
        ? $backendBaseUrl . '/index.php?path=api/callback/payment-asia/deposit'
        : '';

    try {
        $db->beginTransaction();

        paymentAsiaOut('=== ' . $gatewayName . ' Init ===');

        $gateway = $db->fetchOne(
            "SELECT * FROM paymentGatewaySettings WHERE gatewayKey = :gatewayKey LIMIT 1",
            ['gatewayKey' => $gatewayKey]
        );

        $gatewayData = [
            'gatewayKey' => $gatewayKey,
            'gatewayName' => $gatewayName,
            'iconClass' => 'fas fa-money-check-dollar',
            'integration' => 1,
            'isEnabled' => 1,
            'isDepositEnabled' => 1,
            'isWithdrawalEnabled' => 1,
            'processingTime' => '1-3 business days',
            'environment' => 'production',
            'appId' => null,
            'apiKey' => $appId,
            'secretKey' => $secret,
            'merchantName' => PAYMENT_ASIA_PROVIDER_ALIAS,
            'webhookUrl' => null,
            'returnUrl' => null,
            'supportedFiatCurrencies' => json_encode([$currency], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'supportedCryptoCurrencies' => null,
            'amountDecimalPlaces' => $amountDecimalPlaces,
            'configData' => json_encode([
                'deposit_url' => 'https://payment.pa-sys.com/app/page/{MerchantToken}',
                'withdrawal_url' => 'https://gateway.pa-sys.com/{MerchantToken}/payout/v1/request',
                'notify_url' => $notifyUrl,
                'provider' => PAYMENT_ASIA_PROVIDER_ALIAS,
                'providerKey' => 'payment_asia',
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
            paymentAsiaOut("[OK] paymentGatewaySettings inserted: id={$gatewayId}");
        } else {
            $db->update('paymentGatewaySettings', $gatewayData, 'id = :id', ['id' => $gateway['id']]);
            $gateway = $db->fetchOne(
                "SELECT * FROM paymentGatewaySettings WHERE id = :id LIMIT 1",
                ['id' => $gateway['id']]
            );
            paymentAsiaOut("[SKIP] paymentGatewaySettings already exists, normalized: id={$gateway['id']}");
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
            'notes' => 'Initialized by Payment Asia bootstrap script for ' . $currency . '.',
            'updatedBy' => null
        ];

        if (!$gatewayFundingSetting) {
            $gatewayFundingSettingId = $db->insert('paymentGatewayFundingSettings', array_merge(
                ['gatewaySettingId' => (int)$gateway['id']],
                $gatewayFundingData
            ));
            paymentAsiaOut("[OK] paymentGatewayFundingSettings inserted: id={$gatewayFundingSettingId}");
        } else {
            $db->update(
                'paymentGatewayFundingSettings',
                $gatewayFundingData,
                'id = :id',
                ['id' => $gatewayFundingSetting['id']]
            );
            $gatewayFundingSettingId = (int)$gatewayFundingSetting['id'];
            paymentAsiaOut("[SKIP] paymentGatewayFundingSettings already exists, normalized: id={$gatewayFundingSettingId}");
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
            'fixed' => PAYMENT_ASIA_DEFAULT_DEPOSIT_FIXED_FEE,
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
            'fixed' => PAYMENT_ASIA_DEFAULT_WITHDRAWAL_FIXED_FEE,
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
            'iconClass' => 'fas fa-money-check-dollar',
            'shortCode' => $shortCode,
            'networkName' => PAYMENT_ASIA_PROVIDER_LABEL,
            'country' => null,
            'minPurchaseAmount' => null,
            'maxPurchaseAmount' => null,
            'isDepositEnabled' => 1,
            'isWithdrawalEnabled' => 1,
            'processingTime' => '1-3 business days',
            'displayOrder' => $displayOrder
        ];

        if (!$paymentMethod) {
            $paymentMethodId = $db->insert('paymentMethods', $paymentMethodData);
            $paymentMethod = $db->fetchOne(
                "SELECT * FROM paymentMethods WHERE id = :id LIMIT 1",
                ['id' => $paymentMethodId]
            );
            paymentAsiaOut("[OK] paymentMethods inserted: id={$paymentMethodId}");
        } else {
            $db->update('paymentMethods', $paymentMethodData, 'id = :id', ['id' => $paymentMethod['id']]);
            $paymentMethod = $db->fetchOne(
                "SELECT * FROM paymentMethods WHERE id = :id LIMIT 1",
                ['id' => $paymentMethod['id']]
            );
            paymentAsiaOut("[SKIP] paymentMethods already exists, normalized: id={$paymentMethod['id']}");
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
            paymentAsiaOut("[OK] withdrawalVerificationTemplates inserted: id={$templateId}");
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
            paymentAsiaOut("[SKIP] withdrawalVerificationTemplates already exists, normalized: id={$template['id']}");
        }

        $category = $categoryModel->findOne([
            'templateId' => $template['id'],
            'categoryName' => PAYMENT_ASIA_TEMPLATE_CATEGORY
        ]);

        if (!$category) {
            $categoryId = $categoryModel->createCategory([
                'templateId' => (int)$template['id'],
                'categoryName' => PAYMENT_ASIA_TEMPLATE_CATEGORY,
                'description' => 'Locked Payment Asia bank withdrawal fields.',
                'displayOrder' => 1,
                'isExpanded' => 1,
                'isActive' => 1,
                'isLocked' => 1
            ]);
            $category = $categoryModel->findById($categoryId);
            paymentAsiaOut("[OK] withdrawalVerificationQuestionCategories inserted: id={$categoryId}");
        } else {
            $categoryModel->update($category['id'], [
                'description' => 'Locked Payment Asia bank withdrawal fields.',
                'displayOrder' => 1,
                'isExpanded' => 1,
                'isActive' => 1,
                'isLocked' => 1
            ]);
            $category = $categoryModel->findById($category['id']);
            paymentAsiaOut("[SKIP] withdrawalVerificationQuestionCategories already exists, normalized: id={$category['id']}");
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
                paymentAsiaOut("[OK] withdrawal question inserted: scope={$questionSpec['scope']} id={$questionId}");
            } else {
                $questionModel->update($existing['id'], $questionData);
                if ($questionSpec['questionType'] === 'single_choice') {
                    $questionOptionModel->updateQuestionOptions($existing['id'], $questionSpec['options'] ?? []);
                }
                paymentAsiaOut("[SKIP] withdrawal question already exists, normalized: scope={$questionSpec['scope']} id={$existing['id']}");
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
                paymentAsiaOut("[OK] paymentSupportQuestions inserted: name={$supportQuestion['name']} scope={$supportQuestion['scope']} id={$supportQuestionId}");
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
                paymentAsiaOut("[SKIP] paymentSupportQuestions already exists, normalized: name={$supportQuestion['name']} scope={$supportQuestion['scope']} id={$existingSupportQuestion['id']}");
            }
        }

        $existingSupportQuestions = $paymentSupportQuestionModel->getAdminQuestions((int)$gateway['id']);
        foreach ($existingSupportQuestions as $existingSupportQuestion) {
            $key = ($existingSupportQuestion['name'] ?? '') . '|' . ($existingSupportQuestion['scope'] ?? '');
            if (in_array($key, $desiredSupportQuestionKeys, true)) {
                continue;
            }

            $paymentSupportQuestionModel->delete((int)$existingSupportQuestion['id']);
            paymentAsiaOut("[CLEAN] paymentSupportQuestions removed: name={$existingSupportQuestion['name']} scope={$existingSupportQuestion['scope']} id={$existingSupportQuestion['id']}");
        }

        $db->commit();

        paymentAsiaOut('');
        paymentAsiaOut('[DONE] Payment Asia bootstrap finished successfully.');
        paymentAsiaOut('Gateway ID: ' . $gateway['id']);
        paymentAsiaOut('Payment Method ID: ' . $paymentMethod['id']);
        paymentAsiaOut('Template ID: ' . $template['id']);
        paymentAsiaOut('Category ID: ' . $category['id']);

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
    $cliSecrets = getPaymentAsiaCliSecrets();
    $gatewayConfigs = getPaymentAsiaGatewayConfigs();

    try {
        if ($cliSecrets['currency'] !== null && $cliSecrets['currency'] !== '') {
            $currency = $cliSecrets['currency'];
            if (!isset($gatewayConfigs[$currency])) {
                throw new InvalidArgumentException('Unsupported currency: ' . $currency);
            }

            runPaymentAsiaGatewayInit($gatewayConfigs[$currency], $cliSecrets['appId'], $cliSecrets['secret']);
        } else {
            foreach ($gatewayConfigs as $config) {
                runPaymentAsiaGatewayInit($config, $cliSecrets['appId'], $cliSecrets['secret']);
                paymentAsiaOut('');
            }
        }

        exit(0);
    } catch (Throwable $e) {
        paymentAsiaOut('[ERROR] ' . $e->getMessage());
        if ($e instanceof InvalidArgumentException) {
            paymentAsiaOut('Usage: php paymentasiagatewayinit.php [--app-id=YOUR_APP_ID] [--secret=YOUR_SECRET] [--currency=PHP|IDR|MYR|VND]');
        }
        exit(1);
    }
}
