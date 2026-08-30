<template>
  <div class="notification-wrapper">
    <button
      type="button"
      class="notification-btn"
      :aria-label="t('notifTitle', 'Notifications')"
      :aria-expanded="dropdownVisible"
      @click="handleBellClick"
      ref="buttonRef"
    >
      <i class="fas fa-bell"></i>
      <span v-if="unreadCount > 0" class="notification-badge">{{
        unreadCount
      }}</span>
    </button>

    <transition name="notification-fade">
      <div
        v-if="dropdownVisible"
        class="notification-dropdown"
        ref="dropdownRef"
      >
        <div class="notification-header">
          <h3>{{ t("notifTitle", "Notifications") }}</h3>
          <button
            v-if="notifications.length && unreadCount > 0"
            type="button"
            class="btn-mark-all"
            @click="markAllAsRead"
          >
            {{ t("notifMarkAllRead", "Mark all as read") }}
          </button>
        </div>

        <div class="notification-list">
          <div v-if="loadingRecent" class="notification-loading">
            <i class="fas fa-spinner fa-spin"></i>
            {{ t("notifLoading", "Loading...") }}
          </div>
          <template v-else>
            <button
              v-for="item in notifications"
              :key="item.id"
              type="button"
              :class="['notification-item', { unread: !item.isRead }]"
              @click="openNotification(item)"
            >
              <div class="notification-icon" :class="item.type">
                <i :class="getNotificationIcon(item.type)"></i>
              </div>
              <div class="notification-content">
                <div class="notification-title-row">
                  <div class="notification-title">{{ item.subject }}</div>
                  <span v-if="!item.isRead" class="panel-pill">{{
                    t("notifNew", "New")
                  }}</span>
                </div>
                <div class="notification-message">{{ item.message }}</div>
                <div class="notification-time">
                  {{ formatTime(item.createdAt) }}
                </div>
              </div>
            </button>
            <div v-if="!notifications.length" class="notification-empty">
              <i class="fas fa-bell-slash"></i>
              <p>{{ t("notifNoNotificationsYet", "No notifications yet") }}</p>
            </div>
          </template>
        </div>

        <div v-if="notifications.length" class="notification-footer">
          <button type="button" class="btn-view-all" @click="openPanel()">
            {{ t("notifViewAll", "View All Notifications") }}
          </button>
        </div>
      </div>
    </transition>
  </div>

  <Teleport to="body">
    <div
      v-if="panelVisible"
      class="notification-panel-overlay"
      @click.self="closePanel"
    >
      <div class="notification-panel">
        <div class="panel-header">
          <div>
            <h2>{{ t("notifTitle", "Notifications") }}</h2>
            <span class="panel-subtitle">
              <span v-if="unreadCount > 0">{{ unreadCount }} unread</span>
              <span v-else>{{ t("notifAllCaughtUp", "All caught up") }}</span>
            </span>
          </div>
          <div class="panel-actions">
            <button
              v-if="panelNotifications.length && unreadCount > 0"
              type="button"
              class="btn-text"
              @click="markAllAsRead"
            >
              {{ t("notifMarkAllRead", "Mark all as read") }}
            </button>
            <button type="button" class="btn-text" @click="closePanel">
              {{ t("notifClose", "Close") }}
            </button>
          </div>
        </div>

        <div class="panel-list" ref="panelListRef" @scroll="handlePanelScroll">
          <button
            v-for="item in panelNotifications"
            :key="item.id"
            type="button"
            :class="[
              'panel-item',
              {
                unread: !item.isRead,
                active:
                  selectedNotification && selectedNotification.id === item.id,
              },
            ]"
            @click="selectNotification(item)"
          >
            <div class="panel-item-icon-wrapper">
              <div class="panel-item-icon" :class="item.type">
                <i :class="getNotificationIcon(item.type)"></i>
              </div>
            </div>
            <div class="panel-item-main">
              <div class="panel-item-title">{{ item.subject }}</div>
              <div class="panel-item-message">{{ item.message }}</div>
            </div>
            <div class="panel-item-meta">
              <span class="panel-item-time">{{
                formatTime(item.createdAt)
              }}</span>
              <span v-if="!item.isRead" class="panel-pill">New</span>
            </div>
          </button>

          <div v-if="panelLoading" class="panel-loading">
            <i class="fas fa-spinner fa-spin"></i>
            {{ t("notifLoadingMore", "Loading more...") }}
          </div>
          <div v-else-if="panelError" class="panel-error">
            <i class="fas fa-exclamation-circle"></i>
            <span>{{ panelError }}</span>
          </div>
          <div v-else-if="!panelNotifications.length" class="panel-empty">
            <i class="fas fa-inbox"></i>
            <p>
              {{ t("notifNoNotificationsFound", "No notifications found") }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </Teleport>

  <Teleport to="body">
    <!-- 普通消息详情弹窗 -->
    <div
      v-if="
        selectedNotification &&
        selectedNotification.type !== 'withdrawal_document_request'
      "
      class="notification-detail-overlay"
      @click.self="closeDetailModal"
    >
      <div class="notification-detail-modal">
        <div class="detail-header">
          <div>
            <h3>{{ selectedNotification.subject }}</h3>
            <span class="detail-time">{{
              formatTime(selectedNotification.createdAt)
            }}</span>
          </div>
          <button type="button" class="btn-text" @click="closeDetailModal">
            {{ t("notifClose", "Close") }}
          </button>
        </div>
        <div class="detail-body">
          <p>{{ selectedNotification.message }}</p>
        </div>
      </div>
    </div>

    <!-- Withdraw消息确认弹窗 -->
    <div
      v-if="withdrawalModalVisible"
      class="notification-detail-overlay"
      @click.self="closeWithdrawalModal"
    >
      <div class="notification-detail-modal">
        <div class="detail-header">
          <div>
            <h3>{{ withdrawalModalNotification?.subject }}</h3>
            <span class="detail-time">{{
              withdrawalModalNotification
                ? formatTime(withdrawalModalNotification.createdAt)
                : ""
            }}</span>
          </div>
          <button type="button" class="btn-text" @click="closeWithdrawalModal">
            {{ t("notifClose", "Close") }}
          </button>
        </div>
        <div class="detail-body">
          <div v-if="withdrawalModalLoading" class="modal-loading">
            <i class="fas fa-spinner fa-spin"></i>
            <p>{{ t("notifCheckingStatus", "Checking status...") }}</p>
          </div>
          <div v-else-if="withdrawalModalSubmitted">
            <div class="withdrawal-success-message">
              <div class="success-icon-large">
                <i class="fas fa-check-circle"></i>
              </div>
              <h4>
                {{
                  t(
                    "notifDocsSubmittedSuccess",
                    "Documents Submitted Successfully",
                  )
                }}
              </h4>
              <p>{{ withdrawalModalNotification?.message }}</p>
              <p class="success-note">
                {{
                  t(
                    "notifSuccessNote",
                    "Your additional documents have been successfully submitted. We will review them and get back to you as soon as possible.",
                  )
                }}
              </p>
            </div>
          </div>
          <div v-else>
            <p>{{ withdrawalModalNotification?.message }}</p>
            <div class="withdrawal-confirm-actions">
              <p class="confirm-prompt">
                {{
                  t(
                    "notifSubmitNowPrompt",
                    "Would you like to submit the required documents now?",
                  )
                }}
              </p>
              <div class="modal-actions">
                <button
                  type="button"
                  class="btn btn-secondary"
                  @click="closeWithdrawalModal"
                >
                  {{ t("wdCancel", "Cancel") }}
                </button>
                <button
                  type="button"
                  class="btn btn-primary"
                  @click="confirmGoToSupplement"
                >
                  {{ t("notifConfirm", "Confirm") }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from "vue";
import { useRouter } from "vue-router";
import { useLanguageStore } from "@/stores/language";
import { clientNotificationService } from "@/services/clientNotificationService";
import withdrawalService from "@/services/withdrawalService";

const router = useRouter();
const languageStore = useLanguageStore();
const t = (key, fallback) => languageStore.t(key, fallback);

const dropdownVisible = ref(false);
const loadingRecent = ref(false);
const recentInitialized = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);

const panelVisible = ref(false);
const panelInitialized = ref(false);
const panelNotifications = ref([]);
const panelPage = ref(1);
const panelHasMore = ref(true);
const panelLoading = ref(false);
const panelError = ref("");
const selectedNotification = ref(null);

const buttonRef = ref(null);
const dropdownRef = ref(null);
const panelListRef = ref(null);

const PER_PAGE_DROPDOWN = 3;
const PANEL_PAGE_SIZE = 10;
const SCROLL_THRESHOLD = 120;

// Withdrawal弹窗相关状态
const withdrawalModalVisible = ref(false);
const withdrawalModalNotification = ref(null);
const withdrawalModalLoading = ref(false);
const withdrawalModalSubmitted = ref(false);
const pendingWithdrawalId = ref(null);

const handleBellClick = async () => {
  dropdownVisible.value = !dropdownVisible.value;
  if (dropdownVisible.value) {
    await fetchRecentNotifications({ force: !recentInitialized.value });
  }
};

const fetchRecentNotifications = async ({ force = false } = {}) => {
  if (loadingRecent.value) return;
  if (recentInitialized.value && !force) {
    return;
  }
  loadingRecent.value = true;
  try {
    const response = await clientNotificationService.fetchNotifications({
      perPage: PER_PAGE_DROPDOWN,
    });
    const payload = response?.data ?? response ?? {};
    const items = Array.isArray(payload.items)
      ? payload.items.map(normalizeNotification)
      : [];
    notifications.value = items.slice(0, PER_PAGE_DROPDOWN);
    mergeNotifications(panelNotifications, items);
    syncRecentFromPanel();
    if (typeof payload.unreadCount === "number") {
      unreadCount.value = payload.unreadCount;
    }
  } catch (error) {
    console.error("Failed to load notifications", error);
  } finally {
    loadingRecent.value = false;
    recentInitialized.value = true;
  }
};

const fetchPanelNotifications = async ({ reset = false } = {}) => {
  if (panelLoading.value) return;
  if (reset) {
    panelPage.value = 1;
    panelHasMore.value = true;
    panelNotifications.value = [];
  }
  if (!panelHasMore.value) return;

  panelLoading.value = true;
  panelError.value = "";
  try {
    const response = await clientNotificationService.fetchNotifications({
      page: panelPage.value,
      perPage: PANEL_PAGE_SIZE,
    });
    const payload = response?.data ?? response ?? {};
    const items = Array.isArray(payload.items)
      ? payload.items.map(normalizeNotification)
      : [];
    mergeNotifications(panelNotifications, items);
    syncRecentFromPanel();

    if (typeof payload.unreadCount === "number") {
      unreadCount.value = payload.unreadCount;
    }

    panelHasMore.value = payload.pagination?.hasMore ?? false;
    if (panelHasMore.value) {
      panelPage.value += 1;
    }
    panelInitialized.value = true;
  } catch (error) {
    console.error("Failed to load notifications", error);
    panelError.value =
      error?.response?.data?.message ||
      error.message ||
      "Failed to load notifications";
  } finally {
    panelLoading.value = false;
  }
};

const openNotification = async (notification) => {
  await markNotificationAsRead(notification);

  // 处理withdrawal_document_request类型的通知
  if (
    notification.type === "withdrawal_document_request" &&
    notification.metadata
  ) {
    try {
      const metadata =
        typeof notification.metadata === "string"
          ? JSON.parse(notification.metadata)
          : notification.metadata;

      if (metadata.withdrawalId) {
        // 检查是否已提交
        await checkWithdrawalStatusAndShowModal(
          notification,
          metadata.withdrawalId,
        );
        return;
      }
    } catch (err) {
      console.error("Failed to parse notification metadata:", err);
    }
  }

  // 普通消息：只显示详情弹窗，不打开右侧面板
  selectedNotification.value = { ...notification, isRead: true };
};

const openPanel = async (focusedNotification = null) => {
  dropdownVisible.value = false;
  panelVisible.value = true;

  if (!panelInitialized.value) {
    await fetchPanelNotifications({ reset: true });
  }

  await nextTick(() => {
    if (focusedNotification) {
      const found = panelNotifications.value.find(
        (item) => item.id === focusedNotification.id,
      );
      if (found) {
        selectedNotification.value = { ...found, isRead: true };
      } else {
        selectedNotification.value = { ...focusedNotification, isRead: true };
      }
    }
  });
};

const closePanel = () => {
  panelVisible.value = false;
  closeDetailModal();
};

const selectNotification = async (notification) => {
  await markNotificationAsRead(notification);

  // 处理withdrawal_document_request类型的通知
  if (
    notification.type === "withdrawal_document_request" &&
    notification.metadata
  ) {
    try {
      const metadata =
        typeof notification.metadata === "string"
          ? JSON.parse(notification.metadata)
          : notification.metadata;

      if (metadata.withdrawalId) {
        // 检查是否已提交
        await checkWithdrawalStatusAndShowModal(
          notification,
          metadata.withdrawalId,
        );
        return;
      }
    } catch (err) {
      console.error("Failed to parse notification metadata:", err);
    }
  }

  // 普通消息：显示详情弹窗
  const found = panelNotifications.value.find(
    (item) => item.id === notification.id,
  );
  selectedNotification.value = found
    ? { ...found, isRead: true }
    : { ...notification, isRead: true };
};

const closeDetailModal = () => {
  selectedNotification.value = null;
};

const closeWithdrawalModal = () => {
  withdrawalModalVisible.value = false;
  withdrawalModalNotification.value = null;
  withdrawalModalLoading.value = false;
  withdrawalModalSubmitted.value = false;
  pendingWithdrawalId.value = null;
};

const checkWithdrawalStatusAndShowModal = async (
  notification,
  withdrawalId,
) => {
  // 验证withdrawalId
  if (!withdrawalId) {
    console.error("Withdrawal ID is missing");
    return;
  }

  withdrawalModalVisible.value = true;
  withdrawalModalNotification.value = notification;
  withdrawalModalLoading.value = true;
  withdrawalModalSubmitted.value = false;
  pendingWithdrawalId.value = withdrawalId;

  try {
    // 检查状态并加载数据（后端接口返回完整数据）
    const response = await withdrawalService.getDocumentRequest(withdrawalId);
    const documentRequest = response?.data || response;

    // 检查是否已提交（submittedAt不为空或requestStatus为'submitted'）
    if (
      documentRequest.submittedAt ||
      documentRequest.requestStatus === "submitted"
    ) {
      withdrawalModalSubmitted.value = true;
    } else {
      withdrawalModalSubmitted.value = false;
    }

    // 缓存完整数据，供页面加载时使用（避免重复加载）
    // 缓存有效期5分钟
    if (documentRequest) {
      sessionStorage.setItem(
        `withdrawal_document_request_${withdrawalId}`,
        JSON.stringify({
          data: documentRequest,
          timestamp: Date.now(),
        }),
      );
    }
  } catch (error) {
    console.error("Failed to check withdrawal status:", error);
    // 如果获取失败，默认显示未提交状态
    withdrawalModalSubmitted.value = false;
  } finally {
    withdrawalModalLoading.value = false;
  }
};

const confirmGoToSupplement = () => {
  // 先保存 withdrawalId，因为 closeWithdrawalModal 会清空它
  const withdrawalId = pendingWithdrawalId.value;
  if (withdrawalId) {
    closePanel(); // 关闭右侧消息列表
    closeWithdrawalModal(); // 关闭弹窗（会清空 pendingWithdrawalId）
    router.push(`/client/withdrawal-supplement/${withdrawalId}`);
  }
};

const handlePanelScroll = () => {
  const el = panelListRef.value;
  if (!el || panelLoading.value || !panelHasMore.value) return;

  if (el.scrollTop + el.clientHeight >= el.scrollHeight - SCROLL_THRESHOLD) {
    fetchPanelNotifications();
  }
};

const markNotificationAsRead = async (notification) => {
  if (!notification) return;
  if (notification.isRead) {
    applyReadState(notification.id);
    return;
  }

  try {
    const response = await clientNotificationService.markAsRead({
      id: notification.id,
    });
    applyReadState(notification.id);
    if (response?.data?.unreadCount !== undefined) {
      unreadCount.value = response.data.unreadCount;
    } else {
      unreadCount.value = Math.max(0, unreadCount.value - 1);
    }
  } catch (error) {
    console.error("Failed to mark notification as read", error);
  }
};

const markAllAsRead = async () => {
  try {
    await clientNotificationService.markAllAsRead();
    panelNotifications.value = panelNotifications.value.map((item) => ({
      ...item,
      isRead: true,
      readAt: item.readAt || new Date().toISOString(),
    }));
    notifications.value = notifications.value.map((item) => ({
      ...item,
      isRead: true,
      readAt: item.readAt || new Date().toISOString(),
    }));
    if (selectedNotification.value) {
      selectedNotification.value = {
        ...selectedNotification.value,
        isRead: true,
        readAt: selectedNotification.value.readAt || new Date().toISOString(),
      };
    }
    unreadCount.value = 0;
  } catch (error) {
    console.error("Failed to mark all notifications as read", error);
  }
};

const applyReadState = (id) => {
  const nowIso = new Date().toISOString();
  const updateList = (list) => {
    const index = list.findIndex((item) => item.id === id);
    if (index !== -1 && !list[index].isRead) {
      list[index] = {
        ...list[index],
        isRead: true,
        readAt: list[index].readAt || nowIso,
      };
    }
  };

  updateList(panelNotifications.value);
  updateList(notifications.value);

  if (selectedNotification.value && selectedNotification.value.id === id) {
    selectedNotification.value = {
      ...selectedNotification.value,
      isRead: true,
      readAt: selectedNotification.value.readAt || nowIso,
    };
  }
};

const mergeNotifications = (targetRef, newItems) => {
  if (!newItems.length) return;
  const map = new Map();
  targetRef.value.forEach((item) => map.set(item.id, item));
  newItems.forEach((item) => {
    const existing = map.get(item.id) || {};
    map.set(item.id, { ...existing, ...item });
  });
  const merged = Array.from(map.values());
  merged.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
  targetRef.value = merged;
};

const syncRecentFromPanel = () => {
  if (!panelNotifications.value.length) {
    return;
  }
  notifications.value = panelNotifications.value.slice(0, PER_PAGE_DROPDOWN);
};

const normalizeNotification = (item) => {
  return {
    id: Number(item.id),
    notificationId: Number(item.notificationId ?? item.id ?? 0),
    subject: item.subject || "Notification",
    message: item.message || "",
    isRead: Boolean(item.isRead),
    readAt: item.readAt,
    createdAt: item.createdAt
      ? new Date(item.createdAt).toISOString()
      : new Date().toISOString(),
    type: item.type || "common", // 默认为'common'
    metadata: item.metadata || null,
  };
};

const getNotificationIcon = (type) => {
  const icons = {
    success: "fas fa-check-circle",
    info: "fas fa-info-circle",
    warning: "fas fa-exclamation-triangle",
    error: "fas fa-times-circle",
    withdrawal_document_request: "fas fa-file-upload",
  };
  return icons[type] || "fas fa-bell";
};

const formatTime = (value) => {
  if (!value) return "";
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return "";

  const now = new Date();
  const diffSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

  if (diffSeconds < 60) {
    return "Just now";
  }
  if (diffSeconds < 3600) {
    const minutes = Math.floor(diffSeconds / 60);
    return `${minutes} minute${minutes > 1 ? "s" : ""} ago`;
  }
  if (diffSeconds < 86400) {
    const hours = Math.floor(diffSeconds / 3600);
    return `${hours} hour${hours > 1 ? "s" : ""} ago`;
  }
  if (diffSeconds < 604800) {
    const days = Math.floor(diffSeconds / 86400);
    return `${days} day${days > 1 ? "s" : ""} ago`;
  }
  return date.toLocaleDateString(undefined, {
    month: "short",
    day: "numeric",
    year: date.getFullYear() !== now.getFullYear() ? "numeric" : undefined,
  });
};

const handleDocumentClick = (event) => {
  if (!dropdownVisible.value) return;
  const btnEl = buttonRef.value;
  const dropdownEl = dropdownRef.value;
  if (
    btnEl &&
    dropdownEl &&
    !btnEl.contains(event.target) &&
    !dropdownEl.contains(event.target)
  ) {
    dropdownVisible.value = false;
  }
};

const handleKeydown = (event) => {
  if (event.key === "Escape") {
    if (panelVisible.value) {
      closePanel();
    } else if (dropdownVisible.value) {
      dropdownVisible.value = false;
    }
  }
};

onMounted(async () => {
  await fetchRecentNotifications({ force: true });
  document.addEventListener("click", handleDocumentClick);
  document.addEventListener("keydown", handleKeydown);
});

onUnmounted(() => {
  document.removeEventListener("click", handleDocumentClick);
  document.removeEventListener("keydown", handleKeydown);
});
</script>

<style scoped>
.notification-wrapper {
  position: relative;
}

.notification-btn {
  position: relative;
  background: white;
  border: 2px solid var(--color-border);
  width: 44px;
  height: 44px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition:
    background-color 0.3s ease,
    border-color 0.3s ease,
    color 0.3s ease;
  font-size: 18px;
  color: var(--color-text);
  outline: none;
}

.notification-btn:hover {
  border-color: var(--color-brand);
  background: var(--color-brand-soft);
  color: var(--color-brand);
}

.notification-badge {
  position: absolute;
  top: -5px;
  right: -5px;
  background: var(--color-danger);
  color: white;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 600;
  border: 2px solid var(--color-canvas);
}

.notification-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  background: white;
  border-radius: var(--radius-lg);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  width: 360px;
  max-height: 480px;
  display: flex;
  flex-direction: column;
  z-index: 1000;
}

.notification-header {
  padding: 20px;
  border-bottom: 1px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.notification-header h3 {
  font-size: 18px;
  color: var(--color-ink);
  margin: 0;
}

.btn-mark-all {
  background: none;
  border: none;
  color: var(--color-brand);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: color 0.2s ease;
}

.btn-mark-all:hover {
  color: var(--color-brand-strong);
}

.notification-list {
  flex: 1;
  overflow-y: auto;
}

.notification-list::-webkit-scrollbar {
  width: 6px;
}

.notification-list::-webkit-scrollbar-track {
  background: var(--color-surface-soft);
}

.notification-list::-webkit-scrollbar-thumb {
  background: var(--color-border-strong);
  border-radius: 3px;
}

.notification-item {
  width: 100%;
  border: 0;
  background: transparent;
  text-align: left;
  padding: 15px 20px;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  border-bottom: 1px solid var(--color-border);
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.notification-item:hover {
  background: var(--color-surface-soft);
}

.notification-item.unread {
  background: var(--color-brand-soft);
}

.notification-icon {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 16px;
}

.notification-icon.success {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.notification-icon.info {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.notification-icon.warning {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.notification-icon.error {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.notification-content {
  flex: 1;
  min-width: 0;
}

.notification-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.notification-title {
  flex: 1;
  min-width: 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.notification-message {
  font-size: 13px;
  color: var(--color-muted);
  margin-bottom: 4px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.notification-time {
  font-size: 12px;
  color: var(--color-faint);
}

.notification-loading,
.notification-empty {
  padding: 40px 20px;
  text-align: center;
  color: #94a3b8;
  font-size: 14px;
}

.notification-empty i {
  font-size: 36px;
  margin-bottom: 12px;
  display: block;
}

.notification-footer {
  padding: 12px 20px;
  border-top: 1px solid var(--color-border);
}

.btn-view-all {
  width: 100%;
  padding: 10px;
  background: var(--color-brand-soft);
  border: none;
  border-radius: var(--radius-md);
  color: var(--color-brand);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition:
    background-color 0.2s ease,
    color 0.2s ease;
}

.btn-view-all:hover {
  background: var(--color-brand);
  color: white;
}

.notification-fade-enter-active,
.notification-fade-leave-active {
  transition:
    opacity 0.2s ease,
    transform 0.2s ease;
}

.notification-fade-enter-from,
.notification-fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

.notification-panel-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(3px);
  display: flex;
  justify-content: flex-end;
  align-items: stretch;
  padding: 0;
  z-index: 2500;
}

.notification-panel {
  background: #ffffff;
  border-radius: 16px 0 0 16px;
  width: min(420px, 100%);
  max-width: 100%;
  display: flex;
  flex-direction: column;
  box-shadow: -12px 0 30px rgba(15, 23, 42, 0.22);
  overflow: hidden;
}

.panel-header {
  padding: 24px 28px;
  border-bottom: 1px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: linear-gradient(
    135deg,
    var(--color-surface-soft) 0%,
    var(--color-surface-muted) 50%,
    var(--color-surface-soft) 100%
  );
}

.panel-header h2 {
  margin: 0;
  font-size: 20px;
  color: var(--color-ink-strong);
}

.panel-subtitle {
  display: block;
  margin-top: 6px;
  font-size: 13px;
  color: var(--color-muted);
}

.panel-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.btn-text {
  background: none;
  border: none;
  color: var(--color-brand);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  padding: 6px 8px;
  border-radius: var(--radius-sm);
  transition:
    background 0.2s ease,
    color 0.2s ease;
}

.btn-text:hover {
  background: rgba(var(--color-brand-rgb), 0.1);
  color: var(--color-brand-strong);
}

.panel-list {
  flex: 1;
  overflow-y: auto;
  padding: 0 8px;
}

.panel-list::-webkit-scrollbar {
  width: 8px;
}

.panel-list::-webkit-scrollbar-track {
  background: #f1f5f9;
}

.panel-list::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

.panel-item {
  width: 100%;
  border: 0;
  background: transparent;
  text-align: left;
  padding: 18px 20px;
  margin: 0;
  border-bottom: 1px solid var(--color-border);
  display: flex;
  align-items: flex-start;
  gap: 12px;
  cursor: pointer;
  transition:
    background 0.2s ease,
    transform 0.2s ease;
}

.panel-item-icon-wrapper {
  flex-shrink: 0;
}

.panel-item-icon {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}

.panel-item-icon.common {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.panel-item-icon.withdrawal_document_request {
  background: #fed7aa;
  color: var(--color-warning);
}

.panel-item-icon.success {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.panel-item-icon.info {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.panel-item-icon.warning {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.panel-item-icon.error {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.panel-item:last-of-type {
  border-bottom: none;
}

.panel-item:hover {
  background: var(--color-surface-soft);
}

.panel-item.unread {
  background: #f1f5ff;
}

.panel-item.active {
  border-radius: var(--radius-lg);
  background: linear-gradient(
    135deg,
    rgba(var(--color-brand-rgb), 0.12),
    rgba(var(--color-brand-rgb), 0.12)
  );
  border: 1px solid rgba(var(--color-brand-rgb), 0.25);
}

.panel-item-main {
  flex: 1;
  min-width: 0;
}

.panel-item-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 6px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.panel-item-message {
  font-size: 13px;
  color: var(--color-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.panel-item-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
  flex-shrink: 0;
}

.panel-item-time {
  font-size: 12px;
  color: var(--color-faint);
}

.panel-pill {
  background: var(--color-brand);
  color: white;
  font-size: 11px;
  padding: 3px 8px;
  border-radius: 999px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.panel-loading,
.panel-empty,
.panel-error {
  padding: 24px;
  text-align: center;
  font-size: 14px;
  color: #64748b;
}

.panel-empty i,
.panel-error i {
  display: block;
  font-size: 36px;
  margin-bottom: 12px;
}

.notification-detail-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2600;
  padding: 24px;
}

.notification-detail-modal {
  width: min(520px, 100%);
  background: #ffffff;
  border-radius: var(--radius-xl);
  padding: 28px 32px;
  box-shadow: 0 35px 70px rgba(15, 23, 42, 0.3);
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.detail-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.detail-header h3 {
  margin: 0;
  font-size: 18px;
  color: var(--color-ink-strong);
}

.detail-time {
  display: block;
  margin-top: 6px;
  font-size: 12px;
  color: #94a3b8;
}

.detail-body {
  font-size: 14px;
  color: #475569;
  line-height: 1.6;
  white-space: pre-line;
}

.modal-loading {
  text-align: center;
  padding: 20px;
  color: #64748b;
}

.modal-loading i {
  font-size: 24px;
  margin-bottom: 12px;
  display: block;
}

.withdrawal-success-message {
  text-align: center;
  padding: 20px 0;
}

.success-icon-large {
  font-size: 48px;
  color: var(--color-success);
  margin-bottom: 16px;
}

.withdrawal-success-message h4 {
  font-size: 18px;
  color: var(--color-ink-strong);
  margin: 0 0 12px 0;
}

.success-note {
  margin-top: 16px;
  padding: 12px;
  background: var(--color-success-soft);
  border-radius: var(--radius-md);
  color: var(--color-success);
  font-size: 13px;
}

.withdrawal-confirm-actions {
  padding: 20px 0;
}

.confirm-prompt {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-ink-strong);
  margin-bottom: 20px;
}

.modal-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
}

.btn {
  padding: 10px 20px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition:
    background-color 0.2s ease,
    color 0.2s ease;
  border: none;
}

.btn-primary {
  background: var(--color-brand);
  color: white;
}

.btn-primary:hover {
  background: var(--color-brand-strong);
}

.btn-secondary {
  background: var(--color-border);
  color: var(--color-text);
}

.btn-secondary:hover {
  background: var(--color-border-strong);
}

@media (max-width: 768px) {
  .notification-panel-overlay {
    padding: 0;
    align-items: flex-end;
  }

  .notification-panel {
    border-radius: 16px 16px 0 0;
    width: 100%;
    max-height: 60vh;
  }

  .notification-detail-modal {
    width: 100%;
    max-height: 70vh;
  }
}
</style>
