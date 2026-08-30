<?php
/**
 * IB Partner 状态历史模型
 * 对应表：ib_partner_status_history，供客户端 ib-dashboard 时间线展示
 * title/description 写入时按 getStatusTitle、getStatusDescription 规则生成并入库，读取时直接用库中值
 */

require_once __DIR__ . '/BaseModel.php';

class IbPartnerStatusHistory extends BaseModel {
    protected $table = 'ib_partner_status_history';
    protected $primaryKey = 'id';

    /**
     * 记录状态变更（添加时间线一条），并写入 title、description
     */
    public function logStatusHistory($partnerId, $previousStatus, $newStatus, $changedBy, $notes = null) {
        $changedByName = null;
        if (!empty($changedBy)) {
            $row = $this->db->fetchOne('SELECT fullName FROM adminUsers WHERE id = :id LIMIT 1', ['id' => (int) $changedBy]);
            $changedByName = $row['fullName'] ?? null;
        }
        if ($previousStatus === null || $previousStatus === '') {
            $title = 'IB Registration Submitted';
            $description = 'Your IB registration has been submitted and is pending review.';
        } else {
            $title = $this->getTitleForStatus($newStatus, $previousStatus);
            $description = $this->getDescriptionForStatus($newStatus, $changedByName, $notes);
        }

        return $this->db->insert($this->table, [
            'partnerId' => (int) $partnerId,
            'previousStatus' => $previousStatus,
            'newStatus' => $newStatus,
            'changedBy' => $changedBy,
            'notes' => $notes,
            'title' => $title,
            'description' => $description
        ]);
    }

    /** 与 ClientAuthController::getStatusTitle 一致，用于写入 */
    private function getTitleForStatus($status, $previousStatus) {
        if ($previousStatus === null || $previousStatus === '') {
            return 'IB Registration Submitted';
        }
        $titles = [
            'pending' => 'Pending Review',
            'in_review' => 'Under Review',
            'approved' => 'Application Approved',
            'rejected' => 'Application Rejected',
            'more_info_requested' => 'More Information Requested',
            'pending_initial_review' => 'Initial Review',
            'pending_risk_review' => 'Risk Review',
            'pending_final_review' => 'Final Review'
        ];
        return $titles[$status] ?? 'Status Updated';
    }

    /** 与 ClientAuthController::getStatusDescription 一致，用于写入 */
    private function getDescriptionForStatus($status, $changedBy, $notes) {
        $descriptions = [
            'pending' => 'Your application is pending review',
            'in_review' => 'Your application is being reviewed by ' . $changedBy,
            'approved' => 'Your application has been approved by ' . $changedBy,
            'rejected' => 'Your application has been rejected by ' . $changedBy,
            'more_info_requested' => 'Additional information has been requested',
            'pending_initial_review' => 'Your application is in initial review' . ($changedBy ? '. Reverted by ' . $changedBy : ''),
            'pending_risk_review' => 'Initial review completed. Now in risk review' . ($changedBy ? ' by ' . $changedBy : ''),
            'pending_final_review' => 'Risk review completed. Now in final review' . ($changedBy ? ' by ' . $changedBy : '')
        ];
        $description = $descriptions[$status] ?? 'Status updated by ' . $changedBy;
        if ($notes !== null && $notes !== '') {
            $description .= '. ' . $notes;
        }
        return $description;
    }

    /**
     * 获取指定 Partner 的状态历史（按时间倒序，含 changedByName）
     */
    public function getStatusHistory($partnerId) {
        $sql = "SELECT
                    sh.*,
                    au.fullName AS changedByName,
                    au.username AS changedByUsername
                FROM {$this->table} sh
                LEFT JOIN adminUsers au ON sh.changedBy = au.id
                WHERE sh.partnerId = :partnerId
                ORDER BY sh.createdAt ASC";
        return $this->db->fetchAll($sql, ['partnerId' => (int) $partnerId]);
    }
}
