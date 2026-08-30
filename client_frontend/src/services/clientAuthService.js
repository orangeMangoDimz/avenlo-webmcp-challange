import clientApi from "./clientApi";

// 客户端认证服务：统一走 index.php?path=api/client/auth/xxx，与 kyc-settings/client-notice 一致，
// 保证 CorsMiddleware 与请求头（含 X-Preview-Token）在同一入口下处理，预览模式可正常识别
// /api/client-auth.php?path=
const clientAuthPath = (action) => `index.php?path=api/client/auth/${action}`;

const clientAuthService = {
  // 客户注册
  register(data) {
    return clientApi.post(clientAuthPath("register"), data);
  },

  /** 记录 Sales 推荐链接访问（中间页调用，按 IP 限频） */
  salesReferralVisit(ref) {
    return clientApi.post("index.php?path=api/sales/sales-referral-visit", {
      ref,
    });
  },

  /** 记录 IB 推荐链接访问（中间页调用，按 IP 限频） */
  ibReferralVisit(ref) {
    return clientApi.post("index.php?path=api/ib-referral-visit", { ref });
  },

  // 客户登录
  login(credentials) {
    return clientApi.post(clientAuthPath("login"), credentials);
  },

  // App→WebView handoff：用 App 端签发的一次性 code 换 client JWT
  exchangeHandoff(code) {
    return clientApi.post(clientAuthPath("exchange-handoff"), { code });
  },

  // 客户登出
  logout() {
    return clientApi.post(clientAuthPath("logout"));
  },

  // 获取当前用户信息
  getProfile() {
    return clientApi.get(clientAuthPath("me"));
  },

  // 验证邮箱
  verifyEmail(token) {
    return clientApi.post(clientAuthPath("verify-email"), { token });
  },

  // 重新发送验证邮件
  resendVerification(email) {
    return clientApi.post(clientAuthPath("resend-verification"), { email });
  },

  // 忘记密码
  forgotPassword(email) {
    // 传递 useHashMode: true 表示使用 Hash 模式路由
    // 后端会根据此参数决定生成的链接是否包含 #
    return clientApi.post(clientAuthPath("forgot-password"), {
      email,
      useHashMode: true, // 客户端使用 Hash 模式路由
    });
  },

  // 重置密码
  resetPassword(data) {
    return clientApi.post(clientAuthPath("reset-password"), data);
  },

  // 修改密码
  changePassword(data) {
    return clientApi.post(clientAuthPath("change-password"), data);
  },

  // 更新个人资料
  updateProfile(data) {
    return clientApi.put(clientAuthPath("update-profile"), data);
  },

  // 获取客户端文档
  getMyDocuments() {
    return clientApi.get(clientAuthPath("my-documents"));
  },
};

export default clientAuthService;
