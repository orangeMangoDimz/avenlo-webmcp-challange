/**
 * PSP Callback Lookup API Service
 * 后端 GET /api/psp-callback-lookup?orderId=xxx&transactionType=deposit&recordId=1697
 * orderId 是 deposit / withdrawal 的 transactionId；recordId 支持回查旧 callback 日志。
 */

import api from "./api";

export const lookupPspCallback = (orderId, options = {}) => {
  const params = { orderId };
  const transactionType = String(options.transactionType || "").trim();
  const recordId = Number(options.recordId || 0);

  if (transactionType) {
    params.transactionType = transactionType;
  }
  if (recordId > 0) {
    params.recordId = recordId;
  }

  return api.get("/psp-callback-lookup", { params });
};

export default {
  lookupPspCallback,
};
