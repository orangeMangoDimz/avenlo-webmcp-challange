<?php
/**
 * KYC Resubmit Answer Model
 * 对应表: kycResubmitAnswers
 */

require_once __DIR__ . '/BaseModel.php';

class KycResubmitAnswer extends BaseModel {
    protected $table = 'kycResubmitAnswers';
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

    /**
     * 保存重新提交的答案
     */
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
            'uploadedFiles' => isset($itemData['uploadedFiles']) ? json_encode($itemData['uploadedFiles']) : null
        ]);
    }

    /**
     * 批量保存答案
     */
    public function saveAnswers($requestId, $submissionId, $answers) {
        $savedCount = 0;
        foreach ($answers as $answer) {
            if ($this->saveAnswer($requestId, $submissionId, $answer)) {
                $savedCount++;
            }
        }
        return $savedCount;
    }

    /**
     * 获取请求的所有答案
     */
    public function getRequestAnswers($requestId) {
        $answers = $this->findAll(['requestId' => $requestId], 'submittedAt ASC');

        // 解析 JSON 数据
        foreach ($answers as &$answer) {
            if ($answer['answerValues']) {
                $answer['answerValues'] = json_decode($answer['answerValues'], true);
            }
            if ($answer['uploadedFiles']) {
                $answer['uploadedFiles'] = json_decode($answer['uploadedFiles'], true);
            }
        }

        return $answers;
    }

    /**
     * 根据 itemIndex 查找或创建答案记录
     */
    public function findOrCreateByItemIndex($requestId, $submissionId, $itemIndex, $itemData) {
        // 先尝试查找现有的答案记录
        $existing = $this->findOne([
            'requestId' => $requestId,
            'submissionId' => $submissionId,
            'itemId' => $itemIndex
        ]);

        if ($existing) {
            return $existing;
        }

        // 如果没有找到，创建新记录
        return $this->saveAnswer($requestId, $submissionId, $itemData);
    }

    /**
     * 追加文件到 uploadedFiles
     */
    public function appendFile($requestId, $submissionId, $itemIndex, $filePath) {
        $existing = $this->findOne([
            'requestId' => $requestId,
            'submissionId' => $submissionId,
            'itemId' => $itemIndex
        ]);

        $uploadedFiles = [];
        if ($existing && $existing['uploadedFiles']) {
            $uploadedFiles = json_decode($existing['uploadedFiles'], true) ?: [];
        }

        $uploadedFiles[] = $filePath;

        if ($existing) {
            return $this->update($existing['id'], [
                'uploadedFiles' => json_encode($uploadedFiles)
            ]);
        } else {
            // 如果没有现有记录，需要先创建
            // 这里需要 itemData，但在这个方法中我们没有，所以应该在调用前先创建
            return false;
        }
    }
}
