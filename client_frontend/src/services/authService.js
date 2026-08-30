import api from "./api";

const authService = {
  /**
   * 用户登录
   * @param {Object} credentials - 登录凭证
   * @param {string} credentials.username - 用户名
   * @param {string} credentials.password - 密码
   * @param {boolean} credentials.rememberMe - 记住我
   */
  async login(credentials) {
    return await api.post("/auth/login", {
      username: credentials.username,
      password: credentials.password,
      rememberMe: credentials.rememberMe || false,
    });
  },

  /**
   * 用户登出
   */
  async logout() {
    return await api.post("/auth/logout");
  },

  /**
   * 获取当前用户信息
   */
  async getProfile() {
    return await api.get("/auth/me");
  },

  /**
   * 更新用户资料
   * @param {number} userId - 用户ID
   * @param {Object} data - 用户资料数据
   * @param {string} data.fullName - 全名
   * @param {string} data.email - 邮箱
   * @param {string} data.phone - 电话（可选）
   * @param {string} data.phoneCountryCode - 电话国家区号（可选）
   * @param {string} data.department - 部门（可选）
   */
  async updateProfile(userId, data) {
    return await api.put(`/users/${userId}/profile`, {
      fullName: data.fullName,
      email: data.email,
      phone: data.phone,
      phoneCountryCode: data.phoneCountryCode,
      department: data.department,
    });
  },

  /**
   * 修改密码
   * @param {Object} data - 密码数据
   * @param {string} data.currentPassword - 当前密码
   * @param {string} data.newPassword - 新密码
   * @param {string} data.confirmPassword - 确认新密码
   */
  async changePassword(data) {
    return await api.post("/auth/change-password", {
      currentPassword: data.currentPassword,
      newPassword: data.newPassword,
      confirmPassword: data.confirmPassword,
    });
  },

  /**
   * 忘记密码 - 请求重置
   * @param {string} email - 邮箱地址
   */
  async requestPasswordReset(email) {
    return await api.post("/auth/forgot-password", {
      email: email,
    });
  },

  /**
   * 重置密码
   * @param {string} token - 重置令牌
   * @param {string} newPassword - 新密码
   */
  async resetPassword(token, newPassword) {
    return await api.post("/auth/reset-password", {
      token: token,
      newPassword: newPassword,
    });
  },

  /**
   * 刷新令牌
   */
  async refreshToken() {
    return await api.post("/auth/refresh");
  },
};

export default authService;
