<template>
  <div class="history-page ui-page">
    <div class="history-container ui-data-region">
      <!-- Header with Search, Filters and Actions -->
      <div class="history-header ui-toolbar">
        <div class="header-left">
          <div class="history-search">
            <input
              id="historySearch"
              v-model="search.keywords"
              type="text"
              :placeholder="
                t('searchByTransactionId', 'Search by transaction ID...')
              "
              @keyup.enter="handleSearch"
            />
          </div>
          <div class="history-filter">
            <CustomSelect
              id="historyFilterType"
              v-model="search.type"
              :options="transactionTypeOptions"
              @change="handleSearch"
            />
          </div>
        </div>
        <div class="header-right">
          <div class="date-range">
            <label for="dateFrom">{{ t("dateRange", "Date Range") }}</label>
            <div class="date-inputs">
              <input id="dateFrom" v-model="search.periodFrom" type="date" />
              <span class="date-separator">{{ t("to", "To") }}</span>
              <input id="dateTo" v-model="search.periodTo" type="date" />
            </div>
            <button
              id="applyDateBtn"
              type="button"
              class="apply-btn"
              @click="handleSearch"
            >
              <i class="fas fa-check"></i>
              {{ t("apply", "Apply") }}
            </button>
          </div>
          <button
            id="exportBtn"
            type="button"
            class="export-btn"
            @click="exportCsv"
          >
            <i class="fas fa-file-export"></i>
            {{ t("export", "Export") }}
          </button>
        </div>
      </div>

      <!-- History Tabs -->
      <div class="history-tabs">
        <button
          type="button"
          class="history-tab"
          :class="activeTab === 'all' ? 'active' : ''"
          :aria-pressed="activeTab === 'all'"
          @click="filterByTab('all')"
        >
          {{ t("allTransactions", "All Transactions") }}
        </button>
        <button
          type="button"
          class="history-tab"
          :class="activeTab === 'deposit' ? 'active' : ''"
          :aria-pressed="activeTab === 'deposit'"
          @click="filterByTab('deposit')"
        >
          {{ t("deposits", "Deposits") }}
        </button>
        <button
          type="button"
          class="history-tab"
          :class="activeTab === 'withdrawal' ? 'active' : ''"
          :aria-pressed="activeTab === 'withdrawal'"
          @click="filterByTab('withdrawal')"
        >
          {{ t("withdrawals", "Withdrawals") }}
        </button>
        <button
          type="button"
          class="history-tab"
          :class="activeTab === 'internal_transfer' ? 'active' : ''"
          :aria-pressed="activeTab === 'internal_transfer'"
          @click="filterByTab('internal_transfer')"
        >
          {{ t("internalTransfers", "Internal Transfers") }}
        </button>
        <button
          type="button"
          class="history-tab"
          :class="activeTab === 'commission' ? 'active' : ''"
          :aria-pressed="activeTab === 'commission'"
          @click="filterByTab('commission')"
        >
          {{ t("commission", "Commission") }}
        </button>
        <button
          type="button"
          class="history-tab"
          :class="activeTab === 'credit' ? 'active' : ''"
          :aria-pressed="activeTab === 'credit'"
          @click="filterByTab('credit')"
        >
          {{ t("credit", "Credit") }}
        </button>
      </div>

      <!-- History List -->
      <div
        v-if="loadingHistory && transactionHistory.length === 0"
        class="loading-state"
      >
        <i class="fas fa-spinner fa-spin"></i>
        <p>{{ t("loadingHistory", "Loading history...") }}</p>
      </div>

      <div v-else-if="filteredHistory.length === 0" class="empty-state">
        <i class="fas fa-inbox"></i>
        <p>{{ t("noTransactionsFound", "No transactions found") }}</p>
      </div>

      <div v-else class="history-list">
        <div
          v-for="tx in filteredHistory"
          :key="`${tx.type}-${tx.id}`"
          class="history-item"
        >
          <div class="history-item-row">
            <div class="history-item-main">
              <div class="history-icon" :class="getHistoryIconClass(tx)">
                <i :class="getHistoryIcon(tx)"></i>
              </div>
              <div class="history-info">
                <div class="history-topline">
                  <div class="history-title-wrap">
                    <div class="history-title-line">
                      <span class="history-title">{{
                        getHistoryTitle(tx)
                      }}</span>
                      <!-- gateway chip：紧贴 Deposit/Withdrawal 标题右侧，一眼看到走的哪个支付方式 -->
                      <span
                        v-if="
                          (tx.type === 'deposit' || tx.type === 'withdrawal') &&
                          tx.gatewayName
                        "
                        class="history-gateway-inline"
                      >
                        <i class="fas fa-wallet"></i>
                        <span>{{ tx.gatewayName }}</span>
                      </span>
                    </div>
                    <div class="history-date">
                      {{ formatDateTime(tx.requestedAt) }}
                    </div>
                  </div>
                </div>

                <div class="history-meta">
                  <div class="history-transaction-id">
                    <span class="meta-label">{{
                      t("transactionId", "Transaction ID")
                    }}</span>
                    <strong>{{ tx.transactionId }}</strong>
                  </div>
                  <div
                    v-if="tx.type === 'withdrawal'"
                    class="history-transfer-info"
                  >
                    <template v-if="tx.accountChips?.length">
                      <span class="history-direction-chip">
                        <i class="fas fa-arrow-up"></i>
                        <span>{{ t("from", "From") }}</span>
                      </span>
                      <span
                        v-for="(chip, index) in tx.accountChips"
                        :key="`${tx.type}-${tx.id}-withdrawal-chip-${index}`"
                        :class="['history-account-chip', chip.tone]"
                      >
                        <i :class="chip.icon"></i>
                        <span>{{ chip.label }}</span>
                      </span>
                    </template>
                    <template v-else>
                      <i class="fas fa-arrow-up"></i>
                      {{
                        tx.displayText ||
                        tx.fromAccount ||
                        t("transWalletBalance", "Wallet")
                      }}
                    </template>
                  </div>
                  <div
                    v-if="tx.type === 'deposit'"
                    class="history-transfer-info"
                  >
                    <template v-if="tx.accountChips?.length">
                      <span class="history-direction-chip">
                        <i class="fas fa-arrow-down"></i>
                        <span>{{ t("to", "To") }}</span>
                      </span>
                      <span
                        v-for="(chip, index) in tx.accountChips"
                        :key="`${tx.type}-${tx.id}-deposit-chip-${index}`"
                        :class="['history-account-chip', chip.tone]"
                      >
                        <i :class="chip.icon"></i>
                        <span>{{ chip.label }}</span>
                      </span>
                    </template>
                    <template v-else>
                      <i class="fas fa-arrow-down"></i>
                      {{
                        tx.displayText ||
                        tx.toAccount ||
                        t("transWalletBalance", "Wallet")
                      }}
                    </template>
                  </div>
                  <div
                    v-if="tx.type === 'internal_transfer'"
                    class="history-transfer-info"
                  >
                    <template v-if="tx.accountChips?.length">
                      <span class="history-direction-chip">
                        <i class="fas fa-exchange-alt"></i>
                      </span>
                      <span
                        v-for="(chip, index) in tx.accountChips"
                        :key="`${tx.type}-${tx.id}-transfer-chip-${index}`"
                        :class="
                          chip.type === 'arrow'
                            ? 'history-account-arrow'
                            : ['history-account-chip', chip.tone]
                        "
                      >
                        <template v-if="chip.type === 'arrow'">
                          <i class="fas fa-arrow-right"></i>
                        </template>
                        <template v-else>
                          <i :class="chip.icon"></i>
                          <span>{{ chip.label }}</span>
                        </template>
                      </span>
                    </template>
                    <template v-else>
                      <i class="fas fa-exchange-alt"></i>
                      {{
                        tx.displayText || `${tx.fromAccount} → ${tx.toAccount}`
                      }}
                    </template>
                  </div>
                  <div
                    v-if="tx.type === 'commission'"
                    class="history-transfer-info"
                  >
                    <i class="fas fa-hand-holding-usd"></i>
                    {{
                      t("commissionCredited", "Commission credited to Wallet")
                    }}
                  </div>
                  <div
                    v-if="tx.type === 'credit'"
                    class="history-transfer-info"
                  >
                    <template v-if="tx.accountChips?.length">
                      <span class="history-direction-chip">
                        <i
                          :class="
                            (parseFloat(tx.amount) || 0) >= 0
                              ? 'fas fa-arrow-down'
                              : 'fas fa-arrow-up'
                          "
                        ></i>
                        <span>{{
                          (parseFloat(tx.amount) || 0) >= 0
                            ? t("to", "To")
                            : t("from", "From")
                        }}</span>
                      </span>
                      <span
                        v-for="(chip, index) in tx.accountChips"
                        :key="`${tx.type}-${tx.id}-credit-chip-${index}`"
                        :class="['history-account-chip', chip.tone]"
                      >
                        <i :class="chip.icon"></i>
                        <span>{{ chip.label }}</span>
                      </span>
                    </template>
                    <template v-else>
                      <i class="fas fa-gift"></i>
                      {{
                        tx.displayText ||
                        tx.toAccount ||
                        t("transTradingAccount", "Trading Account")
                      }}
                    </template>
                  </div>
                </div>
              </div>
            </div>
            <div class="history-side">
              <div class="history-side-top">
                <span :class="['history-status', tx.status]">{{
                  getStatusLabel(tx.status)
                }}</span>
                <div class="history-amount-group">
                  <div class="history-amount-label">
                    {{ t("amount", "Amount") }}
                  </div>
                  <div
                    class="history-amount"
                    :class="getHistoryAmountClass(tx)"
                  >
                    {{ getHistoryAmountPrefix(tx)
                    }}{{ formatCurrency(tx.amount) }}
                  </div>
                </div>
              </div>
              <div class="history-action-row">
                <button
                  v-if="canCancelTransaction(tx)"
                  type="button"
                  class="history-action-btn history-action-btn--danger"
                  @click="openCancelModal(tx)"
                >
                  <i class="fas fa-ban"></i>
                  {{ t("cancel", "Cancel") }}
                </button>
                <button
                  type="button"
                  class="history-action-btn history-action-btn--ghost"
                  @click="toggleHistoryDetail(tx)"
                >
                  <i
                    :class="
                      isHistoryExpanded(tx)
                        ? 'fas fa-chevron-up'
                        : 'fas fa-chevron-down'
                    "
                  ></i>
                  {{
                    isHistoryExpanded(tx)
                      ? t("hideDetails", "Hide details")
                      : t("viewDetails", "View details")
                  }}
                </button>
              </div>
            </div>
          </div>

          <!-- 展开的详情面板：deposit/withdraw 显示 currency/fee/net/时间/拒绝原因；
               internal_transfer 显示扣减账户和增加账户的具体金额。 -->
          <div v-if="isHistoryExpanded(tx)" class="history-detail-panel">
            <!-- internal_transfer：左右两列高亮显示 from(扣) / to(增) -->
            <template v-if="tx.type === 'internal_transfer'">
              <div class="history-detail-flow">
                <div class="history-detail-flow__side">
                  <div class="history-detail-flow__label">
                    <i class="fas fa-arrow-up"></i>
                    {{ t("transDeductedFrom", "Deducted from") }}
                  </div>
                  <div class="history-detail-flow__account">
                    {{ tx.fromAccount || t("transWalletBalance", "Wallet") }}
                  </div>
                  <div class="history-detail-flow__amount negative">
                    −{{ formatCurrency(tx.fromPlatformAmount ?? tx.amount) }}
                  </div>
                </div>
                <div class="history-detail-flow__arrow">
                  <i class="fas fa-arrow-right"></i>
                </div>
                <div class="history-detail-flow__side">
                  <div class="history-detail-flow__label">
                    <i class="fas fa-arrow-down"></i>
                    {{ t("transCreditedTo", "Credited to") }}
                  </div>
                  <div class="history-detail-flow__account">
                    {{
                      tx.toAccount ||
                      t("transTradingAccount", "Trading Account")
                    }}
                  </div>
                  <div class="history-detail-flow__amount positive">
                    +{{ formatCurrency(tx.toPlatformAmount ?? tx.amount) }}
                  </div>
                </div>
              </div>
              <div class="history-detail-meta">
                <div class="history-detail-field">
                  <span class="history-detail-label">{{
                    t("transRequestedAt", "Requested At")
                  }}</span>
                  <span class="history-detail-value">{{
                    formatDateTime(tx.requestedAt)
                  }}</span>
                </div>
                <div v-if="tx.completedAt" class="history-detail-field">
                  <span class="history-detail-label">{{
                    t("transCompletedAt", "Completed At")
                  }}</span>
                  <span class="history-detail-value">{{
                    formatDateTime(tx.completedAt)
                  }}</span>
                </div>
              </div>
            </template>
            <!-- deposit / withdrawal / commission：常规 key-value 网格 -->
            <template v-else>
              <div class="history-detail-meta">
                <div
                  v-for="field in buildHistoryDetailFields(tx)"
                  :key="field.label"
                  class="history-detail-field"
                  :class="{
                    'is-emphasized': field.emphasized,
                    'is-danger': field.danger,
                    'is-full': field.full,
                  }"
                >
                  <span class="history-detail-label">{{ field.label }}</span>
                  <span class="history-detail-value">{{ field.value }}</span>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>

      <Teleport to="body">
        <div
          v-if="cancelModal.visible"
          class="cancel-modal-overlay"
          @click="closeCancelModal"
        >
          <div class="cancel-modal" @click.stop>
            <div class="cancel-modal-header">
              <h3>{{ getCancelModalTitle() }}</h3>
              <button
                type="button"
                class="cancel-modal-close"
                :disabled="cancelModal.submitting"
                @click="closeCancelModal"
              >
                ×
              </button>
            </div>

            <div class="cancel-modal-body">
              <p class="cancel-modal-text">
                {{ getCancelModalPrompt() }}
              </p>

              <textarea
                v-model="cancelModal.reason"
                class="cancel-modal-textarea"
                :placeholder="
                  t('cancelReasonPlaceholder', 'Enter cancellation reason')
                "
                rows="4"
                maxlength="500"
              ></textarea>

              <div class="cancel-modal-meta">
                <span v-if="cancelModal.error" class="cancel-modal-error">{{
                  cancelModal.error
                }}</span>
                <span class="cancel-modal-count"
                  >{{ cancelModal.reason.length }}/500</span
                >
              </div>
            </div>

            <div class="cancel-modal-footer">
              <button
                type="button"
                class="cancel-modal-btn secondary"
                :disabled="cancelModal.submitting"
                @click="closeCancelModal"
              >
                {{ t("close", "Close") }}
              </button>
              <button
                type="button"
                class="cancel-modal-btn danger"
                :disabled="cancelModal.submitting"
                @click="submitCancelTransaction"
              >
                <i
                  v-if="cancelModal.submitting"
                  class="fas fa-spinner fa-spin"
                ></i>
                {{
                  cancelModal.submitting
                    ? t("processing", "Processing")
                    : t("confirmCancel", "Confirm Cancel")
                }}
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- Loading more indicator -->
      <div
        v-if="loadingHistory && transactionHistory.length > 0"
        class="loading-more"
      >
        <i class="fas fa-spinner fa-spin"></i>
        <span>{{ t("loadingMore", "Loading more...") }}</span>
      </div>
      <div v-if="!hasMore && transactionHistory.length > 0" class="no-more">
        <p>{{ t("noMoreTransactions", "No more transactions") }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from "vue";
import CustomSelect from "@/components/common/CustomSelect.vue";
import { useLanguageStore } from "@/stores/language";
import clientTransactionService from "../services/clientTransactionApi";

const languageStore = useLanguageStore();
const t = (key, fallback = "") => languageStore.t(key, fallback);
import { formatCurrency } from "../utils/helpers";
import { watch } from "vue";

const loadingHistory = ref(false);
const transactionHistory = ref([]);
const currentPage = ref(1);
const perPage = ref(20);
const total = ref(0);
const totalPages = ref(0);
const hasMore = ref(true);
const activeTab = ref("all");
let searchTimer = null;
const cancelModal = ref({
  visible: false,
  transaction: null,
  reason: "",
  error: "",
  submitting: false,
});

// Search and filter state
const search = ref({
  keywords: "",
  type: "",
  periodFrom: "",
  periodTo: "",
});

// Filtered history based on active tab
const filteredHistory = computed(() => {
  if (activeTab.value === "all") {
    return transactionHistory.value;
  }
  return transactionHistory.value.filter((tx) => {
    if (activeTab.value === "deposit") return tx.type === "deposit";
    if (activeTab.value === "withdrawal") return tx.type === "withdrawal";
    if (activeTab.value === "internal_transfer")
      return tx.type === "internal_transfer";
    if (activeTab.value === "commission") return tx.type === "commission";
    if (activeTab.value === "credit") return tx.type === "credit";
    return true;
  });
});

const transactionTypeOptions = computed(() => [
  { value: "", label: t("allTypes", "All Types") },
  { value: "deposit", label: t("deposit", "Deposit") },
  { value: "withdrawal", label: t("withdrawal", "Withdrawal") },
  {
    value: "internal_transfer",
    label: t("internalTransfer", "Internal Transfer"),
  },
  { value: "commission", label: t("commission", "Commission") },
  { value: "credit", label: t("credit", "Credit") },
]);

const normalizePlatformKey = (value, fallback = "wallet") => {
  const normalized = String(value || "")
    .trim()
    .toLowerCase();

  if (!normalized) return fallback;
  if (normalized === "wallet" || normalized === "available_balance")
    return "wallet";
  if (normalized.includes("mt5")) return "mt5";
  if (normalized.includes("mt4")) return "mt4";
  if (normalized.includes("financepro") || normalized === "fp")
    return "financepro";

  return normalized;
};

// badge 只用 shortCode，没有就空着（wallet 不是交易平台，单独显示）
const getPlatformBadgeMeta = (platformKey, shortCode = "") => {
  const normalized = normalizePlatformKey(platformKey);

  switch (normalized) {
    case "wallet":
      return {
        label: t("transWalletBalance", "Wallet"),
        tone: "wallet",
        icon: "fas fa-dollar-sign",
      };
    case "mt5":
      return { label: shortCode || "", tone: "mt5", icon: "fas fa-chart-line" };
    case "mt4":
      return { label: shortCode || "", tone: "mt4", icon: "fas fa-chart-area" };
    case "financepro":
      return {
        label: shortCode || "",
        tone: "financepro",
        icon: "fas fa-bolt",
      };
    default:
      return {
        label: shortCode || "",
        tone: "default",
        icon: "fas fa-layer-group",
      };
  }
};

const formatPlatformAccountLabel = (
  platformKey,
  accountLabel,
  shortCode = "",
) => {
  const platform = getPlatformBadgeMeta(platformKey, shortCode);
  const normalizedAccount = String(accountLabel || "").trim();

  if (!normalizedAccount) return platform.label;
  if (normalizePlatformKey(platformKey) === "wallet") return platform.label;

  return `${platform.label} ${normalizedAccount}`;
};

const createAccountChip = (platformKey, accountLabel, shortCode = "") => {
  const platform = getPlatformBadgeMeta(platformKey, shortCode);
  const normalizedPlatform = normalizePlatformKey(platformKey);
  const normalizedAccount = String(accountLabel || "").trim();

  return {
    type: "account",
    tone: platform.tone,
    icon: platform.icon,
    label:
      normalizedPlatform === "wallet" || !normalizedAccount
        ? platform.label
        : `${platform.label} ${normalizedAccount}`,
  };
};

const resolveDepositPlatformKey = (item) => {
  if (item.platformKey) return item.platformKey;
  if (!item.toTradingAccountId) return "wallet";
  return "default";
};

const resolveWithdrawalPlatformKey = (item) => {
  if (item.platformKey) return item.platformKey;
  if (!item.fromTradingAccountId) return "wallet";
  return "default";
};

const loadHistory = async (page = 1, append = false) => {
  if (loadingHistory.value) return;

  loadingHistory.value = true;
  try {
    const response = await clientTransactionService.getTransactionHistory(
      page,
      perPage.value,
      search.value.keywords || null,
      search.value.type || null,
      search.value.periodFrom || null,
      search.value.periodTo || null,
    );

    if (response.success) {
      const newItems = (response.data.items || []).map((item) => {
        if (item.type === "withdrawal") {
          const platformKey = resolveWithdrawalPlatformKey(item);
          const shortCode = item.platformShortCode || "";
          const fromAccount = item.fromTradingAccountId
            ? item.fromAccountNumber ||
              t("transTradingAccount", "Trading Account")
            : t("transWalletBalance", "Wallet");

          return {
            ...item,
            fromAccount,
            displayText: `${t("from", "From")} ${formatPlatformAccountLabel(platformKey, fromAccount, shortCode)}`,
            platformBadge: getPlatformBadgeMeta(platformKey, shortCode),
            accountChips: [
              createAccountChip(platformKey, fromAccount, shortCode),
            ],
          };
        }

        if (item.type === "deposit") {
          const platformKey = resolveDepositPlatformKey(item);
          const shortCode = item.platformShortCode || "";
          const toAccount = item.toTradingAccountId
            ? item.toAccountNumber ||
              t("transTradingAccount", "Trading Account")
            : t("transWalletBalance", "Wallet");

          return {
            ...item,
            toAccount,
            displayText: `${t("to", "To")} ${formatPlatformAccountLabel(platformKey, toAccount, shortCode)}`,
            platformBadge: getPlatformBadgeMeta(platformKey, shortCode),
            accountChips: [
              createAccountChip(platformKey, toAccount, shortCode),
            ],
          };
        }

        // credit：平台侧打进/撤回的赠金，归一化方式跟 deposit 类似（落到交易账户上）
        // amount 后端已带符号（IN 正/OUT 负），前端展示直接透传
        if (item.type === "credit") {
          const platformKey =
            item.toPlatformKey || item.platformKey || "wallet";
          const shortCode =
            item.toPlatformShortCode || item.platformShortCode || "";
          const toAccount =
            item.toAccountNumber || t("transTradingAccount", "Trading Account");
          return {
            ...item,
            toAccount,
            displayText: `${t("to", "To")} ${formatPlatformAccountLabel(platformKey, toAccount, shortCode)}`,
            platformBadge: getPlatformBadgeMeta(platformKey, shortCode),
            accountChips: [
              createAccountChip(platformKey, toAccount, shortCode),
            ],
          };
        }

        // 处理内部转账，只保留一条记录，清楚显示从哪个账户转到哪个账户
        if (item.type === "internal_transfer") {
          const fromPlatformKey =
            item.fromPlatformKey || item.fromType || "wallet";
          const toPlatformKey = item.toPlatformKey || "wallet";
          const fromShortCode = item.fromPlatformShortCode || "";
          const toShortCode = item.toPlatformShortCode || "";
          const fromAccount =
            item.fromType == "wallet" || item.fromType == "available_balance"
              ? t("transWalletBalance", "Wallet")
              : item.fromAccountNickname ||
                item.fromAccountNumber ||
                t("transTradingAccount", "Trading Account");
          const toAccount =
            item.toAccountNickname ||
            item.toAccountNumber ||
            t("transTradingAccount", "Trading Account");

          return {
            ...item,
            type: "internal_transfer",
            fromAccount: fromAccount,
            toAccount: toAccount,
            displayText: `${formatPlatformAccountLabel(fromPlatformKey, fromAccount, fromShortCode)} → ${formatPlatformAccountLabel(toPlatformKey, toAccount, toShortCode)}`,
            fromPlatformBadge: getPlatformBadgeMeta(
              fromPlatformKey,
              fromShortCode,
            ),
            toPlatformBadge: getPlatformBadgeMeta(toPlatformKey, toShortCode),
            accountChips: [
              createAccountChip(fromPlatformKey, fromAccount, fromShortCode),
              { type: "arrow" },
              createAccountChip(toPlatformKey, toAccount, toShortCode),
            ],
          };
        }

        return item;
      });

      if (append) {
        // 追加模式：将新数据追加到现有列表
        transactionHistory.value = [...transactionHistory.value, ...newItems];
      } else {
        // 替换模式：替换整个列表
        transactionHistory.value = newItems;
      }

      total.value = response.data.pagination?.total || 0;
      totalPages.value = response.data.pagination?.total_pages || 0;
      currentPage.value = response.data.pagination?.page || page;
      perPage.value = response.data.pagination?.per_page || 20;
      hasMore.value =
        newItems.length >= perPage.value &&
        currentPage.value < totalPages.value;
    }
  } catch (err) {
    console.error("Failed to load history:", err);
  } finally {
    loadingHistory.value = false;
  }
};

// 监听搜索关键词变化，添加防抖
watch(
  () => search.value.keywords,
  (newVal) => {
    if (searchTimer) {
      clearTimeout(searchTimer);
      searchTimer = null;
    }
    searchTimer = setTimeout(() => handleSearch(), 500);
  },
);

// 搜索处理
const handleSearch = () => {
  currentPage.value = 1;
  hasMore.value = true;
  loadHistory(1, false);
};

// Tab 筛选
const filterByTab = (tab) => {
  activeTab.value = tab;
  // Tab 筛选在前端进行，不需要重新加载数据
};

// system 网关（Admin Pay）的单子由后台主动创建，即便 pending 也不允许客户端取消
const canCancelTransaction = (tx) =>
  ["deposit", "withdrawal", "internal_transfer"].includes(tx.type) &&
  tx.status === "pending" &&
  tx.gatewayKey !== "system";

const expandedHistoryKey = ref(null);
const getHistoryKey = (tx) => `${tx.type}-${tx.id}`;
const isHistoryExpanded = (tx) =>
  expandedHistoryKey.value === getHistoryKey(tx);
const toggleHistoryDetail = (tx) => {
  const k = getHistoryKey(tx);
  expandedHistoryKey.value = expandedHistoryKey.value === k ? null : k;
};

// 详情面板字段：deposit / withdrawal / commission 共用 key-value 网格。
// 内部转账走单独的 from/to flow 模板，不走这里。
const buildHistoryDetailFields = (tx) => {
  const fields = [];
  const currency = tx.currencyCode || "";
  const push = (label, value, opts = {}) => {
    if (value === undefined || value === null || value === "") return;
    fields.push({ label, value, ...opts });
  };
  push(t("transCurrency", "Currency"), currency);
  if (
    tx.platformFee !== undefined &&
    tx.platformFee !== null &&
    Number(tx.platformFee) !== 0
  ) {
    // 不要 $ 前缀，统一用 "数字 + 单位" 形式（如 "5.00 USDT"）
    push(
      t("transPlatformFee", "Platform Fee"),
      `${formatCurrency(tx.platformFee, "")} ${currency}`.trim(),
    );
  }
  // platformAmount 的语义按方向区分：
  //   入金 → 客户「预计要支付」的金额（含 fee 后实际打出去的钱）
  //   出金 → 客户「预计到账」的金额（去掉 fee 后能收到的钱）
  if (
    tx.platformAmount !== undefined &&
    tx.platformAmount !== null &&
    tx.platformAmount !== ""
  ) {
    const netLabel =
      tx.type === "withdrawal"
        ? t("transEstimatedReceived", "Estimated Received")
        : t("transEstimatedPayment", "Estimated Payment");
    push(
      netLabel,
      `${formatCurrency(tx.platformAmount, "")} ${currency}`.trim(),
      { emphasized: true },
    );
  }
  push(t("transRequestedAt", "Requested At"), formatDateTime(tx.requestedAt));
  if (tx.completedAt)
    push(t("transCompletedAt", "Completed At"), formatDateTime(tx.completedAt));
  // 拒绝原因：只在拒绝/失败/取消时显示。admin 拒绝时可能没填备注，给个 fallback 文字
  // 这样用户至少知道"被拒了但无原因"，而不是看到一个 rejected 状态却没任何说明
  const rejected = ["rejected", "failed", "cancelled", "canceled"].includes(
    String(tx.status || "").toLowerCase(),
  );
  if (rejected) {
    const reason =
      tx.rejectionReason ||
      tx.reason ||
      tx.adminNotes ||
      t("transNoReasonProvided", "No reason provided");
    push(t("transRejectionReason", "Rejection Reason"), reason, {
      danger: true,
      full: true,
    });
  }
  return fields;
};

const isWithdrawalCancel = computed(
  () => cancelModal.value.transaction?.type === "withdrawal",
);
const isInternalTransferCancel = computed(
  () => cancelModal.value.transaction?.type === "internal_transfer",
);

const getCancelModalTitle = () =>
  isInternalTransferCancel.value
    ? t("cancelInternalTransfer", "Cancel Internal Transfer")
    : isWithdrawalCancel.value
      ? t("cancelWithdrawal", "Cancel Withdrawal")
      : t("cancelDeposit", "Cancel Deposit");

const getCancelModalPrompt = () =>
  isInternalTransferCancel.value
    ? t(
        "cancelInternalTransferReasonPrompt",
        "Please provide a reason for cancelling this internal transfer.",
      )
    : isWithdrawalCancel.value
      ? t(
          "cancelWithdrawalReasonPrompt",
          "Please provide a reason for cancelling this withdrawal.",
        )
      : t(
          "cancelDepositReasonPrompt",
          "Please provide a reason for cancelling this deposit.",
        );

const openCancelModal = (tx) => {
  cancelModal.value = {
    visible: true,
    transaction: tx,
    reason: "",
    error: "",
    submitting: false,
  };
};

const closeCancelModal = (force = false) => {
  if (cancelModal.value.submitting && !force) return;

  cancelModal.value = {
    visible: false,
    transaction: null,
    reason: "",
    error: "",
    submitting: false,
  };
};

const getErrorMessage = (err, fallback) => {
  if (err?.errors) {
    const firstFieldErrors = Object.values(err.errors).find(
      (value) => Array.isArray(value) && value.length > 0,
    );
    if (firstFieldErrors) return firstFieldErrors[0];
  }

  return err?.message || fallback;
};

const submitCancelTransaction = async () => {
  const transaction = cancelModal.value.transaction;
  const reason = cancelModal.value.reason.trim();

  if (!transaction?.id) return;

  if (!reason) {
    cancelModal.value.error = t(
      "cancelReasonRequired",
      "Cancellation reason is required.",
    );
    return;
  }

  cancelModal.value.submitting = true;
  cancelModal.value.error = "";

  try {
    const response =
      transaction.type === "withdrawal"
        ? await clientTransactionService.cancelWithdrawal(
            transaction.id,
            reason,
          )
        : transaction.type === "internal_transfer"
          ? await clientTransactionService.cancelInternalTransfer(
              transaction.id,
              reason,
            )
          : await clientTransactionService.cancelDeposit(
              transaction.id,
              reason,
            );
    const updatedTransaction = response.data || {};

    transactionHistory.value = transactionHistory.value.map((item) => {
      if (item.type === transaction.type && item.id === transaction.id) {
        return {
          ...item,
          ...updatedTransaction,
          type: transaction.type,
        };
      }

      return item;
    });

    closeCancelModal(true);
  } catch (err) {
    cancelModal.value.error = getErrorMessage(
      err,
      transaction.type === "internal_transfer"
        ? t(
            "cancelInternalTransferFailed",
            "Failed to cancel internal transfer. Please try again.",
          )
        : transaction.type === "withdrawal"
          ? t(
              "cancelWithdrawalFailed",
              "Failed to cancel withdrawal. Please try again.",
            )
          : t(
              "cancelDepositFailed",
              "Failed to cancel deposit. Please try again.",
            ),
    );
  } finally {
    cancelModal.value.submitting = false;
  }
};

// 滚动到底部加载更多
const handleScroll = () => {
  const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
  const windowHeight = window.innerHeight;
  const documentHeight = document.documentElement.scrollHeight;

  // 当滚动到距离底部100px时加载更多
  if (scrollTop + windowHeight >= documentHeight - 100) {
    if (hasMore.value && !loadingHistory.value) {
      loadHistory(currentPage.value + 1, true);
    }
  }
};

// 导出 CSV
const exportCsv = () => {
  const headers = [
    "Transaction ID",
    "Type",
    "Amount",
    "Status",
    "Date",
    "From Account",
    "To Account",
  ];

  const rows = filteredHistory.value.map((tx) => [
    tx.transactionId || "",
    getHistoryTitle(tx),
    formatCurrency(tx.amount),
    getStatusLabel(tx.status),
    formatDateTime(tx.requestedAt),
    tx.fromAccount || "",
    tx.toAccount || "",
  ]);

  const csvContent = [
    headers.join(","),
    ...rows.map((row) => row.map((cell) => `"${cell}"`).join(",")),
  ].join("\n");

  const blob = new Blob(["\uFEFF" + csvContent], {
    type: "text/csv;charset=utf-8;",
  });
  const link = document.createElement("a");
  const url = URL.createObjectURL(blob);
  link.setAttribute("href", url);
  link.setAttribute(
    "download",
    `transaction_history_${new Date().toISOString().split("T")[0]}.csv`,
  );
  link.style.visibility = "hidden";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

const formatDateTime = (datetime) => {
  if (!datetime) return "-";
  const date = new Date(datetime);
  return date.toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const getStatusLabel = (status) => {
  const labels = {
    pending: t("pending", "Pending"),
    processing: t("processing", "Processing"),
    completed: t("completed", "Completed"),
    rejected: t("rejected", "Rejected"),
    failed: t("failed", "Failed"),
    cancelled: t("cancelled", "Cancelled"),
  };
  return labels[status] || status;
};

const getHistoryTitle = (tx) => {
  if (tx.type === "deposit") return t("deposit", "Deposit");
  if (tx.type === "withdrawal") return t("withdrawal", "Withdrawal");
  if (tx.type === "internal_transfer")
    return t("internalTransfer", "Internal Transfer");
  if (tx.type === "commission") return t("commission", "Commission");
  if (tx.type === "credit") return t("credit", "Credit");
  return "Transaction";
};

const getHistoryIcon = (tx) => {
  if (tx.type === "deposit") return "fas fa-arrow-down";
  if (tx.type === "withdrawal") return "fas fa-arrow-up";
  if (tx.type === "internal_transfer") return "fas fa-exchange-alt";
  if (tx.type === "commission") return "fas fa-hand-holding-usd";
  if (tx.type === "credit") return "fas fa-gift";
  return "fas fa-exchange-alt";
};

const getHistoryIconClass = (tx) => {
  if (tx.type === "deposit") return "deposit";
  if (tx.type === "withdrawal") return "withdrawal";
  if (tx.type === "internal_transfer") return "transfer";
  if (tx.type === "commission") return "commission";
  if (tx.type === "credit") return "credit";
  return "transfer";
};

const getHistoryAmountClass = (tx) => {
  if (["rejected", "failed", "cancelled"].includes(tx.status)) return "neutral";
  // credit 的 amount 已带符号（IN 正 / OUT 负），按金额本身决定颜色
  if (tx.type === "credit") {
    return (parseFloat(tx.amount) || 0) >= 0 ? "deposit" : "withdrawal";
  }
  if (
    tx.type === "deposit" ||
    tx.type === "internal_transfer" ||
    tx.type === "commission"
  )
    return "deposit";
  if (tx.type === "withdrawal") return "withdrawal";
  return "";
};

const getHistoryAmountPrefix = (tx) => {
  if (["rejected", "failed", "cancelled"].includes(tx.status)) return "";
  // credit 的 amount 已带符号，负数自己就带 '-'，正数补 '+'
  if (tx.type === "credit") {
    return (parseFloat(tx.amount) || 0) >= 0 ? "+" : "";
  }
  if (
    tx.type === "deposit" ||
    tx.type === "internal_transfer" ||
    tx.type === "commission"
  )
    return "+";
  if (tx.type === "withdrawal") return "-";
  return "";
};

onMounted(() => {
  loadHistory(1, false);
  // 添加滚动监听
  window.addEventListener("scroll", handleScroll);
});

onUnmounted(() => {
  // 移除滚动监听
  window.removeEventListener("scroll", handleScroll);
});
</script>

<style scoped>
.history-page {
  display: block;
}

.history-container {
  background: rgba(255, 255, 255, 0.94);
  border-radius: 24px;
  padding: 28px;
  border: 1px solid rgba(226, 232, 240, 0.9);
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.06);
}

/* Header */
.history-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 20px;
  margin-bottom: 25px;
  padding: 20px;
  background: linear-gradient(
    135deg,
    var(--color-surface-soft) 0%,
    var(--color-surface-muted) 100%
  );
  border-radius: var(--radius-lg);
  border: 2px solid var(--color-border);
  flex-wrap: wrap;
}

.header-left {
  display: flex;
  gap: 12px;
  flex: 1;
  min-width: 300px;
}

.header-right {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  flex-wrap: wrap;
}

.history-search {
  display: flex;
  flex: 1;
  min-width: 0;
  min-width: 250px;
}

.history-search input {
  width: 100%;
  padding: 12px 16px 12px 16px;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
}

.history-search input:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

.history-search input::placeholder {
  color: var(--color-faint);
}

.history-filter {
  min-width: 160px;
}

.history-filter select {
  width: 100%;
  padding: 12px 16px;
  background-color: white;
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  color: var(--color-ink);
  font-size: 14px;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%234a5568' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  padding-right: 36px;
  height: 44px;
  box-sizing: border-box;
}

.history-filter select:focus {
  border-color: var(--color-brand);
  outline: none;
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.1);
}

/* Date Range */
.date-range {
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 12px;
  background: white;
  padding: 0;
  border-radius: var(--radius-md);
  border: 2px solid var(--color-border);
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
  height: 44px;
}

.date-range:hover {
  border-color: var(--color-border-strong);
}

.date-range label {
  color: var(--color-text);
  font-size: 13px;
  font-weight: 600;
  white-space: nowrap;
  padding: 0 12px;
  margin: 0;
}

.date-inputs {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
}

.date-range input {
  padding: 10px 12px;
  background-color: white;
  border: none;
  border-radius: 0;
  color: var(--color-ink);
  font-size: 13px;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
  min-width: 140px;
  height: 100%;
}

.date-range input:focus {
  outline: none;
  background-color: var(--color-surface-soft);
}

.date-separator {
  color: var(--color-muted);
  font-size: 13px;
  font-weight: 500;
  padding: 0 4px;
}

.apply-btn {
  background: var(--color-brand);
  border: none;
  color: white;
  padding: 0 18px;
  border-radius: 0 6px 6px 0;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
  white-space: nowrap;
  font-size: 13px;
  font-weight: 600;
  height: 100%;
  box-shadow: none;
}

.apply-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.3);
}

.export-btn {
  background: white;
  border: 2px solid var(--color-brand);
  color: var(--color-brand);
  padding: 12px 20px;
  border-radius: var(--radius-md);
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease,
    box-shadow 0.3s ease,
    opacity 0.3s ease,
    transform 0.3s ease;
  white-space: nowrap;
  font-size: 14px;
  font-weight: 600;
  align-self: flex-start;
  height: 44px;
  box-sizing: border-box;
}

.export-btn:hover {
  background: var(--color-brand);
  color: white;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.3);
}

/* Tabs */
.history-tabs {
  display: flex;
  gap: 10px;
  margin-bottom: 24px;
  overflow-x: auto;
  padding-bottom: 4px;
}

.history-tab {
  appearance: none;
  padding: 10px 16px;
  cursor: pointer;
  transition:
    color 0.3s ease,
    background-color 0.3s ease,
    border-color 0.3s ease;
  font-size: 13px;
  font-weight: 700;
  color: #64748b;
  white-space: nowrap;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  background: #fff;
}

.history-tab:hover {
  color: #1d4ed8;
  border-color: #bfdbfe;
  background: #f8fbff;
}

.history-tab.active {
  color: #1d4ed8;
  border-color: #93c5fd;
  background: linear-gradient(135deg, #eff6ff 0%, var(--color-brand-soft) 100%);
  box-shadow: inset 0 0 0 1px rgba(147, 197, 253, 0.4);
}

.loading-state,
.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--color-muted);
}

.loading-state i,
.empty-state i {
  font-size: 48px;
  margin-bottom: 16px;
  color: var(--color-border-strong);
}

.history-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.history-item {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 22px;
  background: linear-gradient(
    180deg,
    rgba(255, 255, 255, 0.96) 0%,
    rgba(248, 250, 252, 0.96) 100%
  );
  border-radius: 20px;
  border: 1px solid var(--color-border);
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease,
    border-color 0.2s ease;
  box-shadow: 0 10px 28px rgba(15, 23, 42, 0.04);
}

/* 原来 .history-item 自己当 flex 横向布局；现在它改成纵向，把横向规则下放到 .history-item-row */
.history-item-row {
  display: flex;
  justify-content: space-between;
  align-items: stretch;
  gap: 18px;
}

/* 标题这一行：把 "Withdrawal" / "Deposit" 文字和右侧的 gateway chip 横向并排 */
.history-title-line {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

/* gateway 小 chip：紧贴在标题右侧 */
.history-gateway-inline {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  background: var(--color-brand-soft);
  color: var(--color-brand-strong);
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}
.history-gateway-inline i {
  font-size: 11px;
}

/* 详情面板：跟主行用 subtle 分隔，整体一张浅卡片 */
.history-detail-panel {
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  border-radius: 14px;
  padding: 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

/* internal_transfer 专用 flow 区：左 from(扣) → 右 to(增)，金额放大、红/绿区分 */
.history-detail-flow {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  gap: 16px;
  padding: 14px 16px;
  background: #ffffff;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}
.history-detail-flow__side {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}
.history-detail-flow__label {
  font-size: 11px;
  color: #64748b;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.history-detail-flow__account {
  font-size: 13px;
  color: #1e293b;
  font-weight: 600;
  word-break: break-all;
}
.history-detail-flow__amount {
  font-size: 18px;
  font-weight: 700;
}
.history-detail-flow__amount.positive {
  color: #047857;
}
.history-detail-flow__amount.negative {
  color: #b91c1c;
}
.history-detail-flow__arrow {
  color: #94a3b8;
  font-size: 18px;
}

/* meta 网格：两列 label-value */
.history-detail-meta {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px 24px;
}
.history-detail-field {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 10px;
  font-size: 13px;
  padding: 4px 0;
  min-width: 0;
}
.history-detail-field.is-full {
  grid-column: 1 / -1;
}
.history-detail-label {
  color: #64748b;
  font-weight: 500;
  flex-shrink: 0;
}
.history-detail-value {
  color: #1e293b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
  min-width: 0;
}
.history-detail-field.is-emphasized .history-detail-value {
  color: #0f172a;
  font-size: 15px;
}
.history-detail-field.is-danger .history-detail-value {
  color: #b91c1c;
  font-weight: 600;
}

@media (max-width: 640px) {
  .history-detail-meta {
    grid-template-columns: 1fr;
  }
  .history-detail-flow {
    grid-template-columns: 1fr;
    gap: 8px;
  }
  .history-detail-flow__arrow {
    transform: rotate(90deg);
  }
}

.history-item:hover {
  transform: translateY(-1px);
  border-color: #cbd5e1;
  box-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
}

.history-item-main {
  display: flex;
  gap: 16px;
  min-width: 0;
  flex: 1;
}

.history-icon {
  width: 56px;
  height: 56px;
  border-radius: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  flex-shrink: 0;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
}

.history-icon.deposit {
  background: #e6fffa;
  color: #38b2ac;
}

.history-icon.withdrawal {
  background: var(--color-danger-soft);
  color: var(--color-danger-border);
}

.history-icon.transfer-out {
  background: var(--color-danger-soft);
  color: var(--color-danger-border);
}

.history-icon.transfer-in {
  background: #e6fffa;
  color: #38b2ac;
}

.history-icon.transfer {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.history-icon.commission {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.history-icon.credit {
  background: #fefcbf;
  color: #b7791f;
}

.history-info {
  flex: 1;
  min-width: 0;
}

.history-topline {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.history-title-wrap {
  min-width: 0;
}

.history-title {
  font-size: 17px;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 6px;
}

.history-date {
  font-size: 13px;
  color: #64748b;
}

.history-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 14px;
}

.history-transaction-id,
.history-transfer-info {
  display: inline-flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 999px;
  background: var(--color-surface-soft);
  border: 1px solid var(--color-border);
  font-size: 12px;
  color: #475569;
}

.history-platform-pair {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 8px;
}

.history-platform-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 999px;
  border: 1px solid transparent;
  font-size: 12px;
  font-weight: 700;
}

.history-platform-pill.wallet {
  background: var(--color-surface-soft);
  border-color: var(--color-border);
  color: #475569;
}

.history-platform-pill.mt5 {
  background: #e0f2fe;
  border-color: var(--color-info-border);
  color: var(--color-info);
}

.history-platform-pill.mt4 {
  background: var(--color-brand-soft);
  border-color: #ddd6fe;
  color: #6d28d9;
}

.history-platform-pill.financepro {
  background: #ecfccb;
  border-color: #d9f99d;
  color: #4d7c0f;
}

.history-platform-pill.default {
  background: #f1f5f9;
  border-color: var(--color-border);
  color: #334155;
}

.meta-label {
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-size: 11px;
}

.history-transfer-info {
  max-width: 100%;
}

.history-transfer-info i {
  font-size: 10px;
}

.history-account-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 7px 12px;
  border-radius: 999px;
  border: 1px solid transparent;
  font-size: 12px;
  font-weight: 700;
  line-height: 1;
}

.history-account-chip.wallet {
  background: var(--color-surface-soft);
  border-color: var(--color-border);
  color: #475569;
}

.history-account-chip.mt5 {
  background: #e0f2fe;
  border-color: var(--color-info-border);
  color: var(--color-info);
}

.history-account-chip.mt4 {
  background: var(--color-brand-soft);
  border-color: #ddd6fe;
  color: #6d28d9;
}

.history-account-chip.financepro {
  background: #ecfccb;
  border-color: #d9f99d;
  color: #4d7c0f;
}

.history-account-chip.default {
  background: #f1f5f9;
  border-color: var(--color-border);
  color: #334155;
}

.history-account-arrow {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  color: #94a3b8;
}

.history-account-arrow i {
  font-size: 11px;
}

.history-direction-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 0 2px 0 0;
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
  line-height: 1;
}

.history-direction-chip i {
  font-size: 11px;
}

.history-amount-label {
  font-size: 11px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #94a3b8;
  font-weight: 700;
}

.history-amount {
  font-size: 24px;
  font-weight: 700;
  min-width: 140px;
  text-align: right;
  line-height: 1.1;
}

.history-amount.deposit {
  color: #38b2ac;
}

.history-amount.withdrawal {
  color: var(--color-danger-border);
}

.history-amount.neutral {
  color: #64748b;
}

.history-side {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  justify-content: center;
  gap: 12px;
  min-width: 160px;
}

.history-side-top {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 10px;
}

.history-amount-group {
  text-align: right;
}

.history-status {
  padding: 8px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  min-width: 108px;
  text-align: center;
}

.history-status.pending {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.history-status.processing {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.history-status.completed {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.history-status.rejected,
.history-status.failed {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.history-status.cancelled {
  background: #f1f5f9;
  color: #64748b;
  border: 1px solid var(--color-border);
}

/* Cancel + View details 统一胶囊按钮：同尺寸同形状，靠颜色区分语义。
   Cancel 用红色（danger）表明破坏性操作，View details 用中性灰描边。 */
.history-action-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.history-action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  border-radius: 999px;
  padding: 8px 14px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.15s ease,
    background-color 0.15s ease,
    border-color 0.15s ease,
    box-shadow 0.15s ease,
    opacity 0.15s ease,
    transform 0.15s ease;
  border: 1px solid transparent;
  background: #ffffff;
  min-width: 108px;
  line-height: 1;
}
.history-action-btn i {
  font-size: 11px;
}

.history-action-btn--ghost {
  border-color: #cbd5e1;
  color: #475569;
}
.history-action-btn--ghost:hover {
  background: #f1f5f9;
  border-color: #94a3b8;
  color: #1e293b;
}

.history-action-btn--danger {
  border-color: var(--color-danger-border);
  background: var(--color-danger-soft);
  color: #b91c1c;
}
.history-action-btn--danger:hover {
  background: var(--color-danger-soft);
  border-color: #f87171;
  color: var(--color-danger);
}

.history-action-btn:focus-visible {
  outline: none;
  box-shadow: 0 0 0 4px rgba(148, 163, 184, 0.18);
}

.cancel-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  z-index: 2000;
}

.cancel-modal {
  width: min(100%, 520px);
  background: #ffffff;
  border-radius: 18px;
  box-shadow: 0 25px 60px rgba(15, 23, 42, 0.2);
  overflow: hidden;
}

.cancel-modal-header,
.cancel-modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 22px;
}

.cancel-modal-header {
  border-bottom: 1px solid var(--color-border);
}

.cancel-modal-header h3 {
  margin: 0;
  font-size: 18px;
  color: #1e293b;
}

.cancel-modal-close {
  border: none;
  background: transparent;
  color: #64748b;
  font-size: 24px;
  line-height: 1;
  cursor: pointer;
}

.cancel-modal-body {
  padding: 22px;
}

.cancel-modal-text {
  margin: 0 0 14px;
  color: #475569;
  font-size: 14px;
}

.cancel-modal-textarea {
  width: 100%;
  min-height: 120px;
  border: 1px solid #cbd5e1;
  border-radius: var(--radius-lg);
  padding: 14px 16px;
  font-size: 14px;
  color: #1e293b;
  resize: vertical;
  box-sizing: border-box;
}

.cancel-modal-textarea:focus {
  outline: none;
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px rgba(var(--color-brand-rgb), 0.12);
}

.cancel-modal-meta {
  margin-top: 10px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.cancel-modal-error {
  color: var(--color-danger);
  font-size: 13px;
}

.cancel-modal-count {
  margin-left: auto;
  color: #94a3b8;
  font-size: 12px;
}

.cancel-modal-footer {
  border-top: 1px solid var(--color-border);
  justify-content: flex-end;
  gap: 12px;
}

.cancel-modal-btn {
  border: none;
  border-radius: var(--radius-md);
  padding: 11px 18px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    opacity 0.2s ease,
    transform 0.2s ease;
}

.cancel-modal-btn.secondary {
  background: var(--color-border);
  color: #334155;
}

.cancel-modal-btn.secondary:hover:not(:disabled) {
  background: #cbd5e1;
}

.cancel-modal-btn.danger {
  background: linear-gradient(135deg, #475569 0%, #334155 100%);
  color: #fff;
}

.cancel-modal-btn.danger:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 8px 20px rgba(51, 65, 85, 0.24);
}

.cancel-modal-btn:disabled,
.cancel-modal-close:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

/* Loading more indicator */
.loading-more,
.no-more {
  text-align: center;
  padding: 20px;
  color: var(--color-muted);
  font-size: 14px;
}

.loading-more {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.loading-more i {
  font-size: 18px;
  color: #2563eb;
}

.no-more p {
  margin: 0;
  color: var(--color-faint);
}

@media (max-width: 992px) {
  .history-header {
    flex-direction: column;
    gap: 12px;
    padding: 14px;
    border-radius: var(--radius-md);
    border-width: 1px;
  }

  .header-left {
    width: 100%;
    flex-direction: column;
    min-width: 0;
  }

  .header-right {
    width: 100%;
    flex-direction: column;
  }

  .history-search {
    min-width: 0;
  }

  .history-filter {
    width: 100%;
  }

  .date-range {
    width: 100%;
    flex-direction: column;
    align-items: stretch;
    height: auto;
    padding: 12px;
  }

  .date-range label {
    margin-bottom: 8px;
  }

  .date-inputs {
    flex-direction: column;
    width: 100%;
    gap: 8px;
    align-items: stretch;
  }

  .date-range input {
    width: 100%;
    min-width: 100%;
    height: 40px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
  }

  .date-separator {
    display: none;
  }

  .apply-btn {
    width: 100%;
    justify-content: center;
    height: 40px;
    border-radius: var(--radius-sm);
    margin-top: 8px;
  }

  .export-btn {
    width: 100%;
    justify-content: center;
  }

  .history-item {
    flex-direction: column;
  }

  .history-side {
    width: 100%;
    min-width: 0;
    align-items: stretch;
    padding-top: 14px;
    border-top: 1px solid var(--color-border);
    gap: 10px;
  }

  .history-side-top {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }

  .history-amount-group {
    text-align: right;
  }

  .history-amount {
    text-align: right;
    font-size: 18px;
  }

  .history-amount-label {
    text-align: right;
  }

  .history-cancel-btn {
    width: 100%;
    text-align: center;
    justify-content: center;
  }
}

@media (max-width: 768px) {
  .history-container {
    padding: 14px;
    border-radius: var(--radius-xl);
  }

  .history-header {
    margin-bottom: 16px;
  }

  .history-search input {
    padding: 10px 12px;
    font-size: 13px;
  }

  .history-filter select {
    padding: 10px 12px;
    font-size: 13px;
    height: 40px;
  }

  .date-range label {
    font-size: 12px;
  }

  .date-range input {
    font-size: 13px;
  }

  .apply-btn {
    font-size: 13px;
  }

  .export-btn {
    font-size: 13px;
    padding: 10px 16px;
  }

  /* Tabs */
  .history-tabs {
    gap: 6px;
    margin-bottom: 16px;
    padding-bottom: 2px;
    overflow-x: auto;
    overflow-y: hidden;
  }

  .history-tab {
    padding: 7px 12px;
    font-size: 12px;
    white-space: nowrap;
  }

  /* List items */
  .history-list {
    gap: 10px;
  }

  .history-item {
    padding: 14px;
    border-radius: 14px;
    gap: 12px;
  }

  .history-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-lg);
    font-size: 16px;
  }

  .history-title {
    font-size: 14px;
    margin-bottom: 3px;
  }

  .history-date {
    font-size: 12px;
  }

  .history-topline {
    flex-direction: column;
    align-items: flex-start;
  }

  .history-meta {
    flex-direction: column;
    align-items: stretch;
    margin-top: 8px;
    gap: 6px;
  }

  .history-transaction-id,
  .history-transfer-info {
    font-size: 12px;
    padding: 4px 8px;
  }

  .history-status {
    font-size: 11px;
    padding: 5px 10px;
    min-width: 80px;
  }

  .history-amount {
    font-size: 16px;
  }

  .history-cancel-btn {
    font-size: 12px;
    padding: 8px 12px;
  }

  .loading-state i,
  .empty-state i {
    font-size: 36px;
  }

  .loading-state p,
  .empty-state p {
    font-size: 14px;
  }

  .cancel-modal-footer {
    flex-direction: column;
    align-items: stretch;
  }

  .cancel-modal-btn {
    width: 100%;
  }
}

@media (max-width: 576px) {
  .history-item-main {
    flex-direction: column;
    gap: 8px;
  }
}
</style>
