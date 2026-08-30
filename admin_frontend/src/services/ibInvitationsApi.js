/**
 * IB Invitations API Service
 * IB邀请相关API接口
 */

import api from "./api";

const IB_INITIAL_LOG_SUB_MODULE = "ib_initial";

const withIbInitialLog = (data = {}) => ({
  ...data,
  logSubModuleKey: IB_INITIAL_LOG_SUB_MODULE,
});

/**
 * 获取IB邀请列表
 * @param {Object} params - 查询参数
 */
export const getInvitations = (params = {}) => {
  return api.get("/ib-invitations", { params });
};

/**
 * 创建IB邀请
 * @param {Object} data - 邀请数据 { clientId, selectedDocuments, invitationMessage }
 */
export const createInvitation = (data) => {
  return api.post("/ib-invitations", withIbInitialLog(data));
};

/**
 * 验证邀请码
 * @param {string} code - 邀请码
 */
export const verifyInvitation = (code) => {
  return api.get(`/ib-invitations/verify/${code}`);
};

/**
 * 接受邀请
 * @param {string} code - 邀请码
 */
export const acceptInvitation = (code) => {
  return api.post(`/ib-invitations/${code}/accept`);
};

/**
 * 拒绝邀请
 * @param {string} code - 邀请码
 */
export const declineInvitation = (code) => {
  return api.post(`/ib-invitations/${code}/decline`);
};

export default {
  getInvitations,
  createInvitation,
  verifyInvitation,
  acceptInvitation,
  declineInvitation,
};
