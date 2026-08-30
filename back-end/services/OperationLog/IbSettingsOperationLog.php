<?php
/**
 * IB 设置页 — 操作日志写入辅助（log_ib / ib_settings）
 *
 * 仅当请求显式携带 logSubModuleKey=ib_settings 时写入。
 * 详情文案与页面可见字段一致，不含文档/等级/规则等 DB ID；targetId 恒为 null。
 */

require_once __DIR__ . '/../OperationLogPages.php';
require_once __DIR__ . '/../AdminOperationLogWriter.php';
require_once __DIR__ . '/../OperationLogTexts/IbOperationLogTexts.php';
require_once __DIR__ . '/../OperationLogTexts/OperationLogTextHelpers.php';
require_once __DIR__ . '/../../models/TradingPlatform.php';

class IbSettingsOperationLog {
    public static function shouldLog($input = null) {
        if (!is_array($input)) {
            return false;
        }
        $key = trim((string) ($input['logSubModuleKey'] ?? $input['operationLogSubModule'] ?? ''));
        return $key === OperationLogPages::subModuleKeyByAlias('page_ib_settings');
    }

    public static function subModule() {
        return OperationLogPages::subModuleKeyByAlias('page_ib_settings');
    }

    /**
     * @return array<string,mixed>
     */
    public static function inputFromRequest($body = null) {
        $data = is_array($body) ? $body : [];
        if (!empty($_GET['logSubModuleKey'])) {
            $data['logSubModuleKey'] = $_GET['logSubModuleKey'];
        } elseif (!empty($_GET['operationLogSubModule'])) {
            $data['operationLogSubModule'] = $_GET['operationLogSubModule'];
        }
        if (empty($data)) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }
        return $data;
    }

    public static function log($input, $operationTypeKey, $detailZh, $detailEn, $operatorId = null) {
        if (!self::shouldLog($input)) {
            return;
        }
        $params = [
            'modelKey' => 'log_ib',
            'subModuleKey' => self::subModule(),
            'operationTypeKey' => trim((string) $operationTypeKey) ?: 'edit',
            'targetId' => null,
            'detailZh' => trim((string) $detailZh),
            'detailEn' => trim((string) $detailEn),
        ];
        $oid = $operatorId !== null ? (int) $operatorId : 0;
        if ($oid > 0) {
            $params['operatorId'] = $oid;
        }
        (new AdminOperationLogWriter())->record($params);
    }

    public static function logFailure($input, $operationTypeKey, $failureMethod, $apiMessage, $operatorId = null) {
        if (!self::shouldLog($input)) {
            return;
        }
        list($detailZh, $detailEn) = call_user_func(
            ['IbOperationLogTexts', $failureMethod],
            $apiMessage
        );
        self::log($input, $operationTypeKey, $detailZh, $detailEn, $operatorId);
    }

    public static function resolvePlatformDisplayName($platformKey) {
        $key = strtolower(trim((string) $platformKey));
        if ($key === '') {
            return '';
        }
        $platform = (new TradingPlatform())->findByKey($key);
        if (is_array($platform)) {
            $name = trim((string) ($platform['displayName'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }
        $fallback = [
            'mt4' => 'MT4',
            'mt5' => 'MT5',
            'financepro' => 'FinancePro',
        ];
        return $fallback[$key] ?? strtoupper($key);
    }

    public static function logDocumentAddSuccess($input, $title) {
        list($zh, $en) = IbOperationLogTexts::ibSettingsDocumentAdd($title);
        self::log($input, 'add', $zh, $en);
    }

    public static function logDocumentEditSuccess($input, $title) {
        list($zh, $en) = IbOperationLogTexts::ibSettingsDocumentEdit($title);
        self::log($input, 'edit', $zh, $en);
    }

    public static function logDocumentDeleteSuccess($input, $title) {
        list($zh, $en) = IbOperationLogTexts::ibSettingsDocumentDelete($title);
        self::log($input, 'delete', $zh, $en);
    }

    public static function logDocumentDuplicateSuccess($input, $sourceTitle) {
        list($zh, $en) = IbOperationLogTexts::ibSettingsDocumentDuplicate($sourceTitle);
        self::log($input, 'add', $zh, $en);
    }

    public static function logTierAddSuccess($input, $tierName, $tierLevel) {
        list($zh, $en) = IbOperationLogTexts::ibSettingsTierAdd($tierName, $tierLevel);
        self::log($input, 'add', $zh, $en);
    }

    public static function logTierEditSuccess($input, $tierName, $tierLevel) {
        list($zh, $en) = IbOperationLogTexts::ibSettingsTierEdit($tierName, $tierLevel);
        self::log($input, 'edit', $zh, $en);
    }

    public static function logTierDeleteSuccess($input, $tierName, $tierLevel) {
        list($zh, $en) = IbOperationLogTexts::ibSettingsTierDelete($tierName, $tierLevel);
        self::log($input, 'delete', $zh, $en);
    }

    public static function logRuleAddSuccess($input, $ruleName) {
        list($zh, $en) = IbOperationLogTexts::ibSettingsRuleAdd($ruleName);
        self::log($input, 'add', $zh, $en);
    }

    public static function logRuleEditSuccess($input, $ruleName) {
        list($zh, $en) = IbOperationLogTexts::ibSettingsRuleEdit($ruleName);
        self::log($input, 'edit', $zh, $en);
    }

    public static function logRuleDeleteSuccess($input, $ruleName) {
        list($zh, $en) = IbOperationLogTexts::ibSettingsRuleDelete($ruleName);
        self::log($input, 'delete', $zh, $en);
    }

    public static function logRuleDuplicateSuccess($input, $sourceRuleName) {
        list($zh, $en) = IbOperationLogTexts::ibSettingsRuleDuplicate($sourceRuleName);
        self::log($input, 'add', $zh, $en);
    }

    public static function logSyncSuccess($input, $platformKey, $securitiesCount, $symbolsCount) {
        $platformName = self::resolvePlatformDisplayName($platformKey);
        list($zh, $en) = IbOperationLogTexts::ibSettingsSyncProductsSuccess(
            $platformName,
            (int) $securitiesCount,
            (int) $symbolsCount
        );
        self::log($input, 'import', $zh, $en);
    }

    /**
     * 异步同步任务完成后写入（无 HTTP 请求上下文，使用任务中的 adminId）
     */
    public static function logSyncSuccessForOperator($adminId, $platformKey, $securitiesCount, $symbolsCount) {
        $adminId = (int) $adminId;
        if ($adminId <= 0) {
            return;
        }
        $platformName = self::resolvePlatformDisplayName($platformKey);
        list($zh, $en) = IbOperationLogTexts::ibSettingsSyncProductsSuccess(
            $platformName,
            (int) $securitiesCount,
            (int) $symbolsCount
        );
        self::log(['logSubModuleKey' => self::subModule()], 'import', $zh, $en, $adminId);
    }

    public static function logSyncFailureForOperator($adminId, $platformKey, $apiMessage) {
        $adminId = (int) $adminId;
        if ($adminId <= 0) {
            return;
        }
        self::logFailure(
            ['logSubModuleKey' => self::subModule()],
            'import',
            'ibSettingsSyncProductsFailure',
            $apiMessage,
            $adminId
        );
    }

    public static function logSymbolExchangeBatchModeSuccess($input, $mode) {
        list($zh, $en) = IbOperationLogTexts::ibSettingsSymbolExchangeBatchMode($mode);
        self::log($input, 'edit', $zh, $en);
    }

    public static function logSymbolExchangeAddSuccess($input, $symbolName) {
        list($zh, $en) = IbOperationLogTexts::ibSettingsSymbolExchangeAdd($symbolName);
        self::log($input, 'add', $zh, $en);
    }

    public static function logSymbolExchangeEditSuccess($input, $symbolName) {
        list($zh, $en) = IbOperationLogTexts::ibSettingsSymbolExchangeEdit($symbolName);
        self::log($input, 'edit', $zh, $en);
    }

    public static function logSymbolExchangeDeleteSuccess($input, $symbolName) {
        list($zh, $en) = IbOperationLogTexts::ibSettingsSymbolExchangeDelete($symbolName);
        self::log($input, 'delete', $zh, $en);
    }
}
