/**
 * Client Ticket API Service
 * 客户端工单相关API接口（后台使用）
 */

import api from "./api";

const CLIENT_TICKETS_LOG_SUB_MODULE = "client_tickets";

const withClientTicketsLog = (data = {}) => ({
  ...data,
  logSubModuleKey: CLIENT_TICKETS_LOG_SUB_MODULE,
});

/**
 * 获取工单列表
 * @param {Object} params - 查询参数 (page, per_page, startDate, endDate, clientId)
 */
export const getTickets = (params = {}) => {
  return api.get("/client-tickets", { params });
};

/**
 * 获取单个工单详情
 * @param {number} id - 工单ID
 */
export const getTicket = (id) => {
  return api.get(`/client-tickets/detail/${id}`);
};

/**
 * 标记工单状态（已解决/未解决）
 * @param {number} id - 工单ID
 * @param {'resolved'|'open'} status - 目标状态
 */
export const updateTicketStatus = (id, status) => {
  return api.post(
    `/client-tickets/${id}/status`,
    withClientTicketsLog({ status }),
  );
};

export default {
  getTickets,
  getTicket,
  updateTicketStatus,
};
