<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplate.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionCategory.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestion.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionOption.php';
require_once __DIR__ . '/../models/PaymentSupportQuestion.php';
require_once __DIR__ . '/../services/FivePayService.php';

const FIVEPAY_PROVIDER_LABEL = '5Pay';
const FIVEPAY_PROVIDER_KEY = '5pay';
const FIVEPAY_TEMPLATE_CATEGORY = 'Bank Account Details';
const FIVEPAY_DEFAULT_DEPOSIT_FIXED_FEE = 0.00;
const FIVEPAY_DEFAULT_WITHDRAWAL_FIXED_FEE = 0.00;

function fivePayOut($message) {
    echo $message . PHP_EOL;
}

function getFivePayGatewayConfig() {
    return [
        'currency' => 'HKD',
        'gatewayKey' => '5pay-hkd',
        'gatewayName' => '5Pay HKD',
        'methodName' => '5Pay HKD FPS',
        'shortCode' => 'HKD',
        'displayOrder' => 28,
        'amountDecimalPlaces' => 2
    ];
}

function getFivePayHkdBankRows() {
    return FivePayService::hkdPayoutBanks();
}

function getFivePaySupportBankOptions() {
    return array_values(array_map(static function ($row) {
        return [
            'label' => $row['name'],
            'value' => $row['code']
        ];
    }, getFivePayHkdBankRows()));
}

function getFivePayTemplateBankOptions() {
    return array_values(array_map(static function ($row) {
        return [
            'optionLabel' => $row['name'],
            'optionValue' => $row['code']
        ];
    }, getFivePayHkdBankRows()));
}

function getFivePayDepositQuestions() {
    return [
        [
            'name' => 'first_name',
            'hintText' => 'Payer first name as registered with the bank.',
            'questionType' => 'text',
            'validationRules' => 'required|min:1|max:100',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
            'isLocked' => 1
        ],
        [
            'name' => 'last_name',
            'hintText' => 'Payer last name as registered with the bank.',
            'questionType' => 'text',
            'validationRules' => 'required|min:1|max:100',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
            'isLocked' => 1
        ]
    ];
}

function getFivePayWithdrawSupportQuestions() {
    return [
        [
            'name' => 'bank_code',
            'hintText' => 'Beneficiary HKD bank. Use Faster Payment System for FPS payouts.',
            'questionType' => 'single_choice',
            'validationRules' => 'required',
            'options' => getFivePaySupportBankOptions(),
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
            'isLocked' => 1
        ],
        [
            'name' => 'account_name',
            'hintText' => 'Beneficiary name exactly as registered at the bank.',
            'questionType' => 'text',
            'validationRules' => 'required|min:1|max:100',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
            'isLocked' => 1
        ],
        [
            'name' => 'account_number',
            'hintText' => 'Beneficiary bank account number or FPS identifier.',
            'questionType' => 'text',
            'validationRules' => 'required|min:4|max:50',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
            'isLocked' => 1
        ],
        [
            'name' => 'phone',
            'hintText' => 'Required for FPS: phone number or FPS account number.',
            'questionType' => 'text',
            'validationRules' => 'max:50',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
            'isLocked' => 1
        ]
    ];
}

function getFivePayWithdrawalTemplateQuestions() {
    return [
        [
            'scope' => 'bank_code',
            'questionText' => 'Which bank should receive the withdrawal?',
            'helpText' => 'Use Faster Payment System for HKD FPS payouts.',
            'questionType' => 'single_choice',
            'validationRules' => 'required',
            'options' => getFivePayTemplateBankOptions(),
            'isRequired' => 1
        ],
        [
            'scope' => 'account_name',
            'questionText' => 'What is the beneficiary name?',
            'helpText' => 'Use the bank beneficiary name exactly as registered.',
            'questionType' => 'text',
            'validationRules' => 'required|min:1|max:100',
            'options' => null,
            'isRequired' => 1
        ],
        [
            'scope' => 'account_number',
            'questionText' => 'What is the beneficiary account number?',
            'helpText' => 'Enter the full bank account number or FPS identifier.',
            'questionType' => 'text',
            'validationRules' => 'required|min:4|max:50',
            'options' => null,
            'isRequired' => 1
        ],
        [
            'scope' => 'phone',
            'questionText' => 'What is the FPS phone or account number?',
            'helpText' => 'Required when paying out via Faster Payment System.',
            'questionType' => 'text',
            'validationRules' => 'max:50',
            'options' => null,
            'isRequired' => 0
        ]
    ];
}

function getFivePayCliOptions() {
    $options = getopt('', [
        'merchant-id::',
        'private-key::',
        'platform-public-key::',
        'base-url::',
        'environment::'
    ]);
    return [
        'merchantId' => trim((string)($options['merchant-id'] ?? '')),
        'privateKey' => trim((string)($options['private-key'] ?? '')),
        'platformPublicKey' => trim((string)($options['platform-public-key'] ?? '')),
        'baseUrl' => trim((string)($options['base-url'] ?? FivePayService::DEFAULT_BASE_URL)),
        'environment' => trim((string)($options['environment'] ?? 'sandbox'))
    ];
}

function runFivePayGatewayInit(array $config, array $cli) {
    $currency = strtoupper(trim((string)($config['currency'] ?? '')));
    $gatewayKey = trim((string)($config['gatewayKey'] ?? ''));
    $gatewayName = trim((string)($config['gatewayName'] ?? ''));
    $methodName = trim((string)($config['methodName'] ?? $gatewayName));
    $shortCode = trim((string)($config['shortCode'] ?? $currency));
    $displayOrder = isset($config['displayOrder']) ? (int)$config['displayOrder'] : 0;
    $amountDecimalPlaces = isset($config['amountDecimalPlaces']) ? (int)$config['amountDecimalPlaces'] : 2;

    if ($currency === '' || $gatewayKey === '' || $gatewayName === '') {
        throw new InvalidArgumentException('5Pay config is incomplete.');
    }

    $db = Database::getInstance();
    $templateModel = new WithdrawalVerificationTemplate();
    $categoryModel = new WithdrawalVerificationQuestionCategory();
    $questionModel = new WithdrawalVerificationQuestion();
    $questionOptionModel = new WithdrawalVerificationQuestionOption();
    $paymentSupportQuestionModel = new PaymentSupportQuestion();

    $templateName = FIVEPAY_PROVIDER_LABEL . ' ' . $currency . ' Withdrawal Verification';
    $templateDescription = 'Locked withdrawal verification fields for 5Pay ' . $currency . '.';
    $depositQuestions = getFivePayDepositQuestions();
    $withdrawSupportQuestions = getFivePayWithdrawSupportQuestions();
    $withdrawTemplateQuestions = getFivePayWithdrawalTemplateQuestions();
    $supportQuestions = array_merge($depositQuestions, $withdrawSupportQuestions);

    $appConfig = require __DIR__ . '/../config/app.php';
    $backendBaseUrl = rtrim((string)($appConfig['file_base_url'] ?? ''), '/');
    if ($backendBaseUrl !== '') {
        $backendBaseUrl = preg_replace('#/index\.php$#i', '', $backendBaseUrl);
    }
    $notifyUrl = $backendBaseUrl !== ''
        ? $backendBaseUrl . '/api/callback/5pay/deposit'
        : '';

    try {
        $db->beginTransaction();

        fivePayOut('=== ' . $gatewayName . ' Init ===');

        $gateway = $db->fetchOne(
            "SELECT * FROM paymentGatewaySettings WHERE gatewayKey = :gatewayKey LIMIT 1",
            ['gatewayKey' => $gatewayKey]
        );
        if (!$gateway) {
            $gateway = $db->fetchOne(
                "SELECT * FROM paymentGatewaySettings WHERE gatewayKey = :gatewayKey LIMIT 1",
                ['gatewayKey' => 'spay-hkd']
            );
        }

        $existingConfig = [];
        if ($gateway && !empty($gateway['configData'])) {
            $decoded = json_decode((string)$gateway['configData'], true);
            if (is_array($decoded)) {
                $existingConfig = $decoded;
            }
        }

        $platformPublicKey = $cli['platformPublicKey'] !== ''
            ? $cli['platformPublicKey']
            : trim((string)($existingConfig['platform_public_key'] ?? ''));
        $privateKey = $cli['privateKey'] !== ''
            ? $cli['privateKey']
            : trim((string)($existingConfig['private_key'] ?? (($gateway['secretKey'] ?? ''))));

        $configPayload = [
            'providerKey' => FIVEPAY_PROVIDER_KEY,
            'base_url' => $cli['baseUrl'] !== '' ? $cli['baseUrl'] : FivePayService::DEFAULT_BASE_URL,
            'private_key' => $privateKey,
            'platform_public_key' => $platformPublicKey,
            'currency_code' => $currency,
            'deposit_method' => FivePayService::DEPOSIT_METHOD_F2F,
            'deposit_bank_code' => FivePayService::BANK_FPS,
            'wallet' => FivePayService::WALLET_F2F,
            'token' => FivePayService::TOKEN_HKD
        ];

        $gatewayData = [
            'gatewayKey' => $gatewayKey,
            'gatewayName' => $gatewayName,
            'iconClass' => 'fas fa-dollar-sign',
            'integration' => 1,
            'isEnabled' => (
                $cli['merchantId'] !== ''
                && $privateKey !== ''
                && $platformPublicKey !== ''
            ) ? 1 : 0,
            'isDepositEnabled' => 1,
            'isWithdrawalEnabled' => 1,
            'processingTime' => 'Instant - 1 business day',
            'environment' => $cli['environment'] === 'production' ? 'production' : 'sandbox',
            'appId' => null,
            'apiKey' => $cli['merchantId'] !== '' ? $cli['merchantId'] : null,
            'secretKey' => null,
            'merchantName' => FIVEPAY_PROVIDER_KEY,
            'webhookUrl' => $notifyUrl !== '' ? $notifyUrl : null,
            'returnUrl' => null,
            'supportedFiatCurrencies' => json_encode([$currency], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'supportedCryptoCurrencies' => null,
            'amountDecimalPlaces' => $amountDecimalPlaces,
            'configData' => json_encode($configPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updatedBy' => null
        ];

        if (!$gateway) {
            $gatewayId = $db->insert('paymentGatewaySettings', $gatewayData);
            $gateway = $db->fetchOne(
                "SELECT * FROM paymentGatewaySettings WHERE id = :id LIMIT 1",
                ['id' => $gatewayId]
            );
            fivePayOut("[OK] paymentGatewaySettings inserted: id={$gatewayId}");
        } else {
            if ($cli['merchantId'] === '') {
                unset($gatewayData['apiKey']);
            }
            if ($privateKey === '') {
                unset($configPayload['private_key']);
            }
            $gatewayData['configData'] = json_encode(
                array_merge($existingConfig, $configPayload),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            $hasCredentials = trim((string)($gatewayData['apiKey'] ?? $gateway['apiKey'] ?? '')) !== ''
                && trim((string)(($privateKey !== '' ? $privateKey : ($existingConfig['private_key'] ?? '')))) !== ''
                && trim((string)($platformPublicKey !== '' ? $platformPublicKey : ($existingConfig['platform_public_key'] ?? ''))) !== '';
            $gatewayData['isEnabled'] = $hasCredentials ? 1 : (int)($gateway['isEnabled'] ?? 0);
            $db->update('paymentGatewaySettings', $gatewayData, 'id = :id', ['id' => $gateway['id']]);
            $gateway = $db->fetchOne(
                "SELECT * FROM paymentGatewaySettings WHERE id = :id LIMIT 1",
                ['id' => $gateway['id']]
            );
            fivePayOut("[SKIP] paymentGatewaySettings already exists, normalized: id={$gateway['id']}");
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
            'notes' => 'Initialized by 5Pay bootstrap script for ' . $currency . '.',
            'updatedBy' => null
        ];

        if (!$gatewayFundingSetting) {
            $gatewayFundingSettingId = $db->insert('paymentGatewayFundingSettings', array_merge(
                ['gatewaySettingId' => (int)$gateway['id']],
                $gatewayFundingData
            ));
            fivePayOut("[OK] paymentGatewayFundingSettings inserted: id={$gatewayFundingSettingId}");
        } else {
            $db->update(
                'paymentGatewayFundingSettings',
                $gatewayFundingData,
                'id = :id',
                ['id' => $gatewayFundingSetting['id']]
            );
            $gatewayFundingSettingId = (int)$gatewayFundingSetting['id'];
            fivePayOut("[SKIP] paymentGatewayFundingSettings already exists, normalized: id={$gatewayFundingSettingId}");
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
            'fixed' => FIVEPAY_DEFAULT_DEPOSIT_FIXED_FEE,
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
            'fixed' => FIVEPAY_DEFAULT_WITHDRAWAL_FIXED_FEE,
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
            'iconClass' => 'fas fa-dollar-sign',
            'shortCode' => $shortCode,
            'networkName' => FIVEPAY_PROVIDER_LABEL,
            'country' => 'HK',
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
            fivePayOut("[OK] paymentMethods inserted: id={$paymentMethodId}");
        } else {
            $db->update('paymentMethods', $paymentMethodData, 'id = :id', ['id' => $paymentMethod['id']]);
            $paymentMethod = $db->fetchOne(
                "SELECT * FROM paymentMethods WHERE id = :id LIMIT 1",
                ['id' => $paymentMethod['id']]
            );
            fivePayOut("[SKIP] paymentMethods already exists, normalized: id={$paymentMethod['id']}");
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
            fivePayOut("[OK] withdrawalVerificationTemplates inserted: id={$templateId}");
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
            fivePayOut("[SKIP] withdrawalVerificationTemplates already exists, normalized: id={$template['id']}");
        }

        $category = $categoryModel->findOne([
            'templateId' => $template['id'],
            'categoryName' => FIVEPAY_TEMPLATE_CATEGORY
        ]);

        if (!$category) {
            $categoryId = $categoryModel->createCategory([
                'templateId' => (int)$template['id'],
                'categoryName' => FIVEPAY_TEMPLATE_CATEGORY,
                'description' => 'Locked 5Pay bank withdrawal fields.',
                'displayOrder' => 1,
                'isExpanded' => 1,
                'isActive' => 1,
                'isLocked' => 1
            ]);
            $category = $categoryModel->findById($categoryId);
            fivePayOut("[OK] withdrawalVerificationQuestionCategories inserted: id={$categoryId}");
        } else {
            $categoryModel->update($category['id'], [
                'description' => 'Locked 5Pay bank withdrawal fields.',
                'displayOrder' => 1,
                'isExpanded' => 1,
                'isActive' => 1,
                'isLocked' => 1
            ]);
            $category = $categoryModel->findById($category['id']);
            fivePayOut("[SKIP] withdrawalVerificationQuestionCategories already exists, normalized: id={$category['id']}");
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
                fivePayOut("[OK] withdrawal question inserted: scope={$questionSpec['scope']} id={$questionId}");
            } else {
                $questionModel->update($existing['id'], $questionData);
                if ($questionSpec['questionType'] === 'single_choice') {
                    $questionOptionModel->updateQuestionOptions($existing['id'], $questionSpec['options'] ?? []);
                }
                fivePayOut("[SKIP] withdrawal question already exists, normalized: scope={$questionSpec['scope']} id={$existing['id']}");
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
                fivePayOut("[OK] paymentSupportQuestions inserted: name={$supportQuestion['name']} scope={$supportQuestion['scope']} id={$supportQuestionId}");
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
                fivePayOut("[SKIP] paymentSupportQuestions already exists, normalized: name={$supportQuestion['name']} scope={$supportQuestion['scope']} id={$existingSupportQuestion['id']}");
            }
        }

        $existingSupportQuestions = $paymentSupportQuestionModel->getAdminQuestions((int)$gateway['id']);
        foreach ($existingSupportQuestions as $existingSupportQuestion) {
            $key = ($existingSupportQuestion['name'] ?? '') . '|' . ($existingSupportQuestion['scope'] ?? '');
            if (in_array($key, $desiredSupportQuestionKeys, true)) {
                continue;
            }

            $paymentSupportQuestionModel->delete((int)$existingSupportQuestion['id']);
            fivePayOut("[CLEAN] paymentSupportQuestions removed: name={$existingSupportQuestion['name']} scope={$existingSupportQuestion['scope']} id={$existingSupportQuestion['id']}");
        }

        $db->commit();

        fivePayOut('');
        fivePayOut('[DONE] 5Pay bootstrap finished successfully.');
        fivePayOut('Gateway ID: ' . $gateway['id']);
        fivePayOut('Payment Method ID: ' . $paymentMethod['id']);
        fivePayOut('Template ID: ' . $template['id']);
        fivePayOut('Category ID: ' . $category['id']);
        if (empty($gateway['apiKey']) || $privateKey === '' || $platformPublicKey === '') {
            fivePayOut('[NOTE] Gateway created DISABLED: credentials incomplete.');
            fivePayOut('       Re-run with --merchant-id/--private-key/--platform-public-key.');
        } else {
            $storedConfig = json_decode((string)($gateway['configData'] ?? ''), true);
            $storedPrivateKey = is_array($storedConfig) ? trim((string)($storedConfig['private_key'] ?? '')) : '';
            if ($storedPrivateKey === '' || strlen($storedPrivateKey) < strlen($privateKey)) {
                fivePayOut('[WARN] private_key may be truncated in DB. Confirm configData.private_key length.');
            }
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
        runFivePayGatewayInit(getFivePayGatewayConfig(), getFivePayCliOptions());
        exit(0);
    } catch (Throwable $e) {
        fivePayOut('[ERROR] ' . $e->getMessage());
        if ($e instanceof InvalidArgumentException) {
            fivePayOut('Usage: php fivepaygatewayinit.php [--merchant-id=ID] [--private-key=KEY] [--platform-public-key=KEY] [--base-url=URL] [--environment=sandbox|production]');
        }
        exit(1);
    }
}
