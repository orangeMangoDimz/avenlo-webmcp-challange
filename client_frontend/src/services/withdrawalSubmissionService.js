import api from "./api";

export const withdrawalSubmissionService = {
  async getTemplateDetails(templateId) {
    return await api.get(`/withdrawal-templates/${templateId}`);
  },

  async createSubmission(templateId, paymentMethodId = null) {
    const payload = { templateId };
    if (
      paymentMethodId !== null &&
      paymentMethodId !== undefined &&
      paymentMethodId !== ""
    ) {
      payload.paymentMethodId = paymentMethodId;
    }
    return await api.post("/withdrawal-submissions", payload);
  },

  async getSubmissionDetails(submissionId) {
    return await api.get(`/withdrawal-submissions/${submissionId}`);
  },

  async saveAnswers(submissionId, answers) {
    return await api.post(`/withdrawal-submissions/${submissionId}/answers`, {
      answers,
    });
  },

  async uploadFile(submissionId, questionId, file) {
    const formData = new FormData();
    formData.append("file", file);
    formData.append("questionId", questionId);

    return await api.post(
      `/withdrawal-submissions/${submissionId}/upload`,
      formData,
      {
        headers: {
          "Content-Type": "multipart/form-data",
        },
      },
    );
  },

  async signDocuments(submissionId, documentIds) {
    return await api.post(
      `/withdrawal-submissions/${submissionId}/sign-documents`,
      { documentIds },
    );
  },

  async submitApplication(submissionId) {
    return await api.post(`/withdrawal-submissions/${submissionId}/submit`);
  },

  // 客户软删除自己某条已保存地址，只对客户隐藏
  async hideAddress(submissionId) {
    return await api.post(`/withdrawal-submissions/${submissionId}/hide`);
  },

  async evaluateRules(submissionId) {
    return await api.post(
      `/withdrawal-submissions/${submissionId}/evaluate-rules`,
    );
  },

  async getResubmitRequest(submissionId) {
    return await api.get(
      `/withdrawal-submissions/${submissionId}/resubmit-request`,
    );
  },

  async getResubmitAnswers(submissionId) {
    return await api.get(
      `/withdrawal-submissions/${submissionId}/resubmit-answers`,
    );
  },

  async uploadResubmitFile(submissionId, itemIndex, file) {
    const formData = new FormData();
    formData.append("file", file);
    formData.append("itemIndex", itemIndex);

    return await api.post(
      `/withdrawal-submissions/${submissionId}/resubmit-upload`,
      formData,
      {
        headers: {
          "Content-Type": "multipart/form-data",
        },
      },
    );
  },
};

export default withdrawalSubmissionService;
