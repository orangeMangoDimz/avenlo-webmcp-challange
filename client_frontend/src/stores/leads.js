import { defineStore } from "pinia";
import { ref, computed } from "vue";
import * as leadsService from "@/services/leadsService";

export const useLeadsStore = defineStore("leads", () => {
  // State
  const leads = ref([]);
  const currentLead = ref(null);
  const leadTags = ref([]);
  const searchTags = ref([]);
  const statistics = ref({
    leads: {
      totalLeads: 0,
      todayLeads: 0,
      verifiedLeads: 0,
      activeLeads: 0,
    },
    kyc: {
      not_started: 0,
      in_progress: 0,
      pending_review: 0,
      approved: 0,
      rejected: 0,
      total: 0,
    },
  });
  const pagination = ref({
    total: 0,
    page: 1,
    per_page: 10,
    total_pages: 0,
  });
  const loading = ref(false);
  const error = ref(null);

  // Getters
  const selectedLeads = computed(() => {
    return leads.value.filter((lead) => lead.selected);
  });

  const selectedLeadIds = computed(() => {
    return selectedLeads.value.map((lead) => lead.leadId);
  });

  const hasSelection = computed(() => {
    return selectedLeads.value.length > 0;
  });

  // Actions

  /**
   * Fetch leads list
   */
  const fetchLeads = async (params = {}) => {
    loading.value = true;
    error.value = null;

    try {
      const response = await leadsService.getLeads(params);

      if (response.success) {
        // Add selected property to each lead for checkbox management
        // Note: data.items contains the leads array, data.pagination contains pagination info
        const leadsData = response.data.items || [];
        leads.value = leadsData.map((lead) => ({
          ...lead,
          selected: false,
        }));

        if (response.data.pagination) {
          pagination.value = response.data.pagination;
        }
      } else {
        error.value = response.message || "Failed to fetch leads";
      }

      return response;
    } catch (err) {
      error.value = err.message || "An error occurred while fetching leads";
      throw err;
    } finally {
      loading.value = false;
    }
  };

  /**
   * Fetch single lead details
   */
  const fetchLead = async (id) => {
    loading.value = true;
    error.value = null;

    try {
      const response = await leadsService.getLead(id);

      if (response.success) {
        currentLead.value = response.data;
      } else {
        error.value = response.message || "Failed to fetch lead";
      }

      return response;
    } catch (err) {
      error.value = err.message || "An error occurred while fetching lead";
      throw err;
    } finally {
      loading.value = false;
    }
  };

  /**
   * Update lead information
   */
  const updateLeadInfo = async (id, data) => {
    loading.value = true;
    error.value = null;

    try {
      const response = await leadsService.updateLead(id, data);

      if (response.success) {
        // Update lead in the list
        const index = leads.value.findIndex((lead) => lead.leadId === id);
        if (index !== -1) {
          leads.value[index] = { ...leads.value[index], ...data };
        }

        // Update current lead if it's the same
        if (currentLead.value && currentLead.value.leadId === id) {
          currentLead.value = { ...currentLead.value, ...data };
        }
      } else {
        error.value = response.message || "Failed to update lead";
      }

      return response;
    } catch (err) {
      error.value = err.message || "An error occurred while updating lead";
      throw err;
    } finally {
      loading.value = false;
    }
  };

  /**
   * Update lead status
   */
  const updateStatus = async (id, status, notes = "") => {
    try {
      const response = await leadsService.updateLeadStatus(id, {
        status,
        notes,
      });

      if (response.success) {
        // Update lead status in the list
        const index = leads.value.findIndex((lead) => lead.leadId === id);
        if (index !== -1) {
          leads.value[index].status = status;
        }
      }

      return response;
    } catch (err) {
      error.value = err.message || "An error occurred while updating status";
      throw err;
    }
  };

  /**
   * Fetch leads statistics
   */
  const fetchStatistics = async () => {
    try {
      const response = await leadsService.getLeadsStatistics();

      if (response.success) {
        statistics.value = response.data;
      }

      return response;
    } catch (err) {
      error.value =
        err.message || "An error occurred while fetching statistics";
      throw err;
    }
  };

  /**
   * Fetch all lead tags
   */
  const fetchLeadTags = async (withUsage = false) => {
    try {
      const response = await leadsService.getLeadTags(withUsage);

      if (response.success) {
        leadTags.value = response.data;
      }

      return response;
    } catch (err) {
      error.value = err.message || "An error occurred while fetching tags";
      throw err;
    }
  };

  /**
   * Create lead tag
   */
  const createTag = async (data) => {
    try {
      const response = await leadsService.createLeadTag(data);

      if (response.success) {
        leadTags.value.push(response.data);
      }

      return response;
    } catch (err) {
      error.value = err.message || "An error occurred while creating tag";
      throw err;
    }
  };

  /**
   * Delete lead tag
   */
  const deleteTag = async (id) => {
    try {
      const response = await leadsService.deleteLeadTag(id);

      if (response.success) {
        const index = leadTags.value.findIndex((tag) => tag.id === id);
        if (index !== -1) {
          leadTags.value.splice(index, 1);
        }
      }

      return response;
    } catch (err) {
      error.value = err.message || "An error occurred while deleting tag";
      throw err;
    }
  };

  /**
   * Assign tag to lead
   */
  const assignTag = async (leadId, tagId) => {
    try {
      const response = await leadsService.assignTagToLead(leadId, tagId);

      if (response.success) {
        // Update lead's tags in the list
        const lead = leads.value.find((l) => l.leadId === leadId);
        if (lead && lead.tags) {
          const tag = leadTags.value.find((t) => t.id === tagId);
          if (tag && !lead.tags.some((t) => t.id === tagId)) {
            lead.tags.push(tag);
          }
        }
      }

      return response;
    } catch (err) {
      error.value = err.message || "An error occurred while assigning tag";
      throw err;
    }
  };

  /**
   * Remove tag from lead
   */
  const removeTag = async (leadId, tagId) => {
    try {
      const response = await leadsService.removeTagFromLead(leadId, tagId);

      if (response.success) {
        // Update lead's tags in the list
        const lead = leads.value.find((l) => l.leadId === leadId);
        if (lead && lead.tags) {
          lead.tags = lead.tags.filter((t) => t.id !== tagId);
        }
      }

      return response;
    } catch (err) {
      error.value = err.message || "An error occurred while removing tag";
      throw err;
    }
  };

  /**
   * Bulk assign tag
   */
  const bulkAssign = async (leadIds, tagId) => {
    try {
      const response = await leadsService.bulkAssignTag(leadIds, tagId);

      if (response.success) {
        const tag = leadTags.value.find((t) => t.id === tagId);

        // Update tags for all selected leads
        leadIds.forEach((leadId) => {
          const lead = leads.value.find((l) => l.leadId === leadId);
          if (lead && tag) {
            if (!lead.tags) {
              lead.tags = [];
            }
            if (!lead.tags.some((t) => t.id === tagId)) {
              lead.tags.push(tag);
            }
          }
        });
      }

      return response;
    } catch (err) {
      error.value = err.message || "An error occurred during bulk assignment";
      throw err;
    }
  };

  /**
   * Fetch search tags
   */
  const fetchSearchTags = async (activeOnly = true) => {
    try {
      const response = await leadsService.getSearchTags(activeOnly);

      if (response.success) {
        searchTags.value = response.data;
      }

      return response;
    } catch (err) {
      error.value =
        err.message || "An error occurred while fetching search tags";
      throw err;
    }
  };

  /**
   * Create search tag
   */
  const createSearchTag = async (data) => {
    try {
      const response = await leadsService.createSearchTag(data);

      if (response.success) {
        searchTags.value.push(response.data);
      }

      return response;
    } catch (err) {
      error.value =
        err.message || "An error occurred while creating search tag";
      throw err;
    }
  };

  /**
   * Delete search tag
   */
  const deleteSearchTag = async (id) => {
    try {
      const response = await leadsService.deleteSearchTag(id);

      if (response.success) {
        const index = searchTags.value.findIndex((tag) => tag.id === id);
        if (index !== -1) {
          searchTags.value.splice(index, 1);
        }
      }

      return response;
    } catch (err) {
      error.value =
        err.message || "An error occurred while deleting search tag";
      throw err;
    }
  };

  /**
   * Export leads
   */
  const exportLeadsData = async (leadIds, format) => {
    try {
      const response = await leadsService.exportLeads(leadIds, format);
      return response;
    } catch (err) {
      error.value = err.message || "An error occurred while exporting leads";
      throw err;
    }
  };

  /**
   * Toggle lead selection
   */
  const toggleLeadSelection = (leadId) => {
    const lead = leads.value.find((l) => l.leadId === leadId);
    if (lead) {
      lead.selected = !lead.selected;
    }
  };

  /**
   * Toggle all leads selection
   */
  const toggleAllSelection = (selected) => {
    leads.value.forEach((lead) => {
      lead.selected = selected;
    });
  };

  /**
   * Clear selection
   */
  const clearSelection = () => {
    leads.value.forEach((lead) => {
      lead.selected = false;
    });
  };

  /**
   * Reset store
   */
  const reset = () => {
    leads.value = [];
    currentLead.value = null;
    leadTags.value = [];
    searchTags.value = [];
    statistics.value = {
      leads: {
        totalLeads: 0,
        todayLeads: 0,
        verifiedLeads: 0,
        activeLeads: 0,
      },
      kyc: {
        not_started: 0,
        in_progress: 0,
        pending_review: 0,
        approved: 0,
        rejected: 0,
        total: 0,
      },
    };
    pagination.value = {
      total: 0,
      page: 1,
      per_page: 10,
      total_pages: 0,
    };
    loading.value = false;
    error.value = null;
  };

  return {
    // State
    leads,
    currentLead,
    leadTags,
    searchTags,
    statistics,
    pagination,
    loading,
    error,

    // Getters
    selectedLeads,
    selectedLeadIds,
    hasSelection,

    // Actions
    fetchLeads,
    fetchLead,
    updateLeadInfo,
    updateStatus,
    fetchStatistics,
    fetchLeadTags,
    createTag,
    deleteTag,
    assignTag,
    removeTag,
    bulkAssign,
    fetchSearchTags,
    createSearchTag,
    deleteSearchTag,
    exportLeadsData,
    toggleLeadSelection,
    toggleAllSelection,
    clearSelection,
    reset,
  };
});
