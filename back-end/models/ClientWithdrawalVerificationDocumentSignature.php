<?php
/**
 * Client Withdrawal Verification Document Signature Model
 * 对应表: clientWithdrawalVerificationDocumentSignatures
 */

require_once __DIR__ . '/BaseModel.php';

class ClientWithdrawalVerificationDocumentSignature extends BaseModel {
    protected $table = 'clientWithdrawalVerificationDocumentSignatures';
    protected $primaryKey = 'id';
    protected $fillable = [
        'submissionId',
        'templateDocumentId',
        'ipAddress',
        'userAgent'
    ];

    public function getSubmissionSignatures($submissionId) {
        $sql = "SELECT
                    s.*,
                    d.documentTitle,
                    d.documentContent
                FROM clientWithdrawalVerificationDocumentSignatures s
                INNER JOIN withdrawalVerificationTemplateDocuments d ON s.templateDocumentId = d.id
                WHERE s.submissionId = :submissionId
                ORDER BY d.displayOrder";

        return $this->query($sql, ['submissionId' => $submissionId]);
    }

    public function signDocument($submissionId, $templateDocumentId, $ipAddress = null, $userAgent = null) {
        $existing = $this->findOne([
            'submissionId' => $submissionId,
            'templateDocumentId' => $templateDocumentId
        ]);

        if ($existing) {
            return $existing['id'];
        }

        return $this->create([
            'submissionId' => $submissionId,
            'templateDocumentId' => $templateDocumentId,
            'ipAddress' => $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? null),
            'userAgent' => $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? null)
        ]);
    }

    public function signDocumentsBatch($submissionId, $documentIds, $ipAddress = null, $userAgent = null) {
        $results = [];
        foreach ($documentIds as $documentId) {
            $results[] = $this->signDocument($submissionId, $documentId, $ipAddress, $userAgent);
        }
        return $results;
    }

    public function hasSignedAllRequired($submissionId, $templateId) {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM withdrawalVerificationTemplateDocuments
                     WHERE templateId = :templateId AND isActive = 1) AS requiredCount,
                    (SELECT COUNT(*) FROM clientWithdrawalVerificationDocumentSignatures s
                     INNER JOIN withdrawalVerificationTemplateDocuments d ON s.templateDocumentId = d.id
                     WHERE s.submissionId = :submissionId AND d.templateId = :templateId2) AS signedCount";

        $result = $this->queryOne($sql, [
            'templateId' => $templateId,
            'templateId2' => $templateId,
            'submissionId' => $submissionId
        ]);

        return $result && (int)$result['requiredCount'] === (int)$result['signedCount'];
    }
}
