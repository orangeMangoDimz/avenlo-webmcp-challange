<?php
/**
 * Withdrawal Verification Activity Log Model
 * 对应表: withdrawalVerificationActivityLog
 */

require_once __DIR__ . '/BaseModel.php';

class WithdrawalVerificationActivityLog extends BaseModel {
    protected $table = 'withdrawalVerificationActivityLog';
    protected $primaryKey = 'id';
    protected $fillable = [
        'submissionId',
        'activityType',
        'description',
        'performedBy',
        'metadata',
        'ipAddress'
    ];

    public function getSubmissionActivities($submissionId, $limit = 50) {
        $limit = max(1, (int)$limit);
        $sql = "SELECT
                    a.*,
                    u.fullName AS performedByName
                FROM withdrawalVerificationActivityLog a
                LEFT JOIN adminUsers u ON a.performedBy = u.id
                WHERE a.submissionId = :submissionId
                ORDER BY a.createdAt DESC
                LIMIT {$limit}";

        return $this->query($sql, ['submissionId' => $submissionId]);
    }

    public function logActivity($submissionId, $activityType, $description, $adminId = null, $metadata = null) {
        return $this->create([
            'submissionId' => $submissionId,
            'activityType' => $activityType,
            'description' => $description,
            'performedBy' => $adminId,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
}
