<?php
/**
 * Normalize payment support question options to [{label, value}] structure.
 *
 * 用法：
 *   php script/normalize_support_questions.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../models/PaymentSupportQuestion.php';

function normalizeSupportQuestionsOut($message) {
    echo $message . PHP_EOL;
}

try {
    $db = Database::getInstance();
    $paymentSupportQuestionModel = new PaymentSupportQuestion();

    $db->beginTransaction();

    normalizeSupportQuestionsOut('=== Normalize Support Questions ===');

    $supportQuestions = $db->fetchAll(
        "SELECT id, name, scope, options
         FROM paymentSupportQuestions
         ORDER BY id ASC"
    );

    $updatedSupportQuestions = 0;
    foreach ($supportQuestions as $question) {
        $rawOptions = $question['options'] ?? null;
        if ($rawOptions === null || trim((string)$rawOptions) === '') {
            continue;
        }

        $decoded = json_decode((string)$rawOptions, true);
        if (!is_array($decoded)) {
            normalizeSupportQuestionsOut("[SKIP] Invalid JSON: id={$question['id']}");
            continue;
        }

        $normalized = $paymentSupportQuestionModel->normalizeOptionsPayload($decoded);
        $normalizedJson = empty($normalized)
            ? null
            : json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($normalizedJson === (string)$rawOptions) {
            continue;
        }

        $db->update(
            'paymentSupportQuestions',
            [
                'options' => $normalizedJson,
                'updatedAt' => date('Y-m-d H:i:s')
            ],
            'id = :id',
            ['id' => $question['id']]
        );

        $updatedSupportQuestions++;
        normalizeSupportQuestionsOut("[OK] Normalized: id={$question['id']} name={$question['name']} scope={$question['scope']}");
    }

    $db->commit();

    normalizeSupportQuestionsOut('');
    normalizeSupportQuestionsOut('[DONE] Support question normalization completed successfully.');
    normalizeSupportQuestionsOut('paymentSupportQuestions updated: ' . $updatedSupportQuestions);
    exit(0);
} catch (Throwable $e) {
    if (isset($db) && $db->getConnection()->inTransaction()) {
        $db->rollback();
    }

    normalizeSupportQuestionsOut('[ERROR] ' . $e->getMessage());
    exit(1);
}
