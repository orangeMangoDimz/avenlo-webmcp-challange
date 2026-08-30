<?php
/**
 * Withdrawal Verification Template Document Model
 * 对应表: withdrawalVerificationTemplateDocuments
 */

require_once __DIR__ . '/BaseModel.php';

class WithdrawalVerificationTemplateDocument extends BaseModel {
    protected $table = 'withdrawalVerificationTemplateDocuments';
    protected $primaryKey = 'id';
    protected $fillable = [
        'templateId',
        'documentId',
        'documentTitle',
        'documentContent',
        'displayOrder',
        'isActive'
    ];

    public function getTemplateDocuments($templateId, $activeOnly = true) {
        $conditions = ['templateId' => $templateId];

        if ($activeOnly) {
            $conditions['isActive'] = 1;
        }

        return $this->findAll($conditions, 'displayOrder');
    }

    public function createTemplateDocument($data) {
        if (!isset($data['displayOrder'])) {
            $data['displayOrder'] = $this->getMaxDisplayOrder($data['templateId']) + 1;
        }

        return $this->create($data);
    }

    private function getMaxDisplayOrder($templateId) {
        $sql = "SELECT MAX(displayOrder) AS maxOrder
                FROM {$this->table}
                WHERE templateId = :templateId";
        $result = $this->queryOne($sql, ['templateId' => $templateId]);

        return (int)($result['maxOrder'] ?? 0);
    }

    public function updateDisplayOrder($templateId, $documentOrders) {
        foreach ($documentOrders as $order => $documentId) {
            $this->update($documentId, ['displayOrder' => $order + 1]);
        }
    }
}
