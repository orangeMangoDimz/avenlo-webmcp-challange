<?php
/**
 * 密码强度设置模型
 */

require_once __DIR__ . '/BaseModel.php';

class PasswordStrengthSettings extends BaseModel {
    protected $table = 'passwordStrengthSettings';
    protected $primaryKey = 'id';

    protected $fillable = [
        'strengthLevel', 'minLength', 'requireLetters',
        'requireNumbers', 'requireUppercase', 'requireLowercase',
        'requireSpecialChars', 'description'
    ];

    /**
     * 获取当前设置
     */
    public function getSettings() {
        $settings = $this->findById(1);
        if (!$settings) {
            // 创建默认设置
            $this->create([
                'strengthLevel' => 'medium',
                'minLength' => 8,
                'requireLetters' => 1,
                'requireNumbers' => 1,
                'requireUppercase' => 0,
                'requireLowercase' => 0,
                'requireSpecialChars' => 0,
                'description' => 'Minimum 8 characters with letters and numbers'
            ]);
            $settings = $this->findById(1);
        }
        return $this->convertBooleanFields($settings);
    }

    /**
     * 转换布尔字段为真正的布尔值
     */
    private function convertBooleanFields($data) {
        if (empty($data)) {
            return $data;
        }

        $data['requireLetters'] = (bool)(int)$data['requireLetters'];
        $data['requireNumbers'] = (bool)(int)$data['requireNumbers'];
        $data['requireUppercase'] = (bool)(int)$data['requireUppercase'];
        $data['requireLowercase'] = (bool)(int)$data['requireLowercase'];
        $data['requireSpecialChars'] = (bool)(int)$data['requireSpecialChars'];

        return $data;
    }

    /**
     * 更新设置
     */
    public function updateSettings($data) {
        return $this->update(1, $data);
    }

    /**
     * 验证密码是否符合当前设置
     */
    public function validatePassword($password) {
        $settings = $this->getSettings();
        $errors = [];

        // 检查最小长度
        if (strlen($password) < $settings['minLength']) {
            $errors[] = "Password must be at least {$settings['minLength']} characters long";
        }

        // 检查是否包含字母
        if ($settings['requireLetters'] && !preg_match('/[a-zA-Z]/', $password)) {
            $errors[] = "Password must contain letters";
        }

        // 检查是否包含数字
        if ($settings['requireNumbers'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain numbers";
        }

        // 检查是否包含大写字母
        if ($settings['requireUppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain uppercase letters";
        }

        // 检查是否包含小写字母
        if ($settings['requireLowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain lowercase letters";
        }

        // 检查是否包含特殊字符
        if ($settings['requireSpecialChars'] && !preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = "Password must contain special characters";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * 根据强度级别设置配置
     */
    public function applyStrengthLevel($level) {
        $configurations = [
            'low' => [
                'strengthLevel' => 'low',
                'minLength' => 6,
                'requireLetters' => 0,
                'requireNumbers' => 0,
                'requireUppercase' => 0,
                'requireLowercase' => 0,
                'requireSpecialChars' => 0,
                'description' => 'Minimum 6 characters'
            ],
            'medium' => [
                'strengthLevel' => 'medium',
                'minLength' => 8,
                'requireLetters' => 1,
                'requireNumbers' => 1,
                'requireUppercase' => 0,
                'requireLowercase' => 0,
                'requireSpecialChars' => 0,
                'description' => 'Minimum 8 characters with letters and numbers'
            ],
            'high' => [
                'strengthLevel' => 'high',
                'minLength' => 12,
                'requireLetters' => 1,
                'requireNumbers' => 1,
                'requireUppercase' => 1,
                'requireLowercase' => 1,
                'requireSpecialChars' => 1,
                'description' => 'Minimum 12 characters with uppercase, lowercase, numbers and special characters'
            ]
        ];

        if (!isset($configurations[$level])) {
            throw new Exception('Invalid strength level');
        }

        return $this->updateSettings($configurations[$level]);
    }
}
