<template>
  <div class="ir-list-container">
    <!-- Page Header -->
    <div class="ir-list-page-header">
      <div class="ir-list-page-title">
        <h1 class="ir-list-page-title__heading">
          {{ t("page_ibInitialReview_title") }}
        </h1>
        <p class="ir-list-page-title__desc">
          {{ t("page_ibInitialReview_sub") }}
        </p>
      </div>
      <div class="ir-list-page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- No permission -->
    <div v-if="!hasReadonlyPermission" class="ir-list-loading">
      <p class="ir-list-loading__text">{{ t("ibIr_noPermission") }}</p>
    </div>
    <!-- Loading State -->
    <div v-else-if="hasReadonlyPermission && loading" class="ir-list-loading">
      <i class="fas fa-spinner fa-spin ir-list-loading__icon"></i>
      <p class="ir-list-loading__text">{{ t("ibIr_loading") }}</p>
    </div>

    <!-- Table -->
    <div v-else class="ir-list-table-wrap">
      <div class="ir-list-toolbar">
        <div class="ir-list-toolbar__left">
          <h2 class="ir-list-toolbar__title">{{ t("ibIr_toolbar_title") }}</h2>
        </div>
        <div class="ir-list-toolbar__center">
          <input
            v-model="searchKeyword"
            type="text"
            class="ir-list-search"
            :placeholder="t('ibIr_searchPlaceholder')"
            @keyup.enter="onSearch"
          />
          <button
            type="button"
            class="ir-list-btn ir-list-btn--search"
            @click="onSearch"
          >
            <i class="fas fa-search"></i> {{ t("ibIr_btnSearch") }}
          </button>
        </div>
        <div class="ir-list-toolbar__right">
          <button
            v-if="hasIbInvitationPermission"
            type="button"
            class="ir-list-btn ir-list-btn--success"
            @click="openIbInvitation"
          >
            <i class="fas fa-envelope"></i> {{ t("ibIr_btnInvitation") }}
          </button>
          <button
            v-if="hasIbInvitationPermission"
            type="button"
            class="ir-list-btn ir-list-btn--success"
            @click="openCreateMultiIb"
          >
            <i class="fas fa-plus"></i> {{ t("ibIr_btnCreateMultiIb") }}
          </button>
          <div class="ir-list-rows">
            <label class="ir-list-rows__label">{{ t("ibIr_showRows") }}</label>
            <select
              v-model="perPage"
              class="ir-list-rows__select"
              @change="onPageSizeChange"
            >
              <option :value="5">{{ t("ibIr_rows_5") }}</option>
              <option :value="10">{{ t("ibIr_rows_10") }}</option>
              <option :value="20">{{ t("ibIr_rows_20") }}</option>
              <option value="all">{{ t("ibIr_rows_all") }}</option>
            </select>
          </div>
        </div>
      </div>

      <div class="ir-list-table-scroll">
        <table class="ir-list-table">
          <thead class="ir-list-table__head">
            <tr>
              <th class="ir-list-table__th">{{ t("ibIr_col_ibName") }}</th>
              <th class="ir-list-table__th">{{ t("ibIr_col_email") }}</th>
              <th class="ir-list-table__th">{{ t("ibIr_col_phone") }}</th>
              <th class="ir-list-table__th">{{ t("ibIr_col_ibType") }}</th>
              <th class="ir-list-table__th">{{ t("ibIr_col_tierLevel") }}</th>
              <th class="ir-list-table__th ir-list-table__th--rule-name">
                {{ t("ibIr_col_ruleName") }}
              </th>
              <th class="ir-list-table__th">
                {{ t("ibIr_col_applicationDate") }}
              </th>
              <th class="ir-list-table__th">{{ t("ibIr_col_status") }}</th>
              <th class="ir-list-table__th">
                {{ t("ibIr_col_initialReviewer") }}
              </th>
              <th class="ir-list-table__th">
                {{ t("ibIr_col_riskReviewer") }}
              </th>
              <th class="ir-list-table__th">
                {{ t("ibIr_col_finalReviewer") }}
              </th>
              <th class="ir-list-table__th ir-list-table__th--sticky">
                {{ t("ibIr_col_action") }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ib in list" :key="ib.id" class="ir-list-table__row">
              <td class="ir-list-table__cell">
                <div class="ir-list-ib">
                  <div class="ir-list-ib__name">
                    {{ ib.ibName || ib.companyName || "—" }}
                  </div>
                  <div class="ir-list-ib__code">{{ ib.ibCode }}</div>
                </div>
              </td>
              <td class="ir-list-table__cell">{{ ib.email || "—" }}</td>
              <td class="ir-list-table__cell">{{ formatPhone(ib.phone) }}</td>
              <td class="ir-list-table__cell">{{ ib.ibType || "—" }}</td>
              <td class="ir-list-table__cell">{{ formatTierLevel(ib) }}</td>
              <td
                class="ir-list-table__cell ir-list-table__cell--multiline ir-list-table__cell--rule-name"
              >
                {{ ib.ruleNames || "—" }}
              </td>
              <td class="ir-list-table__cell">
                {{ formatDate(ib.applicationDate) }}
              </td>
              <td class="ir-list-table__cell">
                <span class="ir-list-status" :class="statusClass(ib.status)">{{
                  ib.statusDisplay || formatIbStatus(ib.status)
                }}</span>
              </td>
              <td class="ir-list-table__cell">
                {{ ib.initialReviewerName || "—" }}
              </td>
              <td class="ir-list-table__cell">
                {{ ib.riskReviewerName || "—" }}
              </td>
              <td class="ir-list-table__cell">
                {{ ib.finalReviewerName || "—" }}
              </td>
              <td class="ir-list-table__cell ir-list-table__cell--sticky">
                <button
                  v-if="hasInitialReviewPermission"
                  type="button"
                  class="ir-list-btn-action ir-list-btn-action--detail"
                  @click="openInitialReviewModal(ib)"
                >
                  <i class="fas fa-clipboard-check"></i>
                  {{ t("ibIr_btn_initialReview") }}
                </button>
              </td>
            </tr>
            <tr v-if="list.length === 0" class="ir-list-table__row">
              <td colspan="12" class="ir-list-table__empty">
                {{ t("ibIr_empty") }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="ir-list-pagination" v-if="pagination.total > 0">
        <span class="ir-list-pagination__info">{{ paginationInfo }}</span>
        <div class="ir-list-pagination__btns">
          <button
            type="button"
            class="ir-list-btn ir-list-btn--pagination"
            :disabled="currentPage <= 1"
            @click="goToPage(currentPage - 1)"
          >
            <i class="fas fa-chevron-left"></i> {{ t("ibIr_pagination_prev") }}
          </button>
          <span class="ir-list-pagination__page">{{
            tParams("ibIr_pagination_pageOf", "Page {current} of {total}", {
              current: currentPage,
              total: totalPagesText,
            })
          }}</span>
          <button
            type="button"
            class="ir-list-btn ir-list-btn--pagination"
            :disabled="!hasNextPage"
            @click="goToPage(currentPage + 1)"
          >
            {{ t("ibIr_pagination_next") }} <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- IB Invitation Modal -->
    <NewIbInvitationModal
      v-if="showIbInvitationModal"
      :documents="documentTemplates"
      @close="showIbInvitationModal = false"
      @send="handleInvitationSent"
    />

    <!-- Create Multi IB Modal -->
    <CreateMultiIbModal
      v-if="showCreateMultiIbModal"
      @close="showCreateMultiIbModal = false"
      @created="handleCreateMultiIbSuccess"
    />

    <!-- Initial Review Modal -->
    <InitialReviewModal
      v-if="initialReviewRow"
      :row="initialReviewRow"
      @close="initialReviewRow = null"
      @success="handleInitialReviewSuccess"
    />
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import NewIbInvitationModal from "@/components/ib/NewIbInvitationModal.vue";
import CreateMultiIbModal from "@/components/ib/CreateMultiIbModal.vue";
import InitialReviewModal from "@/components/ib/InitialReviewModal.vue";
import ibPartnersApi from "@/services/ibPartnersApi";
import ibSettingsApi from "@/services/ibSettingsApi";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams, languageStore } = useAdminI18n();

const router = useRouter();
const authStore = useAuthStore();

const hasReadonlyPermission = computed(() =>
  authStore.hasPermission("page_ib_initial_review_readonly"),
);
const hasIbInvitationPermission = computed(() =>
  authStore.hasPermission("page_ib_initial_review_ib_invitation"),
);
const hasInitialReviewPermission = computed(() =>
  authStore.hasPermission("page_ib_initial_review_initial_review"),
);
const loading = ref(true);
const list = ref([]);
const searchKeyword = ref("");
const perPage = ref(10);
const currentPage = ref(1);
const pagination = ref({
  total: 0,
  total_pages: 1,
  page: 1,
  per_page: 10,
});
const showIbInvitationModal = ref(false);
const showCreateMultiIbModal = ref(false);
const documentTemplates = ref([]);
const initialReviewRow = ref(null);

const totalPagesText = computed(() => {
  const total = pagination.value.total_pages;
  if (total <= 0) return "1";
  return String(total);
});

const hasNextPage = computed(() => {
  return pagination.value.page < pagination.value.total_pages;
});

const paginationInfo = computed(() => {
  const total = pagination.value.total;
  if (total === 0) return t("ibIr_pagination_noRecords");
  if (perPage.value === "all")
    return tParams("ibIr_pagination_totalAll", "Total {total} record(s)", {
      total,
    });
  const per = Number(pagination.value.per_page) || 10;
  const from = (currentPage.value - 1) * per + 1;
  const to = Math.min(currentPage.value * per, total);
  return tParams("ibIr_pagination_showing", "Showing {from}-{to} of {total}", {
    from,
    to,
    total,
  });
});

const formatDate = (dateString) => {
  if (!dateString) return "—";
  const date = new Date(dateString);
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return date.toLocaleDateString(loc, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const formatPhone = (phone) => {
  if (!phone || typeof phone !== "string") return "—";
  const trimmed = phone.trim();
  return trimmed || "—";
};

/** Tier Level 列显示等级数字 1、2 等 */
const formatTierLevel = (ib) => {
  const level = ib?.tierLevel;
  if (level !== undefined && level !== null && level !== "")
    return String(level);
  return "—";
};

/** 数据库 status 为小写+下划线，转为页面显示：首字母大写+空格 */
const formatIbStatus = (status) => {
  if (!status || typeof status !== "string") return "—";
  const keyMap = {
    pending_initial_review: "ibIr_status_pending_initial_review",
    pending_risk_review: "ibIr_status_pending_risk_review",
    pending_final_review: "ibIr_status_pending_final_review",
    approved: "ibIr_status_approved",
    rejected: "ibIr_status_rejected",
  };
  const k = keyMap[status];
  return k
    ? t(k)
    : status.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
};

const statusClass = (status) => {
  if (!status) return "";
  const s = (status || "").toLowerCase();
  if (s.includes("approved")) return "ir-list-status--approved";
  if (s.includes("rejected")) return "ir-list-status--rejected";
  return "ir-list-status--pending";
};

const onSearch = () => {
  currentPage.value = 1;
  loadList();
};

const onPageSizeChange = () => {
  currentPage.value = 1;
  loadList();
};

const goToPage = (page) => {
  if (page < 1 || page > pagination.value.total_pages) return;
  currentPage.value = page;
  loadList();
};

const loadList = async () => {
  try {
    loading.value = true;
    const params = {
      page: currentPage.value,
      per_page: perPage.value === "all" ? "all" : perPage.value,
    };
    if (searchKeyword.value.trim()) {
      params.search = searchKeyword.value.trim();
    }
    const response = await ibPartnersApi.getInitialReviewList(params);
    if (response.success && response.data) {
      list.value = response.data.items || response.data || [];
      if (response.data.pagination) {
        pagination.value = {
          total: response.data.pagination.total ?? 0,
          total_pages: Math.max(1, response.data.pagination.total_pages ?? 1),
          page: response.data.pagination.page ?? 1,
          per_page: response.data.pagination.per_page ?? perPage.value,
        };
      }
    } else {
      list.value = [];
    }
  } catch (error) {
    console.error("Failed to load initial review list:", error);
    list.value = [];
    alert(t("ibIr_alert_loadListFailed"));
  } finally {
    loading.value = false;
  }
};

const loadDocumentTemplates = async () => {
  try {
    const response = await ibSettingsApi.getDocuments(true);
    if (response.success && response.data) {
      documentTemplates.value = response.data;
    }
  } catch (error) {
    console.error("Failed to load document templates:", error);
  }
};

const openIbInvitation = () => {
  showIbInvitationModal.value = true;
};

const openCreateMultiIb = () => {
  showCreateMultiIbModal.value = true;
};

const handleInvitationSent = ({ skipSign } = {}) => {
  showIbInvitationModal.value = false;
  if (skipSign) {
    loadList();
  }
  alert(t("ibIr_alert_invitationOk"));
};

const handleCreateMultiIbSuccess = () => {
  showCreateMultiIbModal.value = false;
  loadList();
  alert(t("ibIr_alert_createMultiIbOk"));
};

const openInitialReviewModal = (ib) => {
  initialReviewRow.value = ib;
};

const handleInitialReviewSuccess = () => {
  initialReviewRow.value = null;
  loadList();
  alert(t("ibIr_alert_reviewSubmitted"));
};

const goToDetail = (id) => {
  router.push({ name: "ib-partners", query: { detail: id } });
};

onMounted(async () => {
  await Promise.all([loadList(), loadDocumentTemplates()]);
});
</script>

<style scoped>
/* layout */
.ir-list-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 40px 20px;
}

.ir-list-page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.ir-list-page-title__heading {
  font-size: 28px;
  color: var(--color-ink);
  margin: 0 0 5px 0;
}

.ir-list-page-title__desc {
  font-size: 14px;
  color: var(--color-muted);
  margin: 0;
}

.ir-list-page-actions {
  display: flex;
  align-items: center;
  gap: 20px;
}

/* loading */
.ir-list-loading {
  text-align: center;
  padding: 100px 20px;
  color: var(--color-muted);
}

.ir-list-loading__icon {
  font-size: 32px;
  color: var(--color-brand);
}

.ir-list-loading__text {
  margin: 15px 0 0 0;
  color: var(--color-muted);
}

/* table wrap & toolbar */
.ir-list-table-wrap {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

/* 表格横向滚动，表头一行，Action 列固定在右侧 */
.ir-list-table-scroll {
  overflow-x: auto;
  overflow-y: visible;
  -webkit-overflow-scrolling: touch;
}

.ir-list-toolbar {
  padding: 20px 30px;
  background: var(--color-surface-soft);
  border-bottom: 2px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
}

.ir-list-toolbar__title {
  font-size: 18px;
  color: var(--color-ink);
  margin: 0;
}

.ir-list-toolbar__left {
  display: flex;
  align-items: center;
}

.ir-list-toolbar__center {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
  justify-content: center;
  min-width: 280px;
}

.ir-list-toolbar__right {
  display: flex;
  align-items: center;
  gap: 15px;
}

.ir-list-search {
  padding: 10px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  min-width: 260px;
  transition: border-color 0.2s;
}

.ir-list-search:focus {
  outline: none;
  border-color: var(--color-brand);
}

.ir-list-search::placeholder {
  color: var(--color-faint);
}

.ir-list-btn {
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

.ir-list-btn--search {
  background: var(--color-brand-solid);
  color: white;
}

.ir-list-btn--search:hover {
  background: var(--color-brand-strong);
}

.ir-list-btn--success {
  background: var(--color-success-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(72, 187, 120, 0.3);
}

.ir-list-btn--success:hover {
  background: var(--color-success-solid);
  transform: translateY(-2px);
}

.ir-list-rows {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: var(--color-text);
}

.ir-list-rows__label {
  font-weight: 600;
}

.ir-list-rows__select {
  padding: 8px 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  color: var(--color-ink);
  background: var(--color-surface);
  font-size: 14px;
  cursor: pointer;
}

/* table */
.ir-list-table {
  width: 100%;
  min-width: 1100px;
  border-collapse: separate;
  border-spacing: 0;
  background: var(--color-surface);
  table-layout: auto;
}

.ir-list-table__head {
  background: var(--color-surface-soft);
}

.ir-list-table__th {
  padding: 16px 20px;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
  white-space: nowrap;
}

/* Action 列固定在右侧，左侧细线+柔和内阴影提示还有列可横向滑动 */
.ir-list-table__th--sticky {
  position: sticky;
  right: 0;
  z-index: 2;
  background: var(--color-surface-soft);
  border-left: 1px solid var(--color-border);
  box-shadow: inset 12px 0 20px -10px rgba(0, 0, 0, 0.06);
}

.ir-list-table__row {
  border-bottom: 1px solid var(--color-border);
  transition: background 0.2s;
}

.ir-list-table__row:hover {
  background: var(--color-surface-soft);
}

.ir-list-table__row:hover .ir-list-table__cell--sticky {
  background: var(--color-surface-soft);
}

.ir-list-table__cell {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
  white-space: nowrap;
}

/* Rule Name 列允许多行显示，列宽加宽减少行数 */
.ir-list-table__cell--multiline {
  white-space: normal;
}

.ir-list-table__th--rule-name,
.ir-list-table__cell--rule-name {
  min-width: 260px;
  max-width: 360px;
}

.ir-list-table__cell--sticky {
  position: sticky;
  right: 0;
  z-index: 1;
  background: var(--color-surface);
  border-left: 1px solid var(--color-border);
  max-width: none;
  overflow: visible;
  box-shadow: inset 12px 0 20px -10px rgba(0, 0, 0, 0.06);
}

.ir-list-table__empty {
  text-align: center;
  color: var(--color-muted);
  padding: 40px;
}

.ir-list-ib {
  display: flex;
  flex-direction: column;
}

.ir-list-ib__name {
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 4px;
  font-size: 15px;
}

.ir-list-ib__code {
  font-size: 12px;
  color: var(--color-muted);
  font-family: "Courier New", monospace;
}

.ir-list-status {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.ir-list-status--pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.ir-list-status--approved {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.ir-list-status--rejected {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.ir-list-btn-action {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.ir-list-btn-action--detail {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.ir-list-btn-action--detail:hover {
  background: var(--color-brand-solid);
  color: white;
}

/* Pagination */
.ir-list-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
  padding: 16px 24px;
  background: var(--color-surface-soft);
  border-top: 1px solid var(--color-border);
}

.ir-list-pagination__info {
  font-size: 14px;
  color: var(--color-text);
}

.ir-list-pagination__btns {
  display: flex;
  align-items: center;
  gap: 12px;
}

.ir-list-pagination__page {
  font-size: 14px;
  color: var(--color-text);
}

.ir-list-btn--pagination {
  padding: 8px 14px;
  font-size: 13px;
  background: var(--color-border);
  color: var(--color-text);
}

.ir-list-btn--pagination:hover:not(:disabled) {
  background: var(--color-brand-solid);
  color: white;
}

.ir-list-btn--pagination:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .ir-list-container {
    padding: 20px 15px;
  }
  .ir-list-page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
  }
  .ir-list-toolbar {
    flex-direction: column;
    align-items: stretch;
  }
  .ir-list-toolbar__center {
    min-width: 0;
  }
  .ir-list-search {
    min-width: 0;
    width: 100%;
  }
  .ir-list-toolbar__right {
    flex-wrap: wrap;
  }
}
</style>
