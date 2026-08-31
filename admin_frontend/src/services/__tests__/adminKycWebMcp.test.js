/** @vitest-environment jsdom */

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  createGetKycAnswersTool,
  createGetKycDocumentsTool,
  createGetKycProgressTool,
  createGetKycTimelineTool,
  createGetKycTool,
  createSearchKycTool,
  normalizeKycSearchInput,
  normalizeKycSubmissionInput,
  registerAdminKycWebMcpTools,
} from "../adminKycWebMcp";
import {
  WEBMCP_TOOL_CATALOG,
  WEBMCP_TOOL_SECTIONS,
  groupWebMcpTools,
} from "../adminWebMcpCatalog";

const authStore = {
  isAuthenticated: true,
  hasPermission: vi.fn((permission) => permission === "page_kyclist_readonly"),
};

const summary = {
  submissionId: 123,
  status: "pending",
  client: { id: 42, name: "John Smith", maskedEmail: "j***@example.com" },
  attentionItems: [{ code: "UNASSIGNED_REVIEWER" }],
};

const queueSubmission = {
  submissionId: 123,
  status: "pending",
  client: { name: "John Smith", maskedEmail: "j***@example.com" },
};

const webMcpApi = {
  searchKyc: vi.fn(async () => ({
    submissions: [queueSubmission],
    pagination: { page: 1, limit: 25, total: 1 },
  })),
  getKyc: vi.fn(async () => summary),
  getKycAnswers: vi.fn(async () => ({ submissionId: 123, categories: [] })),
  getKycDocuments: vi.fn(async () => ({ submissionId: 123, fileQuestions: [] })),
  getKycProgress: vi.fn(async () => ({ submissionId: 123, completionPercentage: 75 })),
  getKycTimeline: vi.fn(async () => ({ submissionId: 123, events: [] })),
};

describe("admin KYC WebMCP tools", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    delete document.modelContext;
    vi.restoreAllMocks();
  });

  it("normalizes queue filters and canonical statuses", () => {
    expect(
      normalizeKycSearchInput({
        email: " john@example.com ",
        country: " Indonesia ",
        status: "submitted",
        assigned: false,
        minWaitingHours: "24",
        page: "2",
        limit: 10,
      }),
    ).toEqual({
      email: "john@example.com",
      country: "Indonesia",
      status: "pending",
      assigned: false,
      minWaitingHours: 24,
      page: 2,
      limit: 10,
    });

    expect(normalizeKycSearchInput({ status: "pending_documents" })).toEqual({
      status: "requires_documents",
      page: 1,
      limit: 25,
    });
  });

  it.each([
    {},
    { status: "fraudulent" },
    { assigned: "yes" },
    { country: "ID", limit: 51 },
    { unsupported: true },
  ])("rejects unsafe or empty KYC searches", (input) => {
    expect(() => normalizeKycSearchInput(input)).toThrow();
  });

  it("requires one exact positive submission ID", () => {
    expect(normalizeKycSubmissionInput({ submissionId: "123" })).toEqual({
      submissionId: 123,
    });
    expect(() => normalizeKycSubmissionInput({})).toThrow(/submissionId/i);
    expect(() => normalizeKycSubmissionInput({ submissionId: 0 })).toThrow(
      /submissionId/i,
    );
  });

  it("searches KYC submissions with normalized filters", async () => {
    const tool = createSearchKycTool({ authStore, webMcpApi });
    const result = await tool.execute({
      status: "submitted",
      assigned: false,
      minWaitingHours: "24",
    });

    expect(webMcpApi.searchKyc).toHaveBeenCalledWith({
      status: "pending",
      assigned: false,
      minWaitingHours: 24,
      page: 1,
      limit: 25,
    });
    expect(result.submissions[0].client.maskedEmail).toBe("j***@example.com");
  });

  it.each([
    [createGetKycTool, "getKyc", summary],
    [createGetKycAnswersTool, "getKycAnswers", { submissionId: 123, categories: [] }],
    [createGetKycDocumentsTool, "getKycDocuments", { submissionId: 123, fileQuestions: [] }],
    [createGetKycProgressTool, "getKycProgress", { submissionId: 123, completionPercentage: 75 }],
    [createGetKycTimelineTool, "getKycTimeline", { submissionId: 123, events: [] }],
  ])("calls the exact submission endpoint for each detail tool", async (createTool, apiMethod, expected) => {
    const tool = createTool({ authStore, webMcpApi });
    const result = await tool.execute({ submissionId: "123" });

    expect(webMcpApi[apiMethod]).toHaveBeenCalledWith({ submissionId: 123 });
    expect(result).toEqual(expected);
  });

  it("maps hidden or missing submissions to a safe error", async () => {
    webMcpApi.getKyc.mockRejectedValueOnce({ statusCode: 404 });
    const tool = createGetKycTool({ authStore, webMcpApi });

    await expect(tool.execute({ submissionId: 123 })).rejects.toThrow(
      "KYC submission not found.",
    );
  });

  it("blocks calls without the KYC read permission", async () => {
    const unauthorizedStore = {
      isAuthenticated: true,
      hasPermission: vi.fn(() => false),
    };
    const tool = createGetKycTool({
      authStore: unauthorizedStore,
      webMcpApi,
    });

    await expect(tool.execute({ submissionId: 123 })).rejects.toThrow(
      /not authorized/i,
    );
    expect(webMcpApi.getKyc).not.toHaveBeenCalled();
  });

  it("registers exactly the six approved tools and aborts them on cleanup", async () => {
    const registerTool = vi.fn(() => Promise.resolve());
    document.modelContext = { registerTool };

    const cleanup = registerAdminKycWebMcpTools({ authStore, webMcpApi });
    await Promise.resolve();

    expect(registerTool.mock.calls.map(([tool]) => tool.name)).toEqual([
      "search_kyc",
      "get_kyc",
      "get_kyc_answers",
      "get_kyc_documents",
      "get_kyc_progress",
      "get_kyc_timeline",
    ]);
    expect(registerTool.mock.calls.every(([tool]) => tool.inputSchema)).toBe(true);

    const signal = registerTool.mock.calls[0][1].signal;
    cleanup();
    expect(signal.aborted).toBe(true);
  });

  it("does not register KYC tools for an unauthorized admin", () => {
    const registerTool = vi.fn();
    document.modelContext = { registerTool };

    registerAdminKycWebMcpTools({
      authStore: { isAuthenticated: true, hasPermission: vi.fn(() => false) },
      webMcpApi,
    });

    expect(registerTool).not.toHaveBeenCalled();
  });

  it("publishes only the approved KYC tools in a dedicated catalog section", () => {
    const kycTools = WEBMCP_TOOL_CATALOG.filter(
      ({ sectionKey }) => sectionKey === "kyc",
    );
    expect(WEBMCP_TOOL_SECTIONS.map(({ key }) => key)).toContain("kyc");
    expect(kycTools.map(({ name }) => name)).toEqual([
      "search_kyc",
      "get_kyc",
      "get_kyc_answers",
      "get_kyc_documents",
      "get_kyc_progress",
      "get_kyc_timeline",
    ]);
    expect(groupWebMcpTools(WEBMCP_TOOL_CATALOG)).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ key: "kyc", tools: kycTools }),
      ]),
    );
  });
});
