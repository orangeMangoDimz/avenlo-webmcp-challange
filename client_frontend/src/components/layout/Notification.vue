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
          <h3><i class="fas fa-bell"></i> Notifications</h3>
          <button class="notification-close" @click="closeNotifications">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="notification-body">
          <div v-if="notifications.length === 0" class="notification-empty">
            <i class="fas fa-bell-slash"></i>
            <p>No notifications</p>
          </div>
          <div
            v-for="notification in notifications"
            :key="notification.id"
            class="notification-item"
            :class="{ unread: !notification.read }"
            @click="markAsRead(notification.id)"
          >
            <div class="notification-icon" :class="notification.type">
              <i :class="getIcon(notification.type)"></i>
            </div>
            <div class="notification-content">
              <div class="notification-title">{{ notification.title }}</div>
              <div class="notification-message">{{ notification.message }}</div>
              <div class="notification-time">
                {{ formatTime(notification.createdAt) }}
              </div>
            </div>
            <button
              class="notification-delete"
              @click.stop="deleteNotification(notification.id)"
            >
              <i class="fas fa-times"></i>
            </button>
          </div>
        </div>
        <div class="notification-footer">
          <button class="btn-text" @click="markAllAsRead">
            Mark all as read
          </button>
          <button class="btn-text" @click="clearAll">Clear all</button>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";

const isOpen = ref(false);
const notifications = ref([
  {
    id: 1,
    type: "info",
    title: "System Update",
    message: "System will be updated tonight at 2:00 AM",
    createdAt: new Date(Date.now() - 3600000),
    read: false,
  },
  {
    id: 2,
    type: "success",
    title: "Account Created",
    message: 'New admin account "john.doe" has been created',
    createdAt: new Date(Date.now() - 7200000),
    read: false,
  },
  {
    id: 3,
    type: "warning",
    title: "Failed Login Attempt",
    message: "3 failed login attempts detected from IP 192.168.1.1",
    createdAt: new Date(Date.now() - 10800000),
    read: true,
  },
]);

const unreadCount = computed(() => {
  return notifications.value.filter((n) => !n.read).length;
});

const toggleNotifications = () => {
  isOpen.value = !isOpen.value;
};

const closeNotifications = () => {
  isOpen.value = false;
};

const markAsRead = (id) => {
  const notification = notifications.value.find((n) => n.id === id);
  if (notification) {
    notification.read = true;
  }
};

const markAllAsRead = () => {
  notifications.value.forEach((n) => {
    n.read = true;
  });
};

const deleteNotification = (id) => {
  const index = notifications.value.findIndex((n) => n.id === id);
  if (index > -1) {
    notifications.value.splice(index, 1);
  }
};

const clearAll = () => {
  if (confirm("Are you sure you want to clear all notifications?")) {
    notifications.value = [];
  }
};

const getIcon = (type) => {
  const icons = {
    info: "fas fa-info-circle",
    success: "fas fa-check-circle",
    warning: "fas fa-exclamation-triangle",
    error: "fas fa-times-circle",
  };
  return icons[type] || "fas fa-bell";
};

const formatTime = (date) => {
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
  border: 1px solid var(--color-border);
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
  font-size: 14px;
  font-weight: 600;
  border: 1px solid var(--color-canvas);
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
  border-bottom: 1px solid var(--color-border);
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

.notification-item {
  padding: 15px 20px;
  border-bottom: 1px solid var(--color-border);
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
  color: var(--color-muted);
  font-size: 14px;
  line-height: 1.4;
  margin-bottom: 6px;
}

.notification-time {
  color: var(--color-faint);
  font-size: 14px;
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
  border-top: 1px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  gap: 10px;
}

.btn-text {
  background: none;
  border: none;
  color: var(--color-brand);
  font-size: 14px;
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
</style>
