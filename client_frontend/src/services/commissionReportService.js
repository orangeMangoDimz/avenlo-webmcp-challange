import api from "./api";

/**
 * 佣金报表服务
 */
export const commissionReportService = {
  /**
   * 获取统计信息
   * @param {object} params - 查询参数（start_date, end_date）
   */
  async getStatistics(params = {}) {
    return await api.get("/client/commission-report/statistics", { params });
  },

  /**
   * 获取佣金列表
   * @param {object} params - 查询参数（page, per_page, start_date, end_date, search）
   */
  async getList(params = {}) {
    return await api.get("/client/commission-report/list", { params });
  },

  /**
   * 获取详细佣金明细
   * @param {object} params - 查询参数（referral_id, type）
   */
  async getDetail(params = {}) {
    return await api.get("/client/commission-report/detail", { params });
  },

  /**
   * 当前用户是否有进行中/已完成待下载的导出任务
   */
  async getActiveExport() {
    return await api.get("/client/commission-report/export-active");
  },

  /**
   * 排队异步导出（返回 jobId）
   * @param {object} params - 查询参数（start_date, end_date, search）
   * @param {array} items - 勾选行
   */
  async export(params = {}, items = []) {
    return await api.post(
      "/client/commission-report/export",
      { items },
      { params },
    );
  },

  /**
   * 轮询导出进度
   * @param {string} jobId
   */
  async getExportStatus(jobId) {
    return await api.get("/client/commission-report/export-status", {
      params: { jobId },
    });
  },

  /**
   * 请求取消导出
   * @param {string} jobId
   */
  async cancelExport(jobId) {
    return await api.post("/client/commission-report/export-cancel", { jobId });
  },

  /**
   * 下载已完成的 CSV
   * @param {string} jobId
   */
  async downloadExport(jobId) {
    return await api.get("/client/commission-report/export-download", {
      params: { jobId },
      responseType: "blob",
    });
  },
};

export default commissionReportService;
