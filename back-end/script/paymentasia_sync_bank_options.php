<?php
/**
 * Sync Payment Asia bank options from a JSON file.
 *
 * Usage:
 *   php script/paymentasia_sync_bank_options.php --file=/absolute/or/relative/path/to/banks.json
 *   php script/paymentasia_sync_bank_options.php --file=script/support/pa-banks.json
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../models/PaymentSupportQuestion.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplate.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestion.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionOption.php';
require_once __DIR__ . '/paymentasiagatewayinit.php';

function paymentAsiaBankSyncOut($message) {
    echo $message . PHP_EOL;
}

function getPaymentAsiaBankSyncCliOptions() {
    $options = getopt('', ['file:']);

    return [
        'file' => trim((string)($options['file'] ?? ''))
    ];
}

function resolvePaymentAsiaBankSyncFilePath($inputPath) {
    $inputPath = trim((string)$inputPath);
    if ($inputPath === '') {
        throw new InvalidArgumentException('The --file argument is required.');
    }

    $candidates = [$inputPath];

    if ($inputPath[0] !== DIRECTORY_SEPARATOR) {
        $candidates[] = getcwd() . DIRECTORY_SEPARATOR . $inputPath;
        $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . $inputPath;
    }

    foreach ($candidates as $candidate) {
        $resolved = realpath($candidate);
        if ($resolved !== false && is_file($resolved) && is_readable($resolved)) {
            return $resolved;
        }
    }

    throw new InvalidArgumentException('Bank options file not found or unreadable: ' . $inputPath);
}

function loadPaymentAsiaBankRows($filePath, array $gatewayConfigs) {
    $raw = file_get_contents($filePath);
    if ($raw === false) {
        throw new RuntimeException('Failed to read bank options file: ' . $filePath);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Bank options file must contain a JSON array.');
    }

    $rows = [];
    $seen = [];
    $targetCurrency = null;

    foreach ($decoded as $index => $row) {
        if (!is_array($row)) {
            paymentAsiaBankSyncOut("[WARN] Skipped non-object row at index {$index}.");
            continue;
        }

        $currency = strtoupper(trim((string)($row['currency'] ?? '')));
        $code = trim((string)($row['code'] ?? ''));
        $name = trim((string)($row['name'] ?? ''));

        if ($currency === '' || $code === '' || $name === '') {
            paymentAsiaBankSyncOut("[WARN] Skipped invalid row at index {$index}: currency/code/name are required.");
            continue;
        }

        if (!isset($gatewayConfigs[$currency])) {
            paymentAsiaBankSyncOut("[WARN] Skipped unsupported currency '{$currency}' at index {$index}.");
            continue;
        }

        if ($targetCurrency === null) {
            $targetCurrency = $currency;
        }

        if ($currency !== $targetCurrency) {
            paymentAsiaBankSyncOut("[WARN] Skipped row at index {$index} because currency '{$currency}' does not match target currency '{$targetCurrency}'.");
            continue;
        }

        $dedupeKey = $currency . '|' . $code;
        if (isset($seen[$dedupeKey])) {
            paymentAsiaBankSyncOut("[WARN] Skipped duplicate bank code '{$code}' for currency '{$currency}'.");
            continue;
        }

        $rows[] = [
            'currency' => $currency,
            'code' => $code,
            'name' => $name
        ];
        $seen[$dedupeKey] = true;
    }

    if ($targetCurrency === null) {
        throw new InvalidArgumentException('No valid Payment Asia bank rows were found in the file.');
    }

    return [
        'currency' => $targetCurrency,
        'rows' => $rows
    ];
}

function buildPaymentAsiaSupportBankOptions(array $rows) {
    return array_values(array_map(function ($row) {
        return [
            'label' => $row['name'],
            'value' => $row['code']
        ];
    }, $rows));
}

function buildPaymentAsiaTemplateBankOptions(array $rows) {
    return array_values(array_map(function ($row) {
        return [
            'optionLabel' => $row['name'],
            'optionValue' => $row['code']
        ];
    }, $rows));
}

function normalizePaymentAsiaCurrencyKey($value) {
    $value = strtoupper(trim((string)$value));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/\.JSON$/', '', $value);
    $value = preg_replace('/^PA-/', '', $value);

    return $value;
}

function buildPaymentAsiaGatewayKey($currency) {
    $currency = strtolower(trim((string)$currency));
    if ($currency === '') {
        return '';
    }

    return 'pa-' . $currency;
}

function syncPaymentAsiaBankOptionsForCurrency(array $config, array $rows) {
    $currency = strtoupper(trim((string)($config['currency'] ?? '')));
    $gatewayKey = buildPaymentAsiaGatewayKey($currency);

    if ($currency === '' || $gatewayKey === '') {
        throw new InvalidArgumentException('Payment Asia config is incomplete.');
    }

    $db = Database::getInstance();
    $supportQuestionModel = new PaymentSupportQuestion();
    $templateModel = new WithdrawalVerificationTemplate();
    $questionModel = new WithdrawalVerificationQuestion();
    $questionOptionModel = new WithdrawalVerificationQuestionOption();

    $supportOptions = buildPaymentAsiaSupportBankOptions($rows);
    $templateOptions = buildPaymentAsiaTemplateBankOptions($rows);

    $updatedSupportQuestionCount = 0;
    $updatedTemplateQuestionCount = 0;

    try {
        $db->beginTransaction();

        paymentAsiaBankSyncOut("=== Sync {$gatewayKey} ({$currency}) ===");

        $gateway = $db->fetchOne(
            "SELECT id, gatewayKey, gatewayName
             FROM paymentGatewaySettings
             WHERE gatewayKey = :gatewayKey
             LIMIT 1",
            ['gatewayKey' => $gatewayKey]
        );

        if (!$gateway) {
            paymentAsiaBankSyncOut("[WARN] paymentGatewaySettings not found for gatewayKey={$gatewayKey}, skipped.");
            $db->commit();
            return;
        }

        $supportQuestion = $supportQuestionModel->findOne([
            'paymentGatewayId' => (int)$gateway['id'],
            'name' => 'bank_name',
            'scope' => PaymentSupportQuestion::SCOPE_WITHDRAW
        ]);

        if (!$supportQuestion) {
            paymentAsiaBankSyncOut("[WARN] paymentSupportQuestions bank_name/withdraw not found for gatewayKey={$gatewayKey}, skipped.");
        } else {
            $supportQuestionModel->updateQuestion((int)$supportQuestion['id'], [
                'options' => $supportOptions,
                'isActive' => 1,
                'updatedBy' => null
            ]);
            $updatedSupportQuestionCount++;
            paymentAsiaBankSyncOut("[OK] paymentSupportQuestions updated: id={$supportQuestion['id']} options=" . count($supportOptions));
        }

        $templates = $templateModel->findAll([
            'gatewaySettingId' => (int)$gateway['id']
        ], 'id ASC');

        if (empty($templates)) {
            paymentAsiaBankSyncOut("[WARN] withdrawalVerificationTemplates not found for gatewayKey={$gatewayKey}, skipped.");
        } else {
            foreach ($templates as $template) {
                $questions = $questionModel->findAll([
                    'templateId' => (int)$template['id'],
                    'scope' => 'bank_name'
                ], 'displayOrder');

                if (empty($questions)) {
                    paymentAsiaBankSyncOut("[WARN] bank_name question not found in templateId={$template['id']}, skipped.");
                    continue;
                }

                foreach ($questions as $question) {
                    $questionOptionModel->updateQuestionOptions((int)$question['id'], $templateOptions);
                    $updatedTemplateQuestionCount++;
                    paymentAsiaBankSyncOut("[OK] withdrawalVerificationQuestionOptions updated: templateId={$template['id']} questionId={$question['id']} options=" . count($templateOptions));
                }
            }
        }

        $db->commit();

        paymentAsiaBankSyncOut("[DONE] {$gatewayKey} sync complete. supportQuestions={$updatedSupportQuestionCount}, templateQuestions={$updatedTemplateQuestionCount}");
    } catch (Throwable $e) {
        if ($db->getConnection()->inTransaction()) {
            $db->rollback();
        }

        throw $e;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $cliOptions = getPaymentAsiaBankSyncCliOptions();
    $gatewayConfigs = getPaymentAsiaGatewayConfigs();

    try {
        $filePath = resolvePaymentAsiaBankSyncFilePath($cliOptions['file']);
        $payload = loadPaymentAsiaBankRows($filePath, $gatewayConfigs);
        $currencyKey = normalizePaymentAsiaCurrencyKey($payload['currency'] ?? '');

        if ($currencyKey === '' || !isset($gatewayConfigs[$currencyKey])) {
            throw new InvalidArgumentException('Unsupported currency found in file: ' . ($payload['currency'] ?? ''));
        }

        $rows = $payload['rows'] ?? [];
        if (empty($rows)) {
            throw new InvalidArgumentException('No bank rows found for currency: ' . $currencyKey);
        }

        syncPaymentAsiaBankOptionsForCurrency($gatewayConfigs[$currencyKey], $rows);

        exit(0);
    } catch (Throwable $e) {
        paymentAsiaBankSyncOut('[ERROR] ' . $e->getMessage());
        if ($e instanceof InvalidArgumentException) {
            paymentAsiaBankSyncOut('Usage: php script/paymentasia_sync_bank_options.php --file=/path/to/banks.json');
        }
        exit(1);
    }
}
