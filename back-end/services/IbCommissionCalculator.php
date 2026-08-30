<?php
/**
 * IB佣金计算服务类
 * 实现佣金计算的核心逻辑（阶段2）
 */

require_once __DIR__ . '/../models/BaseModel.php';
require_once __DIR__ . '/../models/IbCommissionCalculation.php';
require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/../models/IbNetworkHierarchy.php';
require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Logger.php';

class IbCommissionCalculator {
    private $db;
    private $commissionModel;
    private $ibPartnerModel;
    private $networkHierarchyModel;
    private $clientUserModel;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->commissionModel = new IbCommissionCalculation();
        $this->ibPartnerModel = new IbPartner();
        $this->networkHierarchyModel = new IbNetworkHierarchy();
        $this->clientUserModel = new ClientUser();
    }

    /**
     * 计算订单的IB佣金（主入口）
     * @param int $orderId 订单ID
     * @param bool $forceRecalculate 是否强制重新计算
     * @return array 计算结果
     */
    public function calculateOrderCommission($orderId, $forceRecalculate = false) {
        try {
            // 1. 获取订单信息
            $order = $this->getOrder($orderId);
            if (!$order) {
//                Logger::error("Order not found: {$orderId}");
                throw new Exception("Order not found: {$orderId}");
            }

            // 2. 检查订单是否已关闭
            if ($order['trading_status'] != 2 || $order['closetime'] <= 0) {
//                Logger::error("Order is not closed yet");
                throw new Exception("Order is not closed yet");
            }

            // 3. 查找客户和直接上级IB代理
            $clientInfo = $this->getClientAndDirectIbPartner($order['trading_login']);
            if (!$clientInfo || !$clientInfo['directIbPartnerId']) {
//                Logger::error("aaaa=Client has no IB partner assigned");
                // 客户没有关联IB代理，不计算佣金
                return [
                    'success' => true,
                    'message' => 'Client has no IB partner assigned',
                    'calculations' => []
                ];
            }

            // 4. 查找所有上级IB代理（包括直接上级）
            $ibHierarchy = $this->getAllParentIbPartners($clientInfo['directIbPartnerId']);
            if (empty($ibHierarchy)) {
//                Logger::error("aaaa=No active IB partners found");
                return [
                    'success' => true,
                    'message' => 'No active IB partners found',
                    'calculations' => []
                ];
            }

            // 5. 检查是否已计算过（除非强制重新计算）
            if (!$forceRecalculate) {
                $existingCalculations = $this->checkExistingCalculations($orderId, $ibHierarchy);
                if (!empty($existingCalculations)) {
//                    Logger::error("aaaa=Commission already calculated");
                    return [
                        'success' => true,
                        'message' => 'Commission already calculated',
                        'calculations' => $existingCalculations
                    ];
                }
            }

            // 6. 计算持仓时长
            $holdDuration = $order['closetime'] - $order['opentime'];

            // 7. 级联计算佣金
            $calculations = $this->calculateCascadingCommission(
                $order,
                $clientInfo,
                $ibHierarchy,
                $holdDuration
            );

            return [
                'success' => true,
                'message' => 'Commission calculated successfully',
                'calculations' => $calculations
            ];

        } catch (Exception $e) {
//            Logger::error("Commission calculation error for order {$orderId}: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'calculations' => []
            ];
        }
    }

    /**
     * 获取订单信息
     */
    private function getOrder($orderId) {
        $sql = "SELECT * FROM orders WHERE id = :orderId LIMIT 1";
        return $this->db->fetchOne($sql, ['orderId' => $orderId]);
    }

    /**
     * 查找客户和直接上级IB代理
     */
    private function getClientAndDirectIbPartner($tradingLogin) {
        $sql = "SELECT
                    cu.id AS clientId,
                    cipa.ibPartnerId AS directIbPartnerId,
                    cipa.referralCode,
                    ib.ibCode,
                    ib.applicationId,
                    ib.userId AS ibClientId,
                    ib.status AS ibStatus
                FROM clientUsers cu
                INNER JOIN tradingAccounts ta ON ta.userId = cu.id
                INNER JOIN tradingAccountExternalAccounts taea ON taea.tradingAccountId = ta.id
                INNER JOIN ib_partner_bind b ON b.childClientId = cu.id AND b.isClient = 1
                INNER JOIN ibPartners ib ON ib.id = b.parentId
                WHERE taea.providerAccountId = :tradingLogin
                  AND ib.status = 'approved'
                LIMIT 1";

        return $this->db->fetchOne($sql, ['tradingLogin' => $tradingLogin]);
    }

    /**
     * 获取所有上级IB代理（递归查询，从直接上级开始向上）
     * 返回数组，第一个是最底层（直接上级），最后一个是最高级
     */
    private function getAllParentIbPartners($directIbPartnerId) {
        $hierarchy = [];
        $visited = [];

        // 从直接上级IB代理开始
        $currentIbId = $directIbPartnerId;
        $tierLevel = 1; // 从第1层开始（直接上级）

        while ($currentIbId) {
            // 防止循环引用
            if (isset($visited[$currentIbId])) {
//                Logger::error("Circular reference detected in IB hierarchy: {$currentIbId}");
                break;
            }
            $visited[$currentIbId] = true;

            // 获取当前IB代理信息
            $ibPartner = $this->ibPartnerModel->findById($currentIbId);
            if (!$ibPartner || $ibPartner['status'] != 'approved') {
                break;
            }

            // 获取层级信息
            $parentInfo = $this->networkHierarchyModel->getParentIbPartner($currentIbId);
            $hierarchyLevel = $parentInfo ? (int)$parentInfo['hierarchyLevel'] : $tierLevel;

            // 添加到层级列表（从最底层开始）
            $hierarchy[] = [
                'id' => $ibPartner['id'],
                'ibCode' => $ibPartner['ibCode'],
                'applicationId' => $ibPartner['applicationId'],
                'userId' => $ibPartner['userId'],
                'tierLevel' => $hierarchyLevel
            ];

            // 查找父级IB代理
            if (!$parentInfo || !$parentInfo['parentIbPartnerId']) {
                break;
            }

            $currentIbId = $parentInfo['parentIbPartnerId'];
            $tierLevel++;
        }

        return $hierarchy;
    }

    /**
     * 检查是否已计算过佣金
     */
    private function checkExistingCalculations($orderId, $ibHierarchy) {
        $ibPartnerIds = array_column($ibHierarchy, 'id');
        if (empty($ibPartnerIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ibPartnerIds), '?'));
        $sql = "SELECT * FROM ibCommissionCalculations
                WHERE orderId = ? AND ibPartnerId IN ({$placeholders})";

        $params = array_merge([$orderId], $ibPartnerIds);
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * 级联计算佣金
     */
    private function calculateCascadingCommission($order, $clientInfo, $ibHierarchy, $holdDuration) {
        $calculations = [];
        $subordinateExtractedCommission = null;
        $subordinateIbPartnerId = null;

        // 从最底层开始计算（第一个是直接上级）
        foreach ($ibHierarchy as $index => $ibPartner) {
            try {
                // 检查最小持仓时间
                if (!$this->checkMinHoldDuration($holdDuration)) {
//                    Logger::error("IB {$ibPartner['id']} (applicationId: {$ibPartner['applicationId']}) 持仓时间不满足要求: {$holdDuration}秒");
                    continue; // 跳过该IB代理
                }

                // 获取所有佣金规则（支持多个规则）
                $rules = $this->getCommissionRules($ibPartner['applicationId']);
                if (empty($rules)) {
//                    Logger::error("IB {$ibPartner['id']} (applicationId: {$ibPartner['applicationId']}) 没有找到active的佣金规则");
                    continue; // 没有规则，跳过
                }

                // 尝试所有规则，找到第一个有匹配产品规则的规则
                $rule = null;
                $result = null;
                foreach ($rules as $ruleCandidate) {
                    $result = $this->calculateBaseCommission($order, $ruleCandidate, $ibPartner, $clientInfo);
                    if ($result) {
                        $rule = $ruleCandidate;
                        break; // 找到匹配的规则，退出循环
                    }
                }

                if (!$rule || !$result) {
//                    Logger::error("IB {$ibPartner['id']} (applicationId: {$ibPartner['applicationId']}) 所有规则都没有匹配的产品规则（symbol: {$order['symbol']}）");
                    continue;
                }

                if ($index === 0) {
                    // 最底层IB代理：计算原始佣金

                    $originalCommission = $result['totalCommission'];
                    $extractedCommission = 0; // 暂时为0，稍后会被上级提取
                    $finalCommission = $originalCommission; // 暂时等于原始佣金

                    $subordinateExtractedCommission = $originalCommission; // 用于上级提取
                    $subordinateIbPartnerId = $ibPartner['id'];

                    // 保存计算记录
                    $calculationId = $this->saveCommissionCalculation(
                        $order,
                        $ibPartner,
                        $clientInfo,
                        $holdDuration,
                        $rule,
                        $result,
                        $originalCommission,
                        $extractedCommission,
                        $finalCommission
                    );

                    $calculations[] = [
                        'ibPartnerId' => $ibPartner['id'],
                        'ibCode' => $ibPartner['ibCode'],
                        'calculationId' => $calculationId,
                        'originalCommission' => $originalCommission,
                        'extractedCommission' => $extractedCommission,
                        'finalCommission' => $finalCommission
                    ];

                } else {
                    // 上级IB代理：从下级的提取佣金中提取
                    // 注意：上级IB代理不需要匹配产品规则，只需要从规则中获取extractionRate
                    // 如果当前规则没有extractionRate，尝试其他规则
                    $extractionRule = $rule;
                    if (empty($rule['extractionRate']) || (float)$rule['extractionRate'] <= 0) {
                        // 当前规则没有extractionRate，尝试其他规则
                        foreach ($rules as $ruleCandidate) {
                            if (!empty($ruleCandidate['extractionRate']) && (float)$ruleCandidate['extractionRate'] > 0) {
                                $extractionRule = $ruleCandidate;
                                break;
                            }
                        }
                    }

                    $extractedCommission = $this->calculateExtraction(
                        $subordinateExtractedCommission,
                        $extractionRule
                    );
                    $finalCommission = $extractedCommission;

                    // 更新下级IB代理的提取佣金和最终佣金
                    $this->updateSubordinateCommission(
                        $order['id'],
                        $subordinateIbPartnerId,
                        $extractedCommission
                    );

                    // 保存上级IB代理的计算记录
                    // 注意：使用extractionRule（可能和rule不同，如果rule没有extractionRate）
                    $calculationId = $this->saveCommissionCalculation(
                        $order,
                        $ibPartner,
                        $clientInfo,
                        $holdDuration,
                        $extractionRule,
                        null, // 上级没有基础佣金计算详情
                        0, // originalCommission = 0
                        $extractedCommission,
                        $finalCommission
                    );

                    $calculations[] = [
                        'ibPartnerId' => $ibPartner['id'],
                        'ibCode' => $ibPartner['ibCode'],
                        'calculationId' => $calculationId,
                        'originalCommission' => 0,
                        'extractedCommission' => $extractedCommission,
                        'finalCommission' => $finalCommission
                    ];

                    // 更新变量，用于下一级计算
                    $subordinateExtractedCommission = $extractedCommission;
                    $subordinateIbPartnerId = $ibPartner['id'];
                }

            } catch (Exception $e) {
//                Logger::error("Error calculating commission for IB {$ibPartner['id']}: " . $e->getMessage());
                continue; // 继续处理下一个IB代理
            }
        }

        return $calculations;
    }

    /**
     * 检查最小持仓时间
     */
    private function checkMinHoldDuration($holdDuration) {
        $sql = "SELECT settingValue FROM ibProgramSettings
                WHERE settingKey = 'min_position_hold_seconds'
                LIMIT 1";
        $result = $this->db->fetchOne($sql, []);
        $minHoldDuration = (int)($result['settingValue'] ?? 0);

        // 如果设置为0，表示不检查最小持仓时间
        if ($minHoldDuration == 0) {
            return true;
        }

        return $holdDuration >= $minHoldDuration;
    }

    /**
     * 获取佣金规则（支持多个规则，返回所有active规则）
     */
    private function getCommissionRules($applicationId) {
        $sql = "SELECT * FROM ibApplicationCustomRules
                WHERE applicationId = :applicationId
                  AND status = 'active'
                ORDER BY createdAt DESC";

        return $this->db->fetchAll($sql, ['applicationId' => $applicationId]);
    }

    /**
     * 获取佣金规则（单个，兼容旧代码，已废弃，使用getCommissionRules）
     * @deprecated 使用 getCommissionRules() 替代
     */
    private function getCommissionRule($applicationId) {
        $rules = $this->getCommissionRules($applicationId);
        return !empty($rules) ? $rules[0] : null;
    }

    /**
     * 计算基础佣金
     */
    private function calculateBaseCommission($order, $rule, $ibPartner, $clientInfo) {
        // 1. 匹配产品规则
        $productRule = $this->matchProductRule($rule['id'], $order['symbol']);
        if (!$productRule) {
            return null; // 没有匹配的产品规则
        }

        // 2. 根据佣金类型计算基础佣金
        $baseCommission = $this->calculateByCommissionType(
            $order,
            $productRule
        );

        // 3. 应用额外规则
        $additionalCommission = $this->applyAdditionalRules(
            $order,
            $rule['id'],
            $productRule,
            $ibPartner['id'],
            $baseCommission
        );

        $totalCommission = $baseCommission + $additionalCommission;

        return [
            'baseCommission' => $baseCommission,
            'additionalCommission' => $additionalCommission,
            'totalCommission' => $totalCommission,
            'productRule' => $productRule,
            'commissionType' => $productRule['commissionType']
        ];
    }

    /**
     * 匹配产品规则
     */
    private function matchProductRule($customRuleId, $symbol) {
        // 首先尝试精确匹配 Symbol
        $sql = "SELECT * FROM ibApplicationCustomRuleProducts
                WHERE customRuleId = :customRuleId
                  AND productType = 'symbol'
                  AND productName = :symbol
                LIMIT 1";

        $result = $this->db->fetchOne($sql, [
            'customRuleId' => $customRuleId,
            'symbol' => $symbol
        ]);

        if ($result) {
            return $result;
        }

        // 记录调试信息：没有找到匹配的产品规则
//        Logger::error("没有找到匹配的产品规则: customRuleId={$customRuleId}, symbol={$symbol}, productType=symbol");

        // 如果没找到，尝试匹配 Security
        // 新的表关系：ibCustomSymbols.securityId -> ibCustomSecurities.id
        // 通过symbol找到对应的security，然后匹配productName
        $sql = "SELECT p.*
                FROM ibApplicationCustomRuleProducts p
                INNER JOIN ibCustomSecurities sec ON sec.securityName = p.productName
                INNER JOIN ibCustomSymbols cs ON cs.securityId = sec.id
                WHERE p.customRuleId = :customRuleId
                  AND p.productType = 'security'
                  AND cs.symbolName = :symbol
                LIMIT 1";

        $result = $this->db->fetchOne($sql, [
            'customRuleId' => $customRuleId,
            'symbol' => $symbol
        ]);

//        if (!$result) {
//            // 记录调试信息：也没有找到Security匹配
//            Logger::error("也没有找到Security匹配的产品规则: customRuleId={$customRuleId}, symbol={$symbol}, productType=security");
//        }

        return $result;
    }

    /**
     * 根据佣金类型计算基础佣金
     */
    private function calculateByCommissionType($order, $productRule) {
        $volume = $this->resolveOrderLots($order);
        $commissionType = $productRule['commissionType'];
        $commissionRate = (float)$productRule['commissionRate'];
        $additionalRate = (float)($productRule['additionalRate'] ?? 0);

        switch ($commissionType) {
            case 'per_lot':
                // Per Lot: Rate × Volume
                $baseCommission = $commissionRate * $volume;
                // 如果满足额外条件（如月度交易量 > threshold），可以加上 additionalRate
                // 这里暂时不处理，在 applyAdditionalRules 中处理
                break;

            case 'percentage':
                // Percentage: (Spread × Rate%) × Volume，然后应用最小/最大限制
                $spread = $this->calculateSpread($order);
                $baseCommission = ($spread * ($commissionRate / 100)) * $volume;

                // 应用最小/最大限制
                $minCommission = $additionalRate; // additionalRate 作为最小值
                // 最大值可能需要从 ruleCondition 中获取，这里暂时使用 additionalRate * 2
                $maxCommission = $additionalRate * 2;
                if ($maxCommission > 0) {
                    $baseCommission = max($minCommission, min($baseCommission, $maxCommission));
                } else {
                    $baseCommission = max($minCommission, $baseCommission);
                }
                break;

            case 'per_trade':
                // Per Trade: 每笔交易固定金额
                $baseCommission = $commissionRate;
                // 如果满足额外条件，可以加上 additionalRate
                break;

            case 'cashback':
                // Cash Back: Rate × Volume
                $baseCommission = $commissionRate * $volume;
                break;

            case 'hybrid':
                // Hybrid: (Rate × Volume) + (Spread × AdditionalRate% × Volume)
                $spread = $this->calculateSpread($order);
                $baseCommission = ($commissionRate * $volume)
                                + ($spread * ($additionalRate / 100) * $volume);
                break;

            default:
                $baseCommission = 0;
        }

        return max(0, $baseCommission); // 确保不为负数
    }

    /**
     * 计算点差（Spread）
     * 公式：Spread = (Ask - Bid) × 10^digits
     *
     * 异常处理策略（参考正常交易平台的处理方式）：
     * 1. 如果 ask 或 bid 为 NULL，使用备用方案（从 openprice/closeprice 估算）
     * 2. 如果计算结果为负数，使用绝对值（理论上 ask 应该 >= bid）
     * 3. 如果计算结果为 0，使用最小点差（0.000001）避免除零错误
     * 4. 如果计算结果异常大（超过合理范围），设置上限并记录警告
     * 5. 记录异常情况到日志，便于排查数据问题
     */
    private function calculateSpread($order) {
        $ask = isset($order['ask']) ? (float)$order['ask'] : null;
        $bid = isset($order['bid']) ? (float)$order['bid'] : null;
        $digits = isset($order['digits']) ? (int)$order['digits'] : 0;

        // 情况1：ask 和 bid 都存在，使用标准公式计算
        if ($ask !== null && $bid !== null && $ask > 0 && $bid > 0) {
            // 计算原始点差：Ask - Bid
            $rawSpread = $ask - $bid;

            // 如果为负数，使用绝对值（数据异常，但继续处理）
            if ($rawSpread < 0) {
//                Logger::error("订单 {$order['id']} 的 Ask ({$ask}) < Bid ({$bid})，使用绝对值计算 Spread");
                $rawSpread = abs($rawSpread);
            }

            // 应用 digits 倍数：Spread = (Ask - Bid) × 10^digits
            $spread = $rawSpread * pow(10, $digits);

            // 验证计算结果
            if ($spread <= 0) {
                // 如果计算结果为 0 或负数，使用最小点差
//                Logger::error("订单 {$order['id']} 计算出的 Spread 为 0 或负数 ({$spread})，使用最小点差 0.000001");
                return 0.000001;
            }

            // 检查异常大的值（超过 1000000，可能是数据错误或 digits 设置错误）
            if ($spread > 1000000) {
//                Logger::error("订单 {$order['id']} 计算出的 Spread 异常大 ({$spread})，可能是 digits ({$digits}) 设置错误，限制为 1000000");
                return 1000000;
            }

            return $spread;
        }

        // 情况2：ask 或 bid 缺失，使用备用方案
        // 备用方案：从 openprice 和 closeprice 估算点差（不准确，但比返回 0 好）
        if (isset($order['openprice']) && isset($order['closeprice'])
            && $order['openprice'] > 0 && $order['closeprice'] > 0) {
            $estimatedSpread = abs((float)$order['closeprice'] - (float)$order['openprice']);

            // 应用 digits 倍数（如果 digits 有效）
            if ($digits > 0) {
                $estimatedSpread = $estimatedSpread * pow(10, $digits);
            }

            // 如果估算值仍然为 0，使用最小点差
            if ($estimatedSpread <= 0) {
//                Logger::error("订单 {$order['id']} 无法计算 Spread（Ask/Bid 缺失，估算值也为 0），使用最小点差 0.000001");
                return 0.000001;
            }

//            Logger::error("订单 {$order['id']} 的 Ask/Bid 缺失，使用 openprice/closeprice 估算 Spread: {$estimatedSpread}");
            return $estimatedSpread;
        }

        // 情况3：所有数据都缺失，返回最小点差并记录错误
//        Logger::error("订单 {$order['id']} 无法计算 Spread：Ask/Bid 缺失且 openprice/closeprice 也缺失或无效，使用最小点差 0.000001");
        return 0.000001;
    }

    /**
     * 应用额外规则
     */
    private function applyAdditionalRules($order, $customRuleId, $productRule, $ibPartnerId, $baseCommission) {
        $additionalCommission = 0;
        $volume = $this->resolveOrderLots($order);

        // 获取该规则的所有额外规则
        $sql = "SELECT * FROM ibApplicationCustomRuleAdditionalRules
                WHERE customRuleId = :customRuleId
                  AND isActive = 1";

        $additionalRules = $this->db->fetchAll($sql, ['customRuleId' => $customRuleId]);

        foreach ($additionalRules as $rule) {
            $ruleType = $rule['ruleType'];
            $ruleValue = (float)$rule['ruleValue'];
            $ruleCondition = json_decode($rule['ruleCondition'] ?? '{}', true);

            switch ($ruleType) {
                case 'bonus_commission':
                    // Bonus Commission: 检查月度交易量 > threshold
                    $monthlyVolume = $this->getMonthlyVolume($ibPartnerId, $order);
                    $threshold = (float)($ruleCondition['threshold'] ?? 0);
                    if ($monthlyVolume > $threshold) {
                        $additionalCommission += $ruleValue * $volume;
                    }
                    break;

                case 'volume_tiers':
                    // Volume-based Tiers: 根据月度交易量查找对应的层级
                    $monthlyVolume = $this->getMonthlyVolume($ibPartnerId, $order);
                    $tier = $this->findTierByVolume($monthlyVolume, $rule['id']);
                    if ($tier) {
                        // 注意：可能需要替换 baseCommission，而不是叠加
                        // 这里暂时叠加，具体业务逻辑需要确认
                        $additionalCommission += $tier['commissionRate'] * $volume;
                    }
                    break;

                case 'volume_multiplier':
                    // Volume Multiplier: 检查月度交易量 > threshold
                    $monthlyVolume = $this->getMonthlyVolume($ibPartnerId, $order);
                    $threshold = (float)($ruleCondition['threshold'] ?? 0);
                    if ($monthlyVolume > $threshold) {
                        $multiplier = $ruleValue; // 如 1.25
                        $additionalCommission += ($baseCommission * $multiplier) - $baseCommission;
                    }
                    break;

                case 'performance_bonus':
                    // Performance Bonus: 检查性能指标
                    if ($this->checkPerformanceCriteria($ibPartnerId, $ruleCondition)) {
                        $bonus = $baseCommission * ($ruleValue / 100); // ruleValue 是百分比
                        $additionalCommission += $bonus;
                    }
                    break;

                case 'cash_rebate':
                    // Cash Rebate: 检查条件
                    if ($this->checkRebateCondition($order, $ruleCondition)) {
                        $additionalCommission += $ruleValue * $volume;
                    }
                    break;
            }
        }

        return max(0, $additionalCommission);
    }

    /**
     * 获取月度交易量
     */
    private function getMonthlyVolume($ibPartnerId, $order) {
        // 获取订单所在月份的开始和结束时间
        $orderCloseTime = (int)$order['closetime'];
        $monthStart = date('Y-m-01 00:00:00', $orderCloseTime);
        $monthEnd = date('Y-m-t 23:59:59', $orderCloseTime);

        $sql = "SELECT COALESCE(SUM(volume), 0) as totalVolume
                FROM ibCommissionCalculations
                WHERE ibPartnerId = :ibPartnerId
                  AND orderCloseTime >= :monthStart
                  AND orderCloseTime <= :monthEnd
                  AND calculationStatus != 'cancelled'";

        $result = $this->db->fetchOne($sql, [
            'ibPartnerId' => $ibPartnerId,
            'monthStart' => strtotime($monthStart),
            'monthEnd' => strtotime($monthEnd)
        ]);

        return (float)($result['totalVolume'] ?? 0);
    }

    /**
     * 根据交易量查找对应的层级
     */
    private function findTierByVolume($monthlyVolume, $additionalRuleId) {
        $sql = "SELECT * FROM ibApplicationCustomRuleCommissionTiers
                WHERE additionalRuleId = :additionalRuleId
                  AND minimumVolume <= :volume
                  AND (maximumVolume IS NULL OR maximumVolume = 'Unlimited' OR maximumVolume >= :volume)
                ORDER BY tierLevel DESC
                LIMIT 1";

        return $this->db->fetchOne($sql, [
            'additionalRuleId' => $additionalRuleId,
            'volume' => $monthlyVolume
        ]);
    }

    /**
     * 检查性能指标
     */
    private function checkPerformanceCriteria($ibPartnerId, $ruleCondition) {
        // 这里需要根据业务需求实现性能指标检查
        // 例如：排名前10%、留存率、增长率等
        // 暂时返回 false，需要后续实现
        return false;
    }

    /**
     * 检查返现条件
     */
    private function checkRebateCondition($order, $ruleCondition) {
        // 这里需要根据业务需求实现返现条件检查
        // 例如：所有交易、交易量阈值、客户类型等
        // 暂时返回 true（所有交易都返现）
        return true;
    }

    /**
     * 计算提取佣金（上级从下级提取）
     */
    private function calculateExtraction($subordinateExtractedCommission, $rule) {
        // 从规则中获取提取比例（如果规则支持）
        // 注意：这里需要从 ibApplicationCustomRules 表中获取 extractionRate 和 extractionType
        // 如果表中没有这些字段，需要先添加

        $extractionType = 'percentage'; // 默认按比例
        $extractionRate = 0; // 默认不提取

        // 尝试从规则中获取提取配置
        if (isset($rule['extractionRate'])) {
            $extractionRate = (float)$rule['extractionRate'];
        }
        if (isset($rule['extractionType'])) {
            $extractionType = $rule['extractionType'];
        }

        if ($extractionRate <= 0) {
            return 0; // 不提取
        }

        if ($extractionType === 'percentage') {
            // 按比例提取
            $extractionAmount = $subordinateExtractedCommission * ($extractionRate / 100);
        } else {
            // 固定金额提取
            $extractionAmount = $extractionRate;
        }

        // 确保提取金额不超过下级的提取佣金
        return min($extractionAmount, $subordinateExtractedCommission);
    }

    /**
     * 更新下级IB代理的提取佣金和最终佣金
     */
    private function updateSubordinateCommission($orderId, $subordinateIbPartnerId, $extractedCommission) {
        $sql = "UPDATE ibCommissionCalculations
                SET extractedCommission = :extractedCommission,
                    finalCommission = originalCommission - :extractedCommission
                WHERE orderId = :orderId
                  AND ibPartnerId = :ibPartnerId";

        $stmt = $this->db->query($sql, [
            'extractedCommission' => $extractedCommission,
            'orderId' => $orderId,
            'ibPartnerId' => $subordinateIbPartnerId
        ]);

        return $stmt ? $stmt->rowCount() : 0;
    }

    /**
     * 保存佣金计算记录
     */
    private function saveCommissionCalculation(
        $order,
        $ibPartner,
        $clientInfo,
        $holdDuration,
        $rule,
        $calculationResult,
        $originalCommission,
        $extractedCommission,
        $finalCommission
    ) {
        // 计算支付周期和统计周期
        $paymentCycle = $rule['paymentCycle'] ?? 'monthly';
        $orderCloseTime = (int)$order['closetime'];
        $periodStart = date('Y-m-01', $orderCloseTime);
        $periodEnd = date('Y-m-t', $orderCloseTime);
        $volume = $this->resolveOrderLots($order);

        // 构建计算详情（JSON格式）
        $calculationDetails = [
            'orderId' => $order['id'],
            'symbol' => $order['symbol'],
            'volume' => $volume,
            'holdDuration' => $holdDuration,
            'commissionType' => $calculationResult['commissionType'] ?? null,
            'baseCommission' => $calculationResult['baseCommission'] ?? 0,
            'additionalCommission' => $calculationResult['additionalCommission'] ?? 0,
            'totalCommission' => $calculationResult['totalCommission'] ?? $finalCommission,
            'calculatedAt' => date('Y-m-d H:i:s')
        ];

        // 获取 symbolId（优先从orders表的symbol_id字段获取，如果为0或NULL则通过symbol名称查询）
        $symbolId = null;

        // 检查orders表中的symbol_id字段（可能是symbol_id或symbolId）
        $orderSymbolId = null;
        if (isset($order['symbol_id'])) {
            $orderSymbolId = $order['symbol_id'];
        } elseif (isset($order['symbolId'])) {
            $orderSymbolId = $order['symbolId'];
        }

        if ($orderSymbolId !== null && $orderSymbolId !== '' && (int)$orderSymbolId > 0) {
            // 优先使用orders表中的symbol_id字段
            $symbolId = (int)$orderSymbolId;
//            Logger::info("使用orders表的symbol_id字段: orderId={$order['id']}, symbol_id={$symbolId}");
        } elseif (isset($order['symbol']) && !empty($order['symbol'])) {
            // 如果symbol_id为0或NULL，则通过symbol名称查询ibCustomSymbols表
            $symbolSql = "SELECT id FROM ibCustomSymbols WHERE symbolName = :symbol LIMIT 1";
            $symbolResult = $this->db->fetchOne($symbolSql, ['symbol' => $order['symbol']]);
            if ($symbolResult && isset($symbolResult['id'])) {
                $symbolId = (int)$symbolResult['id'];
//                Logger::info("通过symbol名称查询到symbolId: orderId={$order['id']}, symbol={$order['symbol']}, symbolId={$symbolId}");
            }
//            else {
//                Logger::error("无法找到symbolId: orderId={$order['id']}, symbol={$order['symbol']}, orders.symbol_id={$orderSymbolId}");
//            }
        }
//        else {
//            Logger::error("订单缺少symbol字段: orderId={$order['id']}, orders.symbol_id={$orderSymbolId}");
//        }

        $data = [
            'orderId' => $order['id'],
            'ibPartnerId' => $ibPartner['id'],
            'tierLevel' => (int)($ibPartner['tierLevel'] ?? 0),
            'clientId' => $clientInfo['clientId'],
            'tradingLogin' => $order['trading_login'],
            'symbolId' => $symbolId,
            'symbol' => $order['symbol'],
            'volume' => $volume,
            'orderOpenTime' => (int)$order['opentime'],
            'orderCloseTime' => (int)$order['closetime'],
            'holdDuration' => $holdDuration,
            'spread' => $this->calculateSpread($order),
            'originalCommission' => $originalCommission,
            'extractedCommission' => $extractedCommission,
            'finalCommission' => $finalCommission,
            'commissionType' => $calculationResult['commissionType'] ?? null,
            'ruleId' => $rule['id'],
            'productRuleId' => $calculationResult['productRule']['id'] ?? null,
            'additionalRuleId' => null, // 暂时为null，后续可以从额外规则中获取
            'calculationDetails' => json_encode($calculationDetails),
            'calculationStatus' => 'calculated',
            'paymentCycle' => $paymentCycle,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'calculatedAt' => date('Y-m-d H:i:s')
        ];

        return $this->commissionModel->create($data);
    }

    /**
     * 订单同步后 volume 使用平台原始单位保存，100 代表 1 lot。
     */
    private function resolveOrderLots($order) {
        $rawVolume = isset($order['volume']) && is_numeric($order['volume'])
            ? (float)$order['volume']
            : 0.0;

        return $rawVolume / 100;
    }
}
