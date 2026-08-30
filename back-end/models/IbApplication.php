<?php
/**
 * IB Application 模型
 * 对应表：ibApplications
 */

require_once __DIR__ . '/BaseModel.php';

class IbApplication extends BaseModel {
    protected $table = 'ibApplications';
    protected $primaryKey = 'id';

    protected $fillable = [
        'applicantName', 'applicantEmail', 'applicantPhone', 'ibType',
        'companyName', 'country', 'expectedClients', 'yearsOfExperience',
        'hasPreviousIbExperience', 'applicationStatus', 'reviewerId', 'auditorId',
        'assignedTierLevelId', 'applicationDate', 'reviewStartDate', 'rulesLastSavedAt',
        'isEdit', 'reviewCompletedDate', 'approvedBy', 'rejectedBy', 'rejectionReason',
        'additionalInfoRequest', 'createdIbPartnerId', 'updatedBy', 'clientId'
    ];

    /**
     * 获取申请列表（带分页和过滤）
     */
    public function getApplications($page = 1, $perPage = 10, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $conditions = [];
        $params = [];

        // 构建筛选条件
        if (isset($filters['applicationStatus'])) {
            $conditions[] = "app.applicationStatus = :applicationStatus";
            $params['applicationStatus'] = $filters['applicationStatus'];
        }

        if (isset($filters['ibType'])) {
            $conditions[] = "app.ibType = :ibType";
            $params['ibType'] = $filters['ibType'];
        }

        if (isset($filters['reviewerId'])) {
            $conditions[] = "app.reviewerId = :reviewerId";
            $params['reviewerId'] = $filters['reviewerId'];
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // 使用视图获取申请列表
        $sql = "SELECT * FROM vw_ibApplicationsDetails app
                {$whereClause}
                ORDER BY app.applicationDate DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as count
                     FROM ibApplications app
                     {$whereClause}";

        $items = $this->db->fetchAll($sql, $params);
        $total = $this->db->fetchOne($countSql, $params)['count'];

        // 处理每个item的数据类型和JSON字段
        foreach ($items as &$item) {
            // 确保 isEdit 字段返回为数字 0/1，而不是布尔值
            if (isset($item['isEdit'])) {
                $item['isEdit'] = (int)$item['isEdit'];
            }

            // 解析 preAssignedRules JSON 字段（如果存在且是字符串）
            if (isset($item['preAssignedRules']) && is_string($item['preAssignedRules'])) {
                $decoded = json_decode($item['preAssignedRules'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $item['preAssignedRules'] = $decoded;
                } else {
                    // 如果解析失败，尝试通过 getPreAssignedRules 获取
                    $item['preAssignedRules'] = $this->getPreAssignedRules($item['id']);
                }
            } elseif (!isset($item['preAssignedRules']) || empty($item['preAssignedRules'])) {
                // 如果没有 preAssignedRules 字段，但有 preAssignedRulesCount，获取规则列表
                if (isset($item['preAssignedRulesCount']) && $item['preAssignedRulesCount'] > 0) {
                    $item['preAssignedRules'] = $this->getPreAssignedRules($item['id']);
                } else {
                    $item['preAssignedRules'] = [];
                }
            }
        }
        unset($item);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * 获取申请详细信息
     */
    public function getApplicationDetails($applicationId) {
        $sql = "SELECT
                    app.*,
                    tl.tierLevel,
                    tl.tierName,
                    tl.tierDescription,
                    au.fullName as reviewerName,
                    au.email as reviewerEmail,
                    au2.fullName as auditorName,
                    au2.email as auditorEmail
                FROM ibApplications app
                LEFT JOIN ibTierLevels tl ON app.assignedTierLevelId = tl.id
                LEFT JOIN adminUsers au ON app.reviewerId = au.id
                LEFT JOIN adminUsers au2 ON app.auditorId = au2.id
                WHERE app.id = :applicationId
                LIMIT 1";

        $application = $this->db->fetchOne($sql, ['applicationId' => $applicationId]);

        if (!$application) {
            return null;
        }

        // 确保 isEdit 字段返回为数字 0/1，而不是布尔值
        if (isset($application['isEdit'])) {
            $application['isEdit'] = (int)$application['isEdit'];
        }

        // 获取产品焦点
        $application['productFocus'] = $this->getProductFocus($applicationId);

        // 获取预分配的规则
        $application['preAssignedRules'] = $this->getPreAssignedRules($applicationId);

        // 获取个性化规则
        $application['customRules'] = $this->getCustomRules($applicationId);

        // 获取状态历史
        $application['statusHistory'] = $this->getStatusHistory($applicationId);

        return $application;
    }

    /**
     * 获取产品焦点
     */
    public function getProductFocus($applicationId) {
        $sql = "SELECT * FROM ibApplicationProductFocus
                WHERE applicationId = :applicationId
                LIMIT 1";

        $result = $this->db->fetchOne($sql, ['applicationId' => $applicationId]);

        if ($result) {
            // 解析JSON字段
            $result['primaryProducts'] = json_decode($result['primaryProducts'], true);
            $result['targetRegions'] = json_decode($result['targetRegions'], true);
            $result['marketingChannels'] = json_decode($result['marketingChannels'], true);
        }

        return $result;
    }

    /**
     * 获取预分配的规则
     */
    public function getPreAssignedRules($applicationId) {
        $sql = "SELECT
                    cr.*,
                    ara.assignedAt
                FROM ibApplicationRuleAssignments ara
                INNER JOIN ibCommissionRules cr ON ara.ruleId = cr.id
                WHERE ara.applicationId = :applicationId
                ORDER BY ara.assignedAt DESC";

        return $this->db->fetchAll($sql, ['applicationId' => $applicationId]);
    }

    /**
     * 获取状态历史
     */
    public function getStatusHistory($applicationId) {
        $sql = "SELECT
                    sh.*,
                    au.fullName as changedByName,
                    au.username as changedByUsername
                FROM ibApplicationStatusHistory sh
                LEFT JOIN adminUsers au ON sh.changedBy = au.id
                WHERE sh.applicationId = :applicationId
                ORDER BY sh.createdAt DESC";

        return $this->db->fetchAll($sql, ['applicationId' => $applicationId]);
    }

    /**
     * 获取申请统计
     */
    public function getApplicationStatistics() {
        $sql = "SELECT
                    COUNT(*) as totalApplications,
                    SUM(CASE WHEN applicationStatus = 'pending' THEN 1 ELSE 0 END) as pendingCount,
                    SUM(CASE WHEN applicationStatus = 'in_review' THEN 1 ELSE 0 END) as inReviewCount,
                    SUM(CASE WHEN applicationStatus = 'approved' THEN 1 ELSE 0 END) as approvedCount,
                    SUM(CASE WHEN applicationStatus = 'rejected' THEN 1 ELSE 0 END) as rejectedCount,
                    SUM(CASE WHEN applicationStatus = 'more_info_requested' THEN 1 ELSE 0 END) as moreInfoRequestedCount,
                    SUM(CASE WHEN DATE(applicationDate) = CURDATE() THEN 1 ELSE 0 END) as todayApplications,
                    SUM(CASE WHEN DATE(reviewCompletedDate) = CURDATE() AND applicationStatus = 'approved' THEN 1 ELSE 0 END) as approvedToday
                FROM ibApplications";

        return $this->db->fetchOne($sql);
    }

    /**
     * 批准申请（调用存储过程）
     */
    public function approveApplication($applicationId, $approvedBy, $tierLevelId) {
        $sql = "CALL sp_approveIbApplication(:applicationId, :approvedBy, :tierLevelId, @ibPartnerId, @success, @message)";

        $this->db->query($sql, [
            'applicationId' => $applicationId,
            'approvedBy' => $approvedBy,
            'tierLevelId' => $tierLevelId
        ]);

        // 获取输出参数
        $result = $this->db->fetchOne("SELECT @ibPartnerId as ibPartnerId, @success as success, @message as message");

        return $result;
    }

    /**
     * 拒绝申请（调用存储过程）
     */
    public function rejectApplication($applicationId, $rejectedBy, $rejectionReason) {
        $sql = "CALL sp_rejectIbApplication(:applicationId, :rejectedBy, :rejectionReason, @success, @message)";

        $this->db->query($sql, [
            'applicationId' => $applicationId,
            'rejectedBy' => $rejectedBy,
            'rejectionReason' => $rejectionReason
        ]);

        // 获取输出参数
        $result = $this->db->fetchOne("SELECT @success as success, @message as message");

        return $result;
    }

    /**
     * 分配审核人
     */
    public function assignReviewer($applicationId, $reviewerId, $assignedBy) {
        $data = [
            'reviewerId' => $reviewerId,
            'applicationStatus' => 'in_review',
            'reviewStartDate' => date('Y-m-d H:i:s'),
            'updatedBy' => $assignedBy
        ];

        return $this->update($applicationId, $data);
    }

    /**
     * 请求更多信息
     */
    public function requestMoreInfo($applicationId, $infoRequest, $requestedBy) {
        $data = [
            'applicationStatus' => 'more_info_requested',
            'additionalInfoRequest' => $infoRequest,
            'updatedBy' => $requestedBy
        ];

        return $this->update($applicationId, $data);
    }

    /**
     * 分配层级
     */
    public function assignTierLevel($applicationId, $tierLevelId, $assignedBy) {
        // 检查当前状态，如果在 in_review 状态下，更新 rulesLastSavedAt
        $application = $this->findById($applicationId);
        $data = [
            'assignedTierLevelId' => $tierLevelId,
            'updatedBy' => $assignedBy
        ];

        if ($application && $application['applicationStatus'] === 'in_review') {
            $data['rulesLastSavedAt'] = date('Y-m-d H:i:s');
        }

        return $this->update($applicationId, $data);
    }

    /**
     * 预分配规则
     */
    public function assignRule($applicationId, $ruleId, $assignedBy) {
        $sql = "INSERT INTO ibApplicationRuleAssignments
                (applicationId, ruleId, assignedBy)
                VALUES (:applicationId, :ruleId, :assignedBy)
                ON DUPLICATE KEY UPDATE assignedAt = CURRENT_TIMESTAMP";

        $stmt = $this->db->query($sql, [
            'applicationId' => $applicationId,
            'ruleId' => $ruleId,
            'assignedBy' => $assignedBy
        ]);

        // 检查当前状态，如果在 in_review 状态下，更新 rulesLastSavedAt
        $application = $this->findById($applicationId);
        if ($application && $application['applicationStatus'] === 'in_review') {
            $this->update($applicationId, [
                'rulesLastSavedAt' => date('Y-m-d H:i:s'),
                'updatedBy' => $assignedBy
            ]);
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * 移除预分配规则
     */
    public function removeRule($applicationId, $ruleId) {
        $sql = "DELETE FROM ibApplicationRuleAssignments
                WHERE applicationId = :applicationId AND ruleId = :ruleId";

        $stmt = $this->db->query($sql, [
            'applicationId' => $applicationId,
            'ruleId' => $ruleId
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * 批量分配审批人（操作员和审核审批人）
     */
    public function bulkAssignReviewers($applicationId, $operatorId, $auditorId, $assignedBy) {
        $data = [
            'reviewerId' => $operatorId, // 销售操作员
            'auditorId' => $auditorId, // 审核审批人
            'applicationStatus' => 'in_review',
            'reviewStartDate' => date('Y-m-d H:i:s'),
            'updatedBy' => $assignedBy
        ];

        return $this->update($applicationId, $data);
    }

    /**
     * 保存个性化规则配置
     */
    public function saveCustomRules($applicationId, $rulesData, $adminId) {
        // 开始事务
        $this->db->beginTransaction();

        try {
            // 删除现有的个性化规则
            $this->deleteCustomRules($applicationId);

            // 删除现有的规则分配（ibApplicationRuleAssignments）
            $this->db->query(
                "DELETE FROM ibApplicationRuleAssignments WHERE applicationId = :applicationId",
                ['applicationId' => $applicationId]
            );

            $savedRules = [];
            $assignedRuleIds = []; // 记录已分配的规则ID，用于更新 ibApplicationRuleAssignments

            // 保存每个规则
            foreach ($rulesData as $ruleData) {
                // 确保 ruleName 存在，如果不存在则从 ruleId 查询或使用默认值
                $ruleName = $ruleData['ruleName'] ?? null;
                if (empty($ruleName) && !empty($ruleData['ruleId'])) {
                    // 尝试从 ibCommissionRules 表查询 ruleName
                    $ruleQuery = "SELECT ruleName FROM ibCommissionRules WHERE id = :ruleId LIMIT 1";
                    $ruleInfo = $this->db->fetchOne($ruleQuery, ['ruleId' => $ruleData['ruleId']]);
                    $ruleName = $ruleInfo['ruleName'] ?? ('Rule ' . $ruleData['ruleId']);
                }
                if (empty($ruleName)) {
                    $ruleName = 'Custom Rule';
                }

                // 保存主规则
                $customRuleId = $this->db->insert('ibApplicationCustomRules', [
                    'applicationId' => $applicationId,
                    'ruleId' => $ruleData['ruleId'] ?? null,
                    'ruleName' => $ruleName,
                    'ruleType' => $ruleData['ruleType'] ?? 'custom',
                    'ruleDescription' => $ruleData['ruleDescription'] ?? null,
                    'targetRegion' => $ruleData['targetRegion'] ?? 'global',
                    'paymentCycle' => $ruleData['paymentCycle'] ?? 'monthly',
                    'paymentDay' => $ruleData['paymentDay'] ?? null,
                    'minimumPayout' => $ruleData['minimumPayout'] ?? 100.00,
                    'payoutCurrency' => $ruleData['payoutCurrency'] ?? 'USD',
                    'autoPaymentEnabled' => $ruleData['autoPaymentEnabled'] ?? 1,
                    'status' => $ruleData['status'] ?? 'active',
                    'createdBy' => $adminId,
                    'updatedBy' => $adminId
                ]);

                // 保存产品配置
                if (isset($ruleData['products']) && is_array($ruleData['products'])) {
                    foreach ($ruleData['products'] as $product) {
                        $this->db->insert('ibApplicationCustomRuleProducts', [
                            'customRuleId' => $customRuleId,
                            'productType' => $product['productType'] ?? 'security',
                            'productName' => $product['productName'],
                            'commissionType' => $product['commissionType'],
                            'commissionRate' => $product['commissionRate'],
                            'additionalRate' => $product['additionalRate'] ?? 0,
                            'minimumVolume' => $product['minimumVolume'] ?? '0.01 lots'
                        ]);
                    }
                }

                // 保存额外规则
                if (isset($ruleData['additionalRules']) && is_array($ruleData['additionalRules'])) {
                    foreach ($ruleData['additionalRules'] as $additionalRule) {
                        $additionalRuleId = $this->db->insert('ibApplicationCustomRuleAdditionalRules', [
                            'customRuleId' => $customRuleId,
                            'productType' => $additionalRule['productType'] ?? 'security',
                            'productName' => $additionalRule['productName'] ?? null,
                            'ruleType' => $additionalRule['ruleType'],
                            'ruleValue' => $additionalRule['ruleValue'] ?? null,
                            'ruleCondition' => $additionalRule['ruleCondition'] ?? null,
                            'isActive' => $additionalRule['isActive'] ?? 1
                        ]);

                        // 保存佣金层级（如果是volume_tiers类型）
                        if ($additionalRule['ruleType'] === 'volume_tiers' && isset($additionalRule['tiers'])) {
                            foreach ($additionalRule['tiers'] as $tier) {
                                $this->db->insert('ibApplicationCustomRuleCommissionTiers', [
                                    'additionalRuleId' => $additionalRuleId,
                                    'tierLevel' => $tier['tierLevel'],
                                    'tierName' => $tier['tierName'] ?? null,
                                    'commissionRate' => $tier['commissionRate'],
                                    'minimumVolume' => $tier['minimumVolume'] ?? 0,
                                    'maximumVolume' => $tier['maximumVolume'] ?? null
                                ]);
                            }
                        }
                    }
                }

                $savedRules[] = $customRuleId;

                // 记录规则ID，用于更新 ibApplicationRuleAssignments
                if (isset($ruleData['ruleId']) && $ruleData['ruleId']) {
                    $assignedRuleIds[] = (int)$ruleData['ruleId'];
                }
            }

            // 更新 ibApplicationRuleAssignments 表（记录哪些规则被分配给了这个 application）
            foreach ($assignedRuleIds as $ruleId) {
                $this->assignRule($applicationId, $ruleId, $adminId);
            }

            // 所有数据保存成功后，更新 rulesLastSavedAt 和 isEdit 字段
            // isEdit = 1 表示进入审批模式（Reviewer可以审批）
            // 如果当前状态是 rejected，同时更新状态为 in_review
            $application = $this->findById($applicationId);
            $updateData = [
                'rulesLastSavedAt' => date('Y-m-d H:i:s'),
                'isEdit' => 1, // 保存成功后进入审批模式
                'updatedBy' => $adminId
            ];

            // 如果当前状态是 rejected，更新状态为 in_review
            if ($application && $application['applicationStatus'] === 'rejected') {
                $updateData['applicationStatus'] = 'in_review';
            }

            $this->update($applicationId, $updateData);

            $this->db->commit();
            return ['savedRules' => $savedRules];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 删除个性化规则
     */
    private function deleteCustomRules($applicationId) {
        // 获取所有customRuleId
        $customRules = $this->db->fetchAll(
            "SELECT id FROM ibApplicationCustomRules WHERE applicationId = :applicationId",
            ['applicationId' => $applicationId]
        );

        foreach ($customRules as $rule) {
            $customRuleId = $rule['id'];

            // 获取additionalRuleId
            $additionalRules = $this->db->fetchAll(
                "SELECT id FROM ibApplicationCustomRuleAdditionalRules WHERE customRuleId = :customRuleId",
                ['customRuleId' => $customRuleId]
            );

            // 删除佣金层级
            foreach ($additionalRules as $ar) {
                $this->db->query(
                    "DELETE FROM ibApplicationCustomRuleCommissionTiers WHERE additionalRuleId = :additionalRuleId",
                    ['additionalRuleId' => $ar['id']]
                );
            }

            // 删除额外规则
            $this->db->query(
                "DELETE FROM ibApplicationCustomRuleAdditionalRules WHERE customRuleId = :customRuleId",
                ['customRuleId' => $customRuleId]
            );

            // 删除产品配置
            $this->db->query(
                "DELETE FROM ibApplicationCustomRuleProducts WHERE customRuleId = :customRuleId",
                ['customRuleId' => $customRuleId]
            );
        }

        // 删除主规则
        $this->db->query(
            "DELETE FROM ibApplicationCustomRules WHERE applicationId = :applicationId",
            ['applicationId' => $applicationId]
        );
    }

    /**
     * 获取个性化规则配置
     */
    public function getCustomRules($applicationId) {
        $sql = "SELECT * FROM ibApplicationCustomRules WHERE applicationId = :applicationId";
        $customRules = $this->db->fetchAll($sql, ['applicationId' => $applicationId]);

        foreach ($customRules as &$rule) {
            $customRuleId = $rule['id'];

            // 获取产品配置
            $products = $this->db->fetchAll(
                "SELECT * FROM ibApplicationCustomRuleProducts WHERE customRuleId = :customRuleId",
                ['customRuleId' => $customRuleId]
            );
            $rule['products'] = $products;

            // 获取额外规则
            $additionalRules = $this->db->fetchAll(
                "SELECT * FROM ibApplicationCustomRuleAdditionalRules WHERE customRuleId = :customRuleId",
                ['customRuleId' => $customRuleId]
            );

            foreach ($additionalRules as &$ar) {
                // 如果是volume_tiers类型，获取佣金层级
                if ($ar['ruleType'] === 'volume_tiers') {
                    $tiers = $this->db->fetchAll(
                        "SELECT * FROM ibApplicationCustomRuleCommissionTiers WHERE additionalRuleId = :additionalRuleId ORDER BY tierLevel ASC",
                        ['additionalRuleId' => $ar['id']]
                    );
                    $ar['tiers'] = $tiers;
                }
            }

            $rule['additionalRules'] = $additionalRules;
        }

        return $customRules;
    }

    /**
     * 记录状态历史
     */
    public function logStatusHistory($applicationId, $previousStatus, $newStatus, $changedBy, $notes = null) {
        return $this->db->insert('ibApplicationStatusHistory', [
            'applicationId' => $applicationId,
            'previousStatus' => $previousStatus,
            'newStatus' => $newStatus,
            'changedBy' => $changedBy,
            'notes' => $notes
        ]);
    }
}
