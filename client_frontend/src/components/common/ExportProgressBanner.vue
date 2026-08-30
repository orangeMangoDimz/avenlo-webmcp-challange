<template>
  <div class="export-progress-banner" :class="{ 'is-cancelling': cancelling }">
    <div class="export-progress-banner__main">
      <span class="export-progress-banner__icon">
        <i :class="cancelling ? 'fas fa-times' : 'fas fa-file-export'"></i>
      </span>
      <div class="export-progress-banner__copy">
        <strong>{{ cancelling ? cancellingTitle : title }}</strong>
        <div class="export-progress-banner__status">
          <span>{{ statusText }}</span>
          <div class="export-progress-banner__track">
            <div
              class="export-progress-banner__bar"
              :style="{ width: `${barWidth}%` }"
            ></div>
          </div>
        </div>
      </div>
    </div>
    <button
      v-if="!cancelling"
      type="button"
      class="export-progress-banner__cancel"
      :disabled="cancelDisabled"
      @click.stop="$emit('cancel-export')"
    >
      {{ cancelLabel }}
    </button>
  </div>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
  cancelling: {
    type: Boolean,
    default: false,
  },
  statusText: {
    type: String,
    default: "",
  },
  percent: {
    type: Number,
    default: 0,
  },
  cancelDisabled: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: "Export in progress",
  },
  cancellingTitle: {
    type: String,
    default: "Cancelling export...",
  },
  cancelLabel: {
    type: String,
    default: "Cancel",
  },
});

defineEmits(["cancel-export"]);

const barWidth = computed(() =>
  Math.max(0, Math.min(100, Number(props.percent || 0))),
);
</script>

<style scoped>
.export-progress-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
  padding: 16px 20px;
  background: white;
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.export-progress-banner__main {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  min-width: 0;
  flex: 1;
}

.export-progress-banner__icon {
  width: 40px;
  height: 40px;
  flex-shrink: 0;
  border-radius: var(--radius-md);
  background: var(--color-brand);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.export-progress-banner.is-cancelling .export-progress-banner__icon {
  background: var(--color-danger);
}

.export-progress-banner__copy {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
  flex: 1;
}

.export-progress-banner__copy strong {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

.export-progress-banner__copy span {
  font-size: 13px;
  color: var(--color-muted);
  flex-shrink: 0;
}

.export-progress-banner__status {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.export-progress-banner__track {
  flex: 1;
  min-width: 80px;
  height: 8px;
  background: var(--color-border);
  border-radius: 999px;
  overflow: hidden;
}

.export-progress-banner__bar {
  height: 100%;
  background: var(--color-brand);
  transition: width 0.3s ease;
}

.export-progress-banner.is-cancelling .export-progress-banner__bar {
  background: var(--color-danger);
}

.export-progress-banner__cancel {
  flex-shrink: 0;
  padding: 12px 24px;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-danger);
  color: #fff;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.export-progress-banner__cancel:hover:not(:disabled) {
  background: var(--color-danger);
}

.export-progress-banner__cancel:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
