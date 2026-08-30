import api from "./api";

// 登录页设置服务
const loginSettingsService = {
  // ========== 品牌设置 ==========
  getBranding() {
    return api.get("/login-settings/branding");
  },

  updateBranding(data) {
    return api.put("/login-settings/branding", data);
  },

  uploadLogo(formData) {
    return api.post("/login-settings/branding/upload-logo", formData, {
      headers: { "Content-Type": "multipart/form-data" },
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
    return api.post("/login-settings/form-fields", data);
  },

  updateFormField(id, data) {
    return api.put(`/login-settings/form-fields/${id}`, data);
  },

  deleteFormField(id) {
    return api.delete(`/login-settings/form-fields/${id}`);
  },

  updateFieldsOrder(orders) {
    return api.put("/login-settings/form-fields/order", { orders });
  },

  // ========== 密码强度设置 ==========
  getPasswordStrength() {
    return api.get("/login-settings/password-strength");
  },

  updatePasswordStrength(data) {
    return api.put("/login-settings/password-strength", data);
  },

  applyPasswordLevel(level) {
    return api.post("/login-settings/password-strength/apply-level", { level });
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
    return api.post("/login-settings/legal-documents", data);
  },

  updateLegalDocument(id, data) {
    return api.put(`/login-settings/legal-documents/${id}`, data);
  },

  deleteLegalDocument(id) {
    return api.delete(`/login-settings/legal-documents/${id}`);
  },

  // ========== 语言包 ==========
  getLanguagePacks() {
    return api.get("/login-settings/language-packs");
  },

  getEnabledLanguagePacks() {
    return api.get("/login-settings/language-packs/enabled");
  },

  uploadLanguagePack(data) {
    return api.post("/login-settings/language-packs", data);
  },

  updateLanguagePack(languageCode, data) {
    return api.put(`/login-settings/language-packs/${languageCode}`, data);
  },

  setDefaultLanguage(languageCode) {
    return api.post("/login-settings/language-packs/set-default", {
      languageCode,
    });
  },

  // ========== IP语言检测设置 ==========
  getIpDetectionLanguage() {
    return api.get("/login-settings/detect-language");
  },

  getIpLanguageDetection() {
    return api.get("/login-settings/ip-language-detection");
  },

  updateIpLanguageDetection(data) {
    return api.put("/login-settings/ip-language-detection", data);
  },

  // ========== 邮件验证设置 ==========
  getEmailVerification() {
    return api.get("/login-settings/email-verification");
  },

  updateEmailVerification(data) {
    return api.put("/login-settings/email-verification", data);
  },

  getDefaultTradingGroups(platform) {
    return api.get("/login-settings/trading-groups/default", {
      params: { platform },
    });
  },

  // ========== 变更日志 ==========
  getChangeLog(params = {}) {
    return api.get("/login-settings/change-log", { params });
  },
};

export default loginSettingsService;
