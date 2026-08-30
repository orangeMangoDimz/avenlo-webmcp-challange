<?php
/**
 * Crypto gateway withdrawal verification bootstrap initializer
 *
 * 用法：
 *   php script/cryptogatewayinit.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplate.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionCategory.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestion.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionOption.php';
require_once __DIR__ . '/../models/PaymentSupportQuestion.php';

const CRYPTO_GATEWAY_KEY = 'crypto';
const CRYPTO_TEMPLATE_NAME = 'Crypto Withdrawal Verification';
const CRYPTO_TEMPLATE_DESCRIPTION = 'Minimal locked fields for crypto withdrawal verification.';
const CRYPTO_CATEGORY_NAME = 'Wallet Details';
const CRYPTO_DEFAULT_DEPOSIT_FIXED_FEE = 3.00;
const CRYPTO_DEFAULT_WITHDRAWAL_FIXED_FEE = 5.00;

function out($message) {
    echo $message . PHP_EOL;
}

$db = Database::getInstance();
$templateModel = new WithdrawalVerificationTemplate();
$categoryModel = new WithdrawalVerificationQuestionCategory();
$questionModel = new WithdrawalVerificationQuestion();
$questionOptionModel = new WithdrawalVerificationQuestionOption();
$paymentSupportQuestionModel = new PaymentSupportQuestion();

$chainOptions = ['TRC20', 'BEP20', 'ERC20', 'SOL'];

$questions = [
    [
        'scope' => 'chain',
        'questionText' => 'Which chain are you using?',
        'helpText' => 'Select the receiving chain.',
        'questionType' => 'single_choice',
        'validationRules' => 'required',
        'options' => $chainOptions
    ],
    [
        'scope' => 'wallet_address',
        'questionText' => 'What is your wallet address?',
        'helpText' => 'Enter the full receiving wallet address.',
        'questionType' => 'text',
        'validationRules' => 'required|min:8|max:255',
        'options' => null
    ]
];

$supportQuestions = [
    [
        'name' => 'chain',
        'hintText' => 'Transfer chain.',
        'questionType' => 'single_choice',
        'validationRules' => 'required',
        'options' => $chainOptions,
        'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
        'isLocked' => 0
    ],
    [
        'name' => 'chain',
        'hintText' => 'Transfer chain.',
        'questionType' => 'single_choice',
        'validationRules' => 'required',
        'options' => $chainOptions,
        'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
        'isLocked' => 0
    ],
    [
        'name' => 'wallet_address',
        'hintText' => 'Receiving wallet address.',
        'questionType' => 'text',
        'validationRules' => 'required|min:8|max:255',
        'options' => null,
        'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
        'isLocked' => 0
    ],
    [
        'name' => 'transaction_hash',
        'hintText' => 'On-chain transaction hash.',
        'questionType' => 'text',
        'validationRules' => 'required|min:16|max:255',
        'options' => null,
        'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
        'isLocked' => 0
    ]
];

try {
    $db->beginTransaction();

    out('=== Crypto Gateway Init ===');

    $gateway = $db->fetchOne(
        "SELECT * FROM paymentGatewaySettings WHERE gatewayKey = :gatewayKey LIMIT 1",
        ['gatewayKey' => CRYPTO_GATEWAY_KEY]
    );

    if (!$gateway) {
        $gatewayId = $db->insert('paymentGatewaySettings', [
            'gatewayKey' => 'crypto',
            'gatewayName' => 'Crypto',
            'iconClass' => 'fa-brands fa-bitcoin',
            'integration' => 0,
            'type' => 'crypto',
            'isEnabled' => 1,
            'isDepositEnabled' => 1,
            'isWithdrawalEnabled' => 1,
            'processingTime' => '5-30 minutes',
            'environment' => 'production',
            'appId' => null,
            'apiKey' => null,
            'secretKey' => null,
            'merchantName' => 'Crypto',
            'webhookUrl' => null,
            'returnUrl' => null,
            'supportedFiatCurrencies' => null,
            'supportedCryptoCurrencies' => '["BTC","ETH","USDT","USDC"]',
            'configData' => null,
            'updatedBy' => null
        ]);

        $gateway = $db->fetchOne(
            "SELECT * FROM paymentGatewaySettings WHERE id = :id LIMIT 1",
            ['id' => $gatewayId]
        );
        out("[OK] paymentGatewaySettings inserted: id={$gatewayId}");
    } else {
        $db->update('paymentGatewaySettings', [
            'iconClass' => 'fas fa-bitcoin',
            'integration' => 0,
            'type' => 'crypto',
            'isEnabled' => 1,
            'isDepositEnabled' => 1,
            'isWithdrawalEnabled' => 1,
            'processingTime' => '5-30 minutes',
            'gatewayName' => 'Crypto',
            'merchantName' => 'Crypto',
            'updatedBy' => null
        ], 'id = :id', ['id' => $gateway['id']]);
        $gateway = $db->fetchOne(
            "SELECT * FROM paymentGatewaySettings WHERE id = :id LIMIT 1",
            ['id' => $gateway['id']]
        );
        out("[SKIP] paymentGatewaySettings already exists: id={$gateway['id']}");
    }

    $gatewayFeeSetting = $db->fetchOne(
        "SELECT * FROM paymentGatewayFundingSettings WHERE gatewaySettingId = :gatewaySettingId LIMIT 1",
        ['gatewaySettingId' => $gateway['id']]
    );

    $gatewayFeeData = [
        'calculationMode' => 'single',
        'minDeposit' => null,
        'maxDeposit' => null,
        'minWithdrawal' => null,
        'maxWithdrawal' => null,
        'isActive' => 1,
        'notes' => 'Initialized by Crypto bootstrap script.',
        'updatedBy' => null
    ];

    if (!$gatewayFeeSetting) {
        $gatewayFeeSettingId = $db->insert('paymentGatewayFundingSettings', array_merge(
            ['gatewaySettingId' => (int)$gateway['id']],
            $gatewayFeeData
        ));
        out("[OK] paymentGatewayFundingSettings inserted: id={$gatewayFeeSettingId}");
    } else {
        $db->update(
            'paymentGatewayFundingSettings',
            $gatewayFeeData,
            'id = :id',
            ['id' => $gatewayFeeSetting['id']]
        );
        $gatewayFeeSettingId = (int)$gatewayFeeSetting['id'];
        out("[SKIP] paymentGatewayFundingSettings already exists, normalized: id={$gatewayFeeSetting['id']}");
    }

    $db->delete(
        'paymentGatewayFeeRules',
        'gatewayFundingSettingId = :gatewayFundingSettingId',
        ['gatewayFundingSettingId' => $gatewayFeeSettingId]
    );

    $db->insert('paymentGatewayFeeRules', [
        'gatewayFundingSettingId' => $gatewayFeeSettingId,
        'transactionType' => 'deposit',
        'thresholdAmount' => 0,
        'feeMode' => 'fixed',
        'percentage' => 0,
        'fixed' => CRYPTO_DEFAULT_DEPOSIT_FIXED_FEE,
        'minFee' => null,
        'maxFee' => null,
        'chargeToClient' => 1,
        'sortOrder' => 0,
        'isActive' => 1
    ]);

    $db->insert('paymentGatewayFeeRules', [
        'gatewayFundingSettingId' => $gatewayFeeSettingId,
        'transactionType' => 'withdrawal',
        'thresholdAmount' => 0,
        'feeMode' => 'fixed',
        'percentage' => 0,
        'fixed' => CRYPTO_DEFAULT_WITHDRAWAL_FIXED_FEE,
        'minFee' => null,
        'maxFee' => null,
        'chargeToClient' => 1,
        'sortOrder' => 0,
        'isActive' => 1
    ]);

    $template = $templateModel->findOne([
        'gatewaySettingId' => $gateway['id'],
        'templateName' => CRYPTO_TEMPLATE_NAME
    ]);

    if (!$template) {
        $templateId = $templateModel->create([
            'gatewaySettingId' => (int)$gateway['id'],
            'templateName' => CRYPTO_TEMPLATE_NAME,
            'description' => CRYPTO_TEMPLATE_DESCRIPTION,
            'status' => 'active',
            'isAutoApproveEnabled' => 0,
            'requireDocumentSignature' => 0,
            'displayOrder' => 1,
            'createdBy' => null,
            'updatedBy' => null
        ]);

        $template = $templateModel->findById($templateId);
        out("[OK] withdrawalVerificationTemplates inserted: id={$templateId}");
    } else {
        out("[SKIP] withdrawalVerificationTemplates already exists: id={$template['id']}");
    }

    $category = $categoryModel->findOne([
        'templateId' => $template['id'],
        'categoryName' => CRYPTO_CATEGORY_NAME
    ]);

    if (!$category) {
        $categoryId = $categoryModel->createCategory([
            'templateId' => (int)$template['id'],
            'categoryName' => CRYPTO_CATEGORY_NAME,
            'description' => 'Crypto wallet fields.',
            'displayOrder' => 1,
            'isExpanded' => 1,
            'isActive' => 1,
            'isLocked' => 0
        ]);
        $category = $categoryModel->findById($categoryId);
        out("[OK] withdrawalVerificationQuestionCategories inserted: id={$categoryId}");
    } else {
        $categoryModel->update($category['id'], [
            'description' => 'Crypto wallet fields.',
            'displayOrder' => 1,
            'isExpanded' => 1,
            'isActive' => 1,
            'isLocked' => 0
        ]);
        $category = $categoryModel->findById($category['id']);
        out("[SKIP] withdrawalVerificationQuestionCategories already exists, normalized: id={$category['id']}");
    }

    foreach ($questions as $index => $questionSpec) {
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
            'isRequired' => 1,
            'isActive' => 1,
            'isLocked' => 0,
            'displayOrder' => $index + 1,
            'metadata' => null,
            'updatedBy' => null
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

        if (!$existingSupportQuestion) {
            $supportQuestionId = $paymentSupportQuestionModel->createQuestion([
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
            ]);
            out("[OK] paymentSupportQuestions inserted: name={$supportQuestion['name']} scope={$supportQuestion['scope']} id={$supportQuestionId}");
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
            out("[SKIP] paymentSupportQuestions already exists, normalized: name={$supportQuestion['name']} scope={$supportQuestion['scope']} id={$existingSupportQuestion['id']}");
        }
    }

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
    out('[DONE] Crypto bootstrap finished successfully.');
    out('Gateway ID: ' . $gateway['id']);
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
