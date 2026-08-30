<?php
/**
 * 客户用户模型
 */

require_once __DIR__ . '/BaseModel.php';

class ClientUser extends BaseModel {
    protected $table = 'clientUsers';
    protected $primaryKey = 'id';

    protected $fillable = [
        'email', 'passwordHash', 'firstName', 'lastName',
        'phone', 'phoneCountryCode', 'country', 'emailVerified', 'emailVerifiedAt',
        'status', 'registrationIp', 'lastLoginAt', 'lastLoginIp',
        'kycStatus', 'kycSubmissionId', 'verifiedAt', 'tags',
        'assignedTo', 'notes', 'accountManagerId', 'accountManagerAssignedAt', 'accountManagerNote',
        'mallPointsBalance', 'deletedAt'
    ];

    protected $hidden = ['passwordHash'];

    /**
     * 根据邮箱查找
     * 注意：用于登录验证时需要包含 passwordHash
     */
    public function findByEmail($email, $includePassword = false) {
        // 暂不放开注销邮箱重新注册：判重含已注销账号。交易数据删除流程完成后，
        // 加回 "AND deletedAt IS NULL" 即可让原邮箱可被重新注册。
        $sql = "SELECT * FROM {$this->table} WHERE email = :email LIMIT 1";
        $result = $this->db->fetchOne($sql, ['email' => $email]);

        // 如果不需要密码，则隐藏敏感字段
        if (!$includePassword && $result) {
            return $this->hideFields($result);
        }

        return $result;
    }

    /**
     * 根据手机号查找客户，用于注册 / 新建 / 修改时校验手机号是否重复。
     * 修改场景传入 $excludeId 排除客户自己；未命中返回 falsy。
     */
    public function findByPhone($phone, $excludeId = null) {
        // 暂不放开注销电话重新注册：判重含已注销账号。交易数据删除流程完成后，
        // 加回 "AND deletedAt IS NULL" 即可让原电话可被重新注册。
        $sql = "SELECT * FROM {$this->table} WHERE phone = :phone";
        $params = ['phone' => $phone];

        if ($excludeId !== null) {
            $sql .= " AND {$this->primaryKey} != :excludeId";
            $params['excludeId'] = $excludeId;
        }

        $sql .= " LIMIT 1";
        $result = $this->db->fetchOne($sql, $params);

        return $result ? $this->hideFields($result) : $result;
    }

    /**
     * 根据ID查找（包含密码哈希）
     */
    public function findByIdWithPassword($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * 验证密码
     */
    public function verifyPassword($inputPassword, $hashedPassword) {
        return password_verify($inputPassword, $hashedPassword);
    }

    /**
     * 哈希密码
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    /**
     * 更新最后登录时间
     */
    public function updateLastLogin($userId, $ipAddress) {
        return $this->db->update(
            $this->table,
            [
                'lastLoginAt' => date('Y-m-d H:i:s'),
                'lastLoginIp' => $ipAddress
            ],
            'id = :id',
            ['id' => $userId]
        );
    }

    /**
     * 验证邮箱
     */
    public function verifyEmail($userId) {
        return $this->update($userId, [
            'emailVerified' => 1,
            'emailVerifiedAt' => date('Y-m-d H:i:s'),
            'status' => 'active'
        ]);
    }

    /**
     * 检查邮箱是否已验证
     */
    public function isEmailVerified($userId) {
        $user = $this->findById($userId);
        return $user && $user['emailVerified'] == 1;
    }

    /**
     * 获取活跃用户
     */
    public function getActiveUsers() {
        return $this->findAll([
            'status' => 'active',
            'emailVerified' => 1
        ], 'createdAt DESC');
    }

    /**
     * 搜索客户
     */
    public function search($keyword, $page = 1, $perPage = 10) {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM {$this->table}
                WHERE (email LIKE :keyword
                   OR firstName LIKE :keyword
                   OR lastName LIKE :keyword
                   OR phone LIKE :keyword)
                ORDER BY createdAt DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as count FROM {$this->table}
                     WHERE (email LIKE :keyword
                        OR firstName LIKE :keyword
                        OR lastName LIKE :keyword
                        OR phone LIKE :keyword)";

        $params = ['keyword' => "%{$keyword}%"];

        $items = $this->db->fetchAll($sql, $params);
        $total = $this->db->fetchOne($countSql, $params)['count'];

        return [
            'items' => $this->hideFields($items),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * 按状态统计用户
     */
    public function getStatsByStatus() {
        $sql = "SELECT status, COUNT(*) as count
                FROM {$this->table}
                GROUP BY status";
        return $this->db->fetchAll($sql);
    }

    /**
     * 按KYC状态统计用户
     */
    public function getStatsByKycStatus() {
        $sql = "SELECT kycStatus, COUNT(*) as count
                FROM {$this->table}
                GROUP BY kycStatus";
        return $this->db->fetchAll($sql);
    }

    /**
     * 获取已验证的客户列表（用于Client List）
     */
    public function getVerifiedClients($params = []) {
        $sql = "SELECT c.*, s.submittedAt, s.reviewedAt, s.reviewedBy
                FROM {$this->table} c
                LEFT JOIN clientKycSubmissions s ON c.kycSubmissionId = s.id
                WHERE c.kycStatus = 'approved'";

        $sqlParams = [];

        // 添加搜索条件
        if (!empty($params['search'])) {
            $sql .= " AND (c.firstName LIKE :search OR c.lastName LIKE :search OR c.email LIKE :search)";
            $sqlParams['search'] = '%' . $params['search'] . '%';
        }

        // 添加标签筛选
        if (!empty($params['tags'])) {
            $sql .= " AND c.tags IS NOT NULL";
        }

        // 添加分配筛选
        if (!empty($params['assignedTo'])) {
            $sql .= " AND c.assignedTo = :assignedTo";
            $sqlParams['assignedTo'] = $params['assignedTo'];
        }

        // 排序
        $orderBy = $params['orderBy'] ?? 'c.verifiedAt';
        $orderDir = $params['orderDir'] ?? 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        // 分页
        if (isset($params['limit'])) {
            $offset = $params['offset'] ?? 0;
            $sql .= " LIMIT {$offset}, {$params['limit']}";
        }

        return $this->db->fetchAll($sql, $sqlParams);
    }

    /**
     * 后台 Client List 统一数据源：与 /clients-list 页面一致的用户列表，支持搜索、KYC 筛选、排序、分页
     * 可选排除「已接受 IB 邀请」的用户（在 ibPartners 中已有记录），供 IB Invitation 的 Select Client 使用
     * 保证未来加载「已通过 KYC 认证」等场景时复用此方法，避免数据不一致
     *
     * @param array $options [
     *   'page' => int,
     *   'per_page' => int,
     *   'search' => string|null,
     *   'kyc_status' => string|null,
     *   'sort_field' => string,
     *   'sort_direction' => string 'asc'|'desc',
     *   'excludeIbAccepted' => bool  true 时排除已在 ibPartners 中的用户（已点击同意成为 IB）
     *   'restrict_to_sales_id' => int|null  仅销售时传入，只返回与该 Sales 绑定的客户（sales_bind）
     * ]
     * @return array ['items' => array, 'total' => int]
     */
    public function getClientListForAdmin(array $options = []) {
        $page = isset($options['page']) ? (int) $options['page'] : 1;
        $perPage = isset($options['per_page']) ? (int) $options['per_page'] : 25;
        $search = isset($options['search']) ? trim((string) $options['search']) : null;
        if ($search === '') {
            $search = null;
        }
        $kycStatus = isset($options['kyc_status']) ? $options['kyc_status'] : null;
        $sortField = isset($options['sort_field']) ? $options['sort_field'] : 'createdAt';
        $sortDirection = (isset($options['sort_direction']) && strtolower($options['sort_direction']) === 'asc') ? 'ASC' : 'DESC';
        $excludeIbAccepted = !empty($options['excludeIbAccepted']);
        $restrictToSalesId = isset($options['restrict_to_sales_id']) ? (int)$options['restrict_to_sales_id'] : null;
        if ($restrictToSalesId <= 0) {
            $restrictToSalesId = null;
        }

        $allowedSortFields = ['id', 'firstName', 'lastName', 'email', 'createdAt', 'lastLoginAt', 'kycStatus'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'createdAt';
        }

        $params = [];
        $baseSelect = "cu.*,
                    cu.accountManagerAssignedAt,
                    au.fullName AS managerName,
                    au.email AS managerEmail,
                    (SELECT COUNT(*) FROM leadTagAssignments WHERE leadId = cu.id) as tagCount,
                    (SELECT COUNT(*) FROM clientKycSubmissions WHERE clientId = cu.id) as kycSubmissionsCount,
                    (SELECT submissionStatus FROM clientKycSubmissions WHERE clientId = cu.id ORDER BY createdAt DESC LIMIT 1) as latestKycStatus,
                    (SELECT templateName FROM kycTemplates kt
                     INNER JOIN clientKycSubmissions cks ON kt.id = cks.templateId
                     WHERE cks.clientId = cu.id ORDER BY cks.createdAt DESC LIMIT 1) as kycTemplateName,
                    (SELECT verifiedAt FROM clientKycSubmissions WHERE clientId = cu.id AND submissionStatus = 'approved' ORDER BY updatedAt DESC LIMIT 1) as kycVerifiedAt,
                    (SELECT COUNT(*) FROM clientActivityLog WHERE userId = cu.id AND activityType = 'login') as loginCount";

        $excludeIbCondition = $excludeIbAccepted
            ? " AND cu.id NOT IN (SELECT userId FROM ibPartners WHERE userId IS NOT NULL) "
            : '';
        $salesRestrictCondition = $restrictToSalesId !== null
            ? " AND cu.id IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id) "
            : '';
        if ($restrictToSalesId !== null) {
            $params['restrict_to_sales_id'] = $restrictToSalesId;
        }

        if ($search !== null) {
            $searchPattern = '%' . $search . '%';
            $params['s1'] = $searchPattern;
            $params['s2'] = $searchPattern;
            $params['s3'] = $searchPattern;
            $params['s4'] = $searchPattern;
            $params['s5'] = $searchPattern;
            $params['s6'] = $searchPattern;
            $params['s7'] = $searchPattern;
            $params['s8'] = $searchPattern;
            // 交易系统外部id（providerAccountId）也并入统一搜索，需 join 交易账户及其外部账户表
            // s8 用「名 姓」拼接整体匹配，解决直接搜全名（firstName + lastName）匹配不到的问题
            // 纯数字时按客户 id 精确匹配（列表展示的就是 cu.id），非数字不并入避免被隐式转成 0 误命中
            $idClause = '';
            if (ctype_digit($search)) {
                $idClause = ' OR cu.id = :sId';
                $params['sId'] = (int) $search;
            }
            $sql = "SELECT DISTINCT {$baseSelect}
                FROM clientUsers cu
                LEFT JOIN adminUsers au ON au.id = cu.accountManagerId
                LEFT JOIN leadTagAssignments lta ON cu.id = lta.leadId
                LEFT JOIN leadTags lt ON lta.tagId = lt.id
                LEFT JOIN tradingAccounts ta ON ta.userId = cu.id
                LEFT JOIN tradingAccountExternalAccounts taea ON taea.tradingAccountId = ta.id
                WHERE 1=1
                  AND (cu.firstName LIKE :s1 OR cu.lastName LIKE :s2 OR cu.email LIKE :s3
                       OR cu.phone LIKE :s4 OR cu.country LIKE :s5 OR lt.tagName LIKE :s6
                       OR taea.providerAccountId LIKE :s7
                       OR CONCAT_WS(' ', cu.firstName, cu.lastName) LIKE :s8{$idClause})
                  {$excludeIbCondition}{$salesRestrictCondition}";
            $countSql = "SELECT COUNT(DISTINCT cu.id) as total
                FROM clientUsers cu
                LEFT JOIN leadTagAssignments lta ON cu.id = lta.leadId
                LEFT JOIN leadTags lt ON lta.tagId = lt.id
                LEFT JOIN tradingAccounts ta ON ta.userId = cu.id
                LEFT JOIN tradingAccountExternalAccounts taea ON taea.tradingAccountId = ta.id
                WHERE 1=1
                  AND (cu.firstName LIKE :s1 OR cu.lastName LIKE :s2 OR cu.email LIKE :s3
                       OR cu.phone LIKE :s4 OR cu.country LIKE :s5 OR lt.tagName LIKE :s6
                       OR taea.providerAccountId LIKE :s7
                       OR CONCAT_WS(' ', cu.firstName, cu.lastName) LIKE :s8{$idClause})
                  {$excludeIbCondition}{$salesRestrictCondition}";
        } else {
            $sql = "SELECT {$baseSelect}
                FROM clientUsers cu
                LEFT JOIN adminUsers au ON au.id = cu.accountManagerId
                WHERE 1=1 {$excludeIbCondition}{$salesRestrictCondition}";
            $countSql = "SELECT COUNT(*) as total FROM clientUsers cu WHERE 1=1 {$excludeIbCondition}{$salesRestrictCondition}";
        }

        if ($kycStatus !== null && $kycStatus !== '') {
            $sql .= " AND cu.kycStatus = :kycStatus";
            $countSql .= " AND cu.kycStatus = :kycStatus";
            $params['kycStatus'] = $kycStatus;
        }

        $totalRow = $this->db->fetchOne($countSql, $params);
        $total = (int) ($totalRow['total'] ?? 0);

        $sql .= " ORDER BY cu.{$sortField} {$sortDirection}";
        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT " . (int) $offset . ", " . (int) $perPage;

        $items = $this->db->fetchAll($sql, $params);
        return ['items' => $items, 'total' => $total];
    }

    /**
     * 后台客户详情基础主体。
     * 当前字段保持和 getClientListForAdmin 的 item 一致，后续详情页专属字段可以只扩展这里。
     */
    public function getAdminClientDetailById(int $clientId, array $options = []) {
        if ($clientId <= 0) {
            return null;
        }

        $restrictToSalesId = isset($options['restrict_to_sales_id']) ? (int)$options['restrict_to_sales_id'] : null;
        if ($restrictToSalesId <= 0) {
            $restrictToSalesId = null;
        }

        $params = ['client_id' => $clientId];
        $salesRestrictCondition = '';
        if ($restrictToSalesId !== null) {
            $salesRestrictCondition = " AND cu.id IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id) ";
            $params['restrict_to_sales_id'] = $restrictToSalesId;
        }

        $baseSelect = "cu.*,
                    cu.accountManagerAssignedAt,
                    au.fullName AS managerName,
                    au.email AS managerEmail,
                    (SELECT COUNT(*) FROM leadTagAssignments WHERE leadId = cu.id) as tagCount,
                    (SELECT COUNT(*) FROM clientKycSubmissions WHERE clientId = cu.id) as kycSubmissionsCount,
                    (SELECT submissionStatus FROM clientKycSubmissions WHERE clientId = cu.id ORDER BY createdAt DESC LIMIT 1) as latestKycStatus,
                    (SELECT id FROM clientKycSubmissions WHERE clientId = cu.id ORDER BY createdAt DESC LIMIT 1) as latestKycSubmissionId,
                    (SELECT templateName FROM kycTemplates kt
                     INNER JOIN clientKycSubmissions cks ON kt.id = cks.templateId
                     WHERE cks.clientId = cu.id ORDER BY cks.createdAt DESC LIMIT 1) as kycTemplateName,
                    (SELECT verifiedAt FROM clientKycSubmissions WHERE clientId = cu.id AND submissionStatus = 'approved' ORDER BY updatedAt DESC LIMIT 1) as kycVerifiedAt,
                    (SELECT COUNT(*) FROM clientActivityLog WHERE userId = cu.id AND activityType = 'login') as loginCount";

        $sql = "SELECT {$baseSelect}
                FROM clientUsers cu
                LEFT JOIN adminUsers au ON au.id = cu.accountManagerId
                WHERE cu.id = :client_id {$salesRestrictCondition}
                LIMIT 1";

        $client = $this->db->fetchOne($sql, $params);
        if (!$client) {
            return null;
        }

        // clientUsers.kycSubmissionId 只在通过后同步；未通过/审核中的详情要回落到最新 submission。
        if (empty($client['kycSubmissionId']) && !empty($client['latestKycSubmissionId'])) {
            $client['kycSubmissionId'] = $client['latestKycSubmissionId'];
        }
        unset($client['latestKycSubmissionId']);

        return $client;
    }

    /**
     * 后台客户是否存在（不校验销售绑定范围）。
     */
    public function existsAdminClient(int $clientId): bool {
        if ($clientId <= 0) {
            return false;
        }
        $row = $this->db->fetchOne(
            'SELECT id FROM clientUsers WHERE id = :client_id LIMIT 1',
            ['client_id' => $clientId]
        );
        return !empty($row);
    }

    /**
     * 积分变动记录「奖励对象」下拉专用：筛选/分页与 getClientListForAdmin 一致，并额外支持 phoneCountryCode 模糊搜索。
     * 不修改 getClientListForAdmin，避免影响 Client List。
     *
     * @param array $options 同 getClientListForAdmin（page, per_page, search, kyc_status, sort_field, sort_direction, excludeIbAccepted, restrict_to_sales_id）
     * @return array{items: array, total: int}
     */
    public function getClientListForPointsLedgerPicker(array $options = []) {
        $page = isset($options['page']) ? (int) $options['page'] : 1;
        $perPage = isset($options['per_page']) ? (int) $options['per_page'] : 25;
        $search = isset($options['search']) ? trim((string) $options['search']) : null;
        if ($search === '') {
            $search = null;
        }
        $kycStatus = isset($options['kyc_status']) ? $options['kyc_status'] : null;
        $sortField = isset($options['sort_field']) ? $options['sort_field'] : 'createdAt';
        $sortDirection = (isset($options['sort_direction']) && strtolower($options['sort_direction']) === 'asc') ? 'ASC' : 'DESC';
        $excludeIbAccepted = !empty($options['excludeIbAccepted']);
        $restrictToSalesId = isset($options['restrict_to_sales_id']) ? (int) $options['restrict_to_sales_id'] : null;
        if ($restrictToSalesId <= 0) {
            $restrictToSalesId = null;
        }

        $allowedSortFields = ['id', 'firstName', 'lastName', 'email', 'createdAt', 'lastLoginAt', 'kycStatus'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'createdAt';
        }

        $params = [];
        $baseSelect = "cu.id, cu.email, cu.firstName, cu.lastName, cu.phone, cu.phoneCountryCode, cu.status, cu.kycStatus, cu.createdAt";

        $excludeIbCondition = $excludeIbAccepted
            ? " AND cu.id NOT IN (SELECT userId FROM ibPartners WHERE userId IS NOT NULL) "
            : '';
        $salesRestrictCondition = $restrictToSalesId !== null
            ? " AND cu.id IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id) "
            : '';
        if ($restrictToSalesId !== null) {
            $params['restrict_to_sales_id'] = $restrictToSalesId;
        }

        if ($search !== null) {
            $searchPattern = '%' . $search . '%';
            $params['s1'] = $searchPattern;
            $params['s2'] = $searchPattern;
            $params['s3'] = $searchPattern;
            $params['s4'] = $searchPattern;
            $params['s5'] = $searchPattern;
            $params['s6'] = $searchPattern;
            $params['s7'] = $searchPattern;
            $sql = "SELECT DISTINCT {$baseSelect}
                FROM clientUsers cu
                LEFT JOIN leadTagAssignments lta ON cu.id = lta.leadId
                LEFT JOIN leadTags lt ON lta.tagId = lt.id
                WHERE 1=1
                  AND (cu.firstName LIKE :s1 OR cu.lastName LIKE :s2 OR cu.email LIKE :s3
                       OR cu.phone LIKE :s4 OR cu.country LIKE :s5 OR lt.tagName LIKE :s6
                       OR cu.phoneCountryCode LIKE :s7)
                  {$excludeIbCondition}{$salesRestrictCondition}";
            $countSql = "SELECT COUNT(DISTINCT cu.id) as total
                FROM clientUsers cu
                LEFT JOIN leadTagAssignments lta ON cu.id = lta.leadId
                LEFT JOIN leadTags lt ON lta.tagId = lt.id
                WHERE 1=1
                  AND (cu.firstName LIKE :s1 OR cu.lastName LIKE :s2 OR cu.email LIKE :s3
                       OR cu.phone LIKE :s4 OR cu.country LIKE :s5 OR lt.tagName LIKE :s6
                       OR cu.phoneCountryCode LIKE :s7)
                  {$excludeIbCondition}{$salesRestrictCondition}";
        } else {
            $sql = "SELECT {$baseSelect}
                FROM clientUsers cu
                WHERE 1=1 {$excludeIbCondition}{$salesRestrictCondition}";
            $countSql = "SELECT COUNT(*) as total FROM clientUsers cu WHERE 1=1 {$excludeIbCondition}{$salesRestrictCondition}";
        }

        if ($kycStatus !== null && $kycStatus !== '') {
            $sql .= " AND cu.kycStatus = :kycStatus";
            $countSql .= " AND cu.kycStatus = :kycStatus";
            $params['kycStatus'] = $kycStatus;
        }

        $totalRow = $this->db->fetchOne($countSql, $params);
        $total = (int) ($totalRow['total'] ?? 0);

        $sql .= " ORDER BY cu.{$sortField} {$sortDirection}";
        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT " . (int) $offset . ", " . (int) $perPage;

        $items = $this->db->fetchAll($sql, $params);
        return ['items' => $items, 'total' => $total];
    }

    /**
     * 获取客户统计信息
     */
    public function getClientStatistics() {
        $sql = "SELECT
                    COUNT(*) as total,
                    COUNT(CASE WHEN kycStatus = 'approved' THEN 1 END) as verified,
                    COUNT(CASE WHEN kycStatus = 'submitted' THEN 1 END) as pending,
                    COUNT(CASE WHEN kycStatus = 'rejected' THEN 1 END) as rejected,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active
                FROM {$this->table}";

        return $this->db->fetchOne($sql);
    }

    /**
     * 获取客户的 KYC 状态消息（带映射）
     * 使用 KycStatusMapper 将 kycStatus 映射到对应的消息模板
     *
     * @param int $userId - 客户ID
     * @return array|null - 包含状态和消息模板的数组
     */
    public function getKycStatusWithMessage($userId) {
        require_once __DIR__ . '/../utils/KycStatusMapper.php';
        require_once __DIR__ . '/KycStatusMessageTemplate.php';

        $user = $this->findById($userId);
        if (!$user) {
            return null;
        }

        $clientStatus = $user['kycStatus'] ?? 'not_started';

        // 映射到模板状态类型
        $templateStatus = KycStatusMapper::clientStatusToTemplateStatus($clientStatus);

        // 获取对应的消息模板
        $templateModel = new KycStatusMessageTemplate();
        $messageTemplate = $templateModel->getStatusConfig($templateStatus);

        return [
            'userId' => $userId,
            'clientStatus' => $clientStatus,
            'templateStatus' => $templateStatus,
            'statusDisplayName' => KycStatusMapper::getStatusDisplayName($clientStatus),
            'statusColorClass' => KycStatusMapper::getStatusColorClass($clientStatus),
            'messageTemplate' => $messageTemplate
        ];
    }

    /**
     * 批量获取多个客户的 KYC 状态消息
     *
     * @param array $userIds - 客户ID数组
     * @return array - 包含每个客户状态信息的数组
     */
    public function batchGetKycStatusWithMessage($userIds) {
        if (empty($userIds)) {
            return [];
        }

        require_once __DIR__ . '/../utils/KycStatusMapper.php';
        require_once __DIR__ . '/KycStatusMessageTemplate.php';

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $sql = "SELECT id, kycStatus FROM {$this->table} WHERE id IN ({$placeholders})";

        $users = $this->db->fetchAll($sql, $userIds);

        $templateModel = new KycStatusMessageTemplate();
        $results = [];

        foreach ($users as $user) {
            $clientStatus = $user['kycStatus'] ?? 'not_started';
            $templateStatus = KycStatusMapper::clientStatusToTemplateStatus($clientStatus);
            $messageTemplate = $templateModel->getStatusConfig($templateStatus);

            $results[] = [
                'userId' => $user['id'],
                'clientStatus' => $clientStatus,
                'templateStatus' => $templateStatus,
                'statusDisplayName' => KycStatusMapper::getStatusDisplayName($clientStatus),
                'statusColorClass' => KycStatusMapper::getStatusColorClass($clientStatus),
                'messageTemplate' => $messageTemplate
            ];
        }

        return $results;
    }
}
