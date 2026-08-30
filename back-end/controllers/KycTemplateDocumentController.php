<?php
/**
 * KYC Template Document 控制器
 */

require_once __DIR__ . '/../models/KycTemplateDocument.php';
require_once __DIR__ . '/../models/KycTemplateEditHistory.php';
require_once __DIR__ . '/../models/KycTemplate.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogTexts/KycOperationLogTexts.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class KycTemplateDocumentController {
    private $documentModel;
    private $historyModel;

    public function __construct() {
        $this->documentModel = new KycTemplateDocument();
        $this->historyModel = new KycTemplateEditHistory();
    }

    /**
     * 获取模板的所有文档
     * GET /api/kyc-templates/{templateId}/documents
     */
    public function getTemplateDocuments($templateId) {
        $activeOnly = isset($_GET['active_only']) ? (bool)$_GET['active_only'] : true;

        $documents = $this->documentModel->getTemplateDocuments($templateId, $activeOnly);

        Response::success([
            'documents' => $documents,
            'total' => count($documents)
        ]);
    }

    /**
     * 创建新文档
     * POST /api/kyc-template-documents/create
     */
    public function create() {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            $this->logDocumentMutationFailure([], 'add', 0, 'documentAddFailure', 'Invalid JSON input');
            Response::error('Invalid JSON input', 400);
            return;
        }

        // 验证必填字段
        $required = ['templateId', 'documentTitle', 'documentContent'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                $this->logDocumentMutationFailure(
                    $input,
                    'add',
                    (int) ($input['templateId'] ?? 0),
                    'documentAddFailure',
                    "Field '{$field}' is required"
                );
                Response::error("Field '{$field}' is required", 400);
                return;
            }
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        $data = [
            'templateId' => $input['templateId'],
            'documentId' => $input['documentId'] ?? null,
            'documentTitle' => $input['documentTitle'],
            'documentContent' => $input['documentContent'],
            'displayOrder' => $input['displayOrder'] ?? null,
            'isActive' => isset($input['isActive']) ? (int)$input['isActive'] : 1
        ];

        try {
            $documentId = $this->documentModel->createTemplateDocument($data);

            // 记录历史
            $this->historyModel->logChange(
                $input['templateId'],
                'document_added',
                "Document '{$input['documentTitle']}' added",
                $adminId
            );

            $this->logDocumentMutation(
                $input,
                'add',
                (int) $input['templateId'],
                (string) ($input['documentTitle'] ?? '')
            );

            Response::success([
                'message' => 'Document created successfully',
                'data' => ['documentId' => $documentId]
            ]);

        } catch (Exception $e) {
            $this->logDocumentMutationFailure(
                $input,
                'add',
                (int) ($input['templateId'] ?? 0),
                'documentAddFailure',
                'Failed to create document: ' . $e->getMessage()
            );
            Response::error('Failed to create document: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新文档
     * PUT /api/kyc-template-documents/modify/{id}
     */
    public function update($id) {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            $this->logDocumentMutationFailure([], 'edit', 0, 'documentEditFailure', 'Invalid JSON input');
            Response::error('Invalid JSON input', 400);
            return;
        }

        // 检查文档是否存在
        $document = $this->documentModel->findById($id);
        if (!$document) {
            $this->logDocumentMutationFailure($input, 'edit', 0, 'documentEditFailure', 'Document not found');
            Response::error('Document not found', 404);
            return;
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        $data = [];
        if (isset($input['documentTitle'])) $data['documentTitle'] = $input['documentTitle'];
        if (isset($input['documentContent'])) $data['documentContent'] = $input['documentContent'];
        if (isset($input['displayOrder'])) $data['displayOrder'] = $input['displayOrder'];
        if (isset($input['isActive'])) $data['isActive'] = (int)$input['isActive'];

        try {
            $this->documentModel->update($id, $data);

            // 记录历史
            $this->historyModel->logChange(
                $document['templateId'],
                'document_modified',
                "Document '{$document['documentTitle']}' updated",
                $adminId
            );

            $this->logDocumentMutation(
                $input,
                'edit',
                (int) $document['templateId'],
                (string) ($document['documentTitle'] ?? ''),
                (string) ($input['documentTitle'] ?? $document['documentTitle'] ?? '')
            );

            Response::success(['message' => 'Document updated successfully']);

        } catch (Exception $e) {
            $this->logDocumentMutationFailure(
                $input,
                'edit',
                (int) ($document['templateId'] ?? 0),
                'documentEditFailure',
                'Failed to update document: ' . $e->getMessage()
            );
            Response::error('Failed to update document: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 删除文档
     * DELETE /api/kyc-template-documents/remove/{id}
     */
    public function delete($id) {
        // 检查文档是否存在
        $document = $this->documentModel->findById($id);
        if (!$document) {
            $this->logDocumentMutationFailure(null, 'delete', 0, 'documentDeleteFailure', 'Document not found');
            Response::error('Document not found', 404);
            return;
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        try {
            $this->documentModel->delete($id);

            // 记录历史
            $this->historyModel->logChange(
                $document['templateId'],
                'document_removed',
                "Document '{$document['documentTitle']}' removed",
                $adminId
            );

            $this->logDocumentMutation(
                null,
                'delete',
                (int) $document['templateId'],
                (string) ($document['documentTitle'] ?? '')
            );

            Response::success(['message' => 'Document deleted successfully']);

        } catch (Exception $e) {
            $this->logDocumentMutationFailure(
                null,
                'delete',
                (int) ($document['templateId'] ?? 0),
                'documentDeleteFailure',
                'Failed to delete document: ' . $e->getMessage()
            );
            Response::error('Failed to delete document: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取单个文档详情
     * GET /api/kyc-template-documents/{id}
     */
    public function show($id) {
        $document = $this->documentModel->findById($id);

        if (!$document) {
            Response::error('Document not found', 404);
            return;
        }

        Response::success(['document' => $document]);
    }

    private function logDocumentMutationFailure($input, $operationTypeKey, $templateId, $failureMethod, $apiMessage) {
        $subModule = OperationLogPages::resolveLogKycTemplatesFromRequest(is_array($input) ? $input : []);
        list($detailZh, $detailEn) = call_user_func(['KycOperationLogTexts', $failureMethod], $apiMessage);
        (new AdminOperationLogWriter())->logKycTemplateMutation(
            $subModule,
            trim((string) $operationTypeKey) ?: 'edit',
            (int) $templateId > 0 ? (int) $templateId : null,
            $detailZh,
            $detailEn
        );
    }

    private function logDocumentMutation($input, $operationTypeKey, $templateId, $documentTitle, $newDocumentTitle = null) {
        $subModule = OperationLogPages::resolveLogKycTemplatesFromRequest(is_array($input) ? $input : []);
        $templateName = KycOperationLogTexts::resolveTemplateName($templateId);
        $documentTitle = trim((string) $documentTitle);
        $op = trim((string) $operationTypeKey) ?: 'edit';

        if ($op === 'add') {
            list($detailZh, $detailEn) = KycOperationLogTexts::addDocument($templateName, $documentTitle);
        } elseif ($op === 'delete') {
            list($detailZh, $detailEn) = KycOperationLogTexts::deleteDocument($templateName, $documentTitle);
        } else {
            $newTitle = trim((string) ($newDocumentTitle ?? $documentTitle));
            if ($newTitle !== '' && $newTitle !== $documentTitle) {
                list($detailZh, $detailEn) = KycOperationLogTexts::renameDocument(
                    $templateName,
                    $documentTitle,
                    $newTitle
                );
            } else {
                list($detailZh, $detailEn) = KycOperationLogTexts::updateDocument($templateName, $documentTitle);
            }
        }

        (new AdminOperationLogWriter())->logKycTemplateMutation(
            $subModule,
            $op,
            (int) $templateId,
            $detailZh,
            $detailEn
        );
    }
}
