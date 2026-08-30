<?php
/**
 * IB 风控审核列表页 — 操作日志写入辅助（log_ib / ib_risk）
 *
 * 仅当请求显式携带 logSubModuleKey=ib_risk 时写入（reject 与终审列表共用）。
 */

require_once __DIR__ . '/../OperationLogPages.php';
require_once __DIR__ . '/../AdminOperationLogWriter.php';
require_once __DIR__ . '/../OperationLogTexts/IbOperationLogTexts.php';
require_once __DIR__ . '/../OperationLogTexts/OperationLogTextHelpers.php';
require_once __DIR__ . '/../../models/ClientUser.php';
require_once __DIR__ . '/../../models/TradingGroup.php';

class IbRiskOperationLog {
    public static function shouldLog($input = null) {
        if (!is_array($input)) {
            return false;
        }
        $key = trim((string) ($input['logSubModuleKey'] ?? $input['operationLogSubModule'] ?? ''));
        return $key === OperationLogPages::subModuleKeyByAlias('page_ib_risk_review');
    }

    public static function subModule() {
        return OperationLogPages::subModuleKeyByAlias('page_ib_risk_review');
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
     * @param int[] $groupIds
     */
    public static function resolveTradingGroupNames(array $groupIds) {
        $names = [];
        if (empty($groupIds)) {
            return $names;
        }
        $model = new TradingGroup();
        foreach ($groupIds as $groupId) {
            $gid = (int) $groupId;
            if ($gid <= 0) {
                continue;
            }
            $group = $model->findById($gid);
            if (!is_array($group)) {
                $names[] = '组别#' . $gid;
                continue;
            }
            $name = trim((string) ($group['name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($group['label'] ?? ''));
            }
            $names[] = $name !== '' ? $name : ('组别#' . $gid);
        }
        return $names;
    }

    /**
     * RR1 成功
     */
    public static function logSubmitRiskReviewSuccess(array $input, array $ibPartner, $ibPartnerId, array $groupIds) {
        if (!self::shouldLog($input)) {
            return;
        }
        $clientId = (int) ($ibPartner['userId'] ?? 0);
        list($clientDisplay,) = self::resolveClientDisplay($clientId);
        $ibDisplay = AdminOperationLogWriter::formatIbPartnerDisplayName($ibPartner);
        $groupNames = self::resolveTradingGroupNames($groupIds);

        list($detailZh, $detailEn) = IbOperationLogTexts::riskReviewSubmitSuccess(
            $clientDisplay,
            $clientId,
            $ibDisplay,
            (int) $ibPartnerId,
            $groupNames
        );
        self::log($input, 'approve', $clientId > 0 ? $clientId : null, $detailZh, $detailEn);
    }

    /**
     * RR2 成功（仅 pending_risk_review 驳回）
     */
    public static function logRiskRejectSuccess(array $input, array $ibPartner, $ibPartnerId) {
        if (!self::shouldLog($input)) {
            return;
        }
        $clientId = (int) ($ibPartner['userId'] ?? 0);
        list($clientDisplay,) = self::resolveClientDisplay($clientId);
        $ibDisplay = AdminOperationLogWriter::formatIbPartnerDisplayName($ibPartner);
        list($detailZh, $detailEn) = IbOperationLogTexts::riskReviewRejectSuccess(
            $clientDisplay,
            $clientId,
            $ibDisplay,
            (int) $ibPartnerId
        );
        self::log($input, 'reject', $clientId > 0 ? $clientId : null, $detailZh, $detailEn);
    }
}
