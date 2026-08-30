<?php
/**
 * 系统设置 — 日志设置（log_system / log_settings）
 *
 * 仅当请求显式携带 logSubModuleKey=log_settings 时写入；targetId 恒为 null。
 * 写入时绕过 isLoggingEnabled，避免关闭 log_system 后无法记录自身操作。
 */

require_once __DIR__ . '/../OperationLogPages.php';
require_once __DIR__ . '/../AdminOperationLogWriter.php';
require_once __DIR__ . '/../OperationLogTexts/SystemOperationLogTexts.php';

class LogSettingsOperationLog {
    public static function shouldLog($input = null) {
        if (!is_array($input)) {
            return false;
        }
        $key = trim((string) ($input['logSubModuleKey'] ?? $input['operationLogSubModule'] ?? ''));
        return $key === OperationLogPages::subModuleKeyByAlias('page_log_settings');
    }

    public static function subModule() {
        return OperationLogPages::subModuleKeyByAlias('page_log_settings');
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
        (new AdminOperationLogWriter())->record($params, true);
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

    /**
     * @param array<string,mixed> $moduleRow
     */
    public static function logModuleToggleSuccess($input, array $moduleRow, $enabled, $operatorId = null) {
        $nameZh = trim((string) ($moduleRow['moduleNameZh'] ?? ''));
        $nameEn = trim((string) ($moduleRow['moduleNameEn'] ?? ''));
        if ($nameZh === '') {
            $nameZh = trim((string) ($moduleRow['modelKey'] ?? '模块'));
        }
        if ($nameEn === '') {
            $nameEn = trim((string) ($moduleRow['modelKey'] ?? 'module'));
        }
        list($zh, $en) = SystemOperationLogTexts::logSettingsModuleToggleSuccess($nameZh, $nameEn, !!$enabled);
        self::log($input, $enabled ? 'enable' : 'disable', $zh, $en, $operatorId);
    }

    /**
     * @param array<int,array<string,mixed>> $moduleRows
     */
    public static function logBulkToggleSuccess($input, array $moduleRows, $enabled, $operatorId = null) {
        if (empty($moduleRows)) {
            return;
        }
        list($zh, $en) = SystemOperationLogTexts::logSettingsModuleBulkToggleSuccess($moduleRows, !!$enabled);
        self::log($input, $enabled ? 'enable' : 'disable', $zh, $en, $operatorId);
    }
}
