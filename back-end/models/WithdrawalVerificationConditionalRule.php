<?php
/**
 * Withdrawal Verification Conditional Rule Model
 * 对应表: withdrawalVerificationConditionalRules
 */

require_once __DIR__ . '/BaseModel.php';

class WithdrawalVerificationConditionalRule extends BaseModel {
    protected $table = 'withdrawalVerificationConditionalRules';
    protected $primaryKey = 'id';
    protected $fillable = [
        'templateId',
        'ruleName',
        'ruleType',
        'triggerQuestionId',
        'triggerAnswer',
        'targetQuestionId',
        'rejectMessage',
        'isActive',
        'displayOrder',
        'createdBy'
    ];

    public function getTemplateRules($templateId, $activeOnly = true) {
        $sql = "SELECT
                    r.*,
                    tq.questionText AS triggerQuestionText,
                    tq.questionNumber AS triggerQuestionNumber,
                    tq.questionType AS triggerQuestionType,
                    COALESCE(triggerOption.optionLabel, triggerOption.optionValue, r.triggerAnswer) AS triggerAnswerLabel,
                    target.questionText AS targetQuestionText,
                    target.questionNumber AS targetQuestionNumber
                FROM withdrawalVerificationConditionalRules r
                INNER JOIN withdrawalVerificationQuestions tq ON r.triggerQuestionId = tq.id
                LEFT JOIN withdrawalVerificationQuestionOptions triggerOption
                    ON triggerOption.questionId = r.triggerQuestionId
                    AND triggerOption.optionValue = r.triggerAnswer
                LEFT JOIN withdrawalVerificationQuestions target ON r.targetQuestionId = target.id
                WHERE r.templateId = :templateId";

        if ($activeOnly) {
            $sql .= " AND r.isActive = 1";
        }

        $sql .= " ORDER BY r.displayOrder";

        return $this->query($sql, ['templateId' => $templateId]);
    }

    public function getRuleDetails($ruleId) {
        $sql = "SELECT
                    r.*,
                    tq.questionText AS triggerQuestionText,
                    tq.questionNumber AS triggerQuestionNumber,
                    COALESCE(triggerOption.optionLabel, triggerOption.optionValue, r.triggerAnswer) AS triggerAnswerLabel,
                    target.questionText AS targetQuestionText,
                    target.questionNumber AS targetQuestionNumber
                FROM withdrawalVerificationConditionalRules r
                INNER JOIN withdrawalVerificationQuestions tq ON r.triggerQuestionId = tq.id
                LEFT JOIN withdrawalVerificationQuestionOptions triggerOption
                    ON triggerOption.questionId = r.triggerQuestionId
                    AND triggerOption.optionValue = r.triggerAnswer
                LEFT JOIN withdrawalVerificationQuestions target ON r.targetQuestionId = target.id
                WHERE r.id = :id";

        return $this->queryOne($sql, ['id' => $ruleId]);
    }

    public function evaluateRule($ruleId, $answer) {
        $rule = $this->getRuleDetails($ruleId);

        if (!$rule || (int)$rule['isActive'] !== 1) {
            return null;
        }

        $triggered = is_array($answer)
            ? in_array($rule['triggerAnswer'], $answer, true)
            : ((string)$answer === (string)$rule['triggerAnswer']);

        if (!$triggered) {
            return null;
        }

        return [
            'ruleType' => $rule['ruleType'],
            'ruleName' => $rule['ruleName'],
            'targetQuestionId' => $rule['targetQuestionId'],
            'targetQuestionNumber' => $rule['targetQuestionNumber'],
            'rejectMessage' => $rule['rejectMessage']
        ];
    }

    public function evaluateTemplateRules($templateId, $answers) {
        $rules = $this->getTemplateRules($templateId, true);
        $actions = [];

        foreach ($rules as $rule) {
            $questionId = $rule['triggerQuestionId'];
            if (isset($answers[$questionId])) {
                $result = $this->evaluateRule($rule['id'], $answers[$questionId]);
                if ($result) {
                    $actions[] = $result;
                }
            }
        }

        return $actions;
    }

    public function updateRuleOrder($ruleId, $newOrder) {
        return $this->update($ruleId, ['displayOrder' => $newOrder]);
    }
}
