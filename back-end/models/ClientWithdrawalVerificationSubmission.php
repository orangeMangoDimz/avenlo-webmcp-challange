<?php
/**
 * Client Withdrawal Verification Submission Model
 * 对应表: clientWithdrawalVerificationSubmissions
 */

require_once __DIR__ . '/BaseModel.php';

class ClientWithdrawalVerificationSubmission extends BaseModel {
    protected $table = 'clientWithdrawalVerificationSubmissions';
    protected $primaryKey = 'id';
    protected $fillable = [
        'clientId',
        'templateId',
        'gatewaySettingId',
        'paymentMethodId',
        'submissionStatus',
        'isClientHidden',
        'submittedAt',
        'reviewedAt',
        'reviewedBy',
        'approvalNotes',
        'rejectionReason',
        'ipAddress',
        'userAgent'
    ];

    // 客户端只展示未被客户隐藏的记录；admin 端用其它查询，不走这里，仍可见全部
    public function getClientSubmissions($clientId) {
        return $this->findAll(['clientId' => $clientId, 'isClientHidden' => 0], 'createdAt DESC');
    }

    // 客户软删除：只对客户隐藏，记录保留，admin 仍可见
    public function hideForClient($submissionId) {
        return $this->update($submissionId, [
            'isClientHidden' => 1,
            'updatedAt' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 分页获取客户在出金模板里的 payment 记录，供后台客户详情复用客户端 payment 结构。
     */
    public function getClientTemplatePayments($clientId, $page = 1, $perPage = 10, $filters = []) {
        $page = max(1, (int)$page);
        $perPage = max(1, (int)$perPage);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT
                    s.*,
                    t.templateName,
                    pgs.gatewayName,
                    pgs.iconClass AS gatewayIconClass,
                    pgs.type AS gatewayType
                FROM {$this->table} s
                LEFT JOIN withdrawalVerificationTemplates t ON t.id = s.templateId
                LEFT JOIN paymentGatewaySettings pgs ON pgs.id = s.gatewaySettingId
                WHERE s.clientId = :clientId";
        $params = ['clientId' => (int)$clientId];

        if (!empty($filters['gatewaySettingId'])) {
            $sql .= " AND s.gatewaySettingId = :gatewaySettingId";
            $params['gatewaySettingId'] = (int)$filters['gatewaySettingId'];
        }

        if (!empty($filters['templateId'])) {
            $sql .= " AND s.templateId = :templateId";
            $params['templateId'] = (int)$filters['templateId'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND s.submissionStatus = :status";
            $params['status'] = $filters['status'];
        }

        $countSql = "SELECT COUNT(*) AS total FROM ({$sql}) AS t";
        $totalResult = $this->queryOne($countSql, $params);
        $total = (int)($totalResult['total'] ?? 0);

        $sql .= " ORDER BY s.createdAt DESC, s.id DESC LIMIT {$perPage} OFFSET {$offset}";

        return [
            'items' => $this->query($sql, $params),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage)
        ];
    }

    public function getLatestSubmission($clientId, $gatewaySettingId = null) {
        $sql = "SELECT * FROM {$this->table} WHERE clientId = :clientId";
        $params = ['clientId' => $clientId];

        if ($gatewaySettingId !== null) {
            $sql .= " AND gatewaySettingId = :gatewaySettingId";
            $params['gatewaySettingId'] = $gatewaySettingId;
        }

        $sql .= " ORDER BY createdAt DESC LIMIT 1";

        return $this->queryOne($sql, $params);
    }

    public function getSubmissionProgress($submissionId) {
        $sql = "SELECT * FROM vw_client_withdrawal_verification_progress WHERE submissionId = :id";
        return $this->queryOne($sql, ['id' => $submissionId]);
    }

    public function getAllSubmissionsProgress($page = 1, $perPage = 10, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM vw_client_withdrawal_verification_progress";
        $params = [];
        $where = [];

        // 审核列表不展示草稿：客户尚未提交，不进入审核流程
        $where[] = "submissionStatus <> 'draft'";

        if (isset($filters['status'])) {
            $where[] = "submissionStatus = :status";
            $params['status'] = $filters['status'];
        }

        if (isset($filters['templateId'])) {
            $where[] = "templateId = :templateId";
            $params['templateId'] = $filters['templateId'];
        }

        if (isset($filters['gatewaySettingId'])) {
            $where[] = "gatewaySettingId = :gatewaySettingId";
            $params['gatewaySettingId'] = $filters['gatewaySettingId'];
        }

        if (isset($filters['clientId'])) {
            $where[] = "clientId = :clientId";
            $params['clientId'] = $filters['clientId'];
        }

        if (!empty($filters['restrict_to_sales_id']) && (int)$filters['restrict_to_sales_id'] > 0) {
            $where[] = "clientId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)";
            $params['restrict_to_sales_id'] = (int)$filters['restrict_to_sales_id'];
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $countSql = "SELECT COUNT(*) AS total FROM (" . $sql . ") AS t";
        $totalResult = $this->queryOne($countSql, $params);
        $total = (int)($totalResult['total'] ?? 0);

        $perPage = max(1, (int)$perPage);
        $offset = max(0, (int)$offset);

        $sql .= " ORDER BY submittedAt DESC, submissionId DESC LIMIT {$perPage} OFFSET {$offset}";
        $items = $this->query($sql, $params);

        return [
            'items' => $items,
            'total' => $total,
            'page' => (int)$page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage)
        ];
    }

    public function submitVerification($submissionId) {
        return $this->update($submissionId, [
            'submissionStatus' => 'submitted',
            'submittedAt' => date('Y-m-d H:i:s')
        ]);
    }

    public function approve($submissionId, $adminId, $notes = null) {
        return $this->update($submissionId, [
            'submissionStatus' => 'approved',
            'reviewedAt' => date('Y-m-d H:i:s'),
            'reviewedBy' => $adminId,
            'approvalNotes' => $notes,
            'updatedAt' => date('Y-m-d H:i:s')
        ]);
    }

    public function reject($submissionId, $adminId, $reason) {
        return $this->update($submissionId, [
            'submissionStatus' => 'rejected',
            'reviewedAt' => date('Y-m-d H:i:s'),
            'reviewedBy' => $adminId,
            'rejectionReason' => $reason,
            'updatedAt' => date('Y-m-d H:i:s')
        ]);
    }

    public function getPendingReviews($limit = 50) {
        $limit = max(1, (int)$limit);
        $sql = "SELECT * FROM vw_client_withdrawal_verification_progress
                WHERE submissionStatus = 'submitted'
                ORDER BY submittedAt ASC
                LIMIT {$limit}";
        return $this->query($sql, []);
    }

    public function getSubmissionStatistics($templateId = null, $gatewaySettingId = null) {
        $sql = "SELECT submissionStatus, COUNT(*) AS count FROM {$this->table}";
        $params = [];
        $where = [];

        if ($templateId !== null) {
            $where[] = "templateId = :templateId";
            $params['templateId'] = $templateId;
        }

        if ($gatewaySettingId !== null) {
            $where[] = "gatewaySettingId = :gatewaySettingId";
            $params['gatewaySettingId'] = $gatewaySettingId;
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " GROUP BY submissionStatus";

        return $this->query($sql, $params);
    }

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

    public function assignReviewer($submissionId, $reviewerId) {
        $data = [
            'reviewedBy' => $reviewerId,
            'reviewedAt' => date('Y-m-d H:i:s'),
            'updatedAt' => date('Y-m-d H:i:s')
        ];

        $submission = $this->findById($submissionId);
        if ($submission && !in_array($submission['submissionStatus'], ['draft', 'incomplete', 'approved', 'rejected'], true)) {
            $data['submissionStatus'] = 'under_review';
        }

        return $this->update($submissionId, $data);
    }
}
