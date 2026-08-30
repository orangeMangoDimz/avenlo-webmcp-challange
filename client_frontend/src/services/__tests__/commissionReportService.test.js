import { describe, it, expect, vi, beforeEach } from "vitest";

// commissionReportService imports `./api` (shared Axios instance), not clientApi.
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
import commissionReportService from "@/services/commissionReportService";

beforeEach(() => {
  vi.clearAllMocks();
});

describe("commissionReportService.getStatistics", () => {
  it("fetches statistics with query params", async () => {
    const params = { start_date: "2026-01-01", end_date: "2026-01-31" };
    api.get.mockResolvedValueOnce({
      success: true,
      data: { totalCommission: 1500 },
    });

    const result = await commissionReportService.getStatistics(params);

    expect(api.get).toHaveBeenCalledWith(
      "/client/commission-report/statistics",
      { params },
    );
    expect(result.data.totalCommission).toBe(1500);
  });

  it("propagates error when statistics request fails", async () => {
    api.get.mockRejectedValueOnce({
      success: false,
      message: "Unauthorized",
    });

    await expect(commissionReportService.getStatistics()).rejects.toMatchObject(
      {
        success: false,
        message: "Unauthorized",
      },
    );
  });
});

describe("commissionReportService.getList", () => {
  it("fetches list with pagination params", async () => {
    const params = { page: 1, per_page: 20, search: "alice" };
    api.get.mockResolvedValueOnce({
      success: true,
      data: [{ id: 1, amount: 100 }],
      meta: { total: 1, page: 1 },
    });

    const result = await commissionReportService.getList(params);

    expect(api.get).toHaveBeenCalledWith("/client/commission-report/list", {
      params,
    });
    expect(result.data).toHaveLength(1);
  });

  it("uses empty params object when none are provided", async () => {
    api.get.mockResolvedValueOnce({ success: true, data: [] });

    await commissionReportService.getList();

    expect(api.get).toHaveBeenCalledWith("/client/commission-report/list", {
      params: {},
    });
  });
});

describe("commissionReportService.getDetail", () => {
  it("fetches detail with referral and type params", async () => {
    const params = { referral_id: 42, type: "direct" };
    api.get.mockResolvedValueOnce({
      success: true,
      data: [{ ticket: "T1", commission: 5 }],
    });

    const result = await commissionReportService.getDetail(params);

    expect(api.get).toHaveBeenCalledWith("/client/commission-report/detail", {
      params,
    });
    expect(result.data[0].ticket).toBe("T1");
  });
});

describe("commissionReportService.getActiveExport", () => {
  it("fetches the active export job for the current user", async () => {
    api.get.mockResolvedValueOnce({
      success: true,
      data: { jobId: "job-abc", status: "processing", progress: 40 },
    });

    const result = await commissionReportService.getActiveExport();

    expect(api.get).toHaveBeenCalledWith(
      "/client/commission-report/export-active",
    );
    expect(result.data.jobId).toBe("job-abc");
    expect(result.data.status).toBe("processing");
  });

  it("returns null data when no active export exists", async () => {
    api.get.mockResolvedValueOnce({ success: true, data: null });

    const result = await commissionReportService.getActiveExport();

    expect(result.data).toBeNull();
  });

  it("propagates error when active-export lookup fails", async () => {
    api.get.mockRejectedValueOnce(new Error("Network Error"));

    await expect(commissionReportService.getActiveExport()).rejects.toThrow(
      "Network Error",
    );
  });
});

describe("commissionReportService.export", () => {
  it("posts selected items and query params and returns a jobId", async () => {
    const params = {
      start_date: "2026-01-01",
      end_date: "2026-01-31",
      search: "",
    };
    const items = [{ referral_id: 1, type: "direct" }];
    api.post.mockResolvedValueOnce({
      success: true,
      data: { jobId: "job-xyz" },
    });

    const result = await commissionReportService.export(params, items);

    expect(api.post).toHaveBeenCalledWith(
      "/client/commission-report/export",
      { items },
      { params },
    );
    expect(result.data.jobId).toBe("job-xyz");
  });

  it("defaults to empty params and empty items when omitted", async () => {
    api.post.mockResolvedValueOnce({
      success: true,
      data: { jobId: "job-default" },
    });

    await commissionReportService.export();

    expect(api.post).toHaveBeenCalledWith(
      "/client/commission-report/export",
      { items: [] },
      { params: {} },
    );
  });

  it("propagates error when export enqueue fails", async () => {
    api.post.mockRejectedValueOnce({
      success: false,
      message: "Export already in progress",
    });

    await expect(commissionReportService.export({}, [])).rejects.toMatchObject({
      success: false,
      message: "Export already in progress",
    });
  });
});

describe("commissionReportService.getExportStatus", () => {
  it("polls export status by jobId", async () => {
    api.get.mockResolvedValueOnce({
      success: true,
      data: { jobId: "job-xyz", status: "completed", progress: 100 },
    });

    const result = await commissionReportService.getExportStatus("job-xyz");

    expect(api.get).toHaveBeenCalledWith(
      "/client/commission-report/export-status",
      { params: { jobId: "job-xyz" } },
    );
    expect(result.data.status).toBe("completed");
  });

  it("propagates error when status poll fails", async () => {
    api.get.mockRejectedValueOnce({
      success: false,
      message: "Job not found",
    });

    await expect(
      commissionReportService.getExportStatus("missing"),
    ).rejects.toMatchObject({
      message: "Job not found",
    });
  });
});

describe("commissionReportService.cancelExport", () => {
  it("posts jobId to export-cancel endpoint", async () => {
    api.post.mockResolvedValueOnce({
      success: true,
      data: { jobId: "job-xyz", status: "cancelled" },
    });

    const result = await commissionReportService.cancelExport("job-xyz");

    expect(api.post).toHaveBeenCalledWith(
      "/client/commission-report/export-cancel",
      { jobId: "job-xyz" },
    );
    expect(result.data.status).toBe("cancelled");
  });

  it("propagates error when cancel fails", async () => {
    api.post.mockRejectedValueOnce({
      success: false,
      message: "Cannot cancel completed job",
    });

    await expect(
      commissionReportService.cancelExport("job-xyz"),
    ).rejects.toMatchObject({
      message: "Cannot cancel completed job",
    });
  });
});

describe("commissionReportService.downloadExport", () => {
  it("downloads export as a blob by jobId", async () => {
    const blob = new Blob(["csv,data"], { type: "text/csv" });
    api.get.mockResolvedValueOnce(blob);

    const result = await commissionReportService.downloadExport("job-xyz");

    expect(api.get).toHaveBeenCalledWith(
      "/client/commission-report/export-download",
      {
        params: { jobId: "job-xyz" },
        responseType: "blob",
      },
    );
    expect(result).toBe(blob);
  });

  it("propagates error when download fails", async () => {
    api.get.mockRejectedValueOnce({
      success: false,
      message: "File not ready",
    });

    await expect(
      commissionReportService.downloadExport("job-xyz"),
    ).rejects.toMatchObject({
      message: "File not ready",
    });
  });
});
