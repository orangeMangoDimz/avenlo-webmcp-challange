<template>
  <section class="funding-chart" aria-labelledby="funding-chart-title">
    <div class="funding-chart-heading">
      <div>
        <p class="operations-eyebrow">Completed flow</p>
        <h2 id="funding-chart-title">Funding trend</h2>
      </div>
      <label v-if="currencies.length > 1">
        <span>Currency</span>
        <select v-model="currency">
          <option v-for="item in currencies" :key="item" :value="item">{{ item }}</option>
        </select>
      </label>
    </div>

    <div v-if="status === 'loading'" class="chart-state" aria-live="polite">
      <i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i>
      <strong>Loading funding trend</strong>
      <span>Reading completed, scoped transactions…</span>
    </div>
    <div v-else-if="status === 'restricted'" class="chart-state">
      <i class="fas fa-lock" aria-hidden="true"></i>
      <strong>Outside your permission scope</strong>
      <span>Funding values remain protected.</span>
    </div>
    <div v-else-if="status === 'error'" class="chart-state">
      <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
      <strong>Funding trend unavailable</strong>
      <span>Refresh to try this section again.</span>
    </div>
    <div v-else-if="!chartPoints.length" class="chart-state">
      <i class="fas fa-chart-line" aria-hidden="true"></i>
      <strong>No completed funding in this period</strong>
      <span>Change the date range or refresh the dashboard.</span>
    </div>
    <template v-else>
      <div class="funding-view-tabs" role="tablist" aria-label="Funding trend views">
        <button
          id="funding-chart-tab"
          ref="chartTab"
          class="funding-view-tab"
          :class="{ 'is-active': activeView === 'chart' }"
          type="button"
          role="tab"
          :aria-selected="activeView === 'chart'"
          aria-controls="funding-chart-panel"
          :tabindex="activeView === 'chart' ? 0 : -1"
          @click="selectView('chart')"
          @keydown="handleViewKeydown"
        >
          <i class="fas fa-chart-line" aria-hidden="true"></i> Chart
        </button>
        <button
          id="funding-table-tab"
          ref="tableTab"
          class="funding-view-tab"
          :class="{ 'is-active': activeView === 'table' }"
          type="button"
          role="tab"
          :aria-selected="activeView === 'table'"
          aria-controls="funding-table-panel"
          :tabindex="activeView === 'table' ? 0 : -1"
          @click="selectView('table')"
          @keydown="handleViewKeydown"
        >
          <i class="fas fa-table" aria-hidden="true"></i> Data table
        </button>
      </div>

      <div v-show="activeView === 'chart'" id="funding-chart-panel" class="funding-view-panel" role="tabpanel" aria-labelledby="funding-chart-tab" tabindex="0">
        <div class="chart-legend" aria-label="Funding series legend">
          <span class="is-deposit">Deposits</span>
          <span class="is-withdrawal">Withdrawals</span>
          <span class="is-net">Net flow</span>
        </div>
        <div class="funding-chart-plot">
          <svg viewBox="0 0 640 280" role="img" :aria-label="chartAriaLabel">
            <g class="chart-grid" aria-hidden="true">
              <line v-for="tick in axisTicks" :key="`grid-${tick.position}`" x1="78" x2="620" :y1="tick.position" :y2="tick.position" />
            </g>
            <line class="chart-y-axis" x1="78" x2="78" y1="40" y2="204" aria-hidden="true" />
            <line class="chart-x-axis" x1="78" x2="620" y1="204" y2="204" aria-hidden="true" />
            <g class="chart-y-ticks" aria-hidden="true">
              <g v-for="tick in axisTicks" :key="`y-${tick.position}`">
                <line class="chart-tick" x1="74" x2="78" :y1="tick.position" :y2="tick.position" />
                <text x="68" :y="tick.position + 3" text-anchor="end">{{ tick.label }}</text>
              </g>
            </g>
            <line class="chart-zero" x1="78" x2="620" :y1="zeroY" :y2="zeroY" aria-hidden="true" />
            <polyline class="series is-deposit" :points="seriesPoints('deposits')" />
            <polyline class="series is-withdrawal" :points="seriesPoints('withdrawals')" />
            <polyline class="series is-net" :points="seriesPoints('netFlow')" />
            <g v-for="(point, index) in chartPoints" :key="`${point.date}-${index}`" class="chart-point">
              <circle
                :class="{ 'is-active': activePointIndex === index }"
                :cx="x(index)"
                :cy="y(point.netFlow)"
                r="4"
                tabindex="0"
                :aria-label="pointAriaLabel(point)"
                @mouseenter="activePointIndex = index"
                @mouseleave="activePointIndex = null"
                @focus="activePointIndex = index"
                @blur="activePointIndex = null"
              ></circle>
              <line v-if="showLabel(index)" class="chart-tick chart-x-tick" :x1="x(index)" :x2="x(index)" y1="204" y2="209" aria-hidden="true" />
              <text v-if="showLabel(index)" class="chart-axis-tick-label" :x="x(index)" y="232" text-anchor="middle">{{ formatAxisDate(point.date) }}</text>
            </g>
            <text class="chart-axis-label chart-axis-label-y" x="26" y="122" text-anchor="middle" transform="rotate(-90 26 122)">Amount ({{ currency }})</text>
            <text class="chart-axis-label chart-axis-label-x" x="349" y="265" text-anchor="middle">Date</text>
          </svg>
          <div v-if="activePoint" class="funding-chart-tooltip" role="tooltip" :style="tooltipStyle">
            <strong>{{ activePoint.date }} · {{ currency }}</strong>
            <span><i class="is-deposit" aria-hidden="true"></i>Deposits <b>{{ formatExact(activePoint.deposits) }}</b></span>
            <span><i class="is-withdrawal" aria-hidden="true"></i>Withdrawals <b>{{ formatExact(activePoint.withdrawals) }}</b></span>
            <span><i class="is-net" aria-hidden="true"></i>Net flow <b>{{ formatExact(activePoint.netFlow) }}</b></span>
          </div>
        </div>
      </div>

      <div v-show="activeView === 'table'" id="funding-table-panel" class="funding-view-panel" role="tabpanel" aria-labelledby="funding-table-tab" tabindex="0">
        <div class="chart-table-scroll">
          <table>
            <thead><tr><th>Date</th><th>Deposits</th><th>Withdrawals</th><th>Net flow</th></tr></thead>
            <tbody>
              <tr v-for="point in chartPoints" :key="point.date">
                <th scope="row">{{ point.date }}</th>
                <td>{{ format(point.deposits) }}</td>
                <td>{{ format(point.withdrawals) }}</td>
                <td>{{ format(point.netFlow) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </section>
</template>

<script setup>
import { computed, nextTick, ref, watch } from "vue";

const props = defineProps({
  status: { type: String, default: "ready" },
  points: { type: Array, default: () => [] },
  startDate: { type: String, default: "" },
  endDate: { type: String, default: "" },
});

const currencies = computed(() =>
  [...new Set(props.points.map((point) => String(point.currency || "USD")))].sort(),
);
const currency = ref("");
watch(
  currencies,
  (values) => {
    if (!values.includes(currency.value)) currency.value = values[0] || "USD";
  },
  { immediate: true },
);
const pointsForCurrency = computed(() =>
  props.points.filter((point) => String(point.currency || "USD") === currency.value),
);
const periodDates = computed(() => {
  const parseDate = (value) => {
    const match = String(value || "").match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return null;
    const timestamp = Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    return Number.isNaN(timestamp) ? null : timestamp;
  };
  const start = parseDate(props.startDate);
  const end = parseDate(props.endDate);
  if (start === null || end === null || end < start || (end - start) / 86400000 > 90) return [];
  const dates = [];
  for (let timestamp = start; timestamp <= end; timestamp += 86400000) {
    dates.push(new Date(timestamp).toISOString().slice(0, 10));
  }
  return dates;
});
const chartPoints = computed(() => {
  const activePoints = pointsForCurrency.value;
  if (!periodDates.value.length) return activePoints;
  const byDate = new Map(activePoints.map((point) => [String(point.date), point]));
  return periodDates.value.map((date) => byDate.get(date) || {
    date,
    currency: currency.value,
    deposits: 0,
    withdrawals: 0,
    netFlow: 0,
  });
});
const activePointIndex = ref(null);
const activeView = ref("chart");
const chartTab = ref(null);
const tableTab = ref(null);
const selectView = (view) => {
  activeView.value = view;
  if (view === "table") activePointIndex.value = null;
};
const handleViewKeydown = (event) => {
  if (!["ArrowRight", "ArrowDown", "ArrowLeft", "ArrowUp"].includes(event.key)) return;
  event.preventDefault();
  const nextView = activeView.value === "chart" ? "table" : "chart";
  selectView(nextView);
  nextTick(() => (nextView === "chart" ? chartTab.value : tableTab.value)?.focus());
};
const activePoint = computed(() =>
  activePointIndex.value === null ? null : chartPoints.value[activePointIndex.value] || null,
);
const values = computed(() => chartPoints.value.flatMap((point) => [
  Number(point.deposits) || 0,
  Number(point.withdrawals) || 0,
  Number(point.netFlow) || 0,
]));
const minimum = computed(() => Math.min(0, ...values.value));
const maximum = computed(() => Math.max(1, ...values.value));
const range = computed(() => Math.max(1, maximum.value - minimum.value));
const chartLeft = 78;
const chartRight = 620;
const x = (index) => chartPoints.value.length === 1
  ? (chartLeft + chartRight) / 2
  : chartLeft + (index / (chartPoints.value.length - 1)) * (chartRight - chartLeft);
const y = (value) => 204 - ((Number(value) - minimum.value) / range.value) * 164;
const zeroY = computed(() => y(0));
const seriesPoints = (key) => chartPoints.value.map((point, index) => `${x(index)},${y(point[key])}`).join(" ");
const showLabel = (index) => chartPoints.value.length <= 8 || index % Math.ceil(chartPoints.value.length / 7) === 0;
const formatAxisDate = (date) => {
  const value = String(date || "");
  return /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : value.slice(0, 10);
};
const format = (value) => new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(Number(value) || 0);
const formatExact = (value) => {
  try {
    return new Intl.NumberFormat(undefined, {
      style: "currency",
      currency: currency.value || "USD",
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(Number(value) || 0);
  } catch {
    return `${currency.value || "USD"} ${Number(value || 0).toFixed(2)}`;
  }
};
const pointAriaLabel = (point) => `${point.date}, ${currency.value}: deposits ${formatExact(point.deposits)}, withdrawals ${formatExact(point.withdrawals)}, net flow ${formatExact(point.netFlow)}`;
const chartGridPositions = [40, 81, 122, 163, 204];
const formatAxisValue = (value) => {
  try {
    return new Intl.NumberFormat(undefined, { notation: "compact", maximumFractionDigits: 1 }).format(Number(value) || 0);
  } catch {
    return format(value);
  }
};
const axisTicks = computed(() => chartGridPositions.map((position) => {
  const value = minimum.value + ((204 - position) / 164) * range.value;
  return { position, value, label: formatAxisValue(value) };
}));
const chartAriaLabel = computed(() => `Funding trend in ${currency.value} across ${chartPoints.value.length} date points. X axis shows dates. Y axis shows amount in ${currency.value}.`);
const tooltipStyle = computed(() => {
  if (activePointIndex.value === null) return {};
  return {
    left: `${(x(activePointIndex.value) / 640) * 100}%`,
    top: `${(y(activePoint.value?.netFlow || 0) / 280) * 100}%`,
  };
});
</script>

<style scoped>
.funding-chart {
  min-width: 0;
  padding: 22px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
}
.funding-chart-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; }
.operations-eyebrow { margin: 0 0 5px; color: var(--color-brand); font-size: 11px; font-weight: 800; letter-spacing: .11em; text-transform: uppercase; }
h2 { margin: 0; color: var(--color-ink); font-size: 19px; }
label { display: grid; gap: 5px; color: var(--color-muted); font-size: 11px; font-weight: 750; text-transform: uppercase; }
select { min-height: 34px; padding: 5px 28px 5px 10px; color: var(--color-ink); background: var(--color-surface-soft); border: 1px solid var(--color-border); border-radius: 5px; }
.chart-legend { display: flex; flex-wrap: wrap; gap: 16px; margin: 20px 0 3px; color: var(--color-muted); font-size: 11px; font-weight: 700; }
.chart-legend span::before { display: inline-block; width: 14px; height: 3px; margin-right: 6px; vertical-align: middle; content: ""; background: currentColor; }
.funding-view-tabs { display: flex; gap: 4px; margin-top: 18px; padding: 4px; background: var(--color-surface-soft); border: 1px solid var(--color-border); border-radius: 6px; }
.funding-view-tab { display: inline-flex; align-items: center; gap: 7px; min-height: 34px; padding: 7px 12px; color: var(--color-muted); background: transparent; border: 1px solid transparent; border-radius: 4px; font: inherit; font-size: 11px; font-weight: 750; cursor: pointer; }
.funding-view-tab:hover { color: var(--color-ink); background: var(--color-surface); }
.funding-view-tab.is-active { color: var(--color-brand); background: var(--color-surface); border-color: var(--color-border); box-shadow: 0 2px 5px rgba(15, 23, 42, .08); }
.funding-view-tab:focus-visible { outline: 2px solid var(--color-focus-ring); outline-offset: 1px; }
.funding-view-panel { min-width: 0; }
.is-deposit { color: var(--color-success) !important; }
.is-withdrawal { color: var(--color-danger) !important; }
.is-net { color: var(--color-brand) !important; }
.funding-chart-plot { position: relative; }
svg { display: block; width: 100%; min-height: 230px; overflow: visible; }
.chart-grid line { stroke: var(--color-border); stroke-width: 1; }
.chart-y-axis, .chart-x-axis { stroke: var(--color-border-strong); stroke-width: 1.5; }
.chart-tick { stroke: var(--color-border-strong); stroke-width: 1; }
.chart-zero { stroke: var(--color-border-strong); stroke-dasharray: 4 4; }
.series { fill: none; stroke: currentColor; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; }
.series.is-withdrawal { stroke-dasharray: 7 5; }
.chart-point circle { fill: var(--color-surface); stroke: var(--color-brand); stroke-width: 3; cursor: crosshair; transition: stroke-width 120ms ease; }
.chart-point circle:hover, .chart-point circle.is-active { stroke: var(--color-warning); stroke-width: 5; }
.chart-point circle:focus { outline: none; stroke: var(--color-warning); stroke-width: 5; }
.chart-point text, .chart-y-ticks text { fill: var(--color-muted); font-size: 10px; }
.chart-axis-label { fill: var(--color-muted); font-size: 10px; font-weight: 750; letter-spacing: .06em; }
.funding-chart-tooltip { position: absolute; z-index: 2; display: grid; min-width: 184px; gap: 6px; padding: 10px 12px; color: var(--color-text); background: var(--color-surface); border: 1px solid var(--color-brand); border-radius: 6px; box-shadow: 0 10px 24px rgba(0, 0, 0, .22); pointer-events: none; transform: translate(-50%, calc(-100% - 12px)); }
.funding-chart-tooltip::after { position: absolute; bottom: -6px; left: 50%; width: 10px; height: 10px; content: ""; background: var(--color-surface); border-right: 1px solid var(--color-brand); border-bottom: 1px solid var(--color-brand); transform: translateX(-50%) rotate(45deg); }
.funding-chart-tooltip strong { padding-bottom: 3px; color: var(--color-ink-strong); border-bottom: 1px solid var(--color-border); font-size: 11px; }
.funding-chart-tooltip span { display: flex; align-items: center; gap: 7px; color: var(--color-text); font-size: 10px; }
.funding-chart-tooltip span i { width: 13px; height: 3px; flex: 0 0 auto; background: currentColor; }
.funding-chart-tooltip b { margin-left: auto; color: var(--color-ink-strong); font-variant-numeric: tabular-nums; font-weight: 750; }
:global(:root[data-theme="dark"]) .funding-chart-tooltip { color: #f8fafc; background: var(--color-brand-strong); }
:global(:root[data-theme="dark"]) .funding-chart-tooltip::after { background: var(--color-brand-strong); }
:global(:root[data-theme="dark"]) .funding-chart-tooltip strong { color: #fff; border-bottom-color: rgba(255, 255, 255, .18); }
:global(:root[data-theme="dark"]) .funding-chart-tooltip span { color: rgba(255, 255, 255, .76); }
:global(:root[data-theme="dark"]) .funding-chart-tooltip b { color: #fff; }
.chart-state { display: grid; min-height: 260px; place-items: center; align-content: center; gap: 8px; color: var(--color-muted); text-align: center; }
.chart-state i { color: var(--color-brand); font-size: 24px; }
.chart-state strong { color: var(--color-ink); }
.chart-state span { font-size: 12px; }
.chart-table-scroll { margin-top: 10px; overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 8px; border-bottom: 1px solid var(--color-border); text-align: right; }
th:first-child { text-align: left; }
@media (max-width: 600px) { .funding-chart-heading { flex-direction: column; } svg { min-height: 190px; } .funding-chart-tooltip { min-width: 166px; } }
</style>
