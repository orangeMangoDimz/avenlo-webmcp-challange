<?php
/**
 * Client KYC Document Signature Model
 * 对应表: clientKycDocumentSignatures
 */

require_once __DIR__ . '/BaseModel.php';

class ClientKycDocumentSignature extends BaseModel {
    protected $table = 'clientKycDocumentSignatures';
    protected $primaryKey = 'id';
    protected $fillable = [
        'submissionId',
        'templateDocumentId',
        'ipAddress',
        'userAgent'
    ];

    /**
     * 获取提交的所有签名
     */
    public function getSubmissionSignatures($submissionId) {
        $sql = "SELECT
                    s.*,
                    d.documentTitle,
                    d.documentContent
                FROM clientKycDocumentSignatures s
                INNER JOIN kycTemplateDocuments d ON s.templateDocumentId = d.id
                WHERE s.submissionId = :submissionId
                ORDER BY d.displayOrder";

        return $this->query($sql, ['submissionId' => $submissionId]);
    }

    /**
     * 签署文档
     */
    public function signDocument($submissionId, $templateDocumentId, $ipAddress = null, $userAgent = null) {
        $data = [
            'submissionId' => $submissionId,
            'templateDocumentId' => $templateDocumentId,
            'ipAddress' => $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? null,
            'userAgent' => $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? null
        ];

        // 检查是否已签署
        $existing = $this->findOne([
            'submissionId' => $submissionId,
            'templateDocumentId' => $templateDocumentId
        ]);

        if ($existing) {
            return $existing['id'];
        }

        return $this->create($data);
    }

    /**
     * 批量签署文档
     */
    public function signDocumentsBatch($submissionId, $documentIds, $ipAddress = null, $userAgent = null) {
        $results = [];

        foreach ($documentIds as $docId) {
            $results[] = $this->signDocument($submissionId, $docId, $ipAddress, $userAgent);
        }

        return $results;
    }

    /**
     * 检查是否已签署所有必需文档
     */
    public function hasSignedAllRequired($submissionId, $templateId) {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM kycTemplateDocuments
                     WHERE templateId = :templateId AND isActive = 1) AS required,
                    (SELECT COUNT(*) FROM clientKycDocumentSignatures s
                     INNER JOIN kycTemplateDocuments d ON s.templateDocumentId = d.id
                     WHERE s.submissionId = :submissionId AND d.templateId = :templateId2) AS signed";

        $result = $this->queryOne($sql, [
            'templateId' => $templateId,
            'templateId2' => $templateId,
            'submissionId' => $submissionId
        ]);

        return $result && $result['required'] == $result['signed'];
    }
}
