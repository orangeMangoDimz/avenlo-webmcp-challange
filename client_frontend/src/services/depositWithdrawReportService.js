import api from "./api";

/**
 * Deposit / Withdraw report service
 */
export const depositWithdrawReportService = {
  /**
   * @param {object} params - page, per_page, start_date, end_date, search, ibPartnerId
   */
  async getList(params = {}) {
    return await api.get("/client/deposit-withdraw-report/list", { params });
  },

  /**
   * Deposit + withdrawal history for one referral row.
   * @param {object} params - referral_id, type, page, per_page, start_date, end_date, ibPartnerId
   */
  async getDetail(params = {}) {
    return await api.get("/client/deposit-withdraw-report/detail", { params });
  },

  async getActiveExport() {
    return await api.get("/client/deposit-withdraw-report/export-active");
  },

  /**
   * @param {object} params - start_date, end_date, search, ibPartnerId
   * @param {array} items - selected rows
   */
  async export(params = {}, items = []) {
    return await api.post(
      "/client/deposit-withdraw-report/export",
      { items },
      { params },
    );
  },

  async getExportStatus(jobId) {
    return await api.get("/client/deposit-withdraw-report/export-status", {
      params: { jobId },
    });
  },

  async cancelExport(jobId) {
    return await api.post("/client/deposit-withdraw-report/export-cancel", {
      jobId,
    });
  },

  async downloadExport(jobId) {
    return await api.get("/client/deposit-withdraw-report/export-download", {
      params: { jobId },
      responseType: "blob",
    });
  },
};

export default depositWithdrawReportService;
