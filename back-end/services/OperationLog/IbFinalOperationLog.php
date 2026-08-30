<?php
/**
 * IB 终审列表页 — 操作日志写入辅助（log_ib / ib_final）
 *
 * 仅当请求显式携带 logSubModuleKey=ib_final 时写入（reject 与风控列表共用）。
 */

require_once __DIR__ . '/../OperationLogPages.php';
require_once __DIR__ . '/../AdminOperationLogWriter.php';
require_once __DIR__ . '/../OperationLogTexts/IbOperationLogTexts.php';
require_once __DIR__ . '/../OperationLogTexts/OperationLogTextHelpers.php';
require_once __DIR__ . '/IbInitialOperationLog.php';
require_once __DIR__ . '/IbRiskOperationLog.php';
require_once __DIR__ . '/../../models/ClientUser.php';
require_once __DIR__ . '/../../models/IbPartner.php';

class IbFinalOperationLog {
    public static function shouldLog($input = null) {
        if (!is_array($input)) {
            return false;
        }
        $key = trim((string) ($input['logSubModuleKey'] ?? $input['operationLogSubModule'] ?? ''));
        return $key === OperationLogPages::subModuleKeyByAlias('page_ib_final_review');
    }

    public static function subModule() {
        return OperationLogPages::subModuleKeyByAlias('page_ib_final_review');
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
     * @param int[] $childIds
     * @return string[]
     */
    public static function resolveChildIbLabels(array $childIds) {
        $labels = [];
        if (empty($childIds)) {
            return $labels;
        }
        $model = new IbPartner();
        foreach ($childIds as $childId) {
            $id = (int) $childId;
            if ($id <= 0) {
                continue;
            }
            $ib = $model->findById($id);
            if (!is_array($ib)) {
                $labels[] = 'IB#' . $id;
                continue;
            }
            $display = AdminOperationLogWriter::formatIbPartnerDisplayName($ib);
            $labels[] = $display !== '' ? "{$display}（ID {$id}）" : "IB（ID {$id}）";
        }
        return $labels;
    }

    /**
     * @param int[] $clientIds
     * @return string[]
     */
    public static function resolveBoundClientLabels(array $clientIds) {
        $labels = [];
        if (empty($clientIds)) {
            return $labels;
        }
        $model = new ClientUser();
        foreach ($clientIds as $clientId) {
            $id = (int) $clientId;
            if ($id <= 0) {
                continue;
            }
            $client = $model->findById($id);
            if (!is_array($client)) {
                $labels[] = '客户#' . $id;
                continue;
            }
            $display = OperationLogTextHelpers::formatClientDisplayName($client);
            if ($display === '') {
                $display = '客户#' . $id;
            }
            $labels[] = "{$display}（ID {$id}）";
        }
        return $labels;
    }

    /**
     * FR1 成功
     */
    public static function logApproveSuccess(array $input, array $ibPartner, $ibPartnerId, $ibCode) {
        if (!self::shouldLog($input)) {
            return;
        }
        $clientId = (int) ($ibPartner['userId'] ?? 0);
        list($clientDisplay,) = self::resolveClientDisplay($clientId);
        $ibDisplay = AdminOperationLogWriter::formatIbPartnerDisplayName($ibPartner);
        list($detailZh, $detailEn) = IbOperationLogTexts::finalReviewApproveSuccess(
            $clientDisplay,
            $clientId,
            $ibDisplay,
            (int) $ibPartnerId,
            trim((string) $ibCode)
        );
        self::log($input, 'approve', $clientId > 0 ? $clientId : null, $detailZh, $detailEn);
    }

    /**
     * FR2 成功（仅 pending_final_review 驳回）
     */
    public static function logFinalRejectSuccess(array $input, array $ibPartner, $ibPartnerId) {
        if (!self::shouldLog($input)) {
            return;
        }
        $clientId = (int) ($ibPartner['userId'] ?? 0);
        list($clientDisplay,) = self::resolveClientDisplay($clientId);
        $ibDisplay = AdminOperationLogWriter::formatIbPartnerDisplayName($ibPartner);
        list($detailZh, $detailEn) = IbOperationLogTexts::finalReviewRejectSuccess(
            $clientDisplay,
            $clientId,
            $ibDisplay,
            (int) $ibPartnerId
        );
        self::log($input, 'reject', $clientId > 0 ? $clientId : null, $detailZh, $detailEn);
    }

    /**
     * FR3 成功
     */
    public static function logBindSuccess(
        array $input,
        array $parentIb,
        $parentIbId,
        array $childIds,
        array $clientIds,
        $ibBoundCount,
        $clientBoundCount
    ) {
        if (!self::shouldLog($input)) {
            return;
        }
        $clientId = (int) ($parentIb['userId'] ?? 0);
        list($clientDisplay,) = self::resolveClientDisplay($clientId);
        $parentDisplay = AdminOperationLogWriter::formatIbPartnerDisplayName($parentIb);
        $childLabels = self::resolveChildIbLabels($childIds);
        $clientLabels = self::resolveBoundClientLabels($clientIds);
        list($detailZh, $detailEn) = IbOperationLogTexts::finalReviewBindSuccess(
            $clientDisplay,
            $clientId,
            $parentDisplay,
            (int) $parentIbId,
            $childLabels,
            $clientLabels,
            (int) $ibBoundCount,
            (int) $clientBoundCount
        );
        self::log($input, 'edit', $clientId > 0 ? $clientId : null, $detailZh, $detailEn);
    }

    /**
     * FR4 成功
     */
    public static function logUnbindSuccess(
        array $input,
        array $parentIb,
        $parentIbId,
        array $childIds,
        array $clientIds,
        $ibUnboundCount,
        $clientUnboundCount
    ) {
        if (!self::shouldLog($input)) {
            return;
        }
        $clientId = (int) ($parentIb['userId'] ?? 0);
        list($clientDisplay,) = self::resolveClientDisplay($clientId);
        $parentDisplay = AdminOperationLogWriter::formatIbPartnerDisplayName($parentIb);
        $childLabels = self::resolveChildIbLabels($childIds);
        $clientLabels = self::resolveBoundClientLabels($clientIds);
        list($detailZh, $detailEn) = IbOperationLogTexts::finalReviewUnbindSuccess(
            $clientDisplay,
            $clientId,
            $parentDisplay,
            (int) $parentIbId,
            $childLabels,
            $clientLabels,
            (int) $ibUnboundCount,
            (int) $clientUnboundCount
        );
        self::log($input, 'edit', $clientId > 0 ? $clientId : null, $detailZh, $detailEn);
    }

    /**
     * FR5 成功
     */
    public static function logPostApprovalUpdateSuccess(
        array $input,
        array $ibPartnerBefore,
        $ibPartnerId,
        array $updateData,
        array $ruleIds,
        $ruleIdsProvided,
        $groupIds,
        $groupIdsProvided
    ) {
        if (!self::shouldLog($input)) {
            return;
        }
        $clientId = (int) ($ibPartnerBefore['userId'] ?? 0);
        list($clientDisplay,) = self::resolveClientDisplay($clientId);
        $ibDisplay = AdminOperationLogWriter::formatIbPartnerDisplayName($ibPartnerBefore);

        $oldIbType = trim((string) ($ibPartnerBefore['ibType'] ?? ''));
        $newIbType = isset($updateData['ibType']) ? trim((string) $updateData['ibType']) : $oldIbType;

        $oldTierId = isset($ibPartnerBefore['tierLevelId']) && $ibPartnerBefore['tierLevelId'] !== '' && $ibPartnerBefore['tierLevelId'] !== null
            ? (int) $ibPartnerBefore['tierLevelId'] : 0;
        $newTierId = isset($updateData['tierLevelId']) ? (int) $updateData['tierLevelId'] : $oldTierId;

        $oldTierName = IbInitialOperationLog::resolveTierName($oldTierId);
        $newTierName = IbInitialOperationLog::resolveTierName($newTierId);

        $ruleNames = $ruleIdsProvided ? IbInitialOperationLog::resolveRuleNames($ruleIds) : [];
        $groupNames = $groupIdsProvided && is_array($groupIds) ? IbRiskOperationLog::resolveTradingGroupNames($groupIds) : [];

        list($detailZh, $detailEn) = IbOperationLogTexts::finalReviewPostApprovalUpdateSuccess(
            $clientDisplay,
            $clientId,
            $ibDisplay,
            (int) $ibPartnerId,
            $oldIbType,
            $newIbType,
            $oldTierName,
            $newTierName,
            $ruleIdsProvided,
            $ruleNames,
            $groupIdsProvided,
            $groupNames
        );
        self::log($input, 'edit', $clientId > 0 ? $clientId : null, $detailZh, $detailEn);
    }
}
