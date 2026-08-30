<template>
  <div class="container ui-page">
    <div v-if="ibPartnerOptions.length > 1" class="commission-ib-switcher">
      <div class="commission-ib-switcher__label">
        <i class="fas fa-exchange-alt"></i>
        <span>{{ t("commSelectIbPartner", "Select IB") }}</span>
      </div>
      <CustomSelect
        v-model="selectedIbPartnerId"
        class="commission-ib-switcher__select"
        :options="ibPartnerOptions"
        searchable
        :placeholder="t('commSelectIbPartner', 'Select IB')"
        :search-placeholder="t('commSearchIbPartner', 'Search IB...')"
        @change="onIbPartnerChange"
      />
    </div>

    <ExportProgressBanner
      v-if="exportBannerVisible && exportStatusText"
      :cancelling="exportCancelling"
      :status-text="exportStatusText"
      :percent="exportProgressPercent"
      :cancel-disabled="!exportJobId"
      :title="t('commExportInProgressTitle', 'Export in progress')"
      :cancelling-title="t('commExportCancelling', 'Cancelling export...')"
      :cancel-label="t('commExportCancel', 'Cancel')"
      @cancel-export="cancelActiveExport"
    />

    <!-- Date Filter Section -->
    <div class="date-filter-section ui-toolbar">
      <div class="date-filter-container">
        <span class="date-filter-label">{{
          t("commTimePeriod", "Time Period:")
        }}</span>
        <div class="date-filter-presets">
          <button
            :class="['preset-btn', { active: activePreset === 'all' }]"
            @click="selectPreset('all')"
          >
            {{ t("commAll", "All") }}
          </button>
          <button
            :class="['preset-btn', { active: activePreset === 'today' }]"
            @click="selectPreset('today')"
          >
            {{ t("commToday", "Today") }}
          </button>
          <button
            :class="['preset-btn', { active: activePreset === 'month' }]"
            @click="selectPreset('month')"
          >
            {{ t("commThisMonthBtn", "This Month") }}
          </button>
          <button
            :class="['preset-btn', { active: activePreset === 'quarter' }]"
            @click="selectPreset('quarter')"
          >
            {{ t("commThisQuarter", "This Quarter") }}
          </button>
          <button
            :class="['preset-btn', { active: activePreset === 'year' }]"
            @click="selectPreset('year')"
          >
            {{ t("commThisYear", "This Year") }}
          </button>
        </div>
        <div class="date-input-wrapper">
          <label>{{ t("commFromDate", "From Date") }}</label>
          <input type="date" v-model="startDate" />
        </div>
        <div class="date-input-wrapper">
          <label>{{ t("commToDate", "To Date") }}</label>
          <input type="date" v-model="endDate" />
        </div>
        <button class="btn-apply-filter" @click="applyDateFilter">
          <i class="fas fa-filter"></i>
          {{ t("commApplyFilter", "Apply Filter") }}
        </button>
        <button class="btn-export" @click="exportReport">
          <i class="fas fa-download"></i> {{ t("commExport", "Export") }}
        </button>
      </div>
    </div>

    <Teleport to="body">
      <div
        v-if="exportModal.visible"
        class="export-modal-overlay"
        @click="onExportModalContinue"
      >
        <div class="export-modal" @click.stop>
          <div class="export-modal-header">
            <h3>{{ t("commExportInProgressTitle", "Export in progress") }}</h3>
            <button
              type="button"
              class="export-modal-close"
              :aria-label="t('close', 'Close')"
              @click="onExportModalContinue"
            >
              ×
            </button>
          </div>
          <div class="export-modal-body">
            <p class="export-modal-text">
              {{
                exportModal.message ||
                t(
                  "commExportInProgressMsg",
                  "You already have a report export running. Continue waiting or cancel it.",
                )
              }}
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
              {{ t("commExportContinue", "Continue") }}
            </button>
            <button
              type="button"
              class="export-modal-btn secondary"
              @click="onExportModalCancel"
              :disabled="exportModal.busy"
            >
              {{ t("commExportCancel", "Cancel") }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Commission Breakdown Table -->
    <div class="commission-table-container ui-data-region">
      <div class="commission-table-toolbar">
        <div class="commission-table-toolbar__left">
          <h2 class="commission-table-toolbar__title">
            <i class="fas fa-list-alt"></i>
            {{ t("dwrBreakdown", "Deposit Withdraw Breakdown") }}
          </h2>
        </div>
        <div class="commission-table-toolbar__right">
          <div class="commission-show-rows">
            <label class="commission-show-rows__label">{{
              t("commShowRows", "Show Rows")
            }}</label>
            <CustomSelect
              v-model="commissionPerPage"
              class="commission-show-rows__select"
              :options="pageSizeOptions"
              @change="onPerPageChange"
            />
          </div>
        </div>
      </div>
      <div class="commission-table-scroll ui-table-scroll">
        <table class="commission-table">
          <thead>
            <tr>
              <th class="checkbox-col">
                <label class="custom-checkbox">
                  <input
                    type="checkbox"
                    :checked="allVisibleSelected"
                    @change="toggleSelectAllVisible"
                    :aria-label="t('commSelectAll', 'Select all')"
                  />
                  <span class="checkbox-checkmark"></span>
                </label>
              </th>
              <th>{{ t("commReferral", "Referral") }}</th>
              <th>{{ t("commType", "Type") }}</th>
              <th>{{ t("dwrLevel", "Level") }}</th>
              <th>
                {{ t("dwrTotalCompletedDeposit", "Total Completed Deposit") }}
              </th>
              <th>
                {{
                  t("dwrTotalCompletedWithdrawal", "Total Completed Withdrawal")
                }}
              </th>
              <th>{{ t("dwrNetDeposit", "Net Deposit") }}</th>
              <th>{{ t("commDetail", "Detail") }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="loading">
              <tr>
                <td colspan="8" class="empty-state">
                  <i class="fas fa-spinner fa-spin"></i>
                  <p>{{ t("commLoading", "Loading...") }}</p>
                </td>
              </tr>
            </template>
            <template v-else>
              <template v-for="item in visibleList" :key="getRowKey(item)">
                <tr
                  :data-item-id="item.id"
                  :class="{ 'row-child': (item.depth || 0) > 0 }"
                >
                  <td class="checkbox-col">
                    <label class="custom-checkbox">
                      <input
                        type="checkbox"
                        :checked="isRowSelected(item)"
                        @change="toggleRowSelection(item)"
                        :aria-label="t('commSelectRow', 'Select row')"
                      />
                      <span class="checkbox-checkmark"></span>
                    </label>
                  </td>
                  <td class="cell-referral">
                    <div
                      class="referral-info referral-info--tree"
                      :style="{
                        paddingLeft:
                          (item.depth || 0) * 36 +
                          ((item.type || '') === 'Direct Client' ? 32 : 0) +
                          'px',
                      }"
                    >
                      <div class="referral-avatar" :style="avatarStyle(item)">
                        {{
                          item.initials ||
                          getInitials(item.name || item.referralName)
                        }}
                      </div>
                      <div class="referral-details">
                        <div class="referral-meta">
                          <div class="referral-name">
                            {{ item.name || item.referralName }}
                          </div>
                          <span v-if="item.isSelf" class="self-badge">{{
                            t("commSelfBadge", "Self")
                          }}</span>
                        </div>
                        <div class="referral-code">{{ item.referralCode }}</div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span
                      :class="[
                        'type-badge',
                        (item.type || '').toLowerCase().replace(/\s+/g, '-'),
                      ]"
                      >{{ typeLabel(item) }}</span
                    >
                  </td>
                  <td>
                    <span
                      :class="['level-badge', levelBadgeClass(item)]"
                      :style="levelBadgeStyle(item)"
                      >{{ levelLabel(item) }}</span
                    >
                  </td>
                  <td>
                    <div class="commission-amount">
                      {{ formatCurrency(item.totalDeposit ?? 0) }}
                    </div>
                  </td>
                  <td>
                    <div class="commission-amount">
                      {{ formatCurrency(item.totalWithdraw ?? 0) }}
                    </div>
                  </td>
                  <td>
                    <div
                      class="commission-amount"
                      :class="netDepositClass(item.netDeposit)"
                    >
                      {{ formatCurrency(item.netDeposit ?? 0) }}
                    </div>
                  </td>
                  <td>
                    <button
                      class="btn-action btn-detail"
                      @click="toggleDetail(item)"
                      :disabled="loadingDetail[getRowKey(item)]"
                    >
                      <i
                        v-if="loadingDetail[getRowKey(item)]"
                        class="fas fa-spinner fa-spin"
                      ></i>
                      <i
                        v-else
                        :class="
                          detailExpandedKey === getRowKey(item)
                            ? 'fas fa-chevron-up'
                            : 'fas fa-chevron-down'
                        "
                      ></i>
                      {{
                        detailExpandedKey === getRowKey(item)
                          ? t("commHide", "Hide")
                          : t("commDetail", "Detail")
                      }}
                    </button>
                  </td>
                </tr>
                <tr
                  v-if="detailExpandedKey === getRowKey(item)"
                  class="detail-row show"
                >
                  <td colspan="8">
                    <div class="detail-content">
                      <div
                        v-if="loadingDetail[getRowKey(item)]"
                        style="text-align: center; padding: 20px"
                      >
                        <i class="fas fa-spinner fa-spin"></i>
                        {{ t("commLoading", "Loading...") }}
                      </div>
                      <template v-else>
                        <!-- Deposit History -->
                        <h4 class="detail-section-title">
                          <i class="fas fa-arrow-down"></i>
                          {{ t("dwrDepositHistory", "Deposit History") }}
                        </h4>
                        <div class="detail-table-scroll">
                          <table class="detail-table">
                            <thead>
                              <tr>
                                <th>{{ t("commDate", "Date") }}</th>
                                <th>
                                  {{ t("dwrTransactionId", "Transaction ID") }}
                                </th>
                                <th>
                                  {{
                                    t("commAccountNumber", "Trading Account")
                                  }}
                                </th>
                                <th>{{ t("dwrCurrency", "Currency") }}</th>
                                <th>{{ t("commAmount", "Amount") }}</th>
                                <th>{{ t("commStatus", "Status") }}</th>
                                <th>{{ t("dwrHandledAt", "Handled At") }}</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr
                                v-for="(row, index) in item.depositRows"
                                :key="index"
                              >
                                <td>{{ row.date || t("commDash", "—") }}</td>
                                <td>
                                  {{ row.transactionId || t("commDash", "—") }}
                                </td>
                                <td>
                                  {{ row.accountNumber || t("commDash", "—") }}
                                </td>
                                <td>
                                  {{ row.currencyCode || t("commDash", "—") }}
                                </td>
                                <td>{{ formatCurrency(row.amount || 0) }}</td>
                                <td>
                                  <span
                                    :class="[
                                      'status-badge',
                                      (row.status || 'pending').toLowerCase(),
                                    ]"
                                  >
                                    {{ statusDisplay(row.status || "pending") }}
                                  </span>
                                </td>
                                <td>
                                  {{ row.handledAt || t("commDash", "—") }}
                                </td>
                              </tr>
                              <tr
                                v-if="
                                  !item.depositRows ||
                                  item.depositRows.length === 0
                                "
                              >
                                <td colspan="7" class="empty-state">
                                  <i class="fas fa-inbox"></i>
                                  <p>
                                    {{
                                      t(
                                        "dwrNoDepositData",
                                        "No deposit history for this period.",
                                      )
                                    }}
                                  </p>
                                </td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                        <div class="detail-total-row">
                          <span
                            class="detail-total-item detail-total-item--grand"
                          >
                            <span class="detail-total-label"
                              >{{
                                t(
                                  "dwrTotalCompletedDeposit",
                                  "Total Completed Deposit",
                                )
                              }}:</span
                            >
                            <span class="detail-total-value">{{
                              formatCurrency(
                                completedAmount(item.depositStatusTotals),
                              )
                            }}</span>
                          </span>
                          <span
                            v-for="(st, i) in item.depositStatusTotals || []"
                            :key="i"
                            class="detail-total-item"
                          >
                            <span class="detail-total-label"
                              >{{ t("dwrTotalPrefix", "Total") }}
                              {{ statusDisplay(st.status) }}:</span
                            >
                            <span class="detail-total-value">{{
                              st.count || 0
                            }}</span>
                          </span>
                        </div>
                        <div
                          v-if="item.depositTotal > 0"
                          class="commission-pagination commission-pagination--detail"
                        >
                          <span class="commission-pagination__info">
                            {{ t("commShowingRecords", "Showing") }}
                            {{
                              ((item.depositPage || 1) - 1) *
                                (item.depositPerPage || DETAIL_PER_PAGE) +
                              1
                            }}-{{
                              Math.min(
                                (item.depositPage || 1) *
                                  (item.depositPerPage || DETAIL_PER_PAGE),
                                item.depositTotal,
                              )
                            }}
                            {{ t("commOf", "of") }} {{ item.depositTotal }}
                          </span>
                          <div class="commission-pagination__btns">
                            <button
                              type="button"
                              class="commission-pagination__btn"
                              :disabled="
                                (item.depositPage || 1) <= 1 ||
                                loadingDetail[getRowKey(item)]
                              "
                              @click="
                                goToDepositPage(
                                  item,
                                  (item.depositPage || 1) - 1,
                                )
                              "
                            >
                              <i class="fas fa-chevron-left"></i>
                              {{ t("commPrevious", "Prev") }}
                            </button>
                            <span class="commission-pagination__page">
                              {{
                                t("commPageOfFormat", "Page {0} of {1}")
                                  .replace("{0}", String(item.depositPage || 1))
                                  .replace(
                                    "{1}",
                                    String(item.depositTotalPages || 1),
                                  )
                              }}
                            </span>
                            <button
                              type="button"
                              class="commission-pagination__btn"
                              :disabled="
                                (item.depositPage || 1) >=
                                  (item.depositTotalPages || 1) ||
                                loadingDetail[getRowKey(item)]
                              "
                              @click="
                                goToDepositPage(
                                  item,
                                  (item.depositPage || 1) + 1,
                                )
                              "
                            >
                              {{ t("commNext", "Next") }}
                              <i class="fas fa-chevron-right"></i>
                            </button>
                          </div>
                        </div>
                        <!-- Withdrawal History -->
                        <h4 class="detail-section-title">
                          <i class="fas fa-arrow-up"></i>
                          {{ t("dwrWithdrawalHistory", "Withdrawal History") }}
                        </h4>
                        <div class="detail-table-scroll">
                          <table class="detail-table">
                            <thead>
                              <tr>
                                <th>{{ t("commDate", "Date") }}</th>
                                <th>
                                  {{ t("dwrTransactionId", "Transaction ID") }}
                                </th>
                                <th>
                                  {{
                                    t("commAccountNumber", "Trading Account")
                                  }}
                                </th>
                                <th>{{ t("dwrCurrency", "Currency") }}</th>
                                <th>{{ t("commAmount", "Amount") }}</th>
                                <th>{{ t("commStatus", "Status") }}</th>
                                <th>{{ t("dwrHandledAt", "Handled At") }}</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr
                                v-for="(row, index) in item.withdrawRows"
                                :key="index"
                              >
                                <td>{{ row.date || t("commDash", "—") }}</td>
                                <td>
                                  {{ row.transactionId || t("commDash", "—") }}
                                </td>
                                <td>
                                  {{ row.accountNumber || t("commDash", "—") }}
                                </td>
                                <td>
                                  {{ row.currencyCode || t("commDash", "—") }}
                                </td>
                                <td>{{ formatCurrency(row.amount || 0) }}</td>
                                <td>
                                  <span
                                    :class="[
                                      'status-badge',
                                      (row.status || 'pending').toLowerCase(),
                                    ]"
                                  >
                                    {{ statusDisplay(row.status || "pending") }}
                                  </span>
                                </td>
                                <td>
                                  {{ row.handledAt || t("commDash", "—") }}
                                </td>
                              </tr>
                              <tr
                                v-if="
                                  !item.withdrawRows ||
                                  item.withdrawRows.length === 0
                                "
                              >
                                <td colspan="7" class="empty-state">
                                  <i class="fas fa-inbox"></i>
                                  <p>
                                    {{
                                      t(
                                        "dwrNoWithdrawData",
                                        "No withdrawal history for this period.",
                                      )
                                    }}
                                  </p>
                                </td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                        <div class="detail-total-row">
                          <span
                            class="detail-total-item detail-total-item--grand"
                          >
                            <span class="detail-total-label"
                              >{{
                                t(
                                  "dwrTotalCompletedWithdrawal",
                                  "Total Completed Withdrawal",
                                )
                              }}:</span
                            >
                            <span class="detail-total-value">{{
                              formatCurrency(
                                completedAmount(item.withdrawStatusTotals),
                              )
                            }}</span>
                          </span>
                          <span
                            v-for="(st, i) in item.withdrawStatusTotals || []"
                            :key="i"
                            class="detail-total-item"
                          >
                            <span class="detail-total-label"
                              >{{ t("dwrTotalPrefix", "Total") }}
                              {{ statusDisplay(st.status) }}:</span
                            >
                            <span class="detail-total-value">{{
                              st.count || 0
                            }}</span>
                          </span>
                        </div>
                        <div class="detail-total-row detail-net-deposit-row">
                          <span
                            class="detail-total-item detail-total-item--grand"
                          >
                            <span class="detail-total-label"
                              >{{
                                t(
                                  "dwrNetDepositLabel",
                                  "Net Deposit (Total Completed Deposit - Total Completed Withdrawal)",
                                )
                              }}:</span
                            >
                            <span
                              class="detail-total-value"
                              :class="netDepositClass(item.netDeposit)"
                              >{{ formatCurrency(item.netDeposit || 0) }}</span
                            >
                          </span>
                        </div>
                        <div
                          v-if="item.withdrawTotal > 0"
                          class="commission-pagination commission-pagination--detail"
                        >
                          <span class="commission-pagination__info">
                            {{ t("commShowingRecords", "Showing") }}
                            {{
                              ((item.withdrawPage || 1) - 1) *
                                (item.withdrawPerPage || DETAIL_PER_PAGE) +
                              1
                            }}-{{
                              Math.min(
                                (item.withdrawPage || 1) *
                                  (item.withdrawPerPage || DETAIL_PER_PAGE),
                                item.withdrawTotal,
                              )
                            }}
                            {{ t("commOf", "of") }} {{ item.withdrawTotal }}
                          </span>
                          <div class="commission-pagination__btns">
                            <button
                              type="button"
                              class="commission-pagination__btn"
                              :disabled="
                                (item.withdrawPage || 1) <= 1 ||
                                loadingDetail[getRowKey(item)]
                              "
                              @click="
                                goToWithdrawPage(
                                  item,
                                  (item.withdrawPage || 1) - 1,
                                )
                              "
                            >
                              <i class="fas fa-chevron-left"></i>
                              {{ t("commPrevious", "Prev") }}
                            </button>
                            <span class="commission-pagination__page">
                              {{
                                t("commPageOfFormat", "Page {0} of {1}")
                                  .replace(
                                    "{0}",
                                    String(item.withdrawPage || 1),
                                  )
                                  .replace(
                                    "{1}",
                                    String(item.withdrawTotalPages || 1),
                                  )
                              }}
                            </span>
                            <button
                              type="button"
                              class="commission-pagination__btn"
                              :disabled="
                                (item.withdrawPage || 1) >=
                                  (item.withdrawTotalPages || 1) ||
                                loadingDetail[getRowKey(item)]
                              "
                              @click="
                                goToWithdrawPage(
                                  item,
                                  (item.withdrawPage || 1) + 1,
                                )
                              "
                            >
                              {{ t("commNext", "Next") }}
                              <i class="fas fa-chevron-right"></i>
                            </button>
                          </div>
                        </div>
                      </template>
                    </div>
                  </td>
                </tr>
              </template>
              <tr v-if="!loading && visibleList.length === 0" :key="'empty'">
                <td colspan="8" class="empty-state">
                  <i class="fas fa-inbox"></i>
                  <p>
                    {{
                      t("commNoCommissionRecords", "No rebate records found.")
                    }}
                  </p>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <!-- 底部分页（样式参考后台 Final Review List）：左侧 Showing x-y of z，右侧 Prev + Page n of N + Next -->
      <div
        class="commission-pagination"
        v-if="!loading && (totalCommissions > 0 || commissions.length > 0)"
      >
        <span class="commission-pagination__info">{{
          commissionPaginationInfo
        }}</span>
        <div class="commission-pagination__btns">
          <button
            type="button"
            class="commission-pagination__btn"
            :disabled="commissionPage <= 1"
            @click="goToPage(commissionPage - 1)"
          >
            <i class="fas fa-chevron-left"></i> {{ t("commPrevious", "Prev") }}
          </button>
          <span class="commission-pagination__page">{{
            commissionPageOfText
          }}</span>
          <button
            type="button"
            class="commission-pagination__btn"
            :disabled="commissionPage >= commissionTotalPages"
            @click="goToPage(commissionPage + 1)"
          >
            {{ t("commNext", "Next") }} <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import CustomSelect from "@/components/common/CustomSelect.vue";
import ExportProgressBanner from "@/components/common/ExportProgressBanner.vue";
import { useLanguageStore } from "@/stores/language";
import { formatCurrency, getInitials } from "@/utils/helpers";
import depositWithdrawReportService from "@/services/depositWithdrawReportService";
import ibDashboardService from "@/services/ibDashboardService";

const languageStore = useLanguageStore();
const t = (key, fallback) => languageStore.t(key, fallback);

const netDepositClass = (value) => {
  const amount = Number(value) || 0;
  if (amount > 0) return "net-deposit--positive";
  if (amount < 0) return "net-deposit--negative";
  return "";
};

const loading = ref(false);
const ibPartners = ref([]);
const selectedIbPartnerId = ref("");

const formatIbPartnerLabel = (ibPartner) => {
  // 与 IB Dashboard 一致：客户端展示优先使用 clientAlias，空则回落到 ibCode
  const alias = (ibPartner.clientAlias || "").trim();
  if (alias) return alias;
  return ibPartner.ibCode || `IB #${ibPartner.id}`;
};

const ibPartnerOptions = computed(() =>
  ibPartners.value.map((ibPartner) => ({
    value: ibPartner.id,
    label: formatIbPartnerLabel(ibPartner),
  })),
);

const selectedIbPartnerParams = () => {
  if (!selectedIbPartnerId.value) return {};
  return { ibPartnerId: selectedIbPartnerId.value };
};

const fetchIbPartners = async () => {
  const response = await ibDashboardService.getPartners();
  const data = response.data?.data || response.data;
  ibPartners.value = Array.isArray(data) ? data : [];

  if (ibPartners.value.length === 0) {
    selectedIbPartnerId.value = "";
    return;
  }

  const selectedExists = ibPartners.value.some(
    (ibPartner) => ibPartner.id === selectedIbPartnerId.value,
  );
  if (!selectedIbPartnerId.value || !selectedExists) {
    selectedIbPartnerId.value = ibPartners.value[0].id;
  }
};

const onIbPartnerChange = async () => {
  commissionPage.value = 1;
  await fetchData();
};

// Date Filter
const activePreset = ref("month");
const startDate = ref("");
const endDate = ref("");

// Commission Data（层级列表，无分页）
const commissions = ref([]);
const totalCommissions = ref(0);
const searchQuery = ref("");

const getRowKey = (row) => `${row.id}-${row.type || ""}`;

function typeLabel(item) {
  if ((item.type || "") === "Direct Client")
    return t("dwrTypeSubIbClient", "Sub-IB's Client");
  return item.type;
}

function levelLabel(item) {
  if ((item.type || "") === "Direct Client")
    return t("dwrLevelClient", "Client");
  const name = (item.tierName || "").trim();
  return name || t("commDash", "—");
}

function levelBadgeClass(item) {
  if (item.badgeColor) return "";
  if ((item.type || "") === "Direct Client") return "level-client";
  const n = Number(item.tierLevel);
  if (n === 1) return "level-tier-1";
  if (n === 2) return "level-tier-2";
  if (n === 3) return "level-tier-3";
  if (n >= 4) return "level-tier-other";
  return "level-unknown";
}

function levelBadgeStyle(item) {
  const color = (item.badgeColor || "").trim();
  if (!/^#[0-9a-fA-F]{6}$/.test(color)) return null;
  return { background: color + "1f", color };
}

function avatarStyle(item) {
  const color = (item.badgeColor || "").trim();
  if (!/^#[0-9a-fA-F]{6}$/.test(color)) return null;
  return { background: color };
}

// Detail (deposit + withdraw history) expansion state
const DETAIL_PER_PAGE = 20;
const detailExpandedKey = ref(null);
const loadingDetail = ref({});

// Completed-only amount from a section's per-status totals
const completedAmount = (statusTotals) => {
  const row = (statusTotals || []).find(
    (s) => String(s.status).toLowerCase() === "completed",
  );
  return row ? row.total || 0 : 0;
};

const statusDisplay = (status) => {
  const s = String(status || "").toLowerCase();
  const map = {
    pending: t("commStatusPending", "Pending"),
    approved: t("commStatusApproved", "Approved"),
    completed: t("commStatusCompleted", "Completed"),
    cancelled: t("commStatusCancelled", "Cancelled"),
  };
  if (map[s]) return map[s];
  if (!s) return t("commDash", "—");
  return s
    .split("_")
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
};

const buildDetailParams = (item, depositPage, withdrawPage) => {
  const params = {
    referral_id: item.id,
    type: item.type,
    deposit_page: depositPage,
    withdraw_page: withdrawPage,
    per_page: DETAIL_PER_PAGE,
  };
  if (startDate.value)
    params.start_date = formatDateWithTimezone(startDate.value);
  if (endDate.value)
    params.end_date = formatDateWithTimezone(endDate.value, "end");
  Object.assign(params, selectedIbPartnerParams());
  return params;
};

const applySection = (item, section, data, requestedPage) => {
  const prefix = section === "deposit" ? "deposit" : "withdraw";
  const total = data?.total ?? 0;
  const perPage = data?.per_page ?? DETAIL_PER_PAGE;
  item[`${prefix}Rows`] = Array.isArray(data?.items) ? data.items : [];
  item[`${prefix}Total`] = total;
  item[`${prefix}Page`] = data?.page ?? requestedPage;
  item[`${prefix}PerPage`] = perPage;
  item[`${prefix}TotalPages`] =
    data?.total_pages ?? Math.max(1, Math.ceil(total / perPage));
  item[`${prefix}StatusTotals`] = Array.isArray(data?.statusTotals)
    ? data.statusTotals
    : [];
  item[`${prefix}AmountTotal`] = data?.amountTotal ?? 0;
};

const loadDetail = async (item, depositPage = 1, withdrawPage = 1) => {
  const key = getRowKey(item);
  loadingDetail.value = { ...loadingDetail.value, [key]: true };
  try {
    const response = await depositWithdrawReportService.getDetail(
      buildDetailParams(item, depositPage, withdrawPage),
    );
    const data = response.data?.data || response.data || {};
    applySection(item, "deposit", data.deposits, depositPage);
    applySection(item, "withdraw", data.withdrawals, withdrawPage);
  } catch (error) {
    console.error("Failed to fetch deposit withdraw detail:", error);
    applySection(item, "deposit", null, 1);
    applySection(item, "withdraw", null, 1);
  } finally {
    const next = { ...loadingDetail.value };
    delete next[key];
    loadingDetail.value = next;
  }
};

const toggleDetail = async (item) => {
  const key = getRowKey(item);
  if (detailExpandedKey.value === key) {
    detailExpandedKey.value = null;
    return;
  }
  detailExpandedKey.value = key;
  await loadDetail(item, 1, 1);
};

const goToDepositPage = async (item, page) => {
  const target = Number(page);
  if (target < 1 || target > (item.depositTotalPages || 1)) return;
  await loadDetail(item, target, item.withdrawPage || 1);
};

const goToWithdrawPage = async (item, page) => {
  const target = Number(page);
  if (target < 1 || target > (item.withdrawTotalPages || 1)) return;
  await loadDetail(item, item.depositPage || 1, target);
};

const visibleList = computed(() => commissions.value);

// 分页：按直接绑定的 IB/Client 数量（服务端分页，层级子节点不计入）
const commissionPage = ref(1);
const commissionPerPage = ref(10);
const pageSizeOptions = [
  { value: 10, label: "10" },
  { value: 20, label: "20" },
  { value: 50, label: "50" },
  { value: 100, label: "100" },
];
const commissionTotalPages = computed(() =>
  Math.max(1, Math.ceil(totalCommissions.value / commissionPerPage.value)),
);
const commissionPaginationInfo = computed(() => {
  const total = totalCommissions.value;
  if (total <= 0)
    return `${t("commShowingRecords", "Showing")} 0 ${t("commOf", "of")} 0`;
  const from = (commissionPage.value - 1) * commissionPerPage.value + 1;
  const to = Math.min(commissionPage.value * commissionPerPage.value, total);
  return `${t("commShowingRecords", "Showing")} ${from}-${to} ${t("commOf", "of")} ${total}`;
});
const commissionPageOfText = computed(() => {
  const fmt = t("commPageOfFormat", "Page {0} of {1}");
  return fmt
    .replace("{0}", String(commissionPage.value))
    .replace("{1}", String(commissionTotalPages.value));
});
function goToPage(p) {
  const n = Number(p);
  if (n < 1 || n > commissionTotalPages.value) return;
  commissionPage.value = n;
  fetchData();
}
function onPerPageChange() {
  commissionPage.value = 1;
  fetchData();
}

// 导出勾选：Map(key=`${id}-${type}` -> { id, type })，跨翻页保持，不随 list 重载清空
const selectedRows = ref(new Map());
const isRowSelected = (item) => selectedRows.value.has(getRowKey(item));
const toggleRowSelection = (item) => {
  const key = getRowKey(item);
  const next = new Map(selectedRows.value);
  if (next.has(key)) {
    next.delete(key);
  } else {
    next.set(key, { id: item.id, type: item.type });
  }
  selectedRows.value = next;
};
const allVisibleSelected = computed(
  () =>
    visibleList.value.length > 0 &&
    visibleList.value.every((r) => selectedRows.value.has(getRowKey(r))),
);
const toggleSelectAllVisible = () => {
  const next = new Map(selectedRows.value);
  if (allVisibleSelected.value) {
    visibleList.value.forEach((r) => next.delete(getRowKey(r)));
  } else {
    visibleList.value.forEach((r) =>
      next.set(getRowKey(r), { id: r.id, type: r.type }),
    );
  }
  selectedRows.value = next;
};

// Date Filter Methods
const selectPreset = (preset) => {
  activePreset.value = preset;
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

  switch (preset) {
    case "all":
      startDate.value = "";
      endDate.value = "";
      break;
    case "today":
      startDate.value = formatDateForInput(today);
      endDate.value = formatDateForInput(today);
      break;
    case "week": {
      const weekStart = new Date(today);
      weekStart.setDate(today.getDate() - today.getDay());
      const weekEnd = new Date(weekStart);
      weekEnd.setDate(weekStart.getDate() + 6);
      startDate.value = formatDateForInput(weekStart);
      endDate.value = formatDateForInput(weekEnd);
      break;
    }
    case "month": {
      const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
      const monthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);
      startDate.value = formatDateForInput(monthStart);
      endDate.value = formatDateForInput(monthEnd);
      break;
    }
    case "quarter": {
      const quarterStart = new Date(
        today.getFullYear(),
        Math.floor(today.getMonth() / 3) * 3,
        1,
      );
      const quarterEnd = new Date(
        quarterStart.getFullYear(),
        quarterStart.getMonth() + 3,
        0,
      );
      startDate.value = formatDateForInput(quarterStart);
      endDate.value = formatDateForInput(quarterEnd);
      break;
    }
    case "year": {
      const yearStart = new Date(today.getFullYear(), 0, 1);
      const yearEnd = new Date(today.getFullYear(), 11, 31);
      startDate.value = formatDateForInput(yearStart);
      endDate.value = formatDateForInput(yearEnd);
      break;
    }
    case "custom":
      // Keep current dates
      break;
  }
};

const formatDateForInput = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

// 开始日期按当天 00:00:00，结束日期按当天 23:59:59.999 传给后端，避免结束日数据被截掉。
const formatDateWithTimezone = (dateString, boundary = "start") => {
  if (!dateString) return null;
  const time = boundary === "end" ? "T23:59:59.999" : "T00:00:00.000";
  const date = new Date(dateString + time);
  return date.toISOString();
};

const applyDateFilter = async () => {
  commissionPage.value = 1;
  selectedRows.value = new Map();
  await fetchData();
};

// 导出 CSV：空文本用 "-"，空数字用 "0"（与 prod 展示一致）
const EXPORT_NUMERIC_FIELDS = new Set([
  "tradingVolume",
  "commissionEarned",
  "bdLots",
  "bdAmount",
  "bdBalance",
  "bdProfitLoss",
  "bdMarginLevel",
  "bdAccountEquity",
  "bdCredit",
  "bdTradingId",
]);

const EXPORT_MONEY_FIELDS = new Set([
  "tradingVolume",
  "commissionEarned",
  "bdAmount",
  "bdBalance",
  "bdProfitLoss",
  "bdAccountEquity",
  "bdCredit",
]);

const isExportBlank = (value) =>
  value === "" || value === null || value === undefined;

const formatExportCell = (value, field) => {
  if (EXPORT_NUMERIC_FIELDS.has(field)) {
    if (isExportBlank(value)) {
      return "0";
    }
    const num = typeof value === "number" ? value : Number(value);
    if (Number.isNaN(num)) {
      return "0";
    }
    if (EXPORT_MONEY_FIELDS.has(field)) {
      return num.toFixed(2);
    }
    return String(num);
  }

  if (isExportBlank(value) || value === "—") {
    return "-";
  }

  return String(value);
};

// 将 JSON 数据转换为 CSV
// eslint-disable-next-line no-unused-vars
const convertToCSV = (data, headers, fields = null) => {
  if (!data || data.length === 0) {
    return "";
  }

  // 添加 BOM 以支持 Excel 正确显示中文
  let csv = "\uFEFF";

  // 写入表头
  csv += headers.join(",") + "\n";

  // 写入数据
  data.forEach((row) => {
    const values = headers.map((header, i) => {
      // 根据表头找到对应的字段名
      const fieldMap = {
        "Referral Name": "referralName",
        "Referral Email": "summaryEmail",
        Email: "email",
        "Referral Code": "referralCode",
        Type: "type",
        Level: "tierName",
        "Trading Volume": "tradingVolume",
        "Commission Earned": "commissionEarned",
        "Payment Status": "summaryStatus",
        Status: "status",
      };
      // 后端导出会带 fields（与 headers 一一对应），优先用它精确定位字段，避免 summary/明细 同名键（email/status）相互覆盖
      const field =
        fields && fields[i]
          ? fields[i]
          : fieldMap[header] || header.toLowerCase().replace(/\s+/g, "");
      let value = formatExportCell(row[field], field);

      // 处理包含逗号、引号或换行符的值
      if (value.includes(",") || value.includes('"') || value.includes("\n")) {
        value = '"' + value.replace(/"/g, '""') + '"';
      }

      return value;
    });
    csv += values.join(",") + "\n";
  });

  return csv;
};

const EXPORT_POLL_MS = 7000;

const exportPolling = ref(false);
const exportJobId = ref("");
const exportStatusText = ref("");
const exportBannerVisible = ref(false);
const exportCancelling = ref(false);
const lastExportProgress = ref({
  jobId: "",
  percent: 0,
  message: "",
  status: "",
});
const exportProgressPercent = computed(() =>
  Math.max(0, Math.min(100, Number(lastExportProgress.value?.percent || 0))),
);
let exportPollTimer = null;
let discardDownload = false;
let pollGeneration = 0;
let exportModalDelayTimer = null;
const EXPORT_MODAL_DELAY_MS = 3000;

const exportModal = ref({
  visible: false,
  jobId: "",
  percent: 0,
  message: "",
  busy: false,
});

const stopExportPoll = () => {
  if (exportPollTimer) {
    clearInterval(exportPollTimer);
    exportPollTimer = null;
  }
  exportPolling.value = false;
};

const invalidateExportPolls = () => {
  pollGeneration += 1;
  stopExportPoll();
};

const resetExportUi = () => {
  discardDownload = false;
  exportCancelling.value = false;
  exportStatusText.value = "";
  exportBannerVisible.value = false;
  exportJobId.value = "";
  clearExportModalDelay();
  closeExportModal();
};

const isAlreadyFinishedCancelError = (error) => {
  const code = Number(error?.statusCode || 0);
  const message = String(error?.message || "").toLowerCase();
  return (
    code === 400 &&
    (message.includes("already completed") || message.includes("not ready"))
  );
};

const clearExportModalDelay = () => {
  if (exportModalDelayTimer) {
    clearTimeout(exportModalDelayTimer);
    exportModalDelayTimer = null;
  }
};

const scheduleExportModal = (payload) => {
  clearExportModalDelay();
  const seed = payload?.data ?? payload ?? {};
  lastExportProgress.value = {
    jobId: String(seed.jobId || ""),
    percent: Number(seed.percent || 0),
    message: String(seed.message || ""),
    status: String(seed.status || "queued"),
  };
  exportModalDelayTimer = setTimeout(() => {
    exportModalDelayTimer = null;
    const latest = lastExportProgress.value;
    if (
      !["queued", "running", "cancelling"].includes(String(latest.status || ""))
    ) {
      return;
    }
    if (!latest.jobId || latest.jobId !== exportJobId.value) {
      return;
    }
    openExportModal(latest);
  }, EXPORT_MODAL_DELAY_MS);
};

const applyExportProgress = (payload) => {
  const data = payload?.data ?? payload ?? {};
  const percent = Number(data.percent ?? 0);
  const status = String(data.status || "");
  const message = String(data.message || "");
  const jobId = String(data.jobId || exportJobId.value || "");
  if (exportCancelling.value && ["queued", "running"].includes(status)) {
    const cancellingText = t("commExportCancelling", "Cancelling export...");
    lastExportProgress.value = {
      jobId,
      percent,
      message: cancellingText,
      status: "cancelling",
    };
    exportStatusText.value = `${cancellingText} (${percent}%)`;
    return { ...data, percent, status: "cancelling", message: cancellingText };
  }
  if (status === "cancelling") {
    exportCancelling.value = true;
  }
  lastExportProgress.value = { jobId, percent, message, status };
  exportStatusText.value = message
    ? `${message} (${percent}%)`
    : t("commExportProgress", "Export progress: {percent}%").replace(
        "{percent}",
        String(percent),
      );
  if (
    exportModal.value.visible &&
    exportModal.value.jobId === (data.jobId || exportJobId.value)
  ) {
    exportModal.value = {
      ...exportModal.value,
      percent,
      message: message || exportModal.value.message,
    };
  }
  return { ...data, percent, status, message };
};

const triggerBlobDownload = (blob, filename) => {
  const link = document.createElement("a");
  const url = URL.createObjectURL(blob);
  link.setAttribute("href", url);
  link.setAttribute("download", filename);
  link.style.visibility = "hidden";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  setTimeout(() => URL.revokeObjectURL(url), 100);
};

const downloadExportFile = async (jobId) => {
  if (discardDownload) return;
  const response = await depositWithdrawReportService.downloadExport(jobId);
  if (discardDownload) return;
  const blob =
    response?.data instanceof Blob
      ? response.data
      : new Blob([response?.data || response], {
          type: "text/csv;charset=utf-8;",
        });
  if (blob.type && blob.type.includes("application/json")) {
    const text = await blob.text();
    let parsed = null;
    try {
      parsed = JSON.parse(text);
    } catch (_) {
      // Ignore malformed error payloads and use the generic message below.
    }
    throw new Error(
      parsed?.message ||
        t("commExportFailed", "Failed to export report. Please try again."),
    );
  }
  if (discardDownload) return;
  triggerBlobDownload(
    blob,
    `deposit_withdraw_report_${new Date().toISOString().split("T")[0]}.csv`,
  );
};

const handleExportTerminal = async (data) => {
  const status = String(data.status || "");
  const jobId = data.jobId || exportJobId.value;
  clearExportModalDelay();
  stopExportPoll();
  if (status === "done") {
    if (discardDownload) {
      resetExportUi();
      return;
    }
    try {
      await downloadExportFile(jobId);
      if (discardDownload) {
        resetExportUi();
        return;
      }
      resetExportUi();
    } catch (error) {
      console.error("Failed to download export:", error);
      alert(
        error?.message ||
          t("commExportFailed", "Failed to export report. Please try again."),
      );
    }
    return;
  }
  if (status === "cancelled") {
    resetExportUi();
    return;
  }
  if (status === "error") {
    resetExportUi();
    alert(
      data.message ||
        t("commExportFailed", "Failed to export report. Please try again."),
    );
  }
};

const pollExportOnce = async (jobId) => {
  const generation = pollGeneration;
  const response = await depositWithdrawReportService.getExportStatus(jobId);
  if (generation !== pollGeneration) return;
  const data = applyExportProgress(response);
  if (["done", "error", "cancelled"].includes(String(data.status || ""))) {
    await handleExportTerminal(data);
  }
};

const startExportPoll = (jobId) => {
  invalidateExportPolls();
  exportJobId.value = jobId;
  exportPolling.value = true;
  exportBannerVisible.value = true;
  pollExportOnce(jobId).catch((error) => {
    console.error("Export poll failed:", error);
  });
  exportPollTimer = setInterval(() => {
    pollExportOnce(jobId).catch((error) => {
      console.error("Export poll failed:", error);
    });
  }, EXPORT_POLL_MS);
};

const openExportModal = (payload) => {
  const data = payload?.data ?? payload ?? {};
  exportBannerVisible.value = false;
  exportModal.value = {
    visible: true,
    jobId: String(data.jobId || ""),
    percent: Number(data.percent || 0),
    message: String(data.message || ""),
    busy: false,
  };
};

const closeExportModal = () => {
  exportModal.value = {
    visible: false,
    jobId: "",
    percent: 0,
    message: "",
    busy: false,
  };
};

const onExportModalContinue = () => {
  const jobId = exportModal.value.jobId;
  closeExportModal();
  exportBannerVisible.value = true;
  if (jobId) {
    startExportPoll(jobId);
  }
};

const onExportModalCancel = async () => {
  const jobId = exportModal.value.jobId;
  if (!jobId || exportCancelling.value) return;

  const percent = Number(exportModal.value.percent || 0);
  closeExportModal();
  await requestUserCancel(jobId, percent);
};

const requestUserCancel = async (jobId, percent) => {
  if (!jobId || exportCancelling.value) return;
  discardDownload = true;
  exportCancelling.value = true;
  invalidateExportPolls();
  exportJobId.value = jobId;
  exportBannerVisible.value = true;
  exportStatusText.value = `${t("commExportCancelling", "Cancelling export...")} (${percent}%)`;
  try {
    await depositWithdrawReportService.cancelExport(jobId);
    startExportPoll(jobId);
  } catch (error) {
    if (isAlreadyFinishedCancelError(error)) {
      resetExportUi();
      return;
    }
    discardDownload = false;
    exportCancelling.value = false;
    startExportPoll(jobId);
    alert(
      error?.message ||
        t("commExportFailed", "Failed to export report. Please try again."),
    );
  }
};

const cancelActiveExport = async () => {
  const jobId = exportJobId.value;
  const percentMatch = String(exportStatusText.value || "").match(/\((\d+)%\)/);
  const percent = percentMatch ? percentMatch[1] : "0";
  await requestUserCancel(jobId, percent);
};

const enqueueNewExport = async () => {
  discardDownload = false;
  try {
    const exportParams = {};
    if (startDate.value)
      exportParams.start_date = formatDateWithTimezone(startDate.value);
    if (endDate.value)
      exportParams.end_date = formatDateWithTimezone(endDate.value, "end");
    if (searchQuery.value) exportParams.search = searchQuery.value;
    Object.assign(exportParams, selectedIbPartnerParams());
    const items = Array.from(selectedRows.value.values());

    const response = await depositWithdrawReportService.export(
      exportParams,
      items,
    );
    const result = response?.data ?? response;
    const jobId = result?.jobId;
    if (!jobId) {
      alert(
        t("commExportFailed", "Failed to export report. Please try again."),
      );
      return;
    }
    exportStatusText.value =
      t("commExportQueued", "Export queued...") + " (0%)";
    startExportPoll(jobId);
    scheduleExportModal({
      jobId,
      status: "queued",
      percent: 0,
      message: t("commExportQueued", "Export queued..."),
    });
  } catch (error) {
    if (error?.statusCode === 409) {
      openExportModal(error?.errors || {});
      return;
    }
    console.error("Failed to export:", error);
    alert(
      error?.message ||
        t("commExportFailed", "Failed to export report. Please try again."),
    );
  }
};

const exportReport = async () => {
  try {
    const response = await depositWithdrawReportService.getActiveExport();
    const active = response?.data ?? response;
    if (active?.active) {
      const status = String(active.status || "");
      // Modal only when a job is still in progress
      if (["queued", "running", "cancelling"].includes(status)) {
        openExportModal(active);
        return;
      }
      // Ready file: download immediately, no modal
      if (status === "done" && active.jobId) {
        try {
          await downloadExportFile(active.jobId);
          exportJobId.value = "";
          exportStatusText.value = "";
          exportBannerVisible.value = false;
        } catch (error) {
          console.error("Failed to download export:", error);
          alert(
            error?.message ||
              t(
                "commExportFailed",
                "Failed to export report. Please try again.",
              ),
          );
        }
        return;
      }
    }
    // No active job — enqueue and poll until file downloads
    await enqueueNewExport();
  } catch (error) {
    console.error("Failed to check export status:", error);
    alert(
      error?.message ||
        t("commExportFailed", "Failed to export report. Please try again."),
    );
  }
};

onUnmounted(() => {
  stopExportPoll();
});

const resumeActiveExportIfAny = async () => {
  try {
    const response = await depositWithdrawReportService.getActiveExport();
    const active = response?.data ?? response;
    if (!active?.active || !active.jobId) return;
    const status = String(active.status || "");
    if (!["queued", "running", "cancelling"].includes(status)) return;
    applyExportProgress(active);
    startExportPoll(active.jobId);
  } catch (error) {
    console.error("Failed to resume export status:", error);
  }
};

const fetchData = async () => {
  loading.value = true;
  detailExpandedKey.value = null;
  loadingDetail.value = {};
  try {
    const params = {
      page: commissionPage.value,
      per_page: commissionPerPage.value,
    };
    if (startDate.value)
      params.start_date = formatDateWithTimezone(startDate.value);
    if (endDate.value)
      params.end_date = formatDateWithTimezone(endDate.value, "end");
    if (searchQuery.value) params.search = searchQuery.value;
    Object.assign(params, selectedIbPartnerParams());

    const response = await depositWithdrawReportService.getList(params);
    const data = response.data?.data || response.data;

    if (data && data.items) {
      commissions.value = data.items.map((item) => ({ ...item }));
      totalCommissions.value = data.pagination?.total ?? data.total ?? 0;
    } else {
      commissions.value = [];
      totalCommissions.value = 0;
    }
  } catch (error) {
    console.error("Failed to fetch deposit withdraw list:", error);
    commissions.value = [];
    totalCommissions.value = 0;
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  // Initialize date filter to current month
  selectPreset("month");
  await fetchIbPartners();
  await fetchData();
  await resumeActiveExportIfAny();
});
</script>

<style scoped>
.container {
  max-width: 1400px;
  margin: 0 auto;
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

.commission-ib-switcher__select :deep(.custom-select__trigger) {
  min-height: 40px;
  border-color: var(--color-border);
  border-radius: var(--radius-md);
}

/* Statistics Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.stat-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 24px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.stat-title {
  font-size: 14px;
  color: var(--color-muted);
  font-weight: 500;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  color: white;
}

.stat-card.total .stat-icon {
  background: linear-gradient(
    135deg,
    var(--color-success-solid) 0%,
    var(--color-success-solid) 100%
  );
}

.stat-card.month .stat-icon,
.stat-card.paid .stat-icon {
  background: linear-gradient(
    135deg,
    var(--color-info-solid) 0%,
    var(--color-info-solid) 100%
  );
}

.stat-card.pending .stat-icon {
  background: linear-gradient(
    135deg,
    var(--color-warning-solid) 0%,
    var(--color-warning-solid) 100%
  );
}

.stat-card.referrals .stat-icon {
  background: var(--color-brand-solid);
}

.stat-value {
  font-size: 32px;
  font-weight: 700;
  color: var(--color-ink);
  margin-bottom: 8px;
}

.stat-footer {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 12px;
}

.stat-change {
  font-size: 12px;
  display: flex;
  align-items: center;
  gap: 4px;
  font-weight: 600;
}

.stat-change.positive {
  color: var(--color-success);
}

.stat-change.neutral {
  color: var(--color-muted);
}

.stat-change.negative {
  color: var(--color-danger);
}

.stat-period {
  font-size: 12px;
  color: var(--color-faint);
}

/* Date Filter Section */
.date-filter-section {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 20px;
  margin-bottom: 30px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.date-filter-container {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.date-filter-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
}

.date-filter-presets {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.preset-btn {
  padding: 8px 16px;
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 13px;
  color: var(--color-text);
  cursor: pointer;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    opacity 0.2s ease,
    transform 0.2s ease;
}

.preset-btn:hover {
  background: var(--color-surface-muted);
  border-color: var(--color-border-strong);
}

.preset-btn.active {
  background: var(--color-brand-solid);
  color: white;
  border-color: transparent;
}

.date-input-wrapper {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.date-input-wrapper label {
  font-size: 12px;
  color: var(--color-muted);
}

.date-input-wrapper input {
  padding: 8px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 13px;
}

.btn-apply-filter,
.btn-export {
  padding: 10px 20px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    opacity 0.2s ease,
    transform 0.2s ease;
  border: none;
}

.btn-apply-filter {
  background: var(--color-brand-solid);
  color: white;
}

.btn-apply-filter:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.btn-export {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  color: var(--color-text);
}

.btn-export:hover {
  background: var(--color-surface-muted);
  border-color: var(--color-border-strong);
}

.export-status-text {
  font-size: 12px;
  color: var(--color-muted);
  align-self: center;
  white-space: nowrap;
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
  font-size: 13px;
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
  font-size: 13px;
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

/* Commission Table */
.commission-table-container {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 24px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.table-header {
  margin-bottom: 20px;
}

.table-header h2 {
  font-size: 18px;
  font-weight: 600;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
}

.table-header i {
  color: var(--color-brand);
}

.commission-table {
  width: 100%;
  border-collapse: collapse;
  min-width: max-content;
}

.commission-table-scroll {
  width: 100%;
  overflow-x: auto;
  overflow-y: hidden;
  -webkit-overflow-scrolling: touch;
}

.commission-table thead {
  background: var(--color-surface-soft);
}

.commission-table th {
  padding: 12px 16px;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  border-bottom: 1px solid var(--color-border);
  white-space: nowrap;
}

.commission-table tbody tr {
  border-bottom: 1px solid var(--color-border);
  transition: background 0.2s ease;
}

.commission-table tbody tr:hover {
  background: var(--color-surface-soft);
}

.commission-table td {
  padding: 16px;
  font-size: 14px;
  white-space: nowrap;
}

.checkbox-col {
  width: 50px;
  text-align: center;
}
.custom-checkbox {
  position: relative;
  display: inline-block;
  width: 20px;
  height: 20px;
  vertical-align: middle;
}
.custom-checkbox input[type="checkbox"] {
  position: absolute;
  opacity: 0;
  cursor: pointer;
  width: 20px;
  height: 20px;
  margin: 0;
}
.checkbox-checkmark {
  position: absolute;
  top: 0;
  left: 0;
  height: 20px;
  width: 20px;
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 4px;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
}
.custom-checkbox:hover .checkbox-checkmark {
  border-color: var(--color-brand);
}
.custom-checkbox input[type="checkbox"]:checked ~ .checkbox-checkmark {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
}
.checkbox-checkmark:after {
  content: "";
  position: absolute;
  display: none;
  left: 6px;
  top: 2px;
  width: 5px;
  height: 10px;
  border: solid white;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
}
.custom-checkbox input[type="checkbox"]:checked ~ .checkbox-checkmark:after {
  display: block;
}
.referral-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.referral-info--tree {
  display: flex;
  align-items: center;
  gap: 8px;
}

.level-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

.level-badge.level-tier-1 {
  background: var(--color-brand-soft);
  color: var(--color-purple);
}

.level-badge.level-tier-2 {
  background: var(--color-info-soft);
  color: var(--color-brand);
}

.level-badge.level-tier-3 {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.level-badge.level-tier-other {
  background: var(--color-surface-soft);
  color: var(--color-text);
}

.level-badge.level-client {
  background: var(--color-surface-soft);
  color: var(--color-text);
}

.level-badge.level-unknown {
  background: var(--color-surface-soft);
  color: var(--color-faint);
}

.cell-referral {
  min-width: 260px;
}

.commission-table tbody tr.row-child {
  background: var(--color-surface-soft);
}

.detail-section-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 15px;
  margin-top: 25px;
}

.no-breakdown {
  padding: 24px;
  text-align: center;
  color: var(--color-muted);
  font-size: 13px;
}

.detail-total-row {
  margin-top: 16px;
  padding: 12px 0;
  font-size: 14px;
  color: var(--color-ink);
  border-top: 1px solid var(--color-border);
  display: flex;
  flex-wrap: wrap;
  gap: 10px 24px;
}

.detail-total-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.detail-total-label {
  color: var(--color-muted);
}

.detail-total-value {
  font-weight: 700;
  color: var(--color-ink);
}

.detail-total-item--grand .detail-total-value {
  color: var(--color-brand);
}

.detail-total-count {
  color: var(--color-faint);
  font-size: 12px;
  font-weight: 400;
}

.referral-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--color-brand-solid);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
  flex-shrink: 0;
}

.referral-details {
  flex: 1;
}

.referral-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 2px;
}

.referral-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

.self-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--color-success-soft);
  color: var(--color-success);
  font-size: 11px;
  font-weight: 700;
  line-height: 1.4;
}

.referral-code {
  font-size: 12px;
  color: var(--color-muted);
}

/* Same Type colors as admin IB Commission / Rebate Report */
.type-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

.type-badge.direct,
.type-badge.direct-client {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.type-badge.sub-ib {
  background: var(--color-brand-soft);
  color: var(--color-purple);
}

.commission-amount {
  font-weight: 600;
  color: var(--color-ink);
}

.commission-amount.net-deposit--positive,
.detail-total-value.net-deposit--positive {
  color: var(--color-success);
}

.commission-amount.net-deposit--negative,
.detail-total-value.net-deposit--negative {
  color: var(--color-danger);
}

.status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

/* 后端 status 取自 ib_commission_order.status：completed / approved / cancelled / pending
   原本只有 paid / pending / processing 三个 class，新值会落到无样式 base，所以加 alias */
.status-badge.paid,
.status-badge.completed,
.status-badge.approved {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.status-badge.processing {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.status-badge.cancelled,
.status-badge.rejected,
.status-badge.failed,
.status-badge.payment_failed {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.status-badge.unpaid {
  background: var(--color-surface-soft);
  color: var(--color-text);
}

.direction-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

.direction-badge.deposit {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.direction-badge.withdrawal {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.btn-action {
  padding: 6px 12px;
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 12px;
  color: var(--color-text);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    opacity 0.2s ease,
    transform 0.2s ease;
}

.btn-action:hover {
  background: var(--color-surface-muted);
  border-color: var(--color-border-strong);
}

.btn-detail {
  background: var(--color-brand-solid);
  color: white;
  border: none;
}

.btn-detail:hover {
  background: linear-gradient(
    135deg,
    var(--color-brand-strong) 0%,
    var(--color-brand-solid) 100%
  );
}

.detail-row {
  display: none;
}

.detail-row.show {
  display: table-row;
}

/* 详情 td 不参与外层 7 列的列宽计算，避免内层 19 列宽表把外层 .commission-table-scroll 也撑出横滚 */
.commission-table tbody tr.detail-row > td {
  max-width: 0;
  padding: 0;
  overflow: hidden;
}

.detail-content {
  padding: 20px;
  background: var(--color-surface-soft);
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}

/* 内层表格自己横滚（外层不动） */
.detail-content .detail-table-scroll {
  max-width: 100%;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
  margin-bottom: 25px;
}

.detail-item {
  background: var(--color-surface);
  padding: 20px;
  border-radius: var(--radius-md);
  border-left: 1px solid var(--color-brand);
}

.detail-label {
  font-size: 12px;
  color: var(--color-muted);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  display: block;
  margin-bottom: 8px;
}

.detail-value {
  font-size: 18px;
  font-weight: 700;
  color: var(--color-ink);
}

@media (max-width: 1024px) {
  .detail-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }
}

.detail-table {
  width: 100%;
  min-width: max-content;
  border-collapse: collapse;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  overflow: hidden;
}

.detail-table-scroll {
  width: 100%;
  overflow-x: auto;
  overflow-y: hidden;
  -webkit-overflow-scrolling: touch;
}

.detail-table thead {
  background: var(--color-surface-soft);
}

.detail-table th {
  padding: 10px 12px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text);
  border-bottom: 1px solid var(--color-border);
  white-space: nowrap;
}

.detail-table td {
  padding: 10px 12px;
  font-size: 13px;
  color: var(--color-ink);
  border-bottom: 1px solid var(--color-border);
  white-space: nowrap;
}

.detail-table td.empty-state,
.detail-table td.empty-state i,
.detail-table td.empty-state p {
  color: var(--color-faint);
}

.detail-table tbody tr:last-child td {
  border-bottom: none;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--color-faint);
}

.empty-state i {
  font-size: 48px;
  margin-bottom: 16px;
  display: block;
}

.empty-state p {
  font-size: 16px;
  margin: 0;
}

/* Table toolbar（右上角 Show Rows，参考后台 Final Review List） */
.commission-table-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 12px;
}

.commission-table-toolbar__left {
  flex: 1;
}

.commission-table-toolbar__title {
  font-size: 16px;
  margin: 0;
  font-weight: 600;
  color: var(--color-ink);
}

.commission-table-toolbar__right {
  flex-shrink: 0;
}

.commission-show-rows {
  display: flex;
  align-items: center;
  gap: 8px;
}

.commission-show-rows__label {
  font-size: 14px;
  color: var(--color-text);
  white-space: nowrap;
}

.commission-show-rows__select {
  min-width: 72px;
}

.commission-show-rows__select :deep(.custom-select__trigger) {
  min-height: 34px;
  padding: 6px 10px;
  border-radius: var(--radius-md);
  border-color: var(--color-border);
  font-size: 13px;
}

.commission-show-rows__select :deep(.custom-select__trigger-text) {
  text-align: left;
}

.commission-show-rows__select :deep(.custom-select__menu) {
  min-width: 100%;
}

/* 底部分页（参考后台 Final Review List：左 Showing x-y of z，右 Prev + Page n of N + Next） */
.commission-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
  margin-top: 0;
  padding: 16px 24px;
  background: var(--color-surface-soft);
  border-top: 1px solid var(--color-border);
}

/* Direct Client 详情内嵌分页：跟外层同一组件，缩小内边距/去掉顶部边框 */
.commission-pagination--detail {
  margin-top: 12px;
  padding: 10px 14px;
  border-top: none;
  border-radius: var(--radius-md);
}

.commission-pagination__info {
  font-size: 14px;
  color: var(--color-text);
}

.commission-pagination__btns {
  display: flex;
  align-items: center;
  gap: 12px;
}

.commission-pagination__page {
  font-size: 14px;
  color: var(--color-text);
}

.commission-pagination__btn {
  padding: 8px 14px;
  font-size: 13px;
  background: var(--color-border);
  color: var(--color-text);
  border: none;
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition:
    background 0.2s,
    color 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.commission-pagination__btn:hover:not(:disabled) {
  background: var(--color-brand-solid);
  color: white;
}

.commission-pagination__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .container {
    padding: 12px;
  }

  .commission-ib-switcher {
    align-items: stretch;
    flex-direction: column;
  }

  .commission-ib-switcher__select {
    width: 100%;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  /* Time Period filter 移动端 */
  .date-filter-section {
    padding: 14px;
  }

  .date-filter-container {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }

  .date-filter-label {
    font-size: 13px;
  }

  .date-filter-presets {
    width: 100%;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
  }

  .preset-btn {
    text-align: center;
    padding: 8px 12px;
    font-size: 12px;
  }

  .date-input-wrapper {
    width: 100%;
  }

  .date-input-wrapper input {
    width: 100%;
  }

  .btn-apply-filter,
  .btn-export {
    width: 100%;
    justify-content: center;
    padding: 10px 16px;
    font-size: 13px;
  }

  .commission-table {
    font-size: 12px;
  }

  .commission-table th,
  .commission-table td {
    padding: 8px;
  }

  .commission-pagination {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
