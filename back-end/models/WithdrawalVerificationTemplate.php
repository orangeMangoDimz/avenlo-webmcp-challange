<?php
/**
 * Withdrawal Verification Template Model
 * 对应表: withdrawalVerificationTemplates
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/WithdrawalVerificationQuestionOption.php';

class WithdrawalVerificationTemplate extends BaseModel {
    private $questionOptionModel;
    protected $table = 'withdrawalVerificationTemplates';
    protected $primaryKey = 'id';
    protected $fillable = [
        'gatewaySettingId',
        'templateName',
        'description',
        'status',
        'isAutoApproveEnabled',
        'requireDocumentSignature',
        'totalQuestions',
        'totalRules',
        'displayOrder',
        'createdBy',
        'updatedBy',
        'deletedAt'
    ];

    const NOT_DELETED_CONDITION = 'deletedAt IS NULL';

    public function __construct() {
        parent::__construct();
        $this->questionOptionModel = new WithdrawalVerificationQuestionOption();
    }

    protected function notDeletedCondition($alias = null) {
        $prefix = $alias ? $alias . '.' : '';
        return "{$prefix}deletedAt IS NULL";
    }

    public function findById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id AND {$this->notDeletedCondition()} LIMIT 1";
        $result = $this->db->fetchOne($sql, ['id' => $id]);
        return $result ? $this->hideFields($result) : null;
    }

    public function findOne($conditions) {
        if (!array_key_exists('deletedAt', $conditions)) {
            $conditions['deletedAt'] = null;
        }
        return parent::findOne($conditions);
    }

    public function findAll($conditions = [], $orderBy = null, $limit = null, $offset = null) {
        if (!array_key_exists('deletedAt', $conditions)) {
            $conditions['deletedAt'] = null;
        }
        return parent::findAll($conditions, $orderBy, $limit, $offset);
    }

    public function update($id, $data) {
        $filteredData = $this->filterFillable($data);
        if (empty($filteredData)) {
            return false;
        }

        return $this->db->update(
            $this->table,
            $filteredData,
            "{$this->primaryKey} = :id AND {$this->notDeletedCondition()}",
            ['id' => $id]
        );
    }

    public function getTemplatesSummary($filters = []) {
        $sql = "SELECT
                    t.id AS templateId,
                    t.gatewaySettingId,
                    pgs.gatewayKey,
                    pgs.gatewayName,
                    pgs.iconClass,
                    pgs.isDepositEnabled,
                    pgs.isWithdrawalEnabled,
                    t.templateName,
                    t.description,
                    t.status,
                    t.isAutoApproveEnabled,
                    t.requireDocumentSignature,
                    t.totalQuestions,
                    t.totalRules,
                    t.displayOrder,
                    t.createdAt,
                    t.updatedAt,
                    (SELECT COUNT(*) FROM withdrawalVerificationQuestionCategories c WHERE c.templateId = t.id AND c.isActive = 1) AS activeCategoryCount,
                    (SELECT COUNT(*) FROM clientWithdrawalVerificationSubmissions s WHERE s.templateId = t.id) AS totalSubmissions,
                    (SELECT COUNT(*) FROM clientWithdrawalVerificationSubmissions s WHERE s.templateId = t.id AND s.submissionStatus = 'approved') AS approvedSubmissions
                FROM withdrawalVerificationTemplates t
                INNER JOIN paymentGatewaySettings pgs ON pgs.id = t.gatewaySettingId";
        $params = [];
        $where = [
            $this->notDeletedCondition('t'),
            $this->notDeletedCondition('pgs')
        ];

        if (isset($filters['status'])) {
            $where[] = "t.status = :status";
            $params['status'] = $filters['status'];
        }

        if (isset($filters['gatewaySettingId'])) {
            $where[] = "t.gatewaySettingId = :gatewaySettingId";
            $params['gatewaySettingId'] = $filters['gatewaySettingId'];
        }

        if (isset($filters['gatewayKey'])) {
            $where[] = "pgs.gatewayKey = :gatewayKey";
            $params['gatewayKey'] = $filters['gatewayKey'];
        }

        $sql .= " WHERE " . implode(' AND ', $where);

        $sql .= " ORDER BY pgs.gatewayName, t.displayOrder, t.id";

        return $this->query($sql, $params);
    }

    public function getTemplateSummary($templateId) {
        $sql = "SELECT
                    t.id AS templateId,
                    t.gatewaySettingId,
                    pgs.gatewayKey,
                    pgs.gatewayName,
                    pgs.iconClass,
                    pgs.isDepositEnabled,
                    pgs.isWithdrawalEnabled,
                    t.templateName,
                    t.description,
                    t.status,
                    t.isAutoApproveEnabled,
                    t.requireDocumentSignature,
                    t.totalQuestions,
                    t.totalRules,
                    t.displayOrder,
                    t.createdAt,
                    t.updatedAt,
                    (SELECT COUNT(*) FROM withdrawalVerificationQuestionCategories c WHERE c.templateId = t.id AND c.isActive = 1) AS activeCategoryCount,
                    (SELECT COUNT(*) FROM clientWithdrawalVerificationSubmissions s WHERE s.templateId = t.id) AS totalSubmissions,
                    (SELECT COUNT(*) FROM clientWithdrawalVerificationSubmissions s WHERE s.templateId = t.id AND s.submissionStatus = 'approved') AS approvedSubmissions
                FROM withdrawalVerificationTemplates t
                INNER JOIN paymentGatewaySettings pgs ON pgs.id = t.gatewaySettingId
                WHERE t.id = :id
                  AND {$this->notDeletedCondition('t')}
                  AND {$this->notDeletedCondition('pgs')}";
        return $this->queryOne($sql, ['id' => $templateId]);
    }

    public function getActiveTemplates($gatewaySettingId = null) {
        $sql = "SELECT
                    t.id AS templateId,
                    t.gatewaySettingId,
                    pgs.gatewayKey,
                    pgs.gatewayName,
                    pgs.iconClass,
                    pgs.isDepositEnabled,
                    pgs.isWithdrawalEnabled,
                    t.templateName,
                    t.description,
                    t.requireDocumentSignature,
                    t.isAutoApproveEnabled,
                    t.totalQuestions,
                    t.totalRules,
                    t.displayOrder
                FROM withdrawalVerificationTemplates t
                INNER JOIN paymentGatewaySettings pgs ON pgs.id = t.gatewaySettingId
                WHERE t.status = 'active'
                  AND {$this->notDeletedCondition('t')}
                  AND {$this->notDeletedCondition('pgs')}";
        $params = [];

        if ($gatewaySettingId !== null) {
            $sql .= " AND t.gatewaySettingId = :gatewaySettingId";
            $params['gatewaySettingId'] = $gatewaySettingId;
        }

        $sql .= " ORDER BY pgs.gatewayName, t.displayOrder, t.id";

        return $this->query($sql, $params);
    }

    public function getTemplateDetails($templateId) {
        $template = $this->findById($templateId);

        if (!$template) {
            return null;
        }

        $sql = "SELECT * FROM withdrawalVerificationQuestionCategories
                WHERE templateId = :id AND isActive = 1
                ORDER BY displayOrder";
        $categories = $this->query($sql, ['id' => $templateId]);

        foreach ($categories as &$category) {
            $sql = "SELECT *
                    FROM withdrawalVerificationQuestions
                    WHERE templateId = :templateId AND categoryId = :categoryId AND isActive = 1
                    ORDER BY displayOrder";
            $category['questions'] = $this->query($sql, [
                'templateId' => $templateId,
                'categoryId' => $category['id']
            ]);

            foreach ($category['questions'] as &$question) {
                if (in_array($question['questionType'] ?? null, ['single_choice', 'multiple_choice'], true)) {
                    $question['options'] = $this->questionOptionModel->formatOptionsForResponse(
                        $this->questionOptionModel->getQuestionOptions($question['id'], true)
                    );
                } else {
                    $question['options'] = [];
                }

                $fileTypes = $this->query(
                    "SELECT documentType
                     FROM withdrawalVerificationQuestionDocumentTypes
                     WHERE questionId = :questionId
                     ORDER BY displayOrder",
                    ['questionId' => $question['id']]
                );
                $question['fileDocumentTypes'] = array_values(array_map(function ($item) {
                    return $item['documentType'];
                }, $fileTypes));
            }
            unset($question);
        }
        unset($category);

        $template['categories'] = $categories;

        $sql = "SELECT
                    r.*,
                    tq.questionText AS triggerQuestionText,
                    tq.questionNumber AS triggerQuestionNumber,
                    COALESCE(triggerOption.optionLabel, triggerOption.optionValue, r.triggerAnswer) AS triggerAnswerLabel,
                    target.questionText AS targetQuestionText,
                    target.questionNumber AS targetQuestionNumber
                FROM withdrawalVerificationConditionalRules r
                INNER JOIN withdrawalVerificationQuestions tq ON r.triggerQuestionId = tq.id
                LEFT JOIN withdrawalVerificationQuestionOptions triggerOption
                    ON triggerOption.questionId = r.triggerQuestionId
                    AND triggerOption.optionValue = r.triggerAnswer
                LEFT JOIN withdrawalVerificationQuestions target ON r.targetQuestionId = target.id
                WHERE r.templateId = :id AND r.isActive = 1
                ORDER BY r.displayOrder";
        $template['rules'] = $this->query($sql, ['id' => $templateId]);

        $sql = "SELECT * FROM withdrawalVerificationTemplateDocuments
                WHERE templateId = :id AND isActive = 1
                ORDER BY displayOrder";
        $template['documents'] = $this->query($sql, ['id' => $templateId]);

        return $template;
    }

    public function createTemplate($data) {
        $templateId = $this->create($data);
        return $this->getTemplateDetails($templateId);
    }

    public function updateStatus($templateId, $status) {
        return $this->update($templateId, ['status' => $status]);
    }

    public function syncCounters($templateId) {
        $questionCount = $this->queryOne(
            "SELECT COUNT(*) AS total FROM withdrawalVerificationQuestions WHERE templateId = :templateId",
            ['templateId' => $templateId]
        );
        $ruleCount = $this->queryOne(
            "SELECT COUNT(*) AS total FROM withdrawalVerificationConditionalRules WHERE templateId = :templateId",
            ['templateId' => $templateId]
        );

        return $this->update($templateId, [
            'totalQuestions' => (int)($questionCount['total'] ?? 0),
            'totalRules' => (int)($ruleCount['total'] ?? 0)
        ]);
    }

    public function cloneTemplate($templateId, $newName) {
        $template = $this->findById($templateId);

        if (!$template) {
            return false;
        }

        unset($template['id'], $template['createdAt'], $template['updatedAt']);

        $template['templateName'] = $newName;
        $template['status'] = 'draft';
        $template['totalQuestions'] = 0;
        $template['totalRules'] = 0;

        return $this->create($template);
    }

    public function softDeleteByGatewaySettingId($gatewaySettingId, $deletedAt = null, $updatedBy = null) {
        $deletedAt = $deletedAt ?: date('Y-m-d H:i:s');
        $sql = "UPDATE {$this->table}
                SET deletedAt = :deletedAt,
                    updatedBy = :updatedBy
                WHERE gatewaySettingId = :gatewaySettingId
                  AND {$this->notDeletedCondition()}";

        $this->db->query($sql, [
            'deletedAt' => $deletedAt,
            'updatedBy' => $updatedBy,
            'gatewaySettingId' => (int)$gatewaySettingId
        ]);

        return true;
    }
}
