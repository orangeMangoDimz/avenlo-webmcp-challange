<?php
/**
 * 系统设置 — 账户管理（log_system / accounts）
 */

require_once __DIR__ . '/../OperationLogPages.php';
require_once __DIR__ . '/../AdminOperationLogWriter.php';
require_once __DIR__ . '/../OperationLogTexts/SystemOperationLogTexts.php';
require_once __DIR__ . '/AdminUserLogSnapshot.php';

class AccountsOperationLog {
    public static function shouldLog($input = null) {
        if (!is_array($input)) {
            return false;
        }
        $key = trim((string) ($input['logSubModuleKey'] ?? $input['operationLogSubModule'] ?? ''));
        return $key === OperationLogPages::subModuleKeyByAlias('page_accounts');
    }

    public static function subModule() {
        return OperationLogPages::subModuleKeyByAlias('page_accounts');
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

    public static function log($input, $operationTypeKey, $detailZh, $detailEn, $targetId = null, $operatorId = null) {
        if (!self::shouldLog($input)) {
            return;
        }
        $tid = $targetId !== null ? (int) $targetId : null;
        if ($tid !== null && $tid <= 0) {
            $tid = null;
        }
        $params = [
            'modelKey' => 'log_system',
            'subModuleKey' => self::subModule(),
            'operationTypeKey' => trim((string) $operationTypeKey) ?: 'edit',
            'targetId' => $tid,
            'detailZh' => trim((string) $detailZh),
            'detailEn' => trim((string) $detailEn),
        ];
        $oid = $operatorId !== null ? (int) $operatorId : 0;
        if ($oid > 0) {
            $params['operatorId'] = $oid;
        }
        (new AdminOperationLogWriter())->record($params);
    }

    public static function logFailure($input, $operationTypeKey, $failureMethod, $apiMessage, $targetId = null, $operatorId = null) {
        if (!self::shouldLog($input)) {
            return;
        }
        list($detailZh, $detailEn) = call_user_func(
            ['SystemOperationLogTexts', $failureMethod],
            $apiMessage
        );
        self::log($input, $operationTypeKey, $detailZh, $detailEn, $targetId, $operatorId);
    }

    public static function logCreateSuccess($input, array $state, $userId, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::adminUserCreateSuccess($state);
        self::log($input, 'add', $zh, $en, $userId, $operatorId);
    }

    public static function logUpdateSuccess($input, array $beforeState, array $afterState, $userId, $operatorId = null) {
        $texts = SystemOperationLogTexts::adminUserUpdateSuccessDiff($beforeState, $afterState);
        if ($texts === null) {
            return;
        }
        list($zh, $en) = $texts;
        self::log($input, 'edit', $zh, $en, $userId, $operatorId);
    }

    public static function logDeleteSuccess($input, array $state, $userId, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::adminUserDeleteSuccess($state);
        self::log($input, 'delete', $zh, $en, $userId, $operatorId);
    }
}
