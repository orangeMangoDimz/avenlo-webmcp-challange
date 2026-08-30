<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplate.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionCategory.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestion.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionOption.php';
require_once __DIR__ . '/../models/PaymentSupportQuestion.php';
require_once __DIR__ . '/../services/CvPayService.php';

const CVPAY_PROVIDER_LABEL = 'CVPay';
const CVPAY_PROVIDER_KEY = 'cvpay';
const CVPAY_TEMPLATE_CATEGORY = 'Bank Account Details';
const CVPAY_DEFAULT_DEPOSIT_FIXED_FEE = 0.00;
const CVPAY_DEFAULT_WITHDRAWAL_FIXED_FEE = 0.00;

function cvpayOut($message) {
    echo $message . PHP_EOL;
}

function getCvPayGatewayConfig() {
    return [
        'currency' => 'VND',
        'gatewayKey' => 'cvpay-vnd',
        'gatewayName' => 'CVPay VND',
        'methodName' => 'CVPay VND',
        'shortCode' => 'VND',
        'displayOrder' => 27,
        'amountDecimalPlaces' => 0
    ];
}

function getCvPayWayCodeOptions() {
    return [
        ['value' => 'BANKACCOUNT', 'label' => 'Bank Account'],
        ['value' => 'ATMCARD', 'label' => 'ATM Card'],
    ];
}

function getCvPayVndBanksDataPath() {
    return __DIR__ . '/data/cvpay_vnd_banks.json';
}

function loadCvPayVndBankRows() {
    $path = getCvPayVndBanksDataPath();
    if (!is_file($path)) {
        throw new RuntimeException('CVPay VND bank list missing: ' . $path);
    }

    $decoded = json_decode((string)file_get_contents($path), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('CVPay VND bank list is not valid JSON: ' . $path);
    }

    $rows = [];
    $seen = [];
    foreach ($decoded as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $bankCode = strtoupper(trim((string)($item['bankCode'] ?? '')));
        $bankName = trim((string)($item['bankName'] ?? ''));
        if ($bankCode === '' || $bankName === '') {
            continue;
        }
        if (isset($seen[$bankCode])) {
            continue;
        }

        $seen[$bankCode] = true;
        $rows[] = [
            'code' => $bankCode,
            'name' => $bankName,
            'index' => $index
        ];
    }

    usort($rows, static function ($left, $right) {
        return strcmp($left['name'], $right['name']);
    });

    return $rows;
}

function getCvPaySupportBankOptions() {
    return array_values(array_map(static function ($row) {
        return [
            'label' => $row['name'],
            'value' => $row['code']
        ];
    }, loadCvPayVndBankRows()));
}

function getCvPayTemplateBankOptions() {
    return array_values(array_map(static function ($row) {
        return [
            'optionLabel' => $row['name'],
            'optionValue' => $row['code']
        ];
    }, loadCvPayVndBankRows()));
}

function getCvPayDepositQuestions() {
    return [
        [
            'name' => 'phone',
            'hintText' => 'Buyer mobile number (provide phone or email for CVPay deposits).',
            'questionType' => 'tel',
            'validationRules' => 'max:30',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
            'isLocked' => 1
        ],
        [
            'name' => 'email',
            'hintText' => 'Buyer email (provide phone or email for CVPay deposits).',
            'questionType' => 'email',
            'validationRules' => 'max:100',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
            'isLocked' => 1
        ],
        [
            'name' => 'buyer_name',
            'hintText' => 'Buyer name in uppercase English (optional).',
            'questionType' => 'text',
            'validationRules' => 'max:100',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
            'isLocked' => 1
        ]
    ];
}

function getCvPayWithdrawSupportQuestions() {
    return [
        [
            'name' => 'way_code',
            'hintText' => 'CVPay withdrawal channel: Bank Account or ATM Card.',
            'questionType' => 'single_choice',
            'validationRules' => 'required',
            'options' => getCvPayWayCodeOptions(),
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
            'isLocked' => 1
        ],
        [
            'name' => 'bank_name',
            'hintText' => 'Select the Vietnamese bank. CRM sends bankCode to CVPay as bankName.',
            'questionType' => 'single_choice',
            'validationRules' => 'required',
            'options' => getCvPaySupportBankOptions(),
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
            'isLocked' => 1
        ],
        [
            'name' => 'account_name',
            'hintText' => 'Beneficiary account holder name.',
            'questionType' => 'text',
            'validationRules' => 'required|min:2|max:100',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
            'isLocked' => 1
        ],
        [
            'name' => 'account_number',
            'hintText' => 'Beneficiary bank account or ATM card number.',
            'questionType' => 'text',
            'validationRules' => 'required|min:4|max:50',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
            'isLocked' => 1
        ]
    ];
}

function getCvPayWithdrawalTemplateQuestions() {
    return [
        [
            'scope' => 'way_code',
            'questionText' => 'Which withdrawal channel should be used?',
            'helpText' => 'Select Bank Account or ATM Card.',
            'questionType' => 'single_choice',
            'validationRules' => 'required',
            'options' => getCvPayWayCodeOptions(),
            'isRequired' => 1
        ],
        [
            'scope' => 'bank_name',
            'questionText' => 'Which bank should receive the withdrawal?',
            'helpText' => 'Search and select the Vietnamese bank. CRM sends bankCode to CVPay as bankName.',
            'questionType' => 'single_choice',
            'validationRules' => 'required',
            'options' => getCvPayTemplateBankOptions(),
            'isRequired' => 1
        ],
        [
            'scope' => 'account_name',
            'questionText' => 'What is the beneficiary account name?',
            'helpText' => 'Use the name registered with the bank.',
            'questionType' => 'text',
            'validationRules' => 'required|min:2|max:100',
            'options' => null,
            'isRequired' => 1
        ],
        [
            'scope' => 'account_number',
            'questionText' => 'What is the beneficiary account number?',
            'helpText' => 'Enter the full bank account or ATM card number.',
            'questionType' => 'text',
            'validationRules' => 'required|min:4|max:50',
            'options' => null,
            'isRequired' => 1
        ]
    ];
}

function getCvPayCliOptions() {
    $options = getopt('', [
        'mch-no::',
        'app-id::',
        'app-key::',
        'base-url::',
        'environment::'
    ]);
    return [
        'mchNo' => trim((string)($options['mch-no'] ?? '')),
        'appId' => trim((string)($options['app-id'] ?? '')),
        'appKey' => trim((string)($options['app-key'] ?? '')),
        'baseUrl' => trim((string)($options['base-url'] ?? CvPayService::DEFAULT_BASE_URL)),
        'environment' => trim((string)($options['environment'] ?? 'sandbox'))
    ];
}

function runCvPayGatewayInit(array $config, array $cli) {
    $currency = strtoupper(trim((string)($config['currency'] ?? '')));
    $gatewayKey = trim((string)($config['gatewayKey'] ?? ''));
    $gatewayName = trim((string)($config['gatewayName'] ?? ''));
    $methodName = trim((string)($config['methodName'] ?? $gatewayName));
    $shortCode = trim((string)($config['shortCode'] ?? $currency));
    $displayOrder = isset($config['displayOrder']) ? (int)$config['displayOrder'] : 0;
    $amountDecimalPlaces = isset($config['amountDecimalPlaces']) ? (int)$config['amountDecimalPlaces'] : 0;

    if ($currency === '' || $gatewayKey === '' || $gatewayName === '') {
        throw new InvalidArgumentException('CVPay config is incomplete.');
    }

    $db = Database::getInstance();
    $templateModel = new WithdrawalVerificationTemplate();
    $categoryModel = new WithdrawalVerificationQuestionCategory();
    $questionModel = new WithdrawalVerificationQuestion();
    $questionOptionModel = new WithdrawalVerificationQuestionOption();
    $paymentSupportQuestionModel = new PaymentSupportQuestion();

    $templateName = CVPAY_PROVIDER_LABEL . ' ' . $currency . ' Withdrawal Verification';
    $templateDescription = 'Locked withdrawal verification fields for CVPay ' . $currency . '.';
    $depositQuestions = getCvPayDepositQuestions();
    $withdrawSupportQuestions = getCvPayWithdrawSupportQuestions();
    $withdrawTemplateQuestions = getCvPayWithdrawalTemplateQuestions();
    $supportQuestions = array_merge($depositQuestions, $withdrawSupportQuestions);

    $appConfig = require __DIR__ . '/../config/app.php';
    $backendBaseUrl = rtrim((string)($appConfig['file_base_url'] ?? ''), '/');
    if ($backendBaseUrl !== '') {
        $backendBaseUrl = preg_replace('#/index\.php$#i', '', $backendBaseUrl);
    }
    $notifyUrl = $backendBaseUrl !== ''
        ? $backendBaseUrl . '/api/callback/cvpay/deposit'
        : '';

    try {
        $db->beginTransaction();

        cvpayOut('=== ' . $gatewayName . ' Init ===');

        $gateway = $db->fetchOne(
            "SELECT * FROM paymentGatewaySettings WHERE gatewayKey = :gatewayKey LIMIT 1",
            ['gatewayKey' => $gatewayKey]
        );

        $gatewayData = [
            'gatewayKey' => $gatewayKey,
            'gatewayName' => $gatewayName,
            'iconClass' => 'fas fa-dong-sign',
            'integration' => 1,
            'isEnabled' => (
                $cli['mchNo'] !== ''
                && $cli['appId'] !== ''
                && $cli['appKey'] !== ''
            ) ? 1 : 0,
            'isDepositEnabled' => 1,
            'isWithdrawalEnabled' => 1,
            'processingTime' => 'Instant - 1 business day',
            'environment' => $cli['environment'] === 'production' ? 'production' : 'sandbox',
            'appId' => $cli['appId'] !== '' ? $cli['appId'] : null,
            'apiKey' => $cli['mchNo'] !== '' ? $cli['mchNo'] : null,
            'secretKey' => $cli['appKey'] !== '' ? $cli['appKey'] : null,
            'merchantName' => strtolower(CVPAY_PROVIDER_LABEL),
            'webhookUrl' => $notifyUrl !== '' ? $notifyUrl : null,
            'returnUrl' => null,
            'supportedFiatCurrencies' => json_encode([$currency], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'supportedCryptoCurrencies' => null,
            'amountDecimalPlaces' => $amountDecimalPlaces,
            'configData' => json_encode([
                'providerKey' => CVPAY_PROVIDER_KEY,
                'base_url' => $cli['baseUrl'] !== '' ? $cli['baseUrl'] : CvPayService::DEFAULT_BASE_URL,
                'default_way_code' => CvPayService::DEFAULT_WAY_CODE,
                'currency' => CvPayService::DEFAULT_CURRENCY,
                'currency_code' => $currency
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updatedBy' => null
        ];

        if (!$gateway) {
            $gatewayId = $db->insert('paymentGatewaySettings', $gatewayData);
            $gateway = $db->fetchOne(
                "SELECT * FROM paymentGatewaySettings WHERE id = :id LIMIT 1",
                ['id' => $gatewayId]
            );
            cvpayOut("[OK] paymentGatewaySettings inserted: id={$gatewayId}");
        } else {
            if ($cli['mchNo'] === '') {
                unset($gatewayData['apiKey']);
            }
            if ($cli['appId'] === '') {
                unset($gatewayData['appId']);
            }
            if ($cli['appKey'] === '') {
                unset($gatewayData['secretKey']);
            }
            unset($gatewayData['isEnabled']);
            $db->update('paymentGatewaySettings', $gatewayData, 'id = :id', ['id' => $gateway['id']]);
            $gateway = $db->fetchOne(
                "SELECT * FROM paymentGatewaySettings WHERE id = :id LIMIT 1",
                ['id' => $gateway['id']]
            );
            cvpayOut("[SKIP] paymentGatewaySettings already exists, normalized: id={$gateway['id']}");
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
            'notes' => 'Initialized by CVPay bootstrap script for ' . $currency . '.',
            'updatedBy' => null
        ];

        if (!$gatewayFundingSetting) {
            $gatewayFundingSettingId = $db->insert('paymentGatewayFundingSettings', array_merge(
                ['gatewaySettingId' => (int)$gateway['id']],
                $gatewayFundingData
            ));
            cvpayOut("[OK] paymentGatewayFundingSettings inserted: id={$gatewayFundingSettingId}");
        } else {
            $db->update(
                'paymentGatewayFundingSettings',
                $gatewayFundingData,
                'id = :id',
                ['id' => $gatewayFundingSetting['id']]
            );
            $gatewayFundingSettingId = (int)$gatewayFundingSetting['id'];
            cvpayOut("[SKIP] paymentGatewayFundingSettings already exists, normalized: id={$gatewayFundingSettingId}");
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
            'fixed' => CVPAY_DEFAULT_DEPOSIT_FIXED_FEE,
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
            'fixed' => CVPAY_DEFAULT_WITHDRAWAL_FIXED_FEE,
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
            'iconClass' => 'fas fa-dong-sign',
            'shortCode' => $shortCode,
            'networkName' => CVPAY_PROVIDER_LABEL,
            'country' => 'VN',
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
            cvpayOut("[OK] paymentMethods inserted: id={$paymentMethodId}");
        } else {
            $db->update('paymentMethods', $paymentMethodData, 'id = :id', ['id' => $paymentMethod['id']]);
            $paymentMethod = $db->fetchOne(
                "SELECT * FROM paymentMethods WHERE id = :id LIMIT 1",
                ['id' => $paymentMethod['id']]
            );
            cvpayOut("[SKIP] paymentMethods already exists, normalized: id={$paymentMethod['id']}");
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
            cvpayOut("[OK] withdrawalVerificationTemplates inserted: id={$templateId}");
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
            cvpayOut("[SKIP] withdrawalVerificationTemplates already exists, normalized: id={$template['id']}");
        }

        $category = $categoryModel->findOne([
            'templateId' => $template['id'],
            'categoryName' => CVPAY_TEMPLATE_CATEGORY
        ]);

        if (!$category) {
            $categoryId = $categoryModel->createCategory([
                'templateId' => (int)$template['id'],
                'categoryName' => CVPAY_TEMPLATE_CATEGORY,
                'description' => 'Locked CVPay bank withdrawal fields.',
                'displayOrder' => 1,
                'isExpanded' => 1,
                'isActive' => 1,
                'isLocked' => 1
            ]);
            $category = $categoryModel->findById($categoryId);
            cvpayOut("[OK] withdrawalVerificationQuestionCategories inserted: id={$categoryId}");
        } else {
            $categoryModel->update($category['id'], [
                'description' => 'Locked CVPay bank withdrawal fields.',
                'displayOrder' => 1,
                'isExpanded' => 1,
                'isActive' => 1,
                'isLocked' => 1
            ]);
            $category = $categoryModel->findById($category['id']);
            cvpayOut("[SKIP] withdrawalVerificationQuestionCategories already exists, normalized: id={$category['id']}");
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
                cvpayOut("[OK] withdrawal question inserted: scope={$questionSpec['scope']} id={$questionId}");
            } else {
                $questionModel->update($existing['id'], $questionData);
                if ($questionSpec['questionType'] === 'single_choice') {
                    $questionOptionModel->updateQuestionOptions($existing['id'], $questionSpec['options'] ?? []);
                }
                cvpayOut("[SKIP] withdrawal question already exists, normalized: scope={$questionSpec['scope']} id={$existing['id']}");
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
                cvpayOut("[OK] paymentSupportQuestions inserted: name={$supportQuestion['name']} scope={$supportQuestion['scope']} id={$supportQuestionId}");
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
                cvpayOut("[SKIP] paymentSupportQuestions already exists, normalized: name={$supportQuestion['name']} scope={$supportQuestion['scope']} id={$existingSupportQuestion['id']}");
            }
        }

        $existingSupportQuestions = $paymentSupportQuestionModel->getAdminQuestions((int)$gateway['id']);
        foreach ($existingSupportQuestions as $existingSupportQuestion) {
            $key = ($existingSupportQuestion['name'] ?? '') . '|' . ($existingSupportQuestion['scope'] ?? '');
            if (in_array($key, $desiredSupportQuestionKeys, true)) {
                continue;
            }

            $paymentSupportQuestionModel->delete((int)$existingSupportQuestion['id']);
            cvpayOut("[CLEAN] paymentSupportQuestions removed: name={$existingSupportQuestion['name']} scope={$existingSupportQuestion['scope']} id={$existingSupportQuestion['id']}");
        }

        $db->commit();

        cvpayOut('');
        cvpayOut('[DONE] CVPay bootstrap finished successfully.');
        cvpayOut('Gateway ID: ' . $gateway['id']);
        cvpayOut('Payment Method ID: ' . $paymentMethod['id']);
        cvpayOut('[NEXT] php script/cvpay_sync_bank_options.php');
        cvpayOut('Template ID: ' . $template['id']);
        cvpayOut('Category ID: ' . $category['id']);
        if (empty($gateway['apiKey']) || empty($gateway['secretKey']) || empty($gateway['appId'])) {
            cvpayOut('[NOTE] Gateway created DISABLED: credentials incomplete.');
            cvpayOut('       Re-run with --mch-no/--app-id/--app-key.');
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
        runCvPayGatewayInit(getCvPayGatewayConfig(), getCvPayCliOptions());
        exit(0);
    } catch (Throwable $e) {
        cvpayOut('[ERROR] ' . $e->getMessage());
        if ($e instanceof InvalidArgumentException) {
            cvpayOut('Usage: php cvpaygatewayinit.php [--mch-no=NO] [--app-id=ID] [--app-key=KEY] [--base-url=URL] [--environment=sandbox|production]');
        }
        exit(1);
    }
}
