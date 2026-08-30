import api from "./api";

/**
 * 客户端交易服务
 * 客户端存款和提款API
 */
export const clientTransactionService = {
  /**
   * 获取支付方式列表
   * @param {string} type - 'deposit' or 'withdrawal'
   */
  async getPaymentMethods(type = null) {
    const params = type ? { type } : {};
    return await api.get("/payment-methods", { params });
  },

  /**
   * 创建存款
   * @param {Object} data - { paymentMethodId, amount, tradingAccountId, amountCrypto, fromAddress }
   */
  async createDeposit(data) {
    return await api.post("/deposits/create", data);
  },

  async createAlchemyPayDeposit(data) {
    return await api.post("/deposits/create/alchemypay", data);
  },

  /**
   * 取消存款
   * @param {number|string} id - 存款ID
   * @param {string} reason - 取消原因
   */
  async cancelDeposit(id, reason) {
    return await api.post(`/deposits/${id}/cancel`, { reason });
  },

  /**
   * 获取我的存款列表
   * @param {number} limit - 显示数量
   */
  async getMyDeposits(limit = 10) {
    return await api.get("/deposits/my-deposits", { params: { limit } });
  },

  /**
   * 创建提款
   * @param {Object} data - { paymentMethodId, amount, tradingAccountId, destinationAddress, destinationLabel, withdrawalReason }
   */
  async createWithdrawal(data) {
    return await api.post("/withdrawals/create", data);
  },

  async createWithdrawalSupportData(data) {
    return await api.post("/withdrawals/create/support-data", data);
  },

  /**
   * 取消提款
   * @param {number|string} id - 提款ID
   * @param {string} reason - 取消原因
   */
  async cancelWithdrawal(id, reason) {
    return await api.post(`/withdrawals/${id}/cancel`, { reason });
  },

  /**
   * 获取我的提款列表
   * @param {number} limit - 显示数量
   */
  async getMyWithdrawals(limit = 10) {
    return await api.get("/withdrawals/my-withdrawals", { params: { limit } });
  },

  /**
   * 获取保存的钱包列表
   * @param {number} paymentMethodId - 支付方式ID（可选）
   */
  async getSavedWallets(paymentMethodId = null) {
    const params = paymentMethodId ? { paymentMethodId } : {};
    return await api.get("/client-wallets", { params });
  },

  /**
   * 创建保存的钱包
   * @param {Object} data - { walletName, paymentMethodId, walletAddress, networkType, isDefault }
   */
  async createSavedWallet(data) {
    return await api.post("/client-wallets", data);
  },

  /**
   * 更新保存的钱包
   * @param {number} id - 钱包ID
   * @param {Object} data - { walletName, isDefault }
   */
  async updateSavedWallet(id, data) {
    return await api.put(`/client-wallets/${id}`, data);
  },

  /**
   * 删除保存的钱包
   * @param {number} id - 钱包ID
   */
  async deleteSavedWallet(id) {
    return await api.delete(`/client-wallets/${id}`);
  },

  /**
   * 设置为默认钱包
   * @param {number} id - 钱包ID
   */
  async setDefaultWallet(id) {
    return await api.post(`/client-wallets/${id}/set-default`);
  },

  /**
   * 获取加密货币存款地址
   */
  async getCryptoDepositAddresses() {
    return await api.get("/transaction-settings/crypto-addresses");
  },

  /**
   * 获取可用余额和存款限制
   * Wallet 公式：入金(completed) - 出金(pending/processing/completed) + 已完成佣金 - 内部转账(Wallet→交易账户)金额
   */
  async getAvailableBalance() {
    return await api.get("/deposits/available-balance");
  },

  /**
   * 获取客户端可用的支付网关
   */
  async getClientGateways() {
    return await api.get("/transaction-settings/client/gateways");
  },

  /**
   * 获取客户端可用的出金网关
   */
  async getClientWithdrawGateways() {
    return await api.get("/transaction-settings/client/withdraw/gateways");
  },

  /**
   * 获取网关支持的币种列表（包含paymentMethods的样式信息）
   * @param {string} gatewayKey - 网关key (如: alchemy_pay)
   */
  async getGatewayCryptos(gatewayKey) {
    return await api.get(
      `/transaction-settings/client/gateways/${gatewayKey}/cryptos`,
    );
  },

  /**
   * 获取出金模板下的已保存支付信息
   * @param {number|string} gatewaySettingId - 支付网关配置ID
   */
  async getWithdrawalTemplatePayments(gatewaySettingId) {
    return await api.get(`/withdrawal-templates/payments/${gatewaySettingId}`);
  },

  async getDepositTemplatePayments(gatewaySettingId) {
    return await api.get(
      `/transaction-settings/client/deposit/payments/${gatewaySettingId}`,
    );
  },

  async getClientExchangeRates(
    assetType,
    currency = "USD",
    transactionType = null,
  ) {
    return await api.get("/transaction-settings/client/exchange-rates", {
      params: {
        assetType,
        type: transactionType,
        currency,
      },
    });
  },

  /**
   * 创建内部转账
   * @param {Object} data - { fromType, fromTradingAccountId, toTradingAccountId, amount }
   */
  async createInternalTransfer(data) {
    return await api.post("/internal-transfers/create", data);
  },

  /**
   * 获取我的内部转账列表
   * @param {number} limit - 显示数量
   */
  async getMyInternalTransfers(limit = 10) {
    return await api.get("/internal-transfers/my-transfers", {
      params: { limit },
    });
  },

  /**
   * 取消内部转账
   * @param {number|string} id - 内部转账ID
   * @param {string} reason - 取消原因
   */
  async cancelInternalTransfer(id, reason) {
    return await api.post(`/internal-transfers/${id}/cancel`, { reason });
  },

  /**
   * 获取交易历史（混合查询 Deposit、Withdrawal 和 Internal Transfer）
   * @param {number} page - 页码
   * @param {number} per_page - 每页数量
   * @param {string} keywords - 关键字搜索（transactionId）
   * @param {string} type - 类型筛选（deposit, withdrawal, internal_transfer）
   * @param {string} periodFrom - 开始日期
   * @param {string} periodTo - 结束日期
   */
  async getTransactionHistory(
    page = 1,
    per_page = 20,
    keywords = null,
    type = null,
    periodFrom = null,
    periodTo = null,
  ) {
    const params = { page, per_page };
    if (keywords) params.keywords = keywords;
    if (type) params.type = type;
    if (periodFrom) params.periodFrom = periodFrom;
    if (periodTo) params.periodTo = periodTo;
    return await api.get("/trading/accounts/transaction-history", { params });
  },

  /**
   * 获取客户端交易展示内容
   * @param {string|null} scope - deposit | withdrawal | internal_transfer
   */
  async getClientDisplayContents(scope = null) {
    const params = scope ? { scope } : {};
    return await api.get("/transaction-settings/client/display-contents", {
      params,
    });
  },

  /**
   * 获取交易限制信息
   */
  async getTransactionLimits() {
    return await api.get("/transaction-settings/limits");
  },

  /**
   * 获取安全设置
   */
  async getSecuritySettings() {
    return await api.get("/transaction-settings/client/security-settings");
  },

  /**
   * 请求出金OTP验证码
   */
  async requestWithdrawalOTP() {
    return await api.post("/withdrawals/request-otp");
  },

  /**
   * 验证出金OTP验证码
   * @param {string} otpCode - OTP验证码
   */
  async verifyWithdrawalOTP(otpCode) {
    return await api.post("/withdrawals/verify-otp", { otpCode });
  },

  /**
   * 检查OTP验证状态
   */
  async checkOTPStatus() {
    return await api.get("/withdrawals/otp-status");
  },

  /**
   * 获取已验证的账户列表
   * @param {number} paymentMethodId - 支付方式ID
   */
  async getVerifiedAccounts(paymentMethodId) {
    return await api.get("/account-verification/verified-accounts", {
      params: { paymentMethodId },
    });
  },

  /**
   * 提交账户验证申请
   * @param {FormData} formData - 包含账户信息和文件的FormData对象
   * 注意：不要手动设置 Content-Type，axios 会自动处理 FormData 的 Content-Type 和 boundary
   */
  async submitAccountVerification(formData) {
    return await api.post("/account-verification/submit", formData);
  },
};

export default clientTransactionService;
