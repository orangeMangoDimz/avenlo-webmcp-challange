import { createRouter, createWebHashHistory } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { useClientAuthStore } from "@/stores/clientAuth";
import { useLanguageStore } from "@/stores/language";
import { getPreviewSession } from "@/services/previewSessionService";

const router = createRouter({
  history: createWebHashHistory(import.meta.env.BASE_URL),
  routes: [
    //跟目录跳转
    {
      path: "/",
      redirect: "/client",
    },
    // Client Login/Register
    {
      path: "/client/login",
      name: "client-login",
      component: () => import("@/views/ClientLogin.vue"),
      meta: { requiresAuth: false, clientAuth: true },
    },
    // Client Email Verification
    {
      path: "/client/verify-email",
      name: "client-verify-email",
      component: () => import("@/views/ClientEmailVerification.vue"),
      meta: { requiresAuth: false, clientAuth: true },
    },
    // Client Password Reset
    {
      path: "/client/reset-password",
      name: "client-reset-password",
      component: () => import("@/views/ClientResetPassword.vue"),
      meta: { requiresAuth: false, clientAuth: true },
    },
    // IB Invitation (handled before login check)
    {
      path: "/ib/invitation/:encryptedCode",
      name: "ib-invitation",
      component: () => import("@/views/IbInvitation.vue"),
      meta: { requiresAuth: false, clientAuth: true, handleInvitation: true },
    },
    // Sales Referral 中间页：记录访问后跳转登录
    {
      path: "/registration/s/:suffix",
      name: "sales-registration-referral",
      component: () => import("@/views/SalesReferral.vue"),
      meta: { requiresAuth: false, clientAuth: true },
    },
    // IB Referral 中间页：记录访问后跳转登录，注册时绑定 IB-Client
    {
      path: "/registration/i/:suffix",
      name: "ib-registration-referral",
      component: () => import("@/views/IbReferral.vue"),
      meta: { requiresAuth: false, clientAuth: true },
    },
    // View as client 预览入口（跳过登录，凭 token 进入首页）
    {
      path: "/preview",
      name: "client-preview",
      component: () => import("@/views/ClientPreview.vue"),
      meta: { requiresAuth: false, clientAuth: true, isPreviewEntry: true },
    },
    // App→WebView handoff 落地页：用一次性 code 换 client JWT 后跳到 redirect
    {
      path: "/auth/handoff",
      name: "client-auth-handoff",
      component: () => import("@/views/ClientAuthHandoff.vue"),
      meta: { requiresAuth: false, clientAuth: true, isHandoffEntry: true },
    },
    // WebView 隔离入口：复用客户端门户的 KYC / 交易主体页面，但不走 ClientLayout
    // （没有侧边栏、顶部 header），交易页隐藏顶部 tab 切换条，由路由 meta 决定主体类型。
    // KYC 校验放在交易页面内部（ClientTransactions.vue）按需渲染 KycRequiredNotice，
    // 不在路由层做跳转，避免被绕过的风险。
    {
      path: "/app/kyc",
      name: "app-kyc",
      component: () => import("@/views/ClientKycVerification.vue"),
      meta: { requiresAuth: true, clientAuth: true, isWebView: true },
    },
    {
      path: "/app/deposit",
      name: "app-deposit",
      component: () => import("@/views/ClientTransactions.vue"),
      meta: {
        requiresAuth: true,
        clientAuth: true,
        isWebView: true,
        transactionTab: "deposit",
      },
    },
    {
      path: "/app/withdraw",
      name: "app-withdraw",
      component: () => import("@/views/ClientTransactions.vue"),
      meta: {
        requiresAuth: true,
        clientAuth: true,
        isWebView: true,
        transactionTab: "withdraw",
      },
    },
    {
      path: "/app/transfer",
      name: "app-transfer",
      component: () => import("@/views/ClientTransactions.vue"),
      meta: {
        requiresAuth: true,
        clientAuth: true,
        isWebView: true,
        transactionTab: "transfer",
      },
    },
    {
      path: "/app/openaccount",
      name: "app-openaccount",
      component: () => import("@/views/ClientNewAccount.vue"),
      meta: { requiresAuth: true, clientAuth: true, isWebView: true },
    },
    // Client Portal
    {
      path: "/client",
      component: () => import("@/layouts/ClientLayout.vue"),
      meta: { requiresAuth: true, clientAuth: true },
      children: [
        {
          path: "",
          redirect: "/client/dashboard",
        },
        {
          path: "dashboard",
          name: "client-dashboard",
          component: () => import("@/views/ClientDashboard.vue"),
          meta: {
            requiresAuth: true,
            clientAuth: true,
            title: "Dashboard",
            subtitle: "Welcome to your trading dashboard",
            icon: "fas fa-home",
          },
        },
        {
          path: "documents",
          name: "client-documents",
          component: () => import("@/views/ClientDocuments.vue"),
          meta: {
            requiresAuth: true,
            clientAuth: true,
            title: "My Documents",
            subtitle: "View and download your signed legal documents",
            icon: "fas fa-file-alt",
          },
        },
        {
          path: "profile",
          name: "client-profile",
          component: () => import("@/views/ClientDashboard.vue"), // TODO: Create ClientProfile.vue
          meta: {
            requiresAuth: true,
            clientAuth: true,
            title: "My Profile",
            subtitle: "Manage your account information",
            icon: "fas fa-user",
          },
        },
        {
          path: "settings",
          name: "client-settings",
          component: () => import("@/views/ClientDashboard.vue"), // TODO: Create ClientSettings.vue
          meta: {
            requiresAuth: true,
            clientAuth: true,
            title: "Account Settings",
            subtitle: "Configure your account preferences",
            icon: "fas fa-cog",
          },
        },
        {
          path: "kyc-verification",
          name: "client-kyc-verification",
          component: () => import("@/views/ClientKycVerification.vue"),
          meta: {
            requiresAuth: true,
            clientAuth: true,
            title: "KYC Verification",
            subtitle: "Complete your identity verification",
            icon: "fas fa-id-card",
          },
        },
        {
          path: "ib-dashboard",
          name: "client-ib-dashboard",
          component: () => import("@/views/IbDashboard.vue"),
          meta: {
            requiresAuth: true,
            clientAuth: true,
            title: "IB Program",
            subtitle: "Become an Introducing Broker and earn commissions",
            icon: "fas fa-handshake",
          },
        },
        {
          path: "ib-dashboard-active",
          name: "client-ib-dashboard-active",
          component: () => import("@/views/IbDashboardActive.vue"),
          meta: {
            requiresAuth: true,
            clientAuth: true,
            title: "My IB Dashboard",
            subtitle: "Manage your network and track commissions",
            icon: "fas fa-handshake",
            requiresIbApproved: true,
          },
        },
        {
          path: "commission-report",
          name: "client-commission-report",
          component: () => import("@/views/CommissionReport.vue"),
          meta: {
            requiresAuth: true,
            clientAuth: true,
            title: "Commission Report",
            subtitle: "Track your earnings and referral performance",
            icon: "fas fa-chart-pie",
          },
        },
        {
          path: "deposit-withdraw-report",
          name: "client-deposit-withdraw-report",
          component: () => import("@/views/DepositWithdrawReport.vue"),
          meta: {
            requiresAuth: true,
            clientAuth: true,
            title: "Deposit Withdraw Report",
            subtitle: "Track deposits and withdrawals across your network",
            icon: "fas fa-money-bill-transfer",
          },
        },
        {
          path: "ib-statement",
          name: "client-ib-statement",
          component: () => import("@/views/IbStatementReport.vue"),
          meta: {
            requiresAuth: true,
            clientAuth: true,
            requiresIbApproved: true,
            title: "P&L Report",
            subtitle: "View your introducing broker client P&L statement",
            icon: "fas fa-file-invoice-dollar",
          },
        },
        {
          path: "ib-client-position",
          name: "client-ib-client-position",
          component: () => import("@/views/IbClientPosition.vue"),
          meta: {
            requiresAuth: true,
            clientAuth: true,
            requiresIbApproved: true,
            title: "Client's Position",
            subtitle:
              "View positions and orders for traders in your IB network",
            icon: "fas fa-chart-line",
          },
        },
        {
          path: "withdrawal-supplement/:id",
          name: "client-withdrawal-supplement",
          component: () => import("@/views/ClientWithdrawalSupplement.vue"),
          meta: {
            requiresAuth: true,
            clientAuth: true,
            title: "Additional Information Required",
            subtitle: "Provide additional information for withdrawal",
            icon: "fas fa-file-upload",
          },
        },
        {
          path: "accounts",
          name: "client-accounts",
          component: () => import("@/views/ClientAccounts.vue"),
          meta: {
            requiresAuth: true,
            clientAuth: true,
            title: "My Accounts",
            subtitle: "Manage your trading accounts",
            icon: "fas fa-wallet",
          },
        },
        {
          path: "account/new",
          name: "client-accounts-new",
          component: () => import("@/views/ClientNewAccount.vue"),
          meta: {
            requiresAuth: true,
            clientAuth: true,
            requiresKyc: true, // 需要 KYC 验证
            title: "Open New Account",
            subtitle: "Create a new trading account",
            icon: "fas fa-plus-circle",
          },
        },
        {
          path: "transactions",
          name: "client-transactions",
          component: () => import("@/views/ClientTransactions.vue"),
          meta: {
            requiresAuth: true,
            clientAuth: true,
            title: "Transactions",
            subtitle: "Funding Management",
            icon: "fas fa-exchange-alt",
          },
        },
        {
          path: "transactions/success",
          name: "client-transactions-success",
          component: () => import("@/views/ClientTransactions.vue"),
          meta: {
            requiresAuth: true,
            clientAuth: true,
            title: "Payment Successful",
            subtitle: "Your deposit has been submitted successfully",
            icon: "fas fa-exchange-alt",
          },
        },
        {
          path: "transactions/fail",
          name: "client-transactions-fail",
          component: () => import("@/views/ClientTransactions.vue"),
          meta: {
            requiresAuth: true,
            clientAuth: true,
            title: "Payment Failed",
            subtitle: "Your deposit was not completed",
            icon: "fas fa-exchange-alt",
          },
        },
        {
          path: "transactions/pending",
          name: "client-transactions-pending",
          component: () => import("@/views/ClientTransactions.vue"),
          meta: {
            requiresAuth: true,
            clientAuth: true,
            title: "Payment Pending",
            subtitle: "We are waiting for the payment channel response",
            icon: "fas fa-exchange-alt",
          },
        },
        {
          path: "transaction-history",
          name: "client-transaction-history",
          component: () => import("@/views/TransactionHistory.vue"),
          meta: {
            requiresAuth: true,
            clientAuth: true,
            title: "Transaction History",
            subtitle: "View all your deposit and withdrawal transactions",
            icon: "fas fa-history",
          },
        },
        {
          path: "positions",
          name: "open-positions",
          component: () => import("@/views/OpenPositions.vue"),
          meta: {
            requiresAuth: true,
            clientAuth: true,
            title: "Positions",
            subtitle: "View and manage your open positions",
            icon: "fas fa-list",
          },
        },
        {
          path: "history",
          name: "order-history",
          component: () => import("@/views/OrderHistory.vue"),
          meta: {
            requiresAuth: true,
            clientAuth: true,
            title: "Order History",
            subtitle: "Order History",
            icon: "fas fa-history",
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
  // App→WebView 落地页（/app/*、/auth/handoff）允许 App 通过 ?lang= 注入语言，
  // /app/* 不走 ClientLayout，所以这里必须同时补 loadClientPortalTranslations，
  // 否则 public/language-pack-*-template.json 里的 UI 文案不会加载，页面会显示成 key 或英文。
  const isWebViewRoute = to.matched.some(
    (record) => record.meta.isWebView || record.meta.isHandoffEntry,
  );
  if (isWebViewRoute && to.query.lang) {
    const langCode = String(to.query.lang);
    const languageStore = useLanguageStore();
    try {
      if (languageStore.currentLanguage !== langCode) {
        await languageStore.changeLanguage(langCode);
      }
      await languageStore.loadClientPortalTranslations();
    } catch (_) {
      // 语言切换失败不阻塞路由，继续走原语言
    }
  }

  // 如果是登录页，直接跳过路由守卫，允许访问
  if (to.name === "client-login") {
    next();
    return;
  }

  // App→WebView handoff 入口：组件自己处理 code 兑换，守卫一律放行
  // （此时本地可能还没 token，也可能有旧 token，组件会负责覆盖 + 跳转）
  if (to.matched.some((record) => record.meta.isHandoffEntry)) {
    next();
    return;
  }

  const authStore = useAuthStore();
  const clientAuthStore = useClientAuthStore();
  const requiresAuth = to.matched.some((record) => record.meta.requiresAuth);
  const isClientAuth = to.matched.some((record) => record.meta.clientAuth);
  const handleInvitation = to.matched.some(
    (record) => record.meta.handleInvitation,
  );
  const isPreviewEntry = to.matched.some(
    (record) => record.meta.isPreviewEntry,
  );

  // 预览入口：不要求登录，直接进入
  if (isPreviewEntry) {
    next();
    return;
  }

  // Handle IB invitation route
  if (handleInvitation) {
    // 确保用户信息已加载（如果有token）
    if (clientAuthStore.token && !clientAuthStore.user) {
      await clientAuthStore.fetchUser();
    }

    // 如果未登录，保存邀请码到sessionStorage，然后跳转到登录页
    if (!clientAuthStore.isAuthenticated) {
      const encryptedCode = to.params.encryptedCode;
      sessionStorage.setItem("pendingIbInvitation", encryptedCode);
      next({ name: "client-login", query: { redirect: to.fullPath } });
      return;
    }

    // 如果已登录，直接进入邀请处理页面（页面会处理验证逻辑）
    next();
    return;
  }

  // Handle client authentication routes
  if (isClientAuth) {
    // 刷新后恢复预览模式：需要登录但当前未认证且 sessionStorage 有 previewToken 时，用 token 恢复预览会话
    if (requiresAuth && !clientAuthStore.isAuthenticated) {
      const storedPreviewToken =
        typeof sessionStorage !== "undefined"
          ? sessionStorage.getItem("previewToken")
          : null;
      if (storedPreviewToken) {
        try {
          const res = await getPreviewSession(storedPreviewToken);
          const data = res?.data ?? res;
          const user = data?.user;
          if (user && data?.clientUserId) {
            clientAuthStore.setPreviewSession(user, storedPreviewToken);
            await clientAuthStore.loadPreviewProfile();
            next();
            return;
          }
        } catch (_) {
          if (typeof sessionStorage !== "undefined")
            sessionStorage.removeItem("previewToken");
        }
      }
    }

    // For protected client pages
    if (clientAuthStore.token && !clientAuthStore.user) {
      await clientAuthStore.fetchUser();
    }

    if (requiresAuth && !clientAuthStore.isAuthenticated) {
      next({ name: "client-login", query: { redirect: to.fullPath } });
      return;
    }

    // Check if route requires IB approval
    const requiresIbApproved = to.matched.some(
      (record) => record.meta.requiresIbApproved,
    );
    if (requiresIbApproved && !clientAuthStore.isIbApproved) {
      // Redirect to ib-dashboard if user is not approved
      next({ name: "client-ib-dashboard" });
      return;
    }

    // 如果已登录的客户端用户访问登录页面，跳转到dashboard（除非是IB邀请链接）
    if (to.name === "client-login" && clientAuthStore.isAuthenticated) {
      const pendingInvitation = sessionStorage.getItem("pendingIbInvitation");
      if (pendingInvitation) {
        // 如果有待处理的IB邀请，跳转到邀请验证页面
        next({ path: `/ib/invitation/${pendingInvitation}` });
      } else {
        // 否则跳转到dashboard
        next({ name: "client-dashboard" });
      }
      return;
    }

    next();
    return;
  }

  // Handle admin authentication routes
  if (authStore.token && !authStore.user) {
    // console.log('Token exists but no user, fetching user info...')
    await authStore.fetchUser();
  }

  if (requiresAuth && !authStore.isAuthenticated) {
    next({ name: "client-login", query: { redirect: to.fullPath } });
  } else if (to.name === "client-login" && authStore.isAuthenticated) {
    next({ name: "accounts" });
  } else {
    next();
  }
});

export default router;
