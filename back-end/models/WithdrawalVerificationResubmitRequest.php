<?php
/**
 * Withdrawal Verification Resubmit Request Model
 * 对应表: withdrawalVerificationResubmitRequests
 */

require_once __DIR__ . '/BaseModel.php';

class WithdrawalVerificationResubmitRequest extends BaseModel {
    protected $table = 'withdrawalVerificationResubmitRequests';
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

    public function createRequest($submissionId, $clientId, $requestedBy, $requestedItems, $additionalNotes = null) {
        return $this->create([
            'submissionId' => $submissionId,
            'clientId' => $clientId,
            'requestedBy' => $requestedBy,
            'requestedItems' => json_encode($requestedItems),
            'additionalNotes' => $additionalNotes,
            'status' => 'pending'
        ]);
    }

    public function getLatestRequest($submissionId) {
        $sql = "SELECT * FROM {$this->table}
                WHERE submissionId = :submissionId
                ORDER BY createdAt DESC
                LIMIT 1";
        return $this->queryOne($sql, ['submissionId' => $submissionId]);
    }

    public function getPendingRequests($submissionId) {
        return $this->findAll([
            'submissionId' => $submissionId,
            'status' => 'pending'
        ], 'createdAt DESC');
    }

    public function markAsCompleted($requestId) {
        return $this->update($requestId, [
            'status' => 'completed',
            'completedAt' => date('Y-m-d H:i:s')
        ]);
    }

    public function getRequestWithItems($requestId) {
        $request = $this->findById($requestId);
        if (!$request) {
            return null;
        }

        if (!empty($request['requestedItems'])) {
            $request['requestedItems'] = json_decode($request['requestedItems'], true);
        }

        require_once __DIR__ . '/WithdrawalVerificationResubmitAnswer.php';
        $answerModel = new WithdrawalVerificationResubmitAnswer();
        $request['answers'] = $answerModel->getRequestAnswers($requestId);

        return $request;
    }
}
