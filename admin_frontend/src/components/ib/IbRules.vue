<template>
  <div>
    <!-- Page Header (hidden when embedded in Settings tabs) -->
    <div v-if="!embedded" class="page-header">
      <div class="page-title">
        <h1>{{ t("ibRuleMgmt_page_title") }}</h1>
        <p>{{ t("ibRuleMgmt_page_sub") }}</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <!-- Statistics Header
    <div class="stats-header">
      <div>
        <h2 style="font-size: 20px; color: var(--color-ink); margin-bottom: 5px;">All IB Rules</h2>
        <p style="font-size: 14px; color: var(--color-muted);">Manage different IB rule templates with customized commission structures</p>
      </div>
      <div class="page-stats">
        <div class="stat-badge">
          <i class="fas fa-list-alt"></i>
          <span>{{ statistics.totalRules }} Total Rules</span>
        </div>
        <div class="stat-badge" style="background: var(--color-success-soft); color: var(--color-success);">
          <i class="fas fa-check-circle"></i>
          <span>{{ statistics.activeRules }} Active</span>
        </div>
      </div>
    </div> -->

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <i
        class="fas fa-spinner fa-spin"
        style="font-size: 32px; color: var(--color-brand)"
      ></i>
      <p style="margin-top: 15px; color: var(--color-muted)">
        {{ t("ibRuleMgmt_loading") }}
      </p>
    </div>

    <!-- Rules Table -->
    <div v-else class="package-table-container">
      <!-- Sync products from trading platform (per platform) -->
      <div v-if="hasSyncProducts !== false" class="sync-products-section">
        <div class="sync-products-header">
          <h3 class="sync-products-title">
            <i class="fas fa-cloud-download-alt"></i>
            {{ t("ibRuleMgmt_sync_title") }}
          </h3>
          <p class="sync-products-desc">
            {{ t("ibRuleMgmt_sync_desc") }}
          </p>
        </div>
        <div class="sync-products-cards">
          <p v-if="platforms.length === 0" class="sync-platforms-empty">
            {{ t("ibRuleMgmt_sync_noPlatforms") }}
          </p>
          <div
            v-for="platform in platforms"
            :key="platform.key"
            class="sync-platform-card"
          >
            <div class="sync-platform-icon" :class="platform.key">
              <i :class="platform.icon"></i>
            </div>
            <div class="sync-platform-info">
              <span class="sync-platform-name">{{ platform.name }}</span>
              <span class="sync-platform-code">{{ platform.shortCode }}</span>
            </div>
            <div class="sync-platform-action">
              <button
                type="button"
                class="btn btn-sync-platform"
                :disabled="syncingPlatformKey !== null"
                @click="syncProductsByPlatform(platform.key)"
              >
                <i
                  class="fas fa-sync-alt"
                  :class="{ 'fa-spin': syncingPlatformKey === platform.key }"
                ></i>
                {{
                  syncingPlatformKey === platform.key
                    ? t("ibRuleMgmt_sync_syncing")
                    : t("ibRuleMgmt_sync_btn")
                }}
              </button>
              <span
                v-if="syncingPlatformKey === platform.key && syncProgress"
                class="sync-status-text"
              >
                {{ syncProgress.message }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="table-header">
        <div class="table-header-left">
          <h2>{{ t("ibRuleMgmt_table_title") }}</h2>
        </div>
        <div class="table-header-center">
          <div class="ib-rules-search-wrap">
            <input
              v-model="searchKeyword"
              type="text"
              class="ib-rules-search-input"
              :placeholder="t('ibRuleMgmt_searchPlaceholder')"
              @keyup.enter="applySearch"
            />
            <button type="button" class="btn btn-search" @click="applySearch">
              <i class="fas fa-search"></i> {{ t("ibRuleMgmt_btn_search") }}
            </button>
          </div>
        </div>
        <div class="table-header-right">
          <button
            v-if="hasCreateRule !== false"
            class="btn btn-success"
            @click="openCreateModal"
          >
            <i class="fas fa-plus"></i> {{ t("ibRuleMgmt_btn_newRule") }}
          </button>
          <div class="ib-rules-show-rows-wrap">
            <label class="ib-rules-show-rows-label">{{
              t("ibRuleMgmt_showRows")
            }}</label>
            <select
              v-model="pageSize"
              class="ib-rules-show-rows-select"
              @change="onPageSizeChange"
            >
              <option :value="5">5</option>
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option value="all">{{ t("ibCo_perPage_all") }}</option>
            </select>
          </div>
        </div>
      </div>

      <div class="ib-rules-table-scroll">
        <table class="package-table ib-rules-table">
          <thead>
            <tr>
              <th>{{ t("ibRuleMgmt_th_ruleName") }}</th>
              <th>{{ t("ibRuleMgmt_th_ruleType") }}</th>
              <th>{{ t("ibRuleMgmt_th_platform") }}</th>
              <th>{{ t("ibRuleMgmt_th_product") }}</th>
              <th>{{ t("ibRuleMgmt_th_targetRegion") }}</th>
              <th>{{ t("ibRuleMgmt_th_currency") }}</th>
              <th>{{ t("ibRuleMgmt_th_minPayout") }}</th>
              <th>{{ t("ibRuleMgmt_th_minTrade") }}</th>
              <th>{{ t("ibRuleMgmt_th_maxTrade") }}</th>
              <th>{{ t("ibRuleMgmt_th_rate") }}</th>
              <th>{{ t("ibRuleMgmt_th_tier") }}</th>
              <th>{{ t("ibRuleMgmt_th_fixedAmount") }}</th>
              <th>{{ t("ibRuleMgmt_th_rebateAmount") }}</th>
              <th>{{ t("ibRuleMgmt_th_paymentCycle") }}</th>
              <th>{{ t("ibRuleMgmt_th_paymentDay") }}</th>
              <th>{{ t("ibRuleMgmt_th_createDate") }}</th>
              <th>{{ t("ibRuleMgmt_th_description") }}</th>
              <th>{{ t("ibRuleMgmt_th_status") }}</th>
              <th class="ib-rules-table__th--sticky">
                {{ t("ibRuleMgmt_th_action") }}
              </th>
            </tr>
          </thead>
          <tbody>
            <template v-for="rule in rules" :key="rule.id">
              <tr :data-package-id="rule.id">
                <td>{{ rule.ruleName }}</td>
                <td>{{ formatRuleType(rule.ruleType) }}</td>
                <td>{{ formatPlatform(rule.trading_platforms_key) }}</td>
                <td class="ib-rules-product-cell">
                  <div class="ib-rule-product-tags">
                    <template
                      v-for="(item, idx) in getDisplayProducts(rule)"
                      :key="idx"
                    >
                      <span
                        v-if="item.type === 'tag'"
                        class="ib-rule-product-tag"
                        >{{ item.text }}</span
                      >
                      <span
                        v-else-if="item.type === 'more'"
                        class="ib-rule-product-more"
                        >{{ item.text }}</span
                      >
                      <span v-else class="ib-rule-product-empty">—</span>
                    </template>
                  </div>
                </td>
                <td>{{ rule.targetRegion || "—" }}</td>
                <td>{{ rule.payoutCurrency || "—" }}</td>
                <td>{{ formatAmount(rule.minimumPayout) }}</td>
                <td>{{ formatAmount(rule.minimum_trade) }}</td>
                <td>{{ formatAmount(rule.maximum_trade) }}</td>
                <td>{{ formatRate(rule.rate) }}</td>
                <td>
                  {{
                    rule.tierLevel != null && rule.tierLevel !== ""
                      ? rule.tierLevel
                      : "—"
                  }}
                </td>
                <td>{{ formatAmount(rule.fixed_amount) }}</td>
                <td>{{ formatAmount(rule.rebateAmount, 5) }}</td>
                <td>{{ formatPaymentCycle(rule.paymentCycle) }}</td>
                <td>{{ rule.paymentDay || "—" }}</td>
                <td>{{ formatDate(rule.createdAt) }}</td>
                <td class="ib-rules-desc-cell">
                  {{ rule.ruleDescription || "—" }}
                </td>
                <!-- Description 列对应 ruleDescription 字段 -->
                <td>
                  <span
                    class="status-badge"
                    :class="(rule.status || 'draft').toLowerCase()"
                    >{{ formatRuleStatus(rule.status) }}</span
                  >
                </td>
                <td class="ib-rules-table__cell--sticky">
                  <div class="action-buttons">
                    <button
                      v-if="hasEditRule !== false"
                      class="btn-icon btn-edit"
                      @click="openEditModal(rule)"
                      :title="t('ibRuleMgmt_aria_editRule')"
                    >
                      <i class="fas fa-edit"></i>
                    </button>
                    <button
                      class="btn-icon btn-copy"
                      @click="duplicateRule(rule.id)"
                      :title="t('ibRuleMgmt_aria_copyRule')"
                    >
                      <i class="fas fa-copy"></i>
                    </button>
                    <button
                      v-if="hasDeleteRule !== false"
                      class="btn-icon btn-delete"
                      @click="deleteRule(rule.id)"
                      :title="t('ibRuleMgmt_aria_deleteRule')"
                    >
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <div class="ib-rules-pagination" v-if="pagination.total > 0">
        <span class="ib-rules-pagination-info">
          {{ paginationInfo }}
        </span>
        <div class="ib-rules-pagination-btns">
          <button
            type="button"
            class="btn btn-pagination"
            :disabled="currentPage <= 1"
            @click="goToPage(currentPage - 1)"
          >
            <i class="fas fa-chevron-left"></i> {{ t("ibIr_pagination_prev") }}
          </button>
          <span class="ib-rules-pagination-page">
            {{
              tParams("ibIr_pagination_pageOf", "Page {current} of {total}", {
                current: currentPage,
                total: totalPagesText,
              })
            }}
          </span>
          <button
            type="button"
            class="btn btn-pagination"
            :disabled="!hasNextPage"
            @click="goToPage(currentPage + 1)"
          >
            {{ t("ibIr_pagination_next") }} <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Create Rule Modal -->
    <CreateRuleModal
      v-if="showCreateModal"
      @close="closeCreateModal"
      @created="handleRuleCreated"
    />

    <!-- Edit Rule Modal -->
    <EditRuleModal
      v-if="showEditModal"
      :rule="editingRule"
      @close="closeEditModal"
      @saved="handleRuleSaved"
    />
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import CreateRuleModal from "@/components/ib/CreateRuleModal.vue";
import EditRuleModal from "@/components/ib/EditRuleModal.vue";
import ibRulesApi from "@/services/ibRulesApi";
import ibSettingsApi from "@/services/ibSettingsApi";
import loginSettingsService from "@/services/loginSettingsService";
import { useAdminI18n } from "@/composables/useAdminI18n";

defineProps({
  embedded: { type: Boolean, default: false },
  hasSyncProducts: { type: Boolean, default: true },
  hasCreateRule: { type: Boolean, default: true },
  hasEditRule: { type: Boolean, default: true },
  hasDeleteRule: { type: Boolean, default: true },
});

const { t, tParams, languageStore } = useAdminI18n();

const formatRuleStatus = (status) => {
  if (!status || String(status).trim() === "") return "—";
  const s = String(status).toLowerCase();
  const keyMap = {
    active: "ibTierMgmt_status_active",
    inactive: "ibTierMgmt_status_inactive",
    draft: "ibTierMgmt_status_draft",
  };
  const k = keyMap[s];
  return k ? t(k) : String(status);
};

const loading = ref(true);
const rules = ref([]);
const showCreateModal = ref(false);
const showEditModal = ref(false);
const editingRule = ref(null);
const searchKeyword = ref("");
const pageSize = ref(10);
const currentPage = ref(1);
const pagination = ref({
  total: 0,
  total_pages: 1,
  page: 1,
  per_page: 10,
});

const statistics = computed(() => {
  return {
    totalRules: pagination.value.total,
    activeRules: rules.value.filter((r) => r.status === "active").length,
  };
});

const totalPagesText = computed(() => {
  const total = pagination.value.total_pages;
  if (total <= 0) return "1";
  return String(total);
});

const hasNextPage = computed(() => {
  return pagination.value.page < pagination.value.total_pages;
});

const paginationInfo = computed(() => {
  const total = pagination.value.total;
  if (total === 0) return t("ibIr_pagination_noRecords");
  if (pageSize.value === "all")
    return tParams("ibIr_pagination_totalAll", "Total {total} record(s)", {
      total,
    });
  const perPage = pageSize.value;
  const from = (currentPage.value - 1) * perPage + 1;
  const to = Math.min(currentPage.value * perPage, total);
  return tParams("ibIr_pagination_showing", "Showing {from}-{to} of {total}", {
    from,
    to,
    total,
  });
});

const goToPage = (page) => {
  if (page < 1 || page > pagination.value.total_pages) return;
  currentPage.value = page;
  loadRules();
};

/** 按平台同步产品：平台列表来自接口（tradingPlatforms 表 isEnabled=1，以 platformKey 为标识） */
const platforms = ref([]);
const platformIcons = {
  mt4: "fas fa-desktop",
  mt5: "fas fa-laptop",
  financepro: "fas fa-chart-line",
};
const syncingPlatformKey = ref(null);
const syncProgress = ref(null);
let syncProgressTimer = null;

const pollSyncProgress = (platformKey) => {
  stopPollSyncProgress();
  syncProgressTimer = setInterval(async () => {
    try {
      const res = await ibSettingsApi.getSyncProgress(platformKey);
      const data = res?.data ?? res;
      syncProgress.value = data;
      if (data?.status === "done" || data?.status === "error") {
        alert(
          data.message ||
            (data.status === "done"
              ? t("ibRuleMgmt_sync_done")
              : t("ibRuleMgmt_sync_failed")),
        );
        stopPollSyncProgress();
        syncingPlatformKey.value = null;
        syncProgress.value = null;
      }
    } catch (e) {
      console.error("Poll sync progress failed:", e);
    }
  }, 5000);
};

const stopPollSyncProgress = () => {
  if (syncProgressTimer) {
    clearInterval(syncProgressTimer);
    syncProgressTimer = null;
  }
};

const loadPlatforms = async () => {
  try {
    const res = await loginSettingsService.getTradingGroupPlatforms();
    // 接口返回 body：{ success, message, data: [ { key, name } ] }，拦截器已返回 body
    const list = Array.isArray(res?.data) ? res.data : [];
    platforms.value = list
      .map((p) => ({
        key: p.key ?? p.platformKey ?? "",
        name: p.name ?? p.displayName ?? p.key ?? "",
        shortCode:
          p.shortCode ??
          (p.key === "financepro" ? "FinancePro" : (p.key || "").toUpperCase()),
        icon:
          platformIcons[p.key] ??
          platformIcons[p.platformKey] ??
          "fas fa-server",
      }))
      .filter((p) => p.key);
  } catch (e) {
    console.error("Failed to load trading platforms:", e);
  }
};

/**
 * 按平台同步 Securities & Symbols
 * MT4/MT5：触发异步任务后轮询进度；FinancePro：同步接口直接返回结果
 */
const syncProductsByPlatform = async (platformKey) => {
  if (syncingPlatformKey.value) return;
  syncingPlatformKey.value = platformKey;
  syncProgress.value = null;
  // MT4/MT5 走异步任务队列，提交后轮询进度；其余平台同步返回结果
  const isAsyncSync = platformKey === "mt5" || platformKey === "mt4";
  try {
    const response = await ibSettingsApi.syncProducts(platformKey);
    const body =
      response?.success !== undefined ? response : (response?.data ?? {});
    const payload = body?.data ?? {};
    const message = body?.message ?? "";

    // 异步任务已提交，启动轮询
    if (isAsyncSync && body?.success) {
      syncProgress.value = {
        status: "connecting",
        message: t("ibRuleMgmt_sync_taskSubmitted"),
        percent: 0,
      };
      pollSyncProgress(platformKey);
      return;
    }

    // FinancePro 同步返回
    if (body?.success && payload?.success === false && payload?.message) {
      alert(payload.message);
    } else if (body?.success && payload?.success === true) {
      const sec = payload.securitiesCount ?? 0;
      const sym = payload.symbolsCount ?? 0;
      alert(
        tParams(
          "ibRuleMgmt_sync_doneCounts",
          "Sync completed. {sec} securities, {sym} symbols.",
          { sec, sym },
        ),
      );
    } else {
      alert(message || t("ibRuleMgmt_sync_done"));
    }
  } catch (e) {
    console.error("Sync products failed:", e);
    const msg = e?.response?.data?.message || e?.message || "";
    alert(msg || t("ibRuleMgmt_sync_failGeneric"));
  } finally {
    // 异步平台由轮询回调重置；其余直接重置
    if (!isAsyncSync) {
      syncingPlatformKey.value = null;
      syncProgress.value = null;
    }
  }
};

const applySearch = () => {
  currentPage.value = 1;
  loadRules();
};

const onPageSizeChange = () => {
  currentPage.value = 1;
  loadRules();
};

/**
 * 获取规则图标
 */
const getRuleIcon = (ruleType) => {
  const icons = {
    standard: "fa-layer-group",
    premium: "fa-crown",
    ultra: "fa-gem",
    custom: "fa-cog",
  };
  return icons[ruleType] || "fa-file-invoice-dollar";
};

/**
 * 获取规则颜色
 */
const getRuleColor = (ruleType) => {
  const colors = {
    standard: "#3b82f6",
    premium: "#f59e0b",
    ultra: "#8b5cf6",
    custom: "#64748b",
  };
  return colors[ruleType] || "var(--color-brand)";
};

/**
 * 根据平台 key 显示名称（使用已加载的 platforms 列表）
 */
const formatPlatform = (key) => {
  if (!key) return "—";
  const p = platforms.value.find((x) => x.key === key);
  return p ? p.name : key;
};

const PRODUCT_DISPLAY_LIMIT = 2;

const getRuleProductNames = (rule) => {
  if (Array.isArray(rule?.products) && rule.products.length > 0) {
    return rule.products.map((product) => product?.productName).filter(Boolean);
  }
  if (rule?.productName) return [rule.productName];
  return [];
};

const getRuleProductTotalCount = (rule) => {
  const countFromApi = Number(rule?.productCount ?? 0);
  if (Array.isArray(rule?.products) && rule.products.length > 0) {
    const namedCount = rule.products.filter(
      (product) => product?.productName,
    ).length;
    return Math.max(countFromApi, namedCount, rule.products.length);
  }
  if (countFromApi > 0) return countFromApi;
  return rule?.productName ? 1 : 0;
};

/** 参考 KYC 模板列表适用国家：展示前 N 个 +「另有 x 个」 */
const getDisplayProducts = (rule) => {
  const names = getRuleProductNames(rule);
  const total = getRuleProductTotalCount(rule);
  if (total === 0) return [{ type: "empty" }];
  if (total <= PRODUCT_DISPLAY_LIMIT) {
    const list =
      names.length > 0 ? names : rule?.productName ? [rule.productName] : [];
    return list.slice(0, total).map((text) => ({ type: "tag", text }));
  }
  const visible = (
    names.length > 0 ? names : rule?.productName ? [rule.productName] : []
  ).slice(0, PRODUCT_DISPLAY_LIMIT);
  const moreCount = total - visible.length;
  if (moreCount <= 0) {
    return visible.map((text) => ({ type: "tag", text }));
  }
  return [
    ...visible.map((text) => ({ type: "tag", text })),
    {
      type: "more",
      text: tParams("ibRuleMgmt_moreProducts", "+{n} more", { n: moreCount }),
    },
  ];
};

const formatRuleType = (type) => {
  if (!type) return "—";
  const keyMap = {
    cash_back_rebate: "ibRuleMgmt_ruleType_cash_back_rebate",
    per_lot: "ibRuleMgmt_ruleType_per_lot",
    percentage: "ibRuleMgmt_ruleType_percentage",
    per_trade: "ibRuleMgmt_ruleType_per_trade",
    per_trade_rebate: "ibRuleMgmt_ruleType_per_trade_rebate",
    per_lot_rebate: "ibRuleMgmt_ruleType_per_lot_rebate",
    hybrid: "ibRuleMgmt_ruleType_hybrid",
  };
  const k = keyMap[type];
  return k ? t(k) : String(type).replace(/_/g, " ");
};

/** 金额/数字千分位显示，decimals 默认 2 */
const formatAmount = (value, decimals = 2) => {
  if (value == null || value === "") return "—";
  const num = Number(value);
  if (Number.isNaN(num)) return "—";
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return num.toLocaleString(loc, {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  });
};

/** Rate 百分比：设置多少显示多少，小数点后不补 0，末尾加 % */
const formatRate = (value) => {
  if (value == null || value === "") return "—";
  const num = Number(value);
  if (Number.isNaN(num)) return "—";
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  const s = num.toLocaleString(loc, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 10,
  });
  return s + "%";
};

const formatPaymentCycle = (cycle) => {
  if (!cycle) return "—";
  const keyMap = {
    realtime: "ibRuleMgmt_payCycle_realtime",
    daily: "ibRuleMgmt_payCycle_daily",
    weekly: "ibRuleMgmt_payCycle_weekly",
    biweekly: "ibRuleMgmt_payCycle_biweekly",
    monthly: "ibRuleMgmt_payCycle_monthly",
    quarterly: "ibRuleMgmt_payCycle_quarterly",
  };
  const k = keyMap[cycle];
  return k ? t(k) : String(cycle);
};

/**
 * 格式化日期
 */
const formatDate = (dateString) => {
  if (!dateString) return t("ibDocCard_date_na");
  const date = new Date(dateString);
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return date.toLocaleDateString(loc, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

/**
 * 打开编辑弹窗
 */
const openEditModal = async (rule) => {
  try {
    const response = await ibRulesApi.getRule(rule.id);
    editingRule.value =
      response?.success && response.data ? response.data : rule;
  } catch (error) {
    console.error("Failed to load rule detail for editing:", error);
    editingRule.value = rule;
  }
  showEditModal.value = true;
};

/**
 * 关闭编辑弹窗
 */
const closeEditModal = () => {
  showEditModal.value = false;
  editingRule.value = null;
};

/**
 * 编辑保存后刷新列表
 */
const handleRuleSaved = async () => {
  closeEditModal();
  await loadRules();
};

/**
 * 打开创建模态框
 */
const openCreateModal = () => {
  showCreateModal.value = true;
};

/**
 * 关闭创建模态框
 */
const closeCreateModal = () => {
  showCreateModal.value = false;
};

/**
 * 处理规则创建成功
 */
const handleRuleCreated = async () => {
  await loadRules();
  closeCreateModal();
};

/**
 * 复制规则
 */
const duplicateRule = async (ruleId) => {
  const rule = rules.value.find((r) => r.id === ruleId);
  if (!rule) return;
  const productCount = Array.isArray(rule.products)
    ? rule.products.filter((product) => product?.productName).length
    : Number(rule.productCount ?? 0);

  const confirmMessage = tParams(
    "ibRuleMgmt_confirm_copy",
    'Copy Rule\n\nAre you sure you want to copy this rule?\n\nRule: {name}\nProducts: {products} Products\nStatus: {status}\n\nA new draft rule will be created with "(Copy)" suffix.',
    {
      name: rule.ruleName || "",
      products: String(productCount),
      status: formatRuleStatus(rule.status),
    },
  );

  if (!confirm(confirmMessage)) return;

  try {
    const response = await ibRulesApi.duplicateRule(ruleId);

    if (response.success) {
      alert(
        tParams(
          "ibRuleMgmt_alert_copyOk",
          'Rule copied successfully.\n\nNew rule: "{name}"\nStatus: Draft\n\nYou can now edit the copied rule.',
          { name: `${rule.ruleName || ""}${t("ibTierMgmt_copyNameSuffix")}` },
        ),
      );
      await loadRules();
    } else {
      alert(
        tParams("ibRuleMgmt_alert_copyFail", "Failed to copy rule: {msg}", {
          msg: response.message ?? "",
        }),
      );
    }
  } catch (error) {
    console.error("Failed to copy rule:", error);
    alert(t("ibRuleMgmt_alert_copyFailGeneric"));
  }
};

/**
 * 删除规则
 */
const deleteRule = async (ruleId) => {
  const rule = rules.value.find((r) => r.id === ruleId);
  if (!rule) return;

  const confirmMessage = tParams(
    "ibRuleMgmt_confirm_delete",
    "Delete Rule\n\nAre you sure you want to delete this rule?\n\nRule: {name}\nStatus: {status}\n\n⚠️ This action cannot be undone!",
    { name: rule.ruleName || "", status: formatRuleStatus(rule.status) },
  );

  if (!confirm(confirmMessage)) return;

  if (rule.status === "active" && rule.assignedIbCount > 0) {
    const finalConfirm = confirm(
      tParams(
        "ibRuleMgmt_confirm_deleteActive",
        "WARNING: This is an ACTIVE rule!\n\n{count} IB partner(s) are using this rule.\n\nAre you absolutely sure you want to proceed?",
        { count: rule.assignedIbCount },
      ),
    );
    if (!finalConfirm) return;
  }

  try {
    const response = await ibRulesApi.deleteRule(ruleId);

    if (response.success) {
      alert(
        tParams(
          "ibRuleMgmt_alert_deleteOk",
          'Rule deleted successfully.\n\nRule "{name}" has been removed from the system.',
          { name: rule.ruleName || "" },
        ),
      );
      await loadRules();
    } else {
      alert(
        tParams("ibRuleMgmt_alert_deleteFail", "Failed to delete rule: {msg}", {
          msg: response.message ?? "",
        }),
      );
    }
  } catch (error) {
    console.error("Failed to delete rule:", error);

    if (error.response?.status === 409) {
      alert(t("ibRuleMgmt_alert_deleteInUse"));
    } else {
      alert(error.message || t("ibRuleMgmt_alert_deleteFailGeneric"));
    }
  }
};

/**
 * 加载规则列表（服务端分页 + 关键字搜索）
 */
const loadRules = async () => {
  try {
    loading.value = true;

    const params = {
      page: currentPage.value,
      per_page: pageSize.value === "all" ? "all" : pageSize.value,
    };
    const keyword = (searchKeyword.value || "").trim();
    if (keyword) {
      params.search = keyword;
    }

    const response = await ibRulesApi.getRules(params);

    if (response.success && response.data) {
      rules.value = response.data.items || response.data;
      if (response.data.pagination) {
        pagination.value = {
          total: response.data.pagination.total ?? 0,
          total_pages: Math.max(1, response.data.pagination.total_pages ?? 1),
          page: response.data.pagination.page ?? 1,
          per_page: response.data.pagination.per_page ?? pageSize.value,
        };
      }
    }
  } catch (error) {
    console.error("Failed to load rules:", error);
    alert(t("ibRuleMgmt_alert_loadFail"));
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  await Promise.all([loadRules(), loadPlatforms()]);
});

onBeforeUnmount(() => {
  stopPollSyncProgress();
});
</script>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.page-title h1 {
  font-size: 28px;
  color: var(--color-ink);
  margin-bottom: 5px;
}

.page-title p {
  font-size: 14px;
  color: var(--color-muted);
}

.page-actions {
  display: flex;
  align-items: center;
  gap: 20px;
}

.stats-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.page-stats {
  display: flex;
  gap: 15px;
}

.stat-badge {
  background: var(--color-brand-soft);
  color: var(--color-brand);
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
}

.stat-badge i {
  font-size: 16px;
}

.loading-state {
  text-align: center;
  padding: 100px 20px;
  color: var(--color-muted);
}

.package-table-container {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

/* Sync products from platform */
.sync-products-section {
  padding: 20px 24px;
  background: var(--color-surface-soft);
  border-bottom: 1px solid var(--color-border);
}

.sync-products-header {
  margin-bottom: 16px;
}

.sync-products-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-ink);
  margin: 0 0 6px 0;
  display: flex;
  align-items: center;
  gap: 10px;
}

.sync-products-title i {
  color: var(--color-brand);
}

.sync-products-desc {
  font-size: 13px;
  color: var(--color-muted);
  margin: 0;
  line-height: 1.5;
}

.sync-platforms-empty {
  font-size: 13px;
  color: var(--color-muted);
  margin: 0;
  font-style: italic;
}

.sync-products-cards {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
}

.sync-platform-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 18px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  min-width: 260px;
}

.sync-platform-card:hover {
  border-color: var(--color-brand-soft);
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.1);
}

.sync-platform-icon {
  width: 44px;
  height: 44px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  color: white;
  flex-shrink: 0;
}

.sync-platform-icon.mt4 {
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
}

.sync-platform-icon.mt5 {
  background: linear-gradient(135deg, var(--color-accent) 0%, #7c3aed 100%);
}

.sync-platform-icon.financepro {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
}

.sync-platform-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.sync-platform-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

.sync-platform-code {
  font-size: 12px;
  color: var(--color-muted);
}

.btn-sync-platform {
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-brand);
  background: var(--color-brand-soft);
  border: 1px solid var(--color-brand-soft);
  border-radius: var(--radius-md);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.2s ease;
}

.btn-sync-platform:hover:not(:disabled) {
  background: var(--color-brand-solid);
  color: white;
  border-color: var(--color-brand);
}

.btn-sync-platform:disabled {
  opacity: 0.7;
  cursor: not-allowed;
  color: var(--color-faint);
  background: var(--color-surface-soft);
  border-color: var(--color-border);
}

.sync-platform-action {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
}

.sync-status-text {
  font-size: 12px;
  color: var(--color-muted);
}

.ib-rules-table-scroll {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.ib-rules-table {
  min-width: 1200px;
  width: 100%;
}

.ib-rules-table th,
.ib-rules-table td {
  padding: 10px 12px;
  font-size: 13px;
  white-space: nowrap;
}

.ib-rules-table .ib-rules-desc-cell {
  max-width: 320px;
  min-width: 200px;
  white-space: normal;
  word-break: break-word;
}

.ib-rules-table .ib-rules-product-cell {
  white-space: nowrap;
}

.ib-rule-product-tags {
  display: flex;
  flex-wrap: nowrap;
  gap: 6px;
  align-items: center;
}

.ib-rule-product-tag {
  background: var(--color-surface-muted);
  color: var(--color-text);
  padding: 3px 8px;
  border-radius: var(--radius-md);
  font-size: 12px;
  font-weight: 500;
  line-height: 1.3;
  white-space: nowrap;
  flex-shrink: 0;
}

.ib-rule-product-more {
  color: var(--color-muted);
  font-size: 12px;
  font-weight: 500;
  padding: 3px 2px;
  white-space: nowrap;
  flex-shrink: 0;
}

.ib-rule-product-empty {
  color: var(--color-faint);
}

/* Action 列固定在右侧，左侧细线+柔和内阴影提示还有列可横向滑动 */
.ib-rules-table__th--sticky {
  position: sticky;
  right: 0;
  z-index: 2;
  background: var(--color-surface-soft);
  border-left: 1px solid var(--color-border);
  box-shadow: inset 12px 0 20px -10px rgba(0, 0, 0, 0.06);
}

.ib-rules-table__cell--sticky {
  position: sticky;
  right: 0;
  z-index: 1;
  background: var(--color-surface);
  border-left: 1px solid var(--color-border);
  box-shadow: inset 12px 0 20px -10px rgba(0, 0, 0, 0.06);
}

.package-table tbody tr:hover .ib-rules-table__cell--sticky {
  background: var(--color-surface-soft);
}

.ib-rules-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 20px;
  border-top: 1px solid var(--color-border);
  background: var(--color-surface-soft);
  flex-wrap: wrap;
  gap: 12px;
}

.ib-rules-pagination-info {
  font-size: 13px;
  color: var(--color-text);
  font-weight: 500;
}

.ib-rules-pagination-btns {
  display: flex;
  align-items: center;
  gap: 12px;
}

.ib-rules-pagination-page {
  font-size: 13px;
  color: var(--color-text);
  font-weight: 500;
}

.btn-pagination {
  padding: 8px 14px;
  font-size: 13px;
  background: var(--color-surface);
  color: var(--color-text);
  border: 1px solid var(--color-border);
}

.btn-pagination:hover:not(:disabled) {
  background: var(--color-surface-muted);
  border-color: var(--color-border-strong);
}

.btn-pagination:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.table-header {
  padding: 20px 30px;
  background: var(--color-surface-soft);
  border-bottom: 2px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.table-header h2 {
  font-size: 18px;
  color: var(--color-ink);
}

.table-header-left {
  display: flex;
  align-items: center;
}

.table-header-center {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}

.ib-rules-search-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.ib-rules-search-input {
  padding: 8px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 14px;
  min-width: 200px;
}

.ib-rules-search-input:focus {
  outline: none;
  border-color: var(--color-brand);
}

.btn-search {
  background: var(--color-brand-solid);
  color: white;
  padding: 8px 16px;
}

.btn-search:hover {
  background: var(--color-brand-strong);
}

.table-header-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.ib-rules-show-rows-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-left: 20px;
}

.ib-rules-show-rows-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  white-space: nowrap;
}

.ib-rules-show-rows-select {
  padding: 8px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  background: var(--color-surface);
  cursor: pointer;
}

.btn {
  padding: 12px 20px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-success {
  background: var(--color-success-solid);
  color: white;
  box-shadow: 0 2px 8px rgba(72, 187, 120, 0.3);
}

.btn-success:hover {
  background: var(--color-success-solid);
  transform: translateY(-2px);
}

.btn-sync-products {
  background: transparent;
  color: var(--color-brand);
  border: 1.5px solid #6366f1;
}

.btn-sync-products:hover:not(:disabled) {
  background: #6366f1;
  color: white;
  border-color: #6366f1;
}

.btn-sync-products:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  color: var(--color-faint);
  border-color: #cbd5e1;
}

.package-table {
  width: 100%;
  border-collapse: collapse;
}

.package-table thead {
  background: var(--color-surface-soft);
}

.package-table th {
  padding: 16px 20px;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--color-border);
}

.package-table tbody tr {
  border-bottom: 1px solid var(--color-border);
  transition: all 0.2s ease;
}

.package-table tbody tr:hover {
  background: var(--color-surface-soft);
}

.package-table tbody tr.expanded {
  background: var(--color-brand-soft);
}

.package-table td {
  padding: 16px 20px;
  font-size: 14px;
  color: var(--color-text);
}

.package-info {
  display: flex;
  flex-direction: column;
}

.package-name {
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 4px;
  font-size: 15px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.package-description {
  font-size: 12px;
  color: var(--color-muted);
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.active {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.inactive {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.status-badge.draft {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.action-buttons {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-edit {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.btn-edit:hover {
  background: var(--color-brand-solid);
  color: white;
  transform: translateY(-2px);
}

.ib-rules-desc-cell {
  max-width: 320px;
  min-width: 200px;
  word-break: break-word;
  text-align: left;
}

.btn-action {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-detail {
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.btn-detail:hover {
  background: var(--color-brand-solid);
  color: white;
}

.btn-icon {
  padding: 8px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
}

.btn-copy {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.btn-copy:hover {
  background: #0284c7;
  color: white;
  transform: translateY(-2px);
}

.btn-delete {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.btn-delete:hover {
  background: var(--color-danger-solid);
  color: white;
  transform: translateY(-2px);
}

.detail-row {
  background: var(--color-surface-soft);
}

@media (max-width: 768px) {
  .container {
    padding: 20px 15px;
  }

  .page-header,
  .stats-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
  }
}
</style>
