<?php
/**
 * IB 佣金订单列表页 — 操作日志写入辅助（log_ib / ib_commission）
 *
 * 仅当请求显式携带 logSubModuleKey=ib_commission 时写入。
 */

require_once __DIR__ . '/../OperationLogPages.php';
require_once __DIR__ . '/../AdminOperationLogWriter.php';
require_once __DIR__ . '/../OperationLogTexts/IbOperationLogTexts.php';
require_once __DIR__ . '/../OperationLogTexts/OperationLogTextHelpers.php';
require_once __DIR__ . '/../../models/ClientUser.php';
require_once __DIR__ . '/../../models/IbPartner.php';
require_once __DIR__ . '/../../utils/Database.php';

class IbCommissionOperationLog {
    public static function shouldLog($input = null) {
        if (!is_array($input)) {
            return false;
        }
        $key = trim((string) ($input['logSubModuleKey'] ?? $input['operationLogSubModule'] ?? ''));
        return $key === OperationLogPages::subModuleKeyByAlias('page_ib_commission_order');
    }

    public static function subModule() {
        return OperationLogPages::subModuleKeyByAlias('page_ib_commission_order');
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
     * @return array|null 含佣金订单、IB、客户、规则信息
     */
    public static function loadOrderContext($orderId) {
        $id = (int) $orderId;
        if ($id <= 0) {
            return null;
        }
        $db = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT co.id, co.ibPartnerId, co.commission, co.currency, co.status, co.ruleId, co.ruleType,
                    ib.userId, ib.companyName, ib.ibCode, ib.ibType,
                    cu.firstName, cu.lastName, cu.email,
                    r.ruleName
             FROM ib_commission_order co
             INNER JOIN ibPartners ib ON ib.id = co.ibPartnerId
             LEFT JOIN clientUsers cu ON cu.id = ib.userId
             LEFT JOIN ibCommissionRules r ON r.id = co.ruleId
             WHERE co.id = :id
             LIMIT 1",
            ['id' => $id]
        );
        return is_array($row) ? $row : null;
    }

    public static function resolveClientIdFromContext($context) {
        if (!is_array($context)) {
            return null;
        }
        $clientId = (int) ($context['userId'] ?? 0);
        return $clientId > 0 ? $clientId : null;
    }

    public static function resolveIbDisplay(array $context) {
        $ibPartnerId = (int) ($context['ibPartnerId'] ?? 0);
        $ibRow = [
            'companyName' => $context['companyName'] ?? '',
            'ibCode' => $context['ibCode'] ?? '',
            'ibType' => $context['ibType'] ?? '',
            'contactEmail' => $context['email'] ?? '',
        ];
        $display = AdminOperationLogWriter::formatIbPartnerDisplayName($ibRow);
        if ($display === '' && $ibPartnerId > 0) {
            $display = 'IB#' . $ibPartnerId;
        }
        return [$display, $ibPartnerId];
    }

    public static function formatCommissionAmount(array $context) {
        $amount = (float) ($context['commission'] ?? 0);
        $currency = trim((string) ($context['currency'] ?? ''));
        $formatted = rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
        if ($formatted === '') {
            $formatted = '0';
        }
        return $currency !== '' ? "{$formatted} {$currency}" : $formatted;
    }

    /**
     * CO1 成功
     */
    public static function logApproveSuccess(array $input, array $context) {
        if (!self::shouldLog($input)) {
            return;
        }
        $clientId = self::resolveClientIdFromContext($context);
        list($ibDisplay, $ibPartnerId) = self::resolveIbDisplay($context);
        list($detailZh, $detailEn) = IbOperationLogTexts::commissionOrderApproveSuccess(
            (int) ($context['id'] ?? 0),
            $ibDisplay,
            $ibPartnerId,
            trim((string) ($context['ruleName'] ?? '')),
            self::formatCommissionAmount($context)
        );
        self::log($input, 'approve', $clientId, $detailZh, $detailEn);
    }

    /**
     * CO2 成功
     */
    public static function logCompleteSuccess(array $input, array $context) {
        if (!self::shouldLog($input)) {
            return;
        }
        $clientId = self::resolveClientIdFromContext($context);
        list($ibDisplay, $ibPartnerId) = self::resolveIbDisplay($context);
        list($detailZh, $detailEn) = IbOperationLogTexts::commissionOrderCompleteSuccess(
            (int) ($context['id'] ?? 0),
            $ibDisplay,
            $ibPartnerId,
            trim((string) ($context['ruleName'] ?? '')),
            self::formatCommissionAmount($context)
        );
        self::log($input, 'approve', $clientId, $detailZh, $detailEn);
    }

    /**
     * CO3 成功
     */
    public static function logCancelSuccess(array $input, array $context) {
        if (!self::shouldLog($input)) {
            return;
        }
        $clientId = self::resolveClientIdFromContext($context);
        list($ibDisplay, $ibPartnerId) = self::resolveIbDisplay($context);
        list($detailZh, $detailEn) = IbOperationLogTexts::commissionOrderCancelSuccess(
            (int) ($context['id'] ?? 0),
            $ibDisplay,
            $ibPartnerId,
            trim((string) ($context['ruleName'] ?? '')),
            self::formatCommissionAmount($context),
            trim((string) ($context['status'] ?? ''))
        );
        self::log($input, 'reject', $clientId, $detailZh, $detailEn);
    }
}
