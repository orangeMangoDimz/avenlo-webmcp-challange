<?php
/**
 * KYC Conditional Rule 控制器
 */

require_once __DIR__ . '/../models/KycConditionalRule.php';
require_once __DIR__ . '/../models/KycQuestion.php';
require_once __DIR__ . '/../models/KycQuestionOption.php';
require_once __DIR__ . '/../models/KycTemplateEditHistory.php';
require_once __DIR__ . '/../models/KycTemplate.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogTexts/KycOperationLogTexts.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';

class KycRuleController {
    private $ruleModel;
    private $questionModel;
    private $optionModel;
    private $historyModel;

    public function __construct() {
        $this->ruleModel = new KycConditionalRule();
        $this->questionModel = new KycQuestion();
        $this->optionModel = new KycQuestionOption();
        $this->historyModel = new KycTemplateEditHistory();
    }

    /**
     * 获取模板的所有规则
     * GET /api/kyc-templates/{templateId}/rules
     */
    public function getTemplateRules($templateId) {
        $activeOnly = isset($_GET['active_only']) ? (bool)$_GET['active_only'] : true;

        $rules = $this->ruleModel->getTemplateRules($templateId, $activeOnly);

        Response::success([
            'rules' => $rules,
            'total' => count($rules)
        ]);
    }

    /**
     * 获取规则详情
     * GET /api/kyc-rules/{id}
     */
    public function show($id) {
        $rule = $this->ruleModel->getRuleDetails($id);

        if (!$rule) {
            Response::notFound('Rule not found');
        }

        Response::success($rule);
    }

    /**
     * 创建新规则
     * POST /api/kyc-rules
     */
    public function create() {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

//        // 验证
//        $validator = new Validator($input);
//        $validator->required(['templateId', 'ruleName', 'ruleType', 'triggerQuestionId', 'triggerAnswer']);
//        $validator->in('ruleType', ['jump_to', 'reject']);
//
//        if (!$validator->validate()) {
//            Response::validationError($validator->getErrors());
//        }

        // 根据规则类型验证必需字段
        if (($input['ruleType'] ?? '') === 'jump_to' && empty($input['targetQuestionId'])) {
            $this->logRuleMutationFailure(
                $input,
                'add',
                (int) ($input['templateId'] ?? 0),
                'ruleAddFailure',
                OperationLogTextHelpers::validationErrorsToMessage([
                    'targetQuestionId' => ['Target question is required for jump_to rules'],
                ])
            );
            Response::validationError(['targetQuestionId' => 'Target question is required for jump_to rules']);
        }

        if (($input['ruleType'] ?? '') === 'reject' && empty($input['rejectMessage'])) {
            $this->logRuleMutationFailure(
                $input,
                'add',
                (int) ($input['templateId'] ?? 0),
                'ruleAddFailure',
                OperationLogTextHelpers::validationErrorsToMessage([
                    'rejectMessage' => ['Reject message is required for reject rules'],
                ])
            );
            Response::validationError(['rejectMessage' => 'Reject message is required for reject rules']);
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        $data = [
            'templateId' => $input['templateId'],
            'ruleName' => $input['ruleName'],
            'ruleType' => $input['ruleType'],
            'triggerQuestionId' => $input['triggerQuestionId'],
            'triggerAnswer' => $input['triggerAnswer'],
            'targetQuestionId' => $input['targetQuestionId'] ?? null,
            'rejectMessage' => $input['rejectMessage'] ?? null,
            'isActive' => $input['isActive'] == false ? 0 : (empty($input['isActive']) ? 1 : 1),
            'displayOrder' => $input['displayOrder'] ?? 0,
            'createdBy' => $adminId
        ];

        try {
            $ruleId = $this->ruleModel->create($data);

            // 记录历史
            $this->historyModel->logChange(
                $input['templateId'],
                'rule_added',
                "Rule '{$input['ruleName']}' created",
                $adminId
            );

            $rule = $this->ruleModel->getRuleDetails($ruleId);
            $this->logRuleMutation($input, 'add', (int) $input['templateId'], (string) ($input['ruleName'] ?? ''));

            Response::created($rule, 'Rule created successfully');

        } catch (Exception $e) {
            $this->logRuleMutationFailure(
                $input,
                'add',
                (int) ($input['templateId'] ?? 0),
                'ruleAddFailure',
                'Failed to create rule: ' . $e->getMessage()
            );
            Response::error('Failed to create rule: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新规则
     * PUT /api/kyc-rules/{id}
     */
    public function update($id) {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $rule = $this->ruleModel->findById($id);

        if (!$rule) {
            $this->logRuleMutationFailure($input, 'edit', 0, 'ruleEditFailure', 'Rule not found');
            Response::notFound('Rule not found');
        }
        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        $data = [];

        if (isset($input['ruleName'])) {
            $data['ruleName'] = $input['ruleName'];
        }

        if (isset($input['ruleType'])) {
            $data['ruleType'] = $input['ruleType'];
        }

        if (isset($input['triggerQuestionId'])) {
            $data['triggerQuestionId'] = $input['triggerQuestionId'];
        }

        if (isset($input['triggerAnswer'])) {
            $data['triggerAnswer'] = $input['triggerAnswer'];
        }

        if (isset($input['targetQuestionId'])) {
            $data['targetQuestionId'] = $input['targetQuestionId'];
        }

        if (isset($input['rejectMessage'])) {
            $data['rejectMessage'] = $input['rejectMessage'];
        }

        if (isset($input['isActive'])) {
            $data['isActive'] = $input['isActive'];
        }

        if (isset($input['displayOrder'])) {
            $data['displayOrder'] = $input['displayOrder'];
        }

        try {
            $this->ruleModel->update($id, $data);

            // 记录历史
            $this->historyModel->logChange(
                $rule['templateId'],
                'rule_modified',
                "Rule '{$rule['ruleName']}' updated",
                $adminId
            );

            $updatedRule = $this->ruleModel->getRuleDetails($id);
            $this->logRuleMutation(
                $input,
                'edit',
                (int) $rule['templateId'],
                (string) ($rule['ruleName'] ?? ''),
                (string) ($updatedRule['ruleName'] ?? $rule['ruleName'] ?? '')
            );

            Response::success($updatedRule, 'Rule updated successfully');

        } catch (Exception $e) {
            $this->logRuleMutationFailure(
                $input,
                'edit',
                (int) ($rule['templateId'] ?? 0),
                'ruleEditFailure',
                'Failed to update rule: ' . $e->getMessage()
            );
            Response::error('Failed to update rule: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 删除规则
     * DELETE /api/kyc-rules/{id}
     */
    public function delete($id) {
        $rule = $this->ruleModel->findById($id);

        if (!$rule) {
            $this->logRuleMutationFailure(null, 'delete', 0, 'ruleDeleteFailure', 'Rule not found');
            Response::notFound('Rule not found');
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        try {
            $this->ruleModel->delete($id);

            // 记录历史
            $this->historyModel->logChange(
                $rule['templateId'],
                'rule_deleted',
                "Rule '{$rule['ruleName']}' deleted",
                $adminId
            );

            $this->logRuleMutation(
                null,
                'delete',
                (int) $rule['templateId'],
                (string) ($rule['ruleName'] ?? '')
            );

            Response::success(null, 'Rule deleted successfully');

        } catch (Exception $e) {
            $this->logRuleMutationFailure(
                null,
                'delete',
                (int) ($rule['templateId'] ?? 0),
                'ruleDeleteFailure',
                'Failed to delete rule: ' . $e->getMessage()
            );
            Response::error('Failed to delete rule: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取选择题类型的问题（用于规则配置）
     * GET /api/kyc-templates/{templateId}/choice-questions
     */
    public function getChoiceQuestions($templateId) {
        $questions = $this->questionModel->getChoiceTypeQuestions($templateId);

        // 为选择题类型添加选项数据
        foreach ($questions as &$question) {
            if (in_array($question['questionType'], ['single_choice', 'multiple_choice'])) {
                $options = $this->optionModel->getQuestionOptions($question['id'], true);
                $question['options'] = array_map(function($option) {
                    return [
                        'id' => $option['id'],
                        'optionValue' => $option['optionValue']
                    ];
                }, $options);
            }
        }

        Response::success([
            'questions' => $questions,
            'total' => count($questions)
        ]);
    }

    private function logRuleMutationFailure($input, $operationTypeKey, $templateId, $failureMethod, $apiMessage) {
        $subModule = OperationLogPages::resolveLogKycTemplatesFromRequest(is_array($input) ? $input : []);
        list($detailZh, $detailEn) = call_user_func(['KycOperationLogTexts', $failureMethod], $apiMessage);
        (new AdminOperationLogWriter())->logKycTemplateMutation(
            $subModule,
            trim((string) $operationTypeKey) ?: 'edit',
            (int) $templateId > 0 ? (int) $templateId : null,
            $detailZh,
            $detailEn
        );
    }

    private function logRuleMutation($input, $operationTypeKey, $templateId, $ruleName, $newRuleName = null) {
        $subModule = OperationLogPages::resolveLogKycTemplatesFromRequest(is_array($input) ? $input : []);
        $templateName = KycOperationLogTexts::resolveTemplateName($templateId);
        $ruleName = trim((string) $ruleName);
        $op = trim((string) $operationTypeKey) ?: 'edit';

        if ($op === 'add') {
            list($detailZh, $detailEn) = KycOperationLogTexts::addRule($templateName, $ruleName);
        } elseif ($op === 'delete') {
            list($detailZh, $detailEn) = KycOperationLogTexts::deleteRule($templateName, $ruleName);
        } else {
            $newName = trim((string) ($newRuleName ?? $ruleName));
            if ($newName !== '' && $newName !== $ruleName) {
                list($detailZh, $detailEn) = KycOperationLogTexts::renameRule(
                    $templateName,
                    $ruleName,
                    $newName
                );
            } else {
                list($detailZh, $detailEn) = KycOperationLogTexts::updateRule($templateName, $ruleName);
            }
        }

        (new AdminOperationLogWriter())->logKycTemplateMutation(
            $subModule,
            $op,
            (int) $templateId,
            $detailZh,
            $detailEn
        );
    }
}
