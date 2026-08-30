<?php
/**
 * KYC Email Notification Template Model
 * 对应表: kycEmailNotificationTemplates
 */

require_once __DIR__ . '/BaseModel.php';

class KycEmailNotificationTemplate extends BaseModel {
    protected $table = 'kycEmailNotificationTemplates';
    protected $primaryKey = 'id';

    protected $fillable = [
        'templateKey',
        'templateName',
        'emailSubject',
        'emailBody',
        'emailType',
        'triggerEvent',
        'isActive',
        'sendDelay',
        'ccEmails',
        'attachmentRequired',
        'updatedBy'
    ];

    /**
     * 根据模板键获取邮件模板
     */
    public function getByKey($templateKey) {
        return $this->findOne(['templateKey' => $templateKey]);
    }

    /**
     * 根据邮件类型获取模板
     */
    public function getByEmailType($emailType) {
        return $this->findAll(['emailType' => $emailType, 'isActive' => 1]);
    }

    /**
     * 获取所有活跃的邮件模板
     */
    public function getActiveTemplates() {
        return $this->findAll(['isActive' => 1], 'emailType, templateName');
    }

    /**
     * 根据触发事件获取模板
     */
    public function getByTriggerEvent($triggerEvent) {
        return $this->findOne([
            'triggerEvent' => $triggerEvent,
            'isActive' => 1
        ]);
    }

    /**
     * 更新邮件模板（通过 templateKey）
     */
    public function updateByKey($templateKey, $data) {
        $template = $this->getByKey($templateKey);

        if (!$template) {
            return false;
        }

        return $this->update($template['id'], $data);
    }

    /**
     * 处理模板变量替换
     */
    public function processTemplate($templateKey, $variables = []) {
        $template = $this->getByKey($templateKey);

        if (!$template) {
            return null;
        }

        $subject = $template['emailSubject'];
        $body = $template['emailBody'];

        // 替换变量
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $subject = str_replace($placeholder, $value, $subject);
            $body = str_replace($placeholder, $value, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
            'sendDelay' => $template['sendDelay'],
            'ccEmails' => json_decode($template['ccEmails'], true),
            'attachmentRequired' => (bool)$template['attachmentRequired']
        ];
    }

    /**
     * 获取按邮件类型分组的模板
     */
    public function getGroupedByType() {
        $templates = $this->getActiveTemplates();

        $grouped = [];
        foreach ($templates as $template) {
            $type = $template['emailType'];
            if (!isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $template;
        }

        return $grouped;
    }
}
