import api from "./api";

/**
 * IB Dashboard服务
 */
export const ibDashboardService = {
  /**
   * 获取当前账号可切换的IB身份
   */
  async getPartners() {
    return await api.get("/client/ib/dashboard/partners");
  },

  /**
   * 设置/清空当前账号名下某个 IB 的 clientAlias（Name IB）
   * 传空字符串或 null 表示清空别名
   */
  async updateClientAlias(ibPartnerId, clientAlias) {
    return await api.put("/client/ib/dashboard/alias", {
      ibPartnerId,
      clientAlias,
    });
  },

  /**
   * 获取IB Dashboard统计信息
   */
  async getStatistics(params = {}) {
    return await api.get("/client/ib/dashboard/statistics", { params });
  },

  /**
   * 获取推荐URL和统计
   */
  async getReferralUrl(params = {}) {
    return await api.get("/client/ib/dashboard/referral-url", { params });
  },

  /**
   * 获取佣金率
   */
  async getCommissionRates(params = {}) {
    return await api.get("/client/ib/dashboard/commission-rates", { params });
  },

  /**
   * 获取IB Journey时间线
   */
  async getJourney(params = {}) {
    return await api.get("/client/ib/dashboard/journey", { params });
  },

  /**
   * 获取IB网络图数据
   */
  async getNetwork(params = {}) {
    return await api.get("/client/ib/dashboard/network", { params });
  },

  /**
   * 获取网络成员列表
   * @param {object} params - 查询参数（page, per_page, search, type）
   */
  async getNetworkMembers(params = {}) {
    return await api.get("/client/ib/dashboard/network-members", { params });
  },
};

export default ibDashboardService;
