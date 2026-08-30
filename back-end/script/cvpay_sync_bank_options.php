<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../models/PaymentSupportQuestion.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplate.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestion.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionOption.php';
require_once __DIR__ . '/cvpaygatewayinit.php';

function cvpayBankSyncOut($message) {
    echo $message . PHP_EOL;
}

function getCvPayBankSyncCliOptions() {
    $options = getopt('', ['gateway-key::']);
    return [
        'gatewayKey' => trim((string)($options['gateway-key'] ?? 'cvpay-vnd')),
    ];
}

function syncCvPayBankOptions(string $gatewayKey): void {
    $db = Database::getInstance();
    $supportQuestionModel = new PaymentSupportQuestion();
    $templateModel = new WithdrawalVerificationTemplate();
    $questionModel = new WithdrawalVerificationQuestion();
    $questionOptionModel = new WithdrawalVerificationQuestionOption();

    $gateway = $db->fetchOne(
        "SELECT * FROM paymentGatewaySettings WHERE gatewayKey = :gatewayKey LIMIT 1",
        ['gatewayKey' => $gatewayKey]
    );
    if (!$gateway) {
        throw new RuntimeException("paymentGatewaySettings not found for gatewayKey={$gatewayKey}. Run cvpaygatewayinit.php first.");
    }

    cvpayBankSyncOut("=== Sync {$gatewayKey} banks from local JSON ===");
    $supportOptions = getCvPaySupportBankOptions();
    $templateOptions = getCvPayTemplateBankOptions();
    cvpayBankSyncOut('[OK] Loaded ' . count($supportOptions) . ' banks from ' . getCvPayVndBanksDataPath());

    $db->beginTransaction();
    try {
        $supportQuestion = $supportQuestionModel->findOne([
            'paymentGatewayId' => (int)$gateway['id'],
            'name' => 'bank_name',
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW
        ]);
        if (!$supportQuestion) {
            throw new RuntimeException('paymentSupportQuestions bank_name/withdraw not found. Run cvpaygatewayinit.php first.');
        }

        $supportQuestionModel->updateQuestion((int)$supportQuestion['id'], [
            'questionType' => 'single_choice',
            'validationRules' => 'required',
            'hintText' => 'Select the Vietnamese bank. CRM sends bankCode to CVPay as bankName.',
            'options' => $supportOptions,
            'isActive' => 1,
            'updatedBy' => null
        ]);
        cvpayBankSyncOut("[OK] paymentSupportQuestions updated: id={$supportQuestion['id']} options=" . count($supportOptions));

        $templates = $templateModel->findAll([
            'gatewaySettingId' => (int)$gateway['id']
        ]);
        $updatedTemplateQuestionCount = 0;
        foreach ($templates as $template) {
            $questions = $questionModel->findAll([
                'templateId' => (int)$template['id'],
                'scope' => 'bank_name'
            ]);
            foreach ($questions as $question) {
                $questionModel->update((int)$question['id'], [
                    'questionType' => 'single_choice',
                    'questionText' => 'Which bank should receive the withdrawal?',
                    'helpText' => 'Search and select the Vietnamese bank. CRM sends bankCode to CVPay as bankName.',
                    'validationRules' => 'required',
                    'isRequired' => 1,
                    'isActive' => 1,
                    'updatedBy' => null
                ]);
                $questionOptionModel->updateQuestionOptions((int)$question['id'], $templateOptions);
                $updatedTemplateQuestionCount++;
                cvpayBankSyncOut("[OK] withdrawal question options updated: id={$question['id']} options=" . count($templateOptions));
            }
        }

        $db->commit();
        cvpayBankSyncOut('[DONE] CVPay bank sync finished. templateQuestions=' . $updatedTemplateQuestionCount);
    } catch (Throwable $e) {
        if ($db->getConnection()->inTransaction()) {
            $db->rollback();
        }
        throw $e;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $cli = getCvPayBankSyncCliOptions();
        syncCvPayBankOptions($cli['gatewayKey']);
        exit(0);
    } catch (Throwable $e) {
        cvpayBankSyncOut('[ERROR] ' . $e->getMessage());
        cvpayBankSyncOut('Usage: php cvpay_sync_bank_options.php [--gateway-key=cvpay-vnd]');
        exit(1);
    }
}
