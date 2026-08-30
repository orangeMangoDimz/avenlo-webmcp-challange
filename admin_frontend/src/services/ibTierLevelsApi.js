/**
 * IB Tier Levels API Service
 * IB层级模板相关API接口
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
 * 获取所有层级列表
 * @param {boolean} withStats - 是否包含统计数据
 */
export const getTierLevels = (withStats = false) => {
  const params = withStats ? { with_stats: true } : {};
  return api.get("/ib-tier-levels", { params });
};

/**
 * 获取所有激活的层级（用于选择器）
 */
export const getActiveTiers = () => {
  return api.get("/ib-tier-levels/active");
};

/**
 * 获取单个层级详情
 * @param {number} id - 层级ID
 */
export const getTierLevel = (id) => {
  return api.get(`/ib-tier-levels/${id}`);
};

/**
 * 创建层级
 * @param {Object} data - 层级数据
 */
export const createTierLevel = (data) => {
  return api.post("/ib-tier-levels", withIbSettingsLog(data));
};

/**
 * 更新层级
 * @param {number} id - 层级ID
 * @param {Object} data - 更新数据
 */
export const updateTierLevel = (id, data) => {
  return api.put(`/ib-tier-levels/${id}`, withIbSettingsLog(data));
};

/**
 * 删除层级
 * @param {number} id - 层级ID
 */
export const deleteTierLevel = (id) => {
  return api.delete(`/ib-tier-levels/${id}`, { params: deleteLogParams() });
};

export default {
  getTierLevels,
  getActiveTiers,
  getTierLevel,
  createTierLevel,
  updateTierLevel,
  deleteTierLevel,
};
