<?php
/**
 * KYC Question Document Type Model
 * 对应表: kycQuestionDocumentTypes
 */

require_once __DIR__ . '/BaseModel.php';

class KycQuestionDocumentType extends BaseModel {
    protected $table = 'kycQuestionDocumentTypes';
    protected $primaryKey = 'id';
    protected $fillable = [
        'questionId',
        'documentType',
        'documentDisplayName',
        'isRequired',
        'displayOrder'
    ];

    /**
     * 获取问题的所有文档类型
     */
    public function getQuestionDocumentTypes($questionId) {
        return $this->findAll(['questionId' => $questionId], 'displayOrder');
    }

    /**
     * 批量创建文档类型
     */
    public function createBatch($questionId, $documentTypes) {
        $results = [];
        $order = 1;

        foreach ($documentTypes as $docType) {
            $data = [
                'questionId' => $questionId,
                'documentType' => $docType['type'] ?? $docType,
                'documentDisplayName' => $docType['displayName'] ?? $this->getDisplayName($docType['type'] ?? $docType),
                'isRequired' => $docType['required'] ?? 0,
                'displayOrder' => $order++
            ];

            $results[] = $this->create($data);
        }

        return $results;
    }

    /**
     * 更新问题的文档类型
     */
    public function updateQuestionDocumentTypes($questionId, $documentTypes) {
        // 删除现有类型
        $sql = "DELETE FROM {$this->table} WHERE questionId = :questionId";
        $this->db->query($sql, ['questionId' => $questionId]);

        // 创建新类型
        return $this->createBatch($questionId, $documentTypes);
    }

    /**
     * 获取文档类型显示名称
     */
    private function getDisplayName($documentType) {
        $names = [
            'ID_CARD' => 'Identity Card',
            'PASSPORT' => 'Passport',
            'DRIVERS_LICENSE' => 'Driver\'s License',
            'PROOF_ADDRESS' => 'Proof of Address',
            'BANK_STATEMENT' => 'Bank Statement',
            'UTILITY_BILL' => 'Utility Bill',
            'INCOME_PROOF' => 'Income Verification',
            'TAX_DOCUMENT' => 'Tax Document',
            'EMPLOYMENT_LETTER' => 'Employment Letter',
            'BUSINESS_REGISTRATION' => 'Business Registration',
            'FINANCIAL_STATEMENT' => 'Financial Statement',
            'OTHER' => 'Other Document'
        ];

        return $names[$documentType] ?? $documentType;
    }
}
