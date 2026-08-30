<?php
/**
 * 法律文档管理控制器
 */

require_once __DIR__ . '/../models/LegalDocument.php';
require_once __DIR__ . '/../models/LegalDocumentSignature.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';

class LegalDocumentController {
    private $documentModel;
    private $signatureModel;

    public function __construct() {
        $this->documentModel = new LegalDocument();
        $this->signatureModel = new LegalDocumentSignature();
    }

    /**
     * 获取所有法律文档
     * GET /api/legal-documents
     */
    public function index() {
        $languageCode = $_GET['language'] ?? null;
        $activeOnly = isset($_GET['active_only']) ? filter_var($_GET['active_only'], FILTER_VALIDATE_BOOLEAN) : false;

        if ($languageCode) {
            if ($activeOnly) {
                $documents = $this->documentModel->getActiveDocuments($languageCode);
            } else {
                $documents = $this->documentModel->getAllDocuments($languageCode);
            }
        } else {
            if ($activeOnly) {
                $documents = $this->documentModel->findAll(['isActive' => 1], 'displayOrder ASC');
            } else {
                $documents = $this->documentModel->findAll([], 'displayOrder ASC, languageCode ASC');
            }
        }

        Response::success($documents);
    }

    /**
     * 获取单个法律文档
     * GET /api/legal-documents/{id}
     */
    public function show($id) {
        $document = $this->documentModel->findById($id);

        if (!$document) {
            Response::notFound('Legal document not found');
        }

        // 获取签名统计
        $stats = $this->signatureModel->getDocumentSignatureStats($document['documentType']);
        $document['signatureStats'] = $stats;

        Response::success($document);
    }

    /**
     * 根据文档类型获取文档
     * GET /api/legal-documents/type/{documentType}
     */
    public function getByType($documentType) {
        $languageCode = $_GET['language'] ?? 'en';

        $document = $this->documentModel->getByType($documentType, $languageCode);

        if (!$document) {
            Response::notFound('Legal document not found for the specified type and language');
        }

        Response::success($document);
    }

    /**
     * 创建法律文档
     * POST /api/legal-documents
     */
    public function create() {
        $data = json_decode(file_get_contents('php://input'), true);
        $data['isActive'] = $this->normalizeBoolean($data['isActive'] ?? null) ?? 1;
        $data['isMandatory'] = $this->normalizeBoolean($data['isMandatory'] ?? null) ?? 0;

        Validator::make($data, [
            'documentType' => 'required',
            'title' => 'required',
            'content' => 'required',
            'languageCode' => 'required'
        ]);

        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        $documentId = $this->documentModel->create([
            'documentType' => $data['documentType'],
            'title' => $data['title'],
            'content' => $data['content'],
            'languageCode' => $data['languageCode'],
            'isActive' => $data['isActive'],
            'isMandatory' => $data['isMandatory'],
            'version' => $data['version'] ?? '1.0',
            'effectiveDate' => $data['effectiveDate'] ?? date('Y-m-d'),
            'displayOrder' => $data['displayOrder'] ?? 0,
            'updatedBy' => $adminId
        ]);

        $document = $this->documentModel->findById($documentId);

        Response::success($document, 'Legal document created successfully', 201);
    }

    /**
     * 更新法律文档
     * PUT /api/legal-documents/{id}
     */
    public function update($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        $document = $this->documentModel->findById($id);
        if (!$document) {
            Response::notFound('Legal document not found');
        }

        $data['isActive'] = $this->normalizeBoolean($data['isActive'] ?? null) ?? ($document['isActive'] ?? 1);
        $data['isMandatory'] = $this->normalizeBoolean($data['isMandatory'] ?? null) ?? ($document['isMandatory'] ?? 0);

        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        $updateData = [];

        if (isset($data['documentType'])) {
            $updateData['documentType'] = $data['documentType'];
        }

        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
        }

        if (isset($data['content'])) {
            $updateData['content'] = $data['content'];
        }

        if (isset($data['languageCode'])) {
            $updateData['languageCode'] = $data['languageCode'];
        }

        $updateData['isActive'] = $data['isActive'];
        $updateData['isMandatory'] = $data['isMandatory'];

        if (isset($data['version'])) {
            $updateData['version'] = $data['version'];
        }

        if (isset($data['effectiveDate'])) {
            $updateData['effectiveDate'] = $data['effectiveDate'];
        }

        if (isset($data['displayOrder'])) {
            $updateData['displayOrder'] = $data['displayOrder'];
        }

        $updateData['updatedBy'] = $adminId;

        $this->documentModel->update($id, $updateData);

        $updatedDocument = $this->documentModel->findById($id);

        Response::success($updatedDocument, 'Legal document updated successfully');
    }

    /**
     * 删除法律文档
     * DELETE /api/legal-documents/{id}
     */
    public function delete($id) {
        $document = $this->documentModel->findById($id);

        if (!$document) {
            Response::notFound('Legal document not found');
        }

        // 检查是否有签名记录
        $signatures = $this->signatureModel->findAll(['documentId' => $id], null, 1);
        if (!empty($signatures)) {
            Response::error('Cannot delete document with existing signatures. Please deactivate it instead.', 400);
        }

        $this->documentModel->delete($id);

        Response::success(null, 'Legal document deleted successfully');
    }

    /**
     * 切换文档激活状态
     * POST /api/legal-documents/{id}/toggle
     */
    public function toggleActive($id) {
        $document = $this->documentModel->findById($id);

        if (!$document) {
            Response::notFound('Legal document not found');
        }

        $newStatus = !$document['isActive'];
        $this->documentModel->toggleActive($id, $newStatus);

        $updatedDocument = $this->documentModel->findById($id);

        Response::success($updatedDocument, 'Document status toggled successfully');
    }

    /**
     * 批量更新文档顺序
     * POST /api/legal-documents/reorder
     */
    public function reorder() {
        $data = json_decode(file_get_contents('php://input'), true);

        Validator::make($data, [
            'documents' => 'required|array'
        ]);

        $this->documentModel->batchUpdateOrder($data['documents']);

        Response::success(null, 'Display order updated successfully');
    }

    /**
     * 发布新版本
     * POST /api/legal-documents/{id}/publish
     */
    public function publishNewVersion($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        $document = $this->documentModel->findById($id);
        if (!$document) {
            Response::notFound('Legal document not found');
        }

        Validator::make($data, [
            'version' => 'required'
        ]);

        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        $this->documentModel->publishNewVersion($id, $data['version'], $adminId);

        $updatedDocument = $this->documentModel->findById($id);

        Response::success($updatedDocument, 'New version published successfully');
    }

    /**
     * 获取可用语言列表
     * GET /api/legal-documents/type/{documentType}/languages
     */
    public function getAvailableLanguages($documentType) {
        $languages = $this->documentModel->getAvailableLanguages($documentType);

        Response::success(['languages' => $languages]);
    }

    private function normalizeBoolean($value) {
        if ($value === null) {
            return null;
        }

        return ($value === true || $value === 1 || $value === '1') ? 1 : 0;
    }
}
