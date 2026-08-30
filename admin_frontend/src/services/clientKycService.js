import api from "./api";

/**
 * 客户端 KYC 服务
 */
export const clientKycService = {
  /**
   * 获取客户端可用的 KYC 模板
   * 根据客户的国家自动选择合适的模板
   */
  async getAvailableTemplate() {
    return await api.get("/kyc/client-template");
  },

  /**
   * 获取 KYC 模板详情（包含所有问题）
   * @param {number} templateId - 模板ID
   */
  async getTemplateDetails(templateId) {
    return await api.get(`/kyc/templates/${templateId}/details`);
  },

  /**
   * 创建新的 KYC 提交
   * @param {number} templateId - 模板ID
   */
  async createSubmission(templateId) {
    return await api.post("/kyc/submissions", { templateId });
  },

  /**
   * 保存 KYC 答案
   * @param {number} submissionId - 提交ID
   * @param {object} answers - 答案数据
   */
  async saveAnswers(submissionId, answers) {
    return await api.put(`/kyc/submissions/${submissionId}/answers`, {
      answers,
    });
  },

  /**
   * 上传 KYC 文件
   * @param {number} submissionId - 提交ID
   * @param {number} questionId - 问题ID
   * @param {File} file - 文件对象
   */
  async uploadFile(submissionId, questionId, file) {
    const formData = new FormData();
    formData.append("file", file);
    formData.append("questionId", questionId);

    return await api.post(`/kyc/submissions/${submissionId}/upload`, formData, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });
  },

  /**
   * 签署文档
   * @param {number} submissionId - 提交ID
   * @param {array} documentIds - 文档ID数组
   */
  async signDocuments(submissionId, documentIds) {
    return await api.post(`/kyc/submissions/${submissionId}/sign-documents`, {
      documentIds,
    });
  },

  /**
   * 提交 KYC 申请（最终提交）
   * @param {number} submissionId - 提交ID
   */
  async submitApplication(submissionId) {
    return await api.post(`/kyc/submissions/${submissionId}/submit`);
  },

  /**
   * 获取客户的 KYC 提交状态
   */
  async getMySubmission() {
    return await api.get("/kyc/my-submission");
  },

  /**
   * 获取 KYC 提交详情
   * @param {number} submissionId - 提交ID
   */
  async getSubmissionDetails(submissionId) {
    return await api.get(`/kyc/submissions/${submissionId}`);
  },
};

export default clientKycService;
