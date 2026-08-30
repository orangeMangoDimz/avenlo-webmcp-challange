/**
 * IB Commission Rules API Service
 * IB佣金规则相关API接口
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
 * 获取佣金规则列表
 * @param {Object} params - 查询参数
 */
export const getRules = (params = {}) => {
  return api.get("/ib-rules", { params });
};

/**
 * 获取所有激活的规则（用于选择器）
 */
export const getActiveRules = () => {
  return api.get("/ib-rules/active");
};

/**
 * 按 Tier Level 获取激活的规则
 * @param {number} tierLevelId - ibTierLevels.id
 */
export const getActiveRulesByTierLevel = (tierLevelId) => {
  return api.get("/ib-rules/active-by-tier", {
    params: { tier_level_id: tierLevelId },
  });
};

/**
 * 获取单个规则详情
 * @param {number} id - 规则ID
 */
export const getRule = (id) => {
  return api.get(`/ib-rules/${id}`);
};

/**
 * 创建佣金规则
 * @param {Object} data - 规则数据
 */
export const createRule = (data) => {
  return api.post("/ib-rules", withIbSettingsLog(data));
};

/**
 * 更新佣金规则
 * @param {number} id - 规则ID
 * @param {Object} data - 更新数据
 */
export const updateRule = (id, data) => {
  return api.put(`/ib-rules/${id}`, withIbSettingsLog(data));
};

/**
 * 删除佣金规则
 * @param {number} id - 规则ID
 */
export const deleteRule = (id) => {
  return api.delete(`/ib-rules/${id}`, { params: deleteLogParams() });
};

/**
 * 复制佣金规则
 * @param {number} id - 规则ID
 */
export const duplicateRule = (id) => {
  return api.post(`/ib-rules/${id}/duplicate`, withIbSettingsLog({}));
};

/**
 * 更新规则的产品配置
 * @param {number} id - 规则ID
 * @param {Array} products - 产品配置数组
 */
export const updateProducts = (id, products) => {
  return api.put(`/ib-rules/${id}/products`, withIbSettingsLog({ products }));
};

/**
 * 更新规则的额外规则配置
 * @param {number} id - 规则ID
 * @param {Array} additionalRules - 额外规则配置数组
 */
export const updateAdditionalRules = (id, additionalRules) => {
  return api.put(`/ib-rules/${id}/additional-rules`, { additionalRules });
};

/**
 * 更新佣金层级
 * @param {number} additionalRuleId - 额外规则ID
 * @param {Array} tiers - 层级数组
 */
export const updateTiers = (additionalRuleId, tiers) => {
  return api.put(`/ib-rules/additional-rules/${additionalRuleId}/tiers`, {
    tiers,
  });
};

export default {
  getRules,
  getActiveRules,
  getActiveRulesByTierLevel,
  getRule,
  createRule,
  updateRule,
  deleteRule,
  duplicateRule,
  updateProducts,
  updateAdditionalRules,
  updateTiers,
};
