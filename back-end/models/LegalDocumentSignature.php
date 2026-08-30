<?php
/**
 * 法律文档签署记录模型
 */

require_once __DIR__ . '/BaseModel.php';

class LegalDocumentSignature extends BaseModel {
    protected $table = 'legalDocumentSignatures';
    protected $primaryKey = 'id';

    protected $fillable = [
        'leadId', 'documentId', 'documentType', 'documentVersion',
        'signedAt', 'ipAddress', 'userAgent'
    ];

    /**
     * 记录文档签署
     */
    public function recordSignature($leadId, $documentId, $documentType, $documentVersion = null, $ipAddress = null, $userAgent = null) {
        return $this->create([
            'leadId' => $leadId,
            'documentId' => $documentId,
            'documentType' => $documentType,
            'documentVersion' => $documentVersion,
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent
        ]);
    }

    /**
     * 获取Lead签署的所有文档
     */
    public function getLeadSignatures($leadId) {
        $sql = "SELECT lds.*, ld.title, ld.languageCode
                FROM {$this->table} lds
                INNER JOIN legalDocuments ld ON lds.documentId = ld.id
                WHERE lds.leadId = :leadId
                ORDER BY lds.signedAt DESC";

        return $this->db->fetchAll($sql, ['leadId' => $leadId]);
    }

    /**
     * 检查Lead是否已签署特定文档
     */
    public function hasSignedDocument($leadId, $documentType) {
        $signature = $this->findOne([
            'leadId' => $leadId,
            'documentType' => $documentType
        ]);

        return $signature !== null;
    }

    /**
     * 检查Lead是否已签署所有必需文档
     */
    public function hasSignedAllRequired($leadId) {
        $requiredDocs = ['terms_of_service', 'privacy_policy', 'risk_disclosure'];

        foreach ($requiredDocs as $docType) {
            if (!$this->hasSignedDocument($leadId, $docType)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 获取特定文档的签署统计
     */
    public function getDocumentSignatureStats($documentType) {
        $sql = "SELECT
                    COUNT(*) as totalSignatures,
                    COUNT(DISTINCT leadId) as uniqueLeads,
                    DATE(signedAt) as signDate,
                    COUNT(*) as dailyCount
                FROM {$this->table}
                WHERE documentType = :documentType
                GROUP BY DATE(signedAt)
                ORDER BY signDate DESC
                LIMIT 30";

        return $this->db->fetchAll($sql, ['documentType' => $documentType]);
    }
}
