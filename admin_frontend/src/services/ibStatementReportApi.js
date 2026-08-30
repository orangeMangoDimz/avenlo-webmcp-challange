import api from "./api";

export const getPartners = (params = {}) => {
  return api.get("/ib-statement-report/partners", { params });
};

export const getStatement = (params = {}) => {
  return api.get("/ib-statement-report", { params });
};

export const getActiveExport = () => {
  return api.get("/ib-statement-report/export-active");
};

export const exportReport = (data) => {
  return api.post("/ib-statement-report/export", data);
};

export const getExportStatus = (jobId) => {
  return api.get("/ib-statement-report/export-status", { params: { jobId } });
};

export const cancelExport = (jobId) => {
  return api.post("/ib-statement-report/export-cancel", { jobId });
};

export const downloadExport = (jobId) => {
  return api.get("/ib-statement-report/export-download", {
    params: { jobId },
    responseType: "blob",
  });
};

export default {
  getPartners,
  getStatement,
  getActiveExport,
  exportReport,
  getExportStatus,
  cancelExport,
  downloadExport,
};
