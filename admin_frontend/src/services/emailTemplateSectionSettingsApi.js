/**
 * Email Template Section Settings API Service
 * 邮件模板板块设置相关API接口
 */

import api from "./api";
import { getSubModuleKey } from "@/config/operationLogPages";

/** 每次请求时解析，避免模块顶层缓存空值（HMR / 加载顺序） */
const emailSettingsLogSubModuleKey = () =>
  getSubModuleKey("page_email_settings");

const withEmailSettingsLog = (payload = {}) => ({
  ...payload,
  logSubModuleKey: emailSettingsLogSubModuleKey(),
});

/**
 * 获取所有板块设置
 */
export const getSettings = () => {
  return api.get("/email-template-section-settings");
};

/**
 * 获取单个板块设置
 * @param {string} sectionKey - 板块标识（如：leads, client_list）
 */
export const getSectionSettings = (sectionKey) => {
  return api.get(`/email-template-section-settings/${sectionKey}`);
};

/**
 * 更新单个板块的模板设置
 * @param {string} sectionKey - 板块标识
 * @param {Array<number>} templateIds - 选中的模板ID数组
 */
export const updateSectionSettings = (sectionKey, templateIds) => {
  return api.put(
    `/email-template-section-settings/${sectionKey}`,
    withEmailSettingsLog({
      templateIds,
    }),
  );
};

/**
 * 批量更新多个板块的设置
 * @param {Object} settings - 设置对象，格式：{ sectionKey: [templateIds], ... }
 */
export const updateBatchSettings = (settings) => {
  return api.put("/email-template-section-settings/batch", settings);
};

/**
 * 获取板块可用的邮件模板列表（用于 Send Notification 弹窗）
 * @param {string} sectionKey - 板块标识
 */
export const getSectionTemplates = (sectionKey) => {
  return api.get(`/email-template-section-settings/${sectionKey}/templates`);
};

export default {
  getSettings,
  getSectionSettings,
  updateSectionSettings,
  updateBatchSettings,
  getSectionTemplates,
};
