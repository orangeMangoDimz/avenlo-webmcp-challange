<?php
/**
 * 交易模块（log_transaction）操作日志 — 中英详情文案
 * 子模块：deposits、withdrawals、internal_transfer、address_verification、transaction_settings 等
 */

require_once __DIR__ . '/OperationLogTextHelpers.php';

class TransactionOperationLogTexts {
    public static function formatAmount($amount) {
        return number_format((float) $amount, 2, '.', '');
    }

    public static function depositApprove($transactionId, $clientName, $amount, $clientId) {
        $txn = trim((string) $transactionId);
        $name = trim((string) $clientName);
        $amt = self::formatAmount($amount);
        $id = (int) $clientId;
        return [
            "批准入金：{$txn}；客户：{$name}；金额：{$amt}；客户 ID：{$id}",
            "Approved deposit: {$txn}; client: {$name}; amount: {$amt}; client ID: {$id}",
        ];
    }

    public static function depositReject($transactionId, $reasonTitle, $clientId) {
        $txn = trim((string) $transactionId);
        $reason = trim((string) $reasonTitle);
        $id = (int) $clientId;
        return [
            "拒绝入金：{$txn}；原因：{$reason}；客户 ID：{$id}",
            "Rejected deposit: {$txn}; reason: {$reason}; client ID: {$id}",
        ];
    }

    public static function depositBulkApprove($count, array $transactionIds) {
        $n = (int) $count;
        $ids = OperationLogTextHelpers::formatTransactionIdList($transactionIds);
        return [
            "批量批准 {$n} 笔入金；交易编号：{$ids}",
            "Bulk approved {$n} deposit(s); transaction IDs: {$ids}",
        ];
    }

    public static function depositTagBulk($count, array $transactionIds, $tagName, $clientId = null) {
        $tag = trim((string) $tagName);
        $txnList = OperationLogTextHelpers::formatTransactionIdList($transactionIds);
        $n = (int) $count;
        if ($n <= 1 && $clientId !== null && (int) $clientId > 0) {
            $id = (int) $clientId;
            $txn = $txnList !== '' ? $txnList : '—';
            return [
                "为入金添加标签「{$tag}」；交易编号：{$txn}；客户 ID：{$id}",
                "Added tag \"{$tag}\" to deposit; transaction ID: {$txn}; client ID: {$id}",
            ];
        }
        return [
            "为 {$n} 笔入金添加标签「{$tag}」；交易编号：{$txnList}",
            "Added tag \"{$tag}\" to {$n} deposit(s); transaction IDs: {$txnList}",
        ];
    }

    public static function depositTagRemove($transactionId, $tagName, $clientId) {
        $txn = trim((string) $transactionId);
        $tag = trim((string) $tagName);
        $id = (int) $clientId;
        return [
            "移除入金标签「{$tag}」；交易编号：{$txn}；客户 ID：{$id}",
            "Removed deposit tag \"{$tag}\"; transaction ID: {$txn}; client ID: {$id}",
        ];
    }

    public static function depositNoteAdd($transactionId, $clientId) {
        $txn = trim((string) $transactionId);
        $id = (int) $clientId;
        return [
            "添加入金备注；交易编号：{$txn}；客户 ID：{$id}",
            "Added deposit note; transaction ID: {$txn}; client ID: {$id}",
        ];
    }

    public static function depositEmail($email) {
        $email = trim((string) $email);
        return [
            "向 {$email} 发送入金相关邮件；",
            "Sent deposit-related email to {$email};",
        ];
    }

    public static function depositExport($count, $format) {
        $n = (int) $count;
        $fmt = strtoupper(trim((string) $format));
        return [
            "导出 {$n} 笔入金（{$fmt}）",
            "Exported {$n} deposit(s) ({$fmt})",
        ];
    }

    public static function depositApproveFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '入金审批同意操作失败',
            'Deposit approval failed',
            $apiMessageEn
        );
    }

    public static function depositRejectFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '入金审批拒绝操作失败',
            'Deposit rejection failed',
            $apiMessageEn
        );
    }

    public static function depositBulkApproveFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '批量入金审批同意操作失败',
            'Bulk deposit approval failed',
            $apiMessageEn
        );
    }

    public static function depositTagBulkFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '入金批量添加标签操作失败',
            'Bulk deposit tagging failed',
            $apiMessageEn
        );
    }

    public static function depositTagRemoveFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '入金移除标签操作失败',
            'Deposit tag removal failed',
            $apiMessageEn
        );
    }

    public static function depositNoteAddFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '入金添加备注操作失败',
            'Deposit note add failed',
            $apiMessageEn
        );
    }

    public static function depositEmailFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '入金联系客户发邮件操作失败',
            'Deposit client email failed',
            $apiMessageEn
        );
    }

    public static function depositExportFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '入金导出操作失败',
            'Deposit export failed',
            $apiMessageEn
        );
    }

    public static function searchTagCreate($tagName, $keywords) {
        $tagName = trim((string) $tagName);
        $keywords = trim((string) $keywords);
        return [
            "新建搜索标签：{$tagName}（关键词：{$keywords}）",
            "Created search tag \"{$tagName}\" (keywords: {$keywords})",
        ];
    }

    public static function searchTagDelete($tagName) {
        $tagName = trim((string) $tagName);
        return [
            "删除搜索标签：{$tagName}",
            "Deleted search tag \"{$tagName}\"",
        ];
    }

    public static function searchTagCreateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '新建搜索标签操作失败',
            'Search tag creation failed',
            $apiMessageEn
        );
    }

    public static function searchTagDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '删除搜索标签操作失败',
            'Search tag deletion failed',
            $apiMessageEn
        );
    }

    // -------------------------------------------------------------------------
    // Withdrawals（出金）
    // -------------------------------------------------------------------------

    public static function withdrawalApproveSuccess($transactionId, $clientName, $amount, $clientId) {
        $txn = trim((string) $transactionId);
        $name = trim((string) $clientName);
        $amt = self::formatAmount($amount);
        $id = (int) $clientId;
        return [
            "出金审批同意：{$txn}；客户：{$name}；金额：{$amt}；客户 ID：{$id}",
            "Withdrawal approved: {$txn}; client: {$name}; amount: {$amt}; client ID: {$id}",
        ];
    }

    public static function withdrawalApproveFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金审批同意操作失败',
            'Withdrawal approval failed',
            $apiMessageEn
        );
    }

    public static function withdrawalRejectSuccess($transactionId, $reasonTitle, $clientId) {
        $txn = trim((string) $transactionId);
        $reason = trim((string) $reasonTitle);
        $id = (int) $clientId;
        return [
            "出金审批拒绝：{$txn}；原因：{$reason}；客户 ID：{$id}",
            "Withdrawal rejected: {$txn}; reason: {$reason}; client ID: {$id}",
        ];
    }

    public static function withdrawalRejectFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金审批拒绝操作失败',
            'Withdrawal rejection failed',
            $apiMessageEn
        );
    }

    public static function withdrawalBulkApproveSuccess($count, array $transactionIds) {
        $n = (int) $count;
        $ids = OperationLogTextHelpers::formatTransactionIdList($transactionIds);
        return [
            "批量出金审批同意：{$n} 笔；交易编号：{$ids}",
            "Bulk withdrawal approval: {$n} item(s); transaction IDs: {$ids}",
        ];
    }

    public static function withdrawalBulkApproveFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '批量出金审批同意操作失败',
            'Bulk withdrawal approval failed',
            $apiMessageEn
        );
    }

    public static function withdrawalTagBulkSuccess($count, array $transactionIds, $tagName, $clientId = null) {
        $tag = trim((string) $tagName);
        $txnList = OperationLogTextHelpers::formatTransactionIdList($transactionIds);
        $n = (int) $count;
        if ($n <= 1 && $clientId !== null && (int) $clientId > 0) {
            $id = (int) $clientId;
            $txn = $txnList !== '' ? $txnList : '—';
            return [
                "出金添加标签「{$tag}」：{$txn}；客户 ID：{$id}",
                "Added withdrawal tag \"{$tag}\": {$txn}; client ID: {$id}",
            ];
        }
        return [
            "为 {$n} 笔出金添加标签「{$tag}」；交易编号：{$txnList}",
            "Added tag \"{$tag}\" to {$n} withdrawal(s); transaction IDs: {$txnList}",
        ];
    }

    public static function withdrawalTagBulkFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金批量添加标签操作失败',
            'Bulk withdrawal tagging failed',
            $apiMessageEn
        );
    }

    public static function withdrawalTagRemoveSuccess($transactionId, $tagName, $clientId) {
        $txn = trim((string) $transactionId);
        $tag = trim((string) $tagName);
        $id = (int) $clientId;
        return [
            "出金移除标签「{$tag}」：{$txn}；客户 ID：{$id}",
            "Removed withdrawal tag \"{$tag}\": {$txn}; client ID: {$id}",
        ];
    }

    public static function withdrawalTagRemoveFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金移除标签操作失败',
            'Withdrawal tag removal failed',
            $apiMessageEn
        );
    }

    public static function withdrawalRequestDocumentsSuccess($transactionId) {
        $txn = trim((string) $transactionId);
        return [
            "出金 {$txn} 需要补充文件",
            "Withdrawal {$txn}: additional documents required",
        ];
    }

    public static function withdrawalRequestDocumentsFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金补充文件请求操作失败',
            'Withdrawal document request failed',
            $apiMessageEn
        );
    }

    public static function withdrawalEmailSuccess($email) {
        $email = trim((string) $email);
        return [
            "向 {$email} 发送出金相关邮件",
            "Sent withdrawal-related email to {$email}",
        ];
    }

    public static function withdrawalEmailFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金联系客户发邮件操作失败',
            'Withdrawal client email failed',
            $apiMessageEn
        );
    }

    public static function withdrawalExportSuccess($count, $format) {
        $n = (int) $count;
        $fmt = strtoupper(trim((string) $format));
        return [
            "导出 {$n} 笔出金（{$fmt}）",
            "Exported {$n} withdrawal(s) ({$fmt})",
        ];
    }

    public static function withdrawalExportFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金导出操作失败',
            'Withdrawal export failed',
            $apiMessageEn
        );
    }

    // -------------------------------------------------------------------------
    // Internal Transfers（内部转账）
    // -------------------------------------------------------------------------

    public static function internalTransferApproveSuccess($transactionId, $clientName, $amount, $clientId) {
        $txn = trim((string) $transactionId);
        $name = trim((string) $clientName);
        $amt = self::formatAmount($amount);
        $id = (int) $clientId;
        return [
            "批准内部转账：{$txn}；客户：{$name}；金额：{$amt}；客户 ID：{$id}",
            "Approved internal transfer: {$txn}; client: {$name}; amount: {$amt}; client ID: {$id}",
        ];
    }

    public static function internalTransferApproveFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '批准内部转账操作失败',
            'Internal transfer approval failed',
            $apiMessageEn
        );
    }

    public static function internalTransferRejectSuccess($transactionId, $reasonTitle, $clientId) {
        $txn = trim((string) $transactionId);
        $reason = trim((string) $reasonTitle);
        $id = (int) $clientId;
        return [
            "拒绝内部转账：{$txn}；原因：{$reason}；客户 ID：{$id}",
            "Rejected internal transfer: {$txn}; reason: {$reason}; client ID: {$id}",
        ];
    }

    public static function internalTransferRejectFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '拒绝内部转账操作失败',
            'Internal transfer rejection failed',
            $apiMessageEn
        );
    }

    public static function internalTransferBulkApproveSuccess($count, array $transactionIds) {
        $n = (int) $count;
        $ids = OperationLogTextHelpers::formatTransactionIdList($transactionIds);
        return [
            "批量批准 {$n} 笔内部转账；交易编号：{$ids}",
            "Bulk approved {$n} internal transfer(s); transaction IDs: {$ids}",
        ];
    }

    public static function internalTransferBulkApproveFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '批量批准内部转账操作失败',
            'Internal transfer bulk approval failed',
            $apiMessageEn
        );
    }

    public static function internalTransferTagBulkSuccess($count, array $transactionIds, $tagName, $clientId = null) {
        $tag = trim((string) $tagName);
        $txnList = OperationLogTextHelpers::formatTransactionIdList($transactionIds);
        $n = (int) $count;
        if ($n <= 1 && $clientId !== null && (int) $clientId > 0) {
            $id = (int) $clientId;
            $txn = $txnList !== '' ? $txnList : '—';
            return [
                "为内部转账添加标签「{$tag}」；交易编号：{$txn}；客户 ID：{$id}",
                "Added tag \"{$tag}\" to internal transfer; transaction ID: {$txn}; client ID: {$id}",
            ];
        }
        return [
            "为 {$n} 笔内部转账添加标签「{$tag}」；交易编号：{$txnList}",
            "Added tag \"{$tag}\" to {$n} internal transfer(s); transaction IDs: {$txnList}",
        ];
    }

    public static function internalTransferTagBulkFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '批量添加内部转账标签操作失败',
            'Internal transfer bulk tag assignment failed',
            $apiMessageEn
        );
    }

    public static function internalTransferTagRemoveSuccess($transactionId, $tagName, $clientId) {
        $txn = trim((string) $transactionId);
        $tag = trim((string) $tagName);
        $id = (int) $clientId;
        return [
            "移除内部转账标签「{$tag}」；交易编号：{$txn}；客户 ID：{$id}",
            "Removed internal transfer tag \"{$tag}\"; transaction ID: {$txn}; client ID: {$id}",
        ];
    }

    public static function internalTransferTagRemoveFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '移除内部转账标签操作失败',
            'Internal transfer tag removal failed',
            $apiMessageEn
        );
    }

    public static function internalTransferNoteAddSuccess($transactionId, $clientId) {
        $txn = trim((string) $transactionId);
        $id = (int) $clientId;
        return [
            "添加内部转账备注；交易编号：{$txn}；客户 ID：{$id}",
            "Added internal transfer note; transaction ID: {$txn}; client ID: {$id}",
        ];
    }

    public static function internalTransferNoteAddFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '添加内部转账备注操作失败',
            'Internal transfer note creation failed',
            $apiMessageEn
        );
    }

    public static function internalTransferEmailSuccess($email) {
        $email = trim((string) $email);
        return [
            "向 {$email} 发送内部转账相关邮件；",
            "Sent internal transfer-related email to {$email};",
        ];
    }

    public static function internalTransferEmailFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '发送内部转账邮件操作失败',
            'Internal transfer email failed',
            $apiMessageEn
        );
    }

    public static function internalTransferExportSuccess($count, $format) {
        $n = (int) $count;
        $fmt = strtoupper(trim((string) $format));
        return [
            "导出 {$n} 笔内部转账（{$fmt}）",
            "Exported {$n} internal transfer(s) ({$fmt})",
        ];
    }

    public static function internalTransferExportFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '内部转账导出操作失败',
            'Internal transfer export failed',
            $apiMessageEn
        );
    }

    // -------------------------------------------------------------------------
    // address_verification（subModuleKey: address_verification）
    // -------------------------------------------------------------------------

    public static function addressVerificationApprove($submissionId, $gatewayName, $clientId, $notes = null) {
        $sid = (int) $submissionId;
        $cid = (int) $clientId;
        $gateway = trim((string) $gatewayName);
        $gwZh = $gateway !== '' ? "；网关：{$gateway}" : '';
        $gwEn = $gateway !== '' ? "; gateway: {$gateway}" : '';
        $noteZh = $notes ? OperationLogTextHelpers::notesSuffixZh($notes) : '';
        $noteEn = $notes ? OperationLogTextHelpers::notesSuffixEn($notes) : '';
        return [
            "审批通过地址验证提交 #{$sid}{$gwZh}；客户 ID：{$cid}{$noteZh}",
            "Approved address verification submission #{$sid}{$gwEn}; client ID: {$cid}{$noteEn}",
        ];
    }

    public static function addressVerificationApproveFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '地址验证审批通过操作失败',
            'Address verification approval failed',
            $apiMessageEn
        );
    }

    public static function addressVerificationReject($submissionId, $gatewayName, $reason, $clientId) {
        $sid = (int) $submissionId;
        $cid = (int) $clientId;
        $gateway = trim((string) $gatewayName);
        $gwZh = $gateway !== '' ? "；网关：{$gateway}" : '';
        $gwEn = $gateway !== '' ? "; gateway: {$gateway}" : '';
        $reason = trim((string) $reason);
        return [
            "拒绝地址验证提交 #{$sid}{$gwZh}；原因：{$reason}；客户 ID：{$cid}",
            "Rejected address verification submission #{$sid}{$gwEn}; reason: {$reason}; client ID: {$cid}",
        ];
    }

    public static function addressVerificationRejectFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '地址验证拒绝操作失败',
            'Address verification rejection failed',
            $apiMessageEn
        );
    }

    public static function addressVerificationNeedDocs($submissionId, $gatewayName, $itemCount, $clientId) {
        $sid = (int) $submissionId;
        $cid = (int) $clientId;
        $gateway = trim((string) $gatewayName);
        $gwZh = $gateway !== '' ? "；网关：{$gateway}" : '';
        $gwEn = $gateway !== '' ? "; gateway: {$gateway}" : '';
        $count = (int) $itemCount;
        return [
            "要求地址验证补件 #{$sid}{$gwZh}；请求 {$count} 项；客户 ID：{$cid}",
            "Requested address verification resubmission #{$sid}{$gwEn}; {$count} item(s); client ID: {$cid}",
        ];
    }

    public static function addressVerificationNeedDocsFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '地址验证补件请求操作失败',
            'Address verification document request failed',
            $apiMessageEn
        );
    }

    public static function addressVerificationAssign($submissionId, $gatewayName, $reviewerName, $clientId) {
        $sid = (int) $submissionId;
        $cid = (int) $clientId;
        $gateway = trim((string) $gatewayName);
        $gwZh = $gateway !== '' ? "；网关：{$gateway}" : '';
        $gwEn = $gateway !== '' ? "; gateway: {$gateway}" : '';
        $reviewer = trim((string) $reviewerName);
        return [
            "分配地址验证提交 #{$sid} 给审核员：{$reviewer}{$gwZh}；客户 ID：{$cid}",
            "Assigned address verification submission #{$sid} to reviewer: {$reviewer}{$gwEn}; client ID: {$cid}",
        ];
    }

    public static function addressVerificationAssignFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '地址验证分配审核员操作失败',
            'Address verification reviewer assignment failed',
            $apiMessageEn
        );
    }

    // -------------------------------------------------------------------------
    // withdraw_kyc_templates（subModuleKey: withdraw_kyc_templates）
    // -------------------------------------------------------------------------

    public static function resolveWithdrawKycTemplateMeta($templateId) {
        $id = (int) $templateId;
        if ($id <= 0) {
            return ['name' => '', 'gateway' => ''];
        }
        require_once __DIR__ . '/../../models/WithdrawalVerificationTemplate.php';
        $row = (new WithdrawalVerificationTemplate())->getTemplateDetails($id);
        if (!is_array($row)) {
            return ['name' => '', 'gateway' => ''];
        }
        return [
            'name' => trim((string) ($row['templateName'] ?? '')),
            'gateway' => trim((string) ($row['gatewayName'] ?? '')),
        ];
    }

    public static function withdrawKycStatusZh($status) {
        $map = [
            'draft' => '草稿',
            'active' => '已启用',
            'inactive' => '已停用',
            'archived' => '已归档',
        ];
        $key = strtolower(trim((string) $status));
        return $map[$key] ?? $status;
    }

    public static function withdrawKycStatusEn($status) {
        $map = [
            'draft' => 'Draft',
            'active' => 'Active',
            'inactive' => 'Inactive',
            'archived' => 'Archived',
        ];
        $key = strtolower(trim((string) $status));
        return $map[$key] ?? $status;
    }

    public static function withdrawKycRenameTemplate($oldName, $newName, $gatewayName = '') {
        $t = self::withdrawKycTemplateLabelZh($oldName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($oldName, $gatewayName);
        return [
            "{$t}：名称改为「" . self::wktQ($newName) . '」',
            "{$te}: renamed to \"" . self::wktQEn($newName) . '"',
        ];
    }

    public static function withdrawKycUpdateDescription($templateName, $gatewayName = '') {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        return [
            "{$t}：更新了描述",
            "{$te}: description updated",
        ];
    }

    public static function withdrawKycChangeStatus($templateName, $oldStatus, $newStatus, $gatewayName = '') {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        return [
            "{$t}：状态由「" . self::withdrawKycStatusZh($oldStatus) . '」改为「' . self::withdrawKycStatusZh($newStatus) . '」',
            "{$te}: status changed from \"" . self::withdrawKycStatusEn($oldStatus) . '" to "' . self::withdrawKycStatusEn($newStatus) . '"',
        ];
    }

    public static function withdrawKycToggleAutoApprove($templateName, $enabled, $gatewayName = '') {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        if ($enabled) {
            return ["{$t}：开启自动审批", "{$te}: auto-approve enabled"];
        }
        return ["{$t}：关闭自动审批", "{$te}: auto-approve disabled"];
    }

    public static function withdrawKycToggleDocSignature($templateName, $enabled, $gatewayName = '') {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        if ($enabled) {
            return ["{$t}：开启法律文档签名要求", "{$te}: legal document signature required"];
        }
        return ["{$t}：关闭法律文档签名要求", "{$te}: legal document signature requirement disabled"];
    }

    public static function withdrawKycAddCategory($templateName, $gatewayName, $categoryName) {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        $c = self::wktQ($categoryName);
        $ce = self::wktQEn($categoryName);
        return [
            "{$t}：新增分类「{$c}」",
            "{$te}: added category \"{$ce}\"",
        ];
    }

    public static function withdrawKycRenameCategory($templateName, $gatewayName, $oldName, $newName) {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        return [
            "{$t}：分类「" . self::wktQ($oldName) . '」改名为「' . self::wktQ($newName) . '」',
            "{$te}: category \"" . self::wktQEn($oldName) . '" renamed to "' . self::wktQEn($newName) . '"',
        ];
    }

    public static function withdrawKycUpdateCategory($templateName, $gatewayName, $categoryName) {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        $c = self::wktQ($categoryName);
        $ce = self::wktQEn($categoryName);
        return [
            "{$t}：更新了分类「{$c}」",
            "{$te}: updated category \"{$ce}\"",
        ];
    }

    public static function withdrawKycDeleteCategory($templateName, $gatewayName, $categoryName) {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        $c = self::wktQ($categoryName);
        $ce = self::wktQEn($categoryName);
        return [
            "{$t}：删除分类「{$c}」",
            "{$te}: deleted category \"{$ce}\"",
        ];
    }

    public static function withdrawKycAddQuestion($templateName, $gatewayName, $questionText) {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        $q = self::wktQ($questionText);
        $qe = self::wktQEn($questionText);
        return [
            "{$t}：新增问题「{$q}」",
            "{$te}: added question \"{$qe}\"",
        ];
    }

    public static function withdrawKycChangeQuestion($templateName, $gatewayName, $oldText, $newText) {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        return [
            "{$t}：问题「" . self::wktQ($oldText) . '」改为「' . self::wktQ($newText) . '」',
            "{$te}: question \"" . self::wktQEn($oldText) . '" changed to "' . self::wktQEn($newText) . '"',
        ];
    }

    public static function withdrawKycUpdateQuestion($templateName, $gatewayName, $questionText) {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        $q = self::wktQ($questionText);
        $qe = self::wktQEn($questionText);
        return [
            "{$t}：更新了问题「{$q}」",
            "{$te}: updated question \"{$qe}\"",
        ];
    }

    public static function withdrawKycDeleteQuestion($templateName, $gatewayName, $questionText) {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        $q = self::wktQ($questionText);
        $qe = self::wktQEn($questionText);
        return [
            "{$t}：删除问题「{$q}」",
            "{$te}: deleted question \"{$qe}\"",
        ];
    }

    public static function withdrawKycDuplicateQuestion($templateName, $gatewayName, $questionText) {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        $q = self::wktQ($questionText);
        $qe = self::wktQEn($questionText);
        return [
            "{$t}：复制问题「{$q}」",
            "{$te}: duplicated question \"{$qe}\"",
        ];
    }

    public static function withdrawKycAddRule($templateName, $gatewayName, $ruleName) {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        $r = self::wktQ($ruleName);
        $re = self::wktQEn($ruleName);
        return [
            "{$t}：新增规则「{$r}」",
            "{$te}: added rule \"{$re}\"",
        ];
    }

    public static function withdrawKycRenameRule($templateName, $gatewayName, $oldName, $newName) {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        return [
            "{$t}：规则「" . self::wktQ($oldName) . '」改名为「' . self::wktQ($newName) . '」',
            "{$te}: rule \"" . self::wktQEn($oldName) . '" renamed to "' . self::wktQEn($newName) . '"',
        ];
    }

    public static function withdrawKycUpdateRule($templateName, $gatewayName, $ruleName) {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        $r = self::wktQ($ruleName);
        $re = self::wktQEn($ruleName);
        return [
            "{$t}：更新了规则「{$r}」",
            "{$te}: updated rule \"{$re}\"",
        ];
    }

    public static function withdrawKycDeleteRule($templateName, $gatewayName, $ruleName) {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        $r = self::wktQ($ruleName);
        $re = self::wktQEn($ruleName);
        return [
            "{$t}：删除规则「{$r}」",
            "{$te}: deleted rule \"{$re}\"",
        ];
    }

    public static function withdrawKycAddDocument($templateName, $gatewayName, $docTitle) {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        $d = self::wktQ($docTitle);
        $de = self::wktQEn($docTitle);
        return [
            "{$t}：新增法律文档「{$d}」",
            "{$te}: added legal document \"{$de}\"",
        ];
    }

    public static function withdrawKycRenameDocument($templateName, $gatewayName, $oldTitle, $newTitle) {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        return [
            "{$t}：法律文档「" . self::wktQ($oldTitle) . '」标题改为「' . self::wktQ($newTitle) . '」',
            "{$te}: legal document \"" . self::wktQEn($oldTitle) . '" renamed to "' . self::wktQEn($newTitle) . '"',
        ];
    }

    public static function withdrawKycUpdateDocument($templateName, $gatewayName, $documentTitle) {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        $d = self::wktQ($documentTitle);
        $de = self::wktQEn($documentTitle);
        return [
            "{$t}：更新了法律文档「{$d}」",
            "{$te}: updated legal document \"{$de}\"",
        ];
    }

    public static function withdrawKycDeleteDocument($templateName, $gatewayName, $docTitle) {
        $t = self::withdrawKycTemplateLabelZh($templateName, $gatewayName);
        $te = self::withdrawKycTemplateLabelEn($templateName, $gatewayName);
        $d = self::wktQ($docTitle);
        $de = self::wktQEn($docTitle);
        return [
            "{$t}：删除法律文档「{$d}」",
            "{$te}: deleted legal document \"{$de}\"",
        ];
    }

    public static function withdrawKycTemplateEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金KYC模板编辑操作失败',
            'Withdraw KYC template update failed',
            $apiMessageEn
        );
    }

    public static function withdrawKycTemplateAutoApproveFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金KYC模板自动审批设置操作失败',
            'Withdraw KYC template auto-approve setting failed',
            $apiMessageEn
        );
    }

    public static function withdrawKycTemplateDocSignatureFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金KYC模板法律文档签名设置操作失败',
            'Withdraw KYC template document signature setting failed',
            $apiMessageEn
        );
    }

    public static function withdrawKycCategoryAddFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金KYC模板分类新增操作失败',
            'Withdraw KYC template category creation failed',
            $apiMessageEn
        );
    }

    public static function withdrawKycCategoryEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金KYC模板分类编辑操作失败',
            'Withdraw KYC template category update failed',
            $apiMessageEn
        );
    }

    public static function withdrawKycCategoryDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金KYC模板分类删除操作失败',
            'Withdraw KYC template category deletion failed',
            $apiMessageEn
        );
    }

    public static function withdrawKycQuestionAddFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金KYC模板问题新增操作失败',
            'Withdraw KYC template question creation failed',
            $apiMessageEn
        );
    }

    public static function withdrawKycQuestionEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金KYC模板问题编辑操作失败',
            'Withdraw KYC template question update failed',
            $apiMessageEn
        );
    }

    public static function withdrawKycQuestionDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金KYC模板问题删除操作失败',
            'Withdraw KYC template question deletion failed',
            $apiMessageEn
        );
    }

    public static function withdrawKycRuleAddFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金KYC模板规则新增操作失败',
            'Withdraw KYC template rule creation failed',
            $apiMessageEn
        );
    }

    public static function withdrawKycRuleEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金KYC模板规则编辑操作失败',
            'Withdraw KYC template rule update failed',
            $apiMessageEn
        );
    }

    public static function withdrawKycRuleDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金KYC模板规则删除操作失败',
            'Withdraw KYC template rule deletion failed',
            $apiMessageEn
        );
    }

    public static function withdrawKycDocumentAddFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金KYC模板法律文档新增操作失败',
            'Withdraw KYC template legal document creation failed',
            $apiMessageEn
        );
    }

    public static function withdrawKycDocumentEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金KYC模板法律文档编辑操作失败',
            'Withdraw KYC template legal document update failed',
            $apiMessageEn
        );
    }

    public static function withdrawKycDocumentDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '出金KYC模板法律文档删除操作失败',
            'Withdraw KYC template legal document deletion failed',
            $apiMessageEn
        );
    }

    private static function withdrawKycTemplateLabelZh($name, $gatewayName = '') {
        $n = self::wktQ($name !== '' ? $name : '—');
        $gw = trim((string) $gatewayName);
        $gwPart = $gw !== '' ? "；网关：{$gw}" : '';
        return "出金KYC模板「{$n}」{$gwPart}";
    }

    private static function withdrawKycTemplateLabelEn($name, $gatewayName = '') {
        $n = self::wktQEn($name !== '' ? $name : '—');
        $gw = trim((string) $gatewayName);
        $gwPart = $gw !== '' ? "; gateway: {$gw}" : '';
        return "Withdraw KYC template \"{$n}\"{$gwPart}";
    }

    private static function wktQ($text) {
        return str_replace(['「', '」'], ["'", "'"], trim((string) $text));
    }

    private static function wktQEn($text) {
        return trim((string) $text);
    }

    // -------------------------------------------------------------------------
    // transaction_settings（subModuleKey: transaction_settings）
    // -------------------------------------------------------------------------

    public static function resolveGatewayLabelBySettingId($gatewaySettingId) {
        $id = (int) $gatewaySettingId;
        if ($id <= 0) {
            return '';
        }
        require_once __DIR__ . '/../../models/PaymentGatewaySetting.php';
        $row = (new PaymentGatewaySetting())->findById($id);
        if (!is_array($row)) {
            return '';
        }
        $name = trim((string) ($row['gatewayName'] ?? ''));
        return $name !== '' ? $name : trim((string) ($row['gatewayKey'] ?? ''));
    }

    public static function transactionSettingsGatewayToggle($gatewayName, $enabled) {
        $gw = trim((string) $gatewayName);
        if ($enabled) {
            return ["开启支付网关「{$gw}」", "Enabled payment gateway \"{$gw}\""];
        }
        return ["关闭支付网关「{$gw}」", "Disabled payment gateway \"{$gw}\""];
    }

    public static function transactionSettingsGatewayCapability($gatewayName) {
        $gw = trim((string) $gatewayName);
        return [
            "更新支付网关「{$gw}」：可用性与支持币种",
            "Updated payment gateway \"{$gw}\": availability and supported currencies",
        ];
    }

    public static function transactionSettingsGatewayEdit($gatewayName) {
        $gw = trim((string) $gatewayName);
        return [
            "更新支付网关「{$gw}」配置",
            "Updated payment gateway \"{$gw}\" configuration",
        ];
    }

    public static function transactionSettingsGatewayDelete($gatewayName) {
        $gw = trim((string) $gatewayName);
        return [
            "删除支付网关「{$gw}」",
            "Deleted payment gateway \"{$gw}\"",
        ];
    }

    public static function transactionSettingsGatewayLimits($gatewayName) {
        $gw = trim((string) $gatewayName);
        return [
            "更新支付网关「{$gw}」入出金限额",
            "Updated deposit/withdrawal limits for gateway \"{$gw}\"",
        ];
    }

    public static function transactionSettingsGatewayDepositFee($gatewayName) {
        $gw = trim((string) $gatewayName);
        return [
            "更新支付网关「{$gw}」入金手续费规则",
            "Updated deposit fee rules for gateway \"{$gw}\"",
        ];
    }

    public static function transactionSettingsGatewayWithdrawFee($gatewayName) {
        $gw = trim((string) $gatewayName);
        return [
            "更新支付网关「{$gw}」出金手续费规则",
            "Updated withdrawal fee rules for gateway \"{$gw}\"",
        ];
    }

    public static function transactionSettingsGatewayDisplayContent($gatewayName) {
        $gw = trim((string) $gatewayName);
        return [
            "更新支付网关「{$gw}」展示文案",
            "Updated display content for gateway \"{$gw}\"",
        ];
    }

    public static function transactionSettingsSupportQuestionAdd($gatewayName, $questionName, $scope) {
        $gw = trim((string) $gatewayName);
        $q = trim((string) $questionName);
        $scopeLabel = self::paymentSupportScopeZh($scope);
        $scopeEn = self::paymentSupportScopeEn($scope);
        return [
            "支付网关「{$gw}」：新增{$scopeLabel}支持问题「{$q}」",
            "Gateway \"{$gw}\": added {$scopeEn} support question \"{$q}\"",
        ];
    }

    public static function transactionSettingsSupportQuestionEdit($gatewayName, $questionName, $scope) {
        $gw = trim((string) $gatewayName);
        $q = trim((string) $questionName);
        $scopeLabel = self::paymentSupportScopeZh($scope);
        $scopeEn = self::paymentSupportScopeEn($scope);
        return [
            "支付网关「{$gw}」：更新{$scopeLabel}支持问题「{$q}」",
            "Gateway \"{$gw}\": updated {$scopeEn} support question \"{$q}\"",
        ];
    }

    public static function transactionSettingsSupportQuestionDelete($gatewayName, $questionName, $scope) {
        $gw = trim((string) $gatewayName);
        $q = trim((string) $questionName);
        $scopeLabel = self::paymentSupportScopeZh($scope);
        $scopeEn = self::paymentSupportScopeEn($scope);
        return [
            "支付网关「{$gw}」：删除{$scopeLabel}支持问题「{$q}」",
            "Gateway \"{$gw}\": deleted {$scopeEn} support question \"{$q}\"",
        ];
    }

    public static function transactionSettingsDisplayContentUpdate($scope) {
        $label = self::displayContentScopeZh($scope);
        $labelEn = self::displayContentScopeEn($scope);
        return [
            "更新{$label}展示内容",
            "Updated {$labelEn} display content",
        ];
    }

    public static function transactionSettingsNotificationUpdate($settingKey, $settingValue) {
        $label = self::notificationSettingLabelZh($settingKey);
        $labelEn = self::notificationSettingLabelEn($settingKey);
        $val = self::formatNotificationSettingValue($settingKey, $settingValue);
        $valEn = self::formatNotificationSettingValueEn($settingKey, $settingValue);
        return [
            "更新通知设置：{$label}为 {$val}",
            "Updated notification setting: {$labelEn} set to {$valEn}",
        ];
    }

    public static function transactionSettingsSecurityUpdate(array $linesZh, array $linesEn) {
        $zh = $linesZh ? implode('；', $linesZh) : '更新安全设置';
        $en = $linesEn ? implode('; ', $linesEn) : 'Updated security settings';
        return [
            "更新安全设置：{$zh}",
            "Updated security settings: {$en}",
        ];
    }

    public static function transactionSettingsAutoApprovalUpdate(array $linesZh, array $linesEn) {
        $zh = implode('；', $linesZh);
        $en = implode('; ', $linesEn);
        return [
            "更新自动审批规则：{$zh}",
            "Updated auto-approval rules: {$en}",
        ];
    }

    public static function transactionSettingsExchangeRateAdd($currencyCode) {
        $code = trim((string) $currencyCode);
        return [
            "新建汇率 {$code}",
            "Created exchange rate {$code}",
        ];
    }

    public static function transactionSettingsExchangeRateEdit($currencyCode, $extraZh = '', $extraEn = '') {
        $code = trim((string) $currencyCode);
        $extraZh = trim((string) $extraZh);
        $extraEn = trim((string) $extraEn);
        $zh = $extraZh !== '' ? "编辑汇率 {$code}：{$extraZh}" : "编辑汇率 {$code}";
        $en = $extraEn !== '' ? "Updated exchange rate {$code}: {$extraEn}" : "Updated exchange rate {$code}";
        return [$zh, $en];
    }

    public static function transactionSettingsExchangeRateToggle($currencyCode, $enabled) {
        $code = trim((string) $currencyCode);
        if ($enabled) {
            return ["启用汇率 {$code}", "Enabled exchange rate {$code}"];
        }
        return ["停用汇率 {$code}", "Disabled exchange rate {$code}"];
    }

    public static function transactionSettingsExchangeRateDelete($currencyCode) {
        $code = trim((string) $currencyCode);
        return [
            "删除汇率 {$code}",
            "Deleted exchange rate {$code}",
        ];
    }

    public static function autoApprovalRuleTypeZh($ruleTypeKey) {
        $map = [
            'deposit' => '入金',
            'withdrawal' => '出金',
            'internal_transfer' => '内部转账',
            'internalTransfer' => '内部转账',
        ];
        $key = strtolower(trim((string) $ruleTypeKey));
        return $map[$key] ?? $ruleTypeKey;
    }

    public static function autoApprovalRuleTypeEn($ruleTypeKey) {
        $map = [
            'deposit' => 'Deposit',
            'withdrawal' => 'Withdrawal',
            'internal_transfer' => 'Internal transfer',
            'internalTransfer' => 'Internal transfer',
        ];
        $key = strtolower(trim((string) $ruleTypeKey));
        return $map[$key] ?? $ruleTypeKey;
    }

    public static function securitySettingFieldLabels() {
        return [
            'salesManagerNotifications' => ['zh' => '销售经理通知', 'en' => 'sales manager notifications'],
            'withdrawalOtpRequired' => ['zh' => '出金 OTP 验证', 'en' => 'withdrawal OTP verification'],
            'otpValidityMinutes' => ['zh' => 'OTP 有效期（分钟）', 'en' => 'OTP validity (minutes)'],
            'requireVerifiedWalletOnly' => ['zh' => '仅允许已验证钱包出金', 'en' => 'verified wallet only for withdrawals'],
            'requireWithdrawalVerification' => ['zh' => '出金账户验证', 'en' => 'withdrawal account verification'],
            'verificationMaxFileSize' => ['zh' => '验证文件大小上限（MB）', 'en' => 'verification max file size (MB)'],
            'autoRejectUnverified' => ['zh' => '自动拒绝未验证出金', 'en' => 'auto-reject unverified withdrawals'],
        ];
    }

    public static function transactionSettingsGatewayEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '支付网关编辑操作失败',
            'Payment gateway update failed',
            $apiMessageEn
        );
    }

    public static function transactionSettingsGatewayDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '支付网关删除操作失败',
            'Payment gateway deletion failed',
            $apiMessageEn
        );
    }

    public static function transactionSettingsGatewayFeeFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '支付网关限额/手续费设置操作失败',
            'Gateway limit/fee setting update failed',
            $apiMessageEn
        );
    }

    public static function transactionSettingsGatewayDisplayFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '支付网关展示文案更新操作失败',
            'Gateway display content update failed',
            $apiMessageEn
        );
    }

    public static function transactionSettingsSupportQuestionAddFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '支付支持问题新增操作失败',
            'Payment support question creation failed',
            $apiMessageEn
        );
    }

    public static function transactionSettingsSupportQuestionEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '支付支持问题编辑操作失败',
            'Payment support question update failed',
            $apiMessageEn
        );
    }

    public static function transactionSettingsSupportQuestionDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '支付支持问题删除操作失败',
            'Payment support question deletion failed',
            $apiMessageEn
        );
    }

    public static function transactionSettingsDisplayContentFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '交易展示内容更新操作失败',
            'Transaction display content update failed',
            $apiMessageEn
        );
    }

    public static function transactionSettingsNotificationFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '通知设置更新操作失败',
            'Notification setting update failed',
            $apiMessageEn
        );
    }

    public static function transactionSettingsSecurityFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '安全设置更新操作失败',
            'Security settings update failed',
            $apiMessageEn
        );
    }

    public static function transactionSettingsAutoApprovalFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '自动审批规则更新操作失败',
            'Auto-approval rules update failed',
            $apiMessageEn
        );
    }

    public static function transactionSettingsExchangeRateAddFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '汇率新建操作失败',
            'Exchange rate creation failed',
            $apiMessageEn
        );
    }

    public static function transactionSettingsExchangeRateEditFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '汇率编辑操作失败',
            'Exchange rate update failed',
            $apiMessageEn
        );
    }

    public static function transactionSettingsExchangeRateDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '汇率删除操作失败',
            'Exchange rate deletion failed',
            $apiMessageEn
        );
    }

    private static function paymentSupportScopeZh($scope) {
        $s = strtolower(trim((string) $scope));
        if ($s === 'withdraw' || $s === 'withdrawal') {
            return '出金';
        }
        return '入金';
    }

    private static function paymentSupportScopeEn($scope) {
        $s = strtolower(trim((string) $scope));
        if ($s === 'withdraw' || $s === 'withdrawal') {
            return 'withdrawal';
        }
        return 'deposit';
    }

    private static function displayContentScopeZh($scope) {
        $map = [
            'deposit' => '入金页',
            'withdrawal' => '出金页',
            'internal_transfer' => '内部转账页',
        ];
        $key = strtolower(trim((string) $scope));
        return $map[$key] ?? $scope;
    }

    private static function displayContentScopeEn($scope) {
        $map = [
            'deposit' => 'deposit page',
            'withdrawal' => 'withdrawal page',
            'internal_transfer' => 'internal transfer page',
        ];
        $key = strtolower(trim((string) $scope));
        return $map[$key] ?? $scope;
    }

    private static function notificationSettingLabelZh($settingKey) {
        $map = [
            'clientEmailNotifications' => '客户邮件通知',
            'adminEmailNotifications' => '管理员邮件通知',
            'adminNotificationEmails' => '管理员通知邮箱',
            'largeDepositAlerts' => '大额入金提醒',
            'largeDepositThreshold' => '大额入金阈值',
            'largeWithdrawalAlerts' => '大额出金提醒',
            'largeWithdrawalThreshold' => '大额出金阈值',
        ];
        return $map[$settingKey] ?? '通知设置';
    }

    private static function notificationSettingLabelEn($settingKey) {
        $map = [
            'clientEmailNotifications' => 'client email notifications',
            'adminEmailNotifications' => 'admin email notifications',
            'adminNotificationEmails' => 'admin notification emails',
            'largeDepositAlerts' => 'large deposit alerts',
            'largeDepositThreshold' => 'large deposit threshold',
            'largeWithdrawalAlerts' => 'large withdrawal alerts',
            'largeWithdrawalThreshold' => 'large withdrawal threshold',
        ];
        return $map[$settingKey] ?? $settingKey;
    }

    private static function formatNotificationSettingValue($settingKey, $value) {
        if (is_bool($value)) {
            return $value ? '开启' : '关闭';
        }
        if ($value === 1 || $value === '1' || $value === 'true') {
            return '开启';
        }
        if ($value === 0 || $value === '0' || $value === 'false') {
            return '关闭';
        }
        return trim((string) $value);
    }

    private static function formatNotificationSettingValueEn($settingKey, $value) {
        if (is_bool($value)) {
            return $value ? 'enabled' : 'disabled';
        }
        if ($value === 1 || $value === '1' || $value === 'true') {
            return 'enabled';
        }
        if ($value === 0 || $value === '0' || $value === 'false') {
            return 'disabled';
        }
        return trim((string) $value);
    }
}
