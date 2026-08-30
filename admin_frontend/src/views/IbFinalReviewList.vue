<template>
  <div class="ir-list-container">
    <!-- Page Header -->
    <div class="ir-list-page-header">
      <div class="ir-list-page-title">
        <h1 class="ir-list-page-title__heading">
          {{ t("page_ibFinalReview_title") }}
        </h1>
        <p class="ir-list-page-title__desc">
          {{ t("page_ibFinalReview_sub") }}
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
    <div v-else-if="loading" class="ir-list-loading">
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
              <th class="ir-list-table__th">
                {{ t("ibFr_col_totalClients") }}
              </th>
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
            <tr
              v-for="ib in visibleList"
              :key="ib.id"
              class="ir-list-table__row"
              :class="{ 'ir-list-table__row--child': (ib.depth || 0) > 0 }"
            >
              <td class="ir-list-table__cell ir-list-table__cell--name">
                <div
                  class="ir-list-ib"
                  :style="{ paddingLeft: (ib.depth || 0) * 20 + 'px' }"
                >
                  <span
                    v-if="ib.hasChildren"
                    class="ir-list-ib__toggle"
                    @click="toggleExpand(ib.id)"
                    :aria-label="
                      isExpanded(ib.id)
                        ? t('ibFr_aria_collapse')
                        : t('ibFr_aria_expand')
                    "
                  >
                    {{ isExpanded(ib.id) ? "−" : "+" }}
                  </span>
                  <span
                    v-else
                    class="ir-list-ib__toggle ir-list-ib__toggle--empty"
                  ></span>
                  <div class="ir-list-ib__main">
                    <div class="ir-list-ib__name">
                      {{ ib.ibName || ib.companyName || "—" }}
                    </div>
                    <div class="ir-list-ib__code">{{ ib.ibCode }}</div>
                  </div>
                </div>
              </td>
              <td class="ir-list-table__cell">{{ ib.email || "—" }}</td>
              <td class="ir-list-table__cell">{{ formatPhone(ib.phone) }}</td>
              <td class="ir-list-table__cell">{{ ib.ibType || "—" }}</td>
              <td class="ir-list-table__cell">{{ formatTierLevel(ib) }}</td>
              <td class="ir-list-table__cell">{{ ib.totalClients ?? 0 }}</td>
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
                  v-if="hasApprovePermission"
                  type="button"
                  class="ir-list-btn-action ir-list-btn-action--approve"
                  :disabled="ib.status !== 'pending_final_review'"
                  @click="onApprove(ib)"
                >
                  <i class="fas fa-check"></i> {{ t("ibFr_btn_approve") }}
                </button>
                <button
                  v-if="hasRejectPermission"
                  type="button"
                  class="ir-list-btn-action ir-list-btn-action--reject"
                  :disabled="ib.status !== 'pending_final_review'"
                  @click="onReject(ib)"
                >
                  <i class="fas fa-times"></i> {{ t("ibRr_btn_reject") }}
                </button>
                <button
                  v-if="hasBindPermission"
                  type="button"
                  class="ir-list-btn-action ir-list-btn-action--bind"
                  :disabled="ib.status !== 'approved'"
                  @click="onBind(ib)"
                >
                  <i class="fas fa-link"></i> {{ t("ibFr_btn_bind") }}
                </button>
                <button
                  v-if="hasUnbindPermission"
                  type="button"
                  class="ir-list-btn-action ir-list-btn-action--unbind"
                  :disabled="
                    ib.status !== 'approved' || !(ib.boundChildCount > 0)
                  "
                  @click="onUnbind(ib)"
                >
                  <i class="fas fa-unlink"></i> {{ t("ibFr_btn_unbind") }}
                </button>
                <button
                  v-if="hasEditPermission"
                  type="button"
                  class="ir-list-btn-action ir-list-btn-action--edit"
                  :disabled="ib.status !== 'approved'"
                  @click="onEdit(ib)"
                >
                  <i class="fas fa-edit"></i> {{ t("ib_finalReview_btn_edit") }}
                </button>
              </td>
            </tr>
            <tr v-if="visibleList.length === 0" class="ir-list-table__row">
              <td colspan="13" class="ir-list-table__empty">
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

    <!-- Bind Relationship Modal -->
    <BindRelationshipModal
      v-if="bindRow"
      :row="bindRow"
      @close="bindRow = null"
      @success="handleBindSuccess"
    />

    <!-- Unbind Relationship Modal -->
    <UnbindRelationshipModal
      v-if="unbindRow"
      :row="unbindRow"
      @close="unbindRow = null"
      @success="handleUnbindSuccess"
    />

    <FinalReviewEditModal
      v-if="editRow"
      :row="editRow"
      @close="editRow = null"
      @success="handleEditSuccess"
    />
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import BindRelationshipModal from "@/components/ib/BindRelationshipModal.vue";
import UnbindRelationshipModal from "@/components/ib/UnbindRelationshipModal.vue";
import FinalReviewEditModal from "@/components/ib/FinalReviewEditModal.vue";
import { ref, computed, onMounted } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useAdminI18n } from "@/composables/useAdminI18n";
import ibPartnersApi from "@/services/ibPartnersApi";

const { t, tParams, languageStore } = useAdminI18n();

const authStore = useAuthStore();
const hasReadonlyPermission = computed(() =>
  authStore.hasPermission("page_ib_final_review_readonly"),
);
const hasApprovePermission = computed(() =>
  authStore.hasPermission("page_ib_final_review_approve"),
);
const hasRejectPermission = computed(() =>
  authStore.hasPermission("page_ib_final_review_reject"),
);
const hasBindPermission = computed(() =>
  authStore.hasPermission("page_ib_final_review_bind"),
);
const hasUnbindPermission = computed(() =>
  authStore.hasPermission("page_ib_final_review_unbind"),
);
const hasEditPermission = computed(() =>
  authStore.hasPermission("page_ib_final_review_edit"),
);

const bindRow = ref(null);
const unbindRow = ref(null);
const editRow = ref(null);
const loading = ref(true);
const list = ref([]);
const expandedIds = ref([]);
const searchKeyword = ref("");
const perPage = ref(10);
const currentPage = ref(1);
const pagination = ref({
  total: 0,
  total_pages: 1,
  page: 1,
  per_page: 10,
});

const idToRow = computed(() => {
  const map = {};
  for (const row of list.value) {
    const id = row.id != null ? Number(row.id) : null;
    if (id != null) map[id] = row;
  }
  return map;
});

const expandedSet = computed(() => new Set(expandedIds.value));

const visibleList = computed(() => {
  const expanded = expandedSet.value;
  const idMap = idToRow.value;
  function isVisible(row) {
    const depth = row.depth != null ? Number(row.depth) : 0;
    if (depth === 0) return true;
    const pid = row.parentId != null ? Number(row.parentId) : null;
    if (pid == null || !expanded.has(pid)) return false;
    const parent = idMap[pid];
    return parent ? isVisible(parent) : true;
  }
  return list.value.filter(isVisible);
});

function isExpanded(id) {
  const numId = id != null ? Number(id) : null;
  return numId != null && expandedSet.value.has(numId);
}

function toggleExpand(id) {
  const numId = id != null ? Number(id) : null;
  if (numId == null) return;
  const arr = expandedIds.value.slice();
  const idx = arr.indexOf(numId);
  if (idx >= 0) arr.splice(idx, 1);
  else arr.push(numId);
  expandedIds.value = arr;
}

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
  return k ? t(k) : String(status).replace(/_/g, " ");
};

const statusClass = (status) => {
  if (!status) return "";
  if (status.includes("pending")) return "ir-list-status--pending";
  if (status === "approved") return "ir-list-status--approved";
  if (status === "rejected") return "ir-list-status--rejected";
  return "";
};

const loadList = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value === "all" ? "all" : perPage.value,
      search: searchKeyword.value || undefined,
    };
    const res = await ibPartnersApi.getFinalReviewList(params);
    const payload = res.data?.data ?? res.data;
    const rawItems = payload?.items ?? payload ?? [];
    list.value = Array.isArray(rawItems) ? rawItems : [];
    expandedIds.value = [];
    const pag = payload?.pagination ?? res.data?.pagination;
    pagination.value = {
      total: pag?.total ?? 0,
      total_pages: Math.max(1, pag?.total_pages ?? 1),
      page: pag?.page ?? 1,
      per_page: pag?.per_page ?? 10,
    };
  } catch (err) {
    list.value = [];
    expandedIds.value = [];
    pagination.value = { total: 0, total_pages: 1, page: 1, per_page: 10 };
    alert(t("ibIr_alert_loadListFailed"));
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

const onApprove = async (ib) => {
  const name = ib.ibName || ib.companyName || ib.ibCode || "";
  if (
    !confirm(
      tParams(
        "ibFr_confirm_approve",
        'Approve IB "{name}"? Status will be set to Approved and an IB Code will be generated.',
        { name },
      ),
    )
  ) {
    return;
  }
  try {
    const response = await ibPartnersApi.approveIbPartner(ib.id);
    const raw = response?.data?.message || "";
    alert(raw || t("ibFr_alert_approveOk"));
    loadList();
  } catch (err) {
    const raw = err.response?.data?.message || err.message || "";
    alert(raw || t("ibFr_alert_approveFail"));
  }
};

const onReject = async (ib) => {
  const name = ib.ibName || ib.companyName || ib.ibCode || "";
  if (
    !confirm(
      tParams(
        "ibFr_confirm_reject",
        'Reject IB "{name}"? Status will revert to Pending Initial Review and you will be recorded as Final Reviewer.',
        { name },
      ),
    )
  ) {
    return;
  }
  try {
    const response = await ibPartnersApi.rejectIbPartner(ib.id, {
      logSubModuleKey: "ib_final",
    });
    const raw =
      response && response.data && response.data.message
        ? response.data.message
        : "";
    alert(raw || t("ibFr_alert_rejectOk"));
    loadList();
  } catch (err) {
    const raw = err.response?.data?.message || err.message || "";
    alert(raw || t("ibFr_alert_rejectFail"));
  }
};

const onBind = (ib) => {
  bindRow.value = ib;
};

const handleBindSuccess = () => {
  bindRow.value = null;
  loadList();
};

const onUnbind = (ib) => {
  unbindRow.value = ib;
};

const handleUnbindSuccess = () => {
  unbindRow.value = null;
  loadList();
};

const onEdit = (ib) => {
  editRow.value = ib;
};

const handleEditSuccess = () => {
  editRow.value = null;
  loadList();
};

onMounted(() => {
  loadList();
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

.ir-list-table__cell--name {
  min-width: 220px;
}

.ir-list-ib {
  display: flex;
  align-items: flex-start;
  gap: 0;
  min-height: 48px;
}

.ir-list-ib__toggle {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-right: 4px;
  font-size: 16px;
  font-weight: 700;
  line-height: 1;
  color: var(--color-brand);
  cursor: pointer;
  border-radius: 4px;
  user-select: none;
  transition:
    background 0.2s,
    color 0.2s;
}

.ir-list-ib__toggle:hover:not(.ir-list-ib__toggle--empty) {
  background: var(--color-brand-soft);
  color: var(--color-brand-strong);
}

.ir-list-ib__toggle--empty {
  cursor: default;
  visibility: hidden;
}

.ir-list-ib__main {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
}

.ir-list-ib__name {
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 4px;
  font-size: 15px;
}

.ir-list-ib__code {
  font-size: 14px;
  color: var(--color-muted);
  font-family: "Courier New", monospace;
}

.ir-list-table__row--child .ir-list-ib__name {
  font-weight: 500;
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
  background: var(--color-success-soft);
  color: var(--color-success);
}

.ir-list-status--rejected {
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

.ir-list-btn-action--approve:hover {
  background: var(--color-success-solid);
  color: white;
}

.ir-list-btn-action--reject {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.ir-list-btn-action--reject:hover {
  background: var(--color-danger-solid);
  color: white;
}

.ir-list-btn-action--bind {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.ir-list-btn-action--bind:hover {
  background: var(--color-brand-solid);
  color: white;
}

.ir-list-btn-action--unbind {
  background: var(--color-border);
  color: var(--color-text);
}

.ir-list-btn-action--unbind:hover {
  background: var(--color-muted);
  color: white;
}

.ir-list-btn-action--edit {
  background: var(--color-brand-soft);
  color: var(--color-brand-strong);
}

.ir-list-btn-action--edit:hover:not(:disabled) {
  background: var(--color-brand-soft);
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
