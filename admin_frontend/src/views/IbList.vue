<template>
  <div class="ir-list-container ui-page workspace-list-page">
    <div class="ir-list-page-header page-header ui-page-header">
      <div class="ir-list-page-title">
        <h1 class="ir-list-page-title__heading">
          {{ t("page_ibList_title") }}
        </h1>
        <p class="ir-list-page-title__desc">{{ t("page_ibList_sub") }}</p>
      </div>
      <div class="ir-list-page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <div v-if="loading" class="ir-list-loading">
      <i class="fas fa-spinner fa-spin ir-list-loading__icon"></i>
      <p class="ir-list-loading__text">{{ t("ibList_loading") }}</p>
    </div>

    <div v-else class="ir-list-table-wrap">
      <div class="ir-list-stats-header">
        <div>
          <h2 class="ir-list-stats-title">{{ t("ibList_stats_heading") }}</h2>
          <p class="ir-list-stats-desc">{{ t("ibList_stats_sub") }}</p>
        </div>
        <div class="ir-list-page-stats">
          <div class="ir-list-stat-badge">
            <i class="fas fa-handshake"></i>
            <span>{{
              tParams("ibList_stat_total", "{n} Total IBs", {
                n: listStats.totalIbs,
              })
            }}</span>
          </div>
          <div class="ir-list-stat-badge ir-list-stat-badge--active">
            <i class="fas fa-check-circle"></i>
            <span>{{
              tParams("ibList_stat_active", "{n} Active", {
                n: listStats.activeIbs,
              })
            }}</span>
          </div>
          <div class="ir-list-stat-badge ir-list-stat-badge--pending">
            <i class="fas fa-clock"></i>
            <span>{{
              tParams("ibList_stat_pending", "{n} Pending", {
                n: listStats.pendingIbs,
              })
            }}</span>
          </div>
        </div>
      </div>

      <div class="ir-list-toolbar">
        <div class="ir-list-toolbar__left">
          <h2 class="ir-list-toolbar__title">
            {{ t("ibList_toolbar_title") }}
          </h2>
          <!-- Bulk actions (same structure as Leads) -->
          <div
            v-if="hasSelection && hasNotificationsPermission"
            class="bulk-actions"
          >
            <span class="bulk-actions-label">{{
              t("leads_bulkSelected")
            }}</span>
            <span class="bulk-actions-count">{{ selectedIbIds.length }}</span>
            <button
              type="button"
              class="btn-bulk btn-bulk-notification"
              @click="openBulkSendNotificationModal"
              :title="t('ibList_bulkSendNotificationTitle')"
            >
              <span class="btn-bulk-notification-icon"
                ><i class="fas fa-bell"></i
              ></span>
              <span class="btn-bulk-notification-text">{{
                t("leads_bulkSendNotification")
              }}</span>
            </button>
          </div>
        </div>
        <div class="ir-list-toolbar__right">
          <input
            v-model="searchKeyword"
            type="text"
            class="ir-list-search"
            :placeholder="t('ibList_searchPlaceholder')"
            @keyup.enter="onSearch"
          />
          <button
            type="button"
            class="ir-list-btn ir-list-btn--search"
            @click="onSearch"
          >
            <i class="fas fa-search"></i> {{ t("ibList_btnSearch") }}
          </button>
          <div class="ir-list-rows">
            <label class="ir-list-rows__label">{{ t("leads_showRows") }}</label>
            <select
              v-model="perPage"
              class="ir-list-rows__select"
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

      <div ref="tableScrollWrapperRef" class="ir-list-table-scroll">
        <table class="ir-list-table">
          <thead class="ir-list-table__head">
            <tr>
              <th
                v-if="hasNotificationsPermission"
                class="ir-list-table__th ir-list-table__th--check"
              >
                <input
                  type="checkbox"
                  :checked="isAllVisibleSelected"
                  :indeterminate.prop="
                    isSomeVisibleSelected && !isAllVisibleSelected
                  "
                  @change="toggleSelectAllVisible"
                  :aria-label="t('ibList_ariaSelectAll')"
                />
              </th>
              <th class="ir-list-table__th">{{ t("ibList_col_ibName") }}</th>
              <th class="ir-list-table__th">{{ t("ibList_col_email") }}</th>
              <th class="ir-list-table__th">{{ t("ibList_col_phone") }}</th>
              <th class="ir-list-table__th">{{ t("ibList_col_ibType") }}</th>
              <th class="ir-list-table__th">{{ t("ibList_col_tierLevel") }}</th>
              <th class="ir-list-table__th">
                {{ t("ibList_col_totalClients") }}
              </th>
              <th class="ir-list-table__th ir-list-table__th--rule-name">
                {{ t("ibList_col_ruleName") }}
              </th>
              <th class="ir-list-table__th">
                {{ t("ibList_col_applicationDate") }}
              </th>
              <th class="ir-list-table__th">{{ t("ibList_col_status") }}</th>
              <th class="ir-list-table__th">
                {{ t("ibList_col_initialReviewer") }}
              </th>
              <th class="ir-list-table__th">
                {{ t("ibList_col_riskReviewer") }}
              </th>
              <th class="ir-list-table__th">
                {{ t("ibList_col_finalReviewer") }}
              </th>
              <th class="ir-list-table__th ir-list-table__th--sticky">
                {{ t("ibList_col_action") }}
              </th>
            </tr>
          </thead>
          <tbody>
            <template v-for="ib in visibleList" :key="ib.id">
              <tr
                class="ir-list-table__row"
                :class="{
                  'ir-list-table__row--child': (ib.depth || 0) > 0,
                  'ir-list-table__row--search-match': !!ib.searchMatch,
                  'ir-list-table__row--expanded': expandedRows.includes(ib.id),
                }"
              >
                <td
                  v-if="hasNotificationsPermission"
                  class="ir-list-table__cell ir-list-table__cell--check"
                >
                  <input
                    v-if="ib.userId != null"
                    type="checkbox"
                    :value="ib.id"
                    :checked="selectedIbIds.includes(ib.id)"
                    @change="toggleSelectIb(ib.id)"
                    @click.stop
                    :aria-label="t('ibList_ariaSelectRow')"
                  />
                  <span v-else class="ir-list-table__cell--check-empty">—</span>
                </td>
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
                          ? t('ibList_ariaCollapse')
                          : t('ibList_ariaExpand')
                      "
                    >
                      {{ isExpanded(ib.id) ? "−" : "+" }}
                    </span>
                    <span
                      v-else
                      class="ir-list-ib__toggle ir-list-ib__toggle--empty"
                    ></span>
                    <div class="ir-list-ib__main">
                      <router-link
                        v-if="canViewClientDetail && ib.userId != null"
                        class="ir-list-ib__name ir-list-ib__name--link"
                        :to="{
                          name: 'client-detail',
                          query: { id: ib.userId },
                        }"
                        >{{ ib.ibName || ib.companyName || "—" }}</router-link
                      >
                      <div v-else class="ir-list-ib__name">
                        {{ ib.ibName || ib.companyName || "—" }}
                      </div>
                      <div class="ir-list-ib__code">{{ ib.ibCode }}</div>
                      <div
                        v-if="ib.adminAlias"
                        class="ir-list-ib__alias"
                        :title="t('ibDetail_label_adminAlias', 'Admin Alias')"
                      >
                        {{ ib.adminAlias }}
                      </div>
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
                  <span
                    class="ir-list-status"
                    :class="statusClass(ib.status)"
                    >{{ ib.statusDisplay || formatIbStatus(ib.status) }}</span
                  >
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
                    type="button"
                    class="ir-list-btn-action ir-list-btn-action--detail ir-list-btn-action--icon-only"
                    @click="toggleRowExpansion(ib.id)"
                    :title="
                      expandedRows.includes(ib.id)
                        ? t('leads_btnHide')
                        : t('leads_btnDetail')
                    "
                    :aria-label="
                      expandedRows.includes(ib.id)
                        ? t('leads_btnHide')
                        : t('leads_btnDetail')
                    "
                  >
                    <i
                      :class="[
                        'fas',
                        expandedRows.includes(ib.id)
                          ? 'fa-chevron-up'
                          : 'fa-chevron-down',
                      ]"
                    ></i>
                  </button>
                  <router-link
                    v-if="canViewClientDetailProfile && ib.userId != null"
                    class="ir-list-btn-action ir-list-btn-action--client-detail ir-list-btn-action--icon-only"
                    :to="{
                      name: 'client-detail',
                      query: { id: ib.userId, source: ibListLogSubModule },
                    }"
                    :title="t('leads_btnDetail')"
                  >
                    <i class="fas fa-address-card"></i>
                  </router-link>
                  <button
                    v-if="hasViewAsClientPermission"
                    type="button"
                    class="ir-list-btn-action ir-list-btn-action--client ir-list-btn-action--icon-only"
                    @click.stop="handleViewAsClient(ib)"
                    :title="t('leads_titleViewAsClient')"
                  >
                    <i class="fas fa-user"></i>
                  </button>
                  <button
                    v-if="hasNotificationsPermission && ib.userId != null"
                    type="button"
                    class="ir-list-btn-action btn-notification ir-list-btn-action--icon-only"
                    @click.stop="openSendNotificationModal(ib)"
                    :title="t('leads_titleSendNotification')"
                  >
                    <i class="fas fa-bell"></i>
                  </button>
                </td>
              </tr>
              <tr
                class="ir-list-detail-row"
                :class="{ show: expandedRows.includes(ib.id) }"
              >
                <td :colspan="hasNotificationsPermission ? 14 : 13">
                  <div
                    class="ir-list-detail-content"
                    v-if="expandedRows.includes(ib.id)"
                  >
                    <IbDetailRow :row="ib" />
                  </div>
                </td>
              </tr>
            </template>
            <tr v-if="visibleList.length === 0" class="ir-list-table__row">
              <td
                :colspan="hasNotificationsPermission ? 14 : 13"
                class="ir-list-table__empty"
              >
                {{ t("ibList_empty") }}
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
            <i class="fas fa-chevron-left"></i> {{ t("ibList_btnPrev") }}
          </button>
          <span class="ir-list-pagination__page">{{
            tParams("ibList_pageOf", "Page {current} of {total}", {
              current: String(currentPage),
              total: totalPagesText,
            })
          }}</span>
          <button
            type="button"
            class="ir-list-btn ir-list-btn--pagination"
            :disabled="!hasNextPage"
            @click="goToPage(currentPage + 1)"
          >
            {{ t("ibList_btnNext") }} <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>

    <SendNotificationModal
      v-if="
        notificationRecipient ||
        (bulkNotificationRecipients && bulkNotificationRecipients.length)
      "
      v-model="showSendNotificationModal"
      :recipient="
        notificationRecipient ||
        (bulkNotificationRecipients && bulkNotificationRecipients[0])
      "
      :recipients="bulkNotificationRecipients"
      section-key="ib_list"
      :log-sub-module-key="ibListLogSubModule"
      @send="handleSendNotification"
    />
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { ref, computed, nextTick, onMounted, onUnmounted, watch } from "vue";
import { useRoute } from "vue-router";
import IbDetailRow from "@/components/ib/IbDetailRow.vue";
import SendNotificationModal from "@/components/client/SendNotificationModal.vue";
import ibPartnersApi from "@/services/ibPartnersApi";
import { notificationService } from "@/services/notificationService";
import leadsService from "@/services/leadsService";
import { useAuthStore } from "@/stores/auth";
import { CLIENT_DETAIL_PERMISSION_KEYS } from "@/utils/constants";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";
import { getSubModuleKey } from "@/config/operationLogPages";

const ibListLogSubModule = getSubModuleKey("page_ib_list");

const { t, tParams, languageStore } = useAdminI18n();

const route = useRoute();
const authStore = useAuthStore();
const hasViewAsClientPermission = computed(() =>
  authStore.hasPermission("page_iblist_view_as_client"),
);
const hasNotificationsPermission = computed(() =>
  authStore.hasPermission("page_iblist_notifications"),
);
// 能否进客户详情页：与路由守卫口径一致，8 个详情权限任一即可
const canViewClientDetail = computed(() =>
  CLIENT_DETAIL_PERMISSION_KEYS.some((key) => authStore.hasPermission(key)),
);
const canViewClientDetailProfile = computed(() =>
  authStore.hasPermission("page_clientsdetail_profile"),
);

const expandedRows = ref([]);
const loading = ref(true);
const list = ref([]);
const expandedIds = ref([]);
const selectedIbIds = ref([]);
const showSendNotificationModal = ref(false);
const notificationRecipient = ref(null);
const bulkNotificationRecipients = ref(null);
const searchKeyword = ref("");
const perPage = ref(10);
const currentPage = ref(1);
const tableScrollWrapperRef = ref(null);
const pagination = ref({
  total: 0,
  total_pages: 1,
  page: 1,
  per_page: 10,
});
const listStats = ref({
  totalIbs: 0,
  activeIbs: 0,
  pendingIbs: 0,
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

const hasSelection = computed(() => selectedIbIds.value.length > 0);

const isAllVisibleSelected = computed(() => {
  const visible = visibleList.value;
  const visibleWithUser = visible.filter((ib) => ib.userId != null);
  if (visibleWithUser.length === 0) return false;
  const set = new Set(selectedIbIds.value);
  return visibleWithUser.every((ib) => set.has(ib.id));
});

const isSomeVisibleSelected = computed(() => {
  const visible = visibleList.value;
  return visible.some(
    (ib) => ib.userId != null && selectedIbIds.value.includes(ib.id),
  );
});

const selectedIbRecipients = computed(() => {
  return visibleList.value
    .filter((ib) => selectedIbIds.value.includes(ib.id) && ib.userId != null)
    .map((ib) => ({
      id: Number(ib.userId),
      email: ib.email || "",
      firstName: ib.ibName || ib.companyName || "",
      lastName: "",
    }));
});

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
  if (total === 0) return t("ibList_pagination_noRecords");
  if (perPage.value === "all") {
    return tParams("ibList_pagination_totalOnly", "Total {n} record(s)", {
      n: String(total),
    });
  }
  const per = Number(pagination.value.per_page) || 10;
  const from = (currentPage.value - 1) * per + 1;
  const to = Math.min(currentPage.value * per, total);
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
  const t = phone.trim();
  return t || "—";
};

const formatTierLevel = (ib) => {
  const level = ib?.tierLevel;
  if (level !== undefined && level !== null && level !== "")
    return String(level);
  return "—";
};

const formatIbStatus = (status) => {
  if (!status || typeof status !== "string") return "—";
  const map = {
    pending_initial_review: "ibList_status_pending_initial_review",
    pending_risk_review: "ibList_status_pending_risk_review",
    pending_final_review: "ibList_status_pending_final_review",
    approved: "ibList_status_approved",
    rejected: "ibList_status_rejected",
  };
  const key = map[status];
  return key ? t(key) : status;
};

const statusClass = (status) => {
  if (!status) return "";
  if (status.includes("pending")) return "ir-list-status--pending";
  if (status === "approved") return "ir-list-status--approved";
  if (status === "rejected") return "ir-list-status--rejected";
  return "";
};

const syncDetailPanelWidth = () => {
  const wrapper = tableScrollWrapperRef.value;
  if (!wrapper) return;
  wrapper.style.setProperty("--detail-panel-width", `${wrapper.clientWidth}px`);
};

const scheduleListLayoutRecalc = () => {
  nextTick(() => {
    requestAnimationFrame(() => {
      syncDetailPanelWidth();
      requestAnimationFrame(() => syncDetailPanelWidth());
    });
  });
};

const loadList = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value === "all" ? "all" : perPage.value,
      search: searchKeyword.value || undefined,
    };
    const res = await ibPartnersApi.getAllStatusList(params);
    const payload = res.data?.data ?? res.data;
    const rawItems = payload?.items ?? payload ?? [];
    list.value = Array.isArray(rawItems) ? rawItems : [];
    const hasSearchMatch = rawItems.some((ib) => ib.searchMatch);
    if (hasSearchMatch) {
      const idToRow = {};
      for (const ib of rawItems) idToRow[ib.id] = ib;
      const expandParents = new Set();
      for (const ib of rawItems) {
        let pid = ib.parentId;
        while (pid != null) {
          expandParents.add(pid);
          const parentRow = idToRow[pid];
          pid = parentRow ? parentRow.parentId : null;
        }
      }
      expandedIds.value = Array.from(expandParents);
    } else {
      // 默认展开所有有子节点的 IB
      const allParentIds = (rawItems || [])
        .filter((ib) => ib.hasChildren)
        .map((ib) => ib.id);
      expandedIds.value = allParentIds;
    }
    const pag = payload?.pagination ?? res.data?.pagination;
    pagination.value = {
      total: pag?.total ?? 0,
      total_pages: Math.max(1, pag?.total_pages ?? 1),
      page: pag?.page ?? 1,
      per_page: pag?.per_page ?? 10,
    };
    const st = payload?.stats ?? res.data?.stats ?? {};
    listStats.value = {
      totalIbs: st.totalIbs ?? 0,
      activeIbs: st.activeIbs ?? 0,
      pendingIbs: st.pendingIbs ?? 0,
    };
  } catch (err) {
    list.value = [];
    expandedIds.value = [];
    pagination.value = { total: 0, total_pages: 1, page: 1, per_page: 10 };
    listStats.value = { totalIbs: 0, activeIbs: 0, pendingIbs: 0 };
  } finally {
    loading.value = false;
    scheduleListLayoutRecalc();
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

const toggleRowExpansion = (ibId) => {
  const index = expandedRows.value.indexOf(ibId);
  if (index > -1) {
    expandedRows.value.splice(index, 1);
  } else {
    expandedRows.value.push(ibId);
  }
  scheduleListLayoutRecalc();
};

function toggleSelectAllVisible() {
  const visible = visibleList.value;
  const withUser = visible.filter((ib) => ib.userId != null);
  if (isAllVisibleSelected.value) {
    selectedIbIds.value = selectedIbIds.value.filter(
      (id) => !withUser.some((ib) => ib.id === id),
    );
  } else {
    const existing = new Set(selectedIbIds.value);
    withUser.forEach((ib) => existing.add(ib.id));
    selectedIbIds.value = Array.from(existing);
  }
}

function toggleSelectIb(id) {
  const idx = selectedIbIds.value.indexOf(id);
  if (idx >= 0) {
    selectedIbIds.value = selectedIbIds.value
      .slice(0, idx)
      .concat(selectedIbIds.value.slice(idx + 1));
  } else {
    selectedIbIds.value = selectedIbIds.value.concat([id]);
  }
}

function openSendNotificationModal(ib) {
  if (ib.userId == null) {
    alert(t("ibList_alert_noUserNotify"));
    return;
  }
  notificationRecipient.value = {
    id: Number(ib.userId),
    email: ib.email || "",
    firstName: ib.ibName || ib.companyName || "",
    lastName: "",
  };
  bulkNotificationRecipients.value = null;
  showSendNotificationModal.value = true;
}

function openBulkSendNotificationModal() {
  const recipients = selectedIbRecipients.value;
  if (!recipients.length) {
    alert(t("ibList_alert_bulkNoRecipients"));
    return;
  }
  bulkNotificationRecipients.value = recipients;
  notificationRecipient.value = null;
  showSendNotificationModal.value = true;
}

function handleSendNotification(result) {
  if (result?.bulk) {
    const successCount = result.successCount ?? 0;
    const failCount = result.failCount ?? 0;
    const total = successCount + failCount;
    alert(
      tParams(
        "leads_notif_bulkDone",
        "Bulk notification finished. Sent: {success}, Failed: {fail} ({total} total).",
        {
          success: String(successCount),
          fail: String(failCount),
          total: String(total),
        },
      ),
    );
    showSendNotificationModal.value = false;
    bulkNotificationRecipients.value = null;
    selectedIbIds.value = [];
    loadList();
    return;
  }
  const payload = result?.payload ?? {};
  const recipient = result?.recipient ?? {};
  const response = result?.response ?? {};
  const types = [];
  if (payload.sendSystemNotification)
    types.push(t("leads_notif_channelSystem"));
  if (payload.sendEmail) types.push(t("leads_notif_channelEmail"));
  const typeDisplay = types.length
    ? types.join(" & ")
    : t("leads_notif_typeNa");
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  let message = response.message || t("leads_notif_processedOk");
  if (!response.message) {
    if (payload.scheduleType === "scheduled" && payload.scheduledAt) {
      const scheduledDate = new Date(payload.scheduledAt);
      const localTime = scheduledDate.toLocaleString(loc, {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        hour12: false,
      });
      message = tParams(
        "leads_notif_scheduledFor",
        "Notification scheduled for {time}!",
        { time: localTime },
      );
    } else {
      message = t("leads_notif_sentOk");
    }
  }
  const recipientDisplay =
    recipient.name ||
    recipient.email ||
    tParams("ibList_notif_recipientFallback", "IB #{n}", {
      n: String(payload.clientId || ""),
    });
  alert(
    tParams(
      "leads_notif_resultBlock",
      "{message}\nType: {type}\nRecipient: {recipient}",
      {
        message,
        type: typeDisplay,
        recipient: recipientDisplay,
      },
    ),
  );
  showSendNotificationModal.value = false;
  notificationRecipient.value = null;
}

watch(showSendNotificationModal, (visible) => {
  if (!visible) {
    notificationRecipient.value = null;
    bulkNotificationRecipients.value = null;
  }
});

const handleViewAsClient = async (ib) => {
  const clientUserId = ib.userId != null ? Number(ib.userId) : null;
  if (clientUserId == null) {
    alert(t("ibList_alert_noUserPreview"));
    return;
  }
  try {
    const res = await leadsService.createPreviewToken(
      clientUserId,
      ibListLogSubModule,
    );
    const url = res?.data?.previewUrl;
    if (!url) {
      alert(t("ibList_alert_previewUrlFailed"));
      return;
    }
    window.open(url, "_blank", "noopener,noreferrer");
  } catch (e) {
    const data = e?.response?.data ?? e;
    const rawMsg = data?.message || e?.message || t("ibList_previewFailed");
    const msg = translateApiErrorMessage(data?.errorCode, rawMsg);
    alert(msg);
  }
};

onMounted(async () => {
  if (route.query.search != null && route.query.search !== "") {
    searchKeyword.value = String(route.query.search);
  }
  await loadList();
  const detailId = route.query.detailId;
  if (detailId != null && detailId !== "") {
    const id = parseInt(detailId, 10);
    if (!Number.isNaN(id)) {
      const fromList = idToRow.value[id];
      if (fromList) {
        expandedRows.value.push(id);
      }
    }
  }
  scheduleListLayoutRecalc();
  window.addEventListener("resize", syncDetailPanelWidth);
});

onUnmounted(() => {
  window.removeEventListener("resize", syncDetailPanelWidth);
});
</script>

<style scoped>
.ir-list-container {
  max-width: 1600px;
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
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}
.ir-list-stats-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0;
  padding: 20px 30px;
  border-bottom: 2px solid var(--color-border);
  flex-wrap: wrap;
  gap: 15px;
}
.ir-list-stats-title {
  font-size: 20px;
  color: var(--color-ink);
  margin: 0 0 5px 0;
}
.ir-list-stats-desc {
  font-size: 14px;
  color: var(--color-muted);
  margin: 0;
}
.ir-list-page-stats {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
}
.ir-list-stat-badge {
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
.ir-list-stat-badge i {
  font-size: 16px;
}
.ir-list-stat-badge--active {
  background: var(--color-success-soft);
  color: var(--color-success);
}
.ir-list-stat-badge--pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}
.ir-list-table-scroll {
  --detail-panel-width: 100%;
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
.ir-list-toolbar__left {
  display: flex;
  align-items: center;
  gap: 20px;
  flex: 1;
  min-width: 0;
}
.ir-list-toolbar__title {
  font-size: 18px;
  color: var(--color-ink);
  margin: 0;
}
.ir-list-toolbar__right {
  display: flex;
  align-items: center;
  gap: 15px;
  flex-shrink: 0;
}
.ir-list-search {
  padding: 10px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  min-width: 260px;
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
.ir-list-rows {
  display: flex;
  align-items: center;
  gap: 10px;
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
  min-width: 1100px;
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
  color: var(--color-brand);
  cursor: pointer;
  border-radius: 4px;
  user-select: none;
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
/* 名字可点跳客户详情：仅有权限且有关联用户时渲染成链接 */
.ir-list-ib__name--link {
  display: inline-block;
  text-decoration: none;
  cursor: pointer;
}
.ir-list-ib__name--link:hover {
  color: var(--color-brand);
  text-decoration: underline;
}
.ir-list-ib__code {
  font-size: 14px;
  color: var(--color-muted);
  font-family: "Courier New", monospace;
}
.ir-list-ib__alias {
  font-size: 14px;
  color: var(--color-brand-strong);
  margin-top: 2px;
}
.ir-list-table__row--child .ir-list-ib__name {
  font-weight: 500;
}
.ir-list-table__row--search-match {
  background: var(--color-warning-soft);
  border-left: 4px solid var(--color-warning);
}
.ir-list-table__row--search-match:hover {
  background: var(--color-warning-soft);
}
.ir-list-table__row--search-match .ir-list-table__cell--sticky {
  background: var(--color-warning-soft);
}
.ir-list-table__row--search-match:hover .ir-list-table__cell--sticky {
  background: var(--color-warning-soft);
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
}
.ir-list-btn-action--icon-only {
  justify-content: center;
  width: 34px;
  height: 34px;
  padding: 0;
}
.ir-list-btn-action--detail {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}
.ir-list-btn-action--detail:hover {
  background: var(--color-brand-solid);
  color: white;
}
.ir-list-btn-action--client-detail {
  background: var(--color-surface-muted);
  color: var(--color-ink);
  text-decoration: none;
  margin-left: 8px;
}
.ir-list-btn-action--client-detail:hover {
  background: var(--color-border);
  transform: translateY(-2px);
}
.ir-list-btn-action--client {
  background: linear-gradient(
    135deg,
    var(--color-info-soft) 0%,
    var(--color-info-border) 100%
  );
  color: var(--color-info);
  margin-left: 8px;
}
.ir-list-btn-action--client:hover {
  background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
  color: #fff;
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

/* Bulk actions (same as Leads) */
.bulk-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 15px;
  background: var(--color-brand-soft);
  border-radius: var(--radius-md);
  border: 2px solid var(--color-brand);
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
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.btn-bulk-notification {
  background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
  color: #fff;
  box-shadow: 0 2px 8px rgba(13, 148, 136, 0.35);
}
.btn-bulk-notification:hover {
  background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(13, 148, 136, 0.4);
}
.btn-bulk-notification-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: var(--radius-sm);
  font-size: 14px;
}
.btn-bulk-notification-text {
  letter-spacing: 0.02em;
}

/* Row bell button：颜色/阴影区分语义，尺寸/padding 交给 .ir-list-btn-action--icon-only 统一管理 */
.btn-notification {
  background: linear-gradient(
    135deg,
    var(--color-warning) 0%,
    var(--color-warning) 100%
  );
  color: #fff;
  box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
  margin-left: 8px;
}
.btn-notification:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

/* Detail row (expandable) */
.ir-list-table__row--expanded {
  background: var(--color-brand-soft);
}
.ir-list-detail-row {
  display: none;
  position: relative;
  z-index: 1;
}
.ir-list-detail-row.show {
  display: table-row;
}
.ir-list-detail-row > td {
  padding: 0;
  white-space: normal;
  background: var(--color-surface-soft);
}
.ir-list-detail-content {
  padding: 30px;
  background: var(--color-surface-soft);
  width: var(--detail-panel-width, 100%);
  max-width: 100%;
  box-sizing: border-box;
  position: sticky;
  left: 0;
  z-index: 1;
}

/* Checkbox column */
.ir-list-table__th--check,
.ir-list-table__cell--check {
  width: 44px;
  text-align: center;
  vertical-align: middle;
}
.ir-list-table__cell--check input[type="checkbox"] {
  cursor: pointer;
}
.ir-list-table__cell--check-empty {
  color: var(--color-border-strong);
  font-size: 14px;
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
  .ir-list-page-actions {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>
