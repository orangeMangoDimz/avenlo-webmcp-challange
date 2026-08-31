/** @vitest-environment jsdom */

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  createCheckAdminUserPermissionTool,
  createExportOperationLogsTool,
  createFindRolesByPermissionTool,
  createGetAdminUserTool,
  createGetOperationLogTool,
  createGetRolePermissionsTool,
  createNavigateToOperationLogsTool,
  createSearchAdminUsersTool,
  createSearchOperationLogsTool,
  normalizeAdminPermissionInput,
  normalizeAdminUserInput,
  normalizeAdminUserSearchInput,
  normalizeOperationLogIdInput,
  normalizeOperationLogSearchInput,
  normalizePermissionInput,
  normalizeRoleInput,
  registerAdminAdminLogWebMcpTools,
} from "../adminAdminLogWebMcp";
import {
  buildOperationLogRouteQuery,
  hydrateOperationLogRouteQuery,
} from "../operationLogWebMcpNavigation";
import {
  WEBMCP_TOOL_CATALOG,
  WEBMCP_TOOL_SECTIONS,
  groupWebMcpTools,
} from "../adminWebMcpCatalog";

const permissions = new Set([
  "page_accountmanagement_readonly",
  "page_rolemanagement_readonly",
  "page_operationlogreport_readonly",
  "page_operationlogreport_export",
]);

const authStore = {
  isAuthenticated: true,
  hasPermission: vi.fn((permission) => permissions.has(permission)),
};

const webMcpApi = {
  searchAdminUsers: vi.fn(async () => ({
    adminUsers: [{ id: 42, fullName: "Sarah Tan", status: "active" }],
    pagination: { page: 1, limit: 25, total: 1 },
  })),
  getAdminUser: vi.fn(async () => ({
    adminUser: { id: 42, fullName: "Sarah Tan", status: "active" },
  })),
  getRolePermissions: vi.fn(async () => ({
    role: { id: 4, name: "Operations" },
    permissions: [{ key: "page_withdraw_approve" }],
  })),
  findRolesByPermission: vi.fn(async () => ({
    permission: { key: "page_withdraw_approve" },
    roles: [{ id: 4, name: "Operations" }],
  })),
  checkAdminUserPermission: vi.fn(async () => ({
    adminUser: { id: 42, fullName: "Sarah Tan" },
    permission: { key: "page_withdraw_approve" },
    hasPermission: true,
    sources: ["role"],
  })),
  searchOperationLogs: vi.fn(async () => ({
    operationLogs: [{ id: 123, operator: { id: 42 } }],
    pagination: { page: 1, limit: 25, total: 1 },
  })),
  getOperationLog: vi.fn(async () => ({ operationLog: { id: 123 } })),
  exportOperationLogs: vi.fn(async () => ({
    jobId: "aolr_abc123",
    exportType: "operation_logs",
    queued: true,
    reused: false,
  })),
};

const router = {
  push: vi.fn(async () => {}),
  resolve: vi.fn(({ name, query }) => ({
    href:
      name === "webmcp-export-progress"
        ? `/#/webmcp/export-progress?jobId=${query.jobId}`
        : `/#/operation-log-report?${new URLSearchParams(query).toString()}`,
  })),
};

describe("admin Admin Log WebMCP tools", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    delete document.modelContext;
    vi.restoreAllMocks();
  });

  it("normalizes administrator, role, and permission selectors", () => {
    expect(
      normalizeAdminUserSearchInput({
        query: " Sarah ",
        status: "ACTIVE",
        roleId: "4",
        page: "2",
        limit: "50",
      }),
    ).toEqual({
      query: "Sarah",
      status: "active",
      roleId: 4,
      page: 2,
      limit: 50,
    });
    expect(normalizeAdminUserInput({ adminUserId: "42" })).toEqual({
      adminUserId: 42,
    });
    expect(normalizeRoleInput({ roleName: " Operations " })).toEqual({
      roleName: "Operations",
    });
    expect(
      normalizePermissionInput({ permissionKey: " page_withdraw_approve " }),
    ).toEqual({
      permissionKey: "page_withdraw_approve",
      includeInactive: false,
    });
    expect(
      normalizeAdminPermissionInput({
        adminUserId: "42",
        permissionKey: "page_withdraw_approve",
      }),
    ).toEqual({ adminUserId: 42, permissionKey: "page_withdraw_approve" });
    for (const permissionKey of [
      "application_logs.view",
      "perm-ib-management",
      "ib_application_edit_rules",
      "group_report",
    ]) {
      expect(normalizePermissionInput({ permissionKey })).toEqual({
        permissionKey,
        includeInactive: false,
      });
    }
  });

  it("normalizes exact operation-log filters and pagination", () => {
    expect(
      normalizeOperationLogSearchInput({
        operatorId: "42",
        module: " role_management ",
        operationType: "edit",
        targetType: "admin_role",
        targetId: "7",
        startDate: "2026-08-01",
        endDate: "2026-08-31",
        query: " permission ",
        page: "2",
        limit: "50",
      }),
    ).toEqual({
      operatorId: 42,
      module: "role_management",
      operationType: "edit",
      targetType: "admin_role",
      targetId: 7,
      startDate: "2026-08-01",
      endDate: "2026-08-31",
      query: "permission",
      page: 2,
      limit: 50,
    });
    expect(normalizeOperationLogIdInput({ operationLogId: "123" })).toEqual({
      operationLogId: 123,
    });
  });

  it.each([
    [() => normalizeAdminUserSearchInput({}), /filter/i],
    [
      () => normalizeRoleInput({ roleId: 4, roleName: "Operations" }),
      /exactly one/i,
    ],
    [
      () => normalizePermissionInput({ permissionKey: "approve permission" }),
      /permissionKey/i,
    ],
    [() => normalizeOperationLogSearchInput({ targetId: 7 }), /targetType/i],
    [
      () => normalizeOperationLogSearchInput({ module: "not_a_real_module" }),
      /registered/i,
    ],
    [
      () => normalizeOperationLogSearchInput({ targetType: "ib_partner" }),
      /targetType/i,
    ],
    [
      () =>
        normalizeOperationLogSearchInput({
          module: "role_management",
          startDate: "2026-09-01",
          endDate: "2026-08-01",
        }),
      /startDate/i,
    ],
  ])("rejects unsafe or ambiguous input", (operation, message) => {
    expect(operation).toThrow(message);
  });

  it("accepts frontend modules synchronized from the backend audit registry", () => {
    expect(normalizeOperationLogSearchInput({ module: "pm_products" })).toEqual(
      {
        module: "pm_products",
        page: 1,
        limit: 25,
      },
    );
  });

  it("calls each read endpoint with normalized input", async () => {
    await createSearchAdminUsersTool({ authStore, webMcpApi }).execute({
      query: " Sarah ",
    });
    await createGetAdminUserTool({ authStore, webMcpApi }).execute({
      adminUserId: "42",
    });
    await createGetRolePermissionsTool({ authStore, webMcpApi }).execute({
      roleName: " Operations ",
    });
    await createFindRolesByPermissionTool({ authStore, webMcpApi }).execute({
      permissionKey: "page_withdraw_approve",
    });
    await createCheckAdminUserPermissionTool({ authStore, webMcpApi }).execute({
      adminUserId: "42",
      permissionKey: "page_withdraw_approve",
    });
    await createSearchOperationLogsTool({ authStore, webMcpApi }).execute({
      module: " role_management ",
    });
    await createGetOperationLogTool({ authStore, webMcpApi }).execute({
      operationLogId: "123",
    });

    expect(webMcpApi.searchAdminUsers).toHaveBeenCalledWith({
      query: "Sarah",
      page: 1,
      limit: 25,
    });
    expect(webMcpApi.getAdminUser).toHaveBeenCalledWith({ adminUserId: 42 });
    expect(webMcpApi.getRolePermissions).toHaveBeenCalledWith({
      roleName: "Operations",
    });
    expect(webMcpApi.findRolesByPermission).toHaveBeenCalledWith({
      permissionKey: "page_withdraw_approve",
      includeInactive: false,
    });
    expect(webMcpApi.checkAdminUserPermission).toHaveBeenCalledWith({
      adminUserId: 42,
      permissionKey: "page_withdraw_approve",
    });
    expect(webMcpApi.searchOperationLogs).toHaveBeenCalledWith({
      module: "role_management",
      page: 1,
      limit: 25,
    });
    expect(webMcpApi.getOperationLog).toHaveBeenCalledWith({
      operationLogId: 123,
    });
  });

  it("builds and hydrates an exact cross-module audit-report route", async () => {
    const filters = {
      operatorId: 42,
      targetType: "client",
      targetId: 456,
      query: "profile",
    };
    expect(buildOperationLogRouteQuery(filters)).toEqual({
      source: "webmcp",
      modelKey: "all",
      operatorId: "42",
      targetType: "client",
      targetId: "456",
      query: "profile",
      startDate: "",
      endDate: "",
    });
    expect(
      hydrateOperationLogRouteQuery(buildOperationLogRouteQuery(filters)),
    ).toEqual({
      source: "webmcp",
      modelKey: "all",
      subModule: "all",
      operationType: "all",
      operatorId: 42,
      targetType: "client",
      targetId: 456,
      query: "profile",
      startDate: "",
      endDate: "",
    });

    const tool = createNavigateToOperationLogsTool({
      authStore,
      webMcpApi,
      router,
    });
    const result = await tool.execute(filters);
    expect(webMcpApi.searchOperationLogs).toHaveBeenCalledWith({
      ...filters,
      page: 1,
      limit: 1,
    });
    expect(router.push).toHaveBeenCalledWith({
      name: "operation-log-report",
      query: buildOperationLogRouteQuery(filters),
    });
    expect(result.success).toBe(true);
    expect(result.route).toContain("operation-log-report");
  });

  it("opens the shared progress page for an operation-log export", async () => {
    const open = vi.spyOn(window, "open").mockReturnValue({});
    const tool = createExportOperationLogsTool({
      authStore,
      webMcpApi,
      router,
    });
    const result = await tool.execute({ module: "role_management" });

    expect(webMcpApi.exportOperationLogs).toHaveBeenCalledWith({
      module: "role_management",
      language: "en",
    });
    expect(open).toHaveBeenCalledWith(
      "/#/webmcp/export-progress?jobId=aolr_abc123",
      "_blank",
      "noopener",
    );
    expect(result).toEqual(
      expect.objectContaining({
        success: true,
        jobId: "aolr_abc123",
        queued: true,
        opened: true,
      }),
    );
  });

  it("registers exactly the nine permitted Admin Log tools", async () => {
    const registerTool = vi.fn(() => Promise.resolve());
    document.modelContext = { registerTool };
    const cleanup = registerAdminAdminLogWebMcpTools({
      authStore,
      webMcpApi,
      router,
    });
    await Promise.resolve();

    expect(registerTool.mock.calls.map(([tool]) => tool.name)).toEqual([
      "search_admin_users",
      "get_admin_user",
      "get_role_permissions",
      "find_roles_by_permission",
      "check_admin_user_permission",
      "search_operation_logs",
      "get_operation_log",
      "navigate_to_operation_logs",
      "export_operation_logs",
    ]);
    const signals = registerTool.mock.calls.map(
      ([, options]) => options.signal,
    );
    cleanup();
    expect(signals.every((signal) => signal.aborted)).toBe(true);
  });

  it("publishes the Admin Log tools in their own catalog section", () => {
    const tools = WEBMCP_TOOL_CATALOG.filter(
      ({ sectionKey }) => sectionKey === "admin_log",
    );
    expect(WEBMCP_TOOL_SECTIONS.map(({ key }) => key)).toContain("admin_log");
    expect(tools.map(({ name }) => name)).toEqual([
      "search_admin_users",
      "get_admin_user",
      "get_role_permissions",
      "find_roles_by_permission",
      "check_admin_user_permission",
      "search_operation_logs",
      "get_operation_log",
      "navigate_to_operation_logs",
      "export_operation_logs",
    ]);
    expect(groupWebMcpTools()).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ key: "admin_log", tools }),
      ]),
    );
  });
});
