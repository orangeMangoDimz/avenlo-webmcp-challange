<?php
/**
 * 数据验证类
 */

class Validator {
    private $data = [];
    private $rules = [];
    private $errors = [];

    public function __construct($data, $rules) {
        $this->data = $data;
        $this->rules = $rules;
    }

    /**
     * 执行验证
     */
    public function validate() {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    /**
     * 快速验证（静态方法）
     * 返回错误数组，如果没有错误则返回空数组
     */
    public static function validateData($data, $rules) {
        $validator = new self($data, $rules);
        $validator->validate();
        return $validator->getErrors();
    }

    /**
     * 执行验证
     */
    public function check() {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    /**
     * 应用单个规则
     */
    private function applyRule($field, $value, $rule) {
        // 解析规则和参数
        $parts = explode(':', $rule);
        $ruleName = $parts[0];
        $params = isset($parts[1]) ? explode(',', $parts[1]) : [];

        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError($field, "{$field} is required");
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "{$field} must be a valid email");
                }
                break;

            case 'min':
                if (!empty($value) && strlen($value) < $params[0]) {
                    $this->addError($field, "{$field} must be at least {$params[0]} characters");
                }
                break;

            case 'max':
                if (!empty($value) && strlen($value) > $params[0]) {
                    $this->addError($field, "{$field} must not exceed {$params[0]} characters");
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, "{$field} must be numeric");
                }
                break;

            case 'alpha':
                if (!empty($value) && !ctype_alpha($value)) {
                    $this->addError($field, "{$field} must contain only letters");
                }
                break;

            case 'alphanumeric':
                if (!empty($value) && !ctype_alnum($value)) {
                    $this->addError($field, "{$field} must contain only letters and numbers");
                }
                break;

            case 'in':
                if (!empty($value) && !in_array($value, $params)) {
                    $this->addError($field, "{$field} must be one of: " . implode(', ', $params));
                }
                break;

            case 'array':
                if (!empty($value) && !is_array($value)) {
                    $this->addError($field, "{$field} must be an array");
                }
                break;

            case 'string':
                if (!empty($value) && !is_string($value)) {
                    $this->addError($field, "{$field} must be a string");
                }
                break;

            case 'unique':
                // 需要数据库查询，参数: table,column
                if (!empty($value) && count($params) >= 2) {
                    $table = $params[0];
                    $column = $params[1];
                    $db = Database::getInstance();

                    // 构建查询条件
                    $whereClause = "{$column} = :value";
                    $queryParams = ['value' => $value];

                    // 如果表是 adminUsers，需要排除软删除的记录（deletedAt IS NULL）
                    if ($table === 'adminUsers') {
                        $whereClause .= " AND deletedAt IS NULL";
                    }

                    $existing = $db->fetchOne(
                        "SELECT COUNT(*) as count FROM {$table} WHERE {$whereClause}",
                        $queryParams
                    );
                    if ($existing['count'] > 0) {
                        $this->addError($field, "{$field} already exists");
                    }
                }
                break;
        }
    }

    /**
     * 添加错误
     */
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * 获取错误
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * 快速验证
     */
    public static function make($data, $rules) {
        $validator = new self($data, $rules);
        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }
        return true;
    }
}
