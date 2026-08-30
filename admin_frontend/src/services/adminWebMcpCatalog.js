export const WEBMCP_TOOL_SECTIONS = [
  {
    key: "client",
    title: "Client",
    description: "Look up and navigate through client information.",
    icon: "fa-user-group",
  },
  {
    key: "transactions",
    title: "Transactions",
    description: "Inspect funding activity across visible clients.",
    icon: "fa-arrow-right-arrow-left",
  },
];

const CLIENT_LOOKUP_FIELDS = [
  {
    name: "email",
    type: "string",
    requirement: "One of",
    description: "Exact client email address (maximum 254 characters).",
  },
  {
    name: "id",
    type: "integer",
    requirement: "One of",
    description: "Exact client ID from 1 to 2147483647.",
  },
  {
    name: "code",
    type: "string",
    requirement: "One of",
    description: "Exact approved IB code (maximum 64 characters).",
  },
];

const SEARCH_CLIENT_FIELDS = [
  {
    name: "country",
    type: "string",
    requirement: "At least one",
    description: "Exact country code or country name (maximum 100 characters).",
  },
  {
    name: "tag",
    type: "string",
    requirement: "At least one",
    description: "Exact client tag name (maximum 100 characters).",
  },
  {
    name: "neverLoggedIn",
    type: "boolean",
    requirement: "At least one",
    description: "Filters clients by whether a last login is recorded.",
  },
  {
    name: "kycStatus",
    type: "string",
    requirement: "At least one",
    description: "Exact client KYC status (maximum 50 characters).",
  },
  {
    name: "status",
    type: "string",
    requirement: "At least one",
    description: "Exact client account status (maximum 50 characters).",
  },
  {
    name: "search",
    type: "string",
    requirement: "At least one",
    description:
      "Name, email, phone, or trading-account search text (maximum 100 characters).",
  },
  {
    name: "page",
    type: "integer",
    requirement: "Optional",
    description: "Result page from 1 to 1000. Defaults to 1.",
  },
  {
    name: "limit",
    type: "integer",
    requirement: "Optional",
    description: "Results per page from 1 to 50. Defaults to 25.",
  },
];

const TRANSACTION_FIELDS = [
  ...CLIENT_LOOKUP_FIELDS,
  {
    name: "type",
    type: "enum",
    requirement: "Optional",
    description:
      "all, deposit, withdrawal, internal_transfer, or credit. Defaults to all.",
  },
  {
    name: "page",
    type: "integer",
    requirement: "Optional",
    description: "Result page from 1 to 1000. Defaults to 1.",
  },
  {
    name: "limit",
    type: "integer",
    requirement: "Optional",
    description: "Results per page from 1 to 50. Defaults to 10.",
  },
];

const CLIENT_RESPONSE = {
  id: 42,
  name: "Jane Smith",
  email: "jane@example.com",
  country: "ID",
  status: "active",
  kycStatus: "approved",
  manager: "Account Manager",
  registeredAt: "2026-01-15T10:30:00Z",
  lastLoginAt: "2026-08-30T08:10:00Z",
  isIb: true,
  ibCode: "IB-2026-042",
};

const PAGINATION_RESPONSE = {
  page: 1,
  limit: 25,
  perPage: 25,
  total: 1,
  totalPages: 1,
  hasMore: false,
};

const TRANSACTION_SEARCH_FIELDS = [
  {
    name: "transactionId",
    type: "string",
    requirement: "At least one",
    description: "Exact transaction identifier.",
  },
  {
    name: "clientEmail",
    type: "string",
    requirement: "At least one",
    description: "Exact client email address.",
  },
  {
    name: "clientId",
    type: "integer",
    requirement: "At least one",
    description: "Exact client ID.",
  },
  {
    name: "type",
    type: "enum",
    requirement: "At least one",
    description: "deposit, withdrawal, internal_transfer, or credit.",
  },
  {
    name: "status",
    type: "string",
    requirement: "At least one",
    description: "Exact transaction status.",
  },
  {
    name: "dateFrom / dateTo",
    type: "YYYY-MM-DD",
    requirement: "At least one",
    description: "Inclusive transaction date range.",
  },
  {
    name: "minAmount / maxAmount",
    type: "number",
    requirement: "At least one",
    description: "Inclusive amount range.",
  },
  {
    name: "page / limit",
    type: "integer",
    requirement: "Optional",
    description:
      "Page defaults to 1; limit defaults to 25 and is capped at 50.",
  },
];

const TRANSACTION_RESPONSE = {
  id: 9,
  transactionId: "DEP-2026-0009",
  type: "deposit",
  status: "pending",
  amount: 100,
  currency: "USD",
  date: "2026-08-30T07:45:00Z",
  client: { id: 42, name: "Jane Smith", email: "jane@example.com" },
};

export const WEBMCP_TOOL_CATALOG = [
  {
    name: "get_client",
    title: "Get client",
    description:
      "Retrieve one client visible to the signed-in administrator by email, client ID, or exact approved IB code.",
    icon: "fa-user",
    permissionKeys: ["page_clientslist_readonly", "page_clientsdetail_profile"],
    sectionKey: "client",
    inputSummary: "Provide exactly one client identifier.",
    inputFields: CLIENT_LOOKUP_FIELDS,
    inputExample: { email: "client@example.com" },
    outputSummary: "Returns a sanitized client profile.",
    outputExample: { client: CLIENT_RESPONSE },
    accessMode: "read",
  },
  {
    name: "navigate_to_client",
    title: "Navigate to client",
    description:
      "Open one client visible to the signed-in administrator in the admin dashboard by email, client ID, or exact approved IB code.",
    icon: "fa-compass",
    permissionKeys: ["page_clientslist_readonly", "page_clientsdetail_profile"],
    sectionKey: "client",
    inputSummary: "Provide exactly one client identifier.",
    inputFields: CLIENT_LOOKUP_FIELDS,
    inputExample: { id: 42 },
    outputSummary:
      "Navigates to the resolved client and returns the dashboard route.",
    outputExample: {
      success: true,
      clientId: 42,
      route: "/client-detail?id=42",
    },
    accessMode: "read",
  },
  {
    name: "search_clients",
    title: "Search clients",
    description:
      "Find clients visible to the signed-in administrator using filters such as country, tag, KYC status, account status, or login history.",
    icon: "fa-users",
    permissionKeys: ["page_clientslist_readonly"],
    sectionKey: "client",
    inputSummary: "Provide at least one filter; pagination is optional.",
    inputFields: SEARCH_CLIENT_FIELDS,
    inputExample: {
      country: "Indonesia",
      tag: "VIP",
      neverLoggedIn: false,
      kycStatus: "approved",
      status: "active",
      search: "jane@example.com",
      page: 1,
      limit: 25,
    },
    outputSummary:
      "Returns matching sanitized client profiles and pagination metadata.",
    outputExample: {
      clients: [{ ...CLIENT_RESPONSE, tags: ["VIP"] }],
      pagination: PAGINATION_RESPONSE,
    },
    accessMode: "read",
  },
  {
    name: "get_client_documents",
    title: "Get client documents",
    description:
      "Retrieve safe metadata for one visible client's registration, KYC, and approved IB documents.",
    icon: "fa-folder-open",
    permissionKeys: ["page_clientsdetail_document"],
    sectionKey: "client",
    inputSummary: "Provide exactly one client identifier.",
    inputFields: CLIENT_LOOKUP_FIELDS,
    inputExample: { id: 42 },
    outputSummary:
      "Returns all available safe document metadata for the client.",
    outputExample: {
      clientId: 42,
      documents: [
        {
          id: "kyc_1",
          documentType: "kyc_document",
          title: "Passport",
          version: "1.0",
          templateName: "Standard KYC",
          signedAt: "2026-01-16T08:30:00Z",
          source: "kyc",
        },
      ],
    },
    accessMode: "read",
  },
  {
    name: "get_client_trading_accounts",
    title: "Get client trading accounts",
    description:
      "Retrieve one visible client's trading accounts, platform, status, currency, balance, and equity.",
    icon: "fa-chart-line",
    permissionKeys: ["page_clientsdetail_trading"],
    sectionKey: "client",
    inputSummary: "Provide exactly one client identifier.",
    inputFields: CLIENT_LOOKUP_FIELDS,
    inputExample: { id: 42 },
    outputSummary:
      "Returns all trading-account identifiers, status, and balance metrics.",
    outputExample: {
      clientId: 42,
      accounts: [
        {
          id: 7,
          accountNumber: "100007",
          accountNickname: "Primary MT5",
          login: "100007",
          platformKey: "mt5",
          platformName: "MetaTrader 5",
          status: "active",
          currency: "USD",
          accountType: "standard",
          leverage: "1:500",
          initialDeposit: 1000,
          balance: 1250,
          equity: 1280,
          credit: 0,
          balanceUpdatedAt: "2026-08-30T08:15:00Z",
          createdAt: "2026-01-15T11:00:00Z",
        },
      ],
    },
    accessMode: "read",
  },
  {
    name: "get_client_recent_transactions",
    title: "Get client recent transactions",
    description:
      "Retrieve one visible client's recent deposits, withdrawals, internal transfers, or credit activity.",
    icon: "fa-arrow-right-arrow-left",
    permissionKeys: ["page_clientsdetail_funding"],
    sectionKey: "client",
    inputSummary:
      "Provide exactly one client identifier; transaction type and pagination are optional.",
    inputFields: TRANSACTION_FIELDS,
    inputExample: {
      id: 42,
      type: "all",
      page: 1,
      limit: 10,
    },
    outputSummary:
      "Returns recent funding transactions and pagination metadata.",
    outputExample: {
      clientId: 42,
      type: "all",
      transactions: [
        {
          id: 9,
          transactionId: "DEP-2026-0009",
          type: "deposit",
          status: "completed",
          amount: 100,
          currency: "USD",
          date: "2026-08-30T07:45:00Z",
        },
      ],
      pagination: {
        ...PAGINATION_RESPONSE,
        limit: 10,
        perPage: 10,
      },
    },
    accessMode: "read",
  },
  {
    name: "export_clients",
    title: "Export clients",
    description:
      "Start an Excel export for selected clients visible to the signed-in administrator. The browser opens a progress page and downloads the file when ready.",
    icon: "fa-file-export",
    permissionKeys: ["page_clientslist_export"],
    sectionKey: "client",
    inputSummary:
      "Provide selected client IDs and optional country, tag, status, KYC, login, search, or registration-date filters.",
    inputFields: [
      {
        name: "clientIds",
        type: "integer[]",
        requirement: "Required",
        description: "Selected client IDs; maximum 500.",
      },
      {
        name: "country",
        type: "string",
        requirement: "Optional",
        description: "Exact country code or country name.",
      },
      {
        name: "tag",
        type: "string",
        requirement: "Optional",
        description: "Exact client tag name.",
      },
      {
        name: "neverLoggedIn",
        type: "boolean",
        requirement: "Optional",
        description:
          "When true, include only clients without a recorded login.",
      },
      {
        name: "kycStatus",
        type: "string",
        requirement: "Optional",
        description: "Exact client KYC status.",
      },
      {
        name: "status",
        type: "string",
        requirement: "Optional",
        description: "Exact client account status.",
      },
      {
        name: "search",
        type: "string",
        requirement: "Optional",
        description: "Client name, email, phone, or country search text.",
      },
      {
        name: "registeredFrom / registeredTo",
        type: "YYYY-MM-DD",
        requirement: "Optional",
        description: "Inclusive client registration date range.",
      },
    ],
    inputExample: {
      clientIds: [42, 43],
      country: "Indonesia",
      tag: "VIP",
      registeredFrom: "2026-01-01",
      registeredTo: "2026-08-30",
    },
    outputSummary:
      "Returns the queued job ID and browser progress URL; the page downloads the Excel file when ready.",
    outputExample: {
      success: true,
      jobId: "wmcp_clients_abc123",
      progressUrl: "/#/webmcp/export-progress?jobId=wmcp_clients_abc123",
      opened: true,
      queued: true,
    },
    accessMode: "read",
  },
  {
    name: "export_client_transactions",
    title: "Export client transactions",
    description:
      "Start an Excel export for selected clients' funding transactions with optional date, type, and status filters. The browser opens a progress page and downloads the file when ready.",
    icon: "fa-file-invoice-dollar",
    permissionKeys: ["page_fundingreport_export"],
    sectionKey: "client",
    inputSummary:
      "Provide selected client IDs and optional transaction date, type, or status filters.",
    inputFields: [
      {
        name: "clientIds",
        type: "integer[]",
        requirement: "Required",
        description: "Selected client IDs; maximum 500.",
      },
      {
        name: "dateFrom / dateTo",
        type: "YYYY-MM-DD",
        requirement: "Optional",
        description: "Inclusive transaction date range.",
      },
      {
        name: "type",
        type: "enum",
        requirement: "Optional",
        description: "all, deposit, withdrawal, internal_transfer, or credit.",
      },
      {
        name: "status",
        type: "string",
        requirement: "Optional",
        description: "Exact transaction status.",
      },
    ],
    inputExample: {
      clientIds: [42, 43],
      dateFrom: "2026-08-01",
      dateTo: "2026-08-30",
      type: "all",
      status: "completed",
    },
    outputSummary:
      "Returns the queued job ID and browser progress URL; the page downloads the Excel file when ready.",
    outputExample: {
      success: true,
      jobId: "wmcp_transactions_abc123",
      progressUrl: "/#/webmcp/export-progress?jobId=wmcp_transactions_abc123",
      opened: true,
      queued: true,
    },
    accessMode: "read",
  },
  {
    name: "search_transactions",
    title: "Search transactions",
    description:
      "Find funding transactions visible to the signed-in administrator by client, transaction ID, type, status, date range, or amount range.",
    icon: "fa-magnifying-glass-dollar",
    permissionKeys: ["page_fundingreport_readonly"],
    sectionKey: "transactions",
    inputSummary:
      "Provide at least one transaction filter; pagination is optional.",
    inputFields: TRANSACTION_SEARCH_FIELDS,
    inputExample: {
      type: "withdrawal",
      status: "pending_review",
      minAmount: 10000,
      dateFrom: "2026-08-24",
      page: 1,
      limit: 25,
    },
    outputSummary:
      "Returns safe transaction summaries and pagination metadata.",
    outputExample: {
      transactions: [TRANSACTION_RESPONSE],
      pagination: PAGINATION_RESPONSE,
    },
    accessMode: "read",
  },
  {
    name: "get_transaction",
    title: "Get transaction",
    description:
      "Retrieve one funding transaction visible to the signed-in administrator by its exact transaction ID.",
    icon: "fa-receipt",
    permissionKeys: ["page_fundingreport_readonly"],
    sectionKey: "transactions",
    inputSummary:
      "Provide an exact transaction ID; type is optional for disambiguation.",
    inputFields: [
      {
        name: "transactionId",
        type: "string",
        requirement: "Required",
        description: "Exact transaction identifier, such as DEP-2026-0009.",
      },
      {
        name: "type",
        type: "enum",
        requirement: "Optional",
        description: "deposit, withdrawal, internal_transfer, or credit.",
      },
    ],
    inputExample: { transactionId: "DEP-2026-0009" },
    outputSummary:
      "Returns one safe transaction summary with its visible client.",
    outputExample: { transaction: TRANSACTION_RESPONSE },
    accessMode: "read",
  },
];

export const groupWebMcpTools = (catalog = WEBMCP_TOOL_CATALOG) =>
  WEBMCP_TOOL_SECTIONS.map((section) => ({
    ...section,
    tools: catalog.filter((tool) => tool.sectionKey === section.key),
  })).filter(({ tools }) => tools.length > 0);
