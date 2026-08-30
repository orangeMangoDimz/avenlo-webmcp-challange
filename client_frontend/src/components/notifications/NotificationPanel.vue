<template>
  <Teleport to="body">
    <div
      v-if="visible"
      class="notification-panel-overlay"
      @click.self="handleClose"
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
              v-if="notifications.length && unreadCount > 0"
              class="btn-text"
              @click="$emit('mark-all-read')"
            >
              {{ t("notifMarkAllRead", "Mark all as read") }}
            </button>
            <button class="btn-text" @click="handleClose">
              {{ t("notifClose", "Close") }}
            </button>
          </div>
        </div>

        <div class="panel-list" ref="panelListRef" @scroll="handleScroll">
          <div
            v-for="item in notifications"
            :key="item.id"
            :class="[
              'panel-item',
              {
                unread: !item.isRead,
                active: selectedId && selectedId === item.id,
              },
            ]"
            @click="$emit('select', item)"
          >
            <div class="panel-item-main">
              <div class="panel-item-title">{{ item.subject }}</div>
              <div class="panel-item-message">{{ item.message }}</div>
            </div>
            <div class="panel-item-meta">
              <span class="panel-item-time">{{
                formatTime(item.createdAt)
              }}</span>
              <span v-if="!item.isRead" class="panel-pill">{{
                t("notifNew", "New")
              }}</span>
            </div>
          </div>

          <div v-if="loading" class="panel-loading">
            <i class="fas fa-spinner fa-spin"></i>
            {{ t("notifLoadingMore", "Loading more...") }}
          </div>
          <div v-else-if="error" class="panel-error">
            <i class="fas fa-exclamation-circle"></i>
            <span>{{ error }}</span>
          </div>
          <div v-else-if="!notifications.length" class="panel-empty">
            <i class="fas fa-inbox"></i>
            <p>
              {{ t("notifNoNotificationsFound", "No notifications found") }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref } from "vue";
import { useLanguageStore } from "@/stores/language";

const languageStore = useLanguageStore();
const t = (key, fallback) => languageStore.t(key, fallback);

defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  notifications: {
    type: Array,
    default: () => [],
  },
  unreadCount: {
    type: Number,
    default: 0,
  },
  selectedId: {
    type: Number,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  error: {
    type: String,
    default: "",
  },
});

const emit = defineEmits(["close", "select", "scroll", "mark-all-read"]);

const panelListRef = ref(null);

const handleClose = () => {
  emit("close");
};

const handleScroll = () => {
  const el = panelListRef.value;
  if (!el) return;
  emit("scroll", {
    scrollTop: el.scrollTop,
    scrollHeight: el.scrollHeight,
    clientHeight: el.clientHeight,
  });
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
</script>

<style scoped>
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
  background: var(--color-surface);
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
  font-size: 14px;
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
  font-size: 14px;
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
  background: var(--color-surface-soft);
}

.panel-list::-webkit-scrollbar-thumb {
  background: var(--color-surface-muted);
  border-radius: 4px;
}

.panel-item {
  padding: 18px 20px;
  margin: 0 12px;
  border-bottom: 1px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  gap: 16px;
  cursor: pointer;
  transition:
    background 0.2s ease,
    transform 0.2s ease;
}

.panel-item:last-of-type {
  border-bottom: none;
}

.panel-item:hover {
  background: var(--color-surface-soft);
}

.panel-item.unread {
  background: var(--color-brand-soft);
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
  font-size: 14px;
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
  font-size: 14px;
  color: var(--color-faint);
}

.panel-pill {
  background: var(--color-brand-solid);
  color: white;
  font-size: 14px;
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
  color: var(--color-muted);
}

.panel-empty i,
.panel-error i {
  display: block;
  font-size: 36px;
  margin-bottom: 12px;
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
}
</style>
