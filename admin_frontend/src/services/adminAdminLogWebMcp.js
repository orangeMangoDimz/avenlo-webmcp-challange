import { adminWebMcpApi } from "@/services/adminWebMcpApi";
import { WEBMCP_TOOL_CATALOG } from "@/services/adminWebMcpCatalog";
import {
  buildOperationLogRouteQuery,
  isRegisteredOperationLogModule,
} from "@/services/operationLogWebMcpNavigation";
import { recordWebMcpActivity } from "@/services/adminWebMcpOperations";

const MAX_ID = 2147483647;
const MAX_PAGE = 1000;
const MAX_LIMIT = 50;
const noop = () => {};

const ACCOUNT_PERMISSIONS = [
  "page_accountmanagement_readonly",
  "page_accountmanagement_edit",
];
const ROLE_PERMISSIONS = [
  "page_rolemanagement_readonly",
  "page_rolemanagement_edit",
];
const LOG_READ_PERMISSIONS = ["page_operationlogreport_readonly"];
const LOG_EXPORT_PERMISSIONS = ["page_operationlogreport_export"];

const positiveIdProperty = (description) => ({
  type: "integer",
  minimum: 1,
  maximum: MAX_ID,
  description,
});

const adminUserSearchInputSchema = {
  type: "object",
  properties: {
    query: {
      type: "string",
      minLength: 1,
      maxLength: 100,
      description: "Administrator name, username, email, or exact numeric ID.",
    },
    status: { type: "string", enum: ["active", "inactive"] },
    roleId: positiveIdProperty("Exact administrator role ID."),
    page: { type: "integer", minimum: 1, maximum: MAX_PAGE },
    limit: { type: "integer", minimum: 1, maximum: MAX_LIMIT },
  },
  anyOf: [
    { required: ["query"] },
    { required: ["status"] },
    { required: ["roleId"] },
  ],
  additionalProperties: false,
};

const adminUserInputSchema = {
  type: "object",
  required: ["adminUserId"],
  properties: {
    adminUserId: positiveIdProperty("Exact administrator ID."),
  },
  additionalProperties: false,
};

const roleInputSchema = {
  type: "object",
  properties: {
    roleId: positiveIdProperty("Exact administrator role ID."),
    roleName: {
      type: "string",
      minLength: 1,
      maxLength: 100,
      description: "Exact role name, such as Operations.",
    },
  },
  oneOf: [{ required: ["roleId"] }, { required: ["roleName"] }],
  additionalProperties: false,
};

const permissionKeyProperty = {
  type: "string",
  minLength: 1,
  maxLength: 120,
  pattern: "^[A-Za-z0-9_.-]+$",
  description: "Exact active permission key, such as page_withdraw_approve.",
};

const permissionInputSchema = {
  type: "object",
  required: ["permissionKey"],
  properties: {
    permissionKey: permissionKeyProperty,
    includeInactive: {
      type: "boolean",
      description: "Include inactive roles. Defaults to false.",
    },
  },
  additionalProperties: false,
};

const adminPermissionInputSchema = {
  type: "object",
  required: ["adminUserId", "permissionKey"],
  properties: {
    adminUserId: positiveIdProperty("Exact administrator ID."),
    permissionKey: permissionKeyProperty,
  },
  additionalProperties: false,
};

const operationLogProperties = {
  operatorId: positiveIdProperty(
    "Exact administrator who performed the action.",
  ),
  module: {
    type: "string",
    minLength: 1,
    maxLength: 80,
    description:
      "Exact audit submodule key, such as role_management, accounts, or withdrawals.",
  },
  operationType: {
    type: "string",
    minLength: 1,
    maxLength: 80,
    description:
      "Exact operation type key, such as add, edit, delete, or export.",
  },
  targetType: {
    type: "string",
    enum: ["client", "admin_user", "admin_role", "points_mall_product"],
  },
  targetId: positiveIdProperty("Exact target ID. targetType is also required."),
  startDate: { type: "string", format: "date" },
  endDate: { type: "string", format: "date" },
  query: {
    type: "string",
    minLength: 1,
    maxLength: 200,
    description: "Text found in operator, module, target ID, or audit detail.",
  },
};

const operationLogFilterRequirements = Object.keys(operationLogProperties).map(
  (key) => ({ required: [key] }),
);

const operationLogSearchInputSchema = {
  type: "object",
  properties: {
    ...operationLogProperties,
    page: { type: "integer", minimum: 1, maximum: MAX_PAGE },
    limit: { type: "integer", minimum: 1, maximum: MAX_LIMIT },
  },
  anyOf: operationLogFilterRequirements,
  dependentRequired: { targetId: ["targetType"] },
  additionalProperties: false,
};

const operationLogFilterInputSchema = {
  type: "object",
  properties: operationLogProperties,
  anyOf: operationLogFilterRequirements,
  dependentRequired: { targetId: ["targetType"] },
  additionalProperties: false,
};

const operationLogIdInputSchema = {
  type: "object",
  required: ["operationLogId"],
  properties: {
    operationLogId: positiveIdProperty("Exact operation-log ID."),
  },
  additionalProperties: false,
};

const ensureObject = (input) => {
  if (!input || typeof input !== "object" || Array.isArray(input)) {
    throw new Error("Input must be an object.");
  }
  return input;
};

const rejectUnsupported = (input, allowed) => {
  const unsupported = Object.keys(input).find((key) => !allowed.includes(key));
  if (unsupported) throw new Error(`${unsupported} is not supported.`);
};

const normalizeInteger = (value, name, maximum = MAX_ID, defaultValue) => {
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

const normalizeString = (value, name, maximum) => {
  if (typeof value !== "string") throw new Error(`${name} must be a string.`);
  const normalized = value.trim();
  if (!normalized || normalized.length > maximum) {
    throw new Error(`${name} must be between 1 and ${maximum} characters.`);
  }
  return normalized;
};

const normalizeKey = (value, name, maximum) => {
  const normalized = normalizeString(value, name, maximum);
  if (!/^[A-Za-z0-9_.-]+$/.test(normalized)) {
    throw new Error(`${name} contains unsupported characters.`);
  }
  return normalized;
};

const normalizePermissionKey = (value) => {
  return normalizeKey(value, "permissionKey", 120);
};

const normalizeBoolean = (value, name, defaultValue) => {
  if (value === undefined) return defaultValue;
  if (typeof value === "boolean") return value;
  if (["true", "1", 1].includes(value)) return true;
  if (["false", "0", 0].includes(value)) return false;
  throw new Error(`${name} must be a boolean.`);
};

const normalizeDate = (value, name) => {
  if (typeof value !== "string" || !/^\d{4}-\d{2}-\d{2}$/.test(value.trim())) {
    throw new Error(`${name} must use YYYY-MM-DD.`);
  }
  const normalized = value.trim();
  const parsed = new Date(`${normalized}T00:00:00Z`);
  if (
    Number.isNaN(parsed.getTime()) ||
    parsed.toISOString().slice(0, 10) !== normalized
  ) {
    throw new Error(`${name} must be a valid date.`);
  }
  return normalized;
};

export const normalizeAdminUserSearchInput = (input = {}) => {
  ensureObject(input);
  const filterKeys = ["query", "status", "roleId"];
  rejectUnsupported(input, [...filterKeys, "page", "limit"]);
  if (!filterKeys.some((key) => Object.hasOwn(input, key))) {
    throw new Error("At least one administrator search filter is required.");
  }
  const normalized = {};
  if (Object.hasOwn(input, "query")) {
    normalized.query = normalizeString(input.query, "query", 100);
  }
  if (Object.hasOwn(input, "status")) {
    const status = normalizeString(input.status, "status", 20).toLowerCase();
    if (!["active", "inactive"].includes(status)) {
      throw new Error("status must be active or inactive.");
    }
    normalized.status = status;
  }
  if (Object.hasOwn(input, "roleId")) {
    normalized.roleId = normalizeInteger(input.roleId, "roleId");
  }
  normalized.page = normalizeInteger(input.page, "page", MAX_PAGE, 1);
  normalized.limit = normalizeInteger(input.limit, "limit", MAX_LIMIT, 25);
  return normalized;
};

export const normalizeAdminUserInput = (input = {}) => {
  ensureObject(input);
  rejectUnsupported(input, ["adminUserId"]);
  if (!Object.hasOwn(input, "adminUserId"))
    throw new Error("adminUserId is required.");
  return { adminUserId: normalizeInteger(input.adminUserId, "adminUserId") };
};

export const normalizeRoleInput = (input = {}) => {
  ensureObject(input);
  rejectUnsupported(input, ["roleId", "roleName"]);
  const provided = ["roleId", "roleName"].filter((key) =>
    Object.hasOwn(input, key),
  );
  if (provided.length !== 1) {
    throw new Error("Exactly one of roleId or roleName is required.");
  }
  return provided[0] === "roleId"
    ? { roleId: normalizeInteger(input.roleId, "roleId") }
    : { roleName: normalizeString(input.roleName, "roleName", 100) };
};

export const normalizePermissionInput = (input = {}) => {
  ensureObject(input);
  rejectUnsupported(input, ["permissionKey", "includeInactive"]);
  if (!Object.hasOwn(input, "permissionKey")) {
    throw new Error("permissionKey is required.");
  }
  return {
    permissionKey: normalizePermissionKey(input.permissionKey),
    includeInactive: normalizeBoolean(
      input.includeInactive,
      "includeInactive",
      false,
    ),
  };
};

export const normalizeAdminPermissionInput = (input = {}) => {
  ensureObject(input);
  rejectUnsupported(input, ["adminUserId", "permissionKey"]);
  if (
    !Object.hasOwn(input, "adminUserId") ||
    !Object.hasOwn(input, "permissionKey")
  ) {
    throw new Error("adminUserId and permissionKey are required.");
  }
  return {
    adminUserId: normalizeInteger(input.adminUserId, "adminUserId"),
    permissionKey: normalizePermissionKey(input.permissionKey),
  };
};

const operationLogFilterKeys = Object.keys(operationLogProperties);

export const normalizeOperationLogSearchInput = (
  input = {},
  { pagination = true } = {},
) => {
  ensureObject(input);
  rejectUnsupported(
    input,
    pagination
      ? [...operationLogFilterKeys, "page", "limit"]
      : operationLogFilterKeys,
  );
  if (!operationLogFilterKeys.some((key) => Object.hasOwn(input, key))) {
    throw new Error("At least one operation-log filter is required.");
  }
  const normalized = {};
  if (Object.hasOwn(input, "operatorId")) {
    normalized.operatorId = normalizeInteger(input.operatorId, "operatorId");
  }
  for (const [key, maximum] of [
    ["module", 80],
    ["operationType", 80],
  ]) {
    if (Object.hasOwn(input, key))
      normalized[key] = normalizeKey(input[key], key, maximum);
  }
  if (normalized.module && !isRegisteredOperationLogModule(normalized.module)) {
    throw new Error("module must be a registered operation-log module.");
  }
  if (Object.hasOwn(input, "targetType")) {
    const targetType = normalizeString(
      input.targetType,
      "targetType",
      40,
    ).toLowerCase();
    const allowed = [
      "client",
      "admin_user",
      "admin_role",
      "points_mall_product",
    ];
    if (!allowed.includes(targetType))
      throw new Error("targetType is not supported.");
    normalized.targetType = targetType;
  }
  if (Object.hasOwn(input, "targetId")) {
    normalized.targetId = normalizeInteger(input.targetId, "targetId");
  }
  if (normalized.targetId && !normalized.targetType) {
    throw new Error("targetType is required when targetId is provided.");
  }
  for (const key of ["startDate", "endDate"]) {
    if (Object.hasOwn(input, key))
      normalized[key] = normalizeDate(input[key], key);
  }
  if (
    normalized.startDate &&
    normalized.endDate &&
    normalized.startDate > normalized.endDate
  ) {
    throw new Error("startDate must be on or before endDate.");
  }
  if (Object.hasOwn(input, "query")) {
    normalized.query = normalizeString(input.query, "query", 200);
  }
  if (pagination) {
    normalized.page = normalizeInteger(input.page, "page", MAX_PAGE, 1);
    normalized.limit = normalizeInteger(input.limit, "limit", MAX_LIMIT, 25);
  }
  return normalized;
};

export const normalizeOperationLogIdInput = (input = {}) => {
  ensureObject(input);
  rejectUnsupported(input, ["operationLogId"]);
  if (!Object.hasOwn(input, "operationLogId")) {
    throw new Error("operationLogId is required.");
  }
  return {
    operationLogId: normalizeInteger(input.operationLogId, "operationLogId"),
  };
};

const hasAnyPermission = (authStore, permissionKeys) =>
  permissionKeys.some((permission) => authStore?.hasPermission?.(permission));

const requirePermission = (authStore, permissionKeys) => {
  if (
    !authStore?.isAuthenticated ||
    !hasAnyPermission(authStore, permissionKeys)
  ) {
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
    if ([401, 403].includes(statusCode)) {
      throw new Error("You are not authorized to use this tool.");
    }
    if (statusCode === 404) throw new Error(notFoundMessage);
    throw new Error(unavailableMessage);
  }
};

const createReadTool = ({
  name,
  inputSchema,
  normalizer,
  permissionKeys,
  authStore,
  operation,
  notFoundMessage,
  unavailableMessage,
}) => ({
  name,
  title: catalogEntry(name)?.title || name,
  description: catalogEntry(name)?.description || name,
  inputSchema,
  annotations: { readOnlyHint: true, untrustedContentHint: true },
  execute: async (input = {}) => {
    requirePermission(authStore, permissionKeys);
    const normalized = normalizer(input);
    return executeApi(
      () => operation(normalized),
      notFoundMessage,
      unavailableMessage,
    );
  },
});

export const createSearchAdminUsersTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
}) =>
  createReadTool({
    name: "search_admin_users",
    inputSchema: adminUserSearchInputSchema,
    normalizer: normalizeAdminUserSearchInput,
    permissionKeys: ACCOUNT_PERMISSIONS,
    authStore,
    operation: (input) => webMcpApi.searchAdminUsers(input),
    notFoundMessage: "No administrators matched the search.",
    unavailableMessage: "Unable to search administrators.",
  });

export const createGetAdminUserTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
}) =>
  createReadTool({
    name: "get_admin_user",
    inputSchema: adminUserInputSchema,
    normalizer: normalizeAdminUserInput,
    permissionKeys: ACCOUNT_PERMISSIONS,
    authStore,
    operation: (input) => webMcpApi.getAdminUser(input),
    notFoundMessage: "Administrator not found.",
    unavailableMessage: "Unable to get the administrator.",
  });

export const createGetRolePermissionsTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
}) =>
  createReadTool({
    name: "get_role_permissions",
    inputSchema: roleInputSchema,
    normalizer: normalizeRoleInput,
    permissionKeys: ROLE_PERMISSIONS,
    authStore,
    operation: (input) => webMcpApi.getRolePermissions(input),
    notFoundMessage: "Role not found.",
    unavailableMessage: "Unable to get role permissions.",
  });

export const createFindRolesByPermissionTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
}) =>
  createReadTool({
    name: "find_roles_by_permission",
    inputSchema: permissionInputSchema,
    normalizer: normalizePermissionInput,
    permissionKeys: ROLE_PERMISSIONS,
    authStore,
    operation: (input) => webMcpApi.findRolesByPermission(input),
    notFoundMessage: "Permission not found.",
    unavailableMessage: "Unable to find roles by permission.",
  });

export const createCheckAdminUserPermissionTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
}) =>
  createReadTool({
    name: "check_admin_user_permission",
    inputSchema: adminPermissionInputSchema,
    normalizer: normalizeAdminPermissionInput,
    permissionKeys: ROLE_PERMISSIONS,
    authStore,
    operation: (input) => webMcpApi.checkAdminUserPermission(input),
    notFoundMessage: "Administrator or permission not found.",
    unavailableMessage: "Unable to check the administrator permission.",
  });

export const createSearchOperationLogsTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
}) =>
  createReadTool({
    name: "search_operation_logs",
    inputSchema: operationLogSearchInputSchema,
    normalizer: normalizeOperationLogSearchInput,
    permissionKeys: LOG_READ_PERMISSIONS,
    authStore,
    operation: (input) => webMcpApi.searchOperationLogs(input),
    notFoundMessage: "No operation logs matched the search.",
    unavailableMessage: "Unable to search operation logs.",
  });

export const createGetOperationLogTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
}) =>
  createReadTool({
    name: "get_operation_log",
    inputSchema: operationLogIdInputSchema,
    normalizer: normalizeOperationLogIdInput,
    permissionKeys: LOG_READ_PERMISSIONS,
    authStore,
    operation: (input) => webMcpApi.getOperationLog(input),
    notFoundMessage: "Operation log not found.",
    unavailableMessage: "Unable to get the operation log.",
  });

export const createNavigateToOperationLogsTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
  router,
}) => ({
  name: "navigate_to_operation_logs",
  title:
    catalogEntry("navigate_to_operation_logs")?.title ||
    "Navigate to operation logs",
  description:
    catalogEntry("navigate_to_operation_logs")?.description ||
    "Open filtered results in the operation-log report.",
  inputSchema: operationLogFilterInputSchema,
  annotations: { readOnlyHint: true, untrustedContentHint: true },
  execute: async (input = {}) => {
    requirePermission(authStore, LOG_READ_PERMISSIONS);
    const normalized = normalizeOperationLogSearchInput(input, {
      pagination: false,
    });
    await executeApi(
      () => webMcpApi.searchOperationLogs({ ...normalized, page: 1, limit: 1 }),
      "No operation logs matched the search.",
      "Unable to validate operation-log filters.",
    );
    const query = buildOperationLogRouteQuery(normalized);
    await router?.push?.({ name: "operation-log-report", query });
    const resolved = router?.resolve?.({ name: "operation-log-report", query });
    return {
      success: true,
      route:
        resolved?.href ||
        `/#/operation-log-report?${new URLSearchParams(query)}`,
      filters: normalized,
    };
  },
});

export const createExportOperationLogsTool = ({
  authStore,
  webMcpApi = adminWebMcpApi,
  router,
}) => ({
  name: "export_operation_logs",
  title:
    catalogEntry("export_operation_logs")?.title || "Export operation logs",
  description:
    catalogEntry("export_operation_logs")?.description ||
    "Export filtered operation logs.",
  inputSchema: operationLogFilterInputSchema,
  annotations: { readOnlyHint: true, untrustedContentHint: true },
  execute: async (input = {}) => {
    requirePermission(authStore, LOG_EXPORT_PERMISSIONS);
    const normalized = normalizeOperationLogSearchInput(input, {
      pagination: false,
    });
    let language = "en";
    try {
      language =
        window?.localStorage?.getItem("adminLanguage") === "zh" ? "zh" : "en";
    } catch {
      language = "en";
    }
    const job = await executeApi(
      () => webMcpApi.exportOperationLogs({ ...normalized, language }),
      "Operation-log export was not found.",
      "Unable to export operation logs.",
    );
    const jobId = String(job?.jobId || "").trim();
    if (!jobId) throw new Error("Unable to export operation logs.");
    const progressUrl =
      router?.resolve?.({ name: "webmcp-export-progress", query: { jobId } })
        ?.href ||
      `/#/webmcp/export-progress?jobId=${encodeURIComponent(jobId)}`;
    let opened = false;
    try {
      opened = Boolean(window?.open?.(progressUrl, "_blank", "noopener"));
    } catch {
      opened = false;
    }
    recordWebMcpActivity(
      {
        id: `export:${jobId}`,
        kind: "export",
        label: "Operation log export",
        jobId,
      },
      authStore?.user?.id,
    );
    return {
      success: true,
      jobId,
      progressUrl,
      opened,
      queued: Boolean(job?.queued),
      reused: Boolean(job?.reused),
      agentNextAction: "none",
      retryPolicy: "Only retry after the user reports that no file appeared.",
    };
  },
});

export const registerAdminAdminLogWebMcpTools = ({
  authStore,
  webMcpApi = adminWebMcpApi,
  router,
  modelContext = typeof document !== "undefined"
    ? document.modelContext
    : undefined,
} = {}) => {
  if (!modelContext?.registerTool || !authStore?.isAuthenticated) return noop;
  const definitions = [
    [
      "search_admin_users",
      ACCOUNT_PERMISSIONS,
      () => createSearchAdminUsersTool({ authStore, webMcpApi }),
    ],
    [
      "get_admin_user",
      ACCOUNT_PERMISSIONS,
      () => createGetAdminUserTool({ authStore, webMcpApi }),
    ],
    [
      "get_role_permissions",
      ROLE_PERMISSIONS,
      () => createGetRolePermissionsTool({ authStore, webMcpApi }),
    ],
    [
      "find_roles_by_permission",
      ROLE_PERMISSIONS,
      () => createFindRolesByPermissionTool({ authStore, webMcpApi }),
    ],
    [
      "check_admin_user_permission",
      ROLE_PERMISSIONS,
      () => createCheckAdminUserPermissionTool({ authStore, webMcpApi }),
    ],
    [
      "search_operation_logs",
      LOG_READ_PERMISSIONS,
      () => createSearchOperationLogsTool({ authStore, webMcpApi }),
    ],
    [
      "get_operation_log",
      LOG_READ_PERMISSIONS,
      () => createGetOperationLogTool({ authStore, webMcpApi }),
    ],
    [
      "navigate_to_operation_logs",
      LOG_READ_PERMISSIONS,
      () => createNavigateToOperationLogsTool({ authStore, webMcpApi, router }),
    ],
    [
      "export_operation_logs",
      LOG_EXPORT_PERMISSIONS,
      () => createExportOperationLogsTool({ authStore, webMcpApi, router }),
    ],
  ];
  const tools = definitions
    .filter(([, permissionKeys]) => hasAnyPermission(authStore, permissionKeys))
    .map(([, , create]) => create());
  if (!tools.length) return noop;

  const controller = new AbortController();
  try {
    tools.forEach((tool) => {
      Promise.resolve(
        modelContext.registerTool(tool, { signal: controller.signal }),
      ).catch((error) => {
        controller.abort();
        if (error?.name !== "AbortError") {
          console.warn("Admin Log WebMCP tool could not be registered.", error);
        }
      });
    });
  } catch (error) {
    controller.abort();
    console.warn("Admin Log WebMCP tool could not be registered.", error);
  }
  return () => controller.abort();
};
