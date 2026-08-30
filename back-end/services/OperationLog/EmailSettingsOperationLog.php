<?php
/**
 * 系统设置 — 邮件设置（log_system / email_settings）
 *
 * 仅当请求显式携带 logSubModuleKey=email_settings 时写入；targetId 恒为 null。
 */

require_once __DIR__ . '/../OperationLogPages.php';
require_once __DIR__ . '/../AdminOperationLogWriter.php';
require_once __DIR__ . '/../OperationLogTexts/SystemOperationLogTexts.php';
require_once __DIR__ . '/EmailSettingsLogSnapshot.php';

class EmailSettingsOperationLog {
    public static function shouldLog($input = null) {
        if (!is_array($input)) {
            return false;
        }
        $key = trim((string) ($input['logSubModuleKey'] ?? $input['operationLogSubModule'] ?? ''));
        return $key === OperationLogPages::subModuleKeyByAlias('page_email_settings');
    }

    public static function subModule() {
        return OperationLogPages::subModuleKeyByAlias('page_email_settings');
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

    /**
     * @param int[] $beforeIds
     * @param int[] $afterIds
     * @param array<int,string> $templateNameById
     */
    public static function logSectionUpdateSuccess(
        $input,
        $sectionKey,
        $sectionNameEn,
        array $beforeIds,
        array $afterIds,
        array $templateNameById,
        $operatorId = null
    ) {
        if (EmailSettingsLogSnapshot::idsEqual($beforeIds, $afterIds)) {
            return;
        }
        list($zh, $en) = SystemOperationLogTexts::emailSettingsSectionUpdateSuccess(
            $sectionKey,
            $sectionNameEn,
            EmailSettingsLogSnapshot::diffTemplateIds($beforeIds, $afterIds),
            $templateNameById
        );
        self::log($input, 'edit', $zh, $en, $operatorId);
    }
}
