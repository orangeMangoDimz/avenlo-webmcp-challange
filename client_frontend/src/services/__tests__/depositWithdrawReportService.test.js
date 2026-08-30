import { describe, it, expect, vi, beforeEach } from "vitest";

vi.mock("@/services/api", () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

import api from "@/services/api";
import depositWithdrawReportService from "@/services/depositWithdrawReportService";

beforeEach(() => {
  vi.clearAllMocks();
});

describe("depositWithdrawReportService.getList", () => {
  it("fetches list with pagination and date params", async () => {
    const params = { page: 1, per_page: 10, start_date: "2026-01-01" };
    api.get.mockResolvedValueOnce({
      success: true,
      data: [{ id: 1, totalDeposit: 100 }],
    });

    const result = await depositWithdrawReportService.getList(params);

    expect(api.get).toHaveBeenCalledWith(
      "/client/deposit-withdraw-report/list",
      { params },
    );
    expect(result.data).toHaveLength(1);
  });

  it("uses empty params object when none are provided", async () => {
    api.get.mockResolvedValueOnce({ success: true, data: [] });

    await depositWithdrawReportService.getList();

    expect(api.get).toHaveBeenCalledWith(
      "/client/deposit-withdraw-report/list",
      { params: {} },
    );
  });
});

describe("depositWithdrawReportService.export", () => {
  it("posts selected items with query params", async () => {
    const params = { start_date: "2026-01-01" };
    const items = [{ id: 1, type: "Direct Client" }];
    api.post.mockResolvedValueOnce({ success: true, jobId: "dwr_1" });

    const result = await depositWithdrawReportService.export(params, items);

    expect(api.post).toHaveBeenCalledWith(
      "/client/deposit-withdraw-report/export",
      { items },
      { params },
    );
    expect(result.jobId).toBe("dwr_1");
  });
});

describe("depositWithdrawReportService export helpers", () => {
  it("getActiveExport hits export-active", async () => {
    api.get.mockResolvedValueOnce({ data: { active: false } });
    await depositWithdrawReportService.getActiveExport();
    expect(api.get).toHaveBeenCalledWith(
      "/client/deposit-withdraw-report/export-active",
    );
  });

  it("getExportStatus passes jobId", async () => {
    api.get.mockResolvedValueOnce({ data: { status: "running" } });
    await depositWithdrawReportService.getExportStatus("dwr_abc");
    expect(api.get).toHaveBeenCalledWith(
      "/client/deposit-withdraw-report/export-status",
      { params: { jobId: "dwr_abc" } },
    );
  });

  it("cancelExport posts jobId to export-cancel endpoint", async () => {
    api.post.mockResolvedValueOnce({
      success: true,
      data: { jobId: "dwr_abc", status: "cancelled" },
    });

    const result = await depositWithdrawReportService.cancelExport("dwr_abc");

    expect(api.post).toHaveBeenCalledWith(
      "/client/deposit-withdraw-report/export-cancel",
      { jobId: "dwr_abc" },
    );
    expect(result.data.status).toBe("cancelled");
  });

  it("propagates error when cancelExport fails", async () => {
    api.post.mockRejectedValueOnce({
      success: false,
      message: "Cannot cancel completed job",
    });

    await expect(
      depositWithdrawReportService.cancelExport("dwr_abc"),
    ).rejects.toMatchObject({
      message: "Cannot cancel completed job",
    });
  });

  it("downloadExport uses blob responseType", async () => {
    api.get.mockResolvedValueOnce({ data: new Blob(["a"]) });
    await depositWithdrawReportService.downloadExport("dwr_abc");
    expect(api.get).toHaveBeenCalledWith(
      "/client/deposit-withdraw-report/export-download",
      { params: { jobId: "dwr_abc" }, responseType: "blob" },
    );
  });
});
