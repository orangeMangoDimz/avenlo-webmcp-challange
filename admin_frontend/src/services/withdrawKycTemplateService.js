import api from "./api";
import { getSubModuleKey } from "@/config/operationLogPages";

const WITHDRAW_KYC_TEMPLATES_LOG_SUB_MODULE = getSubModuleKey(
  "page_withdrawkyctemplates",
);

const withLogSubModule = (data = {}) => ({
  logSubModuleKey: WITHDRAW_KYC_TEMPLATES_LOG_SUB_MODULE,
  ...data,
});

const deleteLogParams = () =>
  WITHDRAW_KYC_TEMPLATES_LOG_SUB_MODULE
    ? { logSubModuleKey: WITHDRAW_KYC_TEMPLATES_LOG_SUB_MODULE }
    : {};

const createTemplateServices = ({
  templateBasePath,
  questionBasePath,
  categoryBasePath,
  ruleBasePath,
  documentBasePath,
}) => {
  const templateService = {
    async getTemplates() {
      return await api.get(templateBasePath);
    },

    async getStatistics() {
      return await api.get(`${templateBasePath}/statistics`);
    },

    async getTemplate(id) {
      return await api.get(`${templateBasePath}/${id}`);
    },

    async createTemplate(data) {
      return await api.post(`${templateBasePath}/create`, data);
    },

    async updateTemplate(id, data) {
      return await api.put(
        `${templateBasePath}/modify/${id}`,
        withLogSubModule(data),
      );
    },

    async deleteTemplate(id) {
      return await api.delete(`${templateBasePath}/${id}`);
    },

    async cloneTemplate(id) {
      return await api.post(`${templateBasePath}/${id}/clone`);
    },

    async updateCountries(id, data) {
      return await api.put(`${templateBasePath}/${id}/countries`, data);
    },

    async getHistory(id) {
      return await api.get(`${templateBasePath}/${id}/history`);
    },

    async getTemplateQuestions(id, activeOnly = false) {
      return await api.post(`${templateBasePath}/${id}/admin-questions`, {
        active_only: activeOnly,
      });
    },

    async getTemplateCategories(id) {
      return await api.get(`${templateBasePath}/${id}/categories`);
    },

    async getTemplateRules(id) {
      return await api.get(`${templateBasePath}/${id}/rules`);
    },

    async getChoiceQuestions(id) {
      return await api.get(`${templateBasePath}/${id}/choice-questions`);
    },

    async getTemplateDocuments(id) {
      return await api.get(`${templateBasePath}/${id}/documents`);
    },
  };

  const questionService = {
    async createQuestion(data) {
      return await api.post(
        `${questionBasePath}/create`,
        withLogSubModule(data),
      );
    },

    async getQuestion(id) {
      return await api.get(`${questionBasePath}/${id}`);
    },

    async updateQuestion(id, data) {
      return await api.put(
        `${questionBasePath}/modify/${id}`,
        withLogSubModule(data),
      );
    },

    async deleteQuestion(id) {
      return await api.delete(`${questionBasePath}/delete/${id}`, {
        params: deleteLogParams(),
      });
    },

    async duplicateQuestion(id) {
      return await api.post(
        `${questionBasePath}/${id}/duplicate`,
        withLogSubModule({}),
      );
    },

    async reorderQuestions(data) {
      return await api.put(`${questionBasePath}/reorder`, data);
    },
  };

  const categoryService = {
    async createCategory(data) {
      return await api.post(
        `${categoryBasePath}/create`,
        withLogSubModule(data),
      );
    },

    async updateCategory(id, data) {
      return await api.put(`${categoryBasePath}/${id}`, withLogSubModule(data));
    },

    async deleteCategory(id) {
      return await api.delete(`${categoryBasePath}/remove/${id}`, {
        params: deleteLogParams(),
      });
    },
  };

  const ruleService = {
    async createRule(data) {
      return await api.post(`${ruleBasePath}/create`, withLogSubModule(data));
    },

    async updateRule(id, data) {
      return await api.put(
        `${ruleBasePath}/modify/${id}`,
        withLogSubModule(data),
      );
    },

    async deleteRule(id) {
      return await api.delete(`${ruleBasePath}/remove/${id}`, {
        params: deleteLogParams(),
      });
    },
  };

  const documentService = {
    async createDocument(data) {
      return await api.post(
        `${documentBasePath}/create`,
        withLogSubModule(data),
      );
    },

    async updateDocument(id, data) {
      return await api.put(
        `${documentBasePath}/modify/${id}`,
        withLogSubModule(data),
      );
    },

    async deleteDocument(id) {
      return await api.delete(`${documentBasePath}/remove/${id}`, {
        params: deleteLogParams(),
      });
    },

    async getDocument(id) {
      return await api.get(`${documentBasePath}/${id}`);
    },
  };

  return {
    templateService,
    questionService,
    categoryService,
    ruleService,
    documentService,
  };
};

const withdrawKycServices = createTemplateServices({
  templateBasePath: "/withdrawal-templates",
  questionBasePath: "/withdrawal-questions",
  categoryBasePath: "/withdrawal-categories",
  ruleBasePath: "/withdrawal-rules",
  documentBasePath: "/withdrawal-template-documents",
});

export const withdrawKycTemplateService = withdrawKycServices.templateService;
export const withdrawKycQuestionService = withdrawKycServices.questionService;
export const withdrawKycCategoryService = withdrawKycServices.categoryService;
export const withdrawKycRuleService = withdrawKycServices.ruleService;
export const withdrawKycDocumentService = withdrawKycServices.documentService;

export const withdrawKycServiceSet = {
  templateService: withdrawKycTemplateService,
  questionService: withdrawKycQuestionService,
  categoryService: withdrawKycCategoryService,
  ruleService: withdrawKycRuleService,
  documentService: withdrawKycDocumentService,
};

export default {
  withdrawKycTemplateService,
  withdrawKycQuestionService,
  withdrawKycCategoryService,
  withdrawKycRuleService,
  withdrawKycDocumentService,
  withdrawKycServiceSet,
};
