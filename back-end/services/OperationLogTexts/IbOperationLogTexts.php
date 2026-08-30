<?php
/**
 * IB 模块 — 操作日志文案（log_ib）
 */

require_once __DIR__ . '/OperationLogTextHelpers.php';

class IbOperationLogTexts {
    public static function ibTypeLabelZh($ibType) {
        $t = strtolower(trim((string) $ibType));
        if ($t === 'individual') {
            return '个人';
        }
        if ($t === 'company' || $t === 'corporate') {
            return '公司';
        }
        return trim((string) $ibType);
    }

    public static function ibTypeLabelEn($ibType) {
        $t = strtolower(trim((string) $ibType));
        if ($t === 'individual') {
            return 'Individual';
        }
        if ($t === 'company' || $t === 'corporate') {
            return 'Company';
        }
        return trim((string) $ibType);
    }

    /**
     * IR1 — 初审提交成功
     *
     * @return array{0:string,1:string}
     */
    public static function initialReviewSubmitSuccess(
        $clientDisplay,
        $clientId,
        $ibDisplay,
        $ibId,
        $ibType,
        $tierName,
        array $ruleNames
    ) {
        $clientDisplay = trim((string) $clientDisplay);
        $clientId = (int) $clientId;
        $ibDisplay = trim((string) $ibDisplay);
        $ibId = (int) $ibId;
        $tierName = trim((string) $tierName);
        $typeZh = self::ibTypeLabelZh($ibType);
        $typeEn = self::ibTypeLabelEn($ibType);

        $extraZh = [];
        $extraEn = [];
        if ($typeZh !== '') {
            $extraZh[] = "IB 类型：{$typeZh}";
            $extraEn[] = "IB type: {$typeEn}";
        }
        if ($tierName !== '') {
            $extraZh[] = "层级：{$tierName}";
            $extraEn[] = "Tier: {$tierName}";
        }
        if (!empty($ruleNames)) {
            $rulesZh = implode('、', $ruleNames);
            $rulesEn = implode(', ', $ruleNames);
            $extraZh[] = "佣金规则：{$rulesZh}";
            $extraEn[] = "Commission rules: {$rulesEn}";
        }

        $suffixZh = !empty($extraZh) ? '；' . implode('；', $extraZh) : '';
        $suffixEn = !empty($extraEn) ? '; ' . implode('; ', $extraEn) : '';

        return [
            "提交初审：客户 {$clientDisplay}（ID {$clientId}），IB {$ibDisplay}（ID {$ibId}）已进入风控初审{$suffixZh}",
            "Initial review submitted: client {$clientDisplay} (ID {$clientId}), IB {$ibDisplay} (ID {$ibId}) moved to risk review{$suffixEn}",
        ];
    }

    /**
     * IR2a — 发送邮件邀请成功
     *
     * @return array{0:string,1:string}
     */
    public static function invitationEmailSuccess($clientDisplay, $clientId) {
        $clientDisplay = trim((string) $clientDisplay);
        $clientId = (int) $clientId;
        return [
            "发送 IB 邀请邮件：客户 {$clientDisplay}（ID {$clientId}）",
            "Sent IB invitation email to client {$clientDisplay} (ID {$clientId})",
        ];
    }

    /**
     * IR2b — 跳过签署直接建待初审 IB
     *
     * @return array{0:string,1:string}
     */
    public static function invitationSkipSignSuccess($clientDisplay, $clientId, $ibPartnerId) {
        $clientDisplay = trim((string) $clientDisplay);
        $clientId = (int) $clientId;
        $ibPartnerId = (int) $ibPartnerId;
        return [
            "跳过签署创建待初审 IB：客户 {$clientDisplay}（ID {$clientId}），IB ID {$ibPartnerId}",
            "Created pending initial review IB (skip sign): client {$clientDisplay} (ID {$clientId}), IB ID {$ibPartnerId}",
        ];
    }

    /**
     * IR3 — Create Multi IB 成功
     *
     * @return array{0:string,1:string}
     */
    public static function createMultiIbSuccess(
        $clientDisplay,
        $clientId,
        $sourceIbDisplay,
        $sourceIbId,
        $newIbPartnerId
    ) {
        $clientDisplay = trim((string) $clientDisplay);
        $clientId = (int) $clientId;
        $sourceIbDisplay = trim((string) $sourceIbDisplay);
        $sourceIbId = (int) $sourceIbId;
        $newIbPartnerId = (int) $newIbPartnerId;
        return [
            "创建额外 IB：客户 {$clientDisplay}（ID {$clientId}），基于已通过 IB {$sourceIbDisplay}（ID {$sourceIbId}），新建待初审 IB ID {$newIbPartnerId}",
            "Created additional IB: client {$clientDisplay} (ID {$clientId}) from approved IB {$sourceIbDisplay} (ID {$sourceIbId}), new pending initial review IB ID {$newIbPartnerId}",
        ];
    }

    public static function initialReviewSubmitFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'IB 初审提交失败',
            'IB initial review submission failed',
            $apiMessageEn
        );
    }

    public static function invitationCreateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'IB 邀请操作失败',
            'IB invitation failed',
            $apiMessageEn
        );
    }

    public static function createMultiIbFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '创建额外 IB 失败',
            'Create additional IB failed',
            $apiMessageEn
        );
    }

    /**
     * RR1 — 风控审核提交成功
     *
     * @return array{0:string,1:string}
     */
    public static function riskReviewSubmitSuccess(
        $clientDisplay,
        $clientId,
        $ibDisplay,
        $ibId,
        array $groupNames
    ) {
        $clientDisplay = trim((string) $clientDisplay);
        $clientId = (int) $clientId;
        $ibDisplay = trim((string) $ibDisplay);
        $ibId = (int) $ibId;

        $suffixZh = '';
        $suffixEn = '';
        if (!empty($groupNames)) {
            $groupsZh = implode('、', $groupNames);
            $groupsEn = implode(', ', $groupNames);
            $suffixZh = "；交易组别：{$groupsZh}";
            $suffixEn = "; Trading groups: {$groupsEn}";
        }

        return [
            "提交风控审核：客户 {$clientDisplay}（ID {$clientId}），IB {$ibDisplay}（ID {$ibId}）已进入终审{$suffixZh}",
            "Risk review submitted: client {$clientDisplay} (ID {$clientId}), IB {$ibDisplay} (ID {$ibId}) moved to final review{$suffixEn}",
        ];
    }

    /**
     * RR2 — 风控驳回成功
     *
     * @return array{0:string,1:string}
     */
    public static function riskReviewRejectSuccess($clientDisplay, $clientId, $ibDisplay, $ibId) {
        $clientDisplay = trim((string) $clientDisplay);
        $clientId = (int) $clientId;
        $ibDisplay = trim((string) $ibDisplay);
        $ibId = (int) $ibId;
        return [
            "风控驳回：客户 {$clientDisplay}（ID {$clientId}），IB {$ibDisplay}（ID {$ibId}）已回退至待初审",
            "Risk review rejected: client {$clientDisplay} (ID {$clientId}), IB {$ibDisplay} (ID {$ibId}) reverted to pending initial review",
        ];
    }

    public static function riskReviewSubmitFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'IB 风控审核提交失败',
            'IB risk review submission failed',
            $apiMessageEn
        );
    }

    public static function riskReviewRejectFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'IB 风控驳回失败',
            'IB risk review rejection failed',
            $apiMessageEn
        );
    }

    /**
     * FR1 — 终审通过成功
     *
     * @return array{0:string,1:string}
     */
    public static function finalReviewApproveSuccess(
        $clientDisplay,
        $clientId,
        $ibDisplay,
        $ibId,
        $ibCode
    ) {
        $clientDisplay = trim((string) $clientDisplay);
        $clientId = (int) $clientId;
        $ibDisplay = trim((string) $ibDisplay);
        $ibId = (int) $ibId;
        $ibCode = trim((string) $ibCode);
        $codeZh = $ibCode !== '' ? "，IB 编号 {$ibCode}" : '';
        $codeEn = $ibCode !== '' ? ", IB code {$ibCode}" : '';
        return [
            "终审通过：客户 {$clientDisplay}（ID {$clientId}），IB {$ibDisplay}（ID {$ibId}）已批准{$codeZh}",
            "Final review approved: client {$clientDisplay} (ID {$clientId}), IB {$ibDisplay} (ID {$ibId}) approved{$codeEn}",
        ];
    }

    /**
     * FR2 — 终审驳回成功
     *
     * @return array{0:string,1:string}
     */
    public static function finalReviewRejectSuccess($clientDisplay, $clientId, $ibDisplay, $ibId) {
        $clientDisplay = trim((string) $clientDisplay);
        $clientId = (int) $clientId;
        $ibDisplay = trim((string) $ibDisplay);
        $ibId = (int) $ibId;
        return [
            "终审驳回：客户 {$clientDisplay}（ID {$clientId}），IB {$ibDisplay}（ID {$ibId}）已回退至待初审",
            "Final review rejected: client {$clientDisplay} (ID {$clientId}), IB {$ibDisplay} (ID {$ibId}) reverted to pending initial review",
        ];
    }

    /**
     * FR3 — 绑定关系成功
     *
     * @param string[] $childLabels
     * @param string[] $clientLabels
     * @return array{0:string,1:string}
     */
    public static function finalReviewBindSuccess(
        $clientDisplay,
        $clientId,
        $parentDisplay,
        $parentIbId,
        array $childLabels,
        array $clientLabels,
        $ibBoundCount,
        $clientBoundCount
    ) {
        $clientDisplay = trim((string) $clientDisplay);
        $clientId = (int) $clientId;
        $parentDisplay = trim((string) $parentDisplay);
        $parentIbId = (int) $parentIbId;
        $ibBoundCount = (int) $ibBoundCount;
        $clientBoundCount = (int) $clientBoundCount;

        $partsZh = [];
        $partsEn = [];
        if ($ibBoundCount > 0) {
            $listZh = !empty($childLabels) ? implode('、', $childLabels) : "{$ibBoundCount} 个子级 IB";
            $listEn = !empty($childLabels) ? implode(', ', $childLabels) : "{$ibBoundCount} child IB(s)";
            $partsZh[] = "子级 IB {$ibBoundCount} 个：{$listZh}";
            $partsEn[] = "{$ibBoundCount} child IB(s): {$listEn}";
        }
        if ($clientBoundCount > 0) {
            $listZh = !empty($clientLabels) ? implode('、', $clientLabels) : "{$clientBoundCount} 个客户";
            $listEn = !empty($clientLabels) ? implode(', ', $clientLabels) : "{$clientBoundCount} client(s)";
            $partsZh[] = "客户 {$clientBoundCount} 个：{$listZh}";
            $partsEn[] = "{$clientBoundCount} client(s): {$listEn}";
        }
        if (empty($partsZh)) {
            $partsZh[] = '未新增绑定';
            $partsEn[] = 'no new bindings added';
        }
        $detailZh = "绑定关系：父级 IB {$parentDisplay}（ID {$parentIbId}），客户 {$clientDisplay}（ID {$clientId}）；"
            . implode('；', $partsZh);
        $detailEn = "Bind relationships: parent IB {$parentDisplay} (ID {$parentIbId}), client {$clientDisplay} (ID {$clientId}); "
            . implode('; ', $partsEn);
        return [$detailZh, $detailEn];
    }

    /**
     * FR4 — 解绑关系成功
     *
     * @param string[] $childLabels
     * @param string[] $clientLabels
     * @return array{0:string,1:string}
     */
    public static function finalReviewUnbindSuccess(
        $clientDisplay,
        $clientId,
        $parentDisplay,
        $parentIbId,
        array $childLabels,
        array $clientLabels,
        $ibUnboundCount,
        $clientUnboundCount
    ) {
        $clientDisplay = trim((string) $clientDisplay);
        $clientId = (int) $clientId;
        $parentDisplay = trim((string) $parentDisplay);
        $parentIbId = (int) $parentIbId;
        $ibUnboundCount = (int) $ibUnboundCount;
        $clientUnboundCount = (int) $clientUnboundCount;

        $partsZh = [];
        $partsEn = [];
        if ($ibUnboundCount > 0) {
            $listZh = !empty($childLabels) ? implode('、', $childLabels) : "{$ibUnboundCount} 个子级 IB";
            $listEn = !empty($childLabels) ? implode(', ', $childLabels) : "{$ibUnboundCount} child IB(s)";
            $partsZh[] = "子级 IB {$ibUnboundCount} 个：{$listZh}";
            $partsEn[] = "{$ibUnboundCount} child IB(s): {$listEn}";
        }
        if ($clientUnboundCount > 0) {
            $listZh = !empty($clientLabels) ? implode('、', $clientLabels) : "{$clientUnboundCount} 个客户";
            $listEn = !empty($clientLabels) ? implode(', ', $clientLabels) : "{$clientUnboundCount} client(s)";
            $partsZh[] = "客户 {$clientUnboundCount} 个：{$listZh}";
            $partsEn[] = "{$clientUnboundCount} client(s): {$listEn}";
        }
        $detailZh = "解绑关系：父级 IB {$parentDisplay}（ID {$parentIbId}），客户 {$clientDisplay}（ID {$clientId}）；"
            . implode('；', $partsZh);
        $detailEn = "Unbind relationships: parent IB {$parentDisplay} (ID {$parentIbId}), client {$clientDisplay} (ID {$clientId}); "
            . implode('; ', $partsEn);
        return [$detailZh, $detailEn];
    }

    /**
     * FR5 — 终审通过后编辑成功
     *
     * @param string[] $ruleNames
     * @param string[] $groupNames
     * @return array{0:string,1:string}
     */
    public static function finalReviewPostApprovalUpdateSuccess(
        $clientDisplay,
        $clientId,
        $ibDisplay,
        $ibId,
        $oldIbType,
        $newIbType,
        $oldTierName,
        $newTierName,
        $ruleIdsProvided,
        array $ruleNames,
        $groupIdsProvided,
        array $groupNames
    ) {
        $clientDisplay = trim((string) $clientDisplay);
        $clientId = (int) $clientId;
        $ibDisplay = trim((string) $ibDisplay);
        $ibId = (int) $ibId;

        $changesZh = [];
        $changesEn = [];
        $oldType = trim((string) $oldIbType);
        $newType = trim((string) $newIbType);
        if ($newType !== '' && strcasecmp($oldType, $newType) !== 0) {
            $changesZh[] = 'IB 类型：' . self::ibTypeLabelZh($oldType) . ' → ' . self::ibTypeLabelZh($newType);
            $changesEn[] = 'IB type: ' . self::ibTypeLabelEn($oldType) . ' → ' . self::ibTypeLabelEn($newType);
        }
        $oldTier = trim((string) $oldTierName);
        $newTier = trim((string) $newTierName);
        if ($newTier !== '' && $oldTier !== $newTier) {
            $changesZh[] = "层级：{$oldTier} → {$newTier}";
            $changesEn[] = "Tier: {$oldTier} → {$newTier}";
        }
        if ($ruleIdsProvided) {
            $rulesZh = !empty($ruleNames) ? implode('、', $ruleNames) : '（已清空）';
            $rulesEn = !empty($ruleNames) ? implode(', ', $ruleNames) : '(cleared)';
            $changesZh[] = "佣金规则：{$rulesZh}";
            $changesEn[] = "Commission rules: {$rulesEn}";
        }
        if ($groupIdsProvided) {
            $groupsZh = !empty($groupNames) ? implode('、', $groupNames) : '（已清空）';
            $groupsEn = !empty($groupNames) ? implode(', ', $groupNames) : '(cleared)';
            $changesZh[] = "交易组别：{$groupsZh}";
            $changesEn[] = "Trading groups: {$groupsEn}";
        }

        $suffixZh = !empty($changesZh) ? '；' . implode('；', $changesZh) : '';
        $suffixEn = !empty($changesEn) ? '; ' . implode('; ', $changesEn) : '';

        return [
            "终审后编辑：客户 {$clientDisplay}（ID {$clientId}），IB {$ibDisplay}（ID {$ibId}）{$suffixZh}",
            "Post-approval edit: client {$clientDisplay} (ID {$clientId}), IB {$ibDisplay} (ID {$ibId}){$suffixEn}",
        ];
    }

    public static function finalReviewApproveFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'IB 终审通过失败',
            'IB final review approval failed',
            $apiMessageEn
        );
    }

    public static function finalReviewRejectFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'IB 终审驳回失败',
            'IB final review rejection failed',
            $apiMessageEn
        );
    }

    public static function finalReviewBindFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'IB 绑定关系失败',
            'IB bind relationships failed',
            $apiMessageEn
        );
    }

    public static function finalReviewUnbindFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'IB 解绑关系失败',
            'IB unbind relationships failed',
            $apiMessageEn
        );
    }

    public static function finalReviewPostApprovalUpdateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'IB 终审后编辑失败',
            'IB post-approval edit failed',
            $apiMessageEn
        );
    }

    /**
     * CO1 — 佣金订单审批成功
     *
     * @return array{0:string,1:string}
     */
    public static function commissionOrderApproveSuccess(
        $orderId,
        $ibDisplay,
        $ibPartnerId,
        $ruleName,
        $commissionText
    ) {
        $orderId = (int) $orderId;
        $ibDisplay = trim((string) $ibDisplay);
        $ibPartnerId = (int) $ibPartnerId;
        $ruleName = trim((string) $ruleName);
        $commissionText = trim((string) $commissionText);
        $ruleZh = $ruleName !== '' ? "，规则 {$ruleName}" : '';
        $ruleEn = $ruleName !== '' ? ", rule {$ruleName}" : '';
        $amtZh = $commissionText !== '' ? "，佣金 {$commissionText}" : '';
        $amtEn = $commissionText !== '' ? ", commission {$commissionText}" : '';
        return [
            "审批佣金订单：订单 ID {$orderId}，IB {$ibDisplay}（ID {$ibPartnerId}）{$ruleZh}{$amtZh}，状态 pending → approved",
            "Commission order approved: order ID {$orderId}, IB {$ibDisplay} (ID {$ibPartnerId}){$ruleEn}{$amtEn}, status pending → approved",
        ];
    }

    /**
     * CO2 — 佣金订单完成出款成功
     *
     * @return array{0:string,1:string}
     */
    public static function commissionOrderCompleteSuccess(
        $orderId,
        $ibDisplay,
        $ibPartnerId,
        $ruleName,
        $commissionText
    ) {
        $orderId = (int) $orderId;
        $ibDisplay = trim((string) $ibDisplay);
        $ibPartnerId = (int) $ibPartnerId;
        $ruleName = trim((string) $ruleName);
        $commissionText = trim((string) $commissionText);
        $ruleZh = $ruleName !== '' ? "，规则 {$ruleName}" : '';
        $ruleEn = $ruleName !== '' ? ", rule {$ruleName}" : '';
        $amtZh = $commissionText !== '' ? "，佣金 {$commissionText}" : '';
        $amtEn = $commissionText !== '' ? ", commission {$commissionText}" : '';
        return [
            "完成佣金出款：订单 ID {$orderId}，IB {$ibDisplay}（ID {$ibPartnerId}）{$ruleZh}{$amtZh}，状态 approved → completed",
            "Commission payout completed: order ID {$orderId}, IB {$ibDisplay} (ID {$ibPartnerId}){$ruleEn}{$amtEn}, status approved → completed",
        ];
    }

    /**
     * CO3 — 佣金订单取消成功
     *
     * @return array{0:string,1:string}
     */
    public static function commissionOrderCancelSuccess(
        $orderId,
        $ibDisplay,
        $ibPartnerId,
        $ruleName,
        $commissionText,
        $previousStatus
    ) {
        $orderId = (int) $orderId;
        $ibDisplay = trim((string) $ibDisplay);
        $ibPartnerId = (int) $ibPartnerId;
        $ruleName = trim((string) $ruleName);
        $commissionText = trim((string) $commissionText);
        $previousStatus = trim((string) $previousStatus);
        $ruleZh = $ruleName !== '' ? "，规则 {$ruleName}" : '';
        $ruleEn = $ruleName !== '' ? ", rule {$ruleName}" : '';
        $amtZh = $commissionText !== '' ? "，佣金 {$commissionText}" : '';
        $amtEn = $commissionText !== '' ? ", commission {$commissionText}" : '';
        $statusZh = $previousStatus !== '' ? "，原状态 {$previousStatus}" : '';
        $statusEn = $previousStatus !== '' ? ", previous status {$previousStatus}" : '';
        return [
            "取消佣金订单：订单 ID {$orderId}，IB {$ibDisplay}（ID {$ibPartnerId}）{$ruleZh}{$amtZh}{$statusZh}",
            "Commission order cancelled: order ID {$orderId}, IB {$ibDisplay} (ID {$ibPartnerId}){$ruleEn}{$amtEn}{$statusEn}",
        ];
    }

    public static function commissionOrderApproveFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '佣金订单审批失败',
            'Commission order approval failed',
            $apiMessageEn
        );
    }

    public static function commissionOrderCompleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '佣金订单完成出款失败',
            'Commission order completion failed',
            $apiMessageEn
        );
    }

    public static function commissionOrderCancelFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '佣金订单取消失败',
            'Commission order cancellation failed',
            $apiMessageEn
        );
    }

    // -------------------------------------------------------------------------
    // ib_settings（subModuleKey: ib_settings；targetId 恒为 null；不含 DB ID）
    // -------------------------------------------------------------------------

    public static function ibSettingsUntitledDocumentZh() {
        return '未命名文档';
    }

    public static function ibSettingsUntitledDocumentEn() {
        return 'Untitled document';
    }

    public static function ibSettingsUntitledTierZh() {
        return '未命名等级';
    }

    public static function ibSettingsUntitledTierEn() {
        return 'Unnamed tier';
    }

    public static function ibSettingsUntitledRuleZh() {
        return '未命名规则';
    }

    public static function ibSettingsUntitledRuleEn() {
        return 'Unnamed rule';
    }

    private static function ibSettingsDocumentLabelZh($title) {
        $name = trim((string) $title);
        return $name !== '' ? $name : self::ibSettingsUntitledDocumentZh();
    }

    private static function ibSettingsDocumentLabelEn($title) {
        $name = trim((string) $title);
        return $name !== '' ? $name : self::ibSettingsUntitledDocumentEn();
    }

    private static function ibSettingsTierLabelZh($tierName) {
        $name = trim((string) $tierName);
        return $name !== '' ? $name : self::ibSettingsUntitledTierZh();
    }

    private static function ibSettingsTierLabelEn($tierName) {
        $name = trim((string) $tierName);
        return $name !== '' ? $name : self::ibSettingsUntitledTierEn();
    }

    private static function ibSettingsRuleLabelZh($ruleName) {
        $name = trim((string) $ruleName);
        return $name !== '' ? $name : self::ibSettingsUntitledRuleZh();
    }

    private static function ibSettingsRuleLabelEn($ruleName) {
        $name = trim((string) $ruleName);
        return $name !== '' ? $name : self::ibSettingsUntitledRuleEn();
    }

    public static function ibSettingsDocumentAdd($title) {
        $t = self::ibSettingsDocumentLabelZh($title);
        $te = self::ibSettingsDocumentLabelEn($title);
        return ["创建 IB 文档「{$t}」", "Created IB document \"{$te}\""];
    }

    public static function ibSettingsDocumentEdit($title) {
        $t = self::ibSettingsDocumentLabelZh($title);
        $te = self::ibSettingsDocumentLabelEn($title);
        return ["更新 IB 文档「{$t}」", "Updated IB document \"{$te}\""];
    }

    public static function ibSettingsDocumentDelete($title) {
        $t = self::ibSettingsDocumentLabelZh($title);
        $te = self::ibSettingsDocumentLabelEn($title);
        return ["删除 IB 文档「{$t}」", "Deleted IB document \"{$te}\""];
    }

    public static function ibSettingsDocumentDuplicate($sourceTitle) {
        $t = self::ibSettingsDocumentLabelZh($sourceTitle);
        $te = self::ibSettingsDocumentLabelEn($sourceTitle);
        return ["复制 IB 文档「{$t}」", "Duplicated IB document \"{$te}\""];
    }

    public static function ibSettingsTierAdd($tierName, $tierLevel) {
        $t = self::ibSettingsTierLabelZh($tierName);
        $te = self::ibSettingsTierLabelEn($tierName);
        $level = (int) $tierLevel;
        return [
            "创建 IB 等级「{$t}」（第 {$level} 级）",
            "Created IB tier \"{$te}\" (level {$level})",
        ];
    }

    public static function ibSettingsTierEdit($tierName, $tierLevel) {
        $t = self::ibSettingsTierLabelZh($tierName);
        $te = self::ibSettingsTierLabelEn($tierName);
        $level = (int) $tierLevel;
        return [
            "更新 IB 等级「{$t}」（第 {$level} 级）",
            "Updated IB tier \"{$te}\" (level {$level})",
        ];
    }

    public static function ibSettingsTierDelete($tierName, $tierLevel) {
        $t = self::ibSettingsTierLabelZh($tierName);
        $te = self::ibSettingsTierLabelEn($tierName);
        $level = (int) $tierLevel;
        return [
            "删除 IB 等级「{$t}」（第 {$level} 级）",
            "Deleted IB tier \"{$te}\" (level {$level})",
        ];
    }

    public static function ibSettingsRuleAdd($ruleName) {
        $t = self::ibSettingsRuleLabelZh($ruleName);
        $te = self::ibSettingsRuleLabelEn($ruleName);
        return ["创建 IB 佣金规则「{$t}」", "Created IB commission rule \"{$te}\""];
    }

    public static function ibSettingsRuleEdit($ruleName) {
        $t = self::ibSettingsRuleLabelZh($ruleName);
        $te = self::ibSettingsRuleLabelEn($ruleName);
        return ["更新 IB 佣金规则「{$t}」", "Updated IB commission rule \"{$te}\""];
    }

    public static function ibSettingsRuleDelete($ruleName) {
        $t = self::ibSettingsRuleLabelZh($ruleName);
        $te = self::ibSettingsRuleLabelEn($ruleName);
        return ["删除 IB 佣金规则「{$t}」", "Deleted IB commission rule \"{$te}\""];
    }

    public static function ibSettingsRuleDuplicate($sourceRuleName) {
        $t = self::ibSettingsRuleLabelZh($sourceRuleName);
        $te = self::ibSettingsRuleLabelEn($sourceRuleName);
        return ["复制 IB 佣金规则「{$t}」", "Duplicated IB commission rule \"{$te}\""];
    }

    public static function ibSettingsSyncProductsSuccess($platformName, $securitiesCount, $symbolsCount) {
        $platform = trim((string) $platformName);
        if ($platform === '') {
            $platform = '交易平台';
        }
        $sec = max(0, (int) $securitiesCount);
        $sym = max(0, (int) $symbolsCount);
        return [
            "从交易平台同步产品：{$platform}（{$sec} 条证券，{$sym} 条品种）",
            "Synced products from trading platform: {$platform} ({$sec} securities, {$sym} symbols)",
        ];
    }

    public static function ibSettingsDocumentAddFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '创建 IB 文档失败',
            'Failed to create IB document',
            $apiMessageEn
        );
    }

    public static function ibSettingsDocumentEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新 IB 文档失败',
            'Failed to update IB document',
            $apiMessageEn
        );
    }

    public static function ibSettingsDocumentDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '删除 IB 文档失败',
            'Failed to delete IB document',
            $apiMessageEn
        );
    }

    public static function ibSettingsDocumentDuplicateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '复制 IB 文档失败',
            'Failed to duplicate IB document',
            $apiMessageEn
        );
    }

    public static function ibSettingsTierAddFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '创建 IB 等级失败',
            'Failed to create IB tier',
            $apiMessageEn
        );
    }

    public static function ibSettingsTierEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新 IB 等级失败',
            'Failed to update IB tier',
            $apiMessageEn
        );
    }

    public static function ibSettingsTierDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '删除 IB 等级失败',
            'Failed to delete IB tier',
            $apiMessageEn
        );
    }

    public static function ibSettingsRuleAddFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '创建 IB 佣金规则失败',
            'Failed to create IB commission rule',
            $apiMessageEn
        );
    }

    public static function ibSettingsRuleEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新 IB 佣金规则失败',
            'Failed to update IB commission rule',
            $apiMessageEn
        );
    }

    public static function ibSettingsRuleDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '删除 IB 佣金规则失败',
            'Failed to delete IB commission rule',
            $apiMessageEn
        );
    }

    public static function ibSettingsRuleDuplicateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '复制 IB 佣金规则失败',
            'Failed to duplicate IB commission rule',
            $apiMessageEn
        );
    }

    public static function ibSettingsSyncProductsFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '从交易平台同步产品失败',
            'Failed to sync products from trading platform',
            $apiMessageEn
        );
    }

    public static function ibSettingsSymbolExchangeBatchMode($mode) {
        $isManual = strtolower(trim((string) $mode)) === 'manual';
        $zh = $isManual ? '手动' : '自动';
        $en = $isManual ? 'Manual' : 'Auto';
        return [
            "批量切换品种汇率同步模式为「{$zh}」",
            "Batch updated symbol exchange sync mode to \"{$en}\"",
        ];
    }

    public static function ibSettingsSymbolExchangeAdd($symbolName) {
        $t = trim((string) $symbolName);
        if ($t === '') {
            $t = '未命名品种';
        }
        return ["新增品种汇率「{$t}」", "Created symbol exchange rate \"{$t}\""];
    }

    public static function ibSettingsSymbolExchangeEdit($symbolName) {
        $t = trim((string) $symbolName);
        if ($t === '') {
            $t = '未命名品种';
        }
        return ["更新品种汇率「{$t}」", "Updated symbol exchange rate \"{$t}\""];
    }

    public static function ibSettingsSymbolExchangeDelete($symbolName) {
        $t = trim((string) $symbolName);
        if ($t === '') {
            $t = '未命名品种';
        }
        return ["删除品种汇率「{$t}」", "Deleted symbol exchange rate \"{$t}\""];
    }

    public static function ibSettingsSymbolExchangeFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '品种汇率操作失败',
            'Symbol exchange rate operation failed',
            $apiMessageEn
        );
    }
}
