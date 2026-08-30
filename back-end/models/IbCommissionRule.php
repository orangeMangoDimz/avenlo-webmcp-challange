<?php
/**
 * IB Commission Rule 模型
 * 对应表：ibCommissionRules
 */

require_once __DIR__ . '/BaseModel.php';

class IbCommissionRule extends BaseModel {
    protected $table = 'ibCommissionRules';
    protected $primaryKey = 'id';

    protected $fillable = [
        'ruleName', 'ruleType', 'ruleDescription', 'targetRegion',
        'paymentCycle', 'paymentDay', 'minimumPayout', 'payoutCurrency',
        'autoPaymentEnabled', 'status', 'createdBy', 'updatedBy',
        'tierId', 'product_type', 'product', 'trading_platforms_key', 'minimum_trade', 'maximum_trade', 'rate', 'fixed_amount', 'rebateAmount'
    ];

    /**
     * 获取佣金规则列表（带分页）
     */
    public function getRules($page = 1, $perPage = 10, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $conditions = [];
        $params = [];

        // 关键字：按 Rule Name 模糊搜索
        if (!empty($filters['search'])) {
            $conditions[] = "cr.ruleName LIKE :search";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        // 构建筛选条件
        if (isset($filters['status'])) {
            $conditions[] = "cr.status = :status";
            $params['status'] = $filters['status'];
        }

        if (isset($filters['ruleType'])) {
            $conditions[] = "cr.ruleType = :ruleType";
            $params['ruleType'] = $filters['ruleType'];
        }

        if (isset($filters['targetRegion'])) {
            $conditions[] = "cr.targetRegion = :targetRegion";
            $params['targetRegion'] = $filters['targetRegion'];
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // 使用视图获取规则列表
        $sql = "SELECT * FROM vw_ibCommissionRulesSummary cr
                {$whereClause}
                ORDER BY cr.createdAt DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as count
                     FROM ibCommissionRules cr
                     {$whereClause}";

        $items = $this->db->fetchAll($sql, $params);
        $total = $this->db->fetchOne($countSql, $params)['count'];

        if (!empty($items)) {
            $items = $this->decodeViewProducts($items);
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * 获取规则详细信息（包含产品配置和额外规则）
     */
    public function getRuleDetails($ruleId) {
        $sql = "SELECT * FROM ibCommissionRules WHERE id = :ruleId LIMIT 1";
        $rule = $this->db->fetchOne($sql, ['ruleId' => $ruleId]);

        if (!$rule) {
            return null;
        }

        // 获取产品配置
        $rule['products'] = $this->getRuleProducts($ruleId);

        // 获取额外规则
        $rule['additionalRules'] = $this->getAdditionalRules($ruleId);

        // 获取分配统计
        $rule['assignmentStats'] = $this->getAssignmentStats($ruleId);

        return $rule;
    }

    /**
     * 获取规则的产品配置
     */
    public function getRuleProducts($ruleId) {
        $sql = "SELECT * FROM ibRuleProducts
                WHERE ruleId = :ruleId
                ORDER BY id ASC";

        return $this->db->fetchAll($sql, ['ruleId' => $ruleId]);
    }

    private function decodeViewProducts(array $items) {
        foreach ($items as &$item) {
            if (!isset($item['products']) || $item['products'] === null || $item['products'] === '') {
                $item['products'] = [];
                continue;
            }

            if (is_string($item['products'])) {
                $decodedProducts = json_decode($item['products'], true);
                $item['products'] = is_array($decodedProducts) ? $decodedProducts : [];
                continue;
            }

            if (!is_array($item['products'])) {
                $item['products'] = [];
            }
        }
        unset($item);

        return $items;
    }

    /**
     * 获取规则的额外规则配置
     */
    public function getAdditionalRules($ruleId, $includeInactive = false) {
        // 先获取额外规则列表
        $sql = "SELECT ar.*
                FROM ibRuleAdditionalRules ar
                WHERE ar.ruleId = :ruleId";

        if (!$includeInactive) {
            $sql .= " AND ar.isActive = 1";
        }

        $sql .= " ORDER BY ar.id ASC";

        $additionalRules = $this->db->fetchAll($sql, ['ruleId' => $ruleId]);

        // 为每个额外规则添加 tierCount 和 tiers
        foreach ($additionalRules as &$additionalRule) {
            // 获取层级数量
            $tierCountSql = "SELECT COUNT(*) as count FROM ibRuleCommissionTiers WHERE additionalRuleId = :additionalRuleId";
            $tierCountResult = $this->db->fetchOne($tierCountSql, ['additionalRuleId' => $additionalRule['id']]);
            $additionalRule['tierCount'] = $tierCountResult ? (int)$tierCountResult['count'] : 0;

            // 如果是volume_tiers类型，获取层级配置
            if ($additionalRule['ruleType'] === 'volume_tiers') {
                $additionalRule['tiers'] = $this->getCommissionTiers($additionalRule['id']);
            }
        }

        return $additionalRules;
    }

    /**
     * 获取佣金层级
     */
    public function getCommissionTiers($additionalRuleId) {
        $sql = "SELECT * FROM ibRuleCommissionTiers
                WHERE additionalRuleId = :additionalRuleId
                ORDER BY tierLevel ASC";

        return $this->db->fetchAll($sql, ['additionalRuleId' => $additionalRuleId]);
    }

    /**
     * 获取规则分配统计
     */
    public function getAssignmentStats($ruleId) {
        // 分别查询两个统计数据，避免 PDO 参数绑定问题
        $ibCountSql = "SELECT COUNT(DISTINCT ibPartnerId) as count FROM ibPartnerRuleAssignments WHERE ruleId = :ruleId";
        $appCountSql = "SELECT COUNT(DISTINCT applicationId) as count FROM ibApplicationRuleAssignments WHERE ruleId = :ruleId";

        $ibResult = $this->db->fetchOne($ibCountSql, ['ruleId' => $ruleId]);
        $appResult = $this->db->fetchOne($appCountSql, ['ruleId' => $ruleId]);

        return [
            'assignedIbCount' => $ibResult ? (int)$ibResult['count'] : 0,
            'assignedApplicationsCount' => $appResult ? (int)$appResult['count'] : 0
        ];
    }

    /**
     * 获取所有激活的规则（用于选择器）
     */
    public function getActiveRules() {
        $sql = "SELECT
                    id,
                    ruleName,
                    ruleType,
                    paymentCycle,
                    minimumPayout,
                    payoutCurrency,
                    targetRegion,
                    (SELECT COUNT(*) FROM ibRuleProducts WHERE ruleId = ibCommissionRules.id) as productCount,
                    (SELECT COUNT(*) FROM ibRuleAdditionalRules WHERE ruleId = ibCommissionRules.id AND isActive = 1) as additionalRulesCount
                FROM ibCommissionRules
                WHERE status = 'active'
                ORDER BY ruleName ASC";

        return $this->db->fetchAll($sql);
    }

    /**
     * 按 Tier Level 获取激活的规则：仅返回 tierId 为空或等于该层级的规则
     * @param int $tierLevelId ibTierLevels.id
     */
    public function getActiveRulesByTierLevel($tierLevelId) {
        $tierLevelId = (int) $tierLevelId;
        if ($tierLevelId <= 0) {
            return $this->getActiveRules();
        }
        $sql = "SELECT
                    cr.id,
                    cr.ruleName,
                    cr.ruleType,
                    cr.paymentCycle,
                    cr.minimumPayout,
                    cr.payoutCurrency,
                    cr.targetRegion,
                    (SELECT COUNT(*) FROM ibRuleProducts WHERE ruleId = cr.id) as productCount,
                    (SELECT COUNT(*) FROM ibRuleAdditionalRules WHERE ruleId = cr.id AND isActive = 1) as additionalRulesCount
                FROM ibCommissionRules cr
                WHERE cr.status = 'active'
                AND (cr.tierId IS NULL OR cr.tierId = :tierLevelId)
                ORDER BY cr.ruleName ASC";
        return $this->db->fetchAll($sql, ['tierLevelId' => $tierLevelId]);
    }

    /**
     * 复制规则
     */
    public function duplicateRule($ruleId, $createdBy) {
        // 获取原规则
        $originalRule = $this->findById($ruleId);
        if (!$originalRule) {
            return false;
        }

        // 开始事务
        $this->db->beginTransaction();

        try {
            $newRuleData = [
                'ruleName' => $originalRule['ruleName'] . ' (Copy)',
                'status' => 'draft',
                'createdBy' => $createdBy
            ];

            $fieldsToCopy = [
                'ruleType',
                'ruleDescription',
                'targetRegion',
                'paymentCycle',
                'paymentDay',
                'minimumPayout',
                'payoutCurrency',
                'autoPaymentEnabled',
                'trading_platforms_key',
                'tierId',
                'product_type',
                'product',
                'minimum_trade',
                'maximum_trade',
                'rate',
                'fixed_amount',
                'rebateAmount'
            ];

            foreach ($fieldsToCopy as $field) {
                if (array_key_exists($field, $originalRule)) {
                    $newRuleData[$field] = $originalRule[$field];
                }
            }

            $newRuleId = $this->create($newRuleData);

            // 复制产品配置
            $products = $this->getRuleProducts($ruleId);
            foreach ($products as $product) {
                $productData = [
                    'ruleId' => $newRuleId,
                    'productType' => $product['productType'],
                    'productName' => $product['productName'],
                    'commissionType' => $product['commissionType'],
                    'commissionRate' => $product['commissionRate'],
                    'additionalRate' => $product['additionalRate'],
                    'minimumVolume' => $product['minimumVolume']
                ];
                if (isset($product['productId']) && $product['productId'] !== null && $product['productId'] !== '') {
                    $productData['productId'] = (int) $product['productId'];
                }

                $this->db->insert('ibRuleProducts', $productData);
            }

            // 复制额外规则，并保留 volume tiers 配置
            $additionalRules = $this->getAdditionalRules($ruleId, true);
            foreach ($additionalRules as $additionalRule) {
                $additionalRuleData = [
                    'ruleId' => $newRuleId,
                    'productType' => $additionalRule['productType'],
                    'productName' => $additionalRule['productName'],
                    'ruleType' => $additionalRule['ruleType'],
                    'ruleValue' => $additionalRule['ruleValue'],
                    'ruleCondition' => $additionalRule['ruleCondition'],
                    'isActive' => $additionalRule['isActive']
                ];

                $newAdditionalRuleId = $this->db->insert('ibRuleAdditionalRules', $additionalRuleData);

                if (
                    $additionalRule['ruleType'] === 'volume_tiers'
                    && !empty($additionalRule['tiers'])
                    && is_array($additionalRule['tiers'])
                ) {
                    foreach ($additionalRule['tiers'] as $tier) {
                        $tierData = [
                            'additionalRuleId' => $newAdditionalRuleId,
                            'tierLevel' => $tier['tierLevel'],
                            'tierName' => $tier['tierName'],
                            'commissionRate' => $tier['commissionRate'],
                            'minimumVolume' => $tier['minimumVolume'],
                            'maximumVolume' => $tier['maximumVolume']
                        ];

                        $this->db->insert('ibRuleCommissionTiers', $tierData);
                    }
                }
            }

            $this->db->commit();
            return $newRuleId;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
