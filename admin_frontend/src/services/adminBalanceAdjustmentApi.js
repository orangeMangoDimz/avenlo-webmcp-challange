/**
 * Admin Balance Adjustment API
 * Admin 手工对用户 wallet 打钱 / 扣钱
 */

import api from "./api";

/** 创建一次打钱 / 扣钱操作 */
export const createBalanceAdjustment = (payload) => {
  return api.post("/admin/balance-adjustments", payload);
};

/** 获取某用户当前 wallet 可用余额（弹窗预览用） */
export const getWalletBalance = (userId) => {
  return api.get(`/admin/balance-adjustments/wallet-balance/${userId}`);
};

/** Admin 手工调整历史列表 */
export const listBalanceAdjustments = (params = {}) => {
  return api.get("/admin/balance-adjustments", { params });
};

export default {
  createBalanceAdjustment,
  getWalletBalance,
  listBalanceAdjustments,
};
