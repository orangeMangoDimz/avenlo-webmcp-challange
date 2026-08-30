<template>
  <div class="deposit-management-page ui-page workspace-list-page">
    <!-- Page Header -->
    <div class="page-header ui-page-header">
      <div class="page-title ui-page-heading">
        <h1 class="ui-page-title">{{ t("page_depositMgmt_title") }}</h1>
        <p class="ui-page-description">{{ t("page_depositMgmt_sub") }}</p>
      </div>
      <div class="page-actions ui-page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- Statistics Header -->
    <div class="stats-header ui-surface ui-summary-band">
      <div>
        <h2>{{ t("depositMgmt_stats_heading") }}</h2>
        <p>{{ t("depositMgmt_stats_sub") }}</p>
      </div>
      <div class="page-stats" v-if="statistics">
        <div class="stat-badge">
          <i class="fas fa-dollar-sign"></i>
          <span>{{
            tParams("depositMgmt_stat_today", "{amount} Today", {
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
            tParams("depositMgmt_stat_pending", "{n} Pending", {
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
            tParams("depositMgmt_stat_processing", "{n} Processing", {
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
            tParams("depositMgmt_stat_completed", "{n} Completed", {
              n: formatNumber(statistics.completedCount || 0),
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
            :placeholder="t('depositMgmt_searchPlaceholder')"
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

    <!-- Deposit Table -->
    <div class="deposit-table-container ui-data-region">
      <div class="table-header ui-toolbar">
        <div class="table-header-left">
          <h2>{{ t("depositMgmt_allDeposits") }}</h2>
          <div
            :class="['bulk-actions', { show: selectedDepositIds.length > 0 }]"
          >
            <span class="bulk-actions-label">{{
              t("leads_bulkSelected")
            }}</span>
            <span class="bulk-actions-count">{{
              selectedDepositIds.length
            }}</span>
            <button
              type="button"
              v-if="hasApprovePermission"
              class="btn-bulk btn-bulk-approve"
              @click="showBulkApproveModal = true"
            >
              <i class="fas fa-check-double"></i>
              {{ t("depositMgmt_bulkApproveAll") }}
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
            <label>{{ t("depositMgmt_showLabel") }}</label>
            <select v-model="perPage" @change="handlePerPageChange">
              <option :value="10">{{ t("depositMgmt_rows_10") }}</option>
              <option :value="20">{{ t("depositMgmt_rows_20") }}</option>
              <option :value="50">{{ t("depositMgmt_rows_50") }}</option>
              <option :value="100">{{ t("depositMgmt_rows_100") }}</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-container">
        <i class="fas fa-spinner fa-spin"></i>
        <p>{{ t("depositMgmt_loading") }}</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="error-container">
        <i class="fas fa-exclamation-circle"></i>
        <p>{{ error }}</p>
        <button type="button" class="btn btn-primary" @click="loadDeposits">
          <i class="fas fa-redo"></i> {{ t("depositMgmt_retry") }}
        </button>
      </div>

      <!-- Table -->
      <div v-else class="ui-table-scroll">
        <table class="deposit-table">
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
              <th>{{ t("depositMgmt_col_client") }}</th>
              <th>{{ t("depositMgmt_col_amount") }}</th>
              <th>{{ t("depositMgmt_col_toAccount") }}</th>
              <th>{{ t("depositMgmt_col_paymentMethod") }}</th>
              <th>{{ t("depositMgmt_col_requestTime") }}</th>
              <th>{{ t("depositMgmt_col_approvalTime") }}</th>
              <th>{{ t("depositMgmt_col_status") }}</th>
              <th>{{ t("depositMgmt_col_tags") }}</th>
              <th>{{ t("depositMgmt_col_action") }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="deposits.length === 0">
              <tr>
                <td colspan="10" class="empty-state">
                  <i class="fas fa-inbox"></i>
                  <p>{{ t("depositMgmt_empty") }}</p>
                </td>
              </tr>
            </template>
            <template v-else v-for="deposit in deposits" :key="deposit.id">
              <tr
                :data-deposit-id="deposit.id"
                :class="{ expanded: expandedDepositId === deposit.id }"
              >
                <td class="checkbox-col">
                  <label class="custom-checkbox">
                    <input
                      type="checkbox"
                      :value="deposit.id"
                      v-model="selectedDepositIds"
                    />
                    <span class="checkbox-checkmark"></span>
                  </label>
                </td>
                <td>
                  <div class="client-info">
                    <div class="client-avatar">
                      {{ getInitials(deposit.firstName, deposit.lastName) }}
                    </div>
                    <div class="client-details">
                      <div class="client-name">
                        {{ deposit.firstName }} {{ deposit.lastName }}
                      </div>
                      <div class="client-id">
                        {{ t("depositMgmt_clientIdPrefix") }}
                        {{ deposit.userId }}
                      </div>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="amount-display">
                    {{ formatCurrency(deposit.amount) }}
                  </div>
                  <div class="amount-crypto" v-if="deposit.amountCrypto">
                    {{ deposit.amountCrypto }} {{ deposit.shortCode }}
                  </div>
                </td>
                <td>
                  <div class="source-account">
                    <div
                      v-if="hasTradingAccountTarget(deposit)"
                      class="account-stack"
                    >
                      <span
                        :class="[
                          'platform-chip',
                          `platform-${getDepositPlatformMeta(deposit).key}`,
                        ]"
                      >
                        <i :class="getDepositPlatformMeta(deposit).icon"></i>
                        {{ getDepositPlatformMeta(deposit).label }}
                      </span>
                      <span class="account-text">{{
                        formatDepositToAccount(deposit)
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
                      deposit.methodType || 'fiat',
                    ]"
                  >
                    <i
                      :class="
                        deposit.gatewayIconClass ||
                        getMethodIcon(deposit.methodKey)
                      "
                    ></i>
                    {{ deposit.gatewayName || deposit.methodName || "-" }}
                  </span>
                </td>
                <td>
                  <div>{{ formatDate(deposit.requestedAt) }}</div>
                  <small class="time-small">{{
                    formatTime(deposit.requestedAt)
                  }}</small>
                </td>
                <td>
                  <div v-if="deposit.approvedAt">
                    {{ formatDate(deposit.approvedAt) }}
                    <small class="time-small">{{
                      formatTime(deposit.approvedAt)
                    }}</small>
                  </div>
                  <div v-else-if="deposit.rejectedAt" class="rejected-time">
                    {{ formatDate(deposit.rejectedAt) }}
                    <small>{{ t("depositMgmt_approvalRejected") }}</small>
                  </div>
                  <div
                    v-else-if="
                      deposit.cancaledAt ||
                      deposit.cancelledAt ||
                      deposit.canceledAt
                    "
                    class="rejected-time"
                  >
                    {{
                      formatDate(
                        deposit.cancaledAt ||
                          deposit.cancelledAt ||
                          deposit.canceledAt,
                      )
                    }}
                    <small>{{ t("depositMgmt_approvalCancelled") }}</small>
                  </div>
                  <span v-else class="pending-text">{{
                    t("depositMgmt_approvalPending")
                  }}</span>
                </td>
                <td>
                  <span :class="['status-badge', 'ui-status', deposit.status]">
                    {{ getStatusLabel(deposit.status) }}
                  </span>
                </td>
                <td>
                  <div class="deposit-tags">
                    <span
                      v-for="tag in deposit.tags"
                      :key="tag.id"
                      class="deposit-tag"
                    >
                      <i class="fas fa-tag"></i> {{ tag.tagName }}
                      <button
                        v-if="hasTagsPermission"
                        type="button"
                        class="deposit-tag-remove"
                        :aria-label="
                          tParams('leads_removeTagTitle', 'Remove {name} tag', {
                            name: tag.tagName,
                          })
                        "
                        @click="removeDepositTag(deposit.id, tag.id)"
                      >
                        ×
                      </button>
                    </span>
                    <span
                      v-if="!deposit.tags || deposit.tags.length === 0"
                      class="no-tags"
                    >
                      {{ t("depositMgmt_noTags") }}
                    </span>
                  </div>
                </td>
                <td>
                  <button
                    type="button"
                    class="btn-action btn-detail"
                    @click="toggleDetail(deposit.id)"
                  >
                    <i
                      :class="
                        expandedDepositId === deposit.id
                          ? 'fas fa-chevron-up'
                          : 'fas fa-chevron-down'
                      "
                    ></i>
                    {{
                      expandedDepositId === deposit.id
                        ? t("depositMgmt_btnHide")
                        : t("depositMgmt_btnDetails")
                    }}
                  </button>
                </td>
              </tr>

              <!-- Detail Row Component -->
              <DepositDetailRow
                :deposit-id="deposit.id"
                :deposit="deposit"
                :is-expanded="expandedDepositId === deposit.id"
                :has-approve-permission="hasApprovePermission"
                :has-reject-permission="hasRejectPermission"
                :has-add-note-permission="hasAddNotePermission"
                :has-contact-client-permission="hasContactClientPermission"
                :approving="processingApprove"
                @approve="handleApproveDeposit"
                @reject="handleRejectDeposit"
                @refresh="loadDeposits"
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
          <i class="fas fa-chevron-left"></i> {{ t("depositMgmt_previous") }}
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
          {{ t("depositMgmt_next") }} <i class="fas fa-chevron-right"></i>
        </button>

        <span class="pagination-info">
          {{
            tParams(
              "depositMgmt_pagination_range",
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
      :selected-deposits="selectedDepositsData"
      :processing="processingBulkApprove"
      @confirm="handleBulkApprove"
    />

    <BulkTagModal
      v-model="showBulkTagModal"
      :selected-deposits="selectedDepositsData"
      :processing="processingBulkTag"
      @confirm="handleBulkAddTag"
    />

    <SearchTagModal
      v-model="showSearchTagModal"
      :processing="processingSearchTag"
      @confirm="handleCreateSearchTag"
    />

    <RejectWithdrawalModal
      v-model="showRejectModal"
      :withdrawal-id="selectedDepositForAction"
      :processing="processingReject"
      :entity-label="t('depositMgmt_entityLabel')"
      rejection-scope="deposit"
      @confirm="handleRejectConfirm"
    />
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { ref, computed, onMounted, watch } from "vue";
import { useAuthStore } from "@/stores/auth";
import DepositDetailRow from "../components/deposits/DepositDetailRow.vue";
import BulkApproveModal from "../components/deposits/BulkApproveModal.vue";
import BulkTagModal from "../components/deposits/BulkTagModal.vue";
import SearchTagModal from "../components/deposits/SearchTagModal.vue";
import RejectWithdrawalModal from "../components/withdrawals/RejectWithdrawalModal.vue";
import depositApi from "../services/depositApi";
import { recordOperationLog } from "@/services/operationLogReportApi";
import { buildExportLogPayload } from "@/config/operationLogPages";
import { formatCurrency, formatNumber } from "../utils/helpers";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";

const { t, tParams, languageStore } = useAdminI18n();

const authStore = useAuthStore();

// Permission checks for Deposit page
const hasTagsPermission = computed(() =>
  authStore.hasPermission("page_deposit_tags"),
);
const hasApprovePermission = computed(() =>
  authStore.hasPermission("page_deposit_approve"),
);
const hasRejectPermission = computed(() =>
  authStore.hasPermission("page_deposit_reject"),
);
const hasExportPermission = computed(() =>
  authStore.hasPermission("page_deposit_export"),
);
const hasAddNotePermission = computed(() =>
  authStore.hasPermission("page_deposit_addnote"),
);
const hasContactClientPermission = computed(() =>
  authStore.hasPermission("page_deposit_contactclient"),
);

// State
const loading = ref(true);
const error = ref(null);
const deposits = ref([]);
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
const selectedDepositIds = ref([]);
const expandedDepositId = ref(null);

// Modals
const showBulkApproveModal = ref(false);
const showBulkTagModal = ref(false);
const showSearchTagModal = ref(false);
const showExportDropdown = ref(false);
const showRejectModal = ref(false);
const selectedDepositForAction = ref(null);

// Processing states
const processingBulkApprove = ref(false);
const processingBulkTag = ref(false);
const processingSearchTag = ref(false);
const processingReject = ref(false);
const processingApprove = ref(false);

// Computed
const isAllSelected = computed(() => {
  return (
    deposits.value.length > 0 &&
    selectedDepositIds.value.length === deposits.value.length
  );
});

const isIndeterminate = computed(() => {
  return (
    selectedDepositIds.value.length > 0 &&
    selectedDepositIds.value.length < deposits.value.length
  );
});

const selectedDepositsData = computed(() => {
  return deposits.value
    .filter((d) => selectedDepositIds.value.includes(d.id))
    .map((d) => ({
      id: d.id,
      transactionId: d.transactionId,
      clientName: `${d.firstName} ${d.lastName}`,
      amount: d.amount,
    }));
});

// 加载存款列表
const loadDeposits = async () => {
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

    const response = await depositApi.getDeposits(params);

    if (response.success) {
      deposits.value = response.data.items || response.data || [];
      total.value = response.data.pagination.total || 0;
      totalPages.value = response.data.pagination.total_pages || 0;
      currentPage.value = response.data.pagination.page || 1;
      perPage.value = response.data.pagination.per_page || 10;
    } else {
      const raw = response.message || t("depositMgmt_err_loadFailed");
      error.value = translateApiErrorMessage(response.errorCode, raw);
    }
  } catch (err) {
    console.error("Failed to load deposits:", err);
    const data = err?.response?.data ?? err;
    const rawMsg =
      data?.message || err?.message || t("depositMgmt_err_loadFailed");
    error.value = translateApiErrorMessage(data?.errorCode, rawMsg);
  } finally {
    loading.value = false;
  }
};

// 加载统计数据
const loadStatistics = async () => {
  try {
    const response = await depositApi.getDepositStatistics();
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
    const response = await depositApi.getSearchTags("deposit");
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
  loadDeposits();
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
  loadDeposits();
};

// 清除搜索
const clearSearch = () => {
  searchQuery.value = "";
  activeSearchTag.value = null;
  loadDeposits();
};

// 应用搜索标签
const applySearchTag = (tag) => {
  if (activeSearchTag.value === tag.id) {
    // 取消激活
    activeSearchTag.value = null;
    searchQuery.value = "";
  } else {
    // 激活标签
    activeSearchTag.value = tag.id;
    searchQuery.value = tag.searchKeywords.split(",")[0].trim();
  }
  handleSearch();
};

// 移除搜索标签
const removeSearchTag = async (tagId) => {
  if (!confirm(t("depositMgmt_confirm_removeSearchTag"))) return;

  try {
    const response = await depositApi.deleteSearchTag(tagId);
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
        "depositMgmt_alert_removeSearchTagFailed",
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
    const response = await depositApi.createSearchTag(data);
    if (response.success) {
      await loadSearchTags();
      showSearchTagModal.value = false;
      alert(t("depositMgmt_alert_searchTagCreated"));
    }
  } catch (err) {
    const data = err?.response?.data ?? err;
    const rawMsg = data?.message || err?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams(
        "depositMgmt_alert_createTagFailed",
        "Failed to create tag: {msg}",
        { msg },
      ),
    );
  } finally {
    processingSearchTag.value = false;
  }
};

// 切换详情
const toggleDetail = (depositId) => {
  if (expandedDepositId.value === depositId) {
    expandedDepositId.value = null;
  } else {
    expandedDepositId.value = depositId;
  }
};

// 切换全选
const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedDepositIds.value = [];
  } else {
    selectedDepositIds.value = deposits.value.map((d) => d.id);
  }
};

// 批准单个存款
const handleApproveDeposit = async (depositId) => {
  if (processingApprove.value) return; // 审批进行中，避免重复提交
  if (!confirm(t("depositMgmt_confirm_approve"))) return;

  processingApprove.value = true;
  try {
    const response = await depositApi.approveDeposit(depositId);
    if (response.success) {
      alert(t("depositMgmt_alert_approveOk"));
      await loadDeposits();
      await loadStatistics();
    }
  } catch (err) {
    const data = err?.response?.data ?? err;
    const rawMsg = data?.message || err?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams(
        "depositMgmt_alert_approveFailed",
        "Failed to approve deposit: {msg}",
        { msg },
      ),
    );
  } finally {
    processingApprove.value = false;
  }
};

const handleRejectDeposit = (depositId) => {
  selectedDepositForAction.value = depositId;
  showRejectModal.value = true;
};

const handleRejectConfirm = async (data) => {
  processingReject.value = true;
  try {
    const response = await depositApi.rejectDeposit(
      selectedDepositForAction.value,
      data,
    );
    if (response.success) {
      alert(t("depositMgmt_alert_rejectOk"));
      showRejectModal.value = false;
      selectedDepositForAction.value = null;
      expandedDepositId.value = null;
      await loadDeposits();
      await loadStatistics();
    }
  } catch (err) {
    const data = err?.response?.data ?? err;
    const rawMsg = data?.message || err?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams(
        "depositMgmt_alert_rejectFailed",
        "Failed to reject deposit: {msg}",
        { msg },
      ),
    );
  } finally {
    processingReject.value = false;
  }
};

// 批量批准
const handleBulkApprove = async (data) => {
  processingBulkApprove.value = true;
  try {
    const response = await depositApi.bulkApproveDeposits(data);
    if (response.success) {
      const summary = response.data.summary;
      alert(
        tParams(
          "depositMgmt_alert_bulkApprove",
          "Bulk approval completed. Success: {success}, Failed: {failed}, Total: {total}",
          {
            success: String(summary.success),
            failed: String(summary.failed),
            total: String(summary.total),
          },
        ),
      );

      showBulkApproveModal.value = false;
      selectedDepositIds.value = [];
      await loadDeposits();
      await loadStatistics();
    }
  } catch (err) {
    const data = err?.response?.data ?? err;
    const rawMsg = data?.message || err?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams(
        "depositMgmt_alert_bulkApproveFailed",
        "Failed to approve deposits: {msg}",
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
    const response = await depositApi.bulkAddTags(data);
    if (response.success) {
      alert(
        tParams(
          "depositMgmt_alert_bulkTagOk",
          'Tag "{tag}" added to {n} deposit(s)!',
          {
            tag: data.tagName,
            n: String(data.depositIds.length),
          },
        ),
      );

      showBulkTagModal.value = false;
      selectedDepositIds.value = [];
      await loadDeposits();
    }
  } catch (err) {
    const data = err?.response?.data ?? err;
    const rawMsg = data?.message || err?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams("depositMgmt_alert_bulkTagFailed", "Failed to add tags: {msg}", {
        msg,
      }),
    );
  } finally {
    processingBulkTag.value = false;
  }
};

// 移除存款标签
const removeDepositTag = async (depositId, tagId) => {
  if (!confirm(t("depositMgmt_confirm_removeDepositTag"))) return;

  try {
    const response = await depositApi.removeTagFromDeposit(depositId, tagId);
    if (response.success) {
      await loadDeposits();
    }
  } catch (err) {
    const data = err?.response?.data ?? err;
    const rawMsg = data?.message || err?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams(
        "depositMgmt_alert_removeDepositTagFailed",
        "Failed to remove tag: {msg}",
        { msg },
      ),
    );
  }
};

// 导出
const handleExport = async (format) => {
  showExportDropdown.value = false;

  if (selectedDepositIds.value.length === 0) {
    alert(t("depositMgmt_alert_exportNeedSelection"));
    return;
  }

  try {
    const response = await depositApi.exportDeposits({
      depositIds: selectedDepositIds.value,
      format,
    });

    if (response.success) {
      // 处理导出数据
      const deposits = response.data.deposits || [];

      if (format === "csv") {
        exportAsCSV(deposits);
      } else if (format === "excel") {
        exportAsExcel(deposits);
      }

      alert(
        tParams(
          "depositMgmt_alert_exportOk",
          "Successfully exported {n} deposit(s)!",
          { n: String(deposits.length) },
        ),
      );

      const fmtLabel = format.toUpperCase();
      recordOperationLog(
        buildExportLogPayload("page_deposits", {
          detailZh: `导出 ${deposits.length} 笔入金（${fmtLabel}）`,
          detailEn: `Exported ${deposits.length} deposit(s) (${fmtLabel})`,
        }),
      ).catch(() => {});
    }
  } catch (err) {
    const data = err?.response?.data ?? err;
    const rawMsg = data?.message || err?.message || t("common_unknownError");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(
      tParams("depositMgmt_alert_exportFailed", "Failed to export: {msg}", {
        msg,
      }),
    );
  }
};

// CSV导出
const exportAsCSV = (deposits) => {
  let csv =
    "Transaction ID,Client Name,Email,Amount,Payment Method,Status,Request Time,Approval Time\n";

  deposits.forEach((d) => {
    csv += `"${d.transactionId}","${d.firstName} ${d.lastName}","${d.email}",`;
    csv += `"${d.amount}","${getDepositGatewayName(d)}","${d.status}",`;
    csv += `"${d.requestedAt}","${d.approvedAt || "N/A"}"\n`;
  });

  const blob = new Blob([csv], { type: "text/csv" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = `deposits_${Date.now()}.csv`;
  link.click();
};

// Excel导出
const exportAsExcel = (deposits) => {
  // 简化版Excel导出
  exportAsCSV(deposits);
};

// 切换每页显示行数
const handlePerPageChange = () => {
  currentPage.value = 1;
  loadDeposits();
};

// 切换页码
const changePage = (page) => {
  currentPage.value = page;
  loadDeposits();
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

const getInitials = (firstName, lastName) => {
  if (!firstName && !lastName) return "??";
  const first = firstName?.[0] || "";
  const last = lastName?.[0] || "";
  return (first + last).toUpperCase();
};

const getStatusLabel = (status) => {
  const key = String(status || "").toLowerCase();
  const labels = {
    pending: "depositMgmt_status_pending",
    processing: "depositMgmt_status_processing",
    completed: "depositMgmt_status_completed",
    failed: "depositMgmt_status_failed",
    rejected: "depositMgmt_status_rejected",
    expired: "depositMgmt_status_expired",
    cancaled: "depositMgmt_status_cancelled",
    cancelled: "depositMgmt_status_cancelled",
    canceled: "depositMgmt_status_cancelled",
    unpaid: "depositMgmt_status_unpaid",
    payment_failed: "depositMgmt_status_payment_failed",
  };
  const trKey = labels[key];
  return trKey ? t(trKey) : status;
};

const getDepositGatewayName = (deposit) => {
  return deposit?.gatewayName || deposit?.methodName || "-";
};

const hasTradingAccountTarget = (deposit) => {
  return Boolean(
    deposit?.tradingAccountId &&
    (deposit?.groupLabel || deposit?.accountNumber),
  );
};

const formatDepositToAccount = (deposit) => {
  if (!hasTradingAccountTarget(deposit)) {
    return t("depositMgmt_wallet");
  }

  const groupLabel = String(deposit?.groupLabel || "").trim();
  const accountNumber = String(deposit?.accountNumber || "").trim();

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

const getDepositPlatformMeta = (deposit) => {
  const platformKey = normalizePlatformKey(
    deposit?.groupPlatformKey ||
      deposit?.targetPlatformKey ||
      deposit?.platformKey,
  );
  const shortCode = deposit?.targetPlatformShortCode || "";

  return {
    key: platformKey,
    label: getPlatformLabel(platformKey, shortCode),
    icon: getPlatformIcon(platformKey),
  };
};

const getMethodIcon = (methodKey) => {
  const icons = {
    bitcoin: "fab fa-bitcoin",
    ethereum: "fab fa-ethereum",
    usdt: "fas fa-coins",
    usdc: "fas fa-coins",
    bank_transfer: "fas fa-university",
    alchemy_pay: "fas fa-credit-card",
  };
  return icons[methodKey] || "fas fa-wallet";
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
  await Promise.all([loadDeposits(), loadStatistics(), loadSearchTags()]);
});
</script>

<style scoped>
.deposit-management-page {
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
  padding: 12px 45px 12px 45px;
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

.deposit-table-container {
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
  animation: slideDown 0.2s ease;
}

.export-dropdown.show {
  display: block;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
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

.deposit-table {
  width: 100%;
  border-collapse: collapse;
}

.deposit-table thead {
  background: var(--color-surface-soft);
}

.deposit-table th {
  padding: 16px 20px;
  text-align: left;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
}

.deposit-table tbody tr {
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

.deposit-table tbody tr:hover {
  background: var(--color-surface-soft);
}

.deposit-table tbody tr.expanded {
  background: var(--color-brand-soft);
}

.deposit-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
}

.deposit-table th:nth-child(2),
.deposit-table td:nth-child(2) {
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
  color: var(--color-success);
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

.status-badge.failed {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.status-badge.rejected {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.status-badge.expired {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.status-badge.cancaled,
.status-badge.cancelled,
.status-badge.canceled {
  background: var(--color-border);
  color: var(--color-text);
}

.deposit-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  max-width: 200px;
}

.deposit-tag {
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

.deposit-tag:hover {
  background: var(--color-warning-border);
}

.deposit-tag-remove {
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

.deposit-tag-remove:hover {
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
  color: var(--color-faint);
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
  .deposit-management-page {
    padding: 20px 15px;
  }

  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
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

  .deposit-table {
    font-size: 14px;
  }

  .deposit-table th,
  .deposit-table td {
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
