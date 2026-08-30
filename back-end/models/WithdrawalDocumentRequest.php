<?php
/**
 * Withdrawal Document Request Model
 * 对应表: withdrawalDocumentRequests
 */

require_once __DIR__ . '/BaseModel.php';

class WithdrawalDocumentRequest extends BaseModel {
    protected $table = 'withdrawalDocumentRequests';
    protected $primaryKey = 'id';
    protected $fillable = [
        'withdrawalId',
        'requestStatus',
        'requestedBy',
        'requestedAt',
        'submittedAt',
        'reviewedAt',
        'reviewedBy',
        'adminInstructions',
        'adminNotes'
    ];

    /**
     * 获取提款的文档请求
     */
    public function getByWithdrawal($withdrawalId) {
        $sql = "SELECT wdr.*,
                       ra.fullName as requestedByName,
                       rb.fullName as reviewedByName
                FROM {$this->table} wdr
                LEFT JOIN adminUsers ra ON wdr.requestedBy = ra.id
                LEFT JOIN adminUsers rb ON wdr.reviewedBy = rb.id
                WHERE wdr.withdrawalId = :withdrawalId
                ORDER BY wdr.requestedAt DESC
                LIMIT 1";

        return $this->db->fetchOne($sql, ['withdrawalId' => $withdrawalId]);
    }

    /**
     * 获取待处理的文档请求
     */
    public function getPendingRequests($userId = null) {
        $sql = "SELECT wdr.*,
                       w.transactionId, w.amount,
                       u.firstName, u.lastName, u.email
                FROM {$this->table} wdr
                INNER JOIN withdrawals w ON wdr.withdrawalId = w.id
                INNER JOIN clientUsers u ON w.userId = u.id
                WHERE wdr.requestStatus = 'pending'";

        $params = [];

        if ($userId) {
            $sql .= " AND w.userId = :userId";
            $params['userId'] = $userId;
        }

        $sql .= " ORDER BY wdr.requestedAt DESC";

        return $this->query($sql, $params);
    }
}
