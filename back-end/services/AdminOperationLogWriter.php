<?php
/**
 * 后台操作日志写入（各业务在关键操作后调用）
 */

require_once __DIR__ . '/../models/AdminOperationLog.php';
require_once __DIR__ . '/../models/AdminOperationLogModuleSetting.php';
require_once __DIR__ . '/../models/AdminDictionaryItem.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/OperationLogTexts/OperationLogTextHelpers.php';
require_once __DIR__ . '/OperationLogTexts/ClientOperationLogTexts.php';
require_once __DIR__ . '/OperationLogTexts/KycOperationLogTexts.php';
require_once __DIR__ . '/OperationLogTexts/TransactionOperationLogTexts.php';
require_once __DIR__ . '/OperationLogTexts/SalesOperationLogTexts.php';

class AdminOperationLogWriter {
    /** @deprecated 使用 OperationLogTextHelpers::MAX_ID_LIST_CHARS */
    public const MAX_ID_LIST_CHARS = OperationLogTextHelpers::MAX_ID_LIST_CHARS;

    private $logModel;
    private $moduleSettingModel;
    private $dictModel;

    public function __construct() {
        $this->logModel = new AdminOperationLog();
        $this->moduleSettingModel = new AdminOperationLogModuleSetting();
        $this->dictModel = new AdminDictionaryItem();
    }

    /**
     * 记录一条操作日志；模块未启动或非管理员时静默跳过。
     *
     * @param array{
     *   modelKey:string,
     *   subModuleKey:string,
     *   operationTypeKey:string,
     *   detailZh?:string|null,
     *   detailEn?:string|null,
     *   targetId?:int|null
     * } $params
     * @return int|false 新日志 ID，未写入时 false
     */
    /**
     * @param bool $bypassModuleLoggingGate 为 true 时不检查 isLoggingEnabled（仅日志设置 meta 记录使用）
     */
    public function record(array $params, $bypassModuleLoggingGate = false) {
        $modelKey = trim((string) ($params['modelKey'] ?? ''));
        $subModuleKey = trim((string) ($params['subModuleKey'] ?? ''));
        $operationTypeKey = trim((string) ($params['operationTypeKey'] ?? ''));
        if ($modelKey === '' || $subModuleKey === '' || $operationTypeKey === '') {
            return false;
        }

        if (!$bypassModuleLoggingGate && !$this->moduleSettingModel->isLoggingEnabled($modelKey)) {
            return false;
        }

        $operatorId = isset($params['operatorId']) && (int) $params['operatorId'] > 0
            ? (int) $params['operatorId']
            : $this->resolveAdminOperatorId();
        if ($operatorId <= 0) {
            return false;
        }

        $module = $this->moduleSettingModel->findByModelKey($modelKey);
        if (!$module) {
            return false;
        }

        $subLabels = $this->resolveSubModuleLabels($modelKey, $subModuleKey);
        $nowUtc = gmdate('Y-m-d H:i:s');
        $targetId = isset($params['targetId']) ? (int) $params['targetId'] : null;
        if ($targetId !== null && $targetId <= 0) {
            $targetId = null;
        }

        return $this->logModel->create([
            'operatorId' => $operatorId,
            'modelKey' => $modelKey,
            'moduleNameZh' => (string) ($module['moduleNameZh'] ?? ''),
            'moduleNameEn' => (string) ($module['moduleNameEn'] ?? ''),
            'subModuleKey' => $subModuleKey,
            'subModuleNameZh' => $subLabels['zh'],
            'subModuleNameEn' => $subLabels['en'],
            'operationTypeKey' => $operationTypeKey,
            'targetId' => $targetId,
            'detailZh' => (string) ($params['detailZh'] ?? ''),
            'detailEn' => (string) ($params['detailEn'] ?? ''),
            'ipAddress' => $this->resolveClientIp(),
            'operatedAt' => $nowUtc,
            'createdAt' => $nowUtc,
        ]);
    }

    /**
     * 报表模块 — 导出操作
     */
    public function logReportExport($subModuleKey, $detailZh, $detailEn) {
        return $this->record([
            'modelKey' => 'log_report',
            'subModuleKey' => $subModuleKey,
            'operationTypeKey' => 'export',
            'detailZh' => $detailZh,
            'detailEn' => $detailEn,
            'targetId' => null,
        ]);
    }

    /**
     * 从请求体解析子模块（按 modelKey 校验字典 operation_log_sub_module_{modelKey}）
     */
    public static function resolveSubModuleKey($data, $modelKey, $defaultSubModule) {
        $modelKey = trim((string) $modelKey);
        $defaultSubModule = trim((string) $defaultSubModule);
        if ($defaultSubModule === '') {
            return '';
        }
        if (!is_array($data)) {
            return $defaultSubModule;
        }
        $key = trim((string) ($data['logSubModuleKey'] ?? $data['operationLogSubModule'] ?? ''));
        if ($key === '') {
            return $defaultSubModule;
        }
        if ($modelKey === '') {
            return $defaultSubModule;
        }
        $validKeys = self::getValidSubModuleKeysForModel($modelKey);
        if (in_array($key, $validKeys, true)) {
            return $key;
        }
        return $defaultSubModule;
    }

    /**
     * @return string[]
     */
    private static function getValidSubModuleKeysForModel($modelKey) {
        static $cache = [];
        $modelKey = trim((string) $modelKey);
        if ($modelKey === '') {
            return [];
        }
        if (isset($cache[$modelKey])) {
            return $cache[$modelKey];
        }
        $dict = new AdminDictionaryItem();
        $options = $dict->findOptionsByGroupAndCode(
            AdminDictionaryItem::GROUP_OPERATION_LOG,
            AdminDictionaryItem::CODE_SUB_MODULE_PREFIX . $modelKey
        );
        $keys = [];
        foreach ($options as $opt) {
            $k = trim((string) ($opt['value'] ?? ''));
            if ($k !== '') {
                $keys[] = $k;
            }
        }
        $cache[$modelKey] = $keys;
        return $keys;
    }

    public static function entityLabelZh($subModuleKey) {
        return OperationLogTextHelpers::entityLabelZh($subModuleKey);
    }

    public static function entityLabelEn($subModuleKey) {
        return OperationLogTextHelpers::entityLabelEn($subModuleKey);
    }

    public static function formatIbPartnerDisplayName(array $row) {
        return OperationLogTextHelpers::formatIbPartnerDisplayName($row);
    }

    public static function formatClientIdList(array $ids) {
        return OperationLogTextHelpers::formatClientIdList($ids);
    }

    public static function formatClientDisplayName(array $row) {
        return OperationLogTextHelpers::formatClientDisplayName($row);
    }

    public static function formatSalesDisplayName(array $row) {
        $name = trim((string) ($row['fullName'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $username = trim((string) ($row['username'] ?? ''));
        return $username !== '' ? $username : 'Sales';
    }

    public static function formatChangeSummaryZh(array $changes) {
        return OperationLogTextHelpers::formatChangeSummaryZh($changes);
    }

    public static function formatChangeSummaryEn(array $changes) {
        return OperationLogTextHelpers::formatChangeSummaryEn($changes);
    }

    public function logClientAdd($subModuleKey, $targetId, $detailZh, $detailEn) {
        return $this->record([
            'modelKey' => 'log_client',
            'subModuleKey' => $subModuleKey,
            'operationTypeKey' => 'add',
            'targetId' => $targetId,
            'detailZh' => $detailZh,
            'detailEn' => $detailEn,
        ]);
    }

    public function logClientEdit($subModuleKey, $targetId, $detailZh, $detailEn) {
        return $this->record([
            'modelKey' => 'log_client',
            'subModuleKey' => $subModuleKey,
            'operationTypeKey' => 'edit',
            'targetId' => $targetId,
            'detailZh' => $detailZh,
            'detailEn' => $detailEn,
        ]);
    }

    public function logClientDelete($subModuleKey, $detailZh, $detailEn) {
        return $this->record([
            'modelKey' => 'log_client',
            'subModuleKey' => $subModuleKey,
            'operationTypeKey' => 'delete',
            'targetId' => null,
            'detailZh' => $detailZh,
            'detailEn' => $detailEn,
        ]);
    }

    public function logClientView($subModuleKey, $targetId, $detailZh, $detailEn) {
        return $this->record([
            'modelKey' => 'log_client',
            'subModuleKey' => $subModuleKey,
            'operationTypeKey' => 'view',
            'targetId' => $targetId,
            'detailZh' => $detailZh,
            'detailEn' => $detailEn,
        ]);
    }

    public function logClientExport($subModuleKey, $detailZh, $detailEn) {
        return $this->record([
            'modelKey' => 'log_client',
            'subModuleKey' => $subModuleKey,
            'operationTypeKey' => 'export',
            'targetId' => null,
            'detailZh' => $detailZh,
            'detailEn' => $detailEn,
        ]);
    }

    public function logClientNotify($subModuleKey, $targetId, $detailZh, $detailEn) {
        return $this->record([
            'modelKey' => 'log_client',
            'subModuleKey' => $subModuleKey,
            'operationTypeKey' => 'notify',
            'targetId' => $targetId,
            'detailZh' => $detailZh,
            'detailEn' => $detailEn,
        ]);
    }

    public function logClientAssign($subModuleKey, $targetId, $detailZh, $detailEn) {
        return $this->record([
            'modelKey' => 'log_client',
            'subModuleKey' => $subModuleKey,
            'operationTypeKey' => 'assign',
            'targetId' => $targetId,
            'detailZh' => $detailZh,
            'detailEn' => $detailEn,
        ]);
    }

    public function logClientCreated($subModuleKey, $clientRow, $clientId, $success = true, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = ClientOperationLogTexts::clientCreated($clientRow, $clientId);
            $targetId = (int) $clientId;
        } else {
            list($detailZh, $detailEn) = ClientOperationLogTexts::clientCreatedFailure($failureMessage);
            $targetId = null;
        }
        return $this->logClientAdd($subModuleKey, $targetId, $detailZh, $detailEn);
    }

    public function logClientAssignBulk($subModuleKey, array $clientIds, $managerName, $notes = '', $success = true, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = ClientOperationLogTexts::assignBulk(
                $subModuleKey,
                $clientIds,
                $managerName,
                $notes
            );
        } else {
            list($detailZh, $detailEn) = ClientOperationLogTexts::assignBulkFailure($failureMessage);
        }
        return $this->logClientAssign($subModuleKey, null, $detailZh, $detailEn);
    }

    public function logClientAssignSingle($subModuleKey, $clientId, $managerName, $notes = '', $success = true, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = ClientOperationLogTexts::assignSingle(
                $subModuleKey,
                $clientId,
                $managerName,
                $notes
            );
            $targetId = (int) $clientId;
        } else {
            list($detailZh, $detailEn) = ClientOperationLogTexts::assignSingleFailure($failureMessage);
            $targetId = $clientId > 0 ? (int) $clientId : null;
        }
        return $this->logClientAssign($subModuleKey, $targetId, $detailZh, $detailEn);
    }

    public function logClientTagBulk($subModuleKey, array $clientIds, $tagName, $success = true, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = ClientOperationLogTexts::tagBulk($subModuleKey, $clientIds, $tagName);
            $targetId = count($clientIds) === 1 ? (int) $clientIds[0] : null;
        } else {
            list($detailZh, $detailEn) = ClientOperationLogTexts::tagBulkFailure($failureMessage);
            $targetId = null;
        }
        return $this->logClientEdit($subModuleKey, $targetId, $detailZh, $detailEn);
    }

    public function logClientTagRemove($subModuleKey, $clientId, $tagName, $success = true, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = ClientOperationLogTexts::tagRemove($clientId, $tagName);
        } else {
            list($detailZh, $detailEn) = ClientOperationLogTexts::tagRemoveFailure($failureMessage);
        }
        return $this->logClientEdit($subModuleKey, $this->positiveTargetId($clientId), $detailZh, $detailEn);
    }

    public function logClientProfileUpdate($subModuleKey, $clientId, $displayName, array $changes, $success = true, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = ClientOperationLogTexts::profileUpdate($clientId, $displayName, $changes);
        } else {
            list($detailZh, $detailEn) = ClientOperationLogTexts::profileUpdateFailure($failureMessage);
        }
        return $this->logClientEdit($subModuleKey, $this->positiveTargetId($clientId), $detailZh, $detailEn);
    }

    public function logClientPasswordResetEmail($subModuleKey, $clientId, $email, $emailSent = true, $success = true, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = ClientOperationLogTexts::passwordResetEmail($clientId, $email, $emailSent);
        } else {
            list($detailZh, $detailEn) = ClientOperationLogTexts::passwordResetEmailFailure($failureMessage);
        }
        return $this->logClientNotify($subModuleKey, $this->positiveTargetId($clientId), $detailZh, $detailEn);
    }

    public function logClientViewAsClient($subModuleKey, $clientId, $displayName, $success = true, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = ClientOperationLogTexts::viewAsClient($clientId, $displayName);
        } else {
            list($detailZh, $detailEn) = ClientOperationLogTexts::viewAsClientFailure($failureMessage);
        }
        return $this->logClientView($subModuleKey, $this->positiveTargetId($clientId), $detailZh, $detailEn);
    }

    public function logClientLeadsExport($subModuleKey, $count, $format) {
        list($detailZh, $detailEn) = ClientOperationLogTexts::leadsExport($count, $format);
        return $this->logClientExport($subModuleKey, $detailZh, $detailEn);
    }

    public function logClientNotificationSingle(
        $subModuleKey,
        $clientId,
        $displayName,
        $channelsZh,
        $channelsEn,
        $scheduleZh,
        $scheduleEn,
        $success = true,
        $failureMessage = ''
    ) {
        if ($success) {
            list($detailZh, $detailEn) = ClientOperationLogTexts::notificationSingle(
                $clientId,
                $displayName,
                $channelsZh,
                $channelsEn,
                $scheduleZh,
                $scheduleEn
            );
        } else {
            list($detailZh, $detailEn) = ClientOperationLogTexts::notificationSingleFailure($failureMessage);
        }
        return $this->logClientNotify($subModuleKey, $this->positiveTargetId($clientId), $detailZh, $detailEn);
    }

    public function logClientNotificationBulk(
        $subModuleKey,
        array $clientIds,
        $channelsZh,
        $channelsEn,
        $scheduleZh,
        $scheduleEn,
        $successCount,
        $failCount,
        $success = true,
        $failureMessage = ''
    ) {
        if ($success) {
            list($detailZh, $detailEn) = ClientOperationLogTexts::notificationBulk(
                $clientIds,
                $channelsZh,
                $channelsEn,
                $scheduleZh,
                $scheduleEn,
                $successCount,
                $failCount
            );
        } else {
            list($detailZh, $detailEn) = ClientOperationLogTexts::notificationBulkFailure($failureMessage);
        }
        return $this->logClientNotify($subModuleKey, null, $detailZh, $detailEn);
    }

    public function logSearchTagCreate($subModuleKey, $tagName, $keywords, $success = true, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = ClientOperationLogTexts::searchTagCreate($tagName, $keywords);
        } else {
            list($detailZh, $detailEn) = ClientOperationLogTexts::searchTagCreateFailure($failureMessage);
        }
        return $this->logClientAdd($subModuleKey, null, $detailZh, $detailEn);
    }

    public function logSearchTagDelete($subModuleKey, $tagName, $success = true, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = ClientOperationLogTexts::searchTagDelete($tagName);
        } else {
            list($detailZh, $detailEn) = ClientOperationLogTexts::searchTagDeleteFailure($failureMessage);
        }
        return $this->logClientDelete($subModuleKey, $detailZh, $detailEn);
    }

    public function logClientTradingAccountCreate(
        $subModuleKey,
        $clientId,
        $platform,
        $loginOrNickname,
        $success = true,
        $failureMessage = ''
    ) {
        if ($success) {
            list($detailZh, $detailEn) = ClientOperationLogTexts::tradingAccountCreate(
                $clientId,
                $platform,
                $loginOrNickname
            );
        } else {
            list($detailZh, $detailEn) = ClientOperationLogTexts::tradingAccountCreateFailure($failureMessage);
        }
        return $this->logClientAdd($subModuleKey, (int) $clientId > 0 ? (int) $clientId : null, $detailZh, $detailEn);
    }

    public function logIbPartnerProfileUpdate(
        $subModuleKey,
        $ibPartnerId,
        $displayName,
        array $changes,
        $success = true,
        $failureMessage = ''
    ) {
        $id = (int) $ibPartnerId;
        if ($success) {
            $summaryZh = OperationLogTextHelpers::formatChangeSummaryZh($changes);
            $summaryEn = OperationLogTextHelpers::formatChangeSummaryEn($changes);
            $detailZh = "更新 IB 资料：{$displayName}；变更：{$summaryZh}；IB ID：{$id}";
            $detailEn = "Updated IB profile: {$displayName}; changes: {$summaryEn}; IB ID: {$id}";
        } else {
            list($detailZh, $detailEn) = ClientOperationLogTexts::ibPartnerProfileUpdateFailure($failureMessage);
        }
        return $this->logClientEdit($subModuleKey, $id > 0 ? $id : null, $detailZh, $detailEn);
    }

    public function logIbReferralSuffixUpdate(
        $subModuleKey,
        $ibPartnerId,
        $displayName,
        $oldSuffix,
        $newSuffix,
        $success = true,
        $failureMessage = ''
    ) {
        $id = (int) $ibPartnerId;
        if ($success) {
            $oldSuffix = (string) $oldSuffix;
            $newSuffix = (string) $newSuffix;
            $detailZh = "更新 IB 推荐链接后缀：{$displayName}；{$oldSuffix} → {$newSuffix}；IB ID：{$id}";
            $detailEn = "Updated IB referral suffix: {$displayName}; {$oldSuffix} → {$newSuffix}; IB ID: {$id}";
        } else {
            list($detailZh, $detailEn) = ClientOperationLogTexts::ibReferralSuffixUpdateFailure($failureMessage);
        }
        return $this->logClientEdit($subModuleKey, $id > 0 ? $id : null, $detailZh, $detailEn);
    }

    public function logSalesReferralSuffixUpdate(
        $subModuleKey,
        $salesId,
        $displayName,
        $oldSuffix,
        $newSuffix,
        $success = true,
        $failureMessage = ''
    ) {
        $id = (int) $salesId;
        if ($success) {
            list($detailZh, $detailEn) = SalesOperationLogTexts::referralSuffixUpdate(
                $id,
                $displayName,
                (string) $oldSuffix,
                (string) $newSuffix
            );
        } else {
            list($detailZh, $detailEn) = SalesOperationLogTexts::referralSuffixUpdateFailure($failureMessage);
        }
        return $this->logSalesEdit($subModuleKey, $this->positiveTargetId($id), $detailZh, $detailEn);
    }

    public function logSalesEdit($subModuleKey, $targetId, $detailZh, $detailEn) {
        return $this->record([
            'modelKey' => 'log_sales',
            'subModuleKey' => $subModuleKey,
            'operationTypeKey' => 'edit',
            'targetId' => $targetId,
            'detailZh' => $detailZh,
            'detailEn' => $detailEn,
        ]);
    }

    private function logKyc($subModuleKey, $operationTypeKey, $targetId, $detailZh, $detailEn) {
        return $this->record([
            'modelKey' => 'log_kyc',
            'subModuleKey' => $subModuleKey,
            'operationTypeKey' => $operationTypeKey,
            'targetId' => $targetId,
            'detailZh' => $detailZh,
            'detailEn' => $detailEn,
        ]);
    }

    public function logKycSubmissionApprove(
        $subModuleKey,
        $clientId,
        $submissionId,
        $templateName,
        $notes = null,
        $success = true,
        $failureMessage = ''
    ) {
        $cid = (int) $clientId;
        if ($success) {
            list($detailZh, $detailEn) = KycOperationLogTexts::submissionApprove(
                $clientId,
                $submissionId,
                $templateName,
                $notes
            );
        } else {
            list($detailZh, $detailEn) = KycOperationLogTexts::submissionApproveFailure($failureMessage);
        }
        return $this->logKyc(
            $subModuleKey,
            'approve',
            $cid > 0 ? $cid : null,
            $detailZh,
            $detailEn
        );
    }

    public function logKycSubmissionReject(
        $subModuleKey,
        $clientId,
        $submissionId,
        $templateName,
        $reason,
        $success = true,
        $failureMessage = ''
    ) {
        $cid = (int) $clientId;
        if ($success) {
            list($detailZh, $detailEn) = KycOperationLogTexts::submissionReject(
                $clientId,
                $submissionId,
                $templateName,
                $reason
            );
        } else {
            list($detailZh, $detailEn) = KycOperationLogTexts::submissionRejectFailure($failureMessage);
        }
        return $this->logKyc(
            $subModuleKey,
            'reject',
            $cid > 0 ? $cid : null,
            $detailZh,
            $detailEn
        );
    }

    public function logKycSubmissionNeedDocs(
        $subModuleKey,
        $clientId,
        $submissionId,
        $templateName,
        $itemCount,
        $success = true,
        $failureMessage = ''
    ) {
        $cid = (int) $clientId;
        if ($success) {
            list($detailZh, $detailEn) = KycOperationLogTexts::submissionNeedDocs(
                $clientId,
                $submissionId,
                $templateName,
                $itemCount
            );
        } else {
            list($detailZh, $detailEn) = KycOperationLogTexts::submissionNeedDocsFailure($failureMessage);
        }
        return $this->logKyc(
            $subModuleKey,
            'edit',
            $cid > 0 ? $cid : null,
            $detailZh,
            $detailEn
        );
    }

    public function logKycReviewerAssignBulk(
        $subModuleKey,
        array $clientIds,
        $submissionCount,
        $reviewerName,
        $notes = '',
        $success = true,
        $failureMessage = ''
    ) {
        if ($success) {
            list($detailZh, $detailEn) = KycOperationLogTexts::reviewerAssignBulk(
                $clientIds,
                $submissionCount,
                $reviewerName,
                $notes
            );
        } else {
            list($detailZh, $detailEn) = KycOperationLogTexts::reviewerAssignBulkFailure($failureMessage);
        }
        return $this->logKyc($subModuleKey, 'assign', null, $detailZh, $detailEn);
    }

    public function logKycSubmissionApproveBulk(
        $subModuleKey,
        array $clientIds,
        $submissionCount,
        $successCount,
        $notes = '',
        $success = true,
        $failureMessage = ''
    ) {
        if ($success) {
            list($detailZh, $detailEn) = KycOperationLogTexts::submissionApproveBulk(
                $clientIds,
                $submissionCount,
                $successCount,
                $notes
            );
        } else {
            list($detailZh, $detailEn) = KycOperationLogTexts::submissionApproveBulkFailure($failureMessage);
        }
        return $this->logKyc($subModuleKey, 'approve', null, $detailZh, $detailEn);
    }

    public function logKycListExport($subModuleKey, $count, $format) {
        list($detailZh, $detailEn) = KycOperationLogTexts::listExport($count, $format);
        return $this->logKyc($subModuleKey, 'export', null, $detailZh, $detailEn);
    }

    public function logKycTemplateAdd(
        $subModuleKey,
        $templateId,
        $templateName,
        $success = true,
        $failureMessage = ''
    ) {
        $tid = (int) $templateId;
        if ($success) {
            list($detailZh, $detailEn) = KycOperationLogTexts::addTemplate($templateName);
        } else {
            list($detailZh, $detailEn) = KycOperationLogTexts::templateAddFailure($failureMessage);
        }
        return $this->logKyc(
            $subModuleKey,
            'add',
            $tid > 0 ? $tid : null,
            $detailZh,
            $detailEn
        );
    }

    public function logKycTemplateDelete(
        $subModuleKey,
        $templateId,
        $templateName,
        $success = true,
        $failureMessage = ''
    ) {
        $tid = (int) $templateId;
        if ($success) {
            list($detailZh, $detailEn) = KycOperationLogTexts::deleteTemplate($templateName);
        } else {
            list($detailZh, $detailEn) = KycOperationLogTexts::templateDeleteFailure($failureMessage);
        }
        return $this->logKyc(
            $subModuleKey,
            'delete',
            $tid > 0 ? $tid : null,
            $detailZh,
            $detailEn
        );
    }

    public function logKycTemplateMutation(
        $subModuleKey,
        $operationTypeKey,
        $templateId,
        $detailZh,
        $detailEn
    ) {
        $tid = (int) $templateId;
        return $this->logKyc(
            $subModuleKey,
            trim((string) $operationTypeKey) ?: 'edit',
            $tid > 0 ? $tid : null,
            trim((string) $detailZh),
            trim((string) $detailEn)
        );
    }

    public function logKycSettingsMutation($subModuleKey, $operationTypeKey, $detailZh, $detailEn) {
        return $this->logKyc(
            $subModuleKey,
            trim((string) $operationTypeKey) ?: 'edit',
            null,
            trim((string) $detailZh),
            trim((string) $detailEn)
        );
    }

    private function logTransaction($subModuleKey, $operationTypeKey, $targetId, $detailZh, $detailEn) {
        $cid = $targetId !== null ? (int) $targetId : null;
        if ($cid !== null && $cid <= 0) {
            $cid = null;
        }
        return $this->record([
            'modelKey' => 'log_transaction',
            'subModuleKey' => $subModuleKey,
            'operationTypeKey' => trim((string) $operationTypeKey) ?: 'edit',
            'targetId' => $cid,
            'detailZh' => trim((string) $detailZh),
            'detailEn' => trim((string) $detailEn),
        ]);
    }

    public function logDepositApprove($subModuleKey, $clientId, $transactionId, $clientName, $amount, $success = true, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::depositApprove(
                $transactionId,
                $clientName,
                $amount,
                $clientId
            );
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::depositApproveFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'approve', (int) $clientId, $detailZh, $detailEn);
    }

    public function logDepositReject($subModuleKey, $clientId, $transactionId, $reasonTitle, $success = true, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::depositReject(
                $transactionId,
                $reasonTitle,
                $clientId
            );
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::depositRejectFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'reject', (int) $clientId, $detailZh, $detailEn);
    }

    public function logDepositBulkApprove($subModuleKey, array $transactionIds, $success = true, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::depositBulkApprove(
                count($transactionIds),
                $transactionIds
            );
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::depositBulkApproveFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'approve', null, $detailZh, $detailEn);
    }

    public function logDepositTagBulk($subModuleKey, array $depositSnapshots, $tagName, $success = true, $failureMessage = '') {
        if ($success) {
            $count = count($depositSnapshots);
            $transactionIds = array_map(function ($row) {
                return (string) ($row['transactionId'] ?? '');
            }, $depositSnapshots);
            $clientId = null;
            if ($count === 1) {
                $clientId = (int) ($depositSnapshots[0]['userId'] ?? 0);
            }
            list($detailZh, $detailEn) = TransactionOperationLogTexts::depositTagBulk(
                $count,
                $transactionIds,
                $tagName,
                $clientId > 0 ? $clientId : null
            );
            $targetId = $count === 1 && $clientId > 0 ? $clientId : null;
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::depositTagBulkFailure($failureMessage);
            $targetId = null;
        }
        return $this->logTransaction($subModuleKey, 'edit', $targetId ?? null, $detailZh, $detailEn);
    }

    public function logDepositTagRemove($subModuleKey, $clientId, $transactionId, $tagName, $success = true, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::depositTagRemove(
                $transactionId,
                $tagName,
                $clientId
            );
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::depositTagRemoveFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'edit', (int) $clientId, $detailZh, $detailEn);
    }

    public function logDepositNoteAdd($subModuleKey, $clientId, $transactionId, $success = true, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::depositNoteAdd($transactionId, $clientId);
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::depositNoteAddFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'edit', (int) $clientId, $detailZh, $detailEn);
    }

    public function logDepositEmail($subModuleKey, $clientId, $email, $success = true, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::depositEmail($email);
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::depositEmailFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'notify', (int) $clientId, $detailZh, $detailEn);
    }

    public function logDepositExport($subModuleKey, $count, $format, $success, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::depositExport($count, $format);
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::depositExportFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'export', null, $detailZh, $detailEn);
    }

    public function logTransactionSearchTagCreate($subModuleKey, $tagName, $keywords) {
        list($detailZh, $detailEn) = TransactionOperationLogTexts::searchTagCreate($tagName, $keywords);
        return $this->logTransaction($subModuleKey, 'add', null, $detailZh, $detailEn);
    }

    public function logTransactionSearchTagDelete($subModuleKey, $tagName) {
        list($detailZh, $detailEn) = TransactionOperationLogTexts::searchTagDelete($tagName);
        return $this->logTransaction($subModuleKey, 'delete', null, $detailZh, $detailEn);
    }

    public function logTransactionSearchTagCreateFailure($subModuleKey, $failureMessage = '') {
        list($detailZh, $detailEn) = TransactionOperationLogTexts::searchTagCreateFailure($failureMessage);
        return $this->logTransaction($subModuleKey, 'add', null, $detailZh, $detailEn);
    }

    public function logTransactionSearchTagDeleteFailure($subModuleKey, $failureMessage = '') {
        list($detailZh, $detailEn) = TransactionOperationLogTexts::searchTagDeleteFailure($failureMessage);
        return $this->logTransaction($subModuleKey, 'delete', null, $detailZh, $detailEn);
    }

    public function logWithdrawalApprove($subModuleKey, $clientId, $transactionId, $clientName, $amount, $success, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawalApproveSuccess(
                $transactionId,
                $clientName,
                $amount,
                $clientId
            );
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawalApproveFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'approve', (int) $clientId, $detailZh, $detailEn);
    }

    public function logWithdrawalReject($subModuleKey, $clientId, $transactionId, $reasonTitle, $success, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawalRejectSuccess(
                $transactionId,
                $reasonTitle,
                $clientId
            );
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawalRejectFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'reject', (int) $clientId, $detailZh, $detailEn);
    }

    public function logWithdrawalBulkApprove($subModuleKey, array $transactionIds, $success, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawalBulkApproveSuccess(
                count($transactionIds),
                $transactionIds
            );
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawalBulkApproveFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'approve', null, $detailZh, $detailEn);
    }

    public function logWithdrawalTagBulk($subModuleKey, array $snapshots, $tagName, $success, $failureMessage = '') {
        if ($success) {
            $count = count($snapshots);
            $transactionIds = array_map(function ($row) {
                return (string) ($row['transactionId'] ?? '');
            }, $snapshots);
            $clientId = null;
            if ($count === 1) {
                $clientId = (int) ($snapshots[0]['userId'] ?? 0);
            }
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawalTagBulkSuccess(
                $count,
                $transactionIds,
                $tagName,
                $clientId > 0 ? $clientId : null
            );
            $targetId = $count === 1 && $clientId > 0 ? $clientId : null;
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawalTagBulkFailure($failureMessage);
            $targetId = null;
        }
        return $this->logTransaction($subModuleKey, 'edit', $targetId ?? null, $detailZh, $detailEn);
    }

    public function logWithdrawalTagRemove($subModuleKey, $clientId, $transactionId, $tagName, $success, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawalTagRemoveSuccess(
                $transactionId,
                $tagName,
                $clientId
            );
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawalTagRemoveFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'edit', (int) $clientId, $detailZh, $detailEn);
    }

    public function logWithdrawalRequestDocuments($subModuleKey, $clientId, $transactionId, $success, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawalRequestDocumentsSuccess($transactionId);
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawalRequestDocumentsFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'edit', (int) $clientId, $detailZh, $detailEn);
    }

    public function logWithdrawalEmail($subModuleKey, $clientId, $email, $success, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawalEmailSuccess($email);
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawalEmailFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'notify', (int) $clientId, $detailZh, $detailEn);
    }

    public function logWithdrawalExport($subModuleKey, $count, $format, $success, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawalExportSuccess($count, $format);
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawalExportFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'export', null, $detailZh, $detailEn);
    }

    public function logInternalTransferApprove($subModuleKey, $clientId, $transactionId, $clientName, $amount, $success, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::internalTransferApproveSuccess(
                $transactionId,
                $clientName,
                $amount,
                $clientId
            );
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::internalTransferApproveFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'approve', (int) $clientId, $detailZh, $detailEn);
    }

    public function logInternalTransferReject($subModuleKey, $clientId, $transactionId, $reasonTitle, $success, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::internalTransferRejectSuccess(
                $transactionId,
                $reasonTitle,
                $clientId
            );
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::internalTransferRejectFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'reject', (int) $clientId, $detailZh, $detailEn);
    }

    public function logInternalTransferBulkApprove($subModuleKey, array $transactionIds, $success, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::internalTransferBulkApproveSuccess(
                count($transactionIds),
                $transactionIds
            );
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::internalTransferBulkApproveFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'approve', null, $detailZh, $detailEn);
    }

    public function logInternalTransferTagBulk($subModuleKey, array $snapshots, $tagName, $success, $failureMessage = '') {
        if ($success) {
            $count = count($snapshots);
            $transactionIds = array_map(function ($row) {
                return (string) ($row['transactionId'] ?? '');
            }, $snapshots);
            $clientId = null;
            if ($count === 1) {
                $clientId = (int) ($snapshots[0]['userId'] ?? 0);
            }
            list($detailZh, $detailEn) = TransactionOperationLogTexts::internalTransferTagBulkSuccess(
                $count,
                $transactionIds,
                $tagName,
                $clientId > 0 ? $clientId : null
            );
            $targetId = $count === 1 && $clientId > 0 ? $clientId : null;
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::internalTransferTagBulkFailure($failureMessage);
            $targetId = null;
        }
        return $this->logTransaction($subModuleKey, 'edit', $targetId ?? null, $detailZh, $detailEn);
    }

    public function logInternalTransferTagRemove($subModuleKey, $clientId, $transactionId, $tagName, $success, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::internalTransferTagRemoveSuccess(
                $transactionId,
                $tagName,
                $clientId
            );
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::internalTransferTagRemoveFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'edit', (int) $clientId, $detailZh, $detailEn);
    }

    public function logInternalTransferNoteAdd($subModuleKey, $clientId, $transactionId, $success, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::internalTransferNoteAddSuccess(
                $transactionId,
                $clientId
            );
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::internalTransferNoteAddFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'edit', (int) $clientId, $detailZh, $detailEn);
    }

    public function logInternalTransferEmail($subModuleKey, $clientId, $email, $success, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::internalTransferEmailSuccess($email);
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::internalTransferEmailFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'notify', (int) $clientId, $detailZh, $detailEn);
    }

    public function logInternalTransferExport($subModuleKey, $count, $format, $success, $failureMessage = '') {
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::internalTransferExportSuccess($count, $format);
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::internalTransferExportFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'export', null, $detailZh, $detailEn);
    }

    public function logAddressVerificationApprove(
        $subModuleKey,
        $clientId,
        $submissionId,
        $gatewayName,
        $notes = null,
        $success = true,
        $failureMessage = ''
    ) {
        $cid = (int) $clientId;
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::addressVerificationApprove(
                $submissionId,
                $gatewayName,
                $clientId,
                $notes
            );
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::addressVerificationApproveFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'approve', $cid > 0 ? $cid : null, $detailZh, $detailEn);
    }

    public function logAddressVerificationReject(
        $subModuleKey,
        $clientId,
        $submissionId,
        $gatewayName,
        $reason,
        $success = true,
        $failureMessage = ''
    ) {
        $cid = (int) $clientId;
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::addressVerificationReject(
                $submissionId,
                $gatewayName,
                $reason,
                $clientId
            );
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::addressVerificationRejectFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'reject', $cid > 0 ? $cid : null, $detailZh, $detailEn);
    }

    public function logAddressVerificationNeedDocs(
        $subModuleKey,
        $clientId,
        $submissionId,
        $gatewayName,
        $itemCount,
        $success = true,
        $failureMessage = ''
    ) {
        $cid = (int) $clientId;
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::addressVerificationNeedDocs(
                $submissionId,
                $gatewayName,
                $itemCount,
                $clientId
            );
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::addressVerificationNeedDocsFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'edit', $cid > 0 ? $cid : null, $detailZh, $detailEn);
    }

    public function logWithdrawKycTemplateMutation(
        $subModuleKey,
        $operationTypeKey,
        $templateId,
        $detailZh,
        $detailEn
    ) {
        $tid = (int) $templateId;
        return $this->logTransaction(
            $subModuleKey,
            trim((string) $operationTypeKey) ?: 'edit',
            $tid > 0 ? $tid : null,
            trim((string) $detailZh),
            trim((string) $detailEn)
        );
    }

    public function logTransactionSettingsMutation($subModuleKey, $operationTypeKey, $detailZh, $detailEn) {
        return $this->logTransaction(
            $subModuleKey,
            trim((string) $operationTypeKey) ?: 'edit',
            null,
            trim((string) $detailZh),
            trim((string) $detailEn)
        );
    }

    public function logSystemMutation($subModuleKey, $operationTypeKey, $targetId, $detailZh, $detailEn) {
        $tid = $targetId !== null ? (int) $targetId : null;
        if ($tid !== null && $tid <= 0) {
            $tid = null;
        }
        return $this->record([
            'modelKey' => 'log_system',
            'subModuleKey' => trim((string) $subModuleKey),
            'operationTypeKey' => trim((string) $operationTypeKey) ?: 'edit',
            'targetId' => $tid,
            'detailZh' => trim((string) $detailZh),
            'detailEn' => trim((string) $detailEn),
        ]);
    }

    private function logIb($subModuleKey, $operationTypeKey, $targetId, $detailZh, $detailEn) {
        $cid = $targetId !== null ? (int) $targetId : null;
        if ($cid !== null && $cid <= 0) {
            $cid = null;
        }
        return $this->record([
            'modelKey' => 'log_ib',
            'subModuleKey' => $subModuleKey,
            'operationTypeKey' => trim((string) $operationTypeKey) ?: 'edit',
            'targetId' => $cid,
            'detailZh' => trim((string) $detailZh),
            'detailEn' => trim((string) $detailEn),
        ]);
    }

    public function logIbMutation($subModuleKey, $operationTypeKey, $targetId, $detailZh, $detailEn) {
        return $this->logIb(
            $subModuleKey,
            $operationTypeKey,
            $targetId,
            trim((string) $detailZh),
            trim((string) $detailEn)
        );
    }

    public function logAddressVerificationAssign(
        $subModuleKey,
        $clientId,
        $submissionId,
        $gatewayName,
        $reviewerName,
        $success = true,
        $failureMessage = ''
    ) {
        $cid = (int) $clientId;
        if ($success) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::addressVerificationAssign(
                $submissionId,
                $gatewayName,
                $reviewerName,
                $clientId
            );
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::addressVerificationAssignFailure($failureMessage);
        }
        return $this->logTransaction($subModuleKey, 'assign', $cid > 0 ? $cid : null, $detailZh, $detailEn);
    }

    public static function formatNotificationChannelsZh($sendSystem, $sendEmail) {
        return ClientOperationLogTexts::formatNotificationChannelsZh($sendSystem, $sendEmail);
    }

    public static function formatNotificationChannelsEn($sendSystem, $sendEmail) {
        return ClientOperationLogTexts::formatNotificationChannelsEn($sendSystem, $sendEmail);
    }

    public static function formatScheduleDescZh($scheduleType, $scheduledAt = null) {
        return ClientOperationLogTexts::formatScheduleDescZh($scheduleType, $scheduledAt);
    }

    public static function formatScheduleDescEn($scheduleType, $scheduledAt = null) {
        return ClientOperationLogTexts::formatScheduleDescEn($scheduleType, $scheduledAt);
    }

    private function positiveTargetId($id) {
        $n = (int) $id;
        return $n > 0 ? $n : null;
    }

    private function resolveAdminOperatorId() {
        $user = AuthMiddleware::getCurrentUser();
        if (!$user || ($user['type'] ?? '') !== 'admin') {
            return 0;
        }
        return (int) ($user['userId'] ?? 0);
    }

    private function resolveSubModuleLabels($modelKey, $subModuleKey) {
        $code = AdminDictionaryItem::CODE_SUB_MODULE_PREFIX . $modelKey;
        $options = $this->dictModel->findOptionsByGroupAndCode(
            AdminDictionaryItem::GROUP_OPERATION_LOG,
            $code
        );
        foreach ($options as $opt) {
            if (($opt['value'] ?? '') === $subModuleKey) {
                return [
                    'zh' => (string) ($opt['labelZh'] ?? $subModuleKey),
                    'en' => (string) ($opt['labelEn'] ?? $subModuleKey),
                ];
            }
        }
        return ['zh' => $subModuleKey, 'en' => $subModuleKey];
    }

    private function resolveClientIp() {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $ip = trim((string) $ip);
        return $ip !== '' ? $ip : '0.0.0.0';
    }
}
