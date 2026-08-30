<?php
/**
 * 后台操作日志 — 读取 config/operation_log_pages.php
 *
 * @sync admin_frontend/src/config/operationLogPages.js
 */

require_once __DIR__ . '/AdminOperationLogWriter.php';

class OperationLogPages {
    private static $config;

    private static function config() {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config/operation_log_pages.php';
        }
        return self::$config;
    }

    /**
     * @return array<string,string>
     */
    public static function pageKeys() {
        return self::config()['pageKeys'] ?? [];
    }

    /**
     * 按别名读取 pageKey（如 pageKey('page_leads') → 'leads'）
     */
    public static function pageKey($alias) {
        $alias = trim((string) $alias);
        $map = self::pageKeys();
        return isset($map[$alias]) ? (string) $map[$alias] : '';
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function pages() {
        return self::config()['pages'] ?? [];
    }

    /**
     * @return array<string,string>
     */
    public static function detailSourceMap() {
        return self::config()['detailSourceMap'] ?? [];
    }

    public static function page($pageKey) {
        $pageKey = trim((string) $pageKey);
        $pages = self::pages();
        return $pages[$pageKey] ?? null;
    }

    public static function modelKey($pageKey) {
        $page = self::page($pageKey);
        return trim((string) ($page['modelKey'] ?? ''));
    }

    public static function modelKeyByAlias($alias) {
        return self::modelKey(self::pageKey($alias));
    }

    public static function subModuleKey($pageKey) {
        $page = self::page($pageKey);
        return trim((string) ($page['subModuleKey'] ?? ''));
    }

    public static function subModuleKeyByAlias($alias) {
        return self::subModuleKey(self::pageKey($alias));
    }

    public static function pageKeyFromDetailSource($source) {
        $source = trim((string) $source);
        $map = self::detailSourceMap();
        return isset($map[$source]) ? (string) $map[$source] : '';
    }

    public static function subModuleKeyFromDetailSource($source) {
        $pageKey = self::pageKeyFromDetailSource($source);
        if ($pageKey === '') {
            return self::subModuleKeyByAlias('page_leads');
        }
        return self::subModuleKey($pageKey);
    }

    public static function resolveLogClient($body, $defaultSubModule) {
        return AdminOperationLogWriter::resolveSubModuleKey($body, 'log_client', $defaultSubModule);
    }

    public static function resolveLogClientFromRequest($defaultSubModule) {
        $data = [];
        if (!empty($_GET['logSubModuleKey'])) {
            $data['logSubModuleKey'] = $_GET['logSubModuleKey'];
        } elseif (!empty($_GET['operationLogSubModule'])) {
            $data['operationLogSubModule'] = $_GET['operationLogSubModule'];
        }
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = array_merge($data, $decoded);
            }
        }
        return self::resolveLogClient($data, $defaultSubModule);
    }

    public static function resolveLogKyc($body) {
        $default = self::subModuleKeyByAlias('page_kyc_list');
        return AdminOperationLogWriter::resolveSubModuleKey($body, 'log_kyc', $default);
    }

    public static function resolveLogKycTemplates($body) {
        $default = self::subModuleKeyByAlias('page_kyc_templates');
        return AdminOperationLogWriter::resolveSubModuleKey($body, 'log_kyc', $default);
    }

    public static function resolveLogKycTemplatesFromRequest($body = null) {
        $data = is_array($body) ? $body : [];
        if (!empty($_GET['logSubModuleKey'])) {
            $data['logSubModuleKey'] = $_GET['logSubModuleKey'];
        } elseif (!empty($_GET['operationLogSubModule'])) {
            $data['operationLogSubModule'] = $_GET['operationLogSubModule'];
        }
        if (empty($data)) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }
        return self::resolveLogKycTemplates($data);
    }

    public static function resolveLogKycSettings($body) {
        $default = self::subModuleKeyByAlias('page_kyc_settings');
        return AdminOperationLogWriter::resolveSubModuleKey($body, 'log_kyc', $default);
    }

    public static function resolveLogKycSettingsFromRequest($body = null) {
        $data = is_array($body) ? $body : [];
        if (!empty($_GET['logSubModuleKey'])) {
            $data['logSubModuleKey'] = $_GET['logSubModuleKey'];
        } elseif (!empty($_GET['operationLogSubModule'])) {
            $data['operationLogSubModule'] = $_GET['operationLogSubModule'];
        }
        if (empty($data)) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }
        return self::resolveLogKycSettings($data);
    }

    public static function resolveLogTransaction($body, $defaultSubModule) {
        return AdminOperationLogWriter::resolveSubModuleKey($body, 'log_transaction', $defaultSubModule);
    }

    public static function resolveLogDeposits($body) {
        $default = self::subModuleKeyByAlias('page_deposits');
        return self::resolveLogTransaction($body, $default);
    }

    public static function resolveLogDepositsFromRequest($body = null) {
        $data = is_array($body) ? $body : [];
        if (!empty($_GET['logSubModuleKey'])) {
            $data['logSubModuleKey'] = $_GET['logSubModuleKey'];
        } elseif (!empty($_GET['operationLogSubModule'])) {
            $data['operationLogSubModule'] = $_GET['operationLogSubModule'];
        }
        if (empty($data)) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }
        return self::resolveLogDeposits($data);
    }

    public static function resolveLogWithdrawals($body) {
        $default = self::subModuleKeyByAlias('page_withdrawals');
        return self::resolveLogTransaction($body, $default);
    }

    public static function resolveLogWithdrawalsFromRequest($body = null) {
        $data = is_array($body) ? $body : [];
        if (!empty($_GET['logSubModuleKey'])) {
            $data['logSubModuleKey'] = $_GET['logSubModuleKey'];
        } elseif (!empty($_GET['operationLogSubModule'])) {
            $data['operationLogSubModule'] = $_GET['operationLogSubModule'];
        }
        if (empty($data)) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }
        return self::resolveLogWithdrawals($data);
    }

    public static function resolveLogInternalTransfers($body) {
        $default = self::subModuleKeyByAlias('page_internaltransfers');
        return self::resolveLogTransaction($body, $default);
    }

    public static function resolveLogInternalTransfersFromRequest($body = null) {
        $data = is_array($body) ? $body : [];
        if (!empty($_GET['logSubModuleKey'])) {
            $data['logSubModuleKey'] = $_GET['logSubModuleKey'];
        } elseif (!empty($_GET['operationLogSubModule'])) {
            $data['operationLogSubModule'] = $_GET['operationLogSubModule'];
        }
        if (empty($data)) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }
        return self::resolveLogInternalTransfers($data);
    }

    /**
     * 交易搜索标签：优先 body.logSubModuleKey，否则按 transactionType 回落
     */
    public static function resolveLogTransactionSearchTag($body) {
        $data = is_array($body) ? $body : [];
        $explicit = trim((string) ($data['logSubModuleKey'] ?? $data['operationLogSubModule'] ?? ''));
        if ($explicit !== '') {
            $depositDefault = self::subModuleKeyByAlias('page_deposits');
            return self::resolveLogTransaction($data, $depositDefault);
        }
        $type = strtolower(trim((string) ($data['transactionType'] ?? '')));
        if ($type === 'withdrawal') {
            return self::resolveLogWithdrawals($data);
        }
        if ($type === 'internal_transfer') {
            return self::resolveLogInternalTransfers($data);
        }
        return self::resolveLogDeposits($data);
    }

    public static function isWithdrawalsSubModule($subModuleKey) {
        return trim((string) $subModuleKey) === self::subModuleKeyByAlias('page_withdrawals');
    }

    public static function isDepositsSubModule($subModuleKey) {
        return trim((string) $subModuleKey) === self::subModuleKeyByAlias('page_deposits');
    }

    public static function isInternalTransfersSubModule($subModuleKey) {
        return trim((string) $subModuleKey) === self::subModuleKeyByAlias('page_internaltransfers');
    }

    public static function resolveLogAddressVerification($body) {
        $default = self::subModuleKeyByAlias('page_addressverification');
        return self::resolveLogTransaction($body, $default);
    }

    public static function resolveLogAddressVerificationFromRequest($body = null) {
        $data = is_array($body) ? $body : [];
        if (!empty($_GET['logSubModuleKey'])) {
            $data['logSubModuleKey'] = $_GET['logSubModuleKey'];
        } elseif (!empty($_GET['operationLogSubModule'])) {
            $data['operationLogSubModule'] = $_GET['operationLogSubModule'];
        }
        if (empty($data)) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }
        return self::resolveLogAddressVerification($data);
    }

    public static function isAddressVerificationSubModule($subModuleKey) {
        return trim((string) $subModuleKey) === self::subModuleKeyByAlias('page_addressverification');
    }

    public static function resolveLogWithdrawKycTemplates($body) {
        $default = self::subModuleKeyByAlias('page_withdrawkyctemplates');
        return self::resolveLogTransaction($body, $default);
    }

    public static function resolveLogWithdrawKycTemplatesFromRequest($body = null) {
        $data = is_array($body) ? $body : [];
        if (!empty($_GET['logSubModuleKey'])) {
            $data['logSubModuleKey'] = $_GET['logSubModuleKey'];
        } elseif (!empty($_GET['operationLogSubModule'])) {
            $data['operationLogSubModule'] = $_GET['operationLogSubModule'];
        }
        if (empty($data)) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }
        return self::resolveLogWithdrawKycTemplates($data);
    }

    public static function isWithdrawKycTemplatesSubModule($subModuleKey) {
        return trim((string) $subModuleKey) === self::subModuleKeyByAlias('page_withdrawkyctemplates');
    }

    public static function resolveLogTransactionSettings($body) {
        $default = self::subModuleKeyByAlias('page_transactionsettings');
        return self::resolveLogTransaction($body, $default);
    }

    public static function resolveLogTransactionSettingsFromRequest($body = null) {
        $data = is_array($body) ? $body : [];
        if (!empty($_GET['logSubModuleKey'])) {
            $data['logSubModuleKey'] = $_GET['logSubModuleKey'];
        } elseif (!empty($_GET['operationLogSubModule'])) {
            $data['operationLogSubModule'] = $_GET['operationLogSubModule'];
        }
        if (empty($data)) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }
        return self::resolveLogTransactionSettings($data);
    }

    public static function resolveLogTransactionSearchTagFromRequest($body = null) {
        $data = is_array($body) ? $body : [];
        if (!empty($_GET['logSubModuleKey'])) {
            $data['logSubModuleKey'] = $_GET['logSubModuleKey'];
        } elseif (!empty($_GET['operationLogSubModule'])) {
            $data['operationLogSubModule'] = $_GET['operationLogSubModule'];
        }
        if (empty($data)) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }
        return self::resolveLogTransactionSearchTag($data);
    }

    public static function resolveLogSales($body) {
        $default = self::subModuleKeyByAlias('page_sales_list');
        return AdminOperationLogWriter::resolveSubModuleKey($body, 'log_sales', $default);
    }

    /**
     * IB 初审：仅当请求显式携带 ib_initial 时返回子模块 key（邀请等多处共用接口，避免误记）
     */
    public static function resolveLogIbInitial($body) {
        if (!is_array($body)) {
            return '';
        }
        $expected = self::subModuleKeyByAlias('page_ib_initial_review');
        $key = trim((string) ($body['logSubModuleKey'] ?? $body['operationLogSubModule'] ?? ''));
        if ($key === $expected) {
            return $expected;
        }
        return '';
    }

    public static function isIbInitialSubModule($subModuleKey) {
        return trim((string) $subModuleKey) === self::subModuleKeyByAlias('page_ib_initial_review');
    }

    /**
     * IB 风控：仅当请求显式携带 ib_risk 时返回子模块 key（reject 与终审列表共用）
     */
    public static function resolveLogIbRisk($body) {
        if (!is_array($body)) {
            return '';
        }
        $expected = self::subModuleKeyByAlias('page_ib_risk_review');
        $key = trim((string) ($body['logSubModuleKey'] ?? $body['operationLogSubModule'] ?? ''));
        if ($key === $expected) {
            return $expected;
        }
        return '';
    }

    public static function isIbRiskSubModule($subModuleKey) {
        return trim((string) $subModuleKey) === self::subModuleKeyByAlias('page_ib_risk_review');
    }

    /**
     * IB 终审：仅当请求显式携带 ib_final 时返回子模块 key（reject 与风控列表共用）
     */
    public static function resolveLogIbFinal($body) {
        if (!is_array($body)) {
            return '';
        }
        $expected = self::subModuleKeyByAlias('page_ib_final_review');
        $key = trim((string) ($body['logSubModuleKey'] ?? $body['operationLogSubModule'] ?? ''));
        if ($key === $expected) {
            return $expected;
        }
        return '';
    }

    public static function isIbFinalSubModule($subModuleKey) {
        return trim((string) $subModuleKey) === self::subModuleKeyByAlias('page_ib_final_review');
    }

    /**
     * IB 佣金订单：仅当请求显式携带 ib_commission 时返回子模块 key
     */
    public static function resolveLogIbCommission($body) {
        if (!is_array($body)) {
            return '';
        }
        $expected = self::subModuleKeyByAlias('page_ib_commission_order');
        $key = trim((string) ($body['logSubModuleKey'] ?? $body['operationLogSubModule'] ?? ''));
        if ($key === $expected) {
            return $expected;
        }
        return '';
    }

    public static function isIbCommissionSubModule($subModuleKey) {
        return trim((string) $subModuleKey) === self::subModuleKeyByAlias('page_ib_commission_order');
    }

    /**
     * IB 设置：仅当请求显式携带 ib_settings 时返回子模块 key
     */
    public static function resolveLogIbSettings($body) {
        if (!is_array($body)) {
            return '';
        }
        $expected = self::subModuleKeyByAlias('page_ib_settings');
        $key = trim((string) ($body['logSubModuleKey'] ?? $body['operationLogSubModule'] ?? ''));
        if ($key === $expected) {
            return $expected;
        }
        return '';
    }

    public static function resolveLogIbSettingsFromRequest($body = null) {
        $data = is_array($body) ? $body : [];
        if (!empty($_GET['logSubModuleKey'])) {
            $data['logSubModuleKey'] = $_GET['logSubModuleKey'];
        } elseif (!empty($_GET['operationLogSubModule'])) {
            $data['operationLogSubModule'] = $_GET['operationLogSubModule'];
        }
        if (empty($data)) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }
        return $data;
    }

    public static function isIbSettingsSubModule($subModuleKey) {
        return trim((string) $subModuleKey) === self::subModuleKeyByAlias('page_ib_settings');
    }

    /**
     * 请求是否显式携带某页面的 logSubModuleKey（与 operation_log_pages 登记一致时才记日志）
     */
    public static function shouldLogForPageAlias($body, $pageAlias) {
        if (!is_array($body)) {
            return false;
        }
        $expected = self::subModuleKeyByAlias($pageAlias);
        if ($expected === '') {
            return false;
        }
        $key = trim((string) ($body['logSubModuleKey'] ?? $body['operationLogSubModule'] ?? ''));
        return $key === $expected;
    }
}
