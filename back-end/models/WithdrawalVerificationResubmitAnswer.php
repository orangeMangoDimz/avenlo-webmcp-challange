<?php
/**
 * Withdrawal Verification Resubmit Answer Model
 * 对应表: withdrawalVerificationResubmitAnswers
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/../utils/UploadedFilePayload.php';

class WithdrawalVerificationResubmitAnswer extends BaseModel {
    protected $table = 'withdrawalVerificationResubmitAnswers';
    protected $primaryKey = 'id';
    protected $fillable = [
        'requestId',
        'submissionId',
        'itemType',
        'itemId',
        'questionText',
        'questionType',
        'documentName',
        'answerText',
        'answerValues',
        'uploadedFiles'
    ];

    public function saveAnswer($requestId, $submissionId, $itemData) {
        return $this->create([
            'requestId' => $requestId,
            'submissionId' => $submissionId,
            'itemType' => $itemData['type'] ?? 'question',
            'itemId' => $itemData['itemId'] ?? null,
            'questionText' => $itemData['questionText'] ?? null,
            'questionType' => $itemData['questionType'] ?? null,
            'documentName' => $itemData['documentName'] ?? null,
            'answerText' => $itemData['answerText'] ?? null,
            'answerValues' => isset($itemData['answerValues']) ? json_encode($itemData['answerValues']) : null,
            'uploadedFiles' => isset($itemData['uploadedFiles'])
                ? json_encode(UploadedFilePayload::normalizeForStorage($itemData['uploadedFiles']))
                : null
        ]);
    }

    public function saveAnswers($requestId, $submissionId, $answers) {
        $savedCount = 0;
        foreach ($answers as $answer) {
            if ($this->saveAnswer($requestId, $submissionId, $answer)) {
                $savedCount++;
            }
        }
        return $savedCount;
    }

    public function getRequestAnswers($requestId) {
        $answers = $this->findAll(['requestId' => $requestId], 'submittedAt ASC');

        foreach ($answers as &$answer) {
            if (!empty($answer['answerValues'])) {
                $answer['answerValues'] = json_decode($answer['answerValues'], true);
            }
            if (!empty($answer['uploadedFiles'])) {
                $answer['uploadedFiles'] = UploadedFilePayload::normalizeForResponse($answer['uploadedFiles'], false);
            }
        }

        return $answers;
    }
}
