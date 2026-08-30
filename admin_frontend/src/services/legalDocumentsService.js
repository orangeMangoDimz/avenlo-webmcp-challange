import api from "./api";

/**
 * Legal Documents Management API Service
 */

// ============================================================
// Legal Documents Management
// ============================================================

/**
 * Get all legal documents
 * @param {Object} params - Query parameters
 * @param {string} params.language - Language code filter
 * @param {boolean} params.active_only - Only active documents
 */
export const getLegalDocuments = (params = {}) => {
  return api.get("/legal-documents", { params });
};

/**
 * Get single legal document
 * @param {number} id - Document ID
 */
export const getLegalDocument = (id) => {
  return api.get(`/legal-documents/${id}`);
};

/**
 * Get legal document by type
 * @param {string} documentType - Document type
 * @param {string} language - Language code (default: 'en')
 */
export const getLegalDocumentByType = (documentType, language = "en") => {
  return api.get(`/legal-documents/type/${documentType}`, {
    params: { language },
  });
};

/**
 * Get available languages for a document type
 * @param {string} documentType - Document type
 */
export const getAvailableLanguages = (documentType) => {
  return api.get(`/legal-documents/type/${documentType}/languages`);
};

/**
 * Create legal document
 * @param {Object} data - Document data
 */
export const createLegalDocument = (data) => {
  return api.post("/legal-documents", data);
};

/**
 * Update legal document
 * @param {number} id - Document ID
 * @param {Object} data - Document data
 */
export const updateLegalDocument = (id, data) => {
  return api.put(`/legal-documents/${id}`, data);
};

/**
 * Delete legal document
 * @param {number} id - Document ID
 */
export const deleteLegalDocument = (id) => {
  return api.delete(`/legal-documents/${id}`);
};

/**
 * Toggle document active status
 * @param {number} id - Document ID
 */
export const toggleLegalDocument = (id) => {
  return api.post(`/legal-documents/${id}/toggle`);
};

/**
 * Publish new version of document
 * @param {number} id - Document ID
 * @param {string} version - New version number
 */
export const publishNewVersion = (id, version) => {
  return api.post(`/legal-documents/${id}/publish`, { version });
};

/**
 * Reorder legal documents
 * @param {Array} documents - Array of {id, order} objects
 */
export const reorderLegalDocuments = (documents) => {
  return api.post("/legal-documents/reorder", { documents });
};

// ============================================================
// Legal Document Signatures
// ============================================================

/**
 * Get all signatures with pagination
 * @param {Object} params - Query parameters
 */
export const getLegalDocumentSignatures = (params = {}) => {
  return api.get("/legal-document-signatures", { params });
};

/**
 * Get single signature record
 * @param {number} id - Signature ID
 */
export const getLegalDocumentSignature = (id) => {
  return api.get(`/legal-document-signatures/${id}`);
};

/**
 * Get lead's signature records
 * @param {number} leadId - Lead ID
 */
export const getLeadSignatures = (leadId) => {
  return api.get(`/legal-document-signatures/lead/${leadId}`);
};

/**
 * Get document's signature records
 * @param {number} documentId - Document ID
 * @param {Object} params - Query parameters
 */
export const getDocumentSignatures = (documentId, params = {}) => {
  return api.get(`/legal-document-signatures/document/${documentId}`, {
    params,
  });
};

/**
 * Get document signature statistics
 * @param {string} documentType - Document type
 */
export const getDocumentSignatureStats = (documentType) => {
  return api.get(`/legal-document-signatures/stats/${documentType}`);
};

/**
 * Check if lead has signed a document
 * @param {number} leadId - Lead ID
 * @param {string} documentType - Document type
 */
export const checkSignature = (leadId, documentType) => {
  return api.get(`/legal-document-signatures/check/${leadId}/${documentType}`);
};

/**
 * Check if lead has signed all required documents
 * @param {number} leadId - Lead ID
 */
export const checkAllSignatures = (leadId) => {
  return api.get(`/legal-document-signatures/check-all/${leadId}`);
};

/**
 * Get signature trends
 * @param {Object} params - Query parameters
 */
export const getSignatureTrends = (params = {}) => {
  return api.get("/legal-document-signatures/trends", { params });
};

/**
 * Record document signature
 * @param {Object} data - Signature data
 */
export const recordSignature = (data) => {
  return api.post("/legal-document-signatures", data);
};

/**
 * Delete signature record
 * @param {number} id - Signature ID
 */
export const deleteSignature = (id) => {
  return api.delete(`/legal-document-signatures/${id}`);
};

export default {
  // Legal Documents
  getLegalDocuments,
  getLegalDocument,
  getLegalDocumentByType,
  getAvailableLanguages,
  createLegalDocument,
  updateLegalDocument,
  deleteLegalDocument,
  toggleLegalDocument,
  publishNewVersion,
  reorderLegalDocuments,

  // Legal Document Signatures
  getLegalDocumentSignatures,
  getLegalDocumentSignature,
  getLeadSignatures,
  getDocumentSignatures,
  getDocumentSignatureStats,
  checkSignature,
  checkAllSignatures,
  getSignatureTrends,
  recordSignature,
  deleteSignature,
};
