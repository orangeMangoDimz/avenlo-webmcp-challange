<?php
/**
 * Client 模块（log_client）操作日志 — 中英详情文案
 * 子模块：leads、clients_list、客户详情共享写操作等
 */

require_once __DIR__ . '/OperationLogTextHelpers.php';

class ClientOperationLogTexts {
    public static function formatNotificationChannelsZh($sendSystem, $sendEmail) {
        $parts = [];
        if ($sendSystem) {
            $parts[] = '系统通知';
        }
        if ($sendEmail) {
            $parts[] = '邮件';
        }
        return $parts ? implode('、', $parts) : '—';
    }

    public static function formatNotificationChannelsEn($sendSystem, $sendEmail) {
        $parts = [];
        if ($sendSystem) {
            $parts[] = 'System';
        }
        if ($sendEmail) {
            $parts[] = 'Email';
        }
        return $parts ? implode(', ', $parts) : '—';
    }

    public static function formatScheduleDescZh($scheduleType, $scheduledAt = null) {
        if ($scheduleType === 'scheduled' && $scheduledAt) {
            return '定时发送 ' . $scheduledAt;
        }
        return '即时发送';
    }

    public static function formatScheduleDescEn($scheduleType, $scheduledAt = null) {
        if ($scheduleType === 'scheduled' && $scheduledAt) {
            return 'scheduled for ' . $scheduledAt;
        }
        return 'immediate';
    }

    public static function clientCreated(array $clientRow, $clientId) {
        $name = OperationLogTextHelpers::formatClientDisplayName($clientRow);
        $email = trim((string) ($clientRow['email'] ?? ''));
        $id = (int) $clientId;
        return [
            "新增客户：{$name}（{$email}，ID：{$id}）",
            "Added client: {$name} ({$email}, ID: {$id})",
        ];
    }

    public static function assignBulk($subModuleKey, array $clientIds, $managerName, $notes = '') {
        $count = count($clientIds);
        $ids = OperationLogTextHelpers::formatClientIdList($clientIds);
        $labelZh = OperationLogTextHelpers::entityLabelZh($subModuleKey);
        $labelEn = OperationLogTextHelpers::entityLabelEn($subModuleKey);
        $noteZh = OperationLogTextHelpers::notesSuffixZh($notes);
        $noteEn = OperationLogTextHelpers::notesSuffixEn($notes);
        return [
            "批量分配 {$count} 条{$labelZh}给销售：{$managerName}；涉及客户 ID：{$ids}{$noteZh}",
            "Bulk assigned {$count} {$labelEn}(s) to {$managerName}; client IDs: {$ids}{$noteEn}",
        ];
    }

    public static function assignSingle($subModuleKey, $clientId, $managerName, $notes = '') {
        $id = (int) $clientId;
        $labelZh = OperationLogTextHelpers::entityLabelZh($subModuleKey);
        $labelEn = OperationLogTextHelpers::entityLabelEn($subModuleKey);
        $noteZh = OperationLogTextHelpers::notesSuffixZh($notes);
        $noteEn = OperationLogTextHelpers::notesSuffixEn($notes);
        return [
            "将{$labelZh}分配给销售：{$managerName}；客户 ID：{$id}{$noteZh}",
            "Assigned {$labelEn} to {$managerName}; client ID: {$id}{$noteEn}",
        ];
    }

    public static function tagBulk($subModuleKey, array $clientIds, $tagName) {
        $count = count($clientIds);
        $ids = OperationLogTextHelpers::formatClientIdList($clientIds);
        $labelZh = OperationLogTextHelpers::entityLabelZh($subModuleKey);
        $labelEn = OperationLogTextHelpers::entityLabelEn($subModuleKey);
        $tagName = trim((string) $tagName);
        if ($count <= 1 && $count > 0) {
            $id = (int) $clientIds[0];
            return [
                "为客户添加标签「{$tagName}」；客户 ID：{$id}",
                "Added tag \"{$tagName}\"; client ID: {$id}",
            ];
        }
        return [
            "为 {$count} 条{$labelZh}添加标签「{$tagName}」；涉及客户 ID：{$ids}",
            "Added tag \"{$tagName}\" to {$count} {$labelEn}(s); client IDs: {$ids}",
        ];
    }

    public static function tagRemove($clientId, $tagName) {
        $id = (int) $clientId;
        $tagName = trim((string) $tagName);
        return [
            "移除标签「{$tagName}」；客户 ID：{$id}",
            "Removed tag \"{$tagName}\"; client ID: {$id}",
        ];
    }

    public static function profileUpdate($clientId, $displayName, array $changes) {
        $id = (int) $clientId;
        $displayName = trim((string) $displayName);
        $summaryZh = OperationLogTextHelpers::formatChangeSummaryZh($changes);
        $summaryEn = OperationLogTextHelpers::formatChangeSummaryEn($changes);
        return [
            "更新客户资料：{$displayName}；变更：{$summaryZh}；客户 ID：{$id}",
            "Updated client profile: {$displayName}; changes: {$summaryEn}; client ID: {$id}",
        ];
    }

    public static function passwordResetEmail($clientId, $email, $emailSent = true) {
        $id = (int) $clientId;
        $email = trim((string) $email);
        $detailZh = "向 {$email} 发送密码重置邮件；客户 ID：{$id}";
        $detailEn = "Sent password reset email to {$email}; client ID: {$id}";
        if (!$emailSent) {
            $detailZh .= '（邮件投递失败）';
            $detailEn .= ' (email delivery failed)';
        }
        return [$detailZh, $detailEn];
    }

    public static function viewAsClient($clientId, $displayName) {
        $id = (int) $clientId;
        $displayName = trim((string) $displayName);
        return [
            "以客户端身份预览：{$displayName}；客户 ID：{$id}",
            "View as client: {$displayName}; client ID: {$id}",
        ];
    }

    public static function leadsExport($count, $format) {
        $fmt = strtoupper((string) $format);
        $count = (int) $count;
        return [
            "导出 {$count} 条 Lead（{$fmt}）",
            "Exported {$count} lead(s) ({$fmt})",
        ];
    }

    public static function notificationSingle(
        $clientId,
        $displayName,
        $channelsZh,
        $channelsEn,
        $scheduleZh,
        $scheduleEn
    ) {
        $id = (int) $clientId;
        $displayName = trim((string) $displayName);
        return [
            "向客户 {$displayName} 发送通知，渠道：{$channelsZh}，{$scheduleZh}；客户 ID：{$id}",
            "Sent notification to {$displayName}, channels: {$channelsEn}, {$scheduleEn}; client ID: {$id}",
        ];
    }

    public static function notificationBulk(
        array $clientIds,
        $channelsZh,
        $channelsEn,
        $scheduleZh,
        $scheduleEn,
        $successCount,
        $failCount
    ) {
        $count = count($clientIds);
        $ids = OperationLogTextHelpers::formatClientIdList($clientIds);
        return [
            "批量发送通知：{$count} 人，渠道：{$channelsZh}，{$scheduleZh}；成功 {$successCount}、失败 {$failCount}；涉及客户 ID：{$ids}",
            "Bulk notification to {$count} recipient(s), channels: {$channelsEn}, {$scheduleEn}; success {$successCount}, failed {$failCount}; client IDs: {$ids}",
        ];
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

    public static function tradingAccountCreate($clientId, $platform, $loginOrNickname) {
        $id = (int) $clientId;
        $platform = trim((string) $platform);
        $login = trim((string) $loginOrNickname);
        return [
            "创建交易账户：{$platform}，账号 {$login}；客户 ID：{$id}",
            "Created trading account: {$platform}, login {$login}; client ID: {$id}",
        ];
    }

    public static function profileUpdateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新客户资料失败',
            'Client profile update failed',
            $apiMessageEn
        );
    }

    public static function clientCreatedFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '新增客户失败',
            'Add client failed',
            $apiMessageEn
        );
    }

    public static function assignBulkFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '批量分配销售失败',
            'Bulk assign to sales failed',
            $apiMessageEn
        );
    }

    public static function assignSingleFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '分配销售失败',
            'Assign to sales failed',
            $apiMessageEn
        );
    }

    public static function tagBulkFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '添加标签失败',
            'Add tag failed',
            $apiMessageEn
        );
    }

    public static function tagRemoveFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '移除标签失败',
            'Remove tag failed',
            $apiMessageEn
        );
    }

    public static function searchTagCreateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '新建搜索标签失败',
            'Create search tag failed',
            $apiMessageEn
        );
    }

    public static function searchTagDeleteFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '删除搜索标签失败',
            'Delete search tag failed',
            $apiMessageEn
        );
    }

    public static function notificationSingleFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '发送通知失败',
            'Send notification failed',
            $apiMessageEn
        );
    }

    public static function notificationBulkFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '批量发送通知失败',
            'Bulk send notification failed',
            $apiMessageEn
        );
    }

    public static function passwordResetEmailFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '发送密码重置邮件失败',
            'Send password reset email failed',
            $apiMessageEn
        );
    }

    public static function viewAsClientFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '以客户端身份预览失败',
            'View as client failed',
            $apiMessageEn
        );
    }

    public static function tradingAccountCreateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '创建交易账户失败',
            'Create trading account failed',
            $apiMessageEn
        );
    }

    public static function ibPartnerProfileUpdateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新 IB 资料失败',
            'Update IB profile failed',
            $apiMessageEn
        );
    }

    public static function ibReferralSuffixUpdateFailure($apiMessageEn = '') {
        return OperationLogTextHelpers::formatOperationFailure(
            '更新 IB 推荐链接后缀失败',
            'Update IB referral suffix failed',
            $apiMessageEn
        );
    }
}
