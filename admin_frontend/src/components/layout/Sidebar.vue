<!-- eslint-disable vue/multi-word-component-names -->
<template>
  <aside
    id="sidebar"
    ref="sidebarRef"
    class="sidebar"
    :class="{ 'is-open': open }"
    tabindex="-1"
    :inert="!open"
    :aria-hidden="!open"
    :aria-modal="open || undefined"
    :role="open ? 'dialog' : undefined"
    aria-label="Primary navigation"
    @click="closeAfterNavigation"
    @keydown.tab.prevent="trapFocus"
  >
    <div class="sidebar-header">
      <div class="sidebar-brand" aria-label="Avenlo control center">
        <span class="sidebar-brand-mark" aria-hidden="true">A</span>
        <span class="sidebar-brand-copy">
          <strong>Avenlo</strong>
          <small>Control center</small>
        </span>
      </div>
      <button
        type="button"
        class="toggle-sidebar-btn"
        aria-label="Close navigation"
        @click="toggleSidebar"
      >
        <i class="fas fa-bars"></i>
      </button>
    </div>

    <nav class="sidebar-navigation" aria-label="Operations navigation">
      <p class="sidebar-navigation-label">
        {{ t("nav_navigation", "Navigation") }}
      </p>

      <!-- Client operations -->
      <div
        v-if="hasClientSectionPermission"
        class="menu-section"
        :class="{ expanded: expandedSections.includes('client') }"
      >
        <button
          type="button"
          class="menu-section-header"
          data-title="Client operations"
          :aria-expanded="expandedSections.includes('client')"
          @click="toggleSection('client')"
        >
          <div class="menu-section-title">
            <span class="menu-icon"><i class="fas fa-users"></i></span>
            <span class="menu-section-copy"
              ><span class="menu-text">{{
                t("nav_section_clients", "Client operations")
              }}</span
              ><small>{{
                t("nav_section_clients_hint", "Clients and prospects")
              }}</small></span
            >
          </div>
          <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
        </button>
        <div class="menu-items">
          <router-link
            v-if="authStore.hasPermission('page_clientslist_readonly')"
            to="/clients-list"
            class="menu-item"
            :class="{ active: route.name === 'client-detail' }"
            active-class="active"
          >
            {{ t("nav_clients", "Clients") }}
          </router-link>
          <router-link
            v-if="authStore.hasPermission('page_leads_readonly')"
            to="/leads"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_leads", "Leads") }}
          </router-link>
        </div>
      </div>

      <!-- Identity and compliance -->
      <div
        v-if="hasKycSectionPermission"
        class="menu-section"
        :class="{ expanded: expandedSections.includes('kyc') }"
      >
        <button
          type="button"
          class="menu-section-header"
          data-title="Identity and compliance"
          :aria-expanded="expandedSections.includes('kyc')"
          @click="toggleSection('kyc')"
        >
          <div class="menu-section-title">
            <span class="menu-icon"><i class="fas fa-id-card"></i></span>
            <span class="menu-section-copy"
              ><span class="menu-text">{{
                t("nav_section_compliance", "Identity & compliance")
              }}</span
              ><small>{{
                t("nav_section_compliance_hint", "Reviews and policy")
              }}</small></span
            >
          </div>
          <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
        </button>
        <div class="menu-items">
          <router-link
            v-if="authStore.hasPermission('page_kyclist_readonly')"
            to="/kyc-list"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_kyc_reviews", "KYC reviews") }}
          </router-link>
          <router-link
            v-if="authStore.hasPermission('page_kyctemplates_readonly')"
            to="/kyc-templates"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_kyc_templates", "KYC templates") }}
          </router-link>
          <router-link
            v-if="authStore.hasPermission('page_kycsettings_readonly')"
            to="/kyc-settings"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_kyc_configuration", "KYC configuration") }}
          </router-link>
          <!-- External KYC Settings 已合并到 /kyc-settings 页面，sidebar 不再独立列出 -->
        </div>
      </div>

      <!-- Money movement -->
      <div
        v-if="hasTransactionSectionPermission"
        class="menu-section"
        :class="{ expanded: expandedSections.includes('transaction') }"
      >
        <button
          type="button"
          class="menu-section-header"
          data-title="Money movement"
          :aria-expanded="expandedSections.includes('transaction')"
          @click="toggleSection('transaction')"
        >
          <div class="menu-section-title">
            <span class="menu-icon"><i class="fas fa-exchange-alt"></i></span>
            <span class="menu-section-copy"
              ><span class="menu-text">{{
                t("nav_section_money", "Money movement")
              }}</span
              ><small>{{
                t("nav_section_money_hint", "Funding and payouts")
              }}</small></span
            >
          </div>
          <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
        </button>
        <div class="menu-items">
          <router-link
            v-if="authStore.hasPermission('page_deposit_readonly')"
            to="/deposits"
            class="menu-item"
            active-class="active"
            >{{ t("nav_deposits", "Deposits") }}</router-link
          >
          <router-link
            v-if="authStore.hasPermission('page_withdraw_readonly')"
            to="/withdrawals"
            class="menu-item"
            active-class="active"
            >{{ t("nav_withdrawals", "Withdrawals") }}</router-link
          >
          <router-link
            v-if="authStore.hasPermission('page_internaltransfer_readonly')"
            to="/internal-transfers"
            class="menu-item"
            active-class="active"
            >{{
              t("nav_internal_transfers", "Internal transfers")
            }}</router-link
          >
          <router-link
            v-if="authStore.hasPermission('page_addressverification_readonly')"
            to="/address-verification"
            class="menu-item"
            active-class="active"
            >{{
              t("nav_address_verification", "Address verification")
            }}</router-link
          >
          <router-link
            v-if="authStore.hasPermission('page_transactionsettings_readonly')"
            to="/transaction-settings"
            class="menu-item"
            active-class="active"
            >{{
              t("nav_payment_configuration", "Payment configuration")
            }}</router-link
          >
          <router-link
            v-if="authStore.hasPermission('page_withdrawkyctemplates_readonly')"
            to="/withdraw-kyc-templates"
            class="menu-item"
            active-class="active"
            >{{
              t("nav_withdrawal_templates", "Withdrawal templates")
            }}</router-link
          >
        </div>
      </div>

      <!-- Partner network -->
      <div
        v-if="hasIbSectionPermission"
        class="menu-section"
        :class="{ expanded: expandedSections.includes('ib') }"
      >
        <button
          type="button"
          class="menu-section-header"
          data-title="Partner network"
          :aria-expanded="expandedSections.includes('ib')"
          @click="toggleSection('ib')"
        >
          <div class="menu-section-title">
            <span class="menu-icon"><i class="fas fa-handshake"></i></span>
            <span class="menu-section-copy"
              ><span class="menu-text">{{
                t("nav_section_partners", "Partner network")
              }}</span
              ><small>{{
                t("nav_section_partners_hint", "IB relationships")
              }}</small></span
            >
          </div>
          <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
        </button>
        <div class="menu-items">
          <router-link
            v-if="authStore.hasPermission('page_iblist_readonly')"
            to="/ib-list"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_partner_network", "Partner network") }}
          </router-link>
          <router-link
            v-if="authStore.hasPermission('page_ib_commission_order_readonly')"
            to="/ib-commission-order"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_commission_orders", "Commission orders") }}
          </router-link>
          <router-link
            v-if="authStore.hasPermission('page_ibreport_readonly')"
            to="/ib-report"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_partner_performance", "Partner performance") }}
          </router-link>
          <router-link
            v-if="authStore.hasPermission('page_ib_settings_readonly')"
            to="/ib-settings"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_partner_configuration", "Partner configuration") }}
          </router-link>
          <router-link
            v-if="authStore.hasPermission('page_ib_initial_review_readonly')"
            to="/ib-initial-review"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_initial_review", "Initial review") }}
          </router-link>
          <router-link
            v-if="authStore.hasPermission('page_ib_risk_review_readonly')"
            to="/ib-risk-review"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_risk_review", "Risk review") }}
          </router-link>
          <router-link
            v-if="authStore.hasPermission('page_ib_final_review_readonly')"
            to="/ib-final-review"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_final_approval", "Final approval") }}
          </router-link>
        </div>
      </div>

      <!-- Sales operations -->
      <div
        v-if="hasSalesSectionPermission"
        class="menu-section"
        :class="{ expanded: expandedSections.includes('sales') }"
      >
        <button
          type="button"
          class="menu-section-header"
          data-title="Sales operations"
          :aria-expanded="expandedSections.includes('sales')"
          @click="toggleSection('sales')"
        >
          <div class="menu-section-title">
            <span class="menu-icon"><i class="fas fa-user-tie"></i></span>
            <span class="menu-section-copy"
              ><span class="menu-text">{{
                t("nav_section_sales", "Sales operations")
              }}</span
              ><small>{{
                t("nav_section_sales_hint", "Team performance")
              }}</small></span
            >
          </div>
          <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
        </button>
        <div class="menu-items">
          <router-link
            v-if="authStore.hasPermission('page_dailyreport_readonly')"
            to="/daily-report"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_daily_performance", "Daily performance") }}
          </router-link>
          <router-link
            v-if="isSalesManagement"
            to="/sales-list"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_sales_team", "Sales team") }}
          </router-link>
          <router-link
            v-if="isSales"
            to="/sales-dashboard"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_sales_workspace", "Sales workspace") }}
          </router-link>
          <router-link
            v-if="hasSalesSectionPermission"
            to="/ib-link"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_partner_links", "Partner links") }}
          </router-link>
        </div>
      </div>

      <!-- Analytics -->
      <div
        v-if="hasReportSectionPermission"
        class="menu-section"
        :class="{ expanded: expandedSections.includes('report') }"
      >
        <button
          type="button"
          class="menu-section-header"
          data-title="Analytics"
          :aria-expanded="expandedSections.includes('report')"
          @click="toggleSection('report')"
        >
          <div class="menu-section-title">
            <span class="menu-icon"><i class="fas fa-chart-bar"></i></span>
            <span class="menu-section-copy"
              ><span class="menu-text">{{
                t("nav_section_analytics", "Analytics")
              }}</span
              ><small>{{
                t("nav_section_analytics_hint", "Performance insights")
              }}</small></span
            >
          </div>
          <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
        </button>
        <div class="menu-items">
          <router-link
            v-if="authStore.hasPermission('page_fundingreport_readonly')"
            to="/funding-report"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_funding_analytics", "Funding analytics") }}
          </router-link>
          <router-link
            v-if="authStore.hasPermission('page_ibreport_readonly')"
            to="/ib-report"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_partner_analytics", "Partner analytics") }}
          </router-link>
          <router-link
            v-if="authStore.hasPermission('page_ibstatement_readonly')"
            to="/ib-statement"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_partner_statements", "Partner statements") }}
          </router-link>
          <router-link
            v-if="authStore.hasPermission('page_operationlogreport_readonly')"
            to="/operation-log-report"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_activity_audit", "Activity audit") }}
          </router-link>
          <router-link
            v-if="authStore.hasPermission('page_fundingreport_readonly')"
            to="/custom-report"
            class="menu-item"
            :class="{ active: isCustomReportRoute }"
            active-class="active"
          >
            {{ t("nav_custom_reports", "Custom reports") }}
          </router-link>
        </div>
      </div>

      <!-- Administration -->
      <div
        v-if="hasSystemSettingSectionPermission"
        class="menu-section"
        :class="{ expanded: expandedSections.includes('system') }"
      >
        <button
          type="button"
          class="menu-section-header"
          data-title="Administration"
          :aria-expanded="expandedSections.includes('system')"
          @click="toggleSection('system')"
        >
          <div class="menu-section-title">
            <span class="menu-icon"><i class="fas fa-cog"></i></span>
            <span class="menu-section-copy"
              ><span class="menu-text">{{
                t("nav_section_administration", "Administration")
              }}</span
              ><small>{{
                t("nav_section_administration_hint", "Workspace controls")
              }}</small></span
            >
          </div>
          <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
        </button>
        <div class="menu-items">
          <router-link
            v-if="authStore.hasPermission('page_accountmanagement_readonly')"
            to="/accounts"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_user_accounts", "User accounts") }}
          </router-link>
          <router-link
            v-if="authStore.hasPermission('page_rolemanagement_readonly')"
            to="/role-management"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_roles_access", "Roles & access") }}
          </router-link>
          <router-link
            v-if="authStore.hasPermission('page_loginpagesettings_readonly')"
            to="/login-page-settings"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_login_experience", "Login experience") }}
          </router-link>
          <router-link
            v-if="authStore.hasPermission('page_platformsettings_readonly')"
            to="/platform-settings"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_platform_configuration", "Platform configuration") }}
          </router-link>
          <!--        <router-link to="/settings" class="menu-item" active-class="active">System Settings</router-link>-->
          <router-link
            v-if="authStore.hasPermission('page_clienttickets_readonly')"
            to="/client-tickets"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_client_support", "Client support") }}
          </router-link>
          <router-link
            v-if="authStore.hasPermission('page_emailtemplates_readonly')"
            to="/email-templates"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_email_templates", "Email templates") }}
          </router-link>
          <router-link
            v-if="authStore.hasPermission('page_emailsettings_readonly')"
            to="/email-settings"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_email_delivery", "Email delivery") }}
          </router-link>
          <router-link
            v-if="
              authStore.hasPermission('page_logsettings_readonly') ||
              authStore.hasPermission('page_logsettings_edit')
            "
            to="/log-settings"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_audit_configuration", "Audit configuration") }}
          </router-link>
        </div>
      </div>

      <div
        v-if="showDeveloperSettings"
        class="menu-section"
        :class="{ expanded: expandedSections.includes('developer') }"
      >
        <button
          type="button"
          class="menu-section-header"
          data-title="Developer tools"
          :aria-expanded="expandedSections.includes('developer')"
          @click="toggleSection('developer')"
        >
          <div class="menu-section-title">
            <span class="menu-icon"><i class="fas fa-code"></i></span>
            <span class="menu-section-copy"
              ><span class="menu-text">{{
                t("nav_section_developer", "Developer tools")
              }}</span
              ><small>{{
                t("nav_section_developer_hint", "Non-production controls")
              }}</small></span
            >
          </div>
          <span class="menu-arrow"><i class="fas fa-chevron-down"></i></span>
        </button>
        <div class="menu-items">
          <router-link
            to="/developer-settings"
            class="menu-item"
            active-class="active"
          >
            {{ t("nav_feature_controls", "Feature controls") }}
          </router-link>
        </div>
      </div>
    </nav>
  </aside>
</template>

<script setup>
import { ref, onMounted, computed, nextTick, watch } from "vue";
import { useRoute } from "vue-router";
import { useMenuSettingsStore } from "@/stores/menuSettings";
import { useAuthStore } from "@/stores/auth";
import { useAdminI18n } from "@/composables/useAdminI18n";
import api from "@/services/api";

const { t } = useAdminI18n();

const route = useRoute();
const menuSettingsStore = useMenuSettingsStore();
const authStore = useAuthStore();

const props = defineProps({
  open: Boolean,
});
const emit = defineEmits(["close"]);

const sidebarRef = ref(null);
const expandedSections = ref([]);
const showDeveloperSettings = ref(false);

// 检查 Client 分组是否有任何页面权限
const hasClientSectionPermission = computed(() => {
  return (
    authStore.hasPermission("page_leads_readonly") ||
    authStore.hasPermission("page_clientslist_readonly")
  );
});

// 检查 Transaction 分组是否有任何页面权限
const hasTransactionSectionPermission = computed(() => {
  return (
    authStore.hasPermission("page_deposit_readonly") ||
    authStore.hasPermission("page_withdraw_readonly") ||
    authStore.hasPermission("page_addressverification_readonly") ||
    authStore.hasPermission("page_withdrawkyctemplates_readonly") ||
    authStore.hasPermission("page_internaltransfer_readonly") ||
    authStore.hasPermission("page_transactionsettings_readonly")
  );
});

// 检查 KYC 分组是否有任何页面权限
const hasKycSectionPermission = computed(() => {
  return (
    authStore.hasPermission("page_kyclist_readonly") ||
    authStore.hasPermission("page_kyctemplates_readonly") ||
    authStore.hasPermission("page_kycsettings_readonly") ||
    authStore.hasPermission("page_externalkycsettings_readonly") ||
    authStore.hasPermission("page_externalkycsettings_edit")
  );
});

// 检查 IB 分组是否有任何页面权限
const hasIbSectionPermission = computed(() => {
  return (
    authStore.hasPermission("page_iblist_readonly") ||
    authStore.hasPermission("page_ib_initial_review_readonly") ||
    authStore.hasPermission("page_ib_risk_review_readonly") ||
    authStore.hasPermission("page_ib_final_review_readonly") ||
    authStore.hasPermission("page_ib_commission_order_readonly") ||
    authStore.hasPermission("page_ibreport_readonly") ||
    authStore.hasPermission("page_ib_settings_readonly")
  );
});

// 销售管理 = Sales Manager 角色(5) 或 page_saleslist_view；销售 = Sales 角色(6) 或 page_salesdashboard_view
const SALES_MANAGER_ROLE_ID = 5;
const SALES_ROLE_ID = 6;
const isSalesManagement = computed(() => {
  const roleId = authStore.user?.roleId;
  return (
    roleId === SALES_MANAGER_ROLE_ID ||
    authStore.hasPermission("page_saleslist_view")
  );
});
const isSales = computed(() => {
  const roleId = authStore.user?.roleId;
  return (
    roleId === SALES_ROLE_ID ||
    authStore.hasPermission("page_salesdashboard_view")
  );
});
const hasSalesSectionPermission = computed(
  () =>
    isSalesManagement.value ||
    isSales.value ||
    authStore.hasPermission("page_dailyreport_readonly"),
);

// 检查 Report 分组是否有任何页面权限
const hasReportSectionPermission = computed(() => {
  return (
    authStore.hasPermission("page_fundingreport_readonly") ||
    authStore.hasPermission("page_ibreport_readonly") ||
    authStore.hasPermission("page_ibstatement_readonly") ||
    authStore.hasPermission("page_operationlogreport_readonly")
  );
});

const isCustomReportRoute = computed(() =>
  String(route.path || "").startsWith("/custom-report"),
);

// 检查 System Setting 分组是否有任何页面权限
const hasSystemSettingSectionPermission = computed(() => {
  return (
    authStore.hasPermission("page_accountmanagement_readonly") ||
    authStore.hasPermission("page_rolemanagement_readonly") ||
    authStore.hasPermission("page_loginpagesettings_readonly") ||
    authStore.hasPermission("page_platformsettings_readonly") ||
    authStore.hasPermission("page_clienttickets_readonly") ||
    authStore.hasPermission("page_emailtemplates_readonly") ||
    authStore.hasPermission("page_emailsettings_readonly") ||
    authStore.hasPermission("page_logsettings_readonly") ||
    authStore.hasPermission("page_logsettings_edit")
  );
});

const toggleSidebar = () => {
  emit("close");
};

const closeAfterNavigation = (event) => {
  if (event.target.closest("a")) emit("close");
};

const toggleSection = (section) => {
  const index = expandedSections.value.indexOf(section);
  if (index > -1) {
    expandedSections.value.splice(index, 1);
  } else {
    expandedSections.value.push(section);
  }
};

const isNonProductionEnv = (env) => env === "dev" || env === "staging";

const loadDeveloperSettings = async () => {
  try {
    const res = await api.get("/developer-settings");
    const payload = res.data || {};
    showDeveloperSettings.value = isNonProductionEnv(payload.environment);
  } catch {
    showDeveloperSettings.value = false;
  }
};

const expandDeveloperSectionIfNeeded = () => {
  const path = String(route.path || "");
  const sectionByPath = [
    ["client", ["/clients-list", "/client-detail", "/leads"]],
    ["kyc", ["/kyc-list", "/kyc-templates", "/kyc-settings"]],
    [
      "transaction",
      [
        "/deposits",
        "/withdrawals",
        "/internal-transfers",
        "/address-verification",
        "/transaction-settings",
        "/withdraw-kyc-templates",
      ],
    ],
    [
      "ib",
      [
        "/ib-list",
        "/ib-commission-order",
        "/ib-initial-review",
        "/ib-risk-review",
        "/ib-final-review",
        "/ib-settings",
      ],
    ],
    ["sales", ["/daily-report", "/sales-list", "/sales-dashboard", "/ib-link"]],
    [
      "report",
      [
        "/funding-report",
        "/ib-report",
        "/ib-statement",
        "/operation-log-report",
        "/custom-report",
      ],
    ],
    [
      "system",
      [
        "/accounts",
        "/role-management",
        "/login-page-settings",
        "/platform-settings",
        "/client-tickets",
        "/email-templates",
        "/email-settings",
        "/log-settings",
      ],
    ],
    ["developer", ["/developer-settings"]],
  ];
  const activeSection = sectionByPath.find(([, paths]) =>
    paths.some((prefix) => path.startsWith(prefix)),
  )?.[0];
  if (activeSection && !expandedSections.value.includes(activeSection)) {
    expandedSections.value.push(activeSection);
  }
};

watch(
  () => route.path,
  () => {
    emit("close");
    expandDeveloperSectionIfNeeded();
  },
  { immediate: true },
);

watch(
  () => props.open,
  async (isOpen) => {
    if (!isOpen) return;
    await nextTick();
    getFocusableElements()[0]?.focus();
  },
);

const getFocusableElements = () =>
  Array.from(
    sidebarRef.value?.querySelectorAll(
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ) || [],
  ).filter(
    (element) => !element.closest(".menu-section:not(.expanded) .menu-items"),
  );

const trapFocus = (event) => {
  const elements = getFocusableElements();
  if (!elements.length) return;
  const currentIndex = elements.indexOf(document.activeElement);
  const nextIndex = event.shiftKey
    ? currentIndex <= 0
      ? elements.length - 1
      : currentIndex - 1
    : currentIndex === elements.length - 1
      ? 0
      : currentIndex + 1;
  elements[nextIndex].focus();
};

onMounted(async () => {
  await menuSettingsStore.loadSettings();
  await loadDeveloperSettings();
  expandDeveloperSectionIfNeeded();
});
</script>

<style scoped>
.sidebar {
  width: 264px;
  flex-shrink: 0;
  background: var(--color-sidebar);
  color: white;
  height: 100vh;
  position: sticky;
  top: 0;
  border-right: 1px solid rgba(255, 255, 255, 0.08);
  transition: width var(--transition-fast);
  overflow-y: auto;
  overflow-x: hidden;
  z-index: 100;
  outline: 0;
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

.menu-section {
  margin: 3px 10px;
}

.menu-section-header {
  width: 100%;
  border: 0;
  color: inherit;
  background: transparent;
  font: inherit;
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

.menu-item:hover {
  background: rgba(255, 255, 255, 0.07);
  color: white;
}

.menu-item.active,
.menu-item.router-link-active {
  background: rgba(255, 255, 255, 0.1);
  box-shadow: inset 3px 0 0 var(--color-accent);
  color: white;
  font-weight: 600;
}

.sidebar.collapsed .menu-items {
  display: none;
}

@media (max-width: 1023px) {
  .sidebar {
    position: fixed;
    left: 0;
    top: 0;
    z-index: 110;
    width: min(320px, 88vw);
    transform: translateX(-100%);
    transition: transform var(--transition-fast);
    box-shadow: none;
  }

  .sidebar.is-mobile-open {
    transform: translateX(0);
  }

  .sidebar.collapsed {
    width: min(320px, 88vw);
  }

  .sidebar.collapsed .logo-container,
  .sidebar.collapsed .menu-text {
    display: flex;
    opacity: 1;
  }

  .sidebar.collapsed .menu-arrow {
    opacity: 1;
  }

  .sidebar.collapsed .menu-items {
    display: block;
  }
}
</style>

<style scoped>
.sidebar {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.sidebar-header {
  min-height: 82px;
  padding: 16px 18px;
  background: linear-gradient(
    135deg,
    var(--color-sidebar-raised),
    var(--color-sidebar)
  );
}

.sidebar-brand {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 11px;
}

.sidebar-brand-mark {
  display: inline-grid;
  width: 36px;
  height: 36px;
  flex: 0 0 36px;
  place-items: center;
  color: #fff;
  background: linear-gradient(
    145deg,
    var(--color-brand),
    var(--color-brand-strong)
  );
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 10px 4px 10px 4px;
  box-shadow: 0 8px 18px rgba(4, 10, 22, 0.24);
  font-family: var(--font-display);
  font-size: 19px;
  font-weight: 800;
  letter-spacing: -0.08em;
}

.sidebar-brand-copy {
  display: grid;
  min-width: 0;
  gap: 2px;
  line-height: 1;
}

.sidebar-brand-copy strong {
  color: #fff;
  font-size: 15px;
  font-weight: 750;
  letter-spacing: 0.01em;
}

.sidebar-brand-copy small {
  color: var(--color-muted);
  font-size: 10px;
  font-weight: 650;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.toggle-sidebar-btn {
  width: 36px;
  height: 36px;
  flex: 0 0 36px;
  border-radius: 8px;
}

.sidebar-navigation {
  flex: 1;
  min-height: 0;
  padding: 14px 12px 20px;
  overflow-y: auto;
  overscroll-behavior: contain;
}

.sidebar-navigation-label {
  margin: 0 8px 9px;
  color: var(--color-faint);
  font-size: 10px;
  font-weight: 750;
  letter-spacing: 0.13em;
  text-transform: uppercase;
}

.menu-section {
  margin: 0 0 6px;
}

.menu-section-header {
  min-height: 56px;
  padding: 8px;
  border: 1px solid transparent;
  border-radius: 8px;
}

.menu-section-header:hover {
  background: rgba(255, 255, 255, 0.045);
  border-color: rgba(255, 255, 255, 0.075);
}

.menu-section-title {
  min-width: 0;
  gap: 10px;
}

.menu-icon {
  display: inline-grid;
  width: 34px;
  min-width: 34px;
  height: 34px;
  place-items: center;
  color: var(--color-accent);
  background: rgba(255, 255, 255, 0.035);
  border: 1px solid rgba(255, 255, 255, 0.065);
  border-radius: 8px;
  font-size: 14px;
}

.menu-section-copy {
  display: grid;
  min-width: 0;
  gap: 3px;
}

.menu-text {
  color: var(--color-ink);
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.005em;
}

.menu-section-copy small {
  overflow: hidden;
  color: var(--color-muted);
  font-size: 10px;
  font-weight: 500;
  line-height: 1.2;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.menu-arrow {
  display: inline-grid;
  width: 24px;
  height: 24px;
  flex: 0 0 24px;
  place-items: center;
  color: var(--color-muted);
  border-radius: 6px;
  font-size: 10px;
}

.menu-section-header:hover .menu-arrow {
  color: var(--color-ink);
  background: rgba(255, 255, 255, 0.06);
}

.menu-items {
  max-height: 0;
  margin: 0 0 8px 24px;
  padding-left: 17px;
  border-left: 1px solid var(--color-border-strong);
}

.menu-section.expanded .menu-items {
  max-height: 820px;
}

.menu-item {
  position: relative;
  min-height: 34px;
  margin: 2px 0;
  padding: 8px 10px;
  color: var(--color-muted);
  border: 1px solid transparent;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 550;
}

.menu-item::before {
  position: absolute;
  top: 50%;
  left: -22px;
  width: 5px;
  height: 5px;
  content: "";
  background: var(--color-border-strong);
  border-radius: 999px;
  transform: translateY(-50%);
}

.menu-item:hover {
  color: var(--color-ink);
  background: rgba(255, 255, 255, 0.05);
  border-color: rgba(255, 255, 255, 0.06);
}

.menu-item.active,
.menu-item.router-link-active {
  color: #fff;
  background: var(--color-brand-solid);
  border-color: color-mix(in srgb, var(--color-brand) 58%, transparent);
  box-shadow: none;
  font-weight: 700;
}

.menu-item.active::before,
.menu-item.router-link-active::before {
  width: 7px;
  height: 7px;
  background: var(--color-accent);
  box-shadow: 0 0 0 3px var(--color-sidebar);
}

.menu-section-header:focus-visible,
.menu-item:focus-visible,
.toggle-sidebar-btn:focus-visible {
  outline: 2px solid var(--color-brand);
  outline-offset: 2px;
}

@media (max-width: 1023px) {
  .sidebar-navigation {
    padding-bottom: 28px;
  }
}
</style>
