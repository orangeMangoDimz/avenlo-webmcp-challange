/** @vitest-environment jsdom */

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  createGetClientIbUplineTool,
  createGetIbClientsTool,
  createGetIbNetworkStatsTool,
  createGetIbNetworkTool,
  createGetIbPartnerTool,
  createNavigateToIbTool,
  normalizeClientUplineInput,
  normalizeIbClientsInput,
  normalizeIbLookupInput,
  normalizeIbNetworkInput,
  registerAdminIbWebMcpTools,
} from "../adminIbWebMcp";
import {
  WEBMCP_TOOL_CATALOG,
  WEBMCP_TOOL_SECTIONS,
  groupWebMcpTools,
} from "../adminWebMcpCatalog";

const authStore = {
  isAuthenticated: true,
  hasPermission: vi.fn((permission) => permission === "page_iblist_readonly"),
};

const ib = {
  id: 42,
  clientId: 7,
  code: "IB-2026-042",
  name: "Jane Partner",
  email: "partner@example.com",
  country: "ID",
  status: "approved",
  ibType: "Master IB",
  tier: { id: 2, level: 2, name: "Gold" },
  registeredAt: "2026-01-15T10:30:00Z",
};

const webMcpApi = {
  getIbPartner: vi.fn(async () => ({ ib })),
  getIbNetwork: vi.fn(async () => ({
    ib,
    maxDepth: 1,
    children: [],
  })),
  getIbNetworkStats: vi.fn(async () => ({
    ib,
    totals: {
      directIbs: 2,
      totalDescendantIbs: 4,
      directClients: 5,
      totalNetworkClients: 12,
      totalNetworkMembers: 16,
    },
  })),
  getIbClients: vi.fn(async () => ({
    ib,
    relationship: "direct",
    clients: [],
    pagination: { page: 1, limit: 25, total: 0, totalPages: 0, hasMore: false },
  })),
  getClientIbUpline: vi.fn(async () => ({
    client: { id: 9, name: "Client User", email: "client@example.com" },
    upline: [ib],
    complete: true,
  })),
};

const router = { push: vi.fn(async () => {}) };

describe("admin IB WebMCP tools", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    delete document.modelContext;
    vi.restoreAllMocks();
  });

  it("normalizes exactly one IB selector", () => {
    expect(normalizeIbLookupInput({ code: " IB-2026-042 " })).toEqual({
      code: "IB-2026-042",
    });
    expect(normalizeIbLookupInput({ id: "42" })).toEqual({ id: 42 });
    expect(() => normalizeIbLookupInput({})).toThrow(/exactly one/i);
    expect(() =>
      normalizeIbLookupInput({ id: 42, email: "partner@example.com" }),
    ).toThrow(/exactly one/i);
    expect(() => normalizeIbLookupInput({ email: "bad" })).toThrow(/valid/i);
  });

  it("normalizes network depth and client pagination", () => {
    expect(normalizeIbNetworkInput({ id: 42, maxDepth: "1" })).toEqual({
      id: 42,
      maxDepth: 1,
    });
    expect(normalizeIbNetworkInput({ code: "IB-42" })).toEqual({
      code: "IB-42",
    });
    expect(
      normalizeIbClientsInput({
        id: 42,
        relationship: "ALL",
        page: "2",
        limit: 50,
      }),
    ).toEqual({ id: 42, relationship: "all", page: 2, limit: 50 });
    expect(() => normalizeIbClientsInput({ id: 42 })).toThrow(/relationship/i);
    expect(() =>
      normalizeIbClientsInput({
        id: 42,
        relationship: "children",
      }),
    ).toThrow(/direct|all/i);
  });

  it("normalizes client upline selectors without accepting IB codes", () => {
    expect(
      normalizeClientUplineInput({ email: " client@example.com " }),
    ).toEqual({ email: "client@example.com" });
    expect(() => normalizeClientUplineInput({ code: "IB-42" })).toThrow(
      /supported|exactly one/i,
    );
  });

  it.each([
    [createGetIbPartnerTool, "getIbPartner", { id: 42 }],
    [createGetIbNetworkTool, "getIbNetwork", { id: 42, maxDepth: 1 }],
    [createGetIbNetworkStatsTool, "getIbNetworkStats", { code: "IB-2026-042" }],
    [
      createGetIbClientsTool,
      "getIbClients",
      { id: 42, relationship: "direct", page: 1, limit: 25 },
    ],
    [
      createGetClientIbUplineTool,
      "getClientIbUpline",
      { email: "client@example.com" },
    ],
  ])(
    "calls the dedicated API for each read tool",
    async (createTool, method, input) => {
      const tool = createTool({ authStore, webMcpApi });
      await tool.execute(input);
      expect(webMcpApi[method]).toHaveBeenCalledWith(input);
    },
  );

  it("navigates to the resolved IB list detail", async () => {
    const tool = createNavigateToIbTool({ authStore, webMcpApi, router });

    const result = await tool.execute({ code: "IB-2026-042" });

    expect(router.push).toHaveBeenCalledWith({
      name: "ib-list",
      query: { search: "IB-2026-042", detailId: "42" },
    });
    expect(result).toEqual({
      success: true,
      ibId: 42,
      route: "/ib-list?search=IB-2026-042&detailId=42",
    });
  });

  it("blocks calls without IB read permission", async () => {
    const tool = createGetIbPartnerTool({
      authStore: { isAuthenticated: true, hasPermission: vi.fn(() => false) },
      webMcpApi,
    });

    await expect(tool.execute({ id: 42 })).rejects.toThrow(/not authorized/i);
    expect(webMcpApi.getIbPartner).not.toHaveBeenCalled();
  });

  it("maps hidden IBs to a safe not-found error", async () => {
    webMcpApi.getIbPartner.mockRejectedValueOnce({ statusCode: 404 });
    const tool = createGetIbPartnerTool({ authStore, webMcpApi });

    await expect(tool.execute({ id: 42 })).rejects.toThrow(
      "IB partner not found.",
    );
  });

  it("registers exactly six IB tools and aborts them on cleanup", async () => {
    const registerTool = vi.fn(() => Promise.resolve());
    document.modelContext = { registerTool };

    const cleanup = registerAdminIbWebMcpTools({
      authStore,
      webMcpApi,
      router,
    });
    await Promise.resolve();

    expect(registerTool.mock.calls.map(([tool]) => tool.name)).toEqual([
      "get_ib_partner",
      "get_ib_network",
      "get_ib_network_stats",
      "get_ib_clients",
      "get_client_ib_upline",
      "navigate_to_ib",
    ]);
    expect(
      registerTool.mock.calls.every(
        ([tool]) =>
          tool.annotations?.readOnlyHint === true &&
          tool.annotations?.untrustedContentHint === true,
      ),
    ).toBe(true);

    const signal = registerTool.mock.calls[0][1].signal;
    cleanup();
    expect(signal.aborted).toBe(true);
  });

  it("publishes IB tools in a dedicated catalog section", () => {
    const ibTools = WEBMCP_TOOL_CATALOG.filter(
      ({ sectionKey }) => sectionKey === "ib",
    );
    expect(WEBMCP_TOOL_SECTIONS.map(({ key }) => key)).toContain("ib");
    expect(ibTools.map(({ name }) => name)).toEqual([
      "get_ib_partner",
      "get_ib_network",
      "get_ib_network_stats",
      "get_ib_clients",
      "get_client_ib_upline",
      "navigate_to_ib",
    ]);
    expect(groupWebMcpTools(WEBMCP_TOOL_CATALOG)).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ key: "ib", tools: ibTools }),
      ]),
    );
  });
});
