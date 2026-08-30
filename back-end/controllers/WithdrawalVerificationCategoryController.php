<?php
/**
 * Withdrawal Verification Category Controller
 */

require_once __DIR__ . '/../models/WithdrawalVerificationQuestionCategory.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplateEditHistory.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../services/OperationLog/WithdrawKycTemplateOperationLog.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class WithdrawalVerificationCategoryController {
    private $categoryModel;
    private $historyModel;

    public function __construct() {
        $this->categoryModel = new WithdrawalVerificationQuestionCategory();
        $this->historyModel = new WithdrawalVerificationTemplateEditHistory();
    }

    public function getTemplateCategories($templateId) {
        $activeOnly = isset($_GET['active_only']) ? (bool)$_GET['active_only'] : true;
        $withCount = isset($_GET['with_count']) ? (bool)$_GET['with_count'] : false;

        $categories = $withCount
            ? $this->categoryModel->getCategoriesWithQuestionCount($templateId)
            : $this->categoryModel->getTemplateCategories($templateId, $activeOnly);

        Response::success([
            'categories' => $categories,
            'total' => count($categories)
        ]);
    }

    public function show($id) {
        $category = $this->categoryModel->findById($id);
        if (!$category) {
            Response::notFound('Category not found');
        }

        Response::success($category);
    }

    public function create($allowScope = false, $allowLocked = false) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        if (empty($input['templateId']) || empty($input['name'])) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'add',
                (int) ($input['templateId'] ?? 0),
                'withdrawKycCategoryAddFailure',
                OperationLogTextHelpers::validationErrorsToMessage([
                    'templateId' => empty($input['templateId']) ? 'templateId is required' : null,
                    'name' => empty($input['name']) ? 'name is required' : null
                ])
            );
            Response::validationError([
                'templateId' => empty($input['templateId']) ? 'templateId is required' : null,
                'name' => empty($input['name']) ? 'name is required' : null
            ]);
        }

        try {
            $data = [
                'templateId' => (int)$input['templateId'],
                'categoryName' => $input['name'],
                'description' => $input['description'] ?? null,
                'displayOrder' => $input['order'] ?? 0,
                'isExpanded' => isset($input['isExpanded']) ? (int)(bool)$input['isExpanded'] : 1,
                'isActive' => isset($input['isActive']) ? (int)(bool)$input['isActive'] : 1
            ];

            if ($allowLocked && array_key_exists('isLocked', $input)) {
                $data['isLocked'] = (int)(bool)$input['isLocked'];
            }

            $categoryId = $this->categoryModel->createCategory($data);

            $this->historyModel->logChange(
                (int)$input['templateId'],
                'category_added',
                "Category '{$input['name']}' created",
                $adminId
            );

            WithdrawKycTemplateOperationLog::logCategoryMutation(
                $input,
                'add',
                (int) $input['templateId'],
                (string) ($input['name'] ?? '')
            );

            Response::created($this->categoryModel->findById($categoryId), 'Category created successfully');
        } catch (Exception $e) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'add',
                (int) ($input['templateId'] ?? 0),
                'withdrawKycCategoryAddFailure',
                'Failed to create category: ' . $e->getMessage()
            );
            Response::error('Failed to create category: ' . $e->getMessage(), 500);
        }
    }

    public function update($id, $allowScope = false, $allowLocked = false) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $category = $this->categoryModel->findById($id);
        if (!$category) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'edit',
                0,
                'withdrawKycCategoryEditFailure',
                'Category not found'
            );
            Response::notFound('Category not found');
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;
        $isLocked = $this->categoryModel->isLocked($id);

        $data = [];
        if (isset($input['categoryName'])) {
            $data['categoryName'] = $input['categoryName'];
        }
        if (isset($input['name'])) {
            $data['categoryName'] = $input['name'];
        }
        if (isset($input['description'])) {
            $data['description'] = $input['description'];
        }
        if (isset($input['displayOrder'])) {
            $data['displayOrder'] = $input['displayOrder'];
        }
        if (isset($input['order'])) {
            $data['displayOrder'] = $input['order'];
        }
        if (isset($input['isExpanded'])) {
            $data['isExpanded'] = (int)(bool)$input['isExpanded'];
        }
        if (isset($input['isActive'])) {
            $data['isActive'] = (int)(bool)$input['isActive'];
        }
        if ($allowLocked && array_key_exists('isLocked', $input)) {
            $data['isLocked'] = (int)(bool)$input['isLocked'];
        }

        try {
            $updated = $this->categoryModel->updateIfUnlocked($id, $data);

            if (!$updated) {
                if ($isLocked) {
                    WithdrawKycTemplateOperationLog::logMutationFailure(
                        $input,
                        'edit',
                        (int) ($category['templateId'] ?? 0),
                        'withdrawKycCategoryEditFailure',
                        'Locked category can only modify categoryName and description'
                    );
                    Response::error('Locked category can only modify categoryName and description', 400);
                }

                WithdrawKycTemplateOperationLog::logMutationFailure(
                    $input,
                    'edit',
                    (int) ($category['templateId'] ?? 0),
                    'withdrawKycCategoryEditFailure',
                    'Failed to update category'
                );
                Response::error('Failed to update category', 400);
            }

            $this->historyModel->logChange(
                $category['templateId'],
                'category_modified',
                "Category '{$category['categoryName']}' updated",
                $adminId
            );

            $updatedCategory = $this->categoryModel->findById($id);
            $oldName = (string) ($category['categoryName'] ?? '');
            $newName = (string) ($updatedCategory['categoryName'] ?? $oldName);
            WithdrawKycTemplateOperationLog::logCategoryMutation(
                $input,
                'edit',
                (int) $category['templateId'],
                $oldName,
                $newName
            );

            Response::success($updatedCategory, 'Category updated successfully');
        } catch (Exception $e) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'edit',
                (int) ($category['templateId'] ?? 0),
                'withdrawKycCategoryEditFailure',
                'Failed to update category: ' . $e->getMessage()
            );
            Response::error('Failed to update category: ' . $e->getMessage(), 500);
        }
    }

    public function delete($id) {
        $input = $_GET;
        $category = $this->categoryModel->findById($id);
        if (!$category) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'delete',
                0,
                'withdrawKycCategoryDeleteFailure',
                'Category not found'
            );
            Response::notFound('Category not found');
        }

        if ($this->categoryModel->isLocked($id)) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'delete',
                (int) ($category['templateId'] ?? 0),
                'withdrawKycCategoryDeleteFailure',
                'Locked category cannot be deleted'
            );
            Response::error('Locked category cannot be deleted', 400);
        }

        $result = $this->categoryModel->queryOne(
            "SELECT COUNT(*) AS count FROM withdrawalVerificationQuestions WHERE categoryId = :id AND isActive = 1",
            ['id' => $id]
        );

        if ($result && (int)$result['count'] > 0) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'delete',
                (int) ($category['templateId'] ?? 0),
                'withdrawKycCategoryDeleteFailure',
                'Cannot delete category that contains active questions'
            );
            Response::error('Cannot delete category that contains active questions', 400);
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        try {
            $this->categoryModel->deleteIfUnlocked($id);

            $this->historyModel->logChange(
                $category['templateId'],
                'category_deleted',
                "Category '{$category['categoryName']}' deleted",
                $adminId
            );

            WithdrawKycTemplateOperationLog::logCategoryMutation(
                $input,
                'delete',
                (int) $category['templateId'],
                (string) ($category['categoryName'] ?? '')
            );

            Response::success(null, 'Category deleted successfully');
        } catch (Exception $e) {
            WithdrawKycTemplateOperationLog::logMutationFailure(
                $input,
                'delete',
                (int) ($category['templateId'] ?? 0),
                'withdrawKycCategoryDeleteFailure',
                'Failed to delete category: ' . $e->getMessage()
            );
            Response::error('Failed to delete category: ' . $e->getMessage(), 500);
        }
    }

    public function reorder() {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!isset($input['orders']) || !is_array($input['orders'])) {
            Response::validationError(['orders' => 'Orders array is required']);
        }

        try {
            $updated = $this->categoryModel->updateBatchOrder($input['orders']);
            Response::success([
                'updated' => $updated,
                'message' => "{$updated} categories reordered successfully"
            ]);
        } catch (Exception $e) {
            Response::error('Failed to reorder categories: ' . $e->getMessage(), 500);
        }
    }
}
