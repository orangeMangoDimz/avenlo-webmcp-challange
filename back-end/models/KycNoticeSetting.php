<?php
/**
 * KYC Notice Setting Model
 * 对应表: kycNoticeSettings
 */

require_once __DIR__ . '/BaseModel.php';

class KycNoticeSetting extends BaseModel {
    protected $table = 'kycNoticeSettings';
    protected $primaryKey = 'id';

    protected $fillable = [
        'settingKey',
        'isEnabled',
        'noticeTitle',
        'noticeSubtitle',
        'noticeDescription',
        'requirementsTitle',
        'verificationTimeNotice',
        'primaryButtonText',
        'primaryButtonAction',
        'secondaryButtonText',
        'secondaryButtonAction',
        'displayPosition',
        'displayPriority',
        'isDismissible',
        'showIcon',
        'iconClass',
        'backgroundColor',
        'borderColor',
        'updatedBy'
    ];

    /**
     * 根据设置键获取设置
     */
    public function getByKey($settingKey) {
        return $this->findOne(['settingKey' => $settingKey]);
    }

    /**
     * 获取默认 KYC 通知设置
     */
    public function getDefaultSettings() {
        return $this->getByKey('default_kyc_notice');
    }

    /**
     * 获取所有启用的通知设置
     */
    public function getEnabledSettings() {
        return $this->findAll(['isEnabled' => 1], 'displayPriority ASC');
    }

    /**
     * 更新通知设置（通过 settingKey）
     */
    public function updateByKey($settingKey, $data) {
        $setting = $this->getByKey($settingKey);

        if (!$setting) {
            return false;
        }

        return $this->update($setting['id'], $data);
    }

    /**
     * 获取客户端可见的通知配置
     * 用于客户端仪表板显示
     */
    public function getClientNoticeConfig() {
        $setting = $this->getDefaultSettings();

        if (!$setting || !$setting['isEnabled']) {
            return null;
        }

        // 只返回客户端需要的字段
        return [
            'title' => $setting['noticeTitle'],
            'subtitle' => $setting['noticeSubtitle'],
            'description' => $setting['noticeDescription'],
            'requirementsTitle' => $setting['requirementsTitle'],
            'verificationTimeNotice' => $setting['verificationTimeNotice'],
            'primaryButton' => [
                'text' => $setting['primaryButtonText'],
                'action' => $setting['primaryButtonAction']
            ],
            'secondaryButton' => [
                'text' => $setting['secondaryButtonText'],
                'action' => $setting['secondaryButtonAction']
            ],
            'display' => [
                'position' => $setting['displayPosition'],
                'priority' => $setting['displayPriority'],
                'isDismissible' => (bool)$setting['isDismissible'],
                'showIcon' => (bool)$setting['showIcon'],
                'iconClass' => $setting['iconClass'],
                'backgroundColor' => $setting['backgroundColor'],
                'borderColor' => $setting['borderColor']
            ]
        ];
    }
}
