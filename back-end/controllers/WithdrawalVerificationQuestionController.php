<?php
/**
 * Withdrawal Verification Question Controller
 */

require_once __DIR__ . '/../models/WithdrawalVerificationQuestion.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionOption.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionDocumentType.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplate.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplateEditHistory.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../services/OperationLog/WithdrawKycTemplateOperationLog.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class WithdrawalVerificationQuestionController {
    private $questionModel;
    private $optionModel;
    private $documentTypeModel;
    private $templateModel;
    private $historyModel;

    public function __construct() {
        $this->questionModel = new WithdrawalVerificationQuestion();
        $this->optionModel = new WithdrawalVerificationQuestionOption();
        $this->documentTypeModel = new WithdrawalVerificationQuestionDocumentType();
        $this->templateModel = new WithdrawalVerificationTemplate();
        $this->historyModel = new WithdrawalVerificationTemplateEditHistory();
    }

    public function getTemplateQuestions($templateId) {
        $questions = $this->questionModel->getTemplateQuestions($templateId, true);
        $questions = $this->attachQuestionExtras($questions);
        Response::success($questions);
    }

    public function getAdminTemplateQuestions($templateId) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $activeOnly = array_key_exists('active_only', $input) ? (bool)$input['active_only'] : true;

        $questions = $this->questionModel->getTemplateQuestions($templateId, $activeOnly);
        $questions = $this->attachQuestionExtras($questions);

        Response::success([
            'questions' => $questions,
            'total' => count($questions)
        ]);
    }

    public function getCategoryQuestions($categoryId) {
        $activeOnly = isset($_GET['active_only']) ? (bool)$_GET['active_only'] : true;
        $questions = $this->attachQuestionExtras($this->questionModel->getCategoryQuestions($categoryId, $activeOnly), false);

        Response::success([
            'questions' => $questions,
            'total' => count($questions)
        ]);
    }

    public function show($id) {
        $question = $this->questionModel->getQuestionDetails($id);
        if (!$question) {
            Response::notFound('Question not found');
        }

        Response::success($question);
    }

    public function create($allowScope = false, $allowLocked = false) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        if (empty($input['templateId']) || empty($input['categoryId']) || empty($input['questionText']) || empty($input['questionType'])) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'add',
                (int) ($input['templateId'] ?? 0),
                'withdrawKycQuestionAddFailure',
                OperationLogTextHelpers::validationErrorsToMessage([
                    'templateId' => empty($input['templateId']) ? 'templateId is required' : null,
                    'categoryId' => empty($input['categoryId']) ? 'categoryId is required' : null,
                    'questionText' => empty($input['questionText']) ? 'questionText is required' : null,
                    'questionType' => empty($input['questionType']) ? 'questionType is required' : null
                ])
            );
            Response::validationError([
                'templateId' => empty($input['templateId']) ? 'templateId is required' : null,
                'categoryId' => empty($input['categoryId']) ? 'categoryId is required' : null,
                'questionText' => empty($input['questionText']) ? 'questionText is required' : null,
                'questionType' => empty($input['questionType']) ? 'questionType is required' : null
            ]);
        }

        $data = [
            'templateId' => (int)$input['templateId'],
            'categoryId' => (int)$input['categoryId'],
            'questionText' => $input['questionText'],
            'helpText' => $input['helpText'] ?? null,
            'questionType' => $input['questionType'],
            'validationRules' => $input['validationRule'] ?? ($input['validationRules'] ?? null),
            'isRequired' => array_key_exists('isRequired', $input) ? (int)(bool)$input['isRequired'] : 1,
            'isActive' => array_key_exists('isActive', $input) ? (int)(bool)$input['isActive'] : 1,
            'displayOrder' => $input['displayOrder'] ?? 0,
            'metadata' => isset($input['metadata']) ? json_encode($input['metadata']) : null,
            'createdBy' => $adminId
        ];

        if ($allowScope && array_key_exists('scope', $input)) {
            if (!$this->questionModel->isValidScope($input['scope'])) {
                WithdrawKycTemplateOperationLog::logMutationFailure(
                    $input,
                    'add',
                    (int) ($input['templateId'] ?? 0),
                    'withdrawKycQuestionAddFailure',
                    'scope must be one of: ' . implode(', ', WithdrawalVerificationQuestion::ALLOWED_SCOPES)
                );
                Response::validationError([
                    'scope' => 'scope must be one of: ' . implode(', ', WithdrawalVerificationQuestion::ALLOWED_SCOPES)
                ]);
            }
            $data['scope'] = $input['scope'];
        }

        if ($allowLocked && array_key_exists('isLocked', $input)) {
            $data['isLocked'] = (int)(bool)$input['isLocked'];
        }

        try {
            $questionId = $this->questionModel->createQuestion($data);

            if (in_array($input['questionType'], ['single_choice', 'multiple_choice'], true) && isset($input['options']) && is_array($input['options'])) {
                $this->optionModel->createBatch($questionId, $input['options']);
            }

            if ($input['questionType'] === 'file_upload' && isset($input['documentTypes']) && is_array($input['documentTypes'])) {
                $this->documentTypeModel->createBatch($questionId, $input['documentTypes']);
            }

            $this->historyModel->logChange(
                (int)$input['templateId'],
                'question_added',
                "Question added: {$input['questionText']}",
                $adminId
            );
            $this->templateModel->syncCounters((int)$input['templateId']);

            WithdrawKycTemplateOperationLog::logQuestionMutation(
                $input,
                'add',
                (int) $input['templateId'],
                (string) ($input['questionText'] ?? '')
            );

            Response::created($this->questionModel->getQuestionDetails($questionId), 'Question created successfully');
        } catch (Exception $e) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'add',
                (int) ($input['templateId'] ?? 0),
                'withdrawKycQuestionAddFailure',
                'Failed to create question: ' . $e->getMessage()
            );
            Response::error('Failed to create question: ' . $e->getMessage(), 500);
        }
    }

    public function update($id, $allowScope = false, $allowLocked = false) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $question = $this->questionModel->findById($id);
        if (!$question) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'edit',
                0,
                'withdrawKycQuestionEditFailure',
                'Question not found'
            );
            Response::notFound('Question not found');
        }

        if ($this->questionModel->isLocked($id)) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'edit',
                (int) ($question['templateId'] ?? 0),
                'withdrawKycQuestionEditFailure',
                'Locked question cannot be modified'
            );
            Response::error('Locked question cannot be modified', 400);
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        $data = [];
        if (isset($input['questionText'])) {
            $data['questionText'] = $input['questionText'];
        }
        if (isset($input['helpText'])) {
            $data['helpText'] = $input['helpText'];
        }
        if (isset($input['questionType'])) {
            $data['questionType'] = $input['questionType'];
        }
        if ($allowScope && isset($input['scope'])) {
            if (!$this->questionModel->isValidScope($input['scope'])) {
                WithdrawKycTemplateOperationLog::logMutationFailure(
                    $input,
                    'edit',
                    (int) ($question['templateId'] ?? 0),
                    'withdrawKycQuestionEditFailure',
                    'scope must be one of: ' . implode(', ', WithdrawalVerificationQuestion::ALLOWED_SCOPES)
                );
                Response::validationError([
                    'scope' => 'scope must be one of: ' . implode(', ', WithdrawalVerificationQuestion::ALLOWED_SCOPES)
                ]);
            }
            $data['scope'] = $input['scope'];
        } elseif (isset($input['scope'])) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'edit',
                (int) ($question['templateId'] ?? 0),
                'withdrawKycQuestionEditFailure',
                'scope is not allowed for this update endpoint'
            );
            Response::validationError([
                'scope' => 'scope is not allowed for this update endpoint'
            ]);
        }
        if (isset($input['validationRule'])) {
            $data['validationRules'] = $input['validationRule'];
        }
        if (isset($input['validationRules'])) {
            $data['validationRules'] = $input['validationRules'];
        }
        if (isset($input['isRequired'])) {
            $data['isRequired'] = (int)(bool)$input['isRequired'];
        }
        if (isset($input['isActive'])) {
            $data['isActive'] = (int)(bool)$input['isActive'];
        }
        if (isset($input['displayOrder'])) {
            $data['displayOrder'] = $input['displayOrder'];
        }
        if (isset($input['metadata'])) {
            $data['metadata'] = json_encode($input['metadata']);
        }
        if ($allowLocked && array_key_exists('isLocked', $input)) {
            $data['isLocked'] = (int)(bool)$input['isLocked'];
        }
        $data['updatedBy'] = $adminId;

        try {
            $this->questionModel->updateIfUnlocked($id, $data);

            if (isset($input['options']) && is_array($input['options'])) {
                $this->optionModel->updateQuestionOptions($id, $input['options']);
            }

            if (isset($input['documentTypes']) && is_array($input['documentTypes'])) {
                $this->documentTypeModel->updateQuestionDocumentTypes($id, $input['documentTypes']);
            }

            $this->historyModel->logChange(
                $question['templateId'],
                'question_modified',
                "Question #{$question['questionNumber']} modified",
                $adminId
            );
            $this->templateModel->syncCounters((int)$question['templateId']);

            $updated = $this->questionModel->getQuestionDetails($id);
            $oldText = (string) ($question['questionText'] ?? '');
            $newText = (string) ($updated['questionText'] ?? $oldText);
            WithdrawKycTemplateOperationLog::logQuestionMutation(
                $input,
                'edit',
                (int) $question['templateId'],
                $oldText,
                $newText
            );

            Response::success($updated, 'Question updated successfully');
        } catch (Exception $e) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'edit',
                (int) ($question['templateId'] ?? 0),
                'withdrawKycQuestionEditFailure',
                'Failed to update question: ' . $e->getMessage()
            );
            Response::error('Failed to update question: ' . $e->getMessage(), 500);
        }
    }

    public function delete($id) {
        $input = $_GET;
        $question = $this->questionModel->findById($id);
        if (!$question) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'delete',
                0,
                'withdrawKycQuestionDeleteFailure',
                'Question not found'
            );
            Response::notFound('Question not found');
        }

        if ($this->questionModel->isLocked($id)) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'delete',
                (int) ($question['templateId'] ?? 0),
                'withdrawKycQuestionDeleteFailure',
                'Locked question cannot be deleted'
            );
            Response::error('Locked question cannot be deleted', 400);
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        try {
            $this->questionModel->deleteIfUnlocked($id);

            $this->historyModel->logChange(
                $question['templateId'],
                'question_removed',
                "Question #{$question['questionNumber']} '{$question['questionText']}' deleted",
                $adminId
            );
            $this->templateModel->syncCounters((int)$question['templateId']);

            WithdrawKycTemplateOperationLog::logQuestionMutation(
                $input,
                'delete',
                (int) $question['templateId'],
                (string) ($question['questionText'] ?? '')
            );

            Response::success(null, 'Question deleted successfully');
        } catch (Exception $e) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'delete',
                (int) ($question['templateId'] ?? 0),
                'withdrawKycQuestionDeleteFailure',
                'Failed to delete question: ' . $e->getMessage()
            );
            Response::error('Failed to delete question: ' . $e->getMessage(), 500);
        }
    }

    public function reorder() {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!isset($input['orders']) || !is_array($input['orders'])) {
            Response::validationError(['orders' => 'Orders array is required']);
        }

        try {
            $updated = $this->questionModel->updateBatchOrder($input['orders']);
            Response::success([
                'updated' => $updated,
                'message' => "{$updated} questions reordered successfully"
            ]);
        } catch (Exception $e) {
            Response::error('Failed to reorder questions: ' . $e->getMessage(), 500);
        }
    }

    public function duplicate($id) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $question = $this->questionModel->getQuestionDetails($id);
        if (!$question) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'add',
                0,
                'withdrawKycQuestionAddFailure',
                'Question not found'
            );
            Response::notFound('Question not found');
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;
        $sourceText = (string) ($question['questionText'] ?? '');

        unset($question['id'], $question['questionId'], $question['createdAt'], $question['updatedAt']);
        $question['questionText'] .= ' (Copy)';
        $question['createdBy'] = $adminId;
        $question['isLocked'] = 0;

        try {
            $newQuestionId = $this->questionModel->createQuestion($question);

            if (isset($question['options']) && is_array($question['options'])) {
                $this->optionModel->createBatch($newQuestionId, $question['options']);
            }

            if (isset($question['documentTypes']) && is_array($question['documentTypes'])) {
                $this->documentTypeModel->createBatch($newQuestionId, $question['documentTypes']);
            }
            $this->templateModel->syncCounters((int)$question['templateId']);

            WithdrawKycTemplateOperationLog::logQuestionDuplicate(
                $input,
                (int) $question['templateId'],
                $sourceText
            );

            Response::created($this->questionModel->getQuestionDetails($newQuestionId), 'Question duplicated successfully');
        } catch (Exception $e) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'add',
                (int) ($question['templateId'] ?? 0),
                'withdrawKycQuestionAddFailure',
                'Failed to duplicate question: ' . $e->getMessage()
            );
            Response::error('Failed to duplicate question: ' . $e->getMessage(), 500);
        }
    }

    private function attachQuestionExtras($questions, $useQuestionIdAlias = true) {
        foreach ($questions as &$question) {
            $questionId = $useQuestionIdAlias
                ? ($question['questionId'] ?? $question['id'])
                : $question['id'];

            if (in_array($question['questionType'], ['single_choice', 'multiple_choice'], true)) {
                $question['options'] = $this->optionModel->formatOptionsForResponse(
                    $this->optionModel->getQuestionOptions($questionId, true)
                );
            } elseif ($question['questionType'] === 'file_upload') {
                $question['options'] = array_map(function ($docType) {
                    return [
                        'documentType' => $docType['documentType']
                    ];
                }, $this->documentTypeModel->getQuestionDocumentTypes($questionId));
            }
        }

        return $questions;
    }
}
