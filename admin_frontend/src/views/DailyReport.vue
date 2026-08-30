<template>
  <div class="daily-report-page">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-title">
        <h1>{{ t("page_dailyReport_title", "Daily Report") }}</h1>
        <p>
          {{
            t(
              "page_dailyReport_sub",
              "Daily deposit, withdrawal and sign-ups per sales, against each sales monthly KPI",
            )
          }}
        </p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <div v-if="!hasReadonlyPermission" class="dr-no-permission">
      {{
        t(
          "dailyReport_noPermission",
          "You do not have permission to view this page.",
        )
      }}
    </div>

    <template v-else>
      <!-- Month + day selector -->
      <div class="dr-filter-card">
        <el-config-provider :locale="elementPlusLocale">
          <div class="dr-filter-top">
            <div class="dr-filter-month">
              <span class="dr-filter-label">{{
                t("dailyReport_month", "Month")
              }}</span>
              <el-date-picker
                v-model="selectedMonth"
                type="month"
                value-format="YYYY-MM"
                :clearable="false"
                :disabled-date="disableFutureMonth"
                :placeholder="t('dailyReport_month', 'Month')"
                class="dr-month-picker"
                @change="onMonthChange"
              />
              <button
                type="button"
                class="dr-btn-plain"
                :disabled="isTodaySelected"
                @click="jumpToToday"
              >
                {{ t("dailyReport_preset_today", "Today") }}
              </button>
            </div>

            <div v-if="canViewAllSales" class="dr-search">
              <input
                v-model="search"
                type="text"
                class="dr-search-input"
                :placeholder="
                  t('dailyReport_searchSales', 'Search sales name or email')
                "
                @keyup.enter="loadReport"
              />
              <button type="button" class="dr-btn-plain" @click="loadReport">
                <i class="fas fa-search"></i> {{ t("common_search", "Search") }}
              </button>
            </div>

            <button
              type="button"
              class="dr-btn-refresh"
              :disabled="loading"
              @click="loadReport"
            >
              <i
                class="fas"
                :class="loading ? 'fa-spinner fa-spin' : 'fa-sync-alt'"
              ></i>
              {{ t("common_refresh", "Refresh") }}
            </button>

            <span
              class="dr-tz-badge"
              :title="
                t(
                  'dailyReport_tzHint',
                  'Days are split by this timezone (taken from your device, falls back to UTC+10)',
                )
              "
            >
              <i class="fas fa-globe"></i> {{ timezoneLabel }}
            </span>
          </div>
        </el-config-provider>

        <!-- Day strip: pick any day inside the selected month -->
        <div class="dr-day-strip">
          <span class="dr-filter-label">{{ t("dailyReport_day", "Day") }}</span>
          <div class="dr-day-strip-scroll">
            <button
              v-for="day in daysInMonth"
              :key="day"
              type="button"
              :class="[
                'dr-day-btn',
                { active: day === selectedDay, disabled: isFutureDay(day) },
              ]"
              :disabled="isFutureDay(day)"
              :title="weekdayLabel(day)"
              @click="selectDay(day)"
            >
              <span class="dr-day-num">{{ day }}</span>
              <span class="dr-day-weekday">{{ weekdayLabel(day) }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Selected day totals -->
      <div class="stats-grid">
        <div class="stat-card deposit">
          <div class="stat-header">
            <span class="stat-title">{{
              t("dailyReport_stat_deposit", "Deposit")
            }}</span>
            <div class="stat-icon"><i class="fas fa-arrow-down"></i></div>
          </div>
          <div class="stat-value">
            {{ formatCurrency(summary.deposits || 0) }}
          </div>
          <div class="stat-footer">
            <i class="fas fa-receipt"></i>
            <span>{{
              tParams("dailyReport_stat_txCount", "{count} transactions", {
                count: formatNumber(summary.depositCount || 0),
              })
            }}</span>
          </div>
        </div>

        <div class="stat-card withdrawal">
          <div class="stat-header">
            <span class="stat-title">{{
              t("dailyReport_stat_withdrawal", "Withdrawal")
            }}</span>
            <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
          </div>
          <div class="stat-value">
            {{ formatCurrency(summary.withdrawals || 0) }}
          </div>
          <div class="stat-footer">
            <i class="fas fa-receipt"></i>
            <span>{{
              tParams("dailyReport_stat_txCount", "{count} transactions", {
                count: formatNumber(summary.withdrawalCount || 0),
              })
            }}</span>
          </div>
        </div>

        <div class="stat-card net">
          <div class="stat-header">
            <span class="stat-title">{{
              t("dailyReport_stat_netDeposit", "Net Deposit")
            }}</span>
            <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
          </div>
          <div class="stat-value" :class="amountClass(summary.netDeposit)">
            {{ formatSignedCurrency(summary.netDeposit || 0) }}
          </div>
          <div class="stat-footer">
            <i class="fas fa-calendar-day"></i>
            <span>{{ dayLabel }}</span>
          </div>
        </div>

        <div class="stat-card leads">
          <div class="stat-header">
            <span class="stat-title">{{
              t("dailyReport_stat_newLeads", "New Leads")
            }}</span>
            <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
          </div>
          <div class="stat-value">
            {{ formatNumber(summary.newLeads || 0) }}
          </div>
          <div class="stat-footer">
            <i class="fas fa-user-friends"></i>
            <span>{{
              tParams("dailyReport_stat_salesCount", "{count} sales", {
                count: rows.length,
              })
            }}</span>
          </div>
        </div>

        <div class="stat-card clients">
          <div class="stat-header">
            <span class="stat-title">{{
              t("dailyReport_stat_newClients", "New Clients")
            }}</span>
            <div class="stat-icon"><i class="fas fa-user-check"></i></div>
          </div>
          <div class="stat-value">
            {{ formatNumber(summary.newClients || 0) }}
          </div>
          <div class="stat-footer">
            <i class="fas fa-user-friends"></i>
            <span>{{
              tParams("dailyReport_stat_salesCount", "{count} sales", {
                count: rows.length,
              })
            }}</span>
          </div>
        </div>

        <div class="stat-card kpi">
          <div class="stat-header">
            <span class="stat-title">{{
              tParams("dailyReport_stat_kpi", "{month} KPI", {
                month: monthLabel,
              })
            }}</span>
            <div class="stat-icon"><i class="fas fa-bullseye"></i></div>
          </div>
          <div class="stat-value">
            {{ formatSignedCurrency(summary.monthToDateNetDeposit || 0) }}
            <span class="stat-value-sub"
              >/
              {{
                summary.kpiTarget ? formatCurrency(summary.kpiTarget) : "—"
              }}</span
            >
          </div>
          <div class="dr-progress">
            <div
              class="dr-progress-bar"
              :class="rateClass(summary.kpiAchievementRate)"
              :style="{ width: barWidth(summary.kpiAchievementRate) }"
            ></div>
          </div>
          <div class="stat-footer">
            <span
              v-if="hasRate(summary.kpiAchievementRate)"
              :class="rateTextClass(summary.kpiAchievementRate)"
            >
              <strong>{{ summary.kpiAchievementRate }}%</strong>
              {{ t("dailyReport_ofMonthlyTarget", "of monthly target") }}
            </span>
            <span v-else>{{
              t("dailyReport_noTargetSet", "No target set yet")
            }}</span>
          </div>
        </div>
      </div>

      <div v-if="error" class="dr-error">
        <i class="fas fa-exclamation-triangle"></i> {{ error }}
      </div>

      <!-- Per-sales table: selected day on the left, running month on the right -->
      <div class="dr-table-card">
        <div class="dr-table-header">
          <h3>
            <i class="fas fa-user-tie"></i>
            {{
              tParams("dailyReport_tableTitle", "Sales Performance — {date}", {
                date: dayLabel,
              })
            }}
          </h3>
          <div class="dr-table-header-notes">
            <span class="dr-note">
              <i class="fas fa-circle-check"></i>
              {{
                t(
                  "dailyReport_completedOnly",
                  "Completed deposits and withdrawals only",
                )
              }}
            </span>
            <span v-if="!canEditKpi" class="dr-readonly-hint">
              <i class="fas fa-lock"></i>
              {{
                t("dailyReport_kpiReadonly", "KPI is read-only for your role")
              }}
            </span>
          </div>
        </div>

        <div v-if="loading" class="dr-loading">
          {{ t("common_loading", "Loading...") }}
        </div>

        <div v-else class="dr-table-scroll">
          <table class="dr-table">
            <thead>
              <tr class="dr-group-row">
                <th rowspan="2" class="dr-col-sales">
                  {{ t("dailyReport_th_sales", "Sales") }}
                </th>
                <th colspan="5" class="dr-group dr-group-day">
                  <i class="fas fa-calendar-day"></i> {{ dayLabel }}
                </th>
                <th colspan="2" class="dr-group dr-group-month">
                  <i class="fas fa-bullseye"></i>
                  {{
                    tParams(
                      "dailyReport_group_month",
                      "{month} to date vs KPI",
                      { month: monthLabel },
                    )
                  }}
                </th>
              </tr>
              <tr>
                <th class="dr-num">
                  {{ t("dailyReport_th_deposit", "Deposit") }}
                </th>
                <th class="dr-num">
                  {{ t("dailyReport_th_withdrawal", "Withdrawal") }}
                </th>
                <th class="dr-num">
                  {{ t("dailyReport_th_netDeposit", "Net Deposit") }}
                </th>
                <th class="dr-num">
                  {{ t("dailyReport_th_newLeads", "New Leads") }}
                </th>
                <th class="dr-num">
                  {{ t("dailyReport_th_newClients", "New Clients") }}
                </th>
                <th class="dr-num dr-col-kpi">
                  {{ t("dailyReport_th_netVsKpi", "Net / KPI Target") }}
                </th>
                <th class="dr-num">
                  {{ t("dailyReport_th_achievement", "Achievement") }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.salesId">
                <td class="dr-col-sales">
                  <div class="dr-sales-name">{{ row.salesName }}</div>
                  <div class="dr-sales-email">{{ row.email }}</div>
                </td>
                <td class="dr-num dr-deposit">
                  {{ formatCurrency(row.deposits) }}
                </td>
                <td class="dr-num dr-withdrawal">
                  {{ formatCurrency(row.withdrawals) }}
                </td>
                <td class="dr-num" :class="amountClass(row.netDeposit)">
                  <strong>{{ formatSignedCurrency(row.netDeposit) }}</strong>
                </td>
                <td class="dr-num">{{ formatNumber(row.newLeads) }}</td>
                <td class="dr-num">{{ formatNumber(row.newClients) }}</td>
                <td class="dr-col-kpi">
                  <div class="dr-kpi-line">
                    <span
                      class="dr-kpi-actual"
                      :class="amountClass(row.monthToDateNetDeposit)"
                    >
                      {{ formatSignedCurrency(row.monthToDateNetDeposit) }}
                    </span>
                    <span class="dr-kpi-slash">/</span>
                    <div v-if="canEditKpi" class="dr-kpi-cell">
                      <input
                        v-model="kpiDrafts[row.salesId]"
                        type="number"
                        min="0"
                        step="0.01"
                        class="dr-kpi-input"
                        :class="{ saving: savingIds.includes(row.salesId) }"
                        :disabled="savingIds.includes(row.salesId)"
                        :placeholder="
                          t('dailyReport_kpiPlaceholder', 'Set target')
                        "
                        @keyup.enter="saveKpi(row)"
                        @blur="saveKpi(row)"
                      />
                      <i
                        v-if="savingIds.includes(row.salesId)"
                        class="fas fa-spinner fa-spin dr-kpi-spinner"
                      ></i>
                      <i
                        v-else-if="savedIds.includes(row.salesId)"
                        class="fas fa-check dr-kpi-saved"
                      ></i>
                    </div>
                    <span v-else class="dr-kpi-target">{{
                      row.kpiTarget !== null
                        ? formatCurrency(row.kpiTarget)
                        : "—"
                    }}</span>
                  </div>
                  <div class="dr-progress">
                    <div
                      class="dr-progress-bar"
                      :class="rateClass(row.kpiAchievementRate)"
                      :style="{ width: barWidth(row.kpiAchievementRate) }"
                    ></div>
                  </div>
                </td>
                <td class="dr-num">
                  <span
                    v-if="hasRate(row.kpiAchievementRate)"
                    class="dr-rate-badge"
                    :class="rateClass(row.kpiAchievementRate)"
                  >
                    {{ row.kpiAchievementRate }}%
                  </span>
                  <span v-else class="dr-muted">—</span>
                </td>
              </tr>
              <tr v-if="!rows.length">
                <td colspan="8" class="dr-empty">
                  {{ t("dailyReport_empty", "No sales users to show") }}
                </td>
              </tr>
            </tbody>
            <tfoot v-if="rows.length">
              <tr>
                <td class="dr-col-sales">
                  {{ t("dailyReport_total", "Total") }}
                </td>
                <td class="dr-num dr-deposit">
                  {{ formatCurrency(summary.deposits || 0) }}
                </td>
                <td class="dr-num dr-withdrawal">
                  {{ formatCurrency(summary.withdrawals || 0) }}
                </td>
                <td class="dr-num" :class="amountClass(summary.netDeposit)">
                  <strong>{{
                    formatSignedCurrency(summary.netDeposit || 0)
                  }}</strong>
                </td>
                <td class="dr-num">
                  {{ formatNumber(summary.newLeads || 0) }}
                </td>
                <td class="dr-num">
                  {{ formatNumber(summary.newClients || 0) }}
                </td>
                <td class="dr-col-kpi">
                  <div class="dr-kpi-line">
                    <span
                      class="dr-kpi-actual"
                      :class="amountClass(summary.monthToDateNetDeposit)"
                    >
                      {{
                        formatSignedCurrency(summary.monthToDateNetDeposit || 0)
                      }}
                    </span>
                    <span class="dr-kpi-slash">/</span>
                    <span class="dr-kpi-target">{{
                      summary.kpiTarget
                        ? formatCurrency(summary.kpiTarget)
                        : "—"
                    }}</span>
                  </div>
                </td>
                <td class="dr-num">
                  <span
                    v-if="hasRate(summary.kpiAchievementRate)"
                    class="dr-rate-badge"
                    :class="rateClass(summary.kpiAchievementRate)"
                  >
                    {{ summary.kpiAchievementRate }}%
                  </span>
                  <span v-else class="dr-muted">—</span>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { ElConfigProvider, ElDatePicker } from "element-plus";
import zhCn from "element-plus/es/locale/lang/zh-cn";
import en from "element-plus/es/locale/lang/en";
import "element-plus/es/components/date-picker/style/css";
import "element-plus/es/components/config-provider/style/css";
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import { useAuthStore } from "@/stores/auth";
import { useAdminI18n } from "@/composables/useAdminI18n";
import dailyReportApi from "../services/dailyReportApi";
import { formatCurrency, formatNumber } from "../utils/helpers";

const { t, tParams, languageStore } = useAdminI18n();
const authStore = useAuthStore();

const elementPlusLocale = computed(() =>
  languageStore.currentLanguage === "zh" ? zhCn : en,
);
const dateLocale = computed(() =>
  languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US",
);

const hasReadonlyPermission = computed(() =>
  authStore.hasPermission("page_dailyreport_readonly"),
);

// Report days are split by the browser's timezone; backend falls back to UTC+10
const tzOffsetMinutes = -new Date().getTimezoneOffset();

const loading = ref(false);
const error = ref(null);
const rows = ref([]);
const summary = ref({});
const timezoneLabel = ref("");
const search = ref("");

// Month drives the KPI, day drives the transaction columns
const selectedMonth = ref("");
const selectedDay = ref(1);

// The backend decides both, so a sales user cannot unlock editing from the client
const canEditKpi = ref(false);
const canViewAllSales = ref(false);

// Inline KPI editing: draft values and per-sales saving state
const kpiDrafts = ref({});
const savingIds = ref([]);
const savedIds = ref([]);

const today = new Date();
const todayMonth = monthString(today);
const todayDay = today.getDate();

function monthString(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}`;
}

const selectedDate = computed(
  () => `${selectedMonth.value}-${String(selectedDay.value).padStart(2, "0")}`,
);

const daysInMonth = computed(() => {
  if (!selectedMonth.value) return [];
  const [year, month] = selectedMonth.value.split("-").map(Number);
  const total = new Date(year, month, 0).getDate();
  return Array.from({ length: total }, (unused, index) => index + 1);
});

const isTodaySelected = computed(
  () => selectedMonth.value === todayMonth && selectedDay.value === todayDay,
);

const dayLabel = computed(() => {
  if (!selectedMonth.value) return "—";
  return new Date(`${selectedDate.value}T00:00:00`).toLocaleDateString(
    dateLocale.value,
    {
      day: "numeric",
      month: "short",
      year: "numeric",
    },
  );
});

const monthLabel = computed(() => {
  if (!selectedMonth.value) return "—";
  return new Date(`${selectedMonth.value}-01T00:00:00`).toLocaleDateString(
    dateLocale.value,
    {
      month: "short",
      year: "numeric",
    },
  );
});

function weekdayLabel(day) {
  const date = new Date(
    `${selectedMonth.value}-${String(day).padStart(2, "0")}T00:00:00`,
  );
  return date.toLocaleDateString(dateLocale.value, { weekday: "narrow" });
}

// A day that has not happened yet has nothing to show
function isFutureDay(day) {
  if (selectedMonth.value > todayMonth) return true;
  return selectedMonth.value === todayMonth && day > todayDay;
}

function disableFutureMonth(date) {
  return monthString(date) > todayMonth;
}

function onMonthChange() {
  // Current month opens on today; a finished month opens on its last day, so the
  // month-to-date column covers the whole month straight away
  selectedDay.value =
    selectedMonth.value === todayMonth
      ? todayDay
      : daysInMonth.value[daysInMonth.value.length - 1] || 1;
  loadReport();
}

function selectDay(day) {
  selectedDay.value = day;
  loadReport();
}

function jumpToToday() {
  selectedMonth.value = todayMonth;
  selectedDay.value = todayDay;
  loadReport();
}

function formatSignedCurrency(value) {
  const amount = Number(value || 0);
  const sign = amount < 0 ? "-" : "";
  return `${sign}${formatCurrency(Math.abs(amount))}`;
}

function amountClass(value) {
  const amount = Number(value || 0);
  if (amount > 0) return "dr-positive";
  if (amount < 0) return "dr-negative";
  return "";
}

function hasRate(rate) {
  return rate !== null && rate !== undefined;
}

function rateClass(rate) {
  if (!hasRate(rate)) return "dr-rate-none";
  const value = Number(rate || 0);
  if (value >= 100) return "dr-rate-good";
  if (value >= 70) return "dr-rate-warn";
  return "dr-rate-bad";
}

function rateTextClass(rate) {
  return `dr-rate-text ${rateClass(rate)}`;
}

// The bar stops at 100% so an overachiever does not overflow the cell
function barWidth(rate) {
  if (!hasRate(rate)) return "0%";
  return `${Math.max(0, Math.min(100, Number(rate)))}%`;
}

const loadReport = async () => {
  if (!hasReadonlyPermission.value) return;

  loading.value = true;
  error.value = null;

  try {
    const response = await dailyReportApi.getSummary({
      date: selectedDate.value,
      tzOffset: tzOffsetMinutes,
      search: search.value || undefined,
    });

    if (!response.success) {
      throw new Error(
        response.message || t("common_unknownError", "Unknown error"),
      );
    }

    const data = response.data || {};
    rows.value = data.rows || [];
    summary.value = data.summary || {};
    timezoneLabel.value = data.timezone?.label || "";
    canEditKpi.value = Boolean(data.permissions?.canEditKpi);
    canViewAllSales.value = Boolean(data.permissions?.canViewAllSales);
    if (data.date) {
      selectedMonth.value = data.date.slice(0, 7);
      selectedDay.value = Number(data.date.slice(8, 10));
    }

    // Rebuild drafts so no input from the previous load leaks through
    const drafts = {};
    rows.value.forEach((row) => {
      drafts[row.salesId] =
        row.kpiTarget === null || row.kpiTarget === undefined
          ? ""
          : String(row.kpiTarget);
    });
    kpiDrafts.value = drafts;
    savedIds.value = [];
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      err.message ||
      t("common_unknownError", "Unknown error");
    rows.value = [];
    summary.value = {};
  } finally {
    loading.value = false;
  }
};

const saveKpi = async (row) => {
  if (!canEditKpi.value || savingIds.value.includes(row.salesId)) return;

  const raw = (kpiDrafts.value[row.salesId] ?? "").toString().trim();
  const previous =
    row.kpiTarget === null || row.kpiTarget === undefined
      ? ""
      : String(row.kpiTarget);

  // An empty box means "leave it alone" — restore the stored value instead of clearing the KPI
  if (raw === "") {
    kpiDrafts.value = { ...kpiDrafts.value, [row.salesId]: previous };
    return;
  }

  const target = Number(raw);
  if (!Number.isFinite(target) || target < 0) {
    error.value = t(
      "dailyReport_kpiInvalid",
      "KPI target must be a number greater than or equal to 0",
    );
    kpiDrafts.value = { ...kpiDrafts.value, [row.salesId]: previous };
    return;
  }

  if (previous !== "" && Number(previous) === target) return;

  savingIds.value = [...savingIds.value, row.salesId];
  error.value = null;

  try {
    const response = await dailyReportApi.saveKpi({
      salesId: row.salesId,
      month: selectedMonth.value,
      kpiTarget: target,
    });
    if (!response.success) {
      throw new Error(
        response.message || t("common_unknownError", "Unknown error"),
      );
    }

    const saved = Number(response.data?.kpiTarget ?? target);
    rows.value = rows.value.map((item) =>
      item.salesId === row.salesId
        ? {
            ...item,
            kpiTarget: saved,
            kpiAchievementRate:
              saved > 0
                ? Number(
                    ((item.monthToDateNetDeposit / saved) * 100).toFixed(2),
                  )
                : null,
          }
        : item,
    );
    kpiDrafts.value = { ...kpiDrafts.value, [row.salesId]: String(saved) };
    recalculateSummaryKpi();

    savedIds.value = [...savedIds.value, row.salesId];
    setTimeout(() => {
      savedIds.value = savedIds.value.filter((id) => id !== row.salesId);
    }, 1500);
  } catch (err) {
    error.value =
      err.response?.data?.message ||
      err.message ||
      t("common_unknownError", "Unknown error");
    kpiDrafts.value = { ...kpiDrafts.value, [row.salesId]: previous };
  } finally {
    savingIds.value = savingIds.value.filter((id) => id !== row.salesId);
  }
};

function recalculateSummaryKpi() {
  const totalTarget = rows.value.reduce(
    (sum, row) => sum + Number(row.kpiTarget || 0),
    0,
  );
  const mtdNet = Number(summary.value.monthToDateNetDeposit || 0);
  summary.value = {
    ...summary.value,
    kpiTarget: totalTarget,
    kpiAchievementRate:
      totalTarget > 0
        ? Number(((mtdNet / totalTarget) * 100).toFixed(2))
        : null,
  };
}

onMounted(() => {
  selectedMonth.value = todayMonth;
  selectedDay.value = todayDay;
  loadReport();
});
</script>

<style scoped>
.daily-report-page {
  padding: 24px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 24px;
}
.page-title h1 {
  margin: 0 0 6px;
  font-size: 26px;
  color: var(--color-ink-strong);
}
.page-title p {
  margin: 0;
  font-size: 14px;
  color: var(--color-muted);
}

.dr-no-permission {
  padding: 40px;
  text-align: center;
  color: var(--color-muted);
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border);
}

/* Filter card: month row on top, day strip below */
.dr-filter-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 16px 20px;
  margin-bottom: 20px;
}
.dr-filter-top {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}
.dr-filter-month {
  display: flex;
  align-items: center;
  gap: 10px;
}
.dr-filter-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--color-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.dr-month-picker {
  width: 150px;
}
.dr-btn-plain {
  padding: 7px 14px;
  border: 1px solid var(--color-border);
  background: var(--color-surface-soft);
  color: var(--color-text);
  border-radius: var(--radius-md);
  font-size: 13px;
  cursor: pointer;
}
.dr-btn-plain:hover:not(:disabled) {
  background: var(--color-surface-muted);
}
.dr-btn-plain:disabled {
  opacity: 0.5;
  cursor: default;
}
.dr-search {
  display: flex;
  align-items: center;
  gap: 8px;
}
.dr-search-input {
  padding: 7px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 13px;
  width: 220px;
  color: var(--color-ink);
}
.dr-search-input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.15);
}
.dr-btn-refresh {
  padding: 7px 14px;
  border: 1px solid var(--color-brand);
  background: var(--color-brand-solid);
  color: #fff;
  border-radius: var(--radius-md);
  font-size: 13px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.dr-btn-refresh:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.dr-tz-badge {
  margin-left: auto;
  font-size: 12px;
  color: var(--color-text);
  background: var(--color-surface-muted);
  border-radius: 999px;
  padding: 6px 12px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: help;
}

.dr-day-strip {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px dashed var(--color-border);
}
.dr-day-strip-scroll {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 4px;
}
.dr-day-btn {
  min-width: 42px;
  padding: 6px 4px;
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  border-radius: var(--radius-md);
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  line-height: 1;
}
.dr-day-btn:hover:not(.disabled) {
  border-color: #a3bffa;
  background: var(--color-surface-soft);
}
.dr-day-num {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}
.dr-day-weekday {
  font-size: 10px;
  color: var(--color-faint);
  text-transform: uppercase;
}
.dr-day-btn.active {
  background: var(--color-brand-solid);
  border-color: var(--color-brand);
}
.dr-day-btn.active .dr-day-num,
.dr-day-btn.active .dr-day-weekday {
  color: #fff;
}
.dr-day-btn.disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

/* Stat cards */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}
.stat-card {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 20px;
  border: 1px solid var(--color-border);
  border-top: 3px solid var(--color-border-strong);
}
.stat-card.deposit {
  border-top-color: var(--color-success);
}
.stat-card.withdrawal {
  border-top-color: var(--color-danger);
}
.stat-card.net {
  border-top-color: var(--color-brand);
}
.stat-card.leads {
  border-top-color: var(--color-warning);
}
.stat-card.clients {
  border-top-color: #319795;
}
.stat-card.kpi {
  border-top-color: #805ad5;
}
.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  gap: 8px;
}
.stat-title {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.stat-icon {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-surface-soft);
  color: var(--color-text);
}
.stat-value {
  font-size: 23px;
  font-weight: 700;
  color: var(--color-ink-strong);
  margin-bottom: 10px;
}
.stat-value-sub {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-faint);
}
.stat-footer {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--color-muted);
}

/* Shared progress bar */
.dr-progress {
  height: 6px;
  background: var(--color-surface-muted);
  border-radius: 999px;
  overflow: hidden;
  margin-bottom: 8px;
}
.dr-progress-bar {
  height: 100%;
  border-radius: 999px;
  transition: width 0.3s ease;
}
.dr-progress-bar.dr-rate-good {
  background: var(--color-success-solid);
}
.dr-progress-bar.dr-rate-warn {
  background: var(--color-warning-solid);
}
.dr-progress-bar.dr-rate-bad {
  background: var(--color-danger-solid);
}
.dr-progress-bar.dr-rate-none {
  background: transparent;
}
.dr-rate-text.dr-rate-good {
  color: var(--color-success);
}
.dr-rate-text.dr-rate-warn {
  color: var(--color-warning);
}
.dr-rate-text.dr-rate-bad {
  color: var(--color-danger);
}

.dr-error {
  background: var(--color-danger-soft);
  border: 1px solid var(--color-danger-border);
  color: var(--color-danger);
  padding: 12px 16px;
  border-radius: var(--radius-md);
  margin-bottom: 16px;
  font-size: 13px;
}

/* Table */
.dr-table-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}
.dr-table-header {
  padding: 18px 22px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.dr-table-header h3 {
  margin: 0;
  font-size: 17px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 8px;
}
.dr-table-header h3 i {
  color: var(--color-brand);
}
.dr-table-header-notes {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}
.dr-note {
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
.dr-note i {
  color: var(--color-success);
}
.dr-readonly-hint {
  font-size: 12px;
  color: var(--color-faint);
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.dr-loading {
  padding: 36px;
  text-align: center;
  color: var(--color-muted);
}
.dr-table-scroll {
  overflow-x: auto;
}
.dr-table {
  width: 100%;
  border-collapse: collapse;
}
.dr-table thead {
  background: var(--color-surface-soft);
}
.dr-table th {
  padding: 11px 16px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.4px;
  border-bottom: 2px solid var(--color-border);
  white-space: nowrap;
}
.dr-group-row th.dr-group {
  text-align: center;
  font-size: 12px;
  border-bottom: 1px solid var(--color-border);
}
.dr-group-day {
  background: var(--color-brand-soft);
  color: #43509b;
}
.dr-group-month {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}
.dr-table td {
  padding: 12px 16px;
  border-bottom: 1px solid var(--color-surface-muted);
  font-size: 14px;
  color: var(--color-text);
  white-space: nowrap;
}
.dr-table tbody tr:hover {
  background: var(--color-surface-soft);
}
.dr-table tfoot td {
  background: var(--color-surface-soft);
  font-weight: 700;
  color: var(--color-ink);
  border-top: 2px solid var(--color-border);
}
.dr-num {
  text-align: right;
}
.dr-table th.dr-num {
  text-align: right;
}
.dr-col-sales {
  text-align: left;
}
.dr-col-kpi {
  min-width: 240px;
}
.dr-sales-name {
  font-weight: 600;
  color: var(--color-ink);
}
.dr-sales-email {
  font-size: 12px;
  color: var(--color-faint);
}
.dr-deposit {
  color: var(--color-success);
}
.dr-withdrawal {
  color: var(--color-danger);
}
.dr-positive {
  color: var(--color-success);
}
.dr-negative {
  color: var(--color-danger);
}
.dr-muted {
  color: var(--color-border-strong);
}
.dr-empty {
  text-align: center;
  color: var(--color-faint);
  padding: 32px;
}

/* Net / KPI cell */
.dr-kpi-line {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
  margin-bottom: 6px;
}
.dr-kpi-actual {
  font-weight: 700;
}
.dr-kpi-slash {
  color: var(--color-border-strong);
}
.dr-kpi-target {
  color: var(--color-text);
}
.dr-kpi-cell {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.dr-kpi-input {
  width: 110px;
  padding: 5px 9px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 13px;
  text-align: right;
  color: var(--color-ink);
  background: var(--color-surface);
}
.dr-kpi-input:hover {
  border-color: var(--color-border-strong);
}
.dr-kpi-input:focus {
  outline: none;
  border-color: #805ad5;
  box-shadow: 0 0 0 3px rgba(128, 90, 213, 0.15);
}
.dr-kpi-input.saving {
  opacity: 0.6;
}
.dr-kpi-spinner {
  color: var(--color-brand);
  font-size: 12px;
}
.dr-kpi-saved {
  color: var(--color-success);
  font-size: 12px;
}

.dr-rate-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}
.dr-rate-badge.dr-rate-good {
  background: var(--color-success-soft);
  color: var(--color-success);
}
.dr-rate-badge.dr-rate-warn {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}
.dr-rate-badge.dr-rate-bad {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}
.dr-rate-badge.dr-rate-none {
  background: transparent;
  color: var(--color-border-strong);
}
</style>
