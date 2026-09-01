import { describe, expect, it } from "vitest";
import {
  buildEvidenceCsv,
  buildOperationsRecordRoute,
  createWebMcpActivityStore,
  defaultOperationsFilters,
  normalizeOperationsOverview,
  paginateItems,
} from "../adminWebMcpOperations";

const responseFixture = {
  generatedAt: "2026-09-01T08:00:00Z",
  period: {
    startDate: "2026-08-26",
    endDate: "2026-09-01",
    timezone: { offsetMinutes: 420, label: "UTC+07:00" },
  },
  policy: {
    highValueAmount: 10000,
    kycOverdueHours: 24,
    queueLimit: 25,
    staleAfterSeconds: 300,
  },
  scope: {
    funding: { access: "all", canExport: true },
    kyc: { access: "restricted", canExport: false },
  },
  metrics: {
    netFunding: {
      status: "ready",
      totals: [{ currency: "USD", deposits: 1200, withdrawals: 200, netFlow: 1000 }],
    },
    overdueKyc: { status: "restricted" },
  },
  attentionQueue: { items: [], total: 0, truncated: false },
  fundingTrend: { status: "ready", points: [] },
  sales: { status: "restricted" },
  ib: { status: "restricted" },
  sectionErrors: [],
};

describe("admin WebMCP operations dashboard helpers", () => {
  it("defaults to the last seven calendar days in the browser offset", () => {
    expect(defaultOperationsFilters(new Date("2026-09-01T08:00:00Z"), 420)).toEqual({
      startDate: "2026-08-26",
      endDate: "2026-09-01",
      tzOffset: 420,
    });
  });

  it("normalizes ready and restricted sections without inventing protected values", () => {
    const result = normalizeOperationsOverview(responseFixture);
    expect(result.metrics.netFunding.totals[0].netFlow).toBe(1000);
    expect(result.metrics.overdueKyc).toEqual({ status: "restricted" });
    expect(result.scope.kyc).toEqual({ access: "restricted", canExport: false });
  });

  it.each([
    [
      { kind: "transaction", transactionType: "deposit", recordId: 9, transactionId: "DEP-9" },
      { name: "deposits", query: { source: "webmcp-overview", search: "DEP-9", detailId: "9" } },
    ],
    [
      { kind: "kyc", submissionId: 7 },
      { name: "kyc-list", query: { source: "webmcp-overview", submissionId: "7" } },
    ],
    [
      { kind: "client", clientId: 42 },
      { name: "client-detail", query: { id: "42" } },
    ],
    [
      { kind: "audit", operationLogId: 11 },
      {
        name: "operation-log-report",
        query: { source: "webmcp", modelKey: "all", query: "11" },
      },
    ],
  ])("builds an exact route for %o", (item, route) => {
    expect(buildOperationsRecordRoute(item)).toEqual(route);
  });

  it("isolates, expires, and caps browser-local activity by administrator", () => {
    const values = new Map();
    const storage = {
      getItem: (key) => values.get(key) ?? null,
      setItem: (key, value) => values.set(key, value),
    };
    let now = Date.parse("2026-09-01T08:00:00Z");
    const store = createWebMcpActivityStore({
      storage,
      adminId: 7,
      now: () => now,
      limit: 2,
      retentionDays: 30,
    });

    store.record({ id: "one", kind: "investigation", label: "First" });
    now += 1000;
    store.record({ id: "two", kind: "export", label: "Second" });
    now += 1000;
    store.record({ id: "three", kind: "open", label: "Third" });

    expect(store.list().map(({ id }) => id)).toEqual(["three", "two"]);
    expect(createWebMcpActivityStore({ storage, adminId: 8 }).list()).toEqual([]);
  });

  it("escapes spreadsheet formulas when exporting visible evidence", () => {
    const csv = buildEvidenceCsv([
      {
        severity: "high",
        kind: "transaction",
        reason: "=HYPERLINK(\"https://bad.example\")",
        relatedLabel: "Deposit DEP-9",
        ageHours: 12,
      },
    ]);
    expect(csv).toContain("'=HYPERLINK");
    expect(csv).toContain("Severity,Type,Reason,Related record,Age hours");
  });

  it("paginates the returned priority window with stable page bounds", () => {
    expect(paginateItems([1, 2, 3, 4, 5], 2, 2)).toEqual({
      items: [3, 4],
      page: 2,
      perPage: 2,
      total: 5,
      totalPages: 3,
      from: 3,
      to: 4,
    });
    expect(paginateItems([1, 2], 99, 10).page).toBe(1);
    expect(paginateItems([], 1, 10).totalPages).toBe(1);
  });
});
