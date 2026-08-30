import api from "./api";

export const ibCommissionReportService = {
  async getList(params = {}) {
    return await api.get("/ib-commission-report", { params });
  },

  async getStatistics(params = {}) {
    return await api.get("/ib-commission-report/statistics", { params });
  },

  async getDetails(ibPartnerId, params = {}) {
    return await api.get(`/ib-commission-report/${ibPartnerId}`, { params });
  },

  async getActiveExport() {
    return await api.get("/ib-commission-report/export-detail-active");
  },

  async enqueueExportDetail(params = {}) {
    return await api.post("/ib-commission-report/export-detail", params);
  },

  async getExportStatus(jobId) {
    return await api.get("/ib-commission-report/export-detail-status", {
      params: { jobId },
    });
  },

  async cancelExport(jobId) {
    return await api.post("/ib-commission-report/export-detail-cancel", {
      jobId,
    });
  },

  async downloadExport(jobId) {
    return await api.get("/ib-commission-report/export-detail-download", {
      params: { jobId },
      responseType: "blob",
    });
  },
};

export default ibCommissionReportService;
