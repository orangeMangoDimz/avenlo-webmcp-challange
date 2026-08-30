<template>
  <div class="container">
    <div class="page-header">
      <div class="page-title">
        <h1>
          <i class="fas fa-ticket-alt"></i> {{ t("page_clientTickets_title") }}
        </h1>
        <p>{{ t("page_clientTickets_sub") }}</p>
      </div>
      <div class="page-actions">
        <PageHeaderActions />
      </div>
    </div>

    <div class="tickets-content">
      <div class="tickets-header">
        <!-- 与交易平台后台 Daily Report 一致：Element 日期选择器 + ConfigProvider 切换中英文 -->
        <el-config-provider :locale="elementPlusLocale">
          <div class="filters filter-row">
            <el-date-picker
              v-model="filters.startDate"
              type="date"
              value-format="YYYY-MM-DD"
              :placeholder="t('clientTickets_startDate')"
              class="filter-date"
              clearable
            />
            <span class="filter-sep">–</span>
            <el-date-picker
              v-model="filters.endDate"
              type="date"
              value-format="YYYY-MM-DD"
              :placeholder="t('clientTickets_endDate')"
              class="filter-date"
              clearable
            />
            <button type="button" class="btn btn-primary" @click="applyFilters">
              <i class="fas fa-check"></i> {{ t("clientTickets_apply") }}
            </button>
          </div>
        </el-config-provider>
      </div>

      <div v-if="loading" class="loading-container">
        <i class="fas fa-spinner fa-spin"></i>
        <p>{{ t("clientTickets_loading") }}</p>
      </div>

      <div v-else-if="error" class="error-container">
        <i class="fas fa-exclamation-circle"></i>
        <p>{{ error }}</p>
        <button class="btn btn-primary" @click="loadTickets">
          <i class="fas fa-redo"></i> {{ t("clientTickets_retry") }}
        </button>
      </div>

      <div v-else class="tickets-list-container">
        <div v-if="tickets.length === 0" class="empty-state">
          <i class="fas fa-inbox"></i>
          <p>{{ t("clientTickets_empty") }}</p>
        </div>

        <div v-else class="tickets-list">
          <div v-for="ticket in tickets" :key="ticket.id" class="ticket-card">
            <div class="ticket-header">
              <div class="ticket-title-section">
                <h3>{{ ticket.title }}</h3>
                <span class="ticket-id">#{{ ticket.id }}</span>
              </div>
              <div class="ticket-meta">
                <span
                  class="ticket-status-badge"
                  :class="isResolved(ticket) ? 'resolved' : 'open'"
                >
                  <i
                    :class="
                      isResolved(ticket)
                        ? 'fas fa-check-circle'
                        : 'fas fa-circle-notch'
                    "
                  ></i>
                  {{
                    isResolved(ticket)
                      ? t("clientTickets_statusResolved", "Resolved")
                      : t("clientTickets_statusOpen", "Unresolved")
                  }}
                </span>
                <span class="ticket-date">
                  <i class="fas fa-calendar"></i>
                  {{ formatDateTime(ticket.createdAt) }}
                </span>
                <button
                  v-if="canResolve"
                  type="button"
                  class="btn-ticket-status"
                  :class="isResolved(ticket) ? 'reopen' : 'resolve'"
                  :disabled="statusUpdatingId === ticket.id"
                  @click="toggleResolved(ticket)"
                >
                  <i
                    v-if="statusUpdatingId === ticket.id"
                    class="fas fa-spinner fa-spin"
                  ></i>
                  <i
                    v-else
                    :class="
                      isResolved(ticket) ? 'fas fa-rotate-left' : 'fas fa-check'
                    "
                  ></i>
                  {{
                    isResolved(ticket)
                      ? t("clientTickets_markOpen", "Reopen")
                      : t("clientTickets_markResolved", "Mark Resolved")
                  }}
                </button>
              </div>
            </div>
            <div class="ticket-body">
              <div class="ticket-content">
                <p>{{ ticket.content }}</p>
              </div>
              <div class="ticket-client-info">
                <div class="client-info-item">
                  <strong>{{ t("clientTickets_labelClient") }}</strong>
                  <span>{{ ticket.firstName }} {{ ticket.lastName }}</span>
                </div>
                <div class="client-info-item">
                  <strong>{{ t("clientTickets_labelEmail") }}</strong>
                  <span>{{ ticket.email }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="pagination">
          <button
            class="btn btn-secondary"
            :disabled="currentPage === 1"
            @click="changePage(currentPage - 1)"
          >
            <i class="fas fa-chevron-left"></i>
            {{ t("clientTickets_previous") }}
          </button>
          <span class="page-info">
            {{ paginationLabel }}
          </span>
          <button
            class="btn btn-secondary"
            :disabled="currentPage === totalPages"
            @click="changePage(currentPage + 1)"
          >
            {{ t("clientTickets_next") }} <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import PageHeaderActions from "@/components/layout/PageHeaderActions.vue";

import { ref, onMounted, computed } from "vue";
import { ElConfigProvider, ElDatePicker } from "element-plus";
import zhCn from "element-plus/es/locale/lang/zh-cn";
import en from "element-plus/es/locale/lang/en";
import "element-plus/es/components/date-picker/style/css";
import "element-plus/es/components/config-provider/style/css";

import clientTicketApi from "@/services/clientTicketApi";
import { formatNumber } from "@/utils/helpers";
import { useAdminI18n } from "@/composables/useAdminI18n";
import { useAuthStore } from "@/stores/auth";

const { t, tParams, languageStore } = useAdminI18n();
const authStore = useAuthStore();

// 标记工单需要 resolve 权限（Administrator 默认拥有全部权限）
const canResolve = computed(() =>
  authStore.hasPermission("page_clienttickets_resolve"),
);

const elementPlusLocale = computed(() =>
  languageStore.currentLanguage === "zh" ? zhCn : en,
);

const paginationLabel = computed(() =>
  tParams(
    "clientTickets_pagination",
    "Page {current} of {totalPages} ({total} tickets)",
    {
      current: formatNumber(currentPage.value),
      totalPages: formatNumber(totalPages.value),
      total: formatNumber(total.value),
    },
  ),
);

const loading = ref(false);
const error = ref(null);
const tickets = ref([]);
const currentPage = ref(1);
const perPage = ref(10);
const total = ref(0);

/** 默认不按日期筛选；选日期后点「应用」才传给接口 */
const filters = ref({
  startDate: null,
  endDate: null,
});

const totalPages = computed(() => {
  return Math.ceil(total.value / perPage.value);
});

const loadTickets = async () => {
  loading.value = true;
  error.value = null;

  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value,
    };

    if (filters.value.startDate) {
      params.startDate = filters.value.startDate;
    }
    if (filters.value.endDate) {
      params.endDate = filters.value.endDate;
    }

    const response = await clientTicketApi.getTickets(params);
    if (response.success) {
      tickets.value = response.data.items || [];
      total.value = response.data.total || 0;
    } else {
      error.value = response.message || t("clientTickets_loadFailed");
    }
  } catch (err) {
    console.error("Failed to load tickets:", err);
    error.value = err.response?.data?.message || t("clientTickets_loadFailed");
  } finally {
    loading.value = false;
  }
};

const applyFilters = () => {
  currentPage.value = 1;
  loadTickets();
};

const changePage = (page) => {
  currentPage.value = page;
  loadTickets();
};

// status 为空/非 resolved 一律按未解决处理
const isResolved = (ticket) => ticket.status === "resolved";

const statusUpdatingId = ref(null);

const toggleResolved = async (ticket) => {
  if (!canResolve.value || statusUpdatingId.value) return;
  const nextStatus = isResolved(ticket) ? "open" : "resolved";
  statusUpdatingId.value = ticket.id;
  try {
    const response = await clientTicketApi.updateTicketStatus(
      ticket.id,
      nextStatus,
    );
    if (response.success) {
      ticket.status = nextStatus;
    } else {
      error.value =
        response.message ||
        t("clientTickets_updateFailed", "Failed to update ticket status");
    }
  } catch (err) {
    console.error("Failed to update ticket status:", err);
    error.value =
      err.response?.data?.message ||
      t("clientTickets_updateFailed", "Failed to update ticket status");
  } finally {
    statusUpdatingId.value = null;
  }
};

const formatDateTime = (datetime) => {
  if (!datetime) return "";
  const date = new Date(datetime);
  const loc = languageStore.currentLanguage === "zh" ? "zh-CN" : "en-US";
  return date.toLocaleString(loc, {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

onMounted(() => {
  loadTickets();
});
</script>

<style scoped>
.container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 40px 20px;
}

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
  display: flex;
  align-items: center;
  gap: 10px;
}

.page-title h1 i {
  color: var(--color-brand);
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

.tickets-content {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 30px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.tickets-header {
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--color-border);
}

.filters {
  display: flex;
  gap: 15px;
  align-items: center;
  flex-wrap: wrap;
}

.filter-row {
  gap: 8px;
}

.filter-sep {
  color: var(--color-muted);
  font-size: 14px;
  user-select: none;
}

.filter-date {
  width: 180px;
  max-width: 100%;
}

.filter-date :deep(.el-input__wrapper) {
  border-radius: var(--radius-md);
}

.loading-container,
.error-container,
.empty-state {
  padding: 60px 20px;
  text-align: center;
  color: var(--color-muted);
}

.loading-container i,
.error-container i {
  font-size: 48px;
  margin-bottom: 15px;
  display: block;
  color: var(--color-brand);
}

.error-container i {
  color: var(--color-danger);
}

.empty-state i {
  font-size: 64px;
  margin-bottom: 20px;
  display: block;
  color: var(--color-border-strong);
}

.tickets-list-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.tickets-list {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.ticket-card {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 20px;
  transition: all 0.2s ease;
}

.ticket-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  border-color: var(--color-border-strong);
}

.ticket-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 15px;
  padding-bottom: 15px;
  border-bottom: 1px solid #f1f3f5;
}

.ticket-title-section {
  flex: 1;
}

.ticket-title-section h3 {
  font-size: 18px;
  color: var(--color-ink);
  margin-bottom: 5px;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.ticket-id {
  font-size: 14px;
  color: var(--color-muted);
  background: var(--color-surface-soft);
  padding: 2px 8px;
  border-radius: 4px;
}

.ticket-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 5px;
}

.ticket-date {
  font-size: 14px;
  color: var(--color-muted);
  display: flex;
  align-items: center;
  gap: 5px;
}

.ticket-status-badge {
  font-size: 14px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: 999px;
}

.ticket-status-badge.resolved {
  color: var(--color-success);
  background: var(--color-success-soft);
}

.ticket-status-badge.open {
  color: var(--color-warning);
  background: var(--color-warning-soft);
}

.btn-ticket-status {
  font-size: 14px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border: 1px solid transparent;
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.btn-ticket-status:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-ticket-status.resolve {
  color: #fff;
  background: var(--color-success-solid);
}

.btn-ticket-status.reopen {
  color: var(--color-warning);
  background: var(--color-surface);
  border-color: #fcd34d;
}

.ticket-body {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.ticket-content {
  color: var(--color-text);
  line-height: 1.6;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.ticket-client-info {
  display: flex;
  gap: 20px;
  padding-top: 15px;
  border-top: 1px solid #f1f3f5;
  font-size: 14px;
}

.client-info-item {
  display: flex;
  gap: 8px;
}

.client-info-item strong {
  color: var(--color-ink);
}

.client-info-item span {
  color: var(--color-muted);
}

.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 30px;
  padding-top: 20px;
  border-top: 2px solid var(--color-border);
}

.page-info {
  color: var(--color-muted);
  font-size: 14px;
}

.btn {
  padding: 10px 20px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-primary {
  background: var(--color-brand-solid);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--color-brand-rgb), 0.4);
}

.btn-secondary {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.btn-secondary:hover:not(:disabled) {
  background: var(--color-border);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
