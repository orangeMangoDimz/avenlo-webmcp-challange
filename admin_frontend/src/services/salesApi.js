import api from "./api";

/**
 * Sales 列表与统计 API（统一在 sales.php）
 */
const salesApi = {
  /**
   * 获取 Sales 列表（含分页与顶部统计）
   * @param {Object} params - page, per_page, search
   * @returns {Promise<{ items, pagination, stats }>}
   */
  async getList(params = {}) {
    const res = await api.get("/sales", { params });
    const data = res?.data ?? res;
    return {
      items: data?.items ?? [],
      pagination: data?.pagination ?? {
        total: 0,
        page: 1,
        per_page: 10,
        total_pages: 1,
      },
      stats: data?.stats ?? { totalSales: 0, activeSales: 0, inactiveSales: 0 },
    };
  },

  /**
   * 仅获取顶部统计
   */
  async getStats() {
    const res = await api.get("/sales/stats");
    const data = res?.data ?? res;
    return data ?? { totalSales: 0, activeSales: 0, inactiveSales: 0 };
  },

  /**
   * 当前登录用户作为 Sales 的资料（用于 Sales Dashboard）
   * GET /api/sales/me
   */
  async getMe() {
    const res = await api.get("/sales", { params: { subpath: "me" } });
    const data = res?.data ?? res;
    return data?.data ?? data;
  },

  /**
   * 某 Sales 绑定的 Client 列表（分页 + 搜索 id/name）
   * 使用 subpath 避免与路由用 path 冲突
   */
  async getBoundClients(salesId, params = {}) {
    const res = await api.get("/sales", {
      params: { subpath: `${salesId}/bound-clients`, ...params },
    });
    const data = res?.data ?? res;
    return {
      items: data?.items ?? [],
      pagination: data?.pagination ?? {
        total: 0,
        page: 1,
        per_page: 20,
        total_pages: 1,
      },
    };
  },

  /**
   * 某 Sales 绑定的 IB 列表（分页 + 搜索 id/name）
   * 使用 subpath 避免与路由用 path 冲突
   */
  async getBoundIbs(salesId, params = {}) {
    const res = await api.get("/sales", {
      params: { subpath: `${salesId}/bound-ibs`, ...params },
    });
    const data = res?.data ?? res;
    return {
      items: data?.items ?? [],
      pagination: data?.pagination ?? {
        total: 0,
        page: 1,
        per_page: 20,
        total_pages: 1,
      },
    };
  },

  /**
   * 某 Sales 单月业绩（Sales Dashboard 顶部卡片）
   * GET /api/sales/{salesId}/monthly-performance?month=YYYY-MM&tzOffset=
   */
  async getMonthlyPerformance(salesId, params = {}) {
    const res = await api.get("/sales", {
      params: { subpath: `${salesId}/monthly-performance`, ...params },
    });
    const data = res?.data ?? res;
    return data?.data ?? data;
  },

  /**
   * 更新 Sales 个人推荐链接后缀
   * POST /api/sales/{salesId}/referral-suffix  Body: { suffix }
   */
  async updateReferralSuffix(salesId, suffix, options = {}) {
    const payload = { suffix, ...options };
    const res = await api.post(`/sales/${salesId}/referral-suffix`, payload);
    const data = res?.data ?? res;
    return data?.data ?? data;
  },
};

export default salesApi;
