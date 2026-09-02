export const WEBMCP_TOOL_SECTIONS = [
  {
    key: "dashboard",
    title: "Dashboard",
    description: "Summarize the current WebMCP runtime and execution health.",
    icon: "fa-gauge-high",
  },
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
  {
    key: "kyc",
    title: "KYC",
    description: "Discover and review visible KYC submissions safely.",
    icon: "fa-id-card",
  },
  {
    key: "ib",
    title: "IB",
    description: "Inspect visible IB partners, networks, clients, and uplines.",
    icon: "fa-handshake",
  },
  {
    key: "sales",
    title: "Sales",
    description:
      "Discover sales users, assignments, performance, and rankings.",
    icon: "fa-user-tie",
  },
  {
    key: "report",
    title: "Report",
    description: "Inspect, navigate, and export permission-scoped reports.",
    icon: "fa-chart-column",
  },
  {
    key: "admin_log",
    title: "Admin Log",
    description:
      "Inspect administrators, roles, effective permissions, and audit history.",
    icon: "fa-shield-halved",
  },
];

export const WEBMCP_BASE_ROLES = [
  {
    key: "administrator",
    label: "Administrator",
    hasAllPermissions: true,
    permissionKeys: [],
  },
  {
    key: "manager",
    label: "Manager",
    permissionKeys: [
      "page_clientsdetail_document",
      "page_clientsdetail_funding",
      "page_clientsdetail_profile",
      "page_clientsdetail_trading",
      "page_clientslist_export",
      "page_clientslist_readonly",
      "page_fundingreport_export",
      "page_fundingreport_readonly",
      "page_iblist_readonly",
      "page_kyclist_readonly",
      "page_operationlogreport_export",
      "page_operationlogreport_readonly",
      "page_salesdashboard_view",
      "page_saleslist_view",
    ],
  },
  { key: "operator", label: "Operator", permissionKeys: [] },
  { key: "viewer", label: "Viewer", permissionKeys: [] },
  {
    key: "sales_manager",
    label: "Sales Manager",
    permissionKeys: [
      "page_clientsdetail_document",
      "page_clientsdetail_funding",
      "page_clientsdetail_profile",
      "page_clientsdetail_trading",
      "page_clientslist_export",
      "page_clientslist_readonly",
      "page_dailyreport_readonly",
      "page_iblist_readonly",
      "page_kyclist_readonly",
      "page_salesdashboard_view",
      "page_saleslist_view",
    ],
  },
  {
    key: "sales",
    label: "Sales",
    permissionKeys: [
      "page_clientsdetail_document",
      "page_clientsdetail_funding",
      "page_clientsdetail_profile",
      "page_clientsdetail_trading",
      "page_clientslist_export",
      "page_clientslist_readonly",
      "page_dailyreport_readonly",
      "page_fundingreport_readonly",
      "page_iblist_readonly",
      "page_kyclist_readonly",
      "page_salesdashboard_view",
    ],
  },
  {
    key: "ops",
    label: "Ops",
    permissionKeys: [
      "page_clientsdetail_document",
      "page_clientsdetail_funding",
      "page_clientsdetail_profile",
      "page_clientsdetail_trading",
      "page_clientslist_export",
      "page_clientslist_readonly",
      "page_fundingreport_export",
      "page_fundingreport_readonly",
      "page_iblist_readonly",
      "page_kyclist_readonly",
      "page_salesdashboard_view",
      "page_saleslist_view",
    ],
  },
];

export const getWebMcpToolBaseRoles = (tool = {}) => {
  const requiredPermissionKeys = Array.isArray(tool.permissionKeys)
    ? tool.permissionKeys
    : [];
  if (requiredPermissionKeys.length === 0) {
    return WEBMCP_BASE_ROLES.map((role) => role.label);
  }
  const matcher = tool.permissionMatch === "all" ? "every" : "some";

  return WEBMCP_BASE_ROLES.filter(
    (role) =>
      role.hasAllPermissions ||
      requiredPermissionKeys[matcher]((permissionKey) =>
        role.permissionKeys.includes(permissionKey),
      ),
  ).map((role) => role.label);
};

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
    name: "salesAssignment",
    type: "enum",
    requirement: "At least one",
    description: "unassigned returns clients without a sales representative.",
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

const KYC_SUBMISSION_FIELD = [
  {
    name: "submissionId",
    type: "integer",
    requirement: "Required",
    description: "Exact KYC submission ID from 1 to 2147483647.",
  },
];

const KYC_SEARCH_FIELDS = [
  {
    name: "submissionId",
    type: "integer",
    requirement: "At least one",
    description: "Exact KYC submission ID.",
  },
  {
    name: "email",
    type: "string",
    requirement: "At least one",
    description: "Exact client email address.",
  },
  {
    name: "country",
    type: "string",
    requirement: "At least one",
    description: "Exact country code or country name.",
  },
  {
    name: "status",
    type: "enum",
    requirement: "At least one",
    description:
      "draft, incomplete, pending, under_review, requires_documents, approved, rejected, or expired.",
  },
  {
    name: "assigned",
    type: "boolean",
    requirement: "At least one",
    description: "Use false to find submissions without a reviewer.",
  },
  {
    name: "reviewerId",
    type: "integer",
    requirement: "At least one",
    description: "Exact assigned reviewer ID.",
  },
  {
    name: "templateId",
    type: "integer",
    requirement: "At least one",
    description: "Exact KYC template ID.",
  },
  {
    name: "provider",
    type: "string",
    requirement: "At least one",
    description: "Exact verification provider name, or Local.",
  },
  {
    name: "minWaitingHours",
    type: "integer",
    requirement: "At least one",
    description: "Minimum whole hours the submission has waited.",
  },
  {
    name: "page / limit",
    type: "integer",
    requirement: "Optional",
    description:
      "Page defaults to 1; limit defaults to 25 and is capped at 50.",
  },
];

const KYC_QUEUE_RESPONSE = {
  submissionId: 123,
  client: {
    name: "John Smith",
    maskedEmail: "j***@example.com",
    country: "ID",
  },
  status: "pending",
  template: { id: 7, name: "Standard KYC" },
  provider: { name: "Local", managedExternally: false },
  submittedAt: "2026-08-30T10:00:00Z",
  completionPercentage: 75,
  reviewer: null,
  waitingHours: 27,
};

export const WEBMCP_TOOL_CATALOG = [
  {
    name: "get_dashboard_summary",
    title: "Get dashboard summary",
    description:
      "Retrieve the current permission-scoped Operations Overview shown in the admin dashboard.",
    icon: "fa-gauge-high",
    permissionKeys: [],
    sectionKey: "dashboard",
    inputSummary:
      "No input is required. Returns the dashboard's default seven-day window in the browser timezone.",
    inputFields: [
      {
        name: "None",
        type: "none",
        requirement: "None",
        description: "This tool does not accept parameters.",
      },
    ],
    inputExample: {},
    outputSummary:
      "Returns dashboard metrics, exception queue, funding trend, sales and IB summaries, recent activity, scopes, and section errors.",
    outputExample: {
      generatedAt: "2026-09-03T08:00:00Z",
      period: { startDate: "2026-08-28", endDate: "2026-09-03" },
      policy: { highValueAmount: 10000, kycOverdueHours: 24 },
      scope: { funding: { access: "all", canExport: true } },
      metrics: { netFunding: { status: "ready", totals: [] } },
      attentionQueue: { items: [], total: 0, truncated: false },
      fundingTrend: { status: "ready", points: [] },
      sales: { status: "ready", summary: {}, rankings: [] },
      ib: { status: "ready", summary: {}, leaders: [] },
      recentActivity: { status: "ready", items: [] },
      sectionErrors: [],
    },
    accessMode: "read",
  },
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
  {
    name: "export_transactions",
    title: "Export transactions",
    description:
      "Start an Excel export for funding transactions visible to the signed-in administrator, including deposits, withdrawals, internal transfers, and credits, with optional filters.",
    icon: "fa-file-export",
    permissionKeys: ["page_fundingreport_export"],
    sectionKey: "transactions",
    inputSummary:
      "Optionally filter by transaction date, type, status, or amount range.",
    inputFields: [
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
      {
        name: "minAmount / maxAmount",
        type: "number",
        requirement: "Optional",
        description: "Inclusive transaction amount range.",
      },
    ],
    inputExample: {
      dateFrom: "2026-08-01",
      dateTo: "2026-08-30",
      type: "all",
      status: "completed",
      minAmount: 100,
      maxAmount: 5000,
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
    name: "search_kyc",
    title: "Search KYC",
    description:
      "Find KYC submissions visible to the signed-in administrator by client, status, assignment, template, provider, country, or waiting time. Queue results mask email addresses.",
    icon: "fa-magnifying-glass",
    permissionKeys: ["page_kyclist_readonly"],
    sectionKey: "kyc",
    inputSummary: "Provide at least one KYC queue filter.",
    inputFields: KYC_SEARCH_FIELDS,
    inputExample: {
      status: "pending",
      assigned: false,
      minWaitingHours: 24,
      page: 1,
      limit: 25,
    },
    outputSummary:
      "Returns privacy-minimized KYC queue entries and pagination metadata.",
    outputExample: {
      submissions: [KYC_QUEUE_RESPONSE],
      pagination: PAGINATION_RESPONSE,
    },
    accessMode: "read",
  },
  {
    name: "get_kyc",
    title: "Get KYC",
    description:
      "Summarize one visible KYC submission, including status, identity summary, dates, reviewer, completion, latest request or decision, and objective attention items. It does not determine fraud or approval eligibility.",
    icon: "fa-id-card",
    permissionKeys: ["page_kyclist_readonly"],
    sectionKey: "kyc",
    inputSummary: "Provide an exact KYC submission ID.",
    inputFields: KYC_SUBMISSION_FIELD,
    inputExample: { submissionId: 123 },
    outputSummary: "Returns a sanitized KYC submission summary.",
    outputExample: {
      ...KYC_QUEUE_RESPONSE,
      attentionItems: [
        { code: "UNASSIGNED_REVIEWER", message: "No reviewer is assigned." },
      ],
    },
    accessMode: "read",
  },
  {
    name: "get_kyc_answers",
    title: "Get KYC answers",
    description:
      "Retrieve the active questionnaire and submitted values for one visible KYC submission, including required, answered, and missing states. File questions return counts instead of storage locations.",
    icon: "fa-list-check",
    permissionKeys: ["page_kyclist_readonly"],
    sectionKey: "kyc",
    inputSummary: "Provide an exact KYC submission ID.",
    inputFields: KYC_SUBMISSION_FIELD,
    inputExample: { submissionId: 123 },
    outputSummary:
      "Returns questionnaire categories, normalized answers, and missing required questions.",
    outputExample: {
      submissionId: 123,
      categories: [
        {
          id: 1,
          name: "Identity",
          questions: [
            {
              id: 12,
              question: "Upload proof of identity",
              type: "file_upload",
              required: true,
              answered: true,
              value: { fileCount: 1 },
            },
          ],
        },
      ],
    },
    accessMode: "read",
  },
  {
    name: "get_kyc_documents",
    title: "Get KYC documents",
    description:
      "Retrieve metadata-only document requirements, uploads, signatures, and resubmission documents for one visible KYC submission. URLs, paths, contents, and upload IP data are excluded.",
    icon: "fa-folder-open",
    permissionKeys: ["page_kyclist_readonly"],
    sectionKey: "kyc",
    inputSummary: "Provide an exact KYC submission ID.",
    inputFields: KYC_SUBMISSION_FIELD,
    inputExample: { submissionId: 123 },
    outputSummary:
      "Returns document presence, type/name, source, and upload or signature times.",
    outputExample: {
      submissionId: 123,
      fileQuestions: [
        {
          questionId: 12,
          question: "Upload proof of address",
          required: true,
          status: "missing",
          files: [],
        },
      ],
      missingRequiredItems: [
        { source: "question_upload", id: 12, name: "Upload proof of address" },
      ],
    },
    accessMode: "read",
  },
  {
    name: "get_kyc_progress",
    title: "Get KYC progress",
    description:
      "Retrieve questionnaire and document completion for one visible KYC submission, including exact missing required item identifiers.",
    icon: "fa-bars-progress",
    permissionKeys: ["page_kyclist_readonly"],
    sectionKey: "kyc",
    inputSummary: "Provide an exact KYC submission ID.",
    inputFields: KYC_SUBMISSION_FIELD,
    inputExample: { submissionId: 123 },
    outputSummary:
      "Returns completion percentages, counts, missing required items, and overall completeness.",
    outputExample: {
      submissionId: 123,
      status: "pending",
      completionPercentage: 75,
      questionnaire: { requiredQuestions: 12, answeredRequiredQuestions: 9 },
      documents: { requiredItems: 2, completedItems: 1 },
      overallComplete: false,
    },
    accessMode: "read",
  },
  {
    name: "get_kyc_timeline",
    title: "Get KYC timeline",
    description:
      "Retrieve chronological lifecycle and administrator activity for one visible KYC submission, including assignment, document requests, resubmission, approval, and rejection events when recorded.",
    icon: "fa-clock-rotate-left",
    permissionKeys: ["page_kyclist_readonly"],
    sectionKey: "kyc",
    inputSummary: "Provide an exact KYC submission ID.",
    inputFields: KYC_SUBMISSION_FIELD,
    inputExample: { submissionId: 123 },
    outputSummary:
      "Returns sanitized chronological events with actor identity and safe metadata.",
    outputExample: {
      submissionId: 123,
      events: [
        {
          eventType: "assigned",
          title: "Assigned",
          occurredAt: "2026-08-30T10:30:00Z",
          actor: { id: 3, name: "Admin User" },
        },
      ],
      truncated: false,
    },
    accessMode: "read",
  },
  {
    name: "get_ib_partner",
    title: "Get IB partner",
    description:
      "Retrieve one approved IB partner visible to the signed-in administrator by IB partner ID, email, or exact IB code.",
    icon: "fa-handshake",
    permissionKeys: ["page_iblist_readonly"],
    sectionKey: "ib",
    inputSummary: "Provide exactly one IB identifier.",
    inputFields: [
      {
        name: "id",
        type: "integer",
        requirement: "One of",
        description: "Exact IB partner ID.",
      },
      {
        name: "email",
        type: "string",
        requirement: "One of",
        description: "Exact client or IB contact email.",
      },
      {
        name: "code",
        type: "string",
        requirement: "One of",
        description: "Exact approved IB code.",
      },
    ],
    inputExample: { code: "IB-2026-042" },
    outputSummary: "Returns a sanitized IB profile.",
    outputExample: {
      ib: {
        id: 42,
        clientId: 7,
        code: "IB-2026-042",
        name: "Jane Partner",
        email: "partner@example.com",
        country: "ID",
        status: "approved",
        ibType: "Master IB",
        tier: { id: 2, level: 2, name: "Gold" },
        registeredAt: "2026-01-15T10:30:00Z",
      },
    },
    accessMode: "read",
  },
  {
    name: "get_ib_network",
    title: "Get IB network",
    description:
      "Retrieve the visible child-IB hierarchy for one approved IB. Omit maxDepth for the configured hierarchy limit or use maxDepth 1 for direct child IBs.",
    icon: "fa-sitemap",
    permissionKeys: ["page_iblist_readonly"],
    sectionKey: "ib",
    inputSummary: "Provide one IB identifier and optional maximum depth.",
    inputFields: [
      {
        name: "id / email / code",
        type: "IB selector",
        requirement: "One of",
        description: "Exact visible IB identifier.",
      },
      {
        name: "maxDepth",
        type: "integer",
        requirement: "Optional",
        description: "Maximum child-IB depth; 1 returns direct children.",
      },
    ],
    inputExample: { code: "IB-2026-042", maxDepth: 1 },
    outputSummary: "Returns the root IB and nested visible child IBs.",
    outputExample: {
      ib: { id: 42, code: "IB-2026-042" },
      maxDepth: 1,
      children: [],
    },
    accessMode: "read",
  },
  {
    name: "get_ib_network_stats",
    title: "Get IB network stats",
    description:
      "Return visible direct and total IB/client counts for one approved IB network.",
    icon: "fa-chart-pie",
    permissionKeys: ["page_iblist_readonly"],
    sectionKey: "ib",
    inputSummary: "Provide exactly one IB identifier.",
    inputFields: [
      {
        name: "id / email / code",
        type: "IB selector",
        requirement: "One of",
        description: "Exact visible IB identifier.",
      },
    ],
    inputExample: { id: 42 },
    outputSummary:
      "Returns network totals derived from visible IBs and ordinary clients.",
    outputExample: {
      ib: { id: 42, code: "IB-2026-042" },
      totals: {
        directIbs: 2,
        totalDescendantIbs: 4,
        directClients: 5,
        totalNetworkClients: 12,
        totalNetworkMembers: 16,
      },
    },
    accessMode: "read",
  },
  {
    name: "get_ib_clients",
    title: "Get IB clients",
    description:
      "Retrieve visible ordinary clients assigned directly to one IB or throughout its visible child-IB network.",
    icon: "fa-users",
    permissionKeys: ["page_iblist_readonly"],
    sectionKey: "ib",
    inputSummary:
      "Provide one IB identifier, relationship scope, and optional pagination.",
    inputFields: [
      {
        name: "id / email / code",
        type: "IB selector",
        requirement: "One of",
        description: "Exact visible IB identifier.",
      },
      {
        name: "relationship",
        type: "enum",
        requirement: "Required",
        description: "direct or all.",
      },
      {
        name: "page / limit",
        type: "integer",
        requirement: "Optional",
        description:
          "Page defaults to 1; limit defaults to 25 and is capped at 50.",
      },
    ],
    inputExample: { id: 42, relationship: "all", page: 1, limit: 25 },
    outputSummary:
      "Returns clients, their direct IB, relative depth, and pagination.",
    outputExample: {
      ib: { id: 42 },
      relationship: "all",
      clients: [],
      pagination: PAGINATION_RESPONSE,
    },
    accessMode: "read",
  },
  {
    name: "get_client_ib_upline",
    title: "Get client IB upline",
    description:
      "Retrieve the visible IB upline for one client, ordered from the direct IB toward the root IB.",
    icon: "fa-arrow-up-wide-short",
    permissionKeys: ["page_iblist_readonly"],
    sectionKey: "ib",
    inputSummary: "Provide exactly one client ID or email.",
    inputFields: [
      {
        name: "id",
        type: "integer",
        requirement: "One of",
        description: "Exact client ID.",
      },
      {
        name: "email",
        type: "string",
        requirement: "One of",
        description: "Exact client email.",
      },
    ],
    inputExample: { email: "client@example.com" },
    outputSummary:
      "Returns a sanitized client and its visible direct-to-root IB chain.",
    outputExample: {
      client: { id: 9, email: "client@example.com" },
      upline: [],
      complete: true,
    },
    accessMode: "read",
  },
  {
    name: "navigate_to_ib",
    title: "Navigate to IB",
    description:
      "Open one approved IB visible to the signed-in administrator in the Partner Network page.",
    icon: "fa-compass",
    permissionKeys: ["page_iblist_readonly"],
    sectionKey: "ib",
    inputSummary: "Provide exactly one IB identifier.",
    inputFields: [
      {
        name: "id / email / code",
        type: "IB selector",
        requirement: "One of",
        description: "Exact visible IB identifier.",
      },
    ],
    inputExample: { code: "IB-2026-042" },
    outputSummary:
      "Navigates to the filtered IB list and expands the resolved IB.",
    outputExample: {
      success: true,
      ibId: 42,
      route: "/ib-list?search=IB-2026-042&detailId=42",
    },
    accessMode: "read",
  },
  {
    name: "search_sales",
    title: "Search sales",
    description:
      "Find sales users visible to the signed-in administrator by sales ID, name, username, or email.",
    icon: "fa-magnifying-glass",
    permissionKeys: ["page_saleslist_view", "page_salesdashboard_view"],
    sectionKey: "sales",
    inputSummary: "Provide sales search text and optional pagination.",
    inputFields: [
      {
        name: "query",
        type: "string",
        requirement: "Required",
        description: "Sales ID, name, username, or email search text.",
      },
      {
        name: "page / limit",
        type: "integer",
        requirement: "Optional",
        description:
          "Page defaults to 1; limit defaults to 25 and is capped at 50.",
      },
    ],
    inputExample: { query: "Sarah", page: 1, limit: 25 },
    outputSummary: "Returns sanitized sales profiles and pagination metadata.",
    outputExample: {
      sales: [
        {
          id: 42,
          name: "Sarah Tan",
          email: "sarah@example.com",
          status: "active",
          totalClients: 8,
          totalPartners: 2,
        },
      ],
      pagination: PAGINATION_RESPONSE,
    },
    accessMode: "read",
  },
  {
    name: "get_sales_clients",
    title: "Get sales clients",
    description:
      "Retrieve ordinary clients assigned to one visible sales user. Approved IB users are returned by get_sales_partners instead.",
    icon: "fa-users",
    permissionKeys: ["page_saleslist_view", "page_salesdashboard_view"],
    sectionKey: "sales",
    inputSummary:
      "Provide a sales ID and optional client search or pagination.",
    inputFields: [
      {
        name: "salesId",
        type: "integer",
        requirement: "Required",
        description: "Exact sales user ID returned by search_sales.",
      },
      {
        name: "search",
        type: "string",
        requirement: "Optional",
        description: "Client ID, name, or email search text.",
      },
      {
        name: "page / limit",
        type: "integer",
        requirement: "Optional",
        description:
          "Page defaults to 1; limit defaults to 25 and is capped at 50.",
      },
    ],
    inputExample: { salesId: 42, page: 1, limit: 25 },
    outputSummary:
      "Returns sanitized assigned clients and pagination metadata.",
    outputExample: {
      sales: { id: 42, name: "Sarah Tan" },
      clients: [
        {
          id: 91,
          name: "Client User",
          email: "client@example.com",
          country: "Indonesia",
          kycStatus: "approved",
          balance: 1200,
          trades: 4,
        },
      ],
      pagination: PAGINATION_RESPONSE,
    },
    accessMode: "read",
  },
  {
    name: "get_sales_partners",
    title: "Get sales partners",
    description:
      "Retrieve approved IB partners assigned to one visible sales user.",
    icon: "fa-handshake",
    permissionKeys: ["page_saleslist_view", "page_salesdashboard_view"],
    sectionKey: "sales",
    inputSummary:
      "Provide a sales ID and optional partner search or pagination.",
    inputFields: [
      {
        name: "salesId",
        type: "integer",
        requirement: "Required",
        description: "Exact sales user ID returned by search_sales.",
      },
      {
        name: "search",
        type: "string",
        requirement: "Optional",
        description: "Partner ID, name, email, alias, or IB code search text.",
      },
      {
        name: "page / limit",
        type: "integer",
        requirement: "Optional",
        description:
          "Page defaults to 1; limit defaults to 25 and is capped at 50.",
      },
    ],
    inputExample: { salesId: 42, search: "IB-2026", page: 1, limit: 25 },
    outputSummary:
      "Returns sanitized assigned IB partners and pagination metadata.",
    outputExample: {
      sales: { id: 42, name: "Sarah Tan" },
      partners: [
        {
          id: 7,
          userId: 91,
          code: "IB-2026-007",
          name: "Partner User",
          clientCount: 12,
          totalCommission: 500,
          status: "approved",
        },
      ],
      pagination: PAGINATION_RESPONSE,
    },
    accessMode: "read",
  },
  {
    name: "get_sales_performance",
    title: "Get sales performance",
    description:
      "Return one visible sales user's monthly deposits, withdrawals, net deposit, registrations, KPI target, and achievement rate.",
    icon: "fa-chart-line",
    permissionKeys: ["page_saleslist_view", "page_salesdashboard_view"],
    sectionKey: "sales",
    inputSummary:
      "Provide a sales ID, optional calendar month, and timezone offset.",
    inputFields: [
      {
        name: "salesId",
        type: "integer",
        requirement: "Required",
        description: "Exact sales user ID returned by search_sales.",
      },
      {
        name: "month",
        type: "YYYY-MM",
        requirement: "Optional",
        description: "Calendar month; defaults to the current month.",
      },
      {
        name: "tzOffset",
        type: "integer",
        requirement: "Optional",
        description: "Minutes east of UTC; defaults to the browser timezone.",
      },
    ],
    inputExample: { salesId: 42, month: "2026-08", tzOffset: 420 },
    outputSummary: "Returns monthly metrics and net-deposit KPI comparison.",
    outputExample: {
      sales: { id: 42, name: "Sarah Tan" },
      month: "2026-08",
      metrics: {
        deposits: 1200,
        withdrawals: 200,
        netDeposit: 1000,
        newLeads: 4,
        newClients: 2,
      },
      target: { netDeposit: 2000, achievementRate: 50 },
    },
    accessMode: "read",
  },
  {
    name: "get_sales_daily_summary",
    title: "Get sales daily summary",
    description:
      "Return one visible sales user's results for a day with month-to-date net-deposit KPI context.",
    icon: "fa-calendar-day",
    permissionKeys: ["page_dailyreport_readonly"],
    sectionKey: "sales",
    inputSummary: "Provide a sales ID, optional date, and timezone offset.",
    inputFields: [
      {
        name: "salesId",
        type: "integer",
        requirement: "Required",
        description: "Exact sales user ID returned by search_sales.",
      },
      {
        name: "date",
        type: "YYYY-MM-DD",
        requirement: "Optional",
        description: "Calendar date; defaults to today.",
      },
      {
        name: "tzOffset",
        type: "integer",
        requirement: "Optional",
        description: "Minutes east of UTC; defaults to the browser timezone.",
      },
    ],
    inputExample: { salesId: 42, date: "2026-08-31", tzOffset: 420 },
    outputSummary: "Returns daily metrics and current monthly KPI progress.",
    outputExample: {
      sales: { id: 42, name: "Sarah Tan" },
      date: "2026-08-31",
      metrics: {
        deposits: 300,
        withdrawals: 100,
        netDeposit: 200,
        newLeads: 3,
        newClients: 1,
      },
      monthToDateNetDeposit: 500,
      target: { netDeposit: 1000, achievementRate: 50 },
    },
    accessMode: "read",
  },
  {
    name: "get_sales_leaderboard",
    title: "Get sales leaderboard",
    description:
      "Rank the visible sales team by new clients, new leads, net deposit, or deposits for a day or month.",
    icon: "fa-ranking-star",
    permissionKeys: ["page_dailyreport_readonly", "page_saleslist_view"],
    sectionKey: "sales",
    inputSummary:
      "Choose an optional metric, period, date or month, timezone, and result limit.",
    inputFields: [
      {
        name: "metric",
        type: "enum",
        requirement: "Optional",
        description:
          "newClients, newLeads, netDeposit, or deposits; defaults to newClients.",
      },
      {
        name: "period",
        type: "enum",
        requirement: "Optional",
        description: "day or month; defaults to month.",
      },
      {
        name: "date / month",
        type: "date",
        requirement: "Optional",
        description: "Date for day rankings or month for month rankings.",
      },
      {
        name: "tzOffset",
        type: "integer",
        requirement: "Optional",
        description: "Minutes east of UTC; defaults to the browser timezone.",
      },
      {
        name: "limit",
        type: "integer",
        requirement: "Optional",
        description: "Top results from 1 to 50; defaults to 10.",
      },
    ],
    inputExample: {
      metric: "newClients",
      period: "month",
      month: "2026-08",
      limit: 10,
    },
    outputSummary:
      "Returns deterministic competition rankings for the selected metric.",
    outputExample: {
      metric: "newClients",
      period: "month",
      month: "2026-08",
      rankings: [{ rank: 1, sales: { id: 42, name: "Sarah Tan" }, value: 5 }],
    },
    accessMode: "read",
  },
  {
    name: "navigate_to_sales",
    title: "Navigate to sales",
    description:
      "Open one visible sales user's dashboard in the admin application.",
    icon: "fa-compass",
    permissionKeys: ["page_saleslist_view", "page_salesdashboard_view"],
    sectionKey: "sales",
    inputSummary: "Provide an exact visible sales ID.",
    inputFields: [
      {
        name: "salesId",
        type: "integer",
        requirement: "Required",
        description: "Exact sales user ID returned by search_sales.",
      },
    ],
    inputExample: { salesId: 42 },
    outputSummary:
      "Navigates to the selected sales dashboard and returns its route.",
    outputExample: {
      success: true,
      salesId: 42,
      route: "/sales-dashboard?salesId=42",
    },
    accessMode: "read",
  },
  {
    name: "get_funding_summary",
    title: "Get funding summary",
    description:
      "Return scoped funding totals and net flow for an inclusive date range.",
    icon: "fa-chart-pie",
    permissionKeys: ["page_fundingreport_readonly"],
    sectionKey: "report",
    inputSummary:
      "Provide an optional date range; the current month is used by default.",
    inputFields: [
      {
        name: "startDate / endDate",
        type: "YYYY-MM-DD",
        requirement: "Optional pair",
        description: "Inclusive funding period.",
      },
    ],
    inputExample: { startDate: "2026-08-01", endDate: "2026-08-31" },
    outputSummary:
      "Returns deposit, withdrawal, transfer, count, and net-flow totals.",
    outputExample: {
      period: { startDate: "2026-08-01", endDate: "2026-08-31" },
      summary: { totalDeposits: 1000, totalWithdrawals: 200, netFlow: 800 },
    },
    accessMode: "read",
  },
  {
    name: "search_funding_transactions",
    title: "Search funding transactions",
    description:
      "Search scoped Funding Report rows by period, type, status, client, transaction, or amount.",
    icon: "fa-magnifying-glass-dollar",
    permissionKeys: ["page_fundingreport_readonly"],
    sectionKey: "report",
    inputSummary: "Provide optional funding filters and pagination.",
    inputFields: [
      {
        name: "startDate / endDate",
        type: "YYYY-MM-DD",
        requirement: "Optional pair",
        description: "Inclusive period; defaults to the current month.",
      },
      {
        name: "type / status / query",
        type: "filters",
        requirement: "Optional",
        description: "Funding transaction filters.",
      },
      {
        name: "minAmount / maxAmount",
        type: "number",
        requirement: "Optional",
        description: "Inclusive amount range.",
      },
      {
        name: "page / limit",
        type: "integer",
        requirement: "Optional",
        description: "Limit defaults to 25 and is capped at 50.",
      },
    ],
    inputExample: {
      type: "withdrawal",
      startDate: "2026-08-24",
      endDate: "2026-08-31",
    },
    outputSummary: "Returns sanitized transactions and pagination.",
    outputExample: { transactions: [], pagination: PAGINATION_RESPONSE },
    accessMode: "read",
  },
  {
    name: "get_daily_sales_performance",
    title: "Get daily sales performance",
    description:
      "Return scoped daily sales totals and deterministic rankings for one metric.",
    icon: "fa-ranking-star",
    permissionKeys: ["page_dailyreport_readonly"],
    sectionKey: "report",
    inputSummary:
      "Choose an optional date, timezone, ranking metric, and limit.",
    inputFields: [
      {
        name: "date",
        type: "YYYY-MM-DD",
        requirement: "Optional",
        description: "Defaults to today.",
      },
      {
        name: "rankBy",
        type: "enum",
        requirement: "Optional",
        description:
          "deposits, withdrawals, netDeposit, newLeads, or newClients.",
      },
      {
        name: "tzOffset / limit",
        type: "integer",
        requirement: "Optional",
        description: "Browser timezone and top-row limit.",
      },
    ],
    inputExample: { date: "2026-08-31", rankBy: "deposits", limit: 10 },
    outputSummary:
      "Returns team/self totals and ranked rows with complete daily metrics.",
    outputExample: {
      date: "2026-08-31",
      rankBy: "deposits",
      summary: {},
      rankings: [],
    },
    accessMode: "read",
  },
  {
    name: "search_ib_partners",
    title: "Search IB partners",
    description:
      "Find approved IB partners available to the IB statement report.",
    icon: "fa-magnifying-glass",
    permissionKeys: ["page_ibstatement_readonly", "page_ibstatement"],
    sectionKey: "report",
    inputSummary: "Provide IB code, name, or alias search text.",
    inputFields: [
      {
        name: "query",
        type: "string",
        requirement: "Required",
        description: "IB code, name, or alias.",
      },
      {
        name: "page / limit",
        type: "integer",
        requirement: "Optional",
        description: "Limit defaults to 25 and is capped at 50.",
      },
    ],
    inputExample: { query: "IB-001" },
    outputSummary: "Returns visible partner IDs, codes, names, and pagination.",
    outputExample: {
      partners: [{ id: 1, ibCode: "IB-001", name: "Partner" }],
      pagination: PAGINATION_RESPONSE,
    },
    accessMode: "read",
  },
  {
    name: "get_ib_statement",
    title: "Get IB statement",
    description: "Return a scoped IB statement with bounded account details.",
    icon: "fa-file-invoice-dollar",
    permissionKeys: ["page_ibstatement_readonly", "page_ibstatement"],
    sectionKey: "report",
    inputSummary: "Provide one IB ID or code and an optional date range.",
    inputFields: [
      {
        name: "ibPartnerId / ibCode",
        type: "IB selector",
        requirement: "One of",
        description: "Exact IB identifier.",
      },
      {
        name: "startDate / endDate",
        type: "YYYY-MM-DD",
        requirement: "Optional pair",
        description: "Defaults to the current month.",
      },
      {
        name: "page / limit",
        type: "integer",
        requirement: "Optional",
        description: "Account-detail pagination.",
      },
    ],
    inputExample: {
      ibCode: "IB-001",
      startDate: "2026-08-01",
      endDate: "2026-08-31",
    },
    outputSummary:
      "Returns statement headline, movements, breakdowns, and paginated accounts.",
    outputExample: {
      partner: { id: 1, ibCode: "IB-001" },
      accounts: [],
      accountsPagination: PAGINATION_RESPONSE,
    },
    accessMode: "read",
  },
  {
    name: "list_custom_reports",
    title: "List custom reports",
    description:
      "List custom reports available in the current administrator's report scope.",
    icon: "fa-table-list",
    permissionKeys: ["page_fundingreport_readonly"],
    sectionKey: "report",
    inputSummary: "Optionally search report names and paginate.",
    inputFields: [
      {
        name: "search / page / limit",
        type: "filter",
        requirement: "Optional",
        description: "Name search and pagination.",
      },
    ],
    inputExample: { search: "Funding" },
    outputSummary: "Returns report metadata, widget counts, and pagination.",
    outputExample: { reports: [], pagination: PAGINATION_RESPONSE },
    accessMode: "read",
  },
  {
    name: "get_custom_report_results",
    title: "Get custom report results",
    description:
      "Return bounded saved results for one custom report or widget.",
    icon: "fa-table-cells",
    permissionKeys: ["page_fundingreport_readonly"],
    sectionKey: "report",
    inputSummary: "Provide a report ID and optional widget ID or pagination.",
    inputFields: [
      {
        name: "reportId",
        type: "string",
        requirement: "Required",
        description: "Exact custom report ID.",
      },
      {
        name: "widgetId / page / limit",
        type: "filter",
        requirement: "Optional",
        description: "Widget selection and result pagination.",
      },
    ],
    inputExample: { reportId: "12" },
    outputSummary:
      "Returns saved widget views using configured visible columns and filters.",
    outputExample: { report: { id: "12" }, widgets: [], truncated: false },
    accessMode: "read",
  },
  {
    name: "navigate_to_report",
    title: "Navigate to report",
    description:
      "Open a funding, daily sales, IB statement, operation log, or custom report page.",
    icon: "fa-compass",
    permissionKeys: [
      "page_fundingreport_readonly",
      "page_dailyreport_readonly",
      "page_ibstatement_readonly",
      "page_ibstatement",
      "page_operationlogreport_readonly",
    ],
    sectionKey: "report",
    inputSummary: "Choose a report key and optional custom report ID.",
    inputFields: [
      {
        name: "reportKey",
        type: "enum",
        requirement: "Required",
        description:
          "funding, daily_sales, ib_statement, operation_logs, or custom.",
      },
      {
        name: "reportId",
        type: "string",
        requirement: "Optional",
        description: "Custom report ID only.",
      },
    ],
    inputExample: { reportKey: "funding" },
    outputSummary: "Navigates to the permission-checked route.",
    outputExample: {
      success: true,
      reportKey: "funding",
      route: "/funding-report",
    },
    accessMode: "read",
  },
  {
    name: "export_funding_report",
    title: "Export funding report",
    description:
      "Queue a scoped funding report export and open its automatic-download progress page.",
    icon: "fa-file-export",
    permissionKeys: ["page_fundingreport_export"],
    sectionKey: "report",
    inputSummary:
      "Provide optional funding period, type, status, or amount filters.",
    inputFields: [
      {
        name: "startDate / endDate",
        type: "YYYY-MM-DD",
        requirement: "Optional pair",
        description: "Defaults to the current month.",
      },
      {
        name: "type / status / minAmount / maxAmount",
        type: "filters",
        requirement: "Optional",
        description: "Funding export filters.",
      },
    ],
    inputExample: { startDate: "2026-08-01", endDate: "2026-08-31" },
    outputSummary: "Returns the export job and typed progress URL.",
    outputExample: {
      jobId: "wmcp_funding_123",
      exportKind: "funding_report",
      queued: true,
    },
    accessMode: "export",
  },
  {
    name: "export_ib_statement",
    title: "Export IB statement",
    description:
      "Queue a scoped IB statement export and open its automatic-download progress page.",
    icon: "fa-file-export",
    permissionKeys: ["page_ibstatement_readonly", "page_ibstatement_export"],
    permissionMatch: "all",
    sectionKey: "report",
    inputSummary:
      "Provide one IB selector, optional period, and CSV or Excel format.",
    inputFields: [
      {
        name: "ibPartnerId / ibCode",
        type: "IB selector",
        requirement: "One of",
        description: "Exact visible IB.",
      },
      {
        name: "startDate / endDate / format",
        type: "filters",
        requirement: "Optional",
        description: "Period and output format.",
      },
    ],
    inputExample: {
      ibPartnerId: 1,
      startDate: "2026-08-01",
      endDate: "2026-08-31",
      format: "csv",
    },
    outputSummary: "Returns the export job and typed progress URL.",
    outputExample: {
      jobId: "wmcp_ib_123",
      exportKind: "ib_statement",
      queued: true,
    },
    accessMode: "export",
  },
  {
    name: "search_admin_users",
    title: "Search administrator users",
    description:
      "Find non-deleted administrator accounts by ID, name, username, email, role, or status.",
    icon: "fa-user-shield",
    permissionKeys: [
      "page_accountmanagement_readonly",
      "page_accountmanagement_edit",
    ],
    sectionKey: "admin_log",
    inputSummary: "Provide at least one administrator filter.",
    inputFields: [
      {
        name: "query",
        type: "string",
        requirement: "At least one",
        description: "Name, username, email, or exact administrator ID.",
      },
      {
        name: "roleId / status",
        type: "integer / enum",
        requirement: "At least one",
        description: "Exact role ID or active/inactive status.",
      },
      {
        name: "page / limit",
        type: "integer",
        requirement: "Optional",
        description: "Limit defaults to 25 and caps at 50.",
      },
    ],
    inputExample: { query: "Sarah", status: "active" },
    outputSummary: "Returns sanitized administrators and pagination.",
    outputExample: {
      adminUsers: [
        {
          id: 42,
          fullName: "Sarah Tan",
          status: "active",
          role: { id: 4, name: "Operations" },
        },
      ],
      pagination: PAGINATION_RESPONSE,
    },
    accessMode: "read",
  },
  {
    name: "get_admin_user",
    title: "Get administrator user",
    description:
      "Retrieve one sanitized administrator profile with current role and account status.",
    icon: "fa-address-card",
    permissionKeys: [
      "page_accountmanagement_readonly",
      "page_accountmanagement_edit",
    ],
    sectionKey: "admin_log",
    inputSummary: "Provide an exact administrator ID.",
    inputFields: [
      {
        name: "adminUserId",
        type: "integer",
        requirement: "Required",
        description: "Exact administrator ID.",
      },
    ],
    inputExample: { adminUserId: 42 },
    outputSummary: "Returns a sanitized administrator profile.",
    outputExample: {
      adminUser: {
        id: 42,
        fullName: "Sarah Tan",
        status: "active",
        role: { id: 4, name: "Operations" },
      },
    },
    accessMode: "read",
  },
  {
    name: "get_role_permissions",
    title: "Get role permissions",
    description:
      "Retrieve all active permissions assigned to one exact administrator role.",
    icon: "fa-key",
    permissionKeys: [
      "page_rolemanagement_readonly",
      "page_rolemanagement_edit",
    ],
    sectionKey: "admin_log",
    inputSummary: "Provide exactly one role ID or exact role name.",
    inputFields: [
      {
        name: "roleId / roleName",
        type: "integer / string",
        requirement: "One of",
        description: "Exact administrator role selector.",
      },
    ],
    inputExample: { roleName: "Operations" },
    outputSummary: "Returns the role and its assigned active permissions.",
    outputExample: {
      role: { id: 4, name: "Operations" },
      permissions: [{ key: "page_withdraw_approve" }],
    },
    accessMode: "read",
  },
  {
    name: "find_roles_by_permission",
    title: "Find roles by permission",
    description:
      "Find roles that grant an exact active permission, including implicit Super Admin access.",
    icon: "fa-users-gear",
    permissionKeys: [
      "page_rolemanagement_readonly",
      "page_rolemanagement_edit",
    ],
    sectionKey: "admin_log",
    inputSummary: "Provide an exact permission key.",
    inputFields: [
      {
        name: "permissionKey",
        type: "string",
        requirement: "Required",
        description: "Exact key, such as page_withdraw_approve.",
      },
      {
        name: "includeInactive",
        type: "boolean",
        requirement: "Optional",
        description: "Include inactive roles; defaults to false.",
      },
    ],
    inputExample: { permissionKey: "page_withdraw_approve" },
    outputSummary: "Returns the permission and matching roles.",
    outputExample: {
      permission: { key: "page_withdraw_approve" },
      roles: [{ id: 4, name: "Operations" }],
    },
    accessMode: "read",
  },
  {
    name: "check_admin_user_permission",
    title: "Check administrator permission",
    description:
      "Check effective access through role, custom grant, or Super Admin privileges.",
    icon: "fa-user-check",
    permissionKeys: [
      "page_rolemanagement_readonly",
      "page_rolemanagement_edit",
    ],
    sectionKey: "admin_log",
    inputSummary: "Provide an administrator ID and exact permission key.",
    inputFields: [
      {
        name: "adminUserId",
        type: "integer",
        requirement: "Required",
        description: "Exact administrator ID.",
      },
      {
        name: "permissionKey",
        type: "string",
        requirement: "Required",
        description: "Exact active permission key.",
      },
    ],
    inputExample: { adminUserId: 42, permissionKey: "page_withdraw_approve" },
    outputSummary: "Returns the effective result and grant sources.",
    outputExample: { hasPermission: true, sources: ["role"] },
    accessMode: "read",
  },
  {
    name: "search_operation_logs",
    title: "Search operation logs",
    description:
      "Search newest-first administrator activity across modules by operator, module, operation, target, dates, or audit text.",
    icon: "fa-clock-rotate-left",
    permissionKeys: ["page_operationlogreport_readonly"],
    sectionKey: "admin_log",
    inputSummary: "Provide at least one audit filter; pagination is optional.",
    inputFields: [
      {
        name: "operatorId / module / operationType",
        type: "integer / key",
        requirement: "At least one",
        description: "Exact operator, submodule, or operation type.",
      },
      {
        name: "targetType / targetId",
        type: "enum / integer",
        requirement: "Optional",
        description: "Exact target; targetId requires targetType.",
      },
      {
        name: "startDate / endDate / query",
        type: "date / string",
        requirement: "Optional",
        description: "Inclusive UTC dates or audit text.",
      },
      {
        name: "page / limit",
        type: "integer",
        requirement: "Optional",
        description: "Limit defaults to 25 and caps at 50.",
      },
    ],
    inputExample: { module: "role_management" },
    outputSummary: "Returns sanitized operation logs and pagination.",
    outputExample: { operationLogs: [], pagination: PAGINATION_RESPONSE },
    accessMode: "read",
  },
  {
    name: "get_operation_log",
    title: "Get operation log",
    description: "Retrieve one exact sanitized operation-log record by ID.",
    icon: "fa-file-lines",
    permissionKeys: ["page_operationlogreport_readonly"],
    sectionKey: "admin_log",
    inputSummary: "Provide an exact operation-log ID.",
    inputFields: [
      {
        name: "operationLogId",
        type: "integer",
        requirement: "Required",
        description: "Exact operation-log ID.",
      },
    ],
    inputExample: { operationLogId: 123 },
    outputSummary: "Returns the complete visible audit record.",
    outputExample: { operationLog: { id: 123, operationType: "edit" } },
    accessMode: "read",
  },
  {
    name: "navigate_to_operation_logs",
    title: "Navigate to operation logs",
    description: "Open the audit report with the same exact filters applied.",
    icon: "fa-compass",
    permissionKeys: ["page_operationlogreport_readonly"],
    sectionKey: "admin_log",
    inputSummary: "Provide the audit filters to open.",
    inputFields: [
      {
        name: "audit filters",
        type: "object fields",
        requirement: "At least one",
        description:
          "Same filters as search_operation_logs without pagination.",
      },
    ],
    inputExample: { targetType: "client", targetId: 456 },
    outputSummary: "Navigates to the filtered audit report.",
    outputExample: {
      success: true,
      route:
        "/operation-log-report?modelKey=all&targetType=client&targetId=456",
    },
    accessMode: "read",
  },
  {
    name: "export_operation_logs",
    title: "Export operation logs",
    description:
      "Start a permission-gated CSV export for the same audit filters and open its progress page.",
    icon: "fa-file-export",
    permissionKeys: ["page_operationlogreport_export"],
    sectionKey: "admin_log",
    inputSummary: "Provide the audit filters to export.",
    inputFields: [
      {
        name: "audit filters",
        type: "object fields",
        requirement: "At least one",
        description:
          "Same filters as search_operation_logs without pagination.",
      },
    ],
    inputExample: { operatorId: 42, module: "role_management" },
    outputSummary: "Returns an owner-bound job and opens the progress page.",
    outputExample: { jobId: "aolr_abc123", queued: true, opened: true },
    accessMode: "export",
  },
];

export const groupWebMcpTools = (catalog = WEBMCP_TOOL_CATALOG) =>
  WEBMCP_TOOL_SECTIONS.map((section) => ({
    ...section,
    tools: catalog.filter((tool) => tool.sectionKey === section.key),
  })).filter(({ tools }) => tools.length > 0);
