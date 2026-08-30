import clientApi from "./clientApi";

/**
 * IB申请服务
 */
const ibApplicationApi = {
  /**
   * 创建IB申请（客户端同意成为IB）
   * @param {Object} data - 申请数据
   */
  async createApplication(data) {
    return await clientApi.post(
      "/index.php?path=api/ib-applications/agree",
      data,
    );
  },

  /**
   * 获取当前用户的IB申请状态
   */
  async getMyStatus() {
    return await clientApi.get("index.php?path=api/client/auth/ib-status");
  },

  /**
   * 获取IB申请详情
   * @param {number} applicationId - 申请ID
   */
  async getApplication(applicationId) {
    return await clientApi.get(
      `/index.php?path=api/ib-applications/${applicationId}`,
    );
  },

  /**
   * 获取IB申请活动日志
   * @param {number} applicationId - 申请ID
   */
  async getActivities(applicationId) {
    return await clientApi.get(
      `/index.php?path=api/ib-applications/${applicationId}/activities`,
    );
  },
};

export default ibApplicationApi;
