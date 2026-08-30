import api from "./api";

/**
 * Lead Bulk Operations API Service
 */

// ============================================================
// Lead Bulk Operations Management
// ============================================================

/**
 * Get bulk operations history with pagination
 * @param {Object} params - Query parameters
 * @param {number} params.page - Page number
 * @param {number} params.per_page - Items per page
 * @param {string} params.operation_type - Filter by operation type
 */
export const getBulkOperations = (params = {}) => {
  return api.get("/lead-bulk-operations", { params });
};

/**
 * Get single bulk operation details
 * @param {number} id - Operation ID
 */
export const getBulkOperation = (id) => {
  return api.get(`/lead-bulk-operations/${id}`);
};

/**
 * Get bulk operations by admin
 * @param {number} adminId - Admin user ID
 * @param {number} limit - Limit number of results
 */
export const getBulkOperationsByAdmin = (adminId, limit = 20) => {
  return api.get(`/lead-bulk-operations/admin/${adminId}`, {
    params: { limit },
  });
};

/**
 * Get bulk operations by lead
 * @param {number} leadId - Lead ID
 */
export const getBulkOperationsByLead = (leadId) => {
  return api.get(`/lead-bulk-operations/lead/${leadId}`);
};

/**
 * Get bulk operation statistics
 * @param {Object} params - Query parameters
 * @param {string} params.start_date - Start date filter
 * @param {string} params.end_date - End date filter
 */
export const getBulkOperationStats = (params = {}) => {
  return api.get("/lead-bulk-operations/stats", { params });
};

/**
 * Get recent bulk operations
 * @param {number} limit - Limit number of results
 */
export const getRecentBulkOperations = (limit = 10) => {
  return api.get("/lead-bulk-operations/recent", { params: { limit } });
};

/**
 * Get bulk operation trends
 * @param {Object} params - Query parameters
 * @param {string} params.start_date - Start date
 * @param {string} params.end_date - End date
 * @param {string} params.operation_type - Filter by operation type
 */
export const getBulkOperationTrends = (params = {}) => {
  return api.get("/lead-bulk-operations/trends", { params });
};

/**
 * Get admin rankings by bulk operations
 * @param {Object} params - Query parameters
 * @param {string} params.start_date - Start date filter
 * @param {string} params.end_date - End date filter
 * @param {number} params.limit - Limit number of results
 */
export const getAdminRankings = (params = {}) => {
  return api.get("/lead-bulk-operations/admin-rankings", { params });
};

/**
 * Export bulk operations
 * @param {Object} data - Export parameters
 * @param {string} data.start_date - Start date filter
 * @param {string} data.end_date - End date filter
 * @param {string} data.operation_type - Filter by operation type
 */
export const exportBulkOperations = (data = {}) => {
  return api.post("/lead-bulk-operations/export", data);
};

/**
 * Delete bulk operation record
 * @param {number} id - Operation ID
 */
export const deleteBulkOperation = (id) => {
  return api.delete(`/lead-bulk-operations/${id}`);
};

export default {
  getBulkOperations,
  getBulkOperation,
  getBulkOperationsByAdmin,
  getBulkOperationsByLead,
  getBulkOperationStats,
  getRecentBulkOperations,
  getBulkOperationTrends,
  getAdminRankings,
  exportBulkOperations,
  deleteBulkOperation,
};
