/**
 * Internal Transfer Management API Service
 * 内部转账管理相关API接口
 */

import api from "./api";
import { getSubModuleKey } from "@/config/operationLogPages";

const INTERNAL_TRANSFERS_LOG_SUB_MODULE = getSubModuleKey(
  "page_internaltransfers",
);

const withLogSubModule = (data = {}) => ({
  ...data,
  logSubModuleKey: data.logSubModuleKey || INTERNAL_TRANSFERS_LOG_SUB_MODULE,
});

/**
 * 获取内部转账列表
 * @param {Object} params - 查询参数
 */
export const getInternalTransfers = (params = {}) => {
  return api.get("/internal-transfers", { params });
};

/**
 * 获取单个内部转账详情
 * @param {number} id - 转账ID
 */
export const getInternalTransfer = (id) => {
  return api.get(`/internal-transfers/${id}`);
};

/**
 * 批准内部转账
 * @param {number} id - 转账ID
 * @param {Object} data - 批准数据 (adminNotes)
 */
export const approveInternalTransfer = (id, data = {}) => {
  return api.post(`/internal-transfers/${id}/approve`, withLogSubModule(data));
};

/**
 * 拒绝内部转账
 * @param {number} id - 转账ID
 * @param {Object} data - { rejectionReasonId, rejectionNotes, customReason }
 */
export const rejectInternalTransfer = (id, data) => {
  return api.post(`/internal-transfers/${id}/reject`, withLogSubModule(data));
};

/**
 * 获取内部转账拒绝原因列表
 * @param {Object} params - 查询参数
 */
export const getRejectionReasons = (
  params = { scope: "internal_transfer" },
) => {
  return api.get("/internal-transfers/rejection-reasons", { params });
};

/**
 * 获取内部转账状态历史
 * @param {number} id - 转账ID
 */
export const getInternalTransferHistory = (id) => {
  return api.get(`/internal-transfers/${id}/history`);
};

/**
 * 获取内部转账备注列表
 * @param {number} id - 转账ID
 */
export const getInternalTransferNotes = (id) => {
  return api.get(`/internal-transfers/${id}/notes`);
};

/**
 * 添加内部转账备注
 * @param {number} id - 转账ID
 * @param {Object} data - { noteContent: string }
 */
export const addInternalTransferNote = (id, data) => {
  return api.post(`/internal-transfers/${id}/notes`, withLogSubModule(data));
};

/**
 * 添加标签到内部转账
 * @param {number} id - 转账ID
 * @param {Object} data - { tagName: string }
 */
export const addInternalTransferTag = (id, data) => {
  return api.post(`/internal-transfers/${id}/tags`, withLogSubModule(data));
};

/**
 * 移除内部转账的标签
 * @param {number} id - 转账ID
 * @param {number} tagId - 标签ID
 */
export const removeInternalTransferTag = (id, tagId) => {
  return api.delete(`/internal-transfers/${id}/tags/${tagId}`, {
    data: withLogSubModule({}),
  });
};

/**
 * 发送邮件给客户
 * @param {number} id - 转账ID
 * @param {Object} data - { email: '', subject: '', content: '' }
 */
export const sendEmail = (id, data) => {
  return api.post(
    `/internal-transfers/${id}/send-email`,
    withLogSubModule(data),
  );
};

/**
 * 获取内部转账统计
 * @param {Object} params - 查询参数 (startDate, endDate)
 */
export const getInternalTransferStatistics = (params = {}) => {
  return api.get("/internal-transfers/statistics", { params });
};

/**
 * 批量批准内部转账
 * @param {Object} data - { transferIds: [], adminNotes: '' }
 */
export const bulkApproveInternalTransfers = (data) => {
  return api.post("/internal-transfers/bulk-approve", withLogSubModule(data));
};

/**
 * 批量添加标签
 * @param {Object} data - { transferIds: [], tagName: '' }
 */
export const bulkAddTags = (data) => {
  return api.post("/internal-transfers/bulk-add-tags", withLogSubModule(data));
};

/**
 * 获取交易搜索标签
 * @param {string} type - 'deposit' | 'withdrawal' | 'internal_transfer' | 'both'
 */
export const getSearchTags = (type = "internal_transfer") => {
  return api.get("/internal-transfers/search-tags", { params: { type } });
};

/**
 * 创建搜索标签
 * @param {Object} data - { tagName: '', searchKeywords: '', transactionType: 'internal_transfer' }
 */
export const createSearchTag = (data) => {
  return api.post(
    "/internal-transfers/create-search-tags",
    withLogSubModule(data),
  );
};

/**
 * 删除搜索标签
 * @param {number} id - 标签ID
 */
export const deleteSearchTag = (id, extra = {}) => {
  return api.delete(`/internal-transfers/delete-search-tags/${id}`, {
    data: withLogSubModule(extra),
  });
};

/**
 * 导出内部转账数据
 * @param {Object} data - { transferIds: [], format: 'csv' | 'excel' }
 */
export const exportInternalTransfers = (data) => {
  return api.post("/internal-transfers/export", withLogSubModule(data));
};
