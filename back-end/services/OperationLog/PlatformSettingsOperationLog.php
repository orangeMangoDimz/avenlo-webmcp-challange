<?php
/**
 * 系统设置 — 平台设置（log_system / platform_settings）
 *
 * 仅当请求显式携带 logSubModuleKey=platform_settings 时写入；targetId 恒为 null。
 */

require_once __DIR__ . '/../OperationLogPages.php';
require_once __DIR__ . '/../AdminOperationLogWriter.php';
require_once __DIR__ . '/../OperationLogTexts/SystemOperationLogTexts.php';
require_once __DIR__ . '/../../utils/RequestInput.php';
require_once __DIR__ . '/PlatformSettingsLogSnapshot.php';

class PlatformSettingsOperationLog {
    public static function shouldLog($input = null) {
        if (!is_array($input)) {
            return false;
        }
        $key = trim((string) ($input['logSubModuleKey'] ?? $input['operationLogSubModule'] ?? ''));
        return $key === OperationLogPages::subModuleKeyByAlias('page_platform_settings');
    }

    public static function subModule() {
        return OperationLogPages::subModuleKeyByAlias('page_platform_settings');
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
        if (!empty($_POST['logSubModuleKey'])) {
            $data['logSubModuleKey'] = $_POST['logSubModuleKey'];
        } elseif (!empty($_POST['operationLogSubModule'])) {
            $data['operationLogSubModule'] = $_POST['operationLogSubModule'];
        }
        if (empty($data)) {
            $fromBody = RequestInput::readJsonBody();
            if (is_array($fromBody)) {
                $data = $fromBody;
            }
        }
        return $data;
    }

    public static function log($input, $operationTypeKey, $detailZh, $detailEn, $operatorId = null) {
        if (!self::shouldLog($input)) {
            return;
        }
        $params = [
            'modelKey' => 'log_system',
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
            ['SystemOperationLogTexts', $failureMethod],
            $apiMessage
        );
        self::log($input, $operationTypeKey, $detailZh, $detailEn, $operatorId);
    }

    public static function logAccountUpdateSuccess($input, array $beforeState, array $afterState, $operatorId = null) {
        $fpBefore = PlatformSettingsLogSnapshot::platformAccountFingerprints($beforeState);
        $fpAfter = PlatformSettingsLogSnapshot::platformAccountFingerprints($afterState);
        $changed = PlatformSettingsLogSnapshot::changedKeys($fpBefore, $fpAfter, ['accountLimit', 'passwordMode']);
        if (empty($changed)) {
            return;
        }
        $texts = SystemOperationLogTexts::platformSettingsAccountUpdateSuccessDiff($beforeState, $afterState, $changed);
        if ($texts === null) {
            return;
        }
        list($zh, $en) = $texts;
        self::log($input, 'edit', $zh, $en, $operatorId);
    }

    public static function logGroupSyncSuccess($input, $platformKey, array $result, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::platformSettingsGroupSyncSuccess($platformKey, $result);
        self::log($input, 'import', $zh, $en, $operatorId);
    }

    public static function logGroupSetDefaultSuccess($input, array $groupState, $platformKey, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::platformSettingsGroupSetDefaultSuccess($groupState, $platformKey);
        self::log($input, 'edit', $zh, $en, $operatorId);
    }

    public static function logGroupRemoveDefaultSuccess($input, array $groupState, $platformKey, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::platformSettingsGroupRemoveDefaultSuccess($groupState, $platformKey);
        self::log($input, 'edit', $zh, $en, $operatorId);
    }

    public static function logGroupEditSuccess($input, array $beforeState, array $afterState, $operatorId = null) {
        $fpBefore = PlatformSettingsLogSnapshot::tradingGroupFingerprints($beforeState);
        $fpAfter = PlatformSettingsLogSnapshot::tradingGroupFingerprints($afterState);
        $changed = PlatformSettingsLogSnapshot::changedKeys($fpBefore, $fpAfter, ['label', 'unit', 'scale']);
        if (empty($changed)) {
            return;
        }
        $texts = SystemOperationLogTexts::platformSettingsGroupEditSuccessDiff($beforeState, $afterState, $changed);
        if ($texts === null) {
            return;
        }
        list($zh, $en) = $texts;
        self::log($input, 'edit', $zh, $en, $operatorId);
    }

    public static function logLeverageCreateSuccess($input, array $state, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::platformSettingsLeverageCreateSuccess($state);
        self::log($input, 'add', $zh, $en, $operatorId);
    }

    public static function logLeverageUpdateSuccess($input, array $beforeState, array $afterState, $operatorId = null) {
        $fpBefore = PlatformSettingsLogSnapshot::leverageFingerprints($beforeState);
        $fpAfter = PlatformSettingsLogSnapshot::leverageFingerprints($afterState);
        $changed = PlatformSettingsLogSnapshot::changedKeys(
            $fpBefore,
            $fpAfter,
            ['leverageValue', 'displayLabel', 'riskNote', 'displayOrder']
        );
        if (empty($changed)) {
            return;
        }
        $texts = SystemOperationLogTexts::platformSettingsLeverageUpdateSuccessDiff($beforeState, $afterState, $changed);
        if ($texts === null) {
            return;
        }
        list($zh, $en) = $texts;
        self::log($input, 'edit', $zh, $en, $operatorId);
    }

    public static function logLeverageToggleSuccess($input, array $state, $enabled, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::platformSettingsLeverageToggleSuccess($state, !!$enabled);
        self::log($input, $enabled ? 'enable' : 'disable', $zh, $en, $operatorId);
    }

    public static function logLeverageDeleteSuccess($input, array $state, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::platformSettingsLeverageDeleteSuccess($state);
        self::log($input, 'delete', $zh, $en, $operatorId);
    }
}
