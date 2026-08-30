<?php
/**
 * 操作日志详情 — 跨模块共用格式化与映射工具
 */

class OperationLogTextHelpers {
    /** 批量详情中 ID 列表最大字符数，超出则截断 */
    public const MAX_ID_LIST_CHARS = 8000;

    public static function entityLabelZh($subModuleKey) {
        if ($subModuleKey === 'clients_list') {
            return '客户';
        }
        if ($subModuleKey === 'ib_list') {
            return 'IB';
        }
        return 'Lead';
    }

    public static function entityLabelEn($subModuleKey) {
        if ($subModuleKey === 'clients_list') {
            return 'client';
        }
        if ($subModuleKey === 'ib_list') {
            return 'IB partner';
        }
        return 'lead';
    }

    public static function formatIbPartnerDisplayName(array $row) {
        $name = trim((string) ($row['ibName'] ?? $row['companyName'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $code = trim((string) ($row['ibCode'] ?? ''));
        return $code !== '' ? $code : 'IB';
    }

    /**
     * @param int[]|string[] $ids
     */
    public static function formatClientIdList(array $ids) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        })));
        if (empty($ids)) {
            return '';
        }
        $str = implode(', ', $ids);
        if (strlen($str) <= self::MAX_ID_LIST_CHARS) {
            return $str;
        }
        $truncated = mb_substr($str, 0, self::MAX_ID_LIST_CHARS, 'UTF-8');
        return $truncated . '…（共 ' . count($ids) . ' 个）';
    }

    /**
     * @param string[] $ids
     */
    public static function formatTransactionIdList(array $ids) {
        $ids = array_values(array_unique(array_filter(array_map(function ($id) {
            return trim((string) $id);
        }, $ids), function ($id) {
            return $id !== '';
        })));
        if (empty($ids)) {
            return '';
        }
        $str = implode(', ', $ids);
        if (strlen($str) <= self::MAX_ID_LIST_CHARS) {
            return $str;
        }
        $truncated = mb_substr($str, 0, self::MAX_ID_LIST_CHARS, 'UTF-8');
        return $truncated . '…（共 ' . count($ids) . ' 个）';
    }

    public static function formatClientDisplayName(array $row) {
        $name = trim((string) (($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? '')));
        if ($name !== '') {
            return $name;
        }
        return trim((string) ($row['email'] ?? ''));
    }

    /**
     * @param array<string,array{old:mixed,new:mixed}> $changes
     */
    public static function formatChangeSummaryZh(array $changes) {
        $parts = [];
        foreach ($changes as $field => $pair) {
            $old = $pair['old'] ?? '';
            $new = $pair['new'] ?? '';
            $parts[] = $field . '：' . $old . '→' . $new;
        }
        return implode('；', $parts);
    }

    /**
     * @param array<string,array{old:mixed,new:mixed}> $changes
     */
    public static function formatChangeSummaryEn(array $changes) {
        $parts = [];
        foreach ($changes as $field => $pair) {
            $old = $pair['old'] ?? '';
            $new = $pair['new'] ?? '';
            $parts[] = $field . ': ' . $old . ' -> ' . $new;
        }
        return implode('; ', $parts);
    }

    public static function notesSuffixZh($notes) {
        $notes = trim((string) $notes);
        return $notes !== '' ? "；备注：{$notes}" : '';
    }

    public static function notesSuffixEn($notes) {
        $notes = trim((string) $notes);
        return $notes !== '' ? "; notes: {$notes}" : '';
    }

    public const GENERIC_FAILURE_REASON_ZH = '系统处理失败，请稍后重试';
    public const GENERIC_FAILURE_REASON_EN = 'System processing failed. Please try again later.';

    /**
     * 与 Response::validationError 默认 message 生成规则一致
     */
    public static function validationErrorsToMessage(array $errors) {
        $messages = [];
        foreach ($errors as $errorMessages) {
            if (is_array($errorMessages)) {
                $messages = array_merge($messages, $errorMessages);
            } else {
                $messages[] = $errorMessages;
            }
        }
        return !empty($messages) ? implode('; ', $messages) : 'Validation Failed';
    }

    /**
     * @return array{0:string,1:string} [detailZh, detailEn]
     */
    public static function formatOperationFailure($prefixZh, $prefixEn, $apiMessageEn) {
        list($reasonZh, $reasonEn) = self::resolveUserFacingMessage($apiMessageEn);
        return [
            trim((string) $prefixZh) . '：' . $reasonZh,
            trim((string) $prefixEn) . ': ' . $reasonEn,
        ];
    }

    /**
     * 将 API 英文 message 转为日志用的中英文原因（友好翻译；技术信息兜底）
     *
     * @return array{0:string,1:string} [reasonZh, reasonEn]
     */
    public static function resolveUserFacingMessage($apiMessageEn) {
        $messageEn = trim((string) $apiMessageEn);
        if ($messageEn === '') {
            return [self::GENERIC_FAILURE_REASON_ZH, self::GENERIC_FAILURE_REASON_EN];
        }

        if (strpos($messageEn, '; ') !== false) {
            $parts = array_map('trim', explode('; ', $messageEn));
            $zhParts = [];
            $enParts = [];
            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }
                list($zh, $en) = self::resolveUserFacingMessage($part);
                $zhParts[] = $zh;
                $enParts[] = $en;
            }
            if (!empty($zhParts)) {
                return [implode('；', $zhParts), implode('; ', $enParts)];
            }
            return [self::GENERIC_FAILURE_REASON_ZH, self::GENERIC_FAILURE_REASON_EN];
        }

        if (self::isTechnicalErrorMessage($messageEn)) {
            return [self::GENERIC_FAILURE_REASON_ZH, self::GENERIC_FAILURE_REASON_EN];
        }

        $exact = self::apiMessageCatalog();
        if (isset($exact[$messageEn])) {
            return $exact[$messageEn];
        }

        if (preg_match(
            '/^Bulk approval failed at withdrawal ID (\d+): (.+)$/i',
            $messageEn,
            $matches
        )) {
            $withdrawalId = $matches[1];
            $innerEn = trim($matches[2]);
            list($innerZh, $innerEnResolved) = self::resolveUserFacingMessage($innerEn);
            return [
                "出金 ID {$withdrawalId} 批量审批失败：{$innerZh}",
                "Bulk approval failed at withdrawal ID {$withdrawalId}: {$innerEnResolved}",
            ];
        }

        if (preg_match(
            '/^Bulk approval failed at deposit ID (\d+): (.+)$/i',
            $messageEn,
            $matches
        )) {
            $depositId = $matches[1];
            $innerEn = trim($matches[2]);
            list($innerZh, $innerEnResolved) = self::resolveUserFacingMessage($innerEn);
            return [
                "入金 ID {$depositId} 批量审批失败：{$innerZh}",
                "Bulk approval failed at deposit ID {$depositId}: {$innerEnResolved}",
            ];
        }

        if (preg_match(
            '/^Bulk approval failed at transfer ID (\d+): (.+)$/i',
            $messageEn,
            $matches
        )) {
            $transferId = $matches[1];
            $innerEn = trim($matches[2]);
            list($innerZh, $innerEnResolved) = self::resolveUserFacingMessage($innerEn);
            return [
                "内部转账 ID {$transferId} 批量审批失败：{$innerZh}",
                "Bulk approval failed at transfer ID {$transferId}: {$innerEnResolved}",
            ];
        }

        if (preg_match(
            '/^Cannot delete role with (\d+) active users$/',
            $messageEn,
            $matches
        )) {
            $count = $matches[1];
            return [
                "该角色下仍有 {$count} 名活跃用户，无法删除",
                "Cannot delete role with {$count} active users",
            ];
        }

        if (preg_match('/^Submission (\d+) not found$/i', $messageEn, $matches)) {
            return [
                "未找到 KYC 提交 #{$matches[1]}",
                "Submission {$matches[1]} not found",
            ];
        }

        if (preg_match("/^Field '([^']+)' is required$/i", $messageEn, $matches)) {
            $field = $matches[1];
            $fieldLabels = [
                'templateId' => ['模板 ID', 'Template ID'],
                'documentTitle' => ['文档标题', 'Document title'],
                'documentContent' => ['文档内容', 'Document content'],
            ];
            if (isset($fieldLabels[$field])) {
                return [
                    $fieldLabels[$field][0] . '不能为空',
                    $fieldLabels[$field][1] . ' is required',
                ];
            }
            return ["{$field} 不能为空", "Field '{$field}' is required"];
        }

        if (preg_match(
            '/^Only inactive templates can be deleted\. Current status is "(.+)"\./i',
            $messageEn,
            $matches
        )) {
            $status = $matches[1];
            return [
                "仅可删除已停用模板，当前状态为「{$status}」",
                "Only inactive templates can be deleted. Current status is \"{$status}\".",
            ];
        }

        if (preg_match('/^(.+) must be a boolean value$/i', $messageEn, $matches)) {
            $field = trim($matches[1]);
            $fieldLabels = [
                'salesManagerNotifications' => ['销售经理通知', 'sales manager notifications'],
                'withdrawalOtpRequired' => ['出金 OTP 验证', 'withdrawal OTP verification'],
                'requireVerifiedWalletOnly' => ['仅允许已验证钱包出金', 'verified wallet only for withdrawals'],
                'requireWithdrawalVerification' => ['出金账户验证', 'withdrawal account verification'],
                'autoRejectUnverified' => ['自动拒绝未验证出金', 'auto-reject unverified withdrawals'],
            ];
            if (isset($fieldLabels[$field])) {
                return [
                    $fieldLabels[$field][0] . '须为布尔值',
                    $fieldLabels[$field][1] . ' must be a boolean value',
                ];
            }
            return ["{$field} 须为布尔值", "{$field} must be a boolean value"];
        }

        $transactionSettingsFailurePrefixes = [
            'Failed to update gateway settings' => ['支付网关配置更新失败', 'Failed to update gateway settings'],
            'Failed to soft delete gateway' => ['支付网关删除失败', 'Failed to soft delete gateway'],
            'Failed to update gateway display content' => ['支付网关展示文案更新失败', 'Failed to update gateway display content'],
            'Failed to update display content' => ['交易展示内容更新失败', 'Failed to update display content'],
            'Failed to update gateway fee limits' => ['支付网关限额更新失败', 'Failed to update gateway fee limits'],
            'Failed to update gateway fee settings' => ['支付网关手续费更新失败', 'Failed to update gateway fee settings'],
            'Failed to create payment support question' => ['支付支持问题新增失败', 'Failed to create payment support question'],
            'Failed to update payment support question' => ['支付支持问题编辑失败', 'Failed to update payment support question'],
            'Failed to delete payment support question' => ['支付支持问题删除失败', 'Failed to delete payment support question'],
            'Failed to update notification setting' => ['通知设置更新失败', 'Failed to update notification setting'],
            'Failed to update security settings' => ['安全设置更新失败', 'Failed to update security settings'],
            'Failed to update auto approval rules' => ['自动审批规则更新失败', 'Failed to update auto approval rules'],
            'Failed to create exchange rate' => ['汇率新建失败', 'Failed to create exchange rate'],
            'Failed to update exchange rate status' => ['汇率状态更新失败', 'Failed to update exchange rate status'],
            'Failed to update exchange rate' => ['汇率编辑失败', 'Failed to update exchange rate'],
            'Failed to delete exchange rate' => ['汇率删除失败', 'Failed to delete exchange rate'],
        ];
        foreach ($transactionSettingsFailurePrefixes as $prefixEn => $labels) {
            if (stripos($messageEn, $prefixEn) === 0) {
                $suffix = trim(substr($messageEn, strlen($prefixEn)));
                $suffix = ltrim($suffix, ':');
                if ($suffix === '') {
                    return [$labels[0], $labels[1]];
                }
                list($innerZh, $innerEn) = self::resolveUserFacingMessage($suffix);
                return [
                    $labels[0] . '：' . $innerZh,
                    $labels[1] . ': ' . $innerEn,
                ];
            }
        }

        if (preg_match(
            '/^Cannot delete rule: (\d+) IB partner\(s\) are using this rule$/i',
            $messageEn,
            $matches
        )) {
            $count = (int) $matches[1];
            return [
                "无法删除：仍有 {$count} 个 IB 正在使用此佣金规则",
                "Cannot delete rule: {$count} IB partner(s) are using this rule",
            ];
        }

        if (preg_match(
            '/^Cannot delete tier: (\d+) IB partner\(s\) are using this tier level$/i',
            $messageEn,
            $matches
        )) {
            $count = (int) $matches[1];
            return [
                "无法删除：仍有 {$count} 个 IB 正在使用此等级",
                "Cannot delete tier: {$count} IB partner(s) are using this tier level",
            ];
        }

        if (preg_match('/^Unsupported platform: (.+)$/i', $messageEn, $matches)) {
            $platform = trim($matches[1]);
            return [
                "不支持的交易平台：{$platform}",
                "Unsupported platform: {$platform}",
            ];
        }

        if (preg_match('/^Platform is not enabled in config: (.+)$/i', $messageEn, $matches)) {
            $platform = trim($matches[1]);
            return [
                "配置中未启用该平台：{$platform}",
                "Platform is not enabled in config: {$platform}",
            ];
        }

        if (preg_match('/^Trading platform not found for: (.+)$/i', $messageEn, $matches)) {
            $platform = trim($matches[1]);
            return [
                "未找到交易平台：{$platform}",
                "Trading platform not found for: {$platform}",
            ];
        }

        if (preg_match('/^Failed to connect myswoole: (.+)$/i', $messageEn, $matches)) {
            return [
                '无法连接后台同步服务',
                'Failed to connect background sync service: ' . trim($matches[1]),
            ];
        }

        return [self::GENERIC_FAILURE_REASON_ZH, $messageEn !== '' ? $messageEn : self::GENERIC_FAILURE_REASON_EN];
    }

    public static function isTechnicalErrorMessage($message) {
        $message = trim((string) $message);
        if ($message === '') {
            return false;
        }

        $needles = [
            'SQLSTATE',
            'Exception',
            ' at line ',
            '.php',
            'Failed to approve withdrawal',
            'Failed to reject withdrawal',
            'Failed to approve deposit',
            'Failed to reject deposit',
            'Failed to approve:',
            'Failed to reject:',
            'Failed to request resubmission:',
            'Failed to bulk assign:',
            'Failed to bulk approve:',
            'Sync failed:',
            'Failed to create template:',
            'Failed to update template:',
            'Failed to delete template:',
            'Failed to clone template:',
            'Failed to update countries:',
            'Failed to update third-party binding:',
            'Failed to create question:',
            'Failed to update question:',
            'Failed to delete question:',
            'Failed to duplicate question:',
            'Failed to create category:',
            'Failed to update category:',
            'Failed to delete category:',
            'Failed to create rule:',
            'Failed to update rule:',
            'Failed to delete rule:',
            'Failed to create document:',
            'Failed to update document:',
            'Failed to delete document:',
            'Failed to duplicate document:',
            'Failed to create commission rule',
            'Failed to duplicate rule',
            'Failed to update products',
            'Failed to create client:',
            'Failed to assign clients:',
            'Failed to create notification:',
            'Failed to create trading account:',
            'Failed to call target platform deposit',
            'Failed to rollback',
            'Failed to update gateway settings',
            'Failed to soft delete gateway',
            'Failed to update gateway display content',
            'Failed to update display content',
            'Failed to update gateway fee limits',
            'Failed to update gateway fee settings',
            'Failed to create payment support question',
            'Failed to update payment support question',
            'Failed to delete payment support question',
            'Failed to update notification setting',
            'Failed to update security settings',
            'Failed to update auto approval rules',
            'Failed to create exchange rate',
            'Failed to update exchange rate status',
            'Failed to update exchange rate',
            'Failed to delete exchange rate',
            'Invalid withdrawal id',
            'Invalid deposit id',
            'Invalid withdrawal rejection mode',
            'FinancePro',
            'endpoint not configured',
            'unknown error',
        ];
        foreach ($needles as $needle) {
            if (stripos($message, $needle) !== false) {
                return true;
            }
        }

        if (preg_match('/^Failed to send email:\s*.+$/i', $message)) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    private static function apiMessageCatalog() {
        return array_merge(
            self::transactionApiMessageCatalog(),
            self::transactionSettingsApiMessageCatalog(),
            self::clientApiMessageCatalog(),
            self::salesApiMessageCatalog(),
            self::ibApiMessageCatalog(),
            self::ibSettingsApiMessageCatalog(),
            self::pointsMallSettingsApiMessageCatalog(),
            self::pointsMallProductsApiMessageCatalog(),
            self::pointsMallCategoriesApiMessageCatalog(),
            self::pointsMallRedemptionsApiMessageCatalog(),
            self::pointsMallLedgerApiMessageCatalog(),
            self::systemAccountsApiMessageCatalog(),
            self::systemRolesApiMessageCatalog(),
            self::systemLoginPageSettingsApiMessageCatalog(),
            self::systemLogSettingsApiMessageCatalog(),
            self::systemEmailSettingsApiMessageCatalog(),
            self::systemEmailTemplatesApiMessageCatalog()
        );
    }

    /**
     * 系统设置 — 账户管理 API message → [中文, 英文友好句]
     *
     * @return array<string,array{0:string,1:string}>
     */
    private static function systemAccountsApiMessageCatalog() {
        return [
            'Invalid JSON body' => ['请求数据格式无效', 'Invalid JSON body'],
            'User not found' => ['未找到该管理员', 'Admin user not found'],
            'Email already in use' => ['邮箱已被使用', 'Email already in use'],
            'Password must be at least 8 characters' => [
                '密码至少 8 位',
                'Password must be at least 8 characters',
            ],
            'username is required' => ['用户名为必填项', 'Username is required'],
            'email is required' => ['邮箱为必填项', 'Email is required'],
            'password is required' => ['密码为必填项', 'Password is required'],
            'fullName is required' => ['姓名为必填项', 'Full name is required'],
            'roleId is required' => ['角色为必填项', 'Role is required'],
            'username must be at least 3 characters' => ['用户名至少 3 个字符', 'Username must be at least 3 characters'],
            'password must be at least 8 characters' => ['密码至少 8 个字符', 'Password must be at least 8 characters'],
            'username already exists' => ['用户名已存在', 'Username already exists'],
            'email already exists' => ['邮箱已存在', 'Email already exists'],
            'email must be a valid email' => ['邮箱格式无效', 'Email must be a valid email address'],
            'roleId must be numeric' => ['角色无效', 'Role must be numeric'],
            'departmentId must be numeric' => ['部门无效', 'Department must be numeric'],
            'positionId must be numeric' => ['职位无效', 'Position must be numeric'],
            'Validation Failed' => ['请求参数校验失败', 'Validation failed'],
        ];
    }

    /**
     * 系统设置 — 角色管理 API message → [中文, 英文友好句]（各自纯语言，不混用）
     *
     * @return array<string,array{0:string,1:string}>
     */
    private static function systemRolesApiMessageCatalog() {
        return [
            'Invalid JSON body' => ['请求数据格式无效', 'Invalid JSON body'],
            'Role not found' => ['未找到该角色', 'Role not found'],
            'roleName is required' => ['角色名称为必填项', 'Role name is required'],
            'Cannot modify Super Admin role' => ['无法修改超级管理员角色', 'Cannot modify Super Admin role'],
            'Cannot modify system role' => ['无法修改系统角色', 'Cannot modify system role'],
            'Cannot delete system role' => ['无法删除系统角色', 'Cannot delete system role'],
            'Cannot modify Super Admin permissions' => ['无法修改超级管理员权限', 'Cannot modify Super Admin permissions'],
            'Permission IDs must be an array' => ['权限 ID 须为数组', 'Permission IDs must be an array'],
            'permissionIds is required' => ['权限列表为必填项', 'Permission IDs are required'],
            'Failed to update permissions' => ['更新权限失败', 'Failed to update permissions'],
            'Validation Failed' => ['请求参数校验失败', 'Validation failed'],
        ];
    }

    /**
     * 系统设置 — 登录页设置 API message → [中文, 英文友好句]
     *
     * @return array<string,array{0:string,1:string}>
     */
    private static function systemLoginPageSettingsApiMessageCatalog() {
        return [
            'Invalid JSON body' => ['请求数据格式无效', 'Invalid JSON body'],
            'Invalid data format' => ['数据格式无效', 'Invalid data format'],
            'No file uploaded' => ['未上传文件', 'No file uploaded'],
            'Form field not found' => ['未找到该表单字段', 'Form field not found'],
            'Mandatory system fields cannot be edited here' => ['系统必填字段不可在此编辑', 'Mandatory system fields cannot be edited here'],
            'Legal document not found' => ['未找到该法律文档', 'Legal document not found'],
            'A legal document with the same title and version already exists.' => [
                '已存在相同标题与版本的法律文档',
                'A legal document with the same title and version already exists',
            ],
            'Language pack not found' => ['未找到该语言包', 'Language pack not found'],
            'Language pack already exists. Use update instead.' => [
                '语言包已存在，请使用更新',
                'Language pack already exists. Use update instead',
            ],
            'languageCode is required' => ['语言代码为必填项', 'Language code is required'],
            'level is required' => ['密码强度等级为必填项', 'Password strength level is required'],
            'fieldId is required' => ['字段 ID 为必填项', 'Field ID is required'],
            'fieldName is required' => ['字段名称为必填项', 'Field name is required'],
            'fieldType is required' => ['字段类型为必填项', 'Field type is required'],
            'documentType is required' => ['文档类型为必填项', 'Document type is required'],
            'title is required' => ['标题为必填项', 'Title is required'],
            'content is required' => ['内容为必填项', 'Content is required'],
            'Invalid file type. Only PNG, JPG, and SVG are allowed.' => [
                '文件类型无效，仅支持 PNG、JPG、SVG',
                'Invalid file type. Only PNG, JPG, and SVG are allowed',
            ],
            'File size must be less than 5MB.' => ['文件大小须小于 5MB', 'File size must be less than 5MB'],
            'Failed to upload file.' => ['文件上传失败', 'Failed to upload file'],
            'Validation Failed' => ['请求参数校验失败', 'Validation failed'],
        ];
    }

    /**
     * 系统设置 — 日志设置 API message → [中文, 英文友好句]
     *
     * @return array<string,array{0:string,1:string}>
     */
    private static function systemLogSettingsApiMessageCatalog() {
        return [
            'id is required' => ['模块 ID 为必填项', 'Module ID is required'],
            'ids must be a non-empty array' => ['模块 ID 列表不能为空', 'Module ID list must be a non-empty array'],
            'ids must contain valid positive integers' => [
                '模块 ID 须为正整数',
                'Module IDs must be valid positive integers',
            ],
            'No records updated' => ['未找到可更新的模块记录', 'No records updated'],
            'Validation Failed' => ['请求参数校验失败', 'Validation failed'],
        ];
    }

    /**
     * 系统设置 — 邮件设置 API message → [中文, 英文友好句]
     *
     * @return array<string,array{0:string,1:string}>
     */
    private static function systemEmailSettingsApiMessageCatalog() {
        return [
            'Invalid JSON body' => ['请求数据格式无效', 'Invalid JSON body'],
            'The templateIds field is required and must be an array' => [
                '模板 ID 列表为必填项且须为数组',
                'Template ID list is required and must be an array',
            ],
            'Failed to update settings' => ['更新邮件模板设置失败', 'Failed to update settings'],
            'Validation Failed' => ['请求参数校验失败', 'Validation failed'],
        ];
    }

    /**
     * 系统设置 — 邮件模板 API message → [中文, 英文友好句]
     *
     * @return array<string,array{0:string,1:string}>
     */
    private static function systemEmailTemplatesApiMessageCatalog() {
        return [
            'Invalid JSON body' => ['请求数据格式无效', 'Invalid JSON body'],
            'Template not found' => ['未找到该邮件模板', 'Template not found'],
            'Template key already exists' => ['模板 key 已存在', 'Template key already exists'],
            'Failed to update template status' => ['更新模板状态失败', 'Failed to update template status'],
            'Template not found after update' => ['更新后未找到模板', 'Template not found after update'],
            'Validation Failed' => ['请求参数校验失败', 'Validation failed'],
        ];
    }

    /**
     * 积分商城设置页 API message → [中文, 英文友好句]
     *
     * @return array<string,array{0:string,1:string}>
     */
    private static function pointsMallSettingsApiMessageCatalog() {
        return [
            'Description text too long' => ['说明内容过长', 'Description text too long'],
            'No fields to update' => ['未提交可更新字段', 'No fields to update'],
            'Invalid JSON body' => ['请求数据格式无效', 'Invalid JSON body'],
            'integralPanel object is required' => ['缺少积分配置数据', 'Integral configuration data is required'],
            'integralPanel JSON encode failed' => ['积分配置数据无法保存', 'Integral configuration could not be saved'],
            'integralPanel too large' => ['积分配置数据过大', 'Integral configuration data is too large'],
            'Redemption reminder requires both an active email template and at least one administrator, or leave both empty.' => [
                '兑换提醒须同时配置邮件模板与管理员，或全部留空',
                'Redemption reminder requires both an email template and administrators, or leave both empty',
            ],
            'The selected email template is not available or has been deactivated.' => [
                '所选邮件模板不可用或已停用',
                'The selected email template is not available or has been deactivated',
            ],
            'The selected template recipient type must be admin or both.' => [
                '所选邮件模板收件人类型须为管理员',
                'The selected template recipient type must be admin or both',
            ],
            'One or more administrators are not active or no longer exist' => [
                '部分通知管理员不存在或已停用',
                'One or more notification administrators are not active or no longer exist',
            ],
            'Settings not found' => ['未找到积分商城设置', 'Settings not found'],
            'Validation Failed' => ['请求参数校验失败', 'Validation failed'],
        ];
    }

    /**
     * 积分商城商品列表页 API message → [中文, 英文友好句]
     *
     * @return array<string,array{0:string,1:string}>
     */
    private static function pointsMallCategoriesApiMessageCatalog() {
        return [
            'Invalid JSON body' => ['请求数据格式无效', 'Invalid JSON body'],
            'id is required' => ['缺少分类 ID', 'Category id is required'],
            'enabled is required' => ['缺少启用状态', 'Enabled status is required'],
            'Category not found' => ['分类不存在', 'Category not found'],
            'nameZh, nameEn and sort are required' => ['分类名称与排序均为必填', 'Category names and sort are required'],
            'Invalid nameZh' => ['中文名称无效', 'Invalid Chinese name'],
            'Invalid nameEn' => ['英文名称无效', 'Invalid English name'],
            'Invalid sort' => ['排序无效', 'Invalid sort order'],
            'Sort must be between 1 and 999' => ['排序须在 1–999 之间', 'Sort must be between 1 and 999'],
            'Parent must be a top-level category' => ['上级分类须为一级分类', 'Parent must be a top-level category'],
            'Cannot set parent to self' => ['不能将上级设为自己', 'Cannot set parent to self'],
            'Cannot delete: subcategories exist' => ['存在子分类，无法删除', 'Cannot delete: subcategories exist'],
            'Failed to create category' => ['创建失败', 'Create failed'],
        ];
    }

    private static function pointsMallRedemptionsApiMessageCatalog() {
        return [
            'Invalid JSON body' => ['请求数据格式无效', 'Invalid JSON body'],
            'orderId is required' => ['缺少订单号', 'Order id is required'],
            'auditStatus is required' => ['缺少审核状态', 'Audit status is required'],
            'Invalid auditStatus' => ['审核状态无效', 'Invalid audit status'],
            'Redemption not found' => ['兑换记录不存在', 'Redemption not found'],
            'Invalid audit status transition' => ['审核状态变更不允许', 'Invalid audit status transition'],
            'Client not found' => ['客户不存在', 'Client not found'],
            'Insufficient points balance' => ['积分余额不足', 'Insufficient points balance'],
        ];
    }

    private static function pointsMallLedgerApiMessageCatalog() {
        return [
            'Invalid JSON body' => ['请求数据格式无效', 'Invalid JSON body'],
            'clientUserId is required' => ['缺少客户 ID', 'Client user id is required'],
            'Invalid pointsDirection' => ['积分方向无效', 'Invalid points direction'],
            'pointsValue must be a positive amount' => ['积分数量须为正数', 'Points value must be a positive amount'],
            'Invalid triggerKey' => ['触发类型无效', 'Invalid trigger type'],
            'remark is required' => ['备注为必填', 'Remark is required'],
            'remark max 50 characters' => ['备注最多 50 字', 'Remark max 50 characters'],
            'Client not found' => ['客户不存在', 'Client not found'],
            'Insufficient points balance' => ['积分余额不足', 'Insufficient points balance'],
            'Insert failed' => ['写入失败', 'Insert failed'],
        ];
    }

    private static function pointsMallProductsApiMessageCatalog() {
        return [
            'Invalid JSON body' => ['请求数据格式无效', 'Invalid JSON body'],
            'id is required' => ['缺少商品 ID', 'Product id is required'],
            'enabled is required' => ['缺少上架状态', 'Enabled status is required'],
            'Invalid id' => ['无效的商品 ID', 'Invalid product id'],
            'Product not found' => ['商品不存在', 'Product not found'],
            'nameZh and nameEn are required' => ['商品中英文名称均为必填', 'Chinese and English product names are required'],
            'nameZh and nameEn cannot be empty' => ['商品名称不能为空', 'Product names cannot be empty'],
            'Invalid paymentMode' => ['支付方案无效', 'Invalid payment mode'],
            'exchangePoints is required' => ['兑换积分为必填', 'Exchange points is required'],
            'Invalid exchangePoints' => ['兑换积分无效', 'Invalid exchange points'],
            'exchangeAmount is required for combo payment' => ['组合支付须填写组合金额', 'Combo amount is required for combo payment'],
            'Invalid exchangeAmount' => ['组合金额无效', 'Invalid combo amount'],
            'Invalid productType' => ['商品类型无效', 'Invalid product type'],
            'Invalid virtualCategory' => ['虚拟商品类别无效', 'Invalid virtual product category'],
            'virtualDepositAmount is required for real deposit' => ['真实入金须填写入金金额', 'Deposit amount is required for real deposit'],
            'Invalid virtualDepositAmount' => ['入金金额无效', 'Invalid deposit amount'],
            'Invalid categoryId' => ['分类无效', 'Invalid category'],
            'images must be an array' => ['商品图片格式无效', 'Product images must be an array'],
            'At most 9 images' => ['商品图片最多 9 张', 'At most 9 product images allowed'],
            'At least one image is required' => ['至少上传 1 张商品图片', 'At least one product image is required'],
            'Create failed' => ['创建失败', 'Create failed'],
        ];
    }

    /**
     * Sales 模块 API message → [中文, 英文友好句]
     *
     * @return array<string,array{0:string,1:string}>
     */
    private static function salesApiMessageCatalog() {
        return [
            'Invalid sales id' => ['无效的销售 ID', 'Invalid sales id'],
            'User is not a Sales or not found' => [
                '该用户不是销售或不存在',
                'User is not a Sales or not found',
            ],
            'This referral code is already in use by another Sales' => [
                '该推荐码已被其他销售使用',
                'This referral code is already in use by another Sales',
            ],
        ];
    }

    /**
     * IB 初审列表 API message → [中文, 英文友好句]
     *
     * @return array<string,array{0:string,1:string}>
     */
    private static function ibApiMessageCatalog() {
        return [
            'Invalid JSON body' => ['请求体格式无效', 'Invalid JSON body'],
            'Only IB in Pending Initial Review can be submitted' => [
                '仅待初审状态的 IB 可提交初审',
                'Only IB in Pending Initial Review can be submitted',
            ],
            'Client already has a pending invitation' => [
                '该客户已有待处理的邀请',
                'Client already has a pending invitation',
            ],
            'This user is already bound to an IB. Please unbind first before sending an invitation.' => [
                '该客户已绑定 IB，请先解绑再发送邀请',
                'This user is already bound to an IB. Please unbind first before sending an invitation.',
            ],
            'You can only invite clients bound to your sales account' => [
                '只能邀请已绑定到您销售账号的客户',
                'You can only invite clients bound to your sales account',
            ],
            'This client already has an IB partner record' => [
                '该客户已有 IB 伙伴记录',
                'This client already has an IB partner record',
            ],
            'Only approved IB partners can create an additional IB' => [
                '仅已通过的 IB 可创建额外 IB',
                'Only approved IB partners can create an additional IB',
            ],
            'IB partner is not linked to a client user' => [
                '该 IB 未关联客户账号',
                'IB partner is not linked to a client user',
            ],
            'You can only create an additional IB for your own account' => [
                '只能为自己的账号创建额外 IB',
                'You can only create an additional IB for your own account',
            ],
            'You do not have permission to create an additional IB' => [
                '无权限创建额外 IB',
                'You do not have permission to create an additional IB',
            ],
            'No authenticated user found' => [
                '未找到已登录用户',
                'No authenticated user found',
            ],
            'Only IB in Pending Risk Review can be submitted' => [
                '仅待风控审核状态的 IB 可提交风控审核',
                'Only IB in Pending Risk Review can be submitted',
            ],
            'Failed to save risk review' => [
                '保存风控审核失败',
                'Failed to save risk review',
            ],
            'Only IB in Pending Risk Review or Pending Final Review can be rejected' => [
                '仅待风控或待终审状态的 IB 可驳回',
                'Only IB in Pending Risk Review or Pending Final Review can be rejected',
            ],
        ];
    }

    /**
     * IB 设置页 API message → [中文, 英文友好句]
     *
     * @return array<string,array{0:string,1:string}>
     */
    private static function ibSettingsApiMessageCatalog() {
        return [
            'Document template not found' => ['未找到该 IB 文档', 'Document template not found'],
            'Tier level not found' => ['未找到该 IB 等级', 'Tier level not found'],
            'Tier level number already exists' => ['该等级序号已存在', 'Tier level number already exists'],
            'Commission rule not found' => ['未找到该佣金规则', 'Commission rule not found'],
            'Invalid rule type' => ['无效的规则类型', 'Invalid rule type'],
            'platformKey is required' => ['请选择交易平台', 'platformKey is required'],
            'trading_platforms_key is required' => ['请选择交易平台', 'trading_platforms_key is required'],
            'FinancePro get_security_symbol endpoint not configured' => [
                'FinancePro 同步接口未配置',
                'FinancePro get_security_symbol endpoint not configured',
            ],
            'Invalid FinancePro response: missing ResData' => [
                'FinancePro 返回数据无效：缺少 ResData',
                'Invalid FinancePro response: missing ResData',
            ],
            'Failed to create document: No ID returned' => [
                '创建文档失败：未返回有效记录',
                'Failed to create document: No ID returned',
            ],
            'Unauthorized' => ['未授权', 'Unauthorized'],
            'Validation Failed' => ['请求参数校验失败', 'Validation failed'],
            'Valid symbolId is required' => ['请选择品种', 'Please select a symbol'],
            'Symbol not found or disabled' => ['品种不存在或已停用', 'Symbol not found or is disabled'],
            'Exchange setting already exists for this symbol' => [
                '该品种的汇率配置已存在',
                'An exchange rate setting already exists for this symbol',
            ],
            'targetCurrency is required' => ['目标币种为必填项', 'Target currency is required'],
            'targetCurrency cannot be empty' => ['目标币种不能为空', 'Target currency cannot be empty'],
            'Invalid syncMode' => ['同步模式无效', 'Invalid sync mode'],
            'baseCurrency is required in manual mode' => [
                '手动模式下基准货币为必填项',
                'Base currency is required in manual mode',
            ],
            'exchangeRate must be greater than 0 in manual mode' => [
                '手动模式下汇率必须大于 0',
                'Exchange rate must be greater than 0 in manual mode',
            ],
            'Remarks must be at most 200 characters' => [
                '备注最多 200 个字符',
                'Remarks must be at most 200 characters',
            ],
            'Symbol exchange rate not found' => ['未找到该汇率配置', 'Exchange rate setting not found'],
            'Exchange rate setting not found' => ['未找到该汇率配置', 'Exchange rate setting not found'],
            'mode must be auto or manual' => [
                '页级同步模式必须为自动或手动',
                'Global sync mode must be auto or manual',
            ],
        ];
    }

    /**
     * Client 模块（Leads / Client List / IB List）API message → [中文, 英文友好句]
     *
     * @return array<string,array{0:string,1:string}>
     */
    private static function clientApiMessageCatalog() {
        return [
            'Lead not found' => ['未找到该 Lead', 'Lead not found'],
            'Client not found' => ['未找到该客户', 'Client not found'],
            'Client not found.' => ['未找到该客户', 'Client not found'],
            'No changes detected' => ['未检测到变更', 'No changes detected'],
            'Email already exists' => ['邮箱已被使用', 'Email already exists'],
            'Phone number already exists' => ['手机号已被使用', 'Phone number already exists'],
            'Tag name already exists' => ['标签名称已存在', 'Tag name already exists'],
            'IB partner not found' => ['未找到该 IB 伙伴', 'IB partner not found'],
            'Invalid IB partner id' => ['无效的 IB 伙伴 ID', 'Invalid IB partner id'],
            'You do not have permission to perform this action' => [
                '无权限执行此操作',
                'You do not have permission to perform this action',
            ],
            'Please select at least one client to assign' => [
                '请至少选择一位客户再分配',
                'Please select at least one client to assign',
            ],
            'Selected manager is not available' => [
                '所选销售不可用',
                'Selected manager is not available',
            ],
            'Selected account manager is not available' => [
                '所选客户经理不可用',
                'Selected account manager is not available',
            ],
            'Selected KYC template is not available' => [
                '所选 KYC 模板不可用',
                'Selected KYC template is not available',
            ],
            'Password is required when auto-generation is disabled' => [
                '未开启自动生成密码时须填写密码',
                'Password is required when auto-generation is disabled',
            ],
            'KYC verification is required before opening a trading account' => [
                '开户前须完成 KYC 认证',
                'KYC verification is required before opening a trading account',
            ],
            'User not found' => ['未找到该用户', 'User not found'],
            'Initial deposit must be at least 100' => [
                '初始入金不得低于 100',
                'Initial deposit must be at least 100',
            ],
            'Selected platform is not available' => [
                '所选交易平台不可用',
                'Selected platform is not available',
            ],
            'Password is required for this platform.' => [
                '该平台开户须填写交易密码',
                'Password is required for this platform.',
            ],
            'Selected leverage is not available for this platform' => [
                '所选杠杆在该平台不可用',
                'Selected leverage is not available for this platform',
            ],
            'Selected trading group is not available for account opening.' => [
                '所选交易组不可用于开户',
                'Selected trading group is not available for account opening.',
            ],
            'MT5 account opening requires a valid trading group' => [
                'MT5 开户须选择有效交易组',
                'MT5 account opening requires a valid trading group',
            ],
            'MT4 account opening requires a valid trading group' => [
                'MT4 开户须选择有效交易组',
                'MT4 account opening requires a valid trading group',
            ],
            'Unable to generate unique account number, please try again' => [
                '无法生成唯一账号，请重试',
                'Unable to generate unique account number, please try again',
            ],
            'Suffix is required and cannot be empty' => [
                '推荐后缀不能为空',
                'Suffix is required and cannot be empty',
            ],
            'Suffix must be at most 100 characters' => [
                '推荐后缀最多 100 个字符',
                'Suffix must be at most 100 characters',
            ],
            'Suffix may only contain letters, numbers, hyphens and underscores' => [
                '推荐后缀仅可包含字母、数字、连字符和下划线',
                'Suffix may only contain letters, numbers, hyphens and underscores',
            ],
            'This referral code is already in use by another IB' => [
                '该推荐码已被其他 IB 使用',
                'This referral code is already in use by another IB',
            ],
            'At least one notification channel must be selected.' => [
                '请至少选择一种通知渠道',
                'At least one notification channel must be selected.',
            ],
            'Scheduled time is required for scheduled notifications.' => [
                '定时通知须填写发送时间',
                'Scheduled time is required for scheduled notifications.',
            ],
            'Invalid scheduled time format.' => [
                '定时发送时间格式无效',
                'Invalid scheduled time format.',
            ],
            'Scheduled time must be in the future.' => [
                '定时发送时间须为未来时间',
                'Scheduled time must be in the future.',
            ],
            'Selected email template does not exist.' => [
                '所选邮件模板不存在',
                'Selected email template does not exist.',
            ],
            'Selected email template is not active.' => [
                '所选邮件模板未启用',
                'Selected email template is not active.',
            ],
            'clientIds must be a non-empty array.' => [
                'clientIds 须为非空数组',
                'clientIds must be a non-empty array.',
            ],
        ];
    }

    /**
     * 交易模块 API message → [中文, 英文友好句]
     *
     * @return array<string,array{0:string,1:string}>
     */
    private static function transactionApiMessageCatalog() {
        return [
            'Deposit not found' => ['未找到该入金', 'Deposit not found'],
            'Only pending deposits can be approved' => [
                '仅待处理状态的入金可以批准',
                'Only pending deposits can be approved',
            ],
            'Only pending deposits can be rejected' => [
                '仅待处理状态的入金可以拒绝',
                'Only pending deposits can be rejected',
            ],
            'Deposit not found or not in pending status' => [
                '未找到该入金或状态非待处理',
                'Deposit not found or not in pending status',
            ],
            'depositIds is required' => ['未选择入金记录', 'Deposit selection is required'],
            'noteContent is required' => ['备注内容不能为空', 'Note content is required'],
            'Note content cannot be empty' => ['备注内容不能为空', 'Note content cannot be empty'],
            'Email address does not match the deposit client' => [
                '邮箱地址与客户不匹配',
                'Email address does not match the deposit client',
            ],
            'Please select at least one deposit to export' => [
                '请至少选择一笔入金再导出',
                'Please select at least one deposit to export',
            ],
            'No valid deposits found for export' => [
                '没有可导出的有效入金记录',
                'No valid deposits found for export',
            ],
            'Withdrawal not found' => ['未找到该出金', 'Withdrawal not found'],
            'Only pending withdrawals can be approved' => [
                '仅待处理状态的出金可以批准',
                'Only pending withdrawals can be approved',
            ],
            'Only pending withdrawals can be rejected' => [
                '仅待处理状态的出金可以拒绝',
                'Only pending withdrawals can be rejected',
            ],
            'Withdrawal not found or not in pending status' => [
                '未找到该出金或状态非待处理',
                'Withdrawal not found or not in pending status',
            ],
            'withdrawalIds is required' => ['未选择出金记录', 'Withdrawal selection is required'],
            'tagName is required' => ['标签名称不能为空', 'Tag name is required'],
            'items is required' => ['未选择需补充的资料项', 'Document items are required'],
            'rejectionReasonId is required' => ['拒绝原因不能为空', 'Rejection reason is required'],
            'email is required' => ['邮箱不能为空', 'Email is required'],
            'email must be a valid email' => ['邮箱格式不正确', 'Email must be a valid email address'],
            'subject is required' => ['邮件主题不能为空', 'Email subject is required'],
            'content is required' => ['邮件内容不能为空', 'Email content is required'],
            'searchKeywords is required' => ['搜索关键词不能为空', 'Search keywords are required'],
            'Invalid rejection reason' => ['无效的拒绝原因', 'Invalid rejection reason'],
            'Custom reason is required when selecting "Other" option' => [
                '选择「其他」时必须填写自定义原因',
                'Custom reason is required when selecting "Other" option',
            ],
            'Email address does not match the withdrawal client' => [
                '邮箱地址与客户不匹配',
                'Email address does not match the withdrawal client',
            ],
            'Failed to send email' => ['邮件发送失败', 'Failed to send email'],
            'At least one question or document is required' => [
                '至少需要一项问题或文件',
                'At least one question or document is required',
            ],
            'Please select at least one withdrawal to export' => [
                '请至少选择一笔出金再导出',
                'Please select at least one withdrawal to export',
            ],
            'No valid withdrawals found for export' => [
                '没有可导出的有效出金记录',
                'No valid withdrawals found for export',
            ],
            'Internal transfer not found' => ['未找到该内部转账', 'Internal transfer not found'],
            'Internal transfer already completed' => [
                '该内部转账已完成',
                'Internal transfer already completed',
            ],
            'Only pending transfers can be approved' => [
                '仅待处理状态的内部转账可以批准',
                'Only pending transfers can be approved',
            ],
            'Only pending transfers can be rejected' => [
                '仅待处理状态的内部转账可以拒绝',
                'Only pending transfers can be rejected',
            ],
            'Transfer not found or already completed' => [
                '未找到该内部转账或已完成',
                'Transfer not found or already completed',
            ],
            'Please select at least one transfer to export' => [
                '请至少选择一笔内部转账再导出',
                'Please select at least one transfer to export',
            ],
            'No valid transfers found for export' => [
                '没有可导出的有效内部转账记录',
                'No valid transfers found for export',
            ],
            'Email address does not match the transfer client' => [
                '邮箱地址与客户不匹配',
                'Email address does not match the transfer client',
            ],
            'Search tag not found' => ['未找到该搜索标签', 'Search tag not found'],
            'A search tag with this name already exists' => [
                '已存在同名搜索标签',
                'A search tag with this name already exists',
            ],
            'Validation Failed' => ['请求参数校验失败', 'Validation failed'],
            'Submission not found' => ['未找到该 KYC 提交', 'Submission not found'],
            'Rejection reason is required' => ['拒绝原因不能为空', 'Rejection reason is required'],
            'At least one item (question or document) is required' => [
                '至少需要一项问题或文件',
                'At least one item (question or document) is required',
            ],
            'Submission IDs array is required' => [
                '未选择 KYC 提交记录',
                'Submission selection is required',
            ],
            'Reviewer ID is required' => ['审核员不能为空', 'Reviewer ID is required'],
            'Submissions with assigned reviewers cannot be reassigned' => [
                '已分配审核员的提交不可重新分配',
                'Submissions with assigned reviewers cannot be reassigned',
            ],
            'Gateway not found' => ['未找到该第三方 KYC 网关', 'Gateway not found'],
            'No valid fields to update' => ['没有可更新的有效字段', 'No valid fields to update'],
            'isEnabled is required' => ['启用状态不能为空', 'isEnabled is required'],
            'Gateway is not enabled' => ['网关未启用', 'Gateway is not enabled'],
            'Invalid JSON input' => ['请求数据格式无效', 'Invalid JSON input'],
            'settingKey is required' => ['settingKey 不能为空', 'settingKey is required'],
            'Notice settings not found' => ['未找到 KYC 提示文案设置', 'Notice settings not found'],
            'Failed to update notice settings' => [
                'KYC 提示文案更新失败',
                'Failed to update notice settings',
            ],
            'Template not found' => ['未找到该 KYC 模板', 'Template not found'],
            'Question not found' => ['未找到该问题', 'Question not found'],
            'Category not found' => ['未找到该分类', 'Category not found'],
            'Rule not found' => ['未找到该规则', 'Rule not found'],
            'Document not found' => ['未找到该法律文档', 'Document not found'],
            'Countries array is required' => ['国家列表不能为空', 'Countries array is required'],
            'One or more selected countries (or All Countries) are already assigned to another template. Each country can only be used by one template.' => [
                '所选国家（或全部国家）已被其他模板占用',
                'One or more selected countries (or All Countries) are already assigned to another template.',
            ],
            'This template cannot be deleted because it has been used by client users. Please archive it instead.' => [
                '该模板已被客户使用，无法删除',
                'This template cannot be deleted because it has been used by client users.',
            ],
            'Cannot delete category that contains active questions' => [
                '分类下仍有启用中的问题，无法删除',
                'Cannot delete category that contains active questions',
            ],
            'isThirdPartyEnabled is required' => [
                '第三方 KYC 启用状态不能为空',
                'isThirdPartyEnabled is required',
            ],
            'thirdPartyProvider and externalTemplateId are required when enabling' => [
                '启用第三方 KYC 时须指定平台与等级',
                'thirdPartyProvider and externalTemplateId are required when enabling',
            ],
            'External KYC template not found' => [
                '未找到第三方 KYC 等级',
                'External KYC template not found',
            ],
            'External KYC template is not active' => [
                '第三方 KYC 等级未启用',
                'External KYC template is not active',
            ],
            'External KYC gateway not found' => [
                '未找到第三方 KYC 网关',
                'External KYC gateway not found',
            ],
            'External KYC gateway is not enabled' => [
                '第三方 KYC 网关未启用',
                'External KYC gateway is not enabled',
            ],
            'Provider does not match the gateway of the selected template' => [
                '平台与所选等级所属网关不匹配',
                'Provider does not match the gateway of the selected template',
            ],
            'Target question is required for jump_to rules' => [
                '跳转规则须指定目标问题',
                'Target question is required for jump_to rules',
            ],
            'Reject message is required for reject rules' => [
                '拒绝规则须填写拒绝提示',
                'Reject message is required for reject rules',
            ],
        ];
    }

    /**
     * 交易设置页 API message → [中文, 英文友好句]
     *
     * @return array<string,array{0:string,1:string}>
     */
    private static function transactionSettingsApiMessageCatalog() {
        return [
            'Payment gateway not found' => ['未找到该支付网关', 'Payment gateway not found'],
            'System payment gateway cannot be modified' => [
                '系统支付网关不允许修改',
                'System payment gateway cannot be modified',
            ],
            'System payment gateway cannot be deleted' => [
                '系统支付网关不允许删除',
                'System payment gateway cannot be deleted',
            ],
            'type must be either fiat or crypto' => [
                '网关类型必须为法币或加密货币',
                'type must be either fiat or crypto',
            ],
            'configData must be a valid JSON string or object' => [
                'configData 必须是有效的 JSON 字符串或对象',
                'configData must be a valid JSON string or object',
            ],
            'No valid display content fields provided' => [
                '未提供有效的展示文案字段',
                'No valid display content fields provided',
            ],
            'No valid gateway fee limit fields provided' => [
                '未提供有效的网关限额字段',
                'No valid gateway fee limit fields provided',
            ],
            'gatewaySettingId is required' => [
                '须指定支付网关',
                'gatewaySettingId is required',
            ],
            'Payment support question not found' => [
                '未找到该支付支持问题',
                'Payment support question not found',
            ],
            'No valid payment support question fields provided' => [
                '未提供有效的支付支持问题字段',
                'No valid payment support question fields provided',
            ],
            'This row is locked. Only hintText can be updated.' => [
                '该记录已锁定，仅可更新提示文案',
                'This row is locked. Only hintText can be updated.',
            ],
            'name is required' => ['名称不能为空', 'name is required'],
            'Validation failed' => ['请求参数校验失败', 'Validation failed'],
            'Admin authentication required' => [
                '需要管理员身份验证',
                'Admin authentication required',
            ],
            'Invalid request data' => ['请求数据无效', 'Invalid request data'],
            'OTP validity period must be between 1 and 60 minutes' => [
                'OTP 有效期须在 1–60 分钟之间',
                'OTP validity period must be between 1 and 60 minutes',
            ],
            'Verification file size must be between 1 and 20 MB' => [
                '验证文件大小须在 1–20 MB 之间',
                'Verification file size must be between 1 and 20 MB',
            ],
            'Failed to update security settings' => [
                '安全设置更新失败',
                'Failed to update security settings',
            ],
            'Failed to update auto approval rules' => [
                '自动审批规则更新失败',
                'Failed to update auto approval rules',
            ],
            'No valid auto-approval rules provided' => [
                '未提供有效的自动审批规则',
                'No valid auto-approval rules provided',
            ],
            'Currency code is required' => [
                '货币代码不能为空',
                'Currency code is required',
            ],
            'Exchange rate must be greater than 0' => [
                '汇率必须大于 0',
                'Exchange rate must be greater than 0',
            ],
            'Type must be fiat or crypto' => [
                '类型必须为法币或加密货币',
                'Type must be fiat or crypto',
            ],
            'Currency code already exists' => [
                '货币代码已存在',
                'Currency code already exists',
            ],
            'Exchange rate not found' => [
                '未找到该汇率',
                'Exchange rate not found',
            ],
            'Failed to create exchange rate' => [
                '汇率新建失败',
                'Failed to create exchange rate',
            ],
            'Failed to update exchange rate' => [
                '汇率编辑失败',
                'Failed to update exchange rate',
            ],
            'Failed to delete exchange rate' => [
                '汇率删除失败',
                'Failed to delete exchange rate',
            ],
            'Failed to update exchange rate status' => [
                '汇率状态更新失败',
                'Failed to update exchange rate status',
            ],
        ];
    }
}
