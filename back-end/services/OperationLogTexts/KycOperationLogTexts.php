<?php
/**
 * KYC 模块（log_kyc）操作日志 — 中英详情文案
 *
 * - kyc_list：KYC List 审批/分配/导出等
 * - kyc_templates：KYC Templates（与页面可见字段一致，不含模板/问题等 DB ID）
 * - kyc_settings：KYC Settings（提示文案、第三方网关；targetId 恒为 null）
 */

require_once __DIR__ . '/OperationLogTextHelpers.php';

class KycOperationLogTexts {
    // -------------------------------------------------------------------------
    // kyc_list（subModuleKey: kyc_list）
    // -------------------------------------------------------------------------

    public static function submissionApprove($clientId, $submissionId, $templateName, $notes = null) {
        $cid = (int) $clientId;
        $sid = (int) $submissionId;
        $tpl = trim((string) $templateName);
        $tplZh = $tpl !== '' ? "，模板：{$tpl}" : '';
        $tplEn = $tpl !== '' ? ", template: {$tpl}" : '';
        $noteZh = $notes ? OperationLogTextHelpers::notesSuffixZh($notes) : '';
        $noteEn = $notes ? OperationLogTextHelpers::notesSuffixEn($notes) : '';
        return [
            "审批通过 KYC 提交 #{$sid}{$tplZh}；客户 ID：{$cid}{$noteZh}",
            "Approved KYC submission #{$sid}{$tplEn}; client ID: {$cid}{$noteEn}",
        ];
    }

    public static function submissionReject($clientId, $submissionId, $templateName, $reason) {
        $cid = (int) $clientId;
        $sid = (int) $submissionId;
        $tpl = trim((string) $templateName);
        $tplZh = $tpl !== '' ? "，模板：{$tpl}" : '';
        $tplEn = $tpl !== '' ? ", template: {$tpl}" : '';
        $reason = trim((string) $reason);
        return [
            "拒绝 KYC 提交 #{$sid}{$tplZh}；原因：{$reason}；客户 ID：{$cid}",
            "Rejected KYC submission #{$sid}{$tplEn}; reason: {$reason}; client ID: {$cid}",
        ];
    }

    public static function submissionNeedDocs($clientId, $submissionId, $templateName, $itemCount) {
        $cid = (int) $clientId;
        $sid = (int) $submissionId;
        $tpl = trim((string) $templateName);
        $tplZh = $tpl !== '' ? "，模板：{$tpl}" : '';
        $tplEn = $tpl !== '' ? ", template: {$tpl}" : '';
        $count = (int) $itemCount;
        return [
            "要求 KYC 补件 #{$sid}{$tplZh}；请求 {$count} 项；客户 ID：{$cid}",
            "Requested KYC resubmission #{$sid}{$tplEn}; {$count} item(s); client ID: {$cid}",
        ];
    }

    public static function reviewerAssignBulk(array $clientIds, $submissionCount, $reviewerName, $notes = '') {
        $ids = OperationLogTextHelpers::formatClientIdList($clientIds);
        $count = (int) $submissionCount;
        $reviewerName = trim((string) $reviewerName);
        $noteZh = OperationLogTextHelpers::notesSuffixZh($notes);
        $noteEn = OperationLogTextHelpers::notesSuffixEn($notes);
        return [
            "批量分配 {$count} 条 KYC 提交给审核员：{$reviewerName}；涉及客户 ID：{$ids}{$noteZh}",
            "Bulk assigned {$count} KYC submission(s) to reviewer {$reviewerName}; client IDs: {$ids}{$noteEn}",
        ];
    }

    public static function submissionApproveBulk(
        array $clientIds,
        $submissionCount,
        $successCount,
        $notes = ''
    ) {
        $ids = OperationLogTextHelpers::formatClientIdList($clientIds);
        $selected = (int) $submissionCount;
        $success = (int) $successCount;
        $noteZh = OperationLogTextHelpers::notesSuffixZh($notes);
        $noteEn = OperationLogTextHelpers::notesSuffixEn($notes);
        return [
            "批量审批通过 {$success} 条 KYC 提交（共选 {$selected} 条）；涉及客户 ID：{$ids}{$noteZh}",
            "Bulk approved {$success} KYC submission(s) ({$selected} selected); client IDs: {$ids}{$noteEn}",
        ];
    }

    public static function listExport($count, $format) {
        $fmt = strtoupper((string) $format);
        $n = (int) $count;
        return [
            "导出 {$n} 条 KYC 提交（{$fmt}）",
            "Exported {$n} KYC submission(s) ({$fmt})",
        ];
    }

    public static function submissionApproveFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 审批通过操作失败',
            'KYC approval failed',
            $apiMessageEn
        );
    }

    public static function submissionRejectFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 审批拒绝操作失败',
            'KYC rejection failed',
            $apiMessageEn
        );
    }

    public static function submissionNeedDocsFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 要求补件操作失败',
            'KYC resubmission request failed',
            $apiMessageEn
        );
    }

    public static function reviewerAssignBulkFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 批量分配审核员操作失败',
            'Bulk KYC reviewer assignment failed',
            $apiMessageEn
        );
    }

    public static function submissionApproveBulkFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 批量审批通过操作失败',
            'Bulk KYC approval failed',
            $apiMessageEn
        );
    }

    // -------------------------------------------------------------------------
    // kyc_templates（subModuleKey: kyc_templates）
    // -------------------------------------------------------------------------

    public static function statusZh($status) {
        $map = [
            'draft' => '草稿',
            'active' => '已启用',
            'inactive' => '已停用',
            'archived' => '已归档',
        ];
        $key = strtolower(trim((string) $status));
        return $map[$key] ?? $status;
    }

    public static function statusEn($status) {
        $map = [
            'draft' => 'Draft',
            'active' => 'Active',
            'inactive' => 'Inactive',
            'archived' => 'Archived',
        ];
        $key = strtolower(trim((string) $status));
        return $map[$key] ?? $status;
    }

    public static function templateLabelZh($name) {
        return '模板「' . self::qZh($name) . '」';
    }

    public static function templateLabelEn($name) {
        return 'Template "' . self::qEn($name) . '"';
    }

    public static function addTemplate($name) {
        $n = self::qZh($name);
        $ne = self::qEn($name);
        return [
            "新建 KYC 模板「{$n}」",
            "Created KYC template \"{$ne}\"",
        ];
    }

    public static function deleteTemplate($name) {
        $n = self::qZh($name);
        $ne = self::qEn($name);
        return [
            "删除 KYC 模板「{$n}」",
            "Deleted KYC template \"{$ne}\"",
        ];
    }

    public static function renameTemplate($oldName, $newName) {
        return [
            '模板「' . self::qZh($oldName) . '」：名称改为「' . self::qZh($newName) . '」',
            'Template "' . self::qEn($oldName) . '": renamed to "' . self::qEn($newName) . '"',
        ];
    }

    public static function updateDescription($templateName) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        return [
            "{$t}：更新了描述",
            "{$te}: description updated",
        ];
    }

    public static function changeStatus($templateName, $oldStatus, $newStatus) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        return [
            "{$t}：状态由「" . self::statusZh($oldStatus) . '」改为「' . self::statusZh($newStatus) . '」',
            "{$te}: status changed from \"" . self::statusEn($oldStatus) . '" to "' . self::statusEn($newStatus) . '"',
        ];
    }

    public static function toggleAutoApprove($templateName, $enabled) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        if ($enabled) {
            return ["{$t}：开启自动审批", "{$te}: auto-approve enabled"];
        }
        return ["{$t}：关闭自动审批", "{$te}: auto-approve disabled"];
    }

    public static function toggleDocSignature($templateName, $enabled) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        if ($enabled) {
            return ["{$t}：开启法律文档签名要求", "{$te}: legal document signature required"];
        }
        return ["{$t}：关闭法律文档签名要求", "{$te}: legal document signature requirement disabled"];
    }

    public static function updateCountries($templateName, array $countryNames) {
        $listZh = self::joinNames($countryNames);
        $listEn = $listZh;
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        return [
            "{$t}：适用国家改为 {$listZh}",
            "{$te}: countries set to {$listEn}",
        ];
    }

    public static function thirdPartyEnable($templateName, $providerLabel, $levelLabel) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        $binding = trim($providerLabel) . ' · ' . trim($levelLabel);
        return [
            "{$t}：开启第三方 KYC（{$binding}）",
            "{$te}: third-party KYC enabled ({$binding})",
        ];
    }

    public static function thirdPartyDisable($templateName) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        return [
            "{$t}：关闭第三方 KYC",
            "{$te}: third-party KYC disabled",
        ];
    }

    public static function thirdPartyRebind($templateName, $providerLabel, $levelLabel) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        $binding = trim($providerLabel) . ' · ' . trim($levelLabel);
        return [
            "{$t}：更新第三方 KYC 绑定（{$binding}）",
            "{$te}: third-party KYC binding updated ({$binding})",
        ];
    }

    public static function addCategory($templateName, $categoryName) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        $c = self::qZh($categoryName);
        $ce = self::qEn($categoryName);
        return [
            "{$t}：新增分类「{$c}」",
            "{$te}: added category \"{$ce}\"",
        ];
    }

    public static function renameCategory($templateName, $oldName, $newName) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        return [
            "{$t}：分类「" . self::qZh($oldName) . '」改名为「' . self::qZh($newName) . '」',
            "{$te}: category \"" . self::qEn($oldName) . '" renamed to "' . self::qEn($newName) . '"',
        ];
    }

    public static function updateCategory($templateName, $categoryName) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        $c = self::qZh($categoryName);
        $ce = self::qEn($categoryName);
        return [
            "{$t}：更新了分类「{$c}」",
            "{$te}: updated category \"{$ce}\"",
        ];
    }

    public static function deleteCategory($templateName, $categoryName) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        $c = self::qZh($categoryName);
        $ce = self::qEn($categoryName);
        return [
            "{$t}：删除分类「{$c}」",
            "{$te}: deleted category \"{$ce}\"",
        ];
    }

    public static function addQuestion($templateName, $questionText) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        $q = self::qZh($questionText);
        $qe = self::qEn($questionText);
        return [
            "{$t}：新增问题「{$q}」",
            "{$te}: added question \"{$qe}\"",
        ];
    }

    public static function changeQuestion($templateName, $oldText, $newText) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        return [
            "{$t}：问题「" . self::qZh($oldText) . '」改为「' . self::qZh($newText) . '」',
            "{$te}: question \"" . self::qEn($oldText) . '" changed to "' . self::qEn($newText) . '"',
        ];
    }

    public static function updateQuestion($templateName, $questionText) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        $q = self::qZh($questionText);
        $qe = self::qEn($questionText);
        return [
            "{$t}：更新了问题「{$q}」",
            "{$te}: updated question \"{$qe}\"",
        ];
    }

    public static function deleteQuestion($templateName, $questionText) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        $q = self::qZh($questionText);
        $qe = self::qEn($questionText);
        return [
            "{$t}：删除问题「{$q}」",
            "{$te}: deleted question \"{$qe}\"",
        ];
    }

    public static function duplicateQuestion($templateName, $questionText) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        $q = self::qZh($questionText);
        $qe = self::qEn($questionText);
        return [
            "{$t}：复制问题「{$q}」",
            "{$te}: duplicated question \"{$qe}\"",
        ];
    }

    public static function addRule($templateName, $ruleName) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        $r = self::qZh($ruleName);
        $re = self::qEn($ruleName);
        return [
            "{$t}：新增规则「{$r}」",
            "{$te}: added rule \"{$re}\"",
        ];
    }

    public static function renameRule($templateName, $oldName, $newName) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        return [
            "{$t}：规则「" . self::qZh($oldName) . '」改名为「' . self::qZh($newName) . '」',
            "{$te}: rule \"" . self::qEn($oldName) . '" renamed to "' . self::qEn($newName) . '"',
        ];
    }

    public static function updateRule($templateName, $ruleName) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        $r = self::qZh($ruleName);
        $re = self::qEn($ruleName);
        return [
            "{$t}：更新了规则「{$r}」",
            "{$te}: updated rule \"{$re}\"",
        ];
    }

    public static function deleteRule($templateName, $ruleName) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        $r = self::qZh($ruleName);
        $re = self::qEn($ruleName);
        return [
            "{$t}：删除规则「{$r}」",
            "{$te}: deleted rule \"{$re}\"",
        ];
    }

    public static function addDocument($templateName, $docTitle) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        $d = self::qZh($docTitle);
        $de = self::qEn($docTitle);
        return [
            "{$t}：新增法律文档「{$d}」",
            "{$te}: added legal document \"{$de}\"",
        ];
    }

    public static function renameDocument($templateName, $oldTitle, $newTitle) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        return [
            "{$t}：法律文档「" . self::qZh($oldTitle) . '」标题改为「' . self::qZh($newTitle) . '」',
            "{$te}: legal document \"" . self::qEn($oldTitle) . '" renamed to "' . self::qEn($newTitle) . '"',
        ];
    }

    public static function updateDocument($templateName, $documentTitle) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        $d = self::qZh($documentTitle);
        $de = self::qEn($documentTitle);
        return [
            "{$t}：更新了法律文档「{$d}」",
            "{$te}: updated legal document \"{$de}\"",
        ];
    }

    public static function deleteDocument($templateName, $docTitle) {
        $t = self::templateLabelZh($templateName);
        $te = self::templateLabelEn($templateName);
        $d = self::qZh($docTitle);
        $de = self::qEn($docTitle);
        return [
            "{$t}：删除法律文档「{$d}」",
            "{$te}: deleted legal document \"{$de}\"",
        ];
    }

    public static function templateAddFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 模板新建操作失败',
            'KYC template creation failed',
            $apiMessageEn
        );
    }

    public static function templateEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 模板编辑操作失败',
            'KYC template update failed',
            $apiMessageEn
        );
    }

    public static function templateAutoApproveFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 模板自动审批设置操作失败',
            'KYC template auto-approve setting failed',
            $apiMessageEn
        );
    }

    public static function templateDocSignatureFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 模板法律文档签名设置操作失败',
            'KYC template document signature setting failed',
            $apiMessageEn
        );
    }

    public static function templateThirdPartyFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 模板第三方 KYC 设置操作失败',
            'KYC template third-party KYC setting failed',
            $apiMessageEn
        );
    }

    public static function templateDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 模板删除操作失败',
            'KYC template deletion failed',
            $apiMessageEn
        );
    }

    public static function questionAddFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 模板问题新增操作失败',
            'KYC template question creation failed',
            $apiMessageEn
        );
    }

    public static function questionEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 模板问题编辑操作失败',
            'KYC template question update failed',
            $apiMessageEn
        );
    }

    public static function questionDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 模板问题删除操作失败',
            'KYC template question deletion failed',
            $apiMessageEn
        );
    }

    public static function categoryAddFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 模板分类新增操作失败',
            'KYC template category creation failed',
            $apiMessageEn
        );
    }

    public static function categoryEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 模板分类编辑操作失败',
            'KYC template category update failed',
            $apiMessageEn
        );
    }

    public static function categoryDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 模板分类删除操作失败',
            'KYC template category deletion failed',
            $apiMessageEn
        );
    }

    public static function ruleAddFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 模板规则新增操作失败',
            'KYC template rule creation failed',
            $apiMessageEn
        );
    }

    public static function ruleEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 模板规则编辑操作失败',
            'KYC template rule update failed',
            $apiMessageEn
        );
    }

    public static function ruleDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 模板规则删除操作失败',
            'KYC template rule deletion failed',
            $apiMessageEn
        );
    }

    public static function documentAddFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 模板法律文档新增操作失败',
            'KYC template legal document creation failed',
            $apiMessageEn
        );
    }

    public static function documentEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 模板法律文档编辑操作失败',
            'KYC template legal document update failed',
            $apiMessageEn
        );
    }

    public static function documentDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 模板法律文档删除操作失败',
            'KYC template legal document deletion failed',
            $apiMessageEn
        );
    }

    // -------------------------------------------------------------------------
    // kyc_settings（subModuleKey: kyc_settings；targetId 恒为 null）
    // -------------------------------------------------------------------------

    public static function noticeFieldLabelZh($field) {
        $map = [
            'isEnabled' => '启用状态',
            'noticeTitle' => '标题',
            'noticeSubtitle' => '副标题',
            'noticeDescription' => '描述',
            'requirementsTitle' => '要求区标题',
            'verificationTimeNotice' => '审核时效说明',
            'primaryButtonText' => '主按钮文案',
            'primaryButtonAction' => '主按钮动作',
            'secondaryButtonText' => '次按钮文案',
            'secondaryButtonAction' => '次按钮动作',
            'displayPosition' => '展示位置',
            'displayPriority' => '展示优先级',
            'isDismissible' => '可关闭',
            'showIcon' => '显示图标',
            'iconClass' => '图标样式',
            'backgroundColor' => '背景色',
            'borderColor' => '边框色',
        ];
        return $map[$field] ?? $field;
    }

    public static function noticeFieldLabelEn($field) {
        $map = [
            'isEnabled' => 'Enabled',
            'noticeTitle' => 'Title',
            'noticeSubtitle' => 'Subtitle',
            'noticeDescription' => 'Description',
            'requirementsTitle' => 'Requirements Title',
            'verificationTimeNotice' => 'Verification Time Notice',
            'primaryButtonText' => 'Primary Button Text',
            'primaryButtonAction' => 'Primary Button Action',
            'secondaryButtonText' => 'Secondary Button Text',
            'secondaryButtonAction' => 'Secondary Button Action',
            'displayPosition' => 'Display Position',
            'displayPriority' => 'Display Priority',
            'isDismissible' => 'Dismissible',
            'showIcon' => 'Show Icon',
            'iconClass' => 'Icon Class',
            'backgroundColor' => 'Background Color',
            'borderColor' => 'Border Color',
        ];
        return $map[$field] ?? $field;
    }

    public static function gatewayFieldLabelZh($field) {
        $map = [
            'displayName' => '显示名称',
            'environment' => '环境',
            'baseUrl' => 'API 地址',
            'iframeBaseUrl' => 'iframe 地址',
            'returnUrl' => '完成跳转 URL',
            'detailUrl' => '详情页 URL',
            'api_credentials' => 'API 凭据',
            'configData' => '扩展配置',
            'sync_levels' => '同步等级列表',
        ];
        return $map[$field] ?? $field;
    }

    public static function gatewayFieldLabelEn($field) {
        $map = [
            'displayName' => 'Display Name',
            'environment' => 'Environment',
            'baseUrl' => 'Base URL',
            'iframeBaseUrl' => 'iframe Base URL',
            'returnUrl' => 'Return URL',
            'detailUrl' => 'Detail URL',
            'api_credentials' => 'API Credentials',
            'configData' => 'Extended Config',
            'sync_levels' => 'Sync Verification Levels',
        ];
        return $map[$field] ?? $field;
    }

    /**
     * @param string[] $changedFields DB/API 字段名
     */
    public static function noticeSettingsUpdate(array $changedFields) {
        $labelsZh = [];
        $labelsEn = [];
        foreach ($changedFields as $field) {
            $labelsZh[] = self::noticeFieldLabelZh($field);
            $labelsEn[] = self::noticeFieldLabelEn($field);
        }
        $listZh = $labelsZh ? implode('、', $labelsZh) : '设置';
        $listEn = $labelsEn ? implode(', ', $labelsEn) : 'settings';
        return [
            "更新了客户端 KYC 提示文案：修改{$listZh}",
            "Updated client KYC notice copy: updated {$listEn}",
        ];
    }

    /**
     * @param string[] $changedFields 含 api_credentials、sync_levels 等逻辑字段
     */
    public static function gatewayUpdate($gatewayLabel, array $changedFields) {
        $name = self::qZh($gatewayLabel);
        $nameEn = self::qEn($gatewayLabel);
        $labelsZh = [];
        $labelsEn = [];
        foreach ($changedFields as $field) {
            $labelsZh[] = self::gatewayFieldLabelZh($field);
            $labelsEn[] = self::gatewayFieldLabelEn($field);
        }
        $listZh = $labelsZh ? implode('、', $labelsZh) : '配置';
        $listEn = $labelsEn ? implode(', ', $labelsEn) : 'settings';
        return [
            "修改第三方 KYC 网关「{$name}」：修改{$listZh}",
            "Updated external KYC gateway \"{$nameEn}\": updated {$listEn}",
        ];
    }

    public static function gatewayEnable($gatewayLabel, $enabled) {
        $name = self::qZh($gatewayLabel);
        $nameEn = self::qEn($gatewayLabel);
        if ($enabled) {
            return [
                "修改第三方 KYC 网关「{$name}」：启用",
                "Updated external KYC gateway \"{$nameEn}\": enabled",
            ];
        }
        return [
            "修改第三方 KYC 网关「{$name}」：停用",
            "Updated external KYC gateway \"{$nameEn}\": disabled",
        ];
    }

    public static function gatewayDelete($gatewayLabel) {
        $name = self::qZh($gatewayLabel);
        $nameEn = self::qEn($gatewayLabel);
        return [
            "删除第三方 KYC 网关「{$name}」",
            "Deleted external KYC gateway \"{$nameEn}\"",
        ];
    }

    public static function gatewaySync($gatewayLabel) {
        $name = self::qZh($gatewayLabel);
        $nameEn = self::qEn($gatewayLabel);
        return [
            "同步第三方 KYC 网关「{$name}」等级列表",
            "Synced verification levels for external KYC gateway \"{$nameEn}\"",
        ];
    }

    public static function noticeSettingsUpdateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            'KYC 提示文案更新操作失败',
            'KYC notice settings update failed',
            $apiMessageEn
        );
    }

    public static function gatewayUpdateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '第三方 KYC 网关编辑操作失败',
            'External KYC gateway update failed',
            $apiMessageEn
        );
    }

    public static function gatewayEnableFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '第三方 KYC 网关状态更新操作失败',
            'External KYC gateway status update failed',
            $apiMessageEn
        );
    }

    public static function gatewayDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '第三方 KYC 网关删除操作失败',
            'External KYC gateway deletion failed',
            $apiMessageEn
        );
    }

    public static function gatewaySyncFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '第三方 KYC 网关同步等级操作失败',
            'External KYC gateway level sync failed',
            $apiMessageEn
        );
    }

    public static function resolveGatewayLabel(array $gateway) {
        $display = trim((string) ($gateway['displayName'] ?? ''));
        if ($display !== '') {
            return $display;
        }
        $provider = trim((string) ($gateway['provider'] ?? ''));
        return $provider !== '' ? ucfirst($provider) : 'Gateway';
    }

    // -------------------------------------------------------------------------
    // 共用
    // -------------------------------------------------------------------------

    public static function resolveTemplateName($templateId) {
        $id = (int) $templateId;
        if ($id <= 0) {
            return '';
        }
        require_once __DIR__ . '/../../models/KycTemplate.php';
        $row = (new KycTemplate())->findById($id);
        return trim((string) ($row['templateName'] ?? ''));
    }

    private static function joinNames(array $names) {
        $parts = [];
        foreach ($names as $name) {
            $n = trim((string) $name);
            if ($n !== '') {
                $parts[] = $n;
            }
        }
        return $parts ? implode(', ', $parts) : '—';
    }

    private static function qZh($text) {
        return str_replace(['「', '」'], ['\'', '\''], trim((string) $text));
    }

    private static function qEn($text) {
        return trim((string) $text);
    }
}
