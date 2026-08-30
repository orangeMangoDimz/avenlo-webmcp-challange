<template>
  <div class="positions-container ui-page">
    <!-- Tab Navigation -->
    <div class="positions-tabs">
      <div
        class="positions-tab"
        :class="activeTab === 'openPositions' ? 'active' : ''"
        @click="activeTab = 'openPositions'"
      >
        <i class="fas fa-list"></i>
        {{ t("tradingOpenPositions", "Open Positions") }}
        <span class="tab-counter" v-if="openPositions.length > 0">{{
          openPositions.length
        }}</span>
      </div>
      <div
        class="positions-tab"
        :class="activeTab === 'pendingOrders' ? 'active' : ''"
        @click="activeTab = 'pendingOrders'"
      >
        <i class="fas fa-clock"></i>
        {{ t("tradingPendingOrders", "Pending Orders") }}
        <span class="tab-counter" v-if="pendingOrders.length > 0">{{
          pendingOrders.length
        }}</span>
      </div>
    </div>

    <!-- Open Positions Table -->
    <div v-show="activeTab === 'openPositions'" class="table-panel">
      <div class="table-container">
        <table class="positions-table">
          <thead>
            <tr>
              <th>{{ t("tradingPlatform", "Platform") }}</th>
              <th>{{ t("tradingSymbol", "Symbol") }}</th>
              <th>{{ t("tradingType", "Type") }}</th>
              <th>{{ t("tradingLots", "Lots") }}</th>
              <th>{{ t("tradingEntry", "Entry") }}</th>
              <th>{{ t("tradingCurrent", "Current") }}</th>
              <th>{{ t("tradingStopLoss", "Stop Loss") }}</th>
              <th>{{ t("tradingTakeProfit", "Take Profit") }}</th>
              <th>{{ t("tradingProfit", "P/L") }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!loadingPositions && openPositions.length === 0">
              <td colspan="9" class="empty-state-cell">
                <div class="empty-state">
                  <i class="fas fa-inbox"></i>
                  <p>{{ t("tradingNoOpenPositions", "No open positions") }}</p>
                </div>
              </td>
            </tr>
            <tr v-if="loadingPositions && openPositions.length === 0">
              <td colspan="9" class="empty-state-cell">
                <div class="empty-state">
                  <i class="fas fa-spinner fa-spin"></i>
                  <p>Loading...</p>
                </div>
              </td>
            </tr>
            <tr v-for="(position, index) in openPositions" :key="index">
              <td>{{ position.PlatformName || "-" }}</td>
              <td>
                <div class="symbol-cell">
                  <div class="symbol-icon stock-icon">
                    {{ position.SecurityName || position.security || "Forex" }}
                  </div>
                  <div class="symbol-details">
                    <span class="symbol-name">{{ position.Symbol }}</span>
                  </div>
                </div>
              </td>
              <td>
                <span class="trade-label" :class="position.SideBadge">
                  {{ position.SideName }}
                </span>
              </td>
              <!-- volume 入库为手数×100，展示真实手数需 /100 -->
              <td>{{ formatNumber(position.Volume / 100, 2) }}</td>
              <td>{{ formatPrice(position.OpenPrice, position) }}</td>
              <td>{{ formatPrice(position.ClosePrice, position) }}</td>
              <td>{{ position.Sl || "-" }}</td>
              <td>{{ position.Tp || "-" }}</td>
              <td
                class="pnl-cell"
                :class="position.Profit > 0 ? 'positive' : 'negative'"
              >
                {{ position.Profit > 0 ? "+" : "" }}${{
                  Math.abs(position.Profit).toFixed(2)
                }}
              </td>
            </tr>
          </tbody>
        </table>
        <!-- 加载更多指示器 - 暂时注释掉分页功能 -->
        <!-- <div v-if="loadingPositions && openPositions.length > 0" class="loading-more">
            <i class="fas fa-spinner fa-spin"></i>
            <span>{{ t('positionsLoading', 'Loading more...') }}</span>
          </div>
          <div v-if="!hasMorePositions && openPositions.length > 0" class="no-more">
            <p>{{ t('positionsNoMore', 'No more positions') }}</p>
          </div> -->
      </div>
    </div>

    <!-- Pending Orders Table -->
    <div v-show="activeTab === 'pendingOrders'" class="table-panel">
      <div class="table-container">
        <table class="positions-table">
          <thead>
            <tr>
              <th>{{ t("tradingPlatform", "Platform") }}</th>
              <th>{{ t("pendingOrdersSymbol", "Symbol") }}</th>
              <th>{{ t("pendingOrdersType", "Type") }}</th>
              <th>{{ t("pendingOrdersLots", "Lots") }}</th>
              <th>{{ t("pendingOrdersPrice", "Price") }}</th>
              <th>{{ t("pendingOrdersStopLoss", "Stop Loss") }}</th>
              <th>{{ t("pendingOrdersTakeProfit", "Take Profit") }}</th>
              <th>{{ t("pendingOrdersCreated", "Created") }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!loadingPending && pendingOrders.length === 0">
              <td colspan="8" class="empty-state-cell">
                <div class="empty-state">
                  <i class="fas fa-inbox"></i>
                  <p>
                    {{ t("pendingOrdersNoPendingOrders", "No pending orders") }}
                  </p>
                </div>
              </td>
            </tr>
            <tr v-if="loadingPending && pendingOrders.length === 0">
              <td colspan="8" class="empty-state-cell">
                <div class="empty-state">
                  <i class="fas fa-spinner fa-spin"></i>
                  <p>Loading...</p>
                </div>
              </td>
            </tr>
            <tr v-for="(order, index) in pendingOrders" :key="index">
              <td>{{ order.PlatformName || "-" }}</td>
              <td>
                <div class="symbol-cell">
                  <div class="symbol-icon stock-icon">
                    {{ order.SecurityName || order.security || "Forex" }}
                  </div>
                  <div class="symbol-details">
                    <span class="symbol-name">{{ order.Symbol }}</span>
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
              <td>
                {{ formatPrice(order.TargetPrice || order.OpenPrice, order) }}
              </td>
              <td>{{ order.Sl || "-" }}</td>
              <td>{{ order.Tp || "-" }}</td>
              <td>{{ formatDateTime(order.CloseTime || order.OpenTime) }}</td>
            </tr>
          </tbody>
        </table>
        <!-- 加载更多指示器 - 暂时注释掉分页功能 -->
        <!-- <div v-if="loadingPending && pendingOrders.length > 0" class="loading-more">
            <i class="fas fa-spinner fa-spin"></i>
            <span>{{ t('pendingOrdersLoading', 'Loading more...') }}</span>
          </div>
          <div v-if="!hasMorePending && pendingOrders.length > 0" class="no-more">
            <p>{{ t('pendingOrdersNoMore', 'No more pending orders') }}</p>
          </div> -->
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from "vue";
import { useLanguageStore } from "@/stores/language";
import tradingAccountService from "@/services/tradingAccountService";
import { formatNumber } from "@/utils/helpers";

// Language store
const languageStore = useLanguageStore();

// Translation function
const t = (key, fallback = "") => languageStore.t(key, fallback);

// Active tab
const activeTab = ref("openPositions");

// Loading state for Open Positions
const loadingPositions = ref(false);
// 暂时注释掉分页相关代码
// const currentPagePositions = ref(1)
// const pageSizePositions = ref(100)
// const hasMorePositions = ref(true)

// Loading state for Pending Orders
const loadingPending = ref(false);
// 暂时注释掉分页相关代码
// const currentPagePending = ref(1)
// const pageSizePending = ref(100)
// const hasMorePending = ref(true)

const openPositions = ref([]);
const pendingOrders = ref([]);

// Methods
const processOpenPositions = (positions) => {
  return positions.map((position) => {
    const processed = { ...position };
    if (!processed.SideName) {
      processed.SideName = getOrderSide(position);
    }
    if (!processed.SideBadge) {
      const sideNameUpper = processed.SideName.toUpperCase();
      if (
        sideNameUpper.indexOf("BUY") !== -1 ||
        sideNameUpper.indexOf("买入") !== -1
      ) {
        processed.SideBadge = "buy";
      } else if (
        sideNameUpper.indexOf("SELL") !== -1 ||
        sideNameUpper.indexOf("卖出") !== -1
      ) {
        processed.SideBadge = "sell";
      } else {
        processed.SideBadge =
          processed.Cmd === 0 || processed.Cmd === 2 || processed.Cmd === 4
            ? "buy"
            : "sell";
      }
    }
    processed.Profit = parseFloat(processed.Profit) || 0;
    if (!processed.digits) processed.digits = 5;
    return processed;
  });
};

const processPendingOrders = (orders) => {
  return orders.map((order) => {
    const processed = { ...order };
    if (!processed.SideName) {
      processed.SideName = getOrderSide(order);
    }
    if (!processed.SideBadge) {
      const sideNameUpper = processed.SideName.toUpperCase();
      const hasLimit =
        sideNameUpper.indexOf("LIMIT") !== -1 ||
        sideNameUpper.indexOf("限价") !== -1;
      const hasStop =
        sideNameUpper.indexOf("STOP") !== -1 ||
        sideNameUpper.indexOf("止损") !== -1;
      if (hasStop && hasLimit) {
        // stop limit（8/9）单独一个徽标，和普通 limit / stop 区分
        processed.SideBadge = "stop-limit";
      } else if (hasLimit) {
        processed.SideBadge = "limit";
      } else if (hasStop) {
        processed.SideBadge = "stop";
      } else {
        processed.SideBadge = "market";
      }
    }
    if (!processed.digits) processed.digits = 5;
    return processed;
  });
};

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

const formatPrice = (price, item) => {
  if (!price) return "-";
  const digits = item.digits || item.Digits || 5;
  return parseFloat(price).toFixed(digits);
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

// 获取持仓列表（开单持仓订单）
const fetchOpenPositions = async () => {
  if (loadingPositions.value) return;

  try {
    loadingPositions.value = true;

    // 暂时注释掉分页参数
    // const params = {
    //   page: page,
    //   pageSize: pageSizePositions.value,
    //   sort: 'Id',
    //   sortOrder: 'DESC'
    // }

    const response = await tradingAccountService.getOpenPositions();
    const data = response?.data ?? response ?? {};

    const items = data.items || [];
    // 暂时注释掉分页相关代码
    // const pagination = data.pagination || {}

    // 处理持仓数据
    const processedItems = processOpenPositions(items);
    openPositions.value = processedItems;

    // 暂时注释掉分页相关代码
    // if (append) {
    //   // 追加模式：将新数据追加到现有列表
    //   openPositions.value = [...openPositions.value, ...processedItems]
    // } else {
    //   // 替换模式：替换整个列表
    //   openPositions.value = processedItems
    //   currentPagePositions.value = 1
    // }

    // 暂时注释掉分页相关代码
    // currentPagePositions.value = pagination.currentPage || page
    // hasMorePositions.value = pagination.hasMore !== false && items.length >= pageSizePositions.value
  } catch (error) {
    console.error("Failed to fetch open positions", error);
  } finally {
    loadingPositions.value = false;
  }
};

// 获取挂单列表（未开单订单）
const fetchPendingOrders = async () => {
  if (loadingPending.value) return;

  try {
    loadingPending.value = true;

    // 暂时注释掉分页参数
    // const params = {
    //   page: page,
    //   pageSize: pageSizePending.value,
    //   sort: 'Id',
    //   sortOrder: 'DESC'
    // }

    const response = await tradingAccountService.getPendingOrders();
    const data = response?.data ?? response ?? {};

    const items = data.items || [];
    // 暂时注释掉分页相关代码
    // const pagination = data.pagination || {}

    // 处理挂单数据
    const processedItems = processPendingOrders(items);
    pendingOrders.value = processedItems;

    // 暂时注释掉分页相关代码
    // if (append) {
    //   // 追加模式：将新数据追加到现有列表
    //   pendingOrders.value = [...pendingOrders.value, ...processedItems]
    // } else {
    //   // 替换模式：替换整个列表
    //   pendingOrders.value = processedItems
    //   currentPagePending.value = 1
    // }

    // 暂时注释掉分页相关代码
    // currentPagePending.value = pagination.currentPage || page
    // hasMorePending.value = pagination.hasMore !== false && items.length >= pageSizePending.value
  } catch (error) {
    console.error("Failed to fetch pending orders", error);
  } finally {
    loadingPending.value = false;
  }
};

// 滚动到底部加载更多 - 暂时注释掉分页功能
// const handleScroll = () => {
//   const scrollTop = window.pageYOffset || document.documentElement.scrollTop
//   const windowHeight = window.innerHeight
//   const documentHeight = document.documentElement.scrollHeight

//   // 当滚动到距离底部100px时加载更多
//   if (scrollTop + windowHeight >= documentHeight - 100) {
//     // Open Positions 标签页
//     if (activeTab.value === 'openPositions' && hasMorePositions.value && !loadingPositions.value) {
//       fetchOpenPositions(currentPagePositions.value + 1, true)
//     }
//     // Pending Orders 标签页
//     else if (activeTab.value === 'pendingOrders' && hasMorePending.value && !loadingPending.value) {
//       fetchPendingOrders(currentPagePending.value + 1, true)
//     }
//   }
// }

// 监听标签页切换
const watchActiveTab = () => {
  if (activeTab.value === "openPositions" && openPositions.value.length === 0) {
    fetchOpenPositions();
  } else if (
    activeTab.value === "pendingOrders" &&
    pendingOrders.value.length === 0
  ) {
    fetchPendingOrders();
  }
};

// Initialize
onMounted(() => {
  // 获取持仓列表
  fetchOpenPositions();

  // 监听标签页切换
  watch(activeTab, watchActiveTab);

  // 暂时注释掉滚动监听（分页功能）
  // window.addEventListener('scroll', handleScroll)
});

onUnmounted(() => {
  // 暂时注释掉滚动监听（分页功能）
  // window.removeEventListener('scroll', handleScroll)
});
</script>

<style scoped>
.open-positions-page {
  padding: 40px 20px;
  max-width: 1400px;
  margin: 0 auto;
}

.positions-container {
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  padding: 30px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

/* Tab Navigation */
.positions-tabs {
  display: flex;
  border-bottom: 1px solid var(--color-border);
  gap: 0;
}

.positions-tab {
  padding: 14px 24px;
  cursor: pointer;
  transition:
    background-color 0.3s ease,
    border-color 0.3s ease,
    color 0.3s ease;
  position: relative;
  font-size: 15px;
  font-weight: 600;
  color: var(--color-muted);
  display: flex;
  align-items: center;
  gap: 10px;
}

.positions-tab:hover {
  color: var(--color-brand);
}

.positions-tab.active {
  color: var(--color-brand);
}

.positions-tab.active::after {
  content: "";
  position: absolute;
  left: 0;
  right: 0;
  bottom: -2px;
  height: 3px;
  background: var(--color-brand-solid);
  border-radius: 3px 3px 0 0;
}

.positions-tab i {
  font-size: 16px;
}

.tab-counter {
  background: var(--color-brand-solid);
  color: white;
  padding: 2px 8px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
  min-width: 20px;
  text-align: center;
}

.positions-tab:not(.active) .tab-counter {
  background: var(--color-border);
  color: var(--color-muted);
}

/* Table Panel */
.table-panel {
  margin-top: 20px;
}

.table-container {
  overflow-x: auto;
  overflow-y: hidden;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
}

.positions-table {
  width: 100%;
  border-collapse: collapse;
  background: var(--color-surface);
}

.positions-table thead {
  background: linear-gradient(
    135deg,
    var(--color-surface-soft) 0%,
    var(--color-surface-muted) 100%
  );
}

.positions-table th {
  padding: 15px 12px;
  text-align: left;
  font-size: 14px;
  font-weight: 700;
  color: var(--color-ink);
  border-bottom: 1px solid var(--color-border);
  white-space: nowrap;
}

.positions-table td {
  padding: 15px 12px;
  border-bottom: 1px solid var(--color-border);
  font-size: 14px;
  color: var(--color-text);
}

.positions-table tbody tr:hover {
  background-color: var(--color-surface-soft);
}

.positions-table tbody tr:last-child td {
  border-bottom: none;
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

.trade-label.limit {
  background-color: rgba(59, 130, 246, 0.15);
  color: var(--color-info);
}

.trade-label.stop {
  background-color: rgba(234, 179, 8, 0.15);
  color: var(--color-warning);
}

.trade-label.market {
  background-color: rgba(107, 114, 128, 0.15);
  color: var(--color-text);
}

.trade-label.stop-limit {
  background-color: rgba(168, 85, 247, 0.15);
  color: var(--color-purple);
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
  font-size: 14px;
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

/* Responsive */
@media (max-width: 768px) {
  .open-positions-page {
    padding: 0;
  }

  .positions-container {
    padding: 16px;
    border-radius: 0;
    box-shadow: none;
  }

  .positions-tabs {
    overflow-x: auto;
    overflow-y: hidden;
    margin-bottom: 16px;
    gap: 0;
  }

  .positions-tab {
    white-space: nowrap;
    padding: 10px 14px;
    font-size: 14px;
    gap: 6px;
  }

  .positions-tab i {
    font-size: 14px;
  }

  .tab-counter {
    font-size: 14px;
    padding: 1px 6px;
  }

  .table-panel {
    margin-top: 12px;
  }

  .table-container {
    overflow-x: auto;
    overflow-y: hidden;
    border-radius: var(--radius-md);
    border-width: 1px;
  }

  .positions-table {
    min-width: 700px;
  }

  .positions-table th {
    padding: 10px 8px;
    font-size: 14px;
  }

  .positions-table td {
    padding: 10px 8px;
    font-size: 14px;
  }

  .symbol-icon {
    width: 36px;
    height: 22px;
    /* @font-floor-exempt: visual-only symbol mark */
    font-size: 9px;
  }

  .symbol-cell {
    gap: 8px;
  }

  .symbol-name {
    font-size: 14px;
  }

  .trade-label {
    padding: 2px 8px;
    font-size: 14px;
    min-width: 50px;
  }

  .pnl-cell {
    font-size: 14px;
  }

  .empty-state i {
    font-size: 36px;
  }

  .empty-state p {
    font-size: 14px;
  }

  .empty-state-cell {
    padding: 40px 16px;
  }
}
</style>
