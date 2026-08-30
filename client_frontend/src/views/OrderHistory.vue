<template>
  <div class="history-container ui-page">
    <!-- Header with Search, Filters and Actions -->
    <div class="history-header">
      <div class="header-left">
        <div class="history-search">
          <input
            id="historySearch"
            v-model="search.keywords"
            type="text"
            :placeholder="t('orderHistorySearchOrders', 'Search orders...')"
            @keyup.enter="handleSearch"
          />
          <button type="button" @click="handleSearch" class="search-btn">
            <i class="fas fa-search"></i>
          </button>
        </div>
        <div class="history-filter">
          <CustomSelect
            id="historyFilterType"
            v-model="search.cmd"
            :options="orderTypeList"
            @change="handleSearch"
          />
        </div>
      </div>
      <div class="header-right">
        <div class="date-range">
          <label for="dateFrom">{{
            t("transactionsDateRange", "Date Range")
          }}</label>
          <div class="date-inputs">
            <input id="dateFrom" v-model="search.periodFrom" type="date" />
            <span class="date-separator">{{ t("transactionsTo", "To") }}</span>
            <input id="dateTo" v-model="search.periodTo" type="date" />
          </div>
          <button id="applyDateBtn" class="apply-btn" @click="handleSearch">
            <i class="fas fa-check"></i>
            {{ t("transactionsApply", "Apply") }}
          </button>
        </div>
        <button id="exportBtn" class="export-btn" @click="exportCsv">
          <i class="fas fa-file-export"></i>
          {{ t("transactionsExport", "Export") }}
        </button>
      </div>
    </div>

    <!-- Summary Panel -->
    <div class="summary-panel">
      <div class="summary-title">
        <i class="fas fa-chart-line"></i>
        {{ t("orderHistoryTradingSummary", "Trading Summary") }}
      </div>
      <div class="summary-grid">
        <div class="summary-item">
          <div class="summary-label">
            {{ t("orderHistoryTotalTrades", "Total Trades") }}
          </div>
          <div class="summary-value">{{ totalTrades }}</div>
        </div>
        <div class="summary-item">
          <div class="summary-label">
            {{ t("orderHistoryProfitLoss", "Profit / Loss") }}
          </div>
          <div
            class="summary-value"
            :class="totalPnl >= 0 ? 'positive' : 'negative'"
          >
            {{ totalPnl >= 0 ? "+" : "" }}${{ Math.abs(totalPnl).toFixed(2) }}
          </div>
        </div>
        <div class="summary-item">
          <div class="summary-label">
            {{ t("orderHistoryWinRate", "Win Rate") }}
          </div>
          <div class="summary-value">{{ winRate }}%</div>
        </div>
        <div class="summary-item">
          <div class="summary-label">
            {{ t("orderHistoryAvgHoldingTime", "Avg. Holding Time") }}
          </div>
          <div class="summary-value">{{ avgHoldingTime }}</div>
        </div>
      </div>
      <div class="summary-chart">
        <div class="chart-container">
          <div
            class="chart-bar positive"
            :style="{ width: profitablePercentage + '%' }"
          ></div>
          <div
            class="chart-bar negative"
            :style="{ width: lossPercentage + '%' }"
          ></div>
        </div>
        <div class="chart-value">
          <div class="chart-value-item">
            <div class="chart-indicator positive"></div>
            <span
              >{{ profitableCount }}
              {{ t("orderHistoryProfitable", "Profitable") }}</span
            >
          </div>
          <div class="chart-value-item">
            <div class="chart-indicator negative"></div>
            <span>{{ lossCount }} {{ t("orderHistoryLoss", "Loss") }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- History Tabs -->
    <div class="history-tabs">
      <div
        class="history-tab"
        :class="timePeriod === 'all' ? 'active' : ''"
        @click="filterList('all')"
      >
        {{ t("orderHistoryAllTime", "All Time") }}
      </div>
      <div
        class="history-tab"
        :class="timePeriod === 'daily' ? 'active' : ''"
        @click="filterList('daily')"
      >
        {{ t("orderHistoryToday", "Today") }}
      </div>
      <div
        class="history-tab"
        :class="timePeriod === 'weekly' ? 'active' : ''"
        @click="filterList('weekly')"
      >
        {{ t("orderHistoryThisWeek", "This Week") }}
      </div>
      <div
        class="history-tab"
        :class="timePeriod === 'monthly' ? 'active' : ''"
        @click="filterList('monthly')"
      >
        {{ t("orderHistoryThisMonth", "This Month") }}
      </div>
      <div
        class="history-tab"
        :class="timePeriod === 'quarterly' ? 'active' : ''"
        @click="filterList('quarterly')"
      >
        {{ t("orderHistoryThisQuarter", "This Quarter") }}
      </div>
    </div>

    <!-- History Table -->
    <div class="table-container">
      <table class="history-table">
        <thead>
          <tr>
            <th>{{ t("tradingPlatform", "Platform") }}</th>
            <th class="sortable" @click="sortHistory('Id')">
              {{ t("orderHistoryOrderId", "Order ID") }}
              <i class="sort-icon" :class="getSortIcon('Id')"></i>
            </th>
            <th class="sortable" @click="sortHistory('Symbol')">
              {{ t("orderHistorySymbol", "Symbol") }}
              <i class="sort-icon" :class="getSortIcon('Symbol')"></i>
            </th>
            <th class="sortable" @click="sortHistory('Cmd')">
              {{ t("orderHistoryType", "Type") }}
              <i class="sort-icon" :class="getSortIcon('Cmd')"></i>
            </th>
            <th class="sortable" @click="sortHistory('Volume')">
              {{ t("orderHistorySize", "Size") }}
              <i class="sort-icon" :class="getSortIcon('Volume')"></i>
            </th>
            <th class="sortable" @click="sortHistory('OpenPrice')">
              {{ t("orderHistoryOpenPrice", "Open Price") }}
              <i class="sort-icon" :class="getSortIcon('OpenPrice')"></i>
            </th>
            <th class="sortable" @click="sortHistory('ClosePrice')">
              {{ t("orderHistoryClosePrice", "Close Price") }}
              <i class="sort-icon" :class="getSortIcon('ClosePrice')"></i>
            </th>
            <th class="sortable" @click="sortHistory('Profit')">
              {{ t("tradingProfit", "P/L") }}
              <i class="sort-icon" :class="getSortIcon('Profit')"></i>
            </th>
            <th class="sortable" @click="sortHistory('CloseTime')">
              {{ t("orderHistoryDate", "Date") }}
              <i class="sort-icon" :class="getSortIcon('CloseTime')"></i>
            </th>
            <!--              <th width="100px">{{ t('orderHistoryActions', 'Actions') }}</th>-->
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && displayHistoryOrder.length === 0">
            <td colspan="10" class="empty-state-cell">
              <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>{{ t("orderHistoryNoOrders", "No orders found") }}</p>
              </div>
            </td>
          </tr>
          <tr v-if="loading && displayHistoryOrder.length === 0">
            <td colspan="10" class="empty-state-cell">
              <div class="empty-state">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading...</p>
              </div>
            </td>
          </tr>
          <tr v-for="(order, index) in displayHistoryOrder" :key="index">
            <td>{{ order.PlatformName || "-" }}</td>
            <td>
              <div>{{ order.Id }}</div>
            </td>
            <td>
              <div class="symbol-cell">
                <div class="symbol-icon stock-icon">
                  {{ order.security }}
                </div>
                <div class="symbol-details">
                  <span class="symbol-name">{{ order.Source }}</span>
                  <span class="symbol-fullname">{{ order.Symbol }}</span>
                </div>
              </div>
            </td>
            <td>
              <span class="trade-label" :class="order.SideBadge">
                {{ order.SideName }}
              </span>
            </td>
            <!-- volume 入库为手数×100，展示真实手数需 /100 -->
            <td>{{ formatNumber(order.Volume / 100, 2) }}</td>
            <td>{{ order.OpenPrice }}</td>
            <td>{{ order.ClosePrice }}</td>
            <td
              class="pnl-cell"
              :class="order.Profit > 0 ? 'positive' : 'negative'"
            >
              {{ order.Profit > 0 ? "+" : "" }}${{
                Math.abs(order.Profit).toFixed(2)
              }}
            </td>
            <td>{{ formatDateTime(order.CloseTime) }}</td>
            <!--              <td>-->
            <!--                <div class="table-actions">-->
            <!--                  <div-->
            <!--                    class="table-action view-order"-->
            <!--                    :title="t('orderHistoryViewDetails', 'View Details')"-->
            <!--                    @click="showOrderDetail(order)"-->
            <!--                  >-->
            <!--                    <i class="fas fa-eye"></i>-->
            <!--                  </div>-->
            <!--                  <div-->
            <!--                    class="table-action"-->
            <!--                    :title="t('orderHistoryRepeatTrade', 'Repeat Trade')"-->
            <!--                    @click="repeatTrade(order)"-->
            <!--                  >-->
            <!--                    <i class="fas fa-redo"></i>-->
            <!--                  </div>-->
            <!--                </div>-->
            <!--              </td>-->
          </tr>
        </tbody>
      </table>
      <!-- 加载更多指示器 -->
      <div
        v-if="loading && displayHistoryOrder.length > 0"
        class="loading-more"
      >
        <i class="fas fa-spinner fa-spin"></i>
        <span>{{ t("orderHistoryLoading", "Loading more...") }}</span>
      </div>
      <div v-if="!hasMore && displayHistoryOrder.length > 0" class="no-more">
        <p>{{ t("orderHistoryNoMore", "No more orders") }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import CustomSelect from "@/components/common/CustomSelect.vue";
import { useLanguageStore } from "@/stores/language";
import tradingAccountService from "@/services/tradingAccountService";
import { formatNumber } from "@/utils/helpers";

// Language store
const languageStore = useLanguageStore();

// Translation function
const t = (key, fallback = "") => languageStore.t(key, fallback);

// Search and filter state
const search = ref({
  cmd: "",
  keywords: "",
  periodFrom: "",
  periodTo: "",
});

const timePeriod = ref("all");
const historySortField = ref("Id");
const historySortOrder = ref("desc");

// Loading and pagination state
const loading = ref(false);
const currentPage = ref(1);
const pageSize = ref(100);
const hasMore = ref(true);
const filters = ref({
  Status: [],
  Side: [],
  SecurityType: [],
  DatePeriod: [],
});

// Order type list
const orderTypeList = computed(() => [
  { label: t("orderHistoryAllTypes", "All Types"), value: "" },
  { label: t("orderHistoryBuyOrders", "Buy Orders"), value: "0,2,4" },
  { label: t("orderHistorySellTypes", "Sell Types"), value: "1,3,5" },
  { label: t("orderHistoryLimitTypes", "Limit Types"), value: "2,3" },
  { label: t("orderHistoryStopTypes", "Stop Types"), value: "4,5" },
]);

const historyOrder = ref([]);
const displayHistoryOrder = ref([]);

// 处理订单数据，添加辅助字段
const processOrderData = (orders) => {
  return orders.map((order) => {
    const processed = { ...order };
    processed.SideName = getOrderSide(order);
    processed.SideBadge =
      processed.SideName.indexOf("BUY") !== -1 ||
      processed.SideName.indexOf("买入") !== -1
        ? "buy"
        : "sell";
    processed.Profit = parseFloat(order.Profit) || 0;
    // 从API返回的数据中提取security信息（如果有SecurityType）
    if (!processed.security) {
      processed.security = "Forex"; // 默认值
    }
    return processed;
  });
};

// 之前的processOrderData函数被移到这里，避免重复定义

// Computed properties for summary
const totalTrades = computed(() => displayHistoryOrder.value.length);

const totalPnl = computed(() => {
  return displayHistoryOrder.value.reduce((sum, order) => {
    return sum + (order.Profit || 0);
  }, 0);
});

const profitableCount = computed(() => {
  return displayHistoryOrder.value.filter((order) => order.Profit > 0).length;
});

const lossCount = computed(() => {
  return displayHistoryOrder.value.filter((order) => order.Profit <= 0).length;
});

const winRate = computed(() => {
  const total = profitableCount.value + lossCount.value;
  if (total === 0) return "0.00";
  return ((profitableCount.value / total) * 100).toFixed(2);
});

const profitablePercentage = computed(() => {
  const total = profitableCount.value + lossCount.value;
  if (total === 0) return 0;
  return (profitableCount.value / total) * 100;
});

const lossPercentage = computed(() => {
  const total = profitableCount.value + lossCount.value;
  if (total === 0) return 0;
  return (lossCount.value / total) * 100;
});

const avgHoldingTime = computed(() => {
  if (displayHistoryOrder.value.length === 0) return "0h 0m";

  let totalHoldTime = 0;
  let count = 0;
  displayHistoryOrder.value.forEach((order) => {
    if (
      order.CloseTime &&
      order.OpenTime &&
      order.CloseTime > 0 &&
      order.OpenTime > 0
    ) {
      totalHoldTime += order.CloseTime - order.OpenTime;
      count++;
    }
  });

  if (count === 0) return "0h 0m";

  const avgTime = Math.floor(totalHoldTime / count);
  const hour = Math.floor(avgTime / 3600);
  const minute = Math.floor((avgTime % 3600) / 60);

  return `${hour}h ${minute}m`;
});

const getOrderSide = (order) => {
  const sideMap = {
    0: t("tradingBuy", "BUY"),
    1: t("tradingSell", "SELL"),
    2: t("tradingBuy", "BUY") + " " + t("tradingLimit", "Limit"),
    3: t("tradingSell", "SELL") + " " + t("tradingLimit", "Limit"),
    4: t("tradingBuy", "BUY") + " " + t("tradingStop", "Stop"),
    5: t("tradingSell", "SELL") + " " + t("tradingStop", "Stop"),
    // 8 / 9：buy / sell stop limit
    8:
      t("tradingBuy", "BUY") +
      " " +
      t("tradingStop", "Stop") +
      " " +
      t("tradingLimit", "Limit"),
    9:
      t("tradingSell", "SELL") +
      " " +
      t("tradingStop", "Stop") +
      " " +
      t("tradingLimit", "Limit"),
  };
  return sideMap[order.Cmd] || "UNKNOWN";
};

const formatDateTime = (timestamp) => {
  if (!timestamp) return "";
  const date = new Date(timestamp * 1000);
  const lang = languageStore.currentLanguage || "en";
  const locale = lang === "zh" ? "zh-CN" : "en-US";
  return date.toLocaleString(locale, {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
};

// 获取历史订单数据
const fetchOrderHistory = async (page = 1, append = false) => {
  if (loading.value) return;

  try {
    loading.value = true;

    // 构建查询参数
    const params = {
      page: page,
      pageSize: pageSize.value,
      sort: historySortField.value,
      sortOrder: historySortOrder.value,
      keywords: search.value.keywords || null,
      cmd: search.value.cmd || null,
      periodFrom: search.value.periodFrom || null,
      periodTo: search.value.periodTo || null,
    };

    // 根据时间周期设置日期范围
    const now = new Date();
    const todayStart = new Date(now.setHours(0, 0, 0, 0));

    switch (timePeriod.value) {
      case "daily":
        params.periodFrom = todayStart.toISOString().split("T")[0];
        break;
      case "weekly": {
        const weekStart = new Date(
          todayStart.getTime() - (now.getDay() - 1) * 24 * 60 * 60 * 1000,
        );
        params.periodFrom = weekStart.toISOString().split("T")[0];
        break;
      }
      case "monthly": {
        const monthStart = new Date(
          todayStart.getFullYear(),
          todayStart.getMonth(),
          1,
        );
        params.periodFrom = monthStart.toISOString().split("T")[0];
        break;
      }
      case "quarterly": {
        const quarterStart = new Date(
          todayStart.getTime() - 90 * 24 * 60 * 60 * 1000,
        );
        params.periodFrom = quarterStart.toISOString().split("T")[0];
        break;
      }
    }

    const response = await tradingAccountService.getOrderHistory(params);
    const data = response?.data ?? response ?? {};

    const items = data.items || [];
    const pagination = data.pagination || {};

    // 保存筛选选项
    if (data.filters) {
      filters.value = data.filters;
    }

    // 处理订单数据
    const processedItems = processOrderData(items);

    if (append) {
      // 追加模式：将新数据追加到现有列表
      historyOrder.value = [...historyOrder.value, ...processedItems];
    } else {
      // 替换模式：替换整个列表
      historyOrder.value = processedItems;
      currentPage.value = 1;
    }

    displayHistoryOrder.value = [...historyOrder.value];

    // 更新分页状态
    currentPage.value = pagination.currentPage || page;
    hasMore.value =
      pagination.hasMore !== false && items.length >= pageSize.value;
  } catch (error) {
    console.error("Failed to fetch order history", error);
    // 如果API失败，保持现有数据或显示空状态
  } finally {
    loading.value = false;
  }
};

const handleSearch = () => {
  // 重置分页并重新获取数据
  currentPage.value = 1;
  hasMore.value = true;
  fetchOrderHistory(1, false);
};

const filterList = (period) => {
  timePeriod.value = period;
  // 重新获取数据，而不是在前端过滤
  currentPage.value = 1;
  hasMore.value = true;
  fetchOrderHistory(1, false);
};

const sortHistory = (field) => {
  if (historySortField.value === field) {
    historySortOrder.value = historySortOrder.value === "asc" ? "desc" : "asc";
  } else {
    historySortField.value = field;
    historySortOrder.value = "desc";
  }
  // 重新获取数据以应用排序
  currentPage.value = 1;
  hasMore.value = true;
  fetchOrderHistory(1, false);
};

const getSortIcon = (field) => {
  if (historySortField.value !== field) {
    return "fas fa-sort";
  }
  return historySortOrder.value === "asc"
    ? "fas fa-sort-up"
    : "fas fa-sort-down";
};

const exportCsv = () => {
  // CSV导出
  const headers = [
    t("orderHistoryOrderId", "Order ID"),
    t("orderHistorySymbol", "Symbol"),
    t("orderHistoryType", "Type"),
    t("orderHistorySize", "Size"),
    t("orderHistoryOpenPrice", "Open Price"),
    t("orderHistoryClosePrice", "Close Price"),
    t("tradingProfit", "P/L"),
    t("orderHistoryDate", "Date"),
  ];
  const rows = displayHistoryOrder.value.map((order) => [
    order.Id,
    order.Symbol,
    order.SideName,
    order.Volume / 100,
    order.OpenPrice,
    order.ClosePrice,
    order.Profit,
    formatDateTime(order.CloseTime),
  ]);

  const csvContent = [
    headers.join(","),
    ...rows.map((row) => row.join(",")),
  ].join("\n");

  const blob = new Blob(["\uFEFF" + csvContent], {
    type: "text/csv;charset=utf-8;",
  });
  const link = document.createElement("a");
  const url = URL.createObjectURL(blob);
  link.setAttribute("href", url);
  link.setAttribute(
    "download",
    `trading_history_${new Date().toISOString().split("T")[0]}.csv`,
  );
  link.style.visibility = "hidden";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

// 滚动到底部加载更多
const handleScroll = () => {
  const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
  const windowHeight = window.innerHeight;
  const documentHeight = document.documentElement.scrollHeight;

  // 当滚动到距离底部100px时加载更多
  if (scrollTop + windowHeight >= documentHeight - 100) {
    if (hasMore.value && !loading.value) {
      fetchOrderHistory(currentPage.value + 1, true);
    }
  }
};

// Initialize
onMounted(() => {
  // 获取历史订单数据
  fetchOrderHistory(1, false);

  // 添加滚动监听
  window.addEventListener("scroll", handleScroll);
});

onUnmounted(() => {
  // 移除滚动监听
  window.removeEventListener("scroll", handleScroll);
});
</script>

<style scoped>
.order-history-page {
  padding: 40px 20px;
  max-width: 1400px;
  margin: 0 auto;
}

.history-container {
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  padding: 30px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
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
  border: 1px solid var(--color-border);
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
  min-width: 250px;
}

.history-search input {
  flex: 1;
  padding: 12px 16px;
  border: 1px solid var(--color-border);
  border-top-right-radius: 0;
  border-bottom-right-radius: 0;
  border-top-left-radius: 8px;
  border-bottom-left-radius: 8px;
  border-right: none;
  font-size: 14px;
  transition:
    border-color 0.3s ease,
    background-color 0.3s ease,
    box-shadow 0.3s ease;
  background: var(--color-surface);
  color: var(--color-ink);
  height: 44px;
  box-sizing: border-box;
}

.history-search input:focus {
  border-color: var(--color-brand);
  box-shadow: none;
}

.history-search input::placeholder {
  color: var(--color-faint);
}

.search-btn {
  background: var(--color-brand-solid);
  border: 1px solid var(--color-brand);
  border-left: none;
  padding: 0 20px;
  color: white;
  border-top-right-radius: 8px;
  border-bottom-right-radius: 8px;
  cursor: pointer;
  transition:
    background-color 0.3s ease,
    color 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 48px;
  height: 44px;
  box-sizing: border-box;
}

.search-btn:hover {
  opacity: 0.9;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(var(--color-brand-rgb), 0.3);
}

.history-filter {
  min-width: 160px;
}

.history-filter select {
  width: 100%;
  padding: 12px 16px;
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  color: var(--color-ink);
  font-size: 14px;
  cursor: pointer;
  transition:
    border-color 0.3s ease,
    background-color 0.3s ease;
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

  box-shadow: none;
}

/* Date Range - Integrated in header */
.date-range {
  display: flex;
  align-items: center;
  gap: 12px;
  background: var(--color-surface);
  padding: 0;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  transition:
    border-color 0.3s ease,
    box-shadow 0.3s ease;
  height: 44px;
}

.date-range:hover {
  border-color: var(--color-border-strong);
}

.date-range label {
  color: var(--color-text);
  font-size: 14px;
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
  background-color: var(--color-surface);
  border: none;
  border-radius: 0;
  color: var(--color-ink);
  font-size: 14px;
  transition:
    background-color 0.3s ease,
    color 0.3s ease;
  min-width: 140px;
  height: 100%;
}

.date-range input:focus {
  background-color: var(--color-surface-soft);
}

.date-separator {
  color: var(--color-muted);
  font-size: 14px;
  font-weight: 500;
  padding: 0 4px;
}

.apply-btn {
  background: var(--color-brand-solid);
  border: none;
  color: white;
  padding: 0 18px;
  border-radius: 0 6px 6px 0;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  transition:
    background-color 0.3s ease,
    color 0.3s ease;
  white-space: nowrap;
  font-size: 14px;
  font-weight: 600;
  height: 100%;
  box-shadow: none;
}

.apply-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.3);
}

.export-btn {
  background: var(--color-surface);
  border: 1px solid var(--color-brand);
  color: var(--color-brand);
  padding: 12px 20px;
  border-radius: var(--radius-md);
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition:
    background-color 0.3s ease,
    color 0.3s ease;
  white-space: nowrap;
  font-size: 14px;
  font-weight: 600;
  align-self: flex-start;
  height: 44px;
  box-sizing: border-box;
}

.export-btn:hover {
  background: var(--color-brand-solid);
  color: white;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.3);
}

/* Summary Panel */
.summary-panel {
  background: linear-gradient(
    135deg,
    var(--color-surface-soft) 0%,
    var(--color-surface-muted) 100%
  );
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  margin-bottom: 25px;
  padding: 25px;
}

.summary-title {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 20px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 10px;
}

.summary-title i {
  color: var(--color-brand);
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-bottom: 20px;
}

.summary-item {
  display: flex;
  flex-direction: column;
  background: var(--color-surface);
  padding: 15px;
  border-radius: var(--radius-md);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.summary-label {
  font-size: 14px;
  color: var(--color-muted);
  margin-bottom: 8px;
  font-weight: 600;
}

.summary-value {
  font-size: 20px;
  font-weight: 700;
  color: var(--color-ink);
}

.summary-value.positive {
  color: var(--color-success);
}

.summary-value.negative {
  color: var(--color-danger);
}

.summary-chart {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-top: 20px;
}

.chart-container {
  flex-grow: 1;
  height: 40px;
  background-color: var(--color-surface);
  border-radius: var(--radius-md);
  overflow: hidden;
  position: relative;
  box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
}

.chart-bar {
  height: 100%;
  position: absolute;
  top: 0;
  transition: width 0.3s ease;
}

.chart-bar.positive {
  background: linear-gradient(
    90deg,
    var(--color-success-solid) 0%,
    var(--color-success-solid) 100%
  );
  left: 0;
}

.chart-bar.negative {
  background: linear-gradient(
    90deg,
    var(--color-danger-border) 0%,
    var(--color-danger-solid) 100%
  );
  right: 0;
}

.chart-value {
  display: flex;
  gap: 20px;
  flex-shrink: 0;
}

.chart-value-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: var(--color-text);
  font-weight: 600;
}

.chart-indicator {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.chart-indicator.positive {
  background-color: var(--color-success-solid);
}

.chart-indicator.negative {
  background-color: var(--color-danger-border);
}

/* Tabs */
.history-tabs {
  display: flex;
  margin-bottom: 20px;
  border-bottom: 1px solid var(--color-border);
  overflow-x: auto;
  overflow-y: hidden;
}

.history-tab {
  padding: 12px 24px;
  cursor: pointer;
  transition:
    background-color 0.3s ease,
    color 0.3s ease,
    border-color 0.3s ease;
  position: relative;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-muted);
  white-space: nowrap;
}

.history-tab:hover {
  color: var(--color-brand);
}

.history-tab.active {
  color: var(--color-brand);
}

.history-tab.active::after {
  content: "";
  position: absolute;
  left: 0;
  right: 0;
  bottom: -2px;
  height: 3px;
  background: var(--color-brand-solid);
  border-radius: 3px 3px 0 0;
}

/* Table */
.table-container {
  overflow-x: auto;
  overflow-y: hidden;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
}

.history-table {
  width: 100%;
  border-collapse: collapse;
  background: var(--color-surface);
}

.history-table thead {
  background: linear-gradient(
    135deg,
    var(--color-surface-soft) 0%,
    var(--color-surface-muted) 100%
  );
}

.history-table th {
  padding: 15px 12px;
  text-align: left;
  font-size: 14px;
  font-weight: 700;
  color: var(--color-ink);
  border-bottom: 1px solid var(--color-border);
  white-space: nowrap;
}

.history-table td {
  padding: 15px 12px;
  border-bottom: 1px solid var(--color-border);
  font-size: 14px;
  color: var(--color-text);
}

.history-table tbody tr:hover {
  background-color: var(--color-surface-soft);
}

.history-table tbody tr:last-child td {
  border-bottom: none;
}

.sortable {
  cursor: pointer;
  user-select: none;
  position: relative;
  transition: background-color 0.2s ease;
}

.sortable:hover {
  background-color: var(--color-surface-muted);
}

.sort-icon {
  margin-left: 8px;
  /* @font-floor-exempt: visual-only sort glyph */
  font-size: 12px;
  opacity: 0.5;
  transition: opacity 0.2s ease;
}

.sortable:hover .sort-icon {
  opacity: 1;
}

.sortable .fa-sort-up,
.sortable .fa-sort-down {
  opacity: 1;
  color: var(--color-brand);
}

/* Trade Label */
.trade-label {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-sm);
  font-size: 14px;
  text-align: center;
  min-width: 70px;
  font-weight: 600;
}

.trade-label.buy {
  background-color: rgba(34, 197, 94, 0.15);
  color: var(--color-success);
}

.trade-label.sell {
  background-color: rgba(239, 68, 68, 0.15);
  color: var(--color-danger);
}

.pnl-cell {
  font-weight: 700;
  font-size: 15px;
}

.pnl-cell.positive {
  color: var(--color-success);
}

.pnl-cell.negative {
  color: var(--color-danger);
}

.order-id {
  color: var(--color-muted);
  font-size: 14px;
  margin-top: 4px;
}

.symbol-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.symbol-icon {
  width: 48px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(
    135deg,
    var(--color-brand-soft) 0%,
    var(--color-brand-soft) 100%
  );
  border-radius: var(--radius-sm);
  /* @font-floor-exempt: visual-only symbol mark */
  font-size: 11px;
  font-weight: 700;
  color: var(--color-brand);
}

.symbol-details {
  display: flex;
  flex-direction: column;
}

.symbol-name {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
}

.symbol-fullname {
  font-size: 14px;
  color: var(--color-muted);
}

.table-actions {
  display: flex;
  gap: 8px;
  justify-content: center;
}

.table-action {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition:
    background-color 0.3s ease,
    color 0.3s ease;
  color: var(--color-muted);
  background: var(--color-surface-soft);
}

.table-action:hover {
  background: var(--color-brand-solid);
  color: white;
  transform: translateY(-2px);
}

.empty-state-cell {
  text-align: center;
  padding: 60px 20px;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 15px;
  color: var(--color-faint);
}

.empty-state i {
  font-size: 48px;
}

.empty-state p {
  font-size: 16px;
  margin: 0;
}

/* Responsive */
@media (max-width: 992px) {
  .history-header {
    flex-direction: column;
    gap: 15px;
  }

  .header-left {
    width: 100%;
    flex-direction: column;
  }

  .header-right {
    width: 100%;
    flex-direction: column;
  }

  .date-range {
    width: 100%;
  }

  .export-btn {
    width: 100%;
    justify-content: center;
  }

  .summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .order-history-page {
    padding: 20px 15px;
  }

  .history-container {
    padding: 20px;
  }

  .history-header {
    padding: 15px;
  }

  .header-left {
    min-width: 100%;
  }

  .history-search {
    min-width: 100%;
  }

  .history-filter {
    width: 100%;
  }

  .date-range {
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
  }

  .date-range input {
    width: 100%;
    min-width: 100%;
    height: 44px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
  }

  .date-separator {
    display: none;
  }

  .apply-btn {
    width: 100%;
    justify-content: center;
    height: 44px;
    border-radius: var(--radius-sm);
    margin-top: 8px;
  }

  .summary-grid {
    grid-template-columns: 1fr;
  }

  .table-container {
    overflow-x: scroll;
  }

  .history-table {
    min-width: 1000px;
  }
}

@media (max-width: 576px) {
  .history-tabs {
    overflow-x: auto;
  }

  .history-tab {
    white-space: nowrap;
  }

  .chart-value {
    flex-direction: column;
    gap: 10px;
  }

  .history-header {
    padding: 12px;
  }
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
  color: var(--color-brand);
}

.no-more p {
  margin: 0;
  color: var(--color-faint);
}
</style>
