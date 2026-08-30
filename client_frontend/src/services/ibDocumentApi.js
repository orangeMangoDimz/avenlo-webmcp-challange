import clientApi from "./clientApi";

/**
 * IB文档服务
 */
const ibDocumentApi = {
  /**
   * 获取所有必需的IB文档
   */
  async getRequiredDocuments() {
    return await clientApi.get(
      "/index.php?path=api/ib-settings/documents&active_only=true",
    );
  },

  /**
   * 获取单个IB文档
   * @param {number} documentId - 文档ID
   */
  async getDocument(documentId) {
    return await clientApi.get(
      `/index.php?path=api/ib-settings/documents/${documentId}`,
    );
  },
};

export default ibDocumentApi;
