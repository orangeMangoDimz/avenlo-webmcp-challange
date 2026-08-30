import api from "./api";
import clientAuthService from "./clientAuthService";

/**
 * 客户管理服务
 */
export const clientService = {
  /**
   * 获取客户列表
   * @param {object} params - 查询参数
   */
  async getList(params = {}) {
    return await api.get("/admin-clients", { params });
  },

  /**
   * 获取客户统计信息
   */
  async getStatistics() {
    return await api.get("/admin-clients/statistics");
  },

  /**
   * 获取开户所需的下拉选项
   */
  async getCreateOptions() {
    return await api.get("/admin-clients/options");
  },

  /**
   * 获取可分配的 Sales 列表（仅 Role 为 Sales 的用户，供 Assign to Sales Representative 下拉使用）
   */
  async getAssignableSales() {
    return await api.get("/admin-clients/assignable-sales");
  },

  /**
   * 创建新客户
   * @param {object} data - 客户信息
   */
  async createClient(data) {
    return await api.post("/admin-clients/create", data);
  },

  /**
   * 获取客户详情
   * @param {number} id - 客户ID
   */
  async getDetail(id) {
    return await api.get("/admin-clients/detail", { params: { id } });
  },

  /**
   * 更新客户信息
   * @param {number} id - 客户ID
   * @param {object} data - 更新数据
   */
  async update(id, data) {
    return await api.put(`/admin-clients/${id}`, data);
  },

  /**
   * 删除客户
   * @param {number} id - 客户ID
   */
  async delete(id) {
    return await api.delete(`/admin-clients/${id}`);
  },

  /**
   * 批量分配客户
   * @param {array} clientIds - 客户ID数组
   * @param {object} assignmentData - 分配数据
   */
  async bulkAssign(clientIds, assignmentData) {
    return await api.post("/admin-clients/bulk-assign", {
      clientIds,
      ...assignmentData,
    });
  },

  /**
   * 批量添加标签
   * @param {array} clientIds - 客户ID数组
   * @param {object} tagData - 标签数据
   */
  async bulkAddTags(clientIds, tagData) {
    return await api.post("/admin-clients/bulk-tags", {
      clientIds,
      ...tagData,
    });
  },

  /**
   * 批量删除客户
   * @param {array} clientIds - 客户ID数组
   */
  async bulkDelete(clientIds) {
    return await api.post("/admin-clients/bulk-delete", {
      clientIds,
    });
  },

  /**
   * 导出客户数据
   * @param {string} format - 导出格式 (csv, excel)
   * @param {object} params - 导出参数
   */
  async export(format, params = {}) {
    return await api.get(`/admin-clients/export/${format}`, {
      params,
      responseType: "blob",
    });
  },

  /**
   * 获取客户活动日志
   * @param {number} id - 客户ID
   */
  async getActivities(id) {
    return await api.get(`/admin-clients/${id}/activities`);
  },

  /**
   * 获取客户KYC历史
   * @param {number} id - 客户ID
   */
  async getKycHistory(id) {
    return await api.get(`/admin-clients/${id}/kyc-history`);
  },

  /**
   * 获取客户文档
   * @param {number} id - 客户ID
   */
  async getDocuments(id) {
    return await api.get(`/admin-clients/${id}/documents`);
  },

  /**
   * 更新客户状态
   * @param {number} id - 客户ID
   * @param {string} status - 新状态
   */
  async updateStatus(id, status) {
    return await api.put(`/admin-clients/${id}/status`, { status });
  },

  /**
   * 强制通过客户 KYC
   * - 如果没有 KYC submission，后端会自动创建一条合适的 submission
   * - 然后统一标记为 approved
   * @param {number} id - 客户ID
   */
  async approveKyc(id) {
    return await api.post(`/admin-clients/${id}/approve-kyc`);
  },

  /**
   * 重置客户密码
   * @param {number} id - 客户ID
   */
  async resetPassword(id, data) {
    return await api.post(`/admin-clients/${id}/reset-password`, data);
  },

  /**
   * 发送客户密码重置邮件
   * @param {number} id - 客户ID
   */
  async sendPasswordReset(id, email, extra = {}) {
    try {
      return await api.post(`/admin-clients/${id}/send-password-reset`, {
        useHashMode: true,
        ...extra,
      });
    } catch (error) {
      const message = error?.message || error?.response?.data?.message || "";
      const isRouteMissing =
        typeof message === "string" &&
        message.toLowerCase().includes("route not found");

      // Some backends do not implement the admin-clients send-password-reset route yet.
      // Fall back to the client forgot-password endpoint, which sends the same reset email by address.
      if (isRouteMissing && email) {
        return await clientAuthService.forgotPassword(email);
      }

      throw error;
    }
  },

  /**
   * 发送邮件给客户
   * @param {number} id - 客户ID
   * @param {object} emailData - 邮件数据
   */
  async sendEmail(id, emailData) {
    return await api.post(`/admin-clients/${id}/send-email`, emailData);
  },

  /**
   * 获取客户交易信息
   * @param {number} id - 客户ID
   */
  async getTradingInfo(id) {
    return await api.get(`/admin-clients/${id}/trading-info`);
  },

  /**
   * Get a client's trading history (open positions, pending orders, or closed orders)
   * @param {number} id - client id
   * @param {object} params - { status: 'closed'|'open'|'pending', page, pageSize, keywords, side, periodFrom, periodTo, sort, sortOrder }
   */
  async getTradingHistory(id, params = {}) {
    return await api.get(`/admin-clients/${id}/trading-history`, { params });
  },

  /**
   * 获取后台给客户开户可用的交易平台
   */
  async getTradingPlatforms() {
    return await api.get("/admin-clients/trading-platforms");
  },

  /**
   * 获取客户在指定平台的默认交易组和杠杆配置
   * @param {number} id - 客户ID
   * @param {string} platform - 平台 key
   */
  async getDefaultTradingGroups(id, platform) {
    return await api.get(`/admin-clients/${id}/trading-groups/default`, {
      params: { platform },
    });
  },

  /**
   * 后台为客户创建交易账号
   * @param {number} id - 客户ID
   * @param {object} data - 开户数据
   */
  async createTradingAccount(id, data) {
    return await api.post(`/admin-clients/${id}/trading-accounts/create`, data);
  },

  async getAssignableCommissionRules(id) {
    return await api.get(
      `/admin-clients/${id}/trading-accounts/assignable-commission-rules`,
    );
  },

  async updateAssignedCommissionRule(
    clientId,
    tradingAccountId,
    assignedCommissionRuleId,
  ) {
    return await api.patch(
      `/admin-clients/${clientId}/trading-accounts/${tradingAccountId}/assigned-commission-rule`,
      { assignedCommissionRuleId },
    );
  },

  /**
   * 获取客户资金交易记录
   * @param {number} id - 客户ID
   * @param {object} params - 查询参数 { type, page, limit }
   */
  async getTransactions(id, params = {}) {
    return await api.get(`/admin-clients/${id}/transactions`, { params });
  },

  /**
   * 获取客户出金方式模板提交记录
   * @param {number} id - 客户ID
   * @param {object} params - 查询参数 { page, per_page }
   */
  async getWithdrawTemplates(id, params = {}) {
    return await api.get("/admin-clients/withdraw-template", {
      params: {
        clientId: id,
        ...params,
      },
    });
  },

  /**
   * 获取客户关联的 IB 基础信息
   * @param {number} id - IB Partner ID
   */
  async getIb(id) {
    return await api.get("/admin-clients/ib", { params: { id } });
  },

  /**
   * 获取某 IB 整个下级网络下的客户列表（分页）
   * @param {number} ibPartnerId - IB Partner ID
   * @param {object} params - { page, per_page }
   */
  async getIbNetworkClients(ibPartnerId, params = {}) {
    return await api.get("/admin-clients/ib-network-clients", {
      params: { ibPartnerId, ...params },
    });
  },

  async getActiveIbNetworkClientsExport() {
    return await api.get("/admin-clients/ib-network-clients/export-active");
  },

  async enqueueIbNetworkClientsExport(ibPartnerId, params = {}) {
    return await api.post("/admin-clients/ib-network-clients/export", params, {
      params: { ibPartnerId },
    });
  },

  async getIbNetworkClientsExportStatus(jobId) {
    return await api.get("/admin-clients/ib-network-clients/export-status", {
      params: { jobId },
    });
  },

  async cancelIbNetworkClientsExport(jobId) {
    return await api.post("/admin-clients/ib-network-clients/export-cancel", {
      jobId,
    });
  },

  async downloadIbNetworkClientsExport(jobId) {
    return await api.get("/admin-clients/ib-network-clients/export-download", {
      params: { jobId },
      responseType: "blob",
    });
  },

  /**
   * 获取某 IB 的佣金统计（顶部统计卡）
   * @param {number} ibPartnerId - IB Partner ID
   * @param {object} params - { start_date, end_date }
   */
  async getIbCommissionStatistics(ibPartnerId, params = {}) {
    return await api.get("/admin-clients/commission-report/statistics", {
      params: { ibPartnerId, ...params },
    });
  },

  /**
   * 获取某 IB 的佣金明细列表
   * @param {number} ibPartnerId - IB Partner ID
   * @param {object} params - { page, per_page, start_date, end_date, search }
   */
  async getIbCommissionList(ibPartnerId, params = {}) {
    return await api.get("/admin-clients/commission-report/list", {
      params: { ibPartnerId, ...params },
    });
  },

  /**
   * 获取某 IB 佣金明细的展开详情
   * @param {number} ibPartnerId - IB Partner ID
   * @param {object} params - { referral_id, type, page, per_page, start_date, end_date }
   */
  async getIbCommissionDetail(ibPartnerId, params = {}) {
    return await api.get("/admin-clients/commission-report/detail", {
      params: { ibPartnerId, ...params },
    });
  },

  async getActiveIbCommissionDetailExport() {
    return await api.get(
      "/admin-clients/commission-report/export-detail-active",
    );
  },

  async enqueueIbCommissionDetailExport(ibPartnerId, params = {}) {
    return await api.post(
      "/admin-clients/commission-report/export-detail",
      params,
      {
        params: { ibPartnerId },
      },
    );
  },

  async getIbCommissionDetailExportStatus(jobId) {
    return await api.get(
      "/admin-clients/commission-report/export-detail-status",
      { params: { jobId } },
    );
  },

  async cancelIbCommissionDetailExport(jobId) {
    return await api.post(
      "/admin-clients/commission-report/export-detail-cancel",
      { jobId },
    );
  },

  async downloadIbCommissionDetailExport(jobId) {
    return await api.get(
      "/admin-clients/commission-report/export-detail-download",
      {
        params: { jobId },
        responseType: "blob",
      },
    );
  },

  async getIbUpline(id) {
    return await api.get(`/admin-clients/${id}/ib-upline`);
  },

  /**
   * 获取客户 Sales Assignment 信息
   * @param {number} id - 客户ID
   */
  async getSalesAssignment(id) {
    return await api.get(`/admin-clients/${id}/sales`);
  },

  /**
   * 获取用于IB选择的已验证客户列表
   * @param {object} params - 查询参数 { search, page, per_page }
   */
  async getClientsForIbSelection(params = {}) {
    return await api.get("/admin-clients/for-ib-selection", { params });
  },
};

/**
 * 客户标签管理服务
 */
export const clientTagService = {
  /**
   * 获取所有标签
   */
  async getTags() {
    return await api.get("/admin-client-tags");
  },

  /**
   * 创建新标签
   * @param {object} data - 标签数据
   */
  async createTag(data) {
    return await api.post("/admin-client-tags/create", data);
  },

  /**
   * 更新标签
   * @param {number} id - 标签ID
   * @param {object} data - 更新数据
   */
  async updateTag(id, data) {
    return await api.put(`/admin-client-tags/${id}`, data);
  },

  /**
   * 删除标签
   * @param {number} id - 标签ID
   */
  async deleteTag(id) {
    return await api.delete(`/admin-client-tags/${id}`);
  },

  /**
   * 获取标签统计
   */
  async getTagStatistics() {
    return await api.get("/admin-client-tags/statistics");
  },
};

/**
 * 客户分配管理服务
 */
export const clientAssignmentService = {
  /**
   * 获取可分配的管理员列表
   */
  async getAssignableAdmins() {
    return await api.get("/admin-client-assignments/admins");
  },

  /**
   * 获取管理员的客户分配统计
   * @param {number} adminId - 管理员ID
   */
  async getAdminAssignmentStats(adminId) {
    return await api.get(
      `/admin-client-assignments/admin/${adminId}/statistics`,
    );
  },

  /**
   * 重新分配客户
   * @param {number} clientId - 客户ID
   * @param {number} fromAdminId - 原管理员ID
   * @param {number} toAdminId - 新管理员ID
   */
  async reassignClient(clientId, fromAdminId, toAdminId) {
    return await api.post("/admin-client-assignments/reassign", {
      clientId,
      fromAdminId,
      toAdminId,
    });
  },
};

/**
 * 客户报告服务
 */
export const clientReportService = {
  /**
   * 获取客户概览报告
   * @param {object} params - 报告参数
   */
  async getOverviewReport(params = {}) {
    return await api.get("/admin-client-reports/overview", { params });
  },

  /**
   * 获取KYC完成率报告
   * @param {object} params - 报告参数
   */
  async getKycCompletionReport(params = {}) {
    return await api.get("/admin-client-reports/kyc-completion", { params });
  },

  /**
   * 获取客户活跃度报告
   * @param {object} params - 报告参数
   */
  async getActivityReport(params = {}) {
    return await api.get("/admin-client-reports/activity", { params });
  },

  /**
   * 导出报告
   * @param {string} reportType - 报告类型
   * @param {string} format - 导出格式
   * @param {object} params - 报告参数
   */
  async exportReport(reportType, format, params = {}) {
    return await api.get(
      `/admin-client-reports/${reportType}/export/${format}`,
      {
        params,
        responseType: "blob",
      },
    );
  },
};

export default {
  clientService,
  clientTagService,
  clientAssignmentService,
  clientReportService,
};
