<?php
/**
 * Withdrawal Verification Template Edit History Model
 * 对应表: withdrawalVerificationTemplateEditHistory
 */

require_once __DIR__ . '/BaseModel.php';

class WithdrawalVerificationTemplateEditHistory extends BaseModel {
    protected $table = 'withdrawalVerificationTemplateEditHistory';
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

    public function getTemplateHistory($templateId, $limit = 50) {
        $limit = max(1, (int)$limit);
        $sql = "SELECT
                    h.*,
                    u.fullName AS editorName
                FROM withdrawalVerificationTemplateEditHistory h
                LEFT JOIN adminUsers u ON h.editedBy = u.id
                WHERE h.templateId = :templateId
                ORDER BY h.createdAt DESC
                LIMIT {$limit}";

        return $this->query($sql, ['templateId' => $templateId]);
    }

    public function logChange($templateId, $changeType, $description, $adminId = null, $fieldName = null, $oldValue = null, $newValue = null) {
        return $this->create([
            'templateId' => $templateId,
            'changeType' => $changeType,
            'description' => $description,
            'editedBy' => $adminId,
            'fieldName' => $fieldName,
            'oldValue' => $oldValue,
            'newValue' => $newValue,
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
}
