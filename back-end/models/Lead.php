<?php
/**
 * Lead 模型（基于 clientUsers 表）
 */

require_once __DIR__ . '/BaseModel.php';

class Lead extends BaseModel {
    protected $table = 'clientUsers';
    protected $primaryKey = 'id';

    protected $fillable = [
        'email', 'passwordHash', 'firstName', 'lastName', 'phone', 'country',
        'emailVerified', 'emailVerifiedAt', 'status', 'registrationIp',
        'lastLoginAt', 'lastLoginIp'
    ];

    protected $hidden = ['passwordHash'];

    /**
     * 获取Lead的完整信息（使用视图）
     */
    public function getLeadSummary($leadId) {
        // 优先从 clientUsers.kycStatus 获取 KYC 状态（来自 kyc_change.sql）
        $sql = "SELECT
                    cu.id AS leadId,
                    cu.email,
                    cu.firstName,
                    cu.lastName,
                    cu.phone,
                    cu.country,
                    cu.status,
                    cu.emailVerified,
                    cu.registrationIp,
                    cu.createdAt AS registrationDate,
                    cu.lastLoginAt,
                    cu.kycStatus,
                    cu.verifiedAt,
                    cu.accountManagerId,
                    cu.accountManagerNote,
                    cu.accountManagerAssignedAt,
                    au.fullName AS managerName,
                    au.email AS managerEmail,
                    au.username AS managerUsername,
                    (SELECT COUNT(*) FROM leadTagAssignments WHERE leadId = cu.id) AS tagCount,
                    (SELECT COUNT(*) FROM legalDocumentSignatures WHERE leadId = cu.id) AS signedDocumentsCount
                FROM clientUsers cu
                LEFT JOIN adminUsers au ON cu.accountManagerId = au.id
                WHERE cu.id = :leadId
                LIMIT 1";

        $lead = $this->db->fetchOne($sql, ['leadId' => $leadId]);

        // 映射 KYC 状态（统一枚举值）
        if ($lead && isset($lead['kycStatus'])) {
            $lead['kycStatus'] = $this->normalizeKycStatus($lead['kycStatus']);
        }
        if ($lead && !empty($lead['accountManagerAssignedAt']) && $lead['accountManagerAssignedAt'] !== '0000-00-00 00:00:00') {
            $lead['accountManagerAssignedAt'] = date('c', strtotime($lead['accountManagerAssignedAt']));
        }

        return $lead;
    }

    /**
     * 获取所有Leads（带分页）
     * 注意：KYC状态为approved的用户不会显示在Leads列表中（已转为正式客户）
     */
    public function getLeads($page = 1, $perPage = 10, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $conditions = [];
        $params = [];

        // 默认排除KYC状态为approved的用户（已转为正式客户）
        $conditions[] = "(cu.kycStatus IS NULL OR cu.kycStatus != 'approved')";

        // 构建筛选条件
        if (isset($filters['status'])) {
            $conditions[] = "cu.status = :status";
            $params['status'] = $filters['status'];
        }

        if (isset($filters['country'])) {
            $conditions[] = "cu.country = :country";
            $params['country'] = $filters['country'];
        }

        // 暂时注释掉 emailVerified 过滤条件
        // if (isset($filters['emailVerified'])) {
        //     $conditions[] = "cu.emailVerified = :emailVerified";
        //     $params['emailVerified'] = $filters['emailVerified'];
        // }

        if (isset($filters['kycStatus'])) {
            $conditions[] = "cu.kycStatus = :kycStatus";
            $params['kycStatus'] = $filters['kycStatus'];
        }

        if (isset($filters['sales_id']) && (int)$filters['sales_id'] > 0) {
            $conditions[] = "cu.id IN (SELECT clientId FROM sales_bind WHERE salesId = :sales_id)";
            $params['sales_id'] = (int)$filters['sales_id'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        // 优先从 clientUsers.kycStatus 获取 KYC 状态
        $sql = "SELECT
                    cu.id AS leadId,
                    cu.email,
                    cu.firstName,
                    cu.lastName,
                    cu.phone,
                    cu.country,
                    cu.status,
                    cu.emailVerified,
                    cu.registrationIp,
                    cu.createdAt AS registrationDate,
                    cu.lastLoginAt,
                    cu.kycStatus,
                    cu.verifiedAt,
                    cu.accountManagerId,
                    cu.accountManagerNote,
                    cu.accountManagerAssignedAt,
                    au.fullName AS managerName,
                    au.email AS managerEmail,
                    au.username AS managerUsername,
                    (SELECT COUNT(*) FROM leadTagAssignments WHERE leadId = cu.id) AS tagCount,
                    (SELECT COUNT(*) FROM legalDocumentSignatures WHERE leadId = cu.id) AS signedDocumentsCount
                FROM clientUsers cu
                LEFT JOIN adminUsers au ON cu.accountManagerId = au.id
                {$whereClause}
                ORDER BY cu.createdAt DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as count FROM clientUsers cu {$whereClause}";

        $items = $this->db->fetchAll($sql, $params);

        // 映射 KYC 状态（统一枚举值）
        foreach ($items as &$item) {
            if (isset($item['kycStatus'])) {
                $item['kycStatus'] = $this->normalizeKycStatus($item['kycStatus']);
            }
            if (!empty($item['accountManagerAssignedAt']) && $item['accountManagerAssignedAt'] !== '0000-00-00 00:00:00') {
                $item['accountManagerAssignedAt'] = date('c', strtotime($item['accountManagerAssignedAt']));
            }
        }

        $total = $this->db->fetchOne($countSql, $params)['count'];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * 搜索Leads
     * 注意：KYC状态为approved的用户不会显示在搜索结果中（已转为正式客户）
     * @param int|null $salesId 仅销售时传入，只返回与该 Sales 绑定的 lead（sales_bind）
     */
    public function searchLeads($keyword, $page = 1, $perPage = 10, $salesId = null) {
        $offset = ($page - 1) * $perPage;
        $searchPattern = "%{$keyword}%";
        $salesCondition = ($salesId !== null && (int)$salesId > 0)
            ? " AND cu.id IN (SELECT clientId FROM sales_bind WHERE salesId = " . (int)$salesId . ") "
            : '';

        // 纯数字时按 lead id 精确匹配（列表展示的就是 cu.id），非数字不并入避免被隐式转成 0 误命中
        $idClause = ctype_digit(trim((string)$keyword)) ? ' OR cu.id = ?' : '';

        // 优先从 clientUsers.kycStatus 获取 KYC 状态
        $sql = "SELECT DISTINCT
                    cu.id AS leadId,
                    cu.email,
                    cu.firstName,
                    cu.lastName,
                    cu.phone,
                    cu.country,
                    cu.status,
                    cu.emailVerified,
                    cu.registrationIp,
                    cu.createdAt AS registrationDate,
                    cu.lastLoginAt,
                    cu.kycStatus,
                    cu.verifiedAt,
                    cu.accountManagerId,
                    cu.accountManagerNote,
                    cu.accountManagerAssignedAt,
                    au.fullName AS managerName,
                    au.email AS managerEmail,
                    au.username AS managerUsername,
                    (SELECT COUNT(*) FROM leadTagAssignments WHERE leadId = cu.id) AS tagCount,
                    (SELECT COUNT(*) FROM legalDocumentSignatures WHERE leadId = cu.id) AS signedDocumentsCount
                FROM clientUsers cu
                LEFT JOIN leadTagAssignments lta ON cu.id = lta.leadId
                LEFT JOIN leadTags lt ON lta.tagId = lt.id
                LEFT JOIN adminUsers au ON cu.accountManagerId = au.id
                WHERE (cu.kycStatus IS NULL OR cu.kycStatus != 'approved')
                  AND (cu.firstName LIKE ?
                   OR cu.lastName LIKE ?
                   OR cu.email LIKE ?
                   OR cu.phone LIKE ?
                   OR cu.country LIKE ?
                   OR lt.tagName LIKE ?{$idClause})
                  {$salesCondition}
                ORDER BY cu.createdAt DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(DISTINCT cu.id) as count FROM clientUsers cu
                     LEFT JOIN leadTagAssignments lta ON cu.id = lta.leadId
                     LEFT JOIN leadTags lt ON lta.tagId = lt.id
                     WHERE (cu.kycStatus IS NULL OR cu.kycStatus != 'approved')
                       AND (cu.firstName LIKE ?
                        OR cu.lastName LIKE ?
                        OR cu.email LIKE ?
                        OR cu.phone LIKE ?
                        OR cu.country LIKE ?
                        OR lt.tagName LIKE ?{$idClause})
                       {$salesCondition}";

        // 使用问号占位符，需要为每个占位符提供一个值
        $params = [
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern
        ];
        // id 精确匹配的占位符接在 6 个 LIKE 之后，主查询与 count 查询占位符顺序一致
        if ($idClause !== '') {
            $params[] = (int) trim((string)$keyword);
        }

        $items = $this->db->fetchAll($sql, $params);

        // 映射 KYC 状态（统一枚举值）
        foreach ($items as &$item) {
            if (isset($item['kycStatus'])) {
                $item['kycStatus'] = $this->normalizeKycStatus($item['kycStatus']);
            }
            if (!empty($item['accountManagerAssignedAt']) && $item['accountManagerAssignedAt'] !== '0000-00-00 00:00:00') {
                $item['accountManagerAssignedAt'] = date('c', strtotime($item['accountManagerAssignedAt']));
            }
        }

        $total = $this->db->fetchOne($countSql, $params)['count'];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * 根据邮箱查找Lead
     */
    public function findByEmail($email) {
        return $this->findOne(['email' => $email]);
    }

    /**
     * 获取Lead的标签
     */
    public function getLeadTags($leadId) {
        $sql = "SELECT lt.*
                FROM leadTags lt
                INNER JOIN leadTagAssignments lta ON lt.id = lta.tagId
                WHERE lta.leadId = :leadId
                ORDER BY lt.tagName";

        return $this->db->fetchAll($sql, ['leadId' => $leadId]);
    }

    /**
     * 获取Lead的法律文档签署记录
     */
    public function getLeadDocuments($leadId) {
        $sql = "SELECT lds.*,
                       ld.title,
                       ld.documentType,
                       ld.content,
                       ld.version,
                       ld.languageCode
                FROM legalDocumentSignatures lds
                INNER JOIN legalDocuments ld ON lds.documentId = ld.id
                WHERE lds.leadId = :leadId
                ORDER BY lds.signedAt DESC";

        return $this->db->fetchAll($sql, ['leadId' => $leadId]);
    }

    /**
     * 获取Lead的活动日志
     */
    public function getLeadActivityLog($leadId, $limit = 20) {
        $sql = "SELECT * FROM leadActivityLog
                WHERE leadId = :leadId
                ORDER BY createdAt DESC
                LIMIT {$limit}";

        return $this->db->fetchAll($sql, ['leadId' => $leadId]);
    }

    /**
     * 获取统计信息
     * 注意：统计仅包含KYC状态非approved的用户（Leads）
     */
    public function getStatistics() {
        $sql = "SELECT
                    COUNT(*) as totalLeads,
                    SUM(CASE WHEN DATE(createdAt) = CURDATE() THEN 1 ELSE 0 END) as todayLeads,
                    SUM(CASE WHEN emailVerified = 1 THEN 1 ELSE 0 END) as verifiedLeads,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as activeLeads
                FROM {$this->table}
                WHERE (kycStatus IS NULL OR kycStatus != 'approved')";

        return $this->db->fetchOne($sql);
    }

    /**
     * 获取KYC统计信息（用于统一的统计接口）
     * 注意：Leads页面排除approved用户，他们已转为正式客户
     */
    public function getKycStatistics() {
        // Leads页面只统计未审批通过的用户
        $sql = "SELECT
                    SUM(CASE WHEN kycStatus = 'not_started' OR kycStatus IS NULL THEN 1 ELSE 0 END) as not_started,
                    SUM(CASE WHEN kycStatus = 'in_progress' OR kycStatus = 'incomplete' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN kycStatus IN ('under_review', 'submitted') THEN 1 ELSE 0 END) as pending_review,
                    0 as approved,
                    SUM(CASE WHEN kycStatus = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    COUNT(*) as total
                FROM {$this->table}
                WHERE (kycStatus IS NULL OR kycStatus != 'approved')";

        return $this->db->fetchOne($sql);
    }

    /**
     * 统一 KYC 状态枚举值
     * 将 kyc_change.sql 中的状态映射到前端期望的格式
     */
    private function normalizeKycStatus($status) {
        if (!$status) {
            return 'not_started';
        }

        // kyc_change.sql 中的枚举值:
        // 'not_started','in_progress','submitted','under_review','approved','rejected','incomplete'

        // leads_database.sql 和前端期望的枚举值:
        // 'not_started','in_progress','pending_review','approved','rejected'

        $statusMap = [
            'not_started' => 'not_started',
            'in_progress' => 'in_progress',
            'incomplete' => 'in_progress',
            'submitted' => 'pending_review',
            'under_review' => 'pending_review',
            'approved' => 'approved',
            'rejected' => 'rejected'
        ];

        return $statusMap[$status] ?? $status;
    }
}
