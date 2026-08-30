<?php
/**
 * Commission Order 服务
 * 按设计文档实现：入金即返佣、每手返佣、每次交易返佣，仅写入 ib_commission_order
 * 多层级：先按公式算原始佣金，再自顶向下抵扣
 */

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../models/IbCommissionOrder.php';
require_once __DIR__ . '/../models/IbSymbolExchangeSetting.php';

class CommissionOrderService {
    private $db;
    private $commissionOrderModel;

    const RULE_TYPE_CASH_BACK = 'cash_back_rebate';
    const RULE_TYPE_PER_LOT = 'per_lot';
    const RULE_TYPE_PER_TRADE = 'per_trade';
    const RULE_TYPE_PER_LOT_REBATE = 'per_lot_rebate';
    const RULE_TYPE_PER_TRADE_REBATE = 'per_trade_rebate';

    /** 与 IbPartner::STATUS_APPROVED 一致 */
    const IB_STATUS_APPROVED = 'approved';

    /** 与 ibCommissionRules.paymentCycle 一致：实时规则生成 ib_commission_order 时直接 completed（记入钱包） */
    const PAYMENT_CYCLE_REALTIME = 'realtime';

    public function __construct() {
        $this->db = Database::getInstance();
        $this->commissionOrderModel = new IbCommissionOrder();
    }

    /**
     * 按规则 paymentCycle 生成佣金单的状态与时间字段（实时=最终态 completed，等同人工 Approve+Complete 后的数据）。
     *
     * @param array $ruleRow 须含 paymentCycle（来自 ibCommissionRules）
     * @param string $now date('Y-m-d H:i:s')
     * @return array{status:string,statusDate:?string,payoutDate:?string,autoSettled:int}
     */
    private function commissionOrderStatusFieldsForRule(array $ruleRow, $now) {
        $cycle = isset($ruleRow['paymentCycle']) ? (string) $ruleRow['paymentCycle'] : '';
        if ($cycle === self::PAYMENT_CYCLE_REALTIME) {
            return [
                'status' => IbCommissionOrder::STATUS_COMPLETED,
                'statusDate' => $now,
                'payoutDate' => $now,
                'autoSettled' => 1,
            ];
        }
        return [
            'status' => IbCommissionOrder::STATUS_PENDING,
            'statusDate' => null,
            'payoutDate' => null,
            'autoSettled' => 0,
        ];
    }

    /**
     * 客户本人作为 IB 时返回 ibPartners.id（approved 且 ibCode 去空格后非空），否则 0
     */
    private function getApprovedIbPartnerIdByUserId(int $userId, int $preferredIbPartnerId = 0): int
    {
        if ($userId <= 0) {
            return 0;
        }

        if ($preferredIbPartnerId > 0) {
            $preferred = $this->db->fetchOne(
                'SELECT id, ibCode FROM ibPartners
                 WHERE id = :id AND userId = :userId AND status = :st LIMIT 1',
                [
                    'id' => $preferredIbPartnerId,
                    'userId' => $userId,
                    'st' => self::IB_STATUS_APPROVED,
                ]
            );
            if ($preferred && !empty($preferred['id'])) {
                $code = isset($preferred['ibCode']) ? trim((string) $preferred['ibCode']) : '';
                if ($code !== '') {
                    return (int) $preferred['id'];
                }
            }
        }

        $row = $this->db->fetchOne(
            'SELECT id, ibCode FROM ibPartners WHERE userId = :userId AND status = :st LIMIT 1',
            ['userId' => $userId, 'st' => self::IB_STATUS_APPROVED]
        );

        if (!$row || empty($row['id'])) {
            return 0;
        }
        $code = isset($row['ibCode']) ? trim((string) $row['ibCode']) : '';
        if ($code === '') {
            return 0;
        }

        return (int) $row['id'];
    }

    /**
     * Resolve assigned commission rule on the trading account for an order login.
     * Returns [userId, tradingAccountId, assignedCommissionRuleId, preferredSelfIbPartnerId]
     */
    private function resolveTradingAccountAssignmentForOrder($orderId): array
    {
        $empty = [
            'userId' => 0,
            'tradingAccountId' => 0,
            'assignedCommissionRuleId' => 0,
            'preferredSelfIbPartnerId' => 0,
        ];
        $orderId = (int) $orderId;
        if ($orderId <= 0) {
            return $empty;
        }
        $row = $this->db->fetchOne(
            'SELECT cu.id AS userId, ta.id AS tradingAccountId, ta.assignedCommissionRuleId
             FROM orders o
             INNER JOIN tradingAccountExternalAccounts taea ON taea.providerAccountId = o.trading_login
             INNER JOIN tradingAccounts ta ON ta.id = taea.tradingAccountId
             INNER JOIN clientUsers cu ON cu.id = ta.userId
             WHERE o.id = :oid
             LIMIT 1',
            ['oid' => $orderId]
        );
        if (!$row) {
            return $empty;
        }
        $userId = (int) ($row['userId'] ?? 0);
        $ruleId = (int) ($row['assignedCommissionRuleId'] ?? 0);
        $preferredIb = 0;
        if ($ruleId > 0 && $userId > 0) {
            $preferredIb = $this->getIbPartnerIdForAssignedRule($ruleId, $userId);
        }
        return [
            'userId' => $userId,
            'tradingAccountId' => (int) ($row['tradingAccountId'] ?? 0),
            'assignedCommissionRuleId' => $ruleId,
            'preferredSelfIbPartnerId' => $preferredIb,
        ];
    }

    /**
     * Rule must be active and linked via ib_partner_rules to an approved IB owned by userId.
     */
    private function getIbPartnerIdForAssignedRule(int $ruleId, int $userId): int
    {
        if ($ruleId <= 0 || $userId <= 0) {
            return 0;
        }
        $row = $this->db->fetchOne(
            'SELECT ib.id
             FROM ib_partner_rules pr
             INNER JOIN ibCommissionRules cr ON cr.id = pr.ruleId AND cr.status = \'active\'
             INNER JOIN ibPartners ib ON ib.id = pr.ibPartnerId
               AND ib.userId = :uid AND ib.status = :st
             WHERE pr.ruleId = :rid
             LIMIT 1',
            ['uid' => $userId, 'st' => self::IB_STATUS_APPROVED, 'rid' => $ruleId]
        );
        return $row && !empty($row['id']) ? (int) $row['id'] : 0;
    }

    /**
     * 在绑定链末尾追加「产生佣金客户本人为 IB」的一层；链尾已是本人 id 时不重复追加。
     */
    private function appendSelfIbPartnerToChain(array $chain, $clientUserId, int $preferredIbPartnerId = 0): array
    {
        $selfIbId = $this->getApprovedIbPartnerIdByUserId((int) $clientUserId, $preferredIbPartnerId);
        if ($selfIbId <= 0) {
            return $chain;
        }
        $n = count($chain);
        if ($n > 0 && (int) $chain[$n - 1] === $selfIbId) {
            return $chain;
        }
        $chain[] = $selfIbId;
        return $chain;
    }

    /**
     * 从「离产生佣金方最近的一层上级 ibPartners.id」开始，沿 ib_partner_bind(childId→parentId) 走到网体根，
     * 返回自顶向下链 [topIbId, ..., firstUplineIbId]。
     *
     * @param int $firstUplineIbId 直属上级 ibPartners.id（对客户绑而言即 parentId；须 >0）
     */
    private function buildIbUplineChainTopDownFromFirstUpline(int $firstUplineIbId): array
    {
        if ($firstUplineIbId <= 0) {
            return [];
        }
        $chainBottomToTop = [$firstUplineIbId];
        $current = $firstUplineIbId;
        $visited = [$current => true];
        while (true) {
            $row = $this->db->fetchOne(
                'SELECT parentId FROM ib_partner_bind WHERE childId = :id LIMIT 1',
                ['id' => $current]
            );
            if (!$row || empty($row['parentId'])) {
                break;
            }
            $pid = (int) $row['parentId'];
            if (isset($visited[$pid])) {
                break;
            }
            $visited[$pid] = true;
            $current = $pid;
            $chainBottomToTop[] = $current;
        }
        return array_reverse($chainBottomToTop);
    }

    /**
     * 根据客户 userId 获取绑定链（自顶向下）[topIbId, ..., directIbId]，
     * 若客户本人为已批准且 ibCode 非空的 IB，则在链尾追加其 ibPartners.id。
     *
     * 规则：
     * 1) 若有「普通客户」绑定（ib_partner_bind.childClientId = 客户、isClient=1），从直属上级 IB 向上爬满链；
     * 2) 若无客户绑定，但本人为有效 IB：从「本人 ibPartners.id 在 IB 树中的父级」作为第一层上级，用同一套方式向上爬，
     *    再 appendSelfIbPartnerToChain（入金返佣 / 每手 / 每笔 共用此链）。
     */
    public function getBindingChainForClient($clientUserId, int $preferredSelfIbPartnerId = 0) {
        $clientUserId = (int) $clientUserId;
        if ($clientUserId <= 0) {
            return [];
        }
        $direct = $this->db->fetchOne(
            'SELECT parentId FROM ib_partner_bind WHERE childClientId = :cid AND isClient = 1 LIMIT 1',
            ['cid' => $clientUserId]
        );

        $chain = [];
        if ($direct && !empty($direct['parentId'])) {
            $chain = $this->buildIbUplineChainTopDownFromFirstUpline((int) $direct['parentId']);
        }

        if (empty($chain)) {
            $selfIbId = $this->getApprovedIbPartnerIdByUserId($clientUserId, $preferredSelfIbPartnerId);
            if ($selfIbId > 0) {
                $parentIb = $this->db->fetchOne(
                    'SELECT parentId FROM ib_partner_bind WHERE childId = :id LIMIT 1',
                    ['id' => $selfIbId]
                );
                if ($parentIb && !empty($parentIb['parentId'])) {
                    $chain = $this->buildIbUplineChainTopDownFromFirstUpline((int) $parentIb['parentId']);
                }
            }
        }

        return $this->appendSelfIbPartnerToChain($chain, $clientUserId, $preferredSelfIbPartnerId);
    }

    /**
     * 根据订单 trading_login 解析出 clientUserId，再返回绑定链
     */
    public function getBindingChainForOrder($orderId) {
        $assignment = $this->resolveTradingAccountAssignmentForOrder($orderId);
        if ($assignment['userId'] <= 0) {
            $order = $this->db->fetchOne('SELECT trading_login FROM orders WHERE id = :id LIMIT 1', ['id' => $orderId]);
            if (!$order || empty($order['trading_login'])) {
                return [];
            }
            $client = $this->db->fetchOne(
                'SELECT cu.id FROM clientUsers cu
                 INNER JOIN tradingAccounts ta ON ta.userId = cu.id
                 INNER JOIN tradingAccountExternalAccounts taea ON taea.tradingAccountId = ta.id
                 WHERE taea.providerAccountId = :login LIMIT 1',
                ['login' => $order['trading_login']]
            );
            if (!$client) {
                return [];
            }
            return $this->getBindingChainForClient($client['id']);
        }
        return $this->getBindingChainForClient(
            $assignment['userId'],
            (int) $assignment['preferredSelfIbPartnerId']
        );
    }

    /**
     * 获取链上各 IB 的入金即返佣规则（仅 status 有效且 ruleType 匹配）
     * 返回 [ibPartnerId => ruleRow, ...]，无规则的不在键中
     */
    private function getCashBackRulesForChain(array $chain) {
        if (empty($chain)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($chain), '?'));
        $sql = "SELECT pr.ibPartnerId, cr.id AS ruleId, cr.ruleType, cr.rate, cr.payoutCurrency, cr.paymentCycle
                FROM ib_partner_rules pr
                INNER JOIN ibCommissionRules cr ON cr.id = pr.ruleId AND cr.status = 'active'
                WHERE pr.ibPartnerId IN ({$placeholders}) AND cr.ruleType = ?";
        $params = array_merge($chain, [self::RULE_TYPE_CASH_BACK]);
        $rows = $this->db->fetchAll($sql, $params);
        $byIb = [];
        foreach ($rows as $r) {
            $ibId = (int) $r['ibPartnerId'];
            if (!isset($byIb[$ibId])) {
                $byIb[$ibId] = $r;
            }
        }
        return $byIb;
    }

    /**
     * 入金审核通过后生成 Commission Order（多层级：原始后自顶向下抵扣）
     */
    public function createFromDeposit($depositId) {
        $depositId = (int) $depositId;
        if ($depositId <= 0) {
            return ['created' => 0, 'message' => 'Invalid deposit id'];
        }
        $deposit = $this->db->fetchOne('SELECT id, userId, amount, status FROM deposits WHERE id = :id LIMIT 1', ['id' => $depositId]);
        if (!$deposit || ($deposit['status'] ?? '') !== 'completed') {
            return ['created' => 0, 'message' => 'Deposit not found or not approved'];
        }
        $clientUserId = (int) $deposit['userId'];
        $amount = (float) $deposit['amount'];
        if ($amount <= 0) {
            return ['created' => 0, 'message' => 'Deposit amount invalid'];
        }

        $chain = $this->getBindingChainForClient($clientUserId);
        if (empty($chain)) {
            return ['created' => 0, 'message' => 'No IB binding chain for client'];
        }

        $rules = $this->getCashBackRulesForChain($chain);
        $rawByIb = [];
        foreach ($chain as $ibId) {
            if (!isset($rules[$ibId])) {
                continue;
            }
            $rate = (float) $rules[$ibId]['rate'];
            $rawByIb[$ibId] = $amount * ($rate / 100.0);
        }

        $finalByIb = $this->deductTopDown($chain, $rawByIb);
        $now = date('Y-m-d H:i:s');
        $created = 0;
        foreach ($finalByIb as $ibPartnerId => $commission) {
            if ($commission <= 0) {
                continue;
            }
            $rule = $rules[$ibPartnerId];
            $currency = $rule['payoutCurrency'] ?? 'USD';
            $statusFields = $this->commissionOrderStatusFieldsForRule($rule, $now);
            $newId = (int) $this->db->insert('ib_commission_order', array_merge([
                'ibPartnerId' => $ibPartnerId,
                'orderId' => null,
                'depositId' => $depositId,
                'ruleId' => $rule['ruleId'],
                'ruleType' => self::RULE_TYPE_CASH_BACK,
                'commission' => round($commission, 2),
                'currency' => $currency,
                'orderDate' => $now,
                'cancelDate' => null,
            ], $statusFields));
            $created++;
        }
        return ['created' => $created, 'message' => 'OK'];
    }

    /**
     * 自顶向下抵扣：final[i] = raw[i] - raw[next in chain with raw]
     */
    private function deductTopDown(array $chain, array $rawByIb) {
        $finalByIb = [];
        foreach ($chain as $idx => $ibId) {
            $raw = $rawByIb[$ibId] ?? 0;
            $nextRaw = 0;
            for ($j = $idx + 1; $j < count($chain); $j++) {
                $nextId = $chain[$j];
                if (isset($rawByIb[$nextId])) {
                    $nextRaw = $rawByIb[$nextId];
                    break;
                }
            }
            $finalByIb[$ibId] = $raw - $nextRaw;
        }
        return $finalByIb;
    }

    /**
     * ibRuleProducts.productType 与 ibCommissionRules.product_type 对照用
     */
    private function ruleProductRowMatchesOrderTradingId(array $productRow, int $orderTradingId): bool
    {
        if ($orderTradingId <= 0) {
            return false;
        }
        $pt = isset($productRow['productType']) ? (string) $productRow['productType'] : '';
        $pid = isset($productRow['productId']) && $productRow['productId'] !== null && $productRow['productId'] !== ''
            ? (int) $productRow['productId'] : 0;

        if ($pt === 'symbol' && $pid > 0) {
            $sym = $this->db->fetchOne(
                'SELECT trading_id FROM ibCustomSymbols WHERE id = :id AND EnabledMark = 1 LIMIT 1',
                ['id' => $pid]
            );
            return $sym && (int) $sym['trading_id'] === $orderTradingId;
        }
        if ($pt === 'security' && $pid > 0) {
            $sym = $this->db->fetchOne(
                'SELECT cs.securityId
                 FROM ibCustomSymbols cs
                 INNER JOIN ibCustomSecurities sec ON sec.id = cs.securityId AND sec.EnabledMark = 1
                 WHERE cs.trading_id = :tid AND cs.EnabledMark = 1
                 LIMIT 1',
                ['tid' => $orderTradingId]
            );
            return $sym && isset($sym['securityId']) && (int) $sym['securityId'] === $pid;
        }
        return false;
    }

    /**
     * 规则主表上仅 product_type + product 时的匹配（无 ibRuleProducts 子行或子行未启用时）
     */
    private function ruleProductMatchesOrderLegacy(array $rule, $order): bool
    {
        $symbolId = (int) ($order['symbol_id'] ?? 0);
        if ($symbolId <= 0) {
            return false;
        }
        $productType = $rule['product_type'] ?? null;
        $product = isset($rule['product']) ? (int) $rule['product'] : 0;

        if ($productType === 'symbols') {
            $sym = $this->db->fetchOne(
                'SELECT id FROM ibCustomSymbols WHERE trading_id = :tid AND EnabledMark = 1 LIMIT 1',
                ['tid' => $symbolId]
            );
            return $sym && (int) $sym['id'] === $product;
        }
        if ($productType === 'securities') {
            $sym = $this->db->fetchOne(
                'SELECT cs.securityId
                 FROM ibCustomSymbols cs
                 INNER JOIN ibCustomSecurities sec ON sec.id = cs.securityId AND sec.EnabledMark = 1
                 WHERE cs.trading_id = :tid AND cs.EnabledMark = 1
                 LIMIT 1',
                ['tid' => $symbolId]
            );
            if (!$sym || !$sym['securityId']) {
                return false;
            }
            return (int) $sym['securityId'] === $product;
        }
        return $product <= 0;
    }

    /**
     * 订单产品与规则是否匹配：
     * - 若 ibRuleProducts 中存在与规则 ruleType（commissionType）一致的行，则任一行匹配即通过（同一规则多 Symbol / Security）；
     * - 否则回退到 ibCommissionRules 上 product_type + product。
     *
     * 订单 symbol_id 对应各平台品种的 trading_id（与 ibCustomSymbols.trading_id 对齐）。
     */
    private function ruleProductMatchesOrder(array $rule, $order): bool
    {
        $orderTradingId = (int) ($order['symbol_id'] ?? 0);
        if ($orderTradingId <= 0) {
            return false;
        }
        $ruleId = isset($rule['ruleId']) ? (int) $rule['ruleId'] : 0;
        $ruleType = isset($rule['ruleType']) ? (string) $rule['ruleType'] : '';
        if ($ruleId <= 0 || $ruleType === '') {
            return false;
        }

        $productRows = $this->db->fetchAll(
            'SELECT productType, productId FROM ibRuleProducts WHERE ruleId = :rid AND commissionType = :ct',
            ['rid' => $ruleId, 'ct' => $ruleType]
        );
        if (!empty($productRows)) {
            foreach ($productRows as $pr) {
                if ($this->ruleProductRowMatchesOrderTradingId($pr, $orderTradingId)) {
                    return true;
                }
            }
            return false;
        }

        return $this->ruleProductMatchesOrderLegacy($rule, $order);
    }

    /**
     * 获取链上各 IB 的订单类佣金规则（含产品匹配）
     * 返回 [ibPartnerId => ['per_lot' => ruleRow|null, ...], ...]
     */
    private function getOrderRulesForChain(array $chain, $order, int $assignedCommissionRuleId = 0) {
        if (empty($chain)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($chain), '?'));
        $sql = "SELECT pr.ibPartnerId, cr.id AS ruleId, cr.ruleType, cr.fixed_amount, cr.rebateAmount,
                       cr.payoutCurrency, cr.product_type, cr.product, cr.paymentCycle
                FROM ib_partner_rules pr
                INNER JOIN ibCommissionRules cr ON cr.id = pr.ruleId AND cr.status = 'active'
                WHERE pr.ibPartnerId IN ({$placeholders}) AND cr.ruleType IN (?, ?, ?, ?)";
        $params = array_merge($chain, [
            self::RULE_TYPE_PER_LOT,
            self::RULE_TYPE_PER_TRADE,
            self::RULE_TYPE_PER_LOT_REBATE,
            self::RULE_TYPE_PER_TRADE_REBATE,
        ]);
        $rows = $this->db->fetchAll($sql, $params);
        $byIb = [];
        foreach ($chain as $ibId) {
            $byIb[$ibId] = [
                'per_lot' => null,
                'per_trade' => null,
                'per_lot_rebate' => null,
                'per_trade_rebate' => null,
            ];
        }
        foreach ($rows as $r) {
            $ibId = (int) $r['ibPartnerId'];
            if (!isset($byIb[$ibId])) {
                continue;
            }
            if (!$this->ruleProductMatchesOrder($r, $order)) {
                continue;
            }
            if ($r['ruleType'] === self::RULE_TYPE_PER_LOT) {
                if ($byIb[$ibId]['per_lot'] !== null) {
                    continue;
                }
                $byIb[$ibId]['per_lot'] = $r;
            } elseif ($r['ruleType'] === self::RULE_TYPE_PER_TRADE) {
                if ($byIb[$ibId]['per_trade'] !== null) {
                    continue;
                }
                $byIb[$ibId]['per_trade'] = $r;
            } elseif ($r['ruleType'] === self::RULE_TYPE_PER_LOT_REBATE) {
                if ($byIb[$ibId]['per_lot_rebate'] !== null) {
                    continue;
                }
                $byIb[$ibId]['per_lot_rebate'] = $r;
            } elseif ($r['ruleType'] === self::RULE_TYPE_PER_TRADE_REBATE) {
                if ($byIb[$ibId]['per_trade_rebate'] !== null) {
                    continue;
                }
                $byIb[$ibId]['per_trade_rebate'] = $r;
            }
        }

        return $byIb;
    }

    private function applyAssignedRuleOverride(array &$byIb, array $chain, $order, int $assignedCommissionRuleId): void
    {
        $placeholders = implode(',', array_fill(0, count($chain), '?'));
        $params = array_merge([$assignedCommissionRuleId], $chain);
        $assigned = $this->db->fetchOne(
            "SELECT pr.ibPartnerId, cr.id AS ruleId, cr.ruleType, cr.fixed_amount, cr.rebateAmount,
                    cr.payoutCurrency, cr.product_type, cr.product, cr.paymentCycle
             FROM ib_partner_rules pr
             INNER JOIN ibCommissionRules cr ON cr.id = pr.ruleId AND cr.status = 'active'
             WHERE pr.ruleId = ? AND pr.ibPartnerId IN ({$placeholders})
             LIMIT 1",
            $params
        );
        if (!$assigned) {
            return;
        }
        $ibId = (int) $assigned['ibPartnerId'];
        if (!isset($byIb[$ibId])) {
            return;
        }
        if (!$this->ruleProductMatchesOrder($assigned, $order)) {
            return;
        }
        $slotMap = [
            self::RULE_TYPE_PER_LOT => 'per_lot',
            self::RULE_TYPE_PER_TRADE => 'per_trade',
            self::RULE_TYPE_PER_LOT_REBATE => 'per_lot_rebate',
            self::RULE_TYPE_PER_TRADE_REBATE => 'per_trade_rebate',
        ];
        $slot = $slotMap[$assigned['ruleType']] ?? null;
        if ($slot === null) {
            return;
        }
        $byIb[$ibId][$slot] = $assigned;
    }

    private function rebateAmountToUsd(float $rebateAmount, float $exchangeRate): float {
        $rate = $exchangeRate > 0 ? $exchangeRate : 1.0;
        return $rebateAmount / $rate;
    }

    private function computeOrderRuleSlotRaw(string $slot, array $rule, float $lots, float $exchangeRate): float {
        if ($slot === 'per_lot') {
            return $lots * (float) $rule['fixed_amount'];
        }
        if ($slot === 'per_trade') {
            return (float) $rule['fixed_amount'];
        }
        $rebate = (float) ($rule['rebateAmount'] ?? 0);
        if ($rebate <= 0) {
            return 0;
        }
        if ($slot === 'per_lot_rebate') {
            return $lots * $this->rebateAmountToUsd($rebate, $exchangeRate);
        }
        if ($slot === 'per_trade_rebate') {
            return $this->rebateAmountToUsd($rebate, $exchangeRate);
        }
        return 0;
    }

    private function orderRuleSlotType(string $slot): string {
        $map = [
            'per_lot' => self::RULE_TYPE_PER_LOT,
            'per_trade' => self::RULE_TYPE_PER_TRADE,
            'per_lot_rebate' => self::RULE_TYPE_PER_LOT_REBATE,
            'per_trade_rebate' => self::RULE_TYPE_PER_TRADE_REBATE,
        ];
        return $map[$slot] ?? $slot;
    }

    /**
     * 订单关闭后生成 Commission Order（Per Lot + Per Trade，多层级抵扣），幂等
     */
    public function createFromOrder($orderId) {
        $orderId = (int) $orderId;
        if ($orderId <= 0) {
            return ['created' => 0, 'message' => 'Invalid order id'];
        }

        $order = $this->db->fetchOne('SELECT * FROM orders WHERE id = :id LIMIT 1', ['id' => $orderId]);
        if (!$order) {
            return ['created' => 0, 'message' => 'Order not found'];
        }
        if ((int) ($order['trading_status'] ?? 0) !== 2 || (int) ($order['closetime'] ?? 0) <= 0) {
            return ['created' => 0, 'message' => 'Order not closed'];
        }
        // 只对市价成交单计佣：cmd=0(buy)/1(sell)。挂单类型（限价/止损等）即使平仓也不计佣
        if (!in_array((int) ($order['cmd'] ?? -1), [0, 1], true)) {
            return ['created' => 0, 'message' => 'Not a market buy/sell order'];
        }

        $existing = $this->db->fetchOne('SELECT id FROM ib_commission_order WHERE orderId = :oid LIMIT 1', ['oid' => $orderId]);
        if ($existing) {
            return ['created' => 0, 'message' => 'Already processed (idempotent)'];
        }

        $chain = $this->getBindingChainForOrder($orderId);
        if (empty($chain)) {
            return ['created' => 0, 'message' => 'No IB binding chain for order client'];
        }

        $assignment = $this->resolveTradingAccountAssignmentForOrder($orderId);
        $orderRules = $this->getOrderRulesForChain(
            $chain,
            $order,
            (int) ($assignment['assignedCommissionRuleId'] ?? 0)
        );
        $lots = ((float) ($order['volume'] ?? 0)) / 100.0;
        $exchangeRate = IbSymbolExchangeSetting::resolveEffectiveExchangeRateForSymbolTradingId(
            (int) ($order['symbol_id'] ?? 0),
            isset($order['trading_platforms_key']) ? (string) $order['trading_platforms_key'] : null
        );

        $slots = ['per_lot', 'per_trade', 'per_lot_rebate', 'per_trade_rebate'];
        $now = date('Y-m-d H:i:s');
        $created = 0;
        $hasAnyRule = false;

        foreach ($slots as $slot) {
            $rawByIbForSlot = [];
            foreach ($chain as $ibId) {
                $rule = $orderRules[$ibId][$slot] ?? null;
                if (!$rule) {
                    continue;
                }
                $raw = $this->computeOrderRuleSlotRaw($slot, $rule, $lots, $exchangeRate);
                if ($raw > 0) {
                    $rawByIbForSlot[$ibId] = $raw;
                }
            }
            if (empty($rawByIbForSlot)) {
                continue;
            }
            $hasAnyRule = true;

            $finalByIb = $this->deductTopDown($chain, $rawByIbForSlot);
            foreach ($finalByIb as $ibPartnerId => $commission) {
                if ($commission <= 0) {
                    continue;
                }
                $rule = $orderRules[$ibPartnerId][$slot] ?? null;
                if (!$rule) {
                    continue;
                }
                $statusFields = $this->commissionOrderStatusFieldsForRule($rule, $now);
                $newId = (int) $this->db->insert('ib_commission_order', array_merge([
                    'ibPartnerId' => $ibPartnerId,
                    'orderId' => $orderId,
                    'depositId' => null,
                    'ruleId' => $rule['ruleId'],
                    'ruleType' => $this->orderRuleSlotType($slot),
                    'commission' => round($commission, 2),
                    'currency' => $rule['payoutCurrency'] ?? 'USD',
                    'orderDate' => $now,
                    'cancelDate' => null,
                ], $statusFields));
                $created++;
            }
        }

        if (!$hasAnyRule) {
            return ['created' => 0, 'message' => 'No matching rules for order'];
        }
        return ['created' => $created, 'message' => 'OK'];
    }
}
