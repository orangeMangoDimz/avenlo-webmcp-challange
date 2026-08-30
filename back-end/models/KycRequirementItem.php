<?php
/**
 * KYC Requirement Item Model
 * 对应表: kycRequirementItems
 */

require_once __DIR__ . '/BaseModel.php';

class KycRequirementItem extends BaseModel {
    protected $table = 'kycRequirementItems';
    protected $primaryKey = 'id';

    protected $fillable = [
        'noticeSettingId',
        'itemTitle',
        'itemDescription',
        'itemType',
        'iconClass',
        'isRequired',
        'displayOrder',
        'isActive'
    ];

    /**
     * 获取特定通知设置的所有要求项
     */
    public function getByNoticeSettingId($noticeSettingId) {
        return $this->findAll(
            ['noticeSettingId' => $noticeSettingId, 'isActive' => 1],
            'displayOrder ASC'
        );
    }

    /**
     * 获取必需的要求项
     */
    public function getRequiredItems($noticeSettingId) {
        return $this->findAll([
            'noticeSettingId' => $noticeSettingId,
            'isRequired' => 1,
            'isActive' => 1
        ], 'displayOrder ASC');
    }

    /**
     * 获取所有活跃的要求项
     */
    public function getActiveItems($noticeSettingId) {
        return $this->getByNoticeSettingId($noticeSettingId);
    }

    /**
     * 获取按类型分组的要求项
     */
    public function getItemsByType($noticeSettingId) {
        $items = $this->getByNoticeSettingId($noticeSettingId);

        $grouped = [
            'document' => [],
            'information' => [],
            'action' => []
        ];

        foreach ($items as $item) {
            $type = $item['itemType'];
            if (isset($grouped[$type])) {
                $grouped[$type][] = $item;
            }
        }

        return $grouped;
    }

    /**
     * 批量更新显示顺序
     */
    public function updateOrder($itemIds) {
        try {
            $this->db->beginTransaction();

            foreach ($itemIds as $order => $itemId) {
                $this->update($itemId, ['displayOrder' => $order + 1]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
