<template>
  <div class="ir-list-container ir-commission-order-page">
    <div class="ir-list-page-header">
      <div class="ir-list-page-title">
        <h1 class="ir-list-page-title__heading">
          {{ t("page_ibCommissionOrder_title") }}
        </h1>
        <p class="ir-list-page-title__desc">
          {{ t("page_ibCommissionOrder_sub") }}
        </p>
      </div>
      <div class="ir-list-page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <div v-if="!hasReadonlyPermission" class="ir-list-loading">
      <p class="ir-list-loading__text">{{ t("ibCo_noPermission") }}</p>
    </div>
    <div v-else-if="loading" class="ir-list-loading">
      <i class="fas fa-spinner fa-spin ir-list-loading__icon"></i>
      <p class="ir-list-loading__text">{{ t("ibCo_loading") }}</p>
    </div>

    <div v-else class="ir-list-table-wrap">
      <div class="ir-list-stats">
        <div class="stat-badge">
          <span>{{
            tParams("ibCo_stat_totalIbs", "{n} Total IBs", { n: summary.total })
          }}</span>
        </div>
        <div class="stat-badge pending">
          <span>{{
            tParams("ibCo_stat_pending", "{n} Pending", { n: summary.pending })
          }}</span>
        </div>
        <div class="stat-badge approved">
          <span>{{
            tParams("ibCo_stat_approved", "{n} Approved", {
              n: summary.approved,
            })
          }}</span>
        </div>
        <div class="stat-badge success">
          <span>{{
            tParams("ibCo_stat_completed", "{n} Completed", {
              n: summary.completed,
            })
          }}</span>
        </div>
        <div class="stat-badge cancelled">
          <span>{{
            tParams("ibCo_stat_cancelled", "{n} Cancelled", {
              n: summary.cancelled,
            })
          }}</span>
        </div>
      </div>
      <div class="ir-list-toolbar">
        <div class="ir-list-toolbar__left">
          <h2 class="ir-list-toolbar__title">{{ t("ibCo_toolbar_title") }}</h2>
        </div>
        <div class="ir-list-toolbar__center">
          <input
            v-model="searchKeyword"
            type="text"
            class="ir-list-search"
            :placeholder="t('ibCo_searchPlaceholder')"
            @keyup.enter="onSearch"
          />
          <select
            v-model="filterStatus"
            class="ir-list-rows__select"
            @change="onSearch"
          >
            <option value="">{{ t("ibCo_filter_allStatus") }}</option>
            <option value="pending">{{ t("ibCo_orderStatus_pending") }}</option>
            <option value="approved">
              {{ t("ibCo_orderStatus_approved") }}
            </option>
            <option value="completed">
              {{ t("ibCo_orderStatus_completed") }}
            </option>
            <option value="cancelled">
              {{ t("ibCo_orderStatus_cancelled") }}
            </option>
          </select>
          <button
            type="button"
            class="ir-list-btn ir-list-btn--search"
            @click="onSearch"
          >
            <i class="fas fa-search"></i> {{ t("ibCo_btn_search") }}
          </button>
        </div>
        <div class="ir-list-toolbar__right">
          <div class="ir-list-rows">
            <label class="ir-list-rows__label">{{ t("ibCo_showRows") }}</label>
            <select
              v-model="perPage"
              class="ir-list-rows__select"
              @change="onPageSizeChange"
            >
              <option :value="5">{{ t("ibCo_perPage_5") }}</option>
              <option :value="10">{{ t("ibCo_perPage_10") }}</option>
              <option :value="20">{{ t("ibCo_perPage_20") }}</option>
              <option value="all">{{ t("ibCo_perPage_all") }}</option>
            </select>
          </div>
        </div>
      </div>

      <div class="ir-list-table-scroll">
        <table class="ir-list-table">
          <thead class="ir-list-table__head">
            <tr>
              <th class="ir-list-table__th">{{ t("ibCo_col_ibName") }}</th>
              <th class="ir-list-table__th">{{ t("ibCo_col_email") }}</th>
              <th class="ir-list-table__th">{{ t("ibCo_col_tierLevel") }}</th>
              <th class="ir-list-table__th ir-list-table__th--rule-name">
                {{ t("ibCo_col_ruleName") }}
              </th>
              <th class="ir-list-table__th">{{ t("ibCo_col_commission") }}</th>
              <th class="ir-list-table__th">{{ t("ibCo_col_orderDate") }}</th>
              <th class="ir-list-table__th">{{ t("ibCo_col_statusDate") }}</th>
              <th class="ir-list-table__th">{{ t("ibCo_col_payoutDate") }}</th>
              <th class="ir-list-table__th">{{ t("ibCo_col_status") }}</th>
              <th class="ir-list-table__th ir-list-table__th--sticky">
                {{ t("ibCo_col_action") }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in list" :key="row.id" class="ir-list-table__row">
              <td class="ir-list-table__cell ir-list-table__cell--name">
                {{ row.ibName || "—" }}
              </td>
              <td class="ir-list-table__cell">{{ row.email || "—" }}</td>
              <td class="ir-list-table__cell">
                {{ formatTierLevel(row.tierLevel) }}
              </td>
              <td
                class="ir-list-table__cell ir-list-table__cell--multiline ir-list-table__cell--rule-name"
              >
                {{ row.ruleName || "—" }}
              </td>
              <td class="ir-list-table__cell">
                {{ formatCommission(row.commission) }}
              </td>
              <td class="ir-list-table__cell">
                {{ formatDate(row.orderDate) }}
              </td>
              <td class="ir-list-table__cell">
                {{ formatDate(row.statusDate) }}
              </td>
              <td class="ir-list-table__cell">
                {{ formatDate(row.payoutDate) }}
              </td>
              <td class="ir-list-table__cell">
                <span class="ir-list-status" :class="statusClass(row.status)">{{
                  statusDisplay(row.status)
                }}</span>
              </td>
              <td class="ir-list-table__cell ir-list-table__cell--sticky">
                <button
                  v-if="hasApprovePermission"
                  type="button"
                  class="ir-list-btn-action ir-list-btn-action--approve"
                  :disabled="row.status !== 'pending'"
                  @click="onApprove(row)"
                >
                  <i class="fas fa-check"></i> {{ t("ibCo_btn_approve") }}
                </button>
                <button
                  v-if="hasCompletePermission"
                  type="button"
                  class="ir-list-btn-action ir-list-btn-action--complete"
                  :disabled="row.status !== 'approved'"
                  @click="onComplete(row)"
                >
                  <i class="fas fa-check-double"></i>
                  {{ t("ibCo_btn_complete") }}
                </button>
                <button
                  v-if="hasCancelPermission"
                  type="button"
                  class="ir-list-btn-action ir-list-btn-action--cancel"
                  :disabled="row.status !== 'pending'"
                  @click="onCancel(row)"
                >
                  <i class="fas fa-times"></i> {{ t("ibCo_btn_cancelOrder") }}
                </button>
              </td>
            </tr>
            <tr v-if="list.length === 0" class="ir-list-table__row">
              <td colspan="10" class="ir-list-table__empty">
                {{ t("ibCo_empty") }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

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
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import { ref, computed, onMounted } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useAdminI18n } from "@/composables/useAdminI18n";
import {
  getCommissionOrderList,
  approveCommissionOrder,
  completeCommissionOrder,
  cancelCommissionOrder,
} from "@/services/commissionOrdersApi";

const { t, tParams, languageStore } = useAdminI18n();

const authStore = useAuthStore();
const hasReadonlyPermission = computed(() =>
  authStore.hasPermission("page_ib_commission_order_readonly"),
);
const hasApprovePermission = computed(() =>
  authStore.hasPermission("page_ib_commission_order_approve"),
);
const hasCompletePermission = computed(() =>
  authStore.hasPermission("page_ib_commission_order_complete"),
);
const hasCancelPermission = computed(() =>
  authStore.hasPermission("page_ib_commission_order_cancel"),
);

const loading = ref(true);
const list = ref([]);
const searchKeyword = ref("");
const filterStatus = ref("");
const perPage = ref(10);
const currentPage = ref(1);
const pagination = ref({
  total: 0,
  total_pages: 1,
  page: 1,
  per_page: 10,
});
const summary = ref({
  total: 0,
  pending: 0,
  approved: 0,
  completed: 0,
  cancelled: 0,
});

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

function formatDate(dateString) {
  if (!dateString) return "—";
  const date = new Date(dateString);
  if (Number.isNaN(date.getTime())) return "—";
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return date.toLocaleString(loc, {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hour12: false,
  });
}

function formatTierLevel(tierLevel) {
  if (tierLevel !== undefined && tierLevel !== null && tierLevel !== "")
    return String(tierLevel);
  return "—";
}

function formatCommission(commission) {
  if (commission === undefined || commission === null) return "—";
  const num = Number(commission);
  if (Number.isNaN(num)) return "—";
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return (
    "$" +
    num.toLocaleString(loc, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  );
}

function statusDisplay(status) {
  if (!status || typeof status !== "string") return "—";
  const keyMap = {
    pending: "ibCo_orderStatus_pending",
    approved: "ibCo_orderStatus_approved",
    completed: "ibCo_orderStatus_completed",
    cancelled: "ibCo_orderStatus_cancelled",
  };
  const k = keyMap[status];
  return k ? t(k) : String(status).replace(/_/g, " ");
}

function statusClass(status) {
  if (!status) return "";
  if (status === "pending") return "ir-list-status--pending";
  if (status === "approved") return "ir-list-status--approved";
  if (status === "completed") return "ir-list-status--completed";
  if (status === "cancelled") return "ir-list-status--cancelled";
  return "";
}

const loadList = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value === "all" ? "all" : perPage.value,
      search: searchKeyword.value || undefined,
      status: filterStatus.value || undefined,
    };
    const res = await getCommissionOrderList(params);
    const payload = res.data?.data ?? res.data;
    const rawItems = payload?.items ?? payload ?? [];
    list.value = Array.isArray(rawItems) ? rawItems : [];
    const pag = payload?.pagination ?? res.data?.pagination;
    pagination.value = {
      total: pag?.total ?? 0,
      total_pages: Math.max(1, pag?.total_pages ?? 1),
      page: pag?.page ?? 1,
      per_page: pag?.per_page ?? 10,
    };
    const sum = payload?.summary ?? {};
    summary.value = {
      total: sum.total ?? 0,
      pending: sum.pending ?? 0,
      approved: sum.approved ?? 0,
      completed: sum.completed ?? 0,
      cancelled: sum.cancelled ?? 0,
    };
  } catch (err) {
    list.value = [];
    pagination.value = { total: 0, total_pages: 1, page: 1, per_page: 10 };
    summary.value = {
      total: 0,
      pending: 0,
      approved: 0,
      completed: 0,
      cancelled: 0,
    };
    alert(t("ibCo_alert_loadListFailed"));
  } finally {
    loading.value = false;
  }
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

const onApprove = async (row) => {
  if (
    !confirm(
      tParams(
        "ibCo_confirm_approve",
        "Approve this commission order (ID: {id})?",
        { id: row.id },
      ),
    )
  )
    return;
  try {
    await approveCommissionOrder(row.id);
    alert(t("ibCo_alert_approveOk"));
    loadList();
  } catch (err) {
    const raw = err.response?.data?.message || err.message || "";
    alert(raw || t("ibCo_alert_approveFail"));
  }
};

const onComplete = async (row) => {
  if (
    !confirm(
      tParams(
        "ibCo_confirm_complete",
        "Mark this commission order (ID: {id}) as completed (payout done)?",
        { id: row.id },
      ),
    )
  )
    return;
  try {
    await completeCommissionOrder(row.id);
    alert(t("ibCo_alert_completeOk"));
    loadList();
  } catch (err) {
    const raw = err.response?.data?.message || err.message || "";
    alert(raw || t("ibCo_alert_completeFail"));
  }
};

const onCancel = async (row) => {
  if (
    !confirm(
      tParams(
        "ibCo_confirm_cancel",
        "Cancel this commission order (ID: {id})?",
        { id: row.id },
      ),
    )
  )
    return;
  try {
    await cancelCommissionOrder(row.id);
    alert(t("ibCo_alert_cancelOk"));
    loadList();
  } catch (err) {
    const raw = err.response?.data?.message || err.message || "";
    alert(raw || t("ibCo_alert_cancelFail"));
  }
};

onMounted(() => {
  loadList();
});
</script>

<style scoped>
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

.ir-list-table-wrap {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.ir-list-stats {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 15px;
  padding: 16px 30px;
  background: var(--color-surface-soft);
  border-bottom: 1px solid var(--color-border);
}

.ir-list-stats .stat-badge {
  background: var(--color-brand-soft);
  color: var(--color-brand);
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
}

.ir-list-stats .stat-badge.pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.ir-list-stats .stat-badge.approved {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.ir-list-stats .stat-badge.success {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.ir-list-stats .stat-badge.cancelled {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.ir-list-table-scroll {
  overflow-x: auto;
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

.ir-list-toolbar__left,
.ir-list-toolbar__center,
.ir-list-toolbar__right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.ir-list-toolbar__center {
  flex: 1;
  justify-content: center;
  min-width: 280px;
}

.ir-list-search {
  padding: 10px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  color: var(--color-ink);
  background: var(--color-surface);
  font-size: 14px;
  min-width: 200px;
}

.ir-list-search:focus {
  outline: none;
  border-color: var(--color-brand);
}

.ir-list-btn {
  padding: 12px 20px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
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

.ir-list-rows__label {
  font-weight: 600;
  font-size: 14px;
  color: var(--color-text);
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

.ir-list-table {
  width: 100%;
  min-width: 1000px;
  border-collapse: separate;
  border-spacing: 0;
  background: var(--color-surface);
}

.ir-list-table__head {
  background: var(--color-surface-soft);
}

.ir-list-table__th {
  padding: 16px 20px;
  text-align: left;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
  white-space: nowrap;
}

.ir-list-table__th--sticky {
  position: sticky;
  right: 0;
  z-index: 2;
  width: 280px;
  min-width: 280px;
  max-width: 280px;
  background: var(--color-surface-soft);
  border-left: 1px solid var(--color-border);
  box-shadow: inset 12px 0 20px -10px rgba(0, 0, 0, 0.06);
}

.ir-list-table__th--rule-name,
.ir-list-table__cell--rule-name {
  min-width: 180px;
  max-width: 280px;
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

.ir-list-table__cell--multiline {
  white-space: normal;
}

.ir-list-table__cell--sticky {
  position: sticky;
  right: 0;
  z-index: 1;
  width: 280px;
  min-width: 280px;
  max-width: 280px;
  background: var(--color-surface);
  border-left: 1px solid var(--color-border);
  box-shadow: inset 12px 0 20px -10px rgba(0, 0, 0, 0.06);
  white-space: nowrap;
}

.ir-list-table__cell--name {
  min-width: 160px;
}

.ir-list-table__empty {
  text-align: center;
  color: var(--color-muted);
  padding: 40px;
}

.ir-list-status {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
}

.ir-list-status--pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.ir-list-status--approved {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.ir-list-status--completed {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.ir-list-status--cancelled {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.ir-list-btn-action {
  padding: 8px 14px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-right: 6px;
  margin-bottom: 4px;
}

.ir-list-btn-action:last-child {
  margin-right: 0;
}

.ir-list-btn-action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  pointer-events: none;
}

.ir-list-btn-action--approve {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.ir-list-btn-action--approve:hover:not(:disabled) {
  background: var(--color-success-solid);
  color: white;
}

.ir-list-btn-action--complete {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.ir-list-btn-action--complete:hover:not(:disabled) {
  background: var(--color-info-solid);
  color: white;
}

.ir-list-btn-action--cancel {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.ir-list-btn-action--cancel:hover:not(:disabled) {
  background: var(--color-danger-solid);
  color: white;
}

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
  font-size: 14px;
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
</style>
