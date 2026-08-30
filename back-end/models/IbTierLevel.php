<?php
/**
 * IB Tier Level 模型
 * 对应表：ibTierLevels
 */

require_once __DIR__ . '/BaseModel.php';

class IbTierLevel extends BaseModel {
    protected $table = 'ibTierLevels';
    protected $primaryKey = 'id';

    protected $fillable = [
        'tierLevel', 'tierName', 'tierDescription', 'badgeColor',
        'canRecruitSubAgents', 'canViewReports', 'canManageClients',
        'status', 'createdBy', 'updatedBy'
    ];

    /**
     * 获取所有激活的层级（用于选择器）
     */
    public function getActiveTierLevels() {
        $sql = "SELECT
                    id,
                    tierLevel,
                    tierName,
                    tierDescription,
                    badgeColor,
                    canRecruitSubAgents,
                    canViewReports,
                    canManageClients
                FROM ibTierLevels
                WHERE status = 'active'
                ORDER BY tierLevel ASC";

        return $this->db->fetchAll($sql);
    }

    /**
     * 获取层级使用统计
     */
    public function getTierUsageStats($tierLevelId) {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM ibPartners WHERE tierLevelId = :tierLevelId) as assignedIbCount,
                    (SELECT COUNT(*) FROM ibApplications WHERE assignedTierLevelId = :tierLevelId) as assignedApplicationsCount
                FROM dual";

        return $this->db->fetchOne($sql, ['tierLevelId' => $tierLevelId]);
    }

    /**
     * tierLevel 大于指定上限的配置行（用于缩小 max_tier_level_count 校验）。
     *
     * @return array<int, array{tierLevel: int|string, tierName: string}>
     */
    public function getTierLevelsAbove(int $maxLevel): array {
        if ($maxLevel < 0) {
            return [];
        }
        $sql = "SELECT tierLevel, tierName FROM {$this->table}
                WHERE tierLevel > :max_level
                ORDER BY tierLevel ASC";
        return $this->db->fetchAll($sql, ['max_level' => $maxLevel]);
    }

    /**
     * 获取层级列表（带使用统计）
     */
    public function getTierLevelsWithStats() {
        $sql = "SELECT
                    tl.*,
                    (SELECT COUNT(*) FROM ibPartners WHERE tierLevelId = tl.id AND status = 'approved') as activeIbCount,
                    (SELECT COUNT(*) FROM ibApplications WHERE assignedTierLevelId = tl.id AND applicationStatus = 'pending') as pendingApplicationsCount
                FROM ibTierLevels tl
                ORDER BY tl.tierLevel ASC";

        return $this->db->fetchAll($sql);
    }
}
