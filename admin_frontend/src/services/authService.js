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
   * @param {string} data.phoneCountryCode - 电话国家代码（可选）
   * @param {string} data.department - 部门（可选）
   * @param {string} data.emailVerificationCode - 邮箱验证码（修改邮箱时必需）
   */
  async updateProfile(userId, data) {
    return await api.post(`/users/${userId}/profile`, {
      fullName: data.fullName,
      email: data.email,
      phone: data.phone,
      phoneCountryCode: data.phoneCountryCode,
      departmentId: data.departmentId,
      emailVerificationCode: data.emailVerificationCode,
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
    // 传递 useHashMode: true 表示使用 Hash 模式路由
    // 后端会根据此参数决定生成的链接是否包含 #
    return await api.post("/auth/forgot-password", {
      email: email,
      useHashMode: true, // 后台管理端使用 Hash 模式路由
    });
  },

  /**
   * 重置密码
   * @param {string} token - 重置令牌
   * @param {string} password - 新密码
   * @param {string} confirmPassword - 确认新密码
   */
  async resetPassword(token, password, confirmPassword) {
    return await api.post("/auth/reset-password", {
      token: token,
      password: password,
      confirmPassword: confirmPassword,
    });
  },

  /**
   * 刷新令牌
   */
  async refreshToken() {
    return await api.post("/auth/refresh");
  },

  /**
   * 发送邮箱验证码（用于修改邮箱）
   * @param {string} email - 新邮箱地址
   */
  async sendEmailVerificationCode(email) {
    try {
      const response = await api.post("/otp/send-email-code", { email });
      return { success: true, data: response.data };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message ||
          error.message ||
          "Failed to send verification code",
      };
    }
  },

  /**
   * 验证邮箱验证码
   * @param {string} email - 邮箱地址
   * @param {string} code - 验证码
   */
  async verifyEmailCode(email, code) {
    try {
      const response = await api.post("/otp/verify-email-code", {
        email,
        code,
      });
      return { success: true, data: response.data };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message ||
          error.message ||
          "Failed to verify code",
      };
    }
  },
};

export default authService;
