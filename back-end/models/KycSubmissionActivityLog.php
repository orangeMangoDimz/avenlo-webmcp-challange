<?php
/**
 * KYC Submission Activity Log Model
 * 对应表: kycSubmissionActivityLog
 */

require_once __DIR__ . '/BaseModel.php';

class KycSubmissionActivityLog extends BaseModel {
    protected $table = 'kycSubmissionActivityLog';
    protected $primaryKey = 'id';
    protected $fillable = [
        'submissionId',
        'activityType',
        'description',
        'performedBy',
        'metadata',
        'ipAddress'
    ];

    /**
     * 获取提交的活动日志
     */
    public function getSubmissionActivities($submissionId, $limit = 50) {
        $limit = max(1, (int)$limit); // 确保是正整数
        $sql = "SELECT
                    a.*,
                    u.fullName AS performedByName
                FROM kycSubmissionActivityLog a
                LEFT JOIN adminUsers u ON a.performedBy = u.id
                WHERE a.submissionId = :submissionId
                ORDER BY a.createdAt DESC
                LIMIT {$limit}";

        return $this->query($sql, [
            'submissionId' => $submissionId
        ]);
    }

    /**
     * 记录活动
     */
    public function logActivity($submissionId, $activityType, $description, $adminId = null, $metadata = null) {
        $data = [
            'submissionId' => $submissionId,
            'activityType' => $activityType,
            'description' => $description,
            'performedBy' => $adminId,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null
        ];

        return $this->create($data);
    }

    /**
     * 获取最近的活动
     */
    public function getRecentActivities($limit = 100, $filters = []) {
        $sql = "SELECT
                    a.*,
                    u.fullName AS performedByName,
                    cu.email AS clientEmail,
                    cu.firstName AS clientFirstName,
                    cu.lastName AS clientLastName
                FROM kycSubmissionActivityLog a
                LEFT JOIN adminUsers u ON a.performedBy = u.id
                LEFT JOIN clientKycSubmissions s ON a.submissionId = s.id
                LEFT JOIN clientUsers cu ON s.clientId = cu.id";

        $params = [];
        $where = [];

        if (isset($filters['activityType'])) {
            $where[] = "a.activityType = :activityType";
            $params['activityType'] = $filters['activityType'];
        }

        if (isset($filters['performedBy'])) {
            $where[] = "a.performedBy = :performedBy";
            $params['performedBy'] = $filters['performedBy'];
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $limit = max(1, (int)$limit); // 确保是正整数
        $sql .= " ORDER BY a.createdAt DESC LIMIT {$limit}";

        return $this->query($sql, $params);
    }
}
