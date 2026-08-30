/**
 * Funding Report API Service
 * 资金报告相关API接口
 */

import api from "./api";

/**
 * 获取资金统计数据
 * @param {Object} params - { startDate, endDate }
 */
export const getStatistics = (params = {}) => {
  return api.get("/funding-reports/statistics", { params });
};

/**
 * 获取所有交易列表（存款+提款）
 * @param {Object} params - { page, per_page, startDate, endDate, type }
 */
export const getAllTransactions = (params = {}) => {
  return api.get("/funding-reports/transactions", { params });
};

/**
 * 导出资金报告（选中行异步任务）
 * @param {Object} data - { items: [{ type, id }] }
 */
export const exportReport = (data) => {
  return api.post("/funding-reports/export", data);
};

export const getActiveExport = () => {
  return api.get("/funding-reports/export-active");
};

export const getExportStatus = (jobId) => {
  return api.get("/funding-reports/export-status", { params: { jobId } });
};

export const cancelExport = (jobId) => {
  return api.post("/funding-reports/export-cancel", { jobId });
};

export const downloadExport = (jobId) => {
  return api.get("/funding-reports/export-download", {
    params: { jobId },
    responseType: "blob",
  });
};

export default {
  getStatistics,
  getAllTransactions,
  exportReport,
  getActiveExport,
  getExportStatus,
  cancelExport,
  downloadExport,
};
