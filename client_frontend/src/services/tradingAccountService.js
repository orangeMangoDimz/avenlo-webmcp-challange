import api from "./api";

/**
 * 交易账户服务
 */
const tradingAccountService = {
  /**
   * 获取启用的平台及杠杆配置
   */
  async getPlatforms() {
    return await api.get("/trading/platforms");
  },

  /**
   * 获取平台下载/打开配置
   */
  async getPlatformDownloads() {
    return await api.get("/trading/platform-downloads");
  },

  /**
   * 获取当前用户的交易账户
   */
  async getAccounts() {
    return await api.get("/trading/accounts");
  },

  /**
   * 创建新的交易账户
   * @param {Object} payload
   */
  async createAccount(payload) {
    return await api.post("/trading/accounts/create", payload);
  },

  /**
   * 修改交易账户杠杆
   * @param {Object} payload { tradingAccountId, leverageValue }
   */
  async changeLeverage(payload) {
    return await api.post("/trading/accounts/leverage", payload);
  },

  /**
   * 发送邮箱验证码到本人邮箱（改交易密码用，复用通用 OTP）
   * @param {string} email
   * @param {string} purpose 邮件文案用途，默认 trading_password
   */
  async sendEmailCode(email, purpose = "trading_password") {
    return await api.post("/otp/send-email-code", { email, purpose });
  },

  /**
   * 修改交易密码（需邮箱验证码）
   * @param {Object} payload { tradingAccountId, code, newPassword }
   */
  async changeTradingPassword(payload) {
    return await api.post("/trading/accounts/password", payload);
  },

  /**
   * 修改账户在 CRM 上显示的名字
   * @param {Object} payload { tradingAccountId, name }
   */
  async changeAccountName(payload) {
    return await api.post("/trading/accounts/name", payload);
  },

  /**
   * 获取账户总览信息
   */
  async getAccountSummary() {
    return await api.get("/trading/accounts/summary");
  },

  /**
   * 获取历史订单列表
   * @param {Object} params 查询参数
   */
  async getOrderHistory(params = {}) {
    return await api.get("/trading/accounts/history", { params });
  },

  /**
   * 获取持仓列表（开单持仓订单）
   * @param {Object} params 查询参数（page, pageSize, sort, sortOrder, keywords, cmd）
   */
  async getOpenPositions(params = {}) {
    return await api.get("/trading/accounts/positions", { params });
  },

  /**
   * 获取挂单列表（未开单订单）
   * @param {Object} params 查询参数（page, pageSize, sort, sortOrder, keywords, cmd）
   */
  async getPendingOrders(params = {}) {
    return await api.get("/trading/accounts/pending", { params });
  },
};

export default tradingAccountService;
