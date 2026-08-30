<?php
/**
 * Withdrawal Verification Rule Controller
 */

require_once __DIR__ . '/../models/WithdrawalVerificationConditionalRule.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestion.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionOption.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplate.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplateEditHistory.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../services/OperationLog/WithdrawKycTemplateOperationLog.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class WithdrawalVerificationRuleController {
    private $ruleModel;
    private $questionModel;
    private $optionModel;
    private $templateModel;
    private $historyModel;

    public function __construct() {
        $this->ruleModel = new WithdrawalVerificationConditionalRule();
        $this->questionModel = new WithdrawalVerificationQuestion();
        $this->optionModel = new WithdrawalVerificationQuestionOption();
        $this->templateModel = new WithdrawalVerificationTemplate();
        $this->historyModel = new WithdrawalVerificationTemplateEditHistory();
    }

    public function getTemplateRules($templateId) {
        $activeOnly = isset($_GET['active_only']) ? (bool)$_GET['active_only'] : true;
        $rules = $this->ruleModel->getTemplateRules($templateId, $activeOnly);

        Response::success([
            'rules' => $rules,
            'total' => count($rules)
        ]);
    }

    public function show($id) {
        $rule = $this->ruleModel->getRuleDetails($id);
        if (!$rule) {
            Response::notFound('Rule not found');
        }

        Response::success($rule);
    }

    public function create() {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        if (!empty($input['triggerQuestionId']) && $this->questionModel->isLocked((int)$input['triggerQuestionId'])) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'add',
                (int) ($input['templateId'] ?? 0),
                'withdrawKycRuleAddFailure',
                'Locked question cannot be used as jump_from / trigger question'
            );
            Response::validationError(['triggerQuestionId' => 'Locked question cannot be used as jump_from / trigger question']);
        }

        if (($input['ruleType'] ?? null) === 'jump_to' && empty($input['targetQuestionId'])) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'add',
                (int) ($input['templateId'] ?? 0),
                'withdrawKycRuleAddFailure',
                'Target question is required for jump_to rules'
            );
            Response::validationError(['targetQuestionId' => 'Target question is required for jump_to rules']);
        }

        if (($input['ruleType'] ?? null) === 'reject' && empty($input['rejectMessage'])) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'add',
                (int) ($input['templateId'] ?? 0),
                'withdrawKycRuleAddFailure',
                'Reject message is required for reject rules'
            );
            Response::validationError(['rejectMessage' => 'Reject message is required for reject rules']);
        }

        try {
            $ruleId = $this->ruleModel->create([
                'templateId' => (int)$input['templateId'],
                'ruleName' => $input['ruleName'],
                'ruleType' => $input['ruleType'],
                'triggerQuestionId' => (int)$input['triggerQuestionId'],
                'triggerAnswer' => $input['triggerAnswer'],
                'targetQuestionId' => isset($input['targetQuestionId']) ? (int)$input['targetQuestionId'] : null,
                'rejectMessage' => $input['rejectMessage'] ?? null,
                'isActive' => array_key_exists('isActive', $input) ? (int)(bool)$input['isActive'] : 1,
                'displayOrder' => $input['displayOrder'] ?? 0,
                'createdBy' => $adminId
            ]);

            $this->historyModel->logChange(
                (int)$input['templateId'],
                'rule_added',
                "Rule '{$input['ruleName']}' created",
                $adminId
            );
            $this->templateModel->syncCounters((int)$input['templateId']);

            WithdrawKycTemplateOperationLog::logRuleMutation(
                $input,
                'add',
                (int) $input['templateId'],
                (string) ($input['ruleName'] ?? '')
            );

            Response::created($this->ruleModel->getRuleDetails($ruleId), 'Rule created successfully');
        } catch (Exception $e) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'add',
                (int) ($input['templateId'] ?? 0),
                'withdrawKycRuleAddFailure',
                'Failed to create rule: ' . $e->getMessage()
            );
            Response::error('Failed to create rule: ' . $e->getMessage(), 500);
        }
    }

    public function update($id) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $rule = $this->ruleModel->findById($id);
        if (!$rule) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'edit',
                0,
                'withdrawKycRuleEditFailure',
                'Rule not found'
            );
            Response::notFound('Rule not found');
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        $data = [];
        foreach (['ruleName', 'ruleType', 'triggerQuestionId', 'triggerAnswer', 'targetQuestionId', 'rejectMessage', 'displayOrder'] as $field) {
            if (isset($input[$field])) {
                $data[$field] = $input[$field];
            }
        }
        if (isset($input['isActive'])) {
            $data['isActive'] = (int)(bool)$input['isActive'];
        }

        $triggerQuestionId = isset($data['triggerQuestionId']) ? (int)$data['triggerQuestionId'] : (int)$rule['triggerQuestionId'];
        if ($triggerQuestionId > 0 && $this->questionModel->isLocked($triggerQuestionId)) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'edit',
                (int) ($rule['templateId'] ?? 0),
                'withdrawKycRuleEditFailure',
                'Locked question cannot be used as jump_from / trigger question'
            );
            Response::validationError(['triggerQuestionId' => 'Locked question cannot be used as jump_from / trigger question']);
        }

        try {
            $this->ruleModel->update($id, $data);

            $this->historyModel->logChange(
                $rule['templateId'],
                'rule_modified',
                "Rule '{$rule['ruleName']}' updated",
                $adminId
            );
            $this->templateModel->syncCounters((int)$rule['templateId']);

            $updated = $this->ruleModel->getRuleDetails($id);
            $oldName = (string) ($rule['ruleName'] ?? '');
            $newName = (string) ($updated['ruleName'] ?? $oldName);
            WithdrawKycTemplateOperationLog::logRuleMutation(
                $input,
                'edit',
                (int) $rule['templateId'],
                $oldName,
                $newName
            );

            Response::success($updated, 'Rule updated successfully');
        } catch (Exception $e) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'edit',
                (int) ($rule['templateId'] ?? 0),
                'withdrawKycRuleEditFailure',
                'Failed to update rule: ' . $e->getMessage()
            );
            Response::error('Failed to update rule: ' . $e->getMessage(), 500);
        }
    }

    public function delete($id) {
        $input = $_GET;
        $rule = $this->ruleModel->findById($id);
        if (!$rule) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'delete',
                0,
                'withdrawKycRuleDeleteFailure',
                'Rule not found'
            );
            Response::notFound('Rule not found');
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        try {
            $this->ruleModel->delete($id);

            $this->historyModel->logChange(
                $rule['templateId'],
                'rule_deleted',
                "Rule '{$rule['ruleName']}' deleted",
                $adminId
            );
            $this->templateModel->syncCounters((int)$rule['templateId']);

            WithdrawKycTemplateOperationLog::logRuleMutation(
                $input,
                'delete',
                (int) $rule['templateId'],
                (string) ($rule['ruleName'] ?? '')
            );

            Response::success(null, 'Rule deleted successfully');
        } catch (Exception $e) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'delete',
                (int) ($rule['templateId'] ?? 0),
                'withdrawKycRuleDeleteFailure',
                'Failed to delete rule: ' . $e->getMessage()
            );
            Response::error('Failed to delete rule: ' . $e->getMessage(), 500);
        }
    }

    public function getChoiceQuestions($templateId) {
        $questions = $this->questionModel->getChoiceTypeQuestions($templateId);

        foreach ($questions as &$question) {
            if (in_array($question['questionType'], ['single_choice', 'multiple_choice'], true)) {
                $question['options'] = $this->optionModel->formatOptionsForResponse(
                    $this->optionModel->getQuestionOptions($question['id'], true)
                );
            }
        }

        Response::success([
            'questions' => $questions,
            'total' => count($questions)
        ]);
    }
}
