<template>
  <div class="notification-wrapper">
    <button
      class="notification-btn"
      @click="toggleDropdown"
      ref="notificationBtn"
    >
      <i class="fas fa-bell"></i>
      <span v-if="unreadCount > 0" class="notification-badge">{{
        unreadCount
      }}</span>
    </button>

    <div
      v-if="showDropdown"
      class="notification-dropdown"
      ref="notificationDropdown"
    >
      <div class="notification-header">
        <h3>Notifications</h3>
        <button
          v-if="notifications.length > 0"
          class="btn-mark-all"
          @click="markAllAsRead"
        >
          Mark all as read
        </button>
      </div>

      <div class="notification-list">
        <div
          v-for="notification in notifications"
          :key="notification.id"
          :class="['notification-item', { unread: !notification.read }]"
          @click="markAsRead(notification.id)"
        >
          <div class="notification-icon" :class="notification.type">
            <i :class="getNotificationIcon(notification.type)"></i>
          </div>
          <div class="notification-content">
            <div class="notification-title">{{ notification.title }}</div>
            <div class="notification-message">{{ notification.message }}</div>
            <div class="notification-time">
              {{ formatTime(notification.createdAt) }}
            </div>
          </div>
          <button
            class="notification-close"
            @click.stop="removeNotification(notification.id)"
          >
            <i class="fas fa-times"></i>
          </button>
        </div>

        <div v-if="notifications.length === 0" class="notification-empty">
          <i class="fas fa-bell-slash"></i>
          <p>No new notifications</p>
        </div>
      </div>

      <div v-if="notifications.length > 0" class="notification-footer">
        <button class="btn-view-all" @click="viewAllNotifications">
          View All Notifications
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";

const showDropdown = ref(false);
const notificationBtn = ref(null);
const notificationDropdown = ref(null);

const notifications = ref([
  {
    id: 1,
    type: "success",
    title: "Deposit Successful",
    message: "Your deposit of $5,000 has been processed successfully.",
    createdAt: new Date(Date.now() - 5 * 60000), // 5 minutes ago
    read: false,
  },
  {
    id: 2,
    type: "info",
    title: "Document Verification",
    message:
      "Please upload your proof of address to complete KYC verification.",
    createdAt: new Date(Date.now() - 2 * 3600000), // 2 hours ago
    read: false,
  },
  {
    id: 3,
    type: "warning",
    title: "Margin Call Warning",
    message: "Your account margin level is below 120%. Please add funds.",
    createdAt: new Date(Date.now() - 24 * 3600000), // 1 day ago
    read: true,
  },
]);

const unreadCount = computed(() => {
  return notifications.value.filter((n) => !n.read).length;
});

const toggleDropdown = () => {
  showDropdown.value = !showDropdown.value;
};

const markAsRead = (notificationId) => {
  const notification = notifications.value.find((n) => n.id === notificationId);
  if (notification) {
    notification.read = true;
  }
};

const markAllAsRead = () => {
  notifications.value.forEach((n) => {
    n.read = true;
  });
};

const removeNotification = (notificationId) => {
  const index = notifications.value.findIndex((n) => n.id === notificationId);
  if (index !== -1) {
    notifications.value.splice(index, 1);
  }
};

const viewAllNotifications = () => {
  showDropdown.value = false;
  // Navigate to notifications page
  // router.push('/client/notifications')
};

const getNotificationIcon = (type) => {
  const icons = {
    success: "fas fa-check-circle",
    info: "fas fa-info-circle",
    warning: "fas fa-exclamation-triangle",
    error: "fas fa-times-circle",
  };
  return icons[type] || "fas fa-bell";
};

const formatTime = (date) => {
  const now = new Date();
  const diff = Math.floor((now - date) / 1000); // seconds

  if (diff < 60) {
    return "Just now";
  } else if (diff < 3600) {
    const minutes = Math.floor(diff / 60);
    return `${minutes} minute${minutes > 1 ? "s" : ""} ago`;
  } else if (diff < 86400) {
    const hours = Math.floor(diff / 3600);
    return `${hours} hour${hours > 1 ? "s" : ""} ago`;
  } else if (diff < 604800) {
    const days = Math.floor(diff / 86400);
    return `${days} day${days > 1 ? "s" : ""} ago`;
  } else {
    return date.toLocaleDateString("en-US", {
      month: "short",
      day: "numeric",
      year: date.getFullYear() !== now.getFullYear() ? "numeric" : undefined,
    });
  }
};

const handleClickOutside = (event) => {
  if (notificationBtn.value && notificationDropdown.value) {
    if (
      !notificationBtn.value.contains(event.target) &&
      !notificationDropdown.value.contains(event.target)
    ) {
      showDropdown.value = false;
    }
  }
};

onMounted(() => {
  document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener("click", handleClickOutside);
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

.notification-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  width: 380px;
  max-height: 500px;
  display: flex;
  flex-direction: column;
  z-index: 1000;
  animation: slideDown 0.3s ease;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
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
  max-height: 400px;
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
  padding: 15px 20px;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  border-bottom: 1px solid var(--color-border);
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
  width: 3px;
  height: 100%;
  background: var(--color-brand-solid);
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

.notification-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
  margin-bottom: 4px;
}

.notification-message {
  font-size: 13px;
  color: var(--color-muted);
  line-height: 1.4;
  margin-bottom: 4px;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.notification-time {
  font-size: 12px;
  color: var(--color-faint);
}

.notification-close {
  background: none;
  border: none;
  color: var(--color-faint);
  cursor: pointer;
  padding: 4px;
  transition: color 0.2s ease;
  flex-shrink: 0;
}

.notification-close:hover {
  color: var(--color-danger);
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
  font-size: 14px;
  font-style: italic;
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
  transition: all 0.2s ease;
}

.btn-view-all:hover {
  background: var(--color-brand-solid);
  color: white;
}

@media (max-width: 768px) {
  .notification-dropdown {
    width: 320px;
    right: -20px;
  }
}
</style>
