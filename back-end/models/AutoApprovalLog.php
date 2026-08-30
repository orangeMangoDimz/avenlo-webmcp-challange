<?php
/**
 * Auto Approval Log Model
 * 对应表: autoApprovalLog
 * 自动审批决策审计日志
 */

require_once __DIR__ . '/BaseModel.php';

class AutoApprovalLog extends BaseModel {
    protected $table = 'autoApprovalLog';
    protected $primaryKey = 'id';
    protected $fillable = [
        'transactionType',
        'transactionId',
        'transactionRefId',
        'userId',
        'ruleId',
        'wasAutoApproved',
        'checkResults',
        'rejectionReason',
        'amount',
        'clientCountry',
        'clientTags',
        'kycStatus',
        'ipAddress',
        'checkedAt'
    ];

    /**
     * 记录自动审批检查结果
     * @param array $data
     * @return int|bool
     */
    public function logCheck($data) {
        // 编码JSON字段
        if (isset($data['checkResults']) && is_array($data['checkResults'])) {
            $data['checkResults'] = json_encode($data['checkResults']);
        }

        $data['checkedAt'] = date('Y-m-d H:i:s');

        return $this->create($data);
    }

    /**
     * 获取交易的审批历史
     * @param string $transactionType - deposit/withdrawal
     * @param int $transactionId
     * @return array
     */
    public function getTransactionHistory($transactionType, $transactionId) {
        $sql = "SELECT l.*, r.ruleName
                FROM {$this->table} l
                LEFT JOIN autoApprovalRules r ON l.ruleId = r.id
                WHERE l.transactionType = ? AND l.transactionId = ?
                ORDER BY l.checkedAt DESC";

        return $this->query($sql, [$transactionType, $transactionId]);
    }

    /**
     * 获取客户的审批历史
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getUserHistory($userId, $limit = 50) {
        $sql = "SELECT l.*, r.ruleName
                FROM {$this->table} l
                LEFT JOIN autoApprovalRules r ON l.ruleId = r.id
                WHERE l.userId = ?
                ORDER BY l.checkedAt DESC
                LIMIT ?";

        return $this->query($sql, [$userId, $limit]);
    }

    /**
     * 获取规则的应用历史
     * @param int $ruleId
     * @param int $limit
     * @return array
     */
    public function getRuleHistory($ruleId, $limit = 100) {
        $sql = "SELECT * FROM {$this->table}
                WHERE ruleId = ?
                ORDER BY checkedAt DESC
                LIMIT ?";

        return $this->query($sql, [$ruleId, $limit]);
    }

    /**
     * 获取自动审批统计
     * @param string $transactionType - deposit/withdrawal
     * @param string $dateFrom
     * @param string $dateTo
     * @return array
     */
    public function getApprovalStats($transactionType = null, $dateFrom = null, $dateTo = null) {
        $sql = "SELECT
                    COUNT(*) as totalChecks,
                    SUM(CASE WHEN wasAutoApproved = 1 THEN 1 ELSE 0 END) as approvedCount,
                    SUM(CASE WHEN wasAutoApproved = 0 THEN 1 ELSE 0 END) as rejectedCount,
                    SUM(CASE WHEN wasAutoApproved = 1 THEN amount ELSE 0 END) as approvedAmount,
                    AVG(CASE WHEN wasAutoApproved = 1 THEN amount ELSE NULL END) as avgApprovedAmount,
                    transactionType
                FROM {$this->table}
                WHERE 1=1";

        $params = [];

        if ($transactionType) {
            $sql .= " AND transactionType = ?";
            $params[] = $transactionType;
        }

        if ($dateFrom) {
            $sql .= " AND checkedAt >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $sql .= " AND checkedAt <= ?";
            $params[] = $dateTo;
        }

        $sql .= " GROUP BY transactionType";

        return $this->query($sql, $params);
    }

    /**
     * 获取拒绝原因统计
     * @param string $transactionType
     * @param int $limit
     * @return array
     */
    public function getRejectionReasons($transactionType = null, $limit = 10) {
        $sql = "SELECT
                    rejectionReason,
                    COUNT(*) as count,
                    transactionType
                FROM {$this->table}
                WHERE wasAutoApproved = 0 AND rejectionReason IS NOT NULL";

        $params = [];

        if ($transactionType) {
            $sql .= " AND transactionType = ?";
            $params[] = $transactionType;
        }

        $sql .= " GROUP BY rejectionReason, transactionType
                  ORDER BY count DESC
                  LIMIT ?";
        $params[] = $limit;

        return $this->query($sql, $params);
    }

    /**
     * 获取国家分布统计
     * @param string $transactionType
     * @param bool $approvedOnly
     * @return array
     */
    public function getCountryDistribution($transactionType = null, $approvedOnly = false) {
        $sql = "SELECT
                    clientCountry,
                    COUNT(*) as count,
                    SUM(CASE WHEN wasAutoApproved = 1 THEN 1 ELSE 0 END) as approvedCount,
                    SUM(amount) as totalAmount
                FROM {$this->table}
                WHERE clientCountry IS NOT NULL";

        $params = [];

        if ($transactionType) {
            $sql .= " AND transactionType = ?";
            $params[] = $transactionType;
        }

        if ($approvedOnly) {
            $sql .= " AND wasAutoApproved = 1";
        }

        $sql .= " GROUP BY clientCountry
                  ORDER BY count DESC";

        return $this->query($sql, $params);
    }

    /**
     * 清理旧日志
     * @param int $daysToKeep - 保留天数
     * @return int - 删除的记录数
     */
    public function cleanOldLogs($daysToKeep = 90) {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
        $sql = "DELETE FROM {$this->table} WHERE checkedAt < ?";
        $stmt = $this->db->query($sql, [$cutoffDate]);
        return $stmt ? $stmt->rowCount() : 0;
    }
}
