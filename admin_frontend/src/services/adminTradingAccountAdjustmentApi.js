/**
 * Admin Trading Account Adjustment API
 * Admin 手工对客户【交易账户】调整：
 *   - credit：直接打平台 credits（靠同步拉回）
 *   - balance：走 deposit/withdrawal 审批流打钱/扣钱
 */

import api from "./api";

/** 给交易账户打/扣 credits（直接打平台） */
export const createTradingAccountCredit = (payload) => {
  return api.post("/admin/trading-account-adjustments/credit", payload);
};

/** 给交易账户打钱/扣钱（走 deposit/withdrawal 流程） */
export const createTradingAccountBalance = (payload) => {
  return api.post("/admin/trading-account-adjustments/balance", payload);
};

/** 重置交易账户密码（自动生成 + 发客户邮箱，admin 不回显密码） */
export const resetTradingPassword = (payload) => {
  return api.post("/admin/trading-account-adjustments/reset-password", payload);
};

/** 修改交易账户分组 */
export const changeTradingGroup = (payload) => {
  return api.post("/admin/trading-account-adjustments/group", payload);
};

/** 修改交易账户杠杆 */
export const changeTradingLeverage = (payload) => {
  return api.post("/admin/trading-account-adjustments/leverage", payload);
};

/** 拿该账户平台的 group / leverage 选项（改组/改杠杆弹窗用） */
export const getTradingAccountOptions = (tradingAccountId) => {
  return api.get("/admin/trading-account-adjustments/options", {
    params: { tradingAccountId },
  });
};

export default {
  createTradingAccountCredit,
  createTradingAccountBalance,
  resetTradingPassword,
  changeTradingGroup,
  changeTradingLeverage,
  getTradingAccountOptions,
};
