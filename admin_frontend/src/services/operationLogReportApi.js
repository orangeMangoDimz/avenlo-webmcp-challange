/**
 * 后台操作日志报表
 */
import api from "./api";

/** 首屏：Tab + 字典 + 默认列表 */
export function fetchOperationLogReportInit() {
  return api.get("/operation-log/reports/init");
}

/**
 * 查询 / 翻页 / 切 Tab 后查询
 * @param {object} params
 */
export function fetchOperationLogReports(params = {}) {
  return api.get("/operation-log/reports", { params });
}

export function exportOperationLogReports(body = {}) {
  return api.post("/operation-log/reports/export", body);
}

export function getActiveExport() {
  return api.get("/operation-log/reports/export-active");
}

export function getExportStatus(jobId) {
  return api.get("/operation-log/reports/export-status", { params: { jobId } });
}

export function cancelExport(jobId) {
  return api.post("/operation-log/reports/export-cancel", { jobId });
}

export function downloadExport(jobId) {
  return api.get("/operation-log/reports/export-download", {
    params: { jobId },
    responseType: "blob",
  });
}

/**
 * 仅写入操作日志（纯前端操作成功后调用，不改变业务数据）
 * @param {object} body modelKey, subModuleKey, operationTypeKey, detailZh, detailEn, targetId?
 */
export function recordOperationLog(body = {}) {
  return api.post("/operation-log/record", body);
}

export default {
  fetchOperationLogReportInit,
  fetchOperationLogReports,
  exportOperationLogReports,
  getActiveExport,
  getExportStatus,
  cancelExport,
  downloadExport,
  recordOperationLog,
};
