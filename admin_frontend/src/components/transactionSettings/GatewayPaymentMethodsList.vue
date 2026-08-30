<template>
  <div class="payment-methods-list">
    <div v-if="methods.length === 0" class="empty-state">
      <i class="fas fa-wallet"></i>
      <p>{{ t("txnSettings_paymentMethodsEmpty") }}</p>
    </div>

    <div
      v-for="method in methods"
      :key="method.value"
      class="toggle-switch-wrapper"
    >
      <div class="toggle-label">
        <div class="method-title-row">
          <div class="toggle-label-title">
            {{ method.label || method.value }}
          </div>
          <span
            :class="[
              'status-badge',
              isMethodEnabled(method) ? 'active' : 'inactive',
            ]"
          >
            {{
              isMethodEnabled(method)
                ? t("txnSettings_statusEnabled")
                : t("txnSettings_statusDisabled")
            }}
          </span>
        </div>
        <div class="toggle-label-description">{{ method.value || "-" }}</div>
      </div>
      <div
        :class="[
          'toggle-switch',
          {
            active: isMethodEnabled(method),
            disabled: !canEdit || togglingId === method.value,
          },
        ]"
        @click="
          canEdit && togglingId !== method.value
            ? $emit('toggle', method)
            : null
        "
      ></div>
    </div>
  </div>
</template>

<script setup>
import { useAdminI18n } from "@/composables/useAdminI18n";

const { t } = useAdminI18n();

defineProps({
  methods: {
    type: Array,
    default: () => [],
  },
  canEdit: {
    type: Boolean,
    default: false,
  },
  togglingId: {
    type: [Number, String],
    default: null,
  },
});

defineEmits(["toggle"]);

const isMethodEnabled = (method) => method?.isEnabled !== false;
</script>

<style scoped>
.payment-methods-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.toggle-switch-wrapper {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 15px 20px;
  background: var(--color-surface-soft);
  border-radius: var(--radius-md);
}

.toggle-label {
  flex: 1;
  min-width: 0;
}

.method-title-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.toggle-label-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-ink);
}

.toggle-label-description {
  margin-top: 4px;
  font-size: 14px;
  color: var(--color-muted);
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: var(--radius-lg);
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.active {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.status-badge.inactive {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.toggle-switch {
  position: relative;
  width: 40px;
  height: 22px;
  background: var(--color-border-strong);
  border-radius: 11px;
  cursor: pointer;
  transition: all 0.3s ease;
  flex-shrink: 0;
}

.toggle-switch.active {
  background: var(--color-success-solid);
}

.toggle-switch::after {
  content: "";
  position: absolute;
  top: 2px;
  left: 2px;
  width: 18px;
  height: 18px;
  background: var(--color-surface);
  border-radius: 50%;
  transition: all 0.3s ease;
}

.toggle-switch.active::after {
  left: 20px;
}

.toggle-switch.disabled {
  cursor: not-allowed;
  opacity: 0.7;
  background: var(--color-border) !important;
}

.toggle-switch.disabled.active {
  background: var(--color-success-soft) !important;
  opacity: 0.8;
}

.toggle-switch.disabled::after {
  background: var(--color-border-strong);
}

.toggle-switch.disabled.active::after {
  background: var(--color-surface);
}

.empty-state {
  text-align: center;
  padding: 28px 16px;
  border: 1px dashed var(--color-border);
  border-radius: var(--radius-md);
  color: var(--color-muted);
}

.empty-state i {
  font-size: 28px;
  margin-bottom: 8px;
  color: var(--color-faint);
}

.empty-state p {
  margin: 0;
}

@media (max-width: 768px) {
  .toggle-switch-wrapper {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
