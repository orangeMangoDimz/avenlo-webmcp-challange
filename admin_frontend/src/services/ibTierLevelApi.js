/**
 * IB Tier Level API Service
 * IB层级管理相关API接口
 */

import api from "./api";

/**
 * 获取所有激活的层级
 */
export const getActiveTierLevels = () => {
  return api.get("/ib-tier-levels/active");
};

/**
 * 获取所有层级（带统计）
 * @param {boolean} withStats - 是否包含统计信息
 */
export const getTierLevels = (withStats = false) => {
  const params = withStats ? { with_stats: true } : {};
  return api.get("/ib-tier-levels", { params });
};

/**
 * 获取单个层级
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
  return api.post("/ib-tier-levels", data);
};

/**
 * 更新层级
 * @param {number} id - 层级ID
 * @param {Object} data - 层级数据
 */
export const updateTierLevel = (id, data) => {
  return api.put(`/ib-tier-levels/${id}`, data);
};

/**
 * 删除层级
 * @param {number} id - 层级ID
 */
export const deleteTierLevel = (id) => {
  return api.delete(`/ib-tier-levels/${id}`);
};

export default {
  getActiveTierLevels,
  getTierLevels,
  getTierLevel,
  createTierLevel,
  updateTierLevel,
  deleteTierLevel,
};
