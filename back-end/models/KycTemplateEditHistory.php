<?php
/**
 * KYC Template Edit History Model
 * 对应表: kycTemplateEditHistory
 */

require_once __DIR__ . '/BaseModel.php';

class KycTemplateEditHistory extends BaseModel {
    protected $table = 'kycTemplateEditHistory';
    protected $primaryKey = 'id';
    protected $fillable = [
        'templateId',
        'changeType',
        'fieldName',
        'oldValue',
        'newValue',
        'description',
        'editedBy',
        'ipAddress'
    ];

    /**
     * 获取模板的编辑历史
     */
    public function getTemplateHistory($templateId, $limit = 50) {
        $limit = max(1, (int)$limit); // 确保是正整数
        $sql = "SELECT
                    h.*,
                    u.fullName AS editorName
                FROM kycTemplateEditHistory h
                LEFT JOIN adminUsers u ON h.editedBy = u.id
                WHERE h.templateId = :templateId
                ORDER BY h.createdAt DESC
                LIMIT {$limit}";

        return $this->query($sql, [
            'templateId' => $templateId
        ]);
    }

    /**
     * 记录变更
     */
    public function logChange($templateId, $changeType, $description, $adminId = null, $fieldName = null, $oldValue = null, $newValue = null) {
        $data = [
            'templateId' => $templateId,
            'changeType' => $changeType,
            'description' => $description,
            'editedBy' => $adminId,
            'fieldName' => $fieldName,
            'oldValue' => $oldValue,
            'newValue' => $newValue,
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null
        ];

        return $this->create($data);
    }
}
