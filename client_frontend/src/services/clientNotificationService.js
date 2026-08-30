import api from "./api";

/**
 * 客户端通知服务
 */
export const clientNotificationService = {
  /**
   * 获取通知列表（默认返回最新 5 条）
   * @param {Object} params
   */
  async fetchNotifications(params = {}) {
    return await api.get("/client-notifications/list", { params });
  },

  /**
   * 将指定通知标记为已读
   * @param {{ id?: number, ids?: number[] }} payload
   */
  async markAsRead(payload) {
    return await api.post("/client-notifications/mark-read", payload);
  },

  /**
   * 将所有通知标记为已读
   */
  async markAllAsRead() {
    return await api.get("/client-notifications/mark-all-read");
  },
};
