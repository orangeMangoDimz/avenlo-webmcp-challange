<?php
/**
 * Client Deposit / Withdraw Report controller.
 * Same IB tree as Commission Report; columns are Total Deposit, Total Withdrawal, Net Deposit.
 */

require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/../models/IbPartnerBind.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Logger.php';

class ClientDepositWithdrawReportController {
    private $ibPartnerModel;
    private $ibPartnerBindModel;

    public function __construct() {
        $this->ibPartnerModel = new IbPartner();
        $this->ibPartnerBindModel = new IbPartnerBind();
    }

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
                cu.email AS userEmail,
                tl.tierLevel AS tierLevel,
                tl.tierName AS tierName,
                tl.badgeColor AS badgeColor
                FROM ibPartners ib
                LEFT JOIN clientUsers cu ON cu.id = ib.userId
                LEFT JOIN ibTierLevels tl ON tl.id = ib.tierLevelId
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
            'tierLevel' => isset($row['tierLevel']) && $row['tierLevel'] !== null && $row['tierLevel'] !== ''
                ? (int)$row['tierLevel']
                : null,
            'tierName' => $row['tierName'] ?? null,
            'badgeColor' => $row['badgeColor'] ?? null,
        ];
    }

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
     * Batch SUM(amount) for deposits (sourceType=deposit) or withdrawals by userId.
     * Date range on completedAt is optional (All preset omits dates).
     *
     * @return array<int,float> userId => total
     */
    private function sumAmountsByUserIds($db, $table, array $userIds, $startDate, $endDate) {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), function ($id) {
            return $id > 0;
        })));
        if (empty($userIds)) {
            return [];
        }
        if (!in_array($table, ['deposits', 'withdrawals'], true)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($userIds as $i => $uid) {
            $key = 'uid' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $uid;
        }

        $sql = "SELECT userId, COALESCE(SUM(amount), 0) AS total
                FROM {$table}
                WHERE userId IN (" . implode(',', $placeholders) . ")
                  AND status = 'completed'
                  AND completedAt IS NOT NULL";
        if ($table === 'deposits') {
            $sql .= " AND sourceType = 'deposit'";
        }
        if ($startDate !== null) {
            $sql .= " AND requestedAt >= :startDate";
            $params['startDate'] = $startDate;
        }
        if ($endDate !== null) {
            $sql .= " AND requestedAt <= :endDate";
            $params['endDate'] = $endDate;
        }
        $sql .= " GROUP BY userId";

        $rows = $db->fetchAll($sql, $params);
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['userId']] = (float)($row['total'] ?? 0);
        }
        return $map;
    }

    /**
     * Resolve own-account userId for each tree row.
     * Direct Client id = clientUsers.id; Sub-IB / Self = ibPartners.userId.
     *
     * @return array{flat: array, rowUserIds: array<string,int>}
     */
    private function buildTree($ibPartnerId, $search, $db) {
        $flat = [];
        $recurse = function ($parentId, $depth) use (&$recurse, &$flat) {
            $children = $this->ibPartnerBindModel->getDirectBindChildren($parentId);
            usort($children, function ($a, $b) {
                $aIsClient = ($a['type'] ?? '') === 'Direct Client' ? 0 : 1;
                $bIsClient = ($b['type'] ?? '') === 'Direct Client' ? 0 : 1;
                if ($aIsClient !== $bIsClient) {
                    return $aIsClient <=> $bIsClient;
                }
                return ((int) $a['id']) <=> ((int) $b['id']);
            });
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
                    'tierLevel' => null,
                    'tierName' => null,
                    'totalDeposit' => 0.0,
                    'totalWithdraw' => 0.0,
                    'netDeposit' => 0.0,
                ];
                if ($type === 'Sub-IB' && $hasChildren) {
                    $recurse($childId, $depth + 1);
                }
            }
        };
        $recurse($ibPartnerId, 0);

        $selfUserId = 0;
        if ($this->isTopLevelIb($ibPartnerId)) {
            $selfInfo = $this->getCurrentIbDisplayInfo($ibPartnerId, $db);
            if ($selfInfo) {
                $selfUserId = (int)($selfInfo['userId'] ?? 0);
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
                    'tierLevel' => $selfInfo['tierLevel'] ?? null,
                    'tierName' => $selfInfo['tierName'] ?? null,
                    'badgeColor' => $selfInfo['badgeColor'] ?? null,
                    'totalDeposit' => 0.0,
                    'totalWithdraw' => 0.0,
                    'netDeposit' => 0.0,
                ]);
            }
        }

        // Map Sub-IB id -> ibPartners.userId + tier
        $subIbIds = array_values(array_unique(array_map(function ($r) {
            return (int) $r['id'];
        }, array_filter($flat, function ($r) {
            return ($r['type'] ?? '') === 'Sub-IB';
        }))));

        $subIbUserMap = [];
        $tierByPartnerId = [];
        if (!empty($subIbIds)) {
            $placeholders = [];
            $params = [];
            foreach ($subIbIds as $i => $sid) {
                $key = 'sib' . $i;
                $placeholders[] = ':' . $key;
                $params[$key] = $sid;
            }
            $rows = $db->fetchAll(
                "SELECT ib.id, ib.userId, tl.tierLevel AS tierLevel, tl.tierName AS tierName, tl.badgeColor AS badgeColor
                 FROM ibPartners ib
                 LEFT JOIN ibTierLevels tl ON tl.id = ib.tierLevelId
                 WHERE ib.id IN (" . implode(',', $placeholders) . ")",
                $params
            );
            foreach ($rows as $row) {
                $pid = (int)$row['id'];
                $subIbUserMap[$pid] = (int)($row['userId'] ?? 0);
                $tierByPartnerId[$pid] = [
                    'tierLevel' => isset($row['tierLevel']) && $row['tierLevel'] !== null && $row['tierLevel'] !== ''
                        ? (int)$row['tierLevel']
                        : null,
                    'tierName' => $row['tierName'] ?? null,
                    'badgeColor' => $row['badgeColor'] ?? null,
                ];
            }
        }
        if ($selfUserId > 0) {
            $subIbUserMap[(int)$ibPartnerId] = $selfUserId;
        }

        foreach ($flat as $idx => $row) {
            if (($row['type'] ?? '') !== 'Sub-IB') {
                continue;
            }
            $pid = (int)$row['id'];
            if (!isset($tierByPartnerId[$pid])) {
                continue;
            }
            $flat[$idx]['tierLevel'] = $tierByPartnerId[$pid]['tierLevel'];
            $flat[$idx]['tierName'] = $tierByPartnerId[$pid]['tierName'];
            $flat[$idx]['badgeColor'] = $tierByPartnerId[$pid]['badgeColor'];
        }

        $rowUserIds = [];
        foreach ($flat as $row) {
            $key = ($row['type'] ?? '') . '#' . (int)$row['id'];
            if (($row['type'] ?? '') === 'Direct Client') {
                $rowUserIds[$key] = (int)$row['id'];
            } else {
                $rowUserIds[$key] = $subIbUserMap[(int)$row['id']] ?? 0;
            }
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $keep = [];
            foreach ($flat as $row) {
                $name = mb_strtolower(trim($row['referralName'] ?? '') . ' ' . ($row['email'] ?? '') . ' ' . ($row['referralCode'] ?? ''));
                if (strpos($name, $needle) !== false) {
                    $keep[(int)$row['id'] . '#' . ($row['type'] ?? '')] = true;
                }
            }
            $ancestors = [];
            foreach ($flat as $row) {
                $k = (int)$row['id'] . '#' . ($row['type'] ?? '');
                if (!empty($keep[$k])) {
                    $pid = (int)($row['parentId'] ?? 0);
                    while ($pid > 0) {
                        $ancestors[$pid] = true;
                        $parentRow = null;
                        foreach ($flat as $r) {
                            if ((int)$r['id'] === $pid && ($r['type'] ?? '') === 'Sub-IB') {
                                $parentRow = $r;
                                break;
                            }
                        }
                        $pid = $parentRow ? (int)($parentRow['parentId'] ?? 0) : 0;
                    }
                }
            }
            $flat = array_values(array_filter($flat, function ($row) use ($keep, $ancestors) {
                $k = (int)$row['id'] . '#' . ($row['type'] ?? '');
                $id = (int)$row['id'];
                return !empty($keep[$k]) || !empty($ancestors[$id]);
            }));
            $filteredKeys = [];
            foreach ($flat as $row) {
                $filteredKeys[($row['type'] ?? '') . '#' . (int)$row['id']] = true;
            }
            $rowUserIds = array_intersect_key($rowUserIds, $filteredKeys);
        }

        return ['flat' => $flat, 'rowUserIds' => $rowUserIds];
    }

    private function attachAmounts(array $flat, array $rowUserIds, $db, $startDate, $endDate) {
        $userIds = array_values($rowUserIds);
        $depositMap = $this->sumAmountsByUserIds($db, 'deposits', $userIds, $startDate, $endDate);
        $withdrawMap = $this->sumAmountsByUserIds($db, 'withdrawals', $userIds, $startDate, $endDate);

        foreach ($flat as &$row) {
            $key = ($row['type'] ?? '') . '#' . (int)$row['id'];
            $userId = (int)($rowUserIds[$key] ?? 0);
            $deposit = $userId > 0 ? (float)($depositMap[$userId] ?? 0) : 0.0;
            $withdraw = $userId > 0 ? (float)($withdrawMap[$userId] ?? 0) : 0.0;
            $row['totalDeposit'] = $deposit;
            $row['totalWithdraw'] = $withdraw;
            $row['netDeposit'] = $deposit - $withdraw;
        }
        unset($row);

        return $flat;
    }

    /**
     * GET /api/client/deposit-withdraw-report/list
     */
    public function list() {
        try {
            $currentUserId = $this->getCurrentUserId();
            $ibPartner = $this->getReportIbPartner($currentUserId);
            $this->respondListForIb((int)$ibPartner['id']);
        } catch (Exception $e) {
            Response::error('Failed to fetch deposit withdraw list: ' . $e->getMessage(), 500);
        }
    }

    private function respondListForIb($ibPartnerId) {
        $ibPartnerId = (int)$ibPartnerId;
        $startDate = !empty($_GET['start_date']) ? $this->convertDateToServerTimezone($_GET['start_date']) : null;
        $endDate = !empty($_GET['end_date']) ? $this->convertEndDateToServerTimezone($_GET['end_date']) : null;
        $search = trim((string)($_GET['search'] ?? ''));
        $db = Database::getInstance();

        $built = $this->buildTree($ibPartnerId, $search, $db);
        $items = $this->attachAmounts($built['flat'], $built['rowUserIds'], $db, $startDate, $endDate);

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
            return strcmp($a, $b);
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
                'tierLevel' => isset($item['tierLevel']) && $item['tierLevel'] !== null && $item['tierLevel'] !== ''
                    ? (int)$item['tierLevel']
                    : null,
                'tierName' => $item['tierName'] ?? null,
                'badgeColor' => $item['badgeColor'] ?? null,
                'totalDeposit' => (float)($item['totalDeposit'] ?? 0),
                'totalWithdraw' => (float)($item['totalWithdraw'] ?? 0),
                'netDeposit' => (float)($item['netDeposit'] ?? 0),
                'referralName' => $name,
            ];
        }, $itemsForPage);

        Response::paginated($formattedItems, $totalDirect, $page, $perPage);
    }

    /**
     * Build full tree with amounts (used by export service).
     */
    public function buildReportRows($ibPartnerId, $startDate, $endDate, $search, $db) {
        $built = $this->buildTree((int)$ibPartnerId, trim((string)$search), $db);
        return $this->attachAmounts($built['flat'], $built['rowUserIds'], $db, $startDate, $endDate);
    }

    /**
     * Build one flat row per deposit/withdrawal transaction across the whole
     * tree (used by export service). Mirrors the on-screen detail view: every
     * status/source, filtered only by requestedAt within the date range.
     */
    public function buildDetailRows($ibPartnerId, $startDate, $endDate, $search, $db) {
        $built = $this->buildTree((int)$ibPartnerId, trim((string)$search), $db);
        $flat = $built['flat'];
        $rowUserIds = $built['rowUserIds'];
        $userIds = array_values($rowUserIds);

        $depositMap = $this->fetchAllTransactionsByUserIds('deposits', $userIds, $startDate, $endDate, $db);
        $withdrawMap = $this->fetchAllTransactionsByUserIds('withdrawals', $userIds, $startDate, $endDate, $db);

        $detailRows = [];
        foreach ($flat as $row) {
            $key = ($row['type'] ?? '') . '#' . (int)$row['id'];
            $userId = (int)($rowUserIds[$key] ?? 0);
            $name = trim((string)($row['referralName'] ?? '')) ?: ((string)($row['email'] ?? '—'));
            $typeLabel = !empty($row['isSelf']) ? 'Self' : (string)($row['type'] ?? '');
            $levelLabel = $this->resolveLevelLabel($row);
            $deposits = $userId > 0 ? ($depositMap[$userId] ?? []) : [];
            $withdrawals = $userId > 0 ? ($withdrawMap[$userId] ?? []) : [];

            foreach ($deposits as $t) {
                $detailRows[] = $this->makeDetailExportRow($row, $name, $typeLabel, $levelLabel, 'Deposit', $t);
            }
            foreach ($withdrawals as $t) {
                $detailRows[] = $this->makeDetailExportRow($row, $name, $typeLabel, $levelLabel, 'Withdrawal', $t);
            }
        }

        return $detailRows;
    }

    private function resolveLevelLabel(array $row) {
        if (($row['type'] ?? '') === 'Direct Client') {
            return 'Client';
        }
        $name = trim((string)($row['tierName'] ?? ''));
        return $name !== '' ? $name : '—';
    }

    private function makeDetailExportRow(array $row, $name, $typeLabel, $levelLabel, $direction, array $t) {
        return [
            'id' => (int)($row['id'] ?? 0),
            'type' => (string)($row['type'] ?? ''),
            'referralName' => $name,
            'typeLabel' => $typeLabel,
            'levelLabel' => $levelLabel,
            'direction' => $direction,
            'date' => $t['date'] ?? '—',
            'transactionId' => $t['transactionId'] ?? '—',
            'accountNumber' => $t['accountNumber'] ?? '',
            'currencyCode' => $t['currencyCode'] ?? '',
            'amount' => (float)($t['amount'] ?? 0),
            'status' => $t['status'] ?? 'pending',
            'handledBy' => $t['handledBy'] ?? '—',
            'handledAt' => $t['handledAt'] ?? '—',
        ];
    }

    /**
     * Batch-fetch every deposit/withdrawal transaction for a set of userIds,
     * grouped by userId. Same shape/rules as fetchTransactionHistory items,
     * but unpaginated (all rows) for the export.
     *
     * @return array<int,array> userId => list of transaction items
     */
    private function fetchAllTransactionsByUserIds($table, array $userIds, $startDate, $endDate, $db) {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), function ($id) {
            return $id > 0;
        })));
        if (empty($userIds) || !in_array($table, ['deposits', 'withdrawals'], true)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($userIds as $i => $uid) {
            $key = 'uid' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $uid;
        }

        $where = "{$table}.userId IN (" . implode(',', $placeholders) . ")";
        if ($startDate !== null) {
            $where .= " AND {$table}.requestedAt >= :startDate";
            $params['startDate'] = $startDate;
        }
        if ($endDate !== null) {
            $where .= " AND {$table}.requestedAt <= :endDate";
            $params['endDate'] = $endDate;
        }

        $sql = "SELECT {$table}.userId, {$table}.transactionId, {$table}.amount, {$table}.currencyCode, {$table}.status, {$table}.requestedAt,
                       {$table}.approvedAt, {$table}.rejectedAt,
                       (SELECT providerAccountId FROM tradingAccountExternalAccounts WHERE tradingAccountId = {$table}.tradingAccountId LIMIT 1) AS accountNumber,
                       COALESCE(NULLIF(TRIM(aa.fullName), ''), aa.username) AS approvedByName,
                       COALESCE(NULLIF(TRIM(ra.fullName), ''), ra.username) AS rejectedByName
                FROM {$table}
                LEFT JOIN adminUsers aa ON aa.id = {$table}.approvedBy
                LEFT JOIN adminUsers ra ON ra.id = {$table}.rejectedBy
                WHERE {$where}
                ORDER BY {$table}.requestedAt DESC";
        $rows = $db->fetchAll($sql, $params);

        $map = [];
        foreach ($rows as $row) {
            $uid = (int)($row['userId'] ?? 0);
            $status = strtolower((string)($row['status'] ?? ''));
            $handledBy = $status === 'rejected'
                ? ($row['rejectedByName'] ?? '')
                : ($row['approvedByName'] ?? '');
            $handledBy = trim((string)$handledBy);
            $handledAtRaw = $status === 'rejected'
                ? ($row['rejectedAt'] ?? null)
                : ($row['approvedAt'] ?? null);
            $map[$uid][] = [
                'date' => !empty($row['requestedAt']) ? date('M d, Y', strtotime($row['requestedAt'])) : '—',
                'transactionId' => $row['transactionId'] ?? '—',
                'amount' => (float)($row['amount'] ?? 0),
                'currencyCode' => $row['currencyCode'] ?? '',
                'accountNumber' => $row['accountNumber'] ?? '',
                'status' => $row['status'] ?? 'pending',
                'handledBy' => $handledBy !== '' ? $handledBy : '—',
                'handledAt' => !empty($handledAtRaw) ? date('M d, Y', strtotime($handledAtRaw)) : '—',
            ];
        }

        return $map;
    }

    /**
     * Resolve the own-account clientUsers.id for a referral row, validating it
     * belongs to the report IB's tree (prevents accessing arbitrary users).
     */
    private function resolveReferralUserId($ibPartnerId, $referralId, $type, $db) {
        $ibPartnerId = (int)$ibPartnerId;
        $referralId = (int)$referralId;

        if ($type === 'Sub-IB') {
            $allowedIbIds = array_map('intval', $this->ibPartnerBindModel->getDescendantIbPartnerIds($ibPartnerId, true));
            if (!in_array($referralId, $allowedIbIds, true)) {
                return 0;
            }
            $row = $db->fetchOne('SELECT userId FROM ibPartners WHERE id = :id LIMIT 1', ['id' => $referralId]);
            return (int)($row['userId'] ?? 0);
        }

        // Direct Client: referralId is clientUsers.id and must sit under the IB tree
        $allowedClientIds = array_map('intval', $this->ibPartnerBindModel->getClientIdsUnderIbTree($ibPartnerId));
        if (!in_array($referralId, $allowedClientIds, true)) {
            return 0;
        }
        return $referralId;
    }

    /**
     * GET /api/client/deposit-withdraw-report/detail
     * Full deposit + withdrawal history (all statuses, all sources) for a referral,
     * filtered only by createdAt within the selected date range.
     */
    public function detail() {
        try {
            $currentUserId = $this->getCurrentUserId();
            $ibPartner = $this->getReportIbPartner($currentUserId);
            $ibPartnerId = (int)$ibPartner['id'];

            $referralId = (int)($_GET['referral_id'] ?? 0);
            $type = trim((string)($_GET['type'] ?? ''));
            if ($referralId <= 0 || !in_array($type, ['Direct Client', 'Sub-IB'], true)) {
                Response::validationError(['referral_id' => 'referral_id and a valid type are required']);
            }

            $db = Database::getInstance();
            $targetUserId = $this->resolveReferralUserId($ibPartnerId, $referralId, $type, $db);
            if ($targetUserId <= 0) {
                Response::error('Referral not found', 404);
            }

            $startDate = !empty($_GET['start_date']) ? $this->convertDateToServerTimezone($_GET['start_date']) : null;
            $endDate = !empty($_GET['end_date']) ? $this->convertEndDateToServerTimezone($_GET['end_date']) : null;

            $perPage = (int)($_GET['per_page'] ?? 20);
            if ($perPage < 1) $perPage = 20;
            if ($perPage > 100) $perPage = 100;

            $depositPage = max(1, (int)($_GET['deposit_page'] ?? 1));
            $withdrawPage = max(1, (int)($_GET['withdraw_page'] ?? 1));

            $deposits = $this->fetchTransactionHistory('deposits', $targetUserId, $startDate, $endDate, $depositPage, $perPage, $db);
            $withdrawals = $this->fetchTransactionHistory('withdrawals', $targetUserId, $startDate, $endDate, $withdrawPage, $perPage, $db);

            Response::success([
                'deposits' => $deposits,
                'withdrawals' => $withdrawals,
            ]);
        } catch (Exception $e) {
            Response::error('Failed to fetch detail: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Fetch one paginated transaction-history section (deposits OR withdrawals)
     * for a user, filtered by createdAt. All statuses / sources are included.
     */
    private function fetchTransactionHistory($table, $userId, $startDate, $endDate, $page, $perPage, $db) {
        $allowedTables = ['deposits', 'withdrawals'];
        if (!in_array($table, $allowedTables, true)) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 1];
        }

        $params = ['uid' => (int)$userId];
        $where = 'userId = :uid';
        if ($startDate !== null) {
            $where .= ' AND requestedAt >= :startDate';
            $params['startDate'] = $startDate;
        }
        if ($endDate !== null) {
            $where .= ' AND requestedAt <= :endDate';
            $params['endDate'] = $endDate;
        }

        $countRow = $db->fetchOne("SELECT COUNT(*) AS total FROM {$table} WHERE {$where}", $params);
        $total = (int)($countRow['total'] ?? 0);

        // Amount totals grouped by status across the whole filtered set (not just this page)
        $statusRows = $db->fetchAll(
            "SELECT status, COUNT(*) AS cnt, SUM(amount) AS amountTotal FROM {$table} WHERE {$where} GROUP BY status",
            $params
        );
        $statusTotals = array_map(function ($r) {
            return [
                'status' => $r['status'] ?? 'pending',
                'count' => (int)($r['cnt'] ?? 0),
                'total' => (float)($r['amountTotal'] ?? 0),
            ];
        }, $statusRows);
        $amountTotal = array_sum(array_column($statusTotals, 'total'));

        $limit = (int)$perPage;
        $offset = ((int)$page - 1) * $limit;
        if ($offset < 0) $offset = 0;

        $sql = "SELECT {$table}.transactionId, {$table}.amount, {$table}.currencyCode, {$table}.status, {$table}.requestedAt,
                       {$table}.approvedAt, {$table}.rejectedAt,
                       (SELECT providerAccountId FROM tradingAccountExternalAccounts WHERE tradingAccountId = {$table}.tradingAccountId LIMIT 1) AS accountNumber,
                       COALESCE(NULLIF(TRIM(aa.fullName), ''), aa.username) AS approvedByName,
                       COALESCE(NULLIF(TRIM(ra.fullName), ''), ra.username) AS rejectedByName
                FROM {$table}
                LEFT JOIN adminUsers aa ON aa.id = {$table}.approvedBy
                LEFT JOIN adminUsers ra ON ra.id = {$table}.rejectedBy
                WHERE {$where}
                ORDER BY {$table}.requestedAt DESC
                LIMIT {$limit} OFFSET {$offset}";
        $rows = $db->fetchAll($sql, $params);

        $items = array_map(function ($row) {
            $status = strtolower((string)($row['status'] ?? ''));
            $handledBy = $status === 'rejected'
                ? ($row['rejectedByName'] ?? '')
                : ($row['approvedByName'] ?? '');
            $handledBy = trim((string)$handledBy);
            $handledAtRaw = $status === 'rejected'
                ? ($row['rejectedAt'] ?? null)
                : ($row['approvedAt'] ?? null);
            return [
                'date' => !empty($row['requestedAt']) ? date('M d, Y', strtotime($row['requestedAt'])) : '—',
                'transactionId' => $row['transactionId'] ?? '—',
                'amount' => (float)($row['amount'] ?? 0),
                'currencyCode' => $row['currencyCode'] ?? '',
                'accountNumber' => $row['accountNumber'] ?? '',
                'status' => $row['status'] ?? 'pending',
                'handledBy' => $handledBy !== '' ? $handledBy : '—',
                'handledAt' => !empty($handledAtRaw) ? date('M d, Y', strtotime($handledAtRaw)) : '—',
            ];
        }, $rows);

        return [
            'items' => $items,
            'total' => $total,
            'page' => (int)$page,
            'per_page' => $perPage,
            'total_pages' => (int)max(1, ceil($total / $perPage)),
            'statusTotals' => $statusTotals,
            'amountTotal' => (float)$amountTotal,
        ];
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

    public function exportActive()
    {
        try {
            require_once __DIR__ . '/../services/ClientDepositWithdrawReportExportService.php';
            $currentUserId = (int)$this->getCurrentUserId();
            $progress = ClientDepositWithdrawReportExportService::getActiveForUser($currentUserId);
            Response::success($this->exportProgressPayload($progress));
        } catch (Exception $e) {
            Response::error('Failed to fetch export status: ' . $e->getMessage(), 500);
        }
    }

    public function export()
    {
        try {
            require_once __DIR__ . '/../services/ClientDepositWithdrawReportExportService.php';
            $currentUserId = (int)$this->getCurrentUserId();
            $ibPartner = $this->getReportIbPartner($currentUserId);

            $active = ClientDepositWithdrawReportExportService::getActiveForUser($currentUserId);
            $activeStatus = (string)($active['status'] ?? '');
            if (in_array($activeStatus, ['queued', 'running', 'cancelling'], true)) {
                Response::error('Export already in progress', 409, $this->exportProgressPayload($active));
            }
            if ($activeStatus === 'done') {
                ClientDepositWithdrawReportExportService::clearActive($currentUserId);
            }

            $rawBody = json_decode(file_get_contents('php://input'), true);
            $rawItems = (is_array($rawBody) && isset($rawBody['items']) && is_array($rawBody['items'])) ? $rawBody['items'] : [];
            $selectedItems = array_slice($rawItems, 0, 500);

            $jobId = str_replace('.', '', uniqid('dwr_', true));
            ClientDepositWithdrawReportExportService::ensureExportDir();
            ClientDepositWithdrawReportExportService::writeProgress($jobId, [
                'clientUserId' => $currentUserId,
                'status' => 'queued',
                'cancelRequested' => false,
                'percent' => 0,
                'processed' => 0,
                'total' => 0,
                'message' => 'Queued',
                'file' => $jobId . '.csv',
            ]);
            ClientDepositWithdrawReportExportService::writeActive($currentUserId, $jobId);

            $payload = [
                'type' => 'export_deposit_withdraw_report',
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
                ClientDepositWithdrawReportExportService::writeProgress($jobId, [
                    'clientUserId' => $currentUserId,
                    'status' => 'error',
                    'cancelRequested' => false,
                    'percent' => 0,
                    'message' => $e->getMessage(),
                    'file' => null,
                ]);
                ClientDepositWithdrawReportExportService::clearActive($currentUserId);
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

    public function exportStatus()
    {
        try {
            require_once __DIR__ . '/../services/ClientDepositWithdrawReportExportService.php';
            $currentUserId = (int)$this->getCurrentUserId();
            $jobId = isset($_GET['jobId']) ? trim((string)$_GET['jobId']) : '';
            if ($jobId === '') {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = ClientDepositWithdrawReportExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['clientUserId'] ?? 0) !== $currentUserId) {
                Response::notFound('Export job not found');
            }
            require_once __DIR__ . '/../services/ExportJobTimeoutReaper.php';
            $progress = ExportJobTimeoutReaper::reapIfStale(
                $jobId,
                $progress,
                [ClientDepositWithdrawReportExportService::class, 'writeProgress'],
                [ClientDepositWithdrawReportExportService::class, 'clearActive']
            );
            Response::success($this->exportProgressPayload($progress));
        } catch (Exception $e) {
            Response::error('Failed to fetch export status: ' . $e->getMessage(), 500);
        }
    }

    public function exportCancel()
    {
        try {
            require_once __DIR__ . '/../services/ClientDepositWithdrawReportExportService.php';
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
            $progress = ClientDepositWithdrawReportExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['clientUserId'] ?? 0) !== $currentUserId) {
                Response::notFound('Export job not found');
            }
            $status = (string)($progress['status'] ?? '');
            if ($status === 'cancelled') {
                Response::success($this->exportProgressPayload($progress), 'Already cancelled');
            }
            if ($status === 'error') {
                ClientDepositWithdrawReportExportService::clearActive($currentUserId);
                Response::success($this->exportProgressPayload($progress), 'Export already failed');
            }

            if ($status === 'queued') {
                ClientDepositWithdrawReportExportService::writeProgress($jobId, [
                    'clientUserId' => $currentUserId,
                    'status' => 'cancelled',
                    'cancelRequested' => true,
                    'percent' => 0,
                    'message' => 'Export cancelled',
                    'file' => null,
                ]);
                ClientDepositWithdrawReportExportService::clearActive($currentUserId);
                $updated = ClientDepositWithdrawReportExportService::readProgress($jobId);
                Response::success($this->exportProgressPayload($updated), 'Export cancelled');
            }

            ClientDepositWithdrawReportExportService::requestCancel($jobId);
            $updated = ClientDepositWithdrawReportExportService::readProgress($jobId);
            Response::success($this->exportProgressPayload($updated), 'Cancel requested');
        } catch (Exception $e) {
            Response::error('Failed to cancel export: ' . $e->getMessage(), 500);
        }
    }

    public function exportDownload()
    {
        try {
            require_once __DIR__ . '/../services/ClientDepositWithdrawReportExportService.php';
            $currentUserId = (int)$this->getCurrentUserId();
            $jobId = isset($_GET['jobId']) ? trim((string)$_GET['jobId']) : '';
            if ($jobId === '') {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = ClientDepositWithdrawReportExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['clientUserId'] ?? 0) !== $currentUserId) {
                Response::notFound('Export job not found');
            }
            if (($progress['status'] ?? '') !== 'done') {
                Response::error('Export is not ready', 400);
            }
            $csvFile = ClientDepositWithdrawReportExportService::csvPath($jobId);
            if (!file_exists($csvFile) || !is_readable($csvFile)) {
                Response::error('Export file missing', 404);
            }

            ClientDepositWithdrawReportExportService::clearActive($currentUserId);

            $filename = 'deposit_withdraw_report_' . date('Y-m-d') . '.csv';
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
