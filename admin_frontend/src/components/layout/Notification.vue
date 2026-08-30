<template>
  <div class="notification-wrapper">
    <button class="notification-btn" @click="toggleNotifications">
      <i class="fas fa-bell"></i>
      <span v-if="unreadCount > 0" class="notification-badge">{{
        unreadCount
      }}</span>
    </button>

    <Teleport to="body">
      <div v-if="isOpen" class="notification-panel" @click.stop>
        <div class="notification-header">
          <h3><i class="fas fa-bell"></i> {{ t("common_notifications") }}</h3>
          <button class="notification-close" @click="closeNotifications">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="notification-body">
          <div v-if="loading" class="notification-loading">
            <i class="fas fa-spinner fa-spin"></i>
            <p>{{ t("notif_loading") }}</p>
          </div>
          <div
            v-else-if="notifications.length === 0"
            class="notification-empty"
          >
            <i class="fas fa-bell-slash"></i>
            <p>{{ t("notif_empty") }}</p>
          </div>
          <div
            v-else
            v-for="notification in notifications"
            :key="notification.id"
            class="notification-item"
            :class="{ unread: !notification.read }"
            @click="handleNotificationClick(notification)"
          >
            <div class="notification-icon" :class="notification.type">
              <i
                :class="
                  getIcon(notification.notificationType || notification.type)
                "
              ></i>
            </div>
            <div class="notification-content">
              <div class="notification-title">{{ notification.title }}</div>
              <div class="notification-message">{{ notification.message }}</div>
              <div class="notification-time">
                {{ formatTime(notification.createdAt) }}
              </div>
            </div>
          </div>
        </div>
        <div class="notification-footer">
          <button
            class="btn-text"
            @click="markAllAsRead"
            :disabled="unreadCount === 0"
          >
            {{ t("notif_markAllRead") }}
          </button>
          <button class="btn-text" @click="openPanel">
            {{ t("common_viewAllNotifications") }}
          </button>
        </div>
      </div>
    </Teleport>

    <!-- View All Notifications Panel -->
    <Teleport to="body">
      <div
        v-if="panelVisible"
        class="notification-panel-overlay"
        @click.self="closePanel"
      >
        <div class="notification-panel-full">
          <div class="panel-header">
            <div>
              <h2>{{ t("common_notifications") }}</h2>
              <span class="panel-subtitle">
                <span v-if="unreadCount > 0"
                  >{{ unreadCount }} {{ t("notif_unread") }}</span
                >
                <span v-else>{{ t("notif_allCaughtUp") }}</span>
              </span>
            </div>
            <div class="panel-actions">
              <button
                v-if="panelNotifications.length && unreadCount > 0"
                class="btn-text"
                @click="markAllAsRead"
              >
                {{ t("notif_markAllRead") }}
              </button>
              <button class="btn-text" @click="closePanel">
                {{ t("common_close") }}
              </button>
            </div>
          </div>

          <div
            class="panel-list"
            ref="panelListRef"
            @scroll="handlePanelScroll"
          >
            <div
              v-for="item in panelNotifications"
              :key="item.id"
              :class="[
                'panel-item',
                {
                  unread: !item.read,
                  active:
                    selectedNotification && selectedNotification.id === item.id,
                },
              ]"
              @click="selectNotification(item)"
            >
              <div class="panel-item-icon-wrapper">
                <div class="panel-item-icon" :class="item.type">
                  <i :class="getIcon(item.notificationType || item.type)"></i>
                </div>
              </div>
              <div class="panel-item-main">
                <div class="panel-item-title">{{ item.title }}</div>
                <div class="panel-item-message">{{ item.message }}</div>
              </div>
              <div class="panel-item-meta">
                <span class="panel-item-time">{{
                  formatTime(item.createdAt)
                }}</span>
                <span v-if="!item.read" class="panel-pill">{{
                  t("notif_new")
                }}</span>
              </div>
            </div>

            <div v-if="panelLoading" class="panel-loading">
              <i class="fas fa-spinner fa-spin"></i>
              {{ t("notif_loadingMore") }}
            </div>
            <div v-else-if="panelError" class="panel-error">
              <i class="fas fa-exclamation-circle"></i>
              <span>{{ panelError }}</span>
            </div>
            <div
              v-else-if="!panelNotifications.length && !panelLoading"
              class="panel-empty"
            >
              <i class="fas fa-inbox"></i>
              <p>{{ t("notif_noneFound") }}</p>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Notification Detail Modal -->
    <Teleport to="body">
      <div
        v-if="
          selectedNotification &&
          selectedNotification.notificationType !== 'client_ticket'
        "
        class="notification-detail-overlay"
        @click.self="closeDetailModal"
      >
        <div class="notification-detail-modal">
          <div class="detail-header">
            <div>
              <h3>{{ selectedNotification.title }}</h3>
              <span class="detail-time">{{
                formatTime(selectedNotification.createdAt)
              }}</span>
            </div>
            <button class="btn-text" @click="closeDetailModal">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="detail-body">
            <p>{{ selectedNotification.message }}</p>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useRouter } from "vue-router";
import adminNotificationApi from "@/services/adminNotificationApi";
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();
const router = useRouter();
const isOpen = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);
const loading = ref(false);

// Panel state
const panelVisible = ref(false);
const panelNotifications = ref([]);
const panelPage = ref(1);
const panelHasMore = ref(true);
const panelLoading = ref(false);
const panelError = ref("");
const selectedNotification = ref(null);
const panelListRef = ref(null);

const PANEL_PAGE_SIZE = 20;
const SCROLL_THRESHOLD = 120;

const toggleNotifications = async () => {
  isOpen.value = !isOpen.value;
  if (isOpen.value) {
    await loadNotifications();
  }
};

const closeNotifications = () => {
  isOpen.value = false;
};

const loadNotifications = async () => {
  loading.value = true;
  try {
    const response = await adminNotificationApi.getNotifications({
      limit: 3,
      offset: 0,
    });
    if (response.success) {
      notifications.value = (response.data.notifications || []).map((n) => {
        // 处理 metadata JSON
        let metadata = null;
        if (n.metadata) {
          if (typeof n.metadata === "string") {
            try {
              metadata = JSON.parse(n.metadata);
            } catch (e) {
              metadata = null;
            }
          } else {
            metadata = n.metadata;
          }
        }

        return {
          id: n.id,
          type: getNotificationType(n.type),
          title: n.subject,
          message: n.message,
          createdAt: n.createdAt,
          read: n.isRead == 1 || n.isRead === true || n.isRead === "1",
          metadata: metadata,
          notificationType: n.type,
        };
      });
      unreadCount.value = response.data.unreadCount || 0;
    }
  } catch (err) {
    console.error("Failed to load notifications:", err);
  } finally {
    loading.value = false;
  }
};

const loadUnreadCount = async () => {
  try {
    const response = await adminNotificationApi.getUnreadCount();
    if (response.success) {
      unreadCount.value = response.data.unreadCount || 0;
    }
  } catch (err) {
    console.error("Failed to load unread count:", err);
  }
};

const handleNotificationClick = async (notification) => {
  // 先标记为已读
  if (!notification.read) {
    await markAsRead(notification.id);
  }

  // 根据消息类型处理
  if (notification.notificationType === "client_ticket") {
    // 工单消息：跳转到工单列表
    if (notification.metadata && notification.metadata.actionUrl) {
      router.push(notification.metadata.actionUrl);
      closeNotifications();
    }
  } else {
    // 普通消息：显示详情弹窗
    selectedNotification.value = { ...notification, read: true };
  }
};

const markAsRead = async (id) => {
  const notification =
    notifications.value.find((n) => n.id === id) ||
    panelNotifications.value.find((n) => n.id === id);
  if (notification && !notification.read) {
    try {
      await adminNotificationApi.markAsRead(id);
      notification.read = true;
      unreadCount.value = Math.max(0, unreadCount.value - 1);
    } catch (err) {
      console.error("Failed to mark notification as read:", err);
    }
  }
};

const markAllAsRead = async () => {
  try {
    await adminNotificationApi.markAllAsRead();
    notifications.value.forEach((n) => {
      n.read = true;
    });
    panelNotifications.value.forEach((n) => {
      n.read = true;
    });
    unreadCount.value = 0;
  } catch (err) {
    console.error("Failed to mark all as read:", err);
  }
};

const openPanel = async () => {
  isOpen.value = false;
  panelVisible.value = true;
  panelPage.value = 1;
  panelHasMore.value = true;
  panelNotifications.value = [];
  await fetchPanelNotifications({ reset: true });
};

const closePanel = () => {
  panelVisible.value = false;
  selectedNotification.value = null;
};

const closeDetailModal = () => {
  selectedNotification.value = null;
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
    const offset = (panelPage.value - 1) * PANEL_PAGE_SIZE;
    const response = await adminNotificationApi.getNotifications({
      limit: PANEL_PAGE_SIZE,
      offset,
    });
    if (response.success) {
      const items = (response.data.notifications || []).map((n) => {
        let metadata = null;
        if (n.metadata) {
          if (typeof n.metadata === "string") {
            try {
              metadata = JSON.parse(n.metadata);
            } catch (e) {
              metadata = null;
            }
          } else {
            metadata = n.metadata;
          }
        }

        return {
          id: n.id,
          type: getNotificationType(n.type),
          title: n.subject,
          message: n.message,
          createdAt: n.createdAt,
          read: n.isRead == 1 || n.isRead === true || n.isRead === "1",
          metadata: metadata,
          notificationType: n.type,
        };
      });

      panelNotifications.value = [...panelNotifications.value, ...items];

      const total = response.data.total || 0;
      panelHasMore.value = panelNotifications.value.length < total;
      if (panelHasMore.value) {
        panelPage.value += 1;
      }
    }
  } catch (err) {
    console.error("Failed to load panel notifications:", err);
    panelError.value =
      err.response?.data?.message || "Failed to load notifications";
  } finally {
    panelLoading.value = false;
  }
};

const handlePanelScroll = () => {
  const el = panelListRef.value;
  if (!el || panelLoading.value || !panelHasMore.value) return;

  const scrollTop = el.scrollTop;
  const scrollHeight = el.scrollHeight;
  const clientHeight = el.clientHeight;

  if (scrollHeight - scrollTop - clientHeight < SCROLL_THRESHOLD) {
    fetchPanelNotifications();
  }
};

const selectNotification = async (notification) => {
  // 先标记为已读
  if (!notification.read) {
    await markAsRead(notification.id);
  }

  // 根据消息类型处理
  if (notification.notificationType === "client_ticket") {
    // 工单消息：跳转到工单列表
    if (notification.metadata && notification.metadata.actionUrl) {
      router.push(notification.metadata.actionUrl);
      closePanel();
    }
  } else {
    // 普通消息：显示详情弹窗
    selectedNotification.value = { ...notification, read: true };
  }
};

const deleteNotification = (id) => {
  // 暂时不实现删除功能，因为后端没有提供删除接口
  console.log("Delete notification:", id);
};

const clearAll = () => {
  // 暂时不实现清除功能
  console.log("Clear all notifications");
};

const getNotificationType = (type) => {
  const typeMap = {
    client_ticket: "info",
    common: "info",
  };
  return typeMap[type] || "info";
};

const getIcon = (type) => {
  const icons = {
    info: "fas fa-info-circle",
    success: "fas fa-check-circle",
    warning: "fas fa-exclamation-triangle",
    error: "fas fa-times-circle",
    client_ticket: "fas fa-ticket-alt",
    common: "fas fa-bell",
  };
  return icons[type] || "fas fa-bell";
};

const formatTime = (date) => {
  if (!date) return "";
  const now = new Date();
  const diff = now - new Date(date);
  const minutes = Math.floor(diff / 60000);
  const hours = Math.floor(diff / 3600000);
  const days = Math.floor(diff / 86400000);

  if (minutes < 60) return `${minutes} minute${minutes !== 1 ? "s" : ""} ago`;
  if (hours < 24) return `${hours} hour${hours !== 1 ? "s" : ""} ago`;
  return `${days} day${days !== 1 ? "s" : ""} ago`;
};

const handleClickOutside = (event) => {
  if (
    isOpen.value &&
    !event.target.closest(".notification-wrapper") &&
    !event.target.closest(".notification-panel")
  ) {
    closeNotifications();
  }
};

let unreadCountInterval = null;

onMounted(() => {
  document.addEventListener("click", handleClickOutside);
  // 初始加载未读数量
  loadUnreadCount();
  // 每120秒刷新一次未读数量
  unreadCountInterval = setInterval(loadUnreadCount, 120000);
});

onUnmounted(() => {
  document.removeEventListener("click", handleClickOutside);
  if (unreadCountInterval) {
    clearInterval(unreadCountInterval);
  }
});
</script>

<style scoped>
.notification-wrapper {
  position: relative;
}

.notification-btn {
  position: relative;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  width: 44px;
  height: 44px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
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
  background: var(--color-danger-solid);
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

.notification-panel {
  position: fixed;
  top: 80px;
  right: 20px;
  width: 400px;
  max-width: calc(100vw - 40px);
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
  z-index: 1000;
  animation: slideIn 0.3s ease;
  max-height: calc(100vh - 100px);
  display: flex;
  flex-direction: column;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.notification-header {
  padding: 20px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.notification-header h3 {
  font-size: 18px;
  color: var(--color-ink);
  display: flex;
  align-items: center;
  gap: 10px;
}

.notification-header h3 i {
  color: var(--color-brand);
}

.notification-close {
  width: 32px;
  height: 32px;
  border-radius: var(--radius-sm);
  background: var(--color-surface-soft);
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  font-size: 16px;
  color: var(--color-text);
}

.notification-close:hover {
  background: var(--color-border);
  color: var(--color-ink);
}

.notification-body {
  flex: 1;
  overflow-y: auto;
  max-height: 400px;
}

.notification-body::-webkit-scrollbar {
  width: 6px;
}

.notification-body::-webkit-scrollbar-track {
  background: var(--color-surface-soft);
}

.notification-body::-webkit-scrollbar-thumb {
  background: var(--color-border-strong);
  border-radius: 3px;
}

.notification-empty {
  padding: 60px 20px;
  text-align: center;
  color: var(--color-faint);
}

.notification-empty i {
  font-size: 48px;
  margin-bottom: 15px;
  display: block;
}

.notification-empty p {
  font-size: 16px;
}

.notification-loading {
  padding: 60px 20px;
  text-align: center;
  color: var(--color-faint);
}

.notification-loading i {
  font-size: 32px;
  margin-bottom: 15px;
  display: block;
  color: var(--color-brand);
}

.notification-loading p {
  font-size: 14px;
}

.btn-text:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.notification-item {
  padding: 15px 20px;
  border-bottom: 1px solid #f1f3f5;
  display: flex;
  gap: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
}

.notification-item:hover {
  background: var(--color-surface-soft);
}

.notification-item.unread {
  background: var(--color-brand-soft);
}

.notification-item.unread::before {
  content: "";
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: var(--color-brand-solid);
}

.notification-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
}

.notification-icon.info {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.notification-icon.success {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.notification-icon.warning {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.notification-icon.error {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.notification-icon.client_ticket {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.notification-content {
  flex: 1;
  min-width: 0;
}

.notification-title {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  margin-bottom: 4px;
}

.notification-message {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  color: var(--color-muted);
  font-size: 13px;
  line-height: 1.4;
  margin-bottom: 6px;
}

.notification-time {
  color: var(--color-faint);
  font-size: 12px;
}

.notification-delete {
  width: 24px;
  height: 24px;
  border-radius: 4px;
  background: transparent;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  font-size: 14px;
  color: var(--color-faint);
  flex-shrink: 0;
  opacity: 0;
}

.notification-item:hover .notification-delete {
  opacity: 1;
}

.notification-delete:hover {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.notification-footer {
  padding: 15px 20px;
  border-top: 2px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  gap: 10px;
}

.btn-text {
  background: none;
  border: none;
  color: var(--color-brand);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  padding: 8px 12px;
  border-radius: var(--radius-sm);
  transition: all 0.2s ease;
}

.btn-text:hover {
  background: var(--color-brand-soft);
  color: var(--color-brand-strong);
}

@media (max-width: 768px) {
  .notification-panel {
    top: 70px;
    right: 10px;
    width: calc(100vw - 20px);
  }
}

/* Panel Overlay */
.notification-panel-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.notification-panel-full {
  width: 480px;
  max-width: 90vw;
  height: 100vh;
  background: var(--color-surface);
  display: flex;
  flex-direction: column;
  box-shadow: -4px 0 20px rgba(0, 0, 0, 0.15);
  animation: slideInRight 0.3s ease;
}

@keyframes slideInRight {
  from {
    transform: translateX(100%);
  }
  to {
    transform: translateX(0);
  }
}

.panel-header {
  padding: 24px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-shrink: 0;
}

.panel-header h2 {
  font-size: 20px;
  color: var(--color-ink);
  margin-bottom: 4px;
}

.panel-subtitle {
  font-size: 13px;
  color: var(--color-muted);
}

.panel-actions {
  display: flex;
  gap: 12px;
  align-items: center;
}

.panel-list {
  flex: 1;
  overflow-y: auto;
  padding: 0;
}

.panel-list::-webkit-scrollbar {
  width: 6px;
}

.panel-list::-webkit-scrollbar-track {
  background: var(--color-surface-soft);
}

.panel-list::-webkit-scrollbar-thumb {
  background: var(--color-border-strong);
  border-radius: 3px;
}

.panel-item {
  padding: 16px 24px;
  border-bottom: 1px solid #f1f3f5;
  display: flex;
  gap: 16px;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
}

.panel-item:hover {
  background: var(--color-surface-soft);
}

.panel-item.unread {
  background: var(--color-brand-soft);
}

.panel-item.unread::before {
  content: "";
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: var(--color-brand-solid);
}

.panel-item.active {
  background: var(--color-brand-soft);
}

.panel-item-icon-wrapper {
  flex-shrink: 0;
}

.panel-item-icon {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
}

.panel-item-icon.info {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.panel-item-icon.client_ticket {
  background: var(--color-info-soft);
  color: var(--color-info);
}

.panel-item-main {
  flex: 1;
  min-width: 0;
}

.panel-item-title {
  font-weight: 600;
  color: var(--color-ink);
  font-size: 14px;
  margin-bottom: 4px;
}

.panel-item-message {
  color: var(--color-muted);
  font-size: 13px;
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
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
  white-space: nowrap;
}

.panel-pill {
  background: var(--color-brand-solid);
  color: white;
  font-size: 10px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: var(--radius-lg);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.panel-loading,
.panel-error,
.panel-empty {
  padding: 60px 24px;
  text-align: center;
  color: var(--color-faint);
}

.panel-loading i,
.panel-error i {
  font-size: 32px;
  margin-bottom: 15px;
  display: block;
  color: var(--color-brand);
}

.panel-error i {
  color: var(--color-danger);
}

.panel-empty i {
  font-size: 64px;
  margin-bottom: 20px;
  display: block;
  color: var(--color-border-strong);
}

.panel-error span {
  display: block;
  margin-top: 10px;
  font-size: 14px;
}

/* Notification Detail Modal */
.notification-detail-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 3000;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: fadeIn 0.2s ease;
}

.notification-detail-modal {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  width: 90%;
  max-width: 600px;
  max-height: 80vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.detail-header {
  padding: 24px;
  border-bottom: 2px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-shrink: 0;
}

.detail-header h3 {
  font-size: 20px;
  color: var(--color-ink);
  margin-bottom: 8px;
}

.detail-time {
  font-size: 13px;
  color: var(--color-muted);
}

.detail-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
}

.detail-body p {
  color: var(--color-text);
  line-height: 1.6;
  white-space: pre-wrap;
  margin: 0;
}
</style>
