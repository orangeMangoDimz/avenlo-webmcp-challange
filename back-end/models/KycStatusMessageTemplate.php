<?php
/**
 * KYC Status Message Template Model
 * 对应表: kycStatusMessageTemplates
 */

require_once __DIR__ . '/BaseModel.php';

class KycStatusMessageTemplate extends BaseModel {
    protected $table = 'kycStatusMessageTemplates';
    protected $primaryKey = 'id';

    protected $fillable = [
        'statusType',
        'messageTitle',
        'messageContent',
        'messageType',
        'showActionButton',
        'actionButtonText',
        'actionButtonUrl',
        'iconClass',
        'titleIcon',
        'badgeText',
        'badgeClass',
        'isActive',
        'displayOrder',
        'updatedBy'
    ];

    /**
     * 根据状态类型获取消息模板
     */
    public function getByStatusType($statusType) {
        return $this->findOne(['statusType' => $statusType]);
    }

    /**
     * 获取所有活跃的状态消息模板
     */
    public function getActiveTemplates() {
        return $this->findAll(['isActive' => 1], 'displayOrder ASC');
    }

    /**
     * 获取特定状态类型的消息（用于客户端显示）
     */
    public function getClientMessage($statusType) {
        $template = $this->findOne([
            'statusType' => $statusType,
            'isActive' => 1
        ]);

        if (!$template) {
            return null;
        }

        return [
            'title' => $template['messageTitle'],
            'content' => $template['messageContent'],
            'type' => $template['messageType'],
            'icon' => $template['iconClass'],
            'action' => $template['showActionButton'] ? [
                'text' => $template['actionButtonText'],
                'url' => $template['actionButtonUrl']
            ] : null
        ];
    }

    /**
     * 获取完整的状态配置（包含新字段）
     */
    public function getStatusConfig($statusType) {
        $template = $this->findOne([
            'statusType' => $statusType,
            'isActive' => 1
        ]);

        if (!$template) {
            return null;
        }

        return [
            'title' => $template['messageTitle'],
            'description' => $template['messageContent'],
            'icon' => $template['titleIcon'] ?: $template['iconClass'],
            'iconClass' => $this->getIconClassMapping($statusType),
            'badgeText' => $template['badgeText'],
            'badgeClass' => $template['badgeClass'],
            'messageType' => $template['messageType'],
            'messageIcon' => $template['iconClass'],
            'messageTitle' => $template['messageTitle'],
            'messageContent' => $template['messageContent'],
            'showActionButton' => (bool)$template['showActionButton'],
            'actionButtonText' => $template['actionButtonText'],
            'actionButtonUrl' => $template['actionButtonUrl']
        ];
    }

    /**
     * 获取图标类映射
     */
    private function getIconClassMapping($statusType) {
        $mapping = [
            'draft' => 'incomplete',
            'incomplete' => 'incomplete',
            'pending' => 'pending',
            'under_review' => 'in-review',
            'approved' => 'approved',
            'rejected' => 'rejected',
            'expired' => 'incomplete',
            'resubmit_required' => 'incomplete',
            'pending_documents' => 'pending'
        ];

        return $mapping[$statusType] ?? 'incomplete';
    }

    /**
     * 批量更新状态消息
     */
    public function bulkUpdate($templates, $updatedBy = null) {
        try {
            $this->db->beginTransaction();

            foreach ($templates as $templateData) {
                if (isset($templateData['statusType'])) {
                    $existing = $this->getByStatusType($templateData['statusType']);

                    if ($existing) {
                        if ($updatedBy) {
                            $templateData['updatedBy'] = $updatedBy;
                        }
                        $this->update($existing['id'], $templateData);
                    }
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
