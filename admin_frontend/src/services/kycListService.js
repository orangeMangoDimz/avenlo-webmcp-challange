import api from "./api";
import { getSubModuleKey } from "@/config/operationLogPages";

const KYC_LIST_LOG_SUB_MODULE = getSubModuleKey("page_kyc_list");

/**
 * KYC提交管理服务
 */
export const kycSubmissionService = {
  /**
   * 获取KYC提交列表
   * @param {object} params - 查询参数
   */
  async getList(params = {}) {
    return await api.get("/kyc-submissions", { params });
  },

  /**
   * 获取待审核KYC列表
   * @param {number} limit - 限制数量
   */
  async getPending(limit = 50) {
    return await api.get("/kyc-submissions/pending", { params: { limit } });
  },

  /**
   * 获取KYC统计信息
   */
  async getStatistics() {
    return await api.get("/kyc-submissions/statistics");
  },

  /**
   * 获取KYC提交详情
   * @param {number} id - 提交ID
   */
  async getDetail(id) {
    return await api.get(`/kyc-submissions/${id}`);
  },

  /**
   * 审批KYC提交
   * @param {number} id - 提交ID
   * @param {object} data - 审批数据
   */
  async approve(id, data = {}) {
    return await api.post(`/kyc-submissions/${id}/approve`, {
      logSubModuleKey: KYC_LIST_LOG_SUB_MODULE,
      ...data,
    });
  },

  /**
   * 拒绝KYC提交
   * @param {number} id - 提交ID
   * @param {object} data - 拒绝数据
   */
  async reject(id, data) {
    return await api.post(`/kyc-submissions/${id}/reject`, {
      logSubModuleKey: KYC_LIST_LOG_SUB_MODULE,
      ...data,
    });
  },

  /**
   * 需要更多文档
   * @param {number} id - 提交ID
   * @param {object} data - 请求数据
   */
  async needDocs(id, data) {
    return await api.post(`/kyc-submissions/${id}/need-docs`, {
      logSubModuleKey: KYC_LIST_LOG_SUB_MODULE,
      ...data,
    });
  },

  /**
   * 分配审核员
   * @param {number} id - 提交ID
   * @param {object} data - 分配数据
   */
  async assign(id, data) {
    return await api.post(`/kyc-submissions/${id}/assign`, data);
  },

  /**
   * 获取活动日志
   * @param {number} id - 提交ID
   */
  async getActivities(id) {
    return await api.get(`/kyc-submissions/${id}/activities`);
  },

  /**
   * 批量审批KYC提交
   * @param {array} ids - 提交ID数组
   * @param {object} data - 审批数据
   */
  async bulkApprove(ids, data = {}) {
    return await api.post("/kyc-submissions/bulk-approve", {
      submissionIds: ids,
      logSubModuleKey: KYC_LIST_LOG_SUB_MODULE,
      ...data,
    });
  },

  /**
   * 批量拒绝KYC提交
   * @param {array} ids - 提交ID数组
   * @param {object} data - 拒绝数据
   */
  async bulkReject(ids, data) {
    return await api.post("/kyc-submissions/bulk-reject", {
      submissionIds: ids,
      ...data,
    });
  },

  /**
   * 批量分配审核员
   * @param {array} ids - 提交ID数组
   * @param {object} data - 分配数据
   */
  async bulkAssign(ids, data) {
    return await api.post("/kyc-submissions/bulk-assign", {
      submissionIds: ids,
      logSubModuleKey: KYC_LIST_LOG_SUB_MODULE,
      ...data,
    });
  },

  /**
   * 导出KYC数据
   * @param {string} format - 导出格式 (csv, excel)
   * @param {object} params - 导出参数
   */
  async export(format, params = {}) {
    return await api.get(`/kyc-submissions/export/${format}`, {
      params,
      responseType: "blob",
    });
  },

  /**
   * 获取KYC提交进度
   * @param {number} id - 提交ID
   */
  async getProgress(id) {
    return await api.get(`/kyc-submissions/${id}/progress`);
  },

  /**
   * 获取KYC提交答案
   * @param {number} id - 提交ID
   */
  async getAnswers(id) {
    return await api.get(`/kyc-submissions/${id}/answers`);
  },

  /**
   * 获取KYC提交文档
   * @param {number} id - 提交ID
   */
  async getDocuments(id) {
    return await api.get(`/kyc-submissions/${id}/documents`);
  },

  /**
   * 获取KYC提交时间线
   * @param {number} id - 提交ID
   */
  async getTimeline(id) {
    return await api.get(`/kyc-submissions/${id}/timeline`);
  },

  /**
   * 获取重新提交请求详情
   * @param {number} id - 提交ID
   */
  async getResubmitRequest(id) {
    return await api.get(`/kyc-submissions/${id}/resubmit-request`);
  },

  /**
   * 获取重新提交的答案
   * @param {number} id - 提交ID
   */
  async getResubmitAnswers(id) {
    return await api.get(`/kyc-submissions/${id}/resubmit-answers`);
  },
};

/**
 * KYC审核员管理服务
 */
export const kycReviewerService = {
  /**
   * 获取审核员列表
   */
  async getReviewers() {
    return await api.get("/kyc-reviewers");
  },

  /**
   * 获取审核员统计
   * @param {number} reviewerId - 审核员ID
   */
  async getReviewerStats(reviewerId) {
    return await api.get(`/kyc-reviewers/${reviewerId}/statistics`);
  },
};

export default {
  kycSubmissionService,
  kycReviewerService,
};
