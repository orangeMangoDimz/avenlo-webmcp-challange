<?php
/**
 * 邮件验证设置模型
 */

require_once __DIR__ . '/BaseModel.php';

class EmailVerificationSettings extends BaseModel {
    protected $table = 'emailVerificationSettings';
    protected $primaryKey = 'id';

    protected $fillable = [
        'isRequired', 'emailSubject', 'emailTemplate',
        'verificationLinkExpiryHours', 'allowResend', 'resendCooldownMinutes'
    ];

    /**
     * 获取设置
     */
    public function getSettings() {
        $config = require __DIR__ . '/../config/app.php';
        $logoname = $config['logoname'];
        $settings = $this->findById(1);
        if (!$settings) {
            // 创建默认设置
            $this->create([
                'isRequired' => 1,
                'emailSubject' => 'Verify Your Account',
                'emailTemplate' => "Dear {user_name},\n\nThank you for registering! To complete your registration and start trading, please verify your email address by clicking the link below:\n\n{verification_link}\n\nThis link will expire in 24 hours.\n\nIf you did not create this account, please ignore this email.\n\nBest regards,\nThe {$logoname} Team",
                'verificationLinkExpiryHours' => 24,
                'allowResend' => 1,
                'resendCooldownMinutes' => 5
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

        $data['isRequired'] = (bool)(int)$data['isRequired'];
        $data['allowResend'] = (bool)(int)$data['allowResend'];

        return $data;
    }

    /**
     * 更新设置
     */
    public function updateSettings($data) {
        // 确保布尔值被正确转换为整数
        if (isset($data['isRequired'])) {
            $data['isRequired'] = $data['isRequired'] ? 1 : 0;
        }
        if (isset($data['allowResend'])) {
            $data['allowResend'] = $data['allowResend'] ? 1 : 0;
        }

        return $this->update(1, $data);
    }

    /**
     * 生成验证邮件内容
     */
    public function generateEmailContent($userName, $verificationLink) {
        $settings = $this->getSettings();

        $content = str_replace(
            ['{user_name}', '{verification_link}'],
            [$userName, $verificationLink],
            $settings['emailTemplate']
        );

        return [
            'subject' => $settings['emailSubject'],
            'body' => $content
        ];
    }
}
