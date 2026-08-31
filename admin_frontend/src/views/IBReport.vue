<template>
  <div class="ib-report-page">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-title">
        <h1>{{ t("page_ibReport_title") }}</h1>
        <p>{{ t("page_ibReport_sub") }}</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- Statistics Cards -->
    <ExportProgressBanner
      v-if="exportBannerVisible && exportStatusText"
      :cancelling="exportCancelling"
      :status-text="exportStatusText"
      :percent="exportProgressPercent"
      :cancel-disabled="!exportJobId"
      :title="t('ibReport_exportInProgressTitle', 'Export in progress')"
      :cancelling-title="t('ibReport_exportCancelling', 'Cancelling export...')"
      :cancel-label="t('ibReport_exportCancel', 'Cancel')"
      @cancel-export="cancelActiveExport"
    />

    <div
      v-if="exportModal.visible"
      class="export-modal-overlay"
      @click="onExportModalContinue"
    >
      <div class="export-modal" @click.stop>
        <div class="export-modal-header">
          <h3>{{ t("ibReport_exportInProgressTitle") }}</h3>
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
            {{ exportModal.message || t("ibReport_exportInProgressMsg") }}
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
            {{ t("ibReport_exportContinue") }}
          </button>
          <button
            type="button"
            class="export-modal-btn secondary"
            @click="onExportModalCancel"
            :disabled="exportModal.busy"
          >
            {{ t("ibReport_exportCancel") }}
          </button>
        </div>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card total">
        <div class="stat-header">
          <span class="stat-title">{{
            t("ibReport_stat_totalCommission")
          }}</span>
          <div class="stat-icon">
            <i class="fas fa-hand-holding-usd"></i>
          </div>
        </div>
        <div class="stat-value">
          {{ formatCurrency(statistics.totalCommission || 0) }}
        </div>
        <div class="stat-footer">
          <div
            :class="[
              'stat-change',
              changeClass(statistics.totalCommissionChange),
            ]"
          >
            <i :class="changeIcon(statistics.totalCommissionChange)"></i>
            <span>{{
              changeText(statistics.totalCommissionChange, true)
            }}</span>
          </div>
          <span class="stat-period">{{ t("ibReport_vsLastPeriod") }}</span>
        </div>
      </div>

      <div class="stat-card paid">
        <div class="stat-header">
          <span class="stat-title">{{
            t("ibReport_stat_paidCommission")
          }}</span>
          <div class="stat-icon">
            <i class="fas fa-check-circle"></i>
          </div>
        </div>
        <div class="stat-value">
          {{ formatCurrency(statistics.paidCommission || 0) }}
        </div>
        <div class="stat-footer">
          <div
            :class="[
              'stat-change',
              changeClass(statistics.paidCommissionChange),
            ]"
          >
            <i :class="changeIcon(statistics.paidCommissionChange)"></i>
            <span>{{ changeText(statistics.paidCommissionChange, true) }}</span>
          </div>
          <span class="stat-period">{{ t("ibReport_vsLastPeriod") }}</span>
        </div>
      </div>

      <div class="stat-card total-lots">
        <div class="stat-header">
          <span class="stat-title">{{ t("ibReport_stat_totalLots") }}</span>
          <div class="stat-icon">
            <i class="fas fa-layer-group"></i>
          </div>
        </div>
        <div class="stat-value">
          {{ formatNumber(statistics.totalLots || 0, 2) }}
        </div>
        <div class="stat-footer">
          <div
            :class="['stat-change', changeClass(statistics.totalLotsChange)]"
          >
            <i :class="changeIcon(statistics.totalLotsChange)"></i>
            <span>{{ changeText(statistics.totalLotsChange, true) }}</span>
          </div>
          <span class="stat-period">{{ t("ibReport_vsLastPeriod") }}</span>
        </div>
      </div>

      <div class="stat-card total-trade">
        <div class="stat-header">
          <span class="stat-title">{{ t("ibReport_stat_totalTrade") }}</span>
          <div class="stat-icon">
            <i class="fas fa-exchange-alt"></i>
          </div>
        </div>
        <div class="stat-value">
          {{ formatNumber(statistics.totalTrade || 0) }}
        </div>
        <div class="stat-footer">
          <div
            :class="['stat-change', changeClass(statistics.totalTradeChange)]"
          >
            <i :class="changeIcon(statistics.totalTradeChange)"></i>
            <span>{{ changeText(statistics.totalTradeChange, true) }}</span>
          </div>
          <span class="stat-period">{{ t("ibReport_vsLastPeriod") }}</span>
        </div>
      </div>
    </div>

    <!-- Date Filter Section（与客户工单一致：Element Plus 日期，随后台语言切换文案） -->
    <div class="date-filter-section">
      <el-config-provider :locale="elementPlusLocale">
        <div class="date-filter-container">
          <div class="date-filter-row date-filter-row--presets">
            <span class="date-filter-label">{{ t("ibReport_timePeriod") }}</span>
            <div class="date-filter-presets">
              <button
                :class="['preset-btn', { active: activePreset === 'today' }]"
                @click="selectPreset('today')"
              >
                {{ t("ibReport_preset_today") }}
              </button>
              <button
                :class="['preset-btn', { active: activePreset === 'month' }]"
                @click="selectPreset('month')"
              >
                {{ t("ibReport_preset_month") }}
              </button>
              <button
                :class="['preset-btn', { active: activePreset === 'quarter' }]"
                @click="selectPreset('quarter')"
              >
                {{ t("ibReport_preset_quarter") }}
              </button>
              <button
                :class="['preset-btn', { active: activePreset === 'year' }]"
                @click="selectPreset('year')"
              >
                {{ t("ibReport_preset_year") }}
              </button>
            </div>
          </div>
          <div class="date-filter-row date-filter-row--controls">
            <div class="date-input-wrapper">
              <label>{{ t("ibReport_fromDate") }}</label>
              <el-date-picker
                v-model="startDate"
                type="date"
                value-format="YYYY-MM-DD"
                :placeholder="t('ibReport_fromDate')"
                class="filter-date"
                clearable
              />
            </div>
            <div class="date-input-wrapper">
              <label>{{ t("ibReport_toDate") }}</label>
              <el-date-picker
                v-model="endDate"
                type="date"
                value-format="YYYY-MM-DD"
                :placeholder="t('ibReport_toDate')"
                class="filter-date"
                clearable
              />
            </div>
            <button
              type="button"
              class="btn-apply-filter"
              @click="applyDateFilter"
            >
              <i class="fas fa-filter"></i> {{ t("ibReport_applyFilter") }}
            </button>
            <button
              v-if="hasExportPermission"
              type="button"
              class="btn-export"
              @click="exportDetailReport"
            >
              <i class="fas fa-download"></i> {{ t("ibReport_export") }}
            </button>
          </div>
        </div>
      </el-config-provider>
    </div>

    <!-- Search & Filter Section -->
    <div class="search-filter-section">
      <div class="search-container">
        <div class="search-field">
          <label>{{ t("ibReport_searchIb") }}</label>
          <div class="search-input-wrapper">
            <input
              type="text"
              v-model="searchQuery"
              :placeholder="t('ibReport_search_placeholder')"
              @change="handleSearch"
            />
            <i class="fas fa-search search-icon"></i>
          </div>
        </div>
        <div class="filter-field">
          <label>{{ t("ibReport_label_status") }}</label>
          <select v-model="statusFilter" @change="handleFilter">
            <option value="">{{ t("ibReport_status_all") }}</option>
            <option value="paid">{{ t("ibReport_status_paid") }}</option>
            <option value="pending">{{ t("ibReport_status_pending") }}</option>
            <option value="processing">
              {{ t("ibReport_status_processing") }}
            </option>
          </select>
        </div>
        <div class="filter-field">
          <label>{{ t("ibReport_label_sortBy") }}</label>
          <select v-model="sortBy" @change="handleSort">
            <option value="commission_desc">
              {{ t("ibReport_sort_commissionDesc") }}
            </option>
            <option value="commission_asc">
              {{ t("ibReport_sort_commissionAsc") }}
            </option>
            <option value="name_asc">{{ t("ibReport_sort_nameAsc") }}</option>
            <option value="name_desc">{{ t("ibReport_sort_nameDesc") }}</option>
          </select>
        </div>
        <div style="align-self: flex-end">
          <button class="btn-clear-filter" @click="clearFilters">
            <i class="fas fa-times"></i> {{ t("ibReport_clear") }}
          </button>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="error-message">
      <i class="fas fa-exclamation-circle"></i> {{ error }}
    </div>

    <!-- IB Commission Table -->
    <div class="ib-table-container">
      <div class="table-header">
        <h2>
          <i class="fas fa-chart-line"></i> {{ t("ibReport_section_details") }}
        </h2>
      </div>

      <div v-if="loading" class="loading-container">
        <i class="fas fa-spinner fa-spin"></i> {{ t("ibReport_loading") }}
      </div>

      <div v-else class="ib-table-scroll">
        <table class="ib-table">
          <thead>
            <tr>
              <th>{{ t("ibReport_th_ibInfo") }}</th>
              <th>{{ t("ibReport_th_totalCommission") }}</th>
              <th>{{ t("ibReport_th_clientsReferred") }}</th>
              <th>{{ t("ibReport_th_tradingVolume") }}</th>
              <th>{{ t("ibReport_th_lastPayout") }}</th>
              <th>{{ t("ibReport_th_detail") }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="filteredIbList.length > 0">
              <template v-for="ib in filteredIbList" :key="ib.id">
                <tr
                  :data-ib-id="ib.id"
                  :class="{ expanded: expandedRows.includes(ib.id) }"
                >
                  <td>
                    <div class="ib-info">
                      <div class="ib-avatar">{{ ib.initials || "—" }}</div>
                      <div class="ib-details">
                        <div class="ib-name">{{ ib.name || "—" }}</div>
                        <div class="ib-code">
                          {{ ib.ibCode || ib.code || "" }}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div class="commission-amount">
                      {{ formatCurrency(ib.totalCommission || 0) }}
                    </div>
                  </td>
                  <td>
                    {{
                      tParams("ibReport_clientsCount", "", {
                        count: ib.clientsReferred || 0,
                      })
                    }}
                  </td>
                  <td>{{ formatNumber(ib.tradingVolume ?? 0, 2) }}</td>
                  <td>{{ ib.lastPayout || "--" }}</td>
                  <td>
                    <button
                      :class="['btn-action', 'btn-detail']"
                      @click="toggleDetails(ib.id)"
                    >
                      <i
                        :class="
                          expandedRows.includes(ib.id)
                            ? 'fas fa-chevron-up'
                            : 'fas fa-chevron-down'
                        "
                      ></i>
                      {{ t("ibReport_btn_detail") }}
                    </button>
                  </td>
                </tr>
                <tr v-if="expandedRows.includes(ib.id)" class="detail-row show">
                  <td colspan="6">
                    <div class="detail-content">
                      <div v-if="loadingDetails[ib.id]" class="loading-details">
                        <i class="fas fa-spinner fa-spin"></i>
                        {{ t("ibReport_loading_details") }}
                      </div>
                      <div v-else>
                        <div class="detail-grid">
                          <div class="detail-item">
                            <span class="detail-label">{{
                              t("ibReport_detail_totalCommission")
                            }}</span>
                            <div class="detail-value">
                              {{ formatCurrency(ib.totalCommission || 0) }}
                            </div>
                          </div>
                          <div class="detail-item">
                            <span class="detail-label">{{
                              t("ibReport_detail_paidCommission")
                            }}</span>
                            <div class="detail-value">
                              {{ formatCurrency(ib.paidCommission || 0) }}
                            </div>
                          </div>
                          <div class="detail-item">
                            <span class="detail-label">{{
                              t("ibReport_detail_pendingCommission")
                            }}</span>
                            <div class="detail-value">
                              {{ formatCurrency(ib.pendingCommission || 0) }}
                            </div>
                          </div>
                        </div>
                        <h4
                          style="
                            font-size: 14px;
                            font-weight: 600;
                            color: var(--color-ink);
                            margin-bottom: 15px;
                          "
                        >
                          <i class="fas fa-list"></i>
                          {{ t("ibReport_breakdown_title") }}
                        </h4>
                        <div
                          v-if="ib.breakdown && ib.breakdown.length > 0"
                          class="detail-table-scroll detail-table-scroll--wide"
                        >
                          <table class="detail-table detail-table--wide">
                            <thead>
                              <tr>
                                <th>
                                  {{ t("ibReport_breakdown_th_date", "Date") }}
                                </th>
                                <th>
                                  {{
                                    t(
                                      "ibReport_breakdown_th_accountNumber",
                                      "Account Number",
                                    )
                                  }}
                                </th>
                                <th>
                                  {{ t("ibReport_breakdown_th_name", "Name") }}
                                </th>
                                <th>
                                  {{
                                    t(
                                      "ibReport_breakdown_th_accountOwner",
                                      "Account Owner",
                                    )
                                  }}
                                </th>
                                <th>
                                  {{
                                    t("ibReport_breakdown_th_email", "Email")
                                  }}
                                </th>
                                <th>
                                  {{ t("ibReport_breakdown_th_kyc", "KYC") }}
                                </th>
                                <th>
                                  {{
                                    t(
                                      "ibReport_breakdown_th_tradeDate",
                                      "Trade Date",
                                    )
                                  }}
                                </th>
                                <th>
                                  {{
                                    t("ibReport_breakdown_th_tradingId", "ID")
                                  }}
                                </th>
                                <th>
                                  {{
                                    t("ibReport_breakdown_th_symbol", "Symbol")
                                  }}
                                </th>
                                <th>
                                  {{ t("ibReport_breakdown_th_lots", "Lots") }}
                                </th>
                                <th>
                                  {{
                                    t(
                                      "ibReport_breakdown_th_lastDepositTime",
                                      "Last Deposit Time",
                                    )
                                  }}
                                </th>
                                <th>
                                  {{
                                    t("ibReport_breakdown_th_amount", "Amount")
                                  }}
                                </th>
                                <th>
                                  {{
                                    t(
                                      "ibReport_breakdown_th_platform",
                                      "Platform",
                                    )
                                  }}
                                </th>
                                <th>
                                  {{
                                    t(
                                      "ibReport_breakdown_th_accountType",
                                      "Account Type",
                                    )
                                  }}
                                </th>
                                <th>
                                  {{
                                    t(
                                      "ibReport_breakdown_th_baseCurrency",
                                      "Base Currency",
                                    )
                                  }}
                                </th>
                                <th>
                                  {{
                                    t(
                                      "ibReport_breakdown_th_balance",
                                      "Balance",
                                    )
                                  }}
                                </th>
                                <th>
                                  {{
                                    t(
                                      "ibReport_breakdown_th_profitLoss",
                                      "Profit/Loss",
                                    )
                                  }}
                                </th>
                                <th>
                                  {{
                                    t(
                                      "ibReport_breakdown_th_marginLevel",
                                      "Margin Level",
                                    )
                                  }}
                                </th>
                                <th>
                                  {{
                                    t(
                                      "ibReport_breakdown_th_accountEquity",
                                      "Account Equity",
                                    )
                                  }}
                                </th>
                                <th>
                                  {{
                                    t("ibReport_breakdown_th_credit", "Credit")
                                  }}
                                </th>
                                <th>
                                  {{
                                    t("ibReport_breakdown_th_status", "Status")
                                  }}
                                </th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr
                                v-for="(row, index) in ib.breakdown"
                                :key="index"
                              >
                                <td>{{ row.date || "—" }}</td>
                                <td>{{ row.accountNumber || "—" }}</td>
                                <td>{{ row.name || "—" }}</td>
                                <td>{{ row.accountOwner || "—" }}</td>
                                <td>{{ row.email || "—" }}</td>
                                <td>{{ row.kyc || "—" }}</td>
                                <td>{{ row.tradeDate || "—" }}</td>
                                <td>{{ row.tradingId || "—" }}</td>
                                <td>{{ row.symbol || "—" }}</td>
                                <td>{{ formatNumber(row.lots || 0, 2) }}</td>
                                <td>{{ row.lastDepositTime || "—" }}</td>
                                <td>{{ formatCurrency(row.amount || 0) }}</td>
                                <td>{{ row.platform || "—" }}</td>
                                <td>{{ row.accountType || "—" }}</td>
                                <td>{{ row.baseCurrency || "—" }}</td>
                                <td>{{ formatCurrency(row.balance || 0) }}</td>
                                <td>
                                  {{ formatCurrency(row.profitLoss || 0) }}
                                </td>
                                <td>
                                  {{ formatNumber(row.marginLevel || 0, 2) }}
                                </td>
                                <td>
                                  {{ formatCurrency(row.accountEquity || 0) }}
                                </td>
                                <td>{{ formatCurrency(row.credit || 0) }}</td>
                                <td>
                                  <span
                                    :class="[
                                      'status-badge',
                                      (row.status || 'pending').toLowerCase(),
                                    ]"
                                  >
                                    {{ statusDisplay(row.status || "pending") }}
                                  </span>
                                </td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                        <div v-else class="no-breakdown">
                          <p>{{ t("ibReport_no_breakdown") }}</p>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </template>
            <tr v-else>
              <td colspan="6" class="no-data">
                <p>{{ t("ibReport_empty") }}</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination">
        <div class="pagination-info">
          {{
            tParams("ibReport_pagination_range", "", {
              from: formatNumber((currentPage - 1) * perPage + 1),
              to: formatNumber(Math.min(currentPage * perPage, totalIbCount)),
              total: formatNumber(totalIbCount),
            })
          }}
        </div>
        <div class="pagination-controls">
          <button
            class="pagination-btn"
            :disabled="currentPage === 1"
            @click="changePage(currentPage - 1)"
          >
            <i class="fas fa-chevron-left"></i>
            {{ t("ibReport_pagination_previous") }}
          </button>
          <template v-for="page in visiblePages" :key="page">
            <button
              v-if="page !== '...'"
              :class="['pagination-btn', { active: currentPage === page }]"
              @click="changePage(page)"
            >
              {{ page }}
            </button>
            <span v-else class="pagination-ellipsis">...</span>
          </template>
          <button
            class="pagination-btn"
            :disabled="currentPage === totalPages"
            @click="changePage(currentPage + 1)"
          >
            {{ t("ibReport_pagination_next") }}
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
import { useAuthStore } from "@/stores/auth";
import { ElConfigProvider, ElDatePicker } from "element-plus";
import zhCn from "element-plus/es/locale/lang/zh-cn";
import en from "element-plus/es/locale/lang/en";
import "element-plus/es/components/date-picker/style/css";
import "element-plus/es/components/config-provider/style/css";
import { formatCurrency, formatNumber } from "@/utils/helpers";
import ibCommissionReportService from "@/services/ibCommissionReportService";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { useAsyncReportExport } from "@/composables/useAsyncReportExport";

const { t, tParams, languageStore } = useAdminI18n();

const {
  exportJobId,
  exportStatusText,
  exportBannerVisible,
  exportCancelling,
  exportModal,
  lastExportProgress,
  startOrResumeExport,
  resumeActiveExportIfAny,
  cancelActiveExport,
  onExportModalContinue,
  onExportModalCancel,
} = useAsyncReportExport({
  getActiveExport: () => ibCommissionReportService.getActiveExport(),
  enqueueExport: (params) =>
    ibCommissionReportService.enqueueExportDetail(params),
  getExportStatus: (jobId) => ibCommissionReportService.getExportStatus(jobId),
  cancelExport: (jobId) => ibCommissionReportService.cancelExport(jobId),
  downloadExport: (jobId) => ibCommissionReportService.downloadExport(jobId),
  buildFilename: () =>
    `ib_commission_detail_${new Date().toISOString().split("T")[0]}.csv`,
  t,
});

const exportProgressPercent = computed(() =>
  Math.max(0, Math.min(100, Number(lastExportProgress.value?.percent || 0))),
);

const elementPlusLocale = computed(() =>
  languageStore.currentLanguage === "zh" ? zhCn : en,
);

const authStore = useAuthStore();

// 权限检查
const hasReadonlyPermission = computed(() =>
  authStore.hasPermission("page_ibreport_readonly"),
);
const hasExportPermission = computed(() =>
  authStore.hasPermission("page_ibreport_export"),
);

// State
const activePreset = ref("month");
const startDate = ref("");
const endDate = ref("");
const searchQuery = ref("");
const statusFilter = ref("");
const sortBy = ref("commission_desc");
const expandedRows = ref([]);
const currentPage = ref(1);
const perPage = ref(10);
const totalIbCount = ref(0);
const loading = ref(false);
const loadingDetails = ref({}); // 记录每个IB的详情加载状态
const error = ref(null);

// Statistics
const statistics = ref({
  totalCommission: 0,
  totalCommissionChange: 0,
  paidCommission: 0,
  paidCommissionChange: 0,
  totalLots: 0,
  totalLotsChange: 0,
  totalTrade: 0,
  totalTradeChange: 0,
});

// IB List (从API获取)
const ibList = ref([]);
// 存储每个IB的详情数据
const ibDetails = ref({});

// Computed
// 所有过滤和排序都在后端处理，这里直接返回列表
const filteredIbList = computed(() => {
  return ibList.value;
});

const totalPages = computed(() => {
  return Math.ceil(totalIbCount.value / perPage.value);
});

const visiblePages = computed(() => {
  const pages = [];
  const total = totalPages.value;
  const current = currentPage.value;

  if (total <= 7) {
    for (let i = 1; i <= total; i++) {
      pages.push(i);
    }
  } else {
    if (current <= 3) {
      for (let i = 1; i <= 4; i++) pages.push(i);
      pages.push("...");
      pages.push(total);
    } else if (current >= total - 2) {
      pages.push(1);
      pages.push("...");
      for (let i = total - 3; i <= total; i++) pages.push(i);
    } else {
      pages.push(1);
      pages.push("...");
      for (let i = current - 1; i <= current + 1; i++) pages.push(i);
      pages.push("...");
      pages.push(total);
    }
  }

  return pages;
});

// Methods
const selectPreset = (preset) => {
  activePreset.value = preset;
  const today = new Date();
  let start, end;

  switch (preset) {
    case "today":
      start = end = new Date();
      break;
    case "month":
      end = new Date();
      start = new Date(today.getFullYear(), today.getMonth(), 1);
      break;
    case "quarter":
      end = new Date();
      const quarter = Math.floor(today.getMonth() / 3);
      start = new Date(today.getFullYear(), quarter * 3, 1);
      break;
    case "year":
      end = new Date();
      start = new Date(today.getFullYear(), 0, 1);
      break;
  }

  startDate.value = formatDateForInput(start);
  endDate.value = formatDateForInput(end);
};

const formatDateForInput = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

// 将日期转换为带时区的ISO 8601格式（用于发送到后端）
const formatDateWithTimezone = (dateString) => {
  if (!dateString) return null;
  // 将日期字符串转换为本地日期的00:00:00，然后转换为ISO格式（带时区）
  const date = new Date(dateString + "T00:00:00");
  return date.toISOString();
};

// 统计卡片：根据与上期对比的变化返回样式类（positive/negative/neutral）
const changeClass = (value) => {
  const v = Number(value);
  if (v > 0) return "positive";
  if (v < 0) return "negative";
  return "neutral";
};

// 统计卡片：箭头图标（涨/跌/平）
const changeIcon = (value) => {
  const v = Number(value);
  if (v > 0) return "fas fa-arrow-up";
  if (v < 0) return "fas fa-arrow-down";
  return "fas fa-minus";
};

// 统计卡片：变化文案。isPercent 为 true 时显示百分比（如 +12.5%、-3.2%），否则为 IBs 绝对值（如 +5 IBs）
const changeText = (value, isPercent) => {
  const v = Number(value);
  if (isPercent) {
    const sign = v > 0 ? "+" : "";
    return `${sign}${v}%`;
  }
  if (v > 0)
    return tParams("ibReport_change_ibCountPos", "+{count} IBs", { count: v });
  if (v < 0)
    return tParams("ibReport_change_ibCountNeg", "{count} IBs", { count: v });
  return t("ibReport_change_ibCountZero");
};

// Detail 表格中 ib_commission_order.status 的展示文案
const statusDisplay = (status) => {
  const s = (status || "").toLowerCase();
  const map = {
    pending: "ibReport_orderStatus_pending",
    approved: "ibReport_orderStatus_approved",
    completed: "ibReport_orderStatus_completed",
    cancelled: "ibReport_orderStatus_cancelled",
  };
  const k = map[s];
  if (k) return t(k);
  return status
    ? status.charAt(0).toUpperCase() + status.slice(1).toLowerCase()
    : "—";
};

const applyDateFilter = async () => {
  if (!startDate.value || !endDate.value) {
    alert(t("ibReport_alert_datesRequired"));
    return;
  }

  if (new Date(startDate.value) > new Date(endDate.value)) {
    alert(t("ibReport_alert_dateOrder"));
    return;
  }

  await fetchData();
};

const exportDetailReport = async () => {
  await startOrResumeExport(() => {
    const exportParams = {};
    if (startDate.value)
      exportParams.start_date = formatDateWithTimezone(startDate.value);
    if (endDate.value)
      exportParams.end_date = formatDateWithTimezone(endDate.value);
    if (statusFilter.value) exportParams.status = statusFilter.value;
    if (searchQuery.value) exportParams.search = searchQuery.value;
    if (sortBy.value) exportParams.sort = sortBy.value;
    return exportParams;
  });
};

const handleSearch = async () => {
  currentPage.value = 1;
  // 搜索走接口
  if (startDate.value && endDate.value) {
    await fetchData();
  }
};

const handleFilter = async () => {
  currentPage.value = 1;
  // 状态过滤走接口
  if (startDate.value && endDate.value) {
    await fetchData();
  }
};

const handleSort = async () => {
  currentPage.value = 1;
  // 排序走接口
  if (startDate.value && endDate.value) {
    await fetchData();
  }
};

const clearFilters = async () => {
  searchQuery.value = "";
  statusFilter.value = "";
  sortBy.value = "commission_desc";
  currentPage.value = 1;
  // 清空筛选条件后重新获取数据
  if (startDate.value && endDate.value) {
    await fetchData();
  }
};

const toggleDetails = async (id) => {
  const index = expandedRows.value.indexOf(id);
  if (index > -1) {
    expandedRows.value.splice(index, 1);
  } else {
    // 关闭其他展开的行
    expandedRows.value = [id];
    // 每次展开都重新加载详情数据
    await fetchIbDetails(id);
  }
};

// 获取IB详情
const fetchIbDetails = async (ibPartnerId) => {
  if (loadingDetails.value[ibPartnerId]) {
    return; // 正在加载中，避免重复请求
  }

  loadingDetails.value[ibPartnerId] = true;
  try {
    const params = {};
    if (startDate.value)
      params.start_date = formatDateWithTimezone(startDate.value);
    if (endDate.value) params.end_date = formatDateWithTimezone(endDate.value);

    const response = await ibCommissionReportService.getDetails(
      ibPartnerId,
      params,
    );
    const data = response.data?.data || response.data;

    if (data) {
      // 更新IB列表中的对应项
      const ibIndex = ibList.value.findIndex((ib) => ib.id === ibPartnerId);
      if (ibIndex !== -1) {
        ibList.value[ibIndex].forexCommission =
          data.statistics?.totalCommission || 0;
        ibList.value[ibIndex].cfdCommission = 0; // TODO: 从breakdown中计算
        ibList.value[ibIndex].cryptoCommission = 0; // TODO: 从breakdown中计算
        ibList.value[ibIndex].breakdown = data.breakdown || [];
      }
      ibDetails.value[ibPartnerId] = data;
    }
  } catch (err) {
    console.error("Failed to fetch IB details:", err);
    error.value = err.response?.data?.message || t("ibReport_err_fetchDetails");
  } finally {
    loadingDetails.value[ibPartnerId] = false;
  }
};

const changePage = async (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
    expandedRows.value = []; // 切换页面时关闭所有展开的行
    // 重新获取数据（分页由API处理）
    if (startDate.value && endDate.value) {
      await fetchData();
    }
  }
};

// 获取数据
const fetchData = async () => {
  loading.value = true;
  error.value = null;

  try {
    // 构建统一的过滤参数（统计和列表接口使用相同的过滤条件）
    const commonParams = {};
    if (startDate.value)
      commonParams.start_date = formatDateWithTimezone(startDate.value);
    if (endDate.value)
      commonParams.end_date = formatDateWithTimezone(endDate.value);
    if (statusFilter.value) commonParams.status = statusFilter.value;
    if (searchQuery.value) commonParams.search = searchQuery.value;

    // 获取统计信息（使用相同的过滤条件）
    const statsResponse =
      await ibCommissionReportService.getStatistics(commonParams);
    const statsData = statsResponse.data?.data || statsResponse.data;
    if (statsData) {
      statistics.value = {
        totalCommission: statsData.totalCommission || 0,
        totalCommissionChange: statsData.totalCommissionChange || 0,
        paidCommission: statsData.paidCommission || 0,
        paidCommissionChange: statsData.paidCommissionChange || 0,
        totalLots: statsData.totalLots || 0,
        totalLotsChange: statsData.totalLotsChange || 0,
        totalTrade: statsData.totalTrade || 0,
        totalTradeChange: statsData.totalTradeChange || 0,
      };
    }

    // 获取IB列表（使用相同的过滤条件，加上分页和排序）
    const listParams = {
      ...commonParams,
      page: currentPage.value,
      per_page: perPage.value,
    };
    if (sortBy.value) listParams.sort = sortBy.value;

    const listResponse = await ibCommissionReportService.getList(listParams);
    const listData = listResponse.data?.data || listResponse.data;

    if (listData) {
      if (listData.items) {
        ibList.value = listData.items.map((ib) => ({
          ...ib,
          // 确保有breakdown字段（初始为空，展开时加载）
          breakdown: ib.breakdown || [],
        }));
        totalIbCount.value = listData.pagination?.total || listData.total || 0;
      } else if (Array.isArray(listData)) {
        ibList.value = listData;
        totalIbCount.value = listData.length;
      }
    }
  } catch (err) {
    console.error("Failed to fetch IB commission report:", err);
    error.value = err.response?.data?.message || t("ibReport_err_fetchReport");
    // 如果API失败，使用空数组，避免页面崩溃
    ibList.value = [];
    totalIbCount.value = 0;
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  const today = new Date();
  const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
  startDate.value = formatDateForInput(firstDay);
  endDate.value = formatDateForInput(today);

  await fetchData();
  await resumeActiveExportIfAny();
});
</script>

<style scoped>
.ib-report-page {
  max-width: 1400px;
  margin: 0 auto;
  padding: 40px 20px;
}

/* Page Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.page-title {
  display: flex;
  flex-direction: column;
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
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
  margin-bottom: 30px;
}

.stat-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 25px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  transition: all 0.3s ease;
  border: 1px solid var(--color-border);
  border-left: 4px solid transparent;
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.stat-card.total {
  border-left-color: var(--color-brand);
}

.stat-card.paid {
  border-left-color: var(--color-success);
}

.stat-card.total-lots {
  border-left-color: var(--color-warning);
}

.stat-card.total-trade {
  border-left-color: var(--color-accent);
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

.stat-card.total .stat-icon {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.stat-card.paid .stat-icon {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.stat-card.total-lots .stat-icon {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.stat-card.total-trade .stat-icon {
  background: var(--color-brand-soft);
  color: var(--color-accent);
}

.stat-value {
  font-size: 32px;
  font-weight: 700;
  color: var(--color-ink);
  margin-bottom: 10px;
}

.stat-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 15px;
  border-top: 1px solid var(--color-border);
}

.stat-change {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 14px;
  font-weight: 600;
}

.stat-change.positive {
  color: var(--color-success);
}

.stat-change.negative {
  color: var(--color-danger);
}

.stat-change.neutral {
  color: var(--color-muted);
}

.stat-period {
  font-size: 14px;
  color: var(--color-faint);
}

/* Date Filter Section */
.date-filter-section {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 25px 30px;
  margin-bottom: 30px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow-x: auto;
}

.date-filter-container {
  display: flex;
  align-items: center;
  gap: 15px;
  flex-wrap: nowrap;
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
  flex-wrap: nowrap;
}

.preset-btn {
  padding: 8px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-size: 14px;
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
  font-size: 14px;
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

.btn-export {
  padding: 10px 20px;
  background: var(--color-surface);
  color: var(--color-brand);
  border: 2px solid var(--color-brand);
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

.btn-export:hover {
  background: var(--color-brand-solid);
  color: white;
}

/* Search & Filter Section */
.search-filter-section {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 25px 30px;
  margin-bottom: 30px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.search-container {
  display: flex;
  gap: 15px;
  align-items: flex-end;
  flex-wrap: wrap;
}

.search-field {
  flex: 1;
  min-width: 250px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.search-field label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
}

.search-input-wrapper {
  position: relative;
}

.search-input-wrapper input {
  width: 100%;
  padding: 10px 40px 10px 15px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  transition: all 0.3s ease;
}

.search-input-wrapper input:focus {
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

.filter-field {
  min-width: 200px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.filter-field label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
}

.filter-field select {
  padding: 10px 15px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  transition: all 0.3s ease;
  color: var(--color-text);
  background: var(--color-surface);
  cursor: pointer;
}

.filter-field select:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.btn-clear-filter {
  padding: 10px 20px;
  background: var(--color-surface);
  color: var(--color-muted);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-clear-filter:hover {
  border-color: var(--color-border-strong);
  background: var(--color-surface-soft);
}

/* IB Commission Table */
.ib-table-container {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.ib-table-scroll {
  width: 100%;
  overflow-x: auto;
  overflow-y: hidden;
  overscroll-behavior-inline: contain;
  -webkit-overflow-scrolling: touch;
}

.table-header {
  padding: 25px 30px;
  background: var(--color-surface-soft);
  border-bottom: 2px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.table-header h2 {
  font-size: 18px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 12px;
}

.table-header h2 i {
  color: var(--color-brand);
}

.ib-table {
  width: 100%;
  min-width: 940px;
  table-layout: fixed;
  border-collapse: collapse;
  font-family: var(--font-ui);
}

.ib-table > thead > tr > th:nth-child(1) {
  width: 28%;
}
.ib-table > thead > tr > th:nth-child(2) {
  width: 14%;
}
.ib-table > thead > tr > th:nth-child(3) {
  width: 15%;
}
.ib-table > thead > tr > th:nth-child(4) {
  width: 16%;
}
.ib-table > thead > tr > th:nth-child(5) {
  width: 14%;
}
.ib-table > thead > tr > th:nth-child(6) {
  width: 13%;
}

.ib-table > thead > tr > th,
.ib-table > tbody > tr > td {
  line-height: 1.4;
}

.ib-table thead {
  background: var(--color-surface-soft);
}

.ib-table th {
  padding: 16px 20px;
  text-align: left;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
}

.ib-table tbody tr {
  border-bottom: 1px solid var(--color-border);
  transition: all 0.2s ease;
}

.ib-table tbody tr:not(.detail-row):hover {
  background: var(--color-surface-soft);
}

.ib-table tbody tr.expanded {
  background: var(--color-brand-soft);
}

.ib-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
}

.ib-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.ib-avatar {
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

.ib-details {
  display: flex;
  flex-direction: column;
}

.ib-name {
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 3px;
}

.ib-code {
  font-size: 14px;
  color: var(--color-muted);
  font-family: var(--font-ui);
  font-variant-numeric: tabular-nums;
}

.commission-amount {
  font-weight: 700;
  font-size: 16px;
  color: var(--color-ink);
}

/* 对齐 client_frontend 风格：方角胶囊、不大写、配色稍浅 */
.status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 14px;
  font-weight: 600;
}

.status-badge.paid,
.status-badge.completed,
.status-badge.approved {
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

.status-badge.cancelled {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.btn-action {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
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

.detail-row {
  display: none;
  background: var(--color-surface-soft);
}

.detail-row.show {
  display: table-row;
}

/* Keep the nested wide table out of the six-column summary table's width calculation. */
.ib-table tbody tr.detail-row > td {
  max-width: 0;
  padding: 0;
  overflow: hidden;
}

.detail-content {
  padding: 30px;
  width: 100%;
  min-width: 0;
  max-width: 100%;
  box-sizing: border-box;
}

/* 内层宽表自己横滚 */
.detail-content .detail-table-scroll--wide {
  max-width: 100%;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 25px;
  margin-bottom: 25px;
}

.detail-item {
  background: var(--color-surface);
  padding: 20px;
  border-radius: var(--radius-md);
  border-left: 3px solid var(--color-brand);
}

.detail-label {
  font-size: 14px;
  color: var(--color-muted);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 8px;
  display: block;
}

.detail-value {
  font-size: 18px;
  font-weight: 700;
  color: var(--color-ink);
}

/* 对齐 client_frontend CommissionReport.vue 的 detail-table 视觉风格 */
.detail-table {
  width: 100%;
  min-width: max-content;
  border-collapse: collapse;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  overflow: hidden;
}

.detail-table-scroll--wide {
  width: 100%;
  min-width: 0;
  max-width: 100%;
  overflow-x: auto;
  overflow-y: hidden;
  overscroll-behavior-inline: contain;
  -webkit-overflow-scrolling: touch;
}
.detail-table--wide {
  width: max-content;
  min-width: 1800px;
  table-layout: auto;
}

.detail-table thead {
  background: var(--color-surface-soft);
}

.detail-table th {
  padding: 10px 12px;
  text-align: left;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  border-bottom: 1px solid var(--color-border);
  white-space: nowrap;
}

.detail-table td {
  padding: 10px 12px;
  font-size: 14px;
  color: var(--color-ink);
  border-bottom: 1px solid var(--color-border);
  white-space: nowrap;
}

.detail-table tbody tr:last-child td {
  border-bottom: none;
}

.detail-table tbody tr:hover {
  background: var(--color-surface-soft);
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
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  color: var(--color-text);
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
  padding: 8px 4px;
  color: var(--color-faint);
}

/* Loading and Error States */
.loading-container {
  padding: 40px;
  text-align: center;
  color: var(--color-muted);
  font-size: 16px;
}

.loading-container i {
  margin-right: 10px;
  font-size: 20px;
}

.error-message {
  background: var(--color-danger-soft);
  color: var(--color-danger);
  padding: 15px 20px;
  border-radius: var(--radius-md);
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.error-message i {
  font-size: 18px;
}

.loading-details {
  padding: 20px;
  text-align: center;
  color: var(--color-muted);
}

.loading-details i {
  margin-right: 10px;
}

.no-data,
.no-breakdown {
  padding: 40px;
  text-align: center;
  color: var(--color-faint);
}

.no-data p,
.no-breakdown p {
  margin: 0;
  font-size: 14px;
}

/* Responsive */
@media (min-width: 769px) and (max-width: 1024px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .detail-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .ib-report-page {
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

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .date-filter-container {
    flex-direction: column;
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .date-filter-presets {
    flex-wrap: wrap;
  }

  .date-input-wrapper {
    width: 100%;
  }

  .search-container {
    flex-direction: column;
  }

  .search-field,
  .filter-field {
    width: 100%;
  }

  .detail-grid {
    grid-template-columns: 1fr;
  }

  .ib-table {
    font-size: 14px;
  }

  .ib-table th,
  .ib-table td {
    padding: 12px 10px;
  }

  .pagination {
    flex-direction: column;
    gap: 15px;
  }
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
</style>
