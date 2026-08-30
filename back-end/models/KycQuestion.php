<?php
/**
 * KYC Question Model
 * 对应表: kycQuestions
 */

require_once __DIR__ . '/BaseModel.php';

class KycQuestion extends BaseModel {
    protected $table = 'kycQuestions';
    protected $primaryKey = 'id';
    protected $fillable = [
        'templateId',
        'categoryId',
        'questionNumber',
        'questionText',
        'helpText',
        'questionType',
        'validationRules',
        'isRequired',
        'isActive',
        'displayOrder',
        'metadata',
        'createdBy',
        'updatedBy'
    ];

    /**
     * 获取模板的所有问题（使用视图）
     */
    public function getTemplateQuestions($templateId, $activeOnly = true) {
        $sql = "SELECT * FROM vw_kyc_questions_full WHERE templateId = :templateId";

        if ($activeOnly) {
            $sql .= " AND isActive = 1";
        }

        $sql .= " ORDER BY displayOrder";

        return $this->query($sql, ['templateId' => $templateId]);
    }

    /**
     * 获取分类的所有问题
     */
    public function getCategoryQuestions($categoryId, $activeOnly = true) {
        $conditions = ['categoryId' => $categoryId];

        if ($activeOnly) {
            $conditions['isActive'] = 1;
        }

        return $this->findAll($conditions, 'displayOrder');
    }

    /**
     * 获取问题完整信息（含选项和文档类型）
     */
    public function getQuestionDetails($questionId) {
        $question = $this->findById($questionId);

        if (!$question) {
            return null;
        }

        // 获取选项（如果是选择题）
        if (in_array($question['questionType'], ['single_choice', 'multiple_choice'])) {
            $sql = "SELECT * FROM kycQuestionOptions
                    WHERE questionId = :id AND isActive = 1
                    ORDER BY displayOrder";
            $question['options'] = $this->query($sql, ['id' => $questionId]);
        }

        // 获取文档类型（如果是文件上传）
        if ($question['questionType'] === 'file_upload') {
            $sql = "SELECT * FROM kycQuestionDocumentTypes
                    WHERE questionId = :id
                    ORDER BY displayOrder";
            $question['documentTypes'] = $this->query($sql, ['id' => $questionId]);
        }

        return $question;
    }

    /**
     * 创建问题并自动分配问题编号
     */
    public function createQuestion($data) {
        // 获取模板的最大问题编号
        $sql = "SELECT COALESCE(MAX(questionNumber), 0) AS maxNumber
                FROM kycQuestions
                WHERE templateId = :templateId";
        $result = $this->queryOne($sql, ['templateId' => $data['templateId']]);

        $data['questionNumber'] = ($result['maxNumber'] ?? 0) + 1;
        $data['displayOrder'] = $data['questionNumber'];

        return $this->create($data);
    }

    /**
     * 更新问题顺序
     */
    public function updateQuestionOrder($questionId, $newOrder) {
        return $this->update($questionId, ['displayOrder' => $newOrder]);
    }

    /**
     * 批量更新问题顺序
     */
    public function updateBatchOrder($orders) {
        $updated = 0;
        foreach ($orders as $questionId => $order) {
            if ($this->updateQuestionOrder($questionId, $order)) {
                $updated++;
            }
        }
        return $updated;
    }

    /**
     * 按模板统计问题总数（含 Active + Inactive），用于后台列表展示
     * @return array [templateId => count]
     */
    public function getTotalQuestionCountByTemplate() {
        $sql = "SELECT templateId, COUNT(*) AS cnt FROM {$this->table} GROUP BY templateId";
        $rows = $this->query($sql);
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['templateId']] = (int)$r['cnt'];
        }
        return $map;
    }

    /**
     * 获取选择题类型的问题（用于条件规则）
     */
    public function getChoiceTypeQuestions($templateId) {
        $sql = "SELECT * FROM kycQuestions
                WHERE templateId = :templateId
                AND questionType IN ('single_choice', 'multiple_choice', 'yes_no')
                AND isActive = 1
                ORDER BY displayOrder";

        return $this->query($sql, ['templateId' => $templateId]);
    }
}
