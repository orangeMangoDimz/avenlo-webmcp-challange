import { adminWebMcpApi } from "@/services/adminWebMcpApi";
import { WEBMCP_TOOL_CATALOG } from "@/services/adminWebMcpCatalog";

const MAX_ID = 2147483647;
const MAX_DEPTH = 9999;
const IB_PERMISSION = "page_iblist_readonly";
const IB_LOOKUP_KEYS = ["email", "id", "code"];
const noop = () => {};

const ibLookupProperties = {
  email: {
    type: "string",
    maxLength: 254,
    description: "Exact client or IB contact email address.",
  },
  id: {
    type: "integer",
    minimum: 1,
    maximum: MAX_ID,
    description: "Exact numeric IB partner ID.",
  },
  code: {
    type: "string",
    maxLength: 64,
    description: "Exact approved IB code.",
  },
};

const ibLookupInputSchema = {
  type: "object",
  properties: ibLookupProperties,
  oneOf: IB_LOOKUP_KEYS.map((key) => ({ required: [key] })),
  additionalProperties: false,
};

const ibNetworkInputSchema = {
  ...ibLookupInputSchema,
  properties: {
    ...ibLookupProperties,
    maxDepth: {
      type: "integer",
      minimum: 1,
      maximum: MAX_DEPTH,
      description:
        "Maximum child-IB depth. Omit it to use the configured IB tier limit; use 1 for direct child IBs.",
    },
  },
};

const ibClientsInputSchema = {
  ...ibLookupInputSchema,
  required: ["relationship"],
  properties: {
    ...ibLookupProperties,
    relationship: {
      type: "string",
      enum: ["direct", "all"],
      description:
        "direct returns clients assigned to this IB; all includes clients assigned throughout its visible child-IB network.",
    },
    page: { type: "integer", minimum: 1, maximum: 1000 },
    limit: { type: "integer", minimum: 1, maximum: 50 },
  },
};

const clientUplineInputSchema = {
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
      maximum: MAX_ID,
      description: "Exact numeric client ID.",
    },
  },
  oneOf: [{ required: ["email"] }, { required: ["id"] }],
  additionalProperties: false,
};

const normalizeInteger = (value, name, maximum, defaultValue) => {
  if (value === undefined) return defaultValue;
  const number =
    typeof value === "string" && /^\d+$/.test(value.trim())
      ? Number(value.trim())
      : value;
  if (!Number.isSafeInteger(number) || number < 1 || number > maximum) {
    throw new Error(`${name} must be an integer between 1 and ${maximum}.`);
  }
  return number;
};

const normalizeEmail = (value) => {
  if (typeof value !== "string") {
    throw new Error("email must be a string.");
  }
  const email = value.trim();
  if (
    !email ||
    email.length > 254 ||
    !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
  ) {
    throw new Error("email must be a valid email address.");
  }
  return email;
};

const rejectUnsupportedKeys = (input, allowed, context) => {
  if (!input || typeof input !== "object" || Array.isArray(input)) {
    throw new Error("Input must be an object.");
  }
  const unsupportedKey = Object.keys(input).find(
    (key) => !allowed.includes(key),
  );
  if (unsupportedKey) {
    throw new Error(`${unsupportedKey} is not supported for ${context}.`);
  }
};

export const normalizeIbLookupInput = (input = {}) => {
  rejectUnsupportedKeys(input, IB_LOOKUP_KEYS, "an IB lookup");
  const provided = IB_LOOKUP_KEYS.filter((key) => Object.hasOwn(input, key));
  if (provided.length !== 1) {
    throw new Error("Exactly one of email, id, or code is required.");
  }
  const key = provided[0];
  if (key === "email") return { email: normalizeEmail(input.email) };
  if (key === "id") {
    return { id: normalizeInteger(input.id, "id", MAX_ID) };
  }
  if (typeof input.code !== "string") {
    throw new Error("code must be a string.");
  }
  const code = input.code.trim();
  if (!code || code.length > 64) {
    throw new Error("code must be between 1 and 64 characters.");
  }
  return { code };
};

export const normalizeIbNetworkInput = (input = {}) => {
  rejectUnsupportedKeys(
    input,
    [...IB_LOOKUP_KEYS, "maxDepth"],
    "an IB network lookup",
  );
  const normalized = normalizeIbLookupInput(
    Object.fromEntries(
      Object.entries(input).filter(([key]) => IB_LOOKUP_KEYS.includes(key)),
    ),
  );
  if (Object.hasOwn(input, "maxDepth")) {
    normalized.maxDepth = normalizeInteger(
      input.maxDepth,
      "maxDepth",
      MAX_DEPTH,
    );
  }
  return normalized;
};

export const normalizeIbClientsInput = (input = {}) => {
  rejectUnsupportedKeys(
    input,
    [...IB_LOOKUP_KEYS, "relationship", "page", "limit"],
    "an IB clients lookup",
  );
  const normalized = normalizeIbLookupInput(
    Object.fromEntries(
      Object.entries(input).filter(([key]) => IB_LOOKUP_KEYS.includes(key)),
    ),
  );
  if (typeof input.relationship !== "string") {
    throw new Error("relationship is required and must be direct or all.");
  }
  normalized.relationship = input.relationship.trim().toLowerCase();
  if (!["direct", "all"].includes(normalized.relationship)) {
    throw new Error("relationship must be direct or all.");
  }
  normalized.page = normalizeInteger(input.page, "page", 1000, 1);
  normalized.limit = normalizeInteger(input.limit, "limit", 50, 25);
  return normalized;
};

export const normalizeClientUplineInput = (input = {}) => {
  rejectUnsupportedKeys(input, ["email", "id"], "a client upline lookup");
  const provided = ["email", "id"].filter((key) => Object.hasOwn(input, key));
  if (provided.length !== 1) {
    throw new Error("Exactly one of email or id is required.");
  }
  return provided[0] === "email"
    ? { email: normalizeEmail(input.email) }
    : { id: normalizeInteger(input.id, "id", MAX_ID) };
};

const requirePermission = (authStore) => {
  if (
    !authStore?.isAuthenticated ||
    !authStore?.hasPermission?.(IB_PERMISSION)
  ) {
    throw new Error("You are not authorized to use this tool.");
  }
};

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

const catalogEntry = (name) =>
  WEBMCP_TOOL_CATALOG.find((tool) => tool.name === name);

const createReadTool = ({
  name,
  inputSchema,
  normalizeInput,
  apiMethod,
  authStore,
  webMcpApi,
  notFoundMessage,
  unavailableMessage,
}) => ({
  name,
  title: catalogEntry(name)?.title,
  description: catalogEntry(name)?.description || name,
  inputSchema,
  annotations: {
    readOnlyHint: true,
    untrustedContentHint: true,
  },
  execute: async (input = {}) => {
    requirePermission(authStore);
    const normalized = normalizeInput(input);
    return executeApi(
      () => webMcpApi[apiMethod](normalized),
      notFoundMessage,
      unavailableMessage,
    );
  },
});

export const createGetIbPartnerTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
}) =>
  createReadTool({
    name: "get_ib_partner",
    inputSchema: ibLookupInputSchema,
    normalizeInput: normalizeIbLookupInput,
    apiMethod: "getIbPartner",
    authStore,
    webMcpApi,
    notFoundMessage: "IB partner not found.",
    unavailableMessage: "Unable to get the IB partner.",
  });

export const createGetIbNetworkTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
}) =>
  createReadTool({
    name: "get_ib_network",
    inputSchema: ibNetworkInputSchema,
    normalizeInput: normalizeIbNetworkInput,
    apiMethod: "getIbNetwork",
    authStore,
    webMcpApi,
    notFoundMessage: "IB partner not found.",
    unavailableMessage: "Unable to get the IB network.",
  });

export const createGetIbNetworkStatsTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
}) =>
  createReadTool({
    name: "get_ib_network_stats",
    inputSchema: ibLookupInputSchema,
    normalizeInput: normalizeIbLookupInput,
    apiMethod: "getIbNetworkStats",
    authStore,
    webMcpApi,
    notFoundMessage: "IB partner not found.",
    unavailableMessage: "Unable to get IB network statistics.",
  });

export const createGetIbClientsTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
}) =>
  createReadTool({
    name: "get_ib_clients",
    inputSchema: ibClientsInputSchema,
    normalizeInput: normalizeIbClientsInput,
    apiMethod: "getIbClients",
    authStore,
    webMcpApi,
    notFoundMessage: "IB partner not found.",
    unavailableMessage: "Unable to get IB clients.",
  });

export const createGetClientIbUplineTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
}) =>
  createReadTool({
    name: "get_client_ib_upline",
    inputSchema: clientUplineInputSchema,
    normalizeInput: normalizeClientUplineInput,
    apiMethod: "getClientIbUpline",
    authStore,
    webMcpApi,
    notFoundMessage: "Client not found.",
    unavailableMessage: "Unable to get the client's IB upline.",
  });

export const createNavigateToIbTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
  router,
}) => ({
  name: "navigate_to_ib",
  title: catalogEntry("navigate_to_ib")?.title,
  description:
    catalogEntry("navigate_to_ib")?.description || "Navigate to an IB.",
  inputSchema: ibLookupInputSchema,
  annotations: {
    readOnlyHint: true,
    untrustedContentHint: true,
  },
  execute: async (input = {}) => {
    requirePermission(authStore);
    const normalized = normalizeIbLookupInput(input);
    const response = await executeApi(
      () => webMcpApi.getIbPartner(normalized),
      "IB partner not found.",
      "Unable to get the IB partner.",
    );
    const ib = response?.ib ?? response;
    const ibId = Number(ib?.id);
    const search = String(ib?.code || ib?.email || ibId);
    if (!Number.isSafeInteger(ibId) || ibId < 1 || !router?.push) {
      throw new Error("Unable to navigate to the IB partner.");
    }
    try {
      await router.push({
        name: "ib-list",
        query: { search, detailId: String(ibId) },
      });
    } catch {
      throw new Error("Unable to navigate to the IB partner.");
    }
    return {
      success: true,
      ibId,
      route: `/ib-list?search=${encodeURIComponent(search)}&detailId=${ibId}`,
    };
  },
});

export const registerAdminIbWebMcpTools = ({
  authStore,
  webMcpApi = adminWebMcpApi,
  router,
  modelContext = typeof document !== "undefined"
    ? document.modelContext
    : undefined,
} = {}) => {
  if (
    !modelContext?.registerTool ||
    !authStore?.isAuthenticated ||
    !authStore?.hasPermission?.(IB_PERMISSION)
  ) {
    return noop;
  }

  const controller = new AbortController();
  const tools = [
    createGetIbPartnerTool({ authStore, webMcpApi }),
    createGetIbNetworkTool({ authStore, webMcpApi }),
    createGetIbNetworkStatsTool({ authStore, webMcpApi }),
    createGetIbClientsTool({ authStore, webMcpApi }),
    createGetClientIbUplineTool({ authStore, webMcpApi }),
    createNavigateToIbTool({ authStore, webMcpApi, router }),
  ];

  try {
    tools.forEach((tool) => {
      Promise.resolve(
        modelContext.registerTool(tool, { signal: controller.signal }),
      ).catch((error) => {
        controller.abort();
        if (error?.name !== "AbortError") {
          console.warn("Admin IB WebMCP tool could not be registered.", error);
        }
      });
    });
  } catch (error) {
    controller.abort();
    console.warn("Admin IB WebMCP tool could not be registered.", error);
  }

  return () => controller.abort();
};
