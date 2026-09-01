import { adminWebMcpApi } from "@/services/adminWebMcpApi";
import { WEBMCP_TOOL_CATALOG } from "@/services/adminWebMcpCatalog";
import { recordWebMcpActivity } from "@/services/adminWebMcpOperations";

const MAX_ID = 2147483647;
const MAX_PAGE = 1000;
const MAX_LIMIT = 50;
const noop = () => {};

const PERMISSIONS = {
  fundingRead: ["page_fundingreport_readonly"],
  fundingExport: ["page_fundingreport_export"],
  dailyRead: ["page_dailyreport_readonly"],
  ibRead: ["page_ibstatement_readonly", "page_ibstatement"],
  ibExport: ["page_ibstatement_export"],
  operationRead: ["page_operationlogreport_readonly"],
  customRead: ["page_fundingreport_readonly"],
};

const reportRoutes = {
  funding: { name: "funding-report", path: "/funding-report", permissions: PERMISSIONS.fundingRead },
  daily_sales: { name: "daily-report", path: "/daily-report", permissions: PERMISSIONS.dailyRead },
  ib_statement: { name: "ib-statement", path: "/ib-statement", permissions: PERMISSIONS.ibRead },
  operation_logs: {
    name: "operation-log-report",
    path: "/operation-log-report",
    permissions: PERMISSIONS.operationRead,
  },
  custom: { name: "custom-report", path: "/custom-report", permissions: PERMISSIONS.customRead },
};

const dateSchema = {
  type: "string",
  pattern: "^\\d{4}-\\d{2}-\\d{2}$",
  description: "Calendar date in YYYY-MM-DD format.",
};
const paginationSchema = {
  page: { type: "integer", minimum: 1, maximum: MAX_PAGE },
  limit: { type: "integer", minimum: 1, maximum: MAX_LIMIT },
};
const periodSchema = {
  type: "object",
  properties: { startDate: dateSchema, endDate: dateSchema },
  dependentRequired: { startDate: ["endDate"], endDate: ["startDate"] },
  additionalProperties: false,
};
const fundingSearchSchema = {
  type: "object",
  properties: {
    startDate: dateSchema,
    endDate: dateSchema,
    type: { type: "string", enum: ["all", "deposit", "withdrawal", "internal_transfer"] },
    status: { type: "string", maxLength: 50 },
    query: { type: "string", maxLength: 200 },
    minAmount: { type: "number", minimum: 0 },
    maxAmount: { type: "number", minimum: 0 },
    ...paginationSchema,
  },
  dependentRequired: { startDate: ["endDate"], endDate: ["startDate"] },
  additionalProperties: false,
};
const dailySchema = {
  type: "object",
  properties: {
    date: dateSchema,
    tzOffset: { type: "integer", minimum: -720, maximum: 840 },
    rankBy: {
      type: "string",
      enum: ["deposits", "withdrawals", "netDeposit", "newLeads", "newClients"],
    },
    limit: paginationSchema.limit,
  },
  additionalProperties: false,
};
const ibSearchSchema = {
  type: "object",
  required: ["query"],
  properties: { query: { type: "string", maxLength: 100 }, ...paginationSchema },
  additionalProperties: false,
};
const ibStatementSchema = {
  type: "object",
  properties: {
    ibPartnerId: { type: "integer", minimum: 1, maximum: MAX_ID },
    ibCode: { type: "string", maxLength: 64 },
    startDate: dateSchema,
    endDate: dateSchema,
    ...paginationSchema,
  },
  oneOf: [{ required: ["ibPartnerId"] }, { required: ["ibCode"] }],
  dependentRequired: { startDate: ["endDate"], endDate: ["startDate"] },
  additionalProperties: false,
};
const customListSchema = {
  type: "object",
  properties: { search: { type: "string", maxLength: 100 }, ...paginationSchema },
  additionalProperties: false,
};
const customResultsSchema = {
  type: "object",
  required: ["reportId"],
  properties: {
    reportId: { type: "string", maxLength: 64 },
    widgetId: { type: "string", maxLength: 64 },
    ...paginationSchema,
  },
  additionalProperties: false,
};
const navigateSchema = {
  type: "object",
  required: ["reportKey"],
  properties: {
    reportKey: { type: "string", enum: Object.keys(reportRoutes) },
    reportId: { type: "string", maxLength: 64 },
  },
  additionalProperties: false,
};
const fundingExportSchema = {
  ...fundingSearchSchema,
  properties: {
    startDate: dateSchema,
    endDate: dateSchema,
    type: fundingSearchSchema.properties.type,
    status: fundingSearchSchema.properties.status,
    minAmount: fundingSearchSchema.properties.minAmount,
    maxAmount: fundingSearchSchema.properties.maxAmount,
  },
};
const ibExportSchema = {
  ...ibStatementSchema,
  properties: {
    ibPartnerId: ibStatementSchema.properties.ibPartnerId,
    ibCode: ibStatementSchema.properties.ibCode,
    startDate: dateSchema,
    endDate: dateSchema,
    format: { type: "string", enum: ["csv", "excel"] },
  },
};

const own = (value, key) => Object.prototype.hasOwnProperty.call(value, key);
const rejectUnsupportedKeys = (input, allowed, context) => {
  if (!input || typeof input !== "object" || Array.isArray(input)) {
    throw new Error("Input must be an object.");
  }
  const unsupported = Object.keys(input).find((key) => !allowed.includes(key));
  if (unsupported) throw new Error(`${unsupported} is not supported for ${context}.`);
};
const normalizeInteger = (value, name, maximum, defaultValue) => {
  if (value === undefined && defaultValue !== undefined) return defaultValue;
  const parsed =
    typeof value === "string" && /^\d+$/.test(value.trim()) ? Number(value.trim()) : value;
  if (!Number.isSafeInteger(parsed) || parsed < 1 || parsed > maximum) {
    throw new Error(`${name} must be an integer between 1 and ${maximum}.`);
  }
  return parsed;
};
const normalizeText = (value, name, maximum) => {
  if (typeof value !== "string") throw new Error(`${name} must be a string.`);
  const normalized = value.trim();
  if (!normalized || normalized.length > maximum) {
    throw new Error(`${name} must be between 1 and ${maximum} characters.`);
  }
  return normalized;
};
const normalizeDate = (value, name) => {
  if (typeof value !== "string" || !/^\d{4}-\d{2}-\d{2}$/.test(value.trim())) {
    throw new Error(`${name} must use a valid YYYY-MM-DD date.`);
  }
  const normalized = value.trim();
  const date = new Date(`${normalized}T00:00:00.000Z`);
  if (Number.isNaN(date.getTime()) || date.toISOString().slice(0, 10) !== normalized) {
    throw new Error(`${name} must use a valid YYYY-MM-DD date.`);
  }
  return normalized;
};
const normalizeNumber = (value, name) => {
  const number = typeof value === "string" && value.trim() !== "" ? Number(value) : value;
  if (typeof number !== "number" || !Number.isFinite(number) || number < 0) {
    throw new Error(`${name} must be a non-negative number.`);
  }
  return number;
};
const safeId = (value, name) => {
  const normalized = normalizeText(value, name, 64);
  if (!/^[A-Za-z0-9_-]+$/.test(normalized)) {
    throw new Error(`${name} contains unsupported characters.`);
  }
  return normalized;
};
const localToday = () => {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, "0");
  const day = String(now.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};
const currentMonthPeriod = (today) => ({ startDate: `${today.slice(0, 7)}-01`, endDate: today });

export const normalizeFundingPeriodInput = (input = {}, today = localToday()) => {
  rejectUnsupportedKeys(input, ["startDate", "endDate"], "a funding period");
  const hasStart = own(input, "startDate");
  const hasEnd = own(input, "endDate");
  if (hasStart !== hasEnd) throw new Error("startDate and endDate must both be provided.");
  const period = hasStart
    ? { startDate: normalizeDate(input.startDate, "startDate"), endDate: normalizeDate(input.endDate, "endDate") }
    : currentMonthPeriod(today);
  if (period.startDate > period.endDate) throw new Error("startDate cannot be after endDate.");
  return period;
};

export const normalizeFundingSearchInput = (input = {}, today = localToday()) => {
  rejectUnsupportedKeys(
    input,
    ["startDate", "endDate", "type", "status", "query", "minAmount", "maxAmount", "page", "limit"],
    "a funding transaction search",
  );
  const normalized = normalizeFundingPeriodInput(
    Object.fromEntries(Object.entries(input).filter(([key]) => ["startDate", "endDate"].includes(key))),
    today,
  );
  if (own(input, "type")) {
    if (!["all", "deposit", "withdrawal", "internal_transfer"].includes(input.type)) {
      throw new Error("type must be all, deposit, withdrawal, or internal_transfer.");
    }
    normalized.type = input.type;
  }
  for (const [key, maximum] of [["status", 50], ["query", 200]]) {
    if (own(input, key)) normalized[key] = normalizeText(input[key], key, maximum);
  }
  for (const key of ["minAmount", "maxAmount"]) {
    if (own(input, key)) normalized[key] = normalizeNumber(input[key], key);
  }
  if (normalized.minAmount > normalized.maxAmount) {
    throw new Error("minAmount cannot be greater than maxAmount.");
  }
  normalized.page = normalizeInteger(input.page, "page", MAX_PAGE, 1);
  normalized.limit = normalizeInteger(input.limit, "limit", MAX_LIMIT, 25);
  return normalized;
};

export const normalizeDailySalesPerformanceInput = (
  input = {},
  today = localToday(),
  defaultTzOffset = -new Date().getTimezoneOffset(),
) => {
  rejectUnsupportedKeys(input, ["date", "tzOffset", "rankBy", "limit"], "daily sales performance");
  const tzOffset = own(input, "tzOffset") ? Number(input.tzOffset) : Number(defaultTzOffset);
  if (!Number.isInteger(tzOffset) || tzOffset < -720 || tzOffset > 840) {
    throw new Error("tzOffset must be an integer between -720 and 840.");
  }
  const rankBy = input.rankBy ?? "netDeposit";
  if (!["deposits", "withdrawals", "netDeposit", "newLeads", "newClients"].includes(rankBy)) {
    throw new Error("rankBy is not supported.");
  }
  return {
    date: own(input, "date") ? normalizeDate(input.date, "date") : today,
    tzOffset,
    rankBy,
    limit: normalizeInteger(input.limit, "limit", MAX_LIMIT, 25),
  };
};

export const normalizeIbSearchInput = (input = {}) => {
  rejectUnsupportedKeys(input, ["query", "page", "limit"], "an IB report search");
  return {
    query: normalizeText(input.query, "query", 100),
    page: normalizeInteger(input.page, "page", MAX_PAGE, 1),
    limit: normalizeInteger(input.limit, "limit", MAX_LIMIT, 25),
  };
};

export const normalizeIbStatementInput = (input = {}, today = localToday()) => {
  rejectUnsupportedKeys(
    input,
    ["ibPartnerId", "ibCode", "startDate", "endDate", "page", "limit", "format"],
    "an IB statement",
  );
  const selectors = ["ibPartnerId", "ibCode"].filter((key) => own(input, key));
  if (selectors.length !== 1) throw new Error("Exactly one of ibPartnerId or ibCode is required.");
  const normalized =
    selectors[0] === "ibPartnerId"
      ? { ibPartnerId: normalizeInteger(input.ibPartnerId, "ibPartnerId", MAX_ID) }
      : { ibCode: normalizeText(input.ibCode, "ibCode", 64) };
  Object.assign(
    normalized,
    normalizeFundingPeriodInput(
      Object.fromEntries(Object.entries(input).filter(([key]) => ["startDate", "endDate"].includes(key))),
      today,
    ),
  );
  normalized.page = normalizeInteger(input.page, "page", MAX_PAGE, 1);
  normalized.limit = normalizeInteger(input.limit, "limit", MAX_LIMIT, 25);
  if (own(input, "format")) {
    if (!["csv", "excel"].includes(input.format)) throw new Error("format must be csv or excel.");
    normalized.format = input.format;
  }
  return normalized;
};

const normalizeCustomListInput = (input = {}) => {
  rejectUnsupportedKeys(input, ["search", "page", "limit"], "a custom report list");
  const normalized = {};
  if (own(input, "search")) normalized.search = normalizeText(input.search, "search", 100);
  normalized.page = normalizeInteger(input.page, "page", MAX_PAGE, 1);
  normalized.limit = normalizeInteger(input.limit, "limit", MAX_LIMIT, 25);
  return normalized;
};
const normalizeCustomResultsInput = (input = {}) => {
  rejectUnsupportedKeys(input, ["reportId", "widgetId", "page", "limit"], "custom report results");
  const normalized = { reportId: safeId(input.reportId, "reportId") };
  if (own(input, "widgetId")) normalized.widgetId = safeId(input.widgetId, "widgetId");
  normalized.page = normalizeInteger(input.page, "page", MAX_PAGE, 1);
  normalized.limit = normalizeInteger(input.limit, "limit", MAX_LIMIT, 25);
  return normalized;
};

const hasAnyPermission = (authStore, permissionKeys) =>
  permissionKeys.some((key) => authStore?.hasPermission?.(key));
const requirePermission = (authStore, permissionKeys) => {
  if (!authStore?.isAuthenticated || !hasAnyPermission(authStore, permissionKeys)) {
    throw new Error("You are not authorized to use this tool.");
  }
};
const catalogEntry = (name) => WEBMCP_TOOL_CATALOG.find((tool) => tool.name === name);
const executeApi = async (operation, notFoundMessage, unavailableMessage) => {
  try {
    return await operation();
  } catch (error) {
    const status = error?.statusCode ?? error?.response?.data?.statusCode;
    if ([401, 403].includes(status)) throw new Error("You are not authorized to use this tool.");
    if (status === 404) throw new Error(notFoundMessage);
    throw new Error(unavailableMessage);
  }
};
const createReadTool = ({ name, schema, normalize, permissionKeys, authStore, webMcpApi, apiMethod, unavailable }) => ({
  name,
  title: catalogEntry(name)?.title || name,
  description: catalogEntry(name)?.description || name,
  inputSchema: schema,
  annotations: { readOnlyHint: true, destructiveHint: false, untrustedContentHint: true },
  execute: async (input = {}) => {
    requirePermission(authStore, permissionKeys);
    const normalized = normalize(input);
    return executeApi(
      () => webMcpApi[apiMethod](normalized),
      "Report data not found.",
      unavailable,
    );
  },
});

export const createGetFundingSummaryTool = ({ authStore, webMcpApi = adminWebMcpApi }) =>
  createReadTool({
    name: "get_funding_summary",
    schema: periodSchema,
    normalize: normalizeFundingPeriodInput,
    permissionKeys: PERMISSIONS.fundingRead,
    authStore,
    webMcpApi,
    apiMethod: "getFundingSummary",
    unavailable: "Unable to get the funding summary.",
  });
export const createSearchFundingTransactionsTool = ({ authStore, webMcpApi = adminWebMcpApi }) =>
  createReadTool({
    name: "search_funding_transactions",
    schema: fundingSearchSchema,
    normalize: normalizeFundingSearchInput,
    permissionKeys: PERMISSIONS.fundingRead,
    authStore,
    webMcpApi,
    apiMethod: "searchFundingTransactions",
    unavailable: "Unable to search funding transactions.",
  });
export const createGetDailySalesPerformanceTool = ({ authStore, webMcpApi = adminWebMcpApi }) =>
  createReadTool({
    name: "get_daily_sales_performance",
    schema: dailySchema,
    normalize: normalizeDailySalesPerformanceInput,
    permissionKeys: PERMISSIONS.dailyRead,
    authStore,
    webMcpApi,
    apiMethod: "getDailySalesPerformance",
    unavailable: "Unable to get daily sales performance.",
  });
export const createSearchIbPartnersTool = ({ authStore, webMcpApi = adminWebMcpApi }) =>
  createReadTool({
    name: "search_ib_partners",
    schema: ibSearchSchema,
    normalize: normalizeIbSearchInput,
    permissionKeys: PERMISSIONS.ibRead,
    authStore,
    webMcpApi,
    apiMethod: "searchIbPartners",
    unavailable: "Unable to search IB partners.",
  });
export const createGetIbStatementTool = ({ authStore, webMcpApi = adminWebMcpApi }) =>
  createReadTool({
    name: "get_ib_statement",
    schema: ibStatementSchema,
    normalize: normalizeIbStatementInput,
    permissionKeys: PERMISSIONS.ibRead,
    authStore,
    webMcpApi,
    apiMethod: "getIbStatement",
    unavailable: "Unable to get the IB statement.",
  });
export const createListCustomReportsTool = ({ authStore, webMcpApi = adminWebMcpApi }) =>
  createReadTool({
    name: "list_custom_reports",
    schema: customListSchema,
    normalize: normalizeCustomListInput,
    permissionKeys: PERMISSIONS.customRead,
    authStore,
    webMcpApi,
    apiMethod: "listCustomReports",
    unavailable: "Unable to list custom reports.",
  });
export const createGetCustomReportResultsTool = ({ authStore, webMcpApi = adminWebMcpApi }) =>
  createReadTool({
    name: "get_custom_report_results",
    schema: customResultsSchema,
    normalize: normalizeCustomResultsInput,
    permissionKeys: PERMISSIONS.customRead,
    authStore,
    webMcpApi,
    apiMethod: "getCustomReportResults",
    unavailable: "Unable to get custom report results.",
  });

export const createNavigateToReportTool = ({ authStore, router }) => ({
  name: "navigate_to_report",
  title: catalogEntry("navigate_to_report")?.title || "Navigate to report",
  description: catalogEntry("navigate_to_report")?.description || "Open an available report.",
  inputSchema: navigateSchema,
  annotations: { readOnlyHint: true, destructiveHint: false, untrustedContentHint: true },
  execute: async (input = {}) => {
    rejectUnsupportedKeys(input, ["reportKey", "reportId"], "report navigation");
    const reportKey = String(input.reportKey || "").trim();
    const target = reportRoutes[reportKey];
    if (!target) throw new Error("reportKey is not supported.");
    requirePermission(authStore, target.permissions);
    if (!router?.push) throw new Error("Unable to navigate to the report.");
    let route = { name: target.name };
    let path = target.path;
    if (reportKey === "custom" && own(input, "reportId")) {
      const reportId = safeId(input.reportId, "reportId");
      route = { name: "custom-report-detail", params: { reportId } };
      path = `/custom-report/${encodeURIComponent(reportId)}`;
    } else if (own(input, "reportId")) {
      throw new Error("reportId is only supported for custom reports.");
    }
    try {
      await router.push(route);
    } catch {
      throw new Error("Unable to navigate to the report.");
    }
    return { success: true, reportKey, route: path };
  },
});

const openExportProgress = (router, jobId, exportKind) => {
  const route = { name: "webmcp-export-progress", query: { jobId, exportKind } };
  const progressUrl = router?.resolve?.(route)?.href ||
    `/#/webmcp/export-progress?jobId=${encodeURIComponent(jobId)}&exportKind=${encodeURIComponent(exportKind)}`;
  let opened = false;
  try {
    opened = Boolean(window?.open?.(progressUrl, "_blank", "noopener"));
  } catch {
    opened = false;
  }
  return { progressUrl, opened };
};
const createExportTool = ({
  name,
  schema,
  normalize,
  permissionKeys,
  authStore,
  webMcpApi,
  router,
  apiMethod,
  exportKind,
}) => ({
  name,
  title: catalogEntry(name)?.title || name,
  description: catalogEntry(name)?.description || name,
  inputSchema: schema,
  annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: false, untrustedContentHint: true },
  execute: async (input = {}) => {
    permissionKeys.forEach((keys) => requirePermission(authStore, keys));
    let normalized = normalize(input);
    if (exportKind === "ib_statement" && normalized.ibCode) {
      const lookup = await executeApi(
        () => webMcpApi.searchIbPartners({ query: normalized.ibCode, page: 1, limit: 25 }),
        "IB partner not found.",
        "Unable to resolve the IB partner.",
      );
      const exact = lookup?.partners?.find((partner) => partner.ibCode === normalized.ibCode);
      if (!exact) throw new Error("IB partner not found.");
      normalized = { ...normalized, ibPartnerId: Number(exact.id) };
      delete normalized.ibCode;
    }
    delete normalized.page;
    delete normalized.limit;
    const job = await executeApi(
      () => webMcpApi[apiMethod](normalized),
      "Report data not found.",
      "Unable to queue the report export.",
    );
    const jobId = String(job?.jobId || "").trim();
    if (!jobId) throw new Error("Unable to queue the report export.");
    recordWebMcpActivity(
      {
        id: `export:${jobId}`,
        kind: "export",
        label: catalogEntry(name)?.title || name,
        jobId,
        exportKind,
      },
      authStore?.user?.id,
    );
    return {
      success: true,
      jobId,
      exportKind,
      ...openExportProgress(router, jobId, exportKind),
      queued: Boolean(job?.queued),
      reused: Boolean(job?.reused),
      agentNextAction: "none",
      retryPolicy: "Only retry after the user reports that no file appeared.",
    };
  },
});

const normalizeFundingExportInput = (input = {}) => {
  const normalized = normalizeFundingSearchInput({ ...input, page: 1, limit: 25 });
  delete normalized.page;
  delete normalized.limit;
  delete normalized.query;
  return normalized;
};
const normalizeIbExportInput = (input = {}) => normalizeIbStatementInput(input);

export const createExportFundingReportTool = ({ authStore, webMcpApi = adminWebMcpApi, router }) =>
  createExportTool({
    name: "export_funding_report",
    schema: fundingExportSchema,
    normalize: normalizeFundingExportInput,
    permissionKeys: [PERMISSIONS.fundingExport],
    authStore,
    webMcpApi,
    router,
    apiMethod: "exportFundingReport",
    exportKind: "funding_report",
  });
export const createExportIbStatementTool = ({ authStore, webMcpApi = adminWebMcpApi, router }) =>
  createExportTool({
    name: "export_ib_statement",
    schema: ibExportSchema,
    normalize: normalizeIbExportInput,
    permissionKeys: [PERMISSIONS.ibRead, PERMISSIONS.ibExport],
    authStore,
    webMcpApi,
    router,
    apiMethod: "exportIbStatement",
    exportKind: "ib_statement",
  });

export const registerAdminReportWebMcpTools = ({
  authStore,
  webMcpApi = adminWebMcpApi,
  router,
  modelContext = typeof document !== "undefined" ? document.modelContext : undefined,
} = {}) => {
  if (!modelContext?.registerTool || !authStore?.isAuthenticated) return noop;
  const hasReportReadPermission = hasAnyPermission(authStore, [
    ...PERMISSIONS.fundingRead,
    ...PERMISSIONS.dailyRead,
    ...PERMISSIONS.ibRead,
    ...PERMISSIONS.operationRead,
    ...PERMISSIONS.customRead,
  ]);
  const definitions = [
    [() => hasAnyPermission(authStore, PERMISSIONS.fundingRead), () => createGetFundingSummaryTool({ authStore, webMcpApi })],
    [() => hasAnyPermission(authStore, PERMISSIONS.fundingRead), () => createSearchFundingTransactionsTool({ authStore, webMcpApi })],
    [() => hasAnyPermission(authStore, PERMISSIONS.dailyRead), () => createGetDailySalesPerformanceTool({ authStore, webMcpApi })],
    [() => hasAnyPermission(authStore, PERMISSIONS.ibRead), () => createSearchIbPartnersTool({ authStore, webMcpApi })],
    [() => hasAnyPermission(authStore, PERMISSIONS.ibRead), () => createGetIbStatementTool({ authStore, webMcpApi })],
    [() => hasAnyPermission(authStore, PERMISSIONS.customRead), () => createListCustomReportsTool({ authStore, webMcpApi })],
    [() => hasAnyPermission(authStore, PERMISSIONS.customRead), () => createGetCustomReportResultsTool({ authStore, webMcpApi })],
    [() => hasReportReadPermission, () => createNavigateToReportTool({ authStore, router })],
    [() => hasAnyPermission(authStore, PERMISSIONS.fundingExport), () => createExportFundingReportTool({ authStore, webMcpApi, router })],
    [
      () =>
        hasAnyPermission(authStore, PERMISSIONS.ibRead) &&
        hasAnyPermission(authStore, PERMISSIONS.ibExport),
      () => createExportIbStatementTool({ authStore, webMcpApi, router }),
    ],
  ];
  const tools = definitions.filter(([available]) => available()).map(([, create]) => create());
  const controller = new AbortController();
  try {
    tools.forEach((tool) => {
      Promise.resolve(modelContext.registerTool(tool, { signal: controller.signal })).catch((error) => {
        controller.abort();
        if (error?.name !== "AbortError") {
          console.warn("Admin Report WebMCP tool could not be registered.", error);
        }
      });
    });
  } catch (error) {
    controller.abort();
    console.warn("Admin Report WebMCP tool could not be registered.", error);
  }
  return () => controller.abort();
};
