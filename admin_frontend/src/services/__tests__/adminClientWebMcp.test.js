/** @vitest-environment jsdom */

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  createGetClientTool,
  createNavigateToClientTool,
  registerAdminClientWebMcpTools,
} from "../adminClientWebMcp";

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
  });

  afterEach(() => {
    delete document.modelContext;
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

  it("registers both tools and unregisters them on cleanup", async () => {
    const registerTool = vi.fn(() => Promise.resolve());
    document.modelContext = { registerTool };

    const cleanup = registerAdminClientWebMcpTools({
      authStore,
      webMcpApi,
      router,
    });
    await Promise.resolve();

    expect(registerTool).toHaveBeenCalledTimes(2);
    expect(registerTool.mock.calls.map(([tool]) => tool.name)).toEqual([
      "get_client",
      "navigate_to_client",
    ]);

    const signal = registerTool.mock.calls[0][1].signal;
    cleanup();
    expect(signal.aborted).toBe(true);
  });
});
