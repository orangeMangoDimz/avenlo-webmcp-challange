import api from "./api";

/**
 * 客户通知服务
 */
export const notificationService = {
  /**
   * 发送或计划发送通知（单个）
   * @param {Object} data
   */
  async sendToClient(data) {
    return await api.post("/client-notifications", data);
  },

  /**
   * 批量发送或计划发送通知
   * @param {Object} data - { clientIds: number[], subject, message, scheduleType, scheduledAt?, priority?, sendSystemNotification, sendEmail, emailTemplate? }
   */
  async sendBulkToClients(data) {
    return await api.post("/client-notifications/bulk", data);
  },

  /**
   * 获取启用状态的邮件模板
   */
  async getEmailTemplates() {
    return await api.get("/client-email-templates");
  },

  /**
   * 预留：触发定时通知处理
   */
  async processDueNotifications() {
    return await api.post("/client-notifications/process-due");
  },
};
