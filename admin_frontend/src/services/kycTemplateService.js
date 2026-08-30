import api from "./api";
import { getSubModuleKey } from "@/config/operationLogPages";

const KYC_TEMPLATES_LOG_SUB_MODULE = getSubModuleKey("page_kyc_templates");

const withLogSubModule = (data = {}) => ({
  logSubModuleKey: KYC_TEMPLATES_LOG_SUB_MODULE,
  ...data,
});

const deleteLogParams = () =>
  KYC_TEMPLATES_LOG_SUB_MODULE
    ? { logSubModuleKey: KYC_TEMPLATES_LOG_SUB_MODULE }
    : {};

/**
 * KYC模板管理服务
 */
export const kycTemplateService = {
  /**
   * 获取所有KYC模板
   */
  async getTemplates() {
    return await api.get("/kyc-templates");
  },

  /**
   * 获取KYC统计信息
   */
  async getStatistics() {
    return await api.get("/kyc-templates/statistics");
  },

  /**
   * 获取单个模板详情
   * @param {number} id - 模板ID
   */
  async getTemplate(id) {
    return await api.get(`/kyc-templates/${id}`);
  },

  /**
   * 创建新模板
   * @param {object} data - 模板数据
   */
  async createTemplate(data) {
    return await api.post("/kyc-templates/create", withLogSubModule(data));
  },

  /**
   * 更新模板
   * @param {number} id - 模板ID
   * @param {object} data - 更新数据
   */
  async updateTemplate(id, data) {
    return await api.put(`/kyc-templates/modify/${id}`, withLogSubModule(data));
  },

  /**
   * 原子更新第三方绑定（isThirdPartyEnabled + thirdPartyProvider + externalTemplateId）
   * @param {number} id 模板 ID
   * @param {object} payload  { isThirdPartyEnabled, thirdPartyProvider?, externalTemplateId? }
   */
  async updateThirdPartyBinding(id, payload) {
    return await api.put(
      `/kyc-templates/${id}/third-party`,
      withLogSubModule(payload),
    );
  },

  /**
   * 删除模板
   * @param {number} id - 模板ID
   */
  async deleteTemplate(id) {
    return await api.delete(`/kyc-templates/${id}`, {
      params: deleteLogParams(),
    });
  },

  /**
   * 克隆模板
   * @param {number} id - 模板ID
   */
  async cloneTemplate(id) {
    return await api.post(`/kyc-templates/${id}/clone`, withLogSubModule({}));
  },

  /**
   * 获取已被其他模板占用的国家代码（当前模板已选的不算）
   * @param {number|null} excludeTemplateId - 当前编辑的模板 ID，不传则返回所有被占用的
   */
  async getTakenCountryCodes(excludeTemplateId = null) {
    const params =
      excludeTemplateId != null
        ? { exclude_template_id: excludeTemplateId }
        : {};
    return await api.get("/kyc-templates/taken-country-codes", { params });
  },

  /**
   * 更新模板国家
   * @param {number} id - 模板ID
   * @param {object} data - 国家数据
   */
  async updateCountries(id, data) {
    return await api.put(
      `/kyc-templates/${id}/countries`,
      withLogSubModule(data),
    );
  },

  /**
   * 获取模板历史记录
   * @param {number} id - 模板ID
   */
  async getHistory(id) {
    return await api.get(`/kyc-templates/${id}/history`);
  },

  /**
   * 获取模板问题列表
   * @param {number} id - 模板ID
   */
  async getTemplateQuestions(id, activeOnly = false) {
    return await api.post(`/kyc-templates/${id}/admin-questions`, {
      active_only: activeOnly,
    });
  },

  /**
   * 获取模板分类列表
   * @param {number} id - 模板ID
   */
  async getTemplateCategories(id) {
    return await api.get(`/kyc-templates/${id}/categories`);
  },

  /**
   * 获取模板规则列表
   * @param {number} id - 模板ID
   */
  async getTemplateRules(id) {
    return await api.get(`/kyc-templates/${id}/rules`);
  },

  /**
   * 获取模板选择题列表（用于规则配置）
   * @param {number} id - 模板ID
   */
  async getChoiceQuestions(id) {
    return await api.get(`/kyc-templates/${id}/choice-questions`);
  },

  /**
   * 获取模板文档列表
   * @param {number} id - 模板ID
   */
  async getTemplateDocuments(id) {
    return await api.get(`/kyc-templates/${id}/documents`);
  },
};

/**
 * KYC问题管理服务
 */
export const kycQuestionService = {
  /**
   * 创建新问题
   * @param {object} data - 问题数据
   */
  async createQuestion(data) {
    return await api.post("/kyc-questions/create", withLogSubModule(data));
  },

  /**
   * 获取问题详情
   * @param {number} id - 问题ID
   */
  async getQuestion(id) {
    return await api.get(`/kyc-questions/${id}`);
  },

  /**
   * 更新问题
   * @param {number} id - 问题ID
   * @param {object} data - 更新数据
   */
  async updateQuestion(id, data) {
    return await api.put(`/kyc-questions/modify/${id}`, withLogSubModule(data));
  },

  /**
   * 删除问题
   * @param {number} id - 问题ID
   */
  async deleteQuestion(id) {
    return await api.delete(`/kyc-questions/delete/${id}`, {
      params: deleteLogParams(),
    });
  },

  /**
   * 复制问题
   * @param {number} id - 问题ID
   */
  async duplicateQuestion(id) {
    return await api.post(
      `/kyc-questions/${id}/duplicate`,
      withLogSubModule({}),
    );
  },

  /**
   * 重新排序问题
   * @param {object} data - 排序数据
   */
  async reorderQuestions(data) {
    return await api.put("/kyc-questions/reorder", withLogSubModule(data));
  },
};

/**
 * KYC分类管理服务
 */
export const kycCategoryService = {
  /**
   * 创建新分类
   * @param {object} data - 分类数据
   */
  async createCategory(data) {
    return await api.post("/kyc-categories/create", withLogSubModule(data));
  },

  /**
   * 更新分类
   * @param {number} id - 分类ID
   * @param {object} data - 更新数据
   */
  async updateCategory(id, data) {
    return await api.put(`/kyc-categories/${id}`, withLogSubModule(data));
  },

  /**
   * 删除分类
   * @param {number} id - 分类ID
   */
  async deleteCategory(id) {
    return await api.delete(`/kyc-categories/remove/${id}`, {
      params: deleteLogParams(),
    });
  },
};

/**
 * KYC规则管理服务
 */
export const kycRuleService = {
  /**
   * 创建新规则
   * @param {object} data - 规则数据
   */
  async createRule(data) {
    return await api.post("/kyc-rules/create", withLogSubModule(data));
  },

  /**
   * 更新规则
   * @param {number} id - 规则ID
   * @param {object} data - 更新数据
   */
  async updateRule(id, data) {
    return await api.put(`/kyc-rules/modify/${id}`, withLogSubModule(data));
  },

  /**
   * 删除规则
   * @param {number} id - 规则ID
   */
  async deleteRule(id) {
    return await api.delete(`/kyc-rules/remove/${id}`, {
      params: deleteLogParams(),
    });
  },
};

/**
 * KYC模板文档管理服务
 */
export const kycDocumentService = {
  /**
   * 创建新文档
   * @param {object} data - 文档数据
   */
  async createDocument(data) {
    return await api.post(
      "/kyc-template-documents/create",
      withLogSubModule(data),
    );
  },

  /**
   * 更新文档
   * @param {number} id - 文档ID
   * @param {object} data - 更新数据
   */
  async updateDocument(id, data) {
    return await api.put(
      `/kyc-template-documents/modify/${id}`,
      withLogSubModule(data),
    );
  },

  /**
   * 删除文档
   * @param {number} id - 文档ID
   */
  async deleteDocument(id) {
    return await api.delete(`/kyc-template-documents/remove/${id}`, {
      params: deleteLogParams(),
    });
  },

  /**
   * 获取文档详情
   * @param {number} id - 文档ID
   */
  async getDocument(id) {
    return await api.get(`/kyc-template-documents/${id}`);
  },
};

export default {
  kycTemplateService,
  kycQuestionService,
  kycCategoryService,
  kycRuleService,
};
