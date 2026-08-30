import api from "./api";
import { getSubModuleKey } from "@/config/operationLogPages";

/** 每次请求时解析，避免模块顶层缓存空值（HMR / 加载顺序） */
const loginPageSettingsLogSubModuleKey = () =>
  getSubModuleKey("page_login_page_settings");
const platformSettingsLogSubModuleKey = () =>
  getSubModuleKey("page_platform_settings");

const withLoginPageSettingsLog = (payload = {}) => ({
  ...payload,
  logSubModuleKey: loginPageSettingsLogSubModuleKey(),
});

const withPlatformSettingsLog = (payload = {}) => ({
  ...payload,
  logSubModuleKey: platformSettingsLogSubModuleKey(),
});

const loginPageSettingsLogParams = () => {
  const key = loginPageSettingsLogSubModuleKey();
  return key ? { logSubModuleKey: key } : {};
};

const platformSettingsLogParams = () => {
  const key = platformSettingsLogSubModuleKey();
  return key ? { logSubModuleKey: key } : {};
};

const deleteLoginPageSettingsLogParams = () => loginPageSettingsLogParams();

// 登录页设置服务
const loginSettingsService = {
  // ========== 品牌设置 ==========
  getBranding() {
    return api.get("/login-settings/branding");
  },

  updateBranding(data) {
    return api.put("/login-settings/branding", withLoginPageSettingsLog(data));
  },

  uploadLogo(formData) {
    const key = loginPageSettingsLogSubModuleKey();
    if (key && formData instanceof FormData) {
      formData.append("logSubModuleKey", key);
    }
    return api.post("/login-settings/branding/upload-logo", formData, {
      headers: { "Content-Type": "multipart/form-data" },
      params: loginPageSettingsLogParams(),
    });
  },

  // ========== 注册表单字段 ==========
  getFormFields() {
    return api.get("/login-settings/form-fields");
  },

  getEnabledFormFields() {
    return api.get("/login-settings/form-fields/enabled");
  },

  createFormField(data) {
    return api.post(
      "/login-settings/form-fields",
      withLoginPageSettingsLog(data),
    );
  },

  updateFormField(id, data) {
    return api.put(
      `/login-settings/form-fields/${id}`,
      withLoginPageSettingsLog(data),
    );
  },

  deleteFormField(id) {
    return api.delete(`/login-settings/form-fields/${id}`, {
      params: deleteLoginPageSettingsLogParams(),
    });
  },

  updateFieldsOrder(orders) {
    return api.put(
      "/login-settings/form-fields/order",
      withLoginPageSettingsLog({ orders }),
    );
  },

  // ========== 密码强度设置 ==========
  getPasswordStrength() {
    return api.get("/login-settings/password-strength");
  },

  updatePasswordStrength(data) {
    return api.put("/login-settings/password-strength", data);
  },

  applyPasswordLevel(level) {
    return api.post(
      "/login-settings/password-strength/apply-level",
      withLoginPageSettingsLog({ level }),
    );
  },

  // ========== 法律文档 ==========
  getLegalDocuments(lang = "en") {
    return api.get("/login-settings/legal-documents", { params: { lang } });
  },

  getActiveLegalDocuments(lang = "en") {
    return api.get("/login-settings/legal-documents/active", {
      params: { lang },
    });
  },

  getActiveLegalDocumentsPublic(lang = "en") {
    return api.get("/login-settings/legal-documents/active", {
      params: { lang },
    });
  },

  createLegalDocument(data) {
    return api.post(
      "/login-settings/legal-documents",
      withLoginPageSettingsLog(data),
    );
  },

  updateLegalDocument(id, data) {
    return api.put(
      `/login-settings/legal-documents/${id}`,
      withLoginPageSettingsLog(data),
    );
  },

  deleteLegalDocument(id) {
    return api.delete(`/login-settings/legal-documents/${id}`, {
      params: deleteLoginPageSettingsLogParams(),
    });
  },

  // ========== 语言包 ==========
  getLanguagePacks() {
    return api.get("/login-settings/language-packs");
  },

  getEnabledLanguagePacks() {
    return api.get("/login-settings/language-packs/enabled");
  },

  getTranslations(languageCode) {
    return api.get(
      `/login-settings/language-packs/${languageCode}/translations`,
    );
  },

  uploadLanguagePack(data) {
    const key = loginPageSettingsLogSubModuleKey();
    if (key && data instanceof FormData) {
      data.append("logSubModuleKey", key);
    }
    return api.post("/login-settings/language-packs", data, {
      params: loginPageSettingsLogParams(),
    });
  },

  updateLanguagePack(languageCode, data) {
    return api.put(
      `/login-settings/language-packs/${languageCode}`,
      withLoginPageSettingsLog(data),
    );
  },

  setDefaultLanguage(languageCode) {
    return api.post(
      "/login-settings/language-packs/set-default",
      withLoginPageSettingsLog({ languageCode }),
    );
  },

  // ========== IP语言检测设置 ==========
  getIpLanguageDetection() {
    return api.get("/login-settings/ip-language-detection");
  },

  updateIpLanguageDetection(data) {
    return api.put(
      "/login-settings/ip-language-detection",
      withLoginPageSettingsLog(data),
    );
  },

  // ========== 邮件验证设置 ==========
  getEmailVerification() {
    return api.get("/login-settings/email-verification");
  },

  updateEmailVerification(data) {
    return api.put(
      "/login-settings/email-verification",
      withLoginPageSettingsLog(data),
    );
  },

  // ========== 交易平台组别 ==========
  getTradingGroupPlatforms() {
    return api.get("/login-settings/trading-groups/platforms");
  },

  updatePlatformAccountSettings(platformKey, payload) {
    return api.put(
      `/login-settings/trading-groups/platforms/${encodeURIComponent(platformKey)}/account-settings`,
      withPlatformSettingsLog(payload),
    );
  },

  getTradingGroups(platform = null) {
    return api.get("/login-settings/trading-groups", {
      params: platform ? { platform } : {},
    });
  },

  updateTradingGroupLabel(groupId, label) {
    return api.put(`/login-settings/trading-groups/${groupId}/label`, {
      label,
    });
  },

  updateTradingGroupConfig(groupId, data) {
    return api.put(
      `/login-settings/trading-groups/${groupId}/config`,
      withPlatformSettingsLog(data),
    );
  },

  getDefaultTradingGroup(platform = null) {
    return api.get("/login-settings/trading-groups/default", {
      params: platform ? { platform } : {},
    });
  },

  setDefaultTradingGroup(groupId, platformKey = null) {
    return api.post(
      "/login-settings/trading-groups/set-default",
      withPlatformSettingsLog({ groupId, platformKey }),
    );
  },

  removeDefaultTradingGroup(groupId, platformKey = null) {
    return api.post(
      "/login-settings/trading-groups/remove-default",
      withPlatformSettingsLog({ groupId, platformKey }),
    );
  },

  syncTradingGroups(platform) {
    return api.post(
      "/login-settings/trading-groups/sync",
      withPlatformSettingsLog({ platform }),
    );
  },

  // ========== 平台杠杆设置 ==========
  getAdminTradingLeverages() {
    return api.get("/admin/trading/leverages");
  },

  getAdminTradingLeverage(id) {
    return api.get(`/admin/trading/leverages/${id}`);
  },

  createAdminTradingLeverage(data) {
    return api.post("/admin/trading/leverages", withPlatformSettingsLog(data));
  },

  updateAdminTradingLeverage(id, data) {
    return api.put(
      `/admin/trading/leverages/${id}`,
      withPlatformSettingsLog(data),
    );
  },

  enableAdminTradingLeverage(id) {
    return api.put(`/admin/trading/leverages/${id}/enable`, null, {
      params: platformSettingsLogParams(),
    });
  },

  disableAdminTradingLeverage(id) {
    return api.put(`/admin/trading/leverages/${id}/disable`, null, {
      params: platformSettingsLogParams(),
    });
  },

  deleteAdminTradingLeverage(id) {
    return api.delete(`/admin/trading/leverages/${id}`, {
      params: platformSettingsLogParams(),
    });
  },

  // ========== 国家列表 ==========
  getCountriesList() {
    return api.get("/login-settings/countries");
  },

  updateCountriesStatus(data) {
    return api.put(
      "/login-settings/countries/status",
      withLoginPageSettingsLog(data),
    );
  },

  // ========== 变更日志 ==========
  getChangeLog(params = {}) {
    return api.get("/login-settings/change-log", { params });
  },
};

export default loginSettingsService;
