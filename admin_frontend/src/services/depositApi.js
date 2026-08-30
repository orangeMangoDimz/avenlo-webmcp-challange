/**
 * Deposit Management API Service
 * 存款管理相关API接口
 */

import api from "./api";
import { getSubModuleKey } from "@/config/operationLogPages";

const DEPOSITS_LOG_SUB_MODULE = getSubModuleKey("page_deposits");

const withLogSubModule = (data = {}) => ({
  ...data,
  logSubModuleKey: data.logSubModuleKey || DEPOSITS_LOG_SUB_MODULE,
});

/**
 * 获取存款列表
 * @param {Object} params - 查询参数
 */
export const getDeposits = (params = {}) => {
  return api.get("/deposits", { params });
};

/**
 * 获取单个存款详情
 * @param {number} id - 存款ID
 */
export const getDeposit = (id) => {
  return api.get(`/deposits/${id}`);
};

/**
 * 获取存款统计
 * @param {Object} params - 查询参数 (startDate, endDate)
 */
export const getDepositStatistics = (params = {}) => {
  return api.get("/deposits/statistics", { params });
};

/**
 * 批准存款
 * @param {number} id - 存款ID
 * @param {Object} data - 批准数据 (adminNotes)
 */
export const approveDeposit = (id, data = {}) => {
  return api.post(`/deposits/${id}/approve`, withLogSubModule(data));
};

/**
 * 批量批准存款
 * @param {Object} data - { depositIds: [], adminNotes: '' }
 */
export const bulkApproveDeposits = (data) => {
  return api.post("/deposits/bulk-approve", withLogSubModule(data));
};

/**
 * 拒绝存款
 * @param {number} id - 存款ID
 * @param {Object} data - { rejectionReasonId, rejectionNotes, customReason }
 */
export const rejectDeposit = (id, data) => {
  return api.post(`/deposits/${id}/reject`, withLogSubModule(data));
};

/**
 * 获取存款拒绝原因列表
 * @param {Object} params - 查询参数
 */
export const getRejectionReasons = (params = { scope: "deposit" }) => {
  return api.get("/deposits/rejection-reasons", { params });
};

/**
 * 获取存款状态历史
 * @param {number} id - 存款ID
 */
export const getDepositHistory = (id) => {
  return api.get(`/deposits/${id}/history`);
};

/**
 * 获取存款备注列表
 * @param {number} id - 存款ID
 */
export const getDepositNotes = (id) => {
  return api.get(`/deposits/${id}/notes`);
};

/**
 * 添加存款备注
 * @param {number} id - 存款ID
 * @param {Object} data - { noteContent: '' }
 */
export const addDepositNote = (id, data) => {
  return api.post(`/deposits/${id}/notes`, withLogSubModule(data));
};

/**
 * 添加标签到存款
 * @param {number} id - 存款ID
 * @param {Object} data - { tagName: '' }
 */
export const addTagToDeposit = (id, data) => {
  return api.post(`/deposits/${id}/tags`, withLogSubModule(data));
};

/**
 * 从存款移除标签
 * @param {number} depositId - 存款ID
 * @param {number} tagId - 标签ID
 */
export const removeTagFromDeposit = (depositId, tagId) => {
  return api.delete(`/deposits/${depositId}/tags/${tagId}`, {
    data: withLogSubModule({}),
  });
};

/**
 * 批量添加标签
 * @param {Object} data - { depositIds: [], tagName: '' }
 */
export const bulkAddTags = (data) => {
  return api.post("/deposits/bulk-add-tags", withLogSubModule(data));
};

/**
 * 导出存款数据
 * @param {Object} data - { depositIds: [], format: 'csv' | 'excel' }
 */
export const exportDeposits = (data) => {
  return api.post("/deposits/export", withLogSubModule(data));
};

/**
 * 获取存款标签列表
 */
export const getDepositTags = () => {
  return api.get("/deposit-tags");
};

/**
 * 创建存款标签
 * @param {Object} data - { tagName: '', tagColor: '', textColor: '', description: '' }
 */
export const createDepositTag = (data) => {
  return api.post("/deposit-tags", data);
};

/**
 * 删除存款标签
 * @param {number} id - 标签ID
 */
export const deleteDepositTag = (id) => {
  return api.delete(`/deposit-tags/${id}`);
};

/**
 * 获取交易搜索标签
 * @param {string} type - 'deposit' | 'withdrawal' | 'both'
 */
export const getSearchTags = (type = "deposit") => {
  return api.get("/transaction-search-tags", { params: { type } });
};

/**
 * 创建搜索标签
 * @param {Object} data - { tagName: '', searchKeywords: '', transactionType: 'deposit' }
 */
export const createSearchTag = (data) => {
  return api.post("/transaction-search-tags", withLogSubModule(data));
};

/**
 * 删除搜索标签
 * @param {number} id - 标签ID
 */
export const deleteSearchTag = (id, extra = {}) => {
  return api.delete(`/transaction-search-tags/${id}`, {
    data: withLogSubModule(extra),
  });
};

/**
 * 获取支付方式列表
 */
export const getPaymentMethods = () => {
  return api.get("/payment-methods");
};

/**
 * 发送邮件给客户
 * @param {number} id - 存款ID
 * @param {Object} data - { email: '', subject: '', content: '' }
 */
export const sendEmail = (id, data) => {
  return api.post(`/deposits/${id}/send-email`, withLogSubModule(data));
};

export default {
  getDeposits,
  getDeposit,
  getDepositStatistics,
  approveDeposit,
  bulkApproveDeposits,
  rejectDeposit,
  getRejectionReasons,
  getDepositHistory,
  getDepositNotes,
  addDepositNote,
  addTagToDeposit,
  removeTagFromDeposit,
  bulkAddTags,
  exportDeposits,
  getDepositTags,
  createDepositTag,
  deleteDepositTag,
  getSearchTags,
  createSearchTag,
  deleteSearchTag,
  getPaymentMethods,
  sendEmail,
};
