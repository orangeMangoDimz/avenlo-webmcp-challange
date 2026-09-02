import { describe, expect, it, vi } from "vitest";
import { createGetDashboardSummaryTool } from "../adminWebMcpDashboard";
import {
  WEBMCP_TOOL_CATALOG,
  WEBMCP_TOOL_SECTIONS,
} from "../adminWebMcpCatalog";

const dashboardPayload = {
  generatedAt: "2026-09-03T08:00:00Z",
  period: {
    startDate: "2026-08-28",
    endDate: "2026-09-03",
    timezone: { offsetMinutes: 420, label: "UTC+07:00" },
  },
  policy: {
    highValueAmount: 10000,
    kycOverdueHours: 24,
    auditMutationBurstCount: 5,
    auditMutationWindowMinutes: 15,
    queueLimit: 25,
    queueReservePerType: 1,
    staleAfterSeconds: 300,
  },
  scope: {
    funding: { access: "all", canExport: true },
    kyc: { access: "all", canExport: false },
    clients: { access: "all", canExport: true },
    audit: { access: "all", canExport: false },
    sales: { access: "team", canExport: false },
    ib: { access: "all", canExport: true },
  },
  metrics: {
    netFunding: {
      status: "ready",
      totals: [
        { currency: "USD", deposits: 1200, withdrawals: 200, netFlow: 1000 },
      ],
    },
    pendingHighValueTransactions: {
      status: "ready",
      count: 2,
      totals: [{ currency: "USD", amount: 20000, count: 2 }],
    },
    overdueKyc: { status: "ready", count: 1 },
    operationalAlerts: { status: "ready", count: 0 },
  },
  attentionQueue: {
    items: [
      { id: "transaction:deposit:9", kind: "transaction", severity: "high" },
    ],
    total: 1,
    truncated: false,
  },
  fundingTrend: {
    status: "ready",
    points: [{ date: "2026-09-03", totals: [] }],
  },
  sales: {
    status: "ready",
    summary: { netDeposit: 1000, newClients: 3 },
    rankings: [],
  },
  ib: {
    status: "ready",
    summary: { partnerCount: 2, clientCount: 5, newPartnerCount: 1 },
    leaders: [],
  },
  recentActivity: {
    status: "ready",
    items: [{ id: "export:1", occurredAt: "2026-09-03T06:00:00Z" }],
  },
  sectionErrors: [],
};

describe("admin WebMCP dashboard tool", () => {
  it("returns the same permission-scoped Operations Overview payload shown by the dashboard", async () => {
    const getOperationsOverview = vi.fn(async () => dashboardPayload);
    const tool = createGetDashboardSummaryTool({
      webMcpApi: { getOperationsOverview },
      now: () => new Date("2026-09-03T08:00:00.000Z"),
      timezoneOffset: () => 420,
    });

    await expect(tool.execute({})).resolves.toBe(dashboardPayload);
    expect(getOperationsOverview).toHaveBeenCalledWith({
      startDate: "2026-08-28",
      endDate: "2026-09-03",
      tzOffset: 420,
    });
  });

  it("rejects parameters instead of silently changing the dashboard query", async () => {
    const getOperationsOverview = vi.fn();
    const tool = createGetDashboardSummaryTool({
      webMcpApi: { getOperationsOverview },
    });

    await expect(tool.execute({ startDate: "2026-01-01" })).rejects.toThrow(
      "get_dashboard_summary does not accept parameters.",
    );
    expect(getOperationsOverview).not.toHaveBeenCalled();
  });

  it("publishes the dashboard tool for every authenticated administrator", () => {
    expect(WEBMCP_TOOL_SECTIONS[0]).toMatchObject({
      key: "dashboard",
      title: "Dashboard",
    });
    expect(WEBMCP_TOOL_CATALOG[0]).toMatchObject({
      name: "get_dashboard_summary",
      sectionKey: "dashboard",
      permissionKeys: [],
      accessMode: "read",
    });
  });

  it("documents Operations Overview data instead of WebMCP runtime telemetry", () => {
    const tool = WEBMCP_TOOL_CATALOG[0];

    expect(tool.outputExample).toMatchObject({
      policy: { highValueAmount: 10000 },
      scope: { funding: { access: "all", canExport: true } },
      metrics: { netFunding: { status: "ready" } },
      attentionQueue: { items: [], total: 0, truncated: false },
      fundingTrend: { status: "ready", points: [] },
      sales: { status: "ready" },
      ib: { status: "ready" },
      recentActivity: { status: "ready", items: [] },
    });
    expect(tool.outputExample).not.toHaveProperty("tools");
    expect(tool.outputExample).not.toHaveProperty("executions");
  });
});
