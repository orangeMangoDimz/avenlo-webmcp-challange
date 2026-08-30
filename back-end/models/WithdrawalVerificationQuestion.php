<?php
/**
 * Withdrawal Verification Question Model
 * 对应表: withdrawalVerificationQuestions
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/WithdrawalVerificationQuestionOption.php';

class WithdrawalVerificationQuestion extends BaseModel {
    private $optionModel;
    public const ALLOWED_SCOPES = [
        'name',
        'dob',
        'phone',
        'email',
        'wallet_address',
        'chain',
        'first_name',
        'last_name',
        'middle_name',
        'address',
        'address1',
        'address2',
        'city',
        'state',
        'postcode',
        'country',
        'address_type',
        'bank_name',
        'bank_code',
        'bank_province',
        'bank_city',
        'bank_branch',
        'customer_name',
        'customer_lastname',
        'account_number',
        'account_name',
        'way_code'
    ];

    protected $table = 'withdrawalVerificationQuestions';
    protected $primaryKey = 'id';
    protected $fillable = [
        'templateId',
        'categoryId',
        'questionNumber',
        'questionText',
        'helpText',
        'questionType',
        'scope',
        'validationRules',
        'isRequired',
        'isActive',
        'isLocked',
        'displayOrder',
        'metadata',
        'createdBy',
        'updatedBy'
    ];

    public function __construct() {
        parent::__construct();
        $this->optionModel = new WithdrawalVerificationQuestionOption();
    }

    public function getTemplateQuestions($templateId, $activeOnly = true) {
        $sql = "SELECT
                    q.id,
                    q.templateId,
                    q.categoryId,
                    c.categoryName,
                    q.questionNumber,
                    q.questionText,
                    q.helpText,
                    q.questionType,
                    q.scope,
                    q.validationRules,
                    q.isRequired,
                    q.isActive,
                    q.isLocked,
                    q.displayOrder,
                    q.metadata,
                    q.createdAt,
                    q.updatedAt,
                    q.createdBy,
                    q.updatedBy,
                    (
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id', o.id,
                                'optionLabel', COALESCE(o.optionLabel, o.optionValue),
                                'optionValue', o.optionValue,
                                'displayOrder', o.displayOrder
                            )
                        )
                        FROM withdrawalVerificationQuestionOptions o
                        WHERE o.questionId = q.id AND o.isActive = 1
                    ) AS options,
                    (
                        SELECT JSON_ARRAYAGG(d.documentType)
                        FROM withdrawalVerificationQuestionDocumentTypes d
                        WHERE d.questionId = q.id
                    ) AS fileDocumentTypes
                FROM withdrawalVerificationQuestions q
                INNER JOIN withdrawalVerificationQuestionCategories c ON c.id = q.categoryId
                WHERE q.templateId = :templateId";

        if ($activeOnly) {
            $sql .= " AND q.isActive = 1";
        }

        $sql .= " ORDER BY q.displayOrder";

        $questions = $this->query($sql, ['templateId' => $templateId]);

        foreach ($questions as &$question) {
            if (in_array($question['questionType'] ?? null, ['single_choice', 'multiple_choice'], true)) {
                $decodedOptions = json_decode((string)($question['options'] ?? '[]'), true);
                $question['options'] = is_array($decodedOptions) ? $decodedOptions : [];
            } else {
                $question['options'] = [];
            }

            $decodedFileTypes = json_decode((string)($question['fileDocumentTypes'] ?? '[]'), true);
            $question['fileDocumentTypes'] = is_array($decodedFileTypes) ? $decodedFileTypes : [];
        }
        unset($question);

        return $questions;
    }

    public function getCategoryQuestions($categoryId, $activeOnly = true) {
        $conditions = ['categoryId' => $categoryId];

        if ($activeOnly) {
            $conditions['isActive'] = 1;
        }

        return $this->findAll($conditions, 'displayOrder');
    }

    public function getQuestionDetails($questionId) {
        $question = $this->findById($questionId);

        if (!$question) {
            return null;
        }

        if (in_array($question['questionType'], ['single_choice', 'multiple_choice'], true)) {
            $sql = "SELECT * FROM withdrawalVerificationQuestionOptions
                    WHERE questionId = :id AND isActive = 1
                    ORDER BY displayOrder";
            $question['options'] = $this->optionModel->formatOptionsForResponse(
                $this->query($sql, ['id' => $questionId])
            );
        }

        if ($question['questionType'] === 'file_upload') {
            $sql = "SELECT * FROM withdrawalVerificationQuestionDocumentTypes
                    WHERE questionId = :id
                    ORDER BY displayOrder";
            $question['documentTypes'] = $this->query($sql, ['id' => $questionId]);
        }

        return $question;
    }

    public function createQuestion($data) {
        $sql = "SELECT COALESCE(MAX(questionNumber), 0) AS maxNumber
                FROM withdrawalVerificationQuestions
                WHERE templateId = :templateId";
        $result = $this->queryOne($sql, ['templateId' => $data['templateId']]);

        $data['questionNumber'] = ($result['maxNumber'] ?? 0) + 1;
        $data['displayOrder'] = $data['displayOrder'] ?? $data['questionNumber'];
        if (!isset($data['isLocked'])) {
            $data['isLocked'] = 0;
        }
        if (array_key_exists('scope', $data) && $data['scope'] !== null && $data['scope'] !== '') {
            $data['scope'] = $this->normalizeScope($data['scope']);
        } else {
            $data['scope'] = null;
        }

        return $this->create($data);
    }

    public function updateQuestionOrder($questionId, $newOrder) {
        return $this->update($questionId, ['displayOrder' => $newOrder]);
    }

    public function isLocked($questionId) {
        $question = $this->findById($questionId);
        return $question ? (int)($question['isLocked'] ?? 0) === 1 : false;
    }

    public function updateIfUnlocked($questionId, $data) {
        if ($this->isLocked($questionId)) {
            return false;
        }

        unset($data['isLocked']);
        if (array_key_exists('scope', $data)) {
            if ($data['scope'] === null || $data['scope'] === '') {
                $data['scope'] = null;
            } else {
                $data['scope'] = $this->normalizeScope($data['scope']);
            }
        }
        return $this->update($questionId, $data);
    }

    public function deleteIfUnlocked($questionId) {
        if ($this->isLocked($questionId)) {
            return false;
        }

        return $this->delete($questionId);
    }

    public function updateBatchOrder($orders) {
        $updated = 0;
        foreach ($orders as $questionId => $order) {
            if ($this->updateQuestionOrder($questionId, $order)) {
                $updated++;
            }
        }
        return $updated;
    }

    public function getChoiceTypeQuestions($templateId) {
        $sql = "SELECT * FROM withdrawalVerificationQuestions
                WHERE templateId = :templateId
                AND questionType IN ('single_choice', 'multiple_choice', 'yes_no')
                AND isActive = 1
                ORDER BY displayOrder";

        return $this->query($sql, ['templateId' => $templateId]);
    }

    public function isValidScope($scope) {
        if ($scope === null || $scope === '') {
            return true;
        }

        return in_array($scope, self::ALLOWED_SCOPES, true);
    }

    private function normalizeScope($scope) {
        $scope = trim((string)$scope);
        if (!$this->isValidScope($scope)) {
            throw new InvalidArgumentException('Invalid scope value');
        }

        return $scope;
    }
}
