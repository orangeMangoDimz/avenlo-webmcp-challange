<?php
/**
 * KYC Category 控制器
 */

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
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class KycCategoryController {
    private $categoryModel;
    private $historyModel;

    public function __construct() {
        $this->categoryModel = new KycQuestionCategory();
        $this->historyModel = new KycTemplateEditHistory();
    }

    /**
     * 获取模板的所有分类
     * GET /api/kyc-templates/{templateId}/categories
     */
    public function getTemplateCategories($templateId) {
        $activeOnly = isset($_GET['active_only']) ? (bool)$_GET['active_only'] : true;
        $withCount = isset($_GET['with_count']) ? (bool)$_GET['with_count'] : false;

        if ($withCount) {
            $categories = $this->categoryModel->getCategoriesWithQuestionCount($templateId);
        } else {
            $categories = $this->categoryModel->getTemplateCategories($templateId, $activeOnly);
        }

        Response::success([
            'categories' => $categories,
            'total' => count($categories)
        ]);
    }

    /**
     * 获取分类详情
     * GET /api/kyc-categories/{id}
     */
    public function show($id) {
        $category = $this->categoryModel->findById($id);

        if (!$category) {
            Response::notFound('Category not found');
        }

        Response::success($category);
    }

    /**
     * 创建新分类
     * POST /api/kyc-categories
     */
    public function create() {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

//        // 验证
//        $validator = new Validator($input);
//        $validator->required(['templateId', 'categoryName']);
//        $validator->maxLength('categoryName', 200);
//
//        if (!$validator->validate()) {
//            Response::validationError($validator->getErrors());
//        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        $data = [
            'templateId' => $input['templateId'],
            'categoryName' => $input['name'],
            'description' => $input['description'] ?? null,
            'displayOrder' => $input['order'] ?? 0,
            'isExpanded' => $input['isExpanded'] ?? 1,
            'isActive' => $input['isActive'] ?? 1
        ];

        try {
            $categoryId = $this->categoryModel->create($data);

            // 记录历史
            $this->historyModel->logChange(
                $input['templateId'],
                'category_added',
                "Category '{$input['name']}' created",
                $adminId
            );

            $category = $this->categoryModel->findById($categoryId);
            $this->logCategoryMutation(
                $input,
                'add',
                (int) $input['templateId'],
                (string) ($input['name'] ?? '')
            );

            Response::created($category, 'Category created successfully');

        } catch (Exception $e) {
            $this->logCategoryMutationFailure(
                $input,
                'add',
                (int) ($input['templateId'] ?? 0),
                'categoryAddFailure',
                'Failed to create category: ' . $e->getMessage()
            );
            Response::error('Failed to create category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新分类
     * PUT /api/kyc-categories/{id}
     */
    public function update($id) {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $category = $this->categoryModel->findById($id);

        if (!$category) {
            $this->logCategoryMutationFailure($input, 'edit', 0, 'categoryEditFailure', 'Category not found');
            Response::notFound('Category not found');
        }
        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        $data = [];

        if (isset($input['categoryName'])) {
            $data['categoryName'] = $input['categoryName'];
        }

        if (isset($input['description'])) {
            $data['description'] = $input['description'];
        }

        if (isset($input['displayOrder'])) {
            $data['displayOrder'] = $input['displayOrder'];
        }

        if (isset($input['isExpanded'])) {
            $data['isExpanded'] = $input['isExpanded'];
        }

        if (isset($input['isActive'])) {
            $data['isActive'] = $input['isActive'];
        }

        try {
            $this->categoryModel->update($id, $data);

            // 记录历史
            $this->historyModel->logChange(
                $category['templateId'],
                'category_modified',
                "Category '{$category['categoryName']}' updated",
                $adminId
            );

            $updatedCategory = $this->categoryModel->findById($id);
            $newName = (string) ($updatedCategory['categoryName'] ?? $category['categoryName'] ?? '');
            $oldName = (string) ($category['categoryName'] ?? '');
            $this->logCategoryMutation(
                $input,
                'edit',
                (int) $category['templateId'],
                $oldName,
                $newName
            );

            Response::success($updatedCategory, 'Category updated successfully');

        } catch (Exception $e) {
            $this->logCategoryMutationFailure(
                $input,
                'edit',
                (int) ($category['templateId'] ?? 0),
                'categoryEditFailure',
                'Failed to update category: ' . $e->getMessage()
            );
            Response::error('Failed to update category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 删除分类
     * DELETE /api/kyc-categories/{id}
     */
    public function delete($id) {
        $category = $this->categoryModel->findById($id);

        if (!$category) {
            $this->logCategoryMutationFailure(null, 'delete', 0, 'categoryDeleteFailure', 'Category not found');
            Response::notFound('Category not found');
        }

        // 检查分类下是否有问题
        $sql = "SELECT COUNT(*) as count FROM kycQuestions WHERE categoryId = :id AND isActive = 1";
        $result = $this->categoryModel->queryOne($sql, ['id' => $id]);

        if ($result && $result['count'] > 0) {
            $this->logCategoryMutationFailure(
                null,
                'delete',
                (int) ($category['templateId'] ?? 0),
                'categoryDeleteFailure',
                'Cannot delete category that contains active questions'
            );
            Response::error('Cannot delete category that contains active questions', 400);
        }

        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        try {
            $this->categoryModel->delete($id);

            // 记录历史
            $this->historyModel->logChange(
                $category['templateId'],
                'category_deleted',
                "Category '{$category['categoryName']}' deleted",
                $adminId
            );

            $this->logCategoryMutation(
                null,
                'delete',
                (int) $category['templateId'],
                (string) ($category['categoryName'] ?? '')
            );

            Response::success(null, 'Category deleted successfully');

        } catch (Exception $e) {
            $this->logCategoryMutationFailure(
                null,
                'delete',
                (int) ($category['templateId'] ?? 0),
                'categoryDeleteFailure',
                'Failed to delete category: ' . $e->getMessage()
            );
            Response::error('Failed to delete category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 批量更新分类顺序
     * PUT /api/kyc-categories/reorder
     */
    public function reorder() {
        $input = json_decode(file_get_contents('php://input'), true);

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

    private function logCategoryMutationFailure($input, $operationTypeKey, $templateId, $failureMethod, $apiMessage) {
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

    private function logCategoryMutation($input, $operationTypeKey, $templateId, $categoryName, $newCategoryName = null) {
        $subModule = OperationLogPages::resolveLogKycTemplatesFromRequest(is_array($input) ? $input : []);
        $templateName = KycOperationLogTexts::resolveTemplateName($templateId);
        $categoryName = trim((string) $categoryName);
        $op = trim((string) $operationTypeKey) ?: 'edit';

        if ($op === 'add') {
            list($detailZh, $detailEn) = KycOperationLogTexts::addCategory($templateName, $categoryName);
        } elseif ($op === 'delete') {
            list($detailZh, $detailEn) = KycOperationLogTexts::deleteCategory($templateName, $categoryName);
        } else {
            $newName = trim((string) ($newCategoryName ?? $categoryName));
            if ($newName !== '' && $newName !== $categoryName) {
                list($detailZh, $detailEn) = KycOperationLogTexts::renameCategory(
                    $templateName,
                    $categoryName,
                    $newName
                );
            } else {
                list($detailZh, $detailEn) = KycOperationLogTexts::updateCategory(
                    $templateName,
                    $categoryName
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
