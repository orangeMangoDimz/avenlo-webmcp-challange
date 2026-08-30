/**
 * Transaction Settings API Service
 * 交易设置相关API接口
 */

import api from "./api";
import { getSubModuleKey } from "@/config/operationLogPages";

const TRANSACTION_SETTINGS_LOG_SUB_MODULE = getSubModuleKey(
  "page_transactionsettings",
);

const withLogSubModule = (data = {}) => ({
  logSubModuleKey: TRANSACTION_SETTINGS_LOG_SUB_MODULE,
  ...data,
});

const deleteLogParams = () =>
  TRANSACTION_SETTINGS_LOG_SUB_MODULE
    ? { logSubModuleKey: TRANSACTION_SETTINGS_LOG_SUB_MODULE }
    : {};

/**
 * 获取所有交易设置
 */
export const getAllSettings = () => {
  return api.get("/transaction-settings");
};

/**
 * 获取加密货币地址配置
 * @param {string} gateway - 网关key (如: alchemy_pay)，可选
 */
export const getCryptoAddresses = (gateway = null) => {
  const params = {};
  if (gateway) {
    params.gateway = gateway;
  }
  return api.get("/transaction-settings/crypto-addresses", { params });
};

/**
 * 更新加密货币地址
 * @param {number} paymentMethodId - 支付方式ID
 * @param {Object} data - 地址数据
 */
export const updateCryptoAddress = (paymentMethodId, data) => {
  return api.put(
    `/transaction-settings/crypto-addresses/${paymentMethodId}`,
    data,
  );
};

/**
 * 获取网关配置
 */
export const getGateways = () => {
  return api.get("/transaction-settings/gateways");
};

/**
 * 更新网关配置
 * @param {string} gatewayKey - 网关key (如: alchemy_pay)
 * @param {Object} data - 网关配置数据
 */
export const updateGateway = (gatewayKey, data) => {
  return api.put(
    `/transaction-settings/gateways-modify/${gatewayKey}`,
    withLogSubModule(data),
  );
};

/**
 * 删除网关配置
 * @param {string} gatewayKey - 网关key (如: alchemy_pay)
 */
export const deleteGateway = (gatewayKey) => {
  return api.delete(`/transaction-settings/gateways/${gatewayKey}`, {
    params: deleteLogParams(),
  });
};

export const updateGatewayLimitSettings = (gatewaySettingId, data) => {
  return api.put(
    `/transaction-settings/gateway-fees-modify/${gatewaySettingId}/limit`,
    withLogSubModule(data),
  );
};

export const updateGatewayDepositFeeSettings = (gatewaySettingId, data) => {
  return api.put(
    `/transaction-settings/gateway-fees-modify/${gatewaySettingId}/deposit-fee`,
    withLogSubModule(data),
  );
};

export const updateGatewayWithdrawFeeSettings = (gatewaySettingId, data) => {
  return api.put(
    `/transaction-settings/gateway-fees-modify/${gatewaySettingId}/withdraw-fee`,
    withLogSubModule(data),
  );
};

export const addGatewayQuickAmount = (gatewaySettingId, data) => {
  return api.post(
    `/transaction-settings/gateway-fees-modify/${gatewaySettingId}/quick-amounts`,
    withLogSubModule(data),
  );
};

export const deleteGatewayQuickAmount = (gatewaySettingId, quickAmountId) => {
  return api.delete(
    `/transaction-settings/gateway-fees-modify/${gatewaySettingId}/quick-amounts/${quickAmountId}`,
    { params: deleteLogParams() },
  );
};

/**
 * 获取网关资金配置
 * @param {number|string} gatewaySettingId - 网关设置ID
 */
export const getGatewayFees = (gatewaySettingId) => {
  return api.get("/transaction-settings/gateway-fees", {
    params: {
      gateway_setting_id: gatewaySettingId,
    },
  });
};

export const getGatewayFundingSettings = getGatewayFees;

/**
 * 获取网关展示内容
 * @param {number|string} gatewaySettingId - 网关设置ID
 */
export const getGatewayDisplayContent = (gatewaySettingId) => {
  return api.get(
    `/transaction-settings/gateway-display-content/${gatewaySettingId}`,
  );
};

/**
 * 获取支付支持问题
 * @param {number|string} gatewaySettingId - 网关设置ID
 */
export const getGatewaySupportQuestions = (gatewaySettingId) => {
  return api.get("/transaction-settings/payment-support-questions", {
    params: {
      gateway_setting_id: gatewaySettingId,
    },
  });
};

export const createGatewaySupportQuestion = (data) => {
  return api.post(
    "/transaction-settings/payment-support-questions",
    withLogSubModule(data),
  );
};

export const updateGatewaySupportQuestion = (id, data) => {
  return api.put(
    `/transaction-settings/payment-support-questions/${id}`,
    withLogSubModule(data),
  );
};

export const toggleGatewaySupportQuestionOption = (id, data) => {
  return api.post(
    `/transaction-settings/payment-support-questions/${id}/options/toggle`,
    withLogSubModule(data),
  );
};

export const deleteGatewaySupportQuestion = (id) => {
  return api.delete(`/transaction-settings/payment-support-questions/${id}`, {
    params: deleteLogParams(),
  });
};

/**
 * 更新网关展示内容
 * @param {number|string} gatewaySettingId - 网关设置ID
 * @param {Object} data - 展示内容数据
 */
export const updateGatewayDisplayContent = (gatewaySettingId, data) => {
  return api.put(
    `/transaction-settings/gateway-display-content/${gatewaySettingId}`,
    withLogSubModule(data),
  );
};

/**
 * 获取交易展示内容配置
 * @param {string|null} scope - 配置范围 (internal_transfer/deposit)
 */
export const getDisplayContents = (scope = null) => {
  if (scope) {
    return api.get(`/transaction-settings/display-contents/${scope}`);
  }
  return api.get("/transaction-settings/display-contents");
};

/**
 * 更新交易展示内容配置
 * @param {string} scope - 配置范围 (internal_transfer/deposit)
 * @param {Object} data - 配置数据 { contentJson, title, content, isActive }
 */
export const updateDisplayContent = (scope, data) => {
  return api.put(
    `/transaction-settings/display-contents/${scope}`,
    withLogSubModule(data),
  );
};

/**
 * 获取交易限额
 * @param {string} type - 交易类型 (deposit/withdrawal)
 * @param {string} paymentType - 支付类型 (crypto/fiat/all)
 */
export const getLimits = (type = null, paymentType = null) => {
  const params = {};
  if (type) params.type = type;
  if (paymentType) params.payment_type = paymentType;

  return api.get("/transaction-settings/limits", { params });
};

/**
 * 更新交易限额
 * @param {number} id - 限额配置ID
 * @param {Object} data - 限额数据
 */
export const updateLimit = (id, data) => {
  return api.put(`/transaction-settings/limits-modify/${id}`, data);
};

/**
 * 获取通知设置
 * @param {string} category - 分类 (client/admin/alerts)
 */
export const getNotifications = (category = null) => {
  const params = {};
  if (category) params.category = category;

  return api.get("/transaction-settings/notifications", { params });
};

/**
 * 更新通知设置
 * @param {string} settingKey - 设置键
 * @param {any} settingValue - 设置值
 */
export const updateNotification = (settingKey, settingValue) => {
  return api.put(
    "/transaction-settings/notifications-modify",
    withLogSubModule({
      settingKey,
      settingValue,
    }),
  );
};

/**
 * 获取支付方式列表
 */
export const getPaymentMethods = () => {
  return api.get("/payment-methods");
};

/**
 * 更新支付方式开关
 * @param {number|string} id
 * @param {Object} data
 */
export const updatePaymentMethod = (id, data) => {
  return api.put(`/payment-methods/${id}`, withLogSubModule(data));
};

/**
 * 获取国家列表
 */
export const getCountries = () => {
  return api.get("/transaction-settings/countries");
};

/**
 * 获取国家列表（从 countryList 表，专供 Transaction Settings 入金自动审核国家多选）
 * GET /api/transaction-settings/countries/list-from-country-list
 */
export const getCountriesForAutoApproval = () => {
  return api.get("/transaction-settings/countries/list-from-country-list");
};

/**
 * 获取客户端界面文字配置
 */
export const getClientTexts = () => {
  return api.get("/transaction-settings/client-texts");
};

/**
 * 更新客户端界面文字配置
 * @param {Object} data - 文字配置数据 { deposit: {...}, withdrawal: {...} }
 */
export const updateClientTexts = (data) => {
  return api.put("/transaction-settings/client-texts", data);
};

/**
 * 获取自动审批规则
 */
export const getAutoApprovalRules = () => {
  return api.get("/transaction-settings/auto-approval-rules");
};

/**
 * 更新自动审批规则
 * @param {Object} data - 规则数据 { deposit: {...}, withdrawal: {...} }
 */
export const updateAutoApprovalRules = (data) => {
  return api.put(
    "/transaction-settings/auto-approval-rules",
    withLogSubModule(data),
  );
};

/**
 * 检查交易是否满足自动审批条件
 * @param {string} type - 交易类型 (deposit/withdrawal)
 * @param {number} transactionId - 交易ID
 */
export const checkAutoApproval = (type, transactionId) => {
  return api.post("/transaction-settings/check-auto-approval", {
    type,
    transactionId,
  });
};

/**
 * 获取安全设置
 */
export const getSecuritySettings = () => {
  return api.get("/transaction-settings/security-settings");
};

/**
 * 更新安全设置
 * @param {Object} data - 安全设置数据
 */
export const updateSecuritySettings = (data) => {
  return api.put(
    "/transaction-settings/security-settings",
    withLogSubModule(data),
  );
};

/**
 * 获取账户验证申请列表（管理员）
 * @param {Object} params - 查询参数 { page, per_page, status, accountType }
 */
export const getVerificationList = (params = {}) => {
  return api.get("/account-verification", { params });
};

/**
 * 获取账户验证详情（管理员）
 * @param {number} id - 验证ID
 */
export const getVerificationDetails = (id) => {
  return api.get(`/account-verification/${id}`);
};

/**
 * 审核账户验证申请（管理员）
 * @param {number} id - 验证ID
 * @param {Object} data - { action: 'approve' | 'reject', reviewNotes, rejectionReason }
 */
export const reviewVerification = (id, data) => {
  return api.post(`/account-verification/${id}/review`, data);
};

/**
 * 下载验证文件（管理员）
 * @param {number} fileId - 文件ID
 */
export const downloadVerificationFile = (fileId) => {
  return api.get(`/account-verification/files/${fileId}`, {
    responseType: "blob",
  });
};

/**
 * 获取账户验证统计（管理员）
 */
export const getVerificationStatistics = () => {
  return api.get("/account-verification/statistics");
};

/**
 * 获取汇率设置列表
 * @param {boolean} includeInactive - 是否包含禁用的汇率
 */
export const getExchangeRates = (includeInactive = false) => {
  const params = {};
  if (includeInactive) {
    params.includeInactive = "true";
  }
  return api.get("/transaction-settings/exchange-rates", { params });
};

/**
 * 创建汇率
 * @param {Object} data - 汇率数据 { currencyCode, currencyName, exchangeRate, isActive }
 */
export const createExchangeRate = (data) => {
  return api.post(
    "/transaction-settings/exchange-rates",
    withLogSubModule(data),
  );
};

/**
 * 更新汇率
 * @param {number} id - 汇率ID
 * @param {Object} data - 汇率数据
 */
export const updateExchangeRate = (id, data) => {
  return api.put(
    `/transaction-settings/exchange-rates/${id}`,
    withLogSubModule(data),
  );
};

/**
 * 删除汇率
 * @param {number} id - 汇率ID
 */
export const deleteExchangeRate = (id) => {
  return api.delete(`/transaction-settings/exchange-rates/${id}`, {
    params: deleteLogParams(),
  });
};

/**
 * 切换汇率启用状态
 * @param {number} id - 汇率ID
 */
export const toggleExchangeRate = (id) => {
  return api.post(
    `/transaction-settings/exchange-rates/${id}/toggle`,
    withLogSubModule({}),
  );
};

/**
 * 切换汇率的 auto / manual 同步模式
 * @param {number} id - 汇率ID
 */
export const toggleExchangeRateSyncMode = (id) => {
  return api.post(
    `/transaction-settings/exchange-rates/${id}/sync-mode`,
    withLogSubModule({}),
  );
};

export default {
  getAllSettings,
  getCryptoAddresses,
  updateCryptoAddress,
  getGateways,
  updateGateway,
  deleteGateway,
  getGatewayFundingSettings,
  getGatewayDisplayContent,
  getGatewaySupportQuestions,
  createGatewaySupportQuestion,
  updateGatewaySupportQuestion,
  toggleGatewaySupportQuestionOption,
  deleteGatewaySupportQuestion,
  updateGatewayDisplayContent,
  getGatewayFees,
  updateGatewayLimitSettings,
  updateGatewayDepositFeeSettings,
  updateGatewayWithdrawFeeSettings,
  addGatewayQuickAmount,
  deleteGatewayQuickAmount,
  getDisplayContents,
  updateDisplayContent,
  getLimits,
  updateLimit,
  getNotifications,
  updateNotification,
  getPaymentMethods,
  updatePaymentMethod,
  getCountries,
  getCountriesForAutoApproval,
  getClientTexts,
  updateClientTexts,
  getAutoApprovalRules,
  updateAutoApprovalRules,
  checkAutoApproval,
  getSecuritySettings,
  updateSecuritySettings,
  getVerificationList,
  getVerificationDetails,
  reviewVerification,
  downloadVerificationFile,
  getVerificationStatistics,
  getExchangeRates,
  createExchangeRate,
  updateExchangeRate,
  deleteExchangeRate,
  toggleExchangeRate,
  toggleExchangeRateSyncMode,
};
