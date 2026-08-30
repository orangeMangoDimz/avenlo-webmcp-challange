<?php
/**
 * Lead KYC 状态模型
 */

require_once __DIR__ . '/BaseModel.php';

class LeadKycStatus extends BaseModel {
    protected $table = 'leadKycStatus';
    protected $primaryKey = 'id';

    protected $fillable = [
        'leadId', 'kycStatus', 'documentSubmittedAt', 'reviewedAt',
        'reviewedBy', 'rejectionReason', 'notes'
    ];

    /**
     * 获取Lead的KYC状态
     */
    public function getLeadKycStatus($leadId) {
        return $this->findOne(['leadId' => $leadId]);
    }

    /**
     * 创建或更新KYC状态
     */
    public function updateKycStatus($leadId, $kycStatus, $reviewedBy = null, $rejectionReason = null, $notes = null) {
        $existing = $this->findOne(['leadId' => $leadId]);

        $data = [
            'kycStatus' => $kycStatus,
            'notes' => $notes
        ];

        if ($reviewedBy) {
            $data['reviewedAt'] = date('Y-m-d H:i:s');
            $data['reviewedBy'] = $reviewedBy;
        }

        if ($kycStatus === 'rejected' && $rejectionReason) {
            $data['rejectionReason'] = $rejectionReason;
        }

        if ($existing) {
            $this->update($existing['id'], $data);
            return $existing['id'];
        } else {
            $data['leadId'] = $leadId;
            return $this->create($data);
        }
    }

    /**
     * 标记文档已提交
     */
    public function markDocumentSubmitted($leadId) {
        $existing = $this->findOne(['leadId' => $leadId]);

        if ($existing) {
            return $this->update($existing['id'], [
                'documentSubmittedAt' => date('Y-m-d H:i:s'),
                'kycStatus' => 'pending_review'
            ]);
        } else {
            return $this->create([
                'leadId' => $leadId,
                'documentSubmittedAt' => date('Y-m-d H:i:s'),
                'kycStatus' => 'pending_review'
            ]);
        }
    }

    /**
     * 批准KYC
     */
    public function approveKyc($leadId, $reviewedBy, $notes = null) {
        return $this->updateKycStatus($leadId, 'approved', $reviewedBy, null, $notes);
    }

    /**
     * 拒绝KYC
     */
    public function rejectKyc($leadId, $reviewedBy, $rejectionReason, $notes = null) {
        return $this->updateKycStatus($leadId, 'rejected', $reviewedBy, $rejectionReason, $notes);
    }

    /**
     * 获取待审核的KYC列表
     */
    public function getPendingReviews($page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT lks.*, cu.firstName, cu.lastName, cu.email, cu.phone, cu.country
                FROM {$this->table} lks
                INNER JOIN clientUsers cu ON lks.leadId = cu.id
                WHERE lks.kycStatus = 'pending_review'
                ORDER BY lks.documentSubmittedAt ASC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as count
                     FROM {$this->table}
                     WHERE kycStatus = 'pending_review'";

        $items = $this->db->fetchAll($sql);
        $total = $this->db->fetchOne($countSql)['count'];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * 获取KYC统计
     */
    public function getKycStatistics() {
        $sql = "SELECT
                    kycStatus,
                    COUNT(*) as count
                FROM {$this->table}
                GROUP BY kycStatus";

        $results = $this->db->fetchAll($sql);

        $stats = [
            'not_started' => 0,
            'in_progress' => 0,
            'pending_review' => 0,
            'approved' => 0,
            'rejected' => 0,
            'total' => 0
        ];

        foreach ($results as $row) {
            $stats[$row['kycStatus']] = $row['count'];
            $stats['total'] += $row['count'];
        }

        return $stats;
    }

    /**
     * 获取特定审核员的审核记录
     */
    public function getReviewsByReviewer($reviewerId, $page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT lks.*, cu.firstName, cu.lastName, cu.email
                FROM {$this->table} lks
                INNER JOIN clientUsers cu ON lks.leadId = cu.id
                WHERE lks.reviewedBy = :reviewerId
                ORDER BY lks.reviewedAt DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as count
                     FROM {$this->table}
                     WHERE reviewedBy = :reviewerId";

        $params = ['reviewerId' => $reviewerId];

        $items = $this->db->fetchAll($sql, $params);
        $total = $this->db->fetchOne($countSql, $params)['count'];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }
}
