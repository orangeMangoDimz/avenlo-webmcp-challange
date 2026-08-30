import clientApi from "./clientApi";

/**
 * 客户端提款服务
 */
const withdrawalService = {
  /**
   * 获取提款的文档请求
   * @param {number} withdrawalId - 提款ID
   */
  async getDocumentRequest(withdrawalId) {
    return await clientApi.get(
      `/index.php?path=api/withdrawals/${withdrawalId}/document-request`,
    );
  },

  /**
   * 上传文件
   * @param {number} withdrawalId - 提款ID
   * @param {number} itemId - 项目ID
   * @param {File} file - 文件对象
   */
  async uploadDocument(withdrawalId, itemId, file) {
    const formData = new FormData();
    formData.append("file", file);
    formData.append("itemId", itemId);
    return await clientApi.post(
      `/index.php?path=api/withdrawals/${withdrawalId}/upload-document`,
      formData,
      {
        headers: {
          "Content-Type": "multipart/form-data",
        },
      },
    );
  },

  /**
   * 提交答案和文档
   * @param {number} withdrawalId - 提款ID
   * @param {Object} answers - 答案数据
   */
  async submitDocuments(withdrawalId, answers) {
    return await clientApi.post(
      `/index.php?path=api/withdrawals/${withdrawalId}/submit-documents`,
      {
        answers,
      },
    );
  },

  /**
   * 获取我的提款列表
   */
  async getMyWithdrawals() {
    return await clientApi.get(
      "/index.php?path=api/withdrawals/my-withdrawals",
    );
  },
};

export default withdrawalService;
