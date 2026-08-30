<?php
/**
 * IBeePay gateway/bootstrap initializer
 *
 * 用法：
 *   php script/ibeepaygatewayinit.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplate.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionCategory.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestion.php';
require_once __DIR__ . '/../models/PaymentSupportQuestion.php';

const IBEEPAY_GATEWAY_KEY = 'ibeepay';
const IBEEPAY_TEMPLATE_NAME = 'IBeePay Pre-Withdrawal Verification';
const IBEEPAY_TEMPLATE_DESCRIPTION = 'Baseline locked fields for IBeePay withdrawal verification.';
const IBEEPAY_CATEGORY_NAME = 'Basic Identity Fields';
const IBEEPAY_DEFAULT_DEPOSIT_FIXED_FEE = 3.00;
const IBEEPAY_DEFAULT_WITHDRAWAL_FIXED_FEE = 5.00;

function out($message) {
    echo $message . PHP_EOL;
}

$db = Database::getInstance();
$templateModel = new WithdrawalVerificationTemplate();
$categoryModel = new WithdrawalVerificationQuestionCategory();
$questionModel = new WithdrawalVerificationQuestion();
$paymentSupportQuestionModel = new PaymentSupportQuestion();

$questions = [
    [
        'scope' => 'name',
        'questionText' => 'What is your full name?',
        'helpText' => 'Enter the full legal name used by the IBeePay payment profile.',
        'questionType' => 'text',
        'validationRules' => 'required|min:2|max:100'
    ],
    [
        'scope' => 'dob',
        'questionText' => 'What is your date of birth?',
        'helpText' => 'Provide the account holder date of birth exactly as registered with IBeePay.',
        'questionType' => 'date',
        'validationRules' => 'required|date'
    ],
    [
        'scope' => 'email',
        'questionText' => 'What is your email address?',
        'helpText' => 'Use the email address tied to the IBeePay account.',
        'questionType' => 'email',
        'validationRules' => 'required|email|max:255'
    ],
    [
        'scope' => 'phone',
        'questionText' => 'What is your phone number?',
        'helpText' => 'Use the mobile number tied to the IBeePay account, including country code, for example +8210.... IBeePay requires this format.',
        'questionType' => 'tel',
        'validationRules' => 'required|max:30'
    ]
];

$supportQuestions = [
    [
        'name' => 'name',
        'hintText' => 'Full name on the IBeePay profile.',
        'questionType' => 'text',
        'validationRules' => 'required|min:2|max:100',
        'options' => null,
        'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
        'isLocked' => 1
    ],
    [
        'name' => 'name',
        'hintText' => 'Full name on the IBeePay profile.',
        'questionType' => 'text',
        'validationRules' => 'required|min:2|max:100',
        'options' => null,
        'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
        'isLocked' => 1
    ],
    [
        'name' => 'dob',
        'hintText' => 'Date of birth on the IBeePay profile.',
        'questionType' => 'date',
        'validationRules' => 'required|date',
        'options' => null,
        'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
        'isLocked' => 1
    ],
    [
        'name' => 'dob',
        'hintText' => 'Date of birth on the IBeePay profile.',
        'questionType' => 'date',
        'validationRules' => 'required|date',
        'options' => null,
        'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
        'isLocked' => 1
    ],
    [
        'name' => 'email',
        'hintText' => 'Email used for the IBeePay account.',
        'questionType' => 'email',
        'validationRules' => 'required|email|max:255',
        'options' => null,
        'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
        'isLocked' => 1
    ],
    [
        'name' => 'email',
        'hintText' => 'Email used for the IBeePay account.',
        'questionType' => 'email',
        'validationRules' => 'required|email|max:255',
        'options' => null,
        'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
        'isLocked' => 1
    ],
    [
        'name' => 'phone',
        'hintText' => 'Phone number with country code, for example +8210....',
        'questionType' => 'tel',
        'validationRules' => 'required|max:30',
        'options' => null,
        'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW,
        'isLocked' => 1
    ],
    [
        'name' => 'phone',
        'hintText' => 'Phone number with country code, for example +8210....',
        'questionType' => 'tel',
        'validationRules' => 'required|max:30',
        'options' => null,
        'scope' => PaymentSupportQuestion::SCOPE_DEPOSIT,
        'isLocked' => 1
    ]
];

try {
    $db->beginTransaction();

    out('=== IBeePay Gateway Init ===');

    $gateway = $db->fetchOne(
        "SELECT * FROM paymentGatewaySettings WHERE gatewayKey = :gatewayKey LIMIT 1",
        ['gatewayKey' => IBEEPAY_GATEWAY_KEY]
    );

    if (!$gateway) {
        $gatewayId = $db->insert('paymentGatewaySettings', [
            'gatewayKey' => 'ibeepay',
            'gatewayName' => 'IBeePay',
            'iconClass' => 'fas fa-coins',
            'integration' => 1,
            'isEnabled' => 1,
            'isDepositEnabled' => 1,
            'isWithdrawalEnabled' => 1,
            'environment' => 'production',
            'appId' => 'utrada',
            'apiKey' => null,
            'secretKey' => '99efdc27702f44e29d4f7c0db34b942a',
            'merchantName' => 'utrada',
            'webhookUrl' => null,
            'returnUrl' => null,
            'supportedFiatCurrencies' => '["USD"]',
            'supportedCryptoCurrencies' => null,
            'configData' => '{"deposit_url":"http://devel.api.ibeepay.com/popup/v1/deposit","withdrawal_url":"http://devel.api.ibeepay.com/rest/v1/withdrawal","lang":"en"}',
            'updatedBy' => null
        ]);

        $gateway = $db->fetchOne(
            "SELECT * FROM paymentGatewaySettings WHERE id = :id LIMIT 1",
            ['id' => $gatewayId]
        );
        out("[OK] paymentGatewaySettings inserted: id={$gatewayId}");
    } else {
        $db->update('paymentGatewaySettings', [
            'integration' => 1,
            'updatedBy' => null
        ], 'id = :id', ['id' => $gateway['id']]);
        $gateway = $db->fetchOne(
            "SELECT * FROM paymentGatewaySettings WHERE id = :id LIMIT 1",
            ['id' => $gateway['id']]
        );
        out("[SKIP] paymentGatewaySettings already exists: id={$gateway['id']}");
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
        'notes' => 'Initialized by IBeePay bootstrap script.',
        'updatedBy' => null
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

    $db->delete(
        'paymentGatewayFeeRules',
        'gatewayFundingSettingId = :gatewayFundingSettingId',
        ['gatewayFundingSettingId' => $gatewayFundingSettingId]
    );

    $db->insert('paymentGatewayFeeRules', [
        'gatewayFundingSettingId' => $gatewayFundingSettingId,
        'transactionType' => 'deposit',
        'thresholdAmount' => 0,
        'feeMode' => 'fixed',
        'percentage' => 0,
        'fixed' => IBEEPAY_DEFAULT_DEPOSIT_FIXED_FEE,
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
        'feeMode' => 'fixed',
        'percentage' => 0,
        'fixed' => IBEEPAY_DEFAULT_WITHDRAWAL_FIXED_FEE,
        'minFee' => null,
        'maxFee' => null,
        'chargeToClient' => 1,
        'sortOrder' => 0,
        'isActive' => 1
    ]);

    $paymentMethod = $db->fetchOne(
        "SELECT * FROM paymentMethods WHERE methodKey = :methodKey LIMIT 1",
        ['methodKey' => IBEEPAY_GATEWAY_KEY]
    );

    if (!$paymentMethod) {
        $maxDisplayOrder = $db->fetchOne("SELECT COALESCE(MAX(displayOrder), 0) AS maxDisplayOrder FROM paymentMethods");
        $paymentMethodId = $db->insert('paymentMethods', [
            'methodKey' => 'ibeepay',
            'methodName' => 'IBeePay',
            'methodType' => 'fiat',
            'iconClass' => 'fas fa-bee',
            'shortCode' => 'IBEEPAY',
            'networkName' => 'IBeePay',
            'country' => null,
            'minPurchaseAmount' => null,
            'maxPurchaseAmount' => null,
            'isDepositEnabled' => 1,
            'isWithdrawalEnabled' => 1,
            'processingTime' => '1-3 business days',
            'displayOrder' => 1
        ]);

        $paymentMethod = $db->fetchOne(
            "SELECT * FROM paymentMethods WHERE id = :id LIMIT 1",
            ['id' => $paymentMethodId]
        );
        out("[OK] paymentMethods inserted: id={$paymentMethodId}");
    } else {
        out("[SKIP] paymentMethods already exists: id={$paymentMethod['id']}");
    }

    $template = $templateModel->findOne([
        'gatewaySettingId' => $gateway['id'],
        'templateName' => IBEEPAY_TEMPLATE_NAME
    ]);

    if (!$template) {
        $templateId = $templateModel->create([
            'gatewaySettingId' => (int)$gateway['id'],
            'templateName' => IBEEPAY_TEMPLATE_NAME,
            'description' => IBEEPAY_TEMPLATE_DESCRIPTION,
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
        'categoryName' => IBEEPAY_CATEGORY_NAME
    ]);

    if (!$category) {
        $categoryId = $categoryModel->createCategory([
            'templateId' => (int)$template['id'],
            'categoryName' => IBEEPAY_CATEGORY_NAME,
            'description' => 'Locked IBeePay identity fields.',
            'displayOrder' => 1,
            'isExpanded' => 1,
            'isActive' => 1,
            'isLocked' => 1
        ]);
        $category = $categoryModel->findById($categoryId);
        out("[OK] withdrawalVerificationQuestionCategories inserted: id={$categoryId}");
    } else {
        $categoryModel->update($category['id'], [
            'description' => 'Locked IBeePay identity fields.',
            'displayOrder' => 1,
            'isExpanded' => 1,
            'isActive' => 1,
            'isLocked' => 1
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
            'isLocked' => 1,
            'displayOrder' => $index + 1,
            'metadata' => null,
            'updatedBy' => null
        ];

        if (!$existing) {
            $questionData['createdBy'] = null;
            $questionId = $questionModel->createQuestion($questionData);
            out("[OK] question inserted: scope={$questionSpec['scope']} id={$questionId}");
        } else {
            $questionModel->update($existing['id'], $questionData);
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
    out('[DONE] IBeePay bootstrap finished successfully.');
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
