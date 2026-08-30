import axios from "axios";
import router from "@/router";
import { useClientAuthStore } from "@/stores/clientAuth";

const api = axios.create({
  baseURL:
    import.meta.env.VITE_API_BASE_URL ||
    "http://localhost/Utrada%20CRM/back-end/index.php?path=api",
  timeout: 60000,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
});

// logout接口白名单，避免在logout响应上触发401跳转循环
const LOGOUT_ENDPOINTS = ["/auth/logout", "auth/logout"];

// 预览模式下禁止写操作时的提示文案（英文）
const PREVIEW_BLOCK_MESSAGE = "Preview mode - operation not allowed";

// 预览模式下仍放行的写操作端点：本质是只读导出，用 POST 只是为了把 items 放进 body（避免 URL 长度限制），不会修改客户数据
const PREVIEW_ALLOWED_WRITE_ENDPOINTS = [
  "/client/commission-report/export",
  "client/commission-report/export",
  "/client/commission-report/export-cancel",
  "client/commission-report/export-cancel",
  "/client/deposit-withdraw-report/export",
  "client/deposit-withdraw-report/export",
  "/client/deposit-withdraw-report/export-cancel",
  "client/deposit-withdraw-report/export-cancel",
  "/client/ib-statement-report/export",
  "client/ib-statement-report/export",
  "/client/ib-statement-report/export-cancel",
  "client/ib-statement-report/export-cancel",
];

// Request interceptor
api.interceptors.request.use(
  async (config) => {
    const method = (config.method || "get").toLowerCase();
    const isWrite = ["post", "put", "patch", "delete"].includes(method);
    const requestUrl = config.url || "";
    // 必须精确匹配（Array.includes），不能用子串匹配，否则未来带相同前缀的写接口会被误放行
    const isAllowedWrite = PREVIEW_ALLOWED_WRITE_ENDPOINTS.includes(requestUrl);
    if (isWrite && !isAllowedWrite) {
      let isPreview = false;
      try {
        const clientAuthStore = useClientAuthStore();
        isPreview = !!clientAuthStore.isPreviewMode;
      } catch (_) {}
      if (!isPreview && typeof sessionStorage !== "undefined") {
        isPreview = !!sessionStorage.getItem("previewToken");
      }
      if (isPreview) {
        if (typeof window !== "undefined") {
          window.dispatchEvent(
            new CustomEvent("preview-mode-blocked", {
              detail: { message: PREVIEW_BLOCK_MESSAGE },
            }),
          );
          try {
            window.alert(PREVIEW_BLOCK_MESSAGE);
          } catch (_) {}
        }
        const err = new Error(PREVIEW_BLOCK_MESSAGE);
        err.isPreviewBlock = true;
        return Promise.reject(err);
      }
    }

    // 从baseURL中提取base和path参数
    const baseURL = config.baseURL || "";
    const urlParts = baseURL.split("?path=");

    if (urlParts.length === 2) {
      // 如果baseURL包含?path=参数，需要特殊处理
      const base = urlParts[0]; // http://localhost/Utrada%20CRM/back-end/index.php
      const pathPrefix = urlParts[1]; // api

      // 移除endpoint前面的斜杠
      let endpoint = config.url || "";
      if (endpoint.startsWith("/")) {
        endpoint = endpoint.substring(1);
      }

      // 构建完整路径
      const fullPath = pathPrefix ? `${pathPrefix}/${endpoint}` : endpoint;

      // 重新构建URL
      config.url = `${base}?path=${fullPath}`;
      config.baseURL = ""; // 清空baseURL，因为已经包含在url中
    } else {
      // 正常处理 - 移除endpoint前面的斜杠
      if (config.url && config.url.startsWith("/")) {
        config.url = config.url.substring(1);
      }
    }

    // 处理 FormData：如果是 FormData，让 axios 自动设置 Content-Type（包括 boundary）
    // 如果手动设置了 Content-Type，需要删除默认的 application/json
    if (config.data instanceof FormData) {
      delete config.headers["Content-Type"];
    }

    // 预览模式与正常模式隔离：预览时只用 X-Preview-Token，不读 localStorage 的 clientToken
    let isPreview = false;
    try {
      const clientAuthStore = useClientAuthStore();
      isPreview = !!clientAuthStore.isPreviewMode;
      if (!isPreview && typeof sessionStorage !== "undefined") {
        isPreview = !!sessionStorage.getItem("previewToken");
      }
      if (isPreview && clientAuthStore && clientAuthStore.previewToken) {
        config.headers["X-Preview-Token"] = clientAuthStore.previewToken;
      } else if (isPreview && typeof sessionStorage !== "undefined") {
        const t = sessionStorage.getItem("previewToken");
        if (t) config.headers["X-Preview-Token"] = t;
      }
    } catch (_) {}

    if (!isPreview) {
      const token = localStorage.getItem("clientToken");
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
    }

    return config;
  },
  (error) => {
    return Promise.reject(error);
  },
);

// Response interceptor
api.interceptors.response.use(
  async (response) => {
    // blob 响应（文件下载）直接返回原始响应，不走下面的 JSON 业务判断
    if (
      response.config.responseType === "blob" ||
      response.data instanceof Blob
    ) {
      return response;
    }

    const data = response.data;

    // 检查响应体中的状态码（现在所有错误都返回200 HTTP状态码）
    // 如果响应中有statusCode字段，检查是否为401（需要登出）
    if (data && !data.success && data.statusCode === 401) {
      const requestUrl = response.config?.url || "";
      const isLogoutEndpoint = LOGOUT_ENDPOINTS.some((endpoint) =>
        requestUrl.includes(endpoint),
      );

      if (isLogoutEndpoint) {
        localStorage.removeItem("clientToken");
        return Promise.reject(data);
      }

      // 预览模式下不跳转登录、不清理 localStorage，避免与正常模式混淆
      try {
        if (useClientAuthStore().isPreviewMode) {
          return Promise.reject(data);
        }
      } catch (_) {}

      console.log("🔴 401 Unauthorized detected in response interceptor", data);
      localStorage.removeItem("clientToken");
      const currentPath = router.currentRoute.value.path;
      const isOnLoginPage =
        currentPath === "/client/login" ||
        currentPath.includes("/client/login");
      if (!isOnLoginPage) {
        console.log("Redirecting to client login page...");
        router.push("/client/login");
        return Promise.reject(new Error(data.message || "Unauthorized"));
      }
      return Promise.reject(data);
    }

    // 如果业务逻辑错误（success: false），将其作为错误抛出，让业务代码在catch中处理
    if (data && !data.success) {
      return Promise.reject(data);
    }

    return data;
  },
  async (error) => {
    if (error.response?.data) {
      const data = error.response.data;
      if (data && data.statusCode === 401) {
        const requestUrl = error.config?.url || "";
        const isLogoutEndpoint = LOGOUT_ENDPOINTS.some((endpoint) =>
          requestUrl.includes(endpoint),
        );
        if (isLogoutEndpoint) {
          localStorage.removeItem("clientToken");
          return Promise.reject(data);
        }
        try {
          if (useClientAuthStore().isPreviewMode) return Promise.reject(data);
        } catch (_) {}
        console.log("🔴 401 Unauthorized detected in error handler", data);
        localStorage.removeItem("clientToken");
        const currentPath = router.currentRoute.value.path;
        const isOnLoginPage =
          currentPath === "/client/login" ||
          currentPath.includes("/client/login");
        if (!isOnLoginPage) {
          console.log("Redirecting to client login page...");
          router.push("/client/login");
        }
        return Promise.reject(data);
      }
      return Promise.reject(data);
    }
    return Promise.reject(error);
  },
);

export default api;
