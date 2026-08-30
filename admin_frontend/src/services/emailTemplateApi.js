/**
 * Email Template API Service
 * 邮件模板管理相关API接口
 */

import api from "./api";
import { getSubModuleKey } from "@/config/operationLogPages";

/** 每次请求时解析，避免模块顶层缓存空值（HMR / 加载顺序） */
const emailTemplatesLogSubModuleKey = () =>
  getSubModuleKey("page_email_templates");

const withEmailTemplatesLog = (payload = {}) => ({
  ...payload,
  logSubModuleKey: emailTemplatesLogSubModuleKey(),
});

const emailTemplatesLogParams = () => {
  const key = emailTemplatesLogSubModuleKey();
  return key ? { logSubModuleKey: key } : {};
};

/**
 * 获取模板列表
 * @param {Object} params - 查询参数 (page, per_page, category, recipientType, isActive, search)
 */
export const getTemplates = (params = {}) => {
  return api.get("/email-templates", { params });
};

/**
 * 获取单个模板
 * @param {number} id - 模板ID
 */
export const getTemplate = (id) => {
  return api.get(`/email-templates/${id}`);
};

/**
 * 根据key获取模板
 * @param {string} key - 模板key
 */
export const getTemplateByKey = (key) => {
  return api.get(`/email-templates/key/${key}`);
};

/**
 * 创建模板
 * @param {Object} data - 模板数据
 */
export const createTemplate = (data) => {
  return api.post("/email-templates/create", withEmailTemplatesLog(data));
};

/**
 * 更新模板
 * @param {number} id - 模板ID
 * @param {Object} data - 模板数据
 */
export const updateTemplate = (id, data) => {
  return api.put(`/email-templates/modify/${id}`, withEmailTemplatesLog(data));
};

/**
 * 删除模板
 * @param {number} id - 模板ID
 */
export const deleteTemplate = (id) => {
  return api.delete(`/email-templates/remove/${id}`, {
    params: emailTemplatesLogParams(),
  });
};

/**
 * 获取所有分类
 */
export const getCategories = () => {
  return api.get("/email-templates/categories");
};

/**
 * 切换模板启用状态
 * @param {number} id - 模板ID
 */
export const toggleActive = (id) => {
  return api.get(`/email-templates/${id}/toggle-active`, {
    params: emailTemplatesLogParams(),
  });
};

export default {
  getTemplates,
  getTemplate,
  getTemplateByKey,
  createTemplate,
  updateTemplate,
  deleteTemplate,
  getCategories,
  toggleActive,
};
