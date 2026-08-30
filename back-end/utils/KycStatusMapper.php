<?php
/**
 * KYC Status Mapper Utility
 *
 * 用于映射 clientUsers.kycStatus 和 kycStatusMessageTemplates.statusType
 * 解决两个表之间枚举值不一致的问题
 */

class KycStatusMapper {

    /**
     * clientUsers.kycStatus 的枚举值
     */
    const CLIENT_STATUS_NOT_STARTED = 'not_started';
    const CLIENT_STATUS_IN_PROGRESS = 'in_progress';
    const CLIENT_STATUS_INCOMPLETE = 'incomplete';
    const CLIENT_STATUS_SUBMITTED = 'submitted';
    const CLIENT_STATUS_UNDER_REVIEW = 'under_review';
    const CLIENT_STATUS_APPROVED = 'approved';
    const CLIENT_STATUS_REJECTED = 'rejected';

    /**
     * kycStatusMessageTemplates.statusType 的枚举值
     */
    const TEMPLATE_STATUS_DRAFT = 'draft';
    const TEMPLATE_STATUS_INCOMPLETE = 'incomplete';
    const TEMPLATE_STATUS_PENDING = 'pending';
    const TEMPLATE_STATUS_UNDER_REVIEW = 'under_review';
    const TEMPLATE_STATUS_APPROVED = 'approved';
    const TEMPLATE_STATUS_REJECTED = 'rejected';
    const TEMPLATE_STATUS_EXPIRED = 'expired';
    const TEMPLATE_STATUS_RESUBMIT_REQUIRED = 'resubmit_required';
    const TEMPLATE_STATUS_PENDING_DOCUMENTS = 'pending_documents';

    /**
     * clientUsers.kycStatus → kycStatusMessageTemplates.statusType 映射
     */
    private static $clientToTemplateMap = [
        self::CLIENT_STATUS_NOT_STARTED => self::TEMPLATE_STATUS_DRAFT,
        self::CLIENT_STATUS_IN_PROGRESS => self::TEMPLATE_STATUS_INCOMPLETE,
        self::CLIENT_STATUS_INCOMPLETE => self::TEMPLATE_STATUS_INCOMPLETE,
        self::CLIENT_STATUS_SUBMITTED => self::TEMPLATE_STATUS_PENDING,
        self::CLIENT_STATUS_UNDER_REVIEW => self::TEMPLATE_STATUS_UNDER_REVIEW,
        self::CLIENT_STATUS_APPROVED => self::TEMPLATE_STATUS_APPROVED,
        self::CLIENT_STATUS_REJECTED => self::TEMPLATE_STATUS_REJECTED,
    ];

    /**
     * kycStatusMessageTemplates.statusType → clientUsers.kycStatus 映射（反向）
     */
    private static $templateToClientMap = [
        self::TEMPLATE_STATUS_DRAFT => self::CLIENT_STATUS_NOT_STARTED,
        self::TEMPLATE_STATUS_INCOMPLETE => self::CLIENT_STATUS_IN_PROGRESS,
        self::TEMPLATE_STATUS_PENDING => self::CLIENT_STATUS_SUBMITTED,
        self::TEMPLATE_STATUS_UNDER_REVIEW => self::CLIENT_STATUS_UNDER_REVIEW,
        self::TEMPLATE_STATUS_APPROVED => self::CLIENT_STATUS_APPROVED,
        self::TEMPLATE_STATUS_REJECTED => self::CLIENT_STATUS_REJECTED,
        self::TEMPLATE_STATUS_EXPIRED => self::CLIENT_STATUS_REJECTED,
        self::TEMPLATE_STATUS_RESUBMIT_REQUIRED => self::CLIENT_STATUS_INCOMPLETE,
        self::TEMPLATE_STATUS_PENDING_DOCUMENTS => self::CLIENT_STATUS_IN_PROGRESS,
    ];

    /**
     * 将 clientUsers.kycStatus 映射到 kycStatusMessageTemplates.statusType
     *
     * @param string $clientStatus - clientUsers.kycStatus 的值
     * @return string - 对应的 kycStatusMessageTemplates.statusType 的值
     */
    public static function clientStatusToTemplateStatus($clientStatus) {
        if (!$clientStatus) {
            return self::TEMPLATE_STATUS_DRAFT;
        }

        return self::$clientToTemplateMap[$clientStatus] ?? self::TEMPLATE_STATUS_INCOMPLETE;
    }

    /**
     * 将 kycStatusMessageTemplates.statusType 映射到 clientUsers.kycStatus
     *
     * @param string $templateStatus - kycStatusMessageTemplates.statusType 的值
     * @return string - 对应的 clientUsers.kycStatus 的值
     */
    public static function templateStatusToClientStatus($templateStatus) {
        if (!$templateStatus) {
            return self::CLIENT_STATUS_NOT_STARTED;
        }

        return self::$templateToClientMap[$templateStatus] ?? self::CLIENT_STATUS_IN_PROGRESS;
    }

    /**
     * 获取状态映射对照表（用于文档和调试）
     *
     * @return array - 映射关系数组
     */
    public static function getMappingTable() {
        return [
            'client_to_template' => self::$clientToTemplateMap,
            'template_to_client' => self::$templateToClientMap
        ];
    }

    /**
     * 获取所有 clientUsers.kycStatus 的有效值
     *
     * @return array
     */
    public static function getValidClientStatuses() {
        return [
            self::CLIENT_STATUS_NOT_STARTED,
            self::CLIENT_STATUS_IN_PROGRESS,
            self::CLIENT_STATUS_INCOMPLETE,
            self::CLIENT_STATUS_SUBMITTED,
            self::CLIENT_STATUS_UNDER_REVIEW,
            self::CLIENT_STATUS_APPROVED,
            self::CLIENT_STATUS_REJECTED,
        ];
    }

    /**
     * 获取所有 kycStatusMessageTemplates.statusType 的有效值
     *
     * @return array
     */
    public static function getValidTemplateStatuses() {
        return [
            self::TEMPLATE_STATUS_DRAFT,
            self::TEMPLATE_STATUS_INCOMPLETE,
            self::TEMPLATE_STATUS_PENDING,
            self::TEMPLATE_STATUS_UNDER_REVIEW,
            self::TEMPLATE_STATUS_APPROVED,
            self::TEMPLATE_STATUS_REJECTED,
            self::TEMPLATE_STATUS_EXPIRED,
            self::TEMPLATE_STATUS_RESUBMIT_REQUIRED,
            self::TEMPLATE_STATUS_PENDING_DOCUMENTS,
        ];
    }

    /**
     * 验证是否是有效的 clientUsers.kycStatus
     *
     * @param string $status
     * @return bool
     */
    public static function isValidClientStatus($status) {
        return in_array($status, self::getValidClientStatuses());
    }

    /**
     * 验证是否是有效的 kycStatusMessageTemplates.statusType
     *
     * @param string $status
     * @return bool
     */
    public static function isValidTemplateStatus($status) {
        return in_array($status, self::getValidTemplateStatuses());
    }

    /**
     * 获取状态的用户友好显示名称
     *
     * @param string $clientStatus - clientUsers.kycStatus 的值
     * @return string
     */
    public static function getStatusDisplayName($clientStatus) {
        $displayNames = [
            self::CLIENT_STATUS_NOT_STARTED => 'Not Started',
            self::CLIENT_STATUS_IN_PROGRESS => 'In Progress',
            self::CLIENT_STATUS_INCOMPLETE => 'Incomplete',
            self::CLIENT_STATUS_SUBMITTED => 'Submitted',
            self::CLIENT_STATUS_UNDER_REVIEW => 'Under Review',
            self::CLIENT_STATUS_APPROVED => 'Approved',
            self::CLIENT_STATUS_REJECTED => 'Rejected',
        ];

        return $displayNames[$clientStatus] ?? ucwords(str_replace('_', ' ', $clientStatus));
    }

    /**
     * 获取状态对应的颜色类（用于前端显示）
     *
     * @param string $clientStatus - clientUsers.kycStatus 的值
     * @return string - CSS类名
     */
    public static function getStatusColorClass($clientStatus) {
        $colorClasses = [
            self::CLIENT_STATUS_NOT_STARTED => 'gray',
            self::CLIENT_STATUS_IN_PROGRESS => 'blue',
            self::CLIENT_STATUS_INCOMPLETE => 'yellow',
            self::CLIENT_STATUS_SUBMITTED => 'orange',
            self::CLIENT_STATUS_UNDER_REVIEW => 'orange',
            self::CLIENT_STATUS_APPROVED => 'green',
            self::CLIENT_STATUS_REJECTED => 'red',
        ];

        return $colorClasses[$clientStatus] ?? 'gray';
    }
}
