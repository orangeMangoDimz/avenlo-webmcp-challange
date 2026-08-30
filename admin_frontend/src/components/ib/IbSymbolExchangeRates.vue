<template>
  <div class="ib-exchange-rates">
    <div v-if="loading" class="loading-state">
      <i class="fas fa-spinner fa-spin ib-exchange-rates__spinner"></i>
      <p>{{ t("ibExRate_loading") }}</p>
    </div>

    <template v-else>
      <div v-if="listError" class="ib-exchange-rates__alert" role="alert">
        <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
        <span>{{ listError }}</span>
        <button
          type="button"
          class="ib-exchange-rates__alert-close"
          :aria-label="t('ibExRate_alert_dismiss')"
          @click="listError = ''"
        >
          <i class="fas fa-times" aria-hidden="true"></i>
        </button>
      </div>

      <div class="ib-exchange-rates__panel">
        <div class="ib-exchange-rates__header">
          <div class="ib-exchange-rates__header-text">
            <h2>{{ t("ibExRate_title") }}</h2>
            <p>{{ pollIntervalSubtitle }}</p>
          </div>
          <div class="ib-exchange-rates__header-controls">
            <div class="ib-exchange-rates__mode">
              <span class="ib-exchange-rates__mode-label">{{
                t("ibExRate_globalMode")
              }}</span>
              <div
                class="ib-exchange-rates__mode-toggle"
                v-if="canBatchUpdateMode"
              >
                <button
                  type="button"
                  class="ib-exchange-rates__mode-btn"
                  :class="{ active: globalSyncMode === 'auto' }"
                  :disabled="switchingGlobalMode"
                  @click="setGlobalSyncMode('auto')"
                >
                  {{ t("ibExRate_mode_auto") }}
                </button>
                <button
                  type="button"
                  class="ib-exchange-rates__mode-btn"
                  :class="{ active: globalSyncMode === 'manual' }"
                  :disabled="switchingGlobalMode"
                  @click="setGlobalSyncMode('manual')"
                >
                  {{ t("ibExRate_mode_manual") }}
                </button>
              </div>
              <span v-else class="ib-exchange-rates__mode-readonly">
                {{
                  globalSyncMode === "auto"
                    ? t("ibExRate_mode_auto")
                    : t("ibExRate_mode_manual")
                }}
              </span>
            </div>
            <div class="ib-exchange-rates__last-update">
              <span
                >{{ t("ibExRate_lastUpdate") }}:
                {{ lastListRefreshLabel }}</span
              >
            </div>
          </div>
        </div>

        <div class="ib-exchange-rates__toolbar">
          <div class="ib-exchange-rates__toolbar-left">
            <div class="ib-exchange-rates__filter-bar">
              <div class="ib-exchange-rates__search-field">
                <i
                  class="fas fa-search ib-exchange-rates__search-icon"
                  aria-hidden="true"
                ></i>
                <input
                  v-model="searchKeyword"
                  type="text"
                  class="ib-exchange-rates__search-input"
                  :class="{ 'has-clear': !!searchKeyword }"
                  :placeholder="t('ibExRate_searchPlaceholder')"
                  @keyup.enter="applySearch"
                />
                <button
                  v-if="searchKeyword"
                  type="button"
                  class="ib-exchange-rates__search-clear"
                  :aria-label="t('ibExRate_search_clear')"
                  @click="clearSearch"
                >
                  <i class="fas fa-times" aria-hidden="true"></i>
                </button>
              </div>
              <button
                type="button"
                class="ib-ex-btn ib-ex-btn--search"
                @click="applySearch"
              >
                <i class="fas fa-search" aria-hidden="true"></i>
                {{ t("ibExRate_btn_search") }}
              </button>
              <div
                class="ib-exchange-rates__filter-divider"
                aria-hidden="true"
              ></div>
              <div class="ib-exchange-rates__mode-filter">
                <label
                  class="ib-exchange-rates__mode-filter-label"
                  for="ib-ex-rate-sync-filter"
                >
                  {{ t("ibExRate_filter_mode") }}
                </label>
                <select
                  id="ib-ex-rate-sync-filter"
                  v-model="filterSyncMode"
                  class="ib-exchange-rates__mode-filter-select"
                  @change="onFilterChange"
                >
                  <option value="">{{ t("ibExRate_filter_all") }}</option>
                  <option value="auto">{{ t("ibExRate_mode_auto") }}</option>
                  <option value="manual">
                    {{ t("ibExRate_mode_manual") }}
                  </option>
                </select>
              </div>
            </div>
          </div>
          <div class="ib-exchange-rates__toolbar-right">
            <button
              type="button"
              class="ib-ex-btn ib-ex-btn--outline"
              :disabled="refreshing"
              @click="refreshList"
            >
              <i class="fas fa-sync-alt" :class="{ 'fa-spin': refreshing }"></i>
              {{ t("ibExRate_btn_refreshData") }}
            </button>
            <button
              v-if="canCreate"
              type="button"
              class="ib-ex-btn ib-ex-btn--primary"
              @click="openCreateModal"
            >
              <i class="fas fa-plus"></i> {{ t("ibExRate_btn_add") }}
            </button>
            <select
              v-model.number="pageSize"
              class="ib-exchange-rates__page-size-select"
              @change="onPageSizeChange"
            >
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
            </select>
          </div>
        </div>

        <div class="ib-exchange-rates__table-scroll">
          <table class="ib-exchange-rates__table">
            <thead>
              <tr>
                <th>{{ t("ibExRate_col_symbol") }}</th>
                <th>{{ t("ibExRate_col_rate") }}</th>
                <th>{{ t("ibExRate_col_updatedAt") }}</th>
                <th>{{ t("ibExRate_col_mode") }}</th>
                <th class="ib-exchange-rates__th--actions">
                  {{ t("ibExRate_col_actions") }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="pagedRates.length === 0">
                <td colspan="5" class="ib-exchange-rates__empty">
                  {{ t("ibExRate_empty") }}
                </td>
              </tr>
              <tr v-for="rate in pagedRates" :key="rate.id">
                <td>
                  <span class="ib-exchange-rates__symbol-name">{{
                    rate.symbol
                  }}</span>
                </td>
                <td class="ib-exchange-rates__td-rate">
                  {{ formatExchangeRateLine(rate) }}
                </td>
                <td class="ib-exchange-rates__td-time">
                  {{ formatDateTime(rate.updatedAt) }}
                </td>
                <td>
                  <span
                    class="ib-exchange-rates__mode-badge"
                    :class="rate.syncMode"
                  >
                    {{
                      rate.syncMode === "auto"
                        ? t("ibExRate_mode_autoShort")
                        : t("ibExRate_mode_manualShort")
                    }}
                  </span>
                </td>
                <td class="ib-exchange-rates__td-actions">
                  <div class="ib-exchange-rates__action-buttons">
                    <button
                      type="button"
                      class="ib-exchange-rates__action-btn ib-exchange-rates__action-btn--view"
                      :title="t('ibExRate_aria_view')"
                      @click="openViewModal(rate)"
                    >
                      <i class="fas fa-eye"></i>
                    </button>
                    <button
                      v-if="canEdit"
                      type="button"
                      class="ib-exchange-rates__action-btn ib-exchange-rates__action-btn--edit"
                      :title="t('ibExRate_aria_edit')"
                      @click="openEditModal(rate)"
                    >
                      <i class="fas fa-edit"></i>
                    </button>
                    <button
                      v-if="canDelete"
                      type="button"
                      class="ib-exchange-rates__action-btn ib-exchange-rates__action-btn--delete"
                      :title="t('ibExRate_aria_delete')"
                      @click="confirmDelete(rate)"
                    >
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div
          v-if="filteredRates.length > 0"
          class="ib-exchange-rates__pagination"
        >
          <span class="ib-exchange-rates__pagination-info">
            {{
              tParams("ibExRate_totalRecords", "Total {n} records", {
                n: filteredRates.length,
              })
            }}
          </span>
          <div class="ib-exchange-rates__pagination-btns">
            <button
              type="button"
              class="ib-ex-btn ib-ex-btn--page-nav"
              :disabled="currentPage <= 1"
              @click="goToPage(currentPage - 1)"
            >
              <i class="fas fa-chevron-left"></i>
            </button>
            <button
              v-for="page in visiblePages"
              :key="page"
              type="button"
              class="ib-ex-btn ib-ex-btn--page"
              :class="{ active: page === currentPage }"
              @click="goToPage(page)"
            >
              {{ page }}
            </button>
            <button
              type="button"
              class="ib-ex-btn ib-ex-btn--page-nav"
              :disabled="currentPage >= totalPages"
              @click="goToPage(currentPage + 1)"
            >
              <i class="fas fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
    </template>

    <IbSymbolExchangeRateModal
      v-if="showFormModal"
      :mode="formModalMode"
      :rate="editingRate"
      :symbols="symbolOptions"
      :global-sync-mode="globalSyncMode"
      :saving="saving"
      :save-error="formSaveError"
      @close="closeFormModal"
      @save="handleSave"
    />

    <IbSymbolExchangeRateViewModal
      v-if="showViewModal"
      :rate="viewingRate"
      @close="showViewModal = false"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import IbSymbolExchangeRateModal from "@/components/ib/IbSymbolExchangeRateModal.vue";
import IbSymbolExchangeRateViewModal from "@/components/ib/IbSymbolExchangeRateViewModal.vue";
import ibSettingsApi from "@/services/ibSettingsApi";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { translateApiErrorMessage } from "@/i18n/adminI18nBridge";

const POLL_INTERVAL_MS = 5 * 60 * 1000;

const props = defineProps({
  embedded: { type: Boolean, default: true },
  canBatchUpdateMode: { type: Boolean, default: false },
  canCreate: { type: Boolean, default: false },
  canEdit: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
});

const { t, tParams } = useAdminI18n();

const pollIntervalLabel = computed(() => {
  const totalSeconds = Math.round(POLL_INTERVAL_MS / 1000);
  if (totalSeconds <= 0) {
    return tParams("ibExRate_interval_seconds", "{n} seconds", { n: 0 });
  }
  if (totalSeconds % 3600 === 0) {
    const n = totalSeconds / 3600;
    return n === 1
      ? tParams("ibExRate_interval_hour", "{n} hour", { n })
      : tParams("ibExRate_interval_hours", "{n} hours", { n });
  }
  if (totalSeconds % 60 === 0) {
    const n = totalSeconds / 60;
    return n === 1
      ? tParams("ibExRate_interval_minute", "{n} minute", { n })
      : tParams("ibExRate_interval_minutes", "{n} minutes", { n });
  }
  return totalSeconds === 1
    ? tParams("ibExRate_interval_second", "{n} second", { n: totalSeconds })
    : tParams("ibExRate_interval_seconds", "{n} seconds", { n: totalSeconds });
});

const pollIntervalSubtitle = computed(() =>
  tParams(
    "ibExRate_sub",
    "Display supported symbol exchange rates. Data refreshes every {interval} in auto sync mode.",
    { interval: pollIntervalLabel.value },
  ),
);

const loading = ref(true);
const refreshing = ref(false);
const saving = ref(false);
const switchingGlobalMode = ref(false);
const rates = ref([]);
const symbolOptions = ref([]);
const globalSyncMode = ref("auto");
const lastListRefreshAt = ref(null);
const searchKeyword = ref("");
const appliedSearch = ref("");
const filterSyncMode = ref("");
const pageSize = ref(10);
const currentPage = ref(1);

const showFormModal = ref(false);
const formModalMode = ref("create");
const editingRate = ref(null);
const showViewModal = ref(false);
const viewingRate = ref(null);
const formSaveError = ref("");
const listError = ref("");

let pollTimer = null;

const resolveApiError = (error, fallbackCode) => {
  if (
    error &&
    typeof error === "object" &&
    (error.message || error.errorCode)
  ) {
    return translateApiErrorMessage(
      error.errorCode,
      error.message || t(`err_${fallbackCode}`),
    );
  }
  if (error?.message) return error.message;
  return t(`err_${fallbackCode}`);
};

const lastListRefreshLabel = computed(() => {
  if (!lastListRefreshAt.value) return "—";
  return formatDateTime(lastListRefreshAt.value);
});

const filteredRates = computed(() => {
  let list = [...rates.value];
  const keyword = appliedSearch.value.trim().toLowerCase();
  if (keyword) {
    list = list.filter((r) => (r.symbol || "").toLowerCase().includes(keyword));
  }
  if (filterSyncMode.value) {
    list = list.filter((r) => r.syncMode === filterSyncMode.value);
  }
  return list;
});

const totalPages = computed(() =>
  Math.max(1, Math.ceil(filteredRates.value.length / pageSize.value)),
);

const pagedRates = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value;
  return filteredRates.value.slice(start, start + pageSize.value);
});

const visiblePages = computed(() => {
  const total = totalPages.value;
  const current = currentPage.value;
  const pages = [];
  const maxVisible = 7;
  let start = Math.max(1, current - Math.floor(maxVisible / 2));
  let end = Math.min(total, start + maxVisible - 1);
  start = Math.max(1, end - maxVisible + 1);
  for (let i = start; i <= end; i += 1) pages.push(i);
  return pages;
});

const EXCHANGE_RATE_DECIMALS = 5;

const formatExchangeRateDisplay = (rate) =>
  new Intl.NumberFormat(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: EXCHANGE_RATE_DECIMALS,
  }).format(rate);

const formatExchangeRateLine = (row) => {
  if (!row) return "—";
  const n = Number(row.exchangeRate);
  if (row.exchangeRate == null || row.exchangeRate === "" || Number.isNaN(n))
    return "—";
  const code =
    row.exchangeRateCurrency || row.targetCurrency || row.baseCurrency || "USD";
  return tParams("txnSettings_rateLine", "1 USD = {rate} {code}", {
    rate: formatExchangeRateDisplay(n),
    code: String(code).trim(),
  }).replace(/\s+$/u, "");
};

const formatDateTime = (value) => {
  if (!value) return "—";
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
};

const applySearch = () => {
  appliedSearch.value = searchKeyword.value.trim();
  currentPage.value = 1;
  loadRates({ silent: true });
};

const clearSearch = () => {
  searchKeyword.value = "";
  appliedSearch.value = "";
  currentPage.value = 1;
  loadRates({ silent: true });
};

const onFilterChange = () => {
  currentPage.value = 1;
  loadRates({ silent: true });
};

const goToPage = (page) => {
  currentPage.value = Math.min(Math.max(1, page), totalPages.value);
};

const onPageSizeChange = () => {
  currentPage.value = 1;
};

const loadSymbolOptions = async () => {
  try {
    const res = await ibSettingsApi.getCustomSymbols();
    if (!res?.success || res.data == null) {
      symbolOptions.value = [];
      return;
    }
    const raw = res.data;
    const items = Array.isArray(raw) ? raw : raw.items || [];
    symbolOptions.value = items
      .filter((s) => Number(s.EnabledMark ?? s.enabledMark ?? 1) === 1)
      .map((s) => ({
        id: Number(s.id),
        symbolName: s.symbolName || s.symbol_name || "",
        currency: s.Currency || s.currency || "",
        tradingPlatformKey:
          s.trading_platforms_key || s.tradingPlatformKey || "",
      }))
      .filter((s) => s.id > 0 && s.symbolName)
      .sort((a, b) => a.symbolName.localeCompare(b.symbolName));
  } catch (error) {
    console.error("Failed to load custom symbols:", error);
    symbolOptions.value = [];
  }
};

const loadRates = async ({ silent = false } = {}) => {
  if (!silent) loading.value = true;
  else refreshing.value = true;
  try {
    const res = await ibSettingsApi.getSymbolExchangeRates({
      search: appliedSearch.value || undefined,
      syncMode: filterSyncMode.value || undefined,
    });
    if (res?.success) {
      const payload = res.data || {};
      rates.value = payload.items || payload.rates || [];
      if (payload.globalSyncMode) globalSyncMode.value = payload.globalSyncMode;
      lastListRefreshAt.value =
        payload.lastRefreshedAt || new Date().toISOString();
    }
  } catch (error) {
    console.error("Failed to load symbol exchange rates:", error);
  } finally {
    loading.value = false;
    refreshing.value = false;
  }
};

const refreshList = async () => {
  await loadRates({ silent: true });
};

const setGlobalSyncMode = async (mode) => {
  if (globalSyncMode.value === mode) return;
  switchingGlobalMode.value = true;
  listError.value = "";
  try {
    const res = await ibSettingsApi.setSymbolExchangeGlobalMode(mode);
    if (res?.success !== false) {
      await loadRates({ silent: true });
    }
  } catch (error) {
    listError.value = resolveApiError(error, "IB_EX_RATE_GLOBAL_MODE_FAILED");
  } finally {
    switchingGlobalMode.value = false;
    resetPollTimer();
  }
};

const openCreateModal = async () => {
  await loadSymbolOptions();
  formSaveError.value = "";
  formModalMode.value = "create";
  editingRate.value = null;
  showFormModal.value = true;
};

const openEditModal = async (rate) => {
  await loadSymbolOptions();
  formSaveError.value = "";
  formModalMode.value = "edit";
  editingRate.value = { ...rate };
  showFormModal.value = true;
};

const openViewModal = (rate) => {
  viewingRate.value = { ...rate };
  showViewModal.value = true;
};

const closeFormModal = () => {
  showFormModal.value = false;
  editingRate.value = null;
  formSaveError.value = "";
};

const handleSave = async (payload) => {
  saving.value = true;
  formSaveError.value = "";
  try {
    if (formModalMode.value === "create") {
      await ibSettingsApi.createSymbolExchangeRate(payload);
    } else {
      await ibSettingsApi.updateSymbolExchangeRate(
        editingRate.value.id,
        payload,
      );
    }
    closeFormModal();
    await loadRates({ silent: true });
  } catch (error) {
    formSaveError.value = resolveApiError(error, "IB_EX_RATE_SAVE_FAILED");
  } finally {
    saving.value = false;
  }
};

const confirmDelete = async (rate) => {
  if (
    !window.confirm(
      tParams("ibExRate_confirm_delete", "Delete exchange rate for {symbol}?", {
        symbol: rate.symbol,
      }),
    )
  ) {
    return;
  }
  listError.value = "";
  try {
    await ibSettingsApi.deleteSymbolExchangeRate(rate.id);
    await loadRates({ silent: true });
  } catch (error) {
    listError.value = resolveApiError(error, "IB_EX_RATE_DELETE_FAILED");
  }
};

const resetPollTimer = () => {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
  if (globalSyncMode.value === "auto") {
    pollTimer = setInterval(() => {
      refreshList();
    }, POLL_INTERVAL_MS);
  }
};

watch(globalSyncMode, () => {
  resetPollTimer();
});

onMounted(async () => {
  await Promise.all([loadSymbolOptions(), loadRates()]);
  resetPollTimer();
});

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer);
});
</script>

<style scoped>
.ib-exchange-rates__alert {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 16px;
  padding: 12px 14px;
  border-radius: var(--radius-md);
  background: var(--color-danger-soft);
  border: 1px solid var(--color-danger-border);
  color: var(--color-danger);
  font-size: 14px;
  line-height: 1.5;
}

.ib-exchange-rates__alert i.fa-exclamation-circle {
  margin-top: 2px;
  flex-shrink: 0;
}

.ib-exchange-rates__alert-close {
  margin-left: auto;
  border: none;
  background: transparent;
  color: var(--color-danger);
  cursor: pointer;
  padding: 2px 4px;
  flex-shrink: 0;
}

.ib-exchange-rates__panel {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.ib-exchange-rates__spinner {
  font-size: 32px;
  color: var(--color-brand);
}

.ib-exchange-rates__header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: flex-start;
  gap: 20px;
  padding: 24px 28px;
  border-bottom: 1px solid var(--color-border);
}

.ib-exchange-rates__header-text h2 {
  margin: 0 0 8px;
  font-size: 20px;
  font-weight: 700;
  color: var(--color-ink);
}

.ib-exchange-rates__header-text p {
  margin: 0;
  font-size: 14px;
  color: var(--color-muted);
  line-height: 1.5;
}

.ib-exchange-rates__header-controls {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 24px;
}

.ib-exchange-rates__mode {
  display: flex;
  align-items: center;
  gap: 12px;
}

.ib-exchange-rates__mode-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  white-space: nowrap;
}

.ib-exchange-rates__mode-toggle {
  display: inline-flex;
  padding: 4px;
  background: var(--color-surface-muted);
  border-radius: var(--radius-md);
}

.ib-exchange-rates__mode-btn {
  border: none;
  background: transparent;
  color: var(--color-text);
  font-size: 13px;
  font-weight: 600;
  padding: 8px 18px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.ib-exchange-rates__mode-btn.active {
  background: var(--color-brand-solid);
  color: #fff;
  box-shadow: 0 2px 6px rgba(var(--color-brand-rgb), 0.35);
}

.ib-exchange-rates__mode-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.ib-exchange-rates__mode-readonly {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  padding: 6px 12px;
  background: var(--color-surface-muted);
  border-radius: var(--radius-md);
}

.ib-exchange-rates__last-update {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  color: var(--color-muted);
  white-space: nowrap;
}

.ib-exchange-rates__toolbar {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 18px 28px;
  background: var(--color-surface);
  border-bottom: 1px solid var(--color-border);
}

.ib-exchange-rates__toolbar-left,
.ib-exchange-rates__toolbar-right {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.ib-exchange-rates__filter-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.ib-exchange-rates__search-field {
  position: relative;
  display: flex;
  align-items: center;
}

.ib-exchange-rates__search-icon {
  position: absolute;
  left: 12px;
  color: var(--color-faint);
  font-size: 13px;
  pointer-events: none;
  z-index: 1;
}

.ib-exchange-rates__search-input {
  width: min(280px, 52vw);
  padding: 9px 12px 9px 34px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  color: var(--color-ink);
  background: var(--color-surface);
  transition:
    border-color 0.2s,
    box-shadow 0.2s;
}

.ib-exchange-rates__search-input.has-clear {
  padding-right: 36px;
}

.ib-exchange-rates__search-input::placeholder {
  color: var(--color-faint);
}

.ib-exchange-rates__search-input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.12);
}

.ib-exchange-rates__search-clear {
  position: absolute;
  right: 8px;
  width: 22px;
  height: 22px;
  border: none;
  background: transparent;
  color: var(--color-faint);
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  transition:
    color 0.2s,
    background 0.2s;
}

.ib-exchange-rates__search-clear:hover {
  background: var(--color-surface-soft);
  color: var(--color-text);
}

.ib-exchange-rates__filter-divider {
  width: 1px;
  height: 24px;
  background: var(--color-border);
  flex-shrink: 0;
  margin: 0 2px;
}

.ib-exchange-rates__mode-filter {
  display: flex;
  align-items: center;
  gap: 8px;
}

.ib-exchange-rates__mode-filter-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  white-space: nowrap;
}

.ib-exchange-rates__mode-filter-select {
  min-width: 128px;
  padding: 9px 32px 9px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text);
  background-color: var(--color-surface);
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23718096' d='M2.5 4.5 6 8l3.5-3.5'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  appearance: none;
  cursor: pointer;
  transition:
    border-color 0.2s,
    box-shadow 0.2s;
}

.ib-exchange-rates__mode-filter-select:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.12);
}

.ib-ex-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 9px 16px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
  border: none;
}

.ib-ex-btn--search {
  background: var(--color-brand-solid);
  color: #fff;
}

.ib-ex-btn--search:hover {
  background: var(--color-brand-strong);
}

.ib-ex-btn--outline {
  background: var(--color-surface);
  color: var(--color-text);
  border: 1px solid var(--color-border);
}

.ib-ex-btn--outline:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.ib-ex-btn--primary {
  background: var(--color-brand-solid);
  color: #fff;
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.ib-ex-btn--primary:hover {
  background: var(--color-brand-strong);
}

.ib-ex-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.ib-exchange-rates__page-size-select {
  padding: 9px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  background: var(--color-surface);
  cursor: pointer;
  min-width: 64px;
}

.ib-exchange-rates__table-scroll {
  overflow-x: auto;
  width: 100%;
}

.ib-exchange-rates__table {
  width: 100%;
  min-width: 720px;
  border-collapse: collapse;
  table-layout: auto;
}

.ib-exchange-rates__table thead {
  background: var(--color-surface-soft);
}

.ib-exchange-rates__table th {
  padding: 16px 24px;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  border-bottom: 2px solid var(--color-border);
  white-space: nowrap;
}

.ib-exchange-rates__table tbody tr {
  border-bottom: 1px solid var(--color-border);
  transition: background 0.2s;
}

.ib-exchange-rates__table tbody tr:hover {
  background: var(--color-surface-soft);
}

.ib-exchange-rates__table td {
  padding: 16px 24px;
  font-size: 14px;
  color: var(--color-text);
  vertical-align: middle;
}

.ib-exchange-rates__td-rate {
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.ib-exchange-rates__td-time {
  white-space: nowrap;
  color: var(--color-muted);
}

.ib-exchange-rates__td-actions {
  width: 140px;
  text-align: center;
}

.ib-exchange-rates__th--actions {
  width: 140px;
  text-align: center;
}

.ib-exchange-rates__symbol-name {
  font-weight: 600;
  color: var(--color-ink);
  white-space: nowrap;
}

.ib-exchange-rates__mode-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
}

.ib-exchange-rates__mode-badge.auto {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.ib-exchange-rates__mode-badge.manual {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.ib-exchange-rates__empty {
  text-align: center;
  color: var(--color-faint);
  padding: 40px 16px !important;
}

.ib-exchange-rates__action-buttons {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.ib-exchange-rates__action-btn {
  width: 34px;
  height: 34px;
  border: none;
  border-radius: var(--radius-md);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  transition: all 0.2s;
}

.ib-exchange-rates__action-btn--view {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.ib-exchange-rates__action-btn--view:hover {
  background: var(--color-border);
  color: var(--color-ink);
}

.ib-exchange-rates__action-btn--edit {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.ib-exchange-rates__action-btn--edit:hover {
  background: var(--color-brand-solid);
  color: #fff;
}

.ib-exchange-rates__action-btn--delete {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.ib-exchange-rates__action-btn--delete:hover {
  background: var(--color-danger-solid);
  color: #fff;
}

.ib-exchange-rates__pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  padding: 16px 28px;
  border-top: 1px solid var(--color-border);
  background: var(--color-surface-soft);
}

.ib-exchange-rates__pagination-info {
  font-size: 13px;
  color: var(--color-muted);
  font-weight: 500;
}

.ib-exchange-rates__pagination-btns {
  display: flex;
  align-items: center;
  gap: 6px;
}

.ib-ex-btn--page,
.ib-ex-btn--page-nav {
  min-width: 36px;
  height: 36px;
  padding: 0 10px;
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
  border-radius: var(--radius-md);
}

.ib-ex-btn--page.active {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
  color: #fff;
}

.ib-ex-btn--page:hover:not(.active),
.ib-ex-btn--page-nav:hover:not(:disabled) {
  background: var(--color-surface-muted);
  border-color: var(--color-border-strong);
}

.ib-ex-btn--page-nav:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.loading-state {
  text-align: center;
  padding: 48px 16px;
  color: var(--color-muted);
}

@media (max-width: 900px) {
  .ib-exchange-rates__header {
    flex-direction: column;
  }

  .ib-exchange-rates__toolbar {
    flex-direction: column;
    align-items: stretch;
  }

  .ib-exchange-rates__toolbar-left,
  .ib-exchange-rates__toolbar-right {
    width: 100%;
    justify-content: flex-start;
  }

  .ib-exchange-rates__search-input {
    flex: 1;
    min-width: 0;
    width: auto;
  }
}
</style>
