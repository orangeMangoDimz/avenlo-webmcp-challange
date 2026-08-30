<?php
/**
 * Lead 标签分配模型
 */

require_once __DIR__ . '/BaseModel.php';

class LeadTagAssignment extends BaseModel {
    protected $table = 'leadTagAssignments';
    protected $primaryKey = 'id';

    protected $fillable = [
        'leadId', 'tagId', 'assignedBy'
    ];

    /**
     * 为Lead分配标签
     */
    public function assignTag($leadId, $tagId, $assignedBy) {
        // 检查是否已经分配
        $existing = $this->findOne([
            'leadId' => $leadId,
            'tagId' => $tagId
        ]);

        if ($existing) {
            return $existing['id'];
        }

        return $this->create([
            'leadId' => $leadId,
            'tagId' => $tagId,
            'assignedBy' => $assignedBy
        ]);
    }

    /**
     * 移除Lead的标签
     */
    public function removeTag($leadId, $tagId) {
        $sql = "DELETE FROM {$this->table}
                WHERE leadId = :leadId AND tagId = :tagId";

        return $this->db->query($sql, [
            'leadId' => $leadId,
            'tagId' => $tagId
        ]);
    }

    /**
     * 获取Lead的所有标签
     */
    public function getLeadTags($leadId) {
        $sql = "SELECT lt.*, lta.assignedAt, lta.assignedBy
                FROM leadTags lt
                INNER JOIN {$this->table} lta ON lt.id = lta.tagId
                WHERE lta.leadId = :leadId
                ORDER BY lt.tagName ASC";

        return $this->db->fetchAll($sql, ['leadId' => $leadId]);
    }

    /**
     * 获取拥有特定标签的所有Leads
     */
    public function getLeadsByTag($tagId, $page = 1, $perPage = 10) {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT cu.*, lta.assignedAt
                FROM clientUsers cu
                INNER JOIN {$this->table} lta ON cu.id = lta.leadId
                WHERE lta.tagId = :tagId
                ORDER BY lta.assignedAt DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as count
                     FROM {$this->table}
                     WHERE tagId = :tagId";

        $params = ['tagId' => $tagId];

        $items = $this->db->fetchAll($sql, $params);
        $total = $this->db->fetchOne($countSql, $params)['count'];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * 批量分配标签
     */
    public function bulkAssignTag($leadIds, $tagId, $assignedBy) {
        $successCount = 0;

        foreach ($leadIds as $leadId) {
            try {
                $this->assignTag($leadId, $tagId, $assignedBy);
                $successCount++;
            } catch (Exception $e) {
                // 继续处理其他leads
                continue;
            }
        }

        return $successCount;
    }

    /**
     * 批量移除标签
     */
    public function bulkRemoveTag($leadIds, $tagId) {
        $placeholders = implode(',', array_fill(0, count($leadIds), '?'));

        $sql = "DELETE FROM {$this->table}
                WHERE leadId IN ({$placeholders}) AND tagId = ?";

        $params = array_merge($leadIds, [$tagId]);

        return $this->db->query($sql, $params);
    }
}
