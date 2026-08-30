<template>
  <Teleport to="body">
    <div
      v-if="notification"
      class="notification-detail-overlay"
      @click.self="handleClose"
    >
      <div class="notification-detail-modal">
        <div class="detail-header">
          <div>
            <h3>{{ notification.subject }}</h3>
            <span class="detail-time">{{
              formatTime(notification.createdAt)
            }}</span>
          </div>
          <button class="btn-text" @click="handleClose">
            {{ t("notifClose", "Close") }}
          </button>
        </div>
        <div class="detail-body">
          <p>{{ notification.message }}</p>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { useLanguageStore } from "@/stores/language";

const languageStore = useLanguageStore();
const t = (key, fallback) => languageStore.t(key, fallback);

const props = defineProps({
  notification: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["close"]);

const handleClose = () => {
  emit("close");
};

const formatTime = (value) => {
  if (!value) return "";
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return "";

  const now = new Date();
  const diffSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

  if (diffSeconds < 60) {
    return t("notifJustNow", "Just now");
  }
  if (diffSeconds < 3600) {
    const minutes = Math.floor(diffSeconds / 60);
    return (
      minutes +
      " " +
      (minutes > 1
        ? t("notifMinutesAgo", "minutes ago")
        : t("notifMinuteAgo", "minute ago"))
    );
  }
  if (diffSeconds < 86400) {
    const hours = Math.floor(diffSeconds / 3600);
    return (
      hours +
      " " +
      (hours > 1
        ? t("notifHoursAgo", "hours ago")
        : t("notifHourAgo", "hour ago"))
    );
  }
  if (diffSeconds < 604800) {
    const days = Math.floor(diffSeconds / 86400);
    return (
      days +
      " " +
      (days > 1 ? t("notifDaysAgo", "days ago") : t("notifDayAgo", "day ago"))
    );
  }
  return date.toLocaleDateString(undefined, {
    month: "short",
    day: "numeric",
    year: date.getFullYear() !== now.getFullYear() ? "numeric" : undefined,
  });
};
</script>

<style scoped>
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
  max-height: 70vh;
  background: #ffffff;
  border-radius: var(--radius-xl);
  padding: 28px 32px;
  box-shadow: 0 35px 70px rgba(15, 23, 42, 0.3);
  display: flex;
  flex-direction: column;
  gap: 18px;
  overflow: hidden;
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

.detail-body {
  font-size: 14px;
  color: #475569;
  line-height: 1.6;
  white-space: pre-line;
  overflow-y: auto;
}

@media (max-width: 768px) {
  .notification-detail-modal {
    width: 100%;
    max-height: 80vh;
  }
}
</style>
