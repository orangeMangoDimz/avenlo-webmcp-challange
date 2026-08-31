/** @vitest-environment jsdom */

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const loadSalesModule = () => import("../adminSalesWebMcp");

const managerAuthStore = {
  isAuthenticated: true,
  user: { id: 7, roleId: 5 },
  hasPermission: vi.fn((permission) =>
    [
      "page_saleslist_view",
      "page_salesdashboard_view",
      "page_dailyreport_readonly",
    ].includes(permission),
  ),
};

const salesUserAuthStore = {
  isAuthenticated: true,
  user: { id: 42, roleId: 6 },
  hasPermission: vi.fn((permission) =>
    ["page_salesdashboard_view", "page_dailyreport_readonly"].includes(
      permission,
    ),
  ),
};

const sales = {
  id: 42,
  name: "Sarah Tan",
  email: "sarah@example.com",
  status: "active",
  department: "Sales",
  position: "Account Manager",
  totalClients: 8,
  totalPartners: 2,
};

const webMcpApi = {
  searchSales: vi.fn(async () => ({
    sales: [sales],
    pagination: { page: 1, limit: 25, total: 1, totalPages: 1, hasMore: false },
  })),
  getSalesClients: vi.fn(async () => ({
    sales,
    clients: [],
    pagination: { page: 1, limit: 25, total: 0, totalPages: 0, hasMore: false },
  })),
  getSalesPartners: vi.fn(async () => ({
    sales,
    partners: [],
    pagination: { page: 1, limit: 25, total: 0, totalPages: 0, hasMore: false },
  })),
  getSalesPerformance: vi.fn(async () => ({
    sales,
    month: "2026-08",
    metrics: { netDeposit: 500 },
    target: { netDeposit: 1000, achievementRate: 50 },
  })),
  getSalesDailySummary: vi.fn(async () => ({
    sales,
    date: "2026-08-31",
    metrics: { newClients: 2 },
  })),
  getSalesLeaderboard: vi.fn(async () => ({
    metric: "newClients",
    period: "month",
    rankings: [{ rank: 1, sales, value: 2 }],
  })),
};

const router = { push: vi.fn(async () => {}) };

describe("admin sales WebMCP tools", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    delete document.modelContext;
    vi.restoreAllMocks();
  });

  it("normalizes sales search and paginated relationship inputs", async () => {
    const {
      normalizeSalesSearchInput,
      normalizeSalesRelationshipInput,
    } = await loadSalesModule();

    expect(normalizeSalesSearchInput({ query: " Sarah ", limit: "10" })).toEqual({
      query: "Sarah",
      page: 1,
      limit: 10,
    });
    expect(
      normalizeSalesRelationshipInput({
        salesId: "42",
        search: " client ",
        page: "2",
      }),
    ).toEqual({ salesId: 42, search: "client", page: 2, limit: 25 });
    expect(() => normalizeSalesSearchInput({ query: "" })).toThrow(/query/i);
    expect(() =>
      normalizeSalesRelationshipInput({ salesId: 0 }),
    ).toThrow(/salesId/i);
  });

  it("normalizes monthly, daily, and leaderboard periods", async () => {
    const {
      normalizeSalesPerformanceInput,
      normalizeSalesDailyInput,
      normalizeSalesLeaderboardInput,
    } = await loadSalesModule();

    expect(
      normalizeSalesPerformanceInput({
        salesId: "42",
        month: "2026-08",
        tzOffset: "420",
      }),
    ).toEqual({ salesId: 42, month: "2026-08", tzOffset: 420 });
    expect(
      normalizeSalesDailyInput({
        salesId: 42,
        date: "2026-08-31",
        tzOffset: -300,
      }),
    ).toEqual({ salesId: 42, date: "2026-08-31", tzOffset: -300 });
    expect(
      normalizeSalesLeaderboardInput({ metric: "newClients" }, 420),
    ).toEqual({
      metric: "newClients",
      period: "month",
      tzOffset: 420,
      limit: 10,
    });
    expect(() =>
      normalizeSalesLeaderboardInput({ metric: "commission" }, 420),
    ).toThrow(/metric/i);
    expect(() =>
      normalizeSalesLeaderboardInput({ period: "day", month: "2026-08" }, 420),
    ).toThrow(/month/i);
  });

  it("calls each sales endpoint with normalized inputs", async () => {
    const {
      createSearchSalesTool,
      createGetSalesClientsTool,
      createGetSalesPartnersTool,
      createGetSalesPerformanceTool,
      createGetSalesDailySummaryTool,
      createGetSalesLeaderboardTool,
    } = await loadSalesModule();

    await createSearchSalesTool({ authStore: managerAuthStore, webMcpApi }).execute({
      query: " Sarah ",
    });
    await createGetSalesClientsTool({
      authStore: managerAuthStore,
      webMcpApi,
    }).execute({ salesId: "42" });
    await createGetSalesPartnersTool({
      authStore: managerAuthStore,
      webMcpApi,
    }).execute({ salesId: 42 });
    await createGetSalesPerformanceTool({
      authStore: managerAuthStore,
      webMcpApi,
    }).execute({ salesId: 42, month: "2026-08", tzOffset: 420 });
    await createGetSalesDailySummaryTool({
      authStore: managerAuthStore,
      webMcpApi,
    }).execute({ salesId: 42, date: "2026-08-31", tzOffset: 420 });
    await createGetSalesLeaderboardTool({
      authStore: managerAuthStore,
      webMcpApi,
    }).execute({ metric: "newClients", period: "month", month: "2026-08", tzOffset: 420 });

    expect(webMcpApi.searchSales).toHaveBeenCalledWith({
      query: "Sarah",
      page: 1,
      limit: 25,
    });
    expect(webMcpApi.getSalesClients).toHaveBeenCalledWith({
      salesId: 42,
      page: 1,
      limit: 25,
    });
    expect(webMcpApi.getSalesPartners).toHaveBeenCalledWith({
      salesId: 42,
      page: 1,
      limit: 25,
    });
    expect(webMcpApi.getSalesPerformance).toHaveBeenCalledWith({
      salesId: 42,
      month: "2026-08",
      tzOffset: 420,
    });
    expect(webMcpApi.getSalesDailySummary).toHaveBeenCalledWith({
      salesId: 42,
      date: "2026-08-31",
      tzOffset: 420,
    });
    expect(webMcpApi.getSalesLeaderboard).toHaveBeenCalledWith({
      metric: "newClients",
      period: "month",
      month: "2026-08",
      tzOffset: 420,
      limit: 10,
    });
  });

  it("navigates to the resolved target sales dashboard", async () => {
    const { createNavigateToSalesTool } = await loadSalesModule();
    const tool = createNavigateToSalesTool({
      authStore: managerAuthStore,
      webMcpApi,
      router,
    });

    const result = await tool.execute({ salesId: "42" });

    expect(webMcpApi.searchSales).toHaveBeenCalledWith({
      query: "42",
      page: 1,
      limit: 25,
    });
    expect(router.push).toHaveBeenCalledWith({
      name: "sales-dashboard",
      query: { salesId: "42" },
    });
    expect(result).toEqual({
      success: true,
      salesId: 42,
      route: "/sales-dashboard?salesId=42",
    });
  });

  it("registers management tools and unregisters every one", async () => {
    const { registerAdminSalesWebMcpTools } = await loadSalesModule();
    const registerTool = vi.fn(() => Promise.resolve());
    document.modelContext = { registerTool };

    const cleanup = registerAdminSalesWebMcpTools({
      authStore: managerAuthStore,
      webMcpApi,
      router,
    });
    await Promise.resolve();

    expect(registerTool.mock.calls.map(([tool]) => tool.name)).toEqual([
      "search_sales",
      "get_sales_clients",
      "get_sales_partners",
      "get_sales_performance",
      "get_sales_daily_summary",
      "get_sales_leaderboard",
      "navigate_to_sales",
    ]);
    expect(
      registerTool.mock.calls.every(
        ([tool]) =>
          tool.annotations?.readOnlyHint === true &&
          tool.annotations?.untrustedContentHint === true,
      ),
    ).toBe(true);

    const signals = registerTool.mock.calls.map(([, options]) => options.signal);
    cleanup();
    expect(signals.every((signal) => signal.aborted)).toBe(true);
  });

  it("does not expose the team leaderboard to a sales-only user", async () => {
    const { registerAdminSalesWebMcpTools } = await loadSalesModule();
    const registerTool = vi.fn(() => Promise.resolve());
    document.modelContext = { registerTool };

    registerAdminSalesWebMcpTools({
      authStore: salesUserAuthStore,
      webMcpApi,
      router,
    });
    await Promise.resolve();

    expect(registerTool.mock.calls.map(([tool]) => tool.name)).toEqual([
      "search_sales",
      "get_sales_clients",
      "get_sales_partners",
      "get_sales_performance",
      "get_sales_daily_summary",
      "navigate_to_sales",
    ]);
  });

  it("blocks direct leaderboard calls without daily-report permission", async () => {
    const { createGetSalesLeaderboardTool } = await loadSalesModule();
    const managementWithoutDaily = {
      isAuthenticated: true,
      user: { id: 7, roleId: 5 },
      hasPermission: vi.fn(
        (permission) => permission === "page_saleslist_view",
      ),
    };
    const tool = createGetSalesLeaderboardTool({
      authStore: managementWithoutDaily,
      webMcpApi,
    });

    await expect(tool.execute({})).rejects.toThrow(/not authorized/i);
    expect(webMcpApi.getSalesLeaderboard).not.toHaveBeenCalled();
  });

  it("publishes the approved tools in a dedicated sales catalog section", async () => {
    const { WEBMCP_TOOL_CATALOG, WEBMCP_TOOL_SECTIONS, groupWebMcpTools } =
      await import("../adminWebMcpCatalog");
    const salesTools = WEBMCP_TOOL_CATALOG.filter(
      ({ sectionKey }) => sectionKey === "sales",
    );

    expect(WEBMCP_TOOL_SECTIONS.map(({ key }) => key)).toContain("sales");
    expect(salesTools.map(({ name }) => name)).toEqual([
      "search_sales",
      "get_sales_clients",
      "get_sales_partners",
      "get_sales_performance",
      "get_sales_daily_summary",
      "get_sales_leaderboard",
      "navigate_to_sales",
    ]);
    expect(groupWebMcpTools(WEBMCP_TOOL_CATALOG)).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ key: "sales", tools: salesTools }),
      ]),
    );
  });
});
