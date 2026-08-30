<?php
/**
 * 系统设置模块（log_system）操作日志详情文案
 */

require_once __DIR__ . '/OperationLogTextHelpers.php';
require_once __DIR__ . '/../OperationLog/AdminUserLogSnapshot.php';
require_once __DIR__ . '/../OperationLog/AdminRoleLogSnapshot.php';
require_once __DIR__ . '/../OperationLog/LoginPageSettingsLogSnapshot.php';

class SystemOperationLogTexts {
    /**
     * @param array<string,mixed> $state
     */
    public static function adminDisplayNameZh(array $state) {
        $name = trim((string) ($state['fullName'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $username = trim((string) ($state['username'] ?? ''));
        if ($username !== '') {
            return $username;
        }
        return trim((string) ($state['email'] ?? ''));
    }

    /**
     * @param array<string,mixed> $state
     */
    public static function adminDisplayNameEn(array $state) {
        $username = trim((string) ($state['username'] ?? ''));
        if ($username !== '') {
            return $username;
        }
        $email = trim((string) ($state['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }
        return trim((string) ($state['fullName'] ?? ''));
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function adminUserCreateSuccess(array $state) {
        $zh = self::adminDisplayNameZh($state);
        $en = self::adminDisplayNameEn($state);
        return ["创建管理员「{$zh}」", 'Created admin user "' . $en . '"'];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function adminUserDeleteSuccess(array $state) {
        $zh = self::adminDisplayNameZh($state);
        $en = self::adminDisplayNameEn($state);
        return ["删除管理员「{$zh}」", 'Deleted admin user "' . $en . '"'];
    }

    /**
     * @param array<string,mixed> $beforeState
     * @param array<string,mixed> $afterState
     * @return array{0:string,1:string}|null
     */
    public static function adminUserUpdateSuccessDiff(array $beforeState, array $afterState) {
        $fpBefore = AdminUserLogSnapshot::sectionFingerprints($beforeState);
        $fpAfter = AdminUserLogSnapshot::sectionFingerprints($afterState);
        $changed = AdminUserLogSnapshot::changedSectionKeys($fpBefore, $fpAfter);
        if (empty($changed)) {
            return null;
        }

        $nameZh = self::adminDisplayNameZh($afterState);
        $nameEn = self::adminDisplayNameEn($afterState);

        $partsZh = [];
        $partsEn = [];
        foreach ($changed as $key) {
            list($zh, $en) = self::formatAdminUserChangedSection($key, $beforeState, $afterState);
            $partsZh[] = $zh;
            $partsEn[] = $en;
        }

        return [
            '更新管理员「' . $nameZh . '」：' . implode('；', $partsZh),
            'Updated admin user "' . $nameEn . '": ' . implode('; ', $partsEn),
        ];
    }

    public static function adminUserCreateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '创建管理员失败',
            'Failed to create admin user',
            $apiMessageEn
        );
    }

    public static function adminUserUpdateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新管理员失败',
            'Failed to update admin user',
            $apiMessageEn
        );
    }

    public static function adminUserDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '删除管理员失败',
            'Failed to delete admin user',
            $apiMessageEn
        );
    }

    /**
     * @param array<string,mixed> $beforeState
     * @param array<string,mixed> $afterState
     * @return array{0:string,1:string}
     */
    private static function formatAdminUserChangedSection($key, array $beforeState, array $afterState) {
        switch ($key) {
            case 'fullName':
                $oldZh = self::adminDisplayNameZh($beforeState);
                $newZh = self::adminDisplayNameZh($afterState);
                $oldEn = self::adminDisplayNameEn($beforeState);
                $newEn = self::adminDisplayNameEn($afterState);
                return ["姓名：「{$oldZh}」→「{$newZh}」", 'Name: "' . $oldEn . '" -> "' . $newEn . '"'];

            case 'email':
                $old = trim((string) ($beforeState['email'] ?? ''));
                $new = trim((string) ($afterState['email'] ?? ''));
                return ["邮箱：{$old}→{$new}", 'Email: ' . $old . ' -> ' . $new];

            case 'role':
                $oldZh = trim((string) ($beforeState['roleLabelZh'] ?? '')) ?: '—';
                $newZh = trim((string) ($afterState['roleLabelZh'] ?? '')) ?: '—';
                $oldEn = trim((string) ($beforeState['roleLabelEn'] ?? '')) ?: '—';
                $newEn = trim((string) ($afterState['roleLabelEn'] ?? '')) ?: '—';
                return ["角色：「{$oldZh}」→「{$newZh}」", 'Role: "' . $oldEn . '" -> "' . $newEn . '"'];

            case 'department':
                $old = trim((string) ($beforeState['departmentName'] ?? '')) ?: '—';
                $new = trim((string) ($afterState['departmentName'] ?? '')) ?: '—';
                return ["部门：「{$old}」→「{$new}」", 'Department: "' . $old . '" -> "' . $new . '"'];

            case 'position':
                $old = trim((string) ($beforeState['positionName'] ?? '')) ?: '—';
                $new = trim((string) ($afterState['positionName'] ?? '')) ?: '—';
                return ["职位：「{$old}」→「{$new}」", 'Position: "' . $old . '" -> "' . $new . '"'];

            case 'status':
                $oldZh = self::statusLabelZh($beforeState['status'] ?? '');
                $newZh = self::statusLabelZh($afterState['status'] ?? '');
                $oldEn = self::statusLabelEn($beforeState['status'] ?? '');
                $newEn = self::statusLabelEn($afterState['status'] ?? '');
                return ["状态：{$oldZh}→{$newZh}", 'Status: ' . $oldEn . ' -> ' . $newEn];

            case 'password':
                return ['密码：已更新', 'Password: updated'];

            default:
                return ['', ''];
        }
    }

    private static function statusLabelZh($status) {
        $status = trim((string) $status);
        if ($status === 'active') {
            return '启用';
        }
        if ($status === 'inactive') {
            return '停用';
        }
        return $status !== '' ? $status : '—';
    }

    private static function statusLabelEn($status) {
        $status = trim((string) $status);
        if ($status === 'active') {
            return 'Active';
        }
        if ($status === 'inactive') {
            return 'Inactive';
        }
        return $status !== '' ? $status : '—';
    }

    // --- role_management（角色管理）---

    /**
     * @param array<string,mixed> $state
     */
    public static function roleDisplayNameZh(array $state) {
        $display = trim((string) ($state['roleDisplayName'] ?? ''));
        if ($display !== '') {
            return $display;
        }
        return trim((string) ($state['roleName'] ?? ''));
    }

    /**
     * @param array<string,mixed> $state
     */
    public static function roleDisplayNameEn(array $state) {
        $name = trim((string) ($state['roleName'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        return trim((string) ($state['roleDisplayName'] ?? ''));
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function adminRoleCreateSuccess(array $state) {
        $zh = self::roleDisplayNameZh($state);
        $en = self::roleDisplayNameEn($state);
        return ["创建角色「{$zh}」", 'Created role "' . $en . '"'];
    }

    /**
     * @param string[] $changed
     * @return array{0:string,1:string}|null
     */
    public static function adminRoleUpdateSuccessDiff(array $beforeState, array $afterState, array $changed) {
        if (empty($changed)) {
            return null;
        }

        $nameZh = self::roleDisplayNameZh($afterState);
        $nameEn = self::roleDisplayNameEn($afterState);

        $partsZh = [];
        $partsEn = [];
        foreach ($changed as $key) {
            list($zh, $en) = self::formatAdminRoleChangedSection($key, $beforeState, $afterState);
            if ($zh !== '' || $en !== '') {
                $partsZh[] = $zh;
                $partsEn[] = $en;
            }
        }
        if (empty($partsZh)) {
            return null;
        }

        return [
            '更新角色「' . $nameZh . '」：' . implode('；', $partsZh),
            'Updated role "' . $nameEn . '": ' . implode('; ', $partsEn),
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function adminRoleToggleSuccess(array $state, $enabled) {
        $zh = self::roleDisplayNameZh($state);
        $en = self::roleDisplayNameEn($state);
        if ($enabled) {
            return ["启用角色「{$zh}」", 'Enabled role "' . $en . '"'];
        }
        return ["停用角色「{$zh}」", 'Disabled role "' . $en . '"'];
    }

    public static function adminRoleCreateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '创建角色失败',
            'Failed to create role',
            $apiMessageEn
        );
    }

    public static function adminRoleUpdateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新角色失败',
            'Failed to update role',
            $apiMessageEn
        );
    }

    /**
     * @param array<string,mixed> $beforeState
     * @param array<string,mixed> $afterState
     * @return array{0:string,1:string}
     */
    private static function formatAdminRoleChangedSection($key, array $beforeState, array $afterState) {
        switch ($key) {
            case 'roleName':
                $oldZh = self::roleDisplayNameZh($beforeState);
                $newZh = self::roleDisplayNameZh($afterState);
                $oldEn = self::roleDisplayNameEn($beforeState);
                $newEn = self::roleDisplayNameEn($afterState);
                return ["名称：「{$oldZh}」→「{$newZh}」", 'Name: "' . $oldEn . '" -> "' . $newEn . '"'];

            case 'description':
                $old = trim((string) ($beforeState['description'] ?? '')) ?: '—';
                $new = trim((string) ($afterState['description'] ?? '')) ?: '—';
                return ["描述：「{$old}」→「{$new}」", 'Description: "' . $old . '" -> "' . $new . '"'];

            case 'isActive':
                $oldZh = self::roleActiveLabelZh($beforeState['isActive'] ?? false);
                $newZh = self::roleActiveLabelZh($afterState['isActive'] ?? false);
                $oldEn = self::roleActiveLabelEn($beforeState['isActive'] ?? false);
                $newEn = self::roleActiveLabelEn($afterState['isActive'] ?? false);
                return ["状态：{$oldZh}→{$newZh}", 'Status: ' . $oldEn . ' -> ' . $newEn];

            case 'permissions':
                $oldCount = (int) ($beforeState['permissionCount'] ?? count($beforeState['permissionIds'] ?? []));
                $newCount = (int) ($afterState['permissionCount'] ?? count($afterState['permissionIds'] ?? []));
                return ["权限：{$oldCount} 项→{$newCount} 项", 'Permissions: ' . $oldCount . ' item(s) -> ' . $newCount . ' item(s)'];

            default:
                return ['', ''];
        }
    }

    private static function roleActiveLabelZh($isActive) {
        return !empty($isActive) ? '启用' : '停用';
    }

    private static function roleActiveLabelEn($isActive) {
        return !empty($isActive) ? 'Active' : 'Inactive';
    }

    // --- login_page_settings（登录页设置）---

    private static function loginPageFieldLabelZh($name) {
        $name = trim((string) $name);
        return $name !== '' ? $name : '未命名字段';
    }

    private static function loginPageFieldLabelEn($name) {
        $name = trim((string) $name);
        return $name !== '' ? $name : 'Untitled field';
    }

    private static function loginPageDocLabelZh($title) {
        $title = trim((string) $title);
        return $title !== '' ? $title : '未命名文档';
    }

    private static function loginPageDocLabelEn($title) {
        $title = trim((string) $title);
        return $title !== '' ? $title : 'Untitled document';
    }

    private static function loginPagePasswordLevelLabelZh($level) {
        $level = strtolower(trim((string) $level));
        $map = ['low' => '低', 'medium' => '中', 'high' => '高'];
        return $map[$level] ?? $level;
    }

    private static function loginPagePasswordLevelLabelEn($level) {
        $level = strtolower(trim((string) $level));
        $map = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];
        return $map[$level] ?? ucfirst($level);
    }

    /**
     * @param array<string,mixed> $beforeState
     * @param array<string,mixed> $afterState
     * @return array{0:string,1:string}|null
     */
    public static function loginPageBrandingEditSuccessDiff(array $beforeState, array $afterState) {
        $fpBefore = LoginPageSettingsLogSnapshot::brandingFingerprints($beforeState);
        $fpAfter = LoginPageSettingsLogSnapshot::brandingFingerprints($afterState);
        $changed = LoginPageSettingsLogSnapshot::changedKeys(
            $fpBefore,
            $fpAfter,
            ['logoType', 'logoText', 'logoImagePath', 'taglineEn', 'taglineZh']
        );
        if (empty($changed)) {
            return null;
        }

        $partsZh = [];
        $partsEn = [];
        foreach ($changed as $key) {
            list($zh, $en) = self::formatLoginPageBrandingChangedSection($key, $beforeState, $afterState);
            if ($zh !== '' || $en !== '') {
                $partsZh[] = $zh;
                $partsEn[] = $en;
            }
        }
        if (empty($partsZh)) {
            return null;
        }

        return [
            '更新登录页品牌设置：' . implode('；', $partsZh),
            'Updated login page branding: ' . implode('; ', $partsEn),
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function loginPageBrandingUploadLogoSuccess() {
        return ['上传登录页 Logo', 'Uploaded login page logo'];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function loginPageFormFieldAddSuccess($fieldName) {
        $zh = self::loginPageFieldLabelZh($fieldName);
        $en = self::loginPageFieldLabelEn($fieldName);
        return ["新增注册表单字段「{$zh}」", 'Added registration form field "' . $en . '"'];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function loginPageFormFieldEditSuccess($fieldName) {
        $zh = self::loginPageFieldLabelZh($fieldName);
        $en = self::loginPageFieldLabelEn($fieldName);
        return ["更新注册表单字段「{$zh}」", 'Updated registration form field "' . $en . '"'];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function loginPageFormFieldToggleSuccess($fieldName, $enabled) {
        $zh = self::loginPageFieldLabelZh($fieldName);
        $en = self::loginPageFieldLabelEn($fieldName);
        if ($enabled) {
            return ["启用注册表单字段「{$zh}」", 'Enabled registration form field "' . $en . '"'];
        }
        return ["停用注册表单字段「{$zh}」", 'Disabled registration form field "' . $en . '"'];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function loginPageFormFieldDeleteSuccess($fieldName) {
        $zh = self::loginPageFieldLabelZh($fieldName);
        $en = self::loginPageFieldLabelEn($fieldName);
        return ["删除注册表单字段「{$zh}」", 'Deleted registration form field "' . $en . '"'];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function loginPageFormFieldsOrderSuccess() {
        return ['更新注册表单字段顺序', 'Updated registration form field order'];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function loginPagePasswordLevelApplySuccess($level) {
        $zh = self::loginPagePasswordLevelLabelZh($level);
        $en = self::loginPagePasswordLevelLabelEn($level);
        return ["应用密码强度等级：{$zh}", 'Applied password strength level: ' . $en];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function loginPageCountriesStatusSuccess($enabledCount, $disabledCount) {
        $enabledCount = max(0, (int) $enabledCount);
        $disabledCount = max(0, (int) $disabledCount);
        return [
            "更新国家/地区启用状态（启用 {$enabledCount} 个，停用 {$disabledCount} 个）",
            "Updated country availability (enabled {$enabledCount}, disabled {$disabledCount})",
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function loginPageLegalDocAddSuccess($title) {
        $zh = self::loginPageDocLabelZh($title);
        $en = self::loginPageDocLabelEn($title);
        return ["新增法律文档「{$zh}」", 'Added legal document "' . $en . '"'];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function loginPageLegalDocEditSuccess($title) {
        $zh = self::loginPageDocLabelZh($title);
        $en = self::loginPageDocLabelEn($title);
        return ["更新法律文档「{$zh}」", 'Updated legal document "' . $en . '"'];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function loginPageLegalDocDeleteSuccess($title) {
        $zh = self::loginPageDocLabelZh($title);
        $en = self::loginPageDocLabelEn($title);
        return ["删除法律文档「{$zh}」", 'Deleted legal document "' . $en . '"'];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function loginPageLanguagePackUploadSuccess($languageName, $languageCode) {
        $name = trim((string) $languageName);
        $code = trim((string) $languageCode);
        if ($name === '') {
            $name = strtoupper($code);
        }
        return [
            "上传语言包「{$name}」（{$code}）",
            'Uploaded language pack "' . $name . '" (' . $code . ')',
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function loginPageLanguagePackToggleSuccess($languageCode, $enabled) {
        $code = trim((string) $languageCode);
        if ($enabled) {
            return ["启用语言包（{$code}）", 'Enabled language pack (' . $code . ')'];
        }
        return ["停用语言包（{$code}）", 'Disabled language pack (' . $code . ')'];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function loginPageDefaultLanguageSetSuccess($languageCode) {
        $code = trim((string) $languageCode);
        return ["设置默认语言为 {$code}", 'Set default language to ' . $code];
    }

    /**
     * @param array<string,mixed> $beforeState
     * @param array<string,mixed> $afterState
     * @return array{0:string,1:string}|null
     */
    public static function loginPageIpLanguageDetectionEditSuccessDiff(array $beforeState, array $afterState) {
        $before = LoginPageSettingsLogSnapshot::ipLanguageFromRow($beforeState);
        $after = LoginPageSettingsLogSnapshot::ipLanguageFromRow($afterState);
        $fpBefore = [
            'isEnabled' => !empty($before['isEnabled']) ? '1' : '0',
            'defaultLanguageCode' => (string) ($before['defaultLanguageCode'] ?? ''),
            'fallbackLanguageCode' => (string) ($before['fallbackLanguageCode'] ?? ''),
        ];
        $fpAfter = [
            'isEnabled' => !empty($after['isEnabled']) ? '1' : '0',
            'defaultLanguageCode' => (string) ($after['defaultLanguageCode'] ?? ''),
            'fallbackLanguageCode' => (string) ($after['fallbackLanguageCode'] ?? ''),
        ];
        $changed = LoginPageSettingsLogSnapshot::changedKeys(
            $fpBefore,
            $fpAfter,
            ['isEnabled', 'defaultLanguageCode', 'fallbackLanguageCode']
        );
        if (empty($changed)) {
            return null;
        }

        $partsZh = [];
        $partsEn = [];
        foreach ($changed as $key) {
            if ($key === 'isEnabled') {
                $on = !empty($after['isEnabled']);
                $partsZh[] = $on ? '开启 IP 语言检测' : '关闭 IP 语言检测';
                $partsEn[] = $on ? 'enabled IP language detection' : 'disabled IP language detection';
                continue;
            }
            if ($key === 'defaultLanguageCode') {
                $old = $fpBefore['defaultLanguageCode'] ?: '—';
                $new = $fpAfter['defaultLanguageCode'] ?: '—';
                $partsZh[] = "默认语言：{$old}→{$new}";
                $partsEn[] = 'default language: ' . $old . ' -> ' . $new;
                continue;
            }
            if ($key === 'fallbackLanguageCode') {
                $old = $fpBefore['fallbackLanguageCode'] ?: '—';
                $new = $fpAfter['fallbackLanguageCode'] ?: '—';
                $partsZh[] = "回退语言：{$old}→{$new}";
                $partsEn[] = 'fallback language: ' . $old . ' -> ' . $new;
            }
        }

        return [
            '更新 IP 语言检测设置：' . implode('；', $partsZh),
            'Updated IP language detection: ' . implode('; ', $partsEn),
        ];
    }

    /**
     * @param array<string,mixed> $beforeState
     * @param array<string,mixed> $afterState
     * @return string[]
     */
    public static function loginPageEmailVerificationChangedKeys(array $beforeState, array $afterState) {
        $before = LoginPageSettingsLogSnapshot::emailVerificationFromRow($beforeState);
        $after = LoginPageSettingsLogSnapshot::emailVerificationFromRow($afterState);
        $fpBefore = [
            'isRequired' => !empty($before['isRequired']) ? '1' : '0',
            'emailSubject' => (string) ($before['emailSubject'] ?? ''),
            'emailTemplate' => (string) ($before['emailTemplate'] ?? ''),
            'verificationLinkExpiryHours' => (string) (int) ($before['verificationLinkExpiryHours'] ?? 0),
            'allowResend' => !empty($before['allowResend']) ? '1' : '0',
            'resendCooldownMinutes' => (string) (int) ($before['resendCooldownMinutes'] ?? 0),
        ];
        $fpAfter = [
            'isRequired' => !empty($after['isRequired']) ? '1' : '0',
            'emailSubject' => (string) ($after['emailSubject'] ?? ''),
            'emailTemplate' => (string) ($after['emailTemplate'] ?? ''),
            'verificationLinkExpiryHours' => (string) (int) ($after['verificationLinkExpiryHours'] ?? 0),
            'allowResend' => !empty($after['allowResend']) ? '1' : '0',
            'resendCooldownMinutes' => (string) (int) ($after['resendCooldownMinutes'] ?? 0),
        ];
        return LoginPageSettingsLogSnapshot::changedKeys(
            $fpBefore,
            $fpAfter,
            ['isRequired', 'emailSubject', 'emailTemplate', 'verificationLinkExpiryHours', 'allowResend', 'resendCooldownMinutes']
        );
    }

    /**
     * @param array<string,mixed> $beforeState
     * @param array<string,mixed> $afterState
     * @return array{0:string,1:string}|null
     */
    public static function loginPageEmailVerificationEditSuccessDiff(array $beforeState, array $afterState) {
        $before = LoginPageSettingsLogSnapshot::emailVerificationFromRow($beforeState);
        $after = LoginPageSettingsLogSnapshot::emailVerificationFromRow($afterState);
        $changed = self::loginPageEmailVerificationChangedKeys($beforeState, $afterState);
        if (empty($changed)) {
            return null;
        }

        $partsZh = [];
        $partsEn = [];
        foreach ($changed as $key) {
            if ($key === 'isRequired') {
                $on = !empty($after['isRequired']);
                $partsZh[] = $on ? '开启注册邮箱验证' : '关闭注册邮箱验证';
                $partsEn[] = $on ? 'enabled email verification' : 'disabled email verification';
                continue;
            }
            if ($key === 'emailSubject') {
                $old = trim((string) ($before['emailSubject'] ?? '')) ?: '—';
                $new = trim((string) ($after['emailSubject'] ?? '')) ?: '—';
                $partsZh[] = "邮件主题：{$old}→{$new}";
                $partsEn[] = 'email subject: ' . $old . ' -> ' . $new;
                continue;
            }
            if ($key === 'emailTemplate') {
                $partsZh[] = '邮件模板已更新';
                $partsEn[] = 'email template updated';
                continue;
            }
            if ($key === 'verificationLinkExpiryHours') {
                $old = (int) ($before['verificationLinkExpiryHours'] ?? 0);
                $new = (int) ($after['verificationLinkExpiryHours'] ?? 0);
                $partsZh[] = "验证链接有效期：{$old} 小时→{$new} 小时";
                $partsEn[] = 'verification link expiry: ' . $old . 'h -> ' . $new . 'h';
                continue;
            }
            if ($key === 'allowResend') {
                $on = !empty($after['allowResend']);
                $partsZh[] = $on ? '允许重发验证邮件' : '禁止重发验证邮件';
                $partsEn[] = $on ? 'allowed resend' : 'disallowed resend';
                continue;
            }
            if ($key === 'resendCooldownMinutes') {
                $old = (int) ($before['resendCooldownMinutes'] ?? 0);
                $new = (int) ($after['resendCooldownMinutes'] ?? 0);
                $partsZh[] = "重发冷却时间：{$old} 分钟→{$new} 分钟";
                $partsEn[] = 'resend cooldown: ' . $old . ' min -> ' . $new . ' min';
            }
        }

        return [
            '更新邮件验证设置：' . implode('；', $partsZh),
            'Updated email verification settings: ' . implode('; ', $partsEn),
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function formatLoginPageBrandingChangedSection($key, array $beforeState, array $afterState) {
        switch ($key) {
            case 'logoType':
                $old = trim((string) ($beforeState['logoType'] ?? '')) ?: '—';
                $new = trim((string) ($afterState['logoType'] ?? '')) ?: '—';
                return ["Logo 类型：{$old}→{$new}", 'Logo type: ' . $old . ' -> ' . $new];
            case 'logoText':
                $old = trim((string) ($beforeState['logoText'] ?? '')) ?: '—';
                $new = trim((string) ($afterState['logoText'] ?? '')) ?: '—';
                return ["Logo 文字：{$old}→{$new}", 'Logo text: ' . $old . ' -> ' . $new];
            case 'logoImagePath':
                return ['Logo 图片已更新', 'Logo image updated'];
            case 'taglineEn':
                $partsZh = ['英文标语已更新'];
                $partsEn = ['English tagline updated'];
                return [$partsZh[0], $partsEn[0]];
            case 'taglineZh':
                return ['中文标语已更新', 'Chinese tagline updated'];
            default:
                return ['', ''];
        }
    }

    public static function loginPageBrandingEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新登录页品牌设置失败',
            'Failed to update login page branding',
            $apiMessageEn
        );
    }

    public static function loginPageBrandingUploadLogoFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '上传登录页 Logo 失败',
            'Failed to upload login page logo',
            $apiMessageEn
        );
    }

    public static function loginPageFormFieldAddFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '新增注册表单字段失败',
            'Failed to add registration form field',
            $apiMessageEn
        );
    }

    public static function loginPageFormFieldEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新注册表单字段失败',
            'Failed to update registration form field',
            $apiMessageEn
        );
    }

    public static function loginPageFormFieldDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '删除注册表单字段失败',
            'Failed to delete registration form field',
            $apiMessageEn
        );
    }

    public static function loginPageFormFieldsOrderFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新注册表单字段顺序失败',
            'Failed to update registration form field order',
            $apiMessageEn
        );
    }

    public static function loginPagePasswordLevelApplyFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '应用密码强度等级失败',
            'Failed to apply password strength level',
            $apiMessageEn
        );
    }

    public static function loginPageCountriesStatusFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新国家/地区启用状态失败',
            'Failed to update country availability',
            $apiMessageEn
        );
    }

    public static function loginPageLegalDocAddFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '新增法律文档失败',
            'Failed to add legal document',
            $apiMessageEn
        );
    }

    public static function loginPageLegalDocEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新法律文档失败',
            'Failed to update legal document',
            $apiMessageEn
        );
    }

    public static function loginPageLegalDocDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '删除法律文档失败',
            'Failed to delete legal document',
            $apiMessageEn
        );
    }

    public static function loginPageLanguagePackUploadFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '上传语言包失败',
            'Failed to upload language pack',
            $apiMessageEn
        );
    }

    public static function loginPageLanguagePackEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新语言包失败',
            'Failed to update language pack',
            $apiMessageEn
        );
    }

    public static function loginPageDefaultLanguageSetFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '设置默认语言失败',
            'Failed to set default language',
            $apiMessageEn
        );
    }

    public static function loginPageIpLanguageDetectionEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新 IP 语言检测设置失败',
            'Failed to update IP language detection settings',
            $apiMessageEn
        );
    }

    public static function loginPageEmailVerificationEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新邮件验证设置失败',
            'Failed to update email verification settings',
            $apiMessageEn
        );
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function logSettingsModuleToggleSuccess($nameZh, $nameEn, $enabled) {
        $nameZh = trim((string) $nameZh);
        $nameEn = trim((string) $nameEn);
        if ($enabled) {
            return [
                "启动「{$nameZh}」模块操作日志",
                'Started operation logging for "' . $nameEn . '" module',
            ];
        }
        return [
            "停止「{$nameZh}」模块操作日志",
            'Stopped operation logging for "' . $nameEn . '" module',
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $modules
     * @return array{0:string,1:string}
     */
    public static function logSettingsModuleBulkToggleSuccess(array $modules, $enabled) {
        $namesZh = [];
        $namesEn = [];
        foreach ($modules as $row) {
            $zh = trim((string) ($row['moduleNameZh'] ?? ''));
            $en = trim((string) ($row['moduleNameEn'] ?? ''));
            if ($zh === '') {
                $zh = trim((string) ($row['modelKey'] ?? ''));
            }
            if ($en === '') {
                $en = trim((string) ($row['modelKey'] ?? ''));
            }
            if ($zh !== '') {
                $namesZh[] = $zh;
            }
            if ($en !== '') {
                $namesEn[] = $en;
            }
        }
        $count = count($modules);
        $listZh = implode('、', $namesZh);
        $listEn = implode(', ', $namesEn);
        if ($enabled) {
            return [
                "批量启动操作日志：{$listZh}（{$count} 个模块）",
                "Bulk started operation logging for: {$listEn} ({$count} modules)",
            ];
        }
        return [
            "批量停止操作日志：{$listZh}（{$count} 个模块）",
            "Bulk stopped operation logging for: {$listEn} ({$count} modules)",
        ];
    }

    public static function logSettingsModuleEnableFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '启动模块操作日志失败',
            'Failed to start module operation logging',
            $apiMessageEn
        );
    }

    public static function logSettingsModuleDisableFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '停止模块操作日志失败',
            'Failed to stop module operation logging',
            $apiMessageEn
        );
    }

    public static function logSettingsModuleBulkEnableFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '批量启动模块操作日志失败',
            'Failed to bulk start module operation logging',
            $apiMessageEn
        );
    }

    public static function logSettingsModuleBulkDisableFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '批量停止模块操作日志失败',
            'Failed to bulk stop module operation logging',
            $apiMessageEn
        );
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function emailSettingsSectionLabelZh($sectionKey, $sectionNameEn = '') {
        $map = [
            'leads' => 'Leads',
            'client_list' => '客户列表',
            'ib_list' => 'IB 列表',
        ];
        $key = trim((string) $sectionKey);
        if (isset($map[$key])) {
            return $map[$key];
        }
        $fallback = trim((string) $sectionNameEn);
        return $fallback !== '' ? $fallback : $key;
    }

    /**
     * @param array{added:int[],removed:int[]} $diff
     * @param array<int,string> $templateNameById
     * @return array{0:string,1:string}
     */
    public static function emailSettingsSectionUpdateSuccess(
        $sectionKey,
        $sectionNameEn,
        array $diff,
        array $templateNameById
    ) {
        $sectionZh = self::emailSettingsSectionLabelZh($sectionKey, $sectionNameEn);
        $sectionEn = trim((string) $sectionNameEn);
        if ($sectionEn === '') {
            $sectionEn = trim((string) $sectionKey);
        }

        $addedNamesZh = self::formatEmailTemplateNameList($diff['added'] ?? [], $templateNameById, true);
        $removedNamesZh = self::formatEmailTemplateNameList($diff['removed'] ?? [], $templateNameById, true);
        $addedNamesEn = self::formatEmailTemplateNameList($diff['added'] ?? [], $templateNameById, false);
        $removedNamesEn = self::formatEmailTemplateNameList($diff['removed'] ?? [], $templateNameById, false);

        $partsZh = [];
        $partsEn = [];
        if ($addedNamesZh !== '') {
            $partsZh[] = '新增 ' . $addedNamesZh;
            $partsEn[] = 'added ' . $addedNamesEn;
        }
        if ($removedNamesZh !== '') {
            $partsZh[] = '移除 ' . $removedNamesZh;
            $partsEn[] = 'removed ' . $removedNamesEn;
        }
        $changeZh = !empty($partsZh) ? implode('；', $partsZh) : '无变更';
        $changeEn = !empty($partsEn) ? implode('; ', $partsEn) : 'no changes';

        return [
            "更新 {$sectionZh} 板块邮件模板：{$changeZh}",
            'Updated ' . $sectionEn . ' section email templates: ' . $changeEn,
        ];
    }

    public static function emailSettingsSectionUpdateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新板块邮件模板失败',
            'Failed to update section email templates',
            $apiMessageEn
        );
    }

    /**
     * @param int[] $ids
     * @param array<int,string> $templateNameById
     */
    private static function formatEmailTemplateNameList(array $ids, array $templateNameById, $useChineseQuotes) {
        $labels = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $name = trim((string) ($templateNameById[$id] ?? ''));
            if ($name === '') {
                $name = 'ID ' . $id;
            }
            if ($useChineseQuotes) {
                $labels[] = '「' . $name . '」';
            } else {
                $labels[] = '"' . $name . '"';
            }
        }
        return implode('、', $labels);
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function emailTemplateDisplayNameZh(array $state) {
        $name = trim((string) ($state['templateName'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $key = trim((string) ($state['templateKey'] ?? ''));
        return $key !== '' ? $key : '邮件模板';
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function emailTemplateDisplayNameEn(array $state) {
        $name = trim((string) ($state['templateName'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $key = trim((string) ($state['templateKey'] ?? ''));
        return $key !== '' ? $key : 'Email template';
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function emailTemplateCreateSuccess(array $state) {
        $zh = self::emailTemplateDisplayNameZh($state);
        $en = self::emailTemplateDisplayNameEn($state);
        $key = trim((string) ($state['templateKey'] ?? ''));
        $keyPart = $key !== '' ? "（key: {$key}）" : '';
        $keyPartEn = $key !== '' ? ' (key: ' . $key . ')' : '';
        return [
            "创建邮件模板「{$zh}」{$keyPart}",
            'Created email template "' . $en . '"' . $keyPartEn,
        ];
    }

    /**
     * @param string[] $changed
     * @return array{0:string,1:string}|null
     */
    public static function emailTemplateUpdateSuccessDiff(array $beforeState, array $afterState, array $changed) {
        if (empty($changed)) {
            return null;
        }

        $nameZh = self::emailTemplateDisplayNameZh($afterState);
        $nameEn = self::emailTemplateDisplayNameEn($afterState);

        $partsZh = [];
        $partsEn = [];
        foreach ($changed as $key) {
            list($zh, $en) = self::formatEmailTemplateChangedSection($key, $beforeState, $afterState);
            if ($zh !== '' || $en !== '') {
                $partsZh[] = $zh;
                $partsEn[] = $en;
            }
        }
        if (empty($partsZh)) {
            return null;
        }

        return [
            '更新邮件模板「' . $nameZh . '」：' . implode('；', $partsZh),
            'Updated email template "' . $nameEn . '": ' . implode('; ', $partsEn),
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function emailTemplateToggleSuccess(array $state, $enabled) {
        $zh = self::emailTemplateDisplayNameZh($state);
        $en = self::emailTemplateDisplayNameEn($state);
        if ($enabled) {
            return ["启用邮件模板「{$zh}」", 'Enabled email template "' . $en . '"'];
        }
        return ["停用邮件模板「{$zh}」", 'Disabled email template "' . $en . '"'];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function emailTemplateDeleteSuccess(array $state) {
        $zh = self::emailTemplateDisplayNameZh($state);
        $en = self::emailTemplateDisplayNameEn($state);
        $key = trim((string) ($state['templateKey'] ?? ''));
        $keyPart = $key !== '' ? "（key: {$key}）" : '';
        $keyPartEn = $key !== '' ? ' (key: ' . $key . ')' : '';
        return [
            "删除邮件模板「{$zh}」{$keyPart}",
            'Deleted email template "' . $en . '"' . $keyPartEn,
        ];
    }

    public static function emailTemplateCreateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '创建邮件模板失败',
            'Failed to create email template',
            $apiMessageEn
        );
    }

    public static function emailTemplateUpdateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新邮件模板失败',
            'Failed to update email template',
            $apiMessageEn
        );
    }

    public static function emailTemplateDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '删除邮件模板失败',
            'Failed to delete email template',
            $apiMessageEn
        );
    }

    public static function emailTemplateToggleFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '切换邮件模板状态失败',
            'Failed to toggle email template status',
            $apiMessageEn
        );
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function formatEmailTemplateChangedSection($key, array $beforeState, array $afterState) {
        switch ($key) {
            case 'templateKey':
                $old = trim((string) ($beforeState['templateKey'] ?? ''));
                $new = trim((string) ($afterState['templateKey'] ?? ''));
                return ["模板 key：{$old} → {$new}", "template key: {$old} → {$new}"];
            case 'templateName':
                $old = trim((string) ($beforeState['templateName'] ?? ''));
                $new = trim((string) ($afterState['templateName'] ?? ''));
                return ["模板名称：{$old} → {$new}", "template name: {$old} → {$new}"];
            case 'category':
                $old = trim((string) ($beforeState['category'] ?? ''));
                $new = trim((string) ($afterState['category'] ?? ''));
                return ["分类：{$old} → {$new}", "category: {$old} → {$new}"];
            case 'emailSubject':
                $old = trim((string) ($beforeState['emailSubject'] ?? ''));
                $new = trim((string) ($afterState['emailSubject'] ?? ''));
                return ["邮件主题：{$old} → {$new}", "email subject: {$old} → {$new}"];
            case 'emailBody':
                return ['邮件正文已更新', 'email body updated'];
            case 'recipientType':
                $old = self::emailTemplateRecipientLabelZh($beforeState['recipientType'] ?? '');
                $new = self::emailTemplateRecipientLabelZh($afterState['recipientType'] ?? '');
                $oldEn = self::emailTemplateRecipientLabelEn($beforeState['recipientType'] ?? '');
                $newEn = self::emailTemplateRecipientLabelEn($afterState['recipientType'] ?? '');
                return ["收件人类型：{$old} → {$new}", "recipient type: {$oldEn} → {$newEn}"];
            case 'description':
                return ['描述已更新', 'description updated'];
            case 'variables':
                $beforeCount = count($beforeState['variables'] ?? []);
                $afterCount = count($afterState['variables'] ?? []);
                return [
                    "模板变量：{$beforeCount} → {$afterCount} 个",
                    "template variables: {$beforeCount} → {$afterCount}",
                ];
            case 'isActive':
                $enabled = !empty($afterState['isActive']);
                return $enabled ? ['状态：已启用', 'status: enabled'] : ['状态：已停用', 'status: disabled'];
            default:
                return ['', ''];
        }
    }

    private static function emailTemplateRecipientLabelZh($value) {
        $map = [
            'client' => '客户',
            'admin' => '管理员',
            'both' => '客户与管理员',
        ];
        $value = trim((string) $value);
        return $map[$value] ?? ($value !== '' ? $value : '-');
    }

    private static function emailTemplateRecipientLabelEn($value) {
        $map = [
            'client' => 'client',
            'admin' => 'admin',
            'both' => 'client and admin',
        ];
        $value = trim((string) $value);
        return $map[$value] ?? ($value !== '' ? $value : '-');
    }

    // --- 平台设置（log_system / platform_settings）---

    private static function platformSettingsGroupDisplayName(array $state) {
        $label = trim((string) ($state['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }
        $name = trim((string) ($state['name'] ?? ''));
        return $name !== '' ? $name : '交易组';
    }

    private static function platformSettingsGroupDisplayNameEn(array $state) {
        $label = trim((string) ($state['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }
        $name = trim((string) ($state['name'] ?? ''));
        return $name !== '' ? $name : 'trading group';
    }

    private static function platformSettingsPlatformLabel(array $state) {
        $name = trim((string) ($state['platformName'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $key = trim((string) ($state['platformKey'] ?? ''));
        return $key !== '' ? $key : '平台';
    }

    private static function platformSettingsPlatformLabelEn(array $state) {
        $name = trim((string) ($state['platformName'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $key = trim((string) ($state['platformKey'] ?? ''));
        return $key !== '' ? $key : 'platform';
    }

    private static function platformSettingsPasswordModeLabelZh($mode) {
        return trim((string) $mode) === 'manual' ? '手动' : '随机';
    }

    private static function platformSettingsPasswordModeLabelEn($mode) {
        return trim((string) $mode) === 'manual' ? 'manual' : 'random';
    }

    /**
     * @param string[] $changed
     * @return array{0:string,1:string}|null
     */
    public static function platformSettingsAccountUpdateSuccessDiff(array $beforeState, array $afterState, array $changed) {
        if (empty($changed)) {
            return null;
        }
        $platformZh = self::platformSettingsPlatformLabel($afterState);
        $platformEn = self::platformSettingsPlatformLabelEn($afterState);
        $partsZh = [];
        $partsEn = [];
        foreach ($changed as $key) {
            if ($key === 'accountLimit') {
                $old = (int) ($beforeState['accountLimit'] ?? 0);
                $new = (int) ($afterState['accountLimit'] ?? 0);
                $partsZh[] = "账户上限 {$old}→{$new}";
                $partsEn[] = "account limit {$old}→{$new}";
            } elseif ($key === 'passwordMode') {
                $oldZh = self::platformSettingsPasswordModeLabelZh($beforeState['passwordMode'] ?? '');
                $newZh = self::platformSettingsPasswordModeLabelZh($afterState['passwordMode'] ?? '');
                $oldEn = self::platformSettingsPasswordModeLabelEn($beforeState['passwordMode'] ?? '');
                $newEn = self::platformSettingsPasswordModeLabelEn($afterState['passwordMode'] ?? '');
                $partsZh[] = "默认开户密码模式 {$oldZh}→{$newZh}";
                $partsEn[] = "password mode {$oldEn}→{$newEn}";
            }
        }
        if (empty($partsZh)) {
            return null;
        }
        return [
            "已更新 {$platformZh} 开户设置：" . implode('；', $partsZh),
            'Updated ' . $platformEn . ' account settings: ' . implode('; ', $partsEn),
        ];
    }

    /**
     * @param array<string,mixed> $result
     * @return array{0:string,1:string}
     */
    public static function platformSettingsGroupSyncSuccess($platformKey, array $result) {
        $platform = trim((string) $platformKey);
        $synced = (int) ($result['synced'] ?? 0);
        $updated = (int) ($result['updated'] ?? 0);
        $total = (int) ($result['total'] ?? 0);
        return [
            "已同步 {$platform} 交易组：新增 {$synced}、更新 {$updated}，共 {$total} 组",
            "Synced {$platform} trading groups: {$synced} added, {$updated} updated, {$total} total",
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function platformSettingsGroupSetDefaultSuccess(array $groupState, $platformKey) {
        $nameZh = self::platformSettingsGroupDisplayName($groupState);
        $nameEn = self::platformSettingsGroupDisplayNameEn($groupState);
        $platform = trim((string) $platformKey);
        return [
            "已将交易组「{$nameZh}」设为 {$platform} 默认组",
            'Set trading group "' . $nameEn . '" as default for ' . $platform,
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function platformSettingsGroupRemoveDefaultSuccess(array $groupState, $platformKey) {
        $nameZh = self::platformSettingsGroupDisplayName($groupState);
        $nameEn = self::platformSettingsGroupDisplayNameEn($groupState);
        $platform = trim((string) $platformKey);
        return [
            "已取消交易组「{$nameZh}」在 {$platform} 的默认组",
            'Removed default flag from trading group "' . $nameEn . '" on ' . $platform,
        ];
    }

    /**
     * @param string[] $changed label|unit|scale
     * @return array{0:string,1:string}|null
     */
    public static function platformSettingsGroupEditSuccessDiff(array $beforeState, array $afterState, array $changed) {
        if (empty($changed)) {
            return null;
        }
        $nameZh = self::platformSettingsGroupDisplayName($afterState);
        $nameEn = self::platformSettingsGroupDisplayNameEn($afterState);
        $partsZh = [];
        $partsEn = [];
        foreach ($changed as $key) {
            if ($key === 'label') {
                $old = trim((string) ($beforeState['label'] ?? ''));
                $new = trim((string) ($afterState['label'] ?? ''));
                $partsZh[] = "标签 {$old}→{$new}";
                $partsEn[] = 'label ' . ($old !== '' ? $old : '-') . '→' . ($new !== '' ? $new : '-');
            } elseif ($key === 'unit') {
                $old = trim((string) ($beforeState['unit'] ?? ''));
                $new = trim((string) ($afterState['unit'] ?? ''));
                $partsZh[] = '单位 ' . ($old !== '' ? $old : '空') . '→' . ($new !== '' ? $new : '空');
                $partsEn[] = 'unit ' . ($old !== '' ? $old : 'empty') . '→' . ($new !== '' ? $new : 'empty');
            } elseif ($key === 'scale') {
                $old = trim((string) ($beforeState['scale'] ?? ''));
                $new = trim((string) ($afterState['scale'] ?? ''));
                $partsZh[] = '精度 ' . ($old !== '' ? $old : '空') . '→' . ($new !== '' ? $new : '空');
                $partsEn[] = 'scale ' . ($old !== '' ? $old : 'empty') . '→' . ($new !== '' ? $new : 'empty');
            }
        }
        if (empty($partsZh)) {
            return null;
        }
        return [
            "已更新交易组「{$nameZh}」：" . implode('；', $partsZh),
            'Updated trading group "' . $nameEn . '": ' . implode('; ', $partsEn),
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function platformSettingsLeverageCreateSuccess(array $state) {
        $platform = self::platformSettingsPlatformLabelEn($state);
        $value = trim((string) ($state['leverageValue'] ?? ''));
        $label = trim((string) ($state['displayLabel'] ?? ''));
        $labelPart = $label !== '' ? "（{$label}）" : '';
        $labelPartEn = $label !== '' ? ' (' . $label . ')' : '';
        return [
            "已新增 {$platform} 杠杆 {$value}{$labelPart}",
            'Added ' . $platform . ' leverage ' . $value . $labelPartEn,
        ];
    }

    /**
     * @param string[] $changed
     * @return array{0:string,1:string}|null
     */
    public static function platformSettingsLeverageUpdateSuccessDiff(array $beforeState, array $afterState, array $changed) {
        if (empty($changed)) {
            return null;
        }
        $value = trim((string) ($afterState['leverageValue'] ?? ''));
        $label = trim((string) ($afterState['displayLabel'] ?? ''));
        $headZh = $value !== '' ? $value : self::platformSettingsPlatformLabel($afterState);
        $headEn = $value !== '' ? $value : self::platformSettingsPlatformLabelEn($afterState);
        if ($label !== '') {
            $headZh .= "（{$label}）";
            $headEn .= ' (' . $label . ')';
        }
        $partsZh = [];
        $partsEn = [];
        foreach ($changed as $key) {
            if ($key === 'leverageValue') {
                $old = trim((string) ($beforeState['leverageValue'] ?? ''));
                $new = trim((string) ($afterState['leverageValue'] ?? ''));
                $partsZh[] = "杠杆值 {$old}→{$new}";
                $partsEn[] = 'leverage value ' . $old . '→' . $new;
            } elseif ($key === 'displayLabel') {
                $old = trim((string) ($beforeState['displayLabel'] ?? ''));
                $new = trim((string) ($afterState['displayLabel'] ?? ''));
                $partsZh[] = "显示名 {$old}→{$new}";
                $partsEn[] = 'display label ' . ($old !== '' ? $old : '-') . '→' . ($new !== '' ? $new : '-');
            } elseif ($key === 'riskNote') {
                $partsZh[] = '风险提示已更新';
                $partsEn[] = 'risk note updated';
            } elseif ($key === 'displayOrder') {
                $old = (int) ($beforeState['displayOrder'] ?? 0);
                $new = (int) ($afterState['displayOrder'] ?? 0);
                $partsZh[] = "排序 {$old}→{$new}";
                $partsEn[] = "display order {$old}→{$new}";
            }
        }
        if (empty($partsZh)) {
            return null;
        }
        return [
            "已更新杠杆 {$headZh}：" . implode('；', $partsZh),
            'Updated leverage ' . $headEn . ': ' . implode('; ', $partsEn),
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function platformSettingsLeverageToggleSuccess(array $state, $enabled) {
        $value = trim((string) ($state['leverageValue'] ?? ''));
        $label = trim((string) ($state['displayLabel'] ?? ''));
        $headZh = $value !== '' ? $value : '杠杆';
        $headEn = $value !== '' ? $value : 'leverage';
        if ($label !== '') {
            $headZh .= "（{$label}）";
            $headEn .= ' (' . $label . ')';
        }
        if ($enabled) {
            return ["已启用杠杆 {$headZh}", 'Enabled leverage ' . $headEn];
        }
        return ["已停用杠杆 {$headZh}", 'Disabled leverage ' . $headEn];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function platformSettingsLeverageDeleteSuccess(array $state) {
        $value = trim((string) ($state['leverageValue'] ?? ''));
        $label = trim((string) ($state['displayLabel'] ?? ''));
        $headZh = $value !== '' ? $value : '杠杆';
        $headEn = $value !== '' ? $value : 'leverage';
        if ($label !== '') {
            $headZh .= "（{$label}）";
            $headEn .= ' (' . $label . ')';
        }
        return ["已删除杠杆 {$headZh}", 'Deleted leverage ' . $headEn];
    }

    public static function platformSettingsAccountUpdateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新平台开户设置失败',
            'Failed to update platform account settings',
            $apiMessageEn
        );
    }

    public static function platformSettingsGroupSyncFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '同步交易组失败',
            'Failed to sync trading groups',
            $apiMessageEn
        );
    }

    public static function platformSettingsGroupDefaultFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新默认交易组失败',
            'Failed to update default trading group',
            $apiMessageEn
        );
    }

    public static function platformSettingsGroupEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新交易组失败',
            'Failed to update trading group',
            $apiMessageEn
        );
    }

    public static function platformSettingsLeverageCreateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '新增杠杆失败',
            'Failed to create leverage',
            $apiMessageEn
        );
    }

    public static function platformSettingsLeverageUpdateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新杠杆失败',
            'Failed to update leverage',
            $apiMessageEn
        );
    }

    public static function platformSettingsLeverageToggleFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '启停杠杆失败',
            'Failed to toggle leverage',
            $apiMessageEn
        );
    }

    public static function platformSettingsLeverageDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '删除杠杆失败',
            'Failed to delete leverage',
            $apiMessageEn
        );
    }

    public static function clientTicketStatusUpdateSuccess($ticketTitle, $clientDisplayName, $status) {
        $title = trim((string) $ticketTitle);
        if ($title === '') {
            $title = '—';
        }
        $client = trim((string) $clientDisplayName);
        if ($client === '') {
            $client = '—';
        }
        $isResolved = strtolower(trim((string) $status)) === 'resolved';
        if ($isResolved) {
            $zh = "将工单「{$title}」（客户：{$client}）标记为已解决";
            $en = "Marked ticket \"{$title}\" (client: {$client}) as resolved";
        } else {
            $zh = "将工单「{$title}」（客户：{$client}）重新标记为未解决";
            $en = "Reopened ticket \"{$title}\" (client: {$client})";
        }
        return [$zh, $en];
    }

    public static function clientTicketStatusUpdateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新工单状态失败',
            'Failed to update ticket status',
            $apiMessageEn
        );
    }
}
