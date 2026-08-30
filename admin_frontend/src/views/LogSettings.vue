<template>
  <div class="log-settings-page">
    <div class="page-header">
      <div class="page-title">
        <h1>{{ t("page_logSettings_title") }}</h1>
        <p>{{ t("page_logSettings_sub") }}</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <div v-if="loading" class="ls-loading">
      <i class="fas fa-spinner fa-spin"></i>
      <p>{{ t("logSettings_loading") }}</p>
    </div>

    <div v-else-if="loadError" class="ls-error">
      <i class="fas fa-exclamation-circle"></i>
      <p>{{ loadError }}</p>
      <button type="button" class="ls-btn ls-btn--retry" @click="loadModules">
        <i class="fas fa-redo"></i> {{ t("logSettings_retry") }}
      </button>
    </div>

    <div v-else class="ls-table-wrap">
      <!-- 工具栏（结构/样式参考 IB List） -->
      <div class="ls-toolbar">
        <div class="ls-toolbar__left">
          <h2 class="ls-toolbar__title">
            {{ t("logSettings_toolbar_title") }}
          </h2>
          <div v-if="hasSelection && hasEditPermission" class="bulk-actions">
            <span class="bulk-actions-label">{{
              t("leads_bulkSelected")
            }}</span>
            <span class="bulk-actions-count">{{ selectedIds.length }}</span>
            <button
              type="button"
              class="btn-bulk btn-bulk-start"
              :disabled="actionLoading"
              @click="bulkSetStatus('running')"
            >
              <i class="fas fa-play"></i>
              {{ t("logSettings_bulk_start") }}
            </button>
            <button
              type="button"
              class="btn-bulk btn-bulk-stop"
              :disabled="actionLoading"
              @click="bulkSetStatus('stopped')"
            >
              <i class="fas fa-stop"></i>
              {{ t("logSettings_bulk_stop") }}
            </button>
          </div>
        </div>
        <div class="ls-toolbar__right">
          <div class="ls-rows">
            <label class="ls-rows__label">{{ t("leads_showRows") }}</label>
            <select
              v-model="perPage"
              class="ls-rows__select"
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

      <div class="ls-table-scroll">
        <table class="ls-table">
          <thead class="ls-table__head">
            <tr>
              <th class="ls-table__th ls-table__th--check">
                <input
                  type="checkbox"
                  :checked="isAllVisibleSelected"
                  :indeterminate.prop="
                    isSomeVisibleSelected && !isAllVisibleSelected
                  "
                  :aria-label="t('logSettings_ariaSelectAll')"
                  @change="toggleSelectAllVisible"
                />
              </th>
              <th class="ls-table__th">
                {{ t("logSettings_col_moduleName") }}
              </th>
              <th class="ls-table__th">
                {{ t("logSettings_col_lastStartStop") }}
              </th>
              <th class="ls-table__th">
                {{ t("logSettings_col_currentStatus") }}
              </th>
              <th class="ls-table__th ls-table__th--action">
                {{ t("logSettings_col_action") }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in visibleList"
              :key="row.id"
              class="ls-table__row"
              :class="{
                'ls-table__row--selected': selectedIds.includes(row.id),
              }"
            >
              <td class="ls-table__cell ls-table__cell--check">
                <input
                  type="checkbox"
                  :checked="selectedIds.includes(row.id)"
                  :aria-label="t('logSettings_ariaSelectRow')"
                  @change="toggleSelectRow(row.id)"
                  @click.stop
                />
              </td>
              <td class="ls-table__cell ls-table__cell--name">
                {{ displayModuleName(row) }}
              </td>
              <td class="ls-table__cell">{{ formatLastStartStop(row) }}</td>
              <td class="ls-table__cell">
                <span class="log-status-badge" :class="row.statusKey">
                  {{
                    row.statusKey === "running"
                      ? t("logSettings_status_running")
                      : t("logSettings_status_stopped")
                  }}
                </span>
              </td>
              <td class="ls-table__cell ls-table__cell--action">
                <button
                  v-if="row.statusKey === 'running' && hasEditPermission"
                  type="button"
                  class="btn-log-action btn-log-action--stop"
                  :disabled="actionLoading"
                  @click="setRowStatus(row.id, 'stopped')"
                >
                  {{ t("logSettings_btn_stop") }}
                </button>
                <button
                  v-else-if="row.statusKey !== 'running' && hasEditPermission"
                  type="button"
                  class="btn-log-action btn-log-action--start"
                  :disabled="actionLoading"
                  @click="setRowStatus(row.id, 'running')"
                >
                  {{ t("logSettings_btn_start") }}
                </button>
                <span v-else-if="!hasEditPermission" class="ls-readonly-hint"
                  >—</span
                >
              </td>
            </tr>
            <tr v-if="visibleList.length === 0" class="ls-table__row">
              <td colspan="5" class="ls-table__empty">
                {{ t("logSettings_empty") }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="pagination.total > 0" class="ls-pagination">
        <span class="ls-pagination__info">{{ paginationInfo }}</span>
        <div class="ls-pagination__btns">
          <button
            type="button"
            class="ls-btn ls-btn--pagination"
            :disabled="currentPage <= 1"
            @click="goToPage(currentPage - 1)"
          >
            <i class="fas fa-chevron-left"></i> {{ t("ibList_btnPrev") }}
          </button>
          <span class="ls-pagination__page">
            {{
              tParams("ibList_pageOf", "Page {current} of {total}", {
                current: String(currentPage),
                total: totalPagesText,
              })
            }}
          </span>
          <button
            type="button"
            class="ls-btn ls-btn--pagination"
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
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { useAuthStore } from "@/stores/auth";
import {
  fetchOperationLogModuleSettings,
  startOperationLogModule,
  stopOperationLogModule,
  bulkStartOperationLogModules,
  bulkStopOperationLogModules,
} from "@/services/operationLogModuleSettingsApi";

const { t, tParams, languageStore } = useAdminI18n();
const authStore = useAuthStore();

const hasEditPermission = computed(() =>
  authStore.hasPermission("page_logsettings_edit"),
);

const modules = ref([]);
const selectedIds = ref([]);
const perPage = ref(10);
const currentPage = ref(1);
const loading = ref(true);
const loadError = ref("");
const actionLoading = ref(false);
const serverPagination = ref({
  total: 0,
  total_pages: 1,
  page: 1,
  per_page: 10,
});

const filteredList = computed(() => modules.value);

const effectivePerPage = computed(() => {
  if (perPage.value === "all")
    return serverPagination.value.per_page || modules.value.length || 1;
  return Number(perPage.value) || 10;
});

const totalPages = computed(() => serverPagination.value.total_pages || 1);

const pagination = computed(() => ({
  total: serverPagination.value.total,
  total_pages: serverPagination.value.total_pages,
  page: serverPagination.value.page,
  per_page: serverPagination.value.per_page,
}));

const visibleList = computed(() => filteredList.value);

const hasSelection = computed(() => selectedIds.value.length > 0);

const isAllVisibleSelected = computed(() => {
  if (visibleList.value.length === 0) return false;
  return visibleList.value.every((row) => selectedIds.value.includes(row.id));
});

const isSomeVisibleSelected = computed(() =>
  visibleList.value.some((row) => selectedIds.value.includes(row.id)),
);

const totalPagesText = computed(() => String(totalPages.value));

const hasNextPage = computed(() => currentPage.value < totalPages.value);

const paginationInfo = computed(() => {
  const total = pagination.value.total;
  if (total === 0) return t("ibList_pagination_noRecords");
  if (perPage.value === "all") {
    return tParams("ibList_pagination_totalOnly", "Total {n} record(s)", {
      n: String(total),
    });
  }
  const from = (currentPage.value - 1) * effectivePerPage.value + 1;
  const to = Math.min(currentPage.value * effectivePerPage.value, total);
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

onMounted(() => {
  loadModules();
});

function displayModuleName(row) {
  return languageStore.currentLanguage === "zh"
    ? row.moduleNameZh || row.moduleNameEn || ""
    : row.moduleNameEn || row.moduleNameZh || "";
}

/** 后端已格式化为 Y-m-d H:i:s，直接展示 */
function formatLastStartStop(row) {
  return row.lastStartStopAt || "-";
}

async function loadModules() {
  loading.value = true;
  loadError.value = "";
  try {
    const res = await fetchOperationLogModuleSettings({
      page: currentPage.value,
      per_page: perPage.value === "all" ? "all" : perPage.value,
    });
    const data = res.data?.data ?? res.data;
    const items = data?.items ?? [];
    const pag = data?.pagination ?? {};
    modules.value = items.map((item) => ({
      ...item,
      statusKey: item.statusKey || (item.status === 1 ? "running" : "stopped"),
    }));
    serverPagination.value = {
      total: pag.total ?? items.length,
      total_pages: pag.total_pages ?? 1,
      page: pag.page ?? currentPage.value,
      per_page: pag.per_page ?? effectivePerPage.value,
    };
    const visibleIds = new Set(modules.value.map((m) => m.id));
    selectedIds.value = selectedIds.value.filter((id) => visibleIds.has(id));
  } catch (e) {
    loadError.value = e?.response?.data?.message || t("logSettings_loadFailed");
    modules.value = [];
  } finally {
    loading.value = false;
  }
}

function toggleSelectRow(id) {
  const idx = selectedIds.value.indexOf(id);
  if (idx > -1) selectedIds.value.splice(idx, 1);
  else selectedIds.value.push(id);
}

function toggleSelectAllVisible() {
  if (isAllVisibleSelected.value) {
    const visibleIds = new Set(visibleList.value.map((r) => r.id));
    selectedIds.value = selectedIds.value.filter((id) => !visibleIds.has(id));
  } else {
    const set = new Set(selectedIds.value);
    visibleList.value.forEach((row) => set.add(row.id));
    selectedIds.value = [...set];
  }
}

async function setRowStatus(id, status) {
  if (!hasEditPermission.value || actionLoading.value) return;
  actionLoading.value = true;
  try {
    if (status === "running") {
      await startOperationLogModule(id);
    } else {
      await stopOperationLogModule(id);
    }
    await loadModules();
  } catch (e) {
    window.alert(e?.response?.data?.message || t("logSettings_actionFailed"));
  } finally {
    actionLoading.value = false;
  }
}

async function bulkSetStatus(status) {
  if (
    !hasEditPermission.value ||
    actionLoading.value ||
    !selectedIds.value.length
  )
    return;
  actionLoading.value = true;
  try {
    const ids = [...selectedIds.value];
    if (status === "running") {
      await bulkStartOperationLogModules(ids);
    } else {
      await bulkStopOperationLogModules(ids);
    }
    selectedIds.value = [];
    await loadModules();
  } catch (e) {
    window.alert(e?.response?.data?.message || t("logSettings_actionFailed"));
  } finally {
    actionLoading.value = false;
  }
}

function onPageSizeChange() {
  currentPage.value = 1;
  loadModules();
}

function goToPage(page) {
  if (page < 1 || page > totalPages.value) return;
  currentPage.value = page;
  loadModules();
}
</script>

<style scoped>
.log-settings-page {
  padding: 40px 20px;
  max-width: 1600px;
  margin: 0 auto;
}

.ls-loading,
.ls-error {
  text-align: center;
  padding: 80px 20px;
  color: var(--color-muted);
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.ls-loading i,
.ls-error i {
  font-size: 32px;
  margin-bottom: 12px;
  display: block;
}

.ls-loading i {
  color: var(--color-brand);
}

.ls-error i {
  color: var(--color-danger);
}

.ls-btn--retry {
  margin-top: 16px;
  padding: 10px 20px;
  background: var(--color-brand-solid);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  cursor: pointer;
  font-weight: 600;
}

.ls-readonly-hint {
  color: var(--color-faint);
  font-size: 14px;
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

/* 表格区域（对齐 IB List .ir-list-table-wrap） */
.ls-table-wrap {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.ls-toolbar {
  padding: 20px 30px;
  background: var(--color-surface-soft);
  border-bottom: 2px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
}

.ls-toolbar__left {
  display: flex;
  align-items: center;
  gap: 20px;
  flex: 1;
  min-width: 0;
  flex-wrap: wrap;
}

.ls-toolbar__title {
  font-size: 18px;
  color: var(--color-ink);
  margin: 0;
  font-weight: 600;
}

.ls-toolbar__right {
  display: flex;
  align-items: center;
  gap: 15px;
  flex-shrink: 0;
}

.ls-rows {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: var(--color-text);
}

.ls-rows__label {
  white-space: nowrap;
}

.ls-rows__select {
  padding: 8px 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-size: 14px;
  cursor: pointer;
}

.ls-rows__select:focus {
  outline: none;
  border-color: var(--color-brand);
}

/* Bulk actions（与 IB List / Leads 一致） */
.bulk-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 15px;
  background: var(--color-brand-soft);
  border-radius: var(--radius-md);
  border: 2px solid var(--color-brand);
  flex-wrap: wrap;
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

.btn-bulk-start {
  background: linear-gradient(135deg, #3182ce 0%, #2b6cb0 100%);
  color: #fff;
  box-shadow: 0 2px 8px rgba(49, 130, 206, 0.35);
}

.btn-bulk-start:hover {
  background: linear-gradient(135deg, #2b6cb0 0%, var(--color-info) 100%);
  transform: translateY(-1px);
}

.btn-bulk-stop {
  background: linear-gradient(
    135deg,
    var(--color-danger) 0%,
    var(--color-danger) 100%
  );
  color: #fff;
  box-shadow: 0 2px 8px rgba(229, 62, 62, 0.35);
}

.btn-bulk-stop:hover {
  background: linear-gradient(135deg, var(--color-danger) 0%, #9b2c2c 100%);
  transform: translateY(-1px);
}

.ls-table-scroll {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.ls-table {
  width: 100%;
  border-collapse: collapse;
}

.ls-table__head {
  background: var(--color-surface-soft);
}

.ls-table__th {
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

.ls-table__th--action {
  text-align: center;
}

.ls-table__th--check,
.ls-table__cell--check {
  width: 44px;
  text-align: center;
  vertical-align: middle;
}

.ls-table__cell--check input[type="checkbox"] {
  cursor: pointer;
}

.ls-table__row {
  border-bottom: 1px solid var(--color-border);
  transition: background 0.2s;
}

.ls-table__row:hover {
  background: var(--color-surface-soft);
}

.ls-table__row--selected {
  background: var(--color-brand-soft);
}

.ls-table__row--selected:hover {
  background: var(--color-brand-soft);
}

.ls-table__cell {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
  vertical-align: middle;
}

.ls-table__cell--name {
  font-weight: 600;
  color: var(--color-ink);
}

.ls-table__cell--action {
  text-align: center;
  white-space: nowrap;
}

.ls-table__empty {
  text-align: center;
  color: var(--color-muted);
  padding: 40px;
}

.log-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
}

.log-status-badge::before {
  content: "";
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.log-status-badge.running {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.log-status-badge.running::before {
  background: var(--color-success-solid);
}

.log-status-badge.stopped {
  background: var(--color-surface-muted);
  color: var(--color-muted);
}

.log-status-badge.stopped::before {
  background: var(--color-faint);
}

.btn-log-action {
  padding: 8px 20px;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  background: var(--color-surface);
  transition: all 0.2s ease;
}

.btn-log-action--stop {
  color: var(--color-danger);
  border: 1px solid var(--color-danger);
}

.btn-log-action--stop:hover {
  background: var(--color-danger-soft);
}

.btn-log-action--start {
  color: var(--color-info);
  border: 1px solid #3182ce;
}

.btn-log-action--start:hover {
  background: var(--color-info-soft);
}

.ls-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
  padding: 16px 24px;
  background: var(--color-surface-soft);
  border-top: 1px solid var(--color-border);
}

.ls-pagination__info {
  font-size: 14px;
  color: var(--color-text);
}

.ls-pagination__btns {
  display: flex;
  align-items: center;
  gap: 12px;
}

.ls-pagination__page {
  font-size: 14px;
  color: var(--color-text);
}

.ls-btn--pagination {
  padding: 8px 14px;
  font-size: 14px;
  background: var(--color-border);
  color: var(--color-text);
  border: none;
  border-radius: var(--radius-md);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s ease;
}

.ls-btn--pagination:hover:not(:disabled) {
  background: var(--color-brand-solid);
  color: white;
}

.ls-btn--pagination:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .log-settings-page {
    padding: 20px 12px;
  }

  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }

  .ls-toolbar {
    flex-direction: column;
    align-items: stretch;
  }

  .ls-toolbar__right {
    justify-content: flex-end;
  }
}
</style>
