/**
 * IB Partners API Service
 * IB合作伙伴相关API接口
 */

import api from "./api";

const IB_INITIAL_LOG_SUB_MODULE = "ib_initial";
const IB_RISK_LOG_SUB_MODULE = "ib_risk";
const IB_FINAL_LOG_SUB_MODULE = "ib_final";

const withIbInitialLog = (data = {}) => ({
  ...data,
  logSubModuleKey: IB_INITIAL_LOG_SUB_MODULE,
});

const withIbRiskLog = (data = {}) => ({
  ...data,
  logSubModuleKey: IB_RISK_LOG_SUB_MODULE,
});

const withIbFinalLog = (data = {}) => ({
  ...data,
  logSubModuleKey: IB_FINAL_LOG_SUB_MODULE,
});

/**
 * 获取IB合作伙伴列表
 * @param {Object} params - 查询参数
 */
export const getIbPartners = (params = {}) => {
  return api.get("/ib-partners", { params });
};

/**
 * 获取单个IB合作伙伴详情
 * @param {number} id - IB合作伙伴ID
 */
export const getIbPartner = (id) => {
  return api.get(`/ib-partners/${id}`);
};

/**
 * 更新IB合作伙伴信息
 * @param {number} id - IB合作伙伴ID
 * @param {Object} data - 更新数据
 */
export const updateIbPartner = (id, data, extra = {}) => {
  return api.put(`/ib-partners/${id}`, { ...data, ...extra });
};

/**
 * 获取IB统计数据
 */
export const getStatistics = () => {
  return api.get("/ib-partners/statistics");
};

/**
 * 初审列表：仅 status = Pending Initial Review 的 IB，支持按姓名/邮箱搜索与分页
 * @param {Object} params - { page, per_page, search }
 */
export const getInitialReviewList = (params = {}) => {
  return api.get("/ib-partners/initial-review", { params });
};

/**
 * 风险审核列表：仅 status = Pending Risk Review 的 IB，支持按姓名/邮箱搜索与分页
 * @param {Object} params - { page, per_page, search }
 */
export const getRiskReviewList = (params = {}) => {
  return api.get("/ib-partners/risk-review", { params });
};

/**
 * 终审列表：仅 status = Pending Final Review 的 IB，支持按姓名/邮箱搜索与分页
 * @param {Object} params - { page, per_page, search }
 */
export const getFinalReviewList = (params = {}) => {
  return api.get("/ib-partners/final-review", { params });
};

/**
 * IB List：全部状态的 IB，支持按姓名/邮箱搜索与分页（Client 分组下 IB List 页）
 * @param {Object} params - { page, per_page, search }
 */
export const getAllStatusList = (params = {}) => {
  return api.get("/ib-partners/all-list", { params });
};

/**
 * 获取指定 IB 的网络关系树（与客户端 Your IB Network 同结构，供 Detail 关系网图）
 * @param {number} id - IB Partner ID
 * @param {Object} params - { max_depth }
 */
export const getNetwork = (id, params = {}) => {
  return api.get(`/ib-partners/${id}/network`, { params });
};

/**
 * 获取指定 IB 的网络统计（Total Network, Level 2/3 IBs, Direct Clients）
 * @param {number} id - IB Partner ID
 */
export const getNetworkStats = (id) => {
  return api.get(`/ib-partners/${id}/network-stats`);
};

/**
 * 提交初审：更新 reviewers、ibType、tierLevelId、规则，状态改为 pending_risk_review
 * @param {number} id - IB Partner ID
 * @param {Object} data - { initialReviewer, riskReviewer, finalReviewer, ibType, tierLevelId, ruleIds }
 */
export const submitInitialReview = (id, data) => {
  return api.post(
    `/ib-partners/${id}/submit-initial-review`,
    withIbInitialLog(data),
  );
};

/**
 * 拒绝（回退到 pending_initial_review）
 * @param {number} id - IB Partner ID
 */
export const rejectIbPartner = (id, extra = {}) => {
  return api.post(`/ib-partners/${id}/reject`, extra);
};

/**
 * 终审通过：状态改为 approved，生成 ibCode，记录 finalReviewer
 * POST /api/ib-partners/{id}/approve
 */
export const approveIbPartner = (id, extra = {}) => {
  return api.post(`/ib-partners/${id}/approve`, withIbFinalLog(extra));
};

/**
 * 可绑定 IB 列表：未与当前 IB 绑定且 TierLevel 小于当前 IB
 * GET /api/ib-partners/{id}/bindables?search=
 */
export const getBindables = (id, params = {}) => {
  return api.get(`/ib-partners/${id}/bindables`, { params });
};

/**
 * 绑定：子级 IB + 普通客户
 * POST /api/ib-partners/{id}/bind  Body: { childIds: number[], clientIds: number[] }
 */
export const bindIbPartners = (id, payload) => {
  const body = Array.isArray(payload)
    ? withIbFinalLog({ childIds: payload, clientIds: [] })
    : withIbFinalLog({
        childIds: payload?.childIds ?? [],
        clientIds: payload?.clientIds ?? [],
      });
  return api.post(`/ib-partners/${id}/bind`, body);
};

/**
 * 已绑定子级列表（直接下一级 IB + 绑定的普通客户），供 Unbind 弹窗
 * GET /api/ib-partners/{id}/bound-children
 */
export const getBoundChildren = (id) => {
  return api.get(`/ib-partners/${id}/bound-children`);
};

/**
 * 解绑：子级 IB + 普通客户
 * POST /api/ib-partners/{id}/unbind  Body: { childIds: number[], clientIds: number[] }
 */
export const unbindIbPartners = (id, payload) => {
  const body = Array.isArray(payload)
    ? withIbFinalLog({ childIds: payload, clientIds: [] })
    : withIbFinalLog({
        childIds: payload?.childIds ?? [],
        clientIds: payload?.clientIds ?? [],
      });
  return api.post(`/ib-partners/${id}/unbind`, body);
};

/**
 * 提交风险审核：riskReviewer、status = pending_final_review，可选多组别 groupIds（后端写入 ib_partner_trading_groups）
 * @param {number} id - IB Partner ID
 * @param {Object} data - { groupIds?: number[] }，兼容旧版单字段 groupId
 */
export const submitRiskReview = (id, data = {}) => {
  return api.post(`/ib-partners/${id}/submit-risk-review`, withIbRiskLog(data));
};

/**
 * 终审通过后：改 Tier 前的 IB↔IB 绑定阻碍（不含客户绑定）
 * GET /api/ib-partners/{id}/tier-change-blockers
 */
export const getTierChangeBlockers = (id) => {
  return api.get(`/ib-partners/${id}/tier-change-blockers`);
};

/**
 * 终审通过后修正 IB Type、Tier、规则、组别
 * POST /api/ib-partners/{id}/post-approval-update
 */
export const postApprovalUpdate = (id, data = {}) => {
  return api.post(
    `/ib-partners/${id}/post-approval-update`,
    withIbFinalLog(data),
  );
};

/**
 * 获取IB客户列表
 * @param {number} id - IB合作伙伴ID
 * @param {Object} params - 查询参数
 */
export const getIbClients = (id, params = {}) => {
  return api.get(`/ib-partners/${id}/clients`, { params });
};

/**
 * 获取IB佣金历史
 * @param {number} id - IB合作伙伴ID
 * @param {Object} params - 查询参数
 */
export const getIbCommissions = (id, params = {}) => {
  return api.get(`/ib-partners/${id}/commissions`, { params });
};

/**
 * 获取IB网络层级
 * @param {number} id - IB合作伙伴ID
 * @param {number} maxDepth - 最大深度
 */
export const getIbNetwork = (id, maxDepth = 5) => {
  return api.get(`/ib-partners/${id}/network`, {
    params: { max_depth: maxDepth },
  });
};

/**
 * 分配佣金规则给IB
 * @param {number} id - IB合作伙伴ID
 * @param {number} ruleId - 规则ID
 */
export const assignRule = (id, ruleId) => {
  return api.post(`/ib-partners/${id}/rules`, { ruleId });
};

/**
 * 移除IB的佣金规则
 * @param {number} id - IB合作伙伴ID
 * @param {number} ruleId - 规则ID
 */
export const removeRule = (id, ruleId) => {
  return api.delete(`/ib-partners/${id}/rules/${ruleId}`);
};

/**
 * 获取IB活动日志
 * @param {number} id - IB合作伙伴ID
 * @param {number} limit - 限制数量
 */
export const getActivities = (id, limit = 50) => {
  return api.get(`/ib-partners/${id}/activities`, { params: { limit } });
};

/**
 * 导出IB列表
 * @param {Array} selectedIds - 选中的IB ID数组
 * @param {string} format - 导出格式 (csv/excel)
 */
export const exportIbPartners = (selectedIds, format = "csv") => {
  return api.post("/ib-partners/export", { selectedIds, format });
};

/**
 * 获取IB合作伙伴的文档（从 ibApplicationDocumentAcknowledgements 表）
 * @param {number} id - IB合作伙伴ID
 */
export const getIbPartnerDocuments = (id) => {
  return api.get(`/ib-partners/${id}/documents`);
};

/**
 * 保存IB规则配置
 * @param {number} id - IB合作伙伴ID
 * @param {Object} data - 规则配置数据
 */
export const saveRules = (id, data) => {
  return api.post(`/ib-partners/${id}/rules/save`, data);
};

/**
 * 更新 IB Referral URL 后缀（仅后缀可编辑）
 * POST /api/ib-partners/{id}/referral-suffix  Body: { suffix }
 * @param {number} id - IB合作伙伴ID
 * @param {string} suffix - 新的后缀
 * @returns {Promise<{ ibReferralUrl: string, suffix: string }>}
 */
export async function updateReferralSuffix(id, suffix, extra = {}) {
  const res = await api.post(`/ib-partners/${id}/referral-suffix`, {
    suffix,
    ...extra,
  });
  return res?.data ?? res;
}

export default {
  getIbPartners,
  getIbPartner,
  updateIbPartner,
  getStatistics,
  getInitialReviewList,
  getRiskReviewList,
  getFinalReviewList,
  getAllStatusList,
  getNetwork,
  getNetworkStats,
  submitInitialReview,
  rejectIbPartner,
  approveIbPartner,
  getBindables,
  bindIbPartners,
  getBoundChildren,
  unbindIbPartners,
  submitRiskReview,
  getTierChangeBlockers,
  postApprovalUpdate,
  getIbClients,
  getIbCommissions,
  getIbNetwork,
  assignRule,
  removeRule,
  getActivities,
  exportIbPartners,
  getIbPartnerDocuments,
  saveRules,
  updateReferralSuffix,
};
