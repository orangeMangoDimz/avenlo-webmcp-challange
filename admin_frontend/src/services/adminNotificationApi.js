/**
 * Admin Notification API Service
 * 后台管理员通知相关API接口
 */

import api from "./api";

/**
 * 获取管理员通知列表
 * @param {Object} params - 查询参数 (limit, offset)
 */
export const getNotifications = (params = {}) => {
  return api.get("/admin/notifications", { params });
};

/**
 * 获取未读通知数量
 */
export const getUnreadCount = () => {
  return api.get("/admin/notifications/unread-count");
};

/**
 * 标记通知为已读
 * @param {number} id - 通知ID
 */
export const markAsRead = (id) => {
  return api.post(`/admin/notifications/${id}/read`);
};

/**
 * 批量标记通知为已读
 * @param {Array<number>} ids - 通知ID数组
 */
export const markAsReadBatch = (ids) => {
  return api.post("/admin/notifications/mark-read", { ids });
};

/**
 * 标记所有通知为已读
 */
export const markAllAsRead = () => {
  return api.post("/admin/notifications/mark-all-read");
};

export default {
  getNotifications,
  getUnreadCount,
  markAsRead,
  markAsReadBatch,
  markAllAsRead,
};
