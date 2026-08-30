<?php
/**
 * 客户端佣金报表控制器
 * 仅显示当前用户（IB）的直接下一级：Direct Sub-IB 与 Direct Client，结构与后台 IB Report 对齐
 */

require_once __DIR__ . '/../models/IbCommissionOrder.php';
require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/../models/ClientIbPartnerAssignment.php';
require_once __DIR__ . '/../models/IbPartnerBind.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class ClientCommissionReportController {
    private const BREAKDOWN_ROWS_PER_CLIENT_CAP = 1000;

    private $commissionOrderModel;
    private $ibPartnerModel;
    private $clientIbPartnerAssignmentModel;
    private $ibPartnerBindModel;

    /**
     * 将前端发送的日期转换为服务器时区（Asia/Shanghai）用于查询，以前端页面的时区为准。
     * - 若前端传 ISO 8601（带 T/Z/+）：按该时刻转为服务器时区再查询。
     * - 若只传 YYYY-MM-DD：视为服务器时区当日 00:00:00（兼容旧调用）。
     */
    private function convertDateToServerTimezone($dateString) {
        if (empty($dateString)) return null;
        try {
            if (strpos($dateString, 'T') !== false || strpos($dateString, 'Z') !== false || strpos($dateString, '+') !== false) {
                $date = new DateTime($dateString);
                $date->setTimezone(new DateTimeZone('Asia/Shanghai'));
                return $date->format('Y-m-d H:i:s');
            }
            return $dateString . ' 00:00:00';
        } catch (Exception $e) {
            return $dateString;
        }
    }

    /**
     * 结束日期转为服务器时区当日 23:59:59（同上，以前端传入的带时区时间为准）。
     */
    private function convertEndDateToServerTimezone($dateString) {
        if (empty($dateString)) return null;
        try {
            if (strpos($dateString, 'T') !== false || strpos($dateString, 'Z') !== false || strpos($dateString, '+') !== false) {
                $date = new DateTime($dateString);
                $date->setTimezone(new DateTimeZone('Asia/Shanghai'));
                $date->setTime(23, 59, 59);
                return $date->format('Y-m-d H:i:s');
            }
            return $dateString . ' 23:59:59';
        } catch (Exception $e) {
            return (strpos($dateString, ' ') === false) ? $dateString . ' 23:59:59' : $dateString;
        }
    }

    private function percentChange($current, $previous) {
        if ($previous == 0) return $current > 0 ? 100.0 : 0.0;
        return (($current - $previous) / $previous) * 100;
    }

    private function getInitials($name) {
        $name = trim((string)$name);
        if ($name === '') return '—';
        $words = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr(end($words), 0, 1));
        }
        return strtoupper(substr($name, 0, 2));
    }

    private function isTopLevelIb($ibPartnerId) {
        $parent = $this->ibPartnerBindModel->getParentIbPartner((int)$ibPartnerId);
        return empty($parent) || empty($parent['parentIbPartnerId']);
    }

    private function getCurrentIbDisplayInfo($ibPartnerId, $db) {
        $ibPartnerId = (int)$ibPartnerId;
        if ($ibPartnerId <= 0) {
            return null;
        }

        $sql = "SELECT ib.id, ib.ibCode, ib.companyName, ib.contactEmail, ib.userId,
                TRIM(CONCAT(COALESCE(cu.firstName,''), ' ', COALESCE(cu.lastName,''))) AS userFullName,
                cu.email AS userEmail
                FROM ibPartners ib
                LEFT JOIN clientUsers cu ON cu.id = ib.userId
                WHERE ib.id = :id
                LIMIT 1";
        $row = $db->fetchOne($sql, ['id' => $ibPartnerId]);
        if (!$row) {
            return null;
        }

        $name = trim((string)($row['userFullName'] ?? ''));
        if ($name === '') {
            $name = trim((string)($row['companyName'] ?? ''));
        }
        $email = $row['userEmail'] ?? ($row['contactEmail'] ?? '');

        return [
            'id' => $ibPartnerId,
            'userId' => (int)($row['userId'] ?? 0),
            'name' => $name !== '' ? $name : ($email !== '' ? $email : '—'),
            'email' => $email,
            'referralCode' => (string)($row['ibCode'] ?? ''),
        ];
    }

    public function __construct() {
        $this->commissionOrderModel = new IbCommissionOrder();
        $this->ibPartnerModel = new IbPartner();
        $this->clientIbPartnerAssignmentModel = new ClientIbPartnerAssignment();
        $this->ibPartnerBindModel = new IbPartnerBind();
    }

    /**
     * 获取当前用户ID（支持预览 X-Preview-Token 与 JWT）
     */
    private function getCurrentUserId() {
        $userId = ClientAuthContext::getCurrentClientUserId();
        if ($userId !== null) {
            return $userId;
        }
        $token = JWT::getTokenFromHeader();
        if (!$token) {
            Response::error('Unauthorized - No token provided', 401);
        }
        try {
            $payload = JWT::decode($token);
            if (!isset($payload['userId'])) {
                Response::error('Invalid token - No user ID', 401);
            }
            return $payload['userId'];
        } catch (Exception $e) {
            Response::error('Invalid token: ' . $e->getMessage(), 401);
        }
    }

    /**
     * Commission Report 只能查看当前登录账号名下已批准的 IB 身份。
     */
    private function getReportIbPartner($currentUserId) {
        $requestedIbPartnerId = (int)($_GET['ibPartnerId'] ?? 0);
        $ibPartners = $this->ibPartnerModel->getAllByClientId($currentUserId);
        $approvedPartners = array_values(array_filter($ibPartners, function ($ibPartner) {
            return ($ibPartner['status'] ?? '') === IbPartner::STATUS_APPROVED;
        }));

        if (empty($approvedPartners)) {
            Response::error('IB partner not found', 404);
        }

        $selectedId = (int)($approvedPartners[0]['id'] ?? 0);
        if ($requestedIbPartnerId > 0) {
            $selectedId = 0;
            foreach ($approvedPartners as $ibPartner) {
                if ((int)($ibPartner['id'] ?? 0) === $requestedIbPartnerId) {
                    $selectedId = $requestedIbPartnerId;
                    break;
                }
            }
            if ($selectedId <= 0) {
                Response::error('IB partner not found', 404);
            }
        }

        $ibPartner = $this->ibPartnerModel->findById($selectedId);
        if (!$ibPartner || (int)($ibPartner['userId'] ?? 0) !== (int)$currentUserId || ($ibPartner['status'] ?? '') !== IbPartner::STATUS_APPROVED) {
            Response::error('IB partner not found', 404);
        }

        return $ibPartner;
    }

    /**
     * 获取统计信息（与后台 IB Report 对齐：本周期 vs 上周期）
     * GET /api/client/commission-report/statistics
     */
    public function statistics() {
        try {
            $currentUserId = $this->getCurrentUserId();
            $ibPartner = $this->getReportIbPartner($currentUserId);
            $this->respondStatisticsForIb((int)$ibPartner['id'], false);
        } catch (Exception $e) {
            Response::error('Failed to fetch statistics: ' . $e->getMessage(), 500);
        }
    }

    public function adminStatistics($ibPartnerId) {
        $ibPartnerId = (int)$ibPartnerId;
        if ($ibPartnerId <= 0) {
            Response::validationError(['ibPartnerId' => 'ibPartnerId is required']);
        }
        if (!$this->ibPartnerModel->findById($ibPartnerId)) {
            Response::notFound('IB partner not found');
        }

        Response::success($this->getStatisticsPayloadForIb($ibPartnerId, true));
    }

    private function respondStatisticsForIb($ibPartnerId, $includeTree = false) {
        Response::success($this->getStatisticsPayloadForIb($ibPartnerId, $includeTree));
    }

    public function getStatisticsPayloadForIb($ibPartnerId, $includeTree = false) {
        $startDate = isset($_GET['start_date']) ? $this->convertDateToServerTimezone($_GET['start_date']) : null;
        $endDate = isset($_GET['end_date']) ? $this->convertEndDateToServerTimezone($_GET['end_date']) : null;

        $tz = new DateTimeZone('Asia/Shanghai');
        $now = new DateTime('now', $tz);
        if ($startDate && $endDate) {
            $thisStart = $startDate;
            $thisEnd = $endDate;
            $startDt = new DateTime($thisStart, $tz);
            $endDt = new DateTime($thisEnd, $tz);
            $days = (int)$endDt->diff($startDt)->days + 1;
            $prevEndDt = (clone $startDt)->modify('-1 day');
            $prevStartDt = (clone $prevEndDt)->modify('-' . ($days - 1) . ' days');
            $prevStart = $prevStartDt->format('Y-m-d 00:00:00');
            $prevEnd = $prevEndDt->format('Y-m-d 23:59:59');
        } else {
            $thisStart = $now->format('Y-m-01 00:00:00');
            $thisEnd = $now->format('Y-m-d 23:59:59');
            $prevStart = (clone $now)->modify('first day of last month')->format('Y-m-d 00:00:00');
            $prevEnd = (clone $now)->modify('last day of last month')->format('Y-m-d 23:59:59');
        }

        $db = Database::getInstance();
        $ibPartnerIds = $includeTree
            ? $this->ibPartnerBindModel->getDescendantIbPartnerIds($ibPartnerId, true)
            : [$ibPartnerId];
        $ibPartnerIds = array_values(array_unique(array_filter(array_map('intval', $ibPartnerIds))));
        if (empty($ibPartnerIds)) {
            $ibPartnerIds = [$ibPartnerId];
        }

        $placeholders = implode(',', array_map(function ($index) { return ':ib' . $index; }, array_keys($ibPartnerIds)));
        $baseWhere = "ibPartnerId IN ({$placeholders}) AND status != :cancelled";
        $paramsBase = ['cancelled' => 'cancelled'];
        foreach ($ibPartnerIds as $index => $id) {
            $paramsBase['ib' . $index] = $id;
        }
        $paramsThis = array_merge($paramsBase, ['startDate' => $thisStart, 'endDate' => $thisEnd]);
        $paramsPrev = array_merge($paramsBase, ['startDate' => $prevStart, 'endDate' => $prevEnd]);
        $sql = "SELECT SUM(commission) AS totalCommission,
                SUM(CASE WHEN status = 'completed' THEN commission ELSE 0 END) AS paidCommission,
                SUM(CASE WHEN status IN ('pending','approved') THEN commission ELSE 0 END) AS pendingCommission
                FROM ib_commission_order WHERE {$baseWhere} AND orderDate >= :startDate AND orderDate <= :endDate";
        $rowThis = $db->fetchOne($sql, $paramsThis);
        $rowPrev = $db->fetchOne($sql, $paramsPrev);

        $totalThis = (float)($rowThis['totalCommission'] ?? 0);
        $totalPrev = (float)($rowPrev['totalCommission'] ?? 0);
        $paidThis = (float)($rowThis['paidCommission'] ?? 0);
        $paidPrev = (float)($rowPrev['paidCommission'] ?? 0);
        $pendingThis = (float)($rowThis['pendingCommission'] ?? 0);
        $pendingPrev = (float)($rowPrev['pendingCommission'] ?? 0);

        $referrals = (int)$this->ibPartnerBindModel->getApprovedReferralsCountUnderIb($ibPartnerId);
        $referralsPrev = (int)$this->ibPartnerBindModel->getApprovedReferralsCountUnderIb($ibPartnerId, $prevEnd);
        $referralsChange = (float)$this->percentChange($referrals, $referralsPrev);
        $newReferrals = $referrals - $referralsPrev;

        // 账户级 deposit / withdraw 统计：IB partner 自己 user 账号的资金流，不聚合下线
        $ibPartner = $this->ibPartnerModel->findById($ibPartnerId);
        $accountUserId = (int)($ibPartner['userId'] ?? 0);
        $totalDeposit = $this->sumCompletedAmount($db, 'deposits', $accountUserId, $thisStart, $thisEnd);
        $totalWithdraw = $this->sumCompletedAmount($db, 'withdrawals', $accountUserId, $thisStart, $thisEnd);
        $netDeposit = $totalDeposit - $totalWithdraw;

        $volumeThis = $this->commissionOrderModel->getTotalTradingVolumeLots($ibPartnerIds, $thisStart, $thisEnd);
        $volumePrev = $this->commissionOrderModel->getTotalTradingVolumeLots($ibPartnerIds, $prevStart, $prevEnd);

        return [
            'totalCommission' => $totalThis,
            'totalCommissionChange' => round($this->percentChange($totalThis, $totalPrev), 1),
            'paidCommission' => $paidThis,
            'paidCommissionChange' => round($this->percentChange($paidThis, $paidPrev), 1),
            'pendingPayout' => $pendingThis,
            'pendingPayoutChange' => round($this->percentChange($pendingThis, $pendingPrev), 1),
            'referrals' => $referrals,
            'referralsChange' => round($referralsChange, 1),
            'totalEarnings' => $totalThis,
            'thisMonth' => $paidThis,
            'totalReferrals' => $referrals,
            'newReferrals' => (int)$newReferrals,
            'totalDeposit' => $totalDeposit,
            'totalWithdraw' => $totalWithdraw,
            'netDeposit' => $netDeposit,
            'totalTradingVolume' => $volumeThis,
            'totalTradingVolumeChange' => round($this->percentChange($volumeThis, $volumePrev), 1)
        ];
    }

    /**
     * 用 status='completed' + completedAt 在区间内 的口径，sum 出账户级金额
     * 表名只走 'deposits' / 'withdrawals' 白名单，避免拼接注入
     */
    private function sumCompletedAmount($db, $table, $userId, $start, $end) {
        if ($userId <= 0) {
            return 0.0;
        }
        if (!in_array($table, ['deposits', 'withdrawals'], true)) {
            return 0.0;
        }
        $row = $db->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM {$table}
             WHERE userId = :userId
               AND status = 'completed'
               AND completedAt IS NOT NULL
               AND completedAt >= :startDate
               AND completedAt <= :endDate",
            ['userId' => $userId, 'startDate' => $start, 'endDate' => $end]
        );
        return (float)($row['total'] ?? 0);
    }

    /**
     * 按绑定关系构建层级列表（扁平结构，含 depth/parentId/hasChildren），并合并佣金等统计
     */
    private function buildListTree($ibPartnerId, $startDate, $endDate, $search, $db) {
        $flat = [];
        $recurse = function ($parentId, $depth) use (&$recurse, &$flat) {
            $children = $this->ibPartnerBindModel->getDirectBindChildren($parentId);
            foreach ($children as $c) {
                $childId = (int) $c['id'];
                $type = $c['type'];
                $subChildren = ($type === 'Sub-IB') ? $this->ibPartnerBindModel->getDirectBindChildren($childId) : [];
                $hasChildren = count($subChildren) > 0;
                $flat[] = [
                    'id' => $childId,
                    'type' => $type,
                    'isSelf' => false,
                    'referralName' => $c['referralName'] ?? '',
                    'email' => $c['email'] ?? '',
                    'referralCode' => $c['referralCode'] ?? '',
                    'depth' => $depth,
                    'parentId' => $parentId,
                    'hasChildren' => $hasChildren,
                    'commissionEarned' => 0,
                    'paidCommission' => 0,
                    'pendingCommission' => 0,
                    'clientsReferred' => 0,
                    'tradingVolume' => 0,
                    'lastPayoutDate' => null,
                ];
                if ($type === 'Sub-IB' && $hasChildren) {
                    $recurse($childId, $depth + 1);
                }
            }
        };
        $recurse($ibPartnerId, 0);

        if ($this->isTopLevelIb($ibPartnerId)) {
            $selfInfo = $this->getCurrentIbDisplayInfo($ibPartnerId, $db);
            if ($selfInfo) {
                array_unshift($flat, [
                    'id' => (int)$selfInfo['id'],
                    'type' => 'Sub-IB',
                    'isSelf' => true,
                    'referralName' => $selfInfo['name'],
                    'email' => $selfInfo['email'],
                    'referralCode' => $selfInfo['referralCode'],
                    'depth' => 0,
                    'parentId' => 0,
                    'hasChildren' => false,
                    'commissionEarned' => 0,
                    'paidCommission' => 0,
                    'pendingCommission' => 0,
                    'clientsReferred' => 0,
                    'tradingVolume' => 0,
                    'lastPayoutDate' => null,
                ]);
            }
        }

        $subIbIds = array_values(array_unique(array_map(function ($r) {
            return (int) $r['id'];
        }, array_filter($flat, function ($r) { return ($r['type'] ?? '') === 'Sub-IB'; }))));
        $clientIds = array_values(array_unique(array_map(function ($r) {
            return (int) $r['id'];
        }, array_filter($flat, function ($r) { return ($r['type'] ?? '') === 'Direct Client'; }))));
        $descendantIbIds = $this->ibPartnerBindModel->getDescendantIbPartnerIds($ibPartnerId, true);

        $dateWhere = '1=1';
        if ($startDate) $dateWhere .= " AND ico.orderDate >= :startDate";
        if ($endDate) $dateWhere .= " AND ico.orderDate <= :endDate";
        $searchWhereClients = $search ? " AND (CONCAT(COALESCE(cu.firstName,''), ' ', COALESCE(cu.lastName,'')) LIKE :search OR cu.email LIKE :search)" : "";
        $searchWhereSub = $search ? " AND (ib.companyName LIKE :search OR ib.ibCode LIKE :search OR ib.contactEmail LIKE :search)" : "";
        $params = [];
        if ($startDate !== null) $params['startDate'] = $startDate;
        if ($endDate !== null) $params['endDate'] = $endDate;
        if ($search !== '') $params['search'] = '%' . $search . '%';

        $statsMap = [];
        if (!empty($clientIds) && !empty($descendantIbIds)) {
            $phCl = implode(',', array_map(function ($i) { return ':cl' . $i; }, array_keys($clientIds)));
            $phIb = implode(',', array_map(function ($i) { return ':dib' . $i; }, array_keys($descendantIbIds)));
            $paramsClients = [];
            if ($startDate !== null) $paramsClients['startDate'] = $startDate;
            if ($endDate !== null) $paramsClients['endDate'] = $endDate;
            if ($search !== '') $paramsClients['search'] = '%' . $search . '%';
            foreach ($descendantIbIds as $i => $id) { $paramsClients['dib' . $i] = $id; }
            foreach ($clientIds as $i => $id) { $paramsClients['cl' . $i] = $id; }
            $clientsSql = "SELECT cu.id AS clientId, 'Direct Client' AS type, SUM(ico.commission) AS commissionEarned,
                SUM(CASE WHEN ico.status = 'completed' THEN ico.commission ELSE 0 END) AS paidCommission,
                SUM(CASE WHEN ico.status IN ('pending','approved') THEN ico.commission ELSE 0 END) AS pendingCommission,
                MAX(CASE WHEN ico.status = 'completed' THEN ico.payoutDate END) AS lastPayoutDate
                FROM ib_commission_order ico
                LEFT JOIN deposits d ON ico.depositId = d.id
                LEFT JOIN orders o ON ico.orderId = o.id
                LEFT JOIN tradingAccountExternalAccounts taea ON taea.providerAccountId = o.trading_login
                LEFT JOIN tradingAccounts ta ON ta.id = taea.tradingAccountId
                INNER JOIN clientUsers cu ON cu.id = COALESCE(d.userId, ta.userId)
                WHERE ico.ibPartnerId IN ({$phIb}) AND ico.status != 'cancelled'
                AND (ico.depositId IS NOT NULL OR ico.orderId IS NOT NULL)
                AND COALESCE(d.userId, ta.userId) IN ({$phCl}) AND {$dateWhere} {$searchWhereClients}
                GROUP BY cu.id, cu.firstName, cu.lastName, cu.email";
            $rows = $db->fetchAll($clientsSql, $paramsClients);
            foreach ($rows as $r) {
                $statsMap['Direct Client#' . (int)$r['clientId']] = $r;
            }
        }
        if (!empty($subIbIds)) {
            $phSub = implode(',', array_map(function ($i) { return ':sub' . $i; }, array_keys($subIbIds)));
            $paramsSub = [];
            if ($startDate !== null) $paramsSub['startDate'] = $startDate;
            if ($endDate !== null) $paramsSub['endDate'] = $endDate;
            if ($search !== '') $paramsSub['search'] = '%' . $search . '%';
            foreach ($subIbIds as $i => $id) { $paramsSub['sub' . $i] = $id; }
            $subIbsSql = "SELECT ib.id AS clientId, 'Sub-IB' AS type, SUM(ico.commission) AS commissionEarned,
                SUM(CASE WHEN ico.status = 'completed' THEN ico.commission ELSE 0 END) AS paidCommission,
                SUM(CASE WHEN ico.status IN ('pending','approved') THEN ico.commission ELSE 0 END) AS pendingCommission,
                MAX(CASE WHEN ico.status = 'completed' THEN ico.payoutDate END) AS lastPayoutDate
                FROM ib_commission_order ico
                INNER JOIN ibPartners ib ON ib.id = ico.ibPartnerId
                LEFT JOIN clientUsers cu_ib ON cu_ib.id = ib.userId
                WHERE ico.ibPartnerId IN ({$phSub}) AND ico.status != 'cancelled' AND {$dateWhere} {$searchWhereSub}
                GROUP BY ib.id, ib.companyName, ib.contactEmail, ib.ibCode, ib.userId, cu_ib.firstName, cu_ib.lastName, cu_ib.email";
            $rows = $db->fetchAll($subIbsSql, $paramsSub);
            foreach ($rows as $r) {
                $statsMap['Sub-IB#' . (int)$r['clientId']] = $r;
            }
        }

        // 「自己」行只统计落在 IB 自己账户上的佣金(self-referral)，覆盖上面按 ibPartnerId 汇总出来的全量
        if ($this->isTopLevelIb($ibPartnerId)) {
            $selfInfo = $this->getCurrentIbDisplayInfo($ibPartnerId, $db);
            $selfUserId = (int)($selfInfo['userId'] ?? 0);
            if ($selfUserId > 0) {
                $selfStats = $this->commissionOrderModel->getClientCommissionStatistics($ibPartnerId, $selfUserId, $startDate, $endDate);
                $statsMap['Sub-IB#' . (int)$ibPartnerId] = [
                    'commissionEarned' => $selfStats['totalCommission'],
                    'paidCommission' => $selfStats['paidCommission'],
                    'pendingCommission' => $selfStats['pendingCommission'],
                    'lastPayoutDate' => $selfStats['lastPayoutDate'],
                ];
            }
        }

        $volumeMap = [];
        if (!empty($clientIds) && !empty($descendantIbIds)) {
            $phVolIb = implode(',', array_fill(0, count($descendantIbIds), '?'));
            $phVolCl = implode(',', array_fill(0, count($clientIds), '?'));
            $volParams = array_merge($descendantIbIds, $clientIds);
            if ($startDate) { $volParams[] = $startDate; }
            if ($endDate) { $volParams[] = $endDate; }
            $volSql = "SELECT x.clientUserId AS clientId, COALESCE(SUM(o.volume), 0) / 100 AS tradingVolume
                FROM (SELECT COALESCE(d2.userId, ta2.userId) AS clientUserId, ico2.orderId FROM ib_commission_order ico2
                LEFT JOIN deposits d2 ON d2.id = ico2.depositId LEFT JOIN orders o2 ON o2.id = ico2.orderId
                LEFT JOIN tradingAccountExternalAccounts taea2 ON taea2.providerAccountId = o2.trading_login
                LEFT JOIN tradingAccounts ta2 ON ta2.id = taea2.tradingAccountId
                WHERE ico2.ibPartnerId IN ({$phVolIb}) AND ico2.status != 'cancelled' AND ico2.orderId IS NOT NULL
                AND COALESCE(d2.userId, ta2.userId) IN ({$phVolCl})";
            if ($startDate) $volSql .= " AND ico2.orderDate >= ?";
            if ($endDate) $volSql .= " AND ico2.orderDate <= ?";
            $volSql .= " GROUP BY COALESCE(d2.userId, ta2.userId), ico2.orderId) x INNER JOIN orders o ON o.id = x.orderId GROUP BY x.clientUserId";
            $volRows = $db->fetchAll($volSql, $volParams);
            foreach ($volRows as $r) { $volumeMap[(int)$r['clientId']] = (float)($r['tradingVolume'] ?? 0); }
        }
        $subIbVolumeMap = [];
        $clientsReferredMap = [];
        if (!empty($subIbIds)) {
            foreach ($subIbIds as $sid) {
                $clientsReferredMap[$sid] = (int) $this->ibPartnerBindModel->getApprovedReferralsCountUnderIb($sid);
            }
            $ph = implode(',', array_fill(0, count($subIbIds), '?'));
            $volDateWhere = ' WHERE ico2.ibPartnerId IN (' . $ph . ') AND ico2.orderId IS NOT NULL AND ico2.status != \'cancelled\' ';
            $volParams = $subIbIds;
            if ($startDate) { $volDateWhere .= ' AND ico2.orderDate >= ?'; $volParams[] = $startDate; }
            if ($endDate) { $volDateWhere .= ' AND ico2.orderDate <= ?'; $volParams[] = $endDate; }
            $volSql = "SELECT x.ibPartnerId AS clientId, COALESCE(SUM(o.volume), 0) / 100 AS tradingVolume
                FROM (SELECT ico2.ibPartnerId, ico2.orderId FROM ib_commission_order ico2 {$volDateWhere} GROUP BY ico2.ibPartnerId, ico2.orderId) x
                INNER JOIN orders o ON o.id = x.orderId GROUP BY x.ibPartnerId";
            $volRows = $db->fetchAll($volSql, $volParams);
            foreach ($volRows as $r) { $subIbVolumeMap[(int)$r['clientId']] = (float)($r['tradingVolume'] ?? 0); }
        }

        foreach ($flat as &$row) {
            $key = ($row['type'] ?? '') . '#' . (int)$row['id'];
            if (isset($statsMap[$key])) {
                $row['commissionEarned'] = (float)($statsMap[$key]['commissionEarned'] ?? 0);
                $row['paidCommission'] = (float)($statsMap[$key]['paidCommission'] ?? 0);
                $row['pendingCommission'] = (float)($statsMap[$key]['pendingCommission'] ?? 0);
                $row['lastPayoutDate'] = $statsMap[$key]['lastPayoutDate'] ?? null;
            }
            if (($row['type'] ?? '') === 'Direct Client') {
                $row['tradingVolume'] = $volumeMap[(int)$row['id']] ?? 0;
            } else {
                $row['clientsReferred'] = $clientsReferredMap[(int)$row['id']] ?? 0;
                $row['tradingVolume'] = $subIbVolumeMap[(int)$row['id']] ?? 0;
            }
        }
        unset($row);

        // 只保留有 ib_commission_order 佣金信息的节点，或其上级（便于层级展示）
        $rowKey = function ($row) { return ($row['type'] ?? '') . '#' . (int)$row['id']; };
        $hasCommissionKeys = [];
        foreach ($flat as $row) {
            if ((float)($row['commissionEarned'] ?? 0) > 0) {
                $hasCommissionKeys[$rowKey($row)] = true;
            }
        }
        $showKeys = $hasCommissionKeys;
        foreach ($flat as $row) {
            if (empty($showKeys[$rowKey($row)])) continue;
            $pid = (int)($row['parentId'] ?? 0);
            while ($pid > 0) {
                $parentRow = null;
                foreach ($flat as $r) {
                    if ((int)($r['id']) === $pid && ($r['type'] ?? '') === 'Sub-IB') { $parentRow = $r; break; }
                }
                if (!$parentRow) break;
                $showKeys[$rowKey($parentRow)] = true;
                $pid = (int)($parentRow['parentId'] ?? 0);
            }
        }
        $flat = array_values(array_filter($flat, function ($row) use ($rowKey, $showKeys) {
            return !empty($showKeys[$rowKey($row)]);
        }));

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $keep = [];
            foreach ($flat as $row) {
                $name = mb_strtolower(trim($row['referralName'] ?? '') . ' ' . ($row['email'] ?? '') . ' ' . ($row['referralCode'] ?? ''));
                if (strpos($name, $needle) !== false) $keep[(int)$row['id']] = true;
            }
            $ancestors = [];
            foreach ($flat as $row) {
                if (!empty($keep[(int)$row['id']])) {
                    $pid = (int)($row['parentId'] ?? 0);
                    while ($pid > 0) {
                        $ancestors[$pid] = true;
                        $parentRow = null;
                        foreach ($flat as $r) { if ((int)($r['id']) === $pid && ($r['type'] ?? '') === 'Sub-IB') { $parentRow = $r; break; } }
                        $pid = $parentRow ? (int)($parentRow['parentId'] ?? 0) : 0;
                    }
                }
            }
            $flat = array_values(array_filter($flat, function ($row) use ($keep, $ancestors) {
                $id = (int)$row['id'];
                return !empty($keep[$id]) || !empty($ancestors[$id]);
            }));
        }

        return $flat;
    }

    /**
     * 获取佣金列表（按绑定关系层级展示，参考后台 Final Review List：展开/收起、缩进）
     * GET /api/client/commission-report/list
     */
    public function list() {
        try {
            $currentUserId = $this->getCurrentUserId();
            $ibPartner = $this->getReportIbPartner($currentUserId);
            $this->respondListForIb((int)$ibPartner['id']);
        } catch (Exception $e) {
            Response::error('Failed to fetch commission list: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 后台按 ibPartnerId 查看某 IB 的佣金明细列表（复用客户端 IB Report 同一套逻辑）
     * GET /api/admin-clients/commission-report/list?ibPartnerId=
     */
    public function adminList($ibPartnerId) {
        $ibPartnerId = (int)$ibPartnerId;
        if ($ibPartnerId <= 0) {
            Response::validationError(['ibPartnerId' => 'ibPartnerId is required']);
        }
        if (!$this->ibPartnerModel->findById($ibPartnerId)) {
            Response::error('IB partner not found', 404);
        }
        try {
            $this->respondListForIb($ibPartnerId);
        } catch (Exception $e) {
            Response::error('Failed to fetch commission list: ' . $e->getMessage(), 500);
        }
    }

    private function respondListForIb($ibPartnerId) {
            $ibPartnerId = (int)$ibPartnerId;
            $startDate = !empty($_GET['start_date']) ? $this->convertDateToServerTimezone($_GET['start_date']) : null;
            $endDate = !empty($_GET['end_date']) ? $this->convertEndDateToServerTimezone($_GET['end_date']) : null;
            $search = trim((string)($_GET['search'] ?? ''));
            $db = Database::getInstance();

            $items = $this->buildListTree($ibPartnerId, $startDate, $endDate, $search, $db);

            // 分页按「与当前用户直接绑定的 IB/Client」数量（仅 depth=0），子级不计入分页（参考后台 Final Review List）
            $rowKey = function ($r) { return ($r['type'] ?? '') . '#' . (int)$r['id']; };
            $rowKeyToRootKey = [];
            $byDepth = [];
            foreach ($items as $r) {
                $d = (int)($r['depth'] ?? 0);
                $byDepth[$d][] = $r;
            }
            ksort($byDepth, SORT_NUMERIC);
            foreach ($byDepth as $depth => $rows) {
                foreach ($rows as $r) {
                    $k = $rowKey($r);
                    if ($depth === 0) {
                        $rowKeyToRootKey[$k] = $k;
                    } else {
                        $pid = (int)($r['parentId'] ?? 0);
                        $parentKey = $pid > 0 ? ('Sub-IB#' . $pid) : $k;
                        $rowKeyToRootKey[$k] = isset($rowKeyToRootKey[$parentKey]) ? $rowKeyToRootKey[$parentKey] : $parentKey;
                    }
                }
            }

            $directRows = array_values(array_filter($items, function ($r) { return (int)($r['depth'] ?? 0) === 0; }));
            $directKeys = array_map($rowKey, $directRows);
            usort($directKeys, function ($a, $b) {
                $cmp = strcmp($a, $b);
                return $cmp !== 0 ? $cmp : 0;
            });
            $totalDirect = count($directKeys);

            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = (int)($_GET['per_page'] ?? 20);
            if ($perPage < 1) $perPage = 20;
            if ($perPage > 100) $perPage = 100;
            $offset = ($page - 1) * $perPage;
            $pageRootKeys = array_slice($directKeys, $offset, $perPage);

            $itemsForPage = array_values(array_filter($items, function ($row) use ($rowKeyToRootKey, $pageRootKeys, $rowKey) {
                $rk = $rowKey($row);
                $rootKey = $rowKeyToRootKey[$rk] ?? $rk;
                return in_array($rootKey, $pageRootKeys, true);
            }));

            $formattedItems = array_map(function ($item) {
                $name = trim($item['referralName'] ?? '') ?: ($item['email'] ?? '—');
                return [
                    'id' => $item['id'],
                    'name' => $name,
                    'initials' => $this->getInitials($name),
                    'referralCode' => $item['referralCode'] ?? '',
                    'type' => $item['type'],
                    'isSelf' => !empty($item['isSelf']),
                    'depth' => (int)($item['depth'] ?? 0),
                    'parentId' => isset($item['parentId']) ? (int)$item['parentId'] : null,
                    'hasChildren' => !empty($item['hasChildren']),
                    'totalCommission' => (float)($item['commissionEarned'] ?? 0),
                    'paidCommission' => (float)($item['paidCommission'] ?? 0),
                    'pendingCommission' => (float)($item['pendingCommission'] ?? 0),
                    'clientsReferred' => (int)($item['clientsReferred'] ?? 0),
                    'tradingVolume' => (float)($item['tradingVolume'] ?? 0),
                    'lastPayout' => !empty($item['lastPayoutDate']) ? date('M d, Y', strtotime($item['lastPayoutDate'])) : '--',
                    'referralName' => $name,
                    'commissionEarned' => (float)($item['commissionEarned'] ?? 0),
                ];
            }, $itemsForPage);

            Response::paginated($formattedItems, $totalDirect, $page, $perPage);
    }

    /**
     * 获取客户/下级IB的详细佣金明细（与后台 IB Report Detail 一致：Rule Name, Product Type, Quantity, Rate/Amount, Status）
     * GET /api/client/commission-report/detail
     */
    public function detail() {
        try {
            $currentUserId = $this->getCurrentUserId();
            $ibPartner = $this->getReportIbPartner($currentUserId);
            $this->respondDetailForIb((int)$ibPartner['id']);
        } catch (Exception $e) {
            Response::error('Failed to fetch detail: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 后台按 ibPartnerId 查看某 IB 的佣金明细展开（复用客户端逻辑）
     * GET /api/admin-clients/commission-report/detail?ibPartnerId=&referral_id=&type=
     */
    public function adminDetail($ibPartnerId) {
        $ibPartnerId = (int)$ibPartnerId;
        if ($ibPartnerId <= 0) {
            Response::validationError(['ibPartnerId' => 'ibPartnerId is required']);
        }
        if (!$this->ibPartnerModel->findById($ibPartnerId)) {
            Response::error('IB partner not found', 404);
        }
        try {
            $this->respondDetailForIb($ibPartnerId);
        } catch (Exception $e) {
            Response::error('Failed to fetch detail: ' . $e->getMessage(), 500);
        }
    }

    private function respondDetailForIb($ibPartnerId) {
        $referralId = $_GET['referral_id'] ?? null;
        $type = $_GET['type'] ?? null;

        if (!$referralId || !$type) {
            Response::error('Missing referral_id or type parameter', 400);
        }

        $filters = [];
        if (!empty($_GET['start_date'])) $filters['startDate'] = $this->convertDateToServerTimezone($_GET['start_date']);
        if (!empty($_GET['end_date'])) $filters['endDate'] = $this->convertEndDateToServerTimezone($_GET['end_date']);

        if ($type === 'Sub-IB') {
            // breakdown 也按 page 分页（默认 20，max 100）；上面的统计四宫格仍走全量 stats，不受分页影响
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = (int)($_GET['per_page'] ?? 20);
            if ($perPage < 1) $perPage = 20;
            if ($perPage > 100) $perPage = 100;

            // 顶级 IB 的「自己」行（referral_id 即 IB 本人）只看落在自己账户上的佣金，统计和历史都按自己账户过滤
            $isSelfRow = $this->isTopLevelIb($ibPartnerId) && (int)$referralId === (int)$ibPartnerId;
            $selfUserId = 0;
            if ($isSelfRow) {
                $selfInfo = $this->getCurrentIbDisplayInfo($ibPartnerId, Database::getInstance());
                $selfUserId = (int)($selfInfo['userId'] ?? 0);
            }
            if ($isSelfRow && $selfUserId > 0) {
                $commissionList = $this->commissionOrderModel->getCommissionListByClient($ibPartnerId, $selfUserId, $page, $perPage, $filters);
                $stats = $this->commissionOrderModel->getClientCommissionStatistics($ibPartnerId, $selfUserId,
                    $filters['startDate'] ?? null, $filters['endDate'] ?? null);
            } else {
                $commissionList = $this->commissionOrderModel->getIbCommissionList((int)$referralId, $page, $perPage, $filters);
                $stats = $this->commissionOrderModel->getIbCommissionStatistics((int)$referralId,
                    $filters['startDate'] ?? null, $filters['endDate'] ?? null);
            }
            $totalClients = (int)$this->ibPartnerBindModel->getApprovedReferralsCountUnderIb((int)$referralId);
            $totalCommission = (float)($stats['totalCommission'] ?? $stats['totalEarned'] ?? 0);
            $paidCommission = (float)($stats['paidCommission'] ?? $stats['withdrawn'] ?? 0);
            $pendingCommission = (float)($stats['pendingCommission'] ?? $stats['pending'] ?? 0);
            $total = (int)($commissionList['total'] ?? 0);
            $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 0;
            Response::success([
                'type' => 'Sub-IB',
                'statistics' => [
                    'totalCommission' => $totalCommission,
                    'paidCommission' => $paidCommission,
                    'pendingCommission' => $pendingCommission,
                    'totalEarned' => $totalCommission,
                    'withdrawn' => $paidCommission,
                    'pending' => $pendingCommission,
                    'totalClients' => $totalClients
                ],
                'breakdown' => $this->formatBreakdown($commissionList['items']),
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'totalPages' => $totalPages
                ]
            ]);
            return;
        }

        // Direct Client：Detail 返回订单级数据（含账户/平台/balance/profit 等扩展字段），按 page 分页
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int)($_GET['per_page'] ?? 20);
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        $commissionList = $this->commissionOrderModel->getCommissionListByClient($ibPartnerId, (int)$referralId, $page, $perPage, $filters);
        $orders = self::formatClientDetailOrders($commissionList['items']);
        $total = (int)($commissionList['total'] ?? 0);
        $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 0;
        $pageTotalCommission = 0;
        foreach ($orders as $order) {
            $pageTotalCommission += (float)($order['commissionEarned'] ?? 0);
        }
        Response::success([
            'type' => 'Direct Client',
            'statistics' => null,
            'orders' => $orders,
            'totalCommission' => $pageTotalCommission,
            'breakdown' => [],
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages
            ]
        ]);
    }

    /**
     * Client 用户 Detail：把佣金原始记录格式化成详情行（含账户/平台/balance/credit/profit 等扩展字段）。
     * Order 行字段来自 orders + 通过 trading_login 关联的 tradingAccount；
     * Deposit 行字段来自 deposits + 通过 deposits.tradingAccountId 关联的 tradingAccount。
     * credit 来自 tradingAccountExternalAccounts.platformCredit（同步任务写入）；
     * equity / marginLevel 仍是实时字段 DB 没有，按约定写 0。
     */
    public static function formatClientDetailOrders($items) {
        if (empty($items)) return [];
        $rows = [];
        foreach ($items as $item) {
            $ruleType = $item['ruleRuleType'] ?? $item['ruleType'] ?? '';
            $hasDeposit = !empty($item['depositId']);
            $hasOrder = !empty($item['orderId']);

            if ($hasDeposit && ($ruleType === 'cash_back_rebate' || !$hasOrder)) {
                $rowType = 'deposit';
                $productTypeKey = 'commDeposit';
                $productType = 'Deposit';
                $volume = 0;
                $profit = 0;
                $depositAmount = isset($item['depositAmount']) ? (float)$item['depositAmount'] : 0;
                $amount = $depositAmount;
                // 需要的是平台侧真实 login（如 MT5 login），不是 tradingAccounts.accountNumber（带 MT5 前缀的内部号）
                $accountNumber = $item['depositProviderAccountId'] ?? '';
                // accountType 优先取 trading_group.label，没有再回落到 tradingAccounts.accountType（历史值可能是 name）
                $accountType = !empty($item['depositAccountTypeLabel'])
                    ? $item['depositAccountTypeLabel']
                    : ($item['depositAccountType'] ?? '');
                $baseCurrency = $item['depositAccountCurrency'] ?? '';
                $platform = $item['depositPlatformName'] ?? $item['depositPlatformCode'] ?? '';
                $balance = $item['depositPlatformBalance'] !== null ? (float)$item['depositPlatformBalance'] : 0;
                $credit = $item['depositPlatformCredit'] !== null ? (float)$item['depositPlatformCredit'] : 0;
                $tradeDateRaw = null;
                $symbol = '';
                $tradingId = null;
            } elseif ($hasOrder) {
                $rowType = 'order';
                $productTypeKey = null;
                $symbolName = $item['symbolName'] ?? $item['orderSymbol'] ?? '';
                $securityName = $item['securityName'] ?? '';
                $productType = trim($symbolName . ($securityName ? ' - ' . $securityName : '')) ?: ($item['ruleName'] ?? '—');
                $volume = isset($item['orderVolume']) ? (float)$item['orderVolume'] / 100 : 0;
                $profit = isset($item['orderProfit']) ? (float)$item['orderProfit'] : 0;
                $depositAmount = 0;
                $amount = (float)($item['finalCommission'] ?? 0);
                // 平台侧真实 login（如 MT5 login），来自 tradingAccountExternalAccounts.providerAccountId
                $accountNumber = $item['orderProviderAccountId'] ?? '';
                $accountType = !empty($item['orderAccountTypeLabel'])
                    ? $item['orderAccountTypeLabel']
                    : ($item['orderAccountType'] ?? '');
                $baseCurrency = $item['orderAccountCurrency'] ?? '';
                $platform = $item['orderPlatformName'] ?? $item['orderPlatformCode'] ?? '';
                $balance = $item['orderPlatformBalance'] !== null ? (float)$item['orderPlatformBalance'] : 0;
                $credit = $item['orderPlatformCredit'] !== null ? (float)$item['orderPlatformCredit'] : 0;
                $tradeDateRaw = $item['orderCloseTime'] ?? $item['orderOpenTime'] ?? null;
                $symbol = $symbolName;
                $tradingId = $item['orderTradingId'] ?? null;
            } else {
                $rowType = 'deposit';
                $productTypeKey = 'commDeposit';
                $productType = 'Deposit';
                $volume = 0;
                $profit = 0;
                $depositAmount = isset($item['depositAmount']) ? (float)$item['depositAmount'] : 0;
                $amount = $depositAmount;
                $accountNumber = $item['depositProviderAccountId'] ?? '';
                $accountType = !empty($item['depositAccountTypeLabel'])
                    ? $item['depositAccountTypeLabel']
                    : ($item['depositAccountType'] ?? '');
                $baseCurrency = $item['depositAccountCurrency'] ?? '';
                $platform = $item['depositPlatformName'] ?? $item['depositPlatformCode'] ?? '';
                $balance = $item['depositPlatformBalance'] !== null ? (float)$item['depositPlatformBalance'] : 0;
                $credit = $item['depositPlatformCredit'] !== null ? (float)$item['depositPlatformCredit'] : 0;
                $tradeDateRaw = null;
                $symbol = '';
                $tradingId = null;
            }

            $orderDate = !empty($item['calculatedAt']) ? date('M d, Y', strtotime($item['calculatedAt'])) : '—';
            // orders.opentime/closetime 是 Unix 时间戳（秒），不能用 strtotime（会解析失败回退到 1970）
            $tradeDate = $tradeDateRaw ? date('M d, Y H:i', is_numeric($tradeDateRaw) ? (int)$tradeDateRaw : strtotime($tradeDateRaw)) : '—';
            $lastDepositTime = !empty($item['lastDepositTime']) ? date('M d, Y H:i', strtotime($item['lastDepositTime'])) : '—';
            $fullName = trim(($item['ownerFirstName'] ?? '') . ' ' . ($item['ownerLastName'] ?? ''));
            // 状态来自 ib_commission_order.status，admin 端要按 pending/completed/processing 显示 badge
            $status = $item['calculationStatus'] ?? $item['status'] ?? 'pending';
            $paymentDate = !empty($item['withdrawnAt']) ? date('M d, Y', strtotime($item['withdrawnAt'])) : null;

            $rows[] = [
                'rowType' => $rowType,
                'productTypeKey' => $productTypeKey,
                'productType' => $productType,
                // 主要字段
                'date' => $orderDate,
                'accountNumber' => $accountNumber ?: '',
                'name' => $fullName,
                'accountOwner' => $fullName,
                'email' => $item['ownerEmail'] ?? '',
                'kyc' => $item['ownerKycStatus'] ?? '',
                'tradeDate' => $tradeDate,
                'symbol' => $symbol,
                'tradingId' => $tradingId,
                'lots' => $volume,
                'lastDepositTime' => $lastDepositTime,
                'amount' => $amount,
                'platform' => $platform,
                'accountType' => $accountType,
                'baseCurrency' => $baseCurrency,
                'balance' => $balance,
                'profitLoss' => $profit,
                'marginLevel' => 0,
                'accountEquity' => 0,
                'credit' => $credit,
                'status' => $status,
                'paymentDate' => $paymentDate,
                // 兼容原有字段
                'volume' => $volume,
                'depositAmount' => $depositAmount,
                'orderDate' => $orderDate,
                'commissionEarned' => (float)($item['finalCommission'] ?? $item['commission'] ?? 0),
            ];
        }
        return $rows;
    }

    /**
     * Sub-IB 详情 Commission Breakdown：扩展到 19 个字段，跟 Direct Client 的 Orders & Commission 同列。
     * Order 行字段来自 orders + 通过 trading_login 关联的 tradingAccount；
     * Deposit 行字段来自 deposits + 通过 deposits.tradingAccountId 关联的 tradingAccount。
     * credit 来自 tradingAccountExternalAccounts.platformCredit；equity / marginLevel 仍按约定写 0。
     */
    private function formatBreakdown($items) {
        if (empty($items)) return [];
        return self::formatClientDetailOrders($items);
    }

    /**
     * Export 用：把 formatClientDetailOrders() 的明细行重映射成带 bd 前缀的扁平键，
     * 避免和 summary 的 email/status 撞键。只取导出需要的 21 列。
     */
    public static function mapDetailToBd(array $detail): array
    {
        return [
            'bdDate'            => $detail['date'] ?? '',
            'bdAccountNumber'   => $detail['accountNumber'] ?? '',
            'bdName'            => $detail['name'] ?? '',
            'bdAccountOwner'    => $detail['accountOwner'] ?? '',
            'bdEmail'           => $detail['email'] ?? '',
            'bdKyc'             => $detail['kyc'] ?? '',
            'bdTradeDate'       => $detail['tradeDate'] ?? '',
            'bdTradingId'       => $detail['tradingId'] ?? '',
            'bdSymbol'          => $detail['symbol'] ?? '',
            'bdLots'            => $detail['lots'] ?? '',
            'bdLastDepositTime' => $detail['lastDepositTime'] ?? '',
            'bdAmount'          => $detail['amount'] ?? '',
            'bdPlatform'        => $detail['platform'] ?? '',
            'bdAccountType'     => $detail['accountType'] ?? '',
            'bdBaseCurrency'    => $detail['baseCurrency'] ?? '',
            'bdBalance'         => $detail['balance'] ?? '',
            'bdProfitLoss'      => $detail['profitLoss'] ?? '',
            'bdMarginLevel'     => $detail['marginLevel'] ?? '',
            'bdAccountEquity'   => $detail['accountEquity'] ?? '',
            'bdCredit'          => $detail['credit'] ?? '',
            'bdStatus'          => $detail['status'] ?? '',
        ];
    }

    // ===== 明细导出（服务端生成 CSV）：把 detail 展开里的逐条明细全部拉平导出 =====

    /**
     * 明细 CSV 列头（与 detail 展开表一致，末尾补一列 Commission Earned）
     */
    public static function detailExportHeaders(): array {
        return [
            'Date', 'Account Number', 'Name', 'Account Owner', 'Email', 'KYC', 'Trade Date', 'ID',
            'Symbol', 'Lots', 'Last Deposit Time', 'Amount', 'Platform', 'Account Type', 'Base Currency',
            'Balance', 'Profit/Loss', 'Margin Level', 'Account Equity', 'Credit', 'Status', 'Commission Earned'
        ];
    }

    /**
     * 把 formatClientDetailOrders 的一行转成按列头顺序排好的标量数组
     */
    public static function detailExportValues(array $row): array {
        return [
            $row['date'] ?? '',
            $row['accountNumber'] ?? '',
            $row['name'] ?? '',
            $row['accountOwner'] ?? '',
            $row['email'] ?? '',
            $row['kyc'] ?? '',
            $row['tradeDate'] ?? '',
            $row['tradingId'] ?? '',
            $row['symbol'] ?? '',
            $row['lots'] ?? 0,
            $row['lastDepositTime'] ?? '',
            $row['amount'] ?? 0,
            $row['platform'] ?? '',
            $row['accountType'] ?? '',
            $row['baseCurrency'] ?? '',
            $row['balance'] ?? 0,
            $row['profitLoss'] ?? 0,
            $row['marginLevel'] ?? 0,
            $row['accountEquity'] ?? 0,
            $row['credit'] ?? 0,
            $row['status'] ?? '',
            $row['commissionEarned'] ?? 0,
        ];
    }

    /**
     * 流式输出明细 CSV（带 UTF-8 BOM，Excel 双击可正常显示中文）。
     * $records: 每项 ['identity' => 按 $identityHeaders 顺序的值, 'detail' => formatClientDetailOrders 的一行]
     */
    public static function streamDetailCsv(string $filename, array $identityHeaders, array $records): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_merge($identityHeaders, self::detailExportHeaders()));
        foreach ($records as $rec) {
            fputcsv($out, array_merge($rec['identity'], self::detailExportValues($rec['detail'])));
        }
        fclose($out);
    }

    public static function writeDetailCsvFile(string $path, array $identityHeaders, array $records): void {
        $out = fopen($path, 'w');
        if ($out === false) {
            throw new RuntimeException('Failed to open CSV file for writing');
        }
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_merge($identityHeaders, self::detailExportHeaders()));
        foreach ($records as $rec) {
            fputcsv($out, array_merge($rec['identity'] ?? [], self::detailExportValues($rec['detail'] ?? [])));
        }
        fclose($out);
    }

    public function listDetailExportReferrals(int $ibPartnerId, ?string $startDate, ?string $endDate, string $search): array {
        $db = Database::getInstance();
        return $this->buildListTree($ibPartnerId, $startDate, $endDate, $search, $db);
    }

    public function fetchDetailRecordsForReferral(int $ibPartnerId, array $ref, array $detailFilters): array {
        $db = Database::getInstance();
        $refId = (int)($ref['id'] ?? 0);
        $type = $ref['type'] ?? '';
        $refName = trim((string)($ref['referralName'] ?? '')) ?: (string)($ref['email'] ?? '');
        $refCode = (string)($ref['referralCode'] ?? '');
        $bigPerPage = 1000000;

        if ($type === 'Sub-IB') {
            if (!empty($ref['isSelf'])) {
                $selfInfo = $this->getCurrentIbDisplayInfo($ibPartnerId, $db);
                $selfUserId = (int)($selfInfo['userId'] ?? 0);
                $list = $selfUserId > 0
                    ? $this->commissionOrderModel->getCommissionListByClient($ibPartnerId, $selfUserId, 1, $bigPerPage, $detailFilters)
                    : ['items' => []];
            } else {
                $list = $this->commissionOrderModel->getIbCommissionList($refId, 1, $bigPerPage, $detailFilters);
            }
        } else {
            $list = $this->commissionOrderModel->getCommissionListByClient($ibPartnerId, $refId, 1, $bigPerPage, $detailFilters);
        }

        $identity = [$refName, $refCode, $type];
        $records = [];
        foreach (self::formatClientDetailOrders($list['items'] ?? []) as $detailRow) {
            $records[] = ['identity' => $identity, 'detail' => $detailRow];
        }
        return $records;
    }

    public function collectDetailRecordsForIb(int $ibPartnerId, ?string $startDate, ?string $endDate, string $search): array {
        $referrals = $this->listDetailExportReferrals($ibPartnerId, $startDate, $endDate, $search);
        $detailFilters = [];
        if ($startDate) $detailFilters['startDate'] = $startDate;
        if ($endDate) $detailFilters['endDate'] = $endDate;

        $records = [];
        foreach ($referrals as $ref) {
            foreach ($this->fetchDetailRecordsForReferral($ibPartnerId, $ref, $detailFilters) as $row) {
                $records[] = $row;
            }
        }
        return $records;
    }

    public function adminExportDetail($ibPartnerId) {
        try {
            require_once __DIR__ . '/../services/AdminIbCommissionDetailExportService.php';
            $ibPartnerId = (int)$ibPartnerId;
            if ($ibPartnerId <= 0) {
                Response::validationError(['ibPartnerId' => 'ibPartnerId is required']);
            }
            if (!$this->ibPartnerModel->findById($ibPartnerId)) {
                Response::error('IB partner not found', 404);
            }

            $currentUser = AuthMiddleware::getCurrentUser();
            $adminUserId = (int)($currentUser['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }

            $active = AdminIbCommissionDetailExportService::getActiveForAdmin($adminUserId);
            $activeStatus = (string)($active['status'] ?? '');
            if (in_array($activeStatus, ['queued', 'running', 'cancelling'], true)) {
                Response::error('Export already in progress', 409, $this->exportProgressPayload($active));
            }
            if ($activeStatus === 'done') {
                AdminIbCommissionDetailExportService::clearActive($adminUserId);
            }

            $rawBody = json_decode(file_get_contents('php://input'), true);
            $body = is_array($rawBody) ? $rawBody : [];

            $jobId = str_replace('.', '', uniqid('aibcd_', true));
            AdminIbCommissionDetailExportService::ensureExportDir();
            AdminIbCommissionDetailExportService::writeProgress($jobId, [
                'adminUserId' => $adminUserId,
                'status' => 'queued',
                'cancelRequested' => false,
                'percent' => 0,
                'processed' => 0,
                'total' => 0,
                'message' => 'Queued',
                'file' => $jobId . '.csv',
            ]);
            AdminIbCommissionDetailExportService::writeActive($adminUserId, $jobId);

            $payload = [
                'type' => 'export_admin_ib_commission_detail',
                'jobId' => $jobId,
                'adminUserId' => $adminUserId,
                'userId' => $adminUserId,
                'userType' => 'admin',
                'scope' => 'single_ib',
                'filters' => [
                    'ibPartnerId' => $ibPartnerId,
                    'start_date' => $body['start_date'] ?? $_GET['start_date'] ?? null,
                    'end_date' => $body['end_date'] ?? $_GET['end_date'] ?? null,
                    'search' => $body['search'] ?? $_GET['search'] ?? '',
                ],
                'requestedAt' => time(),
            ];

            try {
                $this->dispatchSwooleTask($payload);
            } catch (Exception $e) {
                AdminIbCommissionDetailExportService::writeProgress($jobId, [
                    'adminUserId' => $adminUserId,
                    'status' => 'error',
                    'cancelRequested' => false,
                    'percent' => 0,
                    'message' => $e->getMessage(),
                    'file' => null,
                ]);
                AdminIbCommissionDetailExportService::clearActive($adminUserId);
                Response::error('Failed to queue export task: ' . $e->getMessage(), 500);
            }

            Response::success([
                'jobId' => $jobId,
                'queued' => true,
            ], 'Export task accepted');
        } catch (Exception $e) {
            Response::error('Failed to export: ' . $e->getMessage(), 500);
        }
    }

    public function adminExportDetailActive() {
        try {
            require_once __DIR__ . '/../services/AdminIbCommissionDetailExportService.php';
            $currentUser = AuthMiddleware::getCurrentUser();
            $adminUserId = (int)($currentUser['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $progress = AdminIbCommissionDetailExportService::getActiveForAdmin($adminUserId);
            Response::success($this->exportProgressPayload($progress));
        } catch (Exception $e) {
            Response::error('Failed to fetch export status: ' . $e->getMessage(), 500);
        }
    }

    public function adminExportDetailStatus() {
        try {
            require_once __DIR__ . '/../services/AdminIbCommissionDetailExportService.php';
            $currentUser = AuthMiddleware::getCurrentUser();
            $adminUserId = (int)($currentUser['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $jobId = isset($_GET['jobId']) ? trim((string)$_GET['jobId']) : '';
            if ($jobId === '') {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = AdminIbCommissionDetailExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['adminUserId'] ?? 0) !== $adminUserId) {
                Response::notFound('Export job not found');
            }
            require_once __DIR__ . '/../services/ExportJobTimeoutReaper.php';
            $progress = ExportJobTimeoutReaper::reapIfStale(
                $jobId,
                $progress,
                [AdminIbCommissionDetailExportService::class, 'writeProgress'],
                [AdminIbCommissionDetailExportService::class, 'clearActive']
            );
            Response::success($this->exportProgressPayload($progress));
        } catch (Exception $e) {
            Response::error('Failed to fetch export status: ' . $e->getMessage(), 500);
        }
    }

    public function adminExportDetailCancel() {
        try {
            require_once __DIR__ . '/../services/AdminIbCommissionDetailExportService.php';
            $currentUser = AuthMiddleware::getCurrentUser();
            $adminUserId = (int)($currentUser['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $rawBody = json_decode(file_get_contents('php://input'), true);
            $jobId = '';
            if (is_array($rawBody) && !empty($rawBody['jobId'])) {
                $jobId = trim((string)$rawBody['jobId']);
            } elseif (!empty($_GET['jobId'])) {
                $jobId = trim((string)$_GET['jobId']);
            }
            if ($jobId === '') {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = AdminIbCommissionDetailExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['adminUserId'] ?? 0) !== $adminUserId) {
                Response::notFound('Export job not found');
            }
            $status = (string)($progress['status'] ?? '');
            if ($status === 'cancelled') {
                Response::success($this->exportProgressPayload($progress), 'Already cancelled');
            }
            if ($status === 'error') {
                AdminIbCommissionDetailExportService::clearActive($adminUserId);
                Response::success($this->exportProgressPayload($progress), 'Export already failed');
            }
            AdminIbCommissionDetailExportService::requestCancel($jobId);
            $updated = AdminIbCommissionDetailExportService::readProgress($jobId);
            Response::success($this->exportProgressPayload($updated), 'Cancel requested');
        } catch (Exception $e) {
            Response::error('Failed to cancel export: ' . $e->getMessage(), 500);
        }
    }

    public function adminExportDetailDownload() {
        try {
            require_once __DIR__ . '/../services/AdminIbCommissionDetailExportService.php';
            $currentUser = AuthMiddleware::getCurrentUser();
            $adminUserId = (int)($currentUser['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $jobId = isset($_GET['jobId']) ? trim((string)$_GET['jobId']) : '';
            if ($jobId === '') {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = AdminIbCommissionDetailExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['adminUserId'] ?? 0) !== $adminUserId) {
                Response::notFound('Export job not found');
            }
            if (($progress['status'] ?? '') !== 'done') {
                Response::error('Export is not ready', 400);
            }
            $csvFile = AdminIbCommissionDetailExportService::csvPath($jobId);
            if (!file_exists($csvFile) || !is_readable($csvFile)) {
                Response::error('Export file missing', 404);
            }

            AdminIbCommissionDetailExportService::clearActive($adminUserId);

            $filename = 'ib_commission_detail_' . date('Y-m-d') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($csvFile));
            header('Cache-Control: no-store');
            readfile($csvFile);
            exit;
        } catch (Exception $e) {
            Response::error('Failed to download export: ' . $e->getMessage(), 500);
        }
    }

    private function dispatchSwooleTask(array $payload): void
    {
        $address = config_swoole_address();
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($address, $errno, $errstr, 1.0);
        if (!$socket) {
            throw new Exception('Failed to connect myswoole: ' . $errstr . ' (' . $errno . ')');
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            fclose($socket);
            throw new Exception('Failed to encode task payload');
        }

        $written = @fwrite($socket, $json . '$$$###');
        fclose($socket);
        if ($written === false || $written <= 0) {
            throw new Exception('Failed to send task to myswoole');
        }
    }

    private function exportProgressPayload(?array $progress): array
    {
        if ($progress === null) {
            return ['active' => false];
        }
        return [
            'active' => true,
            'jobId' => (string)($progress['jobId'] ?? ''),
            'status' => (string)($progress['status'] ?? ''),
            'percent' => (int)($progress['percent'] ?? 0),
            'message' => (string)($progress['message'] ?? ''),
            'processed' => (int)($progress['processed'] ?? 0),
            'total' => (int)($progress['total'] ?? 0),
            'downloadReady' => !empty($progress['downloadReady']) || (($progress['status'] ?? '') === 'done'),
        ];
    }

    /**
     * GET /api/client/commission-report/export-active
     */
    public function exportActive()
    {
        try {
            require_once __DIR__ . '/../services/ClientCommissionReportExportService.php';
            $currentUserId = (int)$this->getCurrentUserId();
            $progress = ClientCommissionReportExportService::getActiveForUser($currentUserId);
            Response::success($this->exportProgressPayload($progress));
        } catch (Exception $e) {
            Response::error('Failed to fetch export status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/client/commission-report/export — enqueue async CSV job
     */
    public function export()
    {
        try {
            require_once __DIR__ . '/../services/ClientCommissionReportExportService.php';
            $currentUserId = (int)$this->getCurrentUserId();
            $ibPartner = $this->getReportIbPartner($currentUserId);

            $active = ClientCommissionReportExportService::getActiveForUser($currentUserId);
            $activeStatus = (string)($active['status'] ?? '');
            if (in_array($activeStatus, ['queued', 'running', 'cancelling'], true)) {
                Response::error('Export already in progress', 409, $this->exportProgressPayload($active));
            }
            // done: allow start-new by replacing active pointer
            if ($activeStatus === 'done') {
                ClientCommissionReportExportService::clearActive($currentUserId);
            }

            $rawBody = json_decode(file_get_contents('php://input'), true);
            $rawItems = (is_array($rawBody) && isset($rawBody['items']) && is_array($rawBody['items'])) ? $rawBody['items'] : [];
            $selectedItems = array_slice($rawItems, 0, 500);

            $jobId = str_replace('.', '', uniqid('ccr_', true));
            ClientCommissionReportExportService::ensureExportDir();
            ClientCommissionReportExportService::writeProgress($jobId, [
                'clientUserId' => $currentUserId,
                'status' => 'queued',
                'cancelRequested' => false,
                'percent' => 0,
                'processed' => 0,
                'total' => 0,
                'message' => 'Queued',
                'file' => $jobId . '.csv',
            ]);
            ClientCommissionReportExportService::writeActive($currentUserId, $jobId);

            $payload = [
                'type' => 'export_commission_report',
                'jobId' => $jobId,
                'clientUserId' => $currentUserId,
                'filters' => [
                    'start_date' => $_GET['start_date'] ?? null,
                    'end_date' => $_GET['end_date'] ?? null,
                    'search' => $_GET['search'] ?? '',
                    'ibPartnerId' => (int)$ibPartner['id'],
                ],
                'items' => $selectedItems,
                'requestedAt' => time(),
            ];

            try {
                $this->dispatchSwooleTask($payload);
            } catch (Exception $e) {
                ClientCommissionReportExportService::writeProgress($jobId, [
                    'clientUserId' => $currentUserId,
                    'status' => 'error',
                    'cancelRequested' => false,
                    'percent' => 0,
                    'message' => $e->getMessage(),
                    'file' => null,
                ]);
                ClientCommissionReportExportService::clearActive($currentUserId);
                Response::error('Failed to queue export task: ' . $e->getMessage(), 500);
            }

            Response::success([
                'jobId' => $jobId,
                'queued' => true,
            ], 'Export task accepted');
        } catch (Exception $e) {
            Response::error('Failed to export: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/client/commission-report/export-status?jobId=
     */
    public function exportStatus()
    {
        try {
            require_once __DIR__ . '/../services/ClientCommissionReportExportService.php';
            $currentUserId = (int)$this->getCurrentUserId();
            $jobId = isset($_GET['jobId']) ? trim((string)$_GET['jobId']) : '';
            if ($jobId === '') {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = ClientCommissionReportExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['clientUserId'] ?? 0) !== $currentUserId) {
                Response::notFound('Export job not found');
            }
            require_once __DIR__ . '/../services/ExportJobTimeoutReaper.php';
            $progress = ExportJobTimeoutReaper::reapIfStale(
                $jobId,
                $progress,
                [ClientCommissionReportExportService::class, 'writeProgress'],
                [ClientCommissionReportExportService::class, 'clearActive']
            );
            Response::success($this->exportProgressPayload($progress));
        } catch (Exception $e) {
            Response::error('Failed to fetch export status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/client/commission-report/export-cancel
     */
    public function exportCancel()
    {
        try {
            require_once __DIR__ . '/../services/ClientCommissionReportExportService.php';
            $currentUserId = (int)$this->getCurrentUserId();
            $rawBody = json_decode(file_get_contents('php://input'), true);
            $jobId = '';
            if (is_array($rawBody) && !empty($rawBody['jobId'])) {
                $jobId = trim((string)$rawBody['jobId']);
            } elseif (!empty($_GET['jobId'])) {
                $jobId = trim((string)$_GET['jobId']);
            }
            if ($jobId === '') {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = ClientCommissionReportExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['clientUserId'] ?? 0) !== $currentUserId) {
                Response::notFound('Export job not found');
            }
            $status = (string)($progress['status'] ?? '');
            if ($status === 'cancelled') {
                Response::success($this->exportProgressPayload($progress), 'Already cancelled');
            }
            if ($status === 'error') {
                ClientCommissionReportExportService::clearActive($currentUserId);
                Response::success($this->exportProgressPayload($progress), 'Export already failed');
            }
            ClientCommissionReportExportService::requestCancel($jobId);
            $updated = ClientCommissionReportExportService::readProgress($jobId);
            Response::success($this->exportProgressPayload($updated), 'Cancel requested');
        } catch (Exception $e) {
            Response::error('Failed to cancel export: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/client/commission-report/export-download?jobId=
     */
    public function exportDownload()
    {
        try {
            require_once __DIR__ . '/../services/ClientCommissionReportExportService.php';
            $currentUserId = (int)$this->getCurrentUserId();
            $jobId = isset($_GET['jobId']) ? trim((string)$_GET['jobId']) : '';
            if ($jobId === '') {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = ClientCommissionReportExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['clientUserId'] ?? 0) !== $currentUserId) {
                Response::notFound('Export job not found');
            }
            if (($progress['status'] ?? '') !== 'done') {
                Response::error('Export is not ready', 400);
            }
            $csvFile = ClientCommissionReportExportService::csvPath($jobId);
            if (!file_exists($csvFile) || !is_readable($csvFile)) {
                Response::error('Export file missing', 404);
            }

            ClientCommissionReportExportService::clearActive($currentUserId);

            $filename = 'commission_report_' . date('Y-m-d') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($csvFile));
            header('Cache-Control: no-store');
            readfile($csvFile);
            exit;
        } catch (Exception $e) {
            Response::error('Failed to download export: ' . $e->getMessage(), 500);
        }
    }
}
