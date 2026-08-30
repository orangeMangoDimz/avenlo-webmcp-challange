<?php
/**
 * Client KYC Submission Model
 * 对应表: clientKycSubmissions
 */

require_once __DIR__ . '/BaseModel.php';

class ClientKycSubmission extends BaseModel {
    protected $table = 'clientKycSubmissions';
    protected $primaryKey = 'id';
    protected $fillable = [
        'clientId',
        'templateId',
        'isThirdParty',
        'submissionStatus',
        'submittedAt',
        'reviewedAt',
        'reviewedBy',
        'approvalNotes',
        'rejectionReason',
        'ipAddress',
        'userAgent',
        'providerApplicantId',
        'externalId'
    ];

    /**
     * 获取客户的所有提交
     */
    public function getClientSubmissions($clientId) {
        return $this->findAll(['clientId' => $clientId], 'createdAt DESC');
    }

    /**
     * 获取客户的最新提交
     */
    public function getLatestSubmission($clientId) {
        $sql = "SELECT * FROM {$this->table}
                WHERE clientId = :clientId
                ORDER BY createdAt DESC
                LIMIT 1";
        return $this->queryOne($sql, ['clientId' => $clientId]);
    }

    /**
     * 获取某客户在某模板下的所有提交（最新在前）
     * 创建新 KYC 前用于判断是否已存在进行中/已通过的提交，避免重复创建
     */
    public function getByClientAndTemplate($clientId, $templateId) {
        return $this->findAll(
            ['clientId' => $clientId, 'templateId' => $templateId],
            'createdAt DESC'
        );
    }

    /**
     * 获取提交进度（使用视图）
     */
    public function getSubmissionProgress($submissionId) {
        $sql = "SELECT * FROM vw_client_kyc_progress WHERE submissionId = :id";
        return $this->queryOne($sql, ['id' => $submissionId]);
    }

    /**
     * 获取所有提交进度（分页）
     */
    public function getAllSubmissionsProgress($page = 1, $perPage = 10, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM vw_client_kyc_progress";
        $params = [];
        $where = [];

        // 排除draft和incomplete状态
        $where[] = "submissionStatus NOT IN ('draft', 'incomplete')";

        if (isset($filters['status'])) {
            $where[] = "submissionStatus = :status";
            $params['status'] = $filters['status'];
        }

        if (isset($filters['templateId'])) {
            $where[] = "templateId = :templateId";
            $params['templateId'] = $filters['templateId'];
        }

        if (!empty($filters['restrict_to_sales_id']) && (int)$filters['restrict_to_sales_id'] > 0) {
            $where[] = "clientId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)";
            $params['restrict_to_sales_id'] = (int)$filters['restrict_to_sales_id'];
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        // 获取总数
        $countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") AS t";
        $totalResult = $this->queryOne($countSql, $params);
        $total = $totalResult['total'] ?? 0;

        // 确保分页参数是正整数
        $perPage = max(1, (int)$perPage);
        $offset = max(0, (int)$offset);

        // 获取数据
        $sql .= " ORDER BY submittedAt DESC LIMIT {$perPage} OFFSET {$offset}";
        $items = $this->query($sql, $params);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * 提交KYC表单
     */
    public function submitKyc($submissionId) {
        return $this->update($submissionId, [
            'submissionStatus' => 'submitted',
            'submittedAt' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 批准KYC
     */
    public function approve($submissionId, $adminId, $notes = null) {
        return $this->update($submissionId, [
            'submissionStatus' => 'approved',
            'reviewedAt' => date('Y-m-d H:i:s'),
            'reviewedBy' => $adminId,
            'approvalNotes' => $notes,
            'updatedAt' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 拒绝KYC
     */
    public function reject($submissionId, $adminId, $reason) {
        return $this->update($submissionId, [
            'submissionStatus' => 'rejected',
            'reviewedAt' => date('Y-m-d H:i:s'),
            'reviewedBy' => $adminId,
            'rejectionReason' => $reason,
            'updatedAt' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 获取待审核的提交
     */
    public function getPendingReviews($limit = 50) {
        $limit = max(1, (int)$limit); // 确保是正整数
        $sql = "SELECT * FROM vw_client_kyc_progress
                WHERE submissionStatus = 'submitted'
                ORDER BY submittedAt ASC
                LIMIT {$limit}";
        return $this->query($sql, []);
    }

    /**
     * 统计提交状态
     */
    public function getSubmissionStatistics($templateId = null) {
        $sql = "SELECT
                    submissionStatus,
                    COUNT(*) AS count
                FROM clientKycSubmissions";

        $params = [];
        $where = [];

        // 排除draft和incomplete状态
        $where[] = "submissionStatus NOT IN ('draft', 'incomplete')";

        if ($templateId) {
            $where[] = "templateId = :templateId";
            $params['templateId'] = $templateId;
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " GROUP BY submissionStatus";

        return $this->query($sql, $params);
    }

    /**
     * 更新提交状态
     */
    public function updateStatus($submissionId, $status, $reviewerId = null) {
        $data = [
            'submissionStatus' => $status,
            'updatedAt' => date('Y-m-d H:i:s')
        ];

        if ($reviewerId) {
            $data['reviewedBy'] = $reviewerId;
            $data['reviewedAt'] = date('Y-m-d H:i:s');
        }

        return $this->update($submissionId, $data);
    }

    /**
     * 分配审核员
     */
    public function assignReviewer($submissionId, $reviewerId, $assignedBy = null) {
        $data = [
            'reviewedBy' => $reviewerId,
            'reviewedAt' => date('Y-m-d H:i:s'),
            'updatedAt' => date('Y-m-d H:i:s')
        ];

        // 分配审核员后，将状态自动更新为 under_review（排除draft和incomplete状态）
        $submission = $this->findById($submissionId);
        if ($submission && !in_array($submission['submissionStatus'], ['draft', 'incomplete', 'approved', 'rejected'])) {
            $data['submissionStatus'] = 'under_review';
        }

        return $this->update($submissionId, $data);
    }
}
