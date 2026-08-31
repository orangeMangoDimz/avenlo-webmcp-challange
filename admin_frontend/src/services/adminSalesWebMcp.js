import { adminWebMcpApi } from "@/services/adminWebMcpApi";
import { WEBMCP_TOOL_CATALOG } from "@/services/adminWebMcpCatalog";

const MAX_ID = 2147483647;
const MAX_PAGE = 1000;
const MAX_LIMIT = 50;
const MAX_QUERY_LENGTH = 100;
const SALES_MANAGER_ROLE_ID = 5;
const SALES_ROLE_ID = 6;
const noop = () => {};

const salesIdProperty = {
  type: "integer",
  minimum: 1,
  maximum: MAX_ID,
  description: "Exact sales user ID returned by search_sales.",
};

const paginationProperties = {
  page: { type: "integer", minimum: 1, maximum: MAX_PAGE },
  limit: { type: "integer", minimum: 1, maximum: MAX_LIMIT },
};

const tzOffsetProperty = {
  type: "integer",
  minimum: -720,
  maximum: 840,
  description: "Timezone offset in minutes east of UTC; defaults to the browser timezone.",
};

const searchSalesInputSchema = {
  type: "object",
  required: ["query"],
  properties: {
    query: {
      type: "string",
      maxLength: MAX_QUERY_LENGTH,
      description: "Sales ID, name, username, or email search text.",
    },
    ...paginationProperties,
  },
  additionalProperties: false,
};

const relationshipInputSchema = {
  type: "object",
  required: ["salesId"],
  properties: {
    salesId: salesIdProperty,
    search: {
      type: "string",
      maxLength: MAX_QUERY_LENGTH,
      description: "Optional client or partner search text.",
    },
    ...paginationProperties,
  },
  additionalProperties: false,
};

const performanceInputSchema = {
  type: "object",
  required: ["salesId"],
  properties: {
    salesId: salesIdProperty,
    month: {
      type: "string",
      pattern: "^\\d{4}-\\d{2}$",
      description: "Calendar month in YYYY-MM format; defaults to the current month.",
    },
    tzOffset: tzOffsetProperty,
  },
  additionalProperties: false,
};

const dailyInputSchema = {
  type: "object",
  required: ["salesId"],
  properties: {
    salesId: salesIdProperty,
    date: {
      type: "string",
      pattern: "^\\d{4}-\\d{2}-\\d{2}$",
      description: "Calendar date in YYYY-MM-DD format; defaults to today.",
    },
    tzOffset: tzOffsetProperty,
  },
  additionalProperties: false,
};

const leaderboardInputSchema = {
  type: "object",
  properties: {
    metric: {
      type: "string",
      enum: ["newClients", "newLeads", "netDeposit", "deposits"],
      description: "Metric to rank; defaults to newClients.",
    },
    period: {
      type: "string",
      enum: ["day", "month"],
      description: "Ranking period; defaults to month.",
    },
    date: {
      type: "string",
      pattern: "^\\d{4}-\\d{2}-\\d{2}$",
      description: "Date for a daily leaderboard.",
    },
    month: {
      type: "string",
      pattern: "^\\d{4}-\\d{2}$",
      description: "Month for a monthly leaderboard.",
    },
    tzOffset: tzOffsetProperty,
    limit: { type: "integer", minimum: 1, maximum: MAX_LIMIT },
  },
  additionalProperties: false,
};

const navigateInputSchema = {
  type: "object",
  required: ["salesId"],
  properties: { salesId: salesIdProperty },
  additionalProperties: false,
};

const rejectUnsupportedKeys = (input, allowed, context) => {
  if (!input || typeof input !== "object" || Array.isArray(input)) {
    throw new Error("Input must be an object.");
  }
  const unsupported = Object.keys(input).find((key) => !allowed.includes(key));
  if (unsupported) {
    throw new Error(`${unsupported} is not supported for ${context}.`);
  }
};

const normalizePositiveInteger = (value, name, maximum, defaultValue) => {
  if (value === undefined && defaultValue !== undefined) return defaultValue;
  const parsed =
    typeof value === "string" && /^\d+$/.test(value.trim())
      ? Number(value.trim())
      : value;
  if (!Number.isSafeInteger(parsed) || parsed < 1 || parsed > maximum) {
    throw new Error(`${name} must be an integer between 1 and ${maximum}.`);
  }
  return parsed;
};

const normalizeString = (value, name, maximum) => {
  if (typeof value !== "string") {
    throw new Error(`${name} must be a string.`);
  }
  const normalized = value.trim();
  if (!normalized || normalized.length > maximum) {
    throw new Error(`${name} must be between 1 and ${maximum} characters.`);
  }
  return normalized;
};

const normalizeTzOffset = (value) => {
  const parsed =
    typeof value === "string" && /^-?\d+$/.test(value.trim())
      ? Number(value.trim())
      : value;
  if (!Number.isSafeInteger(parsed) || parsed < -720 || parsed > 840) {
    throw new Error("tzOffset must be an integer between -720 and 840.");
  }
  return parsed;
};

const normalizeDate = (value, name, pattern, isoLength) => {
  if (typeof value !== "string" || !pattern.test(value.trim())) {
    throw new Error(`${name} must use a valid ${isoLength === 7 ? "YYYY-MM" : "YYYY-MM-DD"} value.`);
  }
  const normalized = value.trim();
  const probe = isoLength === 7 ? `${normalized}-01` : normalized;
  const date = new Date(`${probe}T00:00:00.000Z`);
  if (Number.isNaN(date.getTime()) || date.toISOString().slice(0, isoLength) !== normalized) {
    throw new Error(`${name} must use a valid ${isoLength === 7 ? "YYYY-MM" : "YYYY-MM-DD"} value.`);
  }
  return normalized;
};

const browserTzOffset = () => -new Date().getTimezoneOffset();

export const normalizeSalesSearchInput = (input = {}) => {
  rejectUnsupportedKeys(input, ["query", "page", "limit"], "a sales search");
  return {
    query: normalizeString(input.query, "query", MAX_QUERY_LENGTH),
    page: normalizePositiveInteger(input.page, "page", MAX_PAGE, 1),
    limit: normalizePositiveInteger(input.limit, "limit", MAX_LIMIT, 25),
  };
};

export const normalizeSalesRelationshipInput = (input = {}) => {
  rejectUnsupportedKeys(
    input,
    ["salesId", "search", "page", "limit"],
    "a sales relationship lookup",
  );
  const normalized = {
    salesId: normalizePositiveInteger(input.salesId, "salesId", MAX_ID),
  };
  if (Object.hasOwn(input, "search")) {
    normalized.search = normalizeString(input.search, "search", MAX_QUERY_LENGTH);
  }
  normalized.page = normalizePositiveInteger(input.page, "page", MAX_PAGE, 1);
  normalized.limit = normalizePositiveInteger(input.limit, "limit", MAX_LIMIT, 25);
  return normalized;
};

export const normalizeSalesPerformanceInput = (
  input = {},
  defaultTzOffset = browserTzOffset(),
) => {
  rejectUnsupportedKeys(input, ["salesId", "month", "tzOffset"], "sales performance");
  const normalized = {
    salesId: normalizePositiveInteger(input.salesId, "salesId", MAX_ID),
  };
  if (Object.hasOwn(input, "month")) {
    normalized.month = normalizeDate(input.month, "month", /^\d{4}-\d{2}$/, 7);
  }
  normalized.tzOffset = normalizeTzOffset(input.tzOffset ?? defaultTzOffset);
  return normalized;
};

export const normalizeSalesDailyInput = (
  input = {},
  defaultTzOffset = browserTzOffset(),
) => {
  rejectUnsupportedKeys(input, ["salesId", "date", "tzOffset"], "a sales daily summary");
  const normalized = {
    salesId: normalizePositiveInteger(input.salesId, "salesId", MAX_ID),
  };
  if (Object.hasOwn(input, "date")) {
    normalized.date = normalizeDate(input.date, "date", /^\d{4}-\d{2}-\d{2}$/, 10);
  }
  normalized.tzOffset = normalizeTzOffset(input.tzOffset ?? defaultTzOffset);
  return normalized;
};

export const normalizeSalesLeaderboardInput = (
  input = {},
  defaultTzOffset = browserTzOffset(),
) => {
  rejectUnsupportedKeys(
    input,
    ["metric", "period", "date", "month", "tzOffset", "limit"],
    "a sales leaderboard",
  );
  const metric = input.metric ?? "newClients";
  if (!["newClients", "newLeads", "netDeposit", "deposits"].includes(metric)) {
    throw new Error("metric must be one of: newClients, newLeads, netDeposit, deposits.");
  }
  const period = String(input.period ?? "month").trim().toLowerCase();
  if (!["day", "month"].includes(period)) {
    throw new Error("period must be day or month.");
  }
  if (period === "day" && Object.hasOwn(input, "month")) {
    throw new Error("month is not supported for a daily leaderboard.");
  }
  if (period === "month" && Object.hasOwn(input, "date")) {
    throw new Error("date is not supported for a monthly leaderboard.");
  }
  const normalized = {
    metric,
    period,
    tzOffset: normalizeTzOffset(input.tzOffset ?? defaultTzOffset),
    limit: normalizePositiveInteger(input.limit, "limit", MAX_LIMIT, 10),
  };
  if (Object.hasOwn(input, "date")) {
    normalized.date = normalizeDate(input.date, "date", /^\d{4}-\d{2}-\d{2}$/, 10);
  }
  if (Object.hasOwn(input, "month")) {
    normalized.month = normalizeDate(input.month, "month", /^\d{4}-\d{2}$/, 7);
  }
  return normalized;
};

const capabilities = (authStore) => {
  const roleId = Number(authStore?.user?.roleId ?? 0);
  const superAdmin = roleId === 1;
  const management =
    superAdmin ||
    roleId === SALES_MANAGER_ROLE_ID ||
    Boolean(authStore?.hasPermission?.("page_saleslist_view"));
  const sales =
    management ||
    roleId === SALES_ROLE_ID ||
    Boolean(authStore?.hasPermission?.("page_salesdashboard_view"));
  return {
    sales,
    management,
    daily:
      sales &&
      Boolean(authStore?.hasPermission?.("page_dailyreport_readonly")),
    leaderboard:
      management &&
      Boolean(authStore?.hasPermission?.("page_dailyreport_readonly")),
  };
};

const requireCapability = (authStore, capability) => {
  const allowed = authStore?.isAuthenticated && capabilities(authStore)[capability];
  if (!allowed) {
    throw new Error("You are not authorized to use this tool.");
  }
};

const catalogEntry = (name) =>
  WEBMCP_TOOL_CATALOG.find((tool) => tool.name === name);

const executeApi = async (operation, notFoundMessage, unavailableMessage) => {
  try {
    return await operation();
  } catch (error) {
    const statusCode = error?.statusCode ?? error?.response?.data?.statusCode;
    if (statusCode === 401 || statusCode === 403) {
      throw new Error("You are not authorized to use this tool.");
    }
    if (statusCode === 404) {
      throw new Error(notFoundMessage);
    }
    throw new Error(unavailableMessage);
  }
};

const createReadTool = ({
  name,
  inputSchema,
  normalizeInput,
  apiMethod,
  authStore,
  webMcpApi,
  capability = "sales",
  unavailableMessage,
}) => ({
  name,
  title: catalogEntry(name)?.title,
  description: catalogEntry(name)?.description || name,
  inputSchema,
  annotations: { readOnlyHint: true, untrustedContentHint: true },
  execute: async (input = {}) => {
    requireCapability(authStore, capability);
    const normalized = normalizeInput(input);
    return executeApi(
      () => webMcpApi[apiMethod](normalized),
      "Sales user not found.",
      unavailableMessage,
    );
  },
});

export const createSearchSalesTool = ({ authStore, webMcpApi = adminWebMcpApi }) =>
  createReadTool({
    name: "search_sales",
    inputSchema: searchSalesInputSchema,
    normalizeInput: normalizeSalesSearchInput,
    apiMethod: "searchSales",
    authStore,
    webMcpApi,
    unavailableMessage: "Unable to search sales users.",
  });

export const createGetSalesClientsTool = ({ authStore, webMcpApi = adminWebMcpApi }) =>
  createReadTool({
    name: "get_sales_clients",
    inputSchema: relationshipInputSchema,
    normalizeInput: normalizeSalesRelationshipInput,
    apiMethod: "getSalesClients",
    authStore,
    webMcpApi,
    unavailableMessage: "Unable to get assigned clients.",
  });

export const createGetSalesPartnersTool = ({ authStore, webMcpApi = adminWebMcpApi }) =>
  createReadTool({
    name: "get_sales_partners",
    inputSchema: relationshipInputSchema,
    normalizeInput: normalizeSalesRelationshipInput,
    apiMethod: "getSalesPartners",
    authStore,
    webMcpApi,
    unavailableMessage: "Unable to get assigned IB partners.",
  });

export const createGetSalesPerformanceTool = ({ authStore, webMcpApi = adminWebMcpApi }) =>
  createReadTool({
    name: "get_sales_performance",
    inputSchema: performanceInputSchema,
    normalizeInput: normalizeSalesPerformanceInput,
    apiMethod: "getSalesPerformance",
    authStore,
    webMcpApi,
    unavailableMessage: "Unable to get sales performance.",
  });

export const createGetSalesDailySummaryTool = ({ authStore, webMcpApi = adminWebMcpApi }) =>
  createReadTool({
    name: "get_sales_daily_summary",
    inputSchema: dailyInputSchema,
    normalizeInput: normalizeSalesDailyInput,
    apiMethod: "getSalesDailySummary",
    authStore,
    webMcpApi,
    capability: "daily",
    unavailableMessage: "Unable to get the sales daily summary.",
  });

export const createGetSalesLeaderboardTool = ({ authStore, webMcpApi = adminWebMcpApi }) =>
  createReadTool({
    name: "get_sales_leaderboard",
    inputSchema: leaderboardInputSchema,
    normalizeInput: normalizeSalesLeaderboardInput,
    apiMethod: "getSalesLeaderboard",
    authStore,
    webMcpApi,
    capability: "leaderboard",
    unavailableMessage: "Unable to get the sales leaderboard.",
  });

export const createNavigateToSalesTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
  router,
}) => ({
  name: "navigate_to_sales",
  title: catalogEntry("navigate_to_sales")?.title,
  description:
    catalogEntry("navigate_to_sales")?.description ||
    "Navigate to a sales dashboard.",
  inputSchema: navigateInputSchema,
  annotations: { readOnlyHint: true, untrustedContentHint: true },
  execute: async (input = {}) => {
    requireCapability(authStore, "sales");
    const salesId = normalizePositiveInteger(input.salesId, "salesId", MAX_ID);
    rejectUnsupportedKeys(input, ["salesId"], "sales navigation");
    const result = await executeApi(
      () => webMcpApi.searchSales({ query: String(salesId), page: 1, limit: 25 }),
      "Sales user not found.",
      "Unable to find the sales user.",
    );
    const resolved = result?.sales?.find((candidate) => Number(candidate.id) === salesId);
    if (!resolved || !router?.push) {
      throw new Error("Sales user not found.");
    }
    try {
      await router.push({
        name: "sales-dashboard",
        query: { salesId: String(salesId) },
      });
    } catch {
      throw new Error("Unable to navigate to the sales dashboard.");
    }
    return {
      success: true,
      salesId,
      route: `/sales-dashboard?salesId=${salesId}`,
    };
  },
});

export const registerAdminSalesWebMcpTools = ({
  authStore,
  webMcpApi = adminWebMcpApi,
  router,
  modelContext =
    typeof document !== "undefined" ? document.modelContext : undefined,
} = {}) => {
  const access = capabilities(authStore);
  if (!modelContext?.registerTool || !authStore?.isAuthenticated || !access.sales) {
    return noop;
  }

  const controller = new AbortController();
  const tools = [
    createSearchSalesTool({ authStore, webMcpApi }),
    createGetSalesClientsTool({ authStore, webMcpApi }),
    createGetSalesPartnersTool({ authStore, webMcpApi }),
    createGetSalesPerformanceTool({ authStore, webMcpApi }),
    ...(access.daily
      ? [createGetSalesDailySummaryTool({ authStore, webMcpApi })]
      : []),
    ...(access.management && access.daily
      ? [createGetSalesLeaderboardTool({ authStore, webMcpApi })]
      : []),
    createNavigateToSalesTool({ authStore, webMcpApi, router }),
  ];

  try {
    tools.forEach((tool) => {
      Promise.resolve(
        modelContext.registerTool(tool, { signal: controller.signal }),
      ).catch((error) => {
        controller.abort();
        if (error?.name !== "AbortError") {
          console.warn("Admin sales WebMCP tool could not be registered.", error);
        }
      });
    });
  } catch (error) {
    controller.abort();
    console.warn("Admin sales WebMCP tool could not be registered.", error);
  }

  return () => controller.abort();
};
