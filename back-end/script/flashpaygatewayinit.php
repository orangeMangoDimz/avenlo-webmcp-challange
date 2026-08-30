<?php
/**
 * FlashPay gateway bootstrap initializer (South Korea / KRW)
 *
 * Usage:
 *   php script/flashpaygatewayinit.php \
 *     --mch-no=M1784627073 \
 *     --app-id=6a5f3f81e4b046c54f200116 \
 *     --private-key=BASE64_OR_PEM \
 *     --platform-public-key=BASE64_OR_PEM \
 *     --base-url=https://pay.flashpay.fit
 *
 * Mapping:
 *   mch-no               -> paymentGatewaySettings.apiKey
 *   app-id               -> paymentGatewaySettings.appId
 *   private-key          -> paymentGatewaySettings.secretKey
 *   platform-public-key  -> configData.platform_public_key
 *   base-url             -> configData.base_url
 *
 * Bank codes: run flashpay_sync_bank_options.php after init (live querybanks).
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplate.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionCategory.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestion.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionOption.php';
require_once __DIR__ . '/../models/PaymentSupportQuestion.php';
require_once __DIR__ . '/../services/FlashPayService.php';

const FLASHPAY_PROVIDER_LABEL = 'FlashPay';
const FLASHPAY_PROVIDER_KEY = 'flashpay';
const FLASHPAY_TEMPLATE_CATEGORY = 'Bank Account Details';
const FLASHPAY_DEFAULT_DEPOSIT_FIXED_FEE = 0.00;
const FLASHPAY_DEFAULT_WITHDRAWAL_FIXED_FEE = 0.00;

function flashpayOut($message) {
    echo $message . PHP_EOL;
}

function getFlashPayGatewayConfig() {
    return [
        'currency' => 'KRW',
        'gatewayKey' => 'flashpay-krw',
        'gatewayName' => 'FlashPay KRW',
        'methodName' => 'FlashPay KRW',
        'shortCode' => 'KRW',
        'displayOrder' => 26,
        'amountDecimalPlaces' => 0
    ];
}

function getFlashPayDepositQuestions() {
    return [
        [
            'name' => 'first_name',
            'hintText' => 'Payer real full name as registered with the bank (used as channelExtra.firstName).',
            'questionType' => 'text',
            'validationRules' => 'required|min:1|max:100',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
            'isLocked' => 1
        ],
        [
            'name' => 'last_name',
            'hintText' => 'Payer family name (combined with first_name for full legal name when needed).',
            'questionType' => 'text',
            'validationRules' => 'required|min:1|max:100',
            'options' => null,
            'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
            'isLocked' => 1
        ]
    ];
}

function getFlashPayBankCodeOptions() {
    return [];
}

function getFlashPayWithdrawSupportQuestions() {
    return [
        [
            'name' => 'bank_code',
            'hintText' => 'Beneficiary bank. Options synced from FlashPay /api/channel/querybanks.',
            'questionType' => 'single_choice',
            'validationRules' => 'required',
            'options' => getFlashPayBankCodeOptions(),
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

function getFlashPayWithdrawalTemplateQuestions() {
    return [
        [
            'scope' => 'bank_code',
            'questionText' => 'Which bank should receive the withdrawal?',
            'helpText' => 'Options are synced from FlashPay querybanks.',
            'questionType' => 'single_choice',
            'validationRules' => 'required',
            'options' => getFlashPayBankCodeOptions(),
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

function getFlashPayCliOptions() {
    $options = getopt('', [
        'mch-no::',
        'app-id::',
        'private-key::',
        'platform-public-key::',
        'base-url::',
        'environment::'
    ]);
    return [
        'mchNo' => trim((string)($options['mch-no'] ?? '')),
        'appId' => trim((string)($options['app-id'] ?? '')),
        'privateKey' => trim((string)($options['private-key'] ?? '')),
        'platformPublicKey' => trim((string)($options['platform-public-key'] ?? '')),
        'baseUrl' => trim((string)($options['base-url'] ?? FlashPayService::DEFAULT_BASE_URL)),
        'environment' => trim((string)($options['environment'] ?? 'sandbox'))
    ];
}

function runFlashPayGatewayInit(array $config, array $cli) {
    $currency = strtoupper(trim((string)($config['currency'] ?? '')));
    $gatewayKey = trim((string)($config['gatewayKey'] ?? ''));
    $gatewayName = trim((string)($config['gatewayName'] ?? ''));
    $methodName = trim((string)($config['methodName'] ?? $gatewayName));
    $shortCode = trim((string)($config['shortCode'] ?? $currency));
    $displayOrder = isset($config['displayOrder']) ? (int)$config['displayOrder'] : 0;
    $amountDecimalPlaces = isset($config['amountDecimalPlaces']) ? (int)$config['amountDecimalPlaces'] : 0;

    if ($currency === '' || $gatewayKey === '' || $gatewayName === '') {
        throw new InvalidArgumentException('FlashPay config is incomplete.');
    }

    $db = Database::getInstance();
    $templateModel = new WithdrawalVerificationTemplate();
    $categoryModel = new WithdrawalVerificationQuestionCategory();
    $questionModel = new WithdrawalVerificationQuestion();
    $questionOptionModel = new WithdrawalVerificationQuestionOption();
    $paymentSupportQuestionModel = new PaymentSupportQuestion();

    $templateName = FLASHPAY_PROVIDER_LABEL . ' ' . $currency . ' Withdrawal Verification';
    $templateDescription = 'Locked withdrawal verification fields for FlashPay ' . $currency . '.';
    $depositQuestions = getFlashPayDepositQuestions();
    $withdrawSupportQuestions = getFlashPayWithdrawSupportQuestions();
    $withdrawTemplateQuestions = getFlashPayWithdrawalTemplateQuestions();
    $supportQuestions = array_merge($depositQuestions, $withdrawSupportQuestions);

    $appConfig = require __DIR__ . '/../config/app.php';
    $backendBaseUrl = rtrim((string)($appConfig['file_base_url'] ?? ''), '/');
    if ($backendBaseUrl !== '') {
        $backendBaseUrl = preg_replace('#/index\.php$#i', '', $backendBaseUrl);
    }
    $notifyUrl = $backendBaseUrl !== ''
        ? $backendBaseUrl . '/api/callback/flashpay/deposit'
        : '';

    try {
        $db->beginTransaction();

        flashpayOut('=== ' . $gatewayName . ' Init ===');

        $gateway = $db->fetchOne(
            "SELECT * FROM paymentGatewaySettings WHERE gatewayKey = :gatewayKey LIMIT 1",
            ['gatewayKey' => $gatewayKey]
        );

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

        $gatewayData = [
            'gatewayKey' => $gatewayKey,
            'gatewayName' => $gatewayName,
            'iconClass' => 'fas fa-won-sign',
            'integration' => 1,
            'isEnabled' => (
                $cli['mchNo'] !== ''
                && $cli['appId'] !== ''
                && $cli['privateKey'] !== ''
                && $platformPublicKey !== ''
            ) ? 1 : 0,
            'isDepositEnabled' => 1,
            'isWithdrawalEnabled' => 1,
            'processingTime' => 'Instant - 1 business day',
            'environment' => $cli['environment'] === 'production' ? 'production' : 'sandbox',
            'appId' => $cli['appId'] !== '' ? $cli['appId'] : null,
            'apiKey' => $cli['mchNo'] !== '' ? $cli['mchNo'] : null,
            'secretKey' => $cli['privateKey'] !== '' ? $cli['privateKey'] : null,
            'merchantName' => strtolower(FLASHPAY_PROVIDER_LABEL),
            'webhookUrl' => $notifyUrl !== '' ? $notifyUrl : null,
            'returnUrl' => null,
            'supportedFiatCurrencies' => json_encode([$currency], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'supportedCryptoCurrencies' => null,
            'amountDecimalPlaces' => $amountDecimalPlaces,
            'configData' => json_encode([
                'providerKey' => FLASHPAY_PROVIDER_KEY,
                'base_url' => $cli['baseUrl'] !== '' ? $cli['baseUrl'] : FlashPayService::DEFAULT_BASE_URL,
                'platform_public_key' => $platformPublicKey,
                'if_code' => FlashPayService::DEFAULT_IF_CODE,
                'way_code' => FlashPayService::DEFAULT_WAY_CODE,
                'entry_type' => FlashPayService::DEFAULT_ENTRY_TYPE,
                'currency' => FlashPayService::DEFAULT_CURRENCY,
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
            flashpayOut("[OK] paymentGatewaySettings inserted: id={$gatewayId}");
        } else {
            if ($cli['mchNo'] === '') {
                unset($gatewayData['apiKey']);
            }
            if ($cli['appId'] === '') {
                unset($gatewayData['appId']);
            }
            if ($cli['privateKey'] === '') {
                unset($gatewayData['secretKey']);
            }
            unset($gatewayData['isEnabled']);
            $db->update('paymentGatewaySettings', $gatewayData, 'id = :id', ['id' => $gateway['id']]);
            $gateway = $db->fetchOne(
                "SELECT * FROM paymentGatewaySettings WHERE id = :id LIMIT 1",
                ['id' => $gateway['id']]
            );
            flashpayOut("[SKIP] paymentGatewaySettings already exists, normalized: id={$gateway['id']}");
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
            'notes' => 'Initialized by FlashPay bootstrap script for ' . $currency . '.',
            'updatedBy' => null
        ];

        if (!$gatewayFundingSetting) {
            $gatewayFundingSettingId = $db->insert('paymentGatewayFundingSettings', array_merge(
                ['gatewaySettingId' => (int)$gateway['id']],
                $gatewayFundingData
            ));
            flashpayOut("[OK] paymentGatewayFundingSettings inserted: id={$gatewayFundingSettingId}");
        } else {
            $db->update(
                'paymentGatewayFundingSettings',
                $gatewayFundingData,
                'id = :id',
                ['id' => $gatewayFundingSetting['id']]
            );
            $gatewayFundingSettingId = (int)$gatewayFundingSetting['id'];
            flashpayOut("[SKIP] paymentGatewayFundingSettings already exists, normalized: id={$gatewayFundingSettingId}");
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
            'fixed' => FLASHPAY_DEFAULT_DEPOSIT_FIXED_FEE,
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
            'fixed' => FLASHPAY_DEFAULT_WITHDRAWAL_FIXED_FEE,
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
            'networkName' => FLASHPAY_PROVIDER_LABEL,
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
            flashpayOut("[OK] paymentMethods inserted: id={$paymentMethodId}");
        } else {
            $db->update('paymentMethods', $paymentMethodData, 'id = :id', ['id' => $paymentMethod['id']]);
            $paymentMethod = $db->fetchOne(
                "SELECT * FROM paymentMethods WHERE id = :id LIMIT 1",
                ['id' => $paymentMethod['id']]
            );
            flashpayOut("[SKIP] paymentMethods already exists, normalized: id={$paymentMethod['id']}");
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
            flashpayOut("[OK] withdrawalVerificationTemplates inserted: id={$templateId}");
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
            flashpayOut("[SKIP] withdrawalVerificationTemplates already exists, normalized: id={$template['id']}");
        }

        $category = $categoryModel->findOne([
            'templateId' => $template['id'],
            'categoryName' => FLASHPAY_TEMPLATE_CATEGORY
        ]);

        if (!$category) {
            $categoryId = $categoryModel->createCategory([
                'templateId' => (int)$template['id'],
                'categoryName' => FLASHPAY_TEMPLATE_CATEGORY,
                'description' => 'Locked FlashPay bank withdrawal fields.',
                'displayOrder' => 1,
                'isExpanded' => 1,
                'isActive' => 1,
                'isLocked' => 1
            ]);
            $category = $categoryModel->findById($categoryId);
            flashpayOut("[OK] withdrawalVerificationQuestionCategories inserted: id={$categoryId}");
        } else {
            $categoryModel->update($category['id'], [
                'description' => 'Locked FlashPay bank withdrawal fields.',
                'displayOrder' => 1,
                'isExpanded' => 1,
                'isActive' => 1,
                'isLocked' => 1
            ]);
            $category = $categoryModel->findById($category['id']);
            flashpayOut("[SKIP] withdrawalVerificationQuestionCategories already exists, normalized: id={$category['id']}");
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
                flashpayOut("[OK] withdrawal question inserted: scope={$questionSpec['scope']} id={$questionId}");
            } else {
                $questionModel->update($existing['id'], $questionData);
                if ($questionSpec['questionType'] === 'single_choice' && empty($questionSpec['options'])) {
                    // Keep existing bank options until sync script refreshes them.
                } elseif ($questionSpec['questionType'] === 'single_choice') {
                    $questionOptionModel->updateQuestionOptions($existing['id'], $questionSpec['options'] ?? []);
                }
                flashpayOut("[SKIP] withdrawal question already exists, normalized: scope={$questionSpec['scope']} id={$existing['id']}");
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
                flashpayOut("[OK] paymentSupportQuestions inserted: name={$supportQuestion['name']} scope={$supportQuestion['scope']} id={$supportQuestionId}");
            } else {
                $updateData = [
                    'hintText' => $supportQuestion['hintText'],
                    'questionType' => $supportQuestion['questionType'],
                    'validationRules' => $supportQuestion['validationRules'],
                    'isLocked' => $supportQuestion['isLocked'],
                    'isActive' => 1,
                    'updatedBy' => null
                ];
                if (!($supportQuestion['name'] === 'bank_code' && empty($supportQuestion['options']))) {
                    $updateData['options'] = $supportQuestion['options'];
                }
                $paymentSupportQuestionModel->updateQuestion($existingSupportQuestion['id'], $updateData);
                flashpayOut("[SKIP] paymentSupportQuestions already exists, normalized: name={$supportQuestion['name']} scope={$supportQuestion['scope']} id={$existingSupportQuestion['id']}");
            }
        }

        $existingSupportQuestions = $paymentSupportQuestionModel->getAdminQuestions((int)$gateway['id']);
        foreach ($existingSupportQuestions as $existingSupportQuestion) {
            $key = ($existingSupportQuestion['name'] ?? '') . '|' . ($existingSupportQuestion['scope'] ?? '');
            if (in_array($key, $desiredSupportQuestionKeys, true)) {
                continue;
            }

            $paymentSupportQuestionModel->delete((int)$existingSupportQuestion['id']);
            flashpayOut("[CLEAN] paymentSupportQuestions removed: name={$existingSupportQuestion['name']} scope={$existingSupportQuestion['scope']} id={$existingSupportQuestion['id']}");
        }

        $db->commit();

        flashpayOut('');
        flashpayOut('[DONE] FlashPay bootstrap finished successfully.');
        flashpayOut('Gateway ID: ' . $gateway['id']);
        flashpayOut('Payment Method ID: ' . $paymentMethod['id']);
        flashpayOut('Template ID: ' . $template['id']);
        flashpayOut('Category ID: ' . $category['id']);
        flashpayOut('[NEXT] php script/flashpay_sync_bank_options.php');
        if (empty($gateway['apiKey']) || empty($gateway['secretKey']) || empty($gateway['appId'])) {
            flashpayOut('[NOTE] Gateway created DISABLED: credentials incomplete.');
            flashpayOut('       Re-run with --mch-no/--app-id/--private-key/--platform-public-key.');
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
        runFlashPayGatewayInit(getFlashPayGatewayConfig(), getFlashPayCliOptions());
        exit(0);
    } catch (Throwable $e) {
        flashpayOut('[ERROR] ' . $e->getMessage());
        if ($e instanceof InvalidArgumentException) {
            flashpayOut('Usage: php flashpaygatewayinit.php [--mch-no=NO] [--app-id=ID] [--private-key=KEY] [--platform-public-key=KEY] [--base-url=URL] [--environment=sandbox|production]');
        }
        exit(1);
    }
}
