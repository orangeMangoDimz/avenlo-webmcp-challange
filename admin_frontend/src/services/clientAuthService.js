import clientApi from "./clientApi";

// 客户端认证服务
// 注意：client-auth.php 使用查询参数 ?path= 而不是路径方式
const clientAuthService = {
  // 客户注册
  register(data) {
    return clientApi.post("/api/client-auth.php?path=register", data);
  },

  // 客户登录
  login(credentials) {
    return clientApi.post("/api/client-auth.php?path=login", credentials);
  },

  // 客户登出
  logout() {
    return clientApi.post("/api/client-auth.php?path=logout");
  },

  // 获取当前用户信息
  getProfile() {
    return clientApi.get("/api/client-auth.php?path=me");
  },

  // 验证邮箱
  verifyEmail(token) {
    return clientApi.post("/api/client-auth.php?path=verify-email", { token });
  },

  // 重新发送验证邮件
  resendVerification(email) {
    return clientApi.post("/api/client-auth.php?path=resend-verification", {
      email,
    });
  },

  // 忘记密码
  forgotPassword(email, options = {}) {
    return clientApi.post("/api/client-auth.php?path=forgot-password", {
      email,
      useHashMode: true,
      ...options,
    });
  },

  // 重置密码
  resetPassword(data) {
    return clientApi.post("/api/client-auth.php?path=reset-password", data);
  },

  // 修改密码
  changePassword(data) {
    return clientApi.post("/api/client-auth.php?path=change-password", data);
  },

  // 获取客户端文档
  getMyDocuments() {
    return clientApi.get("/api/client-auth.php?path=my-documents");
  },
};

export default clientAuthService;
