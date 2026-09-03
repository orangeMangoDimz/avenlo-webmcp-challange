<template>
  <div class="webmcp-page operations-overview">
    <header class="operations-page-header">
      <div>
        <p class="operations-kicker">Permission-aware command surface</p>
        <h1><i class="fas fa-tower-broadcast" aria-hidden="true"></i> Operations Overview</h1>
        <p>What needs your attention in this selected period, why it matters, and where to investigate it.</p>
      </div>
      <div class="operations-header-actions">
        <div class="runtime-compact" :class="statusClass">
          <span class="runtime-indicator" aria-hidden="true"></span>
          <span><small>Browser tools</small><strong>{{ statusLabel }}</strong></span>
          <label class="runtime-switch is-locked" :aria-label="toggleLabel" title="Always enabled">
            <input checked disabled type="checkbox" aria-label="WebMCP browser tools are always enabled" />
            <span aria-hidden="true"></span>
          </label>
        </div>
        <PageHeaderActions />
      </div>
    </header>

    <section class="operations-quick-actions" aria-label="Essential actions">
      <div class="quick-actions-label">
        <p class="operations-eyebrow">Quick actions</p>
        <span>Essential operations</span>
      </div>
      <div class="quick-actions-list">
        <button class="quick-action-button is-primary" type="button" :disabled="loading || refreshing" @click="loadOverview">
          <i class="fas fa-rotate" :class="{ 'fa-spin': refreshing }" aria-hidden="true"></i>
          {{ refreshing ? "Refreshing" : "Refresh data" }}
        </button>
        <button class="quick-action-button" type="button" @click="focusAttentionQueue">
          <i class="fas fa-list-check" aria-hidden="true"></i> Review attention queue
        </button>
        <div ref="exportMenuRef" class="quick-actions-export-menu" @keydown="handleExportMenuKeydown">
          <button
            ref="exportMenuButton"
            class="quick-action-button"
            type="button"
            aria-haspopup="menu"
            :aria-expanded="exportMenuOpen"
            aria-controls="operations-export-menu"
            @click="toggleExportMenu"
          >
            <i class="fas fa-file-arrow-down" aria-hidden="true"></i> Export
            <i class="fas fa-chevron-down quick-action-chevron" aria-hidden="true"></i>
          </button>
          <div v-if="exportMenuOpen" id="operations-export-menu" class="quick-actions-menu" role="menu" aria-label="Export options">
            <button class="quick-actions-menu-item" type="button" role="menuitem" :disabled="!exportableAttentionItems.length" @click="runVisibleEvidenceExport">
              <i class="fas fa-list-check" aria-hidden="true"></i> Visible queue evidence
            </button>
            <button v-if="overview?.scope?.funding?.canExport" class="quick-actions-menu-item" type="button" role="menuitem" @click="runExportQuickAction('funding')">
              <i class="fas fa-money-bill-transfer" aria-hidden="true"></i> Funding report
            </button>
            <button v-if="overview?.scope?.kyc?.canExport" class="quick-actions-menu-item" type="button" role="menuitem" @click="runExportQuickAction('kyc')">
              <i class="fas fa-user-shield" aria-hidden="true"></i> KYC evidence
            </button>
            <button v-if="overview?.scope?.audit?.canExport" class="quick-actions-menu-item" type="button" role="menuitem" @click="runExportQuickAction('audit')">
              <i class="fas fa-shield-halved" aria-hidden="true"></i> Admin activity
            </button>
          </div>
        </div>
      </div>
    </section>

    <section class="operations-filter-bar" aria-label="Dashboard controls">
      <div class="filter-cluster">
        <label><span>From</span><input v-model="filters.startDate" type="date" :max="filters.endDate" /></label>
        <span class="filter-arrow" aria-hidden="true"><i class="fas fa-arrow-right"></i></span>
        <label><span>To</span><input v-model="filters.endDate" type="date" :min="filters.startDate" /></label>
        <label>
          <span>Timezone</span>
          <select v-model="timezoneMode" @change="handleTimezoneChange">
            <option value="browser">Browser · {{ browserTimezoneLabel }}</option>
            <option value="utc">UTC · UTC+00:00</option>
          </select>
        </label>
      </div>
      <div class="freshness-cluster" aria-live="polite">
        <span class="scope-pill"><i class="fas fa-shield-halved" aria-hidden="true"></i>{{ scopeLabel }}</span>
        <span class="freshness" :class="{ 'is-stale': isStale }">
          <i :class="isStale ? 'fas fa-clock-rotate-left' : 'fas fa-circle-check'" aria-hidden="true"></i>
          {{ freshnessLabel }}
        </span>
      </div>
    </section>

    <div v-if="errorMessage" class="operations-alert" role="alert">
      <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
      <span>{{ errorMessage }}</span>
      <button type="button" @click="loadOverview">Try again</button>
    </div>

    <section class="operations-metric-grid" aria-label="Primary operations metrics">
      <OperationsMetricCard
        v-for="metric in metricCards"
        :key="metric.key"
        v-bind="metric"
        @investigate="investigateMetric(metric)"
        @open="openMetric(metric)"
        @export="exportMetric(metric)"
      />
    </section>

    <section ref="attentionPanel" class="operations-attention-panel" tabindex="-1" aria-labelledby="attention-title">
      <div class="panel-heading">
        <div>
          <p class="operations-eyebrow">Prioritized exceptions</p>
          <h2 id="attention-title">Attention Queue</h2>
          <p>Deterministic rules only. No AI risk scores or hidden ranking logic.</p>
        </div>
        <div class="queue-summary"><strong>{{ attentionSummaryCount }}</strong><span>{{ attentionSummaryLabel }}</span></div>
      </div>
      <div class="queue-toolbar">
        <label><span>Severity</span><select v-model="attentionSeverity"><option value="all">All severities</option><option value="critical">Critical</option><option value="high">High</option><option value="medium">Medium</option></select></label>
        <label><span>Exception type</span><select v-model="attentionType"><option value="all">All exception types</option><option value="transaction">Transactions</option><option value="kyc">KYC reviews</option><option value="client">Sales assignment</option><option value="audit">Administrator activity</option></select></label>
      </div>
      <div v-if="loading || refreshing" class="queue-loading" aria-live="polite"><span v-for="index in 4" :key="index"></span></div>
      <div v-else-if="!filteredAttention.length" class="queue-empty">
        <span><i class="fas fa-check" aria-hidden="true"></i></span><strong>No matching exceptions</strong>
        <p>{{ attentionItems.length ? "Change the queue filters to see other items." : "There is nothing requiring attention in your current scope." }}</p>
      </div>
      <ol v-else class="attention-list">
        <li v-for="item in paginatedAttention" :key="item.id" :class="`is-${item.severity}`">
          <span class="severity-marker" aria-hidden="true"></span>
          <div class="attention-copy">
            <div class="attention-title-row"><span class="severity-label">{{ item.severity }}</span><span class="attention-age">{{ formatAge(item.ageHours) }}</span></div>
            <h3>{{ item.title }}</h3><p>{{ item.reason }}</p>
            <span class="related-record"><i class="fas fa-link" aria-hidden="true"></i>{{ item.relatedLabel }}</span>
          </div>
          <div class="attention-actions">
            <button type="button" @click="investigateItem(item)">Investigate</button>
            <button type="button" class="is-primary" @click="openItem(item)">Open <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></button>
          </div>
        </li>
      </ol>
      <div v-if="filteredAttention.length > attentionPerPage" class="queue-pagination pagination">
        <div class="pagination-info">Showing {{ attentionPagination.from }}–{{ attentionPagination.to }} of {{ attentionPagination.total }} priority items</div>
        <div class="pagination-controls">
          <button class="pagination-btn" type="button" :disabled="attentionPagination.page === 1" @click="changeAttentionPage(attentionPagination.page - 1)"><i class="fas fa-chevron-left" aria-hidden="true"></i> Previous</button>
          <template v-for="page in attentionPageNumbers" :key="page">
            <button v-if="page !== '…'" class="pagination-btn" :class="{ active: attentionPagination.page === page }" type="button" :aria-label="`Go to attention queue page ${page}`" :aria-current="attentionPagination.page === page ? 'page' : undefined" @click="changeAttentionPage(page)">{{ page }}</button>
            <span v-else class="pagination-ellipsis" aria-hidden="true">…</span>
          </template>
          <button class="pagination-btn" type="button" :disabled="attentionPagination.page === attentionPagination.totalPages" @click="changeAttentionPage(attentionPagination.page + 1)">Next <i class="fas fa-chevron-right" aria-hidden="true"></i></button>
        </div>
      </div>
      <p v-if="overview?.attentionQueue?.truncated" class="queue-truncated">Showing prioritized items with reserved coverage per exception type. Open the source records for the full backlog.</p>
    </section>

    <div class="operations-support-grid">
      <OperationsFundingChart
        :status="loading ? 'loading' : overview?.fundingTrend?.status"
        :points="overview?.fundingTrend?.points || []"
        :start-date="overview?.period?.startDate"
        :end-date="overview?.period?.endDate"
      />

      <section class="support-panel" aria-labelledby="sales-title">
        <div class="support-heading"><div><p class="operations-eyebrow">Performance pulse</p><h2 id="sales-title">Sales performance</h2></div><button v-if="overview?.sales?.status === 'ready'" type="button" @click="openNamedRoute('sales-dashboard')">Open</button></div>
        <DashboardSectionState :status="loading ? 'loading' : overview?.sales?.status" label="sales performance" />
        <template v-if="!loading && overview?.sales?.status === 'ready'">
          <div class="sales-summary"><span><small>Net deposit</small><strong>{{ formatNumber(overview.sales.summary?.netDeposit) }}</strong></span><span><small>New clients</small><strong>{{ formatNumber(overview.sales.summary?.newClients) }}</strong></span></div>
          <ol v-if="overview.sales.rankings?.length" class="leader-list">
            <li v-for="ranking in overview.sales.rankings" :key="ranking.sales?.id"><span class="rank-number">{{ ranking.rank }}</span><span class="leader-name"><strong>{{ ranking.sales?.name || "Sales user" }}</strong><small>{{ ranking.sales?.email }}</small></span><span class="leader-value">{{ formatNumber(ranking.value) }}<small v-if="ranking.target?.achievementRate != null">{{ formatNumber(ranking.target.achievementRate) }}% KPI</small></span></li>
          </ol>
          <p v-else class="compact-empty">No sales activity for the selected ending date.</p>
        </template>
      </section>

      <section class="support-panel" aria-labelledby="ib-title">
        <div class="support-heading"><div><p class="operations-eyebrow">Network activity</p><h2 id="ib-title">IB performance</h2></div><button v-if="overview?.ib?.status === 'ready'" type="button" @click="openNamedRoute('ib-list')">Open</button></div>
        <DashboardSectionState :status="loading ? 'loading' : overview?.ib?.status" label="IB performance" />
        <template v-if="!loading && overview?.ib?.status === 'ready'">
          <div class="ib-stats"><span><strong>{{ formatNumber(overview.ib.summary?.partnerCount) }}</strong><small>Visible partners</small></span><span><strong>{{ formatNumber(overview.ib.summary?.clientCount) }}</strong><small>Network clients</small></span><span><strong>{{ formatNumber(overview.ib.summary?.newPartnerCount) }}</strong><small>New in period</small></span></div>
          <ol v-if="overview.ib.leaders?.length" class="leader-list"><li v-for="partner in overview.ib.leaders" :key="partner.id"><span class="rank-number"><i class="fas fa-code-branch" aria-hidden="true"></i></span><span class="leader-name"><strong>{{ partner.name }}</strong><small>{{ partner.code }}</small></span><span class="leader-value">{{ partner.clientCount }} clients</span></li></ol>
          <div class="support-actions"><button type="button" @click="openNamedRoute('ib-list')">Explore network</button><button v-if="overview.scope?.ib?.canExport" type="button" @click="openNamedRoute('ib-statement')">Statement access</button></div>
        </template>
      </section>

      <section class="support-panel activity-panel" aria-labelledby="activity-title">
        <div class="support-heading"><div><p class="operations-eyebrow">Recent activity</p><h2 id="activity-title">Recent exports & investigations</h2></div><span class="local-history"><i class="fas fa-clock-rotate-left" aria-hidden="true"></i> Live + local</span></div>
        <p class="activity-note">System exports come from the audit log; dashboard investigations are retained in this browser.</p>
        <p v-if="!recentActivity.length" class="compact-empty">Dashboard actions and exports will appear here.</p>
        <ol v-else class="activity-list"><li v-for="activity in recentActivity.slice(0, 6)" :key="`${activity.id}-${activity.createdAt}`"><span class="activity-icon"><i :class="activityIcon(activity.kind)" aria-hidden="true"></i></span><span><strong>{{ activity.label }}</strong><small>{{ formatTimestamp(activity.createdAt) }}</small></span><button v-if="activity.route || activity.jobId" type="button" aria-label="Reopen activity" @click="reopenActivity(activity)"><i class="fas fa-arrow-right" aria-hidden="true"></i></button></li></ol>
      </section>
    </div>

    <div ref="evidenceOverlay" v-if="selectedItem" class="evidence-overlay" role="presentation" @click.self="closeInvestigation">
      <section ref="evidenceDrawer" class="evidence-drawer" role="dialog" aria-modal="true" aria-labelledby="evidence-title" tabindex="-1" @keydown="handleEvidenceKeydown">
        <div class="evidence-heading"><div><p class="operations-eyebrow">Evidence preview</p><h2 id="evidence-title">{{ selectedItem.title }}</h2></div><button ref="drawerCloseButton" type="button" aria-label="Close investigation" @click="closeInvestigation"><i class="fas fa-xmark" aria-hidden="true"></i></button></div>
        <div class="evidence-reason" :class="`is-${selectedItem.severity}`"><span>{{ selectedItem.severity }}</span><p>{{ selectedItem.reason }}</p></div>
        <dl class="evidence-grid"><div><dt>Related record</dt><dd>{{ selectedItem.relatedLabel }}</dd></div><div><dt>Age</dt><dd>{{ formatAge(selectedItem.ageHours) }}</dd></div><div><dt>Detected at</dt><dd>{{ formatTimestamp(selectedItem.occurredAt) }}</dd></div><div v-if="selectedItem.status"><dt>Status</dt><dd>{{ selectedItem.status }}</dd></div><div v-if="selectedItem.amount != null"><dt>Amount</dt><dd>{{ formatMoney(selectedItem.amount, selectedItem.currency) }}</dd></div><div v-if="selectedItem.module"><dt>Audit module</dt><dd>{{ selectedItem.module }}</dd></div></dl>
        <div class="evidence-policy"><i class="fas fa-scale-balanced" aria-hidden="true"></i><span>This item was produced by the published deterministic dashboard policy.</span></div>
        <div class="evidence-actions"><button type="button" @click="closeInvestigation">Close</button><button type="button" class="is-primary" @click="openItem(selectedItem)">Open CRM record</button></div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import OperationsMetricCard from "@/components/webmcp/OperationsMetricCard.vue";
import OperationsFundingChart from "@/components/webmcp/OperationsFundingChart.vue";
import DashboardSectionState from "@/components/webmcp/DashboardSectionState.vue";
import { useAuthStore } from "@/stores/auth";
import { adminWebMcpApi } from "@/services/adminWebMcpApi";
import { buildOperationsRecordRoute, createWebMcpActivityStore, defaultOperationsFilters, downloadEvidenceCsv, normalizeOperationsOverview, paginateItems, paginationPages } from "@/services/adminWebMcpOperations";

const router = useRouter();
const authStore = useAuthStore();
const browserOffset = -new Date().getTimezoneOffset();
const filters = ref(defaultOperationsFilters(new Date(), browserOffset));
const timezoneMode = ref("browser");
const overview = ref(null);
const loading = ref(true);
const refreshing = ref(false);
const errorMessage = ref("");
const nowTick = ref(Date.now());
const attentionSeverity = ref("all");
const attentionType = ref("all");
const attentionPage = ref(1);
const attentionPerPage = 5;
const selectedItem = ref(null);
const attentionPanel = ref(null);
const drawerCloseButton = ref(null);
const evidenceOverlay = ref(null);
const evidenceDrawer = ref(null);
const lastFocusedElement = ref(null);
const inertDashboardElements = [];
let previousBodyOverflow = "";
const exportMenuOpen = ref(false);
const exportMenuRef = ref(null);
const exportMenuButton = ref(null);
const enabled = ref(true);
const modelContextSupported = ref(false);
const recentActivity = ref([]);
let requestSequence = 0;
let freshnessTimer = null;

const activityStore = computed(() => createWebMcpActivityStore({ adminId: authStore.user?.id }));
const browserTimezoneLabel = computed(() => offsetLabel(browserOffset));
const statusLabel = computed(() => !enabled.value ? "Disabled" : modelContextSupported.value ? "Enabled" : "Runtime unavailable");
const statusClass = computed(() => ({ "is-enabled": enabled.value && modelContextSupported.value, "is-disabled": !enabled.value, "is-unavailable": enabled.value && !modelContextSupported.value }));
const toggleLabel = "WebMCP browser tools are always enabled";
const attentionItems = computed(() => overview.value?.attentionQueue?.items || []);
const filteredAttention = computed(() => attentionItems.value.filter((item) => (attentionSeverity.value === "all" || item.severity === attentionSeverity.value) && (attentionType.value === "all" || item.kind === attentionType.value)));
const attentionFilterActive = computed(() => attentionSeverity.value !== "all" || attentionType.value !== "all");
const attentionSummaryCount = computed(() => attentionFilterActive.value ? overview.value?.attentionQueue?.total ?? filteredAttention.value.length : overview.value?.attentionQueue?.total ?? 0);
const attentionSummaryLabel = computed(() => attentionFilterActive.value ? "matching items" : "items in scope");
const attentionPagination = computed(() => paginateItems(filteredAttention.value, attentionPage.value, attentionPerPage));
const attentionPageNumbers = computed(() => paginationPages(attentionPagination.value.totalPages, attentionPagination.value.page));
const paginatedAttention = computed(() => attentionPagination.value.items);
watch([attentionSeverity, attentionType], () => {
  attentionPage.value = 1;
  if (overview.value) loadOverview();
});
watch(
  () => attentionPagination.value.totalPages,
  (totalPages) => {
    if (attentionPage.value > totalPages) attentionPage.value = totalPages;
  },
);
const exportableAttentionItems = computed(() => filteredAttention.value.filter(canExportItem));
const generatedTimestamp = computed(() => Date.parse(overview.value?.generatedAt || ""));
const staleSeconds = computed(() => Number(overview.value?.policy?.staleAfterSeconds) || 300);
const ageSeconds = computed(() => Number.isFinite(generatedTimestamp.value) ? Math.max(0, Math.floor((nowTick.value - generatedTimestamp.value) / 1000)) : null);
const isStale = computed(() => ageSeconds.value != null && ageSeconds.value >= staleSeconds.value);
const freshnessLabel = computed(() => loading.value && !overview.value ? "Loading live data" : ageSeconds.value == null ? "Freshness unavailable" : `${isStale.value ? "Stale · updated" : "Updated"} ${formatRelative(ageSeconds.value)} ago`);
const scopeLabel = computed(() => {
  const scopes = Object.values(overview.value?.scope || {});
  if (!scopes.length) return "Resolving scope";
  const visible = scopes.filter(({ access }) => access !== "restricted");
  if (!visible.length) return "No operational scope";
  return visible.some(({ access }) => ["own", "self"].includes(access)) ? "Assigned records" : "Permitted records";
});

const metricCards = computed(() => {
  const metric = overview.value?.metrics || {};
  const isInitial = loading.value && !overview.value;
  return [
    { key: "netFunding", title: "Net funding", icon: "fas fa-wave-square", tone: "success", status: isInitial ? "loading" : metric.netFunding?.status || "restricted", value: netFundingValue(metric.netFunding), detail: netFundingDetail(metric.netFunding), canExport: Boolean(overview.value?.scope?.funding?.canExport), queueType: "transaction", routeName: "funding-report", exportType: "funding" },
    { key: "pendingHighValueTransactions", title: "Pending high-value transactions", icon: "fas fa-money-bill-transfer", tone: "warning", status: isInitial ? "loading" : metric.pendingHighValueTransactions?.status || "restricted", value: formatNumber(metric.pendingHighValueTransactions?.count), detail: `Threshold ${formatNumber(overview.value?.policy?.highValueAmount || 10000)} in transaction currency`, canExport: Boolean(overview.value?.scope?.funding?.canExport), queueType: "transaction", routeName: "funding-report", exportType: "funding" },
    { key: "overdueKyc", title: "Overdue KYC reviews", icon: "fas fa-user-shield", tone: "danger", status: isInitial ? "loading" : metric.overdueKyc?.status || "restricted", value: formatNumber(metric.overdueKyc?.count), detail: `Waiting ${overview.value?.policy?.kycOverdueHours || 24}+ hours for review`, canExport: Boolean(overview.value?.scope?.kyc?.canExport), queueType: "kyc", routeName: "kyc-list", exportType: "kyc" },
    { key: "operationalAlerts", title: "Operational alerts", icon: "fas fa-shield-virus", tone: "info", status: isInitial ? "loading" : metric.operationalAlerts?.status || "restricted", value: formatNumber(metric.operationalAlerts?.count), detail: "Sensitive access changes and administrator mutation bursts", canExport: Boolean(overview.value?.scope?.audit?.canExport), queueType: "audit", routeName: "operation-log-report", exportType: "audit" },
  ];
});

const loadOverview = async () => {
  const sequence = ++requestSequence;
  if (overview.value) refreshing.value = true; else loading.value = true;
  errorMessage.value = "";
  try {
    const queueFilters = {
      ...(attentionSeverity.value !== "all" ? { severity: attentionSeverity.value } : {}),
      ...(attentionType.value !== "all" ? { exceptionType: attentionType.value } : {}),
    };
    const payload = await adminWebMcpApi.getOperationsOverview({ ...filters.value, ...queueFilters });
    if (sequence !== requestSequence) return;
    overview.value = normalizeOperationsOverview(payload);
    mergeRecentActivity();
    nowTick.value = Date.now();
  } catch (error) {
    if (sequence === requestSequence) errorMessage.value = error?.response?.data?.message || error?.message || "Unable to load the operations dashboard.";
  } finally {
    if (sequence === requestSequence) { loading.value = false; refreshing.value = false; }
  }
};
const handleTimezoneChange = () => { filters.value.tzOffset = timezoneMode.value === "utc" ? 0 : browserOffset; };
const toggleExportMenu = () => {
  if (exportMenuOpen.value) closeExportMenu(true);
  else exportMenuOpen.value = true;
};
const exportMenuItems = () => exportMenuRef.value
  ? [...exportMenuRef.value.querySelectorAll(".quick-actions-menu-item:not(:disabled)")]
  : [];
const focusExportMenuButton = () => nextTick(() => exportMenuButton.value?.focus?.());
const closeExportMenu = (restoreFocus = false) => {
  exportMenuOpen.value = false;
  if (restoreFocus) focusExportMenuButton();
};
const handleExportMenuOutside = (event) => {
  if (!exportMenuRef.value?.contains(event.target)) closeExportMenu();
};
const handleExportMenuKeydown = (event) => {
  const key = event.key;
  if (key === "Escape") {
    if (!exportMenuOpen.value) return;
    event.preventDefault();
    closeExportMenu(true);
    return;
  }
  if (key === "Tab") {
    closeExportMenu();
    return;
  }
  const nextKeys = ["ArrowDown", "ArrowRight"];
  const previousKeys = ["ArrowUp", "ArrowLeft"];
  if (![...nextKeys, ...previousKeys, "Home", "End"].includes(key)) return;
  if (!exportMenuOpen.value) {
    if (!nextKeys.includes(key) && !previousKeys.includes(key)) return;
    event.preventDefault();
    exportMenuOpen.value = true;
    nextTick(() => {
      const items = exportMenuItems();
      (previousKeys.includes(key) ? items[items.length - 1] : items[0])?.focus?.();
    });
    return;
  }
  const items = exportMenuItems();
  if (!items.length) return;
  event.preventDefault();
  const currentIndex = items.indexOf(document.activeElement);
  if (key === "Home") return items[0].focus();
  if (key === "End") return items[items.length - 1].focus();
  const step = nextKeys.includes(key) ? 1 : -1;
  if (currentIndex < 0) return (step > 0 ? items[0] : items[items.length - 1]).focus();
  items[(currentIndex + step + items.length) % items.length].focus();
};
const investigateMetric = async (metric) => { attentionType.value = metric.queueType; attentionSeverity.value = "all"; await nextTick(); attentionPanel.value?.scrollIntoView?.({ behavior: "smooth", block: "start" }); };
const changeAttentionPage = (page) => {
  if (page < 1 || page > attentionPagination.value.totalPages) return;
  attentionPage.value = page;
  attentionPanel.value?.scrollIntoView?.({ behavior: "smooth", block: "start" });
};
const focusAttentionQueue = () => attentionPanel.value?.scrollIntoView?.({ behavior: "smooth", block: "start" });
const openMetric = (metric) => openNamedRoute(metric.routeName);
const exportMetric = async (metric) => {
  if (!metric.canExport) return;
  try {
    if (metric.exportType === "kyc") {
      downloadEvidenceCsv(attentionItems.value.filter((item) => item.kind === "kyc"), `webmcp_overdue_kyc_${filters.value.endDate}.csv`);
      recordActivity({ id: `kyc-export:${Date.now()}`, kind: "export", label: "Overdue KYC evidence export" });
      return;
    }
    const job = metric.exportType === "audit"
      ? await adminWebMcpApi.exportOperationLogs({ startDate: filters.value.startDate, endDate: filters.value.endDate, language: "en" })
      : await adminWebMcpApi.exportFundingReport({ startDate: filters.value.startDate, endDate: filters.value.endDate });
    openExportJob(job, metric.exportType === "funding" ? "funding_report" : "");
  } catch { errorMessage.value = "Unable to start this export."; }
};
const exportQuickAction = (exportType) => {
  const metric = metricCards.value.find((item) => item.exportType === exportType);
  if (metric) return exportMetric(metric);
  return null;
};
const runExportQuickAction = async (exportType) => {
  closeExportMenu(true);
  await exportQuickAction(exportType);
};
const runVisibleEvidenceExport = () => {
  closeExportMenu(true);
  exportVisibleEvidence();
};
const openExportJob = (job, exportKind) => {
  const jobId = String(job?.jobId || "").trim();
  if (!jobId) throw new Error("Export job is missing an ID.");
  const query = new URLSearchParams({ jobId });
  if (exportKind) query.set("exportKind", exportKind);
  window.open?.(`/#/webmcp/export-progress?${query.toString()}`, "_blank", "noopener");
  recordActivity({ id: `export:${jobId}`, kind: "export", label: exportKind ? "Funding report export" : "Operation log export", jobId, exportKind });
};
const setDashboardBackgroundInert = (inert) => {
  if (inert) {
    const root = evidenceOverlay.value?.parentElement;
    if (!root) return;
    inertDashboardElements.splice(0);
    [...root.children].forEach((element) => {
      if (element === evidenceOverlay.value) return;
      inertDashboardElements.push({
        element,
        inert: element.inert,
        ariaHidden: element.getAttribute("aria-hidden"),
      });
      element.inert = true;
      element.setAttribute("inert", "");
      element.setAttribute("aria-hidden", "true");
    });
    return;
  }
  inertDashboardElements.splice(0).forEach(({ element, inert, ariaHidden }) => {
    element.inert = inert;
    if (inert) element.setAttribute("inert", "");
    else element.removeAttribute("inert");
    if (ariaHidden === null) element.removeAttribute("aria-hidden");
    else element.setAttribute("aria-hidden", ariaHidden);
  });
};
const focusableElements = (root) => root
  ? [...root.querySelectorAll("button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex=\"-1\"])")]
  : [];
const handleEvidenceKeydown = (event) => {
  if (event.key === "Escape") {
    event.preventDefault();
    closeInvestigation();
    return;
  }
  if (event.key !== "Tab") return;
  const focusables = focusableElements(evidenceDrawer.value);
  if (!focusables.length) {
    event.preventDefault();
    evidenceDrawer.value?.focus?.();
    return;
  }
  const first = focusables[0];
  const last = focusables[focusables.length - 1];
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
};
const investigateItem = async (item) => {
  lastFocusedElement.value = document.activeElement instanceof HTMLElement ? document.activeElement : null;
  previousBodyOverflow = document.body.style.overflow;
  document.body.style.overflow = "hidden";
  selectedItem.value = item;
  recordActivity({ id: `investigate:${item.id}:${Date.now()}`, kind: "investigation", label: item.title });
  await nextTick();
  setDashboardBackgroundInert(true);
  drawerCloseButton.value?.focus?.();
};
const closeInvestigation = () => {
  const restoreTarget = lastFocusedElement.value;
  setDashboardBackgroundInert(false);
  document.body.style.overflow = previousBodyOverflow;
  selectedItem.value = null;
  lastFocusedElement.value = null;
  nextTick(() => {
    if (restoreTarget && document.contains(restoreTarget)) restoreTarget.focus?.();
    else attentionPanel.value?.focus?.();
  });
};
const openItem = async (item) => {
  const route = buildOperationsRecordRoute(item);
  if (!route) { errorMessage.value = "This record does not have a supported CRM destination."; return; }
  recordActivity({ id: `open:${item.id}:${Date.now()}`, kind: "open", label: item.title, route });
  closeInvestigation();
  await router.push(route);
};
const openNamedRoute = async (name) => { const route = { name }; recordActivity({ id: `open:${name}:${Date.now()}`, kind: "open", label: `Opened ${name.replaceAll("-", " ")}`, route }); await router.push(route); };
const exportVisibleEvidence = () => { downloadEvidenceCsv(exportableAttentionItems.value, `webmcp_attention_${filters.value.endDate}.csv`); recordActivity({ id: `evidence:${Date.now()}`, kind: "export", label: "Attention queue evidence export" }); };
function canExportItem(item) { return Boolean(overview.value?.scope?.[item.exportDomain]?.canExport); }
const mergeRecentActivity = () => {
  const local = activityStore.value.list();
  const server = (overview.value?.recentActivity?.items || []).map((item) => ({
    ...item,
    createdAt: item.createdAt || item.occurredAt || "",
    route: item.operationLogId
      ? { name: "operation-log-report", query: { source: "webmcp", modelKey: "all", query: String(item.operationLogId) } }
      : null,
  }));
  const seen = new Set();
  recentActivity.value = [...local, ...server]
    .filter((item) => {
      const key = `${item.id}:${item.createdAt}`;
      if (seen.has(key)) return false;
      seen.add(key);
      return true;
    })
    .sort((left, right) => String(right.createdAt).localeCompare(String(left.createdAt)))
    .slice(0, 20);
};
const recordActivity = (activity) => { activityStore.value.record(activity); mergeRecentActivity(); };
const reopenActivity = async (activity) => {
  if (activity.route) await router.push(activity.route);
  else if (activity.jobId) { const query = new URLSearchParams({ jobId: activity.jobId }); if (activity.exportKind) query.set("exportKind", activity.exportKind); window.open?.(`/#/webmcp/export-progress?${query.toString()}`, "_blank", "noopener"); }
};

const netFundingValue = (metric) => metric?.status !== "ready" ? "—" : !(metric.totals || []).length ? "0" : metric.totals.length > 1 ? `${metric.totals.length} currencies` : formatMoney(metric.totals[0].netFlow, metric.totals[0].currency);
const netFundingDetail = (metric) => {
  if (metric?.status === "error") return metric.message;
  const totals = metric?.totals || [];
  if (!totals.length) return "No completed deposits or withdrawals";
  if (totals.length === 1) return `${formatMoney(totals[0].deposits, totals[0].currency)} in · ${formatMoney(totals[0].withdrawals, totals[0].currency)} out`;
  return totals.slice(0, 2).map((item) => `${item.currency} ${formatNumber(item.netFlow)}`).join(" · ");
};
function formatMoney(value, currency = "USD") { try { return new Intl.NumberFormat(undefined, { style: "currency", currency, maximumFractionDigits: 0 }).format(Number(value) || 0); } catch { return `${currency} ${formatNumber(value)}`; } }
function formatNumber(value) { return new Intl.NumberFormat().format(Number(value) || 0); }
function formatAge(hours) { const amount = Math.max(0, Number(hours) || 0); return amount >= 24 ? `${Math.floor(amount / 24)}d ${Math.floor(amount % 24)}h old` : `${Math.floor(amount)}h old`; }
function formatTimestamp(value) { if (!value) return "Time unavailable"; const date = new Date(value); return Number.isNaN(date.getTime()) ? "Time unavailable" : new Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(date); }
function formatRelative(seconds) { return seconds < 60 ? `${seconds}s` : seconds < 3600 ? `${Math.floor(seconds / 60)}m` : `${Math.floor(seconds / 3600)}h`; }
function offsetLabel(offset) { const sign = offset < 0 ? "-" : "+"; const absolute = Math.abs(offset); return `UTC${sign}${String(Math.floor(absolute / 60)).padStart(2, "0")}:${String(absolute % 60).padStart(2, "0")}`; }
const activityIcon = (kind) => ({ export: "fas fa-file-arrow-down", open: "fas fa-arrow-up-right-from-square", investigation: "fas fa-magnifying-glass" })[kind] || "fas fa-clock";

onMounted(() => {
  modelContextSupported.value = typeof document !== "undefined" && typeof document.modelContext?.registerTool === "function";
  document.addEventListener("click", handleExportMenuOutside);
  recentActivity.value = activityStore.value.list();
  freshnessTimer = window.setInterval(() => { nowTick.value = Date.now(); }, 60000);
  loadOverview();
});
onUnmounted(() => { requestSequence++; closeInvestigation(); document.removeEventListener("click", handleExportMenuOutside); if (freshnessTimer !== null) window.clearInterval(freshnessTimer); });
</script>

<style scoped>
.runtime-switch.is-locked span{cursor:not-allowed;opacity:.8}
.webmcp-page{max-width:1440px;margin:0 auto;padding:8px 2px 42px}.operations-page-header{display:flex;align-items:flex-end;justify-content:space-between;gap:28px;padding:18px 0 22px;border-bottom:1px solid var(--color-border)}.operations-kicker,.operations-eyebrow{margin:0 0 7px;color:var(--color-brand);font-size:11px;font-weight:850;letter-spacing:.12em;text-transform:uppercase}.operations-page-header h1{display:flex;align-items:center;gap:12px;margin:0;color:var(--color-ink-strong);font-size:clamp(27px,3vw,38px);letter-spacing:-.045em}.operations-page-header h1 i{color:var(--color-brand);font-size:.72em}.operations-page-header>div>p:last-child{max-width:680px;margin:8px 0 0;color:var(--color-muted);font-size:14px}.operations-header-actions,.runtime-compact,.filter-cluster,.freshness-cluster,.scope-pill,.freshness,.attention-actions,.support-actions{display:flex;align-items:center;gap:10px}.runtime-compact{min-height:46px;padding:7px 10px;background:var(--color-surface);border:1px solid var(--color-border);border-radius:7px}.runtime-indicator{width:8px;height:8px;background:var(--color-muted);border-radius:50%;box-shadow:0 0 0 4px var(--color-surface-muted)}.runtime-compact.is-enabled .runtime-indicator{background:var(--color-success-solid);box-shadow:0 0 0 4px var(--color-success-soft)}.runtime-compact.is-unavailable .runtime-indicator{background:var(--color-warning-solid)}.runtime-compact small,.runtime-compact strong{display:block;white-space:nowrap}.runtime-compact small{color:var(--color-muted);font-size:10px;text-transform:uppercase}.runtime-compact strong{font-size:12px}.runtime-switch{position:relative;width:38px;height:22px}.runtime-switch input{position:absolute;opacity:0}.runtime-switch span{position:absolute;inset:0;cursor:pointer;background:var(--color-border-strong);border-radius:999px}.runtime-switch span:after{position:absolute;top:3px;left:3px;width:16px;height:16px;content:"";background:var(--color-surface);border-radius:50%;transition:transform .16s}.runtime-switch input:checked+span{background:var(--color-brand-solid)}.runtime-switch input:checked+span:after{transform:translateX(16px)}
.operations-quick-actions{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:12px 0 18px;padding:10px 14px;background:var(--color-surface);border:1px solid var(--color-border);border-left:4px solid var(--color-brand);border-radius:7px}.quick-actions-label{display:flex;align-items:baseline;gap:10px;flex-shrink:0}.quick-actions-label .operations-eyebrow{margin:0}.quick-actions-label span{color:var(--color-muted);font-size:11px}.quick-actions-list{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap}.quick-action-button{display:inline-flex;align-items:center;gap:7px;min-height:36px;padding:8px 11px;color:var(--color-brand);background:var(--color-surface);border:1px solid var(--color-border-strong);border-radius:5px;font:inherit;font-size:11px;font-weight:750;cursor:pointer}.quick-action-button:hover:not(:disabled){color:var(--color-brand-hover);background:var(--color-brand-soft);border-color:var(--color-brand)}.quick-action-button.is-primary{color:#fff;background:var(--color-brand-solid);border-color:var(--color-brand-solid)}.quick-action-button.is-primary:hover:not(:disabled){color:#fff;background:var(--color-brand-solid-hover);border-color:var(--color-brand-solid-hover)}.quick-action-button:focus-visible{outline:2px solid var(--color-focus-ring);outline-offset:2px}.quick-actions-export-menu{position:relative}.quick-action-chevron{font-size:9px}.quick-actions-menu{position:absolute;z-index:30;top:calc(100% + 6px);right:0;display:grid;min-width:225px;gap:2px;padding:5px;background:var(--color-surface);border:1px solid var(--color-border-strong);border-radius:6px;box-shadow:var(--shadow-lg)}.quick-actions-menu-item{display:flex;align-items:center;gap:9px;width:100%;padding:9px 10px;color:var(--color-ink);background:transparent;border:0;border-radius:4px;font:inherit;font-size:11px;font-weight:700;text-align:left;cursor:pointer}.quick-actions-menu-item i{width:15px;color:var(--color-brand);text-align:center}.quick-actions-menu-item:hover:not(:disabled){color:var(--color-brand);background:var(--color-brand-soft)}.quick-actions-menu-item:focus-visible{outline:2px solid var(--color-focus-ring);outline-offset:-2px}.operations-filter-bar{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:18px;padding:15px 16px;background:linear-gradient(135deg,var(--color-surface-soft),var(--color-surface));border:1px solid var(--color-border);border-left:4px solid var(--color-brand);border-radius:7px}.filter-cluster,.freshness-cluster{align-items:flex-end;flex-wrap:wrap}.operations-filter-bar label,.queue-toolbar label{display:grid;gap:5px;color:var(--color-muted);font-size:10px;font-weight:800;text-transform:uppercase}.operations-filter-bar input,.operations-filter-bar select,.queue-toolbar select{min-height:38px;padding:7px 10px;color:var(--color-ink);background:var(--color-surface);border:1px solid var(--color-border-strong);border-radius:5px;font:inherit;font-size:12px}.filter-arrow{padding:10px 2px;color:var(--color-faint)}.scope-pill,.freshness{min-height:38px;padding:7px 10px;color:var(--color-muted);background:var(--color-surface);border:1px solid var(--color-border);border-radius:5px;font-size:11px;font-weight:700}.scope-pill i{color:var(--color-brand)}.freshness i{color:var(--color-success)}.freshness.is-stale{color:var(--color-warning);background:var(--color-warning-soft);border-color:var(--color-warning-border)}.refresh-button,.evidence-button{min-height:38px;padding:8px 13px;color:#fff;background:var(--color-brand-solid);border:1px solid var(--color-brand-solid);border-radius:5px;font:inherit;font-size:12px;font-weight:750;cursor:pointer}.operations-alert{display:flex;align-items:center;gap:10px;margin-bottom:18px;padding:12px 14px;color:var(--color-danger);background:var(--color-danger-soft);border:1px solid var(--color-danger-border);border-radius:6px;font-size:12px}.operations-alert span{flex:1}.operations-alert button{color:currentColor;background:transparent;border:0;font-weight:800}.operations-metric-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}
.operations-attention-panel,.support-panel{background:var(--color-surface);border:1px solid var(--color-border);border-radius:8px;box-shadow:0 12px 28px rgba(15,23,42,.05)}.operations-attention-panel{margin-bottom:18px;overflow:hidden;scroll-margin-top:16px}.panel-heading,.support-heading,.evidence-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:18px}.panel-heading{padding:22px 24px 18px;border-bottom:1px solid var(--color-border)}.panel-heading h2,.support-heading h2,.evidence-heading h2{margin:0;color:var(--color-ink);font-size:20px}.panel-heading p:last-child{margin:6px 0 0;color:var(--color-muted);font-size:12px}.queue-summary{display:grid;text-align:right}.queue-summary strong{font-size:27px}.queue-summary span{color:var(--color-muted);font-size:10px;text-transform:uppercase}.queue-toolbar{display:flex;align-items:flex-end;gap:10px;padding:13px 24px;background:var(--color-surface-soft);border-bottom:1px solid var(--color-border)}.queue-toolbar .evidence-button{margin-left:auto}.attention-list{margin:0;padding:0;list-style:none}.attention-list li{--severity:var(--color-info);position:relative;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:24px;padding:17px 24px 17px 29px;border-bottom:1px solid var(--color-border)}.attention-list li.is-critical{--severity:var(--color-danger)}.attention-list li.is-high{--severity:var(--color-warning)}.severity-marker{position:absolute;inset:0 auto 0 0;width:4px;background:var(--severity)}.attention-title-row{display:flex;align-items:center;gap:8px}.severity-label{padding:3px 6px;color:var(--severity);background:color-mix(in srgb,var(--severity) 10%,transparent);border-radius:3px;font-size:9px;font-weight:850;text-transform:uppercase}.attention-age{color:var(--color-muted);font-size:10px}.attention-copy h3{margin:7px 0 0;font-size:14px}.attention-copy p{margin:4px 0;color:var(--color-muted);font-size:12px}.related-record{display:inline-flex;align-items:center;gap:6px;margin-top:6px;color:var(--color-text-secondary);font-size:11px}.attention-actions button,.support-heading button,.support-actions button{padding:7px 10px;color:var(--color-brand);background:transparent;border:1px solid var(--color-border-strong);border-radius:5px;font:inherit;font-size:11px;font-weight:750;cursor:pointer}.attention-actions .is-primary{color:#fff;background:var(--color-brand-solid);border-color:var(--color-brand-solid)}.queue-empty{display:grid;min-height:240px;place-items:center;align-content:center;gap:7px;color:var(--color-muted);text-align:center}.queue-empty>span{display:grid;width:42px;height:42px;place-items:center;color:var(--color-success);background:var(--color-success-soft);border-radius:50%}.queue-empty p{margin:0;font-size:12px}.queue-loading{display:grid;gap:1px;background:var(--color-border)}.queue-loading span{height:76px;background:linear-gradient(90deg,var(--color-surface),var(--color-surface-soft),var(--color-surface));background-size:200%;animation:shimmer 1.4s infinite}.queue-truncated{margin:0;padding:10px;color:var(--color-muted);background:var(--color-surface-soft);font-size:10px;text-align:center}
.operations-support-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px;align-items:stretch}.operations-support-grid>:first-child{grid-column:1/-1}.support-panel{min-width:0;padding:22px}.operations-support-grid>.support-panel:not(.activity-panel){max-height:520px;overflow-y:auto;scrollbar-color:var(--color-border-strong) var(--color-surface-muted)}.support-heading{padding-bottom:16px;border-bottom:1px solid var(--color-border)}.sales-summary,.ib-stats{display:grid;gap:1px;margin-top:15px;background:var(--color-border);border:1px solid var(--color-border);border-radius:6px}.sales-summary{grid-template-columns:repeat(2,1fr)}.ib-stats{grid-template-columns:repeat(3,1fr)}.sales-summary span,.ib-stats span{display:grid;gap:4px;padding:12px;background:var(--color-surface-soft)}.sales-summary small,.ib-stats small{color:var(--color-muted);font-size:9px;text-transform:uppercase}.sales-summary strong,.ib-stats strong{font-size:17px}.leader-list,.activity-list{margin:12px 0 0;padding:0;list-style:none}.leader-list li{display:grid;grid-template-columns:28px minmax(0,1fr) auto;align-items:center;gap:9px;padding:9px 0;border-bottom:1px solid var(--color-border)}.rank-number,.activity-icon{display:grid;width:25px;height:25px;place-items:center;color:var(--color-brand);background:var(--color-brand-soft);border-radius:4px;font-size:10px;font-weight:850}.leader-name{min-width:0}.leader-name strong,.leader-name small,.activity-list strong,.activity-list small{display:block}.leader-name strong{font-size:11px}.leader-name small,.activity-list small{color:var(--color-muted);font-size:9px}.leader-value{font-size:11px;font-weight:800;text-align:right}.leader-value small{display:block;margin-top:2px;color:var(--color-success);font-size:9px}.support-actions{margin-top:14px}.activity-panel{grid-column:1/-1}.local-history,.activity-note{color:var(--color-muted);font-size:10px}.activity-list li{display:grid;grid-template-columns:28px minmax(0,1fr) 26px;align-items:center;gap:9px;padding:9px 0;border-bottom:1px solid var(--color-border)}.activity-list strong{font-size:10px}.activity-list button{color:var(--color-brand);background:transparent;border:0}.compact-empty{padding:20px;color:var(--color-muted);background:var(--color-surface-soft);border:1px dashed var(--color-border-strong);border-radius:6px;font-size:11px;text-align:center}
.evidence-overlay{position:fixed;z-index:1200;inset:0;display:flex;justify-content:flex-end;overflow:hidden;background:rgba(7,15,30,.58);backdrop-filter:blur(3px)}.evidence-drawer{width:min(470px,100%);height:100%;overflow-y:auto;overscroll-behavior:contain;padding:26px;background:var(--color-surface);box-shadow:-18px 0 50px rgba(0,0,0,.2)}.evidence-heading{padding-bottom:19px;border-bottom:1px solid var(--color-border)}.evidence-heading button{width:34px;height:34px;color:var(--color-muted);background:var(--color-surface-soft);border:1px solid var(--color-border);border-radius:5px}.evidence-reason{margin-top:18px;padding:14px;background:var(--color-surface-soft);border-left:4px solid var(--color-warning)}.evidence-reason.is-critical{border-color:var(--color-danger)}.evidence-reason.is-medium{border-color:var(--color-info)}.evidence-reason span{font-size:9px;font-weight:850;text-transform:uppercase}.evidence-reason p{margin:5px 0;font-size:12px}.evidence-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1px;margin:18px 0;background:var(--color-border);border:1px solid var(--color-border)}.evidence-grid div{padding:12px;background:var(--color-surface)}.evidence-grid dt{color:var(--color-muted);font-size:9px;text-transform:uppercase}.evidence-grid dd{margin:5px 0;font-size:11px;overflow-wrap:anywhere}.evidence-policy{display:flex;gap:9px;padding:12px;color:var(--color-muted);background:var(--color-info-soft);border:1px solid var(--color-info-border);border-radius:6px;font-size:10px}.evidence-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:22px}.evidence-actions button{padding:9px 13px;background:var(--color-surface);border:1px solid var(--color-border-strong);border-radius:5px}.evidence-actions .is-primary{color:#fff;background:var(--color-brand-solid)}button:focus-visible,input:focus-visible,select:focus-visible{outline:2px solid var(--color-focus-ring);outline-offset:2px}button:disabled{opacity:.5;cursor:not-allowed}@keyframes shimmer{to{background-position:-200%}}
@media(max-width:1180px){.operations-metric-grid{grid-template-columns:repeat(2,1fr)}.operations-support-grid{grid-template-columns:1fr 1fr}.operations-support-grid>:first-child,.activity-panel{grid-column:1/-1}}@media(max-width:820px){.operations-page-header,.operations-filter-bar{align-items:flex-start;flex-direction:column}.operations-header-actions{width:100%;justify-content:space-between}.operations-support-grid{grid-template-columns:1fr}.operations-support-grid>:first-child,.activity-panel{grid-column:1/-1}.attention-list li{grid-template-columns:1fr}.queue-toolbar{align-items:stretch;flex-direction:column}.queue-toolbar .evidence-button{margin-left:0;align-self:flex-start}}@media(max-width:560px){.operations-metric-grid{grid-template-columns:1fr}.filter-cluster,.freshness-cluster{align-items:stretch;flex-direction:column;width:100%}.filter-arrow{display:none}.panel-heading{flex-direction:column}.queue-summary{text-align:left}.sales-summary,.ib-stats,.evidence-grid{grid-template-columns:1fr}}@media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important;animation-duration:.01ms!important}}
.queue-pagination{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 24px;background:var(--color-surface);border-top:1px solid var(--color-border)}.pagination-info{color:var(--color-muted);font-size:11px}.pagination-controls{display:flex;align-items:center;gap:5px;flex-wrap:wrap;justify-content:flex-end}.pagination-btn{display:inline-flex;align-items:center;gap:5px;min-height:32px;padding:6px 10px;color:var(--color-text);background:var(--color-surface);border:1px solid var(--color-border-strong);border-radius:5px;font:inherit;font-size:11px;font-weight:700;cursor:pointer}.pagination-btn:hover:not(:disabled){color:var(--color-brand);background:var(--color-brand-soft);border-color:var(--color-brand)}.pagination-btn.active{color:#fff;background:var(--color-brand-solid);border-color:var(--color-brand-solid)}.pagination-ellipsis{padding:6px;color:var(--color-muted);font-size:12px}
@media(max-width:820px){.operations-quick-actions{align-items:flex-start;flex-direction:column}.quick-actions-list{justify-content:flex-start;width:100%}}@media(max-width:560px){.quick-actions-list{align-items:stretch;flex-direction:column;width:100%}.quick-action-button{justify-content:center}.quick-actions-export-menu{width:100%}.quick-actions-export-menu>.quick-action-button{justify-content:center;width:100%}.quick-actions-menu{position:static;width:100%;min-width:0;margin-top:6px}}
.operations-overview .operations-filter-bar label,.operations-overview .queue-toolbar label{font-size:11px}.operations-overview .runtime-compact small{font-size:11px}.operations-overview .scope-pill,.operations-overview .freshness{font-size:12px}.operations-overview .queue-summary span{font-size:11px}.operations-overview .severity-label{font-size:10px}.operations-overview .attention-age{font-size:11px}.operations-overview .related-record{font-size:12px}.operations-overview .queue-truncated{font-size:11px}.operations-overview .sales-summary small,.operations-overview .ib-stats small{font-size:11px}.operations-overview .leader-name strong,.operations-overview .leader-value{font-size:12px}.operations-overview .leader-name small,.operations-overview .leader-value small{font-size:11px}.operations-overview .local-history,.operations-overview .activity-note{font-size:11px}.operations-overview .activity-list strong{font-size:11px}.operations-overview .activity-list small{font-size:10px}.operations-overview .compact-empty{font-size:12px}.operations-overview .evidence-grid dt{font-size:10px}.operations-overview .evidence-grid dd{font-size:12px}.operations-overview .evidence-policy{font-size:11px}.operations-overview .pagination-info,.operations-overview .pagination-btn{font-size:12px}.operations-overview .quick-action-button,.operations-overview .quick-actions-menu-item{font-size:12px}.operations-overview .quick-actions-label span{font-size:12px}
</style>
