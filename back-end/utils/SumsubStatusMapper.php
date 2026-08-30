<?php
/**
 * Sumsub Webhook 状态映射
 *
 * 把 Sumsub webhook payload 映射成本地 clientKycSubmissions.submissionStatus。
 * 本地状态机就 3 个有效落点：
 *   - pending：用户已经在 Sumsub 那边，等审核（onHold / awaitingUser / awaitingService / pending）
 *   - approved：审核通过
 *   - rejected：最终拒绝
 *   - resubmit_required：让用户补传（不算彻底失败，前端会再开 SDK）
 *
 * 其它事件（applicantCreated / applicantReset / applicantDeleted 等）返回 null，
 * 调用方写一条 activityLog 就行，不改 submissionStatus。
 */

class SumsubStatusMapper
{
    /**
     * 映射 webhook payload → 本地 submissionStatus
     *
     * @param array $payload 完整 webhook body
     * @return string|null   返回 null 表示不需要改 submissionStatus
     */
    public static function mapToSubmissionStatus(array $payload)
    {
        $type = $payload['type'] ?? null;
        $reviewResult = $payload['reviewResult'] ?? [];
        $reviewAnswer = $reviewResult['reviewAnswer'] ?? null;
        $rejectType = $reviewResult['reviewRejectType'] ?? null;

        if ($type === 'applicantReviewed') {
            if ($reviewAnswer === 'GREEN') {
                return 'approved';
            }
            if ($reviewAnswer === 'RED') {
                // RETRY 是 Sumsub 让客户补传 → 我们用 resubmit_required，前端会再次启动 SDK
                // FINAL 是最终拒绝 → rejected
                return strtoupper((string)$rejectType) === 'RETRY' ? 'resubmit_required' : 'rejected';
            }
            return null;
        }

        // Sumsub 处理中事件按语义分两档：
        //   applicantOnHold      → under_review（被 Sumsub 工作人员挂起复审）
        //   applicantPending / applicantAwaitingService / applicantAwaitingUser → pending
        if ($type === 'applicantOnHold') {
            return 'under_review';
        }
        if (in_array($type, [
            'applicantPending',
            'applicantAwaitingService',
            'applicantAwaitingUser',
        ], true)) {
            return 'pending';
        }

        // applicantCreated / applicantReset / applicantDeleted / applicantReopened ...
        // 不影响 status
        return null;
    }

    /**
     * 从 reviewResult 里抽出展示用的拒绝理由
     */
    public static function extractRejectionReason(array $payload)
    {
        $reviewResult = $payload['reviewResult'] ?? [];
        $comment = trim((string)($reviewResult['moderationComment'] ?? ''));
        if ($comment !== '') {
            return $comment;
        }
        $labels = $reviewResult['rejectLabels'] ?? [];
        if (is_array($labels) && !empty($labels)) {
            return implode(', ', $labels);
        }
        return null;
    }
}
