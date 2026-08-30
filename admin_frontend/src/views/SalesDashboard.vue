<template>
  <div class="sales-dashboard-page">
    <div class="sales-dashboard-header">
      <div class="sales-dashboard-title">
        <h1>{{ t("page_salesDashboard_title") }}</h1>
        <p>{{ t("page_salesDashboard_sub") }}</p>
      </div>
      <div class="sales-dashboard-actions">
        <PageHeaderActions />
      </div>
    </div>

    <div v-if="loadingMe" class="sd-loading">{{ t("common_loading") }}</div>
    <div v-else-if="!currentSales" class="sd-no-sales"></div>
    <template v-else>
      <!-- Monthly performance: same metrics as Daily Report, for the whole month -->
      <div class="sd-perf-card">
        <div class="sd-perf-head">
          <h3>
            <i class="fas fa-chart-line"></i>
            {{ t("salesDash_perf_title", "Monthly Performance") }}
          </h3>
          <el-config-provider :locale="elementPlusLocale">
            <div class="sd-perf-controls">
              <el-date-picker
                v-model="perfMonth"
                type="month"
                value-format="YYYY-MM"
                :clearable="false"
                :disabled-date="disableFutureMonth"
                :placeholder="t('salesDash_perf_month', 'Month')"
                class="sd-perf-month"
                @change="loadMonthlyPerformance"
              />
              <span
                class="sd-perf-tz"
                :title="
                  t(
                    'salesDash_perf_tzHint',
                    'Month boundaries follow this timezone',
                  )
                "
              >
                <i class="fas fa-globe"></i> {{ perfTimezoneLabel }}
              </span>
              <span class="sd-perf-note">
                <i class="fas fa-circle-check"></i>
                {{
                  t(
                    "salesDash_perf_completedOnly",
                    "Completed transactions only",
                  )
                }}
              </span>
            </div>
          </el-config-provider>
        </div>

        <div v-if="perfError" class="sd-perf-error">
          <i class="fas fa-exclamation-triangle"></i> {{ perfError }}
        </div>

        <div class="sd-perf-grid" :class="{ loading: loadingPerf }">
          <div class="sd-perf-item deposit">
            <div class="sd-perf-label">
              {{ t("salesDash_perf_deposit", "Deposit") }}
            </div>
            <div class="sd-perf-value">{{ formatMoney(perf.deposits) }}</div>
            <div class="sd-perf-sub">
              {{
                tParams("salesDash_perf_txCount", "{count} transactions", {
                  count: perf.depositCount ?? 0,
                })
              }}
            </div>
          </div>
          <div class="sd-perf-item withdrawal">
            <div class="sd-perf-label">
              {{ t("salesDash_perf_withdrawal", "Withdrawal") }}
            </div>
            <div class="sd-perf-value">{{ formatMoney(perf.withdrawals) }}</div>
            <div class="sd-perf-sub">
              {{
                tParams("salesDash_perf_txCount", "{count} transactions", {
                  count: perf.withdrawalCount ?? 0,
                })
              }}
            </div>
          </div>
          <div class="sd-perf-item net">
            <div class="sd-perf-label">
              {{ t("salesDash_perf_netDeposit", "Net Deposit") }}
            </div>
            <div class="sd-perf-value" :class="netDepositClass">
              {{ formatSignedMoney(perf.netDeposit) }}
            </div>
            <div class="sd-perf-sub">
              {{
                t(
                  "salesDash_perf_depositMinusWithdrawal",
                  "Deposit minus withdrawal",
                )
              }}
            </div>
          </div>
          <div class="sd-perf-item leads">
            <div class="sd-perf-label">
              {{ t("salesDash_perf_newLeads", "New Leads") }}
            </div>
            <div class="sd-perf-value">{{ perf.newLeads ?? 0 }}</div>
            <div class="sd-perf-sub">
              {{
                t("salesDash_perf_registeredThisMonth", "Registered this month")
              }}
            </div>
          </div>
          <div class="sd-perf-item clients">
            <div class="sd-perf-label">
              {{ t("salesDash_perf_newClients", "New Clients") }}
            </div>
            <div class="sd-perf-value">{{ perf.newClients ?? 0 }}</div>
            <div class="sd-perf-sub">
              {{ t("salesDash_perf_kycApproved", "KYC approved") }}
            </div>
          </div>
        </div>
      </div>

      <!-- Your Sales Referral URL -->
      <div class="sd-url-card">
        <div class="sd-url-card-header">
          <div class="sd-url-card-icon"><i class="fas fa-link"></i></div>
          <div class="sd-url-card-title">
            <h3>{{ t("salesDash_referralTitle") }}</h3>
            <p>{{ t("salesDash_referralDesc") }}</p>
          </div>
        </div>
        <div class="sd-url-display">
          <div class="sd-url-text">{{ currentSales.personalReferralUrl }}</div>
          <button
            type="button"
            class="sd-btn-icon sd-btn-copy"
            @click="copyReferralUrl"
          >
            <i class="fas fa-copy"></i> {{ t("ibDetail_btnCopy") }}
          </button>
        </div>
        <div class="sd-url-stats">
          <div class="sd-url-stat-item">
            <div class="sd-url-stat-value">{{ urlClicks }}</div>
            <div class="sd-url-stat-label">
              {{ t("salesDash_stat_urlClicks") }}
            </div>
          </div>
          <div class="sd-url-stat-item">
            <div class="sd-url-stat-value">{{ registrations }}</div>
            <div class="sd-url-stat-label">
              {{ t("salesDash_stat_registrations") }}
            </div>
          </div>
          <div class="sd-url-stat-item">
            <div class="sd-url-stat-value">{{ conversionRateDisplay }}</div>
            <div class="sd-url-stat-label">
              {{ t("salesDash_stat_conversionRate") }}
            </div>
          </div>
        </div>
      </div>

      <!-- IBs Under This Sales (same as Sales List) -->
      <div class="sales-detail-section sales-detail-section--full">
        <div class="sales-detail-ib-client-wrap">
          <div
            class="sales-detail-table-header sales-detail-table-header--with-search"
          >
            <h3>
              <i class="fas fa-handshake"></i>
              {{
                tParams(
                  "salesList_ib_section",
                  "IBs Under This Sales ({total} Total)",
                  { total: dashboardIbsPagination.total ?? 0 },
                )
              }}
            </h3>
            <div class="sales-detail-search-row">
              <input
                v-model="ibSearch"
                type="text"
                class="sales-detail-search-input"
                :placeholder="t('salesList_search_ib_placeholder')"
                @keyup.enter="loadBoundIbs(true)"
              />
              <button
                type="button"
                class="sales-detail-btn-search"
                @click="loadBoundIbs(true)"
              >
                <i class="fas fa-search"></i> {{ t("common_search") }}
              </button>
            </div>
          </div>
          <div v-if="loadingIbs" class="sales-detail-loading">
            {{ t("salesList_loadingIbs") }}
          </div>
          <div v-else class="sales-detail-table-scroll">
            <table class="sales-detail-table">
              <thead>
                <tr>
                  <th>{{ t("salesList_th_ibCode") }}</th>
                  <th>{{ t("salesList_th_ibName") }}</th>
                  <th>{{ t("salesList_label_email") }}</th>
                  <th>{{ t("salesList_th_phone") }}</th>
                  <th>{{ t("salesList_th_country") }}</th>
                  <th>{{ t("salesList_th_clientsCount") }}</th>
                  <th>{{ t("salesList_th_totalCommission") }}</th>
                  <th>{{ t("salesList_th_status") }}</th>
                  <th>{{ t("salesList_th_action") }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="ib in dashboardIbs" :key="ib.id">
                  <td>
                    <span
                      class="sales-detail-id sales-detail-id--link"
                      @click="openIbDetail(ib)"
                      >{{ ib.ibCode }}</span
                    >
                    <span v-if="ib.adminAlias" class="sales-detail-ib-alias"
                      >({{ ib.adminAlias }})</span
                    >
                  </td>
                  <td>
                    <span class="sales-detail-name-cell">{{ ib.ibName }}</span>
                  </td>
                  <td>
                    <span class="sales-detail-email">{{ ib.email }}</span>
                  </td>
                  <td>{{ ib.phone }}</td>
                  <td>{{ ib.country }}</td>
                  <td>{{ ib.clientsCount }}</td>
                  <td>
                    <strong class="sales-detail-amount"
                      >${{
                        Number(ib.totalCommission ?? 0).toLocaleString(
                          "en-US",
                          {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                          },
                        )
                      }}</strong
                    >
                  </td>
                  <td>
                    <span class="sales-detail-kyc-badge" :class="ib.status">{{
                      boundIbStatusText(ib)
                    }}</span>
                  </td>
                  <td>
                    <button
                      type="button"
                      class="sales-detail-btn-view"
                      @click="openIbDetail(ib)"
                    >
                      <i class="fas fa-external-link-alt"></i>
                      {{ t("salesList_btn_view") }}
                    </button>
                  </td>
                </tr>
                <tr v-if="!dashboardIbs.length">
                  <td colspan="9" class="sales-detail-empty">
                    {{
                      loadingIbs
                        ? t("salesList_empty_loading")
                        : t("salesList_empty_noIbs")
                    }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div
            v-if="(dashboardIbsPagination.total ?? 0) > 0"
            class="sales-detail-pagination"
          >
            <div class="sales-detail-pagination__rows">
              <label class="sales-detail-pagination__label">{{
                t("salesList_showRows")
              }}</label>
              <select
                v-model="ibsPerPage"
                class="sales-detail-pagination__select"
                @change="onIbsPerPageChange"
              >
                <option :value="5">{{ t("salesList_rows_5") }}</option>
                <option :value="10">{{ t("salesList_rows_10") }}</option>
                <option :value="20">{{ t("salesList_rows_20") }}</option>
                <option value="all">{{ t("salesList_rows_all") }}</option>
              </select>
            </div>
            <span class="sales-detail-pagination__info">{{
              boundIbPaginationInfo()
            }}</span>
            <div class="sales-detail-pagination__btns">
              <button
                type="button"
                class="sales-detail-pagination__btn"
                :disabled="(dashboardIbsPagination.page ?? 1) <= 1"
                @click="goToIbPage((dashboardIbsPagination.page ?? 1) - 1)"
              >
                <i class="fas fa-chevron-left"></i>
                {{ t("ibIr_pagination_prev") }}
              </button>
              <span class="sales-detail-pagination__page">{{
                tParams(
                  "salesList_pagination_pageOf",
                  "Page {current} of {total}",
                  {
                    current: dashboardIbsPagination.page ?? 1,
                    total: dashboardIbsPagination.total_pages ?? 1,
                  },
                )
              }}</span>
              <button
                type="button"
                class="sales-detail-pagination__btn"
                :disabled="
                  (dashboardIbsPagination.page ?? 1) >=
                  (dashboardIbsPagination.total_pages ?? 1)
                "
                @click="goToIbPage((dashboardIbsPagination.page ?? 1) + 1)"
              >
                {{ t("ibIr_pagination_next") }}
                <i class="fas fa-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Clients Under This Sales (same as Sales List) -->
      <div class="sales-detail-section sales-detail-section--full">
        <div class="sales-detail-ib-client-wrap">
          <div
            class="sales-detail-table-header sales-detail-table-header--with-search"
          >
            <h3>
              <i class="fas fa-users"></i>
              {{
                tParams(
                  "salesList_clients_section",
                  "Clients Under This Sales ({total} Total)",
                  { total: dashboardClientsPagination.total ?? 0 },
                )
              }}
            </h3>
            <div class="sales-detail-search-row">
              <input
                v-model="clientSearch"
                type="text"
                class="sales-detail-search-input"
                :placeholder="t('salesList_search_client_placeholder')"
                @keyup.enter="loadBoundClients(true)"
              />
              <button
                type="button"
                class="sales-detail-btn-search"
                @click="loadBoundClients(true)"
              >
                <i class="fas fa-search"></i> {{ t("common_search") }}
              </button>
            </div>
          </div>
          <div v-if="loadingClients" class="sales-detail-loading">
            {{ t("salesList_loadingClients") }}
          </div>
          <div v-else class="sales-detail-table-scroll">
            <table class="sales-detail-table">
              <thead>
                <tr>
                  <th>{{ t("salesList_th_clientId") }}</th>
                  <th>{{ t("salesList_th_firstName") }}</th>
                  <th>{{ t("salesList_th_lastName") }}</th>
                  <th>{{ t("salesList_label_email") }}</th>
                  <th>{{ t("salesList_th_phone") }}</th>
                  <th>{{ t("salesList_th_country") }}</th>
                  <th>{{ t("salesList_th_balance") }}</th>
                  <th>{{ t("salesList_th_trades") }}</th>
                  <th>{{ t("salesList_th_kycStatus") }}</th>
                  <th>{{ t("salesList_th_action") }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="client in dashboardClients" :key="client.id">
                  <td>
                    <span
                      class="sales-detail-id sales-detail-id--link"
                      @click="openClientDetail(client)"
                      >{{ client.clientId }}</span
                    >
                  </td>
                  <td>
                    <span class="sales-detail-name-cell">{{
                      client.firstName
                    }}</span>
                  </td>
                  <td>
                    <span class="sales-detail-name-cell">{{
                      client.lastName
                    }}</span>
                  </td>
                  <td>
                    <span class="sales-detail-email">{{ client.email }}</span>
                  </td>
                  <td>{{ client.phone }}</td>
                  <td>{{ client.country }}</td>
                  <td>
                    <strong class="sales-detail-amount"
                      >${{
                        Number(client.balance ?? 0).toLocaleString("en-US", {
                          minimumFractionDigits: 2,
                          maximumFractionDigits: 2,
                        })
                      }}</strong
                    >
                  </td>
                  <td>{{ client.trades ?? 0 }}</td>
                  <td>
                    <span
                      class="sales-detail-kyc-badge"
                      :class="client.kycStatus"
                      >{{ clientKycText(client) }}</span
                    >
                  </td>
                  <td>
                    <button
                      type="button"
                      class="sales-detail-btn-view"
                      @click="openClientDetail(client)"
                    >
                      <i class="fas fa-external-link-alt"></i>
                      {{ t("salesList_btn_view") }}
                    </button>
                  </td>
                </tr>
                <tr v-if="!dashboardClients.length">
                  <td colspan="10" class="sales-detail-empty">
                    {{
                      loadingClients
                        ? t("salesList_empty_loading")
                        : t("salesList_empty_noClients")
                    }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div
            v-if="(dashboardClientsPagination.total ?? 0) > 0"
            class="sales-detail-pagination"
          >
            <div class="sales-detail-pagination__rows">
              <label class="sales-detail-pagination__label">{{
                t("salesList_showRows")
              }}</label>
              <select
                v-model="clientsPerPage"
                class="sales-detail-pagination__select"
                @change="onClientsPerPageChange"
              >
                <option :value="5">{{ t("salesList_rows_5") }}</option>
                <option :value="10">{{ t("salesList_rows_10") }}</option>
                <option :value="20">{{ t("salesList_rows_20") }}</option>
                <option value="all">{{ t("salesList_rows_all") }}</option>
              </select>
            </div>
            <span class="sales-detail-pagination__info">{{
              boundClientPaginationInfo()
            }}</span>
            <div class="sales-detail-pagination__btns">
              <button
                type="button"
                class="sales-detail-pagination__btn"
                :disabled="(dashboardClientsPagination.page ?? 1) <= 1"
                @click="
                  goToClientPage((dashboardClientsPagination.page ?? 1) - 1)
                "
              >
                <i class="fas fa-chevron-left"></i>
                {{ t("ibIr_pagination_prev") }}
              </button>
              <span class="sales-detail-pagination__page">{{
                tParams(
                  "salesList_pagination_pageOf",
                  "Page {current} of {total}",
                  {
                    current: dashboardClientsPagination.page ?? 1,
                    total: dashboardClientsPagination.total_pages ?? 1,
                  },
                )
              }}</span>
              <button
                type="button"
                class="sales-detail-pagination__btn"
                :disabled="
                  (dashboardClientsPagination.page ?? 1) >=
                  (dashboardClientsPagination.total_pages ?? 1)
                "
                @click="
                  goToClientPage((dashboardClientsPagination.page ?? 1) + 1)
                "
              >
                {{ t("ibIr_pagination_next") }}
                <i class="fas fa-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Sales Network Relationship Graph (same as Sales List) -->
      <div class="sales-detail-section sales-detail-section--full">
        <div class="sales-detail-graph-header">
          <h3>
            <i class="fas fa-project-diagram"></i>
            {{
              tParams(
                "salesList_graph_section",
                "Sales Network Relationship Graph ({total} Total Clients)",
                { total: dashboardClientsPagination.total ?? 0 },
              )
            }}
          </h3>
          <div class="sales-detail-graph-search">
            <i class="fas fa-search"></i>
            <input
              type="text"
              :placeholder="t('salesList_graph_search_placeholder')"
              v-model="networkSearch"
              @keydown.enter="searchSalesGraph()"
            />
          </div>
        </div>
        <div class="sales-network-canvas-wrap">
          <div class="sales-zoom-indicator" v-if="showZoomIndicator">
            <i class="fas fa-search"></i> <span>{{ zoomLevel }}%</span>
          </div>
          <div
            class="sales-network-container"
            ref="networkContainerRef"
            @mousedown="handleGraphMouseDown"
            @wheel.prevent="handleGraphWheel"
            @dblclick="resetGraphView"
          >
            <div
              class="sales-network-graph"
              ref="networkGraphRef"
              :style="{
                transform: `translate(${panX}px, ${panY}px) scale(${zoom})`,
              }"
            >
              <div v-if="graphLoadingFull" class="sales-network-loading">
                <i class="fas fa-spinner fa-spin"></i>
                {{ t("salesList_graph_loading") }}
              </div>
              <template v-else>
                <div class="network-branch network-branch--root">
                  <div class="network-node">
                    <div class="node-card tier1 sales-root-card">
                      <div class="node-content">
                        <div class="node-avatar">
                          {{ getInitials(currentSales.fullName) }}
                        </div>
                        <div class="node-info">
                          <div class="node-title">
                            {{ currentSales.fullName }}
                          </div>
                          <div class="node-subtitle">
                            {{ t("salesList_graph_rootSubtitle") }}
                          </div>
                          <div class="node-badge">
                            <i class="fas fa-user-tie"></i>
                            {{ t("salesList_graph_badge_sales") }}
                          </div>
                        </div>
                      </div>
                      <div class="node-stats">
                        <div class="node-stat">
                          <i class="fas fa-handshake"></i>
                          {{
                            tParams("salesList_graph_stat_ibs", "{count} IBs", {
                              count: (
                                graphTree?.members?.filter(
                                  (m) => m.type === "ib",
                                ) || []
                              ).length,
                            })
                          }}
                        </div>
                        <div class="node-stat">
                          <i class="fas fa-users"></i>
                          {{
                            tParams(
                              "salesList_graph_stat_clients",
                              "{count} Clients",
                              {
                                count: (
                                  graphTree?.members?.filter(
                                    (m) => m.type === "client",
                                  ) || []
                                ).length,
                              },
                            )
                          }}
                        </div>
                      </div>
                    </div>
                    <div
                      class="expand-btn"
                      :class="{ expanded: expandedNodes.tier1 }"
                      @click.stop="toggleGraph"
                    >
                      {{ expandedNodes.tier1 ? "−" : "+" }}
                    </div>
                  </div>
                  <div
                    class="network-children"
                    :class="{ expanded: expandedNodes.tier1 }"
                  >
                    <IbDetailNetworkGraphNode
                      v-for="member in graphTree?.members || []"
                      :key="member.id"
                      :member="member"
                      :expanded-nodes="expandedNodes"
                      :highlighted-node-id="highlightedGraphNodeId"
                      @toggle="onGraphNodeToggle"
                    />
                  </div>
                  <div
                    v-if="expandedNodes.tier1 && !graphTree?.members?.length"
                    class="network-empty-msg"
                  >
                    {{ t("salesList_graph_empty") }}
                  </div>
                </div>
              </template>
            </div>
          </div>
          <div class="sales-zoom-controls">
            <button
              type="button"
              class="sales-zoom-btn"
              @click="zoomGraphOut"
              :aria-label="t('salesList_aria_zoomOut')"
            >
              <i class="fas fa-minus"></i>
            </button>
            <div class="sales-zoom-level">
              <i class="fas fa-search"></i> {{ zoomLevel }}%
            </div>
            <button
              type="button"
              class="sales-zoom-btn"
              @click="zoomGraphIn"
              :aria-label="t('salesList_aria_zoomIn')"
            >
              <i class="fas fa-plus"></i>
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { ref, computed, watch, nextTick, onMounted, onUnmounted } from "vue";
import { ElConfigProvider, ElDatePicker } from "element-plus";
import zhCn from "element-plus/es/locale/lang/zh-cn";
import en from "element-plus/es/locale/lang/en";
import "element-plus/es/components/date-picker/style/css";
import "element-plus/es/components/config-provider/style/css";
import IbDetailNetworkGraphNode from "@/components/ib/IbDetailNetworkGraphNode.vue";
import salesApi from "@/services/salesApi";
import ibPartnersApi from "@/services/ibPartnersApi";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t, tParams, languageStore } = useAdminI18n();

const elementPlusLocale = computed(() =>
  languageStore.currentLanguage === "zh" ? zhCn : en,
);

// Monthly performance cards. Month boundaries follow the browser timezone; the
// backend falls back to UTC+10 when none is sent, same as the Daily Report page.
const perfTzOffsetMinutes = -new Date().getTimezoneOffset();
const currentMonth = `${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, "0")}`;

const perfMonth = ref(currentMonth);
const perf = ref({});
const perfTimezoneLabel = ref("");
const perfError = ref(null);
const loadingPerf = ref(false);

const netDepositClass = computed(() => {
  const amount = Number(perf.value.netDeposit || 0);
  if (amount > 0) return "sd-perf-positive";
  if (amount < 0) return "sd-perf-negative";
  return "";
});

function disableFutureMonth(date) {
  const month = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}`;
  return month > currentMonth;
}

function formatMoney(value) {
  return `$${Number(value || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatSignedMoney(value) {
  const amount = Number(value || 0);
  return `${amount < 0 ? "-" : ""}${formatMoney(Math.abs(amount))}`;
}

async function loadMonthlyPerformance() {
  const salesId = currentSales.value?.id;
  if (!salesId) return;

  loadingPerf.value = true;
  perfError.value = null;
  try {
    const data = await salesApi.getMonthlyPerformance(salesId, {
      month: perfMonth.value,
      tzOffset: perfTzOffsetMinutes,
    });
    perf.value = data?.metrics ?? {};
    perfTimezoneLabel.value = data?.timezone?.label ?? "";
    if (data?.month) perfMonth.value = data.month;
  } catch (e) {
    perfError.value =
      e?.response?.data?.message ||
      e?.message ||
      t("common_unknownError", "Unknown error");
    perf.value = {};
  } finally {
    loadingPerf.value = false;
  }
}

function boundIbStatusText(ib) {
  const s = String(ib?.status ?? "").toLowerCase();
  const map = {
    approved: "salesList_kyc_approved",
    pending: "salesList_kyc_pending",
    rejected: "salesList_kyc_rejected",
    submitted: "salesList_kyc_submitted",
    none: "salesList_kyc_none",
  };
  if (map[s]) return t(map[s]);
  return ib?.statusDisplay || "—";
}

function clientKycText(client) {
  const s = String(client?.kycStatus ?? "").toLowerCase();
  const map = {
    approved: "salesList_kyc_approved",
    pending: "salesList_kyc_pending",
    rejected: "salesList_kyc_rejected",
    submitted: "salesList_kyc_submitted",
    none: "salesList_kyc_none",
  };
  if (map[s]) return t(map[s]);
  return client?.kycStatusDisplay || "—";
}

const loadingMe = ref(true);
const currentSales = ref(null);

const urlClicks = ref(0);
const registrations = ref(0);
const conversionRateDisplay = computed(() => {
  const clicks = urlClicks.value;
  if (clicks === 0) return "0%";
  const rate = (registrations.value / clicks) * 100;
  return `${Number(rate.toFixed(1))}%`;
});

const ibSearch = ref("");
const clientSearch = ref("");
const networkSearch = ref("");
const dashboardIbs = ref([]);
const dashboardClients = ref([]);
const dashboardIbsPagination = ref({
  total: 0,
  page: 1,
  per_page: 10,
  total_pages: 1,
});
const dashboardClientsPagination = ref({
  total: 0,
  page: 1,
  per_page: 10,
  total_pages: 1,
});
const loadingIbs = ref(false);
const loadingClients = ref(false);
const ibsPage = ref(1);
const clientsPage = ref(1);
const ibsPerPage = ref(10);
const clientsPerPage = ref(10);

const graphTree = ref(null);
const expandedNodes = ref({});
const expandedGraph = ref(false);
const graphFullIbsMap = ref(null);
const graphFullClientsMap = ref(null);
const graphLoadingFull = ref(false);
const highlightedGraphNodeId = ref(null);
const networkContainerRef = ref(null);
const networkGraphRef = ref(null);
const panX = ref(0);
const panY = ref(0);
const zoom = ref(1.0);
const isDragging = ref(false);
const startX = ref(0);
const startY = ref(0);
const showZoomIndicator = ref(false);
const zoomLevel = ref("100");

const salesId = computed(() => currentSales.value?.id ?? null);

async function loadMe() {
  loadingMe.value = true;
  currentSales.value = null;
  try {
    const data = await salesApi.getMe();
    currentSales.value = data;
    urlClicks.value = data?.referralUrlClicks ?? 0;
    registrations.value = data?.referralRegistrationsCount ?? 0;
    if (data?.id) loadMonthlyPerformance();
  } catch (e) {
    if (e?.response?.status === 403) {
      currentSales.value = null;
    } else {
      console.error("Load current sales failed:", e);
    }
  } finally {
    loadingMe.value = false;
  }
}

function copyReferralUrl() {
  const url = currentSales.value?.personalReferralUrl;
  if (!url) return;
  navigator.clipboard
    .writeText(url)
    .then(() => {
      alert(t("salesList_alert_urlCopied"));
    })
    .catch(() => {
      alert(t("salesList_alert_copyFailed"));
    });
}

async function loadBoundIbs(resetPage = false) {
  const id = salesId.value;
  if (!id) return;
  if (resetPage) ibsPage.value = 1;
  loadingIbs.value = true;
  try {
    const per =
      ibsPerPage.value === "all" ? 9999 : Number(ibsPerPage.value) || 10;
    const { items, pagination } = await salesApi.getBoundIbs(id, {
      page: ibsPage.value,
      per_page: per,
      search: (ibSearch.value || "").trim() || undefined,
    });
    dashboardIbs.value = items || [];
    dashboardIbsPagination.value = pagination || {
      total: 0,
      page: 1,
      per_page: 10,
      total_pages: 1,
    };
  } catch (e) {
    dashboardIbs.value = [];
    dashboardIbsPagination.value = {
      total: 0,
      page: 1,
      per_page: 10,
      total_pages: 1,
    };
    console.error("Load bound IBs failed:", e);
  } finally {
    loadingIbs.value = false;
  }
}

async function loadBoundClients(resetPage = false) {
  const id = salesId.value;
  if (!id) return;
  if (resetPage) clientsPage.value = 1;
  loadingClients.value = true;
  try {
    const per =
      clientsPerPage.value === "all"
        ? 9999
        : Number(clientsPerPage.value) || 10;
    const { items, pagination } = await salesApi.getBoundClients(id, {
      page: clientsPage.value,
      per_page: per,
      search: (clientSearch.value || "").trim() || undefined,
    });
    dashboardClients.value = items || [];
    dashboardClientsPagination.value = pagination || {
      total: 0,
      page: 1,
      per_page: 10,
      total_pages: 1,
    };
  } catch (e) {
    dashboardClients.value = [];
    dashboardClientsPagination.value = {
      total: 0,
      page: 1,
      per_page: 10,
      total_pages: 1,
    };
    console.error("Load bound clients failed:", e);
  } finally {
    loadingClients.value = false;
  }
}

function boundIbPaginationInfo() {
  const pag = dashboardIbsPagination.value;
  if (!pag || pag.total === 0) return t("salesList_pagination_noRecords");
  const per = Number(pag.per_page) || 10;
  if (per >= (pag.total || 0))
    return tParams(
      "salesList_pagination_totalRecords",
      "Total {total} record(s)",
      { total: pag.total },
    );
  const from = (pag.page - 1) * per + 1;
  const to = Math.min(pag.page * per, pag.total);
  return tParams(
    "salesList_pagination_showing",
    "Showing {from}-{to} of {total}",
    { from, to, total: pag.total },
  );
}

function boundClientPaginationInfo() {
  const pag = dashboardClientsPagination.value;
  if (!pag || pag.total === 0) return t("salesList_pagination_noRecords");
  const per = Number(pag.per_page) || 10;
  if (per >= (pag.total || 0))
    return tParams(
      "salesList_pagination_totalRecords",
      "Total {total} record(s)",
      { total: pag.total },
    );
  const from = (pag.page - 1) * per + 1;
  const to = Math.min(pag.page * per, pag.total);
  return tParams(
    "salesList_pagination_showing",
    "Showing {from}-{to} of {total}",
    { from, to, total: pag.total },
  );
}

function goToIbPage(page) {
  const pag = dashboardIbsPagination.value;
  if (!pag || page < 1 || page > (pag.total_pages || 1)) return;
  ibsPage.value = page;
  loadBoundIbs();
}

function goToClientPage(page) {
  const pag = dashboardClientsPagination.value;
  if (!pag || page < 1 || page > (pag.total_pages || 1)) return;
  clientsPage.value = page;
  loadBoundClients();
}

function onIbsPerPageChange() {
  ibsPage.value = 1;
  loadBoundIbs();
}

function onClientsPerPageChange() {
  clientsPage.value = 1;
  loadBoundClients();
}

function openClientDetail(client) {
  const base = window.location.origin + window.location.pathname;
  const id = client?.id;
  const email = (client?.email ?? "").trim();
  const kycStatus = (client?.kycStatus ?? "").toLowerCase();
  const path = kycStatus === "approved" ? "clients-list" : "leads";
  const q = new URLSearchParams();
  if (id != null) q.set("detailId", String(id));
  if (email) q.set("search", email);
  window.open(
    base + `#/${path}?${q.toString()}`,
    "_blank",
    "noopener,noreferrer",
  );
}

function openIbDetail(ib) {
  const base = window.location.origin + window.location.pathname;
  const id = ib?.id;
  const email = (ib?.email ?? "").trim();
  const q = new URLSearchParams();
  if (id != null) q.set("detailId", String(id));
  if (email) q.set("search", email);
  window.open(
    base + `#/ib-list?${q.toString()}`,
    "_blank",
    "noopener,noreferrer",
  );
}

function getInitials(name) {
  if (!name) return "—";
  return name
    .split(" ")
    .map((s) => s[0])
    .join("")
    .toUpperCase()
    .slice(0, 2);
}

function getIbInitials(name) {
  if (!name) return "—";
  const words = name.split(" ");
  if (words.length >= 3)
    return (words[0][0] + words[1][0] + words[2][0]).toUpperCase().slice(0, 3);
  return name.slice(0, 3).toUpperCase();
}

function buildTier1Members(ibs, clients) {
  const members = [];
  (ibs || []).forEach((ib) => {
    members.push({
      id: `ib-${ib.id}`,
      clientUserId: Number(ib.userId || 0),
      name: ib.ibName || "—",
      code: ib.ibCode || "",
      adminAlias: ib.adminAlias || "",
      type: "ib",
      hasChildren: true,
      children: [],
      initials: getIbInitials(ib.ibName),
    });
  });
  (clients || []).forEach((c) => {
    const fullName =
      [c.firstName, c.lastName].filter(Boolean).join(" ").trim() || "—";
    members.push({
      id: `client-${c.id}`,
      name: fullName,
      code: c.clientId || "",
      type: "client",
      hasChildren: false,
      children: [],
      initials: getInitials(fullName),
    });
  });
  return members;
}

async function loadGraphFullData() {
  const id = salesId.value;
  if (!id) return;
  if (graphFullIbsMap.value && graphFullClientsMap.value) {
    expandedNodes.value = { ...expandedNodes.value, tier1: true };
    return;
  }
  graphLoadingFull.value = true;
  try {
    const [ibsRes, clientsRes] = await Promise.all([
      salesApi.getBoundIbs(id, { page: 1, per_page: 9999 }),
      salesApi.getBoundClients(id, { page: 1, per_page: 9999 }),
    ]);
    const ibs = ibsRes?.items ?? [];
    const clients = clientsRes?.items ?? [];
    graphFullIbsMap.value = { items: ibs };
    graphFullClientsMap.value = { items: clients };
    graphTree.value = { members: buildTier1Members(ibs, clients) };
    expandedNodes.value = { ...expandedNodes.value, tier1: true };
  } finally {
    graphLoadingFull.value = false;
  }
}

function findMemberInTree(members, nodeId) {
  if (!members || !nodeId) return null;
  for (const m of members) {
    if (m.id === nodeId) return m;
    const found = findMemberInTree(m.children, nodeId);
    if (found) return found;
  }
  return null;
}

async function loadIbChildrenForGraph(nodeId) {
  if (!graphTree.value?.members) return;
  const member = findMemberInTree(graphTree.value.members, nodeId);
  if (
    !member ||
    member.type !== "ib" ||
    (member.children && member.children.length > 0)
  )
    return;
  const numericId = parseInt(String(nodeId).replace(/^ib-/, ""), 10);
  if (!numericId) return;
  try {
    const res = await ibPartnersApi.getNetwork(numericId);
    const raw = res?.data?.data ?? res?.data ?? res;
    const list = Array.isArray(raw) ? raw : (raw?.members ?? []);
    member.children = list;
  } catch (e) {
    console.error("Load IB network for graph failed:", e);
    member.children = [];
  }
}

function toggleGraph() {
  if (!expandedGraph.value) {
    expandedGraph.value = true;
    loadGraphFullData();
    return;
  }
  expandedNodes.value = {
    ...expandedNodes.value,
    tier1: !expandedNodes.value.tier1,
  };
}

function onGraphNodeToggle(nodeId) {
  const next = {
    ...expandedNodes.value,
    [nodeId]: !expandedNodes.value[nodeId],
  };
  expandedNodes.value = next;
  if (next[nodeId]) loadIbChildrenForGraph(nodeId);
}

function searchInGraphMembers(members, query, path = []) {
  const results = [];
  const lowerQuery = String(query).toLowerCase().trim();
  if (!lowerQuery) return results;
  (members || []).forEach((member) => {
    const currentPath = [...path, member.id];
    const name = (member.name || "").toLowerCase();
    const code = (member.code || "").toLowerCase();
    const alias = (member.adminAlias || "").toLowerCase();
    const idStr = String(member.id).toLowerCase();
    if (
      name.includes(lowerQuery) ||
      code.includes(lowerQuery) ||
      alias.includes(lowerQuery) ||
      idStr.includes(lowerQuery)
    ) {
      results.push({ member, path: currentPath });
    }
    if (member.children && member.children.length > 0) {
      results.push(
        ...searchInGraphMembers(member.children, query, currentPath),
      );
    }
  });
  return results;
}

function expandToPathInGraph(path) {
  const next = { ...expandedNodes.value, tier1: true };
  (path || []).forEach((nodeId) => {
    if (nodeId !== "tier1") next[nodeId] = true;
  });
  expandedNodes.value = next;
}

function scrollGraphToNode(nodeId) {
  nextTick(() => {
    const el = document.querySelector(`[data-node-id="${nodeId}"]`);
    if (el && networkContainerRef.value) {
      const containerRect = networkContainerRef.value.getBoundingClientRect();
      const nodeRect = el.getBoundingClientRect();
      const relativeX = nodeRect.left - containerRect.left + nodeRect.width / 2;
      const relativeY = nodeRect.top - containerRect.top + nodeRect.height / 2;
      panX.value = containerRect.width / 2 - relativeX;
      panY.value = containerRect.height / 2 - relativeY;
    }
  });
}

function searchSalesGraph() {
  const query = (networkSearch.value || "").trim();
  highlightedGraphNodeId.value = null;
  if (!query) return;
  const members = graphTree.value?.members ?? [];
  const results = searchInGraphMembers(members, query);
  if (results.length > 0) {
    const first = results[0];
    expandToPathInGraph(first.path);
    highlightedGraphNodeId.value = first.member.id;
    scrollGraphToNode(first.member.id);
  } else {
    alert(
      tParams(
        "salesList_graph_noMatch",
        'No members found matching "{query}"',
        { query },
      ),
    );
  }
}

function handleGraphMouseDown(e) {
  if (e.target.closest(".expand-btn") || e.target.closest(".node-card")) return;
  isDragging.value = true;
  startX.value = e.clientX - panX.value;
  startY.value = e.clientY - panY.value;
  if (networkContainerRef.value)
    networkContainerRef.value.classList.add("dragging");
  e.preventDefault();
}

function handleGraphMouseMove(e) {
  if (!isDragging.value) return;
  panX.value = e.clientX - startX.value;
  panY.value = e.clientY - startY.value;
}

function handleGraphMouseUp() {
  if (isDragging.value) {
    isDragging.value = false;
    if (networkContainerRef.value)
      networkContainerRef.value.classList.remove("dragging");
  }
}

function handleGraphWheel(e) {
  e.preventDefault();
  const delta = e.deltaY > 0 ? -0.1 : 0.1;
  zoom.value = Math.max(0.3, Math.min(3.0, zoom.value + delta));
  zoomLevel.value = String(Math.round(zoom.value * 100));
  showZoomIndicator.value = true;
  setTimeout(() => {
    showZoomIndicator.value = false;
  }, 1000);
}

function resetGraphView(e) {
  if (e.target.closest(".node-card") || e.target.closest(".expand-btn")) return;
  zoom.value = 1.0;
  panX.value = 0;
  panY.value = 0;
  zoomLevel.value = "100";
  showZoomIndicator.value = true;
  setTimeout(() => {
    showZoomIndicator.value = false;
  }, 1000);
}

function zoomGraphIn() {
  zoom.value = Math.min(3.0, zoom.value + 0.15);
  zoomLevel.value = String(Math.round(zoom.value * 100));
  showZoomIndicator.value = true;
  setTimeout(() => {
    showZoomIndicator.value = false;
  }, 1000);
}

function zoomGraphOut() {
  zoom.value = Math.max(0.3, zoom.value - 0.15);
  zoomLevel.value = String(Math.round(zoom.value * 100));
  showZoomIndicator.value = true;
  setTimeout(() => {
    showZoomIndicator.value = false;
  }, 1000);
}

watch(
  salesId,
  (id) => {
    if (id) {
      loadBoundIbs();
      loadBoundClients();
    } else {
      dashboardIbs.value = [];
      dashboardClients.value = [];
    }
  },
  { immediate: true },
);

onMounted(() => {
  loadMe();
  document.addEventListener("mousemove", handleGraphMouseMove);
  document.addEventListener("mouseup", handleGraphMouseUp);
});

onUnmounted(() => {
  document.removeEventListener("mousemove", handleGraphMouseMove);
  document.removeEventListener("mouseup", handleGraphMouseUp);
});
</script>

<style scoped>
.sales-dashboard-page {
  max-width: 1600px;
  margin: 0 auto;
  padding: 30px 20px;
}
.sales-dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}
.sales-dashboard-title h1 {
  font-size: 28px;
  color: var(--color-ink);
  margin: 0 0 5px 0;
}
.sales-dashboard-title p {
  font-size: 14px;
  color: var(--color-muted);
  margin: 0;
}
.sales-dashboard-actions {
  display: flex;
  align-items: center;
  gap: 20px;
}

.sd-loading {
  padding: 20px;
  text-align: center;
  color: var(--color-muted);
  font-size: 14px;
}
.sd-no-sales {
  padding: 30px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-lg);
  border: 2px solid var(--color-border);
  margin-bottom: 20px;
}
.sd-no-sales p {
  margin: 0;
  color: var(--color-text);
  font-size: 14px;
}

/* Monthly performance */
.sd-perf-card {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 22px 25px;
  margin-bottom: 24px;
}
.sd-perf-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
  margin-bottom: 18px;
}
.sd-perf-head h3 {
  margin: 0;
  font-size: 18px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 8px;
}
.sd-perf-head h3 i {
  color: var(--color-brand);
}
.sd-perf-controls {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.sd-perf-month {
  width: 150px;
}
.sd-perf-tz {
  font-size: 12px;
  color: var(--color-text);
  background: var(--color-surface-muted);
  border-radius: 999px;
  padding: 5px 11px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: help;
}
.sd-perf-note {
  font-size: 12px;
  color: var(--color-text);
  background: var(--color-success-soft);
  border: 1px solid var(--color-success-soft);
  border-radius: 999px;
  padding: 4px 10px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.sd-perf-note i {
  color: var(--color-success);
}
.sd-perf-error {
  background: var(--color-danger-soft);
  border: 1px solid var(--color-danger-border);
  color: var(--color-danger);
  padding: 10px 14px;
  border-radius: var(--radius-md);
  margin-bottom: 14px;
  font-size: 13px;
}
.sd-perf-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 14px;
}
.sd-perf-grid.loading {
  opacity: 0.5;
}
.sd-perf-item {
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  padding: 16px 18px;
  border-left: 4px solid var(--color-border-strong);
}
.sd-perf-item.deposit {
  border-left-color: var(--color-success);
}
.sd-perf-item.withdrawal {
  border-left-color: var(--color-danger);
}
.sd-perf-item.net {
  border-left-color: var(--color-brand);
}
.sd-perf-item.leads {
  border-left-color: var(--color-warning);
}
.sd-perf-item.clients {
  border-left-color: #319795;
}
.sd-perf-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 8px;
}
.sd-perf-value {
  font-size: 22px;
  font-weight: 700;
  color: var(--color-ink-strong);
  margin-bottom: 4px;
}
.sd-perf-sub {
  font-size: 12px;
  color: var(--color-faint);
}
.sd-perf-positive {
  color: var(--color-success);
}
.sd-perf-negative {
  color: var(--color-danger);
}

.sd-url-card {
  background: linear-gradient(
    135deg,
    var(--color-brand-soft) 0%,
    var(--color-brand-soft) 100%
  );
  border: 2px solid var(--color-brand);
  border-radius: var(--radius-lg);
  padding: 25px;
  margin-bottom: 30px;
}
.sd-url-card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 15px;
}
.sd-url-card-icon {
  width: 50px;
  height: 50px;
  background: var(--color-brand-solid);
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: white;
}
.sd-url-card-title {
  flex: 1;
}
.sd-url-card-title h3 {
  font-size: 18px;
  color: var(--color-ink);
  margin: 0 0 3px 0;
}
.sd-url-card-title p {
  font-size: 13px;
  color: var(--color-muted);
  margin: 0;
}
.sd-url-display {
  background: var(--color-surface);
  border: 2px solid var(--color-border-strong);
  border-radius: var(--radius-md);
  padding: 15px 20px;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 15px;
  margin-bottom: 15px;
}
.sd-url-text {
  flex: 1;
  font-family: "Courier New", monospace;
  font-size: 14px;
  color: var(--color-brand);
  font-weight: 600;
  word-break: break-all;
}
.sd-btn-icon {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.sd-btn-copy {
  background: var(--color-brand-solid);
  color: white;
}
.sd-btn-copy:hover {
  background: var(--color-brand-strong);
}
.sd-url-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 15px;
}
.sd-url-stat-item {
  text-align: center;
  padding: 12px;
  background: var(--color-surface);
  border-radius: var(--radius-md);
}
.sd-url-stat-value {
  font-size: 20px;
  font-weight: 700;
  color: var(--color-brand);
  margin-bottom: 4px;
}
.sd-url-stat-label {
  font-size: 12px;
  color: var(--color-muted);
}

.sales-detail-section {
  margin-bottom: 25px;
}
.sales-detail-section--full {
  width: 100%;
}
.sales-detail-ib-client-wrap {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  overflow: hidden;
  box-shadow: var(--shadow-sm);
}
.sales-detail-table-header {
  padding: 20px 24px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}
.sales-detail-table-header h3 {
  margin: 0;
  font-size: 18px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 8px;
}
.sales-detail-table-header h3 i {
  color: var(--color-brand);
}
.sales-detail-search-row {
  display: flex;
  gap: 10px;
  align-items: center;
}
.sales-detail-search-input {
  padding: 10px 14px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  width: 240px;
}
.sales-detail-search-input:focus {
  outline: none;
  border-color: var(--color-brand);
}
.sales-detail-btn-search {
  padding: 10px 18px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  background: var(--color-brand-solid);
  color: white;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.sales-detail-btn-search:hover {
  background: var(--color-brand-strong);
}
.sales-detail-loading {
  padding: 16px;
  text-align: center;
  color: var(--color-muted);
  font-size: 14px;
}
.sales-detail-table-scroll {
  overflow-x: auto;
  overflow-y: auto;
}
.sales-detail-table {
  width: 100%;
  border-collapse: collapse;
}
.sales-detail-table thead {
  background: var(--color-surface-soft);
}
.sales-detail-table th {
  padding: 14px 18px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
  white-space: nowrap;
}
.sales-detail-table td {
  padding: 14px 18px;
  border-bottom: 1px solid var(--color-border);
  font-size: 14px;
  color: var(--color-text);
  white-space: nowrap;
}
.sales-detail-table tbody tr:hover {
  background: var(--color-surface-soft);
}
.sales-detail-id {
  font-weight: 600;
  color: var(--color-brand);
}
.sales-detail-id--link {
  cursor: pointer;
}
.sales-detail-id--link:hover {
  text-decoration: underline;
}
.sales-detail-ib-alias {
  margin-left: 6px;
  font-size: 12px;
  color: var(--color-muted);
}
.sales-detail-name-cell {
  font-weight: 600;
  color: var(--color-ink);
}
.sales-detail-email {
  color: var(--color-muted);
}
.sales-detail-amount {
  color: var(--color-success);
}
.sales-detail-kyc-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
}
.sales-detail-kyc-badge.approved {
  background: var(--color-success-soft);
  color: var(--color-success);
}
.sales-detail-kyc-badge.pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}
.sales-detail-btn-view {
  padding: 6px 14px;
  border-radius: var(--radius-md);
  border: 2px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
  font-weight: 600;
  cursor: pointer;
  font-size: 13px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.sales-detail-btn-view:hover {
  border-color: var(--color-brand);
  color: var(--color-brand);
}
.sales-detail-empty {
  text-align: center;
  padding: 20px;
  color: var(--color-muted);
  font-size: 14px;
}
.sales-detail-pagination {
  padding: 16px 24px;
  border-top: 1px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  background: var(--color-surface-soft);
}
.sales-detail-pagination__rows {
  display: flex;
  align-items: center;
  gap: 8px;
}
.sales-detail-pagination__label {
  font-size: 14px;
  color: var(--color-text);
}
.sales-detail-pagination__select {
  padding: 8px 12px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
}
.sales-detail-pagination__info {
  font-size: 14px;
  color: var(--color-text);
}
.sales-detail-pagination__btns {
  display: flex;
  align-items: center;
  gap: 12px;
}
.sales-detail-pagination__page {
  font-size: 14px;
  color: var(--color-text);
}
.sales-detail-pagination__btn {
  padding: 8px 14px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  background: var(--color-border);
  color: var(--color-text);
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.sales-detail-pagination__btn:hover:not(:disabled) {
  background: var(--color-brand-solid);
  color: white;
}
.sales-detail-pagination__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.sales-detail-graph-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  flex-wrap: wrap;
  gap: 10px;
}
.sales-detail-graph-header h3 {
  margin: 0;
  font-size: 16px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 8px;
}
.sales-detail-graph-header h3 i {
  color: var(--color-brand);
}
.sales-detail-graph-search {
  position: relative;
  display: flex;
  align-items: center;
}
.sales-detail-graph-search i {
  position: absolute;
  left: 12px;
  color: var(--color-faint);
  font-size: 14px;
}
.sales-detail-graph-search input {
  padding: 10px 12px 10px 35px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  width: 260px;
}

.sales-network-canvas-wrap {
  position: relative;
  min-height: 400px;
}
.sales-zoom-indicator {
  position: absolute;
  top: 12px;
  right: 12px;
  background: rgba(var(--color-brand-rgb), 0.95);
  color: white;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  z-index: 10;
  pointer-events: none;
}
.sales-network-loading {
  padding: 24px;
  text-align: center;
  color: var(--color-muted);
}
.sales-network-container {
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 24px;
  overflow: hidden;
  min-height: 400px;
  max-height: 520px;
  position: relative;
  cursor: grab;
  user-select: none;
}
.sales-network-container.dragging {
  cursor: grabbing;
}
.sales-network-graph {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  min-width: max-content;
  padding: 16px;
  transform-origin: 0 0;
}
.sales-network-graph .network-branch--root {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  gap: 24px;
}
.sales-network-graph .network-branch--root .network-node {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 16px;
}
.sales-network-graph .node-card.tier1.sales-root-card {
  background: var(--color-brand-solid);
  border: 3px solid var(--color-brand-strong);
  color: white;
  min-width: 220px;
  padding: 18px 20px;
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}
.sales-network-graph .node-card.tier1.sales-root-card .node-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.25);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 700;
  border: 2px solid rgba(255, 255, 255, 0.4);
}
.sales-network-graph .node-card.tier1.sales-root-card .node-title {
  font-size: 15px;
}
.sales-network-graph .node-card.tier1.sales-root-card .node-subtitle {
  opacity: 0.9;
}
.sales-network-graph .node-card.tier1.sales-root-card .node-badge {
  background: rgba(255, 255, 255, 0.25);
  padding: 3px 8px;
  border-radius: var(--radius-md);
  font-size: 11px;
}
.sales-network-graph .node-card.tier1.sales-root-card .node-stats {
  display: flex;
  gap: 12px;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid rgba(255, 255, 255, 0.25);
  font-size: 11px;
  flex-wrap: wrap;
}
.sales-network-graph .node-card.tier1.sales-root-card .node-stat {
  display: flex;
  align-items: center;
  gap: 4px;
}
.sales-network-graph .network-branch--root .expand-btn {
  width: 32px;
  height: 32px;
  min-width: 32px;
  min-height: 32px;
  border-radius: 50%;
  background: var(--color-surface);
  border: 3px solid var(--color-border-strong);
  color: var(--color-text);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 16px;
  font-weight: 700;
  flex-shrink: 0;
  z-index: 5;
}
.sales-network-graph .network-branch--root .expand-btn:hover,
.sales-network-graph .network-branch--root .expand-btn.expanded {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
  color: white;
}
.sales-network-graph .network-branch--root .network-children {
  display: none;
  flex-direction: column;
  gap: 16px;
  margin-left: 24px;
  padding-left: 20px;
  border-left: 2px solid var(--color-border-strong);
}
.sales-network-graph .network-branch--root .network-children.expanded {
  display: flex;
}
.sales-network-graph .network-empty-msg {
  margin-left: 24px;
  padding: 12px;
  color: var(--color-muted);
  font-size: 13px;
}
.sales-zoom-controls {
  position: absolute;
  bottom: 20px;
  right: 20px;
  display: flex;
  gap: 10px;
  align-items: center;
  background: var(--color-surface);
  padding: 10px;
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-sm);
  z-index: 5;
}
.sales-zoom-btn {
  width: 36px;
  height: 36px;
  border: 2px solid var(--color-border);
  color: var(--color-ink);
  background: var(--color-surface);
  border-radius: var(--radius-sm);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}
.sales-zoom-btn:hover {
  border-color: var(--color-brand);
  color: var(--color-brand);
}
.sales-zoom-level {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 10px;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
}
</style>
