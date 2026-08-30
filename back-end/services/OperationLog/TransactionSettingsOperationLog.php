<?php
/**
 * 交易设置页 — 操作日志写入辅助（log_transaction / transaction_settings）
 */

require_once __DIR__ . '/../OperationLogPages.php';
require_once __DIR__ . '/../AdminOperationLogWriter.php';
require_once __DIR__ . '/../OperationLogTexts/TransactionOperationLogTexts.php';
require_once __DIR__ . '/../OperationLogTexts/OperationLogTextHelpers.php';

class TransactionSettingsOperationLog {
    public static function subModule($input = null) {
        return OperationLogPages::resolveLogTransactionSettings(is_array($input) ? $input : []);
    }

    public static function log($input, $operationTypeKey, $detailZh, $detailEn) {
        (new AdminOperationLogWriter())->logTransactionSettingsMutation(
            self::subModule($input),
            $operationTypeKey,
            $detailZh,
            $detailEn
        );
    }

    public static function logFailure($input, $operationTypeKey, $failureMethod, $apiMessage) {
        list($detailZh, $detailEn) = call_user_func(
            ['TransactionOperationLogTexts', $failureMethod],
            $apiMessage
        );
        self::log($input, $operationTypeKey, $detailZh, $detailEn);
    }

    public static function gatewayLabel($gateway) {
        if (!is_array($gateway)) {
            return '';
        }
        $name = trim((string) ($gateway['gatewayName'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        return trim((string) ($gateway['gatewayKey'] ?? ''));
    }

    public static function logGatewayUpdateSuccess($input, array $gatewayBefore) {
        $label = self::gatewayLabel($gatewayBefore);
        $data = is_array($input) ? $input : [];
        $keys = array_keys($data);
        $keys = array_values(array_filter($keys, static function ($k) {
            return !in_array($k, ['logSubModuleKey', 'operationLogSubModule'], true);
        }));

        if (count($keys) === 1 && isset($data['isEnabled'])) {
            $on = !empty($data['isEnabled']);
            list($zh, $en) = TransactionOperationLogTexts::transactionSettingsGatewayToggle($label, $on);
            self::log($input, $on ? 'enable' : 'disable', $zh, $en);
            return;
        }

        $capabilityOnly = !empty($keys) && empty(array_diff($keys, [
            'isDepositEnabled', 'isWithdrawalEnabled', 'supportedFiatCurrencies', 'supportedCryptoCurrencies'
        ]));
        if ($capabilityOnly) {
            list($zh, $en) = TransactionOperationLogTexts::transactionSettingsGatewayCapability($label);
            self::log($input, 'edit', $zh, $en);
            return;
        }

        list($zh, $en) = TransactionOperationLogTexts::transactionSettingsGatewayEdit($label);
        self::log($input, 'edit', $zh, $en);
    }

    public static function logGatewayDeleteSuccess($input, array $gateway) {
        $label = self::gatewayLabel($gateway);
        list($zh, $en) = TransactionOperationLogTexts::transactionSettingsGatewayDelete($label);
        self::log($input, 'delete', $zh, $en);
    }

    public static function logGatewayByIdSuccess($input, $gatewaySettingId, $textMethod, $operationType = 'edit') {
        $label = TransactionOperationLogTexts::resolveGatewayLabelBySettingId($gatewaySettingId);
        list($zh, $en) = call_user_func(['TransactionOperationLogTexts', $textMethod], $label);
        self::log($input, $operationType, $zh, $en);
    }

    public static function logSupportQuestion($input, $operationType, $gatewaySettingId, $questionName, $scope = '') {
        $gw = TransactionOperationLogTexts::resolveGatewayLabelBySettingId($gatewaySettingId);
        $name = trim((string) $questionName);
        $scope = trim((string) $scope);
        if ($operationType === 'add') {
            list($zh, $en) = TransactionOperationLogTexts::transactionSettingsSupportQuestionAdd($gw, $name, $scope);
        } elseif ($operationType === 'delete') {
            list($zh, $en) = TransactionOperationLogTexts::transactionSettingsSupportQuestionDelete($gw, $name, $scope);
        } else {
            list($zh, $en) = TransactionOperationLogTexts::transactionSettingsSupportQuestionEdit($gw, $name, $scope);
        }
        self::log($input, $operationType, $zh, $en);
    }

    public static function logDisplayContentScope($input, $scope) {
        list($zh, $en) = TransactionOperationLogTexts::transactionSettingsDisplayContentUpdate($scope);
        self::log($input, 'edit', $zh, $en);
    }

    public static function logNotification($input, $settingKey, $settingValue) {
        list($zh, $en) = TransactionOperationLogTexts::transactionSettingsNotificationUpdate($settingKey, $settingValue);
        self::log($input, 'edit', $zh, $en);
    }

    public static function logSecurityChanges($input, array $changesZh, array $changesEn) {
        if (!$changesZh) {
            return;
        }
        list($zh, $en) = TransactionOperationLogTexts::transactionSettingsSecurityUpdate($changesZh, $changesEn);
        self::log($input, 'edit', $zh, $en);
    }

    public static function logExchangeRate($input, $operationType, $currencyCode, $extraZh = '', $extraEn = '') {
        $code = trim((string) $currencyCode);
        if ($operationType === 'add') {
            list($zh, $en) = TransactionOperationLogTexts::transactionSettingsExchangeRateAdd($code);
        } elseif ($operationType === 'delete') {
            list($zh, $en) = TransactionOperationLogTexts::transactionSettingsExchangeRateDelete($code);
        } elseif ($operationType === 'enable' || $operationType === 'disable') {
            list($zh, $en) = TransactionOperationLogTexts::transactionSettingsExchangeRateToggle(
                $code,
                $operationType === 'enable'
            );
        } else {
            list($zh, $en) = TransactionOperationLogTexts::transactionSettingsExchangeRateEdit($code, $extraZh, $extraEn);
        }
        self::log($input, $operationType === 'enable' || $operationType === 'disable' ? $operationType : (
            $operationType === 'add' ? 'add' : ($operationType === 'delete' ? 'delete' : 'edit')
        ), $zh, $en);
    }

    /**
     * @return array{0:array<int,string>,1:array<int,string>}
     */
    public static function detectAutoApprovalRuleChanges($ruleTypeKey, $existing, $incoming) {
        $typeZh = TransactionOperationLogTexts::autoApprovalRuleTypeZh($ruleTypeKey);
        $typeEn = TransactionOperationLogTexts::autoApprovalRuleTypeEn($ruleTypeKey);
        $linesZh = [];
        $linesEn = [];

        if (!is_array($existing) || !is_array($incoming)) {
            return [$linesZh, $linesEn];
        }

        $boolFields = [
            'isEnabled' => ['开启', '关闭', 'enabled', 'disabled'],
            'requireKycVerified' => ['要求 KYC 已验证', '取消 KYC 已验证要求', 'require KYC verified', 'KYC verification not required'],
            'checkSavedWallet' => ['要求已保存钱包', '取消已保存钱包要求', 'require saved wallet', 'saved wallet not required'],
        ];

        foreach ($boolFields as $field => $labels) {
            if (!array_key_exists($field, $incoming)) {
                continue;
            }
            $old = !empty($existing[$field]);
            $new = !empty($incoming[$field]);
            if ($old === $new) {
                continue;
            }
            if ($field === 'isEnabled') {
                $linesZh[] = $new ? "{$typeZh}自动审批：开启" : "{$typeZh}自动审批：关闭";
                $linesEn[] = $new ? "{$typeEn} auto-approval: enabled" : "{$typeEn} auto-approval: disabled";
            } else {
                $linesZh[] = $new ? "{$typeZh}：{$labels[0]}" : "{$typeZh}：{$labels[1]}";
                $linesEn[] = $new ? "{$typeEn}: {$labels[2]}" : "{$typeEn}: {$labels[3]}";
            }
        }

        $amountFields = [
            'minAmount' => ['最低金额', 'minimum amount'],
            'maxAmount' => ['最高金额', 'maximum amount'],
        ];
        foreach ($amountFields as $field => $labels) {
            if (!array_key_exists($field, $incoming)) {
                continue;
            }
            $old = number_format((float) ($existing[$field] ?? 0), 2, '.', '');
            $new = number_format((float) ($incoming[$field] ?? 0), 2, '.', '');
            if ($old === $new) {
                continue;
            }
            $linesZh[] = "{$typeZh}自动审批：{$labels[0]}由 {$old} 改为 {$new}";
            $linesEn[] = "{$typeEn} auto-approval: {$labels[1]} changed from {$old} to {$new}";
        }

        if (array_key_exists('allowedCountries', $incoming)) {
            $oldList = self::normalizeCountryList($existing['allowedCountries'] ?? null);
            $newList = self::normalizeCountryList($incoming['allowedCountries']);
            if ($oldList !== $newList) {
                $linesZh[] = "{$typeZh}自动审批：允许国家/地区改为 {$newList}";
                $linesEn[] = "{$typeEn} auto-approval: allowed countries set to {$newList}";
            }
        }

        foreach (['requiredClientTags' => ['必需客户标签', 'required client tags'], 'excludedClientTags' => ['排除客户标签', 'excluded client tags']] as $field => $labels) {
            if (!array_key_exists($field, $incoming)) {
                continue;
            }
            $oldList = self::normalizeTagList($existing[$field] ?? null);
            $newList = self::normalizeTagList($incoming[$field]);
            if ($oldList !== $newList) {
                $linesZh[] = "{$typeZh}自动审批：{$labels[0]}改为 {$newList}";
                $linesEn[] = "{$typeEn} auto-approval: {$labels[1]} set to {$newList}";
            }
        }

        return [$linesZh, $linesEn];
    }

    public static function logAutoApprovalBatch($input, array $linesZh, array $linesEn) {
        if (!$linesZh) {
            return;
        }
        list($zh, $en) = TransactionOperationLogTexts::transactionSettingsAutoApprovalUpdate($linesZh, $linesEn);
        self::log($input, 'edit', $zh, $en);
    }

    private static function normalizeCountryList($value) {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }
        if (!is_array($value)) {
            return '—';
        }
        $parts = [];
        foreach ($value as $item) {
            $s = trim((string) $item);
            if ($s !== '') {
                $parts[] = $s;
            }
        }
        return $parts ? implode(', ', $parts) : '—';
    }

    private static function normalizeTagList($value) {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                $s = trim((string) $item);
                if ($s !== '') {
                    $parts[] = $s;
                }
            }
            return $parts ? implode(', ', $parts) : '—';
        }
        $s = trim((string) $value);
        return $s !== '' ? $s : '—';
    }

    /**
     * @return array{0:array<int,string>,1:array<int,string>}
     */
    public static function detectSecurityChanges(array $before, array $incoming) {
        $linesZh = [];
        $linesEn = [];
        $labels = TransactionOperationLogTexts::securitySettingFieldLabels();

        foreach ($labels as $field => $label) {
            if (!array_key_exists($field, $incoming)) {
                continue;
            }
            $old = $before[$field] ?? null;
            $new = $incoming[$field];
            $boolFields = [
                'salesManagerNotifications',
                'withdrawalOtpRequired',
                'requireVerifiedWalletOnly',
                'requireWithdrawalVerification',
                'autoRejectUnverified',
            ];
            if (in_array($field, $boolFields, true)) {
                $oldB = filter_var($old, FILTER_VALIDATE_BOOLEAN);
                $newB = filter_var($new, FILTER_VALIDATE_BOOLEAN);
                if ($oldB === $newB) {
                    continue;
                }
                $linesZh[] = $newB ? "开启{$label['zh']}" : "关闭{$label['zh']}";
                $linesEn[] = $newB ? "Enabled {$label['en']}" : "Disabled {$label['en']}";
                continue;
            }
            if ((string) $old === (string) $new) {
                continue;
            }
            $linesZh[] = "{$label['zh']}由 {$old} 改为 {$new}";
            $linesEn[] = "{$label['en']} changed from {$old} to {$new}";
        }

        return [$linesZh, $linesEn];
    }

    public static function detectExchangeRateChanges(array $before, array $incoming) {
        $partsZh = [];
        $partsEn = [];
        $map = [
            'exchangeRate' => ['汇率', 'exchange rate'],
            'depositBias' => ['入金偏差', 'deposit bias'],
            'withdrawBias' => ['出金偏差', 'withdraw bias'],
            'depositType' => ['入金汇率模式', 'deposit rate mode'],
            'withdrawType' => ['出金汇率模式', 'withdraw rate mode'],
            'isActive' => null,
        ];

        if (array_key_exists('isActive', $incoming)) {
            $old = !empty($before['isActive']);
            $new = !empty($incoming['isActive']);
            if ($old !== $new) {
                $partsZh[] = $new ? '状态改为启用' : '状态改为停用';
                $partsEn[] = $new ? 'status set to active' : 'status set to inactive';
            }
        }

        foreach ($map as $field => $labels) {
            if ($labels === null || !array_key_exists($field, $incoming)) {
                continue;
            }
            $old = (string) ($before[$field] ?? '');
            $new = (string) ($incoming[$field] ?? '');
            if ($old === $new) {
                continue;
            }
            $partsZh[] = "{$labels[0]}由 {$old} 改为 {$new}";
            $partsEn[] = "{$labels[1]} changed from {$old} to {$new}";
        }

        if (array_key_exists('currencyName', $incoming)) {
            $old = trim((string) ($before['currencyName'] ?? ''));
            $new = trim((string) ($incoming['currencyName'] ?? ''));
            if ($old !== $new && $new !== '') {
                $partsZh[] = "名称由 {$old} 改为 {$new}";
                $partsEn[] = "name changed from {$old} to {$new}";
            }
        }

        return [implode('；', $partsZh), implode('; ', $partsEn)];
    }
}
