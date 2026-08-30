<template>
  <div class="custom-report-page">
    <div v-if="!hasReadonlyPermission" class="error-container">
      <i class="fas fa-lock"></i>
      <p>
        {{
          t(
            "common_noPermission",
            "You do not have permission to view this page.",
          )
        }}
      </p>
    </div>

    <template v-else>
      <div class="page-header">
        <div class="page-title">
          <button type="button" class="btn-back" @click="goBack">
            <i class="fas fa-arrow-left"></i>
          </button>
          <div>
            <h1>{{ t("customReport_stack_title", "Sales Coverage") }}</h1>
            <p>
              {{
                t(
                  "customReport_stack_sub",
                  "Accounts, sales managers, and trading groups.",
                )
              }}
            </p>
          </div>
        </div>
        <div class="page-actions">
          <PageHeaderActions />
        </div>
      </div>

      <ExportProgressBanner
        v-if="exportBannerVisible && exportStatusText"
        :cancelling="exportCancelling"
        :status-text="exportStatusText"
        :percent="exportProgressPercent"
        :cancel-disabled="!exportJobId"
        :title="t('ibReport_exportInProgressTitle', 'Export in progress')"
        :cancelling-title="
          t('ibReport_exportCancelling', 'Cancelling export...')
        "
        :cancel-label="t('customReport_cancel', 'Cancel')"
        @cancel-export="cancelActiveExport"
      />

      <div
        v-if="exportColumnModal.visible"
        class="modal-overlay"
        @click.self="closeExportColumnModal"
      >
        <div class="modal-card modal-card-types">
          <div class="modal-card-head">
            <h3>
              {{ t("customReport_exportColumnsTitle", "Export columns") }}
            </h3>
            <button
              type="button"
              class="filter-icon-btn"
              @click="closeExportColumnModal"
            >
              <i class="fas fa-times"></i>
            </button>
          </div>
          <p>
            {{
              t(
                "customReport_exportColumnsHint",
                "Choose whether to export every column or only the ones you pick.",
              )
            }}
          </p>
          <div class="export-column-mode-grid">
            <button
              type="button"
              class="export-column-mode-option"
              :class="{ active: exportColumnModal.mode === 'all' }"
              @click="setExportColumnMode('all')"
            >
              <span class="export-column-mode-icon"
                ><i class="fas fa-th"></i
              ></span>
              <span class="export-column-mode-copy">
                <strong>{{
                  t("customReport_exportAllColumns", "All columns")
                }}</strong>
                <small>{{
                  t(
                    "customReport_exportAllColumnsHint",
                    "Include every field from this data source.",
                  )
                }}</small>
              </span>
            </button>
            <button
              type="button"
              class="export-column-mode-option"
              :class="{ active: exportColumnModal.mode === 'specific' }"
              @click="setExportColumnMode('specific')"
            >
              <span class="export-column-mode-icon"
                ><i class="fas fa-tasks"></i
              ></span>
              <span class="export-column-mode-copy">
                <strong>{{
                  t("customReport_exportSpecificColumns", "Specific columns")
                }}</strong>
                <small>{{
                  t(
                    "customReport_exportSpecificColumnsHint",
                    "Pick which fields to include in the file.",
                  )
                }}</small>
              </span>
            </button>
          </div>
          <div
            v-if="exportColumnModal.mode === 'specific'"
            class="export-column-picker"
          >
            <div class="filter-property-search">
              <i class="fas fa-search"></i>
              <input
                v-model="exportColumnModal.search"
                type="text"
                :placeholder="t('customReport_filterBy', 'Filter by...')"
              />
            </div>
            <div class="column-toggle-list export-column-list">
              <label class="column-toggle-item column-toggle-all">
                <input
                  type="checkbox"
                  :checked="allExportColumnsChecked"
                  :indeterminate.prop="
                    someExportColumnsChecked && !allExportColumnsChecked
                  "
                  @change="toggleAllExportColumns($event.target.checked)"
                />
                <span>{{
                  t("customReport_toggleAllColumns", "Toggle all")
                }}</span>
              </label>
              <label
                v-for="col in filteredExportPickerColumns"
                :key="col.field"
                class="column-toggle-item"
              >
                <input
                  type="checkbox"
                  :checked="!!exportColumnModal.selected[col.field]"
                  @change="toggleExportColumn(col.field, $event.target.checked)"
                />
                <span class="filter-property-icon">{{ col.icon }}</span>
                <span>{{ col.label }}</span>
              </label>
              <p
                v-if="filteredExportPickerColumns.length === 0"
                class="filter-empty-hint"
              >
                {{ t("customReport_noColumns", "No columns found.") }}
              </p>
            </div>
          </div>
          <div class="modal-actions">
            <button
              type="button"
              class="btn btn-primary"
              :disabled="!canConfirmExportColumns"
              @click="confirmExportColumns"
            >
              {{ t("customReport_exportAll", "Export All") }}
            </button>
            <button
              type="button"
              class="btn btn-secondary"
              @click="closeExportColumnModal"
            >
              {{ t("customReport_cancel", "Cancel") }}
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="exportModal.visible"
        class="export-modal-overlay"
        @click="onExportModalContinue"
      >
        <div class="export-modal" @click.stop>
          <div class="export-modal-header">
            <h3>
              {{ t("ibReport_exportInProgressTitle", "Export in progress") }}
            </h3>
            <button
              type="button"
              class="export-modal-close"
              @click="onExportModalContinue"
            >
              ×
            </button>
          </div>
          <div class="export-modal-body">
            <p class="export-modal-text">
              {{
                exportModal.message ||
                t(
                  "ibReport_exportInProgressMsg",
                  "Your export is running. You can continue working.",
                )
              }}
            </p>
            <div class="export-modal-progress">
              <div
                class="export-modal-progress-bar"
                :style="{ width: `${exportModal.percent || 0}%` }"
              ></div>
            </div>
            <p class="export-modal-percent">{{ exportModal.percent || 0 }}%</p>
          </div>
          <div class="export-modal-footer">
            <button
              type="button"
              class="export-modal-btn primary"
              @click="onExportModalContinue"
              :disabled="exportModal.busy"
            >
              {{ t("ibReport_exportContinue", "Continue") }}
            </button>
            <button
              type="button"
              class="export-modal-btn secondary"
              @click="onExportModalCancel"
              :disabled="exportModal.busy"
            >
              {{ t("ibReport_exportCancel", "Cancel") }}
            </button>
          </div>
        </div>
      </div>

      <div class="transaction-table-container stack-toolbar-card">
        <div class="table-header">
          <h2>
            <i class="fas fa-filter"></i>
            {{ t("customReport_filters", "Filters") }}
          </h2>
          <div class="table-controls">
            <div class="search-box">
              <input
                v-model="sharedSearch"
                type="text"
                :placeholder="t('customReport_search_placeholder', 'Search...')"
                @input="onSharedSearchInput"
              />
              <i class="fas fa-search search-icon"></i>
            </div>
            <div class="rows-selector">
              <label>{{ t("customReport_status", "Status") }}</label>
              <select v-model="statusFilter">
                <option value="">
                  {{ t("customReport_statusAll", "All") }}
                </option>
                <option value="active">
                  {{ t("customReport_statusActive", "Active") }}
                </option>
                <option value="inactive">
                  {{ t("customReport_statusInactive", "Inactive") }}
                </option>
                <option value="pending">
                  {{ t("customReport_statusPending", "Pending") }}
                </option>
              </select>
            </div>
            <div class="rows-selector">
              <label>{{ t("customReport_platform", "Platform") }}</label>
              <select v-model="platformFilter">
                <option value="">
                  {{ t("customReport_platformAll", "All") }}
                </option>
                <option value="MT5">MT5</option>
                <option value="MT4">MT4</option>
                <option value="uTrada-Live">uTrada-Live</option>
                <option value="Wallet">Wallet</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card accounts">
          <div class="stat-header">
            <span class="stat-title">{{
              t("customReport_stack_accounts", "Accounts")
            }}</span>
            <div class="stat-icon"><i class="fas fa-user"></i></div>
          </div>
          <div class="stat-value">{{ totals.accounts }}</div>
        </div>
        <div class="stat-card sales">
          <div class="stat-header">
            <span class="stat-title">{{
              t("customReport_stack_sales", "Sales managers")
            }}</span>
            <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
          </div>
          <div class="stat-value">{{ totals.sales }}</div>
        </div>
        <div class="stat-card groups">
          <div class="stat-header">
            <span class="stat-title">{{
              t("customReport_stack_groups", "Trading groups")
            }}</span>
            <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
          </div>
          <div class="stat-value">{{ totals.groups }}</div>
        </div>
      </div>

      <div v-if="loadingSources" class="loading-container">
        <i class="fas fa-spinner fa-spin"></i>
        <p>{{ t("customReport_loading", "Loading...") }}</p>
      </div>

      <div v-else-if="missingSources.length" class="error-container">
        <i class="fas fa-exclamation-triangle"></i>
        <p>
          {{ t("customReport_stack_missing", "Missing data sources:") }}
          {{ missingSources.join(", ") }}
        </p>
      </div>

      <div v-else class="stack-sections">
        <CustomReportStackPanel
          v-if="sourceIds.accounts"
          :data-source-id="sourceIds.accounts"
          :title="t('customReport_stack_accounts', 'Accounts')"
          :default-visible="ACCOUNT_DEFAULTS"
          :shared-filters="sharedFilters"
          :shared-search="debouncedSearch"
          :has-export-permission="hasExportPermission"
          :export-disabled="isExportRunning"
          @total="(value) => (totals.accounts = value)"
          @export-all="onPanelExportAll"
          @export-selected="onPanelExportSelected"
        />
        <CustomReportStackPanel
          v-if="sourceIds.sales"
          :data-source-id="sourceIds.sales"
          :title="t('customReport_stack_sales', 'Sales Managers')"
          :default-visible="SALES_DEFAULTS"
          :shared-filters="sharedFilters"
          :shared-search="debouncedSearch"
          :has-export-permission="hasExportPermission"
          :export-disabled="isExportRunning"
          @total="(value) => (totals.sales = value)"
          @export-all="onPanelExportAll"
          @export-selected="onPanelExportSelected"
        />
        <CustomReportStackPanel
          v-if="sourceIds.groups"
          :data-source-id="sourceIds.groups"
          :title="t('customReport_stack_groups', 'Trading Groups')"
          :default-visible="GROUP_DEFAULTS"
          :shared-filters="sharedFilters"
          :shared-search="debouncedSearch"
          :has-export-permission="hasExportPermission"
          :export-disabled="isExportRunning"
          @total="(value) => (totals.groups = value)"
          @export-all="onPanelExportAll"
          @export-selected="onPanelExportSelected"
        />
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import ExportProgressBanner from "@/components/common/ExportProgressBanner.vue";
import CustomReportStackPanel from "@/components/custom-report/CustomReportStackPanel.vue";
import { useAuthStore } from "@/stores/auth";
import customReportApi from "@/services/customReportApi";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { useAsyncReportExport } from "@/composables/useAsyncReportExport";

const { t } = useAdminI18n();
const authStore = useAuthStore();
const router = useRouter();

const hasReadonlyPermission = computed(() =>
  authStore.hasPermission("page_fundingreport_readonly"),
);
const hasExportPermission = computed(() =>
  authStore.hasPermission("page_fundingreport_export"),
);

const ACCOUNT_DEFAULTS = [
  "accountId",
  "accountNumber",
  "firstName",
  "lastName",
  "status",
  "salesName",
  "groupName",
  "balance",
];

const SALES_DEFAULTS = [
  "salesName",
  "salesRole",
  "status",
  "accountCount",
  "totalIbs",
  "totalClients",
  "totalBalance",
];

const GROUP_DEFAULTS = [
  "groupName",
  "accountId",
  "accountNumber",
  "firstName",
  "lastName",
  "salesName",
  "platformName",
  "status",
];

const loadingSources = ref(true);
const sourceIds = reactive({
  accounts: "",
  sales: "",
  groups: "",
});
const missingSources = ref([]);
const totals = reactive({
  accounts: 0,
  sales: 0,
  groups: 0,
});

const exportSourceId = computed(
  () => sourceIds.accounts || sourceIds.sales || sourceIds.groups || "",
);

const pendingExport = ref(null);

const {
  exportJobId,
  exportStatusText,
  exportBannerVisible,
  exportCancelling,
  exportModal,
  exportPolling,
  lastExportProgress,
  startOrResumeExport,
  resumeActiveExportIfAny,
  cancelActiveExport,
  onExportModalContinue,
  onExportModalCancel,
} = useAsyncReportExport({
  getActiveExport: () =>
    customReportApi.getDataSourceExportActive(exportSourceId.value),
  enqueueExport: (params) =>
    customReportApi.enqueueDataSourceExport(params.dataSourceId, params),
  getExportStatus: (jobId) =>
    customReportApi.getDataSourceExportStatus(exportSourceId.value, jobId),
  cancelExport: (jobId) =>
    customReportApi.cancelDataSourceExport(exportSourceId.value, jobId),
  downloadExport: (jobId) =>
    customReportApi.downloadDataSourceExport(exportSourceId.value, jobId),
  buildFilename: () => {
    const base = (pendingExport.value?.title || "sales_coverage")
      .toString()
      .replace(/[^\w\-]+/g, "_");
    return `${base}_${new Date().toISOString().split("T")[0]}.csv`;
  },
  t,
});

const isExportRunning = computed(
  () =>
    exportPolling.value ||
    exportCancelling.value ||
    !!exportJobId.value ||
    !!exportModal.value.visible,
);

const exportProgressPercent = computed(() =>
  Math.max(0, Math.min(100, Number(lastExportProgress.value?.percent || 0))),
);

const emptyExportColumnModal = () => ({
  visible: false,
  mode: "all",
  search: "",
  selected: {},
  payload: null,
});

const exportColumnModal = ref(emptyExportColumnModal());

const filteredExportPickerColumns = computed(() => {
  const cols = exportColumnModal.value.payload?.allColumns || [];
  const q = String(exportColumnModal.value.search || "")
    .trim()
    .toLowerCase();
  if (!q) return cols;
  return cols.filter(
    (col) =>
      col.label.toLowerCase().includes(q) ||
      col.field.toLowerCase().includes(q),
  );
});

const allExportColumnsChecked = computed(() => {
  const cols = exportColumnModal.value.payload?.allColumns || [];
  return (
    cols.length > 0 &&
    cols.every((col) => exportColumnModal.value.selected[col.field])
  );
});

const someExportColumnsChecked = computed(() =>
  (exportColumnModal.value.payload?.allColumns || []).some(
    (col) => exportColumnModal.value.selected[col.field],
  ),
);

const canConfirmExportColumns = computed(() => {
  if (exportColumnModal.value.mode !== "specific") {
    return (exportColumnModal.value.payload?.allColumns || []).length > 0;
  }
  return someExportColumnsChecked.value;
});

const closeExportColumnModal = () => {
  exportColumnModal.value = emptyExportColumnModal();
};

const setExportColumnMode = (mode) => {
  exportColumnModal.value = { ...exportColumnModal.value, mode };
};

const toggleExportColumn = (field, checked) => {
  exportColumnModal.value = {
    ...exportColumnModal.value,
    selected: { ...exportColumnModal.value.selected, [field]: checked },
  };
};

const toggleAllExportColumns = (checked) => {
  const selected = { ...exportColumnModal.value.selected };
  filteredExportPickerColumns.value.forEach((col) => {
    selected[col.field] = checked;
  });
  exportColumnModal.value = { ...exportColumnModal.value, selected };
};

const onPanelExportAll = (payload) => {
  if (!hasExportPermission.value || isExportRunning.value) return;
  const selected = {};
  payload.allColumns.forEach((col) => {
    selected[col.field] = payload.visibleFields.includes(col.field);
  });
  exportColumnModal.value = {
    visible: true,
    mode: "all",
    search: "",
    selected,
    payload,
  };
};

const confirmExportColumns = async () => {
  if (!canConfirmExportColumns.value || isExportRunning.value) return;
  const payload = exportColumnModal.value.payload;
  if (!payload) return;
  const source =
    exportColumnModal.value.mode === "specific"
      ? payload.allColumns.filter(
          (col) => exportColumnModal.value.selected[col.field],
        )
      : payload.allColumns;
  const columns = source.map((col) => ({ field: col.field, label: col.label }));
  if (!columns.length) return;
  pendingExport.value = payload;
  closeExportColumnModal();
  await startOrResumeExport(() => ({
    dataSourceId: payload.dataSourceId,
    mode: "all",
    columns,
    widgetName: payload.title,
    search: payload.search || "",
    filters: payload.filters || [],
    sorts: payload.sorts || [],
  }));
};

const onPanelExportSelected = async (payload) => {
  if (!hasExportPermission.value || isExportRunning.value) return;
  pendingExport.value = payload;
  await startOrResumeExport(() => ({
    dataSourceId: payload.dataSourceId,
    mode: "selected",
    columns: payload.columns,
    widgetName: payload.title,
    rows: payload.rows,
  }));
};

const sharedSearch = ref("");
const debouncedSearch = ref("");
const statusFilter = ref("");
const platformFilter = ref("");
let searchTimer = null;

const sharedFilters = computed(() => {
  const rules = [];
  if (statusFilter.value) {
    const value = statusFilter.value;
    const titled = value.charAt(0).toUpperCase() + value.slice(1);
    rules.push({
      field: "status",
      op: "in",
      value: value === titled ? [value] : [value, titled],
    });
  }
  if (platformFilter.value) {
    rules.push({
      field: "platformName",
      op: "contains",
      value: platformFilter.value,
    });
  }
  return rules;
});

const onSharedSearchInput = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    debouncedSearch.value = sharedSearch.value.trim();
  }, 300);
};

const goBack = () => {
  router.push("/custom-report");
};

const loadSources = async () => {
  loadingSources.value = true;
  missingSources.value = [];
  try {
    const response = await customReportApi.listDataSources();
    const items = response?.data?.items || [];
    const byObject = Object.fromEntries(
      items.map((item) => [String(item.objectName || ""), item]),
    );
    sourceIds.accounts = byObject.vReportAccounts?.id || "";
    sourceIds.sales = byObject.vReportSalesManagers?.id || "";
    sourceIds.groups = byObject.vReportTradingGroups?.id || "";

    const missing = [];
    if (!sourceIds.accounts) missing.push("Accounts");
    if (!sourceIds.sales) missing.push("Sales Managers");
    if (!sourceIds.groups) missing.push("Trading Groups");
    missingSources.value = missing;
  } catch (err) {
    missingSources.value = ["Accounts", "Sales Managers", "Trading Groups"];
    console.error(err);
  } finally {
    loadingSources.value = false;
  }
  if (exportSourceId.value) {
    resumeActiveExportIfAny();
  }
};

onMounted(() => {
  loadSources();
});
</script>

<style scoped>
.custom-report-page {
  padding: 40px 20px;
  max-width: 1400px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
  gap: 16px;
}

.page-title {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.page-title h1 {
  margin: 0 0 6px;
  font-size: 28px;
  color: var(--color-ink);
}

.page-title p {
  margin: 0;
  color: var(--color-muted);
  font-size: 14px;
  display: block;
}

.page-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.btn-back {
  border: 2px solid var(--color-border);
  background: var(--color-surface);
  width: 40px;
  height: 40px;
  border-radius: var(--radius-md);
  cursor: pointer;
  color: var(--color-text);
}

.btn-back:hover {
  border-color: var(--color-brand);
  color: var(--color-brand);
}

.transaction-table-container {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.stack-toolbar-card {
  margin-bottom: 20px;
}

.table-header {
  padding: 25px 30px;
  background: var(--color-surface-soft);
  border-bottom: 2px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
}

.table-header h2 {
  font-size: 18px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}

.table-header h2 i {
  color: var(--color-brand);
}

.table-controls {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
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

.search-box {
  position: relative;
}

.search-box input {
  padding: 10px 40px 10px 15px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  min-width: 250px;
}

.search-box input:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.search-icon {
  position: absolute;
  right: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-faint);
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}

.stat-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 20px;
  border: 1px solid var(--color-border);
  border-top: 3px solid var(--color-border-strong);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.stat-card.accounts {
  border-top-color: var(--color-brand);
}

.stat-card.sales {
  border-top-color: #319795;
}

.stat-card.groups {
  border-top-color: #805ad5;
}

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  gap: 8px;
}

.stat-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.stat-icon {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-surface-soft);
  color: var(--color-text);
}

.stat-value {
  font-size: 23px;
  font-weight: 700;
  color: var(--color-ink-strong);
}

.stack-sections {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.loading-container,
.error-container {
  text-align: center;
  padding: 60px 20px;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.loading-container i,
.error-container i {
  font-size: 48px;
  margin-bottom: 20px;
}

.loading-container i {
  color: var(--color-brand);
}

.error-container i {
  color: var(--color-danger);
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 20px;
}

.modal-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 28px;
  width: 100%;
  max-width: 420px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.modal-card-types {
  max-width: 520px;
}

.modal-card-types .modal-card-head {
  margin-bottom: 12px;
}

.modal-card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.modal-card-head h3 {
  margin: 0;
}

.modal-card > p {
  margin: 0 0 16px;
  color: var(--color-text);
  font-size: 14px;
}

.filter-icon-btn {
  width: 28px;
  height: 28px;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-muted);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.filter-icon-btn:hover {
  background: var(--color-surface-muted);
  color: var(--color-ink);
}

.filter-property-search {
  position: relative;
  margin: 0 12px 8px;
}

.filter-property-search i {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-faint);
  font-size: 14px;
}

.filter-property-search input {
  width: 100%;
  box-sizing: border-box;
  padding: 8px 10px 8px 30px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 14px;
  outline: none;
}

.filter-property-search input:focus {
  border-color: var(--color-brand);
}

.column-toggle-list {
  padding: 4px 8px 12px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  max-height: 320px;
  overflow-y: auto;
}

.column-toggle-all {
  margin-bottom: 4px;
  padding-left: 26px;
  font-weight: 600;
  border-bottom: 1px solid var(--color-surface-muted);
  border-radius: 6px 6px 0 0;
}

.column-toggle-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  font-size: 14px;
  color: var(--color-ink);
  border: 1px solid transparent;
}

.column-toggle-item:hover {
  background: var(--color-surface-muted);
}

.column-toggle-item input {
  width: 15px;
  height: 15px;
  accent-color: var(--color-brand);
  cursor: pointer;
}

.filter-property-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  color: var(--color-muted);
  font-size: 14px;
  font-weight: 600;
}

.filter-empty-hint {
  margin: 8px 4px;
  color: var(--color-faint);
  font-size: 14px;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.btn {
  border: none;
  border-radius: var(--radius-md);
  padding: 10px 16px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.btn-primary {
  background: var(--color-brand-solid);
  color: #fff;
}

.btn-primary:hover:not(:disabled) {
  opacity: 0.92;
}

.export-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  z-index: 2000;
}

.export-modal {
  width: min(100%, 480px);
  background: var(--color-surface);
  border-radius: 18px;
  box-shadow: 0 25px 60px rgba(15, 23, 42, 0.2);
  overflow: hidden;
}

.export-modal-header,
.export-modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 22px;
}

.export-modal-header {
  border-bottom: 1px solid var(--color-border);
}

.export-modal-header h3 {
  margin: 0;
  font-size: 18px;
  color: var(--color-ink);
}

.export-modal-close {
  border: none;
  background: transparent;
  font-size: 24px;
  line-height: 1;
  cursor: pointer;
  color: var(--color-muted);
}

.export-modal-body {
  padding: 20px 22px;
}

.export-modal-text {
  margin: 0 0 16px;
  color: var(--color-text);
  font-size: 14px;
  line-height: 1.5;
}

.export-modal-progress {
  height: 8px;
  background: var(--color-border);
  border-radius: 999px;
  overflow: hidden;
}

.export-modal-progress-bar {
  height: 100%;
  background: var(--color-brand-solid);
  transition: width 0.3s ease;
}

.export-modal-percent {
  margin: 10px 0 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
}

.export-modal-footer {
  border-top: 1px solid var(--color-border);
  gap: 12px;
  justify-content: flex-end;
}

.export-modal-btn {
  padding: 10px 16px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: none;
}

.export-modal-btn.secondary {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  color: var(--color-text);
}

.export-modal-btn.primary {
  background: var(--color-brand-solid);
  color: #fff;
}

.export-modal-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.export-column-mode-grid {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0 0 16px;
}

.export-column-mode-option {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  width: 100%;
  padding: 12px 14px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  text-align: left;
  cursor: pointer;
  color: var(--color-text);
  transition: all 0.2s ease;
}

.export-column-mode-option:hover {
  border-color: var(--color-border-strong);
  background: var(--color-surface-soft);
}

.export-column-mode-option.active {
  background: var(--color-brand-solid);
  color: #fff;
  border-color: var(--color-brand);
}

.export-column-mode-icon {
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-brand);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.export-column-mode-option.active .export-column-mode-icon {
  background: rgba(255, 255, 255, 0.18);
  color: #fff;
}

.export-column-mode-copy {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.export-column-mode-copy strong {
  font-size: 14px;
  font-weight: 600;
}

.export-column-mode-copy small {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-muted);
  line-height: 1.4;
}

.export-column-mode-option.active .export-column-mode-copy small {
  color: rgba(255, 255, 255, 0.82);
}

.export-column-picker {
  margin: 0 0 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
}

.export-column-picker .filter-property-search {
  margin: 10px 10px 0;
}

.export-column-list {
  max-height: min(280px, calc(100vh - 420px));
  overflow-y: auto;
}

@media (max-width: 900px) {
  .page-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .search-box input {
    min-width: 0;
    width: 100%;
  }
}

@media (max-width: 768px) {
  .custom-report-page {
    padding: 20px 15px;
  }
}
</style>
