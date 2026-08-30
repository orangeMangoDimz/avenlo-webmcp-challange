import { createRouter, createWebHashHistory } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { useClientAuthStore } from "@/stores/clientAuth";
import { useMenuSettingsStore } from "@/stores/menuSettings";

const router = createRouter({
  history: createWebHashHistory(import.meta.env.BASE_URL),
  routes: [
    // Admin Login
    {
      path: "/login",
      name: "login",
      component: () => import("@/views/AdminLogin.vue"),
      meta: { requiresAuth: false },
    },
    // Admin Password Reset
    {
      path: "/reset-password",
      name: "reset-password",
      component: () => import("@/views/AdminResetPassword.vue"),
      meta: { requiresAuth: false },
    },
    // Admin Portal
    {
      path: "/",
      component: () => import("@/layouts/MainLayout.vue"),
      meta: { requiresAuth: true },
      children: [
        {
          path: "",
          redirect: "/clients-list",
        },
        {
          path: "accounts",
          name: "accounts",
          component: () => import("@/views/AdminAccounts.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "role-management",
          name: "role-management",
          component: () => import("@/views/RoleManagement.vue"),
          meta: {
            requiresAuth: true,
            permissionKey: "page_rolemanagement_readonly",
          },
        },
        {
          path: "leads",
          name: "leads",
          component: () => import("@/views/Leads.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "settings",
          name: "settings",
          component: () => import("@/views/Settings.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "login-page-settings",
          name: "login-page-settings",
          component: () => import("@/views/LoginPageSettings.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "platform-settings",
          name: "platform-settings",
          component: () => import("@/views/PlatformSettings.vue"),
          meta: {
            requiresAuth: true,
            permissionKey: "page_platformsettings_readonly",
          },
        },
        {
          path: "kyc-templates",
          name: "kyc-templates",
          component: () => import("@/views/KYCTemplateList.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "kyc-settings",
          name: "kyc-settings",
          component: () => import("@/views/KYCSettings.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "external-kyc-settings",
          name: "external-kyc-settings",
          component: () => import("@/views/ExternalKycSettings.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "kyc-list",
          name: "kyc-list",
          component: () => import("@/views/KYCList.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "clients-list",
          name: "clients-list",
          component: () => import("@/views/ClientsList.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "client-detail",
          name: "client-detail",
          component: () => import("@/views/ClientDetail.vue"),
          meta: {
            requiresAuth: true,
            permissionKeys: [
              "page_clientsdetail_profile",
              "page_clientsdetail_trading",
              "page_clientsdetail_document",
              "page_clientsdetail_funding",
              "page_clientsdetail_payment",
              "page_clientsdetail_ib",
              "page_clientsdetail_ib_referal",
              "page_clientsdetail_sales",
            ],
          },
        },
        {
          path: "ib-list",
          name: "ib-list",
          component: () => import("@/views/IbList.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "transaction-settings",
          name: "transaction-settings",
          component: () => import("@/views/TransactionSettings.vue"),
          meta: {
            requiresAuth: true,
            permissionKey: "page_transactionsettings_readonly",
          },
        },
        {
          path: "address-verification",
          name: "address-verification",
          component: () => import("@/views/AddressVerificationList.vue"),
          meta: {
            requiresAuth: true,
            permissionKey: "page_addressverification_readonly",
          },
        },
        {
          path: "deposits",
          name: "deposits",
          component: () => import("@/views/DepositManagement.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "withdrawals",
          name: "withdrawals",
          component: () => import("@/views/WithdrawalManagement.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "withdraw-kyc-templates",
          name: "withdraw-kyc-templates",
          component: () => import("@/views/WithdrawKYCTemplateList.vue"),
          meta: {
            requiresAuth: true,
            permissionKey: "page_withdrawkyctemplates_readonly",
          },
        },
        {
          path: "internal-transfers",
          name: "internal-transfers",
          component: () => import("@/views/InternalTransferManagement.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "funding-report",
          name: "funding-report",
          component: () => import("@/views/FundingReport.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "ib-report",
          name: "ib-report",
          component: () => import("@/views/IBReport.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "ib-statement",
          name: "ib-statement",
          component: () => import("@/views/IbStatementReport.vue"),
          meta: {
            requiresAuth: true,
            permissionKey: "page_ibstatement_readonly",
          },
        },
        // 日志报表（操作日志查询）
        {
          path: "operation-log-report",
          name: "operation-log-report",
          component: () => import("@/views/OperationLogReport.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "custom-report",
          name: "custom-report",
          component: () => import("@/views/CustomReport.vue"),
          meta: {
            requiresAuth: true,
            permissionKey: "page_fundingreport_readonly",
          },
        },
        {
          path: "custom-report/coverage",
          name: "custom-report-coverage",
          component: () => import("@/views/CustomReportStacked.vue"),
          meta: {
            requiresAuth: true,
            permissionKey: "page_fundingreport_readonly",
          },
        },
        {
          path: "custom-report/:reportId",
          name: "custom-report-detail",
          component: () => import("@/views/CustomReport.vue"),
          meta: {
            requiresAuth: true,
            permissionKey: "page_fundingreport_readonly",
          },
        },
        {
          path: "custom-report/:reportId/widget/:widgetId",
          name: "custom-report-widget",
          component: () => import("@/views/CustomReportWidget.vue"),
          meta: {
            requiresAuth: true,
            permissionKey: "page_fundingreport_readonly",
          },
        },
        {
          path: "ib-settings",
          name: "ib-settings",
          component: () => import("@/views/IbSettings.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "ib-applications",
          name: "ib-applications",
          component: () => import("@/views/IbApplications.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "ib-initial-review",
          name: "ib-initial-review",
          component: () => import("@/views/IbInitialReviewList.vue"),
          meta: { requiresAuth: true },
        },
        {
          path: "ib-risk-review",
          name: "ib-risk-review",
          component: () => import("@/views/IbRiskReviewList.vue"),
          meta: { requiresAuth: true },
        },
        {
          path: "ib-final-review",
          name: "ib-final-review",
          component: () => import("@/views/IbFinalReviewList.vue"),
          meta: { requiresAuth: true },
        },
        {
          path: "ib-commission-order",
          name: "ib-commission-order",
          component: () => import("@/views/IbCommissionOrderList.vue"),
          meta: { requiresAuth: true },
        },
        {
          path: "sales-list",
          name: "sales-list",
          component: () => import("@/views/SalesList.vue"),
          meta: { requiresAuth: true },
        },
        {
          path: "sales-dashboard",
          name: "sales-dashboard",
          component: () => import("@/views/SalesDashboard.vue"),
          meta: { requiresAuth: true },
        },
        {
          path: "daily-report",
          name: "daily-report",
          component: () => import("@/views/DailyReport.vue"),
          meta: {
            requiresAuth: true,
            permissionKey: "page_dailyreport_readonly",
          },
        },
        {
          path: "ib-link",
          name: "ib-link",
          component: () => import("@/views/IbLink.vue"),
          meta: { requiresAuth: true },
        },
        {
          path: "ib-partners",
          name: "ib-partners",
          component: () => import("@/views/IbPartners.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "client-tickets",
          name: "client-tickets",
          component: () => import("@/views/ClientTickets.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "email-templates",
          name: "email-templates",
          component: () => import("@/views/EmailTemplates.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "email-settings",
          name: "email-settings",
          component: () => import("@/views/EmailSettings.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        // 后台操作日志模块开关（日志设置）
        {
          path: "log-settings",
          name: "log-settings",
          component: () => import("@/views/LogSettings.vue"),
          meta: {
            requiresAuth: true,
            permissionKey: "page_logsettings_readonly",
          },
        },
        {
          path: "developer-settings",
          name: "developer-settings",
          component: () => import("@/views/DeveloperSettings.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "webmcp",
          name: "webmcp",
          redirect: "/webmcp/overview",
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "webmcp/overview",
          name: "webmcp-overview",
          component: () => import("@/views/WebMcpOverview.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "webmcp/tools",
          name: "webmcp-tools",
          component: () => import("@/views/WebMcpTools.vue"),
          meta: {
            requiresAuth: true,
          },
        },
        {
          path: "webmcp/export-progress",
          name: "webmcp-export-progress",
          component: () => import("@/views/WebMcpExportProgress.vue"),
          meta: {
            requiresAuth: true,
          },
        },
      ],
    },
    {
      path: "/:pathMatch(.*)*",
      name: "not-found",
      component: () => import("@/views/NotFound.vue"),
    },
  ],
});

// Navigation guard for authentication
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore();
  const clientAuthStore = useClientAuthStore();

  // 如果是登录页，先清理可能残留的认证状态，然后允许访问
  if (to.name === "login") {
    // 如果token已过期或无效，确保清理状态
    if (authStore.token && !authStore.user) {
      // token存在但user不存在，可能是token过期了，清理状态
      authStore.logout();
    }
    next();
    return;
  }

  const requiresAuth = to.matched.some((record) => record.meta.requiresAuth);
  const isClientAuth = to.matched.some((record) => record.meta.clientAuth);

  // Handle client authentication routes
  if (isClientAuth) {
    // For protected client pages
    if (clientAuthStore.token && !clientAuthStore.user) {
      await clientAuthStore.fetchUser();
    }

    if (requiresAuth && !clientAuthStore.isAuthenticated) {
      next({ name: "client-login", query: { redirect: to.fullPath } });
    } else {
      next();
    }
    return;
  }

  // Handle admin authentication routes
  if (authStore.token && !authStore.user) {
    // console.log('Token exists but no user, fetching user info...')
    await authStore.fetchUser();
  }

  if (requiresAuth && !authStore.isAuthenticated) {
    next({ name: "login", query: { redirect: to.fullPath } });
  } else if (to.name === "login" && authStore.isAuthenticated) {
    next({ name: "clients-list" });
  } else {
    const requiredPermissions = to.matched.flatMap((record) => {
      if (Array.isArray(record.meta.permissionKeys))
        return record.meta.permissionKeys;
      return record.meta.permissionKey ? [record.meta.permissionKey] : [];
    });
    if (
      requiredPermissions.length > 0 &&
      !requiredPermissions.some((permission) =>
        authStore.hasPermission(permission),
      )
    ) {
      next({ name: "accounts" });
      return;
    }

    // 如果用户已认证，加载菜单设置（使用缓存，不会频繁请求）
    if (authStore.isAuthenticated) {
      const menuSettingsStore = useMenuSettingsStore();
      // 异步加载，不阻塞路由导航
      menuSettingsStore.loadSettings().catch((err) => {
        console.error("Failed to load menu settings:", err);
      });
    }
    next();
  }
});

export default router;
