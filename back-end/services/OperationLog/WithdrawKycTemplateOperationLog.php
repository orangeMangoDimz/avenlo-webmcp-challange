<?php
/**
 * 出金 KYC 模板页 — 操作日志写入辅助（log_transaction / withdraw_kyc_templates）
 */

require_once __DIR__ . '/../OperationLogPages.php';
require_once __DIR__ . '/../AdminOperationLogWriter.php';
require_once __DIR__ . '/../OperationLogTexts/TransactionOperationLogTexts.php';
require_once __DIR__ . '/../OperationLogTexts/OperationLogTextHelpers.php';

class WithdrawKycTemplateOperationLog {
    public static function subModule($input = null) {
        return OperationLogPages::resolveLogWithdrawKycTemplates(is_array($input) ? $input : []);
    }

    public static function logMutation($input, $operationTypeKey, $templateId, $detailZh, $detailEn) {
        (new AdminOperationLogWriter())->logWithdrawKycTemplateMutation(
            self::subModule($input),
            $operationTypeKey,
            $templateId,
            $detailZh,
            $detailEn
        );
    }

    public static function logMutationFailure($input, $operationTypeKey, $templateId, $failureMethod, $apiMessage) {
        list($detailZh, $detailEn) = call_user_func(
            ['TransactionOperationLogTexts', $failureMethod],
            $apiMessage
        );
        self::logMutation($input, $operationTypeKey, $templateId, $detailZh, $detailEn);
    }

    public static function logCategoryMutation($input, $operationTypeKey, $templateId, $categoryName, $newCategoryName = null) {
        $meta = TransactionOperationLogTexts::resolveWithdrawKycTemplateMeta($templateId);
        $op = trim((string) $operationTypeKey) ?: 'edit';
        $categoryName = trim((string) $categoryName);

        if ($op === 'add') {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycAddCategory(
                $meta['name'],
                $meta['gateway'],
                $categoryName
            );
        } elseif ($op === 'delete') {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycDeleteCategory(
                $meta['name'],
                $meta['gateway'],
                $categoryName
            );
        } else {
            $newName = trim((string) ($newCategoryName ?? $categoryName));
            if ($newName !== '' && $newName !== $categoryName) {
                list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycRenameCategory(
                    $meta['name'],
                    $meta['gateway'],
                    $categoryName,
                    $newName
                );
            } else {
                list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycUpdateCategory(
                    $meta['name'],
                    $meta['gateway'],
                    $categoryName
                );
            }
        }

        self::logMutation($input, $op, $templateId, $detailZh, $detailEn);
    }

    public static function logQuestionMutation(
        $input,
        $operationTypeKey,
        $templateId,
        $questionText,
        $newQuestionText = null
    ) {
        $meta = TransactionOperationLogTexts::resolveWithdrawKycTemplateMeta($templateId);
        $op = trim((string) $operationTypeKey) ?: 'edit';
        $questionText = trim((string) $questionText);

        if ($op === 'add') {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycAddQuestion(
                $meta['name'],
                $meta['gateway'],
                $questionText
            );
        } elseif ($op === 'delete') {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycDeleteQuestion(
                $meta['name'],
                $meta['gateway'],
                $questionText
            );
        } else {
            $newText = trim((string) ($newQuestionText ?? $questionText));
            if ($newText !== '' && $newText !== $questionText) {
                list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycChangeQuestion(
                    $meta['name'],
                    $meta['gateway'],
                    $questionText,
                    $newText
                );
            } else {
                list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycUpdateQuestion(
                    $meta['name'],
                    $meta['gateway'],
                    $questionText
                );
            }
        }

        self::logMutation($input, $op, $templateId, $detailZh, $detailEn);
    }

    public static function logQuestionDuplicate($input, $templateId, $questionText) {
        $meta = TransactionOperationLogTexts::resolveWithdrawKycTemplateMeta($templateId);
        list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycDuplicateQuestion(
            $meta['name'],
            $meta['gateway'],
            $questionText
        );
        self::logMutation($input, 'add', $templateId, $detailZh, $detailEn);
    }

    public static function logRuleMutation($input, $operationTypeKey, $templateId, $ruleName, $newRuleName = null) {
        $meta = TransactionOperationLogTexts::resolveWithdrawKycTemplateMeta($templateId);
        $op = trim((string) $operationTypeKey) ?: 'edit';
        $ruleName = trim((string) $ruleName);

        if ($op === 'add') {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycAddRule(
                $meta['name'],
                $meta['gateway'],
                $ruleName
            );
        } elseif ($op === 'delete') {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycDeleteRule(
                $meta['name'],
                $meta['gateway'],
                $ruleName
            );
        } else {
            $newName = trim((string) ($newRuleName ?? $ruleName));
            if ($newName !== '' && $newName !== $ruleName) {
                list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycRenameRule(
                    $meta['name'],
                    $meta['gateway'],
                    $ruleName,
                    $newName
                );
            } else {
                list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycUpdateRule(
                    $meta['name'],
                    $meta['gateway'],
                    $ruleName
                );
            }
        }

        self::logMutation($input, $op, $templateId, $detailZh, $detailEn);
    }

    public static function logDocumentMutation($input, $operationTypeKey, $templateId, $documentTitle, $newTitle = null) {
        $meta = TransactionOperationLogTexts::resolveWithdrawKycTemplateMeta($templateId);
        $op = trim((string) $operationTypeKey) ?: 'edit';
        $documentTitle = trim((string) $documentTitle);

        if ($op === 'add') {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycAddDocument(
                $meta['name'],
                $meta['gateway'],
                $documentTitle
            );
        } elseif ($op === 'delete') {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycDeleteDocument(
                $meta['name'],
                $meta['gateway'],
                $documentTitle
            );
        } else {
            $newName = trim((string) ($newTitle ?? $documentTitle));
            if ($newName !== '' && $newName !== $documentTitle) {
                list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycRenameDocument(
                    $meta['name'],
                    $meta['gateway'],
                    $documentTitle,
                    $newName
                );
            } else {
                list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycUpdateDocument(
                    $meta['name'],
                    $meta['gateway'],
                    $documentTitle
                );
            }
        }

        self::logMutation($input, $op, $templateId, $detailZh, $detailEn);
    }
}
