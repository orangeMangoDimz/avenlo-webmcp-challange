import { adminWebMcpApi } from "@/services/adminWebMcpApi";
import { WEBMCP_TOOL_CATALOG } from "@/services/adminWebMcpCatalog";

const MAX_CLIENT_ID = 2147483647;
const MAX_EXPORT_CLIENTS = 500;
const LOOKUP_KEYS = ["email", "id", "code"];
const noop = () => {};

const clientLookupInputSchema = {
  type: "object",
  properties: {
    email: {
      type: "string",
      maxLength: 254,
      description: "Exact client email address.",
    },
    id: {
      type: "integer",
      minimum: 1,
      maximum: MAX_CLIENT_ID,
      description: "Exact numeric client ID.",
    },
    code: {
      type: "string",
      maxLength: 64,
      description: "Exact approved IB code for a client who is an IB.",
    },
  },
  oneOf: [
    { required: ["email"] },
    { required: ["id"] },
    { required: ["code"] },
  ],
  additionalProperties: false,
};

const searchClientsInputSchema = {
  type: "object",
  properties: {
    country: {
      type: "string",
      maxLength: 100,
      description:
        "Exact country code or country name, such as ID or Indonesia.",
    },
    tag: {
      type: "string",
      maxLength: 100,
      description: "Exact client tag name, such as VIP.",
    },
    neverLoggedIn: {
      type: "boolean",
      description:
        "When true, return clients whose last login is not recorded.",
    },
    kycStatus: {
      type: "string",
      maxLength: 50,
      description: "Exact client KYC status.",
    },
    status: {
      type: "string",
      maxLength: 50,
      description: "Exact client account status.",
    },
    search: {
      type: "string",
      maxLength: 100,
      description:
        "Search client name, email, phone, or trading account identifier.",
    },
    page: {
      type: "integer",
      minimum: 1,
      maximum: 1000,
    },
    limit: {
      type: "integer",
      minimum: 1,
      maximum: 50,
    },
  },
  anyOf: [
    { required: ["country"] },
    { required: ["tag"] },
    { required: ["neverLoggedIn"] },
    { required: ["kycStatus"] },
    { required: ["status"] },
    { required: ["search"] },
  ],
  additionalProperties: false,
};

const recentTransactionsInputSchema = {
  type: "object",
  properties: {
    ...clientLookupInputSchema.properties,
    type: {
      type: "string",
      enum: ["all", "deposit", "withdrawal", "internal_transfer", "credit"],
      description: "Funding transaction type; defaults to all.",
    },
    page: {
      type: "integer",
      minimum: 1,
      maximum: 1000,
    },
    limit: {
      type: "integer",
      minimum: 1,
      maximum: 50,
    },
  },
  oneOf: clientLookupInputSchema.oneOf,
  additionalProperties: false,
};

const exportClientInputSchema = {
  type: "object",
  required: ["clientIds"],
  properties: {
    clientIds: {
      type: "array",
      minItems: 1,
      maxItems: MAX_EXPORT_CLIENTS,
      uniqueItems: true,
      items: {
        type: "integer",
        minimum: 1,
        maximum: MAX_CLIENT_ID,
      },
      description: "Selected client IDs to export.",
    },
    country: {
      type: "string",
      maxLength: 100,
      description: "Exact country code or country name.",
    },
    tag: {
      type: "string",
      maxLength: 100,
      description: "Exact client tag name.",
    },
    neverLoggedIn: {
      type: "boolean",
      description: "When true, include only clients without a recorded login.",
    },
    kycStatus: {
      type: "string",
      maxLength: 50,
      description: "Exact client KYC status.",
    },
    status: {
      type: "string",
      maxLength: 50,
      description: "Exact client account status.",
    },
    search: {
      type: "string",
      maxLength: 100,
      description: "Client name, email, phone, or country search text.",
    },
    registeredFrom: {
      type: "string",
      pattern: "^\\d{4}-\\d{2}-\\d{2}$",
      description: "Inclusive registration date lower bound.",
    },
    registeredTo: {
      type: "string",
      pattern: "^\\d{4}-\\d{2}-\\d{2}$",
      description: "Inclusive registration date upper bound.",
    },
  },
  additionalProperties: false,
};

const exportClientTransactionsInputSchema = {
  type: "object",
  required: ["clientIds"],
  properties: {
    clientIds: exportClientInputSchema.properties.clientIds,
    dateFrom: {
      type: "string",
      pattern: "^\\d{4}-\\d{2}-\\d{2}$",
      description: "Inclusive transaction date lower bound.",
    },
    dateTo: {
      type: "string",
      pattern: "^\\d{4}-\\d{2}-\\d{2}$",
      description: "Inclusive transaction date upper bound.",
    },
    type: {
      type: "string",
      enum: ["all", "deposit", "withdrawal", "internal_transfer", "credit"],
      description: "Transaction type; defaults to all.",
    },
    status: {
      type: "string",
      maxLength: 50,
      description: "Exact transaction status.",
    },
  },
  additionalProperties: false,
};

const CLIENT_LOOKUP_PERMISSIONS = [
  "page_clientslist_readonly",
  "page_clientsdetail_profile",
];
const SEARCH_CLIENTS_PERMISSIONS = ["page_clientslist_readonly"];
const DOCUMENTS_PERMISSIONS = ["page_clientsdetail_document"];
const TRADING_ACCOUNTS_PERMISSIONS = ["page_clientsdetail_trading"];
const TRANSACTIONS_PERMISSIONS = ["page_clientsdetail_funding"];
const CLIENT_EXPORT_PERMISSIONS = ["page_clientslist_export"];
const TRANSACTION_EXPORT_PERMISSIONS = ["page_fundingreport_export"];

const hasAnyPermission = (authStore, permissionKeys) =>
  permissionKeys.some((permissionKey) =>
    authStore?.hasPermission?.(permissionKey),
  );

const requirePermission = (
  authStore,
  permissionKeys = CLIENT_LOOKUP_PERMISSIONS,
) => {
  if (
    !authStore?.isAuthenticated ||
    !hasAnyPermission(authStore, permissionKeys)
  ) {
    throw new Error("You are not authorized to use this tool.");
  }
};

const resolveClient = async (
  { authStore, webMcpApi, permissionKeys = CLIENT_LOOKUP_PERMISSIONS },
  input,
) => {
  requirePermission(authStore, permissionKeys);
  const lookup = normalizeLookupInput(input);

  try {
    return await webMcpApi.getClient(lookup);
  } catch (error) {
    if (
      error?.statusCode === 404 ||
      error?.response?.data?.statusCode === 404
    ) {
      throw new Error("Client not found.");
    }
    throw new Error("Unable to get client.");
  }
};

const normalizeIntegerOption = (value, name, defaultValue, maximum) => {
  if (value === undefined) return defaultValue;

  const parsed =
    typeof value === "string" && /^\d+$/.test(value.trim())
      ? Number(value.trim())
      : Number(value);
  if (!Number.isSafeInteger(parsed) || parsed < 1 || parsed > maximum) {
    throw new Error(`${name} must be an integer between 1 and ${maximum}.`);
  }
  return parsed;
};

const normalizeStringFilter = (input, key, maximum) => {
  if (typeof input[key] !== "string") {
    throw new Error(`${key} must be a string.`);
  }

  const value = input[key].trim();
  if (!value || value.length > maximum) {
    throw new Error(`${key} must be between 1 and ${maximum} characters.`);
  }
  return value;
};

const normalizeExportClientIds = (value) => {
  if (!Array.isArray(value) || value.length === 0) {
    throw new Error(
      `clientIds must contain between 1 and ${MAX_EXPORT_CLIENTS} client IDs.`,
    );
  }
  if (value.length > MAX_EXPORT_CLIENTS) {
    throw new Error(
      `clientIds cannot contain more than ${MAX_EXPORT_CLIENTS} client IDs.`,
    );
  }

  const seen = new Set();
  return value.map((value) => {
    const id = normalizeIntegerOption(value, "clientIds", null, MAX_CLIENT_ID);
    if (seen.has(id)) {
      throw new Error("clientIds must not contain duplicate IDs.");
    }
    seen.add(id);
    return id;
  });
};

const normalizeExportDate = (input, key) => {
  if (typeof input[key] !== "string") {
    throw new Error(`${key} must use YYYY-MM-DD format.`);
  }
  const value = input[key].trim();
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    throw new Error(`${key} must use YYYY-MM-DD format.`);
  }
  const date = new Date(`${value}T00:00:00.000Z`);
  if (
    Number.isNaN(date.getTime()) ||
    date.toISOString().slice(0, 10) !== value
  ) {
    throw new Error(`${key} must use a valid calendar date.`);
  }
  return value;
};

const normalizeExportDateRange = (normalized, fromKey, toKey) => {
  if (
    normalized[fromKey] &&
    normalized[toKey] &&
    normalized[fromKey] > normalized[toKey]
  ) {
    throw new Error(`${fromKey} cannot be after ${toKey}.`);
  }
};

export const normalizeExportClientInput = (input = {}) => {
  if (!input || typeof input !== "object" || Array.isArray(input)) {
    throw new Error("Input must be an object.");
  }

  const normalized = { clientIds: normalizeExportClientIds(input.clientIds) };
  for (const [key, maximum] of [
    ["country", 100],
    ["tag", 100],
    ["kycStatus", 50],
    ["status", 50],
    ["search", 100],
  ]) {
    if (Object.prototype.hasOwnProperty.call(input, key)) {
      normalized[key] = normalizeStringFilter(input, key, maximum);
    }
  }
  if (Object.prototype.hasOwnProperty.call(input, "neverLoggedIn")) {
    if (typeof input.neverLoggedIn !== "boolean") {
      throw new Error("neverLoggedIn must be a boolean.");
    }
    normalized.neverLoggedIn = input.neverLoggedIn;
  }
  for (const key of ["registeredFrom", "registeredTo"]) {
    if (Object.prototype.hasOwnProperty.call(input, key)) {
      normalized[key] = normalizeExportDate(input, key);
    }
  }
  normalizeExportDateRange(normalized, "registeredFrom", "registeredTo");
  return normalized;
};

export const normalizeExportTransactionInput = (input = {}) => {
  if (!input || typeof input !== "object" || Array.isArray(input)) {
    throw new Error("Input must be an object.");
  }

  const normalized = { clientIds: normalizeExportClientIds(input.clientIds) };
  for (const key of ["dateFrom", "dateTo"]) {
    if (Object.prototype.hasOwnProperty.call(input, key)) {
      normalized[key] = normalizeExportDate(input, key);
    }
  }
  normalizeExportDateRange(normalized, "dateFrom", "dateTo");

  const aliases = {
    deposits: "deposit",
    withdrawals: "withdrawal",
    "internal-transfer": "internal_transfer",
    "internal-transfers": "internal_transfer",
    internaltransfer: "internal_transfer",
    internaltransfers: "internal_transfer",
  };
  const type = String(input.type ?? "all")
    .trim()
    .toLowerCase();
  normalized.type = aliases[type] || type;
  if (
    !["all", "deposit", "withdrawal", "internal_transfer", "credit"].includes(
      normalized.type,
    )
  ) {
    throw new Error(
      "type must be one of: all, deposit, withdrawal, internal_transfer, credit.",
    );
  }
  if (Object.prototype.hasOwnProperty.call(input, "status")) {
    normalized.status = normalizeStringFilter(input, "status", 50);
  }
  return normalized;
};

export const normalizeSearchInput = (input = {}) => {
  if (!input || typeof input !== "object" || Array.isArray(input)) {
    throw new Error("Input must be an object.");
  }

  const filterKeys = [
    "country",
    "tag",
    "neverLoggedIn",
    "kycStatus",
    "status",
    "search",
  ];
  if (
    !filterKeys.some((key) => Object.prototype.hasOwnProperty.call(input, key))
  ) {
    throw new Error(
      "At least one search filter is required: country, tag, neverLoggedIn, kycStatus, status, or search.",
    );
  }

  const normalized = {};
  if (Object.prototype.hasOwnProperty.call(input, "country")) {
    normalized.country = normalizeStringFilter(input, "country", 100);
  }
  if (Object.prototype.hasOwnProperty.call(input, "tag")) {
    normalized.tag = normalizeStringFilter(input, "tag", 100);
  }
  if (Object.prototype.hasOwnProperty.call(input, "neverLoggedIn")) {
    if (typeof input.neverLoggedIn !== "boolean") {
      throw new Error("neverLoggedIn must be a boolean.");
    }
    normalized.neverLoggedIn = input.neverLoggedIn;
  }
  if (Object.prototype.hasOwnProperty.call(input, "kycStatus")) {
    normalized.kycStatus = normalizeStringFilter(input, "kycStatus", 50);
  }
  if (Object.prototype.hasOwnProperty.call(input, "status")) {
    normalized.status = normalizeStringFilter(input, "status", 50);
  }
  if (Object.prototype.hasOwnProperty.call(input, "search")) {
    normalized.search = normalizeStringFilter(input, "search", 100);
  }

  normalized.page = normalizeIntegerOption(input.page, "page", 1, 1000);
  normalized.limit = normalizeIntegerOption(input.limit, "limit", 25, 50);
  return normalized;
};

export const normalizeTransactionInput = (input = {}) => {
  if (!input || typeof input !== "object" || Array.isArray(input)) {
    throw new Error("Input must be an object.");
  }

  const lookup = normalizeLookupInput(input);
  const allowedTypes = [
    "all",
    "deposit",
    "withdrawal",
    "internal_transfer",
    "credit",
  ];
  const type = input.type === undefined ? "all" : String(input.type).trim();
  if (!allowedTypes.includes(type)) {
    throw new Error(
      "type must be one of: all, deposit, withdrawal, internal_transfer, credit.",
    );
  }

  return {
    ...lookup,
    type,
    page: normalizeIntegerOption(input.page, "page", 1, 1000),
    limit: normalizeIntegerOption(input.limit, "limit", 10, 50),
  };
};

export const normalizeLookupInput = (input = {}) => {
  if (!input || typeof input !== "object" || Array.isArray(input)) {
    throw new Error("Input must be an object.");
  }

  const providedKeys = LOOKUP_KEYS.filter((key) =>
    Object.prototype.hasOwnProperty.call(input, key),
  );
  if (providedKeys.length !== 1) {
    throw new Error("Exactly one of email, id, or code is required.");
  }

  const [key] = providedKeys;
  if (key === "email") {
    if (typeof input.email !== "string") {
      throw new Error("email must be a string.");
    }

    const email = input.email.trim();
    if (!email || email.length > 254 || !/^\S+@\S+\.\S+$/.test(email)) {
      throw new Error(
        "email must be a valid email address of 254 characters or fewer.",
      );
    }
    return { email };
  }

  if (key === "code") {
    if (typeof input.code !== "string") {
      throw new Error("code must be a string.");
    }

    const code = input.code.trim();
    if (!code || code.length > 64) {
      throw new Error("code must be between 1 and 64 characters.");
    }
    return { code };
  }

  const id =
    typeof input.id === "string" && /^\d+$/.test(input.id.trim())
      ? Number(input.id.trim())
      : Number(input.id);
  if (!Number.isSafeInteger(id) || id < 1 || id > MAX_CLIENT_ID) {
    throw new Error(`id must be an integer between 1 and ${MAX_CLIENT_ID}.`);
  }
  return { id };
};

const clientName = (client) =>
  [client.firstName, client.lastName].filter(Boolean).join(" ").trim() ||
  client.name ||
  client.email ||
  `Client ${client.id}`;

export const projectClient = (client = {}) => {
  const ibCode =
    typeof client.ibCode === "string" && client.ibCode.trim() !== ""
      ? client.ibCode.trim()
      : null;

  return {
    id: Number(client.id),
    name: clientName(client),
    email: client.email || null,
    country: client.country || null,
    status: client.status || null,
    kycStatus: client.kycStatus || client.latestKycStatus || null,
    manager:
      client.manager || client.managerName || client.managerEmail || null,
    registeredAt: client.createdAt || null,
    lastLoginAt: client.lastLoginAt || null,
    isIb: Boolean(ibCode),
    ibCode,
  };
};

export const createGetClientTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
}) => ({
  name: "get_client",
  title: "Get client",
  description:
    "Retrieve one client visible to the signed-in administrator by email, client ID, or exact approved IB code.",
  inputSchema: clientLookupInputSchema,
  annotations: {
    readOnlyHint: true,
    untrustedContentHint: true,
  },
  execute: async (input = {}) => {
    const client = await resolveClient(
      { authStore, webMcpApi, permissionKeys: CLIENT_LOOKUP_PERMISSIONS },
      input,
    );
    return { client: projectClient(client) };
  },
});

export const createNavigateToClientTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
  router,
}) => ({
  name: "navigate_to_client",
  title: "Navigate to client",
  description:
    "Open one client visible to the signed-in administrator in the admin dashboard by email, client ID, or exact approved IB code.",
  inputSchema: clientLookupInputSchema,
  annotations: {
    readOnlyHint: true,
    untrustedContentHint: true,
  },
  execute: async (input = {}) => {
    const client = await resolveClient(
      { authStore, webMcpApi, permissionKeys: CLIENT_LOOKUP_PERMISSIONS },
      input,
    );
    const clientId = Number(client.id);

    if (!router?.push) {
      throw new Error("Unable to navigate to client.");
    }

    try {
      await router.push({
        name: "client-detail",
        query: { id: clientId },
      });
    } catch {
      throw new Error("Unable to navigate to client.");
    }

    return {
      success: true,
      clientId,
      route: `/client-detail?id=${clientId}`,
    };
  },
});

const requestClientData = async ({
  authStore,
  permissionKeys,
  input,
  request,
  notFoundMessage,
  errorMessage,
}) => {
  requirePermission(authStore, permissionKeys);

  try {
    return await request(input);
  } catch (error) {
    if (
      error?.statusCode === 404 ||
      error?.response?.data?.statusCode === 404
    ) {
      throw new Error(notFoundMessage);
    }
    throw new Error(errorMessage);
  }
};

export const createSearchClientsTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
}) => ({
  name: "search_clients",
  title: "Search clients",
  description:
    "Find clients visible to the signed-in administrator using filters such as country, tag, KYC status, account status, or login history.",
  inputSchema: searchClientsInputSchema,
  annotations: {
    readOnlyHint: true,
    untrustedContentHint: true,
  },
  execute: async (input = {}) => {
    const filters = normalizeSearchInput(input);
    return requestClientData({
      authStore,
      permissionKeys: SEARCH_CLIENTS_PERMISSIONS,
      input: filters,
      request: (requestInput) => webMcpApi.searchClients(requestInput),
      notFoundMessage: "No clients found.",
      errorMessage: "Unable to search clients.",
    });
  },
});

export const createGetClientDocumentsTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
}) => ({
  name: "get_client_documents",
  title: "Get client documents",
  description:
    "Retrieve safe metadata for one visible client's registration, KYC, and approved IB documents by email, client ID, or exact approved IB code.",
  inputSchema: clientLookupInputSchema,
  annotations: {
    readOnlyHint: true,
    untrustedContentHint: true,
  },
  execute: async (input = {}) => {
    const lookup = normalizeLookupInput(input);
    return requestClientData({
      authStore,
      permissionKeys: DOCUMENTS_PERMISSIONS,
      input: lookup,
      request: (requestInput) => webMcpApi.getClientDocuments(requestInput),
      notFoundMessage: "Client not found.",
      errorMessage: "Unable to get client documents.",
    });
  },
});

export const createGetClientTradingAccountsTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
}) => ({
  name: "get_client_trading_accounts",
  title: "Get client trading accounts",
  description:
    "Retrieve one visible client's trading accounts, platform, status, currency, balance, and equity by email, client ID, or exact approved IB code.",
  inputSchema: clientLookupInputSchema,
  annotations: {
    readOnlyHint: true,
    untrustedContentHint: true,
  },
  execute: async (input = {}) => {
    const lookup = normalizeLookupInput(input);
    return requestClientData({
      authStore,
      permissionKeys: TRADING_ACCOUNTS_PERMISSIONS,
      input: lookup,
      request: (requestInput) =>
        webMcpApi.getClientTradingAccounts(requestInput),
      notFoundMessage: "Client not found.",
      errorMessage: "Unable to get client trading accounts.",
    });
  },
});

export const createGetClientRecentTransactionsTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
}) => ({
  name: "get_client_recent_transactions",
  title: "Get client recent transactions",
  description:
    "Retrieve one visible client's recent funding transactions, including deposits, withdrawals, internal transfers, or credit activity.",
  inputSchema: recentTransactionsInputSchema,
  annotations: {
    readOnlyHint: true,
    untrustedContentHint: true,
  },
  execute: async (input = {}) => {
    const transactionInput = normalizeTransactionInput(input);
    return requestClientData({
      authStore,
      permissionKeys: TRANSACTIONS_PERMISSIONS,
      input: transactionInput,
      request: (requestInput) =>
        webMcpApi.getClientRecentTransactions(requestInput),
      notFoundMessage: "Client not found.",
      errorMessage: "Unable to get client recent transactions.",
    });
  },
});

const getExportProgressUrl = (router, jobId) => {
  const resolved = router?.resolve?.({
    name: "webmcp-export-progress",
    query: { jobId },
  });
  if (resolved?.href) return resolved.href;
  return `/#/webmcp/export-progress?jobId=${encodeURIComponent(jobId)}`;
};

const openExportProgress = (router, jobId) => {
  const progressUrl = getExportProgressUrl(router, jobId);
  let opened = false;
  if (typeof window !== "undefined" && typeof window.open === "function") {
    try {
      opened = Boolean(window.open(progressUrl, "_blank", "noopener"));
    } catch {
      opened = false;
    }
  }
  return { progressUrl, opened };
};

const createExportTool = ({
  authStore,
  webMcpApi,
  router,
  inputSchema,
  normalizeInput,
  permissionKeys,
  name,
  title,
  description,
  request,
  errorMessage,
}) => ({
  name,
  title,
  description,
  inputSchema,
  annotations: {
    readOnlyHint: true,
    untrustedContentHint: true,
  },
  execute: async (input = {}) => {
    requirePermission(authStore, permissionKeys);
    const normalized = normalizeInput(input);
    let exportJob;
    try {
      exportJob = await request(webMcpApi, normalized);
    } catch {
      throw new Error(errorMessage);
    }
    const jobId = String(exportJob?.jobId || "").trim();
    if (!jobId) {
      throw new Error(errorMessage);
    }
    const { progressUrl, opened } = openExportProgress(router, jobId);
    return {
      success: true,
      jobId,
      progressUrl,
      opened,
      queued: Boolean(exportJob?.queued),
    };
  },
});

export const createExportClientsTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
  router,
}) =>
  createExportTool({
    authStore,
    webMcpApi,
    router,
    inputSchema: exportClientInputSchema,
    normalizeInput: normalizeExportClientInput,
    permissionKeys: CLIENT_EXPORT_PERMISSIONS,
    name: "export_clients",
    title: "Export clients",
    description:
      "Start an Excel export for selected clients visible to the signed-in administrator. The browser opens a progress page and downloads the file when ready.",
    request: (api, input) => api.exportClients(input),
    errorMessage: "Unable to export clients.",
  });

export const createExportClientTransactionsTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
  router,
}) =>
  createExportTool({
    authStore,
    webMcpApi,
    router,
    inputSchema: exportClientTransactionsInputSchema,
    normalizeInput: normalizeExportTransactionInput,
    permissionKeys: TRANSACTION_EXPORT_PERMISSIONS,
    name: "export_client_transactions",
    title: "Export client transactions",
    description:
      "Start an Excel export for selected clients' funding transactions with optional date, type, and status filters. The browser opens a progress page and downloads the file when ready.",
    request: (api, input) => api.exportClientTransactions(input),
    errorMessage: "Unable to export client transactions.",
  });

export const registerAdminClientWebMcpTools = ({
  authStore,
  webMcpApi = adminWebMcpApi,
  router,
}) => {
  const modelContext =
    typeof document === "undefined" ? null : document.modelContext;
  if (!modelContext?.registerTool || !authStore?.isAuthenticated) {
    return noop;
  }

  const controller = new AbortController();
  const toolDefinitions = [
    {
      catalog: WEBMCP_TOOL_CATALOG[0],
      create: () => createGetClientTool({ authStore, webMcpApi }),
    },
    {
      catalog: WEBMCP_TOOL_CATALOG[1],
      create: () =>
        createNavigateToClientTool({ authStore, webMcpApi, router }),
    },
    {
      catalog: WEBMCP_TOOL_CATALOG[2],
      create: () => createSearchClientsTool({ authStore, webMcpApi }),
    },
    {
      catalog: WEBMCP_TOOL_CATALOG[3],
      create: () => createGetClientDocumentsTool({ authStore, webMcpApi }),
    },
    {
      catalog: WEBMCP_TOOL_CATALOG[4],
      create: () =>
        createGetClientTradingAccountsTool({ authStore, webMcpApi }),
    },
    {
      catalog: WEBMCP_TOOL_CATALOG[5],
      create: () =>
        createGetClientRecentTransactionsTool({ authStore, webMcpApi }),
    },
    {
      catalog: WEBMCP_TOOL_CATALOG[6],
      create: () => createExportClientsTool({ authStore, webMcpApi, router }),
    },
    {
      catalog: WEBMCP_TOOL_CATALOG[7],
      create: () =>
        createExportClientTransactionsTool({ authStore, webMcpApi, router }),
    },
  ];
  const tools = toolDefinitions
    .filter(({ catalog }) =>
      hasAnyPermission(authStore, catalog.permissionKeys),
    )
    .map(({ create }) => create());

  if (tools.length === 0) {
    return noop;
  }

  try {
    tools.forEach((tool) => {
      Promise.resolve(
        modelContext.registerTool(tool, { signal: controller.signal }),
      ).catch((error) => {
        controller.abort();
        if (error?.name !== "AbortError") {
          console.warn(
            "Admin client WebMCP tool could not be registered.",
            error,
          );
        }
      });
    });
  } catch (error) {
    controller.abort();
    console.warn("Admin client WebMCP tool could not be registered.", error);
  }

  return () => controller.abort();
};
