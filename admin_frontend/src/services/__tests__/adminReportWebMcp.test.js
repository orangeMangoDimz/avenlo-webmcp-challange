/** @vitest-environment jsdom */

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  createExportFundingReportTool,
  createExportIbStatementTool,
  createGetDailySalesPerformanceTool,
  createGetFundingSummaryTool,
  createNavigateToReportTool,
  normalizeDailySalesPerformanceInput,
  normalizeFundingPeriodInput,
  normalizeIbStatementInput,
  registerAdminReportWebMcpTools,
} from "../adminReportWebMcp";
import {
  WEBMCP_TOOL_CATALOG,
  WEBMCP_TOOL_SECTIONS,
} from "../adminWebMcpCatalog";

const allPermissions = new Set([
  "page_fundingreport_readonly",
  "page_fundingreport_export",
  "page_dailyreport_readonly",
  "page_ibstatement_readonly",
  "page_ibstatement_export",
  "page_operationlogreport_readonly",
]);

const authStore = {
  isAuthenticated: true,
  user: { id: 7, roleId: 1 },
  hasPermission: vi.fn((permission) => allPermissions.has(permission)),
};

const fullPagination = {
  page: 1,
  limit: 25,
  perPage: 25,
  total: 0,
  totalPages: 0,
  hasMore: false,
};

const webMcpApi = {
  getFundingSummary: vi.fn(async () => ({
    period: { startDate: "2026-08-01", endDate: "2026-08-31" },
    summary: { totalDeposits: 1000, totalWithdrawals: 200, netFlow: 800 },
  })),
  searchFundingTransactions: vi.fn(async () => ({ transactions: [], pagination: fullPagination })),
  getDailySalesPerformance: vi.fn(async () => ({
    date: "2026-08-31",
    rankBy: "deposits",
    summary: { deposits: 1000 },
    rankings: [],
  })),
  searchIbPartners: vi.fn(async () => ({ partners: [], pagination: fullPagination })),
  getIbStatement: vi.fn(async () => ({
    partner: { id: 1, ibCode: "IB-001", name: "Partner" },
    accounts: [],
    accountsPagination: fullPagination,
  })),
  searchOperationLogs: vi.fn(async () => ({ logs: [], pagination: fullPagination })),
  listCustomReports: vi.fn(async () => ({ reports: [], pagination: fullPagination })),
  getCustomReportResults: vi.fn(async () => ({ report: { id: "12" }, widgets: [] })),
  exportFundingReport: vi.fn(async () => ({ jobId: "wmcp_funding_123", queued: true })),
  exportIbStatement: vi.fn(async () => ({ jobId: "wmcp_ib_123", queued: true })),
};

const router = {
  push: vi.fn(async () => {}),
  resolve: vi.fn(({ query }) => ({
    href: `/#/webmcp/export-progress?jobId=${query.jobId}&exportKind=${query.exportKind}`,
  })),
};

describe("admin Report WebMCP tools", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(window, "open").mockReturnValue({});
  });

  afterEach(() => {
    delete document.modelContext;
    vi.restoreAllMocks();
  });

  it("normalizes browser-default periods and daily timezone", () => {
    expect(normalizeFundingPeriodInput({}, "2026-08-31")).toEqual({
      startDate: "2026-08-01",
      endDate: "2026-08-31",
    });
    expect(
      normalizeDailySalesPerformanceInput(
        { rankBy: "deposits", limit: "10" },
        "2026-08-31",
        420,
      ),
    ).toEqual({ date: "2026-08-31", tzOffset: 420, rankBy: "deposits", limit: 10 });
    expect(() =>
      normalizeFundingPeriodInput({ startDate: "2026-08-31" }, "2026-08-31"),
    ).toThrow(/both/i);
  });

  it("requires exactly one IB selector and applies the current-month period", () => {
    expect(normalizeIbStatementInput({ ibCode: " IB-001 " }, "2026-08-31")).toEqual({
      ibCode: "IB-001",
      startDate: "2026-08-01",
      endDate: "2026-08-31",
      page: 1,
      limit: 25,
    });
    expect(() =>
      normalizeIbStatementInput(
        { ibPartnerId: 1, ibCode: "IB-001" },
        "2026-08-31",
      ),
    ).toThrow(/exactly one/i);
  });

  it("calls Report endpoints with normalized inputs", async () => {
    await createGetFundingSummaryTool({ authStore, webMcpApi }).execute({
      startDate: "2026-08-01",
      endDate: "2026-08-31",
    });
    await createGetDailySalesPerformanceTool({ authStore, webMcpApi }).execute({
      date: "2026-08-31",
      tzOffset: 420,
      rankBy: "deposits",
    });

    expect(webMcpApi.getFundingSummary).toHaveBeenCalledWith({
      startDate: "2026-08-01",
      endDate: "2026-08-31",
    });
    expect(webMcpApi.getDailySalesPerformance).toHaveBeenCalledWith({
      date: "2026-08-31",
      tzOffset: 420,
      rankBy: "deposits",
      limit: 25,
    });
  });

  it("navigates only to the approved report routes", async () => {
    const tool = createNavigateToReportTool({ authStore, router });

    expect(await tool.execute({ reportKey: "funding" })).toEqual({
      success: true,
      reportKey: "funding",
      route: "/funding-report",
    });
    expect(router.push).toHaveBeenCalledWith({ name: "funding-report" });

    await tool.execute({ reportKey: "custom", reportId: "12" });
    expect(router.push).toHaveBeenLastCalledWith({
      name: "custom-report-detail",
      params: { reportId: "12" },
    });
    await expect(tool.execute({ reportKey: "unknown" })).rejects.toThrow(/reportKey/i);
  });

  it("queues report-specific exports and opens the typed progress page", async () => {
    const funding = createExportFundingReportTool({ authStore, webMcpApi, router });
    const statement = createExportIbStatementTool({ authStore, webMcpApi, router });

    const fundingResult = await funding.execute({
      startDate: "2026-08-01",
      endDate: "2026-08-31",
    });
    const statementResult = await statement.execute({
      ibPartnerId: 1,
      startDate: "2026-08-01",
      endDate: "2026-08-31",
      format: "csv",
    });

    expect(fundingResult.exportKind).toBe("funding_report");
    expect(statementResult.exportKind).toBe("ib_statement");
    expect(router.resolve).toHaveBeenCalledWith({
      name: "webmcp-export-progress",
      query: { jobId: "wmcp_funding_123", exportKind: "funding_report" },
    });
  });

  it("registers the ten Report tools and aborts every registration", async () => {
    const registerTool = vi.fn(() => Promise.resolve());
    document.modelContext = { registerTool };

    const cleanup = registerAdminReportWebMcpTools({
      authStore,
      webMcpApi,
      router,
    });
    await Promise.resolve();

    expect(registerTool.mock.calls.map(([tool]) => tool.name)).toEqual([
      "get_funding_summary",
      "search_funding_transactions",
      "get_daily_sales_performance",
      "search_ib_partners",
      "get_ib_statement",
      "list_custom_reports",
      "get_custom_report_results",
      "navigate_to_report",
      "export_funding_report",
      "export_ib_statement",
    ]);
    expect(registerTool.mock.calls.every(([tool]) => tool.annotations.readOnlyHint)).toBe(true);

    const signals = registerTool.mock.calls.map(([, options]) => options.signal);
    cleanup();
    expect(signals.every((signal) => signal.aborted)).toBe(true);
  });

  it("publishes Report tools with distinct export access", () => {
    const reportTools = WEBMCP_TOOL_CATALOG.filter(
      ({ sectionKey }) => sectionKey === "report",
    );
    expect(WEBMCP_TOOL_SECTIONS.map(({ key }) => key)).toContain("report");
    expect(reportTools.map(({ name }) => name)).toEqual([
      "get_funding_summary",
      "search_funding_transactions",
      "get_daily_sales_performance",
      "search_ib_partners",
      "get_ib_statement",
      "list_custom_reports",
      "get_custom_report_results",
      "navigate_to_report",
      "export_funding_report",
      "export_ib_statement",
    ]);
    expect(
      reportTools.filter(({ accessMode }) => accessMode === "export").map(({ name }) => name),
    ).toEqual(["export_funding_report", "export_ib_statement"]);
    expect(
      reportTools.find(({ name }) => name === "export_ib_statement")
        ?.permissionMatch,
    ).toBe("all");
  });

  it("does not disclose IB statement export without both read and export access", async () => {
    const registerTool = vi.fn(() => Promise.resolve());
    const exportOnlyAuthStore = {
      isAuthenticated: true,
      user: { id: 9, roleId: 7 },
      hasPermission: vi.fn(
        (permission) => permission === "page_ibstatement_export",
      ),
    };

    registerAdminReportWebMcpTools({
      authStore: exportOnlyAuthStore,
      webMcpApi,
      router,
      modelContext: { registerTool },
    });
    await Promise.resolve();

    const names = registerTool.mock.calls.map(([tool]) => tool.name);
    expect(names).not.toContain("export_ib_statement");
    expect(names).not.toContain("navigate_to_report");
  });
});
