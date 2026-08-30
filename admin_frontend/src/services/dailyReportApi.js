/**
 * Daily Report API Service (Sales)
 * Per-sales daily deposit / withdrawal / net deposit / new leads / new clients,
 * plus the monthly KPI target of each sales user.
 */

import api from "./api";

/**
 * Fetch the per-sales report of one day
 * @param {Object} params - { date, tzOffset, search }
 */
export const getSummary = (params = {}) => {
  return api.get("/daily-reports/summary", { params });
};

/**
 * Save one sales user's monthly KPI target
 * @param {Object} data - { salesId, month, kpiTarget }
 */
export const saveKpi = (data) => {
  return api.put("/daily-reports/kpi", data);
};

export default {
  getSummary,
  saveKpi,
};
