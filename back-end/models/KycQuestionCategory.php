<?php
/**
 * KYC Question Category Model
 * 对应表: kycQuestionCategories
 */

require_once __DIR__ . '/BaseModel.php';

class KycQuestionCategory extends BaseModel {
    protected $table = 'kycQuestionCategories';
    protected $primaryKey = 'id';
    protected $fillable = [
        'templateId',
        'categoryName',
        'description',
        'displayOrder',
        'isExpanded',
        'isActive'
    ];

    /**
     * 获取模板的所有分类
     */
    public function getTemplateCategories($templateId, $activeOnly = true) {
        $conditions = ['templateId' => $templateId];

        if ($activeOnly) {
            $conditions['isActive'] = 1;
        }

        return $this->findAll($conditions, 'displayOrder');
    }

    /**
     * 获取分类及其问题数量
     */
    public function getCategoriesWithQuestionCount($templateId) {
        $sql = "SELECT
                    c.*,
                    COUNT(q.id) AS questionCount
                FROM kycQuestionCategories c
                LEFT JOIN kycQuestions q ON c.id = q.categoryId AND q.isActive = 1
                WHERE c.templateId = :templateId
                GROUP BY c.id
                ORDER BY c.displayOrder";

        return $this->query($sql, ['templateId' => $templateId]);
    }

    /**
     * 更新分类顺序
     */
    public function updateOrder($categoryId, $newOrder) {
        return $this->update($categoryId, ['displayOrder' => $newOrder]);
    }

    /**
     * 批量更新分类顺序
     */
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
