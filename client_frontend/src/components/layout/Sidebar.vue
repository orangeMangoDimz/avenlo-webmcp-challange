<template>
  <aside class="sidebar" :class="{ collapsed: isCollapsed }" id="sidebar">
    <div class="sidebar-header">
      <img :src="logoImage" alt="BOX Logo" class="sidebar-logo" />
      <button class="toggle-sidebar-btn" @click="toggleSidebar">
        <i class="fas fa-bars"></i>
      </button>
    </div>

    <!-- Client Section -->
    <div
      class="menu-section"
      :class="{ expanded: expandedSections.includes('client') }"
    >
      <div
        class="menu-section-header"
        data-title="Client"
        @click="toggleSection('client')"
      >
        <div class="menu-section-title">
          <span class="menu-icon"><i class="fas fa-users"></i></span>
          <span class="menu-text">Client</span>
        </div>
        <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
      </div>
      <div class="menu-items">
        <router-link to="/leads" class="menu-item">Leads</router-link>
        <a href="#" class="menu-item">Client List</a>
      </div>
    </div>

    <!-- Transaction Section -->
    <div
      class="menu-section"
      :class="{ expanded: expandedSections.includes('transaction') }"
    >
      <div
        class="menu-section-header"
        data-title="Transaction"
        @click="toggleSection('transaction')"
      >
        <div class="menu-section-title">
          <span class="menu-icon"><i class="fas fa-exchange-alt"></i></span>
          <span class="menu-text">Transaction</span>
        </div>
        <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
      </div>
      <div class="menu-items">
        <a href="#" class="menu-item">Deposit</a>
        <a href="#" class="menu-item">Withdraw</a>
        <a href="#" class="menu-item">Internal Transfer</a>
      </div>
    </div>

    <!-- KYC Section -->
    <div
      class="menu-section"
      :class="{ expanded: expandedSections.includes('kyc') }"
    >
      <div
        class="menu-section-header"
        data-title="KYC"
        @click="toggleSection('kyc')"
      >
        <div class="menu-section-title">
          <span class="menu-icon"><i class="fas fa-id-card"></i></span>
          <span class="menu-text">KYC</span>
        </div>
        <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
      </div>
      <div class="menu-items">
        <a href="#" class="menu-item">KYC List</a>
        <router-link to="/kyc-templates" class="menu-item" active-class="active"
          >KYC Templates</router-link
        >
        <router-link to="/kyc-settings" class="menu-item" active-class="active"
          >KYC Settings</router-link
        >
      </div>
    </div>

    <!-- IB Section -->
    <div
      class="menu-section"
      :class="{ expanded: expandedSections.includes('ib') }"
    >
      <div
        class="menu-section-header"
        data-title="IB"
        @click="toggleSection('ib')"
      >
        <div class="menu-section-title">
          <span class="menu-icon"><i class="fas fa-handshake"></i></span>
          <span class="menu-text">IB</span>
        </div>
        <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
      </div>
      <div class="menu-items">
        <a href="#" class="menu-item">IB List</a>
        <a href="#" class="menu-item">IB Rule</a>
      </div>
    </div>

    <!-- Report Section -->
    <div
      class="menu-section"
      :class="{ expanded: expandedSections.includes('report') }"
    >
      <div
        class="menu-section-header"
        data-title="Report"
        @click="toggleSection('report')"
      >
        <div class="menu-section-title">
          <span class="menu-icon"><i class="fas fa-chart-bar"></i></span>
          <span class="menu-text">Report</span>
        </div>
        <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
      </div>
      <div class="menu-items">
        <a href="#" class="menu-item">Funding Report</a>
      </div>
    </div>

    <!-- System Setting Section -->
    <div
      class="menu-section expanded"
      :class="{ expanded: expandedSections.includes('system') }"
    >
      <div
        class="menu-section-header"
        data-title="System Setting"
        @click="toggleSection('system')"
      >
        <div class="menu-section-title">
          <span class="menu-icon"><i class="fas fa-cog"></i></span>
          <span class="menu-text">System Setting</span>
        </div>
        <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
      </div>
      <div class="menu-items">
        <router-link to="/accounts" class="menu-item" active-class="active"
          >Account Management</router-link
        >
        <router-link
          to="/login-page-settings"
          class="menu-item"
          active-class="active"
          >Login Page Settings</router-link
        >
        <router-link to="/settings" class="menu-item" active-class="active"
          >System Settings</router-link
        >
      </div>
    </div>

    <!-- Journal Section -->
    <div
      class="menu-section"
      :class="{ expanded: expandedSections.includes('journal') }"
    >
      <div
        class="menu-section-header"
        data-title="Journal"
        @click="toggleSection('journal')"
      >
        <div class="menu-section-title">
          <span class="menu-icon"><i class="fas fa-book"></i></span>
          <span class="menu-text">Journal</span>
        </div>
        <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
      </div>
      <div class="menu-items">
        <a href="#" class="menu-item">Activity Log</a>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { ref, onMounted } from "vue";
import logoImage from "@/assets/image/logo_h.png";

const isCollapsed = ref(false);
const expandedSections = ref(["system"]);

const toggleSidebar = () => {
  isCollapsed.value = !isCollapsed.value;
  localStorage.setItem("sidebarCollapsed", isCollapsed.value);
};

const toggleSection = (section) => {
  if (isCollapsed.value) return;

  const index = expandedSections.value.indexOf(section);
  if (index > -1) {
    expandedSections.value.splice(index, 1);
  } else {
    expandedSections.value.push(section);
  }
};

onMounted(() => {
  const savedState = localStorage.getItem("sidebarCollapsed");
  if (savedState === "true") {
    isCollapsed.value = true;
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

.sidebar-logo {
  height: 42px;
  max-width: 200px;
  object-fit: contain;
  transition: opacity 0.3s ease;
}

.sidebar.collapsed .sidebar-logo {
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

.menu-item:hover {
  background: rgba(255, 255, 255, 0.1);
  color: white;
  padding-left: 60px;
}

.menu-item.active {
  background: var(--color-brand);
  color: white;
  font-weight: 600;
}

.sidebar.collapsed .menu-items {
  display: none;
}

@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    left: 0;
    top: 0;
    z-index: 100;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
  }
}
</style>
