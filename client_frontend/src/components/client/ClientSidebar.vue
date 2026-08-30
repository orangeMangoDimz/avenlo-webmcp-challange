<template>
  <!-- Navigation sheet backdrop -->
  <button
    v-if="isMobileOpen"
    type="button"
    class="sidebar-overlay"
    :aria-label="t('closeNavigation', 'Close navigation')"
    @click="closeMobile"
  ></button>

  <aside
    :class="['sidebar', { 'mobile-open': isMobileOpen }]"
    :inert="!isMobileOpen ? '' : undefined"
    :aria-hidden="!isMobileOpen ? 'true' : undefined"
    role="dialog"
    aria-modal="true"
    aria-labelledby="clientSidebarTitle"
    id="clientSidebar"
    @keydown="handleKeydown"
  >
    <div class="sidebar-header">
      <span id="clientSidebarTitle" class="sidebar-sheet-title">{{
        t("navigate", "Navigate")
      }}</span>
      <button
        ref="closeButton"
        type="button"
        class="toggle-sidebar-btn"
        :aria-label="t('closeNavigation', 'Close navigation')"
        @click="closeMobile"
      >
        <i class="fas fa-times"></i>
      </button>
    </div>

    <!-- Dashboard Section -->
    <div class="menu-section" :class="{ expanded: expandedSections.dashboard }">
      <button
        type="button"
        class="menu-section-header"
        :data-title="t('navDashboard', 'Dashboard')"
        :aria-expanded="expandedSections.dashboard"
        @click="toggleSection('dashboard')"
      >
        <div class="menu-section-title">
          <span class="menu-icon"><i class="fas fa-home"></i></span>
          <span class="menu-text">{{ t("navDashboard", "Dashboard") }}</span>
        </div>
        <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
      </button>
      <div class="menu-items">
        <router-link
          to="/client/dashboard"
          class="menu-item"
          active-class="active"
          >{{ t("navOverview", "Overview") }}</router-link
        >
        <!--        <router-link to="/client/account-summary" class="menu-item">Account Summary</router-link>-->
      </div>
    </div>

    <!-- Transactions Section -->
    <div
      v-if="isKycApproved"
      class="menu-section"
      :class="{ expanded: expandedSections.transactions }"
    >
      <button
        type="button"
        class="menu-section-header"
        :data-title="t('navTransactions', 'Transactions')"
        :aria-expanded="expandedSections.transactions"
        @click="toggleSection('transactions')"
      >
        <div class="menu-section-title">
          <span class="menu-icon"><i class="fas fa-exchange-alt"></i></span>
          <span class="menu-text">{{
            t("navTransactions", "Transactions")
          }}</span>
        </div>
        <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
      </button>
      <div class="menu-items">
        <router-link
          to="/client/transactions"
          class="menu-item"
          active-class="active"
          >{{ t("navFundingManagement", "Deposit & Withdraw") }}</router-link
        >
        <router-link
          to="/client/transaction-history"
          class="menu-item"
          active-class="active"
          >{{ t("navTransactionHistory", "Transaction History") }}</router-link
        >
      </div>
    </div>

    <!-- Trading Section -->
    <div
      v-if="isKycApproved"
      class="menu-section"
      :class="{ expanded: expandedSections.trading }"
    >
      <button
        type="button"
        class="menu-section-header"
        :data-title="t('navTrading', 'Trading')"
        :aria-expanded="expandedSections.trading"
        @click="toggleSection('trading')"
      >
        <div class="menu-section-title">
          <span class="menu-icon"><i class="fas fa-chart-bar"></i></span>
          <span class="menu-text">{{ t("navTrading", "Trading") }}</span>
        </div>
        <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
      </button>
      <div class="menu-items">
        <!-- <router-link to="/client/trading" class="menu-item">Trading Platform</router-link> -->
        <router-link
          to="/client/positions"
          class="menu-item"
          active-class="active"
          >{{ t("navPositions", "Positions") }}</router-link
        >
        <router-link
          to="/client/history"
          class="menu-item"
          active-class="active"
          >{{ t("navOrderHistory", "Order History") }}</router-link
        >
      </div>
    </div>

    <!-- Accounts Section -->
    <div
      v-if="isKycApproved"
      class="menu-section"
      :class="{ expanded: expandedSections.accounts }"
    >
      <button
        type="button"
        class="menu-section-header"
        :data-title="t('navAccounts', 'Accounts')"
        :aria-expanded="expandedSections.accounts"
        @click="toggleSection('accounts')"
      >
        <div class="menu-section-title">
          <span class="menu-icon"><i class="fas fa-wallet"></i></span>
          <span class="menu-text">{{ t("navAccounts", "Accounts") }}</span>
        </div>
        <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
      </button>
      <div class="menu-items">
        <router-link
          to="/client/accounts"
          class="menu-item"
          active-class="active"
          >{{ t("navMyAccounts", "My Accounts") }}</router-link
        >
        <router-link
          v-if="canOpenNewAccount"
          to="/client/account/new"
          class="menu-item"
          active-class="active"
        >
          {{ t("navOpenNewAccount", "Open New Account") }}
        </router-link>
      </div>
    </div>

    <!-- IB Program Section (显示条件：通过IB邀请但没有通过后台IB Applications审核通过的用户) -->
    <div v-if="showIbProgram && !isIbApproved" class="menu-section">
      <router-link
        to="/client/ib-dashboard"
        class="menu-section-header menu-section-locked"
        :class="{ active: $route.name === 'client-ib-dashboard' }"
        :data-title="t('navIbProgramLocked', 'IB Program (Locked)')"
        style="text-decoration: none; color: inherit"
      >
        <div class="menu-section-title">
          <span class="menu-icon"><i class="fas fa-handshake"></i></span>
          <span class="menu-text"
            >{{ t("navIbProgram", "IB Program")
            }}<i class="fas fa-lock menu-lock-icon"></i
          ></span>
        </div>
      </router-link>
    </div>

    <!-- IB Program Section (显示条件：用户已通过审批成为 IB 代理) -->
    <div
      v-if="showIbProgram && isIbApproved"
      class="menu-section"
      :class="{ expanded: expandedSections.ibProgram }"
    >
      <button
        type="button"
        class="menu-section-header"
        :data-title="t('navIbProgram', 'IB Program')"
        :aria-expanded="expandedSections.ibProgram"
        @click="toggleSection('ibProgram')"
      >
        <div class="menu-section-title">
          <span class="menu-icon"><i class="fas fa-handshake"></i></span>
          <span class="menu-text">{{ t("navIbProgram", "IB Program") }}</span>
        </div>
        <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
      </button>
      <div class="menu-items">
        <router-link
          to="/client/ib-dashboard-active"
          class="menu-item"
          active-class="active"
          >{{ t("navMyIbDashboard", "My IB Dashboard") }}</router-link
        >
        <router-link
          to="/client/commission-report"
          class="menu-item"
          active-class="active"
          >{{ t("navCommissionReport", "Commission Report") }}</router-link
        >
        <router-link
          to="/client/deposit-withdraw-report"
          class="menu-item"
          active-class="active"
          >{{
            t("navDepositWithdrawReport", "Deposit Withdraw Report")
          }}</router-link
        >
        <router-link
          to="/client/ib-statement"
          class="menu-item"
          active-class="active"
          >{{ t("navPnlReport", "P&L Report") }}</router-link
        >
        <router-link
          to="/client/ib-client-position"
          class="menu-item"
          active-class="active"
          >{{ t("navClientPosition", "Client's Position") }}</router-link
        >
      </div>
    </div>

    <!-- Documents Section -->
    <div class="menu-section" :class="{ expanded: expandedSections.documents }">
      <button
        type="button"
        class="menu-section-header"
        :data-title="t('navDocuments', 'Documents')"
        :aria-expanded="expandedSections.documents"
        @click="toggleSection('documents')"
      >
        <div class="menu-section-title">
          <span class="menu-icon"><i class="fas fa-file-alt"></i></span>
          <span class="menu-text">{{ t("navDocuments", "Documents") }}</span>
        </div>
        <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
      </button>
      <div class="menu-items">
        <router-link
          to="/client/documents"
          class="menu-item"
          active-class="active"
          >{{ t("navMyDocuments", "My Documents") }}</router-link
        >
        <!-- <router-link to="/client/statements" class="menu-item">Statements</router-link> -->
        <!-- <router-link to="/client/reports" class="menu-item">Reports</router-link> -->
      </div>
    </div>
  </aside>
</template>

<script setup>
import { ref, computed, watch, nextTick } from "vue";
import { useRoute } from "vue-router";
import { useClientAuthStore } from "@/stores/clientAuth";
import { useLanguageStore } from "@/stores/language";

const props = defineProps({
  mobileOpen: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["close-mobile"]);

const route = useRoute();
const clientAuthStore = useClientAuthStore();
const languageStore = useLanguageStore();
const t = (key, fallback = "") => languageStore.t(key, fallback);
const canOpenNewAccount = computed(() => clientAuthStore.canOpenNewAccount);
const isKycApproved = computed(() => clientAuthStore.isKycApproved);
const showIbProgram = computed(() => clientAuthStore.showIbProgram);
const isIbApproved = computed(() => clientAuthStore.isIbApproved);

const isMobileOpen = computed(() => props.mobileOpen);
const closeButton = ref(null);

const expandedSections = ref({
  dashboard: true,
  trading: false,
  accounts: false,
  transactions: false,
  documents: false,
  ibProgram: false,
});

const closeMobile = () => {
  emit("close-mobile");
};

const toggleSection = (section) => {
  expandedSections.value[section] = !expandedSections.value[section];
};

watch(
  () => route.path,
  () => emit("close-mobile"),
);

watch(isMobileOpen, async (open) => {
  if (open) {
    await nextTick();
    closeButton.value?.focus();
  }
});

const handleKeydown = (event) => {
  if (event.key === "Escape") {
    event.preventDefault();
    closeMobile();
    return;
  }
  if (event.key !== "Tab") return;

  const focusable = [
    ...event.currentTarget.querySelectorAll(
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ),
  ].filter((element) => {
    if (element.closest("[inert]")) return false;
    const menuItems = element.closest(".menu-items");
    return (
      !menuItems ||
      (menuItems.getBoundingClientRect().height > 0 &&
        window.getComputedStyle(menuItems).visibility !== "hidden")
    );
  });
  const first = focusable[0];
  const last = focusable.at(-1);
  if (!first || !last) return;
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
};
</script>

<style scoped>
.sidebar {
  width: 264px;
  flex-shrink: 0;
  background: var(--color-sidebar);
  color: white;
  height: 100vh;
  border-right: 1px solid rgba(255, 255, 255, 0.08);
  transition:
    width var(--transition-fast),
    transform var(--transition-fast);
  overflow-y: auto;
  overflow-x: hidden;
  z-index: 100;
}

.sidebar.collapsed {
  width: 76px;
}

.sidebar::-webkit-scrollbar {
  width: 6px;
}

.sidebar::-webkit-scrollbar-track {
  background: var(--color-sidebar);
}

.sidebar::-webkit-scrollbar-thumb {
  background: var(--color-sidebar-raised);
  border-radius: 3px;
}

.sidebar-header {
  min-height: 78px;
  padding: 18px 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: transparent;
}

.logo-container {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  transition: opacity 0.3s ease;
}

.sidebar.collapsed .logo-container {
  opacity: 0;
  display: none;
}

/* 横版logo样式 */
.horizontal-logo .sidebar-logo {
  height: 36px;
  max-width: 200px;
  object-fit: contain;
}

/* 正方形logo + 文字样式 */
.square-logo {
  display: flex;
  align-items: center;
  gap: 12px;
}

.sidebar-logo-square {
  width: 40px;
  height: 40px;
  object-fit: contain;
  flex-shrink: 0;
}

.logo-text {
  font-size: 18px;
  font-weight: 600;
  color: white;
  white-space: nowrap;
}

.toggle-sidebar-btn {
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: white;
  width: 32px;
  height: 32px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition:
    background var(--transition-fast),
    border-color var(--transition-fast);
  font-size: 15px;
}

.toggle-sidebar-btn:hover {
  background: rgba(255, 255, 255, 0.12);
  border-color: rgba(255, 255, 255, 0.24);
}

.sidebar.collapsed .toggle-sidebar-btn {
  margin: 0 auto;
}

.menu-section {
  margin: 3px 10px;
}

.menu-section-header {
  width: 100%;
  border: 0;
  background: transparent;
  color: inherit;
  text-align: left;
  min-height: 44px;
  padding: 10px 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
  border-radius: var(--radius-sm);
  transition:
    background var(--transition-fast),
    color var(--transition-fast);
  user-select: none;
}

.menu-section-header:hover {
  background: rgba(255, 255, 255, 0.07);
}

.menu-section-title {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
}

.menu-icon {
  color: #d8bc83;
  font-size: 16px;
  min-width: 24px;
  text-align: center;
}

.menu-text {
  font-size: 13px;
  font-weight: 550;
  letter-spacing: 0.01em;
  white-space: nowrap;
  transition: opacity 0.3s ease;
}

.sidebar.collapsed .menu-text {
  opacity: 0;
  display: none;
}

.menu-arrow {
  font-size: 12px;
  transition:
    transform 0.3s ease,
    opacity 0.3s ease;
}

.sidebar.collapsed .menu-arrow {
  opacity: 0;
}

.menu-section.expanded .menu-arrow {
  transform: rotate(180deg);
}

.menu-items {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.3s ease;
  margin: 2px 0 4px 16px;
  background: transparent;
  border-left: 1px solid rgba(255, 255, 255, 0.12);
}

.menu-section.expanded .menu-items {
  max-height: 500px;
}

.menu-item {
  min-height: 38px;
  margin: 2px 8px;
  padding: 9px 12px 9px 18px;
  display: flex;
  align-items: center;
  color: rgba(255, 255, 255, 0.7);
  text-decoration: none;
  border-radius: var(--radius-sm);
  transition:
    background var(--transition-fast),
    color var(--transition-fast);
  font-size: 13px;
  cursor: pointer;
}

.sidebar.collapsed .menu-item {
  padding-left: 20px;
  justify-content: center;
}

.menu-item:hover {
  background: rgba(255, 255, 255, 0.07);
  color: white;
}

.sidebar.collapsed .menu-item:hover {
  padding-left: 20px;
}

.menu-item.router-link-active,
.menu-item.active {
  background: rgba(255, 255, 255, 0.1);
  box-shadow: inset 3px 0 0 var(--color-accent);
  color: white;
  font-weight: 600;
}

.sidebar.collapsed .menu-items {
  display: none;
}

/* Lock Icon for IB Section */
.menu-lock-icon {
  margin-left: 8px;
  font-size: 13px;
  color: white;
  opacity: 0.7;
}

.sidebar.collapsed .menu-lock-icon {
  display: none;
}

/* Locked Menu Section Styles */
.menu-section-locked {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 15px 20px;
  cursor: pointer;
  transition: background-color 0.2s ease;
  user-select: none;
}

.menu-section-locked:hover {
  background: rgba(255, 255, 255, 0.07);
}

.menu-section-locked.active {
  background: rgba(255, 255, 255, 0.1);
  box-shadow: inset 3px 0 0 var(--color-accent);
}

.sidebar.collapsed .menu-section-locked {
  justify-content: center;
  position: relative;
}

.sidebar.collapsed .menu-section-locked::after {
  content: attr(data-title);
  position: absolute;
  left: 76px;
  background: var(--color-sidebar-raised);
  color: white;
  padding: 8px 12px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  white-space: nowrap;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s ease;
  z-index: 1000;
}

.sidebar.collapsed .menu-section-locked:hover::after {
  opacity: 1;
}

/* Tooltip for collapsed sidebar */
.sidebar.collapsed .menu-section-header {
  position: relative;
  justify-content: center;
}

.sidebar.collapsed .menu-section-header::after {
  content: attr(data-title);
  position: absolute;
  left: 76px;
  background: var(--color-sidebar-raised);
  color: white;
  padding: 8px 12px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  white-space: nowrap;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s ease;
  z-index: 1000;
}

.sidebar.collapsed .menu-section-header:hover::after {
  opacity: 1;
}

/* 移动端遮罩层 */
.sidebar-overlay {
  display: none;
  border: 0;
}

/* 桌面端：隐藏移动端专用按钮 */
.mobile-only {
  display: none !important;
}

@media (max-width: 1000px) {
  /* 移动端：隐藏桌面折叠按钮，显示关闭按钮 */
  .desktop-only {
    display: none !important;
  }

  .mobile-only {
    display: flex !important;
  }

  .sidebar-overlay {
    display: block;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 199;
    animation: fadeIn 0.3s ease;
  }

  @keyframes fadeIn {
    from {
      opacity: 0;
    }
    to {
      opacity: 1;
    }
  }

  .sidebar {
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    min-height: 100vh;
    z-index: 200;
    width: min(86vw, 320px);
    transform: translateX(-100%);
    transition: transform 0.3s ease;
    box-shadow: none;
  }

  /* 移动端忽略 collapsed 状态，始终全宽 */
  .sidebar.collapsed {
    width: min(86vw, 320px);
    transform: translateX(-100%);
  }

  .sidebar.collapsed .menu-text,
  .sidebar.collapsed .menu-arrow {
    opacity: 1;
    display: inline;
  }

  .sidebar.collapsed .logo-container {
    opacity: 1;
    display: flex;
  }

  .sidebar.collapsed .menu-items {
    display: block;
  }

  .sidebar.collapsed .menu-item {
    padding-left: 56px;
    justify-content: flex-start;
  }

  .sidebar.collapsed .menu-section-header {
    justify-content: space-between;
  }

  .sidebar.collapsed .menu-section-header::after {
    display: none;
  }

  .sidebar.collapsed .toggle-sidebar-btn {
    margin: 0;
  }

  .sidebar.mobile-open {
    transform: translateX(0);
    box-shadow: 2px 0 20px rgba(0, 0, 0, 0.3);
  }

  .sidebar.collapsed.mobile-open {
    transform: translateX(0);
  }
}
</style>
