/**
 * Withdrawal Management API Service
 * 提款管理相关API接口
 */

import api from "./api";
import { getSubModuleKey } from "@/config/operationLogPages";

const WITHDRAWALS_LOG_SUB_MODULE = getSubModuleKey("page_withdrawals");

const withLogSubModule = (data = {}) => ({
  ...data,
  logSubModuleKey: data.logSubModuleKey || WITHDRAWALS_LOG_SUB_MODULE,
});

/**
 * 获取提款列表
 * @param {Object} params - 查询参数
 */
export const getWithdrawals = (params = {}) => {
  return api.get("/withdrawals", { params });
};

/**
 * 获取单个提款详情
 * @param {number} id - 提款ID
 */
export const getWithdrawal = (id) => {
  return api.get(`/withdrawals/${id}`);
};

/**
 * 获取提款统计
 * @param {Object} params - 查询参数 (startDate, endDate)
 */
export const getWithdrawalStatistics = (params = {}) => {
  return api.get("/withdrawals/statistics", { params });
};

/**
 * 批准提款
 * @param {number} id - 提款ID
 * @param {Object} data - 批准数据 (adminNotes)
 */
export const approveWithdrawal = (id, data = {}) => {
  return api.post(`/withdrawals/${id}/approve`, withLogSubModule(data));
};

/**
 * 拒绝提款
 * @param {number} id - 提款ID
 * @param {Object} data - { rejectionReasonId, rejectionNotes, customReason }
 */
export const rejectWithdrawal = (id, data) => {
  return api.post(`/withdrawals/${id}/reject`, withLogSubModule(data));
};

/**
 * 完成提款
 * @param {number} id - 提款ID
 * @param {Object} data - { transactionHash }
 */
export const completeWithdrawal = (id, data = {}) => {
  return api.post(`/withdrawals/${id}/complete`, data);
};

/**
 * 批量批准提款
 * @param {Object} data - { withdrawalIds: [], adminNotes: '' }
 */
export const bulkApproveWithdrawals = (data) => {
  return api.post("/withdrawals/bulk-approve", withLogSubModule(data));
};

/**
 * 获取提款状态历史
 * @param {number} id - 提款ID
 */
export const getWithdrawalHistory = (id) => {
  return api.get(`/withdrawals/${id}/history`);
};

/**
 * 请求额外文档
 * @param {number} id - 提款ID
 * @param {Object} data - { items: [], adminInstructions: '' }
 */
export const requestDocuments = (id, data) => {
  return api.post(
    `/withdrawals/${id}/request-documents`,
    withLogSubModule(data),
  );
};

/**
 * 添加标签到提款
 * @param {number} id - 提款ID
 * @param {Object} data - { tagName: '' }
 */
export const addTagToWithdrawal = (id, data) => {
  return api.post(`/withdrawals/${id}/tags`, withLogSubModule(data));
};

/**
 * 从提款移除标签
 * @param {number} withdrawalId - 提款ID
 * @param {number} tagId - 标签ID
 */
export const removeTagFromWithdrawal = (withdrawalId, tagId) => {
  return api.delete(`/withdrawals/${withdrawalId}/tags/${tagId}`, {
    data: withLogSubModule({}),
  });
};

/**
 * 批量添加标签
 * @param {Object} data - { withdrawalIds: [], tagName: '' }
 */
export const bulkAddTags = (data) => {
  return api.post("/withdrawals/bulk-add-tags", withLogSubModule(data));
};

/**
 * 导出提款数据
 * @param {Object} data - { withdrawalIds: [], format: 'csv' | 'excel' }
 */
export const exportWithdrawals = (data) => {
  return api.post("/withdrawals/export", withLogSubModule(data));
};

/**
 * 获取拒绝原因列表
 */
export const getRejectionReasons = (params = { scope: "withdrawal" }) => {
  return api.get("/withdrawals/rejection-reasons", { params });
};

/**
 * 获取提款标签列表
 */
export const getWithdrawalTags = () => {
  return api.get("/withdrawal-tags");
};

/**
 * 创建提款标签
 * @param {Object} data - { tagName: '', tagColor: '', textColor: '', description: '' }
 */
export const createWithdrawalTag = (data) => {
  return api.post("/withdrawal-tags", data);
};

/**
 * 删除提款标签
 * @param {number} id - 标签ID
 */
export const deleteWithdrawalTag = (id) => {
  return api.delete(`/withdrawal-tags/${id}`);
};

/**
 * 发送邮件给客户
 * @param {number} id - 提款ID
 * @param {Object} data - { email: '', subject: '', content: '' }
 */
export const sendEmail = (id, data) => {
  return api.post(`/withdrawals/${id}/send-email`, withLogSubModule(data));
};

/**
 * 获取提款的文档请求
 * @param {number} id - 提款ID
 */
export const getDocumentRequest = (id) => {
  return api.get(`/withdrawals/${id}/document-request`);
};

export default {
  getWithdrawals,
  getWithdrawal,
  getWithdrawalStatistics,
  approveWithdrawal,
  rejectWithdrawal,
  completeWithdrawal,
  bulkApproveWithdrawals,
  getWithdrawalHistory,
  requestDocuments,
  addTagToWithdrawal,
  removeTagFromWithdrawal,
  bulkAddTags,
  exportWithdrawals,
  getRejectionReasons,
  getWithdrawalTags,
  createWithdrawalTag,
  deleteWithdrawalTag,
  sendEmail,
  getDocumentRequest,
};
