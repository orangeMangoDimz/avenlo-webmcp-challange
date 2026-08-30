<?php
/**
 * KYC Template Model
 * 对应表: kycTemplates
 */

require_once __DIR__ . '/BaseModel.php';

class KycTemplate extends BaseModel {
    protected $table = 'kycTemplates';
    protected $primaryKey = 'id';
    protected $fillable = [
        'templateName',
        'description',
        'status',
        'isThirdPartyEnabled',
        'thirdPartyProvider',
        'externalTemplateId',
        'isAutoApproveEnabled',
        'requireDocumentSignature',
        'displayOrder',
        'createdBy',
        'updatedBy'
    ];

    /**
     * 获取模板汇总（使用视图）
     */
    public function getTemplatesSummary($filters = []) {
        $sql = "SELECT * FROM vw_kyc_template_summary";
        $params = [];
        $where = [];

        if (isset($filters['status'])) {
            $where[] = "status = :status";
            $params['status'] = $filters['status'];
        }

        if (isset($filters['isThirdPartyEnabled'])) {
            $where[] = "isThirdPartyEnabled = :isThirdPartyEnabled";
            $params['isThirdPartyEnabled'] = $filters['isThirdPartyEnabled'];
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY displayOrder, templateId";

        return $this->query($sql, $params);
    }

    /**
     * 获取单个模板汇总
     */
    public function getTemplateSummary($templateId) {
        $sql = "SELECT * FROM vw_kyc_template_summary WHERE templateId = :id";
        return $this->queryOne($sql, ['id' => $templateId]);
    }

    /**
     * 获取活跃模板（使用视图）
     */
    public function getActiveTemplates() {
        $sql = "SELECT * FROM vw_kyc_active_templates";
        return $this->query($sql);
    }

    /**
     * 获取模板的完整信息（包含国家、分类、问题、规则、文档）
     */
    public function getTemplateDetails($templateId) {
        $template = $this->findById($templateId);

        if (!$template) {
            return null;
        }

        // 获取适用国家
        $sql = "SELECT * FROM kycTemplateCountries WHERE templateId = :id ORDER BY countryName";
        $template['countries'] = $this->query($sql, ['id' => $templateId]);

        // 获取问题分类和问题
        $sql = "SELECT * FROM kycQuestionCategories WHERE templateId = :id AND isActive = 1 ORDER BY displayOrder";
        $categories = $this->query($sql, ['id' => $templateId]);

        foreach ($categories as &$category) {
            // 获取该分类下的问题
            $sql = "SELECT * FROM vw_kyc_questions_full
                    WHERE templateId = :tid AND categoryId = :cid AND isActive = 1
                    ORDER BY displayOrder";
            $questions = $this->query($sql, [
                'tid' => $templateId,
                'cid' => $category['id']
            ]);

            // 为每个问题获取选项和文档类型
            foreach ($questions as &$question) {
                // 获取选项（如果是选择题）
                if (in_array($question['questionType'], ['single_choice', 'multiple_choice'])) {
                    $sql = "SELECT * FROM kycQuestionOptions
                            WHERE questionId = :qid AND isActive = 1
                            ORDER BY displayOrder";
                    $question['options'] = $this->query($sql, ['qid' => $question['questionId']]);
                }

                // 获取文档类型（如果是文件上传）
                if ($question['questionType'] === 'file_upload') {
                    $sql = "SELECT * FROM kycQuestionDocumentTypes
                            WHERE questionId = :qid
                            ORDER BY displayOrder";
                    $question['documentTypes'] = $this->query($sql, ['qid' => $question['questionId']]);
                }
            }

            $category['questions'] = $questions;
        }

        $template['categories'] = $categories;

        // 获取条件规则
        $sql = "SELECT
                    r.*,
                    tq.questionText AS triggerQuestionText,
                    tq.questionNumber AS triggerQuestionNumber,
                    target.questionText AS targetQuestionText,
                    target.questionNumber AS targetQuestionNumber
                FROM kycConditionalRules r
                INNER JOIN kycQuestions tq ON r.triggerQuestionId = tq.id
                LEFT JOIN kycQuestions target ON r.targetQuestionId = target.id
                WHERE r.templateId = :id AND r.isActive = 1
                ORDER BY r.displayOrder";
        $template['rules'] = $this->query($sql, ['id' => $templateId]);

        // 获取文档要求
        $sql = "SELECT * FROM kycTemplateDocuments
                WHERE templateId = :id AND isActive = 1
                ORDER BY displayOrder";
        $template['documents'] = $this->query($sql, ['id' => $templateId]);

        return $template;
    }

    /**
     * 创建模板并返回完整信息
     */
    public function createTemplate($data) {
        $templateId = $this->create($data);
        return $this->getTemplateDetails($templateId);
    }

    /**
     * 更新模板状态
     */
    public function updateStatus($templateId, $status) {
        return $this->update($templateId, ['status' => $status]);
    }

    /**
     * 克隆模板
     */
    public function cloneTemplate($templateId, $newName) {
        $template = $this->findById($templateId);

        if (!$template) {
            return false;
        }

        unset($template['id']);
        unset($template['createdAt']);
        unset($template['updatedAt']);

        $template['templateName'] = $newName;
        $template['status'] = 'draft';
        $template['totalQuestions'] = 0;
        $template['totalRules'] = 0;

        return $this->create($template);
    }
}
