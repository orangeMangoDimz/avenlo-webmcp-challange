<?php
/**
 * 后台操作日志 — 页面注册表（路由 → modelKey + subModuleKey）
 *
 * 约定：
 * - 字典 admin_dictionary_items 为子模块 key 的权威来源
 * - 本文件为开发导航；业务代码可按需读取，新模块埋点优先登记于此
 * - routeName 与 admin_frontend router name 一致
 *
 * defaultTarget：client_user | ib_partner | transaction | admin_user | points_mall_product | none
 * shared + inheritsSource：共享详情页，写操作子模块由 URL ?source= 决定
 */

return [
    /**
     * 页面别名 → pageKey（与 router.name / pages 数组下标一致）
     * Controller / 前端通过 OperationLogPages::pageKey('page_leads') 读取，勿硬编码路由名
     */
    'pageKeys' => [
        'page_leads' => 'leads',
        'page_clients_list' => 'clients-list',
        'page_ib_list' => 'ib-list',
        'page_client_detail' => 'client-detail',
        'page_kyc_list' => 'kyc-list',
        'page_kyc_templates' => 'kyc-templates',
        'page_kyc_settings' => 'kyc-settings',
        'page_deposits' => 'deposits',
        'page_withdrawals' => 'withdrawals',
        'page_funding_report' => 'funding-report',
        'page_ib_report' => 'ib-report',
        'page_ib_statement' => 'ib-statement',
        'page_operation_log_report' => 'operation-log-report',
        'page_custom_report' => 'custom-report',
        'page_sales_list' => 'sales-list',
        'page_sales_dashboard' => 'sales-dashboard',
        'page_internaltransfers' => 'internal-transfers',
        'page_addressverification' => 'address-verification',
        'page_withdrawkyctemplates' => 'withdraw-kyc-templates',
        'page_transactionsettings' => 'transaction-settings',
        'page_ib_initial_review' => 'ib-initial-review',
        'page_ib_risk_review' => 'ib-risk-review',
        'page_ib_final_review' => 'ib-final-review',
        'page_ib_commission_order' => 'ib-commission-order',
        'page_ib_settings' => 'ib-settings',
        'page_points_mall_settings' => 'points-mall-settings',
        'page_points_mall_products' => 'points-mall-products',
        'page_points_mall_redemptions' => 'points-mall-redemptions',
        'page_points_mall_ledger' => 'points-mall-ledger',
        'page_points_mall_categories' => 'points-mall-categories',
        'page_accounts' => 'accounts',
        'page_role_management' => 'role-management',
        'page_login_page_settings' => 'login-page-settings',
        'page_platform_settings' => 'platform-settings',
        'page_client_tickets' => 'client-tickets',
        'page_email_templates' => 'email-templates',
        'page_email_settings' => 'email-settings',
        'page_log_settings' => 'log-settings',
    ],

    'pages' => [
    // --- log_client ---
    'leads' => [
        'routeName' => 'leads',
        'path' => '/leads',
        'modelKey' => 'log_client',
        'subModuleKey' => 'leads',
        'defaultTarget' => 'client_user',
    ],
    'clients-list' => [
        'routeName' => 'clients-list',
        'path' => '/clients-list',
        'modelKey' => 'log_client',
        'subModuleKey' => 'clients_list',
        'defaultTarget' => 'client_user',
    ],
    'ib-list' => [
        'routeName' => 'ib-list',
        'path' => '/ib-list',
        'modelKey' => 'log_client',
        'subModuleKey' => 'ib_list',
        'defaultTarget' => 'client_user',
    ],
    'client-detail' => [
        'routeName' => 'client-detail',
        'path' => '/client-detail',
        'modelKey' => 'log_client',
        'subModuleKey' => null,
        'shared' => true,
        'inheritsSource' => true,
        'sourceQueryKey' => 'source',
        'defaultTarget' => 'client_user',
    ],

    // --- log_kyc ---
    'kyc-list' => [
        'routeName' => 'kyc-list',
        'path' => '/kyc-list',
        'modelKey' => 'log_kyc',
        'subModuleKey' => 'kyc_list',
        'defaultTarget' => 'client_user',
    ],
    'kyc-templates' => [
        'routeName' => 'kyc-templates',
        'path' => '/kyc-templates',
        'modelKey' => 'log_kyc',
        'subModuleKey' => 'kyc_templates',
        'defaultTarget' => 'none',
    ],
    'kyc-settings' => [
        'routeName' => 'kyc-settings',
        'path' => '/kyc-settings',
        'modelKey' => 'log_kyc',
        'subModuleKey' => 'kyc_settings',
        'defaultTarget' => 'none',
    ],
    // --- log_transaction ---
    'deposits' => [
        'routeName' => 'deposits',
        'path' => '/deposits',
        'modelKey' => 'log_transaction',
        'subModuleKey' => 'deposits',
        'defaultTarget' => 'client_user',
    ],
    'withdrawals' => [
        'routeName' => 'withdrawals',
        'path' => '/withdrawals',
        'modelKey' => 'log_transaction',
        'subModuleKey' => 'withdrawals',
        'defaultTarget' => 'client_user',
    ],
    'internal-transfers' => [
        'routeName' => 'internal-transfers',
        'path' => '/internal-transfers',
        'modelKey' => 'log_transaction',
        'subModuleKey' => 'internal_transfer',
        'defaultTarget' => 'client_user',
    ],
    'address-verification' => [
        'routeName' => 'address-verification',
        'path' => '/address-verification',
        'modelKey' => 'log_transaction',
        'subModuleKey' => 'address_verification',
        'defaultTarget' => 'client_user',
    ],
    'withdraw-kyc-templates' => [
        'routeName' => 'withdraw-kyc-templates',
        'path' => '/withdraw-kyc-templates',
        'modelKey' => 'log_transaction',
        'subModuleKey' => 'withdraw_kyc_templates',
        'defaultTarget' => 'none',
    ],
    'transaction-settings' => [
        'routeName' => 'transaction-settings',
        'path' => '/transaction-settings',
        'modelKey' => 'log_transaction',
        'subModuleKey' => 'transaction_settings',
        'defaultTarget' => 'none',
    ],

    // --- log_ib ---
    'ib-initial-review' => [
        'routeName' => 'ib-initial-review',
        'path' => '/ib-initial-review',
        'modelKey' => 'log_ib',
        'subModuleKey' => 'ib_initial',
        'defaultTarget' => 'client_user',
    ],
    'ib-risk-review' => [
        'routeName' => 'ib-risk-review',
        'path' => '/ib-risk-review',
        'modelKey' => 'log_ib',
        'subModuleKey' => 'ib_risk',
        'defaultTarget' => 'client_user',
    ],
    'ib-final-review' => [
        'routeName' => 'ib-final-review',
        'path' => '/ib-final-review',
        'modelKey' => 'log_ib',
        'subModuleKey' => 'ib_final',
        'defaultTarget' => 'client_user',
    ],
    'ib-commission-order' => [
        'routeName' => 'ib-commission-order',
        'path' => '/ib-commission-order',
        'modelKey' => 'log_ib',
        'subModuleKey' => 'ib_commission',
        'defaultTarget' => 'client_user',
    ],
    'ib-settings' => [
        'routeName' => 'ib-settings',
        'path' => '/ib-settings',
        'modelKey' => 'log_ib',
        'subModuleKey' => 'ib_settings',
        'defaultTarget' => 'none',
    ],

    // --- log_sales ---
    'sales-list' => [
        'routeName' => 'sales-list',
        'modelKey' => 'log_sales',
        'subModuleKey' => 'sales_list',
        'defaultTarget' => 'admin_user',
    ],
    'sales-dashboard' => [
        'routeName' => 'sales-dashboard',
        'modelKey' => 'log_sales',
        'subModuleKey' => 'sales_dashboard',
        'defaultTarget' => 'none',
    ],

    // --- log_points_mall ---
    'points-mall-settings' => [
        'routeName' => 'points-mall-settings',
        'path' => '/points-mall-settings',
        'modelKey' => 'log_points_mall',
        'subModuleKey' => 'pm_settings',
        'defaultTarget' => 'none',
    ],
    'points-mall-products' => [
        'routeName' => 'points-mall-products',
        'modelKey' => 'log_points_mall',
        'subModuleKey' => 'pm_products',
        'defaultTarget' => 'points_mall_product',
    ],
    'points-mall-redemptions' => [
        'routeName' => 'points-mall-redemptions',
        'modelKey' => 'log_points_mall',
        'subModuleKey' => 'pm_redemptions',
        'defaultTarget' => 'client_user',
    ],
    'points-mall-ledger' => [
        'routeName' => 'points-mall-ledger',
        'modelKey' => 'log_points_mall',
        'subModuleKey' => 'pm_ledger',
        'defaultTarget' => 'client_user',
    ],
    'points-mall-categories' => [
        'routeName' => 'points-mall-categories',
        'modelKey' => 'log_points_mall',
        'subModuleKey' => 'pm_categories',
        'defaultTarget' => 'none',
    ],

    // --- log_report ---
    'funding-report' => [
        'routeName' => 'funding-report',
        'modelKey' => 'log_report',
        'subModuleKey' => 'funding_report',
        'defaultTarget' => 'none',
    ],
    'ib-report' => [
        'routeName' => 'ib-report',
        'modelKey' => 'log_report',
        'subModuleKey' => 'ib_report',
        'defaultTarget' => 'none',
    ],
    'ib-statement' => [
        'routeName' => 'ib-statement',
        'path' => '/ib-statement',
        'modelKey' => 'log_report',
        'subModuleKey' => 'ib_statement',
        'defaultTarget' => 'none',
    ],
    'operation-log-report' => [
        'routeName' => 'operation-log-report',
        'modelKey' => 'log_report',
        'subModuleKey' => 'operation_log_report',
        'defaultTarget' => 'none',
    ],
    'custom-report' => [
        'routeName' => 'custom-report',
        'path' => '/custom-report',
        'modelKey' => 'log_report',
        'subModuleKey' => 'custom_report',
        'defaultTarget' => 'none',
    ],

    // --- log_system ---
    'accounts' => [
        'routeName' => 'accounts',
        'path' => '/accounts',
        'modelKey' => 'log_system',
        'subModuleKey' => 'accounts',
        'defaultTarget' => 'admin_user',
    ],
    'role-management' => [
        'routeName' => 'role-management',
        'path' => '/role-management',
        'modelKey' => 'log_system',
        'subModuleKey' => 'role_management',
        'defaultTarget' => 'admin_role',
    ],
    'login-page-settings' => [
        'routeName' => 'login-page-settings',
        'path' => '/login-page-settings',
        'modelKey' => 'log_system',
        'subModuleKey' => 'login_page_settings',
        'defaultTarget' => 'none',
    ],
    'platform-settings' => [
        'routeName' => 'platform-settings',
        'path' => '/platform-settings',
        'modelKey' => 'log_system',
        'subModuleKey' => 'platform_settings',
        'defaultTarget' => 'none',
    ],
    'client-tickets' => [
        'routeName' => 'client-tickets',
        'path' => '/client-tickets',
        'modelKey' => 'log_system',
        'subModuleKey' => 'client_tickets',
        'defaultTarget' => 'client_user',
    ],
    'email-templates' => [
        'routeName' => 'email-templates',
        'path' => '/email-templates',
        'modelKey' => 'log_system',
        'subModuleKey' => 'email_templates',
        'defaultTarget' => 'none',
    ],
    'email-settings' => [
        'routeName' => 'email-settings',
        'path' => '/email-settings',
        'modelKey' => 'log_system',
        'subModuleKey' => 'email_settings',
        'defaultTarget' => 'none',
    ],
    'log-settings' => [
        'routeName' => 'log-settings',
        'path' => '/log-settings',
        'modelKey' => 'log_system',
        'subModuleKey' => 'log_settings',
        'defaultTarget' => 'none',
    ],
    ],

    /** client-detail ?source= 值 → pageKey（router.name） */
    'detailSourceMap' => [
        'leads' => 'leads',
        'clients_list' => 'clients-list',
        'ib_list' => 'ib-list',
    ],
];
