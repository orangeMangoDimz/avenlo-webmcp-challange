<?php
/**
 * IB 初审列表页 — 操作日志写入辅助（log_ib / ib_initial）
 *
 * 仅当请求显式携带 logSubModuleKey=ib_initial 时写入（邀请接口等多处共用）。
 */

require_once __DIR__ . '/../OperationLogPages.php';
require_once __DIR__ . '/../AdminOperationLogWriter.php';
require_once __DIR__ . '/../OperationLogTexts/IbOperationLogTexts.php';
require_once __DIR__ . '/../OperationLogTexts/OperationLogTextHelpers.php';
require_once __DIR__ . '/../../models/IbTierLevel.php';
require_once __DIR__ . '/../../models/IbCommissionRule.php';
require_once __DIR__ . '/../../models/ClientUser.php';

class IbInitialOperationLog {
    public static function shouldLog($input = null) {
        if (!is_array($input)) {
            return false;
        }
        $key = trim((string) ($input['logSubModuleKey'] ?? $input['operationLogSubModule'] ?? ''));
        return $key === OperationLogPages::subModuleKeyByAlias('page_ib_initial_review');
    }

    public static function subModule() {
        return OperationLogPages::subModuleKeyByAlias('page_ib_initial_review');
    }

    public static function log($input, $operationTypeKey, $clientId, $detailZh, $detailEn) {
        if (!self::shouldLog($input)) {
            return;
        }
        $cid = $clientId !== null ? (int) $clientId : 0;
        (new AdminOperationLogWriter())->logIbMutation(
            self::subModule(),
            $operationTypeKey,
            $cid > 0 ? $cid : null,
            $detailZh,
            $detailEn
        );
    }

    public static function logFailure($input, $operationTypeKey, $clientId, $failureMethod, $apiMessage) {
        if (!self::shouldLog($input)) {
            return;
        }
        list($detailZh, $detailEn) = call_user_func(
            ['IbOperationLogTexts', $failureMethod],
            $apiMessage
        );
        self::log($input, $operationTypeKey, $clientId, $detailZh, $detailEn);
    }

    /**
     * @param int[] $ruleIds
     */
    public static function resolveRuleNames(array $ruleIds) {
        $names = [];
        if (empty($ruleIds)) {
            return $names;
        }
        $model = new IbCommissionRule();
        foreach ($ruleIds as $ruleId) {
            $rid = (int) $ruleId;
            if ($rid <= 0) {
                continue;
            }
            $rule = $model->findById($rid);
            $name = is_array($rule) ? trim((string) ($rule['ruleName'] ?? '')) : '';
            $names[] = $name !== '' ? $name : ('规则#' . $rid);
        }
        return $names;
    }

    public static function resolveTierName($tierLevelId) {
        $tid = (int) $tierLevelId;
        if ($tid <= 0) {
            return '';
        }
        $tier = (new IbTierLevel())->findById($tid);
        if (!is_array($tier)) {
            return '';
        }
        $name = trim((string) ($tier['tierName'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $level = trim((string) ($tier['tierLevel'] ?? ''));
        return $level !== '' ? ('Tier ' . $level) : '';
    }

    public static function resolveClientDisplay($clientId) {
        $cid = (int) $clientId;
        if ($cid <= 0) {
            return ['', 0];
        }
        $client = (new ClientUser())->findById($cid);
        if (!is_array($client)) {
            return ['客户#' . $cid, $cid];
        }
        $display = OperationLogTextHelpers::formatClientDisplayName($client);
        if ($display === '') {
            $display = '客户#' . $cid;
        }
        return [$display, $cid];
    }

    /**
     * IR1 成功
     */
    public static function logSubmitInitialReviewSuccess(array $input, array $ibPartnerBefore, $ibPartnerId, array $updateData, array $ruleIds) {
        if (!self::shouldLog($input)) {
            return;
        }
        $clientId = (int) ($ibPartnerBefore['userId'] ?? 0);
        list($clientDisplay,) = self::resolveClientDisplay($clientId);
        $ibDisplay = AdminOperationLogWriter::formatIbPartnerDisplayName($ibPartnerBefore);
        $ibType = isset($updateData['ibType'])
            ? $updateData['ibType']
            : ($ibPartnerBefore['ibType'] ?? '');
        $tierLevelId = isset($updateData['tierLevelId'])
            ? $updateData['tierLevelId']
            : ($ibPartnerBefore['tierLevelId'] ?? null);
        $tierName = self::resolveTierName($tierLevelId);
        $ruleNames = self::resolveRuleNames($ruleIds);

        list($detailZh, $detailEn) = IbOperationLogTexts::initialReviewSubmitSuccess(
            $clientDisplay,
            $clientId,
            $ibDisplay,
            (int) $ibPartnerId,
            $ibType,
            $tierName,
            $ruleNames
        );
        self::log($input, 'approve', $clientId > 0 ? $clientId : null, $detailZh, $detailEn);
    }

    public static function logInvitationEmailSuccess(array $input, $clientId, $clientDisplay = '') {
        if (!self::shouldLog($input)) {
            return;
        }
        $cid = (int) $clientId;
        if ($clientDisplay === '' && $cid > 0) {
            list($clientDisplay,) = self::resolveClientDisplay($cid);
        }
        list($detailZh, $detailEn) = IbOperationLogTexts::invitationEmailSuccess($clientDisplay, $cid);
        self::log($input, 'add', $cid > 0 ? $cid : null, $detailZh, $detailEn);
    }

    public static function logInvitationSkipSignSuccess(array $input, $clientId, $ibPartnerId, $clientDisplay = '') {
        if (!self::shouldLog($input)) {
            return;
        }
        $cid = (int) $clientId;
        if ($clientDisplay === '' && $cid > 0) {
            list($clientDisplay,) = self::resolveClientDisplay($cid);
        }
        list($detailZh, $detailEn) = IbOperationLogTexts::invitationSkipSignSuccess(
            $clientDisplay,
            $cid,
            (int) $ibPartnerId
        );
        self::log($input, 'add', $cid > 0 ? $cid : null, $detailZh, $detailEn);
    }

    public static function logCreateMultiIbSuccess(
        array $input,
        array $sourceIb,
        $sourceIbId,
        array $client,
        $newIbPartnerId
    ) {
        if (!self::shouldLog($input)) {
            return;
        }
        $clientId = (int) ($sourceIb['userId'] ?? ($client['id'] ?? 0));
        $clientDisplay = OperationLogTextHelpers::formatClientDisplayName($client);
        if ($clientDisplay === '' && $clientId > 0) {
            $clientDisplay = '客户#' . $clientId;
        }
        $sourceIbDisplay = AdminOperationLogWriter::formatIbPartnerDisplayName($sourceIb);
        list($detailZh, $detailEn) = IbOperationLogTexts::createMultiIbSuccess(
            $clientDisplay,
            $clientId,
            $sourceIbDisplay,
            (int) $sourceIbId,
            (int) $newIbPartnerId
        );
        self::log($input, 'add', $clientId > 0 ? $clientId : null, $detailZh, $detailEn);
    }
}
