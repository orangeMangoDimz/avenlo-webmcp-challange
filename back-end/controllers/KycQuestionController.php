<?php
/**
 * KYC Question 控制器
 */

require_once __DIR__ . '/../models/KycQuestion.php';
require_once __DIR__ . '/../models/KycQuestionOption.php';
require_once __DIR__ . '/../models/KycQuestionDocumentType.php';
require_once __DIR__ . '/../models/KycQuestionCategory.php';
require_once __DIR__ . '/../models/KycTemplateEditHistory.php';
require_once __DIR__ . '/../models/KycTemplate.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogTexts/KycOperationLogTexts.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class KycQuestionController {
    private $questionModel;
    private $optionModel;
    private $documentTypeModel;
    private $categoryModel;
    private $historyModel;

    public function __construct() {
        $this->questionModel = new KycQuestion();
        $this->optionModel = new KycQuestionOption();
        $this->documentTypeModel = new KycQuestionDocumentType();
        $this->categoryModel = new KycQuestionCategory();
        $this->historyModel = new KycTemplateEditHistory();
    }

    /**
     * 获取模板的所有问题（客户端接口）
     * GET /api/kyc-templates/{templateId}/questions
     * 只返回active的问题
     */
    public function getTemplateQuestions($templateId) {
        // 客户端接口：始终只返回active的问题
        $activeOnly = true;
        $questions = $this->questionModel->getTemplateQuestions($templateId, $activeOnly);

        // 为选择题类型添加选项数据，为文件上传类型添加文档类型数据
        foreach ($questions as &$question) {
            if (in_array($question['questionType'], ['single_choice', 'multiple_choice'])) {
                $options = $this->optionModel->getQuestionOptions($question['questionId'], true);
                $question['options'] = array_map(function($option) {
                    return [
                        'id' => $option['id'],
                        'optionValue' => $option['optionValue']
                    ];
                }, $options);
            } elseif ($question['questionType'] === 'file_upload') {
                // 获取文件上传类型的文档类型选项
                $documentTypes = $this->documentTypeModel->getQuestionDocumentTypes($question['questionId']);
                $question['options'] = array_map(function($docType) {
                    return [
                        'documentType' => $docType['documentType'],
                        'documentDisplayName' => $docType['documentDisplayName']
                    ];
                }, $documentTypes);
            }
        }

        Response::success($questions);
    }

    /**
     * 获取模板的所有问题（后台管理接口）
     * POST /api/kyc-templates/{templateId}/admin-questions
     * 支持通过参数控制是否返回所有问题（包括inactive）
     */
    public function getAdminTemplateQuestions($templateId) {
        // 后台管理接口：从POST请求体中读取JSON数据
        $activeOnly = true; // 默认值

        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['active_only'])) {
            $activeOnly = (bool)$input['active_only'];
        }

        $questions = $this->questionModel->getTemplateQuestions($templateId, $activeOnly);

        // 为选择题类型添加选项数据，为文件上传类型添加文档类型数据
        foreach ($questions as &$question) {
            if (in_array($question['questionType'], ['single_choice', 'multiple_choice'])) {
                $options = $this->optionModel->getQuestionOptions($question['questionId'], true);
                $question['options'] = array_map(function($option) {
                    return [
                        'id' => $option['id'],
                        'optionValue' => $option['optionValue']
                    ];
                }, $options);
            } elseif ($question['questionType'] === 'file_upload') {
                // 获取文件上传类型的文档类型选项
                $documentTypes = $this->documentTypeModel->getQuestionDocumentTypes($question['questionId']);
                $question['options'] = array_map(function($docType) {
                    return [
                        'documentType' => $docType['documentType'],
                        'documentDisplayName' => $docType['documentDisplayName']
                    ];
                }, $documentTypes);
            }
        }

        Response::success([
            'questions' => $questions,
            'total' => count($questions)
        ]);
    }

    /**
     * 获取分类的所有问题
     * GET /api/kyc-categories/{categoryId}/questions
     */
    public function getCategoryQuestions($categoryId) {
        $activeOnly = isset($_GET['active_only']) ? (bool)$_GET['active_only'] : true;

        $questions = $this->questionModel->getCategoryQuestions($categoryId, $activeOnly);

        // 为选择题类型添加选项数据，为文件上传类型添加文档类型数据
        foreach ($questions as &$question) {
            if (in_array($question['questionType'], ['single_choice', 'multiple_choice'])) {
                $options = $this->optionModel->getQuestionOptions($question['questionId'], true);
                $question['options'] = array_map(function($option) {
                    return [
                        'id' => $option['id'],
                        'optionValue' => $option['optionValue']
                    ];
                }, $options);
            } elseif ($question['questionType'] === 'file_upload') {
                // 获取文件上传类型的文档类型选项
                $documentTypes = $this->documentTypeModel->getQuestionDocumentTypes($question['questionId']);
                $question['options'] = array_map(function($docType) {
                    return [
                        'documentType' => $docType['documentType'],
                        'documentDisplayName' => $docType['documentDisplayName']
                    ];
                }, $documentTypes);
            }
        }

        Response::success([
            'questions' => $questions,
            'total' => count($questions)
        ]);
    }

    /**
     * 获取问题详情
     * GET /api/kyc-questions/{id}
     */
    public function show($id) {
        $question = $this->questionModel->getQuestionDetails($id);

        if (!$question) {
            Response::notFound('Question not found');
        }

        Response::success($question);
    }

    /**
     * 创建新问题
     * POST /api/kyc-questions
     */
    public function create() {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

//        // 验证
//        $validator = new Validator($input);
//        $validator->required(['templateId', 'categoryId', 'questionText', 'questionType']);
//        $validator->in('questionType', [
//            'text', 'number', 'email', 'tel', 'date',
//            'single_choice', 'multiple_choice', 'yes_no',
//            'file_upload', 'textarea'
//        ]);
//
//        if (!$validator->validate()) {
//            Response::validationError($validator->getErrors());
//        }

        // 获取当前管理员ID
        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        $data = [
            'templateId' => $input['templateId'],
            'categoryId' => $input['categoryId'],
            'questionText' => $input['questionText'],
            'helpText' => $input['helpText'] ?? null,
            'questionType' => $input['questionType'],
            'validationRules' => $input['validationRule'] ?? null,
            'isRequired' => $input['isRequired'] == false ? 0 : (empty($input['isRequired']) ? 1 : 1),
            'isActive' => $input['isActive'] == false ? 0 : (empty($input['isActive']) ? 1 : 1),
            'displayOrder' => $input['displayOrder'] ?? 0,
            'metadata' => isset($input['metadata']) ? json_encode($input['metadata']) : null,
            'createdBy' => $adminId
        ];

        try {
            $questionId = $this->questionModel->createQuestion($data);

            // 如果是选择题，创建选项
            if (in_array($input['questionType'], ['single_choice', 'multiple_choice'])) {
                if (isset($input['options']) && is_array($input['options'])) {
                    $this->optionModel->createBatch($questionId, $input['options']);
                }
            }

            // 如果是文件上传，创建文档类型
            if ($input['questionType'] === 'file_upload') {
                if (isset($input['documentTypes']) && is_array($input['documentTypes'])) {
                    $this->documentTypeModel->createBatch($questionId, $input['documentTypes']);
                }
            }

            // 记录历史
            $this->historyModel->logChange(
                $input['templateId'],
                'question_added',
                "Question added: {$input['questionText']}",
                $adminId
            );

            $question = $this->questionModel->getQuestionDetails($questionId);
            $this->logQuestionMutation(
                $input,
                'add',
                (int) $input['templateId'],
                (string) ($input['questionText'] ?? '')
            );

            Response::created($question, 'Question created successfully');

        } catch (Exception $e) {
            $this->logQuestionMutationFailure(
                $input,
                'add',
                (int) ($input['templateId'] ?? 0),
                'questionAddFailure',
                'Failed to create question: ' . $e->getMessage()
            );
            Response::error('Failed to create question: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新问题
     * PUT /api/kyc-questions/{id}
     */
    public function update($id) {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $question = $this->questionModel->findById($id);

        if (!$question) {
            $this->logQuestionMutationFailure($input, 'edit', 0, 'questionEditFailure', 'Question not found');
            Response::notFound('Question not found');
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

        if (isset($input['validationRule'])) {
            $data['validationRules'] = $input['validationRule'];
        }

        if (isset($input['isRequired'])) {
            $data['isRequired'] = $input['isRequired'] == false ? 0 : (empty($input['isRequired']) ? 1 : 1);
        }

        if (isset($input['isActive'])) {
            $data['isActive'] = $input['isActive'] == false ? 0 : (empty($input['isActive']) ? 1 : 1);
        }

        if (isset($input['displayOrder'])) {
            $data['displayOrder'] = $input['displayOrder'];
        }

        $data['updatedBy'] = $adminId;

        try {
            $this->questionModel->update($id, $data);

            // 更新选项（如果提供）
            if (isset($input['options']) && is_array($input['options'])) {
                // 跳过空选项数组 [""] 的处理
                $validOptions = array_filter($input['options'], function($option) {
                    return !empty(trim($option));
                });

                if (!empty($validOptions)) {
                    $this->optionModel->updateQuestionOptions($id, $input['options']);
                }
            }

            // 更新文档类型（如果提供）
            if (isset($input['documentTypes']) && is_array($input['documentTypes'])) {
                $this->documentTypeModel->updateQuestionDocumentTypes($id, $input['documentTypes']);
            }

            // 记录历史
            $this->historyModel->logChange(
                $question['templateId'],
                'question_modified',
                "Question #{$question['questionNumber']} modified",
                $adminId
            );

            $updatedQuestion = $this->questionModel->getQuestionDetails($id);
            $this->logQuestionMutation(
                $input,
                'edit',
                (int) $question['templateId'],
                (string) ($question['questionText'] ?? ''),
                (string) ($updatedQuestion['questionText'] ?? $question['questionText'] ?? '')
            );

            Response::success($updatedQuestion, 'Question updated successfully');

        } catch (Exception $e) {
            $this->logQuestionMutationFailure(
                $input,
                'edit',
                (int) ($question['templateId'] ?? 0),
                'questionEditFailure',
                'Failed to update question: ' . $e->getMessage()
            );
            Response::error('Failed to update question: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 删除问题
     * DELETE /api/kyc-questions/{id}
     */
    public function delete($id) {
        $question = $this->questionModel->findById($id);

        if (!$question) {
            $this->logQuestionMutationFailure(null, 'delete', 0, 'questionDeleteFailure', 'Question not found');
            Response::notFound('Question not found');
        }
        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        try {
            $this->questionModel->delete($id);

            // 记录历史
            $this->historyModel->logChange(
                $question['templateId'],
                'question_removed',
                "Question #{$question['questionNumber']} '{$question['questionText']}' deleted",
                $adminId
            );

            $this->logQuestionMutation(
                null,
                'delete',
                (int) $question['templateId'],
                (string) ($question['questionText'] ?? '')
            );

            Response::success(null, 'Question deleted successfully');

        } catch (Exception $e) {
            $this->logQuestionMutationFailure(
                null,
                'delete',
                (int) ($question['templateId'] ?? 0),
                'questionDeleteFailure',
                'Failed to delete question: ' . $e->getMessage()
            );
            Response::error('Failed to delete question: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 批量更新问题顺序
     * PUT /api/kyc-questions/reorder
     */
    public function reorder() {
        $input = json_decode(file_get_contents('php://input'), true);

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

    /**
     * 复制问题
     * POST /api/kyc-questions/{id}/duplicate
     */
    public function duplicate($id) {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = [];
        }

        $question = $this->questionModel->getQuestionDetails($id);

        if (!$question) {
            $this->logQuestionMutationFailure($input, 'add', 0, 'questionAddFailure', 'Question not found');
            Response::notFound('Question not found');
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        // 移除ID和时间戳
        unset($question['questionId']);
        unset($question['id']);
        unset($question['createdAt']);
        unset($question['updatedAt']);

        // 修改问题文本
        $question['questionText'] .= ' (Copy)';
        $question['createdBy'] = $adminId;

        try {
            $newQuestionId = $this->questionModel->createQuestion($question);

            // 复制选项
            if (isset($question['options']) && is_array($question['options'])) {
                $options = array_column($question['options'], 'optionValue');
                $this->optionModel->createBatch($newQuestionId, $options);
            }

            // 复制文档类型
            if (isset($question['documentTypes']) && is_array($question['documentTypes'])) {
                $this->documentTypeModel->createBatch($newQuestionId, $question['documentTypes']);
            }

            $newQuestion = $this->questionModel->getQuestionDetails($newQuestionId);
            $this->logQuestionMutation(
                $input,
                'duplicate',
                (int) $question['templateId'],
                (string) ($question['questionText'] ?? '')
            );

            Response::created($newQuestion, 'Question duplicated successfully');

        } catch (Exception $e) {
            $this->logQuestionMutationFailure(
                $input,
                'add',
                (int) ($question['templateId'] ?? 0),
                'questionAddFailure',
                'Failed to duplicate question: ' . $e->getMessage()
            );
            Response::error('Failed to duplicate question: ' . $e->getMessage(), 500);
        }
    }

    private function logQuestionMutationFailure($input, $operationTypeKey, $templateId, $failureMethod, $apiMessage) {
        $subModule = OperationLogPages::resolveLogKycTemplatesFromRequest(is_array($input) ? $input : []);
        list($detailZh, $detailEn) = call_user_func(['KycOperationLogTexts', $failureMethod], $apiMessage);
        $op = trim((string) $operationTypeKey) ?: 'edit';
        if ($op === 'duplicate') {
            $op = 'add';
        }
        (new AdminOperationLogWriter())->logKycTemplateMutation(
            $subModule,
            $op,
            (int) $templateId > 0 ? (int) $templateId : null,
            $detailZh,
            $detailEn
        );
    }

    private function logQuestionMutation(
        $input,
        $operationTypeKey,
        $templateId,
        $questionText,
        $newQuestionText = null
    ) {
        $subModule = OperationLogPages::resolveLogKycTemplatesFromRequest(is_array($input) ? $input : []);
        $templateName = KycOperationLogTexts::resolveTemplateName($templateId);
        $questionText = trim((string) $questionText);
        $op = trim((string) $operationTypeKey) ?: 'edit';

        if ($op === 'add') {
            list($detailZh, $detailEn) = KycOperationLogTexts::addQuestion($templateName, $questionText);
        } elseif ($op === 'delete') {
            list($detailZh, $detailEn) = KycOperationLogTexts::deleteQuestion($templateName, $questionText);
        } elseif ($op === 'duplicate') {
            list($detailZh, $detailEn) = KycOperationLogTexts::duplicateQuestion($templateName, $questionText);
            $op = 'add';
        } else {
            $newText = trim((string) ($newQuestionText ?? $questionText));
            if ($newText !== '' && $newText !== $questionText) {
                list($detailZh, $detailEn) = KycOperationLogTexts::changeQuestion(
                    $templateName,
                    $questionText,
                    $newText
                );
            } else {
                list($detailZh, $detailEn) = KycOperationLogTexts::updateQuestion(
                    $templateName,
                    $questionText
                );
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
