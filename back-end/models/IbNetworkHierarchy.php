<?php
/**
 * IB Network Hierarchy 模型
 * 对应表：ibNetworkHierarchy
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/IbProgramSetting.php';

class IbNetworkHierarchy extends BaseModel {
    protected $table = 'ibNetworkHierarchy';
    protected $primaryKey = 'id';

    protected $fillable = [
        'ibPartnerId', 'parentIbPartnerId', 'hierarchyLevel', 'establishedAt'
    ];

    /**
     * 获取直接下级IB数量
     */
    public function getDirectSubIbsCount($ibPartnerId, $beforeDate = null) {
        $params = ['ibPartnerId' => $ibPartnerId];
        $whereClause = "WHERE parentIbPartnerId = :ibPartnerId";

        if ($beforeDate) {
            $whereClause .= " AND establishedAt <= :beforeDate";
            $params['beforeDate'] = $beforeDate;
        }

        $sql = "SELECT COUNT(*) as count
                FROM {$this->table}
                {$whereClause}";

        $result = $this->db->fetchOne($sql, $params);
        return (int)($result['count'] ?? 0);
    }

    /**
     * 获取直接下级IB列表
     */
    public function getDirectSubIbs($ibPartnerId) {
        $sql = "SELECT
                    ibh.id,
                    ibh.ibPartnerId as subIbPartnerId,
                    ibh.parentIbPartnerId,
                    ibh.hierarchyLevel as tier,
                    ibh.establishedAt
                FROM {$this->table} ibh
                WHERE ibh.parentIbPartnerId = :ibPartnerId
                ORDER BY ibh.establishedAt DESC";

        return $this->db->fetchAll($sql, ['ibPartnerId' => $ibPartnerId]);
    }

    /**
     * 获取所有下级IB（递归，使用PHP实现）
     */
    public function getAllSubIbs($ibPartnerId, $beforeDate = null) {
        try {
            // 1. 查询所有IB的层级关系（parent-child）
            $hierarchySql = "
                SELECT
                    ibPartnerId,
                    parentIbPartnerId,
                    hierarchyLevel as tier,
                    id
                FROM {$this->table}
                WHERE parentIbPartnerId IS NOT NULL
            ";

            if ($beforeDate) {
                $hierarchySql .= " AND establishedAt <= :beforeDate";
            }

            $hierarchyData = $this->db->fetchAll($hierarchySql, $beforeDate ? ['beforeDate' => $beforeDate] : []);

            // 构建父子关系映射：parentId => [childData]
            $childrenMap = [];
            $ibDataMap = []; // 存储每个IB的完整数据
            foreach ($hierarchyData as $row) {
                $parentId = (int)$row['parentIbPartnerId'];
                $childId = (int)$row['ibPartnerId'];
                $tier = (int)$row['tier'];

                if (!isset($childrenMap[$parentId])) {
                    $childrenMap[$parentId] = [];
                }

                $childData = [
                    'id' => (int)$row['id'],
                    'ibPartnerId' => $childId,
                    'parentIbPartnerId' => $parentId,
                    'tier' => $tier
                ];

                $childrenMap[$parentId][] = $childData;
                $ibDataMap[$childId] = $childData;
            }

            // 2. 使用PHP递归获取所有下级IB
            $maxDepth = IbProgramSetting::resolveNetworkMaxDepth();
            $allSubIbs = [];
            $path = []; // 递归路径（用于检测循环引用）

            $this->getSubIbsRecursive(
                $ibPartnerId,
                $childrenMap,
                $ibDataMap,
                $allSubIbs,
                $path,
                $maxDepth,
                0
            );

            // 按 tier 和 level 排序
            usort($allSubIbs, function($a, $b) {
                if ($a['tier'] != $b['tier']) {
                    return $a['tier'] - $b['tier'];
                }
                return $a['level'] - $b['level'];
            });

            return $allSubIbs;

        } catch (Exception $e) {
            Logger::error("Error in getAllSubIbs: " . $e->getMessage());
            // 如果出错，回退到只获取直接下级IB
            return $this->getDirectSubIbs($ibPartnerId);
        }
    }

    /**
     * 递归获取所有下级IB
     * @param int $ibPartnerId 当前IB ID
     * @param array $childrenMap 父子关系映射 [parentId => [childData]]
     * @param array $ibDataMap IB数据映射 [ibPartnerId => ibData]
     * @param array $allSubIbs 结果数组（引用传递）
     * @param array $path 当前递归路径（用于检测循环引用）
     * @param int $maxDepth 最大递归深度
     * @param int $currentDepth 当前递归深度
     */
    private function getSubIbsRecursive($ibPartnerId, &$childrenMap, &$ibDataMap, &$allSubIbs, &$path, $maxDepth, $currentDepth) {
        // 防止超过最大深度
        if ($currentDepth >= $maxDepth) {
            Logger::error("Maximum depth ({$maxDepth}) reached for IB Partner ID: {$ibPartnerId}");
            return;
        }

        // 检测循环引用：如果当前节点已在路径中，说明存在循环
        if (in_array($ibPartnerId, $path)) {
            Logger::error("Circular reference detected for IB Partner ID: {$ibPartnerId}. Path: " . implode(' -> ', $path) . " -> {$ibPartnerId}");
            return;
        }

        // 将当前节点添加到路径中
        $path[] = $ibPartnerId;

        // 获取直接下级IB
        if (isset($childrenMap[$ibPartnerId]) && is_array($childrenMap[$ibPartnerId])) {
            foreach ($childrenMap[$ibPartnerId] as $childData) {
                $childId = $childData['ibPartnerId'];

                // 添加到结果数组
                $allSubIbs[] = [
                    'id' => $childData['id'],
                    'ibPartnerId' => $childId,
                    'parentIbPartnerId' => $childData['parentIbPartnerId'],
                    'tier' => $childData['tier'],
                    'level' => $currentDepth + 1
                ];

                // 递归获取下级的下级
                $this->getSubIbsRecursive(
                    $childId,
                    $childrenMap,
                    $ibDataMap,
                    $allSubIbs,
                    $path,
                    $maxDepth,
                    $currentDepth + 1
                );
            }
        }

        // 从路径中移除当前节点（回溯）
        array_pop($path);
    }

    /**
     * 获取父级IB代理
     */
    public function getParentIbPartner($ibPartnerId) {
        $sql = "SELECT
                    id,
                    ibPartnerId,
                    parentIbPartnerId,
                    hierarchyLevel,
                    establishedAt
                FROM {$this->table}
                WHERE ibPartnerId = :ibPartnerId
                LIMIT 1";

        return $this->db->fetchOne($sql, ['ibPartnerId' => $ibPartnerId]);
    }
}
