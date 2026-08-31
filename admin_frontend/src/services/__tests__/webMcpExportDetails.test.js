import { describe, expect, it } from "vitest";
import {
  getExportDetails,
  resolveExportTransport,
  shouldAutoDownloadExport,
} from "../webMcpExportDetails";

describe("getExportDetails", () => {
  it("describes a client export", () => {
    expect(getExportDetails("clients")).toEqual({
      subject: "Selected client profiles",
      recordLabel: "1 row per client",
      format: "Excel-compatible .xls",
      dataType: "Tabular text (CSV-compatible)",
      scope: "Selected clients visible to this administrator",
      fields: [
        "Identity",
        "Contact details",
        "Country and status",
        "KYC status",
        "Manager and tags",
        "Registration and last login",
      ],
    });
  });

  it("describes a transaction export", () => {
    expect(getExportDetails("transactions")).toEqual({
      subject: "Funding transactions",
      recordLabel: "1 row per transaction",
      format: "Excel-compatible .xls",
      dataType: "Tabular text (CSV-compatible)",
      scope: "Transactions visible to this administrator",
      fields: [
        "Client identity",
        "Transaction ID and type",
        "Status",
        "Amount and currency",
        "Transaction date",
      ],
    });
  });

  it("falls back to a neutral description while the job type loads", () => {
    expect(getExportDetails()).toEqual({
      subject: "Export data",
      recordLabel: "Rows from the selected export",
      format: "Excel-compatible .xls",
      dataType: "Tabular text (CSV-compatible)",
      scope: "Access-controlled admin export",
      fields: [],
    });
  });

  it("describes Report-specific funding and IB statement exports", () => {
    expect(getExportDetails("funding_report").subject).toBe("Funding report");
    expect(getExportDetails("ib_statement").subject).toBe("IB statement");
  });

  it("describes and routes operation-log exports by their owner-bound job prefix", () => {
    expect(getExportDetails("operation_logs")).toEqual({
      subject: "Administrator operation logs",
      recordLabel: "1 row per audit event",
      format: "CSV (.csv)",
      dataType: "Tabular text (CSV)",
      scope: "Filtered operation logs visible to this administrator",
      fields: [
        "Operation time and administrator",
        "Module and submodule",
        "Operation type and target",
        "Localized audit detail",
        "IP address",
      ],
    });
    expect(resolveExportTransport({ jobId: "aolr_abc123" })).toEqual({
      kind: "operation_logs",
      exportType: "operation_logs",
    });
  });

  it("does not repeat an automatic download after the server recorded one", () => {
    expect(
      shouldAutoDownloadExport({
        status: "done",
        downloadRequestedAt: "",
        autoDownloadAttempted: false,
      }),
    ).toBe(true);
    expect(
      shouldAutoDownloadExport({
        status: "done",
        downloadRequestedAt: "2026-08-30 17:00:00",
        autoDownloadAttempted: false,
      }),
    ).toBe(false);
    expect(
      shouldAutoDownloadExport({
        status: "done",
        downloadRequestedAt: "",
        autoDownloadAttempted: true,
      }),
    ).toBe(false);
  });
});
