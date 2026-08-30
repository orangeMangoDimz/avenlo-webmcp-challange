<?php
/**
 * 系统设置 — 邮件模板（log_system / email_templates）
 *
 * 仅当请求显式携带 logSubModuleKey=email_templates 时写入。
 */

require_once __DIR__ . '/../OperationLogPages.php';
require_once __DIR__ . '/../AdminOperationLogWriter.php';
require_once __DIR__ . '/../OperationLogTexts/SystemOperationLogTexts.php';
require_once __DIR__ . '/../../utils/RequestInput.php';
require_once __DIR__ . '/EmailTemplatesLogSnapshot.php';

class EmailTemplatesOperationLog {
    public static function shouldLog($input = null) {
        if (!is_array($input)) {
            return false;
        }
        $key = trim((string) ($input['logSubModuleKey'] ?? $input['operationLogSubModule'] ?? ''));
        return $key === OperationLogPages::subModuleKeyByAlias('page_email_templates');
    }

    public static function subModule() {
        return OperationLogPages::subModuleKeyByAlias('page_email_templates');
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
            } else {
                $raw = file_get_contents('php://input');
                if ($raw !== false && $raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $data = $decoded;
                    }
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

    public static function logCreateSuccess($input, array $state, $templateId, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::emailTemplateCreateSuccess($state);
        self::log($input, 'add', $zh, $en, $templateId, $operatorId);
    }

    public static function logUpdateSuccess($input, array $beforeState, array $afterState, $templateId, $operatorId = null) {
        $fpBefore = EmailTemplatesLogSnapshot::fingerprints($beforeState);
        $fpAfter = EmailTemplatesLogSnapshot::fingerprints($afterState);
        $changed = EmailTemplatesLogSnapshot::changedKeys($fpBefore, $fpAfter);
        if (empty($changed)) {
            return;
        }
        if ($changed === ['isActive']) {
            self::logToggleSuccess($input, $afterState, !empty($afterState['isActive']), $templateId, $operatorId);
            return;
        }
        $texts = SystemOperationLogTexts::emailTemplateUpdateSuccessDiff($beforeState, $afterState, $changed);
        if ($texts === null) {
            return;
        }
        list($zh, $en) = $texts;
        self::log($input, 'edit', $zh, $en, $templateId, $operatorId);
    }

    public static function logToggleSuccess($input, array $state, $enabled, $templateId, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::emailTemplateToggleSuccess($state, !!$enabled);
        self::log($input, $enabled ? 'enable' : 'disable', $zh, $en, $templateId, $operatorId);
    }

    public static function logDeleteSuccess($input, array $state, $templateId, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::emailTemplateDeleteSuccess($state);
        self::log($input, 'delete', $zh, $en, $templateId, $operatorId);
    }
}
