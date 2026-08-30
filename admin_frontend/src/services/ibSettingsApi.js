/**
 * IB Settings API Service
 * IB设置相关API接口
 */

import api from "./api";
import { getSubModuleKey } from "@/config/operationLogPages";

const IB_SETTINGS_LOG_SUB_MODULE = getSubModuleKey("page_ib_settings");

const withIbSettingsLog = (data = {}) => ({
  logSubModuleKey: IB_SETTINGS_LOG_SUB_MODULE,
  ...data,
});

const deleteLogParams = () =>
  IB_SETTINGS_LOG_SUB_MODULE
    ? { logSubModuleKey: IB_SETTINGS_LOG_SUB_MODULE }
    : {};

/**
 * 获取所有IB设置
 * @param {string} group - 设置组 (general, display, commission, documents)
 */
export const getAllSettings = (group = null) => {
  const params = group ? { group } : {};
  return api.get("/ib-settings", { params });
};

/**
 * 获取单个设置
 * @param {string} key - 设置键
 */
export const getSetting = (key) => {
  return api.get(`/ib-settings/${key}`);
};

/**
 * 更新单个设置
 * @param {string} key - 设置键
 * @param {any} value - 设置值
 */
export const updateSetting = (key, value) => {
  return api.put(`/ib-settings/${key}`, { settingValue: value });
};

/**
 * 批量更新设置
 * @param {Object} settings - 设置键值对对象
 */
export const bulkUpdateSettings = (settings) => {
  return api.post("/ib-settings/bulk-update", { settings });
};

/**
 * 获取所有文档模板
 * @param {boolean} activeOnly - 仅获取激活的文档
 */
export const getDocuments = (activeOnly = false) => {
  const params = activeOnly ? { active_only: true } : {};
  return api.get("/ib-settings/documents", { params });
};

/**
 * 获取单个文档模板
 * @param {number} id - 文档ID
 */
export const getDocument = (id) => {
  return api.get(`/ib-settings/documents/${id}`);
};

/**
 * 创建文档模板
 * @param {Object} data - 文档数据
 */
export const createDocument = (data) => {
  return api.post("/ib-settings/documents", withIbSettingsLog(data));
};

/**
 * 更新文档模板
 * @param {number} id - 文档ID
 * @param {Object} data - 文档数据
 */
export const updateDocument = (id, data) => {
  return api.put(`/ib-settings/documents/${id}`, withIbSettingsLog(data));
};

/**
 * 删除文档模板
 * @param {number} id - 文档ID
 */
export const deleteDocument = (id) => {
  return api.delete(`/ib-settings/documents/${id}`, {
    params: deleteLogParams(),
  });
};

/**
 * 复制文档模板
 * @param {number} id - 文档ID
 */
export const duplicateDocument = (id) => {
  return api.post(
    `/ib-settings/documents/${id}/duplicate`,
    withIbSettingsLog({}),
  );
};

/**
 * 获取自定义证券列表
 */
export const getCustomSecurities = () => {
  return api.get("/ib-settings/custom-securities");
};

/**
 * 按平台获取自定义证券列表
 * @param {string} tradingPlatformsKey - tradingPlatforms.platformKey（必填）
 */
export const getCustomSecuritiesByPlatform = (tradingPlatformsKey) => {
  return api.get("/ib-settings/custom-securitiesbyplatform", {
    params: { trading_platforms_key: tradingPlatformsKey },
  });
};

/**
 * 创建自定义证券
 * @param {Object} data - 证券数据
 */
export const createCustomSecurity = (data) => {
  return api.post("/ib-settings/custom-securities", data);
};

/**
 * 获取自定义交易对列表
 */
export const getCustomSymbols = () => {
  return api.get("/ib-settings/custom-symbols");
};

/**
 * 按平台获取自定义交易对列表
 * @param {string} tradingPlatformsKey - tradingPlatforms.platformKey（必填）
 */
export const getCustomSymbolsByPlatform = (tradingPlatformsKey) => {
  return api.get("/ib-settings/custom-symbolsbyplatform", {
    params: { trading_platforms_key: tradingPlatformsKey },
  });
};

/**
 * 创建自定义交易对
 * @param {Object} data - 交易对数据
 */
export const createCustomSymbol = (data) => {
  return api.post("/ib-settings/custom-symbols", data);
};

/**
 * 按平台同步 Securities & Symbols
 * @param {string} platformKey - 'mt4' | 'mt5' | 'financepro'
 * @returns {Promise<{ success: boolean, data?: object, message?: string }>}
 */
export const syncProducts = (platformKey) => {
  return api.post(
    "/ib-settings/sync-products",
    withIbSettingsLog({ platformKey }),
  );
};

/**
 * 查询同步进度
 * @param {string} platformKey - 'mt5'
 */
export const getSyncProgress = (platformKey) => {
  return api.get("/ib-settings/sync-progress", { params: { platformKey } });
};

/**
 * IB 品种汇率列表
 * @param {Object} params - { search, syncMode, page, perPage }
 */
export const getSymbolExchangeRates = (params = {}) => {
  return api.get("/ib-settings/symbol-exchange-rates", { params });
};

/**
 * 创建品种汇率
 */
export const createSymbolExchangeRate = (data) => {
  return api.post(
    "/ib-settings/symbol-exchange-rates",
    withIbSettingsLog(data),
  );
};

/**
 * 更新品种汇率
 */
export const updateSymbolExchangeRate = (id, data) => {
  return api.post(
    `/ib-settings/symbol-exchange-rates/${id}`,
    withIbSettingsLog(data),
  );
};

/**
 * 删除品种汇率
 */
export const deleteSymbolExchangeRate = (id) => {
  return api.delete(`/ib-settings/symbol-exchange-rates/${id}`, {
    params: deleteLogParams(),
  });
};

/**
 * 切换页级汇率同步模式（auto | manual）
 */
export const setSymbolExchangeGlobalMode = (mode) => {
  return api.post(
    "/ib-settings/symbol-exchange-rates/global-mode",
    withIbSettingsLog({ mode }),
  );
};

export default {
  getAllSettings,
  getSetting,
  updateSetting,
  bulkUpdateSettings,
  getDocuments,
  getDocument,
  createDocument,
  updateDocument,
  deleteDocument,
  duplicateDocument,
  getCustomSecurities,
  getCustomSecuritiesByPlatform,
  createCustomSecurity,
  getCustomSymbols,
  getCustomSymbolsByPlatform,
  createCustomSymbol,
  syncProducts,
  getSyncProgress,
  getSymbolExchangeRates,
  createSymbolExchangeRate,
  updateSymbolExchangeRate,
  deleteSymbolExchangeRate,
  setSymbolExchangeGlobalMode,
};
