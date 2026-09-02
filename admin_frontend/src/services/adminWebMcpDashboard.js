import { WEBMCP_TOOL_CATALOG } from "@/services/adminWebMcpCatalog";
import { adminWebMcpApi } from "@/services/adminWebMcpApi";
import { defaultOperationsFilters } from "@/services/adminWebMcpOperations";

export const hasWebMcpToolPermission = (tool = {}, authStore) => {
  const permissionKeys = Array.isArray(tool.permissionKeys)
    ? tool.permissionKeys
    : [];
  if (permissionKeys.length === 0) return true;

  const matcher = tool.permissionMatch === "all" ? "every" : "some";
  return permissionKeys[matcher]((permissionKey) =>
    authStore?.hasPermission?.(permissionKey),
  );
};

export const filterWebMcpTools = ({
  catalog = WEBMCP_TOOL_CATALOG,
  authStore,
  showAllTools = false,
} = {}) => {
  const tools = Array.isArray(catalog) ? catalog : [];
  return showAllTools
    ? tools
    : tools.filter((tool) => hasWebMcpToolPermission(tool, authStore));
};

const validateEmptyInput = (input) => {
  if (
    !input ||
    typeof input !== "object" ||
    Array.isArray(input) ||
    Object.keys(input).length > 0
  ) {
    throw new Error("get_dashboard_summary does not accept parameters.");
  }
};

export const createGetDashboardSummaryTool = ({
  webMcpApi = adminWebMcpApi,
  now = () => new Date(),
  timezoneOffset = (date) => -date.getTimezoneOffset(),
} = {}) => ({
  name: "get_dashboard_summary",
  title: "Get dashboard summary",
  description:
    "Return the permission-scoped Operations Overview data shown in the admin dashboard.",
  inputSchema: {
    type: "object",
    properties: {},
    additionalProperties: false,
  },
  annotations: { readOnlyHint: true, untrustedContentHint: true },
  execute: async (input = {}) => {
    validateEmptyInput(input);
    const current = now();
    return webMcpApi.getOperationsOverview(
      defaultOperationsFilters(current, timezoneOffset(current)),
    );
  },
});

export const registerAdminDashboardWebMcpTools = ({
  authStore,
  webMcpApi = adminWebMcpApi,
  modelContext = typeof document !== "undefined"
    ? document.modelContext
    : undefined,
} = {}) => {
  if (!modelContext?.registerTool || !authStore?.isAuthenticated) {
    return () => {};
  }

  const controller = new AbortController();
  try {
    Promise.resolve(
      modelContext.registerTool(createGetDashboardSummaryTool({ webMcpApi }), {
        signal: controller.signal,
      }),
    ).catch((error) => {
      controller.abort();
      if (error?.name !== "AbortError") {
        console.warn(
          "Admin dashboard WebMCP tool could not be registered.",
          error,
        );
      }
    });
  } catch (error) {
    controller.abort();
    console.warn("Admin dashboard WebMCP tool could not be registered.", error);
  }

  return () => controller.abort();
};
