import api from "./api";
import { getSubModuleKey } from "@/config/operationLogPages";

const KYC_SETTINGS_LOG_SUB_MODULE = getSubModuleKey("page_kyc_settings");

const withLogSubModule = (data = {}) => ({
  logSubModuleKey: KYC_SETTINGS_LOG_SUB_MODULE,
  ...data,
});

/**
 * KYC设置管理服务
 */
export const kycSettingsService = {
  /**
   * 获取所有KYC设置概览
   */
  async getAllSettings() {
    return await api.get("/kyc-settings");
  },

  /**
   * 获取统计信息
   */
  async getStatistics() {
    return await api.get("/kyc-settings/statistics");
  },

  // ============================================================
  // 通知设置
  // ============================================================

  /**
   * 获取KYC通知设置
   * @param {string} key - 设置键，默认 'default_kyc_notice'
   */
  async getNoticeSettings(key = "default_kyc_notice") {
    return await api.get(`/kyc-settings/notice/${key}`);
  },

  /**
   * 更新KYC通知设置
   * @param {object} data - 设置数据
   */
  async updateNoticeSettings(data) {
    return await api.put("/kyc-settings/notice/modify", withLogSubModule(data));
  },

  /**
   * 获取客户端通知配置（公开接口）
   */
  async getClientNoticeConfig() {
    return await api.get("/kyc-settings/client-notice");
  },

  // ============================================================
  // 状态消息模板
  // ============================================================

  /**
   * 获取状态消息模板列表
   * @param {object} params - 查询参数
   */
  async getStatusMessages(params = {}) {
    const queryParams = new URLSearchParams(params).toString();
    return await api.get(
      `/kyc-settings/status-messages${queryParams ? "?" + queryParams : ""}`,
    );
  },

  /**
   * 更新状态消息模板
   * @param {number} id - 模板ID
   * @param {object} data - 更新数据
   */
  async updateStatusMessage(id, data) {
    return await api.put(`/kyc-settings/status-messages/${id}`, data);
  },

  /**
   * 批量更新状态消息
   * @param {array} templates - 模板数组
   */
  async bulkUpdateStatusMessages(templates) {
    return await api.put("/kyc-settings/status-messages/bulk", templates);
  },

  /**
   * 获取客户端状态消息（公开接口）
   * @param {string} statusType - 状态类型
   */
  async getClientStatusMessage(statusType) {
    return await api.get(`/kyc-settings/client-status-message/${statusType}`);
  },

  // ============================================================
  // 要求项管理
  // ============================================================

  /**
   * 获取要求项列表
   * @param {number} noticeSettingId - 通知设置ID
   */
  async getRequirements(noticeSettingId = null) {
    const params = noticeSettingId
      ? `?notice_setting_id=${noticeSettingId}`
      : "";
    return await api.get(`/kyc-settings/requirements${params}`);
  },

  /**
   * 创建要求项
   * @param {object} data - 要求项数据
   */
  async createRequirement(data) {
    return await api.post("/kyc-settings/requirements", data);
  },

  /**
   * 更新要求项
   * @param {number} id - 要求项ID
   * @param {object} data - 更新数据
   */
  async updateRequirement(id, data) {
    return await api.put(`/kyc-settings/requirements/${id}`, data);
  },

  /**
   * 删除要求项
   * @param {number} id - 要求项ID
   */
  async deleteRequirement(id) {
    return await api.delete(`/kyc-settings/requirements/${id}`);
  },

  /**
   * 重新排序要求项
   * @param {array} itemIds - 要求项ID数组（按新顺序）
   */
  async reorderRequirements(itemIds) {
    return await api.put("/kyc-settings/requirements/reorder", { itemIds });
  },

  // ============================================================
  // 邮件模板
  // ============================================================

  /**
   * 获取邮件模板列表
   * @param {object} params - 查询参数
   */
  async getEmailTemplates(params = {}) {
    const queryParams = new URLSearchParams(params).toString();
    return await api.get(
      `/kyc-settings/email-templates${queryParams ? "?" + queryParams : ""}`,
    );
  },

  /**
   * 获取单个邮件模板
   * @param {number} id - 模板ID
   */
  async getEmailTemplate(id) {
    return await api.get(`/kyc-settings/email-templates/${id}`);
  },

  /**
   * 更新邮件模板
   * @param {number} id - 模板ID
   * @param {object} data - 更新数据
   */
  async updateEmailTemplate(id, data) {
    return await api.put(`/kyc-settings/email-templates/${id}`, data);
  },

  /**
   * 预览邮件模板
   * @param {number} id - 模板ID
   * @param {object} variables - 变量对象
   */
  async previewEmailTemplate(id, variables = {}) {
    return await api.post(`/kyc-settings/email-templates/${id}/preview`, {
      variables,
    });
  },

  // ============================================================
  // 更改历史
  // ============================================================

  /**
   * 获取更改历史
   * @param {object} params - 查询参数
   */
  async getChangeHistory(params = {}) {
    const queryParams = new URLSearchParams(params).toString();
    return await api.get(
      `/kyc-settings/change-history${queryParams ? "?" + queryParams : ""}`,
    );
  },
};

export default kycSettingsService;
