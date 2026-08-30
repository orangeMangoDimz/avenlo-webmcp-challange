<?php
/**
 * 法律文档签名记录控制器
 */

require_once __DIR__ . '/../models/LegalDocumentSignature.php';
require_once __DIR__ . '/../models/LegalDocument.php';
require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class LegalDocumentSignatureController {
    private $signatureModel;
    private $documentModel;
    private $clientUserModel;

    public function __construct() {
        $this->signatureModel = new LegalDocumentSignature();
        $this->documentModel = new LegalDocument();
        $this->clientUserModel = new ClientUser();
    }

    /**
     * 获取所有签名记录（分页）
     * GET /api/legal-document-signatures
     */
    public function index() {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        // 筛选条件
        if (isset($_GET['leadId'])) {
            $conditions[] = "lds.leadId = :leadId";
            $params['leadId'] = $_GET['leadId'];
        }

        if (isset($_GET['documentId'])) {
            $conditions[] = "lds.documentId = :documentId";
            $params['documentId'] = $_GET['documentId'];
        }

        if (isset($_GET['documentType'])) {
            $conditions[] = "lds.documentType = :documentType";
            $params['documentType'] = $_GET['documentType'];
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT lds.*,
                       ld.title as documentTitle,
                       ld.languageCode,
                       cu.firstName,
                       cu.lastName,
                       cu.email
                FROM legalDocumentSignatures lds
                INNER JOIN legalDocuments ld ON lds.documentId = ld.id
                INNER JOIN clientUsers cu ON lds.leadId = cu.id
                {$whereClause}
                ORDER BY lds.signedAt DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as count
                     FROM legalDocumentSignatures lds
                     {$whereClause}";

        $items = $this->signatureModel->db->fetchAll($sql, $params);
        $total = $this->signatureModel->db->fetchOne($countSql, $params)['count'];

        Response::paginated($items, $total, $page, $perPage);
    }

    /**
     * 获取单个签名记录
     * GET /api/legal-document-signatures/{id}
     */
    public function show($id) {
        $sql = "SELECT lds.*,
                       ld.title as documentTitle,
                       ld.content as documentContent,
                       ld.languageCode,
                       cu.firstName,
                       cu.lastName,
                       cu.email,
                       cu.country
                FROM legalDocumentSignatures lds
                INNER JOIN legalDocuments ld ON lds.documentId = ld.id
                INNER JOIN clientUsers cu ON lds.leadId = cu.id
                WHERE lds.id = :id";

        $signature = $this->signatureModel->db->fetchOne($sql, ['id' => $id]);

        if (!$signature) {
            Response::notFound('Signature record not found');
        }

        Response::success($signature);
    }

    /**
     * 获取特定Lead的所有签名记录
     * GET /api/legal-document-signatures/lead/{leadId}
     */
    public function getLeadSignatures($leadId) {
        $signatures = $this->signatureModel->getLeadSignatures($leadId);

        Response::success($signatures);
    }

    /**
     * 获取特定文档的所有签名记录（分页）
     * GET /api/legal-document-signatures/document/{documentId}
     */
    public function getDocumentSignatures($documentId) {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT lds.*,
                       cu.firstName,
                       cu.lastName,
                       cu.email,
                       cu.country
                FROM legalDocumentSignatures lds
                INNER JOIN clientUsers cu ON lds.leadId = cu.id
                WHERE lds.documentId = :documentId
                ORDER BY lds.signedAt DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as count
                     FROM legalDocumentSignatures lds
                     WHERE documentId = :documentId";

        $items = $this->signatureModel->db->fetchAll($sql, ['documentId' => $documentId]);
        $total = $this->signatureModel->db->fetchOne($countSql, ['documentId' => $documentId])['count'];

        Response::paginated($items, $total, $page, $perPage);
    }

    /**
     * 获取文档签名统计
     * GET /api/legal-document-signatures/stats/{documentType}
     */
    public function getDocumentStats($documentType) {
        $stats = $this->signatureModel->getDocumentSignatureStats($documentType);

        // 获取总体统计
        $sql = "SELECT
                    COUNT(*) as totalSignatures,
                    COUNT(DISTINCT leadId) as uniqueLeads
                FROM legalDocumentSignatures
                WHERE documentType = :documentType";

        $overallStats = $this->signatureModel->db->fetchOne($sql, ['documentType' => $documentType]);

        Response::success([
            'overall' => $overallStats,
            'daily' => $stats
        ]);
    }

    /**
     * 检查Lead是否已签署特定文档
     * GET /api/legal-document-signatures/check/{leadId}/{documentType}
     */
    public function checkSignature($leadId, $documentType) {
        $hasSigned = $this->signatureModel->hasSignedDocument($leadId, $documentType);

        Response::success([
            'hasSigned' => $hasSigned,
            'leadId' => $leadId,
            'documentType' => $documentType
        ]);
    }

    /**
     * 检查Lead是否已签署所有必需文档
     * GET /api/legal-document-signatures/check-all/{leadId}
     */
    public function checkAllSignatures($leadId) {
        $hasSignedAll = $this->signatureModel->hasSignedAllRequired($leadId);

        // 获取详细签名状态
        $requiredDocs = ['terms_of_service', 'privacy_policy', 'risk_disclosure'];
        $signatureStatus = [];

        foreach ($requiredDocs as $docType) {
            $signatureStatus[$docType] = $this->signatureModel->hasSignedDocument($leadId, $docType);
        }

        Response::success([
            'hasSignedAll' => $hasSignedAll,
            'leadId' => $leadId,
            'signatureStatus' => $signatureStatus
        ]);
    }

    /**
     * 记录文档签名（供客户端使用）
     * POST /api/legal-document-signatures
     */
    public function create() {
        $data = json_decode(file_get_contents('php://input'), true);

        Validator::make($data, [
            'leadId' => 'required|numeric',
            'documentId' => 'required|numeric',
            'documentType' => 'required'
        ]);

        // 获取IP和User Agent
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // 获取文档版本
        $document = $this->documentModel->findById($data['documentId']);
        if (!$document) {
            Response::notFound('Document not found');
        }

        $signatureId = $this->signatureModel->recordSignature(
            $data['leadId'],
            $data['documentId'],
            $data['documentType'],
            $document['version'],
            $ipAddress,
            $userAgent
        );

        $signature = $this->signatureModel->findById($signatureId);

        Response::success($signature, 'Signature recorded successfully', 201);
    }

    /**
     * 删除签名记录（管理员操作，谨慎使用）
     * DELETE /api/legal-document-signatures/{id}
     */
    public function delete($id) {
        $signature = $this->signatureModel->findById($id);

        if (!$signature) {
            Response::notFound('Signature record not found');
        }

        $this->signatureModel->delete($id);

        Response::success(null, 'Signature record deleted successfully');
    }

    /**
     * 获取签名趋势统计
     * GET /api/legal-document-signatures/trends
     */
    public function getTrends() {
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');

        $sql = "SELECT
                    DATE(signedAt) as signDate,
                    documentType,
                    COUNT(*) as count
                FROM legalDocumentSignatures
                WHERE DATE(signedAt) BETWEEN :startDate AND :endDate
                GROUP BY DATE(signedAt), documentType
                ORDER BY signDate DESC, documentType";

        $trends = $this->signatureModel->db->fetchAll($sql, [
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);

        Response::success($trends);
    }
}
