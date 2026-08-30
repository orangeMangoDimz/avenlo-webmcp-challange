/**
 * Commission Orders API
 */

import api from "./api";

const IB_COMMISSION_LOG_SUB_MODULE = "ib_commission";

const withIbCommissionLog = (data = {}) => ({
  ...data,
  logSubModuleKey: IB_COMMISSION_LOG_SUB_MODULE,
});

export const getCommissionOrderList = (params = {}) => {
  return api.get("/commission-orders", { params });
};

export const approveCommissionOrder = (id, extra = {}) => {
  return api.post(
    `/commission-orders/${id}/approve`,
    withIbCommissionLog(extra),
  );
};

export const completeCommissionOrder = (id, extra = {}) => {
  return api.post(
    `/commission-orders/${id}/complete`,
    withIbCommissionLog(extra),
  );
};

export const cancelCommissionOrder = (id, extra = {}) => {
  return api.post(
    `/commission-orders/${id}/cancel`,
    withIbCommissionLog(extra),
  );
};

export default {
  getCommissionOrderList,
  approveCommissionOrder,
  completeCommissionOrder,
  cancelCommissionOrder,
};
