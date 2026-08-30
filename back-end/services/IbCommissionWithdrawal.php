<?php
/**
 * IB佣金提现服务类
 * 实现佣金提现到钱包的核心逻辑
 */

require_once __DIR__ . '/../models/BaseModel.php';
require_once __DIR__ . '/../models/IbCommissionCalculation.php';
require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Logger.php';

class IbCommissionWithdrawal {
    private $db;
    private $commissionModel;
    private $ibPartnerModel;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->commissionModel = new IbCommissionCalculation();
        $this->ibPartnerModel = new IbPartner();
    }

    /**
     * 处理佣金提现（主入口）
     * 根据支付周期自动提现到期的佣金
     */
    public function processWithdrawals() {
        $results = [];

        // 获取所有需要提现的IB代理（按支付周期分组）
        $paymentCycles = ['realtime', 'daily', 'weekly', 'biweekly', 'monthly', 'quarterly'];

        foreach ($paymentCycles as $paymentCycle) {
            // -----------------------------------------------------------------
            // realtime：本类原逻辑依赖 ibCommissionCalculations 等「旧按周期提现」体系。
            // 现「实时」佣金已改为：ib_commission_order 在 CommissionOrderService 中按规则
            // paymentCycle=realtime 生成时直接 status=completed（记入 CRM 钱包可用余额）。
            // 此处不再对 realtime 跑下方提现循环，避免与新口径重复；若未来改回按周期打款，可去掉本 continue。
            // -----------------------------------------------------------------
            if ($paymentCycle === 'realtime') {
                continue;
            }

            try {
                $ibPartners = $this->getIbPartnersForWithdrawal($paymentCycle);

                foreach ($ibPartners as $ibPartner) {
                    try {
                        $result = $this->processIbPartnerWithdrawal($ibPartner, $paymentCycle);
                        if ($result['success']) {
                            $results[] = $result;
                        }
                    } catch (Exception $e) {
                        Logger::error("Failed to process withdrawal for IB {$ibPartner['id']}: " . $e->getMessage());
                        $results[] = [
                            'success' => false,
                            'ibPartnerId' => $ibPartner['id'],
                            'ibCode' => $ibPartner['ibCode'],
                            'message' => $e->getMessage()
                        ];
                    }
                }
            } catch (Exception $e) {
                Logger::error("Failed to process withdrawals for cycle {$paymentCycle}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * 获取需要提现的IB代理列表
     */
    private function getIbPartnersForWithdrawal($paymentCycle) {
        $now = date('Y-m-d');

        // 根据支付周期计算周期结束日期
        $periodEnd = $this->calculatePeriodEnd($paymentCycle, $now);

        if (!$periodEnd) {
            return []; // 未到提现时间
        }

        $periodStart = $this->calculatePeriodStart($paymentCycle, $periodEnd);

        // 查找有未提现佣金的IB代理
        $sql = "SELECT DISTINCT
                    ib.id,
                    ib.ibCode,
                    ib.userId,
                    ib.applicationId,
                    r.minimumPayout,
                    r.paymentCycle
                FROM ibPartners ib
                INNER JOIN ibApplications app ON app.id = ib.applicationId
                INNER JOIN ibApplicationCustomRules r ON r.applicationId = app.id
                WHERE ib.status = 'approved'
                  AND r.status = 'active'
                  AND r.paymentCycle = :paymentCycle
                  AND EXISTS (
                      SELECT 1 FROM ibCommissionCalculations icc
                      WHERE icc.ibPartnerId = ib.id
                        AND icc.calculationStatus = 'calculated'
                        AND icc.paymentCycle = :paymentCycle
                        AND icc.periodStart = :periodStart
                        AND icc.periodEnd = :periodEnd
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM ibCommissionWithdrawals icw
                      WHERE icw.ibPartnerId = ib.id
                        AND icw.paymentCycle = :paymentCycle
                        AND icw.periodStart = :periodStart
                        AND icw.periodEnd = :periodEnd
                        AND icw.status IN ('completed', 'processing')
                  )";

        return $this->db->fetchAll($sql, [
            'paymentCycle' => $paymentCycle,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd
        ]);
    }

    /**
     * 计算周期结束日期
     */
    private function calculatePeriodEnd($paymentCycle, $currentDate) {
        $today = strtotime($currentDate);

        switch ($paymentCycle) {
            case 'realtime':
                // 实时：每天提现
                return date('Y-m-d', $today);

            case 'daily':
                // 每日：每天提现（前一天的数据）
                return date('Y-m-d', strtotime('-1 day', $today));

            case 'weekly':
                // 每周：每周一提现上周的数据
                $dayOfWeek = date('w', $today); // 0=Sunday, 1=Monday
                if ($dayOfWeek == 1) { // 周一
                    return date('Y-m-d', strtotime('-1 day', $today)); // 上周日
                }
                return null;

            case 'biweekly':
                // 每两周：每两周的周一提现
                $dayOfWeek = date('w', $today);
                $dayOfMonth = date('j', $today);
                if ($dayOfWeek == 1 && ($dayOfMonth <= 7 || ($dayOfMonth >= 15 && $dayOfMonth <= 21))) {
                    return date('Y-m-d', strtotime('-1 day', $today));
                }
                return null;

            case 'monthly':
                // 每月：每月1号提现上个月的数据
                if (date('j', $today) == 1) {
                    return date('Y-m-t', strtotime('-1 month', $today)); // 上个月最后一天
                }
                return null;

            case 'quarterly':
                // 每季度：每季度第一个月的1号提现上季度的数据
                $month = date('n', $today);
                $day = date('j', $today);
                if (in_array($month, [1, 4, 7, 10]) && $day == 1) {
                    // 计算上季度最后一天
                    $lastMonth = $month - 1;
                    if ($lastMonth == 0) $lastMonth = 12;
                    $lastYear = date('Y', strtotime('-1 month', $today));
                    return date('Y-m-t', mktime(0, 0, 0, $lastMonth, 1, $lastYear));
                }
                return null;

            default:
                return null;
        }
    }

    /**
     * 计算周期开始日期
     */
    private function calculatePeriodStart($paymentCycle, $periodEnd) {
        $endDate = strtotime($periodEnd);

        switch ($paymentCycle) {
            case 'realtime':
            case 'daily':
                return date('Y-m-d', $endDate);

            case 'weekly':
                return date('Y-m-d', strtotime('-6 days', $endDate));

            case 'biweekly':
                return date('Y-m-d', strtotime('-13 days', $endDate));

            case 'monthly':
                return date('Y-m-01', $endDate);

            case 'quarterly':
                $month = date('n', $endDate);
                $year = date('Y', $endDate);
                // 计算季度第一个月
                if ($month <= 3) {
                    $quarterStartMonth = 1;
                } elseif ($month <= 6) {
                    $quarterStartMonth = 4;
                } elseif ($month <= 9) {
                    $quarterStartMonth = 7;
                } else {
                    $quarterStartMonth = 10;
                }
                return date('Y-m-01', mktime(0, 0, 0, $quarterStartMonth, 1, $year));

            default:
                return date('Y-m-01', $endDate);
        }
    }

    /**
     * 处理单个IB代理的佣金提现
     */
    private function processIbPartnerWithdrawal($ibPartner, $paymentCycle) {
        $now = date('Y-m-d');
        $periodEnd = $this->calculatePeriodEnd($paymentCycle, $now);
        $periodStart = $this->calculatePeriodStart($paymentCycle, $periodEnd);

        // 获取未提现的佣金总额
        $totalCommission = $this->getUnwithdrawnCommission(
            $ibPartner['id'],
            $paymentCycle,
            $periodStart,
            $periodEnd
        );

        if ($totalCommission <= 0) {
            return [
                'success' => false,
                'ibPartnerId' => $ibPartner['id'],
                'ibCode' => $ibPartner['ibCode'],
                'message' => 'No commission to withdraw'
            ];
        }

        // 检查最小提现金额
        $minimumPayout = (float)($ibPartner['minimumPayout'] ?? 0);
        if ($minimumPayout > 0 && $totalCommission < $minimumPayout) {
            return [
                'success' => false,
                'ibPartnerId' => $ibPartner['id'],
                'ibCode' => $ibPartner['ibCode'],
                'message' => "Commission ({$totalCommission}) is less than minimum payout ({$minimumPayout})"
            ];
        }

        // 创建提现记录
        $withdrawal = $this->createCommissionWithdrawal(
            $ibPartner,
            $totalCommission,
            $paymentCycle,
            $periodStart,
            $periodEnd
        );

        if (!$withdrawal) {
            throw new Exception('Failed to create withdrawal record');
        }

        // 在deposits表创建记录，使钱包余额自动增加。
        $depositId = $this->createCommissionDeposit(
            $ibPartner,
            $totalCommission,
            $withdrawal['id'],
            $withdrawal['transactionId']
        );

        if (!$depositId) {
            // 更新提现记录状态为失败
            $this->updateWithdrawalStatus($withdrawal['id'], 'failed', 'Failed to create deposit record');
            throw new Exception('Failed to create deposit record');
        }

        // 更新佣金计算记录状态
        $this->updateCommissionCalculationsStatus(
            $ibPartner['id'],
            $withdrawal['id'],
            $paymentCycle,
            $periodStart,
            $periodEnd
        );

        // 更新提现记录状态为完成
        $this->updateWithdrawalStatus($withdrawal['id'], 'completed', null);

        return [
            'success' => true,
            'ibPartnerId' => $ibPartner['id'],
            'ibCode' => $ibPartner['ibCode'],
            'withdrawalId' => $withdrawal['id'],
            'depositId' => $depositId,
            'totalAmount' => $totalCommission,
            'paymentCycle' => $paymentCycle,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd
        ];
    }

    /**
     * 获取未提现的佣金总额
     */
    private function getUnwithdrawnCommission($ibPartnerId, $paymentCycle, $periodStart, $periodEnd) {
        $sql = "SELECT COALESCE(SUM(finalCommission), 0) as total
                FROM ibCommissionCalculations
                WHERE ibPartnerId = :ibPartnerId
                  AND calculationStatus = 'calculated'
                  AND paymentCycle = :paymentCycle
                  AND periodStart = :periodStart
                  AND periodEnd = :periodEnd";

        $result = $this->db->fetchOne($sql, [
            'ibPartnerId' => $ibPartnerId,
            'paymentCycle' => $paymentCycle,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd
        ]);

        return (float)($result['total'] ?? 0);
    }

    /**
     * 创建佣金提现记录
     */
    private function createCommissionWithdrawal($ibPartner, $totalAmount, $paymentCycle, $periodStart, $periodEnd) {
        // 生成唯一交易ID
        $transactionId = 'COMM-' . date('Ymd') . '-' . str_pad($ibPartner['id'], 5, '0', STR_PAD_LEFT) . '-' . time();

        // 获取佣金计算记录数量
        $calculationCount = $this->getCalculationCount(
            $ibPartner['id'],
            $paymentCycle,
            $periodStart,
            $periodEnd
        );

        $data = [
            'ibPartnerId' => $ibPartner['id'],
            'transactionId' => $transactionId,
            'totalAmount' => $totalAmount,
            'paymentCycle' => $paymentCycle,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'calculationCount' => $calculationCount,
            'status' => 'processing'
        ];

        $withdrawalId = $this->db->insert('ibCommissionWithdrawals', $data);

        return [
            'id' => $withdrawalId,
            'transactionId' => $transactionId
        ];
    }

    /**
     * 获取佣金计算记录数量
     */
    private function getCalculationCount($ibPartnerId, $paymentCycle, $periodStart, $periodEnd) {
        $sql = "SELECT COUNT(*) as count
                FROM ibCommissionCalculations
                WHERE ibPartnerId = :ibPartnerId
                  AND calculationStatus = 'calculated'
                  AND paymentCycle = :paymentCycle
                  AND periodStart = :periodStart
                  AND periodEnd = :periodEnd";

        $result = $this->db->fetchOne($sql, [
            'ibPartnerId' => $ibPartnerId,
            'paymentCycle' => $paymentCycle,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd
        ]);

        return (int)($result['count'] ?? 0);
    }

    /**
     * 创建deposit记录（佣金提现到钱包）
     * 注意：钱包余额是通过 SUM(deposits.netAmount WHERE status='completed') 计算的
     * 所以需要在deposits表创建记录，使钱包余额自动增加
     *
     * 规则：sourceType='commission'时不需要 paymentMethodId
     */
    private function createCommissionDeposit($ibPartner, $totalAmount, $withdrawalId, $transactionId) {
        $data = [
            'transactionId' => $transactionId,
            'userId' => $ibPartner['userId'],
            'sourceType' => 'commission', // 标记为佣金提现
            'amount' => $totalAmount,
            'netAmount' => $totalAmount, // 佣金提现无手续费
            'status' => 'completed', // 直接标记为已完成，使钱包余额立即增加
            'requestedAt' => date('Y-m-d H:i:s'),
            'completedAt' => date('Y-m-d H:i:s')
        ];

        return $this->db->insert('deposits', $data);
    }

    /**
     * 更新提现记录状态
     */
    private function updateWithdrawalStatus($withdrawalId, $status, $failureReason = null) {
        $data = [
            'status' => $status,
            'processedAt' => date('Y-m-d H:i:s')
        ];

        if ($failureReason) {
            $data['failureReason'] = substr($failureReason, 0, 500);
        }

        $this->db->update('ibCommissionWithdrawals', $data, 'id = :id', ['id' => $withdrawalId]);
    }

    /**
     * 更新佣金计算记录状态
     */
    private function updateCommissionCalculationsStatus($ibPartnerId, $withdrawalId, $paymentCycle, $periodStart, $periodEnd) {
        $data = [
            'calculationStatus' => 'withdrawn',
            'withdrawnAt' => date('Y-m-d H:i:s'),
            'withdrawalId' => $withdrawalId
        ];

        $where = "ibPartnerId = :ibPartnerId
                  AND calculationStatus = 'calculated'
                  AND paymentCycle = :paymentCycle
                  AND periodStart = :periodStart
                  AND periodEnd = :periodEnd";

        $params = [
            'ibPartnerId' => $ibPartnerId,
            'paymentCycle' => $paymentCycle,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd
        ];

        $this->db->update('ibCommissionCalculations', $data, $where, $params);
    }
}
