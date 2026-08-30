<template>
  <div class="container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-title">
        <h1>{{ t("page_kycTemplateList_title") }}</h1>
        <p>{{ t("kycTplList_sub") }}</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- Statistics Header -->
    <div class="stats-header">
      <div>
        <h2
          style="font-size: 20px; color: var(--color-ink); margin-bottom: 5px"
        >
          {{ t("kycTplList_stats_heading") }}
        </h2>
        <p style="font-size: 14px; color: var(--color-muted)">
          {{ t("kycTplList_stats_sub") }}
        </p>
      </div>
      <div class="page-stats">
        <div class="stat-badge">
          <i class="fas fa-file-alt"></i>
          <span>{{
            tParams("kycTplList_stat_total", "{n} Total Templates", {
              n: formatNumber(statistics.totalTemplates || 0),
            })
          }}</span>
        </div>
        <div
          class="stat-badge"
          style="
            background: var(--color-success-soft);
            color: var(--color-success);
          "
        >
          <i class="fas fa-check-circle"></i>
          <span>{{
            tParams("kycTplList_stat_active", "{n} Active", {
              n: formatNumber(statistics.activeTemplates || 0),
            })
          }}</span>
        </div>
      </div>
    </div>

    <!-- Templates Table -->
    <div class="templates-table-container">
      <div class="table-header">
        <div class="table-header-left">
          <h2>{{ t("kycTplList_table_title") }}</h2>
        </div>
        <div class="table-header-right">
          <button
            v-if="hasAddTemplatePermission"
            class="btn btn-success"
            @click="openCreateTemplateModal"
          >
            <i class="fas fa-plus"></i> {{ t("kycTplList_btn_newTemplate") }}
          </button>
          <div class="rows-selector">
            <label>{{ t("kycTplList_showRows") }}</label>
            <select v-model="rowsPerPage" @change="changeRowsPerPage">
              <option value="5">{{ t("kycTplList_rows_5") }}</option>
              <option value="10">{{ t("kycTplList_rows_10") }}</option>
              <option value="20">{{ t("kycTplList_rows_20") }}</option>
              <option value="all">{{ t("kycTplList_rows_all") }}</option>
            </select>
          </div>
        </div>
      </div>

      <table class="templates-table">
        <thead>
          <tr>
            <th>{{ t("kycTplList_col_templateName") }}</th>
            <th>{{ t("kycTplList_col_countries") }}</th>
            <th>{{ t("kycTplList_col_questions") }}</th>
            <th>{{ t("kycTplList_col_rules") }}</th>
            <th>{{ t("kycTplList_col_status") }}</th>
            <th>{{ t("kycTplList_col_updated") }}</th>
            <th>{{ t("kycTplList_col_action") }}</th>
          </tr>
        </thead>
        <tbody>
          <template
            v-for="(template, index) in displayedTemplates"
            :key="template.templateId"
          >
            <!-- Main Row -->
            <tr
              :data-template-id="template.templateId"
              :class="{ expanded: expandedRows.includes(template.templateId) }"
            >
              <td>
                <div class="template-info">
                  <div class="template-name">{{ template.templateName }}</div>
                  <div class="template-description">
                    {{ template.description }}
                  </div>
                </div>
              </td>
              <td>
                <div class="country-tags">
                  <span
                    v-for="(country, idx) in getDisplayCountries(
                      template.countries,
                    )"
                    :key="idx"
                    class="country-tag"
                  >
                    <i class="fas fa-flag"></i> {{ country }}
                  </span>
                </div>
              </td>
              <td>
                {{
                  tParams("kycTplList_questionsCount", "{n} Questions", {
                    n: template.totalQuestions || 0,
                  })
                }}
              </td>
              <td>
                {{
                  tParams("kycTplList_rulesCount", "{n} Rules", {
                    n: formatNumber(template.totalRules || 0),
                  })
                }}
              </td>
              <td>
                <span class="status-badge" :class="template.status">{{
                  formatStatus(template.status)
                }}</span>
              </td>
              <td>{{ formatDate(template.updatedAt) }}</td>
              <td>
                <div class="action-buttons">
                  <button
                    class="btn-action btn-detail"
                    @click="toggleDetail(template.templateId)"
                  >
                    <i
                      class="fas"
                      :class="
                        expandedRows.includes(template.templateId)
                          ? 'fa-chevron-up'
                          : 'fa-chevron-down'
                      "
                    ></i>
                    {{
                      expandedRows.includes(template.templateId)
                        ? t("kycTplList_btn_hide")
                        : t("kycTplList_btn_detail")
                    }}
                  </button>
                  <button
                    v-if="hasDeleteTemplatePermission"
                    class="btn-action btn-delete"
                    @click="
                      handleDeleteTemplate(
                        template.templateId,
                        template.templateName,
                      )
                    "
                  >
                    <i class="fas fa-trash"></i>
                    {{ t("kycTplList_btn_delete") }}
                  </button>
                </div>
              </td>
            </tr>

            <!-- Detail Row -->
            <tr
              v-if="expandedRows.includes(template.templateId)"
              class="detail-row show"
            >
              <td colspan="7">
                <TemplateDetail
                  :template="template"
                  :has-edit-template-permission="hasEditTemplatePermission"
                  :has-add-category-permission="hasAddCategoryPermission"
                  :has-edit-category-permission="hasEditCategoryPermission"
                  :has-delete-category-permission="hasDeleteCategoryPermission"
                  :has-add-question-permission="hasAddQuestionPermission"
                  :has-edit-question-permission="hasEditQuestionPermission"
                  :has-duplicate-question-permission="
                    hasDuplicateQuestionPermission
                  "
                  :has-delete-question-permission="hasDeleteQuestionPermission"
                  :has-add-rule-permission="hasAddRulePermission"
                  :has-edit-rule-permission="hasEditRulePermission"
                  :has-delete-rule-permission="hasDeleteRulePermission"
                  :has-require-legal-document-permission="
                    hasRequireLegalDocumentPermission
                  "
                  :has-add-legal-document-permission="
                    hasAddLegalDocumentPermission
                  "
                  :has-edit-legal-document-permission="
                    hasEditLegalDocumentPermission
                  "
                  :has-delete-legal-document-permission="
                    hasDeleteLegalDocumentPermission
                  "
                  @refresh="loadTemplates"
                  @close="toggleDetail(template.templateId)"
                />
              </td>
            </tr>
          </template>

          <!-- Empty State -->
          <tr v-if="templates.length === 0 && !loading">
            <td
              colspan="7"
              style="
                text-align: center;
                padding: 60px 20px;
                color: var(--color-muted);
              "
            >
              <i
                class="fas fa-inbox"
                style="font-size: 48px; margin-bottom: 15px; display: block"
              ></i>
              <p style="font-size: 16px; font-weight: 600">
                {{ t("kycTplList_empty_title") }}
              </p>
              <p style="font-size: 14px; margin-top: 8px">
                {{ t("kycTplList_empty_sub") }}
              </p>
            </td>
          </tr>

          <!-- Loading State -->
          <tr v-if="loading">
            <td colspan="7" style="text-align: center; padding: 60px 20px">
              <i
                class="fas fa-spinner fa-spin"
                style="font-size: 32px; color: var(--color-brand)"
              ></i>
              <p style="margin-top: 15px; color: var(--color-muted)">
                {{ t("kycTplList_loading") }}
              </p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create/Edit Template Modal -->
    <TemplateModal
      v-if="showTemplateModal"
      :template="currentTemplate"
      @close="closeTemplateModal"
      @save="handleSaveTemplate"
    />
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { ref, computed, onMounted } from "vue";
import { useAuthStore } from "@/stores/auth";
import { kycTemplateService } from "@/services/kycTemplateService";
import TemplateDetail from "@/components/kyc/TemplateDetail.vue";
import TemplateModal from "@/components/kyc/TemplateModal.vue";
import { formatNumber } from "@/utils/helpers";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams, languageStore } = useAdminI18n();
const dateLocale = () =>
  languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";

const authStore = useAuthStore();

// 权限检查
const hasReadonlyPermission = computed(() =>
  authStore.hasPermission("page_kyctemplates_readonly"),
);
const hasAddTemplatePermission = computed(() =>
  authStore.hasPermission("page_kyctemplates_addtemplate"),
);
const hasEditTemplatePermission = computed(() =>
  authStore.hasPermission("page_kyctemplates_edittemplate"),
);
const hasDeleteTemplatePermission = computed(() =>
  authStore.hasPermission("page_kyctemplates_deletetemplate"),
);
const hasAddCategoryPermission = computed(() =>
  authStore.hasPermission("page_kyctemplates_addcategory"),
);
const hasEditCategoryPermission = computed(() =>
  authStore.hasPermission("page_kyctemplates_editcategory"),
);
const hasDeleteCategoryPermission = computed(() =>
  authStore.hasPermission("page_kyctemplates_deletecategory"),
);
const hasAddQuestionPermission = computed(() =>
  authStore.hasPermission("page_kyctemplates_addquestion"),
);
const hasEditQuestionPermission = computed(() =>
  authStore.hasPermission("page_kyctemplates_editquestion"),
);
const hasDuplicateQuestionPermission = computed(() =>
  authStore.hasPermission("page_kyctemplates_duplicatequestion"),
);
const hasDeleteQuestionPermission = computed(() =>
  authStore.hasPermission("page_kyctemplates_deletequestion"),
);
const hasAddRulePermission = computed(() =>
  authStore.hasPermission("page_kyctemplates_addrule"),
);
const hasEditRulePermission = computed(() =>
  authStore.hasPermission("page_kyctemplates_editrule"),
);
const hasDeleteRulePermission = computed(() =>
  authStore.hasPermission("page_kyctemplates_deleterule"),
);
const hasAddLegalDocumentPermission = computed(() =>
  authStore.hasPermission("page_kyctemplates_addlegaldocument"),
);
const hasEditLegalDocumentPermission = computed(() =>
  authStore.hasPermission("page_kyctemplates_editlegaldocument"),
);
const hasDeleteLegalDocumentPermission = computed(() =>
  authStore.hasPermission("page_kyctemplates_deletelegaldocument"),
);
const hasRequireLegalDocumentPermission = computed(() =>
  authStore.hasPermission("page_kyctemplates_requirelegaldocument"),
);

// Reactive state
const templates = ref([]);
const statistics = ref({});
const loading = ref(false);
const expandedRows = ref([]);
const rowsPerPage = ref("10");
const showTemplateModal = ref(false);
const currentTemplate = ref(null);

// Computed
const displayedTemplates = computed(() => {
  if (rowsPerPage.value === "all") {
    return templates.value;
  }
  return templates.value.slice(0, parseInt(rowsPerPage.value));
});

// Methods
const loadTemplates = async () => {
  loading.value = true;
  try {
    const response = await kycTemplateService.getTemplates();
    if (response.success) {
      templates.value = response.data.templates;
    }
  } catch (error) {
    console.error("Failed to load templates:", error);
    alert(t("kycTplList_alert_loadFailed"));
  } finally {
    loading.value = false;
  }
};

const loadStatistics = async () => {
  try {
    const response = await kycTemplateService.getStatistics();
    if (response.success) {
      statistics.value = response.data;
    }
  } catch (error) {
    console.error("Failed to load statistics:", error);
  }
};

const toggleDetail = (templateId) => {
  const index = expandedRows.value.indexOf(templateId);
  if (index > -1) {
    expandedRows.value.splice(index, 1);
  } else {
    expandedRows.value.push(templateId);
  }
};

const changeRowsPerPage = () => {
  // Close all expanded rows when changing rows per page
  expandedRows.value = [];
};

const getDisplayCountries = (countries) => {
  if (!countries || countries.length === 0)
    return [t("kycTplList_notSpecified")];
  if (countries.length <= 2) return countries.map((c) => c.countryName || c);
  return [
    ...countries.slice(0, 2).map((c) => c.countryName || c),
    tParams("kycTplList_moreCountries", "+{n} more", {
      n: countries.length - 2,
    }),
  ];
};

const formatStatus = (status) => {
  if (status === "active") return t("kycTpl_status_active");
  if (status === "inactive") return t("kycTpl_status_inactive");
  if (status === "draft") return t("kycTpl_status_draft");
  return status;
};

const formatDate = (dateString) => {
  if (!dateString) return t("addrVerif_na");
  const date = new Date(dateString);
  return date.toLocaleDateString(dateLocale(), {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const openCreateTemplateModal = () => {
  currentTemplate.value = null;
  showTemplateModal.value = true;
};

const closeTemplateModal = () => {
  showTemplateModal.value = false;
  currentTemplate.value = null;
};

const handleSaveTemplate = async (templateData) => {
  try {
    const response = await kycTemplateService.createTemplate(templateData);
    if (response.success) {
      alert(t("kycTplList_alert_createOk"));
      await loadTemplates();
      await loadStatistics();
      closeTemplateModal();
    } else {
      alert(
        tParams(
          "kycTplList_alert_createFailed",
          "Failed to create template: {msg}",
          { msg: response.message || t("common_unknownError") },
        ),
      );
    }
  } catch (error) {
    console.error("Failed to save template:", error);
    alert(t("kycTplList_alert_saveFailed"));
  }
};

const handleDeleteTemplate = async (templateId, templateName) => {
  if (
    !confirm(
      tParams("kycTplList_confirm_delete", "Delete?", { name: templateName }),
    )
  ) {
    return;
  }

  try {
    const response = await kycTemplateService.deleteTemplate(templateId);
    if (response.success) {
      alert(t("kycTplList_alert_deleteOk"));
      // 如果删除的模板是展开的，关闭它
      const index = expandedRows.value.indexOf(templateId);
      if (index > -1) {
        expandedRows.value.splice(index, 1);
      }
      await loadTemplates();
      await loadStatistics();
    } else {
      alert(
        tParams("kycTplList_alert_deleteCannot", "Cannot delete: {msg}", {
          msg: response.message || t("common_unknownError"),
        }),
      );
    }
  } catch (error) {
    console.error("Failed to delete template:", error);
    const message =
      error?.message ||
      error?.response?.data?.message ||
      t("kycTplList_alert_deleteFailed");
    alert(message);
  }
};

// Lifecycle
onMounted(async () => {
  await loadTemplates();
  await loadStatistics();
});
</script>

<style scoped>
.container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 40px 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.page-title h1 {
  font-size: 28px;
  color: var(--color-ink);
  margin-bottom: 5px;
}

.page-title p {
  font-size: 14px;
  color: var(--color-muted);
}

.page-actions {
  display: flex;
  align-items: center;
  gap: 20px;
}

.stats-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.page-stats {
  display: flex;
  gap: 15px;
}

.stat-badge {
  background: var(--color-brand-soft);
  color: var(--color-brand);
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
}

.stat-badge i {
  font-size: 16px;
}

.templates-table-container {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.table-header {
  padding: 20px 30px;
  background: var(--color-surface-soft);
  border-bottom: 2px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.table-header h2 {
  font-size: 18px;
  color: var(--color-ink);
}

.table-header-left {
  display: flex;
  align-items: center;
  gap: 20px;
  flex: 1;
}

.table-header-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.rows-selector {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: var(--color-text);
}

.rows-selector label {
  font-weight: 600;
}

.rows-selector select {
  padding: 8px 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-size: 14px;
  cursor: pointer;
  outline: none;
  transition: all 0.3s ease;
}

.rows-selector select:hover {
  border-color: var(--color-brand);
}

.rows-selector select:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.templates-table {
  width: 100%;
  border-collapse: collapse;
}

.templates-table thead {
  background: var(--color-surface-soft);
}

.templates-table th {
  padding: 16px 20px;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
}

.templates-table tbody tr {
  border-bottom: 1px solid var(--color-border);
  transition: all 0.2s ease;
}

.templates-table tbody tr:hover:not(.detail-row) {
  background: var(--color-surface-soft);
}

.templates-table tbody tr.expanded {
  background: var(--color-brand-soft);
}

.templates-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
}

.template-info {
  display: flex;
  flex-direction: column;
}

.template-name {
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 4px;
  font-size: 15px;
}

.template-description {
  font-size: 12px;
  color: var(--color-muted);
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.active {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.inactive {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.status-badge.draft {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.detail-row {
  display: none;
}

.detail-row.show {
  display: table-row;
}

.country-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.country-tag {
  background: var(--color-brand-soft);
  color: var(--color-brand);
  padding: 4px 10px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.btn {
  padding: 12px 20px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-success {
  background: var(--color-success-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(72, 187, 120, 0.3);
}

.btn-success:hover {
  background: var(--color-success-solid);
  transform: translateY(-2px);
}

.btn-action {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-detail {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.btn-detail:hover {
  background: var(--color-brand-solid);
  color: white;
}

.action-buttons {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-delete {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.btn-delete:hover {
  background: var(--color-danger-border);
  color: white;
}

@media (max-width: 768px) {
  .container {
    padding: 20px 15px;
  }

  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
  }

  .page-actions {
    width: 100%;
    justify-content: flex-end;
  }

  .stats-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }

  .page-stats {
    flex-direction: column;
    width: 100%;
  }

  .stat-badge {
    width: 100%;
    justify-content: center;
  }

  .table-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }

  .table-header-left,
  .table-header-right {
    width: 100%;
  }

  .table-header-right {
    justify-content: space-between;
  }

  .templates-table {
    font-size: 12px;
  }

  .templates-table th,
  .templates-table td {
    padding: 12px 10px;
  }
}
</style>
