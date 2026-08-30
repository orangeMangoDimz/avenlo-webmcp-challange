import api from "./api";

/**
 * Leads Management API Service
 */

// ============================================================
// Leads Management
// ============================================================

/**
 * Get leads list with pagination and filters
 */
export const getLeads = (params = {}) => {
  return api.get("/leads", { params });
};

/**
 * Get single lead details
 */
export const getLead = (id) => {
  return api.get(`/leads/${id}`);
};

/**
 * Update lead information
 */
export const updateLead = (id, data) => {
  return api.put(`/leads/${id}`, data);
};

/**
 * Update lead status
 */
export const updateLeadStatus = (id, data) => {
  return api.post(`/leads/${id}/status`, data);
};

/**
 * Get lead activities
 */
export const getLeadActivities = (id, limit = 50) => {
  return api.get(`/leads/${id}/activities`, { params: { limit } });
};

/**
 * Get lead edit history
 */
export const getLeadEditHistory = (id, limit = 50) => {
  return api.get(`/leads/${id}/edit-history`, { params: { limit } });
};

/**
 * Get lead status history
 */
export const getLeadStatusHistory = (id) => {
  return api.get(`/leads/${id}/status-history`);
};

/**
 * Get leads statistics
 */
export const getLeadsStatistics = () => {
  return api.get("/leads/statistics");
};

/**
 * Export leads
 */
export const exportLeads = (leadIds, format = "csv") => {
  return api.post("/leads/export", { leadIds, format });
};

// ============================================================
// Lead Tags Management
// ============================================================

/**
 * Get all lead tags
 */
export const getLeadTags = (withUsage = false) => {
  return api.get("/lead-tags", { params: { with_usage: withUsage } });
};

/**
 * Get single lead tag
 */
export const getLeadTag = (id) => {
  return api.get(`/lead-tags/${id}`);
};

/**
 * Create lead tag
 */
export const createLeadTag = (data) => {
  return api.post("/lead-tags", data);
};

/**
 * Update lead tag
 */
export const updateLeadTag = (id, data) => {
  return api.put(`/lead-tags/${id}`, data);
};

/**
 * Delete lead tag
 */
export const deleteLeadTag = (id) => {
  return api.delete(`/lead-tags/${id}`);
};

/**
 * Get lead's tags
 */
export const getLeadTagsById = (leadId) => {
  return api.get(`/leads/${leadId}/tags`);
};

/**
 * Assign tag to lead
 */
export const assignTagToLead = (leadId, tagId) => {
  return api.post(`/leads/${leadId}/tags`, { tagId });
};

/**
 * Remove tag from lead
 */
export const removeTagFromLead = (leadId, tagId) => {
  return api.delete(`/leads/${leadId}/tags/${tagId}`);
};

/**
 * Bulk assign tag to multiple leads
 */
export const bulkAssignTag = (leadIds, tagId) => {
  return api.post("/lead-tags/bulk-assign", { leadIds, tagId });
};

/**
 * Bulk remove tag from multiple leads
 */
export const bulkRemoveTag = (leadIds, tagId) => {
  return api.post("/lead-tags/bulk-remove", { leadIds, tagId });
};

/**
 * Get leads with specific tag
 */
export const getLeadsByTag = (tagId, params = {}) => {
  return api.get(`/lead-tags/${tagId}/leads`, { params });
};

// ============================================================
// KYC Management
// ============================================================

/**
 * Get pending KYC reviews
 */
export const getPendingKyc = (params = {}) => {
  return api.get("/kyc/pending", { params });
};

/**
 * Get KYC statistics
 */
export const getKycStatistics = () => {
  return api.get("/kyc/statistics");
};

/**
 * Get lead's KYC status
 */
export const getLeadKyc = (leadId) => {
  return api.get(`/leads/${leadId}/kyc`);
};

/**
 * Update lead's KYC status
 */
export const updateLeadKyc = (leadId, data) => {
  return api.put(`/leads/${leadId}/kyc`, data);
};

/**
 * Approve KYC
 */
export const approveKyc = (leadId, notes = "") => {
  return api.post(`/leads/${leadId}/kyc/approve`, { notes });
};

/**
 * Reject KYC
 */
export const rejectKyc = (leadId, rejectionReason, notes = "") => {
  return api.post(`/leads/${leadId}/kyc/reject`, { rejectionReason, notes });
};

/**
 * Mark KYC document as submitted
 */
export const markKycDocumentSubmitted = (leadId) => {
  return api.post(`/leads/${leadId}/kyc/document-submitted`);
};

/**
 * Get reviewer's KYC records
 */
export const getReviewerKycRecords = (reviewerId, params = {}) => {
  return api.get(`/kyc/reviewer/${reviewerId}`, { params });
};

// ============================================================
// Search Tags Management
// ============================================================

/**
 * Get search tags
 */
export const getSearchTags = (activeOnly = false) => {
  return api.get("/search-tags", { params: { active_only: activeOnly } });
};

/**
 * Create search tag
 */
export const createSearchTag = (data) => {
  return api.post("/search-tags", data);
};

/**
 * Update search tag
 */
export const updateSearchTag = (id, data) => {
  return api.put(`/search-tags/${id}`, data);
};

/**
 * Delete search tag
 */
export const deleteSearchTag = (id) => {
  return api.delete(`/search-tags/${id}`);
};

/**
 * Toggle search tag active status
 */
export const toggleSearchTag = (id) => {
  return api.post(`/search-tags/${id}/toggle`);
};

/**
 * Reorder search tags
 */
export const reorderSearchTags = (tags) => {
  return api.post("/search-tags/reorder", { tags });
};

export default {
  // Leads
  getLeads,
  getLead,
  updateLead,
  updateLeadStatus,
  getLeadActivities,
  getLeadEditHistory,
  getLeadStatusHistory,
  getLeadsStatistics,
  exportLeads,

  // Lead Tags
  getLeadTags,
  getLeadTag,
  createLeadTag,
  updateLeadTag,
  deleteLeadTag,
  getLeadTagsById,
  assignTagToLead,
  removeTagFromLead,
  bulkAssignTag,
  bulkRemoveTag,
  getLeadsByTag,

  // KYC
  getPendingKyc,
  getKycStatistics,
  getLeadKyc,
  updateLeadKyc,
  approveKyc,
  rejectKyc,
  markKycDocumentSubmitted,
  getReviewerKycRecords,

  // Search Tags
  getSearchTags,
  createSearchTag,
  updateSearchTag,
  deleteSearchTag,
  toggleSearchTag,
  reorderSearchTags,
};
