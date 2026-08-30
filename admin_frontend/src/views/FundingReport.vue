<template>
  <div class="funding-report-page">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-title">
        <h1>{{ t("page_fundingReport_title") }}</h1>
        <p>{{ t("page_fundingReport_sub") }}</p>
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

    <!-- Statistics Cards -->
    <div class="stats-grid">
      <div class="stat-card deposit">
        <div class="stat-header">
          <span class="stat-title">{{
            t("fundingReport_stat_totalDeposits")
          }}</span>
          <div class="stat-icon">
            <i class="fas fa-arrow-down"></i>
          </div>
        </div>
        <div class="stat-value">
          {{ formatCurrency(statistics.totalDeposits || 0) }}
        </div>
        <div class="stat-footer">
          <div class="stat-count">
            <i class="fas fa-receipt"></i>
            <span>{{
              tParams("fundingReport_stat_txCount", "{count} transactions", {
                count: statistics.depositCount || 0,
              })
            }}</span>
          </div>
        </div>
      </div>

      <div class="stat-card withdrawal">
        <div class="stat-header">
          <span class="stat-title">{{
            t("fundingReport_stat_totalWithdrawals")
          }}</span>
          <div class="stat-icon">
            <i class="fas fa-arrow-up"></i>
          </div>
        </div>
        <div class="stat-value">
          {{ formatCurrency(statistics.totalWithdrawals || 0) }}
        </div>
        <div class="stat-footer">
          <div class="stat-count">
            <i class="fas fa-receipt"></i>
            <span>{{
              tParams("fundingReport_stat_txCount", "{count} transactions", {
                count: statistics.withdrawalCount || 0,
              })
            }}</span>
          </div>
        </div>
      </div>

      <!-- Total Internal Transfer 卡片暂时隐藏，有需要时取消注释
      <div class="stat-card internal-transfer">
        <div class="stat-header">
          <span class="stat-title">Total Internal Transfer</span>
          <div class="stat-icon">
            <i class="fas fa-exchange-alt"></i>
          </div>
        </div>
        <div class="stat-value">{{ formatCurrency(statistics.totalInternalTransfer || 0) }}</div>
        <div class="stat-footer">
          <div class="stat-count">
            <i class="fas fa-receipt"></i>
            <span>{{ formatNumber(statistics.internalTransferCount || 0) }} transactions</span>
          </div>
        </div>
      </div>
      -->

      <div class="stat-card net">
        <div class="stat-header">
          <span class="stat-title">{{ t("fundingReport_stat_netFlow") }}</span>
          <div class="stat-icon">
            <i class="fas fa-exchange-alt"></i>
          </div>
        </div>
        <div :class="['stat-value', netFlowClass]">
          {{ netFlowSign
          }}{{ formatCurrency(Math.abs(statistics.netFlow || 0)) }}
        </div>
        <div class="stat-footer">
          <div :class="['stat-change', netFlowClass]">
            <i
              :class="
                statistics.netFlow >= 0
                  ? 'fas fa-arrow-up'
                  : 'fas fa-arrow-down'
              "
            ></i>
            <span>{{
              statistics.netFlow >= 0
                ? t("fundingReport_net_positive")
                : t("fundingReport_net_negative")
            }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Date Filter Section（与客户工单一致：Element Plus 日期，随后台语言切换文案） -->
    <div class="date-filter-section">
      <el-config-provider :locale="elementPlusLocale">
        <div class="date-filter-container">
          <span class="date-filter-label">{{
            t("fundingReport_timePeriod")
          }}</span>
          <div class="date-filter-presets">
            <button
              :class="['preset-btn', { active: activePreset === 'today' }]"
              @click="selectPreset('today')"
            >
              {{ t("fundingReport_preset_today") }}
            </button>
            <button
              :class="['preset-btn', { active: activePreset === 'week' }]"
              @click="selectPreset('week')"
            >
              {{ t("fundingReport_preset_week") }}
            </button>
            <button
              :class="['preset-btn', { active: activePreset === 'month' }]"
              @click="selectPreset('month')"
            >
              {{ t("fundingReport_preset_month") }}
            </button>
            <button
              :class="['preset-btn', { active: activePreset === 'quarter' }]"
              @click="selectPreset('quarter')"
            >
              {{ t("fundingReport_preset_quarter") }}
            </button>
          </div>
          <div class="date-input-wrapper">
            <label>{{ t("fundingReport_fromDate") }}</label>
            <el-date-picker
              v-model="startDate"
              type="date"
              value-format="YYYY-MM-DD"
              :placeholder="t('fundingReport_fromDate')"
              class="filter-date"
              clearable
            />
          </div>
          <div class="date-input-wrapper">
            <label>{{ t("fundingReport_toDate") }}</label>
            <el-date-picker
              v-model="endDate"
              type="date"
              value-format="YYYY-MM-DD"
              :placeholder="t('fundingReport_toDate')"
              class="filter-date"
              clearable
            />
          </div>
          <button
            type="button"
            class="btn-apply-filter"
            @click="applyDateFilter"
          >
            <i class="fas fa-filter"></i> {{ t("fundingReport_applyFilter") }}
          </button>
        </div>
      </el-config-provider>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <i class="fas fa-spinner fa-spin"></i>
      <p>{{ t("fundingReport_loading") }}</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-circle"></i>
      <p>{{ error }}</p>
      <button class="btn btn-primary" @click="loadReport">
        <i class="fas fa-redo"></i> {{ t("fundingReport_retry") }}
      </button>
    </div>

    <!-- Transaction Table -->
    <div v-else class="transaction-table-container">
      <div class="table-header">
        <div style="flex: 1">
          <h2>
            <i class="fas fa-list"></i> {{ t("fundingReport_section_recent") }}
          </h2>
          <div
            :class="[
              'bulk-actions',
              { show: selectedTransactionIds.length > 0 },
            ]"
          >
            <span class="bulk-actions-label">{{
              t("fundingReport_bulk_selected")
            }}</span>
            <span class="bulk-actions-count">{{
              selectedTransactionIds.length
            }}</span>
            <button
              v-if="hasExportPermission"
              type="button"
              class="btn-bulk btn-bulk-export"
              :disabled="isExportRunning"
              @click.stop="toggleExportDropdown"
            >
              <i class="fas fa-download"></i> {{ t("fundingReport_export") }}
              <div :class="['export-dropdown', { show: showExportDropdown }]">
                <div
                  class="export-option csv"
                  @click.stop="handleExport('csv')"
                >
                  <i class="fas fa-file-csv"></i>
                  <span>{{ t("fundingReport_export_csv") }}</span>
                </div>
                <div
                  class="export-option excel"
                  @click.stop="handleExport('excel')"
                >
                  <i class="fas fa-file-excel"></i>
                  <span>{{ t("fundingReport_export_excel") }}</span>
                </div>
              </div>
            </button>
          </div>
        </div>
        <div class="table-controls">
          <div class="search-box">
            <input
              type="text"
              v-model="searchQuery"
              :placeholder="t('fundingReport_search_placeholder')"
              @input="handleSearch"
            />
            <i class="fas fa-search search-icon"></i>
          </div>
        </div>
      </div>

      <table class="transaction-table">
        <thead>
          <tr>
            <th class="checkbox-col">
              <label class="custom-checkbox">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  :indeterminate.prop="isIndeterminate"
                  @change="toggleSelectAll"
                />
                <span class="checkbox-checkmark"></span>
              </label>
            </th>
            <th>{{ t("fundingReport_th_dateTime") }}</th>
            <th>{{ t("fundingReport_th_client") }}</th>
            <th>{{ t("fundingReport_th_type") }}</th>
            <th>{{ t("fundingReport_th_amount") }}</th>
            <th>{{ t("fundingReport_th_paymentMethod") }}</th>
            <th>{{ t("fundingReport_th_status") }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-if="transactions.length === 0">
            <tr>
              <td colspan="7" class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>{{ t("fundingReport_empty") }}</p>
              </td>
            </tr>
          </template>
          <template v-else>
            <tr
              v-for="transaction in transactions"
              :key="`${transaction.transactionType}-${transaction.id}`"
              :data-transaction-id="transaction.id"
            >
              <td class="checkbox-col">
                <label class="custom-checkbox">
                  <input
                    type="checkbox"
                    :value="`${transaction.transactionType}-${transaction.id}`"
                    v-model="selectedTransactionIds"
                  />
                  <span class="checkbox-checkmark"></span>
                </label>
              </td>
              <td>
                <div>{{ formatDate(transaction.requestedAt) }}</div>
                <small class="time-small">{{
                  formatTime(transaction.requestedAt)
                }}</small>
              </td>
              <td>
                <div class="client-info">
                  <div class="client-avatar">
                    {{
                      getInitials(transaction.firstName, transaction.lastName)
                    }}
                  </div>
                  <div class="client-details">
                    <div class="client-name">
                      {{ transaction.firstName }} {{ transaction.lastName }}
                    </div>
                    <div class="client-id">
                      {{ t("fundingReport_clientIdLabel") }}
                      {{ transaction.userId }}
                    </div>
                  </div>
                </div>
              </td>
              <td>
                <span
                  :class="['transaction-type', transaction.transactionType]"
                >
                  <i
                    :class="getTransactionTypeIcon(transaction.transactionType)"
                  ></i>
                  {{ getTransactionTypeLabel(transaction.transactionType) }}
                </span>
              </td>
              <td>
                <div
                  :class="[
                    'amount-display',
                    getAmountClass(transaction.transactionType),
                  ]"
                >
                  {{ getAmountPrefix(transaction.transactionType)
                  }}{{ formatCurrency(transaction.amount) }}
                </div>
              </td>
              <td>
                <span
                  :class="['payment-method-badge', transaction.paymentType]"
                >
                  <i :class="getMethodIcon(transaction.paymentMethod)"></i>
                  {{ transaction.paymentMethod }}
                </span>
              </td>
              <td>
                <span :class="['status-badge', transaction.status]">
                  {{ getStatusLabel(transaction.status) }}
                </span>
              </td>
            </tr>
          </template>
        </tbody>
      </table>

      <!-- Pagination -->
      <div class="pagination" v-if="totalPages > 1">
        <div class="pagination-info">
          {{
            tParams(
              "fundingReport_pagination_range",
              "Showing {from}–{to} of {total} transactions",
              {
                from: (currentPage - 1) * perPage + 1,
                to: Math.min(currentPage * perPage, total),
                total,
              },
            )
          }}
        </div>
        <div class="pagination-controls">
          <button
            class="pagination-btn"
            :disabled="currentPage === 1"
            @click="changePage(currentPage - 1)"
          >
            <i class="fas fa-chevron-left"></i>
            {{ t("fundingReport_pagination_previous") }}
          </button>
          <button
            v-for="page in visiblePages"
            :key="page"
            :class="['pagination-btn', { active: currentPage === page }]"
            @click="changePage(page)"
            v-if="page !== '...'"
          >
            {{ page }}
          </button>
          <span v-else class="pagination-ellipsis">...</span>
          <button
            class="pagination-btn"
            :disabled="currentPage === totalPages"
            @click="changePage(currentPage + 1)"
          >
            {{ t("fundingReport_pagination_next") }}
            <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import ExportProgressBanner from "@/components/common/ExportProgressBanner.vue";

import { ref, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { ElConfigProvider, ElDatePicker } from "element-plus";
import zhCn from "element-plus/es/locale/lang/zh-cn";
import en from "element-plus/es/locale/lang/en";
import "element-plus/es/components/date-picker/style/css";
import "element-plus/es/components/config-provider/style/css";
import fundingReportApi from "../services/fundingReportApi";
import { formatCurrency, formatNumber } from "../utils/helpers";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { useAsyncReportExport } from "@/composables/useAsyncReportExport";

const { t, tParams, languageStore } = useAdminI18n();

const MAX_SELECTED_EXPORT_ITEMS = 500;
const ALLOWED_EXPORT_TYPES = ["deposit", "withdrawal", "internal_transfer"];

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
  getActiveExport: () => fundingReportApi.getActiveExport(),
  enqueueExport: (params) => fundingReportApi.exportReport(params),
  getExportStatus: (jobId) => fundingReportApi.getExportStatus(jobId),
  cancelExport: (jobId) => fundingReportApi.cancelExport(jobId),
  downloadExport: (jobId) => fundingReportApi.downloadExport(jobId),
  buildFilename: () =>
    `funding_report_${new Date().toISOString().split("T")[0]}.csv`,
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

const authStore = useAuthStore();

// 权限检查
const hasReadonlyPermission = computed(() =>
  authStore.hasPermission("page_fundingreport_readonly"),
);
const hasExportPermission = computed(() =>
  authStore.hasPermission("page_fundingreport_export"),
);

const router = useRouter();

// State
const loading = ref(true);
const error = ref(null);
const statistics = ref({
  totalDeposits: 0,
  totalWithdrawals: 0,
  totalInternalTransfer: 0,
  netFlow: 0,
  depositCount: 0,
  withdrawalCount: 0,
  internalTransferCount: 0,
});
const transactions = ref([]);

// Pagination
const currentPage = ref(1);
const perPage = ref(10);
const total = ref(0);
const totalPages = ref(0);
const hasMore = ref(false);

// 可见页码
const visiblePages = computed(() => {
  const pages = [];
  const maxVisible = 5;

  if (totalPages.value <= maxVisible) {
    for (let i = 1; i <= totalPages.value; i++) {
      pages.push(i);
    }
  } else {
    if (currentPage.value <= 3) {
      for (let i = 1; i <= 4; i++) pages.push(i);
      pages.push("...");
      pages.push(totalPages.value);
    } else if (currentPage.value >= totalPages.value - 2) {
      pages.push(1);
      pages.push("...");
      for (let i = totalPages.value - 3; i <= totalPages.value; i++)
        pages.push(i);
    } else {
      pages.push(1);
      pages.push("...");
      pages.push(currentPage.value - 1);
      pages.push(currentPage.value);
      pages.push(currentPage.value + 1);
      pages.push("...");
      pages.push(totalPages.value);
    }
  }

  return pages;
});

// Date Filter
const activePreset = ref("week");
const startDate = ref("");
const endDate = ref("");

// Search and Selection
const searchQuery = ref("");
const selectedTransactionIds = ref([]);
const showExportDropdown = ref(false);

// Computed
const isAllSelected = computed(() => {
  return (
    transactions.value.length > 0 &&
    selectedTransactionIds.value.length === transactions.value.length
  );
});

const isIndeterminate = computed(() => {
  return (
    selectedTransactionIds.value.length > 0 &&
    selectedTransactionIds.value.length < transactions.value.length
  );
});

const netFlowClass = computed(() => {
  return statistics.value.netFlow >= 0 ? "positive" : "negative";
});

const netFlowSign = computed(() => {
  return statistics.value.netFlow >= 0 ? "+" : "";
});

// 初始化日期（本周）
const initializeDates = () => {
  const today = new Date();
  const weekAgo = new Date(today);
  weekAgo.setDate(weekAgo.getDate() - 7);

  startDate.value = formatDateInput(weekAgo);
  endDate.value = formatDateInput(today);
};

// 格式化日期为input格式
const formatDateInput = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

// 选择预设
const selectPreset = (preset) => {
  activePreset.value = preset;

  const today = new Date();
  let start,
    end = new Date();

  switch (preset) {
    case "today":
      start = new Date();
      break;
    case "week":
      start = new Date(today);
      start.setDate(start.getDate() - 7);
      break;
    case "month":
      start = new Date(today);
      start.setMonth(start.getMonth() - 1);
      break;
    case "quarter":
      start = new Date(today);
      start.setMonth(start.getMonth() - 3);
      break;
  }

  startDate.value = formatDateInput(start);
  endDate.value = formatDateInput(end);

  applyDateFilter();
};

// 应用日期筛选
const applyDateFilter = () => {
  if (!startDate.value || !endDate.value) {
    alert(t("fundingReport_alert_datesRequired"));
    return;
  }

  if (new Date(startDate.value) > new Date(endDate.value)) {
    alert(t("fundingReport_alert_dateOrder"));
    return;
  }

  currentPage.value = 1;
  loadReport();
};

// 加载报告
const loadReport = async () => {
  loading.value = true;
  error.value = null;

  try {
    const params = {
      startDate: startDate.value,
      endDate: endDate.value,
    };

    // 加载统计数据
    const statsResponse = await fundingReportApi.getStatistics(params);
    if (statsResponse.success) {
      statistics.value = statsResponse.data.summary || statsResponse.data;
    }

    // 加载交易列表
    const transactionsParams = {
      ...params,
      page: currentPage.value,
      per_page: perPage.value,
    };

    if (searchQuery.value) {
      transactionsParams.search = searchQuery.value;
    }

    const transactionsResponse =
      await fundingReportApi.getAllTransactions(transactionsParams);
    if (transactionsResponse.success) {
      transactions.value =
        transactionsResponse.data.items || transactionsResponse.data || [];
      // 从 pagination 对象中获取所有分页数据
      if (transactionsResponse.data.pagination) {
        const pagination = transactionsResponse.data.pagination;
        total.value = pagination.total || 0;
        perPage.value = pagination.per_page || 10;
        currentPage.value = pagination.page || 1;
        totalPages.value = pagination.total_pages || 0;
        hasMore.value = pagination.has_more || false;
      } else {
        // 兼容旧格式
        total.value = transactionsResponse.data.total || 0;
        totalPages.value = Math.ceil(total.value / perPage.value);
      }
    }

    loading.value = false;
  } catch (err) {
    console.error("Failed to load report:", err);
    error.value = err.response?.data?.message || t("fundingReport_err_load");
    loading.value = false;
  }
};

// 搜索
const handleSearch = () => {
  currentPage.value = 1;
  loadReport();
};

// 切换全选
const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedTransactionIds.value = [];
  } else {
    selectedTransactionIds.value = transactions.value.map(
      (t) => `${t.transactionType}-${t.id}`,
    );
  }
};

const selectedExportItems = () => {
  const selected = new Set(selectedTransactionIds.value);
  return transactions.value
    .filter((row) => selected.has(`${row.transactionType}-${row.id}`))
    .map((row) => ({
      type: String(row.transactionType || ""),
      id: Number(row.id),
    }))
    .filter((item) => item.id > 0 && ALLOWED_EXPORT_TYPES.includes(item.type));
};

const toggleExportDropdown = () => {
  if (isExportRunning.value) return;
  showExportDropdown.value = !showExportDropdown.value;
};

const handleExport = async (format) => {
  showExportDropdown.value = false;
  if (!hasExportPermission.value || isExportRunning.value) return;
  if (format !== "csv" && format !== "excel") return;

  const items = selectedExportItems();
  if (!items.length) {
    alert(
      tParams("fundingReport_alert_exportFail", "Failed to export: {msg}", {
        msg: t("fundingReport_empty"),
      }),
    );
    return;
  }
  if (items.length > MAX_SELECTED_EXPORT_ITEMS) {
    alert(
      tParams("fundingReport_alert_exportFail", "Failed to export: {msg}", {
        msg: String(MAX_SELECTED_EXPORT_ITEMS),
      }),
    );
    return;
  }

  await startOrResumeExport(() => ({ items }));
};

// 切换页码
const changePage = (page) => {
  if (page === "...") return;
  currentPage.value = page;
  loadReport();
};

// 格式化
const formatAmount = (amount) => {
  if (!amount) return "0.00";
  return parseFloat(amount).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const formatDate = (dateString) => {
  if (!dateString) return "-";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
};

const formatTime = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  return date.toLocaleTimeString("en-US", {
    hour: "2-digit",
    minute: "2-digit",
  });
};

const getInitials = (firstName, lastName) => {
  if (!firstName && !lastName) return "??";
  const first = firstName?.[0] || "";
  const last = lastName?.[0] || "";
  return (first + last).toUpperCase();
};

const getStatusLabel = (status) => {
  const s = String(status ?? "").toLowerCase();
  const keys = {
    pending: "fundingReport_status_pending",
    processing: "fundingReport_status_processing",
    completed: "fundingReport_status_completed",
    rejected: "fundingReport_status_rejected",
    failed: "fundingReport_status_failed",
    cancelled: "fundingReport_status_cancelled",
  };
  const k = keys[s];
  return k ? t(k) : status;
};

const getMethodIcon = (method) => {
  const lowerMethod = method?.toLowerCase() || "";
  if (lowerMethod.includes("bitcoin")) return "fab fa-bitcoin";
  if (lowerMethod.includes("ethereum")) return "fab fa-ethereum";
  if (lowerMethod.includes("usdt") || lowerMethod.includes("usdc"))
    return "fas fa-coins";
  if (lowerMethod.includes("bank")) return "fas fa-university";
  if (lowerMethod.includes("alchemy")) return "fas fa-credit-card";
  if (lowerMethod.includes("internal") || lowerMethod.includes("transfer"))
    return "fas fa-exchange-alt";
  return "fas fa-wallet";
};

const getTransactionTypeIcon = (type) => {
  if (type === "deposit") return "fas fa-arrow-down";
  if (type === "withdrawal") return "fas fa-arrow-up";
  if (type === "internal_transfer") return "fas fa-exchange-alt";
  return "fas fa-exchange-alt";
};

const getTransactionTypeLabel = (type) => {
  const x = String(type ?? "").toLowerCase();
  if (x === "deposit") return t("fundingReport_type_deposit");
  if (x === "withdrawal") return t("fundingReport_type_withdrawal");
  if (x === "internal_transfer")
    return t("fundingReport_type_internalTransfer");
  return type;
};

const getAmountClass = (type) => {
  if (type === "deposit") return "positive";
  if (type === "withdrawal") return "negative";
  if (type === "internal_transfer") return "neutral";
  return "";
};

const getAmountPrefix = (type) => {
  if (type === "deposit") return "+";
  if (type === "withdrawal") return "-";
  if (type === "internal_transfer") return "";
  return "";
};

// 关闭dropdown when clicking outside
watch(showExportDropdown, () => {
  if (showExportDropdown.value) {
    const handler = () => {
      showExportDropdown.value = false;
      document.removeEventListener("click", handler);
    };
    setTimeout(() => {
      document.addEventListener("click", handler);
    }, 10);
  }
});

watch(isExportRunning, (running) => {
  if (running) {
    showExportDropdown.value = false;
  }
});

onMounted(() => {
  initializeDates();
  loadReport();
  resumeActiveExportIfAny();
});
</script>

<style scoped>
.funding-report-page {
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

/* Statistics Cards */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(
    3,
    1fr
  ); /* 4 列时改为 repeat(4, 1fr)，显示 Total Internal Transfer 卡片 */
  gap: 20px;
  margin-bottom: 30px;
}

.stat-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 25px;
  box-shadow: var(--shadow-sm);
  transition: all 0.3s ease;
  border-left: 4px solid transparent;
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.stat-card.deposit {
  border-left-color: var(--color-success);
}

.stat-card.withdrawal {
  border-left-color: var(--color-danger);
}

/* Total Internal Transfer 卡片样式，显示该卡片时取消注释
.stat-card.internal-transfer {
  border-left-color: #3182ce;
}
*/

.stat-card.net {
  border-left-color: var(--color-brand);
}

.stat-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 15px;
}

.stat-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
}

.stat-card.deposit .stat-icon {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.stat-card.withdrawal .stat-icon {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

/* Total Internal Transfer 卡片图标样式
.stat-card.internal-transfer .stat-icon {
  background: var(--color-info-soft);
  color: var(--color-info);
}
*/

.stat-card.net .stat-icon {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.stat-value {
  font-size: 32px;
  font-weight: 700;
  color: var(--color-ink);
  margin-bottom: 10px;
}

.stat-value.positive {
  color: var(--color-success);
}

.stat-value.negative {
  color: var(--color-danger);
}

.stat-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 15px;
  border-top: 1px solid var(--color-border);
}

.stat-count {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 13px;
  color: var(--color-muted);
}

.stat-change {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 13px;
  font-weight: 600;
}

.stat-change.positive {
  color: var(--color-success);
}

.stat-change.negative {
  color: var(--color-danger);
}

/* Date Filter Section */
.date-filter-section {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 25px 30px;
  margin-bottom: 30px;
  box-shadow: var(--shadow-sm);
  overflow-x: auto;
}

.date-filter-container {
  display: flex;
  align-items: center;
  gap: 15px;
  flex-wrap: wrap;
}

.date-filter-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  white-space: nowrap;
}

.date-filter-presets {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.preset-btn {
  padding: 8px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  color: var(--color-text);
  white-space: nowrap;
}

.preset-btn:hover {
  border-color: var(--color-border-strong);
  background: var(--color-surface-soft);
}

.preset-btn.active {
  background: var(--color-brand-solid);
  color: white;
  border-color: var(--color-brand);
}

.date-input-wrapper {
  display: flex;
  flex-direction: column;
  gap: 5px;
  min-width: 140px;
}

.date-input-wrapper label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-muted);
  white-space: nowrap;
}

.date-input-wrapper .filter-date {
  width: 100%;
}

.date-input-wrapper :deep(.el-input__wrapper) {
  border-radius: var(--radius-md);
  box-shadow: 0 0 0 1px var(--color-border) inset;
}

.date-input-wrapper :deep(.el-input__wrapper.is-focus) {
  box-shadow:
    0 0 0 1px var(--color-brand) inset,
    0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.btn-apply-filter {
  padding: 10px 20px;
  background: var(--color-brand-solid);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
}

.btn-apply-filter:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.loading-container,
.error-container {
  text-align: center;
  padding: 60px 20px;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
}

.loading-container i {
  font-size: 48px;
  color: var(--color-brand);
  margin-bottom: 20px;
}

.error-container i {
  font-size: 48px;
  color: var(--color-danger);
  margin-bottom: 20px;
}

.loading-container p,
.error-container p {
  font-size: 16px;
  color: var(--color-text);
  margin-bottom: 20px;
}

/* Transaction Table */
.transaction-table-container {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
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

.bulk-actions {
  display: none;
  align-items: center;
  gap: 10px;
  padding: 10px 15px;
  background: var(--color-brand-soft);
  border-radius: var(--radius-md);
  border: 2px solid var(--color-brand);
  margin-top: 10px;
}

.bulk-actions.show {
  display: flex;
}

.bulk-actions-label {
  font-size: 13px;
  color: var(--color-brand);
  font-weight: 600;
}

.bulk-actions-count {
  background: var(--color-brand-solid);
  color: white;
  padding: 2px 8px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
}

.btn-bulk {
  padding: 6px 12px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  position: relative;
}

.btn-bulk-export {
  background: var(--color-brand-solid);
  color: white;
}

.btn-bulk-export:hover:not(:disabled) {
  background: var(--color-brand-strong);
  transform: translateY(-1px);
}

.btn-bulk-export:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.export-dropdown {
  position: absolute;
  top: calc(100% + 5px);
  left: 0;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  min-width: 150px;
  display: none;
  z-index: 1000;
  overflow: hidden;
}

.export-dropdown.show {
  display: block;
}

.export-option {
  padding: 10px 15px;
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--color-text);
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 13px;
  font-weight: 600;
  border-bottom: 1px solid var(--color-border);
}

.export-option:last-child {
  border-bottom: none;
}

.export-option:hover {
  background: var(--color-surface-soft);
  color: var(--color-brand);
}

.export-option.csv:hover {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.export-option.excel:hover {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.table-controls {
  display: flex;
  gap: 15px;
  align-items: center;
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
  transition: all 0.3s ease;
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

.transaction-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  background: var(--color-surface);
}

.transaction-table thead {
  background: var(--color-surface-soft);
}

.transaction-table th {
  padding: 16px 20px;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
}

.transaction-table tbody tr {
  border-bottom: 1px solid var(--color-border);
  transition: all 0.2s ease;
}

.transaction-table tbody tr:hover {
  background: var(--color-surface-soft);
}

.transaction-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
}

.checkbox-col {
  width: 50px;
  text-align: center;
  padding: 16px 10px !important;
}

.custom-checkbox {
  position: relative;
  display: inline-block;
  width: 20px;
  height: 20px;
}

.custom-checkbox input[type="checkbox"] {
  position: absolute;
  opacity: 0;
  cursor: pointer;
  width: 20px;
  height: 20px;
  margin: 0;
}

.checkbox-checkmark {
  position: absolute;
  top: 0;
  left: 0;
  height: 20px;
  width: 20px;
  background-color: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: 4px;
  transition: all 0.3s ease;
}

.custom-checkbox:hover .checkbox-checkmark {
  border-color: var(--color-brand);
}

.custom-checkbox input[type="checkbox"]:checked ~ .checkbox-checkmark {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
}

.checkbox-checkmark:after {
  content: "";
  position: absolute;
  display: none;
  left: 6px;
  top: 2px;
  width: 5px;
  height: 10px;
  border: solid white;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
}

.custom-checkbox input[type="checkbox"]:checked ~ .checkbox-checkmark:after {
  display: block;
}

.client-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.client-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--color-brand-solid);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 700;
  font-size: 14px;
  flex-shrink: 0;
}

.client-details {
  display: flex;
  flex-direction: column;
}

.client-name {
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 3px;
}

.client-id {
  font-size: 12px;
  color: var(--color-muted);
}

.time-small {
  color: var(--color-faint);
  font-size: 12px;
  display: block;
  margin-top: 2px;
}

.transaction-type {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.transaction-type.deposit {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.transaction-type.withdrawal {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.transaction-type.internal_transfer {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.amount-display {
  font-weight: 700;
  font-size: 16px;
}

.amount-display.positive {
  color: var(--color-success);
}

.amount-display.negative {
  color: var(--color-danger);
}

.amount-display.neutral {
  color: var(--color-text);
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.completed {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.status-badge.processing {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.status-badge.failed,
.status-badge.rejected {
  background: var(--color-danger-soft);
  color: var(--color-danger);
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

.empty-state {
  text-align: center;
  padding: 60px 20px !important;
}

.empty-state i {
  font-size: 48px;
  margin-bottom: 15px;
  display: block;
  color: var(--color-border-strong);
}

.empty-state p {
  font-size: 16px;
  color: var(--color-muted);
}

/* Pagination */
.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
}

.pagination-info {
  font-size: 14px;
  color: var(--color-muted);
}

.pagination-controls {
  display: flex;
  gap: 8px;
}

.pagination-btn {
  padding: 8px 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  color: var(--color-text);
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.pagination-btn:hover:not(:disabled) {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pagination-btn.active {
  background: var(--color-brand-solid);
  color: white;
  border-color: var(--color-brand);
}

.pagination-ellipsis {
  padding: 8px 12px;
  color: var(--color-faint);
  font-weight: 600;
}

.btn {
  padding: 12px 24px;
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

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
  box-shadow: 0 4px 15px rgba(var(--color-brand-rgb), 0.4);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(var(--color-brand-rgb), 0.5);
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

@media (min-width: 769px) and (max-width: 1024px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .funding-report-page {
    padding: 20px 15px;
  }

  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .date-filter-container {
    flex-direction: column;
    align-items: flex-start;
  }

  .date-filter-presets {
    width: 100%;
  }

  .preset-btn {
    flex: 1;
  }

  .date-input-wrapper {
    width: 100%;
  }

  .btn-apply-filter {
    width: 100%;
    justify-content: center;
  }

  .table-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .table-controls {
    width: 100%;
  }

  .search-box input {
    width: 100%;
  }

  .transaction-table {
    font-size: 12px;
  }

  .transaction-table th,
  .transaction-table td {
    padding: 12px 10px;
  }

  .pagination {
    flex-direction: column;
    gap: 15px;
  }

  .pagination-controls {
    flex-wrap: wrap;
    justify-content: center;
  }
}
</style>
