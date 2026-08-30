<?php
/**
 * KYC Resubmit Request Model
 * 对应表: kycResubmitRequests
 */

require_once __DIR__ . '/BaseModel.php';

class KycResubmitRequest extends BaseModel {
    protected $table = 'kycResubmitRequests';
    protected $primaryKey = 'id';

    protected $fillable = [
        'submissionId',
        'clientId',
        'requestedBy',
        'requestedItems',
        'additionalNotes',
        'status',
        'completedAt'
    ];

    /**
     * 创建重新提交请求
     */
    public function createRequest($submissionId, $clientId, $requestedBy, $requestedItems, $additionalNotes = null) {
        $requestId = $this->create([
            'submissionId' => $submissionId,
            'clientId' => $clientId,
            'requestedBy' => $requestedBy,
            'requestedItems' => json_encode($requestedItems),
            'additionalNotes' => $additionalNotes,
            'status' => 'pending'
        ]);

        return $requestId;
    }

    /**
     * 获取提交的最新请求
     */
    public function getLatestRequest($submissionId) {
        $sql = "SELECT * FROM {$this->table}
                WHERE submissionId = :submissionId
                ORDER BY createdAt DESC
                LIMIT 1";
        return $this->queryOne($sql, ['submissionId' => $submissionId]);
    }

    /**
     * 获取待处理的请求
     */
    public function getPendingRequests($submissionId) {
        return $this->findAll([
            'submissionId' => $submissionId,
            'status' => 'pending'
        ], 'createdAt DESC');
    }

    /**
     * 标记请求为已完成
     */
    public function markAsCompleted($requestId) {
        return $this->update($requestId, [
            'status' => 'completed'
        ]);
    }

    /**
     * 获取请求的详细信息（包含请求的项目）
     */
    public function getRequestWithItems($requestId) {
        $request = $this->findById($requestId);
        if (!$request) {
            return null;
        }

        // 解析 JSON 数据
        if ($request['requestedItems']) {
            $request['requestedItems'] = json_decode($request['requestedItems'], true);
        }

        // 获取答案（如果已提交）
        require_once __DIR__ . '/KycResubmitAnswer.php';
        $answerModel = new KycResubmitAnswer();
        $answers = $answerModel->getRequestAnswers($requestId);
        $request['answers'] = $answers;

        return $request;
    }
}
