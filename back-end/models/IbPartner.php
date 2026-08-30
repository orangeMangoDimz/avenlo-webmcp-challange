<?php
/**
 * IB Partner 模型
 * 对应表：ibPartners
 */

require_once __DIR__ . '/BaseModel.php';

class IbPartner extends BaseModel {
    protected $table = 'ibPartners';
    protected $primaryKey = 'id';

    /** 状态：数据库存小写+下划线，页面显示为首字母大写+空格 */
    const STATUS_PENDING_INITIAL_REVIEW = 'pending_initial_review';
    const STATUS_PENDING_RISK_REVIEW = 'pending_risk_review';
    const STATUS_PENDING_FINAL_REVIEW = 'pending_final_review';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /** clients list 用来区分/筛选 IB 的系统标签名 */
    const IB_TAG_NAME = 'IB';

    protected $fillable = [
        'ibCode', 'userId', 'applicationId', 'companyName', 'clientAlias', 'adminAlias',
        'ibType', 'tierLevelId', 'groupId',
        'contactPerson', 'contactEmail', 'contactPhone', 'country',
        'address', 'website', 'totalClients', 'activeClients',
        'totalCommissionEarned', 'totalTradingVolume', 'registrationDate',
        'approvalDate', 'approvedBy', 'status', 'updatedBy',
        'initialReviewer', 'riskReviewer', 'finalReviewer'
    ];

    /**
     * 将数据库 status（小写+下划线）转为页面显示（首字母大写+空格）
     * @param string|null $status 如 pending_initial_review
     * @return string 如 Pending Initial Review
     */
    public static function statusToDisplay($status) {
        if ($status === null || $status === '') {
            return '';
        }
        $map = [
            self::STATUS_PENDING_INITIAL_REVIEW => 'Pending Initial Review',
            self::STATUS_PENDING_RISK_REVIEW => 'Pending Risk Review',
            self::STATUS_PENDING_FINAL_REVIEW => 'Pending Final Review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
        ];
        return $map[$status] ?? ucwords(str_replace('_', ' ', $status));
    }

    /**
     * 获取IB合作伙伴列表（带分页和过滤）
     */
    public function getIbPartners($page = 1, $perPage = 10, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $conditions = [];
        $params = [];

        // 构建筛选条件
        if (isset($filters['status'])) {
            $conditions[] = "ib.status = :status";
            $params['status'] = $filters['status'];
        }

        if (isset($filters['tierLevelId'])) {
            $conditions[] = "ib.tierLevelId = :tierLevelId";
            $params['tierLevelId'] = $filters['tierLevelId'];
        }

        if (isset($filters['country'])) {
            $conditions[] = "ib.country = :country";
            $params['country'] = $filters['country'];
        }

        if (isset($filters['ibType'])) {
            $conditions[] = "ib.ibType = :ibType";
            $params['ibType'] = $filters['ibType'];
        }

        // 支持根据 userId 和 applicationId 查询
        if (isset($filters['userId'])) {
            $conditions[] = "ib.userId = :userId";
            $params['userId'] = $filters['userId'];
        }

        if (isset($filters['applicationId'])) {
            $conditions[] = "ib.applicationId = :applicationId";
            $params['applicationId'] = $filters['applicationId'];
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // 使用视图获取IB列表
        $sql = "SELECT * FROM vw_ibPartnersSummary ib
                {$whereClause}
                ORDER BY ib.registrationDate DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as count
                     FROM ibPartners ib
                     {$whereClause}";

        $items = $this->db->fetchAll($sql, $params);
        $total = $this->db->fetchOne($countSql, $params)['count'];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * 获取IB详细信息（包含规则、文档、性能等）
     */
    public function getIbDetails($ibPartnerId) {
        $sql = "SELECT
                    ib.*,
                    ib.userId,
                    ib.applicationId,
                    tl.tierLevel,
                    tl.tierName,
                    tl.tierDescription,
                    tl.canRecruitSubAgents,
                    tl.canViewReports,
                    tl.canManageClients
                FROM ibPartners ib
                LEFT JOIN ibTierLevels tl ON ib.tierLevelId = tl.id
                WHERE ib.id = :ibPartnerId
                LIMIT 1";

        $ibPartner = $this->db->fetchOne($sql, ['ibPartnerId' => $ibPartnerId]);

        if (!$ibPartner) {
            return null;
        }

        // 获取分配的规则
        $ibPartner['assignedRules'] = $this->getAssignedRules($ibPartnerId);

        // 获取文档
        $ibPartner['documents'] = $this->getDocuments($ibPartnerId);

        // 获取支付设置
        $ibPartner['paymentSettings'] = $this->getPaymentSettings($ibPartnerId);

        // 获取推荐码
        $ibPartner['referralCodes'] = $this->getReferralCodes($ibPartnerId);

        // 获取网络层级信息
        $ibPartner['networkInfo'] = $this->getNetworkInfo($ibPartnerId);

        // 获取最新性能指标
        $ibPartner['latestMetrics'] = $this->getLatestMetrics($ibPartnerId);

        // 获取个人保存的规则数据（customRules 和 preAssignedRules）
        // 直接使用 ibPartners.applicationId
        $applicationId = $ibPartner['applicationId'] ?? null;

        if ($applicationId) {
            // 使用 IbApplication 模型的方法获取 preAssignedRules 和 customRules
            require_once __DIR__ . '/IbApplication.php';
            $ibApplicationModel = new IbApplication();

            $ibPartner['preAssignedRules'] = $ibApplicationModel->getPreAssignedRules($applicationId);
            $ibPartner['customRules'] = $ibApplicationModel->getCustomRules($applicationId);
        } else {
            $ibPartner['preAssignedRules'] = [];
            $ibPartner['customRules'] = [];
        }

        return $ibPartner;
    }

    /**
     * 获取IB分配的佣金规则
     */
    public function getAssignedRules($ibPartnerId) {
        $sql = "SELECT
                    cr.*,
                    ra.assignedAt,
                    (SELECT COUNT(*) FROM ibRuleProducts WHERE ruleId = cr.id) as productCount
                FROM ibPartnerRuleAssignments ra
                INNER JOIN ibCommissionRules cr ON ra.ruleId = cr.id
                WHERE ra.ibPartnerId = :ibPartnerId
                ORDER BY ra.assignedAt DESC";

        return $this->db->fetchAll($sql, ['ibPartnerId' => $ibPartnerId]);
    }

    /**
     * 获取IB文档（从 ibPartnerDocumentAcknowledgements 表）
     */
    public function getDocuments($ibPartnerId) {
        // 从 ibPartnerDocumentAcknowledgements 表获取文档
        $documentsSql = "SELECT
                            pda.id,
                            pda.ibPartnerId,
                            pda.documentTemplateId,
                            pda.acknowledged,
                            pda.acknowledgedAt as signedAt,
                            pda.ipAddress,
                            pda.digitalSignature,
                            dt.documentTitle as title,
                            dt.documentContent as content,
                            dt.iconClass,
                            dt.iconGradient,
                            dt.isRequired,
                            dt.displayOrder
                        FROM ibPartnerDocumentAcknowledgements pda
                        INNER JOIN ibDocumentTemplates dt ON pda.documentTemplateId = dt.id
                        WHERE pda.ibPartnerId = :ibPartnerId
                          AND pda.acknowledged = 1
                        ORDER BY dt.displayOrder ASC, pda.acknowledgedAt ASC";

        return $this->db->fetchAll($documentsSql, ['ibPartnerId' => $ibPartnerId]);
    }

    /**
     * 获取支付设置
     */
    public function getPaymentSettings($ibPartnerId) {
        $sql = "SELECT * FROM ibPartnerPaymentSettings
                WHERE ibPartnerId = :ibPartnerId
                ORDER BY isDefault DESC, id DESC";

        return $this->db->fetchAll($sql, ['ibPartnerId' => $ibPartnerId]);
    }

    /**
     * 获取推荐码
     */
    public function getReferralCodes($ibPartnerId) {
        $sql = "SELECT * FROM ibReferralCodes
                WHERE ibPartnerId = :ibPartnerId
                ORDER BY isActive DESC, createdAt DESC";

        return $this->db->fetchAll($sql, ['ibPartnerId' => $ibPartnerId]);
    }

    /**
     * 获取网络层级信息
     */
    public function getNetworkInfo($ibPartnerId) {
        // 获取父级IB
        $parentSql = "SELECT
                        nh.parentIbPartnerId,
                        nh.hierarchyLevel,
                        ib.ibCode as parentIbCode,
                        ib.companyName as parentCompanyName
                    FROM ibNetworkHierarchy nh
                    LEFT JOIN ibPartners ib ON nh.parentIbPartnerId = ib.id
                    WHERE nh.ibPartnerId = :ibPartnerId";

        $parentInfo = $this->db->fetchOne($parentSql, ['ibPartnerId' => $ibPartnerId]);

        // 获取子级IB数量
        $subIbCountSql = "SELECT COUNT(*) as count
                          FROM ibNetworkHierarchy
                          WHERE parentIbPartnerId = :ibPartnerId";

        $subIbCount = $this->db->fetchOne($subIbCountSql, ['ibPartnerId' => $ibPartnerId])['count'];

        // 获取直接客户数量（来自 ib_partner_bind，isClient=1）
        $directClientsSql = "SELECT COUNT(*) as count
                             FROM ib_partner_bind
                             WHERE parentId = :ibPartnerId AND isClient = 1";

        $directClients = $this->db->fetchOne($directClientsSql, ['ibPartnerId' => $ibPartnerId])['count'];

        return [
            'parentIb' => $parentInfo,
            'subIbsCount' => (int)$subIbCount,
            'directClientsCount' => (int)$directClients,
            'hierarchyLevel' => $parentInfo['hierarchyLevel'] ?? 1
        ];
    }

    /**
     * 获取最新性能指标
     */
    public function getLatestMetrics($ibPartnerId) {
        $sql = "SELECT * FROM ibPerformanceMetrics
                WHERE ibPartnerId = :ibPartnerId
                ORDER BY metricPeriod DESC
                LIMIT 1";

        return $this->db->fetchOne($sql, ['ibPartnerId' => $ibPartnerId]);
    }

    /**
     * 搜索IB合作伙伴
     */
    public function searchIbPartners($search, $page = 1, $perPage = 10) {
        $offset = ($page - 1) * $perPage;
        $searchParam = '%' . $search . '%';

        $sql = "SELECT * FROM vw_ibPartnersSummary
                WHERE companyName LIKE :search
                   OR ibCode LIKE :search
                   OR contactEmail LIKE :search
                ORDER BY registrationDate DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as count
                     FROM ibPartners
                     WHERE companyName LIKE :search
                        OR ibCode LIKE :search
                        OR contactEmail LIKE :search";

        $items = $this->db->fetchAll($sql, ['search' => $searchParam]);
        $items = $this->attachMallPointsBalanceToIbSummaryRows($items);
        $total = $this->db->fetchOne($countSql, ['search' => $searchParam])['count'];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * 获取IB客户列表（来自 ib_partner_bind，isClient=1）
     */
    public function getIbClients($ibPartnerId, $page = 1, $perPage = 10) {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT
                    b.id,
                    cu.id AS clientId,
                    b.parentId AS ibPartnerId,
                    NULL AS referralCode,
                    NULL AS referralDate,
                    1 AS isActive,
                    cu.firstName,
                    cu.lastName,
                    cu.email,
                    cu.country,
                    cu.status as clientStatus,
                    cu.createdAt as clientRegistrationDate
                FROM ib_partner_bind b
                INNER JOIN clientUsers cu ON b.childClientId = cu.id
                WHERE b.parentId = :ibPartnerId AND b.isClient = 1
                ORDER BY cu.email ASC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as count
                     FROM ib_partner_bind
                     WHERE parentId = :ibPartnerId AND isClient = 1";

        $items = $this->db->fetchAll($sql, ['ibPartnerId' => $ibPartnerId]);
        $total = $this->db->fetchOne($countSql, ['ibPartnerId' => $ibPartnerId])['count'];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * 获取某 IB「整个下级网络」下的所有客户（含其下级 IB 名下的客户），分页。
     * 用于后台 client detail 的 IB tab。每个客户只挂在一个上级 IB 下，按 cu.id 自然唯一。
     * 返回字段：clientId、姓名、email、country、kycStatus、客户状态、注册时间、
     *          直属上级 IB(parentIbId/parentIbCode)、该客户本身是否为已通过的 IB(isIb)。
     */
    public function getNetworkClients($ibPartnerId, $page = 1, $perPage = 10) {
        return $this->queryNetworkClients($ibPartnerId, $page, $perPage, 100);
    }

    public function getNetworkClientsBatch($ibPartnerId, $page = 1, $perPage = 500) {
        return $this->queryNetworkClients($ibPartnerId, $page, $perPage, 1000);
    }

    private function queryNetworkClients($ibPartnerId, $page, $perPage, $maxPerPage) {
        require_once __DIR__ . '/IbPartnerBind.php';

        $ibPartnerId = (int) $ibPartnerId;
        $page = max(1, (int) $page);
        $perPage = (int) $perPage;
        $maxPerPage = (int) $maxPerPage;
        if ($maxPerPage < 1) $maxPerPage = 100;
        if ($perPage < 1) $perPage = 10;
        if ($perPage > $maxPerPage) $perPage = $maxPerPage;
        $offset = ($page - 1) * $perPage;

        // 整个下级网络的 IB id（含自身）
        $bindModel = new IbPartnerBind();
        $ibIds = $bindModel->getDescendantIbPartnerIds($ibPartnerId, true);
        $ibIds = array_values(array_unique(array_filter(array_map('intval', $ibIds))));
        if (empty($ibIds)) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        // $ibIds 均为整型，直接内联到 IN，避免 UNION 两段重复命名参数的兼容问题
        $inList = implode(',', $ibIds);

        // 网络成员与关系树口径一致：下级 IB(isClient=0) + 直属客户(isClient=1) 都算成员。
        // 之前只取了纯客户，导致「子 IB 本身」不出现在表里（树有 3 个、表只有 2 个）。
        $base = "
            SELECT
                cu.id AS clientId, cu.firstName, cu.lastName, cu.email,
                cu.phone, cu.phoneCountryCode, cu.country, cu.kycStatus,
                cu.status AS clientStatus, cu.createdAt AS clientRegistrationDate,
                b.parentId AS parentIbId, pib.ibCode AS parentIbCode,
                1 AS isIb
            FROM ib_partner_bind b
            INNER JOIN ibPartners sib ON b.childId = sib.id
            INNER JOIN clientUsers cu ON sib.userId = cu.id
            LEFT JOIN ibPartners pib ON pib.id = b.parentId
            WHERE b.isClient = 0 AND b.childId IS NOT NULL AND b.parentId IN ({$inList})
            UNION ALL
            SELECT
                cu.id AS clientId, cu.firstName, cu.lastName, cu.email,
                cu.phone, cu.phoneCountryCode, cu.country, cu.kycStatus,
                cu.status AS clientStatus, cu.createdAt AS clientRegistrationDate,
                b.parentId AS parentIbId, pib.ibCode AS parentIbCode,
                CASE WHEN EXISTS (
                    SELECT 1 FROM ibPartners x WHERE x.userId = cu.id AND x.status = 'approved'
                ) THEN 1 ELSE 0 END AS isIb
            FROM ib_partner_bind b
            INNER JOIN clientUsers cu ON b.childClientId = cu.id
            LEFT JOIN ibPartners pib ON pib.id = b.parentId
            WHERE b.isClient = 1 AND b.childClientId IS NOT NULL AND b.parentId IN ({$inList})
        ";

        $sql = "SELECT * FROM ({$base}) AS m
                ORDER BY m.clientRegistrationDate DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $countSql = "SELECT COUNT(*) AS count FROM ({$base}) AS m";

        $items = $this->db->fetchAll($sql, []);
        $total = (int) ($this->db->fetchOne($countSql, [])['count'] ?? 0);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * 获取IB佣金历史
     */
    public function getCommissionHistory($ibPartnerId, $page = 1, $perPage = 10) {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM ibCommissionHistory
                WHERE ibPartnerId = :ibPartnerId
                ORDER BY commissionPeriodStart DESC, createdAt DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as count
                     FROM ibCommissionHistory
                     WHERE ibPartnerId = :ibPartnerId";

        $items = $this->db->fetchAll($sql, ['ibPartnerId' => $ibPartnerId]);
        $total = $this->db->fetchOne($countSql, ['ibPartnerId' => $ibPartnerId])['count'];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * 初审列表：仅状态为 Pending Initial Review 的 IB，关联 clientUsers（姓名/邮箱/电话）、
     * ib_partner_rules+ibCommissionRules（规则名）、ibTierLevels、ibApplications（申请日）、adminUsers（各阶段审批人）
     * @param int $page 页码
     * @param int $perPage 每页条数，0 表示不限制
     * @param string|null $search 关键字，按姓名或邮箱模糊匹配（仅 clientUsers.firstName / lastName / email）
     * @return array { items, total, page, per_page }
     */
    public function getInitialReviewList($page = 1, $perPage = 10, $search = null, $restrictToSalesId = null) {
        $offset = ($page - 1) * $perPage;
        $params = [];
        $searchCondition = '';
        $salesRestrictCondition = '';

        $params['statusInitial'] = self::STATUS_PENDING_INITIAL_REVIEW;
        $restrictToSalesId = (int)$restrictToSalesId;
        if ($restrictToSalesId > 0) {
            $params['restrict_to_sales_id'] = $restrictToSalesId;
            $salesRestrictCondition = " AND ib.userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id) ";
        }
        if (!empty($search)) {
            $searchParam = '%' . trim($search) . '%';
            $params['search1'] = $searchParam;
            $params['search2'] = $searchParam;
            $params['search3'] = $searchParam;
            $params['search4'] = $searchParam;
            $searchCondition = " AND (
                cu.firstName LIKE :search1 OR cu.lastName LIKE :search2 OR cu.email LIKE :search3
                OR CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,'')) LIKE :search4
            )";
        }

        $limitClause = $perPage > 0 ? "LIMIT " . (int) $perPage . " OFFSET " . (int) $offset : '';

        $sql = "SELECT
                ib.id,
                ib.ibCode,
                ib.userId,
                ib.applicationId,
                ib.companyName,
                ib.ibType,
                ib.tierLevelId,
                ib.status,
                ib.initialReviewer,
                ib.riskReviewer,
                ib.finalReviewer,
                ib.registrationDate,
                COALESCE(TRIM(CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,''))), ib.companyName) AS ibName,
                COALESCE(cu.email, ib.contactEmail) AS email,
                TRIM(CONCAT(IFNULL(cu.phoneCountryCode,''), ' ', IFNULL(cu.phone,''))) AS phone,
                tl.tierLevel AS tierLevel,
                tl.tierName AS tierLevelName,
                r.ruleNames AS ruleNames,
                r.ruleIds AS ruleIds,
                COALESCE(app.createdAt, ib.registrationDate) AS applicationDate,
                au_ir.fullName AS initialReviewerName,
                au_rr.fullName AS riskReviewerName,
                au_fr.fullName AS finalReviewerName
            FROM ibPartners ib
            LEFT JOIN clientUsers cu ON ib.userId = cu.id
            LEFT JOIN ibTierLevels tl ON ib.tierLevelId = tl.id
            LEFT JOIN ibApplications app ON ib.applicationId = app.id
            LEFT JOIN adminUsers au_ir ON ib.initialReviewer = au_ir.id
            LEFT JOIN adminUsers au_rr ON ib.riskReviewer = au_rr.id
            LEFT JOIN adminUsers au_fr ON ib.finalReviewer = au_fr.id
            LEFT JOIN (
                SELECT pr.ibPartnerId,
                    GROUP_CONCAT(cr.ruleName ORDER BY cr.ruleName SEPARATOR ', ') AS ruleNames,
                    GROUP_CONCAT(pr.ruleId ORDER BY cr.ruleName SEPARATOR ',') AS ruleIds
                FROM ib_partner_rules pr
                INNER JOIN ibCommissionRules cr ON pr.ruleId = cr.id
                GROUP BY pr.ibPartnerId
            ) r ON ib.id = r.ibPartnerId
            WHERE ib.status = :statusInitial
            {$salesRestrictCondition}{$searchCondition}
            ORDER BY ib.registrationDate DESC, ib.id DESC
            {$limitClause}";

        $countSql = "SELECT COUNT(*) AS cnt
            FROM ibPartners ib
            LEFT JOIN clientUsers cu ON ib.userId = cu.id
            WHERE ib.status = :statusInitial
            {$salesRestrictCondition}{$searchCondition}";

        $items = $this->db->fetchAll($sql, $params);
        $total = (int) $this->db->fetchOne($countSql, $params)['cnt'];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * 风险审核列表：仅状态为 Pending Risk Review 的 IB，结构同 getInitialReviewList
     */
    public function getRiskReviewList($page = 1, $perPage = 10, $search = null, $restrictToSalesId = null) {
        $offset = ($page - 1) * $perPage;
        $params = [];
        $searchCondition = '';
        $salesRestrictCondition = '';

        $params['statusRisk'] = self::STATUS_PENDING_RISK_REVIEW;
        $restrictToSalesId = (int)$restrictToSalesId;
        if ($restrictToSalesId > 0) {
            $params['restrict_to_sales_id'] = $restrictToSalesId;
            $salesRestrictCondition = " AND ib.userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id) ";
        }
        if (!empty($search)) {
            $searchParam = '%' . trim($search) . '%';
            $params['search1'] = $searchParam;
            $params['search2'] = $searchParam;
            $params['search3'] = $searchParam;
            $params['search4'] = $searchParam;
            $searchCondition = " AND (
                cu.firstName LIKE :search1 OR cu.lastName LIKE :search2 OR cu.email LIKE :search3
                OR CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,'')) LIKE :search4
            )";
        }

        $limitClause = $perPage > 0 ? "LIMIT " . (int) $perPage . " OFFSET " . (int) $offset : '';

        $sql = "SELECT
                ib.id,
                ib.ibCode,
                ib.userId,
                ib.applicationId,
                ib.companyName,
                ib.clientAlias,
                ib.adminAlias,
                ib.ibType,
                ib.tierLevelId,
                ib.groupId,
                ib.status,
                ib.initialReviewer,
                ib.riskReviewer,
                ib.finalReviewer,
                ib.registrationDate,
                COALESCE(TRIM(CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,''))), ib.companyName) AS ibName,
                COALESCE(cu.email, ib.contactEmail) AS email,
                TRIM(CONCAT(IFNULL(cu.phoneCountryCode,''), ' ', IFNULL(cu.phone,''))) AS phone,
                tl.tierLevel AS tierLevel,
                tl.tierName AS tierLevelName,
                r.ruleNames AS ruleNames,
                COALESCE(grp.groupIds, IF(ib.groupId IS NULL, NULL, CAST(ib.groupId AS CHAR))) AS groupIds,
                COALESCE(grp.groupNames, tg_one.name) AS groupNames,
                COALESCE(app.createdAt, ib.registrationDate) AS applicationDate,
                au_ir.fullName AS initialReviewerName,
                au_rr.fullName AS riskReviewerName,
                au_fr.fullName AS finalReviewerName
            FROM ibPartners ib
            LEFT JOIN clientUsers cu ON ib.userId = cu.id
            LEFT JOIN ibTierLevels tl ON ib.tierLevelId = tl.id
            LEFT JOIN ibApplications app ON ib.applicationId = app.id
            LEFT JOIN adminUsers au_ir ON ib.initialReviewer = au_ir.id
            LEFT JOIN adminUsers au_rr ON ib.riskReviewer = au_rr.id
            LEFT JOIN adminUsers au_fr ON ib.finalReviewer = au_fr.id
            LEFT JOIN trading_group tg_one ON ib.groupId = tg_one.id
            LEFT JOIN (
                SELECT pgt.ibPartnerId,
                    GROUP_CONCAT(pgt.groupId ORDER BY tg.name SEPARATOR ',') AS groupIds,
                    GROUP_CONCAT(tg.name ORDER BY tg.name SEPARATOR ', ') AS groupNames
                FROM ib_partner_trading_groups pgt
                INNER JOIN trading_group tg ON pgt.groupId = tg.id
                GROUP BY pgt.ibPartnerId
            ) grp ON ib.id = grp.ibPartnerId
            LEFT JOIN (
                SELECT pr.ibPartnerId, GROUP_CONCAT(cr.ruleName ORDER BY cr.ruleName SEPARATOR ', ') AS ruleNames
                FROM ib_partner_rules pr
                INNER JOIN ibCommissionRules cr ON pr.ruleId = cr.id
                GROUP BY pr.ibPartnerId
            ) r ON ib.id = r.ibPartnerId
            WHERE ib.status = :statusRisk
            {$searchCondition}{$salesRestrictCondition}
            ORDER BY ib.registrationDate DESC, ib.id DESC
            {$limitClause}";

        $countSql = "SELECT COUNT(*) AS cnt
            FROM ibPartners ib
            LEFT JOIN clientUsers cu ON ib.userId = cu.id
            WHERE ib.status = :statusRisk
            {$searchCondition}{$salesRestrictCondition}";

        $items = $this->db->fetchAll($sql, $params);
        $total = (int) $this->db->fetchOne($countSql, $params)['cnt'];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * 终审列表（按绑定关系树形展示）：仅统计并分页「顶级根节点」（无父级的 IB），每页返回这些根及其所有子级的树形扁平列表
     * - 根 = status 符合终审范围 且 id 不在 ib_partner_bind.childId 中
     * - 搜索：仅对 First name、Last name、Email 模糊匹配（作用于根节点筛选）
     */
    public function getFinalReviewList($page = 1, $perPage = 10, $search = null, $restrictToSalesId = null) {
        $this->syncAllTotalClientsFromBindTable();
        $statusParams = [
            'statusInitial' => self::STATUS_PENDING_INITIAL_REVIEW,
            'statusRisk'    => self::STATUS_PENDING_RISK_REVIEW,
        ];
        $restrictToSalesId = (int)$restrictToSalesId;
        $salesDirectIbOnly = $restrictToSalesId > 0;

        // 仅用 IB->IB 绑定构建 children/parent 双向 map（childId 非 NULL）；客户绑定 childId 为 NULL 不参与层级
        $bindRows = $this->db->fetchAll('SELECT parentId, childId FROM ib_partner_bind WHERE childId IS NOT NULL', []);
        $childrenMap = [];
        $parentMap = [];
        foreach ($bindRows as $r) {
            $pid = (int) $r['parentId'];
            $cid = (int) $r['childId'];
            if ($cid <= 0) {
                continue;
            }
            if (!isset($childrenMap[$pid])) {
                $childrenMap[$pid] = [];
            }
            $childrenMap[$pid][] = $cid;
            $parentMap[$cid] = $pid;
        }

        // 取所有合法根（status 不在 initial / risk pending），用于 search 模式下根的合法性校验，也用于非 search 模式直接分页
        if ($restrictToSalesId > 0) {
            $statusParams['restrict_to_sales_id'] = $restrictToSalesId;
            $allRootsSql = "SELECT ib.id
                FROM ibPartners ib
                INNER JOIN sales_bind sb ON sb.clientId = ib.userId AND sb.salesId = :restrict_to_sales_id
                WHERE ib.status NOT IN (:statusInitial, :statusRisk)
                ORDER BY ib.registrationDate DESC, ib.id DESC";
        } else {
            $allRootsSql = "SELECT ib.id
                FROM ibPartners ib
                WHERE ib.status NOT IN (:statusInitial, :statusRisk)
                AND ib.id NOT IN (SELECT childId FROM ib_partner_bind WHERE childId IS NOT NULL)
                ORDER BY ib.registrationDate DESC, ib.id DESC";
        }
        $allRootRows = $this->db->fetchAll($allRootsSql, $statusParams);
        $allRootIds = array_map(function ($r) { return (int) $r['id']; }, $allRootRows);

        $limitRoots = $perPage > 0 ? (int) $perPage : 10000;
        $offsetRoots = ($page - 1) * $limitRoots;

        if (!empty($search)) {
            // 搜索：全树匹配（任意层级 IB 的 cu.name/email 或 ib.companyName/contactEmail/ibCode），向上追到根，
            // 命中根渲染整棵子树。修复原先只在根上 LIKE 导致 Sub-IB 永远搜不到的问题。
            $searchParam = '%' . trim($search) . '%';
            $matchParams = [
                'search1' => $searchParam, 'search2' => $searchParam, 'search3' => $searchParam,
                'search4' => $searchParam, 'search5' => $searchParam, 'search6' => $searchParam,
                'search7' => $searchParam,
            ];
            $matchSql = "SELECT ib.id
                FROM ibPartners ib
                LEFT JOIN clientUsers cu ON ib.userId = cu.id
                WHERE (
                    cu.firstName LIKE :search1 OR cu.lastName LIKE :search2 OR cu.email LIKE :search3
                    OR CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,'')) LIKE :search4
                    OR ib.companyName LIKE :search5 OR ib.contactEmail LIKE :search6 OR ib.ibCode LIKE :search7
                )";
            $matchingRows = $this->db->fetchAll($matchSql, $matchParams);
            $matchingIds = array_map(function ($r) { return (int) $r['id']; }, $matchingRows);

            $rootSet = [];
            if ($salesDirectIbOnly) {
                $directLookup = array_fill_keys($allRootIds, true);
                foreach ($matchingIds as $mid) {
                    if (isset($directLookup[$mid])) {
                        $rootSet[$mid] = true;
                    }
                }
            } else {
            foreach ($matchingIds as $mid) {
                $current = $mid;
                while (isset($parentMap[$current])) {
                    $current = $parentMap[$current];
                }
                $rootSet[$current] = true;
            }
            }

            $rootsToShow = array_values(array_filter($allRootIds, function ($r) use ($rootSet) {
                return isset($rootSet[$r]);
            }));
            $totalRoots = count($rootsToShow);
            $rootIds = array_slice($rootsToShow, $offsetRoots, $limitRoots);
        } else {
            $totalRoots = count($allRootIds);
            $rootIds = array_slice($allRootIds, $offsetRoots, $limitRoots);
        }

        if (empty($rootIds)) {
            return [
                'items' => [],
                'total' => $totalRoots,
                'page' => $page,
                'per_page' => $perPage
            ];
        }

        $treeOrder = [];
        $appendSubtree = function ($nodeId, $depth, $parentId) use ($childrenMap, &$treeOrder, &$appendSubtree) {
            $treeOrder[] = ['id' => $nodeId, 'depth' => $depth, 'parentId' => $parentId];
            if (isset($childrenMap[$nodeId])) {
                foreach ($childrenMap[$nodeId] as $childId) {
                    $appendSubtree($childId, $depth + 1, $nodeId);
                }
            }
        };
        foreach ($rootIds as $rid) {
            if ($salesDirectIbOnly) {
                $treeOrder[] = ['id' => $rid, 'depth' => 0, 'parentId' => null];
            } else {
                $appendSubtree($rid, 0, null);
            }
        }

        $allIds = array_column($treeOrder, 'id');
        $idToMeta = [];
        foreach ($treeOrder as $t) {
            $idToMeta[$t['id']] = ['depth' => $t['depth'], 'parentId' => $t['parentId']];
        }

        $placeholders = implode(',', array_fill(0, count($allIds), '?'));
        $rowSql = "SELECT
                ib.id,
                ib.ibCode,
                ib.userId,
                ib.applicationId,
                ib.companyName,
                ib.clientAlias,
                ib.adminAlias,
                ib.ibType,
                ib.tierLevelId,
                ib.groupId,
                ib.status,
                ib.initialReviewer,
                ib.riskReviewer,
                ib.finalReviewer,
                ib.registrationDate,
                COALESCE(TRIM(CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,''))), ib.companyName) AS ibName,
                COALESCE(cu.email, ib.contactEmail) AS email,
                TRIM(CONCAT(IFNULL(cu.phoneCountryCode,''), ' ', IFNULL(cu.phone,''))) AS phone,
                tl.tierLevel AS tierLevel,
                tl.tierName AS tierLevelName,
                r.ruleNames AS ruleNames,
                COALESCE(grp.groupIds, IF(ib.groupId IS NULL, NULL, CAST(ib.groupId AS CHAR))) AS groupIds,
                COALESCE(grp.groupNames, tg_one.name) AS groupNames,
                COALESCE(app.createdAt, ib.registrationDate) AS applicationDate,
                au_ir.fullName AS initialReviewerName,
                au_rr.fullName AS riskReviewerName,
                au_fr.fullName AS finalReviewerName,
                ib.totalClients AS totalClients,
                (SELECT COUNT(*) FROM ib_partner_bind WHERE parentId = ib.id) AS boundChildCount,
                r.ruleIds AS ruleIds
            FROM ibPartners ib
            LEFT JOIN clientUsers cu ON ib.userId = cu.id
            LEFT JOIN ibTierLevels tl ON ib.tierLevelId = tl.id
            LEFT JOIN ibApplications app ON ib.applicationId = app.id
            LEFT JOIN adminUsers au_ir ON ib.initialReviewer = au_ir.id
            LEFT JOIN adminUsers au_rr ON ib.riskReviewer = au_rr.id
            LEFT JOIN adminUsers au_fr ON ib.finalReviewer = au_fr.id
            LEFT JOIN trading_group tg_one ON ib.groupId = tg_one.id
            LEFT JOIN (
                SELECT pgt.ibPartnerId,
                    GROUP_CONCAT(pgt.groupId ORDER BY tg.name SEPARATOR ',') AS groupIds,
                    GROUP_CONCAT(tg.name ORDER BY tg.name SEPARATOR ', ') AS groupNames
                FROM ib_partner_trading_groups pgt
                INNER JOIN trading_group tg ON pgt.groupId = tg.id
                GROUP BY pgt.ibPartnerId
            ) grp ON ib.id = grp.ibPartnerId
            LEFT JOIN (
                SELECT pr.ibPartnerId,
                    GROUP_CONCAT(cr.ruleName ORDER BY cr.ruleName SEPARATOR ', ') AS ruleNames,
                    GROUP_CONCAT(pr.ruleId ORDER BY cr.ruleName SEPARATOR ',') AS ruleIds
                FROM ib_partner_rules pr
                INNER JOIN ibCommissionRules cr ON pr.ruleId = cr.id
                GROUP BY pr.ibPartnerId
            ) r ON ib.id = r.ibPartnerId
            WHERE ib.id IN ({$placeholders})";
        $rowsById = [];
        foreach ($this->db->fetchAll($rowSql, $allIds) as $row) {
            $rowsById[(int) $row['id']] = $row;
        }

        $items = [];
        foreach ($treeOrder as $t) {
            $id = $t['id'];
            if (!isset($rowsById[$id])) {
                continue;
            }
            $row = $rowsById[$id];
            $row['depth'] = $t['depth'];
            $row['parentId'] = $t['parentId'];
            $row['hasChildren'] = $salesDirectIbOnly ? false : !empty($childrenMap[$id]);
            $items[] = $row;
        }

        $items = $this->attachTotalClientsToFinalReviewList($items);

        return [
            'items' => $items,
            'total' => $totalRoots,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * IB 数量统计（按状态，用于 IB List 页顶部：Total IBs / Active / Pending）
     * 不按层级：Total = 全部 IB，Active = 状态 Approved，Pending = 其余状态
     */
    public function getListStats($restrictToSalesId = null) {
        $restrictToSalesId = (int)$restrictToSalesId;
        if ($restrictToSalesId > 0) {
            $row = $this->db->fetchOne(
                "SELECT COUNT(DISTINCT ib.id) AS cnt,
                        SUM(CASE WHEN ib.status = :approved THEN 1 ELSE 0 END) AS activeCnt
                 FROM ibPartners ib
                 INNER JOIN sales_bind sb ON sb.clientId = ib.userId AND sb.salesId = :restrict_to_sales_id",
                [
                    'restrict_to_sales_id' => $restrictToSalesId,
                    'approved' => self::STATUS_APPROVED,
                ]
            );
            $total = (int)($row['cnt'] ?? 0);
            $active = (int)($row['activeCnt'] ?? 0);
            return [
                'totalIbs' => $total,
                'activeIbs' => $active,
                'pendingIbs' => max(0, $total - $active),
            ];
        }

        $total = (int) $this->db->fetchOne('SELECT COUNT(*) AS cnt FROM ibPartners', [])['cnt'];
        $active = (int) $this->db->fetchOne('SELECT COUNT(*) AS cnt FROM ibPartners WHERE status = ?', [self::STATUS_APPROVED])['cnt'];
        return [
            'totalIbs' => $total,
            'activeIbs' => $active,
            'pendingIbs' => $total - $active
        ];
    }

    /**
     * IB List：与 getFinalReviewList 结构相同。$statusFilter = 'approved' 时仅返回状态为 Approved 的根及其 Approved 子节点
     * 用于后台 Client 分组下 IB List 页（默认只显示 Approved）
     * 有关键字时：在全树中匹配 name/email/ibCode/companyName，只返回「与关键字相关的」层级路径（匹配节点 + 其祖先 + 其子孙），便于在树中一眼看到命中项
     */
    public function getAllStatusList($page = 1, $perPage = 10, $search = null, $statusFilter = 'approved', $restrictToSalesId = null) {
        $this->syncAllTotalClientsFromBindTable();
        $params = [];
        $statusCondition = '';
        if ($statusFilter === 'approved') {
            $statusCondition = " AND ib.status = :statusApproved";
            $params['statusApproved'] = self::STATUS_APPROVED;
        }
        $restrictToSalesId = (int)$restrictToSalesId;

        $bindRows = $this->db->fetchAll('SELECT parentId, childId FROM ib_partner_bind WHERE childId IS NOT NULL', []);
        $childrenMap = [];
        $parentMap = [];
        foreach ($bindRows as $r) {
            $pid = (int) $r['parentId'];
            $cid = (int) $r['childId'];
            if ($cid <= 0) continue;
            if (!isset($childrenMap[$pid])) $childrenMap[$pid] = [];
            $childrenMap[$pid][] = $cid;
            $parentMap[$cid] = $pid;
        }

        $approvedIds = null;
        if ($statusFilter === 'approved') {
            $approvedRows = $this->db->fetchAll('SELECT id FROM ibPartners WHERE status = ?', [self::STATUS_APPROVED]);
            $approvedIds = array_fill_keys(array_map(function ($r) { return (int) $r['id']; }, $approvedRows), true);
        }

        $boundDirectIbIds = null;
        $allowedIbSet = null;
        if ($restrictToSalesId > 0) {
            $boundParams = array_merge($params, ['restrict_to_sales_id' => $restrictToSalesId]);
            $boundRows = $this->db->fetchAll(
                "SELECT ib.id FROM ibPartners ib
                 INNER JOIN sales_bind sb ON sb.clientId = ib.userId AND sb.salesId = :restrict_to_sales_id
                 WHERE 1=1 {$statusCondition}
                 ORDER BY ib.registrationDate DESC, ib.id DESC",
                $boundParams
            );
            $boundDirectIbIds = array_map(function ($r) { return (int) $r['id']; }, $boundRows);
            if (empty($boundDirectIbIds)) {
                return [
                    'items' => [],
                    'total' => 0,
                    'page' => $page,
                    'per_page' => $perPage,
                ];
            }
        }

        $salesDirectIbOnly = $restrictToSalesId > 0;
        $boundDirectLookup = $boundDirectIbIds !== null ? array_fill_keys($boundDirectIbIds, true) : null;

        $rootIds = [];
        $totalRoots = 0;
        $idsToShow = null;
        $matchingIdsSet = null;

        if (!empty($search)) {
            // 搜索匹配范围扩展：
            // 1) IB 本身：cu.firstName/lastName/email + ib.companyName/contactEmail/ibCode（修复原先漏掉 ib.* 三列导致公司号搜不到）
            // 2) 下挂客户 email（ib_partner_bind isClient=1 + childClientId → clientUsers.email），命中后取 parentId 作为 IB 节点
            // 命中后向上追到根，整棵根树都展示（"找到他的 MIB 全"）
            $searchParam = '%' . trim($search) . '%';
            $matchParams = [
                'search1' => $searchParam, 'search2' => $searchParam, 'search3' => $searchParam,
                'search4' => $searchParam, 'search5' => $searchParam, 'search6' => $searchParam,
                'search7' => $searchParam,
            ];
            $matchSql = "SELECT ib.id
                FROM ibPartners ib
                LEFT JOIN clientUsers cu ON ib.userId = cu.id
                WHERE (
                    cu.firstName LIKE :search1 OR cu.lastName LIKE :search2 OR cu.email LIKE :search3
                    OR CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,'')) LIKE :search4
                    OR ib.companyName LIKE :search5 OR ib.contactEmail LIKE :search6 OR ib.ibCode LIKE :search7
                )";
            $matchingRows = $this->db->fetchAll($matchSql, $matchParams);
            $matchingIds = array_map(function ($r) { return (int) $r['id']; }, $matchingRows);

            // 客户 email 命中：找到挂这个客户的 IB（parentId）
            $clientMatchRows = $this->db->fetchAll(
                "SELECT DISTINCT b.parentId
                 FROM ib_partner_bind b
                 INNER JOIN clientUsers cu ON cu.id = b.childClientId
                 WHERE b.isClient = 1 AND b.childClientId IS NOT NULL
                 AND cu.email LIKE :searchClient",
                ['searchClient' => $searchParam]
            );
            foreach ($clientMatchRows as $r) {
                $pid = (int) $r['parentId'];
                if ($pid > 0) $matchingIds[] = $pid;
            }
            $matchingIds = array_values(array_unique(array_filter($matchingIds)));

            if ($boundDirectLookup !== null) {
                $matchingIds = array_values(array_filter($matchingIds, function ($id) use ($boundDirectLookup) {
                    return isset($boundDirectLookup[$id]);
                }));
            }

            if (empty($matchingIds)) {
                return [
                    'items' => [],
                    'total' => 0,
                    'page' => $page,
                    'per_page' => $perPage
                ];
            }

            // 命中节点集合，给前端 searchMatch 高亮用（含 IB 本身命中 + 客户 email 命中后取到的 parentId）
            $matchingIdsSet = array_fill_keys($matchingIds, true);

            // 向上追到展示根；销售仅直接绑定时只保留命中且绑定的 IB 本身
            $rootSet = [];
            if ($boundDirectLookup !== null) {
                foreach ($matchingIds as $mid) {
                    if (isset($boundDirectLookup[$mid])) {
                        $rootSet[$mid] = true;
                    }
                }
            } else {
            foreach ($matchingIds as $mid) {
                $current = $mid;
                while (isset($parentMap[$current])) {
                    $current = $parentMap[$current];
                }
                $rootSet[$current] = true;
            }
            }

            if ($boundDirectIbIds !== null) {
                $allRootIds = $boundDirectIbIds;
            } else {
            $allRootsSql = "SELECT ib.id FROM ibPartners ib
                WHERE ib.id NOT IN (SELECT childId FROM ib_partner_bind WHERE childId IS NOT NULL)
                {$statusCondition}
                ORDER BY ib.registrationDate DESC, ib.id DESC";
            $rootListParams = $statusFilter === 'approved' ? ['statusApproved' => $params['statusApproved']] : [];
            $allRootRows = $this->db->fetchAll($allRootsSql, $rootListParams);
            $allRootIds = array_map(function ($r) { return (int) $r['id']; }, $allRootRows);
            }

            $rootsToShow = array_values(array_filter($allRootIds, function ($r) use ($rootSet) {
                return isset($rootSet[$r]);
            }));
            $totalRoots = count($rootsToShow);

            $limitRoots = $perPage > 0 ? (int) $perPage : 10000;
            $offsetRoots = ($page - 1) * $limitRoots;
            $rootIds = array_slice($rootsToShow, $offsetRoots, $limitRoots);
            // idsToShow 留 null：让命中根的整棵子树都渲染（"MIB 全"）
        } else {
            if ($boundDirectIbIds !== null) {
                $totalRoots = count($boundDirectIbIds);
                $limitRoots = $perPage > 0 ? (int) $perPage : 10000;
                $offsetRoots = ($page - 1) * $limitRoots;
                $rootIds = array_slice($boundDirectIbIds, $offsetRoots, $limitRoots);
            } else {
            $rootsSql = "SELECT ib.id
                FROM ibPartners ib
                LEFT JOIN clientUsers cu ON ib.userId = cu.id
                WHERE ib.id NOT IN (SELECT childId FROM ib_partner_bind WHERE childId IS NOT NULL)
                {$statusCondition}
                ORDER BY ib.registrationDate DESC, ib.id DESC";
            $limitRoots = $perPage > 0 ? (int) $perPage : 10000;
            $offsetRoots = ($page - 1) * $limitRoots;
            $rootsSql .= " LIMIT " . (int) $limitRoots . " OFFSET " . (int) $offsetRoots;
            $rootRows = $this->db->fetchAll($rootsSql, $params);
            $rootIds = array_map(function ($r) { return (int) $r['id']; }, $rootRows);

            $countRootsSql = "SELECT COUNT(*) AS cnt
                FROM ibPartners ib
                WHERE ib.id NOT IN (SELECT childId FROM ib_partner_bind WHERE childId IS NOT NULL)
                {$statusCondition}";
            $totalRoots = (int) $this->db->fetchOne($countRootsSql, $params)['cnt'];
            }
        }

        if (empty($rootIds)) {
            return [
                'items' => [],
                'total' => $totalRoots,
                'page' => $page,
                'per_page' => $perPage
            ];
        }

        $treeOrder = [];
        $appendSubtree = function ($nodeId, $depth, $parentId) use ($childrenMap, $approvedIds, $statusFilter, $idsToShow, &$treeOrder, &$appendSubtree) {
            if ($idsToShow !== null && !isset($idsToShow[$nodeId])) {
                return;
            }
            $treeOrder[] = ['id' => $nodeId, 'depth' => $depth, 'parentId' => $parentId];
            if (isset($childrenMap[$nodeId])) {
                foreach ($childrenMap[$nodeId] as $childId) {
                    if ($statusFilter === 'approved' && (!isset($approvedIds[$childId]))) {
                        continue;
                    }
                    if ($idsToShow !== null && !isset($idsToShow[$childId])) {
                        continue;
                    }
                    $appendSubtree($childId, $depth + 1, $nodeId);
                }
            }
        };
        foreach ($rootIds as $rid) {
            if ($salesDirectIbOnly) {
                $treeOrder[] = ['id' => $rid, 'depth' => 0, 'parentId' => null];
            } else {
                $appendSubtree($rid, 0, null);
            }
        }

        $allIds = array_column($treeOrder, 'id');
        $placeholders = implode(',', array_fill(0, count($allIds), '?'));
        $rowSql = "SELECT
                ib.id,
                ib.ibCode,
                ib.userId,
                ib.applicationId,
                ib.companyName,
                ib.clientAlias,
                ib.adminAlias,
                ib.ibType,
                ib.tierLevelId,
                ib.groupId,
                ib.status,
                ib.initialReviewer,
                ib.riskReviewer,
                ib.finalReviewer,
                ib.registrationDate,
                COALESCE(TRIM(CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,''))), ib.companyName) AS ibName,
                COALESCE(cu.email, ib.contactEmail) AS email,
                TRIM(CONCAT(IFNULL(cu.phoneCountryCode,''), ' ', IFNULL(cu.phone,''))) AS phone,
                tl.tierLevel AS tierLevel,
                tl.tierName AS tierLevelName,
                r.ruleNames AS ruleNames,
                COALESCE(app.createdAt, ib.registrationDate) AS applicationDate,
                au_ir.fullName AS initialReviewerName,
                au_rr.fullName AS riskReviewerName,
                au_fr.fullName AS finalReviewerName,
                ib.totalClients AS totalClients,
                (SELECT COUNT(*) FROM ib_partner_bind WHERE parentId = ib.id) AS boundChildCount,
                COALESCE(grp.groupNames, tg_one.name) AS groupName
            FROM ibPartners ib
            LEFT JOIN clientUsers cu ON ib.userId = cu.id
            LEFT JOIN ibTierLevels tl ON ib.tierLevelId = tl.id
            LEFT JOIN ibApplications app ON ib.applicationId = app.id
            LEFT JOIN adminUsers au_ir ON ib.initialReviewer = au_ir.id
            LEFT JOIN adminUsers au_rr ON ib.riskReviewer = au_rr.id
            LEFT JOIN adminUsers au_fr ON ib.finalReviewer = au_fr.id
            LEFT JOIN trading_group tg_one ON ib.groupId = tg_one.id
            LEFT JOIN (
                SELECT pgt.ibPartnerId,
                    GROUP_CONCAT(tg.name ORDER BY tg.name SEPARATOR ', ') AS groupNames
                FROM ib_partner_trading_groups pgt
                INNER JOIN trading_group tg ON pgt.groupId = tg.id
                GROUP BY pgt.ibPartnerId
            ) grp ON ib.id = grp.ibPartnerId
            LEFT JOIN (
                SELECT pr.ibPartnerId, GROUP_CONCAT(cr.ruleName ORDER BY cr.ruleName SEPARATOR ', ') AS ruleNames
                FROM ib_partner_rules pr
                INNER JOIN ibCommissionRules cr ON pr.ruleId = cr.id
                GROUP BY pr.ibPartnerId
            ) r ON ib.id = r.ibPartnerId
            WHERE ib.id IN ({$placeholders})";
        $rowsById = [];
        foreach ($this->db->fetchAll($rowSql, $allIds) as $row) {
            $rowsById[(int) $row['id']] = $row;
        }

        $items = [];
        foreach ($treeOrder as $t) {
            $id = $t['id'];
            if (!isset($rowsById[$id])) continue;
            $row = $rowsById[$id];
            $row['depth'] = $t['depth'];
            $row['parentId'] = $t['parentId'];
            $row['hasChildren'] = $salesDirectIbOnly ? false : !empty($childrenMap[$id]);
            if ($matchingIdsSet !== null && isset($matchingIdsSet[$id])) {
                $row['searchMatch'] = true;
            }
            $items[] = $row;
        }

        return [
            'items' => $items,
            'total' => $totalRoots,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * 获取 IB List(all-list) 同口径的单条记录，仅返回 Approved 列表里存在的 IB。
     */
    public function getAllStatusListItemById($ibPartnerId) {
        $ibPartnerId = (int)$ibPartnerId;
        if ($ibPartnerId <= 0) {
            return null;
        }

        $this->syncAllTotalClientsFromBindTable();

        $bindRows = $this->db->fetchAll('SELECT parentId, childId FROM ib_partner_bind WHERE childId IS NOT NULL', []);
        $childrenMap = [];
        $parentMap = [];
        foreach ($bindRows as $r) {
            $parentId = (int)$r['parentId'];
            $childId = (int)$r['childId'];
            if ($childId <= 0) {
                continue;
            }
            if (!isset($childrenMap[$parentId])) {
                $childrenMap[$parentId] = [];
            }
            $childrenMap[$parentId][] = $childId;
            $parentMap[$childId] = $parentId;
        }

        $depth = 0;
        $currentId = $ibPartnerId;
        while (isset($parentMap[$currentId])) {
            $depth++;
            $currentId = (int)$parentMap[$currentId];
        }
        $parentId = $parentMap[$ibPartnerId] ?? null;

        $sql = "SELECT
                ib.id,
                ib.ibCode,
                ib.userId,
                ib.applicationId,
                ib.companyName,
                ib.clientAlias,
                ib.adminAlias,
                ib.ibType,
                ib.tierLevelId,
                ib.groupId,
                ib.status,
                ib.initialReviewer,
                ib.riskReviewer,
                ib.finalReviewer,
                ib.registrationDate,
                COALESCE(TRIM(CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,''))), ib.companyName) AS ibName,
                COALESCE(cu.email, ib.contactEmail) AS email,
                TRIM(CONCAT(IFNULL(cu.phoneCountryCode,''), ' ', IFNULL(cu.phone,''))) AS phone,
                tl.tierLevel AS tierLevel,
                tl.tierName AS tierLevelName,
                r.ruleNames AS ruleNames,
                COALESCE(app.createdAt, ib.registrationDate) AS applicationDate,
                au_ir.fullName AS initialReviewerName,
                au_rr.fullName AS riskReviewerName,
                au_fr.fullName AS finalReviewerName,
                ib.totalClients AS totalClients,
                (SELECT COUNT(*) FROM ib_partner_bind WHERE parentId = ib.id) AS boundChildCount,
                COALESCE(grp.groupNames, tg_one.name) AS groupName
            FROM ibPartners ib
            LEFT JOIN clientUsers cu ON ib.userId = cu.id
            LEFT JOIN ibTierLevels tl ON ib.tierLevelId = tl.id
            LEFT JOIN ibApplications app ON ib.applicationId = app.id
            LEFT JOIN adminUsers au_ir ON ib.initialReviewer = au_ir.id
            LEFT JOIN adminUsers au_rr ON ib.riskReviewer = au_rr.id
            LEFT JOIN adminUsers au_fr ON ib.finalReviewer = au_fr.id
            LEFT JOIN trading_group tg_one ON ib.groupId = tg_one.id
            LEFT JOIN (
                SELECT pgt.ibPartnerId,
                    GROUP_CONCAT(tg.name ORDER BY tg.name SEPARATOR ', ') AS groupNames
                FROM ib_partner_trading_groups pgt
                INNER JOIN trading_group tg ON pgt.groupId = tg.id
                GROUP BY pgt.ibPartnerId
            ) grp ON ib.id = grp.ibPartnerId
            LEFT JOIN (
                SELECT pr.ibPartnerId, GROUP_CONCAT(cr.ruleName ORDER BY cr.ruleName SEPARATOR ', ') AS ruleNames
                FROM ib_partner_rules pr
                INNER JOIN ibCommissionRules cr ON pr.ruleId = cr.id
                GROUP BY pr.ibPartnerId
            ) r ON ib.id = r.ibPartnerId
            WHERE ib.id = :ibPartnerId
              AND ib.status = :statusApproved
            LIMIT 1";

        $row = $this->db->fetchOne($sql, [
            'ibPartnerId' => $ibPartnerId,
            'statusApproved' => self::STATUS_APPROVED
        ]);

        if (!$row) {
            return null;
        }

        $row['depth'] = $depth;
        $row['parentId'] = $parentId;
        $row['hasChildren'] = !empty($childrenMap[$ibPartnerId]);

        return $row;
    }

    /**
     * 为终审列表每条记录附加 totalClients（所有层级子级数量），在应用层用绑定表递归计算，兼容 MySQL 5.7
     * @param array $items getFinalReviewList 查出的行
     * @return array 每行增加 totalClients (int)
     */
    private function attachTotalClientsToFinalReviewList(array $items) {
        // totalClients 由 SELECT ib.totalClients 提供，由 syncTotalClientsForIb 根据 ib_partner_bind 同步
        return $items;
    }

    /**
     * BFS 统计某 IB 的所有后代数量（递归所有层级）
     * @param int $parentId
     * @param array $childrenMap parentId => [childId, ...]
     * @return int
     */
    private function countDescendants($parentId, array $childrenMap) {
        $count = 0;
        $queue = isset($childrenMap[$parentId]) ? $childrenMap[$parentId] : [];
        $seen = [$parentId => true];
        while (!empty($queue)) {
            $id = array_shift($queue);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $count++;
            if (isset($childrenMap[$id])) {
                foreach ($childrenMap[$id] as $c) {
                    if (!isset($seen[$c])) {
                        $queue[] = $c;
                    }
                }
            }
        }
        return $count;
    }

    /**
     * 可绑定列表：供 Bind 弹窗 Select Client 使用
     * - 只显示「下一级」且尚未被任何人绑定的 IB（可新绑定）。已绑定给其他 parent 的下一级 IB 不显示。
     * - 下一级定义：tierLevel = 父级 tierLevel + 1（仅紧邻一层）。例：A=Tier1 显示 Tier2；B=Tier2 显示 Tier3。
     * - 排除所有已在 ib_partner_bind 中作为 childId 的 IB（即已被任意人绑定的下一级），避免 C 点 Bind 时看到已属于 B 的 D。
     * @param int $parentId 当前点击 Bind 的 IB id（父级）
     * @param string|null $search 搜索关键词
     * @return array 列表项含 id, ibCode, ibName, email, tierLevel
     */
    public function getBindablePartners($parentId, $search = null) {
        $parentId = (int) $parentId;
        if ($parentId <= 0) {
            return [];
        }
        $parent = $this->findById($parentId);
        if (!$parent) {
            return [];
        }
        $parentTierLevel = 0;
        if (!empty($parent['tierLevelId'])) {
            $row = $this->db->fetchOne('SELECT tierLevel FROM ibTierLevels WHERE id = :id LIMIT 1', ['id' => (int) $parent['tierLevelId']]);
            $parentTierLevel = $row ? (int) $row['tierLevel'] : 0;
        }
        $nextTierLevel = $parentTierLevel + 1;
        $params = ['parentId' => $parentId, 'nextTierLevel' => $nextTierLevel];
        $searchCondition = '';
        if (!empty($search)) {
            $searchParam = '%' . trim($search) . '%';
            $params['s1'] = $searchParam;
            $params['s2'] = $searchParam;
            $params['s3'] = $searchParam;
            $params['s4'] = $searchParam;
            $searchCondition = " AND (
                cu.firstName LIKE :s1 OR cu.lastName LIKE :s2 OR cu.email LIKE :s3
                OR CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,'')) LIKE :s4
            )";
        }
        $sql = "SELECT
                c.id,
                c.ibCode,
                COALESCE(TRIM(CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,''))), c.companyName) AS ibName,
                COALESCE(cu.email, c.contactEmail) AS email,
                tl.tierLevel AS tierLevel
            FROM ibPartners c
            LEFT JOIN clientUsers cu ON c.userId = cu.id
            LEFT JOIN ibTierLevels tl ON c.tierLevelId = tl.id
            WHERE c.id != :parentId
            AND c.status = :statusApproved
            AND tl.tierLevel = :nextTierLevel
            AND c.id NOT IN (SELECT childId FROM ib_partner_bind WHERE childId IS NOT NULL)
            {$searchCondition}
            ORDER BY c.ibCode ASC, c.id ASC
            LIMIT 200";
        $params['statusApproved'] = self::STATUS_APPROVED;
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * 可绑定的普通客户列表：完成 KYC 认证、不在 ibPartners、不在 ibInvitations（待处理邀请）、未绑定到任意 IB 的 clientUsers（每个普通用户只能绑定一个 IB）
     * 供 Bind 弹窗与「下一级 IB」一起展示
     * @param int $parentId 当前 IB id
     * @param string|null $search 搜索关键词
     * @return array 列表项含 id(clientId), ibName, email, tierLevel(null), itemType('client')
     */
    public function getBindableClients($parentId, $search = null) {
        $parentId = (int) $parentId;
        if ($parentId <= 0) {
            return [];
        }
        $params = [];
        $searchCondition = '';
        if (!empty($search)) {
            $searchParam = '%' . trim($search) . '%';
            $params['s1'] = $searchParam;
            $params['s2'] = $searchParam;
            $params['s3'] = $searchParam;
            $params['s4'] = $searchParam;
            $searchCondition = " AND (
                cu.firstName LIKE :s1 OR cu.lastName LIKE :s2 OR cu.email LIKE :s3
                OR CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,'')) LIKE :s4
            )";
        }
        $sql = "SELECT
                cu.id,
                COALESCE(TRIM(CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,''))), cu.email) AS ibName,
                cu.email,
                NULL AS tierLevel
            FROM clientUsers cu
            WHERE cu.kycStatus = 'approved'
            AND cu.id NOT IN (SELECT userId FROM ibPartners WHERE userId IS NOT NULL)
            AND cu.id NOT IN (
                SELECT clientId FROM ibInvitations
                WHERE invitationStatus IN ('sent', 'viewed', 'pending')
            )
            AND cu.id NOT IN (SELECT childClientId FROM ib_partner_bind WHERE isClient = 1 AND childClientId IS NOT NULL)
            {$searchCondition}
            ORDER BY cu.email ASC, cu.id ASC
            LIMIT 200";
        $rows = $this->db->fetchAll($sql, $params);
        foreach ($rows as &$r) {
            $r['itemType'] = 'client';
            $r['ibCode'] = null;
        }
        unset($r);
        return $rows;
    }

    /**
     * 获取与当前 IB 直接绑定的下一级子级列表（供 Unbind 弹窗展示）
     * 包含：1) 绑定的下一级 IB 2) 绑定的普通客户（均来自 ib_partner_bind）
     * @param int $parentId 当前 IB id
     * @return array 含 id, itemType('ib'|'client'), ibCode, ibName, email, tierLevel, boundChildCount
     */
    public function getBoundChildren($parentId) {
        $parentId = (int) $parentId;
        if ($parentId <= 0) {
            return [];
        }
        $sql = "SELECT
                c.id,
                c.ibCode,
                COALESCE(TRIM(CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,''))), c.companyName) AS ibName,
                COALESCE(cu.email, c.contactEmail) AS email,
                tl.tierLevel AS tierLevel,
                (SELECT COUNT(*) FROM ib_partner_bind b2 WHERE b2.parentId = c.id AND b2.childId IS NOT NULL) AS boundChildCount
            FROM ib_partner_bind b
            INNER JOIN ibPartners c ON b.childId = c.id
            LEFT JOIN clientUsers cu ON c.userId = cu.id
            LEFT JOIN ibTierLevels tl ON c.tierLevelId = tl.id
            WHERE b.parentId = :parentId
            ORDER BY c.ibCode ASC, c.id ASC";
        $ibList = $this->db->fetchAll($sql, ['parentId' => $parentId]);
        foreach ($ibList as &$row) {
            $row['itemType'] = 'ib';
        }
        unset($row);

        $clientSql = "SELECT
                cu.id,
                NULL AS ibCode,
                COALESCE(TRIM(CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,''))), cu.email) AS ibName,
                cu.email,
                NULL AS tierLevel,
                0 AS boundChildCount
            FROM ib_partner_bind b
            INNER JOIN clientUsers cu ON b.childClientId = cu.id
            WHERE b.parentId = :parentId AND b.isClient = 1
            ORDER BY cu.email ASC, cu.id ASC";
        $clientList = $this->db->fetchAll($clientSql, ['parentId' => $parentId]);
        foreach ($clientList as &$row) {
            $row['itemType'] = 'client';
        }
        unset($row);

        return array_merge($ibList, $clientList);
    }

    /**
     * 仅 IB↔IB：改 Tier Level 前若有父级或子级 IB 绑定则不能修改（普通客户绑定不计入）
     * @param int $ibPartnerId ibPartners.id
     * @return array{parentIb: array|null, childIbs: array}
     */
    public function getIbIbTierChangeBlockers($ibPartnerId) {
        $ibPartnerId = (int) $ibPartnerId;
        if ($ibPartnerId <= 0) {
            return ['parentIb' => null, 'childIbs' => []];
        }
        $parentRow = $this->db->fetchOne(
            "SELECT ib.id, ib.ibCode,
                COALESCE(TRIM(CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,''))), ib.companyName) AS ibName
             FROM ib_partner_bind b
             INNER JOIN ibPartners ib ON b.parentId = ib.id
             LEFT JOIN clientUsers cu ON ib.userId = cu.id
             WHERE b.childId = :cid
             LIMIT 1",
            ['cid' => $ibPartnerId]
        );
        $parentIb = null;
        if ($parentRow) {
            $parentIb = [
                'id' => (int) $parentRow['id'],
                'ibCode' => $parentRow['ibCode'] ?? '',
                'ibName' => $parentRow['ibName'] ?? '',
            ];
        }
        $childRows = $this->db->fetchAll(
            "SELECT c.id, c.ibCode,
                COALESCE(TRIM(CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,''))), c.companyName) AS ibName
             FROM ib_partner_bind b
             INNER JOIN ibPartners c ON b.childId = c.id
             LEFT JOIN clientUsers cu ON c.userId = cu.id
             WHERE b.parentId = :pid AND b.childId IS NOT NULL
             ORDER BY c.ibCode ASC, c.id ASC",
            ['pid' => $ibPartnerId]
        );
        $childIbs = [];
        foreach ($childRows as $r) {
            $childIbs[] = [
                'id' => (int) $r['id'],
                'ibCode' => $r['ibCode'] ?? '',
                'ibName' => $r['ibName'] ?? '',
            ];
        }
        return ['parentIb' => $parentIb, 'childIbs' => $childIbs];
    }

    /**
     * 校验解绑：仅当每个待解绑的子 IB 名下不再挂有「子 IB」时才允许解绑（普通客户绑定不计入）
     * @param int $parentId 当前 IB id（未用于校验，仅接口一致）
     * @param int[] $childIds 要解绑的子级 id 列表
     * @return array [ true ] 或 [ false, 'error message' ]
     */
    public function validateUnbindChildren($parentId, array $childIds) {
        $childIds = array_map('intval', array_filter($childIds, function ($v) { return $v > 0; }));
        if (empty($childIds)) {
            return [false, 'No valid children to unbind.'];
        }
        foreach ($childIds as $cid) {
            $row = $this->db->fetchOne(
                'SELECT COUNT(*) AS cnt FROM ib_partner_bind WHERE parentId = :id AND childId IS NOT NULL',
                ['id' => $cid]
            );
            if ($row && (int) $row['cnt'] > 0) {
                return [false, 'Please unbind the next-level user first.'];
            }
        }
        return [true];
    }

    /**
     * 根据 ib_partner_bind 递归同步 ibPartners.totalClients：
     * totalClients(IB) = 直接绑定数（子 IB + 普通客户）+ 各直接子 IB 的 totalClients 之和。
     * 示例：A 绑 B/C，B 绑 D，D 绑客户 E → D=1, B=2, A=4, C=0。
     * 绑定/解绑后应调用本方法一次，会更新所有出现在绑定表中的 IB。
     */
    public function syncAllTotalClientsFromBindTable() {
        $rows = $this->db->fetchAll(
            'SELECT parentId, childId FROM ib_partner_bind',
            []
        );
        $directCount = [];
        $children = [];
        foreach ($rows as $r) {
            $pid = (int) $r['parentId'];
            $cid = isset($r['childId']) ? (int) $r['childId'] : null;
            if ($pid <= 0) {
                continue;
            }
            if (!isset($directCount[$pid])) {
                $directCount[$pid] = 0;
                $children[$pid] = [];
            }
            $directCount[$pid]++;
            if ($cid > 0 && !in_array($cid, $children[$pid], true)) {
                $children[$pid][] = $cid;
            }
        }
        $allParentIds = array_keys($directCount);
        if (empty($allParentIds)) {
            $this->db->query('UPDATE ibPartners SET totalClients = 0');
            return;
        }
        $getDepth = function ($id, &$depthCache) use (&$getDepth, $children) {
            if (isset($depthCache[$id])) {
                return $depthCache[$id];
            }
            $d = 0;
            foreach (isset($children[$id]) ? $children[$id] : [] as $c) {
                $d = max($d, 1 + $getDepth($c, $depthCache));
            }
            $depthCache[$id] = $d;
            return $d;
        };
        $depthCache = [];
        foreach ($allParentIds as $id) {
            $getDepth($id, $depthCache);
        }
        $order = $allParentIds;
        usort($order, function ($a, $b) use ($depthCache) {
            return ($depthCache[$a] ?? 0) - ($depthCache[$b] ?? 0);
        });
        $totalClients = [];
        foreach ($order as $id) {
            $sum = isset($children[$id]) ? array_sum(array_map(function ($c) use ($totalClients) {
                return $totalClients[$c] ?? 0;
            }, $children[$id])) : 0;
            $totalClients[$id] = ($directCount[$id] ?? 0) + $sum;
        }
        foreach ($totalClients as $id => $cnt) {
            $this->db->query(
                'UPDATE ibPartners SET totalClients = :cnt WHERE id = :id',
                ['cnt' => $cnt, 'id' => $id]
            );
        }
        $placeholders = implode(',', array_fill(0, count($allParentIds), '?'));
        $this->db->query(
            "UPDATE ibPartners SET totalClients = 0 WHERE id NOT IN ({$placeholders})",
            $allParentIds
        );
    }

    /**
     * 获取IB统计数据
     */
    public function getIbStatistics() {
        $sql = "SELECT
                    COUNT(*) as totalIbPartners,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as activeIbPartners,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejectedIbPartners,
                    SUM(CASE WHEN status IN ('pending_initial_review', 'pending_risk_review', 'pending_final_review') THEN 1 ELSE 0 END) as pendingIbPartners,
                    SUM(totalClients) as totalClientsReferred,
                    SUM(totalCommissionEarned) as totalCommissionPaid,
                    SUM(totalTradingVolume) as totalTradingVolume
                FROM ibPartners";

        return $this->db->fetchOne($sql);
    }

    /**
     * 分配佣金规则给IB
     */
    public function assignRule($ibPartnerId, $ruleId, $assignedBy) {
        $sql = "INSERT INTO ibPartnerRuleAssignments
                (ibPartnerId, ruleId, assignedBy)
                VALUES (:ibPartnerId, :ruleId, :assignedBy)
                ON DUPLICATE KEY UPDATE assignedAt = CURRENT_TIMESTAMP";

        $stmt = $this->db->query($sql, [
            'ibPartnerId' => $ibPartnerId,
            'ruleId' => $ruleId,
            'assignedBy' => $assignedBy
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * 移除IB的佣金规则
     */
    public function removeRule($ibPartnerId, $ruleId) {
        $sql = "DELETE FROM ibPartnerRuleAssignments
                WHERE ibPartnerId = :ibPartnerId AND ruleId = :ruleId";

        $stmt = $this->db->query($sql, [
            'ibPartnerId' => $ibPartnerId,
            'ruleId' => $ruleId
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * 获取IB网络树（调用存储过程）
     */
    public function getNetworkTree($rootIbPartnerId, $maxDepth = 5) {
        $sql = "CALL sp_getIbNetworkTree(:rootIbPartnerId, :maxDepth)";

        return $this->db->fetchAll($sql, [
            'rootIbPartnerId' => $rootIbPartnerId,
            'maxDepth' => $maxDepth
        ]);
    }

    /**
     * 保存IB规则配置（通过 applicationId 保存到 ibApplicationCustomRules 相关表）
     */
    public function saveRules($ibPartnerId, $rulesData, $adminId) {
        // 直接使用 ibPartners.applicationId
        $ibPartner = $this->findById($ibPartnerId);

        if (!$ibPartner) {
            throw new Exception('IB partner not found');
        }

        $applicationId = $ibPartner['applicationId'] ?? null;

        if (!$applicationId) {
            throw new Exception('No application found for this IB partner (applicationId is null)');
        }

        // 转换前端数据格式为 saveCustomRules 期望的格式
        // 前端格式: { ruleIds: ['1','2'], paymentSettings: {'1': {...}, '2': {...}}, products: {'1': [...], '2': [...]}, additionalRules: {'1': [...], '2': [...]} }
        // 期望格式: [{ ruleId: 1, paymentCycle: '...', products: [...], additionalRules: [...] }, { ruleId: 2, ... }]
        $convertedRulesData = [];
        $ruleIds = $rulesData['ruleIds'] ?? [];
        $paymentSettings = $rulesData['paymentSettings'] ?? [];
        $products = $rulesData['products'] ?? [];
        $additionalRules = $rulesData['additionalRules'] ?? [];

        // 获取规则信息（用于获取 ruleName 和 ruleType）
        require_once __DIR__ . '/IbCommissionRule.php';
        $ruleModel = new IbCommissionRule();

        foreach ($ruleIds as $ruleIdStr) {
            $ruleId = (int)$ruleIdStr;
            $ruleIdKey = $ruleIdStr; // 用于从对象中获取数据

            // 获取规则基本信息
            $ruleInfo = $ruleModel->findById($ruleId);
            if (!$ruleInfo) {
                // 如果找不到规则，跳过
                continue;
            }

            // 获取该规则的支付设置
            $paymentSetting = $paymentSettings[$ruleIdKey] ?? null;

            // 构建规则数据对象
            $ruleData = [
                'ruleId' => $ruleId,
                'ruleName' => $ruleInfo['ruleName'] ?? ('Rule ' . $ruleId),
                'ruleType' => $ruleInfo['ruleType'] ?? 'percentage',
                'paymentCycle' => $paymentSetting['paymentCycle'] ?? $ruleInfo['paymentCycle'] ?? 'monthly',
                'paymentDay' => $paymentSetting['paymentDay'] ?? $ruleInfo['paymentDay'] ?? null,
                'minimumPayout' => $paymentSetting['minimumPayout'] ?? $ruleInfo['minimumPayout'] ?? 100.00,
                'payoutCurrency' => $paymentSetting['payoutCurrency'] ?? $ruleInfo['payoutCurrency'] ?? 'USD',
                'products' => $products[$ruleIdKey] ?? [],
                'additionalRules' => $additionalRules[$ruleIdKey] ?? []
            ];

            $convertedRulesData[] = $ruleData;
        }

        // 使用 IbApplication 模型的 saveCustomRules 方法
        require_once __DIR__ . '/IbApplication.php';
        $ibApplicationModel = new IbApplication();

        $result = $ibApplicationModel->saveCustomRules($applicationId, $convertedRulesData, $adminId);

        // 同步更新 ibPartnerRuleAssignments 表（用于 IB List 表格显示）
        // 删除现有的规则分配
        $this->db->query(
            "DELETE FROM ibPartnerRuleAssignments WHERE ibPartnerId = :ibPartnerId",
            ['ibPartnerId' => $ibPartnerId]
        );

        // 重新创建规则分配（从 ibApplicationRuleAssignments 同步）
        foreach ($ruleIds as $ruleIdStr) {
            $ruleId = (int)$ruleIdStr;
            $this->assignRule($ibPartnerId, $ruleId, $adminId);
        }

        return $result;
    }

    /**
     * 将 ruleIds 写入 ib_partner_rules 表（初审提交等场景）
     */
    public function setPartnerRulesToIbPartnerRules($ibPartnerId, $ruleIds) {
        $this->db->query(
            "DELETE FROM ib_partner_rules WHERE ibPartnerId = :ibPartnerId",
            ['ibPartnerId' => $ibPartnerId]
        );
        foreach ($ruleIds as $ruleIdStr) {
            $ruleId = (int) $ruleIdStr;
            if ($ruleId > 0) {
                $this->db->query(
                    "INSERT INTO ib_partner_rules (ibPartnerId, ruleId) VALUES (:ibPartnerId, :ruleId)",
                    ['ibPartnerId' => $ibPartnerId, 'ruleId' => $ruleId]
                );
            }
        }
    }

    /**
     * 根据客户 userId 获取其直属 IB 的 id（ib_partner_bind.isClient=1）。
     * 无绑定返回 0。
     */
    public function getDirectIbPartnerIdByClientUserId($userId) {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return 0;
        }
        $row = $this->db->fetchOne(
            'SELECT parentId FROM ib_partner_bind WHERE childClientId = :uid AND isClient = 1 LIMIT 1',
            ['uid' => $userId]
        );
        return $row && !empty($row['parentId']) ? (int) $row['parentId'] : 0;
    }

    /**
     * 获取某 IB 名下配置的交易组别 id 列表（ib_partner_trading_groups）。
     */
    public function getTradingGroupIdsByIbPartnerId($ibPartnerId) {
        $ibPartnerId = (int) $ibPartnerId;
        if ($ibPartnerId <= 0) {
            return [];
        }
        $rows = $this->db->fetchAll(
            'SELECT groupId FROM ib_partner_trading_groups WHERE ibPartnerId = :ibPartnerId',
            ['ibPartnerId' => $ibPartnerId]
        );
        $ids = [];
        foreach ($rows as $r) {
            $gid = (int) ($r['groupId'] ?? 0);
            if ($gid > 0) {
                $ids[] = $gid;
            }
        }
        return $ids;
    }

    /**
     * 将交易组别 ID 写入 ib_partner_trading_groups（风险审核多选组别）
     * @param int $ibPartnerId ibPartners.id
     * @param int[] $groupIds trading_group.id 列表
     */
    public function setPartnerTradingGroups($ibPartnerId, array $groupIds) {
        $ibPartnerId = (int) $ibPartnerId;
        $this->db->query(
            'DELETE FROM ib_partner_trading_groups WHERE ibPartnerId = :ibPartnerId',
            ['ibPartnerId' => $ibPartnerId]
        );
        foreach ($groupIds as $gidStr) {
            $gid = (int) $gidStr;
            if ($gid > 0) {
                $this->db->query(
                    'INSERT INTO ib_partner_trading_groups (ibPartnerId, groupId) VALUES (:ibPartnerId, :groupId)',
                    ['ibPartnerId' => $ibPartnerId, 'groupId' => $gid]
                );
            }
        }
    }

    /**
     * 获取用于导出的IB数据
     */
    public function getIbPartnersForExport($selectedIds) {
        if (empty($selectedIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $sql = "SELECT * FROM vw_ibPartnersSummary WHERE id IN ($placeholders)";

        return $this->db->fetchAll($sql, $selectedIds);
    }

    /**
     * 根据客户ID获取IB代理信息
     */
    public function getByClientId($clientId) {
        $sql = "SELECT ib.*
                FROM {$this->table} ib
                WHERE ib.userId = :clientId
                ORDER BY CASE WHEN ib.status = 'approved' THEN 0 ELSE 1 END, ib.id DESC
                LIMIT 1";

        return $this->db->fetchOne($sql, ['clientId' => $clientId]);
    }

    /**
     * 获取同一个 client account 拥有的所有 IB 身份。
     */
    public function getAllByClientId($clientId) {
        $sql = "SELECT
                    ib.id,
                    ib.ibCode,
                    ib.userId,
                    ib.companyName,
                    ib.clientAlias,
                    ib.adminAlias,
                    ib.ibType,
                    ib.tierLevelId,
                    ib.status,
                    ib.registrationDate,
                    COALESCE(TRIM(CONCAT(IFNULL(cu.firstName,''), ' ', IFNULL(cu.lastName,''))), ib.companyName) AS ibName,
                    COALESCE(cu.email, ib.contactEmail) AS email
                FROM {$this->table} ib
                LEFT JOIN clientUsers cu ON ib.userId = cu.id
                WHERE ib.userId = :clientId
                ORDER BY CASE WHEN ib.status = 'approved' THEN 0 ELSE 1 END, ib.id DESC";

        return $this->db->fetchAll($sql, ['clientId' => (int)$clientId]);
    }

    /**
     * 查「IB」系统标签的 leadTags.id；不存在返回 null（只查，不创建）。
     */
    public function getIbTagId(): ?int
    {
        require_once __DIR__ . '/LeadTag.php';
        $existing = (new LeadTag())->findByName(self::IB_TAG_NAME);
        return ($existing && !empty($existing['id'])) ? (int)$existing['id'] : null;
    }

    /**
     * 确保「IB」系统标签存在并返回 id；没有则创建。
     * 仅供初始化（存量回填脚本）使用——日常创建 IB 不走这里，避免标签被删后又被自动重建。
     */
    public function ensureIbTag(): int
    {
        $tagId = $this->getIbTagId();
        if ($tagId) {
            return $tagId;
        }
        require_once __DIR__ . '/LeadTag.php';
        return (int)(new LeadTag())->create([
            'tagName' => self::IB_TAG_NAME,
            'tagColor' => '#2563eb',
            'description' => 'IB partner',
            'isSystemTag' => 1,
        ]);
    }

    /**
     * 给 client 挂上「IB」标签（幂等）。每个 IB 创建入口调用，clients list 据此区分/筛选 IB。
     * 标签不存在时直接跳过、不创建——标签被删即视为「不再需要该功能」，不自动重建。
     * 失败只记日志，不影响 IB 创建主流程。
     */
    public function tagClientAsIb($clientId, $assignedBy = null): void
    {
        $clientId = (int)$clientId;
        if ($clientId <= 0) {
            return;
        }
        try {
            $tagId = $this->getIbTagId();
            if (!$tagId) {
                return;
            }
            require_once __DIR__ . '/LeadTagAssignment.php';
            (new LeadTagAssignment())->assignTag($clientId, $tagId, $assignedBy);
        } catch (Exception $e) {
            error_log('tagClientAsIb failed for client ' . $clientId . ': ' . $e->getMessage());
        }
    }

    /**
     * 预留：将来有「删除/停用 IB」功能时调用——若该 client 在 ibPartners 已无有效记录则移除 IB 标签。
     * 当前 IB 无失效场景（无硬删除、reject 只是把审核打回 pending），故暂不接入任何入口。
     */
    public function untagClientIfNotIb($clientId): void
    {
        // 触发场景出现后再实现：确认 ibPartners 无该 client 的有效记录后，
        // (new LeadTagAssignment())->removeTag((int)$clientId, $this->ensureIbTag());
    }

    /**
     * 通过 ib_partner_rules 获取当前 IB 关联的佣金规则（仅 status=active）
     * 用于客户端 Dashboard Your Commission Rates 规则列表
     *
     * @param int $ibPartnerId ibPartners.id
     * @return array 每项含 ruleName, product, targetRegion, payoutCurrency, rate, fixed_amount, ruleType 等
     */
    public function getCommissionRulesByPartnerRules($ibPartnerId) {
        $ibPartnerId = (int) $ibPartnerId;
        if ($ibPartnerId <= 0) {
            return [];
        }
        $sql = "SELECT
                    cr.id,
                    cr.ruleName,
                    cr.ruleType,
                    cr.targetRegion,
                    cr.payoutCurrency,
                    cr.rate,
                    cr.fixed_amount,
                    cr.minimum_trade,
                    cr.maximum_trade,
                    sym.symbolName AS symbolName,
                    sec.securityName AS securityName
                FROM ib_partner_rules pr
                INNER JOIN ibCommissionRules cr ON cr.id = pr.ruleId AND cr.status = 'active'
                LEFT JOIN ibCustomSymbols sym ON cr.product_type = 'symbols' AND cr.product = sym.id
                LEFT JOIN ibCustomSecurities sec ON cr.product_type = 'securities' AND cr.product = sec.id
                WHERE pr.ibPartnerId = :ibPartnerId
                ORDER BY cr.ruleName ASC, cr.id ASC";
        $rows = $this->db->fetchAll($sql, ['ibPartnerId' => $ibPartnerId]);
        if (empty($rows)) {
            return [];
        }
        $rules = [];
        foreach ($rows as $r) {
            $productName = '—';
            if (!empty($r['symbolName'])) {
                $productName = (!empty($r['securityName']) ? $r['securityName'] . ' - ' : '') . $r['symbolName'];
            } elseif (!empty($r['securityName'])) {
                $productName = $r['securityName'];
            }
            $rules[] = [
                'id' => $r['id'],
                'ruleName' => $r['ruleName'] ?? '—',
                'ruleType' => $r['ruleType'] ?? '',
                'targetRegion' => $r['targetRegion'] ?? '—',
                'payoutCurrency' => $r['payoutCurrency'] ?? 'USD',
                'rate' => $r['rate'],
                'fixed_amount' => $r['fixed_amount'],
                'productName' => $productName,
                'product' => $productName
            ];
        }
        return $rules;
    }

    /**
     * 获取IB代理的佣金规则（基于 applicationId + ibApplicationCustomRules，兼容旧逻辑）
     */
    public function getCommissionRules($ibPartnerId) {
        $ibPartner = $this->findById($ibPartnerId);
        if (!$ibPartner || !isset($ibPartner['applicationId'])) {
            return [];
        }

        $applicationId = $ibPartner['applicationId'];

        // 获取自定义规则
        $sql = "SELECT * FROM ibApplicationCustomRules
                WHERE applicationId = :applicationId
                AND status = 'active'
                ORDER BY id ASC";
        $customRules = $this->db->fetchAll($sql, ['applicationId' => $applicationId]);

        $rules = [];
        foreach ($customRules as $rule) {
            $customRuleId = $rule['id'];

            // 获取产品配置
            $productsSql = "SELECT
                            p.*,
                            s.securityName,
                            sym.symbolName
                            FROM ibApplicationCustomRuleProducts p
                            LEFT JOIN ibCustomSymbols sym ON p.productName = sym.symbolName AND p.productType = 'symbol'
                            LEFT JOIN ibCustomSecurities s ON (sym.securityId = s.id OR (p.productType = 'security' AND p.productName = s.securityName))
                            WHERE p.customRuleId = :customRuleId
                            ORDER BY p.id ASC";
            $products = $this->db->fetchAll($productsSql, ['customRuleId' => $customRuleId]);

            // 格式化产品数据
            foreach ($products as &$product) {
                // 确定产品名称显示
                if ($product['productType'] === 'symbol') {
                    if (!empty($product['symbolName'])) {
                        $product['displayName'] = ($product['securityName'] ?? '') . ($product['securityName'] ? ' - ' : '') . $product['symbolName'];
                        $product['securityName'] = $product['securityName'] ?? '';
                    } else {
                        $product['displayName'] = $product['productName'];
                        $product['securityName'] = '';
                    }
                } else {
                    // security类型
                    $product['displayName'] = $product['productName'];
                    $product['securityName'] = $product['securityName'] ?? $product['productName'];
                }
            }

            $rule['products'] = $products;
            $rules[] = $rule;
        }

        return $rules;
    }

    /**
     * 从若干入口 IB 向下收集 ib_partner_bind 子树内所有 IB id（用于销售仅看直接绑定 IB 及其下级展示）
     */
    private function buildIbSubtreeIdSet(array $rootIds, array $childrenMap, $approvedIds, $statusFilter) {
        $set = [];
        $walk = function ($nodeId) use (&$walk, &$set, $childrenMap, $approvedIds, $statusFilter) {
            $nodeId = (int) $nodeId;
            if ($nodeId <= 0 || isset($set[$nodeId])) {
                return;
            }
            if ($statusFilter === 'approved' && is_array($approvedIds) && !isset($approvedIds[$nodeId])) {
                return;
            }
            $set[$nodeId] = true;
            foreach ($childrenMap[$nodeId] ?? [] as $childId) {
                $walk($childId);
            }
        };
        foreach ($rootIds as $rootId) {
            $walk($rootId);
        }
        return $set;
    }
}
