<template>
  <div class="withdrawal-management-page ui-page workspace-list-page">
    <!-- Page Header -->
    <div class="page-header ui-page-header">
      <div class="page-title ui-page-heading">
        <h1 class="ui-page-title">{{ t("page_withdrawalMgmt_title") }}</h1>
        <p class="ui-page-description">{{ t("page_withdrawalMgmt_sub") }}</p>
      </div>
      <div class="page-actions ui-page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- Statistics Header -->
    <div class="stats-header ui-surface ui-summary-band">
      <div>
        <h2>{{ t("withdrawalMgmt_stats_heading") }}</h2>
        <p>{{ t("withdrawalMgmt_stats_sub") }}</p>
      </div>
      <div class="page-stats" v-if="statistics">
        <div class="stat-badge">
          <i class="fas fa-dollar-sign"></i>
          <span>{{
            tParams("withdrawalMgmt_stat_today", "{amount} Today", {
              amount: formatCurrency(statistics.todayAmount || 0),
            })
          }}</span>
        </div>
        <button
          type="button"
          class="stat-badge pending clickable"
          :class="{ active: statusFilter.includes('pending') }"
          @click="toggleStatus('pending')"
        >
          <i class="fas fa-clock"></i>
          <span>{{
            tParams("withdrawalMgmt_stat_pending", "{n} Pending", {
              n: formatNumber(statistics.pendingCount || 0),
            })
          }}</span>
        </button>
        <button
          type="button"
          class="stat-badge processing clickable"
          :class="{ active: statusFilter.includes('processing') }"
          @click="toggleStatus('processing')"
        >
          <i class="fas fa-spinner"></i>
          <span>{{
            tParams("withdrawalMgmt_stat_processing", "{n} Processing", {
              n: formatNumber(statistics.processingCount || 0),
            })
          }}</span>
        </button>
        <button
          type="button"
          class="stat-badge success clickable"
          :class="{ active: statusFilter.includes('completed') }"
          @click="toggleStatus('completed')"
        >
          <i class="fas fa-check-circle"></i>
          <span>{{
            tParams("withdrawalMgmt_stat_completed", "{n} Completed", {
              n: formatNumber(statistics.completedCount || 0),
            })
          }}</span>
        </button>
        <button
          type="button"
          class="stat-badge rejected clickable"
          :class="{ active: statusFilter.includes('rejected') }"
          @click="toggleStatus('rejected')"
        >
          <i class="fas fa-times-circle"></i>
          <span>{{
            tParams("withdrawalMgmt_stat_rejected", "{n} Rejected", {
              n: formatNumber(statistics.rejectedCount || 0),
            })
          }}</span>
        </button>
      </div>
    </div>

    <!-- Search and Filter Section -->
    <div class="search-filter-section ui-surface">
      <div class="search-filter-container">
        <!-- Search Input -->
        <div class="search-input-wrapper">
          <i class="fas fa-search search-icon"></i>
          <input
            type="text"
            v-model="searchQuery"
            :placeholder="t('withdrawalMgmt_searchPlaceholder')"
            @input="handleSearch"
          />
          <button
            type="button"
            :class="['fas fa-times clear-search', { show: searchQuery }]"
            aria-label="Clear search"
            @click="clearSearch"
          ></button>
        </div>

        <!-- Quick Search Tags -->
        <div class="tags-section">
          <div class="tags-header">
            <h4>{{ t("leads_quickSearchTags") }}</h4>
            <button
              type="button"
              v-if="hasTagsPermission"
              class="btn-add-tag"
              @click="showSearchTagModal = true"
            >
              <i class="fas fa-plus"></i> {{ t("leads_btnSearchTag") }}
            </button>
          </div>
          <div class="tags-container">
            <div
              v-for="tag in searchTags"
              :key="tag.id"
              class="search-tag-control"
            >
              <button
                type="button"
                :class="['search-tag', { active: activeSearchTag === tag.id }]"
                @click="applySearchTag(tag)"
              >
                <i class="fas fa-tag"></i> {{ tag.tagName }}
              </button>
              <button
                v-if="hasTagsPermission"
                type="button"
                :class="['tag-remove', { active: activeSearchTag === tag.id }]"
                :aria-label="
                  tParams('leads_removeTagTitle', 'Remove {name} tag', {
                    name: tag.tagName,
                  })
                "
                @click="removeSearchTag(tag.id)"
              >
                ×
              </button>
            </div>
            <span v-if="searchTags.length === 0" class="empty-tags-message">
              {{ t("leads_noTagsYet") }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Withdrawal Table -->
    <div class="withdrawal-table-container ui-data-region">
      <div class="table-header ui-toolbar">
        <div class="table-header-left">
          <h2>{{ t("withdrawalMgmt_allWithdrawals") }}</h2>
          <div
            :class="[
              'bulk-actions',
              { show: selectedWithdrawalIds.length > 0 },
            ]"
          >
            <span class="bulk-actions-label">{{
              t("leads_bulkSelected")
            }}</span>
            <span class="bulk-actions-count">{{
              selectedWithdrawalIds.length
            }}</span>
            <button
              type="button"
              v-if="hasApprovePermission"
              class="btn-bulk btn-bulk-approve"
              @click="showBulkApproveModal = true"
            >
              <i class="fas fa-check-double"></i>
              {{ t("withdrawalMgmt_bulkApproveAll") }}
            </button>
            <button
              type="button"
              v-if="hasTagsPermission"
              class="btn-bulk btn-bulk-tag"
              @click="showBulkTagModal = true"
            >
              <i class="fas fa-tag"></i> {{ t("leads_bulkAddTag") }}
            </button>
            <div v-if="hasExportPermission" class="btn-bulk-export">
              <button
                type="button"
                class="btn-bulk"
                @click="showExportDropdown = !showExportDropdown"
              >
                <i class="fas fa-download"></i> {{ t("leads_bulkExport") }}
              </button>
              <div :class="['export-dropdown', { show: showExportDropdown }]">
                <button
                  type="button"
                  class="export-option csv"
                  @click.stop="handleExport('csv')"
                >
                  <i class="fas fa-file-csv"></i>
                  <span>{{ t("depositMgmt_exportCsv") }}</span>
                </button>
                <button
                  type="button"
                  class="export-option excel"
                  @click.stop="handleExport('excel')"
                >
                  <i class="fas fa-file-excel"></i>
                  <span>{{ t("depositMgmt_exportExcel") }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>
        <div class="table-header-right">
          <div class="rows-selector">
            <label>{{ t("withdrawalMgmt_showLabel") }}</label>
            <select v-model="perPage" @change="handlePerPageChange">
              <option :value="10">{{ t("withdrawalMgmt_rows_10") }}</option>
              <option :value="20">{{ t("withdrawalMgmt_rows_20") }}</option>
              <option :value="50">{{ t("withdrawalMgmt_rows_50") }}</option>
              <option :value="100">{{ t("withdrawalMgmt_rows_100") }}</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-container">
        <i class="fas fa-spinner fa-spin"></i>
        <p>{{ t("withdrawalMgmt_loading") }}</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="error-container">
        <i class="fas fa-exclamation-circle"></i>
        <p>{{ error }}</p>
        <button type="button" class="btn btn-primary" @click="loadWithdrawals">
          <i class="fas fa-redo"></i> {{ t("withdrawalMgmt_retry") }}
        </button>
      </div>

      <!-- Table -->
      <div v-else class="ui-table-scroll">
        <table class="withdrawal-table">
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
              <th>{{ t("withdrawalMgmt_col_client") }}</th>
              <th>{{ t("withdrawalMgmt_col_amount") }}</th>
              <th>{{ t("withdrawalMgmt_col_sourceAccount") }}</th>
              <th>{{ t("withdrawalMgmt_col_paymentMethod") }}</th>
              <th>{{ t("withdrawalMgmt_col_requestTime") }}</th>
              <th>{{ t("withdrawalMgmt_col_approvalTime") }}</th>
              <th>{{ t("withdrawalMgmt_col_status") }}</th>
              <th>{{ t("withdrawalMgmt_col_tags") }}</th>
              <th>{{ t("withdrawalMgmt_col_action") }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="withdrawals.length === 0">
              <tr>
                <td colspan="10" class="empty-state">
                  <i class="fas fa-inbox"></i>
                  <p>{{ t("withdrawalMgmt_empty") }}</p>
                </td>
              </tr>
            </template>
            <template
              v-else
              v-for="withdrawal in withdrawals"
              :key="withdrawal.id"
            >
              <tr
                :data-withdrawal-id="withdrawal.id"
                :class="{ expanded: expandedWithdrawalId === withdrawal.id }"
              >
                <td class="checkbox-col">
                  <label class="custom-checkbox">
                    <input
                      type="checkbox"
                      :value="withdrawal.id"
                      v-model="selectedWithdrawalIds"
                    />
                    <span class="checkbox-checkmark"></span>
                  </label>
                </td>
                <td>
                  <div class="client-info">
                    <div class="client-avatar">
                      {{
                        getInitials(withdrawal.firstName, withdrawal.lastName)
                      }}
                    </div>
                    <div class="client-details">
                      <div class="client-name">
                        {{ withdrawal.firstName }} {{ withdrawal.lastName }}
                      </div>
                      <div class="client-id">
                        {{ t("depositMgmt_clientIdPrefix") }}
                        {{ withdrawal.userId }}
                      </div>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="amount-display">
                    {{ formatCurrency(withdrawal.amount) }}
                  </div>
                  <div class="amount-crypto" v-if="withdrawal.amountCrypto">
                    {{ withdrawal.amountCrypto }} {{ withdrawal.shortCode }}
                  </div>
                </td>
                <td>
                  <div class="source-account">
                    <div
                      v-if="hasTradingAccountSource(withdrawal)"
                      class="account-stack"
                    >
                      <span
                        :class="[
                          'platform-chip',
                          `platform-${getWithdrawalPlatformMeta(withdrawal).key}`,
                        ]"
                      >
                        <i
                          :class="getWithdrawalPlatformMeta(withdrawal).icon"
                        ></i>
                        {{ getWithdrawalPlatformMeta(withdrawal).label }}
                      </span>
                      <span class="account-text">{{
                        formatWithdrawalSourceAccount(withdrawal)
                      }}</span>
                    </div>
                    <div v-else class="account-stack">
                      <span class="platform-chip platform-wallet">
                        <i class="fas fa-wallet"></i>
                        {{ t("depositMgmt_wallet") }}
                      </span>
                    </div>
                  </div>
                </td>
                <td>
                  <span
                    :class="[
                      'payment-method-badge',
                      withdrawal.methodType || 'fiat',
                    ]"
                  >
                    <i
                      :class="
                        withdrawal.gatewayIconClass || 'fas fa-university'
                      "
                    ></i>
                    {{
                      withdrawal.gatewayName ||
                      t("withdrawalMgmt_bankTransferFallback")
                    }}
                  </span>
                </td>
                <td>
                  <div>{{ formatDate(withdrawal.requestedAt) }}</div>
                  <small class="time-small">{{
                    formatTime(withdrawal.requestedAt)
                  }}</small>
                </td>
                <td>
                  <div v-if="withdrawal.approvedAt">
                    {{ formatDate(withdrawal.approvedAt) }}
                    <small class="time-small">{{
                      formatTime(withdrawal.approvedAt)
                    }}</small>
                  </div>
                  <div v-else-if="withdrawal.rejectedAt" class="rejected-time">
                    {{ formatDate(withdrawal.rejectedAt) }}
                    <small>{{ t("withdrawalMgmt_approvalRejected") }}</small>
                  </div>
                  <span v-else class="pending-text">{{
                    t("withdrawalMgmt_approvalPending")
                  }}</span>
                </td>
                <td>
                  <span
                    :class="['status-badge', 'ui-status', withdrawal.status]"
                  >
                    {{ getStatusLabel(withdrawal.status) }}
                  </span>
                </td>
                <td>
                  <div class="withdrawal-tags">
                    <span
                      v-for="tag in withdrawal.tags"
                      :key="tag.id"
                      class="withdrawal-tag"
                    >
                      <i class="fas fa-tag"></i> {{ tag.tagName }}
                      <button
                        v-if="hasTagsPermission"
                        type="button"
                        class="withdrawal-tag-remove"
                        :aria-label="
                          tParams('leads_removeTagTitle', 'Remove {name} tag', {
                            name: tag.tagName,
                          })
                        "
                        @click="removeWithdrawalTag(withdrawal.id, tag.id)"
                      >
                        ×
                      </button>
                    </span>
                    <span
                      v-if="!withdrawal.tags || withdrawal.tags.length === 0"
                      class="no-tags"
                    >
                      {{ t("withdrawalMgmt_noTags") }}
                    </span>
                  </div>
                </td>
                <td>
                  <button
                    type="button"
                    class="btn-action btn-detail"
                    @click="toggleDetail(withdrawal.id)"
                  >
                    <i
                      :class="
                        expandedWithdrawalId === withdrawal.id
                          ? 'fas fa-chevron-up'
                          : 'fas fa-chevron-down'
                      "
                    ></i>
                    {{
                      expandedWithdrawalId === withdrawal.id
                        ? t("withdrawalMgmt_btnHide")
                        : t("withdrawalMgmt_btnDetails")
                    }}
                  </button>
                </td>
              </tr>

              <!-- Detail Row Component -->
              <WithdrawalDetailRow
                :withdrawal-id="withdrawal.id"
                :withdrawal="withdrawal"
                :is-expanded="expandedWithdrawalId === withdrawal.id"
                :has-approve-permission="hasApprovePermission"
                :has-reject-permission="hasRejectPermission"
                :has-need-more-documents-permission="
                  hasNeedMoreDocumentsPermission
                "
                :has-contact-client-permission="hasContactClientPermission"
                :approving="processingApprove"
                @approve="handleApproveWithdrawal"
                @reject="handleRejectWithdrawal"
                @request-documents="handleRequestDocuments"
                @refresh="loadWithdrawals"
              />
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination" v-if="totalPages > 1">
        <button
          type="button"
          class="pagination-btn"
          :disabled="currentPage === 1"
          @click="changePage(currentPage - 1)"
        >
          <i class="fas fa-chevron-left"></i> {{ t("withdrawalMgmt_previous") }}
        </button>

        <div class="pagination-pages">
          <button
            type="button"
            v-for="(page, index) in pageNumbers"
            :key="index"
            :class="[
              'pagination-page-btn',
              { active: page === currentPage, ellipsis: page === '...' },
            ]"
            :disabled="page === '...'"
            @click="page !== '...' && changePage(page)"
          >
            {{ page }}
          </button>
        </div>

        <button
          type="button"
          class="pagination-btn"
          :disabled="currentPage === totalPages"
          @click="changePage(currentPage + 1)"
        >
          {{ t("withdrawalMgmt_next") }} <i class="fas fa-chevron-right"></i>
        </button>

        <span class="pagination-info">
          {{
            tParams(
              "withdrawalMgmt_pagination_range",
              "Showing {from} - {to} of {total}",
              {
                from: formatNumber((currentPage - 1) * perPage + 1),
                to: formatNumber(Math.min(currentPage * perPage, total)),
                total: formatNumber(total),
              },
            )
          }}
        </span>
      </div>
    </div>

    <!-- Modals -->
    <BulkApproveModal
      v-model="showBulkApproveModal"
      :selected-deposits="selectedWithdrawalsData"
      :processing="processingBulkApprove"
      :title-content="t('withdrawalMgmt_bulkModalEntity')"
      @confirm="handleBulkApprove"
    />

    <BulkTagModal
      v-model="showBulkTagModal"
      :selected-deposits="selectedWithdrawalsData"
      :processing="processingBulkTag"
      :title-content="t('withdrawalMgmt_bulkModalEntity')"
      @confirm="handleBulkAddTag"
    />

    <SearchTagModal
      v-model="showSearchTagModal"
      :processing="processingSearchTag"
      @confirm="handleCreateSearchTag"
    />

    <RejectWithdrawalModal
      v-model="showRejectModal"
      :withdrawal-id="selectedWithdrawalForAction"
      :processing="processingReject"
      :entity-label="t('withdrawalMgmt_entityLabel')"
      rejection-scope="withdrawal"
      @confirm="handleRejectConfirm"
    />

    <NeedDocumentsModal
      v-model="showNeedDocsModal"
      :withdrawal-id="selectedWithdrawalForAction"
      :processing="processingNeedDocs"
      @confirm="handleRequestDocsConfirm"
    />
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { ref, computed, onMounted, watch } from "vue";
import { useAuthStore } from "@/stores/auth";
import WithdrawalDetailRow from "../components/withdrawals/WithdrawalDetailRow.vue";
import BulkApproveModal from "../components/deposits/BulkApproveModal.vue"; // 复用
import BulkTagModal from "../components/deposits/BulkTagModal.vue"; // 复用
import SearchTagModal from "../components/deposits/SearchTagModal.vue"; // 复用
import RejectWithdrawalModal from "../components/withdrawals/RejectWithdrawalModal.vue";
import NeedDocumentsModal from "../components/withdrawals/NeedDocumentsModal.vue";
import withdrawalApi from "../services/withdrawalApi";
import depositApi from "../services/depositApi"; // 用于搜索标签API
import { recordOperationLog } from "@/services/operationLogReportApi";
import {
  buildExportLogPayload,
  getSubModuleKey,
} from "@/config/operationLogPages";
import { formatCurrency, formatNumber } from "../utils/helpers";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";

const { t, tParams, languageStore } = useAdminI18n();

const WITHDRAWALS_LOG_SUB_MODULE = getSubModuleKey("page_withdrawals");

const authStore = useAuthStore();

// Permission checks for Withdraw page
const hasTagsPermission = computed(() =>
  authStore.hasPermission("page_withdraw_tags"),
);
const hasApprovePermission = computed(() =>
  authStore.hasPermission("page_withdraw_approve"),
);
const hasRejectPermission = computed(() =>
  authStore.hasPermission("page_withdraw_reject"),
);
const hasNeedMoreDocumentsPermission = computed(() =>
  authStore.hasPermission("page_withdraw_needmoredocuments"),
);
const hasExportPermission = computed(() =>
  authStore.hasPermission("page_withdraw_export"),
);
const hasContactClientPermission = computed(() =>
  authStore.hasPermission("page_withdraw_contactclient"),
);

// State
const loading = ref(true);
const error = ref(null);
const withdrawals = ref([]);
const statistics = ref(null);
const searchTags = ref([]);

// Pagination
const currentPage = ref(1);
const perPage = ref(10);
const total = ref(0);
const totalPages = ref(0);

// 计算显示的页码数组
const pageNumbers = computed(() => {
  const pages = [];
  const current = currentPage.value;
  const total = totalPages.value || 0;

  if (total <= 7) {
    // 如果总页数少于等于7页，显示所有页码
    for (let i = 1; i <= total; i++) {
      pages.push(i);
    }
  } else {
    // 总是显示第一页
    pages.push(1);

    if (current <= 4) {
      // 当前页在前4页，显示1-5和最后一页
      for (let i = 2; i <= 5; i++) {
        pages.push(i);
      }
      pages.push("...");
      pages.push(total);
    } else if (current >= total - 3) {
      // 当前页在后4页，显示第一页和最后5页
      pages.push("...");
      for (let i = total - 4; i <= total; i++) {
        pages.push(i);
      }
    } else {
      // 当前页在中间，显示第一页、当前页前后各2页、最后一页
      pages.push("...");
      for (let i = current - 2; i <= current + 2; i++) {
        pages.push(i);
      }
      pages.push("...");
      pages.push(total);
    }
  }

  return pages;
});

// Search and Filter
const searchQuery = ref("");
const activeSearchTag = ref(null);
const statusFilter = ref([]);

// Selection
const selectedWithdrawalIds = ref([]);
const expandedWithdrawalId = ref(null);
const selectedWithdrawalForAction = ref(null);

// Modals
const showBulkApproveModal = ref(false);
const showBulkTagModal = ref(false);
const showSearchTagModal = ref(false);
const showRejectModal = ref(false);
const showNeedDocsModal = ref(false);
const showExportDropdown = ref(false);

// Processing states
const processingBulkApprove = ref(false);
const processingBulkTag = ref(false);
const processingSearchTag = ref(false);
const processingReject = ref(false);
const processingNeedDocs = ref(false);
const processingApprove = ref(false);

// Computed
const isAllSelected = computed(() => {
  return (
    withdrawals.value.length > 0 &&
    selectedWithdrawalIds.value.length === withdrawals.value.length
  );
});

const isIndeterminate = computed(() => {
  return (
    selectedWithdrawalIds.value.length > 0 &&
    selectedWithdrawalIds.value.length < withdrawals.value.length
  );
});

const selectedWithdrawalsData = computed(() => {
  return withdrawals.value
    .filter((w) => selectedWithdrawalIds.value.includes(w.id))
    .map((w) => ({
      id: w.id,
      transactionId: w.transactionId,
      clientName: `${w.firstName} ${w.lastName}`,
      amount: w.amount,
    }));
});

// 加载提款列表
const loadWithdrawals = async () => {
  loading.value = true;
  error.value = null;

  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value,
    };

    if (searchQuery.value) {
      params.search = searchQuery.value;
    }

    if (statusFilter.value.length) {
      params.status = statusFilter.value.join(",");
    }

    const response = await withdrawalApi.getWithdrawals(params);

    if (response.success) {
      withdrawals.value = response.data.items || response.data || [];
      total.value = response.data.pagination.total || 0;
      totalPages.value = response.data.pagination.total_pages || 0;
      currentPage.value = response.data.pagination.page || 1;
      perPage.value = response.data.pagination.per_page || 10;
    } else {
      const raw = response.message || t("withdrawalMgmt_err_loadFailed");
      error.value = translateApiErrorMessage(response.errorCode, raw);
    }
  } catch (err) {
    console.error("Failed to load withdrawals:", err);
    const data = err?.response?.data ?? err;
    const rawMsg =
      data?.message || err?.message || t("withdrawalMgmt_err_loadFailed");
    error.value = translateApiErrorMessage(data?.errorCode, rawMsg);
  } finally {
    loading.value = false;
  }
};

// 加载统计数据
const loadStatistics = async () => {
  try {
    const response = await withdrawalApi.getWithdrawalStatistics();
    if (response.success) {
      statistics.value = response.data;
    }
  } catch (err) {
    console.error("Failed to load statistics:", err);
  }
};

// 加载搜索标签
const loadSearchTags = async () => {
  try {
    const response = await depositApi.getSearchTags("withdrawal");
    if (response.success) {
      searchTags.value = response.data || [];
    }
  } catch (err) {
    console.error("Failed to load search tags:", err);
  }
};

// 搜索
const handleSearch = () => {
  currentPage.value = 1;
  loadWithdrawals();
};

// 顶部状态徽章多选筛选：点击切换某状态，回到第 1 页重新加载
const toggleStatus = (status) => {
  const idx = statusFilter.value.indexOf(status);
  if (idx === -1) {
    statusFilter.value.push(status);
  } else {
    statusFilter.value.splice(idx, 1);
  }
  currentPage.value = 1;
  loadWithdrawals();
};

// 清除搜索
const clearSearch = () => {
  searchQuery.value = "";
  activeSearchTag.value = null;
  loadWithdrawals();
};

// 应用搜索标签
const applySearchTag = (tag) => {
  if (activeSearchTag.value === tag.id) {
    activeSearchTag.value = null;
    searchQuery.value = "";
  } else {
    activeSearchTag.value = tag.id;
    searchQuery.value = tag.searchKeywords.split(",")[0].trim();
  }
  handleSearch();
};

// 移除搜索标签
const removeSearchTag = async (tagId) => {
  if (!confirm(t("withdrawalMgmt_confirm_removeSearchTag"))) return;

  try {
    const response = await depositApi.deleteSearchTag(tagId, {
      logSubModuleKey: WITHDRAWALS_LOG_SUB_MODULE,
    });
    if (response.success) {
      await loadSearchTags();
      if (activeSearchTag.value === tagId) {
        clearSearch();
      }
    }
  } catch (err) {
    const data = err?.response?.data ?? err;
    const rawMsg = data?.message || err?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams(
        "withdrawalMgmt_alert_removeSearchTagFailed",
        "Failed to remove tag: {msg}",
        { msg },
      ),
    );
  }
};

// 创建搜索标签
const handleCreateSearchTag = async (data) => {
  processingSearchTag.value = true;
  try {
    // 修改为withdrawal类型
    const tagData = {
      ...data,
      transactionType: "withdrawal",
      logSubModuleKey: WITHDRAWALS_LOG_SUB_MODULE,
    };
    const response = await depositApi.createSearchTag(tagData);
    if (response.success) {
      await loadSearchTags();
      showSearchTagModal.value = false;
      alert(t("withdrawalMgmt_alert_searchTagCreated"));
    }
  } catch (err) {
    const data = err?.response?.data ?? err;
    const rawMsg = data?.message || err?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams(
        "withdrawalMgmt_alert_createTagFailed",
        "Failed to create tag: {msg}",
        { msg },
      ),
    );
  } finally {
    processingSearchTag.value = false;
  }
};

// 切换详情
const toggleDetail = (withdrawalId) => {
  if (expandedWithdrawalId.value === withdrawalId) {
    expandedWithdrawalId.value = null;
  } else {
    expandedWithdrawalId.value = withdrawalId;
  }
};

// 切换全选
const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedWithdrawalIds.value = [];
  } else {
    selectedWithdrawalIds.value = withdrawals.value.map((w) => w.id);
  }
};

// 批准单个提款
const handleApproveWithdrawal = async (withdrawalId) => {
  if (processingApprove.value) return; // 审批进行中，避免重复提交
  if (!confirm(t("withdrawalMgmt_confirm_approve"))) return;

  processingApprove.value = true;
  try {
    const detailResponse = await withdrawalApi.getWithdrawal(withdrawalId);
    const withdrawalDetails = detailResponse?.data || {};
    const gatewayName = String(withdrawalDetails.gatewayName || "")
      .trim()
      .toLowerCase();
    const currencyCode = String(withdrawalDetails.currencyCode || "")
      .trim()
      .toUpperCase();
    const isIBeePay = gatewayName === "ibeepay";

    if (isIBeePay && currencyCode !== "KRW") {
      alert(
        `iBeePay withdrawals must use KRW. Current currency: ${currencyCode || "N/A"}`,
      );
      return;
    }

    if (
      isIBeePay &&
      (withdrawalDetails.quotedAmount === undefined ||
        withdrawalDetails.quotedAmount === null ||
        withdrawalDetails.quotedAmount === "")
    ) {
      alert("iBeePay withdrawals require quotedAmount before approval.");
      return;
    }

    const approvePayload = isIBeePay
      ? {
          currencyCode,
          quotedAmount: withdrawalDetails.quotedAmount,
        }
      : {};

    const response = await withdrawalApi.approveWithdrawal(
      withdrawalId,
      approvePayload,
    );
    if (response.success) {
      alert(t("withdrawalMgmt_alert_approveOk"));
      await loadWithdrawals();
      await loadStatistics();
      expandedWithdrawalId.value = null;
    }
  } catch (err) {
    const data = err?.response?.data ?? err;
    const rawMsg = data?.message || err?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams(
        "withdrawalMgmt_alert_approveFailed",
        "Failed to approve withdrawal: {msg}",
        { msg },
      ),
    );
  } finally {
    processingApprove.value = false;
  }
};

// 拒绝提款
const handleRejectWithdrawal = (withdrawalId) => {
  selectedWithdrawalForAction.value = withdrawalId;
  showRejectModal.value = true;
};

// 确认拒绝
const handleRejectConfirm = async (data) => {
  processingReject.value = true;
  try {
    const response = await withdrawalApi.rejectWithdrawal(
      selectedWithdrawalForAction.value,
      data,
    );
    if (response.success) {
      alert(t("withdrawalMgmt_alert_rejectOk"));
      showRejectModal.value = false;
      selectedWithdrawalForAction.value = null;
      expandedWithdrawalId.value = null;
      await loadWithdrawals();
      await loadStatistics();
    }
  } catch (err) {
    const data = err?.response?.data ?? err;
    const rawMsg = data?.message || err?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams(
        "withdrawalMgmt_alert_rejectFailed",
        "Failed to reject withdrawal: {msg}",
        { msg },
      ),
    );
  } finally {
    processingReject.value = false;
  }
};

// 请求文档
const handleRequestDocuments = (withdrawalId) => {
  selectedWithdrawalForAction.value = withdrawalId;
  showNeedDocsModal.value = true;
};

// 确认请求文档
const handleRequestDocsConfirm = async (data) => {
  processingNeedDocs.value = true;
  try {
    const response = await withdrawalApi.requestDocuments(
      selectedWithdrawalForAction.value,
      data,
    );
    if (response.success) {
      alert(t("withdrawalMgmt_alert_docsRequestOk"));
      showNeedDocsModal.value = false;
      selectedWithdrawalForAction.value = null;
      expandedWithdrawalId.value = null;
      await loadWithdrawals();
    }
  } catch (err) {
    const data = err?.response?.data ?? err;
    const rawMsg = data?.message || err?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams(
        "withdrawalMgmt_alert_docsRequestFailed",
        "Failed to send document request: {msg}",
        { msg },
      ),
    );
  } finally {
    processingNeedDocs.value = false;
  }
};

// 批量批准
const handleBulkApprove = async (data) => {
  processingBulkApprove.value = true;
  try {
    // 共享的 BulkApproveModal 统一 emit depositIds，出金后端要求字段为 withdrawalIds，这里做映射
    const payload = {
      withdrawalIds: data.depositIds,
      adminNotes: data.adminNotes,
    };
    const response = await withdrawalApi.bulkApproveWithdrawals(payload);
    if (response.success) {
      const summary = response.data.summary;
      alert(
        tParams(
          "withdrawalMgmt_alert_bulkApprove",
          "Bulk approval completed. Success: {success}, Failed: {failed}, Total: {total}",
          {
            success: String(summary.success),
            failed: String(summary.failed),
            total: String(summary.total),
          },
        ),
      );

      showBulkApproveModal.value = false;
      selectedWithdrawalIds.value = [];
      await loadWithdrawals();
      await loadStatistics();
    }
  } catch (err) {
    const data = err?.response?.data ?? err;
    const rawMsg = data?.message || err?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams(
        "withdrawalMgmt_alert_bulkApproveFailed",
        "Failed to approve withdrawals: {msg}",
        { msg },
      ),
    );
  } finally {
    processingBulkApprove.value = false;
  }
};

// 批量添加标签
const handleBulkAddTag = async (data) => {
  processingBulkTag.value = true;
  try {
    // 转换为withdrawal API格式
    const requestData = {
      withdrawalIds: data.depositIds, // 复用的组件用的是depositIds
      tagName: data.tagName,
    };
    const response = await withdrawalApi.bulkAddTags(requestData);
    if (response.success) {
      alert(
        tParams(
          "withdrawalMgmt_alert_bulkTagOk",
          'Tag "{tag}" added to {n} withdrawal(s)!',
          {
            tag: data.tagName,
            n: String(requestData.withdrawalIds.length),
          },
        ),
      );

      showBulkTagModal.value = false;
      selectedWithdrawalIds.value = [];
      await loadWithdrawals();
    }
  } catch (err) {
    const data = err?.response?.data ?? err;
    const rawMsg = data?.message || err?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams(
        "withdrawalMgmt_alert_bulkTagFailed",
        "Failed to add tags: {msg}",
        { msg },
      ),
    );
  } finally {
    processingBulkTag.value = false;
  }
};

// 移除提款标签
const removeWithdrawalTag = async (withdrawalId, tagId) => {
  if (!confirm(t("withdrawalMgmt_confirm_removeWithdrawalTag"))) return;

  try {
    const response = await withdrawalApi.removeTagFromWithdrawal(
      withdrawalId,
      tagId,
    );
    if (response.success) {
      await loadWithdrawals();
    }
  } catch (err) {
    const data = err?.response?.data ?? err;
    const rawMsg = data?.message || err?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams(
        "withdrawalMgmt_alert_removeWithdrawalTagFailed",
        "Failed to remove tag: {msg}",
        { msg },
      ),
    );
  }
};

// 导出
const handleExport = async (format) => {
  showExportDropdown.value = false;

  if (selectedWithdrawalIds.value.length === 0) {
    alert(t("withdrawalMgmt_alert_exportNeedSelection"));
    return;
  }

  try {
    const response = await withdrawalApi.exportWithdrawals({
      withdrawalIds: selectedWithdrawalIds.value,
      format,
    });

    if (response.success) {
      const withdrawals = response.data.withdrawals || [];

      if (format === "csv") {
        exportAsCSV(withdrawals);
      } else if (format === "excel") {
        exportAsExcel(withdrawals);
      }

      alert(
        tParams(
          "withdrawalMgmt_alert_exportOk",
          "Successfully exported {n} withdrawal(s)!",
          { n: String(withdrawals.length) },
        ),
      );

      const fmtLabel = format.toUpperCase();
      recordOperationLog(
        buildExportLogPayload("page_withdrawals", {
          detailZh: `导出 ${withdrawals.length} 笔出金（${fmtLabel}）`,
          detailEn: `Exported ${withdrawals.length} withdrawal(s) (${fmtLabel})`,
        }),
      ).catch(() => {});
    }
  } catch (err) {
    const data = err?.response?.data ?? err;
    const rawMsg = data?.message || err?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams("withdrawalMgmt_alert_exportFailed", "Failed to export: {msg}", {
        msg,
      }),
    );
  }
};

// CSV导出
const exportAsCSV = (withdrawals) => {
  let csv =
    "Transaction ID,Client Name,Email,Amount,Payment Method,Status,Request Time,Approval Time,Rejection Time\n";

  withdrawals.forEach((w) => {
    csv += `"${w.transactionId}","${w.firstName} ${w.lastName}","${w.email}",`;
    csv += `"${w.amount}","${w.gatewayName || "Bank Transfer"}","${w.status}",`;
    csv += `"${w.requestedAt}","${w.approvedAt || "N/A"}","${w.rejectedAt || "N/A"}"\n`;
  });

  const blob = new Blob([csv], { type: "text/csv" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = `withdrawals_${Date.now()}.csv`;
  link.click();
};

// Excel导出
const exportAsExcel = (withdrawals) => {
  exportAsCSV(withdrawals);
};

// 切换每页显示行数
const handlePerPageChange = () => {
  currentPage.value = 1;
  loadWithdrawals();
};

// 切换页码
const changePage = (page) => {
  currentPage.value = page;
  loadWithdrawals();
};

const formatDate = (dateString) => {
  if (!dateString) return "-";
  const date = new Date(dateString);
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return date.toLocaleDateString(loc, {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
};

const formatTime = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return date.toLocaleTimeString(loc, { hour: "2-digit", minute: "2-digit" });
};

const hasTradingAccountSource = (withdrawal) => {
  return Boolean(
    withdrawal?.tradingAccountId &&
    (withdrawal?.groupLabel || withdrawal?.accountNumber),
  );
};

const formatWithdrawalSourceAccount = (withdrawal) => {
  if (!hasTradingAccountSource(withdrawal)) {
    return t("depositMgmt_wallet");
  }

  const groupLabel = String(withdrawal?.groupLabel || "").trim();
  const accountNumber = String(withdrawal?.accountNumber || "").trim();

  if (groupLabel && accountNumber) {
    return `${groupLabel} (${accountNumber})`;
  }

  return groupLabel || accountNumber || t("depositMgmt_wallet");
};

const normalizePlatformKey = (platformKey) => {
  const normalized = String(platformKey || "")
    .trim()
    .toLowerCase();
  if (!normalized) return "wallet";
  if (normalized === "finance_pro") return "financepro";
  return normalized;
};

// badge 只用 shortCode，没有就空着（wallet 不是交易平台，单独显示）
const getPlatformLabel = (platformKey, shortCode) => {
  const normalized = normalizePlatformKey(platformKey);
  if (normalized === "wallet") return t("depositMgmt_platform_wallet");
  return shortCode || "";
};

const getPlatformIcon = (platformKey) => {
  const normalized = normalizePlatformKey(platformKey);
  if (normalized === "wallet") return "fas fa-wallet";
  if (normalized === "mt4" || normalized === "mt5") return "fas fa-chart-line";
  if (normalized === "financepro") return "fas fa-landmark";
  return "fas fa-server";
};

const getWithdrawalPlatformMeta = (withdrawal) => {
  const platformKey = normalizePlatformKey(
    withdrawal?.groupPlatformKey ||
      withdrawal?.targetPlatformKey ||
      withdrawal?.sourcePlatformKey ||
      withdrawal?.platformKey,
  );
  const shortCode = withdrawal?.sourcePlatformShortCode || "";

  return {
    key: platformKey,
    label: getPlatformLabel(platformKey, shortCode),
    icon: getPlatformIcon(platformKey),
  };
};

const getInitials = (firstName, lastName) => {
  if (!firstName && !lastName) return "??";
  const first = firstName?.[0] || "";
  const last = lastName?.[0] || "";
  return (first + last).toUpperCase();
};

const getStatusLabel = (status) => {
  const key = String(status || "").toLowerCase();
  const labels = {
    pending: "withdrawalMgmt_status_pending",
    processing: "withdrawalMgmt_status_processing",
    completed: "withdrawalMgmt_status_completed",
    rejected: "withdrawalMgmt_status_rejected",
    cancaled: "withdrawalMgmt_status_cancelled",
    cancelled: "withdrawalMgmt_status_cancelled",
    canceled: "withdrawalMgmt_status_cancelled",
    failed: "withdrawalMgmt_status_failed",
  };
  const trKey = labels[key];
  return trKey ? t(trKey) : status;
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

// 页面加载
onMounted(async () => {
  await Promise.all([loadWithdrawals(), loadStatistics(), loadSearchTags()]);
});
</script>

<style scoped>
.withdrawal-management-page {
  padding: var(--content-padding);
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

.ui-page-header {
  align-items: flex-end;
  margin-bottom: var(--space-6);
  padding: 0;
  border: 0;
}

.ui-page-title {
  font-family: var(--font-display);
  font-size: clamp(26px, 2vw, 36px);
  font-weight: 600;
  line-height: 1.12;
  letter-spacing: -0.025em;
}

.ui-page-description {
  font-size: 14px;
}

.stats-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.stats-header.ui-surface {
  margin-bottom: var(--space-5);
  padding: var(--space-5);
  border: 1px solid var(--color-border);
}

.stats-header h2 {
  font-size: 20px;
  color: var(--color-ink);
  margin-bottom: 5px;
}

.stats-header p {
  font-size: 14px;
  color: var(--color-muted);
}

.page-stats {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
}

.stat-badge {
  border: 0;
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

.stat-badge.pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.stat-badge.processing {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.stat-badge.success {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.stat-badge.rejected {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.stat-badge.clickable {
  cursor: pointer;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease,
    opacity 0.2s ease,
    width 0.2s ease,
    max-height 0.2s ease,
    filter 0.2s ease;
  user-select: none;
}

.stat-badge.clickable:hover {
  filter: brightness(0.96);
}

.stat-badge.clickable.active {
  outline: 2px solid currentColor;
  outline-offset: 1px;
}

.search-filter-section {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 20px 25px;
  margin-bottom: 30px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.search-filter-container {
  display: flex;
  gap: 20px;
  align-items: flex-start;
  flex-wrap: wrap;
}

.search-input-wrapper {
  flex: 1;
  min-width: 300px;
  position: relative;
}

.search-input-wrapper input {
  width: 100%;
  padding: 12px 45px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  outline: none;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
}

.search-input-wrapper input:focus {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.search-icon {
  position: absolute;
  left: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-faint);
  font-size: 16px;
}

.clear-search {
  border: 0;
  background: transparent;
  position: absolute;
  right: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-faint);
  font-size: 16px;
  cursor: pointer;
  display: none;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease,
    opacity 0.2s ease,
    width 0.2s ease,
    max-height 0.2s ease,
    filter 0.2s ease;
}

.clear-search.show {
  display: block;
}

.clear-search:hover {
  color: var(--color-brand);
}

.tags-section {
  flex: 1;
  min-width: 300px;
}

.tags-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.tags-header h4 {
  font-size: 14px;
  color: var(--color-text);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.btn-add-tag {
  padding: 4px 12px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.btn-add-tag:hover {
  background: var(--color-brand-solid);
  color: white;
}

.tags-container {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  min-height: 36px;
}

.search-tag {
  border: 0;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: var(--color-brand-solid);
  color: white;
  border-radius: var(--radius-xl);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
  user-select: none;
}

.search-tag:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.search-tag.active {
  background: linear-gradient(
    135deg,
    var(--color-success) 0%,
    var(--color-success) 100%
  );
}

.tag-remove {
  border: 0;
  border-radius: 50%;
  color: white;
  background: var(--color-brand-solid);
  cursor: pointer;
  opacity: 0.8;
  transition: opacity 0.2s ease;
  font-weight: 700;
}

.tag-remove.active {
  background: var(--color-success-solid);
}

.search-tag-control {
  display: inline-flex;
  align-items: center;
  gap: 2px;
}

.tag-remove:hover {
  opacity: 1;
}

.empty-tags-message {
  color: var(--color-faint);
  font-size: 14px;
  font-style: italic;
}

.withdrawal-table-container {
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
  flex-wrap: wrap;
  gap: 15px;
}

.table-header h2 {
  font-size: 18px;
  color: var(--color-ink);
  margin: 0;
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

.bulk-actions {
  display: none;
  align-items: center;
  gap: 10px;
  padding: 10px 15px;
  background: var(--color-brand-soft);
  border-radius: var(--radius-md);
  border: 2px solid var(--color-brand);
}

.bulk-actions.show {
  display: flex;
}

.bulk-actions-label {
  font-size: 14px;
  color: var(--color-brand);
  font-weight: 600;
}

.bulk-actions-count {
  background: var(--color-brand-solid);
  color: white;
  padding: 2px 8px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
}

.btn-bulk {
  padding: 6px 12px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  position: relative;
}

.btn-bulk-approve {
  background: var(--color-success-solid);
  color: white;
}

.btn-bulk-approve:hover {
  background: var(--color-success-solid);
  transform: translateY(-1px);
}

.btn-bulk-tag {
  background: var(--color-warning-solid);
  color: white;
}

.btn-bulk-tag:hover {
  background: var(--color-warning-solid);
  transform: translateY(-1px);
}

.btn-bulk-export {
  position: relative;
}

.btn-bulk-export .btn-bulk {
  background: var(--color-brand-solid);
  color: white;
}

.btn-bulk-export .btn-bulk:hover {
  background: var(--color-brand-strong);
  transform: translateY(-1px);
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
  width: 100%;
  border: 0;
  background: transparent;
  text-align: left;
  padding: 10px 15px;
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--color-text);
  cursor: pointer;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease,
    opacity 0.2s ease,
    width 0.2s ease,
    max-height 0.2s ease,
    filter 0.2s ease;
  font-size: 14px;
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
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
}

.rows-selector select:hover,
.rows-selector select:focus {
  border-color: var(--color-brand);
}

.loading-container,
.error-container {
  text-align: center;
  padding: 60px 20px;
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

.withdrawal-table {
  width: 100%;
  border-collapse: collapse;
}

.withdrawal-table thead {
  background: var(--color-surface-soft);
}

.withdrawal-table th {
  padding: 16px 20px;
  text-align: left;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
}

.withdrawal-table tbody tr {
  border-bottom: 1px solid var(--color-border);
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease,
    opacity 0.2s ease,
    width 0.2s ease,
    max-height 0.2s ease,
    filter 0.2s ease;
}

.withdrawal-table tbody tr:hover {
  background: var(--color-surface-soft);
}

.withdrawal-table tbody tr.expanded {
  background: var(--color-brand-soft);
}

.withdrawal-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
}

.withdrawal-table th:nth-child(2),
.withdrawal-table td:nth-child(2) {
  width: 240px;
  min-width: 240px;
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
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
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
  font-size: 14px;
  color: var(--color-muted);
}

.amount-display {
  font-weight: 700;
  font-size: 16px;
  color: var(--color-danger);
}

.amount-crypto {
  font-size: 14px;
  color: var(--color-muted);
  margin-top: 2px;
}

.source-account {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.account-stack {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
}

.account-text {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

.platform-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.platform-wallet {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.platform-mt4 {
  background: var(--color-warning-soft);
  color: #c2410c;
}

.platform-mt5 {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.platform-financepro {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.payment-method-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 14px;
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

.time-small {
  color: var(--color-faint);
  font-size: 14px;
  display: block;
  margin-top: 2px;
}

.pending-text {
  color: var(--color-faint);
  font-style: italic;
}

.rejected-time small {
  color: var(--color-danger);
  font-weight: 600;
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 14px;
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

.status-badge.rejected {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.status-badge.cancaled,
.status-badge.cancelled,
.status-badge.canceled {
  background: var(--color-border);
  color: var(--color-text);
}

.withdrawal-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  max-width: 200px;
}

.withdrawal-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  background: var(--color-warning-soft);
  color: var(--color-warning);
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease,
    opacity 0.2s ease,
    width 0.2s ease,
    max-height 0.2s ease,
    filter 0.2s ease;
}

.withdrawal-tag:hover {
  background: var(--color-warning-border);
}

.withdrawal-tag-remove {
  border: 0;
  background: transparent;
  margin-left: 2px;
  cursor: pointer;
  opacity: 0.7;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease,
    opacity 0.2s ease,
    width 0.2s ease,
    max-height 0.2s ease,
    filter 0.2s ease;
  font-weight: 700;
}

.withdrawal-tag-remove:hover {
  opacity: 1;
  color: var(--color-danger);
  transform: scale(1.2);
}

.no-tags {
  color: var(--color-faint);
  font-size: 14px;
  font-style: italic;
}

.btn-action {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
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

.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 30px;
  border-top: 2px solid var(--color-border);
  flex-wrap: wrap;
  gap: 15px;
}

.pagination-btn {
  padding: 10px 20px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--color-text);
}

.pagination-btn:hover:not(:disabled) {
  border-color: var(--color-brand);
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pagination-pages {
  display: flex;
  gap: 5px;
  align-items: center;
}

.pagination-page-btn {
  min-width: 40px;
  height: 40px;
  padding: 0 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
  color: var(--color-text);
  display: flex;
  align-items: center;
  justify-content: center;
}

.pagination-page-btn:hover:not(:disabled):not(.ellipsis) {
  border-color: var(--color-brand);
  color: var(--color-brand);
  background: var(--color-brand-soft);
}

.pagination-page-btn.active {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
  color: white;
}

.pagination-page-btn.ellipsis {
  border: none;
  background: transparent;
  cursor: default;
  min-width: 20px;
  padding: 0;
}

.pagination-page-btn.ellipsis:hover {
  background: transparent;
}

.pagination-info {
  font-size: 14px;
  color: var(--color-text);
  font-weight: 600;
  margin-left: auto;
}

.btn {
  padding: 12px 24px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease,
    opacity 0.3s ease,
    width 0.3s ease,
    max-height 0.3s ease,
    filter 0.3s ease;
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

@media (max-width: 768px) {
  .withdrawal-management-page {
    padding: 20px 15px;
  }

  .page-header,
  .stats-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
  }

  .page-stats {
    flex-direction: column;
    width: 100%;
  }

  .stat-badge {
    width: 100%;
    justify-content: center;
  }

  .search-filter-container {
    flex-direction: column;
  }

  .search-input-wrapper,
  .tags-section {
    min-width: 100%;
  }

  .table-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .table-header-left,
  .table-header-right {
    width: 100%;
  }

  .bulk-actions {
    width: 100%;
    flex-wrap: wrap;
  }

  .withdrawal-table {
    font-size: 14px;
  }

  .withdrawal-table th,
  .withdrawal-table td {
    padding: 12px 10px;
  }

  .pagination {
    flex-direction: column;
    align-items: stretch;
  }

  .pagination-pages {
    justify-content: center;
    flex-wrap: wrap;
  }

  .pagination-info {
    margin-left: 0;
    text-align: center;
    width: 100%;
  }
}
</style>
