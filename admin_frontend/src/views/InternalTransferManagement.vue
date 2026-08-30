<template>
  <div class="internal-transfer-management-page ui-page workspace-list-page">
    <!-- Page Header -->
    <div class="page-header ui-page-header">
      <div class="page-title ui-page-heading">
        <h1 class="ui-page-title">
          {{ t("page_internalTransferMgmt_title") }}
        </h1>
        <p class="ui-page-description">
          {{ t("page_internalTransferMgmt_sub") }}
        </p>
      </div>
      <div class="page-actions ui-page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- Statistics Header -->
    <div class="stats-header ui-surface ui-summary-band">
      <div>
        <h2>{{ t("internalXfer_stats_heading") }}</h2>
        <p>{{ t("internalXfer_stats_sub") }}</p>
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

    <!-- Internal Transfer Table -->
    <div class="transfer-table-container ui-data-region">
      <div class="table-header ui-toolbar">
        <div class="table-header-left">
          <h2>{{ t("internalXfer_allTransfers") }}</h2>
          <div
            :class="['bulk-actions', { show: selectedTransferIds.length > 0 }]"
          >
            <span class="bulk-actions-label">{{
              t("leads_bulkSelected")
            }}</span>
            <span class="bulk-actions-count">{{
              selectedTransferIds.length
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
        <p>{{ t("internalXfer_loading") }}</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="error-container">
        <i class="fas fa-exclamation-circle"></i>
        <p>{{ error }}</p>
        <button type="button" class="btn btn-primary" @click="loadTransfers">
          <i class="fas fa-redo"></i> {{ t("depositMgmt_retry") }}
        </button>
      </div>

      <!-- Table -->
      <div v-else class="ui-table-scroll">
        <table class="transfer-table">
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
              <th>{{ t("internalXfer_col_from") }}</th>
              <th>{{ t("internalXfer_col_to") }}</th>
              <th>{{ t("depositMgmt_col_amount") }}</th>
              <th>{{ t("depositMgmt_col_requestTime") }}</th>
              <th>{{ t("depositMgmt_col_approvalTime") }}</th>
              <th>{{ t("depositMgmt_col_status") }}</th>
              <th>{{ t("depositMgmt_col_tags") }}</th>
              <th>{{ t("depositMgmt_col_action") }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="transfers.length === 0">
              <tr>
                <td colspan="10" class="empty-state">
                  <i class="fas fa-inbox"></i>
                  <p>{{ t("internalXfer_empty") }}</p>
                </td>
              </tr>
            </template>
            <template v-else v-for="transfer in transfers" :key="transfer.id">
              <tr
                :data-transfer-id="transfer.id"
                :class="{ expanded: expandedTransferId === transfer.id }"
              >
                <td class="checkbox-col">
                  <label class="custom-checkbox">
                    <input
                      type="checkbox"
                      :value="transfer.id"
                      v-model="selectedTransferIds"
                    />
                    <span class="checkbox-checkmark"></span>
                  </label>
                </td>
                <td>
                  <div class="client-info">
                    <div class="client-avatar">
                      {{ getInitials(transfer.firstName, transfer.lastName) }}
                    </div>
                    <div class="client-details">
                      <div class="client-name">
                        {{ transfer.firstName }} {{ transfer.lastName }}
                      </div>
                      <div class="client-id">
                        {{ t("depositMgmt_clientIdPrefix") }}
                        {{ transfer.userId }}
                      </div>
                    </div>
                  </div>
                </td>
                <td>
                  <div
                    v-if="
                      transfer.fromType == 'wallet' ||
                      transfer.fromType == 'available_balance'
                    "
                    class="from-info"
                  >
                    <span class="platform-chip platform-wallet">
                      <i class="fas fa-wallet"></i>
                      {{ t("depositMgmt_wallet") }}
                    </span>
                  </div>
                  <div v-else class="from-info">
                    <div class="account-info">
                      <span
                        :class="[
                          'platform-chip',
                          `platform-${getTransferPlatformMeta(transfer, 'from').key}`,
                        ]"
                      >
                        <i
                          :class="
                            getTransferPlatformMeta(transfer, 'from').icon
                          "
                        ></i>
                        {{ getTransferPlatformMeta(transfer, "from").label }}
                      </span>
                      <div class="account-number">
                        {{ formatTransferAccountLabel(transfer, "from") }}
                      </div>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="account-info">
                    <span
                      :class="[
                        'platform-chip',
                        `platform-${getTransferPlatformMeta(transfer, 'to').key}`,
                      ]"
                    >
                      <i
                        :class="getTransferPlatformMeta(transfer, 'to').icon"
                      ></i>
                      {{ getTransferPlatformMeta(transfer, "to").label }}
                    </span>
                    <div class="account-number">
                      {{ formatTransferAccountLabel(transfer, "to") }}
                    </div>
                  </div>
                </td>
                <td>
                  <div class="amount-display">
                    {{ formatCurrency(transfer.amount) }}
                  </div>
                </td>
                <td>
                  <div>{{ formatDate(transfer.requestedAt) }}</div>
                  <small class="time-small">{{
                    formatTime(transfer.requestedAt)
                  }}</small>
                </td>
                <td>
                  <div v-if="transfer.approvedAt">
                    {{ formatDate(transfer.approvedAt) }}
                    <small class="time-small">{{
                      formatTime(transfer.approvedAt)
                    }}</small>
                  </div>
                  <span v-else class="pending-text">{{
                    t("depositMgmt_approvalPending")
                  }}</span>
                </td>
                <td>
                  <span :class="['status-badge', 'ui-status', transfer.status]">
                    {{ getStatusLabel(transfer.status) }}
                  </span>
                </td>
                <td>
                  <div class="transfer-tags">
                    <span
                      v-for="tag in transfer.tags"
                      :key="tag.id"
                      class="transfer-tag"
                    >
                      <i class="fas fa-tag"></i> {{ tag.tagName }}
                      <button
                        v-if="hasTagsPermission"
                        type="button"
                        class="transfer-tag-remove"
                        :aria-label="
                          tParams('leads_removeTagTitle', 'Remove {name} tag', {
                            name: tag.tagName,
                          })
                        "
                        @click="removeTransferTag(transfer.id, tag.id)"
                      >
                        ×
                      </button>
                    </span>
                    <span
                      v-if="!transfer.tags || transfer.tags.length === 0"
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
                    @click="toggleDetail(transfer.id)"
                  >
                    <i
                      :class="
                        expandedTransferId === transfer.id
                          ? 'fas fa-chevron-up'
                          : 'fas fa-chevron-down'
                      "
                    ></i>
                    {{
                      expandedTransferId === transfer.id
                        ? t("depositMgmt_btnHide")
                        : t("depositMgmt_btnDetails")
                    }}
                  </button>
                </td>
              </tr>

              <!-- Detail Row Component -->
              <InternalTransferDetailRow
                :transfer-id="transfer.id"
                :is-expanded="expandedTransferId === transfer.id"
                :has-approve-permission="hasApprovePermission"
                :has-reject-permission="hasRejectPermission"
                :has-add-note-permission="hasAddNotePermission"
                :has-contact-client-permission="hasContactClientPermission"
                @approve="handleApproveTransfer"
                @reject="handleRejectTransfer"
                @refresh="loadTransfers"
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
      :selected-transfers="selectedTransfersData"
      :processing="processingBulkApprove"
      @confirm="handleBulkApprove"
    />

    <BulkTagModal
      v-model="showBulkTagModal"
      :selected-transfers="selectedTransfersData"
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
      :withdrawal-id="selectedTransferForAction"
      :processing="processingReject"
      :entity-label="t('internalXfer_entityLabel')"
      rejection-scope="internal_transfer"
      @confirm="handleRejectConfirm"
    />
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { ref, computed, onMounted, onBeforeUnmount, watch } from "vue";
import { useAuthStore } from "@/stores/auth";
import InternalTransferDetailRow from "../components/internal-transfers/InternalTransferDetailRow.vue";
import BulkApproveModal from "../components/internal-transfers/BulkApproveModal.vue";
import BulkTagModal from "../components/internal-transfers/BulkTagModal.vue";
import SearchTagModal from "../components/internal-transfers/SearchTagModal.vue";
import RejectWithdrawalModal from "../components/withdrawals/RejectWithdrawalModal.vue";
import * as internalTransferApi from "../services/internalTransferApi";
import { formatCurrency, formatNumber } from "../utils/helpers";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";
import { recordOperationLog } from "@/services/operationLogReportApi";
import { buildExportLogPayload } from "@/config/operationLogPages";

const { t, tParams, languageStore } = useAdminI18n();

const authStore = useAuthStore();

// Permission checks for Internal Transfer page
const hasTagsPermission = computed(() =>
  authStore.hasPermission("page_internaltransfer_tags"),
);
const hasApprovePermission = computed(() =>
  authStore.hasPermission("page_internaltransfer_approve"),
);
const hasRejectPermission = computed(() =>
  authStore.hasPermission("page_internaltransfer_reject"),
);
const hasExportPermission = computed(() =>
  authStore.hasPermission("page_internaltransfer_export"),
);
const hasAddNotePermission = computed(() =>
  authStore.hasPermission("page_internaltransfer_addnote"),
);
const hasContactClientPermission = computed(() =>
  authStore.hasPermission("page_internaltransfer_contactclient"),
);

// State
const loading = ref(true);
const error = ref(null);
const transfers = ref([]);
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
    for (let i = 1; i <= total; i++) {
      pages.push(i);
    }
  } else {
    pages.push(1);

    if (current <= 4) {
      for (let i = 2; i <= 5; i++) {
        pages.push(i);
      }
      pages.push("...");
      pages.push(total);
    } else if (current >= total - 3) {
      pages.push("...");
      for (let i = total - 4; i <= total; i++) {
        pages.push(i);
      }
    } else {
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
const selectedTransferIds = ref([]);
const expandedTransferId = ref(null);
const selectedTransferForAction = ref(null);

// Modals
const showBulkApproveModal = ref(false);
const showBulkTagModal = ref(false);
const showSearchTagModal = ref(false);
const showRejectModal = ref(false);
const showExportDropdown = ref(false);

// Processing states
const processingBulkApprove = ref(false);
const processingBulkTag = ref(false);
const processingSearchTag = ref(false);
const processingReject = ref(false);

// Computed
const isAllSelected = computed(() => {
  return (
    transfers.value.length > 0 &&
    selectedTransferIds.value.length === transfers.value.length
  );
});

const isIndeterminate = computed(() => {
  return (
    selectedTransferIds.value.length > 0 &&
    selectedTransferIds.value.length < transfers.value.length
  );
});

const selectedTransfersData = computed(() => {
  return transfers.value
    .filter((row) => selectedTransferIds.value.includes(row.id))
    .map((row) => ({
      id: row.id,
      transactionId: row.transactionId,
      clientName: `${row.firstName} ${row.lastName}`,
      amount: row.amount,
    }));
});

// 加载内部转账列表
const loadTransfers = async () => {
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

    const response = await internalTransferApi.getInternalTransfers(params);

    if (response.success) {
      transfers.value = response.data.items || response.data || [];
      total.value = response.data.pagination.total || 0;
      totalPages.value = response.data.pagination.total_pages || 0;
      currentPage.value = response.data.pagination.page || 1;
      perPage.value = response.data.pagination.per_page || 10;
    } else {
      const raw = response.message || "";
      error.value = translateApiErrorMessage(
        response.errorCode,
        raw || t("internalXfer_err_loadFailed"),
      );
    }
  } catch (err) {
    console.error("Failed to load internal transfers:", err);
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    error.value = translateApiErrorMessage(
      data?.errorCode,
      rawMsg || t("internalXfer_err_loadFailed"),
    );
  } finally {
    loading.value = false;
  }
};

// 搜索
const handleSearch = () => {
  currentPage.value = 1;
  loadTransfers();
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
  loadTransfers();
};

// 清除搜索
const clearSearch = () => {
  searchQuery.value = "";
  activeSearchTag.value = null;
  loadTransfers();
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
    const response = await internalTransferApi.deleteSearchTag(tagId);
    if (response.success) {
      await loadSearchTags();
      if (activeSearchTag.value === tagId) {
        clearSearch();
      }
    }
  } catch (err) {
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "internalXfer_alert_removeSearchTagFailed",
        "Failed to remove tag: {msg}",
        { msg: translateApiErrorMessage(data?.errorCode, rawMsg) },
      ),
    );
  }
};

// 创建搜索标签
const handleCreateSearchTag = async (data) => {
  processingSearchTag.value = true;
  try {
    const response = await internalTransferApi.createSearchTag(data);
    if (response.success) {
      await loadSearchTags();
      showSearchTagModal.value = false;
      alert(t("internalXfer_alert_searchTagCreated"));
    }
  } catch (err) {
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "internalXfer_alert_createTagFailed",
        "Failed to create tag: {msg}",
        { msg: translateApiErrorMessage(data?.errorCode, rawMsg) },
      ),
    );
  } finally {
    processingSearchTag.value = false;
  }
};

// 切换全选
const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedTransferIds.value = [];
  } else {
    selectedTransferIds.value = transfers.value.map((t) => t.id);
  }
};

// 批量批准
const handleBulkApprove = async (data) => {
  processingBulkApprove.value = true;
  try {
    const response =
      await internalTransferApi.bulkApproveInternalTransfers(data);
    if (response.success) {
      const summary = response.data.summary;
      alert(
        tParams(
          "internalXfer_alert_bulkApprove",
          "Bulk approval completed. Success: {success}, Failed: {failed}, Total: {total}",
          {
            success: summary.success,
            failed: summary.failed,
            total: summary.total,
          },
        ),
      );

      showBulkApproveModal.value = false;
      selectedTransferIds.value = [];
      await loadTransfers();
      await loadStatistics();
    }
  } catch (err) {
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "internalXfer_alert_bulkApproveFailed",
        "Failed to approve transfers: {msg}",
        { msg: translateApiErrorMessage(data?.errorCode, rawMsg) },
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
    const response = await internalTransferApi.bulkAddTags(data);
    if (response.success) {
      alert(
        tParams(
          "internalXfer_alert_bulkTagOk",
          'Tag "{tag}" added to {n} transfer(s)!',
          { tag: data.tagName, n: data.transferIds.length },
        ),
      );

      showBulkTagModal.value = false;
      selectedTransferIds.value = [];
      await loadTransfers();
    }
  } catch (err) {
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams("internalXfer_alert_bulkTagFailed", "Failed to add tags: {msg}", {
        msg: translateApiErrorMessage(data?.errorCode, rawMsg),
      }),
    );
  } finally {
    processingBulkTag.value = false;
  }
};

// 导出
const handleExport = (format) => {
  // 先关闭下拉菜单
  showExportDropdown.value = false;

  if (selectedTransferIds.value.length === 0) {
    alert(t("internalXfer_alert_exportNeedSelection"));
    return;
  }

  // 直接从当前数据中筛选选中的转账记录
  const selectedTransfers = transfers.value.filter((t) =>
    selectedTransferIds.value.includes(t.id),
  );

  if (selectedTransfers.length === 0) {
    alert(t("internalXfer_alert_exportNothing"));
    return;
  }

  // 使用 setTimeout 确保下拉菜单关闭后再执行导出
  setTimeout(() => {
    try {
      if (format === "csv") {
        exportAsCSV(selectedTransfers);
      } else if (format === "excel") {
        exportAsExcel(selectedTransfers);
      }

      alert(
        tParams(
          "internalXfer_alert_exportOk",
          "Successfully exported {n} transfer(s)!",
          { n: selectedTransfers.length },
        ),
      );

      const fmtLabel = format.toUpperCase();
      recordOperationLog(
        buildExportLogPayload("page_internaltransfers", {
          detailZh: `导出 ${selectedTransfers.length} 笔内部转账（${fmtLabel}）`,
          detailEn: `Exported ${selectedTransfers.length} internal transfer(s) (${fmtLabel})`,
        }),
      ).catch(() => {});
    } catch (err) {
      alert(
        tParams("internalXfer_alert_exportFailed", "Failed to export: {msg}", {
          msg: err.message || t("common_unknownError"),
        }),
      );
    }
  }, 100);
};

// CSV导出
const exportAsCSV = (transfers) => {
  let csv =
    "Transaction ID,Client Name,Email,From,To,Amount,Status,Request Time,Approval Time\n";

  transfers.forEach((row) => {
    const fromInfo =
      row.fromType == "wallet" || row.fromType == "available_balance"
        ? t("depositMgmt_wallet")
        : formatTransferAccountLabel(row, "from");

    const toInfo = formatTransferAccountLabel(row, "to");

    csv += `"${row.transactionId || ""}","${row.firstName || ""} ${row.lastName || ""}","${row.email || ""}",`;
    csv += `"${fromInfo}","${toInfo}",`;
    csv += `"${row.amount || "0"}","${row.status || ""}",`;
    csv += `"${row.requestedAt || t("internalXfer_na")}","${row.approvedAt || t("internalXfer_na")}"\n`;
  });

  const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = `internal-transfers_${Date.now()}.csv`;
  link.click();
  URL.revokeObjectURL(url);
};

// Excel导出
const exportAsExcel = (transfers) => {
  // Excel格式导出（使用CSV格式，Excel可以打开）
  let csv =
    "Transaction ID,Client Name,Email,From,To,Amount,Status,Request Time,Approval Time\n";

  transfers.forEach((row) => {
    const fromInfo =
      row.fromType == "wallet" || row.fromType == "available_balance"
        ? t("depositMgmt_wallet")
        : formatTransferAccountLabel(row, "from");

    const toInfo = formatTransferAccountLabel(row, "to");

    csv += `"${row.transactionId || ""}","${row.firstName || ""} ${row.lastName || ""}","${row.email || ""}",`;
    csv += `"${fromInfo}","${toInfo}",`;
    csv += `"${row.amount || "0"}","${row.status || ""}",`;
    csv += `"${row.requestedAt || t("internalXfer_na")}","${row.approvedAt || t("internalXfer_na")}"\n`;
  });

  // 使用Excel MIME类型
  const blob = new Blob(["\ufeff" + csv], {
    type: "application/vnd.ms-excel;charset=utf-8;",
  });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = `internal-transfers_${Date.now()}.xls`;
  link.click();
  URL.revokeObjectURL(url);
};

// 加载统计数据
const loadStatistics = async () => {
  try {
    const response = await internalTransferApi.getInternalTransferStatistics();
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
    const response =
      await internalTransferApi.getSearchTags("internal_transfer");
    if (response.success) {
      searchTags.value = response.data || [];
    }
  } catch (err) {
    console.error("Failed to load search tags:", err);
  }
};

// 切换详情
const toggleDetail = (transferId) => {
  if (expandedTransferId.value === transferId) {
    expandedTransferId.value = null;
  } else {
    expandedTransferId.value = transferId;
  }
};

// 批准单个转账
const handleApproveTransfer = async (transferId) => {
  if (!confirm(t("internalXfer_confirm_approve"))) return;

  try {
    const response =
      await internalTransferApi.approveInternalTransfer(transferId);
    if (response.success) {
      alert(t("internalXfer_alert_approveOk"));
      await loadTransfers();
    }
  } catch (err) {
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "internalXfer_alert_approveFailed",
        "Failed to approve transfer: {msg}",
        { msg: translateApiErrorMessage(data?.errorCode, rawMsg) },
      ),
    );
  }
};

// 拒绝单个转账
const handleRejectTransfer = (transferId) => {
  selectedTransferForAction.value = transferId;
  showRejectModal.value = true;
};

// 确认拒绝
const handleRejectConfirm = async (data) => {
  processingReject.value = true;

  try {
    const response = await internalTransferApi.rejectInternalTransfer(
      selectedTransferForAction.value,
      data,
    );
    if (response.success) {
      alert(t("internalXfer_alert_rejectOk"));
      showRejectModal.value = false;
      selectedTransferForAction.value = null;
      expandedTransferId.value = null;
      await loadTransfers();
      await loadStatistics();
    }
  } catch (err) {
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "internalXfer_alert_rejectFailed",
        "Failed to reject transfer: {msg}",
        { msg: translateApiErrorMessage(data?.errorCode, rawMsg) },
      ),
    );
  } finally {
    processingReject.value = false;
  }
};

// 移除转账标签
const removeTransferTag = async (transferId, tagId) => {
  if (!confirm(t("internalXfer_confirm_removeTransferTag"))) return;

  try {
    const response = await internalTransferApi.removeInternalTransferTag(
      transferId,
      tagId,
    );
    if (response.success) {
      await loadTransfers();
    }
  } catch (err) {
    const data = err.response?.data;
    const rawMsg = data?.message || err.message || "";
    alert(
      tParams(
        "internalXfer_alert_removeTransferTagFailed",
        "Failed to remove tag: {msg}",
        { msg: translateApiErrorMessage(data?.errorCode, rawMsg) },
      ),
    );
  }
};

// 切换每页显示行数
const handlePerPageChange = () => {
  currentPage.value = 1;
  loadTransfers();
};

// 切换页码
const changePage = (page) => {
  currentPage.value = page;
  loadTransfers();
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

const formatTransferAccountLabel = (transfer, direction) => {
  const prefix = direction === "from" ? "from" : "to";
  const groupLabel = String(
    transfer?.[`${prefix}GroupLabel`] || transfer?.groupLabel || "",
  ).trim();
  const accountNumber = String(
    transfer?.[`${prefix}AccountNumber`] || "",
  ).trim();

  if (groupLabel && accountNumber) {
    return `${groupLabel} (${accountNumber})`;
  }

  return groupLabel || accountNumber || t("internalXfer_na");
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
  if (normalized === "wallet") return t("depositMgmt_wallet");
  return shortCode || "";
};

const getPlatformIcon = (platformKey) => {
  const normalized = normalizePlatformKey(platformKey);
  if (normalized === "wallet") return "fas fa-wallet";
  if (normalized === "mt4" || normalized === "mt5") return "fas fa-chart-line";
  if (normalized === "financepro") return "fas fa-landmark";
  return "fas fa-server";
};

const getTransferPlatformMeta = (transfer, direction) => {
  const prefix = direction === "from" ? "from" : "to";
  const platformKey = normalizePlatformKey(
    transfer?.[`${prefix}GroupPlatformKey`] ||
      transfer?.[`${prefix}PlatformKey`] ||
      transfer?.[`${prefix}TradingPlatformKey`] ||
      transfer?.groupPlatformKey ||
      transfer?.platformKey,
  );
  const shortCode = transfer?.[`${prefix}PlatformShortCode`] || "";

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
  const keyByStatus = {
    pending: "depositMgmt_status_pending",
    processing: "depositMgmt_status_processing",
    completed: "depositMgmt_status_completed",
    rejected: "depositMgmt_status_rejected",
    failed: "depositMgmt_status_failed",
    cancelled: "depositMgmt_status_cancelled",
  };
  const key = keyByStatus[status];
  return key ? t(key) : status;
};

// 关闭dropdown when clicking outside
let exportDropdownHandler = null;

watch(showExportDropdown, (newVal) => {
  // 清理之前的事件监听器
  if (exportDropdownHandler) {
    document.removeEventListener("click", exportDropdownHandler);
    exportDropdownHandler = null;
  }

  if (newVal) {
    // 创建新的事件监听器
    exportDropdownHandler = (e) => {
      // 检查点击是否在下拉菜单或按钮内部
      const exportButton = e.target.closest(".btn-bulk-export");
      const exportDropdown = e.target.closest(".export-dropdown");

      // 如果点击在下拉菜单或按钮外部，关闭下拉菜单
      if (!exportButton && !exportDropdown) {
        showExportDropdown.value = false;
        document.removeEventListener("click", exportDropdownHandler);
        exportDropdownHandler = null;
      }
    };

    // 延迟添加事件监听器，避免立即触发
    setTimeout(() => {
      if (showExportDropdown.value && exportDropdownHandler) {
        document.addEventListener("click", exportDropdownHandler);
      }
    }, 10);
  }
});

// 页面加载
onMounted(async () => {
  await Promise.all([loadTransfers(), loadStatistics(), loadSearchTags()]);
});

// 组件卸载时清理事件监听器
onBeforeUnmount(() => {
  if (exportDropdownHandler) {
    document.removeEventListener("click", exportDropdownHandler);
    exportDropdownHandler = null;
  }
});
</script>

<style scoped>
.internal-transfer-management-page {
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
  position: relative;
  flex: 1;
  min-width: 300px;
}

.search-icon {
  position: absolute;
  left: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-faint);
  font-size: 16px;
}

.search-input-wrapper input {
  width: 100%;
  padding: 12px 45px 12px 45px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
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
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.clear-search {
  border: 0;
  background: transparent;
  position: absolute;
  right: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-faint);
  cursor: pointer;
  opacity: 0;
  transition: opacity 0.3s ease;
  font-size: 16px;
}

.clear-search.show {
  opacity: 1;
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
  font-size: 13px;
  color: var(--color-text);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.btn-add-tag {
  padding: 4px 12px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 12px;
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
  font-size: 13px;
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
  font-size: 13px;
  font-style: italic;
}

.transfer-table-container {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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

.table-header-left {
  display: flex;
  align-items: center;
  gap: 20px;
  flex: 1;
}

.table-header h2 {
  font-size: 18px;
  color: var(--color-ink);
  margin: 0;
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
  margin-bottom: 15px;
  display: block;
}

.error-container i {
  font-size: 48px;
  color: var(--color-danger);
  margin-bottom: 15px;
  display: block;
}

.transfer-table {
  width: 100%;
  border-collapse: collapse;
}

.transfer-table thead {
  background: var(--color-surface-soft);
}

.transfer-table thead {
  background: var(--color-surface-soft);
}

.transfer-table th {
  padding: 16px 20px;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
}

.transfer-table tbody tr {
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

.transfer-table tbody tr:hover {
  background: var(--color-surface-soft);
}

.transfer-table tbody tr.expanded {
  background: var(--color-brand-soft);
}

.transfer-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
}

.transfer-table th:nth-child(2),
.transfer-table td:nth-child(2) {
  width: 240px;
  min-width: 240px;
}

.checkbox-col {
  width: 50px;
  text-align: center !important;
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
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
  flex-shrink: 0;
}

.client-name {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
}

.client-id {
  font-size: 12px;
  color: var(--color-muted);
  margin-top: 2px;
}

.from-info,
.account-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.from-type-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  font-size: 12px;
  font-weight: 600;
}

.from-type-badge.available-balance {
  background: var(--color-success-soft);
  color: #38b2ac;
}

.platform-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  width: fit-content;
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

.account-number {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 13px;
}

.account-nickname {
  font-size: 12px;
  color: var(--color-muted);
}

.amount-display {
  font-size: 16px;
  font-weight: 700;
  color: var(--color-success);
}

.time-small {
  display: block;
  font-size: 11px;
  color: var(--color-faint);
  margin-top: 4px;
}

.pending-text {
  color: var(--color-faint);
  font-style: italic;
}

.status-badge {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.status-badge.processing {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.status-badge.completed {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.rejected,
.status-badge.failed,
.status-badge.cancelled {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.transfer-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.transfer-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  background: var(--color-brand-soft);
  color: var(--color-brand);
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
}

.transfer-tag-remove {
  border: 0;
  background: transparent;
  cursor: pointer;
  margin-left: 4px;
  font-weight: 700;
}

.transfer-tag-remove:hover {
  color: var(--color-danger);
}

.no-tags {
  color: var(--color-faint);
  font-size: 12px;
  font-style: italic;
}

.btn-action {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 13px;
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

.page-actions {
  display: flex;
  align-items: center;
  gap: 20px;
}
@media (max-width: 768px) {
  .internal-transfer-management-page {
    padding: 20px 15px;
  }

  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
  }

  .table-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .transfer-table {
    font-size: 12px;
  }

  .transfer-table th,
  .transfer-table td {
    padding: 12px 10px;
  }

  .pagination {
    flex-direction: column;
    align-items: stretch;
  }

  .pagination-info {
    margin-left: 0;
    text-align: center;
  }
}
</style>
