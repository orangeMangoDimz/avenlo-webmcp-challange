<?php
/**
 * KYC Conditional Rule Model
 * 对应表: kycConditionalRules
 */

require_once __DIR__ . '/BaseModel.php';

class KycConditionalRule extends BaseModel {
    protected $table = 'kycConditionalRules';
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

    /**
     * 获取模板的所有规则
     */
    public function getTemplateRules($templateId, $activeOnly = true) {
        $sql = "SELECT
                    r.*,
                    tq.questionText AS triggerQuestionText,
                    tq.questionNumber AS triggerQuestionNumber,
                    tq.questionType AS triggerQuestionType,
                    target.questionText AS targetQuestionText,
                    target.questionNumber AS targetQuestionNumber
                FROM kycConditionalRules r
                INNER JOIN kycQuestions tq ON r.triggerQuestionId = tq.id
                LEFT JOIN kycQuestions target ON r.targetQuestionId = target.id
                WHERE r.templateId = :templateId";

        if ($activeOnly) {
            $sql .= " AND r.isActive = 1";
        }

        $sql .= " ORDER BY r.displayOrder";

        return $this->query($sql, ['templateId' => $templateId]);
    }

    /**
     * 获取规则详情
     */
    public function getRuleDetails($ruleId) {
        $sql = "SELECT
                    r.*,
                    tq.questionText AS triggerQuestionText,
                    tq.questionNumber AS triggerQuestionNumber,
                    target.questionText AS targetQuestionText,
                    target.questionNumber AS targetQuestionNumber
                FROM kycConditionalRules r
                INNER JOIN kycQuestions tq ON r.triggerQuestionId = tq.id
                LEFT JOIN kycQuestions target ON r.targetQuestionId = target.id
                WHERE r.id = :id";

        return $this->queryOne($sql, ['id' => $ruleId]);
    }

    /**
     * 评估规则（检查答案是否触发规则）
     */
    public function evaluateRule($ruleId, $answer) {
        $rule = $this->getRuleDetails($ruleId);

        if (!$rule || $rule['isActive'] != 1) {
            return null;
        }

        // 检查答案是否匹配触发条件
        $triggered = false;

        if (is_array($answer)) {
            // 多选题：检查是否包含触发答案
            $triggered = in_array($rule['triggerAnswer'], $answer);
        } else {
            // 单选题或其他：直接比较
            $triggered = ($answer == $rule['triggerAnswer']);
        }

        if (!$triggered) {
            return null;
        }

        // 返回规则动作
        return [
            'ruleType' => $rule['ruleType'],
            'ruleName' => $rule['ruleName'],
            'targetQuestionId' => $rule['targetQuestionId'],
            'targetQuestionNumber' => $rule['targetQuestionNumber'],
            'rejectMessage' => $rule['rejectMessage']
        ];
    }

    /**
     * 评估模板的所有规则
     */
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

    /**
     * 更新规则顺序
     */
    public function updateRuleOrder($ruleId, $newOrder) {
        return $this->update($ruleId, ['displayOrder' => $newOrder]);
    }
}
