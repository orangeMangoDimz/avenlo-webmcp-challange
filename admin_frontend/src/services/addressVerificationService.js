import api from "./api";
import { getSubModuleKey } from "@/config/operationLogPages";

const ADDRESS_VERIFICATION_LOG_SUB_MODULE = getSubModuleKey(
  "page_addressverification",
);

const withLogSubModule = (data = {}) => ({
  ...data,
  logSubModuleKey: data.logSubModuleKey || ADDRESS_VERIFICATION_LOG_SUB_MODULE,
});

/**
 * Address Verification 提交管理服务
 */
export const addressVerificationSubmissionService = {
  /**
   * 获取 Address Verification 提交列表
   * @param {object} params - 查询参数
   */
  async getList(params = {}) {
    return await api.get("/withdrawal-submissions", { params });
  },

  /**
   * 获取待审核 Address Verification 列表
   * @param {number} limit - 限制数量
   */
  async getPending(limit = 50) {
    return await api.get("/withdrawal-submissions/pending", {
      params: { limit },
    });
  },

  /**
   * 获取 Address Verification 统计信息
   */
  async getStatistics() {
    return await api.get("/withdrawal-submissions/statistics");
  },

  /**
   * 获取 Address Verification 提交详情
   * @param {number} id - 提交ID
   */
  async getDetail(id) {
    return await api.get(`/withdrawal-submissions/${id}`);
  },

  /**
   * 审批 Address Verification 提交
   * @param {number} id - 提交ID
   * @param {object} data - 审批数据
   */
  async approve(id, data = {}) {
    return await api.post(
      `/withdrawal-submissions/${id}/approve`,
      withLogSubModule(data),
    );
  },

  /**
   * 拒绝 Address Verification 提交
   * @param {number} id - 提交ID
   * @param {object} data - 拒绝数据
   */
  async reject(id, data) {
    return await api.post(
      `/withdrawal-submissions/${id}/reject`,
      withLogSubModule(data),
    );
  },

  /**
   * 需要更多文档
   * @param {number} id - 提交ID
   * @param {object} data - 请求数据
   */
  async needDocs(id, data) {
    return await api.post(
      `/withdrawal-submissions/${id}/need-docs`,
      withLogSubModule(data),
    );
  },

  /**
   * 分配审核员
   * @param {number} id - 提交ID
   * @param {object} data - 分配数据
   */
  async assign(id, data) {
    return await api.post(
      `/withdrawal-submissions/${id}/assign`,
      withLogSubModule(data),
    );
  },

  /**
   * 获取活动日志
   * @param {number} id - 提交ID
   */
  async getActivities(id) {
    return await api.get(`/withdrawal-submissions/${id}/activities`);
  },

  /**
   * 批量审批 Address Verification 提交
   * @param {array} ids - 提交ID数组
   * @param {object} data - 审批数据
   */
  async bulkApprove(ids, data = {}) {
    return await Promise.all(ids.map((id) => this.approve(id, data)));
  },

  /**
   * 批量拒绝 Address Verification 提交
   * @param {array} ids - 提交ID数组
   * @param {object} data - 拒绝数据
   */
  async bulkReject(ids, data) {
    return await Promise.all(ids.map((id) => this.reject(id, data)));
  },

  /**
   * 批量分配审核员
   * @param {array} ids - 提交ID数组
   * @param {object} data - 分配数据
   */
  async bulkAssign(ids, data) {
    return await Promise.all(ids.map((id) => this.assign(id, data)));
  },

  /**
   * 导出 Address Verification 数据
   * @param {string} format - 导出格式 (csv, excel)
   * @param {object} params - 导出参数
   */
  async export(format, params = {}) {
    return await api.get("/withdrawal-submissions", { params });
  },

  /**
   * 获取 Address Verification 提交进度
   * @param {number} id - 提交ID
   */
  async getProgress(id) {
    return await api.get(`/withdrawal-submissions/${id}`);
  },

  /**
   * 获取 Address Verification 提交答案
   * @param {number} id - 提交ID
   */
  async getAnswers(id) {
    return await api.get(`/withdrawal-submissions/${id}`);
  },

  /**
   * 获取 Address Verification 提交文档
   * @param {number} id - 提交ID
   */
  async getDocuments(id) {
    return await api.get(`/withdrawal-submissions/${id}`);
  },

  /**
   * 获取 Address Verification 提交时间线
   * @param {number} id - 提交ID
   */
  async getTimeline(id) {
    return await api.get(`/withdrawal-submissions/${id}`);
  },

  /**
   * 获取重新提交请求详情
   * @param {number} id - 提交ID
   */
  async getResubmitRequest(id) {
    return await api.get(`/withdrawal-submissions/${id}/resubmit-request`);
  },

  /**
   * 获取重新提交的答案
   * @param {number} id - 提交ID
   */
  async getResubmitAnswers(id) {
    return await api.get(`/withdrawal-submissions/${id}/resubmit-answers`);
  },
};

/**
 * Address Verification 审核员管理服务
 */
export const addressVerificationReviewerService = {
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
  addressVerificationSubmissionService,
  addressVerificationReviewerService,
};
