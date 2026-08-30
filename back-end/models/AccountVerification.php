<?php
/**
 * Account Verification Model
 * 账户验证模型 - 用于存款/提款账户验证
 */

require_once __DIR__ . '/../utils/Database.php';

class AccountVerification {
    private $db;
    private $table = 'accountVerifications';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 获取客户的已验证账户列表
     * @param int $userId 客户ID
     * @param int $paymentMethodId 支付方式ID (可选)
     * @return array
     */
    public function getVerifiedAccounts($userId, $paymentMethodId = null) {
        $sql = "SELECT
                    av.id,
                    av.userId,
                    av.paymentMethodId,
                    av.accountType,
                    av.accountName,
                    av.verificationStatus,
                    av.submittedAt,
                    av.reviewedAt,
                    av.createdAt,
                    pm.methodName as paymentMethodName,
                    pm.methodType as paymentMethodType,
                    pm.iconClass as paymentMethodIcon,
                    pm.shortCode,
                    -- 银行信息（脱敏）
                    CASE
                        WHEN av.accountType = 'bank' THEN av.bankName
                        ELSE NULL
                    END as bankName,
                    -- 账户标识符（脱敏的账号或完整钱包地址）
                    CASE
                        WHEN av.accountType = 'bank' THEN CONCAT('****', RIGHT(av.accountNumber, 4))
                        WHEN av.accountType = 'crypto' THEN av.walletAddress
                        ELSE NULL
                    END as accountIdentifier,
                    av.walletNetwork
                FROM {$this->table} av
                LEFT JOIN paymentMethods pm ON av.paymentMethodId = pm.id
                WHERE av.userId = :userId
                AND av.verificationStatus = 'approved'";

        if ($paymentMethodId) {
            $sql .= " AND av.paymentMethodId = :paymentMethodId";
        }

        $sql .= " ORDER BY av.createdAt DESC";

        $params = ['userId' => $userId];
        if ($paymentMethodId) {
            $params['paymentMethodId'] = $paymentMethodId;
        }

        // 使用 Database 类的方法，自动规范化数据类型
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * 获取验证申请列表（管理员）
     * @param int $page 页码
     * @param int $perPage 每页数量
     * @param array $filters 筛选条件
     * @return array
     */
    public function getVerifications($page = 1, $perPage = 20, $filters = []) {
        $offset = ($page - 1) * $perPage;

        // 构建WHERE条件
        $whereConditions = [];
        $params = [];

        if (!empty($filters['status'])) {
            $whereConditions[] = "av.verificationStatus = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['accountType'])) {
            $whereConditions[] = "av.accountType = :accountType";
            $params['accountType'] = $filters['accountType'];
        }

        if (!empty($filters['paymentMethodId'])) {
            $whereConditions[] = "av.paymentMethodId = :paymentMethodId";
            $params['paymentMethodId'] = $filters['paymentMethodId'];
        }

        if (!empty($filters['userId'])) {
            $whereConditions[] = "av.userId = :userId";
            $params['userId'] = $filters['userId'];
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // 确保分页参数是正整数
        $perPage = max(1, (int)$perPage);
        $offset = max(0, (int)$offset);

        // 获取总数
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} av {$whereClause}";
        $countResult = $this->db->fetchOne($countSql, $params);
        $total = $countResult['total'] ?? 0;

        // 获取数据
        $sql = "SELECT
                    av.*,
                    cu.firstName as clientFirstName,
                    cu.lastName as clientLastName,
                    cu.email as clientEmail,
                    pm.methodName as paymentMethodName,
                    pm.methodType as paymentMethodType,
                    pm.iconClass as paymentMethodIcon
                FROM {$this->table} av
                LEFT JOIN clientUsers cu ON av.userId = cu.id
                LEFT JOIN paymentMethods pm ON av.paymentMethodId = pm.id
                {$whereClause}
                ORDER BY av.submittedAt DESC
                LIMIT {$perPage} OFFSET {$offset}";

        // 使用 Database 类的方法，自动规范化数据类型
        $items = $this->db->fetchAll($sql, $params);

        return [
            'items' => $items,
            'total' => (int)$total,
            'page' => (int)$page,
            'per_page' => (int)$perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * 获取验证详情
     * @param int $id 验证ID
     * @return array|null
     */
    public function getVerificationDetails($id) {
        $sql = "SELECT
                    av.*,
                    cu.firstName as clientFirstName,
                    cu.lastName as clientLastName,
                    cu.email as clientEmail,
                    cu.phone as clientPhone,
                    pm.methodName as paymentMethodName,
                    pm.methodType as paymentMethodType,
                    pm.iconClass as paymentMethodIcon,
                    pm.shortCode
                FROM {$this->table} av
                LEFT JOIN clientUsers cu ON av.userId = cu.id
                LEFT JOIN paymentMethods pm ON av.paymentMethodId = pm.id
                WHERE av.id = :id";

        // 使用 Database 类的方法，自动规范化数据类型
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * 创建验证申请
     * @param array $data 验证数据
     * @return int 新创建的验证ID
     */
    public function createVerification($data) {
        // 准备插入数据
        $insertData = [
            'userId' => $data['userId'],
            'paymentMethodId' => $data['paymentMethodId'],
            'accountType' => $data['accountType'],
            'accountName' => $data['accountName'],
            'bankName' => $data['bankName'] ?? null,
            'accountNumber' => $data['accountNumber'] ?? null,
            'accountHolderName' => $data['accountHolderName'] ?? null,
            'swiftCode' => $data['swiftCode'] ?? null,
            'walletAddress' => $data['walletAddress'] ?? null,
            'walletNetwork' => $data['walletNetwork'] ?? null,
            'clientNotes' => $data['clientNotes'] ?? null,
            'verificationStatus' => 'pending',
            'submittedAt' => date('Y-m-d H:i:s')
        ];

        // 使用 Database 类的 insert 方法
        return $this->db->insert($this->table, $insertData);
    }

    /**
     * 更新验证状态（审核）
     * @param int $id 验证ID
     * @param string $status 新状态 (approved/rejected)
     * @param int $reviewedBy 审核人ID
     * @param string $reviewNotes 审核备注
     * @param string $rejectionReason 拒绝原因
     * @return bool
     */
    public function updateVerificationStatus($id, $status, $reviewedBy, $reviewNotes = null, $rejectionReason = null) {
        $updateData = [
            'verificationStatus' => $status,
            'reviewedBy' => $reviewedBy,
            'reviewedAt' => date('Y-m-d H:i:s'),
            'reviewNotes' => $reviewNotes,
            'rejectionReason' => $rejectionReason
        ];

        // 使用 Database 类的 update 方法
        return $this->db->update($this->table, $updateData, 'id = :id', ['id' => $id]) > 0;
    }

    /**
     * 检查用户是否已有待审核的验证申请
     * @param int $userId 用户ID
     * @param int $paymentMethodId 支付方式ID
     * @return bool
     */
    public function hasPendingVerification($userId, $paymentMethodId) {
        $sql = "SELECT COUNT(*) as count
                FROM {$this->table}
                WHERE userId = :userId
                AND paymentMethodId = :paymentMethodId
                AND verificationStatus = 'pending'";

        $result = $this->db->fetchOne($sql, [
            'userId' => $userId,
            'paymentMethodId' => $paymentMethodId
        ]);

        return $result && $result['count'] > 0;
    }

    /**
     * 获取验证统计
     * @return array
     */
    public function getStatistics() {
        $sql = "SELECT
                    verificationStatus as status,
                    COUNT(*) as count
                FROM {$this->table}
                GROUP BY verificationStatus";

        // 使用 Database 类的方法，自动规范化数据类型
        return $this->db->fetchAll($sql, []);
    }

    /**
     * 通过ID查找验证记录
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";

        // 使用 Database 类的方法，自动规范化数据类型
        return $this->db->fetchOne($sql, ['id' => $id]);
    }
}
