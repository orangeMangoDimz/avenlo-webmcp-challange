import { describe, expect, it } from "vitest";
import { getExportDetails } from "../webMcpExportDetails";

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
      subject: "Funding transactions for selected clients",
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
});
