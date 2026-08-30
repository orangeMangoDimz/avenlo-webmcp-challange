<template>
  <div class="container">
    <div class="page-header">
      <div class="page-title">
        <h1>{{ t("page_withdrawKycTemplateList_title") }}</h1>
        <p>{{ t("page_withdrawKycTemplateList_sub") }}</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <div class="stats-header">
      <div>
        <h2
          style="font-size: 20px; color: var(--color-ink); margin-bottom: 5px"
        >
          {{ t("withdrawKycTpl_stats_heading") }}
        </h2>
        <p style="font-size: 14px; color: var(--color-muted)">
          {{ t("withdrawKycTpl_stats_sub") }}
        </p>
      </div>
      <div class="page-stats">
        <div class="stat-badge">
          <i class="fas fa-file-alt"></i>
          <span>{{
            tParams(
              "withdrawKycTpl_stat_totalTemplates",
              "{n} Total Templates",
              { n: formatNumber(statistics.totalTemplates || 0) },
            )
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
            tParams("withdrawKycTpl_stat_active", "{n} Active", {
              n: formatNumber(statistics.activeTemplates || 0),
            })
          }}</span>
        </div>
      </div>
    </div>

    <div class="templates-table-container">
      <div class="table-header">
        <div class="table-header-left">
          <h2>{{ t("withdrawKycTpl_tableTitle") }}</h2>
        </div>
        <div class="table-header-right">
          <div class="rows-selector">
            <label>{{ t("withdrawKycTpl_showRows") }}</label>
            <select v-model="rowsPerPage" @change="changeRowsPerPage">
              <option value="5">{{ t("withdrawKycTpl_rows_5") }}</option>
              <option value="10">{{ t("withdrawKycTpl_rows_10") }}</option>
              <option value="20">{{ t("withdrawKycTpl_rows_20") }}</option>
              <option value="all">{{ t("withdrawKycTpl_rows_all") }}</option>
            </select>
          </div>
        </div>
      </div>

      <table class="templates-table">
        <thead>
          <tr>
            <th>{{ t("withdrawKycTpl_col_templateName") }}</th>
            <th>{{ t("withdrawKycTpl_col_paymentMethods") }}</th>
            <th>{{ t("withdrawKycTpl_col_questions") }}</th>
            <th>{{ t("withdrawKycTpl_col_rules") }}</th>
            <th>{{ t("withdrawKycTpl_col_status") }}</th>
            <th>{{ t("withdrawKycTpl_col_lastUpdated") }}</th>
            <th>{{ t("withdrawKycTpl_col_action") }}</th>
          </tr>
        </thead>
        <tbody>
          <template
            v-for="template in displayedTemplates"
            :key="template.templateId"
          >
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
                <div class="payment-method-tags">
                  <span
                    v-for="method in getDisplayPaymentMethods(template)"
                    :key="method.gatewaySettingId || method.gatewayName"
                    :class="[
                      'payment-method-badge',
                      method.methodType || 'fiat',
                    ]"
                  >
                    <i :class="getPaymentMethodIconClass(method)"></i>
                    {{ method.gatewayName }}
                  </span>
                </div>
              </td>
              <td>
                {{
                  tParams("withdrawKycTpl_questionsCount", "{n} Questions", {
                    n: formatNumber(template.totalQuestions || 0),
                  })
                }}
              </td>
              <td>
                {{
                  tParams("withdrawKycTpl_rulesCount", "{n} Rules", {
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
                        ? t("withdrawKycTpl_btnHide")
                        : t("withdrawKycTpl_btnDetail")
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
                    {{ t("withdrawKycTpl_btnDelete") }}
                  </button>
                </div>
              </td>
            </tr>

            <tr
              v-if="expandedRows.includes(template.templateId)"
              class="detail-row show"
            >
              <td colspan="7">
                <WithdrawTemplateDetail
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
                {{ t("withdrawKycTpl_empty") }}
              </p>
            </td>
          </tr>

          <tr v-if="loading">
            <td colspan="7" style="text-align: center; padding: 60px 20px">
              <i
                class="fas fa-spinner fa-spin"
                style="font-size: 32px; color: var(--color-brand)"
              ></i>
              <p style="margin-top: 15px; color: var(--color-muted)">
                {{ t("withdrawKycTpl_loading") }}
              </p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { ref, computed, onMounted } from "vue";
import { useAuthStore } from "@/stores/auth";
import { withdrawKycTemplateService } from "@/services/withdrawKycTemplateService";
import WithdrawTemplateDetail from "@/components/withdrawals/WithdrawTemplateDetail.vue";
import { formatNumber } from "@/utils/helpers";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";

const { t, tParams, languageStore } = useAdminI18n();

const PAYMENT_METHOD_ICON_CLASS = "fas fa-wallet";
const INVALID_PAYMENT_METHOD_ICON_CLASSES = new Set([
  "fas fas-bee",
  "fas fa-bee",
]);

const authStore = useAuthStore();

const hasEditTemplatePermission = computed(() =>
  authStore.hasPermission("page_withdrawkyctemplates_edittemplate"),
);
const hasDeleteTemplatePermission = computed(() =>
  authStore.hasPermission("page_withdrawkyctemplates_deletetemplate"),
);
const hasAddCategoryPermission = computed(() =>
  authStore.hasPermission("page_withdrawkyctemplates_addcategory"),
);
const hasEditCategoryPermission = computed(() =>
  authStore.hasPermission("page_withdrawkyctemplates_editcategory"),
);
const hasDeleteCategoryPermission = computed(() =>
  authStore.hasPermission("page_withdrawkyctemplates_deletecategory"),
);
const hasAddQuestionPermission = computed(() =>
  authStore.hasPermission("page_withdrawkyctemplates_addquestion"),
);
const hasEditQuestionPermission = computed(() =>
  authStore.hasPermission("page_withdrawkyctemplates_editquestion"),
);
const hasDuplicateQuestionPermission = computed(() =>
  authStore.hasPermission("page_withdrawkyctemplates_duplicatequestion"),
);
const hasDeleteQuestionPermission = computed(() =>
  authStore.hasPermission("page_withdrawkyctemplates_deletequestion"),
);
const hasAddRulePermission = computed(() =>
  authStore.hasPermission("page_withdrawkyctemplates_addrule"),
);
const hasEditRulePermission = computed(() =>
  authStore.hasPermission("page_withdrawkyctemplates_editrule"),
);
const hasDeleteRulePermission = computed(() =>
  authStore.hasPermission("page_withdrawkyctemplates_deleterule"),
);
const hasAddLegalDocumentPermission = computed(() =>
  authStore.hasPermission("page_withdrawkyctemplates_addlegaldocument"),
);
const hasEditLegalDocumentPermission = computed(() =>
  authStore.hasPermission("page_withdrawkyctemplates_editlegaldocument"),
);
const hasDeleteLegalDocumentPermission = computed(() =>
  authStore.hasPermission("page_withdrawkyctemplates_deletelegaldocument"),
);
const hasRequireLegalDocumentPermission = computed(() =>
  authStore.hasPermission("page_withdrawkyctemplates_requirelegaldocument"),
);

const templates = ref([]);
const statistics = ref({});
const loading = ref(false);
const expandedRows = ref([]);
const rowsPerPage = ref("10");

const displayedTemplates = computed(() => {
  if (rowsPerPage.value === "all") {
    return templates.value;
  }
  return templates.value.slice(0, parseInt(rowsPerPage.value, 10));
});

const loadTemplates = async () => {
  loading.value = true;
  try {
    const response = await withdrawKycTemplateService.getTemplates();
    if (response.success) {
      templates.value = response.data.templates;
    }
  } catch (error) {
    console.error("Failed to load withdraw KYC templates:", error);
    const data = error?.response?.data ?? error;
    const rawMsg =
      data?.message || error?.message || t("withdrawKycTpl_alert_loadFailed");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(msg);
  } finally {
    loading.value = false;
  }
};

const loadStatistics = async () => {
  try {
    const response = await withdrawKycTemplateService.getStatistics();
    if (response.success) {
      statistics.value = response.data;
    }
  } catch (error) {
    console.error("Failed to load withdraw KYC statistics:", error);
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
  expandedRows.value = [];
};

const getDisplayPaymentMethods = (template) => {
  if (
    Array.isArray(template.paymentMethods) &&
    template.paymentMethods.length > 0
  ) {
    return template.paymentMethods;
  }

  if (template.gatewayName || template.gatewaySettingId) {
    return [
      {
        gatewayName:
          template.gatewayName ||
          tParams("withdrawKycTpl_methodId", "Method #{id}", {
            id: String(template.gatewaySettingId),
          }),
        gatewaySettingId: template.gatewaySettingId,
        iconClass: template.iconClass || template.iconclass,
        methodType: template.methodType || "fiat",
      },
    ];
  }

  return [
    {
      gatewayName: t("withdrawKycTpl_paymentNotSpecified"),
      gatewaySettingId: "unknown",
      iconClass: PAYMENT_METHOD_ICON_CLASS,
      methodType: "fiat",
    },
  ];
};

const getPaymentMethodIconClass = (method) => {
  const iconClass = `${method?.iconClass || method?.iconclass || ""}`.trim();
  if (!iconClass || INVALID_PAYMENT_METHOD_ICON_CLASSES.has(iconClass)) {
    return PAYMENT_METHOD_ICON_CLASS;
  }

  return iconClass;
};

const formatStatus = (status) => {
  const key = String(status || "").toLowerCase();
  const map = {
    active: "withdrawKycTpl_status_active",
    inactive: "withdrawKycTpl_status_inactive",
    draft: "withdrawKycTpl_status_draft",
  };
  const tr = map[key];
  return tr ? t(tr) : status;
};

const formatDate = (dateString) => {
  if (!dateString) return t("leads_na");
  const date = new Date(dateString);
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return date.toLocaleDateString(loc, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const handleDeleteTemplate = async (templateId, templateName) => {
  if (
    !confirm(
      tParams("withdrawKycTpl_confirm_delete", "Are you sure...", {
        name: templateName,
      }),
    )
  ) {
    return;
  }

  try {
    const response =
      await withdrawKycTemplateService.deleteTemplate(templateId);
    if (response.success) {
      alert(t("withdrawKycTpl_alert_deleteOk"));
      const index = expandedRows.value.indexOf(templateId);
      if (index > -1) {
        expandedRows.value.splice(index, 1);
      }
      await loadTemplates();
      await loadStatistics();
    } else {
      const raw = response.message || t("common_unknownError");
      alert(
        tParams("withdrawKycTpl_alert_deleteFailed", "Failed: {msg}", {
          msg: translateApiErrorMessage(response.errorCode, raw),
        }),
      );
    }
  } catch (error) {
    console.error("Failed to delete withdraw KYC template:", error);
    const data = error?.response?.data ?? error;
    const rawMsg = data?.message || error?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams("withdrawKycTpl_alert_deleteFailed", "Failed: {msg}", { msg }),
    );
  }
};

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
}

.template-description {
  font-size: 13px;
  color: var(--color-muted);
}

.payment-method-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.payment-method-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.payment-method-badge.crypto {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.payment-method-badge.fiat {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.status-badge {
  display: inline-block;
  padding: 5px 12px;
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

.action-buttons {
  display: flex;
  gap: 8px;
}

.btn-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-detail {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.btn-detail:hover {
  background: var(--color-border);
}

.btn-delete {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.btn-delete:hover {
  background: var(--color-danger-soft);
}

.detail-row td {
  padding: 0;
}

@media (max-width: 1024px) {
  .stats-header,
  .page-header,
  .table-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
}
</style>
