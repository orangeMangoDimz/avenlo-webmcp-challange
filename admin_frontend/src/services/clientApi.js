import axios from "axios";
import router from "@/router";

// logout接口白名单，避免在logout响应上触发401跳转循环
const CLIENT_LOGOUT_ENDPOINTS = ["/client/auth/logout", "client/auth/logout"];

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

  console.log("Client API baseURL (direct):", envUrl);
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

// Log the full configuration for debugging
// console.log('🔧 Client API Configuration:', {
//   baseURL: clientApiBaseURL,
//   envURL: import.meta.env.VITE_API_BASE_URL,
//   clientEnvURL: import.meta.env.VITE_CLIENT_API_BASE_URL
// })

// Request interceptor
clientApi.interceptors.request.use(
  (config) => {
    // 获取客户端 token
    const token = localStorage.getItem("clientToken");
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    // 调试日志
    // console.log('Client API Request:', {
    //   method: config.method.toUpperCase(),
    //   url: config.url,
    //   fullURL: config.baseURL + config.url,
    //   hasToken: !!token
    // })

    return config;
  },
  (error) => {
    return Promise.reject(error);
  },
);

// Response interceptor
clientApi.interceptors.response.use(
  (response) => {
    const data = response.data;

    // 检查响应体中的状态码（现在所有错误都返回200 HTTP状态码）
    // 如果响应中有statusCode字段，检查是否为401（需要登出）
    if (data && !data.success && data.statusCode === 401) {
      // 检查是否是logout接口，避免循环调用
      const requestUrl = response.config?.url || "";
      const isLogoutEndpoint = CLIENT_LOGOUT_ENDPOINTS.some((endpoint) =>
        requestUrl.includes(endpoint),
      );

      if (isLogoutEndpoint) {
        // logout接口返回401时，只清理token，不跳转（后端已修复为幂等，理论上不会到这里）
        localStorage.removeItem("clientToken");
        localStorage.removeItem("clientRememberMe");
        return Promise.reject(data);
      }

      console.log(
        "🔴 401 Unauthorized detected in clientApi response interceptor",
        data,
      );

      // Unauthorized - clear client token
      localStorage.removeItem("clientToken");
      localStorage.removeItem("clientRememberMe");

      // 检查当前是否在登录页面
      const currentPath = router.currentRoute.value.path;
      const isOnLoginPage =
        currentPath === "/client/login" ||
        currentPath.includes("/client/login");

      console.log(
        "Current path:",
        currentPath,
        "Is on login page:",
        isOnLoginPage,
      );

      // 如果不在登录页面，才跳转到登录页面
      // 如果已经在登录页面，不跳转，让业务代码显示错误信息
      if (!isOnLoginPage) {
        console.log("Redirecting to client login page...");
        // 使用 Vue Router 进行跳转
        router.push("/client/login");
        return Promise.reject(new Error(data.message || "Unauthorized"));
      }

      // 在登录页面时，返回错误数据，让业务代码处理并显示错误信息
      return Promise.reject(data);
    }

    // 如果业务逻辑错误（success: false），将其作为错误抛出
    if (data && !data.success) {
      return Promise.reject(data);
    }

    return data;
  },
  (error) => {
    // 处理网络错误或其他异常（不是业务错误）
    // 如果服务器返回了响应，检查响应体中的状态码
    if (error.response?.data) {
      const data = error.response.data;
      if (data && data.statusCode === 401) {
        console.log(
          "🔴 401 Unauthorized detected in clientApi error handler",
          data,
        );

        // Unauthorized - clear client token
        localStorage.removeItem("clientToken");
        localStorage.removeItem("clientRememberMe");

        // 检查当前是否在登录页面
        const currentPath = router.currentRoute.value.path;
        const isOnLoginPage =
          currentPath === "/client/login" ||
          currentPath.includes("/client/login");

        console.log(
          "Current path:",
          currentPath,
          "Is on login page:",
          isOnLoginPage,
        );

        // 如果不在登录页面，才跳转到登录页面
        if (!isOnLoginPage) {
          console.log("Redirecting to client login page...");
          // 使用 Vue Router 进行跳转
          router.push("/client/login");
        }
      }
      // 返回响应数据，让业务代码处理
      return Promise.reject(data);
    }

    return Promise.reject(error);
  },
);

export default clientApi;
