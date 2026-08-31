const COMMON_DETAILS = {
  format: "Excel-compatible .xls",
  dataType: "Tabular text (CSV-compatible)",
};

const EXPORT_DETAILS = {
  clients: {
    ...COMMON_DETAILS,
    subject: "Selected client profiles",
    recordLabel: "1 row per client",
    scope: "Selected clients visible to this administrator",
    fields: [
      "Identity",
      "Contact details",
      "Country and status",
      "KYC status",
      "Manager and tags",
      "Registration and last login",
    ],
  },
  transactions: {
    ...COMMON_DETAILS,
    subject: "Funding transactions",
    recordLabel: "1 row per transaction",
    scope: "Transactions visible to this administrator",
    fields: [
      "Client identity",
      "Transaction ID and type",
      "Status",
      "Amount and currency",
      "Transaction date",
    ],
  },
  funding_report: {
    ...COMMON_DETAILS,
    subject: "Funding report",
    recordLabel: "1 row per funding transaction",
    scope: "Filtered Funding Report rows visible to this administrator",
    fields: [
      "Client identity",
      "Transaction ID and type",
      "Status and payment method",
      "Amount and currency",
      "Requested date",
    ],
  },
  ib_statement: {
    ...COMMON_DETAILS,
    subject: "IB statement",
    recordLabel: "Statement summary and account rows",
    scope: "Selected IB network visible to this administrator",
    fields: [
      "IB identity and reporting period",
      "Funding and balance movement",
      "Trading totals",
      "Instrument and weekly breakdowns",
      "Visible account details",
    ],
  },
  operation_logs: {
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
  },
};

const DEFAULT_EXPORT_DETAILS = {
  ...COMMON_DETAILS,
  subject: "Export data",
  recordLabel: "Rows from the selected export",
  scope: "Access-controlled admin export",
  fields: [],
};

export const getExportDetails = (exportType) =>
  EXPORT_DETAILS[exportType] || DEFAULT_EXPORT_DETAILS;

export const resolveExportTransport = ({ jobId = "", exportKind = "" } = {}) => {
  if (/^aolr_[A-Za-z0-9._-]+$/.test(String(jobId))) {
    return { kind: "operation_logs", exportType: "operation_logs" };
  }
  if (["funding_report", "ib_statement"].includes(exportKind)) {
    return { kind: "report", exportType: exportKind };
  }
  return { kind: "client", exportType: "" };
};

export const shouldAutoDownloadExport = ({
  status,
  downloadRequestedAt,
  autoDownloadAttempted,
}) => status === "done" && !downloadRequestedAt && !autoDownloadAttempted;
