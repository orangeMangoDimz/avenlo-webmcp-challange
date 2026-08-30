<?php
/**
 * Client Withdrawal Verification Answer Model
 * 对应表: clientWithdrawalVerificationAnswers
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/../utils/UploadedFilePayload.php';

class ClientWithdrawalVerificationAnswer extends BaseModel {
    protected $table = 'clientWithdrawalVerificationAnswers';
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

    public function getSubmissionAnswers($submissionId) {
        $sql = "SELECT
                    a.*,
                    q.questionNumber,
                    q.questionText,
                    q.questionType,
                    q.helpText,
                    q.scope,
                    q.isLocked,
                    c.id AS categoryId,
                    c.categoryName,
                    c.isLocked AS categoryIsLocked
                FROM clientWithdrawalVerificationAnswers a
                INNER JOIN withdrawalVerificationQuestions q ON a.questionId = q.id
                INNER JOIN withdrawalVerificationQuestionCategories c ON q.categoryId = c.id
                WHERE a.submissionId = :submissionId
                ORDER BY q.displayOrder";

        return $this->query($sql, ['submissionId' => $submissionId]);
    }

    public function saveAnswer($submissionId, $questionId, $answer, $questionType) {
        $data = [
            'submissionId' => $submissionId,
            'questionId' => $questionId
        ];

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
                $data['uploadedFiles'] = json_encode(UploadedFilePayload::normalizeForStorage($answer));
                break;
        }

        $existing = $this->findOne([
            'submissionId' => $submissionId,
            'questionId' => $questionId
        ]);

        if ($existing) {
            return $this->update($existing['id'], $data);
        }

        return $this->create($data);
    }

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

    public function getQuestionAnswer($submissionId, $questionId) {
        return $this->findOne([
            'submissionId' => $submissionId,
            'questionId' => $questionId
        ]);
    }

    public function getAnswerValue($answer) {
        if (!empty($answer['answerText'])) {
            return $answer['answerText'];
        }
        if ($answer['answerNumber'] !== null) {
            return $answer['answerNumber'];
        }
        if (!empty($answer['answerDate'])) {
            return $answer['answerDate'];
        }
        if (!empty($answer['answerValues'])) {
            return json_decode($answer['answerValues'], true);
        }
        if (!empty($answer['uploadedFiles'])) {
            return UploadedFilePayload::normalizeForResponse($answer['uploadedFiles'], false);
        }
        return null;
    }

    public function getNormalizedUploadedFiles($uploadedFiles, $includeDownloadUrl = false) {
        return UploadedFilePayload::normalizeForResponse($uploadedFiles, $includeDownloadUrl);
    }
}
