import clientApi from "./clientApi";

/**
 * 客户端工单服务
 */
const clientTicketService = {
  /**
   * 提交工单
   * @param {Object} data - { title, content }
   */
  async submitTicket(data) {
    return await clientApi.post(
      "/index.php?path=api/client-tickets/create",
      data,
    );
  },
};

export default clientTicketService;
