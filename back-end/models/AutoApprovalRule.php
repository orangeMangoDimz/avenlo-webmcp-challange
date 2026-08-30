<?php
/**
 * Auto Approval Rule Model
 * 对应表: autoApprovalRules
 * 自动审批规则管理
 */

require_once __DIR__ . '/BaseModel.php';

class AutoApprovalRule extends BaseModel {
    protected $table = 'autoApprovalRules';
    protected $primaryKey = 'id';
    protected $fillable = [
        'ruleType',
        'ruleName',
        'isEnabled',
        'priority',
        'minAmount',
        'maxAmount',
        'allowedCountries',
        'excludedCountries',
        'requiredClientTags',
        'excludedClientTags',
        'requireKycVerified',
        'minAccountAge',
        'minPreviousTransactions',
        'checkSavedWallet',
        'requireMatchingDepositMethod',
        'allowedPaymentMethods',
        'timeRestrictions',
        'description',
        'updatedBy'
    ];

    /**
     * 获取指定类型的启用规则（按优先级排序）
     * @param string $ruleType - deposit/withdrawal
     * @return array
     */
    public function getEnabledRules($ruleType) {
        $sql = "SELECT * FROM {$this->table} WHERE ruleType = ? AND isEnabled = 1 ORDER BY priority DESC, id ASC";
        return $this->query($sql, [$ruleType]);
    }

    /**
     * 获取规则详情（包含格式化的JSON字段）
     * @param int $id
     * @return array|null
     */
    public function getRuleDetails($id) {
        $rule = $this->findById($id);
        if (!$rule) {
            return null;
        }

        // 解析JSON字段
        if ($rule['allowedCountries']) {
            $rule['allowedCountries'] = json_decode($rule['allowedCountries'], true);
        }
        if ($rule['excludedCountries']) {
            $rule['excludedCountries'] = json_decode($rule['excludedCountries'], true);
        }
        if ($rule['allowedPaymentMethods']) {
            $rule['allowedPaymentMethods'] = json_decode($rule['allowedPaymentMethods'], true);
        }
        if ($rule['timeRestrictions']) {
            $rule['timeRestrictions'] = json_decode($rule['timeRestrictions'], true);
        }

        // 解析逗号分隔的标签
        if ($rule['requiredClientTags']) {
            $rule['requiredClientTags'] = array_filter(array_map('trim', explode(',', $rule['requiredClientTags'])));
        }
        if ($rule['excludedClientTags']) {
            $rule['excludedClientTags'] = array_filter(array_map('trim', explode(',', $rule['excludedClientTags'])));
        }

        return $rule;
    }

    /**
     * 更新规则（处理JSON字段）
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateRule($id, $data) {
        // 编码JSON字段
        if (isset($data['allowedCountries']) && is_array($data['allowedCountries'])) {
            $data['allowedCountries'] = json_encode($data['allowedCountries']);
        }
        if (isset($data['excludedCountries']) && is_array($data['excludedCountries'])) {
            $data['excludedCountries'] = json_encode($data['excludedCountries']);
        }
        if (isset($data['allowedPaymentMethods']) && is_array($data['allowedPaymentMethods'])) {
            $data['allowedPaymentMethods'] = json_encode($data['allowedPaymentMethods']);
        }
        if (isset($data['timeRestrictions']) && is_array($data['timeRestrictions'])) {
            $data['timeRestrictions'] = json_encode($data['timeRestrictions']);
        }

        // 处理标签字段（转换数组为逗号分隔字符串）
        if (isset($data['requiredClientTags']) && is_array($data['requiredClientTags'])) {
            $data['requiredClientTags'] = implode(',', array_filter($data['requiredClientTags']));
        }
        if (isset($data['excludedClientTags']) && is_array($data['excludedClientTags'])) {
            $data['excludedClientTags'] = implode(',', array_filter($data['excludedClientTags']));
        }

        // 规范 tinyint(1) 布尔字段为 0/1，避免前端传 true/false 在部分环境（PHP/PDO/MySQL）下导致更新失败
        $boolFields = ['isEnabled', 'requireKycVerified', 'checkSavedWallet', 'requireMatchingDepositMethod'];
        foreach ($boolFields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = ($data[$field] === true || $data[$field] === 1 || $data[$field] === '1') ? 1 : 0;
            }
        }

        $data['updatedAt'] = date('Y-m-d H:i:s');

        $this->update($id, $data);
        return true;
    }

    /**
     * 创建新规则
     * @param array $data
     * @return int|bool - 返回新规则ID或false
     */
    public function createRule($data) {
        // 编码JSON字段
        if (isset($data['allowedCountries']) && is_array($data['allowedCountries'])) {
            $data['allowedCountries'] = json_encode($data['allowedCountries']);
        }
        if (isset($data['excludedCountries']) && is_array($data['excludedCountries'])) {
            $data['excludedCountries'] = json_encode($data['excludedCountries']);
        }
        if (isset($data['allowedPaymentMethods']) && is_array($data['allowedPaymentMethods'])) {
            $data['allowedPaymentMethods'] = json_encode($data['allowedPaymentMethods']);
        }
        if (isset($data['timeRestrictions']) && is_array($data['timeRestrictions'])) {
            $data['timeRestrictions'] = json_encode($data['timeRestrictions']);
        }

        // 处理标签字段
        if (isset($data['requiredClientTags']) && is_array($data['requiredClientTags'])) {
            $data['requiredClientTags'] = implode(',', array_filter($data['requiredClientTags']));
        }
        if (isset($data['excludedClientTags']) && is_array($data['excludedClientTags'])) {
            $data['excludedClientTags'] = implode(',', array_filter($data['excludedClientTags']));
        }

        return $this->insert($data);
    }

    /**
     * 记录规则应用（更新lastAppliedAt和totalApprovals）
     * @param int $ruleId
     * @return bool
     */
    public function recordApplication($ruleId) {
        $sql = "UPDATE {$this->table} SET lastAppliedAt = NOW(), totalApprovals = totalApprovals + 1 WHERE id = ?";
        $this->db->query($sql, [$ruleId]);
        return true;
    }

    /**
     * 启用/禁用规则
     * @param int $id
     * @param bool $enabled
     * @return bool
     */
    public function toggleRule($id, $enabled) {
        return $this->update($id, ['isEnabled' => $enabled ? 1 : 0]);
    }

    /**
     * 获取规则统计信息
     * @param int $id
     * @return array|null
     */
    public function getRuleStats($id) {
        $sql = "SELECT
                    r.id,
                    r.ruleName,
                    r.ruleType,
                    r.isEnabled,
                    r.totalApprovals,
                    r.lastAppliedAt,
                    COUNT(l.id) as logCount,
                    SUM(CASE WHEN l.wasAutoApproved = 1 THEN 1 ELSE 0 END) as approvedCount,
                    SUM(CASE WHEN l.wasAutoApproved = 0 THEN 1 ELSE 0 END) as rejectedCount
                FROM {$this->table} r
                LEFT JOIN autoApprovalLog l ON r.id = l.ruleId
                WHERE r.id = ?
                GROUP BY r.id";

        $result = $this->query($sql, [$id]);
        return $result ? $result[0] : null;
    }

    /**
     * 获取所有规则的统计摘要
     * @param string $ruleType - deposit/withdrawal
     * @return array
     */
    public function getAllRulesStats($ruleType = null) {
        $sql = "SELECT
                    r.*,
                    COUNT(l.id) as logCount,
                    SUM(CASE WHEN l.wasAutoApproved = 1 THEN 1 ELSE 0 END) as approvedCount
                FROM {$this->table} r
                LEFT JOIN autoApprovalLog l ON r.id = l.ruleId";

        $params = [];
        if ($ruleType) {
            $sql .= " WHERE r.ruleType = ?";
            $params[] = $ruleType;
        }

        $sql .= " GROUP BY r.id ORDER BY r.priority DESC, r.id ASC";

        return $this->query($sql, $params);
    }
}
