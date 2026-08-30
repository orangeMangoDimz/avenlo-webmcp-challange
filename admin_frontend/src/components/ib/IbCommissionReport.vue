<template>
  <div class="ib-cr">
    <ExportProgressBanner
      v-if="exportBannerVisible && exportStatusText"
      :cancelling="exportCancelling"
      :status-text="exportStatusText"
      :percent="exportProgressPercent"
      :cancel-disabled="!exportJobId"
      :title="t('ibReport_exportInProgressTitle', 'Export in progress')"
      :cancelling-title="t('ibReport_exportCancelling', 'Cancelling export...')"
      :cancel-label="t('ibReport_exportCancel', 'Cancel')"
      @cancel-export="cancelActiveExport"
    />

    <div
      v-if="exportModal.visible"
      class="export-modal-overlay"
      @click="onExportModalContinue"
    >
      <div class="export-modal" @click.stop>
        <div class="export-modal-header">
          <h3>{{ t("ibReport_exportInProgressTitle") }}</h3>
          <button
            type="button"
            class="export-modal-close"
            @click="onExportModalContinue"
          >
            ×
          </button>
        </div>
        <div class="export-modal-body">
          <p class="export-modal-text">
            {{ exportModal.message || t("ibReport_exportInProgressMsg") }}
          </p>
          <div class="export-modal-progress">
            <div
              class="export-modal-progress-bar"
              :style="{ width: `${exportModal.percent || 0}%` }"
            ></div>
          </div>
          <p class="export-modal-percent">{{ exportModal.percent || 0 }}%</p>
        </div>
        <div class="export-modal-footer">
          <button
            type="button"
            class="export-modal-btn primary"
            @click="onExportModalContinue"
            :disabled="exportModal.busy"
          >
            {{ t("ibReport_exportContinue") }}
          </button>
          <button
            type="button"
            class="export-modal-btn secondary"
            @click="onExportModalCancel"
            :disabled="exportModal.busy"
          >
            {{ t("ibReport_exportCancel") }}
          </button>
        </div>
      </div>
    </div>

    <div
      class="ib-cr-header"
      style="
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
      "
    >
      <h3 class="ib-cr-title">
        <i class="fas fa-coins"></i>
        {{ t("ibCommReport_title", "Commission Report") }}
      </h3>
      <button
        class="ib-cr-detail-btn"
        :disabled="exportPolling || loading"
        @click="exportDetail"
        style="white-space: nowrap"
      >
        <i
          :class="exportPolling ? 'fas fa-spinner fa-spin' : 'fas fa-download'"
        ></i>
        {{ t("ibReport_export") }}
      </button>
    </div>

    <!-- 明细表 -->
    <div class="ib-cr-table-wrapper">
      <table class="ib-cr-table">
        <thead>
          <tr>
            <th>{{ t("ibCommReport_thReferral", "Referral") }}</th>
            <th>{{ t("ibCommReport_thType", "Type") }}</th>
            <th>
              {{ t("ibCommReport_thTotalCommission", "Total Commission") }}
            </th>
            <th>
              {{ t("ibCommReport_thClientsReferred", "Clients Referred") }}
            </th>
            <th>{{ t("ibCommReport_thTradingVolume", "Trading Volume") }}</th>
            <th>{{ t("ibCommReport_thLastPayout", "Last Payout") }}</th>
            <th>{{ t("ibCommReport_detail", "Detail") }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="7" class="ib-cr-empty">
              <i class="fas fa-spinner fa-spin"></i>
              {{ t("common_loading", "Loading...") }}
            </td>
          </tr>
          <tr v-else-if="!items.length">
            <td colspan="7" class="ib-cr-empty">
              {{ t("ibCommReport_empty", "No commission records found.") }}
            </td>
          </tr>
          <template v-else v-for="item in items" :key="getRowKey(item)">
            <tr
              :class="{
                'ib-cr-row-child': (item.depth || 0) > 0,
                expanded: expandedItemId === getRowKey(item),
              }"
            >
              <td>
                <div
                  class="ib-cr-referral"
                  :style="{ paddingLeft: (item.depth || 0) * 18 + 'px' }"
                >
                  <div class="ib-cr-avatar">
                    {{ item.initials || initials(item.name) }}
                  </div>
                  <div class="ib-cr-referral-meta">
                    <div class="ib-cr-referral-name">
                      {{ item.name || item.referralName }}
                      <span v-if="item.isSelf" class="ib-cr-self">{{
                        t("ibCommReport_self", "Self")
                      }}</span>
                    </div>
                    <div class="ib-cr-referral-code">
                      {{ item.referralCode }}
                    </div>
                  </div>
                </div>
              </td>
              <td>
                <span
                  :class="[
                    'ib-cr-type',
                    (item.type || '').toLowerCase().replace(/\s+/g, '-'),
                  ]"
                  >{{ item.type }}</span
                >
              </td>
              <td class="ib-cr-amount">
                {{
                  formatCurrency(
                    item.totalCommission ?? item.commissionEarned ?? 0,
                  )
                }}
              </td>
              <td>{{ item.clientsReferred ?? 0 }}</td>
              <td>{{ formatNumber(item.tradingVolume ?? 0, 2) }}</td>
              <td>{{ item.lastPayout || "--" }}</td>
              <td>
                <button
                  class="ib-cr-detail-btn"
                  @click="toggleDetails(item)"
                  :disabled="loadingDetail[getRowKey(item)]"
                >
                  <i
                    v-if="loadingDetail[getRowKey(item)]"
                    class="fas fa-spinner fa-spin"
                  ></i>
                  <i
                    v-else
                    :class="
                      expandedItemId === getRowKey(item)
                        ? 'fas fa-chevron-up'
                        : 'fas fa-chevron-down'
                    "
                  ></i>
                  {{
                    expandedItemId === getRowKey(item)
                      ? t("ibCommReport_hide", "Hide")
                      : t("ibCommReport_detail", "Detail")
                  }}
                </button>
              </td>
            </tr>
            <tr
              v-if="expandedItemId === getRowKey(item)"
              class="ib-cr-detail-row"
            >
              <td colspan="7">
                <div class="ib-cr-detail-content">
                  <div
                    v-if="loadingDetail[getRowKey(item)]"
                    class="ib-cr-detail-loading"
                  >
                    <i class="fas fa-spinner fa-spin"></i>
                    {{ t("common_loading", "Loading...") }}
                  </div>
                  <template v-else>
                    <!-- Sub-IB：统计四宫格 + Commission Breakdown 明细 -->
                    <template v-if="item.type === 'Sub-IB'">
                      <div
                        v-if="item.detailStatistics"
                        class="ib-cr-detail-grid"
                      >
                        <div class="ib-cr-detail-stat">
                          <span class="ib-cr-detail-stat-label">{{
                            t(
                              "ibCommReport_totalCommission",
                              "Total Commission",
                            )
                          }}</span>
                          <span class="ib-cr-detail-stat-value">{{
                            formatCurrency(
                              item.detailStatistics.totalCommission ??
                                item.detailStatistics.totalEarned ??
                                0,
                            )
                          }}</span>
                        </div>
                        <div class="ib-cr-detail-stat">
                          <span class="ib-cr-detail-stat-label">{{
                            t("ibCommReport_paidCommission", "Paid Commission")
                          }}</span>
                          <span class="ib-cr-detail-stat-value">{{
                            formatCurrency(
                              item.detailStatistics.paidCommission ??
                                item.detailStatistics.withdrawn ??
                                0,
                            )
                          }}</span>
                        </div>
                        <div class="ib-cr-detail-stat">
                          <span class="ib-cr-detail-stat-label">{{
                            t("ibCommReport_pendingPayout", "Pending Payout")
                          }}</span>
                          <span class="ib-cr-detail-stat-value">{{
                            formatCurrency(
                              item.detailStatistics.pendingCommission ??
                                item.detailStatistics.pending ??
                                0,
                            )
                          }}</span>
                        </div>
                        <div class="ib-cr-detail-stat">
                          <span class="ib-cr-detail-stat-label">{{
                            t("ibCommReport_totalClients", "Total Clients")
                          }}</span>
                          <span class="ib-cr-detail-stat-value">{{
                            item.detailStatistics.totalClients ?? 0
                          }}</span>
                        </div>
                      </div>
                      <h4 class="ib-cr-detail-title">
                        <i class="fas fa-list"></i>
                        {{
                          t("ibReport_breakdown_title", "Commission Breakdown")
                        }}
                      </h4>
                      <div
                        v-if="item.breakdown && item.breakdown.length > 0"
                        class="ib-cr-detail-table-scroll"
                      >
                        <table class="ib-cr-detail-table">
                          <thead>
                            <tr>
                              <th>
                                {{ t("ibReport_breakdown_th_date", "Date") }}
                              </th>
                              <th>
                                {{
                                  t(
                                    "ibReport_breakdown_th_accountNumber",
                                    "Account Number",
                                  )
                                }}
                              </th>
                              <th>
                                {{ t("ibReport_breakdown_th_name", "Name") }}
                              </th>
                              <th>
                                {{
                                  t(
                                    "ibReport_breakdown_th_accountOwner",
                                    "Account Owner",
                                  )
                                }}
                              </th>
                              <th>
                                {{ t("ibReport_breakdown_th_email", "Email") }}
                              </th>
                              <th>
                                {{ t("ibReport_breakdown_th_kyc", "KYC") }}
                              </th>
                              <th>
                                {{
                                  t(
                                    "ibReport_breakdown_th_tradeDate",
                                    "Trade Date",
                                  )
                                }}
                              </th>
                              <th>
                                {{ t("ibReport_breakdown_th_tradingId", "ID") }}
                              </th>
                              <th>
                                {{
                                  t("ibReport_breakdown_th_symbol", "Symbol")
                                }}
                              </th>
                              <th>
                                {{ t("ibReport_breakdown_th_lots", "Lots") }}
                              </th>
                              <th>
                                {{
                                  t(
                                    "ibReport_breakdown_th_lastDepositTime",
                                    "Last Deposit Time",
                                  )
                                }}
                              </th>
                              <th>
                                {{
                                  t("ibReport_breakdown_th_amount", "Amount")
                                }}
                              </th>
                              <th>
                                {{
                                  t(
                                    "ibReport_breakdown_th_accountType",
                                    "Account Type",
                                  )
                                }}
                              </th>
                              <th>
                                {{
                                  t(
                                    "ibReport_breakdown_th_baseCurrency",
                                    "Base Currency",
                                  )
                                }}
                              </th>
                              <th>
                                {{
                                  t("ibReport_breakdown_th_balance", "Balance")
                                }}
                              </th>
                              <th>
                                {{
                                  t(
                                    "ibReport_breakdown_th_profitLoss",
                                    "Profit/Loss",
                                  )
                                }}
                              </th>
                              <th>
                                {{
                                  t("ibReport_breakdown_th_status", "Status")
                                }}
                              </th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr
                              v-for="(row, index) in item.breakdown"
                              :key="index"
                            >
                              <td>{{ row.date || "—" }}</td>
                              <td>{{ row.accountNumber || "—" }}</td>
                              <td>{{ row.name || "—" }}</td>
                              <td>{{ row.accountOwner || "—" }}</td>
                              <td>{{ row.email || "—" }}</td>
                              <td>{{ row.kyc || "—" }}</td>
                              <td>{{ row.tradeDate || "—" }}</td>
                              <td>{{ row.tradingId || "—" }}</td>
                              <td>{{ row.symbol || "—" }}</td>
                              <td>{{ formatNumber(row.lots || 0, 2) }}</td>
                              <td>{{ row.lastDepositTime || "—" }}</td>
                              <td>{{ formatCurrency(row.amount || 0) }}</td>
                              <td>{{ row.accountType || "—" }}</td>
                              <td>{{ row.baseCurrency || "—" }}</td>
                              <td>{{ formatCurrency(row.balance || 0) }}</td>
                              <td>{{ formatCurrency(row.profitLoss || 0) }}</td>
                              <td>
                                <span
                                  :class="[
                                    'ib-cr-status',
                                    (row.status || 'pending').toLowerCase(),
                                  ]"
                                  >{{
                                    statusDisplay(row.status || "pending")
                                  }}</span
                                >
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                      <div v-else class="ib-cr-detail-empty">
                        {{
                          t(
                            "ibReport_no_breakdown",
                            "No commission breakdown available for this period.",
                          )
                        }}
                      </div>
                      <div
                        v-if="item.breakdownTotal > 0"
                        class="ib-cr-detail-pagination"
                      >
                        <button
                          class="ib-cr-page-btn"
                          :disabled="
                            (item.breakdownPage || 1) <= 1 ||
                            loadingDetail[getRowKey(item)]
                          "
                          @click="
                            goToBreakdownPage(
                              item,
                              (item.breakdownPage || 1) - 1,
                            )
                          "
                        >
                          <i class="fas fa-chevron-left"></i>
                          {{ t("ibReport_pagination_previous", "Previous") }}
                        </button>
                        <span class="ib-cr-page-info"
                          >{{ item.breakdownPage || 1 }} /
                          {{ item.breakdownTotalPages || 1 }}</span
                        >
                        <button
                          class="ib-cr-page-btn"
                          :disabled="
                            (item.breakdownPage || 1) >=
                              (item.breakdownTotalPages || 1) ||
                            loadingDetail[getRowKey(item)]
                          "
                          @click="
                            goToBreakdownPage(
                              item,
                              (item.breakdownPage || 1) + 1,
                            )
                          "
                        >
                          {{ t("ibReport_pagination_next", "Next") }}
                          <i class="fas fa-chevron-right"></i>
                        </button>
                      </div>
                    </template>

                    <!-- Direct Client：Orders & Commission 订单明细 -->
                    <template v-else>
                      <h4 class="ib-cr-detail-title">
                        <i class="fas fa-file-invoice-dollar"></i>
                        {{
                          t(
                            "ibCommReport_ordersCommission",
                            "Orders & Commission",
                          )
                        }}
                      </h4>
                      <div
                        v-if="item.orders && item.orders.length > 0"
                        class="ib-cr-detail-table-scroll"
                      >
                        <table class="ib-cr-detail-table">
                          <thead>
                            <tr>
                              <th>
                                {{ t("ibReport_breakdown_th_date", "Date") }}
                              </th>
                              <th>
                                {{
                                  t(
                                    "ibReport_breakdown_th_accountNumber",
                                    "Account Number",
                                  )
                                }}
                              </th>
                              <th>
                                {{ t("ibReport_breakdown_th_name", "Name") }}
                              </th>
                              <th>
                                {{
                                  t(
                                    "ibReport_breakdown_th_accountOwner",
                                    "Account Owner",
                                  )
                                }}
                              </th>
                              <th>
                                {{ t("ibReport_breakdown_th_email", "Email") }}
                              </th>
                              <th>
                                {{ t("ibReport_breakdown_th_kyc", "KYC") }}
                              </th>
                              <th>
                                {{
                                  t(
                                    "ibReport_breakdown_th_tradeDate",
                                    "Trade Date",
                                  )
                                }}
                              </th>
                              <th>
                                {{ t("ibReport_breakdown_th_tradingId", "ID") }}
                              </th>
                              <th>
                                {{
                                  t("ibReport_breakdown_th_symbol", "Symbol")
                                }}
                              </th>
                              <th>
                                {{ t("ibReport_breakdown_th_lots", "Lots") }}
                              </th>
                              <th>
                                {{
                                  t(
                                    "ibReport_breakdown_th_lastDepositTime",
                                    "Last Deposit Time",
                                  )
                                }}
                              </th>
                              <th>
                                {{
                                  t("ibReport_breakdown_th_amount", "Amount")
                                }}
                              </th>
                              <th>
                                {{
                                  t(
                                    "ibReport_breakdown_th_accountType",
                                    "Account Type",
                                  )
                                }}
                              </th>
                              <th>
                                {{
                                  t(
                                    "ibReport_breakdown_th_baseCurrency",
                                    "Base Currency",
                                  )
                                }}
                              </th>
                              <th>
                                {{
                                  t("ibReport_breakdown_th_balance", "Balance")
                                }}
                              </th>
                              <th>
                                {{
                                  t(
                                    "ibReport_breakdown_th_profitLoss",
                                    "Profit/Loss",
                                  )
                                }}
                              </th>
                              <th>
                                {{
                                  t("ibReport_breakdown_th_status", "Status")
                                }}
                              </th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr
                              v-for="(row, index) in item.orders"
                              :key="index"
                            >
                              <td>{{ row.date || "—" }}</td>
                              <td>{{ row.accountNumber || "—" }}</td>
                              <td>{{ row.name || "—" }}</td>
                              <td>{{ row.accountOwner || "—" }}</td>
                              <td>{{ row.email || "—" }}</td>
                              <td>{{ row.kyc || "—" }}</td>
                              <td>{{ row.tradeDate || "—" }}</td>
                              <td>{{ row.tradingId || "—" }}</td>
                              <td>{{ row.symbol || "—" }}</td>
                              <td>{{ formatNumber(row.lots || 0, 2) }}</td>
                              <td>{{ row.lastDepositTime || "—" }}</td>
                              <td>{{ formatCurrency(row.amount || 0) }}</td>
                              <td>{{ row.accountType || "—" }}</td>
                              <td>{{ row.baseCurrency || "—" }}</td>
                              <td>{{ formatCurrency(row.balance || 0) }}</td>
                              <td>{{ formatCurrency(row.profitLoss || 0) }}</td>
                              <td>
                                <span
                                  :class="[
                                    'ib-cr-status',
                                    (row.status || 'pending').toLowerCase(),
                                  ]"
                                  >{{
                                    statusDisplay(row.status || "pending")
                                  }}</span
                                >
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                      <div v-else class="ib-cr-detail-empty">
                        {{
                          t(
                            "ibCommReport_noOrderData",
                            "No order data available for this period.",
                          )
                        }}
                      </div>
                      <div
                        v-if="item.orders && item.orders.length > 0"
                        class="ib-cr-detail-total"
                      >
                        <strong
                          >{{
                            t(
                              "ibCommReport_totalCommission",
                              "Total Commission",
                            )
                          }}:</strong
                        >
                        {{ formatCurrency(item.detailTotalCommission ?? 0) }}
                      </div>
                      <div
                        v-if="item.ordersTotal > 0"
                        class="ib-cr-detail-pagination"
                      >
                        <button
                          class="ib-cr-page-btn"
                          :disabled="
                            (item.ordersPage || 1) <= 1 ||
                            loadingDetail[getRowKey(item)]
                          "
                          @click="
                            goToOrdersPage(item, (item.ordersPage || 1) - 1)
                          "
                        >
                          <i class="fas fa-chevron-left"></i>
                          {{ t("ibReport_pagination_previous", "Previous") }}
                        </button>
                        <span class="ib-cr-page-info"
                          >{{ item.ordersPage || 1 }} /
                          {{ item.ordersTotalPages || 1 }}</span
                        >
                        <button
                          class="ib-cr-page-btn"
                          :disabled="
                            (item.ordersPage || 1) >=
                              (item.ordersTotalPages || 1) ||
                            loadingDetail[getRowKey(item)]
                          "
                          @click="
                            goToOrdersPage(item, (item.ordersPage || 1) + 1)
                          "
                        >
                          {{ t("ibReport_pagination_next", "Next") }}
                          <i class="fas fa-chevron-right"></i>
                        </button>
                      </div>
                    </template>
                  </template>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <div class="ib-cr-pagination" v-if="totalPages > 1">
      <button
        class="ib-cr-page-btn"
        :disabled="page <= 1 || loading"
        @click="changePage(page - 1)"
      >
        <i class="fas fa-chevron-left"></i>
      </button>
      <span class="ib-cr-page-info">{{ page }} / {{ totalPages }}</span>
      <button
        class="ib-cr-page-btn"
        :disabled="page >= totalPages || loading"
        @click="changePage(page + 1)"
      >
        <i class="fas fa-chevron-right"></i>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from "vue";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { useAsyncReportExport } from "@/composables/useAsyncReportExport";
import { clientService } from "@/services/clientListService";
import { formatCurrency, formatNumber } from "@/utils/helpers";
import ExportProgressBanner from "@/components/common/ExportProgressBanner.vue";

const { t } = useAdminI18n();

const props = defineProps({
  ibPartnerId: {
    type: [Number, String],
    default: null,
  },
});

const items = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(20);
const loading = ref(false);

const {
  exportPolling,
  exportJobId,
  exportStatusText,
  exportBannerVisible,
  exportCancelling,
  exportModal,
  lastExportProgress,
  startOrResumeExport,
  resumeActiveExportIfAny,
  cancelActiveExport,
  onExportModalContinue,
  onExportModalCancel,
} = useAsyncReportExport({
  getActiveExport: () => clientService.getActiveIbCommissionDetailExport(),
  enqueueExport: (params) =>
    clientService.enqueueIbCommissionDetailExport(props.ibPartnerId, params),
  getExportStatus: (jobId) =>
    clientService.getIbCommissionDetailExportStatus(jobId),
  cancelExport: (jobId) => clientService.cancelIbCommissionDetailExport(jobId),
  downloadExport: (jobId) =>
    clientService.downloadIbCommissionDetailExport(jobId),
  buildFilename: () =>
    `ib_commission_detail_${props.ibPartnerId}_${new Date().toISOString().split("T")[0]}.csv`,
  t,
});

const exportProgressPercent = computed(() =>
  Math.max(0, Math.min(100, Number(lastExportProgress.value?.percent || 0))),
);

const expandedItemId = ref(null);
const loadingDetail = ref({});
const ORDERS_DEFAULT_PER_PAGE = 20;

const totalPages = computed(() =>
  perPage.value > 0 ? Math.ceil(total.value / perPage.value) : 1,
);

const getRowKey = (item) => `${item.type || ""}#${item.id}`;

const statusDisplay = (status) => {
  const s = (status || "").toLowerCase();
  const map = {
    pending: "ibReport_orderStatus_pending",
    approved: "ibReport_orderStatus_approved",
    completed: "ibReport_orderStatus_completed",
    cancelled: "ibReport_orderStatus_cancelled",
  };
  const k = map[s];
  if (k) return t(k);
  return status
    ? status.charAt(0).toUpperCase() + status.slice(1).toLowerCase()
    : "—";
};

const exportDetail = async () => {
  if (!props.ibPartnerId || exportPolling.value) return;
  await startOrResumeExport(() => ({}));
};

// Direct Client：拉一页订单明细，写回 item.orders / item.ordersPage 等
const loadOrdersPage = async (item, page = 1) => {
  const key = getRowKey(item);
  loadingDetail.value[key] = true;
  try {
    const perPage = item.ordersPerPage || ORDERS_DEFAULT_PER_PAGE;
    const response = await clientService.getIbCommissionDetail(
      props.ibPartnerId,
      {
        referral_id: item.id,
        type: item.type,
        page,
        per_page: perPage,
      },
    );
    const data = response?.data || {};
    item.orders = Array.isArray(data.orders) ? data.orders : [];
    item.detailTotalCommission = data.totalCommission ?? 0;
    item.ordersPage = data.pagination?.page ?? page;
    item.ordersPerPage = data.pagination?.perPage ?? perPage;
    item.ordersTotal = data.pagination?.total ?? item.orders.length;
    item.ordersTotalPages = data.pagination?.totalPages ?? 1;
    item.detailStatistics = null;
    item.breakdown = [];
  } catch (error) {
    console.error("Failed to fetch commission detail:", error);
    item.orders = [];
    item.detailTotalCommission = 0;
    item.ordersPage = 1;
    item.ordersTotal = 0;
    item.ordersTotalPages = 0;
  } finally {
    loadingDetail.value[key] = false;
  }
};

const goToOrdersPage = (item, page) => {
  const total = item.ordersTotalPages || 1;
  const n = Math.max(1, Math.min(total, Number(page) || 1));
  if (n === item.ordersPage) return;
  loadOrdersPage(item, n);
};

// Sub-IB：拉一页 Commission Breakdown，统计四宫格随首页一起带回
const loadBreakdownPage = async (item, page = 1) => {
  const key = getRowKey(item);
  loadingDetail.value[key] = true;
  try {
    const perPage = item.breakdownPerPage || ORDERS_DEFAULT_PER_PAGE;
    const response = await clientService.getIbCommissionDetail(
      props.ibPartnerId,
      {
        referral_id: item.id,
        type: item.type,
        page,
        per_page: perPage,
      },
    );
    const data = response?.data || {};
    item.detailStatistics = data.statistics ?? null;
    item.breakdown = Array.isArray(data.breakdown) ? data.breakdown : [];
    item.breakdownPage = data.pagination?.page ?? page;
    item.breakdownPerPage = data.pagination?.perPage ?? perPage;
    item.breakdownTotal = data.pagination?.total ?? item.breakdown.length;
    item.breakdownTotalPages = data.pagination?.totalPages ?? 1;
    item.orders = null;
    item.detailTotalCommission = null;
  } catch (error) {
    console.error("Failed to fetch commission detail:", error);
    item.breakdown = [];
    item.detailStatistics = null;
    item.breakdownPage = 1;
    item.breakdownTotal = 0;
    item.breakdownTotalPages = 0;
  } finally {
    loadingDetail.value[key] = false;
  }
};

const goToBreakdownPage = (item, page) => {
  const total = item.breakdownTotalPages || 1;
  const n = Math.max(1, Math.min(total, Number(page) || 1));
  if (n === item.breakdownPage) return;
  loadBreakdownPage(item, n);
};

const toggleDetails = async (item) => {
  const key = getRowKey(item);
  if (expandedItemId.value === key) {
    expandedItemId.value = null;
    return;
  }
  expandedItemId.value = key;

  if (item.type === "Sub-IB") {
    // Sub-IB 首次展开拉第一页 breakdown
    if (item.detailStatistics == null) {
      await loadBreakdownPage(item, 1);
    }
    return;
  }
  // Direct Client 首次展开拉第一页 orders
  if (item.orders === undefined) {
    await loadOrdersPage(item, 1);
  }
};

const initials = (name) => {
  if (!name) return "?";
  return name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((s) => s[0])
    .join("")
    .toUpperCase();
};

const loadList = async (ibId) => {
  loading.value = true;
  try {
    const response = await clientService.getIbCommissionList(ibId, {
      page: page.value,
      per_page: perPage.value,
    });
    const data = response?.data || {};
    items.value = Array.isArray(data.items) ? data.items : [];
    total.value = data.pagination?.total ?? items.value.length;
  } catch (error) {
    console.error("Failed to load IB commission list:", error);
    items.value = [];
    total.value = 0;
  } finally {
    loading.value = false;
  }
};

const load = async () => {
  const ibId = props.ibPartnerId;
  if (ibId === null || ibId === undefined || ibId === "") {
    items.value = [];
    total.value = 0;
    return;
  }
  await loadList(ibId);
};

const changePage = (next) => {
  if (next < 1 || next > totalPages.value) return;
  page.value = next;
  expandedItemId.value = null;
  loadList(props.ibPartnerId);
};

watch(
  () => props.ibPartnerId,
  async (ibId) => {
    page.value = 1;
    load();
    if (ibId !== null && ibId !== undefined && ibId !== "") {
      await resumeActiveExportIfAny();
    }
  },
  { immediate: true },
);
</script>

<style scoped>
.ib-cr {
  margin-top: 24px;
}
.ib-cr-header {
  margin-bottom: 12px;
}
.ib-cr-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
}
.ib-cr-title i {
  color: var(--color-info);
}
.ib-cr-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}
.ib-cr-stat {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.ib-cr-stat-label {
  font-size: 14px;
  color: var(--color-muted);
}
.ib-cr-stat-value {
  font-size: 20px;
  font-weight: 700;
  color: var(--color-ink);
}
.ib-cr-table-wrapper {
  overflow-x: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}
.ib-cr-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}
.ib-cr-table th,
.ib-cr-table td {
  padding: 10px 12px;
  text-align: left;
  border-bottom: 1px solid #f1f5f9;
  white-space: nowrap;
}
.ib-cr-table th {
  background: var(--color-surface-soft);
  color: var(--color-muted);
  font-weight: 600;
  text-transform: uppercase;
  font-size: 14px;
}
.ib-cr-row-child {
  background: var(--color-surface-soft);
}
.ib-cr-empty {
  text-align: center;
  color: var(--color-faint);
  padding: 24px;
}
.ib-cr-referral {
  display: flex;
  align-items: center;
  gap: 10px;
}
.ib-cr-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--color-info-soft);
  color: var(--color-info);
  font-size: 14px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.ib-cr-referral-name {
  font-weight: 600;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 6px;
}
.ib-cr-self {
  background: var(--color-info-soft);
  color: var(--color-info);
  font-size: 14px;
  padding: 1px 6px;
  border-radius: var(--radius-md);
}
.ib-cr-referral-code {
  font-size: 14px;
  color: var(--color-faint);
}
.ib-cr-type {
  display: inline-block;
  padding: 2px 8px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  background: var(--color-surface-soft);
  color: var(--color-text);
}
.ib-cr-type.sub-ib {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}
.ib-cr-type.direct-client {
  background: var(--color-success-soft);
  color: var(--color-success);
}
.ib-cr-amount {
  font-weight: 600;
  color: var(--color-success);
}
.ib-cr-pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 12px;
}
.ib-cr-page-btn {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  width: 32px;
  height: 32px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  color: var(--color-text);
}
.ib-cr-page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.ib-cr-page-info {
  font-size: 14px;
  color: var(--color-muted);
}

/* Detail 展开 */
.ib-cr-detail-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  font-size: 14px;
  color: var(--color-info);
  background: var(--color-info-soft);
  border: 1px solid #bfdbfe;
  border-radius: var(--radius-sm);
  cursor: pointer;
  white-space: nowrap;
}
.ib-cr-detail-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.ib-cr-detail-row > td {
  background: var(--color-surface-soft);
  padding: 16px;
  /* max-width:0 让超宽的明细表在内部滚动，而不是把整个外层表撑宽 */
  max-width: 0;
}
.ib-cr-detail-loading {
  text-align: center;
  padding: 20px;
  color: var(--color-muted);
}
.ib-cr-detail-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}
.ib-cr-detail-stat {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}
.ib-cr-detail-stat-label {
  font-size: 14px;
  color: var(--color-muted);
}
.ib-cr-detail-stat-value {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-ink);
}
.ib-cr-detail-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 12px 0 8px;
}
.ib-cr-detail-title i {
  color: var(--color-info);
}
.ib-cr-detail-table-scroll {
  overflow-x: auto;
  max-width: 100%;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}
.ib-cr-detail-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
  white-space: nowrap;
}
.ib-cr-detail-table th,
.ib-cr-detail-table td {
  padding: 8px 12px;
  text-align: left;
  border-bottom: 1px solid #f1f5f9;
}
.ib-cr-detail-table th {
  background: var(--color-surface-soft);
  color: var(--color-muted);
  font-weight: 600;
}
.ib-cr-detail-empty {
  padding: 16px;
  text-align: center;
  color: var(--color-faint);
  font-size: 14px;
}
.ib-cr-detail-total {
  margin-top: 10px;
  font-size: 14px;
  color: var(--color-ink);
}
.ib-cr-detail-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-top: 12px;
}
.ib-cr-status {
  display: inline-block;
  padding: 2px 8px;
  border-radius: var(--radius-md);
  font-size: 14px;
  text-transform: capitalize;
  background: var(--color-surface-soft);
  color: var(--color-text);
}
.ib-cr-status.completed {
  background: var(--color-success-soft);
  color: var(--color-success);
}
.ib-cr-status.pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}
.ib-cr-status.approved {
  background: var(--color-info-soft);
  color: var(--color-info);
}
.ib-cr-status.cancelled {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.export-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  z-index: 2000;
}

.export-modal {
  width: min(100%, 480px);
  background: var(--color-surface);
  border-radius: 18px;
  box-shadow: 0 25px 60px rgba(15, 23, 42, 0.2);
  overflow: hidden;
}

.export-modal-header,
.export-modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 22px;
}

.export-modal-header {
  border-bottom: 1px solid var(--color-border);
}

.export-modal-header h3 {
  margin: 0;
  font-size: 18px;
  color: var(--color-ink);
}

.export-modal-close {
  border: none;
  background: transparent;
  font-size: 24px;
  line-height: 1;
  cursor: pointer;
  color: var(--color-muted);
}

.export-modal-body {
  padding: 20px 22px;
}

.export-modal-text {
  margin: 0 0 16px;
  color: var(--color-text);
  font-size: 14px;
  line-height: 1.5;
}

.export-modal-progress {
  height: 8px;
  background: var(--color-border);
  border-radius: 999px;
  overflow: hidden;
}

.export-modal-progress-bar {
  height: 100%;
  background: var(--color-brand-solid);
  transition: width 0.3s ease;
}

.export-modal-percent {
  margin: 10px 0 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
}

.export-modal-footer {
  border-top: 1px solid var(--color-border);
  gap: 12px;
  justify-content: flex-end;
}

.export-modal-btn {
  padding: 10px 16px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: none;
}

.export-modal-btn.secondary {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  color: var(--color-text);
}

.export-modal-btn.primary {
  background: var(--color-brand-solid);
  color: #fff;
}

.export-modal-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
