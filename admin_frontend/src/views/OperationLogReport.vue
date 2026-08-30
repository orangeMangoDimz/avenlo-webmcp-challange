<template>
  <div class="operation-log-report-page">
    <div class="page-header">
      <div class="page-title">
        <h1>
          <i class="fas fa-clipboard-list"></i>
          {{ t("page_operationLogReport_title") }}
        </h1>
        <p>{{ t("page_operationLogReport_sub") }}</p>
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
      :cancelling-title="t('ibReport_exportCancelling', 'Cancelling export...')"
      :cancel-label="t('customReport_cancel', 'Cancel')"
      @cancel-export="cancelActiveExport"
    />

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

    <!-- 模块 Tab（对应 adminOperationLogModuleSettings 可见模块，含 log_report） -->
    <div class="olr-module-tabs">
      <button
        v-for="tab in moduleTabs"
        :key="tab.modelKey"
        type="button"
        class="olr-module-tab"
        :class="{ active: activeModuleKey === tab.modelKey }"
        @click="switchModule(tab.modelKey)"
      >
        <span>{{ displayTabName(tab) }}</span>
      </button>
    </div>

    <!-- 筛选区 -->
    <div class="olr-filter-card">
      <el-config-provider :locale="elementPlusLocale">
        <div class="olr-filter-row olr-filter-row--main">
          <div class="olr-filter-field olr-filter-field--keyword">
            <label>{{ t("operationLogReport_filter_keyword") }}</label>
            <div class="olr-search-box">
              <i class="fas fa-search"></i>
              <input
                v-model="filters.keyword"
                type="text"
                :placeholder="t('operationLogReport_searchPlaceholder')"
                @keyup.enter="applyFilters"
              />
            </div>
          </div>

          <div class="olr-filter-field olr-filter-field--dates">
            <label>{{ t("operationLogReport_filter_dateRange") }}</label>
            <div class="olr-inline-dates">
              <el-date-picker
                v-model="filters.startDate"
                type="date"
                value-format="YYYY-MM-DD"
                size="large"
                :placeholder="t('operationLogReport_startDate')"
                class="olr-filter-date"
                clearable
              />
              <span class="olr-date-sep">–</span>
              <el-date-picker
                v-model="filters.endDate"
                type="date"
                value-format="YYYY-MM-DD"
                size="large"
                :placeholder="t('operationLogReport_endDate')"
                class="olr-filter-date"
                clearable
              />
            </div>
          </div>

          <div class="olr-filter-field olr-filter-field--submodule">
            <label>{{ t("operationLogReport_filter_subModule") }}</label>
            <select v-model="filters.subModule" class="olr-select">
              <option value="all">
                {{ t("operationLogReport_filter_all") }}
              </option>
              <option
                v-for="sub in subModuleOptions"
                :key="sub.value"
                :value="sub.value"
              >
                {{ displaySubModuleLabel(sub) }}
              </option>
            </select>
          </div>
          <div
            v-if="!isReportModuleTab"
            class="olr-filter-field olr-filter-field--optype"
          >
            <label>{{ t("operationLogReport_filter_operationType") }}</label>
            <select v-model="filters.operationType" class="olr-select">
              <option value="all">
                {{ t("operationLogReport_filter_all") }}
              </option>
              <option
                v-for="opt in operationTypeOptions"
                :key="opt.value"
                :value="opt.value"
              >
                {{ pickDictLabel(opt) }}
              </option>
            </select>
          </div>
          <div class="olr-filter-actions">
            <button
              type="button"
              class="olr-btn olr-btn--primary"
              @click="applyFilters"
            >
              <i class="fas fa-filter"></i>
              {{ t("operationLogReport_btn_search") }}
            </button>
            <button
              type="button"
              class="olr-btn olr-btn--secondary"
              @click="resetFilters"
            >
              <i class="fas fa-redo"></i>
              {{ t("operationLogReport_btn_reset") }}
            </button>
          </div>
        </div>
      </el-config-provider>
    </div>

    <div class="olr-table-wrap">
      <div class="olr-toolbar">
        <div class="olr-toolbar__left">
          <h2 class="olr-toolbar__title">
            {{ t("operationLogReport_section_report") }}
          </h2>
        </div>
        <div class="olr-toolbar__right">
          <button
            type="button"
            class="olr-btn olr-btn--export"
            :disabled="isExportRunning"
            @click="handleExport"
          >
            <i class="fas fa-download"></i> {{ t("operationLogReport_export") }}
          </button>
          <div class="olr-rows">
            <label class="olr-rows__label">{{ t("leads_showRows") }}</label>
            <select
              v-model="perPage"
              class="olr-rows__select"
              @change="onPageSizeChange"
            >
              <option :value="5">{{ t("ibList_rows_5") }}</option>
              <option :value="10">{{ t("ibList_rows_10") }}</option>
              <option :value="20">{{ t("ibList_rows_20") }}</option>
              <option value="all">{{ t("ibList_rows_all") }}</option>
            </select>
          </div>
        </div>
      </div>

      <div v-if="listLoading" class="olr-loading">
        {{ t("ibList_loading") }}
      </div>
      <div v-else class="olr-table-scroll">
        <table class="olr-table">
          <thead>
            <tr>
              <th>{{ t("operationLogReport_col_datetime") }}</th>
              <th>{{ t("operationLogReport_col_operator") }}</th>
              <th>{{ t("operationLogReport_col_module") }}</th>
              <th>{{ t("operationLogReport_col_subModule") }}</th>
              <th v-if="!isReportModuleTab">
                {{ t("operationLogReport_col_operationType") }}
              </th>
              <th v-if="!isReportModuleTab">
                {{ t("operationLogReport_col_target") }}
              </th>
              <th>{{ t("operationLogReport_col_detail") }}</th>
              <th>{{ t("operationLogReport_col_ip") }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in listItems" :key="row.id" class="olr-table__row">
              <td class="olr-table__cell olr-table__cell--time">
                {{ formatOperatedAt(row.operatedAt) }}
              </td>
              <td class="olr-table__cell">
                <div class="olr-operator">
                  <span class="olr-operator__avatar">{{
                    row.operatorInitials || "?"
                  }}</span>
                  <span class="olr-operator__name">{{
                    row.operatorFullName || "—"
                  }}</span>
                </div>
              </td>
              <td class="olr-table__cell">{{ displayModuleName(row) }}</td>
              <td class="olr-table__cell">{{ displaySubModuleName(row) }}</td>
              <td v-if="!isReportModuleTab" class="olr-table__cell">
                <span
                  class="olr-op-type"
                  :class="`olr-op-type--${row.operationTypeKey}`"
                >
                  {{ operationTypeLabel(row.operationTypeKey) }}
                </span>
              </td>
              <td
                v-if="!isReportModuleTab"
                class="olr-table__cell olr-table__cell--target"
              >
                <div v-if="hasTargetDisplayContent(row)" class="olr-target">
                  <div class="olr-target-name">
                    {{ resolveTargetDisplayName(row) || "—" }}
                  </div>
                  <div class="olr-target-meta">
                    <template v-if="isKycTemplatesTarget(row)">
                      {{ t("operationLogReport_target_kyc_template") }}
                    </template>
                    <template v-else-if="isSalesTarget(row)">
                      {{ t("operationLogReport_target_sales") }}
                      {{
                        tParams("operationLogReport_target_id", "ID: {id}", {
                          id: String(row.targetId),
                        })
                      }}
                    </template>
                    <template v-else-if="isAccountsTarget(row)">
                      {{ t("operationLogReport_target_admin") }}
                      {{
                        tParams("operationLogReport_target_id", "ID: {id}", {
                          id: String(row.targetId),
                        })
                      }}
                    </template>
                    <template v-else-if="isRoleManagementTarget(row)">
                      {{ t("operationLogReport_target_role") }}
                      {{
                        tParams("operationLogReport_target_id", "ID: {id}", {
                          id: String(row.targetId),
                        })
                      }}
                    </template>
                    <template v-else>
                      {{ t("operationLogReport_target_client") }}
                      {{
                        tParams("operationLogReport_target_id", "ID: {id}", {
                          id: String(row.targetId),
                        })
                      }}
                    </template>
                  </div>
                </div>
                <span v-else>—</span>
              </td>
              <td class="olr-table__cell olr-table__cell--detail">
                {{ displayDetail(row) }}
              </td>
              <td class="olr-table__cell olr-table__cell--ip">
                {{ row.ipAddress }}
              </td>
            </tr>
            <tr v-if="listItems.length === 0">
              <td :colspan="tableColumnCount" class="olr-table__empty">
                {{ t("operationLogReport_empty") }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="pagination.total > 0" class="olr-pagination">
        <span class="olr-pagination__info">{{ paginationInfo }}</span>
        <div class="olr-pagination__btns">
          <button
            type="button"
            class="olr-btn olr-btn--pagination"
            :disabled="currentPage <= 1"
            @click="goToPage(currentPage - 1)"
          >
            <i class="fas fa-chevron-left"></i> {{ t("ibList_btnPrev") }}
          </button>
          <span class="olr-pagination__page">
            {{
              tParams("ibList_pageOf", "Page {current} of {total}", {
                current: String(currentPage),
                total: totalPagesText,
              })
            }}
          </span>
          <button
            type="button"
            class="olr-btn olr-btn--pagination"
            :disabled="!hasNextPage"
            @click="goToPage(currentPage + 1)"
          >
            {{ t("ibList_btnNext") }} <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { ElConfigProvider, ElDatePicker } from "element-plus";
import zhCn from "element-plus/es/locale/lang/zh-cn";
import en from "element-plus/es/locale/lang/en";
import "element-plus/es/components/date-picker/style/css";
import "element-plus/es/components/config-provider/style/css";
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import ExportProgressBanner from "@/components/common/ExportProgressBanner.vue";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { useAsyncReportExport } from "@/composables/useAsyncReportExport";
import {
  fetchOperationLogReportInit,
  fetchOperationLogReports,
  exportOperationLogReports,
  getActiveExport,
  getExportStatus,
  cancelExport,
  downloadExport,
} from "@/services/operationLogReportApi";

const { t, tParams, languageStore } = useAdminI18n();

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
  getActiveExport,
  enqueueExport: (params) => exportOperationLogReports(params),
  getExportStatus,
  cancelExport,
  downloadExport,
  buildFilename: () => {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `operation-log-report_${y}-${m}-${day}.csv`;
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

const elementPlusLocale = computed(() =>
  languageStore.currentLanguage === "zh" ? zhCn : en,
);

const pageLoading = ref(true);
const listLoading = ref(false);

const moduleTabs = ref([]);
const operationTypeOptions = ref([]);
const subModulesByModelKey = ref({});

const activeModuleKey = ref("");
const filters = ref({
  startDate: "",
  endDate: "",
  keyword: "",
  operationType: "all",
  subModule: "all",
});

const listItems = ref([]);
const pagination = ref({ total: 0, page: 1, per_page: 10, total_pages: 1 });
const perPage = ref(10);
const currentPage = ref(1);

const REPORT_MODULE_KEY = "log_report";

const subModuleOptions = computed(
  () => subModulesByModelKey.value[activeModuleKey.value] || [],
);

/** 报表 Tab：仅导出类日志，无操作对象，隐藏操作类型/操作对象列与筛选 */
const isReportModuleTab = computed(
  () => activeModuleKey.value === REPORT_MODULE_KEY,
);

const tableColumnCount = computed(() => (isReportModuleTab.value ? 6 : 8));

const totalPagesText = computed(() =>
  String(pagination.value.total_pages || 1),
);
const hasNextPage = computed(
  () => currentPage.value < (pagination.value.total_pages || 1),
);

const paginationInfo = computed(() => {
  const total = pagination.value.total || 0;
  if (total === 0) return t("ibList_pagination_noRecords");
  if (perPage.value === "all") {
    return tParams("ibList_pagination_totalOnly", "Total {n} record(s)", {
      n: String(total),
    });
  }
  const pp = Number(perPage.value) || 10;
  const from = (currentPage.value - 1) * pp + 1;
  const to = Math.min(currentPage.value * pp, total);
  return tParams(
    "ibList_pagination_showing",
    "Showing {from}-{to} of {total}",
    {
      from: String(from),
      to: String(to),
      total: String(total),
    },
  );
});

function formatDateInput(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

function initDefaultDates() {
  const today = new Date();
  const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
  filters.value.startDate = formatDateInput(monthStart);
  filters.value.endDate = formatDateInput(today);
}

function pickDictLabel(item) {
  if (!item) return "";
  return languageStore.currentLanguage === "zh" ? item.labelZh : item.labelEn;
}

function displayTabName(tab) {
  return languageStore.currentLanguage === "zh"
    ? tab.moduleNameZh
    : tab.moduleNameEn;
}

function displayModuleName(row) {
  return languageStore.currentLanguage === "zh"
    ? row.moduleNameZh
    : row.moduleNameEn;
}

function displaySubModuleName(row) {
  const subs = subModulesByModelKey.value[row?.modelKey] || [];
  const hit = subs.find((s) => s.value === row?.subModuleKey);
  if (hit) return pickDictLabel(hit);
  return languageStore.currentLanguage === "zh"
    ? row.subModuleNameZh
    : row.subModuleNameEn;
}

function displayDetail(row) {
  return languageStore.currentLanguage === "zh" ? row.detailZh : row.detailEn;
}

function displaySubModuleLabel(sub) {
  return pickDictLabel(sub);
}

function operationTypeLabel(typeKey) {
  const opt = operationTypeOptions.value.find((o) => o.value === typeKey);
  if (opt) return pickDictLabel(opt);
  return typeKey || "—";
}

function formatOperatedAt(iso) {
  if (!iso) return "—";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  const locale = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return d.toLocaleString(locale, {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hour12: false,
  });
}

function buildQueryParams() {
  return {
    model_key: activeModuleKey.value,
    start_date: filters.value.startDate,
    end_date: filters.value.endDate,
    keyword: filters.value.keyword.trim(),
    sub_module: filters.value.subModule,
    operation_type: filters.value.operationType,
    page: currentPage.value,
    per_page: perPage.value,
  };
}

function buildExportBody() {
  return {
    modelKey: activeModuleKey.value,
    startDate: filters.value.startDate,
    endDate: filters.value.endDate,
    keyword: filters.value.keyword.trim(),
    subModule: filters.value.subModule,
    operationType: filters.value.operationType,
  };
}

async function loadList() {
  if (!activeModuleKey.value) return;
  listLoading.value = true;
  try {
    const res = await fetchOperationLogReports(buildQueryParams());
    const data = res?.data ?? res ?? {};
    listItems.value = data.items || [];
    pagination.value = data.pagination || {
      total: 0,
      page: 1,
      per_page: 10,
      total_pages: 1,
    };
    currentPage.value = pagination.value.page || 1;
  } catch (e) {
    listItems.value = [];
    pagination.value = {
      total: 0,
      page: 1,
      per_page: perPage.value,
      total_pages: 1,
    };
  } finally {
    listLoading.value = false;
  }
}

async function switchModule(modelKey) {
  activeModuleKey.value = modelKey;
  filters.value.subModule = "all";
  if (modelKey === REPORT_MODULE_KEY) {
    filters.value.operationType = "all";
  }
  currentPage.value = 1;
  await loadList();
}

async function applyFilters() {
  currentPage.value = 1;
  await loadList();
}

async function resetFilters() {
  initDefaultDates();
  filters.value.keyword = "";
  filters.value.operationType = "all";
  filters.value.subModule = "all";
  currentPage.value = 1;
  await loadList();
}

async function onPageSizeChange() {
  currentPage.value = 1;
  await loadList();
}

async function goToPage(page) {
  if (page < 1 || page > (pagination.value.total_pages || 1)) return;
  currentPage.value = page;
  await loadList();
}

const KYC_TEMPLATES_SUB_MODULE = "kyc_templates";
const ACCOUNTS_SUB_MODULE = "accounts";
const ROLE_MANAGEMENT_SUB_MODULE = "role_management";

function resolveTargetDisplayName(row) {
  const isZh = languageStore.currentLanguage === "zh";
  const zh = String(
    row?.targetDisplayNameZh ?? row?.targetDisplayName ?? "",
  ).trim();
  const en = String(
    row?.targetDisplayNameEn ?? row?.targetDisplayName ?? "",
  ).trim();
  if (isZh) return zh || en;
  return en || zh;
}

function isKycTemplatesTarget(row) {
  return row?.subModuleKey === KYC_TEMPLATES_SUB_MODULE;
}

function isSalesTarget(row) {
  return row?.modelKey === "log_sales";
}

function isAccountsTarget(row) {
  return row?.subModuleKey === ACCOUNTS_SUB_MODULE;
}

function isRoleManagementTarget(row) {
  return row?.subModuleKey === ROLE_MANAGEMENT_SUB_MODULE;
}

/** 无有效展示名时操作对象列仅显示 —（含 KYC 模板、客户/Lead 未解析到姓名等） */
function hasTargetDisplayContent(row) {
  if (!row?.targetId) return false;
  return Boolean(resolveTargetDisplayName(row));
}

async function handleExport() {
  if (isExportRunning.value || !activeModuleKey.value) return;
  await startOrResumeExport(() => ({
    ...buildExportBody(),
    language: languageStore.currentLanguage === "zh" ? "zh" : "en",
  }));
}

onMounted(async () => {
  pageLoading.value = true;
  try {
    const res = await fetchOperationLogReportInit();
    const data = res?.data ?? res ?? {};
    moduleTabs.value = data.modules || [];
    operationTypeOptions.value = data.operationTypes || [];
    subModulesByModelKey.value = data.subModulesByModelKey || {};
    const defaults = data.defaults || {};
    if (defaults.modelKey) {
      activeModuleKey.value = defaults.modelKey;
    } else if (moduleTabs.value.length) {
      activeModuleKey.value = moduleTabs.value[0].modelKey;
    }
    if (defaults.startDate) filters.value.startDate = defaults.startDate;
    if (defaults.endDate) filters.value.endDate = defaults.endDate;
    if (!filters.value.startDate || !filters.value.endDate) initDefaultDates();
    perPage.value = defaults.perPage || 10;
    currentPage.value = defaults.page || 1;
    const list = data.list || {};
    listItems.value = list.items || [];
    pagination.value = list.pagination || {
      total: 0,
      page: 1,
      per_page: 10,
      total_pages: 1,
    };
  } catch {
    initDefaultDates();
  } finally {
    pageLoading.value = false;
  }
  resumeActiveExportIfAny();
});
</script>

<style scoped>
.operation-log-report-page {
  padding: 40px 20px;
  max-width: 1600px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.page-title h1 {
  font-size: 28px;
  color: var(--color-ink);
  margin-bottom: 5px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.page-title h1 i {
  color: var(--color-brand);
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

/* Module tabs */
.olr-module-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 20px;
  padding-bottom: 4px;
  border-bottom: 2px solid var(--color-border);
}

.olr-module-tab {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 18px;
  border: none;
  background: transparent;
  color: var(--color-muted);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border-bottom: 3px solid transparent;
  margin-bottom: -2px;
  transition: all 0.2s ease;
}

.olr-module-tab:hover {
  color: var(--color-brand);
  background: var(--color-surface-soft);
}

.olr-module-tab.active {
  color: var(--color-brand);
  border-bottom-color: var(--color-brand);
}

.olr-module-tab i {
  font-size: 15px;
}

/* Filter card */
.olr-filter-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 24px 28px;
  margin-bottom: 24px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  border: 1px solid var(--color-border);
}

.olr-filter-row--main {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 14px 20px;
}

.olr-filter-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex-shrink: 0;
}

.olr-filter-field label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-muted);
}

.olr-filter-field--keyword {
  flex: 1 1 240px;
  min-width: 220px;
  max-width: 300px;
}

.olr-filter-field--dates {
  flex: 0 0 auto;
}

.olr-filter-field--submodule,
.olr-filter-field--optype {
  flex: 0 0 180px;
  width: 180px;
}

.olr-search-box {
  position: relative;
}

.olr-inline-dates {
  display: flex;
  align-items: center;
  gap: 8px;
}

.olr-date-sep {
  color: var(--color-muted);
  font-size: 14px;
  user-select: none;
  line-height: 1;
}

/* 与积分变动记录 pm-redem-filter-date 对齐，高度贴近关键字/下拉输入框 */
.olr-filter-date {
  --el-input-text-color: var(--color-ink) !important;
  --el-input-placeholder-color: var(--color-faint) !important;
  --el-input-icon-color: #64748b !important;
  display: block !important;
  width: 148px !important;
}

.olr-filter-date :deep(.el-date-editor.el-input),
.olr-filter-date :deep(.el-date-editor) {
  width: 148px !important;
  max-width: none !important;
  min-width: 0;
}

.olr-filter-date :deep(.el-input__wrapper) {
  box-sizing: border-box;
  width: 100%;
  min-height: 42px;
  padding: 0 11px;
  background-color: var(--color-surface);
  border-radius: var(--radius-md);
  box-shadow: 0 0 0 2px var(--color-border) inset;
  transition:
    box-shadow 0.2s ease,
    background-color 0.2s ease;
}

.olr-filter-date :deep(.el-input__wrapper:hover:not(.is-focus)) {
  box-shadow: 0 0 0 2px var(--color-border-strong) inset;
}

.olr-filter-date :deep(.el-input__wrapper.is-focus) {
  box-shadow:
    0 0 0 2px var(--color-brand) inset,
    0 0 0 3px rgba(var(--color-brand-rgb), 0.12);
}

.olr-filter-date :deep(.el-input__inner) {
  color: var(--color-ink);
  font-size: 14px;
  height: 20px;
  line-height: 20px;
}

.olr-filter-date :deep(.el-input__inner::placeholder) {
  color: var(--color-faint);
}

.olr-filter-date :deep(.el-input__prefix-inner),
.olr-filter-date :deep(.el-input__suffix-inner) {
  color: var(--color-muted);
}

.olr-search-box i {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-faint);
}

.olr-search-box input {
  width: 100%;
  padding: 10px 14px 10px 40px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
}

.olr-search-box input:focus {
  outline: none;
  border-color: var(--color-brand);
}

.olr-filter-field--submodule .olr-select,
.olr-filter-field--optype .olr-select {
  width: 100%;
  box-sizing: border-box;
}

.olr-select {
  padding: 10px 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  background: var(--color-surface);
}

.olr-filter-actions {
  display: flex;
  gap: 10px;
  flex-shrink: 0;
  margin-left: auto;
}

.olr-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s ease;
}

.olr-btn--primary {
  background: var(--color-brand-solid);
  color: white;
}

.olr-btn--primary:hover {
  opacity: 0.92;
}

.olr-btn--secondary {
  background: var(--color-surface);
  color: var(--color-text);
  border: 2px solid var(--color-border);
}

.olr-btn--secondary:hover {
  background: var(--color-surface-soft);
}

.olr-btn--export {
  background: var(--color-surface);
  color: var(--color-brand);
  border: 2px solid var(--color-brand);
}

.olr-btn--export:hover:not(:disabled) {
  background: var(--color-brand-soft);
}

.olr-btn--export:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Table wrap */
.olr-loading {
  padding: 48px 24px;
  text-align: center;
  color: var(--color-muted);
  font-size: 14px;
}

.olr-table-wrap {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.olr-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  padding: 16px 24px;
  background: var(--color-surface-soft);
  border-bottom: 2px solid var(--color-border);
}

.olr-toolbar__left {
  display: flex;
  align-items: center;
  min-width: 0;
}

.olr-toolbar__title {
  font-size: 18px;
  font-weight: 600;
  color: var(--color-ink);
  margin: 0;
}

.olr-toolbar__right {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-shrink: 0;
  margin-left: auto;
}

.olr-rows {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: var(--color-text);
}

.olr-rows__select {
  padding: 8px 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-size: 14px;
}

.olr-table-scroll {
  overflow-x: auto;
}

.olr-table {
  width: 100%;
  min-width: 1080px;
  border-collapse: collapse;
}

.olr-table thead {
  background: var(--color-surface-soft);
}

.olr-table th {
  padding: 14px 16px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.4px;
  border-bottom: 2px solid var(--color-border);
  white-space: nowrap;
}

.olr-table__row:hover {
  background: var(--color-surface-soft);
}

.olr-table__cell {
  padding: 14px 16px;
  font-size: 14px;
  color: var(--color-text);
  border-bottom: 1px solid var(--color-border);
  vertical-align: middle;
}

.olr-table__cell--time,
.olr-table__cell--ip {
  white-space: nowrap;
}

.olr-table__cell--detail {
  max-width: 220px;
  line-height: 1.45;
}

.olr-table__cell--target {
  min-width: 140px;
}

.olr-table__empty {
  text-align: center;
  padding: 48px;
  color: var(--color-muted);
}

.olr-operator {
  display: flex;
  align-items: center;
  gap: 10px;
}

.olr-operator__avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--color-brand-solid);
  color: white;
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}

.olr-operator__name {
  font-weight: 600;
  color: var(--color-ink);
}

.olr-target-name {
  font-weight: 600;
  color: var(--color-ink);
}

.olr-target-meta {
  font-size: 12px;
  color: var(--color-muted);
  margin-top: 2px;
}

.olr-op-type {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  background: var(--color-border);
  color: var(--color-text);
}

.olr-op-type--add {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.olr-op-type--edit {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.olr-op-type--delete {
  background: var(--color-danger-soft);
  color: #97266d;
}

.olr-op-type--view {
  background: var(--color-brand-soft);
  color: #553c9a;
}

.olr-op-type--notify {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.olr-op-type--export {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.olr-op-type--import {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.olr-op-type--approve {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.olr-op-type--reject {
  background: var(--color-danger-border);
  color: var(--color-danger);
}

.olr-op-type--assign {
  background: var(--color-info-soft);
  color: #3c366b;
}

.olr-op-type--enable {
  background: var(--color-success-border);
  color: var(--color-success);
}

.olr-op-type--disable {
  background: var(--color-border-strong);
  color: var(--color-ink);
}

.olr-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
  padding: 16px 24px;
  background: var(--color-surface-soft);
  border-top: 1px solid var(--color-border);
}

.olr-pagination__info {
  font-size: 14px;
  color: var(--color-text);
}

.olr-pagination__btns {
  display: flex;
  align-items: center;
  gap: 12px;
}

.olr-pagination__page {
  font-size: 14px;
  color: var(--color-text);
}

.olr-btn--pagination {
  padding: 8px 14px;
  font-size: 13px;
  background: var(--color-border);
  color: var(--color-text);
}

.olr-btn--pagination:hover:not(:disabled) {
  background: var(--color-brand-solid);
  color: white;
}

.olr-btn--pagination:disabled {
  opacity: 0.5;
  cursor: not-allowed;
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
  font-size: 13px;
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
  font-size: 13px;
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

@media (max-width: 768px) {
  .operation-log-report-page {
    padding: 20px 12px;
  }

  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }

  .olr-filter-actions {
    margin-left: 0;
    width: 100%;
  }
}
</style>
