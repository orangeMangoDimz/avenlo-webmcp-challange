import axios from "axios";
import router from "@/router";
import { useAuthStore } from "@/stores/auth";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";

const api = axios.create({
  baseURL:
    import.meta.env.VITE_API_BASE_URL ||
    "http://localhost/Utrada%20CRM/back-end/index.php?path=api",
  timeout: 60000, // 1 minute (60000ms) - increased from 10s for operations like Internal Transfer approval
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
});

// logout接口白名单，避免在logout响应上触发401跳转循环
const LOGOUT_ENDPOINTS = ["/auth/logout", "auth/logout"];

function applyApiErrorCodeTranslation(data) {
  if (data && data.errorCode != null && data.errorCode !== "") {
    data.message = translateApiErrorMessage(data.errorCode, data.message);
  }
  return data;
}

// Request interceptor
api.interceptors.request.use(
  (config) => {
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

    const token = localStorage.getItem("token");
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    const adminLang = localStorage.getItem("adminLanguage") || "en";
    config.headers["Accept-Language"] =
      adminLang === "zh" ? "zh-CN,zh;q=0.9,en;q=0.8" : "en-US,en;q=0.9";

    return config;
  },
  (error) => {
    return Promise.reject(error);
  },
);

// Response interceptor
api.interceptors.response.use(
  (response) => {
    // 如果是 blob 响应（文件下载），直接返回原始响应
    if (
      response.config.responseType === "blob" ||
      response.data instanceof Blob
    ) {
      return response;
    }

    let data = response.data;
    if (data && typeof data === "object" && !data.success) {
      data = applyApiErrorCodeTranslation(data);
    }

    // 检查响应体中的状态码（现在所有错误都返回200 HTTP状态码）
    // 如果响应中有statusCode字段，检查是否为401（需要登出）
    if (data && !data.success && data.statusCode === 401) {
      // console.log('🔴 401 Unauthorized detected in response interceptor', data)

      // 检查是否是logout接口，避免循环调用
      const requestUrl = response.config?.url || "";
      const isLogoutEndpoint = LOGOUT_ENDPOINTS.some((endpoint) =>
        requestUrl.includes(endpoint),
      );

      if (isLogoutEndpoint) {
        // logout接口返回401时，只清理状态，不调用logout和跳转（后端已修复为幂等，理论上不会到这里）
        const authStore = useAuthStore();
        authStore.user = null;
        authStore.token = null;
        authStore.permissions = [];
        localStorage.removeItem("token");
        localStorage.removeItem("rememberMe");
        return Promise.reject(data);
      }

      // Unauthorized - 清除 token 和 auth store 状态
      const authStore = useAuthStore();
      authStore.logout(); // 这会清除 localStorage 和 store 中的状态

      // 检查当前是否在登录页面
      const currentPath = router.currentRoute.value.path;
      const isOnLoginPage =
        currentPath === "/login" || currentPath.includes("/login");

      // console.log('Current path:', currentPath, 'Is on login page:', isOnLoginPage)

      // 如果不在登录页面，才跳转到登录页面
      // 如果已经在登录页面，不跳转，让业务代码显示错误信息
      if (!isOnLoginPage) {
        // console.log('Redirecting to login page...')
        // 使用 Vue Router 进行跳转
        router.push("/login").catch(() => {
          // 忽略导航重复的错误
        });
        return Promise.reject(new Error(data.message || "Unauthorized"));
      }

      // 在登录页面时，返回错误数据，让业务代码处理并显示错误信息
      return Promise.reject(data);
    }

    // 如果业务逻辑错误（success: false），将其作为错误抛出，让业务代码在catch中处理
    if (data && !data.success) {
      return Promise.reject(data);
    }

    return data;
  },
  (error) => {
    // 处理网络错误或其他异常（不是业务错误）
    // 如果服务器返回了响应，检查响应体中的状态码
    if (error.response?.data) {
      let data = error.response.data;
      if (data && typeof data === "object") {
        data = applyApiErrorCodeTranslation(data);
        error.response.data = data;
      }
      if (data && data.statusCode === 401) {
        // 检查是否是logout接口，避免循环调用
        const requestUrl = error.config?.url || "";
        const isLogoutEndpoint = LOGOUT_ENDPOINTS.some((endpoint) =>
          requestUrl.includes(endpoint),
        );

        if (!isLogoutEndpoint) {
          // console.log('🔴 401 Unauthorized detected in error handler', data)

          // Unauthorized - 清除 token 和 auth store 状态
          const authStore = useAuthStore();
          authStore.logout(); // 这会清除 localStorage 和 store 中的状态

          // 检查当前是否在登录页面
          const currentPath = router.currentRoute.value.path;
          const isOnLoginPage =
            currentPath === "/login" || currentPath.includes("/login");

          // console.log('Current path:', currentPath, 'Is on login page:', isOnLoginPage)

          // 如果不在登录页面，才跳转到登录页面
          if (!isOnLoginPage) {
            // console.log('Redirecting to login page...')
            // 使用 Vue Router 进行跳转
            router.push("/login").catch(() => {
              // 忽略导航重复的错误
            });
          }
        }
      }
      // 返回响应数据，让业务代码处理
      return Promise.reject(data);
    }

    return Promise.reject(error);
  },
);

export default api;
