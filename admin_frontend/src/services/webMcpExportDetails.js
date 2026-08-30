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
    subject: "Funding transactions for selected clients",
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
