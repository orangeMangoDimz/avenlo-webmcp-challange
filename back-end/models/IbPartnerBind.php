<?php
/**
 * IB 代理上下级绑定关系
 * 对应表：ib_partner_bind
 * - IB 绑定 IB：parentId, childId(ib), childClientId=NULL, isClient=0
 * - IB 绑定普通客户：parentId, childId=NULL, childClientId(clientUsers.id), isClient=1
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/IbProgramSetting.php';

class IbPartnerBind extends BaseModel {
    protected $table = 'ib_partner_bind';
    protected $primaryKey = 'id';

    /**
     * 批量添加 IB 绑定：parentId 与多个 childId（IB）建立关系
     * @param int $parentId 父级 IB id
     * @param int[] $childIds 子级 IB id 列表
     * @return int 实际插入条数
     */
    public function addBinds($parentId, array $childIds) {
        $parentId = (int) $parentId;
        if ($parentId <= 0 || empty($childIds)) {
            return 0;
        }
        $inserted = 0;
        $parentTier = $this->getParentTierLevel($parentId);
        foreach ($childIds as $childId) {
            $childId = (int) $childId;
            if ($childId <= 0 || $childId === $parentId) {
                continue;
            }
            $childTier = $this->getChildIbTierLevel($childId);
            $row = [
                'parentId' => $parentId,
                'childId' => $childId,
                'childClientId' => null,
                'isClient' => 0,
                'parentTierLevel' => $parentTier,
                'childTierLevel' => $childTier
            ];
            try {
                $this->db->insert($this->table, $row);
                $inserted++;
            } catch (\Throwable $e) {
                if (strpos($e->getMessage(), 'Duplicate') === false && strpos($e->getMessage(), '1062') === false) {
                    throw $e;
                }
            }
        }
        return $inserted;
    }

    /**
     * 按 clientIds 从任意 parent 解除客户绑定（保证每个普通用户只绑一个 IB）
     * @param int[] $clientIds clientUsers.id 列表
     * @return int[] 被解绑的 parentId 列表（用于同步 totalClients）
     */
    public function removeClientBindsByClientIds(array $clientIds) {
        $clientIds = array_map('intval', array_filter($clientIds, function ($v) { return $v > 0; }));
        if (empty($clientIds)) {
            return [];
        }
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT parentId FROM ' . $this->table . ' WHERE childClientId IN (' . implode(',', array_fill(0, count($clientIds), '?')) . ') AND isClient = 1',
            $clientIds
        );
        $affectedParentIds = array_map(function ($r) { return (int) $r['parentId']; }, $rows);
        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
        $this->db->query(
            "DELETE FROM {$this->table} WHERE childClientId IN ({$placeholders}) AND isClient = 1",
            $clientIds
        );
        return $affectedParentIds;
    }

    /**
     * 批量添加客户绑定：仅写入 ib_partner_bind（不再写 clientIbPartnerAssignments）
     * 调用前应先 removeClientBindsByClientIds(clientIds) 保证一客户一 IB
     * @param int $parentId 父级 IB id
     * @param int[] $clientIds clientUsers.id 列表
     * @return int 实际插入条数
     */
    public function addClientBinds($parentId, array $clientIds) {
        $parentId = (int) $parentId;
        if ($parentId <= 0 || empty($clientIds)) {
            return 0;
        }
        $clientIds = array_map('intval', array_filter($clientIds, function ($v) { return $v > 0; }));
        if (empty($clientIds)) {
            return 0;
        }
        $parentTier = $this->getParentTierLevel($parentId);
        $inserted = 0;
        foreach ($clientIds as $clientId) {
            try {
                $this->db->insert($this->table, [
                    'parentId' => $parentId,
                    'childId' => null,
                    'childClientId' => $clientId,
                    'isClient' => 1,
                    'parentTierLevel' => $parentTier,
                    'childTierLevel' => null
                ]);
                $inserted++;
            } catch (\Throwable $e) {
                if (strpos($e->getMessage(), 'Duplicate') === false && strpos($e->getMessage(), '1062') === false) {
                    throw $e;
                }
            }
        }
        return $inserted;
    }

    /**
     * 批量解除 IB 绑定
     */
    public function removeBinds($parentId, array $childIds) {
        $parentId = (int) $parentId;
        if ($parentId <= 0 || empty($childIds)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($childIds), '?'));
        $stmt = $this->db->query(
            "DELETE FROM {$this->table} WHERE parentId = ? AND childId IN ({$placeholders})",
            array_merge([$parentId], array_map('intval', $childIds))
        );
        return $stmt->rowCount();
    }

    /**
     * 批量解除客户绑定（从 ib_partner_bind 删除）
     */
    public function removeClientBinds($parentId, array $clientIds) {
        $parentId = (int) $parentId;
        if ($parentId <= 0 || empty($clientIds)) {
            return 0;
        }
        $clientIds = array_map('intval', array_filter($clientIds, function ($v) { return $v > 0; }));
        if (empty($clientIds)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
        $stmt = $this->db->query(
            "DELETE FROM {$this->table} WHERE parentId = ? AND childClientId IN ({$placeholders}) AND isClient = 1",
            array_merge([$parentId], $clientIds)
        );
        return $stmt->rowCount();
    }

    private function getParentTierLevel($parentId) {
        $row = $this->db->fetchOne(
            'SELECT tl.tierLevel FROM ibPartners ib LEFT JOIN ibTierLevels tl ON ib.tierLevelId = tl.id WHERE ib.id = :id LIMIT 1',
            ['id' => $parentId]
        );
        return $row && isset($row['tierLevel']) ? (int) $row['tierLevel'] : null;
    }

    private function getChildIbTierLevel($childId) {
        $row = $this->db->fetchOne(
            'SELECT tl.tierLevel FROM ibPartners ib LEFT JOIN ibTierLevels tl ON ib.tierLevelId = tl.id WHERE ib.id = :id LIMIT 1',
            ['id' => $childId]
        );
        return $row && isset($row['tierLevel']) ? (int) $row['tierLevel'] : null;
    }

    // ---------- 基于 ib_partner_bind 的层级/客户数（供报表与 Dashboard 调用）----------

    /**
     * 获取直接下级IB数量（isClient=0）
     */
    public function getDirectSubIbsCount($ibPartnerId, $beforeDate = null) {
        $params = ['ibPartnerId' => $ibPartnerId];
        $where = "WHERE parentId = :ibPartnerId AND isClient = 0";
        if ($beforeDate) {
            $where .= " AND (createdAt <= :beforeDate OR createdAt IS NULL)";
            $params['beforeDate'] = $beforeDate;
        }
        $result = $this->db->fetchOne("SELECT COUNT(*) AS count FROM {$this->table} {$where}", $params);
        return (int)($result['count'] ?? 0);
    }

    /**
     * 获取直接下级IB列表（isClient=0），返回与 IbNetworkHierarchy::getDirectSubIbs 兼容结构
     */
    public function getDirectSubIbs($ibPartnerId) {
        $sql = "SELECT b.id, b.childId AS subIbPartnerId, b.parentId AS parentIbPartnerId, 2 AS tier, b.createdAt AS establishedAt
                FROM {$this->table} b
                WHERE b.parentId = :ibPartnerId AND b.isClient = 0
                ORDER BY b.createdAt DESC";
        return $this->db->fetchAll($sql, ['ibPartnerId' => $ibPartnerId]);
    }

    /**
     * 获取所有下级IB（递归，isClient=0），返回与 IbNetworkHierarchy::getAllSubIbs 兼容结构
     */
    public function getAllSubIbs($ibPartnerId, $beforeDate = null) {
        try {
            $hierarchySql = "SELECT parentId AS parentIbPartnerId, childId AS ibPartnerId, id, createdAt AS establishedAt FROM {$this->table} WHERE isClient = 0 AND childId IS NOT NULL";
            if ($beforeDate) {
                $hierarchySql .= " AND (createdAt <= :beforeDate OR createdAt IS NULL)";
            }
            $hierarchyData = $this->db->fetchAll($hierarchySql, $beforeDate ? ['beforeDate' => $beforeDate] : []);

            $childrenMap = [];
            foreach ($hierarchyData as $row) {
                $parentId = (int)$row['parentIbPartnerId'];
                $childId = (int)$row['ibPartnerId'];
                if (!isset($childrenMap[$parentId])) $childrenMap[$parentId] = [];
                $childrenMap[$parentId][] = [
                    'id' => (int)$row['id'],
                    'ibPartnerId' => $childId,
                    'parentIbPartnerId' => $parentId,
                    'tier' => 2
                ];
            }

            $maxDepth = IbProgramSetting::resolveNetworkMaxDepth();
            $allSubIbs = [];
            $path = [];
            $this->bindGetSubIbsRecursive($ibPartnerId, $childrenMap, $allSubIbs, $path, $maxDepth, 0);

            usort($allSubIbs, function($a, $b) {
                if ($a['tier'] != $b['tier']) return $a['tier'] - $b['tier'];
                return $a['level'] - $b['level'];
            });
            return $allSubIbs;
        } catch (\Throwable $e) {
            error_log("IbPartnerBind::getAllSubIbs: " . $e->getMessage());
            return $this->getDirectSubIbs($ibPartnerId);
        }
    }

    private function bindGetSubIbsRecursive($ibPartnerId, &$childrenMap, &$allSubIbs, &$path, $maxDepth, $currentDepth) {
        if ($currentDepth >= $maxDepth || in_array($ibPartnerId, $path)) return;
        $path[] = $ibPartnerId;
        if (isset($childrenMap[$ibPartnerId])) {
            foreach ($childrenMap[$ibPartnerId] as $childData) {
                $childId = $childData['ibPartnerId'];
                $allSubIbs[] = [
                    'id' => $childData['id'],
                    'ibPartnerId' => $childId,
                    'parentIbPartnerId' => $childData['parentIbPartnerId'],
                    'tier' => $childData['tier'],
                    'level' => $currentDepth + 1
                ];
                $this->bindGetSubIbsRecursive($childId, $childrenMap, $allSubIbs, $path, $maxDepth, $currentDepth + 1);
            }
        }
        array_pop($path);
    }

    /**
     * 通过 ib_partner_bind 递归统计：某 IB 下所有直接+间接的「子 IB（仅 status=approved）+ 普通 Client」总数。
     * 用于客户端 Commission Report 的 Total Referrals / Clients Referred / Detail Total Clients。
     *
     * @param int $ibPartnerId 当前 IB 的 ibPartners.id
     * @param string|null $beforeDate 仅统计 createdAt <= 此日期的绑定（用于上周期对比，格式 Y-m-d H:i:s）
     * @return int
     */
    public function getApprovedReferralsCountUnderIb($ibPartnerId, $beforeDate = null) {
        $ibPartnerId = (int) $ibPartnerId;
        if ($ibPartnerId <= 0) {
            return 0;
        }
        $sql = 'SELECT parentId, childId, childClientId, isClient FROM ' . $this->table;
        $params = [];
        if ($beforeDate !== null && $beforeDate !== '') {
            $sql .= ' WHERE (createdAt IS NULL OR createdAt <= :beforeDate)';
            $params['beforeDate'] = $beforeDate;
        }
        $rows = $this->db->fetchAll($sql, $params);
        $children = []; // parentId => [childId, ...] (only isClient=0)
        $clientCountByParent = []; // parentId => count of isClient=1
        foreach ($rows as $r) {
            $pid = (int) $r['parentId'];
            if ($pid <= 0) continue;
            if ((int)($r['isClient'] ?? 0) === 1) {
                $clientCountByParent[$pid] = ($clientCountByParent[$pid] ?? 0) + 1;
            } else {
                $cid = isset($r['childId']) ? (int) $r['childId'] : 0;
                if ($cid > 0) {
                    if (!isset($children[$pid])) $children[$pid] = [];
                    $children[$pid][] = $cid;
                }
            }
        }
        $descendantIds = [$ibPartnerId];
        $added = true;
        $maxIter = 50;
        while ($added && $maxIter-- > 0) {
            $added = false;
            foreach ($descendantIds as $id) {
                foreach (isset($children[$id]) ? $children[$id] : [] as $c) {
                    if (!in_array($c, $descendantIds, true)) {
                        $descendantIds[] = $c;
                        $added = true;
                    }
                }
            }
        }
        $clientTotal = 0;
        foreach ($descendantIds as $id) {
            $clientTotal += $clientCountByParent[$id] ?? 0;
        }
        $subIbIds = [];
        foreach ($descendantIds as $id) {
            foreach (isset($children[$id]) ? $children[$id] : [] as $c) {
                $subIbIds[$c] = true;
            }
        }
        $subIbIds = array_keys($subIbIds);
        if (empty($subIbIds)) {
            return $clientTotal;
        }
        $placeholders = implode(',', array_fill(0, count($subIbIds), '?'));
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM ibPartners WHERE id IN ({$placeholders}) AND status = 'approved'",
            $subIbIds
        );
        $approvedSubIb = (int) ($row['cnt'] ?? 0);
        return (int) ($clientTotal + $approvedSubIb);
    }

    /**
     * 获取某 IB 下所有直接+间接子 IB 的 id 集合（用于列表查全部子 IB）
     *
     * @param int $ibPartnerId 当前 IB 的 ibPartners.id
     * @param bool $includeSelf 是否包含自身（true=含自身，供 commission 范围；false=仅子级供 Sub-IB 列表行）
     * @return int[]
     */
    public function getDescendantIbPartnerIds($ibPartnerId, $includeSelf = true) {
        $ibPartnerId = (int) $ibPartnerId;
        if ($ibPartnerId <= 0) {
            return $includeSelf ? [$ibPartnerId] : [];
        }
        $rows = $this->db->fetchAll('SELECT parentId, childId FROM ' . $this->table . ' WHERE isClient = 0 AND childId IS NOT NULL', []);
        $children = [];
        foreach ($rows as $r) {
            $pid = (int) $r['parentId'];
            $cid = isset($r['childId']) ? (int) $r['childId'] : 0;
            if ($pid <= 0 || $cid <= 0) continue;
            if (!isset($children[$pid])) $children[$pid] = [];
            $children[$pid][] = $cid;
        }
        $descendantIds = [$ibPartnerId];
        $added = true;
        $maxIter = 50;
        while ($added && $maxIter-- > 0) {
            $added = false;
            foreach ($descendantIds as $id) {
                foreach (isset($children[$id]) ? $children[$id] : [] as $c) {
                    if (!in_array($c, $descendantIds, true)) {
                        $descendantIds[] = $c;
                        $added = true;
                    }
                }
            }
        }
        if (!$includeSelf) {
            $descendantIds = array_values(array_filter($descendantIds, function ($id) use ($ibPartnerId) { return $id !== $ibPartnerId; }));
        }
        return $descendantIds;
    }

    /**
     * 获取某 IB 的直接绑定子节点（Sub-IB + Direct Client），用于层级列表展示。
     * 返回顺序：先 Sub-IB（按 id），再 Direct Client（按 childClientId）。
     *
     * @param int $ibPartnerId 父级 ibPartners.id
     * @return array[] 每项 ['type'=>'Sub-IB'|'Direct Client', 'id'=>int, 'referralName'=>'', 'email'=>'', 'referralCode'=>'']
     */
    public function getDirectBindChildren($ibPartnerId) {
        $ibPartnerId = (int) $ibPartnerId;
        if ($ibPartnerId <= 0) {
            return [];
        }
        $out = [];
        $subIbs = $this->db->fetchAll(
            "SELECT b.childId AS id, ib.companyName, ib.clientAlias, ib.adminAlias, ib.ibCode AS referralCode, ib.userId,
                    COALESCE(cu.email, ib.contactEmail) AS email,
                    TRIM(CONCAT(COALESCE(cu.firstName,''), ' ', COALESCE(cu.lastName,''))) AS userFullName
             FROM {$this->table} b
             INNER JOIN ibPartners ib ON ib.id = b.childId
             LEFT JOIN clientUsers cu ON cu.id = ib.userId
             WHERE b.parentId = :pid AND b.isClient = 0 AND b.childId IS NOT NULL
             ORDER BY b.childId ASC",
            ['pid' => $ibPartnerId]
        );
        foreach ($subIbs as $r) {
            // clientAlias 优先：客户端列表统一按管理员维护的客户端别名展示，回落到用户姓名/公司名/邮箱
            $displayName = trim($r['clientAlias'] ?? '')
                ?: trim($r['userFullName'] ?? '')
                ?: trim($r['companyName'] ?? '')
                ?: ($r['email'] ?? '—');
            $out[] = [
                'type' => 'Sub-IB',
                'id' => (int) $r['id'],
                // 该 Sub-IB 对应的 clientUsers.id，供前端跳转客户详情页使用
                'userId' => (int) ($r['userId'] ?? 0),
                'referralName' => $displayName,
                'email' => $r['email'] ?? '',
                'referralCode' => $r['referralCode'] ?? '',
                // Admin-only alias; client-facing callers pick fields explicitly and never expose it
                'adminAlias' => $r['adminAlias'] ?? '',
            ];
        }
        $clients = $this->db->fetchAll(
            "SELECT b.childClientId AS id, CONCAT(COALESCE(c.firstName,''), ' ', COALESCE(c.lastName,'')) AS referralName, c.email, c.id AS referralCode
             FROM {$this->table} b
             INNER JOIN clientUsers c ON c.id = b.childClientId
             WHERE b.parentId = :pid AND b.isClient = 1 AND b.childClientId IS NOT NULL
             ORDER BY b.childClientId ASC",
            ['pid' => $ibPartnerId]
        );
        foreach ($clients as $r) {
            $name = trim($r['referralName'] ?? '');
            $out[] = [
                'type' => 'Direct Client',
                'id' => (int) $r['id'],
                'referralName' => $name ?: ($r['email'] ?? '—'),
                'email' => $r['email'] ?? '',
                'referralCode' => (string) ($r['referralCode'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * 获取某 IB 树下所有直接+间接绑定的 Client id（childClientId）
     *
     * @param int $ibPartnerId 当前 IB 的 ibPartners.id
     * @return int[]
     */
    public function getClientIdsUnderIbTree($ibPartnerId) {
        $ibPartnerId = (int) $ibPartnerId;
        if ($ibPartnerId <= 0) {
            return [];
        }
        $descendantIds = $this->getDescendantIbPartnerIds($ibPartnerId, true);
        if (empty($descendantIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($descendantIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT childClientId FROM " . $this->table . " WHERE parentId IN ({$placeholders}) AND isClient = 1 AND childClientId IS NOT NULL",
            $descendantIds
        );
        $ids = [];
        foreach ($rows as $r) {
            $id = (int) ($r['childClientId'] ?? 0);
            if ($id > 0) $ids[] = $id;
        }
        return array_values(array_unique($ids));
    }

    /**
     * 获取父级IB代理（isClient=0），返回与 IbNetworkHierarchy::getParentIbPartner 兼容结构
     */
    public function getParentIbPartner($ibPartnerId) {
        $row = $this->db->fetchOne(
            "SELECT id, childId AS ibPartnerId, parentId AS parentIbPartnerId, createdAt AS establishedAt FROM {$this->table} WHERE childId = :ibPartnerId AND isClient = 0 LIMIT 1",
            ['ibPartnerId' => $ibPartnerId]
        );
        if ($row) {
            $row['hierarchyLevel'] = 2;
        }
        return $row;
    }

    /**
     * 获取普通客户直属 IB。
     *
     * @param int $clientId clientUsers.id
     * @return array|null ['ibPartnerId'=>int, 'bindId'=>int, 'establishedAt'=>string|null]
     */
    public function getDirectIbForClient($clientId) {
        $clientId = (int)$clientId;
        if ($clientId <= 0) {
            return null;
        }

        $row = $this->db->fetchOne(
            "SELECT id AS bindId, parentId AS ibPartnerId, createdAt AS establishedAt
             FROM {$this->table}
             WHERE childClientId = :clientId AND isClient = 1
             ORDER BY createdAt DESC, id DESC
             LIMIT 1",
            ['clientId' => $clientId]
        );

        return $row ?: null;
    }

    /**
     * 从指定 IB 反查直属到顶层的上级链路，包含当前 IB。
     *
     * @param int $ibPartnerId 起点 ibPartners.id
     * @param int $maxDepth 防止异常循环
     * @return int[] 从当前 IB 到顶层 IB 的 id 列表
     */
    public function getUplineIbPartnerIds($ibPartnerId, $maxDepth = null) {
        if ($maxDepth === null) {
            $maxDepth = IbProgramSetting::resolveNetworkMaxDepth();
        }
        $ibPartnerId = (int)$ibPartnerId;
        if ($ibPartnerId <= 0) {
            return [];
        }

        $ids = [];
        $seen = [];
        $currentId = $ibPartnerId;
        $depth = 0;

        while ($currentId > 0 && $depth < $maxDepth) {
            if (isset($seen[$currentId])) {
                break;
            }

            $ids[] = $currentId;
            $seen[$currentId] = true;
            $parent = $this->getParentIbPartner($currentId);
            $currentId = !empty($parent['parentIbPartnerId']) ? (int)$parent['parentIbPartnerId'] : 0;
            $depth++;
        }

        return $ids;
    }

    /**
     * 供报表使用：返回 [ibPartnerId => clientsCount]（基于 ib_partner_bind 递归）
     */
    public function getClientCountsMapRecursive() {
        $clientCountsMap = [];
        try {
            $hierarchySql = "SELECT parentId AS parentIbPartnerId, childId AS ibPartnerId FROM {$this->table} WHERE isClient = 0 AND childId IS NOT NULL";
            $hierarchyData = $this->db->fetchAll($hierarchySql, []);
            $childrenMap = [];
            $allIbIds = [];
            foreach ($hierarchyData as $row) {
                $parentId = (int)$row['parentIbPartnerId'];
                $childId = (int)$row['ibPartnerId'];
                if (!isset($childrenMap[$parentId])) $childrenMap[$parentId] = [];
                $childrenMap[$parentId][] = $childId;
                $allIbIds[$parentId] = true;
                $allIbIds[$childId] = true;
            }

            $directClientsSql = "SELECT parentId AS ibPartnerId, COUNT(DISTINCT childClientId) AS clientsCount FROM {$this->table} WHERE isClient = 1 GROUP BY parentId";
            $directClientsData = $this->db->fetchAll($directClientsSql, []);
            $directClientsMap = [];
            foreach ($directClientsData as $row) {
                $ibId = (int)$row['ibPartnerId'];
                $directClientsMap[$ibId] = (int)$row['clientsCount'];
                $allIbIds[$ibId] = true;
            }

            $maxDepth = IbProgramSetting::resolveNetworkMaxDepth();
            foreach (array_keys($allIbIds) as $ibPartnerId) {
                if (!isset($clientCountsMap[$ibPartnerId])) {
                    $path = [];
                    $clientCountsMap[$ibPartnerId] = $this->bindCountClientsRecursive($ibPartnerId, $childrenMap, $directClientsMap, $path, $maxDepth, 0);
                }
            }
        } catch (\Throwable $e) {
            error_log("IbPartnerBind::getClientCountsMapRecursive: " . $e->getMessage());
            $fallback = $this->db->fetchAll("SELECT parentId AS ibPartnerId, COUNT(DISTINCT childClientId) AS clientsReferred FROM {$this->table} WHERE isClient = 1 GROUP BY parentId", []);
            foreach ($fallback as $row) {
                $clientCountsMap[(int)$row['ibPartnerId']] = (int)$row['clientsReferred'];
            }
        }
        return $clientCountsMap;
    }

    private function bindCountClientsRecursive($ibPartnerId, &$childrenMap, &$directClientsMap, &$path, $maxDepth, $currentDepth) {
        if ($currentDepth >= $maxDepth || in_array($ibPartnerId, $path)) return 0;
        $path[] = $ibPartnerId;
        $count = isset($directClientsMap[$ibPartnerId]) ? $directClientsMap[$ibPartnerId] : 0;
        if (isset($childrenMap[$ibPartnerId])) {
            foreach ($childrenMap[$ibPartnerId] as $childId) {
                $count += $this->bindCountClientsRecursive($childId, $childrenMap, $directClientsMap, $path, $maxDepth, $currentDepth + 1);
            }
        }
        array_pop($path);
        return $count;
    }
}
