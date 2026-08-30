<?php
/**
 * 系统设置 — 登录页设置（log_system / login_page_settings）
 *
 * 仅当请求显式携带 logSubModuleKey=login_page_settings 时写入；targetId 恒为 null。
 */

require_once __DIR__ . '/../OperationLogPages.php';
require_once __DIR__ . '/../AdminOperationLogWriter.php';
require_once __DIR__ . '/../OperationLogTexts/SystemOperationLogTexts.php';
require_once __DIR__ . '/LoginPageSettingsLogSnapshot.php';

class LoginPageSettingsOperationLog {
    public static function shouldLog($input = null) {
        if (!is_array($input)) {
            return false;
        }
        $key = trim((string) ($input['logSubModuleKey'] ?? $input['operationLogSubModule'] ?? ''));
        return $key === OperationLogPages::subModuleKeyByAlias('page_login_page_settings');
    }

    public static function subModule() {
        return OperationLogPages::subModuleKeyByAlias('page_login_page_settings');
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

    public static function logBrandingEditSuccess($input, array $beforeState, array $afterState, $operatorId = null) {
        $texts = SystemOperationLogTexts::loginPageBrandingEditSuccessDiff($beforeState, $afterState);
        if ($texts === null) {
            return;
        }
        list($zh, $en) = $texts;
        self::log($input, 'edit', $zh, $en, $operatorId);
    }

    public static function logBrandingUploadLogoSuccess($input, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::loginPageBrandingUploadLogoSuccess();
        self::log($input, 'edit', $zh, $en, $operatorId);
    }

    public static function logFormFieldAddSuccess($input, $fieldName, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::loginPageFormFieldAddSuccess($fieldName);
        self::log($input, 'add', $zh, $en, $operatorId);
    }

    public static function logFormFieldEditSuccess($input, array $beforeState, array $afterState, $fieldName, $operatorId = null) {
        $fpBefore = LoginPageSettingsLogSnapshot::formFieldFingerprints($beforeState);
        $fpAfter = LoginPageSettingsLogSnapshot::formFieldFingerprints($afterState);
        $changed = LoginPageSettingsLogSnapshot::changedKeys(
            $fpBefore,
            $fpAfter,
            ['isEnabled', 'isRequired', 'fieldName', 'fieldDescription', 'fieldType']
        );
        if (empty($changed)) {
            return;
        }
        if ($changed === ['isEnabled']) {
            $enabled = !empty($afterState['isEnabled']);
            list($zh, $en) = SystemOperationLogTexts::loginPageFormFieldToggleSuccess($fieldName, $enabled);
            self::log($input, $enabled ? 'enable' : 'disable', $zh, $en, $operatorId);
            return;
        }
        list($zh, $en) = SystemOperationLogTexts::loginPageFormFieldEditSuccess($fieldName);
        self::log($input, 'edit', $zh, $en, $operatorId);
    }

    public static function logFormFieldDeleteSuccess($input, $fieldName, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::loginPageFormFieldDeleteSuccess($fieldName);
        self::log($input, 'delete', $zh, $en, $operatorId);
    }

    public static function logFormFieldsOrderSuccess($input, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::loginPageFormFieldsOrderSuccess();
        self::log($input, 'edit', $zh, $en, $operatorId);
    }

    public static function logPasswordLevelApplySuccess($input, $level, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::loginPagePasswordLevelApplySuccess($level);
        self::log($input, 'edit', $zh, $en, $operatorId);
    }

    public static function logCountriesStatusSuccess($input, $enabledCount, $disabledCount, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::loginPageCountriesStatusSuccess(
            (int) $enabledCount,
            (int) $disabledCount
        );
        self::log($input, 'edit', $zh, $en, $operatorId);
    }

    public static function logLegalDocAddSuccess($input, $title, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::loginPageLegalDocAddSuccess($title);
        self::log($input, 'add', $zh, $en, $operatorId);
    }

    public static function logLegalDocEditSuccess($input, $title, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::loginPageLegalDocEditSuccess($title);
        self::log($input, 'edit', $zh, $en, $operatorId);
    }

    public static function logLegalDocDeleteSuccess($input, $title, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::loginPageLegalDocDeleteSuccess($title);
        self::log($input, 'delete', $zh, $en, $operatorId);
    }

    public static function logLanguagePackUploadSuccess($input, $languageName, $languageCode, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::loginPageLanguagePackUploadSuccess($languageName, $languageCode);
        self::log($input, 'add', $zh, $en, $operatorId);
    }

    public static function logLanguagePackEditSuccess($input, $languageCode, $beforeEnabled, $afterEnabled, $operatorId = null) {
        $before = !empty($beforeEnabled);
        $after = !empty($afterEnabled);
        if ($before === $after) {
            return;
        }
        list($zh, $en) = SystemOperationLogTexts::loginPageLanguagePackToggleSuccess($languageCode, $after);
        self::log($input, $after ? 'enable' : 'disable', $zh, $en, $operatorId);
    }

    public static function logDefaultLanguageSetSuccess($input, $languageCode, $operatorId = null) {
        list($zh, $en) = SystemOperationLogTexts::loginPageDefaultLanguageSetSuccess($languageCode);
        self::log($input, 'edit', $zh, $en, $operatorId);
    }

    public static function logIpLanguageDetectionEditSuccess($input, array $beforeState, array $afterState, $operatorId = null) {
        $texts = SystemOperationLogTexts::loginPageIpLanguageDetectionEditSuccessDiff($beforeState, $afterState);
        if ($texts === null) {
            return;
        }
        list($zh, $en) = $texts;
        $before = LoginPageSettingsLogSnapshot::ipLanguageFromRow($beforeState);
        $after = LoginPageSettingsLogSnapshot::ipLanguageFromRow($afterState);
        $fpBefore = ['isEnabled' => !empty($before['isEnabled']) ? '1' : '0'];
        $fpAfter = ['isEnabled' => !empty($after['isEnabled']) ? '1' : '0'];
        $onlyToggle = LoginPageSettingsLogSnapshot::changedKeys($fpBefore, $fpAfter, ['isEnabled'])
            && trim((string) ($before['defaultLanguageCode'] ?? '')) === trim((string) ($after['defaultLanguageCode'] ?? ''))
            && trim((string) ($before['fallbackLanguageCode'] ?? '')) === trim((string) ($after['fallbackLanguageCode'] ?? ''));
        $op = 'edit';
        if ($onlyToggle) {
            $op = !empty($after['isEnabled']) ? 'enable' : 'disable';
        }
        self::log($input, $op, $zh, $en, $operatorId);
    }

    public static function logEmailVerificationEditSuccess($input, array $beforeState, array $afterState, $operatorId = null) {
        $texts = SystemOperationLogTexts::loginPageEmailVerificationEditSuccessDiff($beforeState, $afterState);
        if ($texts === null) {
            return;
        }
        list($zh, $en) = $texts;
        $changed = SystemOperationLogTexts::loginPageEmailVerificationChangedKeys($beforeState, $afterState);
        $op = 'edit';
        if ($changed === ['isRequired']) {
            $op = !empty($afterState['isRequired']) ? 'enable' : 'disable';
        }
        self::log($input, $op, $zh, $en, $operatorId);
    }
}
