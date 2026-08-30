<?php
/**
 * Sync FlashPay bank options from live /api/channel/querybanks.
 *
 * Usage:
 *   php script/flashpay_sync_bank_options.php
 *   php script/flashpay_sync_bank_options.php --gateway-key=flashpay-krw --country-code=KR
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../models/PaymentSupportQuestion.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplate.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestion.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionOption.php';
require_once __DIR__ . '/../services/FlashPayService.php';
require_once __DIR__ . '/flashpaygatewayinit.php';

function flashpayBankSyncOut($message) {
    echo $message . PHP_EOL;
}

function getFlashPayBankSyncCliOptions() {
    $options = getopt('', ['gateway-key::', 'country-code::', 'way-code::']);
    return [
        'gatewayKey' => trim((string)($options['gateway-key'] ?? 'flashpay-krw')),
        'countryCode' => trim((string)($options['country-code'] ?? 'KR')),
        'wayCode' => trim((string)($options['way-code'] ?? FlashPayService::DEFAULT_WAY_CODE)),
    ];
}

function buildFlashPaySupportBankOptions(array $rows) {
    return array_values(array_map(static function ($row) {
        return [
            'label' => $row['name'],
            'value' => $row['code']
        ];
    }, $rows));
}

function buildFlashPayTemplateBankOptions(array $rows) {
    return array_values(array_map(static function ($row) {
        return [
            'optionLabel' => $row['name'],
            'optionValue' => $row['code']
        ];
    }, $rows));
}

function fetchFlashPayBankRows(array $gateway, string $wayCode, string $countryCode): array {
    $service = new FlashPayService($gateway);
    if (!$service->isConfigured()) {
        throw new RuntimeException('FlashPay gateway is not configured (need mchNo, appId, private key, platform public key, base_url)');
    }

    $payload = [
        'wayCode' => $wayCode,
    ];
    if ($countryCode !== '') {
        $payload['countryCode'] = $countryCode;
    }

    $response = $service->queryBanks($payload);
    if (!FlashPayService::isRequestAccepted($response)) {
        $message = trim((string)($response['msg'] ?? 'unknown error'));
        throw new RuntimeException('FlashPay querybanks rejected: ' . $message);
    }

    $data = $response['data'] ?? null;
    if (!is_array($data)) {
        throw new RuntimeException('FlashPay querybanks returned no bank list');
    }

    $rows = [];
    $seen = [];
    foreach ($data as $index => $item) {
        if (!is_array($item)) {
            flashpayBankSyncOut("[WARN] Skipped non-object bank row at index {$index}.");
            continue;
        }
        $normalized = FlashPayService::normalizeBankOptionRow($item);
        if ($normalized === null) {
            flashpayBankSyncOut("[WARN] Skipped unmapped bank row at index {$index}.");
            continue;
        }
        $code = $normalized['code'];
        if (isset($seen[$code])) {
            continue;
        }
        $seen[$code] = true;
        $rows[] = $normalized;
    }

    if (empty($rows)) {
        throw new RuntimeException('FlashPay querybanks returned an empty bank list');
    }

    return $rows;
}

function syncFlashPayBankOptions(string $gatewayKey, string $wayCode, string $countryCode): void {
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
        throw new RuntimeException("paymentGatewaySettings not found for gatewayKey={$gatewayKey}. Run flashpaygatewayinit.php first.");
    }

    flashpayBankSyncOut("=== Sync {$gatewayKey} banks via querybanks ===");
    $rows = fetchFlashPayBankRows($gateway, $wayCode, $countryCode);
    flashpayBankSyncOut('[OK] Fetched ' . count($rows) . ' banks from FlashPay');

    $supportOptions = buildFlashPaySupportBankOptions($rows);
    $templateOptions = buildFlashPayTemplateBankOptions($rows);

    $db->beginTransaction();
    try {
        $supportQuestion = $supportQuestionModel->findOne([
            'paymentGatewayId' => (int)$gateway['id'],
            'name' => 'bank_code',
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW
        ]);
        if (!$supportQuestion) {
            throw new RuntimeException('paymentSupportQuestions bank_code/withdraw not found. Run flashpaygatewayinit.php first.');
        }

        $supportQuestionModel->updateQuestion((int)$supportQuestion['id'], [
            'options' => $supportOptions,
            'isActive' => 1,
            'updatedBy' => null
        ]);
        flashpayBankSyncOut("[OK] paymentSupportQuestions updated: id={$supportQuestion['id']} options=" . count($supportOptions));

        $templates = $templateModel->findAll([
            'gatewaySettingId' => (int)$gateway['id']
        ]);
        $updatedTemplateQuestionCount = 0;
        foreach ($templates as $template) {
            $questions = $questionModel->findAll([
                'templateId' => (int)$template['id'],
                'scope' => 'bank_code'
            ]);
            foreach ($questions as $question) {
                if (($question['questionType'] ?? '') !== 'single_choice') {
                    continue;
                }
                $questionOptionModel->updateQuestionOptions((int)$question['id'], $templateOptions);
                $updatedTemplateQuestionCount++;
                flashpayBankSyncOut("[OK] withdrawal question options updated: id={$question['id']} options=" . count($templateOptions));
            }
        }

        $db->commit();
        flashpayBankSyncOut('[DONE] FlashPay bank sync finished. templateQuestions=' . $updatedTemplateQuestionCount);
    } catch (Throwable $e) {
        if ($db->getConnection()->inTransaction()) {
            $db->rollback();
        }
        throw $e;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $cli = getFlashPayBankSyncCliOptions();
        syncFlashPayBankOptions($cli['gatewayKey'], $cli['wayCode'], $cli['countryCode']);
        exit(0);
    } catch (Throwable $e) {
        flashpayBankSyncOut('[ERROR] ' . $e->getMessage());
        flashpayBankSyncOut('Usage: php flashpay_sync_bank_options.php [--gateway-key=flashpay-krw] [--way-code=WAKRWPUL_CARD] [--country-code=KR]');
        exit(1);
    }
}
