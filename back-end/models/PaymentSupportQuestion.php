<?php
/**
 * Payment Support Question Model
 */

require_once __DIR__ . '/BaseModel.php';

class PaymentSupportQuestion extends BaseModel {
    public const SCOPE_WITHDRAW = 'withdraw';
    public const SCOPE_DEPOSIT = 'deposit';

    protected $table = 'paymentSupportQuestions';
    protected $primaryKey = 'id';
    protected $fillable = [
        'paymentGatewayId',
        'name',
        'hintText',
        'questionType',
        'validationRules',
        'options',
        'scope',
        'isLocked',
        'isActive',
        'createdAt',
        'updatedAt',
        'updatedBy'
    ];

    public function getGatewayQuestions($paymentGatewayId, $scope = self::SCOPE_WITHDRAW, $activeOnly = true) {
        $scope = $this->normalizeScope($scope);
        $sql = "SELECT id, paymentGatewayId, name, hintText, questionType, validationRules, options, scope, isLocked, isActive, createdAt, updatedAt, updatedBy
                FROM {$this->table}
                WHERE paymentGatewayId = :paymentGatewayId
                  AND scope = :scope";

        if ($activeOnly) {
            $sql .= " AND isActive = 1";
        }

        $sql .= " ORDER BY id ASC";

        $questions = $this->query($sql, [
            'paymentGatewayId' => $paymentGatewayId,
            'scope' => $scope
        ]);

        return $this->hydrateQuestions($questions);
    }

    public function getAdminQuestions($paymentGatewayId = null, $scope = null, $activeOnly = false) {
        $sql = "SELECT id, paymentGatewayId, name, hintText, questionType, validationRules, options, scope, isLocked, isActive, createdAt, updatedAt, updatedBy
                FROM {$this->table}
                WHERE 1 = 1";
        $params = [];

        if ($paymentGatewayId !== null) {
            $sql .= " AND paymentGatewayId = :paymentGatewayId";
            $params['paymentGatewayId'] = (int)$paymentGatewayId;
        }

        if ($scope !== null && $scope !== '') {
            $sql .= " AND scope = :scope";
            $params['scope'] = $this->normalizeScope($scope);
        }

        if ($activeOnly) {
            $sql .= " AND isActive = 1";
        }

        $sql .= " ORDER BY paymentGatewayId ASC, scope ASC, id ASC";

        return $this->hydrateQuestions($this->query($sql, $params));
    }

    public function getQuestionById($id) {
        $question = $this->findById($id);
        return $question ? $this->hydrateQuestion($question) : null;
    }

    public function createQuestion(array $data) {
        $payload = $this->prepareWritePayload($data, true);
        return $this->create($payload);
    }

    public function updateQuestion($id, array $data) {
        $payload = $this->prepareWritePayload($data, false);
        if (empty($payload)) {
            return 0;
        }

        return $this->update($id, $payload);
    }

    public function normalizeOptionsPayload($options) {
        if (!is_array($options)) {
            return [];
        }

        $normalized = [];
        foreach ($options as $option) {
            if (is_array($option)) {
                $hasExplicitValue = array_key_exists('value', $option) || array_key_exists('optionValue', $option);
                $rawValue = $hasExplicitValue
                    ? ($option['value'] ?? $option['optionValue'] ?? '')
                    : $this->extractOptionValue($option);
                $value = trim((string)$rawValue);
                $label = trim((string)($option['label'] ?? $option['labal'] ?? ''));

                if ($label === '' && $value === '') {
                    continue;
                }

                $normalized[] = [
                    'label' => $label !== '' ? $label : $this->extractOptionLabel($option, $value),
                    'value' => $value,
                    'isEnabled' => $this->normalizeOptionEnabledFlag($option)
                ];
                continue;
            }

            $value = trim((string)$option);
            if ($value === '') {
                continue;
            }

            $normalized[] = [
                'label' => $value,
                'value' => $value,
                'isEnabled' => true
            ];
        }

        return array_values($normalized);
    }

    public function extractOptionValues($options) {
        $normalized = $this->normalizeOptionsPayload($options);
        $values = [];

        foreach ($normalized as $option) {
            $value = trim((string)($option['value'] ?? ''));
            if ($value === '') {
                continue;
            }

            $values[] = $value;
        }

        return array_values(array_unique($values));
    }

    public function extractEnabledOptionValues($options) {
        $normalized = $this->normalizeOptionsPayload($options);
        $values = [];

        foreach ($normalized as $option) {
            if (empty($option['isEnabled'])) {
                continue;
            }

            $value = trim((string)($option['value'] ?? ''));
            if ($value === '') {
                continue;
            }

            $values[] = $value;
        }

        return array_values(array_unique($values));
    }

    public function filterEnabledOptions($options) {
        $normalized = $this->normalizeOptionsPayload($options);
        $filtered = [];

        foreach ($normalized as $option) {
            if (empty($option['isEnabled'])) {
                continue;
            }

            $filtered[] = [
                'label' => $option['label'],
                'value' => $option['value']
            ];
        }

        return array_values($filtered);
    }

    public function syncLockedScopeQuestions($paymentGatewayId, $scope, array $specs): array {
        $paymentGatewayId = (int)$paymentGatewayId;
        $scope = $this->normalizeScope($scope);
        if ($paymentGatewayId <= 0 || $scope === '') {
            return [];
        }

        $desiredByName = [];
        foreach ($specs as $spec) {
            if (!is_array($spec)) {
                continue;
            }
            $name = trim((string)($spec['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $desiredByName[$name] = $spec;
        }

        $existing = $this->getGatewayQuestions($paymentGatewayId, $scope, false);
        $existingByName = [];
        foreach ($existing as $question) {
            $existingByName[(string)($question['name'] ?? '')] = $question;
        }

        $desiredNames = array_keys($desiredByName);
        $existingActiveNames = [];
        foreach ($existing as $question) {
            if ((int)($question['isActive'] ?? 0) !== 1) {
                continue;
            }
            $existingActiveNames[] = (string)($question['name'] ?? '');
        }
        sort($desiredNames);
        sort($existingActiveNames);

        $alreadySynced = $desiredNames === $existingActiveNames;
        if ($alreadySynced) {
            foreach ($desiredByName as $name => $spec) {
                $current = $existingByName[$name] ?? null;
                if (!$current) {
                    $alreadySynced = false;
                    break;
                }
                if (trim((string)($current['validationRules'] ?? '')) !== trim((string)($spec['validationRules'] ?? ''))
                    || trim((string)($current['hintText'] ?? '')) !== trim((string)($spec['hintText'] ?? ''))
                    || trim((string)($current['questionType'] ?? '')) !== trim((string)($spec['questionType'] ?? 'text'))
                ) {
                    $alreadySynced = false;
                    break;
                }
            }
        }

        if ($alreadySynced) {
            return $this->getGatewayQuestions($paymentGatewayId, $scope, true);
        }

        foreach ($desiredByName as $name => $spec) {
            $payload = [
                'paymentGatewayId' => $paymentGatewayId,
                'name' => $name,
                'hintText' => $spec['hintText'] ?? '',
                'questionType' => $spec['questionType'] ?? 'text',
                'validationRules' => $spec['validationRules'] ?? '',
                'options' => $spec['options'] ?? null,
                'scope' => $scope,
                'isLocked' => 1,
                'isActive' => 1,
                'updatedBy' => null,
            ];
            if (isset($existingByName[$name])) {
                $this->updateQuestion((int)$existingByName[$name]['id'], $payload);
            } else {
                $this->createQuestion($payload);
            }
        }

        foreach ($existing as $question) {
            $name = (string)($question['name'] ?? '');
            if ($name !== '' && !isset($desiredByName[$name])) {
                $this->delete((int)$question['id']);
            }
        }

        return $this->getGatewayQuestions($paymentGatewayId, $scope, true);
    }

    public function filterQuestionsForClient(array $questions) {
        foreach ($questions as &$question) {
            if (!is_array($question['options'] ?? null) || empty($question['options'])) {
                continue;
            }

            $question['options'] = $this->filterEnabledOptions($question['options']);
        }
        unset($question);

        return $questions;
    }

    public function toggleOptionEnabled($questionId, $optionValue, $isEnabled) {
        $question = $this->getQuestionById($questionId);
        if (!$question) {
            return null;
        }

        $targetValue = trim((string)$optionValue);
        $options = $this->normalizeOptionsPayload($question['options'] ?? []);
        $found = false;

        foreach ($options as &$option) {
            if (trim((string)($option['value'] ?? '')) !== $targetValue) {
                continue;
            }

            $option['isEnabled'] = (bool)$isEnabled;
            $found = true;
            break;
        }
        unset($option);

        if (!$found) {
            return false;
        }

        $this->updateQuestion($questionId, [
            'options' => $options
        ]);

        return $this->getQuestionById($questionId);
    }

    public function normalizeScope($scope) {
        if ($scope === true || $scope === 1 || $scope === '1') {
            return self::SCOPE_WITHDRAW;
        }

        if ($scope === false || $scope === 0 || $scope === '0') {
            return self::SCOPE_DEPOSIT;
        }

        $normalized = strtolower(trim((string)$scope));
        if (in_array($normalized, ['withdraw', 'withdrawal'], true)) {
            return self::SCOPE_WITHDRAW;
        }

        if ($normalized === 'deposit') {
            return self::SCOPE_DEPOSIT;
        }

        return $normalized;
    }

    private function prepareWritePayload(array $data, $isCreate) {
        $payload = $data;

        if (array_key_exists('scope', $payload)) {
            $payload['scope'] = $this->normalizeScope($payload['scope']);
        }

        if (array_key_exists('options', $payload)) {
            if (is_array($payload['options'])) {
                $normalizedOptions = $this->normalizeOptionsPayload($payload['options']);
                $payload['options'] = empty($normalizedOptions)
                    ? null
                    : json_encode($normalizedOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif ($payload['options'] === '' || $payload['options'] === null) {
                $payload['options'] = null;
            }
        }

        $now = date('Y-m-d H:i:s');
        if ($isCreate && !isset($payload['createdAt'])) {
            $payload['createdAt'] = $now;
        }

        if (!isset($payload['updatedAt'])) {
            $payload['updatedAt'] = $now;
        }

        return $this->filterFillable($payload);
    }

    private function hydrateQuestions(array $questions) {
        foreach ($questions as &$question) {
            $question = $this->hydrateQuestion($question);
        }
        unset($question);

        return $questions;
    }

    private function hydrateQuestion(array $question) {
        $question['scope'] = $this->normalizeScope($question['scope'] ?? null);
        $question['options'] = $question['options']
            ? $this->normalizeOptionsPayload(json_decode($question['options'], true) ?: [])
            : [];

        return $question;
    }

    private function extractOptionValue(array $option) {
        $candidates = [
            $option['value'] ?? null,
            $option['optionValue'] ?? null,
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

    private function extractOptionLabel(array $option, $fallbackValue) {
        $candidates = [
            $option['label'] ?? null,
            $option['labal'] ?? null,
            $option['value'] ?? null,
            $option['optionValue'] ?? null
        ];

        foreach ($candidates as $candidate) {
            $label = trim((string)$candidate);
            if ($label !== '') {
                return $label;
            }
        }

        return $fallbackValue;
    }

    private function normalizeOptionEnabledFlag(array $option) {
        if (!array_key_exists('isEnabled', $option) && !array_key_exists('enabled', $option)) {
            return true;
        }

        $raw = array_key_exists('isEnabled', $option) ? $option['isEnabled'] : $option['enabled'];
        if (is_bool($raw)) {
            return $raw;
        }

        if (is_int($raw) || is_float($raw)) {
            return (int)$raw === 1;
        }

        $normalized = strtolower(trim((string)$raw));
        if (in_array($normalized, ['0', 'false', 'no', 'off', 'disabled'], true)) {
            return false;
        }

        return true;
    }
}
