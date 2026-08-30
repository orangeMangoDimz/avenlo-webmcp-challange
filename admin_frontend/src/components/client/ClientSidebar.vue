<template>
  <aside :class="['sidebar', { collapsed: isCollapsed }]" id="clientSidebar">
    <div class="sidebar-header">
      <h3><i class="fas fa-chart-line"></i> {{ branding.logoText }} Client</h3>
      <button class="toggle-sidebar-btn" @click="toggleSidebar">
        <i class="fas fa-bars"></i>
      </button>
    </div>

    <!-- Dashboard Section -->
    <div class="menu-section" :class="{ expanded: expandedSections.dashboard }">
      <div
        class="menu-section-header"
        data-title="Dashboard"
        @click="toggleSection('dashboard')"
      >
        <div class="menu-section-title">
          <span class="menu-icon"><i class="fas fa-home"></i></span>
          <span class="menu-text">Dashboard</span>
        </div>
        <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
      </div>
      <div class="menu-items">
        <router-link to="/client/dashboard" class="menu-item"
          >Overview</router-link
        >
        <router-link to="/client/account-summary" class="menu-item"
          >Account Summary</router-link
        >
      </div>
    </div>

    <!-- Trading Section -->
    <div class="menu-section" :class="{ expanded: expandedSections.trading }">
      <div
        class="menu-section-header"
        data-title="Trading"
        @click="toggleSection('trading')"
      >
        <div class="menu-section-title">
          <span class="menu-icon"><i class="fas fa-chart-bar"></i></span>
          <span class="menu-text">Trading</span>
        </div>
        <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
      </div>
      <div class="menu-items">
        <router-link to="/client/trading" class="menu-item"
          >Trading Platform</router-link
        >
        <router-link to="/client/positions" class="menu-item"
          >Open Positions</router-link
        >
        <router-link to="/client/orders" class="menu-item"
          >Order History</router-link
        >
      </div>
    </div>

    <!-- Accounts Section -->
    <div class="menu-section" :class="{ expanded: expandedSections.accounts }">
      <div
        class="menu-section-header"
        data-title="Accounts"
        @click="toggleSection('accounts')"
      >
        <div class="menu-section-title">
          <span class="menu-icon"><i class="fas fa-wallet"></i></span>
          <span class="menu-text">Accounts</span>
        </div>
        <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
      </div>
      <div class="menu-items">
        <router-link to="/client/accounts" class="menu-item"
          >My Accounts</router-link
        >
        <router-link to="/client/accounts/new" class="menu-item"
          >Open New Account</router-link
        >
      </div>
    </div>

    <!-- Transactions Section -->
    <div
      class="menu-section"
      :class="{ expanded: expandedSections.transactions }"
    >
      <div
        class="menu-section-header"
        data-title="Transactions"
        @click="toggleSection('transactions')"
      >
        <div class="menu-section-title">
          <span class="menu-icon"><i class="fas fa-exchange-alt"></i></span>
          <span class="menu-text">Transactions</span>
        </div>
        <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
      </div>
      <div class="menu-items">
        <router-link to="/client/deposit" class="menu-item"
          >Deposit</router-link
        >
        <router-link to="/client/withdraw" class="menu-item"
          >Withdraw</router-link
        >
        <router-link to="/client/transfer" class="menu-item"
          >Internal Transfer</router-link
        >
        <router-link to="/client/transactions" class="menu-item"
          >Transaction History</router-link
        >
      </div>
    </div>

    <!-- Documents Section -->
    <div class="menu-section" :class="{ expanded: expandedSections.documents }">
      <div
        class="menu-section-header"
        data-title="Documents"
        @click="toggleSection('documents')"
      >
        <div class="menu-section-title">
          <span class="menu-icon"><i class="fas fa-file-alt"></i></span>
          <span class="menu-text">Documents</span>
        </div>
        <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
      </div>
      <div class="menu-items">
        <router-link to="/client/documents" class="menu-item"
          >My Documents</router-link
        >
        <router-link to="/client/statements" class="menu-item"
          >Statements</router-link
        >
        <router-link to="/client/reports" class="menu-item"
          >Reports</router-link
        >
      </div>
    </div>
  </aside>
</template>

<script setup>
import { ref, onMounted } from "vue";
import brandingApi from "@/services/brandingApi";

const isCollapsed = ref(false);
const expandedSections = ref({
  dashboard: false,
  trading: false,
  accounts: false,
  transactions: false,
  documents: true, // Default expanded for documents page
});

const branding = ref({
  logoText: "CRM",
});

const toggleSidebar = () => {
  isCollapsed.value = !isCollapsed.value;
  localStorage.setItem("clientSidebarCollapsed", isCollapsed.value);
};

const toggleSection = (section) => {
  if (isCollapsed.value) return;
  expandedSections.value[section] = !expandedSections.value[section];
};

onMounted(async () => {
  // Restore sidebar state
  const collapsed = localStorage.getItem("clientSidebarCollapsed");
  if (collapsed === "true") {
    isCollapsed.value = true;
  }
  // Load branding configuration
  try {
    const config = await brandingApi.getBranding();
    branding.value = {
      logoText: config.logoText || "CRM",
    };
  } catch (error) {
    console.error("Failed to load branding:", error);
  }
});
</script>

<style scoped>
.sidebar {
  width: 280px;
  background: var(--color-ink);
  color: white;
  height: 100vh;
  position: sticky;
  top: 0;
  transition: all 0.3s ease;
  overflow-y: auto;
  overflow-x: hidden;
  z-index: 100;
}

.sidebar.collapsed {
  width: 70px;
}

.sidebar::-webkit-scrollbar {
  width: 6px;
}

.sidebar::-webkit-scrollbar-track {
  background: var(--color-ink-strong);
}

.sidebar::-webkit-scrollbar-thumb {
  background: var(--color-text);
  border-radius: 3px;
}

.sidebar-header {
  padding: 25px 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: rgba(0, 0, 0, 0.2);
}

.sidebar-header h3 {
  font-size: 18px;
  font-weight: 700;
  white-space: nowrap;
  transition: opacity 0.3s ease;
  letter-spacing: 0.5px;
}

.sidebar-header h3 i {
  margin-right: 10px;
  color: var(--color-brand);
}

.sidebar.collapsed .sidebar-header h3 {
  opacity: 0;
  display: none;
}

.toggle-sidebar-btn {
  background: rgba(255, 255, 255, 0.1);
  border: none;
  color: white;
  width: 32px;
  height: 32px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
  font-size: 18px;
}

.toggle-sidebar-btn:hover {
  background: rgba(255, 255, 255, 0.2);
}

.sidebar.collapsed .toggle-sidebar-btn {
  margin: 0 auto;
}

.menu-section {
  margin-bottom: 5px;
}

.menu-section-header {
  padding: 15px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
  transition: all 0.2s ease;
  user-select: none;
}

.menu-section-header:hover {
  background: rgba(255, 255, 255, 0.05);
}

.menu-section-title {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
}

.menu-icon {
  font-size: 20px;
  min-width: 24px;
  text-align: center;
}

.menu-text {
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
  transition: opacity 0.3s ease;
}

.sidebar.collapsed .menu-text {
  opacity: 0;
  display: none;
}

.menu-arrow {
  /* @font-floor-exempt: visual-only navigation glyph */
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
  background: rgba(0, 0, 0, 0.2);
}

.menu-section.expanded .menu-items {
  max-height: 500px;
}

.menu-item {
  padding: 12px 20px 12px 56px;
  display: flex;
  align-items: center;
  color: rgba(255, 255, 255, 0.8);
  text-decoration: none;
  transition: all 0.2s ease;
  font-size: 14px;
  cursor: pointer;
}

.sidebar.collapsed .menu-item {
  padding-left: 20px;
  justify-content: center;
}

.menu-item:hover {
  background: rgba(255, 255, 255, 0.1);
  color: white;
  padding-left: 60px;
}

.sidebar.collapsed .menu-item:hover {
  padding-left: 20px;
}

.menu-item.router-link-active {
  background: var(--color-brand-solid);
  color: white;
  font-weight: 600;
}

.sidebar.collapsed .menu-items {
  display: none;
}

/* Tooltip for collapsed sidebar */
.sidebar.collapsed .menu-section-header {
  position: relative;
  justify-content: center;
}

.sidebar.collapsed .menu-section-header::after {
  content: attr(data-title);
  position: absolute;
  left: 70px;
  background: var(--color-text);
  color: white;
  padding: 8px 12px;
  border-radius: var(--radius-sm);
  font-size: 14px;
  white-space: nowrap;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s ease;
  z-index: 1000;
}

.sidebar.collapsed .menu-section-header:hover::after {
  opacity: 1;
}

@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    left: 0;
    top: 0;
    z-index: 100;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
  }

  .sidebar:not(.collapsed) {
    width: 280px;
  }
}
</style>
