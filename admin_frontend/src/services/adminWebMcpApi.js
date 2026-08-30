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

  async exportClients(input) {
    return webMcpApi.post("/admin/export-clients", input);
  },

  async exportClientTransactions(input) {
    return webMcpApi.post("/admin/export-client-transactions", input);
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
