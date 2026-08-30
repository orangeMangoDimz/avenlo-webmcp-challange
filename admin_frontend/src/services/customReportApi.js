/**
 * Custom Report API Service
 */

import api from "./api";

export const listReports = (params = {}) => {
  return api.get("/custom-reports", { params });
};

export const createReport = (data) => {
  return api.post("/custom-reports", data);
};

export const updateReport = (id, data) => {
  return api.put(`/custom-reports/${id}`, data);
};

export const deleteReport = (id) => {
  return api.delete(`/custom-reports/${id}`);
};

export const getReport = (id) => {
  return api.get(`/custom-reports/${id}`);
};

export const listDataSources = () => {
  return api.get("/custom-reports/data-sources");
};

export const getDataSource = (dataSourceId) => {
  return api.get(`/custom-reports/data-sources/${dataSourceId}`);
};

export const getDataSourceRows = (dataSourceId, params = {}) => {
  return api.get(`/custom-reports/data-sources/${dataSourceId}/rows`, {
    params,
  });
};

export const getDataSourceColumnValues = (dataSourceId, params = {}) => {
  return api.get(`/custom-reports/data-sources/${dataSourceId}/column-values`, {
    params,
  });
};

export const createWidget = (reportId, data) => {
  return api.post(`/custom-reports/${reportId}/widgets`, data);
};

export const updateWidget = (reportId, widgetId, data) => {
  return api.put(`/custom-reports/${reportId}/widgets/${widgetId}`, data);
};

export const deleteWidget = (reportId, widgetId) => {
  return api.delete(`/custom-reports/${reportId}/widgets/${widgetId}`);
};

export const duplicateWidget = (reportId, widgetId) => {
  return api.post(`/custom-reports/${reportId}/widgets/${widgetId}/duplicate`);
};

export const getTransactions = (params = {}) => {
  return api.get("/custom-reports/transactions", { params });
};

export const getWidgetRows = (reportId, widgetId, params = {}) => {
  return api.get(`/custom-reports/${reportId}/widgets/${widgetId}/rows`, {
    params,
  });
};

export const getWidgetColumnValues = (reportId, widgetId, params = {}) => {
  return api.get(
    `/custom-reports/${reportId}/widgets/${widgetId}/column-values`,
    { params },
  );
};

export const getWidgetDetailRows = (reportId, widgetId, params = {}) => {
  return api.get(
    `/custom-reports/${reportId}/widgets/${widgetId}/detail-rows`,
    { params },
  );
};

export const getWidgetChart = (reportId, widgetId, params = {}) => {
  return api.get(`/custom-reports/${reportId}/widgets/${widgetId}/chart`, {
    params,
  });
};

export const enqueueWidgetExport = (reportId, widgetId, data = {}) => {
  return api.post(
    `/custom-reports/${reportId}/widgets/${widgetId}/export`,
    data,
  );
};

export const getWidgetExportActive = (reportId, widgetId) => {
  return api.get(
    `/custom-reports/${reportId}/widgets/${widgetId}/export-active`,
  );
};

export const getWidgetExportStatus = (reportId, widgetId, jobId) => {
  return api.get(
    `/custom-reports/${reportId}/widgets/${widgetId}/export-status`,
    { params: { jobId } },
  );
};

export const cancelWidgetExport = (reportId, widgetId, jobId) => {
  return api.post(
    `/custom-reports/${reportId}/widgets/${widgetId}/export-cancel`,
    { jobId },
  );
};

export const downloadWidgetExport = (reportId, widgetId, jobId) => {
  return api.get(
    `/custom-reports/${reportId}/widgets/${widgetId}/export-download`,
    {
      params: { jobId },
      responseType: "blob",
    },
  );
};

export const enqueueDataSourceExport = (dataSourceId, data = {}) => {
  return api.post(`/custom-reports/data-sources/${dataSourceId}/export`, data);
};

export const getDataSourceExportActive = (dataSourceId) => {
  return api.get(`/custom-reports/data-sources/${dataSourceId}/export-active`);
};

export const getDataSourceExportStatus = (dataSourceId, jobId) => {
  return api.get(`/custom-reports/data-sources/${dataSourceId}/export-status`, {
    params: { jobId },
  });
};

export const cancelDataSourceExport = (dataSourceId, jobId) => {
  return api.post(
    `/custom-reports/data-sources/${dataSourceId}/export-cancel`,
    { jobId },
  );
};

export const downloadDataSourceExport = (dataSourceId, jobId) => {
  return api.get(
    `/custom-reports/data-sources/${dataSourceId}/export-download`,
    {
      params: { jobId },
      responseType: "blob",
    },
  );
};

export default {
  listReports,
  createReport,
  updateReport,
  deleteReport,
  getReport,
  listDataSources,
  getDataSource,
  getDataSourceRows,
  getDataSourceColumnValues,
  createWidget,
  updateWidget,
  deleteWidget,
  duplicateWidget,
  getTransactions,
  getWidgetRows,
  getWidgetColumnValues,
  getWidgetDetailRows,
  getWidgetChart,
  enqueueWidgetExport,
  getWidgetExportActive,
  getWidgetExportStatus,
  cancelWidgetExport,
  downloadWidgetExport,
  enqueueDataSourceExport,
  getDataSourceExportActive,
  getDataSourceExportStatus,
  cancelDataSourceExport,
  downloadDataSourceExport,
};
