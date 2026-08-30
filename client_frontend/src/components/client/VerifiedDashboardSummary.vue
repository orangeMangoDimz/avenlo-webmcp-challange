<template>
  <div class="verified-dashboard">
    <!-- Top: Total assets + Trading accounts -->
    <div class="overview-grid">
      <!-- Total assets estimate -->
      <section class="overview-card assets-card">
        <header class="overview-card-header">
          <h3 class="overview-card-title">
            {{ t("totalAssetsEstimate", "Total assets estimate") }}
            <button
              type="button"
              class="visibility-toggle"
              :aria-label="
                hideAssets
                  ? t('showAmount', 'Show amount')
                  : t('hideAmount', 'Hide amount')
              "
              @click="hideAssets = !hideAssets"
            >
              <i :class="hideAssets ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
            </button>
          </h3>
        </header>
        <div class="assets-amount">
          <span class="assets-value">{{
            hideAssets ? "••••" : formatTotal(accountSummary.Total)
          }}</span>
          <span class="assets-currency">USD</span>
        </div>
        <div class="assets-actions">
          <button
            type="button"
            class="asset-btn asset-btn--primary"
            @click="goToTransactions('deposit')"
          >
            {{ t("transDeposit", "Deposit") }}
          </button>
          <button
            type="button"
            class="asset-btn"
            @click="goToTransactions('withdraw')"
          >
            {{ t("transWithdraw", "Withdrawal") }}
          </button>
          <button
            type="button"
            class="asset-btn"
            @click="goToTransactions('transfer')"
          >
            {{ t("transInternalTransfer", "Transfer") }}
          </button>
          <button
            type="button"
            class="asset-btn"
            @click="goToTransactionHistory"
          >
            {{ t("history", "History") }}
          </button>
        </div>
      </section>

      <!-- Trading accounts list -->
      <section class="overview-card accounts-card">
        <header class="overview-card-header">
          <h3 class="overview-card-title">
            {{ t("tradingAccount", "Trading Account") }}
          </h3>
          <router-link to="/client/accounts" class="overview-card-link">
            {{ t("more", "More") }} <i class="fas fa-chevron-right"></i>
          </router-link>
        </header>
        <div v-if="loadingAccounts" class="accounts-state">
          <i class="fas fa-spinner fa-spin"></i>
          {{ t("loading", "Loading...") }}
        </div>
        <div v-else-if="!accounts.length" class="accounts-state">
          {{
            t(
              "noTradingAccountsYet",
              "You have not opened any trading accounts yet.",
            )
          }}
        </div>
        <ul v-else class="accounts-list">
          <li
            v-for="acc in displayedAccounts"
            :key="acc.id"
            class="accounts-item"
          >
            <span class="account-platform">{{ formatPlatform(acc) }}</span>
            <span class="account-id">{{ formatAccountId(acc) }}</span>
            <span class="account-balance">{{ formatAccountBalance(acc) }}</span>
            <span class="account-currency">{{
              formatAccountCurrency(acc)
            }}</span>
            <span class="account-group">{{ formatGroupLabel(acc) }}</span>
          </li>
        </ul>
      </section>
    </div>

    <!-- Balance / Equity / Wallet 资金卡片 -->
    <div class="quick-stats">
      <div v-for="stat in stats" :key="stat.label" class="stat-card">
        <div class="stat-card-header">
          <div :class="['stat-card-icon', stat.color]">
            <i :class="stat.icon"></i>
          </div>
          <div class="stat-card-label">{{ stat.label }}</div>
        </div>
        <div class="stat-card-body">
          <div class="stat-card-value">{{ stat.value }}</div>
        </div>
      </div>
    </div>

    <!-- Recent Notifications -->
    <div class="notifications-panel">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-bell"></i>
          {{ t("recentNotifications", "Recent Notifications") }}
        </h2>
        <a
          href="#"
          class="section-link"
          @click.prevent="openNotificationsPanel"
        >
          {{ t("viewAll", "View All") }} <i class="fas fa-arrow-right"></i>
        </a>
      </div>
      <div v-if="loadingNotifications" class="notification-loading">
        <i class="fas fa-spinner fa-spin"></i> {{ t("loading", "Loading...") }}
      </div>
      <template v-else>
        <div
          v-for="notification in recentNotifications"
          :key="notification.id"
          :class="['notification-item', { unread: !notification.isRead }]"
          @click="openNotificationDetail(notification)"
        >
          <div class="notification-icon" :class="notification.type">
            <i :class="getNotificationIcon(notification.type)"></i>
          </div>
          <div class="notification-content">
            <div class="notification-title">{{ notification.subject }}</div>
            <div class="notification-text">{{ notification.message }}</div>
            <div class="notification-time">
              {{ formatTime(notification.createdAt) }}
            </div>
          </div>
        </div>
        <div v-if="!recentNotifications.length" class="notification-empty">
          <i class="fas fa-bell-slash"></i>
          <p>{{ t("noNotificationsYet", "No notifications yet") }}</p>
        </div>
      </template>
    </div>

    <!-- Notification Panel Component -->
    <NotificationPanel
      :visible="panelVisible"
      :notifications="panelNotifications"
      :unread-count="unreadCount"
      :selected-id="selectedNotification?.id"
      :loading="panelLoading"
      :error="panelError"
      @close="closePanel"
      @select="selectNotification"
      @scroll="handlePanelScroll"
      @mark-all-read="markAllAsRead"
    />

    <!-- Notification Detail Modal -->
    <NotificationDetailModal
      :notification="selectedNotification"
      @close="closeDetailModal"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { useRouter } from "vue-router";
import { useLanguageStore } from "@/stores/language";
import { clientNotificationService } from "@/services/clientNotificationService";
import tradingAccountService from "@/services/tradingAccountService";
import {
  formatNumber,
  formatCurrency as formatCurrencyWithSymbol,
} from "@/utils/helpers";
import NotificationPanel from "@/components/notifications/NotificationPanel.vue";
import NotificationDetailModal from "@/components/notifications/NotificationDetailModal.vue";

const router = useRouter();
const languageStore = useLanguageStore();
const t = (key, fallback = "") => languageStore.t(key, fallback);

const PER_PAGE_RECENT = 5;
const PANEL_PAGE_SIZE = 10;
const SCROLL_THRESHOLD = 120;

const loadingNotifications = ref(false);
const recentNotifications = ref([]);
const unreadCount = ref(0);

const panelVisible = ref(false);
const panelNotifications = ref([]);
const panelPage = ref(1);
const panelHasMore = ref(true);
const panelLoading = ref(false);
const panelError = ref("");
const panelInitialized = ref(false);
const selectedNotification = ref(null);
// Total assets 数据，来自 /trading/accounts/summary
const accountSummary = ref({
  Total: "0",
  Wallet: "0",
  Balance: "0",
  Equity: "0",
  Currency: "USD",
});
const hideAssets = ref(false);

// 下面那一排 Balance / Equity / Wallet 资金卡片
const formatCurrency = (value) => formatCurrencyWithSymbol(value, "$");

const stats = computed(() => [
  {
    label: t("balance", "Balance"),
    value: formatCurrency(accountSummary.value.Balance),
    icon: "fas fa-dollar-sign",
    color: "green",
  },
  {
    label: t("equity", "Equity"),
    value: formatCurrency(accountSummary.value.Equity),
    icon: "fas fa-chart-line",
    color: "orange",
  },
  {
    label: t("wallet", "Wallet"),
    value: formatCurrency(accountSummary.value.Wallet),
    icon: "fas fa-wallet",
    color: "red",
  },
]);

// Trading accounts 列表，来自 /trading/accounts，跟 ClientAccounts.vue 用同一接口
const accounts = ref([]);
const loadingAccounts = ref(false);
const ACCOUNTS_PREVIEW_LIMIT = 3;
const displayedAccounts = computed(() =>
  accounts.value.slice(0, ACCOUNTS_PREVIEW_LIMIT),
);

// 跳到 ClientTransactions 并切到指定 tab，对应该页 URL ?tab=deposit|withdraw|transfer
const goToTransactions = (tab) => {
  router.push({ path: "/client/transactions", query: { tab } });
};

const goToTransactionHistory = () => {
  router.push("/client/transaction-history");
};

const formatTotal = (value) => {
  const num = typeof value === "string" ? parseFloat(value) : value;
  return Number.isFinite(num) ? formatNumber(num, 2) : "0.00";
};

const formatPlatform = (acc) => {
  return String(acc?.platformCode || acc?.platformName || "")
    .trim()
    .toUpperCase();
};

const formatAccountId = (acc) => {
  const num = String(acc?.accountNumber || "").trim();
  const nick = String(acc?.accountNickname || "").trim();
  return nick ? `${num} (${nick})` : num;
};

const formatAccountBalance = (acc) => {
  const candidate =
    acc?.availableBalance ??
    acc?.balance ??
    acc?.Balance ??
    acc?.accountBalance ??
    acc?.currentBalance;
  if (candidate === null || candidate === undefined || candidate === "") {
    return "0.00";
  }
  const num = typeof candidate === "string" ? parseFloat(candidate) : candidate;
  return Number.isFinite(num) ? formatNumber(num, 2) : "0.00";
};

const formatAccountCurrency = (acc) => {
  return (
    String(acc?.groupUnit || acc?.accountCurrency || "USD")
      .trim()
      .toUpperCase() || "USD"
  );
};

const formatGroupLabel = (acc) => {
  return String(acc?.groupLabel || acc?.groupName || "").trim() || "-";
};

// Fetch recent notifications
const fetchRecentNotifications = async () => {
  loadingNotifications.value = true;
  try {
    const response = await clientNotificationService.fetchNotifications({
      perPage: PER_PAGE_RECENT,
    });
    const payload = response?.data ?? response ?? {};
    const items = Array.isArray(payload.items)
      ? payload.items.map(normalizeNotification)
      : [];
    recentNotifications.value = items.slice(0, PER_PAGE_RECENT);
    if (typeof payload.unreadCount === "number") {
      unreadCount.value = payload.unreadCount;
    }
  } catch (error) {
    console.error("Failed to load notifications", error);
  } finally {
    loadingNotifications.value = false;
  }
};

const fetchPanelNotifications = async ({ reset = false } = {}) => {
  if (panelLoading.value) return;
  if (reset) {
    panelPage.value = 1;
    panelHasMore.value = true;
    panelNotifications.value = [];
  }
  if (!panelHasMore.value) return;

  panelLoading.value = true;
  panelError.value = "";
  try {
    const response = await clientNotificationService.fetchNotifications({
      page: panelPage.value,
      perPage: PANEL_PAGE_SIZE,
    });
    const payload = response?.data ?? response ?? {};
    const items = Array.isArray(payload.items)
      ? payload.items.map(normalizeNotification)
      : [];
    mergeNotifications(panelNotifications, items);

    if (typeof payload.unreadCount === "number") {
      unreadCount.value = payload.unreadCount;
    }

    panelHasMore.value = payload.pagination?.hasMore ?? false;
    if (panelHasMore.value) {
      panelPage.value += 1;
    }
    panelInitialized.value = true;
  } catch (error) {
    console.error("Failed to load notifications", error);
    panelError.value =
      error?.response?.data?.message ||
      error.message ||
      "Failed to load notifications";
  } finally {
    panelLoading.value = false;
  }
};

const openNotificationDetail = async (notification) => {
  await markNotificationAsRead(notification);
  selectedNotification.value = { ...notification, isRead: true };
};

const openNotificationsPanel = async () => {
  panelVisible.value = true;
  if (!panelInitialized.value) {
    await fetchPanelNotifications({ reset: true });
  }
};

const closePanel = () => {
  panelVisible.value = false;
  closeDetailModal();
};

const selectNotification = async (notification) => {
  await markNotificationAsRead(notification);
  const found = panelNotifications.value.find(
    (item) => item.id === notification.id,
  );
  selectedNotification.value = found
    ? { ...found, isRead: true }
    : { ...notification, isRead: true };
};

const closeDetailModal = () => {
  selectedNotification.value = null;
};

const handlePanelScroll = (scrollInfo) => {
  if (panelLoading.value || !panelHasMore.value) return;
  const { scrollTop, scrollHeight, clientHeight } = scrollInfo;
  if (scrollTop + clientHeight >= scrollHeight - SCROLL_THRESHOLD) {
    fetchPanelNotifications();
  }
};

const markNotificationAsRead = async (notification) => {
  if (!notification) return;
  if (notification.isRead) {
    applyReadState(notification.id);
    return;
  }

  try {
    const response = await clientNotificationService.markAsRead({
      id: notification.id,
    });
    applyReadState(notification.id);
    if (response?.data?.unreadCount !== undefined) {
      unreadCount.value = response.data.unreadCount;
    } else {
      unreadCount.value = Math.max(0, unreadCount.value - 1);
    }
  } catch (error) {
    console.error("Failed to mark notification as read", error);
  }
};

const markAllAsRead = async () => {
  try {
    await clientNotificationService.markAllAsRead();
    panelNotifications.value = panelNotifications.value.map((item) => ({
      ...item,
      isRead: true,
      readAt: item.readAt || new Date().toISOString(),
    }));
    recentNotifications.value = recentNotifications.value.map((item) => ({
      ...item,
      isRead: true,
      readAt: item.readAt || new Date().toISOString(),
    }));
    if (selectedNotification.value) {
      selectedNotification.value = {
        ...selectedNotification.value,
        isRead: true,
        readAt: selectedNotification.value.readAt || new Date().toISOString(),
      };
    }
    unreadCount.value = 0;
  } catch (error) {
    console.error("Failed to mark all notifications as read", error);
  }
};

const applyReadState = (id) => {
  const nowIso = new Date().toISOString();
  const updateList = (list) => {
    const index = list.findIndex((item) => item.id === id);
    if (index !== -1 && !list[index].isRead) {
      list[index] = {
        ...list[index],
        isRead: true,
        readAt: list[index].readAt || nowIso,
      };
    }
  };

  updateList(panelNotifications.value);
  updateList(recentNotifications.value);

  if (selectedNotification.value && selectedNotification.value.id === id) {
    selectedNotification.value = {
      ...selectedNotification.value,
      isRead: true,
      readAt: selectedNotification.value.readAt || nowIso,
    };
  }
};

const mergeNotifications = (targetRef, newItems) => {
  if (!newItems.length) return;
  const map = new Map();
  targetRef.value.forEach((item) => map.set(item.id, item));
  newItems.forEach((item) => {
    const existing = map.get(item.id) || {};
    map.set(item.id, { ...existing, ...item });
  });
  const merged = Array.from(map.values());
  merged.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
  targetRef.value = merged;
};

const normalizeNotification = (item) => {
  return {
    id: Number(item.id),
    notificationId: Number(item.notificationId ?? item.id ?? 0),
    subject: item.subject || "Notification",
    message: item.message || "",
    isRead: Boolean(item.isRead),
    readAt: item.readAt,
    createdAt: item.createdAt
      ? new Date(item.createdAt).toISOString()
      : new Date().toISOString(),
    type: item.type || "info",
  };
};

const getNotificationIcon = (type) => {
  const icons = {
    success: "fas fa-check-circle",
    info: "fas fa-info-circle",
    warning: "fas fa-exclamation-triangle",
    error: "fas fa-times-circle",
  };
  return icons[type] || "fas fa-bell";
};

const formatTime = (value) => {
  if (!value) return "";
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return "";

  const now = new Date();
  const diffSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

  if (diffSeconds < 60) {
    return "Just now";
  }
  if (diffSeconds < 3600) {
    const minutes = Math.floor(diffSeconds / 60);
    return `${minutes} minute${minutes > 1 ? "s" : ""} ago`;
  }
  if (diffSeconds < 86400) {
    const hours = Math.floor(diffSeconds / 3600);
    return `${hours} hour${hours > 1 ? "s" : ""} ago`;
  }
  if (diffSeconds < 604800) {
    const days = Math.floor(diffSeconds / 86400);
    return `${days} day${days > 1 ? "s" : ""} ago`;
  }
  return date.toLocaleDateString(undefined, {
    month: "short",
    day: "numeric",
    year: date.getFullYear() !== now.getFullYear() ? "numeric" : undefined,
  });
};

// Fetch account summary（Total + 各项明细，给上面的 Total assets estimate 和下面的 stat 卡片都用）
const fetchAccountSummary = async () => {
  try {
    const response = await tradingAccountService.getAccountSummary();
    const data = response?.data ?? response ?? {};
    accountSummary.value = {
      Total: data.Total || "0",
      Wallet: data.Wallet || "0",
      Balance: data.Balance || "0",
      Equity: data.Equity || "0",
      Currency: data.Currency || "USD",
    };
  } catch (error) {
    console.error("Failed to load account summary", error);
  }
};

// Fetch trading accounts 列表（展示在 Trading Account 卡片里）
const fetchAccounts = async () => {
  loadingAccounts.value = true;
  try {
    const response = await tradingAccountService.getAccounts();
    if (response?.success && response?.data) {
      accounts.value = response.data.accounts || [];
    }
  } catch (error) {
    console.error("Failed to load trading accounts", error);
  } finally {
    loadingAccounts.value = false;
  }
};

onMounted(async () => {
  await fetchRecentNotifications();
  await Promise.all([fetchAccountSummary(), fetchAccounts()]);
});
</script>

<style scoped>
.verified-dashboard {
  display: flex;
  flex-direction: column;
  gap: 30px;
}

/* Top two-column layout: Total assets + Trading accounts */
.overview-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
}

.overview-card {
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  padding: 28px;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
  display: flex;
  flex-direction: column;
  gap: 20px;
  min-height: 220px;
}

.overview-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.overview-card-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--color-ink);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}

.overview-card-link {
  color: var(--color-text);
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.overview-card-link:hover {
  color: var(--color-ink);
}

/* Total assets card */
.visibility-toggle {
  background: transparent;
  border: none;
  color: var(--color-faint);
  cursor: pointer;
  padding: 4px;
  font-size: 14px;
  display: inline-flex;
  align-items: center;
}

.visibility-toggle:hover {
  color: var(--color-ink);
}

.assets-amount {
  display: flex;
  align-items: baseline;
  gap: 10px;
}

.assets-value {
  font-size: 36px;
  font-weight: 700;
  color: var(--color-ink-strong);
  line-height: 1.1;
}

.assets-currency {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-muted);
}

.assets-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: auto;
}

.asset-btn {
  padding: 9px 18px;
  border-radius: 999px;
  border: none;
  background: var(--color-surface-soft);
  color: var(--color-ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition:
    background 0.2s ease,
    transform 0.15s ease;
}

.asset-btn:hover {
  background: var(--color-border);
  transform: translateY(-1px);
}

.asset-btn--primary {
  background: var(--color-sidebar);
  color: #fff;
}

.asset-btn--primary:hover {
  background: var(--color-sidebar-raised);
}

/* Trading accounts card */
.accounts-state {
  font-size: 14px;
  color: var(--color-faint);
  padding: 24px 0;
  text-align: center;
}

.accounts-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.accounts-item {
  display: grid;
  grid-template-columns: auto 1fr auto auto auto;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
  font-size: 14px;
  color: var(--color-ink);
}

.accounts-item + .accounts-item {
  border-top: 1px solid var(--color-border);
  padding-top: 16px;
}

.account-platform {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 44px;
  padding: 4px 8px;
  border-radius: var(--radius-sm);
  background: var(--color-surface-soft);
  color: var(--color-text);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
}

.account-id {
  color: var(--color-ink);
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.account-balance {
  font-weight: 700;
  color: var(--color-ink-strong);
}

.account-currency {
  color: var(--color-muted);
  font-size: 13px;
  font-weight: 600;
}

.account-group {
  color: var(--color-muted);
  font-size: 13px;
  text-align: right;
  min-width: 90px;
}

/* Balance / Equity / Wallet 资金卡片 */
.quick-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 20px;
}

.stat-card {
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  padding: 22px 20px;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.stat-card-header {
  display: flex;
  align-items: center;
  gap: 12px;
}

.stat-card-icon {
  width: 44px;
  height: 44px;
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 18px;
  flex-shrink: 0;
}

.stat-card-icon.blue {
  background: var(--color-brand-solid);
}
.stat-card-icon.green {
  background: var(--color-success-solid);
}
.stat-card-icon.orange {
  background: var(--color-warning-solid);
}
.stat-card-icon.red {
  background: var(--color-danger-solid);
}

.stat-card-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  flex: 1;
}

.stat-card-body {
  width: 100%;
}

.stat-card-value {
  font-size: 24px;
  font-weight: 700;
  color: var(--color-ink-strong);
  word-break: break-word;
}

/* Shared section-header for the notifications panel below */
.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.section-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 10px;
}

.section-title i {
  color: var(--color-brand);
}

.section-link {
  color: var(--color-brand);
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 5px;
}

.section-link:hover {
  color: var(--color-brand-strong);
}

.notifications-panel {
  background: var(--color-surface);
  border-radius: 20px;
  padding: 32px;
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
}

.notification-item {
  display: flex;
  align-items: flex-start;
  gap: 15px;
  padding: 15px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
  margin-bottom: 15px;
  transition: all 0.2s ease;
  cursor: pointer;
  border-left: 1px solid transparent;
}

.notification-item.unread {
  border-left-color: var(--color-brand);
  background: var(--color-brand-soft);
}

.notification-item:not(:last-child) {
  margin-bottom: 16px;
}

.notification-item:hover {
  background: var(--color-brand-soft);
  transform: translateX(4px);
}

.notification-loading,
.notification-empty {
  padding: 40px 20px;
  text-align: center;
  color: var(--color-faint);
  font-size: 14px;
}

.notification-empty i {
  font-size: 36px;
  margin-bottom: 12px;
  display: block;
}

.notification-icon {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 18px;
  flex-shrink: 0;
}

.notification-content {
  flex: 1;
  min-width: 0;
}

.notification-icon.blue {
  background: var(--color-brand-solid);
}
.notification-icon.green {
  background: var(--color-success-solid);
}
.notification-icon.orange {
  background: var(--color-warning-solid);
}
.notification-icon.info {
  background: var(--color-info-solid);
}
.notification-icon.error {
  background: var(--color-danger-solid);
}

.notification-title {
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 4px;
}

.notification-text {
  font-size: 14px;
  color: var(--color-text);
  line-height: 1.6;
  margin-bottom: 6px;
}

.notification-time {
  font-size: 12px;
  color: var(--color-faint);
}

@media (max-width: 960px) {
  .overview-grid {
    grid-template-columns: 1fr;
  }

  .accounts-item {
    grid-template-columns: auto 1fr;
    grid-template-areas:
      "platform id"
      "balance group";
    row-gap: 6px;
  }

  .account-platform {
    grid-area: platform;
  }
  .account-id {
    grid-area: id;
  }
  .account-balance {
    grid-area: balance;
  }
  .account-currency {
    display: none;
  }
  .account-group {
    grid-area: group;
    text-align: right;
    min-width: 0;
  }
}
</style>
