import api from "./api";

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
    return await api.post("/kyc-templates/create", data);
  },

  /**
   * 更新模板
   * @param {number} id - 模板ID
   * @param {object} data - 更新数据
   */
  async updateTemplate(id, data) {
    return await api.put(`/kyc-templates/${id}`, data);
  },

  /**
   * 删除模板
   * @param {number} id - 模板ID
   */
  async deleteTemplate(id) {
    return await api.delete(`/kyc-templates/${id}`);
  },

  /**
   * 克隆模板
   * @param {number} id - 模板ID
   */
  async cloneTemplate(id) {
    return await api.post(`/kyc-templates/${id}/clone`);
  },

  /**
   * 更新模板国家
   * @param {number} id - 模板ID
   * @param {object} data - 国家数据
   */
  async updateCountries(id, data) {
    return await api.put(`/kyc-templates/${id}/countries`, data);
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
  async getTemplateQuestions(id) {
    return await api.get(`/kyc-templates/${id}/questions`);
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
    return await api.post("/kyc-questions", data);
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
    return await api.put(`/kyc-questions/${id}`, data);
  },

  /**
   * 删除问题
   * @param {number} id - 问题ID
   */
  async deleteQuestion(id) {
    return await api.delete(`/kyc-questions/${id}`);
  },

  /**
   * 复制问题
   * @param {number} id - 问题ID
   */
  async duplicateQuestion(id) {
    return await api.post(`/kyc-questions/${id}/duplicate`);
  },

  /**
   * 重新排序问题
   * @param {object} data - 排序数据
   */
  async reorderQuestions(data) {
    return await api.put("/kyc-questions/reorder", data);
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
    return await api.post("/kyc-categories", data);
  },

  /**
   * 更新分类
   * @param {number} id - 分类ID
   * @param {object} data - 更新数据
   */
  async updateCategory(id, data) {
    return await api.put(`/kyc-categories/${id}`, data);
  },

  /**
   * 删除分类
   * @param {number} id - 分类ID
   */
  async deleteCategory(id) {
    return await api.delete(`/kyc-categories/${id}`);
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
    return await api.post("/kyc-rules", data);
  },

  /**
   * 更新规则
   * @param {number} id - 规则ID
   * @param {object} data - 更新数据
   */
  async updateRule(id, data) {
    return await api.put(`/kyc-rules/${id}`, data);
  },

  /**
   * 删除规则
   * @param {number} id - 规则ID
   */
  async deleteRule(id) {
    return await api.delete(`/kyc-rules/${id}`);
  },
};

export default {
  kycTemplateService,
  kycQuestionService,
  kycCategoryService,
  kycRuleService,
};
