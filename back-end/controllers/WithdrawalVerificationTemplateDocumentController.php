<?php
/**
 * Withdrawal Verification Template Document Controller
 */

require_once __DIR__ . '/../models/WithdrawalVerificationTemplateDocument.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplateEditHistory.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../services/OperationLog/WithdrawKycTemplateOperationLog.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class WithdrawalVerificationTemplateDocumentController {
    private $documentModel;
    private $historyModel;

    public function __construct() {
        $this->documentModel = new WithdrawalVerificationTemplateDocument();
        $this->historyModel = new WithdrawalVerificationTemplateEditHistory();
    }

    public function getTemplateDocuments($templateId) {
        $activeOnly = isset($_GET['active_only']) ? (bool)$_GET['active_only'] : true;
        $documents = $this->documentModel->getTemplateDocuments($templateId, $activeOnly);

        Response::success([
            'documents' => $documents,
            'total' => count($documents)
        ]);
    }

    public function create() {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($input['templateId']) || empty($input['documentTitle']) || empty($input['documentContent'])) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'add',
                (int) ($input['templateId'] ?? 0),
                'withdrawKycDocumentAddFailure',
                OperationLogTextHelpers::validationErrorsToMessage([
                    'templateId' => empty($input['templateId']) ? 'templateId is required' : null,
                    'documentTitle' => empty($input['documentTitle']) ? 'documentTitle is required' : null,
                    'documentContent' => empty($input['documentContent']) ? 'documentContent is required' : null
                ])
            );
            Response::validationError([
                'templateId' => empty($input['templateId']) ? 'templateId is required' : null,
                'documentTitle' => empty($input['documentTitle']) ? 'documentTitle is required' : null,
                'documentContent' => empty($input['documentContent']) ? 'documentContent is required' : null
            ]);
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        try {
            $documentId = $this->documentModel->createTemplateDocument([
                'templateId' => (int)$input['templateId'],
                'documentId' => isset($input['documentId']) ? (int)$input['documentId'] : 0,
                'documentTitle' => $input['documentTitle'],
                'documentContent' => $input['documentContent'],
                'displayOrder' => $input['displayOrder'] ?? null,
                'isActive' => isset($input['isActive']) ? (int)(bool)$input['isActive'] : 1
            ]);

            $this->historyModel->logChange(
                (int)$input['templateId'],
                'document_added',
                "Document '{$input['documentTitle']}' added",
                $adminId
            );

            WithdrawKycTemplateOperationLog::logDocumentMutation(
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
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'add',
                (int) ($input['templateId'] ?? 0),
                'withdrawKycDocumentAddFailure',
                'Failed to create document: ' . $e->getMessage()
            );
            Response::error('Failed to create document: ' . $e->getMessage(), 500);
        }
    }

    public function update($id) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $document = $this->documentModel->findById($id);
        if (!$document) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'edit',
                0,
                'withdrawKycDocumentEditFailure',
                'Document not found'
            );
            Response::notFound('Document not found');
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        $data = [];
        foreach (['documentTitle', 'documentContent', 'displayOrder'] as $field) {
            if (isset($input[$field])) {
                $data[$field] = $input[$field];
            }
        }
        if (isset($input['isActive'])) {
            $data['isActive'] = (int)(bool)$input['isActive'];
        }

        try {
            $this->documentModel->update($id, $data);

            $this->historyModel->logChange(
                $document['templateId'],
                'document_modified',
                "Document '{$document['documentTitle']}' updated",
                $adminId
            );

            $oldTitle = (string) ($document['documentTitle'] ?? '');
            $newTitle = isset($input['documentTitle']) ? (string) $input['documentTitle'] : $oldTitle;
            WithdrawKycTemplateOperationLog::logDocumentMutation(
                $input,
                'edit',
                (int) $document['templateId'],
                $oldTitle,
                $newTitle
            );

            Response::success(['message' => 'Document updated successfully']);
        } catch (Exception $e) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'edit',
                (int) ($document['templateId'] ?? 0),
                'withdrawKycDocumentEditFailure',
                'Failed to update document: ' . $e->getMessage()
            );
            Response::error('Failed to update document: ' . $e->getMessage(), 500);
        }
    }

    public function delete($id) {
        $input = $_GET;
        $document = $this->documentModel->findById($id);
        if (!$document) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'delete',
                0,
                'withdrawKycDocumentDeleteFailure',
                'Document not found'
            );
            Response::notFound('Document not found');
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        try {
            $this->documentModel->delete($id);

            $this->historyModel->logChange(
                $document['templateId'],
                'document_removed',
                "Document '{$document['documentTitle']}' removed",
                $adminId
            );

            WithdrawKycTemplateOperationLog::logDocumentMutation(
                $input,
                'delete',
                (int) $document['templateId'],
                (string) ($document['documentTitle'] ?? '')
            );

            Response::success(['message' => 'Document deleted successfully']);
        } catch (Exception $e) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'delete',
                (int) ($document['templateId'] ?? 0),
                'withdrawKycDocumentDeleteFailure',
                'Failed to delete document: ' . $e->getMessage()
            );
            Response::error('Failed to delete document: ' . $e->getMessage(), 500);
        }
    }

    public function show($id) {
        $document = $this->documentModel->findById($id);
        if (!$document) {
            Response::notFound('Document not found');
        }

        Response::success(['document' => $document]);
    }
}
