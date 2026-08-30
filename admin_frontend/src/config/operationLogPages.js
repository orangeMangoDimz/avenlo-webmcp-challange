/**
 * 后台操作日志 — 页面注册表（与 back-end/config/operation_log_pages.php 对齐）
 * @sync back-end/config/operation_log_pages.php
 * routeName 与 router name 一致；新页埋点前先在此登记。
 */

/** 页面别名 → pageKey（与 router.name 一致） */
export const PAGE_KEY_ALIASES = {
  page_leads: "leads",
  page_clients_list: "clients-list",
  page_ib_list: "ib-list",
  page_client_detail: "client-detail",
  page_kyc_list: "kyc-list",
  page_kyc_templates: "kyc-templates",
  page_kyc_settings: "kyc-settings",
  page_deposits: "deposits",
  page_withdrawals: "withdrawals",
  page_funding_report: "funding-report",
  page_ib_report: "ib-report",
  page_ib_statement: "ib-statement",
  page_operation_log_report: "operation-log-report",
  page_custom_report: "custom-report",
  page_sales_list: "sales-list",
  page_sales_dashboard: "sales-dashboard",
  page_internaltransfers: "internal-transfers",
  page_addressverification: "address-verification",
  page_withdrawkyctemplates: "withdraw-kyc-templates",
  page_transactionsettings: "transaction-settings",
  page_ib_initial_review: "ib-initial-review",
  page_ib_risk_review: "ib-risk-review",
  page_ib_final_review: "ib-final-review",
  page_ib_commission_order: "ib-commission-order",
  page_ib_settings: "ib-settings",
  page_accounts: "accounts",
  page_role_management: "role-management",
  page_login_page_settings: "login-page-settings",
  page_platform_settings: "platform-settings",
  page_client_tickets: "client-tickets",
  page_email_templates: "email-templates",
  page_email_settings: "email-settings",
  page_log_settings: "log-settings",
};

export function pageKey(alias) {
  return PAGE_KEY_ALIASES[alias] ?? "";
}

/** client-detail ?source= 值 → pageKey（router.name） */
export const DETAIL_SOURCE_MAP = {
  leads: pageKey("page_leads"),
  clients_list: pageKey("page_clients_list"),
  ib_list: pageKey("page_ib_list"),
};

export const OPERATION_LOG_PAGES = {
  [pageKey("page_leads")]: {
    routeName: "leads",
    path: "/leads",
    modelKey: "log_client",
    subModuleKey: "leads",
    defaultTarget: "client_user",
  },
  [pageKey("page_clients_list")]: {
    routeName: "clients-list",
    path: "/clients-list",
    modelKey: "log_client",
    subModuleKey: "clients_list",
    defaultTarget: "client_user",
  },
  [pageKey("page_ib_list")]: {
    routeName: "ib-list",
    path: "/ib-list",
    modelKey: "log_client",
    subModuleKey: "ib_list",
    defaultTarget: "client_user",
  },
  [pageKey("page_client_detail")]: {
    routeName: "client-detail",
    path: "/client-detail",
    modelKey: "log_client",
    subModuleKey: null,
    shared: true,
    inheritsSource: true,
    sourceQueryKey: "source",
    defaultTarget: "client_user",
  },
  [pageKey("page_kyc_list")]: {
    routeName: "kyc-list",
    path: "/kyc-list",
    modelKey: "log_kyc",
    subModuleKey: "kyc_list",
    defaultTarget: "client_user",
  },
  [pageKey("page_kyc_templates")]: {
    routeName: "kyc-templates",
    path: "/kyc-templates",
    modelKey: "log_kyc",
    subModuleKey: "kyc_templates",
    defaultTarget: "none",
  },
  [pageKey("page_kyc_settings")]: {
    routeName: "kyc-settings",
    path: "/kyc-settings",
    modelKey: "log_kyc",
    subModuleKey: "kyc_settings",
    defaultTarget: "none",
  },
  [pageKey("page_deposits")]: {
    routeName: "deposits",
    path: "/deposits",
    modelKey: "log_transaction",
    subModuleKey: "deposits",
    defaultTarget: "client_user",
  },
  [pageKey("page_withdrawals")]: {
    routeName: "withdrawals",
    path: "/withdrawals",
    modelKey: "log_transaction",
    subModuleKey: "withdrawals",
    defaultTarget: "client_user",
  },
  [pageKey("page_sales_list")]: {
    routeName: "sales-list",
    path: "/sales-list",
    modelKey: "log_sales",
    subModuleKey: "sales_list",
    defaultTarget: "admin_user",
  },
  [pageKey("page_sales_dashboard")]: {
    routeName: "sales-dashboard",
    path: "/sales-dashboard",
    modelKey: "log_sales",
    subModuleKey: "sales_dashboard",
    defaultTarget: "none",
  },
  "internal-transfers": {
    routeName: "internal-transfers",
    path: "/internal-transfers",
    modelKey: "log_transaction",
    subModuleKey: "internal_transfer",
    defaultTarget: "client_user",
  },
  [pageKey("page_addressverification")]: {
    routeName: "address-verification",
    path: "/address-verification",
    modelKey: "log_transaction",
    subModuleKey: "address_verification",
    defaultTarget: "client_user",
  },
  [pageKey("page_withdrawkyctemplates")]: {
    routeName: "withdraw-kyc-templates",
    path: "/withdraw-kyc-templates",
    modelKey: "log_transaction",
    subModuleKey: "withdraw_kyc_templates",
    defaultTarget: "none",
  },
  [pageKey("page_transactionsettings")]: {
    routeName: "transaction-settings",
    path: "/transaction-settings",
    modelKey: "log_transaction",
    subModuleKey: "transaction_settings",
    defaultTarget: "none",
  },
  [pageKey("page_ib_initial_review")]: {
    routeName: "ib-initial-review",
    path: "/ib-initial-review",
    modelKey: "log_ib",
    subModuleKey: "ib_initial",
    defaultTarget: "client_user",
  },
  [pageKey("page_ib_risk_review")]: {
    routeName: "ib-risk-review",
    path: "/ib-risk-review",
    modelKey: "log_ib",
    subModuleKey: "ib_risk",
    defaultTarget: "client_user",
  },
  [pageKey("page_ib_final_review")]: {
    routeName: "ib-final-review",
    path: "/ib-final-review",
    modelKey: "log_ib",
    subModuleKey: "ib_final",
    defaultTarget: "client_user",
  },
  [pageKey("page_ib_commission_order")]: {
    routeName: "ib-commission-order",
    path: "/ib-commission-order",
    modelKey: "log_ib",
    subModuleKey: "ib_commission",
    defaultTarget: "client_user",
  },
  [pageKey("page_ib_settings")]: {
    routeName: "ib-settings",
    path: "/ib-settings",
    modelKey: "log_ib",
    subModuleKey: "ib_settings",
    defaultTarget: "none",
  },
  [pageKey("page_funding_report")]: {
    routeName: "funding-report",
    modelKey: "log_report",
    subModuleKey: "funding_report",
    defaultTarget: "none",
  },
  [pageKey("page_ib_report")]: {
    routeName: "ib-report",
    modelKey: "log_report",
    subModuleKey: "ib_report",
    defaultTarget: "none",
  },
  [pageKey("page_ib_statement")]: {
    routeName: "ib-statement",
    path: "/ib-statement",
    modelKey: "log_report",
    subModuleKey: "ib_statement",
    defaultTarget: "none",
  },
  [pageKey("page_operation_log_report")]: {
    routeName: "operation-log-report",
    modelKey: "log_report",
    subModuleKey: "operation_log_report",
    defaultTarget: "none",
  },
  [pageKey("page_custom_report")]: {
    routeName: "custom-report",
    path: "/custom-report",
    modelKey: "log_report",
    subModuleKey: "custom_report",
    defaultTarget: "none",
  },
  [pageKey("page_accounts")]: {
    routeName: "accounts",
    path: "/accounts",
    modelKey: "log_system",
    subModuleKey: "accounts",
    defaultTarget: "admin_user",
  },
  [pageKey("page_role_management")]: {
    routeName: "role-management",
    path: "/role-management",
    modelKey: "log_system",
    subModuleKey: "role_management",
    defaultTarget: "admin_role",
  },
  [pageKey("page_login_page_settings")]: {
    routeName: "login-page-settings",
    path: "/login-page-settings",
    modelKey: "log_system",
    subModuleKey: "login_page_settings",
    defaultTarget: "none",
  },
  [pageKey("page_platform_settings")]: {
    routeName: "platform-settings",
    path: "/platform-settings",
    modelKey: "log_system",
    subModuleKey: "platform_settings",
    defaultTarget: "none",
  },
  [pageKey("page_client_tickets")]: {
    routeName: "client-tickets",
    path: "/client-tickets",
    modelKey: "log_system",
    subModuleKey: "client_tickets",
    defaultTarget: "client_user",
  },
  [pageKey("page_email_templates")]: {
    routeName: "email-templates",
    path: "/email-templates",
    modelKey: "log_system",
    subModuleKey: "email_templates",
    defaultTarget: "none",
  },
  [pageKey("page_email_settings")]: {
    routeName: "email-settings",
    path: "/email-settings",
    modelKey: "log_system",
    subModuleKey: "email_settings",
    defaultTarget: "none",
  },
  [pageKey("page_log_settings")]: {
    routeName: "log-settings",
    path: "/log-settings",
    modelKey: "log_system",
    subModuleKey: "log_settings",
    defaultTarget: "none",
  },
};

export function getOperationLogPage(routeName) {
  return OPERATION_LOG_PAGES[routeName] || null;
}

export function getModelKey(pageKeyOrAlias) {
  const key = OPERATION_LOG_PAGES[pageKeyOrAlias]
    ? pageKeyOrAlias
    : pageKey(pageKeyOrAlias);
  const page = getOperationLogPage(key);
  return page?.modelKey ?? "";
}

export function getSubModuleKey(pageKeyOrAlias) {
  const key = OPERATION_LOG_PAGES[pageKeyOrAlias]
    ? pageKeyOrAlias
    : pageKey(pageKeyOrAlias);
  const page = getOperationLogPage(key);
  return page?.subModuleKey ?? "";
}

export function subModuleKeyFromDetailSource(source) {
  const key = String(source || "").trim();
  const resolvedPageKey = DETAIL_SOURCE_MAP[key] || pageKey("page_leads");
  return getSubModuleKey(resolvedPageKey);
}

/**
 * 纯前端导出等场景调用 POST /operation-log/record
 * @param {string} pageAlias - 如 'page_kyc_list'
 */
export function buildExportLogPayload(
  pageAlias,
  { detailZh, detailEn, operationTypeKey = "export" } = {},
) {
  return {
    modelKey: getModelKey(pageAlias),
    subModuleKey: getSubModuleKey(pageAlias),
    operationTypeKey,
    detailZh: detailZh ?? "",
    detailEn: detailEn ?? "",
    targetId: null,
  };
}

export default OPERATION_LOG_PAGES;
