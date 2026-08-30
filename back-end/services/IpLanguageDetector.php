<?php

use GeoIp2\Database\Reader;

class IpLanguageDetector {
    private ?Reader $reader;
    private array $languageMap = [
        // English
        'US' => 'en',
        'GB' => 'en',
        'AU' => 'en',
        'NZ' => 'en',
        'IE' => 'en',
        'CA' => 'en',

        // Chinese
        'CN' => 'zh',
        'HK' => 'zh',
        'TW' => 'zh',
        'MO' => 'zh',
        'SG' => 'zh',

        // Japanese / Korean
        'JP' => 'ja',
        'KR' => 'ko',

        // Southeast Asia
        'TH' => 'th',
        'VN' => 'vi',
        'ID' => 'id',
        'MY' => 'ms',
        'PH' => 'en',

        // Europe
        'FR' => 'fr',
        'DE' => 'de',
        'ES' => 'es',
        'IT' => 'it',
        'NL' => 'nl',
        'PT' => 'pt',
        'RU' => 'ru',
        'TR' => 'tr',
        'PL' => 'pl',

        // Middle East
        'AE' => 'ar',
        'SA' => 'ar',
        'EG' => 'ar',

        // South Asia
        'IN' => 'en',
        'PK' => 'en',
        'BD' => 'en',

        // Africa
        'ZA' => 'en',
        'NG' => 'en',
        'KE' => 'en',
    ];

    public function __construct() {
        $config = require __DIR__ . '/../config/geoip.php';
        $mmdbPath = $config['database_path'];

        if (file_exists($mmdbPath)) {
            $this->reader = new Reader($mmdbPath);
        } else {
            error_log("[IpLanguageDetector] MMDB file not found: " . $mmdbPath);
            $this->reader = null;
        }
    }

    /**
     * 根据IP地址检测语言代码
     *
     * @param string $ipAddress
     * @return string|null 返回语言代码或null如果未检测到
     */
    public function detectLanguage(string $ipAddress): ?string
    {
        if (!$this->reader) {
            error_log("[IpLanguageDetector] GeoIP not initialized, skip detect. IP={$ipAddress}");
            return null;
        }

        try {
            // 获取国家信息
            $record = $this->reader->country($ipAddress);
            $isoCode = $record->country->isoCode ?? null;

            if (!$isoCode) {
                return null;
            }

            // 映射到语言代码
            return $this->languageMap[strtoupper($isoCode)] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }
}
