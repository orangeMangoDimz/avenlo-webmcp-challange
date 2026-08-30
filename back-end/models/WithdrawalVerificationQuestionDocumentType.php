<?php
/**
 * Withdrawal Verification Question Document Type Model
 * 对应表: withdrawalVerificationQuestionDocumentTypes
 */

require_once __DIR__ . '/BaseModel.php';

class WithdrawalVerificationQuestionDocumentType extends BaseModel {
    protected $table = 'withdrawalVerificationQuestionDocumentTypes';
    protected $primaryKey = 'id';
    protected $fillable = [
        'questionId',
        'documentType',
        'displayOrder'
    ];

    public function getQuestionDocumentTypes($questionId) {
        return $this->findAll(['questionId' => $questionId], 'displayOrder');
    }

    public function createBatch($questionId, $documentTypes) {
        $results = [];
        $order = 1;

        foreach ($documentTypes as $docType) {
            $results[] = $this->create([
                'questionId' => $questionId,
                'documentType' => is_array($docType) ? ($docType['type'] ?? '') : $docType,
                'displayOrder' => $order++
            ]);
        }

        return $results;
    }

    public function updateQuestionDocumentTypes($questionId, $documentTypes) {
        $sql = "DELETE FROM {$this->table} WHERE questionId = :questionId";
        $this->db->query($sql, ['questionId' => $questionId]);

        return $this->createBatch($questionId, $documentTypes);
    }
}
