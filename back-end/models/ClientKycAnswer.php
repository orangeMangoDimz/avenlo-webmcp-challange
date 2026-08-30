<?php
/**
 * Client KYC Answer Model
 * 对应表: clientKycAnswers
 */

require_once __DIR__ . '/BaseModel.php';

class ClientKycAnswer extends BaseModel {
    protected $table = 'clientKycAnswers';
    protected $primaryKey = 'id';
    protected $fillable = [
        'submissionId',
        'questionId',
        'answerText',
        'answerValues',
        'answerDate',
        'answerNumber',
        'uploadedFiles'
    ];

    /**
     * 获取提交的所有答案
     */
    public function getSubmissionAnswers($submissionId) {
        $sql = "SELECT
                    a.*,
                    q.questionNumber,
                    q.questionText,
                    q.questionType,
                    q.helpText,
                    c.id as categoryId,
                    c.categoryName
                FROM clientKycAnswers a
                INNER JOIN kycQuestions q ON a.questionId = q.id
                INNER JOIN kycQuestionCategories c ON q.categoryId = c.id
                WHERE a.submissionId = :submissionId
                ORDER BY q.displayOrder";

        return $this->query($sql, ['submissionId' => $submissionId]);
    }

    /**
     * 保存答案
     */
    public function saveAnswer($submissionId, $questionId, $answer, $questionType) {
        $data = [
            'submissionId' => $submissionId,
            'questionId' => $questionId
        ];

        // 根据问题类型存储答案
        switch ($questionType) {
            case 'text':
            case 'email':
            case 'tel':
            case 'textarea':
            case 'single_choice':
            case 'yes_no':
                $data['answerText'] = $answer;
                break;

            case 'number':
                $data['answerNumber'] = $answer;
                break;

            case 'date':
                $data['answerDate'] = $answer;
                break;

            case 'multiple_choice':
                $data['answerValues'] = json_encode($answer);
                break;

            case 'file_upload':
                $data['uploadedFiles'] = json_encode($answer);
                break;
        }

        // 检查是否已存在
        $existing = $this->findOne([
            'submissionId' => $submissionId,
            'questionId' => $questionId
        ]);

        if ($existing) {
            // 更新现有答案
            return $this->update($existing['id'], $data);
        } else {
            // 创建新答案
            return $this->create($data);
        }
    }

    /**
     * 批量保存答案
     */
    public function saveAnswersBatch($submissionId, $answers) {
        $saved = 0;

        foreach ($answers as $answer) {
            if ($this->saveAnswer(
                $submissionId,
                $answer['questionId'],
                $answer['answer'],
                $answer['questionType']
            )) {
                $saved++;
            }
        }

        return $saved;
    }

    /**
     * 获取特定问题的答案
     */
    public function getQuestionAnswer($submissionId, $questionId) {
        return $this->findOne([
            'submissionId' => $submissionId,
            'questionId' => $questionId
        ]);
    }

    /**
     * 获取答案的实际值（解析JSON）
     */
    public function getAnswerValue($answer) {
        if ($answer['answerText']) {
            return $answer['answerText'];
        }
        if ($answer['answerNumber']) {
            return $answer['answerNumber'];
        }
        if ($answer['answerDate']) {
            return $answer['answerDate'];
        }
        if ($answer['answerValues']) {
            return json_decode($answer['answerValues'], true);
        }
        if ($answer['uploadedFiles']) {
            return json_decode($answer['uploadedFiles'], true);
        }
        return null;
    }
}
