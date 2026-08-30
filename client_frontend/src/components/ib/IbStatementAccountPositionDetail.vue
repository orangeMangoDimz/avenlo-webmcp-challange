<template>
  <div class="stmt-pos-detail">
    <div class="stmt-pos-detail__header">
      <router-link
        to="/client/ib-client-position"
        class="stmt-pos-detail__link"
      >
        {{ t("ibStatement_detail_viewFull", "View full history") }}
        <i class="fas fa-arrow-right"></i>
      </router-link>
      <div class="stmt-pos-detail__cards">
        <div class="stmt-pos-detail__card">
          <span class="stmt-pos-detail__card-label">{{
            t("ibStatement_detail_totalLots", "Total Lots")
          }}</span>
          <span class="stmt-pos-detail__card-value">{{
            formatNumber(activeTotals.totalLots || 0, 2)
          }}</span>
        </div>
        <div class="stmt-pos-detail__card">
          <span class="stmt-pos-detail__card-label">{{
            t("ibStatement_detail_openPl", "Open P/L")
          }}</span>
          <span
            class="stmt-pos-detail__card-value"
            :class="amountClass(openNetProfit)"
            >{{ formatCurrency(openNetProfit) }}</span
          >
        </div>
        <div class="stmt-pos-detail__card">
          <span class="stmt-pos-detail__card-label">{{
            t("ibStatement_detail_closedPl", "Closed P/L")
          }}</span>
          <span
            class="stmt-pos-detail__card-value"
            :class="amountClass(closedNetProfit)"
            >{{ formatCurrency(closedNetProfit) }}</span
          >
        </div>
        <div class="stmt-pos-detail__card">
          <span class="stmt-pos-detail__card-label">{{
            t("ibStatement_detail_balance", "Balance")
          }}</span>
          <span
            class="stmt-pos-detail__card-value"
            :class="amountClass(balance)"
            >{{ formatCurrency(balance || 0) }}</span
          >
        </div>
      </div>
    </div>

    <div class="cth-status-switch stmt-pos-detail__tabs">
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
        <span class="stmt-pos-detail__count"
          >({{ statusCounts[option.key] ?? 0 }})</span
        >
      </button>
    </div>

    <div class="cth-table-wrapper">
      <table class="cth-table">
        <thead>
          <tr>
            <th>{{ t("ibClientPosition_thOrderId", "Order ID") }}</th>
            <th>{{ t("ibClientPosition_thSymbol", "Symbol") }}</th>
            <th>{{ t("ibClientPosition_thType", "Type") }}</th>
            <th>{{ t("ibClientPosition_thLots", "Lots") }}</th>
            <th>{{ t("ibClientPosition_thOpenTime", "Open Time") }}</th>
            <th>{{ t("ibClientPosition_thOpenPrice", "Open Price") }}</th>
            <template v-if="isClosed">
              <th>{{ t("ibClientPosition_thCloseTime", "Close Time") }}</th>
              <th>{{ t("ibClientPosition_thClosePrice", "Close Price") }}</th>
            </template>
            <template v-else>
              <th>
                {{ t("ibClientPosition_thCurrentPrice", "Current Price") }}
              </th>
              <th>{{ t("ibClientPosition_thMargin", "Margin") }}</th>
            </template>
            <th>{{ t("ibClientPosition_thSl", "S/L") }}</th>
            <th>{{ t("ibClientPosition_thTp", "T/P") }}</th>
            <th>{{ t("ibClientPosition_thCommission", "Commission") }}</th>
            <th>{{ t("ibClientPosition_thSwap", "Swap") }}</th>
            <th>{{ t("ibClientPosition_thTaxes", "Taxes") }}</th>
            <th>{{ t("ibClientPosition_thProfit", "Profit/Loss") }}</th>
            <th>{{ t("ibClientPosition_thNetProfit", "Net P/L") }}</th>
            <th v-if="isClosed">
              {{ t("ibClientPosition_thReason", "Close Reason") }}
            </th>
            <th>{{ t("ibClientPosition_thComment", "Comment") }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td :colspan="columnCount" class="cth-empty">
              <i class="fas fa-spinner fa-spin"></i>
              {{ t("loading", "Loading...") }}
            </td>
          </tr>
          <tr v-else-if="loadError">
            <td :colspan="columnCount" class="cth-empty cth-empty--error">
              <i class="fas fa-triangle-exclamation"></i>
              {{
                t("ibStatement_detail_loadFailed", "Failed to load positions.")
              }}
              <button type="button" class="cth-retry-btn" @click="load">
                {{ t("retry", "Retry") }}
              </button>
            </td>
          </tr>
          <tr v-else-if="!items.length">
            <td :colspan="columnCount" class="cth-empty">
              {{ t("ibStatement_detail_empty", "No trading records found.") }}
            </td>
          </tr>
          <tr
            v-else
            v-for="row in items"
            :key="`${row.trading_platforms_key}-${row.Id}`"
          >
            <td>{{ row.Id ?? "--" }}</td>
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
        type="button"
        :disabled="page <= 1 || loading"
        @click="changePage(page - 1)"
      >
        <i class="fas fa-chevron-left"></i>
      </button>
      <span class="cth-page-info">{{ page }} / {{ totalPages }}</span>
      <button
        class="cth-page-btn"
        type="button"
        :disabled="page >= totalPages || loading"
        @click="changePage(page + 1)"
      >
        <i class="fas fa-chevron-right"></i>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { useLanguageStore } from "@/stores/language";
import { formatCurrency, formatNumber } from "@/utils/helpers";
import ibClientPositionService from "@/services/ibClientPositionService";

const props = defineProps({
  login: {
    type: [String, Number],
    required: true,
  },
  ibPartnerId: {
    type: [String, Number],
    required: true,
  },
  balance: {
    type: Number,
    default: 0,
  },
});

const languageStore = useLanguageStore();
const t = (key, fallback = "") => languageStore.t(key, fallback);

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

const status = ref("open");
const items = ref([]);
const activeTotals = ref(emptyTotals());
const openNetProfit = ref(0);
const closedNetProfit = ref(0);
const statusCounts = ref({ open: 0, closed: 0, pending: 0 });
const total = ref(0);
const page = ref(1);
const loading = ref(false);
const loadError = ref(false);

const statusOptions = computed(() => [
  { key: "open", label: t("ibStatement_detail_open", "Open Positions") },
  { key: "closed", label: t("ibStatement_detail_closed", "Closed Positions") },
  { key: "pending", label: t("ibStatement_detail_pending", "Pending Orders") },
]);

const isClosed = computed(() => status.value === "closed");
const totalPages = computed(() =>
  total.value > 0 ? Math.ceil(total.value / PAGE_SIZE) : 1,
);
const columnCount = computed(() => (isClosed.value ? 16 : 15));

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
  const mapped = ORDER_SIDES[Number(cmd)];
  if (!mapped) return `#${cmd}`;
  return t(`ibClientPosition_side${Number(cmd)}`, mapped[1]);
};

const amountClass = (value) => {
  const amount = Number(value || 0);
  if (amount > 0) return "cth-amount--up";
  if (amount < 0) return "cth-amount--down";
  return "";
};

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

const unwrapPositions = (response) => {
  const body =
    response?.data && typeof response.data === "object"
      ? response.data
      : response;
  if (body && typeof body === "object" && Array.isArray(body.items)) {
    return body;
  }
  return response?.data?.data ?? response?.data ?? {};
};

const fetchStatusSnapshot = async (statusKey) => {
  const response = await ibClientPositionService.getPositions({
    ibPartnerId: props.ibPartnerId,
    login: props.login,
    status: statusKey,
    page: 1,
    pageSize: 1,
  });
  const data = unwrapPositions(response);
  return {
    total: Number(data.pagination?.total || 0),
    netProfit: Number(data.totals?.netProfit || 0),
  };
};

const loadCounts = async () => {
  const [openSnap, closedSnap, pendingSnap] = await Promise.all([
    fetchStatusSnapshot("open"),
    fetchStatusSnapshot("closed"),
    fetchStatusSnapshot("pending"),
  ]);
  statusCounts.value = {
    open: openSnap.total,
    closed: closedSnap.total,
    pending: pendingSnap.total,
  };
  openNetProfit.value = openSnap.netProfit;
  closedNetProfit.value = closedSnap.netProfit;
};

const load = async () => {
  if (!props.ibPartnerId || !props.login) {
    items.value = [];
    activeTotals.value = emptyTotals();
    total.value = 0;
    loadError.value = false;
    return;
  }

  loading.value = true;
  loadError.value = false;
  try {
    const response = await ibClientPositionService.getPositions({
      ibPartnerId: props.ibPartnerId,
      login: props.login,
      status: status.value,
      page: page.value,
      pageSize: PAGE_SIZE,
    });
    const data = unwrapPositions(response);
    items.value = Array.isArray(data.items) ? data.items : [];
    activeTotals.value = { ...emptyTotals(), ...(data.totals || {}) };
    total.value = Number(data.pagination?.total || 0);
    statusCounts.value = {
      ...statusCounts.value,
      [status.value]: total.value,
    };
    if (status.value === "open") {
      openNetProfit.value = Number(activeTotals.value.netProfit || 0);
    }
    if (status.value === "closed") {
      closedNetProfit.value = Number(activeTotals.value.netProfit || 0);
    }
  } catch (error) {
    loadError.value = true;
    items.value = [];
    activeTotals.value = emptyTotals();
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

const changePage = (next) => {
  if (next < 1 || next > totalPages.value) return;
  page.value = next;
  load();
};

const bootstrap = async () => {
  loadError.value = false;
  loading.value = true;
  try {
    await loadCounts();
    await load();
  } catch (error) {
    loadError.value = true;
    items.value = [];
    activeTotals.value = emptyTotals();
    total.value = 0;
    loading.value = false;
  }
};

watch(
  () => [props.login, props.ibPartnerId],
  () => {
    status.value = "open";
    page.value = 1;
    bootstrap();
  },
);

onMounted(() => {
  bootstrap();
});
</script>

<style scoped>
.stmt-pos-detail {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 16px;
  background: var(--color-surface-soft);
  border-top: 1px solid var(--color-border);
}
.stmt-pos-detail__header {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 12px;
}
.stmt-pos-detail__cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 10px;
}
.stmt-pos-detail__link {
  display: inline-flex;
  align-items: center;
  align-self: flex-end;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-brand);
  text-decoration: none;
  white-space: nowrap;
}
.stmt-pos-detail__card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 10px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: #ffffff;
}
.stmt-pos-detail__card-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  color: #64748b;
}
.stmt-pos-detail__card-value {
  font-size: 16px;
  font-weight: 700;
  color: #1e293b;
}
.stmt-pos-detail__link:hover {
  text-decoration: underline;
}
.stmt-pos-detail__tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.stmt-pos-detail__count {
  font-weight: 600;
  opacity: 0.8;
}
.cth-status-btn {
  padding: 7px 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: #ffffff;
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
.cth-amount--up {
  color: var(--color-success);
}
.cth-amount--down {
  color: var(--color-danger);
}
.cth-table-wrapper {
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: #ffffff;
  min-height: 240px;
  height: 480px;
  max-height: 80vh;
  resize: vertical;
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
  position: sticky;
  top: 0;
  z-index: 1;
  background: var(--color-surface-soft);
  color: #64748b;
  font-weight: 600;
  text-transform: uppercase;
  font-size: 11px;
}
.cth-empty {
  text-align: center;
  color: #94a3b8;
  padding: 24px;
}
.cth-empty--error {
  color: var(--color-danger);
}
.cth-retry-btn {
  margin-left: 8px;
  border: 1px solid var(--color-danger);
  background: #ffffff;
  color: var(--color-danger);
  padding: 3px 10px;
  border-radius: var(--radius-sm);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
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
  background: #fff;
  width: 32px;
  height: 32px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  color: #475569;
}
.cth-page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.cth-page-info {
  font-size: 13px;
  color: #64748b;
}
</style>
