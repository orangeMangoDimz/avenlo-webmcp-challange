<template>
  <div class="container ui-page">
    <div v-if="ibPartnerOptions.length > 1" class="commission-ib-switcher">
      <div class="commission-ib-switcher__label">
        <i class="fas fa-exchange-alt"></i>
        <span>{{ t("commSelectIbPartner", "Select IB") }}</span>
      </div>
      <CustomSelect
        v-model="selectedIbId"
        class="commission-ib-switcher__select"
        :options="ibPartnerOptions"
        searchable
        :placeholder="t('commSelectIbPartner', 'Select IB')"
        :search-placeholder="t('commSearchIbPartner', 'Search IB...')"
        @change="onIbPartnerChange"
      />
    </div>

    <div class="cth">
      <div class="cth-toolbar ui-toolbar">
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
              t('ibClientPosition_searchPlaceholder', 'Symbol or comment')
            "
            @keyup.enter="applyFilters"
          />
          <select v-model="side" class="cth-input cth-input--select">
            <option value="">
              {{ t("ibClientPosition_allDirections", "All directions") }}
            </option>
            <option value="buy">{{ t("ibClientPosition_buy", "Buy") }}</option>
            <option value="sell">
              {{ t("ibClientPosition_sell", "Sell") }}
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
            {{ t("ibClientPosition_search", "Search") }}
          </button>
          <button
            type="button"
            class="cth-btn cth-btn--ghost"
            :disabled="loading"
            @click="resetFilters"
          >
            {{ t("reset", "Reset") }}
          </button>
        </div>
      </div>

      <div class="cth-summary">
        <div class="cth-summary-card">
          <span class="cth-summary-label">{{
            t("ibClientPosition_totalLots", "Total Lots")
          }}</span>
          <span class="cth-summary-value">{{
            formatNumber(totals.totalLots || 0, 2)
          }}</span>
        </div>
        <div class="cth-summary-card">
          <span class="cth-summary-label">{{
            t("ibClientPosition_netProfit", "Net Profit")
          }}</span>
          <span
            class="cth-summary-value"
            :class="amountClass(totals.netProfit)"
            >{{ formatCurrency(totals.netProfit || 0) }}</span
          >
        </div>
        <div class="cth-summary-card">
          <span class="cth-summary-label">{{
            t("ibClientPosition_totalOrders", "Total Orders")
          }}</span>
          <span class="cth-summary-value">{{ totals.totalOrders || 0 }}</span>
        </div>
        <div class="cth-summary-card cth-summary-card--muted">
          <span class="cth-summary-label">{{
            t("ibClientPosition_grossProfit", "Gross P/L")
          }}</span>
          <span
            class="cth-summary-value"
            :class="amountClass(totals.grossProfit)"
            >{{ formatCurrency(totals.grossProfit || 0) }}</span
          >
        </div>
        <div class="cth-summary-card cth-summary-card--muted">
          <span class="cth-summary-label">{{
            t("ibClientPosition_commission", "Commission")
          }}</span>
          <span class="cth-summary-value">{{
            formatCurrency(totals.totalCommission || 0)
          }}</span>
        </div>
        <div class="cth-summary-card cth-summary-card--muted">
          <span class="cth-summary-label">{{
            t("ibClientPosition_swap", "Swap")
          }}</span>
          <span class="cth-summary-value">{{
            formatCurrency(totals.totalSwap || 0)
          }}</span>
        </div>
      </div>

      <div class="cth-table-wrapper ui-data-region ui-table-scroll">
        <table class="cth-table">
          <thead>
            <tr>
              <th>{{ t("ibClientPosition_thClientName", "Client Name") }}</th>
              <th>{{ t("ibClientPosition_thOrderId", "Order ID") }}</th>
              <th>{{ t("ibClientPosition_thLogin", "Login") }}</th>
              <th>{{ t("ibClientPosition_thAccount", "Account Number") }}</th>
              <th>{{ t("ibClientPosition_thPlatform", "Platform") }}</th>
              <th>{{ t("ibClientPosition_thAccountType", "Account Type") }}</th>
              <th>{{ t("ibClientPosition_thCurrency", "Base Currency") }}</th>
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
                  t(
                    "ibClientPosition_loadFailed",
                    "Failed to load trading history.",
                  )
                }}
                <button type="button" class="cth-retry-btn" @click="load">
                  {{ t("retry", "Retry") }}
                </button>
              </td>
            </tr>
            <tr v-else-if="!items.length">
              <td :colspan="columnCount" class="cth-empty">
                {{ t("ibClientPosition_empty", "No trading records found.") }}
              </td>
            </tr>
            <tr
              v-else
              v-for="row in items"
              :key="`${row.trading_platforms_key}-${row.Id}`"
            >
              <td>{{ row.ClientName || "--" }}</td>
              <td>{{ row.Id ?? "--" }}</td>
              <td>{{ row.Login || "--" }}</td>
              <td>{{ row.AccountNumber || "--" }}</td>
              <td>
                {{ row.PlatformName || row.trading_platforms_key || "--" }}
              </td>
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
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import CustomSelect from "@/components/common/CustomSelect.vue";
import { useLanguageStore } from "@/stores/language";
import { formatCurrency, formatNumber } from "@/utils/helpers";
import ibDashboardService from "@/services/ibDashboardService";
import ibClientPositionService from "@/services/ibClientPositionService";

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

const partners = ref([]);
const selectedIbId = ref("");
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

const formatIbPartnerLabel = (ibPartner) => {
  const alias = String(ibPartner.clientAlias || "").trim();
  if (alias) return alias;
  return ibPartner.ibCode || `IB #${ibPartner.id}`;
};

const ibPartnerOptions = computed(() =>
  partners.value.map((ibPartner) => ({
    value: ibPartner.id,
    label: formatIbPartnerLabel(ibPartner),
  })),
);

const statusOptions = computed(() => [
  { key: "closed", label: t("ibClientPosition_closed", "Closed Positions") },
  { key: "open", label: t("ibClientPosition_open", "Open Positions") },
  { key: "pending", label: t("ibClientPosition_pending", "Pending Orders") },
]);

const isClosed = computed(() => status.value === "closed");
const totalPages = computed(() =>
  total.value > 0 ? Math.ceil(total.value / PAGE_SIZE) : 1,
);
const columnCount = computed(() => (isClosed.value ? 23 : 22));
const dateFilterHint = computed(() =>
  isClosed.value
    ? t("ibClientPosition_dateHintClosed", "Filters by close time")
    : t("ibClientPosition_dateHintOpen", "Filters by open time"),
);

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

const loadPartners = async () => {
  const response = await ibDashboardService.getPartners();
  const data = response.data?.data || response.data;
  partners.value = Array.isArray(data) ? data : [];
  if (partners.value.length === 0) {
    selectedIbId.value = "";
    return;
  }
  const selectedExists = partners.value.some(
    (ibPartner) => String(ibPartner.id) === String(selectedIbId.value),
  );
  if (!selectedIbId.value || !selectedExists) {
    selectedIbId.value = partners.value[0].id;
  }
};

const load = async () => {
  if (!selectedIbId.value) {
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
      ibPartnerId: selectedIbId.value,
      status: status.value,
      page: page.value,
      pageSize: PAGE_SIZE,
    };
    if (keywords.value) params.keywords = keywords.value;
    if (side.value) params.side = side.value;
    if (periodFrom.value) params.periodFrom = periodFrom.value;
    if (periodTo.value) params.periodTo = periodTo.value;

    const response = await ibClientPositionService.getPositions(params);
    const data = unwrapPositions(response);
    items.value = Array.isArray(data.items) ? data.items : [];
    totals.value = { ...emptyTotals(), ...(data.totals || {}) };
    total.value = Number(data.pagination?.total || 0);
  } catch (error) {
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

const onIbPartnerChange = () => {
  page.value = 1;
  load();
};

onMounted(async () => {
  try {
    await loadPartners();
    await load();
  } catch (error) {
    loadError.value = true;
    items.value = [];
    totals.value = emptyTotals();
    total.value = 0;
  }
});
</script>

<style scoped>
.container {
  padding: 20px;
}
.commission-ib-switcher {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  margin-bottom: 20px;
}
.commission-ib-switcher__label {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--color-text);
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
}
.commission-ib-switcher__label i {
  color: var(--color-brand);
}
.commission-ib-switcher__select {
  width: 320px;
}
.cth {
  display: flex;
  flex-direction: column;
  gap: 16px;
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
  font-size: 14px;
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
  font-size: 14px;
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
  color: #fff;
  font-size: 14px;
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
  font-size: 14px;
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
  font-size: 14px;
}
.cth-table th,
.cth-table td {
  padding: 10px 12px;
  text-align: left;
  border-bottom: 1px solid var(--color-border);
  white-space: nowrap;
}
.cth-table th {
  background: var(--color-surface-soft);
  color: var(--color-muted);
  font-weight: 600;
  text-transform: uppercase;
  font-size: 14px;
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
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}
.cth-retry-btn:hover {
  background: var(--color-danger-solid);
  color: #fff;
}
.cth-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: var(--radius-md);
  font-size: 14px;
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
  font-size: 14px;
  color: var(--color-muted);
}
@media (max-width: 768px) {
  .commission-ib-switcher {
    flex-direction: column;
    align-items: stretch;
  }
  .commission-ib-switcher__select {
    width: 100%;
  }
}
</style>
