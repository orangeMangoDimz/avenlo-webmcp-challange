<?php
/**
 * Withdrawal Verification Question Category Model
 * 对应表: withdrawalVerificationQuestionCategories
 */

require_once __DIR__ . '/BaseModel.php';

class WithdrawalVerificationQuestionCategory extends BaseModel {
    protected $table = 'withdrawalVerificationQuestionCategories';
    protected $primaryKey = 'id';
    protected $fillable = [
        'templateId',
        'categoryName',
        'description',
        'displayOrder',
        'isExpanded',
        'isActive',
        'isLocked'
    ];

    public function getTemplateCategories($templateId, $activeOnly = true) {
        $conditions = ['templateId' => $templateId];

        if ($activeOnly) {
            $conditions['isActive'] = 1;
        }

        return $this->findAll($conditions, 'displayOrder');
    }

    public function getCategoriesWithQuestionCount($templateId) {
        $sql = "SELECT
                    c.*,
                    COUNT(q.id) AS questionCount
                FROM withdrawalVerificationQuestionCategories c
                LEFT JOIN withdrawalVerificationQuestions q
                    ON c.id = q.categoryId AND q.isActive = 1
                WHERE c.templateId = :templateId
                GROUP BY c.id
                ORDER BY c.displayOrder";

        return $this->query($sql, ['templateId' => $templateId]);
    }

    public function updateOrder($categoryId, $newOrder) {
        return $this->update($categoryId, ['displayOrder' => $newOrder]);
    }

    public function createCategory($data) {
        if (!isset($data['isLocked'])) {
            $data['isLocked'] = 0;
        }

        return $this->create($data);
    }

    public function isLocked($categoryId) {
        $category = $this->findById($categoryId);
        return $category ? (int)($category['isLocked'] ?? 0) === 1 : false;
    }

    public function updateIfUnlocked($categoryId, $data) {
        if ($this->isLocked($categoryId)) {
            $allowedData = [];

            if (array_key_exists('categoryName', $data)) {
                $allowedData['categoryName'] = $data['categoryName'];
            }
            if (array_key_exists('description', $data)) {
                $allowedData['description'] = $data['description'];
            }

            if (empty($allowedData)) {
                return false;
            }

            return $this->update($categoryId, $allowedData);
        }

        unset($data['isLocked']);
        return $this->update($categoryId, $data);
    }

    public function deleteIfUnlocked($categoryId) {
        if ($this->isLocked($categoryId)) {
            return false;
        }

        return $this->delete($categoryId);
    }

    public function updateBatchOrder($orders) {
        $updated = 0;
        foreach ($orders as $categoryId => $order) {
            if ($this->updateOrder($categoryId, $order)) {
                $updated++;
            }
        }
        return $updated;
    }
}
