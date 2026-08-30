<template>
  <div class="cth">
    <!-- Positions vs history: the two share most columns but not all, so each gets its own table -->
    <div class="cth-toolbar">
      <div class="cth-status-switch">
        <button
          v-for="option in statusOptions"
          :key="option.key"
          type="button"
          class="cth-status-btn"
          :class="{ active: status === option.key }"
          :disabled="loading"
          @click="selectStatus(option.key)"
        >
          {{ option.label }}
        </button>
      </div>

      <div class="cth-filters">
        <input
          v-model.trim="keywords"
          type="text"
          class="cth-input"
          :placeholder="
            t('clientTradingHistory_searchPlaceholder', 'Symbol or comment')
          "
          @keyup.enter="applyFilters"
        />
        <select v-model="side" class="cth-input cth-input--select">
          <option value="">
            {{ t("clientTradingHistory_allDirections", "All directions") }}
          </option>
          <option value="buy">
            {{ t("clientTradingHistory_buy", "Buy") }}
          </option>
          <option value="sell">
            {{ t("clientTradingHistory_sell", "Sell") }}
          </option>
        </select>
        <input
          v-model="periodFrom"
          type="date"
          class="cth-input"
          :title="dateFilterHint"
        />
        <input
          v-model="periodTo"
          type="date"
          class="cth-input"
          :title="dateFilterHint"
        />
        <button
          type="button"
          class="cth-btn"
          :disabled="loading"
          @click="applyFilters"
        >
          <i class="fas fa-search"></i>
          {{ t("common_search", "Search") }}
        </button>
        <button
          type="button"
          class="cth-btn cth-btn--ghost"
          :disabled="loading"
          @click="resetFilters"
        >
          {{ t("common_reset", "Reset") }}
        </button>
      </div>
    </div>

    <!-- Totals cover the whole filtered result set, not just the current page -->
    <div class="cth-summary">
      <div class="cth-summary-card">
        <span class="cth-summary-label">{{
          t("clientTradingHistory_totalLots", "Total Lots")
        }}</span>
        <span class="cth-summary-value">{{
          formatNumber(totals.totalLots || 0, 2)
        }}</span>
      </div>
      <div class="cth-summary-card">
        <span class="cth-summary-label">{{
          t("clientTradingHistory_netProfit", "Net Profit")
        }}</span>
        <span
          class="cth-summary-value"
          :class="amountClass(totals.netProfit)"
          >{{ formatCurrency(totals.netProfit || 0) }}</span
        >
      </div>
      <div class="cth-summary-card">
        <span class="cth-summary-label">{{
          t("clientTradingHistory_totalOrders", "Total Orders")
        }}</span>
        <span class="cth-summary-value">{{ totals.totalOrders || 0 }}</span>
      </div>
      <div class="cth-summary-card cth-summary-card--muted">
        <span class="cth-summary-label">{{
          t("clientTradingHistory_grossProfit", "Gross P/L")
        }}</span>
        <span
          class="cth-summary-value"
          :class="amountClass(totals.grossProfit)"
          >{{ formatCurrency(totals.grossProfit || 0) }}</span
        >
      </div>
      <div class="cth-summary-card cth-summary-card--muted">
        <span class="cth-summary-label">{{
          t("clientTradingHistory_commission", "Commission")
        }}</span>
        <span class="cth-summary-value">{{
          formatCurrency(totals.totalCommission || 0)
        }}</span>
      </div>
      <div class="cth-summary-card cth-summary-card--muted">
        <span class="cth-summary-label">{{
          t("clientTradingHistory_swap", "Swap")
        }}</span>
        <span class="cth-summary-value">{{
          formatCurrency(totals.totalSwap || 0)
        }}</span>
      </div>
    </div>

    <div class="cth-table-wrapper">
      <table class="cth-table">
        <thead>
          <tr>
            <th>{{ t("clientTradingHistory_thOrderId", "Order ID") }}</th>
            <th>{{ t("clientTradingHistory_thLogin", "Login") }}</th>
            <th>{{ t("clientTradingHistory_thAccount", "Account Number") }}</th>
            <th>{{ t("clientTradingHistory_thPlatform", "Platform") }}</th>
            <th>
              {{ t("clientTradingHistory_thAccountType", "Account Type") }}
            </th>
            <th>{{ t("clientTradingHistory_thCurrency", "Base Currency") }}</th>
            <th>{{ t("clientTradingHistory_thSymbol", "Symbol") }}</th>
            <th>{{ t("clientTradingHistory_thType", "Type") }}</th>
            <th>{{ t("clientTradingHistory_thLots", "Lots") }}</th>
            <th>{{ t("clientTradingHistory_thOpenTime", "Open Time") }}</th>
            <th>{{ t("clientTradingHistory_thOpenPrice", "Open Price") }}</th>
            <template v-if="isClosed">
              <th>{{ t("clientTradingHistory_thCloseTime", "Close Time") }}</th>
              <th>
                {{ t("clientTradingHistory_thClosePrice", "Close Price") }}
              </th>
            </template>
            <template v-else>
              <th>
                {{ t("clientTradingHistory_thCurrentPrice", "Current Price") }}
              </th>
              <th>{{ t("clientTradingHistory_thMargin", "Margin") }}</th>
            </template>
            <th>{{ t("clientTradingHistory_thSl", "S/L") }}</th>
            <th>{{ t("clientTradingHistory_thTp", "T/P") }}</th>
            <th>{{ t("clientTradingHistory_thCommission", "Commission") }}</th>
            <th>{{ t("clientTradingHistory_thSwap", "Swap") }}</th>
            <th>{{ t("clientTradingHistory_thTaxes", "Taxes") }}</th>
            <th>{{ t("clientTradingHistory_thProfit", "Profit/Loss") }}</th>
            <th>{{ t("clientTradingHistory_thNetProfit", "Net P/L") }}</th>
            <th v-if="isClosed">
              {{ t("clientTradingHistory_thReason", "Close Reason") }}
            </th>
            <th>{{ t("clientTradingHistory_thComment", "Comment") }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td :colspan="columnCount" class="cth-empty">
              <i class="fas fa-spinner fa-spin"></i>
              {{ t("common_loading", "Loading...") }}
            </td>
          </tr>
          <!-- A load failure must read differently from "no data", otherwise an error looks like an empty result -->
          <tr v-else-if="loadError">
            <td :colspan="columnCount" class="cth-empty cth-empty--error">
              <i class="fas fa-triangle-exclamation"></i>
              {{
                t(
                  "clientTradingHistory_loadFailed",
                  "Failed to load trading history.",
                )
              }}
              <button type="button" class="cth-retry-btn" @click="load">
                {{ t("common_retry", "Retry") }}
              </button>
            </td>
          </tr>
          <tr v-else-if="!items.length">
            <td :colspan="columnCount" class="cth-empty">
              {{ t("clientTradingHistory_empty", "No trading records found.") }}
            </td>
          </tr>
          <tr
            v-else
            v-for="row in items"
            :key="`${row.trading_platforms_key}-${row.Id}`"
          >
            <td>{{ row.Id ?? "--" }}</td>
            <td>{{ row.Login || "--" }}</td>
            <td>{{ row.AccountNumber || "--" }}</td>
            <td>{{ row.PlatformName || row.trading_platforms_key || "--" }}</td>
            <td>{{ row.AccountType || "--" }}</td>
            <td>{{ row.Currency || "--" }}</td>
            <td>{{ row.Symbol || "--" }}</td>
            <td>
              <span
                :class="[
                  'cth-badge',
                  isSellSide(row.Cmd) ? 'cth-badge--sell' : 'cth-badge--buy',
                ]"
              >
                {{ orderSideText(row.Cmd) }}
              </span>
            </td>
            <td>{{ formatNumber(row.Lots || 0, 2) }}</td>
            <td>{{ formatTimestamp(row.OpenTime) }}</td>
            <td>{{ formatPrice(row.OpenPrice, row.Digits) }}</td>
            <template v-if="isClosed">
              <td>{{ formatTimestamp(row.CloseTime) }}</td>
              <td>{{ formatPrice(row.ClosePrice, row.Digits) }}</td>
            </template>
            <template v-else>
              <td>
                {{
                  formatPrice(
                    isSellSide(row.Cmd) ? row.Ask : row.Bid,
                    row.Digits,
                  )
                }}
              </td>
              <td>{{ formatCurrency(row.Margin || 0) }}</td>
            </template>
            <td>{{ formatPrice(row.Sl, row.Digits) }}</td>
            <td>{{ formatPrice(row.Tp, row.Digits) }}</td>
            <td>{{ formatCurrency(row.Commission || 0) }}</td>
            <td>{{ formatCurrency(row.Storage || 0) }}</td>
            <td>{{ formatCurrency(row.Taxes || 0) }}</td>
            <td :class="amountClass(row.Profit)">
              {{ formatCurrency(row.Profit || 0) }}
            </td>
            <td :class="amountClass(row.NetProfit)">
              {{ formatCurrency(row.NetProfit || 0) }}
            </td>
            <td v-if="isClosed">{{ closeReasonText(row.Reason) }}</td>
            <td class="cth-comment">{{ row.Comment || "--" }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="totalPages > 1" class="cth-pagination">
      <button
        class="cth-page-btn"
        :disabled="page <= 1 || loading"
        @click="changePage(page - 1)"
      >
        <i class="fas fa-chevron-left"></i>
      </button>
      <span class="cth-page-info">{{ page }} / {{ totalPages }}</span>
      <button
        class="cth-page-btn"
        :disabled="page >= totalPages || loading"
        @click="changePage(page + 1)"
      >
        <i class="fas fa-chevron-right"></i>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { clientService } from "@/services/clientListService";
import { formatCurrency, formatNumber } from "@/utils/helpers";

const { t } = useAdminI18n();

const props = defineProps({
  clientId: {
    type: [Number, String],
    default: null,
  },
});

const PAGE_SIZE = 20;
const emptyTotals = () => ({
  totalOrders: 0,
  totalLots: 0,
  grossProfit: 0,
  totalCommission: 0,
  totalSwap: 0,
  totalTaxes: 0,
  netProfit: 0,
});

const status = ref("closed");
const keywords = ref("");
const side = ref("");
const periodFrom = ref("");
const periodTo = ref("");

const items = ref([]);
const totals = ref(emptyTotals());
const total = ref(0);
const page = ref(1);
const loading = ref(false);
const loadError = ref(false);

const statusOptions = computed(() => [
  {
    key: "closed",
    label: t("clientTradingHistory_closed", "Closed Positions"),
  },
  { key: "open", label: t("clientTradingHistory_open", "Open Positions") },
  {
    key: "pending",
    label: t("clientTradingHistory_pending", "Pending Orders"),
  },
]);

const isClosed = computed(() => status.value === "closed");
const totalPages = computed(() =>
  total.value > 0 ? Math.ceil(total.value / PAGE_SIZE) : 1,
);
// Column count changes between closed and open mode, so the empty-state colspan must follow it
const columnCount = computed(() => (isClosed.value ? 22 : 21));
const dateFilterHint = computed(() =>
  isClosed.value
    ? t("clientTradingHistory_dateHintClosed", "Filters by close time")
    : t("clientTradingHistory_dateHintOpen", "Filters by open time"),
);

/*
 * MT4/MT5 cmd covers more than plain buy/sell: 2-5 are limit/stop pending types and
 * 8/9 are stop-limit, all of which also appear on closed rows once they expire or fill.
 * Odd cmd values are the sell side. Same mapping the client portal uses (OrderHistory.vue).
 */
const ORDER_SIDES = {
  0: ["buy", "Buy"],
  1: ["sell", "Sell"],
  2: ["buy", "Buy Limit"],
  3: ["sell", "Sell Limit"],
  4: ["buy", "Buy Stop"],
  5: ["sell", "Sell Stop"],
  8: ["buy", "Buy Stop Limit"],
  9: ["sell", "Sell Stop Limit"],
};
const isSellSide = (cmd) => ORDER_SIDES[Number(cmd)]?.[0] === "sell";
const orderSideText = (cmd) => {
  const side = ORDER_SIDES[Number(cmd)];
  // Unknown cmd must stay visible rather than silently reading as "Buy"
  if (!side) return `#${cmd}`;
  return t(`clientTradingHistory_side${Number(cmd)}`, side[1]);
};

const amountClass = (value) => {
  const amount = Number(value || 0);
  if (amount > 0) return "cth-amount--up";
  if (amount < 0) return "cth-amount--down";
  return "";
};

// Platform timestamps are 10-digit seconds; 0 means not closed / no value
const formatTimestamp = (value) => {
  const seconds = Number(value || 0);
  if (!seconds) return "--";
  const date = new Date(seconds * 1000);
  if (Number.isNaN(date.getTime())) return "--";
  const pad = (num) => String(num).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
};

const formatPrice = (value, digits) => {
  if (value === null || value === undefined || value === "") return "--";
  const price = Number(value);
  if (Number.isNaN(price) || price === 0) return "--";
  const decimals = Number(digits);
  return price.toFixed(Number.isNaN(decimals) || decimals <= 0 ? 5 : decimals);
};

// MT4/MT5 close reason codes; an unknown code is echoed raw rather than swallowed
const CLOSE_REASONS = {
  0: "Client",
  1: "Expert",
  2: "Dealer",
  3: "Signal",
  4: "Gateway",
  5: "Mobile",
  6: "Web",
  7: "API",
};
const closeReasonText = (reason) => {
  if (reason === null || reason === undefined || reason === "") return "--";
  return CLOSE_REASONS[Number(reason)] || String(reason);
};

const load = async () => {
  const id = props.clientId;
  if (id === null || id === undefined || id === "") {
    items.value = [];
    totals.value = emptyTotals();
    total.value = 0;
    loadError.value = false;
    return;
  }

  loading.value = true;
  loadError.value = false;
  try {
    const params = {
      status: status.value,
      page: page.value,
      pageSize: PAGE_SIZE,
    };
    if (keywords.value) params.keywords = keywords.value;
    if (side.value) params.side = side.value;
    if (periodFrom.value) params.periodFrom = periodFrom.value;
    if (periodTo.value) params.periodTo = periodTo.value;

    const response = await clientService.getTradingHistory(id, params);
    const data = response?.data || {};
    items.value = Array.isArray(data.items) ? data.items : [];
    totals.value = { ...emptyTotals(), ...(data.totals || {}) };
    total.value = Number(data.pagination?.total || 0);
  } catch (error) {
    console.error("Failed to load client trading history:", error);
    loadError.value = true;
    items.value = [];
    totals.value = emptyTotals();
    total.value = 0;
  } finally {
    loading.value = false;
  }
};

const selectStatus = (nextStatus) => {
  if (status.value === nextStatus) return;
  status.value = nextStatus;
  page.value = 1;
  load();
};

const applyFilters = () => {
  page.value = 1;
  load();
};

const resetFilters = () => {
  keywords.value = "";
  side.value = "";
  periodFrom.value = "";
  periodTo.value = "";
  page.value = 1;
  load();
};

const changePage = (next) => {
  if (next < 1 || next > totalPages.value) return;
  page.value = next;
  load();
};

watch(
  () => props.clientId,
  () => {
    page.value = 1;
    load();
  },
  { immediate: true },
);
</script>

<style scoped>
/* Same padding as .table-panel, so the component is not flush when mounted straight onto the unpadded .tab-pane */
.cth {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
}
.cth-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.cth-status-switch {
  display: flex;
  gap: 6px;
}
.cth-status-btn {
  padding: 7px 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}
.cth-status-btn:hover:not(:disabled),
.cth-status-btn.active {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
  color: var(--color-brand);
}
.cth-status-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.cth-filters {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.cth-input {
  height: 32px;
  padding: 0 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 12px;
  color: var(--color-ink);
  background: var(--color-surface);
}
.cth-input--select {
  cursor: pointer;
}
.cth-btn {
  height: 32px;
  padding: 0 12px;
  border: 1px solid var(--color-brand);
  border-radius: var(--radius-sm);
  background: var(--color-brand-solid);
  color: #ffffff;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.cth-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.cth-btn--ghost {
  background: var(--color-surface);
  color: var(--color-text);
  border-color: var(--color-border);
}
.cth-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 12px;
}
.cth-summary-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface-soft);
}
.cth-summary-card--muted {
  background: var(--color-surface);
}
.cth-summary-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  color: var(--color-muted);
}
.cth-summary-value {
  font-size: 18px;
  font-weight: 700;
  color: var(--color-ink);
}
.cth-amount--up {
  color: var(--color-success);
}
.cth-amount--down {
  color: var(--color-danger);
}
.cth-table-wrapper {
  overflow-x: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}
.cth-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.cth-table th,
.cth-table td {
  padding: 10px 12px;
  text-align: left;
  border-bottom: 1px solid #f1f5f9;
  white-space: nowrap;
}
.cth-table th {
  background: var(--color-surface-soft);
  color: var(--color-muted);
  font-weight: 600;
  text-transform: uppercase;
  font-size: 11px;
}
.cth-table tbody tr:hover {
  background: var(--color-surface-soft);
}
.cth-empty {
  text-align: center;
  color: var(--color-faint);
  padding: 24px;
}
.cth-empty--error {
  color: var(--color-danger);
}
.cth-retry-btn {
  margin-left: 8px;
  border: 1px solid var(--color-danger);
  background: var(--color-surface);
  color: var(--color-danger);
  padding: 3px 10px;
  border-radius: var(--radius-sm);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
.cth-retry-btn:hover {
  background: var(--color-danger-solid);
  color: #ffffff;
}
.cth-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: var(--radius-md);
  font-size: 11px;
  font-weight: 600;
}
.cth-badge--buy {
  background: var(--color-success-soft);
  color: var(--color-success);
}
.cth-badge--sell {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}
.cth-comment {
  max-width: 220px;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cth-pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
}
.cth-page-btn {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  width: 32px;
  height: 32px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  color: var(--color-text);
}
.cth-page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.cth-page-info {
  font-size: 13px;
  color: var(--color-muted);
}
</style>
