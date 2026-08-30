<?php
/**
 * Withdrawal Verification Question Option Model
 * 对应表: withdrawalVerificationQuestionOptions
 */

require_once __DIR__ . '/BaseModel.php';

class WithdrawalVerificationQuestionOption extends BaseModel {
    protected $table = 'withdrawalVerificationQuestionOptions';
    protected $primaryKey = 'id';
    protected $fillable = [
        'questionId',
        'optionLabel',
        'optionValue',
        'displayOrder',
        'isActive'
    ];

    public function getQuestionOptions($questionId, $activeOnly = true) {
        $conditions = ['questionId' => $questionId];

        if ($activeOnly) {
            $conditions['isActive'] = 1;
        }

        return $this->findAll($conditions, 'displayOrder');
    }

    public function formatOptionForResponse(array $option) {
        $value = $this->normalizeOptionValue($option);
        $label = $this->normalizeOptionLabel($option, $value);

        return [
            'id' => isset($option['id']) ? (int)$option['id'] : null,
            'optionLabel' => $label,
            'optionValue' => $value,
            'displayOrder' => isset($option['displayOrder']) ? (int)$option['displayOrder'] : null
        ];
    }

    public function formatOptionsForResponse(array $options) {
        $formatted = [];
        foreach ($options as $option) {
            if (!is_array($option)) {
                continue;
            }

            $value = $this->normalizeOptionValue($option);
            if ($value === '') {
                continue;
            }

            $formatted[] = $this->formatOptionForResponse($option);
        }

        return $formatted;
    }

    public function createBatch($questionId, $options) {
        $results = [];
        $order = 1;

        foreach ($options as $option) {
            $optionValue = $this->normalizeOptionValue($option);
            if ($optionValue === '') {
                continue;
            }

            $optionLabel = $this->normalizeOptionLabel($option, $optionValue);

            $results[] = $this->create([
                'questionId' => $questionId,
                'optionLabel' => $optionLabel,
                'optionValue' => $optionValue,
                'displayOrder' => $order++,
                'isActive' => 1
            ]);
        }

        return $results;
    }

    public function updateQuestionOptions($questionId, $options) {
        $sql = "DELETE FROM {$this->table} WHERE questionId = :questionId";
        $this->db->query($sql, ['questionId' => $questionId]);

        return $this->createBatch($questionId, $options);
    }

    private function normalizeOptionValue($option) {
        if (!is_array($option)) {
            return trim((string)$option);
        }

        $candidates = [
            $option['optionValue'] ?? null,
            $option['value'] ?? null,
            $option['optionLabel'] ?? null,
            $option['label'] ?? null,
            $option['labal'] ?? null
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string)$candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function normalizeOptionLabel($option, $fallbackValue = '') {
        if (!is_array($option)) {
            $label = trim((string)$option);
            return $label !== '' ? $label : trim((string)$fallbackValue);
        }

        $candidates = [
            $option['optionLabel'] ?? null,
            $option['label'] ?? null,
            $option['labal'] ?? null,
            $option['optionValue'] ?? null,
            $option['value'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $label = trim((string)$candidate);
            if ($label !== '') {
                return $label;
            }
        }

        return trim((string)$fallbackValue);
    }
}
