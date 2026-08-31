import axios from "axios";

const getWebMcpApiBaseURL = () => {
  const configuredUrl = import.meta.env.VITE_WEBMCP_API_BASE_URL;
  if (configuredUrl) return configuredUrl;

  const apiUrl = import.meta.env.VITE_API_BASE_URL || "/index.php?path=api";
  if (apiUrl.includes("?path=")) {
    const [baseUrl] = apiUrl.split("?path=");
    return `${baseUrl}?path=api/webmcp`;
  }

  const normalizedUrl = apiUrl.replace(/\/$/, "");
  return normalizedUrl.endsWith("/api")
    ? `${normalizedUrl}/webmcp`
    : `${normalizedUrl}/api/webmcp`;
};

const webMcpApi = axios.create({
  baseURL: getWebMcpApiBaseURL(),
  timeout: 60000,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
});

webMcpApi.interceptors.request.use((config) => {
  const token = localStorage.getItem("token");
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  const adminLanguage = localStorage.getItem("adminLanguage") || "en";
  config.headers["Accept-Language"] =
    adminLanguage === "zh" ? "zh-CN,zh;q=0.9,en;q=0.8" : "en-US,en;q=0.9";

  return config;
});

webMcpApi.interceptors.response.use(
  (response) => {
    const payload = response.data;
    if (payload && payload.success === false) {
      const error = new Error(
        payload.message || "WebMCP client lookup failed.",
      );
      error.statusCode = payload.statusCode;
      error.response = { data: payload };
      return Promise.reject(error);
    }
    return payload?.data ?? payload;
  },
  (error) => Promise.reject(error),
);

export const adminWebMcpApi = {
  async getClient(input) {
    return webMcpApi.get("/admin/get-client", { params: input });
  },

  async searchClients(input) {
    return webMcpApi.get("/admin/search-clients", { params: input });
  },

  async getClientDocuments(input) {
    return webMcpApi.get("/admin/get-client-documents", { params: input });
  },

  async getClientTradingAccounts(input) {
    return webMcpApi.get("/admin/get-client-trading-accounts", {
      params: input,
    });
  },

  async getClientRecentTransactions(input) {
    return webMcpApi.get("/admin/get-client-recent-transactions", {
      params: input,
    });
  },

  async searchTransactions(input) {
    return webMcpApi.get("/admin/search-transactions", { params: input });
  },

  async getTransaction(input) {
    return webMcpApi.get("/admin/get-transaction", { params: input });
  },

  async searchKyc(input) {
    return webMcpApi.get("/admin/search-kyc", { params: input });
  },

  async getKyc(input) {
    return webMcpApi.get("/admin/get-kyc", { params: input });
  },

  async getKycAnswers(input) {
    return webMcpApi.get("/admin/get-kyc-answers", { params: input });
  },

  async getKycDocuments(input) {
    return webMcpApi.get("/admin/get-kyc-documents", { params: input });
  },

  async getKycProgress(input) {
    return webMcpApi.get("/admin/get-kyc-progress", { params: input });
  },

  async getKycTimeline(input) {
    return webMcpApi.get("/admin/get-kyc-timeline", { params: input });
  },

  async getIbPartner(input) {
    return webMcpApi.get("/admin/get-ib-partner", { params: input });
  },

  async getIbNetwork(input) {
    return webMcpApi.get("/admin/get-ib-network", { params: input });
  },

  async getIbNetworkStats(input) {
    return webMcpApi.get("/admin/get-ib-network-stats", { params: input });
  },

  async getIbClients(input) {
    return webMcpApi.get("/admin/get-ib-clients", { params: input });
  },

  async getClientIbUpline(input) {
    return webMcpApi.get("/admin/get-client-ib-upline", { params: input });
  },

  async searchSales(input) {
    return webMcpApi.get("/admin/search-sales", { params: input });
  },

  async getSalesClients(input) {
    return webMcpApi.get("/admin/get-sales-clients", { params: input });
  },

  async getSalesPartners(input) {
    return webMcpApi.get("/admin/get-sales-partners", { params: input });
  },

  async getSalesPerformance(input) {
    return webMcpApi.get("/admin/get-sales-performance", { params: input });
  },

  async getSalesDailySummary(input) {
    return webMcpApi.get("/admin/get-sales-daily-summary", { params: input });
  },

  async getSalesLeaderboard(input) {
    return webMcpApi.get("/admin/get-sales-leaderboard", { params: input });
  },

  async getFundingSummary(input) {
    return webMcpApi.get("/admin/get-funding-summary", { params: input });
  },

  async searchFundingTransactions(input) {
    return webMcpApi.get("/admin/search-funding-transactions", { params: input });
  },

  async getDailySalesPerformance(input) {
    return webMcpApi.get("/admin/get-daily-sales-performance", { params: input });
  },

  async searchIbPartners(input) {
    return webMcpApi.get("/admin/search-ib-partners", { params: input });
  },

  async getIbStatement(input) {
    return webMcpApi.get("/admin/get-ib-statement", { params: input });
  },

  async searchAdminUsers(input) {
    return webMcpApi.get("/admin/search-admin-users", { params: input });
  },

  async getAdminUser(input) {
    return webMcpApi.get("/admin/get-admin-user", { params: input });
  },

  async getRolePermissions(input) {
    return webMcpApi.get("/admin/get-role-permissions", { params: input });
  },

  async findRolesByPermission(input) {
    return webMcpApi.get("/admin/find-roles-by-permission", { params: input });
  },

  async checkAdminUserPermission(input) {
    return webMcpApi.get("/admin/check-admin-user-permission", { params: input });
  },

  async searchOperationLogs(input) {
    return webMcpApi.get("/admin/search-operation-logs", { params: input });
  },

  async getOperationLog(input) {
    return webMcpApi.get("/admin/get-operation-log", { params: input });
  },

  async exportOperationLogs(input) {
    return webMcpApi.post("/admin/export-operation-logs", input);
  },

  async listCustomReports(input) {
    return webMcpApi.get("/admin/list-custom-reports", { params: input });
  },

  async getCustomReportResults(input) {
    return webMcpApi.get("/admin/get-custom-report-results", { params: input });
  },

  async exportFundingReport(input) {
    return webMcpApi.post("/admin/export-funding-report", input);
  },

  async exportIbStatement(input) {
    return webMcpApi.post("/admin/export-ib-statement", input);
  },

  async getReportExportStatus(jobId, exportKind) {
    return webMcpApi.get("/admin/report-export-status", {
      params: { jobId, exportKind },
    });
  },

  async downloadReportExport(jobId, exportKind) {
    return webMcpApi.get("/admin/report-export-download", {
      params: { jobId, exportKind },
      responseType: "blob",
    });
  },

  async exportClients(input) {
    return webMcpApi.post("/admin/export-clients", input);
  },

  async exportClientTransactions(input) {
    return webMcpApi.post("/admin/export-client-transactions", input);
  },

  async exportTransactions(input) {
    return webMcpApi.post("/admin/export-transactions", input);
  },

  async getExportStatus(jobId) {
    return webMcpApi.get("/admin/export-status", { params: { jobId } });
  },

  async downloadExport(jobId) {
    return webMcpApi.get("/admin/export-download", {
      params: { jobId },
      responseType: "blob",
    });
  },
};

export { getWebMcpApiBaseURL };
