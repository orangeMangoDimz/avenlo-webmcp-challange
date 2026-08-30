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
   * 删除指定问题的答案
   * @param {number} submissionId - 提交ID
   * @param {array} questionIds - 问题ID数组
   */
  async deleteAnswers(submissionId, questionIds) {
    return await api.delete(`/kyc/submissions/${submissionId}/answers`, {
      data: { questionIds },
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
   * 第三方 KYC 启动信息（access token / levelName 等）
   * 当模板开启了 isThirdPartyEnabled 时使用
   */
  async getExternalLaunch() {
    return await api.get("/kyc/external-launch");
  },

  /**
   * 获取 KYC 提交详情
   * @param {number} submissionId - 提交ID
   */
  async getSubmissionDetails(submissionId) {
    return await api.get(`/kyc/submissions/${submissionId}`);
  },

  /**
   * 获取客户当前KYC状态和进度
   */
  async getClientStatus() {
    return await api.get("/kyc/client-status");
  },

  /**
   * 评估KYC规则
   * @param {number} submissionId - 提交ID
   */
  async evaluateRules(submissionId) {
    return await api.post(`/kyc/submissions/${submissionId}/evaluate-rules`);
  },

  /**
   * 获取模板的条件规则
   * @param {number} templateId - 模板ID
   */
  async getTemplateRules(templateId) {
    return await api.get(`/kyc-rules?template_id=${templateId}`);
  },

  /**
   * 获取下一个应该回答的问题（考虑规则跳转）
   * @param {number} submissionId - 提交ID
   */
  async getNextQuestion(submissionId) {
    return await api.get(`/kyc/submissions/${submissionId}/next-question`);
  },

  /**
   * 获取提交进度信息
   * @param {number} submissionId - 提交ID
   */
  async getSubmissionProgress(submissionId) {
    return await api.get(`/kyc/submissions/${submissionId}/progress`);
  },

  /**
   * 开始KYC验证流程（创建草稿submission）
   */
  async startKycProcess() {
    return await api.post("/kyc/client-start");
  },

  /**
   * 重新开始KYC流程（被拒绝后）
   */
  async restartKycProcess() {
    return await api.post("/kyc/client-restart");
  },

  /**
   * 获取KYC状态消息
   */
  async getStatusMessage(statusType) {
    return await api.get(`/kyc-settings/client-status-message/${statusType}`);
  },

  /**
   * 获取重新提交请求信息
   * @param {number} submissionId - 提交ID
   */
  async getResubmitRequest(submissionId) {
    return await api.get(`/kyc/submissions/${submissionId}/resubmit-request`);
  },

  /**
   * 提交重新提交的答案
   * @param {number} submissionId - 提交ID
   * @param {array} answers - 答案数组
   */
  async submitResubmitAnswers(submissionId, answers) {
    return await api.post(`/kyc/submissions/${submissionId}/resubmit-answers`, {
      answers,
    });
  },

  /**
   * 上传 resubmit 文件
   * @param {number} submissionId - 提交ID
   * @param {number|string} itemIndex - 项目索引
   * @param {File} file - 文件对象
   */
  async uploadResubmitFile(submissionId, itemIndex, file) {
    const formData = new FormData();
    formData.append("file", file);
    formData.append("itemIndex", itemIndex);

    return await api.post(
      `/kyc/submissions/${submissionId}/resubmit-upload`,
      formData,
      {
        headers: {
          "Content-Type": "multipart/form-data",
        },
      },
    );
  },

  /**
   * 获取客户已签名的KYC文档
   */
  async getMySignedDocuments() {
    return await api.get("/kyc/my-signed-documents");
  },
};

export default clientKycService;
