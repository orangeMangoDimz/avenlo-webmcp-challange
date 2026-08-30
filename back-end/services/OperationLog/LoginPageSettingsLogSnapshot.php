<?php
/**
 * 登录页设置 — 保存前后快照与差异对比
 */

class LoginPageSettingsLogSnapshot {
    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function brandingFromRow(array $row) {
        return [
            'logoType' => trim((string) ($row['logoType'] ?? '')),
            'logoText' => trim((string) ($row['logoText'] ?? '')),
            'logoImagePath' => trim((string) ($row['logoImagePath'] ?? '')),
            'taglineEn' => trim((string) ($row['taglineEn'] ?? '')),
            'taglineZh' => trim((string) ($row['taglineZh'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function formFieldFromRow(array $row) {
        return [
            'fieldName' => trim((string) ($row['fieldName'] ?? '')),
            'fieldDescription' => trim((string) ($row['fieldDescription'] ?? '')),
            'fieldType' => trim((string) ($row['fieldType'] ?? '')),
            'isEnabled' => !empty($row['isEnabled']),
            'isRequired' => !empty($row['isRequired']),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function ipLanguageFromRow(array $row) {
        return [
            'isEnabled' => !empty($row['isEnabled']),
            'defaultLanguageCode' => trim((string) ($row['defaultLanguageCode'] ?? '')),
            'fallbackLanguageCode' => trim((string) ($row['fallbackLanguageCode'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function emailVerificationFromRow(array $row) {
        return [
            'isRequired' => !empty($row['isRequired']),
            'emailSubject' => trim((string) ($row['emailSubject'] ?? '')),
            'emailTemplate' => trim((string) ($row['emailTemplate'] ?? '')),
            'verificationLinkExpiryHours' => (int) ($row['verificationLinkExpiryHours'] ?? 0),
            'allowResend' => !empty($row['allowResend']),
            'resendCooldownMinutes' => (int) ($row['resendCooldownMinutes'] ?? 0),
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,string>
     */
    public static function brandingFingerprints(array $state) {
        return [
            'logoType' => (string) ($state['logoType'] ?? ''),
            'logoText' => (string) ($state['logoText'] ?? ''),
            'logoImagePath' => (string) ($state['logoImagePath'] ?? ''),
            'taglineEn' => (string) ($state['taglineEn'] ?? ''),
            'taglineZh' => (string) ($state['taglineZh'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,string>
     */
    public static function formFieldFingerprints(array $state) {
        return [
            'fieldName' => (string) ($state['fieldName'] ?? ''),
            'fieldDescription' => (string) ($state['fieldDescription'] ?? ''),
            'fieldType' => (string) ($state['fieldType'] ?? ''),
            'isEnabled' => !empty($state['isEnabled']) ? '1' : '0',
            'isRequired' => !empty($state['isRequired']) ? '1' : '0',
        ];
    }

    /**
     * @param array<string,string> $before
     * @param array<string,string> $after
     * @param string[] $order
     * @return string[]
     */
    public static function changedKeys(array $before, array $after, array $order) {
        $changed = [];
        foreach ($order as $key) {
            if (($before[$key] ?? '') !== ($after[$key] ?? '')) {
                $changed[] = $key;
            }
        }
        return $changed;
    }
}
