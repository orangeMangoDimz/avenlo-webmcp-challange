/**
 * IB Applications API Service
 * IB申请相关API接口
 */

import api from "./api";

const IB_INITIAL_LOG_SUB_MODULE = "ib_initial";

/**
 * 获取IB申请列表
 * @param {Object} params - 查询参数
 */
export const getApplications = (params = {}) => {
  return api.get("/ib-applications", { params });
};

/**
 * 获取单个申请详情
 * @param {number} id - 申请ID
 */
export const getApplication = (id) => {
  return api.get(`/ib-applications/${id}`);
};

/**
 * 创建IB申请
 * @param {Object} data - 申请数据
 */
export const createApplication = (data) => {
  return api.post("/ib-applications", data);
};

/**
 * Create an additional IB for the client behind an approved IB partner.
 * The backend keeps the legacy request key name as clientId, but the value is an IB partner id.
 * @param {number} ibPartnerId - Existing approved IB partner ID
 */
export const createMultipleIbFromExistingIb = (ibPartnerId) => {
  return api.post("/ib-applications/agree-multiple", {
    clientId: ibPartnerId,
    logSubModuleKey: IB_INITIAL_LOG_SUB_MODULE,
  });
};

/**
 * 更新申请信息
 * @param {number} id - 申请ID
 * @param {Object} data - 更新数据
 */
export const updateApplication = (id, data) => {
  return api.put(`/ib-applications/${id}`, data);
};

/**
 * 获取申请统计数据
 */
export const getStatistics = () => {
  return api.get("/ib-applications/statistics");
};

/**
 * 分配审核人
 * @param {number} id - 申请ID
 * @param {number} reviewerId - 审核人ID
 */
export const assignReviewer = (id, reviewerId) => {
  return api.post(`/ib-applications/${id}/assign-reviewer`, { reviewerId });
};

/**
 * 批准申请
 * @param {number} id - 申请ID
 * @param {number} tierLevelId - 层级ID
 */
export const approveApplication = (id, tierLevelId) => {
  return api.post(`/ib-applications/${id}/approve`, { tierLevelId });
};

/**
 * 拒绝申请
 * @param {number} id - 申请ID
 * @param {string} rejectionReason - 拒绝原因
 */
export const rejectApplication = (id, rejectionReason) => {
  return api.post(`/ib-applications/${id}/reject`, { rejectionReason });
};

/**
 * 请求更多信息
 * @param {number} id - 申请ID
 * @param {string} infoRequest - 请求的信息
 */
export const requestMoreInfo = (id, infoRequest) => {
  return api.post(`/ib-applications/${id}/request-info`, { infoRequest });
};

/**
 * 分配层级
 * @param {number} id - 申请ID
 * @param {number} tierLevelId - 层级ID
 */
export const assignTier = (id, tierLevelId) => {
  return api.post(`/ib-applications/${id}/assign-tier`, { tierLevelId });
};

/**
 * 分配规则（预分配）
 * @param {number} id - 申请ID
 * @param {number} ruleId - 规则ID
 */
export const assignRule = (id, ruleId) => {
  return api.post(`/ib-applications/${id}/assign-rule`, { ruleId });
};

/**
 * 移除预分配规则
 * @param {number} id - 申请ID
 * @param {number} ruleId - 规则ID
 */
export const removeRule = (id, ruleId) => {
  return api.delete(`/ib-applications/${id}/rules/${ruleId}`);
};

/**
 * 批量分配审核人（分别分配操作员和审批人）
 * @param {Array} applicationIds - 申请ID数组
 * @param {number} operatorId - 操作员ID（销售权限）
 * @param {number} auditorId - 审批人ID（风控/运营权限）
 */
export const bulkAssignReviewer = (applicationIds, operatorId, auditorId) => {
  return api.post("/ib-applications/bulk-assign-reviewer", {
    applicationIds,
    operatorId,
    auditorId,
  });
};

/**
 * 保存个性化规则配置
 * @param {number} id - 申请ID
 * @param {Object} rulesData - 规则数据
 */
export const saveCustomRules = (id, rulesData) => {
  return api.post(`/ib-applications/${id}/custom-rules`, { rules: rulesData });
};

/**
 * 获取个性化规则配置
 * @param {number} id - 申请ID
 */
export const getCustomRules = (id) => {
  return api.get(`/ib-applications/${id}/custom-rules`);
};

/**
 * 批量批准申请
 * @param {Array} applicationIds - 申请ID数组
 * @param {number} tierLevelId - 层级ID
 * @param {Array} ruleIds - 规则ID数组（可选）
 */
export const bulkApprove = (applicationIds, tierLevelId, ruleIds = []) => {
  return api.post("/ib-applications/bulk-approve", {
    applicationIds,
    tierLevelId,
    ruleIds,
  });
};

/**
 * 导出申请列表
 * @param {Array} selectedIds - 选中的申请ID数组
 * @param {string} format - 导出格式 (csv/excel)
 */
export const exportApplications = (selectedIds, format = "csv") => {
  return api.post("/ib-applications/export", { selectedIds, format });
};

/**
 * 获取申请活动日志
 * @param {number} id - 申请ID
 * @param {number} limit - 限制数量
 */
export const getActivities = (id, limit = 50) => {
  return api.get(`/ib-applications/${id}/activities`, { params: { limit } });
};

/**
 * 获取IB申请的KYC数据
 * @param {number} id - 申请ID
 */
export const getKycData = (id) => {
  return api.get(`/ib-applications/${id}/kyc-data`);
};

export default {
  getApplications,
  getApplication,
  createApplication,
  createMultipleIbFromExistingIb,
  updateApplication,
  getStatistics,
  assignReviewer,
  approveApplication,
  rejectApplication,
  requestMoreInfo,
  assignTier,
  assignRule,
  removeRule,
  bulkAssignReviewer,
  bulkApprove,
  exportApplications,
  getActivities,
  getKycData,
  saveCustomRules,
  getCustomRules,
};
