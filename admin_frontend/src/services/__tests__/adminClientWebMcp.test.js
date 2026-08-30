/** @vitest-environment jsdom */

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  createGetClientTool,
  createGetClientDocumentsTool,
  createGetClientRecentTransactionsTool,
  createGetClientTradingAccountsTool,
  createExportClientsTool,
  createExportClientTransactionsTool,
  createNavigateToClientTool,
  createSearchClientsTool,
  createSearchTransactionsTool,
  createGetTransactionTool,
  registerAdminClientWebMcpTools,
} from "../adminClientWebMcp";
import {
  WEBMCP_TOOL_CATALOG,
  WEBMCP_TOOL_SECTIONS,
  groupWebMcpTools,
} from "../adminWebMcpCatalog";

const authStore = {
  isAuthenticated: true,
  hasPermission: vi.fn(() => true),
};

const webMcpApi = {
  getClient: vi.fn(async () => ({
    id: 42,
    name: "Jane Smith",
    email: "jane@example.com",
    passwordHash: "must-not-be-returned",
  })),
  searchClients: vi.fn(async () => ({
    clients: [{ id: 42, name: "Jane Smith", tags: ["VIP"] }],
    pagination: { page: 1, perPage: 25, total: 1 },
  })),
  getClientDocuments: vi.fn(async () => ({
    clientId: 42,
    documents: [{ id: "doc-1", title: "Passport", source: "kyc" }],
  })),
  getClientTradingAccounts: vi.fn(async () => ({
    clientId: 42,
    accounts: [{ id: 7, accountNumber: "100007", platformKey: "mt5" }],
  })),
  getClientRecentTransactions: vi.fn(async () => ({
    clientId: 42,
    transactions: [{ id: 9, type: "deposit", amount: 100 }],
  })),
  searchTransactions: vi.fn(async () => ({
    transactions: [{ transactionId: "DEP-2026-0009", type: "deposit" }],
    pagination: { page: 1, perPage: 25, total: 1 },
  })),
  getTransaction: vi.fn(async () => ({
    transaction: { transactionId: "DEP-2026-0009", type: "deposit" },
  })),
  exportClients: vi.fn(async () => ({
    jobId: "wmcp_client_42",
    queued: true,
  })),
  exportClientTransactions: vi.fn(async () => ({
    jobId: "wmcp_transaction_42",
    queued: true,
  })),
};

const router = {
  push: vi.fn(async () => {}),
};

describe("admin client WebMCP tools", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    webMcpApi.getClient.mockResolvedValue({
      id: 42,
      name: "Jane Smith",
      email: "jane@example.com",
      passwordHash: "must-not-be-returned",
    });
    webMcpApi.searchClients.mockResolvedValue({
      clients: [{ id: 42, name: "Jane Smith", tags: ["VIP"] }],
      pagination: { page: 1, perPage: 25, total: 1 },
    });
    webMcpApi.getClientDocuments.mockResolvedValue({
      clientId: 42,
      documents: [{ id: "doc-1", title: "Passport", source: "kyc" }],
    });
    webMcpApi.getClientTradingAccounts.mockResolvedValue({
      clientId: 42,
      accounts: [{ id: 7, accountNumber: "100007", platformKey: "mt5" }],
    });
    webMcpApi.getClientRecentTransactions.mockResolvedValue({
      clientId: 42,
      transactions: [{ id: 9, type: "deposit", amount: 100 }],
    });
    webMcpApi.searchTransactions.mockResolvedValue({
      transactions: [{ transactionId: "DEP-2026-0009", type: "deposit" }],
      pagination: { page: 1, perPage: 25, total: 1 },
    });
    webMcpApi.getTransaction.mockResolvedValue({
      transaction: { transactionId: "DEP-2026-0009", type: "deposit" },
    });
    webMcpApi.exportClients.mockResolvedValue({
      jobId: "wmcp_client_42",
      queued: true,
    });
    webMcpApi.exportClientTransactions.mockResolvedValue({
      jobId: "wmcp_transaction_42",
      queued: true,
    });

    vi.stubGlobal("URL", {
      ...URL,
      createObjectURL: vi.fn(() => "blob:export"),
      revokeObjectURL: vi.fn(),
    });
    vi.spyOn(window, "open").mockImplementation(() => ({}));
  });

  it("exposes the registered tools in the admin catalog", () => {
    expect(WEBMCP_TOOL_CATALOG.map(({ name }) => name)).toEqual([
      "get_client",
      "navigate_to_client",
      "search_clients",
      "get_client_documents",
      "get_client_trading_accounts",
      "get_client_recent_transactions",
      "export_clients",
      "export_client_transactions",
      "search_transactions",
      "get_transaction",
    ]);
    expect(WEBMCP_TOOL_CATALOG.every(({ description }) => description)).toBe(
      true,
    );
    expect(
      WEBMCP_TOOL_CATALOG.every(
        ({
          inputSummary,
          inputExample,
          outputSummary,
          outputExample,
          sectionKey,
          accessMode,
        }) =>
          inputSummary &&
          typeof inputExample === "object" &&
          outputSummary &&
          typeof outputExample === "object" &&
          sectionKey &&
          accessMode,
      ),
    ).toBe(true);
    expect(
      WEBMCP_TOOL_CATALOG.every(
        ({ inputFields }) =>
          inputFields?.length > 0 &&
          inputFields.every(
            ({ name, type, requirement, description }) =>
              name && type && requirement && description,
          ),
      ),
    ).toBe(true);
  });

  it("groups catalog tools into the Client section", () => {
    expect(WEBMCP_TOOL_SECTIONS.map(({ key }) => key)).toEqual([
      "client",
      "transactions",
    ]);
    expect(groupWebMcpTools(WEBMCP_TOOL_CATALOG)).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          key: "client",
          tools: expect.arrayContaining([
            expect.objectContaining({ name: "get_client" }),
            expect.objectContaining({
              name: "get_client_recent_transactions",
            }),
          ]),
        }),
        expect.objectContaining({
          key: "transactions",
          tools: expect.arrayContaining([
            expect.objectContaining({ name: "search_transactions" }),
            expect.objectContaining({ name: "get_transaction" }),
          ]),
        }),
      ]),
    );
  });

  afterEach(() => {
    delete document.modelContext;
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  });

  it("describes only the get_client tool and its three lookup inputs", () => {
    const tool = createGetClientTool({ authStore, webMcpApi });

    expect(tool.name).toBe("get_client");
    expect(tool.inputSchema.properties).toEqual(
      expect.objectContaining({
        email: expect.any(Object),
        id: expect.any(Object),
        code: expect.any(Object),
      }),
    );
  });

  it.each([
    [{ email: "jane@example.com" }],
    [{ id: 42 }],
    [{ code: "IB-2026-042" }],
  ])(
    "passes exactly one supported identifier to the dedicated API",
    async (input) => {
      const tool = createGetClientTool({ authStore, webMcpApi });

      const result = await tool.execute(input);

      expect(webMcpApi.getClient).toHaveBeenLastCalledWith(input);
      expect(result).toEqual({
        client: {
          id: 42,
          name: "Jane Smith",
          email: "jane@example.com",
          country: null,
          status: null,
          kycStatus: null,
          manager: null,
          registeredAt: null,
          lastLoginAt: null,
          isIb: false,
          ibCode: null,
        },
      });
    },
  );

  it("searches clients with country, tag, and never-logged-in filters", async () => {
    const tool = createSearchClientsTool({ authStore, webMcpApi });
    const input = {
      country: "Indonesia",
      tag: "VIP",
      neverLoggedIn: true,
      limit: 25,
    };

    const result = await tool.execute(input);

    expect(webMcpApi.searchClients).toHaveBeenLastCalledWith({
      ...input,
      page: 1,
    });
    expect(result).toEqual({
      clients: [{ id: 42, name: "Jane Smith", tags: ["VIP"] }],
      pagination: { page: 1, perPage: 25, total: 1 },
    });
  });

  it("returns client documents as a structured object", async () => {
    const tool = createGetClientDocumentsTool({ authStore, webMcpApi });

    const result = await tool.execute({ id: 42 });

    expect(webMcpApi.getClientDocuments).toHaveBeenLastCalledWith({ id: 42 });
    expect(result).toEqual({
      clientId: 42,
      documents: [{ id: "doc-1", title: "Passport", source: "kyc" }],
    });
  });

  it("returns trading accounts as a structured object", async () => {
    const tool = createGetClientTradingAccountsTool({ authStore, webMcpApi });

    const result = await tool.execute({ id: 42 });

    expect(webMcpApi.getClientTradingAccounts).toHaveBeenLastCalledWith({
      id: 42,
    });
    expect(result).toEqual({
      clientId: 42,
      accounts: [{ id: 7, accountNumber: "100007", platformKey: "mt5" }],
    });
  });

  it("returns recent transactions as a structured object", async () => {
    const tool = createGetClientRecentTransactionsTool({
      authStore,
      webMcpApi,
    });

    const result = await tool.execute({ id: 42, type: "all", limit: 10 });

    expect(webMcpApi.getClientRecentTransactions).toHaveBeenLastCalledWith({
      id: 42,
      type: "all",
      limit: 10,
      page: 1,
    });
    expect(result).toEqual({
      clientId: 42,
      transactions: [{ id: 9, type: "deposit", amount: 100 }],
    });
  });

  it("searches funding transactions with type, amount, and date filters", async () => {
    const tool = createSearchTransactionsTool({ authStore, webMcpApi });
    const result = await tool.execute({
      type: "withdrawals",
      status: "pending_review",
      minAmount: "10000",
      dateFrom: "2026-08-24",
      page: 1,
      limit: 25,
    });

    expect(webMcpApi.searchTransactions).toHaveBeenLastCalledWith({
      type: "withdrawal",
      status: "pending_review",
      minAmount: 10000,
      dateFrom: "2026-08-24",
      page: 1,
      limit: 25,
    });
    expect(result.transactions).toHaveLength(1);
  });

  it("gets one transaction by exact transaction ID", async () => {
    const tool = createGetTransactionTool({ authStore, webMcpApi });
    const result = await tool.execute({ transactionId: " DEP-2026-0009 " });

    expect(webMcpApi.getTransaction).toHaveBeenLastCalledWith({
      transactionId: "DEP-2026-0009",
    });
    expect(result.transaction.transactionId).toBe("DEP-2026-0009");
  });

  it("exports selected clients and triggers a browser download", async () => {
    const routerWithProgress = {
      ...router,
      resolve: vi.fn(() => ({
        href: "/#/webmcp/export-progress?jobId=wmcp_client_42",
      })),
    };
    const tool = createExportClientsTool({
      authStore,
      webMcpApi,
      router: routerWithProgress,
    });
    const result = await tool.execute({
      clientIds: ["42"],
      country: " Indonesia ",
      registeredFrom: "2026-01-01",
      registeredTo: "2026-01-31",
    });

    expect(webMcpApi.exportClients).toHaveBeenLastCalledWith({
      clientIds: [42],
      country: "Indonesia",
      registeredFrom: "2026-01-01",
      registeredTo: "2026-01-31",
    });
    expect(routerWithProgress.resolve).toHaveBeenLastCalledWith({
      name: "webmcp-export-progress",
      query: { jobId: "wmcp_client_42" },
    });
    expect(window.open).toHaveBeenLastCalledWith(
      "/#/webmcp/export-progress?jobId=wmcp_client_42",
      "_blank",
      "noopener",
    );
    expect(result).toEqual({
      success: true,
      jobId: "wmcp_client_42",
      progressUrl: "/#/webmcp/export-progress?jobId=wmcp_client_42",
      opened: true,
      queued: true,
      reused: false,
      downloadRequested: false,
      agentNextAction: "none",
      retryPolicy: "Only retry after the user reports that no file appeared.",
    });
  });

  it("exports selected client transactions with date and type filters", async () => {
    const routerWithProgress = {
      ...router,
      resolve: vi.fn(() => ({
        href: "/#/webmcp/export-progress?jobId=wmcp_transaction_42",
      })),
    };
    const tool = createExportClientTransactionsTool({
      authStore,
      webMcpApi,
      router: routerWithProgress,
    });
    const result = await tool.execute({
      clientIds: [42, "43"],
      dateFrom: "2026-08-01",
      dateTo: "2026-08-30",
      type: "withdrawals",
      status: "completed",
    });

    expect(webMcpApi.exportClientTransactions).toHaveBeenLastCalledWith({
      clientIds: [42, 43],
      dateFrom: "2026-08-01",
      dateTo: "2026-08-30",
      type: "withdrawal",
      status: "completed",
    });
    expect(window.open).toHaveBeenLastCalledWith(
      "/#/webmcp/export-progress?jobId=wmcp_transaction_42",
      "_blank",
      "noopener",
    );
    expect(result).toEqual({
      success: true,
      jobId: "wmcp_transaction_42",
      progressUrl: "/#/webmcp/export-progress?jobId=wmcp_transaction_42",
      opened: true,
      queued: true,
      reused: false,
      downloadRequested: false,
      agentNextAction: "none",
      retryPolicy: "Only retry after the user reports that no file appeared.",
    });
  });

  it("rejects empty export selections and invalid dates before calling the API", async () => {
    const clientTool = createExportClientsTool({ authStore, webMcpApi });
    const transactionTool = createExportClientTransactionsTool({
      authStore,
      webMcpApi,
    });

    await expect(clientTool.execute({ clientIds: [] })).rejects.toThrow(
      /clientIds/i,
    );
    await expect(
      transactionTool.execute({ clientIds: [42], dateFrom: "bad" }),
    ).rejects.toThrow(/dateFrom/i);
    expect(webMcpApi.exportClients).not.toHaveBeenCalled();
    expect(webMcpApi.exportClientTransactions).not.toHaveBeenCalled();
  });

  it.each([
    [{ email: "jane@example.com" }],
    [{ id: 42 }],
    [{ code: "IB-2026-042" }],
  ])(
    "navigates to the resolved client for one supported identifier",
    async (input) => {
      const tool = createNavigateToClientTool({ authStore, webMcpApi, router });

      const result = await tool.execute(input);

      expect(webMcpApi.getClient).toHaveBeenLastCalledWith(input);
      expect(router.push).toHaveBeenLastCalledWith({
        name: "client-detail",
        query: { id: 42 },
      });
      expect(result).toEqual({
        success: true,
        clientId: 42,
        route: "/client-detail?id=42",
      });
    },
  );

  it.each([
    {},
    { email: "jane@example.com", id: 42 },
    { email: " " },
    { code: " " },
    { id: 0 },
  ])(
    "rejects input that does not contain exactly one valid identifier",
    async (input) => {
      const tool = createGetClientTool({ authStore, webMcpApi });

      await expect(tool.execute(input)).rejects.toThrow(
        /exactly one|valid|between|integer/i,
      );
      expect(webMcpApi.getClient).not.toHaveBeenCalled();
    },
  );

  it("rejects calls from an unauthenticated or unauthorized admin", async () => {
    const tool = createGetClientTool({
      authStore: { isAuthenticated: false, hasPermission: vi.fn(() => false) },
      webMcpApi,
    });

    await expect(tool.execute({ id: 42 })).rejects.toThrow(/not authorized/i);
    expect(webMcpApi.getClient).not.toHaveBeenCalled();
  });

  it("registers all permitted tools and unregisters them on cleanup", async () => {
    const registerTool = vi.fn(() => Promise.resolve());
    document.modelContext = { registerTool };

    const cleanup = registerAdminClientWebMcpTools({
      authStore,
      webMcpApi,
      router,
    });
    await Promise.resolve();

    expect(registerTool).toHaveBeenCalledTimes(10);
    expect(registerTool.mock.calls.map(([tool]) => tool.name)).toEqual([
      "get_client",
      "navigate_to_client",
      "search_clients",
      "get_client_documents",
      "get_client_trading_accounts",
      "get_client_recent_transactions",
      "export_clients",
      "export_client_transactions",
      "search_transactions",
      "get_transaction",
    ]);

    const signal = registerTool.mock.calls[0][1].signal;
    cleanup();
    expect(signal.aborted).toBe(true);
  });
});
