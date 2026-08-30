import axios from "axios";
import router from "@/router";
import { useClientAuthStore } from "@/stores/clientAuth";

// logout接口白名单，避免在logout响应上触发401跳转循环
const CLIENT_LOGOUT_ENDPOINTS = ["/client/auth/logout", "client/auth/logout"];

// 401 时不跳登录的端点：exchange-handoff 失败（code 过期/被用）应让落地页渲染错误，
// 而不是把 WebView 整个推到登录页
const NO_AUTO_LOGIN_REDIRECT_ENDPOINTS = [
  "/client/auth/logout",
  "client/auth/logout",
  "/client/auth/exchange-handoff",
  "client/auth/exchange-handoff",
];

// 客户端专用 API 实例
// 客户端 API 使用直接路径访问（不通过 index.php 路由）
const getClientApiBaseURL = () => {
  // 优先使用专门的客户端 API URL
  const clientUrl = import.meta.env.VITE_CLIENT_API_BASE_URL;
  if (clientUrl) {
    console.log(
      "Client API baseURL (from VITE_CLIENT_API_BASE_URL):",
      clientUrl,
    );
    return clientUrl;
  }

  // 否则从管理端 API URL 中提取基础路径
  const envUrl =
    import.meta.env.VITE_API_BASE_URL ||
    "http://localhost/Utrada%20CRM/back-end";

  // 如果 URL 包含 index.php?path=，则提取基础路径
  if (envUrl.includes("index.php")) {
    const baseUrl = envUrl.split("/index.php")[0];
    // console.log('Client API baseURL (extracted from admin URL):', baseUrl)
    return baseUrl;
  }

  // console.log('Client API baseURL (direct):', envUrl)
  return envUrl;
};

const clientApiBaseURL = getClientApiBaseURL();

const clientApi = axios.create({
  baseURL: clientApiBaseURL,
  timeout: 60000,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
});

// 预览模式下禁止写操作时的提示文案（英文）
const PREVIEW_BLOCK_MESSAGE = "Preview mode - operation not allowed";

// Log the full configuration for debugging
// console.log('🔧 Client API Configuration:', {
//   baseURL: clientApiBaseURL,
//   envURL: import.meta.env.VITE_API_BASE_URL,
//   clientEnvURL: import.meta.env.VITE_CLIENT_API_BASE_URL
// })

// Request interceptor：预览模式只带 X-Preview-Token，非 GET 请求直接拦截不发出
clientApi.interceptors.request.use(
  async (config) => {
    const method = (config.method || "get").toLowerCase();
    const isWrite = ["post", "put", "patch", "delete"].includes(method);
    if (isWrite) {
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

    let isPreview = false;
    try {
      const clientAuthStore = useClientAuthStore();
      isPreview = !!clientAuthStore.isPreviewMode;
      if (!isPreview && typeof sessionStorage !== "undefined") {
        isPreview = !!sessionStorage.getItem("previewToken");
      }
      if (isPreview) {
        const t =
          (clientAuthStore && clientAuthStore.previewToken) ||
          (typeof sessionStorage !== "undefined"
            ? sessionStorage.getItem("previewToken")
            : null);
        if (t) config.headers["X-Preview-Token"] = t;
        return config;
      }
    } catch (_) {}

    const token =
      sessionStorage.getItem("clientToken") ||
      localStorage.getItem("clientToken");
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  },
);

// Response interceptor
clientApi.interceptors.response.use(
  async (response) => {
    const data = response.data;

    // 检查响应体中的状态码（现在所有错误都返回200 HTTP状态码）
    if (data && !data.success && data.statusCode === 401) {
      const requestUrl = response.config?.url || "";
      const isLogoutEndpoint = CLIENT_LOGOUT_ENDPOINTS.some((endpoint) =>
        requestUrl.includes(endpoint),
      );
      const isNoRedirectEndpoint = NO_AUTO_LOGIN_REDIRECT_ENDPOINTS.some(
        (endpoint) => requestUrl.includes(endpoint),
      );

      if (isLogoutEndpoint) {
        sessionStorage.removeItem("clientToken");
        localStorage.removeItem("clientToken");
        localStorage.removeItem("clientRememberMe");
        return Promise.reject(data);
      }

      // exchange-handoff 等端点：失败让上层组件自己渲染错误，不要把整个 WebView 推到登录页
      if (isNoRedirectEndpoint) {
        return Promise.reject(data);
      }

      // 预览模式下不跳转登录、不清理 localStorage
      try {
        if (useClientAuthStore().isPreviewMode) {
          return Promise.reject(data);
        }
      } catch (_) {}

      console.log(
        "🔴 401 Unauthorized detected in clientApi response interceptor",
        data,
      );
      sessionStorage.removeItem("clientToken");
      localStorage.removeItem("clientToken");
      localStorage.removeItem("clientRememberMe");
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
        const isLogoutEndpoint = CLIENT_LOGOUT_ENDPOINTS.some((endpoint) =>
          requestUrl.includes(endpoint),
        );
        const isNoRedirectEndpoint = NO_AUTO_LOGIN_REDIRECT_ENDPOINTS.some(
          (endpoint) => requestUrl.includes(endpoint),
        );

        if (!isLogoutEndpoint && !isNoRedirectEndpoint) {
          try {
            if (useClientAuthStore().isPreviewMode) {
              return Promise.reject(data);
            }
          } catch (_) {}

          console.log(
            "🔴 401 Unauthorized detected in clientApi error handler",
            data,
          );
          sessionStorage.removeItem("clientToken");
          localStorage.removeItem("clientToken");
          localStorage.removeItem("clientRememberMe");
          const currentPath = router.currentRoute.value.path;
          const isOnLoginPage =
            currentPath === "/client/login" ||
            currentPath.includes("/client/login");
          if (!isOnLoginPage) {
            console.log("Redirecting to client login page...");
            router.push("/client/login");
          }
        }
      }
      return Promise.reject(data);
    }
    return Promise.reject(error);
  },
);

export default clientApi;
