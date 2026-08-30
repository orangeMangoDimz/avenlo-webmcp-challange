<?php
/**
 * IP语言检测设置模型
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/../services/IpLanguageDetector.php';

class IpLanguageDetectionSettings extends BaseModel {
    protected $table = 'ipLanguageDetectionSettings';
    protected $primaryKey = 'id';

    protected $fillable = [
        'isEnabled', 'defaultLanguageCode', 'fallbackLanguageCode'
    ];
    private $detector = null;

    /**
     * 获取设置
     */
    public function getSettings() {
        $settings = $this->findById(1);
        if (!$settings) {
            // 创建默认设置
            $this->create([
                'isEnabled' => 1,
                'defaultLanguageCode' => 'en',
                'fallbackLanguageCode' => 'en'
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

        $data['isEnabled'] = (bool)(int)$data['isEnabled'];

        return $data;
    }

    /**
     * 更新设置
     */
    public function updateSettings($data) {
        return $this->update(1, $data);
    }

    private function getDetector(): IpLanguageDetector
    {
        if ($this->detector === null) {
            $this->detector = new IpLanguageDetector();
        }
        return $this->detector;
    }

    /**
     * 根据IP检测语言
     */
    public function detectLanguageByIp($ipAddress): string {
        $settings = $this->getSettings();

        if (!$settings['isEnabled']) {
            return $settings['defaultLanguageCode'];
        }

        $detector = $this->getDetector();
        return $detector->detectLanguage($ipAddress)
            ?? ($settings['fallbackLanguageCode']
                ?? $settings['defaultLanguageCode']);
    }
}
